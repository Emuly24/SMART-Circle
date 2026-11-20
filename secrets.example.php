<?php

declare(strict_types=1);

/**
 * SMART Circle — secrets template.
 *
 * HOW TO USE
 *   1. Copy this file to `secrets.php` (same folder) and fill in the real values.
 *   2. Upload `secrets.php` to the server ONCE by FTP / the InfinityFree file manager.
 *   3. NEVER commit `secrets.php` (it is git-ignored by the `*secret*` rule in .gitignore).
 *
 * WHY A .php FILE?
 *   If the web server ever serves this file directly, PHP runs it and it returns an array,
 *   so nothing is printed. A plain-text `.env` would be exposed if the `.htaccess` dotfile rule failed.
 *
 * STATUS
 *   The app currently reads these values from `.env` (see `.env.example` and
 *   `src/Support/Env.php`). Loading `secrets.php` is planned for config/bootstrap.php;
 *   until then, put the same values in `.env`. Keys here match the `.env` variable names.
 *
 * ADMIN PASSWORD
 *   Store a HASH, never the plain password. Generate one locally with:
 *     php -r "echo password_hash('your-new-admin-password', PASSWORD_DEFAULT), PHP_EOL;"
 */

return [
    // ----- Application -----
    'APP_DEBUG' => 'false', // never 'true' on the live site

    // ----- Database (InfinityFree control panel → MySQL Databases) -----
    'DB_HOST' => 'sqlXXX.infinityfree.com',
    'DB_NAME' => 'if0_XXXXXXXX_smartcircle',
    'DB_USER' => 'if0_XXXXXXXX',
    'DB_PASS' => 'change-me',

    // ----- Admin -----
    'ADMIN_EMAIL'         => 'admin@example.com',
    'ADMIN_PASSWORD_HASH' => '$2y$12$replace.with.output.of.password_hash.................',

    // ----- Security keys (each: 64 random hex chars, e.g. php -r "echo bin2hex(random_bytes(32));") -----
    'CSRF_SECRET'    => 'replace-with-64-hex-characters',
    'SESSION_SECRET' => 'replace-with-another-64-hex-characters',
];
