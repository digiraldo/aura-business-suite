<?php
/**
 * Portal Frontend del Estudiante — Fase 8
 *
 * Shortcodes:
 *   [aura_student_login]         — Formulario de login personalizado
 *   [aura_student_portal]        — Portal privado (requiere login + cap)
 *   [aura_enrollment_form]       — Formulario público de inscripción/solicitud
 *   [aura_student_paz_salvo_check] — Verificación pública de paz y salvo
 *
 * AJAX (sin login):
 *   aura_students_submit_enrollment  — Guardar nueva solicitud pública
 *   aura_students_ajax_login         — Login AJAX desde portal
 *
 * AJAX (requiere login + aura_students_view_own):
 *   aura_student_portal_my_courses   — Cursos inscritos del estudiante
 *   aura_student_portal_my_payments  — Historial de pagos + cuotas
 *   aura_student_portal_my_certs     — Certificados emitidos
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Frontend {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // Shortcodes
        add_shortcode( 'aura_student_login',          [ __CLASS__, 'shortcode_login' ] );
        add_shortcode( 'aura_student_portal',         [ __CLASS__, 'shortcode_portal' ] );
        add_shortcode( 'aura_enrollment_form',        [ __CLASS__, 'shortcode_enrollment_form' ] );
        add_shortcode( 'aura_student_paz_salvo_check',[ __CLASS__, 'shortcode_paz_salvo_check' ] );

        // AJAX sin login (formulario público + login AJAX)
        add_action( 'wp_ajax_nopriv_aura_students_submit_enrollment', [ __CLASS__, 'ajax_submit_enrollment' ] );
        add_action( 'wp_ajax_aura_students_submit_enrollment',        [ __CLASS__, 'ajax_submit_enrollment' ] );

        add_action( 'wp_ajax_nopriv_aura_students_ajax_login', [ __CLASS__, 'ajax_login' ] );
        add_action( 'wp_ajax_aura_students_ajax_login',        [ __CLASS__, 'ajax_login' ] );

        // AJAX con login (portal del estudiante)
        add_action( 'wp_ajax_aura_student_portal_my_courses',  [ __CLASS__, 'ajax_my_courses' ] );
        add_action( 'wp_ajax_aura_student_portal_my_payments', [ __CLASS__, 'ajax_my_payments' ] );
        add_action( 'wp_ajax_aura_student_portal_my_certs',    [ __CLASS__, 'ajax_my_certs' ] );
        add_action( 'wp_ajax_aura_student_portal_my_forms',    [ __CLASS__, 'ajax_my_forms' ] );

        // Enqueue en frontend
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // ASSETS FRONTEND
    // ─────────────────────────────────────────────────────────────

    public static function enqueue_frontend_assets(): void {
        global $post;

        // Solo cargamos en páginas con nuestros shortcodes
        if ( ! $post || ! is_a( $post, 'WP_Post' ) ) return;

        $has_sc = (
            has_shortcode( $post->post_content, 'aura_student_login' )      ||
            has_shortcode( $post->post_content, 'aura_student_portal' )     ||
            has_shortcode( $post->post_content, 'aura_enrollment_form' )    ||
            has_shortcode( $post->post_content, 'aura_student_paz_salvo_check' )
        );

        if ( ! $has_sc ) return;

        wp_enqueue_style(
            'aura-students-frontend',
            AURA_PLUGIN_URL . 'assets/css/students-frontend.css',
            [],
            AURA_VERSION
        );

        wp_register_script(
            'aura-students-frontend',
            AURA_PLUGIN_URL . 'assets/js/students-frontend.js',
            [ 'jquery' ],
            AURA_VERSION,
            true
        );
        wp_enqueue_script( 'aura-students-frontend' );

        wp_localize_script( 'aura-students-frontend', 'auraStudentsFrontend', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aura_students_frontend_nonce' ),
            'strings' => [
                // Login
                'loading'        => __( 'Cargando…', 'aura-suite' ),
                'error'          => __( 'Ocurrió un error. Intenta de nuevo.', 'aura-suite' ),
                'checking'       => __( 'Verificando…', 'aura-suite' ),
                'login_btn'      => __( 'Ingresar al portal', 'aura-suite' ),
                'login_ok'       => __( 'Acceso correcto. Redirigiendo…', 'aura-suite' ),
                // Formulario de inscripción
                'submitting'     => __( 'Enviando…', 'aura-suite' ),
                'submit_btn'     => __( 'Enviar solicitud', 'aura-suite' ),
                'submit_ok'      => __( 'Solicitud enviada exitosamente.', 'aura-suite' ),
                'submit_error'   => __( 'Error al enviar la solicitud.', 'aura-suite' ),
                // Portal — cursos
                'scholarship'    => __( 'beca', 'aura-suite' ),
                'internal'       => __( 'interna', 'aura-suite' ),
                'external'       => __( 'externa', 'aura-suite' ),
                'covered'        => __( 'cubierto', 'aura-suite' ),
                'balance_lbl'    => __( 'Saldo:', 'aura-suite' ),
                'up_to_date'     => __( 'Al día', 'aura-suite' ),
                'err_courses'    => __( 'Error al cargar los cursos.', 'aura-suite' ),
                // Estado de inscripción
                'enrl_active'    => __( 'Activo', 'aura-suite' ),
                'enrl_completed' => __( 'Completado', 'aura-suite' ),
                'enrl_pending'   => __( 'Pendiente', 'aura-suite' ),
                'enrl_withdrawn' => __( 'Retirado', 'aura-suite' ),
                'enrl_suspended' => __( 'Suspendido', 'aura-suite' ),
                // Estado de pago
                'pay_unpaid'     => __( 'Sin pagar', 'aura-suite' ),
                'pay_partial'    => __( 'Parcial', 'aura-suite' ),
                'pay_paid'       => __( 'Pagado', 'aura-suite' ),
                'pay_overdue'    => __( 'Vencido', 'aura-suite' ),
                'pay_pending'    => __( 'Pendiente', 'aura-suite' ),
                // Métodos de pago
                'mth_cash'       => __( 'Efectivo', 'aura-suite' ),
                'mth_transfer'   => __( 'Transferencia', 'aura-suite' ),
                'mth_card'       => __( 'Tarjeta', 'aura-suite' ),
                'mth_check'      => __( 'Cheque', 'aura-suite' ),
                'mth_other'      => __( 'Otro', 'aura-suite' ),
                'receipt_view'   => __( 'Ver', 'aura-suite' ),
                'err_payments'   => __( 'Error al cargar los pagos.', 'aura-suite' ),
                // Certificados
                'cert_issued'    => __( 'Emitido:', 'aura-suite' ),
                'cert_pdf'       => __( 'Descargar PDF', 'aura-suite' ),
                'cert_qr'        => __( 'Ver QR', 'aura-suite' ),
                'err_certs'      => __( 'Error al cargar los certificados.', 'aura-suite' ),
                // Paz y salvo público
                'ps_enter_email' => __( 'Ingresa tu correo.', 'aura-suite' ),
                'ps_checking'    => __( 'Consultando…', 'aura-suite' ),
                'ps_check_btn'   => __( 'Consultar', 'aura-suite' ),
                'ps_ok'          => __( 'Estás al día con tus pagos.', 'aura-suite' ),
                'ps_debt'        => __( 'Tienes pagos pendientes.', 'aura-suite' ),
                'ps_not_found'   => __( 'Correo no encontrado.', 'aura-suite' ),
            ],
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // SHORTCODE: LOGIN
    // ─────────────────────────────────────────────────────────────

    public static function shortcode_login( array $atts ): string {
        $atts = shortcode_atts( [
            'redirect' => '',
        ], $atts, 'aura_student_login' );

        // Si ya está logueado y tiene el cap, redirigir al portal
        if ( is_user_logged_in() && current_user_can( 'aura_students_view_own' ) ) {
            $redirect = $atts['redirect'] ?: home_url( '/mi-portal/' );
            wp_safe_redirect( $redirect );
            exit;
        }

        $redirect_url = esc_url( $atts['redirect'] ?: home_url( '/mi-portal/' ) );
        $nonce        = wp_create_nonce( 'aura_students_frontend_nonce' );

        ob_start();
        include AURA_PLUGIN_DIR . 'templates/students/frontend/login.php';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────
    // SHORTCODE: PORTAL DEL ESTUDIANTE
    // ─────────────────────────────────────────────────────────────

    public static function shortcode_portal( array $atts ): string {
        if ( ! is_user_logged_in() ) {
            $login_url = self::get_login_page_url();
            return '<div class="aura-portal-notice">'
                . __( 'Debes iniciar sesión para acceder a tu portal.', 'aura-suite' )
                . ' <a href="' . esc_url( $login_url ) . '">' . __( 'Iniciar sesión', 'aura-suite' ) . '</a>'
                . '</div>';
        }

        if ( ! current_user_can( 'aura_students_view_own' ) && ! current_user_can( 'manage_options' ) ) {
            return '<div class="aura-portal-notice aura-portal-error">'
                . __( 'No tienes acceso al portal de estudiantes.', 'aura-suite' )
                . '</div>';
        }

        // Obtener datos del estudiante vinculado a este WP user
        $student = self::get_student_by_wp_user( get_current_user_id() );

        if ( ! $student ) {
            return '<div class="aura-portal-notice aura-portal-warning">'
                . __( 'No se encontró un perfil de estudiante vinculado a tu cuenta.', 'aura-suite' )
                . '</div>';
        }

        $nonce = wp_create_nonce( 'aura_students_frontend_nonce' );
        ob_start();
        include AURA_PLUGIN_DIR . 'templates/students/frontend/portal.php';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────
    // SHORTCODE: FORMULARIO DE INSCRIPCIÓN PÚBLICA
    // ─────────────────────────────────────────────────────────────

    public static function shortcode_enrollment_form( array $atts ): string {
        $atts = shortcode_atts( [
            'type' => '', // student|volunteer|teacher|participant|intern
        ], $atts, 'aura_enrollment_form' );

        $allowed_types = [ 'student', 'volunteer', 'teacher', 'participant', 'intern' ];
        $form_type     = in_array( $atts['type'], $allowed_types, true ) ? $atts['type'] : '';

        // Rate limit check — max 3 envíos en 24h por IP
        $ip           = self::get_client_ip();
        $rate_key     = 'aura_enroll_rate_' . md5( $ip );
        $rate_count   = (int) get_transient( $rate_key );
        $rate_limit   = 3;
        $rate_blocked = $rate_count >= $rate_limit;

        // Áreas de interés
        global $wpdb;
        $areas = [];
        $ta    = $wpdb->prefix . 'aura_areas';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ta}'" ) === $ta ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $areas = $wpdb->get_results(
                "SELECT id, name FROM {$ta} WHERE type='program' AND status='active' ORDER BY name ASC"
            );
        }

        $nonce = wp_create_nonce( 'aura_students_frontend_nonce' );

        ob_start();
        include AURA_PLUGIN_DIR . 'templates/students/frontend/enrollment-form.php';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────
    // SHORTCODE: VERIFICACIÓN PAZ Y SALVO
    // ─────────────────────────────────────────────────────────────

    public static function shortcode_paz_salvo_check( array $atts ): string {
        ob_start();
        ?>
        <div class="aura-paz-salvo-check">
            <h3><?php esc_html_e( 'Verificar Estado de Paz y Salvo', 'aura-suite' ); ?></h3>
            <div class="aura-ps-form">
                <input type="email" id="aura-ps-email" placeholder="<?php esc_attr_e( 'Tu correo electrónico', 'aura-suite' ); ?>" class="aura-input aura-input-full" />
                <button id="aura-ps-btn" class="aura-btn aura-btn-primary">
                    <?php esc_html_e( 'Consultar', 'aura-suite' ); ?>
                </button>
            </div>
            <div id="aura-ps-result" style="display:none;margin-top:12px;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: LOGIN
    // ─────────────────────────────────────────────────────────────

    public static function ajax_login(): void {
        check_ajax_referer( 'aura_students_frontend_nonce', 'nonce' );

        $username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
        $password = wp_unslash( $_POST['password'] ?? '' );
        $redirect = esc_url_raw( wp_unslash( $_POST['redirect'] ?? '' ) );

        if ( empty( $username ) || empty( $password ) ) {
            wp_send_json_error( [ 'message' => __( 'Usuario y contraseña son obligatorios.', 'aura-suite' ) ] );
        }

        $user = wp_signon( [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => ! empty( $_POST['remember'] ),
        ], is_ssl() );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( [ 'message' => __( 'Usuario o contraseña incorrectos.', 'aura-suite' ) ] );
        }

        if (
            ! user_can( $user->ID, 'aura_students_view_own' ) &&
            ! user_can( $user->ID, 'manage_options' )
        ) {
            wp_logout();
            wp_send_json_error( [ 'message' => __( 'Tu cuenta no tiene acceso al portal de estudiantes.', 'aura-suite' ) ] );
        }

        $portal_url = $redirect ?: self::get_portal_page_url();
        wp_send_json_success( [ 'redirect' => $portal_url ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ENVIAR FORMULARIO DE INSCRIPCIÓN
    // ─────────────────────────────────────────────────────────────

    public static function ajax_submit_enrollment(): void {
        check_ajax_referer( 'aura_students_frontend_nonce', 'nonce' );

        // Rate limiting por IP
        $ip         = self::get_client_ip();
        $rate_key   = 'aura_enroll_rate_' . md5( $ip );
        $rate_count = (int) get_transient( $rate_key );

        if ( $rate_count >= 3 ) {
            wp_send_json_error( [
                'message' => __( 'Has enviado demasiadas solicitudes. Por favor espera 24 horas antes de intentarlo de nuevo.', 'aura-suite' ),
            ] );
        }

        global $wpdb;
        $ts = $wpdb->prefix . 'aura_students';

        $first_name  = sanitize_text_field( wp_unslash( $_POST['first_name']  ?? '' ) );
        $last_name   = sanitize_text_field( wp_unslash( $_POST['last_name']   ?? '' ) );
        $email       = sanitize_email(      wp_unslash( $_POST['email']       ?? '' ) );
        $phone       = sanitize_text_field( wp_unslash( $_POST['phone']       ?? '' ) );
        $id_number   = sanitize_text_field( wp_unslash( $_POST['id_number']   ?? '' ) );
        $birthdate   = sanitize_text_field( wp_unslash( $_POST['birthdate']   ?? '' ) );
        $gender      = sanitize_key(        wp_unslash( $_POST['gender']      ?? '' ) );
        $address     = sanitize_text_field( wp_unslash( $_POST['address']     ?? '' ) );
        $city        = sanitize_text_field( wp_unslash( $_POST['city']        ?? '' ) );
        $country     = sanitize_text_field( wp_unslash( $_POST['country']     ?? 'US' ) );
        $motivation  = sanitize_textarea_field( wp_unslash( $_POST['motivation']  ?? '' ) );
        $supported_by= sanitize_text_field( wp_unslash( $_POST['supported_by']   ?? '' ) );
        $talent      = sanitize_textarea_field( wp_unslash( $_POST['talent']      ?? '' ) );
        $experience  = sanitize_textarea_field( wp_unslash( $_POST['experience']  ?? '' ) );
        $extra_info  = sanitize_textarea_field( wp_unslash( $_POST['extra_info']  ?? '' ) );
        $profile_type= sanitize_key(        wp_unslash( $_POST['profile_type']    ?? 'student' ) );

        // Áreas de interés (array de IDs)
        $preferred_areas_raw = isset( $_POST['preferred_areas'] ) && is_array( $_POST['preferred_areas'] )
            ? array_map( 'intval', $_POST['preferred_areas'] )
            : [];
        $preferred_areas     = wp_json_encode( array_filter( $preferred_areas_raw ) );

        // Validaciones
        if ( empty( $first_name ) || empty( $last_name ) || empty( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'Nombre, apellido y correo son obligatorios.', 'aura-suite' ) ] );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'El correo electrónico no es válido.', 'aura-suite' ) ] );
        }

        $allowed_types = [ 'student', 'volunteer', 'teacher', 'participant', 'intern' ];
        if ( ! in_array( $profile_type, $allowed_types, true ) ) {
            $profile_type = 'student';
        }

        // Verificar si ya existe
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$ts} WHERE email = %s AND deleted_at IS NULL",
            $email
        ) );
        if ( $existing ) {
            wp_send_json_error( [
                'message' => __( 'Ya existe una solicitud con este correo electrónico.', 'aura-suite' ),
            ] );
        }

        $data   = [
            'profile_type'   => $profile_type,
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'email'          => $email,
            'phone'          => $phone,
            'id_number'      => $id_number,
            'birthdate'      => $birthdate ?: null,
            'gender'         => in_array( $gender, [ 'M', 'F', 'otro', 'prefiero_no_decir' ], true ) ? $gender : null,
            'address'        => $address,
            'city'           => $city,
            'country'        => $country ?: 'US',
            'preferred_areas'=> $preferred_areas,
            'motivation'     => $motivation,
            'supported_by'   => $supported_by,
            'talent'         => $talent,
            'experience'     => $experience,
            'extra_info'     => $extra_info,
            'status'         => 'applicant',
            'created_by'     => 0, // público, sin usuario
        ];

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $inserted = $wpdb->insert( $ts, $data );

        if ( ! $inserted ) {
            wp_send_json_error( [ 'message' => __( 'No se pudo guardar la solicitud. Por favor intenta más tarde.', 'aura-suite' ) ] );
        }

        $student_id = $wpdb->insert_id;

        // Actualizar contador de rate limiting
        set_transient( $rate_key, $rate_count + 1, DAY_IN_SECONDS );

        // Notificar a admins (si la clase de notificaciones está disponible)
        if ( class_exists( 'Aura_Students_Notifications' ) ) {
            Aura_Students_Notifications::notify_new_applicant( $student_id );
        } else {
            // Fallback: notificación básica al admin
            $admin_email = get_option( 'admin_email' );
            $subject     = sprintf( __( '[AURA] Nueva solicitud de %s %s', 'aura-suite' ), $first_name, $last_name );
            $body        = sprintf(
                __( "Se ha recibido una nueva solicitud de inscripción.\n\nNombre: %s %s\nEmail: %s\nTipo: %s\n\nRevisa el panel de administración para aprobar o rechazar.", 'aura-suite' ),
                $first_name, $last_name, $email, $profile_type
            );
            wp_mail( $admin_email, $subject, $body );
        }

        wp_send_json_success( [
            'message'    => __( '¡Solicitud enviada! Te contactaremos pronto con la respuesta.', 'aura-suite' ),
            'student_id' => $student_id,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: MIS CURSOS (portal del estudiante)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_my_courses(): void {
        check_ajax_referer( 'aura_students_frontend_nonce', 'nonce' );
        self::require_student_cap();

        global $wpdb;
        $student = self::get_student_by_wp_user( get_current_user_id() );
        if ( ! $student ) {
            wp_send_json_error( [ 'message' => __( 'Perfil no encontrado.', 'aura-suite' ) ] );
        }

        $te = $wpdb->prefix . 'aura_student_enrollments';
        $tc = $wpdb->prefix . 'aura_student_courses';
        $ta = $wpdb->prefix . 'aura_areas';

        $areas_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ta ) );

        $area_join = '';
        $area_cols = "NULL AS area_id, '' AS area_name";
        if ( $areas_exists ) {
            $area_join = "LEFT JOIN `{$ta}` ar ON ar.id = c.area_id";
            $area_cols = 'ar.id AS area_id, ar.name AS area_name';
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id AS enrollment_id, e.status AS enrollment_status,
                    e.enrollment_date, e.base_cost, e.net_cost,
                    e.scholarship_pct, e.scholarship_type,
                    e.payment_scheme, e.installment_count, e.installment_amount,
                    e.total_paid, e.balance_due, e.payment_status,
                    c.id AS course_id, c.name AS course_name,
                    c.description AS course_desc,
                    c.start_date, c.end_date,
                    {$area_cols}
             FROM {$te} e
             JOIN {$tc} c ON c.id = e.course_id
             {$area_join}
             WHERE e.student_id = %d AND e.status IN ('active','completed','pending')
             ORDER BY e.enrollment_date DESC",
            $student->id
        ) );

        // Agrupar por área
        $grouped = [];
        foreach ( $rows as $row ) {
            $area_key = $row->area_id ?: 0;
            if ( ! isset( $grouped[ $area_key ] ) ) {
                $grouped[ $area_key ] = [
                    'area_id'   => $area_key,
                    'area_name' => $row->area_name ?: __( 'Sin área', 'aura-suite' ),
                    'courses'   => [],
                ];
            }
            $grouped[ $area_key ]['courses'][] = $row;
        }

        wp_send_json_success( [
            'areas'  => array_values( $grouped ),
            'total'  => count( $rows ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: MIS PAGOS (portal del estudiante)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_my_payments(): void {
        check_ajax_referer( 'aura_students_frontend_nonce', 'nonce' );
        self::require_student_cap();

        global $wpdb;
        $student = self::get_student_by_wp_user( get_current_user_id() );
        if ( ! $student ) {
            wp_send_json_error( [ 'message' => __( 'Perfil no encontrado.', 'aura-suite' ) ] );
        }

        $tp  = $wpdb->prefix . 'aura_student_payments';
        $tc  = $wpdb->prefix . 'aura_student_courses';
        $tis = $wpdb->prefix . 'aura_student_installment_schedule';
        $te  = $wpdb->prefix . 'aura_student_enrollments';

        // Pagos realizados
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.id, p.payment_date, p.amount, p.payment_method,
                    p.reference_number, p.receipt_url, p.installment_num, p.notes,
                    c.name AS course_name
             FROM {$tp} p
             JOIN {$tc} c ON c.id = p.course_id
             WHERE p.student_id = %d
             ORDER BY p.payment_date DESC, p.id DESC",
            $student->id
        ) );

        // Cuotas pendientes/vencidas
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pending_installments = [];
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tis ) ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $pending_installments = $wpdb->get_results( $wpdb->prepare(
                "SELECT is2.id, is2.installment_num, is2.due_date,
                        is2.expected_amount, is2.paid_amount, is2.status,
                        c.name AS course_name
                 FROM {$tis} is2
                 JOIN {$te} e ON e.id = is2.enrollment_id
                 JOIN {$tc} c ON c.id = e.course_id
                 WHERE e.student_id = %d AND is2.status IN ('pending','overdue','partial')
                 ORDER BY is2.due_date ASC",
                $student->id
            ) );
        }

        // Resumen de deuda
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $summary = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                SUM(total_paid)  AS total_paid,
                SUM(balance_due) AS total_balance,
                SUM(net_cost)    AS total_net
             FROM {$te}
             WHERE student_id = %d AND status IN ('active','completed','pending')",
            $student->id
        ) );

        wp_send_json_success( [
            'payments'             => $payments,
            'pending_installments' => $pending_installments,
            'summary'              => $summary,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: MIS CERTIFICADOS (portal del estudiante)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_my_certs(): void {
        check_ajax_referer( 'aura_students_frontend_nonce', 'nonce' );
        self::require_student_cap();

        global $wpdb;
        $student = self::get_student_by_wp_user( get_current_user_id() );
        if ( ! $student ) {
            wp_send_json_error( [ 'message' => __( 'Perfil no encontrado.', 'aura-suite' ) ] );
        }

        $certs = [];

        // Si el módulo de certificados existe, consultar su tabla
        $tci = $wpdb->prefix . 'aura_certificate_issued';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tci ) ) ) {
            $tc = $wpdb->prefix . 'aura_student_courses';
            $ta = $wpdb->prefix . 'aura_areas';
            $areas_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ta ) );
            $area_join = $areas_exists ? "LEFT JOIN `{$ta}` ar ON ar.id = c.area_id" : '';
            $area_col  = $areas_exists ? 'ar.name AS area_name' : "'' AS area_name";

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $certs = $wpdb->get_results( $wpdb->prepare(
                "SELECT ci.id, ci.issued_date, ci.certificate_url, ci.qr_url,
                        c.name AS course_name, {$area_col}
                 FROM {$tci} ci
                 JOIN {$tc} c ON c.id = ci.course_id
                 {$area_join}
                 WHERE ci.student_id = %d
                 ORDER BY ci.issued_date DESC",
                $student->id
            ) );
        }

        wp_send_json_success( [ 'certificates' => $certs ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: MIS FORMULARIOS (portal del estudiante)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_my_forms(): void {
        check_ajax_referer( 'aura_students_frontend_nonce', 'nonce' );
        self::require_student_cap();

        if ( ! class_exists( 'Aura_Forms_Frontend' ) ) {
            wp_send_json_error( [ 'message' => __( 'El módulo de formularios no está disponible.', 'aura-suite' ) ] );
        }

        $html = Aura_Forms_Frontend::shortcode_portal( [] );
        wp_send_json_success( [ 'html' => $html ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve el registro de aura_students vinculado al WP user actual.
     */
    public static function get_student_by_wp_user( int $wp_user_id ): ?object {
        global $wpdb;
        $ts = $wpdb->prefix . 'aura_students';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ts ) ) !== $ts ) {
            return null;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$ts} WHERE wp_user_id = %d AND deleted_at IS NULL",
            $wp_user_id
        ) );
    }

    /**
     * Termina el request con error si el usuario no tiene capacidad de portal.
     */
    private static function require_student_cap(): void {
        if (
            ! current_user_can( 'aura_students_view_own' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Acceso denegado.', 'aura-suite' ) ] );
        }
    }

    /**
     * Obtiene la URL de la página del portal desde opciones.
     */
    private static function get_portal_page_url(): string {
        $settings = get_option( 'aura_students_settings', [] );
        $page_id  = (int) ( $settings['portal_page_id'] ?? 0 );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) return $url;
        }
        return home_url( '/mi-portal/' );
    }

    /**
     * Obtiene la URL del login del portal.
     */
    private static function get_login_page_url(): string {
        $settings = get_option( 'aura_students_settings', [] );
        $page_id  = (int) ( $settings['login_page_id'] ?? 0 );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
            if ( $url ) return $url;
        }
        return home_url( '/acceso-estudiantes/' );
    }

    /**
     * IP del cliente de forma segura.
     */
    private static function get_client_ip(): string {
        // No usamos REMOTE_ADDR directamente con proxy — solo como fallback
        $ip = '';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = filter_var( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ), FILTER_VALIDATE_IP );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            // Puede contener múltiples IPs separadas por coma; tomamos la primera
            $parts = explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            $ip    = filter_var( trim( $parts[0] ), FILTER_VALIDATE_IP );
        }
        if ( ! $ip ) {
            $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : '0.0.0.0';
        }
        return $ip ?: '0.0.0.0';
    }
}
