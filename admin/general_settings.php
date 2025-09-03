<?php
// Admin General Settings: branding, company info, social links with logo upload
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/helpers/settings.php';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$status  = '';
$errors  = [];

// Keys managed on this page
$settingKeys = [
    'site_title', 'site_tagline', 'site_description',
    'company_name', 'company_email', 'company_phone', 'company_address',
    'social_facebook', 'social_twitter', 'social_instagram',
    'site_logo'
];

// Load existing values (used for initial state and safe logo removal)
$settings = get_settings($conn, $settingKeys);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    // Normalize inputs
    $fields = [
        'site_title', 'site_tagline', 'site_description',
        'company_name', 'company_email', 'company_phone', 'company_address',
        'social_facebook', 'social_twitter', 'social_instagram'
    ];

    // Validation helpers
    $email = trim($_POST['company_email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid company email address.';
    }

    $urlFields = ['social_facebook', 'social_twitter', 'social_instagram'];
    foreach ($urlFields as $uf) {
        $url = trim($_POST[$uf] ?? '');
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $label = ucwords(str_replace('_', ' ', str_replace('social_', '', $uf)));
            $errors[] = "Invalid URL for {$label}.";
        }
    }

    // If requested, remove current logo
    if (isset($_POST['remove_logo']) && ($_POST['remove_logo'] === '1')) {
        $currentLogo = $settings['site_logo'] ?? '';
        if ($currentLogo) {
            $projRoot = realpath(__DIR__ . '/..');
            $filePath = realpath($projRoot . DIRECTORY_SEPARATOR . $currentLogo);
            // Ensure file is within assets/images to avoid arbitrary deletion
            $imagesDir = realpath($projRoot . '/assets/images');
            if ($filePath && $imagesDir && strpos($filePath, $imagesDir) === 0) {
                @unlink($filePath);
            }
            set_setting($conn, 'site_logo', '');
            $settings['site_logo'] = '';
        }
    }

    // Logo upload (if provided)
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['site_logo']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
            $size = (int)($_FILES['site_logo']['size'] ?? 0);
            $allowed = ['png', 'jpg', 'jpeg', 'gif', 'svg'];
            $maxBytes = 2 * 1024 * 1024; // 2MB

            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Logo must be an image of type PNG, JPG, JPEG, GIF, or SVG.';
            }
            if ($size > $maxBytes) {
                $errors[] = 'Logo file size must be 2MB or less.';
            }

            $isImage = true;
            if ($ext !== 'svg') {
                $imgInfo = @getimagesize($tmp);
                if ($imgInfo === false) {
                    $isImage = false;
                    $errors[] = 'Uploaded file does not appear to be a valid image.';
                }
            }

            if ($isImage && empty($errors) && is_uploaded_file($tmp)) {
                $filename = 'site_logo_' . uniqid('', true) . '.' . $ext;
                $targetDir = __DIR__ . '/../assets/images/';
                if (!is_dir($targetDir)) { @mkdir($targetDir, 0775, true); }
                $target = $targetDir . $filename;
                if (move_uploaded_file($tmp, $target)) {
                    // Store relative path used by admin header
                    set_setting($conn, 'site_logo', 'assets/images/' . $filename);
                    $settings['site_logo'] = 'assets/images/' . $filename;
                } else {
                    $errors[] = 'Failed to save the uploaded logo.';
                }
            }
        } else {
            $errors[] = 'Logo upload failed with error code: ' . (int)$_FILES['site_logo']['error'];
        }
    }

    // Persist text settings (skip writing invalid ones we warned about)
    foreach ($fields as $f) {
        $val = trim($_POST[$f] ?? '');
        if ($f === 'company_email' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            continue; // skip invalid email
        }
        if (in_array($f, $urlFields, true) && $val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
            continue; // skip invalid URL
        }
        set_setting($conn, $f, $val);
        $settings[$f] = $val;
    }

    if (empty($errors)) {
        $message = 'Settings saved successfully.';
        $status  = 'success';
    } else {
        $message = implode(' ', $errors);
        $status  = 'danger';
    }
}

// Reload values to ensure latest persistence (optional since we updated $settings inline)
$settings = get_settings($conn, $settingKeys);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - General</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
</head>
<body>
<?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
<div class="admin-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="content">
        <div class="page-header">
            <div>
                <h1 class="h3 mb-1">General Settings</h1>
                <div class="text-muted-600">Manage branding, company information, and social links</div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $status === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="row g-3">
                <!-- Branding -->
                <div class="col-12">
                    <div class="card shadow-soft">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Branding</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 align-items-center">
                                <div class="col-12 col-md-7">
                                    <label class="form-label fw-semibold" for="site_logo">Site Logo</label>
                                    <input type="file" class="form-control" name="site_logo" id="site_logo" accept="image/*">
                                    <div class="form-text">PNG, JPG, GIF or SVG. Max 2MB.</div>
                                    <?php if (!empty($settings['site_logo'])): ?>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" value="1" id="remove_logo" name="remove_logo">
                                            <label class="form-check-label" for="remove_logo">Remove current logo</label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-5">
                                    <div class="border rounded p-3 text-center bg-light">
                                        <div class="text-muted small mb-2">Preview</div>
                                        <?php if (!empty($settings['site_logo'])): ?>
                                            <img src="../<?php echo htmlspecialchars($settings['site_logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Current Logo" style="max-height:80px;">
                                        <?php else: ?>
                                            <div class="text-muted">No logo set</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="site_title">Site Title</label>
                                    <input type="text" class="form-control" name="site_title" id="site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Your store name">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="site_tagline">Tagline</label>
                                    <input type="text" class="form-control" name="site_tagline" id="site_tagline" value="<?php echo htmlspecialchars($settings['site_tagline'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Short description">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="site_description">Description</label>
                                    <textarea class="form-control" name="site_description" id="site_description" rows="3" placeholder="Describe your store"><?php echo htmlspecialchars($settings['site_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Company -->
                <div class="col-12">
                    <div class="card shadow-soft">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Company</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="company_name">Company Name</label>
                                    <input type="text" class="form-control" name="company_name" id="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Company LLC">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="company_email">Company Email</label>
                                    <input type="email" class="form-control" name="company_email" id="company_email" value="<?php echo htmlspecialchars($settings['company_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="support@example.com">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="company_phone">Company Phone</label>
                                    <input type="text" class="form-control" name="company_phone" id="company_phone" value="<?php echo htmlspecialchars($settings['company_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="+1 555 123 4567">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="company_address">Company Address</label>
                                    <textarea class="form-control" name="company_address" id="company_address" rows="2" placeholder="Street, City, State, ZIP"><?php echo htmlspecialchars($settings['company_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social -->
                <div class="col-12">
                    <div class="card shadow-soft">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Social</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="social_facebook">Facebook URL</label>
                                    <input type="text" class="form-control" name="social_facebook" id="social_facebook" value="<?php echo htmlspecialchars($settings['social_facebook'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://facebook.com/yourpage">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="social_twitter">Twitter URL</label>
                                    <input type="text" class="form-control" name="social_twitter" id="social_twitter" value="<?php echo htmlspecialchars($settings['social_twitter'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://x.com/yourhandle">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="social_instagram">Instagram URL</label>
                                    <input type="text" class="form-control" name="social_instagram" id="social_instagram" value="<?php echo htmlspecialchars($settings['social_instagram'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://instagram.com/yourhandle">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-admin-primary"><i class="fa fa-floppy-disk me-1"></i> Save Settings</button>
            </div>
        </form>
    </main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>
