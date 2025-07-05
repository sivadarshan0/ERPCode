<?php
// Session handling (only start if not already active)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => false,    // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header_remove("X-Powered-By");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'ERP System') ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <header class="app-header">
        <h1><?= htmlspecialchars($headerTitle ?? 'ERP System') ?></h1>
        <?php if (isset($_SESSION['username'])): ?>
            <nav>
                <span>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="/logout.php">Logout</a>
            </nav>
        <?php endif; ?>
    </header>
    <main class="container">