<?php

declare(strict_types=1);

namespace SmartCircle\Application\Community;

use SmartCircle\Application\Lessons\LessonService;
use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Application\Topics\TopicService;
use SmartCircle\Data\Repositories\DiscussionRepository;

/**
 * Questions and comments under a lesson. Only people who may watch the lesson take part (the honesty
 * rule applies). Students appear by first name and form. @Name mentions someone already in the
 * discussion (or @tutor); #words become tags. Anyone can report a comment; the tutor can hide it or
 * mark an answer "Tutor approved". Everyone who comments follows the discussion.
 */
final class DiscussionService
{
    public const MAX_PER_DAY = 30;
    public const REACTIONS = ['same_question' => 'Same question', 'thanks' => 'Thanks'];

    public function __construct(
        private readonly DiscussionRepository $repo,
        private readonly LessonService $lessons,
        private readonly AccessService $access,
        private readonly TopicService $topics,
        private readonly NotificationService $notifications,
    ) {
    }

    /** @return array<string, mixed>|null the lesson, when this person may take part in its discussion */
    public function lessonFor(int $lessonId, int $userId, string $role): ?array
    {
        $lesson = $this->lessons->find($lessonId);
        if ($lesson === null || !$this->lessons->canWatch($lesson, $role)) {
            return null;
        }
        if ($role !== 'tutor' && !empty($lesson['topic_id'])) {
            $topic = $this->topics->find((int) $lesson['topic_id']);
            if ($topic !== null && !$this->access->check($userId, $role, $topic)['open']) {
                return null;
            }
        }

        return $lesson;
    }

    /** @return array{comments: list<array<string, mixed>>, mine: list<string>, following: bool} */
    public function thread(int $lessonId, int $userId, string $role): array
    {
        $rows = $this->repo->thread('lesson', $lessonId, $role === 'tutor');
        $byParent = [];
        foreach ($rows as $r) {
            $r['author'] = self::displayName($r);
            $r['html'] = self::format((string) $r['body']);
            $byParent[(int) ($r['parent_id'] ?? 0)][] = $r;
        }
        $top = [];
        foreach ($byParent[0] ?? [] as $c) {
            $c['replies'] = $byParent[(int) $c['id']] ?? [];
            $top[] = $c;
        }

        return ['comments' => $top, 'mine' => $this->repo->myReactions('lesson', $lessonId, $userId), 'following' => $this->repo->follows($userId, 'lesson', (string) $lessonId)];
    }

    /** @return string error ('' when posted) */
    public function post(int $lessonId, int $userId, string $role, string $name, string $body, ?int $parentId, string $time): string
    {
        $lesson = $this->lessonFor($lessonId, $userId, $role);
        if ($lesson === null) {
            return 'You cannot take part in this discussion.';
        }
        $body = trim(str_replace("\r\n", "\n", $body));
        if (mb_strlen($body) < 2) {
            return 'Write your question or comment first.';
        }
        if (mb_strlen($body) > 2000) {
            return 'Please keep it under 2000 characters.';
        }
        if ($role !== 'tutor' && $this->repo->countToday($userId) >= self::MAX_PER_DAY) {
            return 'That is a lot of comments for one day. Please come back tomorrow.';
        }
        if ($parentId !== null) {
            $parent = $this->repo->comment($parentId);
            if ($parent === null || $parent['target_type'] !== 'lesson' || (int) $parent['target_id'] !== $lessonId || $parent['parent_id'] !== null) {
                return 'That comment is not in this discussion.';
            }
        }
        $timeMs = self::parseTime($time);
        $id = $this->repo->add('lesson', $lessonId, $parentId, $userId, $body, $timeMs);
        $this->repo->tag('comment', $id, self::hashtags($body));
        $this->repo->follow($userId, 'lesson', (string) $lessonId, true);

        // Who hears about it: the tutor (new questions), people mentioned, and followers of the discussion.
        $first = explode(' ', trim($name))[0];
        $link = '/lessons/' . $lessonId . '#comment-' . $id;
        $mentioned = $this->mentions($lessonId, $body, $userId);
        foreach ($mentioned as $m) {
            $this->repo->addMention($id, $m);
        }
        $this->notifications->notify($mentioned, 'mention', $first . ' mentioned you: ' . $lesson['title'], mb_substr($body, 0, 200), $link);
        $followers = array_values(array_diff($this->repo->followers('lesson', (string) $lessonId), [$userId], $mentioned));
        $this->notifications->notify($followers, 'discussion', 'New in the discussion: ' . $lesson['title'], $first . ': ' . mb_substr($body, 0, 160), $link);
        if ($role !== 'tutor' && $parentId === null) {
            $this->notifications->notifyTutors('discussion', 'New question on ' . $lesson['title'], $first . ': ' . mb_substr($body, 0, 160), $link);
        }

        return '';
    }

    /** @return string error */
    public function react(int $commentId, int $userId, string $role, string $kind): string
    {
        $c = $this->repo->comment($commentId);
        if (!isset(self::REACTIONS[$kind]) || $c === null || $c['target_type'] !== 'lesson' || $this->lessonFor((int) $c['target_id'], $userId, $role) === null) {
            return 'Not possible.';
        }
        $this->repo->toggleReaction($commentId, $userId, $kind);

        return '';
    }

    /** @return string error */
    public function report(int $commentId, int $userId, string $role, string $reason): string
    {
        $c = $this->repo->comment($commentId);
        if ($c === null || $c['target_type'] !== 'lesson' || $this->lessonFor((int) $c['target_id'], $userId, $role) === null) {
            return 'Not possible.';
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            return 'Please say briefly what is wrong.';
        }
        $this->repo->report('comment', $commentId, $userId, mb_substr($reason, 0, 255));
        $this->notifications->notifyTutors('report', 'A comment was reported', mb_substr($reason, 0, 200), '/studio/community');

        return '';
    }

    public function follow(int $lessonId, int $userId, string $role, bool $on): bool
    {
        if ($this->lessonFor($lessonId, $userId, $role) === null) {
            return false;
        }
        $this->repo->follow($userId, 'lesson', (string) $lessonId, $on);

        return true;
    }

    /* ---------- The tutor ---------- */

    public function setHidden(int $commentId, bool $hidden, int $tutorId): ?int
    {
        $c = $this->repo->comment($commentId);
        if ($c === null) {
            return null;
        }
        $this->repo->setStatus($commentId, $hidden ? 'hidden' : 'visible');
        $this->repo->resolveReports('comment', $commentId, $tutorId);

        return (int) $c['target_id'];
    }

    public function approve(int $commentId, bool $on): ?int
    {
        $c = $this->repo->comment($commentId);
        if ($c === null || $c['parent_id'] === null) {
            return null;
        }
        $this->repo->setApproved($commentId, $on);
        if ($on) {
            $this->notifications->notify([(int) $c['user_id']], 'discussion', 'The tutor approved your answer. Well done!', mb_substr((string) $c['body'], 0, 160), '/lessons/' . $c['target_id'] . '#comment-' . $commentId);
        }

        return (int) $c['target_id'];
    }

    /** @return list<array<string, mixed>> */
    public function openReports(): array
    {
        return $this->repo->openReports();
    }

    public function keep(int $reportId, int $tutorId): void
    {
        $r = $this->repo->findReport($reportId);
        if ($r !== null) {
            $this->repo->resolveReports((string) $r['target_type'], (int) $r['target_id'], $tutorId);
        }
    }

    /** @return list<array<string, mixed>> comments with a tag, only from lessons this person may open */
    public function tagged(string $tag, int $userId, string $role): array
    {
        $out = [];
        foreach ($this->repo->tagged(mb_strtolower($tag)) as $c) {
            if ($this->lessonFor((int) $c['target_id'], $userId, $role) !== null) {
                $c['author'] = self::displayName($c + ['author_form' => null]);
                $c['html'] = self::format((string) $c['body']);
                $out[] = $c;
            }
        }

        return $out;
    }

    /* ---------- Pure helpers ---------- */

    /** "Chikondi (Form 2)", "Tutor", "Teacher Grace". Pure. @param array<string, mixed> $r */
    public static function displayName(array $r): string
    {
        $first = explode(' ', trim((string) $r['full_name']))[0];

        return match ($r['role'] ?? '') {
            'tutor' => 'Tutor',
            'team' => $first . ' (SMART Circle Team)',
            'partner' => 'Teacher ' . $first,
            default => $first . (!empty($r['author_form']) ? ' (Form ' . $r['author_form'] . ')' : ''),
        };
    }

    /** Escaped text with #tags linked and @mentions highlighted. Pure. */
    public static function format(string $body): string
    {
        $s = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $s = (string) preg_replace('/(^|\s)#([a-z0-9_]{2,30})\b/iu', '$1<a class="tag-link" href="/community/tags/$2">#$2</a>', $s);
        $s = (string) preg_replace('/(^|\s)@([\p{L}\'-]{2,30})/u', '$1<strong class="mention">@$2</strong>', $s);

        return nl2br($s, false);
    }

    /** @return list<string> lowercase tags in a text. Pure. */
    public static function hashtags(string $body): array
    {
        preg_match_all('/(?:^|\s)#([a-z0-9_]{2,30})\b/iu', $body, $m);

        return array_values(array_unique(array_map('mb_strtolower', $m[1])));
    }

    /** "12:30" or "750" (seconds) to milliseconds; empty or bad = null. Pure. */
    public static function parseTime(string $time): ?int
    {
        $time = trim($time);
        if (preg_match('/^(\d{1,3}):([0-5]\d)$/', $time, $m)) {
            return ((int) $m[1] * 60 + (int) $m[2]) * 1000;
        }

        return null;
    }

    /** @return list<int> users mentioned with @FirstName (people in the discussion) or @tutor */
    private function mentions(int $lessonId, string $body, int $author): array
    {
        preg_match_all('/(?:^|\s)@([\p{L}\'-]{2,30})/u', $body, $m);
        if ($m[1] === []) {
            return [];
        }
        $wanted = array_map('mb_strtolower', $m[1]);
        $ids = [];
        foreach ($this->repo->participants('lesson', $lessonId) as $p) {
            if (in_array(mb_strtolower(explode(' ', trim((string) $p['full_name']))[0]), $wanted, true)) {
                $ids[] = (int) $p['id'];
            }
        }
        if (in_array('tutor', $wanted, true)) {
            $ids = array_merge($ids, $this->notifications->tutorIds());
        }

        return array_values(array_diff(array_unique($ids), [$author]));
    }
}
