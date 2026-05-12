<?php
/**
 * Vista: Salidas / Trips — Fase 3 (implementación completa)
 * Tabla DataTables + modales de nueva salida, check-in y cancelación.
 *
 * @package Aura_Business_Suite\Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Verificar acceso mínimo
if ( ! current_user_can( 'aura_vehicles_exits_create' )
    && ! current_user_can( 'aura_vehicles_view_all' )
    && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aura-suite' ) );
}

$can_create   = current_user_can( 'aura_vehicles_exits_create' )  || current_user_can( 'manage_options' );
$can_edit_all = current_user_can( 'aura_vehicles_exits_edit_all' ) || current_user_can( 'manage_options' );
$can_edit_own = current_user_can( 'aura_vehicles_exits_edit_own' );
$can_delete_all = current_user_can( 'aura_vehicles_exits_delete_all' )
    || current_user_can( 'aura_vehicles_delete' )
    || current_user_can( 'manage_options' );
$can_delete_own = current_user_can( 'aura_vehicles_exits_delete_own' );

// Áreas para filtro
global $wpdb;
$areas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY name ASC"
) ?: array();
?>

<div class="wrap aura-vehicles-trips">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-car" style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
        <?php esc_html_e( 'Salidas', 'aura-suite' ); ?>
    </h1>

    <?php if ( $can_create ) : ?>
    <button type="button" id="aura-trips-btn-create" class="page-title-action">
        + <?php esc_html_e( 'Nueva Salida', 'aura-suite' ); ?>
    </button>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- ── Barra de filtros ────────────────────────────────── -->
    <div class="aura-veh-filters-bar">
        <select id="aura-trips-filter-type">
            <option value=""><?php esc_html_e( 'Todos los tipos', 'aura-suite' ); ?></option>
            <option value="rental"><?php esc_html_e( 'Renta', 'aura-suite' ); ?></option>
            <option value="errand"><?php esc_html_e( 'Encargo', 'aura-suite' ); ?></option>
            <option value="maintenance"><?php esc_html_e( 'Mantenimiento', 'aura-suite' ); ?></option>
            <option value="other"><?php esc_html_e( 'Otro', 'aura-suite' ); ?></option>
        </select>

        <select id="aura-trips-filter-status">
            <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
            <option value="active"><?php esc_html_e( 'Activa', 'aura-suite' ); ?></option>
            <option value="returned"><?php esc_html_e( 'Retornada', 'aura-suite' ); ?></option>
            <option value="cancelled"><?php esc_html_e( 'Cancelada', 'aura-suite' ); ?></option>
        </select>

        <?php if ( ! empty( $areas ) ) : ?>
        <select id="aura-trips-filter-area">
            <option value="0"><?php esc_html_e( 'Todas las áreas', 'aura-suite' ); ?></option>
            <?php foreach ( $areas as $area ) : ?>
            <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <input type="date" id="aura-trips-filter-from" title="<?php esc_attr_e( 'Desde', 'aura-suite' ); ?>">
        <input type="date" id="aura-trips-filter-to"   title="<?php esc_attr_e( 'Hasta', 'aura-suite' ); ?>">

        <input type="search" id="aura-trips-search"
               placeholder="<?php esc_attr_e( 'Buscar…', 'aura-suite' ); ?>"
               class="regular-text">

        <button id="aura-trips-filter-apply" class="button">
            <span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Filtrar', 'aura-suite' ); ?>
        </button>
        <button id="aura-trips-filter-clear" class="button button-link">
            <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
        </button>
    </div>

    <!-- ── Tabla ──────────────────────────────────────────── -->
    <div class="aura-trips-table-wrapper">
        <table id="aura-trips-table" class="wp-list-table widefat striped aura-trips-table-structured">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Vehículo', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Responsable / Cliente', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Destino', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Salida', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Retorno', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'KM', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

<?php
// ── Assets (DataTables core + Responsive) ───────────────────────
wp_enqueue_style( 'datatables-css', 'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css', array(), '2.2.2' );
wp_enqueue_style( 'datatables-responsive-css', 'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css', array( 'datatables-css' ), '3.0.4' );
wp_enqueue_script( 'datatables-js',  'https://cdn.datatables.net/2.2.2/js/dataTables.min.js', array( 'jquery' ), '2.2.2', true );
wp_enqueue_script( 'datatables-responsive-js', 'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js', array( 'datatables-js' ), '3.0.4', true );

$module_url = AURA_PLUGIN_URL . 'modules/vehicles/';
$module_dir = AURA_PLUGIN_DIR . 'modules/vehicles/';

wp_enqueue_script(
    'aura-veh-trips',
    $module_url . 'assets/js/vehicle-trips.js',
    array( 'jquery', 'datatables-responsive-js' ),
    filemtime( $module_dir . 'assets/js/vehicle-trips.js' ),
    true
);

// Config PHP → JS
$trips_cfg = wp_json_encode( array(
    'apiBase'    => rest_url( 'aura/v1/' ),
    'nonce'      => wp_create_nonce( 'wp_rest' ),
    'currentUid' => get_current_user_id(),
    'canCreate'  => $can_create,
    'canEditAll' => $can_edit_all,
    'canEditOwn' => $can_edit_own,
    'canDeleteAll' => $can_delete_all,
    'canDeleteOwn' => $can_delete_own,
    'txt'        => array(
        'loading'          => __( 'Cargando…', 'aura-suite' ),
        'no_results'       => __( 'No se encontraron salidas.', 'aura-suite' ),
        'error'            => __( 'Error al procesar la solicitud.', 'aura-suite' ),
        'saved'            => __( 'Salida registrada correctamente.', 'aura-suite' ),
        'updated'          => __( 'Salida actualizada.', 'aura-suite' ),
        'checked_in'       => __( 'Retorno registrado correctamente.', 'aura-suite' ),
        'cancelled'        => __( 'Salida cancelada.', 'aura-suite' ),
        'deleted'          => __( 'Salida eliminada.', 'aura-suite' ),
        'status' => array(
            'active'    => __( 'Activa', 'aura-suite' ),
            'returned'  => __( 'Retornada', 'aura-suite' ),
            'cancelled' => __( 'Cancelada', 'aura-suite' ),
        ),
        'type' => array(
            'rental'      => __( 'Renta', 'aura-suite' ),
            'errand'      => __( 'Encargo', 'aura-suite' ),
            'maintenance' => __( 'Mantenimiento', 'aura-suite' ),
            'other'       => __( 'Otro', 'aura-suite' ),
        ),
        'maint_subtype' => array(
            'preventive'  => __( 'Preventivo', 'aura-suite' ),
            'corrective'  => __( 'Correctivo', 'aura-suite' ),
            'inspection'  => __( 'Inspección', 'aura-suite' ),
        ),
        'maint_priority' => array(
            'low'      => __( 'Baja', 'aura-suite' ),
            'medium'   => __( 'Media', 'aura-suite' ),
            'high'     => __( 'Alta', 'aura-suite' ),
            'critical' => __( 'Crítico', 'aura-suite' ),
        ),
    ),
), JSON_UNESCAPED_UNICODE );
?>
<script>var auraTripsListCfg = <?php echo $trips_cfg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;</script>

</div><!-- .wrap -->


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Nueva / Editar salida
══════════════════════════════════════════════════════════════ -->
<div id="aura-trips-modal-form" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-trips-form-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-modal-large">

        <div class="aura-veh-modal-header">
            <h2 id="aura-trips-form-title"><?php esc_html_e( 'Nueva Salida', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body">
            <input type="hidden" id="aura-trips-form-id" value="">

            <!-- Sección: Datos de la salida -->
            <div class="aura-veh-form-section">
            <div class="aura-veh-form-section-title">
                <span class="dashicons dashicons-car"></span>
                <?php esc_html_e( 'Datos de la salida', 'aura-suite' ); ?>
            </div>
            <div class="aura-veh-form-row">
                <div class="aura-veh-form-col" style="flex:2 1 200px;">
                    <label for="trip-vehicle_id"><strong><?php esc_html_e( 'Vehículo *', 'aura-suite' ); ?></strong></label>
                    <select id="trip-vehicle_id" name="vehicle_id" required>
                        <option value=""><?php esc_html_e( '— Seleccionar —', 'aura-suite' ); ?></option>
                    </select>
                    <div id="trip-vehicle-info" class="aura-trips-vehicle-info" style="display:none;"></div>
                </div>
                <div class="aura-veh-form-col">
                    <label for="trip-area_id"><?php esc_html_e( 'Área', 'aura-suite' ); ?></label>
                    <select id="trip-area_id" name="area_id">
                        <option value="0"><?php esc_html_e( '— Sin área —', 'aura-suite' ); ?></option>
                        <?php foreach ( $areas as $area ) : ?>
                        <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="aura-veh-form-row">
                <div class="aura-veh-form-col">
                    <label for="trip-departure_datetime"><strong><?php esc_html_e( 'Fecha / hora de salida *', 'aura-suite' ); ?></strong></label>
                    <input type="datetime-local" id="trip-departure_datetime" name="departure_datetime" required>
                </div>
                <div class="aura-veh-form-col">
                    <label for="trip-departure_odometer"><strong><?php esc_html_e( 'Odómetro salida *', 'aura-suite' ); ?></strong></label>
                    <input type="number" id="trip-departure_odometer" name="departure_odometer" min="0" value="0">
                </div>
                <div class="aura-veh-form-col">
                    <label for="trip-departure_fuel"><strong><?php esc_html_e( 'Nivel de combustible salida', 'aura-suite' ); ?></strong></label>
                    <select id="trip-departure_fuel" name="departure_fuel">
                        <option value="0"><?php esc_html_e( 'Vacío', 'aura-suite' ); ?></option>
                        <option value="25"><?php esc_html_e( '1/4', 'aura-suite' ); ?></option>
                        <option value="50"><?php esc_html_e( '1/2', 'aura-suite' ); ?></option>
                        <option value="75"><?php esc_html_e( '3/4', 'aura-suite' ); ?></option>
                        <option value="100" selected><?php esc_html_e( 'Lleno', 'aura-suite' ); ?></option>
                    </select>
                    <div class="aura-trips-fuel-gauge" id="trip-fuel-gauge" data-value="100" aria-hidden="true">
                        <div class="aura-trips-fuel-gauge__head">
                            <span class="aura-trips-fuel-gauge__label"><?php esc_html_e( 'Indicador', 'aura-suite' ); ?></span>
                            <span class="aura-trips-fuel-gauge__state" id="trip-fuel-gauge-state"><?php esc_html_e( 'Lleno', 'aura-suite' ); ?></span>
                        </div>
                        <div class="aura-trips-fuel-gauge__track" role="img" aria-label="Indicador de combustible">
                            <span class="aura-trips-fuel-gauge__mark aura-trips-fuel-gauge__mark--e">E</span>
                            <div class="aura-trips-fuel-gauge__bar">
                                <span class="aura-trips-fuel-gauge__fill" id="trip-fuel-gauge-fill" style="width:100%;"></span>
                            </div>
                            <span class="aura-trips-fuel-gauge__mark aura-trips-fuel-gauge__mark--f">F</span>
                        </div>
                    </div>
                </div>
            </div>
            </div><!-- /section datos salida -->

            <!-- Sección: Tipo de salida (tarjetas visuales) -->
            <div class="aura-veh-form-section">
            <div class="aura-veh-form-section-title">
                <span class="dashicons dashicons-tag"></span>
                <?php esc_html_e( 'Tipo de salida', 'aura-suite' ); ?>
            </div>
            <div class="aura-trips-type-cards">
                <button type="button" class="aura-trips-type-card" data-type="rental">
                    <span class="dashicons dashicons-money-alt"></span>
                    <span><?php esc_html_e( 'Renta', 'aura-suite' ); ?></span>
                    <small><?php esc_html_e( 'Alquiler externo', 'aura-suite' ); ?></small>
                </button>
                <button type="button" class="aura-trips-type-card" data-type="errand">
                    <span class="dashicons dashicons-clipboard"></span>
                    <span><?php esc_html_e( 'Encargo', 'aura-suite' ); ?></span>
                    <small><?php esc_html_e( 'Diligencias / comisión', 'aura-suite' ); ?></small>
                </button>
                <button type="button" class="aura-trips-type-card" data-type="maintenance">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <span><?php esc_html_e( 'Mantenimiento', 'aura-suite' ); ?></span>
                    <small><?php esc_html_e( 'Preventivo / correctivo', 'aura-suite' ); ?></small>
                </button>
                <button type="button" class="aura-trips-type-card" data-type="other">
                    <span class="dashicons dashicons-marker"></span>
                    <span><?php esc_html_e( 'Otro', 'aura-suite' ); ?></span>
                    <small><?php esc_html_e( 'Uso general', 'aura-suite' ); ?></small>
                </button>
            </div>
            <select id="trip-trip_type" name="trip_type" style="display:none;" required>
                <option value=""></option>
                <option value="rental"><?php esc_html_e( 'Renta (externo)', 'aura-suite' ); ?></option>
                <option value="errand"><?php esc_html_e( 'Encargo', 'aura-suite' ); ?></option>
                <option value="maintenance"><?php esc_html_e( 'Mantenimiento', 'aura-suite' ); ?></option>
                <option value="other"><?php esc_html_e( 'Otro', 'aura-suite' ); ?></option>
            </select>
            </div><!-- /section tipo salida -->

            <!-- Campos tipo: RENTAL ──────────────────────── -->
            <div id="trip-section-rental" class="aura-trips-type-section aura-veh-form-section" style="display:none;">
                <div class="aura-veh-form-section-title">
                    <span class="dashicons dashicons-businessman"></span>
                    <?php esc_html_e( 'Datos del cliente (Renta)', 'aura-suite' ); ?>
                </div>
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="trip-client_name"><strong><?php esc_html_e( 'Cliente *', 'aura-suite' ); ?></strong></label>
                        <input type="text" id="trip-client_name" name="client_name" class="regular-text" maxlength="150" placeholder="<?php esc_attr_e( 'Nombre del cliente', 'aura-suite' ); ?>">
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="trip-client_phone"><?php esc_html_e( 'Teléfono', 'aura-suite' ); ?></label>
                        <input type="text" id="trip-client_phone" name="client_phone" class="regular-text" maxlength="20" placeholder="<?php esc_attr_e( 'Ej: +57 300 123 4567', 'aura-suite' ); ?>">
                    </div>
                </div>
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="trip-client_email"><?php esc_html_e( 'Email', 'aura-suite' ); ?></label>
                        <input type="email" id="trip-client_email" name="client_email" class="regular-text" maxlength="150">
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="trip-client_document"><?php esc_html_e( 'Documento', 'aura-suite' ); ?></label>
                        <input type="text" id="trip-client_document" name="client_document" class="regular-text" maxlength="50">
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="trip-rate_per_km"><?php esc_html_e( 'Tarifa por km', 'aura-suite' ); ?></label>
                        <input type="number" id="trip-rate_per_km" name="rate_per_km" min="0" step="0.01" value="0.00" class="small-text">
                    </div>
                </div>
            </div>

            <!-- Campos tipo: ERRAND / OTHER ────────────── -->
            <div id="trip-section-errand" class="aura-trips-type-section aura-veh-form-section" style="display:none;">
                <div class="aura-veh-form-section-title">
                    <span id="trip-section-errand-icon" class="dashicons dashicons-clipboard"></span>
                    <span id="trip-section-errand-title"><?php esc_html_e( 'Datos del encargo', 'aura-suite' ); ?></span>
                </div>
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="trip-responsible_name"><strong><?php esc_html_e( 'Responsable *', 'aura-suite' ); ?></strong></label>
                        <input type="text" id="trip-responsible_name" name="responsible_name" class="regular-text" maxlength="150" placeholder="<?php esc_attr_e( 'Nombre del responsable', 'aura-suite' ); ?>">
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="trip-assigned_to"><?php esc_html_e( 'Usuario asignado', 'aura-suite' ); ?></label>
                        <select id="trip-assigned_to" name="assigned_to">
                            <option value="0"><?php esc_html_e( '— Sin asignar —', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                </div>
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="trip-destination"><?php esc_html_e( 'Destino', 'aura-suite' ); ?></label>
                        <select id="trip-destination" name="destination" class="regular-text">
                            <option value=""><?php esc_html_e( '— Seleccionar destino —', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="trip-purpose"><?php esc_html_e( 'Propósito', 'aura-suite' ); ?></label>
                        <select id="trip-purpose" name="purpose" class="regular-text">
                            <option value=""><?php esc_html_e( '— Seleccionar propósito —', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                </div>
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col" style="flex:1 1 100%;">
                        <label for="trip-trip_description"><?php esc_html_e( 'Descripción', 'aura-suite' ); ?></label>
                        <textarea id="trip-trip_description" name="trip_description" rows="2" class="large-text"></textarea>
                    </div>
                </div>
            </div>

            <!-- Campos tipo: MAINTENANCE ────────────────── -->
            <div id="trip-section-maintenance" class="aura-trips-type-section aura-veh-form-section" style="display:none;">
                <div class="aura-veh-form-section-title">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e( 'Datos de mantenimiento', 'aura-suite' ); ?>
                </div>
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="trip-maint_subtype"><?php esc_html_e( 'Subtipo', 'aura-suite' ); ?></label>
                        <select id="trip-maint_subtype" name="maint_subtype">
                            <option value="preventive"><?php esc_html_e( 'Preventivo', 'aura-suite' ); ?></option>
                            <option value="corrective"><?php esc_html_e( 'Correctivo', 'aura-suite' ); ?></option>
                            <option value="inspection"><?php esc_html_e( 'Inspección', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="trip-maint_priority"><?php esc_html_e( 'Prioridad', 'aura-suite' ); ?></label>
                        <select id="trip-maint_priority" name="maint_priority">
                            <option value="low"><?php esc_html_e( 'Baja', 'aura-suite' ); ?></option>
                            <option value="medium" selected><?php esc_html_e( 'Media', 'aura-suite' ); ?></option>
                            <option value="high"><?php esc_html_e( 'Alta', 'aura-suite' ); ?></option>
                            <option value="critical"><?php esc_html_e( 'Crítico / Urgente', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="trip-maint_estimated_cost"><?php esc_html_e( 'Costo estimado', 'aura-suite' ); ?></label>
                        <input type="number" id="trip-maint_estimated_cost" name="maint_estimated_cost" min="0" step="0.01" value="0.00" class="small-text">
                    </div>
                </div>
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="trip-maint_provider"><?php esc_html_e( 'Proveedor', 'aura-suite' ); ?></label>
                        <input type="text" id="trip-maint_provider" name="maint_provider" class="regular-text" maxlength="150" placeholder="<?php esc_attr_e( 'Nombre del taller o proveedor', 'aura-suite' ); ?>">
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="trip-maint_contact"><?php esc_html_e( 'Contacto', 'aura-suite' ); ?></label>
                        <input type="text" id="trip-maint_contact" name="maint_contact" class="regular-text" maxlength="50">
                    </div>
                </div>
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col" style="flex:1 1 100%;">
                        <label for="trip-maint_description"><strong><?php esc_html_e( 'Descripción del trabajo *', 'aura-suite' ); ?></strong></label>
                        <textarea id="trip-maint_description" name="maint_description" rows="2" class="large-text"></textarea>
                    </div>
                </div>
            </div>

            <div id="aura-trips-form-error" class="notice notice-error" style="display:none;margin:10px 0;padding:8px 12px;"></div>
        </div><!-- .modal-body -->

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-trips-form-submit" class="button button-primary">
                <?php esc_html_e( 'Registrar Salida', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Registrar retorno (Check-in)
══════════════════════════════════════════════════════════════ -->
<div id="aura-trips-modal-checkin" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-trips-checkin-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-modal-large">

        <div class="aura-veh-modal-header">
            <h2 id="aura-trips-checkin-title"><?php esc_html_e( 'Registrar Retorno', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body">
            <input type="hidden" id="aura-checkin-trip-id" value="">
            <input type="hidden" id="aura-checkin-trip-type" value="">
            <input type="hidden" id="aura-checkin-departure-odometer" value="0">
            <input type="hidden" id="aura-checkin-rate-per-km" value="0">

            <!-- Resumen de la salida -->
            <div id="aura-checkin-summary" class="aura-trips-checkin-summary"></div>

            <div class="aura-veh-form-row">
                <div class="aura-veh-form-col">
                    <label for="checkin-return_datetime"><strong><?php esc_html_e( 'Fecha / hora de retorno *', 'aura-suite' ); ?></strong></label>
                    <input type="datetime-local" id="checkin-return_datetime" name="return_datetime" required>
                </div>
                <div class="aura-veh-form-col">
                    <label for="checkin-return_odometer"><strong><?php esc_html_e( 'Odómetro retorno *', 'aura-suite' ); ?></strong></label>
                    <input type="number" id="checkin-return_odometer" name="return_odometer" min="0" value="0">
                </div>
                <div class="aura-veh-form-col">
                    <label for="checkin-return_fuel"><strong><?php esc_html_e( 'Nivel de combustible retorno', 'aura-suite' ); ?></strong></label>
                    <select id="checkin-return_fuel" name="return_fuel">
                        <option value=""><?php esc_html_e( '— Seleccionar —', 'aura-suite' ); ?></option>
                        <option value="0"><?php esc_html_e( 'Vacío', 'aura-suite' ); ?></option>
                        <option value="25"><?php esc_html_e( '1/4', 'aura-suite' ); ?></option>
                        <option value="50"><?php esc_html_e( '1/2', 'aura-suite' ); ?></option>
                        <option value="75"><?php esc_html_e( '3/4', 'aura-suite' ); ?></option>
                        <option value="100"><?php esc_html_e( 'Lleno', 'aura-suite' ); ?></option>
                    </select>
                    <div class="aura-trips-fuel-gauge" id="checkin-fuel-gauge" data-value="0" aria-hidden="true">
                        <div class="aura-trips-fuel-gauge__head">
                            <span class="aura-trips-fuel-gauge__label"><?php esc_html_e( 'Indicador', 'aura-suite' ); ?></span>
                            <span class="aura-trips-fuel-gauge__state" id="checkin-fuel-gauge-state"><?php esc_html_e( '—', 'aura-suite' ); ?></span>
                        </div>
                        <div class="aura-trips-fuel-gauge__track" role="img" aria-label="Indicador de combustible">
                            <span class="aura-trips-fuel-gauge__mark aura-trips-fuel-gauge__mark--e">E</span>
                            <div class="aura-trips-fuel-gauge__bar">
                                <span class="aura-trips-fuel-gauge__fill" id="checkin-fuel-gauge-fill" style="width:0%;"></span>
                            </div>
                            <span class="aura-trips-fuel-gauge__mark aura-trips-fuel-gauge__mark--f">F</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview KM + total (para rental) -->
            <div id="aura-checkin-preview" style="background:#f0f7ff;border:1px solid #2271b1;border-radius:4px;padding:10px 14px;margin:8px 0;display:none;">
                <strong><?php esc_html_e( 'Preview:', 'aura-suite' ); ?></strong>
                <?php esc_html_e( 'KM recorridos:', 'aura-suite' ); ?> <span id="preview-km">—</span> &nbsp;|&nbsp;
                <?php esc_html_e( 'Total:', 'aura-suite' ); ?> <span id="preview-total">—</span>
            </div>

            <!-- Campos adicionales para rental -->
            <div id="checkin-section-rental" style="display:none;">
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="checkin-additional_charges"><?php esc_html_e( 'Cargos adicionales', 'aura-suite' ); ?></label>
                        <input type="number" id="checkin-additional_charges" name="additional_charges" min="0" step="0.01" value="0.00" class="small-text">
                    </div>
                    <div class="aura-veh-form-col">
                        <label for="checkin-discounts"><?php esc_html_e( 'Descuentos', 'aura-suite' ); ?></label>
                        <input type="number" id="checkin-discounts" name="discounts" min="0" step="0.01" value="0.00" class="small-text">
                    </div>
                </div>
            </div>

            <!-- Campos adicionales para mantenimiento -->
            <div id="checkin-section-maintenance" style="display:none;">
                <div class="aura-veh-form-row">
                    <div class="aura-veh-form-col">
                        <label for="checkin-maint_actual_cost"><?php esc_html_e( 'Costo real', 'aura-suite' ); ?></label>
                        <input type="number" id="checkin-maint_actual_cost" name="maint_actual_cost" min="0" step="0.01" value="0.00" class="small-text">
                    </div>
                    <div class="aura-veh-form-col">
                        <div style="display:flex;align-items:baseline;gap:6px;">
                            <label for="checkin-next_service_interval_km" style="margin:0;"><strong><?php esc_html_e( 'Próximo servicio por km', 'aura-suite' ); ?></strong></label>
                            <button type="button" class="aura-tooltip-trigger" style="margin:0;" aria-label="Ayuda">?
                                <span class="aura-tooltip"><?php esc_html_e( 'Indique cada cuántos km se debe realizar el próximo servicio. Se suma al odómetro de retorno ingresado arriba.', 'aura-suite' ); ?></span>
                            </button>
                        </div>
                        <input type="number" id="checkin-next_service_interval_km" name="next_service_interval_km" min="0" step="1" value="0" class="small-text">
                        <!-- Cálculo dinámico de próximo servicio -->
                        <!-- Cálculo dinámico de próximo servicio con tooltip mejorado -->
                        <div class="aura-checkin-next-service-calc" id="checkin-next-service-calc" style="display:none;">
                            <div class="aura-checkin-next-service-calc__row">
                                <div class="aura-checkin-next-service-calc__item">
                                    <label><?php esc_html_e( 'Odómetro retorno', 'aura-suite' ); ?></label>
                                    <span id="calc-odometer-value" class="aura-calc-value">—</span>
                                </div>
                                <div class="aura-checkin-next-service-calc__op">+</div>
                                <div class="aura-checkin-next-service-calc__item">
                                    <label><?php esc_html_e( 'Intervalo km', 'aura-suite' ); ?></label>
                                    <span id="calc-interval-value" class="aura-calc-value">—</span>
                                </div>
                                <div class="aura-checkin-next-service-calc__op">=</div>
                                <div class="aura-checkin-next-service-calc__result">
                                    <div class="aura-checkin-next-service-calc__result-label"><?php esc_html_e( 'PRÓXIMO', 'aura-suite' ); ?></div>
                                    <span id="calc-result-value" class="aura-calc-result">—</span>
                                </div>
                            </div>
                            <!-- Tooltip de información -->
                            <div class="aura-checkin-next-service-tooltip" title="<?php esc_attr_e( 'Próximo servicio estimado basado en odómetro', 'aura-suite' ); ?>">
                                <span class="dashicons dashicons-info-outline"></span>
                                <div class="aura-tooltip-content">
                                    <strong><?php esc_html_e( 'Próximo servicio', 'aura-suite' ); ?></strong><br>
                                    <span id="tooltip-next-km">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="aura-veh-form-col" style="flex:2 1 300px;">
                        <label for="checkin-maint_completion_notes"><?php esc_html_e( 'Notas de finalización', 'aura-suite' ); ?></label>
                        <textarea id="checkin-maint_completion_notes" rows="2" class="large-text"></textarea>
                    </div>
                </div>
            </div>

            <!-- Gastos para errand / maintenance / other -->
            <div id="checkin-section-expenses" style="display:none;">
                <hr>
                <p class="description"><strong><?php esc_html_e( 'Gastos del viaje', 'aura-suite' ); ?></strong></p>
                <div id="checkin-expenses-lines"></div>
                <button type="button" id="checkin-add-expense" class="button button-small">
                    + <?php esc_html_e( 'Agregar gasto', 'aura-suite' ); ?>
                </button>
                <div class="aura-veh-form-row" style="margin-top:8px;">
                    <div class="aura-veh-form-col">
                        <label><?php esc_html_e( 'Total gastos:', 'aura-suite' ); ?> <strong id="checkin-total-expenses">0.00</strong></label>
                    </div>
                </div>
            </div>

            <div id="aura-checkin-error" class="notice notice-error" style="display:none;margin:10px 0;padding:8px 12px;"></div>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-checkin-submit" class="button button-primary">
                <?php esc_html_e( 'Registrar Retorno', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Cancelar salida
══════════════════════════════════════════════════════════════ -->
<div id="aura-trips-modal-cancel" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-trips-cancel-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-modal-sm">

        <div class="aura-veh-modal-header">
            <h2 id="aura-trips-cancel-title"><?php esc_html_e( 'Cancelar Salida', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body">
            <input type="hidden" id="aura-cancel-trip-id" value="">
            <div class="aura-veh-form-row">
                <div class="aura-veh-form-col" style="flex:1 1 100%;">
                    <label for="aura-cancel-reason"><strong><?php esc_html_e( 'Motivo de cancelación *', 'aura-suite' ); ?></strong></label>
                    <textarea id="aura-cancel-reason" rows="3" class="large-text"
                              placeholder="<?php esc_attr_e( 'Describa el motivo…', 'aura-suite' ); ?>"></textarea>
                </div>
            </div>
            <div id="aura-cancel-error" class="notice notice-error" style="display:none;margin:8px 0;padding:8px 12px;"></div>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Volver', 'aura-suite' ); ?></button>
            <button type="button" id="aura-cancel-submit" class="button button-link-delete">
                <?php esc_html_e( 'Cancelar Salida', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Eliminar salida (confirmar)
══════════════════════════════════════════════════════════════ -->
<div id="aura-trips-modal-delete" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-trips-delete-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-modal-sm">

        <div class="aura-veh-modal-header">
            <h2 id="aura-trips-delete-title"><?php esc_html_e( 'Confirmar eliminación', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body">
            <input type="hidden" id="aura-delete-trip-id" value="">
            <p><?php esc_html_e( '¿Eliminar esta salida? Esta acción no se puede deshacer.', 'aura-suite' ); ?></p>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="aura-delete-trip-confirm" class="button button-link-delete">
                <?php esc_html_e( 'Eliminar', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Detalle de la salida (solo lectura)
══════════════════════════════════════════════════════════════ -->
<div id="aura-trips-modal-detail" class="aura-veh-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-trips-detail-title">
    <div class="aura-veh-modal-overlay"></div>
    <div class="aura-veh-modal-content aura-veh-modal-large">

        <div class="aura-veh-modal-header">
            <h2 id="aura-trips-detail-title"><?php esc_html_e( 'Detalle de Salida', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-veh-modal-close dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>"></button>
        </div>

        <div class="aura-veh-modal-body">
            <div id="aura-trips-detail-content"></div>
        </div>

        <div class="aura-veh-modal-footer">
            <button type="button" class="button aura-veh-modal-close"><?php esc_html_e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>
    </div>
</div>

<script>
(function($) {
    'use strict';

    // Tarjetas de tipo de salida → actualiza select oculto
    $(document).on('click', '.aura-trips-type-card', function() {
        var type = $(this).data('type');
        $('.aura-trips-type-card').removeClass('is-active');
        $(this).addClass('is-active');
        $('#trip-trip_type').val(type).trigger('change');
    });

})(jQuery);
</script>
