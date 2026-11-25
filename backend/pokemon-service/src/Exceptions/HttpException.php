<?php

declare(strict_types=1);

namespace PokemonService\Exceptions;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function context(): array
    {
        return $this->context;
    }
}
