#!/bin/bash
# SSH to production server and clear Laravel cache

SSH_PASS="Alphia@2025"
SSH_USER="citycomm"
SSH_HOST="152.53.86.223"
PROJECT_DIR="/home/citycomm/soud-laravel"

# Use SSH with password
sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 "$SSH_USER@$SSH_HOST" "
cd $PROJECT_DIR
echo '=== Clearing Laravel Cache ==='
php artisan optimize:clear
echo '=== Done ==='
"
