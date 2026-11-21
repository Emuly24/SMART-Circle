<?php

declare(strict_types=1);

/*
 * Default settings. Real secrets (database, mail, keys) never go here:
 * they live in /secrets.php on the server (see secrets.example.php),
 * which overrides any key below.
 */

return [
    'app' => [
        'name'       => 'SMART Circle',
        'tagline'    => 'Understand it. Apply it.',
        'debug'      => false,
        // Absolute site URL used in emails, QR codes and letters, e.g. https://smartcircle.example
        'url'        => '',
        // Turn on only after confirming HTTPS works on the host, to avoid redirect loops.
        'force_https' => false,
        'timezone'   => 'Africa/Blantyre',
    ],

    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => '',
        'user'     => '',
        'password' => '',
    ],

    'session' => [
        'name'          => 'smartcircle',
        // Log out after this many seconds without activity.
        'idle_timeout'  => 60 * 60 * 8,
    ],

    'security' => [
        // Login attempts allowed per phone number (and per IP) in the window below.
        'login_max_attempts' => 5,
        'login_window_secs'  => 15 * 60,
        // Required to run the one-time web installer (set in secrets.php).
        'install_token'      => '',
    ],

    'mail' => [
        // Zoho SMTP (outbound only; replies arrive in the Zoho inbox).
        'host'       => 'smtp.zoho.com',
        'port'       => 465,
        'encryption' => 'ssl',
        'username'   => '',
        'password'   => '',
        'from_email' => 'smartcircle@zohomail.com',
        'from_name'  => 'SMART Circle',
        // Emails sent per batch when the admin area is opened (no cron on InfinityFree).
        'batch_size' => 10,
    ],

    'admissions' => [
        // Spam protection without outside services.
        'min_seconds_to_fill' => 20,
        'max_per_ip_per_day'  => 5,
    ],

    'lessons' => [
        // Largest lesson file accepted (about 10 hours of voice at 32 kbps).
        'max_bytes' => 150 * 1024 * 1024,
    ],

    'paths' => [
        'storage' => dirname(__DIR__) . '/storage',
    ],
];
