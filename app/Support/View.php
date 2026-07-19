<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $viewPath = base_path('app/Views/' . $view . '.php');
        if (!is_file($viewPath)) {
            throw new RuntimeException('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewPath;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        $layoutPath = base_path('app/Views/' . $layout . '.php');
        if (!is_file($layoutPath)) {
            throw new RuntimeException('Layout not found: ' . $layout);
        }

        ob_start();
        require $layoutPath;
        return (string) ob_get_clean();
    }
}
