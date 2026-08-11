<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

use SmartCircle\Controllers\SignupController;

(new SignupController())->handle();
