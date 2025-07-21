#!/bin/bash
# sync_site.sh

# ─── 1. Navigate to your repo ─────────────
cd ~/ERPCode || {
  echo "❌ Failed to change directory to ~/ERPCode"
  exit 1
}

# ─── 2. Pull latest changes ───────────────
echo "🔄 Pulling latest changes from GitHub..."
git pull origin main

# ─── 3. Sync files to /var/www/html ───────
echo "📁 Syncing Site/ to /var/www/html/..."
sudo rsync -av --delete \
  --exclude 'phpmyadmin/' \
  --exclude 'logs/' \
  --exclude 'DBBkp/' \
  ~/ERPCode/Site/ /var/www/html/

