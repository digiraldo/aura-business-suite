<?php
/**
 * Página de Gestión de Etiquetas
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$nonce = wp_create_nonce( 'aura_tags_nonce' );
?>
<div class="wrap aura-tags-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-tag" style="margin-right:6px;"></span>
        <?php esc_html_e( 'Gestión de Etiquetas', 'aura-suite' ); ?>
    </h1>
    <p class="description">
        <?php esc_html_e( 'Administra las etiquetas usadas en transacciones. Haz clic en una para filtrar, renombra, fusiona o elimina.', 'aura-suite' ); ?>
    </p>
    <hr class="wp-header-end">

    <!-- Mensajes -->
    <div id="aura-tags-notice" style="display:none;"></div>

    <div class="aura-tags-layout">

        <!-- Nube de tags -->
        <div class="aura-tags-card aura-tags-cloud-card">
            <h2><?php esc_html_e( 'Nube de Etiquetas', 'aura-suite' ); ?></h2>
            <div id="aura-tag-cloud" class="aura-tag-cloud">
                <span class="spinner is-active" style="float:none;"></span>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="aura-tags-card aura-tags-actions-card">
            <h2><?php esc_html_e( 'Operaciones', 'aura-suite' ); ?></h2>

            <!-- Renombrar -->
            <div class="aura-tags-op">
                <h3><?php esc_html_e( 'Renombrar Etiqueta', 'aura-suite' ); ?></h3>
                <div class="aura-tags-op-row">
                    <input type="text" id="rename-old" placeholder="<?php esc_attr_e( 'Etiqueta actual', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-arrow-right-alt" style="line-height:30px;"></span>
                    <input type="text" id="rename-new" placeholder="<?php esc_attr_e( 'Nuevo nombre', 'aura-suite' ); ?>">
                    <button id="btn-rename-tag" class="button button-primary">
                        <?php esc_html_e( 'Renombrar', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

            <!-- Fusionar -->
            <div class="aura-tags-op">
                <h3><?php esc_html_e( 'Fusionar Etiquetas', 'aura-suite' ); ?></h3>
                <p class="description"><?php esc_html_e( 'La etiqueta origen será reemplazada por la destino en todas las transacciones.', 'aura-suite' ); ?></p>
                <div class="aura-tags-op-row">
                    <input type="text" id="merge-source" placeholder="<?php esc_attr_e( 'Origen', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-arrow-right-alt" style="line-height:30px;"></span>
                    <input type="text" id="merge-target" placeholder="<?php esc_attr_e( 'Destino', 'aura-suite' ); ?>">
                    <button id="btn-merge-tags" class="button button-secondary">
                        <?php esc_html_e( 'Fusionar', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>
        </div>

    </div><!-- /.aura-tags-layout -->

    <!-- Tabla de etiquetas -->
    <div class="aura-tags-card" style="margin-top:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <h2 style="margin:0;"><?php esc_html_e( 'Todas las Etiquetas', 'aura-suite' ); ?></h2>
            <div>
                <input type="search" id="tags-table-filter" placeholder="<?php esc_attr_e( 'Filtrar...', 'aura-suite' ); ?>" style="width:220px;">
            </div>
        </div>
        <table id="aura-tags-table" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:55%"><?php esc_html_e( 'Etiqueta', 'aura-suite' ); ?></th>
                    <th style="width:15%;text-align:center;"><?php esc_html_e( 'Usos', 'aura-suite' ); ?></th>
                    <th style="width:30%;text-align:right;"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody id="tags-table-body">
                <tr><td colspan="3" style="text-align:center;">
                    <span class="spinner is-active" style="float:none;"></span>
                </td></tr>
            </tbody>
        </table>
    </div>

    <!-- Modal confirmar eliminación -->
    <div id="aura-delete-tag-modal" style="display:none;"
         class="aura-modal-backdrop">
        <div class="aura-modal-box">
            <h3><?php esc_html_e( 'Confirmar eliminación', 'aura-suite' ); ?></h3>
            <p><?php esc_html_e( 'La etiqueta', 'aura-suite' ); ?> <strong id="delete-tag-name"></strong>
               <?php esc_html_e( 'será eliminada de todas las transacciones. Esta acción no se puede deshacer.', 'aura-suite' ); ?>
            </p>
            <div style="text-align:right;margin-top:16px;">
                <button id="confirm-delete-tag" class="button button-primary"><?php esc_html_e( 'Sí, eliminar', 'aura-suite' ); ?></button>
                <button id="cancel-delete-tag" class="button"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            </div>
        </div>
    </div>

</div><!-- /.wrap -->

<script>
var auraTagsConfig = {
    nonce:   '<?php echo esc_js( $nonce ); ?>',
    ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
    i18n: {
        confirmMerge:    '<?php echo esc_js( __( '¿Seguro que deseas fusionar estas etiquetas?', 'aura-suite' ) ); ?>',
        noResults:       '<?php echo esc_js( __( 'No hay etiquetas aún.', 'aura-suite' ) ); ?>',
        deleteSuccess:   '<?php echo esc_js( __( 'Etiqueta eliminada.', 'aura-suite' ) ); ?>',
    }
};
</script>
