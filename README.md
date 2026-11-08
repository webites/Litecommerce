# Litecommerce

Open-source, self-hosted e-commerce for small and mid-sized shops, built as a
modular monolith on Laravel. One installation runs one shop. It is designed to
work on ordinary shared hosting: no Node.js, SSH, Composer or permanently
running workers are needed on the server where the shop runs.

Languages: the panel and storefront are available in Polish and English.

## Status

Early release (0.x). The first public version is `0.1.0`. Interfaces and the
database schema may still change between 0.x versions. Every release is
published on the [Releases page](https://github.com/webites/Litecommerce/releases)
as a ready-to-upload ZIP, signed so the panel can verify updates.

## Requirements

- PHP 8.3 or 8.4 with the extensions `ctype, curl, dom, fileinfo, filter,
  hash, mbstring, openssl, pcre, pdo, pdo_mysql, session, tokenizer, xml,
  xmlwriter, bcmath, intl, gd, zip` (`opcache` is recommended)
- MySQL 8.0+ or MariaDB 10.11+
- `memory_limit` of at least 128 MB (256 MB recommended)
- Write access to `storage/` and `bootstrap/cache/`
- A web server whose document root is the `public/` directory

## Install

### Shared hosting (from the release ZIP)

No Composer, Node.js or SSH is needed.

1. Download `litecommerce-<version>.zip` from the Releases page. Optionally
   check it against the `.sha256` file published next to it.
2. Create an empty MySQL or MariaDB database and a user for it.
3. Unpack the ZIP into a folder on the server and point the document root of
   your domain to the `public/` folder inside it. Your hosting panel usually
   lets you choose the document root for a domain or subdomain. A host that
   cannot point a domain to a subfolder is not supported yet.
4. Open the site in a browser. You are redirected to `/install`. The wizard
   checks the environment, asks for the database connection, creates the
   administrator account and locks itself when it finishes. The installer
   creates `.env` and the application key on the first request.
   Do this right after uploading: until the wizard is finished, anyone who
   opens the address first can install the shop.
5. Sign in to the panel at `/panel` and finish the setup wizard (language,
   currency, shop profile, VAT mode, e-mail).
6. Optional but recommended: add the cron job from "Background tasks" below.

On a host without a symlink for `public/storage`, the installer copies the
files instead of linking them.

### VPS or your own server

Use the same ZIP. The web server must serve `public/` and send everything that
is not an existing file to `index.php`. Example for nginx with PHP-FPM:

```
server {
    server_name shop.example.com;
    root /var/www/litecommerce/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

The PHP user needs write access to `storage/`, `bootstrap/cache/` and, for
updates from the panel, to the application folders listed in
`config/updates.php` (`writable_paths`). Do not run the web server as root.

### From source (developers)

Needs PHP, Composer and Node.js on your own computer:

```
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Upload the whole project (including `vendor/` and `public/build/`) and continue
from step 3 above.

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

### From the panel

Settings, "Updates" checks GitHub once a day (this can be switched off) for a
newer stable release. When one is available and fits your server, "Update"
runs these steps and shows the progress:

1. checks the release and your server (PHP version, extensions, write access,
   free space),
2. puts the shop into maintenance mode,
3. creates a backup of the database and of every file it is going to replace,
4. downloads the ZIP and verifies its checksum and its signature against the
   key built into the shop,
5. replaces the files of the release (your `.env`, `storage/`, uploads, and
   the files of plugins and themes you installed are not touched),
6. runs the database migrations and clears caches,
7. checks every file against the signed list and leaves maintenance mode.

Keep the browser tab open while it runs. If you close it, return to the
"Updates" tab and the update continues from where it stopped.

If a step fails before the migrations, the files are restored automatically and
the shop keeps running the old version. If something fails after the
migrations have started, the shop stays in maintenance mode and the panel shows
a report. In that case restore the backup (see "Backup and recovery") or ask
for help; the file backup of that update is in
`storage/app/backups/update-<id>/`.

Take your own backup and copy it off the server before every update.

### Manually

Only if you have SSH access or a developer. Download the new ZIP, take a
backup, turn on maintenance mode from the panel (Settings, "Maintenance"),
unpack the ZIP over the old files without touching `.env` and `storage/`,
delete the files that no longer exist in the new `manifest.json`, run
`php artisan migrate --force` and `php artisan optimize:clear`, then turn
maintenance mode off. The panel update does all of this for you.

## Extending

Plugins and themes are ZIP packages with a `manifest.json`, installed from the
panel. A plugin is trusted PHP code that runs with the permissions of the
application, not a sandbox: install only code you trust. Modules and plugins
extend the shop through the classes in each module's `Contracts` directory.

## Contributing and security

See `CONTRIBUTING.md` and `SECURITY.md`.

## License

MIT, see `LICENSE`. Third-party licenses are listed in `NOTICE`.
