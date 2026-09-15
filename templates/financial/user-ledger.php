<?php
/**
 * Template: Libro Mayor por Usuario (Fase 6, Item 6.3)
 *
 * Accesible en: /wp-admin/admin.php?page=aura-user-ledger
 * Capability:   aura_finance_user_ledger
 *
 * Optimizado según directrices visuales y estructurales de prompt-maestro.md.
 * Incluye panel interactivo de estadísticas, botones de exportación a Excel (.xlsx) y CSV en español.
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -----------------------------------------------------------------------
// Parámetros GET & sanitización
// -----------------------------------------------------------------------
$req_user_id   = intval( $_GET['ledger_user_id'] ?? 0 );
$req_date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
$req_date_to   = sanitize_text_field( $_GET['date_to']   ?? '' );
$req_concept   = sanitize_key( $_GET['concept']           ?? '' );
$req_show_all  = ! empty( $_GET['show_all'] );
$req_paged     = max( 1, intval( $_GET['paged'] ?? 1 ) );

// Moneda
$currency = get_option( 'aura_currency_symbol', '$' );

// Conceptos
$concepts_map = Aura_Financial_User_Ledger::get_concepts_labels();

// Helper: etiqueta de rol principal en español
$aura_role_labels = [
    'administrator' => 'Administrador',
    'editor'        => 'Editor',
    'author'        => 'Autor',
    'contributor'   => 'Colaborador',
    'subscriber'    => 'Suscriptor',
    'driver'        => 'Conductor',
];
$get_primary_role_label = function( WP_User $u ) use ( $aura_role_labels ) {
    if ( empty( $u->roles ) ) return '';
    $role = $u->roles[0];
    return $aura_role_labels[ $role ] ?? ucfirst( $role );
};

// -----------------------------------------------------------------------
// Datos del usuario seleccionado
// -----------------------------------------------------------------------
$selected_user = $req_user_id ? get_userdata( $req_user_id ) : null;

// Obtener usuarios del sistema ordenados alfabéticamente
$all_ledger_users = get_users( [
    'orderby' => 'display_name',
    'order'   => 'ASC',
] );

$filters = [
    'date_from' => $req_date_from,
    'date_to'   => $req_date_to,
    'concept'   => $req_concept,
    'show_all'  => $req_show_all,
    'paged'     => $req_paged,
];

$rows       = [];
$totals     = [ 'income' => 0.0, 'expense' => 0.0, 'net' => 0.0 ];
$total_rows = 0;
$stats      = null;

if ( $req_user_id && $selected_user ) {
    $rows       = Aura_Financial_User_Ledger::get_ledger_rows( $req_user_id, $filters );
    $totals     = Aura_Financial_User_Ledger::get_ledger_totals( $req_user_id, $filters );
    $total_rows = Aura_Financial_User_Ledger::count_ledger_rows( $req_user_id, $filters );
    $stats      = Aura_Financial_User_Ledger::get_ledger_statistics( $req_user_id, $filters );
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

// Nonces
$export_nonce = wp_create_nonce( 'aura_transaction_nonce' );
?>

<div class="wrap aura-user-ledger-wrap aura-module-wrap">

    <!-- ── Hero Banner Superior ─────────────────────────────────────── -->
    <div class="aura-hero-header">
        <div class="aura-hero-content">
            <div class="aura-hero-icon-box">
                <span class="dashicons dashicons-book-alt"></span>
            </div>
            <div class="aura-hero-titles">
                <div class="aura-hero-subtitle-row">
                    <span class="aura-tag-badge">
                        <span class="dashicons dashicons-shield"></span>
                        <?php esc_html_e( 'Aura Finanzas', 'aura-suite' ); ?>
                    </span>
                    <span class="aura-tag-badge aura-tag-secondary">
                        <span class="dashicons dashicons-id-alt"></span>
                        <?php esc_html_e( 'Libro Mayor por Usuario', 'aura-suite' ); ?>
                    </span>
                </div>
                <h1 class="aura-hero-title">
                    <?php esc_html_e( 'Libro Mayor Personal', 'aura-suite' ); ?>
                    <?php if ( $selected_user ) : ?>
                        <span class="aura-hero-user-name">— <?php echo esc_html( $selected_user->display_name ); ?></span>
                    <?php endif; ?>
                </h1>
                <p class="aura-hero-desc">
                    <?php if ( $selected_user ) : ?>
                        <?php printf(
                            esc_html__( 'Auditoría cronológica y balance de cuentas corrientes de %1$s (@%2$s) con la organización.', 'aura-suite' ),
                            '<strong>' . esc_html( $selected_user->display_name ) . '</strong>',
                            esc_html( $selected_user->user_login )
                        ); ?>
                    <?php else : ?>
                        <?php esc_html_e( 'Selecciona un colaborador o tercero para consultar su estado de cuenta corriente, transacciones y balance acumulado.', 'aura-suite' ); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="aura-hero-actions">
            <?php if ( $req_user_id && $selected_user ) : ?>

            <!-- Botón Toggle Estadísticas -->
            <button type="button" id="aura-toggle-stats-btn" class="aura-btn aura-btn-secondary" data-tooltip="Ver estadísticas avanzadas, gráficos y promedios contables">
                <span class="dashicons dashicons-chart-pie"></span>
                <span><?php esc_html_e( 'Estadísticas', 'aura-suite' ); ?></span>
            </button>

            <!-- Formulario y Botón Exportar Excel (Libro Mayor) -->
            <form id="aura-ledger-excel-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" style="display:inline-block;margin:0;">
                <input type="hidden" name="action"    value="aura_export_ledger_excel">
                <input type="hidden" name="nonce"     value="<?php echo esc_attr( $export_nonce ); ?>">
                <input type="hidden" name="user_id"   value="<?php echo esc_attr( $req_user_id ); ?>">
                <input type="hidden" name="date_from" value="<?php echo esc_attr( $req_date_from ); ?>">
                <input type="hidden" name="date_to"   value="<?php echo esc_attr( $req_date_to ); ?>">
                <input type="hidden" name="concept"   value="<?php echo esc_attr( $req_concept ); ?>">
                <input type="hidden" name="show_all"  value="<?php echo $req_show_all ? '1' : ''; ?>">
                <button type="submit" class="aura-btn aura-btn-excel" data-tooltip="Descargar Libro Mayor en formato Microsoft Excel (.xlsx)">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <span><?php esc_html_e( 'Exportar Excel', 'aura-suite' ); ?></span>
                </button>
            </form>

            <!-- Formulario y Botón Exportar CSV (Libro Mayor) -->
            <form id="aura-ledger-csv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" style="display:inline-block;margin:0;">
                <input type="hidden" name="action"    value="aura_export_ledger_csv">
                <input type="hidden" name="nonce"     value="<?php echo esc_attr( $export_nonce ); ?>">
                <input type="hidden" name="user_id"   value="<?php echo esc_attr( $req_user_id ); ?>">
                <input type="hidden" name="date_from" value="<?php echo esc_attr( $req_date_from ); ?>">
                <input type="hidden" name="date_to"   value="<?php echo esc_attr( $req_date_to ); ?>">
                <input type="hidden" name="concept"   value="<?php echo esc_attr( $req_concept ); ?>">
                <input type="hidden" name="show_all"  value="<?php echo $req_show_all ? '1' : ''; ?>">
                <button type="submit" class="aura-btn aura-btn-primary" data-tooltip="Descargar Libro Mayor en archivo CSV delimitado">
                    <span class="dashicons dashicons-download"></span>
                    <span><?php esc_html_e( 'Exportar CSV', 'aura-suite' ); ?></span>
                </button>
            </form>
            <?php endif; ?>

            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-transactions' ) ); ?>" class="aura-btn aura-btn-secondary" data-tooltip="Ir al listado general de transacciones">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <span><?php esc_html_e( 'Transacciones', 'aura-suite' ); ?></span>
            </a>
        </div>
    </div>

    <!-- ── Selector de Usuario y Tarjeta de Perfil ──────────────────── -->
    <div class="aura-card aura-user-select-card" style="margin-bottom: 24px;">
        <div class="aura-card-body">
            <div class="aura-user-select-layout">
                <div class="aura-select-picker-box">
                    <label for="user_select_ledger" class="aura-input-label">
                        <span class="dashicons dashicons-admin-users"></span>
                        <span><?php esc_html_e( 'Seleccionar Usuario / Colaborador:', 'aura-suite' ); ?></span>
                        <span class="aura-help-icon" data-tooltip="Elige el colaborador cuyo libro mayor y balance acumulado deseas auditar."><span class="dashicons dashicons-editor-help"></span></span>
                    </label>
                    <div class="aura-select-wrap">
                        <select id="user_select_ledger" name="ledger_user_id" class="aura-custom-select">
                            <option value=""><?php esc_html_e( '— Seleccionar Usuario o Tercero —', 'aura-suite' ); ?></option>
                            <?php foreach ( $all_ledger_users as $user ) :
                                $avatar_url = get_avatar_url( $user->ID, [ 'size' => 48 ] );
                                $role_label = $get_primary_role_label( $user );
                                $login_name = $user->user_login;
                            ?>
                                <option value="<?php echo esc_attr( $user->ID ); ?>"
                                        <?php selected( $req_user_id, $user->ID ); ?>
                                        data-avatar="<?php echo esc_url( $avatar_url ); ?>"
                                        data-email="<?php echo esc_attr( $user->user_email ); ?>"
                                        data-username="<?php echo esc_attr( $login_name ); ?>"
                                        data-role="<?php echo esc_attr( $role_label ); ?>"
                                        data-name="<?php echo esc_attr( $user->display_name ); ?>">
                                    <?php echo esc_html(
                                        $user->display_name . ' (@' . $login_name . ')' . ( ! empty( $user->user_email ) ? ' — ' . $user->user_email : '' ) . ( $role_label ? ' [' . $role_label . ']' : '' )
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if ( $selected_user ) :
                    $selected_avatar   = get_avatar_url( $selected_user->ID, [ 'size' => 64 ] );
                    $selected_role_lbl = $get_primary_role_label( $selected_user );
                ?>
                <div id="selected-user-card" class="aura-user-profile-badge">
                    <img src="<?php echo esc_url( $selected_avatar ); ?>"
                         alt="<?php echo esc_attr( $selected_user->display_name ); ?>"
                         class="aura-user-avatar">
                    <div class="aura-user-profile-info">
                        <div class="aura-user-name-row">
                            <h3 class="aura-user-display-name"><?php echo esc_html( $selected_user->display_name ); ?></h3>
                            <span class="aura-username-pill">@<?php echo esc_html( $selected_user->user_login ); ?></span>
                            <?php if ( $selected_role_lbl ) : ?>
                            <span class="aura-role-badge">
                                <?php echo esc_html( $selected_role_lbl ); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <p class="aura-user-email-text">
                            <span class="dashicons dashicons-email"></span>
                            <span><?php echo esc_html( $selected_user->user_email ?: __( 'Sin correo registrado', 'aura-suite' ) ); ?></span>
                            &bull;
                            <span class="dashicons dashicons-id"></span>
                            <span>ID #<?php echo (int) $selected_user->ID; ?></span>
                        </p>
                    </div>
                </div>
                <?php else : ?>
                <div id="selected-user-card" class="aura-user-profile-badge" style="display: none;"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ( $req_user_id && $selected_user ) : ?>

    <!-- ── Panel Desplegable de Estadísticas Avanzadas ──────────────── -->
    <?php if ( $stats && ! empty( $stats['metrics'] ) ) :
        $sm = $stats['metrics'];
    ?>
    <div id="aura-ledger-stats-panel" class="aura-card aura-stats-container" style="display: none; margin-bottom: 24px;">
        <div class="aura-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div class="aura-card-title-group">
                <span class="dashicons dashicons-chart-pie" style="color: #6366f1;"></span>
                <h3><?php printf( esc_html__( 'Estadísticas Contables: %s', 'aura-suite' ), esc_html( $selected_user->display_name ) ); ?></h3>
                <span class="aura-help-icon" data-tooltip="Análisis cuantitativo de conceptos, promedios y comportamiento mensual del colaborador."><span class="dashicons dashicons-editor-help"></span></span>
            </div>

            <!-- Botones de Exportación de Estadísticas -->
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">

                <!-- Exportar Estadísticas Excel -->
                <form id="aura-stats-excel-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" style="margin:0;">
                    <input type="hidden" name="action"    value="aura_export_ledger_stats_excel">
                    <input type="hidden" name="nonce"     value="<?php echo esc_attr( $export_nonce ); ?>">
                    <input type="hidden" name="user_id"   value="<?php echo esc_attr( $req_user_id ); ?>">
                    <input type="hidden" name="date_from" value="<?php echo esc_attr( $req_date_from ); ?>">
                    <input type="hidden" name="date_to"   value="<?php echo esc_attr( $req_date_to ); ?>">
                    <input type="hidden" name="concept"   value="<?php echo esc_attr( $req_concept ); ?>">
                    <input type="hidden" name="show_all"  value="<?php echo $req_show_all ? '1' : ''; ?>">
                    <button type="submit" class="aura-btn aura-btn-excel" style="font-size:12px;padding:6px 12px;height:34px;" data-tooltip="Exportar informe estadístico en Excel (.xlsx)">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <span><?php esc_html_e( 'Excel Estadísticas', 'aura-suite' ); ?></span>
                    </button>
                </form>

                <!-- Exportar Estadísticas CSV -->
                <form id="aura-stats-csv-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" style="margin:0;">
                    <input type="hidden" name="action"    value="aura_export_ledger_stats_csv">
                    <input type="hidden" name="nonce"     value="<?php echo esc_attr( $export_nonce ); ?>">
                    <input type="hidden" name="user_id"   value="<?php echo esc_attr( $req_user_id ); ?>">
                    <input type="hidden" name="date_from" value="<?php echo esc_attr( $req_date_from ); ?>">
                    <input type="hidden" name="date_to"   value="<?php echo esc_attr( $req_date_to ); ?>">
                    <input type="hidden" name="concept"   value="<?php echo esc_attr( $req_concept ); ?>">
                    <input type="hidden" name="show_all"  value="<?php echo $req_show_all ? '1' : ''; ?>">
                    <button type="submit" class="aura-btn aura-btn-secondary" style="font-size:12px;padding:6px 12px;height:34px;" data-tooltip="Exportar informe estadístico en CSV">
                        <span class="dashicons dashicons-download"></span>
                        <span><?php esc_html_e( 'CSV Estadísticas', 'aura-suite' ); ?></span>
                    </button>
                </form>

            </div>
        </div>

        <div class="aura-card-body">

            <!-- Fila de Mini-Métricas Clave -->
            <div class="aura-stats-metrics-row">
                <div class="aura-stat-mini-box">
                    <div class="mini-icon" style="background:#eff6ff;color:#2563eb;"><span class="dashicons dashicons-calculator"></span></div>
                    <div class="mini-info">
                        <span class="mini-label"><?php esc_html_e( 'Promedio / Operación', 'aura-suite' ); ?></span>
                        <strong class="mini-value"><?php echo esc_html( $currency . number_format( $sm['avg_transaction'], 2, '.', ',' ) ); ?></strong>
                    </div>
                </div>

                <div class="aura-stat-mini-box">
                    <div class="mini-icon" style="background:#ecfdf5;color:#059669;"><span class="dashicons dashicons-arrow-up-alt"></span></div>
                    <div class="mini-info">
                        <span class="mini-label"><?php esc_html_e( 'Mayor Ingreso', 'aura-suite' ); ?></span>
                        <strong class="mini-value" style="color:#059669;">+<?php echo esc_html( $currency . number_format( $sm['max_income'], 2, '.', ',' ) ); ?></strong>
                    </div>
                </div>

                <div class="aura-stat-mini-box">
                    <div class="mini-icon" style="background:#fef2f2;color:#dc2626;"><span class="dashicons dashicons-arrow-down-alt"></span></div>
                    <div class="mini-info">
                        <span class="mini-label"><?php esc_html_e( 'Mayor Egreso / Aporte', 'aura-suite' ); ?></span>
                        <strong class="mini-value" style="color:#dc2626;">-<?php echo esc_html( $currency . number_format( $sm['max_expense'], 2, '.', ',' ) ); ?></strong>
                    </div>
                </div>

                <div class="aura-stat-mini-box">
                    <div class="mini-icon" style="background:#f5f3ff;color:#7c3aed;"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div class="mini-info">
                        <span class="mini-label"><?php esc_html_e( 'Tasa de Aprobación', 'aura-suite' ); ?></span>
                        <strong class="mini-value" style="color:#7c3aed;"><?php echo esc_html( $sm['approval_rate'] ); ?>%</strong>
                    </div>
                </div>
            </div>

            <!-- Grilla de Análisis Gráfico -->
            <div class="aura-stats-grid">

                <!-- Columna 1: Desglose por Concepto Contable -->
                <div class="aura-stats-card-box">
                    <h4 class="aura-stats-box-title">
                        <span class="dashicons dashicons-category"></span>
                        <span><?php esc_html_e( 'Desglose por Concepto Contable', 'aura-suite' ); ?></span>
                    </h4>
                    <?php if ( ! empty( $stats['concepts'] ) ) : ?>
                    <div class="aura-concept-bars-list">
                        <?php foreach ( $stats['concepts'] as $c ) :
                            $is_inc = ( $c['type'] === 'Ingreso' );
                            $bar_color = $is_inc ? 'linear-gradient(90deg, #10b981, #059669)' : 'linear-gradient(90deg, #ef4444, #dc2626)';
                        ?>
                        <div class="aura-concept-bar-item">
                            <div class="aura-concept-bar-header">
                                <span class="concept-title">
                                    <span class="concept-dot" style="background:<?php echo $is_inc ? '#10b981' : '#ef4444'; ?>;"></span>
                                    <?php echo esc_html( $c['concept_name'] ); ?>
                                    <small style="color:#64748b;font-weight:400;">(<?php echo (int) $c['count']; ?> mov.)</small>
                                </span>
                                <span class="concept-amount" style="color:<?php echo $is_inc ? '#059669' : '#dc2626'; ?>;">
                                    <?php echo ( $is_inc ? '+' : '-' ) . esc_html( $currency . number_format( $c['total'], 2, '.', ',' ) ); ?>
                                    <small style="color:#64748b;font-weight:600;">(<?php echo esc_html( $c['pct'] ); ?>%)</small>
                                </span>
                            </div>
                            <div class="aura-concept-bar-track">
                                <div class="aura-concept-bar-fill" style="width:<?php echo min( 100, max( 4, (float) $c['pct'] ) ); ?>%;background:<?php echo $bar_color; ?>;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else : ?>
                        <p class="adb-empty-state"><?php esc_html_e( 'Sin datos para desglose de conceptos.', 'aura-suite' ); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Columna 2: Evolución Cronológica Mensual -->
                <div class="aura-stats-card-box">
                    <h4 class="aura-stats-box-title">
                        <span class="dashicons dashicons-calendar"></span>
                        <span><?php esc_html_e( 'Evolución Mensual (Ingresos vs Egresos)', 'aura-suite' ); ?></span>
                    </h4>
                    <?php if ( ! empty( $stats['monthly'] ) ) : ?>
                    <div class="aura-monthly-trend-list">
                        <?php foreach ( $stats['monthly'] as $mo ) : ?>
                        <div class="aura-month-trend-row">
                            <div class="aura-month-label">
                                <strong><?php echo esc_html( $mo['label'] ); ?></strong>
                                <small style="color:#64748b;display:block;font-size:11px;"><?php echo (int) $mo['count']; ?> mov.</small>
                            </div>
                            <div class="aura-month-values">
                                <div class="aura-month-val-line">
                                    <span class="val-tag income"><?php esc_html_e( 'Ingresos:', 'aura-suite' ); ?></span>
                                    <span class="val-num" style="color:#059669;">+<?php echo esc_html( $currency . number_format( $mo['income'], 2, '.', ',' ) ); ?></span>
                                </div>
                                <div class="aura-month-val-line">
                                    <span class="val-tag expense"><?php esc_html_e( 'Egresos:', 'aura-suite' ); ?></span>
                                    <span class="val-num" style="color:#dc2626;">-<?php echo esc_html( $currency . number_format( $mo['expense'], 2, '.', ',' ) ); ?></span>
                                </div>
                                <div class="aura-month-val-line net">
                                    <span class="val-tag net"><?php esc_html_e( 'Neto:', 'aura-suite' ); ?></span>
                                    <span class="val-num" style="color:<?php echo $mo['net'] >= 0 ? '#2563eb' : '#dc2626'; ?>;font-weight:800;">
                                        <?php echo ( $mo['net'] >= 0 ? '+' : '' ) . esc_html( $currency . number_format( abs( $mo['net'] ), 2, '.', ',' ) ); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else : ?>
                        <p class="adb-empty-state"><?php esc_html_e( 'Sin datos de evolución mensual.', 'aura-suite' ); ?></p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Barra Lateral / Fila de Filtros y Configuración ─────────── -->
    <div class="aura-card aura-filters-card" style="margin-bottom: 24px;">
        <div class="aura-card-header aura-filters-header">
            <div class="aura-card-title-group">
                <span class="dashicons dashicons-filter" style="color: #3b82f6;"></span>
                <h3><?php esc_html_e( 'Filtros y Período de Consulta', 'aura-suite' ); ?></h3>
                <span class="aura-help-icon" data-tooltip="Filtra las transacciones por fechas, concepto contable o incluye movimientos pendientes."><span class="dashicons dashicons-editor-help"></span></span>
            </div>
        </div>
        <div class="aura-card-body">
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="aura-ledger-filters-form">
                <input type="hidden" name="page"           value="aura-user-ledger">
                <input type="hidden" name="ledger_user_id" value="<?php echo esc_attr( $req_user_id ); ?>">

                <div class="aura-filters-grid">
                    <!-- Fecha Desde -->
                    <div class="aura-form-group">
                        <label for="lf_date_from" class="aura-input-label">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <span><?php esc_html_e( 'Fecha Desde', 'aura-suite' ); ?></span>
                            <span class="aura-help-icon" data-tooltip="Fecha inicial del rango contable."><span class="dashicons dashicons-editor-help"></span></span>
                        </label>
                        <input type="date" id="lf_date_from" name="date_from" class="aura-input" value="<?php echo esc_attr( $req_date_from ); ?>">
                    </div>

                    <!-- Fecha Hasta -->
                    <div class="aura-form-group">
                        <label for="lf_date_to" class="aura-input-label">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <span><?php esc_html_e( 'Fecha Hasta', 'aura-suite' ); ?></span>
                            <span class="aura-help-icon" data-tooltip="Fecha final del rango contable."><span class="dashicons dashicons-editor-help"></span></span>
                        </label>
                        <input type="date" id="lf_date_to" name="date_to" class="aura-input" value="<?php echo esc_attr( $req_date_to ); ?>">
                    </div>

                    <!-- Concepto -->
                    <div class="aura-form-group">
                        <label for="lf_concept" class="aura-input-label">
                            <span class="dashicons dashicons-tag"></span>
                            <span><?php esc_html_e( 'Concepto', 'aura-suite' ); ?></span>
                            <span class="aura-help-icon" data-tooltip="Filtra por la naturaleza contable de la operación (nómina, préstamo, viáticos, etc.)."><span class="dashicons dashicons-editor-help"></span></span>
                        </label>
                        <select id="lf_concept" name="concept" class="aura-input">
                            <option value=""><?php esc_html_e( '— Todos los Conceptos —', 'aura-suite' ); ?></option>
                            <?php foreach ( $concepts_map as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $req_concept, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Toggle Estado -->
                    <div class="aura-form-group aura-toggle-group">
                        <label class="aura-toggle-switch" data-tooltip="Marcar para incluir transacciones en estado pendiente o rechazadas">
                            <input type="checkbox" name="show_all" value="1" <?php checked( $req_show_all ); ?>>
                            <span class="aura-toggle-slider"></span>
                            <span class="aura-toggle-text"><?php esc_html_e( 'Incluir no aprobadas', 'aura-suite' ); ?></span>
                        </label>
                    </div>

                    <!-- Acciones de Filtro -->
                    <div class="aura-form-group aura-filter-buttons">
                        <button type="submit" class="aura-btn aura-btn-primary" data-tooltip="Aplicar los filtros seleccionados">
                            <span class="dashicons dashicons-search"></span>
                            <span><?php esc_html_e( 'Filtrar', 'aura-suite' ); ?></span>
                        </button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-user-ledger&ledger_user_id=' . $req_user_id ) ); ?>" class="aura-btn aura-btn-secondary" data-tooltip="Restablecer filtros a valores por defecto">
                            <span class="dashicons dashicons-image-rotate"></span>
                            <span><?php esc_html_e( 'Limpiar', 'aura-suite' ); ?></span>
                        </a>
                    </div>
                </div>

                <!-- Chips rápidos de fecha -->
                <div class="aura-quick-date-chips">
                    <span class="aura-chips-label"><?php esc_html_e( 'Atajos de Fecha:', 'aura-suite' ); ?></span>
                    <button type="button" class="aura-chip-btn" data-range="this-month"><?php esc_html_e( 'Este Mes', 'aura-suite' ); ?></button>
                    <button type="button" class="aura-chip-btn" data-range="last-month"><?php esc_html_e( 'Mes Anterior', 'aura-suite' ); ?></button>
                    <button type="button" class="aura-chip-btn" data-range="this-quarter"><?php esc_html_e( 'Este Trimestre', 'aura-suite' ); ?></button>
                    <button type="button" class="aura-chip-btn" data-range="this-year"><?php esc_html_e( 'Este Año', 'aura-suite' ); ?></button>
                    <button type="button" class="aura-chip-btn" data-range="all"><?php esc_html_e( 'Todo', 'aura-suite' ); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Tarjetas KPI / Resumen Contable ──────────────────────────── -->
    <div class="aura-kpi-grid" style="margin-bottom: 24px;">

        <!-- Total Ingresos (Perspectiva Usuario: Entradas recibidas de la org) -->
        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #10b981;">
                <span class="dashicons dashicons-arrow-up-alt"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e( 'Total Ingresos', 'aura-suite' ); ?></span>
                    <span class="aura-help-icon" data-tooltip="Perspectiva del Usuario: Egresos de la organización pagados al usuario (sueldos, pagos de reembolsos, liquidaciones)."><span class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #059669;">
                    +<?php echo esc_html( $currency . number_format( $totals['income'], 2, '.', ',' ) ); ?>
                </div>
                <div class="stat-sub"><?php esc_html_e( 'Percibido por el usuario', 'aura-suite' ); ?></div>
            </div>
        </div>

        <!-- Total Egresos (Perspectiva Usuario: Pagos hechos por el usuario a la org) -->
        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #ef4444;">
                <span class="dashicons dashicons-arrow-down-alt"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e( 'Total Egresos', 'aura-suite' ); ?></span>
                    <span class="aura-help-icon" data-tooltip="Perspectiva del Usuario: Entradas a la organización aportadas por el usuario (reintegros de sobrantes, aportes)."><span class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #dc2626;">
                    -<?php echo esc_html( $currency . number_format( $totals['expense'], 2, '.', ',' ) ); ?>
                </div>
                <div class="stat-sub"><?php esc_html_e( 'Entregado a la org', 'aura-suite' ); ?></div>
            </div>
        </div>

        <!-- Balance Neto Corriente -->
        <div class="aura-stat-card">
            <div class="stat-icon" style="background: <?php echo $totals['net'] >= 0 ? '#2563eb' : '#dc2626'; ?>;">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e( 'Balance Neto', 'aura-suite' ); ?></span>
                    <span class="aura-help-icon" data-tooltip="Diferencia neta (Ingresos - Egresos) acumulada en el período consultado."><span class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: <?php echo $totals['net'] >= 0 ? '#2563eb' : '#dc2626'; ?>;">
                    <?php echo ( $totals['net'] >= 0 ? '+' : '' ) . esc_html( $currency . number_format( abs( $totals['net'] ), 2, '.', ',' ) ); ?>
                </div>
                <div class="stat-sub"><?php echo $totals['net'] >= 0 ? esc_html__( 'Saldo a favor del usuario', 'aura-suite' ) : esc_html__( 'Saldo a favor de la org', 'aura-suite' ); ?></div>
            </div>
        </div>

        <!-- Total de Movimientos -->
        <div class="aura-stat-card">
            <div class="stat-icon" style="background: #6366f1;">
                <span class="dashicons dashicons-list-view"></span>
            </div>
            <div class="stat-info">
                <div class="stat-label-wrap">
                    <span class="stat-label"><?php esc_html_e( 'Movimientos', 'aura-suite' ); ?></span>
                    <span class="aura-help-icon" data-tooltip="Cantidad total de transacciones contables registradas para este usuario según los filtros."><span class="dashicons dashicons-editor-help"></span></span>
                </div>
                <div class="stat-value" style="color: #4f46e5;">
                    <?php echo esc_html( number_format( $total_rows ) ); ?>
                </div>
                <div class="stat-sub"><?php esc_html_e( 'Registros auditados', 'aura-suite' ); ?></div>
            </div>
        </div>

    </div>

    <!-- ── Tabla Principal de Movimientos ───────────────────────────── -->
    <div class="aura-card aura-table-card">
        <div class="aura-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div class="aura-card-title-group">
                <span class="dashicons dashicons-media-spreadsheet" style="color: #10b981;"></span>
                <h3><?php esc_html_e( 'Historial Detallado de Movimientos', 'aura-suite' ); ?></h3>
                <?php if ( $total_rows > 0 ) : ?>
                <span class="aura-badge aura-badge-blue">
                    <?php printf( esc_html__( '%s movimientos', 'aura-suite' ), number_format( $total_rows ) ); ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="aura-table-header-info">
                <?php
                if ( $total_rows > 0 ) {
                    $from = ( $req_paged - 1 ) * $per_page + 1;
                    $to   = min( $req_paged * $per_page, $total_rows );
                    printf(
                        esc_html__( 'Mostrando %1$s–%2$s de %3$s', 'aura-suite' ),
                        '<strong>' . number_format( $from ) . '</strong>',
                        '<strong>' . number_format( $to ) . '</strong>',
                        '<strong>' . number_format( $total_rows ) . '</strong>'
                    );
                }
                ?>
            </div>
        </div>

        <div class="aura-card-body" style="padding: 0;">
            <?php if ( ! empty( $rows ) ) : ?>
            <div class="aura-table-responsive-wrap">
                <table class="aura-modern-table aura-ledger-table">
                    <thead>
                        <tr>
                            <th style="width: 75px; text-align: center;">
                                <span><?php esc_html_e( '#', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Identificador único y botón para expandir detalles completos', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th class="col-date" style="width: 105px;">
                                <span><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Fecha en que se efectuó la transacción contable.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th class="col-desc">
                                <span><?php esc_html_e( 'Descripción / Referencia', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Detalle descriptivo del movimiento y número de comprobante/referencia.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th class="col-concept aura-col-tablet-hidden" style="width: 140px;">
                                <span><?php esc_html_e( 'Concepto Contable', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Clasificación contable de la operación con el colaborador.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th class="col-income" style="width: 120px; text-align: right;">
                                <span><?php esc_html_e( 'Ingreso (+)', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Importe percibido por el colaborador de parte de la organización.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th class="col-expense aura-col-mobile-hidden" style="width: 120px; text-align: right;">
                                <span><?php esc_html_e( 'Egreso (-)', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Importe entregado o devuelto por el colaborador a la organización.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th class="col-balance" style="width: 130px; text-align: right;">
                                <span><?php esc_html_e( 'Balance', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Saldo acumulativo corriente a la fecha de este movimiento.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                            <th class="col-status aura-col-mobile-hidden" style="width: 110px; text-align: center;">
                                <span><?php esc_html_e( 'Estado', 'aura-suite' ); ?></span>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Estado de validación contable: Aprobado, Pendiente o Rechazado.', 'aura-suite' ); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) :
                            $is_income    = ( $row->transaction_type === 'expense' ); // Perspectiva usuario: egreso org = ingreso usuario
                            $amount       = (float) $row->amount;
                            $balance      = (float) $row->running_balance;
                            $concept_lbl  = $concepts_map[ $row->related_user_concept ] ?? ( ! empty( $row->related_user_concept ) ? $row->related_user_concept : 'General' );
                            $date_fmt     = date_i18n( 'd/m/Y', strtotime( $row->transaction_date ) );
                            $creator      = ! empty( $row->created_by ) ? get_userdata( $row->created_by ) : null;
                            $creator_name = $creator ? $creator->display_name : 'Sistema';
                            $creator_avatar = $creator ? get_avatar_url( $creator->ID, [ 'size' => 48 ] ) : '';

                            $status_styles = [
                                'approved' => [ 'bg' => 'rgba(16,185,129,0.2)', 'color' => '#10b981', 'icon' => 'dashicons-yes-alt', 'desc' => esc_html__( 'Operación validada y computada formalmente en el libro mayor.', 'aura-suite' ) ],
                                'pending'  => [ 'bg' => 'rgba(245,158,11,0.2)', 'color' => '#f59e0b', 'icon' => 'dashicons-clock',   'desc' => esc_html__( 'En espera de aprobación administrativa o contable.', 'aura-suite' ) ],
                                'rejected' => [ 'bg' => 'rgba(239,68,68,0.2)',  'color' => '#ef4444', 'icon' => 'dashicons-dismiss', 'desc' => esc_html__( 'Transacción rechazada o anulada por auditoría.', 'aura-suite' ) ],
                            ];
                            $st_info = $status_styles[ $row->status ] ?? $status_styles['pending'];

                            $status_badge = match ( $row->status ) {
                                'approved' => '<span class="aura-badge aura-badge-green"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Aprobado', 'aura-suite' ) . '</span>',
                                'pending'  => '<span class="aura-badge aura-badge-orange"><span class="dashicons dashicons-clock"></span> ' . esc_html__( 'Pendiente', 'aura-suite' ) . '</span>',
                                'rejected' => '<span class="aura-badge aura-badge-red"><span class="dashicons dashicons-dismiss"></span> ' . esc_html__( 'Rechazado', 'aura-suite' ) . '</span>',
                                default    => '<span class="aura-badge">' . esc_html( ucfirst( $row->status ) ) . '</span>',
                            };

                            // ── Tarjetas Flotantes Enriquecidas (Tooltips HTML) ──
                            $tip_txn_html = '<div class="aura-tip-card">'
                                . '<div class="aura-tip-card-header">'
                                . '<div class="aura-tip-avatar-large" style="background:' . ( $is_income ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.2)' ) . ';border-color:' . ( $is_income ? 'rgba(16,185,129,0.4)' : 'rgba(239,68,68,0.4)' ) . ';color:' . ( $is_income ? '#10b981' : '#ef4444' ) . ';font-size:22px;font-weight:900;">'
                                . ( $is_income ? '↗' : '↙' )
                                . '</div>'
                                . '<div class="aura-tip-info">'
                                . '<div class="aura-tip-title">' . sprintf( esc_html__( 'Transacción #%d', 'aura-suite' ), $row->id ) . '</div>'
                                . '<div class="aura-tip-subtitle">' . ( $is_income ? esc_html__( 'Ingreso Personal (Egreso Org)', 'aura-suite' ) : esc_html__( 'Egreso Personal (Aporte/Reintegro)', 'aura-suite' ) ) . '</div>'
                                . '<div class="aura-tip-badges">'
                                . '<span class="aura-tip-badge ' . ( $is_income ? 'aura-tip-badge--role' : 'aura-tip-badge--company' ) . '">' . ( $is_income ? '🟢 Ingreso (+)' : '🔴 Egreso (-)' ) . '</span>'
                                . '</div>'
                                . '</div>'
                                . '</div>'
                                . '<div class="aura-tip-card-body">'
                                . '<div class="aura-tip-row"><span>📅 Fecha:</span><strong>' . esc_html( $date_fmt ) . '</strong></div>'
                                . '<div class="aura-tip-row"><span>💵 Importe:</span><strong>' . ( $is_income ? '+' : '-' ) . esc_html( $currency . number_format( $amount, 2, '.', ',' ) ) . '</strong></div>'
                                . '<div class="aura-tip-row"><span>🏷️ Concepto:</span><strong>' . esc_html( $concept_lbl ) . '</strong></div>'
                                . '<div class="aura-tip-row"><span>⚖️ Balance:</span><strong style="color:' . ( $balance >= 0 ? '#2563eb' : '#dc2626' ) . ';">' . ( $balance >= 0 ? '+' : '-' ) . esc_html( $currency . number_format( abs( $balance ), 2, '.', ',' ) ) . '</strong></div>'
                                . '</div>'
                                . '</div>';

                            $tip_desc_html = '<div class="aura-tip-card">'
                                . '<div class="aura-tip-card-header">'
                                . '<div class="aura-tip-avatar-large" style="background:rgba(37,99,235,0.2);color:#60a5fa;">'
                                . '<span class="dashicons dashicons-media-text" style="font-size:24px;width:24px;height:24px;"></span>'
                                . '</div>'
                                . '<div class="aura-tip-info">'
                                . '<div class="aura-tip-title">' . esc_html__( 'Detalle de la Operación', 'aura-suite' ) . '</div>'
                                . '<div class="aura-tip-subtitle">#' . esc_html( $row->id ) . ' &bull; ' . esc_html( $date_fmt ) . '</div>'
                                . '</div>'
                                . '</div>'
                                . '<div class="aura-tip-card-body">'
                                . '<div class="aura-tip-row"><span>📝 Descripción:</span><strong>' . esc_html( $row->description ) . '</strong></div>'
                                . ( ! empty( $row->reference_number ) ? '<div class="aura-tip-row"><span>🔖 Referencia:</span><strong>#' . esc_html( $row->reference_number ) . '</strong></div>' : '' )
                                . '<div class="aura-tip-row"><span>🏷️ Concepto:</span><strong>' . esc_html( $concept_lbl ) . '</strong></div>'
                                . '</div>'
                                . '</div>';

                            $tip_concept_html = '<div class="aura-tip-card">'
                                . '<div class="aura-tip-card-header">'
                                . '<div class="aura-tip-avatar-large" style="background:rgba(59,130,246,0.2);color:#60a5fa;">'
                                . '<span class="dashicons dashicons-tag" style="font-size:24px;width:24px;height:24px;"></span>'
                                . '</div>'
                                . '<div class="aura-tip-info">'
                                . '<div class="aura-tip-title">' . esc_html( $concept_lbl ) . '</div>'
                                . '<div class="aura-tip-subtitle">' . esc_html__( 'Concepto en Libro Mayor', 'aura-suite' ) . '</div>'
                                . '</div>'
                                . '</div>'
                                . '<div class="aura-tip-card-body">'
                                . '<div class="aura-tip-row"><span>🔑 Clave Interna:</span><strong>' . esc_html( $row->related_user_concept ?: 'general' ) . '</strong></div>'
                                . '<div class="aura-tip-row"><span>📄 Transacción:</span><strong>#' . esc_html( $row->id ) . '</strong></div>'
                                . '</div>'
                                . '</div>';

                            $tip_income_html = '<div class="aura-tip-card">'
                                . '<div class="aura-tip-card-header">'
                                . '<div class="aura-tip-avatar-large" style="background:rgba(16,185,129,0.2);color:#10b981;font-size:22px;">↗</div>'
                                . '<div class="aura-tip-info">'
                                . '<div class="aura-tip-title">+' . esc_html( $currency . number_format( $amount, 2, '.', ',' ) ) . '</div>'
                                . '<div class="aura-tip-subtitle">' . esc_html__( 'Ingreso percibido por el usuario', 'aura-suite' ) . '</div>'
                                . '</div>'
                                . '</div>'
                                . '<div class="aura-tip-card-body">'
                                . '<div class="aura-tip-row"><span>⚡ Tipo Flujo:</span><strong style="color:#10b981;">' . esc_html__( 'Abono / Saldo a favor (+)', 'aura-suite' ) . '</strong></div>'
                                . '<div class="aura-tip-row"><span>📌 Estado:</span><strong>' . esc_html( ucfirst( $row->status ) ) . '</strong></div>'
                                . '</div>'
                                . '</div>';

                            $tip_expense_html = '<div class="aura-tip-card">'
                                . '<div class="aura-tip-card-header">'
                                . '<div class="aura-tip-avatar-large" style="background:rgba(239,68,68,0.2);color:#ef4444;font-size:22px;">↙</div>'
                                . '<div class="aura-tip-info">'
                                . '<div class="aura-tip-title">-' . esc_html( $currency . number_format( $amount, 2, '.', ',' ) ) . '</div>'
                                . '<div class="aura-tip-subtitle">' . esc_html__( 'Egreso aportado o devuelto por el usuario', 'aura-suite' ) . '</div>'
                                . '</div>'
                                . '</div>'
                                . '<div class="aura-tip-card-body">'
                                . '<div class="aura-tip-row"><span>⚡ Tipo Flujo:</span><strong style="color:#dc2626;">' . esc_html__( 'Deducción / Descuento (-)', 'aura-suite' ) . '</strong></div>'
                                . '<div class="aura-tip-row"><span>📌 Estado:</span><strong>' . esc_html( ucfirst( $row->status ) ) . '</strong></div>'
                                . '</div>'
                                . '</div>';

                            $tip_balance_html = '<div class="aura-tip-card">'
                                . '<div class="aura-tip-card-header">'
                                . '<div class="aura-tip-avatar-large" style="background:' . ( $balance >= 0 ? 'rgba(37,99,235,0.2)' : 'rgba(239,68,68,0.2)' ) . ';color:' . ( $balance >= 0 ? '#3b82f6' : '#ef4444' ) . ';">'
                                . '<span class="dashicons dashicons-money-alt" style="font-size:24px;width:24px;height:24px;"></span>'
                                . '</div>'
                                . '<div class="aura-tip-info">'
                                . '<div class="aura-tip-title">' . ( $balance >= 0 ? '+' : '-' ) . esc_html( $currency . number_format( abs( $balance ), 2, '.', ',' ) ) . '</div>'
                                . '<div class="aura-tip-subtitle">' . ( $balance >= 0 ? esc_html__( 'Saldo a favor del usuario', 'aura-suite' ) : esc_html__( 'Saldo a favor de la organización', 'aura-suite' ) ) . '</div>'
                                . '</div>'
                                . '</div>'
                                . '<div class="aura-tip-card-body">'
                                . '<div class="aura-tip-row"><span>⚖️ Situación:</span><strong style="color:' . ( $balance >= 0 ? '#2563eb' : '#dc2626' ) . ';">' . ( $balance >= 0 ? esc_html__( 'Crédito a favor colaborador', 'aura-suite' ) : esc_html__( 'Deuda con la organización', 'aura-suite' ) ) . '</strong></div>'
                                . '<div class="aura-tip-row"><span>📅 Al corte de:</span><strong>' . esc_html( $date_fmt ) . '</strong></div>'
                                . '</div>'
                                . '</div>';

                            $tip_status_html = '<div class="aura-tip-card">'
                                . '<div class="aura-tip-card-header">'
                                . '<div class="aura-tip-avatar-large" style="background:' . $st_info['bg'] . ';color:' . $st_info['color'] . ';font-size:22px;">'
                                . '<span class="dashicons ' . esc_attr( $st_info['icon'] ) . '" style="font-size:24px;width:24px;height:24px;"></span>'
                                . '</div>'
                                . '<div class="aura-tip-info">'
                                . '<div class="aura-tip-title">' . sprintf( esc_html__( 'Estado: %s', 'aura-suite' ), ucfirst( $row->status ) ) . '</div>'
                                . '<div class="aura-tip-subtitle">' . esc_html__( 'Auditoría Contable', 'aura-suite' ) . '</div>'
                                . '</div>'
                                . '</div>'
                                . '<div class="aura-tip-card-body">'
                                . '<div class="aura-tip-row"><span>📌 Condición:</span><strong>' . $st_info['desc'] . '</strong></div>'
                                . '</div>'
                                . '</div>';
                        ?>
                        <tr class="aura-parent-row <?php echo $is_income ? 'aura-row-income' : 'aura-row-expense'; ?>" data-row-id="<?php echo esc_attr( $row->id ); ?>">
                            <td style="text-align: center;">
                                <div class="aura-id-toggle-wrap">
                                    <button type="button" class="aura-row-toggle" aria-expanded="false" title="<?php esc_attr_e( 'Expandir / Ver detalles', 'aura-suite' ); ?>">+</button>
                                    <span class="aura-txn-id-pill" data-tooltip="<?php echo esc_attr( $tip_txn_html ); ?>">#<?php echo esc_html( $row->id ); ?></span>
                                </div>
                            </td>
                            <td class="col-date">
                                <span class="aura-date-badge" data-tooltip="<?php echo esc_attr( "Fecha de registro: {$date_fmt}" ); ?>"><?php echo esc_html( $date_fmt ); ?></span>
                            </td>
                            <td class="col-desc">
                                <div class="aura-desc-text" data-tooltip="<?php echo esc_attr( $tip_desc_html ); ?>" style="cursor:help;"><?php echo esc_html( $row->description ); ?></div>
                                <?php if ( ! empty( $row->reference_number ) ) : ?>
                                    <div class="aura-ref-num"><span class="dashicons dashicons-media-text"></span> Ref #<?php echo esc_html( $row->reference_number ); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="col-concept aura-col-tablet-hidden">
                                <span class="aura-concept-pill" data-tooltip="<?php echo esc_attr( $tip_concept_html ); ?>" style="cursor:help;"><?php echo esc_html( $concept_lbl ); ?></span>
                            </td>
                            <td class="col-income" style="text-align: right;">
                                <?php if ( $is_income ) : ?>
                                    <span class="aura-amt-income" data-tooltip="<?php echo esc_attr( $tip_income_html ); ?>" style="cursor:help;">+<?php echo esc_html( $currency . number_format( $amount, 2, '.', ',' ) ); ?></span>
                                <?php else : ?>
                                    <span class="aura-amt-empty">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-expense aura-col-mobile-hidden" style="text-align: right;">
                                <?php if ( ! $is_income ) : ?>
                                    <span class="aura-amt-expense" data-tooltip="<?php echo esc_attr( $tip_expense_html ); ?>" style="cursor:help;">-<?php echo esc_html( $currency . number_format( $amount, 2, '.', ',' ) ); ?></span>
                                <?php else : ?>
                                    <span class="aura-amt-empty">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-balance" style="text-align: right;">
                                <span class="aura-amt-balance <?php echo $balance >= 0 ? 'is-positive' : 'is-negative'; ?>" data-tooltip="<?php echo esc_attr( $tip_balance_html ); ?>" style="cursor:help;">
                                    <?php echo esc_html( ( $balance >= 0 ? '+' : '-' ) . $currency . number_format( abs( $balance ), 2, '.', ',' ) ); ?>
                                </span>
                            </td>
                            <td class="col-status aura-col-mobile-hidden" style="text-align: center;">
                                <span class="aura-status-trigger" data-tooltip="<?php echo esc_attr( $tip_status_html ); ?>" style="display:inline-block;cursor:help;"><?php echo $status_badge; ?></span>
                            </td>
                        </tr>

                        <!-- Fila Hija Expandible (Universal Child Row) -->
                        <tr class="aura-child-row" data-child-for="<?php echo esc_attr( $row->id ); ?>">
                            <td colspan="8">
                                <div class="aura-child-card">
                                    <div class="aura-child-hero-bar">
                                        <div class="aura-child-hero-left">
                                            <span class="aura-type-pill <?php echo $is_income ? 'income' : 'expense'; ?>">
                                                <?php echo $is_income ? '↗' : '↙'; ?>
                                            </span>
                                            <div>
                                                <h4 class="aura-child-hero-title"><?php echo esc_html( $row->description ); ?></h4>
                                                <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap;">
                                                    <span class="aura-concept-pill">
                                                        <span class="dashicons dashicons-calendar-alt"></span>
                                                        <?php echo esc_html( $date_fmt ); ?>
                                                    </span>
                                                    <span class="aura-concept-pill">
                                                        <span class="dashicons dashicons-tag"></span>
                                                        <?php echo esc_html( $concept_lbl ); ?>
                                                    </span>
                                                    <span class="aura-txn-id-pill">
                                                        #<?php echo esc_html( $row->id ); ?>
                                                    </span>
                                                    <?php if ( ! empty( $row->reference_number ) ) : ?>
                                                    <span class="aura-concept-pill">
                                                        <span class="dashicons dashicons-media-text"></span>
                                                        Ref: #<?php echo esc_html( $row->reference_number ); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="text-align:right;">
                                            <span class="aura-amt-balance <?php echo $balance >= 0 ? 'is-positive' : 'is-negative'; ?>" style="font-size:16px;display:block;">
                                                Balance: <?php echo esc_html( ( $balance >= 0 ? '+' : '-' ) . $currency . number_format( abs( $balance ), 2, '.', ',' ) ); ?>
                                            </span>
                                            <small style="color:#64748b;font-weight:600;">
                                                <?php echo $is_income ? '+' . esc_html( $currency . number_format( $amount, 2, '.', ',' ) ) . ' (Ingreso)' : '-' . esc_html( $currency . number_format( $amount, 2, '.', ',' ) ) . ' (Egreso)'; ?>
                                            </small>
                                        </div>
                                    </div>

                                    <div class="aura-child-sections-grid">
                                        <!-- Sub-tarjeta 1: Datos de la Transacción -->
                                        <div class="aura-child-section">
                                            <h5 class="aura-child-section-title">
                                                <span class="dashicons dashicons-media-document"></span>
                                                <?php esc_html_e( 'Datos del Registro', 'aura-suite' ); ?>
                                            </h5>
                                            <div class="aura-child-meta-item">
                                                <span class="aura-child-meta-label"><?php esc_html_e( 'ID Transacción:', 'aura-suite' ); ?></span>
                                                <span class="aura-child-meta-val">#<?php echo esc_html( $row->id ); ?></span>
                                            </div>
                                            <div class="aura-child-meta-item">
                                                <span class="aura-child-meta-label"><?php esc_html_e( 'Naturaleza:', 'aura-suite' ); ?></span>
                                                <span class="aura-child-meta-val" style="color:<?php echo $is_income ? '#059669' : '#dc2626'; ?>;font-weight:700;">
                                                    <?php echo $is_income ? esc_html__( 'Ingreso Usuario (+)', 'aura-suite' ) : esc_html__( 'Egreso Usuario (-)', 'aura-suite' ); ?>
                                                </span>
                                            </div>
                                            <div class="aura-child-meta-item">
                                                <span class="aura-child-meta-label"><?php esc_html_e( 'Fecha Operación:', 'aura-suite' ); ?></span>
                                                <span class="aura-child-meta-val"><?php echo esc_html( $date_fmt ); ?></span>
                                            </div>
                                            <?php if ( ! empty( $row->reference_number ) ) : ?>
                                            <div class="aura-child-meta-item">
                                                <span class="aura-child-meta-label"><?php esc_html_e( 'No. Comprobante:', 'aura-suite' ); ?></span>
                                                <span class="aura-child-meta-val">#<?php echo esc_html( $row->reference_number ); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Sub-tarjeta 2: Registrado Por -->
                                        <div class="aura-child-section">
                                            <h5 class="aura-child-section-title">
                                                <span class="dashicons dashicons-admin-users"></span>
                                                <?php esc_html_e( 'Registrado Por', 'aura-suite' ); ?>
                                            </h5>
                                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                                <?php if ( $creator_avatar ) : ?>
                                                <img src="<?php echo esc_url( $creator_avatar ); ?>" alt="" style="width:34px;height:34px;border-radius:50%;border:1px solid #cbd5e1;" />
                                                <?php else : ?>
                                                <div class="aura-type-pill" style="background:#e2e8f0;color:#475569;"><span class="dashicons dashicons-admin-users"></span></div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong style="font-size:13px;color:#0f172a;display:block;"><?php echo esc_html( $creator_name ); ?></strong>
                                                    <small style="color:#64748b;font-size:11px;"><?php echo esc_html( $creator ? $creator->user_email : 'Sistema' ); ?></small>
                                                </div>
                                            </div>
                                            <div class="aura-child-meta-item">
                                                <span class="aura-child-meta-label"><?php esc_html_e( 'Concepto Contable:', 'aura-suite' ); ?></span>
                                                <span class="aura-child-meta-val"><?php echo esc_html( $concept_lbl ); ?></span>
                                            </div>
                                        </div>

                                        <!-- Sub-tarjeta 3: Auditoría y Balance -->
                                        <div class="aura-child-section">
                                            <h5 class="aura-child-section-title">
                                                <span class="dashicons dashicons-shield"></span>
                                                <?php esc_html_e( 'Auditoría & Saldo', 'aura-suite' ); ?>
                                            </h5>
                                            <div class="aura-child-meta-item">
                                                <span class="aura-child-meta-label"><?php esc_html_e( 'Estado Contable:', 'aura-suite' ); ?></span>
                                                <span class="aura-child-meta-val"><?php echo $status_badge; ?></span>
                                            </div>
                                            <div class="aura-child-meta-item">
                                                <span class="aura-child-meta-label"><?php esc_html_e( 'Monto Operación:', 'aura-suite' ); ?></span>
                                                <span class="aura-child-meta-val" style="color:<?php echo $is_income ? '#059669' : '#dc2626'; ?>;font-weight:700;">
                                                    <?php echo ( $is_income ? '+' : '-' ) . esc_html( $currency . number_format( $amount, 2, '.', ',' ) ); ?>
                                                </span>
                                            </div>
                                            <div class="aura-child-meta-item">
                                                <span class="aura-child-meta-label"><?php esc_html_e( 'Balance Resultante:', 'aura-suite' ); ?></span>
                                                <span class="aura-child-meta-val" style="color:<?php echo $balance >= 0 ? '#2563eb' : '#dc2626'; ?>;font-weight:800;">
                                                    <?php echo ( $balance >= 0 ? '+' : '-' ) . esc_html( $currency . number_format( abs( $balance ), 2, '.', ',' ) ); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="aura-ledger-totals-row">
                            <td colspan="4">
                                <strong><?php esc_html_e( 'TOTALES DEL PERÍODO AUDITADO', 'aura-suite' ); ?></strong>
                            </td>
                            <td class="col-income" style="text-align: right;">
                                <strong class="aura-amt-income">+<?php echo esc_html( $currency . number_format( $totals['income'], 2, '.', ',' ) ); ?></strong>
                            </td>
                            <td class="col-expense aura-col-mobile-hidden" style="text-align: right;">
                                <strong class="aura-amt-expense">-<?php echo esc_html( $currency . number_format( $totals['expense'], 2, '.', ',' ) ); ?></strong>
                            </td>
                            <td class="col-balance" style="text-align: right;">
                                <strong class="aura-amt-balance <?php echo $totals['net'] >= 0 ? 'is-positive' : 'is-negative'; ?>">
                                    <?php echo esc_html( ( $totals['net'] >= 0 ? '+' : '-' ) . $currency . number_format( abs( $totals['net'] ), 2, '.', ',' ) ); ?>
                                </strong>
                            </td>
                            <td class="aura-col-mobile-hidden"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- ── Paginación ──────────────────────────────────────────── -->
            <?php if ( $total_pages > 1 ) : ?>
            <div class="aura-ledger-pagination-bar">
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
            <div class="aura-empty-state-box">
                <span class="dashicons dashicons-yes-alt" style="font-size: 36px; width: 36px; height: 36px; color: #10b981; margin-bottom: 8px;"></span>
                <h4 style="margin:0 0 6px 0;font-size:15px;font-weight:700;color:#0f172a;"><?php esc_html_e( '¡Todo al día!', 'aura-suite' ); ?></h4>
                <p style="margin:0;color:#64748b;font-size:13px;"><?php esc_html_e( 'No se encontraron movimientos registrados para los filtros seleccionados.', 'aura-suite' ); ?></p>
            </div>
            <?php endif; // rows ?>
        </div>
    </div>

    <?php else : ?>
    <div class="aura-card aura-welcome-box">
        <div class="aura-card-body" style="text-align: center; padding: 48px 24px;">
            <div class="aura-welcome-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 12px 0 6px 0;">
                <?php esc_html_e( 'Selecciona un colaborador para consultar su Libro Mayor', 'aura-suite' ); ?>
            </h2>
            <p style="color: #64748b; font-size: 14px; max-width: 480px; margin: 0 auto;">
                <?php esc_html_e( 'Podrás auditar ingresos, pagos de sueldo, reembolsos devueltos, anticipos y el balance neto corriente con la organización.', 'aura-suite' ); ?>
            </p>
        </div>
    </div>
    <?php endif; // selected_user ?>

</div><!-- .aura-user-ledger-wrap -->

<!-- ===== ESTILOS DEL LIBRO MAYOR (AURA DESIGN SYSTEM) ===== -->
<style>
.aura-user-ledger-wrap {
    margin: 16px 20px 30px 2px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    color: #1e293b;
}

/* Hero Header */
.aura-user-ledger-wrap .aura-hero-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 14px;
    padding: 24px 28px;
    color: #ffffff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
    margin-bottom: 24px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.aura-user-ledger-wrap .aura-hero-content {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    min-width: 280px;
}
.aura-user-ledger-wrap .aura-hero-icon-box {
    width: 64px;
    height: 64px;
    border-radius: 14px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 8px 16px rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.2);
}
.aura-user-ledger-wrap .aura-hero-icon-box .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #ffffff;
}
.aura-user-ledger-wrap .aura-hero-titles {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.aura-user-ledger-wrap .aura-hero-subtitle-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.aura-user-ledger-wrap .aura-tag-badge {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #e2e8f0;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.aura-user-ledger-wrap .aura-tag-badge .dashicons {
    font-size: 13px;
    width: 13px;
    height: 13px;
}
.aura-user-ledger-wrap .aura-tag-secondary {
    background: rgba(59, 130, 246, 0.25);
    border-color: rgba(59, 130, 246, 0.4);
    color: #93c5fd;
}
.aura-user-ledger-wrap .aura-hero-title {
    color: #ffffff;
    font-size: 24px;
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
}
.aura-user-ledger-wrap .aura-hero-user-name {
    color: #93c5fd;
    font-weight: 700;
}
.aura-user-ledger-wrap .aura-hero-desc {
    color: #94a3b8;
    font-size: 13.5px;
    margin: 0;
    line-height: 1.4;
}
.aura-user-ledger-wrap .aura-hero-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

/* Botones Aura */
.aura-user-ledger-wrap .aura-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    height: 38px;
    box-sizing: border-box;
}
.aura-user-ledger-wrap .aura-btn-primary {
    background: #2563eb;
    color: #ffffff;
    border-color: #1d4ed8;
}
.aura-user-ledger-wrap .aura-btn-primary:hover {
    background: #1d4ed8;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.aura-user-ledger-wrap .aura-btn-excel {
    background: #107c41;
    color: #ffffff;
    border-color: #0b6233;
}
.aura-user-ledger-wrap .aura-btn-excel:hover {
    background: #0b6233;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(16, 124, 65, 0.35);
}
.aura-user-ledger-wrap .aura-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.2);
}
.aura-user-ledger-wrap .aura-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}

/* Cards & Layout */
.aura-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    overflow: hidden;
}
.aura-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
}
.aura-card-title-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.aura-card-title-group h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
}
.aura-card-body {
    padding: 20px;
}

/* Selector de Usuario */
.aura-user-select-layout {
    display: flex;
    gap: 24px;
    align-items: center;
    flex-wrap: wrap;
}
.aura-select-picker-box {
    flex: 1;
    min-width: 320px;
}
.aura-input-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.aura-input-label .dashicons {
    font-size: 15px;
    width: 15px;
    height: 15px;
    color: #3b82f6;
}
.aura-custom-select,
.aura-input {
    width: 100%;
    background: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    color: #0f172a !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    padding: 9px 14px !important;
    height: 42px !important;
    box-sizing: border-box !important;
    outline: none !important;
    transition: all 0.2s ease;
}
.aura-custom-select:focus,
.aura-input:focus {
    border-color: #3b82f6 !important;
    background: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
}

/* Tarjeta Perfil Usuario Seleccionado */
.aura-user-profile-badge {
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    flex: 0 0 auto;
    min-width: 300px;
}
.aura-user-avatar {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    border: 2px solid #ffffff;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    object-fit: cover;
}
.aura-user-profile-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.aura-user-name-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.aura-user-display-name {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
}
.aura-username-pill {
    background: #e2e8f0;
    color: #334155;
    font-size: 11.5px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
}
.aura-role-badge {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    text-transform: uppercase;
}
.aura-user-email-text {
    margin: 0;
    font-size: 12.5px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 4px;
}
.aura-user-email-text .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

/* Panel de Estadísticas */
.aura-stats-container {
    border-left: 4px solid #6366f1;
}
.aura-stats-metrics-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.aura-stat-mini-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.aura-stat-mini-box .mini-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.aura-stat-mini-box .mini-icon .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}
.aura-stat-mini-box .mini-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.aura-stat-mini-box .mini-label {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
}
.aura-stat-mini-box .mini-value {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
}

.aura-stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.aura-stats-card-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
}
.aura-stats-box-title {
    margin: 0 0 14px 0;
    font-size: 13.5px;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 6px;
}
.aura-stats-box-title .dashicons {
    color: #3b82f6;
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* Concept bars */
.aura-concept-bars-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.aura-concept-bar-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.aura-concept-bar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12.5px;
}
.aura-concept-bar-header .concept-title {
    font-weight: 600;
    color: #334155;
    display: flex;
    align-items: center;
    gap: 6px;
}
.aura-concept-bar-header .concept-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.aura-concept-bar-header .concept-amount {
    font-weight: 700;
}
.aura-concept-bar-track {
    background: #e2e8f0;
    border-radius: 6px;
    height: 10px;
    overflow: hidden;
}
.aura-concept-bar-fill {
    height: 100%;
    border-radius: 6px;
    transition: width 0.5s ease;
}

/* Monthly trend */
.aura-monthly-trend-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.aura-month-trend-row {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.aura-month-label {
    font-size: 13px;
    color: #0f172a;
}
.aura-month-values {
    display: flex;
    gap: 16px;
    align-items: center;
}
.aura-month-val-line {
    font-size: 12px;
    display: flex;
    gap: 4px;
    align-items: center;
}
.aura-month-val-line .val-tag {
    color: #64748b;
    font-size: 11px;
}

/* Filtros Grid */
.aura-filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    align-items: flex-end;
}
.aura-form-group {
    display: flex;
    flex-direction: column;
}
.aura-filter-buttons {
    display: flex;
    flex-direction: row;
    gap: 8px;
    align-items: flex-end;
}
.aura-filter-buttons .aura-btn {
    height: 42px;
}

/* Toggle switch */
.aura-toggle-group {
    justify-content: flex-end;
    padding-bottom: 6px;
}
.aura-toggle-switch {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    user-select: none;
}
.aura-toggle-switch input {
    display: none;
}
.aura-toggle-slider {
    width: 38px;
    height: 22px;
    background: #cbd5e1;
    border-radius: 20px;
    position: relative;
    transition: background 0.2s ease;
}
.aura-toggle-slider::before {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #ffffff;
    top: 3px;
    left: 3px;
    transition: transform 0.2s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.aura-toggle-switch input:checked + .aura-toggle-slider {
    background: #2563eb;
}
.aura-toggle-switch input:checked + .aura-toggle-slider::before {
    transform: translateX(16px);
}

/* Chips rápidos */
.aura-quick-date-chips {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px dashed #e2e8f0;
}
.aura-chips-label {
    font-size: 11.5px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.aura-chip-btn {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.aura-chip-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
    border-color: #94a3b8;
}

/* Grid KPIs */
.aura-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
}
.aura-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.aura-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}
.aura-stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.aura-stat-card .stat-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    color: #ffffff;
}
.aura-stat-card .stat-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
    min-width: 0;
}
.aura-stat-card .stat-label-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
}
.aura-stat-card .stat-label {
    font-size: 11.5px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.aura-stat-card .stat-value {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.1;
}
.aura-stat-card .stat-sub {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 2px;
}

/* Tabla Fluida (Cero Scroll Horizontal Rígido) */
.aura-table-responsive-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.aura-modern-table {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    table-layout: auto !important;
    box-sizing: border-box !important;
    border-collapse: collapse;
    font-size: 13px;
}
.aura-modern-table th {
    background: #1e3a5f !important;
    color: #ffffff !important;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    text-align: left;
    white-space: nowrap;
}
.aura-modern-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.aura-modern-table tr.aura-parent-row:hover td {
    background: #f8fafc;
}
.aura-row-income {
    border-left: 3.5px solid #10b981;
}
.aura-row-expense {
    border-left: 3.5px solid #ef4444;
}

/* Sistema Universal de Filas Expandibles (Child Rows) */
.aura-id-toggle-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    justify-content: center;
}

.aura-row-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #2563eb;
    font-size: 14px;
    font-weight: 800;
    line-height: 1;
    cursor: pointer;
    transition: all 0.15s ease;
    padding: 0;
}

.aura-row-toggle:hover,
.aura-row-toggle.is-active {
    background: #2563eb;
    border-color: #1d4ed8;
    color: #ffffff;
}

.aura-txn-id-pill {
    display: inline-block;
    padding: 2px 7px;
    background: #f1f5f9;
    color: #1e293b;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid #e2e8f0;
}

.aura-child-row {
    display: none;
    background: #f8fafc !important;
}

.aura-child-row td {
    padding: 0 !important;
    border-bottom: 1px solid #e2e8f0;
}

.aura-child-card {
    padding: 16px 20px 20px;
    background: #f8fafc;
    border-left: 3.5px solid #2563eb;
    animation: auraChildFadeIn 0.2s ease-out;
}

@keyframes auraChildFadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}

.aura-child-hero-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding-bottom: 14px;
    margin-bottom: 14px;
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.aura-child-hero-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 240px;
}

.aura-child-hero-title {
    margin: 0;
    font-size: 14.5px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
}

.aura-child-sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
}

.aura-child-section {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px;
    min-width: 0 !important;
}

.aura-child-section-title {
    margin: 0 0 10px 0;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 6px;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 6px;
}

.aura-child-section-title .dashicons {
    font-size: 15px;
    width: 15px;
    height: 15px;
    color: #2563eb;
}

.aura-child-meta-item {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 6px;
    gap: 8px;
}

.aura-child-meta-item:last-child {
    margin-bottom: 0;
}

.aura-child-meta-label {
    color: #64748b;
    flex-shrink: 0;
}

.aura-child-meta-val {
    font-weight: 600;
    color: #0f172a;
    text-align: right;
    word-break: break-word;
}

/* Insignias y Pills */
.aura-type-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: 14px;
    font-weight: 700;
    flex-shrink: 0;
}

.aura-type-pill.income {
    background: #d1fae5;
    color: #059669;
}

.aura-type-pill.expense {
    background: #fee2e2;
    color: #dc2626;
}

.aura-date-badge {
    font-weight: 700;
    color: #475569;
    background: #f1f5f9;
    padding: 3px 7px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
}
.aura-desc-text {
    font-weight: 600;
    color: #0f172a;
    line-height: 1.3;
}
.aura-ref-num {
    font-size: 11.5px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 3px;
    margin-top: 3px;
}
.aura-ref-num .dashicons {
    font-size: 12px;
    width: 12px;
    height: 12px;
}
.aura-concept-pill {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.aura-amt-income {
    color: #059669;
    font-weight: 800;
    font-size: 13.5px;
    white-space: nowrap;
}
.aura-amt-expense {
    color: #dc2626;
    font-weight: 800;
    font-size: 13.5px;
    white-space: nowrap;
}
.aura-amt-empty {
    color: #cbd5e1;
    font-weight: 600;
}
.aura-amt-balance {
    font-weight: 800;
    font-size: 13.5px;
    white-space: nowrap;
}
.aura-amt-balance.is-positive {
    color: #2563eb;
}
.aura-amt-balance.is-negative {
    color: #dc2626;
}

/* Badges Estado */
.aura-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
    white-space: nowrap;
}
.aura-badge-blue   { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
.aura-badge-green  { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.aura-badge-orange { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
.aura-badge-red    { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

/* Fila Totales */
.aura-ledger-totals-row td {
    background: #f8fafc !important;
    border-top: 2px solid #cbd5e1 !important;
    padding: 14px 16px !important;
    font-size: 13.5px !important;
}

/* Paginación */
.aura-ledger-pagination-bar {
    padding: 14px 20px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
}
.aura-ledger-pagination-bar .tablenav-pages {
    float: none;
    margin: 0;
}

/* Estado Vacío */
.aura-empty-state-box {
    text-align: center;
    padding: 48px 20px;
}

/* Tooltip Help Icon */
.aura-help-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    cursor: help;
    transition: color 0.15s ease;
}
.aura-help-icon:hover {
    color: #3b82f6;
}
.aura-help-icon .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

/* ── Portal Global de Tooltips Anclado al Body (Zero-Clipping) ── */
#aura-global-tooltip {
    display: none;
    position: fixed !important;
    top: -9999px;
    left: -9999px;
    z-index: 9999999999 !important;
    pointer-events: none;
    background: #0f172a;
    color: #ffffff !important;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11.5px;
    line-height: 1.4;
    font-weight: 500;
    max-width: 280px;
    text-align: center;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.15s ease, transform 0.15s ease;
    transform: translateY(2px);
}

#aura-global-tooltip.is-visible {
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: auto !important;
    transform: translateY(0);
}

#aura-global-tooltip.has-card-content,
.aura-global-tooltip.has-card-content {
    padding: 0 !important;
    max-width: 360px !important;
    min-width: 280px !important;
    border-radius: 14px !important;
    overflow: hidden !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    text-align: left !important;
}

#aura-global-tooltip.has-card-content .aura-global-tooltip-inner,
.aura-global-tooltip.has-card-content .aura-global-tooltip-inner {
    padding: 0 !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    max-width: 360px !important;
}

#aura-global-tooltip.has-card-content .aura-global-tooltip-arrow,
.aura-global-tooltip.has-card-content .aura-global-tooltip-arrow {
    display: none !important;
}

/* Tarjetas Flotantes Enriquecidas (.aura-tip-card) */
.aura-tip-card {
    display: flex;
    flex-direction: column;
    width: 100%;
    min-width: 280px;
    max-width: 360px;
    font-family: inherit;
    line-height: 1.35;
    background: #0f172a;
    color: #f1f5f9;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid #334155;
}

.aura-tip-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #1e293b;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.aura-tip-avatar-large {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: #334155;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #cbd5e1;
    font-size: 18px;
    font-weight: 700;
}

.aura-tip-avatar-large .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.aura-tip-info {
    flex: 1;
    min-width: 0;
}

.aura-tip-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #ffffff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.1px;
}

.aura-tip-subtitle {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.aura-tip-badges {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 6px;
    flex-wrap: wrap;
}

.aura-tip-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.1);
    color: #cbd5e1;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.aura-tip-badge--company { background: rgba(37, 99, 235, 0.25); color: #93c5fd; border-color: rgba(37, 99, 235, 0.4); }
.aura-tip-badge--user    { background: rgba(124, 58, 237, 0.25); color: #ddd6fe; border-color: rgba(124, 58, 237, 0.4); }
.aura-tip-badge--role    { background: rgba(16, 185, 129, 0.25); color: #a7f3d0; border-color: rgba(16, 185, 129, 0.4); }

.aura-tip-card-body {
    padding: 12px 16px 14px;
    display: flex;
    flex-direction: column;
    gap: 7px;
    font-size: 11.5px;
    background: #0f172a;
}

.aura-tip-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
    color: #94a3b8;
}

.aura-tip-row span {
    color: #94a3b8;
    flex-shrink: 0;
}

.aura-tip-row strong {
    color: #f1f5f9;
    text-align: right;
    font-weight: 600;
    word-break: break-word;
}

.aura-welcome-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #eff6ff;
    color: #2563eb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
}
.aura-welcome-icon .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
}

/* ── Responsive Column Utilities ────────────────────────────── */
@media (max-width: 992px) {
    .aura-col-tablet-hidden {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .aura-col-mobile-hidden {
        display: none !important;
    }
    .aura-stats-grid {
        grid-template-columns: 1fr !important;
    }
}

/* ── Modo Oscuro Exhaustivo (Dark Mode) ──────────────────────── */
html[data-wp-dark-mode-scheme="dark"] .aura-user-ledger-wrap,
html.wp-dark-mode-active .aura-user-ledger-wrap,
body.wp-dark-mode-active .aura-user-ledger-wrap,
body.aura-dark-mode .aura-user-ledger-wrap,
body[data-theme="dark"] .aura-user-ledger-wrap,
[data-theme="dark"] .aura-user-ledger-wrap,
body.admin-color-midnight .aura-user-ledger-wrap,
body.admin-color-coffee .aura-user-ledger-wrap {
    color: #f8fafc;
}

html[data-wp-dark-mode-scheme="dark"] .aura-card,
body.aura-dark-mode .aura-card,
body[data-theme="dark"] .aura-card,
html[data-wp-dark-mode-scheme="dark"] .aura-stat-card,
body.aura-dark-mode .aura-stat-card,
body[data-theme="dark"] .aura-stat-card,
html[data-wp-dark-mode-scheme="dark"] .aura-stat-mini-box,
body.aura-dark-mode .aura-stat-mini-box,
body[data-theme="dark"] .aura-stat-mini-box,
html[data-wp-dark-mode-scheme="dark"] .aura-stats-card-box,
body.aura-dark-mode .aura-stats-card-box,
body[data-theme="dark"] .aura-stats-card-box,
html[data-wp-dark-mode-scheme="dark"] .aura-child-section,
body.aura-dark-mode .aura-child-section,
body[data-theme="dark"] .aura-child-section {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-child-card,
body.aura-dark-mode .aura-child-card,
body[data-theme="dark"] .aura-child-card,
html[data-wp-dark-mode-scheme="dark"] .aura-child-row,
body.aura-dark-mode .aura-child-row,
body[data-theme="dark"] .aura-child-row {
    background: #0f172a !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-card-header,
body.aura-dark-mode .aura-card-header,
body[data-theme="dark"] .aura-card-header {
    background: #1e293b !important;
    border-bottom-color: #334155 !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-card-title-group h3,
body.aura-dark-mode .aura-card-title-group h3,
body[data-theme="dark"] .aura-card-title-group h3,
html[data-wp-dark-mode-scheme="dark"] .aura-stats-box-title,
body.aura-dark-mode .aura-stats-box-title,
body[data-theme="dark"] .aura-stats-box-title,
html[data-wp-dark-mode-scheme="dark"] .aura-user-display-name,
body.aura-dark-mode .aura-user-display-name,
body[data-theme="dark"] .aura-user-display-name,
html[data-wp-dark-mode-scheme="dark"] .aura-month-label,
body.aura-dark-mode .aura-month-label,
body[data-theme="dark"] .aura-month-label,
html[data-wp-dark-mode-scheme="dark"] .mini-value,
body.aura-dark-mode .mini-value,
body[data-theme="dark"] .mini-value,
html[data-wp-dark-mode-scheme="dark"] .concept-title,
body.aura-dark-mode .concept-title,
body[data-theme="dark"] .concept-title,
html[data-wp-dark-mode-scheme="dark"] .aura-desc-text,
body.aura-dark-mode .aura-desc-text,
body[data-theme="dark"] .aura-desc-text,
html[data-wp-dark-mode-scheme="dark"] .aura-child-hero-title,
body.aura-dark-mode .aura-child-hero-title,
body[data-theme="dark"] .aura-child-hero-title,
html[data-wp-dark-mode-scheme="dark"] .aura-child-meta-val,
body.aura-dark-mode .aura-child-meta-val,
body[data-theme="dark"] .aura-child-meta-val {
    color: #f8fafc !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-month-trend-row,
body.aura-dark-mode .aura-month-trend-row,
body[data-theme="dark"] .aura-month-trend-row {
    background: #0f172a !important;
    border-color: #334155 !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-user-profile-badge,
body.aura-dark-mode .aura-user-profile-badge,
body[data-theme="dark"] .aura-user-profile-badge {
    background: #0f172a !important;
    border-color: #334155 !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-custom-select,
body.aura-dark-mode .aura-custom-select,
body[data-theme="dark"] .aura-custom-select,
html[data-wp-dark-mode-scheme="dark"] .aura-input,
body.aura-dark-mode .aura-input,
body[data-theme="dark"] .aura-input {
    background: #0f172a !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
    color-scheme: dark !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-modern-table th,
body.aura-dark-mode .aura-modern-table th,
body[data-theme="dark"] .aura-modern-table th {
    background: #0f172a !important;
    color: #cbd5e1 !important;
    border-bottom-color: #334155 !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-modern-table td,
body.aura-dark-mode .aura-modern-table td,
body[data-theme="dark"] .aura-modern-table td {
    border-bottom-color: #334155 !important;
    color: #e2e8f0 !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-modern-table tr.aura-parent-row:hover td,
body.aura-dark-mode .aura-modern-table tr.aura-parent-row:hover td,
body[data-theme="dark"] .aura-modern-table tr.aura-parent-row:hover td {
    background: #0f172a !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-ledger-totals-row td,
body.aura-dark-mode .aura-ledger-totals-row td,
body[data-theme="dark"] .aura-ledger-totals-row td {
    background: #0f172a !important;
    border-top-color: #334155 !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-chip-btn,
body.aura-dark-mode .aura-chip-btn,
body[data-theme="dark"] .aura-chip-btn,
html[data-wp-dark-mode-scheme="dark"] .aura-row-toggle,
body.aura-dark-mode .aura-row-toggle,
body[data-theme="dark"] .aura-row-toggle {
    background: #0f172a !important;
    border-color: #334155 !important;
    color: #cbd5e1 !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-date-badge,
body.aura-dark-mode .aura-date-badge,
body[data-theme="dark"] .aura-date-badge,
html[data-wp-dark-mode-scheme="dark"] .aura-txn-id-pill,
body.aura-dark-mode .aura-txn-id-pill,
body[data-theme="dark"] .aura-txn-id-pill {
    background: #0f172a !important;
    color: #94a3b8 !important;
    border-color: #334155 !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-concept-bar-track,
body.aura-dark-mode .aura-concept-bar-track,
body[data-theme="dark"] .aura-concept-bar-track {
    background: #0f172a !important;
}

html[data-wp-dark-mode-scheme="dark"] .aura-toggle-text,
body.aura-dark-mode .aura-toggle-text,
body[data-theme="dark"] .aura-toggle-text {
    color: #e2e8f0 !important;
}
</style>

<!-- ===== JAVASCRIPT ===== -->
<script type="text/javascript">
jQuery( function($) {
    'use strict';

    var $select = $( '#user_select_ledger' );
    var $card   = $( '#selected-user-card' );

    // -------------------------------------------------------------------
    // 1. Portal Global de Tooltips (Gestionado por AuraUI)
    // -------------------------------------------------------------------
    if (typeof AuraUI !== 'undefined' && AuraUI.initTooltips) {
        AuraUI.initTooltips();
    }

    // -------------------------------------------------------------------
    // 2. Acordeón de Filas Expandibles (Universal Child Rows)
    // -------------------------------------------------------------------
    $(document).on('click', '.aura-row-toggle', function(e) {
        e.stopPropagation();
        var $btn = $(this);
        var $parentRow = $btn.closest('tr.aura-parent-row');
        var rowId = $parentRow.data('row-id');
        var $childRow = $('tr.aura-child-row[data-child-for="' + rowId + '"]');

        if ($childRow.is(':visible')) {
            $childRow.hide();
            $btn.text('+').attr('aria-expanded', 'false').removeClass('is-active');
            $parentRow.removeClass('is-expanded');
        } else {
            $childRow.show();
            $btn.text('−').attr('aria-expanded', 'true').addClass('is-active');
            $parentRow.addClass('is-expanded');
        }
    });

    // -------------------------------------------------------------------
    // 3. Cambio de usuario → Redirección limpia
    // -------------------------------------------------------------------
    $select.on( 'change', function() {
        var userId = $(this).val();
        if ( userId ) {
            var url = '<?php echo esc_js( admin_url( 'admin.php?page=aura-user-ledger' ) ); ?>' + '&ledger_user_id=' + userId;
            window.location.href = url;
        } else {
            $card.hide();
        }
    });

    // -------------------------------------------------------------------
    // 4. Botón Toggle Panel de Estadísticas
    // -------------------------------------------------------------------
    $('#aura-toggle-stats-btn').on('click', function(e) {
        e.preventDefault();
        var $panel = $('#aura-ledger-stats-panel');
        $panel.slideToggle(250, function() {
            if ($panel.is(':visible')) {
                $('html, body').animate({
                    scrollTop: $panel.offset().top - 40
                }, 300);
            }
        });
    });

    // -------------------------------------------------------------------
    // 5. Atajos de fecha (Chips rápidos)
    // -------------------------------------------------------------------
    $('.aura-chip-btn').on('click', function() {
        var range = $(this).data('range');
        var now   = new Date();
        var fromDate, toDate;

        function toISO(d) {
            var month = '' + (d.getMonth() + 1),
                day   = '' + d.getDate(),
                year  = d.getFullYear();
            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;
            return [year, month, day].join('-');
        }

        if (range === 'this-month') {
            fromDate = new Date(now.getFullYear(), now.getMonth(), 1);
            toDate   = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        } else if (range === 'last-month') {
            fromDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            toDate   = new Date(now.getFullYear(), now.getMonth(), 0);
        } else if (range === 'this-quarter') {
            var quarterMonth = Math.floor(now.getMonth() / 3) * 3;
            fromDate = new Date(now.getFullYear(), quarterMonth, 1);
            toDate   = new Date(now.getFullYear(), quarterMonth + 3, 0);
        } else if (range === 'this-year') {
            fromDate = new Date(now.getFullYear(), 0, 1);
            toDate   = new Date(now.getFullYear(), 11, 31);
        } else if (range === 'all') {
            $('#lf_date_from').val('');
            $('#lf_date_to').val('');
            return;
        }

        if (fromDate && toDate) {
            $('#lf_date_from').val(toISO(fromDate));
            $('#lf_date_to').val(toISO(toDate));
        }
    });

} );
</script>
