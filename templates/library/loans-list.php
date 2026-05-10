<?php
/**
 * Template: Listado de Préstamos
 * Fase 3 — DataTables con 9 columnas + filtros + 3 modales
 *
 * @package Aura_Business_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_library_view_loans_own' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aura-business-suite' ) );
}

$can_create     = current_user_can( 'aura_library_loan_create' )  || current_user_can( 'manage_options' );
$can_return     = current_user_can( 'aura_library_loan_return' )  || current_user_can( 'manage_options' );
$can_extend     = current_user_can( 'aura_library_loan_extend' )  || current_user_can( 'manage_options' );
$can_edit       = current_user_can( 'aura_library_loan_edit' )    || current_user_can( 'manage_options' );
$can_delete     = current_user_can( 'aura_library_loan_delete' )  || current_user_can( 'manage_options' );
$can_view_all   = current_user_can( 'aura_library_view_loans_all' ) || current_user_can( 'manage_options' );
$can_view_fines = current_user_can( 'aura_library_view_fines' )   || current_user_can( 'manage_options' );

$fines_enabled     = (bool) get_option( 'aura_library_fines_enabled', false );
$fines_to_finance  = (bool) get_option( 'aura_library_fines_to_finance', false );
$max_extensions    = (int)  get_option( 'aura_library_max_extensions', 2 );
$extension_days    = (int)  get_option( 'aura_library_extension_days', 7 );
?>
<div class="wrap aura-library-loans-list">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-book-alt" style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#0073aa;"></span>
        <?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?>
    </h1>

    <?php if ( $can_create ) : ?>
    <button type="button" id="aura-lib-btn-new-loan" class="page-title-action">
        + <?php esc_html_e( 'Nuevo Préstamo', 'aura-business-suite' ); ?>
    </button>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- Filtros -->
    <div class="aura-lib-filters-bar">
        <input type="search" id="aura-lib-loans-search"
               placeholder="<?php esc_attr_e( 'Buscar por libro, lector…', 'aura-business-suite' ); ?>"
               class="regular-text">

        <select id="aura-lib-loans-filter-status">
            <option value=""><?php esc_html_e( 'Todos los estados', 'aura-business-suite' ); ?></option>
            <option value="active"><?php    esc_html_e( 'Activo',    'aura-business-suite' ); ?></option>
            <option value="overdue"><?php   esc_html_e( 'Vencido',   'aura-business-suite' ); ?></option>
            <option value="extended"><?php  esc_html_e( 'Extendido', 'aura-business-suite' ); ?></option>
            <option value="returned"><?php  esc_html_e( 'Devuelto',  'aura-business-suite' ); ?></option>
            <option value="lost"><?php      esc_html_e( 'Perdido',   'aura-business-suite' ); ?></option>
            <option value="cancelled"><?php esc_html_e( 'Cancelado', 'aura-business-suite' ); ?></option>
        </select>

        <input type="date" id="aura-lib-loans-filter-from"
               title="<?php esc_attr_e( 'Desde (fecha préstamo)', 'aura-business-suite' ); ?>">
        <input type="date" id="aura-lib-loans-filter-to"
               title="<?php esc_attr_e( 'Hasta (fecha préstamo)', 'aura-business-suite' ); ?>">

        <button id="aura-lib-loans-filter-apply" class="button">
            <span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Filtrar', 'aura-business-suite' ); ?>
        </button>
        <button id="aura-lib-loans-filter-clear" class="button button-link">
            <?php esc_html_e( 'Limpiar', 'aura-business-suite' ); ?>
        </button>
    </div><!-- .aura-lib-filters-bar -->

    <!-- Mensajes de estado -->
    <div id="aura-lib-loans-notice" class="notice" style="display:none;"></div>

    <!-- Tabla DataTables -->
    <table id="aura-lib-loans-table" class="wp-list-table widefat">
        <thead>
            <tr>
                <th><?php esc_html_e( '#',                 'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Libro',             'aura-business-suite' ); ?></th>
                <?php if ( $can_view_all ) : ?>
                <th><?php esc_html_e( 'Lector',            'aura-business-suite' ); ?></th>
                <?php endif; ?>
                <th><?php esc_html_e( 'Fecha préstamo',    'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Devol. prevista',   'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Estado',            'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Extensiones',       'aura-business-suite' ); ?></th>
                <?php if ( $fines_enabled && $can_view_fines ) : ?>
                <th><?php esc_html_e( 'Multa',             'aura-business-suite' ); ?></th>
                <?php endif; ?>
                <th><?php esc_html_e( 'Acciones',          'aura-business-suite' ); ?></th>
            </tr>
        </thead>
        <tbody id="aura-lib-loans-tbody">
            <tr id="aura-lib-loans-loading">
                <td colspan="<?php echo esc_attr( 7 + ( $can_view_all ? 1 : 0 ) + ( $fines_enabled && $can_view_fines ? 1 : 0 ) ); ?>"
                    style="text-align:center;padding:20px;">
                    <span class="spinner is-active" style="float:none;"></span>
                    <?php esc_html_e( 'Cargando…', 'aura-business-suite' ); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Paginación -->
    <div id="aura-lib-loans-pagination" class="aura-lib-pagination" style="margin-top:12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;"></div>

<?php
// DataTables CDN
wp_enqueue_style( 'datatables-css',
    'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css', [], '2.2.2' );
wp_enqueue_style( 'datatables-responsive-css',
    'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css',
    [ 'datatables-css' ], '3.0.4' );
wp_enqueue_script( 'datatables-js',
    'https://cdn.datatables.net/2.2.2/js/dataTables.min.js', [ 'jquery' ], '2.2.2', true );
wp_enqueue_script( 'datatables-responsive-js',
    'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js',
    [ 'datatables-js' ], '3.0.4', true );
wp_enqueue_script( 'aura-lib-loans',
    AURA_PLUGIN_URL . 'assets/js/library-loans.js',
    [ 'jquery', 'datatables-responsive-js', 'jquery-ui-autocomplete' ],
    AURA_VERSION, true );
wp_enqueue_style( 'aura-lib-loans-css',
    AURA_PLUGIN_URL . 'assets/css/library-loans.css',
    [ 'datatables-responsive-css' ], AURA_VERSION );
?>

</div><!-- .aura-library-loans-list -->

<!-- ╔══════════════════════════════════════════╗
     ║  Modal: Nuevo Préstamo (UX v2)           ║
     ╚══════════════════════════════════════════╝ -->
<div id="aura-lib-loan-modal" class="aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-loan-modal-title">
    <div class="aura-lib-modal-overlay"></div>
    <div class="aura-lib-modal-content aura-lib-loan-modal-content">

        <!-- Header con icono y degradado -->
        <div class="aura-lib-loan-mhdr">
            <div class="aura-lib-loan-mhdr-icon">📚</div>
            <div class="aura-lib-loan-mhdr-text">
                <h2 id="aura-lib-loan-modal-title"><?php esc_html_e( 'Nuevo Préstamo', 'aura-business-suite' ); ?></h2>
                <span><?php esc_html_e( 'Completa los campos para registrar el préstamo', 'aura-business-suite' ); ?></span>
            </div>
            <button type="button" class="aura-lib-modal-close aura-lib-loan-mhdr-close dashicons dashicons-no-alt"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"></button>
        </div>

        <!-- Cuerpo del formulario -->
        <div class="aura-lib-modal-body aura-lib-loan-mbody">
            <form id="aura-lib-loan-form" autocomplete="off" novalidate>
                <?php wp_nonce_field( 'aura_library_nonce', 'aura_lib_loan_nonce' ); ?>

                <!-- ══ PASO 1: LIBRO ══════════════════════════════════ -->
                <div class="aura-lib-lstep">
                    <div class="aura-lib-lstep-hdr">
                        <span class="aura-lib-lstep-num">1</span>
                        <strong><?php esc_html_e( 'Libro', 'aura-business-suite' ); ?></strong>
                        <span class="aura-lib-lstep-req"><?php esc_html_e( '* requerido', 'aura-business-suite' ); ?></span>
                    </div>

                    <!-- Buscador de libro -->
                    <div class="aura-lib-lsearch-wrap" id="loan-book-search-row">
                        <span class="dashicons dashicons-search aura-lib-lsearch-icon"></span>
                        <input type="text" id="loan_book_input" name="loan_book_input"
                               class="aura-lib-lsearch-input"
                               placeholder="<?php esc_attr_e( 'Buscar por título, autor o ISBN…', 'aura-business-suite' ); ?>"
                               autocomplete="off">
                    </div>

                    <!-- Tarjeta del libro seleccionado -->
                    <div id="aura-lib-loan-book-card" class="aura-lib-loan-selected-card" style="display:none;">
                        <div class="aura-lib-loan-card-cover" id="loan-book-card-cover">
                            <span class="dashicons dashicons-book" style="font-size:22px;color:#9ca3af;"></span>
                        </div>
                        <div class="aura-lib-loan-card-info">
                            <strong id="loan-book-card-title" class="aura-lib-loan-card-title"></strong>
                            <span id="loan-book-card-sub" class="aura-lib-loan-card-sub"></span>
                        </div>
                        <div id="loan-book-card-avail" class="aura-lib-loan-card-avail"></div>
                        <button type="button" id="aura-lib-loan-clear-book" class="aura-lib-loan-clear-btn"
                                title="<?php esc_attr_e( 'Cambiar libro', 'aura-business-suite' ); ?>">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>

                    <span id="loan-book-error" class="aura-lib-loan-field-error" style="display:none;">
                        ⚠ <?php esc_html_e( 'Selecciona un libro de la lista de sugerencias.', 'aura-business-suite' ); ?>
                    </span>
                    <input type="hidden" id="loan_book_id" name="book_id">
                </div>

                <!-- ══ PASO 2: LECTOR ════════════════════════════════ -->
                <?php if ( $can_view_all ) : ?>
                <div class="aura-lib-lstep">
                    <div class="aura-lib-lstep-hdr">
                        <span class="aura-lib-lstep-num">2</span>
                        <strong><?php esc_html_e( 'Lector', 'aura-business-suite' ); ?></strong>
                        <span class="aura-lib-lstep-req"><?php esc_html_e( '* requerido', 'aura-business-suite' ); ?></span>
                    </div>

                    <!-- Buscador de lector -->
                    <div class="aura-lib-lsearch-wrap" id="loan-user-search-row">
                        <span class="dashicons dashicons-admin-users aura-lib-lsearch-icon"></span>
                        <input type="text" id="loan_user_input" name="loan_user_input"
                               class="aura-lib-lsearch-input"
                               placeholder="<?php esc_attr_e( 'Buscar por nombre o correo del lector…', 'aura-business-suite' ); ?>"
                               autocomplete="off">
                    </div>

                    <!-- Tarjeta del lector seleccionado -->
                    <div id="aura-lib-loan-user-card" class="aura-lib-loan-selected-card" style="display:none;">
                        <div class="aura-lib-loan-user-avatar" id="loan-user-card-avatar">?</div>
                        <div class="aura-lib-loan-card-info">
                            <strong id="loan-user-card-name" class="aura-lib-loan-card-title"></strong>
                            <span id="loan-user-card-email" class="aura-lib-loan-card-sub"></span>
                        </div>
                        <button type="button" id="aura-lib-loan-clear-user" class="aura-lib-loan-clear-btn"
                                title="<?php esc_attr_e( 'Cambiar lector', 'aura-business-suite' ); ?>">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>

                    <span id="loan-user-error" class="aura-lib-loan-field-error" style="display:none;">
                        ⚠ <?php esc_html_e( 'Selecciona un lector de la lista de sugerencias.', 'aura-business-suite' ); ?>
                    </span>
                    <input type="hidden" id="loan_user_id" name="borrower_user_id">
                </div>
                <?php endif; ?>

                <!-- ══ PASO 3: FECHAS ════════════════════════════════ -->
                <div class="aura-lib-lstep">
                    <div class="aura-lib-lstep-hdr">
                        <span class="aura-lib-lstep-num"><?php echo $can_view_all ? '3' : '2'; ?></span>
                        <strong><?php esc_html_e( 'Fechas del préstamo', 'aura-business-suite' ); ?></strong>
                        <span id="loan-duration-chip" class="aura-lib-loan-duration-chip" style="display:none;"></span>
                    </div>
                    <div class="aura-lib-loan-dates-grid">
                        <div class="aura-lib-loan-date-field">
                            <label for="loan_loan_date">
                                <span class="dashicons dashicons-calendar-alt" style="font-size:13px;color:#6b7280;margin-right:3px;"></span>
                                <?php esc_html_e( 'Fecha de préstamo', 'aura-business-suite' ); ?> <span class="required">*</span>
                            </label>
                            <input type="date" id="loan_loan_date" name="loan_date" required data-allow-future="1">
                        </div>
                        <div class="aura-lib-loan-date-field">
                            <label for="loan_due_date">
                                <span class="dashicons dashicons-clock" style="font-size:13px;color:#6b7280;margin-right:3px;"></span>
                                <?php esc_html_e( 'Fecha límite', 'aura-business-suite' ); ?> <span class="required">*</span>
                            </label>
                            <input type="date" id="loan_due_date" name="due_date" required data-allow-future="1">
                        </div>
                    </div>
                </div>

                <!-- ══ NOTAS (colapsable) ════════════════════════════ -->
                <div class="aura-lib-lstep aura-lib-lstep-notes">
                    <button type="button" id="aura-lib-loan-notes-toggle" class="aura-lib-loan-notes-toggle">
                        <span class="dashicons dashicons-plus-alt2 aura-lib-notes-icon"></span>
                        <?php esc_html_e( 'Agregar nota (opcional)', 'aura-business-suite' ); ?>
                    </button>
                    <div id="aura-lib-loan-notes-wrap" style="display:none;margin-top:8px;">
                        <textarea id="loan_notes" name="notes" rows="2" class="large-text"
                                  placeholder="<?php esc_attr_e( 'Observaciones sobre este préstamo…', 'aura-business-suite' ); ?>"></textarea>
                    </div>
                </div>

                <!-- Error general del modal -->
                <div id="aura-lib-loan-modal-error" class="aura-lib-loan-modal-error" style="display:none;"></div>

            </form>
        </div><!-- .aura-lib-modal-body -->

        <!-- Footer pegajoso -->
        <div class="aura-lib-loan-mfooter">
            <button type="button" class="button aura-lib-modal-close">
                <?php esc_html_e( 'Cancelar', 'aura-business-suite' ); ?>
            </button>
            <button type="button" id="aura-lib-loan-save" class="button button-primary aura-lib-loan-save-btn">
                <span class="aura-lib-btn-label">
                    <span class="dashicons dashicons-book-alt" style="vertical-align:middle;margin-right:3px;font-size:16px;height:16px;width:16px;"></span>
                    <?php esc_html_e( 'Registrar Préstamo', 'aura-business-suite' ); ?>
                </span>
                <span class="aura-lib-btn-loading" style="display:none;">
                    <span class="spinner is-active" style="float:none;margin:0;vertical-align:middle;"></span>
                    <?php esc_html_e( 'Guardando…', 'aura-business-suite' ); ?>
                </span>
            </button>
        </div>

    </div><!-- .aura-lib-modal-content -->
</div>

<!-- ╔══════════════════════════════════╗
     ║  Modal: Registrar Devolución     ║
     ╚══════════════════════════════════╝ -->
<div id="aura-lib-return-modal" class="aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-return-modal-title">
    <div class="aura-lib-modal-overlay"></div>
    <div class="aura-lib-modal-content">
        <div class="aura-lib-modal-header">
            <h2 id="aura-lib-return-modal-title"><?php esc_html_e( 'Registrar Devolución', 'aura-business-suite' ); ?></h2>
            <button type="button" class="aura-lib-modal-close dashicons dashicons-no-alt"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"></button>
        </div>
        <div class="aura-lib-modal-body">
            <form id="aura-lib-return-form" autocomplete="off">
                <?php wp_nonce_field( 'aura_library_nonce', 'aura_lib_return_nonce' ); ?>
                <input type="hidden" id="return_loan_id" name="loan_id">

                <!-- Info del préstamo -->
                <div id="aura-lib-return-info" class="aura-lib-return-info-box"></div>

                <div class="aura-lib-form-row">
                    <label for="return_date"><?php esc_html_e( 'Fecha de devolución *', 'aura-business-suite' ); ?></label>
                    <input type="date" id="return_date" name="return_date" class="regular-text" required>
                </div>

                <div class="aura-lib-form-row">
                    <label for="return_condition"><?php esc_html_e( 'Condición del libro', 'aura-business-suite' ); ?></label>
                    <select id="return_condition" name="return_condition" class="regular-text">
                        <option value="good"><?php      esc_html_e( 'Buena',      'aura-business-suite' ); ?></option>
                        <option value="damaged"><?php   esc_html_e( 'Deteriorado','aura-business-suite' ); ?></option>
                        <option value="lost"><?php      esc_html_e( 'Perdido',    'aura-business-suite' ); ?></option>
                    </select>
                </div>

                <div class="aura-lib-form-row">
                    <label for="return_notes"><?php esc_html_e( 'Notas de devolución', 'aura-business-suite' ); ?></label>
                    <textarea id="return_notes" name="return_notes" rows="2" class="large-text"></textarea>
                </div>

                <?php if ( $fines_enabled ) : ?>
                <!-- Panel de multa -->
                <div id="aura-lib-fine-panel" class="aura-lib-fine-panel" style="display:none;">
                    <h3><?php esc_html_e( 'Multa por retraso', 'aura-business-suite' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'Días de retraso:', 'aura-business-suite' ); ?>
                        <strong id="return-overdue-days">0</strong><br>
                        <?php esc_html_e( 'Importe de multa:', 'aura-business-suite' ); ?>
                        <strong id="return-fine-amount">$0.00</strong>
                    </p>
                    <label>
                        <input type="checkbox" id="return_pay_fine" name="pay_fine" value="1">
                        <?php esc_html_e( 'Marcar multa como pagada', 'aura-business-suite' ); ?>
                    </label>
                    <?php if ( $fines_to_finance ) : ?>
                    <br>
                    <label>
                        <input type="checkbox" id="return_to_finance" name="to_finance" value="1">
                        <?php esc_html_e( 'Registrar cobro en Finanzas', 'aura-business-suite' ); ?>
                    </label>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="aura-lib-modal-footer">
                    <button type="submit" class="button button-primary" id="aura-lib-return-save">
                        <?php esc_html_e( 'Confirmar Devolución', 'aura-business-suite' ); ?>
                    </button>
                    <button type="button" class="button aura-lib-modal-close">
                        <?php esc_html_e( 'Cancelar', 'aura-business-suite' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ╔══════════════════════════════════╗
     ║  Modal: Detalle del Préstamo     ║
     ╚══════════════════════════════════╝ -->
<div id="aura-lib-loan-detail-modal" class="aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-loan-detail-title">
    <div class="aura-lib-modal-overlay"></div>
    <div class="aura-lib-modal-content aura-lib-modal-large">
        <div class="aura-lib-modal-header">
            <h2 id="aura-lib-loan-detail-title"><?php esc_html_e( 'Detalle del Préstamo', 'aura-business-suite' ); ?></h2>
            <button type="button" class="aura-lib-modal-close dashicons dashicons-no-alt"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"></button>
        </div>
        <div class="aura-lib-modal-body" id="aura-lib-loan-detail-body">
            <span class="spinner is-active" style="float:none;"></span>
        </div>
    </div>
</div>

<!-- ╔══════════════════════════════════════════╗
     ║  Modal: Editar Préstamo                  ║
     ╚══════════════════════════════════════════╝ -->
<div id="aura-lib-edit-loan-modal" class="aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-edit-loan-title">
    <div class="aura-lib-modal-overlay"></div>
    <div class="aura-lib-modal-content">
        <div class="aura-lib-modal-header">
            <h2 id="aura-lib-edit-loan-title"><?php esc_html_e( 'Editar Préstamo', 'aura-business-suite' ); ?></h2>
            <button type="button" class="aura-lib-modal-close dashicons dashicons-no-alt"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"></button>
        </div>
        <div class="aura-lib-modal-body">
            <form id="aura-lib-edit-loan-form" autocomplete="off">
                <?php wp_nonce_field( 'aura_library_nonce', 'aura_lib_edit_loan_nonce' ); ?>
                <input type="hidden" id="edit_loan_id" name="loan_id">

                <!-- Info resumida del préstamo -->
                <div id="aura-lib-edit-loan-info" class="aura-lib-return-info-box"></div>

                <div class="aura-lib-form-row">
                    <label for="edit_loan_date"><?php esc_html_e( 'Fecha de préstamo *', 'aura-business-suite' ); ?></label>
                    <input type="date" id="edit_loan_date" name="loan_date" class="regular-text" required data-allow-future="1">
                </div>
                <div class="aura-lib-form-row">
                    <label for="edit_due_date"><?php esc_html_e( 'Fecha límite de devolución *', 'aura-business-suite' ); ?></label>
                    <input type="date" id="edit_due_date" name="due_date" class="regular-text" required data-allow-future="1">
                </div>
                <div class="aura-lib-form-row">
                    <label for="edit_status"><?php esc_html_e( 'Estado', 'aura-business-suite' ); ?></label>
                    <select id="edit_status" name="status" class="regular-text">
                        <option value="active"><?php   esc_html_e( 'Activo',    'aura-business-suite' ); ?></option>
                        <option value="overdue"><?php  esc_html_e( 'Vencido',   'aura-business-suite' ); ?></option>
                        <option value="extended"><?php esc_html_e( 'Extendido', 'aura-business-suite' ); ?></option>
                        <option value="lost"><?php     esc_html_e( 'Perdido',   'aura-business-suite' ); ?></option>
                    </select>
                </div>
                <div class="aura-lib-form-row">
                    <label for="edit_notes"><?php esc_html_e( 'Notas', 'aura-business-suite' ); ?></label>
                    <textarea id="edit_notes" name="notes" rows="3" class="large-text"
                              placeholder="<?php esc_attr_e( 'Observaciones sobre este préstamo…', 'aura-business-suite' ); ?>"></textarea>
                </div>

                <div id="aura-lib-edit-loan-error" class="aura-lib-loan-modal-error" style="display:none;"></div>

                <div class="aura-lib-modal-footer">
                    <button type="submit" class="button button-primary" id="aura-lib-edit-loan-save">
                        <?php esc_html_e( 'Guardar Cambios', 'aura-business-suite' ); ?>
                    </button>
                    <button type="button" class="button aura-lib-modal-close">
                        <?php esc_html_e( 'Cancelar', 'aura-business-suite' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ╔══════════════════════════════════════════╗
     ║  Modal: Extender Préstamo                ║
     ╚══════════════════════════════════════════╝ -->
<div id="aura-lib-extend-modal" class="aura-lib-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-lib-extend-modal-title">
    <div class="aura-lib-modal-overlay"></div>
    <div class="aura-lib-modal-content">
        <div class="aura-lib-modal-header">
            <h2 id="aura-lib-extend-modal-title"><?php esc_html_e( 'Extender Préstamo', 'aura-business-suite' ); ?></h2>
            <button type="button" class="aura-lib-modal-close dashicons dashicons-no-alt"
                    title="<?php esc_attr_e( 'Cerrar', 'aura-business-suite' ); ?>"></button>
        </div>
        <div class="aura-lib-modal-body">
            <form id="aura-lib-extend-form" autocomplete="off">
                <?php wp_nonce_field( 'aura_library_nonce', 'aura_lib_extend_nonce' ); ?>
                <input type="hidden" id="extend_loan_id" name="loan_id">

                <!-- Info del préstamo -->
                <div id="aura-lib-extend-info" class="aura-lib-return-info-box"></div>

                <div class="aura-lib-form-row">
                    <label><?php esc_html_e( 'Días a extender', 'aura-business-suite' ); ?></label>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <button type="button" class="button aura-lib-extend-preset" data-days="7">+7 <?php esc_html_e( 'días', 'aura-business-suite' ); ?></button>
                        <button type="button" class="button aura-lib-extend-preset" data-days="14">+14 <?php esc_html_e( 'días', 'aura-business-suite' ); ?></button>
                        <button type="button" class="button aura-lib-extend-preset" data-days="30">+30 <?php esc_html_e( 'días', 'aura-business-suite' ); ?></button>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:8px;">
                        <input type="number" id="extend_days" name="days" min="1" max="180" value="<?php echo esc_attr( $extension_days ); ?>" class="small-text" style="width:70px;">
                        <span style="color:#555;"><?php esc_html_e( 'días personalizados', 'aura-business-suite' ); ?></span>
                    </div>
                </div>

                <div class="aura-lib-form-row">
                    <label><?php esc_html_e( 'Nueva fecha límite', 'aura-business-suite' ); ?></label>
                    <strong id="extend-new-due" style="font-size:15px;color:#0073aa;">—</strong>
                </div>

                <div id="aura-lib-extend-error" class="aura-lib-loan-modal-error" style="display:none;"></div>

                <div class="aura-lib-modal-footer">
                    <button type="submit" class="button button-primary" id="aura-lib-extend-save">
                        <?php esc_html_e( 'Confirmar Extensión', 'aura-business-suite' ); ?>
                    </button>
                    <button type="button" class="button aura-lib-modal-close">
                        <?php esc_html_e( 'Cancelar', 'aura-business-suite' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$js_data = wp_json_encode( [
    'ajaxurl'          => admin_url( 'admin-ajax.php' ),
    'nonce'            => wp_create_nonce( 'aura_library_nonce' ),
    'can_create'       => $can_create,
    'can_return'       => $can_return,
    'can_extend'       => $can_extend,
    'can_edit'         => $can_edit,
    'can_delete'       => $can_delete,
    'can_view_all'     => $can_view_all,
    'can_view_fines'   => $can_view_fines,
    'fines_enabled'    => $fines_enabled,
    'fines_to_finance' => $fines_to_finance,
    'max_extensions'   => $max_extensions,
    'extension_days'   => $extension_days,
    'loan_days'        => (int) get_option( 'aura_library_loan_days', 14 ),
    'txt'              => [
        'loading'          => __( 'Cargando…',                                              'aura-business-suite' ),
        'no_results'       => __( 'No se encontraron préstamos.',                           'aura-business-suite' ),
        'error'            => __( 'Error al procesar la solicitud.',                        'aura-business-suite' ),
        'saved'            => __( 'Préstamo registrado correctamente.',                     'aura-business-suite' ),
        'returned'         => __( 'Devolución registrada correctamente.',                   'aura-business-suite' ),
        'extended'         => __( 'Préstamo extendido correctamente.',                      'aura-business-suite' ),
        'updated'          => __( 'Préstamo actualizado correctamente.',                    'aura-business-suite' ),
        'cancelled'        => __( 'Préstamo cancelado correctamente.',                     'aura-business-suite' ),
        'confirm_extend'   => __( '¿Extender este préstamo?',                              'aura-business-suite' ),
        'confirm_cancel'   => __( '¿Cancelar este préstamo? Esta acción no se puede deshacer.', 'aura-business-suite' ),
        'page_of'          => __( 'Página %1$s de %2$s',                                   'aura-business-suite' ),
        'n_items'          => __( '%s préstamos',                                           'aura-business-suite' ),
        'status_labels'    => [
            'active'    => __( 'Activo',    'aura-business-suite' ),
            'overdue'   => __( 'Vencido',   'aura-business-suite' ),
            'extended'  => __( 'Extendido', 'aura-business-suite' ),
            'returned'  => __( 'Devuelto',  'aura-business-suite' ),
            'lost'      => __( 'Perdido',   'aura-business-suite' ),
            'cancelled' => __( 'Cancelado', 'aura-business-suite' ),
        ],
        'condition_labels' => [
            'good'    => __( 'Buena',       'aura-business-suite' ),
            'damaged' => __( 'Deteriorado', 'aura-business-suite' ),
            'lost'    => __( 'Perdido',     'aura-business-suite' ),
        ],
    ],
] );
?>
<script>var auraLibraryLoans = <?php echo $js_data; // phpcs:ignore WordPress.Security.EscapeOutput ?>;</script>
