# CI/CD

## CI (`.github/workflows/ci.yml`)
Runs on every push to `main`/`master` and on pull requests: Composer install, Vite build, `php artisan test`
(isolated SQLite `:memory:`), the frontend test, `composer audit` and `npm audit`. No secrets or real database are used.

## Deploy (`.github/workflows/deploy.yml`)
Runs after CI succeeds on `main` (or manually), over SSH, in the GitHub `production` environment
(enable "Required reviewers" there to gate releases). It runs `scripts/deploy.sh` on the server, then checks `/up`.

### One-time setup
1. **Server remote:** `/srv/pawid/current` currently has a local path as `origin`. Point it at GitHub with a
   read-only deploy key: `git remote set-url origin git@github.com:Laoag-City/pet-registration-and-monitor.git`.
2. **SSH key for Actions:** `ssh-keygen -t ed25519 -f deploy_key -N ''`; add `deploy_key.pub` to
   `~eihcek/.ssh/authorized_keys` (ideally restricted to a dedicated deploy user) and keep the private key out of the repo.
3. **Repository secrets** (Settings > Environments > production): `DEPLOY_SSH_KEY` (private key), `DEPLOY_HOST`,
   `DEPLOY_USER`, `DEPLOY_KNOWN_HOSTS` (output of `ssh-keyscan -H <host>`, verified against the server's fingerprint).
   Optional variables: `DEPLOY_PATH`, `DEPLOY_URL`.
4. **Sudoers** (`visudo -f /etc/sudoers.d/pawid-deploy`), limited to exactly these commands:
   ```
   eihcek ALL=(root) NOPASSWD: /usr/bin/chown -R www-data\:www-data storage bootstrap/cache, /usr/bin/systemctl reload php8.4-fpm
   ```
   Sudoers matches the arguments literally (relative paths), so the script must run `sudo -n chown` from the app directory, as it does.
5. Run `/usr/local/sbin/pawid-backup.sh` before releases that add migrations.

Rollback: code can be reverted with `git revert` and redeploying; migrations are forward-only, so restore data from a verified backup into a new database rather than rolling back.
