<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\View\View;

/** Public pages that need no login. */
final class PageController
{
    /** @var (\Closure(): ?\SmartCircle\Application\Admissions\SeatChart)|null */
    private ?\Closure $seatChart = null;
    /** @var (\Closure(): ?array)|null */
    private ?\Closure $openIntake = null;

    /** @var (\Closure(): ?array)|null what a signed-in person's home page shows */
    private ?\Closure $memberHome = null;

    public function __construct(private readonly View $view)
    {
    }

    public function setMemberHome(\Closure $memberHome): void
    {
        $this->memberHome = $memberHome;
    }

    /** The home page shows the live seat chart and whether applications are open. */
    public function setSeatChart(\Closure $seatChart, \Closure $openIntake): void
    {
        $this->seatChart = $seatChart;
        $this->openIntake = $openIntake;
    }

    public function home(Request $request): Response
    {
        // The same home page for everyone; signed-in people get "Open my dashboard" instead of
        // the application buttons. Their own summary lives on the dashboard.
        $member = $this->memberHome !== null ? ($this->memberHome)() : null;

        return Response::html($this->view->render('pages/home', [
            'member' => $member,
            'title' => 'Free science, maths and English support for Forms 1–4',
            'subjects' => self::subjects(),
            'chart' => $this->seatChart !== null ? ($this->seatChart)() : null,
            'isOpen' => $this->openIntake !== null && ($this->openIntake)() !== null,
        ]));
    }

    public function about(Request $request): Response
    {
        return Response::html($this->view->render('pages/about', ['title' => 'About SMART Circle',
            'description' => 'Why SMART Circle exists: free, honest support for Form 1–4 students in Malawi that connects every topic to real life. The founder\'s story, our vision and goals.']));
    }

    public function safeguarding(Request $request): Response
    {
        return Response::html($this->view->render('pages/safeguarding', ['title' => 'Safeguarding and privacy']));
    }

    /** @return list<array{key: string, name: string, blurb: string}> */
    public static function subjects(): array
    {
        return [
            ['key' => 'mathematics', 'name' => 'Mathematics', 'blurb' => 'Algebra, geometry, graphs and the reasoning behind every step.'],
            ['key' => 'physics', 'name' => 'Physics', 'blurb' => 'Forces, electricity, waves and energy — and where you meet them every day.'],
            ['key' => 'chemistry', 'name' => 'Chemistry', 'blurb' => 'Reactions, equations and materials, from the kitchen to the farm.'],
            ['key' => 'biology', 'name' => 'Biology', 'blurb' => 'Living things, health and the environment around Malawi.'],
            ['key' => 'english', 'name' => 'English grammar', 'blurb' => 'Tenses, concord, punctuation and sentences that say what you mean.'],
        ];
    }
}
