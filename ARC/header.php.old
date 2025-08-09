<?php
// File: includes/header.php

defined('_IN_APP_') or die('Unauthorized access');

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'My Application'); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link href="/assets/css/main.css" rel="stylesheet">
    
    <!-- Mobile-specific CSS -->
    <meta name="theme-color" content="#712cf9">
    <link rel="manifest" href="/manifest.json">
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">
                <i class="bi bi-box-seam"></i> ERP System
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if ($logged_in): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                        </li>

                        <!-- Merged and Enhanced Customers Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="customersDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-people-fill"></i> Customers
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="customersDropdown">
                                <li><a class="dropdown-item" href="/modules/customer/entry_customer.php">
                                    <i class="bi bi-plus-circle"></i> New Customer
                                </a></li>
                                <li><a class="dropdown-item" href="/modules/customer/list_customers.php">
                                    <i class="bi bi-list-ul"></i> View Customers
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#">
                                    <i class="bi bi-graph-up"></i> Reports
                                </a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if ($logged_in): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="#"><i class="bi bi-gear"></i> Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container-fluid flex-grow-1">
        <div class="row">
            <?php if ($logged_in && !isset($hide_sidebar)): ?>
                <?php require_once __DIR__ . '/sidebar.php'; ?>
            <?php endif; ?>
            
            <main class="<?php echo $logged_in && !isset($hide_sidebar) ? 'col-md-9 ms-sm-auto col-lg-10 px-md-4' : 'col-12'; ?>">
                <!-- Breadcrumb -->
                <?php if (isset($breadcrumbs)): ?>
                <nav aria-label="breadcrumb" class="mt-3">
                    <ol class="breadcrumb">
                        <?php foreach ($breadcrumbs as $text => $url): ?>
                            <?php if ($url): ?>
                                <li class="breadcrumb-item"><a href="<?php echo $url; ?>"><?php echo htmlspecialchars($text); ?></a></li>
                            <?php else: ?>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($text); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
                <?php endif; ?>
                
                <!-- Page Title -->
                <?php if (isset($page_title)): ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
                    <?php if (isset($page_actions)): ?>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <?php foreach ($page_actions as $action): ?>
                                    <a href="<?php echo $action['url']; ?>" class="btn btn-sm btn-<?php echo $action['style'] ?? 'primary'; ?>">
                                        <?php if (isset($action['icon'])): ?>
                                            <i class="bi bi-<?php echo $action['icon']; ?>"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($action['text']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
