<?php
// File: login.php

define('_IN_APP_', true);
session_start();

require_once __DIR__ . '/includes/db.php';
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
        $db = db();
        
        // Check if account is locked
        $locked_until = is_account_locked($username);
        if ($locked_until) {
            $error = "Account temporarily locked. Try again after " . date('h:i A', strtotime($locked_until));
        } else {
            $user = get_user_by_username($username);
            
            if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['last_activity'] = time();
                
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);
                
                // Update user login info
                update_user_login($user['id'], true, $ip_address);
                
                // Redirect to dashboard
                header('Location: index.php');
                exit;
            } else {
                // Failed login
                if ($user) {
                    update_user_login($user['id'], false, $ip_address);
                    $remaining_attempts = 4 - (int)$user['failed_attempts'];
                    
                    if ($remaining_attempts > 0) {
                        $error = "Invalid credentials. {$remaining_attempts} attempts remaining.";
                    } else {
                        $error = "Account locked for 30 minutes due to too many failed attempts.";
                    }
                } else {
                    $error = "Invalid username or password";
                }
            }
        }
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        $error = "A system error occurred. Please try again later.";
    }
}

// Render login form
ob_start();
?>
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
            
            <form method="POST" action="login.php" autocomplete="off">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
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
<?php
$content = ob_get_clean();
require 'includes/layout_login.php';
?>