<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\View\View;

/**
 * Recording studio. For now the "board lab": record strokes + voice on this
 * device, save locally, export/import lesson files, and replay them.
 */
final class StudioController
{
    public function __construct(private readonly View $view)
    {
    }

    public function lab(Request $request): Response
    {
        return Response::html($this->view->render('studio/lab', ['title' => 'Board lab'], 'layouts/studio'));
    }
}
