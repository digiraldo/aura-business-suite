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

        $teacher_portal_page_id = class_exists( 'Aura_Calendar_Admin' ) ? Aura_Calendar_Admin::get_teacher_portal_page_id() : 0;
        $teacher_portal_url     = $teacher_portal_page_id > 0 ? get_permalink( $teacher_portal_page_id ) : home_url( '/portal-instructor/' );
        $students_clean         = class_exists( 'Aura_Calendar_Admin' ) ? Aura_Calendar_Admin::get_students_candidates() : [];
        $student_roles          = class_exists( 'Aura_Calendar_Events' ) ? Aura_Calendar_Events::get_student_roles() : [];
        $teachers_clean         = class_exists( 'Aura_Calendar_Admin' ) ? Aura_Calendar_Admin::get_instructors() : [];
        $programs               = class_exists( 'Aura_Calendar_Programs' ) ? Aura_Calendar_Programs::get_all( [ 'status' => 'active', 'limit' => 100 ] ) : [];

        wp_localize_script( 'aura-calendar-frontend', 'auraCalData', [
            'ajax_url'            => admin_url( 'admin-ajax.php' ),
            'calendar_url'        => admin_url( 'admin.php?page=aura-calendar' ),
            'nonce'               => wp_create_nonce( 'aura_cal_nonce' ),
            'current_user_id'     => get_current_user_id(),
            'teacher_portal_url'  => $teacher_portal_url,
            'teacher_code_prefix' => class_exists( 'Aura_Calendar_Admin' ) ? Aura_Calendar_Admin::get_teacher_code_prefix() : '',
            'user_can_edit'       => current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'aura_manage_calendar' ) || current_user_can( 'manage_options' ),
            'user_can_tasks'      => current_user_can( 'aura_cal_manage_tasks' ) || current_user_can( 'aura_teach_calendar' ) || current_user_can( 'aura_manage_calendar' ) || current_user_can( 'manage_options' ),
            'user_can_attendance' => current_user_can( 'aura_cal_take_attendance' ) || current_user_can( 'aura_take_attendance' ) || current_user_can( 'aura_teach_calendar' ) || current_user_can( 'manage_options' ),
            'user_can_grade'      => current_user_can( 'aura_cal_manage_grades' ) || current_user_can( 'aura_record_grades' ) || current_user_can( 'aura_cal_grade_tasks' ) || current_user_can( 'aura_teach_calendar' ) || current_user_can( 'manage_options' ),
            'gcal_enabled'        => Aura_Calendar_Google_Sync::is_enabled(),
            'programs'            => $programs,
            'teachers'            => $teachers_clean,
            'students'            => $students_clean,
            'student_roles'       => $student_roles,
            'last_sync_version'   => (int) get_option( Aura_Calendar_Events::OPTION_SYNC_VERSION, time() ),
            'paletteColors'       => class_exists( 'Aura_Calendar_Admin' ) ? Aura_Calendar_Admin::get_palette_colors() : [],
            'i18n'                => [
                'today'                 => __( 'Hoy', 'aura' ),
                'month'                 => __( 'Mes', 'aura' ),
                'week'                  => __( 'Semana', 'aura' ),
                'day'                   => __( 'Día', 'aura' ),
                'list'                  => __( 'Agenda', 'aura' ),
                'saved'                 => __( 'Guardado exitosamente.', 'aura' ),
                'error'                 => __( 'Error al procesar la solicitud.', 'aura' ),
                'fullscreen'            => __( 'Pantalla Completa', 'aura' ),
                'exit_fullscreen'       => __( 'Salir de Pantalla Completa', 'aura' ),
                'live_updated'          => __( 'El calendario fue actualizado por otro colaborador.', 'aura' ),
                'live_sync_active'      => __( 'Sincronización en vivo activa', 'aura' ),
                'add_leader'            => __( 'Asignar Estudiante', 'aura' ),
                'select_student'        => __( 'Seleccionar estudiante...', 'aura' ),
                'select_role'           => __( 'Seleccionar responsabilidad...', 'aura' ),
                'confirm_delete'        => __( '¿Estás seguro de eliminar este elemento?', 'aura' ),
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
     * Portal Empresarial del Instructor / Docente
     * - Gestión de clases y horarios con FullCalendar y asistencia rápida
     * - Asignaturas y alumnos matriculados
     * - Tareas académicas sincronizadas con la Biblioteca (asignar libros, resúmenes y calificaciones)
     */
    public static function shortcode_teacher_portal(): string {
        if ( ! is_user_logged_in() ) {
            return self::shortcode_community_login();
        }

        $user_id   = get_current_user_id();
        $user      = wp_get_current_user();
        $prefix    = class_exists( 'Aura_Calendar_Admin' ) ? Aura_Calendar_Admin::get_teacher_code_prefix() : 'CEM-PROF';
        $prof_code = $prefix . '-' . str_pad( (string) $user_id, 4, '0', STR_PAD_LEFT );

        // Verificar permisos docentes o de administración
        $is_teacher = (
            current_user_can( 'aura_cal_view_calendar' ) ||
            current_user_can( 'aura_teach_calendar' ) ||
            current_user_can( 'aura_manage_calendar' ) ||
            current_user_can( 'manage_options' )
        );

        global $wpdb;
        $table_subj  = $wpdb->prefix . 'aura_cal_subjects';
        $table_prog  = $wpdb->prefix . 'aura_cal_programs';
        $table_tasks = $wpdb->prefix . 'aura_cal_tasks';
        $table_books = $wpdb->prefix . 'aura_library_books';
        $table_subs  = $wpdb->prefix . 'aura_cal_task_submissions';

        // Materias a cargo del profesor (titular directo o en equipo de profesores titulares)
        $has_subj_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_subj}'" ) === $table_subj;
        $my_subjects = [];
        if ( $has_subj_table ) {
            $user_id_like = '%' . $wpdb->esc_like( '"' . $user_id . '"' ) . '%';
            $my_subjects = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.*, p.name AS program_name, p.code AS program_code
                 FROM {$table_subj} s
                 LEFT JOIN {$table_prog} p ON p.id = s.program_id
                 WHERE (s.teacher_id = %d OR s.teacher_ids LIKE %s) AND s.deleted_at IS NULL
                 ORDER BY s.name ASC",
                $user_id,
                $user_id_like
            ) );
        }

        // Tareas creadas por o correspondientes al profesor
        $has_tasks_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_tasks}'" ) === $table_tasks;
        $has_books_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_books}'" ) === $table_books;
        $my_tasks = [];
        if ( $has_tasks_table ) {
            $book_join = $has_books_table ? "LEFT JOIN {$table_books} b ON b.id = t.book_id" : "";
            $book_cols = $has_books_table ? ", b.title AS book_title, b.author AS book_author, b.isbn AS book_isbn" : "";
            $user_id_like = '%' . $wpdb->esc_like( '"' . $user_id . '"' ) . '%';

            $my_tasks = $wpdb->get_results( $wpdb->prepare(
                "SELECT t.*, p.name AS program_name, p.code AS program_code, s.name AS subject_name
                        {$book_cols},
                        (SELECT COUNT(*) FROM {$table_subs} sub WHERE sub.task_id = t.id) AS submissions_count
                 FROM {$table_tasks} t
                 LEFT JOIN {$table_prog} p ON p.id = t.program_id
                 LEFT JOIN {$table_subj} s ON s.id = t.subject_id
                 {$book_join}
                 WHERE (t.created_by = %d OR s.teacher_id = %d OR s.teacher_ids LIKE %s) AND t.deleted_at IS NULL
                 ORDER BY t.created_at DESC",
                $user_id,
                $user_id,
                $user_id_like
            ) );
        }

        // Catálogo de libros de la biblioteca disponibles para asignar
        $library_books = [];
        if ( $has_books_table ) {
            $library_books = $wpdb->get_results( "SELECT id, title, author, isbn, dewey_number FROM {$table_books} WHERE deleted_at IS NULL ORDER BY title ASC LIMIT 250" );
        }

        // Programas activos
        $programs = class_exists( 'Aura_Calendar_Programs' ) ? Aura_Calendar_Programs::get_all( [ 'status' => 'active', 'limit' => 100 ] ) : [];

        // Avatar
        $avatar_url = get_avatar_url( $user_id, [ 'size' => 120 ] );

        ob_start();
        ?>
        <div class="aura-portal-wrap aura-teacher-portal" style="max-width: 1200px; margin: 24px auto; padding: 0 16px; font-family: inherit;">

            <!-- BARRA SUPERIOR / ENCABEZADO DEL INSTRUCTOR -->
            <div class="adp-card aura-teacher-card" style="margin-bottom: 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 18px;">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>" style="width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #6366f1; box-shadow: 0 4px 12px rgba(99,102,241,0.25);">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <h1 style="font-size: 20px; font-weight: 700; margin: 0; color: var(--at-text-primary);">
                                    <?php echo esc_html( $user->display_name ); ?>
                                </h1>
                                <span class="adp-badge badge-indigo" style="font-size: 11px; font-weight: 600; font-family: monospace;">
                                    <?php echo esc_html( $prof_code ); ?>
                                </span>
                                <?php if ( $is_teacher ) : ?>
                                    <span class="adp-badge badge-emerald has-dot" style="font-size: 11px;">
                                        <span class="pulse-dot"></span> <?php esc_html_e( 'Instructor Activo', 'aura' ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="adp-badge badge-amber" style="font-size: 11px;">
                                        ⚠️ <?php esc_html_e( 'Rol no asignado en CBAC', 'aura' ); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--at-text-secondary);">
                                <?php esc_html_e( 'Portal Docente y Académico — Aura Business Suite', 'aura' ); ?>
                                <?php if ( ! empty( $my_subjects ) ) : ?>
                                    &bull; <strong><?php echo count( $my_subjects ); ?></strong> <?php esc_html_e( 'asignaturas asignadas', 'aura' ); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button type="button" class="aura-theme-toggle" aria-label="<?php esc_attr_e( 'Cambiar tema', 'aura' ); ?>">
                            <span class="dashicons dashicons-moon"></span>
                            <span class="aura-theme-toggle-label"><?php esc_html_e( 'Tema', 'aura' ); ?></span>
                        </button>

                        <?php if ( current_user_can( 'manage_options' ) || current_user_can( 'aura_view_dashboard' ) ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-calendar' ) ); ?>" class="btn btn-ghost" style="font-size: 12px; padding: 7px 12px;">
                                ⚙️ <?php esc_html_e( 'Panel Admin', 'aura' ); ?>
                            </a>
                        <?php endif; ?>

                        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="btn btn-ghost" style="font-size: 12px; padding: 7px 12px; color: #ef4444;">
                            <span class="dashicons dashicons-migrate" style="font-size: 16px; width: 16px; height: 16px;"></span>
                            <span><?php esc_html_e( 'Salir', 'aura' ); ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <?php if ( ! $is_teacher ) : ?>
                <div class="adp-card" style="padding: 22px; border-radius: 12px; border-left: 4px solid #f59e0b; margin-bottom: 22px; background: rgba(245, 158, 11, 0.08);">
                    <h3 style="margin: 0 0 8px 0; color: #b45309; font-size: 16px;">⚠️ <?php esc_html_e( 'Acceso Docente en Espera de Acreditación', 'aura' ); ?></h3>
                    <p style="margin: 0; font-size: 13px; color: var(--at-text-secondary); line-height: 1.5;">
                        <?php esc_html_e( 'Tu usuario aún no cuenta con la capability docente (aura_cal_view_calendar o plantilla academic_teacher) asignada por el Coordinador Académico en el módulo de Permisos (CBAC). Si crees que se trata de un error, solicita al administrador que active tu perfil docente.', 'aura' ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- NAVEGACIÓN POR PESTAÑAS (TABS DOCENTES) -->
            <div class="aura-teacher-nav" role="tablist" aria-label="<?php esc_attr_e( 'Navegación del Portal Docente', 'aura' ); ?>">
                <button type="button" class="teacher-tab-btn active" data-target="tab-teacher-schedule" role="tab" aria-selected="true">
                    <span class="dashicons dashicons-calendar-alt teacher-tab-icon"></span>
                    <span class="teacher-tab-text"><?php esc_html_e( 'Mis Clases y Horario', 'aura' ); ?></span>
                </button>
                <button type="button" class="teacher-tab-btn" data-target="tab-teacher-subjects" role="tab" aria-selected="false">
                    <span class="dashicons dashicons-book teacher-tab-icon"></span>
                    <span class="teacher-tab-text"><?php esc_html_e( 'Mis Materias y Grupos', 'aura' ); ?></span>
                    <span class="teacher-tab-badge"><?php echo count( $my_subjects ); ?></span>
                </button>
                <button type="button" class="teacher-tab-btn" data-target="tab-teacher-tasks" role="tab" aria-selected="false">
                    <span class="dashicons dashicons-clipboard teacher-tab-icon"></span>
                    <span class="teacher-tab-text"><?php esc_html_e( 'Tareas y Evaluaciones', 'aura' ); ?></span>
                    <span class="teacher-tab-badge teacher-tab-badge-tasks"><?php echo count( $my_tasks ); ?></span>
                </button>
            </div>

            <!-- CONTENIDO TAB 1: CALENDARIO DE CLASES -->
            <div id="tab-teacher-schedule" class="teacher-tab-content active">
                <div class="adp-card aura-teacher-card" style="padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                        <h3 class="adp-card-title" style="font-size: 17px; margin: 0; display: inline-flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-calendar-alt" style="color: #6366f1;"></span>
                            <?php esc_html_e( 'Horario Semanal de Sesiones y Evaluaciones', 'aura' ); ?>
                        </h3>
                        <div style="font-size: 12px; color: var(--at-text-muted);">
                            💡 <?php esc_html_e( 'Haz clic sobre una clase para ver el aula, enlace virtual o tomar lista rápida.', 'aura' ); ?>
                        </div>
                    </div>
                    <div id="aura-teacher-fullcalendar" style="min-height: 600px;"></div>
                </div>
            </div>

            <!-- CONTENIDO TAB 2: MIS MATERIAS -->
            <div id="tab-teacher-subjects" class="teacher-tab-content" style="display: none;">
                <div class="adp-card aura-teacher-card">
                    <h3 class="adp-card-title" style="font-size: 18px; margin: 0 0 6px 0;">
                        📚 <?php esc_html_e( 'Cátedras y Asignaturas Asignadas', 'aura' ); ?>
                    </h3>
                    <p class="adp-card-desc" style="margin: 0 0 20px 0; color: var(--at-text-secondary);">
                        <?php esc_html_e( 'Materias académicas bajo tu titularidad docente según el plan de estudios institucional.', 'aura' ); ?>
                    </p>

                    <?php if ( empty( $my_subjects ) ) : ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--at-text-muted);">
                            <div style="font-size: 36px; margin-bottom: 10px;">📖</div>
                            <p><?php esc_html_e( 'Aún no tienes asignaturas registradas a tu nombre. El coordinador te asignará materias desde el módulo Calendario.', 'aura' ); ?></p>
                        </div>
                    <?php else : ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 18px;">
                            <?php foreach ( $my_subjects as $s ) : 
                                $t_materials = ! empty( $s->teacher_materials ) ? json_decode( $s->teacher_materials, true ) : [];
                                if ( ! is_array( $t_materials ) ) $t_materials = [];
                                $s_materials = ! empty( $s->student_materials ) ? json_decode( $s->student_materials, true ) : [];
                                if ( ! is_array( $s_materials ) ) $s_materials = [];
                                $t_count = count( $t_materials );
                                $s_count = count( $s_materials );
                                $collapse_id = 't-mat-collapse-' . $s->id;
                            ?>
                                <div class="aura-teacher-item-card" data-subject-id="<?php echo esc_attr( $s->id ); ?>" style="display: flex; flex-direction: column; justify-content: space-between;">
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                            <span class="adp-badge badge-indigo"><?php echo esc_html( $s->code ?: 'MAT' ); ?></span>
                                            <?php if ( ! empty( $s->program_name ) ) : ?>
                                                <span class="adp-badge badge-slate" style="font-size: 11px;"><?php echo esc_html( $s->program_name ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <h4 class="aura-teacher-item-title" style="margin-bottom: 6px;">
                                            <?php echo esc_html( $s->name ); ?>
                                        </h4>
                                        <?php if ( ! empty( $s->description ) ) : ?>
                                            <p class="aura-teacher-item-desc" style="margin-bottom: 12px;">
                                                <?php echo esc_html( wp_strip_all_tags( $s->description ) ); ?>
                                            </p>
                                        <?php endif; ?>

                                        <!-- Resumen de Materiales Docente / Alumnos -->
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;">
                                            <span class="adp-badge badge-amber" title="<?php esc_attr_e( 'Material pedagógico de cátedra exclusivo para instructores', 'aura' ); ?>" style="font-size: 11.5px; padding: 4px 8px;">
                                                📁 <?php esc_html_e( 'Cátedra:', 'aura' ); ?> <strong><?php echo $t_count; ?></strong>
                                            </span>
                                            <span class="adp-badge badge-emerald" title="<?php esc_attr_e( 'Material de estudio descargable por estudiantes', 'aura' ); ?>" style="font-size: 11.5px; padding: 4px 8px;">
                                                📖 <?php esc_html_e( 'Alumnos:', 'aura' ); ?> <strong><?php echo $s_count; ?></strong>
                                            </span>
                                        </div>

                                        <!-- Panel Desplegable de Materiales -->
                                        <div id="<?php echo esc_attr( $collapse_id ); ?>" class="aura-teacher-materials-panel" style="display: none; background: var(--at-bg-card-alt, #f8fafc); border: 1px solid var(--at-border); border-radius: 10px; padding: 12px; margin-bottom: 12px; font-size: 12px;">
                                            <!-- Sección 1: Material Pedagógico (Cátedra) -->
                                            <div style="margin-bottom: 12px;">
                                                <div style="font-weight: 700; color: #b45309; display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                                    <span>📁 <?php esc_html_e( 'Material de Cátedra (Pedagógico)', 'aura' ); ?></span>
                                                    <span style="font-size: 10px; color: var(--at-text-muted); font-weight: normal;"><?php esc_html_e( 'Heredable', 'aura' ); ?></span>
                                                </div>
                                                <?php if ( empty( $t_materials ) ) : ?>
                                                    <p style="font-size: 11px; color: var(--at-text-muted); margin: 0 0 4px 0;"><?php esc_html_e( 'Sin documentos de referencia aún.', 'aura' ); ?></p>
                                                <?php else : ?>
                                                    <div style="display: flex; flex-direction: column; gap: 6px;">
                                                        <?php foreach ( $t_materials as $t_mat ) : 
                                                            $view_link = ! empty( $t_mat['view_url'] ) ? $t_mat['view_url'] : ( ! empty( $t_mat['url'] ) ? $t_mat['url'] : '' );
                                                            $is_drive = ! empty( $t_mat['drive_file_id'] ) || ( ! empty( $t_mat['storage'] ) && $t_mat['storage'] === 'google_drive' );
                                                        ?>
                                                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 4px 8px; background: var(--at-bg-card); border: 1px solid var(--at-border); border-radius: 6px;">
                                                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 190px;" title="<?php echo esc_attr( $t_mat['name'] ?? 'Documento' ); ?>">
                                                                    <?php echo $is_drive ? '☁️' : '📄'; ?> <?php echo esc_html( $t_mat['name'] ?? 'Documento' ); ?>
                                                                </span>
                                                                <?php if ( $view_link ) : ?>
                                                                    <a href="<?php echo esc_url( $view_link ); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-ghost" style="padding: 2px 6px; font-size: 10.5px; text-decoration: none;">
                                                                        📥 <?php esc_html_e( 'Ver', 'aura' ); ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Sección 2: Material para Estudiantes -->
                                            <div>
                                                <div style="font-weight: 700; color: #047857; margin-bottom: 6px;">
                                                    📖 <?php esc_html_e( 'Material para Alumnos', 'aura' ); ?>
                                                </div>
                                                <?php if ( empty( $s_materials ) ) : ?>
                                                    <p style="font-size: 11px; color: var(--at-text-muted); margin: 0 0 4px 0;"><?php esc_html_e( 'Sin lecturas o guías publicadas.', 'aura' ); ?></p>
                                                <?php else : ?>
                                                    <div style="display: flex; flex-direction: column; gap: 6px;">
                                                        <?php foreach ( $s_materials as $s_mat ) : 
                                                            $s_link = ! empty( $s_mat['view_url'] ) ? $s_mat['view_url'] : ( ! empty( $s_mat['url'] ) ? $s_mat['url'] : '' );
                                                            $s_is_drive = ! empty( $s_mat['drive_file_id'] ) || ( ! empty( $s_mat['storage'] ) && $s_mat['storage'] === 'google_drive' );
                                                        ?>
                                                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 4px 8px; background: var(--at-bg-card); border: 1px solid var(--at-border); border-radius: 6px;">
                                                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 190px;" title="<?php echo esc_attr( $s_mat['name'] ?? 'Documento' ); ?>">
                                                                    <?php echo $s_is_drive ? '☁️' : '📄'; ?> <?php echo esc_html( $s_mat['name'] ?? 'Documento' ); ?>
                                                                </span>
                                                                <?php if ( $s_link ) : ?>
                                                                    <a href="<?php echo esc_url( $s_link ); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-ghost" style="padding: 2px 6px; font-size: 10.5px; text-decoration: none;">
                                                                        📥 <?php esc_html_e( 'Ver', 'aura' ); ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="border-top: 1px solid var(--at-border); padding-top: 10px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--at-text-muted); flex-wrap: wrap; gap: 6px;">
                                        <div style="display: flex; gap: 6px;">
                                            <button type="button" class="btn btn-ghost btn-toggle-mat-panel" data-target="#<?php echo esc_attr( $collapse_id ); ?>" style="font-size: 11px; padding: 4px 8px;">
                                                📂 <?php esc_html_e( 'Recursos', 'aura' ); ?> (<?php echo $t_count + $s_count; ?>)
                                            </button>
                                            <button type="button" class="btn btn-ghost btn-open-upload-mat" data-subject-id="<?php echo esc_attr( $s->id ); ?>" data-subject-name="<?php echo esc_attr( $s->name ); ?>" style="font-size: 11px; padding: 4px 8px; color: #4f46e5;">
                                                ☁️ <?php esc_html_e( 'Subir', 'aura' ); ?>
                                            </button>
                                        </div>
                                        <button type="button" class="btn btn-ghost btn-new-task-for-subject" data-program-id="<?php echo esc_attr( $s->program_id ); ?>" data-subject-id="<?php echo esc_attr( $s->id ); ?>" style="font-size: 11px; padding: 4px 8px;">
                                            ➕ <?php esc_html_e( 'Crear Tarea', 'aura' ); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONTENIDO TAB 3: TAREAS, CONTROLES DE LECTURA Y EVALUACIONES -->
            <div id="tab-teacher-tasks" class="teacher-tab-content" style="display: none;">
                <div class="adp-card aura-teacher-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <h3 class="adp-card-title" style="font-size: 18px; margin: 0;">
                                📝 <?php esc_html_e( 'Tareas, Controles de Lectura y Ensayos', 'aura' ); ?>
                            </h3>
                            <p class="adp-card-desc" style="margin: 4px 0 0 0; color: var(--at-text-secondary);">
                                <?php esc_html_e( 'Asigna lecturas de la Biblioteca con resúmenes escritos y califica las respuestas de los estudiantes.', 'aura' ); ?>
                            </p>
                        </div>
                        <button type="button" class="btn btn-indigo btn-shimmer btn-lift" id="btn-teacher-open-task-modal">
                            ➕ <?php esc_html_e( 'Asignar Nueva Tarea', 'aura' ); ?>
                        </button>
                    </div>

                    <?php if ( empty( $my_tasks ) ) : ?>
                        <div style="text-align: center; padding: 48px 20px; color: var(--at-text-muted);">
                            <div style="font-size: 40px; margin-bottom: 12px;">📚</div>
                            <h4 style="font-size: 17px; margin-bottom: 6px; color: var(--at-text-primary);"><?php esc_html_e( 'Aún no has creado tareas o actividades', 'aura' ); ?></h4>
                            <p style="max-width: 480px; margin: 0 auto 18px; font-size: 13px; color: var(--at-text-secondary);">
                                <?php esc_html_e( 'Publica tu primera tarea vinculando un libro de la biblioteca o un ensayo con plazo de entrega.', 'aura' ); ?>
                            </p>
                            <button type="button" class="btn btn-indigo btn-lift" id="btn-teacher-open-first-task">
                                ➕ <?php esc_html_e( 'Crear Primera Tarea', 'aura' ); ?>
                            </button>
                        </div>
                    <?php else : ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 18px;">
                            <?php foreach ( $my_tasks as $t ) : ?>
                                <div class="aura-teacher-item-card">
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                            <span class="adp-badge badge-indigo"><?php echo esc_html( $t->program_code ?: $t->program_name ); ?></span>
                                            <?php if ( $t->status === 'published' ) : ?>
                                                <span class="adp-badge badge-emerald has-dot"><span class="pulse-dot"></span> <?php esc_html_e( 'Publicada', 'aura' ); ?></span>
                                            <?php else : ?>
                                                <span class="adp-badge badge-slate"><?php echo esc_html( ucfirst( $t->status ) ); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <h4 class="aura-teacher-item-title">
                                            <?php echo esc_html( $t->title ); ?>
                                        </h4>

                                        <?php if ( ! empty( $t->subject_name ) ) : ?>
                                            <div style="font-size: 12px; font-weight: 600; color: var(--at-text-secondary); margin-bottom: 8px;">
                                                📚 <?php echo esc_html( $t->subject_name ); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ( ! empty( $t->book_title ) ) : ?>
                                            <div class="aura-book-assigned-chip">
                                                <span style="font-size: 20px;">📖</span>
                                                <div style="line-height: 1.35;">
                                                    <div class="book-title"><?php echo esc_html( $t->book_title ); ?></div>
                                                    <?php if ( ! empty( $t->book_author ) ) : ?>
                                                        <div class="book-meta"><?php echo esc_html( $t->book_author ); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ( ! empty( $t->submission_type ) && $t->submission_type === 'text_only' ) : ?>
                                            <div style="font-size: 11px; color: var(--at-primary); font-weight: 600; margin-bottom: 8px;">
                                                ✍️ <?php esc_html_e( 'Resumen en plataforma', 'aura' ); ?>
                                                <?php if ( ! empty( $t->min_words ) ) : ?>
                                                    (mín. <?php echo intval( $t->min_words ); ?> palabras)
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ( ! empty( $t->description ) ) : ?>
                                            <p class="aura-teacher-item-desc" style="max-height: 50px; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo esc_html( wp_strip_all_tags( $t->description ) ); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <div style="border-top: 1px solid var(--at-border); padding-top: 12px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                                        <div style="font-size: 11px; color: var(--at-text-muted);">
                                            ⏰ <?php esc_html_e( 'Vence:', 'aura' ); ?> <strong><?php echo esc_html( date_i18n( 'j M H:i', strtotime( $t->due_datetime ) ) ); ?></strong>
                                        </div>
                                        <button type="button" class="btn btn-emerald btn-shimmer btn-teacher-view-subs" data-task-id="<?php echo esc_attr( $t->id ); ?>" data-task-title="<?php echo esc_attr( $t->title ); ?>" data-max-score="<?php echo esc_attr( $t->max_score ); ?>" style="font-size: 12px; padding: 5px 12px;">
                                            📥 <?php echo intval( $t->submissions_count ); ?> <?php esc_html_e( 'Entregas', 'aura' ); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 MODAL DOCENTE: CREAR TAREA O CONTROL DE LECTURA
                 ══════════════════════════════════════════════════════════════ -->
            <div id="modal-teacher-task" class="aura-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 16px;">
                <div class="aura-modal-container aura-teacher-modal" style="max-width: 620px; width: 100%; overflow: hidden;">
                    <div class="aura-teacher-modal-header">
                        <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: var(--at-text-primary);">
                            📝 <?php esc_html_e( 'Asignar Tarea o Control de Lectura', 'aura' ); ?>
                        </h3>
                        <button type="button" class="btn-close-modal" data-close="#modal-teacher-task" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--at-text-muted);">&times;</button>
                    </div>

                    <form id="form-teacher-task-create">
                        <div class="aura-teacher-modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                            <div class="form-group">
                                <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                    <?php esc_html_e( 'Título de la Actividad', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="text" name="title" id="t-tsk-title" required class="form-control" placeholder="<?php esc_attr_e( 'Ej: Resumen analítico de los capítulos 1 al 4', 'aura' ); ?>" style="width: 100%;">
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                <div class="form-group">
                                    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                        🎓 <?php esc_html_e( 'Programa Académico', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                                    </label>
                                    <select name="program_id" id="t-tsk-prog" required class="form-control" style="width: 100%;">
                                        <option value=""><?php esc_html_e( 'Seleccionar...', 'aura' ); ?></option>
                                        <?php foreach ( $programs as $p ) : ?>
                                            <option value="<?php echo esc_attr( $p->id ); ?>"><?php echo esc_html( $p->name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                        📚 <?php esc_html_e( 'Materia / Asignatura', 'aura' ); ?>
                                    </label>
                                    <select name="subject_id" id="t-tsk-subj" class="form-control" style="width: 100%;">
                                        <option value=""><?php esc_html_e( 'General / Opcional', 'aura' ); ?></option>
                                        <?php foreach ( $my_subjects as $ms ) : ?>
                                            <option value="<?php echo esc_attr( $ms->id ); ?>" data-prog="<?php echo esc_attr( $ms->program_id ); ?>">
                                                <?php echo esc_html( $ms->name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- VINCULACIÓN CON LIBRO DE BIBLIOTECA -->
                            <div class="form-group" style="background: var(--at-chip-bg); padding: 14px; border-radius: 10px; border: 1px solid var(--at-chip-border);">
                                <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-primary);">
                                    📖 <?php esc_html_e( 'Asignar Libro de la Biblioteca (Control de Lectura)', 'aura' ); ?>
                                </label>
                                <select name="book_id" id="t-tsk-book" class="form-control" style="width: 100%;">
                                    <option value=""><?php esc_html_e( '-- Ninguno (Tarea Estándar sin Libro) --', 'aura' ); ?></option>
                                    <?php foreach ( $library_books as $lb ) : ?>
                                        <option value="<?php echo esc_attr( $lb->id ); ?>">
                                            <?php echo esc_html( $lb->title . ( $lb->author ? ' — ' . $lb->author : '' ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color: var(--at-text-muted); font-size: 11px; display: block; margin-top: 4px;">
                                    <?php esc_html_e( 'Los estudiantes verán la ficha del libro asignado en su portal y podrán enviar su resumen escrito.', 'aura' ); ?>
                                </small>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                <div class="form-group">
                                    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                        ✍️ <?php esc_html_e( 'Tipo de Entrega Exigido', 'aura' ); ?>
                                    </label>
                                    <select name="submission_type" id="t-tsk-type" class="form-control" style="width: 100%;">
                                        <option value="text_only"><?php esc_html_e( 'Solo Resumen Escrito en Plataforma', 'aura' ); ?></option>
                                        <option value="text_or_file"><?php esc_html_e( 'Texto Escrito o Archivo (Flexible)', 'aura' ); ?></option>
                                        <option value="file_only"><?php esc_html_e( 'Solo Archivo Adjunto (PDF / Doc)', 'aura' ); ?></option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                        📏 <?php esc_html_e( 'Palabras Mínimas (0 = Sin límite)', 'aura' ); ?>
                                    </label>
                                    <input type="number" name="min_words" id="t-tsk-min-words" value="250" min="0" step="25" class="form-control" style="width: 100%;">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                <div class="form-group">
                                    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                        ⏰ <?php esc_html_e( 'Fecha y Hora Límite', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                                    </label>
                                    <input type="datetime-local" name="due_datetime" id="t-tsk-due" required class="form-control" style="width: 100%;">
                                </div>

                                <div class="form-group">
                                    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                        🎯 <?php esc_html_e( 'Puntaje Máximo', 'aura' ); ?>
                                    </label>
                                    <input type="number" step="0.5" name="max_score" id="t-tsk-max-score" value="100" class="form-control" style="width: 100%;">
                                </div>
                            </div>

                            <div class="form-group">
                                <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                    <?php esc_html_e( 'Instrucciones y Criterios de Evaluación', 'aura' ); ?>
                                </label>
                                <textarea name="description" id="t-tsk-desc" rows="3" class="form-control" placeholder="<?php esc_attr_e( 'Describe las preguntas, objetivos o estructura que debe contener el resumen...', 'aura' ); ?>" style="width: 100%;"></textarea>
                            </div>
                        </div>

                        <div class="aura-teacher-modal-footer">
                            <button type="button" class="btn btn-ghost btn-close-modal" data-close="#modal-teacher-task">
                                <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                            </button>
                            <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-save-teacher-task">
                                💾 <?php esc_html_e( 'Publicar Tarea', 'aura' ); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 MODAL DOCENTE: REVISIÓN DE ENTREGAS Y CALIFICACIÓN
                 ══════════════════════════════════════════════════════════════ -->
            <div id="modal-teacher-submissions" class="aura-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 16px;">
                <div class="aura-modal-container aura-teacher-modal" style="max-width: 780px; width: 100%; overflow: hidden;">
                    <div class="aura-teacher-modal-header">
                        <div>
                            <h3 id="t-subs-modal-title" style="margin: 0; font-size: 17px; font-weight: 700; color: var(--at-text-primary);">
                                📥 <?php esc_html_e( 'Entregas Recibidas', 'aura' ); ?>
                            </h3>
                            <div id="t-subs-modal-sub" style="font-size: 12px; color: var(--at-text-muted); margin-top: 2px;"></div>
                        </div>
                        <button type="button" class="btn-close-modal" data-close="#modal-teacher-submissions" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--at-text-muted);">&times;</button>
                    </div>

                    <div id="t-subs-body" class="aura-teacher-modal-body">
                        <p style="text-align: center; color: var(--at-text-muted);"><?php esc_html_e( 'Cargando entregas...', 'aura' ); ?></p>
                    </div>

                    <div class="aura-teacher-modal-footer">
                        <button type="button" class="btn btn-ghost btn-close-modal" data-close="#modal-teacher-submissions">
                            <?php esc_html_e( 'Cerrar', 'aura' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 MODAL DOCENTE: SUBIR MATERIAL DE ESTUDIO (DRIVE / LOCAL)
                 ══════════════════════════════════════════════════════════════ -->
            <div id="modal-teacher-material" class="aura-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 16px;">
                <div class="aura-modal-container aura-teacher-modal" style="max-width: 540px; width: 100%; overflow: hidden;">
                    <div class="aura-teacher-modal-header">
                        <h3 id="t-mat-modal-title" style="margin: 0; font-size: 17px; font-weight: 700; color: var(--at-text-primary);">
                            ☁️ <?php esc_html_e( 'Subir Material de Estudio o Cátedra', 'aura' ); ?>
                        </h3>
                        <button type="button" class="btn-close-modal" data-close="#modal-teacher-material" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--at-text-muted);">&times;</button>
                    </div>

                    <form id="form-teacher-upload-mat" enctype="multipart/form-data">
                        <input type="hidden" name="subject_id" id="t-mat-subject-id" value="0">
                        <div class="aura-teacher-modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                            <div class="form-group">
                                <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                    📚 <?php esc_html_e( 'Materia Destino', 'aura' ); ?>
                                </label>
                                <input type="text" id="t-mat-subject-name" readonly class="form-control" style="width: 100%; background: var(--at-bg-card-alt, #f8fafc); font-weight: 600;" value="">
                            </div>

                            <div class="form-group">
                                <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                    🎯 <?php esc_html_e( 'Destino del Material', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="material_type" id="t-mat-type" class="form-control" required style="width: 100%;">
                                    <option value="teacher">📁 <?php esc_html_e( 'Material Pedagógico de Cátedra (Exclusivo Docente / Heredable)', 'aura' ); ?></option>
                                    <option value="student">📖 <?php esc_html_e( 'Material de Estudio para Estudiantes (Público alumnos)', 'aura' ); ?></option>
                                </select>
                                <span style="font-size: 11px; color: var(--at-text-muted); display: block; margin-top: 4px;">
                                    <?php esc_html_e( 'El material docente se conservará como documentación de referencia si la materia se asigna a otro instructor.', 'aura' ); ?>
                                </span>
                            </div>

                            <div class="form-group">
                                <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                    📎 <?php esc_html_e( 'Subir Archivo (Se guardará en Google Drive / Nube)', 'aura' ); ?>
                                </label>
                                <input type="file" name="material_file" id="t-mat-file" class="form-control" style="width: 100%;">
                            </div>

                            <div style="text-align: center; font-size: 12px; color: var(--at-text-muted); font-weight: 600; margin: -4px 0;">
                                — <?php esc_html_e( 'O BIEN VINCULAR ENLACE DRIVE EXISTENTE', 'aura' ); ?> —
                            </div>

                            <div class="form-group">
                                <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                    🔗 <?php esc_html_e( 'Enlace Compartido de Google Drive / Nube', 'aura' ); ?>
                                </label>
                                <input type="url" name="drive_link" id="t-mat-drive-link" class="form-control" placeholder="https://drive.google.com/file/d/..." style="width: 100%;">
                            </div>

                            <div class="form-group">
                                <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-text-primary);">
                                    🏷️ <?php esc_html_e( 'Título descriptivo del recurso', 'aura' ); ?>
                                </label>
                                <input type="text" name="material_title" id="t-mat-title" class="form-control" placeholder="<?php esc_attr_e( 'Ej: Guía didáctica Módulo 2 o Sílabo oficial', 'aura' ); ?>" style="width: 100%;">
                            </div>
                        </div>

                        <div class="aura-teacher-modal-footer">
                            <button type="button" class="btn btn-ghost btn-close-modal" data-close="#modal-teacher-material">
                                <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                            </button>
                            <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-submit-teacher-mat">
                                ☁️ <?php esc_html_e( 'Guardar Material', 'aura' ); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <!-- SCRIPT INTERACTIVO DEL PORTAL DOCENTE -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var $ = jQuery;

            // 1. Manejo de Pestañas
            $('.teacher-tab-btn').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                $('.teacher-tab-btn').removeClass('active').attr('aria-selected', 'false');
                $(this).addClass('active').attr('aria-selected', 'true');

                $('.teacher-tab-content').hide();
                $('#' + target).fadeIn(150);

                if (target === 'tab-teacher-schedule' && window.teacherCalendarInstance) {
                    setTimeout(function() {
                        window.teacherCalendarInstance.updateSize();
                    }, 100);
                }
            });

            // 2. Modales: Abrir / Cerrar
            function openTeacherModal(sel) {
                $(sel).css({ display: 'flex' }).hide().fadeIn(150);
            }
            function closeTeacherModal(sel) {
                $(sel).fadeOut(150);
            }

            $('.btn-close-modal').on('click', function() {
                var sel = $(this).data('close');
                closeTeacherModal(sel);
            });

            $('#btn-teacher-open-task-modal, #btn-teacher-open-first-task').on('click', function() {
                $('#form-teacher-task-create')[0].reset();
                // Fijar fecha límite por defecto a 7 días en el futuro a las 23:59
                var d = new Date();
                d.setDate(d.getDate() + 7);
                d.setHours(23, 59, 0, 0);
                var iso = d.toISOString().slice(0, 16);
                $('#t-tsk-due').val(iso);
                openTeacherModal('#modal-teacher-task');
            });

            $('.btn-new-task-for-subject').on('click', function() {
                var progId = $(this).data('program-id');
                var subjId = $(this).data('subject-id');
                $('#form-teacher-task-create')[0].reset();
                $('#t-tsk-prog').val(progId);
                $('#t-tsk-subj').val(subjId);
                var d = new Date();
                d.setDate(d.getDate() + 7);
                d.setHours(23, 59, 0, 0);
                $('#t-tsk-due').val(d.toISOString().slice(0, 16));
                openTeacherModal('#modal-teacher-task');
            });

            // 3. Guardar Tarea desde el Portal del Instructor
            $('#form-teacher-task-create').on('submit', function(e) {
                e.preventDefault();
                var $btn = $('#btn-save-teacher-task');
                $btn.prop('disabled', true).text('Guardando...');

                var data = $(this).serializeArray();
                data.push({ name: 'action', value: 'aura_cal_save_task' });
                data.push({ name: 'nonce', value: auraCalData.nonce });
                data.push({ name: 'id', value: '0' });

                $.post(auraCalData.ajax_url, data, function(res) {
                    $btn.prop('disabled', false).text('💾 Publicar Tarea');
                    if (res && res.success) {
                        alert(res.data.message || 'Tarea guardada exitosamente.');
                        closeTeacherModal('#modal-teacher-task');
                        location.reload();
                    } else {
                        var err = res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error;
                        alert('⚠️ ' + err);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('💾 Publicar Tarea');
                    alert('Error de conexión al guardar la tarea.');
                });
            });

            // 4. Ver Entregas de Tarea y Calificar
            $('.btn-teacher-view-subs').on('click', function() {
                var taskId = $(this).data('task-id');
                var taskTitle = $(this).data('task-title');
                var maxScore = $(this).data('max-score') || 100;

                $('#t-subs-modal-title').text('📥 Entregas — ' + taskTitle);
                $('#t-subs-modal-sub').text('Puntaje máximo de la actividad: ' + maxScore + ' pts');
                $('#t-subs-body').html('<p style="text-align:center;padding:24px;color:var(--at-text-muted);">Cargando entregas de los estudiantes...</p>');
                openTeacherModal('#modal-teacher-submissions');

                $.post(auraCalData.ajax_url, {
                    action: 'aura_cal_get_submissions',
                    nonce: auraCalData.nonce,
                    task_id: taskId
                }, function(res) {
                    if (res && res.success && res.data.submissions) {
                        var subs = res.data.submissions;
                        if (!subs.length) {
                            $('#t-subs-body').html('<div style="text-align:center;padding:32px;color:var(--at-text-muted);"><div style="font-size:32px;margin-bottom:8px;">📭</div><p>Aún no se han recibido entregas de los estudiantes para esta tarea.</p></div>');
                            return;
                        }

                        var html = '<div style="display:flex;flex-direction:column;gap:16px;">';
                        $.each(subs, function(i, s) {
                            var statusBadge = s.status === 'graded'
                                ? '<span class="adp-badge badge-emerald">Calificada</span>'
                                : (s.status === 'late' ? '<span class="adp-badge badge-amber">Entrega Tardía</span>' : '<span class="adp-badge badge-indigo">Entregada</span>');

                            // Contar palabras si hay texto
                            var wordCount = 0;
                            if (s.submission_text) {
                                wordCount = s.submission_text.trim().split(/\s+/).filter(Boolean).length;
                            }

                            html += '<div class="aura-submission-row">';
                            html += '  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">';
                            html += '    <div>';
                            html += '      <strong style="font-size:15px;color:var(--at-text-primary);">' + (s.first_name || 'Estudiante') + ' ' + (s.last_name || '') + '</strong>';
                            if (s.student_code) {
                                html += '      <span style="font-family:monospace;font-size:11px;color:var(--at-text-muted);margin-left:6px;">(' + s.student_code + ')</span>';
                            }
                            html += '    </div>';
                            html += '    <div>' + statusBadge + '</div>';
                            html += '  </div>';

                            if (s.submission_text) {
                                html += '  <div class="aura-submission-text-box">';
                                html += '    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:11px;color:var(--at-text-muted);">';
                                html += '      <span>✍️ Resumen Escrito:</span>';
                                html += '      <span><strong>' + wordCount + '</strong> palabras redactadas</span>';
                                html += '    </div>';
                                html += '    <div style="font-size:13px;color:var(--at-text-primary);white-space:pre-wrap;line-height:1.6;">' + $('<div>').text(s.submission_text).html() + '</div>';
                                html += '  </div>';
                            }

                            if (s.attachment_urls) {
                                html += '  <div style="margin-bottom:10px;font-size:12px;">';
                                html += '    📎 <strong>Archivo adjunto:</strong> <a href="' + s.attachment_urls + '" target="_blank" style="color:var(--at-primary);text-decoration:underline;">Ver documento entregado</a>';
                                html += '  </div>';
                            }

                            // Formulario de Calificación
                            html += '  <div style="border-top:1px solid var(--at-border);padding-top:12px;margin-top:10px;">';
                            html += '    <form class="form-grade-sub" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">';
                            html += '      <input type="hidden" name="submission_id" value="' + s.id + '">';
                            html += '      <div style="flex:0 0 110px;">';
                            html += '        <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;color:var(--at-text-primary);">Nota / Calificación:</label>';
                            html += '        <input type="number" step="0.1" max="' + maxScore + '" min="0" name="score" value="' + (s.score !== null ? s.score : '') + '" placeholder="0 - ' + maxScore + '" required class="form-control" style="width:100%;">';
                            html += '      </div>';
                            html += '      <div style="flex:1;min-width:200px;">';
                            html += '        <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;color:var(--at-text-primary);">Retroalimentación Docente:</label>';
                            html += '        <input type="text" name="feedback" value="' + (s.feedback || '') + '" placeholder="Ej: Excelente análisis crítico de la lectura..." class="form-control" style="width:100%;">';
                            html += '      </div>';
                            html += '      <div>';
                            html += '        <button type="submit" class="btn btn-emerald" style="padding:8px 14px;font-size:12px;">';
                            html += '          ✅ Asignar Nota';
                            html += '        </button>';
                            html += '      </div>';
                            html += '    </form>';
                            html += '  </div>';

                            html += '</div>';
                        });
                        html += '</div>';
                        $('#t-subs-body').html(html);

                        // Handler submit para calificar
                        $('.form-grade-sub').on('submit', function(e) {
                            e.preventDefault();
                            var $f = $(this);
                            var $b = $f.find('button[type="submit"]');
                            $b.prop('disabled', true).text('Guardando...');

                            var postData = $f.serializeArray();
                            postData.push({ name: 'action', value: 'aura_cal_grade_submission' });
                            postData.push({ name: 'nonce', value: auraCalData.nonce });

                            $.post(auraCalData.ajax_url, postData, function(gRes) {
                                $b.prop('disabled', false).text('✅ Asignar Nota');
                                if (gRes && gRes.success) {
                                    alert(gRes.data.message || 'Calificación guardada correctamente.');
                                } else {
                                    var err = gRes && gRes.data && gRes.data.message ? gRes.data.message : auraCalData.i18n.error;
                                    alert('⚠️ ' + err);
                                }
                            }).fail(function() {
                                $b.prop('disabled', false).text('✅ Asignar Nota');
                                alert('Error al registrar la calificación.');
                            });
                        });
                    }
                });
            });

            // 5. Gestión de Materiales (Docente / Alumnos)
            $('.btn-toggle-mat-panel').on('click', function() {
                var target = $(this).data('target');
                $(target).slideToggle(180);
            });

            $('.btn-open-upload-mat').on('click', function() {
                var subjId = $(this).data('subject-id');
                var subjName = $(this).data('subject-name');
                $('#t-mat-subject-id').val(subjId);
                $('#t-mat-subject-name').val(subjName);
                $('#form-teacher-upload-mat')[0].reset();
                $('#t-mat-subject-id').val(subjId);
                $('#t-mat-subject-name').val(subjName);
                openTeacherModal('#modal-teacher-material');
            });

            $('#form-teacher-upload-mat').on('submit', function(e) {
                e.preventDefault();
                var subjId = $('#t-mat-subject-id').val();
                var fileVal = $('#t-mat-file').val();
                var driveLink = $('#t-mat-drive-link').val();

                if (!fileVal && !driveLink) {
                    alert('Por favor selecciona un archivo o ingresa un enlace de Google Drive.');
                    return;
                }

                var $btn = $('#btn-submit-teacher-mat');
                $btn.prop('disabled', true).text('Subiendo a Drive...');

                var formData = new FormData(this);
                formData.append('action', 'aura_cal_upload_subject_material');
                formData.append('nonce', auraCalData.nonce);

                $.ajax({
                    url: auraCalData.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        $btn.prop('disabled', false).text('☁️ Guardar Material');
                        if (res && res.success) {
                            alert(res.data.message || 'Material guardado correctamente en la nube.');
                            closeTeacherModal('#modal-teacher-material');
                            location.reload();
                        } else {
                            var msg = res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error;
                            alert('⚠️ ' + msg);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('☁️ Guardar Material');
                        alert('Error al subir material a la nube.');
                    }
                });
            });

            // 6. FullCalendar para el Instructor
            var calEl = document.getElementById('aura-teacher-fullcalendar');
            if (calEl && typeof FullCalendar !== 'undefined') {
                window.teacherCalendarInstance = new FullCalendar.Calendar(calEl, {
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
                        week:  'Semana',
                        day:   'Día',
                        list:  'Agenda'
                    },
                    slotMinTime: '06:00:00',
                    slotMaxTime: '22:00:00',
                    allDaySlot: false,
                    nowIndicator: true,
                    eventContent: function(arg) {
                        var p = arg.event.extendedProps || {};
                        var title = p.raw_title || arg.event.title;
                        var timeText = arg.timeText;
                        
                        var avatarImg = '';
                        if (p.primary_avatar) {
                            avatarImg = '<img src="' + p.primary_avatar + '" style="width:18px;height:18px;border-radius:50%;object-fit:cover;flex-shrink:0;border:1px solid rgba(255,255,255,0.7);vertical-align:middle;display:inline-block;" />';
                        }

                        var leadersBadge = '';
                        if (p.student_leaders && p.student_leaders.length > 0) {
                            var firstLeader = p.student_leaders[0];
                            leadersBadge = '<span style="font-size:10px;background:rgba(255,255,255,0.28);border-radius:8px;padding:1px 5px;margin-left:auto;white-space:nowrap;font-weight:600;">⭐ ' + (firstLeader.name ? firstLeader.name.split(' ')[0] : 'Líder') + '</span>';
                        }

                        return {
                            html: '<div style="display:flex;align-items:center;gap:5px;width:100%;overflow:hidden;padding:1px 2px;">' +
                                  avatarImg +
                                  (timeText ? '<span style="font-weight:700;font-size:11px;flex-shrink:0;">' + timeText + '</span>' : '') +
                                  '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;font-weight:600;font-size:12px;">' + title + '</span>' +
                                  leadersBadge +
                                  '</div>'
                        };
                    },
                    events: function(info, successCallback, failureCallback) {
                        $.post(auraCalData.ajax_url, {
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
                        var p = info.event.extendedProps || {};
                        var leadersTxt = '';
                        if (p.student_leaders && p.student_leaders.length > 0) {
                            leadersTxt = '\n⭐ Estudiantes con Responsabilidad:\n' + p.student_leaders.map(function(l) {
                                return ' • ' + l.name + ' — ' + (l.role_label || l.role);
                            }).join('\n');
                        }
                        var msg = '📚 ' + info.event.title + '\n' +
                                  (p.subject_name ? 'Materia: ' + p.subject_name + '\n' : '') +
                                  (p.location ? 'Aula / Salón: ' + p.location + '\n' : '') +
                                  (p.online_url ? 'Enlace Virtual: ' + p.online_url + '\n' : '') +
                                  (p.description ? 'Nota: ' + p.description + '\n' : '') +
                                  leadersTxt;
                        alert(msg);
                    }
                });
                window.teacherCalendarInstance.render();

                // Re-render reactivo instantáneo cuando cambia el tema claro/oscuro
                window.addEventListener('aura:themeChanged', function() {
                    if (window.teacherCalendarInstance) {
                        setTimeout(function() {
                            window.teacherCalendarInstance.render();
                        }, 50);
                    }
                });
            }
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
            return self::shortcode_login();
        }

        $current_user_id = get_current_user_id();

        ob_start();
        ?>
        <div class="aura-student-schedule-wrap" style="margin: 20px 0;">
            <div class="adp-card" style="padding: 20px; border-radius: 12px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                    <h3 class="adp-card-title" style="font-size: 18px; margin: 0; display: inline-flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-calendar-alt" style="font-size: 20px; width: 20px; height: 20px; color: #6366f1;"></span>
                        <span><?php esc_html_e( 'Mi Calendario de Clases y Actividades', 'aura' ); ?></span>
                    </h3>
                    <div style="font-size: 12px; color: var(--at-text-muted, #64748b);">
                        💡 <?php esc_html_e( 'Las clases con una estrella (⭐) indican que tienes o hay compañeros con roles de liderazgo asignados.', 'aura' ); ?>
                    </div>
                </div>
                <div id="aura-student-calendar" style="min-height: 540px;"></div>
            </div>

            <!-- MODAL DE DETALLE DE CLASE PARA ESTUDIANTE -->
            <div id="modal-student-event-detail" class="aura-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 16px;">
                <div class="aura-modal-container" style="max-width: 520px; width: 100%; background: var(--aura-surface-card, #ffffff); border-radius: 14px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); border: 1px solid var(--aura-border, #e2e8f0);">
                    <div style="padding: 16px 20px; border-bottom: 1px solid var(--aura-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center; background: var(--aura-surface-alt, #f8fafc);">
                        <h4 id="st-det-title" style="margin: 0; font-size: 17px; font-weight: 700; color: var(--aura-text-primary, #0f172a);">
                            Detalle de Clase
                        </h4>
                        <button type="button" class="btn-close-st-modal" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--aura-text-muted, #64748b); line-height: 1;">&times;</button>
                    </div>

                    <div style="padding: 20px; display: flex; flex-direction: column; gap: 14px; max-height: 70vh; overflow-y: auto;">
                        <!-- Banner destacado si el alumno es líder de la sesión -->
                        <div id="st-det-my-role-banner" style="display: none; padding: 12px 14px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); color: #b45309; font-size: 13px; font-weight: 600;">
                            🌟 <span id="st-det-my-role-text">Tienes una responsabilidad asignada en esta clase</span>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px;">
                            <div>
                                <span style="color: var(--aura-text-muted, #64748b); font-size: 11px; text-transform: uppercase; font-weight: 600; display: block;">Materia</span>
                                <strong id="st-det-subject" style="color: var(--aura-text-primary, #0f172a);">-</strong>
                            </div>
                            <div>
                                <span style="color: var(--aura-text-muted, #64748b); font-size: 11px; text-transform: uppercase; font-weight: 600; display: block;">Horario</span>
                                <strong id="st-det-time" style="color: var(--aura-text-primary, #0f172a);">-</strong>
                            </div>
                        </div>

                        <div style="font-size: 13px;">
                            <span style="color: var(--aura-text-muted, #64748b); font-size: 11px; text-transform: uppercase; font-weight: 600; display: block;">Profesor Titular</span>
                            <div id="st-det-teacher" style="margin-top: 4px; display: flex; align-items: center; gap: 8px;">-</div>
                        </div>

                        <div id="st-det-location-box" style="font-size: 13px;">
                            <span style="color: var(--aura-text-muted, #64748b); font-size: 11px; text-transform: uppercase; font-weight: 600; display: block;">Salón / Ubicación</span>
                            <div id="st-det-location" style="margin-top: 2px;">-</div>
                        </div>

                        <div id="st-det-online-box" style="display: none; padding: 10px 12px; border-radius: 8px; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2);">
                            <span style="font-size: 12px; font-weight: 600; color: #4f46e5; display: block; margin-bottom: 4px;">💻 Clase Virtual En Línea</span>
                            <a id="st-det-online-link" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-indigo" style="font-size: 12px; padding: 5px 12px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                                🚀 Unirse a la Clase Virtual
                            </a>
                        </div>

                        <!-- Sección de Líderes y Monitores Estudiantiles -->
                        <div id="st-det-leaders-box" style="display: none; border-top: 1px solid var(--aura-border, #e2e8f0); padding-top: 12px;">
                            <span style="color: var(--aura-text-muted, #64748b); font-size: 11px; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 8px;">
                                ⭐ Estudiantes Asignados a la Actividad
                            </span>
                            <div id="st-det-leaders-list" style="display: flex; flex-direction: column; gap: 6px;"></div>
                        </div>
                    </div>

                    <div style="padding: 12px 20px; border-top: 1px solid var(--aura-border, #e2e8f0); background: var(--aura-surface-alt, #f8fafc); text-align: right;">
                        <button type="button" class="btn btn-ghost btn-close-st-modal">
                            <?php esc_html_e( 'Cerrar', 'aura' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var $ = jQuery;
            var calEl = document.getElementById('aura-student-calendar');
            if (!calEl || typeof FullCalendar === 'undefined') return;

            var currentUserId = <?php echo intval( $current_user_id ); ?>;

            function closeStModal() {
                $('#modal-student-event-detail').fadeOut(150);
            }
            $('.btn-close-st-modal').on('click', closeStModal);

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
                eventContent: function(arg) {
                    var p = arg.event.extendedProps || {};
                    var title = p.raw_title || arg.event.title;
                    var timeText = arg.timeText;
                    
                    var avatarImg = '';
                    if (p.primary_avatar) {
                        avatarImg = '<img src="' + p.primary_avatar + '" style="width:18px;height:18px;border-radius:50%;object-fit:cover;flex-shrink:0;border:1px solid rgba(255,255,255,0.7);vertical-align:middle;display:inline-block;" />';
                    }

                    var isMeLeader = false;
                    var leaderLabel = '';
                    if (p.student_leaders && p.student_leaders.length > 0) {
                        $.each(p.student_leaders, function(idx, ld) {
                            if (parseInt(ld.student_id, 10) === currentUserId) {
                                isMeLeader = true;
                                leaderLabel = ld.role_label || 'Líder';
                            }
                        });
                        if (!leaderLabel) {
                            leaderLabel = p.student_leaders[0].name ? p.student_leaders[0].name.split(' ')[0] : 'Líder';
                        }
                    }

                    var leadersBadge = '';
                    if (leaderLabel) {
                        var bg = isMeLeader ? 'background:#f59e0b;color:#ffffff;' : 'background:rgba(255,255,255,0.3);color:inherit;';
                        leadersBadge = '<span style="font-size:10px;' + bg + 'border-radius:8px;padding:1px 5px;margin-left:auto;white-space:nowrap;font-weight:700;">⭐ ' + leaderLabel + '</span>';
                    }

                    return {
                        html: '<div style="display:flex;align-items:center;gap:5px;width:100%;overflow:hidden;padding:1px 2px;">' +
                              avatarImg +
                              (timeText ? '<span style="font-weight:700;font-size:11px;flex-shrink:0;">' + timeText + '</span>' : '') +
                              '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;font-weight:600;font-size:12px;">' + title + '</span>' +
                              leadersBadge +
                              '</div>'
                    };
                },
                events: function(info, successCallback, failureCallback) {
                    $.post(auraCalData.ajax_url, {
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
                    var p = info.event.extendedProps || {};
                    var d = info.event;

                    $('#st-det-title').text(d.title);
                    $('#st-det-subject').text(p.subject_name || 'General');

                    var startFormatted = d.start ? d.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                    var endFormatted = d.end ? d.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                    $('#st-det-time').text(startFormatted + (endFormatted ? ' - ' + endFormatted : ''));

                    // Instructor con Avatar
                    if (p.primary_name) {
                        var teacherAvatar = p.primary_avatar ? '<img src="' + p.primary_avatar + '" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">' : '👨‍🏫';
                        $('#st-det-teacher').html(teacherAvatar + ' <strong>' + p.primary_name + '</strong>');
                    } else {
                        $('#st-det-teacher').text('Por designar');
                    }

                    // Ubicación
                    if (p.location) {
                        $('#st-det-location').text(p.location);
                        $('#st-det-location-box').show();
                    } else {
                        $('#st-det-location-box').hide();
                    }

                    // Enlace Virtual
                    if (p.online_url) {
                        $('#st-det-online-link').attr('href', p.online_url);
                        $('#st-det-online-box').show();
                    } else {
                        $('#st-det-online-box').hide();
                    }

                    // Roles de Liderazgo
                    var myRole = null;
                    if (p.student_leaders && p.student_leaders.length > 0) {
                        var html = '';
                        $.each(p.student_leaders, function(i, l) {
                            if (parseInt(l.student_id, 10) === currentUserId) {
                                myRole = l.role_label || l.role;
                            }
                            var lAvatar = l.avatar_url ? '<img src="' + l.avatar_url + '" style="width:22px;height:22px;border-radius:50%;object-fit:cover;">' : '👤';
                            html += '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:5px 8px;background:var(--aura-surface-alt,#f8fafc);border-radius:8px;border:1px solid var(--aura-border,#e2e8f0);font-size:12px;">';
                            html += '  <div style="display:flex;align-items:center;gap:6px;">' + lAvatar + ' <strong>' + l.name + '</strong></div>';
                            html += '  <span style="background:rgba(99,102,241,0.12);color:#4f46e5;font-weight:600;padding:2px 8px;border-radius:6px;font-size:11px;">' + (l.role_label || l.role) + '</span>';
                            html += '</div>';
                        });
                        $('#st-det-leaders-list').html(html);
                        $('#st-det-leaders-box').show();
                    } else {
                        $('#st-det-leaders-box').hide();
                    }

                    // Banner personal
                    if (myRole) {
                        $('#st-det-my-role-text').html('🎯 <strong>¡Fuiste asignado como ' + myRole + ' para esta sesión!</strong> Prepárate para guiar y colaborar con el grupo.');
                        $('#st-det-my-role-banner').show();
                    } else {
                        $('#st-det-my-role-banner').hide();
                    }

                    $('#modal-student-event-detail').css({ display: 'flex' }).hide().fadeIn(150);
                }
            });
            calendar.render();
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
