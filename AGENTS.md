# DeploySift contributor guidance

Work from this Laravel 13 project root. Use this verified toolchain (WAMP PHP 8.3.28, Composer 2.11, system `node`/`npm`); do not use any `.tools/` path (removed).

Verified executables:

- PHP: `C:\wamp64\bin\php\php8.3.28\php.exe` (8.3.28, loads its own `php.ini`, has `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `zip`)
- Composer: `C:\ProgramData\ComposerSetup\bin\composer.bat` (uses whichever `php` is on PATH)
- New shells resolve bare `php` to WAMP via user PATH; in this automation shell always use the full WAMP path above. A bare `php` failing with "Access is denied" means an old shell predating the PATH fix or a broken WinGet shim — open a new terminal or use the full path.

- Findings must be reproducible and retain stored evidence IDs.
- Never execute uploaded files or claim unseen hosting configuration.
- Keep secrets server-side; validate every AI claim against evidence.
- Preserve workspace isolation when adding authentication.

Verify locally (WAMP PHP; start here if commands fail):

```powershell
C:\wamp64\bin\php\php8.3.28\php.exe -v            # expect 8.3.28
C:\wamp64\bin\php\php8.3.28\php.exe -m | Select-String '^pdo_mysql$'
C:\wamp64\bin\php\php8.3.28\php.exe artisan migrate:fresh --seed
C:\wamp64\bin\php\php8.3.28\php.exe artisan route:list --path=api
C:\wamp64\bin\php\php8.3.28\php.exe artisan test
C:\wamp64\bin\php\php8.3.28\php.exe artisan queue:work --once   # drain async jobs
```

Frontend and e2e use system tools: `npm install`, `npm run build`, `npm run test:e2e` (the suite boots its own WAMP-backed server).

`migrate:fresh` deletes tables in the local `stackora` database. The product pipeline is evidence ingestion → extraction → deterministic diagnostics → correlation → report. Keep AI adapters separate from `App\Services\DiagnosticEngine`.
