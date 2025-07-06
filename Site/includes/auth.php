<?php
// File: includes/auth.php

// Temporary debug settings - REMOVE IN PRODUCTION
error_reporting(E_ALL);
ini_set('display_errors', 1);

function login($username, $password) {
    try {
        // Debug: Log input
        error_log("Login attempt for username: $username");

        // Validate input
        if (empty($username) || empty($password)) {
            error_log("Empty username or password");
            return false;
        }

        // Get database connection
        $conn = db();
        if (!$conn) {
            error_log("DB connection is null");
            throw new Exception("Database connection failed");
        }
        
        if ($conn->connect_error) {
            error_log("DB connection error: " . $conn->connect_error);
            throw new Exception("Database connection failed");
        }

        // Debug: Check if users table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
        if ($tableCheck->num_rows === 0) {
            error_log("Users table doesn't exist");
            throw new Exception("Database configuration error");
        }

        // Prepare statement
        $stmt = $conn->prepare("SELECT id, password, is_active FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            error_log("Prepare error: " . $conn->error);
            throw new Exception("Database query error");
        }

        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            error_log("Execute error: " . $stmt->error);
            throw new Exception("Database query error");
        }

        $result = $stmt->get_result();
        
        if ($result->num_rows !== 1) {
            error_log("User not found: $username");
            usleep(rand(100000, 300000));
            return false;
        }

        $user = $result->fetch_assoc();
        
        // Debug: Log password verification
        error_log("Stored hash: " . $user['password']);
        error_log("Password verify result: " . password_verify($password, $user['password']));
        
        if (password_verify($password, $user['password'])) {
            if ($user['is_active'] != 1) {
                error_log("Account inactive for user: $username");
                throw new Exception("Account inactive");
            }
            
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['last_activity'] = time();
            
            error_log("Login successful for user: $username");
            return true;
        }
        
        error_log("Password verification failed for user: $username");
        return false;
    } catch (Exception $e) {
        error_log("Authentication exception: " . $e->getMessage());
        return false;
    }
}