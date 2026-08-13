<?php
/**
 * Bookora Platform - Profile API
 * Supports: read profile, update profile fields, toggle shareContactByEmail, delete account, create minimal user
 * Endpoints:
 *  - GET  /v1/profile.php?action=get&user_id=ID
 *  - POST /v1/profile.php?action=update   { user_id, firstName, lastName, username, phone, bio }
 *  - POST /v1/profile.php?action=toggle_share { user_id, share (true|false) }
 *  - POST /v1/profile.php?action=create { id, email, username, firstName?, lastName?, phone?, bio? }
 *  - DELETE /v1/profile.php?action=delete&user_id=ID
 */

require_once 'config/corshandler.php';
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method === 'GET' && $action === 'get') {
    handle_get();
} elseif ($method === 'POST' || $method === 'PATCH') {
    if ($action === 'update') handle_update();
    elseif ($action === 'toggle_share') handle_toggle_share();
    elseif ($action === 'create') handle_create();
    else send_error('Invalid action for POST/PATCH', 400);
} elseif ($method === 'DELETE' && $action === 'delete') {
    handle_delete();
} else {
    send_error('Method not allowed or invalid action', 405);
}

// ============================================================
function handle_get() {
    $user_id = isset($_GET['user_id']) ? trim($_GET['user_id']) : null;
    if (!$user_id) { send_error('user_id is required', 400); }

    $user = query_fetch_one(
        "SELECT id, firstName, lastName, username, phone, avatarUrl, bio, booksPosted, booksShared, favoritesCount, shareContactByEmail FROM users WHERE id = ?",
        [$user_id], 's'
    );

    if (!$user) { send_error('User not found', 404); }

    // Normalize booleans
    $user['shareContactByEmail'] = (bool)$user['shareContactByEmail'];

    send_success('Profile fetched', $user, 200);
}

function handle_update() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isMultipart = strpos($contentType, 'multipart/form-data') !== false;

    if ($isMultipart) {
        // Use $_POST and $_FILES for multipart updates
        $data = $_POST;
    } else {
        $data = json_decode(file_get_contents('php://input'), true);
    }

    if (!$data || !isset($data['user_id'])) send_error('user_id is required', 400);
    $id = trim($data['user_id']);

    $allowed = ['firstName','lastName','username','phone','bio'];
    $fields = [];
    $params = [];
    $types = '';

    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            if ($f === 'username' && strlen(trim((string)$data[$f])) === 0) {
                send_error('username cannot be empty', 400);
            }
            $fields[] = "$f = ?";
            $params[] = $data[$f];
            $types .= 's';
        }
    }

    if (array_key_exists('shareContactByEmail', $data)) {
        $fields[] = "shareContactByEmail = ?";
        $params[] = ($data['shareContactByEmail']) ? 1 : 0;
        $types .= 'i';
    }

    // Handle avatar upload when multipart/form-data and file provided
    if ($isMultipart && isset($_FILES['avatar']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
        $up = $_FILES['avatar'];
        $origName = $up['name'];
        $tmp = $up['tmp_name'];
        $err = $up['error'];

        if ($err !== UPLOAD_ERR_OK) {
            send_error('File upload error', 400);
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExt = ['jpg','jpeg','png'];
        if (!in_array($ext, $allowedExt)) send_error('Invalid image type', 400);

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadDir = dirname(__DIR__) . '/uploads';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $dest = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $dest)) send_error('Failed to save uploaded file', 500);

        // Save web-accessible path
        $avatarUrl = '/uploads/' . $filename;
        $fields[] = "avatarUrl = ?";
        $params[] = $avatarUrl;
        $types .= 's';
    }

    if (count($fields) === 0) send_error('No updatable fields provided', 400);

    $query = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
    $params[] = $id;
    $types .= 's';

    $ok = query_execute($query, $params, $types);
    if (!$ok) send_error('Failed to update profile', 500);

    $updated = query_fetch_one("SELECT id, firstName, lastName, username, phone, avatarUrl, bio, booksPosted, booksShared, favoritesCount, shareContactByEmail FROM users WHERE id = ?", [$id], 's');
    $updated['shareContactByEmail'] = (bool)$updated['shareContactByEmail'];
    send_success('Profile updated', $updated, 200);
}

function handle_toggle_share() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['user_id']) || !isset($data['share'])) send_error('user_id and share are required', 400);
    $id = trim($data['user_id']);
    $share = $data['share'] ? 1 : 0;

    $ok = query_execute("UPDATE users SET shareContactByEmail = ? WHERE id = ?", [$share, $id], 'is');
    if (!$ok) send_error('Failed to toggle shareContactByEmail', 500);

    send_success('shareContactByEmail updated', ['shareContactByEmail' => (bool)$share], 200);
}

function handle_create() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id']) || !isset($data['email']) || !isset($data['username'])) send_error('id, email and username are required', 400);

    $id = trim($data['id']);
    $email = trim($data['email']);
    $username = trim($data['username']);
    $firstName = isset($data['firstName']) ? $data['firstName'] : '';
    $lastName = isset($data['lastName']) ? $data['lastName'] : '';
    $phone = isset($data['phone']) ? $data['phone'] : null;
    $bio = isset($data['bio']) ? $data['bio'] : null;

    $ok = query_execute(
        "INSERT INTO users (id, firstName, lastName, username, email, phone, bio) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$id, $firstName, $lastName, $username, $email, $phone, $bio],
        'sssssss'
    );

    if (!$ok) send_error('Failed to create user', 500);
    $user = query_fetch_one("SELECT id, firstName, lastName, username, phone, avatarUrl, bio, booksPosted, booksShared, favoritesCount, shareContactByEmail FROM users WHERE id = ?", [$id], 's');
    $user['shareContactByEmail'] = (bool)$user['shareContactByEmail'];
    send_success('User created', $user, 201);
}

function handle_delete() {
    $user_id = isset($_GET['user_id']) ? trim($_GET['user_id']) : null;
    if (!$user_id) send_error('user_id is required', 400);

    $ok = query_execute("DELETE FROM users WHERE id = ?", [$user_id], 's');
    if (!$ok) send_error('Failed to delete user', 500);

    send_success('User deleted', null, 200);
}

function send_success($message, $data = null, $code = 200) {
    http_response_code($code);
    $response = ['status' => 'success', 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response);
    exit();
}

function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

?>
