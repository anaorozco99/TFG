<?php
// ─────────────────────────────────────────────────────────────────────
// n8n.php — Configuración y helper para notificaciones via N8N webhooks
// Cambia las URLs por las de tus webhooks una vez creados en N8N
// ─────────────────────────────────────────────────────────────────────

define('N8N_WEBHOOK_PEDIDO_CLIENTE', 'https://tfg-anaorozco.app.n8n.cloud/webhook/pedido-cliente');
define('N8N_WEBHOOK_PEDIDO_STOCK',   'https://tfg-anaorozco.app.n8n.cloud/webhook/pedido-stock');
define('N8N_WEBHOOK_STOCK_BAJO',     'https://tfg-anaorozco.app.n8n.cloud/webhook/stock-bajo');

/**
 * Envía una notificación a un webhook de N8N via POST JSON.
 * La llamada es no bloqueante (timeout 2s) para no ralentizar la app.
 *
 * @param string $url   URL del webhook de N8N
 * @param array  $datos Datos a enviar como JSON
 */
function n8n_notify(string $url, array $datos): void {
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 2,       // máx 2 segundos, no bloqueamos la app
        CURLOPT_SSL_VERIFYPEER => false,   // para entornos de desarrollo/local
    ]);
    curl_exec($ch);
    curl_close($ch);
}
