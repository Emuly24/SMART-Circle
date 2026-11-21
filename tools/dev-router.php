<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in server, mimicking the .htaccess rules (local development only):
 *   php -S 127.0.0.1:8080 -t public tools/dev-router.php
 * Existing files in public/ are served directly; everything else goes to public/index.php.
 */

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = realpath(__DIR__ . '/../public' . $path);
$public = realpath(__DIR__ . '/../public');

if ($path !== '/' && $file !== false && $public !== false && str_starts_with($file, $public) && is_file($file)) {
    return false;
}

require __DIR__ . '/../public/index.php';
