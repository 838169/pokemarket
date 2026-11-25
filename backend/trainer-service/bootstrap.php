<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'TrainerService\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/src/' . substr($class, strlen($prefix)) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

return require __DIR__ . '/config/app.php';
