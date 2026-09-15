<?php
/**
 * Instalador y Migraciones de Base de Datos — Módulo de Calendario y Horarios Académicos
 *
 * Crea y actualiza las 8 tablas requeridas para el funcionamiento del módulo:
 * 1. wp_aura_cal_programs           — Programas académicos de capacitación
 * 2. wp_aura_cal_subjects           — Materias y asignaturas por programa
 * 3. wp_aura_cal_events             — Clases, talleres, evaluaciones y eventos
 * 4. wp_aura_cal_event_instructors  — Instructores asignados por evento
 * 5. wp_aura_cal_attendance         — Asistencia por evento y estudiante
 * 6. wp_aura_cal_grades             — Calificaciones y evaluaciones ponderadas
 * 7. wp_aura_cal_tasks              — Tareas y asignaciones
 * 8. wp_aura_cal_task_submissions   — Entregas de tareas por estudiantes
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Setup {

    /** Versión actual del esquema de base de datos del módulo */
    const DB_VERSION = '1.1.0';

    /** Clave de opción en wp_options para almacenar la versión instalada */
    const DB_VERSION_OPTION = 'aura_calendar_db_version';

    /**
     * Inicializar hooks de instalación y actualización.
     */
    public static function init(): void {
        if ( self::needs_update() ) {
            add_action( 'admin_init', [ __CLASS__, 'create_tables' ] );
        }
    }

    /**
     * Comprobar si la versión de base de datos requiere actualización.
     */
    public static function needs_update(): bool {
        $installed = get_option( self::DB_VERSION_OPTION, '0' );
        return version_compare( $installed, self::DB_VERSION, '<' );
    }

    /**
     * Crea o actualiza las 8 tablas requeridas usando dbDelta.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t_programs          = $wpdb->prefix . 'aura_cal_programs';
        $t_subjects          = $wpdb->prefix . 'aura_cal_subjects';
        $t_events            = $wpdb->prefix . 'aura_cal_events';
        $t_event_instructors = $wpdb->prefix . 'aura_cal_event_instructors';
        $t_attendance        = $wpdb->prefix . 'aura_cal_attendance';
        $t_grades            = $wpdb->prefix . 'aura_cal_grades';
        $t_tasks             = $wpdb->prefix . 'aura_cal_tasks';
        $t_submissions       = $wpdb->prefix . 'aura_cal_task_submissions';

        // 1. Tabla de Programas de Capacitación
        $sql_programs = "CREATE TABLE {$t_programs} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  academic_period VARCHAR(100) DEFAULT NULL,
  start_date DATE DEFAULT NULL,
  end_date DATE DEFAULT NULL,
  color VARCHAR(20) DEFAULT '#6366f1',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  coordinator_id BIGINT UNSIGNED DEFAULT NULL,
  created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY code (code),
  KEY status (status),
  KEY dates (start_date, end_date),
  KEY deleted_at (deleted_at)
) {$charset_collate};";

        // 2. Tabla de Materias / Asignaturas
        $sql_subjects = "CREATE TABLE {$t_subjects} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  program_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(50) DEFAULT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  color VARCHAR(20) DEFAULT '#3b82f6',
  default_teacher_id BIGINT UNSIGNED DEFAULT NULL,
  total_hours DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  order_index INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY program_id (program_id),
  KEY code (code),
  KEY status (status),
  KEY deleted_at (deleted_at)
) {$charset_collate};";

        // 3. Tabla de Clases, Eventos y Actividades
        $sql_events = "CREATE TABLE {$t_events} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  program_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  event_type VARCHAR(30) NOT NULL DEFAULT 'class',
  description TEXT DEFAULT NULL,
  location VARCHAR(255) DEFAULT NULL,
  online_url VARCHAR(500) DEFAULT NULL,
  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME NOT NULL,
  color VARCHAR(20) DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
  recurrence_group_id VARCHAR(64) DEFAULT NULL,
  recurrence_rule TEXT DEFAULT NULL,
  gcal_event_id VARCHAR(255) DEFAULT NULL,
  gcal_sync_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  gcal_synced_at DATETIME DEFAULT NULL,
  created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY program_id (program_id),
  KEY subject_id (subject_id),
  KEY start_datetime (start_datetime),
  KEY recurrence (recurrence_group_id),
  KEY gcal_id (gcal_event_id),
  KEY gcal_status (gcal_sync_status),
  KEY deleted_at (deleted_at)
) {$charset_collate};";

        // 4. Tabla de Instructores asignados a eventos
        $sql_instructors = "CREATE TABLE {$t_event_instructors} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NOT NULL,
  teacher_id BIGINT UNSIGNED NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'lead',
  notes VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY event_teacher (event_id, teacher_id),
  KEY teacher_id (teacher_id)
) {$charset_collate};";

        // 5. Tabla de Asistencia
        $sql_attendance = "CREATE TABLE {$t_attendance} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'present',
  notes VARCHAR(255) DEFAULT NULL,
  recorded_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY event_student (event_id, student_id),
  KEY student_id (student_id),
  KEY status (status)
) {$charset_collate};";

        // 6. Tabla de Calificaciones y Notas
        $sql_grades = "CREATE TABLE {$t_grades} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  program_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  eval_title VARCHAR(255) NOT NULL,
  eval_type VARCHAR(30) NOT NULL DEFAULT 'partial',
  weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  max_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  feedback TEXT DEFAULT NULL,
  graded_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  graded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY student_subject (student_id, subject_id),
  KEY program_id (program_id)
) {$charset_collate};";

        // 7. Tabla de Tareas y Asignaciones
        $sql_tasks = "CREATE TABLE {$t_tasks} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  program_id BIGINT UNSIGNED NOT NULL,
  subject_id BIGINT UNSIGNED DEFAULT NULL,
  event_id BIGINT UNSIGNED DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  due_datetime DATETIME NOT NULL,
  max_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  attachment_urls LONGTEXT DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'published',
  created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY program_id (program_id),
  KEY subject_id (subject_id),
  KEY due_date (due_datetime),
  KEY deleted_at (deleted_at)
) {$charset_collate};";

        // 8. Tabla de Entregas de Tareas
        $sql_submissions = "CREATE TABLE {$t_submissions} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  submission_text TEXT DEFAULT NULL,
  attachment_urls LONGTEXT DEFAULT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(20) NOT NULL DEFAULT 'submitted',
  score DECIMAL(5,2) DEFAULT NULL,
  feedback TEXT DEFAULT NULL,
  graded_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  graded_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY task_student (task_id, student_id),
  KEY task_id (task_id),
  KEY student_id (student_id),
  KEY status (status)
) {$charset_collate};";

        dbDelta( $sql_programs );
        dbDelta( $sql_subjects );
        dbDelta( $sql_events );
        dbDelta( $sql_instructors );
        dbDelta( $sql_attendance );
        dbDelta( $sql_grades );
        dbDelta( $sql_tasks );
        dbDelta( $sql_submissions );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Eliminar tablas al desinstalar (opcional).
     */
    public static function drop_tables(): void {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'aura_cal_task_submissions',
            $wpdb->prefix . 'aura_cal_tasks',
            $wpdb->prefix . 'aura_cal_grades',
            $wpdb->prefix . 'aura_cal_attendance',
            $wpdb->prefix . 'aura_cal_event_instructors',
            $wpdb->prefix . 'aura_cal_events',
            $wpdb->prefix . 'aura_cal_subjects',
            $wpdb->prefix . 'aura_cal_programs',
        ];

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }

        delete_option( self::DB_VERSION_OPTION );
    }
}
