<?php
// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => true,       // Requires HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

require_once __DIR__.'/includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        // Authenticate with hashed password
        if (login($username, $password)) {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            
            // Redirect to intended page or dashboard
            $redirect = $_SESSION['redirect_to'] ?? 'index.php';
            unset($_SESSION['redirect_to']);
            header("Location: $redirect");
            exit;
        } else {
            // Generic error message (don't reveal which was wrong)
            $error = 'Invalid username or password';
            
            // Security logging
            error_log("Failed login attempt for username: $username from IP: {$_SERVER['REMOTE_ADDR']}");
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login | ERP System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <div class="login-container">
        <form method="POST" class="login-form">
            <h1>ERP Login</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required 
                       value="<?= htmlspecialchars($username ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login">Sign In</button>
            
            <div class="login-links">
                <a href="/forgot-password.php">Forgot Password?</a>
            </div>
        </form>
    </div>
</body>
</html>