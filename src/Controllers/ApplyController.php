<?php

declare(strict_types=1);

namespace SmartCircle\Controllers;

use SmartCircle\Models\ApplicationModel;
use SmartCircle\Support\Csrf;
use SmartCircle\Support\InputValidator;

final class ApplyController extends Controller
{
    private ApplicationModel $applications;

    public function __construct(?ApplicationModel $applications = null)
    {
        $this->applications = $applications ?? new ApplicationModel();
    }

    public function handle(): void
    {
        $this->runSafely(function (): void {
            $this->processRequest();
        });
    }

    private function processRequest(): void
    {
        if (!isset($_SESSION['user_id'])) {
            if ($this->wantsJson()) {
                $this->jsonError('Authentication required.', [], 401);
            }

            $this->redirect('login.php');
        }

        $userId = (int) $_SESSION['user_id'];
        $user = $this->applications->findUserProfile($userId);

        if (!$user) {
            if ($this->wantsJson()) {
                $this->jsonError('User not found.', [], 404);
            }

            $this->redirect('login.php');
        }

        if ((int) $user['approved'] === 1) {
            if ($this->wantsJson()) {
                $this->jsonSuccess('Already approved.', ['redirect' => 'dashboard.php']);
            }

            $this->redirect('dashboard.php');
        }

        if ($this->applications->hasSubmittedApplication($userId)) {
            if ($this->wantsJson()) {
                $this->jsonSuccess('Application already submitted.', ['redirect' => 'pending.php']);
            }

            $this->render('application/already_applied', [
                'pageTitle' => 'Already Applied - SMART Circle',
            ]);

            return;
        }

        $existingApplication = $this->applications->findByUserId($userId);
        $error = '';

        if ($this->isPost()) {
            if (!$this->validateCsrf()) {
                $this->respondCsrfFailure();
            }

            $result = $this->processSubmission($userId, $_POST, $existingApplication !== null);

            if ($result['success']) {
                Csrf::regenerate();

                if ($this->wantsJson()) {
                    $this->jsonSuccess('Application submitted successfully.', ['redirect' => 'pending.php']);
                }

                $this->redirect('pending.php');
            }

            $error = $result['error'];

            if ($this->wantsJson()) {
                $this->jsonError($error, $result['errors'], 422);
            }
        }

        $this->render('application/apply', [
            'error' => $error,
            'user' => $user,
            'application' => $existingApplication,
            'universities' => $this->applications->listUniversities(),
            'allSubjects' => ApplicationModel::ALL_SUBJECTS,
            'coreSubjects' => ApplicationModel::CORE_SUBJECTS,
            'csrfField' => $this->csrfField(),
            'pageTitle' => 'Application Form – SMART Circle',
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, error: string, errors: array<string, string>}
     */
    private function processSubmission(int $userId, array $input, bool $updateExisting): array
    {
        $errors = [];

        $classLevel = InputValidator::enum($input['class_level'] ?? '', ['Form 3', 'Form 4']);
        $gender = InputValidator::enum($input['gender'] ?? '', ['Male', 'Female']);
        $school = InputValidator::string($input['school'] ?? '', 2, 200);
        $dob = InputValidator::date($input['dob'] ?? '');
        $subjectsTaken = InputValidator::stringArray(
            $input['subjects_taken'] ?? [],
            ApplicationModel::ALL_SUBJECTS
        );
        $subjectsAssist = InputValidator::stringArray(
            $input['subjects_assist'] ?? [],
            ApplicationModel::CORE_SUBJECTS
        );
        $ambition = InputValidator::string($input['ambition'] ?? '', 2, 120);
        $careerReason = InputValidator::string($input['career_reason'] ?? '', 10, 2000);
        $whyJoin = InputValidator::string($input['why_join'] ?? '', 10, 2000);
        $targetPoints = InputValidator::integer($input['target_points'] ?? null, 0, 20);
        $university = InputValidator::string($input['university'] ?? '', 1, 200);
        $customUniversity = InputValidator::string($input['custom_university'] ?? '', 2, 200);

        if ($classLevel === null) {
            $errors['class_level'] = 'Select your current class.';
        }

        if ($gender === null) {
            $errors['gender'] = 'Select your gender.';
        }

        if ($school === null) {
            $errors['school'] = 'School name is required.';
        }

        if ($dob === null) {
            $errors['dob'] = 'Enter a valid date of birth.';
        }

        if ($subjectsTaken === []) {
            $errors['subjects_taken'] = 'Select at least one subject you are taking.';
        }

        if ($subjectsAssist === []) {
            $errors['subjects_assist'] = 'Select at least one subject you need assistance with.';
        }

        if ($ambition === null) {
            $errors['ambition'] = 'Career ambition is required.';
        }

        if ($careerReason === null) {
            $errors['career_reason'] = 'Please explain why you want that career (at least 10 characters).';
        }

        if ($whyJoin === null) {
            $errors['why_join'] = 'Please explain why you want to join (at least 10 characters).';
        }

        if ($targetPoints === null) {
            $errors['target_points'] = 'Target points must be between 0 and 20.';
        }

        if ($university === null) {
            $errors['university'] = 'Select a university.';
        }

        if ($university === 'Other') {
            if ($customUniversity === null) {
                $errors['custom_university'] = 'Please specify your university or college.';
            } else {
                $university = $customUniversity;
            }
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'error' => 'Please fill all required fields correctly.',
                'errors' => $errors,
            ];
        }

        $subjectsTakenStr = implode(', ', $subjectsTaken);
        $subjectsAssistStr = implode(', ', $subjectsAssist);
        $route = $this->determineRoute($subjectsTakenStr);

        $this->applications->ensureUniversityExists($university);

        $this->applications->save(
            $userId,
            [
                'class_level' => $classLevel,
                'gender' => $gender,
                'school' => $school,
                'dob' => $dob,
                'subjects' => $subjectsTakenStr,
                'route' => $route,
            ],
            [
                'ambition' => $ambition,
                'career_reason' => $careerReason,
                'university' => $university,
                'why_join' => $whyJoin,
                'subject_assist' => $subjectsAssistStr,
                'target_points' => $targetPoints,
            ],
            $updateExisting
        );

        if (function_exists('log_activity')) {
            log_activity($userId, 'submit_application', 'Application submitted for ' . $classLevel);
        }

        return [
            'success' => true,
            'error' => '',
            'errors' => [],
        ];
    }

    private function determineRoute(string $subjectsTaken): string
    {
        $humanities = ['History', 'Bible Knowledge', 'Social Studies', 'Life Skills'];
        $hasHumanities = false;
        $hasScience = str_contains($subjectsTaken, 'Physics') && str_contains($subjectsTaken, 'Chemistry');

        foreach ($humanities as $subject) {
            if (str_contains($subjectsTaken, $subject)) {
                $hasHumanities = true;
                break;
            }
        }

        if ($hasHumanities && !$hasScience) {
            return 'humanities';
        }

        return 'sciences';
    }
}
