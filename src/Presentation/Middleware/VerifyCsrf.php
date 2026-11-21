<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Middleware;

use SmartCircle\Presentation\Http\Csrf;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;

/** Rejects any POST without the session's form token. */
final class VerifyCsrf implements Middleware
{
    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($request->isPost()) {
            $token = $request->input(Csrf::FIELD);
            if ($token === '') {
                $token = $request->header('X-CSRF-Token');
            }
            if (!$this->csrf->isValid($token)) {
                // 403, not the non-standard 419: Apache turns unknown status codes into 500 errors.
                throw new HttpException(403, HttpException::FORM_EXPIRED);
            }
        }

        return $next($request);
    }
}
