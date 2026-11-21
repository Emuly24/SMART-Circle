<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

use RuntimeException;

/** A rule stopped an admissions action; the message is safe to show. */
final class AdmissionException extends RuntimeException
{
}
