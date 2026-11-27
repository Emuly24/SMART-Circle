<?php

declare(strict_types=1);

/*
 * Front controller: every page request comes through here.
 */

use SmartCircle\Presentation\App;
use SmartCircle\Presentation\Http\Request;

$config = require dirname(__DIR__) . '/config/bootstrap.php';

$app = new App($config);
$app->handle(Request::fromGlobals())->send();
$app->afterResponse();
