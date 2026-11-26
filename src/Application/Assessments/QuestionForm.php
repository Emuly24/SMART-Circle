<?php

declare(strict_types=1);

namespace SmartCircle\Application\Assessments;

use SmartCircle\Support\Subjects;

/** Validates the tutor's question form and turns it into the stored JSON data. */
final class QuestionForm
{
    /**
     * @param array<string, mixed> $in form, subject, topic_id, type, prompt, marks, is_application,
     *   options (list), correct, answer, tolerance_pct, unit, unit_required, accepted, model_answer
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validate(array $in): array
    {
        $type = (string) ($in['type'] ?? '');
        $form = (int) ($in['form'] ?? 0);
        $subject = (string) ($in['subject'] ?? '');
        $prompt = trim(str_replace("\r\n", "\n", (string) ($in['prompt'] ?? '')));
        $marks = (int) ($in['marks'] ?? 1);
        $errors = [];
        $data = [];

        if (!isset(Marker::TYPES[$type])) {
            $errors['type'] = 'Choose a question type.';
        }
        if ($form < 1 || $form > 4) {
            $errors['form'] = 'Choose the form.';
        }
        if (!isset(Subjects::NAMES[$subject])) {
            $errors['subject'] = 'Choose the subject.';
        }
        if (mb_strlen($prompt) < 5) {
            $errors['prompt'] = 'Write the question.';
        }
        if ($marks < 1 || $marks > 50) {
            $errors['marks'] = 'Marks must be between 1 and 50.';
        }

        if ($type === 'mcq') {
            $options = array_values(array_filter(array_map(
                static fn (mixed $o): string => trim((string) $o),
                is_array($in['options'] ?? null) ? $in['options'] : []
            ), static fn (string $o): bool => $o !== ''));
            $correct = (int) ($in['correct'] ?? -1);
            if (count($options) < 2) {
                $errors['options'] = 'Give at least two choices.';
            } elseif ($correct < 0 || $correct >= count($options)) {
                $errors['correct'] = 'Mark which choice is correct.';
            }
            $data = ['options' => $options, 'correct' => (string) $correct];
        } elseif ($type === 'numeric') {
            $answer = str_replace(',', '', trim((string) ($in['answer'] ?? '')));
            if (!is_numeric($answer)) {
                $errors['answer'] = 'Give the correct answer as a number, e.g. 2.5 or 3.2e4.';
            }
            $data = [
                'answer' => is_numeric($answer) ? (float) $answer : null,
                'tolerance_pct' => max(0.0, min(50.0, (float) ($in['tolerance_pct'] ?? 1))),
                'unit' => mb_substr(trim((string) ($in['unit'] ?? '')), 0, 20),
                'unit_required' => ($in['unit_required'] ?? '') === '1',
            ];
        } elseif ($type === 'grammar_fill') {
            $accepted = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r\n", "\n", (string) ($in['accepted'] ?? ''))))));
            if ($accepted === []) {
                $errors['accepted'] = 'Give at least one accepted answer (one per line).';
            }
            $data = ['accepted' => $accepted];
        } elseif ($type === 'short_text') {
            $data = ['model_answer' => trim((string) ($in['model_answer'] ?? ''))];
        }

        $topic = (int) ($in['topic_id'] ?? 0);

        return ['errors' => $errors, 'clean' => [
            'form' => $form, 'subject' => $subject, 'topic_id' => $topic > 0 ? $topic : null, 'type' => $type,
            'prompt' => mb_substr($prompt, 0, 5000), 'data' => $data, 'marks' => $marks,
            'is_application' => ($in['is_application'] ?? '') === '1',
        ]];
    }
}
