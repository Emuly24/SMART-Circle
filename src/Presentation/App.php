<?php

declare(strict_types=1);

namespace SmartCircle\Presentation;

use PDOException;
use SmartCircle\Application\Auth\AuthService;
use SmartCircle\Application\Install\InstallService;
use SmartCircle\Application\Lessons\LessonService;
use SmartCircle\Application\Lessons\UploadService;
use SmartCircle\Data\Database;
use SmartCircle\Data\DatabaseSessionHandler;
use SmartCircle\Data\LessonStorage;
use SmartCircle\Data\Migrator;
use SmartCircle\Data\Repositories\AuditLogRepository;
use SmartCircle\Data\Repositories\LessonRepository;
use SmartCircle\Data\Repositories\LessonUploadRepository;
use SmartCircle\Data\Repositories\LoginAttemptRepository;
use SmartCircle\Data\Repositories\UserRepository;
use SmartCircle\Presentation\Controllers\AuthController;
use SmartCircle\Presentation\Controllers\DashboardController;
use SmartCircle\Presentation\Controllers\InstallController;
use SmartCircle\Presentation\Controllers\LessonController;
use SmartCircle\Presentation\Controllers\PageController;
use SmartCircle\Presentation\Controllers\StudioController;
use SmartCircle\Presentation\Controllers\UploadController;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Csrf;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Router;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\Middleware\RequireGuest;
use SmartCircle\Presentation\Middleware\RequireRole;
use SmartCircle\Presentation\Middleware\VerifyCsrf;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Config;
use SmartCircle\Support\Logger;
use Throwable;

/**
 * Wires the layers together and turns one HTTP request into one response.
 */
final class App
{
    private readonly Logger $logger;
    private readonly View $view;
    private readonly Session $session;
    private readonly Context $context;
    private readonly Csrf $csrf;
    private readonly string $root;

    public function __construct(private readonly Config $config)
    {
        $this->root = dirname(__DIR__, 2);
        $this->logger = new Logger($this->storage('logs'));
        $this->view = new View(__DIR__ . '/View/templates');
        $this->session = new Session();
        $this->context = new Context();
        $this->csrf = new Csrf($this->session);
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->process($request);
        } catch (HttpException $e) {
            $response = $this->errorPage($e->status, $e->getMessage());
        } catch (Throwable $e) {
            $this->logger->exception($e);
            $detail = $this->config->bool('app.debug') ? $e::class . ': ' . $e->getMessage() : '';
            $response = $this->errorPage(500, HttpException::defaultMessage(500), $detail);
        }

        return $this->withSecurityHeaders($response);
    }

    private function process(Request $request): Response
    {
        if ($this->config->bool('app.force_https') && !$request->isHttps()) {
            return Response::redirect('https://' . $request->header('Host') . $request->path, 301);
        }

        $this->shareViewHelpers();
        $this->view->share('path', $request->path);

        if ($this->config->string('db.name') === '') {
            return $this->setupNeededPage('The database is not configured yet. Copy secrets.example.php to secrets.php and fill in the database details.');
        }

        $db = new Database(
            $this->config->string('db.host'),
            $this->config->int('db.port', 3306),
            $this->config->string('db.name'),
            $this->config->string('db.user'),
            $this->config->string('db.password'),
        );

        $users = new UserRepository($db);
        $audit = new AuditLogRepository($db);
        $installer = new InstallService(
            new Migrator($db, $this->root . '/database/migrations'),
            $users,
            $audit,
            $this->config->string('security.install_token'),
        );

        try {
            $installed = $this->isInstalled($installer);
        } catch (PDOException $e) {
            $this->logger->exception($e);
            return $this->setupNeededPage('The site cannot reach its database right now. Please try again in a few minutes.');
        }

        $this->startSession($request, $installed ? new DatabaseSessionHandler($db) : null);

        $auth = new AuthService(
            $users,
            new LoginAttemptRepository($db),
            $audit,
            $this->config->int('security.login_max_attempts', 5),
            $this->config->int('security.login_window_secs', 900),
        );

        if ($installed) {
            $userId = $this->session->userId();
            if ($userId !== null) {
                $user = $auth->activeUser($userId);
                $user === null ? $this->session->logout() : $this->context->setUser($user);
            }
        } elseif ($request->path !== '/install') {
            return Response::redirect('/install');
        }

        $router = $this->routes($auth, $installer, $db);

        return $router->dispatch($request);
    }

    private function routes(AuthService $auth, InstallService $installer, Database $db): Router
    {
        $r = new Router();
        $csrf = new VerifyCsrf($this->csrf);
        $guest = new RequireGuest($this->context);
        $member = new RequireRole($this->context, $this->session);
        $tutor = new RequireRole($this->context, $this->session, ['tutor']);

        $pages = new PageController($this->view);
        $authCtl = new AuthController($auth, $this->session, $this->context, $this->view);
        $installCtl = new InstallController($installer, $this->session, $this->view, $this->storage('installed.lock'));
        $dashboard = new DashboardController($this->context, $this->view, $installer, $this->session);
        $studio = new StudioController($this->view);

        $lessonRepo = new LessonRepository($db);
        $lessonStorage = new LessonStorage($this->storage(''));
        $lessonService = new LessonService($lessonRepo, $lessonStorage);
        $uploadService = new UploadService(
            new LessonUploadRepository($db),
            $lessonRepo,
            $lessonStorage,
            $this->config->int('lessons.max_bytes', 150 * 1024 * 1024),
        );
        $lessons = new LessonController($lessonService, $this->context, $this->session, $this->view);
        $uploads = new UploadController($uploadService, $this->context);

        // Public
        $r->get('/', $pages->home(...));
        $r->get('/about', $pages->about(...));

        // Accounts
        $r->get('/login', $authCtl->showLogin(...), [$guest]);
        $r->post('/login', $authCtl->login(...), [$csrf, $guest]);
        $r->post('/logout', $authCtl->logout(...), [$csrf]);

        // Logged in
        $r->get('/dashboard', $dashboard->index(...), [$member]);

        $r->post('/dashboard/database-update', $dashboard->updateDatabase(...), [$csrf, $tutor]);

        // Lessons: watching (students see published lessons; tutor sees all)
        $r->get('/lessons', $lessons->index(...), [$member]);
        $r->get('/lessons/{id}', $lessons->show(...), [$member]);
        $r->get('/lessons/{id}/file', $lessons->file(...), [$member]);

        // Tutor: recording studio and lesson management
        $r->get('/studio/lab', $studio->lab(...), [$tutor]);
        $r->get('/studio/lessons', $lessons->manage(...), [$tutor]);
        $r->get('/studio/lessons/{id}', $lessons->edit(...), [$tutor]);
        $r->post('/studio/lessons/{id}', $lessons->update(...), [$csrf, $tutor]);
        $r->post('/studio/lessons/{id}/unpublish', $lessons->unpublish(...), [$csrf, $tutor]);
        $r->post('/studio/lessons/{id}/delete', $lessons->delete(...), [$csrf, $tutor]);

        // Tutor: resumable lesson upload API (JSON; CSRF token in X-CSRF-Token header)
        $r->post('/studio/uploads', $uploads->start(...), [$tutor, $csrf]);
        $r->post('/studio/uploads/{id}/chunk', $uploads->chunk(...), [$tutor, $csrf]);
        $r->post('/studio/uploads/{id}/finish', $uploads->finish(...), [$tutor, $csrf]);

        // One-time setup
        $r->get('/install', $installCtl->show(...));
        $r->post('/install', $installCtl->run(...), [$csrf]);

        return $r;
    }

    private function isInstalled(InstallService $installer): bool
    {
        $lock = $this->storage('installed.lock');
        if (is_file($lock)) {
            return true;
        }
        if ($installer->isInstalled()) {
            @file_put_contents($lock, date('c') . "\n");
            return true;
        }

        return false;
    }

    /**
     * Before installation the sessions table does not exist yet, so the installer
     * uses a private file session in storage/ (never web-reachable).
     */
    private function startSession(Request $request, ?DatabaseSessionHandler $handler): void
    {
        $handler ??= (function (): \SessionHandler {
            $dir = $this->storage('sessions');
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            session_save_path($dir);

            return new \SessionHandler();
        })();

        $this->session->start(
            $handler,
            $this->config->string('session.name', 'smartcircle'),
            $request->isHttps(),
            $this->config->int('session.idle_timeout', 28800),
        );
    }

    private function shareViewHelpers(): void
    {
        $this->view->share('appName', $this->config->string('app.name', 'SMART Circle'));
        $this->view->share('tagline', $this->config->string('app.tagline'));
        $this->view->share('ctx', $this->context);
        $this->view->share('csrf', $this->csrf);
        $this->view->share('flashes', fn (): array => session_status() === PHP_SESSION_ACTIVE ? $this->session->takeFlashes() : []);
        $this->view->share('asset', function (string $path): string {
            $file = $this->root . '/public/assets/' . ltrim($path, '/');
            $version = is_file($file) ? (string) filemtime($file) : '1';

            return '/assets/' . ltrim($path, '/') . '?v=' . $version;
        });
    }

    private function errorPage(int $status, string $message, string $detail = ''): Response
    {
        try {
            $html = $this->view->render('errors/error', [
                'title' => $status === 404 ? 'Page not found' : 'Something went wrong',
                'status' => $status,
                'message' => $message,
                'detail' => $detail,
            ]);
        } catch (Throwable) {
            $html = '<!doctype html><title>Error</title><p>' . e($message) . '</p>';
        }

        return Response::html($html, $status);
    }

    private function setupNeededPage(string $message): Response
    {
        return Response::html($this->view->render('errors/setup', [
            'title' => 'Setup needed',
            'message' => $message,
        ], 'layouts/minimal'), 503);
    }

    private function withSecurityHeaders(Response $response): Response
    {
        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'same-origin')
            ->withHeader('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=()')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:; "
                . "style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self'; "
                . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'"
            );
    }

    private function storage(string $path): string
    {
        return rtrim($this->config->string('paths.storage', $this->root . '/storage'), '/') . '/' . $path;
    }
}
