<?php

declare(strict_types=1);

namespace TrainerService;

use PDO;
use PDOException;

final class ControladorEquipos
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
                'SELECT e.id, e.nombre, e.activo, e.fecha_creacion, COUNT(m.posicion) AS total_miembros
                 FROM equipos e
                 LEFT JOIN miembros_equipo m ON m.equipo_id = e.id
                 WHERE e.usuario_id = :usuario_id
                 GROUP BY e.id, e.nombre, e.activo, e.fecha_creacion
                 ORDER BY e.fecha_creacion DESC',
            );
            $consulta->execute([':usuario_id' => $usuarioId]);
            JsonResponse::send([
                'success' => true,
                'equipos' => $consulta->fetchAll(PDO::FETCH_ASSOC),
                'capacidad' => (new GameEconomy($this->conexion))->teamCapacity($usuarioId),
            ]);
        } catch (PDOException) {
            JsonResponse::error('No fue posible consultar los equipos', 500);
        }
    }

    public function create(): never
    {
        $usuarioId = $this->usuarioId();
        $datos = JsonResponse::body();
        $nombre = $this->nombre($datos['nombre'] ?? null);

        try {
            $economy = new GameEconomy($this->conexion);
            $economy->ensure($usuarioId);
            $this->conexion->beginTransaction();
            $lock = $this->conexion->prepare(
                'SELECT usuario_id FROM carteras_usuario WHERE usuario_id = ? FOR UPDATE',
            );
            $lock->execute([$usuarioId]);
            $count = $this->conexion->prepare('SELECT COUNT(*) FROM equipos WHERE usuario_id = ?');
            $count->execute([$usuarioId]);
            $capacity = $economy->teamCapacity($usuarioId);
            if ((int) $count->fetchColumn() >= $capacity['limite']) {
                $this->conexion->rollBack();
                JsonResponse::error(
                    $capacity['nivel_siguiente'] === null
                        ? 'Ya usas todos tus espacios de equipo.'
                        : 'Desbloquea otro equipo al nivel ' . $capacity['nivel_siguiente'] . '.',
                    409,
                );
            }
            $consulta = $this->conexion->prepare(
                'INSERT INTO equipos (usuario_id, nombre) VALUES (:usuario_id, :nombre)',
            );
            $consulta->execute([
                ':usuario_id' => $usuarioId,
                ':nombre' => $nombre,
            ]);
            $id = (int) $this->conexion->lastInsertId();
            $this->conexion->commit();
            JsonResponse::send(
                [
                    'success' => true,
                    'message' => 'Equipo creado correctamente',
                    'equipo' => [
                        'id' => $id,
                        'nombre' => $nombre,
                        'activo' => false,
                    ],
                ],
                201,
            );
        } catch (PDOException) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            JsonResponse::error('No fue posible crear el equipo', 500);
        }
    }

    public function detail(string $teamId): never
    {
        $usuarioId = $this->usuarioId();
        $equipo = $this->equipo($this->teamId($teamId), $usuarioId);

        try {
            $consulta = $this->conexion->prepare(
                'SELECT posicion, pokemon_id, fecha_agregado FROM miembros_equipo WHERE equipo_id = :equipo_id ORDER BY posicion ASC',
            );
            $consulta->execute([':equipo_id' => $equipo['id']]);
            $equipo['miembros'] = $consulta->fetchAll(PDO::FETCH_ASSOC);
            JsonResponse::send([
                'success' => true,
                'equipo' => $equipo,
            ]);
        } catch (PDOException) {
            JsonResponse::error('No fue posible consultar el equipo', 500);
        }
    }

    public function update(string $teamId): never
    {
        $usuarioId = $this->usuarioId();
        $id = $this->teamId($teamId);
        $this->equipo($id, $usuarioId);
        $datos = JsonResponse::body();
        $campos = [];
        $valores = [':id' => $id];

        if (array_key_exists('nombre', $datos)) {
            $campos[] = 'nombre = :nombre';
            $valores[':nombre'] = $this->nombre($datos['nombre']);
        }

        if (array_key_exists('activo', $datos)) {
            if (!is_bool($datos['activo'])) {
                JsonResponse::error('activo debe ser verdadero o falso', 400);
            }
            $campos[] = 'activo = :activo';
            $valores[':activo'] = $datos['activo'] ? 1 : 0;
        }

        if ($campos === []) {
            JsonResponse::error('Debes enviar nombre o activo para actualizar el equipo', 400);
        }

        try {
            $this->conexion->beginTransaction();

            if (array_key_exists('activo', $datos) && $datos['activo']) {
                $consulta = $this->conexion->prepare(
                    'UPDATE equipos SET activo = 0 WHERE usuario_id = :usuario_id',
                );
                $consulta->execute([':usuario_id' => $usuarioId]);
            }

            $consulta = $this->conexion->prepare(
                'UPDATE equipos SET ' . implode(', ', $campos) . ' WHERE id = :id',
            );
            $consulta->execute($valores);
            $this->conexion->commit();
            JsonResponse::send([
                'success' => true,
                'message' => 'Equipo actualizado correctamente',
            ]);
        } catch (PDOException) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }

            JsonResponse::error('No fue posible actualizar el equipo', 500);
        }
    }

    public function delete(string $teamId): never
    {
        $usuarioId = $this->usuarioId();
        $id = $this->teamId($teamId);
        $this->equipo($id, $usuarioId);

        try {
            $consulta = $this->conexion->prepare(
                'DELETE FROM equipos WHERE id = :id AND usuario_id = :usuario_id',
            );
            $consulta->execute([
                ':id' => $id,
                ':usuario_id' => $usuarioId,
            ]);
            JsonResponse::send([
                'success' => true,
                'message' => 'Equipo eliminado correctamente',
            ]);
        } catch (PDOException) {
            JsonResponse::error('No fue posible eliminar el equipo', 500);
        }
    }

    public function saveMember(string $teamId, string $slot): never
    {
        $usuarioId = $this->usuarioId();
        $id = $this->teamId($teamId);
        $posicion = $this->slot($slot);
        $this->equipo($id, $usuarioId);
        $datos = JsonResponse::body();
        $pokemonId = $this->pokemonId($datos['pokemon_id'] ?? null);

        if (!$this->inCollection($usuarioId, $pokemonId)) {
            JsonResponse::error(
                'El Pokémon debe pertenecer a tu colección antes de agregarlo al equipo',
                409,
            );
        }

        try {
            $consulta = $this->conexion->prepare(
                'SELECT posicion FROM miembros_equipo WHERE equipo_id = :equipo_id AND pokemon_id = :pokemon_id',
            );
            $consulta->execute([
                ':equipo_id' => $id,
                ':pokemon_id' => $pokemonId,
            ]);
            $miembro = $consulta->fetch(PDO::FETCH_ASSOC);

            if ($miembro && (int) $miembro['posicion'] !== $posicion) {
                JsonResponse::error('Este Pokémon ya pertenece al equipo', 409);
            }

            $consulta = $this->conexion->prepare(
                'INSERT INTO miembros_equipo (equipo_id, posicion, pokemon_id)
                 VALUES (:equipo_id, :posicion, :pokemon_id)
                 ON DUPLICATE KEY UPDATE pokemon_id = VALUES(pokemon_id), fecha_agregado = CURRENT_TIMESTAMP',
            );
            $consulta->execute([
                ':equipo_id' => $id,
                ':posicion' => $posicion,
                ':pokemon_id' => $pokemonId,
            ]);
            JsonResponse::send([
                'success' => true,
                'message' => 'Pokémon agregado al equipo',
            ]);
        } catch (PDOException) {
            JsonResponse::error('No fue posible guardar el miembro del equipo', 500);
        }
    }

    public function deleteMember(string $teamId, string $slot): never
    {
        $usuarioId = $this->usuarioId();
        $id = $this->teamId($teamId);
        $posicion = $this->slot($slot);
        $this->equipo($id, $usuarioId);

        try {
            $consulta = $this->conexion->prepare(
                'DELETE FROM miembros_equipo WHERE equipo_id = :equipo_id AND posicion = :posicion',
            );
            $consulta->execute([
                ':equipo_id' => $id,
                ':posicion' => $posicion,
            ]);

            if ($consulta->rowCount() === 0) {
                JsonResponse::error('No hay un Pokémon en esa posición', 404);
            }

            JsonResponse::send([
                'success' => true,
                'message' => 'Pokémon eliminado del equipo',
            ]);
        } catch (PDOException) {
            JsonResponse::error('No fue posible eliminar el miembro del equipo', 500);
        }
    }

    private function usuarioId(): int
    {
        $usuario = $this->sesion->user();

        if ($usuario === null) {
            JsonResponse::error('Usuario no autenticado', 401);
        }

        return $usuario['id'];
    }

    private function teamId(mixed $value): int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/', (string) $value)) {
            JsonResponse::error('teamId debe ser un entero positivo', 400);
        }

        return (int) $value;
    }

    private function slot(mixed $value): int
    {
        if (!is_scalar($value) || !preg_match('/^[1-6]$/', (string) $value)) {
            JsonResponse::error('slot debe estar entre 1 y 6', 400);
        }

        return (int) $value;
    }

    private function pokemonId(mixed $value): int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/', (string) $value)) {
            JsonResponse::error('pokemon_id debe ser un entero positivo', 400);
        }

        return (int) $value;
    }

    private function nombre(mixed $value): string
    {
        $nombre = Input::text($value, 'Nombre del equipo', 100);

        if ($nombre === '') {
            JsonResponse::error('nombre debe tener entre 1 y 100 caracteres', 400);
        }

        return $nombre;
    }

    private function equipo(int $id, int $usuarioId): array
    {
        try {
            $consulta = $this->conexion->prepare(
                'SELECT id, nombre, activo, fecha_creacion FROM equipos WHERE id = :id AND usuario_id = :usuario_id',
            );
            $consulta->execute([
                ':id' => $id,
                ':usuario_id' => $usuarioId,
            ]);
            $equipo = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$equipo) {
                JsonResponse::error('Equipo no encontrado', 404);
            }

            return $equipo;
        } catch (PDOException) {
            JsonResponse::error('No fue posible consultar el equipo', 500);
        }
    }

    private function inCollection(int $usuarioId, int $pokemonId): bool
    {
        try {
            $consulta = $this->conexion->prepare(
                'SELECT 1 FROM coleccion_pokemon WHERE usuario_id = :usuario_id AND pokemon_id = :pokemon_id',
            );
            $consulta->execute([
                ':usuario_id' => $usuarioId,
                ':pokemon_id' => $pokemonId,
            ]);

            return (bool) $consulta->fetchColumn();
        } catch (PDOException) {
            JsonResponse::error('No fue posible consultar la colección', 500);
        }
    }
}
