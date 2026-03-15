<?php
/**
 * Template — Configuración del Módulo de Inventario
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$settings   = Aura_Inventory_Categories::get_settings();
$categories = Aura_Inventory_Categories::get_all();
$nonce      = wp_create_nonce( 'aura_inventory_nonce' );

// Categorías financieras para tab Finanzas
global $wpdb;
$finance_cats_settings = [];
if ( class_exists( 'Aura_Financial_Categories' ) ) {
    $finance_cats_settings = $wpdb->get_results(
        "SELECT id, name FROM {$wpdb->prefix}aura_finance_categories
         WHERE type = 'expense' AND is_active = 1 ORDER BY name ASC"
    ) ?: [];
}

$interval_labels = [
    'none'  => __( 'Sin intervalo', 'aura-suite' ),
    'time'  => __( 'Por tiempo',    'aura-suite' ),
    'hours' => __( 'Por horas',     'aura-suite' ),
    'both'  => __( 'Tiempo + horas','aura-suite' ),
];
?>
<div class="wrap aura-inv-settings-wrap">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-tools" style="color:#2271b1;margin-right:6px;"></span>
        <?php _e( 'Configuración — Módulo de Inventario', 'aura-suite' ); ?>
    </h1>
    <hr class="wp-header-end">

    <!-- ── Mensaje global ─────────────────────────────────── -->
    <div id="js-inv-settings-notice" class="aura-inv-notice" style="display:none;"></div>

    <!-- ══════════════════════════════════════════════════════
         PESTAÑAS
    ══════════════════════════════════════════════════════════ -->
    <nav class="aura-inv-tabs" role="tablist">
        <button type="button" class="aura-inv-tab active" data-tab="categories" role="tab">
            <span class="dashicons dashicons-category"></span>
            <?php _e( 'Categorías', 'aura-suite' ); ?>
        </button>
        <button type="button" class="aura-inv-tab" data-tab="general" role="tab">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e( 'General', 'aura-suite' ); ?>
        </button>
        <button type="button" class="aura-inv-tab" data-tab="notifications" role="tab">
            <span class="dashicons dashicons-email-alt"></span>
            <?php _e( 'Notificaciones', 'aura-suite' ); ?>
        </button>
        <button type="button" class="aura-inv-tab" data-tab="gcal" role="tab">
            <span class="dashicons dashicons-calendar-alt" style="color:#4285f4;"></span>
            <?php _e( 'Google Calendar', 'aura-suite' ); ?>
        </button>
        <button type="button" class="aura-inv-tab" data-tab="finanzas" role="tab">
            <span class="dashicons dashicons-money-alt" style="color:#27ae60;"></span>
            <?php _e( 'Finanzas', 'aura-suite' ); ?>
        </button>
    </nav>

    <!-- ══════════════════════════════════════════════════════
         TAB 1: CATEGORÍAS
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-inv-tab-panel active" data-panel="categories">

        <div class="aura-inv-section-header">
            <div>
                <h2><?php _e( 'Categorías de Equipos', 'aura-suite' ); ?></h2>
                <p class="description">
                    <?php _e( 'Las categorías organizan el inventario y definen el intervalo de mantenimiento por defecto para cada tipo de equipo.', 'aura-suite' ); ?>
                </p>
            </div>
            <button type="button" id="js-install-defaults" class="button button-secondary aura-inv-btn-defaults">
                <span class="dashicons dashicons-download"></span>
                <?php _e( 'Instalar categorías predeterminadas', 'aura-suite' ); ?>
            </button>
        </div>

        <!-- Tabla de categorías existentes -->
        <div class="aura-inv-card" style="margin-bottom:24px;">
            <table id="js-categories-table" class="wp-list-table widefat fixed striped aura-inv-cats-table">
                <thead>
                    <tr>
                        <th class="col-name"><?php _e( 'Nombre',    'aura-suite' ); ?></th>
                        <th class="col-slug"><?php _e( 'Slug',      'aura-suite' ); ?></th>
                        <th class="col-interval"><?php _e( 'Intervalo de mantenimiento', 'aura-suite' ); ?></th>
                        <th class="col-count"><?php _e( 'Equipos',  'aura-suite' ); ?></th>
                        <th class="col-actions"><?php _e( 'Acciones','aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody id="js-categories-tbody">
                <?php if ( $categories ) : ?>
                    <?php foreach ( $categories as $cat ) :
                        $interval_str = '';
                        switch ( $cat->interval_type ) {
                            case 'time':
                                $interval_str = sprintf(
                                    _n( 'Cada %d mes', 'Cada %d meses', (int)$cat->interval_months, 'aura-suite' ),
                                    (int)$cat->interval_months
                                );
                                break;
                            case 'hours':
                                $interval_str = sprintf( __( 'Cada %s horas', 'aura-suite' ), number_format( (float)$cat->interval_hours ) );
                                break;
                            case 'both':
                                $interval_str = sprintf(
                                    __( 'Cada %d meses / %s h', 'aura-suite' ),
                                    (int)$cat->interval_months,
                                    number_format( (float)$cat->interval_hours )
                                );
                                break;
                            default:
                                $interval_str = __( 'Sin programa', 'aura-suite' );
                        }
                    ?>
                    <tr data-term-id="<?php echo esc_attr( $cat->term_id ); ?>">
                        <td class="col-name"><strong><?php echo esc_html( $cat->name ); ?></strong></td>
                        <td class="col-slug"><code><?php echo esc_html( $cat->slug ); ?></code></td>
                        <td class="col-interval">
                            <span class="aura-inv-interval-badge aura-inv-interval-<?php echo esc_attr( $cat->interval_type ?: 'none' ); ?>">
                                <?php echo esc_html( $interval_str ); ?>
                            </span>
                        </td>
                        <td class="col-count">
                            <span class="aura-inv-count-badge"><?php echo number_format( (int)$cat->count ); ?></span>
                        </td>
                        <td class="col-actions">
                            <div class="aura-inv-action-group">
                                <button type="button" class="button button-small aura-inv-btn-edit-cat"
                                        data-term-id="<?php echo esc_attr( $cat->term_id ); ?>"
                                        data-name="<?php echo esc_attr( $cat->name ); ?>"
                                        data-slug="<?php echo esc_attr( $cat->slug ); ?>"
                                        data-description="<?php echo esc_attr( $cat->description ?? '' ); ?>"
                                        data-interval-type="<?php echo esc_attr( $cat->interval_type ?: 'none' ); ?>"
                                        data-interval-months="<?php echo esc_attr( $cat->interval_months ?? '' ); ?>"
                                        data-interval-hours="<?php echo esc_attr( $cat->interval_hours ?? '' ); ?>"
                                        title="<?php esc_attr_e( 'Editar categoría', 'aura-suite' ); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button type="button" class="button button-small aura-inv-btn-delete-cat"
                                        data-term-id="<?php echo esc_attr( $cat->term_id ); ?>"
                                        data-name="<?php echo esc_attr( $cat->name ); ?>"
                                        title="<?php esc_attr_e( 'Eliminar categoría', 'aura-suite' ); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr id="js-cats-empty">
                        <td colspan="5" style="padding:20px;text-align:center;color:#646970;">
                            <?php _e( 'No hay categorías. Haz clic en "Instalar categorías predeterminadas" para agregar las más comunes.', 'aura-suite' ); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Formulario: Agregar nueva categoría -->
        <div class="aura-inv-card">
            <h3 class="aura-inv-card-title">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e( 'Agregar nueva categoría', 'aura-suite' ); ?>
            </h3>

            <div id="js-add-cat-msg" class="aura-inv-notice" style="display:none;"></div>

            <form id="js-form-add-category" novalidate>
                <div class="aura-inv-form-grid">

                    <div class="aura-inv-field">
                        <label for="cat-name" class="aura-inv-label">
                            <?php _e( 'Nombre', 'aura-suite' ); ?> <span class="required">*</span>
                        </label>
                        <input type="text" id="cat-name" name="name" class="regular-text"
                               placeholder="<?php esc_attr_e( 'Ej: Equipos de Construcción', 'aura-suite' ); ?>" required>
                    </div>

                    <div class="aura-inv-field">
                        <label for="cat-slug" class="aura-inv-label">
                            <?php _e( 'Slug (identificador)', 'aura-suite' ); ?>
                        </label>
                        <input type="text" id="cat-slug" name="slug" class="regular-text"
                               placeholder="<?php esc_attr_e( 'auto-generado si se deja vacío', 'aura-suite' ); ?>">
                    </div>

                    <div class="aura-inv-field aura-inv-field-full">
                        <label for="cat-description" class="aura-inv-label">
                            <?php _e( 'Descripción', 'aura-suite' ); ?>
                        </label>
                        <input type="text" id="cat-description" name="description" class="large-text"
                               placeholder="<?php esc_attr_e( 'Descripción breve de esta categoría…', 'aura-suite' ); ?>">
                    </div>

                </div>

                <div class="aura-inv-interval-block">
                    <h4><?php _e( 'Intervalo de mantenimiento predeterminado', 'aura-suite' ); ?></h4>
                    <p class="description"><?php _e( 'Al registrar un equipo de esta categoría, se pre-llenará con este intervalo.', 'aura-suite' ); ?></p>

                    <div class="aura-inv-form-grid" style="margin-top:10px;">
                        <div class="aura-inv-field">
                            <label for="cat-interval-type" class="aura-inv-label">
                                <?php _e( 'Tipo de intervalo', 'aura-suite' ); ?>
                            </label>
                            <select id="cat-interval-type" name="interval_type" class="regular-text">
                                <?php foreach ( $interval_labels as $val => $lbl ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="aura-inv-field" id="cat-months-wrap">
                            <label for="cat-interval-months" class="aura-inv-label">
                                <?php _e( 'Cada N meses', 'aura-suite' ); ?>
                            </label>
                            <input type="number" id="cat-interval-months" name="interval_months"
                                   class="small-text" min="1" max="120" placeholder="12">
                        </div>

                        <div class="aura-inv-field" id="cat-hours-wrap">
                            <label for="cat-interval-hours" class="aura-inv-label">
                                <?php _e( 'Cada N horas de uso', 'aura-suite' ); ?>
                            </label>
                            <input type="number" id="cat-interval-hours" name="interval_hours"
                                   class="small-text" min="1" max="9999" placeholder="250">
                        </div>
                    </div>
                </div>

                <div class="aura-inv-form-actions">
                    <button type="submit" class="button button-primary" id="js-btn-add-cat">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e( 'Agregar categoría', 'aura-suite' ); ?>
                    </button>
                </div>
            </form>
        </div>

    </div><!-- /tab categories -->

    <!-- ══════════════════════════════════════════════════════
         TAB 2: CONFIGURACIÓN GENERAL
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-inv-tab-panel" data-panel="general" style="display:none;">

        <div class="aura-inv-card">
            <h2><?php _e( 'Configuración General', 'aura-suite' ); ?></h2>
            <p class="description"><?php _e( 'Parámetros de funcionamiento del módulo de inventario.', 'aura-suite' ); ?></p>

            <div id="js-general-msg" class="aura-inv-notice" style="display:none;"></div>

            <form id="js-form-settings" novalidate>
                <table class="form-table aura-inv-settings-table" role="presentation">

                    <!-- Items por página -->
                    <tr>
                        <th scope="row">
                            <label for="set-items-per-page"><?php _e( 'Equipos por página', 'aura-suite' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="set-items-per-page" name="items_per_page"
                                   class="small-text" min="5" max="100"
                                   value="<?php echo esc_attr( $settings['items_per_page'] ); ?>">
                            <p class="description"><?php _e( 'Número de registros por página en los listados (5–100).', 'aura-suite' ); ?></p>
                        </td>
                    </tr>

                    <!-- Días de alerta antes -->
                    <tr>
                        <th scope="row">
                            <label for="set-alert-days"><?php _e( 'Días de aviso para mantenimiento', 'aura-suite' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="set-alert-days" name="alert_days_before"
                                   class="small-text" min="1" max="60"
                                   value="<?php echo esc_attr( $settings['alert_days_before'] ); ?>">
                            <p class="description"><?php _e( 'Con cuántos días de anticipación se activa la alerta de mantenimiento próximo.', 'aura-suite' ); ?></p>
                        </td>
                    </tr>

                    <!-- Días máximos de préstamo -->
                    <tr>
                        <th scope="row">
                            <label for="set-loan-max"><?php _e( 'Días máximos de préstamo', 'aura-suite' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="set-loan-max" name="loan_max_days"
                                   class="small-text" min="1" max="365"
                                   value="<?php echo esc_attr( $settings['loan_max_days'] ); ?>">
                            <p class="description"><?php _e( 'Número de días por defecto para la devolución esperada al registrar un préstamo.', 'aura-suite' ); ?></p>
                        </td>
                    </tr>

                    <!-- Mostrar equipos retirados -->
                    <tr>
                        <th scope="row">
                            <?php _e( 'Mostrar equipos retirados', 'aura-suite' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_retired" id="set-show-retired"
                                       value="1" <?php checked( $settings['show_retired'] ); ?>>
                                <?php _e( 'Incluir equipos con estado "Retirado" en los listados', 'aura-suite' ); ?>
                            </label>
                            <p class="description"><?php _e( 'Si está desactivado, los equipos retirados se ocultan por defecto en los listados.', 'aura-suite' ); ?></p>
                        </td>
                    </tr>

                    <!-- Símbolo de moneda -->
                    <tr>
                        <th scope="row">
                            <label for="set-currency"><?php _e( 'Símbolo de moneda', 'aura-suite' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="set-currency" name="currency_symbol"
                                   class="small-text"  maxlength="5"
                                   value="<?php echo esc_attr( $settings['currency_symbol'] ); ?>">
                            <select name="currency_position" id="set-currency-pos" style="margin-left:8px;">
                                <option value="before" <?php selected( $settings['currency_position'], 'before' ); ?>>
                                    <?php _e( 'Antes del valor ($100)', 'aura-suite' ); ?>
                                </option>
                                <option value="after" <?php selected( $settings['currency_position'], 'after' ); ?>>
                                    <?php _e( 'Después del valor (100$)', 'aura-suite' ); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e( 'Se usa en reportes, fichas y exportaciones del módulo.', 'aura-suite' ); ?></p>
                        </td>
                    </tr>

                </table>

                <div class="aura-inv-form-actions">
                    <button type="submit" class="button button-primary" id="js-btn-save-general">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e( 'Guardar configuración', 'aura-suite' ); ?>
                    </button>
                </div>
            </form>
        </div>

    </div><!-- /tab general -->

    <!-- ══════════════════════════════════════════════════════
         TAB 3: NOTIFICACIONES
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-inv-tab-panel" data-panel="notifications" style="display:none;">

        <div class="aura-inv-card">
            <h2><?php _e( 'Notificaciones por Correo', 'aura-suite' ); ?></h2>
            <p class="description">
                <?php _e( 'Configure qué alertas se envían por correo y a qué destinatarios.', 'aura-suite' ); ?>
            </p>

            <div id="js-notif-msg" class="aura-inv-notice" style="display:none;"></div>

            <form id="js-form-notifications" novalidate>
                <table class="form-table aura-inv-settings-table" role="presentation">

                    <!-- Alertas habilitadas -->
                    <tr>
                        <th scope="row"><?php _e( 'Alertas de mantenimiento', 'aura-suite' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_alerts" id="set-email-alerts"
                                       value="1" <?php checked( $settings['email_alerts'] ); ?>>
                                <?php _e( 'Enviar correo cuando un equipo se acerque a su fecha de mantenimiento', 'aura-suite' ); ?>
                            </label>
                            <p class="description">
                                <?php printf(
                                    __( 'Los correos se envían al usuario responsable del equipo, a los administradores y la dirección de correo del sitio (%s).', 'aura-suite' ),
                                    '<strong>' . esc_html( get_option( 'admin_email' ) ) . '</strong>'
                                ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Destinatarios adicionales -->
                    <tr>
                        <th scope="row">
                            <label for="set-email-extra"><?php _e( 'Correos adicionales', 'aura-suite' ); ?></label>
                        </th>
                        <td>
                            <textarea id="set-email-extra" name="email_extra"
                                      class="large-text" rows="3"
                                      placeholder="uno@ejemplo.com&#10;otro@ejemplo.com"><?php echo esc_textarea( $settings['email_extra'] ); ?></textarea>
                            <p class="description"><?php _e( 'Un correo por línea. Se agregarán a todos los envíos de alerta del módulo.', 'aura-suite' ); ?></p>
                        </td>
                    </tr>

                    <!-- Info: tipos de alerta -->
                    <tr>
                        <th scope="row"><?php _e( 'Tipos de alerta', 'aura-suite' ); ?></th>
                        <td>
                            <div class="aura-inv-alert-types">
                                <div class="aura-inv-alert-item aura-inv-alert-info">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <div>
                                        <strong><?php _e( 'Mantenimiento próximo', 'aura-suite' ); ?></strong>
                                        <p><?php printf( __( 'Aviso %d días antes de la fecha programada.', 'aura-suite' ), esc_html( $settings['alert_days_before'] ) ); ?></p>
                                    </div>
                                </div>
                                <div class="aura-inv-alert-item aura-inv-alert-warning">
                                    <span class="dashicons dashicons-warning"></span>
                                    <div>
                                        <strong><?php _e( 'Mantenimiento vencido', 'aura-suite' ); ?></strong>
                                        <p><?php _e( 'Aviso el día en que se venció la fecha de mantenimiento.', 'aura-suite' ); ?></p>
                                    </div>
                                </div>
                                <div class="aura-inv-alert-item aura-inv-alert-danger">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <div>
                                        <strong><?php _e( 'Préstamo vencido', 'aura-suite' ); ?></strong>
                                        <p><?php _e( 'Aviso cuando un préstamo no fue devuelto en la fecha esperada.', 'aura-suite' ); ?></p>
                                    </div>
                                </div>
                                <div class="aura-inv-alert-item aura-inv-alert-danger">
                                    <span class="dashicons dashicons-flag"></span>
                                    <div>
                                        <strong><?php _e( 'Equipo dañado en préstamo', 'aura-suite' ); ?></strong>
                                        <p><?php _e( 'Aviso cuando se registra un checkin con estado "dañado".', 'aura-suite' ); ?></p>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                </table>

                <div class="aura-inv-form-actions" style="gap:12px;flex-wrap:wrap;">
                    <button type="submit" class="button button-primary" id="js-btn-save-notif">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e( 'Guardar notificaciones', 'aura-suite' ); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="js-btn-test-email" style="background:#f0f6fc;border-color:#2c6e9e;color:#2c6e9e;">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php _e( 'Enviar correo de prueba', 'aura-suite' ); ?>
                    </button>
                </div>
                <p class="description" style="margin-top:8px;">
                    <?php _e( 'El correo de prueba se envía inmediatamente a todos los destinatarios configurados (admin + correos adicionales), sin condiciones de horario.', 'aura-suite' ); ?>
                </p>
            </form>
        </div>

        <!-- ── WhatsApp — ahora en Ajustes Globales ─────────── -->
        <div class="aura-inv-card" style="margin-top:24px;">
            <h2>
                <span class="dashicons dashicons-smartphone" style="color:#25d366;vertical-align:middle;margin-right:6px;"></span>
                <?php _e( 'WhatsApp para Prestatarios Externos', 'aura-suite' ); ?>
            </h2>
            <div class="notice notice-warning inline" style="margin:0;padding:12px 16px;">
                <p style="margin:0;">
                    <span class="dashicons dashicons-migrate" style="color:#f0a500;vertical-align:middle;"></span>
                    <strong><?php _e( 'Configuración movida a Ajustes Globales', 'aura-suite' ); ?></strong><br>
                    <?php _e( 'La integración de WhatsApp ahora se configura en un solo lugar para todo el sistema.', 'aura-suite' ); ?>
                    &nbsp;<a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-settings' ) ); ?>" class="button button-secondary" style="margin-top:8px;display:inline-block;">
                        <span class="dashicons dashicons-admin-settings" style="vertical-align:middle;"></span>
                        <?php _e( 'Ir a Ajustes → WhatsApp', 'aura-suite' ); ?>
                    </a>
                </p>
            </div>
        </div>

    </div><!-- /tab notifications -->

    <!-- ══════════════════════════════════════════════════════
         TAB 4: GOOGLE CALENDAR
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-inv-tab-panel" data-panel="gcal" style="display:none;">

        <div class="aura-inv-card">
            <h2>
                <span class="dashicons dashicons-calendar-alt" style="color:#4285f4;vertical-align:middle;"></span>
                <?php _e( 'Integración Google Calendar', 'aura-suite' ); ?>
            </h2>

            <!-- Aviso: configuración movida a ajustes globales -->
            <div class="notice notice-info inline" style="margin:0 0 20px;padding:12px 16px;">
                <p style="margin:0;">
                    <span class="dashicons dashicons-migrate" style="color:#2271b1;vertical-align:middle;"></span>
                    <strong><?php _e( 'Configuración movida a Ajustes Globales', 'aura-suite' ); ?></strong><br>
                    <?php _e( 'La integración con Google Calendar (Service Account JSON, correos, recordatorios) ahora se configura en Ajustes → Aura Business Suite → Google Calendar.', 'aura-suite' ); ?>
                    &nbsp;<a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-settings' ) ); ?>"
                             class="button button-secondary" style="margin-top:8px;display:inline-block;">
                        <span class="dashicons dashicons-admin-settings" style="vertical-align:middle;"></span>
                        <?php _e( 'Ir a Ajustes → Google Calendar', 'aura-suite' ); ?>
                    </a>
                </p>
            </div>

            <!-- Estado del calendario activo -->
            <?php
            $gcal_resolved  = get_option( Aura_Inventory_Google_Calendar::CAL_ID_OPTION, '' );
            $gcal_share_url = $gcal_resolved
                ? 'https://calendar.google.com/calendar/render?cid=' . rawurlencode( $gcal_resolved )
                : '';
            ?>
            <?php if ( Aura_Inventory_Google_Calendar::is_enabled() && $gcal_resolved ) : ?>
            <div style="background:#eaf4fb;border:1px solid #2c6e9e;padding:14px;border-radius:4px;margin-bottom:20px;">
                <p style="margin:0 0 10px;">
                    <span class="dashicons dashicons-yes-alt" style="color:#27ae60;"></span>
                    <strong><?php echo esc_html( Aura_Inventory_Google_Calendar::CALENDAR_NAME ); ?></strong>
                    &nbsp;<span style="color:#666;font-size:12px;"><?php _e( 'activo y configurado', 'aura-suite' ); ?></span>
                </p>
                <a href="<?php echo esc_url( $gcal_share_url ); ?>" target="_blank" rel="noopener"
                   style="display:inline-flex;align-items:center;gap:6px;background:#4285f4;color:#fff;border:none;padding:7px 14px;border-radius:4px;text-decoration:none;font-weight:600;font-size:13px;">
                    <span class="dashicons dashicons-calendar-alt" style="margin:0;"></span>
                    <?php _e( 'Agregar a mi Google Calendar', 'aura-suite' ); ?>
                </a>
            </div>
            <?php elseif ( ! Aura_Inventory_Google_Calendar::is_enabled() ) : ?>
            <div class="notice notice-warning inline" style="margin:0 0 20px;padding:10px 14px;">
                <p style="margin:0;">
                    <span class="dashicons dashicons-warning" style="color:#f0a500;"></span>
                    <?php _e( 'La integración no está activa. Configúrala en Ajustes globales.', 'aura-suite' ); ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- Diagnóstico: último sync -->
            <?php
            $last_sync = get_option( 'aura_gcal_last_sync_status', '' );
            if ( $last_sync ) :
                $is_ok     = ( strpos( $last_sync, 'ok:' ) === 0 );
                $sync_info = $is_ok ? substr( $last_sync, 3 ) : substr( $last_sync, 6 );
            ?>
            <p style="font-size:13px;color:<?php echo $is_ok ? '#27ae60' : '#c0392b'; ?>">
                <span class="dashicons <?php echo $is_ok ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                <?php _e( 'Último sync:', 'aura-suite' ); ?>
                <strong><?php echo esc_html( $sync_info ); ?></strong>
            </p>
            <?php endif; ?>

            <!-- Acción de resincronización masiva -->
            <div id="js-gcal-resync-msg" class="aura-inv-notice" style="display:none;margin-bottom:12px;"></div>
            <div class="aura-inv-form-actions">
                <button type="button" class="button button-secondary" id="js-btn-gcal-resync"
                        style="background:#f0f6fc;border-color:#2c6e9e;color:#2c6e9e;">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e( 'Resincronizar todos los equipos con Google Calendar', 'aura-suite' ); ?>
                </button>
            </div>
            <p class="description" style="margin-top:8px;">
                <?php _e( 'Crea o actualiza en Google Calendar los eventos de mantenimiento de todos los equipos que tienen fecha programada.', 'aura-suite' ); ?>
            </p>
        </div>

    </div><!-- /tab gcal -->

    <!-- ══════════════════════════════════════════════════════
         TAB 5: FINANZAS
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-inv-tab-panel" data-panel="finanzas" style="display:none;">

        <div class="aura-inv-card">
            <h2>
                <span class="dashicons dashicons-money-alt" style="color:#27ae60;vertical-align:middle;"></span>
                <?php _e( 'Integración con Finanzas', 'aura-suite' ); ?>
            </h2>
            <p class="description">
                <?php _e( 'Configure cómo los registros de mantenimiento externo se registran automáticamente en el módulo de Finanzas.', 'aura-suite' ); ?>
            </p>

            <div id="js-finanzas-msg" class="aura-inv-notice" style="display:none;"></div>

            <?php if ( empty( $finance_cats_settings ) ) : ?>
            <div class="notice notice-warning inline" style="margin:0 0 20px;padding:12px 16px;">
                <p style="margin:0;">
                    <span class="dashicons dashicons-warning" style="color:#f0a500;"></span>
                    <?php _e( 'No se encontraron categorías financieras de tipo gasto. Asegúrese de que el módulo de Finanzas esté activo y tenga categorías configuradas.', 'aura-suite' ); ?>
                </p>
            </div>
            <?php endif; ?>

            <form id="js-form-finanzas" novalidate>
                <table class="form-table aura-inv-settings-table" role="presentation">

                    <!-- Categoría financiera por defecto -->
                    <tr>
                        <th scope="row">
                            <label for="set-finance-cat"><?php _e( 'Categoría de gasto por defecto', 'aura-suite' ); ?></label>
                        </th>
                        <td>
                            <select id="set-finance-cat" name="finance_category_id" class="regular-text">
                                <option value="0"><?php _e( '— Sin categoría por defecto —', 'aura-suite' ); ?></option>
                                <?php foreach ( $finance_cats_settings as $fc ) : ?>
                                    <option value="<?php echo esc_attr( $fc->id ); ?>"
                                        <?php selected( $settings['finance_category_id'], $fc->id ); ?>>
                                        <?php echo esc_html( $fc->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Al crear un mantenimiento con costo, esta categoría se pre-seleccionará en el formulario. El usuario puede cambiarla antes de guardar.', 'aura-suite' ); ?></p>
                        </td>
                    </tr>

                    <!-- Estado de la transacción -->
                    <tr>
                        <th scope="row">
                            <label for="set-auto-approve"><?php _e( 'Estado de la transacción creada', 'aura-suite' ); ?></label>
                        </th>
                        <td>
                            <select id="set-auto-approve" name="auto_approve_transactions" class="regular-text">
                                <option value="approved" <?php selected( $settings['auto_approve_transactions'], 'approved' ); ?>>
                                    <?php _e( 'Aprobada automáticamente', 'aura-suite' ); ?>
                                </option>
                                <option value="pending" <?php selected( $settings['auto_approve_transactions'], 'pending' ); ?>>
                                    <?php _e( 'Pendiente de aprobación', 'aura-suite' ); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e( 'Si se elige "Pendiente", la transacción aparecerá en la cola de aprobaciones del módulo de Finanzas antes de registrarse definitivamente.', 'aura-suite' ); ?></p>
                        </td>
                    </tr>

                </table>

                <div class="aura-inv-form-actions">
                    <button type="submit" class="button button-primary" id="js-btn-save-finanzas">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e( 'Guardar configuración de finanzas', 'aura-suite' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Información de la integración -->
        <div class="aura-inv-card" style="margin-top:24px;">
            <h3><?php _e( 'Cómo funciona la integración', 'aura-suite' ); ?></h3>
            <div class="aura-inv-alert-types">
                <div class="aura-inv-alert-item aura-inv-alert-info">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <div>
                        <strong><?php _e( 'Mantenimiento externo con costo', 'aura-suite' ); ?></strong>
                        <p><?php _e( 'Al registrar un mantenimiento externo con costo mayor a cero, se muestra una opción para crear automáticamente un gasto en el módulo de Finanzas.', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <div class="aura-inv-alert-item aura-inv-alert-info">
                    <span class="dashicons dashicons-admin-links"></span>
                    <div>
                        <strong><?php _e( 'Vínculo permanente', 'aura-suite' ); ?></strong>
                        <p><?php _e( 'El ID de la transacción financiera se guarda en el registro de mantenimiento. Cada mantenimiento solo puede tener un gasto vinculado (solo en la creación).', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <div class="aura-inv-alert-item aura-inv-alert-warning">
                    <span class="dashicons dashicons-visibility"></span>
                    <div>
                        <strong><?php _e( 'Visible en Finanzas', 'aura-suite' ); ?></strong>
                        <p><?php _e( 'Los gastos creados desde inventario aparecen en el listado de transacciones del módulo de Finanzas marcados como provenientes de Inventario.', 'aura-suite' ); ?></p>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /tab finanzas -->

<!-- ══════════════════════════════════════════════════════
     MODAL — EDITAR CATEGORÍA
     ═══════════════════════════════════════════════════════ -->
<div id="js-modal-edit-cat" class="aura-inv-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="js-edit-cat-title">
    <div class="aura-inv-modal-box">
        <div class="aura-inv-modal-header">
            <h2 id="js-edit-cat-title">
                <span class="dashicons dashicons-edit"></span>
                <?php _e( 'Editar categoría', 'aura-suite' ); ?>
            </h2>
            <button type="button" class="aura-inv-modal-close" id="js-edit-cat-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <div class="aura-inv-modal-body">
            <div id="js-edit-cat-msg" class="aura-inv-notice" style="display:none;"></div>

            <form id="js-form-edit-category" novalidate>
                <input type="hidden" id="edit-cat-term-id" name="term_id">

                <div class="aura-inv-field-group">
                    <div class="aura-inv-field">
                        <label for="edit-cat-name"><?php _e( 'Nombre *', 'aura-suite' ); ?></label>
                        <input type="text" id="edit-cat-name" name="name" class="regular-text" required>
                    </div>
                    <div class="aura-inv-field">
                        <label for="edit-cat-slug"><?php _e( 'Slug', 'aura-suite' ); ?></label>
                        <input type="text" id="edit-cat-slug" name="slug" class="regular-text">
                        <p class="description"><?php _e( 'Identificador URL. Se genera solo si lo dejas vacío.', 'aura-suite' ); ?></p>
                    </div>
                </div>

                <div class="aura-inv-field">
                    <label for="edit-cat-description"><?php _e( 'Descripción', 'aura-suite' ); ?></label>
                    <textarea id="edit-cat-description" name="description" class="large-text" rows="2"></textarea>
                </div>

                <div class="aura-inv-field">
                    <label for="edit-cat-interval-type"><?php _e( 'Intervalo de mantenimiento', 'aura-suite' ); ?></label>
                    <select id="edit-cat-interval-type" name="interval_type">
                        <option value="none"><?php _e( 'Sin programa de mantenimiento', 'aura-suite' ); ?></option>
                        <option value="time"><?php _e( 'Por tiempo (meses)', 'aura-suite' ); ?></option>
                        <option value="hours"><?php _e( 'Por horas de uso', 'aura-suite' ); ?></option>
                        <option value="both"><?php _e( 'Tiempo + horas', 'aura-suite' ); ?></option>
                    </select>
                </div>

                <div class="aura-inv-field-group" id="edit-cat-months-wrap" style="display:none;">
                    <div class="aura-inv-field">
                        <label for="edit-cat-interval-months"><?php _e( 'Cada cuántos meses', 'aura-suite' ); ?></label>
                        <input type="number" id="edit-cat-interval-months" name="interval_months" min="1" max="120" class="small-text">
                    </div>
                </div>

                <div class="aura-inv-field-group" id="edit-cat-hours-wrap" style="display:none;">
                    <div class="aura-inv-field">
                        <label for="edit-cat-interval-hours"><?php _e( 'Cada cuántas horas', 'aura-suite' ); ?></label>
                        <input type="number" id="edit-cat-interval-hours" name="interval_hours" min="1" max="10000" class="small-text">
                    </div>
                </div>

                <div class="aura-inv-modal-footer">
                    <button type="button" class="button" id="js-edit-cat-close-btn"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
                    <button type="submit" class="button button-primary" id="js-btn-update-cat">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e( 'Guardar cambios', 'aura-suite' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</div><!-- .wrap -->

<script>
var auraInvSettings = {
    ajaxurl: <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
    nonce:   <?php echo json_encode( $nonce ); ?>,
    txt: {
        confirm_delete:  <?php echo json_encode( __( '¿Eliminar esta categoría? (Solo si no tiene equipos asignados)', 'aura-suite' ) ); ?>,
        error_name:      <?php echo json_encode( __( 'El nombre es obligatorio.', 'aura-suite' ) ); ?>,
        saving:          <?php echo json_encode( __( 'Guardando…', 'aura-suite' ) ); ?>,
        installing:      <?php echo json_encode( __( 'Instalando…', 'aura-suite' ) ); ?>,
        adding:          <?php echo json_encode( __( 'Agregando…', 'aura-suite' ) ); ?>,
    },
    interval_labels: <?php echo json_encode( $interval_labels ); ?>,
    have_categories: <?php echo json_encode( ! empty( $categories ) ); ?>,
};
</script>
