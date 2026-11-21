<?php

declare(strict_types=1);

/*
 * Loads the code and settings. Used by public/index.php and the tests.
 * No Composer needed on the server: a small PSR-4 autoloader maps SmartCircle\ to src/.
 */

use SmartCircle\Support\Config;

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'SmartCircle\\';
    if (str_starts_with($class, $prefix)) {
        $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require_once $root . '/src/Support/functions.php';

// Tests point SMARTCIRCLE_SECRETS at their own file; the server uses /secrets.php.
$secrets = getenv('SMARTCIRCLE_SECRETS') ?: $root . '/secrets.php';
$config = Config::load($root . '/config/app.php', $secrets);

date_default_timezone_set($config->string('app.timezone', 'Africa/Blantyre'));
error_reporting(E_ALL);
ini_set('display_errors', $config->bool('app.debug') ? '1' : '0');
ini_set('log_errors', '1');

return $config;
