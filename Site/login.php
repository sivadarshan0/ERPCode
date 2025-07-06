<?php
// File: /var/www/html/login.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

define('BASE_PATH', realpath(__DIR__));

require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Both fields are required';
    } elseif (login($username, $password)) {
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Login successful'
        ];
        $redirect = $_SESSION['redirect_to'] ?? 'index.php';
        unset($_SESSION['redirect_to']);
        header("Location: $redirect");
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}

$title = 'ERP System - Login';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="simple-login-container">
    <div class="simple-login-box">
        <h1>ERP System</h1>
        <h2>System Login</h2>
        
        <?php if (!empty($error)): ?>
            <div class="simple-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" class="simple-login-form">
            <div class="simple-form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       value="<?= htmlspecialchars($username ?? '') ?>">
            </div>
            
            <div class="simple-form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="simple-login-btn">Login</button>
        </form>
        
        <div class="simple-footer">
            © <?= date('Y') ?> ERP System
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>