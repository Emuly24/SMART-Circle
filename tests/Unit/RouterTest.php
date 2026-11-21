<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Router;
use SmartCircle\Presentation\Middleware\Middleware;

final class RouterTest extends TestCase
{
    public function testMatchesStaticAndParameterisedRoutes(): void
    {
        $router = new Router();
        $router->get('/', static fn (): Response => new Response('home'));
        $router->get('/topics/{id}/lessons/{slug}', static fn (Request $r): Response => new Response($r->param('id') . '|' . $r->param('slug')));

        self::assertSame('home', $router->dispatch(new Request('GET', '/'))->body);
        self::assertSame('42|ohm-law', $router->dispatch(new Request('GET', '/topics/42/lessons/ohm-law'))->body);
    }

    public function testDotsInPatternsAreLiteral(): void
    {
        $router = new Router();
        $router->get('/sw.js', static fn (): Response => new Response('worker'));

        $this->expectException(HttpException::class);
        $router->dispatch(new Request('GET', '/swXjs'));
    }

    public function testUnknownPathIs404(): void
    {
        $router = new Router();
        $router->get('/a', static fn (): Response => new Response('a'));

        try {
            $router->dispatch(new Request('GET', '/b'));
            self::fail('Expected 404');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status);
        }
    }

    public function testWrongMethodIs405(): void
    {
        $router = new Router();
        $router->get('/login', static fn (): Response => new Response('form'));

        try {
            $router->dispatch(new Request('POST', '/login'));
            self::fail('Expected 405');
        } catch (HttpException $e) {
            self::assertSame(405, $e->status);
        }
    }

    public function testMiddlewareRunsInOrderAndCanStopTheRequest(): void
    {
        $log = new \ArrayObject();
        $mw = static fn (string $name, bool $stop = false): Middleware => new class ($name, $stop, $log) implements Middleware {
            public function __construct(private string $name, private bool $stop, private \ArrayObject $log)
            {
            }

            public function handle(Request $request, callable $next): Response
            {
                $this->log[] = $this->name;

                return $this->stop ? new Response('stopped by ' . $this->name, 403) : $next($request);
            }
        };

        $router = new Router();
        $router->get('/ok', static function () use ($log): Response {
            $log[] = 'handler';
            return new Response('done');
        }, [$mw('first'), $mw('second')]);
        $router->get('/blocked', static fn (): Response => new Response('never'), [$mw('guard', true)]);

        self::assertSame('done', $router->dispatch(new Request('GET', '/ok'))->body);
        self::assertSame(['first', 'second', 'handler'], $log->getArrayCopy());
        self::assertSame(403, $router->dispatch(new Request('GET', '/blocked'))->status);
        self::assertSame(['first', 'second', 'handler', 'guard'], $log->getArrayCopy());
    }
}
