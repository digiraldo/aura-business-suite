<?php
/**
 * Página de Notificaciones — Fase 5, Item 5.4
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$uid    = get_current_user_id();
$nonce  = wp_create_nonce( Aura_Financial_Notifications::NONCE );
$prefs  = get_user_meta( $uid, Aura_Financial_Notifications::PREFS_META_KEY, true );
if ( ! is_array( $prefs ) ) $prefs = [];

$pref = fn( $key, $default = true ) => isset( $prefs[ $key ] ) ? (bool) $prefs[ $key ] : (bool) $default;
$freq = $prefs['email_frequency'] ?? 'immediate';

$types  = Aura_Financial_Notifications::get_types();
$unread = Aura_Financial_Notifications::get_unread_count( $uid );
// Conteo total para KPIs
global $wpdb;
$total_count = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM `{$wpdb->prefix}" . Aura_Financial_Notifications::TABLE . "` WHERE user_id = %d",
    $uid
) );
$read_count = max( 0, $total_count - $unread );
?>
<div class="aura-app-wrapper">
<div class="wrap aura-app-context aura-notif-wrap">
    <div class="aura-layout">

        <!-- =====================================================
             CABECERA DE LA PÁGINA (HERO CANÓNICO)
             ===================================================== -->
        <header class="aura-page-header hero-card aura-glass-card fade-up" style="margin-bottom: 24px;">
            <div class="aura-header-left">
                <div class="aura-header-icon-box" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff;">
                    <span class="dashicons dashicons-bell"></span>
                </div>
                <div class="aura-header-titles">
                    <div class="aura-header-title-row" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <h1 class="wp-heading-inline aura-page-title" style="margin: 0; font-size: 1.55rem; font-weight: 800; color: var(--tx-primary);">
                            <?php esc_html_e( 'Centro de Notificaciones', 'aura-suite' ); ?>
                        </h1>
                        <?php if ( $unread > 0 ) : ?>
                            <span class="badge badge-rose badge-pill aura-notif-badge-title" data-tooltip="<?php esc_attr_e( 'Notificaciones pendientes de lectura', 'aura-suite' ); ?>">
                                <span class="pulse-dot dot-danger"></span>
                                <span><?php printf( esc_html__( '%d sin leer', 'aura-suite' ), $unread ); ?></span>
                            </span>
                        <?php else : ?>
                            <span class="badge badge-emerald badge-pill aura-notif-badge-title" data-tooltip="<?php esc_attr_e( 'No tienes alertas pendientes', 'aura-suite' ); ?>">
                                <span class="pulse-dot dot-online"></span>
                                <span><?php esc_html_e( 'Al día', 'aura-suite' ); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="aura-page-description" style="margin: 4px 0 0; color: var(--tx-muted); font-size: 0.9rem;">
                        <?php esc_html_e( 'Gestión de alertas del sistema, resoluciones financieras y preferencias de entrega en tiempo real.', 'aura-suite' ); ?>
                    </p>
                </div>
            </div>
            <div class="aura-header-right">
                <a href="<?php echo esc_url( admin_url('admin.php?page=aura-suite') ); ?>" class="btn btn-secondary btn-lift">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <span><?php esc_html_e( 'Volver al Dashboard', 'aura-suite' ); ?></span>
                </a>
            </div>
        </header>

        <!-- =====================================================
             TARJETAS KPI DE RESUMEN (DESIGN SYSTEM)
             ===================================================== -->
        <div class="aura-kpi-grid fade-up delay-1" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <!-- KPI 1: Total -->
            <div class="kpi-card kpi-blue card-lift" data-tooltip="<?php esc_attr_e( 'Historial total de notificaciones registradas para tu usuario', 'aura-suite' ); ?>">
                <div class="kpi-header">
                    <span class="kpi-title"><?php esc_html_e( 'Total Notificaciones', 'aura-suite' ); ?></span>
                    <span class="kpi-icon"><span class="dashicons dashicons-email"></span></span>
                </div>
                <div class="kpi-value"><?php echo esc_html( $total_count ); ?></div>
                <div class="kpi-footer">
                    <span class="badge badge-blue"><?php printf( esc_html__( '%d leídas', 'aura-suite' ), $read_count ); ?></span>
                </div>
            </div>

            <!-- KPI 2: Sin Leer -->
            <div class="kpi-card <?php echo $unread > 0 ? 'kpi-rose' : 'kpi-emerald'; ?> card-lift" data-tooltip="<?php esc_attr_e( 'Notificaciones y avisos que aún no has marcado como revisados', 'aura-suite' ); ?>">
                <div class="kpi-header">
                    <span class="kpi-title"><?php esc_html_e( 'Pendientes de Lectura', 'aura-suite' ); ?></span>
                    <span class="kpi-icon"><span class="dashicons dashicons-warning"></span></span>
                </div>
                <div class="kpi-value" id="kpi-unread-val"><?php echo esc_html( $unread ); ?></div>
                <div class="kpi-footer">
                    <?php if ( $unread > 0 ) : ?>
                        <span class="live-chip live-danger"><span class="pulse-dot dot-danger"></span> <?php esc_html_e( 'Requieren atención', 'aura-suite' ); ?></span>
                    <?php else : ?>
                        <span class="live-chip live-online"><span class="pulse-dot dot-online"></span> <?php esc_html_e( 'Bandeja limpia', 'aura-suite' ); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPI 3: Frecuencia Email -->
            <div class="kpi-card kpi-violet card-lift" data-tooltip="<?php esc_attr_e( 'Ritmo de despacho configurado para tus notificaciones por correo', 'aura-suite' ); ?>">
                <div class="kpi-header">
                    <span class="kpi-title"><?php esc_html_e( 'Canal de Email', 'aura-suite' ); ?></span>
                    <span class="kpi-icon"><span class="dashicons dashicons-cloud"></span></span>
                </div>
                <div class="kpi-value" style="font-size: 1.35rem; text-transform: capitalize;">
                    <?php
                    $freq_labels = [
                        'immediate' => __( 'Tiempo Real', 'aura-suite' ),
                        'daily'     => __( 'Resumen Diario', 'aura-suite' ),
                        'weekly'    => __( 'Resumen Semanal', 'aura-suite' ),
                    ];
                    echo esc_html( $freq_labels[ $freq ] ?? $freq );
                    ?>
                </div>
                <div class="kpi-footer">
                    <span class="badge badge-violet"><?php esc_html_e( 'Preferencias activas', 'aura-suite' ); ?></span>
                </div>
            </div>
        </div>

        <div class="aura-notif-layout">

            <!-- =====================================================
                 Panel principal de notificaciones
                 ===================================================== -->
            <section class="aura-notif-main">

                <!-- Toolbar de filtros y acciones -->
                <div class="glass-card aura-notif-toolbar">
                    <div class="aura-notif-filters">
                        <select id="f-notif-type" class="aura-input select-fancy" data-tooltip="<?php esc_attr_e( 'Filtrar por categoría de notificación', 'aura-suite' ); ?>">
                            <option value=""><?php esc_html_e( '— Todos los tipos —', 'aura-suite' ); ?></option>
                            <?php foreach ( $types as $k => $label ) : ?>
                            <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="f-notif-read" class="aura-input select-fancy" data-tooltip="<?php esc_attr_e( 'Filtrar por estado de lectura', 'aura-suite' ); ?>">
                            <option value=""><?php esc_html_e( 'Todas las alertas', 'aura-suite' ); ?></option>
                            <option value="0"><?php esc_html_e( 'No leídas', 'aura-suite' ); ?></option>
                            <option value="1"><?php esc_html_e( 'Leídas', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                    <div class="aura-notif-actions">
                        <button type="button" id="btn-mark-all-read" class="btn btn-secondary btn-lift" data-tooltip="<?php esc_attr_e( 'Marcar todas las notificaciones como leídas', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <span><?php esc_html_e( 'Marcar todo como leído', 'aura-suite' ); ?></span>
                        </button>
                        <button type="button" id="btn-delete-selected" class="btn btn-secondary btn-lift" disabled data-tooltip="<?php esc_attr_e( 'Eliminar las notificaciones que hayas seleccionado', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <span><?php esc_html_e( 'Borrar seleccionadas', 'aura-suite' ); ?></span>
                        </button>
                        <button type="button" id="btn-delete-all" class="btn btn-rose btn-shimmer btn-lift" data-tooltip="<?php esc_attr_e( 'Eliminar todo el historial de notificaciones', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-dismiss"></span>
                            <span><?php esc_html_e( 'Borrar todas', 'aura-suite' ); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Loading state -->
                <div id="notif-loading" class="glass-card" style="text-align:center;padding:48px;display:none;">
                    <div class="loading-bar loading-bar-thick" style="max-width:240px;margin:0 auto 16px;"></div>
                    <p style="margin:0;color:var(--tx-muted);font-size:0.95rem;font-weight:600;">
                        <?php esc_html_e( 'Cargando notificaciones...', 'aura-suite' ); ?>
                    </p>
                </div>

                <!-- Empty state -->
                <div id="notif-empty" class="empty-card" style="text-align:center;padding:60px 24px;display:none;">
                    <span class="empty-icon" style="font-size:48px;line-height:1;display:block;margin-bottom:12px;">🔔</span>
                    <h3 style="margin:0 0 6px;color:var(--tx-primary);font-weight:700;font-size:1.2rem;">
                        <?php esc_html_e( 'No tienes notificaciones', 'aura-suite' ); ?>
                    </h3>
                    <p style="margin:0;color:var(--tx-muted);font-size:0.9rem;">
                        <?php esc_html_e( 'Cuando ocurran eventos o cambios en el sistema, aparecerán aquí.', 'aura-suite' ); ?>
                    </p>
                </div>

                <!-- Lista -->
                <ul id="notif-list" class="aura-notif-list" style="display:none;"></ul>

                <!-- Paginación -->
                <div id="notif-pagination" style="display:none;margin-top:16px;text-align:center;"></div>

            </section><!-- /.aura-notif-main -->

            <!-- =====================================================
                 Preferencias de notificaciones (Sidebar)
                 ===================================================== -->
            <aside class="aura-notif-sidebar">

                <div class="glass-card aura-notif-card">
                    <h3 class="aura-notif-card__title">
                        <span class="dashicons dashicons-email-alt" style="color:var(--aura-indigo, #4f46e5);"></span>
                        <span><?php esc_html_e( 'Preferencias de Email', 'aura-suite' ); ?></span>
                    </h3>
                    <form id="form-notif-prefs">

                        <table class="aura-notif-prefs-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Tipo de Alerta', 'aura-suite' ); ?></th>
                                    <th style="text-align:center;width:60px;"><?php esc_html_e( 'Email', 'aura-suite' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong><?php esc_html_e( 'Transacciones pendientes', 'aura-suite' ); ?></strong>
                                        <small class="aura-pref-desc"><?php esc_html_e( 'Avisos para aprobar o revisar', 'aura-suite' ); ?></small>
                                    </td>
                                    <td style="text-align:center;">
                                        <label class="aura-toggle" data-tooltip="<?php esc_attr_e( 'Activar/desactivar emails de aprobaciones pendientes', 'aura-suite' ); ?>">
                                            <input type="checkbox" name="email_transaction_approval"
                                                   <?php checked( $pref( 'email_transaction_approval' ) ); ?>>
                                            <span class="aura-toggle-slider"></span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong><?php esc_html_e( 'Aprobaciones / Rechazos', 'aura-suite' ); ?></strong>
                                        <small class="aura-pref-desc"><?php esc_html_e( 'Resultados de transacciones creadas', 'aura-suite' ); ?></small>
                                    </td>
                                    <td style="text-align:center;">
                                        <label class="aura-toggle" data-tooltip="<?php esc_attr_e( 'Activar/desactivar emails de resoluciones de transacciones', 'aura-suite' ); ?>">
                                            <input type="checkbox" name="email_transaction_result"
                                                   <?php checked( $pref( 'email_transaction_result' ) ); ?>>
                                            <span class="aura-toggle-slider"></span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong><?php esc_html_e( 'Alertas de presupuesto', 'aura-suite' ); ?></strong>
                                        <small class="aura-pref-desc"><?php esc_html_e( 'Umbrales del 80% y 100% sobrepasado', 'aura-suite' ); ?></small>
                                    </td>
                                    <td style="text-align:center;">
                                        <label class="aura-toggle" data-tooltip="<?php esc_attr_e( 'Activar/desactivar alertas de presupuestos por email', 'aura-suite' ); ?>">
                                            <input type="checkbox" name="email_budget_alert"
                                                   <?php checked( $pref( 'email_budget_alert' ) ); ?>>
                                            <span class="aura-toggle-slider"></span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong><?php esc_html_e( 'Recordatorios periódicos', 'aura-suite' ); ?></strong>
                                        <small class="aura-pref-desc"><?php esc_html_e( 'Comprobantes faltantes y pendientes', 'aura-suite' ); ?></small>
                                    </td>
                                    <td style="text-align:center;">
                                        <label class="aura-toggle" data-tooltip="<?php esc_attr_e( 'Activar/desactivar recordatorios por email', 'aura-suite' ); ?>">
                                            <input type="checkbox" name="email_reminders"
                                                   <?php checked( $pref( 'email_reminders', false ) ); ?>>
                                            <span class="aura-toggle-slider"></span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong><?php esc_html_e( 'Eventos del Sistema', 'aura-suite' ); ?></strong>
                                        <small class="aura-pref-desc"><?php esc_html_e( 'Importaciones, backups y ajustes', 'aura-suite' ); ?></small>
                                    </td>
                                    <td style="text-align:center;">
                                        <label class="aura-toggle" data-tooltip="<?php esc_attr_e( 'Activar/desactivar avisos del sistema por email', 'aura-suite' ); ?>">
                                            <input type="checkbox" name="email_system"
                                                   <?php checked( $pref( 'email_system' ) ); ?>>
                                            <span class="aura-toggle-slider"></span>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="aura-notif-pref-section">
                            <strong class="aura-notif-pref-title">⏱️ <?php esc_html_e( 'Frecuencia de emails', 'aura-suite' ); ?></strong>
                            <div class="aura-radio-group">
                                <label class="aura-radio-label">
                                    <input type="radio" name="email_frequency" value="immediate" <?php checked( $freq, 'immediate' ); ?>>
                                    <span><?php esc_html_e( 'Inmediato (en tiempo real)', 'aura-suite' ); ?></span>
                                </label>
                                <label class="aura-radio-label">
                                    <input type="radio" name="email_frequency" value="daily" <?php checked( $freq, 'daily' ); ?>>
                                    <span><?php esc_html_e( 'Resumen diario (9:00 AM)', 'aura-suite' ); ?></span>
                                </label>
                                <label class="aura-radio-label">
                                    <input type="radio" name="email_frequency" value="weekly" <?php checked( $freq, 'weekly' ); ?>>
                                    <span><?php esc_html_e( 'Resumen semanal (Lunes)', 'aura-suite' ); ?></span>
                                </label>
                            </div>
                        </div>

                        <div class="aura-notif-pref-section">
                            <strong class="aura-notif-pref-title">🌙 <?php esc_html_e( 'Modo No Molestar', 'aura-suite' ); ?></strong>
                            <div class="aura-checkbox-group">
                                <label class="aura-checkbox-label">
                                    <input type="checkbox" name="no_disturb_weekend"
                                           <?php checked( $pref( 'no_disturb_weekend', false ) ); ?>>
                                    <span><?php esc_html_e( 'Silenciar fines de semana', 'aura-suite' ); ?></span>
                                </label>
                                <label class="aura-checkbox-label">
                                    <input type="checkbox" name="no_disturb_hours"
                                           <?php checked( $pref( 'no_disturb_hours', false ) ); ?>>
                                    <span><?php esc_html_e( 'Silenciar fuera de horario (6pm – 8am)', 'aura-suite' ); ?></span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-shimmer btn-lift" style="width:100%;margin-top:16px;min-height:42px;font-weight:700;">
                            <span class="dashicons dashicons-saved"></span>
                            <span><?php esc_html_e( 'Guardar Preferencias', 'aura-suite' ); ?></span>
                        </button>
                        <div id="notif-prefs-msg" style="display:none;margin-top:10px;text-align:center;"></div>
                    </form>
                </div><!-- /.aura-notif-card -->

            </aside><!-- /.aura-notif-sidebar -->

        </div><!-- /.aura-notif-layout -->

        <!-- Footer Global Canónico Aura -->
        <div class="adp-footer">
            <p>
                Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.216.694.825.576C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>DiGiraldo</a></strong> &nbsp;|&nbsp; © <?php echo date('Y'); ?> AURA Business Suite
            </p>
        </div>

    </div><!-- /.aura-layout -->
</div><!-- /.wrap.aura-app-context -->
</div><!-- /.aura-app-wrapper -->

<script>
var auraNotifConfig = {
    nonce:   '<?php echo esc_js( $nonce ); ?>',
    ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
    i18n: {
        unreadBadge:    '<?php echo esc_js( __( 'no leídas', 'aura-suite' ) ); ?>',
        markRead:       '<?php echo esc_js( __( 'Marcar como leída', 'aura-suite' ) ); ?>',
        markUnread:     '<?php echo esc_js( __( 'Marcar como no leída', 'aura-suite' ) ); ?>',
        delete:         '<?php echo esc_js( __( 'Eliminar', 'aura-suite' ) ); ?>',
        view:           '<?php echo esc_js( __( 'Ver', 'aura-suite' ) ); ?>',
        confirmDelete:  '<?php echo esc_js( __( '¿Eliminar esta notificación?', 'aura-suite' ) ); ?>',
        confirmDeleteSelected: '<?php echo esc_js( __( '¿Eliminar las notificaciones seleccionadas?', 'aura-suite' ) ); ?>',
        confirmDeleteAll: '<?php echo esc_js( __( '¿Eliminar TODAS tus notificaciones?', 'aura-suite' ) ); ?>',
        prefsSaved:     '<?php echo esc_js( __( 'Preferencias guardadas correctamente.', 'aura-suite' ) ); ?>',
        page:           '<?php echo esc_js( __( 'Pág.', 'aura-suite' ) ); ?>',
        of:             '<?php echo esc_js( __( 'de', 'aura-suite' ) ); ?>',
    },
    typeColors: {
        transaction_pending:  '#2271b1',
        transaction_approved: '#1e7e34',
        transaction_rejected: '#c00',
        transaction_edited:   '#f57f17',
        budget_warning:       '#e65100',
        budget_exceeded:      '#c00',
        budget_assigned:      '#1e7e34',
        reminder_pending:     '#6a1b9a',
        reminder_no_receipt:  '#6a1b9a',
        reminder_rejected:    '#c00',
        import_complete:      '#4527a0',
        export_ready:         '#0277bd',
        settings_updated:     '#546e7a',
    },
};
</script>
