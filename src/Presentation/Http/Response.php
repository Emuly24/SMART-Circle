<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Http;

final class Response
{
    /** @var (\Closure(): void)|null writes the body itself (large files) */
    private ?\Closure $stream = null;

    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        private array $headers = [],
    ) {
    }

    /**
     * Stream files one after another without loading them into memory.
     * @param list<string> $paths
     */
    public static function files(array $paths, string $contentType, int $totalBytes, string $downloadName = ''): self
    {
        $response = new self('', 200, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) $totalBytes,
            'Cache-Control' => 'private, max-age=86400',
        ]);
        if ($downloadName !== '') {
            $response->headers['Content-Disposition'] = 'attachment; filename="' . str_replace('"', '', $downloadName) . '"';
        }
        $response->stream = static function () use ($paths): void {
            foreach ($paths as $path) {
                readfile($path);
            }
        };

        return $response;
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param array<mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        if ($this->stream !== null) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            ($this->stream)();
            return;
        }
        echo $this->body;
    }
}
