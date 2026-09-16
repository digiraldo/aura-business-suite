<?php
/**
 * Portales Frontend y Login Unificado — Módulo de Calendario
 *
 * Provee shortcodes para la comunidad académica:
 * - [aura_community_login]: Login inteligente unificado con redirección y botón a /wp-admin/ si tiene permisos.
 * - [aura_teacher_portal]: Portal del profesor para ver sus clases, tomar asistencia y calificar.
 * - [aura_student_schedule]: Vista de horarios y clases exclusivas del alumno.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Frontend {

    /**
     * Inicializar shortcodes y endpoints
     */
    public static function init(): void {
        add_shortcode( 'aura_login',           [ __CLASS__, 'shortcode_login' ] );
        add_shortcode( 'aura_community_login', [ __CLASS__, 'shortcode_login' ] );
        add_shortcode( 'aura_teacher_portal',   [ __CLASS__, 'shortcode_teacher_portal' ] );
        add_shortcode( 'aura_student_schedule', [ __CLASS__, 'shortcode_student_schedule' ] );
        add_action( 'wp_enqueue_scripts',       [ __CLASS__, 'enqueue_frontend_assets' ] );
    }

    /**
     * Encolar estilos y scripts para el frontend cuando se usen los shortcodes
     */
    public static function enqueue_frontend_assets(): void {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        $has_shortcode = (
            has_shortcode( $post->post_content, 'aura_login' ) ||
            has_shortcode( $post->post_content, 'aura_community_login' ) ||
            has_shortcode( $post->post_content, 'aura_student_login' ) ||
            has_shortcode( $post->post_content, 'aura_teacher_portal' ) ||
            has_shortcode( $post->post_content, 'aura_student_schedule' )
        );

        if ( ! $has_shortcode ) {
            return;
        }

        // Cargar Dashicons oficiales de WordPress para el frontend
        if ( ! wp_style_is( 'dashicons', 'enqueued' ) ) {
            wp_enqueue_style( 'dashicons' );
        }

        // Cargar Design System Base
        $ds_css = file_exists( AURA_PLUGIN_DIR . 'assets/css/aura-design-system.css' )
            ? AURA_PLUGIN_URL . 'assets/css/aura-design-system.css'
            : AURA_PLUGIN_URL . 'assets/css/design-system.css';

        if ( ! wp_style_is( 'aura-design-system', 'enqueued' ) ) {
            wp_enqueue_style(
                'aura-design-system',
                $ds_css,
                [ 'dashicons' ],
                AURA_VERSION
            );
        }

        // Cargar Estilos de Modo Oscuro y Tema en Frontend
        if ( ! wp_style_is( 'aura-frontend-dark-mode', 'enqueued' ) ) {
            wp_enqueue_style(
                'aura-frontend-dark-mode',
                AURA_PLUGIN_URL . 'assets/css/aura-frontend-dark-mode.css',
                [ 'dashicons', 'aura-design-system' ],
                AURA_VERSION
            );
        }

        if ( ! wp_script_is( 'aura-frontend-theme', 'enqueued' ) ) {
            wp_enqueue_script(
                'aura-frontend-theme',
                AURA_PLUGIN_URL . 'assets/js/aura-frontend-theme.js',
                [],
                AURA_VERSION,
                false
            );
        }

        // Cargar FullCalendar si es el portal del profesor o el horario
        if ( has_shortcode( $post->post_content, 'aura_teacher_portal' ) || has_shortcode( $post->post_content, 'aura_student_schedule' ) ) {
            wp_enqueue_script(
                'fullcalendar-bundle',
                'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js',
                [],
                '6.1.15',
                true
            );
        }

        wp_enqueue_style(
            'aura-calendar-admin',
            AURA_PLUGIN_URL . 'assets/css/calendar-admin.css',
            [ 'aura-design-system', 'aura-frontend-dark-mode' ],
            AURA_VERSION
        );

        wp_enqueue_script(
            'aura-calendar-frontend',
            AURA_PLUGIN_URL . 'assets/js/calendar-admin.js',
            [ 'jquery' ],
            AURA_VERSION,
            true
        );

        wp_localize_script( 'aura-calendar-frontend', 'auraCalData', [
            'ajax_url'            => admin_url( 'admin-ajax.php' ),
            'nonce'               => wp_create_nonce( 'aura_cal_nonce' ),
            'current_user_id'     => get_current_user_id(),
            'user_can_edit'       => false,
            'user_can_attendance' => current_user_can( 'aura_take_attendance' ) || current_user_can( 'manage_options' ),
            'user_can_grade'      => current_user_can( 'aura_record_grades' ) || current_user_can( 'manage_options' ),
            'gcal_enabled'        => Aura_Calendar_Google_Sync::is_enabled(),
            'programs'            => [],
            'teachers'            => [],
            'i18n'                => [
                'today' => __( 'Hoy', 'aura' ),
                'month' => __( 'Mes', 'aura' ),
                'week'  => __( 'Semana', 'aura' ),
                'day'   => __( 'Día', 'aura' ),
                'list'  => __( 'Agenda', 'aura' ),
            ],
        ] );
    }

    /**
     * Shortcode Maestro Unificado: [aura_login]
     * También funciona como alias para [aura_student_login] y [aura_community_login]
     *
     * @param array $atts
     * @return string
     */
    public static function shortcode_login( $atts = [] ): string {
        $atts = shortcode_atts( [
            'redirect' => '',
        ], (array) $atts, 'aura_login' );

        ob_start();

        // ── SI YA ESTÁ LOGUEADO ──
        if ( is_user_logged_in() ) {
            $user       = wp_get_current_user();
            $user_id    = $user->ID;
            $portal_url = '';

            // 1. Detectar página del portal del estudiante
            if ( class_exists( 'Aura_Students_Settings' ) ) {
                $st_page_id = (int) Aura_Students_Settings::get( 'portal_page_id' );
                if ( $st_page_id > 0 ) {
                    $portal_url = get_permalink( $st_page_id );
                }
            }
            if ( empty( $portal_url ) && class_exists( 'Aura_Students_Frontend' ) && method_exists( 'Aura_Students_Frontend', 'get_portal_page_url' ) ) {
                $portal_url = Aura_Students_Frontend::get_portal_page_url();
            }
            if ( empty( $portal_url ) ) {
                $portal_url = home_url( '/portal-estudiante/' );
            }

            // 2. Detectar página del portal del profesor / instructor
            global $wpdb;
            $teacher_portal_url = '';
            $teacher_page_id    = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE '%[aura_teacher_portal%' LIMIT 1" );
            if ( $teacher_page_id > 0 ) {
                $teacher_portal_url = get_permalink( $teacher_page_id );
            } else {
                $teacher_portal_url = home_url( '/portal-instructor/' );
            }

            // 3. Comprobar roles y capacidades
            $can_admin = (
                current_user_can( 'manage_options' ) ||
                current_user_can( 'aura_manage_calendar' ) ||
                current_user_can( 'aura_view_calendar' ) ||
                current_user_can( 'aura_manage_finances' ) ||
                current_user_can( 'aura_financial_view_reports' ) ||
                current_user_can( 'aura_inventory_view_all' ) ||
                current_user_can( 'aura_students_manage' ) ||
                current_user_can( 'edit_posts' )
            );

            $is_teacher = current_user_can( 'aura_teach_calendar' ) || current_user_can( 'manage_options' );

            // Comprobar si tiene ficha o rol de estudiante
            $student_record = null;
            if ( class_exists( 'Aura_Students_Frontend' ) && method_exists( 'Aura_Students_Frontend', 'get_student_by_wp_user' ) ) {
                $student_record = Aura_Students_Frontend::get_student_by_wp_user( $user_id );
            }
            $is_student = ( ! empty( $student_record ) ) || current_user_can( 'aura_students_view_own' ) || current_user_can( 'aura_student_portal_access' );

            // 4. Determinar si tiene Imagen de Perfil real o si mostramos Ícono Predeterminado por Rol
            $profile_photo_url = '';

            // a) Desde ficha de estudiante
            if ( ! empty( $student_record->photo_url ) ) {
                $profile_photo_url = $student_record->photo_url;
            }

            // b) Desde metadatos de usuario (profile_photo, aura_avatar_url, wp_user_avatar)
            if ( empty( $profile_photo_url ) ) {
                $meta_photo = get_user_meta( $user_id, 'profile_photo', true );
                if ( empty( $meta_photo ) ) {
                    $meta_photo = get_user_meta( $user_id, 'aura_avatar_url', true );
                }
                if ( empty( $meta_photo ) ) {
                    $wp_avatar_id = get_user_meta( $user_id, 'wp_user_avatar', true );
                    if ( is_numeric( $wp_avatar_id ) && (int) $wp_avatar_id > 0 ) {
                        $meta_photo = wp_get_attachment_image_url( (int) $wp_avatar_id, 'thumbnail' );
                    } elseif ( is_string( $wp_avatar_id ) && filter_var( $wp_avatar_id, FILTER_VALIDATE_URL ) ) {
                        $meta_photo = $wp_avatar_id;
                    }
                }
                if ( ! empty( $meta_photo ) && filter_var( $meta_photo, FILTER_VALIDATE_URL ) ) {
                    $profile_photo_url = $meta_photo;
                }
            }

            // c) Desde Gravatar / Avatar de WordPress (solo si existe avatar real registrado)
            if ( empty( $profile_photo_url ) ) {
                $avatar_data = get_avatar_data( $user_id, [ 'size' => 128, 'default' => '404' ] );
                if ( ! empty( $avatar_data['found_avatar'] ) && ! empty( $avatar_data['url'] ) ) {
                    $profile_photo_url = $avatar_data['url'];
                }
            }

            // 5. Configurar ícono predeterminado según el rol si no tiene foto de perfil
            if ( $can_admin ) {
                $role_name  = __( 'Director / Administrador', 'aura' );
                $role_badge = 'badge-emerald';
                $role_icon  = 'dashicons-businessperson';
                $icon_color = '#10b981';
                $icon_bg    = 'rgba(16, 185, 129, 0.12)';
            } elseif ( $is_teacher ) {
                $role_name  = __( 'Profesor / Instructor', 'aura' );
                $role_badge = 'badge-indigo';
                $role_icon  = 'dashicons-welcome-learn-more';
                $icon_color = '#4f46e5';
                $icon_bg    = 'rgba(79, 70, 229, 0.12)';
            } else {
                $role_name  = __( 'Estudiante', 'aura' );
                $role_badge = 'badge-violet';
                $role_icon  = 'dashicons-id-alt';
                $icon_color = '#8b5cf6';
                $icon_bg    = 'rgba(139, 92, 246, 0.12)';
            }

            // Si vino un redirect explícito y no es administrador, redirigir automáticamente
            if ( ! empty( $atts['redirect'] ) && ! $can_admin ) {
                wp_safe_redirect( $atts['redirect'] );
                exit;
            }
            ?>
            <div class="aura-portal-wrap" style="max-width: 520px; margin: 40px auto; padding: 0 16px;">
                <div class="adp-card" style="text-align: center; padding: 32px 28px; border-radius: 16px; box-shadow: var(--aura-shadow-md);">
                    <div style="display: flex; justify-content: flex-end; margin-bottom: 8px;">
                        <button type="button" class="aura-theme-toggle" aria-label="<?php esc_attr_e( 'Cambiar tema', 'aura' ); ?>">
                            <span class="dashicons dashicons-moon"></span>
                            <span class="aura-theme-toggle-label"><?php esc_html_e( 'Modo oscuro', 'aura' ); ?></span>
                        </button>
                    </div>

                    <!-- AVATAR DE PERFIL O ÍCONO PREDETERMINADO SEGÚN ROL -->
                    <div style="margin: 0 auto 14px; display: flex; justify-content: center;">
                        <?php if ( ! empty( $profile_photo_url ) ) : ?>
                            <img src="<?php echo esc_url( $profile_photo_url ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>" style="width: 76px; height: 76px; border-radius: 50%; object-fit: cover; border: 3px solid <?php echo esc_attr( $icon_color ); ?>; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12); display: block;" />
                        <?php else : ?>
                            <div style="width: 68px; height: 68px; border-radius: 50%; background: <?php echo esc_attr( $icon_bg ); ?>; color: <?php echo esc_attr( $icon_color ); ?>; display: flex; align-items: center; justify-content: center; border: 2px solid <?php echo esc_attr( $icon_color ); ?>; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);">
                                <span class="dashicons <?php echo esc_attr( $role_icon ); ?>" style="font-size: 34px; width: 34px; height: 34px;"></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-bottom: 8px;">
                        <span class="adp-badge <?php echo esc_attr( $role_badge ); ?>" style="font-size: 11px; padding: 3px 10px; font-weight: 600;">
                            <?php echo esc_html( $role_name ); ?>
                        </span>
                    </div>

                    <h2 class="adp-card-title" style="font-size: 22px; margin-bottom: 8px;">
                        <?php printf( esc_html__( '¡Hola, %s!', 'aura' ), esc_html( $user->display_name ) ); ?>
                    </h2>
                    <p class="adp-card-desc" style="margin-bottom: 24px;">
                        <?php esc_html_e( 'Has iniciado sesión exitosamente en la plataforma institucional.', 'aura' ); ?>
                    </p>

                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php if ( $can_admin ) : ?>
                            <a href="<?php echo esc_url( admin_url() ); ?>" class="btn btn-emerald btn-shimmer btn-lift" style="padding: 12px 20px; font-size: 14px; font-weight: 600; text-decoration: none; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-dashboard"></span>
                                <span><?php esc_html_e( 'Acceder al Panel Administrativo', 'aura' ); ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if ( $is_teacher ) : ?>
                            <a href="<?php echo esc_url( $teacher_portal_url ); ?>" class="btn btn-indigo btn-shimmer btn-lift" style="padding: 12px 20px; font-size: 14px; font-weight: 600; text-decoration: none; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-welcome-learn-more"></span>
                                <span><?php esc_html_e( 'Ir a mi Portal de Instructor', 'aura' ); ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if ( $is_student || $can_admin ) : ?>
                            <a href="<?php echo esc_url( $portal_url ); ?>" class="btn btn-violet btn-shimmer btn-lift" style="padding: 12px 20px; font-size: 14px; font-weight: 600; text-decoration: none; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-id-alt"></span>
                                <span><?php esc_html_e( 'Ir a mi Portal de Estudiante', 'aura' ); ?></span>
                            </a>
                        <?php endif; ?>

                        <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="btn btn-ghost" style="padding: 10px 16px; font-size: 13px; text-decoration: none; margin-top: 8px; justify-content: center; display: inline-flex; align-items: center; gap: 6px;">
                            <span class="dashicons dashicons-migrate"></span>
                            <span><?php esc_html_e( 'Cerrar Sesión', 'aura' ); ?></span>
                        </a>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        // ── PROCESO DE LOGIN POST ──
        $login_error = '';
        if ( isset( $_POST['aura_login_submit'] ) && wp_verify_nonce( $_POST['aura_login_nonce'] ?? '', 'aura_login_action' ) ) {
            $user_login = sanitize_text_field( $_POST['log'] ?? '' );
            $user_pass  = $_POST['pwd'] ?? '';
            $remember   = ! empty( $_POST['rememberme'] );

            $creds = [
                'user_login'    => $user_login,
                'user_password' => $user_pass,
                'remember'      => $remember,
            ];

            $signon = wp_signon( $creds, is_ssl() );
            if ( is_wp_error( $signon ) ) {
                $login_error = __( 'Credenciales incorrectas o usuario no encontrado.', 'aura' );
            } else {
                $target_redirect = sanitize_text_field( $_POST['redirect_to'] ?? '' );
                if ( empty( $target_redirect ) && ! empty( $atts['redirect'] ) ) {
                    $target_redirect = $atts['redirect'];
                }
                if ( ! empty( $target_redirect ) ) {
                    wp_safe_redirect( $target_redirect );
                } else {
                    wp_safe_redirect( $_SERVER['REQUEST_URI'] );
                }
                exit;
            }
        }

        $org_logo = get_option( 'aura_org_logo_url', '' );
        ?>
        <div class="aura-portal-wrap aura-login-wrap" style="max-width: 440px; margin: 40px auto; padding: 0 16px;">
            <div class="adp-card aura-login-card" style="padding: 32px 28px; border-radius: 16px; box-shadow: var(--aura-shadow-md);">
                <div style="display: flex; justify-content: flex-end; margin-bottom: 12px;">
                    <button type="button" class="aura-theme-toggle" aria-label="<?php esc_attr_e( 'Cambiar tema', 'aura' ); ?>">
                        <span class="dashicons dashicons-moon"></span>
                        <span class="aura-theme-toggle-label"><?php esc_html_e( 'Modo oscuro', 'aura' ); ?></span>
                    </button>
                </div>

                <div style="text-align: center; margin-bottom: 24px;">
                    <?php if ( ! empty( $org_logo ) ) : ?>
                        <div style="margin-bottom: 16px;">
                            <img src="<?php echo esc_url( $org_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="max-height: 56px; max-width: 100%; height: auto; margin: 0 auto; display: block; object-fit: contain;">
                        </div>
                    <?php else : ?>
                        <div style="width: 54px; height: 54px; margin: 0 auto 14px; border-radius: 12px; background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);">
                            <span class="dashicons dashicons-businessperson" style="font-size: 28px; width: 28px; height: 28px;"></span>
                        </div>
                    <?php endif; ?>
                    <span class="adp-badge badge-indigo has-dot" style="margin-bottom: 10px; display: inline-flex;">
                        <span class="pulse-dot"></span> <?php esc_html_e( 'Portal Institucional', 'aura' ); ?>
                    </span>
                    <h2 class="adp-card-title" style="font-size: 22px; margin-bottom: 6px;">
                        <?php esc_html_e( 'Iniciar Sesión', 'aura' ); ?>
                    </h2>
                    <p class="adp-card-desc" style="font-size: 13px; margin: 0;">
                        <?php esc_html_e( 'Ingresa tus credenciales para acceder a la plataforma', 'aura' ); ?>
                    </p>
                </div>

                <?php if ( ! empty( $login_error ) ) : ?>
                    <div class="alert-card alert-danger" style="margin-bottom: 18px; padding: 10px 14px; font-size: 13px; border-radius: 8px; display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-warning" style="color: #ef4444; flex-shrink: 0;"></span>
                        <span><?php echo esc_html( $login_error ); ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="" style="display: flex; flex-direction: column;">
                    <?php wp_nonce_field( 'aura_login_action', 'aura_login_nonce' ); ?>
                    <?php if ( ! empty( $atts['redirect'] ) ) : ?>
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url( $atts['redirect'] ); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="aura-login-user">
                            <?php esc_html_e( 'Correo o Usuario', 'aura' ); ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <span class="dashicons dashicons-admin-users"></span>
                            </span>
                            <input type="text" name="log" id="aura-login-user" class="form-control" required placeholder="<?php esc_attr_e( 'nombre@institucion.org', 'aura' ); ?>" autocomplete="username">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="form-label" for="aura-login-pass-input" style="margin: 0;">
                                <?php esc_html_e( 'Contraseña', 'aura' ); ?>
                            </label>
                            <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" style="color: var(--aura-indigo, #4f46e5); text-decoration: none; font-size: 12px; font-weight: 500;">
                                <?php esc_html_e( '¿Olvidaste tu contraseña?', 'aura' ); ?>
                            </a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text">
                                <span class="dashicons dashicons-lock"></span>
                            </span>
                            <input type="password" name="pwd" id="aura-login-pass-input" class="form-control" required placeholder="••••••••" autocomplete="current-password">
                            <button type="button" class="input-group-text aura-btn-toggle-pass" onclick="const p=document.getElementById('aura-login-pass-input');const isP=p.type==='password';p.type=isP?'text':'password';this.querySelector('.dashicons').className='dashicons '+(isP?'dashicons-hidden':'dashicons-visibility');" aria-label="<?php esc_attr_e( 'Mostrar/Ocultar contraseña', 'aura' ); ?>" title="<?php esc_attr_e( 'Mostrar/Ocultar contraseña', 'aura' ); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3" style="display: flex; align-items: center; justify-content: space-between; font-size: 13px;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--aura-text-secondary, #475569);">
                            <input type="checkbox" name="rememberme" value="1" style="border-radius: 4px;">
                            <span><?php esc_html_e( 'Recordar mi sesión', 'aura' ); ?></span>
                        </label>
                    </div>

                    <button type="submit" name="aura_login_submit" value="1" class="btn btn-indigo btn-shimmer btn-lift" style="width: 100%; padding: 12px 20px; font-size: 14px; font-weight: 600; border-radius: 8px; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
                        <span><?php esc_html_e( 'Ingresar a la Plataforma', 'aura' ); ?></span>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </form>
            </div>
        </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Alias retrocompatible para [aura_community_login]
     *
     * @param array $atts
     * @return string
     */
    public static function shortcode_community_login( $atts = [] ): string {
        return self::shortcode_login( (array) $atts );
    }

    /**
     * Shortcode: [aura_teacher_portal]
     * Portal del Instructor para consultar clases, tomar lista y ver tareas
     */
    public static function shortcode_teacher_portal(): string {
        if ( ! is_user_logged_in() ) {
            return self::shortcode_community_login();
        }

        $user_id = get_current_user_id();
        ob_start();
        ?>
        <div class="aura-portal-wrap" style="max-width: 1100px; margin: 30px auto; padding: 0 16px;">
            <div class="adp-card" style="padding: 24px; margin-bottom: 24px; border-radius: 12px;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <span class="adp-badge badge-indigo has-dot">
                            <span class="pulse-dot"></span> <?php esc_html_e( 'Panel del Instructor', 'aura' ); ?>
                        </span>
                        <h2 class="adp-card-title" style="font-size: 22px; margin-top: 8px;">
                            <?php esc_html_e( 'Mis Clases y Horarios Académicos', 'aura' ); ?>
                        </h2>
                        <p class="adp-card-desc">
                            <?php esc_html_e( 'Consulta tus próximas sesiones, toma asistencia en tiempo real y gestiona calificaciones.', 'aura' ); ?>
                        </p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button type="button" class="aura-theme-toggle" aria-label="<?php esc_attr_e( 'Cambiar tema', 'aura' ); ?>">
                            <span class="dashicons dashicons-moon"></span>
                            <span class="aura-theme-toggle-label"><?php esc_html_e( 'Modo oscuro', 'aura' ); ?></span>
                        </button>
                        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="btn btn-ghost" style="font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                            <span class="dashicons dashicons-migrate"></span>
                            <span><?php esc_html_e( 'Salir', 'aura' ); ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Contenedor del Calendario del Profesor -->
            <div class="adp-card" style="padding: 20px; border-radius: 12px;">
                <div id="aura-frontend-calendar" style="min-height: 550px;"></div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calEl = document.getElementById('aura-frontend-calendar');
            if (!calEl || typeof FullCalendar === 'undefined') return;

            var calendar = new FullCalendar.Calendar(calEl, {
                initialView: 'timeGridWeek',
                locale: 'es',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana',
                    day: 'Día',
                    list: 'Agenda'
                },
                slotMinTime: '06:00:00',
                slotMaxTime: '22:00:00',
                allDaySlot: false,
                nowIndicator: true,
                events: function(info, successCallback, failureCallback) {
                    jQuery.post(auraCalData.ajax_url, {
                        action: 'aura_cal_get_events',
                        nonce: auraCalData.nonce,
                        start: info.startStr,
                        end: info.endStr,
                        teacher_id: <?php echo intval( $user_id ); ?>
                    }, function(res) {
                        if (res && res.success) {
                            successCallback(res.data.events || []);
                        } else {
                            failureCallback();
                        }
                    }).fail(failureCallback);
                },
                eventClick: function(info) {
                    var p = info.event.extendedProps;
                    var msg = info.event.title + '\n' +
                              (p.location ? 'Aula: ' + p.location + '\n' : '') +
                              (p.online_url ? 'Online: ' + p.online_url + '\n' : '') +
                              (p.description ? 'Nota: ' + p.description : '');
                    alert(msg);
                }
            });
            calendar.render();
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: [aura_student_schedule]
     * Horario personal del estudiante para incrustar en su portal
     */
    public static function shortcode_student_schedule(): string {
        if ( ! is_user_logged_in() ) {
            return self::shortcode_community_login();
        }

        ob_start();
        ?>
        <div class="aura-student-schedule-wrap" style="margin: 20px 0;">
            <div class="adp-card" style="padding: 20px; border-radius: 12px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                    <h3 class="adp-card-title" style="font-size: 18px; margin: 0; display: inline-flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-calendar-alt" style="font-size: 20px; width: 20px; height: 20px;"></span>
                        <span><?php esc_html_e( 'Mi Calendario de Clases y Actividades', 'aura' ); ?></span>
                    </h3>
                </div>
                <div id="aura-student-calendar" style="min-height: 500px;"></div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calEl = document.getElementById('aura-student-calendar');
            if (!calEl || typeof FullCalendar === 'undefined') return;

            var calendar = new FullCalendar.Calendar(calEl, {
                initialView: 'timeGridWeek',
                locale: 'es',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                slotMinTime: '07:00:00',
                slotMaxTime: '21:00:00',
                allDaySlot: false,
                nowIndicator: true,
                events: function(info, successCallback, failureCallback) {
                    jQuery.post(auraCalData.ajax_url, {
                        action: 'aura_cal_get_events',
                        nonce: auraCalData.nonce,
                        start: info.startStr,
                        end: info.endStr
                    }, function(res) {
                        if (res && res.success) {
                            successCallback(res.data.events || []);
                        } else {
                            failureCallback();
                        }
                    }).fail(failureCallback);
                },
                eventClick: function(info) {
                    var p = info.event.extendedProps;
                    var details = info.event.title + '\n' +
                                  (p.subject_name ? 'Materia: ' + p.subject_name + '\n' : '') +
                                  (p.location ? 'Salón: ' + p.location + '\n' : '') +
                                  (p.online_url ? 'Enlace: ' + p.online_url + '\n' : '') +
                                  (p.description ? '\n' + p.description : '');
                    alert(details);
                }
            });
            calendar.render();
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
