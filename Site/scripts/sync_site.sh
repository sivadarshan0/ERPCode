#!/bin/bash
# FINAL SYNC SCRIPT - To be run by the root user (e.g., from a root cron job)
set -e

# --- Configuration ---
REPO_DIR="/home/admin/ERPCode"
SITE_DIR="$REPO_DIR/Site"
TARGET_DIR="/var/www/html"
EXCLUDES=( "--exclude=Images/" "--exclude=phpmyadmin/" "--exclude=logs/" "--exclude=DBBkp/" )

# --- 1. Pull Latest Changes (run AS ADMIN user) ---
echo "🔄 Pulling latest changes from GitHub as 'admin' user..."
# Uses the helper script to run the git pull command as the 'admin' user
/home/admin/ERPCode/Site/scripts/run_as_admin.sh git -C "$REPO_DIR" pull origin main

# --- 2. Sync Site to Web Directory (run as root) ---
echo "🚚 Syncing code to $TARGET_DIR..."
rsync -av --delete "${EXCLUDES[@]}" "$SITE_DIR/" "$TARGET_DIR/"
echo "✅ Code sync complete."

# --- 3. Set Final, Secure Permissions (run as root) ---
echo "🔑 Setting final permissions for the web server..."

# Set ownership of all files to the web server user (www-data)
# This is crucial for security and for allowing the web application to function.
chown -R www-data:www-data "$TARGET_DIR"

# Set directory permissions: owner/group can read/write/execute, others can only read/execute.
find "$TARGET_DIR" -type d -exec chmod 775 {} \;

# Set file permissions: owner/group can read/write, others can only read.
find "$TARGET_DIR" -type f -exec chmod 664 {} \;

# Explicitly make your shell scripts executable by the owner and group.
chmod +x /var/www/html/scripts/*.sh

echo "✅ Permissions set."
echo "✨ Sync process finished successfully."