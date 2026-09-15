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
    'dashicons-location'        => 'Ubicación',
    'dashicons-location-alt'    => 'Marcador',
    'dashicons-admin-home'      => 'Casa',
    'dashicons-building'        => 'Edificio',
    'dashicons-store'           => 'Tienda',
    'dashicons-airplane'        => 'Avión',
    // Transporte
    'dashicons-car'             => 'Auto',
    'dashicons-carrot'          => 'Ruta',
    'dashicons-cart'            => 'Carga',
    // Trabajo / propósito
    'dashicons-businessman'     => 'Persona',
    'dashicons-groups'          => 'Grupo',
    'dashicons-clipboard'       => 'Portapapeles',
    'dashicons-media-document'  => 'Documento',
    'dashicons-hammer'          => 'Herramienta',
    'dashicons-admin-tools'     => 'Herramientas',
    'dashicons-chart-bar'       => 'Gráfico',
    'dashicons-megaphone'       => 'Anuncio',
    'dashicons-calendar-alt'    => 'Calendario',
    // Finanzas / gastos
    'dashicons-money-alt'       => 'Dinero',
    'dashicons-tag'             => 'Etiqueta',
    'dashicons-tickets-alt'     => 'Ticket',
    'dashicons-admin-generic'   => 'Genérico',
    // Mantenimiento
    'dashicons-admin-settings'  => 'Configuración',
    'dashicons-update'          => 'Actualizar',
    'dashicons-welcome-widgets-menus' => 'Lista',
    // Otros
    'dashicons-star-filled'     => 'Estrella',
    'dashicons-info'            => 'Info',
    'dashicons-marker'          => 'Marcador 2',
    'dashicons-plus-alt'        => 'Plus',
);

$tabs = array(
    'destination' => array( 'label' => 'Destinos',    'icon' => 'dashicons-location',  'singular' => 'Destino' ),
    'purpose'     => array( 'label' => 'Propósitos',  'icon' => 'dashicons-clipboard',  'singular' => 'Propósito' ),
    'expense'     => array( 'label' => 'Gastos',      'icon' => 'dashicons-money-alt',  'singular' => 'Gasto' ),
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
                    'destination' => 'Lista de destinos disponibles para registrar en salidas de tipo Encargo. Las áreas pueden tener sus propios destinos adicionales.',
                    'purpose'     => 'Propósitos o motivos de viaje para salidas de tipo Encargo. Se ven en el formulario de nueva salida.',
                    'expense'     => 'Tipos de gastos que pueden registrarse al hacer el check-in de una salida. El total se suma automáticamente.',
                );
                echo esc_html( $descriptions[ $type ] );
                ?>
            </p>
            <?php if ( $can_manage ) : ?>
            <button type="button" class="button button-primary aura-cat-btn-new" data-type="<?php echo esc_attr( $type ); ?>">
                <span class="dashicons dashicons-plus-alt2"></span>
                Nuevo <?php echo esc_html( $tab['singular'] ); ?>
            </button>
            <?php endif; ?>
        </div>

        <table class="aura-cat-table wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <?php if ( $can_manage ) : ?>
                    <th class="aura-cat-th-handle" title="Arrastrar para reordenar">
                        <span class="dashicons dashicons-menu"></span>
                    </th>
                    <?php endif; ?>
                    <th class="aura-cat-th-icon">Icono</th>
                    <th>Nombre / Descripción</th>
                    <th class="aura-cat-th-area">Ámbito</th>
                    <th class="aura-cat-th-status">Estado</th>
                    <?php if ( $can_manage ) : ?>
                    <th class="aura-cat-th-actions">Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="aura-cat-tbody-<?php echo esc_attr( $type ); ?>">
                <tr>
                    <td colspan="6" class="aura-cat-empty">
                        <span class="spinner is-active" style="float:none;vertical-align:middle;"></span>
                        Cargando…
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
            <h2 id="aura-cat-modal-title">Nuevo Destino</h2>
            <button type="button" class="aura-veh-modal-close aura-cat-modal-close" aria-label="Cerrar">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <form id="aura-cat-form" autocomplete="off">
            <input type="hidden" id="aura-cat-field-type" name="type">

            <div class="aura-veh-modal-body">

                <!-- Nombre -->
                <div class="aura-veh-form-field">
                    <label for="aura-cat-name">
                        Nombre <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="aura-cat-name"
                        name="name"
                        class="regular-text"
                        maxlength="150"
                        required
                        placeholder="Ej. Aeropuerto Internacional"
                    >
                </div>

                <!-- Descripción -->
                <div class="aura-veh-form-field">
                    <label for="aura-cat-description">Descripción
                        <span class="aura-veh-field-hint">(opcional)</span>
                    </label>
                    <input
                        type="text"
                        id="aura-cat-description"
                        name="description"
                        class="regular-text"
                        maxlength="300"
                        placeholder="Breve aclaración o nota"
                    >
                </div>

                <!-- Ícono -->
                <div class="aura-veh-form-field">
                    <label>Ícono</label>
                    <input type="hidden" id="aura-cat-icon" name="icon">
                    <div class="aura-cat-icon-grid">
                        <button type="button" class="aura-icon-option aura-icon-none" data-icon="" title="Sin ícono">
                            <span class="dashicons dashicons-minus"></span>
                            <small>Ninguno</small>
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
                    <label>Ámbito</label>
                    <div class="aura-cat-radio-group">
                        <label class="aura-cat-radio-option">
                            <input type="radio" id="aura-cat-area-type" name="area_type" value="global" checked>
                            <span class="dashicons dashicons-networking"></span>
                            <span>Global — visible en todas las áreas</span>
                        </label>
                        <?php if ( ! empty( $areas ) ) : ?>
                        <label class="aura-cat-radio-option">
                            <input type="radio" name="area_type" id="aura-cat-area-type-area" value="area">
                            <span class="dashicons dashicons-groups"></span>
                            <span>Específico de un área</span>
                        </label>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! empty( $areas ) ) : ?>
                    <div id="aura-cat-area-selector" style="display:none;margin-top:8px;">
                        <select id="aura-cat-area-id" name="area_id" class="regular-text">
                            <option value="">— Seleccionar área —</option>
                            <?php foreach ( $areas as $area ) : ?>
                            <option value="<?php echo esc_attr( $area->id ); ?>">
                                <?php echo esc_html( $area->name ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else : ?>
                    <p class="description" style="margin-top:6px;">
                        No hay áreas configuradas. Todos los ítems serán globales.
                    </p>
                    <?php endif; ?>
                </div>

            </div><!-- .aura-veh-modal-body -->

            <div class="aura-veh-modal-footer">
                <button type="submit" class="button button-primary">Guardar</button>
                <button type="button" class="button aura-cat-modal-close">Cancelar</button>
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
