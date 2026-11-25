<?php

declare(strict_types=1);

namespace TrainerService;

use PDO;
use PDOException;

final class ControladorColeccion
{
    public function __construct(
        private readonly PDO $conexion,
        private readonly SessionManager $sesion,
    ) {}

    public function list(): never
    {
        $usuarioId = $this->usuarioId();

        try {
            $consulta = $this->conexion->prepare(
                'SELECT pokemon_id, fecha_agregado FROM coleccion_pokemon WHERE usuario_id = :usuario_id ORDER BY fecha_agregado DESC',
            );
            $consulta->execute([':usuario_id' => $usuarioId]);
            $coleccion = $consulta->fetchAll(PDO::FETCH_ASSOC);
            JsonResponse::send([
                'success' => true,
                'total' => count($coleccion),
                'coleccion' => $coleccion,
            ]);
        } catch (PDOException) {
            JsonResponse::error('No fue posible consultar la colección', 500);
        }
    }

    public function add(): never
    {
        $uid = $this->usuarioId();
        $body = JsonResponse::body();
        try {
            $game = new GameEconomy($this->conexion);
            $result = $game->purchase($uid, $this->pokemonId($body['pokemon_id'] ?? null));
            JsonResponse::send(['success' => true] + $result + ['cartera' => $game->wallet($uid)]);
        } catch (\DomainException $e) {
            JsonResponse::error($e->getMessage(), 409);
        } catch (\Throwable) {
            JsonResponse::error('No fue posible completar la compra.', 500);
        }
    }

    public function delete(string $pokemonId): never
    {
        $this->usuarioId();
        JsonResponse::error('Tus Pokémon viven en el Hogar. No se pueden eliminar ni vender.', 409);
    }

    private function usuarioId(): int
    {
        $usuario = $this->sesion->user();

        if ($usuario === null) {
            JsonResponse::error('Usuario no autenticado', 401);
        }

        return $usuario['id'];
    }

    private function pokemonId(mixed $value): int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/', (string) $value)) {
            JsonResponse::error('pokemon_id debe ser un entero positivo', 400);
        }

        return (int) $value;
    }
}
