<?php
require_once 'config/corshandler.php';
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'stats';

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId = trim($_GET['user_id'] ?? '');

if ($action === 'stats' && $userId !== '') {
    $booksPosted = (int)query_fetch_one('SELECT COUNT(*) AS total FROM books WHERE ownerId = ?', [$userId], 's')['total'];
    $favoritesCount = (int)query_fetch_one('SELECT COUNT(*) AS total FROM favorites WHERE userId = ?', [$userId], 's')['total'];
    $booksShared = (int)query_fetch_one('SELECT COUNT(*) AS total FROM claim_requests WHERE ownerId = ? AND status IN ("CONFIRMED_CLAIMER", "CONFIRMED_OWNER", "COMPLETED")', [$userId], 's')['total'];
    $unreadNotifications = (int)query_fetch_one('SELECT COUNT(*) AS total FROM notifications WHERE userId = ? AND is_read = 0', [$userId], 's')['total'];

    echo json_encode([
        'status' => 'success',
        'data' => [
            'booksPosted' => $booksPosted,
            'favoritesCount' => $favoritesCount,
            'booksShared' => $booksShared,
            'unreadNotifications' => $unreadNotifications
        ]
    ]);
    exit();
}

$booksPosted = (int)query_fetch_one('SELECT COUNT(*) AS total FROM books', [], '')['total'];
$favoritesCount = (int)query_fetch_one('SELECT COUNT(*) AS total FROM favorites', [], '')['total'];
$booksShared = (int)query_fetch_one('SELECT COUNT(*) AS total FROM claim_requests WHERE status IN ("CONFIRMED_CLAIMER", "CONFIRMED_OWNER", "COMPLETED")', [], '')['total'];
$unreadNotifications = (int)query_fetch_one('SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0', [], '')['total'];

echo json_encode([
    'status' => 'success',
    'data' => [
        'booksPosted' => $booksPosted,
        'favoritesCount' => $favoritesCount,
        'booksShared' => $booksShared,
        'unreadNotifications' => $unreadNotifications
    ]
]);
exit();
?>
