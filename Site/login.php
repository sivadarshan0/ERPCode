<?php
// Error handling at the top
error_reporting(0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/logs/php_errors.log');

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

try {
    require_once __DIR__.'/includes/auth.php';
} catch (Throwable $e) {
    error_log("Auth include failed: ".$e->getMessage());
    die("System temporarily unavailable");
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING) ?? '';
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
<!DOCTYPE html>
<html>
<head>
    <title>ERP Login</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1>ERP System</h1>
            <h2>System Login</h2>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required 
                       value="<?= htmlspecialchars($username) ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Login</button>
            
            <div class="login-footer">
                <a href="/forgot-password.php">Forgot Password?</a>
            </div>
        </form>
    </div>
</body>
</html>