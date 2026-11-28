<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * One-click name tags in messages and emails: {first_name}, {surname}, {full_name}, {title_surname},
 * {title_full_name}, and for guardians {student_first_name} / {student_full_name}. Each reader sees their
 * own name, styled in brand green. {name} (older emails) means the first name.
 */
final class NameTags
{
    public const TITLES = ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Prof'];

    /** Tag => button label. */
    public const TAGS = [
        'first_name' => 'First name',
        'surname' => 'Surname',
        'full_name' => 'Full name',
        'title_surname' => 'Title + surname',
        'title_full_name' => 'Title + full name',
        'student_first_name' => 'Student’s first name',
        'student_full_name' => 'Student’s full name',
    ];

    /** Tags offered for students (no titles). */
    public const STUDENT_TAGS = ['first_name', 'surname', 'full_name'];

    /** Tags offered for teachers and tutors. */
    public const TEACHER_TAGS = ['title_surname', 'title_full_name', 'surname', 'full_name', 'first_name'];

    /** Brand green for names in emails (email clients need inline colours). */
    public const EMAIL_COLOUR = '#157A52';

    private const PATTERN = '/\{(first_name|surname|full_name|title_surname|title_full_name|student_first_name|student_full_name|name)\}/';

    /** Private-use markers around a name in plain text, so the email's HTML can colour it. */
    public const OPEN = "\u{E000}";
    public const CLOSE = "\u{E001}";

    /**
     * The values for one reader. Pure.
     * @param array{full_name?: string|null, title?: string|null, student_name?: string|null} $person
     * @return array<string, string>
     */
    public static function values(array $person): array
    {
        $full = trim((string) preg_replace('/\s+/', ' ', (string) ($person['full_name'] ?? '')));
        $parts = $full === '' ? [] : explode(' ', $full);
        $first = $parts[0] ?? '';
        $surname = count($parts) > 1 ? (string) end($parts) : $first;
        $title = in_array($person['title'] ?? null, self::TITLES, true) ? (string) $person['title'] : '';
        $student = trim((string) ($person['student_name'] ?? ''));
        $sParts = $student === '' ? [] : explode(' ', $student);

        return [
            'first_name' => $first,
            'name' => $first,
            'surname' => $surname,
            'full_name' => $full,
            'title_surname' => $title !== '' ? $title . ' ' . $surname : $full,
            'title_full_name' => $title !== '' ? $title . ' ' . $full : $full,
            'student_first_name' => $sParts[0] ?? '',
            'student_full_name' => $student,
        ];
    }

    public static function has(string $text): bool
    {
        return (bool) preg_match(self::PATTERN, $text);
    }

    /**
     * Fills the tags for one reader. With $marked, each name is wrapped in OPEN/CLOSE (for the email HTML).
     * A tag with no value (a student name for someone who is not a guardian) becomes "there". Pure.
     * @param array{full_name?: string|null, title?: string|null, student_name?: string|null} $person
     */
    public static function fill(string $text, array $person, bool $marked = false): string
    {
        $v = self::values($person);

        return (string) preg_replace_callback(self::PATTERN, static function (array $m) use ($v, $marked): string {
            $value = $v[$m[1]] !== '' ? $v[$m[1]] : 'there';

            return $marked ? self::OPEN . $value . self::CLOSE : $value;
        }, $text);
    }

    /** Plain text for previews: the reader's name, or "[First name]" when the reader is not known. Pure. */
    public static function preview(string $text, ?array $person): string
    {
        if ($person !== null) {
            return self::fill($text, $person);
        }

        return (string) preg_replace_callback(self::PATTERN, static fn (array $m): string => '[' . (self::TAGS[$m[1]] ?? 'First name') . ']', $text);
    }

    /** Removes the OPEN/CLOSE markers (the plain-text email). */
    public static function unmark(string $text): string
    {
        return str_replace([self::OPEN, self::CLOSE], '', $text);
    }

    /**
     * On already-escaped HTML: each tag becomes the reader's name in a green span, or, when the reader is not
     * known (the sender viewing a message to many), a labelled slot. Pure.
     * @param array{full_name?: string|null, title?: string|null, student_name?: string|null}|null $person
     */
    public static function html(string $escaped, ?array $person): string
    {
        $v = $person === null ? null : self::values($person);

        return (string) preg_replace_callback(self::PATTERN, static function (array $m) use ($v): string {
            if ($v === null || $v[$m[1]] === '') {
                $label = self::TAGS[$m[1]] ?? 'First name';
                return '<span class="name-tag name-tag--slot" title="Each person sees their own name here">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
            }

            return '<span class="name-tag">' . htmlspecialchars($v[$m[1]], ENT_QUOTES, 'UTF-8') . '</span>';
        }, $escaped);
    }

    /**
     * Who the tags in a message mean: the reader when they are one of its recipients, else its only
     * recipient, else nobody (shown as slots). Pure.
     * @param list<array<string, mixed>> $members conversation members (user_id, full_name, title)
     * @return array<string, mixed>|null
     */
    public static function readerFor(int $senderId, int $viewerId, array $members): ?array
    {
        $recipients = array_values(array_filter($members, static fn (array $p): bool => (int) $p['user_id'] !== $senderId));
        foreach ($recipients as $p) {
            if ((int) $p['user_id'] === $viewerId) {
                return $p;
            }
        }

        return count($recipients) === 1 ? $recipients[0] : null;
    }
}
