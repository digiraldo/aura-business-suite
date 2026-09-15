<?php
/**
 * Template: Dashboard de Área — Fase 8, Ítem 8.3
 *
 * Acceso:  admin.php?page=aura-areas&view=dashboard[&area_id={id}]
 * Permisos: aura_areas_view_own | aura_areas_view_all | aura_areas_manage | manage_options
 *
 * @package AuraBusinessSuite
 * @subpackage Areas
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Permisos ──────────────────────────────────────────────────────────────
$_adb_can_manage   = current_user_can( 'manage_options' ) || current_user_can( 'aura_areas_manage' );
$_adb_can_all      = $_adb_can_manage || current_user_can( 'aura_areas_view_all' );
$_adb_can_own      = current_user_can( 'aura_areas_view_own' );
$_adb_can_budget   = $_adb_can_all || current_user_can( 'aura_areas_budget_view' );

global $wpdb;
$_areas_table = $wpdb->prefix . 'aura_areas';

// ── Selector de área ──────────────────────────────────────────────────────
$_adb_areas    = [];
$_adb_area_id  = isset( $_GET['area_id'] ) ? absint( $_GET['area_id'] ) : 0;
$_adb_area     = null;

if ( $_adb_can_all ) {
    $_adb_areas = $wpdb->get_results(
        "SELECT id, name, color, icon, slug, logo_id FROM `{$_areas_table}` WHERE status = 'active' ORDER BY sort_order, name"
    );
    if ( ! $_adb_area_id && ! empty( $_adb_areas ) ) {
        $_adb_area_id = (int) $_adb_areas[0]->id;
    }
} elseif ( $_adb_can_own ) {
    $_adb_areas = Aura_Areas_Setup::get_user_areas( get_current_user_id() );
    if ( empty( $_adb_areas ) ) {
        echo '<div class="wrap" style="margin-top:20px;"><div class="notice notice-warning"><p>'
            . esc_html__( 'No estás asignado a ningún área activa.', 'aura-suite' )
            . '</p></div></div>';
        return;
    }
    $allowed_ids = array_map( function( $a ) { return (int) $a->id; }, $_adb_areas );
    if ( ! $_adb_area_id || ! in_array( $_adb_area_id, $allowed_ids, true ) ) {
        $_adb_area_id = (int) $_adb_areas[0]->id;
    }
}

$_adb_logo_url = '';
if ( $_adb_area_id ) {
    $_adb_area = $wpdb->get_row( $wpdb->prepare(
        "SELECT a.*, u.display_name AS responsible_name
         FROM `{$_areas_table}` a
         LEFT JOIN `{$wpdb->users}` u ON u.ID = a.responsible_user_id
         WHERE a.id = %d",
        $_adb_area_id
    ) );

    if ( $_adb_area && ! empty( $_adb_area->logo_id ) ) {
        $_adb_logo_url = wp_get_attachment_image_url( (int) $_adb_area->logo_id, 'medium' ) ?: wp_get_attachment_url( (int) $_adb_area->logo_id ) ?: '';
    }
}

// Nonce para AJAX
$_adb_nonce = wp_create_nonce( Aura_Areas_Admin::NONCE );
?>

<div class="wrap aura-area-dashboard-wrap aura-module-wrap">

    <!-- ── Hero Banner Superior ─────────────────────────────────────── -->
    <div class="aura-hero-header wow-vip-card aura-dashboard-vip-header">
        <div class="aura-hero-content">
            <div id="adb-hero-icon-box" class="aura-hero-icon-box <?php echo $_adb_logo_url ? 'has-logo-img' : ''; ?>" style="<?php echo $_adb_logo_url ? 'background:#ffffff;' : 'background: linear-gradient(135deg, ' . esc_attr( $_adb_area->color ?? '#2271b1' ) . ', #0f172a);'; ?>">
                <?php if ( $_adb_logo_url ) : ?>
                    <img id="adb-hero-logo-img" src="<?php echo esc_url( $_adb_logo_url ); ?>" alt="<?php echo esc_attr( $_adb_area->name ?? '' ); ?>">
                <?php else : ?>
                    <span id="adb-hero-dashicon" class="dashicons <?php echo esc_attr( $_adb_area->icon ?? 'dashicons-building' ); ?>"></span>
                <?php endif; ?>
            </div>
            <div class="aura-hero-titles">
                <div class="aura-hero-subtitle-row">
                    <span class="aura-tag-badge">
                        <span class="dashicons dashicons-chart-pie"></span>
                        <?php esc_html_e( 'Rendimiento & Finanzas', 'aura-suite' ); ?>
                    </span>
                    <?php if ( $_adb_area && ! empty( $_adb_area->type ) ) : ?>
                    <span class="aura-tag-badge aura-tag-type">
                        <?php echo esc_html( ucfirst( $_adb_area->type ) ); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <h1 id="adb-hero-title" class="aura-hero-title">
                    <?php echo $_adb_area ? esc_html( $_adb_area->name ) : esc_html__( 'Dashboard de Área', 'aura-suite' ); ?>
                </h1>
                <p id="adb-hero-desc" class="aura-hero-desc">
                    <?php if ( $_adb_area && $_adb_area->description ) : ?>
                        <?php echo esc_html( $_adb_area->description ); ?> &bull;
                    <?php endif; ?>
                    <?php if ( $_adb_area && $_adb_area->responsible_name ) : ?>
                        <strong><?php esc_html_e( 'Responsable Titular:', 'aura-suite' ); ?></strong> <?php echo esc_html( $_adb_area->responsible_name ); ?>
                    <?php else : ?>
                        <?php esc_html_e( 'Monitoreo de ingresos, egresos, ejecución presupuestaria y actividad de esta área.', 'aura-suite' ); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="aura-hero-actions">
            <?php if ( $_adb_can_all && count( $_adb_areas ) > 1 ) : ?>
            <div class="adb-area-picker-wrap">
                <label for="adb-area-select" class="adb-area-picker-label">
                    <span class="dashicons dashicons-networking"></span> <?php esc_html_e( 'Cambiar Área:', 'aura-suite' ); ?>
                </label>
                <select id="adb-area-select" class="adb-select-input">
                    <?php foreach ( $_adb_areas as $_aa ) : ?>
                    <option value="<?php echo (int) $_aa->id; ?>" <?php selected( (int) $_aa->id, $_adb_area_id ); ?>>
                        <?php echo esc_html( $_aa->name ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <button type="button" id="adb-refresh-btn" class="aura-btn aura-btn-secondary" data-tooltip="Recargar datos del dashboard">
                <span class="dashicons dashicons-update"></span>
                <span><?php esc_html_e( 'Actualizar', 'aura-suite' ); ?></span>
            </button>

            <?php if ( $_adb_can_manage ) : ?>
            <a href="<?php echo admin_url( 'admin.php?page=aura-areas' ); ?>" class="aura-btn aura-btn-primary" data-tooltip="Volver al listado de áreas">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <span><?php esc_html_e( 'Gestión de Áreas', 'aura-suite' ); ?></span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Estado de Carga / Alertas ──────────────────────────────── -->
    <div id="adb-loading" class="adb-status-box">
        <div class="adb-spinner-ring"></div>
        <p><?php esc_html_e( 'Cargando indicadores y transacciones del área…', 'aura-suite' ); ?></p>
    </div>
    <div id="adb-error" class="notice notice-error" style="display:none;margin-top:16px;">
        <p id="adb-error-msg"></p>
    </div>

    <!-- ── Contenedor de Datos del Dashboard ──────────────────────── -->
    <div id="adb-content" style="display:none;margin-top:20px;">

        <!-- ── 1. Tarjetas KPI de Alto Impacto ──────────────────────── -->
        <div id="adb-kpis" class="aura-kpi-grid"></div>

        <!-- ── 2. Fila Principal: Desglose por Categoría + Alertas ─── -->
        <div class="adb-analytics-grid">

            <!-- Gráfico de barras por Categoría -->
            <div class="aura-card adb-chart-card">
                <div class="aura-card-header">
                    <div class="aura-card-title-group">
                        <span class="dashicons dashicons-chart-bar" style="color:#3b82f6;"></span>
                        <h3><?php esc_html_e( 'Gasto Ejecutado por Categoría', 'aura-suite' ); ?></h3>
                        <span class="aura-help-icon" data-tooltip="Distribución acumulada de egresos aprobados asignados a esta área por categoría de gasto.">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </div>
                </div>
                <div class="aura-card-body">
                    <div id="adb-chart" class="adb-chart-container">
                        <p class="adb-empty-state"><?php esc_html_e( 'Sin datos de egresos registrados.', 'aura-suite' ); ?></p>
                    </div>
                </div>
            </div>

            <!-- Panel de Alertas Presupuestarias -->
            <div class="aura-card adb-alerts-card">
                <div class="aura-card-header">
                    <div class="aura-card-title-group">
                        <span class="dashicons dashicons-warning" style="color:#ef4444;"></span>
                        <h3><?php esc_html_e( 'Alertas de Presupuesto', 'aura-suite' ); ?></h3>
                        <span class="aura-help-icon" data-tooltip="Avisos automáticos de partidas presupuestarias que han alcanzado o superado el umbral de alerta.">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </div>
                </div>
                <div class="aura-card-body">
                    <div id="adb-alerts" class="adb-alerts-container">
                        <p class="adb-empty-state"><?php esc_html_e( 'Sin alertas presupuestarias activas.', 'aura-suite' ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── 3. Historial de Últimas Transacciones ─────────────────── -->
        <div class="aura-card adb-table-card" style="margin-top:24px;">
            <div class="aura-card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div class="aura-card-title-group">
                    <span class="dashicons dashicons-list-view" style="color:#10b981;"></span>
                    <h3><?php esc_html_e( 'Últimas Transacciones Registradas', 'aura-suite' ); ?></h3>
                    <span id="adb-tx-count-badge" class="aura-badge aura-badge-blue" style="display:none;"></span>
                    <span class="aura-help-icon" data-tooltip="Historial reciente de ingresos y egresos asociados a este centro de costos.">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </div>
                <div>
                    <a id="adb-all-tx-link" href="#" class="aura-btn aura-btn-secondary" style="font-size:12px;padding:6px 12px;">
                        <span><?php esc_html_e( 'Ver todas en Finanzas', 'aura-suite' ); ?></span>
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                    </a>
                </div>
            </div>
            <div class="aura-card-body" style="padding:0;">
                <div id="adb-transactions" class="adb-tx-container">
                    <p class="adb-empty-state" style="padding:24px;"><?php esc_html_e( 'Sin transacciones registradas para esta área.', 'aura-suite' ); ?></p>
                </div>
            </div>
        </div>

    </div><!-- #adb-content -->

</div><!-- .wrap -->

<style>
/* ── ESTILOS DEL DASHBOARD DE ÁREAS (AURA DESIGN SYSTEM) ───────────────── */
.aura-area-dashboard-wrap {
    margin: 16px 20px 30px 2px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    color: #1e293b;
}

/* Hero Header */
.aura-area-dashboard-wrap .aura-hero-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 14px;
    padding: 24px 28px;
    color: #ffffff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
    margin-bottom: 24px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.aura-area-dashboard-wrap .aura-hero-content {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    min-width: 280px;
}
.aura-area-dashboard-wrap .aura-hero-icon-box {
    width: 64px;
    height: 64px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 8px 16px rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.2);
    overflow: hidden;
    padding: 0;
}
.aura-area-dashboard-wrap .aura-hero-icon-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.aura-area-dashboard-wrap .aura-hero-icon-box.has-logo-img {
    border-color: rgba(255, 255, 255, 0.4);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
}
.aura-area-dashboard-wrap .aura-hero-icon-box .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #ffffff;
}
.aura-area-dashboard-wrap .aura-hero-titles {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.aura-area-dashboard-wrap .aura-hero-subtitle-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.aura-area-dashboard-wrap .aura-tag-badge {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #e2e8f0;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.aura-area-dashboard-wrap .aura-tag-badge .dashicons {
    font-size: 13px;
    width: 13px;
    height: 13px;
}
.aura-area-dashboard-wrap .aura-tag-type {
    background: rgba(59, 130, 246, 0.25);
    border-color: rgba(59, 130, 246, 0.4);
    color: #93c5fd;
}
.aura-area-dashboard-wrap .aura-hero-title {
    color: #ffffff;
    font-size: 24px;
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
}
.aura-area-dashboard-wrap .aura-hero-desc {
    color: #94a3b8;
    font-size: 13.5px;
    margin: 0;
    line-height: 1.4;
}
.aura-area-dashboard-wrap .aura-hero-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.adb-area-picker-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.adb-area-picker-label {
    font-size: 11px;
    font-weight: 600;
    color: #cbd5e1;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.adb-area-picker-label .dashicons {
    font-size: 13px;
    width: 13px;
    height: 13px;
}
.adb-select-input {
    background: rgba(15, 23, 42, 0.6) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    padding: 7px 32px 7px 12px !important;
    min-width: 220px;
    outline: none !important;
    cursor: pointer;
}
.adb-select-input option {
    background: #0f172a;
    color: #ffffff;
}

/* Botones Aura */
.aura-area-dashboard-wrap .aura-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    height: 38px;
    box-sizing: border-box;
}
.aura-area-dashboard-wrap .aura-btn-primary {
    background: #2563eb;
    color: #ffffff;
    border-color: #1d4ed8;
}
.aura-area-dashboard-wrap .aura-btn-primary:hover {
    background: #1d4ed8;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.aura-area-dashboard-wrap .aura-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.2);
}
.aura-area-dashboard-wrap .aura-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}

/* Status / Loading */
.adb-status-box {
    background: #ffffff;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}
.adb-spinner-ring {
    display: inline-block;
    width: 38px;
    height: 38px;
    border: 4px solid #e2e8f0;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: adb-spin 0.8s linear infinite;
    margin-bottom: 12px;
}
@keyframes adb-spin { to { transform: rotate(360deg); } }

/* Grid de KPIs */
.aura-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.aura-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
    overflow: hidden;
}
.aura-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}
.aura-stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.aura-stat-card .stat-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    color: #ffffff;
}
.aura-stat-card .stat-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
    min-width: 0;
}
.aura-stat-card .stat-label-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
}
.aura-stat-card .stat-label {
    font-size: 11.5px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.aura-stat-card .stat-value {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
}
.aura-stat-card .stat-sub {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 2px;
}

/* Cards & Layout */
.adb-analytics-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
    align-items: start;
}
.aura-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    overflow: hidden;
}
.aura-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
}
.aura-card-title-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.aura-card-title-group h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
}
.aura-card-body {
    padding: 20px;
}
.adb-empty-state {
    color: #94a3b8;
    font-style: italic;
    font-size: 13px;
    text-align: center;
    margin: 12px 0;
}

/* Gráficos de barra */
.adb-bar-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}
.adb-bar-label {
    width: 140px;
    flex-shrink: 0;
    font-size: 12.5px;
    font-weight: 600;
    color: #334155;
    text-align: right;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.adb-bar-track {
    flex: 1;
    background: #f1f5f9;
    border-radius: 6px;
    height: 18px;
    overflow: hidden;
    position: relative;
}
.adb-bar-fill {
    height: 100%;
    border-radius: 6px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.adb-bar-amt {
    width: 95px;
    flex-shrink: 0;
    text-align: right;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}

/* Alertas */
.adb-alert-row {
    padding: 12px 14px;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 4px solid;
    background: #f8fafc;
}
.adb-alert-row.exceeded {
    background: #fef2f2;
    border-color: #ef4444;
}
.adb-alert-row.threshold {
    background: #fffbeb;
    border-color: #f59e0b;
}
.adb-alert-row .adb-alert-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.adb-alert-row .adb-alert-cat {
    font-weight: 700;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.adb-alert-row .adb-alert-pct {
    font-size: 14px;
    font-weight: 800;
}
.adb-alert-row .adb-alert-meta {
    font-size: 11.5px;
    color: #64748b;
    margin-top: 4px;
}

/* Tabla de transacciones */
.adb-tx-table {
    width: 100%;
    border-collapse: collapse;
}
.adb-tx-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}
.adb-tx-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
    vertical-align: middle;
}
.adb-tx-table tr:hover td {
    background: #f8fafc;
}
.adb-tx-table tr:last-child td {
    border-bottom: none;
}
.aura-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
}
.aura-badge-blue { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
.aura-badge-green { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.aura-badge-orange { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
.aura-badge-red { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

/* Ayudas contextuales y Tooltips */
.aura-help-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    cursor: help;
    position: relative;
    transition: color 0.15s ease;
}
.aura-help-icon:hover {
    color: #3b82f6;
}
.aura-help-icon .dashicons {
    font-size: 15px;
    width: 15px;
    height: 15px;
}

[data-tooltip] {
    position: relative;
}
[data-tooltip]:hover::before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    background: #0f172a;
    color: #ffffff;
    font-size: 11.5px;
    font-weight: 500;
    line-height: 1.4;
    padding: 6px 10px;
    border-radius: 6px;
    white-space: normal;
    width: max-content;
    max-width: 260px;
    text-align: center;
    z-index: 999999999 !important;
    pointer-events: none;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.4);
    border: 1px solid #334155;
}
[data-tooltip]:hover::after {
    content: '';
    position: absolute;
    bottom: calc(100% + 2px);
    left: 50%;
    transform: translateX(-50%);
    border-width: 6px 6px 0 6px;
    border-style: solid;
    border-color: #0f172a transparent transparent transparent;
    z-index: 999999999 !important;
    pointer-events: none;
}

/* Modo Oscuro */
html[data-wp-dark-mode-scheme="dark"] .aura-stat-card,
body.aura-dark-mode .aura-stat-card,
body[data-theme="dark"] .aura-stat-card,
html[data-wp-dark-mode-scheme="dark"] .aura-card,
body.aura-dark-mode .aura-card,
body[data-theme="dark"] .aura-card,
html[data-wp-dark-mode-scheme="dark"] .adb-status-box,
body.aura-dark-mode .adb-status-box,
body[data-theme="dark"] .adb-status-box {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}
html[data-wp-dark-mode-scheme="dark"] .aura-card-header,
body.aura-dark-mode .aura-card-header,
body[data-theme="dark"] .aura-card-header {
    background: #1e293b !important;
    border-bottom-color: #334155 !important;
}
html[data-wp-dark-mode-scheme="dark"] .aura-card-title-group h3,
body.aura-dark-mode .aura-card-title-group h3,
body[data-theme="dark"] .aura-card-title-group h3,
html[data-wp-dark-mode-scheme="dark"] .aura-stat-card .stat-value,
body.aura-dark-mode .aura-stat-card .stat-value,
body[data-theme="dark"] .aura-stat-card .stat-value,
html[data-wp-dark-mode-scheme="dark"] .adb-bar-amt,
body.aura-dark-mode .adb-bar-amt,
body[data-theme="dark"] .adb-bar-amt {
    color: #f8fafc !important;
}
html[data-wp-dark-mode-scheme="dark"] .adb-tx-table th,
body.aura-dark-mode .adb-tx-table th,
body[data-theme="dark"] .adb-tx-table th {
    background: #0f172a !important;
    color: #cbd5e1 !important;
    border-bottom-color: #334155 !important;
}
html[data-wp-dark-mode-scheme="dark"] .adb-tx-table td,
body.aura-dark-mode .adb-tx-table td,
body[data-theme="dark"] .adb-tx-table td {
    border-bottom-color: #334155 !important;
    color: #e2e8f0 !important;
}
html[data-wp-dark-mode-scheme="dark"] .adb-bar-label,
body.aura-dark-mode .adb-bar-label,
body[data-theme="dark"] .adb-bar-label {
    color: #cbd5e1 !important;
}
html[data-wp-dark-mode-scheme="dark"] .adb-bar-track,
body.aura-dark-mode .adb-bar-track,
body[data-theme="dark"] .adb-bar-track {
    background: #0f172a !important;
}

@media (max-width: 960px) {
    .adb-analytics-grid { grid-template-columns: 1fr !important; }
    .aura-area-dashboard-wrap .aura-hero-header { flex-direction: column; align-items: flex-start; }
    .aura-area-dashboard-wrap .aura-hero-actions { width: 100%; justify-content: space-between; }
}
</style>

<script type="text/javascript">
(function($) {
    'use strict';

    var ajaxUrl   = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';
    var nonce     = '<?php echo esc_js( $_adb_nonce ); ?>';
    var txListUrl = '<?php echo esc_js( admin_url( "admin.php?page=aura-financial-transactions" ) ); ?>';

    function fmt(n) {
        n = parseFloat(n) || 0;
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(n);
    }

    function escHtml(s) {
        return $('<div>').text(s || '').html();
    }

    function updateHeroBanner(area) {
        if (!area) return;
        $('#adb-hero-title').text(area.name || '');
        if (area.description) {
            $('#adb-hero-desc').html(escHtml(area.description) + (area.responsible_name ? ' &bull; <strong><?php echo esc_js( __( "Responsable Titular:", "aura-suite" ) ); ?></strong> ' + escHtml(area.responsible_name) : ''));
        }

        var $box = $('#adb-hero-icon-box');
        if (area.logo_url) {
            $box.addClass('has-logo-img').css('background', '#ffffff');
            $box.html('<img id="adb-hero-logo-img" src="' + escHtml(area.logo_url) + '" alt="' + escHtml(area.name) + '">');
        } else {
            $box.removeClass('has-logo-img').css('background', 'linear-gradient(135deg, ' + (area.color || '#2271b1') + ', #0f172a)');
            $box.html('<span id="adb-hero-dashicon" class="dashicons ' + escHtml(area.icon || 'dashicons-building') + '"></span>');
        }
    }

    /* ── Cargar datos del dashboard ─────────────────────────── */
    function loadDashboard(areaId) {
        $('#adb-loading').show();
        $('#adb-content, #adb-error').hide();

        $.post(ajaxUrl, {
            action:  'aura_area_dashboard_data',
            nonce:   nonce,
            area_id: areaId
        }, function(resp) {
            $('#adb-loading').hide();
            if (!resp.success) {
                $('#adb-error-msg').text(resp.data ? resp.data.message : 'Error al cargar el dashboard');
                $('#adb-error').show();
                return;
            }
            var d = resp.data;
            updateHeroBanner(d.area);
            renderKPIs(d.kpis, d.area);
            renderChart(d.chart_data);
            renderAlerts(d.kpis);
            renderTransactions(d.recent_tx, d.tx_count, areaId);
            $('#adb-content').fadeIn(200);
        }).fail(function() {
            $('#adb-loading').hide();
            $('#adb-error-msg').text('<?php echo esc_js( __( "Error de comunicación con el servidor.", "aura-suite" ) ); ?>');
            $('#adb-error').show();
        });
    }

    /* ── Render KPIs ─────────────────────────────────────────── */
    function renderKPIs(kpis, area) {
        var $c = $('#adb-kpis').empty();
        if (!kpis) {
            $c.append('<p class="adb-empty-state" style="grid-column:1/-1;"><?php echo esc_js( __( "Sin indicadores de presupuesto activos para esta área.", "aura-suite" ) ); ?></p>');
            return;
        }

        var pct = parseFloat(kpis.pct) || 0;
        var pctBg = pct > 100 ? '#ef4444' : (pct >= 90 ? '#f97316' : (pct >= 70 ? '#f59e0b' : '#10b981'));

        var items = [
            {
                icon: 'dashicons-money-alt',
                bg: '#2563eb',
                label: '<?php echo esc_js( __( "Presupuesto Asignado", "aura-suite" ) ); ?>',
                value: fmt(kpis.total_budget),
                sub: '<?php echo esc_js( __( "Monto total autorizado", "aura-suite" ) ); ?>',
                tooltip: 'Suma de todos los presupuestos asignados y vigentes para esta área.'
            },
            {
                icon: 'dashicons-arrow-down-alt',
                bg: '#ef4444',
                label: '<?php echo esc_js( __( "Egresos Ejecutados", "aura-suite" ) ); ?>',
                value: fmt(kpis.total_executed),
                sub: '<?php echo esc_js( __( "Gastos aprobados", "aura-suite" ) ); ?>',
                tooltip: 'Total de egresos registrados y aprobados para esta área.'
            },
            {
                icon: 'dashicons-arrow-up-alt',
                bg: '#10b981',
                label: '<?php echo esc_js( __( "Ingresos del Área", "aura-suite" ) ); ?>',
                value: fmt(kpis.total_income),
                sub: '<?php echo esc_js( __( "Entradas percibidas", "aura-suite" ) ); ?>',
                tooltip: 'Total de ingresos y aportaciones aprobadas a nombre de esta área.'
            },
            {
                icon: pct > 100 ? 'dashicons-warning' : 'dashicons-yes-alt',
                bg: pct > 100 ? '#dc2626' : '#059669',
                label: pct > 100 ? '<?php echo esc_js( __( "Sobregiro", "aura-suite" ) ); ?>' : '<?php echo esc_js( __( "Disponible", "aura-suite" ) ); ?>',
                value: pct > 100 ? fmt(kpis.overrun) : fmt(kpis.available),
                sub: pct > 100 ? '<?php echo esc_js( __( "Excedido del tope", "aura-suite" ) ); ?>' : '<?php echo esc_js( __( "Remanente a ejercer", "aura-suite" ) ); ?>',
                tooltip: pct > 100 ? 'Monto ejecutado por encima del presupuesto autorizado.' : 'Saldo disponible antes de alcanzar el límite presupuestario.'
            },
            {
                icon: 'dashicons-chart-pie',
                bg: pctBg,
                label: '<?php echo esc_js( __( "% Ejecución", "aura-suite" ) ); ?>',
                value: pct + '%',
                sub: '<?php echo esc_js( __( "Avance presupuestal", "aura-suite" ) ); ?>',
                tooltip: 'Porcentaje consumido del presupuesto total asignado.'
            }
        ];

        items.forEach(function(it) {
            $c.append(
                '<div class="aura-stat-card">'
                + '<div class="stat-icon" style="background:' + it.bg + ';"><span class="dashicons ' + it.icon + '"></span></div>'
                + '<div class="stat-info">'
                + '<div class="stat-label-wrap">'
                + '<span class="stat-label">' + escHtml(it.label) + '</span>'
                + '<span class="aura-help-icon" data-tooltip="' + escHtml(it.tooltip) + '"><span class="dashicons dashicons-editor-help"></span></span>'
                + '</div>'
                + '<div class="stat-value">' + escHtml(it.value) + '</div>'
                + (it.sub ? '<div class="stat-sub">' + escHtml(it.sub) + '</div>' : '')
                + '</div>'
                + '</div>'
            );
        });
    }

    /* ── Render Gráfico de Categorías ─────────────────────────── */
    function renderChart(chartData) {
        var $c = $('#adb-chart').empty();
        if (!chartData || !chartData.length) {
            $c.html('<p class="adb-empty-state"><?php echo esc_js( __( "Sin egresos registrados para esta área.", "aura-suite" ) ); ?></p>');
            return;
        }
        chartData.forEach(function(row) {
            $c.append(
                '<div class="adb-bar-row">'
                + '<div class="adb-bar-label" title="' + escHtml(row.name) + '">' + escHtml(row.name) + '</div>'
                + '<div class="adb-bar-track">'
                + '<div class="adb-bar-fill" style="width:' + row.pct + '%;background:' + (row.color || '#3b82f6') + ';"></div>'
                + '</div>'
                + '<div class="adb-bar-amt">' + fmt(row.total) + '</div>'
                + '</div>'
            );
        });
    }

    /* ── Render Alertas ───────────────────────────────────────── */
    function renderAlerts(kpis) {
        var $c = $('#adb-alerts').empty();
        if (!kpis || !kpis.alerts || !kpis.alerts.length) {
            $c.html('<div style="color:#059669;font-size:13.5px;font-weight:600;display:flex;align-items:center;gap:6px;padding:12px 0;"><span class="dashicons dashicons-yes-alt" style="font-size:18px;width:18px;height:18px;"></span><?php echo esc_js( __( "Todo en orden. No hay alertas presupuestarias activas.", "aura-suite" ) ); ?></div>');
            return;
        }
        kpis.alerts.forEach(function(a) {
            var cls   = a.is_exceeded ? 'exceeded' : 'threshold';
            var icon  = a.is_exceeded ? 'dashicons-warning' : 'dashicons-bell';
            var color = a.is_exceeded ? '#ef4444' : '#f59e0b';
            $c.append(
                '<div class="adb-alert-row ' + cls + '">'
                + '<div class="adb-alert-header">'
                + '<span class="adb-alert-cat" style="color:' + color + ';"><span class="dashicons ' + icon + '"></span>' + escHtml(a.category_name) + '</span>'
                + '<span class="adb-alert-pct" style="color:' + color + ';">' + parseFloat(a.pct).toFixed(1) + '%</span>'
                + '</div>'
                + '<div class="adb-alert-meta">'
                + '<?php echo esc_js( __( "Presupuesto:", "aura-suite" ) ); ?> ' + fmt(a.budget_amount) + ' &bull; '
                + '<?php echo esc_js( __( "Ejecutado:", "aura-suite" ) ); ?> ' + fmt(a.executed)
                + '</div>'
                + '</div>'
            );
        });
    }

    /* ── Render Transacciones ─────────────────────────────────── */
    function renderTransactions(txList, txCount, areaId) {
        var badge = $('#adb-tx-count-badge');
        if (txCount > 0) {
            badge.text(txCount + ' <?php echo esc_js( __( "registradas", "aura-suite" ) ); ?>').show();
        } else {
            badge.hide();
        }

        $('#adb-all-tx-link').attr('href', txListUrl + '&filter_area=' + areaId);

        var $c = $('#adb-transactions').empty();
        if (!txList || !txList.length) {
            $c.html('<p class="adb-empty-state" style="padding:24px;"><?php echo esc_js( __( "Sin transacciones registradas para esta área.", "aura-suite" ) ); ?></p>');
            return;
        }

        var html = '<table class="adb-tx-table">'
            + '<thead><tr>'
            + '<th><?php echo esc_js( __( "Fecha", "aura-suite" ) ); ?></th>'
            + '<th><?php echo esc_js( __( "Categoría", "aura-suite" ) ); ?></th>'
            + '<th><?php echo esc_js( __( "Descripción", "aura-suite" ) ); ?></th>'
            + '<th style="text-align:right"><?php echo esc_js( __( "Monto", "aura-suite" ) ); ?></th>'
            + '<th><?php echo esc_js( __( "Estado", "aura-suite" ) ); ?></th>'
            + '<th><?php echo esc_js( __( "Usuario", "aura-suite" ) ); ?></th>'
            + '</tr></thead><tbody>';

        txList.forEach(function(t) {
            var isInc  = t.transaction_type === 'income';
            var amtClr = isInc ? '#059669' : '#dc2626';
            var amtSgn = isInc ? '+' : '-';
            var catBg  = t.category_color || '#64748b';
            var stateMap = {
                pending:  { label: '<?php echo esc_js( __( "Pendiente", "aura-suite" ) ); ?>', cls: 'aura-badge-orange' },
                approved: { label: '<?php echo esc_js( __( "Aprobado",  "aura-suite" ) ); ?>', cls: 'aura-badge-green' },
                rejected: { label: '<?php echo esc_js( __( "Rechazado", "aura-suite" ) ); ?>', cls: 'aura-badge-red' }
            };
            var st = stateMap[t.status] || stateMap.pending;
            var desc = t.description || '';
            if (desc.length > 45) desc = desc.substring(0, 45) + '…';

            html += '<tr>'
                + '<td style="white-space:nowrap;color:#64748b;font-weight:600;">' + escHtml(t.transaction_date || '') + '</td>'
                + '<td>'
                + (t.category_name
                    ? '<span class="aura-badge" style="background:' + catBg + '18;color:' + catBg + ';border:1px solid ' + catBg + '40;">' + escHtml(t.category_name) + '</span>'
                    : '<em style="color:#94a3b8;">—</em>')
                + '</td>'
                + '<td title="' + escHtml(t.description || '') + '" style="font-weight:500;">' + escHtml(desc) + '</td>'
                + '<td style="text-align:right;white-space:nowrap;font-weight:800;color:' + amtClr + ';">'
                + amtSgn + fmt(t.amount)
                + '</td>'
                + '<td><span class="aura-badge ' + st.cls + '">' + st.label + '</span></td>'
                + '<td style="color:#64748b;font-size:12px;">' + escHtml(t.created_by_name || '—') + '</td>'
                + '</tr>';
        });

        html += '</tbody></table>';
        $c.html(html);
    }

    /* ── Inicialización ──────────────────────────────────────── */
    $(document).ready(function() {
        var currentAreaId = <?php echo (int) $_adb_area_id; ?>;

        if (currentAreaId) {
            loadDashboard(currentAreaId);
        }

        $('#adb-area-select').on('change', function() {
            currentAreaId = parseInt($(this).val(), 10);
            if (currentAreaId) {
                if (window.history && window.history.replaceState) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('area_id', currentAreaId);
                    window.history.replaceState({}, '', url.toString());
                }
                loadDashboard(currentAreaId);
            }
        });

        $('#adb-refresh-btn').on('click', function() {
            if (currentAreaId) {
                loadDashboard(currentAreaId);
            }
        });
    });

})(jQuery);
</script>
