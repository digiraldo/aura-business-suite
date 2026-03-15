<?php
/**
 * Template: Libro Mayor por Usuario (Fase 6, Item 6.3)
 *
 * Accesible en: /wp-admin/admin.php?page=aura-user-ledger
 * Capability:   aura_finance_user_ledger
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -----------------------------------------------------------------------
// Parámetros GET & sanitización
// -----------------------------------------------------------------------
$req_user_id  = intval( $_GET['ledger_user_id']  ?? 0 );
$req_date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
$req_date_to   = sanitize_text_field( $_GET['date_to']   ?? '' );
$req_concept   = sanitize_key( $_GET['concept']           ?? '' );
$req_show_all  = ! empty( $_GET['show_all'] );
$req_paged     = max( 1, intval( $_GET['paged'] ?? 1 ) );

// Moneda
$currency = get_option( 'aura_currency_symbol', '$' );

// Conceptos
$concepts_map = Aura_Financial_User_Ledger::get_concepts_labels();

// -----------------------------------------------------------------------
// Datos del usuario seleccionado
// -----------------------------------------------------------------------
$selected_user = $req_user_id ? get_userdata( $req_user_id ) : null;

// Obtener todos los usuarios para el selector
$all_ledger_users = get_users( array( 'orderby' => 'display_name' ) );

$filters = [
    'date_from' => $req_date_from,
    'date_to'   => $req_date_to,
    'concept'   => $req_concept,
    'show_all'  => $req_show_all,
    'paged'     => $req_paged,
];

$rows   = [];
$totals = [ 'income' => 0.0, 'expense' => 0.0, 'net' => 0.0 ];
$total_rows = 0;

if ( $req_user_id && $selected_user ) {
    $rows       = Aura_Financial_User_Ledger::get_ledger_rows( $req_user_id, $filters );
    $totals     = Aura_Financial_User_Ledger::get_ledger_totals( $req_user_id, $filters );
    $total_rows = Aura_Financial_User_Ledger::count_ledger_rows( $req_user_id, $filters );
}

$per_page    = Aura_Financial_User_Ledger::PER_PAGE;
$total_pages = $total_rows > 0 ? (int) ceil( $total_rows / $per_page ) : 1;

// URL base para paginación (conservando todos los filtros excepto paged)
$base_url = add_query_arg(
    array_filter( [
        'page'           => 'aura-user-ledger',
        'ledger_user_id' => $req_user_id ?: null,
        'date_from'      => $req_date_from ?: null,
        'date_to'        => $req_date_to   ?: null,
        'concept'        => $req_concept   ?: null,
        'show_all'       => $req_show_all  ? '1' : null,
    ] ),
    admin_url( 'admin.php' )
);

// Nonce para exportación CSV
$export_nonce = wp_create_nonce( 'aura_transaction_nonce' );
$ajax_nonce   = wp_create_nonce( 'aura_transaction_nonce' );
?>

<div class="wrap aura-user-ledger-wrap">

    <!-- ===== ENCABEZADO ===== -->
    <h1 class="wp-heading-inline">
        📖 <?php _e( 'Libro Mayor por Usuario', 'aura-suite' ); ?>
    </h1>
    <?php if ( $selected_user ) : ?>
        <span class="aura-ledger-subtitle">
            — <?php echo esc_html( $selected_user->display_name ); ?>
            <small style="font-weight:400;color:#8c8f94;">(<?php echo esc_html( $selected_user->user_email ); ?>)</small>
        </span>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- ===== SELECTOR DE USUARIO ===== -->
    <div class="aura-ledger-user-selector">
        <div style="display: flex; gap: 20px; align-items: flex-start; margin-bottom: 20px;">
            <div style="flex: 0 0 400px;">
                <label for="user_select_ledger"><strong><?php _e( 'Seleccionar usuario:', 'aura-suite' ); ?></strong></label>
                <select id="user_select_ledger" name="ledger_user_id" class="regular-text" style="width: 100%; margin-top: 5px;">
                    <option value=""><?php _e( '-- Seleccionar Usuario --', 'aura-suite' ); ?></option>
                    <?php foreach ( $all_ledger_users as $user ) :
                        $avatar_url = get_avatar_url( $user->ID, ['size' => 32] );
                    ?>
                        <option value="<?php echo esc_attr( $user->ID ); ?>"
                                <?php selected( $req_user_id, $user->ID ); ?>
                                data-avatar="<?php echo esc_url( $avatar_url ); ?>"
                                data-email="<?php echo esc_attr( $user->user_email ); ?>">
                            <?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ( $selected_user ) :
                $selected_avatar = get_avatar_url( $selected_user->ID, ['size' => 64] );
            ?>
            <div id="selected-user-card" style="flex: 0 0 auto; background: #f9fafb; border: 3px solid #2563eb; border-radius: 12px; padding: 16px; display: flex; align-items: center; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <img src="<?php echo esc_url( $selected_avatar ); ?>"
                     alt="<?php echo esc_attr( $selected_user->display_name ); ?>"
                     style="width: 64px; height: 64px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div>
                    <h3 style="margin:0 0 5px 0; font-size:18px; color:#1f2937;"><?php echo esc_html( $selected_user->display_name ); ?></h3>
                    <p style="margin:0; font-size:13px; color:#6b7280;">
                        <span class="dashicons dashicons-email" style="font-size:13px;"></span>
                        <?php echo esc_html( $selected_user->user_email ); ?>
                    </p>
                </div>
            </div>
            <?php else : ?>
            <div id="selected-user-card" style="display: none;"></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( $req_user_id && $selected_user ) : ?>

    <!-- ===== FILTROS ===== -->
    <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="aura-ledger-filters">
        <input type="hidden" name="page"           value="aura-user-ledger">
        <input type="hidden" name="ledger_user_id" value="<?php echo esc_attr( $req_user_id ); ?>">

        <div class="aura-filter-row">
            <!-- Fecha desde -->
            <div class="aura-filter-group">
                <label for="lf_date_from"><?php _e( 'Desde', 'aura-suite' ); ?></label>
                <input type="date" id="lf_date_from" name="date_from" class="regular-text"
                       value="<?php echo esc_attr( $req_date_from ); ?>">
            </div>
            <!-- Fecha hasta -->
            <div class="aura-filter-group">
                <label for="lf_date_to"><?php _e( 'Hasta', 'aura-suite' ); ?></label>
                <input type="date" id="lf_date_to" name="date_to" class="regular-text"
                       value="<?php echo esc_attr( $req_date_to ); ?>">
            </div>
            <!-- Concepto -->
            <div class="aura-filter-group">
                <label for="lf_concept"><?php _e( 'Concepto', 'aura-suite' ); ?></label>
                <select id="lf_concept" name="concept">
                    <option value=""><?php _e( '— Todos —', 'aura-suite' ); ?></option>
                    <?php foreach ( $concepts_map as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $req_concept, $key ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Toggle estado -->
            <div class="aura-filter-group aura-filter-toggle">
                <label class="aura-toggle-label">
                    <input type="checkbox" name="show_all" value="1" <?php checked( $req_show_all ); ?>>
                    <?php _e( 'Incluir no aprobadas', 'aura-suite' ); ?>
                </label>
            </div>

            <div class="aura-filter-group aura-filter-actions">
                <button type="submit" class="button button-primary"><?php _e( 'Filtrar', 'aura-suite' ); ?></button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-user-ledger&ledger_user_id=' . $req_user_id ) ); ?>"
                   class="button"><?php _e( 'Limpiar', 'aura-suite' ); ?></a>
            </div>
        </div>
    </form>

    <!-- ===== TARJETAS DE RESUMEN ===== -->
    <div class="aura-ledger-summary-cards">
        <div class="aura-ls-card aura-ls-income">
            <span class="aura-ls-label"><?php _e( 'Total Ingresos', 'aura-suite' ); ?></span>
            <span class="aura-ls-value">+<?php echo esc_html( $currency . number_format( $totals['income'], 2, '.', ',' ) ); ?></span>
        </div>
        <div class="aura-ls-card aura-ls-expense">
            <span class="aura-ls-label"><?php _e( 'Total Egresos', 'aura-suite' ); ?></span>
            <span class="aura-ls-value">-<?php echo esc_html( $currency . number_format( $totals['expense'], 2, '.', ',' ) ); ?></span>
        </div>
        <div class="aura-ls-card <?php echo $totals['net'] >= 0 ? 'aura-ls-net-pos' : 'aura-ls-net-neg'; ?>">
            <span class="aura-ls-label"><?php _e( 'Balance Neto', 'aura-suite' ); ?></span>
            <span class="aura-ls-value">
                <?php echo ( $totals['net'] >= 0 ? '+' : '' ) . esc_html( $currency . number_format( abs( $totals['net'] ), 2, '.', ',' ) ); ?>
            </span>
        </div>
        <div class="aura-ls-card aura-ls-count">
            <span class="aura-ls-label"><?php _e( 'Movimientos', 'aura-suite' ); ?></span>
            <span class="aura-ls-value"><?php echo esc_html( number_format( $total_rows ) ); ?></span>
        </div>
    </div>

    <!-- ===== BARRA SUPERIOR: info paginación + exportar ===== -->
    <div class="aura-ledger-top-bar">
        <span class="aura-ledger-count-info">
            <?php
            if ( $total_rows > 0 ) {
                $from = ( $req_paged - 1 ) * $per_page + 1;
                $to   = min( $req_paged * $per_page, $total_rows );
                printf(
                    esc_html__( 'Mostrando %1$s–%2$s de %3$s movimientos', 'aura-suite' ),
                    number_format( $from ),
                    number_format( $to ),
                    number_format( $total_rows )
                );
            } else {
                _e( 'Sin movimientos para los filtros seleccionados.', 'aura-suite' );
            }
            ?>
        </span>

        <!-- Formulario de exportación CSV (POST via JS) -->
        <form id="aura-ledger-csv-form" method="post"
              action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
              style="display:inline;">
            <input type="hidden" name="action"    value="aura_export_ledger_csv">
            <input type="hidden" name="nonce"     value="<?php echo esc_attr( $export_nonce ); ?>">
            <input type="hidden" name="user_id"   value="<?php echo esc_attr( $req_user_id ); ?>">
            <input type="hidden" name="date_from" value="<?php echo esc_attr( $req_date_from ); ?>">
            <input type="hidden" name="date_to"   value="<?php echo esc_attr( $req_date_to ); ?>">
            <input type="hidden" name="concept"   value="<?php echo esc_attr( $req_concept ); ?>">
            <input type="hidden" name="show_all"  value="<?php echo $req_show_all ? '1' : ''; ?>">
            <button type="submit" class="button button-secondary aura-export-btn">
                ⬇ <?php _e( 'Exportar CSV', 'aura-suite' ); ?>
            </button>
        </form>
    </div>

    <!-- ===== TABLA PRINCIPAL ===== -->
    <?php if ( ! empty( $rows ) ) : ?>
    <div class="aura-ledger-table-wrap">
        <table class="wp-list-table widefat fixed striped aura-ledger-table">
            <thead>
                <tr>
                    <th class="col-date"><?php _e( 'Fecha', 'aura-suite' ); ?></th>
                    <th class="col-desc"><?php _e( 'Descripción', 'aura-suite' ); ?></th>
                    <th class="col-concept"><?php _e( 'Concepto', 'aura-suite' ); ?></th>
                    <th class="col-income"><?php _e( 'Ingreso', 'aura-suite' ); ?></th>
                    <th class="col-expense"><?php _e( 'Egreso', 'aura-suite' ); ?></th>
                    <th class="col-balance"><?php _e( 'Balance', 'aura-suite' ); ?></th>
                    <th class="col-status"><?php _e( 'Estado', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $row ) :
                    $is_income    = $row->transaction_type === 'expense'; // Perspectiva usuario: egreso org = ingreso usuario
                    $amount       = (float) $row->amount;
                    $balance      = (float) $row->running_balance;
                    $concept_lbl  = $concepts_map[ $row->related_user_concept ] ?? $row->related_user_concept;
                    $date_fmt     = date_i18n( 'd/m/Y', strtotime( $row->transaction_date ) );

                    $status_badge = match ( $row->status ) {
                        'approved' => '<span class="aura-badge aura-badge-approved">✅ Aprobado</span>',
                        'pending'  => '<span class="aura-badge aura-badge-pending">⏳ Pendiente</span>',
                        'rejected' => '<span class="aura-badge aura-badge-rejected">❌ Rechazado</span>',
                        default    => '<span class="aura-badge">' . esc_html( ucfirst( $row->status ) ) . '</span>',
                    };
                ?>
                <tr class="<?php echo $is_income ? 'aura-row-income' : 'aura-row-expense'; ?>">
                    <td class="col-date"><?php echo esc_html( $date_fmt ); ?></td>
                    <td class="col-desc">
                        <?php echo esc_html( $row->description ); ?>
                        <?php if ( ! empty( $row->reference_number ) ) : ?>
                            <br><small class="aura-ref-num">#<?php echo esc_html( $row->reference_number ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="col-concept"><span class="aura-concept-tag"><?php echo esc_html( $concept_lbl ); ?></span></td>
                    <td class="col-income <?php echo $is_income ? 'aura-cell-income' : 'aura-cell-empty'; ?>">
                        <?php echo $is_income ? esc_html( $currency . number_format( $amount, 2, '.', ',' ) ) : '—'; ?>
                    </td>
                    <td class="col-expense <?php echo ! $is_income ? 'aura-cell-expense' : 'aura-cell-empty'; ?>">
                        <?php echo ! $is_income ? esc_html( $currency . number_format( $amount, 2, '.', ',' ) ) : '—'; ?>
                    </td>
                    <td class="col-balance <?php echo $balance >= 0 ? 'aura-balance-pos' : 'aura-balance-neg'; ?>">
                        <?php echo esc_html( ( $balance >= 0 ? '' : '-' ) . $currency . number_format( abs( $balance ), 2, '.', ',' ) ); ?>
                    </td>
                    <td class="col-status"><?php echo $status_badge; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="aura-ledger-totals-row">
                    <td colspan="3"><strong><?php _e( 'TOTAL PERÍODO', 'aura-suite' ); ?></strong></td>
                    <td class="col-income aura-cell-income">
                        <strong><?php echo esc_html( $currency . number_format( $totals['income'], 2, '.', ',' ) ); ?></strong>
                    </td>
                    <td class="col-expense aura-cell-expense">
                        <strong><?php echo esc_html( $currency . number_format( $totals['expense'], 2, '.', ',' ) ); ?></strong>
                    </td>
                    <td class="col-balance <?php echo $totals['net'] >= 0 ? 'aura-balance-pos' : 'aura-balance-neg'; ?>">
                        <strong>
                            <?php echo esc_html( ( $totals['net'] >= 0 ? '+' : '-' ) . $currency . number_format( abs( $totals['net'] ), 2, '.', ',' ) ); ?>
                        </strong>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- ===== PAGINACIÓN ===== -->
    <?php if ( $total_pages > 1 ) : ?>
    <div class="aura-ledger-pagination tablenav">
        <div class="tablenav-pages">
            <?php
            echo paginate_links( [
                'base'      => add_query_arg( 'paged', '%#%', $base_url ),
                'format'    => '',
                'current'   => $req_paged,
                'total'     => $total_pages,
                'prev_text' => '&laquo; ' . __( 'Anterior', 'aura-suite' ),
                'next_text' => __( 'Siguiente', 'aura-suite' ) . ' &raquo;',
                'type'      => 'plain',
            ] );
            ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <div class="notice notice-info inline" style="margin-top:20px;">
        <p>
            <?php if ( $req_user_id ) : ?>
                <?php _e( 'No hay movimientos para los filtros seleccionados.', 'aura-suite' ); ?>
            <?php else : ?>
                <?php _e( 'Selecciona un usuario para ver su libro mayor.', 'aura-suite' ); ?>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; // rows ?>

    <?php endif; // selected_user ?>

</div><!-- .aura-user-ledger-wrap -->

<!-- ===== ESTILOS ===== -->
<style>
.aura-user-ledger-wrap { max-width: 1200px; }
.aura-ledger-subtitle { font-size: 18px; color: #3c434a; margin-left: 8px; }

/* Selector usuario */
.aura-ledger-user-selector {
    background: #f9f9f9;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.aura-ledger-user-selector label { 
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

#user_select_ledger {
    padding: 8px 12px;
    font-size: 14px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
}

/* Filtros */
.aura-ledger-filters { margin-bottom: 18px; }
.aura-filter-row {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
    padding: 12px 16px;
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 6px;
}
.aura-filter-group { display: flex; flex-direction: column; gap: 4px; }
.aura-filter-group label { font-size: 12px; font-weight: 600; color: #3c434a; }
.aura-filter-group select,
.aura-filter-group input[type="date"] { font-size: 13px; }
.aura-filter-toggle { justify-content: flex-end; }
.aura-toggle-label { display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer; }
.aura-filter-actions { display: flex; gap: 6px; align-items: flex-end; padding-bottom: 0; }

/* Tarjetas resumen */
.aura-ledger-summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.aura-ls-card {
    padding: 14px 18px;
    border-radius: 8px;
    border-left: 4px solid #ccc;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
}
.aura-ls-income  { border-color: #00a32a; }
.aura-ls-expense { border-color: #d63638; }
.aura-ls-net-pos { border-color: #0073aa; }
.aura-ls-net-neg { border-color: #d63638; }
.aura-ls-count   { border-color: #8c8f94; }
.aura-ls-label { display: block; font-size: 11px; text-transform: uppercase; color: #8c8f94; letter-spacing: .5px; }
.aura-ls-value { display: block; font-size: 20px; font-weight: 700; margin-top: 4px; }
.aura-ls-income  .aura-ls-value { color: #00a32a; }
.aura-ls-expense .aura-ls-value { color: #d63638; }
.aura-ls-net-pos .aura-ls-value { color: #0073aa; }
.aura-ls-net-neg .aura-ls-value { color: #d63638; }

/* Barra superior */
.aura-ledger-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
}
.aura-ledger-count-info { color: #50575e; font-size: 13px; }
.aura-export-btn { font-size: 13px !important; }

/* Tabla */
.aura-ledger-table-wrap { overflow-x: auto; }
.aura-ledger-table th,
.aura-ledger-table td { font-size: 13px; padding: 8px 12px; vertical-align: middle; }
.col-date    { width: 90px; white-space: nowrap; }
.col-concept { width: 190px; }
.col-income  { width: 120px; text-align: right; }
.col-expense { width: 120px; text-align: right; }
.col-balance { width: 130px; text-align: right; font-weight: 600; }
.col-status  { width: 120px; text-align: center; }

.aura-row-income  { border-left: 3px solid #00a32a20; }
.aura-row-expense { border-left: 3px solid #d6363820; }
.aura-cell-income  { color: #00a32a; font-weight: 600; }
.aura-cell-expense { color: #d63638; font-weight: 600; }
.aura-cell-empty   { color: #c3c4c7; }
.aura-balance-pos  { color: #0073aa; }
.aura-balance-neg  { color: #d63638; }
.aura-concept-tag {
    display: inline-block;
    background: #f0f6fc;
    border: 1px solid #c3d9f0;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 12px;
    color: #0073aa;
}
.aura-ref-num { color: #8c8f94; font-size: 11px; }

/* Badges estado */
.aura-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.aura-badge-approved { background: #ecfdf5; color: #00a32a; }
.aura-badge-pending  { background: #fffbeb; color: #b45309; }
.aura-badge-rejected { background: #fef2f2; color: #d63638; }

/* Fila totales */
.aura-ledger-totals-row td { font-size: 14px; background: #f6f7f7 !important; border-top: 2px solid #dee0e2; }
.aura-ledger-totals-row .aura-cell-income  { font-size: 14px; color: #00a32a; }
.aura-ledger-totals-row .aura-cell-expense { font-size: 14px; color: #d63638; }

/* Paginación */
.aura-ledger-pagination { margin-top: 16px; }
.aura-ledger-pagination .tablenav-pages { float: none; text-align: right; }

/* jQuery UI autocomplete */
.ui-autocomplete .ui-menu-item-wrapper {
    padding: 6px 12px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ui-autocomplete .ui-menu-item-wrapper img {
    width: 28px; height: 28px; border-radius: 50%;
}
</style>

<!-- ===== JAVASCRIPT ===== -->
<script>
jQuery( function($) {
    'use strict';

    var $select = $( '#user_select_ledger' );
    var $card   = $( '#selected-user-card' );

    // -------------------------------------------------------------------
    // Cambio de usuario → Recargar página con el usuario seleccionado
    // -------------------------------------------------------------------
    $select.on( 'change', function() {
        var userId = $(this).val();
        
        if ( userId ) {
            // Mostrar tarjeta con avatar del usuario seleccionado
            var $option = $(this).find('option:selected');
            var avatar  = $option.data('avatar');
            var email   = $option.data('email');
            var name    = $option.text().split(' (')[0]; // Extraer nombre sin email
            
            if ( avatar ) {
                $card.html(
                    '<img src="' + avatar + '" alt="' + name + '" ' +
                    'style="width: 64px; height: 64px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">' +
                    '<div>' +
                    '<h3 style="margin:0 0 5px 0; font-size:18px; color:#1f2937;">' + name + '</h3>' +
                    '<p style="margin:0; font-size:13px; color:#6b7280;">' +
                    '<span class="dashicons dashicons-email" style="font-size:13px;"></span> ' + email +
                    '</p>' +
                    '</div>'
                ).css({
                    'flex': '0 0 auto',
                    'background': '#f9fafb',
                    'border': '3px solid #2563eb',
                    'border-radius': '12px',
                    'padding': '16px',
                    'display': 'flex',
                    'align-items': 'center',
                    'gap': '16px',
                    'box-shadow': '0 1px 3px rgba(0,0,0,0.1)'
                }).show();
            }
            
            // Redirigir automáticamente al libro mayor del usuario
            var url = '<?php echo esc_js( admin_url( 'admin.php?page=aura-user-ledger' ) ); ?>' + '&ledger_user_id=' + userId;
            window.location.href = url;
        } else {
            $card.hide();
        }
    });

} );
</script>
