<?php
// Admin General Settings: branding, company info, social links with logo upload and social toggles
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
    'social_facebook', 'social_facebook_enabled',
    'social_twitter', 'social_twitter_enabled',
    'social_instagram', 'social_instagram_enabled',
    'social_youtube', 'social_youtube_enabled',
    'social_linkedin', 'social_linkedin_enabled',
    'social_tiktok', 'social_tiktok_enabled',
    'social_telegram', 'social_telegram_enabled',
    'social_pinterest', 'social_pinterest_enabled',
    'whatsapp_enabled', 'whatsapp_number', 'live_chat_enabled', 'live_chat_require_name', 'live_chat_require_email',
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
        'social_facebook', 'social_twitter', 'social_instagram',
        'social_youtube', 'social_linkedin', 'social_tiktok', 'social_telegram', 'social_pinterest',
        'whatsapp_number'
    ];

    // Validation helpers
    $email = trim($_POST['company_email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid company email address.';
    }

    $urlFields = [
        'social_facebook', 'social_twitter', 'social_instagram',
        'social_youtube', 'social_linkedin', 'social_tiktok', 'social_telegram', 'social_pinterest'
    ];
    foreach ($urlFields as $uf) {
        $url = trim($_POST[$uf] ?? '');
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $label = ucwords(str_replace('_', ' ', str_replace('social_', '', $uf)));
            $errors[] = "Invalid URL for {$label}.";
        }
    }

    // WhatsApp enable/disable toggle
    $whatsapp_enabled = isset($_POST['whatsapp_enabled']) ? '1' : '0';
    set_setting($conn, 'whatsapp_enabled', $whatsapp_enabled);
    $settings['whatsapp_enabled'] = $whatsapp_enabled;

    // Live chat enable/disable toggle
    $live_chat_enabled = isset($_POST['live_chat_enabled']) ? '1' : '0';
    set_setting($conn, 'live_chat_enabled', $live_chat_enabled);
    $settings['live_chat_enabled'] = $live_chat_enabled;

    // Live chat identity requirements
    $live_chat_require_name = isset($_POST['live_chat_require_name']) ? '1' : '0';
    $live_chat_require_email = isset($_POST['live_chat_require_email']) ? '1' : '0';
    set_setting($conn, 'live_chat_require_name', $live_chat_require_name);
    set_setting($conn, 'live_chat_require_email', $live_chat_require_email);
    $settings['live_chat_require_name'] = $live_chat_require_name;
    $settings['live_chat_require_email'] = $live_chat_require_email;

    // Social enable toggles
    $socialPlatforms = ['facebook','twitter','instagram','youtube','linkedin','tiktok','telegram','pinterest'];
    foreach ($socialPlatforms as $sp) {
        $key = 'social_' . $sp . '_enabled';
        $val = isset($_POST[$key]) ? '1' : '0';
        set_setting($conn, $key, $val);
        $settings[$key] = $val;
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
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="social_facebook_enabled" name="social_facebook_enabled" value="1" <?php echo (($settings['social_facebook_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="social_facebook_enabled"><i class="bi bi-facebook me-1"></i> Facebook</label>
                                    </div>
                                    <input type="text" class="form-control" name="social_facebook" id="social_facebook" value="<?php echo htmlspecialchars($settings['social_facebook'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://facebook.com/yourpage">
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="social_twitter_enabled" name="social_twitter_enabled" value="1" <?php echo (($settings['social_twitter_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="social_twitter_enabled"><i class="bi bi-twitter-x me-1"></i> Twitter</label>
                                    </div>
                                    <input type="text" class="form-control" name="social_twitter" id="social_twitter" value="<?php echo htmlspecialchars($settings['social_twitter'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://x.com/yourhandle">
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="social_instagram_enabled" name="social_instagram_enabled" value="1" <?php echo (($settings['social_instagram_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="social_instagram_enabled"><i class="bi bi-instagram me-1"></i> Instagram</label>
                                    </div>
                                    <input type="text" class="form-control" name="social_instagram" id="social_instagram" value="<?php echo htmlspecialchars($settings['social_instagram'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://instagram.com/yourhandle">
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="social_youtube_enabled" name="social_youtube_enabled" value="1" <?php echo (($settings['social_youtube_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="social_youtube_enabled"><i class="bi bi-youtube me-1"></i> YouTube</label>
                                    </div>
                                    <input type="text" class="form-control" name="social_youtube" id="social_youtube" value="<?php echo htmlspecialchars($settings['social_youtube'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://youtube.com/@yourchannel">
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="social_linkedin_enabled" name="social_linkedin_enabled" value="1" <?php echo (($settings['social_linkedin_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="social_linkedin_enabled"><i class="bi bi-linkedin me-1"></i> LinkedIn</label>
                                    </div>
                                    <input type="text" class="form-control" name="social_linkedin" id="social_linkedin" value="<?php echo htmlspecialchars($settings['social_linkedin'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://linkedin.com/company/yourcompany">
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="social_tiktok_enabled" name="social_tiktok_enabled" value="1" <?php echo (($settings['social_tiktok_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="social_tiktok_enabled"><i class="bi bi-tiktok me-1"></i> TikTok</label>
                                    </div>
                                    <input type="text" class="form-control" name="social_tiktok" id="social_tiktok" value="<?php echo htmlspecialchars($settings['social_tiktok'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://www.tiktok.com/@yourhandle">
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="social_telegram_enabled" name="social_telegram_enabled" value="1" <?php echo (($settings['social_telegram_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="social_telegram_enabled"><i class="bi bi-telegram me-1"></i> Telegram</label>
                                    </div>
                                    <input type="text" class="form-control" name="social_telegram" id="social_telegram" value="<?php echo htmlspecialchars($settings['social_telegram'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://t.me/yourchannel">
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="social_pinterest_enabled" name="social_pinterest_enabled" value="1" <?php echo (($settings['social_pinterest_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="social_pinterest_enabled"><i class="bi bi-pinterest me-1"></i> Pinterest</label>
                                    </div>
                                    <input type="text" class="form-control" name="social_pinterest" id="social_pinterest" value="<?php echo htmlspecialchars($settings['social_pinterest'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://pinterest.com/yourprofile">
                                </div>
                            </div>
                            <hr>
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="whatsapp_enabled" name="whatsapp_enabled" value="1" <?php echo (($settings['whatsapp_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="whatsapp_enabled">Enable WhatsApp</label>
                                    </div>
                                </div>
                                <div class="col-12 col-md-8">
                                    <label class="form-label fw-semibold" for="whatsapp_number">WhatsApp Number</label>
                                    <input type="text" class="form-control" name="whatsapp_number" id="whatsapp_number" value="<?php echo htmlspecialchars($settings['whatsapp_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., +923001234567">
                                    <div class="form-text">International format recommended. When enabled and a number is set, a WhatsApp icon appears in the site footer.</div>
                                </div>
                            </div>
                            <hr>
                            <div class="row g-3 align-items-center">
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="live_chat_enabled" name="live_chat_enabled" value="1" <?php echo (($settings['live_chat_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="live_chat_enabled">Enable Live Chat</label>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="text-muted small">Shows a chat widget on the storefront. Manage conversations in Admin → Live Chat.</div>
                                </div>
                            </div>
                            <div class="row g-3 align-items-center mt-1">
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="live_chat_require_name" name="live_chat_require_name" value="1" <?php echo (($settings['live_chat_require_name'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="live_chat_require_name">Require Name before chat</label>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="live_chat_require_email" name="live_chat_require_email" value="1" <?php echo (($settings['live_chat_require_email'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="live_chat_require_email">Require Email before chat</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-admin-primary"><i class="bi bi-save me-1"></i> Save Settings</button>
            </div>
        </form>
    </main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>
