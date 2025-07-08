<?php
// File: includes/header.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Include auth functions
require_once __DIR__.'/auth.php';  // ✅ Add this line

// Define a default page title if not already set
if (!isset($pageTitle)) {
    $pageTitle = 'ERP System';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="icon" href="/assets/favicon.ico" type="image/x-icon">
</head>
<body>
