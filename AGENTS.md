# DeploySift contributor guidance

Work from this Laravel 13 project root. Use the system-installed `php`, `composer`, `node`, and `npm` in this Windows workspace.

- Findings must be reproducible and retain stored evidence IDs.
- Never execute uploaded files or claim unseen hosting configuration.
- Keep secrets server-side; validate every AI claim against evidence.
- Preserve workspace isolation when adding authentication.

Verify locally:

```powershell
php artisan migrate:fresh --seed
php artisan route:list --path=api
```

`migrate:fresh` deletes tables in the local `stackora` database. The product pipeline is evidence ingestion → extraction → deterministic diagnostics → correlation → report. Keep AI adapters separate from `App\Services\DiagnosticEngine`.
