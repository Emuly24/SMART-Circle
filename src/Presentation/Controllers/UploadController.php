<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Lessons\UploadException;
use SmartCircle\Application\Lessons\UploadService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;

/**
 * JSON API used by the studio to upload lesson files in 1 MB pieces.
 * All routes: tutor only, CSRF token in the X-CSRF-Token header.
 */
final class UploadController
{
    public function __construct(
        private readonly UploadService $uploads,
        private readonly Context $context,
    ) {
    }

    public function start(Request $request): Response
    {
        $data = $request->json();

        return $this->run(fn (): array => $this->uploads->start(
            (int) $this->context->userId(),
            (string) ($data['client_key'] ?? ''),
            (string) ($data['title'] ?? ''),
            (int) ($data['size'] ?? 0),
            (string) ($data['sha256'] ?? ''),
        ));
    }

    public function chunk(Request $request): Response
    {
        return $this->run(fn (): array => $this->uploads->chunk(
            (int) $this->context->userId(),
            $request->param('id'),
            (int) $request->query('offset', '-1'),
            $request->body(),
        ));
    }

    public function finish(Request $request): Response
    {
        return $this->run(fn (): array => $this->uploads->finish((int) $this->context->userId(), $request->param('id')));
    }

    /** @param callable(): array<string, mixed> $work */
    private function run(callable $work): Response
    {
        try {
            return Response::json(['ok' => true] + $work());
        } catch (UploadException $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()] + $e->data, $e->status);
        }
    }
}
