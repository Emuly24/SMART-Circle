<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Community\ChallengeService;
use SmartCircle\Application\Community\DiscussionService;
use SmartCircle\Application\Community\ProgressService;
use SmartCircle\Application\Topics\TopicService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Lesson discussions, exit tickets, weekly challenges, the wall, tags and badges; and the tutor's moderation. */
final class CommunityController
{
    public function __construct(
        private readonly DiscussionService $discussion,
        private readonly ChallengeService $challenges,
        private readonly ProgressService $progress,
        private readonly TopicService $topics,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    private function uid(): int
    {
        return (int) $this->context->userId();
    }

    private function role(): string
    {
        return (string) $this->context->role();
    }

    /* ---------- Lesson discussions ---------- */

    public function comment(Request $request): Response
    {
        $id = (int) $request->param('id');
        $parent = (int) $request->input('parent_id');
        $error = $this->discussion->post($id, $this->uid(), $this->role(), (string) ($this->context->user()['full_name'] ?? ''), $request->input('body'), $parent > 0 ? $parent : null, $request->input('time'));
        $this->session->flash(...($error === '' ? ['success', 'Posted. You will be told when someone replies.'] : ['error', $error]));

        return Response::redirect('/lessons/' . $id . '#discussion');
    }

    public function react(Request $request): Response
    {
        $id = (int) $request->param('id');
        $this->discussion->react($id, $this->uid(), $this->role(), $request->input('kind'));

        return Response::redirect($this->back($request) . '#comment-' . $id);
    }

    public function report(Request $request): Response
    {
        $error = $this->discussion->report((int) $request->param('id'), $this->uid(), $this->role(), $request->input('reason'));
        $this->session->flash(...($error === '' ? ['success', 'Thank you. The tutor will look at it.'] : ['error', $error]));

        return Response::redirect($this->back($request) . '#discussion');
    }

    public function follow(Request $request): Response
    {
        $id = (int) $request->param('id');
        $on = $request->input('on') === '1';
        if (!$this->discussion->follow($id, $this->uid(), $this->role(), $on)) {
            throw new HttpException(404);
        }
        $this->session->flash('info', $on ? 'You will be told about new comments.' : 'You will no longer be told about new comments.');

        return Response::redirect('/lessons/' . $id . '#discussion');
    }

    public function moderateComment(Request $request): Response
    {
        $id = (int) $request->param('id');
        $lesson = match ($request->param('action')) {
            'hide' => $this->discussion->setHidden($id, true, $this->uid()),
            'show' => $this->discussion->setHidden($id, false, $this->uid()),
            'approve' => $this->discussion->approve($id, true),
            'unapprove' => $this->discussion->approve($id, false),
            default => throw new HttpException(404),
        } ?? throw new HttpException(404);

        return Response::redirect($request->input('back') === 'community' ? '/studio/community' : '/lessons/' . $lesson . '#comment-' . $id);
    }

    public function exitTicket(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->discussion->lessonFor($id, $this->uid(), $this->role()) === null) {
            throw new HttpException(404);
        }
        $error = $this->progress->saveTicket($id, $this->uid(), (int) $request->input('understanding'), $request->input('real_life_use'), $request->input('still_unclear'));
        $this->session->flash(...($error === '' ? ['success', 'Thank you! The tutor reads every exit ticket.'] : ['error', $error]));

        return Response::redirect('/lessons/' . $id . '#exit-ticket');
    }

    public function tag(Request $request): Response
    {
        $tag = mb_strtolower($request->param('tag'));
        if (!preg_match('/^[a-z0-9_]{2,30}$/', $tag)) {
            throw new HttpException(404);
        }

        return Response::html($this->view->render('community/tag', ['title' => '#' . $tag, 'tag' => $tag, 'comments' => $this->discussion->tagged($tag, $this->uid(), $this->role())]));
    }

    /* ---------- The student's community page ---------- */

    public function hub(Request $request): Response
    {
        return Response::html($this->view->render('community/index', [
            'title' => 'Community',
            'badges' => $this->progress->refresh($this->uid()),
            'allBadges' => ProgressService::BADGES,
            'challenges' => $this->challenges->forStudent($this->uid()),
            'wall' => array_slice($this->challenges->wall(), 0, 6),
            'names' => Subjects::NAMES,
        ]));
    }

    public function challenge(Request $request): Response
    {
        $v = $this->challenges->view((int) $request->param('id'), $this->uid(), $this->role()) ?? throw new HttpException(404);

        return Response::html($this->view->render('community/challenge', ['title' => (string) $v['challenge']['title'], 'v' => $v, 'names' => Subjects::NAMES]));
    }

    public function answer(Request $request): Response
    {
        $id = (int) $request->param('id');
        $error = $this->challenges->answer($id, $this->uid(), $request->input('body'));
        $this->session->flash(...($error === '' ? ['success', 'Answer sent! Everyone\'s answers and the explanation appear when the challenge closes.'] : ['error', $error]));

        return Response::redirect('/community/challenges/' . $id);
    }

    public function wall(Request $request): Response
    {
        return $this->wallPage('');
    }

    public function postWall(Request $request): Response
    {
        $error = $this->challenges->post($this->uid(), $request->file('photo'), $request->input('caption'), (int) $request->input('topic_id'));
        if ($error !== '') {
            return $this->wallPage($error, 422);
        }
        $this->session->flash('success', 'Thank you! Your photo appears on the wall once the tutor has looked at it.');

        return Response::redirect('/community/wall');
    }

    public function wallPhoto(Request $request): Response
    {
        $path = $this->challenges->photo((int) $request->param('id'), $this->uid(), $this->role()) ?? throw new HttpException(404);

        return Response::files([$path], 'image/jpeg', (int) filesize($path))->withHeader('Cache-Control', 'private, max-age=86400');
    }

    public function reportWall(Request $request): Response
    {
        $error = $this->challenges->reportPost((int) $request->param('id'), $this->uid(), $request->input('reason'));
        $this->session->flash(...($error === '' ? ['success', 'Thank you. The tutor will look at it.'] : ['error', $error]));

        return Response::redirect('/community/wall');
    }

    /* ---------- The tutor ---------- */

    public function studio(Request $request): Response
    {
        return $this->studioPage([], []);
    }

    public function createChallenge(Request $request): Response
    {
        $in = [];
        foreach (['form', 'subject', 'topic_id', 'title', 'body', 'days'] as $f) {
            $in[$f] = $request->input($f);
        }
        $r = $this->challenges->create($in, $this->uid());
        if ($r['errors'] !== []) {
            return $this->studioPage($r['errors'], $in, 422);
        }
        $this->session->flash('success', 'Challenge posted. Students whose school has taught the topic can answer it now.');

        return Response::redirect('/studio/community#challenges');
    }

    public function explain(Request $request): Response
    {
        $this->challenges->explain((int) $request->param('id'), $request->input('explanation'));
        $this->session->flash('success', 'Explanation saved. Students see it with everyone\'s answers when the challenge closes.');

        return Response::redirect('/community/challenges/' . (int) $request->param('id'));
    }

    public function deleteChallenge(Request $request): Response
    {
        $this->challenges->delete((int) $request->param('id'));
        $this->session->flash('info', 'Challenge deleted.');

        return Response::redirect('/studio/community#challenges');
    }

    public function moderateWall(Request $request): Response
    {
        $this->challenges->moderate((int) $request->param('id'), $request->param('action'), $this->uid());

        return Response::redirect('/studio/community#wall');
    }

    public function keepReport(Request $request): Response
    {
        $this->discussion->keep((int) $request->param('id'), $this->uid());
        $this->session->flash('info', 'Kept as it is. The report is closed.');

        return Response::redirect('/studio/community');
    }

    private function back(Request $request): string
    {
        $b = $request->input('back');

        return preg_match('#^/(lessons/\d+|community/tags/[a-z0-9_]+)$#', $b) ? $b : '/community';
    }

    private function wallPage(string $error, int $status = 200): Response
    {
        return Response::html($this->view->render('community/wall', [
            'title' => 'Spotted in real life',
            'posts' => $this->challenges->wall(),
            'mine' => $this->role() === 'student' ? $this->challenges->myPosts($this->uid()) : [],
            'error' => $error,
            'topicOptions' => $this->topics->options(),
        ]), $status);
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function studioPage(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/community', [
            'title' => 'Community',
            'reports' => $this->discussion->openReports(),
            'pending' => $this->challenges->pendingWall(),
            'challenges' => $this->challenges->all(),
            'errors' => $errors,
            'old' => $old,
            'names' => Subjects::NAMES,
            'topicOptions' => $this->topics->options(),
        ]), $status);
    }
}
