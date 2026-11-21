<?php

declare(strict_types=1);

/*
 * Fallback entry point. The root .htaccess normally sends every request into public/,
 * so this file is only reached if rewriting is unavailable. It also satisfies hosting
 * panels (InfinityFree's health check) that look for an index file in htdocs.
 */

require __DIR__ . '/public/index.php';
