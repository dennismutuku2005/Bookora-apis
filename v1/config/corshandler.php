<?php
/**
 * Bookora Platform - CORS Handler & Global JSON Helper
 * Centralized CORS configuration and clean JSON output handlers
 */

// Disable HTML error display in API outputs to prevent malformed JSON
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Clear output buffer if present
if (ob_get_length()) {
    ob_clean();
}

// CORS HEADERS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

// HANDLE PREFLIGHT REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Standardized JSON response helpers if not already defined
if (!function_exists('send_error')) {
    function send_error($message, $code = 400, $data = null) {
        if (ob_get_length()) ob_clean();
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'data' => $data
        ]);
        exit();
    }
}

if (!function_exists('send_success')) {
    function send_success($message = 'Success', $data = null, $code = 200) {
        if (ob_get_length()) ob_clean();
        http_response_code($code);
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
        exit();
    }
}
