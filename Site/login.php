<?php

// Site/login.php

define('BASE_PATH', realpath(__DIR__));

// Start session if not exists
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

require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

// Process login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // Don't sanitize passwords
    
    if (empty($username) || empty($password)) {
        $error = 'Both fields are required';
    } elseif (login($username, $password)) {
        // Set success message
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Login successful'
        ];
        
        // Redirect to intended page
        $redirect = $_SESSION['redirect_to'] ?? '/dashboard.php';
        unset($_SESSION['redirect_to']);
        header("Location: $redirect");
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}

// Load templates
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
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        <div class="debug-info">
                            Mode: <?= file_get_contents(BASE_PATH . '/auth_mode.txt') ?>
                        </div>
                    <?php endif; ?>
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