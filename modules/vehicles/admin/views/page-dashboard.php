<?php
/**
 * Vista: Dashboard Flota — Fase 5: Dashboard y KPIs
 *
 * Muestra KPIs en tiempo real y 5 gráficas vía REST API + Chart.js.
 * Los datos se cargan asincrónicamente al iniciar la página y se
 * actualizan al cambiar el período o el área seleccionada.
 *
 * @package Aura_Business_Suite\Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$can_view_all = current_user_can( 'aura_vehicles_view_all' ) || current_user_can( 'manage_options' );

// Cargar áreas disponibles para el selector (solo si tiene view_all)
$areas = array();
if ( $can_view_all ) {
    $areas = $wpdb->get_results(
        "SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE active = 1 ORDER BY name ASC"
    );
}
?>
<div class="wrap" id="aura-veh-dashboard-page">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Dashboard — Flota Vehicular', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">

    <div id="aura-dash-notice" class="aura-dash-notice" style="display:none;"></div>

    <!-- ── Barra de controles ─────────────────────────────────── -->
    <div class="aura-dash-controls">
        <div class="aura-dash-period-group">
            <span class="aura-dash-controls-label"><?php esc_html_e( 'Período:', 'aura-suite' ); ?></span>
            <div class="aura-dash-period-btns">
                <button type="button" class="aura-dash-period-btn" data-period="7d">
                    <?php esc_html_e( '7 días', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-dash-period-btn is-active" data-period="30d">
                    <?php esc_html_e( '30 días', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-dash-period-btn" data-period="90d">
                    <?php esc_html_e( '90 días', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-dash-period-btn" data-period="year">
                    <?php esc_html_e( 'Año actual', 'aura-suite' ); ?>
                </button>
            </div>
        </div>

        <?php if ( $can_view_all && ! empty( $areas ) ) : ?>
        <div class="aura-dash-area-group">
            <label for="aura-dash-area-filter" class="aura-dash-controls-label">
                <?php esc_html_e( 'Área:', 'aura-suite' ); ?>
            </label>
            <select id="aura-dash-area-filter" class="aura-dash-area-select">
                <option value="0"><?php esc_html_e( '— Todas las áreas —', 'aura-suite' ); ?></option>
                <?php foreach ( $areas as $area ) : ?>
                <option value="<?php echo esc_attr( $area->id ); ?>">
                    <?php echo esc_html( $area->name ); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="aura-dash-refresh-wrap">
            <button type="button" id="aura-dash-refresh" class="button">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'Actualizar', 'aura-suite' ); ?>
            </button>
            <span id="aura-dash-last-update" class="aura-dash-last-update" style="display:none;"></span>
        </div>
    </div>

    <!-- ── Fila 1: KPIs de flota ─────────────────────────────── -->
    <div class="aura-dash-section-title">
        <span class="dashicons dashicons-car"></span>
        <?php esc_html_e( 'Estado de Flota', 'aura-suite' ); ?>
    </div>
    <div class="aura-dash-kpis" id="aura-dash-kpis-fleet">
        <div class="aura-dash-kpi-card aura-dash-kpi-available">
            <div class="aura-dash-kpi-icon"><span class="dashicons dashicons-yes-alt"></span></div>
            <div class="aura-dash-kpi-value" id="kpi-available">—</div>
            <div class="aura-dash-kpi-label"><?php esc_html_e( 'Disponibles', 'aura-suite' ); ?></div>
        </div>
        <div class="aura-dash-kpi-card aura-dash-kpi-rented">
            <div class="aura-dash-kpi-icon"><span class="dashicons dashicons-car"></span></div>
            <div class="aura-dash-kpi-value" id="kpi-rented">—</div>
            <div class="aura-dash-kpi-label"><?php esc_html_e( 'En Uso', 'aura-suite' ); ?></div>
        </div>
        <div class="aura-dash-kpi-card aura-dash-kpi-maintenance">
            <div class="aura-dash-kpi-icon"><span class="dashicons dashicons-admin-tools"></span></div>
            <div class="aura-dash-kpi-value" id="kpi-maintenance">—</div>
            <div class="aura-dash-kpi-label"><?php esc_html_e( 'En Mantenimiento', 'aura-suite' ); ?></div>
        </div>
        <div class="aura-dash-kpi-card aura-dash-kpi-unavailable">
            <div class="aura-dash-kpi-icon"><span class="dashicons dashicons-dismiss"></span></div>
            <div class="aura-dash-kpi-value" id="kpi-unavailable">—</div>
            <div class="aura-dash-kpi-label"><?php esc_html_e( 'No Disponibles', 'aura-suite' ); ?></div>
        </div>
    </div>

    <!-- ── Fila 2: KPIs de actividad del período ─────────────── -->
    <div class="aura-dash-section-title">
        <span class="dashicons dashicons-chart-bar"></span>
        <span id="aura-dash-period-label"><?php esc_html_e( 'Actividad del Período', 'aura-suite' ); ?></span>
    </div>
    <div class="aura-dash-kpis" id="aura-dash-kpis-activity">
        <div class="aura-dash-kpi-card aura-dash-kpi-active-trips">
            <div class="aura-dash-kpi-icon"><span class="dashicons dashicons-location-alt"></span></div>
            <div class="aura-dash-kpi-value" id="kpi-active-trips">—</div>
            <div class="aura-dash-kpi-label"><?php esc_html_e( 'Salidas Activas', 'aura-suite' ); ?></div>
        </div>
        <div class="aura-dash-kpi-card aura-dash-kpi-trips-today">
            <div class="aura-dash-kpi-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
            <div class="aura-dash-kpi-value" id="kpi-trips-today">—</div>
            <div class="aura-dash-kpi-label"><?php esc_html_e( 'Salidas Hoy', 'aura-suite' ); ?></div>
        </div>
        <div class="aura-dash-kpi-card aura-dash-kpi-km">
            <div class="aura-dash-kpi-icon"><span class="dashicons dashicons-dashboard"></span></div>
            <div class="aura-dash-kpi-value" id="kpi-km-total">—</div>
            <div class="aura-dash-kpi-label"><?php esc_html_e( 'KM del Período', 'aura-suite' ); ?></div>
        </div>
        <div class="aura-dash-kpi-card aura-dash-kpi-income">
            <div class="aura-dash-kpi-icon"><span class="dashicons dashicons-money-alt"></span></div>
            <div class="aura-dash-kpi-value" id="kpi-income">—</div>
            <div class="aura-dash-kpi-label"><?php esc_html_e( 'Ingresos', 'aura-suite' ); ?></div>
        </div>
        <div class="aura-dash-kpi-card aura-dash-kpi-costs">
            <div class="aura-dash-kpi-icon"><span class="dashicons dashicons-calculator"></span></div>
            <div class="aura-dash-kpi-value" id="kpi-costs">—</div>
            <div class="aura-dash-kpi-label"><?php esc_html_e( 'Costos', 'aura-suite' ); ?></div>
        </div>
    </div>

    <!-- ── Spinner de carga ───────────────────────────────────── -->
    <div id="aura-dash-loader" class="aura-dash-loader" style="display:none;">
        <span class="spinner is-active"></span>
        <span><?php esc_html_e( 'Cargando datos…', 'aura-suite' ); ?></span>
    </div>

    <!-- ── Fila de gráficas 1: Estado flota + KM por vehículo ── -->
    <div class="aura-dash-charts-row">
        <div class="aura-dash-chart-box aura-dash-chart-sm">
            <div class="aura-dash-chart-header">
                <span class="dashicons dashicons-chart-pie"></span>
                <h3><?php esc_html_e( 'Estado de Flota', 'aura-suite' ); ?></h3>
            </div>
            <div class="aura-dash-chart-wrap" id="wrap-fleet-status">
                <canvas id="chart-fleet-status"></canvas>
            </div>
        </div>
        <div class="aura-dash-chart-box aura-dash-chart-lg">
            <div class="aura-dash-chart-header">
                <span class="dashicons dashicons-chart-bar"></span>
                <h3><?php esc_html_e( 'KM por Vehículo (Top 10)', 'aura-suite' ); ?></h3>
            </div>
            <div class="aura-dash-chart-wrap" id="wrap-km-by-vehicle">
                <canvas id="chart-km-by-vehicle"></canvas>
            </div>
        </div>
    </div>

    <!-- ── Fila de gráficas 2: Uso por área + Actividad diaria ─ -->
    <div class="aura-dash-charts-row">
        <div class="aura-dash-chart-box">
            <div class="aura-dash-chart-header">
                <span class="dashicons dashicons-groups"></span>
                <h3><?php esc_html_e( 'Salidas por Área', 'aura-suite' ); ?></h3>
            </div>
            <div class="aura-dash-chart-wrap" id="wrap-usage-by-area">
                <canvas id="chart-usage-by-area"></canvas>
            </div>
        </div>
        <div class="aura-dash-chart-box">
            <div class="aura-dash-chart-header">
                <span class="dashicons dashicons-chart-line"></span>
                <h3><?php esc_html_e( 'Actividad Diaria (Salidas)', 'aura-suite' ); ?></h3>
            </div>
            <div class="aura-dash-chart-wrap" id="wrap-monthly-activity">
                <canvas id="chart-monthly-activity"></canvas>
            </div>
        </div>
    </div>

    <!-- ── Fila de gráficas 3: Costos vs Ingresos ────────────── -->
    <div class="aura-dash-charts-row">
        <div class="aura-dash-chart-box aura-dash-chart-full">
            <div class="aura-dash-chart-header">
                <span class="dashicons dashicons-analytics"></span>
                <h3><?php esc_html_e( 'Costos vs Ingresos (últimos 6 meses)', 'aura-suite' ); ?></h3>
            </div>
            <div class="aura-dash-chart-wrap aura-dash-chart-wrap-wide" id="wrap-cost-vs-income">
                <canvas id="chart-cost-vs-income"></canvas>
            </div>
        </div>
    </div>

</div><!-- #aura-veh-dashboard-page -->
