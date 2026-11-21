<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Middleware;

use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;

/** New students must accept the class agreement before using the site. */
final class RequireOnboarded implements Middleware
{
    public function __construct(private readonly Context $context)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        return $this->context->needsOnboarding() ? Response::redirect('/welcome/onboarding') : $next($request);
    }
}
