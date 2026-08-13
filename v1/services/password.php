<?php
/**
 * Password service helpers for reset token generation and verification
 */

require_once __DIR__ . '/../config/db.php';

/**
 * Generate and store a reset token for a given email. Returns raw token on success, null otherwise.
 */
function generate_reset_token_for_email($email) {
    // Find user
    $user = query_fetch_one("SELECT id, firstName, email FROM users WHERE email = ?", [$email], 's');
    if (!$user) return null;

    $reset_token = bin2hex(random_bytes(16));
    $reset_token_hash = hash('sha256', $reset_token);
    $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $update_query = "UPDATE users SET reset_token_hash = ?, reset_token_expires = ? WHERE id = ?";
    $ok = query_execute($update_query, [$reset_token_hash, $reset_expires, $user['id']], 'sss');
    if (!$ok) return null;

    return $reset_token;
}

/**
 * Verify a raw token and return the user row if valid, null otherwise.
 */
function verify_reset_token($token) {
    $reset_token_hash = hash('sha256', $token);
    $user = query_fetch_one(
        "SELECT id, email, firstName FROM users WHERE reset_token_hash = ? AND reset_token_expires > NOW()",
        [$reset_token_hash],
        's'
    );

    return $user;
}

/**
 * Reset password using raw token and clear token fields. Returns true on success.
 */
function reset_password_with_token($token, $new_password) {
    $user = verify_reset_token($token);
    if (!$user) return false;

    $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
    $update_query = "UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_token_expires = NULL WHERE id = ?";
    return query_execute($update_query, [$password_hash, $user['id']], 'ss');
}

?>
