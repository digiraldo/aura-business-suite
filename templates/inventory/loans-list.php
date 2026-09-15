<?php
/**
 * Template — Listado de Préstamos de Equipos (FASE 5)
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$can_checkout   = current_user_can( 'aura_inventory_checkout'    ) || current_user_can( 'manage_options' );
$can_checkin    = current_user_can( 'aura_inventory_checkin'     ) || current_user_can( 'manage_options' );
$can_loan_edit  = current_user_can( 'aura_inventory_loan_edit'   ) || current_user_can( 'manage_options' );
$can_loan_delete = current_user_can( 'aura_inventory_loan_delete' ) || current_user_can( 'manage_options' );
$kpis         = Aura_Inventory_Loans::get_kpis();
$today        = current_time( 'Y-m-d' );
$nonce        = wp_create_nonce( 'aura_inventory_nonce' );
?>
<div class="aura-app-wrapper aura-inventory-loans-page">
    <div class="wrap aura-app-context">

        <?php
        $header_actions = [];
        if ( $can_checkout ) {
            $header_actions[] = [
                'label' => __( 'Nuevo Préstamo', 'aura-suite' ),
                'url'   => '#',
                'class' => 'aura-btn aura-btn-primary aura-loan-btn-checkout',
                'icon'  => 'dashicons-plus-alt',
                'attr'  => 'id="js-open-checkout"',
            ];
        }

        Aura_UI::render_page_header([
            'title'    => __( 'Préstamos de Equipos', 'aura-suite' ),
            'subtitle' => __( 'Seguimiento en tiempo real de salidas, entregas, responsables y devoluciones de herramientas y activos.', 'aura-suite' ),
            'icon'     => 'dashicons-share',
            'badges'   => [
                ['label' => sprintf( __('%d activos', 'aura-suite'), $kpis['active'] ), 'variant' => $kpis['active'] > 0 ? 'blue' : 'gray', 'tooltip' => __( 'Préstamos actualmente en curso', 'aura-suite' )],
                ['label' => sprintf( __('%d vencidos', 'aura-suite'), $kpis['overdue'] ), 'variant' => $kpis['overdue'] > 0 ? 'red' : 'green', 'tooltip' => __( 'Préstamos con devolución vencida', 'aura-suite' )],
            ],
            'actions'  => $header_actions,
        ]);
        ?>

        <!-- KPIs Canónicos ─────────────────────────────────────── -->
        <?php
        Aura_UI::render_stats_grid([
            [
                'label'   => __( 'Préstamos Activos', 'aura-suite' ),
                'value'   => number_format( $kpis['active'] ),
                'icon'    => 'dashicons-share',
                'variant' => $kpis['active'] > 0 ? 'balance' : 'count',
                'tooltip' => __( 'Equipos actualmente prestados', 'aura-suite' ),
                'sub'     => __( 'En uso por personal o proyectos', 'aura-suite' ),
            ],
            [
                'label'   => __( 'Préstamos Vencidos', 'aura-suite' ),
                'value'   => number_format( $kpis['overdue'] ),
                'icon'    => 'dashicons-warning',
                'variant' => $kpis['overdue'] > 0 ? 'expense' : 'income',
                'tooltip' => __( 'Préstamos que superaron la fecha esperada de entrega', 'aura-suite' ),
                'sub'     => $kpis['overdue'] > 0 ? __( 'Requieren atención urgente', 'aura-suite' ) : __( 'Al día', 'aura-suite' ),
            ],
            [
                'label'   => __( 'Devueltos Este Mes', 'aura-suite' ),
                'value'   => number_format( $kpis['returned_month'] ),
                'icon'    => 'dashicons-yes-alt',
                'variant' => 'income',
                'tooltip' => __( 'Equipos devueltos en el mes calendario', 'aura-suite' ),
                'sub'     => __( 'Retornos confirmados', 'aura-suite' ),
            ],
            [
                'label'   => __( 'Total Histórico', 'aura-suite' ),
                'value'   => number_format( $kpis['total'] ),
                'icon'    => 'dashicons-list-view',
                'variant' => 'count',
                'tooltip' => __( 'Historial completo acumulado de préstamos', 'aura-suite' ),
                'sub'     => __( 'Registros totales', 'aura-suite' ),
            ],
        ]);
        ?>

        <!-- Filtros (Glass Card) ──────────────────────────────── -->
        <div class="aura-glass-card" style="padding: 18px 20px; margin-bottom: 20px;">
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                <input type="text" id="js-loan-search" class="aura-loan-filter"
                       placeholder="<?php esc_attr_e( 'Buscar por equipo o prestatario…', 'aura-suite' ); ?>"
                       style="flex: 1; min-width: 220px; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 12px;">

                <select id="js-loan-status" class="aura-loan-filter" style="height: 38px; min-width: 150px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value=""><?php _e( 'Todos los estados', 'aura-suite' ); ?></option>
                    <option value="active"><?php _e( 'Activos', 'aura-suite' ); ?></option>
                    <option value="overdue"><?php _e( 'Vencidos', 'aura-suite' ); ?></option>
                    <option value="returned"><?php _e( 'Devueltos', 'aura-suite' ); ?></option>
                </select>

                <input type="date" id="js-loan-date-from" class="aura-loan-filter"
                       title="<?php esc_attr_e( 'Desde', 'aura-suite' ); ?>"
                       placeholder="<?php esc_attr_e( 'Desde', 'aura-suite' ); ?>"
                       style="height: 38px; border-radius: 6px; border: 1px solid #cbd5e1;">
                <input type="date" id="js-loan-date-to" class="aura-loan-filter"
                       title="<?php esc_attr_e( 'Hasta', 'aura-suite' ); ?>"
                       placeholder="<?php esc_attr_e( 'Hasta', 'aura-suite' ); ?>"
                       style="height: 38px; border-radius: 6px; border: 1px solid #cbd5e1;">

                <button type="button" class="button aura-btn-primary" id="js-loan-filter-btn" style="height: 38px;">
                    <span class="dashicons dashicons-search" style="vertical-align: middle;"></span> <?php _e( 'Filtrar', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button aura-btn-secondary" id="js-loan-clear-btn" style="height: 38px;">
                    <?php _e( 'Limpiar', 'aura-suite' ); ?>
                </button>
            </div>
        </div>

        <!-- Tabla de préstamos fluida -->
        <div class="aura-dt-wrapper">
            <table id="js-loans-table" class="dataTable aura-table display responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th><?php _e( 'Foto',               'aura-suite' ); ?></th>
                        <th><?php _e( 'Equipo',             'aura-suite' ); ?></th>
                        <th><?php _e( 'Prestatario',        'aura-suite' ); ?></th>
                        <th><?php _e( 'Proyecto / Motivo',  'aura-suite' ); ?></th>
                        <th><?php _e( 'Fecha salida',       'aura-suite' ); ?></th>
                        <th><?php _e( 'Devolución esperada','aura-suite' ); ?></th>
                        <th><?php _e( 'Estado',             'aura-suite' ); ?></th>
                        <th><?php _e( 'Acciones',           'aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<?php
wp_enqueue_script( 'aura-inv-loans-list', AURA_PLUGIN_URL . 'assets/js/inventory-loans.js', ['jquery','datatables-responsive-js', 'aura-ui-core'], AURA_VERSION, true );
wp_enqueue_style(  'aura-inv-loans',      AURA_PLUGIN_URL . 'assets/css/inventory-loans.css', ['aura-design-system'], AURA_VERSION );
?>



<!-- ══════════════════════════════════════════════════════════
     MODAL — CHECKOUT (Nuevo Préstamo)
═══════════════════════════════════════════════════════════════ -->
<div id="js-modal-checkout" class="aura-loan-modal" style="display:none;" role="dialog" aria-modal="true"
     aria-label="<?php esc_attr_e( 'Registrar nuevo préstamo', 'aura-suite' ); ?>">
    <div class="aura-loan-modal-inner">

        <div class="aura-loan-modal-header">
            <h2><?php _e( 'Registrar Préstamo (Checkout)', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-loan-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <form id="js-form-checkout" novalidate>
            <div class="aura-loan-modal-body">

                <!-- Equipo ───────────────────────────────────── -->
                <div class="aura-loan-field-group">
                    <label class="aura-loan-label required">
                        <?php _e( 'Equipo a prestar', 'aura-suite' ); ?>
                        <span class="aura-loan-required">*</span>
                    </label>

                    <!-- Pestañas: Buscar / Ver listado -->
                    <div class="aura-loan-equip-mode-tabs">
                        <button type="button" class="aura-loan-equip-tab active" data-mode="search">
                            <span class="dashicons dashicons-search"></span>
                            <?php _e( 'Buscar', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="aura-loan-equip-tab" data-mode="list">
                            <span class="dashicons dashicons-list-view"></span>
                            <?php _e( 'Ver listado', 'aura-suite' ); ?>
                        </button>
                    </div>

                    <!-- Modo: Búsqueda con autocompletado -->
                    <div id="co-equip-mode-search">
                        <div class="aura-loan-equip-search-wrap">
                            <input type="text" id="co-equipment-search" class="aura-loan-input"
                                   placeholder="<?php esc_attr_e( 'Escriba nombre, código o marca…', 'aura-suite' ); ?>"
                                   autocomplete="off">
                            <div id="co-equip-results" class="aura-loan-equip-results" style="display:none;"></div>
                        </div>
                    </div>

                    <!-- Modo: Listado completo de disponibles -->
                    <div id="co-equip-mode-list" style="display:none;">
                        <div id="co-equip-list-loading" class="aura-loan-equip-list-loading" style="display:none;">
                            <span class="spinner is-active" style="float:none;"></span>
                            <?php _e( 'Cargando equipos disponibles…', 'aura-suite' ); ?>
                        </div>
                        <div id="co-equip-list-select-wrap" style="display:none; align-items:center; gap:8px;">
                            <select id="co-equip-list-select" class="aura-loan-input" style="flex:1;">
                                <option value=""><?php _e( 'Seleccione un equipo…', 'aura-suite' ); ?></option>
                            </select>
                            <button type="button" class="button button-secondary" id="js-btn-select-listed-equip">
                                <?php _e( 'Seleccionar', 'aura-suite' ); ?>
                            </button>
                        </div>
                    </div>

                    <input type="hidden" id="co-equipment-id" name="equipment_id">
                    <div id="co-equip-selected" class="aura-loan-selected-equip" style="display:none;"></div>
                </div>

                <div class="aura-loan-fields-2col">

                    <!-- Fecha de salida ──────────────────────── -->
                    <div class="aura-loan-field-group">
                        <label for="co-loan-date" class="aura-loan-label required">
                            <?php _e( 'Fecha de salida', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                        </label>
                        <input type="date" id="co-loan-date" name="loan_date"
                               class="aura-loan-input"
                               value="<?php echo esc_attr( $today ); ?>"
                               data-allow-future="1" required>
                    </div>

                    <!-- Fecha devolución esperada ────────────── -->
                    <div class="aura-loan-field-group">
                        <label for="co-expected-return" class="aura-loan-label required">
                            <?php _e( 'Devolución esperada', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                        </label>
                        <input type="date" id="co-expected-return" name="expected_return_date"
                               class="aura-loan-input" data-allow-future="1" required>
                    </div>

                </div><!-- .aura-loan-fields-2col -->

                <div class="aura-loan-fields-2col">

                    <!-- Nombre libre (PRIORIDAD) ─────────────── -->
                    <div class="aura-loan-field-group">
                        <label for="co-borrower-name" class="aura-loan-label">
                            <?php _e( 'Nombre del prestatario', 'aura-suite' ); ?>
                            <span class="aura-loan-badge-priority"><?php _e( 'Prioridad', 'aura-suite' ); ?></span>
                        </label>
                        <input type="text" id="co-borrower-name" name="borrowed_to_name"
                               class="aura-loan-input"
                               placeholder="<?php esc_attr_e( 'Nombre de quien recibe el equipo…', 'aura-suite' ); ?>">
                        <p class="aura-loan-field-hint">
                            <?php _e( 'Si lo llena, se muestra como prestatario. Si lo deja vacío, se usa el usuario seleccionado abajo.', 'aura-suite' ); ?>
                        </p>
                    </div>

                    <!-- Teléfono WhatsApp (externo) ──────────── -->
                    <div class="aura-loan-field-group">
                        <label for="co-borrower-phone" class="aura-loan-label">
                            <?php _e( 'Teléfono WhatsApp (externo)', 'aura-suite' ); ?>
                        </label>
                        <input type="tel" id="co-borrower-phone" name="borrowed_to_phone"
                               class="aura-loan-input"
                               placeholder="<?php esc_attr_e( '+51 987 654 321', 'aura-suite' ); ?>"
                               pattern="[\+0-9\s\-]+"
                               maxlength="30">
                        <p class="aura-loan-field-hint">
                            <?php _e( 'Opcional. Si se completa, recibirá notificaciones WhatsApp sobre el préstamo.', 'aura-suite' ); ?>
                        </p>
                    </div>

                    <!-- Prestatario (usuario WP) ─────────────── -->
                    <div class="aura-loan-field-group">
                        <label for="co-borrower-user" class="aura-loan-label">
                            <?php _e( 'Usuario del sistema (alternativo)', 'aura-suite' ); ?>
                        </label>
                        <select id="co-borrower-user" name="borrowed_by_user_id" class="aura-loan-input">
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
                    </div>

                </div>

                <!-- Proyecto / Motivo ────────────────────────── -->
                <div class="aura-loan-field-group">
                    <label for="co-project" class="aura-loan-label">
                        <?php _e( 'Proyecto / Motivo', 'aura-suite' ); ?>
                    </label>
                    <input type="text" id="co-project" name="project"
                           class="aura-loan-input"
                           placeholder="<?php esc_attr_e( 'Ej: Campamento Hadime, Obra en sala B…', 'aura-suite' ); ?>">
                </div>

                <!-- Estado del equipo al salir ──────────────── -->
                <div class="aura-loan-field-group">
                    <label class="aura-loan-label">
                        <?php _e( 'Estado del equipo al salir', 'aura-suite' ); ?>
                    </label>
                    <div class="aura-loan-radio-group">
                        <?php
                        $states_out = [
                            'good' => __( 'Bueno',    'aura-suite' ),
                            'fair' => __( 'Regular',  'aura-suite' ),
                            'poor' => __( 'Deficiente','aura-suite' ),
                        ];
                        foreach ( $states_out as $val => $label ) :
                        ?>
                        <label class="aura-loan-radio-label">
                            <input type="radio" name="equipment_state_out" value="<?php echo esc_attr( $val ); ?>"
                                   <?php checked( $val, 'good' ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- .aura-loan-modal-body -->

            <div class="aura-loan-modal-footer">
                <div id="js-checkout-msg" class="aura-loan-notice" style="display:none;"></div>
                <button type="button" class="button aura-loan-modal-close"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
                <button type="submit" class="button button-primary" id="js-checkout-submit">
                    <span class="dashicons dashicons-share" style="vertical-align:middle;"></span>
                    <?php _e( 'Registrar Préstamo', 'aura-suite' ); ?>
                </button>
            </div>
        </form>

    </div>
</div><!-- #js-modal-checkout -->
<div class="aura-loan-overlay" id="js-overlay" style="display:none;"></div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — CHECKIN (Devolución)
═══════════════════════════════════════════════════════════════ -->
<div id="js-modal-checkin" class="aura-loan-modal" style="display:none;" role="dialog" aria-modal="true"
     aria-label="<?php esc_attr_e( 'Registrar devolución', 'aura-suite' ); ?>">
    <div class="aura-loan-modal-inner">

        <div class="aura-loan-modal-header">
            <h2><?php _e( 'Registrar Devolución (Checkin)', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-loan-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <form id="js-form-checkin" novalidate>
            <input type="hidden" id="ci-loan-id" name="loan_id">

            <div class="aura-loan-modal-body">

                <!-- Resumen del préstamo ───────────────────────── -->
                <div id="ci-summary" class="aura-loan-checkin-summary"></div>

                <div class="aura-loan-fields-2col">

                    <!-- Fecha real de devolución ─────────────── -->
                    <div class="aura-loan-field-group">
                        <label for="ci-return-date" class="aura-loan-label required">
                            <?php _e( 'Fecha de devolución real', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                        </label>
                        <input type="date" id="ci-return-date" name="actual_return_date"
                               class="aura-loan-input"
                               value="<?php echo esc_attr( $today ); ?>" required>
                    </div>

                    <!-- Horas usadas ──────────────────────────── -->
                    <div class="aura-loan-field-group">
                        <label for="ci-hours" class="aura-loan-label">
                            <?php _e( 'Horas de uso (opcional)', 'aura-suite' ); ?>
                        </label>
                        <input type="number" id="ci-hours" name="hours_used"
                               class="aura-loan-input" min="0" step="0.5"
                               placeholder="0.0">
                    </div>

                </div>

                <!-- Estado al regresar ───────────────────────── -->
                <div class="aura-loan-field-group">
                    <label class="aura-loan-label required">
                        <?php _e( 'Estado del equipo al regresar', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                    </label>
                    <div class="aura-loan-radio-group">
                        <?php
                        $states_in = [
                            'good'    => __( 'Bueno',  'aura-suite' ),
                            'fair'    => __( 'Regular','aura-suite' ),
                            'damaged' => __( 'Dañado', 'aura-suite' ),
                        ];
                        foreach ( $states_in as $val => $label ) :
                        ?>
                        <label class="aura-loan-radio-label <?php echo $val === 'damaged' ? 'aura-loan-radio-danger' : ''; ?>">
                            <input type="radio" name="return_state" value="<?php echo esc_attr( $val ); ?>"
                                   <?php checked( $val, 'good' ); ?> required>
                            <?php echo esc_html( $label ); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div id="ci-damaged-warning" class="aura-loan-damaged-warning" style="display:none;">
                        ⚠️ <?php _e( 'Se enviará una notificación automática al administrador sobre el daño.', 'aura-suite' ); ?>
                    </div>
                </div>

                <!-- ¿Requiere mantenimiento? ─────────────────── -->
                <div class="aura-loan-field-group">
                    <label class="aura-loan-checkbox-label">
                        <input type="checkbox" id="ci-req-maint" name="requires_maintenance_after" value="1">
                        <span><?php _e( '¿El equipo requiere mantenimiento después del uso?', 'aura-suite' ); ?></span>
                    </label>
                    <p class="description">
                        <?php _e( 'Si está marcado, el equipo pasará a estado "Mantenimiento" al procesarse la devolución.', 'aura-suite' ); ?>
                    </p>
                </div>

                <!-- Observaciones ────────────────────────────── -->
                <div class="aura-loan-field-group">
                    <label for="ci-observations" class="aura-loan-label">
                        <?php _e( 'Observaciones de devolución', 'aura-suite' ); ?>
                    </label>
                    <textarea id="ci-observations" name="return_observations"
                              class="aura-loan-input aura-loan-textarea" rows="3"
                              placeholder="<?php esc_attr_e( 'Notas sobre el estado del equipo, incidencias durante el uso…', 'aura-suite' ); ?>"></textarea>
                </div>

            </div><!-- .aura-loan-modal-body -->

            <div class="aura-loan-modal-footer">
                <div id="js-checkin-msg" class="aura-loan-notice" style="display:none;"></div>
                <button type="button" class="button aura-loan-modal-close"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
                <button type="submit" class="button button-primary" id="js-checkin-submit">
                    <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span>
                    <?php _e( 'Registrar Devolución', 'aura-suite' ); ?>
                </button>
            </div>
        </form>

    </div>
</div><!-- #js-modal-checkin -->


<!-- ══════════════════════════════════════════════════════════
     MODAL — HISTORIAL de préstamos por equipo
═══════════════════════════════════════════════════════════════ -->
<div id="js-modal-history" class="aura-loan-modal aura-loan-modal-wide" style="display:none;" role="dialog" aria-modal="true">
    <div class="aura-loan-modal-inner">

        <div class="aura-loan-modal-header">
            <div class="aura-loan-history-header-info">
                <h2 id="js-history-title"><?php _e( 'Historial de Préstamos', 'aura-suite' ); ?></h2>
                <span id="js-history-meta" class="aura-loan-history-meta"></span>
            </div>
            <button type="button" class="aura-loan-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <div class="aura-loan-modal-body">
            <div id="js-history-body"></div>
        </div>

        <div class="aura-loan-modal-footer">
            <button type="button" class="button aura-loan-modal-close"><?php _e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>

    </div>
</div><!-- #js-modal-history -->

<!-- ══════════════════════════════════════════════════════════
     MODAL — EDITAR PRÉSTAMO
═══════════════════════════════════════════════════════════════ -->
<div id="js-modal-edit-loan" class="aura-loan-modal" style="display:none;" role="dialog" aria-modal="true"
     aria-label="<?php esc_attr_e( 'Editar préstamo', 'aura-suite' ); ?>">
    <div class="aura-loan-modal-inner" style="max-width:520px;">

        <div class="aura-loan-modal-header">
            <h2><?php _e( 'Editar Préstamo', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-loan-modal-close js-close-edit-loan" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <form id="js-form-edit-loan" novalidate>
            <input type="hidden" id="el-loan-id" name="loan_id">
            <div class="aura-loan-modal-body">

                <p id="js-el-equip-name" style="font-weight:600;margin:0 0 14px;font-size:14px;color:#1d2327;"></p>

                <div class="aura-loan-fields-2col">
                    <div class="aura-loan-field-group">
                        <label for="el-loan-date" class="aura-loan-label required">
                            <?php _e( 'Fecha de salida', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                        </label>
                        <input type="date" id="el-loan-date" name="loan_date" class="aura-loan-input" required>
                    </div>
                    <div class="aura-loan-field-group">
                        <label for="el-expected-return" class="aura-loan-label required">
                            <?php _e( 'Devolución esperada', 'aura-suite' ); ?> <span class="aura-loan-required">*</span>
                        </label>
                        <input type="date" id="el-expected-return" name="expected_return_date" class="aura-loan-input" required>
                    </div>
                </div>

                <div class="aura-loan-field-group">
                    <label for="el-borrowed-name" class="aura-loan-label">
                        <?php _e( 'Nombre del prestatario (externo)', 'aura-suite' ); ?>
                    </label>
                    <input type="text" id="el-borrowed-name" name="borrowed_to_name" class="aura-loan-input"
                           placeholder="<?php esc_attr_e( 'Dejar vacío si es usuario del sistema', 'aura-suite' ); ?>">
                </div>

                <div class="aura-loan-fields-2col">
                    <div class="aura-loan-field-group">
                        <label for="el-borrowed-phone" class="aura-loan-label">
                            <?php _e( 'Teléfono', 'aura-suite' ); ?>
                        </label>
                        <input type="tel" id="el-borrowed-phone" name="borrowed_to_phone" class="aura-loan-input"
                               placeholder="+57 300 0000000">
                    </div>
                    <div class="aura-loan-field-group">
                        <label for="el-project" class="aura-loan-label">
                            <?php _e( 'Proyecto / Motivo', 'aura-suite' ); ?>
                        </label>
                        <input type="text" id="el-project" name="project" class="aura-loan-input">
                    </div>
                </div>

                <div id="js-el-feedback" style="display:none;margin-top:10px;" class="notice notice-error inline"><p></p></div>

            </div><!-- .aura-loan-modal-body -->

            <div class="aura-loan-modal-footer">
                <button type="button" class="button js-close-edit-loan"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
                <button type="submit" class="button button-primary" id="js-el-submit">
                    <span class="spinner" style="float:none;margin:0 4px -3px 0;"></span>
                    <?php _e( 'Guardar cambios', 'aura-suite' ); ?>
                </button>
            </div>
        </form>

    </div><!-- .aura-loan-modal-inner -->
</div><!-- #js-modal-edit-loan -->

<?php
// Inyectar configuración JS
$_loans_js = wp_json_encode( [
    'ajaxurl'      => admin_url( 'admin-ajax.php' ),
    'nonce'        => $nonce,
    'can_checkout'  => $can_checkout,
    'can_checkin'   => $can_checkin,
    'can_loan_edit' => $can_loan_edit,
    'can_loan_delete' => $can_loan_delete,
    'today'        => $today,
    'txt'          => [
        'active'        => __( 'Activo',       'aura-suite' ),
        'overdue'       => __( 'Vencido',      'aura-suite' ),
        'returned'      => __( 'Devuelto',     'aura-suite' ),
        'checkin'       => __( 'Devolver',     'aura-suite' ),
        'edit_loan'     => __( 'Editar',        'aura-suite' ),
        'delete_loan'   => __( 'Eliminar',      'aura-suite' ),
        'confirm_delete_loan' => __( '¿Eliminar este préstamo? Esta acción no se puede deshacer.', 'aura-suite' ),
        'history'       => __( 'Historial',    'aura-suite' ),
        'no_loans'      => __( 'Sin préstamos registrados.', 'aura-suite' ),
        'confirm_checkout' => __( '¿Registrar préstamo?', 'aura-suite' ),
        'confirm_checkin'  => __( '¿Registrar devolución?', 'aura-suite' ),
        'select_equip'  => __( 'Selecciona un equipo primero', 'aura-suite' ),
        'days'          => __( 'días', 'aura-suite' ),
        'days_ago'      => __( 'días de retraso', 'aura-suite' ),
        'no_history'    => __( 'Sin préstamos registrados para este equipo.', 'aura-suite' ),
    ],
] );
?>
<script>var auraInventoryLoans = <?php echo $_loans_js; ?>;</script>
