<?php
// Admin General Settings: logo upload, site title/description, company & social
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/helpers/settings.php';

$message = '';
$errors = [];

// Ensure default keys exist
$settingKeys = [
    'site_title', 'site_tagline', 'site_description', 'company_name', 'company_email', 'company_phone', 'company_address',
    'social_facebook', 'social_twitter', 'social_instagram', 'site_logo'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Text fields
    $fields = [
        'site_title', 'site_tagline', 'site_description', 'company_name', 'company_email', 'company_phone', 'company_address',
        'social_facebook', 'social_twitter', 'social_instagram'
    ];
    foreach ($fields as $f) {
        $val = trim($_POST[$f] ?? '');
        set_setting($conn, $f, $val);
    }

    // Logo upload
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['site_logo']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'gif', 'svg'];
            if (in_array($ext, $allowed, true) && is_uploaded_file($tmp)) {
                $filename = 'site_logo_' . uniqid() . '.' . $ext;
                $targetDir = __DIR__ . '/../assets/images/';
                $target = $targetDir . $filename;
                if (move_uploaded_file($tmp, $target)) {
                    // Store relative path from project root used by headers: ../assets/images/
                    set_setting($conn, 'site_logo', 'assets/images/' . $filename);
                } else {
                    $errors[] = 'Failed to save uploaded logo.';
                }
            } else {
                $errors[] = 'Invalid logo format.';
            }
        } else {
            $errors[] = 'Upload error code: ' . (int)$_FILES['site_logo']['error'];
        }
    }

    if (empty($errors)) {
        $message = 'Settings saved successfully.';
    } else {
        $message = implode(' ', $errors);
    }
}

// Load current values
$settings = get_settings($conn, $settingKeys);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>General Settings</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        .settings-form label { font-weight: 600; margin-top: 12px; }
        .settings-form input[type=text], .settings-form input[type=email], .settings-form textarea { width: 100%; max-width: 640px; }
        .logo-preview { max-height: 64px; display: block; margin-top: 8px; }
        .content { padding-top: 20px; }
    </style>
    <script>
    window.addEventListener('load', function() {
        var loader = document.getElementById('loader');
        if (loader) loader.classList.add('hidden');
    });
    </script>
    </head>
<body>
    <?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
    <div class="admin-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <main class="content">
            <h1>General Settings</h1>
            <?php if ($message): ?>
                <p class="message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <form class="settings-form" method="POST" enctype="multipart/form-data">
                <h2>Branding</h2>
                <label for="site_logo">Site Logo</label>
                <input type="file" name="site_logo" id="site_logo" accept="image/*">
                <?php if (!empty($settings['site_logo'])): ?>
                    <img class="logo-preview" src="../<?php echo htmlspecialchars($settings['site_logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Current Logo">
                <?php endif; ?>

                <label for="site_title">Site Title</label>
                <input type="text" name="site_title" id="site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <label for="site_tagline">Tagline</label>
                <input type="text" name="site_tagline" id="site_tagline" value="<?php echo htmlspecialchars($settings['site_tagline'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <label for="site_description">Description</label>
                <textarea name="site_description" id="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

                <h2>Company</h2>
                <label for="company_name">Company Name</label>
                <input type="text" name="company_name" id="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <label for="company_email">Company Email</label>
                <input type="email" name="company_email" id="company_email" value="<?php echo htmlspecialchars($settings['company_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <label for="company_phone">Company Phone</label>
                <input type="text" name="company_phone" id="company_phone" value="<?php echo htmlspecialchars($settings['company_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <label for="company_address">Company Address</label>
                <textarea name="company_address" id="company_address" rows="2"><?php echo htmlspecialchars($settings['company_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

                <h2>Social</h2>
                <label for="social_facebook">Facebook URL</label>
                <input type="text" name="social_facebook" id="social_facebook" value="<?php echo htmlspecialchars($settings['social_facebook'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <label for="social_twitter">Twitter URL</label>
                <input type="text" name="social_twitter" id="social_twitter" value="<?php echo htmlspecialchars($settings['social_twitter'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <label for="social_instagram">Instagram URL</label>
                <input type="text" name="social_instagram" id="social_instagram" value="<?php echo htmlspecialchars($settings['social_instagram'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </main>
    </div>
    <?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>

