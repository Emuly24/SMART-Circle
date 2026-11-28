<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Support\ReferenceTables;

/** Standard reference tables as JSON, for the board's Tables panel. */
final class ReferenceController
{
    public function index(Request $request): Response
    {
        return Response::json(['tables' => ReferenceTables::menu()]);
    }

    public function show(Request $request): Response
    {
        $t = ReferenceTables::get($request->param('key')) ?? throw new HttpException(404);

        return Response::json($t);
    }
}
