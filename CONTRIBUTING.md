# Contributing

Thank you for helping. Litecommerce is a modular monolith on Laravel; code
lives in `modules/<Name>`, and modules talk to each other only through the
classes in their `Contracts` directory (enforced by `tests/Architecture`).

## Developer Certificate of Origin

Every commit must be signed off to certify the Developer Certificate of Origin
(https://developercertificate.org). Add the sign-off with `git commit -s`; it
appends a `Signed-off-by: Your Name <you@example.com>` line.

By signing off you confirm that you wrote the contribution or have the right
to submit it, and that it may be distributed under the MIT License of this
project.

## Working on the code

```
composer install
npm ci && npm run build
php artisan serve
```

Open `/install` and finish the installer. Before opening a pull request run:

```
composer lint        # code style (Pint)
composer analyse     # static analysis (PHPStan)
composer test        # PHPUnit
```

Guidelines:

- Money and quantities never use floating point.
- Keep classes small and single-purpose; no comment blocks (one or two
  English sentences at most, only where the reason is not obvious).
- A change to behavior needs a test of that behavior, not only the happy path.
- A database change edits the existing migration until the first release is
  tagged; update seeders and demo data in the same pull request.

Security problems: see `SECURITY.md`, not a public issue.
