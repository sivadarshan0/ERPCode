<?php
// File: includes/auth.php

function login($username, $password) {
    try {
        // Validate input
        if (empty($username) || empty($password)) {
            return false;
        }

        // Get database connection
        $conn = db();
        if (!$conn || $conn->connect_error) {
            throw new Exception("Database connection failed");
        }

        // Prepare statement with parameterized query
        $stmt = $conn->prepare("SELECT id, password, is_active FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception("Prepare failed: ".$conn->error);
        }

        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: ".$stmt->error);
        }

        $result = $stmt->get_result();
        
        // Verify user exists
        if ($result->num_rows !== 1) {
            // Delay to prevent timing attacks
            usleep(rand(100000, 300000));
            return false;
        }

        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            if ($user['is_active'] != 1) {
                throw new Exception("Account inactive");
            }
            
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['last_activity'] = time();
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Authentication error: ".$e->getMessage());
        return false;
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function logout() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}