# DeploySift — Production Deployment

Production runbook for DeploySift. For product overview and setup, see the [main README](../README.md).

### 1. Server prerequisites

- Windows Server (or Windows 10/11) with WAMP/XAMPP, or any host with PHP 8.3, Apache 2.4, and MySQL 8.
- Outbound HTTPS for Meta Muse calls (or run with AI disabled — deterministic diagnostics work offline).

### 2. Apache virtual host configuration

```apache
<VirtualHost *:80>
    ServerName deploysift.example.com
    DocumentRoot "C:/wamp64/www/DeploySift/public"
    <Directory "C:/wamp64/www/DeploySift/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Replace `deploysift.example.com` and the paths with your own. For HTTPS, terminate TLS at Apache (see §11) and keep this vhost as a redirect.

### 3. Laravel public directory configuration

The document root MUST be the `public/` directory, never the project root. Verify with a request to `/up` (health) and a direct asset URL; both must resolve. The diagnostic engine flags document roots that do not end in `public/`.

### 4. PHP extensions

Required: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `zip`, `sodium`. With WAMP PHP, confirm with `php -m` and enable the CA bundle for TLS:

```ini
curl.cainfo = "C:\wamp64\bin\php\php8.3.28\extras\ssl\cacert.pem"
openssl.cafile = "C:\wamp64\bin\php\php8.3.28\extras\ssl\cacert.pem"
```

### 5. Environment configuration

Copy `.env.example` to `.env` and set `APP_KEY` (`php artisan key:generate`), `APP_URL`, database credentials, mail driver, and (optionally) `MUSE_API_KEY`, `MUSE_API_ENDPOINT`, `MUSE_MODEL`, plus `GOOGLE_*`/`GITHUB_*` OAuth credentials. Never commit `.env`; never expose keys to the frontend.

### 6. Database setup

Create the database and user with least privilege, then migrate and seed:

```powershell
php artisan migrate:fresh --seed
```

`migrate:fresh` drops all tables — use plain `php artisan migrate` for upgrades on live data.

### 7. Storage permissions

Ensure `storage/` and `bootstrap/cache/` are writable by the web-server user:

```powershell
icacls storage /grant "IIS_IUSRS:(OI)(CI)F"  # or the Apache service account
icacls bootstrap\cache /grant "IIS_IUSRS:(OI)(CI)F"
```

DeploySift only reads and writes these directories; it never changes permissions itself.

### 8. Queue worker setup

`QUEUE_CONNECTION=database` by default. Expensive diagnostics dispatch to `ProcessInvestigation`; run at least one worker:

```powershell
php artisan queue:work --tries=3
```

### 9. Supervisor configuration

On Windows, run the worker as a service (NSSM) or scheduled task with restart-on-failure:

```powershell
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Restart the worker on every deploy (`php artisan queue:restart`).

### 10. Scheduler configuration

Add a minutely task for `schedule:run`:

```powershell
* * * * * php C:\wamp64\www\DeploySift\artisan schedule:run >> NUL 2>&1
```

(Use Task Scheduler on Windows with a 1-minute trigger.)

### 11. HTTPS setup

Terminate TLS at Apache with a Let's Encrypt (or equivalent) certificate and redirect port 80 to 443. Set `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, and `SESSION_DOMAIN` to your host.

### 12. Private file storage

Evidence uses the `local` disk under `storage/app/private/` and is never linked to `public/`. Do not run `storage:link` for evidence, and do not serve `storage/` from Apache.

### 13. Backup and recovery

- Nightly MySQL dump plus `storage/app/` (evidence files). Test restores quarterly.
- Reports regenerate deterministically from stored evidence (`GET /api/investigations/{id}/report`), so the database plus evidence files is a complete backup.

### 14. Logging and monitoring

`LOG_CHANNEL=stack` writes `storage/logs/laravel.log`. Ship it to your log aggregator and alert on `failed` `ai_analysis_runs` rows and repeated 419/422 upload rejections, which indicate client or integration issues.

### 15. Troubleshooting

- `could not find driver`: the serving PHP lacks `pdo_mysql` — serve with the WAMP PHP that has it.
- `Route [login] not defined` on `/api/*`: session middleware missing from the `api` group — ensure `bootstrap/app.php` keeps the session/CSRF prepend.
- 419 on uploads: missing/rotated `X-XSRF-TOKEN` — refresh the token after login/register.
- cURL error 60 to `api.meta.ai`: missing CA bundle — see §4.
- `invalid` AI runs: the model response failed evidence validation; deterministic findings stand — inspect the run's `output.raw`.

### 16. Known limitations

- Hosting screenshots are interpreted by Meta Muse when configured; without it the engine records a placeholder and makes no claim about unseen values.
- Document-root detection trusts hosting-panel text as observed, not verified, and says so in each finding.
- Contradictory-evidence detection is explicit absence reporting, not automated refutation.
- SAM 3.1 segmentation and voice transcription are not integrated (optional per spec).

### 17. Security considerations

- Session auth with CSRF on all state-changing API routes; workspace policies on every investigation, evidence, and report endpoint; cross-user access returns 403.
- Uploads: 10 MB limit, extension allowlist, ZIP traversal/executable caps, secrets redacted before storage and before any AI call, private storage only, never executed.
- AI: `store: false` on Responses calls, redacted inputs persisted for audit, strict evidence-ID validation with rejection of ungrounded output.
- Keep `APP_DEBUG=false` in production and review `storage/logs` permissions.
