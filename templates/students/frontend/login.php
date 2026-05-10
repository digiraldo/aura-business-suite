<?php
/**
 * Template: Login del Portal de Estudiantes
 * Usado por shortcode [aura_student_login]
 *
 * Variables disponibles:
 *  $redirect_url  — URL de redirección tras login exitoso
 *  $nonce         — Nonce ya generado
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="aura-portal-wrap aura-login-wrap">
    <div class="aura-login-card">

        <div class="aura-login-header">
            <?php
            $logo = get_option( 'aura_org_logo_url', '' );
            if ( $logo ) {
                echo '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_option( 'blogname' ) ) . '" class="aura-login-logo" />';
            } else {
                echo '<h2 class="aura-login-site-name">' . esc_html( get_option( 'blogname' ) ) . '</h2>';
            }
            ?>
            <p class="aura-login-subtitle">
                <?php esc_html_e( 'Portal de Estudiantes — Acceso', 'aura-suite' ); ?>
            </p>
        </div>

        <div id="aura-login-notice" class="aura-login-notice" style="display:none;"></div>

        <form id="aura-login-form" class="aura-login-form" novalidate>

            <div class="aura-field-group">
                <label for="aura-login-user"><?php esc_html_e( 'Correo electrónico', 'aura-suite' ); ?></label>
                <input
                    type="email"
                    id="aura-login-user"
                    name="username"
                    autocomplete="username"
                    placeholder="<?php esc_attr_e( 'tu@correo.com', 'aura-suite' ); ?>"
                    class="aura-input aura-input-full"
                    required
                />
            </div>

            <div class="aura-field-group">
                <label for="aura-login-pass">
                    <?php esc_html_e( 'Contraseña', 'aura-suite' ); ?>
                    <a class="aura-forgot-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
                        <?php esc_html_e( '¿Olvidaste la contraseña?', 'aura-suite' ); ?>
                    </a>
                </label>
                <div class="aura-pass-wrap">
                    <input
                        type="password"
                        id="aura-login-pass"
                        name="password"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="aura-input aura-input-full"
                        required
                    />
                    <button type="button" class="aura-toggle-pass" aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'aura-suite' ); ?>">
                        👁
                    </button>
                </div>
            </div>

            <div class="aura-field-inline">
                <label>
                    <input type="checkbox" name="remember" id="aura-remember" value="1" />
                    <?php esc_html_e( 'Recordar sesión', 'aura-suite' ); ?>
                </label>
            </div>

            <input type="hidden" name="redirect" value="<?php echo esc_attr( $redirect_url ); ?>" />

            <button type="submit" id="aura-login-btn" class="aura-btn aura-btn-primary aura-btn-full">
                <?php esc_html_e( 'Ingresar al portal', 'aura-suite' ); ?>
            </button>

        </form>

    </div><!-- /aura-login-card -->
</div><!-- /aura-portal-wrap -->
