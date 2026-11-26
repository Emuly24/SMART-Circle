<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

use SmartCircle\Data\Repositories\DocumentRepository;
use SmartCircle\Support\Codes;

/**
 * Issues letters with a verification code that anyone can check on /verify.
 */
final class DocumentService
{
    public const TITLES = [
        'admission_letter' => 'Letter of admission',
        'waitlist_letter' => 'Waiting list letter',
        'decline_letter' => 'Application outcome',
        'partner_invite' => 'Invitation to partner with SMART Circle',
        'pledge_certificate' => 'The SMART Circle Pledge',
        'term_report' => 'Term report',
    ];

    public function __construct(private readonly DocumentRepository $documents)
    {
    }

    /** @param array<string, mixed> $data */
    public function issue(string $type, string $subjectType, int $subjectId, array $data, ?int $issuedBy): string
    {
        do {
            $code = 'L' . Codes::random(7);
        } while ($this->documents->codeExists($code));

        $this->documents->create($code, $type, $subjectType, $subjectId, self::TITLES[$type] ?? 'Document', $data, $issuedBy);

        return $code;
    }

    /** Letters for an application are replaced when the decision changes. */
    public function revokeFor(string $subjectType, int $subjectId): void
    {
        $this->documents->revokeForSubject($subjectType, $subjectId);
    }

    /** @return array<string, mixed>|null */
    public function find(string $code): ?array
    {
        return $this->documents->findByCode(Codes::normalize($code));
    }

    /** @return list<array<string, mixed>> */
    public function forApplication(int $applicationId): array
    {
        return $this->documents->forSubject('application', $applicationId);
    }

    /** @return list<array<string, mixed>> */
    public function forUser(int $userId): array
    {
        return $this->documents->forSubject('user', $userId);
    }

    /** Mark one document as replaced (e.g. a re-issued term report). */
    public function revoke(string $code): void
    {
        $this->documents->revokeCode($code);
    }
}
