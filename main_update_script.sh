#!/bin/bash 

cd /var/www/apps/erp-backend

# check if 'php8.2' command is available or not, if not use 'php'
if command -v php8.2 >/dev/null 2>&1; then
    PHP_CMD="php8.2"
elif command -v php8.2 >/dev/null 2>&1; then
    PHP_CMD="PHP82"
else
    PHP_CMD="php"
fi

# fix permission
sudo chmod -R 777 storage bootstrap

# Run the update script using the determined PHP command
echo "Running migration .... "
$PHP_CMD artisan migrate --force

# echo "Running role permission seeder ...."
$PHP_CMD artisan db:seed --class=RolePermissionSetting

# echo "Running migration on project class point ..."
$PHP_CMD artisan app:migrate-new-point-scheme

# echo "Running migration on task duration histories ...."
$PHP_CMD artisan app:migrate-project-task-duration-history

# echo "Running migration on employee avatar ...."
$PHP_CMD artisan app:migrate-employee-avatar

# echo "Running migration on sourceable transactions ...."
$PHP_CMD artisan app:migrate-sourceable-transaction

# echo "Logging out all users ..."
$PHP_CMD artisan app:clear-all-authenticated-sesssion

# update interactive price
$PHP_CMD artisan db:seed --class=UpdatePriceGuideSetting