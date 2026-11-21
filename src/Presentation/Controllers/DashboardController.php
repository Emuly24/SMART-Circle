<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\View\View;

/** Landing page after login; each role sees its own dashboard. */
final class DashboardController
{
    public function __construct(
        private readonly Context $context,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        $role = $this->context->role() ?? 'student';
        $template = match ($role) {
            'tutor' => 'dashboard/tutor',
            'partner' => 'dashboard/partner',
            default => 'dashboard/student',
        };

        return Response::html($this->view->render($template, ['title' => 'Dashboard']));
    }
}
