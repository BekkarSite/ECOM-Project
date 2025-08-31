<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../../config/db.php';

$id = intval($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($id > 0 && in_array($action, ['ban', 'unban'])) {
    $is_banned = $action === 'ban' ? 1 : 0;
    $stmt = $conn->prepare('UPDATE users SET is_banned = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $is_banned, $id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: manage_users.php');
exit();