<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Install\InstallService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/** Landing page after login; each role sees its own dashboard. */
final class DashboardController
{
    public function __construct(
        private readonly Context $context,
        private readonly View $view,
        private readonly InstallService $installer,
        private readonly Session $session,
        /** @var (\Closure(int): array<string, mixed>)|null builds the partner's school summary */
        private readonly ?\Closure $partnerSummary = null,
        /** @var (\Closure(int): ?array<string, mixed>)|null the student's latest pledge */
        private readonly ?\Closure $studentPledge = null,
        /** @var (\Closure(int): list<array<string, mixed>>)|null the student's latest marked work */
        private readonly ?\Closure $studentFeedback = null,
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

        return Response::html($this->view->render($template, [
            'title' => 'Dashboard',
            'pendingUpdates' => $role === 'tutor' ? $this->installer->pendingUpdates() : [],
            'summary' => $role === 'partner' && $this->partnerSummary !== null ? ($this->partnerSummary)((int) $this->context->userId()) : null,
            'pledge' => $role === 'student' && $this->studentPledge !== null ? ($this->studentPledge)((int) $this->context->userId()) : null,
            'feedback' => $role === 'student' && $this->studentFeedback !== null ? ($this->studentFeedback)((int) $this->context->userId()) : [],
        ]));
    }

    /** Tutor: apply database updates that came with new code (no shell on InfinityFree). */
    public function updateDatabase(Request $request): Response
    {
        $applied = $this->installer->applyUpdates((int) $this->context->userId(), $request->ip());
        $this->session->flash('success', $applied === []
            ? 'The database is already up to date.'
            : 'Database updated (' . count($applied) . ' update' . (count($applied) === 1 ? '' : 's') . ').');

        return Response::redirect('/dashboard');
    }
}
