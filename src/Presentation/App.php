<?php

declare(strict_types=1);

namespace SmartCircle\Presentation;

use PDOException;
use SmartCircle\Application\Admissions\AccountSetupService;
use SmartCircle\Application\Admissions\ApplicationService;
use SmartCircle\Application\Admissions\DocumentService;
use SmartCircle\Application\Admissions\ReviewService;
use SmartCircle\Application\Assessments\AssessmentService;
use SmartCircle\Application\Assessments\ExamService;
use SmartCircle\Application\Auth\AuthService;
use SmartCircle\Application\Install\InstallService;
use SmartCircle\Application\Lessons\LessonService;
use SmartCircle\Application\Lessons\UploadService;
use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Application\Partners\PartnerService;
use SmartCircle\Application\Partners\SchoolService;
use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Application\Topics\RequestService;
use SmartCircle\Application\Topics\TopicService;
use SmartCircle\Data\Database;
use SmartCircle\Data\DatabaseSessionHandler;
use SmartCircle\Data\LessonStorage;
use SmartCircle\Data\Migrator;
use SmartCircle\Data\Repositories\ApplicationRepository;
use SmartCircle\Data\Repositories\AssessmentRepository;
use SmartCircle\Data\Repositories\QuestionRepository;
use SmartCircle\Data\Repositories\SubmissionRepository;
use SmartCircle\Data\Repositories\AccessRepository;
use SmartCircle\Data\Repositories\AuditLogRepository;
use SmartCircle\Data\Repositories\CoverageRepository;
use SmartCircle\Data\Repositories\PartnerRepository;
use SmartCircle\Data\Repositories\PledgeRepository;
use SmartCircle\Data\Repositories\SchoolRepository;
use SmartCircle\Data\Repositories\TextbookRepository;
use SmartCircle\Data\Repositories\DocumentRepository;
use SmartCircle\Data\Repositories\IntakeRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Data\Repositories\LessonRepository;
use SmartCircle\Data\Repositories\LessonUploadRepository;
use SmartCircle\Data\Repositories\LoginAttemptRepository;
use SmartCircle\Data\Repositories\NotificationRepository;
use SmartCircle\Data\Repositories\TopicRepository;
use SmartCircle\Data\Repositories\TopicRequestRepository;
use SmartCircle\Data\Repositories\UserRepository;
use SmartCircle\Presentation\Controllers\AdmissionsController;
use SmartCircle\Presentation\Controllers\ApplyController;
use SmartCircle\Presentation\Controllers\AssessmentController;
use SmartCircle\Presentation\Controllers\StudioAssessmentController;
use SmartCircle\Presentation\Controllers\AuthController;
use SmartCircle\Presentation\Controllers\DocumentController;
use SmartCircle\Presentation\Controllers\DashboardController;
use SmartCircle\Presentation\Controllers\HonestyController;
use SmartCircle\Presentation\Controllers\InstallController;
use SmartCircle\Presentation\Controllers\LessonController;
use SmartCircle\Presentation\Controllers\NotificationController;
use SmartCircle\Presentation\Controllers\PartnerController;
use SmartCircle\Presentation\Controllers\SchoolController;
use SmartCircle\Presentation\Controllers\StudioTopicController;
use SmartCircle\Presentation\Controllers\TopicController;
use SmartCircle\Presentation\Controllers\PageController;
use SmartCircle\Presentation\Controllers\StudioController;
use SmartCircle\Presentation\Controllers\UploadController;
use SmartCircle\Presentation\Controllers\WelcomeController;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Csrf;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Router;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\Middleware\RequireGuest;
use SmartCircle\Presentation\Middleware\RequireOnboarded;
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
                if ($user !== null && $user['role'] === 'student') {
                    $this->context->setNeedsOnboarding($this->accountSetup($db, $users, $audit)->needsOnboarding((int) $user['id']));
                }
            }
        } elseif ($request->path !== '/install') {
            return Response::redirect('/install');
        }

        $this->view->share('unreadCount', function () use ($db): int {
            $userId = $this->context->userId();

            return $userId === null ? 0 : $this->notifications($db)->unreadCount($userId);
        });

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
        $studio = new StudioController($this->view);

        $lessonRepo = new LessonRepository($db);
        $lessonStorage = new LessonStorage($this->storage(''));
        $topicService = new TopicService(new TopicRepository($db), new StudentRepository($db), new TextbookRepository($db));
        $accessService = new AccessService(new AccessRepository($db), new TopicRepository($db), new CoverageRepository($db), $this->notifications($db));
        $requestService = new RequestService(new TopicRepository($db), new TopicRequestRepository($db), $this->notifications($db), new CoverageRepository($db), $accessService, new AccessRepository($db));
        $lessonService = new LessonService($lessonRepo, $lessonStorage, $topicService, $requestService);
        $uploadService = new UploadService(
            new LessonUploadRepository($db),
            $lessonRepo,
            $lessonStorage,
            $this->config->int('lessons.max_bytes', 150 * 1024 * 1024),
        );
        $lessons = new LessonController($lessonService, $this->context, $this->session, $this->view, $topicService, $accessService);
        $topicsCtl = new TopicController($topicService, $requestService, $this->context, $this->session, $this->view, $accessService);
        $studioTopics = new StudioTopicController($topicService, $requestService, $this->session, $this->view);
        $notificationsCtl = new NotificationController($this->notifications($db), $this->context, $this->session, $this->view);
        $uploads = new UploadController($uploadService, $this->context);

        $users = new UserRepository($db);
        $audit = new AuditLogRepository($db);
        $applicationRepo = new ApplicationRepository($db);
        $intakeRepo = new IntakeRepository($db);
        $documentService = new DocumentService(new DocumentRepository($db));
        $applicationService = new ApplicationService(
            $applicationRepo,
            $intakeRepo,
            $this->storage(''),
            $this->config->int('admissions.min_seconds_to_fill', 20),
            $this->config->int('admissions.max_per_ip_per_day', 5),
        );
        $reviewService = new ReviewService($db, $applicationRepo, $intakeRepo, $users, new StudentRepository($db), $documentService, $audit);
        $apply = new ApplyController($applicationService, $documentService, $this->session, $this->context, $this->view);
        $admissions = new AdmissionsController($reviewService, $applicationService, $documentService, $this->context, $this->session, $this->view);
        $welcome = new WelcomeController($this->accountSetup($db, $users, $audit), $this->session, $this->context, $this->view);
        $docs = new DocumentController($documentService, $this->context, $this->session, $this->view, $this->config->string('app.url'));
        $onboarded = new RequireOnboarded($this->context);
        $student = new RequireRole($this->context, $this->session, ['student']);
        $partnerService = new PartnerService($db, $users, new PartnerRepository($db), new SchoolRepository($db), new StudentRepository($db), $documentService, $audit);
        $schoolService = new SchoolService(new SchoolRepository($db), new CoverageRepository($db), new TextbookRepository($db), $topicService, $accessService);
        $assessmentService = new AssessmentService(new QuestionRepository($db), new AssessmentRepository($db), new SubmissionRepository($db), $this->notifications($db), $accessService);
        $examService = new ExamService(new AssessmentRepository($db), new SubmissionRepository($db), new StudentRepository($db), $accessService);
        $studioAssess = new StudioAssessmentController($assessmentService, $topicService, $this->context, $this->session, $this->view);
        $assessCtl = new AssessmentController($examService, $this->context, $this->session, $this->view);
        $honesty = new HonestyController($accessService, $schoolService, $this->context, $this->session, $this->view);
        $partnersCtl = new PartnerController($partnerService, $schoolService, $this->context, $this->session, $this->view);
        $schoolsCtl = new SchoolController($schoolService, $partnerService, $this->context, $this->session, $this->view);
        $partner = new RequireRole($this->context, $this->session, ['partner']);
        $dashboard = new DashboardController($this->context, $this->view, $installer, $this->session, static function (int $userId) use ($partnerService, $schoolService): ?array {
            $p = $partnerService->find($userId);
            if ($p === null || empty($p['school_id'])) {
                return null;
            }
            $schoolId = (int) $p['school_id'];

            return [
                'partner' => $p,
                'progress' => $schoolService->progress($schoolId),
                'students' => $schoolService->studentsByForm($schoolId),
                'top' => $schoolService->topRequests($schoolId, $p['subject_list']),
                'books' => count($schoolService->textbooks($schoolId)),
                'letters' => $partnerService->letters($userId),
                'fresh' => $schoolService->freshness($schoolId),
            ];
        }, fn (int $userId): ?array => $this->accountSetup($db, $users, $audit)->latestPledge($userId));
        $pages->setSeatChart($applicationService->seatChart(...), $applicationService->openIntake(...));

        // Public
        $r->get('/', $pages->home(...));
        $r->get('/about', $pages->about(...));
        $r->get('/safeguarding', $pages->safeguarding(...));

        // Accounts
        $r->get('/login', $authCtl->showLogin(...), [$guest]);
        $r->post('/login', $authCtl->login(...), [$csrf, $guest]);
        $r->post('/logout', $authCtl->logout(...), [$csrf]);

        // Admissions (public)
        $r->get('/apply', $apply->show(...));
        $r->post('/apply', $apply->submit(...), [$csrf]);
        $r->get('/apply/quiz', $apply->quiz(...));
        $r->post('/apply/quiz', $apply->submitQuiz(...), [$csrf]);
        $r->get('/apply/done', $apply->done(...));
        $r->get('/apply/status', $apply->statusForm(...));
        $r->post('/apply/status', $apply->status(...), [$csrf]);
        $r->get('/letters/{code}', $docs->letter(...));
        $r->get('/verify', $docs->verifyForm(...));
        $r->get('/verify/{code}', $docs->verify(...));

        // New students: setup code -> password -> agreement
        $r->get('/welcome', $welcome->codeForm(...));
        $r->post('/welcome', $welcome->checkCode(...), [$csrf]);
        $r->get('/welcome/password', $welcome->passwordForm(...));
        $r->post('/welcome/password', $welcome->setPassword(...), [$csrf]);
        $r->get('/welcome/onboarding', $welcome->onboardingForm(...), [$student]);
        $r->post('/welcome/onboarding', $welcome->completeOnboarding(...), [$csrf, $student]);

        // Logged in
        $r->get('/dashboard', $dashboard->index(...), [$member, $onboarded]);

        $r->post('/dashboard/database-update', $dashboard->updateDatabase(...), [$csrf, $tutor]);

        // Lessons: watching (students see published lessons; tutor sees all)
        $r->get('/lessons', $lessons->index(...), [$member, $onboarded]);
        $r->get('/lessons/{id}', $lessons->show(...), [$member, $onboarded]);
        $r->get('/lessons/{id}/file', $lessons->file(...), [$member, $onboarded]);

        // Topics: the syllabus, and asking for help on a topic
        $r->get('/topics', $topicsCtl->index(...), [$member, $onboarded]);
        $r->get('/topics/{id}', $topicsCtl->show(...), [$member, $onboarded]);
        $r->post('/topics/{id}/request', $topicsCtl->ask(...), [$csrf, $student, $onboarded]);
        $r->post('/topics/{id}/report', $topicsCtl->report(...), [$csrf, $student, $onboarded]);

        // Notifications (the bell)
        $r->get('/notifications', $notificationsCtl->index(...), [$member]);
        $r->post('/notifications/read', $notificationsCtl->markRead(...), [$csrf, $member]);

        // Tutor: ranked topic requests and the topic catalogue
        $r->get('/studio/requests', $studioTopics->requests(...), [$tutor]);
        $r->get('/studio/requests/{id}', $studioTopics->request(...), [$tutor]);
        $r->post('/studio/requests/{id}/status', $studioTopics->setStatus(...), [$csrf, $tutor]);
        $r->get('/studio/checks', $honesty->checks(...), [$tutor]);
        $r->post('/studio/checks/exception', $honesty->decideException(...), [$csrf, $tutor]);
        $r->post('/studio/checks/report', $honesty->reviewReport(...), [$csrf, $tutor]);
        $r->get('/studio/honesty', $honesty->report(...), [$tutor]);
        $r->get('/studio/topics', $studioTopics->topics(...), [$tutor]);
        $r->post('/studio/topics', $studioTopics->addTopic(...), [$csrf, $tutor]);
        $r->post('/studio/topics/import', $studioTopics->importTopics(...), [$csrf, $tutor]);
        $r->get('/studio/topics/{id}', $studioTopics->editTopic(...), [$tutor]);
        $r->post('/studio/topics/{id}', $studioTopics->updateTopic(...), [$csrf, $tutor]);

        // School partners: their own school's coverage and textbooks
        $r->get('/partner/coverage', $schoolsCtl->coverage(...), [$partner]);
        $r->post('/partner/coverage', $schoolsCtl->saveCoverage(...), [$csrf, $partner]);
        $r->post('/partner/coverage/checked', $schoolsCtl->markChecked(...), [$csrf, $partner]);
        $r->get('/partner/textbooks', $schoolsCtl->textbooks(...), [$partner]);
        $r->post('/partner/textbooks', $schoolsCtl->addTextbook(...), [$csrf, $partner]);
        $r->get('/partner/textbooks/{book}', $schoolsCtl->refs(...), [$partner]);
        $r->post('/partner/textbooks/{book}', $schoolsCtl->saveRefs(...), [$csrf, $partner]);
        $r->post('/partner/textbooks/{book}/remove', $schoolsCtl->removeTextbook(...), [$csrf, $partner]);

        // Tutor: partners and schools (the tutor can also enter a school's reports)
        $r->get('/studio/partners', $partnersCtl->index(...), [$tutor]);
        $r->post('/studio/partners', $partnersCtl->invite(...), [$csrf, $tutor]);
        $r->get('/studio/partners/{id}', $partnersCtl->show(...), [$tutor]);
        $r->post('/studio/partners/{id}/code', $partnersCtl->newCode(...), [$csrf, $tutor]);
        $r->post('/studio/partners/{id}/code/hide', $partnersCtl->hideCode(...), [$csrf, $tutor]);
        $r->post('/studio/partners/{id}/status', $partnersCtl->setStatus(...), [$csrf, $tutor]);
        $r->get('/studio/schools/{school}', $schoolsCtl->summary(...), [$tutor]);
        $r->get('/studio/schools/{school}/coverage', $schoolsCtl->coverage(...), [$tutor]);
        $r->post('/studio/schools/{school}/coverage', $schoolsCtl->saveCoverage(...), [$csrf, $tutor]);
        $r->post('/studio/schools/{school}/coverage/checked', $schoolsCtl->markChecked(...), [$csrf, $tutor]);
        $r->get('/studio/schools/{school}/textbooks', $schoolsCtl->textbooks(...), [$tutor]);
        $r->post('/studio/schools/{school}/textbooks', $schoolsCtl->addTextbook(...), [$csrf, $tutor]);
        $r->get('/studio/schools/{school}/textbooks/{book}', $schoolsCtl->refs(...), [$tutor]);
        $r->post('/studio/schools/{school}/textbooks/{book}', $schoolsCtl->saveRefs(...), [$csrf, $tutor]);
        $r->post('/studio/schools/{school}/textbooks/{book}/remove', $schoolsCtl->removeTextbook(...), [$csrf, $tutor]);

        // Exams, assignments and exercises: students
        $r->get('/assessments', $assessCtl->index(...), [$student, $onboarded]);
        $r->get('/assessments/{id}', $assessCtl->show(...), [$student, $onboarded]);
        $r->post('/assessments/{id}/start', $assessCtl->start(...), [$csrf, $student, $onboarded]);
        $r->get('/assessments/{id}/take', $assessCtl->take(...), [$student, $onboarded]);
        $r->post('/assessments/{id}/save', $assessCtl->save(...), [$csrf, $student, $onboarded]);
        $r->get('/assessments/{id}/result', $assessCtl->result(...), [$student, $onboarded]);

        // Tutor: question bank, assessment builder, marking
        $r->get('/studio/questions', $studioAssess->bank(...), [$tutor]);
        $r->get('/studio/questions/new', $studioAssess->newQuestion(...), [$tutor]);
        $r->post('/studio/questions', $studioAssess->saveQuestion(...), [$csrf, $tutor]);
        $r->get('/studio/questions/{id}', $studioAssess->editQuestion(...), [$tutor]);
        $r->post('/studio/questions/{id}', $studioAssess->saveQuestion(...), [$csrf, $tutor]);
        $r->post('/studio/questions/{id}/delete', $studioAssess->deleteQuestion(...), [$csrf, $tutor]);
        $r->get('/studio/assessments', $studioAssess->index(...), [$tutor]);
        $r->get('/studio/assessments/new', $studioAssess->create(...), [$tutor]);
        $r->post('/studio/assessments', $studioAssess->create(...), [$csrf, $tutor]);
        $r->get('/studio/assessments/{id}', $studioAssess->show(...), [$tutor]);
        $r->post('/studio/assessments/{id}', $studioAssess->update(...), [$csrf, $tutor]);
        $r->post('/studio/assessments/{id}/questions', $studioAssess->addQuestion(...), [$csrf, $tutor]);
        $r->post('/studio/assessments/{id}/questions/{question}/remove', $studioAssess->removeQuestion(...), [$csrf, $tutor]);
        $r->post('/studio/assessments/{id}/questions/{question}/move', $studioAssess->moveQuestion(...), [$csrf, $tutor]);
        $r->get('/studio/assessments/{id}/submissions', $studioAssess->submissions(...), [$tutor]);
        $r->post('/studio/assessments/{id}/{action}', $studioAssess->action(...), [$csrf, $tutor]);
        $r->get('/studio/submissions/{id}', $studioAssess->markForm(...), [$tutor]);
        $r->post('/studio/submissions/{id}', $studioAssess->mark(...), [$csrf, $tutor]);

        // Tutor: recording studio and lesson management
        $r->get('/studio/lab', $studio->lab(...), [$tutor]);
        $r->get('/studio/lessons', $lessons->manage(...), [$tutor]);
        $r->get('/studio/lessons/{id}', $lessons->edit(...), [$tutor]);
        $r->post('/studio/lessons/{id}', $lessons->update(...), [$csrf, $tutor]);
        $r->post('/studio/lessons/{id}/unpublish', $lessons->unpublish(...), [$csrf, $tutor]);
        $r->post('/studio/lessons/{id}/delete', $lessons->delete(...), [$csrf, $tutor]);

        // Tutor: admissions
        $r->get('/studio/admissions', $admissions->index(...), [$tutor]);
        $r->get('/studio/admissions/intake', $admissions->intakeForm(...), [$tutor]);
        $r->post('/studio/admissions/intake', $admissions->intakeSave(...), [$csrf, $tutor]);
        $r->get('/studio/admissions/{id}', $admissions->show(...), [$tutor]);
        $r->post('/studio/admissions/{id}/decision', $admissions->decide(...), [$csrf, $tutor]);
        $r->post('/studio/admissions/{id}/code', $admissions->newCode(...), [$csrf, $tutor]);
        $r->post('/studio/admissions/{id}/code/hide', $admissions->forgetCode(...), [$csrf, $tutor]);
        $r->get('/studio/admissions/{id}/report', $admissions->report(...), [$tutor]);

        // Tutor: resumable lesson upload API (JSON; CSRF token in X-CSRF-Token header)
        $r->post('/studio/uploads', $uploads->start(...), [$tutor, $csrf]);
        $r->post('/studio/uploads/{id}/chunk', $uploads->chunk(...), [$tutor, $csrf]);
        $r->post('/studio/uploads/{id}/finish', $uploads->finish(...), [$tutor, $csrf]);

        // One-time setup
        $r->get('/install', $installCtl->show(...));
        $r->post('/install', $installCtl->run(...), [$csrf]);

        return $r;
    }

    private ?NotificationService $notifications = null;

    private function notifications(Database $db): NotificationService
    {
        return $this->notifications ??= new NotificationService(new NotificationRepository($db), new UserRepository($db));
    }

    private ?AccountSetupService $accountSetup = null;

    private function accountSetup(Database $db, UserRepository $users, AuditLogRepository $audit): AccountSetupService
    {
        return $this->accountSetup ??= new AccountSetupService(
            $users,
            new StudentRepository($db),
            $audit,
            new PledgeRepository($db),
            new DocumentService(new DocumentRepository($db)),
        );
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
