# GitHub Actions — Deploy Workflows

## Active workflows

### `deploy.development.yml` — Auto-deploy to development.citycommerce.group

Triggers on:
- push to `master`
- push to `enhance/upstream-sync-2026-04` (current feature branch — remove this line once merged to master)
- manual `workflow_dispatch` (deploy any branch on demand)

What it does on the dev server:
1. `git fetch && git reset --hard origin/{branch}`
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan migrate --force` ← **runs new migrations**
5. `php artisan optimize:clear` + cache rebuild (config, route, view, event)
6. `php artisan queue:restart`
7. Writes `public/version.json` with the deployed commit
8. Smoke-tests the site responds + verifies version.json matches

### `deploy.production.yml` — FTP-deploy to tour.citycommerce.group on push to `main`
(Pre-existing, unchanged.)

### `tests.yml` — Laravel test suite on push to `main`/`dev` and PRs
(Pre-existing, unchanged.)

---

## Required GitHub secrets for the development deploy

Add via web UI (Settings → Secrets and variables → Actions → New repository secret) or via `gh` CLI:

```bash
# Replace the values with your real dev server connection details
gh secret set DEV_SSH_HOST       -R soudshoja/-city-tour-development -b "152.53.86.223"
gh secret set DEV_SSH_USER       -R soudshoja/-city-tour-development -b "resayili"        # or whatever user owns development.citycommerce.group
gh secret set DEV_SSH_PORT       -R soudshoja/-city-tour-development -b "22"
gh secret set DEV_DEPLOY_PATH    -R soudshoja/-city-tour-development -b "/home/resayili/development.citycommerce.group"
gh secret set DEV_MAINTENANCE_SECRET -R soudshoja/-city-tour-development -b "$(openssl rand -hex 16)"

# Private SSH key — pipe the file content (must be a key the dev server's authorized_keys accepts)
gh secret set DEV_SSH_KEY -R soudshoja/-city-tour-development < ~/.ssh/challenge_health_key
```

| Secret | Purpose | Example |
|---|---|---|
| `DEV_SSH_HOST` | Hostname or IP of the dev server | `152.53.86.223` |
| `DEV_SSH_USER` | Linux user owning the deploy path | `resayili` |
| `DEV_SSH_PORT` | SSH port (defaults to 22 if absent) | `22` |
| `DEV_SSH_KEY` | Private SSH key (full PEM body) | contents of `~/.ssh/challenge_health_key` |
| `DEV_DEPLOY_PATH` | Absolute path on server where the Laravel root lives | `/home/resayili/development.citycommerce.group` |
| `DEV_MAINTENANCE_SECRET` | Random token to bypass `php artisan down` for testing | random hex |

**Verify the secrets are set:**
```bash
gh secret list -R soudshoja/-city-tour-development
```

---

## First-time prerequisites on the dev server

The workflow assumes the dev server already has:
- A git checkout at `${DEV_DEPLOY_PATH}` with `origin` set to `git@github.com:soudshoja/-city-tour-development.git`
- The deploy-user's SSH key (the *public* half of `DEV_SSH_KEY`) added to `~/.ssh/authorized_keys`
- PHP 8.2+, Composer, Node 22+, npm available in the user's PATH
- Write permissions on `storage/`, `bootstrap/cache/`
- The `.env` file populated (the workflow does NOT touch `.env`)

If the git remote on the server uses HTTPS instead of SSH, add a deploy token or switch the remote to SSH.

---

## Manual deploy (when you want to push a specific branch on demand)

```bash
gh workflow run deploy.development.yml -R soudshoja/-city-tour-development -f branch=feature/whatever
```

Or via the web UI: Actions tab → "Deploy to development.citycommerce.group" → Run workflow → pick branch.

---

## Rollback

Re-run the workflow against the previous good commit:
```bash
gh workflow run deploy.development.yml -R soudshoja/-city-tour-development -f branch=master
```
…and have the dev server's master pointer at the older commit, OR push a revert commit and let the workflow auto-fire.
