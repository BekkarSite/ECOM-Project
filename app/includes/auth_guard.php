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

$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$projRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$baseUri = '';
if ($docRoot && $projRoot && strpos($projRoot, $docRoot) === 0) {
    $baseUri = rtrim(substr($projRoot, strlen($docRoot)), '/');
}
$BASE_PATH = $baseUri ? '/' . ltrim($baseUri, '/') : '';
$next = urlencode($_SERVER['REQUEST_URI'] ?? '');
header('Location: ' . $BASE_PATH . '/login.php' . ($next ? ('?next=' . $next) : ''));
exit;
