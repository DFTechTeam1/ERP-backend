# Here we will run necessary laravel commands to ensure the application is up-to-date. This will be run manually after pull the code from github
#!/bin/bash 

# Steps: Run migration, Run necessary commands, Clear cache, Restart queue workers, change folder permissions
# Change to the application directory
# cd /var/www/apps/backend.dfactory.pro
cd /var/www/apps/erp-backend

# pull latest code
# git pull origin staging

# Run migrations
php82 artisan migrate --force
# Clear cache
php82 artisan optimize:clear
# Run necessary commands
php82 artisan app:migrate-invoice-uid
php82 artisan app:migrate-employee-avatar
php82 artisan app:migrate-task-hold
php82 artisan hrd:migration-employee-point
php82 artisan app:migrate-sourceable-transaction
php82 artisan app:migrate-new-point-scheme
php82 artisan app:migrate-project-task-duration-history
php82 artisan db:seed --class=RolePermissionSetting --force
php82 artisan db:seed --class=UpdatePriceGuideSetting --force
php82 artisan config:cache
php82 artisan route:cache
php82 artisan view:cache
# Restart queue workers
php82 artisan queue:restart
# Change folder permissions
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache
sudo chown -R $USER:www-data storage
sudo chown -R $USER:www-data bootstrap/cache
sudo chmod g+s storage
sudo chmod g+s bootstrap/cache
sudo find storage -type d -exec chmod g+s {} \;
sudo find bootstrap/cache -type d -exec chmod g+s {} \;