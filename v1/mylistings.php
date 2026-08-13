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
if (!$user_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'user_id is required']);
    exit();
}

$rows = query_select(
    "SELECT id, title, author, category, condition, location, postedTimestamp, coverUrl, listingType, description, ownerId, rating, coverColor, created_at, updated_at FROM books WHERE ownerId = ? ORDER BY created_at DESC",
    [$user_id], 's'
);

http_response_code(200);
echo json_encode(['status' => 'success', 'data' => ['total' => count($rows), 'items' => $rows]]);
exit();

?>
