# SMART Circle — Initial Diagnosis (Read-Only Audit)

- **Branch:** `audit/initial-diagnosis`
- **Base commit audited:** `7135bf0` ("feat: Implement user signup functionality with CSRF protection and input validation")
- **Date:** 2026-11-04
- **Scope:** Every tracked file (158 files, about 17,500 lines of PHP). No application file was changed. This report is the only file added.

> **How to read this.** Every finding gives a `file:line` reference. Severity uses **Critical / High / Medium / Low**. Counts come from `grep` over the tree, so treat them as approximate (± a few), but the cited lines were checked by hand.

---

## 0. Executive Summary

1. **The commenting, tagging and subscription features do not exist in this codebase.** No table, endpoint, form or JS handler for comments, tags or subscriptions appears anywhere (§2.1). They can't be "fixed"; they have to be built. The nearest existing features (book reviews, annotations, notifications, testimonials) are themselves broken or not wired up (§2.3).
2. **The main cause of "things not working" is a split-brain session.** `config.php` stores sessions in the `sessions` DB table. About 33 pages instead start a *file* session in `./sessions` *before* loading `config.php`. A user who logs in (DB session) looks logged out on those pages (file session), which causes redirect loops and lost logins. Logout never destroys the DB session (§2.2.1).
3. **Nine linked pages don't exist**, including `view_note.php` (linked from five places), `forgot_password.php`, `run_cron_jobs.php`, and the `check_remember_me.php` include, which is a fatal error (§2.2.3).
4. **There is no database schema in the repo**: no `.sql`, no migrations, no `CREATE TABLE`. About 50 tables are referenced. You can't reproduce the database or confirm a table exists from the code (§2.2.2).
5. **Security is critical:** student-reachable SQL injection (`includes/self_quiz.php`, `request_topic.php`), unrestricted file upload that allows remote code execution (`submit_assignment.php`), CSRF protection on only 3 of about 52 form-handling scripts, state changes triggered by GET, 59 copy-pasted admin auth blocks with a hard-coded default password, and debug/schema-dump scripts deployed to production (§3).
6. **InfinityFree:** there are no `sleep()` or `mail()` calls. The scheduled "cron" (GitHub Actions → `run_cron_jobs.php`) calls a file that doesn't exist, uses a hard-coded token, and would probably be blocked by InfinityFree's browser check anyway. HTTP Basic Auth for admins is fragile on this host (§4).

---

## 1. Tech Stack & Structure

### 1.1 Stack

| Layer | Technology | Evidence |
|---|---|---|
| Language | **PHP ≥ 8.0** (uses `str_starts_with`, `str_contains`, union return types `int\|false`, `static fn`) | `composer.json`, `config.php:113`, `src/Support/Env.php` |
| Database | **MySQL/MariaDB**, reached through **two drivers at once**: legacy `mysqli` (`getDB()`) and PDO (`getPDO()` → `SmartCircle\Data\Database`) | `config.php:41-71` |
| Sessions | Custom PDO session handler (`sessions` table) **plus** native file sessions in `./sessions/` (conflict, see §2.2.1) | `config.php:73-151`, e.g. `dashboard.php:2-10` |
| Autoload | Hand-written PSR-4 autoloader (`SmartCircle\` → `src/`); Composer is optional, and there is no `vendor/` | `config/bootstrap.php` |
| Config | `.env` read by `SmartCircle\Support\Env` | `.env.example`, `src/Support/Env.php` |
| Front end | Server-rendered PHP/HTML, one global `style.css` (about 40 KB), jQuery, inline `<script>`, CDN libraries: TinyMCE 6.4.2, MathJax 3, Mermaid 10 **and** 11.6.0 (two versions), Chart.js 4.4.0, Cropper.js, highlight.js, Font Awesome 6 beta | `grep` of CDN URLs |
| Web server | Apache with `.htaccess` (HTTPS redirect, `-Indexes`, dotfile and `config.php` deny) | `.htaccess` |
| CI/CD | GitHub Actions: FTP deploy of the **whole repo root** on push to `main`/`master`; hourly "scheduled checks" `curl` | `.github/workflows/deploy.yml`, `scheduled-checks.yml` |
| Hosting target | InfinityFree (`smarttutor.gt.tc`) | `scheduled-checks.yml:13` |
| Tests / lint | **None** (no PHPUnit, no PHPStan, no CI checks) | — |
| Docs | `README.txt` is **empty** (0 bytes) | — |

**Third-party API keys:** none are needed or used. TinyMCE loads from jsDelivr (self-hosted build, no Tiny Cloud key), and there is no reCAPTCHA, mail API or payment API. The **only** credentials the app needs are `DB_*`, `ADMIN_EMAIL`, `CSRF_SECRET` and `SESSION_SECRET` from `.env`. `Env::require()` throws if any of these is missing, and `config.php` then dies with HTTP 503. Note: `SESSION_SECRET` and `ADMIN_EMAIL` are *required* but never used anywhere.

### 1.2 Current file organisation

The project is mid-migration: a thin new MVC slice sits beside a large legacy "one PHP file per page" app.

```
/ (web root = repo root — everything is publicly reachable)
├── *.php  (≈125 page scripts)         ← LEGACY: SQL + logic + HTML in one file
│   ├── admin_*.php  (≈62 files)       ← admin pages, each with copy-pasted Basic-Auth block
│   ├── student pages (dashboard, notes, exams, take_quiz, read_book, …)
│   ├── AJAX endpoints (note_editor_api, admin_note_lock_api, admin_library_api, get_*.php)
│   └── debug/ops scripts (debug_redirects, session_test, export_schema, admin_backup*)
├── includes/      header/footer + widgets (self_quiz, testimonial_prompt, toc_navigator…)
├── complete_templates.html            ← 46 KB static template dump (deployed publicly)
├── style.css
├── config.php / config.php.example    ← identical; config.php is tracked despite .gitignore
├── init.php, config/bootstrap.php     ← autoloader + .env loader
├── src/                               ← NEW (namespaced SmartCircle\)
│   ├── Controllers/  Controller, Login, Signup, Apply, About
│   ├── Models/       UserModel, ApplicationModel
│   ├── Data/         Database (PDO singleton), Repository, UserRepository
│   └── Support/      Csrf, Env, InputValidator, JsonResponse, ErrorLogger
├── views/          auth/, application/, public/, errors/   ← NEW templates
└── storage/logs/   (.gitkeep)
```

**Migration status:** only **4 pages** go through the new layer: `login.php`, `signup.php`, `apply.php`, `about.php`. These are also the only pages with CSRF protection, PDO and escaped output. The other ~120 scripts use `mysqli`, build SQL by string interpolation, and render HTML inline.

---

## 2. Broken Functionality

### 2.1 Commenting, Tagging, Subscriptions — not implemented

A full-tree search for `comment`, `tag`/`tags`/`tagging`, `subscri*`, `follow`, `like`, `reply` found:

| Searched for | Found |
|---|---|
| `comments` / `post_comments` table, `INSERT INTO …comment…` | **Nothing** |
| `tags` / `note_tags` / `taggables` table, tag UI | **Nothing.** The only "tag" hits are a CSS class `.subject-tag`, `.recipient-tag`, and the English-grammar topic "Question Tags" in `topics_data.php:132`. |
| `subscriptions` / `subscribers` table, subscribe button/endpoint | **Nothing.** The only hit is the word "subscript" in the equation-editor help (`admin_note_editor.php:405`). |

**Conclusion:** the problem is not a missing table, a broken form action or a missing API key. **The features were never built**, or were built in a copy that was never committed. Before building them, check:

1. Was this work done on another machine, branch or host and never pushed? Compare the live InfinityFree file manager against this repo (see §2.2.2: run `export_schema.php` once to see whether `comments`/`tags`/`subscriptions` tables exist in the live DB).
2. If they must be built, they need: tables (`comments`, `tags`, `taggables` pivot, `subscriptions`), CSRF-protected POST endpoints, a notification mechanism (InfinityFree has no `mail()` and no cron, see §4), and moderation in the admin UI.

### 2.2 Root-cause defects that break existing features (and would break any new feature)

#### 2.2.1 Split-brain sessions — **Critical (functional)**

- `config.php:142-151` registers a **database** session handler (`sessions` table) and calls `session_start()`.
- **33 scripts** first do `session_save_path(__DIR__.'/sessions'); session_start();` (a **file** session) and *then* `require 'config.php'`. Examples: `dashboard.php:2-21`, `read_book.php:2-11`, `download.php:2-16`, `index.php`, `notifications.php`, `profile.php`, `take_exam.php`, `submit_quiz.php`, `consent.php`, `pending.php`, `logout.php`.
- In PHP 8, once a session is active, the `session_set_save_handler()` call in `config.php` fails with a warning. Those pages then keep using the file session.

**Effect:**
- Login (`LoginController`, through `config.php`) writes `user_id` into the **DB** session.
- `dashboard.php` reads the **file** session, finds no `user_id`, and redirects to `login.php`. `login.php` sees a logged-in DB session and redirects back to `dashboard.php`. **That is a redirect loop.** `debug_redirects.php` and the recent commits ("Fixed log in session data lost", "Updated to use database session tables") show the team has been chasing exactly this.
- `logout.php:2-24` destroys only the **file** session. The DB session row survives, so **users can't really log out**.
- Any AJAX endpoint (such as a future comment/subscribe endpoint) on a file-session page will see an anonymous user and fail silently.

#### 2.2.2 No schema in source control — **High**

- There is no `.sql`, migration or `CREATE TABLE` anywhere. `*.sql` is even git-ignored.
- About 50 tables are referenced, including `users`, `applications`, `groups`, `group_members`, `group_content_locks`, `notes`, `note_sections`, `note_exercises`, `note_drafts`, `exercise_attempts`, `quizzes`, `quiz_questions`, `quiz_attempts`, `quiz_answers`, `exams`, `exam_questions`, `exam_submissions`, `exam_answers`, `assignments`, `assignment_submissions`, `books`, `book_questions`, `book_annotations`, `book_reviews`, `admin_messages`, `student_messages`, `message_templates`, `testimonials`, `topic_requests`, `topics_covered`, `attendance`, `discipline_log`, `group_meetings`, `student_resources`, `student_reports`, `subject_questions`, `self_quizzes`, `self_quiz_attempts`, `equations_library`, `diagrams_library`, `admin_research_notes`, `admin_settings`, `activity_log`, `universities`, `sessions`.
- There's no way to tell from the repo which of these exist live. `submit_book_review.php:13` needs a `UNIQUE(user_id, book_id)` key for `ON DUPLICATE KEY UPDATE` to work, and nothing in the repo says whether it exists.
- `groups` is a **reserved word in MySQL ≥ 8.0.2** and is used unquoted (e.g. `admin_get_groups.php:49`, `admin_note_editor.php:123`). It works on MariaDB (what InfinityFree runs) but breaks if the app moves to MySQL 8.

#### 2.2.3 Links and includes that point at missing files — **High**

| Missing file | Referenced from | Effect |
|---|---|---|
| `check_remember_me.php` | `note_editor_api.php:2` (`require_once`) | **Fatal error.** The whole note-editor API (image upload, research notes) is down. |
| `view_note.php` | `assignments.php`, `subject.php`, `notes.php`, `quiz_results.php`, `admin_notes_list.php` | 404 on the main "read note" link (`student_view_note.php` exists instead) |
| `forgot_password.php` | `views/auth/login.php` | 404; no password-reset flow exists at all |
| `run_cron_jobs.php` | `.github/workflows/scheduled-checks.yml:13` | Hourly job hits a 404 (see §4) |
| `pending_exercises.php` | `dashboard.php`, `admin_dashboard.php` | 404 |
| `student_submit_assignment.php` | `subject.php` | 404 (`submit_assignment.php` exists) |
| `upload_resource.php` | `includes/header.php` (site-wide nav) | 404 |
| `dismiss_testimonial_prompt.php` | `includes/testimonial_prompt.php` | Prompt can't be dismissed |
| `admin_group_insights.php`, `admin_select_note_for_extraction.php` | `admin_dashboard.php` | 404 |
| `change_password.php` | `check_access.php` allow-list | Dead entry |

#### 2.2.4 Other functional bugs found

- **`submit_book_review.php:14`**: `bind_param("iiissi", …)` gives the 6th value (`review`, a string) type `i`, so on update the review text is saved as `0`. The endpoint is also **not called from anywhere** (orphaned). This is the closest thing the app has to "comments" and it doesn't work.
- **`submit_book_review.php:6`** uses `$user['id']`, which depends on `check_access.php` having filled `$user`. It works, but it's fragile coupling.
- **Two admin auth systems.** The `users`-table login sets `admin_logged` (`LoginController`), while 59 admin pages use HTTP Basic Auth against `admin_settings.admin_hash`. Admins may be asked for credentials twice. There's no admin logout because Basic-Auth credentials are cached by the browser.
- **`is_content_unlocked()`** (`config.php:200-215`) **writes** a lock row on read. A GET by a student inserts DB rows, which is a side effect in a read path.
- **Two DB connections per request** (mysqli + PDO). That doubles connection use against InfinityFree's low per-user connection limit (§4).
- **Mermaid loaded at two major versions** (10 and 11.6.0) on different pages, giving inconsistent diagram rendering.

### 2.3 Status of the nearest existing "social" features

| Feature | Files | Status |
|---|---|---|
| Book reviews (comment-like) | `submit_book_review.php` | Orphaned + type bug (§2.2.4) |
| Book annotations | `read_book.php:33-65` | Works only if the user's session is file-based (§2.2.1); no CSRF |
| Notifications (subscription-like) | `notifications.php`, `admin_notifications_center.php`, `admin_messages` | In-app only. No email or push, and no per-user opt-in/subscribe model. |
| Testimonials | `submit_testimonial.php`, `includes/testimonial_prompt.php`, `get_testimonials.php` | Dismiss endpoint missing |
| Q&A (subject/book questions) | `request_help_subject.php`, `save_book_question.php`, `admin_*_questions.php` | Works, but SQL-injectable on the admin side |

---

## 3. Security Issues

### 3.1 SQL injection

- About **215** `->query("…$var…")` calls interpolate variables directly into SQL across about 50 files. Only **8 files** use `real_escape_string` at all.
- Many interpolated values are `(int)`-cast IDs (low risk). The ones below are **string values from user input**:

| Severity | Location | Who can reach it | Input |
|---|---|---|---|
| **Critical** | `includes/self_quiz.php:15,40,44` | Any logged-in **student** | `$_GET['subject']` → `WHERE subject = '$subject'` |
| **Critical** | `request_topic.php:23,29,46` | Any **student** | `$_POST['subject']`, topic → `DELETE … AND subject = '$subject'` |
| High | `admin_discipline.php:24-29` | Admin (or a CSRF victim admin) | `$_POST['action']`, `reason`, `suspension_end`, raw `{$_POST['admin_notes']}` |
| High | `admin_reports.php:23-25` | Admin/CSRF | `$_POST['status']`, `admin_response` |
| High | `admin_edit_note.php:31`, `admin_note_editor.php:90-139` | Admin/CSRF | title, subject, class, **HTML content** |
| High | `admin_approve.php:38,71,77`, `admin_book_questions.php:24,28`, `admin_subject_questions.php:26`, `admin_message_templates.php:425-427`, `admin_library_manager.php:71,78,97`, `admin_edit_exam.php:32`, `admin_edit_questions.php:37`, `admin_create_exam.php:31`, `admin_create_assignment.php:41`, `admin_edit_assignment.php:50`, `admin_set_meeting.php:27-41`, `admin_attendance.php:50,74,90`, `admin_resources.php:48,51`, `admin_group_locks.php:85-173`, `admin_run_pending_exercises.php:33-81`, `admin_view_note.php:36`, `note_editor_api.php:65` | Admin/CSRF | Various string fields |
| Medium (second-order) | `includes/testimonial_prompt.php:26`, `admin_reassign_group.php:48`, `admin_resources.php:51` | — | DB values re-interpolated into SQL |
| Low | `export_schema.php:47`, `admin_backup.php:25` | — | Table names from `SHOW TABLES` |

Admin pages have no CSRF protection (§3.2), so every "admin-only" injection above can also be triggered by a forged request from a logged-in admin's browser.

**Fix pattern:** PDO prepared statements everywhere, which `src/Data/Repository.php` already provides.

### 3.2 Missing CSRF protection

- About **52 scripts** handle `<form method="post">`. **Only 3 flows** validate a CSRF token: Login, Signup and Apply (`src/Controllers/*`, `src/Support/Csrf.php`). Every other POST is unprotected, including all admin create/edit/delete, discipline, approvals, settings (admin password change, `admin_settings.php:31`), uploads, annotations, testimonials and topic requests.
- **State-changing GET requests** (exploitable with a plain `<img src>` tag):
  - Deletes: `admin_students_list.php:23` (delete student), `admin_exams_list.php:20`, `admin_notes_list.php:22`, `admin_assignments_list.php:20`, `admin_manage_quiz.php:25,31`, `admin_add_questions.php:44`, `admin_feedback.php:27`, `admin_library_manager.php:107`
  - Other changes: `admin_resources.php:60` (reject), `admin_feedback.php:21`, `admin_topic_requests.php:33`, and `admin_reports.php:21` (GET flag + POST body)
- AJAX endpoints with no token: `read_book.php?action=*`, `note_editor_api.php`, `admin_note_lock_api.php`, `admin_library_api.php`, `submit_book_review.php`.

### 3.3 Unescaped output (XSS)

Escaping with `htmlspecialchars` is applied fairly consistently to names and free text in newer pages. The remaining gaps:

| Severity | Location | Issue |
|---|---|---|
| High | `request_topic.php:79` | `<?= $s ?>` and the reflected `$_POST['subject']` comparison. Values come from a fixed list, but the pattern is unsafe. |
| High | `admin_class_overview.php:115` | `echo "<li>{$m['fullname']} …"`: a student-controlled `fullname` is rendered raw in the admin panel (**stored XSS against admin**) |
| Medium | `admin_view_note.php:166`, `student_view_note.php` | Note `content` is printed as raw HTML (by design for a rich-text editor) with no sanitiser (e.g. an HTML allow-list). An admin-authored note can run script for every student. |
| Medium | About 215 `<?= $row['col'] ?>` echoes without escaping, mostly `class_level`, `id`, `points`, dates, `category`, `due_date` | Low risk for numeric columns. `category` (`admin_library_manager.php:146,184`) and `action` (`admin_discipline.php:68`) are free-text strings. |
| — | `includes/header.php:219` | Checked: `$tagline` (built from `ambition`/`university`) **is** escaped on output. No issue. |

### 3.4 Other security issues

| Severity | Issue | Location |
|---|---|---|
| **Critical** | **Unrestricted file upload, so remote code execution is possible.** The extension is taken from the user filename with no allow-list; a student can upload `x.php` into `uploads/assignments/` and run it. No `.htaccess` disables PHP in `uploads/`. Directories are created `0777`. | `submit_assignment.php:44-50` |
| **Critical** | **Debug and data-dump scripts deployed publicly with no auth:** `debug_redirects.php` (387 lines; dumps sessions and DB state, `display_errors=1`), `session_test.php`, and `export_schema.php` (writes `database_schema_export.txt` **into the web root** and links to it) | root |
| **High** | **Hard-coded default admin password** `smarttutor@2026` is seeded into the DB on first run if no hash exists | `config.php:183` |
| High | Fallback bcrypt hash `$2y$12$mQu7…` copy-pasted into **59 admin files** | e.g. `admin_dashboard.php:7` |
| High | **Hard-coded bearer token** `5f4dcc3b5aa765d61d8327deb882cf99` in a public workflow file. It is the MD5 of the string `password`. | `.github/workflows/scheduled-checks.yml:14` |
| High | **IDOR:** any logged-in student can download any other student's exam answers and assignments by filename (no ownership check; filenames are predictable: `user_{id}_exam_{eid}_q{qid}_{time}`) | `download.php:20-45` |
| High | File sessions are written to `./sessions/` **inside the web root** (session-ID filenames aren't guessable, but the files shouldn't be web-reachable) | 33 scripts |
| Medium | Admin uses **HTTP Basic Auth** (credentials sent with every request, no lockout or rate-limit, no logout) | 59 admin files |
| Medium | Login has no rate-limit or lockout | `src/Controllers/LoginController.php` |
| Medium | `download.php` sends `mime_content_type()` with `Content-Disposition: inline`, so uploaded HTML/SVG renders inline (XSS) | `download.php:41-43` |
| Medium | `config.php` is in `.gitignore` but **tracked**. It's currently identical to `config.php.example` with no secrets, but the next local edit could commit credentials. | `.gitignore:2` |
| Medium | FTP deploy uploads **the whole repo root** (including `complete_templates.html`, debug scripts and `composer.json`) | `deploy.yml:17` |
| Low | Font Awesome `6.0.0-beta3` (pre-release); Mermaid `@10` not pinned to a patch version | CDN tags |
| Low | Raw `die()` messages leak implementation detail on many admin pages | various |

**Good things already in place:** PDO with `ATTR_EMULATE_PREPARES=false`; `password_hash`/`password_verify` for user passwords; `session_regenerate_id(true)` on login; a well-built `Csrf` class with HMAC; `download.php`/`admin_download.php` guard against path traversal with `basename` + `realpath`; `.htaccess` blocks dotfiles (so `.env` can't be downloaded); `json_encode(…JSON_HEX_*)` in `views/application/apply.php`.

---

## 4. InfinityFree Host Compatibility

| Check | Result |
|---|---|
| `sleep()` / `usleep()` / `time_nanosleep()` | ✅ **Not used anywhere.** |
| `mail()` | ✅ **Not used anywhere.** ⚠️ This also means **no email exists at all**: no password reset (`forgot_password.php` missing), no subscription or notification emails. `ADMIN_EMAIL` is required in `.env` but never used. |
| `exec` / `shell_exec` / `system` / `passthru` / `proc_open` / `popen` | ✅ Not used. |
| Outbound HTTP from PHP (`curl_init`, remote `file_get_contents`, `fsockopen`) | ✅ Not used. |
| `set_time_limit` / `ini_set('memory_limit')` | ✅ Not used. |
| **Native cron** | ⚠️ **Not used directly, but a cron-dependent design exists.** `.github/workflows/scheduled-checks.yml` curls `https://smarttutor.gt.tc/run_cron_jobs.php` every hour. Problems: **(1)** `run_cron_jobs.php` doesn't exist in the repo, so the job does nothing useful. **(2)** InfinityFree puts a JavaScript/cookie browser check in front of sites, which normally blocks non-browser clients like GitHub-runner `curl`; spoofing `User-Agent`/`Referer` (lines 15-17) doesn't get past it. **(3)** The token is hard-coded (§3.4). **(4)** The hourly schedule burns about 720 Actions runs per month for nothing. |
| Work that should be scheduled | `admin_run_pending_exercises.php` (manual "run" page sending reminder messages); `sess_gc()` (relies on PHP's probabilistic GC, which is fine); the suspension-expiry logic in `check_access.php`. All of these should be **triggered lazily on page load** or by an admin button, not by cron. |
| HTTP Basic Auth (`PHP_AUTH_USER`) | ⚠️ **Risk.** On many shared hosts PHP runs as CGI/FastCGI, and `PHP_AUTH_USER`/`PHP_AUTH_PW` are then **not filled in** without an `.htaccess` `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]` workaround. If they aren't, all 59 admin pages return 401 forever. Test this on the live host; replace with session-based admin login either way. |
| DB connection limits | ⚠️ InfinityFree enforces a low `max_user_connections`. Each request opens **two** connections (mysqli + PDO), plus a PDO session handler that writes on every request. |
| `.env` on the server | ⚠️ `deploy.yml` excludes `.env`, and InfinityFree has no panel for environment variables. A `.env` must be **uploaded by hand** once (the `.htaccess` dotfile rule correctly blocks web access to it). If it's missing, every page returns HTTP 503 from `config.php:136`. |
| Writable dirs created at runtime with `mkdir(…, 0777)` | ⚠️ Works, but it's over-permissive. Prefer `0755`, create the directories once, and add `uploads/.htaccess` (`php_flag engine off` / `RemoveHandler .php`). |
| Upload size | ⚠️ InfinityFree limits uploads to about 10 MB per file. `admin_upload_book.php` (PDF books) and `share_resource.php` (multi-file) don't check size or report the limit to the user. |
| `ORDER BY RAND()` | OK at this data size. |
| PHP version | Needs PHP ≥ 8.0. Select 8.1+ in the InfinityFree control panel. |

**Recommendations for this host:**
- Replace "cron" with a **lazy scheduler**: a `scheduled_jobs` table with a `last_run_at` column, checked at most once every N minutes at the end of a normal page request, plus a "Run now" admin button.
- For email (needed for subscriptions and password reset): use PHPMailer over **external SMTP**, or an HTTPS mail API, and check that the InfinityFree plan allows outbound connections on that port. Otherwise keep notifications **in-app only** (the existing `admin_messages` table).

---

## 5. Target Architecture (3 layers)

### 5.1 Principles

- **Only `public/` is web-reachable.** On InfinityFree, point the domain's document root at `htdocs/public/` if the panel allows it. If it doesn't, deploy `public/*` into `htdocs/` and the rest one level up, or into `htdocs/app/` guarded by `Require all denied`.
- **Presentation** knows HTTP and HTML. **Application** knows use-cases and rules. **Data** knows SQL. Dependencies point **downward only**.
- Keep the existing `SmartCircle\` PSR-4 namespace and hand-written autoloader. Shared hosting needs no Composer at runtime.
- **Migrate one page at a time** (strangler pattern): each legacy `foo.php` becomes a 3-line shim that calls a controller, so URLs and bookmarks keep working.

### 5.2 Proposed folder structure

```
SMART-Circle/
├── public/                              # ── web root (the ONLY public directory)
│   ├── index.php                        # front controller / router entry
│   ├── .htaccess                        # HTTPS, rewrite to index.php, deny dotfiles
│   ├── assets/{css,js,img}/             # style.css split by area; no inline <script>
│   └── uploads/ → (symlink or none)     # prefer serving files via a controller, not directly
│
├── src/
│   ├── Presentation/                    # ── LAYER 1: HTTP in, HTML/JSON out
│   │   ├── Http/
│   │   │   ├── Router.php
│   │   │   ├── Request.php / Response.php
│   │   │   └── Middleware/              # Auth, AdminAuth, Csrf, Approved, Consent, RateLimit
│   │   ├── Controllers/
│   │   │   ├── Auth/                    # Login, Signup, Logout, PasswordReset
│   │   │   ├── Student/                 # Dashboard, Notes, Quizzes, Exams, Assignments, Library
│   │   │   ├── Admin/                   # one controller per admin_* area (≈15, not 62)
│   │   │   ├── Social/                  # CommentController, TagController, SubscriptionController
│   │   │   └── Api/                     # JSON endpoints (annotations, note editor, locks)
│   │   └── View/
│   │       ├── View.php                 # render() with auto-escaping helper e()
│   │       └── templates/{layouts,partials,auth,student,admin,social,errors}/
│   │
│   ├── Application/                     # ── LAYER 2: use-cases & business rules (no SQL, no HTML)
│   │   ├── Auth/            AuthService, AdminAuthService, PasswordResetService
│   │   ├── Enrollment/      ApplicationService, ApprovalService, ConsentService
│   │   ├── Content/         NoteService, ContentLockService (replaces is_content_unlocked)
│   │   ├── Assessment/      QuizService, ExamService, AssignmentService, MarkingService
│   │   ├── Library/         BookService, AnnotationService, ReviewService
│   │   ├── Social/          CommentService, TagService, SubscriptionService
│   │   ├── Notification/    NotificationService (in-app; Mailer interface optional)
│   │   ├── Discipline/      DisciplineService, AttendanceService
│   │   ├── Scheduling/      LazyScheduler (replaces cron), Jobs/*
│   │   ├── Upload/          UploadService (allow-list, size, MIME sniff, random names)
│   │   └── Validation/      InputValidator (moved from Support)
│   │
│   ├── Data/                            # ── LAYER 3: persistence
│   │   ├── Database.php                 # single PDO connection (drop mysqli/getDB)
│   │   ├── SessionHandler.php           # ONE session handler, used by every request
│   │   ├── Repository.php               # base: prepared-statement helpers only
│   │   ├── Repositories/                # UserRepository, NoteRepository, CommentRepository,
│   │   │                                #   TagRepository, SubscriptionRepository, …
│   │   └── Entities/                    # plain DTOs (User, Note, Comment, Tag, Subscription)
│   │
│   └── Support/                         # cross-cutting, no layer rules: Env, Csrf, Logger, Clock
│
├── config/
│   ├── bootstrap.php                    # autoload + Env + error handler + session (ONE place)
│   └── routes.php                       # URL → Controller@method (+ legacy URL aliases)
│
├── database/
│   ├── schema.sql                       # authoritative schema (export the live DB first)
│   ├── migrations/0001_baseline.sql …   # numbered, idempotent; 00xx_comments_tags_subs.sql
│   └── seeds/
│
├── storage/                             # NOT web-reachable
│   ├── uploads/{books,assignments,exam_answers,exercises,profile,notes}/
│   ├── logs/
│   └── cache/
│
├── tools/                               # never deployed: export_schema, debug scripts
├── tests/{Unit,Integration}/
├── docs/audit.md
└── .github/workflows/{deploy.yml (deploys public/ + src/ + config/ only), ci.yml (php -l, phpstan)}
```

### 5.3 Layer rules (to enforce in review)

| Layer | May depend on | Must never |
|---|---|---|
| Presentation | Application, Support | Run SQL, call `getDB()`/`getPDO()`, or echo without `e()` |
| Application | Data (through repository interfaces), Support | Read `$_GET`/`$_POST`/`$_SESSION`, or output HTML |
| Data | Support | Contain business rules or build SQL from unbound input |

### 5.4 Suggested order of work (for a later PR, not this one)

1. **Stop the bleeding (security):** delete or move `debug_redirects.php`, `session_test.php`, `export_schema.php` out of the deploy; add an extension allow-list and `uploads/.htaccess` to `submit_assignment.php`; rotate the admin password and remove the hard-coded default and fallback hash; remove the bearer token and disable `scheduled-checks.yml`.
2. **Fix sessions:** one bootstrap, one session handler, used by every page. Delete every `session_save_path(__DIR__.'/sessions')` block. This alone should fix the login loop and logout.
3. **Commit the schema:** run the schema export once (offline), save it as `database/schema.sql`, and check whether any comment/tag/subscription tables exist live.
4. **Create the missing pages or fix the links** (§2.2.3), starting with `check_remember_me.php` (fatal) and `view_note.php`.
5. **CSRF middleware + POST-only mutations** across all admin pages.
6. **Move pages into the 3 layers** one area at a time (Auth → Student content → Admin).
7. **Build Comments / Tags / Subscriptions** natively in the new structure, with in-app notifications and a lazy scheduler.

---

*End of report. No application code was modified in producing this audit.*
