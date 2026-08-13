<?php
/**
 * Bookora Platform - Forgotten Password API
 * Handles password reset requests
 */

require_once 'config/corshandler.php';
require_once 'config/db.php';
require_once 'services/password.php';

$method = $_SERVER['REQUEST_METHOD'];

// Route to appropriate handler
if ($method === 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    if ($action === 'request_reset') {
        handle_request_reset();
    } elseif ($action === 'reset_password') {
        handle_reset_password();
    } else {
        send_error('Invalid action', 400);
    }
} else {
    send_error('Method not allowed', 405);
}

// ============================================================
// REQUEST PASSWORD RESET
// ============================================================

function handle_request_reset() {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate input
    if (!isset($data['email']) || empty($data['email'])) {
        send_error('Email is required', 400);
        return;
    }
    
    $email = trim($data['email']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_error('Invalid email format', 400);
        return;
    }
    
    // Check if user exists
    $user = query_fetch_one(
        "SELECT id, firstName, email FROM users WHERE email = ?",
        [$email],
        's'
    );
    
    if (!$user) {
        // For security, we don't reveal if email exists or not
        send_success('If an account exists with this email, you will receive a password reset link', null, 200);
        return;
    }
    
    // Generate and store reset token via service
    $reset_token = generate_reset_token_for_email($email);
    if ($reset_token === null) {
        // For security, still return success message
        send_success('If an account exists with this email, you will receive a password reset link', null, 200);
        return;
    }
    
    // Simulate sending reset email and provide mock reset link for testing
    simulate_send_reset_email($user['email'], $user['firstName'], $reset_token);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $reset_link = $protocol . '://' . $host . '/v1/reset-password.php?token=' . $reset_token;

    send_success('If an account exists with this email, you will receive a password reset link', ['reset_link' => $reset_link], 200);
}

// ============================================================
// RESET PASSWORD WITH TOKEN
// ============================================================

function handle_reset_password() {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate input
    if (!isset($data['token']) || empty($data['token'])) {
        send_error('Reset token is required', 400);
        return;
    }
    
    if (!isset($data['new_password']) || empty($data['new_password'])) {
        send_error('New password is required', 400);
        return;
    }
    
    $reset_token = trim($data['token']);
    $new_password = trim($data['new_password']);
    
    // Validate password strength
    if (strlen($new_password) < 6) {
        send_error('Password must be at least 6 characters', 400);
        return;
    }
    
    // Reset password via service
    $ok = reset_password_with_token($reset_token, $new_password);
    if (!$ok) {
        send_error('Invalid or expired reset token', 401);
        return;
    }
    
    send_success('Password has been reset successfully', null, 200);
}

// ============================================================
// SIMULATE EMAIL SENDING SERVICE
// ============================================================

function simulate_send_reset_email($email, $firstName, $reset_token) {
    // In production, this would use an email service like:
    // - PHPMailer
    // - Swift Mailer
    // - SendGrid
    // - AWS SES
    // - MailChimp
    
    // Reset link would be: https://app.bookora.com/reset-password?token=RESET_TOKEN
    $reset_link = "https://bookora.app/reset-password?token=" . $reset_token;
    
    // Simulate logging the email
    $email_log = [
        'to' => $email,
        'subject' => 'Password Reset Request',
        'firstName' => $firstName,
        'reset_link' => $reset_link,
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'simulated_sent'
    ];
    
    // Log to file for debugging
    $log_file = __DIR__ . '/../logs/password_reset.log';
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    
    file_put_contents($log_file, json_encode($email_log) . "\n", FILE_APPEND);
    
    // In production, uncomment and use actual email service:
    /*
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getenv('MAIL_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = getenv('MAIL_USERNAME');
        $mail->Password = getenv('MAIL_PASSWORD');
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        $mail->setFrom('noreply@bookora.com', 'Bookora');
        $mail->addAddress($email, $firstName);
        $mail->Subject = 'Password Reset Request';
        $mail->Body = "Hi {$firstName},\n\nClick this link to reset your password:\n{$reset_link}\n\nThis link expires in 1 hour.\n\nBest,\nBookora Team";
        
        $mail->send();
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $e->getMessage());
    }
    */
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

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
