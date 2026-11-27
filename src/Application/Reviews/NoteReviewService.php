<?php

declare(strict_types=1);

namespace SmartCircle\Application\Reviews;

use SmartCircle\Application\Learning\Quick5Service;
use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\ReviewRepository;
use SmartCircle\Support\Subjects;
use SmartCircle\Support\UploadedFile;

/**
 * Qualified teachers (school partners) check notes in the subjects they teach. They sign once; an approval
 * shows their name, school and signature on the note, its library handout and its printout, but only while
 * the note is exactly as they checked it. Every decision is kept.
 */
final class NoteReviewService
{
    public const MAX_SIGNATURE_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private readonly ReviewRepository $reviews,
        private readonly NotificationService $notifications,
        private readonly string $storageRoot,
    ) {
    }

    /* ---------- The teacher's signature ---------- */

    /** @return array<string, mixed>|null */
    public function reviewer(int $userId): ?array
    {
        $r = $this->reviews->reviewer($userId);
        if ($r !== null) {
            $r['subject_list'] = array_values(array_filter(explode(',', (string) $r['subjects'])));
        }

        return $r;
    }

    /**
     * Saves a drawn signature (PNG data URL) or a photo of one. The picture is redrawn as a clean PNG,
     * so nothing but the picture itself is kept.
     * @return string error ('' when saved)
     */
    public function saveSignature(int $userId, string $drawn, ?UploadedFile $photo, bool $consent): string
    {
        if ($this->reviews->reviewer($userId) === null) {
            return 'Only school partner teachers can sign.';
        }
        if (!$consent) {
            return 'Please tick the box to agree that your name, school and signature may be shown on notes you approve.';
        }
        $bytes = '';
        if ($photo !== null && $photo->ok()) {
            if ($photo->size > self::MAX_SIGNATURE_BYTES || !in_array($photo->mime(), ['image/png', 'image/jpeg', 'image/webp'], true)) {
                return 'Use a JPG, PNG or WebP photo of your signature, smaller than 2 MB.';
            }
            $bytes = (string) file_get_contents($photo->tmpPath);
        } elseif (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $drawn, $m)) {
            $bytes = (string) base64_decode($m[1], true);
        }
        if ($bytes === '' || strlen($bytes) > self::MAX_SIGNATURE_BYTES) {
            return 'Draw your signature in the box, or upload a photo of it.';
        }
        $png = self::cleanPng($bytes);
        if ($png === null) {
            return 'That signature could not be read. Please draw it again.';
        }
        $relative = 'uploads/signatures/' . $userId . '-' . bin2hex(random_bytes(6)) . '.png';
        $path = rtrim($this->storageRoot, '/') . '/' . $relative;
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true) && !is_dir(dirname($path))) {
            return 'The signature could not be saved. Please try again.';
        }
        if (file_put_contents($path, $png) === false) {
            return 'The signature could not be saved. Please try again.';
        }
        // Earlier signatures stay on disk: notes approved before keep the signature they were signed with.
        $this->reviews->setSignature($userId, $relative);

        return '';
    }

    /** Redraws any picture as a PNG at most 600 x 240, keeping transparency. Pure apart from GD. */
    public static function cleanPng(string $bytes): ?string
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 20 || $info[1] < 10 || $info[0] * $info[1] > 25_000_000) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, 600 / $w, 240 / $h);
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagepng($dst, null, 9);

        return (string) ob_get_clean();
    }

    public function signaturePath(int $userId): ?string
    {
        $r = $this->reviews->reviewer($userId);

        return $r !== null && $r['signature_path'] !== null ? $this->file((string) $r['signature_path']) : null;
    }

    /* ---------- The tutor asks ---------- */

    /** @return array{sent: int, error: string} */
    public function requestReview(int $noteId): array
    {
        $note = $this->reviews->note($noteId);
        if ($note === null) {
            return ['sent' => 0, 'error' => 'Notes not found.'];
        }
        $teachers = $this->reviews->reviewersFor((string) $note['subject']);
        $this->reviews->requestReview($noteId);
        if ($teachers === []) {
            return ['sent' => 0, 'error' => 'Saved, but no partner teacher teaches ' . (Subjects::NAMES[$note['subject']] ?? $note['subject'])
                . ' yet. It will be waiting for the first one you invite.'];
        }
        $this->notifications->notify($teachers, 'review', 'Please check: ' . $note['title'],
            'Form ' . $note['form'] . ' ' . (Subjects::NAMES[$note['subject']] ?? '') . '. Approve it with your signature or suggest changes.', '/partner/reviews/' . $noteId);

        return ['sent' => count($teachers), 'error' => ''];
    }

    /* ---------- The teacher checks ---------- */

    /** @return array{waiting: list<array<string, mixed>>, done: list<array<string, mixed>>} notes in the teacher's subjects */
    public function queue(int $userId): array
    {
        $me = $this->reviewer($userId);
        $out = ['waiting' => [], 'done' => []];
        if ($me === null) {
            return $out;
        }
        foreach ($this->reviews->requested() as $n) {
            if (!in_array($n['subject'], $me['subject_list'], true)) {
                continue;
            }
            $n['state'] = $this->state((int) $n['id']);
            $out[$n['state']['state'] === 'approved' ? 'done' : 'waiting'][] = $n;
        }

        return $out;
    }

    /** The note as the teacher sees it: text, pictures and each exercise with its answers. @return array<string, mixed>|null */
    public function forReviewer(int $userId, int $noteId): ?array
    {
        $me = $this->reviewer($userId);
        $note = $this->reviews->note($noteId);
        if ($me === null || $note === null || $note['review_requested_at'] === null || !in_array($note['subject'], $me['subject_list'], true)) {
            return null;
        }
        $blocks = [];
        foreach ($this->reviews->blocks($noteId) as $b) {
            if ($b['type'] === 'checkpoint') {
                $b['questions'] = array_map(static function (array $q): array {
                    $data = json_decode((string) $q['data'], true) ?: [];

                    return $q + ['options' => (array) ($data['options'] ?? []), 'answer' => Quick5Service::rightAnswer((string) $q['type'], $data)];
                }, $b['assessment_id'] !== null ? $this->reviews->questions((int) $b['assessment_id']) : []);
            }
            $blocks[] = $b;
        }

        return ['note' => $note, 'blocks' => $blocks, 'me' => $me, 'state' => $this->state($noteId), 'history' => $this->reviews->forNote($noteId)];
    }

    /** A picture in a note the teacher is checking. */
    public function reviewImage(int $userId, int $noteId, int $blockId): ?string
    {
        $r = $this->forReviewer($userId, $noteId);
        foreach ($r['blocks'] ?? [] as $b) {
            if ((int) $b['id'] === $blockId && $b['type'] === 'image' && $b['image_path'] !== null) {
                return $this->file((string) $b['image_path']);
            }
        }

        return null;
    }

    /** @return array{errors: array<string, string>, decision: string} */
    public function decide(int $userId, int $noteId, string $decision, string $comment): array
    {
        $r = $this->forReviewer($userId, $noteId) ?? throw new \DomainException('Not yours to check.');
        $comment = trim($comment);
        $errors = [];
        if (!in_array($decision, ['approved', 'changes'], true)) {
            $errors['decision'] = 'Choose whether the notes are accurate or need changes.';
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
        $note = $r['note'];
        $this->reviews->add([
            'note_id' => $noteId, 'reviewer_id' => $userId, 'decision' => $decision, 'comment' => $comment === '' ? null : $comment,
            'content_hash' => $this->hash($noteId), 'reviewer_name' => (string) $r['me']['full_name'],
            'school_name' => $r['me']['school_name'], 'signature_path' => $decision === 'approved' ? $r['me']['signature_path'] : null,
        ]);
        $title = ($decision === 'approved' ? 'Approved by ' : 'Changes suggested by ') . $r['me']['full_name'] . ': ' . $note['title'];
        $this->notifications->notifyTutors('review', $title, $comment === '' ? null : $comment, '/studio/notes/' . $noteId . '#reviews');
        if ($note['created_by'] !== null) {
            $this->notifications->notify(array_values(array_diff([(int) $note['created_by']], [$userId])), 'review', $title, $comment === '' ? null : $comment, '/studio/notes/' . $noteId . '#reviews');
        }

        return ['errors' => [], 'decision' => $decision];
    }

    /* ---------- What everyone sees ---------- */

    /**
     * none | requested | changes | approved | stale (approved, then changed).
     * @return array{state: string, review: ?array<string, mixed>}
     */
    public function state(int $noteId): array
    {
        $latest = $this->reviews->forNote($noteId)[0] ?? null;
        if ($latest === null) {
            $note = $this->reviews->note($noteId);

            return ['state' => $note !== null && $note['review_requested_at'] !== null ? 'requested' : 'none', 'review' => null];
        }
        if ($latest['content_hash'] !== $this->hash($noteId)) {
            return ['state' => $latest['decision'] === 'approved' ? 'stale' : 'requested', 'review' => $latest];
        }

        return ['state' => $latest['decision'], 'review' => $latest];
    }

    /** The approval to show on the note, or null. @return array<string, mixed>|null */
    public function approval(int $noteId): ?array
    {
        $s = $this->state($noteId);

        return $s['state'] === 'approved' ? $s['review'] : null;
    }

    /** @return list<array<string, mixed>> */
    public function history(int $noteId): array
    {
        return $this->reviews->forNote($noteId);
    }

    /** The signature picture of a review that is the note's current approval. */
    public function approvalSignature(int $reviewId): ?array
    {
        $r = $this->reviews->find($reviewId);
        if ($r === null || $r['signature_path'] === null) {
            return null;
        }
        $current = $this->approval((int) $r['note_id']);
        if ($current === null || (int) $current['id'] !== $reviewId) {
            return null;
        }
        $path = $this->file((string) $r['signature_path']);

        return $path === null ? null : ['path' => $path, 'note_id' => (int) $r['note_id']];
    }

    /** A fingerprint of everything a teacher checks: title, text, pictures, order and exercises. */
    public function hash(int $noteId): string
    {
        $note = $this->reviews->note($noteId);
        $parts = [(string) ($note['title'] ?? ''), (string) ($note['summary'] ?? '')];
        foreach ($this->reviews->blocks($noteId) as $b) {
            $parts[] = implode("\x1F", [$b['type'], (string) $b['body'], (string) $b['image_path'], (string) $b['align'], (string) $b['is_final']]);
            if ($b['type'] === 'checkpoint' && $b['assessment_id'] !== null) {
                foreach ($this->reviews->questions((int) $b['assessment_id']) as $q) {
                    $parts[] = 'q' . $q['id'] . '@' . $q['updated_at'];
                }
            }
        }

        return hash('sha256', implode("\x1E", $parts));
    }

    private function file(string $relative): ?string
    {
        $path = rtrim($this->storageRoot, '/') . '/' . $relative;

        return is_file($path) ? $path : null;
    }
}
