<?php
/**
 * Página de Gestión de Etiquetas (Fase 5, Item 5.2)
 *
 * Accesible en: /wp-admin/admin.php?page=aura-financial-tags
 * Optimizado visual y estructuralmente según directrices de prompt-maestro.md.
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nonce = wp_create_nonce( 'aura_tags_nonce' );
$all_tags_count = Aura_Financial_Tags::get_all_tags();
$total_unique_tags = count( $all_tags_count );
$total_tag_usages  = array_sum( $all_tags_count );
?>

<div class="wrap aura-tags-wrap aura-module-wrap">

    <!-- ── Hero Banner Superior ─────────────────────────────────────── -->
    <div class="aura-hero-header">
        <div class="aura-hero-content">
            <div class="aura-hero-icon-box">
                <span class="dashicons dashicons-tag"></span>
            </div>
            <div class="aura-hero-titles">
                <div class="aura-hero-subtitle-row">
                    <span class="aura-tag-badge">
                        <span class="dashicons dashicons-shield"></span>
                        <?php esc_html_e( 'Aura Finanzas', 'aura-suite' ); ?>
                    </span>
                    <span class="aura-tag-badge aura-tag-secondary">
                        <span class="dashicons dashicons-tag"></span>
                        <?php esc_html_e( 'Taxonomía Transversal', 'aura-suite' ); ?>
                    </span>
                </div>
                <h1 class="aura-hero-title"><?php esc_html_e( 'Gestión de Etiquetas', 'aura-suite' ); ?></h1>
                <p class="aura-hero-desc">
                    <?php esc_html_e( 'Administra, estandariza, renombra y fusiona las etiquetas asignadas a proyectos, sedes o campañas en los movimientos financieros.', 'aura-suite' ); ?>
                </p>
            </div>
        </div>

        <div class="aura-hero-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-search' ) ); ?>" class="aura-btn aura-btn-secondary" data-tooltip="<?php esc_attr_e( 'Filtrar y buscar transacciones por etiquetas', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-search"></span>
                <span><?php esc_html_e( 'Búsqueda Avanzada', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-transactions' ) ); ?>" class="aura-btn aura-btn-secondary" data-tooltip="<?php esc_attr_e( 'Ir al listado general de transacciones', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-list-view"></span>
                <span><?php esc_html_e( 'Transacciones', 'aura-suite' ); ?></span>
            </a>
        </div>
    </div>

    <!-- ── Mensajes Globales y Notificaciones ──────────────────────── -->
    <div id="aura-tags-notice" style="display:none;"></div>

    <!-- ── Tarjetas KPI de Resumen de Etiquetas ────────────────────── -->
    <div class="aura-kpi-grid" style="margin-bottom: 24px;">
        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #3b82f6;">
                <span class="dashicons dashicons-tag"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e( 'Etiquetas Creadas', 'aura-suite' ); ?></span>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Cantidad total de etiquetas únicas registradas en el sistema.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #2563eb;">
                    <span id="kpi-total-tags"><?php echo esc_html( number_format( $total_unique_tags ) ); ?></span>
                </div>
                <div class="stat-sub"><?php esc_html_e( 'Tags únicos en el catálogo', 'aura-suite' ); ?></div>
            </div>
        </div>

        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #10b981;">
                <span class="dashicons dashicons-networking"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e( 'Asignaciones Totales', 'aura-suite' ); ?></span>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Número total de veces que las etiquetas han sido vinculadas a transacciones.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #059669;">
                    <span id="kpi-total-usages"><?php echo esc_html( number_format( $total_tag_usages ) ); ?></span>
                </div>
                <div class="stat-sub"><?php esc_html_e( 'Usos en movimientos', 'aura-suite' ); ?></div>
            </div>
        </div>

        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #6366f1;">
                <span class="dashicons dashicons-admin-generic"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e( 'Operaciones Masivas', 'aura-suite' ); ?></span>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Herramientas disponibles para unificar y depurar taxonomías.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #4f46e5;">
                    3 <small style="font-size: 13px; font-weight:600; color:#64748b;"><?php esc_html_e( 'herramientas', 'aura-suite' ); ?></small>
                </div>
                <div class="stat-sub"><?php esc_html_e( 'Renombrar, Fusionar y Purgar', 'aura-suite' ); ?></div>
            </div>
        </div>
    </div>

    <!-- ── Layout de 2 Columnas (Nube y Operaciones) ───────────────── -->
    <div class="aura-tags-layout">

        <!-- Nube de Etiquetas -->
        <div class="aura-card aura-tags-card aura-tags-cloud-card">
            <div class="aura-card-header">
                <div class="aura-card-title-group">
                    <span class="dashicons dashicons-cloud" style="color: #3b82f6;"></span>
                    <h3><?php esc_html_e( 'Nube Interactiva de Etiquetas', 'aura-suite' ); ?></h3>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'El tamaño visual es proporcional a la frecuencia de uso. Haz clic en una para filtrar.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                </div>
            </div>
            <div class="aura-card-body">
                <div id="aura-tag-cloud" class="aura-tag-cloud">
                    <span class="spinner is-active" style="float:none;"></span>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas y Estandarización -->
        <div class="aura-card aura-tags-card aura-tags-actions-card">
            <div class="aura-card-header">
                <div class="aura-card-title-group">
                    <span class="dashicons dashicons-randomize" style="color: #6366f1;"></span>
                    <h3><?php esc_html_e( 'Operaciones y Estandarización', 'aura-suite' ); ?></h3>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Aplica cambios en cascada en todas las transacciones históricas.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                </div>
            </div>
            <div class="aura-card-body">

                <!-- Renombrar -->
                <div class="aura-tags-op-box">
                    <h4 class="aura-tags-op-title">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e( 'Renombrar Etiqueta', 'aura-suite' ); ?>
                    </h4>
                    <p class="aura-tags-op-desc"><?php esc_html_e( 'Actualiza el nombre de la etiqueta en todos los registros vinculados.', 'aura-suite' ); ?></p>
                    <div class="aura-tags-op-row">
                        <input type="text" id="rename-old" class="aura-input" placeholder="<?php esc_attr_e( 'Etiqueta actual...', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-arrow-right-alt aura-op-arrow"></span>
                        <input type="text" id="rename-new" class="aura-input" placeholder="<?php esc_attr_e( 'Nuevo nombre...', 'aura-suite' ); ?>">
                        <button type="button" id="btn-rename-tag" class="aura-btn aura-btn-primary">
                            <span class="dashicons dashicons-yes"></span>
                            <span><?php esc_html_e( 'Renombrar', 'aura-suite' ); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Fusionar -->
                <div class="aura-tags-op-box" style="margin-top: 20px;">
                    <h4 class="aura-tags-op-title">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Fusionar Etiquetas', 'aura-suite' ); ?>
                    </h4>
                    <p class="aura-tags-op-desc"><?php esc_html_e( 'La etiqueta origen será reemplazada por la destino sin duplicar valores.', 'aura-suite' ); ?></p>
                    <div class="aura-tags-op-row">
                        <input type="text" id="merge-source" class="aura-input" placeholder="<?php esc_attr_e( 'Origen (a eliminar)...', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-arrow-right-alt aura-op-arrow"></span>
                        <input type="text" id="merge-target" class="aura-input" placeholder="<?php esc_attr_e( 'Destino (a conservar)...', 'aura-suite' ); ?>">
                        <button type="button" id="btn-merge-tags" class="aura-btn aura-btn-secondary">
                            <span class="dashicons dashicons-randomize"></span>
                            <span><?php esc_html_e( 'Fusionar', 'aura-suite' ); ?></span>
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </div><!-- /.aura-tags-layout -->

    <!-- ── Directorio Completo de Etiquetas ────────────────────────── -->
    <div class="aura-card aura-tags-table-card" style="margin-top: 24px;">
        <div class="aura-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
            <div class="aura-card-title-group">
                <span class="dashicons dashicons-list-view" style="color: #10b981;"></span>
                <h3><?php esc_html_e( 'Catálogo de Etiquetas Registradas', 'aura-suite' ); ?></h3>
                <span class="aura-badge aura-badge-blue" id="table-tags-counter">
                    <?php printf( esc_html__( '%d etiquetas', 'aura-suite' ), $total_unique_tags ); ?>
                </span>
            </div>
            <div class="aura-table-search-box">
                <span class="dashicons dashicons-search search-icon"></span>
                <input type="search" id="tags-table-filter" class="aura-input" placeholder="<?php esc_attr_e( 'Buscar etiqueta en tiempo real...', 'aura-suite' ); ?>" style="padding-left: 32px; width: 260px;">
            </div>
        </div>

        <div class="aura-card-body" style="padding: 0;">
            <div class="aura-table-responsive-wrap">
                <table id="aura-tags-table" class="aura-modern-table aura-tags-table">
                    <thead>
                        <tr>
                            <th style="width: 55px; text-align: center;">
                                <span>#</span>
                            </th>
                            <th style="width: 22%;">
                                <span><?php esc_html_e( 'Etiqueta', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php echo esc_attr( '<div class="aura-tip-card"><div class="aura-tip-card-header"><div class="aura-tip-avatar-large" style="background:rgba(59,130,246,0.2);color:#3b82f6;"><span class="dashicons dashicons-tag"></span></div><div class="aura-tip-info"><div class="aura-tip-title">' . __( 'Identificador de Etiqueta', 'aura-suite' ) . '</div><div class="aura-tip-subtitle">' . __( 'Taxonomía Transversal', 'aura-suite' ) . '</div></div></div><div class="aura-tip-card-body"><div class="aura-tip-row"><span>' . __( 'Descripción:', 'aura-suite' ) . '</span><strong>' . __( 'Clave única usada para agrupar movimientos de proyectos, sedes o campañas.', 'aura-suite' ) . '</strong></div></div></div>' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th style="width: 15%; text-align: center;">
                                <span><?php esc_html_e( 'Usos / Movimientos', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php echo esc_attr( '<div class="aura-tip-card"><div class="aura-tip-card-header"><div class="aura-tip-avatar-large" style="background:rgba(16,185,129,0.2);color:#10b981;"><span class="dashicons dashicons-networking"></span></div><div class="aura-tip-info"><div class="aura-tip-title">' . __( 'Frecuencia de Uso', 'aura-suite' ) . '</div><div class="aura-tip-subtitle">' . __( 'Auditoría de Movimientos', 'aura-suite' ) . '</div></div></div><div class="aura-tip-card-body"><div class="aura-tip-row"><span>' . __( 'Desglose:', 'aura-suite' ) . '</span><strong>' . __( 'Total de transacciones vinculadas, indicando ingresos (🟢) y egresos (🔴).', 'aura-suite' ) . '</strong></div></div></div>' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th style="width: 16%; text-align: right;">
                                <span><?php esc_html_e( 'Volumen Total', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php echo esc_attr( '<div class="aura-tip-card"><div class="aura-tip-card-header"><div class="aura-tip-avatar-large" style="background:rgba(99,102,241,0.2);color:#6366f1;"><span class="dashicons dashicons-money-alt"></span></div><div class="aura-tip-info"><div class="aura-tip-title">' . __( 'Volumen Financiero', 'aura-suite' ) . '</div><div class="aura-tip-subtitle">' . __( 'Movimiento Acumulado', 'aura-suite' ) . '</div></div></div><div class="aura-tip-card-body"><div class="aura-tip-row"><span>' . __( 'Cálculo:', 'aura-suite' ) . '</span><strong>' . __( 'Suma absoluta de todas las transacciones vinculadas (Ingresos + Egresos).', 'aura-suite' ) . '</strong></div></div></div>' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th style="width: 16%; text-align: right;">
                                <span><?php esc_html_e( 'Balance Neto', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php echo esc_attr( '<div class="aura-tip-card"><div class="aura-tip-card-header"><div class="aura-tip-avatar-large" style="background:rgba(37,99,235,0.2);color:#2563eb;"><span class="dashicons dashicons-chart-pie"></span></div><div class="aura-tip-info"><div class="aura-tip-title">' . __( 'Balance Contable Neto', 'aura-suite' ) . '</div><div class="aura-tip-subtitle">' . __( 'Margen Resultante', 'aura-suite' ) . '</div></div></div><div class="aura-tip-card-body"><div class="aura-tip-row"><span>' . __( 'Fórmula:', 'aura-suite' ) . '</span><strong>' . __( 'Total Ingresos (+) menos Total Egresos (-).', 'aura-suite' ) . '</strong></div></div></div>' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th style="width: 14%; text-align: center;" class="aura-col-tablet-hidden">
                                <span><?php esc_html_e( 'Último Uso', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php echo esc_attr( '<div class="aura-tip-card"><div class="aura-tip-card-header"><div class="aura-tip-avatar-large" style="background:rgba(148,163,184,0.2);color:#64748b;"><span class="dashicons dashicons-calendar-alt"></span></div><div class="aura-tip-info"><div class="aura-tip-title">' . __( 'Fecha de Actividad', 'aura-suite' ) . '</div><div class="aura-tip-subtitle">' . __( 'Trazabilidad Temporal', 'aura-suite' ) . '</div></div></div><div class="aura-tip-card-body"><div class="aura-tip-row"><span>' . __( 'Información:', 'aura-suite' ) . '</span><strong>' . __( 'Fecha de la última transacción contable registrada con esta etiqueta.', 'aura-suite' ) . '</strong></div></div></div>' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th style="width: 120px; text-align: right;">
                                <span><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php echo esc_attr( '<div class="aura-tip-card"><div class="aura-tip-card-header"><div class="aura-tip-avatar-large" style="background:rgba(30,41,59,0.5);color:#cbd5e1;"><span class="dashicons dashicons-admin-generic"></span></div><div class="aura-tip-info"><div class="aura-tip-title">' . __( 'Herramientas de Etiqueta', 'aura-suite' ) . '</div><div class="aura-tip-subtitle">' . __( 'Operaciones Disponibles', 'aura-suite' ) . '</div></div></div><div class="aura-tip-card-body"><div class="aura-tip-row"><span>' . __( 'Operaciones:', 'aura-suite' ) . '</span><strong>' . __( 'Renombrar, Buscar Transacciones o Eliminar en cascada.', 'aura-suite' ) . '</strong></div></div></div>' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tags-table-body">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 36px 20px;">
                                <span class="spinner is-active" style="float:none; margin:0 auto 10px auto;"></span>
                                <p style="color:#64748b; margin:0; font-size:13px;"><?php esc_html_e( 'Cargando catálogo de etiquetas...', 'aura-suite' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Modal Universal de Confirmación de Eliminación ──────────── -->
    <div id="aura-delete-tag-modal" class="aura-modal-overlay" style="display:none;">
        <div class="aura-modal-content" style="max-width: 480px;">
            <div class="aura-modal-header" style="background:#dc2626;">
                <h2>
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e( 'Confirmar Eliminación de Etiqueta', 'aura-suite' ); ?>
                </h2>
                <button type="button" class="aura-modal-close" id="btn-close-delete-modal" title="<?php esc_attr_e( 'Cerrar modal', 'aura-suite' ); ?>">&times;</button>
            </div>
            <div class="aura-modal-body" style="padding: 24px;">
                <p style="margin: 0 0 12px 0; font-size: 14px; color: #1e293b; line-height: 1.5;">
                    <?php esc_html_e( '¿Estás seguro de que deseas eliminar permanentemente la etiqueta', 'aura-suite' ); ?>
                    <strong id="delete-tag-name" style="color:#dc2626; font-size: 15px;"></strong>?
                </p>
                <div style="background:#fee2e2; border-left:4px solid #ef4444; padding:12px 14px; border-radius:6px; font-size:12.5px; color:#991b1b;">
                    <span class="dashicons dashicons-info" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;"></span>
                    <?php esc_html_e( 'Esta acción desvinculará la etiqueta de todas las transacciones históricas en la base de datos y no se puede deshacer.', 'aura-suite' ); ?>
                </div>
            </div>
            <div class="aura-modal-footer">
                <button type="button" id="cancel-delete-tag" class="aura-ud-btn aura-ud-btn--secondary">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
                <button type="button" id="confirm-delete-tag" class="aura-btn aura-btn-danger" style="background:#dc2626; color:#ffffff; border-color:#b91c1c;">
                    <span class="dashicons dashicons-trash"></span>
                    <span><?php esc_html_e( 'Sí, Eliminar de Todo', 'aura-suite' ); ?></span>
                </button>
            </div>
        </div>
    </div>

</div><!-- /.wrap -->

<script>
var auraTagsConfig = {
    nonce:       '<?php echo esc_js( $nonce ); ?>',
    ajaxUrl:     '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
    searchUrl:   '<?php echo esc_js( admin_url( 'admin.php?page=aura-financial-search' ) ); ?>',
    i18n: {
        confirmMerge:    '<?php echo esc_js( __( '¿Seguro que deseas fusionar estas etiquetas? Esta acción actualizará todas las transacciones vinculadas.', 'aura-suite' ) ); ?>',
        noResults:       '<?php echo esc_js( __( 'No se encontraron etiquetas registradas en el sistema.', 'aura-suite' ) ); ?>',
        deleteSuccess:   '<?php echo esc_js( __( 'Etiqueta eliminada correctamente de todas las transacciones.', 'aura-suite' ) ); ?>',
        renameSuccess:   '<?php echo esc_js( __( 'Etiqueta renombrada con éxito.', 'aura-suite' ) ); ?>',
        mergeSuccess:    '<?php echo esc_js( __( 'Etiquetas fusionadas con éxito.', 'aura-suite' ) ); ?>'
    }
};
</script>
