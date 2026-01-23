# Here we will run necessary laravel commands to ensure the application is up-to-date. This will be run manually after pull the code from github
#!/bin/bash 

# Steps: Run migration, Run necessary commands, Clear cache, Restart queue workers, change folder permissions
# Change to the application directory
cd /var/www/apps/backend.dfactory.pro

# pull latest code
git pull origin staging

# Run migrations
php artisan migrate --force
# Clear cache
php artisan optimize:clear
# Run necessary commands
php artisan app:migrate-invoice-uid
php artisan db:seed --class=RolePermissionSetting --force
php artisan db:seed --class=UpdatePriceGuideSetting --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
# Restart queue workers
php artisan queue:restart
# Change folder permissions
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache
sudo chown -R $USER:www-data storage
sudo chown -R $USER:www-data bootstrap/cache
sudo chmod g+s storage
sudo chmod g+s bootstrap/cache
sudo find storage -type d -exec chmod g+s {} \;
sudo find bootstrap/cache -type d -exec chmod g+s {} \;