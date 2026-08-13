<?php
/**
 * Simple reset password HTML page (mock email flow)
 * Usage: /v1/reset-password.php?token=LONGTOKEN
 */

// Read token from query
$token = isset($_GET['token']) ? $_GET['token'] : '';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password - Bookora</title>
  <style>
    :root { --sky: #00aaff; }
    body { font-family: Arial, sans-serif; background:#f6fbff; margin:0; padding:0; display:flex; align-items:center; justify-content:center; height:100vh; }
    .card { background:white; padding:24px; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.08); width:360px; }
    h1 { color:var(--sky); margin:0 0 12px 0; font-size:20px; }
    label { display:block; margin-top:10px; font-size:13px; }
    input[type="password"] { width:100%; padding:10px; margin-top:6px; box-sizing:border-box; border:1px solid #dfe9f2; border-radius:6px; }
    button { background:var(--sky); color:white; border:none; padding:10px 14px; margin-top:14px; width:100%; border-radius:6px; cursor:pointer; font-weight:600; }
    .message { margin-top:12px; font-size:14px; }
    .muted { color:#666; font-size:13px; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Reset Your Password</h1>
    <p class="muted">Enter a new password for your account.</p>
    <form id="resetForm">
      <label>New password
        <input type="password" id="newPassword" required minlength="6" />
      </label>
      <label>Confirm password
        <input type="password" id="confirmPassword" required minlength="6" />
      </label>
      <button type="submit">Set New Password</button>
      <div id="msg" class="message"></div>
    </form>
  </div>

  <script>
    const token = new URLSearchParams(window.location.search).get('token') || '';
    const form = document.getElementById('resetForm');
    const msg = document.getElementById('msg');

    if (!token) {
      msg.textContent = 'Invalid reset link.';
      msg.style.color = 'red';
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      msg.textContent = '';
      const p1 = document.getElementById('newPassword').value;
      const p2 = document.getElementById('confirmPassword').value;
      if (p1 !== p2) { msg.textContent = 'Passwords do not match.'; msg.style.color='red'; return; }
      if (p1.length < 6) { msg.textContent = 'Password must be at least 6 characters.'; msg.style.color='red'; return; }

      try {
        const res = await fetch('/v1/forgottenpassword.php?action=reset_password', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token: token, new_password: p1 })
        });
        const data = await res.json();
        if (res.ok && data.status === 'success') {
          msg.textContent = data.message || 'Password reset successful.';
          msg.style.color = 'green';
          form.reset();
        } else {
          msg.textContent = data.message || 'Failed to reset password.';
          msg.style.color = 'red';
        }
      } catch (err) {
        msg.textContent = 'Network error.'; msg.style.color='red';
      }
    });
  </script>
</body>
</html>
