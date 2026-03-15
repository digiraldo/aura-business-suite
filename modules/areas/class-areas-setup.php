<?php
/**
 * Áreas y Programas — Fase 7, Ítem 7.1
 *
 * Crea la tabla `wp_aura_areas`, agrega columna `area_id` a las tablas de
 * presupuestos y transacciones, e inserta los programas predefinidos del instituto.
 *
 * @package AuraBusinessSuite
 * @subpackage Areas
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Areas_Setup {

    /** Clave de opción para el guard de migración */
    const MIGRATION_KEY = 'aura_areas_db_v1';

    /** Clave para la actualización de paleta y programas v2 */
    const PROGRAMS_KEY  = 'aura_areas_programs_v2';
    
    /** Clave para la tabla de relaciones área-usuarios */
    const AREA_USERS_TABLE_KEY = 'aura_areas_users_table_v1';

    /** Clave para la restricción UNIQUE en tabla de presupuestos */
    const BUDGETS_UNIQUE_KEY = 'aura_budgets_unique_v1';

    /** Nombre base (sin prefijo) de la tabla de áreas */
    const TABLE = 'aura_areas';

    /* ======================================================================
     * INIT
     * ==================================================================== */

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'maybe_migrate' ] );
        // Ejecutar actualización de paleta/programas de forma independiente
        // (cubre instalaciones anteriores donde la migración v1 ya se ejecutó)
        add_action( 'admin_init', [ __CLASS__, 'run_program_updates' ] );
        // Crear tabla de usuarios si no existe (migración v1.1.0)
        add_action( 'admin_init', [ __CLASS__, 'maybe_create_area_users_table' ] );
        // Agregar restricción UNIQUE en tabla de presupuestos (migración v1.2.0)
        add_action( 'admin_init', [ __CLASS__, 'maybe_add_budgets_unique_key' ] );
    }

    /**
     * Punto de entrada público para la migración de programas v2,
     * seguro de llamar en admin_init (tiene guard interno).
     */
    public static function run_program_updates(): void {
        global $wpdb;
        self::maybe_update_programs( $wpdb );
    }
    
    /**
     * Crea la tabla de relaciones área-usuarios si no existe (v1.1.0)
     * Seguro de llamar en admin_init (tiene guard interno).
     */
    public static function maybe_create_area_users_table(): void {
        if ( get_option( self::AREA_USERS_TABLE_KEY ) ) {
            return;
        }
        
        global $wpdb;
        self::create_area_users_table( $wpdb );
        
        // Migrar responsables existentes a la tabla de relaciones
        $areas_table = $wpdb->prefix . self::TABLE;
        $users_table = $wpdb->prefix . 'aura_area_users';
        
        $areas_with_responsible = $wpdb->get_results(
            "SELECT id, responsible_user_id 
             FROM {$areas_table} 
             WHERE responsible_user_id IS NOT NULL AND responsible_user_id > 0"
        );
        
        foreach ( $areas_with_responsible as $area ) {
            // Solo insertar si no existe ya la relación
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$users_table} WHERE area_id = %d AND user_id = %d",
                $area->id,
                $area->responsible_user_id
            ) );
            
            if ( ! $exists ) {
                $wpdb->insert(
                    $users_table,
                    [
                        'area_id'     => $area->id,
                        'user_id'     => $area->responsible_user_id,
                        'role'        => 'responsible',
                        'assigned_by' => 0, // Sistema
                    ],
                    [ '%d', '%d', '%s', '%d' ]
                );
            }
        }
        
        update_option( self::AREA_USERS_TABLE_KEY, true, false );
        
        // Agregar aviso de éxito en admin (solo si es admin)
        if ( current_user_can( 'manage_options' ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>✅ Aura Business Suite:</strong> Tabla de relaciones área-usuarios creada exitosamente. Sistema multi-usuario activado.</p>';
                echo '</div>';
            } );
        }
    }

    /* ======================================================================
     * UNIQUE KEY EN TABLA DE PRESUPUESTOS (migración v1.2.0)
     * ==================================================================== */

    /**
     * Agrega la restricción UNIQUE (area_id, category_id, start_date, end_date)
     * a la tabla de presupuestos para evitar duplicados en base de datos.
     * Idempotente: se ejecuta solo una vez gracias al guard en wp_options.
     */
    public static function maybe_add_budgets_unique_key(): void {
        if ( get_option( self::BUDGETS_UNIQUE_KEY ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_budgets';

        // Solo ejecutar si la tabla ya existe
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return;
        }

        // Verificar si el índice ya existe para no ejecutar ALTER redundante
        $index_exists = $wpdb->get_var(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = '{$table}'
               AND INDEX_NAME   = 'idx_budget_unique'"
        );

        if ( ! $index_exists ) {
            // MySQL permite múltiples NULLs en UNIQUE KEY, no hay problema con area_id/category_id NULL
            $wpdb->query(
                "ALTER TABLE `{$table}`
                 ADD UNIQUE KEY `idx_budget_unique` (`area_id`, `category_id`, `start_date`, `end_date`)"
            );
        }

        update_option( self::BUDGETS_UNIQUE_KEY, true, false );
    }

    /* ======================================================================
     * MIGRACIÓN PRINCIPAL
     * ==================================================================== */

    /**
     * Ejecuta las migraciones solo una vez (guard con wp_options).
     */
    public static function maybe_migrate(): void {
        if ( get_option( self::MIGRATION_KEY ) ) {
            return;
        }

        global $wpdb;

        self::create_areas_table( $wpdb );
        self::create_area_users_table( $wpdb );  // Tabla para relaciones many-to-many
        self::add_area_id_to_budgets( $wpdb );
        self::add_area_id_to_transactions( $wpdb );
        self::insert_default_programs( $wpdb );

        update_option( self::MIGRATION_KEY, true, false );

        // Marcar la paleta v2 como aplicada (ya se inserta con los colores correctos)
        update_option( self::PROGRAMS_KEY, true, false );
    }

    /**
     * Actualiza colores, nombres e inserta programas nuevos si la tabla ya existía
     * con los valores anteriores (migración incremental independiente).
     */
    private static function maybe_update_programs( \wpdb $wpdb ): void {
        if ( get_option( self::PROGRAMS_KEY ) ) {
            return;
        }

        $table = $wpdb->prefix . self::TABLE;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return;
        }

        // Paleta institucional definitiva: slug => [name, color, icon]
        $updates = self::default_programs();

        // Renombrar slugs legacy antes del loop principal
        $legacy_renames = [
            'cem-voluntarios' => 'hadime-voluntarios',
        ];
        foreach ( $legacy_renames as $old_slug => $new_slug ) {
            $old_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE slug = %s", $old_slug
            ) );
            $new_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE slug = %s", $new_slug
            ) );
            // Solo renombrar si el slug viejo existe y el nuevo todavía no
            if ( $old_exists && ! $new_exists ) {
                $wpdb->update(
                    $table,
                    [ 'slug' => $new_slug ],
                    [ 'slug' => $old_slug ],
                    [ '%s' ],
                    [ '%s' ]
                );
            }
        }

        foreach ( $updates as $program ) {
            // Actualizar si el slug ya existe
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM `{$table}` WHERE slug = %s",
                $program['slug']
            ) );

            if ( $exists ) {
                $wpdb->update(
                    $table,
                    [
                        'name'  => $program['name'],
                        'color' => $program['color'],
                        'icon'  => $program['icon'],
                    ],
                    [ 'slug' => $program['slug'] ],
                    [ '%s', '%s', '%s' ],
                    [ '%s' ]
                );
            } else {
                // Insertar si es un programa nuevo
                $now = current_time( 'mysql' );
                $wpdb->insert(
                    $table,
                    [
                        'name'        => $program['name'],
                        'slug'        => $program['slug'],
                        'type'        => $program['type'],
                        'description' => $program['description'],
                        'color'       => $program['color'],
                        'icon'        => $program['icon'],
                        'sort_order'  => $program['sort_order'],
                        'status'      => 'active',
                        'created_by'  => (int) ( get_current_user_id() ?: 1 ),
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ],
                    [ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' ]
                );
            }
        }

        update_option( self::PROGRAMS_KEY, true, false );
    }

    /* ======================================================================
     * 1. Crear tabla wp_aura_areas
     * ==================================================================== */

    private static function create_areas_table( \wpdb $wpdb ): void {
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            id                  BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            name                VARCHAR(100)     NOT NULL,
            slug                VARCHAR(100)     NOT NULL,
            type                ENUM('program','department','team') NOT NULL DEFAULT 'program',
            description         TEXT             NULL,
            responsible_user_id BIGINT UNSIGNED  NULL COMMENT 'Responsable principal (legacy)',
            parent_area_id      BIGINT UNSIGNED  NULL,
            color               VARCHAR(7)       NOT NULL DEFAULT '#2271b1',
            icon                VARCHAR(50)      NOT NULL DEFAULT 'dashicons-groups',
            status              ENUM('active','archived') NOT NULL DEFAULT 'active',
            sort_order          INT UNSIGNED     NOT NULL DEFAULT 0,
            created_by          BIGINT UNSIGNED  NOT NULL DEFAULT 0,
            created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_slug (slug),
            INDEX idx_status      (status),
            INDEX idx_responsible (responsible_user_id),
            INDEX idx_parent      (parent_area_id),
            INDEX idx_type        (type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        // Crear tabla de relación muchos a muchos área-usuarios
        self::create_area_users_table( $wpdb );
    }
    
    /* ======================================================================
     * 1.1. Crear tabla wp_aura_area_users (relación muchos a muchos)
     * ==================================================================== */

    private static function create_area_users_table( \wpdb $wpdb ): void {
        $table   = $wpdb->prefix . 'aura_area_users';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            id              BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            area_id         BIGINT UNSIGNED  NOT NULL,
            user_id         BIGINT UNSIGNED  NOT NULL,
            role            VARCHAR(50)      NOT NULL DEFAULT 'responsible' COMMENT 'responsible, coordinator, viewer',
            assigned_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            assigned_by     BIGINT UNSIGNED  NOT NULL DEFAULT 0,
            UNIQUE KEY uq_area_user (area_id, user_id),
            INDEX idx_area (area_id),
            INDEX idx_user (user_id),
            INDEX idx_role (role)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* ======================================================================
     * 2. Agregar area_id a aura_finance_budgets
     * ==================================================================== */

    private static function add_area_id_to_budgets( \wpdb $wpdb ): void {
        $table = $wpdb->prefix . 'aura_finance_budgets';

        // Verificar que la tabla exista antes de tocarla
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return;
        }

        // Verificar si la columna ya existe
        $exists = $wpdb->get_results(
            "SHOW COLUMNS FROM `{$table}` LIKE 'area_id'"
        );

        if ( empty( $exists ) ) {
            $wpdb->query(
                "ALTER TABLE `{$table}`
                 ADD COLUMN `area_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `category_id`,
                 ADD INDEX `idx_area` (`area_id`)"
            );
        }
    }

    /* ======================================================================
     * 3. Agregar area_id a aura_finance_transactions
     * ==================================================================== */

    private static function add_area_id_to_transactions( \wpdb $wpdb ): void {
        $table = $wpdb->prefix . 'aura_finance_transactions';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return;
        }

        $exists = $wpdb->get_results(
            "SHOW COLUMNS FROM `{$table}` LIKE 'area_id'"
        );

        if ( empty( $exists ) ) {
            $wpdb->query(
                "ALTER TABLE `{$table}`
                 ADD COLUMN `area_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `related_user_concept`,
                 ADD INDEX `idx_area` (`area_id`)"
            );
        }
    }

    /* ======================================================================
     * 4. Insertar programas predefinidos
     * ==================================================================== */

    /**
     * Paleta institucional definitiva de programas.
     * Esta es la fuente de verdad única para la inserción inicial y la migración v2.
     */
    private static function default_programs(): array {
        return [
            [
                'name'        => 'Hadime Junior',
                'slug'        => 'hadime-junior',
                'type'        => 'program',
                'description' => 'Programa Hadime dirigido a jóvenes.',
                'color'       => '#ede522',   // Amarillo institucional
                'icon'        => 'dashicons-groups',
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Hadime Más',
                'slug'        => 'hadime-mas',
                'type'        => 'program',
                'description' => 'Programa Hadime de profundización.',
                'color'       => '#004526',   // Verde institucional
                'icon'        => 'dashicons-groups',
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Hadime Raíces',
                'slug'        => 'hadime-raices',
                'type'        => 'program',
                'description' => 'Programa Hadime de fundamentos y raíces.',
                'color'       => '#720427',   // Borgoña institucional
                'icon'        => 'dashicons-admin-site',
                'sort_order'  => 3,
            ],
            [
                'name'        => 'Hadime Líderes',
                'slug'        => 'hadime-lideres',
                'type'        => 'program',
                'description' => 'Programa de formación de líderes.',
                'color'       => '#5B2C6F',   // Morado institucional
                'icon'        => 'dashicons-businessman',
                'sort_order'  => 4,
            ],
            [
                'name'        => 'Hadime Misioneros',
                'slug'        => 'hadime-misioneros',
                'type'        => 'program',
                'description' => 'Programa de formación misionera.',
                'color'       => '#102e54',   // Azul institucional
                'icon'        => 'dashicons-location-alt',
                'sort_order'  => 5,
            ],
            [
                'name'        => 'Hadime Voluntarios',
                'slug'        => 'hadime-voluntarios',
                'type'        => 'program',
                'description' => 'Programa de voluntariado.',
                'color'       => '#E67E22',   // Naranja institucional
                'icon'        => 'dashicons-heart',
                'sort_order'  => 6,
            ],
            [
                'name'        => 'Hadime Rentas',
                'slug'        => 'hadime-rentas',
                'type'        => 'program',
                'description' => 'Gestión de rentas e ingresos institucionales.',
                'color'       => '#4D5656',   // Gris Pizarra institucional
                'icon'        => 'dashicons-money-alt',
                'sort_order'  => 7,
            ],
            [
                'name'        => 'Hadime Nuevo Programa',
                'slug'        => 'hadime-nuevo-programa',
                'type'        => 'program',
                'description' => 'Programa por definir.',
                'color'       => '#008080',   // Cerceta / Turquesa institucional
                'icon'        => 'dashicons-plus-alt',
                'sort_order'  => 8,
            ],
        ];
    }

    private static function insert_default_programs( \wpdb $wpdb ): void {
        $table = $wpdb->prefix . self::TABLE;

        // Solo si la tabla está vacía
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
        if ( $count > 0 ) {
            return;
        }

        $programs   = self::default_programs();
        $now        = current_time( 'mysql' );
        $created_by = (int) ( get_current_user_id() ?: 1 );

        foreach ( $programs as $program ) {
            $wpdb->insert(
                $table,
                [
                    'name'        => $program['name'],
                    'slug'        => $program['slug'],
                    'type'        => $program['type'],
                    'description' => $program['description'],
                    'color'       => $program['color'],
                    'icon'        => $program['icon'],
                    'sort_order'  => $program['sort_order'],
                    'status'      => 'active',
                    'created_by'  => $created_by,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' ]
            );
        }
    }

    /* ======================================================================
     * API PÚBLICA — helpers para otras clases
     * ==================================================================== */

    /**
     * Obtiene todas las áreas activas ordenadas.
     *
     * @return array<object>
     */
    public static function get_active_areas(): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return (array) $wpdb->get_results(
            "SELECT id, name, slug, type, color, icon, responsible_user_id, parent_area_id
               FROM `{$table}`
              WHERE status = 'active'
              ORDER BY sort_order ASC, name ASC"
        );
    }

    /**
     * Obtiene un área por su ID.
     *
     * @param int $area_id
     * @return object|null
     */
    public static function get_area( int $area_id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE id = %d",
            $area_id
        ) );
    }

    /**
     * Devuelve el área donde el usuario dado es responsable (primera encontrada).
     *
     * @param int $user_id
     * @return object|null
     */
    public static function get_area_for_user( int $user_id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$table}`
              WHERE responsible_user_id = %d
                AND status = 'active'
              ORDER BY sort_order ASC
              LIMIT 1",
            $user_id
        ) );
    }
    
    /* ======================================================================
     * FUNCIONES PARA GESTIÓN DE MÚLTIPLES RESPONSABLES (ÁREA-USUARIOS)
     * ==================================================================== */
    
    /**
     * Asigna múltiples usuarios a un área
     *
     * @param int   $area_id     ID del área
     * @param array $user_ids    Array de IDs de usuarios
     * @param string $role       Rol (responsible, coordinator, viewer)
     * @return bool
     */
    public static function assign_users_to_area( int $area_id, array $user_ids, string $role = 'responsible' ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_area_users';
        $current_user_id = get_current_user_id();
        
        // Primero eliminar asignaciones existentes para este área
        $wpdb->delete( $table, [ 'area_id' => $area_id ], [ '%d' ] );
        
        // Insertar nuevas asignaciones
        foreach ( $user_ids as $user_id ) {
            $wpdb->insert(
                $table,
                [
                    'area_id'     => $area_id,
                    'user_id'     => absint( $user_id ),
                    'role'        => sanitize_text_field( $role ),
                    'assigned_by' => $current_user_id,
                ],
                [ '%d', '%d', '%s', '%d' ]
            );
        }
        
        // Actualizar responsible_user_id con el primer usuario (compatibilidad)
        if ( ! empty( $user_ids ) ) {
            $wpdb->update(
                $wpdb->prefix . self::TABLE,
                [ 'responsible_user_id' => absint( $user_ids[0] ) ],
                [ 'id' => $area_id ],
                [ '%d' ],
                [ '%d' ]
            );
        }
        
        return true;
    }
    
    /**
     * Obtiene todos los usuarios asignados a un área
     *
     * @param int $area_id ID del área
     * @return array Array de objetos con user_id, role, display_name, avatar_url
     */
    public static function get_area_users( int $area_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_area_users';
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT au.user_id, au.role, au.assigned_at, u.display_name, u.user_email
             FROM {$table} au
             INNER JOIN {$wpdb->users} u ON u.ID = au.user_id
             WHERE au.area_id = %d
             ORDER BY au.assigned_at ASC",
            $area_id
        ) );
        
        $users = [];
        foreach ( $results as $row ) {
            $users[] = [
                'user_id'      => (int) $row->user_id,
                'display_name' => $row->display_name,
                'user_email'   => $row->user_email,
                'role'         => $row->role,
                'assigned_at'  => $row->assigned_at,
                'avatar_url'   => get_avatar_url( $row->user_id, [ 'size' => 32 ] ),
            ];
        }
        
        return $users;
    }
    
    /**
     * Obtiene todas las áreas asignadas a un usuario
     *
     * @param int $user_id ID del usuario
     * @return array Array de objetos de áreas
     */
    public static function get_user_areas( int $user_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_area_users';
        $areas_table = $wpdb->prefix . self::TABLE;
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*, au.role
             FROM {$areas_table} a
             INNER JOIN {$table} au ON au.area_id = a.id
             WHERE au.user_id = %d AND a.status = 'active'
             ORDER BY a.sort_order ASC, a.name ASC",
            $user_id
        ) );
        
        return (array) $results;
    }
    
    /**
     * Verifica si un usuario está asignado a un área
     *
     * @param int $area_id ID del área
     * @param int $user_id ID del usuario
     * @return bool
     */
    public static function is_user_in_area( int $area_id, int $user_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_area_users';
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE area_id = %d AND user_id = %d",
            $area_id,
            $user_id
        ) );
        
        return (int) $count > 0;
    }
    
    /**
     * Obtiene todas las áreas activas
     *
     * @return array Array de objetos de áreas ordenadas por nombre
     */
    public static function get_all_areas(): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$table} 
             WHERE status = 'active' 
             ORDER BY sort_order ASC, name ASC"
        );
        
        return (array) $results;
    }

    /**
     * Fuerza la re-ejecución de la migración (para debug/reinstalación).
     */
    public static function force_remigrate(): void {
        delete_option( self::MIGRATION_KEY );
        self::maybe_migrate();
    }
}
