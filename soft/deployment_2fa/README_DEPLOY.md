# 2FA DEPLOYMENT INSTRUCTIONS 
 
## Step 1: Database Updates 
Run SQL commands in database/sql/2fa_schema.sql 
 
## Step 2: Install Dependencies 
composer install --no-dev --optimize-autoloader 
 
## Step 3: Upload Files 
Upload all files in this folder to production server 
 
## Step 4: Clear Cache 
php artisan config:clear 
php artisan route:clear 
php artisan view:clear 
php artisan cache:clear 
