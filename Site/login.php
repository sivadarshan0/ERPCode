<?php
// File: login.php

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable on production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Secure session (auto-expire when browser closes)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, // expire on browser close
        'path' => '/',
        'secure' => false, // set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

$error = '';
$username = '';

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = isset($_POST['username']) ? htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8') : '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Both username and password are required.';
        } elseif (login($username, $password)) {
            header("Location: index.php");
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } catch (Throwable $e) {
        error_log("Login error: " . $e->getMessage());
        $error = 'System error during login.';
    }
}
?>

<!-- Page Content -->
<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <h1>ERP System</h1>
            <h2>System Login</h2>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form" data-validate autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="<?= htmlspecialchars($username) ?>" 
                    required 
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn-login">Login</button>

            <div class="login-footer">
                <a href="/forgot-password.php">Forgot Password?</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
