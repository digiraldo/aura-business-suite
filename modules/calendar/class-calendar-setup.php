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
    const DB_VERSION = '1.7.4';

    /** Clave de opción en wp_options para almacenar la versión instalada */
    const DB_VERSION_OPTION = 'aura_calendar_db_version';

    /**
     * Inicializar hooks de instalación y actualización.
     */
    public static function init(): void {
        self::maybe_add_event_instructors_columns();
        self::maybe_add_area_id_column();
        self::maybe_add_task_library_columns();
        self::maybe_add_task_targeting_columns();
        self::maybe_add_materials_and_leaders_columns();
        self::maybe_add_modules_and_externals_columns();

        if ( self::needs_update() ) {
            self::repair_all_calendar_tables();
        }

        // Hook AJAX para reparación manual bajo demanda desde Ajustes
        add_action( 'wp_ajax_aura_cal_repair_db', [ __CLASS__, 'ajax_repair_db' ] );
    }

    /**
     * Asegura que wp_aura_cal_event_instructors tenga role, teacher_id, instructor_id y notes,
     * sincronizando datos existentes para que los avatares nunca fallen.
     */
    public static function maybe_add_event_instructors_columns(): void {
        global $wpdb;
        $t_inst = $wpdb->prefix . 'aura_cal_event_instructors';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$t_inst}'" );
        if ( $table_exists !== $t_inst ) {
            return;
        }

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$t_inst}`" );
        if ( empty( $columns ) ) {
            return;
        }

        // 1. Asegurar columna teacher_id
        if ( ! in_array( 'teacher_id', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$t_inst}` ADD COLUMN `teacher_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `event_id`" );
        }

        // 2. Asegurar columna instructor_id (retrocompatibilidad)
        if ( ! in_array( 'instructor_id', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$t_inst}` ADD COLUMN `instructor_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `teacher_id`" );
        }

        // 3. Asegurar columna role
        if ( ! in_array( 'role', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$t_inst}` ADD COLUMN `role` VARCHAR(30) NOT NULL DEFAULT 'lead' AFTER `teacher_id`" );
        }

        // 4. Asegurar columna notes
        if ( ! in_array( 'notes', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$t_inst}` ADD COLUMN `notes` VARCHAR(255) DEFAULT NULL AFTER `role`" );
        }

        // 5. Sincronizar teacher_id <-> instructor_id si uno de los dos tiene 0
        $wpdb->query( "UPDATE `{$t_inst}` SET `teacher_id` = `instructor_id` WHERE (`teacher_id` IS NULL OR `teacher_id` = 0) AND `instructor_id` > 0" );
        $wpdb->query( "UPDATE `{$t_inst}` SET `instructor_id` = `teacher_id` WHERE (`instructor_id` IS NULL OR `instructor_id` = 0) AND `teacher_id` > 0" );

        // 6. Asegurar que role tenga un valor por defecto válido
        $wpdb->query( "UPDATE `{$t_inst}` SET `role` = 'lead' WHERE `role` IS NULL OR `role` = ''" );

        // 7. Retirar índice antiguo conflictivo si existe
        $old_idx = $wpdb->get_results( "SHOW INDEX FROM `{$t_inst}` WHERE Key_name = 'event_instructor'" );
        if ( ! empty( $old_idx ) ) {
            $wpdb->query( "ALTER TABLE `{$t_inst}` DROP INDEX `event_instructor`" );
        }
    }

    /**
     * Asegura la columna area_id en wp_aura_cal_programs si no existe.
     */
    public static function maybe_add_area_id_column(): void {
        global $wpdb;
        $t_programs = $wpdb->prefix . 'aura_cal_programs';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$t_programs}'" );
        if ( $table_exists === $t_programs ) {
            $col_area = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_programs}` LIKE 'area_id'" );
            if ( empty( $col_area ) ) {
                $wpdb->query( "ALTER TABLE `{$t_programs}` ADD COLUMN `area_id` BIGINT UNSIGNED DEFAULT NULL AFTER `deleted_at`, ADD KEY `area_id` (`area_id`)" );
            }
        }
    }

    /**
     * Asegura las columnas book_id, submission_type y min_words en wp_aura_cal_tasks para sincronización con Biblioteca.
     */
    public static function maybe_add_task_library_columns(): void {
        global $wpdb;
        $t_tasks = $wpdb->prefix . 'aura_cal_tasks';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$t_tasks}'" );
        if ( $table_exists === $t_tasks ) {
            $col_book = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_tasks}` LIKE 'book_id'" );
            if ( empty( $col_book ) ) {
                $wpdb->query( "ALTER TABLE `{$t_tasks}` ADD COLUMN `book_id` BIGINT UNSIGNED DEFAULT NULL AFTER `event_id`, ADD KEY `book_id` (`book_id`)" );
            }
            $col_type = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_tasks}` LIKE 'submission_type'" );
            if ( empty( $col_type ) ) {
                $wpdb->query( "ALTER TABLE `{$t_tasks}` ADD COLUMN `submission_type` VARCHAR(30) NOT NULL DEFAULT 'text_or_file' AFTER `attachment_urls`" );
            }
            $col_words = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_tasks}` LIKE 'min_words'" );
            if ( empty( $col_words ) ) {
                $wpdb->query( "ALTER TABLE `{$t_tasks}` ADD COLUMN `min_words` INT UNSIGNED DEFAULT 0 AFTER `submission_type`" );
            }
        }
    }

    /**
     * Asegura las columnas target_type, target_student_ids y student_assignments en wp_aura_cal_tasks.
     * Permite asignación grupal, individual y diferenciada (un libro/tema por estudiante).
     */
    public static function maybe_add_task_targeting_columns(): void {
        global $wpdb;
        $t_tasks = $wpdb->prefix . 'aura_cal_tasks';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$t_tasks}'" );
        if ( $table_exists === $t_tasks ) {
            $col_type = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_tasks}` LIKE 'target_type'" );
            if ( empty( $col_type ) ) {
                $wpdb->query( "ALTER TABLE `{$t_tasks}` ADD COLUMN `target_type` VARCHAR(30) NOT NULL DEFAULT 'all' AFTER `min_words`" );
            }

            $col_students = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_tasks}` LIKE 'target_student_ids'" );
            if ( empty( $col_students ) ) {
                $wpdb->query( "ALTER TABLE `{$t_tasks}` ADD COLUMN `target_student_ids` TEXT DEFAULT NULL AFTER `target_type`" );
            }

            $col_assignments = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_tasks}` LIKE 'student_assignments'" );
            if ( empty( $col_assignments ) ) {
                $wpdb->query( "ALTER TABLE `{$t_tasks}` ADD COLUMN `student_assignments` LONGTEXT DEFAULT NULL AFTER `target_student_ids`" );
            }
        }
    }

    /**
     * Asegura las columnas para materiales docentes, de estudiantes y carpeta Google Drive en materias,
     * y estudiantes líderes en eventos.
     */
    public static function maybe_add_materials_and_leaders_columns(): void {
        global $wpdb;
        $t_subjects = $wpdb->prefix . 'aura_cal_subjects';
        $t_events   = $wpdb->prefix . 'aura_cal_events';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_subjects}'" ) === $t_subjects ) {
            $col_tm = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_subjects}` LIKE 'teacher_materials'" );
            if ( empty( $col_tm ) ) {
                $wpdb->query( "ALTER TABLE `{$t_subjects}` ADD COLUMN `teacher_materials` LONGTEXT DEFAULT NULL AFTER `teachers`" );
            }

            $col_sm = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_subjects}` LIKE 'student_materials'" );
            if ( empty( $col_sm ) ) {
                $wpdb->query( "ALTER TABLE `{$t_subjects}` ADD COLUMN `student_materials` LONGTEXT DEFAULT NULL AFTER `teacher_materials`" );
            }

            $col_gdf = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_subjects}` LIKE 'gdrive_folder_id'" );
            if ( empty( $col_gdf ) ) {
                $wpdb->query( "ALTER TABLE `{$t_subjects}` ADD COLUMN `gdrive_folder_id` VARCHAR(255) DEFAULT NULL AFTER `student_materials`" );
            }
        }

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_events}'" ) === $t_events ) {
            $col_sl = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_events}` LIKE 'student_leaders'" );
            if ( empty( $col_sl ) ) {
                $wpdb->query( "ALTER TABLE `{$t_events}` ADD COLUMN `student_leaders` LONGTEXT DEFAULT NULL AFTER `description`" );
            }
        }
    }

    /**
     * Asegura las columnas module_name y module_order en wp_aura_cal_subjects,
     * y las columnas is_external, external_name, external_email, external_phone, external_org
     * en wp_aura_cal_event_instructors para soportar módulos y docentes terceros.
     */
    public static function maybe_add_modules_and_externals_columns(): void {
        global $wpdb;
        $t_subjects = $wpdb->prefix . 'aura_cal_subjects';
        $t_inst     = $wpdb->prefix . 'aura_cal_event_instructors';

        // 1. Columnas en wp_aura_cal_subjects (Módulos y Docentes Terceros / Externos)
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_subjects}'" ) === $t_subjects ) {
            $cols_subj = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$t_subjects}`" );

            if ( ! in_array( 'module_name', $cols_subj, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_subjects}` ADD COLUMN `module_name` VARCHAR(150) DEFAULT NULL AFTER `description`, ADD KEY `module_name` (`module_name`)" );
            }

            if ( ! in_array( 'module_order', $cols_subj, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_subjects}` ADD COLUMN `module_order` INT NOT NULL DEFAULT 1 AFTER `module_name`" );
            }

            if ( ! in_array( 'external_teachers', $cols_subj, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_subjects}` ADD COLUMN `external_teachers` LONGTEXT DEFAULT NULL AFTER `teachers`" );
            }
        }

        // 2. Columnas en wp_aura_cal_programs (Coordinadores Terceros / Externos)
        $t_programs = $wpdb->prefix . 'aura_cal_programs';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_programs}'" ) === $t_programs ) {
            $cols_prog = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$t_programs}`" );

            if ( ! in_array( 'external_coordinators', $cols_prog, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_programs}` ADD COLUMN `external_coordinators` LONGTEXT DEFAULT NULL AFTER `coordinators`" );
            }
        }

        // 3. Columnas de Instructor Tercero / Externo en wp_aura_cal_event_instructors
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_inst}'" ) === $t_inst ) {
            $cols_inst = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$t_inst}`" );

            if ( ! in_array( 'is_external', $cols_inst, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_inst}` ADD COLUMN `is_external` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`" );
            }

            if ( ! in_array( 'external_name', $cols_inst, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_inst}` ADD COLUMN `external_name` VARCHAR(255) DEFAULT NULL AFTER `is_external`" );
            }

            if ( ! in_array( 'external_email', $cols_inst, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_inst}` ADD COLUMN `external_email` VARCHAR(255) DEFAULT NULL AFTER `external_name`" );
            }

            if ( ! in_array( 'external_phone', $cols_inst, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_inst}` ADD COLUMN `external_phone` VARCHAR(50) DEFAULT NULL AFTER `external_email`" );
            }

            if ( ! in_array( 'external_org', $cols_inst, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_inst}` ADD COLUMN `external_org` VARCHAR(255) DEFAULT NULL AFTER `external_phone`" );
            }

            if ( ! in_array( 'third_party_id', $cols_inst, true ) ) {
                $wpdb->query( "ALTER TABLE `{$t_inst}` ADD COLUMN `third_party_id` BIGINT UNSIGNED DEFAULT NULL AFTER `external_org`, ADD KEY `third_party_id` (`third_party_id`)" );
            }
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
  coordinators TEXT DEFAULT NULL,
  external_coordinators LONGTEXT DEFAULT NULL,
  created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  area_id BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY code (code),
  KEY status (status),
  KEY dates (start_date, end_date),
  KEY area_id (area_id),
  KEY deleted_at (deleted_at)
) {$charset_collate};";

        // 2. Tabla de Materias / Asignaturas
        $sql_subjects = "CREATE TABLE {$t_subjects} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  program_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(50) DEFAULT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  module_name VARCHAR(150) DEFAULT NULL,
  module_order INT NOT NULL DEFAULT 1,
  color VARCHAR(20) DEFAULT '#3b82f6',
  default_teacher_id BIGINT UNSIGNED DEFAULT NULL,
  teachers TEXT DEFAULT NULL,
  external_teachers LONGTEXT DEFAULT NULL,
  teacher_materials LONGTEXT DEFAULT NULL,
  student_materials LONGTEXT DEFAULT NULL,
  gdrive_folder_id VARCHAR(255) DEFAULT NULL,
  total_hours DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  order_index INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY program_id (program_id),
  KEY code (code),
  KEY module_name (module_name),
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
  student_leaders LONGTEXT DEFAULT NULL,
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
  teacher_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  role VARCHAR(30) NOT NULL DEFAULT 'lead',
  is_external TINYINT(1) NOT NULL DEFAULT 0,
  external_name VARCHAR(255) DEFAULT NULL,
  external_email VARCHAR(255) DEFAULT NULL,
  external_phone VARCHAR(50) DEFAULT NULL,
  external_org VARCHAR(255) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY event_id (event_id),
  KEY teacher_id (teacher_id),
  KEY is_external (is_external)
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
  book_id BIGINT UNSIGNED DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  due_datetime DATETIME NOT NULL,
  max_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  attachment_urls LONGTEXT DEFAULT NULL,
  submission_type VARCHAR(30) NOT NULL DEFAULT 'text_or_file',
  min_words INT UNSIGNED DEFAULT 0,
  target_type VARCHAR(30) NOT NULL DEFAULT 'all',
  target_student_ids TEXT DEFAULT NULL,
  student_assignments LONGTEXT DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'published',
  created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY program_id (program_id),
  KEY subject_id (subject_id),
  KEY book_id (book_id),
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

        // Asegurar columnas para múltiples coordinadores y profesores si la tabla ya existía
        $col_coord = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_programs}` LIKE 'coordinators'" );
        if ( empty( $col_coord ) ) {
            $wpdb->query( "ALTER TABLE `{$t_programs}` ADD COLUMN `coordinators` TEXT DEFAULT NULL AFTER `coordinator_id`" );
        }

        $col_area = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_programs}` LIKE 'area_id'" );
        if ( empty( $col_area ) ) {
            $wpdb->query( "ALTER TABLE `{$t_programs}` ADD COLUMN `area_id` BIGINT UNSIGNED DEFAULT NULL AFTER `deleted_at`, ADD KEY `area_id` (`area_id`)" );
        }

        $col_teach = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_subjects}` LIKE 'teachers'" );
        if ( empty( $col_teach ) ) {
            $wpdb->query( "ALTER TABLE `{$t_subjects}` ADD COLUMN `teachers` TEXT DEFAULT NULL AFTER `default_teacher_id`" );
        }

        // Retirar índice obsoleto event_instructor en wp_aura_cal_event_instructors para permitir múltiples profesores
        $old_idx = $wpdb->get_results( "SHOW INDEX FROM `{$t_event_instructors}` WHERE Key_name = 'event_instructor'" );
        if ( ! empty( $old_idx ) ) {
            $wpdb->query( "ALTER TABLE `{$t_event_instructors}` DROP INDEX `event_instructor`" );
        }

        self::maybe_add_event_instructors_columns();
        self::maybe_add_task_library_columns();
        self::maybe_add_task_targeting_columns();
        self::maybe_add_materials_and_leaders_columns();
        self::maybe_add_modules_and_externals_columns();

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Reparación y migración integral de todas las tablas de Calendario.
     * Garantiza que todas las columnas y relaciones existan y estén sincronizadas.
     */
    public static function repair_all_calendar_tables(): array {
        global $wpdb;

        // 1. Asegurar instructores de eventos y sincronizar teacher_id / instructor_id / role
        self::maybe_add_event_instructors_columns();

        // 2. Asegurar columnas de programas
        self::maybe_add_area_id_column();
        $t_programs = $wpdb->prefix . 'aura_cal_programs';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_programs}'" ) === $t_programs ) {
            $col_coord = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_programs}` LIKE 'coordinators'" );
            if ( empty( $col_coord ) ) {
                $wpdb->query( "ALTER TABLE `{$t_programs}` ADD COLUMN `coordinators` TEXT DEFAULT NULL AFTER `coordinator_id`" );
            }
        }

        // 3. Asegurar columnas de materias
        $t_subjects = $wpdb->prefix . 'aura_cal_subjects';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_subjects}'" ) === $t_subjects ) {
            $col_def = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_subjects}` LIKE 'default_teacher_id'" );
            if ( empty( $col_def ) ) {
                $wpdb->query( "ALTER TABLE `{$t_subjects}` ADD COLUMN `default_teacher_id` BIGINT UNSIGNED DEFAULT NULL AFTER `color`" );
            }
            $col_t = $wpdb->get_results( "SHOW COLUMNS FROM `{$t_subjects}` LIKE 'teachers'" );
            if ( empty( $col_t ) ) {
                $wpdb->query( "ALTER TABLE `{$t_subjects}` ADD COLUMN `teachers` TEXT DEFAULT NULL AFTER `default_teacher_id`" );
            }
        }
        self::maybe_add_materials_and_leaders_columns();
        self::maybe_add_modules_and_externals_columns();

        // 4. Asegurar columnas de tareas y biblioteca
        self::maybe_add_task_library_columns();
        self::maybe_add_task_targeting_columns();

        // 5. Asegurar columnas de eventos (Google Sync, líderes estudiantiles, recurrencia)
        $t_events = $wpdb->prefix . 'aura_cal_events';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_events}'" ) === $t_events ) {
            $cols_ev = $wpdb->get_col( "SHOW COLUMNS FROM `{$t_events}`" );
            if ( is_array( $cols_ev ) ) {
                if ( ! in_array( 'recurrence_group_id', $cols_ev, true ) ) {
                    $wpdb->query( "ALTER TABLE `{$t_events}` ADD COLUMN `recurrence_group_id` VARCHAR(64) DEFAULT NULL AFTER `status`, ADD KEY `recurrence` (`recurrence_group_id`)" );
                }
                if ( ! in_array( 'recurrence_rule', $cols_ev, true ) ) {
                    $wpdb->query( "ALTER TABLE `{$t_events}` ADD COLUMN `recurrence_rule` TEXT DEFAULT NULL AFTER `recurrence_group_id`" );
                }
                if ( ! in_array( 'gcal_event_id', $cols_ev, true ) ) {
                    $wpdb->query( "ALTER TABLE `{$t_events}` ADD COLUMN `gcal_event_id` VARCHAR(255) DEFAULT NULL AFTER `recurrence_rule`, ADD KEY `gcal_id` (`gcal_event_id`)" );
                }
                if ( ! in_array( 'gcal_sync_status', $cols_ev, true ) ) {
                    $wpdb->query( "ALTER TABLE `{$t_events}` ADD COLUMN `gcal_sync_status` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `gcal_event_id`, ADD KEY `gcal_status` (`gcal_sync_status`)" );
                }
                if ( ! in_array( 'gcal_synced_at', $cols_ev, true ) ) {
                    $wpdb->query( "ALTER TABLE `{$t_events}` ADD COLUMN `gcal_synced_at` DATETIME DEFAULT NULL AFTER `gcal_sync_status`" );
                }
                if ( ! in_array( 'student_leaders', $cols_ev, true ) ) {
                    $wpdb->query( "ALTER TABLE `{$t_events}` ADD COLUMN `student_leaders` LONGTEXT DEFAULT NULL AFTER `description`" );
                }
            }
        }

        // 6. Ejecutar dbDelta completo para sincronizar tipos y llaves
        self::create_tables();

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

        return [
            'success' => true,
            'version' => self::DB_VERSION,
            'message' => sprintf( __( 'Base de datos del Calendario reparada y sincronizada a la versión %s con éxito.', 'aura' ), self::DB_VERSION ),
        ];
    }

    /**
     * AJAX handler para reparación manual de tablas.
     */
    public static function ajax_repair_db(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_manage_calendar' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $res = self::repair_all_calendar_tables();
        wp_send_json_success( $res );
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
