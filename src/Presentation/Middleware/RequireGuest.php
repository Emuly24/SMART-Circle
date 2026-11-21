<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Middleware;

use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;

/** Logged-in users visiting the login page go to their dashboard instead. */
final class RequireGuest implements Middleware
{
    public function __construct(private readonly Context $context)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        return $this->context->isGuest() ? $next($request) : Response::redirect('/dashboard');
    }
}
