<?php
// File: /var/www/html/login.php

// Error reporting at the very top
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);

// Security constant definition
if (!defined('_IN_APP_')) {
    define('_IN_APP_', true);
}

// Secure session configuration
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'];

    try {
        // Validate input
        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required");
        }

        // Check for account lock
        $locked_until = is_account_locked($username);
        if ($locked_until) {
            throw new Exception("Account temporarily locked. Try again after " . date('h:i A', strtotime($locked_until)));
        }

        // Get user from database
        $user = get_user_by_username($username);
        if (!$user || !is_array($user)) {
            error_log("Failed login attempt for non-existent user: " . $username);
            throw new Exception("Invalid username or password");
        }

        // Verify account status
        if (empty($user['is_active'])) {
            throw new Exception("Account is inactive. Please contact administrator.");
        }

        // Verify password
        if (!password_verify($password, $user['password'] ?? '')) {
            // Update failed attempt
            if (!empty($user['id'])) {
                update_user_login($user['id'], false, $ip_address);
                $remaining_attempts = max(0, 4 - (int)($user['failed_attempts'] ?? 0));
                
                if ($remaining_attempts > 0) {
                    throw new Exception("Invalid credentials. {$remaining_attempts} attempts remaining.");
                }
                throw new Exception("Account locked for 30 minutes due to too many failed attempts.");
            }
            throw new Exception("Invalid username or password");
        }

        // Successful login - set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $ip_address;
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        
        // Update user login info
        update_user_login($user['id'], true, $ip_address);
        
        // Set secure session cookie
        setcookie(
            session_name(),
            session_id(),
            [
                'expires' => time() + 86400, // 1 day
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
        
        // Redirect to dashboard
        header('Location: index.php');
        exit;

    } catch (Exception $e) {
        error_log('Login error [' . $ip_address . ']: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}

// Render login form
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - System Name</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="/assets/css/main.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="card shadow-sm">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h2 class="fw-bold">System Login</h2>
                    <p class="text-muted">Enter your credentials to continue</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="login.php" autocomplete="off" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($username); ?>" required autofocus
                               pattern="[a-zA-Z0-9_]{3,20}" title="3-20 alphanumeric characters">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required
                                   minlength="8" pattern="^(?=.*[A-Za-z])(?=.*\d).{8,}$" 
                                   title="At least 8 characters with 1 letter and 1 number">
                            <button class="btn btn-outline-secondary toggle-password" type="button">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
                    </div>
                    
                    <div class="text-center">
                        <a href="forgot_password.php" class="text-decoration-none">Forgot password?</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>