<?php
declare(strict_types=1);
namespace TrainerService;

final class AccountCodeEmail
{
    public static function render(string $purpose, string $code): array
    {
        [$subject, $message] = match ($purpose) {
            'verify' => ['Verifica tu correo', 'Usa este código para confirmar tu dirección de correo.'],
            'login' => ['Confirma tu inicio de sesión', 'Introduce este código para completar tu acceso.'],
            default => ['Recupera tu contraseña', 'Introduce este código para elegir una nueva contraseña.'],
        };
        $boundary = 'pokemarket-' . bin2hex(random_bytes(16));
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $note = 'Si no solicitaste este código, puedes ignorar el mensaje.';
        $html = <<<HTML
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>$subject</title></head>
<body style="margin:0;background:#edf0f6;color:#19243b;font-family:Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#edf0f6;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background:#fff;border:1px solid #d7dfeb;border-radius:16px;">
<tr><td bgcolor="#172640" style="background:#172640;color:#fff4dc;border-radius:15px 15px 0 0;padding:18px 20px;border-bottom:3px solid #e1ac59;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td style="font-size:22px;font-weight:bold;color:#fff4dc;">Poké<span style="color:#ffd085;">Market</span></td>
<td align="right" style="font-size:13px;color:#ffd085;">Seguridad de la cuenta</td></tr></table>
</td></tr>
<tr><td style="padding:24px 20px 26px;">
<h1 style="font-size:26px;line-height:1.2;margin:0 0 10px;color:#19243b;">$subject</h1>
<p style="font-size:16px;line-height:1.5;margin:0 0 18px;color:#43516a;">$message</p>
<div style="border:1px solid #dcc594;border-radius:12px;background:#fff5df;text-align:center;padding:14px 8px;color:#392910;">
<p style="font-size:13px;margin:0 0 4px;">Tu código</p>
<p dir="ltr" style="font:bold 34px/1.2 Consolas,monospace;letter-spacing:5px;margin:0;">$safeCode</p>
</div>
<p style="font-size:14px;margin:12px 0 0;color:#594018;">Válido durante <strong>15 minutos</strong>.</p>
</td></tr>
<tr><td style="border-top:1px solid #e2e8f1;padding:14px 20px;font-size:13px;line-height:1.45;color:#4b5971;">$note</td></tr>
</table></td></tr></table>
</body></html>
HTML;
        $plain = "PokéMarket\r\n\r\n$subject\r\n$message\r\n\r\nTu código: $code\r\nVálido durante 15 minutos.\r\n\r\n$note\r\n";
        $html = preg_replace('/\\r?\\n/', "\r\n", $html);
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($plain))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--\r\n";
        return [
            '=?UTF-8?B?' . base64_encode($subject . ' · PokéMarket') . '?=',
            $body,
            ['MIME-Version' => '1.0', 'Content-Type' => "multipart/alternative; boundary=\"$boundary\"", 'Content-Language' => 'es'],
        ];
    }
}
