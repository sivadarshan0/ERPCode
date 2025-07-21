#!/bin/bash

# ─── CONFIGURATION ─────────────────────────────
DB_USER="root"
DB_PASS="toor"
BACKUP_DIR="/var/www/html/Site/DBBkp"
DATE=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_FILE="$BACKUP_DIR/DBBackup_$DATE.sql"

# ─── CREATE BACKUP DIRECTORY IF NEEDED ────────
mkdir -p "$BACKUP_DIR"

# ─── PERFORM BACKUP ───────────────────────────
sudo mysqldump -u "$DB_USER" -p"$DB_PASS" --all-databases --routines --events --triggers > "$BACKUP_FILE"

# ─── COMPRESS BACKUP FILE ─────────────────────
gzip "$BACKUP_FILE"

# ─── FINAL OUTPUT ─────────────────────────────
echo "Backup completed: $BACKUP_FILE.gz"
