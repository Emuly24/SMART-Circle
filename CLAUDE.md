# CLAUDE.md — SMART Circle v2 project rules

These rules apply to every change, by humans or AI assistants.

## 1. What SMART Circle is

A free, **replay-based** support programme for Form 1–4 students in Malawi (by default **24**: 3 girls and
3 boys per form; the admin can raise the seats per form and gender) in **Mathematics, Physics, Chemistry, Biology and English grammar**.
Students request topics their school has covered; the tutor records lessons on an iPad board;
every lesson answers "why does this matter / where is it used in real life?".
Admitted students get everything; the public gets free PDF handouts from each lesson.

- Forms 1–2: all five subjects. Forms 3–4 science track (Physics **and** Chemistry): all five.
  Forms 3–4 humanities track: Mathematics, Biology, English grammar.
- Day scholars, boarders, self-boarders and evening-class students are all welcome.
- **Guardians** need not be present when a student applies. After admission the tutor sends a ready-made message in
  the guardian's language (`GuardianMessages`: Chichewa or English); the guardian confirms with their `/g/{token}` link
  (or the tutor records a call). It never blocks the student.
- **Encouraging marking:** answers are "Got it", "Partly there" or "Not yet" (never a red cross), with what went
  well, where it went off track and the next step. Students may ask for a re-check within 7 days; keep every decision.
- No live streaming. No attendance or discipline features.
- **Honesty rule:** a topic, its lessons, requests and (later) exercises open to a student only when
  their own school has taught it (`AccessService`). Never bypass it; early access is a tutor decision.
- **Roles:** `tutor` (the admin), `team` (volunteer tutors: only their own subjects, enforced by `SubjectGuard`;
  they agree to `TeamAgreement` first), `student`, `partner` (school teachers), `alumni`. Pages and menus follow the
  signed-in role. Practice accounts (`users.is_practice`) exist only for the admin's "Try as" test mode.
- Brand: logo, colours and typography in `brand/README.md` — use them everywhere.

## 2. Layout and layers

```
public/            Web root: index.php (front controller), assets/ (css, js, fonts, brand)
src/Presentation/  HTTP in, HTML/JSON out: App, Http/, Middleware/, Controllers/, View/templates/
src/Application/   Use-cases and rules, grouped by domain (Auth, Install, ...)
src/Data/          Database, session handler, migrator, Repositories/
src/Support/       Cross-cutting helpers (Config, Logger, PhoneNumber, e())
config/            app.php (defaults), bootstrap.php (autoload + config)
database/migrations/  NNNN_name.sql, applied in order by the web installer / admin page
storage/           logs, uploads, cache, sessions (pre-install) — never web-reachable
tests/             PHPUnit (dev only, never deployed)
brand/             Logo sources, PNG exports, brand guide
```

The root `.htaccess` sends every request into `public/`; nothing else is reachable.

| Layer | May use | Must NOT |
|---|---|---|
| Presentation | Application, Support | Run SQL; contain business rules; print unescaped data |
| Application | Data (repositories), Support | Read `$_GET`/`$_POST`/`$_SESSION`/`$_SERVER`; output HTML |
| Data | Support | Contain business rules; build SQL from unbound values; output anything |

Only `Request::fromGlobals()` reads superglobals; only `Session` touches `$_SESSION`.

## 3. Coding standards

- `declare(strict_types=1);` in every PHP file except templates. PSR-12. PSR-4 (`SmartCircle\` → `src/`).
- Type every parameter, return and property; prefer `final` classes and `readonly` properties.
- **Database:** PDO through `SmartCircle\Data\Database`; prepared statements for every value. No mysqli.
- **Output:** every value in templates goes through `e()`. No inline `<script>` or `style=""`
  (the Content-Security-Policy blocks them): put CSS in `public/assets/css`, JS in `public/assets/js`.
- **Forms:** every POST route has the `VerifyCsrf` middleware and the form prints `$csrf->field()`.
  Never change data on GET.
- **Access:** protect routes with `RequireRole` (any login, or specific roles).
- **Errors:** never show stack traces to users; `Logger` writes to `storage/logs`.
- **Secrets:** only in `/secrets.php` on the server (template: `secrets.example.php`). Never committed.
- **Assets:** self-hosted only (no CDNs, no external APIs). Link with `$asset('path')` for cache-busting.
- **Screens and themes:** every page must work from 320 px phones to wide desktops with no sideways scrolling,
  and in all themes (Light, Eye comfort, Dark, Auto). Use the colour roles in `app.css` (`--surface`, `--ink`,
  `--link`, `--on-brand`, `--btn-blue`, …), never raw hex colours in components. Lesson boards and printed
  letters stay light (`data-theme-fixed`).
- Phone numbers are stored as `+265XXXXXXXXX` (`PhoneNumber::normalize`).

## 4. InfinityFree constraints

| Constraint | Rule |
|---|---|
| No `mail()` | Email goes out through Zoho SMTP (smartcircle@zohomail.com) from a queue. Replies arrive in Zoho, not the site. |
| No cron | Scheduled work runs lazily on normal requests or from an admin button. |
| No shell / long scripts | No `exec`/`shell_exec`/`proc_open`, no `sleep()`, keep requests short. |
| ~50,000 hits/day | Avoid polling; replays and static files only. |
| 10 MB per file, ~30,000 files | Lessons upload in 1 MB pieces and are stored as ≤ 9 MB part files (`LessonStorage`); unfinished uploads are cleaned after 7 days. |
| Few DB connections | One PDO connection per request (`Database` is created once in `App`). |
| No env-var panel | Settings live in `secrets.php`, uploaded once. |

## 5. Workflow

- Work on a branch (`v2/...`); never commit to `main` directly.
- Before committing: `composer lint` and `composer test` (PHPUnit), plus `tools/e2e.sh`,
  `tools/e2e-lessons.sh`, `tools/e2e-admissions.sh`,
  `tools/e2e-topics.sh`, `tools/e2e-partners.sh`, `tools/e2e-honesty.sh`, `tools/e2e-assessments.sh`, `tools/e2e-notes.sh`, `tools/e2e-grades.sh`, `tools/e2e-support.sh`, `tools/e2e-maneb.sh`, `tools/e2e-team.sh`, `tools/e2e-guardians.sh` and `tools/e2e-update.sh` (destructive: test databases only) when a database is available (ideally also against
  Apache, which honours the .htaccess files).
- After adding a migration, run `php tools/build-schema.php` to refresh `database/schema.sql`
  (a unit test fails if it is out of date). See `docs/database.md`.
- New tables arrive as migrations; on the live site the tutor applies them with the dashboard's "Update database" button.
- Schema changes: a new numbered file in `database/migrations/` — never edit an applied migration.
- Keep each change focused; describe it clearly in the commit message.
