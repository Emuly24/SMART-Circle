<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/check_access.php';

use SmartCircle\Controllers\ApplyController;

(new ApplyController())->handle();
