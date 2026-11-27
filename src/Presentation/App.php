<?php

declare(strict_types=1);

namespace SmartCircle\Presentation;

use SmartCircle\Data\Repositories\ContributionStatsRepository;
use SmartCircle\Application\Partners\ContributionCertificateService;
use SmartCircle\Presentation\Controllers\LessonReviewController;
use SmartCircle\Data\Repositories\LessonReviewRepository;
use SmartCircle\Application\Reviews\LessonReviewService;
use SmartCircle\Presentation\Controllers\TeacherRolesController;
use SmartCircle\Data\Repositories\QuestionSuggestionRepository;
use SmartCircle\Application\Partners\QuestionSuggestionService;
use SmartCircle\Presentation\Middleware\RequireGuestTeacher;
use SmartCircle\Data\Repositories\GuestRepository;
use SmartCircle\Application\Partners\GuestTeacherService;
use SmartCircle\Presentation\Controllers\EngagementController;
use SmartCircle\Data\Repositories\TemplateRepository;
use SmartCircle\Data\Repositories\EngagementRepository;
use SmartCircle\Application\Templates\TemplateService;
use SmartCircle\Application\Students\SeatCheckService;
use SmartCircle\Application\Students\DigestService;
use SmartCircle\Application\Jobs\JobRunner;
use PDOException;
use SmartCircle\Application\Admissions\AccountSetupService;
use SmartCircle\Application\Admissions\ApplicationService;
use SmartCircle\Application\Admissions\DocumentService;
use SmartCircle\Application\Admissions\ReviewService;
use SmartCircle\Application\Admin\BackupService;
use SmartCircle\Application\Alumni\AlumniService;
use SmartCircle\Application\Alumni\YearEndService;
use SmartCircle\Application\Assessments\AssessmentService;
use SmartCircle\Application\Assessments\ExamService;
use SmartCircle\Application\Assessments\RecheckService;
use SmartCircle\Application\Library\LibraryService;
use SmartCircle\Application\Mail\EmailSettings;
use SmartCircle\Application\Mail\MailService;
use SmartCircle\Application\Notes\NoteService;
use SmartCircle\Application\Reviews\NoteReviewService;
use SmartCircle\Application\Auth\AuthService;
use SmartCircle\Application\Calendar\CalendarService;
use SmartCircle\Application\Calendar\RevisionMode;
use SmartCircle\Application\Grades\GradeService;
use SmartCircle\Application\Guardians\GuardianService;
use SmartCircle\Application\Messages\MessageService;
use SmartCircle\Application\Messages\SupportService;
use SmartCircle\Application\Students\StudentOverviewService;
use SmartCircle\Application\Install\InstallService;
use SmartCircle\Application\Lessons\LessonService;
use SmartCircle\Application\Lessons\UploadService;
use SmartCircle\Application\Learning\FeedbackService;
use SmartCircle\Application\Learning\ImpactService;
use SmartCircle\Application\Learning\Quick5Service;
use SmartCircle\Application\Maneb\ManebService;
use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Application\OfficeHours\OfficeHoursService;
use SmartCircle\Application\Practice\PracticeService;
use SmartCircle\Application\Profile\AvatarService;
use SmartCircle\Application\Settings\SettingsService;
use SmartCircle\Application\Settings\SiteAssetService;
use SmartCircle\Application\Team\TeamService;
use SmartCircle\Application\Partners\ContributionService;
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
use SmartCircle\Data\Repositories\BackupRepository;
use SmartCircle\Data\Repositories\PracticeRepository;
use SmartCircle\Data\Repositories\SettingsRepository;
use SmartCircle\Data\Repositories\TeamRepository;
use SmartCircle\Data\Repositories\AlumniRepository;
use SmartCircle\Data\Repositories\CommunityRepository;
use SmartCircle\Data\Repositories\ManebRepository;
use SmartCircle\Data\Repositories\OfficeHoursRepository;
use SmartCircle\Data\Repositories\CalendarRepository;
use SmartCircle\Data\Repositories\ContributionRepository;
use SmartCircle\Data\Repositories\GradeRepository;
use SmartCircle\Data\Repositories\GuardianRepository;
use SmartCircle\Data\Repositories\MessageRepository;
use SmartCircle\Data\Repositories\OverviewRepository;
use SmartCircle\Data\Repositories\CoverageRepository;
use SmartCircle\Data\Repositories\LibraryRepository;
use SmartCircle\Data\Mail\SmtpTransport;
use SmartCircle\Data\Repositories\MailRepository;
use SmartCircle\Data\Repositories\NoteRepository;
use SmartCircle\Data\Repositories\ReviewRepository;
use SmartCircle\Data\Repositories\PartnerRepository;
use SmartCircle\Data\Repositories\RecheckRepository;
use SmartCircle\Data\Repositories\PledgeRepository;
use SmartCircle\Data\Repositories\SchoolRepository;
use SmartCircle\Data\Repositories\TextbookRepository;
use SmartCircle\Data\Repositories\DocumentRepository;
use SmartCircle\Data\Repositories\IntakeRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Data\Repositories\LearningRepository;
use SmartCircle\Data\Repositories\LessonRepository;
use SmartCircle\Data\Repositories\LessonUploadRepository;
use SmartCircle\Data\Repositories\LoginAttemptRepository;
use SmartCircle\Data\Repositories\NotificationRepository;
use SmartCircle\Data\Repositories\TopicRepository;
use SmartCircle\Data\Repositories\TopicRequestRepository;
use SmartCircle\Data\Repositories\UserRepository;
use SmartCircle\Presentation\Controllers\AdmissionsController;
use SmartCircle\Presentation\Controllers\AdminController;
use SmartCircle\Presentation\Controllers\TeamController;
use SmartCircle\Presentation\Controllers\AlumniController;
use SmartCircle\Presentation\Controllers\ManebController;
use SmartCircle\Presentation\Controllers\OfficeHoursController;
use SmartCircle\Presentation\Controllers\ApplyController;
use SmartCircle\Presentation\Controllers\AssessmentController;
use SmartCircle\Presentation\Controllers\StudioAssessmentController;
use SmartCircle\Presentation\Controllers\AuthController;
use SmartCircle\Presentation\Controllers\CalendarController;
use SmartCircle\Presentation\Controllers\ContributionController;
use SmartCircle\Presentation\Controllers\GradesController;
use SmartCircle\Presentation\Controllers\GuardianController;
use SmartCircle\Presentation\Controllers\ProfileController;
use SmartCircle\Presentation\Controllers\MessageController;
use SmartCircle\Presentation\Controllers\StudentsController;
use SmartCircle\Presentation\Controllers\SupportController;
use SmartCircle\Presentation\Controllers\DocumentController;
use SmartCircle\Presentation\Controllers\DashboardController;
use SmartCircle\Presentation\Controllers\HonestyController;
use SmartCircle\Presentation\Controllers\InstallController;
use SmartCircle\Presentation\Controllers\LearningController;
use SmartCircle\Presentation\Controllers\LessonController;
use SmartCircle\Presentation\Controllers\LibraryController;
use SmartCircle\Presentation\Controllers\MailController;
use SmartCircle\Presentation\Controllers\NoteController;
use SmartCircle\Presentation\Controllers\SiteAssetController;
use SmartCircle\Presentation\Controllers\ReviewController;
use SmartCircle\Presentation\Controllers\NotificationController;
use SmartCircle\Presentation\Controllers\StudioLibraryController;
use SmartCircle\Presentation\Controllers\StudioNoteController;
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
use SmartCircle\Presentation\Middleware\SubjectGuard;
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

        if ($this->config->secretsError !== '') {
            return $this->setupNeededPage($this->config->secretsError);
        }

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
                if ($user !== null && $user['role'] === 'partner') {
                    $scope = (new GuestTeacherService(new GuestRepository($db), $this->notifications($db)))->scope((int) $user['id']);
                    if ($scope !== null) {
                        $this->context->setGuestTeacher(true);
                        $this->context->setSubjects($scope);
                    }
                }
                if ($user !== null && $user['role'] === 'team') {
                    $team = $this->team($db, $users, $audit);
                    $this->context->setNeedsOnboarding($team->needsAgreement((int) $user['id']));
                    $this->context->setSubjects($team->scope((int) $user['id']));
                }
                // "Try as": remember the admin behind a practice account, and only for practice accounts.
                $practiceAdmin = $this->session->get('practice_admin');
                if ($user !== null && is_int($practiceAdmin) && (int) ($user['is_practice'] ?? 0) === 1) {
                    $this->context->setPracticeAdmin($practiceAdmin);
                } elseif ($practiceAdmin !== null) {
                    $this->session->forget('practice_admin');
                }
            }
            // New code waiting for "Update database": keep the site safe until the admin applies it.
            $pending = $installer->pendingUpdates();
            if ($pending !== []) {
                $gate = $this->updateGate($request, $pending);
                if ($gate !== null) {
                    return $gate;
                }
            } else {
                $this->afterResponse = fn () => $this->engagement($db)['jobs']->runDue();
            }
        } elseif ($request->path !== '/install') {
            return Response::redirect('/install');
        }

        $this->view->share('unreadCount', function () use ($db): int {
            $userId = $this->context->userId();

            return $userId === null ? 0 : $this->notifications($db)->unreadCount($userId);
        });

        $this->view->share('community', static fn (): ?string => (new SettingsService(new SettingsRepository($db)))->community());
        $this->view->share('siteAssets', fn (): array => (new SiteAssetService(new SettingsRepository($db), $this->storage('')))->versions());
        $this->view->share('messageCount', function () use ($db): int {
            $userId = $this->context->userId();

            return $userId === null ? 0 : (new MessageRepository($db))->unreadConversations($userId);
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
        $teacher = new RequireRole($this->context, $this->session, ['tutor', 'team']);
        // Lesson recording also opens to guest teachers (partner teachers the tutor has chosen).
        $recorder = [new RequireRole($this->context, $this->session, ['tutor', 'team', 'partner']), new RequireGuestTeacher($this->context)];
        $teamTutor = $this->team($db, new UserRepository($db), new AuditLogRepository($db));
        $guard = fn (?string $type = null): SubjectGuard => new SubjectGuard($this->context, $teamTutor, $type);
        $learner = new RequireRole($this->context, $this->session, ['tutor', 'student', 'partner']);
        $alumnus = new RequireRole($this->context, $this->session, ['alumni']);
        $booker = new RequireRole($this->context, $this->session, ['student', 'alumni']);

        $pages = new PageController($this->view);
        $authCtl = new AuthController($auth, $this->session, $this->context, $this->view);
        $installCtl = new InstallController($installer, $this->session, $this->view, $this->storage('installed.lock'));
        $studio = new StudioController($this->view);

        $lessonRepo = new LessonRepository($db);
        $lessonStorage = new LessonStorage($this->storage(''));
        $revisionMode = new RevisionMode(new CalendarRepository($db));
        $topicService = new TopicService(new TopicRepository($db), new StudentRepository($db), new TextbookRepository($db), $revisionMode);
        $accessService = new AccessService(new AccessRepository($db), new TopicRepository($db), new CoverageRepository($db), $this->notifications($db), $revisionMode);
        $requestService = new RequestService(new TopicRepository($db), new TopicRequestRepository($db), $this->notifications($db), new CoverageRepository($db), $accessService, new AccessRepository($db));
        $lessonService = new LessonService($lessonRepo, $lessonStorage, $topicService, $requestService);
        $uploadService = new UploadService(
            new LessonUploadRepository($db),
            $lessonRepo,
            $lessonStorage,
            $this->config->int('lessons.max_bytes', 150 * 1024 * 1024),
        );
        $guestTeachers = new GuestTeacherService(new GuestRepository($db), $this->notifications($db));
        $lessonReviews = new LessonReviewService(new LessonReviewRepository($db), new ReviewRepository($db), $this->notifications($db), $this->storage(''));
        $lessons = new LessonController($lessonService, $this->context, $this->session, $this->view, $topicService, $accessService, $guestTeachers, $lessonReviews);
        $lessonReviewCtl = new LessonReviewController($lessonReviews, $lessonService, $this->context, $this->session, $this->view);
        $topicsCtl = new TopicController($topicService, $requestService, $this->context, $this->session, $this->view, $accessService);
        $studioTopics = new StudioTopicController($topicService, $requestService, $this->session, $this->view, $this->context);
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
        $admissions = new AdmissionsController($reviewService, $applicationService, $documentService, $this->context, $this->session, $this->view, $this->mail($db), $this->config->string('app.url'), $this->engagement($db)['templates']);
        $welcome = new WelcomeController($this->accountSetup($db, $users, $audit), $this->session, $this->context, $this->view);
        $docs = new DocumentController($documentService, $this->context, $this->session, $this->view, $this->config->string('app.url'));
        $onboarded = new RequireOnboarded($this->context);
        $student = new RequireRole($this->context, $this->session, ['student']);
        $partnerService = new PartnerService($db, $users, new PartnerRepository($db), new SchoolRepository($db), new StudentRepository($db), $documentService, $audit);
        $calendarService = new CalendarService(new CalendarRepository($db));
        $schoolService = new SchoolService(new SchoolRepository($db), new CoverageRepository($db), new TextbookRepository($db), $topicService, $accessService, $calendarService);
        $gradeService = new GradeService(new GradeRepository($db), $calendarService, $accessService, $documentService, $this->notifications($db));
        $calendarCtl = new CalendarController($calendarService, $partnerService, $this->context, $this->session, $this->view);
        $messageService = new MessageService(new MessageRepository($db), $this->notifications($db), $accessService->allStudents(...));
        $supportService = new SupportService(new MessageRepository($db), $messageService, $this->notifications($db));
        $overviewService = new StudentOverviewService(new OverviewRepository($db), $gradeService, $calendarService);
        $messagesCtl = new MessageController($messageService, $this->context, $this->session, $this->view);
        $supportCtl = new SupportController($supportService, $overviewService, $this->context, $this->session, $this->view);
        $studentsCtl = new StudentsController($overviewService, $this->context, $this->session, $this->view);
        $studentRepo = new StudentRepository($db);
        $studentForm = static function (int $userId) use ($studentRepo): ?int {
            $p = $studentRepo->profile($userId);

            return $p !== null && $p['status'] === 'active' ? (int) $p['form'] : null;
        };
        $manebCtl = new ManebController(new ManebService(new ManebRepository($db), $studentRepo, $accessService, $this->notifications($db)),
            $studentRepo->profile(...), $this->context, $this->session, $this->view);
        $alumniService = new AlumniService(new AlumniRepository($db), new CommunityRepository($db), $this->notifications($db));
        $alumniCtl = new AlumniController($alumniService, new YearEndService(new AlumniRepository($db), $this->notifications($db), $audit),
            $studentForm, $this->context, $this->session, $this->view);
        $officeIds = static function (string $audience) use ($accessService, $messageService): array {
            if ($audience === 'alumni') {
                return array_map(static fn (array $p): int => (int) $p['id'], $messageService->alumni());
            }
            $form = str_starts_with($audience, 'form:') ? (int) substr($audience, 5) : null;

            return array_values(array_map(static fn (array $s): int => (int) $s['user_id'],
                array_filter($accessService->allStudents(), static fn (array $s): bool => $form === null || (int) $s['form'] === $form)));
        };
        $officeCtl = new OfficeHoursController(new OfficeHoursService(new OfficeHoursRepository($db), $this->notifications($db), $officeIds),
            $studentForm, $this->context, $this->session, $this->view);
        $settingsService = new SettingsService(new SettingsRepository($db));
        $siteAssetCtl = new SiteAssetController(new SiteAssetService(new SettingsRepository($db), $this->storage('')), $this->session);
        $teamCtl = new TeamController($this->team($db, $users, $audit), $this->context, $this->session, $this->view);
        $adminCtl = new AdminController($settingsService, new PracticeService(new PracticeRepository($db), $audit),
            new BackupService(new BackupRepository($db), $audit), $this->context, $this->session, $this->view);
        $guardianCtl = new GuardianController(new GuardianService(new GuardianRepository($db), new OverviewRepository($db), $this->notifications($db),
            $this->config->string('app.url'), $settingsService->tutorWhatsapp(...), $this->engagement($db)['templates']), $this->context, $this->session, $this->view, $this->mail($db));
        $profileCtl = new ProfileController(new AvatarService($users, $this->storage('')), $users->avatarAt(...), $this->context, $this->session, $this->view, new EmailSettings($users));
        $contributionService = new ContributionService(new ContributionRepository($db), $calendarService, $topicService, $this->notifications($db),
            $this->storage(''), $settingsService->tutorWhatsapp(...));
        $contribCtl = new ContributionController($contributionService, $partnerService, $this->context, $this->session, $this->view);
        $learningRepo = new LearningRepository($db);
        $feedbackService = new FeedbackService($learningRepo, $calendarService);
        $quick5Service = new Quick5Service($learningRepo, new StudentRepository($db), $accessService);
        $learningCtl = new LearningController($quick5Service, $feedbackService, new ImpactService($learningRepo, $gradeService, $calendarService),
            $studentForm, $this->context, $this->session, $this->view);
        $gradesCtl = new GradesController($gradeService, $calendarService, $this->context, $this->session, $this->view);
        $assessmentService = new AssessmentService(new QuestionRepository($db), new AssessmentRepository($db), new SubmissionRepository($db), $this->notifications($db), $accessService);
        $examService = new ExamService(new AssessmentRepository($db), new SubmissionRepository($db), new StudentRepository($db), $accessService, $this->notifications($db));
        $recheckService = new RecheckService(new RecheckRepository($db), new SubmissionRepository($db), new AssessmentRepository($db), $this->notifications($db));
        $memberSummary = function () use ($assessmentService, $recheckService, $messageService, $accessService, $gradeService): ?array {
            $uid = $this->context->userId();
            if ($uid === null) {
                return null;
            }
            $role = (string) $this->context->role();
            $toMark = array_filter($assessmentService->toMark(), fn (array $r): bool => $this->context->canTeach((string) $r['subject']));
            $student = $role === 'student' ? $gradeService->student($uid) : null;

            return [
                'role' => $role,
                'user' => $this->context->user(),
                'unread' => $messageService->unread($uid),
                'to_mark' => in_array($role, ['tutor', 'team'], true) ? count($toMark) : 0,
                'rechecks' => $role === 'tutor' ? $recheckService->countOpen() : 0,
                'subjects' => $this->context->subjects(),
                'revision' => $student !== null ? $accessService->revision((int) $student['form']) : null,
                'form' => $student !== null ? (int) $student['form'] : null,
            ];
        };
        $pages->setMemberHome($memberSummary);
        $noteService = new NoteService(new NoteRepository($db), new AssessmentRepository($db), new SubmissionRepository($db), new TopicRepository($db),
            new StudentRepository($db), $accessService, $examService, $this->notifications($db), $this->storage(''));
        $studioAssess = new StudioAssessmentController($assessmentService, $topicService, $this->context, $this->session, $this->view, $recheckService);
        $assessCtl = new AssessmentController($examService, $this->context, $this->session, $this->view, $recheckService);
        $noteReviews = new NoteReviewService(new ReviewRepository($db), $this->notifications($db), $this->storage(''));
        $reviewCtl = new ReviewController($noteReviews, $this->context, $this->session, $this->view, $lessonReviews);
        $libraryService = new LibraryService(new LibraryRepository($db), $this->storage(''));
        $libraryCtl = new LibraryController($libraryService, $this->view, $this->config->string('app.url'), $noteReviews);
        $studioLibrary = new StudioLibraryController($libraryService, $this->session);
        $studioNotes = new StudioNoteController($noteService, $topicService, $this->context, $this->session, $this->view, $noteReviews);
        $notesCtl = new NoteController($noteService, $this->context, $this->session, $this->view, $noteReviews);
        $honesty = new HonestyController($accessService, $schoolService, $this->context, $this->session, $this->view);
        $partnersCtl = new PartnerController($partnerService, $schoolService, $this->context, $this->session, $this->view,
            new ContributionCertificateService(new ContributionStatsRepository($db), new PartnerRepository($db), $documentService, $this->notifications($db)));
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
        }, fn (int $userId): ?array => $this->accountSetup($db, $users, $audit)->latestPledge($userId),
            static fn (int $userId): array => $examService->recentResults($userId),
            static function (int $userId, string $role) use ($calendarService, $gradeService, $accessService): array {
                $student = $role === 'student' ? $gradeService->student($userId) : null;

                return ['today' => $calendarService->today(), 'maneb' => $student !== null ? $calendarService->maneb((int) $student['form']) : [],
                    'form' => $student !== null ? (int) $student['form'] : null,
                    'revision' => $student !== null ? $accessService->revision((int) $student['form']) : null];
            });
        $dashboard->setLearning(static fn (int $userId): array => ['ask_feedback' => $feedbackService->shouldAsk($userId)]);
        $dashboard->setSummary($memberSummary);
        $dashboard->setRooms(static fn (int $userId): array => (new MessageRepository($db))->inbox($userId, 'support'));
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
        $r->get('/lessons', $lessons->index(...), [$learner, $onboarded]);
        $r->get('/lessons/{id}', $lessons->show(...), [$learner, $onboarded]);
        $r->get('/lessons/{id}/file', $lessons->file(...), [$learner, $onboarded]);

        // Topics: the syllabus, and asking for help on a topic
        $r->get('/topics', $topicsCtl->index(...), [$learner, $onboarded]);
        $r->get('/topics/{id}', $topicsCtl->show(...), [$learner, $onboarded]);
        $r->post('/topics/{id}/request', $topicsCtl->ask(...), [$csrf, $student, $onboarded]);
        $r->post('/topics/{id}/report', $topicsCtl->report(...), [$csrf, $student, $onboarded]);

        // Notifications (the bell)
        $r->get('/notifications', $notificationsCtl->index(...), [$member]);
        $r->post('/notifications/read', $notificationsCtl->markRead(...), [$csrf, $member]);

        // Tutor: ranked topic requests and the topic catalogue
        $r->get('/studio/requests', $studioTopics->requests(...), [$teacher, $onboarded, $guard()]);
        $r->get('/studio/requests/{id}', $studioTopics->request(...), [$teacher, $onboarded, $guard('request')]);
        $r->post('/studio/requests/{id}/status', $studioTopics->setStatus(...), [$csrf, $teacher, $onboarded, $guard('request')]);
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

        $r->post('/assessments/{id}/recheck', $assessCtl->recheck(...), [$csrf, $student, $onboarded]);

        // Notes and slides: students read up to each checkpoint
        $r->get('/notes', $notesCtl->index(...), [$student, $onboarded]);
        $r->get('/notes/{id}', $notesCtl->show(...), [$student, $onboarded]);
        $r->post('/notes/{id}/checkpoint/{block}', $notesCtl->submit(...), [$csrf, $student, $onboarded]);
        $r->get('/notes/{id}/images/{block}', $notesCtl->image(...), [$student, $onboarded]);

        // Tutor: notes and slides
        $r->get('/studio/notes', $studioNotes->index(...), [$teacher, $onboarded, $guard()]);
        $r->get('/studio/notes/new', $studioNotes->create(...), [$teacher, $onboarded, $guard()]);
        $r->post('/studio/notes', $studioNotes->create(...), [$csrf, $teacher, $onboarded, $guard()]);
        $r->get('/studio/notes/{id}', $studioNotes->edit(...), [$teacher, $onboarded, $guard('note')]);
        $r->post('/studio/notes/{id}', $studioNotes->update(...), [$csrf, $teacher, $onboarded, $guard('note')]);
        $r->post('/studio/notes/{id}/text', $studioNotes->addText(...), [$csrf, $teacher, $onboarded, $guard('note')]);
        $r->post('/studio/notes/{id}/image', $studioNotes->addImage(...), [$csrf, $teacher, $onboarded, $guard('note')]);
        $r->post('/studio/notes/{id}/checkpoint', $studioNotes->addCheckpoint(...), [$csrf, $teacher, $onboarded, $guard('note')]);
        $r->get('/studio/notes/{id}/images/{block}', $studioNotes->image(...), [$teacher, $onboarded, $guard('note')]);
        $r->post('/studio/notes/{id}/blocks/{block}', $studioNotes->updateBlock(...), [$csrf, $teacher, $onboarded, $guard('note')]);
        $r->post('/studio/notes/{id}/blocks/{block}/align', $studioNotes->alignBlock(...), [$csrf, $teacher, $onboarded, $guard('note')]);
        $r->post('/studio/notes/{id}/blocks/{block}/delete', $studioNotes->deleteBlock(...), [$csrf, $teacher, $onboarded, $guard('note')]);
        $r->post('/studio/notes/{id}/blocks/{block}/move', $studioNotes->moveBlock(...), [$csrf, $teacher, $onboarded, $guard('note')]);
        $r->post('/studio/notes/{id}/review', $reviewCtl->request(...), [$csrf, $teacher, $onboarded, $guard('note')]);
        $r->post('/studio/notes/{id}/library/{action}', $studioLibrary->note(...), [$csrf, $teacher, $onboarded, $guard('note')]);
        $r->post('/studio/notes/{id}/{action}', $studioNotes->action(...), [$csrf, $teacher, $onboarded, $guard('note')]);

        // Question Contributor and Guest Teacher
        $rolesCtl = new TeacherRolesController(new QuestionSuggestionService(new QuestionSuggestionRepository($db), new QuestionRepository($db), new ReviewRepository($db), new TopicRepository($db), $this->notifications($db)),
            $guestTeachers, $topicService, $this->context, $this->session, $this->view);
        $r->get('/partner/questions', $rolesCtl->questions(...), [$partner]);
        $r->post('/partner/questions', $rolesCtl->suggest(...), [$csrf, $partner]);
        $r->get('/studio/question-suggestions', $rolesCtl->review(...), [$tutor]);
        $r->post('/studio/question-suggestions/{id}', $rolesCtl->decide(...), [$csrf, $tutor]);
        $r->post('/studio/partners/{id}/guest', $rolesCtl->setGuest(...), [$csrf, $tutor]);
        $r->post('/studio/partners/{id}/certificate', $partnersCtl->certificate(...), [$csrf, $tutor]);

        // Partner teachers check notes and sign
        $r->get('/partner/reviews', $reviewCtl->queue(...), [$partner]);
        $r->get('/partner/reviews/{id}', $reviewCtl->show(...), [$partner]);
        $r->post('/partner/reviews/{id}', $reviewCtl->decide(...), [$csrf, $partner]);
        $r->get('/partner/reviews/{id}/images/{block}', $reviewCtl->image(...), [$partner]);
        $r->get('/partner/signature', $reviewCtl->signatureForm(...), [$partner]);
        $r->post('/partner/signature', $reviewCtl->saveSignature(...), [$csrf, $partner]);
        $r->get('/partner/signature/image', $reviewCtl->mySignature(...), [$partner]);
        $r->get('/reviews/{id}/signature', $reviewCtl->signature(...));
        $r->get('/partner/reviews/lessons/{id}', $lessonReviewCtl->show(...), [$partner]);
        $r->post('/partner/reviews/lessons/{id}', $lessonReviewCtl->decide(...), [$csrf, $partner]);
        $r->get('/partner/reviews/lessons/{id}/file', $lessonReviewCtl->file(...), [$partner]);
        $r->get('/partner/reviews/lessons/{id}/handout', $lessonReviewCtl->handout(...), [$partner]);
        $r->get('/reviews/lessons/{id}/signature', $lessonReviewCtl->signature(...));
        $r->post('/studio/lessons/{id}/review', $lessonReviewCtl->request(...), [$csrf, $teacher, $onboarded, $guard('lesson')]);

        // The free public library: no account needed
        $r->get('/library', $libraryCtl->index(...));
        $r->get('/library/notes/{id}', $libraryCtl->note(...));
        $r->get('/library/notes/{id}/print', $libraryCtl->printNote(...));
        $r->get('/library/notes/{id}/images/{block}', $libraryCtl->image(...));
        $r->get('/library/lessons/{id}/handout', $libraryCtl->handout(...));

        // Messages, the private room and the student overview
        $r->get('/messages', $messagesCtl->index(...), [$member, $onboarded]);
        $r->get('/messages/new', $messagesCtl->create(...), [$member, $onboarded]);
        $r->post('/messages', $messagesCtl->store(...), [$csrf, $member, $onboarded]);
        $r->post('/messages/announce', $messagesCtl->announce(...), [$csrf, $teacher, $onboarded]);
        $r->get('/messages/{id}', $messagesCtl->show(...), [$member, $onboarded]);
        $r->post('/messages/{id}/reply', $messagesCtl->reply(...), [$csrf, $member, $onboarded]);
        $r->get('/support', $supportCtl->mine(...), [$student, $onboarded]);
        $r->post('/support', $supportCtl->send(...), [$csrf, $student, $onboarded]);
        $r->get('/studio/support', $supportCtl->index(...), [$tutor]);
        $r->get('/studio/support/{id}', $supportCtl->show(...), [$teacher, $onboarded]);
        $r->post('/studio/support/{id}', $supportCtl->reply(...), [$csrf, $teacher, $onboarded]);
        $r->post('/studio/support/{id}/members', $supportCtl->addMember(...), [$csrf, $tutor]);
        $r->post('/studio/support/{id}/guardian', $supportCtl->guardianNotified(...), [$csrf, $tutor]);
        $r->get('/studio/students', $studentsCtl->index(...), [$tutor]);
        $r->get('/studio/students/{id}', $studentsCtl->show(...), [$tutor]);
        $r->post('/studio/students/{id}/contact', $studentsCtl->logContact(...), [$csrf, $tutor]);
        $r->post('/studio/students/{id}/invite', $supportCtl->invite(...), [$csrf, $tutor]);

        // MANEB, alumni and office hours
        $r->get('/results', $manebCtl->public(...));
        $r->get('/revision', $manebCtl->revision(...), [$student, $onboarded]);
        $r->get('/results/mine', $manebCtl->results(...), [$booker, $onboarded]);
        $r->post('/results/mine', $manebCtl->saveResults(...), [$csrf, $booker, $onboarded]);
        $r->get('/studio/results', $manebCtl->studio(...), [$tutor]);
        $r->post('/studio/results/{id}/verify', $manebCtl->verify(...), [$csrf, $tutor]);
        $r->get('/alumni', $alumniCtl->home(...), [$alumnus]);
        $r->post('/alumni', $alumniCtl->saveProfile(...), [$csrf, $alumnus]);
        $r->get('/alumni/community', $alumniCtl->community(...), [$member, $onboarded]);
        $r->post('/alumni/community', $alumniCtl->createPost(...), [$csrf, $member, $onboarded]);
        $r->get('/alumni/community/{id}', $alumniCtl->post(...), [$member, $onboarded]);
        $r->post('/alumni/community/{id}/reply', $alumniCtl->reply(...), [$csrf, $member, $onboarded]);
        $r->post('/alumni/community/{id}/moderate', $alumniCtl->moderate(...), [$csrf, $tutor]);
        $r->get('/studio/alumni', $alumniCtl->studio(...), [$tutor]);
        $r->get('/studio/year-end', $alumniCtl->yearEnd(...), [$tutor]);
        $r->post('/studio/year-end', $alumniCtl->applyYearEnd(...), [$csrf, $tutor]);
        $r->get('/office-hours', $officeCtl->index(...), [$booker, $onboarded]);
        $r->post('/office-hours/{id}/book', $officeCtl->book(...), [$csrf, $booker, $onboarded]);
        $r->post('/office-hours/{id}/cancel', $officeCtl->cancel(...), [$csrf, $booker, $onboarded]);
        $r->get('/studio/office-hours', $officeCtl->studio(...), [$teacher, $onboarded, $guard()]);
        $r->post('/studio/office-hours', $officeCtl->create(...), [$csrf, $teacher, $onboarded, $guard()]);
        $r->get('/studio/office-hours/{id}', $officeCtl->slot(...), [$teacher, $onboarded, $guard()]);
        $r->post('/studio/office-hours/{id}/cancel', $officeCtl->cancelSlot(...), [$csrf, $teacher, $onboarded, $guard()]);
        $r->post('/studio/office-hours/bookings/{id}', $officeCtl->markBooking(...), [$csrf, $teacher, $onboarded, $guard()]);

        // The SMART Circle Team, "Try as", settings and backup (admin)
        $teamOnly = new RequireRole($this->context, $this->session, ['team']);
        $r->get('/studio/team', $teamCtl->index(...), [$tutor]);
        $r->post('/studio/team', $teamCtl->invite(...), [$csrf, $tutor]);
        $r->get('/studio/team/{id}', $teamCtl->show(...), [$tutor]);
        $r->post('/studio/team/{id}/code', $teamCtl->newCode(...), [$csrf, $tutor]);
        $r->post('/studio/team/{id}/code/hide', $teamCtl->hideCode(...), [$csrf, $tutor]);
        $r->post('/studio/team/{id}/subjects', $teamCtl->subjects(...), [$csrf, $tutor]);
        $r->post('/studio/team/{id}/status', $teamCtl->status(...), [$csrf, $tutor]);
        $r->get('/team/agreement', $teamCtl->agreement(...), [$teamOnly]);
        $r->post('/team/agreement', $teamCtl->agree(...), [$csrf, $teamOnly]);
        $r->get('/studio/settings', $adminCtl->settings(...), [$tutor]);
        $mailCtl = new MailController($this->mail($db), $this->context, $this->session, $this->view, $this->engagement($db)['jobs']);
        $engagementCtl = new EngagementController($this->engagement($db)['seats'], $this->engagement($db)['templates'], $this->engagement($db)['jobs'], $this->context, $this->session, $this->view);
        $r->get('/studio/seats', $engagementCtl->seats(...), [$tutor]);
        $r->post('/studio/seats/{id}/nudge', $engagementCtl->nudge(...), [$csrf, $tutor]);
        $r->post('/studio/seats/{id}/pause', $engagementCtl->pause(...), [$csrf, $tutor]);
        $r->post('/studio/seats/{id}/release', $engagementCtl->release(...), [$csrf, $tutor]);
        $r->get('/studio/templates', $engagementCtl->templates(...), [$tutor]);
        $r->get('/studio/templates/{key}', $engagementCtl->template(...), [$tutor]);
        $r->post('/studio/templates/{key}', $engagementCtl->saveTemplate(...), [$csrf, $tutor]);
        $r->post('/studio/templates/{key}/reset', $engagementCtl->resetTemplate(...), [$csrf, $tutor]);
        $r->post('/studio/digest/send', $engagementCtl->digestNow(...), [$csrf, $tutor]);
        $r->get('/studio/mail', $mailCtl->index(...), [$tutor]);
        $r->post('/studio/mail/send', $mailCtl->sendNow(...), [$csrf, $tutor]);
        $r->post('/studio/mail/test', $mailCtl->test(...), [$csrf, $tutor]);
        $r->post('/studio/mail/retry', $mailCtl->retry(...), [$csrf, $tutor]);
        $r->post('/studio/guardians/{id}/email', $guardianCtl->email(...), [$csrf, $tutor]);
        $r->post('/studio/settings/{kind}/upload', $siteAssetCtl->upload(...), [$csrf, $tutor]);
        $r->post('/studio/settings/{kind}/remove', $siteAssetCtl->remove(...), [$csrf, $tutor]);
        $r->get('/site/{kind}', $siteAssetCtl->show(...));
        $r->post('/studio/settings', $adminCtl->saveSettings(...), [$csrf, $tutor]);
        $r->get('/studio/practice', $adminCtl->practice(...), [$tutor]);
        $r->post('/studio/practice/enter', $adminCtl->enter(...), [$csrf, $tutor]);
        $r->post('/studio/practice/reset', $adminCtl->reset(...), [$csrf, $tutor]);
        $r->post('/practice/leave', $adminCtl->leave(...), [$csrf, $member]);
        $r->post('/studio/backup', $adminCtl->backup(...), [$csrf, $tutor]);

        // Guardians, profiles and photos
        $r->get('/g/{token}', $guardianCtl->link(...));
        $r->post('/g/{token}', $guardianCtl->answer(...), [$csrf]);
        $r->get('/studio/guardians', $guardianCtl->index(...), [$tutor]);
        $r->get('/studio/guardians/{id}', $guardianCtl->show(...), [$tutor]);
        $r->post('/studio/guardians/{id}', $guardianCtl->update(...), [$csrf, $tutor]);
        $r->post('/studio/guardians/{id}/confirm', $guardianCtl->confirm(...), [$csrf, $tutor]);
        $r->post('/studio/guardians/{id}/sent', $guardianCtl->sent(...), [$csrf, $tutor]);
        $r->get('/profile', $profileCtl->show(...), [$member, $onboarded]);
        $r->post('/profile/photo', $profileCtl->savePhoto(...), [$csrf, $member, $onboarded]);
        $r->post('/profile/email', $profileCtl->saveEmail(...), [$csrf, $member, $onboarded]);
        $r->post('/profile/photo/remove', $profileCtl->removePhoto(...), [$csrf, $member, $onboarded]);
        $r->get('/avatars/{id}', $profileCtl->photo(...), [$member]);

        // Partner teachers' contributions
        $r->get('/partner/contribute', $contribCtl->page(...), [$partner]);
        $r->post('/partner/contribute/calendar', $contribCtl->calendar(...), [$csrf, $partner]);
        $r->post('/partner/contribute/resource', $contribCtl->share(...), [$csrf, $partner]);
        $r->post('/partner/contribute/suggest', $contribCtl->suggest(...), [$csrf, $partner]);
        $r->get('/partner/resources/{id}/file', $contribCtl->partnerFile(...), [$partner]);
        $r->get('/studio/contributions', $contribCtl->review(...), [$tutor]);
        $r->post('/studio/contributions/resources/{id}/reviewed', $contribCtl->reviewed(...), [$csrf, $tutor]);
        $r->get('/studio/resources/{id}/file', $contribCtl->adminFile(...), [$tutor]);
        $r->post('/studio/contributions/suggestions/{id}', $contribCtl->decide(...), [$csrf, $tutor]);
        $r->post('/studio/contributions/calendar/{id}/resolve', $contribCtl->resolve(...), [$csrf, $tutor]);

        // Quick 5, termly feedback and impact
        $r->get('/quick5', $learningCtl->quick5(...), [$student, $onboarded]);
        $r->post('/quick5', $learningCtl->check(...), [$csrf, $student, $onboarded]);
        $r->get('/feedback', $learningCtl->feedbackForm(...), [$student, $onboarded]);
        $r->post('/feedback', $learningCtl->sendFeedback(...), [$csrf, $student, $onboarded]);
        $r->get('/studio/impact', $learningCtl->impact(...), [$tutor]);
        $r->get('/studio/feedback', $learningCtl->feedbackReport(...), [$tutor]);

        // Grades and the academic calendar
        $r->get('/grades', $gradesCtl->mine(...), [$student, $onboarded]);
        $r->get('/studio/grades', $gradesCtl->book(...), [$tutor]);
        $r->get('/studio/grades/settings', $gradesCtl->settings(...), [$tutor]);
        $r->post('/studio/grades/settings', $gradesCtl->saveSettings(...), [$csrf, $tutor]);
        $r->post('/studio/grades/issue', $gradesCtl->issue(...), [$csrf, $tutor]);
        $r->get('/studio/grades/student/{id}', $gradesCtl->student(...), [$tutor]);
        $r->get('/studio/calendar', $calendarCtl->show(...), [$tutor]);
        $r->post('/studio/calendar/year', $calendarCtl->saveYear(...), [$csrf, $tutor]);
        $r->post('/studio/calendar/events', $calendarCtl->addEvent(...), [$csrf, $tutor]);
        $r->post('/studio/calendar/events/{id}/delete', $calendarCtl->deleteEvent(...), [$csrf, $tutor]);
        $r->get('/partner/calendar', $calendarCtl->show(...), [$partner]);
        $r->post('/partner/calendar/events', $calendarCtl->addEvent(...), [$csrf, $partner]);
        $r->post('/partner/calendar/events/{id}/delete', $calendarCtl->deleteEvent(...), [$csrf, $partner]);

        // Tutor: marking queue and re-checks
        $r->get('/studio/marking', $studioAssess->queue(...), [$teacher, $onboarded, $guard()]);
        $r->get('/studio/rechecks', $studioAssess->rechecks(...), [$teacher, $onboarded, $guard()]);
        $r->get('/studio/rechecks/{id}', $studioAssess->recheck(...), [$teacher, $onboarded, $guard('recheck')]);
        $r->post('/studio/rechecks/{id}', $studioAssess->resolveRecheck(...), [$csrf, $teacher, $onboarded, $guard('recheck')]);

        // Tutor: question bank, assessment builder, marking
        $r->get('/studio/questions', $studioAssess->bank(...), [$teacher, $onboarded, $guard()]);
        $r->get('/studio/questions/new', $studioAssess->newQuestion(...), [$teacher, $onboarded, $guard()]);
        $r->post('/studio/questions', $studioAssess->saveQuestion(...), [$csrf, $teacher, $onboarded, $guard()]);
        $r->get('/studio/questions/{id}', $studioAssess->editQuestion(...), [$teacher, $onboarded, $guard('question')]);
        $r->post('/studio/questions/{id}', $studioAssess->saveQuestion(...), [$csrf, $teacher, $onboarded, $guard('question')]);
        $r->post('/studio/questions/{id}/delete', $studioAssess->deleteQuestion(...), [$csrf, $teacher, $onboarded, $guard('question')]);
        $r->get('/studio/assessments', $studioAssess->index(...), [$teacher, $onboarded, $guard()]);
        $r->get('/studio/assessments/new', $studioAssess->create(...), [$teacher, $onboarded, $guard()]);
        $r->post('/studio/assessments', $studioAssess->create(...), [$csrf, $teacher, $onboarded, $guard()]);
        $r->get('/studio/assessments/{id}', $studioAssess->show(...), [$teacher, $onboarded, $guard('assessment')]);
        $r->post('/studio/assessments/{id}', $studioAssess->update(...), [$csrf, $teacher, $onboarded, $guard('assessment')]);
        $r->post('/studio/assessments/{id}/questions', $studioAssess->addQuestion(...), [$csrf, $teacher, $onboarded, $guard('assessment')]);
        $r->post('/studio/assessments/{id}/questions/{question}/remove', $studioAssess->removeQuestion(...), [$csrf, $teacher, $onboarded, $guard('assessment')]);
        $r->post('/studio/assessments/{id}/questions/{question}/move', $studioAssess->moveQuestion(...), [$csrf, $teacher, $onboarded, $guard('assessment')]);
        $r->get('/studio/assessments/{id}/submissions', $studioAssess->submissions(...), [$teacher, $onboarded, $guard('assessment')]);
        $r->post('/studio/assessments/{id}/{action}', $studioAssess->action(...), [$csrf, $teacher, $onboarded, $guard('assessment')]);
        $r->get('/studio/submissions/{id}', $studioAssess->markForm(...), [$teacher, $onboarded, $guard('submission')]);
        $r->post('/studio/submissions/{id}', $studioAssess->mark(...), [$csrf, $teacher, $onboarded, $guard('submission')]);

        // Tutor: recording studio and lesson management
        $r->get('/studio/lab', $studio->lab(...), [...$recorder, $onboarded, $guard()]);
        $r->get('/studio/lessons', $lessons->manage(...), [...$recorder, $onboarded, $guard()]);
        $r->get('/studio/lessons/{id}', $lessons->edit(...), [...$recorder, $onboarded, $guard('lesson')]);
        $r->post('/studio/lessons/{id}', $lessons->update(...), [$csrf, ...$recorder, $onboarded, $guard('lesson')]);
        $r->get('/studio/lessons/{id}/handout', $studioLibrary->download(...), [...$recorder, $onboarded, $guard('lesson')]);
        $r->post('/studio/lessons/{id}/handout', $studioLibrary->upload(...), [$csrf, ...$recorder, $onboarded, $guard('lesson')]);
        $r->post('/studio/lessons/{id}/handout/{action}', $studioLibrary->handout(...), [$csrf, ...$recorder, $onboarded, $guard('lesson')]);
        $r->post('/studio/lessons/{id}/unpublish', $lessons->unpublish(...), [$csrf, $teacher, $onboarded, $guard('lesson')]);
        $r->post('/studio/lessons/{id}/delete', $lessons->delete(...), [$csrf, ...$recorder, $onboarded, $guard('lesson')]);

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
        $r->post('/studio/uploads', $uploads->start(...), [...$recorder, $csrf, $onboarded, $guard()]);
        $r->post('/studio/uploads/{id}/chunk', $uploads->chunk(...), [...$recorder, $csrf, $onboarded, $guard('upload')]);
        $r->post('/studio/uploads/{id}/finish', $uploads->finish(...), [...$recorder, $csrf, $onboarded, $guard('upload')]);

        // One-time setup
        $r->get('/install', $installCtl->show(...));
        $r->post('/install', $installCtl->run(...), [$csrf]);

        return $r;
    }

    private ?NotificationService $notifications = null;

    private function team(Database $db, UserRepository $users, AuditLogRepository $audit): TeamService
    {
        return new TeamService($db, $users, new TeamRepository($db), new StudentRepository($db), $audit);
    }

    private function notifications(Database $db): NotificationService
    {
        return $this->notifications ??= new NotificationService(new NotificationRepository($db), new UserRepository($db), $this->mail($db));
    }

    private ?MailService $mailService = null;

    private function mail(Database $db): MailService
    {
        $password = $this->config->string('mail.password');
        $transport = $password === '' ? null : new SmtpTransport(
            $this->config->string('mail.host', 'smtp.zoho.com'),
            $this->config->int('mail.port', 465),
            $this->config->string('mail.encryption', 'ssl'),
            $this->config->string('mail.username'),
            $password,
            $this->config->string('mail.from_email', 'smartcircle@zohomail.com'),
            $this->config->string('mail.from_name', 'SMART Circle'),
        );

        return $this->mailService ??= new MailService(new MailRepository($db), $transport, $this->config->string('app.url'), $this->config->int('mail.batch_size', 10));
    }

    /** @var array{seats: SeatCheckService, templates: TemplateService, jobs: JobRunner}|null */
    private ?array $engagement = null;

    /** @return array{seats: SeatCheckService, templates: TemplateService, jobs: JobRunner} */
    private function engagement(Database $db): array
    {
        if ($this->engagement === null) {
            $repo = new EngagementRepository($db);
            $templates = new TemplateService(new TemplateRepository($db));
            $seats = new SeatCheckService($repo, $this->notifications($db), $templates, new AuditLogRepository($db));
            $access = new AccessService(new AccessRepository($db), new TopicRepository($db), new CoverageRepository($db), $this->notifications($db), new RevisionMode(new CalendarRepository($db)));
            $digest = new DigestService($repo, new StudentRepository($db), $access, $this->notifications($db), $seats);
            $this->engagement = ['seats' => $seats, 'templates' => $templates, 'jobs' => new JobRunner(new MailRepository($db), $seats, $digest, $this->mail($db))];
        }

        return $this->engagement;
    }

    /** Work that runs after the page has been sent (no cron on InfinityFree): seat checks, the digest, queued emails. */
    private ?\Closure $afterResponse = null;

    public function afterResponse(): void
    {
        if ($this->afterResponse === null) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            @ob_flush();
            flush();
        }
        try {
            ($this->afterResponse)();
        } catch (Throwable $e) {
            $this->logger->exception($e);
        }
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

    /**
     * While database updates are pending, pages may need tables that do not exist yet. Only signing
     * in and out and the update itself run normally; the admin sees the update button, everyone
     * else a short "being updated" notice (503, so search engines come back later).
     * @param list<string> $pending
     */
    private function updateGate(Request $request, array $pending): ?Response
    {
        $allowed = in_array($request->path, ['/login', '/logout'], true)
            || ($request->path === '/dashboard/database-update' && $request->isPost());
        if ($allowed) {
            return null;
        }
        if ($this->context->isAdmin()) {
            return Response::html($this->view->render('errors/update_needed', [
                'title' => 'Update the database',
                'pending' => $pending,
            ], 'layouts/minimal'), 503);
        }

        return Response::html($this->view->render('errors/updating', [
            'title' => 'Being updated',
            'guest' => $this->context->isGuest(),
        ], 'layouts/minimal'), 503)->withHeader('Retry-After', '600');
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
