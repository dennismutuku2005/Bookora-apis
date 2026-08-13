<?php
/**
 * Bookora Platform - Favorites API
 * GET /v1/favorites.php?action=list&user_id=ID&page=1&per_page=20
 * Returns paginated favorites joined with basic book info
 */

require_once 'config/corshandler.php';
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method !== 'GET' || $action !== 'list') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed or invalid action']);
    exit();
}

$user_id = isset($_GET['user_id']) ? trim($_GET['user_id']) : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
$per_page = max(1, min($per_page, 100));

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'user_id is required']);
    exit();
}

// total count
$countRow = query_fetch_one("SELECT COUNT(*) AS total FROM favorites WHERE userId = ?", [$user_id], 's');
$total = isset($countRow['total']) ? (int)$countRow['total'] : 0;

$total_pages = ($total === 0) ? 0 : (int)ceil($total / $per_page);
$offset = ($page - 1) * $per_page;

// fetch paginated favorites with basic book info
$query = "SELECT f.id AS favoriteId, f.bookId, f.timestamp AS favorited_at, b.title, b.author, b.coverUrl, b.listingType, b.ownerId
          FROM favorites f
          LEFT JOIN books b ON f.bookId = b.id
          WHERE f.userId = ?
          ORDER BY f.created_at DESC
          LIMIT ? OFFSET ?";

$rows = query_select($query, [$user_id, $per_page, $offset], 'sii');

http_response_code(200);
echo json_encode([
    'status' => 'success',
    'data' => [
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total_pages,
        'items' => $rows
    ]
]);
exit();

?>
