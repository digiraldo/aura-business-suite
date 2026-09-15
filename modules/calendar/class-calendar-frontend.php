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
        add_shortcode( 'aura_community_login',  [ __CLASS__, 'shortcode_community_login' ] );
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
            has_shortcode( $post->post_content, 'aura_community_login' ) ||
            has_shortcode( $post->post_content, 'aura_teacher_portal' ) ||
            has_shortcode( $post->post_content, 'aura_student_schedule' )
        );

        if ( ! $has_shortcode ) {
            return;
        }

        // Cargar Design System Base
        if ( ! wp_style_is( 'aura-design-system', 'enqueued' ) ) {
            wp_enqueue_style(
                'aura-design-system',
                AURA_PLUGIN_URL . 'assets/css/design-system.css',
                [],
                AURA_VERSION
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
            [ 'aura-design-system' ],
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
     * Shortcode: [aura_community_login]
     * Formulario unificado de acceso por rol
     */
    public static function shortcode_community_login(): string {
        ob_start();

        // Si ya está logueado
        if ( is_user_logged_in() ) {
            $user       = wp_get_current_user();
            $portal_url = '';

            // Detectar si existe página del portal del estudiante
            if ( class_exists( 'Aura_Students_Settings' ) ) {
                $st_page_id = (int) Aura_Students_Settings::get( 'portal_page_id' );
                if ( $st_page_id > 0 ) {
                    $portal_url = get_permalink( $st_page_id );
                }
            }
            if ( empty( $portal_url ) ) {
                $portal_url = home_url( '/portal-del-estudiante/' );
            }

            // Comprobar si tiene acceso a administración o backend
            $can_admin = (
                current_user_can( 'manage_options' ) ||
                current_user_can( 'aura_view_calendar' ) ||
                current_user_can( 'aura_manage_finances' ) ||
                current_user_can( 'aura_inventory_view_all' )
            );

            // Comprobar si es profesor
            $is_teacher = current_user_can( 'aura_teach_calendar' );
            ?>
            <div class="aura-portal-wrap" style="max-width: 520px; margin: 40px auto; padding: 0 16px;">
                <div class="adp-card" style="text-align: center; padding: 36px 28px;">
                    <div style="width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%; background: var(--aura-primary-alpha, rgba(99, 102, 241, 0.12)); display: flex; align-items: center; justify-content: center; font-size: 28px;">
                        👋
                    </div>
                    <h2 class="adp-card-title" style="font-size: 22px; margin-bottom: 8px;">
                        <?php printf( esc_html__( '¡Hola, %s!', 'aura' ), esc_html( $user->display_name ) ); ?>
                    </h2>
                    <p class="adp-card-desc" style="margin-bottom: 24px;">
                        <?php esc_html_e( 'Has iniciado sesión exitosamente en la plataforma.', 'aura' ); ?>
                    </p>

                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php if ( $is_teacher ) : ?>
                            <a href="<?php echo esc_url( home_url( '/portal-instructor/' ) ); ?>" class="btn btn-indigo btn-shimmer btn-lift" style="padding: 12px 20px; font-size: 15px; font-weight: 600; text-decoration: none;">
                                👨‍🏫 <?php esc_html_e( 'Ir a mi Portal de Instructor', 'aura' ); ?>
                            </a>
                        <?php endif; ?>

                        <a href="<?php echo esc_url( $portal_url ); ?>" class="btn btn-indigo btn-shimmer btn-lift" style="padding: 12px 20px; font-size: 15px; font-weight: 600; text-decoration: none;">
                            🎓 <?php esc_html_e( 'Ir a mi Portal de Estudiante', 'aura' ); ?>
                        </a>

                        <?php if ( $can_admin ) : ?>
                            <a href="<?php echo esc_url( admin_url() ); ?>" class="btn btn-emerald btn-shimmer btn-lift" style="padding: 12px 20px; font-size: 15px; font-weight: 600; text-decoration: none;">
                                ⚡ <?php esc_html_e( 'Acceder al Panel Administrativo', 'aura' ); ?>
                            </a>
                        <?php endif; ?>

                        <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="btn btn-ghost" style="padding: 10px 16px; font-size: 14px; text-decoration: none; margin-top: 8px;">
                            🚪 <?php esc_html_e( 'Cerrar Sesión', 'aura' ); ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        // Proceso de Login POST
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
                wp_safe_redirect( $_SERVER['REQUEST_URI'] );
                exit;
            }
        }
        ?>
        <div class="aura-portal-wrap" style="max-width: 440px; margin: 40px auto; padding: 0 16px;">
            <div class="adp-card" style="padding: 36px 32px; border-radius: 16px; box-shadow: var(--aura-shadow-md);">
                <div style="text-align: center; margin-bottom: 24px;">
                    <span class="adp-badge badge-indigo has-dot" style="margin-bottom: 12px;">
                        <span class="pulse-dot"></span> <?php esc_html_e( 'Acceso a la Comunidad', 'aura' ); ?>
                    </span>
                    <h2 class="adp-card-title" style="font-size: 24px; margin-bottom: 6px;">
                        <?php esc_html_e( 'Iniciar Sesión', 'aura' ); ?>
                    </h2>
                    <p class="adp-card-desc" style="font-size: 14px;">
                        <?php esc_html_e( 'Ingresa con tu correo institucional o usuario.', 'aura' ); ?>
                    </p>
                </div>

                <?php if ( ! empty( $login_error ) ) : ?>
                    <div class="alert-card alert-danger" style="margin-bottom: 20px; padding: 12px 16px; font-size: 14px;">
                        ⚠️ <?php echo esc_html( $login_error ); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="" style="display: flex; flex-direction: column; gap: 16px;">
                    <?php wp_nonce_field( 'aura_login_action', 'aura_login_nonce' ); ?>

                    <div class="form-group">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; margin-bottom: 6px; display: block;">
                            <?php esc_html_e( 'Correo o Usuario', 'aura' ); ?>
                        </label>
                        <div class="input-group">
                            <span class="input-prefix">👤</span>
                            <input type="text" name="log" class="form-control" required placeholder="<?php esc_attr_e( 'ejemplo@institucion.org', 'aura' ); ?>" style="width: 100%; border-radius: 8px; padding: 10px 12px 10px 38px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-size: 13px; font-weight: 600; margin-bottom: 6px; display: block;">
                            <?php esc_html_e( 'Contraseña', 'aura' ); ?>
                        </label>
                        <div class="input-group">
                            <span class="input-prefix">🔒</span>
                            <input type="password" name="pwd" class="form-control" required placeholder="••••••••" style="width: 100%; border-radius: 8px; padding: 10px 12px 10px 38px;">
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 13px;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="rememberme" value="1">
                            <?php esc_html_e( 'Recordarme', 'aura' ); ?>
                        </label>
                        <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" style="color: var(--aura-primary); text-decoration: none;">
                            <?php esc_html_e( '¿Olvidaste tu contraseña?', 'aura' ); ?>
                        </a>
                    </div>

                    <button type="submit" name="aura_login_submit" value="1" class="btn btn-indigo btn-shimmer btn-lift" style="width: 100%; padding: 12px; font-size: 15px; font-weight: 600; border-radius: 8px; margin-top: 8px;">
                        🚀 <?php esc_html_e( 'Ingresar al Portal', 'aura' ); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
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
                    <div>
                        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="btn btn-ghost" style="font-size: 13px;">
                            🚪 <?php esc_html_e( 'Salir', 'aura' ); ?>
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
                              (p.location ? '📍 Aula: ' + p.location + '\n' : '') +
                              (p.online_url ? '💻 Online: ' + p.online_url + '\n' : '') +
                              (p.description ? '📝 ' + p.description : '');
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
                    <h3 class="adp-card-title" style="font-size: 18px; margin: 0;">
                        📅 <?php esc_html_e( 'Mi Calendario de Clases y Actividades', 'aura' ); ?>
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
                    var details = '📚 ' + info.event.title + '\n' +
                                  (p.subject_name ? 'Materia: ' + p.subject_name + '\n' : '') +
                                  (p.location ? '📍 Salón: ' + p.location + '\n' : '') +
                                  (p.online_url ? '💻 Enlace: ' + p.online_url + '\n' : '') +
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
