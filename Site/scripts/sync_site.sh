#!/bin/bash
# FINAL SYNC SCRIPT - To be run by the root user (e.g., from a root cron job)
set -e

# --- Configuration ---
REPO_DIR="/home/admin/ERPCode"
SITE_DIR="$REPO_DIR/Site"
TARGET_DIR="/var/www/html"
EXCLUDES=( "--exclude=Images/" "--exclude=phpmyadmin/" "--exclude=logs/" "--exclude=DBBkp/" )
HELPER_SCRIPT="/var/www/html/scripts/run_as_admin.sh" # Use the live version of the helper

# --- 1. Pull Latest Changes (run AS ADMIN user) ---
echo "🔄 Pulling latest changes from GitHub as 'admin' user..."
if [ -f "$HELPER_SCRIPT" ]; then
    "$HELPER_SCRIPT" git -C "$REPO_DIR" pull origin main
else
    # Fallback for the very first run if helper isn't in /var/www/html yet
    sudo -i -u admin -- git -C "$REPO_DIR" pull origin main
fi


# --- 2. Sync Site to Web Directory (run as root) ---
echo "🚚 Syncing code to $TARGET_DIR..."
# The --owner and --group flags tell rsync to set the ownership during the copy.
rsync -av --delete "${EXCLUDES[@]}" --owner=www-data --group=www-data "$SITE_DIR/" "$TARGET_DIR/"
echo "✅ Code sync complete."


# --- 3. Set Final, Secure Permissions (run as root) ---
echo "🔑 Setting final permissions for the web server..."

# As a final guarantee, ensure correct ownership and permissions.
# This is important for files that were not touched by rsync.
chown -R www-data:www-data "$TARGET_DIR"
find "$TARGET_DIR" -type d -exec chmod 775 {} \;
find "$TARGET_DIR" -type f -exec chmod 664 {} \;
chmod +x "$TARGET_DIR/scripts/"*.sh

echo "✅ Permissions set."
echo "✨ Sync process finished successfully."