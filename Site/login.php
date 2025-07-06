<?php
// File: login.php

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/logs/php_errors.log');

// Secure session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__.'/includes/header.php';

try {
    require_once __DIR__.'/includes/auth.php';
} catch (Throwable $e) {
    error_log("Auth include failed: ".$e->getMessage());
    die("<div class='system-error'>System temporarily unavailable</div>");
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Replace deprecated FILTER_SANITIZE_STRING with modern alternative
        $username = isset($_POST['username']) ? htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8') : '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Both fields are required';
        } elseif (login($username, $password)) {
            header("Location: index.php");
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } catch (Throwable $e) {
        error_log("Login error: ".$e->getMessage());
        $error = 'System error during login';
    }
}
?>

<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <h1>ERP System</h1>
            <h2>System Login</h2>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" class="login-form" autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required 
                       value="<?= htmlspecialchars($username) ?>" autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn-login">Login</button>
            
            <div class="login-footer">
                <a href="/forgot-password.php">Forgot Password?</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__.'/includes/footer.php'; ?>