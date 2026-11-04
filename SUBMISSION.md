# DeploySift — Hackathon Submission

**Every clue. One diagnosis.** DeploySift is an AI Deployment Intelligence platform that diagnoses Laravel/PHP deployment failures by correlating project files, hosting screenshots, application logs, and browser errors — without SSH or server administrator access. Evidence-based AI investigation for deployment failures, built for developers troubleshooting on shared hosting, Plesk, and managed environments.

## Problem statement

"Your application works locally. Why is production broken?" Deployment failures are diagnosed today by SSH-ing into servers and eyeballing logs. That breaks down on shared hosting, managed platforms, and client infrastructure where developers have no shell access — leaving teams to guess between PHP versions, extensions, environment files, build artifacts, and server configuration.

## Innovation

Evidence-based, correlation-first diagnosis instead of generic AI chat:

- Every conclusion links to stored evidence IDs; findings carry expected vs observed values, the triggering rule, and a qualitative confidence methodology.
- Deterministic parsers run first and form the source of truth; Meta Muse reasons over the same evidence package and its output is validated ID-by-ID — ungrounded assertions are rejected, never shown.
- Secrets are redacted before storage and before any AI call; nothing uploaded is ever executed.

## Technical architecture

- **Backend:** Laravel 13 REST API, MySQL, database queue. Workspace-scoped auth (`owner`/`member`/`viewer` policies) on every investigation, evidence, and report endpoint; session auth with CSRF; login throttling (10/min) plus 60/min API rate limiting.
- **Pipeline:** evidence ingestion → deterministic extraction → diagnostics (`DiagnosticEngine`, 10 rule families) → correlation (`evidence_relationships`) → report. Expensive runs dispatch to `ProcessInvestigation` when the queue driver is async, inline under `sync`.
- **Frontend:** server-rendered Blade workspace (dashboard, investigations, reports, evidence explorer, AI analysis), drag-and-drop upload queue with per-file progress, validation, and retry; printable report views.
- **AI layer:** `App\Services\MuseAdapter` (kept separate from `DiagnosticEngine`), Meta Responses API (`POST /v1/responses`, `muse-wonder-1.0`, `store: false`, low reasoning effort), strict-JSON insights with a fixed confidence scale, image evidence attached as base64 `input_image` parts, token usage persisted per run.

## Meta Muse integration

Verified live against `https://api.meta.ai`: text reasoning over redacted evidence packages, screenshot interpretation where attached, structured cross-evidence analysis, timeouts (60 s), retries, redacted run logging, and evidence-ID validation with rejection of ungrounded output. Without credentials the API honestly reports `not_configured` and records a `skipped` run — nothing is fabricated.

## Setup instructions

Requires PHP 8.3 (`pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `zip`, `sodium`), MySQL, Node 18+, Composer:

```powershell
composer install
Copy-Item .env.example .env   # then set DB credentials, APP_KEY via php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve              # http://127.0.0.1:8000
npm install; npm run test:e2e  # Playwright end-to-end suite
```

Optional AI: set `MUSE_API_KEY`, `MUSE_API_ENDPOINT=https://api.meta.ai`, `MUSE_MODEL=muse-wonder-1.0`. WAMP users: add `C:\wamp64\bin\php\php8.3.28` to `PATH` and point `curl.cainfo`/`openssl.cafile` at `extras\ssl\cacert.pem` for outbound TLS.

## Demo scenario instructions

1. Register (or sign in) — a personal workspace is provisioned automatically.
2. Click **Demo: PHP 8.3 vs 8.1** (or Database / Vite failure) for a one-click synthetic investigation processed through the real pipeline.
3. Or create an investigation, open it, and drag files into **Add evidence** (category auto-suggests; ZIPs unpacked with traversal/executable protection).
4. **Run diagnostics** → open findings; click any finding to jump to its linked evidence (★ linked).
5. Open **Reports** for remediation actions, verification checklist, JSON download, or browser print/PDF.
6. Open **AI Analysis** and run analysis for validated Muse insights with evidence references.

## Known limitations

- Hosting screenshots are interpreted by Meta Muse when configured; otherwise a placeholder is recorded and no claim is made about unseen values.
- Document-root detection trusts panel text as observed, not verified, and says so.
- Contradiction handling is explicit absence-reporting, not automated refutation.
- SAM 3.1 segmentation and voice transcription are not integrated.
- Anonymous API flood gets cheap 401s before auth; per-user (60/min) and login (10/min) throttles are enforced.

## Screenshots

Captured headless against the running app during the release audit (`demo/screenshots/`):

- `dashboard.png` — metrics dashboard.
- `evidence.png` — evidence explorer with linked-evidence markers.

## 90-second demo video script

> "Your application works locally. Why is production broken?" [0:00–0:05, title card]

1. [0:05–0:20] One click launches the **PHP 8.3 vs 8.1 demo** — synthetic composer.json, hosting panel, logs, and browser errors flow through the real pipeline.
2. [0:20–0:35] Drag a log file into **Add evidence** — progress bar, redaction, extraction.
3. [0:35–0:50] **AI Analysis**: validated Muse insights, each linked to evidence IDs.
4. [0:50–1:05] Click a finding → jump to its ★ linked evidence (correlation).
5. [1:05–1:20] Report view: diagnosis with supporting evidence, recommended remediation, verification checklist; download JSON.
6. [1:20–1:30] "Every clue. One diagnosis." closing card.

Record at 1080p in Chromium against a seeded local instance; narrate the evidence IDs as they appear. (No recording was produced in this environment — use the script above with any screen recorder.)
