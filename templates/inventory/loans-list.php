<?php
/**
 * Template: Préstamos de Equipos (Checkout / Checkin)
 *
 * Ruta: admin.php?page=aura-inventory-loans
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$can_checkout    = current_user_can( 'aura_inventory_checkout' )    || current_user_can( 'manage_options' );
$can_checkin     = current_user_can( 'aura_inventory_checkin' )     || current_user_can( 'manage_options' );
$can_loan_edit   = current_user_can( 'aura_inventory_loan_edit' )   || current_user_can( 'manage_options' );
$can_loan_delete = current_user_can( 'aura_inventory_loan_delete' ) || current_user_can( 'manage_options' );

if ( ! $can_checkout && ! $can_checkin && ! current_user_can( 'aura_inventory_view_all' ) ) {
    wp_die( __( 'No tienes permisos para acceder al módulo de préstamos.', 'aura-suite' ) );
}

$today         = current_time( 'Y-m-d' );
$inv_settings  = class_exists( 'Aura_Inventory_Categories' ) ? Aura_Inventory_Categories::get_settings() : [];
$loan_max_days = max( 1, intval( $inv_settings['loan_max_days'] ?? 30 ) );
$default_return = date( 'Y-m-d', strtotime( $today . ' +' . $loan_max_days . ' days' ) );
$nonce         = wp_create_nonce( 'aura_inventory_nonce' );
$kpis  = class_exists( 'Aura_Inventory_Loans' )
    ? Aura_Inventory_Loans::get_kpis()
    : [ 'active' => 0, 'overdue' => 0, 'returned_month' => 0, 'total' => 0 ];

// Encolado seguro sin duplicar el handle global de aura-business-suite.php
if ( ! wp_style_is( 'aura-inventory-loans', 'enqueued' ) && ! wp_style_is( 'aura-inv-loans', 'enqueued' ) ) {
    $css_path = AURA_PLUGIN_DIR . 'assets/css/inventory-loans.css';
    wp_enqueue_style(
        'aura-inv-loans',
        AURA_PLUGIN_URL . 'assets/css/inventory-loans.css',
        [ 'aura-design-system' ],
        file_exists( $css_path ) ? filemtime( $css_path ) : AURA_VERSION
    );
}
if ( ! wp_script_is( 'aura-inventory-loans', 'enqueued' ) ) {
    $js_path = AURA_PLUGIN_DIR . 'assets/js/inventory-loans.js';
    wp_enqueue_script(
        'aura-inventory-loans',
        AURA_PLUGIN_URL . 'assets/js/inventory-loans.js',
        [ 'jquery', 'aura-ui-core' ],
        file_exists( $js_path ) ? filemtime( $js_path ) : AURA_VERSION,
        true
    );
}

$header_actions = [];
if ( $can_checkout ) {
    $header_actions[] = [
        'label' => __( 'Nuevo Préstamo', 'aura-suite' ),
        'url'   => '#',
        'icon'  => 'dashicons-share',
        'class' => 'btn btn-shimmer btn-indigo btn-lift',
        'attr'  => 'id="js-open-checkout"',
    ];
}
$header_actions[] = [
    'label' => __( 'Catálogo de Equipos', 'aura-suite' ),
    'url'   => admin_url( 'admin.php?page=aura-inventory-equipment' ),
    'icon'  => 'dashicons-hammer',
    'class' => 'btn btn-glass btn-lift',
];
$header_actions[] = [
    'label' => __( 'Mantenimientos', 'aura-suite' ),
    'url'   => admin_url( 'admin.php?page=aura-inventory-maintenance' ),
    'icon'  => 'dashicons-admin-tools',
    'class' => 'btn btn-glass btn-lift',
];
?>

<div class="aura-app-wrapper aura-inventory-loans">
    <div class="wrap aura-app-context">

        <?php
        if ( class_exists( 'Aura_UI' ) ) {
            Aura_UI::render_page_header( [
                'title'    => __( 'Préstamos de Equipos (Checkout / Checkin)', 'aura-suite' ),
                'subtitle' => __( 'Controla entregas, fechas comprometidas, responsables internos o externos y registro de devoluciones.', 'aura-suite' ),
                'icon'     => 'dashicons-migrate',
                'actions'  => $header_actions,
            ] );

            Aura_UI::render_stats_grid( [
                [
                    'label'   => __( 'Préstamos Activos', 'aura-suite' ),
                    'value'   => number_format_i18n( $kpis['active'] ),
                    'variant' => 'balance',
                    'icon'    => 'dashicons-share',
                    'tooltip' => __( 'Equipos que actualmente se encuentran asignados o fuera de bodega sin devolución registrada', 'aura-suite' ),
                ],
                [
                    'label'   => __( 'Préstamos Vencidos', 'aura-suite' ),
                    'value'   => number_format_i18n( $kpis['overdue'] ),
                    'variant' => 'expense',
                    'icon'    => 'dashicons-warning',
                    'tooltip' => __( 'Préstamos cuya fecha esperada de devolución ya pasó y siguen pendientes de entrega', 'aura-suite' ),
                ],
                [
                    'label'   => __( 'Devueltos este Mes', 'aura-suite' ),
                    'value'   => number_format_i18n( $kpis['returned_month'] ),
                    'variant' => 'income',
                    'icon'    => 'dashicons-yes-alt',
                    'tooltip' => __( 'Equipos reintegrados satisfactoriamente durante el mes en curso', 'aura-suite' ),
                ],
                [
                    'label'   => __( 'Total Histórico', 'aura-suite' ),
                    'value'   => number_format_i18n( $kpis['total'] ),
                    'variant' => 'count',
                    'icon'    => 'dashicons-clipboard',
                    'tooltip' => __( 'Total de movimientos de préstamo registrados en la base de datos', 'aura-suite' ),
                ],
            ] );
        }
        ?>

        <!-- Barra de filtros Glassmorphic BS 5.3 -->
        <div class="aura-glass-card aura-filters-card">
            <div class="aura-loan-filters-grid">
                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Buscar por equipo o nombre del prestatario', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-search"></span></span>
                    <input type="search" id="js-loan-search" class="form-control"
                           placeholder="<?php esc_attr_e( 'Buscar equipo o prestatario…', 'aura-suite' ); ?>">
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Filtrar por estado del préstamo', 'aura-suite' ); ?>">
                    <span class="input-group-text"><span class="dashicons dashicons-flag"></span></span>
                    <select id="js-loan-status" class="form-select">
                        <option value=""><?php _e( 'Todos los estados', 'aura-suite' ); ?></option>
                        <option value="active"><?php _e( 'Activos', 'aura-suite' ); ?></option>
                        <option value="overdue"><?php _e( 'Vencidos', 'aura-suite' ); ?></option>
                        <option value="returned"><?php _e( 'Devueltos', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Fecha de salida desde', 'aura-suite' ); ?>">
                    <span class="input-group-text"><?php _e( 'Desde', 'aura-suite' ); ?></span>
                    <input type="date" id="js-loan-date-from" class="form-control">
                </div>

                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Fecha de salida hasta', 'aura-suite' ); ?>">
                    <span class="input-group-text"><?php _e( 'Hasta', 'aura-suite' ); ?></span>
                    <input type="date" id="js-loan-date-to" class="form-control">
                </div>

                <div class="aura-inv-filters-actions">
                    <button type="button" id="js-loan-filter-btn" class="btn btn-indigo btn-lift" data-tooltip="<?php esc_attr_e( 'Aplicar filtros de búsqueda', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-filter"></span>
                        <?php _e( 'Filtrar', 'aura-suite' ); ?>
                    </button>
                    <button type="button" id="js-loan-clear-btn" class="btn btn-glass" data-tooltip="<?php esc_attr_e( 'Restablecer filtros', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php _e( 'Limpiar', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla DataTables de Préstamos -->
        <div class="aura-glass-card aura-dt-wrapper aura-table-responsive-wrap">
            <table id="js-loans-table" class="aura-table display aura-inv-responsive-table" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:40px;">
                            <span class="aura-th-flex">+
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Haz clic en + para ver ficha completa del préstamo y estado de entrega', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th style="width:68px;">
                            <span class="aura-th-flex"><?php _e( 'Foto', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Pasa el cursor encima para ver la fotografía ampliada sin recortes', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th>
                            <span class="aura-th-flex"><?php _e( 'Equipo', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Equipo entregado en préstamo y su marca o código interno', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th>
                            <span class="aura-th-flex"><?php _e( 'Prestatario', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Responsable, usuario del sistema o contacto externo con enlace WhatsApp', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php _e( 'Proyecto / Motivo', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Actividad, sede u obra donde se utiliza el equipo', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php _e( 'Fecha salida', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Fecha en la que el activo salió de inventario', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th class="aura-inv-col-secondary">
                            <span class="aura-th-flex"><?php _e( 'Devolución esperada', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Fecha límite de retorno o fecha real si ya fue devuelto', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th>
                            <span class="aura-th-flex"><?php _e( 'Estado', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Activo, Vencido (fuera de fecha) o Devuelto', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                        <th style="width:140px;text-align:center;">
                            <span class="aura-th-flex"><?php _e( 'Acciones', 'aura-suite' ); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Registrar devolución, ver historial del equipo, editar o eliminar préstamo', 'aura-suite' ); ?>">?</span>
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <?php if ( class_exists( 'Aura_UI' ) && method_exists( 'Aura_UI', 'render_footer' ) ) : ?>
            <?php Aura_UI::render_footer(); ?>
        <?php else : ?>
            <div class="adp-footer">
                <span>Aura Business Suite · <?php _e( 'Módulo de Préstamos e Inventario', 'aura-suite' ); ?></span>
                <span>Aura Design System</span>
            </div>
        <?php endif; ?>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->


<!-- ══════════════════════════════════════════════════════════
     MODAL — CHECKOUT (Nuevo Préstamo — Item 26 + 3 Pasos UX)
═══════════════════════════════════════════════════════════════ -->
<div id="js-modal-checkout" class="aura-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="js-modal-checkout-title" style="display:none;">
    <div class="aura-modal-container" style="max-width:740px;">
        <div class="aura-modal-header">
            <h3 id="js-modal-checkout-title" class="aura-modal-title">
                <span class="dashicons dashicons-share"></span>
                <?php _e( 'Registrar Préstamo (Checkout)', 'aura-suite' ); ?>
            </h3>
            <button type="button" class="aura-modal-close aura-loan-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>

        <form id="js-form-checkout" novalidate style="display:flex;flex-direction:column;min-height:0;flex:1;">
            <div class="aura-modal-body aura-loan-checkout-body">

                <!-- ══ PASO 1: EQUIPO A PRESTAR ════════════════════════════════════ -->
                <div class="aura-loan-step-card">
                    <div class="aura-loan-step-hdr">
                        <div class="aura-loan-step-title">
                            <span class="aura-loan-step-num">1</span>
                            <strong><?php _e( 'Equipo disponible', 'aura-suite' ); ?></strong>
                            <span class="aura-loan-step-req">* <?php _e( 'requerido', 'aura-suite' ); ?></span>
                        </div>
                        <button type="button" id="aura-inv-btn-equip-picker" class="btn btn-indigo btn-sm"
                                data-tooltip="<?php esc_attr_e( 'Abrir Catálogo Rápido con búsqueda en vivo y filtros por categoría', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-grid-view"></span>
                            <?php _e( 'Catálogo Rápido de Equipos', 'aura-suite' ); ?>
                        </button>
                    </div>

                    <div class="aura-loan-equip-mode-tabs">
                        <button type="button" class="aura-loan-equip-tab active" data-mode="search">
                            <span class="dashicons dashicons-search"></span>
                            <?php _e( 'Búsqueda rápida', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="aura-loan-equip-tab" data-mode="list">
                            <span class="dashicons dashicons-list-view"></span>
                            <?php _e( 'Lista desplegable', 'aura-suite' ); ?>
                        </button>
                    </div>

                    <input type="hidden" id="co-equipment-id" name="equipment_id" required>

                    <div id="co-equip-mode-search" class="aura-loan-equip-search-wrap">
                        <div class="input-group">
                            <span class="input-group-text"><span class="dashicons dashicons-search"></span></span>
                            <input type="text" id="co-equipment-search" class="form-control" autocomplete="off"
                                   placeholder="<?php esc_attr_e( 'Escribe nombre, marca o código interno (o usa Catálogo Rápido)…', 'aura-suite' ); ?>">
                        </div>
                        <div id="co-equip-results" class="aura-loan-equip-results" style="display:none;"></div>
                    </div>

                    <div id="co-equip-mode-list" style="display:none;">
                        <div id="co-equip-list-loading" class="aura-loan-equip-list-loading" style="display:none;">
                            <span class="spinner is-active" style="float:none;margin:0;"></span>
                            <span><?php _e( 'Cargando equipos disponibles…', 'aura-suite' ); ?></span>
                        </div>
                        <div id="co-equip-list-select-wrap" class="input-group" style="display:none;">
                            <select id="co-equip-list-select" class="form-select"></select>
                            <button type="button" id="js-btn-select-listed-equip" class="btn btn-indigo"><?php _e( 'Seleccionar', 'aura-suite' ); ?></button>
                        </div>
                        <div id="co-equip-list-items"></div>
                    </div>

                    <div id="co-equip-selected" class="aura-loan-selected-equip" style="display:none;"></div>
                </div>

                <!-- ══ PASO 2: ¿QUIÉN RECIBE EL EQUIPO? (3 PESTAÑAS EXCLUSIVAS) ════ -->
                <div class="aura-loan-step-card">
                    <div class="aura-loan-step-hdr">
                        <div class="aura-loan-step-title">
                            <span class="aura-loan-step-num">2</span>
                            <strong><?php _e( '¿Quién recibe el equipo?', 'aura-suite' ); ?></strong>
                            <span class="aura-help-icon" tabindex="0"
                                  data-tooltip="<?php esc_attr_e( 'Elige una sola modalidad según quién recibe el activo: Usuario interno (con cuenta WP), Contacto guardado en Aura CRM, o Persona Externa / Tercero.', 'aura-suite' ); ?>">?</span>
                        </div>
                    </div>

                    <input type="hidden" id="co-borrower-mode" value="user">

                    <!-- 3 Pestañas exclusivas con tooltips explicativos -->
                    <div class="aura-loan-borrower-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Tipo de prestatario', 'aura-suite' ); ?>">
                        <button type="button" class="aura-loan-btab active" data-borrower-mode="user" role="tab" aria-selected="true"
                                data-tooltip="<?php esc_attr_e( 'Usa esta opción para empleados, técnicos o administradores con usuario en WordPress. Guarda su cuenta interna y muestra su foto/avatar.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-admin-users"></span>
                            <span><?php _e( 'Usuario del sistema', 'aura-suite' ); ?></span>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Personal interno con cuenta WordPress (guarda borrowed_by_user_id y muestra su avatar).', 'aura-suite' ); ?>">?</span>
                        </button>
                        <button type="button" class="aura-loan-btab" data-borrower-mode="contact" role="tab" aria-selected="false"
                                data-tooltip="<?php esc_attr_e( 'Usa esta opción para estudiantes, docentes o personas registradas en Contactos de Aura CRM. Vincula su ficha, categoría y teléfono para WhatsApp.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-id-alt"></span>
                            <span><?php _e( 'Contacto Aura', 'aura-suite' ); ?></span>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Directorio Aura CRM: vincula el contact_id, autocompleta teléfono WhatsApp y muestra avatar/categoría.', 'aura-suite' ); ?>">?</span>
                        </button>
                        <button type="button" class="aura-loan-btab" data-borrower-mode="external" role="tab" aria-selected="false"
                                data-tooltip="<?php esc_attr_e( 'Usa esta opción para contratistas, técnicos externos, visitantes o empresas (Terceros) sin cuenta ni ficha CRM.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-groups"></span>
                            <span><?php _e( 'Externo / Manual', 'aura-suite' ); ?></span>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Texto libre o Directorio de Terceros (empresas/contratistas) sin usuario WordPress ni CRM.', 'aura-suite' ); ?>">?</span>
                        </button>
                    </div>

                    <!-- Resumen activo de persona seleccionada -->
                    <div id="aura-inv-co-borrower-badge" class="aura-loan-borrower-summary" style="display:none;"></div>

                    <!-- PANEL A: USUARIO DEL SISTEMA -->
                    <div id="co-borrower-panel-user" class="aura-loan-bpanel">
                        <p class="aura-loan-mode-hint">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e( 'Asigna el equipo a un usuario WordPress del equipo interno; el préstamo mostrará su nombre y avatar del perfil.', 'aura-suite' ); ?>
                        </p>
                        <div class="aura-loan-fields-2col">
                            <div class="aura-loan-field-group" style="margin-bottom:0;">
                                <label for="co-borrower-user" class="aura-loan-label">
                                    <?php _e( 'Seleccionar usuario', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Puedes elegirlo de la lista rápida o abrir el Modal de Usuarios con filtro por rol.', 'aura-suite' ); ?>">?</span>
                                </label>
                                <div class="input-group">
                                    <select id="co-borrower-user" name="borrowed_by_user_id" class="form-select">
                                        <option value="<?php echo get_current_user_id(); ?>">
                                            <?php echo esc_html( wp_get_current_user()->display_name ); ?> (<?php _e( 'yo', 'aura-suite' ); ?>)
                                        </option>
                                        <?php
                                        $users = Aura_Roles_Manager::get_aura_users( [ 'fields' => [ 'ID', 'display_name' ] ] );
                                        foreach ( $users as $u ) {
                                            if ( (int) $u->ID === get_current_user_id() ) continue;
                                            echo '<option value="' . esc_attr( $u->ID ) . '">' . esc_html( $u->display_name ) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <button type="button" id="aura-inv-btn-borrower-explorer" class="btn btn-glass aura-inv-trigger-user-explorer"
                                            data-tooltip="<?php esc_attr_e( 'Abrir modal de todos los usuarios del sistema con filtro por rol', 'aura-suite' ); ?>">
                                        <span class="dashicons dashicons-admin-users"></span>
                                        <?php _e( 'Explorar Usuarios', 'aura-suite' ); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="aura-loan-field-group" style="margin-bottom:0;">
                                <label for="co-borrower-user-phone" class="aura-loan-label">
                                    <?php _e( 'WhatsApp de aviso (opcional)', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Si ingresas un teléfono, recibirá confirmación y recordatorios de entrega por WhatsApp.', 'aura-suite' ); ?>">?</span>
                                </label>
                                <input type="tel" id="co-borrower-user-phone" class="form-control"
                                       placeholder="<?php esc_attr_e( 'Ej: +56 9 0000 0000 (opcional)', 'aura-suite' ); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- PANEL B: CONTACTO AURA (CRM) -->
                    <div id="co-borrower-panel-contact" class="aura-loan-bpanel" style="display:none;">
                        <p class="aura-loan-mode-hint">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e( 'Vincula el préstamo a una ficha de Contactos de Aura para heredar su categoría CRM, avatar y teléfono WhatsApp.', 'aura-suite' ); ?>
                        </p>
                        <div class="aura-loan-field-group" style="margin-bottom:10px;">
                            <div class="aura-loan-sub-header">
                                <label class="aura-loan-label" style="margin-bottom:0;">
                                    <?php _e( 'Buscar o explorar Contacto de Aura', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Al elegir un contacto se guarda su ID relacional (contact_id) junto con su nombre y teléfono.', 'aura-suite' ); ?>">?</span>
                                </label>
                                <button type="button" id="aura-inv-btn-contact-explorer" class="btn btn-indigo btn-xs aura-inv-trigger-contact-explorer"
                                        data-tooltip="<?php esc_attr_e( 'Abrir lista visual de Contactos Aura CRM con filtro por categoría', 'aura-suite' ); ?>">
                                    <span class="dashicons dashicons-id-alt"></span>
                                    <?php _e( 'Explorar Contactos Aura', 'aura-suite' ); ?>
                                </button>
                            </div>
                            <?php
                            if ( class_exists( 'Aura_Contact_Selector' ) ) {
                                echo Aura_Contact_Selector::render( [
                                    'id'          => 'co-contact-selector',
                                    'name'        => 'contact_id',
                                    'label'       => '',
                                    'placeholder' => __( 'Escribe nombre, teléfono o correo del contacto…', 'aura-suite' ),
                                    'sync_fields' => [
                                        'name'    => '#co-borrower-name',
                                        'phone'   => '#co-borrower-phone',
                                        'user_id' => '#co-borrower-user',
                                    ],
                                ] );
                            }
                            ?>
                        </div>
                        <div class="aura-loan-fields-2col" style="margin-bottom:0;">
                            <div class="aura-loan-field-group" style="margin-bottom:0;">
                                <label for="co-borrower-name" class="aura-loan-label">
                                    <?php _e( 'Nombre sincronizado', 'aura-suite' ); ?>
                                </label>
                                <input type="text" id="co-borrower-name" class="form-control"
                                       placeholder="<?php esc_attr_e( 'Se rellena al seleccionar el contacto…', 'aura-suite' ); ?>">
                            </div>
                            <div class="aura-loan-field-group" style="margin-bottom:0;">
                                <label for="co-borrower-phone" class="aura-loan-label">
                                    <?php _e( 'WhatsApp / Teléfono', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Teléfono usado para enviar alertas de salida y vencimiento por WhatsApp.', 'aura-suite' ); ?>">?</span>
                                </label>
                                <input type="tel" id="co-borrower-phone" class="form-control"
                                       placeholder="<?php esc_attr_e( 'Se rellena automáticamente si el contacto tiene teléfono', 'aura-suite' ); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- PANEL C: EXTERNO / MANUAL / TERCEROS -->
                    <div id="co-borrower-panel-external" class="aura-loan-bpanel" style="display:none;">
                        <p class="aura-loan-mode-hint">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e( 'Ideal para contratistas, empresas de Terceros o personas externas puntuales que no están registradas en Contactos ni como usuarios.', 'aura-suite' ); ?>
                        </p>
                        <div class="aura-loan-sub-header">
                            <label class="aura-loan-label" style="margin-bottom:0;">
                                <?php _e( 'Datos de la persona o entidad externa', 'aura-suite' ); ?>
                            </label>
                            <button type="button" id="aura-inv-btn-tp-borrower-explorer" class="btn btn-glass btn-xs aura-inv-trigger-tp-explorer"
                                    data-tooltip="<?php esc_attr_e( 'Buscar empresas, fundaciones o personas en el Directorio de Terceros', 'aura-suite' ); ?>">
                                <span class="dashicons dashicons-groups"></span>
                                <?php _e( 'Explorar Directorio de Terceros', 'aura-suite' ); ?>
                            </button>
                        </div>
                        <div class="aura-loan-fields-2col" style="margin-bottom:0;">
                            <div class="aura-loan-field-group" style="margin-bottom:0;">
                                <label for="co-borrower-ext-name" class="aura-loan-label">
                                    <?php _e( 'Nombre completo / Empresa', 'aura-suite' ); ?>
                                </label>
                                <input type="text" id="co-borrower-ext-name" class="form-control"
                                       placeholder="<?php esc_attr_e( 'Ej: Juan Pérez · Productora Invitada…', 'aura-suite' ); ?>">
                            </div>
                            <div class="aura-loan-field-group" style="margin-bottom:0;">
                                <label for="co-borrower-ext-phone" class="aura-loan-label">
                                    <?php _e( 'WhatsApp / Teléfono (opcional)', 'aura-suite' ); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Permite enviar notificación automática por WhatsApp de préstamo y vencimiento.', 'aura-suite' ); ?>">?</span>
                                </label>
                                <input type="tel" id="co-borrower-ext-phone" class="form-control"
                                       placeholder="<?php esc_attr_e( 'Ej: +56 9 0000 0000', 'aura-suite' ); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ PASO 3: FECHAS, MOTIVO Y ESTADO DE SALIDA ══════════════════ -->
                <div class="aura-loan-step-card" style="margin-bottom:2px;">
                    <div class="aura-loan-step-hdr">
                        <div class="aura-loan-step-title">
                            <span class="aura-loan-step-num">3</span>
                            <strong><?php _e( 'Fechas, motivo y estado al salir', 'aura-suite' ); ?></strong>
                        </div>
                        <div class="aura-loan-duration-chips">
                            <span class="aura-loan-dur-lbl"><?php _e( 'Plazo rápido:', 'aura-suite' ); ?></span>
                            <button type="button" class="aura-loan-dur-chip" data-days="3">+3d</button>
                            <button type="button" class="aura-loan-dur-chip" data-days="7">+7d</button>
                            <button type="button" class="aura-loan-dur-chip" data-days="15">+15d</button>
                            <button type="button" class="aura-loan-dur-chip is-default" data-days="<?php echo esc_attr( $loan_max_days ); ?>">+<?php echo esc_html( $loan_max_days ); ?>d</button>
                        </div>
                    </div>

                    <div class="aura-loan-fields-2col">
                        <div class="aura-loan-field-group">
                            <label for="co-loan-date" class="aura-loan-label required">
                                <?php _e( 'Fecha de salida', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                            </label>
                            <input type="date" id="co-loan-date" name="loan_date" class="form-control" value="<?php echo esc_attr( $today ); ?>" required>
                        </div>
                        <div class="aura-loan-field-group">
                            <label for="co-expected-return" class="aura-loan-label required">
                                <?php _e( 'Devolución esperada', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                            </label>
                            <input type="date" id="co-expected-return" name="expected_return_date" class="form-control" value="<?php echo esc_attr( $default_return ); ?>" min="<?php echo esc_attr( $today ); ?>" required>
                        </div>
                    </div>

                    <div class="aura-loan-fields-2col" style="margin-bottom:0;">
                        <div class="aura-loan-field-group" style="margin-bottom:0;">
                            <label for="co-project" class="aura-loan-label">
                                <?php _e( 'Proyecto / Motivo', 'aura-suite' ); ?>
                            </label>
                            <input type="text" id="co-project" name="project" class="form-control"
                                   placeholder="<?php esc_attr_e( 'Ej: Campamento, Mantenimiento sede norte, Evento…', 'aura-suite' ); ?>">
                        </div>

                        <div class="aura-loan-field-group" style="margin-bottom:0;">
                            <label class="aura-loan-label">
                                <?php _e( 'Estado del equipo al salir', 'aura-suite' ); ?>
                            </label>
                            <div class="aura-loan-radio-group">
                                <?php
                                $states_out = [
                                    'good' => __( 'Bueno',      'aura-suite' ),
                                    'fair' => __( 'Regular',    'aura-suite' ),
                                    'poor' => __( 'Deficiente', 'aura-suite' ),
                                ];
                                foreach ( $states_out as $val => $label ) :
                                ?>
                                <label class="aura-loan-radio-label">
                                    <input type="radio" name="equipment_state_out" value="<?php echo esc_attr( $val ); ?>" <?php checked( $val, 'good' ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- .aura-modal-body -->

            <div class="aura-modal-footer">
                <div id="js-checkout-msg" class="aura-loan-notice" style="display:none;"></div>
                <button type="button" class="btn btn-glass aura-loan-modal-close"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
                <button type="submit" class="btn btn-shimmer btn-indigo btn-lift" id="js-checkout-submit">
                    <span class="dashicons dashicons-share"></span>
                    <?php _e( 'Registrar Préstamo', 'aura-suite' ); ?>
                </button>
            </div>
        </form>
    </div>
</div><!-- #js-modal-checkout -->


<!-- ══════════════════════════════════════════════════════════
     MODAL STACKED — EXPLORADOR DE CONTACTOS DE AURA (CRM)
═══════════════════════════════════════════════════════════════ -->
<div id="aura-inv-contact-picker-modal" class="aura-modal-overlay" data-async-modal="true" role="dialog" aria-modal="true" aria-labelledby="aura-inv-cp-title" style="display:none;z-index:1001600 !important;">
    <div class="aura-modal-container" style="max-width:860px;">
        <div class="aura-modal-header">
            <h3 id="aura-inv-cp-title" class="aura-modal-title">
                <span class="dashicons dashicons-id-alt"></span>
                <?php _e( 'Explorar Contactos de Aura CRM', 'aura-suite' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" id="aura-inv-cp-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>
        <div class="aura-modal-body">
            <div style="margin-bottom:14px;">
                <div class="aura-inv-ep-toolbar" style="margin-bottom:10px;">
                    <div class="input-group" style="flex:1;">
                        <span class="input-group-text"><span class="dashicons dashicons-search"></span></span>
                        <input type="search" id="aura-inv-cp-search" class="form-control"
                               placeholder="<?php esc_attr_e( 'Buscar contacto por nombre, teléfono, correo u organización…', 'aura-suite' ); ?>">
                    </div>
                </div>
                <div id="aura-inv-cp-chips" class="aura-equip-picker-chips"></div>
            </div>

            <div class="aura-equip-picker-list-wrap">
                <table class="aura-table aura-equip-picker-table">
                    <thead>
                        <tr>
                            <th><?php _e( 'Contacto', 'aura-suite' ); ?></th>
                            <th class="aura-inv-col-secondary"><?php _e( 'Categoría', 'aura-suite' ); ?></th>
                            <th class="aura-inv-col-secondary"><?php _e( 'Teléfono / Correo', 'aura-suite' ); ?></th>
                            <th style="width:120px;text-align:right;"><?php _e( 'Acción', 'aura-suite' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="aura-inv-cp-tbody"></tbody>
                </table>
                <div id="aura-inv-cp-empty" class="aura-inv-empty-picker" style="display:none;">
                    <span class="dashicons dashicons-id-alt"></span>
                    <p><?php _e( 'No se encontraron contactos que coincidan con tu búsqueda.', 'aura-suite' ); ?></p>
                </div>
            </div>
        </div>
        <div class="aura-modal-footer">
            <span id="aura-inv-cp-count" class="aura-inv-ep-count"></span>
            <button type="button" class="btn btn-glass" id="aura-inv-cp-cancel"><?php _e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL STACKED — CATÁLOGO RÁPIDO DE EQUIPOS DISPONIBLES
═══════════════════════════════════════════════════════════════ -->
<div id="aura-inv-equip-picker-modal" class="aura-modal-overlay" data-async-modal="true" role="dialog" aria-modal="true" aria-labelledby="aura-inv-ep-title" style="display:none;z-index:1001500 !important;">
    <div class="aura-modal-container" style="max-width:920px;">
        <div class="aura-modal-header">
            <h3 id="aura-inv-ep-title" class="aura-modal-title">
                <span class="dashicons dashicons-search"></span>
                <?php _e( 'Catálogo Rápido: Seleccionar Equipo para Préstamo', 'aura-suite' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" id="aura-inv-ep-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>
        <div class="aura-modal-body">
            <div style="margin-bottom:14px;">
                <div class="aura-inv-ep-toolbar" style="margin-bottom:10px;">
                    <div class="input-group" style="flex:1;">
                        <span class="input-group-text"><span class="dashicons dashicons-search"></span></span>
                        <input type="search" id="aura-inv-ep-search" class="form-control"
                               placeholder="<?php esc_attr_e( 'Buscar en tiempo real por nombre, marca, modelo, código interno, serie o ubicación…', 'aura-suite' ); ?>">
                    </div>
                    <select id="aura-inv-ep-category" class="form-select" style="max-width:230px;">
                        <option value=""><?php _e( 'Todas las categorías', 'aura-suite' ); ?></option>
                    </select>
                </div>
                <div id="aura-inv-ep-chips" class="aura-equip-picker-chips"></div>
            </div>

            <div class="aura-equip-picker-list-wrap">
                <table class="aura-table aura-equip-picker-table">
                    <thead>
                        <tr>
                            <th style="width:62px;text-align:center;"><?php _e( 'Foto', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Equipo / Identificación', 'aura-suite' ); ?></th>
                            <th class="aura-inv-col-secondary"><?php _e( 'Categoría', 'aura-suite' ); ?></th>
                            <th class="aura-inv-col-secondary"><?php _e( 'Ubicación', 'aura-suite' ); ?></th>
                            <th style="width:120px;text-align:right;"><?php _e( 'Acción', 'aura-suite' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="aura-inv-ep-tbody"></tbody>
                </table>
                <div id="aura-inv-ep-empty" class="aura-inv-empty-picker" style="display:none;">
                    <span class="dashicons dashicons-search"></span>
                    <p><?php _e( 'No hay equipos disponibles que coincidan con tu búsqueda.', 'aura-suite' ); ?></p>
                </div>
            </div>
        </div>
        <div class="aura-modal-footer">
            <span id="aura-inv-ep-count" class="aura-inv-ep-count"></span>
            <button type="button" class="btn btn-glass" id="aura-inv-ep-cancel"><?php _e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — CHECKIN (Devolución — Item 26 + Async Skeleton)
═══════════════════════════════════════════════════════════════ -->
<div id="js-modal-checkin" class="aura-modal-overlay" data-async-modal="true" role="dialog" aria-modal="true" aria-labelledby="js-modal-checkin-title" style="display:none;">
    <div class="aura-modal-container" style="max-width:640px;">
        <div class="aura-modal-header">
            <h3 id="js-modal-checkin-title" class="aura-modal-title">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php _e( 'Registrar Devolución (Checkin)', 'aura-suite' ); ?>
            </h3>
            <button type="button" class="aura-modal-close aura-loan-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>

        <form id="js-form-checkin" novalidate style="display:flex;flex-direction:column;min-height:0;flex:1;">
            <input type="hidden" id="ci-loan-id" name="loan_id">

            <div class="aura-modal-body">
                <div id="ci-summary" class="aura-loan-checkin-summary"></div>

                <div class="aura-loan-fields-2col">
                    <div class="aura-loan-field-group">
                        <label for="ci-return-date" class="aura-loan-label required">
                            <?php _e( 'Fecha de devolución real', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                        </label>
                        <input type="date" id="ci-return-date" name="actual_return_date" class="form-control" value="<?php echo esc_attr( $today ); ?>" required>
                    </div>
                    <div class="aura-loan-field-group">
                        <label for="ci-hours" class="aura-loan-label">
                            <?php _e( 'Horas de uso (opcional)', 'aura-suite' ); ?>
                        </label>
                        <input type="number" id="ci-hours" name="hours_used" class="form-control" min="0" step="0.5" placeholder="0.0">
                    </div>
                </div>

                <div class="aura-loan-field-group">
                    <label class="aura-loan-label required">
                        <?php _e( 'Estado del equipo al regresar', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                    </label>
                    <div class="aura-loan-radio-group">
                        <?php
                        $states_in = [
                            'good'    => __( 'Bueno',   'aura-suite' ),
                            'fair'    => __( 'Regular', 'aura-suite' ),
                            'damaged' => __( 'Dañado',  'aura-suite' ),
                        ];
                        foreach ( $states_in as $val => $label ) :
                        ?>
                        <label class="aura-loan-radio-label <?php echo $val === 'damaged' ? 'aura-loan-radio-danger' : ''; ?>">
                            <input type="radio" name="return_state" value="<?php echo esc_attr( $val ); ?>" <?php checked( $val, 'good' ); ?> required>
                            <?php echo esc_html( $label ); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div id="ci-damaged-warning" class="aura-loan-damaged-warning" style="display:none;">
                        ⚠️ <?php _e( 'Se enviará una notificación automática al administrador sobre el daño.', 'aura-suite' ); ?>
                    </div>
                </div>

                <div class="aura-loan-field-group">
                    <label class="aura-loan-checkbox-label">
                        <input type="checkbox" id="ci-req-maint" name="requires_maintenance_after" value="1">
                        <span><?php _e( '¿El equipo requiere mantenimiento después del uso?', 'aura-suite' ); ?></span>
                    </label>
                    <p class="description" style="margin:4px 0 0;">
                        <?php _e( 'Si está marcado, el equipo pasará a estado "Mantenimiento" al procesarse la devolución.', 'aura-suite' ); ?>
                    </p>
                </div>

                <div class="aura-loan-field-group" style="margin-bottom:0;">
                    <label for="ci-observations" class="aura-loan-label">
                        <?php _e( 'Observaciones de devolución', 'aura-suite' ); ?>
                    </label>
                    <textarea id="ci-observations" name="return_observations" class="form-control aura-loan-textarea" rows="3"
                              placeholder="<?php esc_attr_e( 'Notas sobre el estado del equipo, incidencias durante el uso…', 'aura-suite' ); ?>"></textarea>
                </div>
            </div><!-- .aura-modal-body -->

            <div class="aura-modal-footer">
                <div id="js-checkin-msg" class="aura-loan-notice" style="display:none;"></div>
                <button type="button" class="btn btn-glass aura-loan-modal-close"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
                <button type="submit" class="btn btn-shimmer btn-indigo btn-lift" id="js-checkin-submit">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e( 'Registrar Devolución', 'aura-suite' ); ?>
                </button>
            </div>
        </form>
    </div>
</div><!-- #js-modal-checkin -->


<!-- ══════════════════════════════════════════════════════════
     MODAL — HISTORIAL de préstamos por equipo (Item 26 + Async Skeleton)
═══════════════════════════════════════════════════════════════ -->
<div id="js-modal-history" class="aura-modal-overlay" data-async-modal="true" role="dialog" aria-modal="true" aria-labelledby="js-history-title" style="display:none;">
    <div class="aura-modal-container" style="max-width:860px;">
        <div class="aura-modal-header">
            <div class="aura-loan-history-header-info">
                <h3 id="js-history-title" class="aura-modal-title"><?php _e( 'Historial de Préstamos', 'aura-suite' ); ?></h3>
                <span id="js-history-meta" class="aura-loan-history-meta"></span>
            </div>
            <button type="button" class="aura-modal-close aura-loan-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>

        <div class="aura-modal-body">
            <div id="js-history-body"></div>
        </div>

        <div class="aura-modal-footer">
            <button type="button" class="btn btn-glass aura-loan-modal-close"><?php _e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>
    </div>
</div><!-- #js-modal-history -->


<!-- ══════════════════════════════════════════════════════════
     MODAL — EDITAR PRÉSTAMO (Item 26)
═══════════════════════════════════════════════════════════════ -->
<div id="js-modal-edit-loan" class="aura-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="js-modal-edit-loan-title" style="display:none;">
    <div class="aura-modal-container" style="max-width:560px;">
        <div class="aura-modal-header">
            <h3 id="js-modal-edit-loan-title" class="aura-modal-title">
                <span class="dashicons dashicons-edit"></span>
                <?php _e( 'Editar Préstamo', 'aura-suite' ); ?>
            </h3>
            <button type="button" class="aura-modal-close js-close-edit-loan" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>

        <form id="js-form-edit-loan" novalidate style="display:flex;flex-direction:column;min-height:0;flex:1;">
            <input type="hidden" id="el-loan-id" name="loan_id">
            <div class="aura-modal-body">
                <p id="js-el-equip-name" class="aura-loan-edit-equip-banner"></p>

                <div class="aura-loan-fields-2col">
                    <div class="aura-loan-field-group">
                        <label for="el-loan-date" class="aura-loan-label required">
                            <?php _e( 'Fecha de salida', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                        </label>
                        <input type="date" id="el-loan-date" name="loan_date" class="form-control" required>
                    </div>
                    <div class="aura-loan-field-group">
                        <label for="el-expected-return" class="aura-loan-label required">
                            <?php _e( 'Devolución esperada', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                        </label>
                        <input type="date" id="el-expected-return" name="expected_return_date" class="form-control" required>
                    </div>
                </div>

                <div class="aura-loan-field-group">
                    <?php
                    if ( class_exists( 'Aura_Contact_Selector' ) ) {
                        echo Aura_Contact_Selector::render( [
                            'id'          => 'el-contact-selector',
                            'name'        => 'contact_id',
                            'label'       => __( 'Vincular a Contacto de Aura', 'aura-suite' ),
                            'placeholder' => __( 'Buscar contacto por nombre, email o teléfono…', 'aura-suite' ),
                            'sync_fields' => [
                                'name'  => '#el-borrowed-name',
                                'phone' => '#el-borrowed-phone',
                            ],
                            'hint'        => __( 'Actualiza automáticamente el nombre y teléfono del contacto.', 'aura-suite' ),
                        ] );
                    }
                    ?>
                </div>

                <div class="aura-loan-field-group">
                    <label for="el-borrowed-name" class="aura-loan-label">
                        <?php _e( 'Nombre del prestatario (externo)', 'aura-suite' ); ?>
                    </label>
                    <input type="text" id="el-borrowed-name" name="borrowed_to_name" class="form-control"
                           placeholder="<?php esc_attr_e( 'Dejar vacío si es usuario del sistema', 'aura-suite' ); ?>">
                </div>

                <div class="aura-loan-fields-2col">
                    <div class="aura-loan-field-group">
                        <label for="el-borrowed-phone" class="aura-loan-label">
                            <?php _e( 'Teléfono', 'aura-suite' ); ?>
                        </label>
                        <input type="tel" id="el-borrowed-phone" name="borrowed_to_phone" class="form-control" placeholder="+57 300 0000000">
                    </div>
                    <div class="aura-loan-field-group">
                        <label for="el-project" class="aura-loan-label">
                            <?php _e( 'Proyecto / Motivo', 'aura-suite' ); ?>
                        </label>
                        <input type="text" id="el-project" name="project" class="form-control">
                    </div>
                </div>

                <div id="js-el-feedback" class="aura-loan-notice error" style="display:none;margin-top:8px;"><p style="margin:0;"></p></div>
            </div><!-- .aura-modal-body -->

            <div class="aura-modal-footer">
                <button type="button" class="btn btn-glass js-close-edit-loan"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
                <button type="submit" class="btn btn-shimmer btn-indigo btn-lift" id="js-el-submit">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e( 'Guardar cambios', 'aura-suite' ); ?>
                </button>
            </div>
        </form>
    </div>
</div><!-- #js-modal-edit-loan -->

<?php
$_loans_js = wp_json_encode( [
    'ajaxurl'         => admin_url( 'admin-ajax.php' ),
    'nonce'           => $nonce,
    'can_checkout'    => $can_checkout,
    'can_checkin'     => $can_checkin,
    'can_loan_edit'   => $can_loan_edit,
    'can_loan_delete' => $can_loan_delete,
    'today'           => $today,
    'default_return'  => $default_return,
    'loan_max_days'   => $loan_max_days,
    'current_user_id' => get_current_user_id(),
    'txt'             => [
        'active'              => __( 'Activo', 'aura-suite' ),
        'overdue'             => __( 'Vencido', 'aura-suite' ),
        'returned'            => __( 'Devuelto', 'aura-suite' ),
        'checkin'             => __( 'Devolver', 'aura-suite' ),
        'edit_loan'           => __( 'Editar', 'aura-suite' ),
        'delete_loan'         => __( 'Eliminar', 'aura-suite' ),
        'confirm_delete_loan' => __( '¿Eliminar este préstamo? Esta acción no se puede deshacer.', 'aura-suite' ),
        'history'             => __( 'Historial', 'aura-suite' ),
        'no_loans'            => __( 'Sin préstamos registrados.', 'aura-suite' ),
        'confirm_checkout'    => __( '¿Registrar préstamo?', 'aura-suite' ),
        'confirm_checkin'     => __( '¿Registrar devolución?', 'aura-suite' ),
        'select_equip'        => __( 'Selecciona un equipo primero', 'aura-suite' ),
        'days'                => __( 'días', 'aura-suite' ),
        'days_ago'            => __( 'días de retraso', 'aura-suite' ),
        'no_history'          => __( 'Sin préstamos registrados para este equipo.', 'aura-suite' ),
    ],
] );
?>
<script>var auraInventoryLoans = <?php echo $_loans_js; ?>;</script>
