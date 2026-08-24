<?php
require_once 'config/corshandler.php';
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && in_array($action, ['list', 'search', ''], true)) {
    handle_list();
} elseif ($method === 'GET' && $action === 'get') {
    handle_get();
} elseif ($method === 'POST') {
    handle_create();
} elseif ($method === 'PUT' || $method === 'PATCH') {
    handle_update();
} elseif ($method === 'DELETE') {
    handle_delete();
} else {
    send_error('Method not allowed or invalid action', 405);
}

function handle_list() {
    $q = trim($_GET['q'] ?? '');
    $userId = trim($_GET['user_id'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $params = [];
    $types = '';
    $where = [];

    if ($userId !== '') {
        $where[] = 'ownerId = ?';
        $params[] = $userId;
        $types .= 's';
    }

    if ($q !== '') {
        $where[] = '(title LIKE ? OR author LIKE ? OR description LIKE ?)';
        $like = "%{$q}%";
        $params[] = $like; $params[] = $like; $params[] = $like;
        $types .= 'sss';
    }

    $sql = 'SELECT id, title, author, category, `condition`, location, postedTimestamp, coverUrl, `listingType`, description, ownerId, rating, coverColor, created_at, updated_at FROM books';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY postedTimestamp DESC LIMIT ? OFFSET ?';

    $params[] = $perPage;
    $params[] = $offset;
    $types .= 'ii';

    $rows = query_select($sql, $params, $types);
    foreach ($rows as &$row) {
        $row['isFavorite'] = false;
        $row['postedDate'] = !empty($row['created_at']) ? $row['created_at'] : '';
        $row['distance'] = '';
        $row['ownerUsername'] = get_owner_username($row['ownerId']);
    }
    unset($row);

    send_success('Books fetched', $rows, 200);
}

function handle_get() {
    $bookId = trim($_GET['id'] ?? '');
    if ($bookId === '') {
        send_error('book id is required', 400);
    }

    $row = query_fetch_one(
        'SELECT id, title, author, category, `condition`, location, postedTimestamp, coverUrl, `listingType`, description, ownerId, rating, coverColor, created_at, updated_at FROM books WHERE id = ?',
        [$bookId],
        's'
    );

    if (!$row) {
        send_error('Book not found', 404);
    }

    $row['isFavorite'] = false;
    $row['postedDate'] = !empty($row['created_at']) ? $row['created_at'] : '';
    $row['distance'] = '';
    $row['ownerUsername'] = get_owner_username($row['ownerId']);

    send_success('Book fetched', $row, 200);
}

function handle_create() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !is_array($data)) {
        send_error('Request body is required', 400);
    }

    $userId = trim((string)($data['user_id'] ?? $data['ownerId'] ?? ''));
    $title = trim((string)($data['title'] ?? ''));
    $author = trim((string)($data['author'] ?? ''));
    $category = trim((string)($data['category'] ?? ''));
    $condition = trim((string)($data['condition'] ?? 'GOOD'));
    $location = trim((string)($data['location'] ?? ''));
    $coverUrl = trim((string)($data['coverUrl'] ?? ''));
    $listingType = trim((string)($data['listingType'] ?? $data['listing_type'] ?? 'GIVEAWAY'));
    $description = trim((string)($data['description'] ?? ''));
    $coverColor = trim((string)($data['coverColor'] ?? '#F0F4FF'));

    if ($userId === '' || $title === '' || $author === '') {
        send_error('user_id, title and author are required', 400);
    }

    $bookId = 'book_' . bin2hex(random_bytes(12));
    $postedTimestamp = isset($data['postedTimestamp']) ? (int)$data['postedTimestamp'] : (int)round(microtime(true) * 1000);

    $ok = query_execute(
        'INSERT INTO books (id, title, author, category, `condition`, location, postedTimestamp, coverUrl, `listingType`, description, ownerId, rating, coverColor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$bookId, $title, $author, $category, $condition, $location, $postedTimestamp, $coverUrl, $listingType, $description, $userId, (float)($data['rating'] ?? 0.0), $coverColor],
        'ssssssissssds'
    );

    if (!$ok) {
        send_error('Failed to create book: ' . get_db_error(), 500);
    }

    $row = query_fetch_one('SELECT id, title, author, category, condition, location, postedTimestamp, coverUrl, listingType, description, ownerId, rating, coverColor, created_at FROM books WHERE id = ?', [$bookId], 's');
    $row['isFavorite'] = false;
    $row['postedDate'] = !empty($row['created_at']) ? $row['created_at'] : '';
    $row['distance'] = '';
    $row['ownerUsername'] = get_owner_username($row['ownerId']);

    send_success('Book created', $row, 201);
}

function handle_update() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !is_array($data)) {
        send_error('Request body is required', 400);
    }

    $bookId = trim((string)($data['id'] ?? $_GET['id'] ?? ''));
    $userId = trim((string)($data['user_id'] ?? $data['ownerId'] ?? ''));
    if ($bookId === '') {
        send_error('book id is required', 400);
    }

    $fields = [];
    $params = [];
    $types = '';
    $allowed = [
        'title' => 's',
        'author' => 's',
        'category' => 's',
        'condition' => 's',
        'location' => 's',
        'coverUrl' => 's',
        'listingType' => 's',
        'description' => 's',
        'coverColor' => 's'
    ];

    foreach ($allowed as $field => $type) {
        if (array_key_exists($field, $data)) {
            // Use backticks for reserved keywords
            $fieldName = in_array($field, ['condition', 'listingType', 'status']) ? '`' . $field . '`' : $field;
            $fields[] = $fieldName . ' = ?';
            $params[] = trim((string)$data[$field]);
            $types .= $type;
        }
    }

    if (count($fields) === 0) {
        send_error('No update fields provided', 400);
    }

    $sql = 'UPDATE books SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $params[] = $bookId;
    $types .= 's';

    if ($userId !== '') {
        $sql .= ' AND ownerId = ?';
        $params[] = $userId;
        $types .= 's';
    }

    $ok = query_execute($sql, $params, $types);
    if (!$ok) {
        send_error('Failed to update book', 500);
    }

    $row = query_fetch_one('SELECT id, title, author, category, `condition`, location, postedTimestamp, coverUrl, `listingType`, description, ownerId, rating, coverColor, created_at FROM books WHERE id = ?', [$bookId], 's');
    if ($row) {
        $row['isFavorite'] = false;
        $row['postedDate'] = !empty($row['created_at']) ? $row['created_at'] : '';
        $row['distance'] = '';
        $row['ownerUsername'] = get_owner_username($row['ownerId']);
    }
    send_success('Book updated', $row ?? null, 200);
}

function handle_delete() {
    $bookId = trim((string)($_GET['id'] ?? ''));
    $userId = trim((string)($_GET['user_id'] ?? ''));
    if ($bookId === '') {
        send_error('book id is required', 400);
    }

    $sql = 'DELETE FROM books WHERE id = ?';
    $params = [$bookId];
    $types = 's';
    if ($userId !== '') {
        $sql .= ' AND ownerId = ?';
        $params[] = $userId;
        $types .= 's';
    }

    $ok = query_execute($sql, $params, $types);
    if (!$ok) {
        send_error('Failed to delete book', 500);
    }

    send_success('Book deleted', null, 200);
}

function get_owner_username($ownerId) {
    if (empty($ownerId)) {
        return '';
    }
    $row = query_fetch_one('SELECT username FROM users WHERE id = ?', [$ownerId], 's');
    return $row['username'] ?? '';
}

function send_success($message, $data = null, $code = 200) {
    http_response_code($code);
    $response = ['status' => 'success', 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}
?>
