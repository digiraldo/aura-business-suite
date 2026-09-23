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
            'first_day'           => (int) get_option( 'start_of_week', 1 ),
            'date_format'         => get_option( 'date_format', 'd-m-Y' ),
            'time_format'         => get_option( 'time_format', 'H:i' ),
            'timezone'            => wp_timezone_string(),
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
            $user    = wp_get_current_user();
            $user_id = $user->ID;

            // Sincronización completa y centralizada con el sistema CBAC
            $access = class_exists( 'Aura_Roles_Manager' )
                ? Aura_Roles_Manager::get_user_portal_access( $user )
                : [
                    'can_admin'          => current_user_can( 'manage_options' ),
                    'is_teacher'         => current_user_can( 'manage_options' ),
                    'is_student'         => current_user_can( 'aura_students_view_own' ),
                    'can_student_portal' => current_user_can( 'manage_options' ) || current_user_can( 'aura_students_view_own' ),
                    'has_certificates'   => false,
                    'has_forms'          => false,
                    'primary_role'       => [
                        'label'       => __( 'Miembro Institucional', 'aura' ),
                        'badge_class' => 'badge-indigo',
                        'icon'        => 'dashicons-admin-users',
                        'color'       => '#4f46e5',
                        'bg'          => 'rgba(79, 70, 229, 0.12)',
                    ],
                    'admin_panel_url'    => admin_url(),
                    'teacher_portal_url' => home_url( '/portal-del-instructor/' ),
                    'student_portal_url' => home_url( '/portal-del-estudiante/' ),
                    'certificates_url'   => '',
                    'forms_portal_url'   => '',
                    'logout_url'         => wp_logout_url( get_permalink() ?: home_url() ),
                ];

            $can_admin          = $access['can_admin'];
            $is_teacher         = $access['is_teacher'];
            $is_student         = $access['is_student'];
            $can_student_portal = $access['can_student_portal'];
            $has_certificates   = $access['has_certificates'];
            $has_forms          = $access['has_forms'];
            $role_info          = $access['primary_role'];
            $role_name          = $role_info['label'];
            $role_badge         = $role_info['badge_class'];
            $role_icon          = $role_info['icon'];
            $icon_color         = $role_info['color'];
            $icon_bg            = $role_info['bg'];
            $portal_url         = $access['student_portal_url'];
            $teacher_portal_url = $access['teacher_portal_url'];
            $certificates_url   = $access['certificates_url'];
            $forms_portal_url   = $access['forms_portal_url'];
            $logout_url         = $access['logout_url'];

            // Determinar si tiene Imagen de Perfil real o si mostramos Ícono Predeterminado por Rol
            $profile_photo_url = '';

            // a) Desde ficha de estudiante
            $student_record = null;
            if ( class_exists( 'Aura_Students_Frontend' ) && method_exists( 'Aura_Students_Frontend', 'get_student_by_wp_user' ) ) {
                $student_record = Aura_Students_Frontend::get_student_by_wp_user( $user_id );
            }
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
                            <a href="<?php echo esc_url( $access['admin_panel_url'] ); ?>" class="btn btn-emerald btn-shimmer btn-lift" style="padding: 12px 20px; font-size: 14px; font-weight: 600; text-decoration: none; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
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

                        <?php if ( $can_student_portal ) : ?>
                            <a href="<?php echo esc_url( $portal_url ); ?>" class="btn btn-violet btn-shimmer btn-lift" style="padding: 12px 20px; font-size: 14px; font-weight: 600; text-decoration: none; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-id-alt"></span>
                                <span><?php esc_html_e( 'Ir a mi Portal de Estudiante', 'aura' ); ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if ( $has_certificates && ! empty( $certificates_url ) ) : ?>
                            <a href="<?php echo esc_url( $certificates_url ); ?>" class="btn btn-amber btn-shimmer btn-lift" style="padding: 12px 20px; font-size: 14px; font-weight: 600; text-decoration: none; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-awards"></span>
                                <span><?php esc_html_e( 'Mis Certificados y Diplomas', 'aura' ); ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if ( $has_forms && ! empty( $forms_portal_url ) ) : ?>
                            <a href="<?php echo esc_url( $forms_portal_url ); ?>" class="btn btn-cyan btn-shimmer btn-lift" style="padding: 12px 20px; font-size: 14px; font-weight: 600; text-decoration: none; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-feedback"></span>
                                <span><?php esc_html_e( 'Portal de Formularios y Encuestas', 'aura' ); ?></span>
                            </a>
                        <?php endif; ?>

                        <a href="<?php echo esc_url( $logout_url ); ?>" class="btn btn-ghost" style="padding: 10px 16px; font-size: 13px; text-decoration: none; margin-top: 8px; justify-content: center; display: inline-flex; align-items: center; gap: 6px;">
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
            $subj_cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table_subj}`" );
            $t_col = in_array( 'default_teacher_id', $subj_cols, true ) ? 's.default_teacher_id' : ( in_array( 'teacher_id', $subj_cols, true ) ? 's.teacher_id' : '0' );
            $ts_col = in_array( 'teachers', $subj_cols, true ) ? 's.teachers' : ( in_array( 'teacher_ids', $subj_cols, true ) ? 's.teacher_ids' : "''" );

            $user_id_like = '%' . $wpdb->esc_like( '"' . $user_id . '"' ) . '%';
            $my_subjects = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.*, p.name AS program_name, p.code AS program_code
                 FROM {$table_subj} s
                 LEFT JOIN {$table_prog} p ON p.id = s.program_id
                 WHERE ({$t_col} = %d OR {$ts_col} LIKE %s) AND s.deleted_at IS NULL
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
            $t_col = isset( $t_col ) ? $t_col : 's.default_teacher_id';
            $ts_col = isset( $ts_col ) ? $ts_col : 's.teachers';

            $my_tasks = $wpdb->get_results( $wpdb->prepare(
                "SELECT t.*, p.name AS program_name, p.code AS program_code, s.name AS subject_name
                        {$book_cols},
                        (SELECT COUNT(*) FROM {$table_subs} sub WHERE sub.task_id = t.id) AS submissions_count
                 FROM {$table_tasks} t
                 LEFT JOIN {$table_prog} p ON p.id = t.program_id
                 LEFT JOIN {$table_subj} s ON s.id = t.subject_id
                 {$book_join}
                 WHERE (t.created_by = %d OR {$t_col} = %d OR {$ts_col} LIKE %s) AND t.deleted_at IS NULL
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
        <script>
            window.auraTeacherBooks = <?php echo wp_json_encode( array_map( function( $b ) {
                return [
                    'id'     => (int) $b->id,
                    'title'  => $b->title,
                    'author' => $b->author ?? '',
                    'isbn'   => $b->isbn ?? '',
                ];
            }, $library_books ) ); ?>;
        </script>
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
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-secondary btn-sm" id="btn-toggle-teacher-fullscreen" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; padding: 4px 10px; border-radius: 6px; font-size: 12px;">
                                <span class="dashicons dashicons-editor-expand" style="font-size: 16px; width: 16px; height: 16px;"></span> <span class="fs-text"><?php esc_html_e( 'Pantalla Completa', 'aura' ); ?></span>
                            </button>
                            <a href="https://calendar.google.com/calendar/u/0/r" target="_blank" rel="noopener noreferrer" id="btn-open-teacher-gcal" class="btn btn-secondary btn-sm aura-btn-gcal-link" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; padding: 4px 10px; border-radius: 6px; font-size: 12px; text-decoration: none; color: inherit;" title="<?php esc_attr_e( 'Abrir esta misma fecha y vista en Google Calendar', 'aura' ); ?>">
                                <span style="font-size: 13px;">📅</span> <?php esc_html_e( 'Google Calendar', 'aura' ); ?> ↗
                            </a>
                            <div style="font-size: 12px; color: var(--at-text-muted);">
                                💡 <?php esc_html_e( 'Haz clic sobre una clase para ver el aula, enlace virtual o tomar lista rápida.', 'aura' ); ?>
                            </div>
                        </div>
                    </div>
                    <div id="aura-teacher-fullcalendar" style="min-height: 600px;"></div>
                    <?php include AURA_PLUGIN_DIR . 'templates/calendar/modal-event-detail.php'; ?>
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
                                        <div style="display: flex; gap: 6px; align-items: center;">
                                            <button type="button" class="btn btn-ghost btn-toggle-mat-panel" data-target="#<?php echo esc_attr( $collapse_id ); ?>" style="font-size: 11px; padding: 4px 8px;">
                                                📂 <?php esc_html_e( 'Recursos', 'aura' ); ?> (<?php echo $t_count + $s_count; ?>)
                                            </button>
                                            <button type="button" class="btn btn-ghost btn-open-upload-mat" data-subject-id="<?php echo esc_attr( $s->id ); ?>" data-subject-name="<?php echo esc_attr( $s->name ); ?>" style="font-size: 11px; padding: 4px 8px; color: #4f46e5;">
                                                ☁️ <?php esc_html_e( 'Subir', 'aura' ); ?>
                                            </button>
                                        </div>
                                        <div style="display: flex; gap: 6px; align-items: center;">
                                            <button type="button" class="btn btn-indigo btn-lift btn-teacher-view-students" data-program-id="<?php echo esc_attr( $s->program_id ); ?>" data-subject-id="<?php echo esc_attr( $s->id ); ?>" data-program-name="<?php echo esc_attr( $s->program_name ); ?>" data-subject-name="<?php echo esc_attr( $s->name ); ?>" style="font-size: 11px; padding: 4px 10px; font-weight: 600;">
                                                👥 <?php esc_html_e( 'Estudiantes', 'aura' ); ?>
                                            </button>
                                            <button type="button" class="btn btn-ghost btn-new-task-for-subject" data-program-id="<?php echo esc_attr( $s->program_id ); ?>" data-subject-id="<?php echo esc_attr( $s->id ); ?>" style="font-size: 11px; padding: 4px 8px;">
                                                ➕ <?php esc_html_e( 'Crear Tarea', 'aura' ); ?>
                                            </button>
                                        </div>
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
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <?php if ( ! empty( $my_subjects ) ) : ?>
                                <button type="button" class="btn btn-ghost btn-teacher-view-students" data-program-id="<?php echo esc_attr( $my_subjects[0]->program_id ); ?>" data-subject-id="<?php echo esc_attr( $my_subjects[0]->id ); ?>" data-program-name="<?php echo esc_attr( $my_subjects[0]->program_name ); ?>" data-subject-name="<?php echo esc_attr( $my_subjects[0]->name ); ?>" style="font-size: 12.5px; padding: 7px 12px; border: 1px solid var(--at-border);">
                                    👥 <?php esc_html_e( 'Nómina de Estudiantes', 'aura' ); ?>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-indigo btn-shimmer btn-lift" id="btn-teacher-open-task-modal">
                                ➕ <?php esc_html_e( 'Asignar Nueva Tarea', 'aura' ); ?>
                            </button>
                        </div>
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
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; flex-wrap: wrap; gap: 6px;">
                                            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                                <span class="adp-badge badge-indigo"><?php echo esc_html( $t->program_code ?: $t->program_name ); ?></span>
                                                <?php if ( ! empty( $t->target_type ) && $t->target_type === 'differentiated' ) : ?>
                                                    <span class="adp-badge badge-purple" title="<?php esc_attr_e( 'Libro o tema diferente por estudiante', 'aura' ); ?>">🎲 <?php esc_html_e( 'Diferenciada', 'aura' ); ?></span>
                                                <?php elseif ( ! empty( $t->target_type ) && $t->target_type === 'individual' ) : ?>
                                                    <span class="adp-badge badge-amber" title="<?php esc_attr_e( 'Asignada a estudiantes específicos', 'aura' ); ?>">👤 <?php esc_html_e( 'Individual', 'aura' ); ?></span>
                                                <?php else : ?>
                                                    <span class="adp-badge badge-slate" title="<?php esc_attr_e( 'Asignada a toda la cohorte', 'aura' ); ?>">👥 <?php esc_html_e( 'Grupal', 'aura' ); ?></span>
                                                <?php endif; ?>
                                            </div>
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

                            <!-- MODALIDAD DE ASIGNACIÓN: GRUPAL / INDIVIDUAL / DIFERENCIADA -->
                            <div class="form-group" style="background: var(--at-bg-card-alt, #f8fafc); padding: 14px; border-radius: 10px; border: 1px solid var(--at-border);">
                                <label style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 8px; color: var(--at-text-primary);">
                                    🎯 <?php esc_html_e( 'Modalidad de Asignación', 'aura' ); ?>
                                </label>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                                    <label class="aura-target-type-card" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; border: 1px solid var(--at-border); border-radius: 8px; cursor: pointer; background: var(--at-bg-card);">
                                        <input type="radio" name="target_type" value="all" checked style="margin: 0;">
                                        <div>
                                            <div style="font-weight: 600; font-size: 12.5px; color: var(--at-text-primary);">👥 <?php esc_html_e( 'Grupal', 'aura' ); ?></div>
                                            <div style="font-size: 11px; color: var(--at-text-muted);"><?php esc_html_e( 'Toda la cohorte', 'aura' ); ?></div>
                                        </div>
                                    </label>
                                    <label class="aura-target-type-card" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; border: 1px solid var(--at-border); border-radius: 8px; cursor: pointer; background: var(--at-bg-card);">
                                        <input type="radio" name="target_type" value="individual" style="margin: 0;">
                                        <div>
                                            <div style="font-weight: 600; font-size: 12.5px; color: var(--at-text-primary);">👤 <?php esc_html_e( 'Individual', 'aura' ); ?></div>
                                            <div style="font-size: 11px; color: var(--at-text-muted);"><?php esc_html_e( 'Alumnos específicos', 'aura' ); ?></div>
                                        </div>
                                    </label>
                                    <label class="aura-target-type-card" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; border: 1px solid var(--at-border); border-radius: 8px; cursor: pointer; background: var(--at-bg-card);">
                                        <input type="radio" name="target_type" value="differentiated" style="margin: 0;">
                                        <div>
                                            <div style="font-weight: 600; font-size: 12.5px; color: var(--at-text-primary);">🎲 <?php esc_html_e( 'Diferenciada', 'aura' ); ?></div>
                                            <div style="font-size: 11px; color: var(--at-text-muted);"><?php esc_html_e( 'Libro por alumno', 'aura' ); ?></div>
                                        </div>
                                    </label>
                                </div>

                                <!-- SECCIÓN TARGETING: INDIVIDUAL -->
                                <div id="section-targeting-individual" style="display: none; margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--at-border);">
                                    <div style="font-size: 12px; font-weight: 600; margin-bottom: 8px; color: var(--at-text-primary); display: flex; justify-content: space-between; align-items: center;">
                                        <span><?php esc_html_e( 'Selecciona los estudiantes que deben realizar esta actividad:', 'aura' ); ?></span>
                                        <button type="button" class="btn btn-ghost" id="btn-toggle-all-indiv" style="font-size: 11px; padding: 2px 8px;"><?php esc_html_e( 'Marcar todos', 'aura' ); ?></button>
                                    </div>
                                    <div id="container-students-individual" style="max-height: 180px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; padding: 8px; background: var(--at-bg-card); border-radius: 6px; border: 1px solid var(--at-border);">
                                        <p style="font-size: 12px; color: var(--at-text-muted); margin: 0; text-align: center; padding: 8px;"><?php esc_html_e( 'Selecciona primero un Programa Académico arriba.', 'aura' ); ?></p>
                                    </div>
                                </div>

                                <!-- SECCIÓN TARGETING: DIFERENCIADA -->
                                <div id="section-targeting-differentiated" style="display: none; margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--at-border);">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
                                        <div>
                                            <div style="font-size: 12.5px; font-weight: 700; color: var(--at-text-primary);">🎲 <?php esc_html_e( 'Asignación de Libro o Tema por Alumno', 'aura' ); ?></div>
                                            <div style="font-size: 11px; color: var(--at-text-muted);"><?php esc_html_e( 'Asigna un libro diferente a cada estudiante para su control de lectura.', 'aura' ); ?></div>
                                        </div>
                                        <button type="button" class="btn btn-purple btn-lift" id="btn-auto-assign-books" style="font-size: 11.5px; padding: 4px 10px;">
                                            🎲 <?php esc_html_e( 'Repartir Libros Aleatoriamente', 'aura' ); ?>
                                        </button>
                                    </div>
                                    <div id="container-students-differentiated" style="max-height: 250px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; padding: 8px; background: var(--at-bg-card); border-radius: 6px; border: 1px solid var(--at-border);">
                                        <p style="font-size: 12px; color: var(--at-text-muted); margin: 0; text-align: center; padding: 8px;"><?php esc_html_e( 'Selecciona primero un Programa Académico arriba.', 'aura' ); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- VINCULACIÓN CON LIBRO DE BIBLIOTECA (GENERAL / COHORTE) -->
                            <div id="section-general-book" class="form-group" style="background: var(--at-chip-bg); padding: 14px; border-radius: 10px; border: 1px solid var(--at-chip-border);">
                                <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px; color: var(--at-primary);">
                                    📖 <?php esc_html_e( 'Asignar Libro de la Biblioteca (Control de Lectura Grupal)', 'aura' ); ?>
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

            <!-- ══════════════════════════════════════════════════════════════
                 MODAL DOCENTE: CONSULTA DE ESTUDIANTES Y ASIGNACIÓN POR MATERIA
                 ══════════════════════════════════════════════════════════════ -->
            <div id="modal-teacher-students" class="aura-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 16px;">
                <div class="aura-modal-container aura-teacher-modal" style="max-width: 840px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; border-radius: 14px;">
                    <div class="aura-teacher-modal-header" style="flex-shrink: 0;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <h3 id="t-stds-modal-title" style="margin: 0; font-size: 17px; font-weight: 700; color: var(--at-text-primary);">
                                    👥 <?php esc_html_e( 'Nómina de Estudiantes Matriculados', 'aura' ); ?>
                                </h3>
                                <span id="t-stds-count-badge" class="adp-badge badge-indigo" style="font-size: 11px;">0 alumnos</span>
                            </div>
                            <div id="t-stds-modal-subtitle" style="font-size: 12px; color: var(--at-text-muted); margin-top: 3px;">
                                <?php esc_html_e( 'Programa y Materia', 'aura' ); ?>
                            </div>
                        </div>
                        <button type="button" class="btn-close-modal" data-close="#modal-teacher-students" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--at-text-muted);">&times;</button>
                    </div>

                    <!-- BARRA DE BÚSQUEDA Y ASIGNACIONES RÁPIDAS -->
                    <div style="padding: 12px 18px; background: var(--at-bg-card-alt, #f8fafc); border-bottom: 1px solid var(--at-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; flex-shrink: 0;">
                        <div style="flex: 1; min-width: 220px; max-width: 360px;">
                            <input type="text" id="t-stds-search" class="form-control" placeholder="<?php esc_attr_e( '🔍 Buscar por nombre, código o correo...', 'aura' ); ?>" style="width: 100%; font-size: 12.5px; padding: 6px 12px;">
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <button type="button" class="btn btn-indigo btn-lift" id="btn-t-stds-assign-all" style="font-size: 11.5px; padding: 6px 12px;">
                                ➕ <?php esc_html_e( 'Asignar Tarea Grupal', 'aura' ); ?>
                            </button>
                            <button type="button" class="btn btn-purple btn-lift" id="btn-t-stds-assign-diff" style="font-size: 11.5px; padding: 6px 12px;">
                                🎲 <?php esc_html_e( 'Repartir Libros', 'aura' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- CONTENEDOR CON LA LISTA DE ESTUDIANTES -->
                    <div id="t-stds-body" class="aura-teacher-modal-body" style="flex: 1; overflow-y: auto; padding: 16px 20px; display: flex; flex-direction: column; gap: 10px;">
                        <p style="text-align: center; color: var(--at-text-muted); padding: 30px;"><?php esc_html_e( 'Cargando nómina...', 'aura' ); ?></p>
                    </div>

                    <div class="aura-teacher-modal-footer" style="flex-shrink: 0;">
                        <button type="button" class="btn btn-ghost btn-close-modal" data-close="#modal-teacher-students">
                            <?php esc_html_e( 'Cerrar', 'aura' ); ?>
                        </button>
                    </div>
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

            var currentProgramStudents = [];
            var activeTeacherProgId = 0;
            var activeTeacherSubjId = 0;
            var activeTeacherProgName = '';
            var activeTeacherSubjName = '';
            var cachedTeacherStudents = [];

            // Alternar secciones según Modalidad de Asignación
            function updateTargetingUI() {
                var targetType = $('input[name="target_type"]:checked').val() || 'all';
                if (targetType === 'all') {
                    $('#section-targeting-individual').slideUp(150);
                    $('#section-targeting-differentiated').slideUp(150);
                    $('#section-general-book').slideDown(150);
                } else if (targetType === 'individual') {
                    $('#section-targeting-individual').slideDown(150);
                    $('#section-targeting-differentiated').slideUp(150);
                    $('#section-general-book').slideDown(150);
                    loadProgramStudentsIfNeeded();
                } else if (targetType === 'differentiated') {
                    $('#section-targeting-individual').slideUp(150);
                    $('#section-targeting-differentiated').slideDown(150);
                    $('#section-general-book').slideUp(150);
                    loadProgramStudentsIfNeeded();
                }
            }

            $('input[name="target_type"]').on('change', updateTargetingUI);

            // Cargar estudiantes del programa seleccionado
            function loadProgramStudentsIfNeeded(callback) {
                var progId = $('#t-tsk-prog').val();
                var subjId = $('#t-tsk-subj').val();

                if (!progId) {
                    $('#container-students-individual').html('<p style="font-size:12px;color:var(--at-text-muted);text-align:center;padding:8px;">Selecciona primero un Programa Académico arriba.</p>');
                    $('#container-students-differentiated').html('<p style="font-size:12px;color:var(--at-text-muted);text-align:center;padding:8px;">Selecciona primero un Programa Académico arriba.</p>');
                    currentProgramStudents = [];
                    return;
                }

                $('#container-students-individual').html('<p style="font-size:12px;color:var(--at-text-muted);text-align:center;padding:8px;">⏳ Cargando alumnos matriculados...</p>');
                $('#container-students-differentiated').html('<p style="font-size:12px;color:var(--at-text-muted);text-align:center;padding:8px;">⏳ Cargando alumnos matriculados...</p>');

                $.post(auraCalData.ajax_url, {
                    action: 'aura_cal_get_program_students',
                    nonce: auraCalData.nonce,
                    program_id: progId,
                    subject_id: subjId
                }, function(res) {
                    if (res && res.success && res.data.students) {
                        currentProgramStudents = res.data.students;
                        renderTargetingStudents(currentProgramStudents);
                        if (typeof callback === 'function') {
                            callback(currentProgramStudents);
                        }
                    } else {
                        var msg = (res && res.data && res.data.message) ? res.data.message : 'No se encontraron alumnos en este programa.';
                        $('#container-students-individual').html('<p style="font-size:12px;color:var(--at-text-muted);text-align:center;padding:8px;">' + msg + '</p>');
                        $('#container-students-differentiated').html('<p style="font-size:12px;color:var(--at-text-muted);text-align:center;padding:8px;">' + msg + '</p>');
                    }
                }).fail(function() {
                    $('#container-students-individual').html('<p style="font-size:12px;color:#ef4444;text-align:center;padding:8px;">Error al cargar alumnos.</p>');
                    $('#container-students-differentiated').html('<p style="font-size:12px;color:#ef4444;text-align:center;padding:8px;">Error al cargar alumnos.</p>');
                });
            }

            $('#t-tsk-prog, #t-tsk-subj').on('change', function() {
                var targetType = $('input[name="target_type"]:checked').val() || 'all';
                if (targetType !== 'all') {
                    loadProgramStudentsIfNeeded();
                }
            });

            // ══════════════════════════════════════════════════════════════
            // GESTIÓN DE NÓMINA DE ESTUDIANTES POR MATERIA Y PROGRAMA
            // ══════════════════════════════════════════════════════════════
            $('.btn-teacher-view-students').on('click', function() {
                activeTeacherProgId = $(this).data('program-id') || 0;
                activeTeacherSubjId = $(this).data('subject-id') || 0;
                activeTeacherProgName = $(this).data('program-name') || 'Programa General';
                activeTeacherSubjName = $(this).data('subject-name') || '';

                $('#t-stds-modal-title').text('👥 Nómina de Estudiantes — ' + (activeTeacherSubjName ? activeTeacherSubjName : 'Programa'));
                $('#t-stds-modal-subtitle').html('🎓 <strong>' + activeTeacherProgName + '</strong>' + (activeTeacherSubjName ? ' &bull; 📚 ' + activeTeacherSubjName : ''));
                $('#t-stds-search').val('');
                $('#t-stds-body').html('<p style="text-align:center;padding:30px;color:var(--at-text-muted);">⏳ Consultando estudiantes matriculados en este programa...</p>');
                $('#t-stds-count-badge').text('Cargando...');
                openTeacherModal('#modal-teacher-students');

                $.post(auraCalData.ajax_url, {
                    action: 'aura_cal_get_program_students',
                    nonce: auraCalData.nonce,
                    program_id: activeTeacherProgId,
                    subject_id: activeTeacherSubjId
                }, function(res) {
                    if (res && res.success && res.data.students) {
                        cachedTeacherStudents = res.data.students;
                        renderTeacherStudentsRoster(cachedTeacherStudents);
                    } else {
                        var msg = (res && res.data && res.data.message) ? res.data.message : 'No se encontraron estudiantes matriculados en este programa.';
                        $('#t-stds-body').html('<div style="text-align:center;padding:36px;color:var(--at-text-muted);"><div style="font-size:36px;margin-bottom:8px;">👥</div><p>' + msg + '</p></div>');
                        $('#t-stds-count-badge').text('0 alumnos');
                        cachedTeacherStudents = [];
                    }
                }).fail(function() {
                    $('#t-stds-body').html('<p style="text-align:center;padding:30px;color:#ef4444;">Error de red al consultar estudiantes.</p>');
                    $('#t-stds-count-badge').text('Error');
                    cachedTeacherStudents = [];
                });
            });

            function renderTeacherStudentsRoster(students) {
                if (!students || !students.length) {
                    $('#t-stds-body').html('<div style="text-align:center;padding:30px;color:var(--at-text-muted);"><p>No se encontraron estudiantes que coincidan con la búsqueda.</p></div>');
                    $('#t-stds-count-badge').text('0 alumnos');
                    return;
                }

                $('#t-stds-count-badge').text(students.length + (students.length === 1 ? ' alumno' : ' alumnos'));

                var html = '';
                $.each(students, function(i, st) {
                    var codeTag = st.student_code ? ' <span style="font-family:monospace;font-size:11px;color:var(--at-text-muted);background:var(--at-bg-card);padding:1px 6px;border-radius:4px;border:1px solid var(--at-border);">' + st.student_code + '</span>' : '';
                    var contactItems = [];
                    if (st.email) {
                        contactItems.push('✉️ <a href="mailto:' + st.email + '" style="color:var(--at-primary);text-decoration:none;">' + st.email + '</a>');
                    }
                    if (st.phone) {
                        contactItems.push('📞 <a href="https://wa.me/' + st.phone.replace(/[^0-9]/g, '') + '" target="_blank" rel="noopener noreferrer" style="color:var(--at-primary);text-decoration:none;">' + st.phone + '</a>');
                    }

                    html += '<div class="aura-student-roster-row" style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:var(--at-bg-card-alt,#f8fafc);border:1px solid var(--at-border);border-radius:10px;gap:12px;flex-wrap:wrap;">';
                    html += '  <div style="display:flex;align-items:center;gap:12px;">';
                    html += '    <img src="' + (st.avatar || '') + '" alt="' + (st.full_name || '') + '" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid #6366f1;box-shadow:0 2px 8px rgba(99,102,241,0.2);">';
                    html += '    <div>';
                    html += '      <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">';
                    html += '        <strong style="font-size:14px;color:var(--at-text-primary);">' + (st.full_name || st.first_name + ' ' + st.last_name) + '</strong>';
                    html += codeTag;
                    html += '      </div>';
                    if (contactItems.length) {
                        html += '      <div style="font-size:11.5px;color:var(--at-text-secondary);margin-top:3px;display:flex;gap:10px;flex-wrap:wrap;">';
                        html += contactItems.join(' &bull; ');
                        html += '      </div>';
                    }
                    html += '    </div>';
                    html += '  </div>';

                    html += '  <div>';
                    html += '    <button type="button" class="btn btn-indigo btn-lift btn-assign-single-student-task" data-student-id="' + st.id + '" data-student-name="' + (st.full_name || st.first_name) + '" style="font-size:11.5px;padding:6px 12px;">';
                    html += '      📝 Asignar Tarea / Responsabilidad';
                    html += '    </button>';
                    html += '  </div>';
                    html += '</div>';
                });

                $('#t-stds-body').html(html);

                // Conectar botón de asignar tarea individual a este estudiante
                $('.btn-assign-single-student-task').off('click').on('click', function() {
                    var studentId = $(this).data('student-id');
                    var studentName = $(this).data('student-name');
                    openTaskModalForSingleStudent(activeTeacherProgId, activeTeacherSubjId, studentId, studentName);
                });
            }

            // Búsqueda en vivo de estudiantes
            $('#t-stds-search').on('input', function() {
                var query = $(this).val().toLowerCase().trim();
                if (!query) {
                    renderTeacherStudentsRoster(cachedTeacherStudents);
                    return;
                }
                var filtered = cachedTeacherStudents.filter(function(st) {
                    var full = (st.full_name || (st.first_name + ' ' + st.last_name)).toLowerCase();
                    var code = (st.student_code || '').toLowerCase();
                    var mail = (st.email || '').toLowerCase();
                    var phone = (st.phone || '').toLowerCase();
                    return full.indexOf(query) !== -1 || code.indexOf(query) !== -1 || mail.indexOf(query) !== -1 || phone.indexOf(query) !== -1;
                });
                renderTeacherStudentsRoster(filtered);
            });

            // Botón Asignar Tarea Grupal desde la nómina
            $('#btn-t-stds-assign-all').on('click', function() {
                closeTeacherModal('#modal-teacher-students');
                $('#form-teacher-task-create')[0].reset();
                $('input[name="target_type"][value="all"]').prop('checked', true);
                updateTargetingUI();
                $('#t-tsk-prog').val(activeTeacherProgId);
                $('#t-tsk-subj').val(activeTeacherSubjId);
                var d = new Date();
                d.setDate(d.getDate() + 7);
                d.setHours(23, 59, 0, 0);
                $('#t-tsk-due').val(d.toISOString().slice(0, 16));
                openTeacherModal('#modal-teacher-task');
            });

            // Botón Repartir Libros Diferenciados desde la nómina
            $('#btn-t-stds-assign-diff').on('click', function() {
                closeTeacherModal('#modal-teacher-students');
                $('#form-teacher-task-create')[0].reset();
                $('input[name="target_type"][value="differentiated"]').prop('checked', true);
                updateTargetingUI();
                $('#t-tsk-prog').val(activeTeacherProgId);
                $('#t-tsk-subj').val(activeTeacherSubjId);
                var d = new Date();
                d.setDate(d.getDate() + 7);
                d.setHours(23, 59, 0, 0);
                $('#t-tsk-due').val(d.toISOString().slice(0, 16));
                openTeacherModal('#modal-teacher-task');
                loadProgramStudentsIfNeeded();
            });

            // Asignación de tarea a un estudiante individual específico
            function openTaskModalForSingleStudent(progId, subjId, studentId, studentName) {
                closeTeacherModal('#modal-teacher-students');
                $('#form-teacher-task-create')[0].reset();
                $('#t-tsk-prog').val(progId);
                $('#t-tsk-subj').val(subjId);
                $('input[name="target_type"][value="individual"]').prop('checked', true);
                updateTargetingUI();

                var d = new Date();
                d.setDate(d.getDate() + 7);
                d.setHours(23, 59, 0, 0);
                $('#t-tsk-due').val(d.toISOString().slice(0, 16));
                $('#t-tsk-title').val('').attr('placeholder', 'Ej: Tarea individual / Responsabilidad para ' + studentName);

                openTeacherModal('#modal-teacher-task');

                // Asegurar que los estudiantes se carguen y se marque exclusivamente este alumno
                loadProgramStudentsIfNeeded(function() {
                    $('.chk-indiv-student').prop('checked', false);
                    $('.chk-indiv-student[value="' + studentId + '"]').prop('checked', true);
                });

                setTimeout(function() {
                    $('#t-tsk-title').focus();
                }, 200);
            }

            // Renderizar listas en los contenedores
            function renderTargetingStudents(students) {
                if (!students || !students.length) {
                    $('#container-students-individual').html('<p style="font-size:12px;color:var(--at-text-muted);text-align:center;padding:8px;">No hay alumnos matriculados en este programa.</p>');
                    $('#container-students-differentiated').html('<p style="font-size:12px;color:var(--at-text-muted);text-align:center;padding:8px;">No hay alumnos matriculados en este programa.</p>');
                    return;
                }

                // 1. Contenedor Individual (Checkboxes)
                var indHtml = '';
                $.each(students, function(i, st) {
                    var codeTag = st.student_code ? ' <span style="font-size:11px;color:var(--at-text-muted);font-family:monospace;">(' + st.student_code + ')</span>' : '';
                    indHtml += '<label style="display:flex;align-items:center;gap:8px;font-size:12.5px;padding:4px 6px;border-radius:4px;cursor:pointer;background:var(--at-bg-card-alt,#f8fafc);">';
                    indHtml += '  <input type="checkbox" class="chk-indiv-student" value="' + st.id + '" checked style="margin:0;">';
                    indHtml += '  <span><strong>' + st.first_name + ' ' + st.last_name + '</strong>' + codeTag + '</span>';
                    indHtml += '</label>';
                });
                $('#container-students-individual').html(indHtml);

                // 2. Contenedor Diferenciado (Cada alumno con su libro)
                var books = window.auraTeacherBooks || [];
                var diffHtml = '';
                $.each(students, function(i, st) {
                    var codeTag = st.student_code ? ' <span style="font-size:11px;color:var(--at-text-muted);font-family:monospace;">(' + st.student_code + ')</span>' : '';
                    diffHtml += '<div class="row-diff-student" data-student-id="' + st.id + '" style="border:1px solid var(--at-border);border-radius:8px;padding:10px;background:var(--at-bg-card-alt,#f8fafc);display:flex;flex-direction:column;gap:6px;">';
                    diffHtml += '  <div style="display:flex;justify-content:space-between;align-items:center;">';
                    diffHtml += '    <div style="font-size:13px;font-weight:700;color:var(--at-text-primary);">👤 ' + st.first_name + ' ' + st.last_name + codeTag + '</div>';
                    diffHtml += '    <span class="adp-badge badge-slate" style="font-size:10.5px;">Alumno #' + st.id + '</span>';
                    diffHtml += '  </div>';
                    diffHtml += '  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">';
                    diffHtml += '    <div>';
                    diffHtml += '      <label style="font-size:11px;font-weight:600;display:block;margin-bottom:2px;color:var(--at-primary);">📖 Libro Asignado:</label>';
                    diffHtml += '      <select class="form-control student-diff-book" style="width:100%;font-size:12px;padding:4px 8px;">';
                    diffHtml += '        <option value="">-- Sin libro específico --</option>';
                    $.each(books, function(bi, bk) {
                        diffHtml += '        <option value="' + bk.id + '" data-title="' + $('<div>').text(bk.title).html() + '" data-author="' + $('<div>').text(bk.author || '').html() + '">' + bk.title + (bk.author ? ' (' + bk.author + ')' : '') + '</option>';
                    });
                    diffHtml += '      </select>';
                    diffHtml += '    </div>';
                    diffHtml += '    <div>';
                    diffHtml += '      <label style="font-size:11px;font-weight:600;display:block;margin-bottom:2px;color:var(--at-text-secondary);">📝 Instrucción o Capítulo Específico:</label>';
                    diffHtml += '      <input type="text" class="form-control student-diff-notes" placeholder="Ej: Leer caps. 1 a 3..." style="width:100%;font-size:12px;padding:4px 8px;">';
                    diffHtml += '    </div>';
                    diffHtml += '  </div>';
                    diffHtml += '</div>';
                });
                $('#container-students-differentiated').html(diffHtml);
            }

            // Marcar / Desmarcar todos en Individual
            $('#btn-toggle-all-indiv').on('click', function() {
                var $chks = $('.chk-indiv-student');
                var anyUnchecked = $chks.filter(':not(:checked)').length > 0;
                $chks.prop('checked', anyUnchecked);
                $(this).text(anyUnchecked ? 'Desmarcar todos' : 'Marcar todos');
            });

            // Botón Repartir Libros Aleatoriamente
            $('#btn-auto-assign-books').on('click', function() {
                var books = window.auraTeacherBooks || [];
                if (!books.length) {
                    alert('No hay libros registrados en la Biblioteca para repartir.');
                    return;
                }
                var $selects = $('.student-diff-book');
                if (!$selects.length) {
                    alert('No hay estudiantes cargados para asignar libros.');
                    return;
                }

                // Barajar libros aleatoriamente
                var shuffled = books.slice().sort(function() { return 0.5 - Math.random(); });

                $selects.each(function(idx) {
                    var bk = shuffled[idx % shuffled.length];
                    $(this).val(bk.id).trigger('change');
                });

                alert('🎲 ¡Se han distribuido ' + books.length + ' libros aleatoriamente entre los ' + $selects.length + ' estudiantes matriculados!');
            });

            $('#btn-teacher-open-task-modal, #btn-teacher-open-first-task').on('click', function() {
                $('#form-teacher-task-create')[0].reset();
                $('input[name="target_type"][value="all"]').prop('checked', true);
                updateTargetingUI();

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
                $('input[name="target_type"][value="all"]').prop('checked', true);
                updateTargetingUI();

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
                var targetType = $('input[name="target_type"]:checked').val() || 'all';

                var targetStudentIds = [];
                var studentAssignments = {};

                if (targetType === 'individual') {
                    $('.chk-indiv-student:checked').each(function() {
                        targetStudentIds.push(parseInt($(this).val(), 10));
                    });
                    if (!targetStudentIds.length) {
                        alert('⚠️ Por favor selecciona al menos un estudiante para la asignación individual.');
                        return;
                    }
                } else if (targetType === 'differentiated') {
                    var hasAnyBook = false;
                    $('.row-diff-student').each(function() {
                        var stId = $(this).data('student-id');
                        var $bookSel = $(this).find('.student-diff-book');
                        var bookId = parseInt($bookSel.val(), 10) || 0;
                        var $selOpt = $bookSel.find('option:selected');
                        var bookTitle = $selOpt.data('title') || '';
                        var bookAuthor = $selOpt.data('author') || '';
                        var notes = $(this).find('.student-diff-notes').val() || '';

                        if (bookId > 0) {
                            hasAnyBook = true;
                        }

                        targetStudentIds.push(stId);
                        studentAssignments[stId] = {
                            book_id: bookId,
                            book_title: bookTitle,
                            book_author: bookAuthor,
                            instructions: notes
                        };
                    });

                    if (!hasAnyBook) {
                        alert('⚠️ Por favor asigna al menos un libro o usa el botón "Repartir Libros Aleatoriamente" para la modalidad diferenciada.');
                        return;
                    }
                }

                $btn.prop('disabled', true).text('Guardando...');

                var data = $(this).serializeArray();
                data.push({ name: 'action', value: 'aura_cal_save_task' });
                data.push({ name: 'nonce', value: auraCalData.nonce });
                data.push({ name: 'id', value: '0' });
                data.push({ name: 'target_type', value: targetType });

                if (targetType === 'individual') {
                    data.push({ name: 'target_student_ids', value: JSON.stringify(targetStudentIds) });
                } else if (targetType === 'differentiated') {
                    data.push({ name: 'target_student_ids', value: JSON.stringify(targetStudentIds) });
                    data.push({ name: 'student_assignments', value: JSON.stringify(studentAssignments) });
                }

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

            // 4. Ver Entregas de Tarea y Calificar (incluyendo estudiantes pendientes y libros asignados)
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
                            $('#t-subs-body').html('<div style="text-align:center;padding:32px;color:var(--at-text-muted);"><div style="font-size:32px;margin-bottom:8px;">📭</div><p>Aún no se han recibido entregas ni hay estudiantes vinculados a esta tarea.</p></div>');
                            return;
                        }

                        var html = '<div style="display:flex;flex-direction:column;gap:16px;">';
                        $.each(subs, function(i, s) {
                            var isPending = !!s.is_pending;
                            var statusBadge = '';
                            if (isPending) {
                                statusBadge = '<span class="adp-badge badge-slate" style="font-size:11px;">⏳ Sin Entrega Aún</span>';
                            } else if (s.status === 'graded') {
                                statusBadge = '<span class="adp-badge badge-emerald">Calificada</span>';
                            } else if (s.status === 'late') {
                                statusBadge = '<span class="adp-badge badge-amber">Entrega Tardía</span>';
                            } else {
                                statusBadge = '<span class="adp-badge badge-indigo">Entregada</span>';
                            }

                            // Contar palabras si hay texto
                            var wordCount = 0;
                            if (s.submission_text) {
                                wordCount = s.submission_text.trim().split(/\s+/).filter(Boolean).length;
                            }

                            html += '<div class="aura-submission-row" style="' + (isPending ? 'opacity:0.85;border-style:dashed;' : '') + '">';
                            html += '  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">';
                            html += '    <div>';
                            html += '      <strong style="font-size:15px;color:var(--at-text-primary);">' + (s.first_name || 'Estudiante') + ' ' + (s.last_name || '') + '</strong>';
                            if (s.student_code) {
                                html += '      <span style="font-family:monospace;font-size:11px;color:var(--at-text-muted);margin-left:6px;">(' + s.student_code + ')</span>';
                            }
                            html += '    </div>';
                            html += '    <div>' + statusBadge + '</div>';
                            html += '  </div>';

                            // Libro asignado diferenciado (si aplica)
                            if (s.assigned_book_title) {
                                html += '  <div style="font-size:12px;color:var(--at-primary);margin-bottom:8px;padding:4px 8px;background:var(--at-chip-bg);border-radius:6px;display:inline-block;">';
                                html += '    📖 <strong>Libro Asignado:</strong> ' + s.assigned_book_title + (s.assigned_book_author ? ' (' + s.assigned_book_author + ')' : '');
                                if (s.assigned_instructions) {
                                    html += ' &bull; <em>' + s.assigned_instructions + '</em>';
                                }
                                html += '  </div>';
                            }

                            if (isPending) {
                                html += '  <div style="font-size:12px;color:var(--at-text-muted);font-style:italic;padding:8px 0;">';
                                html += '    El estudiante no ha enviado su resumen o archivo todavía.';
                                html += '  </div>';
                            } else {
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
                            }

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

            // 6. FullCalendar para el Instructor con Inicialización Resiliente
            function initTeacherCalendar() {
                if (window.teacherCalendarInstance) return;
                var calEl = document.getElementById('aura-teacher-fullcalendar');
                if (!calEl || typeof FullCalendar === 'undefined') return;

                    var timeFmt = (typeof window.getFcTimeConfig === 'function') 
                        ? window.getFcTimeConfig(auraCalData.time_format)
                        : (/[aAgGh]/.test(auraCalData.time_format || '') && !/[HG]/.test(auraCalData.time_format || '') ? { hour: 'numeric', minute: '2-digit', hour12: true, meridiem: 'short' } : { hour: '2-digit', minute: '2-digit', hour12: false });

                    var initialRoute = (typeof window.parseCalendarUrlRoute === 'function') ? window.parseCalendarUrlRoute() : null;
                    var initialView = initialRoute ? initialRoute.view : 'timeGridWeek';
                    var initialDate = initialRoute ? initialRoute.dateStr : undefined;

                    window.teacherCalendarInstance = new FullCalendar.Calendar(calEl, {
                        initialView: initialView,
                        initialDate: initialDate,
                        locale: 'es',
                        firstDay: parseInt(auraCalData.first_day || 1, 10),
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
                        slotMinTime: '00:00:00',
                        slotMaxTime: '24:00:00',
                        scrollTime: '07:00:00',
                        slotLabelFormat: timeFmt,
                        eventTimeFormat: timeFmt,
                        allDaySlot: true,
                        allDayText: '🌅 Todo el día',
                        timeZone: 'local',
                        nowIndicator: true,
                    eventMouseEnter: function(info) {
                        if (typeof window.showEventTooltip === 'function') {
                            window.showEventTooltip(info.event, info.el, info.jsEvent);
                        } else if (typeof showEventTooltip === 'function') {
                            showEventTooltip(info.event, info.el, info.jsEvent);
                        }
                    },
                    eventMouseLeave: function(info) {
                        if (typeof window.hideEventTooltip === 'function') {
                            window.hideEventTooltip();
                        } else if (typeof hideEventTooltip === 'function') {
                            hideEventTooltip();
                        }
                    },
                    eventContent: function(arg) {
                        var p = arg.event.extendedProps || {};
                        var title = p.raw_title || arg.event.title;
                        var timeText = arg.timeText;
                        
                        var avatarImg = '';
                        if (p.primary_avatar) {
                            avatarImg = '<img src="' + p.primary_avatar + '" style="width:18px;height:18px;border-radius:50%;object-fit:cover;flex-shrink:0;border:1px solid rgba(255,255,255,0.7);vertical-align:middle;display:inline-block;" onerror="this.style.display=\'none\';" />';
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
                        if (typeof window.hideEventTooltip === 'function') {
                            window.hideEventTooltip();
                        } else if (typeof hideEventTooltip === 'function') {
                            hideEventTooltip();
                        }
                        if (typeof window.openEventDetail === 'function') {
                            window.openEventDetail(info.event);
                        } else if (typeof openEventDetail === 'function') {
                            openEventDetail(info.event);
                        }
                    },
                    datesSet: function(dateInfo) {
                        var anchorDate = dateInfo.view.currentStart || dateInfo.start;
                        if (typeof window.syncCalendarUrlAndGcalLink === 'function') {
                            window.syncCalendarUrlAndGcalLink(dateInfo.view.type, anchorDate);
                        }
                    }
                });
                window.teacherCalendarInstance.render();

                window.addEventListener('hashchange', function() {
                    if (typeof window.parseCalendarUrlRoute === 'function' && window.teacherCalendarInstance) {
                        var r = window.parseCalendarUrlRoute();
                        if (r) {
                            var currView = window.teacherCalendarInstance.view ? window.teacherCalendarInstance.view.type : '';
                            if (currView !== r.view) {
                                window.teacherCalendarInstance.changeView(r.view, r.dateStr);
                            } else {
                                window.teacherCalendarInstance.gotoDate(r.dateStr);
                            }
                        }
                    }
                });
            }

            // Polling de reintento para garantizar la inicialización aunque el CDN se demore
            initTeacherCalendar();
            var tTries = 0;
            var tInterval = setInterval(function() {
                tTries++;
                if (window.teacherCalendarInstance || tTries > 40) {
                    clearInterval(tInterval);
                } else {
                    initTeacherCalendar();
                }
            }, 100);

            // Toggle Pantalla Completa para Docente (Soporte CSS + Native Fullscreen API)
            function toggleTeacherFullscreen() {
                var $container = $('#tab-teacher-schedule .adp-card');
                var $btn = $('#btn-toggle-teacher-fullscreen');
                var isFs = $container.hasClass('aura-calendar-is-fullscreen');

                if (isFs) {
                    $container.removeClass('aura-calendar-is-fullscreen');
                    $('body').removeClass('aura-cal-fullscreen-active');
                    $btn.removeClass('is-active-fullscreen');
                    $btn.find('.dashicons').removeClass('dashicons-editor-contract').addClass('dashicons-editor-expand');
                    $btn.find('.fs-text').text(auraCalData.i18n.fullscreen || 'Pantalla Completa');

                    if (document.fullscreenElement || document.webkitFullscreenElement) {
                        if (document.exitFullscreen) {
                            document.exitFullscreen().catch(function(){});
                        } else if (document.webkitExitFullscreen) {
                            document.webkitExitFullscreen();
                        }
                    }
                } else {
                    $container.addClass('aura-calendar-is-fullscreen');
                    $('body').addClass('aura-cal-fullscreen-active');
                    $btn.addClass('is-active-fullscreen');
                    $btn.find('.dashicons').removeClass('dashicons-editor-expand').addClass('dashicons-editor-contract');
                    $btn.find('.fs-text').text(auraCalData.i18n.exit_fullscreen || 'Salir de Pantalla Completa');

                    var domEl = $container[0];
                    if (domEl) {
                        if (domEl.requestFullscreen) {
                            domEl.requestFullscreen().catch(function(){});
                        } else if (domEl.webkitRequestFullscreen) {
                            domEl.webkitRequestFullscreen();
                        }
                    }
                }

                setTimeout(function() {
                    if (window.teacherCalendarInstance) {
                        window.teacherCalendarInstance.updateSize();
                    }
                }, 60);
                setTimeout(function() {
                    if (window.teacherCalendarInstance) {
                        window.teacherCalendarInstance.updateSize();
                    }
                }, 220);
            }

            // Delegación global del botón
            $(document).on('click', '#btn-toggle-teacher-fullscreen', function(e) {
                e.preventDefault();
                toggleTeacherFullscreen();
            });

            // Soporte para tecla Escape inteligente (no salir de fullscreen si hay un modal abierto)
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    var $openModals = $('#modal-event-detail:visible, .aura-modal-overlay:visible, [id^="modal-"]:visible');
                    if ($openModals.length > 0) {
                        $openModals.fadeOut(150);
                        $('body').removeClass('aura-modal-open');
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }

                    var $container = $('#tab-teacher-schedule .adp-card');
                    if ($container.hasClass('aura-calendar-is-fullscreen')) {
                        toggleTeacherFullscreen();
                    }
                }
            });

            // Cerrar modal de detalle de evento sin alterar pantalla completa
            $(document).on('click', '.btn-close-evt-detail, [data-close-modal="#modal-event-detail"]', function(e) {
                e.preventDefault();
                $('#modal-event-detail').fadeOut(150);
                $('body').removeClass('aura-modal-open');
            });

            // Sincronización si el usuario sale de fullscreen nativo
            $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', function() {
                if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                    var $container = $('#tab-teacher-schedule .adp-card');
                    if ($container.hasClass('aura-calendar-is-fullscreen')) {
                        $container.removeClass('aura-calendar-is-fullscreen');
                        $('body').removeClass('aura-cal-fullscreen-active');
                        var $btn = $('#btn-toggle-teacher-fullscreen');
                        $btn.removeClass('is-active-fullscreen');
                        $btn.find('.dashicons').removeClass('dashicons-editor-contract').addClass('dashicons-editor-expand');
                        $btn.find('.fs-text').text(auraCalData.i18n.fullscreen || 'Pantalla Completa');
                        if (window.teacherCalendarInstance) {
                            setTimeout(function() { window.teacherCalendarInstance.updateSize(); }, 80);
                        }
                    }
                }
            });

            // Re-render reactivo instantáneo cuando cambia el tema claro/oscuro
            window.addEventListener('aura:themeChanged', function() {
                if (window.teacherCalendarInstance) {
                    setTimeout(function() {
                        window.teacherCalendarInstance.render();
                    }, 50);
                }
            });
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
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-secondary btn-sm" id="btn-toggle-student-fullscreen" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; padding: 4px 10px; border-radius: 6px; font-size: 12px;">
                            <span class="dashicons dashicons-editor-expand" style="font-size: 16px; width: 16px; height: 16px;"></span> <span class="fs-text"><?php esc_html_e( 'Pantalla Completa', 'aura' ); ?></span>
                        </button>
                        <a href="https://calendar.google.com/calendar/u/0/r" target="_blank" rel="noopener noreferrer" id="btn-open-student-gcal" class="btn btn-secondary btn-sm aura-btn-gcal-link" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; padding: 4px 10px; border-radius: 6px; font-size: 12px; text-decoration: none; color: inherit;" title="<?php esc_attr_e( 'Abrir esta misma fecha y vista en Google Calendar', 'aura' ); ?>">
                            <span style="font-size: 13px;">📅</span> <?php esc_html_e( 'Google Calendar', 'aura' ); ?> ↗
                        </a>
                        <div style="font-size: 12px; color: var(--at-text-muted, #64748b);">
                            💡 <?php esc_html_e( 'Las clases con una estrella (⭐) indican que tienes o hay compañeros con roles de liderazgo asignados.', 'aura' ); ?>
                        </div>
                    </div>
                </div>
                <div id="aura-student-calendar" style="min-height: 540px;"></div>
                <?php include AURA_PLUGIN_DIR . 'templates/calendar/modal-event-detail.php'; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var $ = jQuery;
            var currentUserId = <?php echo intval( $current_user_id ); ?>;

            function closeEventDetailModal() {
                $('#modal-event-detail').fadeOut(150);
                $('body').removeClass('aura-modal-open');
            }
            $(document).on('click', '.btn-close-evt-detail, [data-close-modal="#modal-event-detail"]', function(e) {
                e.preventDefault();
                closeEventDetailModal();
            });

            // Inicialización Resiliente de FullCalendar para Estudiantes
            function initStudentCalendar() {
                if (window.studentCalendarInstance) return;
                var calEl = document.getElementById('aura-student-calendar');
                if (!calEl || typeof FullCalendar === 'undefined') return;

                var timeFmt = (typeof window.getFcTimeConfig === 'function') 
                    ? window.getFcTimeConfig(auraCalData.time_format)
                    : (/[aAgGh]/.test(auraCalData.time_format || '') && !/[HG]/.test(auraCalData.time_format || '') ? { hour: 'numeric', minute: '2-digit', hour12: true, meridiem: 'short' } : { hour: '2-digit', minute: '2-digit', hour12: false });

                var initialRoute = (typeof window.parseCalendarUrlRoute === 'function') ? window.parseCalendarUrlRoute() : null;
                var initialView = initialRoute ? initialRoute.view : 'timeGridWeek';
                var initialDate = initialRoute ? initialRoute.dateStr : undefined;

                window.studentCalendarInstance = new FullCalendar.Calendar(calEl, {
                    initialView: initialView,
                    initialDate: initialDate,
                    locale: 'es',
                    firstDay: parseInt(auraCalData.first_day || 1, 10),
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,listWeek'
                    },
                    buttonText: {
                        today: 'Hoy',
                        month: 'Mes',
                        week:  'Semana',
                        list:  'Agenda'
                    },
                    slotMinTime: '00:00:00',
                    slotMaxTime: '24:00:00',
                    scrollTime: '07:00:00',
                    slotLabelFormat: timeFmt,
                    eventTimeFormat: timeFmt,
                    allDaySlot: true,
                    allDayText: '🌅 Todo el día',
                    timeZone: 'local',
                    nowIndicator: true,
                    eventMouseEnter: function(info) {
                        if (typeof window.showEventTooltip === 'function') {
                            window.showEventTooltip(info.event, info.el, info.jsEvent);
                        } else if (typeof showEventTooltip === 'function') {
                            showEventTooltip(info.event, info.el, info.jsEvent);
                        }
                    },
                    eventMouseLeave: function(info) {
                        if (typeof window.hideEventTooltip === 'function') {
                            window.hideEventTooltip();
                        } else if (typeof hideEventTooltip === 'function') {
                            hideEventTooltip();
                        }
                    },
                    eventContent: function(arg) {
                        var p = arg.event.extendedProps || {};
                        var title = p.raw_title || arg.event.title;
                        var timeText = arg.timeText;
                        
                        var avatarImg = '';
                        if (p.primary_avatar) {
                            avatarImg = '<img src="' + p.primary_avatar + '" style="width:18px;height:18px;border-radius:50%;object-fit:cover;flex-shrink:0;border:1px solid rgba(255,255,255,0.7);vertical-align:middle;display:inline-block;" onerror="this.style.display=\'none\';" />';
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
                        if (typeof window.hideEventTooltip === 'function') {
                            window.hideEventTooltip();
                        } else if (typeof hideEventTooltip === 'function') {
                            hideEventTooltip();
                        }
                        if (typeof window.openEventDetail === 'function') {
                            window.openEventDetail(info.event);
                        } else if (typeof openEventDetail === 'function') {
                            openEventDetail(info.event);
                        }
                    },
                    datesSet: function(dateInfo) {
                        var anchorDate = dateInfo.view.currentStart || dateInfo.start;
                        if (typeof window.syncCalendarUrlAndGcalLink === 'function') {
                            window.syncCalendarUrlAndGcalLink(dateInfo.view.type, anchorDate);
                        }
                    }
                });
                window.studentCalendarInstance.render();

                window.addEventListener('hashchange', function() {
                    if (typeof window.parseCalendarUrlRoute === 'function' && window.studentCalendarInstance) {
                        var r = window.parseCalendarUrlRoute();
                        if (r) {
                            var currView = window.studentCalendarInstance.view ? window.studentCalendarInstance.view.type : '';
                            if (currView !== r.view) {
                                window.studentCalendarInstance.changeView(r.view, r.dateStr);
                            } else {
                                window.studentCalendarInstance.gotoDate(r.dateStr);
                            }
                        }
                    }
                });
            }

            // Polling de reintentos
            initStudentCalendar();
            var sTries = 0;
            var sInterval = setInterval(function() {
                sTries++;
                if (window.studentCalendarInstance || sTries > 40) {
                    clearInterval(sInterval);
                } else {
                    initStudentCalendar();
                }
            }, 100);

            // Toggle Pantalla Completa para Estudiante (Soporte CSS + Native Fullscreen API)
            function toggleStudentFullscreen() {
                var $container = $('.aura-student-schedule-wrap .adp-card');
                var $btn = $('#btn-toggle-student-fullscreen');
                var isFs = $container.hasClass('aura-calendar-is-fullscreen');

                if (isFs) {
                    $container.removeClass('aura-calendar-is-fullscreen');
                    $('body').removeClass('aura-cal-fullscreen-active');
                    $btn.removeClass('is-active-fullscreen');
                    $btn.find('.dashicons').removeClass('dashicons-editor-contract').addClass('dashicons-editor-expand');
                    $btn.find('.fs-text').text(auraCalData.i18n.fullscreen || 'Pantalla Completa');

                    if (document.fullscreenElement || document.webkitFullscreenElement) {
                        if (document.exitFullscreen) {
                            document.exitFullscreen().catch(function(){});
                        } else if (document.webkitExitFullscreen) {
                            document.webkitExitFullscreen();
                        }
                    }
                } else {
                    $container.addClass('aura-calendar-is-fullscreen');
                    $('body').addClass('aura-cal-fullscreen-active');
                    $btn.addClass('is-active-fullscreen');
                    $btn.find('.dashicons').removeClass('dashicons-editor-expand').addClass('dashicons-editor-contract');
                    $btn.find('.fs-text').text(auraCalData.i18n.exit_fullscreen || 'Salir de Pantalla Completa');

                    var domEl = $container[0];
                    if (domEl) {
                        if (domEl.requestFullscreen) {
                            domEl.requestFullscreen().catch(function(){});
                        } else if (domEl.webkitRequestFullscreen) {
                            domEl.webkitRequestFullscreen();
                        }
                    }
                }

                setTimeout(function() {
                    if (window.studentCalendarInstance) {
                        window.studentCalendarInstance.updateSize();
                    }
                }, 60);
                setTimeout(function() {
                    if (window.studentCalendarInstance) {
                        window.studentCalendarInstance.updateSize();
                    }
                }, 220);
            }

            // Delegación global
            $(document).on('click', '#btn-toggle-student-fullscreen', function(e) {
                e.preventDefault();
                toggleStudentFullscreen();
            });

            // Tecla Escape: Cerrar modal si está abierto SIN salir de fullscreen
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    var $openModals = $('#modal-event-detail:visible, .aura-modal-overlay:visible, [id^="modal-"]:visible');
                    if ($openModals.length > 0) {
                        $openModals.fadeOut(150);
                        $('body').removeClass('aura-modal-open');
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        return false;
                    }

                    var $container = $('.aura-student-schedule-wrap .adp-card');
                    if ($container.hasClass('aura-calendar-is-fullscreen')) {
                        toggleStudentFullscreen();
                    }
                }
            });

            // Sincronización fullscreen nativo
            $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', function() {
                if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                    var $container = $('.aura-student-schedule-wrap .adp-card');
                    if ($container.hasClass('aura-calendar-is-fullscreen')) {
                        $container.removeClass('aura-calendar-is-fullscreen');
                        $('body').removeClass('aura-cal-fullscreen-active');
                        var $btn = $('#btn-toggle-student-fullscreen');
                        $btn.removeClass('is-active-fullscreen');
                        $btn.find('.dashicons').removeClass('dashicons-editor-contract').addClass('dashicons-editor-expand');
                        $btn.find('.fs-text').text(auraCalData.i18n.fullscreen || 'Pantalla Completa');
                        if (window.studentCalendarInstance) {
                            setTimeout(function() { window.studentCalendarInstance.updateSize(); }, 80);
                        }
                    }
                }
            });

            // Re-render reactivo en cambio de tema
            window.addEventListener('aura:themeChanged', function() {
                if (window.studentCalendarInstance) {
                    setTimeout(function() {
                        window.studentCalendarInstance.render();
                    }, 50);
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
