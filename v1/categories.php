<?php
require_once 'config/corshandler.php';
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

ensure_default_categories();

$rows = query_select('SELECT id, title FROM categories ORDER BY title ASC');

http_response_code(200);
echo json_encode(['status' => 'success', 'data' => $rows]);
exit();

function ensure_default_categories() {
    $defaults = [
        'cat_fiction' => 'Fiction',
        'cat_non_fiction' => 'Non-Fiction',
        'cat_science' => 'Science',
        'cat_technology' => 'Technology',
        'cat_self_help' => 'Self-Help',
        'cat_history' => 'History',
        'cat_biography' => 'Biography',
        'cat_children' => 'Children',
        'cat_business' => 'Business',
        'cat_romance' => 'Romance'
    ];

    foreach ($defaults as $id => $title) {
        $existing = query_fetch_one('SELECT id FROM categories WHERE id = ?', [$id], 's');
        if (!$existing) {
            query_execute('INSERT INTO categories (id, title) VALUES (?, ?)', [$id, $title], 'ss');
        }
    }
}
?>
