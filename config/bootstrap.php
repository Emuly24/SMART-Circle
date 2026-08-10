<?php

declare(strict_types=1);

/**
 * Application bootstrap: PSR-4 autoloading and environment configuration.
 *
 * Loaded by init.php or config.php — do not start sessions here.
 */

(static function (): void {
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    $bootstrapped = true;
    $rootDir = dirname(__DIR__);

    // PSR-4 autoloader: SmartCircle\ → src/
    spl_autoload_register(static function (string $class) use ($rootDir): void {
        $prefix = 'SmartCircle\\';
        $baseDir = $rootDir . '/src/';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require $file;
        }
    });

    // Optional Composer autoload (dev tooling / future packages).
    $composerAutoload = $rootDir . '/vendor/autoload.php';
    if (is_file($composerAutoload)) {
        require_once $composerAutoload;
    }

    SmartCircle\Support\Env::load($rootDir . '/.env');
})();
