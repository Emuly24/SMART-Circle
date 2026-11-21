<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Http;

use SmartCircle\Presentation\Middleware\Middleware;

/**
 * Maps METHOD + path (with {name} placeholders) to a handler, through middleware.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, names: list<string>, handler: callable, middleware: list<Middleware>}> */
    private array $routes = [];

    /** @param list<Middleware> $middleware */
    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /** @param list<Middleware> $middleware */
    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /** @param list<Middleware> $middleware */
    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        // Quote the literal parts; turn each {name} into a capture group.
        $names = [];
        $regex = '';
        foreach (preg_split('/(\{[a-z_]+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            if (preg_match('/^\{([a-z_]+)\}$/', $part, $m)) {
                $names[] = $m[1];
                $regex .= '([^/]+)';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            'names' => $names,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }

            $params = array_combine($route['names'], array_map('rawurldecode', array_slice($m, 1))) ?: [];
            $request = $request->withParams($params);

            $handler = $route['handler'];
            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                static fn (callable $next, Middleware $mw): callable => static fn (Request $r): Response => $mw->handle($r, $next),
                static fn (Request $r): Response => $handler($r)
            );

            return $pipeline($request);
        }

        throw new HttpException($pathMatched ? 405 : 404);
    }
}
