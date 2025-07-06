<?php
require_once __DIR__ . '/../includes/db.php';

echo "Starting password migration...\n";

$conn = db();

// Get users with non-bcrypt passwords
$result = $conn->query("
    SELECT id, password 
    FROM users 
    WHERE password NOT LIKE '$2y$%'
    AND password NOT LIKE '$2a$%'
    AND password NOT LIKE '$2b$%'
");

$migrated = 0;

while ($user = $result->fetch_assoc()) {
    // Skip empty passwords
    if (empty($user['password'])) {
        continue;
    }
    
    $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $user['id']);
    
    if ($stmt->execute()) {
        echo "Migrated user ID: {$user['id']}\n";
        $migrated++;
    } else {
        echo "Error migrating user ID: {$user['id']}\n";
    }
}

echo "\nMigration complete!\n";
echo "Total migrated: $migrated users\n";

// Security reminder
echo "\nIMPORTANT: Delete this script after execution!\n";