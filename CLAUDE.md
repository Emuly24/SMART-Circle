# CLAUDE.md — SMART Circle Project Rules

These rules apply to every change in this repository, by humans or AI assistants.
Background and evidence: `docs/audit.md`.

## 1. What this project is

- SMART Circle: a tutoring platform for Form 3/4 students (notes, quizzes, exams, assignments, library, admin tools).
- **PHP 8.1+**, MariaDB/MySQL, server-rendered HTML, Apache `.htaccess`.
- Hosted on **InfinityFree** (free shared hosting). Deployed by FTP from GitHub Actions (`.github/workflows/deploy.yml`).
- The code is mid-migration from "one PHP file per page" (repo root, `admin_*.php`) to a layered structure under `src/`.
  **All new code goes into the layered structure. Legacy pages are migrated one at a time.**

## 2. Architecture: three layers

Dependencies point **downward only**: Presentation → Application → Data. `Support/` may be used by any layer.

```
public/                     Web root (target). Front controller, assets. Nothing else is web-reachable.
src/
├── Presentation/           LAYER 1 — HTTP in, HTML/JSON out
│   ├── Http/               Router, Request, Response, Middleware (Auth, AdminAuth, Csrf, Approved, Consent)
│   ├── Controllers/        Thin: read input → call a service → render a view
│   └── View/               Templates + View::render() with escaping helper e()
├── Application/            LAYER 2 — use-cases & business rules
│   └── <Domain>/           e.g. Auth, Enrollment, Content, Assessment, Library, Social, Notification, Scheduling, Upload
├── Data/                   LAYER 3 — persistence
│   ├── Database.php        The single PDO connection
│   ├── Repositories/       One per aggregate; prepared statements only
│   └── Entities/           Plain data objects
└── Support/                Cross-cutting: Env, Csrf, Logger, InputValidator
config/                     bootstrap.php (autoload + env), routes.php
database/                   schema.sql, migrations/NNNN_description.sql, seeds/
storage/                    uploads/, logs/, cache/ — never web-reachable
tools/                      Dev-only scripts (schema export, diagnostics) — never deployed
tests/                      Unit/ and Integration/
docs/                       Audit, decisions, runbooks
```

### Layer rules

| Layer | May use | Must NOT |
|---|---|---|
| **Presentation** | Application, Support | Run SQL, call `getDB()`/`getPDO()`, contain business rules, echo unescaped data |
| **Application** | Data (repositories), Support | Read `$_GET`/`$_POST`/`$_SESSION`/`$_FILES` directly, output HTML, run raw SQL |
| **Data** | Support | Contain business rules, build SQL from unbound values, start sessions, output anything |

- Controllers pass plain arrays/value objects to services; services return data or throw domain exceptions.
- One service method = one use-case (e.g. `CommentService::post()`), easy to test without HTTP.
- Legacy URLs keep working: a migrated `foo.php` becomes a thin shim that calls its controller.

## 3. Coding standards

- **`declare(strict_types=1);`** as the first statement of every new or touched PHP file (except pure templates).
- **PSR-12** formatting; **PSR-4** autoloading under the `SmartCircle\` namespace → `src/`.
- Type every parameter, return value and property. Prefer `final` classes and `readonly` properties where it fits.
- Names: classes `PascalCase`, methods/variables `camelCase`, constants `UPPER_SNAKE`, DB tables/columns `snake_case`.
- One class per file; file name = class name.
- **Database:** PDO only (`SmartCircle\Data\Database`). **Prepared statements for every query with a variable — no exceptions.**
  Do not add new `mysqli`/`getDB()` usage; migrate it when you touch a file.
- **Output:** escape everything with `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')` (or `e()`). Use `json_encode(..., JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)` when embedding data in JS.
  Rich-text note HTML must go through an allow-list sanitiser before rendering.
- **Forms:** every state-changing request is **POST** and validates `Csrf` (`SmartCircle\Support\Csrf::hiddenField()` / `validate()`). Never change state on GET.
- **Auth:** check access in middleware or at the top of the controller, before any work. Admin pages use session-based admin auth (HTTP Basic Auth is legacy and being removed).
- **Uploads:** extension allow-list + MIME sniff (`finfo`) + size limit + random file name; store under `storage/uploads/`; serve through a controller that checks ownership.
- **Errors:** never show stack traces or SQL errors to users. Log via `error_log()`/`ErrorLogger`; show `views/errors/generic.php`.
- **Secrets:** never commit credentials. Read them from `.env` / `secrets.php` (see `secrets.example.php`). No hard-coded passwords, hashes or tokens in code or workflows.
- No new dependencies that need Composer at runtime unless they are committed/vendored; the host has no shell.

### Sessions (fixed in `fix/session-unification`)

- **`config.php` is the only place that starts a session.** It registers the database session handler (table `sessions`) and calls `session_start()`.
- Every page must `require_once __DIR__ . '/config.php';` **before** reading or writing `$_SESSION`.
- **Never** call `session_save_path()` or `session_start()` in a page, include or controller.
- Logout must go through `logout.php` (clears `$_SESSION`, expires the cookie, `session_destroy()` deletes the DB row).
- Required table:
  ```sql
  CREATE TABLE IF NOT EXISTS sessions (
      id            VARCHAR(128) NOT NULL PRIMARY KEY,
      data          MEDIUMTEXT   NOT NULL,
      last_accessed DATETIME     NOT NULL,
      INDEX idx_sessions_last_accessed (last_accessed)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ```

## 4. InfinityFree hosting constraints

Design for these limits; do not write code that depends on anything blocked here.

| Constraint | Rule |
|---|---|
| **No `mail()`** | Do not use `mail()`. Notifications are in-app (`admin_messages`). If email is required, use PHPMailer over external SMTP / an HTTPS mail API, behind a `Mailer` interface, and confirm outbound access on the plan first. |
| **No cron jobs** | Do not rely on cron or external pingers (InfinityFree's browser check blocks non-browser clients like `curl`). Use a **lazy scheduler**: a `scheduled_jobs` table with `last_run_at`, checked at most every N minutes during normal requests, plus an admin "Run now" button. |
| **No `sleep()`/`usleep()`, no long-running scripts** | Requests must finish fast. No polling loops, no background workers, no `set_time_limit()` tricks. |
| **No shell/process functions** | No `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`. |
| **Limited outbound connections** | Avoid server-side HTTP calls to third parties; prefer client-side CDNs for front-end libraries (pinned exact versions). |
| **Low DB connection limit** | One connection per request (PDO singleton). Do not open a second `mysqli` connection in new code. |
| **No environment-variable panel** | Configuration lives in a file uploaded once by FTP (`.env` or `secrets.php`), never in Git and never overwritten by deploys. |
| **HTTP Basic Auth unreliable** (PHP via FastCGI) | Do not add new Basic-Auth pages; use session-based login. |
| **Upload limit ≈ 10 MB per file** | Validate size server-side and show a clear error. |
| **Web root is `htdocs/`** | Keep non-public code outside the web root, or protect it with `Require all denied` in `.htaccess`. Do not deploy `tools/`, debug scripts or `docs/`. |
| **Shared resources / hit limits** | Cache expensive queries, avoid `ORDER BY RAND()` on large tables, paginate lists. |

## 5. Workflow rules

- Never commit directly to `main`. Work on a feature branch (`fix/...`, `feat/...`, `refactor/...`, `audit/...`).
- Keep each change focused; do not mix refactors with behaviour changes.
- Before committing: `php -l` on every changed PHP file (and PHPStan/tests once they exist).
- Schema changes go in `database/migrations/` as numbered, idempotent SQL files. Never change the live DB by hand without a migration.
- Update `docs/` when a decision changes the architecture or a hosting assumption.
