<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
use TrainerService\AdventureRules;
function verify(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$last = 0;
foreach ([100, 499, 500, 999, 1000, 1999, 2000, 5000] as $stake) {
    $profile = AdventureRules::difficulty($stake);
    verify($profile['factor'] >= $last, 'La dificultad aumenta con la apuesta');
    $last = $profile['factor'];
    verify(AdventureRules::payout($stake, 'victoria') === 2 * $stake, 'Devolución y premio');
    foreach (['derrota', 'abandono'] as $result) {
        verify(AdventureRules::payout($stake, $result) === 0, 'No devolver entrada al perder');
    }
}
foreach (AdventureRules::SCENES as $scene) {
    verify($scene['precio'] > 0, 'Precio positivo');
    verify(
        is_file(
            dirname(__DIR__) . '/frontend/' . AdventureRules::artwork($scene['imagen'], $scene['fallback']),
        ),
        'Fondo de respaldo disponible',
    );
    verify(
        is_file(dirname(__DIR__) . '/frontend/assets/audio/' . $scene['musica']),
        'Pista local disponible',
    );
}
echo "OK: dificultad, liquidación, escenarios y música local.\n";
