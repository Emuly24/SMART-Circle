<?php

declare(strict_types=1);

/*
 * SMART Circle — server secrets.
 *
 * 1. Copy this file to secrets.php in the same folder.
 * 2. Fill in the real values.
 * 3. Upload secrets.php to the server ONCE (InfinityFree file manager / FTP).
 *    It is git-ignored and never deployed by GitHub Actions.
 *
 * Any key here overrides the same key in config/app.php.
 */

return [
    'app' => [
        'url'   => 'https://your-site.example',
        'debug' => false,
    ],

    // InfinityFree control panel -> MySQL Databases
    'db' => [
        'host'     => 'sqlXXX.infinityfree.com',
        'name'     => 'if0_XXXXXXXX_smartcircle',
        'user'     => 'if0_XXXXXXXX',
        'password' => 'change-me',
    ],

    'security' => [
        // A long random string. Needed once to run /install, then you can remove it.
        // Generate: php -r "echo bin2hex(random_bytes(24));"
        'install_token' => 'replace-with-a-long-random-string',
    ],

    // Zoho Mail SMTP: the address the site sends from (and that letters show for replies)
    'mail' => [
        'username' => 'tutor@smartcircle.gt.tc',
        'password' => 'your-zoho-password-or-app-password',
    ],
];
