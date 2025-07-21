#!/bin/bash
# backup_db.sh

# ─── CONFIGURATION ─────────────────────────────
DB_USER="root"
DB_PASS=""  # Leave empty if using sudo (socket auth)
BACKUP_DIR="/var/www/html/DBBkp"
DATE=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_FILE="$BACKUP_DIR/DBBackup_$DATE.sql"
REPO_DIR=~/ERPCode

# ─── CREATE BACKUP DIRECTORY IF NEEDED ────────
mkdir -p "$BACKUP_DIR"

# ─── DUMP DATABASE ─────────────────────────────
# Use sudo if root requires socket authentication
sudo mysqldump --all-databases --routines --events --triggers > "$BACKUP_FILE"

# ─── COMPRESS BACKUP FILE ─────────────────────
gzip "$BACKUP_FILE"
echo "✅ Backup completed: $BACKUP_FILE.gz"

# ─── COPY TO GIT REPO IF NEEDED ───────────────
if [ "$BACKUP_DIR" != "$REPO_DIR/Site/DBBkp" ]; then
    mkdir -p "$REPO_DIR/Site/DBBkp"
    cp "$BACKUP_FILE.gz" "$REPO_DIR/Site/DBBkp/"
fi

# ─── GIT COMMIT & PUSH ────────────────────────
cd "$REPO_DIR" || exit

# Stage backup folder
git add Site/DBBkp

# Commit with timestamp
git commit -m "Auto-commit: Added DB backup on $(date +'%Y-%m-%d %H:%M:%S')" || echo "No DB changes to commit."

# Push to GitHub
git push origin main
