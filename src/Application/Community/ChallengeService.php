<?php

declare(strict_types=1);

namespace SmartCircle\Application\Community;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Application\Settings\SiteAssetService;
use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Data\Repositories\ChallengeRepository;
use SmartCircle\Data\Repositories\DiscussionRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Data\Repositories\TopicRepository;
use SmartCircle\Support\Subjects;
use SmartCircle\Support\UploadedFile;

/**
 * Weekly real-life challenges (answers stay private until the challenge closes, then everyone sees them
 * with the tutor's explanation) and the "Spotted in real life" wall (photos appear only after the tutor
 * approves them). Both follow the honesty rule for topics.
 */
final class ChallengeService
{
    public function __construct(
        private readonly ChallengeRepository $repo,
        private readonly DiscussionRepository $discussion,
        private readonly StudentRepository $students,
        private readonly AccessService $access,
        private readonly TopicRepository $topics,
        private readonly NotificationService $notifications,
        private readonly string $storageRoot,
    ) {
    }

    /* ---------- Challenges ---------- */

    /** @return array{errors: array<string, string>, id: ?int} */
    public function create(array $in, int $tutorId): array
    {
        $form = (int) ($in['form'] ?? 0);
        $subject = (string) ($in['subject'] ?? '');
        $topicId = (int) ($in['topic_id'] ?? 0);
        $title = trim((string) ($in['title'] ?? ''));
        $body = trim(str_replace("\r\n", "\n", (string) ($in['body'] ?? '')));
        $days = (int) ($in['days'] ?? 7);
        $errors = [];
        if ($form < 1 || $form > 4) {
            $errors['form'] = 'Choose a form.';
        }
        if (!isset(Subjects::NAMES[$subject])) {
            $errors['subject'] = 'Choose a subject.';
        }
        if ($topicId > 0) {
            $t = $this->topics->find($topicId);
            if ($t === null || (int) $t['form'] !== $form || $t['subject'] !== $subject) {
                $errors['topic_id'] = 'Choose a topic from the same form and subject (or none).';
            }
        }
        if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
            $errors['title'] = 'Give it a short title.';
        }
        if (mb_strlen($body) < 10) {
            $errors['body'] = 'Describe the real-life problem.';
        }
        if ($days < 1 || $days > 21) {
            $errors['days'] = 'Between 1 and 21 days.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $id = $this->repo->create(['form' => $form, 'subject' => $subject, 'topic_id' => $topicId > 0 ? $topicId : null, 'title' => $title, 'body' => $body,
            'explanation' => null, 'opens_at' => date('Y-m-d H:i:s'), 'closes_at' => date('Y-m-d 23:59:59', strtotime('+' . $days . ' days'))], $tutorId);

        return ['errors' => [], 'id' => $id];
    }

    public function explain(int $id, string $text): void
    {
        $this->repo->setExplanation($id, trim($text));
    }

    public function delete(int $id): void
    {
        $this->repo->delete($id);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->repo->all();
    }

    /** @return list<array<string, mixed>> challenges open to a student (their forms, subjects, taught topics) */
    public function forStudent(int $userId): array
    {
        $profile = $this->access->withRevision($this->students->profile($userId));
        $decide = $this->access->forStudent($userId);
        if ($profile === null || $decide === null) {
            return [];
        }
        $subjects = Subjects::forTrack((string) $profile['track']);

        return array_values(array_filter($this->repo->forForms($profile['forms']), static fn (array $c): bool => in_array($c['subject'], $subjects, true)
            && ($c['topic_id'] === null || ($decide((int) $c['topic_id'])['open'] ?? false))));
    }

    /** @return array<string, mixed>|null the challenge, my answer, and (once closed) everyone's answers */
    public function view(int $id, int $userId, string $role): ?array
    {
        $c = $this->repo->find($id);
        if ($c === null) {
            return null;
        }
        if ($role === 'student' && !in_array($id, array_map(static fn (array $x): int => (int) $x['id'], $this->forStudent($userId)), true)) {
            return null;
        }
        $closed = strtotime((string) $c['closes_at']) < time();
        $answers = $this->discussion->thread('challenge', $id, false);
        $mine = array_values(array_filter($answers, static fn (array $a): bool => (int) $a['user_id'] === $userId));
        foreach ($answers as &$a) {
            $a['author'] = DiscussionService::displayName($a);
            $a['html'] = DiscussionService::format((string) $a['body']);
        }
        unset($a);

        return ['challenge' => $c, 'closed' => $closed, 'mine' => $mine[0] ?? null,
            'answers' => $closed || $role === 'tutor' ? $answers : [], 'count' => count($answers)];
    }

    /** @return string error */
    public function answer(int $id, int $userId, string $body): string
    {
        $v = $this->view($id, $userId, 'student');
        if ($v === null) {
            return 'This challenge is not open to you.';
        }
        if ($v['closed']) {
            return 'This challenge has closed. See everyone\'s answers below.';
        }
        if ($v['mine'] !== null) {
            return 'You have already answered. Wait for the challenge to close to see the explanation.';
        }
        $body = trim(str_replace("\r\n", "\n", $body));
        if (mb_strlen($body) < 5 || mb_strlen($body) > 2000) {
            return 'Write your answer (up to 2000 characters).';
        }
        $this->discussion->add('challenge', $id, null, $userId, $body, null);

        return '';
    }

    /* ---------- The wall ---------- */

    /** @return string error */
    public function post(int $userId, ?UploadedFile $photo, string $caption, int $topicId): string
    {
        $caption = trim($caption);
        if (mb_strlen($caption) < 5 || mb_strlen($caption) > 500) {
            return 'Say what you spotted and where (5 to 500 characters).';
        }
        if ($this->repo->postsToday($userId) >= 3) {
            return 'Three photos a day is the limit. Please come back tomorrow.';
        }
        if ($photo === null || !$photo->ok() || $photo->size > 8 * 1024 * 1024 || !in_array($photo->mime(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return 'Choose a JPG, PNG or WebP photo (up to 8 MB).';
        }
        $jpg = SiteAssetService::photoJpeg((string) file_get_contents($photo->tmpPath));
        if ($jpg === null) {
            return 'That photo could not be read. Please try another.';
        }
        $rel = 'uploads/wall/' . $userId . '-' . bin2hex(random_bytes(6)) . '.jpg';
        $path = rtrim($this->storageRoot, '/') . '/' . $rel;
        if ((!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true) && !is_dir(dirname($path))) || file_put_contents($path, $jpg) === false) {
            return 'The photo could not be saved. Please try again.';
        }
        $t = $topicId > 0 ? $this->topics->find($topicId) : null;
        $this->repo->addPost($userId, $t !== null ? (int) $t['id'] : null, $caption, $rel);
        $this->notifications->notifyTutors('wall', 'A new photo for the wall is waiting', mb_substr($caption, 0, 160), '/studio/community#wall');

        return '';
    }

    /** @return list<array<string, mixed>> */
    public function wall(): array
    {
        return array_map(static fn (array $p): array => $p + ['author' => DiscussionService::displayName($p + ['role' => 'student', 'author_form' => $p['form']])], $this->repo->posts('visible'));
    }

    /** @return list<array<string, mixed>> */
    public function pendingWall(): array
    {
        return $this->repo->posts('pending');
    }

    /** @return list<array<string, mixed>> */
    public function myPosts(int $userId): array
    {
        return $this->repo->myPosts($userId);
    }

    public function moderate(int $postId, string $action, int $tutorId): void
    {
        $p = $this->repo->post($postId);
        if ($p === null) {
            return;
        }
        match ($action) {
            'approve' => $this->repo->setPost($postId, 'visible', (bool) $p['featured']),
            'feature' => $this->repo->setPost($postId, 'visible', true),
            'unfeature' => $this->repo->setPost($postId, 'visible', false),
            default => $this->repo->setPost($postId, 'hidden', false),
        };
        $this->discussion->resolveReports('wall_post', $postId, $tutorId);
        if ($action === 'approve' && $p['status'] === 'pending') {
            $this->notifications->notify([(int) $p['user_id']], 'wall', 'Your photo is on the wall!', mb_substr((string) $p['caption'], 0, 160), '/community/wall');
        }
        if ($action === 'feature') {
            $this->notifications->notify([(int) $p['user_id']], 'wall', 'Your photo is featured on the wall. Brilliant!', mb_substr((string) $p['caption'], 0, 160), '/community/wall');
        }
    }

    /** @return string error */
    public function reportPost(int $postId, int $userId, string $reason): string
    {
        $p = $this->repo->post($postId);
        if ($p === null || $p['status'] !== 'visible' || mb_strlen(trim($reason)) < 3) {
            return 'Please say briefly what is wrong.';
        }
        $this->discussion->report('wall_post', $postId, $userId, mb_substr(trim($reason), 0, 255));
        $this->notifications->notifyTutors('report', 'A wall photo was reported', mb_substr($reason, 0, 200), '/studio/community');

        return '';
    }

    /** @return string|null a wall photo's file: visible to members, pending/hidden only to its author and the tutor */
    public function photo(int $postId, int $userId, string $role): ?string
    {
        $p = $this->repo->post($postId);
        if ($p === null || $p['photo_path'] === null || ($p['status'] !== 'visible' && $role !== 'tutor' && (int) $p['user_id'] !== $userId)) {
            return null;
        }
        $path = rtrim($this->storageRoot, '/') . '/' . $p['photo_path'];

        return is_file($path) ? $path : null;
    }
}
