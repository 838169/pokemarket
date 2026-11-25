<?php

declare(strict_types=1);

namespace TrainerService;

final class JsonResponse
{
    private static array $allowedOrigins = [];
    private static string $requestId = '';

    public static function configure(array $allowedOrigins): void
    {
        self::$allowedOrigins = $allowedOrigins;
        self::$requestId = bin2hex(random_bytes(8));
        header('X-Request-ID: ' . self::$requestId);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    }

    public static function send(array $data, int $status = 200): never
    {
        self::cors();
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(
            $data + ['request_id' => self::$requestId],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        exit();
    }

    public static function error(string $message, int $status, array $extra = []): never
    {
        self::send(
            array_merge(
                [
                    'success' => false,
                    'message' => $message,
                ],
                $extra,
            ),
            $status,
        );
    }

    public static function log(string $event, \Throwable $error): void
    {
        error_log(
            json_encode([
                'service' => 'trainer',
                'event' => $event,
                'type' => $error::class,
                'request_id' => self::$requestId,
            ]),
        );
    }

    public static function body(): array
    {
        if (
            strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json'
        ) {
            self::error('Usa Content-Type: application/json.', 415);
        }
        $content = file_get_contents('php://input', false, null, 0, 65537);
        if ($content !== false && strlen($content) > 65536) {
            self::error('El cuerpo JSON es demasiado grande.', 413);
        }

        if ($content === false || trim($content) === '') {
            self::error('El cuerpo JSON es obligatorio', 400);
        }

        $data = json_decode($content, true, 32);

        if (
            !is_array($data) ||
            ($data !== [] && array_is_list($data)) ||
            json_last_error() !== JSON_ERROR_NONE
        ) {
            self::error('El cuerpo debe ser JSON válido', 400);
        }

        return $data;
    }

    public static function options(): never
    {
        self::cors();
        http_response_code(204);
        exit();
    }

    private static function cors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin !== '' && in_array($origin, self::$allowedOrigins, true)) {
            header_remove('Access-Control-Allow-Origin');
            header_remove('Access-Control-Allow-Credentials');
            header("Access-Control-Allow-Origin: {$origin}", true);
            header('Access-Control-Allow-Credentials: true', true);
            header('Vary: Origin', true);
        }

        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    }
}
