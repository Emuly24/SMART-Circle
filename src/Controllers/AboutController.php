<?php

declare(strict_types=1);

namespace SmartCircle\Controllers;

use SmartCircle\Models\UserModel;

final class AboutController extends Controller
{
    private UserModel $users;

    public function __construct(?UserModel $users = null)
    {
        $this->users = $users ?? new UserModel();
    }

    public function handle(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('login.php');
        }

        $user = $this->users->findById((int) $_SESSION['user_id']);

        if (!$user) {
            $this->redirect('login.php');
        }

        $this->render('public/about', [
            'user' => $user,
            'isLoggedIn' => true,
            'pageTitle' => 'About SMART Circle',
        ]);
    }
}
