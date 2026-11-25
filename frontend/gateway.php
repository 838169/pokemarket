<?php
declare(strict_types=1);

use PokeMarket\JwtSession;

require_once dirname(__DIR__) . '/config/JwtSession.php';

function gatewayError(int $status, string $message): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
if (!preg_match('#(?:^|/)api/(pokemon|entrenadores)(/[A-Za-z0-9_/-]+)$#D', $path, $route)) {
    gatewayError(404, 'Ruta de API no encontrada.');
}
[, $service, $endpoint] = $route;
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$trainer = $service === 'entrenadores';
if (!in_array($method, $trainer ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET'], true)) {
    gatewayError(405, 'Método no permitido.');
}
if (!$trainer && !preg_match('#\A/(?:health|pokemon(?:/[a-z0-9-]+)?|sprites/[1-9][0-9]{0,4}/(?:home|artwork|animated|back))\z#', $endpoint)) {
    gatewayError(404, 'Ruta de Pokémon no encontrada.');
}

$env = require dirname(__DIR__) . '/config/environment.php';
$google = $trainer && $method === 'GET' && in_array($endpoint, ['/auth/google', '/auth/google-callback'], true);
if ($google) {
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $callback = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . preg_replace('#/auth/google(?:-callback)?$#', '/auth/google-callback', $path);
    if (empty($env['GOOGLE_OAUTH_CLIENT_ID']) || empty($env['GOOGLE_OAUTH_CLIENT_SECRET']) ||
        ($env['GOOGLE_OAUTH_REDIRECT_URI'] ?? '') !== $callback) {
        gatewayError(404, 'El acceso con Google no está disponible en esta dirección.');
    }
}
$headers = ['Accept: application/json'];
$sessionName = 'POKEMARKET_SESSION';
if ($trainer) {
    $sessionId = $_COOKIE[$sessionName] ?? '';
    $token = $_COOKIE[JwtSession::COOKIE] ?? '';
    $validSessionId = is_string($sessionId) && preg_match('/\A[a-zA-Z0-9,-]{1,128}\z/', $sessionId);
    $public = $google || ($method === 'GET' && $endpoint === '/health') ||
        ($method === 'POST' && in_array($endpoint, [
            '/auth/login', '/auth/login-code', '/auth/google-signup', '/auth/register', '/auth/register-code',
            '/auth/register-resend', '/auth/reset-request', '/auth/reset-verify', '/auth/reset-password',
        ], true));
    try {
        $authenticated = $validSessionId && JwtSession::subject($token, $sessionId) !== null;
    } catch (RuntimeException) {
        gatewayError(503, 'Falta configurar la clave JWT del proyecto.');
    }
    if (!$public && !$authenticated) gatewayError(401, 'Inicia sesión para continuar.');
    if ($validSessionId) {
        $headers[] = 'Cookie: ' . $sessionName . '=' . $sessionId .
            ($authenticated ? '; ' . JwtSession::COOKIE . '=' . $token : '');
    }
    if ($method !== 'GET') {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        if ($origin !== '' && $origin !== $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '')) {
            gatewayError(403, 'Origen no permitido.');
        }
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) === 'application/json') {
            $headers[] = 'Content-Type: application/json';
        }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (preg_match('/\A[a-f0-9]{64}\z/', $csrf)) $headers[] = 'X-CSRF-Token: ' . $csrf;
    }
}

$body = file_get_contents('php://input', false, null, 0, 65537);
if (strlen($body ?: '') > 65536) gatewayError(413, 'El cuerpo de la petición es demasiado grande.');
$base = $trainer
    ? (($env['TRAINER_SERVICE_URL'] ?? '') ?: 'http://localhost:8002/trainer-api/v1')
    : (($env['POKEMON_SERVICE_URL'] ?? '') ?: 'http://localhost:8001/pokemon-api/v1');
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = rtrim($base, '/') . $endpoint . ($query === '' ? '' : '?' . $query);
$upstream = curl_init($target);
$upstreamStatus = 0;
$upstreamHeaders = [];
$committed = false;
$streaming = !$trainer && ($_GET['progress'] ?? '') === '1';
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

$sendHeaders = static function () use (
    &$committed, &$upstreamStatus, &$upstreamHeaders, $trainer, $google, $endpoint, $streaming, $https
): void {
    if ($committed) return;
    if ($upstreamStatus < 200 || $upstreamStatus > 599) {
        gatewayError(502, 'Respuesta inválida del microservicio.');
    }
    $committed = true;
    http_response_code($upstreamStatus);
    header('Cache-Control: no-store');
    if ($google) header('Referrer-Policy: no-referrer');
    foreach ($upstreamHeaders as $line) {
        if ($trainer && preg_match('/\ASet-Cookie:\s*(?:POKEMARKET_SESSION|POKEMARKET_AUTH)=/i', $line)) {
            if ($https && !preg_match('/;\s*Secure(?:;|$)/i', $line)) $line .= '; Secure';
            header($line, false);
        } elseif (preg_match('/\A(?:Content-Type|X-Request-ID):/i', $line) ||
            ($google && $upstreamStatus === 302 && preg_match('#\ALocation:\s*(?:https://accounts\.google\.com/o/oauth2/v2/auth\?[^\r\n]+|\.\./\.\./\.\./(?:catalogo\.php|login\.php\?google=(?:error|cuenta|correo|autenticador|registro)))\z#', $line)) ||
            (!$trainer && str_starts_with($endpoint, '/sprites/') &&
                preg_match('#\ALocation:\s*https://raw\.githubusercontent\.com/PokeAPI/sprites/#i', $line))) {
            header($line, false);
        }
    }
    if ($streaming) {
        header('X-Accel-Buffering: no');
        while (ob_get_level()) ob_end_flush();
    }
};

$options = [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$upstreamStatus, &$upstreamHeaders): int {
        if (preg_match('#\AHTTP/\S+ ([1-5][0-9]{2})\b#', $line, $matches)) {
            $upstreamStatus = (int) $matches[1];
            $upstreamHeaders = [];
        } elseif (trim($line) !== '') {
            $upstreamHeaders[] = rtrim($line, "\r\n");
        }
        return strlen($line);
    },
    CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($sendHeaders, $streaming): int {
        $sendHeaders();
        echo $chunk;
        if ($streaming) flush();
        return strlen($chunk);
    },
];
if ($body !== false && $body !== '' && $method !== 'GET') {
    $options[CURLOPT_POSTFIELDS] = $body;
}
curl_setopt_array($upstream, $options);
$received = curl_exec($upstream);
curl_close($upstream);
if (!$committed && $received === false) {
    gatewayError(503, $trainer ? 'El servicio de Entrenadores no está disponible.' : 'El servicio de Pokémon no está disponible.');
}
$sendHeaders();
