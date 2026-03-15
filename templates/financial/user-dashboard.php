<?php
/**
 * Template: Dashboard Financiero Personal del Usuario (Fase 6, Item 6.2)
 *
 * Muestra el resumen financiero personalizado de cada usuario con:
 * - Tarjetas de estadísticas (cobros, pagos, saldo neto, pendientes)
 * - Tabla de movimientos paginada con filtros
 * - Sección de equipos a cargo (desde módulo inventario)
 * - Selector de usuario para administradores (view_others_summary)
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_user_id   = get_current_user_id();
$can_view_others   = current_user_can( 'aura_finance_view_others_summary' ) || current_user_can( 'manage_options' );
$viewing_user_id   = $current_user_id;

// Admin viendo otro usuario
if ( $can_view_others && ! empty( $_GET['view_user'] ) ) {
    $req_id = intval( $_GET['view_user'] );
    if ( get_userdata( $req_id ) ) {
        $viewing_user_id = $req_id;
    }
}

$viewing_user_data = get_userdata( $viewing_user_id );

// Filtros desde GET
$filters = [
    'date_from' => sanitize_text_field( $_GET['date_from'] ?? '' ),
    'date_to'   => sanitize_text_field( $_GET['date_to']   ?? '' ),
    'concept'   => sanitize_key( $_GET['concept']           ?? '' ),
    'status'    => sanitize_key( $_GET['status']            ?? '' ),
    'paged'     => max( 1, intval( $_GET['paged']           ?? 1 ) ),
];

// Datos
$summary    = Aura_Financial_User_Dashboard::get_user_financial_summary( $viewing_user_id );
$movements  = Aura_Financial_User_Dashboard::get_recent_movements( $viewing_user_id, 0, $filters );
$total_movs = Aura_Financial_User_Dashboard::count_movements( $viewing_user_id, $filters );
$loans      = Aura_Financial_User_Dashboard::get_inventory_loans( $viewing_user_id );
$pending    = Aura_Financial_User_Dashboard::count_pending_for_user( $viewing_user_id );
$concepts   = Aura_Financial_User_Dashboard::get_concepts_labels();
$currency   = get_option( 'aura_currency_symbol', '$' );
$per_page   = 20;
$total_pages= ceil( $total_movs / $per_page );

$income  = 0.0;
$expense = 0.0;

if ( ! is_wp_error( $summary ) ) {
    foreach ( $summary['totals'] as $row ) {
        // Perspectiva usuario: egreso org → usuario = ingreso usuario; ingreso org ← usuario = egreso usuario
        if ( $row->transaction_type === 'expense' ) $income  = (float) $row->total;
        if ( $row->transaction_type === 'income'  ) $expense = (float) $row->total;
    }
}

$balance     = $income - $expense;
$base_url    = admin_url( 'admin.php?page=aura-my-finance' );
$nonce_field = wp_create_nonce( 'aura_user_dashboard_nonce' );
?>

<div class="wrap aura-user-dashboard-page">

    <!-- Cabecera -->
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-id-alt" style="font-size:28px;vertical-align:middle;margin-right:8px;"></span>
        <?php
        if ( $viewing_user_id === $current_user_id ) {
            _e( 'Mi Dashboard Financiero', 'aura-suite' );
        } else {
            printf(
                __( 'Dashboard Financiero: %s', 'aura-suite' ),
                '<strong>' . esc_html( $viewing_user_data ? $viewing_user_data->display_name : "Usuario #{$viewing_user_id}" ) . '</strong>'
            );
        }
        ?>
    </h1>

    <?php if ( $can_view_others ) : ?>
    <button type="button" id="aura-view-my-summary-btn" class="page-title-action" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; cursor: pointer;">
        <span class="dashicons dashicons-admin-users" style="font-size: 16px; vertical-align: middle; margin-right: 4px;"></span>
        <?php _e( 'Ver mi resumen', 'aura-suite' ); ?>
    </button>
    <?php endif; ?>

    <a href="<?php echo esc_url( add_query_arg( [
        'action'    => 'aura_export_personal_finance_csv',
        'user_id'   => $viewing_user_id,
        'date_from' => $filters['date_from'],
        'date_to'   => $filters['date_to'],
        'concept'   => $filters['concept'],
        'status'    => $filters['status'],
        'nonce'     => $nonce_field,
    ], admin_url( 'admin-ajax.php' ) ) ); ?>"
       class="page-title-action" style="background: #10b981; color: #fff; border: none;">
        <span class="dashicons dashicons-download" style="font-size: 16px; vertical-align: middle; margin-right: 4px;"></span>
        <?php _e( 'Exportar CSV', 'aura-suite' ); ?>
    </a>

    <button type="button" id="aura-print-dashboard-btn" class="page-title-action" style="background: #f59e0b; color: #fff; border: none; cursor: pointer;">
        <span class="dashicons dashicons-printer" style="font-size: 16px; vertical-align: middle; margin-right: 4px;"></span>
        <?php _e( 'Imprimir / PDF', 'aura-suite' ); ?>
    </button>

    <hr class="wp-header-end">

    <?php if ( is_wp_error( $summary ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html( $summary->get_error_message() ); ?></p></div>
        <?php return; ?>
    <?php endif; ?>

    <!-- ====================================================
         Selector de usuario (solo admins / view_others)
    ===================================================== -->
    <?php if ( $can_view_others ) : ?>
    <div class="aura-user-selector-bar postbox" style="padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <span class="dashicons dashicons-admin-users" style="font-size:24px;color:#8c8f94;"></span>
        <strong><?php _e( 'Ver resumen de otro usuario:', 'aura-suite' ); ?></strong>
        <div class="aura-user-autocomplete-wrap" style="flex:1;max-width:360px;">
            <input type="text"
                   id="ud-user-search"
                   placeholder="<?php esc_attr_e( 'Buscar usuario por nombre o email…', 'aura-suite' ); ?>"
                   autocomplete="off"
                   class="regular-text"
                   value="<?php echo $viewing_user_id !== $current_user_id && $viewing_user_data ? esc_attr( $viewing_user_data->display_name ) : ''; ?>"
                   style="width:100%;">
            <input type="hidden" id="ud-user-id" value="<?php echo esc_attr( $viewing_user_id !== $current_user_id ? $viewing_user_id : '' ); ?>">
        </div>
        <button type="button" id="ud-view-user-btn" class="button button-primary">
            <?php _e( 'Ver Dashboard', 'aura-suite' ); ?>
        </button>
        <?php if ( $viewing_user_id !== $current_user_id ) : ?>
        <a href="<?php echo esc_url( $base_url ); ?>" class="button button-secondary">
            <?php _e( 'Volver a mi resumen', 'aura-suite' ); ?>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ====================================================
         Tarjetas de estadísticas
    ===================================================== -->
    <div class="aura-ud-stats-grid">

        <div class="aura-ud-stat-card income">
            <div class="aura-ud-stat-icon-wrapper">
                <div class="aura-ud-stat-icon">💰</div>
            </div>
            <div class="aura-ud-stat-body">
                <span class="aura-ud-stat-label"><?php _e( 'Cobros Recibidos', 'aura-suite' ); ?></span>
                <span class="aura-ud-stat-value"><?php echo esc_html( $currency . number_format( $income, 2, '.', ',' ) ); ?></span>
                <span class="aura-ud-stat-sub"><?php _e( 'movimientos aprobados', 'aura-suite' ); ?></span>
            </div>
            <div class="aura-ud-stat-trend">↗ +15%</div>
        </div>

        <div class="aura-ud-stat-card expense">
            <div class="aura-ud-stat-icon-wrapper">
                <div class="aura-ud-stat-icon">💳</div>
            </div>
            <div class="aura-ud-stat-body">
                <span class="aura-ud-stat-label"><?php _e( 'Pagos Realizados', 'aura-suite' ); ?></span>
                <span class="aura-ud-stat-value"><?php echo esc_html( $currency . number_format( $expense, 2, '.', ',' ) ); ?></span>
                <span class="aura-ud-stat-sub"><?php _e( 'movimientos aprobados', 'aura-suite' ); ?></span>
            </div>
            <div class="aura-ud-stat-trend down">↘ -8%</div>
        </div>

        <div class="aura-ud-stat-card balance <?php echo $balance >= 0 ? 'positive' : 'negative'; ?>">
            <div class="aura-ud-stat-icon-wrapper">
                <div class="aura-ud-stat-icon"><?php echo $balance >= 0 ? '✨' : '⚠️'; ?></div>
            </div>
            <div class="aura-ud-stat-body">
                <span class="aura-ud-stat-label"><?php _e( 'Saldo Neto Personal', 'aura-suite' ); ?></span>
                <span class="aura-ud-stat-value">
                    <?php echo esc_html( ( $balance >= 0 ? '+' : '' ) . $currency . number_format( abs( $balance ), 2, '.', ',' ) ); ?>
                </span>
                <span class="aura-ud-stat-sub"><?php _e( 'ingresos - egresos', 'aura-suite' ); ?></span>
            </div>
        </div>

        <div class="aura-ud-stat-card pending <?php echo $pending > 0 ? 'has-pending' : ''; ?>">
            <div class="aura-ud-stat-icon-wrapper">
                <div class="aura-ud-stat-icon">⏰</div>
            </div>
            <div class="aura-ud-stat-body">
                <span class="aura-ud-stat-label"><?php _e( 'Pendientes de Pago', 'aura-suite' ); ?></span>
                <span class="aura-ud-stat-value"><?php echo esc_html( $pending ); ?></span>
                <span class="aura-ud-stat-sub"><?php _e( 'sin aprobar aún', 'aura-suite' ); ?></span>
            </div>
            <?php if ( $pending > 0 ) : ?>
            <div class="aura-ud-stat-alert">⚡ Acción requerida</div>
            <?php endif; ?>
        </div>

    </div><!-- .aura-ud-stats-grid -->

    <!-- ====================================================
         Filtros de búsqueda
    ===================================================== -->
    <div class="postbox aura-ud-filters" style="padding:14px 18px;margin-bottom:20px;">
        <form method="GET" action="">
            <input type="hidden" name="page" value="aura-my-finance">
            <?php if ( $viewing_user_id !== $current_user_id ) : ?>
            <input type="hidden" name="view_user" value="<?php echo esc_attr( $viewing_user_id ); ?>">
            <?php endif; ?>

            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">

                <div class="aura-ud-filter-group">
                    <label for="ud-date-from"><?php _e( 'Desde', 'aura-suite' ); ?></label>
                    <input type="date" id="ud-date-from" name="date_from"
                           value="<?php echo esc_attr( $filters['date_from'] ); ?>">
                </div>

                <div class="aura-ud-filter-group">
                    <label for="ud-date-to"><?php _e( 'Hasta', 'aura-suite' ); ?></label>
                    <input type="date" id="ud-date-to" name="date_to"
                           value="<?php echo esc_attr( $filters['date_to'] ); ?>">
                </div>

                <div class="aura-ud-filter-group">
                    <label for="ud-concept"><?php _e( 'Concepto', 'aura-suite' ); ?></label>
                    <select id="ud-concept" name="concept">
                        <option value=""><?php _e( 'Todos', 'aura-suite' ); ?></option>
                        <?php foreach ( $concepts as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>"
                            <?php selected( $filters['concept'], $val ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="aura-ud-filter-group">
                    <label for="ud-status"><?php _e( 'Estado', 'aura-suite' ); ?></label>
                    <select id="ud-status" name="status">
                        <option value=""><?php _e( 'Todos', 'aura-suite' ); ?></option>
                        <option value="approved" <?php selected( $filters['status'], 'approved' ); ?>><?php _e( '✅ Aprobados', 'aura-suite' ); ?></option>
                        <option value="pending"  <?php selected( $filters['status'], 'pending' ); ?>><?php _e( '⏳ Pendientes', 'aura-suite' ); ?></option>
                        <option value="rejected" <?php selected( $filters['status'], 'rejected' ); ?>><?php _e( '❌ Rechazados', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="aura-ud-filter-group">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-filter" style="font-size:14px;vertical-align:middle;margin-right:4px;"></span>
                        <?php _e( 'Filtrar', 'aura-suite' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $base_url . ( $viewing_user_id !== $current_user_id ? '&view_user=' . $viewing_user_id : '' ) ); ?>"
                       class="button button-secondary" style="margin-left:4px;">
                        <?php _e( 'Limpiar', 'aura-suite' ); ?>
                    </a>
                </div>

            </div>
        </form>
    </div><!-- .aura-ud-filters -->

    <!-- ====================================================
         Tabla de movimientos
    ===================================================== -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle" style="padding:12px 16px;">
                <span class="dashicons dashicons-list-view" style="margin-right:6px;"></span>
                <?php _e( 'Movimientos que me involucran', 'aura-suite' ); ?>
                <span class="aura-ud-count">(<?php echo esc_html( $total_movs ); ?>)</span>
            </h2>
        </div>
        <div class="inside" style="padding:0;">

            <?php if ( empty( $movements ) ) : ?>
            <p class="aura-ud-empty">
                <span class="dashicons dashicons-info" style="font-size:36px;color:#ccc;display:block;margin-bottom:8px;"></span>
                <?php _e( 'No se encontraron movimientos para los filtros seleccionados.', 'aura-suite' ); ?>
            </p>
            <?php else : ?>

            <table class="wp-list-table widefat aura-ud-movements-table">
                <thead>
                    <tr>
                        <th scope="col" style="width:90px"><?php _e( 'Fecha', 'aura-suite' ); ?></th>
                        <th scope="col" style="width:60px;text-align:center"><?php _e( 'Tipo', 'aura-suite' ); ?></th>
                        <th scope="col"><?php _e( 'Descripción', 'aura-suite' ); ?></th>
                        <th scope="col" style="width:140px"><?php _e( 'Concepto', 'aura-suite' ); ?></th>
                        <th scope="col" style="width:130px;text-align:right"><?php _e( 'Monto', 'aura-suite' ); ?></th>
                        <th scope="col" style="width:140px"><?php _e( 'Registrado por', 'aura-suite' ); ?></th>
                        <th scope="col" style="width:100px;text-align:center"><?php _e( 'Estado', 'aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $movements as $mov ) :
                        $is_income     = ( $mov->transaction_type === 'expense' ); // Perspectiva usuario: egreso org = ingreso usuario
                        $concept_label = $concepts[ $mov->related_user_concept ] ?? ( $mov->related_user_concept ?: '—' );
                        $date_fmt      = date_i18n( 'd M Y', strtotime( $mov->transaction_date ) );
                        $amount_fmt    = $currency . number_format( (float) $mov->amount, 2, '.', ',' );
                        $creator       = get_userdata( $mov->created_by );
                        $creator_name  = $creator ? $creator->display_name : 'Sistema';
                        $creator_avatar = $creator ? get_avatar_url( $creator->ID, ['size' => 32] ) : '';

                        switch ( $mov->status ) {
                            case 'approved': $status_html = '<span class="aura-ud-badge approved"><span class="dashicons dashicons-yes-alt"></span> ' . __( 'Aprobado', 'aura-suite' ) . '</span>'; break;
                            case 'pending':  $status_html = '<span class="aura-ud-badge pending"><span class="dashicons dashicons-clock"></span> ' . __( 'Pendiente', 'aura-suite' ) . '</span>'; break;
                            case 'rejected': $status_html = '<span class="aura-ud-badge rejected"><span class="dashicons dashicons-dismiss"></span> ' . __( 'Rechazado', 'aura-suite' ) . '</span>'; break;
                            default:         $status_html = '<span class="aura-ud-badge">' . esc_html( $mov->status ) . '</span>';
                        }
                        
                        $row_class = $is_income ? 'aura-row-income' : 'aura-row-expense';
                    ?>
                    <tr class="<?php echo esc_attr( $row_class ); ?>">
                        <td style="font-weight:600;"><?php echo esc_html( $date_fmt ); ?></td>
                        <td style="text-align:center;">
                            <span class="aura-type-badge <?php echo $is_income ? 'income' : 'expense'; ?>">
                                <?php echo $is_income ? '↗' : '↙'; ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo esc_html( $mov->description ); ?></strong>
                        </td>
                        <td>
                            <span class="aura-concept-badge">
                                <span class="dashicons dashicons-tag" style="font-size:14px;"></span>
                                <?php echo esc_html( $concept_label ); ?>
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <span class="aura-amount <?php echo $is_income ? 'income' : 'expense'; ?>">
                                <?php echo $is_income ? '+' : '-'; ?>
                                <?php echo esc_html( $amount_fmt ); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <?php if ( $creator_avatar ) : ?>
                                <img src="<?php echo esc_url( $creator_avatar ); ?>" 
                                     title="<?php echo esc_attr( $creator_name ); ?>"
                                     style="width:24px;height:24px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.2);" />
                                <?php endif; ?>
                                <small><?php echo esc_html( $creator_name ); ?></small>
                            </div>
                        </td>
                        <td style="text-align:center;"><?php echo $status_html; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Paginación
            if ( $total_pages > 1 ) :
                $page_links = paginate_links( [
                    'base'      => add_query_arg( 'paged', '%#%', $base_url ),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $total_pages,
                    'current'   => $filters['paged'],
                ] );
                echo '<div class="tablenav bottom"><div class="tablenav-pages" style="margin:10px 16px;">' . $page_links . '</div></div>';
            endif;
            ?>
            <?php endif; ?>

        </div>
    </div><!-- .postbox (movimientos) -->

    <!-- ====================================================
         Equipos a cargo (inventario)
    ===================================================== -->
    <?php if ( ! empty( $loans ) ) : ?>
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle" style="padding:12px 16px;">
                <span class="dashicons dashicons-archive" style="margin-right:6px;"></span>
                <?php _e( 'Equipos a mi cargo', 'aura-suite' ); ?>
                <span class="aura-ud-count">(<?php echo count( $loans ); ?>)</span>
            </h2>
        </div>
        <div class="inside" style="padding:0;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Equipo', 'aura-suite' ); ?></th>
                        <th style="width:130px"><?php _e( 'Fecha préstamo', 'aura-suite' ); ?></th>
                        <th style="width:160px"><?php _e( 'Devolución estimada', 'aura-suite' ); ?></th>
                        <th style="width:100px;text-align:center"><?php _e( 'Estado', 'aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $loans as $loan ) :
                        $return_date = $loan->expected_return_date
                            ? date_i18n( 'd/m/Y', strtotime( $loan->expected_return_date ) )
                            : '—';
                        $overdue = $loan->expected_return_date && strtotime( $loan->expected_return_date ) < time();
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $loan->item_name ); ?></strong></td>
                        <td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $loan->loan_date ) ) ); ?></td>
                        <td><?php echo esc_html( $return_date ); ?></td>
                        <td style="text-align:center;">
                            <?php if ( $overdue ) : ?>
                                <span class="aura-ud-badge rejected">⚠️ <?php _e( 'Vencido', 'aura-suite' ); ?></span>
                            <?php else : ?>
                                <span class="aura-ud-badge approved">✅ <?php _e( 'Activo', 'aura-suite' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div><!-- .wrap.aura-user-dashboard-page -->

<!-- Modal para "Ver mi resumen" -->
<div id="aura-summary-modal" class="aura-modal" style="display:none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header">
            <h2>
                <span class="dashicons dashicons-analytics" style="font-size:24px;vertical-align:middle;margin-right:8px;"></span>
                <?php _e( 'Mi Resumen Financiero Completo', 'aura-suite' ); ?>
            </h2>
            <button type="button" class="aura-modal-close">&times;</button>
        </div>
        <div class="aura-modal-body">
            <div class="aura-summary-cards">
                <div class="aura-summary-card income-card">
                    <div class="aura-summary-icon">💰</div>
                    <div class="aura-summary-info">
                        <div class="aura-summary-label"><?php _e( 'Total Ingresos', 'aura-suite' ); ?></div>
                        <div class="aura-summary-value income"><?php echo esc_html( $currency . number_format( $income, 2, '.', ',' ) ); ?></div>
                    </div>
                </div>
                <div class="aura-summary-card expense-card">
                    <div class="aura-summary-icon">💳</div>
                    <div class="aura-summary-info">
                        <div class="aura-summary-label"><?php _e( 'Total Egresos', 'aura-suite' ); ?></div>
                        <div class="aura-summary-value expense"><?php echo esc_html( $currency . number_format( $expense, 2, '.', ',' ) ); ?></div>
                    </div>
                </div>
                <div class="aura-summary-card balance-card <?php echo $balance >= 0 ? 'positive' : 'negative'; ?>">
                    <div class="aura-summary-icon"><?php echo $balance >= 0 ? '✨' : '⚠️'; ?></div>
                    <div class="aura-summary-info">
                        <div class="aura-summary-label"><?php _e( 'Balance Neto', 'aura-suite' ); ?></div>
                        <div class="aura-summary-value balance"><?php echo esc_html( ( $balance >= 0 ? '+' : '' ) . $currency . number_format( abs( $balance ), 2, '.', ',' ) ); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="aura-summary-chart" style="margin-top:30px;">
                <h3><?php _e( 'Distribución de Movimientos', 'aura-suite' ); ?></h3>
                <div style="display:flex;gap:20px;align-items:center;justify-content:center;">
                    <div style="text-align:center;">
                        <div style="width:120px;height:120px;border-radius:50%;background:conic-gradient(#10b981 0deg <?php echo $income > 0 ? ( $income / ( $income + $expense ) * 360 ) : 0; ?>deg, #ef4444 <?php echo $income > 0 ? ( $income / ( $income + $expense ) * 360 ) : 0; ?>deg);margin:0 auto 10px;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                        <strong><?php _e( 'Proporción Ingresos/Egresos', 'aura-suite' ); ?></strong>
                    </div>
                    <div>
                        <div style="margin-bottom:10px;">
                            <span style="display:inline-block;width:20px;height:20px;background:#10b981;border-radius:4px;vertical-align:middle;"></span>
                            <strong style="margin-left:8px;"><?php _e( 'Ingresos:', 'aura-suite' ); ?></strong>
                            <?php echo esc_html( $currency . number_format( $income, 2, '.', ',' ) ); ?>
                            <small style="color:#6b7280;">(<?php echo $income + $expense > 0 ? number_format( ( $income / ( $income + $expense ) ) * 100, 1 ) : 0; ?>%)</small>
                        </div>
                        <div>
                            <span style="display:inline-block;width:20px;height:20px;background:#ef4444;border-radius:4px;vertical-align:middle;"></span>
                            <strong style="margin-left:8px;"><?php _e( 'Egresos:', 'aura-suite' ); ?></strong>
                            <?php echo esc_html( $currency . number_format( $expense, 2, '.', ',' ) ); ?>
                            <small style="color:#6b7280;">(<?php echo $income + $expense > 0 ? number_format( ( $expense / ( $income + $expense ) ) * 100, 1 ) : 0; ?>%)</small>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ( $pending > 0 ) : ?>
            <div class="aura-summary-alert" style="margin-top:20px;background:#fef3c7;border-left:4px solid #f59e0b;padding:15px;border-radius:8px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:24px;">⚡</span>
                    <div>
                        <strong style="color:#92400e;"><?php _e( 'Tienes pendientes de aprobación', 'aura-suite' ); ?></strong>
                        <p style="margin:5px 0 0 0;color:#78350f;"><?php printf( __( '%d movimientos esperando aprobación', 'aura-suite' ), $pending ); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="aura-modal-footer">
            <button type="button" class="button button-large button-primary aura-modal-close">
                <?php _e( 'Cerrar', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>

<style>
/* =========================================================
   Dashboard Financiero Personal — estilos mejorados
   ========================================================= */
.aura-user-dashboard-page {
    background: #f3f4f6;
    padding: 20px;
    border-radius: 8px;
}

/* Tarjetas de estadísticas mejoradas */
.aura-ud-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.aura-ud-stat-card {
    background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
    border: none;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    box-shadow: 0 4px 6px rgba(0,0,0,.07), 0 1px 3px rgba(0,0,0,.06);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.aura-ud-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,.12), 0 4px 8px rgba(0,0,0,.08);
}

.aura-ud-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
}

.aura-ud-stat-card.income::before { background: linear-gradient(90deg, #10b981 0%, #059669 100%); }
.aura-ud-stat-card.expense::before { background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%); }
.aura-ud-stat-card.balance.positive::before { background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%); }
.aura-ud-stat-card.balance.negative::before { background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%); }
.aura-ud-stat-card.pending::before { background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%); }

.aura-ud-stat-card.pending.has-pending {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.95; }
}

.aura-ud-stat-icon-wrapper {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    box-shadow: inset 0 2px 4px rgba(0,0,0,.06);
}

.aura-ud-stat-card.income .aura-ud-stat-icon-wrapper { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); }
.aura-ud-stat-card.expense .aura-ud-stat-icon-wrapper { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); }
.aura-ud-stat-card.balance.positive .aura-ud-stat-icon-wrapper { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); }
.aura-ud-stat-card.balance.negative .aura-ud-stat-icon-wrapper { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); }
.aura-ud-stat-card.pending .aura-ud-stat-icon-wrapper { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); }

.aura-ud-stat-icon {
    font-size: 32px;
    line-height: 1;
}

.aura-ud-stat-body {
    flex: 1;
}

.aura-ud-stat-label {
    display: block;
    font-size: 12px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
    font-weight: 600;
}

.aura-ud-stat-value {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
    line-height: 1.2;
    margin-bottom: 4px;
}

.aura-ud-stat-card.income .aura-ud-stat-value { color: #059669; }
.aura-ud-stat-card.expense .aura-ud-stat-value { color: #dc2626; }
.aura-ud-stat-card.balance.positive .aura-ud-stat-value { color: #2563eb; }
.aura-ud-stat-card.balance.negative .aura-ud-stat-value { color: #dc2626; }

.aura-ud-stat-sub {
    display: block;
    font-size: 11px;
    color: #9ca3af;
}

.aura-ud-stat-trend {
    position: absolute;
    top: 16px;
    right: 16px;
    font-size: 14px;
    font-weight: 600;
    color: #059669;
    opacity: 0.7;
}

.aura-ud-stat-trend.down { color: #dc2626; }

.aura-ud-stat-alert {
    font-size: 11px;
    color: #92400e;
    background: #fef3c7;
    padding: 6px 12px;
    border-radius: 12px;
    font-weight: 600;
    text-align: center;
}

/* Tabla mejorada */
.aura-ud-movements-table {
    border-collapse: separate !important;
    border-spacing: 0 8px;
}

.aura-ud-movements-table thead th {
    background: linear-gradient(135deg, #1f2937 0%, #111827 100%) !important;
    color: #ffffff !important;
    font-weight: 600;
    padding: 14px 12px;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
    border: none;
}

.aura-ud-movements-table thead th,
.aura-ud-movements-table thead th * {
    color: #ffffff !important;
}

.aura-ud-movements-table thead th:first-child { border-radius: 8px 0 0 8px; }
.aura-ud-movements-table thead th:last-child { border-radius: 0 8px 8px 0; }

.aura-ud-movements-table tbody tr {
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
    transition: all 0.2s ease;
}

.aura-ud-movements-table tbody tr:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
}

.aura-ud-movements-table tbody tr.aura-row-income {
    border-left: 4px solid #10b981;
}

.aura-ud-movements-table tbody tr.aura-row-expense {
    border-left: 4px solid #ef4444;
}

.aura-ud-movements-table tbody td {
    padding: 16px 12px;
    vertical-align: middle;
    border: none;
}

.aura-type-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-size: 18px;
    font-weight: 700;
}

.aura-type-badge.income {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #059669;
}

.aura-type-badge.expense {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #dc2626;
}

.aura-concept-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f3f4f6;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 12px;
    color: #4b5563;
    font-weight: 500;
}

.aura-amount {
    font-size: 18px;
    font-weight: 700;
}

.aura-amount.income { color: #059669; }
.aura-amount.expense { color: #dc2626; }

.aura-ud-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.aura-ud-badge.approved {
    background: #d1fae5;
    color: #065f46;
}

.aura-ud-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.aura-ud-badge.rejected {
    background: #fee2e2;
    color: #991b1b;
}

.aura-ud-badge .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

/* Modal mejorado */
.aura-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 100000;
}

.aura-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(5px);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.aura-modal-content {
    position: relative;
    width: 90%;
    max-width: 800px;
    margin: 50px auto;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
    animation: slideDown 0.4s ease;
    overflow: hidden;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.aura-modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 24px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.aura-modal-header h2 {
    margin: 0;
    font-size: 22px;
    color: #fff;
}

.aura-modal-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    transition: all 0.2s ease;
}

.aura-modal-close:hover {
    background: rgba(255,255,255,0.3);
    transform: rotate(90deg);
}

.aura-modal-body {
    padding: 30px;
    max-height: calc(100vh - 300px);
    overflow-y: auto;
}

.aura-summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.aura-summary-card {
    background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 6px rgba(0,0,0,.07);
}

.aura-summary-card.income-card { border-left: 4px solid #10b981; }
.aura-summary-card.expense-card { border-left: 4px solid #ef4444; }
.aura-summary-card.balance-card.positive { border-left: 4px solid #3b82f6; }
.aura-summary-card.balance-card.negative { border-left: 4px solid #ef4444; }

.aura-summary-icon {
    font-size: 40px;
    line-height: 1;
}

.aura-summary-info {
    flex: 1;
}

.aura-summary-label {
    font-size: 12px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    font-weight: 600;
}

.aura-summary-value {
    font-size: 26px;
    font-weight: 700;
}

.aura-summary-value.income { color: #059669; }
.aura-summary-value.expense { color: #dc2626; }
.aura-summary-value.balance { color: #2563eb; }

.aura-modal-footer {
    background: #f9fafb;
    padding: 20px 30px;
    text-align: right;
    border-top: 1px solid #e5e7eb;
}

/* Filtros mejorados */
.aura-ud-filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.aura-ud-filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #374151;
}

.aura-ud-filter-group input,
.aura-ud-filter-group select {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 14px;
}

.aura-ud-filter-group input:focus,
.aura-ud-filter-group select:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.aura-ud-count {
    font-size: 14px;
    color: #9ca3af;
    font-weight: 400;
    margin-left: 8px;
}

.aura-ud-empty {
    text-align: center;
    padding: 60px 24px;
    color: #9ca3af;
}

/* Print styles */
@media print {
    .page-title-action,
    .aura-ud-filters,
    .wp-header-end,
    .aura-user-selector-bar,
    .tablenav {
        display: none !important;
    }
    
    .aura-ud-stat-card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    
    .aura-ud-movements-table {
        font-size: 11px;
    }
}
</style>

<script>
(function($) {
    'use strict';

    $(document).ready(function() {

        // ===================================================================
        // Modal "Ver mi resumen"
        // ===================================================================
        const $modal = $('#aura-summary-modal');
        const $viewSummaryBtn = $('#aura-view-my-summary-btn');
        const $modalCloseBtn = $('.aura-modal-close');

        // Abrir modal
        $viewSummaryBtn.on('click', function() {
            $modal.fadeIn(300);
            $('body').css('overflow', 'hidden');
        });

        // Cerrar modal
        $modalCloseBtn.on('click', function() {
            $modal.fadeOut(300);
            $('body').css('overflow', 'auto');
        });

        // Cerrar modal al hacer click en el overlay
        $('.aura-modal-overlay').on('click', function() {
            $modal.fadeOut(300);
            $('body').css('overflow', 'auto');
        });

        // Cerrar modal con tecla ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.is(':visible')) {
                $modal.fadeOut(300);
                $('body').css('overflow', 'auto');
            }
        });

        // ===================================================================
        // Botón "Imprimir / PDF"
        // ===================================================================
        $('#aura-print-dashboard-btn').on('click', function() {
            // Ocultar elementos que no se deben imprimir
            $('.page-title-action').hide();
            $('.aura-ud-filters').hide();
            $('.aura-user-selector-bar').hide();
            $('.tablenav').hide();
            
            // Agregar título para la impresión
            const printTitle = $('<div class="print-header" style="text-align:center;margin-bottom:30px;">')
                .append('<h1 style="font-size:24px;margin-bottom:10px;">📊 Dashboard Financiero Personal</h1>')
                .append('<p style="font-size:14px;color:#6b7280;">Fecha de impresión: ' + new Date().toLocaleDateString('es-ES', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }) + '</p>');
            
            $('.wrap.aura-user-dashboard-page').prepend(printTitle);
            
            // Imprimir
            window.print();
            
            // Restaurar elementos después de imprimir
            setTimeout(function() {
                $('.page-title-action').show();
                $('.aura-ud-filters').show();
                $('.aura-user-selector-bar').show();
                $('.tablenav').show();
                $('.print-header').remove();
            }, 1000);
        });

        // ===================================================================
        // Autocomplete con jQuery UI para el selector de usuario (admin)
        // ===================================================================
        const $udSearch = $('#ud-user-search');
        const $udHidden = $('#ud-user-id');

        if ($udSearch.length && typeof $.fn.autocomplete !== 'undefined') {
            $udSearch.autocomplete({
                minLength: 2,
                delay: 300,
                source: function(request, response) {
                    $.post(ajaxurl, {
                        action: 'aura_search_users',
                        nonce: <?php echo json_encode( wp_create_nonce( 'aura_transaction_nonce' ) ); ?>,
                        term: request.term
                    }, function(res) {
                        response(res.success && Array.isArray(res.data) ? res.data : []);
                    });
                },
                select: function(event, ui) {
                    $udSearch.val(ui.item.name);
                    $udHidden.val(ui.item.id);
                    return false;
                }
            }).autocomplete('instance')._renderItem = function(ul, item) {
                return $('<li>')
                    .append(
                        '<div style="display:flex;align-items:center;gap:8px;">' +
                        '<img src="' + item.avatar_url + '" width="24" height="24" style="border-radius:50%;">' +
                        '<div><strong>' + $('<span>').text(item.name).html() + '</strong>' +
                        '<br><small style="color:#8c8f94">' + $('<span>').text(item.email).html() + '</small></div>' +
                        '</div>'
                    )
                    .appendTo(ul);
            };
        }

        // Botón "Ver Dashboard" redirige al dashboard del usuario seleccionado
        $('#ud-view-user-btn').on('click', function() {
            const uid = $udHidden.val();
            if (! uid) {
                alert(<?php echo json_encode( __( 'Selecciona un usuario de la lista.', 'aura-suite' ) ); ?>);
                return;
            }
            window.location.href = <?php echo json_encode( $base_url . '&view_user=' ); ?> + uid;
        });

        // ===================================================================
        // Animación de entrada para las tarjetas de estadísticas
        // ===================================================================
        $('.aura-ud-stat-card').each(function(index) {
            $(this).css({
                opacity: 0,
                transform: 'translateY(20px)'
            }).delay(index * 100).animate({
                opacity: 1
            }, 500);
            
            setTimeout(() => {
                $(this).css('transform', 'translateY(0)');
            }, index * 100);
        });

        // ===================================================================
        // Efecto hover mejorado para las filas de la tabla
        // ===================================================================
        $('.aura-ud-movements-table tbody tr').hover(
            function() {
                $(this).css({
                    'background': '#f9fafb',
                    'cursor': 'pointer'
                });
            },
            function() {
                $(this).css('background', '#ffffff');
            }
        );

    });
})(jQuery);
</script>
