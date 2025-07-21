#!/bin/bash

# Navigate to your repo
cd ~/ERPCode

# Pull latest changes from GitHub
git pull origin main

# Sync the Site folder to /var/www/html, excluding folders
sudo rsync -av --delete \
  --exclude 'phpmyadmin/' \
  --exclude 'logs/' \
  ~/ERPCode/Site/ /var/www/html/