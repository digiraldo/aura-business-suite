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

        <div style="display: flex; justify-content: flex-end; margin-bottom: 12px;">
            <button type="button" class="aura-theme-toggle" aria-label="<?php esc_attr_e( 'Cambiar tema', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-moon"></span>
                <span class="aura-theme-toggle-label"><?php esc_html_e( 'Modo oscuro', 'aura-suite' ); ?></span>
            </button>
        </div>

        <div class="aura-login-header">
            <?php
            $logo = get_option( 'aura_org_logo_url', '' );
            if ( $logo ) {
                echo '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_option( 'blogname' ) ) . '" class="aura-login-logo" />';
            } else {
                echo '<div style="width: 54px; height: 54px; margin: 0 auto 12px; border-radius: 12px; background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);"><span class="dashicons dashicons-businessperson" style="font-size: 28px; width: 28px; height: 28px;"></span></div>';
                echo '<h2 class="aura-login-site-name">' . esc_html( get_option( 'blogname' ) ) . '</h2>';
            }
            ?>
            <p class="aura-login-subtitle">
                <?php esc_html_e( 'Portal Institucional — Acceso', 'aura-suite' ); ?>
            </p>
        </div>

        <div id="aura-login-notice" class="aura-login-notice" style="display:none;"></div>

        <form id="aura-login-form" class="aura-login-form" novalidate style="display: flex; flex-direction: column;">

            <div class="mb-3">
                <label class="form-label" for="aura-login-user"><?php esc_html_e( 'Correo electrónico o usuario', 'aura-suite' ); ?></label>
                <div class="input-group">
                    <span class="input-group-text">
                        <span class="dashicons dashicons-admin-users"></span>
                    </span>
                    <input
                        type="text"
                        id="aura-login-user"
                        name="username"
                        autocomplete="username"
                        placeholder="<?php esc_attr_e( 'nombre@institucion.org', 'aura-suite' ); ?>"
                        class="form-control"
                        required
                    />
                </div>
            </div>

            <div class="mb-3">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label class="form-label" for="aura-login-pass" style="margin: 0;"><?php esc_html_e( 'Contraseña', 'aura-suite' ); ?></label>
                    <a class="aura-forgot-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>" style="color: var(--aura-indigo, #4f46e5); text-decoration: none; font-size: 12px; font-weight: 500;">
                        <?php esc_html_e( '¿Olvidaste la contraseña?', 'aura-suite' ); ?>
                    </a>
                </div>
                <div class="input-group">
                    <span class="input-group-text">
                        <span class="dashicons dashicons-lock"></span>
                    </span>
                    <input
                        type="password"
                        id="aura-login-pass"
                        name="password"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="form-control"
                        required
                    />
                    <button type="button" class="input-group-text aura-btn-toggle-pass aura-toggle-pass" aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'aura-suite' ); ?>" title="<?php esc_attr_e( 'Mostrar contraseña', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                </div>
            </div>

            <div class="mb-3" style="display: flex; align-items: center; justify-content: space-between; font-size: 13px;">
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--aura-text-secondary, #475569);">
                    <input type="checkbox" name="remember" id="aura-remember" value="1" style="border-radius: 4px;" />
                    <span><?php esc_html_e( 'Recordar sesión', 'aura-suite' ); ?></span>
                </label>
            </div>

            <input type="hidden" name="redirect" value="<?php echo esc_attr( $redirect_url ); ?>" />

            <button type="submit" id="aura-login-btn" class="btn btn-indigo btn-shimmer btn-lift aura-btn aura-btn-primary aura-btn-full" style="width: 100%; padding: 12px 20px; font-size: 14px; font-weight: 600; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                <span><?php esc_html_e( 'Ingresar al portal', 'aura-suite' ); ?></span>
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </button>

        </form>

    </div><!-- /aura-login-card -->
</div><!-- /aura-portal-wrap -->
