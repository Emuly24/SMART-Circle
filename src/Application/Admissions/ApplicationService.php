<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

use SmartCircle\Data\Repositories\ApplicationRepository;
use SmartCircle\Data\Repositories\IntakeRepository;
use SmartCircle\Support\Codes;
use SmartCircle\Support\PhoneNumber;
use SmartCircle\Support\Subjects;
use SmartCircle\Support\UploadedFile;

/**
 * The public side of admissions: apply, take the entry quiz, check status.
 */
final class ApplicationService
{
    public const QUIZ_MINUTES = 20;
    public const MAX_REPORT_BYTES = 3 * 1024 * 1024;
    private const REPORT_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

    public function __construct(
        private readonly ApplicationRepository $applications,
        private readonly IntakeRepository $intakes,
        private readonly string $storageRoot,
        private readonly int $minSecondsToFill = 20,   // humans need longer than this to fill the form
        private readonly int $maxPerIpPerDay = 5,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function openIntake(): ?array
    {
        return $this->intakes->open();
    }

    public function seatChart(): ?SeatChart
    {
        $intake = $this->intakes->current();

        return $intake === null ? null : new SeatChart((int) $intake['seats_per_group'], $this->applications->seatCounts((int) $intake['id']));
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, reference: ?string, id: ?int}
     */
    public function submit(array $input, ?UploadedFile $report, string $ip, int $secondsToFill, bool $byTutor = false): array
    {
        $fail = static fn (array $errors): array => ['errors' => $errors, 'reference' => null, 'id' => null];

        $intake = $this->intakes->open();
        if ($intake === null) {
            return $fail(['form' => 'Applications are closed at the moment.']);
        }

        // Spam protection without outside services: a hidden field bots fill in, a minimum
        // time to complete the form, and a daily limit per network address.
        if (!$byTutor) {
            if (trim((string) ($input['website'] ?? '')) !== '' || $secondsToFill < $this->minSecondsToFill) {
                return $fail(['form' => 'Please take a moment to check your answers, then submit again.']);
            }
            if ($this->applications->countFromIpSince($ip, 24) >= $this->maxPerIpPerDay) {
                return $fail(['form' => 'Too many applications from this connection today. Please try again tomorrow.']);
            }
        }

        [$errors, $data] = ApplicationForm::validate($input);

        if (!isset($errors['phone']) && $this->applications->phoneHasApplication((int) $intake['id'], $data['phone'])) {
            $errors['phone'] = 'There is already an application with this phone number. Use "Check my application" instead.';
        }
        if ($report !== null && !$report->ok()) {
            $errors['report'] = 'The school report did not upload. Try a smaller photo, or apply without it.';
        } elseif ($report !== null) {
            if ($report->size > self::MAX_REPORT_BYTES) {
                $errors['report'] = 'The school report is too large (maximum 3 MB).';
            } elseif (!isset(self::REPORT_TYPES[$report->mime()])) {
                $errors['report'] = 'Upload the school report as a photo (JPG/PNG) or a PDF.';
            }
        }
        if ($errors !== []) {
            return $fail($errors);
        }

        do {
            $reference = Codes::applicationReference();
        } while ($this->applications->referenceExists($reference));

        $id = $this->applications->create([
            'intake_id' => (int) $intake['id'],
            'reference' => $reference,
            'full_name' => $data['full_name'],
            'gender' => $data['gender'],
            'form' => $data['form'],
            'track' => $data['track'],
            'school_id' => $this->applications->schoolId($data['school_name'], $data['district']),
            'phone' => $data['phone'],
            'email' => $data['email'],
            'subjects_taken' => implode(',', $data['subjects_taken']),
            'help_subjects' => implode(',', $data['help_subjects']),
            'struggling_topics' => $data['struggling_topics'],
            'motivation' => $data['motivation'],
            'device' => $data['device'],
            'data_access' => $data['data_access'],
            'guardian_name' => $data['guardian_name'],
            'guardian_phone' => $data['guardian_phone'],
            'guardian_relationship' => $data['guardian_relationship'],
            'guardian_consent_at' => date('Y-m-d H:i:s'),
            'source' => $byTutor ? 'paper' : 'online',
            'ip' => $ip,
        ]);

        if ($report !== null) {
            $mime = $report->mime();
            $relative = "uploads/applications/$reference/report." . self::REPORT_TYPES[$mime];
            if ($report->moveTo($this->storageRoot . '/' . $relative)) {
                $this->applications->setReport($id, $relative, $mime);
            }
        }

        return ['errors' => [], 'reference' => $reference, 'id' => $id];
    }

    /** @return array<string, mixed>|null */
    public function findByReference(string $reference): ?array
    {
        return $this->applications->findByReference(self::formatReference($reference));
    }

    /** Status check: the reference AND the phone number must match. */
    public function findForStatus(string $reference, string $phone): ?array
    {
        $app = $this->findByReference($reference);
        $phone = PhoneNumber::normalize($phone);

        return $app !== null && $phone !== null && hash_equals((string) $app['phone'], $phone) ? $app : null;
    }

    public function canTakeQuiz(array $app): bool
    {
        return $app['quiz_finished_at'] === null && in_array($app['status'], ['submitted', 'shortlisted'], true);
    }

    /** @return list<array<string, mixed>> questions for this applicant's form and track (answers not included) */
    public function startQuiz(array $app): array
    {
        $this->applications->startQuiz((int) $app['id']);

        return array_map(
            static fn (array $q): array => array_diff_key($q, ['correct' => 1, 'explanation' => 1]),
            $this->applications->quizQuestions((int) $app['form'], Subjects::forTrack((string) $app['track']))
        );
    }

    /**
     * @param array<string, string> $answers question id => a|b|c|d
     * @return array{score: int, total: int}
     */
    public function submitQuiz(array $app, array $answers): array
    {
        if (!$this->canTakeQuiz($app)) {
            throw new AdmissionException('The entry quiz has already been completed.');
        }
        $questions = $this->applications->quizQuestions((int) $app['form'], Subjects::forTrack((string) $app['track']));

        // Answers after the time limit (plus 2 minutes' grace for slow connections) do not count.
        $started = $app['quiz_started_at'] !== null ? strtotime((string) $app['quiz_started_at']) : time();
        $late = time() - $started > (self::QUIZ_MINUTES + 2) * 60;

        $rows = [];
        $score = 0;
        foreach ($questions as $q) {
            $chosen = $answers[(string) $q['id']] ?? null;
            $chosen = in_array($chosen, ['a', 'b', 'c', 'd'], true) && !$late ? $chosen : null;
            $correct = $chosen !== null && $chosen === $q['correct'];
            $score += $correct ? 1 : 0;
            $rows[] = ['question_id' => (int) $q['id'], 'chosen' => $chosen, 'is_correct' => $correct];
        }
        $this->applications->saveQuiz((int) $app['id'], $rows, $score, count($questions));

        return ['score' => $score, 'total' => count($questions)];
    }

    public function reportPath(array $app): ?string
    {
        return $app['report_path'] ? $this->storageRoot . '/' . $app['report_path'] : null;
    }

    /** "sc 27 7kq4m" -> "SC-27-7KQ4M" */
    public static function formatReference(string $typed): string
    {
        $raw = Codes::normalize($typed);

        return preg_match('/^SC(\d{2})([A-Z0-9]{5})$/', $raw, $m) ? "SC-{$m[1]}-{$m[2]}" : $raw;
    }
}
