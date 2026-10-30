# Smart Campus Connect Deployment

Use this checklist for cPanel, shared hosting, or a VPS running Apache, PHP, and MySQL.

## 1. Hosting Requirements

- PHP 8.0 or newer with `mysqli`, `curl`, `json`, `fileinfo`, and session support.
- MySQL or MariaDB database.
- Apache with `.htaccess` enabled, or equivalent Nginx rules.
- HTTPS enabled on your domain.

## 2. Upload Files

Upload the project contents to your web root, usually `public_html/`.

Do not run public setup files on production. The included `.htaccess` blocks these files:

- `setup.php`
- `insert_sample_data.php`
- `verify_setup.php`
- `healthcheck.php`
- `db_schema.sql`

## 3. Create The Database

In your hosting control panel:

1. Create a MySQL database.
2. Create a database user and password.
3. Assign the user full privileges on the database.
4. Import `db_schema.sql` using phpMyAdmin or the MySQL CLI.

## 4. Configure Production Values

Edit `includes/config.php` defaults, or set environment variables if your host supports them.

Typical production values:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
DB_HOST=localhost
DB_USER=your_cpanel_db_user
DB_PASS=your_secure_db_password
DB_NAME=your_cpanel_db_name
DB_PORT=3306
GOOGLE_MAPS_API_KEY=your_google_maps_key
PAYNOW_INTEGRATION_ID=your_paynow_integration_id
PAYNOW_INTEGRATION_KEY=your_paynow_integration_key
PAYNOW_SANDBOX=false
```

The app automatically builds these payment URLs from `APP_URL` unless you override them:

```text
PAYNOW_RETURN_URL=https://yourdomain.com/payment_success.php
PAYNOW_RESULT_URL=https://yourdomain.com/api/paynow_callback.php
```

## 5. Upload Permissions

Make sure these directories exist and are writable by PHP:

```text
uploads/
uploads/accommodations/
uploads/buses/
uploads/internships/
```

Recommended permissions are usually `755` for folders. Some shared hosts require `775`.

## 6. PayNow And Google Setup

- In Google Cloud, restrict the Maps API key to your production domain.
- In PayNow, set the result/callback URL to:

```text
https://yourdomain.com/api/paynow_callback.php
```

- Set the return URL to:

```text
https://yourdomain.com/payment_success.php
```

## 7. Final Checks

- Visit `https://yourdomain.com/`.
- Register a test user.
- Confirm login/logout works.
- Add a test accommodation image and verify it appears.
- Check that protected pages redirect to `login.php` when logged out.
- Keep sample/test accounts out of the production database.
