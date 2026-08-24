<?php
/**
 * Bookora Platform - Authentication API
 * Handles user login and registration
 */

require_once 'config/corshandler.php';
require_once 'config/db.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Route to appropriate handler
if ($method === 'POST') {
    if ($action === 'login') {
        handle_login();
    } elseif ($action === 'register') {
        handle_register();
    } elseif ($action === 'google_login') {
        handle_google_login();
    } else {
        send_error('Invalid action', 400);
    }
} else {
    send_error('Method not allowed', 405);
}

// ============================================================
// LOGIN HANDLER
// ============================================================

function handle_login() {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate input
    if (!isset($data['email']) || !isset($data['password'])) {
        send_error('Email and password are required', 400);
        return;
    }
    
    $email = trim($data['email']);
    $password = trim($data['password']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_error('Invalid email format', 400);
        return;
    }
    
    // Find user by email
    $user = query_fetch_one(
        "SELECT id, firstName, lastName, username, email, phone, avatarUrl, bio, rating, booksPosted, booksShared, favoritesCount, password_hash FROM users WHERE email = ?",
        [$email],
        's'
    );
    
    if (!$user) {
        send_error('Email or password is incorrect', 401);
        return;
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        send_error('Email or password is incorrect', 401);
        return;
    }
    
    // Remove password hash from response
    unset($user['password_hash']);
    
    // Update last login
    query_execute(
        "UPDATE users SET last_login = NOW() WHERE id = ?",
        [$user['id']],
        's'
    );
    
    send_success('Login successful', $user, 200);
}

// ============================================================
// REGISTER HANDLER
// ============================================================

function handle_register() {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate required fields
    $required = ['firstName', 'lastName', 'username', 'email', 'phoneNumber', 'password'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            send_error("Field '{$field}' is required", 400);
            return;
        }
    }
    
    // Generate unique ID
    $id = generate_user_id();
    
    $firstName = trim($data['firstName']);
    $lastName = trim($data['lastName']);
    $username = trim($data['username']);
    $email = trim($data['email']);
    $phoneNumber = trim($data['phoneNumber']);
    $password = trim($data['password']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_error('Invalid email format', 400);
        return;
    }
    
    // Validate password strength (at least 6 characters)
    if (strlen($password) < 6) {
        send_error('Password must be at least 6 characters', 400);
        return;
    }
    
    // Validate username (alphanumeric and underscore only, 3-20 chars)
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        send_error('Username must be 3-20 characters (alphanumeric and underscore only)', 400);
        return;
    }
    
    // Check if email already exists
    $existing_email = query_fetch_one(
        "SELECT id FROM users WHERE email = ?",
        [$email],
        's'
    );
    
    if ($existing_email) {
        send_error('Email already registered', 409);
        return;
    }
    
    // Check if username already exists
    $existing_username = query_fetch_one(
        "SELECT id FROM users WHERE username = ?",
        [$username],
        's'
    );
    
    if ($existing_username) {
        send_error('Username already taken', 409);
        return;
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Insert new user
    $query = "INSERT INTO users (id, firstName, lastName, username, email, phone, password_hash, memberSince, is_active) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), TRUE)";
    
    if (!query_execute($query, [$id, $firstName, $lastName, $username, $email, $phoneNumber, $password_hash], 'sssssss')) {
        send_error('Registration failed: ' . get_db_error(), 500);
        return;
    }
    
    // Fetch and return created user (without password)
    $new_user = query_fetch_one(
        "SELECT id, firstName, lastName, username, email, phone, rating, booksPosted, booksShared, favoritesCount, memberSince FROM users WHERE id = ?",
        [$id],
        's'
    );
    
    send_success('Registration successful', $new_user, 201);
}

// ============================================================
// GOOGLE SIGN-IN HANDLER
// ============================================================

function handle_google_login() {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['email'])) {
        send_error('Email is required', 400);
        return;
    }
    
    $email = trim($data['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_error('Invalid email format', 400);
        return;
    }
    
    // Check if user already exists
    $user = query_fetch_one(
        "SELECT id, firstName, lastName, username, email, phone, rating, booksPosted, booksShared, favoritesCount, memberSince FROM users WHERE email = ?",
        [$email],
        's'
    );
    
    if ($user) {
        send_success('Login successful', $user, 200);
        return;
    }
    
    // User does not exist, so register them automatically
    $id = generate_user_id();
    
    $firstName = trim($data['firstName'] ?? '');
    $lastName = trim($data['lastName'] ?? '');
    
    // Generate username from email prefix
    $username = strstr($email, '@', true); // get prefix of email
    if (!$username) {
        $username = 'user';
    }
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username); // strip invalid characters
    
    // Ensure it meets length requirements (3-20)
    if (strlen($username) < 3) {
        $username = $username . '123';
    }
    if (strlen($username) > 20) {
        $username = substr($username, 0, 20);
    }
    
    // Check if username already exists, if so append suffixes until unique
    $base_username = $username;
    $counter = 1;
    while (true) {
        $existing_username = query_fetch_one("SELECT id FROM users WHERE username = ?", [$username], 's');
        if (!$existing_username) {
            break;
        }
        $suffix = (string)$counter;
        $username = substr($base_username, 0, 20 - strlen($suffix)) . $suffix;
        $counter++;
    }
    
    // Generate a secure dummy/random password hash
    $dummy_password = bin2hex(random_bytes(16));
    $password_hash = password_hash($dummy_password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Insert new user
    $query = "INSERT INTO users (id, firstName, lastName, username, email, phone, password_hash, memberSince, is_active) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), TRUE)";
    
    if (!query_execute($query, [$id, $firstName, $lastName, $username, $email, '', $password_hash], 'sssssss')) {
        send_error('Registration failed: ' . get_db_error(), 500);
        return;
    }
    
    // Fetch and return created user
    $new_user = query_fetch_one(
        "SELECT id, firstName, lastName, username, email, phone, rating, booksPosted, booksShared, favoritesCount, memberSince FROM users WHERE id = ?",
        [$id],
        's'
    );
    
    send_success('Registration successful', $new_user, 201);
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Generate unique user ID
 */
function generate_user_id() {
    return 'user_' . bin2hex(random_bytes(12));
}

/**
 * Send success JSON response
 */
function send_success($message, $data = null, $code = 200) {
    http_response_code($code);
    $response = [
        'status' => 'success',
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit();
}

/**
 * Send error JSON response
 */
function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit();
}

?>
