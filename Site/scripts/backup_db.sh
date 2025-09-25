#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

# ─── Configurable Variables ───────────────────────────────────────────────────
DB_USER="root"
DB_PASS="toor"       # Your actual password
SITE_REPO_DIR="/home/admin/ERPCode/Site"
BACKUP_DIR="$SITE_REPO_DIR/DBBkp"
LOG_SOURCE_DIR="/var/www/html/logs"
LOG_DEST_DIR="$SITE_REPO_DIR/logs"
IMAGE_SOURCE_DIR="/var/www/html/Images"
IMAGE_DEST_DIR="$SITE_REPO_DIR/Images"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_FILE="$BACKUP_DIR/db_$TIMESTAMP.sql"
MAX_BACKUPS=7

# ─── 1. Ensure destination folders exist ───────────────────────────────────────
mkdir -p "$BACKUP_DIR"
mkdir -p "$LOG_DEST_DIR"
mkdir -p "$IMAGE_DEST_DIR"

# ─── 2. Dump full database (using sudo) ────────────────────────────────────────
echo "🛢️  Backing up all databases to $DB_FILE..."
# ONLY this command needs sudo to access the database as the root user.
sudo mysqldump -u "$DB_USER" -p"$DB_PASS" --all-databases > "$DB_FILE"
echo "✅ Database backup complete."

# ─── 3. Copy log files (Safely) ────────────────────────────────────────────────
echo "📂 Checking for log files to copy..."
if [ -d "$LOG_SOURCE_DIR" ] && [ "$(ls -A $LOG_SOURCE_DIR)" ]; then
  # This command doesn't need sudo as 'admin' can read from /var/www/html
  cp -r "$LOG_SOURCE_DIR"/* "$LOG_DEST_DIR/"
  echo "✅ Log files copied successfully."
else
  echo "⚠️  Log directory '$LOG_SOURCE_DIR' not found or is empty. Skipping log copy."
fi

# ─── 4. Sync Image Files ───────────────────────────────────────────────────────
echo "🖼️  Syncing image files..."
if [ -d "$IMAGE_SOURCE_DIR" ]; then
  # This command also doesn't need sudo
  rsync -av --delete "$IMAGE_SOURCE_DIR/" "$IMAGE_DEST_DIR/"
  echo "✅ Image files synced successfully."
else
  echo "⚠️  Image directory '$IMAGE_SOURCE_DIR' not found. Skipping image sync."
fi

# ─── 5. Cleanup old backups ────────────────────────────────────────────────────
echo "🧹 Keeping only the last $MAX_BACKUPS database backups in $BACKUP_DIR..."
ls -1t "$BACKUP_DIR"/*.sql | tail -n +$((MAX_BACKUPS + 1)) | xargs -r rm --
echo "🗑️  Old backups cleaned up."

# ─── 6. Auto Commit to Git (as the 'admin' user) ───────────────────────────────
echo "🚀 Committing changes to Git..."
cd "$SITE_REPO_DIR" || { echo "❌ Failed to navigate to Git repository: $SITE_REPO_DIR"; exit 1; }

# Add all changes within the backup directories
git add DBBkp/ logs/ Images/

# Check if there are any changes to commit to avoid an empty commit error
if git diff-index --quiet HEAD --; then
  echo "ℹ️  No new backups, logs, or images to commit. Git is up to date."
else
  # Commit the changes with a descriptive message
  git commit -m "Automated Site Backup & Sync: $TIMESTAMP"
  
  # This push will now use the 'admin' user's SSH key and succeed.
  git push origin main
  echo "✅ Changes successfully pushed to GitHub."
fi

echo "✨ Backup and sync process finished."