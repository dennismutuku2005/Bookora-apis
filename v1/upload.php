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

// Support type and user_id from GET, POST, or REQUEST
$type = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : (isset($_GET['type']) ? trim($_GET['type']) : 'book');
$userId = isset($_REQUEST['user_id']) ? trim($_REQUEST['user_id']) : (isset($_POST['user_id']) ? trim($_POST['user_id']) : (isset($_GET['user_id']) ? trim($_GET['user_id']) : ''));

if ($userId === '') {
    // Generate fallback guest ID if not provided
    $userId = 'user_guest';
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = isset($_FILES['file']) ? $_FILES['file']['error'] : 'no_file';
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "No file uploaded or upload error (code: {$errCode})"]);
    exit();
}

$file = $_FILES['file'];
$allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'application/octet-stream'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($extension === '') {
    $extension = 'jpg';
}

$mimeType = 'image/jpeg';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($detectedMime) {
        $mimeType = $detectedMime;
    }
}

if (!in_array($mimeType, $allowedMimeTypes) && !in_array($extension, $allowedExtensions)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPEG, PNG, WebP, and GIF allowed']);
    exit();
}

// Max file size: 10MB
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'File too large. Max size: 10MB']);
    exit();
}

// Create uploads directory structure
$uploadsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0777, true);
}

$typeDir = $uploadsDir . '/' . $type;
if (!is_dir($typeDir)) {
    @mkdir($typeDir, 0777, true);
}

$userDir = $typeDir . '/' . $userId;
if (!is_dir($userDir)) {
    @mkdir($userDir, 0777, true);
}

// Generate unique filename
$filename = $type . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$filepath = $userDir . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded file to disk']);
    exit();
}

// Construct absolute public URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME']); // e.g. /bookora/Bookora-apis/v1
$baseUploadPath = preg_replace('#/v1/?$#i', '/uploads', $scriptDir); // e.g. /bookora/Bookora-apis/uploads
$relativeUrl = $baseUploadPath . '/' . $type . '/' . $userId . '/' . $filename;
$fullUrl = $protocol . '://' . $host . $relativeUrl;

http_response_code(201);
echo json_encode([
    'status' => 'success',
    'message' => 'File uploaded successfully',
    'data' => [
        'url' => $fullUrl,
        'filename' => $filename,
        'size' => $file['size'],
        'mimeType' => $mimeType
    ]
]);
?>
