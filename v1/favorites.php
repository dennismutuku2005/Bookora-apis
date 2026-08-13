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

// Support GET list and POST add
if ($method === 'GET' && $action === 'list') {
    // continue to list handling below
} elseif ($method === 'POST' && $action === 'add') {
    // handle add favorite
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['user_id']) || !isset($data['book_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'user_id and book_id are required']);
        exit();
    }

    $user_id = trim($data['user_id']);
    $book_id = trim($data['book_id']);
    $fav_id = bin2hex(random_bytes(16));
    $ts = (int)round(microtime(true) * 1000);

    $ok = query_execute("INSERT INTO favorites (id, userId, bookId, timestamp) VALUES (?, ?, ?, ?)", [$fav_id, $user_id, $book_id, $ts], 'sssi');
    if ($ok) {
        http_response_code(201);
        echo json_encode(['status' => 'success', 'message' => 'Favorite added']);
        exit();
    } else {
        $err = get_db_error();
        // Duplicate entry for unique_favorite (userId, bookId)
        if (stripos($err, 'Duplicate') !== false || stripos($err, 'duplicate') !== false) {
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Already favorited']);
            exit();
        }
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to add favorite']);
        exit();
    }

} elseif ($method === 'POST' && $action === 'remove') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['user_id']) || !isset($data['book_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'user_id and book_id are required']);
        exit();
    }

    $user_id = trim($data['user_id']);
    $book_id = trim($data['book_id']);

    $ok = query_execute("DELETE FROM favorites WHERE userId = ? AND bookId = ?", [$user_id, $book_id], 'ss');
    if ($ok) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Favorite removed']);
        exit();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove favorite']);
        exit();
    }

} else {
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
