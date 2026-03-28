# PGConnect on Vercel

PGConnect can be deployed to Vercel by using the PHP community runtime.

Important:

- Vercel does not provide PHP as an official runtime. This project uses the `vercel-php` community runtime.
- You still need an external MySQL database.
- GitHub Pages will not work for this project.

## What this repo already includes

- `vercel.json` to run `*.php` files with `vercel-php`
- automatic base URL detection
- `.env` support through `backend/.env`

## Vercel setup

1. Push this repository to GitHub.
2. Import the GitHub repo into Vercel.
3. Keep the project root as the repo root.
4. Add Vercel environment variables:

```env
PGCONNECT_DB_HOST=your-db-host
PGCONNECT_DB_PORT=3306
PGCONNECT_DB_NAME=your-db-name
PGCONNECT_DB_USER=your-db-user
PGCONNECT_DB_PASS=your-db-password
PGCONNECT_DB_CHARSET=utf8mb4
PGCONNECT_BASE_URL=
```

5. Redeploy.

## Database

You need a MySQL database outside Vercel, for example:

- PlanetScale
- Railway MySQL
- Neon with MySQL-compatible service only if applicable
- Hostinger / cPanel MySQL
- InfinityFree MySQL

Import `pgconnect.sql` into that database before opening the site.

## Notes

- Root `/` is routed to `index.php`.
- Static files like images, `sw.js`, and `manifest.webmanifest` are served by the filesystem.
- File uploads on Vercel are not a good long-term fit because Vercel functions have ephemeral storage. For production, use object storage for uploads.
