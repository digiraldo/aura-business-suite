<?php
/**
 * Página de Registro de Auditoría — Fase 5, Item 5.3
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$nonce = wp_create_nonce( 'aura_audit_nonce' );

// Usuarios para filtro
$users = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );

// Retención configurada
$retention = (int) get_option( 'aura_audit_log_retention_days', 365 );

// Labels de acciones
$action_labels = [
    'transaction_created'            => __( 'Transacción creada',          'aura-suite' ),
    'transaction_updated'            => __( 'Transacción editada',         'aura-suite' ),
    'transaction_deleted'            => __( 'Transacción eliminada',       'aura-suite' ),
    'transaction_restored'           => __( 'Transacción restaurada',      'aura-suite' ),
    'transaction_permanently_deleted'=> __( 'Eliminación permanente',      'aura-suite' ),
    'transaction_approved'           => __( 'Transacción aprobada',        'aura-suite' ),
    'transaction_rejected'           => __( 'Transacción rechazada',       'aura-suite' ),
    'category_created'               => __( 'Categoría creada',            'aura-suite' ),
    'category_updated'               => __( 'Categoría editada',           'aura-suite' ),
    'category_deleted'               => __( 'Categoría eliminada',         'aura-suite' ),
    'budget_created'                 => __( 'Presupuesto creado',          'aura-suite' ),
    'budget_updated'                 => __( 'Presupuesto editado',         'aura-suite' ),
    'budget_deleted'                 => __( 'Presupuesto eliminado',       'aura-suite' ),
    'budget_exceeded'                => __( 'Presupuesto excedido',        'aura-suite' ),
    'export_executed'                => __( 'Exportación ejecutada',       'aura-suite' ),
    'import_executed'                => __( 'Importación ejecutada',       'aura-suite' ),
    'settings_updated'               => __( 'Configuración actualizada',   'aura-suite' ),
];
?>
<div class="wrap aura-audit-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-shield-alt" style="margin-right:6px;"></span>
        <?php esc_html_e( 'Registro de Auditoría', 'aura-suite' ); ?>
    </h1>
    <p class="description">
        <?php esc_html_e( 'Trazabilidad completa de todas las acciones realizadas en el módulo financiero.', 'aura-suite' ); ?>
    </p>
    <hr class="wp-header-end">

    <!-- Alertas del sistema -->
    <div id="audit-alert-panel"></div>

    <div class="aura-audit-layout">

        <!-- Sidebar de filtros -->
        <aside class="aura-audit-sidebar">

            <div class="aura-audit-card">
                <h3><?php esc_html_e( 'Filtros', 'aura-suite' ); ?></h3>

                <label><?php esc_html_e( 'Usuario', 'aura-suite' ); ?></label>
                <select id="f-user">
                    <option value=""><?php esc_html_e( '— Todos —', 'aura-suite' ); ?></option>
                    <?php foreach ( $users as $u ) : ?>
                    <option value="<?php echo esc_attr( $u->ID ); ?>">
                        <?php echo esc_html( $u->display_name ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <label><?php esc_html_e( 'Acción', 'aura-suite' ); ?></label>
                <select id="f-action">
                    <option value=""><?php esc_html_e( '— Todas —', 'aura-suite' ); ?></option>
                    <?php foreach ( $action_labels as $k => $v ) : ?>
                    <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
                    <?php endforeach; ?>
                </select>

                <label><?php esc_html_e( 'Tipo de entidad', 'aura-suite' ); ?></label>
                <select id="f-entity">
                    <option value=""><?php esc_html_e( '— Todas —', 'aura-suite' ); ?></option>
                    <option value="transaction"><?php esc_html_e( 'Transacción', 'aura-suite' ); ?></option>
                    <option value="category"><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></option>
                    <option value="budget"><?php esc_html_e( 'Presupuesto', 'aura-suite' ); ?></option>
                    <option value="export"><?php esc_html_e( 'Exportación', 'aura-suite' ); ?></option>
                    <option value="import"><?php esc_html_e( 'Importación', 'aura-suite' ); ?></option>
                </select>

                <label><?php esc_html_e( 'Rango de fechas', 'aura-suite' ); ?></label>
                <div class="aura-date-range">
                    <input type="date" id="f-date-from" placeholder="<?php esc_attr_e( 'Desde', 'aura-suite' ); ?>">
                    <span>—</span>
                    <input type="date" id="f-date-to" placeholder="<?php esc_attr_e( 'Hasta', 'aura-suite' ); ?>">
                </div>

                <label><?php esc_html_e( 'IP', 'aura-suite' ); ?></label>
                <input type="text" id="f-ip" placeholder="192.168.">

                <div class="aura-filter-buttons" style="margin-top:12px;">
                    <button id="btn-apply-audit-filters" class="button button-primary">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e( 'Buscar', 'aura-suite' ); ?>
                    </button>
                    <button id="btn-clear-audit-filters" class="button">
                        <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

            <!-- Acciones admin -->
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <div class="aura-audit-card">
                <h3><?php esc_html_e( 'Administración de Logs', 'aura-suite' ); ?></h3>

                <button id="btn-export-audit" class="button button-secondary" style="width:100%;margin-bottom:8px;">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Exportar todos (CSV)', 'aura-suite' ); ?>
                </button>

                <div class="aura-audit-purge-row">
                    <label><?php esc_html_e( 'Eliminar logs más antiguos de:', 'aura-suite' ); ?></label>
                    <div style="display:flex;gap:6px;align-items:center;margin-top:4px;">
                        <input type="number" id="purge-days" value="<?php echo esc_attr( $retention ); ?>"
                               min="30" style="width:80px;">
                        <span><?php esc_html_e( 'días', 'aura-suite' ); ?></span>
                        <button id="btn-purge-logs" class="button" style="color:#c00;border-color:#c00;">
                            <?php esc_html_e( 'Purgar', 'aura-suite' ); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php printf( esc_html__( 'Retención configurada: %d días', 'aura-suite' ), $retention ); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </aside><!-- /.aura-audit-sidebar -->

        <!-- Panel de logs -->
        <section class="aura-audit-main">

            <!-- Stats rápidas -->
            <div id="audit-stats-bar" class="aura-audit-stats-bar" style="display:none;">
                <span><strong id="audit-total-count">0</strong> <?php esc_html_e( 'registros encontrados', 'aura-suite' ); ?></span>
            </div>

            <div id="audit-loading" style="display:none;text-align:center;padding:40px;">
                <span class="spinner is-active" style="float:none;width:40px;height:40px;"></span>
                <p><?php esc_html_e( 'Cargando registros…', 'aura-suite' ); ?></p>
            </div>

            <div id="audit-empty" style="display:none;text-align:center;padding:60px 20px;">
                <span class="dashicons dashicons-shield-alt" style="font-size:48px;color:#ccc;"></span>
                <p><?php esc_html_e( 'No se encontraron registros con los filtros aplicados.', 'aura-suite' ); ?></p>
            </div>

            <table id="audit-log-table" class="wp-list-table widefat fixed striped aura-audit-table" style="display:none;">
                <thead>
                    <tr>
                        <th style="width:14%"><?php esc_html_e( 'Fecha / Hora', 'aura-suite' ); ?></th>
                        <th style="width:14%"><?php esc_html_e( 'Usuario', 'aura-suite' ); ?></th>
                        <th style="width:22%"><?php esc_html_e( 'Acción', 'aura-suite' ); ?></th>
                        <th style="width:11%;text-align:center"><?php esc_html_e( 'Entidad', 'aura-suite' ); ?></th>
                        <th style="width:8%;text-align:center"><?php esc_html_e( 'ID', 'aura-suite' ); ?></th>
                        <th style="width:12%;text-align:center"><?php esc_html_e( 'IP', 'aura-suite' ); ?></th>
                        <th style="width:19%"><?php esc_html_e( 'Cambios', 'aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody id="audit-log-body"></tbody>
            </table>

            <!-- Paginación -->
            <div id="audit-pagination" style="display:none;margin-top:14px;text-align:center;"></div>

        </section><!-- /.aura-audit-main -->

    </div><!-- /.aura-audit-layout -->

</div><!-- /.wrap -->

<script>
var auraAuditConfig = {
    nonce:      '<?php echo esc_js( $nonce ); ?>',
    ajaxUrl:    '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
    isAdmin:    <?php echo current_user_can( 'manage_options' ) ? 'true' : 'false'; ?>,
    actionLabels: <?php echo wp_json_encode( $action_labels ); ?>,
    i18n: {
        page:           '<?php echo esc_js( __( 'Pág.', 'aura-suite' ) ); ?>',
        of:             '<?php echo esc_js( __( 'de', 'aura-suite' ) ); ?>',
        diff:           '<?php echo esc_js( __( 'Ver cambios', 'aura-suite' ) ); ?>',
        noChanges:      '<?php echo esc_js( __( 'Sin detalle', 'aura-suite' ) ); ?>',
        confirmPurge:   '<?php echo esc_js( __( '¿Eliminar los logs más antiguos de los días indicados? Esta acción no se puede deshacer.', 'aura-suite' ) ); ?>',
        purgeSuccess:   '<?php echo esc_js( __( 'Logs purgados correctamente.', 'aura-suite' ) ); ?>',
        exportOk:       '<?php echo esc_js( __( 'Exportación lista.', 'aura-suite' ) ); ?>',
        fields: {
            amount:         '<?php echo esc_js( __( 'Monto', 'aura-suite' ) ); ?>',
            category_id:    '<?php echo esc_js( __( 'Categoría ID', 'aura-suite' ) ); ?>',
            transaction_date:'<?php echo esc_js( __( 'Fecha', 'aura-suite' ) ); ?>',
            description:    '<?php echo esc_js( __( 'Descripción', 'aura-suite' ) ); ?>',
            payment_method: '<?php echo esc_js( __( 'Método de pago', 'aura-suite' ) ); ?>',
            status:         '<?php echo esc_js( __( 'Estado', 'aura-suite' ) ); ?>',
            tags:           '<?php echo esc_js( __( 'Etiquetas', 'aura-suite' ) ); ?>',
            notes:          '<?php echo esc_js( __( 'Notas', 'aura-suite' ) ); ?>',
        },
        entityLabels: {
            transaction: '<?php echo esc_js( __( 'Transacción', 'aura-suite' ) ); ?>',
            category:    '<?php echo esc_js( __( 'Categoría', 'aura-suite' ) ); ?>',
            budget:      '<?php echo esc_js( __( 'Presupuesto', 'aura-suite' ) ); ?>',
            export:      '<?php echo esc_js( __( 'Exportación', 'aura-suite' ) ); ?>',
            import:      '<?php echo esc_js( __( 'Importación', 'aura-suite' ) ); ?>',
        },
    }
};
</script>
