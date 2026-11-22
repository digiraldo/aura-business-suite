<?php
/**
 * Vista: Catálogos — Fase 4
 * Gestión de destinos, propósitos y gastos configurables de la flota.
 *
 * @package Aura_Business_Suite\Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'aura_vehicles_edit' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permisos para acceder a los catálogos.', 'aura-suite' ) );
}

$can_manage = current_user_can( 'aura_vehicles_edit' ) || current_user_can( 'manage_options' );

// Áreas activas para el selector en el modal
global $wpdb;
$areas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY name ASC"
) ?: array();

// Iconos dashicons disponibles para catálogos
$available_icons = array(
    // Ubicación / destino
    'dashicons-location'        => __( 'Ubicación', 'aura-suite' ),
    'dashicons-location-alt'    => __( 'Marcador', 'aura-suite' ),
    'dashicons-admin-home'      => __( 'Casa', 'aura-suite' ),
    'dashicons-building'        => __( 'Edificio', 'aura-suite' ),
    'dashicons-store'           => __( 'Tienda', 'aura-suite' ),
    'dashicons-airplane'        => __( 'Avión', 'aura-suite' ),
    // Transporte
    'dashicons-car'             => __( 'Auto', 'aura-suite' ),
    'dashicons-carrot'          => __( 'Ruta', 'aura-suite' ),
    'dashicons-cart'            => __( 'Carga', 'aura-suite' ),
    // Trabajo / propósito
    'dashicons-businessman'     => __( 'Persona', 'aura-suite' ),
    'dashicons-groups'          => __( 'Grupo', 'aura-suite' ),
    'dashicons-clipboard'       => __( 'Portapapeles', 'aura-suite' ),
    'dashicons-media-document'  => __( 'Documento', 'aura-suite' ),
    'dashicons-hammer'          => __( 'Herramienta', 'aura-suite' ),
    'dashicons-admin-tools'     => __( 'Herramientas', 'aura-suite' ),
    'dashicons-chart-bar'       => __( 'Gráfico', 'aura-suite' ),
    'dashicons-megaphone'       => __( 'Anuncio', 'aura-suite' ),
    'dashicons-calendar-alt'    => __( 'Calendario', 'aura-suite' ),
    // Finanzas / gastos
    'dashicons-money-alt'       => __( 'Dinero', 'aura-suite' ),
    'dashicons-tag'             => __( 'Etiqueta', 'aura-suite' ),
    'dashicons-tickets-alt'     => __( 'Ticket', 'aura-suite' ),
    'dashicons-admin-generic'   => __( 'Genérico', 'aura-suite' ),
    // Mantenimiento
    'dashicons-admin-settings'  => __( 'Configuración', 'aura-suite' ),
    'dashicons-update'          => __( 'Actualizar', 'aura-suite' ),
    'dashicons-welcome-widgets-menus' => __( 'Lista', 'aura-suite' ),
    // Otros
    'dashicons-star-filled'     => __( 'Estrella', 'aura-suite' ),
    'dashicons-info'            => __( 'Info', 'aura-suite' ),
    'dashicons-marker'          => __( 'Marcador 2', 'aura-suite' ),
    'dashicons-plus-alt'        => __( 'Plus', 'aura-suite' ),
);

$tabs = array(
    'destination' => array( 'label' => __( 'Destinos', 'aura-suite' ),    'icon' => 'dashicons-location',  'singular' => __( 'Destino', 'aura-suite' ) ),
    'purpose'     => array( 'label' => __( 'Propósitos', 'aura-suite' ),  'icon' => 'dashicons-clipboard',  'singular' => __( 'Propósito', 'aura-suite' ) ),
    'expense'     => array( 'label' => __( 'Gastos', 'aura-suite' ),      'icon' => 'dashicons-money-alt',  'singular' => __( 'Gasto', 'aura-suite' ) ),
);
?>

<div class="wrap aura-vehicles-catalogs" id="aura-catalogs-page">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-category" style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
        <?php esc_html_e( 'Catálogos', 'aura-suite' ); ?>
    </h1>

    <hr class="wp-header-end">

    <!-- ── Aviso de notificaciones ────────────────────────── -->
    <div id="aura-cat-notice" class="notice" style="display:none;margin:12px 0;">
        <p class="aura-cat-notice-text"></p>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         PESTAÑAS
    ═══════════════════════════════════════════════════════ -->
    <nav class="aura-cat-tabs">
        <?php foreach ( $tabs as $type => $tab ) : ?>
        <button
            type="button"
            class="aura-cat-tab<?php echo 'destination' === $type ? ' is-active' : ''; ?>"
            data-type="<?php echo esc_attr( $type ); ?>"
        >
            <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
            <?php echo esc_html( $tab['label'] ); ?>
        </button>
        <?php endforeach; ?>
    </nav>

    <!-- ═══════════════════════════════════════════════════════
         PANELES
    ═══════════════════════════════════════════════════════ -->
    <?php foreach ( $tabs as $type => $tab ) : ?>
    <div
        id="aura-cat-panel-<?php echo esc_attr( $type ); ?>"
        class="aura-cat-panel"
        <?php echo 'destination' !== $type ? 'style="display:none"' : ''; ?>
    >
        <div class="aura-cat-panel-header">
            <p class="aura-cat-panel-desc">
                <?php
                $descriptions = array(
                    'destination' => __( 'Lista de destinos disponibles para registrar en salidas de tipo Encargo. Las áreas pueden tener sus propios destinos adicionales.', 'aura-suite' ),
                    'purpose'     => __( 'Propósitos o motivos de viaje para salidas de tipo Encargo. Se ven en el formulario de nueva salida.', 'aura-suite' ),
                    'expense'     => __( 'Tipos de gastos que pueden registrarse al hacer el check-in de una salida. El total se suma automáticamente.', 'aura-suite' ),
                );
                echo esc_html( $descriptions[ $type ] );
                ?>
            </p>
            <?php if ( $can_manage ) : ?>
            <button type="button" class="button button-primary aura-cat-btn-new" data-type="<?php echo esc_attr( $type ); ?>">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php echo esc_html( sprintf( __( 'Nuevo %s', 'aura-suite' ), $tab['singular'] ) ); ?>
            </button>
            <?php endif; ?>
        </div>

        <table class="aura-cat-table wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <?php if ( $can_manage ) : ?>
                    <th class="aura-cat-th-handle" title="<?php esc_attr_e( 'Arrastrar para reordenar', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-menu"></span>
                    </th>
                    <?php endif; ?>
                    <th class="aura-cat-th-icon"><?php esc_html_e( 'Icono', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Nombre / Descripción', 'aura-suite' ); ?></th>
                    <th class="aura-cat-th-area"><?php esc_html_e( 'Ámbito', 'aura-suite' ); ?></th>
                    <th class="aura-cat-th-status"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                    <?php if ( $can_manage ) : ?>
                    <th class="aura-cat-th-actions"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="aura-cat-tbody-<?php echo esc_attr( $type ); ?>">
                <tr>
                    <td colspan="6" class="aura-cat-empty">
                        <span class="spinner is-active" style="float:none;vertical-align:middle;"></span>
                        <?php esc_html_e( 'Cargando…', 'aura-suite' ); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

</div><!-- .wrap -->


<!-- ═══════════════════════════════════════════════════════════
     MODAL CREAR / EDITAR
═══════════════════════════════════════════════════════════ -->
<div id="aura-cat-modal" class="aura-veh-modal" style="display:none;">
    <div id="aura-cat-modal-overlay" class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content" role="dialog" aria-modal="true" aria-labelledby="aura-cat-modal-title">

        <div class="aura-veh-modal-header">
            <h2 id="aura-cat-modal-title"><?php esc_html_e( 'Nuevo Destino', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close aura-cat-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <form id="aura-cat-form" autocomplete="off">
            <input type="hidden" id="aura-cat-field-type" name="type">

            <div class="aura-veh-modal-body">

                <!-- Nombre -->
                <div class="aura-veh-form-field">
                    <label for="aura-cat-name">
                        <?php esc_html_e( 'Nombre', 'aura-suite' ); ?> <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="aura-cat-name"
                        name="name"
                        class="regular-text"
                        maxlength="150"
                        required
                        placeholder="<?php esc_attr_e( 'Ej. Aeropuerto Internacional', 'aura-suite' ); ?>"
                    >
                </div>

                <!-- Descripción -->
                <div class="aura-veh-form-field">
                    <label for="aura-cat-description"><?php esc_html_e( 'Descripción', 'aura-suite' ); ?>
                        <span class="aura-veh-field-hint"><?php esc_html_e( '(opcional)', 'aura-suite' ); ?></span>
                    </label>
                    <input
                        type="text"
                        id="aura-cat-description"
                        name="description"
                        class="regular-text"
                        maxlength="300"
                        placeholder="<?php esc_attr_e( 'Breve aclaración o nota', 'aura-suite' ); ?>"
                    >
                </div>

                <!-- Ícono -->
                <div class="aura-veh-form-field">
                    <label><?php esc_html_e( 'Ícono', 'aura-suite' ); ?></label>
                    <input type="hidden" id="aura-cat-icon" name="icon">
                    <div class="aura-cat-icon-grid">
                        <button type="button" class="aura-icon-option aura-icon-none" data-icon="" title="<?php esc_attr_e( 'Sin ícono', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-minus"></span>
                            <small><?php esc_html_e( 'Ninguno', 'aura-suite' ); ?></small>
                        </button>
                        <?php foreach ( $available_icons as $dashicon => $label ) : ?>
                        <button type="button" class="aura-icon-option" data-icon="<?php echo esc_attr( $dashicon ); ?>" title="<?php echo esc_attr( $label ); ?>">
                            <span class="dashicons <?php echo esc_attr( $dashicon ); ?>"></span>
                            <small><?php echo esc_html( $label ); ?></small>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Ámbito (global vs área) -->
                <div class="aura-veh-form-field">
                    <label><?php esc_html_e( 'Ámbito', 'aura-suite' ); ?></label>
                    <div class="aura-cat-radio-group">
                        <label class="aura-cat-radio-option">
                            <input type="radio" id="aura-cat-area-type" name="area_type" value="global" checked>
                            <span class="dashicons dashicons-networking"></span>
                            <span><?php esc_html_e( 'Global — visible en todas las áreas', 'aura-suite' ); ?></span>
                        </label>
                        <?php if ( ! empty( $areas ) ) : ?>
                        <label class="aura-cat-radio-option">
                            <input type="radio" name="area_type" id="aura-cat-area-type-area" value="area">
                            <span class="dashicons dashicons-groups"></span>
                            <span><?php esc_html_e( 'Específico de un área', 'aura-suite' ); ?></span>
                        </label>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! empty( $areas ) ) : ?>
                    <div id="aura-cat-area-selector" style="display:none;margin-top:8px;">
                        <select id="aura-cat-area-id" name="area_id" class="regular-text">
                            <option value=""><?php esc_html_e( '— Seleccionar área —', 'aura-suite' ); ?></option>
                            <?php foreach ( $areas as $area ) : ?>
                            <option value="<?php echo esc_attr( $area->id ); ?>">
                                <?php echo esc_html( $area->name ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else : ?>
                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e( 'No hay áreas configuradas. Todos los ítems serán globales.', 'aura-suite' ); ?>
                    </p>
                    <?php endif; ?>
                </div>

            </div><!-- .aura-veh-modal-body -->

            <div class="aura-veh-modal-footer">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar', 'aura-suite' ); ?></button>
                <button type="button" class="button aura-cat-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            </div>
        </form>
    </div><!-- .aura-veh-modal-content -->
</div><!-- #aura-cat-modal -->

<script>
/* Corregir el radio "Global" para que su ID coincida con el listener en JS */
( function() {
    var radios = document.querySelectorAll( 'input[name="area_type"]' );
    radios.forEach( function( r ) {
        r.addEventListener( 'change', function() {
            var sel = document.getElementById( 'aura-cat-area-selector' );
            var areaId = document.getElementById( 'aura-cat-area-id' );
            if ( r.value === 'area' && r.checked ) {
                if ( sel ) sel.style.display = 'block';
            } else if ( r.value === 'global' && r.checked ) {
                if ( sel ) sel.style.display = 'none';
                if ( areaId ) areaId.value = '';
                /* Actualizar el campo oculto #aura-cat-area-id que usa el JS */
                var hidden = document.getElementById( 'aura-cat-area-type' );
                if ( hidden ) hidden.value = 'global';
            }
        } );
    } );
} )();
</script>
