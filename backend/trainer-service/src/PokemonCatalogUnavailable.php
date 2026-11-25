<?php
declare(strict_types=1);
namespace TrainerService;

final class PokemonCatalogUnavailable extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('El servicio de Pokémon no está disponible. Vuelve a intentarlo cuando esté activo.');
    }
}
