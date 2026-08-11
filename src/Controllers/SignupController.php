<?php

declare(strict_types=1);

namespace SmartCircle\Controllers;

use SmartCircle\Models\UserModel;
use SmartCircle\Support\Csrf;
use SmartCircle\Support\InputValidator;

final class SignupController extends Controller
{
    private UserModel $users;

    public function __construct(?UserModel $users = null)
    {
        $this->users = $users ?? new UserModel();
    }

    public function handle(): void
    {
        $this->runSafely(function (): void {
            $this->processRequest();
        });
    }

    private function processRequest(): void
    {
        if ($this->isAlreadyLoggedIn()) {
            $this->renderLoggedInView();

            return;
        }

        $error = '';
        $success = '';
        $form = $this->defaultFormData();

        if ($this->isPost()) {
            if (!$this->validateCsrf()) {
                $this->respondCsrfFailure();
            }

            $result = $this->processSignup($_POST);
            $error = $result['error'];
            $success = $result['success'];
            $form = $result['form'];

            if ($success !== '') {
                Csrf::regenerate();

                if ($this->wantsJson()) {
                    $this->jsonSuccess($success, ['redirect' => 'login.php']);
                }
            } elseif ($error !== '' && $this->wantsJson()) {
                $this->jsonError($error, $result['errors'], 422);
            }
        }

        $this->render('auth/signup', [
            'error' => $error,
            'success' => $success,
            'form' => $form,
            'csrfField' => $this->csrfField(),
            'pageTitle' => 'Sign Up - SMART Circle',
        ]);
    }

    private function isAlreadyLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    private function renderLoggedInView(): void
    {
        $fullname = (string) ($_SESSION['fullname'] ?? '');
        $firstName = UserModel::extractFirstName($fullname);

        if ($firstName === 'User') {
            $dbName = $this->users->findFullnameById((int) $_SESSION['user_id']);
            $firstName = UserModel::extractFirstName($dbName);
        }

        if ($this->wantsJson()) {
            $this->jsonSuccess('Already logged in.', [
                'firstName' => $firstName,
                'redirect' => 'dashboard.php',
            ]);
        }

        $this->render('auth/signup_logged_in', [
            'firstName' => $firstName,
            'pageTitle' => 'Already Logged In - SMART Circle',
        ]);
    }

    /** @return array{username: string, fullname: string, phone: string, email: string, school: string} */
    private function defaultFormData(): array
    {
        return [
            'username' => '',
            'fullname' => '',
            'phone' => '',
            'email' => '',
            'school' => '',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{error: string, success: string, form: array<string, string>, errors: array<string, string>}
     */
    private function processSignup(array $input): array
    {
        $errors = [];
        $form = [
            'username' => trim((string) ($input['username'] ?? '')),
            'fullname' => trim((string) ($input['fullname'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'school' => trim((string) ($input['school'] ?? '')),
        ];

        $username = InputValidator::username($form['username']);
        $fullname = InputValidator::string($form['fullname'], 2, 120);
        $phone = InputValidator::phone($form['phone']);
        $email = InputValidator::optionalEmail($form['email']);
        $school = InputValidator::string($form['school'], 2, 200);
        $password = InputValidator::password($input['password'] ?? '');
        $confirm = is_string($input['confirm_password'] ?? null) ? $input['confirm_password'] : '';

        if ($username === null) {
            $errors['username'] = 'Username must be 3–20 characters (letters, numbers, underscore only).';
        }

        if ($fullname === null) {
            $errors['fullname'] = 'Full name is required.';
        }

        if ($phone === null) {
            $errors['phone'] = 'Enter a valid phone number (9–15 digits).';
        }

        if ($email === null) {
            $errors['email'] = 'Invalid email address.';
        }

        if ($school === null) {
            $errors['school'] = 'School name is required.';
        }

        if ($password === null) {
            $errors['password'] = 'Password must be at least 5 characters.';
        }

        if ($password !== null && $password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            return [
                'error' => 'Please correct the highlighted fields.',
                'success' => '',
                'form' => $form,
                'errors' => $errors,
            ];
        }

        if ($this->users->usernameExists($username)) {
            return [
                'error' => 'Username already taken. Please choose a different one.',
                'success' => '',
                'form' => $form,
                'errors' => ['username' => 'Username already taken.'],
            ];
        }

        if ($this->users->phoneExists($phone)) {
            return [
                'error' => 'Phone number already registered. Please login or use a different number.',
                'success' => '',
                'form' => $form,
                'errors' => ['phone' => 'Phone number already registered.'],
            ];
        }

        $userId = $this->users->create([
            'username' => $username,
            'fullname' => $fullname,
            'phone' => $phone,
            'email' => $email ?? '',
            'school' => $school,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        if (function_exists('log_activity')) {
            log_activity($userId, 'signup', 'New account created');
        }

        return [
            'error' => '',
            'success' => 'Account created successfully! You can now login and complete your application.',
            'form' => $this->defaultFormData(),
            'errors' => [],
        ];
    }
}
