<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\View\View;

/** Public pages that need no login. */
final class PageController
{
    public function __construct(private readonly View $view)
    {
    }

    public function home(Request $request): Response
    {
        return Response::html($this->view->render('pages/home', [
            'title' => 'Free science, maths and English support for Forms 1–4',
            'subjects' => self::subjects(),
        ]));
    }

    public function about(Request $request): Response
    {
        return Response::html($this->view->render('pages/about', ['title' => 'About SMART Circle']));
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
