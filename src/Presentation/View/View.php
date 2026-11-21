<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\View;

use RuntimeException;

/**
 * Renders PHP templates from View/templates inside a layout.
 * Templates must print every value through e().
 */
final class View
{
    /** @var array<string, mixed> values every template receives */
    private array $shared = [];

    public function __construct(private readonly string $directory)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = $this->renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderFile('partials/' . $template, $data);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->directory . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Template not found: $template");
        }

        // Render in a separate scope so template variables (e.g. one called $data or $file)
        // can never collide with this method's own variables.
        $render = static function (View $view, string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } finally {
                $__output = (string) ob_get_clean();
            }

            return $__output;
        };

        return $render($this, $file, $this->shared + $data);
    }
}
