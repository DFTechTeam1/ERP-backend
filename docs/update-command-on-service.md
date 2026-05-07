## Here are commands need to be running after code deployed to production server (May 2026)
```
php artisan app:resync-employee-greatday
php artisan app:resync-local-employment-status-with-greatday
php artisan app:out-of-sync-employee
php artisan app:sync-greatday-employee-id-to-erp
```