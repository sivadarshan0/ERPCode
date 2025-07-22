#!/bin/bash

# ─── Configuration ─────────────────────────────
REPO_DIR="$HOME/ERPCode"
SITE_DIR="$REPO_DIR/Site"
TARGET_DIR="/var/www/html"
EXCLUDES=(
  "--exclude=phpmyadmin/"
  "--exclude=logs/"
  "--exclude=DBBkp/"
)

# ─── 1. Navigate to the repo ───────────────────
cd "$REPO_DIR" || {
  echo "❌ Failed to change directory to $REPO_DIR"
  exit 1
}

# ─── 2. Pull latest changes ────────────────────
echo "🔄 Pulling latest changes at $(date)..."
git pull origin main || {
  echo "❌ Git pull failed"
  exit 2
}

# ─── 3. Sync files to web root ─────────────────
echo "🚚 Syncing files to $TARGET_DIR (excluding logs/DBBkp/phpmyadmin)..."
sudo rsync -av --delete "${EXCLUDES[@]}" "$SITE_DIR/" "$TARGET_DIR/"

echo "✅ Sync complete at $(date)"
