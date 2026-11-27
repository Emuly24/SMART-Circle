<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Settings\SiteAssetService;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;

/** The tutor's signature and About photo: uploaded by the admin, shown on letters and the About page. */
final class SiteAssetController
{
    private const LABELS = ['signature' => 'Signature', 'photo' => 'Photo'];

    public function __construct(
        private readonly SiteAssetService $assets,
        private readonly Session $session,
    ) {
    }

    public function upload(Request $request): Response
    {
        $kind = $this->kind($request);
        $error = $this->assets->upload($kind, $request->file('picture'));
        $this->session->flash(...($error === '' ? ['success', self::LABELS[$kind] . ' saved.'] : ['error', $error]));

        return Response::redirect('/studio/settings#' . $kind);
    }

    public function remove(Request $request): Response
    {
        $kind = $this->kind($request);
        $this->assets->remove($kind);
        $this->session->flash('info', self::LABELS[$kind] . ' removed.');

        return Response::redirect('/studio/settings#' . $kind);
    }

    public function show(Request $request): Response
    {
        $f = $this->assets->file($this->kind($request)) ?? throw new HttpException(404);

        return Response::files([$f['path']], $f['mime'], (int) filesize($f['path']))->withHeader('Cache-Control', 'public, max-age=86400');
    }

    private function kind(Request $request): string
    {
        $kind = $request->param('kind');

        return isset(self::LABELS[$kind]) ? $kind : throw new HttpException(404);
    }
}
