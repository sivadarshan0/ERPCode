#!/bin/bash

# ─── Configuration ─────────────────────────────
REPO_DIR="/home/admin/ERPCode"
SITE_DIR="$REPO_DIR/Site"
TARGET_DIR="/var/www/html"
EXCLUDES=(
  "--exclude=phpmyadmin/"
  "--exclude=logs/"
  "--exclude=DBBkp/"
)

# ─── 1. Confirm Git Repo ───────────────────────
if [ ! -d "$REPO_DIR/.git" ]; then
  echo "❌ $REPO_DIR is not a Git repository!"
  exit 3
fi

# ─── 2. Pull Latest Changes ────────────────────
echo "🔄 Pulling latest changes at $(date)..."
cd "$REPO_DIR" || {
  echo "❌ Failed to cd into $REPO_DIR"
  exit 1
}

git pull origin main || {
  echo "❌ Git pull failed"
  exit 2
}

# ─── 3. Sync Site Folder to /var/www/html ──────
echo "🚚 Syncing $SITE_DIR to $TARGET_DIR..."
sudo rsync -av --delete "${EXCLUDES[@]}" "$SITE_DIR/" "$TARGET_DIR/"

echo "✅ Sync complete at $(date)"

# ─── 3. Make script executable ──────
sudo chmod +x "/var/www/html/*"
