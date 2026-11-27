<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Middleware;

use SmartCircle\Application\Team\TeamService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;

/**
 * Team tutors work only in their own subjects. On a route that opens an item ({id}), the item must
 * be in one of their subjects (or, before it has a subject, be their own); a form that sends a
 * subject must send one of theirs. The admin is never limited.
 */
final class SubjectGuard implements Middleware
{
    public function __construct(
        private readonly Context $context,
        private readonly TeamService $team,
        private readonly ?string $type = null,
        private readonly string $param = 'id',
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $scope = $this->context->subjects();
        if ($scope === null) {
            return $next($request);
        }
        if ($this->type !== null && $request->param($this->param) !== '') {
            $item = $this->team->item($this->type, (int) $request->param($this->param));
            if ($item === null) {
                throw new HttpException(404);
            }
            if (!TeamService::mayOpen($item, $scope, (int) $this->context->userId())) {
                throw new HttpException(403, 'This belongs to a subject you do not teach.');
            }
        }
        $subject = $request->isPost() ? $request->input('subject') : '';
        if ($subject !== '' && !in_array($subject, $scope, true)) {
            throw new HttpException(403, 'You can only work in your own subjects.');
        }

        return $next($request);
    }
}
