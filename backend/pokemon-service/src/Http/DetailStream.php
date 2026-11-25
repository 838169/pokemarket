<?php
declare(strict_types=1);

namespace PokemonService\Http;

use PokemonService\Controllers\PokemonController;
use PokemonService\Exceptions\HttpException;
use Throwable;

final class DetailStream
{
    public static function send(PokemonController $controller, string $id): never
    {
        ini_set('zlib.output_compression', '0');
        header('Content-Type: application/x-ndjson; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        $emit = static function (array $event): void {
            echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            flush();
        };
        try {
            $payload = $controller->detail($id, false, static function (int $percent) use (
                $emit,
            ): void {
                $emit(['event' => 'progress', 'percent' => $percent]);
            });
            $emit(['event' => 'result', 'data' => $payload]);
        } catch (HttpException $error) {
            $emit([
                'event' => 'error',
                'message' => $error->getMessage(),
                'status' => $error->statusCode(),
            ]);
        } catch (Throwable $error) {
            error_log($error->getMessage());
            $emit([
                'event' => 'error',
                'message' => 'No fue posible cargar los detalles. Intenta nuevamente.',
                'status' => 500,
            ]);
        }
        exit();
    }
}
