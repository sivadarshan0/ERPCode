#!/bin/bash

# ─── Configurable Variables ─────────────────────
DB_USER="root"
DB_PASS="your_db_password"       # Replace with your actual password
BACKUP_DIR="/home/admin/ERPCode/Site/DBBkp"
LOG_SOURCE="/var/www/html/logs"
LOG_DEST="/home/admin/ERPCode/Site/logs"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_FILE="$BACKUP_DIR/db_$TIMESTAMP.sql"

# ─── 1. Ensure destination folders exist ────────
mkdir -p "$BACKUP_DIR"
mkdir -p "$LOG_DEST"

# ─── 2. Dump full database ──────────────────────
echo "🛢️  Backing up database to $DB_FILE"
mysqldump -u "$DB_USER" -p"$DB_PASS" --all-databases > "$DB_FILE"

if [ $? -eq 0 ]; then
  echo "✅ Database backup complete."
else
  echo "❌ Database backup failed."
  exit 1
fi

# ─── 3. Copy log files ──────────────────────────
echo "📂 Copying logs from $LOG_SOURCE to $LOG_DEST..."
cp -r "$LOG_SOURCE/"* "$LOG_DEST/"

echo "✅ Log copy complete."

# ─── 4. Cleanup old backups ─────────────────────
MAX_BACKUPS=7
echo "🧹 Keeping only the last $MAX_BACKUPS backups in $BACKUP_DIR..."
cd "$BACKUP_DIR" || exit 1
ls -1t *.sql | tail -n +$((MAX_BACKUPS + 1)) | xargs -r rm --
echo "🗑️  Old backups cleaned up."

# ─── 5. Git status reminder ─────────────────────
echo "📝 You can now add, commit, and push DBBkp and logs via Git."

# ─── 6. Auto Commit to Git ──────────────────────
cd /home/admin/ERPCode || exit
git pull origin main --rebase
git add Site/DBBkp/ Site/logs/
git commit -m "🔄 Auto backup: $TIMESTAMP"
git push origin main
