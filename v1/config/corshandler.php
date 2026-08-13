<?php
/**
 * Bookora Platform - CORS Handler
 * Centralized CORS configuration for all API endpoints
 */

// ============================================================
// CORS HEADERS
// ============================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');

// ============================================================
// HANDLE PREFLIGHT REQUESTS
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

?>
