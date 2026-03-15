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
?>
<div class="wrap aura-notif-wrap">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-bell" style="margin-right:6px;"></span>
        <?php esc_html_e( 'Notificaciones', 'aura-suite' ); ?>
        <?php if ( $unread ) : ?>
            <span class="aura-notif-badge-title"><?php echo esc_html( $unread ); ?></span>
        <?php endif; ?>
    </h1>
    <p class="description"><?php esc_html_e( 'Centro de notificaciones del módulo financiero.', 'aura-suite' ); ?></p>
    <hr class="wp-header-end">

    <div class="aura-notif-layout">

        <!-- =====================================================
             Panel principal de notificaciones
             ===================================================== -->
        <section class="aura-notif-main">

            <!-- Acciones rápidas -->
            <div class="aura-notif-toolbar">
                <div class="aura-notif-filters">
                    <select id="f-notif-type">
                        <option value=""><?php esc_html_e( '— Todos los tipos —', 'aura-suite' ); ?></option>
                        <?php foreach ( $types as $k => $label ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="f-notif-read">
                        <option value=""><?php esc_html_e( 'Todas', 'aura-suite' ); ?></option>
                        <option value="0"><?php esc_html_e( 'No leídas', 'aura-suite' ); ?></option>
                        <option value="1"><?php esc_html_e( 'Leídas', 'aura-suite' ); ?></option>
                    </select>
                </div>
                <div class="aura-notif-actions">
                    <button id="btn-mark-all-read" class="button">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e( 'Marcar todo como leído', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

            <!-- Loading -->
            <div id="notif-loading" style="text-align:center;padding:40px;display:none;">
                <span class="spinner is-active" style="float:none;width:40px;height:40px;"></span>
            </div>

            <!-- Empty -->
            <div id="notif-empty" style="text-align:center;padding:60px 20px;display:none;">
                <span class="dashicons dashicons-bell" style="font-size:48px;color:#ccc;"></span>
                <p style="color:#888;"><?php esc_html_e( 'Sin notificaciones.', 'aura-suite' ); ?></p>
            </div>

            <!-- Lista -->
            <ul id="notif-list" class="aura-notif-list" style="display:none;"></ul>

            <!-- Paginación -->
            <div id="notif-pagination" style="display:none;margin-top:16px;text-align:center;"></div>

        </section><!-- /.aura-notif-main -->

        <!-- =====================================================
             Preferencias de notificaciones
             ===================================================== -->
        <aside class="aura-notif-sidebar">

            <div class="aura-notif-card">
                <h3><?php esc_html_e( 'Preferencias de Email', 'aura-suite' ); ?></h3>
                <form id="form-notif-prefs">

                    <table class="aura-notif-prefs-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Tipo', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Email', 'aura-suite' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php esc_html_e( 'Transacciones pendientes', 'aura-suite' ); ?></td>
                                <td>
                                    <label class="aura-toggle">
                                        <input type="checkbox" name="email_transaction_approval"
                                               <?php checked( $pref( 'email_transaction_approval' ) ); ?>>
                                        <span class="aura-toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Aprobaciones / Rechazos', 'aura-suite' ); ?></td>
                                <td>
                                    <label class="aura-toggle">
                                        <input type="checkbox" name="email_transaction_result"
                                               <?php checked( $pref( 'email_transaction_result' ) ); ?>>
                                        <span class="aura-toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Alertas de presupuesto', 'aura-suite' ); ?></td>
                                <td>
                                    <label class="aura-toggle">
                                        <input type="checkbox" name="email_budget_alert"
                                               <?php checked( $pref( 'email_budget_alert' ) ); ?>>
                                        <span class="aura-toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Recordatorios', 'aura-suite' ); ?></td>
                                <td>
                                    <label class="aura-toggle">
                                        <input type="checkbox" name="email_reminders"
                                               <?php checked( $pref( 'email_reminders', false ) ); ?>>
                                        <span class="aura-toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Sistema (importaciones, etc.)', 'aura-suite' ); ?></td>
                                <td>
                                    <label class="aura-toggle">
                                        <input type="checkbox" name="email_system"
                                               <?php checked( $pref( 'email_system' ) ); ?>>
                                        <span class="aura-toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="aura-notif-pref-section">
                        <strong><?php esc_html_e( 'Frecuencia de emails', 'aura-suite' ); ?></strong>
                        <label class="aura-radio-label">
                            <input type="radio" name="email_frequency" value="immediate" <?php checked( $freq, 'immediate' ); ?>>
                            <?php esc_html_e( 'Inmediato', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-radio-label">
                            <input type="radio" name="email_frequency" value="daily" <?php checked( $freq, 'daily' ); ?>>
                            <?php esc_html_e( 'Resumen diario', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-radio-label">
                            <input type="radio" name="email_frequency" value="weekly" <?php checked( $freq, 'weekly' ); ?>>
                            <?php esc_html_e( 'Resumen semanal', 'aura-suite' ); ?>
                        </label>
                    </div>

                    <div class="aura-notif-pref-section">
                        <strong><?php esc_html_e( 'No molestar', 'aura-suite' ); ?></strong>
                        <label class="aura-checkbox-label">
                            <input type="checkbox" name="no_disturb_weekend"
                                   <?php checked( $pref( 'no_disturb_weekend', false ) ); ?>>
                            <?php esc_html_e( 'Fines de semana', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-checkbox-label">
                            <input type="checkbox" name="no_disturb_hours"
                                   <?php checked( $pref( 'no_disturb_hours', false ) ); ?>>
                            <?php esc_html_e( 'Fuera de horario (6pm – 8am)', 'aura-suite' ); ?>
                        </label>
                    </div>

                    <button type="submit" class="button button-primary" style="width:100%;margin-top:8px;">
                        <?php esc_html_e( 'Guardar preferencias', 'aura-suite' ); ?>
                    </button>
                    <div id="notif-prefs-msg" style="display:none;margin-top:8px;"></div>
                </form>
            </div><!-- /.aura-notif-card -->

        </aside><!-- /.aura-notif-sidebar -->

    </div><!-- /.aura-notif-layout -->

</div><!-- /.wrap -->

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
