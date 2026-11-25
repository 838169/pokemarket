<?php
declare(strict_types=1);

function integrationCookie(string $current, string $header): string
{
    if (!preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $header, $match)) return $current;
    $cookies = [];
    foreach (explode('; ', $current) as $cookie) {
        if ($cookie !== '') {
            [$name, $value] = explode('=', $cookie, 2);
            $cookies[$name] = $value;
        }
    }
    if ($match[2] === '' || $match[2] === 'deleted') unset($cookies[$match[1]]);
    else $cookies[$match[1]] = $match[2];
    return implode('; ', array_map(fn($name, $value) => "$name=$value", array_keys($cookies), $cookies));
}
