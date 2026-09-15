<?php
/**
 * Template para la página pública de QR de vehículos.
 * Se renderiza cuando se accede a ?aura_veh_qr={token}
 * Diseño mobile-first, UX moderna, modo claro/oscuro.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Validate token
$token = sanitize_text_field( get_query_var( 'vqr', '' ) );

// Fetch vehicle data via internal REST call to avoid duplicate logic
$response = rest_do_request(
    new WP_REST_Request( 'GET', '/aura/v1/vehicles/qr/' . $token )
);

$vehicle    = null;
$error_msg  = '';

if ( $response->is_error() || $response->get_status() !== 200 ) {
    $error_msg = 'Código QR no válido o expirado.';
} else {
    $data = $response->get_data();
    if ( empty( $data['valid'] ) ) {
        $error_msg = $data['message'] ?? 'Código QR no válido.';
    } else {
        $vehicle = $data['vehicle'];
        // Merge active_trip into vehicle array for easy template access
        $vehicle['active_trip'] = $data['active_trip'] ?? null;
    }
}

$status_labels = array(
    'available'   => 'Disponible',
    'rented'      => 'En Uso',
    'maintenance' => 'Mantenimiento',
    'unavailable' => 'Fuera de Servicio',
);

$status_colors = array(
    'available'   => '#10b981',
    'rented'      => '#8b5cf6',
    'maintenance' => '#f59e0b',
    'unavailable' => '#ef4444',
);

$veh_status       = $vehicle ? ( $vehicle['status'] ?? 'available' ) : '';
$status_label     = $status_labels[ $veh_status ] ?? $veh_status;
$status_color     = $status_colors[ $veh_status ] ?? '#6b7280';
$has_active_trip  = ! empty( $vehicle['active_trip'] );

$site_url       = esc_url( home_url() );
$site_name      = esc_html( get_bloginfo( 'name' ) );
$nonce_val      = wp_create_nonce( 'aura_veh_qr_trip' );
// Forzar mismo esquema (http/https) que la petición actual para evitar mixed-content.
$_scheme        = is_ssl() ? 'https' : 'http';
$api_base       = esc_url( set_url_scheme( rest_url( 'aura/v1/' ), $_scheme ) );
$nonce_rest     = wp_create_nonce( 'wp_rest' );
$login_nonce    = wp_create_nonce( 'aura_qr_login' );
$login_url_wp   = esc_url( set_url_scheme( admin_url( 'admin-ajax.php' ), $_scheme ) );
$redirect_back  = esc_url( set_url_scheme( home_url( add_query_arg( 'vqr', get_query_var('vqr',''), '/' ) ), $_scheme ) );

// WP user info if logged in
$current_user      = wp_get_current_user();
$logged_in         = is_user_logged_in();
$user_display_name = $logged_in ? esc_html( $current_user->display_name ) : '';

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="theme-color" id="meta-theme-color" content="#0f172a">
<title><?php echo $vehicle ? esc_html( $vehicle['plate'] . ' — ' . $vehicle['brand'] . ' ' . $vehicle['model'] ) : 'QR Vehículo'; ?> · <?php echo $site_name; ?></title>
<style>
/* ── CSS Variables ───────────────────────────── */
:root {
    --bg: #0f172a;
    --bg2: #1e293b;
    --bg3: #334155;
    --surface: rgba(30,41,59,0.85);
    --surface2: rgba(51,65,85,0.7);
    --text: #f1f5f9;
    --text-muted: #94a3b8;
    --border: rgba(148,163,184,0.15);
    --accent: #6366f1;
    --accent2: #818cf8;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --shadow: 0 25px 50px -12px rgba(0,0,0,0.6);
    --radius: 20px;
    --radius-sm: 12px;
}
[data-theme="light"] {
    --bg: #f0f4ff;
    --bg2: #e2e8f0;
    --bg3: #cbd5e1;
    --surface: rgba(255,255,255,0.88);
    --surface2: rgba(241,245,249,0.8);
    --text: #0f172a;
    --text-muted: #475569;
    --border: rgba(100,116,139,0.2);
    --shadow: 0 25px 50px -12px rgba(0,0,0,0.12);
}

/* ── Reset ───────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;-webkit-tap-highlight-color:transparent;}
body {
    font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Inter',sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100%;
    transition: background 0.4s, color 0.4s;
    -webkit-font-smoothing: antialiased;
}

/* ── Background animated ─────────────────────── */
.bg-orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.35;
    pointer-events: none;
    z-index: 0;
    animation: float 8s ease-in-out infinite alternate;
}
.bg-orb-1 { width:320px;height:320px;background:#6366f1;top:-80px;left:-80px;animation-delay:0s; }
.bg-orb-2 { width:250px;height:250px;background:#8b5cf6;bottom:-60px;right:-60px;animation-delay:3s; }
.bg-orb-3 { width:200px;height:200px;background:#10b981;top:40%;left:50%;transform:translateX(-50%);animation-delay:6s;opacity:0.2; }
[data-theme="light"] .bg-orb { opacity: 0.12; }
@keyframes float { from{transform:translateY(0) scale(1);} to{transform:translateY(-30px) scale(1.05);} }
@keyframes fadeUp { from{opacity:0;transform:translateY(24px);} to{opacity:1;transform:translateY(0);} }
@keyframes pulse { 0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,0.4);} 50%{box-shadow:0 0 0 12px rgba(99,102,241,0);} }
@keyframes spin {to{transform:rotate(360deg);}}
@keyframes shimmer { 0%{background-position:-400px 0;} 100%{background-position:400px 0;} }

/* ── Wrapper ─────────────────────────────────── */
.qr-page {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 24px 16px 40px;
    max-width: 480px;
    margin: 0 auto;
}

/* ── Header ──────────────────────────────────── */
.qr-header {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    animation: fadeUp 0.5s ease both;
}
.qr-logo {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-muted);
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.qr-logo span { color: var(--accent2); }

/* ── Theme toggle ────────────────────────────── */
.theme-toggle {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 50px;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    backdrop-filter: blur(10px);
    transition: all 0.3s;
    -webkit-backdrop-filter: blur(10px);
}
.theme-toggle:hover { transform: scale(1.05); background: var(--surface); }
.theme-toggle .icon { font-size: 18px; transition: transform 0.4s; }
.theme-toggle:hover .icon { transform: rotate(20deg); }

/* ── Error card ──────────────────────────────── */
.error-card {
    background: var(--surface);
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: var(--radius);
    padding: 40px 28px;
    text-align: center;
    width: 100%;
    backdrop-filter: blur(20px);
    animation: fadeUp 0.6s ease both;
    box-shadow: var(--shadow);
}
.error-card .error-icon { font-size: 56px; margin-bottom: 16px; }
.error-card h2 { font-size: 22px; font-weight: 700; margin-bottom: 10px; }
.error-card p { color: var(--text-muted); font-size: 15px; line-height: 1.5; }

/* ── Vehicle card ────────────────────────────── */
.vehicle-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    width: 100%;
    padding: 24px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: var(--shadow);
    animation: fadeUp 0.6s ease 0.1s both;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.vehicle-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent), #8b5cf6, var(--success));
}

.vehicle-photo-wrap {
    display: flex;
    align-items: flex-start;
    gap: 18px;
    margin-bottom: 20px;
}
.vehicle-photo {
    width: 90px;
    height: 68px;
    border-radius: var(--radius-sm);
    object-fit: cover;
    border: 2px solid var(--border);
    flex-shrink: 0;
    background: var(--bg3);
}
.vehicle-photo-placeholder {
    width: 90px;
    height: 68px;
    border-radius: var(--radius-sm);
    background: var(--bg3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    flex-shrink: 0;
    border: 2px solid var(--border);
}

.vehicle-info { flex: 1; min-width: 0; }
.vehicle-plate {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: var(--text);
    font-family: 'Courier New', monospace;
    margin-bottom: 4px;
}
.vehicle-name {
    font-size: 15px;
    color: var(--text-muted);
    margin-bottom: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.vehicle-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 50px;
    color: #fff;
    letter-spacing: 0.03em;
    animation: pulse 2s infinite;
}
.vehicle-badge .dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: rgba(255,255,255,0.8);
}

.vehicle-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.meta-item {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 12px 14px;
}
.meta-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin-bottom: 4px;
}
.meta-value {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
}

/* ── Trip card ───────────────────────────────── */
.trip-indicator {
    background: linear-gradient(135deg, rgba(139,92,246,0.2), rgba(99,102,241,0.15));
    border: 1px solid rgba(139,92,246,0.35);
    border-radius: var(--radius-sm);
    padding: 14px 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: fadeUp 0.6s ease 0.2s both;
}
.trip-icon { font-size: 24px; flex-shrink: 0; }
.trip-text { flex: 1; }
.trip-text strong { font-size: 14px; font-weight: 700; display: block; margin-bottom: 2px; }
.trip-text span { font-size: 12px; color: var(--text-muted); }

/* ── Action section ──────────────────────────── */
.action-section {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    width: 100%;
    padding: 28px 24px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: var(--shadow);
    animation: fadeUp 0.6s ease 0.25s both;
}

.action-title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 6px;
    color: var(--text);
}
.action-subtitle {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 22px;
    line-height: 1.4;
}

/* ── Trip type selector ──────────────────────── */
.trip-type-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 18px;
}
.trip-type-card {
    position: relative;
    cursor: pointer;
}
.trip-type-card input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.trip-type-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 14px 10px;
    border-radius: var(--radius-sm);
    background: var(--bg2);
    border: 2px solid var(--border);
    text-align: center;
    transition: all 0.2s;
    cursor: pointer;
    min-height: 80px;
}
.trip-type-label:active { transform: scale(0.96); }
.trip-type-icon { font-size: 22px; line-height: 1; }
.trip-type-name {
    font-size: 13px;
    font-weight: 700;
    color: var(--text);
    line-height: 1.1;
}
.trip-type-desc {
    font-size: 10px;
    color: var(--text-muted);
    line-height: 1.2;
}
.trip-type-card input:checked + .trip-type-label {
    border-color: var(--accent);
    background: rgba(99,102,241,0.12);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.18);
}
.trip-type-card input:checked + .trip-type-label .trip-type-name { color: var(--accent2); }

/* Colores por tipo cuando está seleccionado */
.trip-type-card.type-rental   input:checked + .trip-type-label { border-color:#10b981; background:rgba(16,185,129,0.1); box-shadow:0 0 0 3px rgba(16,185,129,0.15); }
.trip-type-card.type-rental   input:checked + .trip-type-label .trip-type-name { color:#34d399; }
.trip-type-card.type-errand   input:checked + .trip-type-label { border-color:#6366f1; background:rgba(99,102,241,0.1); box-shadow:0 0 0 3px rgba(99,102,241,0.15); }
.trip-type-card.type-errand   input:checked + .trip-type-label .trip-type-name { color:#818cf8; }
.trip-type-card.type-maint    input:checked + .trip-type-label { border-color:#f59e0b; background:rgba(245,158,11,0.1); box-shadow:0 0 0 3px rgba(245,158,11,0.15); }
.trip-type-card.type-maint    input:checked + .trip-type-label .trip-type-name { color:#fbbf24; }
.trip-type-card.type-other    input:checked + .trip-type-label { border-color:#94a3b8; background:rgba(148,163,184,0.1); box-shadow:0 0 0 3px rgba(148,163,184,0.15); }
.trip-type-card.type-other    input:checked + .trip-type-label .trip-type-name { color:#cbd5e1; }

/* ── Form ────────────────────────────────────── */
.form-group { margin-bottom: 18px; }
.form-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin-bottom: 8px;
}
.form-control {
    display: block;
    width: 100%;
    padding: 14px 16px;
    border-radius: var(--radius-sm);
    background: var(--bg2);
    border: 1.5px solid var(--border);
    color: var(--text);
    font-size: 15px;
    font-family: inherit;
    transition: border-color 0.2s, box-shadow 0.2s;
    -webkit-appearance: none;
}
.form-control:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
}
.form-control::placeholder { color: var(--text-muted); }

/* ── Buttons ─────────────────────────────────── */
.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 16px 24px;
    border-radius: var(--radius-sm);
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: all 0.25s;
    font-family: inherit;
    letter-spacing: 0.02em;
    position: relative;
    overflow: hidden;
}
.btn:active { transform: scale(0.97); }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }

.btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    box-shadow: 0 4px 24px rgba(99,102,241,0.4);
}
.btn-primary:hover:not(:disabled) {
    box-shadow: 0 6px 32px rgba(99,102,241,0.6);
    transform: translateY(-1px);
}

.btn-success {
    background: linear-gradient(135deg, #059669, #10b981);
    color: #fff;
    box-shadow: 0 4px 24px rgba(16,185,129,0.35);
}
.btn-success:hover:not(:disabled) {
    box-shadow: 0 6px 32px rgba(16,185,129,0.55);
    transform: translateY(-1px);
}

.btn-warning {
    background: linear-gradient(135deg, #d97706, #f59e0b);
    color: #fff;
    box-shadow: 0 4px 24px rgba(245,158,11,0.35);
}
.btn-warning:hover:not(:disabled) {
    transform: translateY(-1px);
}

/* ── Spinner ─────────────────────────────────── */
.spinner {
    width: 18px; height: 18px;
    border: 2px solid rgba(255,255,255,0.35);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

/* ── Alert ───────────────────────────────────── */
.alert {
    border-radius: var(--radius-sm);
    padding: 14px 16px;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 18px;
    display: none;
    line-height: 1.4;
}
.alert.show { display: block; animation: fadeUp 0.3s ease; }
.alert-success { background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.4); color: #34d399; }
.alert-error   { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.4); color: #f87171; }

/* ── Divider ─────────────────────────────────── */
.divider {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text-muted);
    font-size: 12px;
    margin: 18px 0;
}
.divider::before,.divider::after { content:''; flex:1; height:1px; background:var(--border); }

/* ── Guest fields ────────────────────────────── */
.guest-block { animation: fadeUp 0.4s ease; }

/* ── Footer ──────────────────────────────────── */
.qr-footer {
    margin-top: 28px;
    text-align: center;
    color: var(--text-muted);
    font-size: 12px;
    animation: fadeUp 0.6s ease 0.4s both;
}
.qr-footer a { color: var(--accent2); text-decoration: none; }

/* ── Success overlay ─────────────────────────── */
.success-overlay {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 40px 24px;
    animation: fadeUp 0.5s ease;
}
.success-overlay.show { display: flex; }
.success-icon {
    width: 88px; height: 88px;
    background: linear-gradient(135deg, #059669, #10b981);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 42px;
    margin-bottom: 24px;
    box-shadow: 0 8px 32px rgba(16,185,129,0.4);
}
.success-overlay h2 { font-size: 24px; font-weight: 800; margin-bottom: 10px; }
.success-overlay p  { color: var(--text-muted); font-size: 15px; line-height: 1.5; margin-bottom: 8px; }
.success-overlay .trip-id {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 18px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    font-weight: 700;
    margin-top: 12px;
    color: var(--accent2);
}

/* ── Driver identity card ────────────────────── */
.driver-identity {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--surface2);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    margin-bottom: 18px;
    transition: border-color 0.2s;
}
.driver-identity.is-set {
    border-color: rgba(99,102,241,0.4);
    background: rgba(99,102,241,0.07);
}
.driver-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0;
}
.driver-identity-info {
    flex: 1;
    min-width: 0;
}
.driver-identity-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin-bottom: 2px;
}
.driver-identity-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.driver-identity-sub {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 1px;
}
.btn-change-id {
    background: none;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    padding: 5px 10px;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
    font-family: inherit;
}
.btn-change-id:hover { border-color: var(--accent); color: var(--accent2); }

/* estado "editar nombre" */
.driver-name-edit {
    animation: fadeUp 0.3s ease;
}

/* ── Login panel ─────────────────────────────────── */
.login-toggle-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}
.login-divider-line { flex: 1; height: 1px; background: var(--border); }
.login-toggle-btn {
    background: none;
    border: 1px solid var(--border);
    border-radius: 20px;
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 600;
    padding: 5px 14px;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
    font-family: inherit;
    display: flex;
    align-items: center;
    gap: 5px;
}
.login-toggle-btn:hover { border-color: var(--accent); color: var(--accent2); }

.login-panel {
    display: none;
    background: var(--bg2);
    border: 1.5px solid rgba(99,102,241,0.3);
    border-radius: var(--radius-sm);
    padding: 20px;
    margin-bottom: 18px;
    animation: fadeUp 0.3s ease;
}
.login-panel.open { display: block; }
.login-panel-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.login-panel .form-control {
    background: var(--bg);
    margin-bottom: 10px;
    padding: 12px 14px;
    font-size: 14px;
}
.btn-login-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 13px 20px;
    border-radius: var(--radius-sm);
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    font-family: inherit;
}
.btn-login-submit:disabled { opacity: 0.55; cursor: not-allowed; }
.login-error {
    font-size: 12px;
    color: var(--danger);
    margin-top: 8px;
    display: none;
    background: rgba(239,68,68,0.1);
    border-radius: 8px;
    padding: 8px 12px;
}
.login-error.show { display: block; }
</style>
</head>
<body>

<!-- Background orbs -->
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>
<div class="bg-orb bg-orb-3"></div>

<main class="qr-page">

    <!-- Header -->
    <header class="qr-header">
        <div class="qr-logo"><?php echo $site_name; ?> <span>·</span> Vehículos</div>
        <button class="theme-toggle" onclick="toggleTheme()" aria-label="Cambiar tema">
            <span class="icon" id="theme-icon">☀️</span>
            <span id="theme-label">Claro</span>
        </button>
    </header>

    <?php if ( $error_msg ) : ?>

        <!-- Error state -->
        <div class="error-card">
            <div class="error-icon">🚫</div>
            <h2>Código inválido</h2>
            <p><?php echo esc_html( $error_msg ); ?></p>
        </div>

    <?php else : ?>

        <!-- Vehicle Card -->
        <div class="vehicle-card">
            <div class="vehicle-photo-wrap">
                <?php if ( ! empty( $vehicle['photo_url'] ) ) : ?>
                    <img class="vehicle-photo"
                         src="<?php echo esc_url( $vehicle['photo_url'] ); ?>"
                         alt="<?php echo esc_attr( $vehicle['plate'] ); ?>">
                <?php else : ?>
                    <div class="vehicle-photo-placeholder">🚗</div>
                <?php endif; ?>

                <div class="vehicle-info">
                    <div class="vehicle-plate"><?php echo esc_html( $vehicle['plate'] ); ?></div>
                    <div class="vehicle-name"><?php echo esc_html( $vehicle['brand'] . ' ' . $vehicle['model'] . ( $vehicle['year'] ? ' ' . $vehicle['year'] : '' ) ); ?></div>
                    <span class="vehicle-badge" style="background: <?php echo esc_attr( $status_color ); ?>">
                        <span class="dot"></span>
                        <?php echo esc_html( $status_label ); ?>
                    </span>
                </div>
            </div>

            <div class="vehicle-meta">
                <div class="meta-item">
                    <div class="meta-label">Color</div>
                    <div class="meta-value"><?php echo esc_html( $vehicle['color'] ?: '—' ); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Tipo</div>
                    <div class="meta-value"><?php echo esc_html( ucfirst( $vehicle['type'] ?? '—' ) ); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Kilometraje</div>
                    <div class="meta-value"><?php echo isset( $vehicle['mileage'] ) ? number_format( (int) $vehicle['mileage'] ) . ' km' : '—'; ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Estado</div>
                    <div class="meta-value" style="color: <?php echo esc_attr( $status_color ); ?>"><?php echo esc_html( $status_label ); ?></div>
                </div>
            </div>
        </div>

        <?php if ( $has_active_trip ) : ?>
        <!-- Active trip indicator -->
        <div class="trip-indicator">
            <span class="trip-icon">🚀</span>
            <div class="trip-text">
                <strong>Salida activa en curso</strong>
                <span>Conductor: <?php echo esc_html( $vehicle['active_trip']['driver_name'] ?? 'Desconocido' ); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action section -->
        <div class="action-section" id="action-section">

            <!-- Alert messages -->
            <div class="alert alert-success" id="msg-success"></div>
            <div class="alert alert-error"   id="msg-error"></div>

            <!-- Success overlay (shown after submit) -->
            <div class="success-overlay" id="success-overlay">
                <div class="success-icon">✅</div>
                <h2 id="success-title">¡Registro exitoso!</h2>
                <p id="success-body">La operación fue registrada correctamente.</p>
                <div class="trip-id" id="success-trip-id"></div>
            </div>

            <!-- Form content -->
            <div id="form-content">
                <div class="action-title" id="action-title">
                    <?php echo $has_active_trip ? 'Registrar Retorno' : 'Registrar Salida'; ?>
                </div>
                <div class="action-subtitle" id="action-subtitle">
                    <?php if ( $has_active_trip ) : ?>
                        Confirma el retorno del vehículo a su ubicación de origen.
                    <?php else : ?>
                        Registra la salida de este vehículo. Proporciona tu nombre y el propósito del viaje.
                    <?php endif; ?>
                </div>

                <?php if ( $vehicle['status'] === 'maintenance' || $vehicle['status'] === 'unavailable' ) : ?>
                    <div class="alert alert-error show">
                        ⚠️ Este vehículo no está disponible para uso en este momento.
                    </div>
                <?php else : ?>

                    <!-- Driver identity block -->
                    <?php if ( $logged_in ) : ?>
                    <!-- Usuario WP logueado: mostrar tarjeta fija -->
                    <div class="driver-identity is-set" id="driver-identity">
                        <div class="driver-avatar" id="driver-avatar"><?php echo mb_strtoupper( mb_substr( $user_display_name, 0, 1 ) ); ?></div>
                        <div class="driver-identity-info">
                            <div class="driver-identity-label">Identificado como</div>
                            <div class="driver-identity-name" id="driver-shown-name"><?php echo $user_display_name; ?></div>
                            <div class="driver-identity-sub">Usuario registrado</div>
                        </div>
                    </div>
                    <input type="hidden" id="driver-name" value="<?php echo $user_display_name; ?>">
                    <?php else : ?>
                    <!-- Opción de inicio de sesión para visitantes (arriba de todo) -->
                    <div class="login-toggle-row" id="login-toggle-row">
                        <div class="login-divider-line"></div>
                        <button type="button" class="login-toggle-btn" id="login-toggle-btn" onclick="toggleLoginPanel()">
                            🔒 <span id="login-toggle-label">¿Tienes cuenta? Inicia sesión</span>
                        </button>
                        <div class="login-divider-line"></div>
                    </div>

                    <!-- Panel de login (oculto por defecto) -->
                    <div class="login-panel" id="login-panel">
                        <div class="login-panel-title">🔐 Iniciar sesión con tu cuenta</div>
                        <input type="text"
                               id="login-user"
                               class="form-control"
                               placeholder="Usuario o correo electrónico"
                               autocomplete="username"
                               autocapitalize="none"
                               spellcheck="false">
                        <input type="password"
                               id="login-pass"
                               class="form-control"
                               placeholder="Contraseña"
                               autocomplete="current-password">
                        <button type="button" class="btn-login-submit" id="btn-login-submit" onclick="doWpLogin()">
                            <span id="login-btn-text">→ Entrar</span>
                        </button>
                        <div class="login-error" id="login-error"></div>
                    </div>

                    <!-- Visitante: tarjeta de identificación con nombre desde localStorage -->
                    <div class="driver-identity" id="driver-identity">
                        <div class="driver-avatar" id="driver-avatar">?</div>
                        <div class="driver-identity-info">
                            <div class="driver-identity-label">Continuar como visitante</div>
                            <div class="driver-identity-name" id="driver-shown-name">Escribe tu nombre abajo</div>
                        </div>
                        <button type="button" class="btn-change-id" id="btn-change-id" onclick="showNameEdit()" style="display:none">✏️ Cambiar</button>
                    </div>
                    <!-- Campo de nombre (visible cuando no hay nombre guardado o el usuario quiere cambiarlo) -->
                    <div class="form-group driver-name-edit" id="driver-name-wrap">
                        <label class="form-label" for="driver-name">👤 Tu nombre completo</label>
                        <input type="text"
                               id="driver-name"
                               class="form-control"
                               placeholder="Escribe tu nombre…"
                               maxlength="120"
                               autocomplete="name"
                               oninput="syncDriverNamePreview()">
                    </div>
                    <?php endif; ?>

                    <?php if ( ! $has_active_trip ) : ?>
                    <!-- Trip type selector -->
                    <div class="form-group">
                        <label class="form-label">🏷️ Tipo de salida</label>
                        <div class="trip-type-grid" id="trip-type-grid">
                            <label class="trip-type-card type-rental">
                                <input type="radio" name="trip-type" value="rental" checked>
                                <span class="trip-type-label">
                                    <span class="trip-type-icon">💼</span>
                                    <span class="trip-type-name">Renta</span>
                                    <span class="trip-type-desc">Alquiler externo</span>
                                </span>
                            </label>
                            <label class="trip-type-card type-errand">
                                <input type="radio" name="trip-type" value="errand">
                                <span class="trip-type-label">
                                    <span class="trip-type-icon">📋</span>
                                    <span class="trip-type-name">Encargo</span>
                                    <span class="trip-type-desc">Diligencias / comisión</span>
                                </span>
                            </label>
                            <label class="trip-type-card type-maint">
                                <input type="radio" name="trip-type" value="maintenance">
                                <span class="trip-type-label">
                                    <span class="trip-type-icon">🔧</span>
                                    <span class="trip-type-name">Mantenimiento</span>
                                    <span class="trip-type-desc">Preventivo / correctivo</span>
                                </span>
                            </label>
                            <label class="trip-type-card type-other">
                                <input type="radio" name="trip-type" value="other">
                                <span class="trip-type-label">
                                    <span class="trip-type-icon">🚗</span>
                                    <span class="trip-type-name">Otro</span>
                                    <span class="trip-type-desc">Uso general</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Purpose / destination -->
                    <div class="form-group">
                        <label class="form-label" for="trip-purpose">🗺️ Destino / Propósito</label>
                        <input type="text"
                               id="trip-purpose"
                               class="form-control"
                               placeholder="Ej. Visita a cliente, entrega, diligencia…"
                               maxlength="255">
                    </div>

                    <!-- Odometer (optional) -->
                    <div class="form-group">
                        <label class="form-label" for="trip-mileage">🔢 Kilometraje inicial (opcional)</label>
                        <input type="number"
                               id="trip-mileage"
                               class="form-control"
                               placeholder="Km actuales del odómetro"
                               min="0"
                               value="<?php echo esc_attr( $vehicle['mileage'] ?? '' ); ?>">
                    </div>

                    <button class="btn btn-success" id="btn-checkout" onclick="submitTrip('checkout')">
                        <span>🚗</span> Registrar Salida
                    </button>

                    <?php else : ?>
                    <!-- Return mileage -->
                    <div class="form-group">
                        <label class="form-label" for="return-mileage">🔢 Kilometraje al retornar (opcional)</label>
                        <input type="number"
                               id="return-mileage"
                               class="form-control"
                               placeholder="Km actuales del odómetro"
                               min="<?php echo esc_attr( $vehicle['mileage'] ?? 0 ); ?>">
                    </div>

                    <!-- Notes -->
                    <div class="form-group">
                        <label class="form-label" for="return-notes">📝 Observaciones (opcional)</label>
                        <input type="text"
                               id="return-notes"
                               class="form-control"
                               placeholder="Novedades, incidentes, estado del vehículo…"
                               maxlength="500">
                    </div>

                    <button class="btn btn-warning" id="btn-return" onclick="submitTrip('return')">
                        <span>🏁</span> Registrar Retorno
                    </button>
                    <?php endif; ?>

                <?php endif; ?>
            </div><!-- /#form-content -->
        </div><!-- /.action-section -->

    <?php endif; ?>

    <footer class="qr-footer">
        <p>Powered by <a href="<?php echo $site_url; ?>"><?php echo $site_name; ?></a> · Aura Business Suite</p>
    </footer>

</main>

<script>
(function () {
    // ── Config data ───────────────────────────────────────────────
    var VEHICLE_ID   = <?php echo (int) ( $vehicle['id'] ?? 0 ); ?>;
    var HAS_TRIP     = <?php echo $has_active_trip ? 'true' : 'false'; ?>;
    var TRIP_ID      = <?php echo (int) ( $vehicle['active_trip']['id'] ?? 0 ); ?>;
    var API_BASE     = <?php echo wp_json_encode( $api_base ); ?>;
    var REST_NONCE   = <?php echo wp_json_encode( $nonce_rest ); ?>;
    var QR_TOKEN     = <?php echo wp_json_encode( $token ); ?>;
    var AJAX_URL     = <?php echo wp_json_encode( $login_url_wp ); ?>;
    var LOGIN_NONCE  = <?php echo wp_json_encode( $login_nonce ); ?>;
    var REDIRECT_URL = <?php echo wp_json_encode( $redirect_back ); ?>;

    // ── Theme management ──────────────────────────────────────────
    var root       = document.documentElement;
    var themeIcon  = document.getElementById('theme-icon');
    var themeLabel = document.getElementById('theme-label');
    var metaTheme  = document.getElementById('meta-theme-color');

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        if (theme === 'light') {
            themeIcon.textContent  = '🌙';
            themeLabel.textContent = 'Oscuro';
            metaTheme.content      = '#f0f4ff';
        } else {
            themeIcon.textContent  = '☀️';
            themeLabel.textContent = 'Claro';
            metaTheme.content      = '#0f172a';
        }
    }

    window.toggleTheme = function () {
        var current = root.getAttribute('data-theme') || 'dark';
        var next    = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem('aura-qr-theme', next);
        applyTheme(next);
    };

    // Restore saved theme
    var saved = localStorage.getItem('aura-qr-theme') || 'dark';
    applyTheme(saved);

    // ── Driver identity (localStorage) ───────────────────────────
    var IS_WP_USER = <?php echo $logged_in ? 'true' : 'false'; ?>;

    function initDriverIdentity() {
        if (IS_WP_USER) return; // WP user, nada que hacer
        var saved = localStorage.getItem('aura-qr-driver') || '';
        if (saved) {
            setDriverNameUI(saved);
        }
    }

    function setDriverNameUI(name) {
        var identity   = document.getElementById('driver-identity');
        var avatar     = document.getElementById('driver-avatar');
        var shownName  = document.getElementById('driver-shown-name');
        var nameWrap   = document.getElementById('driver-name-wrap');
        var changeBtn  = document.getElementById('btn-change-id');
        var nameInput  = document.getElementById('driver-name');

        if (!name) {
            if (identity)  identity.classList.remove('is-set');
            if (avatar)    avatar.textContent = '?';
            if (shownName) shownName.textContent = 'Escribe tu nombre abajo';
            if (nameWrap)  nameWrap.style.display = '';
            if (changeBtn) changeBtn.style.display = 'none';
            return;
        }

        if (nameInput)  nameInput.value = name;
        if (identity)   identity.classList.add('is-set');
        if (avatar)     avatar.textContent = name.trim().charAt(0).toUpperCase();
        if (shownName)  shownName.textContent = name.trim();
        if (nameWrap)   nameWrap.style.display = 'none';
        if (changeBtn)  changeBtn.style.display = '';
    }

    window.syncDriverNamePreview = function () {
        if (IS_WP_USER) return;
        var name = (document.getElementById('driver-name') || {}).value || '';
        var avatar    = document.getElementById('driver-avatar');
        var shownName = document.getElementById('driver-shown-name');
        if (avatar)    avatar.textContent = name ? name.trim().charAt(0).toUpperCase() : '?';
        if (shownName) shownName.textContent = name.trim() || 'Escribe tu nombre abajo';
        var identity = document.getElementById('driver-identity');
        if (identity)  identity.classList.toggle('is-set', !!name.trim());
    };

    window.showNameEdit = function () {
        var nameWrap  = document.getElementById('driver-name-wrap');
        var changeBtn = document.getElementById('btn-change-id');
        if (nameWrap)  nameWrap.style.display = '';
        if (changeBtn) changeBtn.style.display = 'none';
        var nameInput = document.getElementById('driver-name');
        if (nameInput) { nameInput.focus(); nameInput.select(); }
    };

    initDriverIdentity();

    // ── Login panel ───────────────────────────────────────────────
    window.toggleLoginPanel = function () {
        if (IS_WP_USER) return;
        var panel     = document.getElementById('login-panel');
        var toggleRow = document.getElementById('login-toggle-row');
        var label     = document.getElementById('login-toggle-label');
        if (!panel) return;
        var isOpen = panel.classList.toggle('open');
        if (label) label.textContent = isOpen ? 'Cerrar sesión' : '¿Tienes cuenta?';
        if (isOpen) {
            var el = document.getElementById('login-user');
            if (el) el.focus();
        }
    };

    window.doWpLogin = function () {
        var username = (document.getElementById('login-user') || {}).value || '';
        var password = (document.getElementById('login-pass') || {}).value || '';
        var errEl    = document.getElementById('login-error');
        var btnText  = document.getElementById('login-btn-text');
        var btn      = document.getElementById('btn-login-submit');

        if (!username.trim() || !password) {
            if (errEl) { errEl.textContent = 'Ingresa usuario y contraseña.'; errEl.classList.add('show'); }
            return;
        }

        if (errEl) errEl.classList.remove('show');
        if (btn)   btn.disabled = true;
        if (btnText) btnText.textContent = 'Ingresando…';

        var data = new URLSearchParams();
        data.append('action',      'aura_qr_login');
        data.append('log',         username.trim());
        data.append('pwd',         password);
        data.append('_wpnonce',    LOGIN_NONCE);
        data.append('redirect_to', REDIRECT_URL);

        fetch(AJAX_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    data.toString(),
        })
        .then(function (r) {
            return r.text().then(function (text) {
                var json = null;
                try { json = JSON.parse(text); } catch (e) { /* no es JSON */ }
                return { ok: r.ok, status: r.status, json: json };
            });
        })
        .then(function (r) {
            if (r.json && r.json.success) {
                if (btnText) btnText.textContent = '✅ ¡Listo! Cargando…';
                window.location.href = REDIRECT_URL;
            } else {
                var msg = (r.json && r.json.data)
                    ? r.json.data
                    : (r.status === 403 ? 'Solicitud no autorizada.' : 'Usuario o contraseña incorrectos.');
                if (errEl) { errEl.textContent = msg; errEl.classList.add('show'); }
                if (btn)   btn.disabled = false;
                if (btnText) btnText.textContent = '→ Entrar';
            }
        })
        .catch(function (err) {
            var msg = (err && err.message) ? err.message : 'Error de conexión.';
            if (errEl) { errEl.textContent = msg; errEl.classList.add('show'); }
            if (btn)   btn.disabled = false;
            if (btnText) btnText.textContent = '→ Entrar';
        });
    };

    // Enviar con Enter en los campos de login
    ['login-user','login-pass'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); doWpLogin(); }
        });
    });

    // ── Trip submission ───────────────────────────────────────────
    window.submitTrip = function (action) {
        var driverName = (document.getElementById('driver-name') || {}).value || '';
        var $btnId     = action === 'checkout' ? 'btn-checkout' : 'btn-return';
        var $btn       = document.getElementById($btnId);

        if (!driverName.trim()) {
            showMsg('error', 'Por favor ingresa tu nombre.');
            return;
        }

        var payload = {
            qr_token   : QR_TOKEN,
            driver_name: driverName.trim(),
        };

        if (action === 'checkout') {
            var tripTypeEl = document.querySelector('input[name="trip-type"]:checked');
            payload.trip_type = tripTypeEl ? tripTypeEl.value : 'errand';
            payload.purpose = (document.getElementById('trip-purpose') || {}).value || '';
            payload.mileage = parseInt((document.getElementById('trip-mileage') || {}).value || '0') || 0;
            payload.action  = 'checkout';
        } else {
            payload.mileage = parseInt((document.getElementById('return-mileage') || {}).value || '0') || 0;
            payload.notes   = (document.getElementById('return-notes') || {}).value || '';
            payload.action  = 'return';
            payload.trip_id = TRIP_ID;
        }

        setLoading($btn, true);
        clearMsgs();

        fetch(API_BASE + 'vehicles/qr/' + QR_TOKEN + '/trip', {
            method : 'POST',
            headers: {
                'Content-Type' : 'application/json',
                'X-WP-Nonce'   : REST_NONCE,
            },
            body: JSON.stringify(payload),
        })
        .then(function (r) { return r.json().then(function(d){ return {ok:r.ok, data:d}; }); })
        .then(function (r) {
            if (r.ok) {
                // Guardar nombre en localStorage para futuras visitas (solo visitantes)
                if (!IS_WP_USER && driverName) {
                    localStorage.setItem('aura-qr-driver', driverName);
                }
                showSuccessOverlay(action, r.data);
            } else {
                var msg = (r.data && r.data.message) ? r.data.message : 'Error al registrar.';
                showMsg('error', msg);
                setLoading($btn, false);
            }
        })
        .catch(function () {
            showMsg('error', 'Error de conexión. Intenta de nuevo.');
            setLoading($btn, false);
        });
    };

    function setLoading(btn, loading) {
        if (!btn) return;
        if (loading) {
            btn._origHtml = btn.innerHTML;
            btn.innerHTML = '<div class="spinner"></div> Procesando…';
            btn.disabled  = true;
        } else {
            btn.innerHTML = btn._origHtml || btn.innerHTML;
            btn.disabled  = false;
        }
    }

    function showMsg(type, msg) {
        var el = document.getElementById('msg-' + type);
        if (!el) return;
        el.textContent = msg;
        el.classList.add('show');
    }

    function clearMsgs() {
        ['success','error'].forEach(function(t){
            var el = document.getElementById('msg-' + t);
            if (el) { el.textContent=''; el.classList.remove('show'); }
        });
    }

    function showSuccessOverlay(action, data) {
        var overlay = document.getElementById('success-overlay');
        var fc      = document.getElementById('form-content');
        if (!overlay) return;

        document.getElementById('success-title').textContent =
            action === 'checkout' ? '¡Salida registrada!' : '¡Retorno registrado!';

        document.getElementById('success-body').textContent =
            action === 'checkout'
                ? 'La salida del vehículo fue registrada correctamente. ¡Buen viaje!'
                : 'El retorno fue registrado. Gracias por usar el sistema.';

        if (data && data.trip_id) {
            document.getElementById('success-trip-id').textContent = 'ID de salida: #' + data.trip_id;
        }

        if (fc) fc.style.display = 'none';
        overlay.classList.add('show');

        // Haptic feedback if available
        if (navigator.vibrate) { navigator.vibrate([50, 30, 50]); }
    }

})();
</script>
</body>
</html>
