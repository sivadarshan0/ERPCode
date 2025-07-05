#!/bin/bash

# Database setup script with better error handling
DB_NAME="erpdb"
ADMIN_USER="dbauser"
ADMIN_PASS="dbauser"
WEB_USER="webuser"
WEB_PASS="webuser"

# Function to display error and exit
error_exit() {
    echo "$1" 1>&2
    exit 1
}

# Check if MySQL is running
if ! systemctl is-active --quiet mysql; then
    error_exit "MySQL service is not running. Please start MySQL first."
fi

# Create database and users
sudo mysql -u root -p <<EOF || error_exit "Failed to execute MySQL commands"
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create admin user with localhost access
CREATE USER IF NOT EXISTS '$ADMIN_USER'@'localhost' IDENTIFIED BY '$ADMIN_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$ADMIN_USER'@'localhost';

-- Create web user with remote access capability
CREATE USER IF NOT EXISTS '$WEB_USER'@'%' IDENTIFIED BY '$WEB_PASS';
GRANT SELECT, INSERT, UPDATE, DELETE ON $DB_NAME.* TO '$WEB_USER'@'%';

FLUSH PRIVILEGES;
EOF

echo "Database and users created successfully"