<?php
/**
 * Configuración — Módulo de Formularios (Fase 8)
 *
 * Opciones globales del módulo almacenadas en wp_options
 * bajo la clave Aura_Forms_Settings::OPTION_KEY.
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_forms_settings' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

$settings = array_merge(
    Aura_Forms_Settings::defaults(),
    get_option( Aura_Forms_Settings::OPTION_KEY, [] )
);

$nonce = wp_create_nonce( 'aura_forms_nonce' );

// Todas las páginas WP publicadas (para los selects)
$all_pages = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title' ] );
?>
<div class="wrap aura-forms-wrap">

    <h1><?php esc_html_e( 'Configuración — Formularios', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">

    <div id="settings-notice" class="notice" style="display:none;"></div>

    <form id="aura-forms-settings-form" method="post">

        <!-- ── INTEGRACIÓN FRONTEND ── -->
        <div class="card aura-settings-card">
            <h2 class="title"><?php esc_html_e( 'Integración Frontend', 'aura-suite' ); ?></h2>
            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row">
                        <label for="public_form_page"><?php esc_html_e( 'Página de formulario público', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <select name="public_form_page" id="public_form_page" class="regular-text">
                            <option value="0"><?php esc_html_e( '— Ninguna —', 'aura-suite' ); ?></option>
                            <?php foreach ( $all_pages as $page ) : ?>
                                <option value="<?php echo esc_attr( $page->ID ); ?>"
                                    <?php selected( (int) $settings['public_form_page'], $page->ID ); ?>>
                                    <?php echo esc_html( $page->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Página de respaldo con el shortcode [aura_form id="X"]. Los formularios públicos funcionan directamente en', 'aura-suite' ); ?>
                            <code>/formulario/<em>slug</em>/</code>
                            <?php esc_html_e( 'sin necesitar esta página.', 'aura-suite' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="portal_page"><?php esc_html_e( 'Página del portal de formularios', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <select name="portal_page" id="portal_page" class="regular-text">
                            <option value="0"><?php esc_html_e( '— Ninguna —', 'aura-suite' ); ?></option>
                            <?php foreach ( $all_pages as $page ) : ?>
                                <option value="<?php echo esc_attr( $page->ID ); ?>"
                                    <?php selected( (int) $settings['portal_page'], $page->ID ); ?>>
                                    <?php echo esc_html( $page->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Página con el shortcode [aura_form_portal], donde el estudiante ve sus encuestas asignadas. La URL de esta página se usa en emails de asignación mediante la variable', 'aura-suite' ); ?>
                            <code>{portal}</code>.
                        </p>
                    </td>
                </tr>

            </table>
        </div><!-- .aura-settings-card -->

        <!-- ── ANTI-SPAM Y SEGURIDAD ── -->
        <div class="card aura-settings-card">
            <h2 class="title"><?php esc_html_e( 'Anti-Spam y Seguridad', 'aura-suite' ); ?></h2>
            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row"><?php esc_html_e( 'Honeypot anti-spam', 'aura-suite' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="honeypot_enabled" value="1"
                                <?php checked( $settings['honeypot_enabled'], '1' ); ?>>
                            <?php esc_html_e( 'Activar campo señuelo (honeypot) para bloquear bots automáticamente', 'aura-suite' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Límite de envíos por IP', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <?php esc_html_e( 'Máximo', 'aura-suite' ); ?>
                            <input type="number" name="rate_limit_count" id="rate_limit_count"
                                   value="<?php echo esc_attr( $settings['rate_limit_count'] ); ?>"
                                   min="1" max="100" class="small-text">
                            <?php esc_html_e( 'envíos en', 'aura-suite' ); ?>
                            <input type="number" name="rate_limit_minutes" id="rate_limit_minutes"
                                   value="<?php echo esc_attr( $settings['rate_limit_minutes'] ); ?>"
                                   min="1" max="1440" class="small-text">
                            <?php esc_html_e( 'minutos por formulario', 'aura-suite' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Protege contra envíos masivos desde la misma dirección IP.', 'aura-suite' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Directorio de archivos subidos', 'aura-suite' ); ?></th>
                    <td>
                        <code>wp-content/uploads/aura-forms/{form_id}/{año}/{mes}/</code>
                        <p class="description"><?php esc_html_e( 'Los archivos subidos por los participantes se guardan en esta ruta (no configurable).', 'aura-suite' ); ?></p>
                    </td>
                </tr>

            </table>
        </div><!-- .aura-settings-card -->

        <!-- ── NOTIFICACIONES ── -->
        <div class="card aura-settings-card">
            <h2 class="title"><?php esc_html_e( 'Notificaciones', 'aura-suite' ); ?></h2>
            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row">
                        <label for="admin_notification_email"><?php esc_html_e( 'Email de notificación al admin', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <input type="email" name="admin_notification_email" id="admin_notification_email"
                               value="<?php echo esc_attr( $settings['admin_notification_email'] ); ?>"
                               class="regular-text">
                        <p class="description"><?php esc_html_e( 'Email que recibe un aviso cada vez que llega una nueva respuesta (se puede sobreescribir por formulario).', 'aura-suite' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="assignment_email_subject"><?php esc_html_e( 'Asunto del correo de asignación', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="assignment_email_subject" id="assignment_email_subject"
                               value="<?php echo esc_attr( $settings['assignment_email_subject'] ); ?>"
                               class="large-text">
                        <p class="description"><?php esc_html_e( 'Asunto del email que recibe el estudiante al asignársele una encuesta.', 'aura-suite' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="assignment_email_body"><?php esc_html_e( 'Cuerpo del correo de asignación', 'aura-suite' ); ?></label>
                    </th>
                    <td>
                        <textarea name="assignment_email_body" id="assignment_email_body"
                                  rows="6" class="large-text"><?php echo esc_textarea( $settings['assignment_email_body'] ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Variables disponibles:', 'aura-suite' ); ?>
                            <code>{nombre}</code>, <code>{formulario}</code>, <code>{url}</code> (URL del formulario específico),
                            <code>{portal}</code> (URL del portal del estudiante), <code>{expira}</code>, <code>{sitio}</code>
                        </p>
                    </td>
                </tr>

            </table>
        </div><!-- .aura-settings-card -->

        <!-- ── HERRAMIENTAS ── -->
        <div class="card aura-settings-card">
            <h2 class="title"><?php esc_html_e( 'Herramientas', 'aura-suite' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'URLs públicas de formularios', 'aura-suite' ); ?></th>
                    <td>
                        <button type="button" id="flush-rewrites-btn" class="button">
                            <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                            <?php esc_html_e( 'Regenerar URLs (flush rewrite rules)', 'aura-suite' ); ?>
                        </button>
                        <span id="flush-spinner" class="spinner" style="float:none;vertical-align:middle;"></span>
                        <p class="description">
                            <?php esc_html_e( 'Usa este botón si la URL pública de tus formularios (ej. /formulario/mi-formulario/) devuelve error 404.', 'aura-suite' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div><!-- .aura-settings-card -->

        <!-- ── BOTÓN GUARDAR ── -->
        <p class="submit">
            <button type="submit" id="save-settings-btn" class="button button-primary button-large">
                <?php esc_html_e( 'Guardar configuración', 'aura-suite' ); ?>
            </button>
            <span id="settings-spinner" class="spinner" style="float:none;vertical-align:middle;"></span>
        </p>

    </form><!-- #aura-forms-settings-form -->

</div><!-- .wrap -->

<style>
.aura-settings-card { padding:20px 24px; margin-bottom:20px; }
.aura-settings-card .title { font-size:1rem; margin:0 0 16px; padding-bottom:8px; border-bottom:1px solid #e5e7eb; }
.aura-settings-card .form-table th { width:260px; }
</style>

<script>
(function($){
    var nonce   = '<?php echo esc_js( $nonce ); ?>';
    var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

    $('#aura-forms-settings-form').on('submit', function(e){
        e.preventDefault();

        var $btn     = $('#save-settings-btn').prop('disabled', true);
        var $spinner = $('#settings-spinner').addClass('is-active');
        var $notice  = $('#settings-notice').hide().removeClass('notice-success notice-error');

        var data = $(this).serializeArray();
        data.push({ name: 'action', value: 'aura_forms_save_settings' });
        data.push({ name: 'nonce',  value: nonce });

        $.post(ajaxUrl, data, function(res){
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if(res.success){
                $notice.addClass('notice-success').show()
                    .html('<p><strong>' + (res.data || '<?php echo esc_js( __( 'Configuración guardada correctamente.', 'aura-suite' ) ); ?>') + '</strong></p>');
            } else {
                $notice.addClass('notice-error').show()
                    .html('<p>' + (res.data || '<?php echo esc_js( __( 'Error al guardar.', 'aura-suite' ) ); ?>') + '</p>');
            }

            $('html,body').animate({ scrollTop: 0 }, 300);
        }).fail(function(){
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $notice.addClass('notice-error').show()
                .html('<p><?php echo esc_js( __( 'Error de conexión. Intenta de nuevo.', 'aura-suite' ) ); ?></p>');
        });
    });

    // ── Flush rewrite rules ──
    $('#flush-rewrites-btn').on('click', function(){
        var $btn     = $(this).prop('disabled', true);
        var $spinner = $('#flush-spinner').addClass('is-active');
        var $notice  = $('#settings-notice').hide().removeClass('notice-success notice-error');

        $.post(ajaxUrl, {
            action : 'aura_forms_flush_rewrites',
            nonce  : nonce,
        }, function(res){
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            if(res.success){
                $notice.addClass('notice-success').show()
                    .html('<p><strong>' + (res.data || '') + '</strong></p>');
            } else {
                $notice.addClass('notice-error').show()
                    .html('<p>' + (res.data || '<?php echo esc_js( __( 'Error. Intenta de nuevo.', 'aura-suite' ) ); ?>') + '</p>');
            }
            $('html,body').animate({ scrollTop: 0 }, 300);
        }).fail(function(){
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });
})(jQuery);
</script>
