<?php
/**
 * Vista: Auditoría de Flota — Fase 7
 *
 * Muestra el log de operaciones con filtros (operación, usuario, fechas, IP),
 * paginación, opción de expandir detalles JSON, exportación CSV y
 * limpieza de logs antiguos.
 *
 * Solo accesible para usuarios con `manage_options`.
 *
 * @package Aura_Business_Suite\Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'aura_vehicles_audit' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permisos para ver la auditoría.', 'aura-suite' ) );
}

global $wpdb;

// Usuarios para el filtro (solo los que tienen registros en la tabla)
$audit_users = $wpdb->get_results(
    "SELECT DISTINCT u.ID, u.display_name
       FROM {$wpdb->prefix}aura_vehicle_audit a
       JOIN {$wpdb->prefix}users u ON u.ID = a.user_id
      ORDER BY u.display_name ASC
      LIMIT 200"
) ?: array();

// Operaciones agrupadas por categoría para el <optgroup>
$operation_groups = array(
    'Vehículos' => array(
        'vehicle_created'          => 'Vehículo creado',
        'vehicle_updated'          => 'Vehículo actualizado',
        'vehicle_deleted'          => 'Vehículo eliminado',
        'vehicle_area_assigned'    => 'Área asignada',
        'vehicle_area_unassigned'  => 'Área desasignada',
        'vehicle_marked_unavailable' => 'Marcado no disponible',
        'vehicle_restored'         => 'Vehículo restaurado',
        'vehicle_transferred'      => 'Vehículo transferido',
        'vehicle_photo_uploaded'   => 'Foto cargada',
        'vehicle_photo_deleted'    => 'Foto eliminada',
    ),
    'Salidas' => array(
        'trip_create'  => 'Salida registrada',
        'trip_checkin' => 'Check-in realizado',
        'trip_cancel'  => 'Salida cancelada',
        'trip_update'  => 'Salida actualizada',
        'trip_delete'  => 'Salida eliminada',
    ),
    'Catálogos' => array(
        'catalog_create' => 'Catálogo creado',
        'catalog_update' => 'Catálogo actualizado',
        'catalog_delete' => 'Catálogo eliminado',
        'catalog_reorder' => 'Catálogos reordenados',
    ),
    'Reportes' => array(
        'report_export' => 'Exportación de reporte',
    ),
    'Auditoría' => array(
        'audit_export_csv' => 'Exportación log CSV',
        'audit_cleanup'    => 'Limpieza de logs',
    ),
);

// Labels de operaciones (mapa plano para badges)
$op_labels = array();
foreach ( $operation_groups as $ops ) {
    foreach ( $ops as $key => $label ) {
        $op_labels[ $key ] = $label;
    }
}
?>
<div class="wrap aura-veh-audit-page" id="aura-veh-audit-app">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-list-view" style="font-size:24px;vertical-align:middle;"></span>
        <?php esc_html_e( 'Auditoría de Flota', 'aura-suite' ); ?>
    </h1>
    <hr class="wp-header-end">

    <!-- ── Filtros ─────────────────────────────────────────────── -->
    <div class="aura-audit-filters card" id="aura-audit-filters">
        <div class="aura-audit-filters-grid">

            <!-- Operación -->
            <div class="aura-audit-filter-group">
                <label for="aura-aud-op"><?php esc_html_e( 'Operación', 'aura-suite' ); ?></label>
                <select id="aura-aud-op">
                    <option value=""><?php esc_html_e( 'Todas', 'aura-suite' ); ?></option>
                    <?php foreach ( $operation_groups as $group => $ops ) : ?>
                    <optgroup label="<?php echo esc_attr( $group ); ?>">
                        <?php foreach ( $ops as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Usuario -->
            <div class="aura-audit-filter-group">
                <label for="aura-aud-user"><?php esc_html_e( 'Usuario', 'aura-suite' ); ?></label>
                <select id="aura-aud-user">
                    <option value="0"><?php esc_html_e( 'Todos', 'aura-suite' ); ?></option>
                    <?php foreach ( $audit_users as $u ) : ?>
                    <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $u->display_name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Desde -->
            <div class="aura-audit-filter-group">
                <label for="aura-aud-from"><?php esc_html_e( 'Desde', 'aura-suite' ); ?></label>
                <input type="date" id="aura-aud-from">
            </div>

            <!-- Hasta -->
            <div class="aura-audit-filter-group">
                <label for="aura-aud-to"><?php esc_html_e( 'Hasta', 'aura-suite' ); ?></label>
                <input type="date" id="aura-aud-to">
            </div>

            <!-- IP -->
            <div class="aura-audit-filter-group">
                <label for="aura-aud-ip"><?php esc_html_e( 'Dirección IP', 'aura-suite' ); ?></label>
                <input type="text" id="aura-aud-ip" placeholder="192.168.1.1">
            </div>

            <!-- Búsqueda libre -->
            <div class="aura-audit-filter-group">
                <label for="aura-aud-search"><?php esc_html_e( 'Búsqueda', 'aura-suite' ); ?></label>
                <input type="text" id="aura-aud-search" placeholder="<?php esc_attr_e( 'Buscar en operación / detalles…', 'aura-suite' ); ?>">
            </div>

        </div>

        <div class="aura-audit-actions">
            <button type="button" id="aura-aud-search-btn" class="button button-primary">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e( 'Buscar', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-aud-reset-btn" class="button">
                <?php esc_html_e( 'Limpiar filtros', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-aud-csv-btn" class="button">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e( 'Exportar CSV', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-aud-cleanup-btn" class="button aura-aud-btn-danger">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e( 'Limpiar logs antiguos…', 'aura-suite' ); ?>
            </button>
            <span class="spinner" id="aura-aud-spinner"></span>
        </div>
    </div><!-- /.aura-audit-filters -->

    <!-- ── Totalizador ─────────────────────────────────────────── -->
    <div class="aura-audit-summary" id="aura-aud-summary" style="display:none;">
        <span id="aura-aud-total-text"></span>
    </div>

    <!-- ── Tabla de registros ──────────────────────────────────── -->
    <div class="aura-audit-table-wrap">
        <table class="wp-list-table widefat fixed striped" id="aura-aud-table">
            <thead>
                <tr>
                    <th style="width:140px;"><?php esc_html_e( 'Fecha / Hora', 'aura-suite' ); ?></th>
                    <th style="width:180px;"><?php esc_html_e( 'Operación', 'aura-suite' ); ?></th>
                    <th style="width:140px;"><?php esc_html_e( 'Usuario', 'aura-suite' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Entidad', 'aura-suite' ); ?></th>
                    <th style="width:60px;"><?php esc_html_e( 'ID', 'aura-suite' ); ?></th>
                    <th style="width:110px;"><?php esc_html_e( 'IP', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Detalles', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody id="aura-aud-tbody">
                <tr>
                    <td colspan="7" style="text-align:center;padding:20px;color:#72777c;">
                        <?php esc_html_e( 'Aplica los filtros y haz clic en Buscar para cargar el log.', 'aura-suite' ); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ── Paginación ──────────────────────────────────────────── -->
    <div class="aura-audit-pagination" id="aura-aud-pagination" style="display:none;">
        <div class="aura-audit-pag-info" id="aura-aud-pag-info"></div>
        <div class="aura-audit-pag-btns">
            <button type="button" id="aura-aud-prev" class="button" disabled>&#8249; <?php esc_html_e( 'Anterior', 'aura-suite' ); ?></button>
            <span id="aura-aud-page-indicator"></span>
            <button type="button" id="aura-aud-next" class="button" disabled><?php esc_html_e( 'Siguiente', 'aura-suite' ); ?> &#8250;</button>
        </div>
    </div>

    <!-- ── Modal: detalles JSON ────────────────────────────────── -->
    <div id="aura-aud-detail-modal" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-aud-detail-title">
        <div class="aura-modal-container" style="max-width:620px;">
            <div class="aura-modal-header">
                <h2 id="aura-aud-detail-title"><?php esc_html_e( 'Detalles del registro', 'aura-suite' ); ?></h2>
                <button type="button" class="aura-modal-close" id="aura-aud-detail-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
            </div>
            <div class="aura-modal-body">
                <table class="widefat striped" id="aura-aud-detail-table">
                    <tbody></tbody>
                </table>
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-size:12px;color:#72777c;"><?php esc_html_e( 'JSON completo', 'aura-suite' ); ?></summary>
                    <pre id="aura-aud-detail-json" style="background:#f6f7f7;padding:10px;border-radius:3px;font-size:11px;overflow-x:auto;max-height:250px;margin-top:6px;"></pre>
                </details>
            </div>
        </div>
    </div>

    <!-- ── Modal: limpieza de logs ─────────────────────────────── -->
    <div id="aura-aud-cleanup-modal" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-aud-cleanup-title">
        <div class="aura-modal-container" style="max-width:420px;">
            <div class="aura-modal-header">
                <h2 id="aura-aud-cleanup-title"><?php esc_html_e( 'Limpiar logs antiguos', 'aura-suite' ); ?></h2>
                <button type="button" class="aura-modal-close" id="aura-aud-cleanup-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
            </div>
            <div class="aura-modal-body">
                <p><?php esc_html_e( 'Se eliminarán permanentemente todos los registros de auditoría anteriores al período indicado.', 'aura-suite' ); ?></p>
                <div class="aura-audit-filter-group" style="margin:14px 0;">
                    <label for="aura-aud-cleanup-days"><strong><?php esc_html_e( 'Eliminar registros anteriores a:', 'aura-suite' ); ?></strong></label>
                    <div style="display:flex;align-items:center;gap:8px;margin-top:6px;">
                        <input type="number" id="aura-aud-cleanup-days" value="90" min="7" max="3650" style="width:80px;">
                        <span><?php esc_html_e( 'días', 'aura-suite' ); ?></span>
                    </div>
                </div>
                <p class="aura-audit-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e( 'Esta acción no se puede deshacer.', 'aura-suite' ); ?>
                </p>
            </div>
            <div class="aura-modal-footer">
                <button type="button" id="aura-aud-cleanup-cancel" class="button"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
                <button type="button" id="aura-aud-cleanup-confirm" class="button button-danger">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e( 'Eliminar logs', 'aura-suite' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden inputs -->
    <input type="hidden" id="aura-aud-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
    <input type="hidden" id="aura-aud-rest-base" value="<?php echo esc_url( rest_url( 'aura/v1/vehicles/audit' ) ); ?>">
    <!-- Labels de operaciones para el JS -->
    <script type="application/json" id="aura-aud-op-labels">
    <?php echo wp_json_encode( $op_labels ); ?>
    </script>

</div><!-- /.wrap -->
