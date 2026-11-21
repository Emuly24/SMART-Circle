<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

final class NotificationController
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('notifications/index', [
            'title' => 'Notifications',
            'items' => $this->notifications->recent((int) $this->context->userId()),
        ]));
    }

    public function markRead(Request $request): Response
    {
        $this->notifications->markAllRead((int) $this->context->userId());
        $this->session->flash('info', 'All notifications marked as read.');

        return Response::redirect('/notifications');
    }
}
