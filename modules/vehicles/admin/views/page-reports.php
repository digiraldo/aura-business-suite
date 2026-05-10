<?php
/**
 * Vista: Reportes Flota — Fase 6
 *
 * Selector de tipo de reporte, panel de filtros, botones de exportación
 * y zona de previsualización con DataTables.
 *
 * @package Aura_Business_Suite\Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'aura_vehicles_reports' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permisos para acceder a los reportes.', 'aura-suite' ) );
}

global $wpdb;

$can_view_all = current_user_can( 'aura_vehicles_view_all' ) || current_user_can( 'manage_options' );

// Áreas disponibles para el filtro
$areas = array();
if ( $can_view_all ) {
    $areas = $wpdb->get_results(
        "SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE active = 1 ORDER BY name ASC"
    ) ?: array();
}

// Vehículos activos para el filtro
$vehicles = $wpdb->get_results(
    "SELECT id, CONCAT(brand, ' ', model, ' (', plate, ')') AS label
       FROM {$wpdb->prefix}aura_vehicles
      WHERE active = 1
      ORDER BY brand, model ASC"
) ?: array();

$report_types = array(
    'trips'        => array( 'label' => __( 'Salidas', 'aura-suite' ),         'icon' => 'dashicons-car' ),
    'maintenances' => array( 'label' => __( 'Mantenimientos', 'aura-suite' ),  'icon' => 'dashicons-admin-tools' ),
    'costs'        => array( 'label' => __( 'Costos', 'aura-suite' ),          'icon' => 'dashicons-money-alt' ),
    'vehicles'     => array( 'label' => __( 'Flota', 'aura-suite' ),           'icon' => 'dashicons-database' ),
    'mileage'      => array( 'label' => __( 'Kilometraje', 'aura-suite' ),     'icon' => 'dashicons-chart-bar' ),
);
?>
<div class="wrap aura-veh-reports-page" id="aura-veh-reports-app">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-chart-line" style="font-size:24px;vertical-align:middle;"></span>
        <?php esc_html_e( 'Reportes de Flota', 'aura-suite' ); ?>
    </h1>
    <hr class="wp-header-end" />

    <!-- ── Selector de tipo de reporte ────────────────────────────── -->
    <div class="aura-rep-type-bar" role="tablist" aria-label="<?php esc_attr_e( 'Tipos de reporte', 'aura-suite' ); ?>">
        <?php foreach ( $report_types as $key => $info ) : ?>
        <button
            type="button"
            class="aura-rep-type-btn<?php echo 'trips' === $key ? ' is-active' : ''; ?>"
            data-type="<?php echo esc_attr( $key ); ?>"
            role="tab"
            aria-selected="<?php echo 'trips' === $key ? 'true' : 'false'; ?>"
        >
            <span class="dashicons <?php echo esc_attr( $info['icon'] ); ?>"></span>
            <?php echo esc_html( $info['label'] ); ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ── Panel de filtros ─────────────────────────────────────────── -->
    <div class="aura-rep-filters-panel card">
        <div class="aura-rep-filters-grid">

            <!-- Período -->
            <div class="aura-rep-filter-group">
                <label for="aura-rep-period"><?php esc_html_e( 'Período', 'aura-suite' ); ?></label>
                <select id="aura-rep-period" name="period">
                    <option value="7d"><?php esc_html_e( 'Últimos 7 días', 'aura-suite' ); ?></option>
                    <option value="30d" selected><?php esc_html_e( 'Últimos 30 días', 'aura-suite' ); ?></option>
                    <option value="90d"><?php esc_html_e( 'Últimos 90 días', 'aura-suite' ); ?></option>
                    <option value="year"><?php esc_html_e( 'Este año', 'aura-suite' ); ?></option>
                    <option value="custom"><?php esc_html_e( 'Personalizado…', 'aura-suite' ); ?></option>
                </select>
            </div>

            <!-- Rango personalizado (oculto until period=custom) -->
            <div class="aura-rep-filter-group aura-rep-custom-dates" id="aura-rep-custom-dates" style="display:none;">
                <label for="aura-rep-date-from"><?php esc_html_e( 'Desde', 'aura-suite' ); ?></label>
                <input type="date" id="aura-rep-date-from" name="date_from" />
            </div>
            <div class="aura-rep-filter-group aura-rep-custom-dates" id="aura-rep-custom-dates-to" style="display:none;">
                <label for="aura-rep-date-to"><?php esc_html_e( 'Hasta', 'aura-suite' ); ?></label>
                <input type="date" id="aura-rep-date-to" name="date_to" />
            </div>

            <?php if ( $can_view_all ) : ?>
            <!-- Área (solo si tiene view_all) -->
            <div class="aura-rep-filter-group">
                <label for="aura-rep-area"><?php esc_html_e( 'Área', 'aura-suite' ); ?></label>
                <select id="aura-rep-area" name="area_id">
                    <option value="0"><?php esc_html_e( 'Todas las áreas', 'aura-suite' ); ?></option>
                    <?php foreach ( $areas as $area ) : ?>
                    <option value="<?php echo esc_attr( $area->id ); ?>">
                        <?php echo esc_html( $area->name ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Vehículo -->
            <div class="aura-rep-filter-group">
                <label for="aura-rep-vehicle"><?php esc_html_e( 'Vehículo', 'aura-suite' ); ?></label>
                <select id="aura-rep-vehicle" name="vehicle_id">
                    <option value="0"><?php esc_html_e( 'Todos los vehículos', 'aura-suite' ); ?></option>
                    <?php foreach ( $vehicles as $veh ) : ?>
                    <option value="<?php echo esc_attr( $veh->id ); ?>">
                        <?php echo esc_html( $veh->label ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tipo de salida (solo visible para trips) -->
            <div class="aura-rep-filter-group" id="aura-rep-trip-type-wrap">
                <label for="aura-rep-trip-type"><?php esc_html_e( 'Tipo de salida', 'aura-suite' ); ?></label>
                <select id="aura-rep-trip-type" name="trip_type">
                    <option value=""><?php esc_html_e( 'Todos', 'aura-suite' ); ?></option>
                    <option value="rental"><?php esc_html_e( 'Alquiler', 'aura-suite' ); ?></option>
                    <option value="errand"><?php esc_html_e( 'Encargo', 'aura-suite' ); ?></option>
                    <option value="maintenance"><?php esc_html_e( 'Mantenimiento', 'aura-suite' ); ?></option>
                    <option value="other"><?php esc_html_e( 'Otro', 'aura-suite' ); ?></option>
                </select>
            </div>

        </div><!-- /.aura-rep-filters-grid -->

        <!-- ── Botones de acción ────────────────────────────────── -->
        <div class="aura-rep-actions">
            <button type="button" id="aura-rep-preview-btn" class="button button-primary">
                <span class="dashicons dashicons-visibility" style="vertical-align:middle;"></span>
                <?php esc_html_e( 'Vista previa', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-rep-csv-btn" class="button" disabled>
                <span class="dashicons dashicons-download" style="vertical-align:middle;"></span>
                <?php esc_html_e( 'Descargar CSV', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-rep-pdf-btn" class="button" disabled>
                <span class="dashicons dashicons-pdf" style="vertical-align:middle;"></span>
                <?php esc_html_e( 'Descargar PDF', 'aura-suite' ); ?>
            </button>
            <span class="aura-rep-spinner spinner" id="aura-rep-spinner"></span>
        </div>
    </div><!-- /.aura-rep-filters-panel -->

    <!-- ── Notificaciones ───────────────────────────────────────── -->
    <div id="aura-rep-notice" class="aura-rep-notice" style="display:none;" role="alert" aria-live="polite"></div>

    <!-- ── Totales ─────────────────────────────────────────────── -->
    <div id="aura-rep-totals" class="aura-rep-totals-bar" style="display:none;"></div>

    <!-- ── Previsualización ─────────────────────────────────────── -->
    <div id="aura-rep-preview" class="aura-rep-preview-container" style="display:none;">
        <div class="aura-rep-preview-header">
            <h2 id="aura-rep-preview-title" class="aura-rep-preview-title"></h2>
            <span id="aura-rep-row-count" class="aura-rep-row-count"></span>
        </div>
        <div class="aura-rep-table-wrap">
            <table id="aura-rep-table" class="wp-list-table widefat fixed striped aura-datatable">
                <thead><tr id="aura-rep-thead"></tr></thead>
                <tbody id="aura-rep-tbody"></tbody>
                <tfoot><tr id="aura-rep-tfoot"></tr></tfoot>
            </table>
        </div>
        <p class="aura-rep-no-data" id="aura-rep-no-data" style="display:none;">
            <?php esc_html_e( 'No se encontraron registros para los filtros aplicados.', 'aura-suite' ); ?>
        </p>
    </div><!-- /#aura-rep-preview -->

    <!-- Nonce oculto para la REST API -->
    <input type="hidden" id="aura-rep-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
    <input type="hidden" id="aura-rep-rest-url" value="<?php echo esc_url( rest_url( 'aura/v1/vehicles/reports' ) ); ?>">

</div><!-- /.wrap -->
