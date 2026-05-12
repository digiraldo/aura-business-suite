<?php
/**
 * Vista: Vehículos — Fase 2 (implementación completa)
 * Tabla DataTables + modales de crear/editar, áreas, baja y fotos.
 *
 * @package Aura_Business_Suite\Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Verificar acceso
if ( ! current_user_can( 'aura_vehicles_view_all' ) && ! current_user_can( 'manage_options' ) ) {
    global $wpdb;
    $user_in_area = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}aura_area_users WHERE user_id = %d",
        get_current_user_id()
    ) );
    if ( ! $user_in_area ) {
        wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aura-suite' ) );
    }
}

$can_create = current_user_can( 'aura_vehicles_create' ) || current_user_can( 'manage_options' );
$can_edit   = current_user_can( 'aura_vehicles_edit' )   || current_user_can( 'manage_options' );
$can_delete = current_user_can( 'aura_vehicles_delete' ) || current_user_can( 'manage_options' );

// Áreas para filtro
global $wpdb;
$areas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY name ASC"
) ?: array();
?>

<div class="wrap aura-vehicles-list">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-car" style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
        <?php esc_html_e( 'Vehículos', 'aura-suite' ); ?>
    </h1>

    <?php if ( $can_create ) : ?>
    <button type="button" id="aura-veh-btn-create" class="page-title-action">
        + <?php esc_html_e( 'Nuevo Vehículo', 'aura-suite' ); ?>
    </button>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- ── Barra de filtros ──────────────────────────────────── -->
    <div class="aura-veh-filters-bar">
        <input type="search" id="aura-veh-search"
               placeholder="<?php esc_attr_e( 'Placa, marca, modelo…', 'aura-suite' ); ?>"
               class="regular-text">

        <select id="aura-veh-filter-status">
            <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
            <option value="available"><?php esc_html_e( 'Disponible', 'aura-suite' ); ?></option>
            <option value="rented"><?php esc_html_e( 'En uso / Alquiler', 'aura-suite' ); ?></option>
            <option value="maintenance"><?php esc_html_e( 'Mantenimiento', 'aura-suite' ); ?></option>
            <option value="unavailable"><?php esc_html_e( 'Fuera de servicio', 'aura-suite' ); ?></option>
        </select>

        <select id="aura-veh-filter-type">
            <option value=""><?php esc_html_e( 'Todos los tipos', 'aura-suite' ); ?></option>
            <option value="sedan">Sedán</option>
            <option value="suv">SUV</option>
            <option value="pickup">Pickup</option>
            <option value="van">Van</option>
            <option value="bus">Bus</option>
            <option value="motorcycle"><?php esc_html_e( 'Moto', 'aura-suite' ); ?></option>
            <option value="truck"><?php esc_html_e( 'Camión', 'aura-suite' ); ?></option>
            <option value="other"><?php esc_html_e( 'Otro', 'aura-suite' ); ?></option>
        </select>

        <?php if ( ! empty( $areas ) ) : ?>
        <select id="aura-veh-filter-area">
            <option value="0"><?php esc_html_e( 'Todas las áreas', 'aura-suite' ); ?></option>
            <?php foreach ( $areas as $area ) : ?>
            <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <button id="aura-veh-filter-apply" class="button">
            <span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Filtrar', 'aura-suite' ); ?>
        </button>
        <button id="aura-veh-filter-clear" class="button button-link">
            <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
        </button>
    </div>

    <!-- ── Tabla ─────────────────────────────────────────────── -->
    <table id="aura-veh-table" class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th style="width:70px;"><?php esc_html_e( 'Foto', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Placa', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Vehículo', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'KM', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Áreas', 'aura-suite' ); ?></th>
                <th><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

<?php
// ── DataTables CDN (core + Responsive) ──────────────────────────
wp_enqueue_style( 'datatables-css', 'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css', array(), '2.2.2' );
wp_enqueue_style( 'datatables-responsive-css', 'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css', array( 'datatables-css' ), '3.0.4' );
wp_enqueue_script( 'datatables-js',  'https://cdn.datatables.net/2.2.2/js/dataTables.min.js', array( 'jquery' ), '2.2.2', true );
wp_enqueue_script( 'datatables-responsive-js', 'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js', array( 'datatables-js' ), '3.0.4', true );

// ── Cropper.js CDN ───────────────────────────────────────────────
wp_enqueue_style( 'cropperjs', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css', array(), '1.6.2' );
wp_enqueue_script( 'cropperjs', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js', array(), '1.6.2', true );

// ── QRCode.js CDN ────────────────────────────────────────────────
wp_enqueue_script( 'qrcodejs', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', array(), '1.0.0', true );

// ── Media Library ────────────────────────────────────────────────
wp_enqueue_media();

$module_url     = AURA_PLUGIN_URL . 'modules/vehicles/';
$module_dir     = AURA_PLUGIN_DIR . 'modules/vehicles/';
wp_enqueue_script(
    'aura-veh-vehicles',
    $module_url . 'assets/js/vehicle-vehicles.js',
    array( 'jquery', 'datatables-responsive-js', 'cropperjs', 'qrcodejs' ),
    filemtime( $module_dir . 'assets/js/vehicle-vehicles.js' ),
    true
);

// Inyectar configuración PHP → JS
$veh_cfg = wp_json_encode( array(
    'apiBase'   => rest_url( 'aura/v1/' ),
    'nonce'     => wp_create_nonce( 'wp_rest' ),
    'ajaxurl'   => admin_url( 'admin-ajax.php' ),
    'qrIconUrl' => AURA_PLUGIN_URL . 'assets/images/qr.svg',
    'vehNonce'  => wp_create_nonce( 'aura_vehicles_nonce' ),
    'canCreate' => $can_create,
    'canEdit'   => $can_edit,
    'canDelete' => $can_delete,
    'txt'       => array(
        'loading'          => __( 'Cargando…', 'aura-suite' ),
        'no_results'       => __( 'No se encontraron vehículos.', 'aura-suite' ),
        'confirm_delete'   => __( '¿Eliminar este vehículo? Esta acción no se puede deshacer.', 'aura-suite' ),
        'error'            => __( 'Error al procesar la solicitud.', 'aura-suite' ),
        'saved'            => __( 'Vehículo guardado correctamente.', 'aura-suite' ),
        'deleted'          => __( 'Vehículo eliminado.', 'aura-suite' ),
        'area_assigned'    => __( 'Área asignada.', 'aura-suite' ),
        'area_unassigned'  => __( 'Área desasignada.', 'aura-suite' ),
        'unavailable_done' => __( 'Vehículo dado de baja.', 'aura-suite' ),
        'restored'         => __( 'Vehículo restaurado.', 'aura-suite' ),
        'transferred'      => __( 'Vehículo transferido.', 'aura-suite' ),
        'photo_uploaded'   => __( 'Foto subida.', 'aura-suite' ),
        'photo_deleted'    => __( 'Foto eliminada.', 'aura-suite' ),
        'status' => array(
            'available'   => __( 'Disponible', 'aura-suite' ),
            'rented'      => __( 'En uso', 'aura-suite' ),
            'maintenance' => __( 'Mantenimiento', 'aura-suite' ),
            'unavailable' => __( 'Fuera de servicio', 'aura-suite' ),
        ),
        'type' => array(
            'sedan'      => 'Sedán',  'suv'        => 'SUV',    'pickup' => 'Pickup',
            'van'        => 'Van',    'bus'        => 'Bus',    'motorcycle' => 'Moto',
            'truck'      => 'Camión', 'other'      => 'Otro',
        ),
        'fuel' => array(
            'gasoline' => 'Gasolina', 'diesel'  => 'Diesel',
            'electric' => 'Eléctrico','hybrid'  => 'Híbrido', 'gas' => 'Gas',
        ),
        'transmission' => array(
            'manual' => 'Manual', 'automatic' => 'Automático',
        ),
    ),
), JSON_UNESCAPED_UNICODE );
?>
<script>var auraVehiclesListCfg = <?php echo $veh_cfg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;</script>

</div><!-- .wrap -->


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Ver detalle de vehículo
══════════════════════════════════════════════════════════════ -->
<div id="aura-veh-modal-view" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-veh-modal-view-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-modal-large aura-veh-view-modal-content">

        <div class="aura-veh-modal-header aura-veh-view-header">
            <h2 id="aura-veh-modal-view-title">
                <span class="dashicons dashicons-visibility" style="vertical-align:middle;"></span>
                <?php esc_html_e( 'Detalle del Vehículo', 'aura-suite' ); ?>
            </h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div id="aura-veh-view-body" class="aura-veh-modal-body aura-veh-view-body"></div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Lightbox foto de vehículo
══════════════════════════════════════════════════════════════ -->
<div id="aura-veh-modal-lightbox" class="aura-veh-modal aura-veh-lightbox" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-veh-lightbox-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-lightbox-content">
        <div class="aura-veh-modal-header aura-veh-lightbox-header">
            <h2 id="aura-veh-lightbox-title"><?php esc_html_e( 'Vista completa', 'aura-suite' ); ?></h2>
            <div class="aura-veh-lightbox-toolbar">
                <button type="button" class="button" id="aura-veh-lightbox-prev" aria-label="<?php esc_attr_e( 'Foto anterior', 'aura-suite' ); ?>">&larr;</button>
                <span id="aura-veh-lightbox-counter" aria-live="polite">1 / 1</span>
                <button type="button" class="button" id="aura-veh-lightbox-next" aria-label="<?php esc_attr_e( 'Foto siguiente', 'aura-suite' ); ?>">&rarr;</button>
            </div>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>
        <div class="aura-veh-modal-body aura-veh-lightbox-body">
            <img id="aura-veh-lightbox-img" src="" alt="">
        </div>
        <div id="aura-veh-lightbox-thumbs" class="aura-veh-lightbox-thumbs"></div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Crear / Editar vehículo
══════════════════════════════════════════════════════════════ -->
<div id="aura-veh-modal-form" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-veh-modal-form-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-modal-large">

        <div class="aura-veh-modal-header">
            <h2 id="aura-veh-modal-form-title"><?php esc_html_e( 'Vehículo', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body">
            <form id="aura-veh-form" novalidate>
                <input type="hidden" id="aura-veh-form-id" value="">

                <div class="aura-veh-form-layout">
                <div class="aura-veh-form-main">

                <!-- Sección: Identificación -->
                <div class="aura-veh-form-section">
                <div class="aura-veh-form-section-title">
                    <span class="dashicons dashicons-id"></span>
                    <?php esc_html_e( 'Identificación', 'aura-suite' ); ?>
                </div>

                <!-- Fila 1: placa / marca / modelo -->
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="veh-plate"><strong><?php esc_html_e( 'Placa *', 'aura-suite' ); ?></strong></label>
                        <input type="text" id="veh-plate" name="plate" class="regular-text" placeholder="ABC-123" maxlength="20" required>
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="veh-brand"><strong><?php esc_html_e( 'Marca *', 'aura-suite' ); ?></strong></label>
                        <input type="text" id="veh-brand" name="brand" class="regular-text" placeholder="Toyota" maxlength="50" required>
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="veh-model"><strong><?php esc_html_e( 'Modelo *', 'aura-suite' ); ?></strong></label>
                        <input type="text" id="veh-model" name="model" class="regular-text" placeholder="Hilux" maxlength="50" required>
                    </div>
                </div>

                <!-- Fila 2: año / color / VIN -->
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="veh-year"><?php esc_html_e( 'Año', 'aura-suite' ); ?></label>
                        <input type="number" id="veh-year" name="year" class="small-text" min="1900" max="2099" placeholder="2020">
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="veh-color"><?php esc_html_e( 'Color', 'aura-suite' ); ?></label>
                        <input type="text" id="veh-color" name="color" class="regular-text" placeholder="Blanco" maxlength="30">
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="veh-vin">VIN</label>
                        <input type="text" id="veh-vin" name="vin" class="regular-text" placeholder="17 caracteres" maxlength="17">
                    </div>
                </div>

                </div><!-- /section identificación -->

                <!-- Sección: Características técnicas -->
                <div class="aura-veh-form-section">
                <div class="aura-veh-form-section-title">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e( 'Características técnicas', 'aura-suite' ); ?>
                </div>

                <!-- Fila 3: tipo / combustible / transmisión -->
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="veh-type"><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></label>
                        <select id="veh-type" name="type">
                            <option value="sedan">Sedán</option>
                            <option value="suv">SUV</option>
                            <option value="pickup">Pickup</option>
                            <option value="van">Van</option>
                            <option value="bus">Bus</option>
                            <option value="motorcycle"><?php esc_html_e( 'Moto', 'aura-suite' ); ?></option>
                            <option value="truck"><?php esc_html_e( 'Camión', 'aura-suite' ); ?></option>
                            <option value="other"><?php esc_html_e( 'Otro', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="veh-fuel_type"><?php esc_html_e( 'Combustible', 'aura-suite' ); ?></label>
                        <select id="veh-fuel_type" name="fuel_type">
                            <option value="gasoline"><?php esc_html_e( 'Gasolina', 'aura-suite' ); ?></option>
                            <option value="diesel">Diesel</option>
                            <option value="electric"><?php esc_html_e( 'Eléctrico', 'aura-suite' ); ?></option>
                            <option value="hybrid"><?php esc_html_e( 'Híbrido', 'aura-suite' ); ?></option>
                            <option value="gas">Gas</option>
                        </select>
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="veh-transmission"><?php esc_html_e( 'Transmisión', 'aura-suite' ); ?></label>
                        <select id="veh-transmission" name="transmission">
                            <option value="manual"><?php esc_html_e( 'Manual', 'aura-suite' ); ?></option>
                            <option value="automatic"><?php esc_html_e( 'Automático', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                </div>

                </div><!-- /section técnicas -->

                <!-- Sección: Datos operativos -->
                <div class="aura-veh-form-section">
                <div class="aura-veh-form-section-title">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php esc_html_e( 'Datos operativos', 'aura-suite' ); ?>
                </div>

                <!-- Fila 4: km / tarifa / estado -->
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="veh-mileage"><?php esc_html_e( 'Kilometraje actual', 'aura-suite' ); ?></label>
                        <input type="number" id="veh-mileage" name="mileage" class="regular-text" min="0" value="0">
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="veh-rate_per_km"><?php esc_html_e( 'Tarifa por km', 'aura-suite' ); ?></label>
                        <input type="number" id="veh-rate_per_km" name="rate_per_km" class="regular-text" min="0" step="0.01" value="0.00">
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="veh-status"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></label>
                        <select id="veh-status" name="status">
                            <option value="available"><?php esc_html_e( 'Disponible', 'aura-suite' ); ?></option>
                            <option value="maintenance"><?php esc_html_e( 'Mantenimiento', 'aura-suite' ); ?></option>
                            <option value="unavailable"><?php esc_html_e( 'Fuera de servicio', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Notas -->
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col" style="flex:1 1 100%;">
                        <label for="veh-notes"><?php esc_html_e( 'Notas', 'aura-suite' ); ?></label>
                        <textarea id="veh-notes" name="notes" rows="3" class="large-text"></textarea>
                    </div>
                </div>
                </div><!-- /section operativos -->

                </div><!-- .aura-veh-form-main -->

                <!-- Sidebar: foto principal -->
                <div class="aura-veh-form-sidebar">
                <div class="aura-veh-form-section">
                <div class="aura-veh-form-section-title">
                    <span class="dashicons dashicons-format-image"></span>
                    <?php esc_html_e( 'Foto principal', 'aura-suite' ); ?>
                </div>
                <input type="hidden" id="veh-photo" value="">
                <div id="aura-veh-photo-preview" style="margin-bottom:10px;min-height:80px;border-radius:4px;overflow:hidden;background:#f0f0f0;"></div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <button type="button" id="aura-veh-photo-select-btn" class="button" style="width:100%;display:flex;align-items:center;justify-content:center;gap:6px;">
                        <span class="dashicons dashicons-format-image"></span>
                        <?php esc_html_e( 'Seleccionar / Cambiar Foto', 'aura-suite' ); ?>
                    </button>
                    <button type="button" id="aura-veh-photo-remove-btn" class="button button-link-delete" style="display:none;width:100%;text-align:center;">
                        <?php esc_html_e( 'Quitar foto', 'aura-suite' ); ?>
                    </button>
                </div>
                <p class="description" style="margin-top:8px;font-size:11px;color:#72777c;"><?php esc_html_e( 'Proporción 4:3. Se recortará al guardar.', 'aura-suite' ); ?></p>
                </div><!-- /section foto -->
                </div><!-- .aura-veh-form-sidebar -->

                </div><!-- .aura-veh-form-layout -->

                <div id="aura-veh-form-error" class="notice notice-error" style="display:none;margin:10px 0;padding:8px 12px;"></div>
            </form>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-veh-form-submit" class="button button-primary">
                <?php esc_html_e( 'Guardar', 'aura-suite' ); ?>
            </button>
        </div>

    </div><!-- .aura-veh-modal-content -->
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Gestión de áreas
══════════════════════════════════════════════════════════════ -->
<div id="aura-veh-modal-areas" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-veh-modal-areas-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content">

        <div class="aura-veh-modal-header">
            <h2 id="aura-veh-modal-areas-title"><?php esc_html_e( 'Gestión de Áreas', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body">
            <input type="hidden" id="aura-veh-areas-vehicle-id" value="">
            <p class="description"><?php esc_html_e( 'Áreas asignadas actualmente:', 'aura-suite' ); ?></p>
            <div id="aura-veh-current-areas"></div>

            <hr>
            <p class="description"><?php esc_html_e( 'Asignar nueva área:', 'aura-suite' ); ?></p>
            <div style="display:flex;gap:8px;align-items:center;">
                <select id="aura-veh-area-select" style="flex:1;">
                    <option value=""><?php esc_html_e( '— Seleccionar área —', 'aura-suite' ); ?></option>
                </select>
                <button type="button" id="aura-veh-area-assign-btn" class="button button-primary">
                    <?php esc_html_e( 'Asignar', 'aura-suite' ); ?>
                </button>
            </div>
            <div id="aura-veh-areas-msg" style="margin-top:8px;"></div>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Dar de baja
══════════════════════════════════════════════════════════════ -->
<div id="aura-veh-modal-unavailable" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-veh-modal-unavailable-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content">

        <div class="aura-veh-modal-header">
            <h2 id="aura-veh-modal-unavailable-title"><?php esc_html_e( 'Dar de Baja', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body">
            <input type="hidden" id="aura-veh-unavail-vehicle-id" value="">
            <div class="aura-veh-form-row">
                <div class="aura-veh-form-col" style="flex:1 1 100%;">
                    <label for="aura-veh-unavail-reason"><strong><?php esc_html_e( 'Motivo *', 'aura-suite' ); ?></strong></label>
                    <input type="text" id="aura-veh-unavail-reason" class="large-text"
                           placeholder="<?php esc_attr_e( 'Ej.: Accidente, retiro definitivo…', 'aura-suite' ); ?>">
                </div>
            </div>
            <div class="aura-veh-form-row">
                <div class="aura-veh-form-col" style="flex:1 1 100%;">
                    <label for="aura-veh-unavail-notes"><?php esc_html_e( 'Observaciones', 'aura-suite' ); ?></label>
                    <textarea id="aura-veh-unavail-notes" rows="3" class="large-text"></textarea>
                </div>
            </div>
            <div id="aura-veh-unavail-msg" style="margin-top:8px;"></div>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-veh-unavail-submit" class="button button-primary">
                <?php esc_html_e( 'Dar de Baja', 'aura-suite' ); ?>
            </button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Transferir
══════════════════════════════════════════════════════════════ -->
<div id="aura-veh-modal-transfer" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-veh-modal-transfer-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content">

        <div class="aura-veh-modal-header">
            <h2 id="aura-veh-modal-transfer-title"><?php esc_html_e( 'Transferir Vehículo', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body">
            <input type="hidden" id="aura-veh-transfer-vehicle-id" value="">
            <div class="aura-veh-form-row">
                <div class="aura-veh-form-col">
                    <label for="aura-veh-transfer-from"><strong><?php esc_html_e( 'Área origen *', 'aura-suite' ); ?></strong></label>
                    <select id="aura-veh-transfer-from" style="width:100%;"></select>
                </div>
                <div class="aura-veh-form-col">
                    <label for="aura-veh-transfer-to"><strong><?php esc_html_e( 'Área destino *', 'aura-suite' ); ?></strong></label>
                    <select id="aura-veh-transfer-to" style="width:100%;"></select>
                </div>
            </div>
            <div id="aura-veh-transfer-msg" style="margin-top:8px;"></div>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-veh-transfer-submit" class="button button-primary">
                <?php esc_html_e( 'Transferir', 'aura-suite' ); ?>
            </button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Fotos
══════════════════════════════════════════════════════════════ -->
<div id="aura-veh-modal-photos" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-veh-modal-photos-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-modal-large">

        <div class="aura-veh-modal-header">
            <h2 id="aura-veh-modal-photos-title"><?php esc_html_e( 'Fotos', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body">
            <input type="hidden" id="aura-veh-photos-vehicle-id" value="">
            <div id="aura-veh-photos-gallery" class="aura-veh-photos-gallery"></div>

            <?php if ( $can_edit ) : ?>
            <hr>
            <p class="description"><?php esc_html_e( 'Añadir fotos (máx 10, 2 MB, JPG/PNG/WebP):', 'aura-suite' ); ?></p>
            <div class="aura-veh-photo-upload-wrap">
                <label for="aura-veh-photo-file" class="button">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e( 'Seleccionar imagen', 'aura-suite' ); ?>
                </label>
                <input type="file" id="aura-veh-photo-file" accept=".jpg,.jpeg,.png,.webp" style="display:none;">
                <span id="aura-veh-photo-filename" style="margin-left:8px;color:#666;"></span>
                <button type="button" id="aura-veh-photo-upload-btn" class="button button-primary" style="display:none;">
                    <?php esc_html_e( 'Subir', 'aura-suite' ); ?>
                </button>
            </div>
            <div id="aura-veh-photos-msg" style="margin-top:8px;"></div>
            <?php endif; ?>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Recortar foto (Cropper.js)
══════════════════════════════════════════════════════════════ -->
<div id="aura-veh-crop-modal" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-veh-crop-modal-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-modal-large">

        <div class="aura-veh-modal-header">
            <h2 id="aura-veh-crop-modal-title"><?php esc_html_e( 'Ajustar Foto', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body" style="text-align:center;">
            <div style="max-height:420px;overflow:hidden;background:#000;display:flex;align-items:center;justify-content:center;">
                <img id="aura-veh-crop-img" src="" alt="" style="max-width:100%;display:block;">
            </div>
            <p class="description" style="margin-top:8px;"><?php esc_html_e( 'Ajusta el área de recorte (proporción 4:3). Luego haz clic en "Aplicar".', 'aura-suite' ); ?></p>
            <div id="aura-veh-crop-msg" style="margin-top:8px;display:none;"></div>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-veh-crop-apply" class="button button-primary">
                <?php esc_html_e( 'Aplicar recorte', 'aura-suite' ); ?>
            </button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Confirmar eliminación
══════════════════════════════════════════════════════════════ -->
<div id="aura-veh-modal-delete" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-veh-modal-delete-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-modal-sm">

        <div class="aura-veh-modal-header">
            <h2 id="aura-veh-modal-delete-title"><?php esc_html_e( 'Confirmar eliminación', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body">
            <input type="hidden" id="aura-veh-delete-vehicle-id" value="">
            <p><?php esc_html_e( '¿Eliminar este vehículo? Esta acción no se puede deshacer.', 'aura-suite' ); ?></p>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-veh-delete-confirm" class="button button-link-delete">
                <?php esc_html_e( 'Eliminar', 'aura-suite' ); ?>
            </button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Código QR del vehículo
══════════════════════════════════════════════════════════════ -->
<div id="aura-veh-modal-qr" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-veh-modal-qr-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-modal-sm">

        <div class="aura-veh-modal-header">
            <h2 id="aura-veh-modal-qr-title">
                <span class="dashicons dashicons-share" style="vertical-align:middle;"></span>
                <?php esc_html_e( 'Código QR', 'aura-suite' ); ?>
            </h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body" style="text-align:center;padding:20px;">
            <input type="hidden" id="aura-veh-qr-vehicle-id" value="">
            <p id="aura-veh-qr-vehicle-name" style="font-weight:600;font-size:15px;margin:0 0 12px;"></p>

            <!-- Loader -->
            <div id="aura-veh-qr-loader" style="padding:30px 0;">
                <span class="spinner is-active" style="float:none;margin:0 auto;display:block;"></span>
            </div>

            <!-- Contenedor QR (se inyecta dinámicamente) -->
            <div id="aura-veh-qr-canvas-wrap" style="display:none;">
                <div id="aura-veh-qr-canvas" style="display:inline-block;padding:12px;background:#fff;border:1px solid #ddd;border-radius:8px;"></div>
                <p class="description" style="margin-top:10px;font-size:12px;word-break:break-all;" id="aura-veh-qr-url-text"></p>
            </div>

            <!-- Sin QR -->
            <div id="aura-veh-qr-empty" style="display:none;padding:20px 0;">
                <span class="dashicons dashicons-share" style="font-size:40px;color:#ccc;display:block;margin:0 auto 10px;"></span>
                <p style="color:#666;"><?php esc_html_e( 'Este vehículo aún no tiene QR. Haz clic en "Generar QR".', 'aura-suite' ); ?></p>
            </div>

            <!-- Mensaje error/éxito -->
            <div id="aura-veh-qr-msg" style="margin-top:10px;display:none;"></div>

            <!-- Botones de acción QR -->
            <div id="aura-veh-qr-actions" style="display:none;margin-top:16px;display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">
                <button type="button" id="aura-veh-qr-download" class="button">
                    <span class="dashicons dashicons-download" style="vertical-align:middle;"></span>
                    <?php esc_html_e( 'Descargar PNG', 'aura-suite' ); ?>
                </button>
                <button type="button" id="aura-veh-qr-copy" class="button">
                    <span class="dashicons dashicons-clipboard" style="vertical-align:middle;"></span>
                    <?php esc_html_e( 'Copiar URL', 'aura-suite' ); ?>
                </button>
                <button type="button" id="aura-veh-qr-print" class="button aura-veh-qr-print-btn" title="<?php esc_attr_e( 'Genera una etiqueta lista para recortar y pegar en el tablero o colgar del vehículo', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-printer" style="vertical-align:middle;"></span>
                    <?php esc_html_e( 'Imprimir etiqueta', 'aura-suite' ); ?>
                </button>
                <button type="button" id="aura-veh-qr-invalidate" class="button" style="color:#c00;">
                    <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                    <?php esc_html_e( 'Regenerar QR', 'aura-suite' ); ?>
                </button>
            </div>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" id="aura-veh-qr-generate" class="button button-primary" style="display:none;">
                <span class="dashicons dashicons-share" style="vertical-align:middle;"></span>
                <?php esc_html_e( 'Generar QR', 'aura-suite' ); ?>
            </button>
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>

    </div>
</div>
