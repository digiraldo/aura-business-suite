<?php
/**
 * Setup del Módulo de Formularios y Encuestas
 *
 * Responsabilidades:
 *  - Crear / migrar las 4 tablas de BD del módulo con dbDelta()
 *  - Registrar rewrite rules para la URL pública /formulario/{slug}/
 *  - Registrar cron events para auto-asignación programada
 *  - Escuchar hooks de Estudiantes para auto-asignación de encuestas feedback
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Setup {

    /** Versión del esquema de BD del módulo */
    const DB_VERSION        = '1.2.0';
    const DB_VERSION_OPTION = 'aura_forms_db_version';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // Migración automática si la versión cambió
        if ( self::needs_update() ) {
            add_action( 'admin_init', [ __CLASS__, 'create_tables' ] );
        }

        // URL amigable /formulario/{slug}/
        add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
        add_action( 'init',       [ __CLASS__, 'register_rewrite_rules' ] );
        // Flush en admin_init (más fiable: ocurre después de que WP ya procesó las reglas del request actual)
        add_action( 'admin_init', [ __CLASS__, 'maybe_flush_rewrite' ] );

        // Cron de auto-asignación programada
        add_action( 'aura_forms_auto_assign', [ __CLASS__, 'run_scheduled_assignment' ], 10, 3 );

        // Escuchar hooks de Módulo Estudiantes para auto-asignación de formularios feedback
        add_action( 'aura_student_enrollment_approved', [ __CLASS__, 'on_enrollment_approved' ], 10, 2 );
        add_action( 'aura_student_course_completed',    [ __CLASS__, 'on_course_completed' ],    10, 2 );
    }

    // ─────────────────────────────────────────────────────────────
    // COMPROBACIÓN DE VERSIÓN
    // ─────────────────────────────────────────────────────────────

    public static function needs_update(): bool {
        return version_compare(
            get_option( self::DB_VERSION_OPTION, '0' ),
            self::DB_VERSION,
            '<'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // BASE DE DATOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Crear o actualizar las 4 tablas del módulo.
     * Idempotente: dbDelta() solo agrega columnas/índices faltantes.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t_forms       = $wpdb->prefix . 'aura_forms';
        $t_fields      = $wpdb->prefix . 'aura_form_fields';
        $t_submissions = $wpdb->prefix . 'aura_form_submissions';
        $t_assignments = $wpdb->prefix . 'aura_form_assignments';

        // ── 1. Formularios ───────────────────────────────────────
        $sql_forms = "CREATE TABLE {$t_forms} (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title               VARCHAR(300) NOT NULL,
            slug                VARCHAR(300) NOT NULL,
            description         TEXT DEFAULT NULL,
            type                VARCHAR(20) NOT NULL DEFAULT 'generic',
            course_id           BIGINT(20) UNSIGNED DEFAULT NULL,
            area_id             BIGINT(20) UNSIGNED DEFAULT NULL,
            submit_button_label VARCHAR(200) NOT NULL DEFAULT 'Enviar',
            success_message     TEXT DEFAULT NULL,
            redirect_url        VARCHAR(500) DEFAULT NULL,
            is_active           TINYINT(1) NOT NULL DEFAULT 1,
            requires_login      TINYINT(1) NOT NULL DEFAULT 0,
            accept_multiple     TINYINT(1) NOT NULL DEFAULT 0,
            max_submissions     INT(10) UNSIGNED DEFAULT NULL,
            close_date          DATETIME DEFAULT NULL,
            primary_color       VARCHAR(7) DEFAULT '#2563eb',
            logo_url            VARCHAR(500) DEFAULT NULL,
            company_name        VARCHAR(300) DEFAULT NULL,
            notify_admin_emails TEXT DEFAULT NULL,
            notify_submitter    TINYINT(1) NOT NULL DEFAULT 0,
            auto_assign_trigger VARCHAR(50) NOT NULL DEFAULT 'none',
            auto_assign_days    TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
            created_by          BIGINT(20) UNSIGNED NOT NULL,
            updated_by          BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at          DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_slug   (slug),
            KEY idx_type         (type),
            KEY idx_active       (is_active),
            KEY idx_course       (course_id),
            KEY idx_area         (area_id),
            KEY idx_deleted      (deleted_at)
        ) ENGINE=InnoDB {$charset_collate};";

        // ── 2. Campos del formulario ─────────────────────────────
        $sql_fields = "CREATE TABLE {$t_fields} (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id             BIGINT(20) UNSIGNED NOT NULL,
            field_uid           VARCHAR(20) NOT NULL,
            label               VARCHAR(500) NOT NULL,
            description         TEXT DEFAULT NULL,
            field_type          VARCHAR(30) NOT NULL DEFAULT 'text',
            options_json        TEXT DEFAULT NULL,
            min_value           DECIMAL(10,2) DEFAULT NULL,
            max_value           DECIMAL(10,2) DEFAULT NULL,
            allowed_extensions  VARCHAR(200) DEFAULT 'jpg,jpeg,png,pdf',
            max_file_size_kb    INT(10) UNSIGNED DEFAULT 5120,
            placeholder         VARCHAR(500) DEFAULT NULL,
            default_value       VARCHAR(500) DEFAULT NULL,
            is_required         TINYINT(1) NOT NULL DEFAULT 0,
            multiple_select     TINYINT(1) NOT NULL DEFAULT 0,
            has_other           TINYINT(1) NOT NULL DEFAULT 0,
            image_url           VARCHAR(500) DEFAULT NULL,
            file_uploaded       VARCHAR(500) DEFAULT NULL,
            file_url            VARCHAR(500) DEFAULT NULL,
            instructions        TEXT DEFAULT NULL,
            terms_text          TEXT DEFAULT NULL,
            disagreement_message VARCHAR(500) DEFAULT NULL,
            mapping_key         VARCHAR(100) DEFAULT NULL,
            sort_order          SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
            created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_field_uid (form_id, field_uid),
            KEY idx_form  (form_id),
            KEY idx_sort  (form_id, sort_order),
            KEY idx_mapping (mapping_key)
        ) ENGINE=InnoDB {$charset_collate};";

        // ── 3. Submissions / respuestas ──────────────────────────
        $sql_submissions = "CREATE TABLE {$t_submissions} (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id             BIGINT(20) UNSIGNED NOT NULL,
            wp_user_id          BIGINT(20) UNSIGNED DEFAULT NULL,
            submitted_name      VARCHAR(300) DEFAULT NULL,
            submitted_email     VARCHAR(300) DEFAULT NULL,
            data_json           LONGTEXT NOT NULL,
            source_url          VARCHAR(1000) DEFAULT NULL,
            ip_address          VARCHAR(45) DEFAULT NULL,
            user_agent          VARCHAR(500) DEFAULT NULL,
            status              VARCHAR(20) NOT NULL DEFAULT 'received',
            enrollment_id       BIGINT(20) UNSIGNED DEFAULT NULL,
            submitted_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_by         BIGINT(20) UNSIGNED DEFAULT NULL,
            reviewed_at         DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_form         (form_id),
            KEY idx_user         (wp_user_id),
            KEY idx_status       (status),
            KEY idx_submitted_at (submitted_at),
            KEY idx_enrollment   (enrollment_id)
        ) ENGINE=InnoDB {$charset_collate};";

        // ── 4. Asignaciones de encuestas a estudiantes ───────────
        $sql_assignments = "CREATE TABLE {$t_assignments} (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id             BIGINT(20) UNSIGNED NOT NULL,
            student_id          BIGINT(20) UNSIGNED NOT NULL,
            enrollment_id       BIGINT(20) UNSIGNED DEFAULT NULL,
            status              VARCHAR(20) NOT NULL DEFAULT 'pending',
            submission_id       BIGINT(20) UNSIGNED DEFAULT NULL,
            assigned_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at          DATETIME DEFAULT NULL,
            completed_at        DATETIME DEFAULT NULL,
            assigned_by         BIGINT(20) UNSIGNED DEFAULT NULL,
            assignment_trigger  VARCHAR(100) DEFAULT NULL,
            notes               TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_assignment (form_id, student_id, enrollment_id),
            KEY idx_form          (form_id),
            KEY idx_student       (student_id),
            KEY idx_status        (status),
            KEY idx_expires       (expires_at)
        ) ENGINE=InnoDB {$charset_collate};";

        dbDelta( $sql_forms );
        dbDelta( $sql_fields );
        dbDelta( $sql_submissions );
        dbDelta( $sql_assignments );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    // ─────────────────────────────────────────────────────────────
    // REWRITE RULES
    // ─────────────────────────────────────────────────────────────

    /**
     * Registra el query var público para que get_query_var('aura_form_slug') funcione.
     * Debe engancharse a 'query_vars' ANTES de que WP procese la solicitud.
     *
     * @param array $vars Lista actual de query vars públicas.
     * @return array Lista ampliada.
     */
    public static function register_query_vars( array $vars ): array {
        $vars[] = 'aura_form_slug';
        return $vars;
    }

    /**
     * URL amigable: /formulario/{slug}/
     */
    /**
     * Registra la rewrite rule y el query var para /formulario/{slug}/
     */
    public static function register_rewrite_rules(): void {
        add_rewrite_rule(
            '^formulario/([a-z0-9\-_]+)/?$',
            'index.php?aura_form_slug=$matches[1]',
            'top'
        );
    }

    /**
     * Flush de las rewrite rules en la primera carga de admin tras instalar o actualizar el módulo.
     * Se ejecuta en admin_init (después de que WP ya leyó las reglas del request actual),
     * por lo que el cambio toma efecto desde el siguiente request.
     * Usa hard flush (true) para actualizar también .htaccess en Apache.
     */
    public static function maybe_flush_rewrite(): void {
        if ( get_option( 'aura_forms_rewrite_version' ) !== self::DB_VERSION ) {
            self::register_rewrite_rules();
            flush_rewrite_rules( true );
            update_option( 'aura_forms_rewrite_version', self::DB_VERSION );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AUTO-ASIGNACIÓN POR HOOKS DE ESTUDIANTES
    // ─────────────────────────────────────────────────────────────

    /**
     * Cuando se aprueba un enrollment → buscar formularios feedback vinculados.
     *
     * @param int $student_id   ID en aura_students
     * @param int $enrollment_id ID en aura_student_enrollments
     */
    public static function on_enrollment_approved( int $student_id, int $enrollment_id ): void {
        global $wpdb;

        // Necesitamos el course_id del enrollment para buscar formularios coincidentes
        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT course_id FROM {$wpdb->prefix}aura_student_enrollments WHERE id = %d LIMIT 1",
            $enrollment_id
        ) );

        if ( ! $enrollment ) return;

        $forms = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, auto_assign_days
             FROM {$wpdb->prefix}aura_forms
             WHERE type = 'feedback'
               AND auto_assign_trigger = 'on_enrollment_approved'
               AND ( course_id = %d OR course_id IS NULL )
               AND is_active = 1
               AND deleted_at IS NULL",
            $enrollment->course_id
        ) );

        if ( empty( $forms ) ) return;

        foreach ( $forms as $form ) {
            if ( (int) $form->auto_assign_days > 0 ) {
                wp_schedule_single_event(
                    time() + ( (int) $form->auto_assign_days * DAY_IN_SECONDS ),
                    'aura_forms_auto_assign',
                    [ (int) $form->id, $student_id, $enrollment_id ]
                );
            } else {
                Aura_Forms_Assignments::create_assignment(
                    (int) $form->id,
                    $student_id,
                    $enrollment_id,
                    null,
                    'on_enrollment_approved'
                );
            }
        }
    }

    /**
     * Cuando se marca un curso como completado por un estudiante.
     *
     * @param int $student_id
     * @param int $enrollment_id
     */
    public static function on_course_completed( int $student_id, int $enrollment_id ): void {
        global $wpdb;

        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT course_id FROM {$wpdb->prefix}aura_student_enrollments WHERE id = %d LIMIT 1",
            $enrollment_id
        ) );

        if ( ! $enrollment ) return;

        $forms = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, auto_assign_days
             FROM {$wpdb->prefix}aura_forms
             WHERE type = 'feedback'
               AND auto_assign_trigger = 'on_course_complete'
               AND ( course_id = %d OR course_id IS NULL )
               AND is_active = 1
               AND deleted_at IS NULL",
            $enrollment->course_id
        ) );

        if ( empty( $forms ) ) return;

        foreach ( $forms as $form ) {
            if ( (int) $form->auto_assign_days > 0 ) {
                wp_schedule_single_event(
                    time() + ( (int) $form->auto_assign_days * DAY_IN_SECONDS ),
                    'aura_forms_auto_assign',
                    [ (int) $form->id, $student_id, $enrollment_id ]
                );
            } else {
                Aura_Forms_Assignments::create_assignment(
                    (int) $form->id,
                    $student_id,
                    $enrollment_id,
                    null,
                    'on_course_complete'
                );
            }
        }
    }

    /**
     * Cron callback: ejecutar la asignación programada después de N días.
     *
     * @param int      $form_id
     * @param int      $student_id
     * @param int|null $enrollment_id
     */
    public static function run_scheduled_assignment( int $form_id, int $student_id, ?int $enrollment_id ): void {
        Aura_Forms_Assignments::create_assignment(
            $form_id,
            $student_id,
            $enrollment_id,
            null,
            'scheduled'
        );
    }
}
