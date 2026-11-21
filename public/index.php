<?php

declare(strict_types=1);

/*
 * Front controller: every page request comes through here.
 */

use SmartCircle\Presentation\App;
use SmartCircle\Presentation\Http\Request;

$config = require dirname(__DIR__) . '/config/bootstrap.php';

(new App($config))->handle(Request::fromGlobals())->send();
