#!/bin/bash
# File: /var/www/html/scripts/run_as_admin.sh
# This script allows the root user to safely run a command as the 'admin' user.
sudo -i -u admin -- "$@"