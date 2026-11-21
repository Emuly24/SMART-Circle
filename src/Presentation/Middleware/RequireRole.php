<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Middleware;

use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;

/**
 * Login required; optionally only for some roles.
 * Guests are sent to the login page and brought back afterwards.
 */
final class RequireRole implements Middleware
{
    /** @param list<string> $roles empty = any logged-in user */
    public function __construct(
        private readonly Context $context,
        private readonly Session $session,
        private readonly array $roles = [],
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->context->isGuest()) {
            if ($request->method === 'GET') {
                $this->session->set('intended', $request->path);
            }
            $this->session->flash('info', 'Please log in to continue.');

            return Response::redirect('/login');
        }

        if ($this->roles !== [] && !in_array($this->context->role(), $this->roles, true)) {
            throw new HttpException(403);
        }

        return $next($request);
    }
}
