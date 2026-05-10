<?php
/**
 * Setup del Módulo de Estudiantes e Inscripciones
 *
 * Responsabilidades:
 *  - Crear / migrar las 5 tablas de BD del módulo
 *  - Programar WP Cron de recordatorios diarios
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Setup {

    /** Versión del esquema de BD del módulo */
    const DB_VERSION        = '1.1.0'; // 1.1.0: agrega preferred_areas a aura_students
    const DB_VERSION_OPTION = 'aura_students_db_version';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // Migración automática si la versión cambió
        if ( self::needs_update() ) {
            add_action( 'admin_init', [ __CLASS__, 'create_tables' ] );
        }

        // Programar cron diario de recordatorios si aún no está programado
        if ( ! wp_next_scheduled( 'aura_students_daily_reminders' ) ) {
            wp_schedule_event( time(), 'daily', 'aura_students_daily_reminders' );
        }
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
     * Crear o actualizar las 5 tablas del módulo.
     * Es idempotente: dbDelta() solo agrega columnas/índices faltantes.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t_courses     = $wpdb->prefix . 'aura_student_courses';
        $t_students    = $wpdb->prefix . 'aura_students';
        $t_enrollments = $wpdb->prefix . 'aura_student_enrollments';
        $t_payments    = $wpdb->prefix . 'aura_student_payments';
        $t_schedule    = $wpdb->prefix . 'aura_student_installment_schedule';

        // ── 1. Cursos / Programas académicos ─────────────────────
        $sql_courses = "CREATE TABLE {$t_courses} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name            VARCHAR(300) NOT NULL,
            slug            VARCHAR(300) NOT NULL,
            description     TEXT DEFAULT NULL,
            area_id         BIGINT(20) UNSIGNED DEFAULT NULL,
            instructor_id   BIGINT(20) UNSIGNED DEFAULT NULL,
            duration_weeks  SMALLINT(5) UNSIGNED DEFAULT NULL,
            max_students    SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
            base_cost       DECIMAL(12,2) NOT NULL DEFAULT '0.00',
            currency        VARCHAR(5) NOT NULL DEFAULT 'USD',
            status          ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
            start_date      DATE DEFAULT NULL,
            end_date        DATE DEFAULT NULL,
            finance_cat_id  BIGINT(20) UNSIGNED DEFAULT NULL,
            created_by      BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_slug (slug(250)),
            KEY idx_status (status),
            KEY idx_area (area_id),
            KEY idx_instructor (instructor_id)
        ) {$charset_collate};";

        // ── 2. Estudiantes / Perfiles de participante ─────────────
        $sql_students = "CREATE TABLE {$t_students} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id      BIGINT(20) UNSIGNED DEFAULT NULL,
            profile_type    ENUM('student','volunteer','teacher','participant','intern') NOT NULL DEFAULT 'student',
            first_name      VARCHAR(150) NOT NULL,
            last_name       VARCHAR(150) NOT NULL,
            email           VARCHAR(254) NOT NULL,
            phone           VARCHAR(30) DEFAULT NULL,
            phone_country   VARCHAR(5) NOT NULL DEFAULT '+1',
            id_number       VARCHAR(50) DEFAULT NULL,
            id_type         ENUM('cedula','pasaporte','dni','otro') DEFAULT NULL,
            birthdate       DATE DEFAULT NULL,
            gender          ENUM('M','F','otro','prefiero_no_decir') DEFAULT NULL,
            address         VARCHAR(500) DEFAULT NULL,
            city            VARCHAR(100) DEFAULT NULL,
            country         VARCHAR(100) NOT NULL DEFAULT 'US',
            photo_url       VARCHAR(500) DEFAULT NULL,
            preferred_areas TEXT DEFAULT NULL,
            motivation      TEXT DEFAULT NULL,
            supported_by    VARCHAR(300) DEFAULT NULL,
            talent          TEXT DEFAULT NULL,
            experience      TEXT DEFAULT NULL,
            extra_info      TEXT DEFAULT NULL,
            status          ENUM('applicant','approved','active','graduated','withdrawn','rejected') NOT NULL DEFAULT 'applicant',
            rejection_reason VARCHAR(500) DEFAULT NULL,
            approved_by     BIGINT(20) UNSIGNED DEFAULT NULL,
            approved_at     DATETIME DEFAULT NULL,
            graduated_at    DATE DEFAULT NULL,
            notes           TEXT DEFAULT NULL,
            created_by      BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at      DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_wp_user (wp_user_id),
            KEY idx_status (status),
            KEY idx_email (email(100)),
            KEY idx_profile_type (profile_type),
            KEY idx_deleted (deleted_at)
        ) {$charset_collate};";

        // ── 3. Inscripciones (relación estudiante ↔ curso) ───────
        $sql_enrollments = "CREATE TABLE {$t_enrollments} (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id          BIGINT(20) UNSIGNED NOT NULL,
            course_id           BIGINT(20) UNSIGNED NOT NULL,
            enrollment_date     DATE NOT NULL,
            status              ENUM('pending','active','completed','withdrawn','suspended') NOT NULL DEFAULT 'pending',
            base_cost           DECIMAL(12,2) NOT NULL DEFAULT '0.00',
            scholarship_type    ENUM('none','internal','external') NOT NULL DEFAULT 'none',
            scholarship_pct     TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
            scholarship_sponsor VARCHAR(200) DEFAULT NULL,
            scholarship_notes   TEXT DEFAULT NULL,
            net_cost            DECIMAL(12,2) NOT NULL DEFAULT '0.00',
            payment_scheme      ENUM('full','installments','scholarship_full') NOT NULL DEFAULT 'full',
            installment_count   TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
            installment_amount  DECIMAL(12,2) NOT NULL DEFAULT '0.00',
            first_payment_date  DATE DEFAULT NULL,
            total_paid          DECIMAL(12,2) NOT NULL DEFAULT '0.00',
            balance_due         DECIMAL(12,2) NOT NULL DEFAULT '0.00',
            payment_status      ENUM('unpaid','partial','paid','overdue') NOT NULL DEFAULT 'unpaid',
            enrolled_by         BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            notes               TEXT DEFAULT NULL,
            created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_student_course (student_id, course_id),
            KEY idx_student (student_id),
            KEY idx_course (course_id),
            KEY idx_status (status),
            KEY idx_pay_status (payment_status)
        ) {$charset_collate};";

        // ── 4. Pagos individuales (cuotas) ────────────────────────
        $sql_payments = "CREATE TABLE {$t_payments} (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            enrollment_id       BIGINT(20) UNSIGNED NOT NULL,
            student_id          BIGINT(20) UNSIGNED NOT NULL,
            course_id           BIGINT(20) UNSIGNED NOT NULL,
            payment_date        DATE NOT NULL,
            amount              DECIMAL(12,2) NOT NULL,
            payment_method      ENUM('cash','transfer','card','check','other') NOT NULL DEFAULT 'cash',
            reference_number    VARCHAR(100) DEFAULT NULL,
            receipt_url         VARCHAR(500) DEFAULT NULL,
            installment_num     TINYINT(3) UNSIGNED DEFAULT NULL,
            finance_tx_id       BIGINT(20) UNSIGNED DEFAULT NULL,
            notes               TEXT DEFAULT NULL,
            registered_by       BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_enrollment (enrollment_id),
            KEY idx_student (student_id),
            KEY idx_date (payment_date),
            KEY idx_finance_tx (finance_tx_id)
        ) {$charset_collate};";

        // ── 5. Calendario de cuotas programadas ───────────────────
        $sql_schedule = "CREATE TABLE {$t_schedule} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            enrollment_id   BIGINT(20) UNSIGNED NOT NULL,
            installment_num TINYINT(3) UNSIGNED NOT NULL,
            due_date        DATE NOT NULL,
            expected_amount DECIMAL(12,2) NOT NULL,
            paid_amount     DECIMAL(12,2) NOT NULL DEFAULT '0.00',
            status          ENUM('pending','paid','overdue','partial') NOT NULL DEFAULT 'pending',
            payment_id      BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_enrollment (enrollment_id),
            KEY idx_due_date (due_date),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta( $sql_courses );
        dbDelta( $sql_students );
        dbDelta( $sql_enrollments );
        dbDelta( $sql_payments );
        dbDelta( $sql_schedule );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }
}
