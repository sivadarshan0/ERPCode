<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth.php';

$conn = db();
$users = $conn->query("SELECT id, password FROM users");

while ($user = $users->fetch_assoc()) {
    if (!password_needs_rehash($user['password'])) continue;
    
    $hashed = password_hash($user['password'], PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password = '$hashed' WHERE id = {$user['id']}");
    echo "Updated password for user {$user['id']}\n";
}