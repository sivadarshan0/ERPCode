#!/bin/bash
# File: /var/www/html/scripts/backup_db.sh
# FINAL BACKUP SCRIPT - To be run by the root user (e.g., from a root cron job)
set -e

# --- Configuration ---
DB_USER="root"
DB_PASS="toor"
SITE_REPO_DIR="/home/admin/ERPCode/Site"
BACKUP_DIR="$SITE_REPO_DIR/DBBkp"
LOG_SOURCE_DIR="/var/www/html/logs"
LOG_DEST_DIR="$SITE_REPO_DIR/logs"
IMAGE_SOURCE_DIR="/var/www/html/Images"
IMAGE_DEST_DIR="$SITE_REPO_DIR/Images"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_FILE="$BACKUP_DIR/db_$TIMESTAMP.sql"

# --- 1. Database Dump (runs as root) ---
echo "🛢️  Backing up database as user '$(whoami)'..."
mkdir -p "$BACKUP_DIR" "$LOG_DEST_DIR" "$IMAGE_DEST_DIR"
mysqldump -u "$DB_USER" -p"$DB_PASS" --all-databases > "$DB_FILE"
echo "✅ Database backup complete."

# --- 2. Change Ownership of New Backup File ---
# This is critical. The new DB file was created by root, so we give it to admin.
chown admin:admin "$DB_FILE"

# --- 3. Sync Files (runs as root) ---
if [ -d "$LOG_SOURCE_DIR" ]; then
    rsync -a "$LOG_SOURCE_DIR/" "$LOG_DEST_DIR/" && echo "✅ Logs synced."
fi
if [ -d "$IMAGE_SOURCE_DIR" ]; then
    rsync -a --delete "$IMAGE_SOURCE_DIR/" "$IMAGE_DEST_DIR/" && echo "✅ Images synced."
fi

# --- 4. Hand Off to the Helper for Git Operations (run AS ADMIN user) ---
echo "🚀 Handing over to 'admin' user for Git operations..."
/var/www/html/scripts/run_as_admin.sh /var/www/html/scripts/git_commit_push.sh

echo "✨ Backup and sync process finished."