#!/bin/bash
# File: /var/www/html/scripts/git_commit_push.sh
# HELPER SCRIPT - Contains only Git commands, to be run as the 'admin' user.
set -e

# --- Configuration ---
SITE_REPO_DIR="/home/admin/ERPCode/Site"
MAX_BACKUPS=7
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# --- 1. Navigate to Repository ---
cd "$SITE_REPO_DIR" || { echo "❌ Git helper failed: Could not cd into $SITE_REPO_DIR"; exit 1; }

# --- 2. Cleanup Old Backups ---
echo "🧹 Cleaning up old database backups..."
# Find all .sql files, sort by time, skip the newest 7, and delete the rest.
ls -1t DBBkp/*.sql | tail -n +$((MAX_BACKUPS + 1)) | xargs -r rm --
echo "🗑️  Old backups cleaned up."

# --- 3. Git Add, Commit, and Push ---
echo "➕ Adding changes to Git..."
git add DBBkp/ logs/ Images/

# Check if there are actually any changes to commit
if git diff-index --quiet HEAD --; then
  echo "ℹ️  No new backups, logs, or images to commit. Git is up to date."
else
  echo "📦 Committing changes..."
  git commit -m "Automated Site Backup & Sync: $TIMESTAMP"
  
  echo "📤 Pushing to GitHub..."
  git push origin main
  echo "✅ Changes successfully pushed to GitHub."
fi