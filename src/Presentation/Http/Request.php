<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Http;

final class Request
{
    /** @var array<string, string> route parameters, filled by the router */
    private array $params = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $server = [],
        private ?string $rawBody = null,
    ) {
    }

    /** Raw request body (uploads send file pieces this way). */
    public function body(): string
    {
        return $this->rawBody ??= (string) file_get_contents('php://input');
    }

    /** @return array<string, mixed> the body decoded as JSON (empty array if not JSON) */
    public function json(): array
    {
        $data = json_decode($this->body(), true);

        return is_array($data) ? $data : [];
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        // Requests rewritten from the root .htaccess may arrive as /public/...
        if ($path === '/public' || str_starts_with($path, '/public/')) {
            $path = substr($path, strlen('/public')) ?: '/';
        }
        $path = '/' . trim($path, '/');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            $_GET,
            $_POST,
            $_SERVER,
        );
    }

    public function query(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return (string) ($this->server[$key] ?? '');
    }

    public function isHttps(): bool
    {
        $https = (string) ($this->server['HTTPS'] ?? '');

        return ($https !== '' && strtolower($https) !== 'off')
            || strtolower($this->header('X-Forwarded-Proto')) === 'https';
    }

    /** @param array<string, string> $params */
    public function withParams(array $params): self
    {
        $clone = clone $this;
        $clone->params = $params;

        return $clone;
    }

    public function param(string $key): string
    {
        return $this->params[$key] ?? '';
    }
}
