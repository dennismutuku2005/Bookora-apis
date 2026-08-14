<?php
require_once 'config/corshandler.php';
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$rows = query_select(
    'SELECT id, firstName, lastName, username, email, phone, avatarUrl, memberSince, rating, booksPosted, booksShared, favoritesCount, bio, shareContactByEmail FROM users ORDER BY firstName ASC, lastName ASC'
);

foreach ($rows as &$row) {
    $row['shareContactByEmail'] = (bool)$row['shareContactByEmail'];
}
unset($row);

http_response_code(200);
echo json_encode(['status' => 'success', 'data' => $rows]);
exit();
?>
