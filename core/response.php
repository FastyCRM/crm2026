<?php
/**
 * FILE: /core/response.php
 * ROLE: единые ответы (JSON/redirect)
 */

declare(strict_types=1);

if (!function_exists('json_response')) {
    function json_response(array $data, int $code = 200): void
    {
        http_response_code($code);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('json_out')) {
    function json_out(array $data, int $code = 200): void
    {
        json_response($data, $code);
    }
}

if (!function_exists('redirect')) {
    /**
     * Безопасный redirect:
     * - если headers еще не отправлены -> обычный Location
     * - если уже отправлены -> JS redirect (fallback)
     */
    function redirect(string $url, int $code = 302): void
    {
        $url = str_replace(["\r", "\n"], '', $url);

        if (!headers_sent()) {
            header('Location: ' . $url, true, $code);
            exit;
        }

        // fallback, если вывод уже пошёл
        echo '<script>location.href=' . json_encode($url, JSON_UNESCAPED_SLASHES) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
        exit;
    }
}
