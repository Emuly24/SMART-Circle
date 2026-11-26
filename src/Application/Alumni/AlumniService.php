<?php

declare(strict_types=1);

namespace SmartCircle\Application\Alumni;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\AlumniRepository;
use SmartCircle\Data\Repositories\CommunityRepository;

/**
 * The alumni community: what each former student is doing now, what help they need (selection,
 * applications, loans, choosing a course, careers), who can mentor, and a moderated board.
 * Posts and replies are never deleted; the tutor can hide them.
 */
final class AlumniService
{
    public const NOW_DOING = [
        'waiting' => 'Waiting for selection / results',
        'university' => 'At university',
        'college' => 'At a college',
        'tvet' => 'At a technical (TEVET) college',
        'working' => 'Working or running a business',
        'repeating' => 'Repeating Form 4',
        'other' => 'Something else',
    ];
    public const NEEDS = [
        'selection' => 'University or college selection',
        'applications' => 'Filling in applications',
        'loans' => 'Student loans and grants',
        'course_choice' => 'Choosing a course',
        'career' => 'Careers and first jobs',
        'mentoring' => 'Someone to talk to now and then',
    ];
    public const KINDS = ['question' => 'Question', 'opportunity' => 'Opportunity', 'story' => 'My story'];

    public function __construct(
        private readonly AlumniRepository $alumni,
        private readonly CommunityRepository $community,
        private readonly NotificationService $notifications,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function profile(int $userId): ?array
    {
        return $this->alumni->profile($userId);
    }

    /**
     * @param array<string, mixed> $in now_doing, institution, programme, needs[], can_mentor, mentor_note
     * @return array<string, string> errors
     */
    public function saveProfile(int $userId, array $in): array
    {
        $errors = [];
        $doing = (string) ($in['now_doing'] ?? '');
        if ($doing !== '' && !isset(self::NOW_DOING[$doing])) {
            $errors['now_doing'] = 'Choose what you are doing now.';
        }
        $text = static fn (string $k, int $max): ?string => ($v = trim((string) ($in[$k] ?? ''))) === '' ? null : mb_substr($v, 0, $max);
        $needs = array_values(array_intersect(array_keys(self::NEEDS), array_map('strval', (array) ($in['needs'] ?? []))));
        if ($errors !== []) {
            return $errors;
        }
        $this->alumni->saveProfile($userId, [
            'now_doing' => $doing === '' ? null : $doing,
            'institution' => $text('institution', 150),
            'programme' => $text('programme', 150),
            'needs' => $needs === [] ? null : implode(',', $needs),
            'can_mentor' => !empty($in['can_mentor']) ? 1 : 0,
            'mentor_note' => $text('mentor_note', 300),
        ]);
        if ($needs !== []) {
            $this->notifications->notifyTutors('alumni_needs', 'An alumnus asked for help', 'Needs: ' . implode(', ', array_map(static fn (string $n): string => self::NEEDS[$n], $needs)), '/studio/alumni');
        }

        return [];
    }

    /** @return list<array<string, mixed>> */
    public function everyone(): array
    {
        return $this->alumni->all();
    }

    /* ---------- The board ---------- */

    /** Who sees which posts: alumni and the tutor see the board; Form 3-4 students see posts shared with them. */
    public static function mayRead(string $role, ?int $form): bool
    {
        return in_array($role, ['alumni', 'tutor'], true) || ($role === 'student' && $form !== null && $form >= 3);
    }

    public static function mayWrite(string $role): bool
    {
        return in_array($role, ['alumni', 'tutor'], true);
    }

    /** @return list<array<string, mixed>> */
    public function posts(string $role): array
    {
        return $this->community->posts($role === 'tutor', $role === 'student');
    }

    /** @return array<string, mixed>|null post with replies, or null if this viewer may not see it */
    public function post(int $id, string $role): ?array
    {
        $p = $this->community->post($id);
        if ($p === null || ($p['status'] !== 'visible' && $role !== 'tutor') || ($role === 'student' && !(bool) $p['for_students'])) {
            return null;
        }

        return ['post' => $p, 'replies' => $this->community->replies($id, $role === 'tutor')];
    }

    /** @return array{errors: array<string, string>, id: int|null} */
    public function createPost(int $userId, string $role, string $kind, string $title, string $body): array
    {
        $errors = [];
        if (!self::mayWrite($role)) {
            $errors['body'] = 'Only alumni and the tutor can post here.';
        }
        if (!isset(self::KINDS[$kind])) {
            $errors['kind'] = 'Choose what kind of post it is.';
        }
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 150) {
            $errors['title'] = 'Give it a short title (up to 150 characters).';
        }
        if (($e = self::checkBody($body)) !== null) {
            $errors['body'] = $e;
        }
        if ($errors === [] && $this->community->postsToday($userId) >= 20) {
            $errors['body'] = 'That is a lot for one day. Please continue tomorrow.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $id = $this->community->createPost($userId, $kind, $title, trim($body));
        $others = array_values(array_filter($this->community->memberIds(), static fn (int $u): bool => $u !== $userId));
        $this->notifications->notify($others, 'community', 'Alumni community: ' . $title, null, '/alumni/community/' . $id);

        return ['errors' => [], 'id' => $id];
    }

    /** @return string|null error */
    public function reply(int $postId, int $userId, string $role, string $body): ?string
    {
        $p = $this->post($postId, $role);
        if ($p === null || !self::mayWrite($role) || $p['post']['status'] !== 'visible') {
            return 'You cannot reply here.';
        }
        if (($e = self::checkBody($body)) !== null) {
            return $e;
        }
        if ($this->community->postsToday($userId) >= 20) {
            return 'That is a lot for one day. Please continue tomorrow.';
        }
        $this->community->reply($postId, $userId, trim($body));
        $author = $p['post']['user_id'] !== null ? (int) $p['post']['user_id'] : null;
        if ($author !== null && $author !== $userId) {
            $this->notifications->notify([$author], 'community', 'New reply: ' . $p['post']['title'], null, '/alumni/community/' . $postId);
        }

        return null;
    }

    public static function checkBody(string $body): ?string
    {
        $body = trim($body);
        if ($body === '') {
            return 'Write something first.';
        }

        return mb_strlen($body) > 4000 ? 'Keep it under 4000 characters.' : null;
    }

    /** Tutor moderation: hide or show a post or reply, or share a post with Form 3-4 students. Returns the post id. */
    public function moderate(string $what, int $id, string $action): ?int
    {
        if ($what === 'post') {
            if ($this->community->post($id) === null) {
                return null;
            }
            match ($action) {
                'hide' => $this->community->setPostStatus($id, 'hidden'),
                'show' => $this->community->setPostStatus($id, 'visible'),
                'share' => $this->community->setForStudents($id, true),
                'unshare' => $this->community->setForStudents($id, false),
                default => null,
            };

            return $id;
        }

        return in_array($action, ['hide', 'show'], true) ? $this->community->setReplyStatus($id, $action === 'hide' ? 'hidden' : 'visible') : null;
    }
}
