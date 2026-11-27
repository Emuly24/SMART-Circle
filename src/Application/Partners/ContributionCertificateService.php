<?php

declare(strict_types=1);

namespace SmartCircle\Application\Partners;

use SmartCircle\Application\Admissions\DocumentService;
use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\ContributionStatsRepository;
use SmartCircle\Data\Repositories\PartnerRepository;

/** A verifiable, signed certificate of contribution for a partner teacher, listing what they gave. */
final class ContributionCertificateService
{
    public const LABELS = [
        'notes_checked' => 'sets of notes checked and signed',
        'lessons_checked' => 'recorded lessons checked and signed',
        'questions_added' => 'questions added to the question bank',
        'guest_lessons' => 'lessons taught as a guest teacher',
        'coverage_reports' => 'topic reports for their school',
        'resources' => 'teaching resources shared',
        'calendar_checks' => 'school calendar checks',
    ];

    public function __construct(
        private readonly ContributionStatsRepository $stats,
        private readonly PartnerRepository $partners,
        private readonly DocumentService $documents,
        private readonly NotificationService $notifications,
    ) {
    }

    /** @return array<string, int> */
    public function counts(int $partnerId): array
    {
        return $this->stats->counts($partnerId);
    }

    /** @return array{code: ?string, error: string} */
    public function issue(int $partnerId, int $tutorId): array
    {
        $p = $this->partners->find($partnerId);
        if ($p === null) {
            return ['code' => null, 'error' => 'Teacher not found.'];
        }
        $counts = array_filter($this->stats->counts($partnerId));
        if ($counts === []) {
            return ['code' => null, 'error' => 'There is nothing to certify yet: no checks, questions, lessons or reports recorded.'];
        }
        $code = $this->documents->issue('contribution_certificate', 'user', $partnerId, [
            'full_name' => $p['full_name'],
            'school' => $p['school_name'],
            'district' => $p['district'],
            'subjects' => (string) $p['subjects'],
            'since' => $p['verified_at'] ?? null,
            'counts' => $counts,
        ], $tutorId);
        $this->notifications->notify([$partnerId], 'certificate', 'Thank you! Your certificate of contribution is ready', 'Open it, print it or save it as PDF.', '/letters/' . $code);

        return ['code' => $code, 'error' => ''];
    }
}
