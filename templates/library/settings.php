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
<div class="aura-app-wrapper aura-library-settings">
    <div class="wrap aura-app-context aura-lib-wrap">

        <?php
        Aura_UI::render_page_header( [
            'title'    => __( 'Configuración — Biblioteca', 'aura-suite' ),
            'subtitle' => __( 'Reglas de préstamo, cálculo de multas, alertas automáticas, integraciones y mantenimiento de auditoría.', 'aura-suite' ),
            'icon'     => 'dashicons-admin-settings',
            'badges'   => [
                [ 'label' => __( '6 Módulos de Ajuste', 'aura-suite' ), 'variant' => 'violet' ],
            ],
            'actions'  => [
                [
                    'label' => __( 'Volver al Dashboard', 'aura-suite' ),
                    'url'   => admin_url( 'admin.php?page=aura-library' ),
                    'class' => 'btn btn-glass btn-sm btn-lift',
                    'icon'  => 'dashicons-dashboard',
                ],
            ],
        ] );
        ?>

        <div id="settings-notice" style="display:none;" class="aura-lib-settings-notice notice notice-success is-dismissible">
            <p id="settings-notice-msg"></p>
        </div>

        <!-- ── Tabs ─────────────────────────────────────────────── -->
        <div class="aura-glass-card aura-lib-settings-tabs" role="tablist">
            <?php
            $tabs = [
                'loans'         => '📋 ' . __( 'Préstamos', 'aura-suite' ),
                'fines'         => '💸 ' . __( 'Multas', 'aura-suite' ),
                'reservations'  => '🔖 ' . __( 'Reservas', 'aura-suite' ),
                'notifications' => '🔔 ' . __( 'Notificaciones', 'aura-suite' ),
                'integrations'  => '🔗 ' . __( 'Integraciones', 'aura-suite' ),
                'maintenance'   => '🔧 ' . __( 'Mantenimiento', 'aura-suite' ),
            ];
            foreach ( $tabs as $tabId => $tabLabel ) :
            ?>
                <button type="button"
                        class="aura-lib-stab btn btn-sm<?php echo $tabId === 'loans' ? ' is-active' : ''; ?>"
                        data-tab="<?php echo esc_attr( $tabId ); ?>">
                    <?php echo esc_html( $tabLabel ); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <form id="form-library-settings" novalidate>
            <input type="hidden" id="settings-nonce" value="<?php echo esc_attr( $nonce ); ?>">

            <!-- ── SECCIÓN 1: PRÉSTAMOS ────────────────────────── -->
            <div class="aura-glass-card aura-lib-spanel" id="stab-loans">
                <div class="aura-lib-settings-grid">

                    <div class="aura-lib-sfield">
                        <label for="cfg-loan-days"><?php esc_html_e( 'Días de préstamo por defecto', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">📆</span>
                            <input type="number" id="cfg-loan-days" name="loan_days" class="form-control" min="1" max="365"
                                   value="<?php echo esc_attr( $g( 'loan_days', 14 ) ); ?>">
                        </div>
                        <p class="description"><?php esc_html_e( 'Días que el lector tiene para devolver el libro.', 'aura-suite' ); ?></p>
                    </div>

                    <div class="aura-lib-sfield">
                        <label for="cfg-extension-days"><?php esc_html_e( 'Días de extensión por solicitud', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">➕</span>
                            <input type="number" id="cfg-extension-days" name="extension_days" class="form-control" min="1" max="90"
                                   value="<?php echo esc_attr( $g( 'extension_days', 7 ) ); ?>">
                        </div>
                        <p class="description"><?php esc_html_e( 'Días que se añaden al plazo cuando se solicita extensión.', 'aura-suite' ); ?></p>
                    </div>

                    <div class="aura-lib-sfield">
                        <label for="cfg-max-extensions"><?php esc_html_e( 'Extensiones máximas por préstamo', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">🔁</span>
                            <input type="number" id="cfg-max-extensions" name="max_extensions" class="form-control" min="0" max="10"
                                   value="<?php echo esc_attr( $g( 'max_extensions', 2 ) ); ?>">
                        </div>
                        <p class="description"><?php esc_html_e( 'Cuántas veces se puede extender un mismo préstamo.', 'aura-suite' ); ?></p>
                    </div>

                </div>
                <div class="aura-lib-settings-actions">
                    <button type="button" class="btn btn-shimmer btn-violet btn-lift aura-lib-save-btn" data-section="loans"
                            data-default-label="<?php esc_attr_e( '💾 Guardar Préstamos', 'aura-suite' ); ?>">
                        💾 <?php esc_html_e( 'Guardar Préstamos', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

            <!-- ── SECCIÓN 2: MULTAS ───────────────────────────── -->
            <div class="aura-glass-card aura-lib-spanel" id="stab-fines" style="display:none;">
                <div class="aura-lib-settings-grid">

                    <div class="aura-lib-sfield aura-lib-sfield--toggle">
                        <label class="aura-lib-toggle-label">
                            <input type="checkbox" name="fines_enabled" value="1"
                                   <?php checked( $g( 'fines_enabled', false ) ); ?>>
                            <span><?php esc_html_e( 'Activar sistema de multas', 'aura-suite' ); ?></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Si se desactiva, no se calculará ni cobrará multa por devoluciones tardías.', 'aura-suite' ); ?></p>
                    </div>

                    <div class="aura-lib-sfield">
                        <label for="cfg-fine-per-day"><?php esc_html_e( 'Tarifa diaria de multa', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">💵 $</span>
                            <input type="number" id="cfg-fine-per-day" name="fine_per_day" class="form-control" min="0" step="0.01"
                                   value="<?php echo esc_attr( $g( 'fine_per_day', '0.00' ) ); ?>">
                        </div>
                        <p class="description"><?php esc_html_e( 'Monto cobrado por cada día de retraso.', 'aura-suite' ); ?></p>
                    </div>

                    <div class="aura-lib-sfield">
                        <label for="cfg-grace-days"><?php esc_html_e( 'Período de gracia (días)', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">⏳</span>
                            <input type="number" id="cfg-grace-days" name="grace_days" class="form-control" min="0" max="30"
                                   value="<?php echo esc_attr( $g( 'grace_days', 1 ) ); ?>">
                        </div>
                        <p class="description"><?php esc_html_e( 'Días después de la fecha de vencimiento antes de aplicar multa.', 'aura-suite' ); ?></p>
                    </div>

                    <div class="aura-lib-sfield">
                        <label for="cfg-fine-max"><?php esc_html_e( 'Multa máxima por préstamo', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">🛡️ $</span>
                            <input type="number" id="cfg-fine-max" name="fine_max" class="form-control" min="0" step="0.01"
                                   value="<?php echo esc_attr( $g( 'fine_max', '0.00' ) ); ?>">
                        </div>
                        <p class="description"><?php esc_html_e( 'Tope máximo por préstamo. 0 = sin límite.', 'aura-suite' ); ?></p>
                    </div>

                </div>
                <div class="aura-lib-settings-actions">
                    <button type="button" class="btn btn-shimmer btn-violet btn-lift aura-lib-save-btn" data-section="fines"
                            data-default-label="<?php esc_attr_e( '💾 Guardar Multas', 'aura-suite' ); ?>">
                        💾 <?php esc_html_e( 'Guardar Multas', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

            <!-- ── SECCIÓN 3: RESERVAS ────────────────────────── -->
            <div class="aura-glass-card aura-lib-spanel" id="stab-reservations" style="display:none;">
                <div class="aura-lib-settings-grid">

                    <div class="aura-lib-sfield">
                        <label for="cfg-reservation-expire-days"><?php esc_html_e( 'Días para expirar una reserva', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">🔖</span>
                            <input type="number" id="cfg-reservation-expire-days" name="reservation_expire_days" class="form-control" min="1" max="30"
                                   value="<?php echo esc_attr( $g( 'reservation_expire_days', 2 ) ); ?>">
                        </div>
                        <p class="description"><?php esc_html_e( 'Si el lector no recoge el libro en estos días, la reserva se cancela automáticamente.', 'aura-suite' ); ?></p>
                    </div>

                </div>
                <div class="aura-lib-settings-actions">
                    <button type="button" class="btn btn-shimmer btn-violet btn-lift aura-lib-save-btn" data-section="reservations"
                            data-default-label="<?php esc_attr_e( '💾 Guardar Reservas', 'aura-suite' ); ?>">
                        💾 <?php esc_html_e( 'Guardar Reservas', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

            <!-- ── SECCIÓN 4: NOTIFICACIONES ─────────────────── -->
            <div class="aura-glass-card aura-lib-spanel" id="stab-notifications" style="display:none;">
                <div class="aura-lib-settings-grid">

                    <div class="aura-lib-sfield aura-lib-sfield--toggle">
                        <label class="aura-lib-toggle-label">
                            <input type="checkbox" name="email_alerts" value="1"
                                   <?php checked( $g( 'email_alerts', true ) ); ?>>
                            <span><?php esc_html_e( 'Activar alertas por email', 'aura-suite' ); ?></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Envía alertas de vencimiento y recordatorios por correo electrónico.', 'aura-suite' ); ?></p>
                    </div>

                    <div class="aura-lib-sfield">
                        <label for="cfg-email-extra"><?php esc_html_e( 'Email extra del bibliotecario', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">📧</span>
                            <input type="email" id="cfg-email-extra" name="email_extra" class="form-control"
                                   placeholder="bibliotecario@ejemplo.com"
                                   value="<?php echo esc_attr( $g( 'email_extra', '' ) ); ?>">
                        </div>
                        <p class="description"><?php esc_html_e( 'Recibirá copia de todas las alertas. Dejar vacío para no enviar copia.', 'aura-suite' ); ?></p>
                    </div>

                    <div class="aura-lib-sfield aura-lib-sfield--toggle">
                        <label class="aura-lib-toggle-label">
                            <input type="checkbox" name="whatsapp_alerts" value="1"
                                   <?php checked( $g( 'whatsapp_alerts', false ) ); ?>>
                            <span><?php esc_html_e( 'Activar alertas por WhatsApp', 'aura-suite' ); ?></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Requiere que el módulo de Notificaciones de Aura Suite esté configurado.', 'aura-suite' ); ?></p>
                    </div>

                    <div class="aura-lib-sfield">
                        <label for="cfg-cron-hour"><?php esc_html_e( 'Hora del cron diario (0-23)', 'aura-suite' ); ?></label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">🕒</span>
                            <input type="number" id="cfg-cron-hour" name="cron_hour" class="form-control" min="0" max="23"
                                   value="<?php echo esc_attr( $g( 'cron_hour', 8 ) ); ?>">
                        </div>
                        <p class="description"><?php esc_html_e( 'Hora (formato 24h) en que se procesan préstamos vencidos y envían alertas.', 'aura-suite' ); ?></p>
                    </div>

                </div>
                <div class="aura-lib-settings-actions">
                    <button type="button" class="btn btn-shimmer btn-violet btn-lift aura-lib-save-btn" data-section="notifications"
                            data-default-label="<?php esc_attr_e( '💾 Guardar Notificaciones', 'aura-suite' ); ?>">
                        💾 <?php esc_html_e( 'Guardar Notificaciones', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

            <!-- ── SECCIÓN 5: INTEGRACIONES ──────────────────── -->
            <div class="aura-glass-card aura-lib-spanel" id="stab-integrations" style="display:none;">
                <div class="aura-lib-settings-grid">

                    <div class="aura-lib-sfield aura-lib-sfield--toggle">
                        <label class="aura-lib-toggle-label">
                            <input type="checkbox" name="fines_to_finance" value="1"
                                   <?php checked( $g( 'fines_to_finance', false ) ); ?>>
                            <span><?php esc_html_e( 'Registrar multas en Módulo de Finanzas', 'aura-suite' ); ?></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Cuando se cobra una multa, se crea automáticamente una transacción de ingreso en Finanzas.', 'aura-suite' ); ?>
                            <?php if ( ! class_exists( 'Aura_Finances' ) ) : ?>
                                <strong class="aura-lib-warning-text"><?php esc_html_e( '⚠ El módulo de Finanzas no está activo.', 'aura-suite' ); ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="aura-lib-sfield aura-lib-sfield--toggle">
                        <label class="aura-lib-toggle-label">
                            <input type="checkbox" name="paz_y_salvo" value="1"
                                   <?php checked( $g( 'paz_y_salvo', false ) ); ?>>
                            <span><?php esc_html_e( 'Incluir biblioteca en Paz y Salvo de Estudiantes', 'aura-suite' ); ?></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Si está activo, el paz y salvo bloqueará a estudiantes con préstamos vencidos o multas pendientes.', 'aura-suite' ); ?>
                            <?php if ( ! class_exists( 'Aura_Students_Dashboard' ) ) : ?>
                                <strong class="aura-lib-warning-text"><?php esc_html_e( '⚠ El módulo de Estudiantes no está activo.', 'aura-suite' ); ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- Info API REST -->
                    <div class="aura-lib-sfield aura-lib-sfield--full">
                        <h4 class="aura-lib-settings-card-heading">
                            🔌 <?php esc_html_e( 'API REST', 'aura-suite' ); ?>
                        </h4>
                        <p class="aura-lib-muted-line">
                            <?php esc_html_e( 'Base URL de la API:', 'aura-suite' ); ?>
                            <code><?php echo esc_html( rest_url( 'aura/v1/library/' ) ); ?></code>
                        </p>
                        <p class="description">
                            <?php esc_html_e( 'Autenticación: Cookie + X-WP-Nonce (panel) o Application Passwords (externo).', 'aura-suite' ); ?>
                        </p>
                        <div class="aura-dt-wrapper aura-table-responsive-wrap" style="margin-top:12px;">
                            <table class="aura-table widefat aura-lib-api-table">
                                <thead><tr>
                                    <th style="width:90px;"><?php esc_html_e( 'Método', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Endpoint', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Descripción', 'aura-suite' ); ?></th>
                                </tr></thead>
                                <tbody>
                                    <?php
                                    $endpoints = [
                                        [ 'GET',    '/books',              __( 'Listar libros', 'aura-suite' ) ],
                                        [ 'POST',   '/books',              __( 'Crear libro', 'aura-suite' ) ],
                                        [ 'GET',    '/books/{id}',         __( 'Detalle de libro', 'aura-suite' ) ],
                                        [ 'PUT',    '/books/{id}',         __( 'Actualizar libro', 'aura-suite' ) ],
                                        [ 'DELETE', '/books/{id}',         __( 'Eliminar libro', 'aura-suite' ) ],
                                        [ 'GET',    '/loans',              __( 'Listar préstamos', 'aura-suite' ) ],
                                        [ 'POST',   '/loans',              __( 'Crear préstamo', 'aura-suite' ) ],
                                        [ 'PUT',    '/loans/{id}/return',  __( 'Registrar devolución', 'aura-suite' ) ],
                                        [ 'PUT',    '/loans/{id}/extend',  __( 'Extender préstamo', 'aura-suite' ) ],
                                        [ 'GET',    '/reservations',       __( 'Listar reservas', 'aura-suite' ) ],
                                        [ 'POST',   '/reservations',       __( 'Crear reserva', 'aura-suite' ) ],
                                        [ 'GET',    '/dashboard',          __( 'KPIs del dashboard', 'aura-suite' ) ],
                                        [ 'GET',    '/reports/summary',    __( 'Reporte resumen', 'aura-suite' ) ],
                                    ];
                                    $method_badges = [ 'GET' => 'badge-blue', 'POST' => 'badge-emerald', 'PUT' => 'badge-amber', 'DELETE' => 'badge-red' ];
                                    foreach ( $endpoints as [ $method, $path, $desc ] ) :
                                        $badge_cls = $method_badges[ $method ] ?? 'badge-slate';
                                    ?>
                                    <tr>
                                        <td><span class="badge <?php echo esc_attr( $badge_cls ); ?>"><?php echo esc_html( $method ); ?></span></td>
                                        <td><code><?php echo esc_html( '/wp-json/aura/v1/library' . $path ); ?></code></td>
                                        <td><?php echo esc_html( $desc ); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
                <div class="aura-lib-settings-actions">
                    <button type="button" class="btn btn-shimmer btn-violet btn-lift aura-lib-save-btn" data-section="integrations"
                            data-default-label="<?php esc_attr_e( '💾 Guardar Integraciones', 'aura-suite' ); ?>">
                        💾 <?php esc_html_e( 'Guardar Integraciones', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

            <!-- ── SECCIÓN 6: MANTENIMIENTO ───────────────────── -->
            <div class="aura-glass-card aura-lib-spanel" id="stab-maintenance" style="display:none;">

                <div class="aura-lib-settings-section-card">
                    <h4>🧹 <?php esc_html_e( 'Limpiar log de auditoría', 'aura-suite' ); ?></h4>
                    <p class="aura-lib-muted-line"><?php esc_html_e( 'Elimina registros de auditoría más antiguos que N días. Esta acción es irreversible.', 'aura-suite' ); ?></p>
                    <div class="aura-lib-clean-bar">
                        <label for="audit-clean-days"><?php esc_html_e( 'Antigüedad mínima (días):', 'aura-suite' ); ?></label>
                        <div class="aura-lib-clean-input-wrap">
                            <div class="input-group">
                                <span class="input-group-text" aria-hidden="true">🗓️</span>
                                <input type="number" id="audit-clean-days" class="form-control" value="90" min="7" max="3650">
                            </div>
                        </div>
                        <button type="button" id="btn-audit-clean" class="btn btn-rose btn-lift">
                            🗑️ <?php esc_html_e( 'Limpiar auditoría', 'aura-suite' ); ?>
                        </button>
                        <span id="audit-clean-result" class="aura-lib-clean-result" style="display:none;"></span>
                    </div>
                </div>

                <div class="aura-lib-settings-section-card" style="margin-top:16px;">
                    <h4>ℹ️ <?php esc_html_e( 'Información del módulo', 'aura-suite' ); ?></h4>
                    <?php
                    global $wpdb;
                    $cnt_books = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aura_library_books WHERE deleted_at IS NULL" ); // phpcs:ignore
                    $cnt_loans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aura_library_loans" ); // phpcs:ignore
                    $cnt_aud   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aura_library_audit" ); // phpcs:ignore
                    ?>
                    <div class="aura-dt-wrapper aura-table-responsive-wrap" style="max-width:480px;">
                        <table class="aura-table widefat aura-lib-info-table">
                            <tbody>
                                <tr><td><?php esc_html_e( 'Libros en catálogo', 'aura-suite' ); ?></td><td><strong><?php echo esc_html( $cnt_books ); ?></strong></td></tr>
                                <tr><td><?php esc_html_e( 'Préstamos registrados', 'aura-suite' ); ?></td><td><strong><?php echo esc_html( $cnt_loans ); ?></strong></td></tr>
                                <tr><td><?php esc_html_e( 'Registros de auditoría', 'aura-suite' ); ?></td><td><strong><?php echo esc_html( $cnt_aud ); ?></strong></td></tr>
                                <tr><td><?php esc_html_e( 'Versión del plugin', 'aura-suite' ); ?></td><td><code><?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '—' ); ?></code></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- #stab-maintenance -->

        </form>

        <div class="adp-footer">
            <div>
                Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; Versión <?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' ); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
            </div>
        </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<script>
(function($){
    'use strict';

    var nonce   = '<?php echo esc_js( $nonce ); ?>';
    var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    // ── Tabs ─────────────────────────────────────────────────────
    $('.aura-lib-stab').on('click', function(){
        var tab = $(this).data('tab');
        $('.aura-lib-stab').removeClass('is-active');
        $(this).addClass('is-active');
        $('.aura-lib-spanel').hide();
        $('#stab-' + tab).show();
    });

    // ── Guardar sección ─────────────────────────────────────────
    $(document).on('click', '.aura-lib-save-btn', function(){
        var $btn         = $(this);
        var section      = $btn.data('section');
        var defaultLabel = $btn.data('default-label') || '<?php echo esc_js( __( '💾 Guardar', 'aura-suite' ) ); ?>';
        var $panel       = $('#stab-' + section);
        var data         = { action: 'aura_library_settings_save', nonce: nonce };

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

        $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Guardando…', 'aura-suite' ) ); ?>');

        $.post(ajaxUrl, data, function(res){
            var $notice = $('#settings-notice');
            if (res.success){
                var okMsg = (res.data && res.data.message) || '<?php echo esc_js( __( 'Guardado.', 'aura-suite' ) ); ?>';
                $notice.removeClass('notice-error').addClass('notice notice-success is-dismissible').show();
                $('#settings-notice-msg').text(okMsg);
                if (typeof AuraUI !== 'undefined' && typeof AuraUI.showToast === 'function') {
                    AuraUI.showToast(okMsg, 'success');
                }
            } else {
                var errMsg = (res.data && res.data.message) || '<?php echo esc_js( __( 'Error al guardar.', 'aura-suite' ) ); ?>';
                $notice.removeClass('notice-success').addClass('notice notice-error is-dismissible').show();
                $('#settings-notice-msg').text(errMsg);
                if (typeof AuraUI !== 'undefined' && typeof AuraUI.showToast === 'function') {
                    AuraUI.showToast(errMsg, 'error');
                }
            }
            setTimeout(function(){ $notice.hide(); }, 5000);
        }).fail(function(){
            if (typeof AuraUI !== 'undefined' && typeof AuraUI.showToast === 'function') {
                AuraUI.showToast('<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>', 'error');
            } else {
                alert('<?php echo esc_js( __( 'Error de conexión.', 'aura-suite' ) ); ?>');
            }
        }).always(function(){
            $btn.prop('disabled', false).text(defaultLabel);
        });
    });

    // ── Limpiar auditoría ────────────────────────────────────────
    $('#btn-audit-clean').on('click', function(){
        var days = parseInt($('#audit-clean-days').val(), 10);
        if (isNaN(days) || days < 7){
            if (typeof AuraUI !== 'undefined' && typeof AuraUI.showToast === 'function') {
                AuraUI.showToast('<?php echo esc_js( __( 'El mínimo es 7 días.', 'aura-suite' ) ); ?>', 'warning');
            } else {
                alert('<?php echo esc_js( __( 'El mínimo es 7 días.', 'aura-suite' ) ); ?>');
            }
            return;
        }
        if (!confirm('<?php echo esc_js( __( '¿Eliminar registros de auditoría anteriores a los días indicados? Esta acción es irreversible.', 'aura-suite' ) ); ?>')) return;

        var $btn = $(this).prop('disabled', true);
        $.post(ajaxUrl, { action:'aura_library_audit_clean', nonce:nonce, days:days }, function(res){
            var $r = $('#audit-clean-result');
            if (res.success){
                $r.removeClass('is-error').addClass('is-success').text(res.data.message).show();
                if (typeof AuraUI !== 'undefined' && typeof AuraUI.showToast === 'function') {
                    AuraUI.showToast(res.data.message, 'success');
                }
            } else {
                var msg = (res.data && res.data.message) || '<?php echo esc_js( __( 'Error', 'aura-suite' ) ); ?>';
                $r.removeClass('is-success').addClass('is-error').text(msg).show();
                if (typeof AuraUI !== 'undefined' && typeof AuraUI.showToast === 'function') {
                    AuraUI.showToast(msg, 'error');
                }
            }
            setTimeout(function(){ $r.hide(); }, 5000);
        }).always(function(){ $btn.prop('disabled', false); });
    });

})(jQuery);
</script>
