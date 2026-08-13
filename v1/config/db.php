<?php
/**
 * Bookora Platform - Database Connection Configuration
 * Handles MySQL connection and provides helper functions
 */

// ============================================================
// DATABASE CONFIGURATION
// ============================================================

// Define database credentials
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'bookora_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_password_here');
define('DB_NAME', getenv('DB_NAME') ?: 'bookora_db');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// ============================================================
// CREATE DATABASE CONNECTION
// ============================================================

try {
    // Create connection using MySQLi
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    // Check connection
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode([
            'status' => 'error',
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]));
    }
    
    // Set charset to utf8mb4
    if (!$conn->set_charset("utf8mb4")) {
        http_response_code(500);
        die(json_encode([
            'status' => 'error',
            'message' => 'Error loading character set utf8mb4: ' . $conn->error
        ]));
    }
    
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection exception: ' . $e->getMessage()
    ]));
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Execute a SELECT query and return results as associative array
 * 
 * @param string $query SQL query with ? placeholders
 * @param array $params Query parameters
 * @param string $types Parameter types (e.g., 'sss', 'iii', 'sid')
 * @return array Results array or empty array if no results
 */
function query_select($query, $params = [], $types = '') {
    global $conn;
    
    try {
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        // Bind parameters if provided
        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
        
    } catch (Exception $e) {
        error_log('Database query error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Execute a SELECT query and return single row
 * 
 * @param string $query SQL query with ? placeholders
 * @param array $params Query parameters
 * @param string $types Parameter types
 * @return array|null Single row or null if not found
 */
function query_fetch_one($query, $params = [], $types = '') {
    global $conn;
    
    try {
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row;
        
    } catch (Exception $e) {
        error_log('Database query error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Execute INSERT, UPDATE, or DELETE query
 * 
 * @param string $query SQL query with ? placeholders
 * @param array $params Query parameters
 * @param string $types Parameter types
 * @return bool True if successful, false otherwise
 */
function query_execute($query, $params = [], $types = '') {
    global $conn;
    
    try {
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
        
    } catch (Exception $e) {
        error_log('Database query error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get the ID of the last inserted row
 * 
 * @return int Last insert ID
 */
function get_last_insert_id() {
    global $conn;
    return $conn->insert_id;
}

/**
 * Get number of affected rows from last query
 * 
 * @return int Number of affected rows
 */
function get_affected_rows() {
    global $conn;
    return $conn->affected_rows;
}

/**
 * Get the last error from the database
 * 
 * @return string Error message
 */
function get_db_error() {
    global $conn;
    return $conn->error;
}

/**
 * Start a database transaction
 * 
 * @return bool True if successful
 */
function start_transaction() {
    global $conn;
    return $conn->begin_transaction();
}

/**
 * Commit a transaction
 * 
 * @return bool True if successful
 */
function commit_transaction() {
    global $conn;
    return $conn->commit();
}

/**
 * Rollback a transaction
 * 
 * @return bool True if successful
 */
function rollback_transaction() {
    global $conn;
    return $conn->rollback();
}

/**
 * Escape string for safe SQL usage
 * 
 * @param string $str String to escape
 * @return string Escaped string
 */
function escape_string($str) {
    global $conn;
    return $conn->real_escape_string($str);
}

/**
 * Close database connection
 */
function close_db_connection() {
    global $conn;
    if ($conn) {
        $conn->close();
    }
}

// ============================================================
// CLOSE CONNECTION ON SCRIPT TERMINATION
// ============================================================

register_shutdown_function('close_db_connection');

?>
