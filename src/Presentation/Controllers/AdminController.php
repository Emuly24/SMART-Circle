<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Admin\BackupService;
use SmartCircle\Application\Practice\PracticeService;
use SmartCircle\Application\Settings\SettingsService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/** Admin-only tools: settings, "Try as" practice accounts, and the database backup. */
final class AdminController
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly PracticeService $practice,
        private readonly BackupService $backup,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function settings(Request $request): Response
    {
        return $this->settingsPage([], ['tutor_whatsapp' => $this->settings->tutorWhatsapp() ?? '', 'whatsapp_community' => $this->settings->community() ?? '']);
    }

    public function saveSettings(Request $request): Response
    {
        $in = ['tutor_whatsapp' => $request->input('tutor_whatsapp'), 'whatsapp_community' => $request->input('whatsapp_community')];
        $errors = $this->settings->save($in);
        if ($errors !== []) {
            return $this->settingsPage($errors, $in, 422);
        }
        $this->session->flash('success', 'Settings saved.');

        return Response::redirect('/studio/settings');
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function settingsPage(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/settings', [
            'title' => 'Settings',
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    /* ---------- Try as ---------- */

    public function practice(Request $request): Response
    {
        return Response::html($this->view->render('studio/practice', [
            'title' => 'Try as',
            'labels' => PracticeService::LABELS,
            'exists' => $this->practice->exists(),
        ]));
    }

    public function enter(Request $request): Response
    {
        $adminId = (int) $this->context->userId();
        $userId = $this->practice->account($request->input('as'), $adminId) ?? throw new HttpException(404);
        $this->session->login($userId);
        $this->session->set('practice_admin', $adminId);
        $this->session->flash('info', 'You are now trying SMART Circle as ' . (PracticeService::LABELS[$request->input('as')] ?? 'a practice account') . '.');

        return Response::redirect('/dashboard');
    }

    /** Back to the admin's own account (any practice account can call this). */
    public function leave(Request $request): Response
    {
        $adminId = $this->context->practiceAdmin() ?? throw new HttpException(403);
        $this->session->forget('practice_admin');
        $this->session->login($adminId);
        $this->session->flash('success', 'Back to your admin account. Practice data stays until you reset it.');

        return Response::redirect('/studio/practice');
    }

    public function reset(Request $request): Response
    {
        $this->practice->reset((int) $this->context->userId(), $request->ip());
        $this->session->flash('success', 'Practice accounts and everything done with them have been removed. They are created fresh next time.');

        return Response::redirect('/studio/practice');
    }

    /* ---------- Backup ---------- */

    public function backup(Request $request): Response
    {
        $b = $this->backup->make((int) $this->context->userId(), $request->ip());

        return (new Response($b['body'], 200, ['Content-Type' => $b['type'], 'Cache-Control' => 'no-store']))
            ->withHeader('Content-Disposition', 'attachment; filename="' . $b['filename'] . '"');
    }
}
