<?php
// Envía a info@bytechservicios.es las solicitudes del formulario de presupuesto.
declare(strict_types=1);
date_default_timezone_set('Europe/Madrid');

const DESTINO   = 'info@bytechservicios.es';
const REMITENTE = 'info@bytechservicios.es';

$quiereJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function responder(bool $ok, string $mensaje, bool $json): void
{
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $m = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>BY-TECH Servicios</title></head><body style="margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;'
           . 'background:#07122a;color:#eef5ff;font-family:system-ui,sans-serif"><div style="max-width:440px;text-align:center">'
           . '<p style="font-size:18px;line-height:1.5">' . $m . '</p><p><a style="color:#3cc8ff" href="/#contacto">Volver a la web</a></p></div></body></html>';
    }
    exit;
}

function campo(string $clave, int $max, bool $unaLinea = true): string
{
    $valor = trim((string)($_POST[$clave] ?? ''));
    $valor = str_replace(["\0", "\r"], '', $valor);
    if ($unaLinea) {
        $valor = str_replace("\n", ' ', $valor);
    }
    return function_exists('mb_substr') ? mb_substr($valor, 0, $max) : substr($valor, 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    responder(false, 'Esta dirección solo recibe el formulario de presupuesto.', $quiereJson);
}

// Trampas contra el spam: un campo oculto que las personas no rellenan
// y un tiempo mínimo desde que se abre la página hasta que se envía.
if (campo('web', 200) !== '') {
    responder(true, '¡Mensaje enviado! Te respondo en menos de 24 horas.', $quiereJson);
}
if ((int)($_POST['t'] ?? 0) < 3) {
    responder(false, 'No se ha podido enviar. Espera unos segundos y vuelve a intentarlo.', $quiereJson);
}

$nombre    = campo('nombre', 80);
$contacto  = campo('contacto', 120);
$localidad = campo('zona', 80);
$servicio  = campo('servicio', 80);
$mensaje   = campo('mensaje', 3000, false);

if ($nombre === '' || $contacto === '' || $mensaje === '') {
    responder(false, 'Rellena tu nombre, un teléfono o email y qué necesitas.', $quiereJson);
}
if (($_POST['privacidad'] ?? '') !== '1') {
    responder(false, 'Para enviar la solicitud tienes que aceptar la política de privacidad.', $quiereJson);
}

$asunto = 'Presupuesto web: ' . ($servicio !== '' ? $servicio : 'consulta') . ' - ' . $nombre;
$cuerpo = "Nueva solicitud desde bytechservicios.es\n\n"
        . "Nombre: {$nombre}\n"
        . "Contacto: {$contacto}\n"
        . 'Localidad: ' . ($localidad !== '' ? $localidad : '-') . "\n"
        . 'Servicio: ' . ($servicio !== '' ? $servicio : '-') . "\n\n"
        . "Mensaje:\n{$mensaje}\n\n"
        . "---\nEnviado el " . date('d/m/Y \a \l\a\s H:i') . ". El cliente aceptó la política de privacidad.\n";

$cabeceras = [
    'From: BY-TECH Servicios <' . REMITENTE . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];
if (filter_var($contacto, FILTER_VALIDATE_EMAIL)) {
    $cabeceras[] = 'Reply-To: ' . $contacto;
}

$enviado = mail(
    DESTINO,
    '=?UTF-8?B?' . base64_encode($asunto) . '?=',
    $cuerpo,
    implode("\r\n", $cabeceras),
    '-f' . REMITENTE
);

responder(
    $enviado,
    $enviado
        ? '¡Mensaje enviado! Te respondo en menos de 24 horas.'
        : 'No se ha podido enviar ahora mismo. Escríbeme por WhatsApp o a info@bytechservicios.es.',
    $quiereJson
);
