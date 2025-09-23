# Here we will run necessary laravel commands to ensure the application is up-to-date. This will be run manually after pull the code from github
#!/bin/bash 

# Steps: Run migration, Run necessary commands, Clear cache, Restart queue workers, change folder permissions
# Change to the application directory
cd /var/www/apps/erp-backend
# cd /var/www/apps/backend.dfactory.pro
# Run migrations
php8.2 artisan migrate --force
# Clear cache
php8.2 artisan optimize:clear
# Run necessary commands
php8.2 artisan app:migrate-invoice-uid
php8.2 artisan db:seed --class=RolePermissionSetting --force
php8.2 artisan db:seed --class=UpdatePriceGuideSetting --force
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
# Restart queue workers
php8.2 artisan queue:restart
# Change folder permissions
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache