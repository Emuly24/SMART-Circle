<?php

declare(strict_types=1);

namespace SmartCircle\Application\Partners;

use SmartCircle\Application\Mail\MailService;
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
        private readonly ?MailService $mail = null,
        private readonly string $siteUrl = '',
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
        $email = trim((string) ($in['email'] ?? ''));
        $errors = [];
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) {
            $errors['email'] = 'That email address does not look right.';
        }
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
            'school' => $school, 'district' => $district, 'subjects' => implode(',', $subjects), 'note' => $note, 'email' => $email,
        ]];
    }

    /** @return array{errors: array<string, string>, code: ?string, clean: array<string, mixed>, emailed?: bool} */
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
        $emailed = false;
        if ($c['email'] !== '' && $this->mail !== null) {
            $m = self::email($c, $code, $this->siteUrl);
            $emailed = $this->mail->queue($c['email'], $c['full_name'], $m['subject'], $m['text'], $c['type'], 'document', null, $tutorId);
        }

        return ['errors' => [], 'code' => $code, 'clean' => $c, 'emailed' => $emailed];
    }

    /**
     * The email that carries an invitation letter. Pure.
     * @param array<string, mixed> $c the clean details
     * @return array{subject: string, text: string}
     */
    public static function email(array $c, string $code, string $siteUrl): array
    {
        $link = rtrim($siteUrl, '/') . '/letters/' . $code;
        $teacher = $c['type'] === 'teacher_invitation';
        $subject = $teacher ? 'An invitation to be a SMART Circle partner teacher' : 'An invitation to join the SMART Circle Team';
        $text = 'Dear ' . $c['salutation'] . ",\n\n"
            . ($teacher
                ? 'I have written you a letter inviting you to be a partner teacher with SMART Circle, the free replay-based support programme for Form 1–4 students in Malawi. It explains why we need you and what you gain from taking part.'
                : 'I have written you a letter inviting you to join the SMART Circle Team as a volunteer tutor. SMART Circle is a free replay-based support programme for Form 1–4 students in Malawi. The letter explains why we need you and what you gain from taking part.')
            . "\n\nRead the signed letter here (you can print it or save it as a PDF): " . $link
            . "\n\nIts reference code is " . $code . '. Anyone can check it is genuine at ' . rtrim($siteUrl, '/') . '/verify.'
            . "\n\nYou can reply to this email with any questions.\n\nWith thanks,\nThe SMART Circle tutor";

        return ['subject' => $subject, 'text' => $text];
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
