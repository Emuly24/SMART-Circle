<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Middleware;

use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;

/** On the lesson recording routes, a partner teacher must be a guest teacher (the tutor and team pass). */
final class RequireGuestTeacher implements Middleware
{
    public function __construct(private readonly Context $context)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->context->role() === 'partner' && !$this->context->isGuestTeacher()) {
            throw new HttpException(403, 'Ask the tutor to make you a guest teacher to record lessons.');
        }

        return $next($request);
    }
}
