<?php
// Authentication Functions with Password Hashing

/**
 * Verify login credentials with hashed passwords
 */
function login($username, $password) {
    try {
        $conn = db();
        $stmt = $conn->prepare("SELECT id, password, is_active, locked_until FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                error_log("Account locked for user: $username until {$user['locked_until']}");
                return false;
            }
            
            // Verify password against hash
            if (password_verify($password, $user['password'])) {
                // Rehash if needed
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $newHash, $user['id']);
                    $updateStmt->execute();
                }
                
                // Reset failed attempts and update last login
                $conn->query("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = {$user['id']}");
                return true;
            } else {
                // Record failed attempt
                $conn->query("UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = {$user['id']}");
                
                // Lock account after 5 failed attempts
                if ($user['failed_attempts'] + 1 >= 5) {
                    $conn->query("UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = {$user['id']}");
                }
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
    }
    return false;
}

/**
 * Hash a password for secure storage
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Validate password strength
 */
function validate_password($password) {
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = "Must be at least 8 characters";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Must contain uppercase letters";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Must contain numbers";
    }
    return empty($errors) ? true : $errors;
}