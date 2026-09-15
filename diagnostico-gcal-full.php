<?php
$_SERVER['HTTP_HOST'] = 'diserwp.test';
$_SERVER['REQUEST_URI'] = '/';
require_once 'C:/laragon/www/diserwp/wp-load.php';
require_once 'C:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/inventory/class-inventory-google-calendar.php';

// Limpiar token cacheado para forzar nuevo
delete_transient('aura_gcal_token');

echo "=== DIAGNÓSTICO GOOGLE CALENDAR ===\n\n";

// 1. Obtener credenciales
$json = get_option('aura_gcal_service_account_json', '');
$creds = json_decode($json, true);
echo "1) Credenciales: client_email=" . $creds['client_email'] . "\n";

// 2. Generar JWT manualmente
$now  = time();
$header  = base64_encode(json_encode(['alg'=>'RS256','typ'=>'JWT']));
$header  = rtrim(strtr($header, '+/', '-_'), '=');
$claim   = base64_encode(json_encode([
    'iss'   => $creds['client_email'],
    'scope' => 'https://www.googleapis.com/auth/calendar',
    'aud'   => 'https://oauth2.googleapis.com/token',
    'iat'   => $now,
    'exp'   => $now + 3600,
]));
$claim = rtrim(strtr($claim, '+/', '-_'), '=');
$to_sign = $header . '.' . $claim;
$sig = '';
$ok = openssl_sign($to_sign, $sig, $creds['private_key'], 'SHA256');
echo "2) openssl_sign: " . ($ok ? "OK" : "FALLO") . "\n";

// 3. Solicitar token OAuth2
$jwt = $to_sign . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
$resp = wp_remote_post('https://oauth2.googleapis.com/token', [
    'timeout' => 20,
    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
    'body'    => http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt,
    ]),
]);
$http_code = wp_remote_retrieve_response_code($resp);
$body = json_decode(wp_remote_retrieve_body($resp), true);
echo "3) Token OAuth2: HTTP $http_code\n";
if (!empty($body['access_token'])) {
    $token = $body['access_token'];
    echo "   access_token obtenido OK (primeros 20 chars): " . substr($token, 0, 20) . "...\n";
} else {
    echo "   ERROR: " . wp_json_encode($body) . "\n";
    die("No se puede continuar sin token.\n");
}

// 4. Listar calendarios
$cal_resp = wp_remote_get('https://www.googleapis.com/calendar/v3/users/me/calendarList', [
    'timeout' => 20,
    'headers' => ['Authorization' => 'Bearer ' . $token],
]);
$cal_code = wp_remote_retrieve_response_code($cal_resp);
$cal_body = json_decode(wp_remote_retrieve_body($cal_resp), true);
echo "4) Listar calendarios: HTTP $cal_code\n";
if ($cal_code == 200) {
    echo "   Calendarios encontrados: " . count($cal_body['items'] ?? []) . "\n";
    foreach (($cal_body['items'] ?? []) as $c) {
        echo "   - " . $c['summary'] . " [" . $c['id'] . "]\n";
    }
} else {
    echo "   ERROR: " . wp_json_encode($cal_body) . "\n";
}

// 5. Crear evento de prueba en el calendario guardado
$cal_id = get_option('aura_gcal_calendar_id_resolved', '');
echo "\n5) Intentar crear evento de prueba en calendar_id: $cal_id\n";
$event = [
    'summary' => 'PRUEBA AURA - Borrar',
    'start'   => ['date' => '2026-06-14'],
    'end'     => ['date' => '2026-06-14'],
];
$ev_resp = wp_remote_post(
    'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($cal_id) . '/events',
    [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode($event),
    ]
);
$ev_code = wp_remote_retrieve_response_code($ev_resp);
$ev_body = json_decode(wp_remote_retrieve_body($ev_resp), true);
echo "   HTTP: $ev_code\n";
if (!empty($ev_body['id'])) {
    echo "   ÉXITO: Evento creado con ID: " . $ev_body['id'] . "\n";
    // Borrar el evento de prueba
    wp_remote_request(
        'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($cal_id) . '/events/' . rawurlencode($ev_body['id']),
        ['method'=>'DELETE','timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$token]]
    );
    echo "   Evento de prueba eliminado.\n";
} else {
    echo "   ERROR: " . wp_json_encode($ev_body) . "\n";
}

echo "\n=== FIN DIAGNÓSTICO ===\n";
