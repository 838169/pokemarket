<?php
declare(strict_types=1);
namespace TrainerService;

final class Input
{
    public static function text(mixed $value, string $field, int $max, bool $trim = true): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException($field . ' debe ser texto.');
        }
        $value = $trim ? trim($value) : $value;
        if (strlen($value) > $max) {
            throw new \InvalidArgumentException($field . ' supera la longitud permitida.');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new \InvalidArgumentException($field . ' contiene caracteres no permitidos.');
        }
        return $value;
    }
}
