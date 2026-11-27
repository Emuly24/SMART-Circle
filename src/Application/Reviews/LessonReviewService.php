<?php

declare(strict_types=1);

namespace SmartCircle\Application\Reviews;

use SmartCircle\Application\Lessons\LessonService;
use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\LessonReviewRepository;
use SmartCircle\Data\Repositories\ReviewRepository;
use SmartCircle\Support\Subjects;

/**
 * Teachers check recorded lessons as they check notes: they watch the replay, read the real-life sections
 * and open the PDF handout, then approve with their signature or suggest changes. An approval covers the
 * lesson exactly as checked (recording, details and handout); any change needs a new check.
 */
final class LessonReviewService
{
    public function __construct(
        private readonly LessonReviewRepository $reviews,
        private readonly ReviewRepository $partners,
        private readonly NotificationService $notifications,
        private readonly string $storageRoot,
    ) {
    }

    /** @return array{sent: int, error: string} */
    public function requestReview(int $lessonId): array
    {
        $l = $this->reviews->lesson($lessonId);
        if ($l === null || $l['subject'] === null) {
            return ['sent' => 0, 'error' => 'Choose the lesson\'s form and subject first.'];
        }
        $this->reviews->requestReview($lessonId);
        $teachers = $this->partners->reviewersFor((string) $l['subject']);
        if ($teachers === []) {
            return ['sent' => 0, 'error' => 'Saved, but no partner teacher teaches ' . (Subjects::NAMES[$l['subject']] ?? $l['subject']) . ' yet.'];
        }
        $this->notifications->notify($teachers, 'review', 'Please check a lesson: ' . $l['title'],
            'Form ' . $l['form'] . ' ' . (Subjects::NAMES[$l['subject']] ?? '') . '. Watch it, then approve it with your signature or suggest changes.', '/partner/reviews/lessons/' . $lessonId);

        return ['sent' => count($teachers), 'error' => ''];
    }

    /** @return list<array<string, mixed>> lessons sent for checking in the teacher's subjects, each with its state */
    public function queue(int $userId): array
    {
        $me = $this->partners->reviewer($userId);
        if ($me === null) {
            return [];
        }
        $subjects = array_filter(explode(',', (string) $me['subjects']));
        $out = [];
        foreach ($this->reviews->requested() as $l) {
            if (in_array($l['subject'], $subjects, true)) {
                $out[] = $l + ['state' => $this->state((int) $l['id'])];
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function forReviewer(int $userId, int $lessonId): ?array
    {
        $me = $this->partners->reviewer($userId);
        $l = $this->reviews->lesson($lessonId);
        if ($me === null || $l === null || $l['review_requested_at'] === null || !in_array($l['subject'], array_filter(explode(',', (string) $me['subjects'])), true)) {
            return null;
        }

        return ['lesson' => $l, 'me' => $me, 'state' => $this->state($lessonId), 'history' => $this->reviews->forLesson($lessonId)];
    }

    /** @return array{errors: array<string, string>, decision: string} */
    public function decide(int $userId, int $lessonId, string $decision, string $comment): array
    {
        $r = $this->forReviewer($userId, $lessonId) ?? throw new \DomainException('Not yours to check.');
        $comment = trim($comment);
        $errors = [];
        if (!in_array($decision, ['approved', 'changes'], true)) {
            $errors['decision'] = 'Choose whether the lesson is accurate or needs changes.';
        } elseif ($decision === 'changes' && mb_strlen($comment) < 5) {
            $errors['comment'] = 'Please say what should change, so the tutor can fix it.';
        } elseif ($decision === 'approved' && $r['me']['signature_path'] === null) {
            $errors['decision'] = 'Please add your signature first.';
        }
        if (mb_strlen($comment) > 3000) {
            $errors['comment'] = 'Please keep it under 3000 characters.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'decision' => $decision];
        }
        $this->reviews->add([
            'lesson_id' => $lessonId, 'reviewer_id' => $userId, 'decision' => $decision, 'comment' => $comment === '' ? null : $comment,
            'content_hash' => self::hash($r['lesson']), 'reviewer_name' => (string) $r['me']['full_name'], 'school_name' => $r['me']['school_name'],
            'signature_path' => $decision === 'approved' ? $r['me']['signature_path'] : null,
        ]);
        $title = ($decision === 'approved' ? 'Lesson approved by ' : 'Changes suggested by ') . $r['me']['full_name'] . ': ' . $r['lesson']['title'];
        $this->notifications->notifyTutors('review', $title, $comment === '' ? null : $comment, '/studio/lessons/' . $lessonId . '#reviews');

        return ['errors' => [], 'decision' => $decision];
    }

    /** @return array{state: string, review: ?array<string, mixed>} none | requested | changes | approved | stale */
    public function state(int $lessonId): array
    {
        $latest = $this->reviews->forLesson($lessonId)[0] ?? null;
        $l = $this->reviews->lesson($lessonId);
        if ($latest === null) {
            return ['state' => $l !== null && $l['review_requested_at'] !== null ? 'requested' : 'none', 'review' => null];
        }
        if ($l === null || $latest['content_hash'] !== self::hash($l)) {
            return ['state' => $latest['decision'] === 'approved' ? 'stale' : 'requested', 'review' => $latest];
        }

        return ['state' => (string) $latest['decision'], 'review' => $latest];
    }

    /** @return array<string, mixed>|null */
    public function approval(int $lessonId): ?array
    {
        $s = $this->state($lessonId);

        return $s['state'] === 'approved' ? $s['review'] : null;
    }

    /** @return list<array<string, mixed>> */
    public function history(int $lessonId): array
    {
        return $this->reviews->forLesson($lessonId);
    }

    /** @return array{path: string}|null the signature of a lesson's current approval */
    public function approvalSignature(int $reviewId): ?array
    {
        $r = $this->reviews->find($reviewId);
        if ($r === null || $r['signature_path'] === null) {
            return null;
        }
        $current = $this->approval((int) $r['lesson_id']);
        $path = rtrim($this->storageRoot, '/') . '/' . $r['signature_path'];

        return $current !== null && (int) $current['id'] === $reviewId && is_file($path) ? ['path' => $path] : null;
    }

    /** @return string|null the lesson's PDF handout, for the teacher checking it */
    public function handoutFor(int $userId, int $lessonId): ?string
    {
        $r = $this->forReviewer($userId, $lessonId);
        $rel = $r['lesson']['handout_path'] ?? null;
        $path = $rel !== null ? rtrim($this->storageRoot, '/') . '/' . $rel : null;

        return $path !== null && is_file($path) ? $path : null;
    }

    /** A fingerprint of what a teacher checks: the recording, its details and the handout. Pure. @param array<string, mixed> $l */
    public static function hash(array $l): string
    {
        $parts = [(string) ($l['sha256'] ?? ''), (string) ($l['title'] ?? ''), (string) ($l['form'] ?? ''), (string) ($l['subject'] ?? ''),
            (string) ($l['topic_id'] ?? ''), (string) ($l['summary'] ?? ''), (string) ($l['handout_path'] ?? '')];
        foreach (array_keys(LessonService::REAL_LIFE_SECTIONS) as $f) {
            $parts[] = (string) ($l[$f] ?? '');
        }

        return hash('sha256', implode("\x1E", $parts));
    }
}
