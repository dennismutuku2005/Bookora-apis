<?php
/**
 * Bookora Platform - Image Upload API
 * POST /v1/upload.php
 * Handles book cover images and profile avatars
 */

require_once 'config/corshandler.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$type = isset($_GET['type']) ? trim($_GET['type']) : 'book';
$userId = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';

if ($userId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'user_id is required']);
    exit();
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['file'];
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimeTypes)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPEG, PNG, WebP, and GIF allowed']);
    exit();
}

// Max file size: 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'File too large. Max size: 5MB']);
    exit();
}

// Create uploads directory if not exists
$uploadsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Create subdirectory by type and user
$typeDir = $uploadsDir . '/' . $type;
if (!is_dir($typeDir)) {
    mkdir($typeDir, 0755, true);
}

$userDir = $typeDir . '/' . $userId;
if (!is_dir($userDir)) {
    mkdir($userDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $type . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$filepath = $userDir . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save file']);
    exit();
}

// Return URL path (adjust based on your deployment)
$url = '/Bookora-apis/uploads/' . $type . '/' . $userId . '/' . $filename;

http_response_code(201);
echo json_encode([
    'status' => 'success',
    'message' => 'File uploaded successfully',
    'data' => [
        'url' => $url,
        'filename' => $filename,
        'size' => $file['size'],
        'mimeType' => $mimeType
    ]
]);
