<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

use SmartCircle\Controllers\LoginController;

(new LoginController())->handle();
