<?php

declare(strict_types=1);

namespace SmartCircle\Controllers;

use SmartCircle\Models\UserModel;

final class LoginController extends Controller
{
    private UserModel $users;

    public function __construct(?UserModel $users = null)
    {
        $this->users = $users ?? new UserModel();
    }

    public function handle(): void
    {
        if ($this->isAlreadyLoggedIn()) {
            $this->redirectAfterLogin((string) $_SESSION['role']);
        }

        $error = '';
        $login = '';

        if ($this->isPost()) {
            $login = trim((string) ($_POST['login'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            $result = $this->attemptLogin($login, $password);

            if ($result['success']) {
                $this->establishSession($result['user']);
                $this->redirectForUser($result['user']);
            }

            $error = $result['error'];
        }

        $this->render('auth/login', [
            'error' => $error,
            'login' => $login,
            'pageTitle' => 'Login - SMART Circle',
        ]);
    }

    private function isAlreadyLoggedIn(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['role']);
    }

    /** @return array{success: bool, error: string, user: ?array} */
    private function attemptLogin(string $login, string $password): array
    {
        if ($login === '' || $password === '') {
            return [
                'success' => false,
                'error' => 'Enter phone/email and password.',
                'user' => null,
            ];
        }

        if (!$this->isValidLoginFormat($login)) {
            return [
                'success' => false,
                'error' => 'Enter a valid phone number or email address.',
                'user' => null,
            ];
        }

        $user = $this->users->findByLogin($login);

        if (!$user || !password_verify($password, (string) $user['password'])) {
            return [
                'success' => false,
                'error' => 'Invalid credentials.',
                'user' => null,
            ];
        }

        return [
            'success' => true,
            'error' => '',
            'user' => $user,
        ];
    }

    private function isValidLoginFormat(string $login): bool
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        $digits = preg_replace('/\D/', '', $login);

        return $digits !== null && strlen($digits) >= 9 && strlen($digits) <= 15;
    }

    private function establishSession(array $user): void
    {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['fullname'] = (string) $user['fullname'];

        if (isset($user['role']) && $user['role'] === 'admin') {
            $_SESSION['role'] = 'admin';
            $_SESSION['admin_logged'] = true;
        } else {
            $_SESSION['role'] = 'student';
            $_SESSION['approved'] = $user['approved'];
            $_SESSION['consent_signed'] = $user['consent_signed'];
            $_SESSION['status'] = $user['status'];
            $_SESSION['suspension_end'] = $user['suspension_end'];
        }

        session_regenerate_id(true);
        session_write_close();
    }

    private function redirectForUser(array $user): void
    {
        if (isset($user['role']) && $user['role'] === 'admin') {
            $this->redirect('admin_dashboard.php');
        }

        if ((int) $user['approved'] === 0) {
            $destination = $this->users->hasApplication((int) $user['id'])
                ? 'pending.php'
                : 'apply.php';
            $this->redirect($destination);
        }

        if ((int) $user['approved'] === 1 && (int) $user['consent_signed'] === 0) {
            $this->redirect('consent.php');
        }

        $this->redirect('dashboard.php');
    }

    private function redirectAfterLogin(string $role): void
    {
        session_write_close();

        if ($role === 'admin') {
            $this->redirect('admin_dashboard.php');
        }

        $this->redirect('dashboard.php');
    }
}
