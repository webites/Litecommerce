# Litecommerce

Open-source, self-hosted e-commerce for small and mid-sized shops, built as a
modular monolith on Laravel. One installation runs one shop. It is designed to
work on ordinary shared hosting: no Node.js, SSH, Composer or permanently
running workers are needed on the server where the shop runs.

Languages: the panel and storefront are available in Polish and English.

## Status

Pre-release. There is no tagged version and no ready-to-upload release package
yet. Until one exists, installing means building the code yourself as
described below. Interfaces and database schema may still change.

## Requirements

- PHP 8.3 or 8.4 with the extensions `ctype, curl, dom, fileinfo, filter,
  hash, mbstring, openssl, pcre, pdo, pdo_mysql, session, tokenizer, xml,
  xmlwriter, bcmath, intl, gd, zip` (`opcache` is recommended)
- MySQL 8.0+ or MariaDB 10.11+
- `memory_limit` of at least 128 MB (256 MB recommended)
- Write access to `storage/` and `bootstrap/cache/`
- A web server whose document root is the `public/` directory

## Install

1. Build the code on your own computer (needs PHP, Composer and Node.js):

   ```
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```

2. Upload the whole project (including `vendor/` and `public/build/`) to the
   server and point the document root to `public/`.
3. Create an empty MySQL or MariaDB database and a user for it.
4. Open the site in a browser. You are redirected to `/install`. The wizard
   checks the environment, asks for the database connection, creates the
   administrator account and locks itself when it finishes. The installer
   creates `.env` and the application key on the first request.
5. Sign in to the panel at `/panel` and finish the setup wizard (language,
   currency, shop profile, VAT mode, e-mail).

On a host without a symlink for `public/storage`, the installer copies the
files instead of linking them.

## Background tasks

Scheduled work (e-mail, payment reconciliation, retention) runs in one of two
modes set by `SCHEDULER_MODE` in `.env`:

- `traffic` (default): tasks run after web requests, like WP-Cron. Needs no
  setup but is not punctual on a shop without visitors.
- `system`: add a cron job that runs every minute:

  ```
  * * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1
  ```

The panel shows the scheduler state under Settings, "Background tasks".

## Backup and recovery

Create a backup of the database and uploaded files (also from cron):

```
php artisan backup:create --keep=7
php artisan backup:verify storage/app/backups/<archive>.zip
```

The result is one ZIP plus a `.sha256` file in `storage/app/backups`. By
default it does not include `.env`; add `--only=database,public,private,env`
if you want it. Without the same `APP_KEY` encrypted data cannot be read.

Restoring is manual and never overwrites a running shop:

1. Import `database.sql` from the archive into an empty database with the
   MySQL client.
2. Unpack `files/public` and `files/private` into `storage/app/public` and
   `storage/app/private`.
3. Restore `.env` (or recreate it with the same `APP_KEY`).

Copy the archives off the server. The tool does not encrypt them or upload
them anywhere.

## Updates

Updating from the panel is not available yet. For now update by uploading the
new version over the old one, after taking a backup and putting the shop into
maintenance mode from the panel. Do not skip the backup: failed migrations are
not rolled back automatically.

## Extending

Plugins and themes are ZIP packages with a `manifest.json`, installed from the
panel. A plugin is trusted PHP code that runs with the permissions of the
application, not a sandbox: install only code you trust. Modules and plugins
extend the shop through the classes in each module's `Contracts` directory.

## Contributing and security

See `CONTRIBUTING.md` and `SECURITY.md`.

## License

MIT, see `LICENSE`. Third-party licenses are listed in `NOTICE`.
