# DeploySift sample evidence

Synthetic deployment-failure evidence for the PHP version incompatibility demo.
All files are fictional and contain no real hosts, credentials, or personal data.

| File | What it proves |
|---|---|
| `composer.json` | Project requires PHP `^8.3` and Laravel `^12.0` |
| `hosting-panel.txt` | Hosting panel reports PHP 8.1.27, FPM handler, extension list, `public/` docroot |
| `laravel.log` | Runtime error: Composer platform check fails on PHP 8.1.27, with stack trace |
| `network.txt` | Browser login request returns HTTP 500 while static assets return 200 |

## How to use

1. Create an investigation in DeploySift.
2. Upload all four files (drag-and-drop accepts them together; or POST each to `/api/investigations/{id}/evidence` with its category: `project`, `hosting`, `logs`, `browser`).
3. Run diagnostics: expect `PHP_VERSION_MISMATCH` linked to all four sources.
4. Open the report for the correlated diagnosis, remediation steps, and verification checklist.

Uploading only `composer.json` is also instructive: DeploySift records the PHP requirement as a confirmed observation and lists the missing hosting evidence instead of claiming an incompatibility it cannot verify.
