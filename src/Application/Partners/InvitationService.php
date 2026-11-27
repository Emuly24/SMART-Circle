<?php

declare(strict_types=1);

namespace SmartCircle\Application\Partners;

use SmartCircle\Application\Admissions\DocumentService;
use SmartCircle\Support\PhoneNumber;
use SmartCircle\Support\Subjects;

/**
 * Invitation letters the tutor gives to people who are not yet in SMART Circle: a school teacher (to become a
 * partner teacher) or a volunteer tutor (to join the team). Each is signed, printable and verifiable, and can
 * be shared by its link.
 */
final class InvitationService
{
    public const TYPES = ['teacher_invitation' => 'A school teacher (partner teacher)', 'tutor_invitation' => 'A volunteer tutor (SMART Circle Team)'];
    public const TITLES = ['', 'Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Prof'];

    public function __construct(
        private readonly DocumentService $documents,
        /** @var \Closure(): ?string the tutor's WhatsApp number, +265... */
        private readonly \Closure $tutorPhone,
    ) {
    }

    /**
     * Checks the details. Pure.
     * @param array<string, mixed> $in type, title, full_name, school, district, subjects (list), note
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validate(array $in): array
    {
        $type = (string) ($in['type'] ?? '');
        $title = (string) ($in['title'] ?? '');
        $name = trim((string) preg_replace('/\s+/', ' ', (string) ($in['full_name'] ?? '')));
        $school = trim((string) ($in['school'] ?? ''));
        $district = trim((string) ($in['district'] ?? ''));
        $note = trim(str_replace("\r\n", "\n", (string) ($in['note'] ?? '')));
        $subjects = Subjects::clean((array) ($in['subjects'] ?? []));
        $errors = [];
        if (!isset(self::TYPES[$type])) {
            $errors['type'] = 'Choose who the letter is for.';
        }
        if (!in_array($title, self::TITLES, true)) {
            $errors['title'] = 'Choose a title.';
        }
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
            $errors['full_name'] = 'Write their full name.';
        }
        if ($type === 'teacher_invitation' && mb_strlen($school) < 3) {
            $errors['school'] = 'Write the name of their school.';
        }
        if ($subjects === []) {
            $errors['subjects'] = 'Choose at least one subject.';
        }
        if (mb_strlen($school) > 150 || mb_strlen($district) > 80) {
            $errors['school'] = 'Keep the school and district short.';
        }
        if (mb_strlen($note) > 800) {
            $errors['note'] = 'Keep the personal note under 800 characters.';
        }
        $parts = explode(' ', $name);
        $salutation = $title !== '' ? $title . ' ' . end($parts) : $parts[0];

        return ['errors' => $errors, 'clean' => [
            'type' => $type, 'title' => $title, 'full_name' => ($title !== '' ? $title . ' ' : '') . $name, 'salutation' => $salutation,
            'school' => $school, 'district' => $district, 'subjects' => implode(',', $subjects), 'note' => $note,
        ]];
    }

    /** @return array{errors: array<string, string>, code: ?string, clean: array<string, mixed>} */
    public function issue(array $in, int $tutorId): array
    {
        $v = self::validate($in);
        if ($v['errors'] !== []) {
            return ['errors' => $v['errors'], 'code' => null, 'clean' => $v['clean']];
        }
        $c = $v['clean'];
        $phone = ($this->tutorPhone)();
        $code = $this->documents->issue($c['type'], 'invitation', 0, [
            'full_name' => $c['full_name'], 'salutation' => $c['salutation'], 'school' => $c['school'], 'district' => $c['district'],
            'subjects' => $c['subjects'], 'note' => $c['note'], 'contact_phone' => $phone !== null ? PhoneNumber::display($phone) : '',
        ], $tutorId);

        return ['errors' => [], 'code' => $code, 'clean' => $c];
    }

    /** @return list<array<string, mixed>> */
    public function issued(): array
    {
        return $this->documents->forSubject('invitation', 0);
    }

    public static function isInvitation(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }
}
