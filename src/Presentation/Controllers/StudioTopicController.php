<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Topics\RequestService;
use SmartCircle\Application\Topics\TopicService;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Tutor: the ranked request list and the topic catalogue editor. */
final class StudioTopicController
{
    public function __construct(
        private readonly TopicService $topics,
        private readonly RequestService $requests,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /* ---------- Requests ---------- */

    public function requests(Request $request): Response
    {
        $filter = [
            'form' => (int) $request->query('form', '0'),
            'subject' => isset(Subjects::NAMES[$request->query('subject')]) ? $request->query('subject') : '',
            'status' => in_array($request->query('status', 'active'), ['active', 'open', 'planned', 'recorded', 'closed', 'all'], true)
                ? $request->query('status', 'active') : 'active',
        ];

        return Response::html($this->view->render('studio/requests/index', [
            'title' => 'Topic requests',
            'rows' => $this->requests->ranked(array_merge($filter, ['status' => $filter['status'] === 'all' ? '' : $filter['status']])),
            'filter' => $filter,
            'names' => Subjects::NAMES,
            'short' => RequestService::STRUGGLES_SHORT,
            'statuses' => RequestService::STATUSES,
        ]));
    }

    public function request(Request $request): Response
    {
        $detail = $this->requests->detail((int) $request->param('id')) ?? throw new HttpException(404);

        return Response::html($this->view->render('studio/requests/show', [
            'title' => 'Request: ' . $detail['topic_title'],
            'r' => $detail,
            'names' => Subjects::NAMES,
            'struggles' => RequestService::STRUGGLES,
            'short' => RequestService::STRUGGLES_SHORT,
            'statuses' => RequestService::STATUSES,
        ]));
    }

    public function setStatus(Request $request): Response
    {
        $id = (int) $request->param('id');
        $status = $request->input('status');
        if ($this->requests->setStatus($id, $status)) {
            $this->session->flash('success', match ($status) {
                'planned' => 'Marked as planned. The students who asked have been told.',
                'closed' => 'Request closed.',
                default => 'Request reopened.',
            });
        }

        return Response::redirect('/studio/requests/' . $id);
    }

    public function exception(Request $request): Response
    {
        $id = (int) $request->param('id');
        $approve = $request->input('decision') === 'approve';
        if ($this->requests->decideException($id, $approve)) {
            $this->session->flash('success', $approve ? 'Exception approved.' : 'Exception declined and the request closed.');
        }

        return Response::redirect('/studio/requests/' . $id);
    }

    /* ---------- Topic catalogue ---------- */

    public function topics(Request $request): Response
    {
        [$form, $subject] = $this->formSubject($request->query('form', '1'), $request->query('subject', 'mathematics'));

        return $this->topicsPage($form, $subject, [], '');
    }

    public function addTopic(Request $request): Response
    {
        [$form, $subject] = $this->formSubject($request->input('form'), $request->input('subject'));
        $parent = (int) $request->input('parent_id');
        $errors = $this->topics->add($form, $subject, $parent === 0 ? null : $parent, $request->input('title'), $request->input('unit'));
        if ($errors !== []) {
            return $this->topicsPage($form, $subject, $errors, '', 422);
        }
        $this->session->flash('success', 'Topic added.');

        return Response::redirect('/studio/topics?form=' . $form . '&subject=' . $subject);
    }

    public function importTopics(Request $request): Response
    {
        [$form, $subject] = $this->formSubject($request->input('form'), $request->input('subject'));
        $added = $this->topics->import($form, $subject, $request->input('list'));
        if ($added === 0) {
            return $this->topicsPage($form, $subject, ['list' => 'No topics found. Put one topic per line.'], $request->input('list'), 422);
        }
        $this->session->flash('success', "Added $added topics and subtopics.");

        return Response::redirect('/studio/topics?form=' . $form . '&subject=' . $subject);
    }

    public function editTopic(Request $request): Response
    {
        return $this->editPage($this->topicOr404($request), []);
    }

    public function updateTopic(Request $request): Response
    {
        $topic = $this->topicOr404($request);
        $input = [];
        foreach (['title', 'unit', 'objectives', 'sort_order', 'is_active'] as $f) {
            $input[$f] = $request->input($f);
        }
        $errors = $this->topics->update((int) $topic['id'], $input);
        if ($errors !== []) {
            return $this->editPage(array_merge($topic, $input), $errors, 422);
        }
        $this->session->flash('success', 'Topic saved.');

        return Response::redirect('/studio/topics?form=' . $topic['form'] . '&subject=' . $topic['subject']);
    }

    /** @param array<string, string> $errors */
    private function topicsPage(int $form, string $subject, array $errors, string $list, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/topics/index', [
            'title' => 'Topic catalogue',
            'form' => $form,
            'subject' => $subject,
            'names' => Subjects::NAMES,
            'tree' => $this->topics->tree($form, $subject, true),
            'errors' => $errors,
            'list' => $list,
        ]), $status);
    }

    /** @param array<string, mixed> $topic @param array<string, string> $errors */
    private function editPage(array $topic, array $errors, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/topics/edit', [
            'title' => 'Edit topic',
            'topic' => $topic,
            'names' => Subjects::NAMES,
            'errors' => $errors,
        ]), $status);
    }

    /** @return array{0: int, 1: string} */
    private function formSubject(string $form, string $subject): array
    {
        return [max(1, min(4, (int) $form)), isset(Subjects::NAMES[$subject]) ? $subject : 'mathematics'];
    }

    /** @return array<string, mixed> */
    private function topicOr404(Request $request): array
    {
        return $this->topics->find((int) $request->param('id')) ?? throw new HttpException(404);
    }
}
