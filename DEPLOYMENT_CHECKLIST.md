# PawID deployment checklist

Updated 6 November 2026. **NOT READY FOR DEPLOYMENT** until the production gates below are resolved. No deployment or database migration was performed during this cleanup.

## Release gates

- [ ] Change the three existing accounts identified by the private audit as using committed demo passwords (including Admin and Staff). Do this through authorized account management; do not reset all accounts or seed the database.
- [ ] Provision a production hostname, valid TLS certificate, current patched PHP runtime and least-privilege PostgreSQL account.
- [ ] Configure and verify scheduler execution, persistent uploads, monitoring and encrypted off-site backups.
- [ ] Complete desktop/mobile browser acceptance, chart/dialog interactions, console/network checks and a real phone QR scan using the production URL.
- [ ] Review two pre-existing missing photo references on archived pets; recover genuine files from a trusted backup if available. Do not invent replacements or remove records.
- [ ] Review ambiguous historical vaccination completion links without automatically merging or deleting doses.
- [ ] Commit/review all intended source changes, including untracked application/test files, before creating a release from Git. A Git archive of the current HEAD alone omits uncommitted work.

See [the cleanup report](docs/cleanup-2026-11-06.md) for baseline/final tests, exact deletions, checkpoint and known limitations.

## Runtime and dependencies

Use a patched, supported PHP release satisfying the committed Composer lock (PHP 8.2 or later). Local PHP 8.2.12 is an old patch level and must not be the production baseline. Required PHP capabilities include PDO/pdo_pgsql, ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, session, tokenizer, xml, xmlreader, xmlwriter, simplexml, gd, zip, zlib and iconv. Tests additionally require pdo_sqlite. Confirm CLI and web PHP load the necessary extensions.

Use PostgreSQL 18 with maintenance/security updates and matching PostgreSQL client tools. Use Composer 2 and Node 22.12+ for Vite 7; the local build uses Node 22.21.0. Install the exact committed dependency versions:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
npm ci
npm run build
composer audit
npm audit
```

Build in CI/staging and deploy the complete `public/build` manifest/assets. Do not deploy `public/hot`. Do not resolve new versions during a production release. Do not run `composer setup` on an existing installation: it generates an APP_KEY and runs migrations.

## Environment and keys

Use `.env.production.example` as a placeholder template. Keep the working local `.env` unchanged. Provision production values through protected files or the hosting secret manager:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-approved-host`.
- `APP_TIMEZONE=Asia/Manila` if the reminder should run at 08:00 Philippine time. Local configuration defaults to UTC; changing the production timezone does not rewrite historical timestamps.
- Preserve APP_KEY when moving an existing installation. Generate it only for a new installation; keep an encrypted recovery copy separately. QR reference decryption and encrypted application data depend on key availability. Do not print or commit it.
- `DB_CONNECTION=pgsql`, correct host/port/database and a dedicated app role. Use verified TLS (`DB_SSLMODE=verify-full` with an appropriate trusted CA and hostname) for remote PostgreSQL.
- Use database sessions/cache, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, host-only cookies and `SESSION_ENCRYPT=true`. Secure cookies require HTTPS.
- Logs must be private, rotated and monitored. Audit correction details and backup copies require access controls and retention policies.
- Current reminders are synchronous database notifications. Email delivery and password-reset email flows are not implemented; `MAIL_MAILER=log` does not deliver messages.

## Server and storage

Point Apache/Nginx document root only at Laravel's **`public/` directory**. Never expose the repository root, `.env`, `.git`, vendor source, bootstrap helpers, tests, logs or dumps over HTTP. A CLI-only guard on helpers is additional protection, not a substitute for the correct document root.

Enforce HTTP-to-HTTPS redirection at the server/proxy using the fixed approved host. Verify certificate renewal, forwarded HTTPS behavior and trusted proxy IPs. Do not blindly trust arbitrary proxy headers. Add appropriate `X-Content-Type-Options: nosniff` and `X-Frame-Options: SAMEORIGIN` headers. Enable HSTS after HTTPS is working.

Configure normal requests through `public/index.php`, prohibit directory listing, and prevent PHP/script execution under uploaded storage. The web account should write only to `storage/` and `bootstrap/cache/`, not application source. Avoid 777 permissions. Run:

```sh
php artisan storage:link
```

Only `storage/app/public` belongs behind that link. Private backups/documents must remain outside public access. Preserve uploaded photos between releases. Public pet photos and limited name-only public QR pages are existing privacy behavior; confirm it with the system owner.

The authenticated layout currently uses Bootstrap/icon CDN resources, and layouts use Google fonts. Verify these resources under the production network/CSP. Compilation alone does not prove browser rendering or CDN availability.

## QR acceptance

Generate/download QR codes while opening PawID at its approved, reachable production origin. The QR generator uses Laravel's current route URL, so correcting APP_URL alone does not repair QR images already printed with `127.0.0.1` or `localhost`. Replace those QR images. Test a new QR on a phone outside the server's local network. A local-network test requires a reachable server address and network configuration; this cleanup does not open firewall ports or expose the local server.

Public scans show owner and pet names only. Invalid/archived references show not-found; private details/downloads require authorization. Preserve the application key needed to decrypt existing references.

## Source versus production artifacts

All tests, test helpers, fixtures, factories, migrations and development dependencies stay in the source repository. `.gitattributes` marks tests, PHPUnit configuration, root QA scripts, diagnostic/backup helpers, the historical screenshot and audit documentation `export-ignore` for Git archive releases; it does not delete them. These rules only affect Git archives. Other CI copy/packaging methods must explicitly apply equivalent exclusions.

Exclude from the deployment package: `.git`, editor configuration, actual `.env` files/secrets, private backups, dumps, logs, sessions, temporary test output, QA scripts/tests, development-only helpers, `node_modules`, and `public/hot`. Install production vendor dependencies via Composer, or provide a verified vendor artifact built for the target platform. Do not blindly delete local vendor/node_modules. The generated `public/build` is ignored by Git but **must** be added to the release artifact after the build. Provision environment files and persistent storage separately.

Do not build a release solely from the current HEAD before untracked/uncommitted functional changes are reviewed and committed. Do not erase working changes with reset/clean.

## Testing and safe local caches

Run tests in the development repository or CI with development dependencies installed:

```sh
composer install --prefer-dist --no-interaction
php artisan config:clear
php artisan test --compact
node --test tests/frontend/repeat-submission.test.cjs
npm run build
```

`Tests\TestCase` refuses any connection other than SQLite `:memory:`. Never point tests at the actual PostgreSQL database. Some tests use migrations against that isolated in-memory database; no fresh/reset/wipe command is needed. The migration-free fixture runner remains available under `tests/`.

Use `config:clear`, `route:clear` and `view:clear` to restore local cache state after compilation verification. Avoid `optimize:clear`/`cache:clear` against the current installation when clearing database-backed application cache would violate the no-data-change requirement. Never clear sessions, queues, logs or uploads as cleanup.

## Backup, migrations and rollback

1. Back up PostgreSQL with custom-format `pg_dump`, uploaded files, source/release metadata and separately encrypted APP_KEY. Verify archive extraction and rehearse restoration into a new isolated database.
2. Keep encrypted off-site backups with an agreed daily/weekly/monthly retention policy and failure alerts. Store recovery keys separately. The local checkpoint/dumps are not an encrypted off-site backup solution.
3. Review migration status and SQL on the intended release/staging copy. The cleanup did not run migrations against the working PostgreSQL database.
4. During an approved release maintenance window, apply only reviewed pending additive migrations using a separate deployment role; the ordinary app role needs required DML/sequence privileges, not superuser or CREATEDB rights. Never run seeders against existing data automatically.
5. Never use migrate:fresh, migrate:reset, db:wipe, truncate or drop for deployment/cleanup. Historical migrations remain in the source tree.
6. The audit-table migrations intentionally reject destructive rollback. Prefer forward fixes. Code rollback must still respect soft-delete markers so removed records do not reappear.
7. Restore a database backup into a new target, verify it and authorize a connection cutover separately. Never overwrite the working database to test a backup.

Local CLI-only backup/rehearsal helpers remain in the source repository (`bootstrap/backup-before-audit.php` and `bootstrap/verify-backup-restore.php`). They use the installed Windows PostgreSQL 18 client tools. These helpers are excluded from Git archive release packages; configure host-specific production backup tooling separately.

The 6 November project checkpoint is outside the web root: `C:\Users\geocl\PawID-checkpoints\2026-11-06-precleanup\project.tar`. Its restored verification tree and manifest are beside it. It contains private environment files, uploads, logs, Git history and dependencies: keep it private. Use `project.tar`, not the abandoned incomplete `project.zip`. Restore selected source files from `restore-verification` to avoid overwriting newer work. For a full recovery extract into a new directory, restore the public storage junction using `storage:link`, and verify environment and tests before any cutover. The source checkpoint does not itself snapshot a running PostgreSQL server; existing database dumps were preserved separately.

## Scheduler and queues

`routes/console.php` schedules `pawid:care-reminders` daily at 08:00 in `config('app.timezone')` with overlap protection. Run the scheduler once every minute on one designated production instance:

```cron
* * * * * cd /srv/pawid/current && /usr/bin/php artisan schedule:run >> /var/log/pawid-scheduler.log 2>&1
```

Use real paths and log rotation. On Windows configure Task Scheduler with the proper PHP executable, `artisan schedule:run`, release working directory and one-minute recurrence. Monitor failures. Verify:

```sh
php artisan schedule:list
php artisan pawid:care-reminders --dry-run
```

The current code uses `notifyNow`; no queue worker is required for these database reminders. Reassess if queued jobs/email are introduced. Multiple servers must share cache locks or designate one scheduler.

## Release compilation and smoke checks

Under the target production environment:

```sh
php artisan route:list
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Reload PHP workers/opcache as appropriate. Verify `/up`, login/logout, owner isolation, direct staff/admin URL restrictions, uploads, QR, archive/restore, dashboards, required species/sex/barangay options, search and pagination. Use disposable staging records to test create/edit/Done/delete/restore; do not mutate real records for smoke testing.

Download all four XLSX reports, including empty datasets and formula-like text. Verify null next-due records remain in history but not schedules; linked completed events export once and legitimate repeated doses remain separate. Verify HTTPS cookie flags, `.env`/backup denial, external assets, charts, mobile navigation and browser console. Confirm real scheduler and backup logs after the first scheduled run.

## References

- [Laravel 12 deployment](https://laravel.com/docs/12.x/deployment)
- [PostgreSQL 18 pg_restore](https://www.postgresql.org/docs/18/app-pgrestore.html)
- [Symfony HTTP Foundation security advisory and patched releases](https://symfony.com/cve-2026-118514)
