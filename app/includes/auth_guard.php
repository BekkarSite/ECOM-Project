<?php
// Lightweight authentication guard for endpoints that don't include the full public header
// Requires session_start() to have been called by the including script.

if (!isset($_SESSION)) {
    session_start();
}

$authenticated = isset($_SESSION['user_id']);
if ($authenticated) {
    return; // Allow request to proceed
}

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjax) {
    header('Content-Type: application/json', true, 401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$next = urlencode($_SERVER['REQUEST_URI'] ?? '');
header('Location: login.php' . ($next ? ('?next=' . $next) : ''));
exit;
