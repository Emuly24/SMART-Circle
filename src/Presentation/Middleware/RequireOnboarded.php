<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Middleware;

use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;

/** New students must accept the class agreement, and new team tutors the team agreement, before using the site. */
final class RequireOnboarded implements Middleware
{
    public function __construct(private readonly Context $context)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->context->needsOnboarding()) {
            return $next($request);
        }

        return Response::redirect($this->context->role() === 'team' ? '/team/agreement' : '/welcome/onboarding');
    }
}
