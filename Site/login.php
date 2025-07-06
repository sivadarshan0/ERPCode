<?php

// Site/login.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session at the VERY TOP
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

// Process login
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

<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <h2>System Login</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['flash'])): ?>
                <div class="alert alert-<?= $_SESSION['flash']['type'] ?>">
                    <?= htmlspecialchars($_SESSION['flash']['message']) ?>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
        </div>
        
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       value="<?= htmlspecialchars($username ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
</div>

<?php
require_once BASE_PATH . '/includes/footer.php';