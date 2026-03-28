# PGConnect Deployment Guide

PGConnect is a PHP + MySQL application. Do not deploy it to GitHub Pages because GitHub Pages only serves static files and does not run PHP or MySQL.

## Supported hosting

Use any hosting that supports:

- PHP 8+
- MySQL or MariaDB
- Apache with `index.php`

Examples:

- InfinityFree
- Hostinger
- 000webhost
- cPanel hosting
- any VPS/shared hosting with PHP + MySQL

## Files to upload

Upload the full project folder contents to your web root, for example `htdocs`, `public_html`, or a subfolder.

Important files:

- `index.php`
- `backend/`
- `admin/`
- `owner/`
- `user/`
- `uploads/`
- `includes/`
- `.htaccess`
- `manifest.webmanifest`
- `sw.js`

## Database setup

1. Create a MySQL database.
2. Create a database user and password.
3. Import `pgconnect.sql`.

## App config

This project supports environment variables and a local `.env` file.

### Option A: use `backend/.env`

Create a file at `backend/.env` with values like:

```env
PGCONNECT_DB_HOST=localhost
PGCONNECT_DB_PORT=3306
PGCONNECT_DB_NAME=your_database_name
PGCONNECT_DB_USER=your_database_user
PGCONNECT_DB_PASS=your_database_password
PGCONNECT_DB_CHARSET=utf8mb4
PGCONNECT_BASE_URL=
```

Notes:

- Leave `PGCONNECT_BASE_URL=` empty if the app is installed at the domain root.
- If the app is installed in a subfolder like `https://example.com/PGConnect`, set `PGCONNECT_BASE_URL=/PGConnect`

### Option B: use hosting environment variables

If your host supports environment variables, set:

- `PGCONNECT_DB_HOST`
- `PGCONNECT_DB_PORT`
- `PGCONNECT_DB_NAME`
- `PGCONNECT_DB_USER`
- `PGCONNECT_DB_PASS`
- `PGCONNECT_DB_CHARSET`
- `PGCONNECT_BASE_URL`

## Permissions

Make sure these are writable by the web server if your host requires explicit permissions:

- `uploads/`
- `backend/` for log files

## First deploy checklist

1. Upload files to PHP hosting.
2. Import `pgconnect.sql`.
3. Add `backend/.env`.
4. Open the site URL.
5. If the database fails, verify DB host, DB name, DB user, and DB password.
6. If images cannot upload, fix permissions on `uploads/`.

## Important

- GitHub Pages will not work for this app.
- `index.php` is the real app entrypoint, not `index.html`.
