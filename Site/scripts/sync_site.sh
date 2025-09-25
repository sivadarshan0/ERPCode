#!/bin/bash

# ─── Configuration ─────────────────────────────
REPO_DIR="/home/admin/ERPCode"
SITE_DIR="$REPO_DIR/Site"
TARGET_DIR="/var/www/html"
EXCLUDES=(
  "--exclude=Images/"   # Protect user-uploaded images
  "--exclude=phpmyadmin/"
  "--exclude=logs/"
  "--exclude=DBBkp/"
)

# ─── 1. Confirm Git Repo ───────────────────────
if [ ! -d "$REPO_DIR/.git" ]; then
  echo "❌ $REPO_DIR is not a Git repository!"
  exit 3
fi

# ─── 2. Pull Latest Changes (as the current user) ──
echo "🔄 Pulling latest changes as user '$(whoami)' at $(date)..."
cd "$REPO_DIR" || {
  echo "❌ Failed to cd into $REPO_DIR"
  exit 1
}

# This command will now use the SSH key of the user running the script (e.g., 'admin')
git pull origin main || {
  echo "❌ Git pull failed. Resolve any conflicts before running again."
  exit 2
}

# ─── 3. Sync Site Folder to /var/www/html (using sudo) ──────
echo "🚚 Syncing $SITE_DIR to $TARGET_DIR..."
# ONLY this rsync command needs sudo because it's writing to a protected directory.
sudo rsync -av --delete "${EXCLUDES[@]}" "$SITE_DIR/" "$TARGET_DIR/"

echo "✅ Sync complete at $(date)"

# ─── 4. Make scripts executable (using sudo) ──────
echo "🔑 Setting script permissions..."
# This command also needs sudo to change file modes in the target directory.
sudo chmod +x /var/www/html/scripts/*.sh

echo "✨ Sync process finished successfully."