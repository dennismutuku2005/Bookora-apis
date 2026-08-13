<?php
/**
 * Bookora Platform - Logout API
 * Marks a user as inactive and updates last_login timestamp
 * POST { user_id: "..." }
 */

require_once 'config/corshandler.php';
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'user_id is required']);
    exit();
}

$user_id = trim($data['user_id']);

$ok = query_execute("UPDATE users SET is_active = 0, last_login = NOW() WHERE id = ?", [$user_id], 's');
if (!$ok) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to logout user']);
    exit();
}

echo json_encode(['status' => 'success', 'message' => 'User logged out']);
exit();

?>
