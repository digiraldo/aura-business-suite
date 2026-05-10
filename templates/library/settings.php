<?php
/**
 * Template: Configuración del Módulo de Biblioteca
 *
 * 6 secciones: Préstamos / Multas / Reservas / Notificaciones / Integraciones / Mantenimiento
 * Formulario guardado vía AJAX.
 *
 * @package AuraBusinessSuite
 * @subpackage Library
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$nonce = wp_create_nonce( 'aura_library_nonce' );
$cfg   = class_exists( 'Aura_Library_Settings' ) ? Aura_Library_Settings::get_all() : [];

// Utilidad para obtener valor con fallback
$g = static function( string $key, $default = '' ) use ( $cfg ) {
    return $cfg[ $key ] ?? $default;
};
?>
<div class="wrap aura-lib-wrap">

    <h1 style="color:#8b5cf6;">
        <span class="dashicons dashicons-admin-settings" style="vertical-align:middle;"></span>
        <?php esc_html_e( 'Configuración — Biblioteca', 'aura-business-suite' ); ?>
    </h1>
    <hr class="wp-header-end">

    <div id="settings-notice" style="display:none;" class="notice notice-success is-dismissible">
        <p id="settings-notice-msg"></p>
    </div>

    <!-- ── Tabs ─────────────────────────────────────────────── -->
    <div class="aura-lib-settings-tabs" style="display:flex;gap:4px;border-bottom:2px solid #e9d5ff;margin-bottom:24px;flex-wrap:wrap;">
        <?php
        $tabs = [
            'loans'         => '📋 ' . __( 'Préstamos', 'aura-business-suite' ),
            'fines'         => '💸 ' . __( 'Multas', 'aura-business-suite' ),
            'reservations'  => '🔖 ' . __( 'Reservas', 'aura-business-suite' ),
            'notifications' => '🔔 ' . __( 'Notificaciones', 'aura-business-suite' ),
            'integrations'  => '🔗 ' . __( 'Integraciones', 'aura-business-suite' ),
            'maintenance'   => '🔧 ' . __( 'Mantenimiento', 'aura-business-suite' ),
        ];
        foreach ( $tabs as $tabId => $tabLabel ) :
        ?>
            <button type="button" class="aura-lib-stab button<?php echo $tabId === 'loans' ? ' button-primary' : ''; ?>"
                    data-tab="<?php echo esc_attr( $tabId ); ?>"
                    style="border-radius:4px 4px 0 0;border-bottom:none;<?php echo $tabId === 'loans' ? 'background:#8b5cf6;border-color:#7c3aed;color:#fff;' : ''; ?>">
                <?php echo esc_html( $tabLabel ); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <form id="form-library-settings" novalidate>
        <input type="hidden" id="settings-nonce" value="<?php echo esc_attr( $nonce ); ?>">

        <!-- ── SECCIÓN 1: PRÉSTAMOS ────────────────────────── -->
        <div class="aura-lib-spanel" id="stab-loans">
            <div class="aura-lib-settings-grid">

                <div class="aura-lib-sfield">
                    <label><?php esc_html_e( 'Días de préstamo por defecto', 'aura-business-suite' ); ?></label>
                    <input type="number" name="loan_days" min="1" max="365"
                           value="<?php echo esc_attr( $g( 'loan_days', 14 ) ); ?>">
                    <p class="description"><?php esc_html_e( 'Días que el lector tiene para devolver el libro.', 'aura-business-suite' ); ?></p>
                </div>

                <div class="aura-lib-sfield">
                    <label><?php esc_html_e( 'Días de extensión por solicitud', 'aura-business-suite' ); ?></label>
                    <input type="number" name="extension_days" min="1" max="90"
                           value="<?php echo esc_attr( $g( 'extension_days', 7 ) ); ?>">
                    <p class="description"><?php esc_html_e( 'Días que se añaden al plazo cuando se solicita extensión.', 'aura-business-suite' ); ?></p>
                </div>

                <div class="aura-lib-sfield">
                    <label><?php esc_html_e( 'Extensiones máximas por préstamo', 'aura-business-suite' ); ?></label>
                    <input type="number" name="max_extensions" min="0" max="10"
                           value="<?php echo esc_attr( $g( 'max_extensions', 2 ) ); ?>">
                    <p class="description"><?php esc_html_e( 'Cuántas veces se puede extender un mismo préstamo.', 'aura-business-suite' ); ?></p>
                </div>

            </div>
            <button type="button" class="button button-primary aura-lib-save-btn" data-section="loans"
                    style="background:#8b5cf6;border-color:#7c3aed;margin-top:16px;">
                💾 <?php esc_html_e( 'Guardar Préstamos', 'aura-business-suite' ); ?>
            </button>
        </div>

        <!-- ── SECCIÓN 2: MULTAS ───────────────────────────── -->
        <div class="aura-lib-spanel" id="stab-fines" style="display:none;">
            <div class="aura-lib-settings-grid">

                <div class="aura-lib-sfield aura-lib-sfield--toggle">
                    <label class="aura-lib-toggle-label">
                        <input type="checkbox" name="fines_enabled" value="1"
                               <?php checked( $g( 'fines_enabled', false ) ); ?>>
                        <span><?php esc_html_e( 'Activar sistema de multas', 'aura-business-suite' ); ?></span>
                    </label>
                    <p class="description"><?php esc_html_e( 'Si se desactiva, no se calculará ni cobrará multa por devoluciones tardías.', 'aura-business-suite' ); ?></p>
                </div>

                <div class="aura-lib-sfield">
                    <label><?php esc_html_e( 'Tarifa diaria de multa', 'aura-business-suite' ); ?></label>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span>$</span>
                        <input type="number" name="fine_per_day" min="0" step="0.01"
                               value="<?php echo esc_attr( $g( 'fine_per_day', '0.00' ) ); ?>" style="max-width:120px;">
                    </div>
                    <p class="description"><?php esc_html_e( 'Monto cobrado por cada día de retraso.', 'aura-business-suite' ); ?></p>
                </div>

                <div class="aura-lib-sfield">
                    <label><?php esc_html_e( 'Período de gracia (días)', 'aura-business-suite' ); ?></label>
                    <input type="number" name="grace_days" min="0" max="30"
                           value="<?php echo esc_attr( $g( 'grace_days', 1 ) ); ?>" style="max-width:100px;">
                    <p class="description"><?php esc_html_e( 'Días después de la fecha de vencimiento antes de aplicar multa.', 'aura-business-suite' ); ?></p>
                </div>

                <div class="aura-lib-sfield">
                    <label><?php esc_html_e( 'Multa máxima por préstamo', 'aura-business-suite' ); ?></label>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span>$</span>
                        <input type="number" name="fine_max" min="0" step="0.01"
                               value="<?php echo esc_attr( $g( 'fine_max', '0.00' ) ); ?>" style="max-width:120px;">
                    </div>
                    <p class="description"><?php esc_html_e( 'Tope máximo por préstamo. 0 = sin límite.', 'aura-business-suite' ); ?></p>
                </div>

            </div>
            <button type="button" class="button button-primary aura-lib-save-btn" data-section="fines"
                    style="background:#8b5cf6;border-color:#7c3aed;margin-top:16px;">
                💾 <?php esc_html_e( 'Guardar Multas', 'aura-business-suite' ); ?>
            </button>
        </div>

        <!-- ── SECCIÓN 3: RESERVAS ────────────────────────── -->
        <div class="aura-lib-spanel" id="stab-reservations" style="display:none;">
            <div class="aura-lib-settings-grid">

                <div class="aura-lib-sfield">
                    <label><?php esc_html_e( 'Días para expirar una reserva', 'aura-business-suite' ); ?></label>
                    <input type="number" name="reservation_expire_days" min="1" max="30"
                           value="<?php echo esc_attr( $g( 'reservation_expire_days', 2 ) ); ?>" style="max-width:100px;">
                    <p class="description"><?php esc_html_e( 'Si el lector no recoge el libro en estos días, la reserva se cancela automáticamente.', 'aura-business-suite' ); ?></p>
                </div>

            </div>
            <button type="button" class="button button-primary aura-lib-save-btn" data-section="reservations"
                    style="background:#8b5cf6;border-color:#7c3aed;margin-top:16px;">
                💾 <?php esc_html_e( 'Guardar Reservas', 'aura-business-suite' ); ?>
            </button>
        </div>

        <!-- ── SECCIÓN 4: NOTIFICACIONES ─────────────────── -->
        <div class="aura-lib-spanel" id="stab-notifications" style="display:none;">
            <div class="aura-lib-settings-grid">

                <div class="aura-lib-sfield aura-lib-sfield--toggle">
                    <label class="aura-lib-toggle-label">
                        <input type="checkbox" name="email_alerts" value="1"
                               <?php checked( $g( 'email_alerts', true ) ); ?>>
                        <span><?php esc_html_e( 'Activar alertas por email', 'aura-business-suite' ); ?></span>
                    </label>
                    <p class="description"><?php esc_html_e( 'Envía alertas de vencimiento y recordatorios por correo electrónico.', 'aura-business-suite' ); ?></p>
                </div>

                <div class="aura-lib-sfield">
                    <label><?php esc_html_e( 'Email extra del bibliotecario', 'aura-business-suite' ); ?></label>
                    <input type="email" name="email_extra" placeholder="bibliotecario@ejemplo.com"
                           value="<?php echo esc_attr( $g( 'email_extra', '' ) ); ?>">
                    <p class="description"><?php esc_html_e( 'Recibirá copia de todas las alertas. Dejar vacío para no enviar copia.', 'aura-business-suite' ); ?></p>
                </div>

                <div class="aura-lib-sfield aura-lib-sfield--toggle">
                    <label class="aura-lib-toggle-label">
                        <input type="checkbox" name="whatsapp_alerts" value="1"
                               <?php checked( $g( 'whatsapp_alerts', false ) ); ?>>
                        <span><?php esc_html_e( 'Activar alertas por WhatsApp', 'aura-business-suite' ); ?></span>
                    </label>
                    <p class="description"><?php esc_html_e( 'Requiere que el módulo de Notificaciones de Aura Suite esté configurado.', 'aura-business-suite' ); ?></p>
                </div>

                <div class="aura-lib-sfield">
                    <label><?php esc_html_e( 'Hora del cron diario (0-23)', 'aura-business-suite' ); ?></label>
                    <input type="number" name="cron_hour" min="0" max="23"
                           value="<?php echo esc_attr( $g( 'cron_hour', 8 ) ); ?>" style="max-width:80px;">
                    <p class="description"><?php esc_html_e( 'Hora (formato 24h) en que se procesan préstamos vencidos y envían alertas.', 'aura-business-suite' ); ?></p>
                </div>

            </div>
            <button type="button" class="button button-primary aura-lib-save-btn" data-section="notifications"
                    style="background:#8b5cf6;border-color:#7c3aed;margin-top:16px;">
                💾 <?php esc_html_e( 'Guardar Notificaciones', 'aura-business-suite' ); ?>
            </button>
        </div>

        <!-- ── SECCIÓN 5: INTEGRACIONES ──────────────────── -->
        <div class="aura-lib-spanel" id="stab-integrations" style="display:none;">
            <div class="aura-lib-settings-grid">

                <div class="aura-lib-sfield aura-lib-sfield--toggle">
                    <label class="aura-lib-toggle-label">
                        <input type="checkbox" name="fines_to_finance" value="1"
                               <?php checked( $g( 'fines_to_finance', false ) ); ?>>
                        <span><?php esc_html_e( 'Registrar multas en Módulo de Finanzas', 'aura-business-suite' ); ?></span>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Cuando se cobra una multa, se crea automáticamente una transacción de ingreso en Finanzas.', 'aura-business-suite' ); ?>
                        <?php if ( ! class_exists( 'Aura_Finances' ) ) : ?>
                            <strong style="color:#dc2626;"><?php esc_html_e( '⚠ El módulo de Finanzas no está activo.', 'aura-business-suite' ); ?></strong>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="aura-lib-sfield aura-lib-sfield--toggle">
                    <label class="aura-lib-toggle-label">
                        <input type="checkbox" name="paz_y_salvo" value="1"
                               <?php checked( $g( 'paz_y_salvo', false ) ); ?>>
                        <span><?php esc_html_e( 'Incluir biblioteca en Paz y Salvo de Estudiantes', 'aura-business-suite' ); ?></span>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Si está activo, el paz y salvo bloqueará a estudiantes con préstamos vencidos o multas pendientes.', 'aura-business-suite' ); ?>
                        <?php if ( ! class_exists( 'Aura_Students_Dashboard' ) ) : ?>
                            <strong style="color:#dc2626;"><?php esc_html_e( '⚠ El módulo de Estudiantes no está activo.', 'aura-business-suite' ); ?></strong>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Info API REST -->
                <div class="aura-lib-sfield" style="grid-column: 1 / -1;">
                    <h4 style="margin:0 0 8px;border-bottom:1px solid #e9d5ff;padding-bottom:6px;">
                        🔌 <?php esc_html_e( 'API REST', 'aura-business-suite' ); ?>
                    </h4>
                    <p style="color:#555;margin:0 0 6px;">
                        <?php esc_html_e( 'Base URL de la API:', 'aura-business-suite' ); ?>
                        <code><?php echo esc_html( rest_url( 'aura/v1/library/' ) ); ?></code>
                    </p>
                    <p style="color:#6b7280;font-size:0.9em;margin:0;">
                        <?php esc_html_e( 'Autenticación: Cookie + X-WP-Nonce (panel) o Application Passwords (externo).', 'aura-business-suite' ); ?>
                    </p>
                    <table class="widefat striped" style="margin-top:12px;font-size:0.87em;max-width:700px;">
                        <thead><tr>
                            <th><?php esc_html_e( 'Método', 'aura-business-suite' ); ?></th>
                            <th><?php esc_html_e( 'Endpoint', 'aura-business-suite' ); ?></th>
                            <th><?php esc_html_e( 'Descripción', 'aura-business-suite' ); ?></th>
                        </tr></thead>
                        <tbody>
                            <?php
                            $endpoints = [
                                [ 'GET',    '/books',              __( 'Listar libros', 'aura-business-suite' ) ],
                                [ 'POST',   '/books',              __( 'Crear libro', 'aura-business-suite' ) ],
                                [ 'GET',    '/books/{id}',         __( 'Detalle de libro', 'aura-business-suite' ) ],
                                [ 'PUT',    '/books/{id}',         __( 'Actualizar libro', 'aura-business-suite' ) ],
                                [ 'DELETE', '/books/{id}',         __( 'Eliminar libro', 'aura-business-suite' ) ],
                                [ 'GET',    '/loans',              __( 'Listar préstamos', 'aura-business-suite' ) ],
                                [ 'POST',   '/loans',              __( 'Crear préstamo', 'aura-business-suite' ) ],
                                [ 'PUT',    '/loans/{id}/return',  __( 'Registrar devolución', 'aura-business-suite' ) ],
                                [ 'PUT',    '/loans/{id}/extend',  __( 'Extender préstamo', 'aura-business-suite' ) ],
                                [ 'GET',    '/reservations',       __( 'Listar reservas', 'aura-business-suite' ) ],
                                [ 'POST',   '/reservations',       __( 'Crear reserva', 'aura-business-suite' ) ],
                                [ 'GET',    '/dashboard',          __( 'KPIs del dashboard', 'aura-business-suite' ) ],
                                [ 'GET',    '/reports/summary',    __( 'Reporte resumen', 'aura-business-suite' ) ],
                            ];
                            $method_colors = [ 'GET' => '#dbeafe', 'POST' => '#dcfce7', 'PUT' => '#fef9c3', 'DELETE' => '#fee2e2' ];
                            foreach ( $endpoints as [ $method, $path, $desc ] ) :
                                $bg = $method_colors[ $method ] ?? '#f3f4f6';
                            ?>
                            <tr>
                                <td><span style="background:<?php echo esc_attr( $bg ); ?>;padding:1px 6px;border-radius:3px;font-size:11px;font-weight:700;"><?php echo esc_html( $method ); ?></span></td>
                                <td><code><?php echo esc_html( '/wp-json/aura/v1/library' . $path ); ?></code></td>
                                <td><?php echo esc_html( $desc ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
            <button type="button" class="button button-primary aura-lib-save-btn" data-section="integrations"
                    style="background:#8b5cf6;border-color:#7c3aed;margin-top:16px;">
                💾 <?php esc_html_e( 'Guardar Integraciones', 'aura-business-suite' ); ?>
            </button>
        </div>

        <!-- ── SECCIÓN 6: MANTENIMIENTO ───────────────────── -->
        <div class="aura-lib-spanel" id="stab-maintenance" style="display:none;">

            <div class="aura-lib-settings-section-card">
                <h4>🧹 <?php esc_html_e( 'Limpiar log de auditoría', 'aura-business-suite' ); ?></h4>
                <p style="color:#555;"><?php esc_html_e( 'Elimina registros de auditoría más antiguos que N días. Esta acción es irreversible.', 'aura-business-suite' ); ?></p>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <label><?php esc_html_e( 'Eliminar registros con más de', 'aura-business-suite' ); ?></label>
                    <input type="number" id="audit-clean-days" value="90" min="7" max="3650" style="width:80px;">
                    <label><?php esc_html_e( 'días', 'aura-business-suite' ); ?></label>
                    <button type="button" id="btn-audit-clean" class="button button-secondary" style="color:#dc2626;border-color:#dc2626;">
                        🗑 <?php esc_html_e( 'Limpiar auditoría', 'aura-business-suite' ); ?>
                    </button>
                    <span id="audit-clean-result" style="font-size:0.9em;color:#059669;display:none;"></span>
                </div>
            </div>

            <div class="aura-lib-settings-section-card" style="margin-top:16px;">
                <h4>ℹ <?php esc_html_e( 'Información del módulo', 'aura-business-suite' ); ?></h4>
                <?php
                global $wpdb;
                $cnt_books = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aura_library_books WHERE deleted_at IS NULL" ); // phpcs:ignore
                $cnt_loans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aura_library_loans" ); // phpcs:ignore
                $cnt_aud   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aura_library_audit" ); // phpcs:ignore
                ?>
                <table class="widefat" style="max-width:400px;font-size:0.9em;">
                    <tr><td><?php esc_html_e( 'Libros en catálogo', 'aura-business-suite' ); ?></td><td><strong><?php echo esc_html( $cnt_books ); ?></strong></td></tr>
                    <tr><td><?php esc_html_e( 'Préstamos registrados', 'aura-business-suite' ); ?></td><td><strong><?php echo esc_html( $cnt_loans ); ?></strong></td></tr>
                    <tr><td><?php esc_html_e( 'Registros de auditoría', 'aura-business-suite' ); ?></td><td><strong><?php echo esc_html( $cnt_aud ); ?></strong></td></tr>
                    <tr><td><?php esc_html_e( 'Versión del plugin', 'aura-business-suite' ); ?></td><td><code><?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '—' ); ?></code></td></tr>
                </table>
            </div>

        </div><!-- #stab-maintenance -->

    </form>

</div><!-- .aura-lib-wrap -->

<style>
.aura-lib-settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.aura-lib-sfield {
    background: #fff;
    border: 1px solid #e9d5ff;
    border-radius: 8px;
    padding: 16px;
}
.aura-lib-sfield label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    font-size: 0.95em;
}
.aura-lib-sfield input[type="number"],
.aura-lib-sfield input[type="email"],
.aura-lib-sfield input[type="text"] {
    width: 100%;
    max-width: 280px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    padding: 6px 10px;
}
.aura-lib-sfield .description { color: #6b7280; font-size: 0.85em; margin-top: 6px; }
.aura-lib-sfield--toggle label { font-weight: normal; cursor: pointer; }
.aura-lib-toggle-label { display: flex; align-items: center; gap: 8px; font-size: 0.95em; }
.aura-lib-toggle-label input[type="checkbox"] { width: 18px; height: 18px; accent-color: #8b5cf6; }
.aura-lib-settings-section-card {
    background: #fff;
    border: 1px solid #e9d5ff;
    border-radius: 8px;
    padding: 20px;
}
.aura-lib-settings-section-card h4 { margin: 0 0 10px; font-size: 1em; }
.aura-lib-stab { border-radius: 4px 4px 0 0 !important; margin-bottom: -2px; }
</style>

<script>
(function($){
    'use strict';

    var nonce   = '<?php echo esc_js( $nonce ); ?>';
    var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    // ── Tabs ─────────────────────────────────────────────────────
    $('.aura-lib-stab').on('click', function(){
        var tab = $(this).data('tab');
        $('.aura-lib-stab').removeClass('button-primary').css({ background:'', borderColor:'', color:'' });
        $(this).addClass('button-primary').css({ background:'#8b5cf6', borderColor:'#7c3aed', color:'#fff' });
        $('.aura-lib-spanel').hide();
        $('#stab-' + tab).show();
    });

    // ── Guardar sección ─────────────────────────────────────────
    $(document).on('click', '.aura-lib-save-btn', function(){
        var section = $(this).data('section');
        var $panel  = $('#stab-' + section);
        var data    = { action: 'aura_library_settings_save', nonce: nonce };

        // Recopilar campos del panel activo
        $panel.find('input[name], select[name]').each(function(){
            var $el  = $(this);
            var name = $el.attr('name');
            if ($el.attr('type') === 'checkbox') {
                data[ name ] = $el.is(':checked') ? '1' : '0';
            } else {
                data[ name ] = $el.val();
            }
        });

        var $btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Guardando…', 'aura-business-suite' ) ); ?>');

        $.post(ajaxUrl, data, function(res){
            var $notice = $('#settings-notice');
            if (res.success){
                $notice.removeClass('notice-error').addClass('notice notice-success is-dismissible').show();
                $('#settings-notice-msg').text(res.data.message || '<?php echo esc_js( __( 'Guardado.', 'aura-business-suite' ) ); ?>');
            } else {
                $notice.removeClass('notice-success').addClass('notice notice-error is-dismissible').show();
                var errMsg = (res.data && res.data.message) || '<?php echo esc_js( __( 'Error al guardar.', 'aura-business-suite' ) ); ?>';
                $('#settings-notice-msg').text(errMsg);
            }
            setTimeout(function(){ $notice.hide(); }, 5000);
        }).fail(function(){
            alert('<?php echo esc_js( __( 'Error de conexión.', 'aura-business-suite' ) ); ?>');
        }).always(function(){
            $btn.prop('disabled', false).text('💾 <?php echo esc_js( __( 'Guardar', 'aura-business-suite' ) ); ?>');
        });
    });

    // ── Limpiar auditoría ────────────────────────────────────────
    $('#btn-audit-clean').on('click', function(){
        var days = parseInt($('#audit-clean-days').val(), 10);
        if (isNaN(days) || days < 7){
            alert('<?php echo esc_js( __( 'El mínimo es 7 días.', 'aura-business-suite' ) ); ?>');
            return;
        }
        if (!confirm('<?php echo esc_js( __( '¿Eliminar registros de auditoría anteriores a los días indicados? Esta acción es irreversible.', 'aura-business-suite' ) ); ?>')) return;

        var $btn = $(this).prop('disabled', true);
        $.post(ajaxUrl, { action:'aura_library_audit_clean', nonce:nonce, days:days }, function(res){
            var $r = $('#audit-clean-result');
            if (res.success){
                $r.text(res.data.message).css('color','#059669').show();
            } else {
                $r.text((res.data && res.data.message)||'Error').css('color','#dc2626').show();
            }
            setTimeout(function(){ $r.hide(); }, 5000);
        }).always(function(){ $btn.prop('disabled', false); });
    });

})(jQuery);
</script>
