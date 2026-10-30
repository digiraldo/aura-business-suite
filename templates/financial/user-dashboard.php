<?php
/**
 * Template: Dashboard Financiero Personal del Usuario (Fase 6, Item 6.2)
 *
 * Muestra el resumen financiero personalizado de cada usuario con:
 * - Tarjetas de estadísticas (cobros, pagos, saldo neto, pendientes) con tooltips contextuales
 * - Filtros avanzados con selector de período rápido por chips
 * - Tabla de movimientos fluida (Zero Horizontal Scroll) con Child Rows expandibles
 * - Sección de equipos a cargo (desde módulo inventario)
 * - Selector de usuario para administradores (view_others_summary)
 * - Estado vacío amigable ("¡Todo al día!")
 * - Modal Universal de Resumen Gráfico y Compatibilidad Total con Dark Mode
 * - 100% Estandarizado con el Aura Design System (P.E.E. / Anti-Clipping / Skeleton Loaders)
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

// Admin consultando otro usuario
if ( $can_view_others && ! empty( $_GET['view_user'] ) ) {
    $req_id = intval( $_GET['view_user'] );
    if ( get_userdata( $req_id ) ) {
        $viewing_user_id = $req_id;
    }
}

$viewing_user_data = get_userdata( $viewing_user_id );

// Filtros sanitizados
$filters = [
    'date_from' => sanitize_text_field( $_GET['date_from'] ?? '' ),
    'date_to'   => sanitize_text_field( $_GET['date_to']   ?? '' ),
    'concept'   => sanitize_key( $_GET['concept']           ?? '' ),
    'status'    => sanitize_key( $_GET['status']            ?? '' ),
    'paged'     => max( 1, intval( $_GET['paged']           ?? 1 ) ),
];

// Obtención de datos
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
        // Perspectiva usuario: egreso org → usuario = cobro usuario; ingreso org ← usuario = pago usuario
        if ( $row->transaction_type === 'expense' ) $income  = (float) $row->total;
        if ( $row->transaction_type === 'income'  ) $expense = (float) $row->total;
    }
}

$balance           = $income - $expense;
$base_url          = admin_url( 'admin.php?page=aura-my-finance' );
$nonce_field       = wp_create_nonce( 'aura_user_dashboard_nonce' );
$user_display_name = $viewing_user_data ? $viewing_user_data->display_name : "Usuario #{$viewing_user_id}";
?>

<div class="wrap aura-user-dashboard-wrap" style="width:100% !important; max-width:100% !important; margin:16px 0 40px 0 !important; padding-right:20px !important; box-sizing:border-box !important;">

    <!-- ====================================================
         Cabecera de Impresión Oficial (@media print)
    ===================================================== -->
    <div class="aura-print-header">
        <div class="aura-print-header__brand-box">
            <?php
            $logo_url = get_site_icon_url( 80 );
            if ( $logo_url ) {
                echo '<img src="' . esc_url( $logo_url ) . '" alt="" class="aura-print-logo" loading="eager">';
            }
            ?>
            <div class="aura-print-header__brand-text">
                <h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
                <span class="aura-print-header__tagline"><?php esc_html_e( 'MÓDULO FINANCIERO — ESTADO PERSONAL', 'aura-suite' ); ?></span>
            </div>
        </div>
        <div class="aura-print-header__report-card">
            <h1><?php esc_html_e( 'Estado Financiero Personal', 'aura-suite' ); ?></h1>
            <div class="aura-print-period-pill">
                <span class="dashicons dashicons-calendar-alt"></span>
                <span><?php echo $filters['date_from'] ? esc_html( "{$filters['date_from']} al " . ( $filters['date_to'] ?: date( 'Y-m-d' ) ) ) : esc_html__( 'Historial Completo', 'aura-suite' ); ?></span>
            </div>
        </div>
        <div class="aura-print-header__meta-box">
            <div class="aura-print-meta-item">
                <span><?php esc_html_e( 'Titular', 'aura-suite' ); ?>:</span>
                <strong class="aura-print-meta-value"><?php echo esc_html( $user_display_name ); ?></strong>
            </div>
            <div class="aura-print-meta-item">
                <span><?php esc_html_e( 'Fecha de Emisión', 'aura-suite' ); ?>:</span>
                <span class="aura-print-meta-value"><?php echo esc_html( date_i18n( 'd/m/Y H:i' ) ); ?></span>
            </div>
            <div class="aura-print-meta-item">
                <span><?php esc_html_e( 'Carácter', 'aura-suite' ); ?>:</span>
                <span class="aura-print-badge-official"><?php esc_html_e( 'Documento Oficial', 'aura-suite' ); ?></span>
            </div>
        </div>
    </div>

    <!-- ====================================================
         Cabecera de Pantalla (WOW VIP Header)
    ===================================================== -->
    <header class="aura-ud-header wow-vip-card aura-dashboard-vip-header">
        <div class="aura-ud-header__title">
            <h1>
                <span class="dashicons dashicons-id-alt"></span>
                <?php
                if ( $viewing_user_id === $current_user_id ) {
                    esc_html_e( 'Mi Dashboard Financiero', 'aura-suite' );
                } else {
                    printf(
                        esc_html__( 'Dashboard Financiero: %s', 'aura-suite' ),
                        esc_html( $user_display_name )
                    );
                }
                ?>
            </h1>
            <p class="aura-ud-header__subtitle">
                <?php esc_html_e( 'Resumen consolidado de ingresos, egresos, movimientos y saldo personal.', 'aura-suite' ); ?>
            </p>
        </div>

        <div class="aura-ud-header__actions" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <button type="button" id="aura-view-my-summary-btn" class="btn btn-primary btn-shimmer btn-lift" data-tooltip="<?php esc_attr_e( 'Ver proporción y desglose gráfico consolidado', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-chart-pie" style="margin-right:4px;"></span>
                <?php esc_html_e( 'Ver Resumen Gráfico', 'aura-suite' ); ?>
            </button>

            <a href="<?php echo esc_url( add_query_arg( [
                'action'    => 'aura_export_personal_finance_csv',
                'user_id'   => $viewing_user_id,
                'date_from' => $filters['date_from'],
                'date_to'   => $filters['date_to'],
                'concept'   => $filters['concept'],
                'status'    => $filters['status'],
                'nonce'     => $nonce_field,
            ], admin_url( 'admin-ajax.php' ) ) ); ?>"
               class="btn btn-emerald btn-shimmer btn-lift"
               data-tooltip="<?php esc_attr_e( 'Descargar listado filtrado en formato CSV', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-download" style="margin-right:4px;"></span>
                <?php esc_html_e( 'Exportar CSV', 'aura-suite' ); ?>
            </a>

            <button type="button" id="aura-print-dashboard-btn" class="btn btn-secondary btn-lift" data-tooltip="<?php esc_attr_e( 'Imprimir o guardar como documento PDF oficial', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-printer" style="margin-right:4px;"></span>
                <?php esc_html_e( 'Imprimir / PDF', 'aura-suite' ); ?>
            </button>
        </div>
    </header>

    <?php if ( is_wp_error( $summary ) ) : ?>
        <div class="alert-card alert-danger" style="margin-bottom:20px;">
            <p><?php echo esc_html( $summary->get_error_message() ); ?></p>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <!-- ====================================================
         Selector de Usuario (Solo Administradores / Supervisores)
    ===================================================== -->
    <?php if ( $can_view_others ) : ?>
    <div class="aura-user-selector-card glass-card" style="margin-bottom:24px; padding:16px 20px; border-radius:var(--r-xl, 18px); background:var(--bg-surface, #ffffff); border:1px solid var(--border-color, #e2e8f0); display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <div class="aura-user-selector-card__icon" style="display:flex; align-items:center; justify-content:center; width:42px; height:42px; border-radius:12px; background:rgba(37,99,235,0.1); color:var(--primary, #2563eb); flex-shrink:0;">
            <span class="dashicons dashicons-admin-users" style="font-size:22px; width:22px; height:22px;"></span>
        </div>
        <div class="aura-user-selector-card__label" style="font-size:13.5px; font-weight:700; color:var(--tx-primary, #0f172a);">
            <?php esc_html_e( 'Consultar otro usuario:', 'aura-suite' ); ?>
        </div>
        <div class="aura-user-autocomplete-wrap input-group" style="flex:1; min-width:260px; max-width:420px; margin:0;">
            <span class="input-group-text" style="background:var(--bg-alt, #f8fafc); border-color:var(--border-color, #cbd5e1); color:var(--tx-muted, #64748b);">👤</span>
            <input type="text"
                   id="ud-user-search"
                   class="form-control"
                   placeholder="<?php esc_attr_e( 'Escribe nombre o correo electrónico…', 'aura-suite' ); ?>"
                   autocomplete="off"
                   value="<?php echo $viewing_user_id !== $current_user_id && $viewing_user_data ? esc_attr( $viewing_user_data->display_name ) : ''; ?>">
            <input type="hidden" id="ud-user-id" value="<?php echo esc_attr( $viewing_user_id !== $current_user_id ? $viewing_user_id : '' ); ?>">
        </div>
        <button type="button" id="ud-view-user-btn" class="btn btn-primary btn-lift" data-tooltip="<?php esc_attr_e( 'Cargar el dashboard financiero del usuario seleccionado', 'aura-suite' ); ?>">
            <span class="dashicons dashicons-visibility" style="margin-right:4px;"></span>
            <?php esc_html_e( 'Ver Dashboard', 'aura-suite' ); ?>
        </button>
        <?php if ( $viewing_user_id !== $current_user_id ) : ?>
        <a href="<?php echo esc_url( $base_url ); ?>" class="btn btn-secondary btn-lift" data-tooltip="<?php esc_attr_e( 'Regresar a la consulta de mi propio estado de cuenta', 'aura-suite' ); ?>">
            <span class="dashicons dashicons-undo" style="margin-right:4px;"></span>
            <?php esc_html_e( 'Volver a mi resumen', 'aura-suite' ); ?>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ====================================================
         Tarjetas de Estadísticas (KPIs) con Tooltips
    ===================================================== -->
    <div class="aura-ud-stats-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:18px; margin-bottom:24px;">

        <!-- Cobros Recibidos -->
        <div class="kpi-card kpi-emerald card-lift" data-tooltip="<?php esc_attr_e( 'Total acumulado de transferencias y pagos emitidos hacia ti por la entidad (aprobados).', 'aura-suite' ); ?>">
            <div class="kpi-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="kpi-value"><?php echo esc_html( $currency . number_format( $income, 2, '.', ',' ) ); ?></div>
            <div class="kpi-label">
                <span><?php esc_html_e( 'Cobros Recibidos', 'aura-suite' ); ?></span>
                <span class="aura-help-icon" style="margin-left:auto;"><span class="dashicons dashicons-editor-help"></span></span>
            </div>
            <div class="kpi-change">
                <span class="pulse-dot"></span>
                <span><?php esc_html_e( 'Movimientos aprobados', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- Pagos Realizados -->
        <div class="kpi-card kpi-rose card-lift" data-tooltip="<?php esc_attr_e( 'Total de aportes, reembolsos devueltos o pagos que has efectuado a la organización (aprobados).', 'aura-suite' ); ?>">
            <div class="kpi-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="kpi-value"><?php echo esc_html( $currency . number_format( $expense, 2, '.', ',' ) ); ?></div>
            <div class="kpi-label">
                <span><?php esc_html_e( 'Pagos Realizados', 'aura-suite' ); ?></span>
                <span class="aura-help-icon" style="margin-left:auto;"><span class="dashicons dashicons-editor-help"></span></span>
            </div>
            <div class="kpi-change">
                <span><?php esc_html_e( 'Movimientos aprobados', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- Saldo Neto Personal -->
        <div class="kpi-card <?php echo $balance >= 0 ? 'kpi-blue' : 'kpi-rose'; ?> card-lift" data-tooltip="<?php esc_attr_e( 'Diferencia neta entre cobros recibidos y pagos realizados. Un saldo positivo indica balance a favor.', 'aura-suite' ); ?>">
            <div class="kpi-icon">
                <span class="dashicons dashicons-chart-pie"></span>
            </div>
            <div class="kpi-value">
                <?php echo esc_html( ( $balance >= 0 ? '+' : '' ) . $currency . number_format( abs( $balance ), 2, '.', ',' ) ); ?>
            </div>
            <div class="kpi-label">
                <span><?php esc_html_e( 'Saldo Neto Personal', 'aura-suite' ); ?></span>
                <span class="aura-help-icon" style="margin-left:auto;"><span class="dashicons dashicons-editor-help"></span></span>
            </div>
            <div class="kpi-change">
                <span><?php esc_html_e( 'Ingresos menos egresos', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- Pendientes de Pago -->
        <div class="kpi-card kpi-amber card-lift" data-tooltip="<?php esc_attr_e( 'Cantidad de movimientos registrados que están pendientes de revisión y validación administrativa.', 'aura-suite' ); ?>">
            <div class="kpi-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="kpi-value"><?php echo esc_html( $pending ); ?></div>
            <div class="kpi-label">
                <span><?php esc_html_e( 'Pendientes de Pago', 'aura-suite' ); ?></span>
                <span class="aura-help-icon" style="margin-left:auto;"><span class="dashicons dashicons-editor-help"></span></span>
            </div>
            <div class="kpi-change">
                <?php if ( $pending > 0 ) : ?>
                    <span class="pulse-dot pulse-amber"></span>
                    <span><?php esc_html_e( 'En espera de aprobación', 'aura-suite' ); ?></span>
                <?php else : ?>
                    <span><?php esc_html_e( 'Al día', 'aura-suite' ); ?></span>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.aura-ud-stats-grid -->

    <!-- ====================================================
         Filtros de Búsqueda y Períodos Rápidos
    ===================================================== -->
    <div class="aura-ud-filters-card card" style="background:var(--bg-surface, #ffffff); border:1px solid var(--border-color, #e2e8f0); border-radius:var(--r-xl, 18px); padding:20px; margin-bottom:24px;">
        <form method="GET" action="" id="aura-ud-filters-form">
            <input type="hidden" name="page" value="aura-my-finance">
            <?php if ( $viewing_user_id !== $current_user_id ) : ?>
            <input type="hidden" name="view_user" value="<?php echo esc_attr( $viewing_user_id ); ?>">
            <?php endif; ?>

            <!-- Chips de Período Rápido -->
            <div class="aura-quick-date-chips" style="display:flex; align-items:center; gap:8px; margin-bottom:16px; padding-bottom:14px; border-bottom:1px solid var(--border-color, #f1f5f9); flex-wrap:wrap;">
                <span class="aura-chips-label" style="font-size:11.5px; font-weight:700; color:var(--tx-muted, #64748b); text-transform:uppercase; letter-spacing:0.5px; margin-right:4px;">
                    <span class="dashicons dashicons-calendar" style="font-size:16px; width:16px; height:16px; vertical-align:text-bottom;"></span> <?php esc_html_e( 'Período Rápido:', 'aura-suite' ); ?>
                </span>
                <button type="button" class="aura-chip-btn" data-period="this_month"><?php esc_html_e( 'Este Mes', 'aura-suite' ); ?></button>
                <button type="button" class="aura-chip-btn" data-period="last_month"><?php esc_html_e( 'Mes Ant.', 'aura-suite' ); ?></button>
                <button type="button" class="aura-chip-btn" data-period="quarter"><?php esc_html_e( 'Trimestre', 'aura-suite' ); ?></button>
                <button type="button" class="aura-chip-btn" data-period="this_year"><?php esc_html_e( 'Este Año', 'aura-suite' ); ?></button>
                <button type="button" class="aura-chip-btn" data-period="all"><?php esc_html_e( 'Todo', 'aura-suite' ); ?></button>
            </div>

            <div class="aura-ud-filters-grid" style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end;">

                <div class="aura-ud-filter-item" style="flex:1; min-width:140px;">
                    <label for="ud-date-from" style="font-size:11px; font-weight:700; color:var(--tx-secondary, #475569); text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:4px; margin-bottom:6px;">
                        <?php esc_html_e( 'Desde', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Fecha de inicio para el rango de consulta', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                    </label>
                    <input type="date" id="ud-date-from" name="date_from" class="input-fancy"
                           value="<?php echo esc_attr( $filters['date_from'] ); ?>" style="width:100%; height:38px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); background:var(--bg-surface, #ffffff); color:var(--tx-primary, #0f172a); padding:6px 12px;">
                </div>

                <div class="aura-ud-filter-item" style="flex:1; min-width:140px;">
                    <label for="ud-date-to" style="font-size:11px; font-weight:700; color:var(--tx-secondary, #475569); text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:4px; margin-bottom:6px;">
                        <?php esc_html_e( 'Hasta', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Fecha límite para el rango de consulta', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                    </label>
                    <input type="date" id="ud-date-to" name="date_to" class="input-fancy"
                           value="<?php echo esc_attr( $filters['date_to'] ); ?>" style="width:100%; height:38px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); background:var(--bg-surface, #ffffff); color:var(--tx-primary, #0f172a); padding:6px 12px;">
                </div>

                <div class="aura-ud-filter-item" style="flex:1; min-width:160px;">
                    <label for="ud-concept" style="font-size:11px; font-weight:700; color:var(--tx-secondary, #475569); text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:4px; margin-bottom:6px;">
                        <?php esc_html_e( 'Concepto', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Filtrar por tipo de concepto contable asignado', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                    </label>
                    <select id="ud-concept" name="concept" class="select-fancy" style="width:100%; height:38px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); background:var(--bg-surface, #ffffff); color:var(--tx-primary, #0f172a); padding:6px 12px;">
                        <option value=""><?php esc_html_e( 'Todos los conceptos', 'aura-suite' ); ?></option>
                        <?php foreach ( $concepts as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>"
                            <?php selected( $filters['concept'], $val ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="aura-ud-filter-item" style="flex:1; min-width:140px;">
                    <label for="ud-status" style="font-size:11px; font-weight:700; color:var(--tx-secondary, #475569); text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:4px; margin-bottom:6px;">
                        <?php esc_html_e( 'Estado', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Filtrar según estado de aprobación administrativa', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                    </label>
                    <select id="ud-status" name="status" class="select-fancy" style="width:100%; height:38px; border-radius:8px; border:1px solid var(--border-color, #cbd5e1); background:var(--bg-surface, #ffffff); color:var(--tx-primary, #0f172a); padding:6px 12px;">
                        <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
                        <option value="approved" <?php selected( $filters['status'], 'approved' ); ?>><?php esc_html_e( 'Aprobados', 'aura-suite' ); ?></option>
                        <option value="pending"  <?php selected( $filters['status'], 'pending' ); ?>><?php esc_html_e( 'Pendientes', 'aura-suite' ); ?></option>
                        <option value="rejected" <?php selected( $filters['status'], 'rejected' ); ?>><?php esc_html_e( 'Rechazados', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="aura-ud-filter-actions" style="display:flex; align-items:center; gap:8px;">
                    <button type="submit" class="btn btn-primary btn-shimmer btn-lift" data-tooltip="<?php esc_attr_e( 'Aplicar los filtros seleccionados', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-filter" style="margin-right:4px;"></span>
                        <?php esc_html_e( 'Filtrar', 'aura-suite' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $base_url . ( $viewing_user_id !== $current_user_id ? '&view_user=' . $viewing_user_id : '' ) ); ?>"
                       class="btn btn-secondary btn-lift"
                       data-tooltip="<?php esc_attr_e( 'Limpiar todos los filtros y volver a valores por defecto', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-image-rotate" style="margin-right:4px;"></span>
                        <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
                    </a>
                </div>

            </div>
        </form>
    </div><!-- /.aura-ud-filters-card -->

    <!-- ====================================================
         Tabla de Movimientos con Child Rows Expandibles
    ===================================================== -->
    <div class="aura-ud-table-card card" style="background:var(--bg-surface, #ffffff); border:1px solid var(--border-color, #e2e8f0); border-radius:var(--r-xl, 18px); margin-bottom:24px; overflow:hidden;">
        <div class="aura-ud-table-card__header" style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border-color, #e2e8f0); background:var(--bg-alt, #f8fafc);">
            <h2 class="aura-ud-table-card__title" style="margin:0; font-size:15px; font-weight:700; color:var(--tx-primary, #0f172a); display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-list-view" style="color:var(--primary, #2563eb);"></span>
                <?php esc_html_e( 'Movimientos que me Involucran', 'aura-suite' ); ?>
                <span class="badge badge-blue pill" style="font-size:11px;"><?php echo esc_html( $total_movs ); ?></span>
            </h2>
        </div>

        <div class="aura-ud-table-wrap">
            <?php if ( empty( $movements ) ) : ?>
            <!-- Estado Vacío ("¡Todo al día!") -->
            <div class="empty-card" style="padding:48px 24px; text-align:center;">
                <div class="empty-icon" style="width:64px; height:64px; border-radius:50%; background:rgba(16,185,129,0.12); color:var(--aura-emerald, #10b981); margin:0 auto 16px; display:flex; align-items:center; justify-content:center;">
                    <span class="dashicons dashicons-yes-alt" style="font-size:32px; width:32px; height:32px;"></span>
                </div>
                <h3 style="font-size:1.15rem; font-weight:700; color:var(--tx-primary, #0f172a); margin:0 0 6px 0;"><?php esc_html_e( '¡Todo al día!', 'aura-suite' ); ?></h3>
                <p style="font-size:0.9rem; color:var(--tx-muted, #64748b); margin:0;"><?php esc_html_e( 'No se encontraron movimientos registrados para los filtros seleccionados.', 'aura-suite' ); ?></p>
                <?php if ( ! empty( $filters['date_from'] ) || ! empty( $filters['date_to'] ) || ! empty( $filters['concept'] ) || ! empty( $filters['status'] ) ) : ?>
                <div style="margin-top:18px;">
                    <a href="<?php echo esc_url( $base_url . ( $viewing_user_id !== $current_user_id ? '&view_user=' . $viewing_user_id : '' ) ); ?>" class="btn btn-secondary btn-lift">
                        <span class="dashicons dashicons-image-rotate" style="margin-right:4px;"></span>
                        <?php esc_html_e( 'Restablecer Filtros', 'aura-suite' ); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php else : ?>

            <table class="aura-ud-table" style="width:100% !important; min-width:100% !important; max-width:100% !important; table-layout:auto !important; box-sizing:border-box !important;">
                <thead>
                    <tr>
                        <th style="width:70px;text-align:center;">
                            <?php esc_html_e( '#', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Identificador único y botón para ver detalles completos', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th style="width:110px" class="aura-col-mobile-hidden">
                            <?php esc_html_e( 'Fecha', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Fecha de registro de la transacción contable', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th style="width:50px;text-align:center">
                            <?php esc_html_e( 'Tipo', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Cobro recibido (ingreso) o Pago realizado (egreso)', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th class="aura-col-desc">
                            <?php esc_html_e( 'Descripción', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Detalle descriptivo de la operación', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th style="width:140px" class="aura-col-tablet-hidden">
                            <?php esc_html_e( 'Concepto', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Concepto contable asignado a la transacción', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th style="width:120px;text-align:right">
                            <?php esc_html_e( 'Monto', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Valor monetario de la transacción', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th style="width:140px" class="aura-col-tablet-hidden">
                            <?php esc_html_e( 'Registrado por', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Usuario que creó el registro en la plataforma', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th style="width:110px;text-align:center" class="aura-col-mobile-hidden">
                            <?php esc_html_e( 'Estado', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Estado contable: Aprobado, Pendiente o Rechazado', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $movements as $mov ) :
                        $is_income     = ( $mov->transaction_type === 'expense' );
                        $concept_label = $concepts[ $mov->related_user_concept ] ?? ( $mov->related_user_concept ?: '—' );
                        $date_fmt      = date_i18n( 'd M Y', strtotime( $mov->transaction_date ) );
                        $amount_fmt    = $currency . number_format( (float) $mov->amount, 2, '.', ',' );
                        $creator       = get_userdata( $mov->created_by );
                        $creator_name  = $creator ? $creator->display_name : 'Sistema';
                        $creator_avatar = $creator ? get_avatar_url( $creator->ID, ['size' => 48] ) : '';

                        // Información y estilos del estado canónico
                        $status_styles = [
                            'approved' => [ 'badge' => 'badge-emerald', 'icon' => 'dashicons-yes-alt', 'desc' => esc_html__( 'Operación validada y computada en el saldo personal.', 'aura-suite' ) ],
                            'pending'  => [ 'badge' => 'badge-amber',   'icon' => 'dashicons-clock',   'desc' => esc_html__( 'En espera de aprobación administrativa o contable.', 'aura-suite' ) ],
                            'rejected' => [ 'badge' => 'badge-rose',    'icon' => 'dashicons-dismiss', 'desc' => esc_html__( 'Transacción rechazada o anulada.', 'aura-suite' ) ],
                        ];
                        $st_info = $status_styles[ $mov->status ] ?? $status_styles['pending'];

                        switch ( $mov->status ) {
                            case 'approved': $status_html = '<span class="badge badge-emerald pill"><span class="dashicons dashicons-yes-alt" style="font-size:13px;width:13px;height:13px;margin-right:4px;"></span>' . esc_html__( 'Aprobado', 'aura-suite' ) . '</span>'; break;
                            case 'pending':  $status_html = '<span class="badge badge-amber pill"><span class="dashicons dashicons-clock" style="font-size:13px;width:13px;height:13px;margin-right:4px;"></span>' . esc_html__( 'Pendiente', 'aura-suite' ) . '</span>'; break;
                            case 'rejected': $status_html = '<span class="badge badge-rose pill"><span class="dashicons dashicons-dismiss" style="font-size:13px;width:13px;height:13px;margin-right:4px;"></span>' . esc_html__( 'Rechazado', 'aura-suite' ) . '</span>'; break;
                            default:         $status_html = '<span class="badge badge-slate pill">' . esc_html( $mov->status ) . '</span>';
                        }

                        // ── Tarjetas Flotantes Enriquecidas (Tooltips HTML) ──
                        $tip_txn_html = '<div class="aura-tip-card">'
                            . '<div class="aura-tip-card-header">'
                            . '<div class="aura-tip-avatar-large" style="background:' . ( $is_income ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.2)' ) . ';border-color:' . ( $is_income ? 'rgba(16,185,129,0.4)' : 'rgba(239,68,68,0.4)' ) . ';color:' . ( $is_income ? '#10b981' : '#ef4444' ) . ';font-size:22px;font-weight:900;">'
                            . ( $is_income ? '↗' : '↙' )
                            . '</div>'
                            . '<div class="aura-tip-info">'
                            . '<div class="aura-tip-title">' . sprintf( esc_html__( 'Transacción #%d', 'aura-suite' ), $mov->id ) . '</div>'
                            . '<div class="aura-tip-subtitle">' . ( $is_income ? esc_html__( 'Cobro Recibido (Ingreso Usuario)', 'aura-suite' ) : esc_html__( 'Pago Realizado (Egreso Usuario)', 'aura-suite' ) ) . '</div>'
                            . '<div class="aura-tip-badges">'
                            . '<span class="aura-tip-badge" style="background:' . ( $is_income ? 'rgba(16,185,129,0.25)' : 'rgba(239,68,68,0.25)' ) . ';color:' . ( $is_income ? '#6ee7b7' : '#fca5a5' ) . ';">' . ( $is_income ? '🟢 Cobro (+)' : '🔴 Pago (-)' ) . '</span>'
                            . '</div>'
                            . '</div>'
                            . '</div>'
                            . '<div class="aura-tip-card-body">'
                            . '<div class="aura-tip-row"><span>📅 Fecha:</span><strong>' . esc_html( $date_fmt ) . '</strong></div>'
                            . '<div class="aura-tip-row"><span>💵 Monto:</span><strong>' . ( $is_income ? '+' : '-' ) . esc_html( $amount_fmt ) . '</strong></div>'
                            . '<div class="aura-tip-row"><span>🏷️ Concepto:</span><strong>' . esc_html( $concept_label ) . '</strong></div>'
                            . '<div class="aura-tip-row"><span>📌 Estado:</span><strong>' . esc_html( ucfirst( $mov->status ) ) . '</strong></div>'
                            . '</div>'
                            . '</div>';

                        $tip_type_html = '<div class="aura-tip-card">'
                            . '<div class="aura-tip-card-header">'
                            . '<div class="aura-tip-avatar-large" style="background:' . ( $is_income ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.2)' ) . ';color:' . ( $is_income ? '#10b981' : '#ef4444' ) . ';font-size:22px;font-weight:900;">'
                            . ( $is_income ? '↗' : '↙' )
                            . '</div>'
                            . '<div class="aura-tip-info">'
                            . '<div class="aura-tip-title">' . ( $is_income ? esc_html__( 'Cobro Recibido (+)', 'aura-suite' ) : esc_html__( 'Pago Realizado (-)', 'aura-suite' ) ) . '</div>'
                            . '<div class="aura-tip-subtitle">' . esc_html__( 'Impacto en el Balance Personal', 'aura-suite' ) . '</div>'
                            . '</div>'
                            . '</div>'
                            . '<div class="aura-tip-card-body">'
                            . '<div class="aura-tip-row"><span>⚡ Flujo:</span><strong style="color:' . ( $is_income ? '#10b981' : '#ef4444' ) . ';">' . ( $is_income ? esc_html__( 'Ingreso / Saldo a favor (+)', 'aura-suite' ) : esc_html__( 'Egreso / Descuento (-)', 'aura-suite' ) ) . '</strong></div>'
                            . '<div class="aura-tip-row"><span>💵 Monto Operación:</span><strong>' . esc_html( $amount_fmt ) . '</strong></div>'
                            . '</div>'
                            . '</div>';

                        $tip_desc_html = '<div class="aura-tip-card">'
                            . '<div class="aura-tip-card-header">'
                            . '<div class="aura-tip-avatar-large" style="background:rgba(37,99,235,0.2);color:#60a5fa;">'
                            . '<span class="dashicons dashicons-media-text" style="font-size:24px;width:24px;height:24px;"></span>'
                            . '</div>'
                            . '<div class="aura-tip-info">'
                            . '<div class="aura-tip-title">' . esc_html__( 'Detalle de la Operación', 'aura-suite' ) . '</div>'
                            . '<div class="aura-tip-subtitle">#' . esc_html( $mov->id ) . ' &bull; ' . esc_html( $date_fmt ) . '</div>'
                            . '</div>'
                            . '</div>'
                            . '<div class="aura-tip-card-body">'
                            . '<div class="aura-tip-row"><span>📝 Descripción:</span><strong>' . esc_html( $mov->description ) . '</strong></div>'
                            . '<div class="aura-tip-row"><span>🏷️ Concepto:</span><strong>' . esc_html( $concept_label ) . '</strong></div>'
                            . '</div>'
                            . '</div>';

                        $tip_concept_html = '<div class="aura-tip-card">'
                            . '<div class="aura-tip-card-header">'
                            . '<div class="aura-tip-avatar-large" style="background:rgba(59,130,246,0.2);color:#60a5fa;">'
                            . '<span class="dashicons dashicons-tag" style="font-size:24px;width:24px;height:24px;"></span>'
                            . '</div>'
                            . '<div class="aura-tip-info">'
                            . '<div class="aura-tip-title">' . esc_html( $concept_label ) . '</div>'
                            . '<div class="aura-tip-subtitle">' . esc_html__( 'Concepto Financiero', 'aura-suite' ) . '</div>'
                            . '</div>'
                            . '</div>'
                            . '<div class="aura-tip-card-body">'
                            . '<div class="aura-tip-row"><span>📌 Clave Concepto:</span><strong>' . esc_html( $mov->related_user_concept ?: 'general' ) . '</strong></div>'
                            . '<div class="aura-tip-row"><span>📄 Transacción:</span><strong>#' . esc_html( $mov->id ) . '</strong></div>'
                            . '</div>'
                            . '</div>';

                        $tip_amount_html = '<div class="aura-tip-card">'
                            . '<div class="aura-tip-card-header">'
                            . '<div class="aura-tip-avatar-large" style="background:' . ( $is_income ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.2)' ) . ';color:' . ( $is_income ? '#10b981' : '#ef4444' ) . ';font-size:22px;">'
                            . '<span class="dashicons dashicons-money-alt" style="font-size:24px;width:24px;height:24px;"></span>'
                            . '</div>'
                            . '<div class="aura-tip-info">'
                            . '<div class="aura-tip-title">' . ( $is_income ? '+' : '-' ) . esc_html( $amount_fmt ) . '</div>'
                            . '<div class="aura-tip-subtitle">' . ( $is_income ? esc_html__( 'Cobro abonado a tu saldo', 'aura-suite' ) : esc_html__( 'Pago deducido de tu saldo', 'aura-suite' ) ) . '</div>'
                            . '</div>'
                            . '</div>'
                            . '<div class="aura-tip-card-body">'
                            . '<div class="aura-tip-row"><span>💵 Importe Neto:</span><strong style="color:' . ( $is_income ? '#10b981' : '#ef4444' ) . ';">' . ( $is_income ? '+' : '-' ) . esc_html( $amount_fmt ) . '</strong></div>'
                            . '<div class="aura-tip-row"><span>📌 Estado:</span><strong>' . esc_html( ucfirst( $mov->status ) ) . '</strong></div>'
                            . '</div>'
                            . '</div>';

                        $tip_creator_html = '<div class="aura-tip-card">'
                            . '<div class="aura-tip-card-header">'
                            . ( $creator_avatar ? '<img src="' . esc_url( $creator_avatar ) . '" class="aura-tip-avatar-large" alt="">' : '<div class="aura-tip-avatar-large"><span class="dashicons dashicons-admin-users" style="font-size:24px;width:24px;height:24px;"></span></div>' )
                            . '<div class="aura-tip-info">'
                            . '<div class="aura-tip-title">' . esc_html( $creator_name ) . '</div>'
                            . '<div class="aura-tip-subtitle">' . esc_html( $creator ? $creator->user_email : 'sistema@aura.suite' ) . '</div>'
                            . '<div class="aura-tip-badges">'
                            . '<span class="aura-tip-badge aura-tip-badge--user">👤 ' . esc_html__( 'Registrador', 'aura-suite' ) . '</span>'
                            . '</div>'
                            . '</div>'
                            . '</div>'
                            . '<div class="aura-tip-card-body">'
                            . '<div class="aura-tip-row"><span>🆔 Usuario ID:</span><strong>#' . ( $creator ? esc_html( $creator->ID ) : '0' ) . '</strong></div>'
                            . '<div class="aura-tip-row"><span>📝 Usuario Login:</span><strong>' . ( $creator ? esc_html( $creator->user_login ) : 'system' ) . '</strong></div>'
                            . '</div>'
                            . '</div>';

                        $tip_status_html = '<div class="aura-tip-card">'
                            . '<div class="aura-tip-card-header">'
                            . '<div class="aura-tip-avatar-large" style="background:rgba(255,255,255,0.1);color:#ffffff;font-size:22px;">'
                            . '<span class="dashicons ' . esc_attr( $st_info['icon'] ) . '" style="font-size:24px;width:24px;height:24px;"></span>'
                            . '</div>'
                            . '<div class="aura-tip-info">'
                            . '<div class="aura-tip-title">' . sprintf( esc_html__( 'Estado: %s', 'aura-suite' ), ucfirst( $mov->status ) ) . '</div>'
                            . '<div class="aura-tip-subtitle">' . esc_html__( 'Validación y Auditoría Contable', 'aura-suite' ) . '</div>'
                            . '</div>'
                            . '</div>'
                            . '<div class="aura-tip-card-body">'
                            . '<div class="aura-tip-row"><span>📌 Condición:</span><strong>' . $st_info['desc'] . '</strong></div>'
                            . '</div>'
                            . '</div>';
                    ?>
                    <tr class="aura-parent-row" data-row-id="<?php echo esc_attr( $mov->id ); ?>">
                        <td style="text-align:center;">
                            <div class="aura-id-toggle-wrap">
                                <button type="button" class="aura-row-toggle" aria-expanded="false" title="<?php esc_attr_e( 'Expandir / Ver detalles', 'aura-suite' ); ?>">+</button>
                                <span class="aura-txn-id-pill" data-tooltip="<?php echo esc_attr( $tip_txn_html ); ?>" style="cursor:help;">#<?php echo esc_html( $mov->id ); ?></span>
                            </div>
                        </td>
                        <td style="font-weight:600;" class="aura-col-mobile-hidden"><?php echo esc_html( $date_fmt ); ?></td>
                        <td style="text-align:center;">
                            <span class="aura-type-pill <?php echo $is_income ? 'income' : 'expense'; ?>" data-tooltip="<?php echo esc_attr( $tip_type_html ); ?>" style="cursor:help;">
                                <?php echo $is_income ? '↗' : '↙'; ?>
                            </span>
                        </td>
                        <td class="aura-col-desc">
                            <strong class="aura-desc-trigger" data-tooltip="<?php echo esc_attr( $tip_desc_html ); ?>" style="cursor:help;"><?php echo esc_html( $mov->description ); ?></strong>
                        </td>
                        <td class="aura-col-tablet-hidden">
                            <span class="aura-concept-pill" data-tooltip="<?php echo esc_attr( $tip_concept_html ); ?>" style="cursor:help;">
                                <span class="dashicons dashicons-tag"></span>
                                <?php echo esc_html( $concept_label ); ?>
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <span class="aura-ud-amount <?php echo $is_income ? 'income' : 'expense'; ?>" data-tooltip="<?php echo esc_attr( $tip_amount_html ); ?>" style="cursor:help;">
                                <?php echo $is_income ? '+' : '-'; ?>
                                <?php echo esc_html( $amount_fmt ); ?>
                            </span>
                        </td>
                        <td class="aura-col-tablet-hidden">
                            <div class="aura-creator-trigger" data-tooltip="<?php echo esc_attr( $tip_creator_html ); ?>" style="display:inline-flex;align-items:center;gap:8px;cursor:help;">
                                <?php if ( $creator_avatar ) : ?>
                                <img src="<?php echo esc_url( $creator_avatar ); ?>" 
                                     alt=""
                                     class="avatar-zoomable"
                                     style="width:24px;height:24px;border-radius:50%;border:1px solid var(--border-color, #cbd5e1);" />
                                <?php endif; ?>
                                <small style="font-weight:600;"><?php echo esc_html( $creator_name ); ?></small>
                            </div>
                        </td>
                        <td style="text-align:center;" class="aura-col-mobile-hidden">
                            <span class="aura-status-trigger" data-tooltip="<?php echo esc_attr( $tip_status_html ); ?>" style="display:inline-block;cursor:help;">
                                <?php echo $status_html; ?>
                            </span>
                        </td>
                    </tr>

                    <!-- Fila Hija Expandible (Child Row) -->
                    <tr class="aura-child-row" data-child-for="<?php echo esc_attr( $mov->id ); ?>">
                        <td colspan="8" style="padding:0 !important; width:100% !important;">
                            <div class="aura-child-card" style="width:100% !important; max-width:100% !important; box-sizing:border-box !important;">
                                <div class="aura-child-hero-bar">
                                    <div class="aura-child-hero-left">
                                        <span class="aura-type-pill <?php echo $is_income ? 'income' : 'expense'; ?>">
                                            <?php echo $is_income ? '↗' : '↙'; ?>
                                        </span>
                                        <div>
                                            <h4 class="aura-child-hero-title"><?php echo esc_html( $mov->description ); ?></h4>
                                            <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap;">
                                                <span class="aura-concept-pill">
                                                    <span class="dashicons dashicons-calendar-alt"></span>
                                                    <?php echo esc_html( $date_fmt ); ?>
                                                </span>
                                                <span class="aura-concept-pill">
                                                    <span class="dashicons dashicons-tag"></span>
                                                    <?php echo esc_html( $concept_label ); ?>
                                                </span>
                                                <span class="aura-txn-id-pill">
                                                    #<?php echo esc_html( $mov->id ); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <span class="aura-ud-amount <?php echo $is_income ? 'income' : 'expense'; ?>" style="font-size:16px;">
                                            <?php echo $is_income ? '+' : '-'; ?><?php echo esc_html( $amount_fmt ); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="aura-child-sections-grid" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; width:100% !important; box-sizing:border-box !important;">
                                    <div class="aura-child-subcard">
                                        <div class="aura-subcard-header">
                                            <span class="dashicons dashicons-info"></span>
                                            <span><?php esc_html_e( 'Datos del Registro', 'aura-suite' ); ?></span>
                                        </div>
                                        <div class="aura-child-meta-item">
                                            <span class="aura-child-meta-label"><?php esc_html_e( 'ID Transacción', 'aura-suite' ); ?>:</span>
                                            <span class="aura-child-meta-val">#<?php echo esc_html( $mov->id ); ?></span>
                                        </div>
                                        <div class="aura-child-meta-item">
                                            <span class="aura-child-meta-label"><?php esc_html_e( 'Flujo Personal', 'aura-suite' ); ?>:</span>
                                            <span class="aura-child-meta-val"><?php echo $is_income ? esc_html__( 'Cobro Recibido (+)', 'aura-suite' ) : esc_html__( 'Pago Realizado (-)', 'aura-suite' ); ?></span>
                                        </div>
                                        <div class="aura-child-meta-item">
                                            <span class="aura-child-meta-label"><?php esc_html_e( 'Fecha', 'aura-suite' ); ?>:</span>
                                            <span class="aura-child-meta-val"><?php echo esc_html( $date_fmt ); ?></span>
                                        </div>
                                    </div>

                                    <div class="aura-child-subcard">
                                        <div class="aura-subcard-header">
                                            <span class="dashicons dashicons-admin-users"></span>
                                            <span><?php esc_html_e( 'Registrado Por', 'aura-suite' ); ?></span>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:10px;margin-top:6px;">
                                            <?php if ( $creator_avatar ) : ?>
                                            <img src="<?php echo esc_url( $creator_avatar ); ?>" alt="" class="avatar-zoomable" style="width:32px;height:32px;border-radius:50%;border:1px solid var(--border-color, #cbd5e1);flex-shrink:0;" />
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo esc_html( $creator_name ); ?></strong>
                                                <div style="font-size:11.5px;color:var(--tx-muted, #64748b);"><?php echo esc_html( $creator ? $creator->user_email : 'Sistema' ); ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="aura-child-subcard">
                                        <div class="aura-subcard-header">
                                            <span class="dashicons dashicons-shield"></span>
                                            <span><?php esc_html_e( 'Auditoría y Estado', 'aura-suite' ); ?></span>
                                        </div>
                                        <div style="margin-top:6px;">
                                            <?php echo $status_html; ?>
                                        </div>
                                        <div style="font-size:11.5px;color:var(--tx-muted, #64748b);margin-top:6px;">
                                            <?php
                                            if ( $mov->status === 'approved' ) {
                                                esc_html_e( 'Operación validada contablemente.', 'aura-suite' );
                                            } elseif ( $mov->status === 'pending' ) {
                                                esc_html_e( 'En espera de aprobación administrativa.', 'aura-suite' );
                                            } else {
                                                esc_html_e( 'Transacción anulada o rechazada.', 'aura-suite' );
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            if ( $total_pages > 1 ) :
                $page_links = paginate_links( [
                    'base'      => add_query_arg( 'paged', '%#%', $base_url ),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $total_pages,
                    'current'   => $filters['paged'],
                ] );
                echo '<div class="tablenav bottom" style="padding:14px 18px;border-top:1px solid var(--border-color, #e2e8f0);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;"><div class="tablenav-pages">' . $page_links . '</div></div>';
            endif;
            ?>
            <?php endif; ?>

        </div>
    </div><!-- /.aura-ud-table-card -->

    <!-- ====================================================
         Equipos a Cargo (Inventario)
    ===================================================== -->
    <?php if ( ! empty( $loans ) ) : ?>
    <div class="aura-ud-table-card card" style="background:var(--bg-surface, #ffffff); border:1px solid var(--border-color, #e2e8f0); border-radius:var(--r-xl, 18px); margin-bottom:24px; overflow:hidden;">
        <div class="aura-ud-table-card__header" style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border-color, #e2e8f0); background:var(--bg-alt, #f8fafc);">
            <h2 class="aura-ud-table-card__title" style="margin:0; font-size:15px; font-weight:700; color:var(--tx-primary, #0f172a); display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-archive" style="color:var(--primary, #2563eb);"></span>
                <?php esc_html_e( 'Equipos a mi Cargo (Inventario)', 'aura-suite' ); ?>
                <span class="badge badge-blue pill" style="font-size:11px;"><?php echo count( $loans ); ?></span>
            </h2>
        </div>
        <div class="aura-ud-table-wrap">
            <table class="aura-ud-table" style="width:100% !important; min-width:100% !important; max-width:100% !important; table-layout:auto !important; box-sizing:border-box !important;">
                <thead>
                    <tr>
                        <th>
                            <?php esc_html_e( 'Equipo / Artículo', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Nombre del artículo o activo asignado', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th style="width:150px">
                            <?php esc_html_e( 'Fecha Préstamo', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Fecha en que se realizó la entrega del equipo', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th style="width:170px">
                            <?php esc_html_e( 'Devolución Estimada', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Fecha límite acordada para la devolución', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
                        <th style="width:120px;text-align:center">
                            <?php esc_html_e( 'Estado', 'aura-suite' ); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Vigencia actual del préstamo', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                        </th>
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
                                <span class="badge badge-rose pill"><span class="dashicons dashicons-warning" style="font-size:13px;width:13px;height:13px;margin-right:4px;"></span><?php esc_html_e( 'Vencido', 'aura-suite' ); ?></span>
                            <?php else : ?>
                                <span class="badge badge-emerald pill"><span class="dashicons dashicons-yes-alt" style="font-size:13px;width:13px;height:13px;margin-right:4px;"></span><?php esc_html_e( 'Activo', 'aura-suite' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ====================================================
         Pie de Impresión Oficial (@media print)
    ===================================================== -->
    <div class="aura-print-footer">
        <div class="aura-print-footer__left">
            <span class="dashicons dashicons-shield-alt"></span>
            <?php esc_html_e( 'Documento confidencial emitido por Aura Business Suite. Válido para propósitos administrativos y contables.', 'aura-suite' ); ?>
        </div>
        <div class="aura-print-footer__right">
            <?php echo esc_html( get_bloginfo( 'name' ) ); ?> &bull; <?php esc_html_e( 'Página 1', 'aura-suite' ); ?>
        </div>
    </div>

    <!-- ====================================================
         Footer Canónico Aura Design System
    ===================================================== -->
    <div class="adp-footer">
        <p>
            Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.216.694.825.576C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>DiGiraldo</a></strong> &nbsp;|&nbsp; © 2026 AURA Business Suite
        </p>
    </div>

</div><!-- /.wrap.aura-user-dashboard-wrap -->

<!-- ====================================================
     Modal Universal de Resumen Financiero Gráfico (Canónico)
===================================================== -->
<div id="aura-summary-modal" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-summary-modal-title">
    <div class="aura-modal-container" style="max-width:640px; width:100%; margin:auto !important; max-height:calc(100vh - 40px) !important; display:flex !important; flex-direction:column !important; overflow:hidden !important;">
        
        <!-- Cabecera del Modal -->
        <div class="aura-modal-header" style="flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,0.2); backdrop-filter:blur(8px); display:flex; align-items:center; justify-content:center;">
                    <span class="dashicons dashicons-analytics" style="font-size:22px; width:22px; height:22px; color:#ffffff;"></span>
                </div>
                <div>
                    <h3 id="aura-summary-modal-title" style="margin:0; font-size:1.15rem; font-weight:700; color:#ffffff; line-height:1.2;">
                        <?php esc_html_e( 'Resumen Financiero Consolidado', 'aura-suite' ); ?>
                    </h3>
                    <p style="margin:2px 0 0; font-size:12.5px; color:rgba(255,255,255,0.85);">
                        <?php echo esc_html( $user_display_name ); ?>
                    </p>
                </div>
            </div>
            <button type="button" class="aura-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>

        <!-- Cuerpo con scroll -->
        <div class="aura-modal-body" style="flex:1 1 auto; overflow-y:auto; padding:22px 24px;">
            <div class="aura-summary-cards-grid" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; margin-bottom:20px;">
                <div class="kpi-card kpi-compact kpi-emerald">
                    <div class="kpi-label"><?php esc_html_e( 'Cobros', 'aura-suite' ); ?></div>
                    <div class="kpi-value" style="font-size:1.35rem; font-weight:800; color:var(--aura-emerald, #10b981);"><?php echo esc_html( $currency . number_format( $income, 2, '.', ',' ) ); ?></div>
                </div>
                <div class="kpi-card kpi-compact kpi-rose">
                    <div class="kpi-label"><?php esc_html_e( 'Pagos', 'aura-suite' ); ?></div>
                    <div class="kpi-value" style="font-size:1.35rem; font-weight:800; color:var(--aura-rose, #ef4444);"><?php echo esc_html( $currency . number_format( $expense, 2, '.', ',' ) ); ?></div>
                </div>
                <div class="kpi-card kpi-compact <?php echo $balance >= 0 ? 'kpi-blue' : 'kpi-rose'; ?>">
                    <div class="kpi-label"><?php esc_html_e( 'Balance', 'aura-suite' ); ?></div>
                    <div class="kpi-value" style="font-size:1.35rem; font-weight:800;"><?php echo esc_html( ( $balance >= 0 ? '+' : '' ) . $currency . number_format( abs( $balance ), 2, '.', ',' ) ); ?></div>
                </div>
            </div>
            
            <div class="card" style="background:var(--bg-alt, #f8fafc); border:1px solid var(--border-color, #e2e8f0); border-radius:12px; padding:20px; text-align:center;">
                <h4 style="margin:0 0 16px 0; font-size:13.5px; font-weight:700; color:var(--tx-primary, #0f172a);"><?php esc_html_e( 'Proporción Cobros / Pagos', 'aura-suite' ); ?></h4>
                <div style="display:flex; gap:24px; align-items:center; justify-content:center; flex-wrap:wrap;">
                    <div>
                        <div style="width:110px; height:110px; border-radius:50%; background:conic-gradient(#10b981 0deg <?php echo $income > 0 ? ( $income / ( $income + $expense ?: 1 ) * 360 ) : 0; ?>deg, #ef4444 <?php echo $income > 0 ? ( $income / ( $income + $expense ?: 1 ) * 360 ) : 0; ?>deg); margin:0 auto; box-shadow:0 4px 12px rgba(0,0,0,0.12);"></div>
                    </div>
                    <div style="text-align:left; font-size:13px; color:var(--tx-primary, #0f172a);">
                        <div style="margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                            <span style="display:inline-block; width:12px; height:12px; background:#10b981; border-radius:3px;"></span>
                            <strong><?php esc_html_e( 'Cobros:', 'aura-suite' ); ?></strong>
                            <span><?php echo esc_html( $currency . number_format( $income, 2, '.', ',' ) ); ?></span>
                            <small style="color:var(--tx-muted, #64748b);">(<?php echo $income + $expense > 0 ? number_format( ( $income / ( $income + $expense ) ) * 100, 1 ) : 0; ?>%)</small>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="display:inline-block; width:12px; height:12px; background:#ef4444; border-radius:3px;"></span>
                            <strong><?php esc_html_e( 'Pagos:', 'aura-suite' ); ?></strong>
                            <span><?php echo esc_html( $currency . number_format( $expense, 2, '.', ',' ) ); ?></span>
                            <small style="color:var(--tx-muted, #64748b);">(<?php echo $income + $expense > 0 ? number_format( ( $expense / ( $income + $expense ) ) * 100, 1 ) : 0; ?>%)</small>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ( $pending > 0 ) : ?>
            <div class="alert-card alert-warning" style="margin-top:16px; display:flex; align-items:center; gap:12px; padding:12px 16px;">
                <span class="dashicons dashicons-clock" style="font-size:24px; width:24px; height:24px; color:var(--aura-amber, #d97706); flex-shrink:0;"></span>
                <div>
                    <strong style="color:var(--tx-primary, #0f172a);"><?php esc_html_e( 'Movimientos pendientes de aprobación', 'aura-suite' ); ?></strong>
                    <p style="margin:2px 0 0 0; font-size:12px; color:var(--tx-secondary, #475569);"><?php printf( esc_html__( 'Tienes %d movimientos en espera de revisión administrativa.', 'aura-suite' ), $pending ); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pie Fijo -->
        <div class="aura-modal-footer" style="flex-shrink:0;">
            <button type="button" class="btn btn-secondary btn-lift aura-modal-close">
                <?php esc_html_e( 'Cerrar', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- ====================================================
     Scripts de Interacción, Portal Tooltips y Chips
===================================================== -->
<script>
(function($) {
    'use strict';

    $(document).ready(function() {

        // ── 1. Portal Global de Tooltips (Gestionado por AuraUI) ──────
        if (typeof AuraUI !== 'undefined' && AuraUI.initTooltips) {
            AuraUI.initTooltips();
        }

        // ── 2. Modal "Ver Resumen" con Skeleton Loaders y Anti-Cierre Accidental ──
        const $modal = $('#aura-summary-modal');
        const $viewSummaryBtn = $('#aura-view-my-summary-btn');
        const $modalCloseBtn = $modal.find('.aura-modal-close');
        let isBackdropMouseDown = false;

        function openSummaryModal() {
            $modal.addClass('is-active').show();
            $('body').css('overflow', 'hidden');

            if (typeof AuraUI !== 'undefined' && typeof AuraUI.showModalSkeleton === 'function') {
                AuraUI.showModalSkeleton('aura-summary-modal', { duration: 280 });
            }
        }

        function closeSummaryModal() {
            $modal.removeClass('is-active').hide();
            $('body').css('overflow', '');

            if (typeof AuraUI !== 'undefined' && typeof AuraUI.hideModalSkeleton === 'function') {
                AuraUI.hideModalSkeleton('aura-summary-modal');
            }
        }

        $viewSummaryBtn.on('click', function(e) {
            e.preventDefault();
            openSummaryModal();
        });

        $modalCloseBtn.on('click', function(e) {
            e.preventDefault();
            closeSummaryModal();
        });

        // Validación en dos fases contra cierre involuntario al seleccionar texto
        $modal.on('mousedown', function(e) {
            isBackdropMouseDown = (e.target === this);
        });

        $modal.on('mouseup', function(e) {
            if (isBackdropMouseDown && e.target === this) {
                closeSummaryModal();
            }
            isBackdropMouseDown = false;
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.is(':visible')) {
                closeSummaryModal();
            }
        });

        // ── 3. Botón Imprimir / PDF ───────────────────────────
        $('#aura-print-dashboard-btn').on('click', function(e) {
            e.preventDefault();
            window.print();
        });

        // ── 4. Chips de Período Rápido ────────────────────────
        $('.aura-chip-btn').on('click', function(e) {
            e.preventDefault();
            const period = $(this).data('period');
            const today = new Date();
            let fromStr = '';
            let toStr = '';

            const formatDate = (d) => {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            if (period === 'this_month') {
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                fromStr = formatDate(firstDay);
                toStr = formatDate(today);
            } else if (period === 'last_month') {
                const firstDayLast = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                const lastDayLast = new Date(today.getFullYear(), today.getMonth(), 0);
                fromStr = formatDate(firstDayLast);
                toStr = formatDate(lastDayLast);
            } else if (period === 'quarter') {
                const currentQuarter = Math.floor(today.getMonth() / 3);
                const firstDayQ = new Date(today.getFullYear(), currentQuarter * 3, 1);
                fromStr = formatDate(firstDayQ);
                toStr = formatDate(today);
            } else if (period === 'this_year') {
                const firstDayY = new Date(today.getFullYear(), 0, 1);
                fromStr = formatDate(firstDayY);
                toStr = formatDate(today);
            } else if (period === 'all') {
                fromStr = '';
                toStr = '';
            }

            $('#ud-date-from').val(fromStr);
            $('#ud-date-to').val(toStr);

            $('.aura-chip-btn').removeClass('is-active');
            $(this).addClass('is-active');

            $('#aura-ud-filters-form').submit();
        });

        // ── 5. Sistema Universal de Child Rows ─────────────────
        $(document).on('click', '.aura-row-toggle', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $parentRow = $btn.closest('tr');
            const $childRow = $parentRow.next('.aura-child-row');

            const isOpen = $childRow.hasClass('is-open');

            if (isOpen) {
                $childRow.removeClass('is-open');
                $btn.removeClass('is-active').text('+').attr('aria-expanded', 'false');
            } else {
                $childRow.addClass('is-open');
                $btn.addClass('is-active').text('−').attr('aria-expanded', 'true');
            }
        });

        // ── 6. Autocomplete con jQuery UI para Selector Admin ─
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
                        '<div style="display:flex;align-items:center;gap:8px;padding:6px 10px;">' +
                        '<img src="' + item.avatar_url + '" width="24" height="24" style="border-radius:50%;">' +
                        '<div><strong>' + $('<span>').text(item.name).html() + '</strong>' +
                        '<br><small style="color:var(--tx-muted, #64748b)">' + $('<span>').text(item.email).html() + '</small></div>' +
                        '</div>'
                    )
                    .appendTo(ul);
            };
        }

        // Botón "Ver Dashboard"
        $('#ud-view-user-btn').on('click', function() {
            const uid = $udHidden.val();
            if (! uid) {
                alert(<?php echo json_encode( __( 'Selecciona un usuario de la lista.', 'aura-suite' ) ); ?>);
                return;
            }
            window.location.href = <?php echo json_encode( $base_url . '&view_user=' ); ?> + uid;
        });

    });
})(jQuery);
</script>
