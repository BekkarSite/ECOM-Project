<?php
// public/search_suggest.php — returns JSON suggestions for product search
session_start();
require_once __DIR__ . '/../config/db.php';

// Compute BASE_PATH similar to header for building absolute-ish URLs
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$projRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$baseUri = '';
if ($docRoot && $projRoot && strpos($projRoot, $docRoot) === 0) {
    $baseUri = rtrim(substr($projRoot, strlen($docRoot)), '/');
}
$BASE_PATH = $baseUri ? '/' . ltrim($baseUri, '/') : '';

header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if ($q === '' && isset($_GET['query'])) {
    $q = trim((string)$_GET['query']);
}

// Normalize and clamp limit
$limit = 8;
if (isset($_GET['limit'])) {
    $limit = (int) $_GET['limit'];
}
$limit = max(1, min($limit, 15));

// Short-circuit on very short terms
if ($q === '' || mb_strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$like = '%' . $q . '%';
$sql = "SELECT id, name, price, image FROM products WHERE name LIKE ? OR description LIKE ? ORDER BY name ASC LIMIT $limit";

$results = [];
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('ss', $like, $like);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['id'];
            $imgPath = null;
            if (!empty($row['image'])) {
                $imgPath = $BASE_PATH . '/assets/images/' . $row['image'];
            } else {
                $imgPath = $BASE_PATH . '/assets/images/placeholder.svg';
            }
            $results[] = [
                'id' => $id,
                'name' => (string)$row['name'],
                'price' => (float)$row['price'],
                'url' => $BASE_PATH . '/product.php?id=' . $id,
                'image' => $imgPath,
            ];
        }
    }
    $stmt->close();
}

echo json_encode($results);
exit;
?>

