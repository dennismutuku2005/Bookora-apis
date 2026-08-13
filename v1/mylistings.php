<?php
/**
 * Bookora Platform - My Listings API
 * Returns all books owned by a given user_id
 * GET /v1/mylistings.php?user_id=USER_ID
 */

require_once 'config/corshandler.php';
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
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
$countRow = query_fetch_one("SELECT COUNT(*) AS total FROM books WHERE ownerId = ?", [$user_id], 's');
$total = isset($countRow['total']) ? (int)$countRow['total'] : 0;

$total_pages = ($total === 0) ? 0 : (int)ceil($total / $per_page);
$offset = ($page - 1) * $per_page;

$rows = query_select(
    "SELECT id, title, author, category, condition, location, postedTimestamp, coverUrl, listingType, description, ownerId, rating, coverColor, created_at, updated_at FROM books WHERE ownerId = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$user_id, $per_page, $offset], 'sii'
);

http_response_code(200);
echo json_encode(['status' => 'success', 'data' => ['total' => $total, 'page' => $page, 'per_page' => $per_page, 'total_pages' => $total_pages, 'items' => $rows]]);
exit();

?>
