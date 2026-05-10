<?php
/**
 * Puente de Integración: Vehículos ↔ Módulo Financial (Fase 10)
 *
 * Se activa automáticamente cuando el módulo Financial está presente.
 * Escucha el hook `aura_vehicles_trip_closed` y crea transacciones en
 * `wp_aura_finance_transactions` según el tipo de salida:
 *
 *  - rental   + total_amount   > 0  →  ingreso
 *  - maintenance + maint_actual_cost > 0  →  egreso
 *  - gastos detallados (expenses_detail)  →  egresos individuales (si opción activa)
 *
 * Prerequisito: el módulo Financial debe estar activo y sus tablas creadas.
 * Si no está activo, este bridge no hace nada.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Financial_Bridge {

    // ── Opciones de configuración ──────────────────────────────────
    const OPT_INCOME_CAT    = 'aura_vehicles_fin_income_category_id';
    const OPT_EXPENSE_CAT   = 'aura_vehicles_fin_expense_category_id';
    const OPT_SYNC_EXPENSES = 'aura_vehicles_fin_sync_trip_expenses';
    const OPT_ENABLED       = 'aura_vehicles_fin_integration_enabled';

    // ── Init ──────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'aura_vehicles_trip_closed', array( __CLASS__, 'on_trip_closed' ), 10, 2 );
    }

    // ── Detectar si el módulo Financial está disponible ───────────

    public static function is_financial_active(): bool {
        global $wpdb;

        if ( ! get_option( self::OPT_ENABLED, false ) ) {
            return false;
        }

        // La tabla de transacciones debe existir
        $table = $wpdb->prefix . 'aura_finance_transactions';
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ) );
    }

    // ── Listener principal ────────────────────────────────────────

    /**
     * Se ejecuta cuando finaliza un check-in exitoso.
     *
     * @param object $trip   Fila original del trip (antes del update).
     * @param array  $update Datos aplicados en el update (tiene total_amount, maint_actual_cost, etc.).
     */
    public static function on_trip_closed( object $trip, array $update ): void {
        if ( ! self::is_financial_active() ) {
            return;
        }

        $trip_type    = $trip->trip_type;
        $total_amount = (float) ( $update['total_amount'] ?? 0 );
        $maint_cost   = (float) ( $update['maint_actual_cost'] ?? 0 );
        $trip_date    = substr( $update['return_datetime'] ?? $trip->departure_datetime ?? current_time( 'mysql' ), 0, 10 );
        $trip_id      = (int) $trip->id;
        $vehicle_id   = (int) $trip->vehicle_id;

        // 1. Rental con importe positivo → ingreso
        if ( 'rental' === $trip_type && $total_amount > 0 ) {
            $cat_id = (int) get_option( self::OPT_INCOME_CAT, 0 );
            if ( $cat_id > 0 ) {
                self::insert_transaction(
                    'income',
                    $cat_id,
                    $total_amount,
                    $trip_date,
                    sprintf(
                        /* translators: %1$d: ID salida, %2$d: ID vehículo */
                        __( 'Ingreso por rental — Salida #%1$d (Vehículo #%2$d)', 'aura-suite' ),
                        $trip_id,
                        $vehicle_id
                    ),
                    'vehicles',
                    $trip_id,
                    'rental_closed'
                );
            }
        }

        // 2. Maintenance con costo real positivo → egreso
        if ( 'maintenance' === $trip_type && $maint_cost > 0 ) {
            $cat_id = (int) get_option( self::OPT_EXPENSE_CAT, 0 );
            if ( $cat_id > 0 ) {
                self::insert_transaction(
                    'expense',
                    $cat_id,
                    $maint_cost,
                    $trip_date,
                    sprintf(
                        /* translators: %1$d: ID salida, %2$d: ID vehículo */
                        __( 'Egreso por mantenimiento — Salida #%1$d (Vehículo #%2$d)', 'aura-suite' ),
                        $trip_id,
                        $vehicle_id
                    ),
                    'vehicles',
                    $trip_id,
                    'maintenance_closed'
                );
            }
        }

        // 3. Gastos detallados del trip → egresos individuales (si opción activa)
        if ( get_option( self::OPT_SYNC_EXPENSES, false ) ) {
            $expenses_raw = $update['expenses_detail'] ?? null;
            $expenses     = array();

            if ( is_string( $expenses_raw ) ) {
                $decoded = json_decode( $expenses_raw, true );
                if ( is_array( $decoded ) ) {
                    $expenses = $decoded;
                }
            } elseif ( is_array( $expenses_raw ) ) {
                $expenses = $expenses_raw;
            }

            $cat_id = (int) get_option( self::OPT_EXPENSE_CAT, 0 );
            if ( $cat_id > 0 && ! empty( $expenses ) ) {
                foreach ( $expenses as $idx => $expense ) {
                    $amt  = (float) ( $expense['amount'] ?? 0 );
                    $desc = sanitize_text_field( $expense['concept'] ?? $expense['type'] ?? sprintf( __( 'Gasto #%d', 'aura-suite' ), $idx + 1 ) );

                    if ( $amt <= 0 ) {
                        continue;
                    }

                    self::insert_transaction(
                        'expense',
                        $cat_id,
                        $amt,
                        $trip_date,
                        sprintf(
                            /* translators: %1$s: concepto, %2$d: ID salida */
                            __( '%1$s — Salida #%2$d', 'aura-suite' ),
                            $desc,
                            $trip_id
                        ),
                        'vehicles',
                        $trip_id,
                        'trip_expense'
                    );
                }
            }
        }
    }

    // ── Insertar transacción directamente en la tabla ─────────────

    /**
     * Inserta una fila en wp_aura_finance_transactions.
     *
     * No llama al AJAX handler del módulo Financial para evitar depender
     * del sistema de aprobación y permisos de usuario actuales durante el
     * cierre de una salida (puede ejecutarse desde cron o REST API).
     *
     * La transacción se crea en estado 'pending' para que siga el flujo
     * normal de aprobación del módulo Financial.
     *
     * @param string $type            'income' | 'expense'
     * @param int    $category_id     ID de categoría en wp_aura_finance_categories
     * @param float  $amount          Importe positivo
     * @param string $date            Fecha en formato Y-m-d
     * @param string $description     Descripción legible
     * @param string $related_module  Módulo de origen (ej. 'vehicles')
     * @param int    $related_item_id ID del ítem relacionado (trip_id)
     * @param string $related_action  Contexto de la acción
     */
    private static function insert_transaction(
        string $type,
        int    $category_id,
        float  $amount,
        string $date,
        string $description,
        string $related_module  = 'vehicles',
        int    $related_item_id = 0,
        string $related_action  = ''
    ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_finance_transactions';

        // Verificar que la categoría existe y está activa antes de insertar
        $cat = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aura_finance_categories WHERE id = %d AND is_active = 1 LIMIT 1",
            $category_id
        ) );

        if ( ! $cat ) {
            // Categoría inválida: registrar en auditoría y salir silenciosamente
            if ( class_exists( 'Aura_Vehicle_Audit_Manager' ) ) {
                Aura_Vehicle_Audit_Manager::log(
                    0,
                    'financial_sync_error',
                    sprintf(
                        __( 'Integración financiera: categoría ID=%d no encontrada o inactiva. No se creó la transacción.', 'aura-suite' ),
                        $category_id
                    )
                );
            }
            return;
        }

        $data = array(
            'transaction_type'    => $type,
            'category_id'         => $category_id,
            'expense_category_id' => $category_id,
            'amount'              => $amount,
            'transaction_date'    => $date,
            'description'         => $description,
            'status'              => 'pending',
            'related_module'      => $related_module,
            'related_item_id'     => $related_item_id > 0 ? $related_item_id : null,
            'related_action'      => $related_action ?: null,
            'created_by'          => get_current_user_id(),
            'created_at'          => current_time( 'mysql' ),
            'updated_at'          => current_time( 'mysql' ),
        );

        $formats = array(
            '%s', '%d', '%d', '%f', '%s', '%s', '%s',
            '%s', '%d', '%s', '%d', '%s',
        );

        $inserted = $wpdb->insert( $table, $data, $formats );

        if ( $inserted && class_exists( 'Aura_Vehicle_Audit_Manager' ) ) {
            Aura_Vehicle_Audit_Manager::log(
                0,
                'financial_sync',
                sprintf(
                    /* translators: %1$s: tipo, %2$.2f: monto, %3$d: trip ID */
                    __( 'Integración financiera: transacción de %1$s por %.2f creada (trip #%3$d).', 'aura-suite' ),
                    $type,
                    $amount,
                    $related_item_id
                )
            );
        }
    }

    // ── API pública: listar categorías financieras ────────────────

    /**
     * Devuelve las categorías Financial activas para el tipo dado.
     * Usada por la UI de settings para cargar el select de categorías.
     *
     * @param  string $type 'income' | 'expense' | 'both'
     * @return array<int, string>  [ id => name ]
     */
    public static function get_categories( string $type = '' ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_finance_categories';

        // Verificar que la tabla existe
        if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) ) {
            return array();
        }

        if ( $type ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, name FROM {$table} WHERE is_active = 1 AND (type = %s OR type = 'both') ORDER BY name ASC",
                $type
            ) );
        } else {
            $rows = $wpdb->get_results(
                "SELECT id, name FROM {$table} WHERE is_active = 1 ORDER BY name ASC"
            );
        }

        $result = array();
        foreach ( $rows as $row ) {
            $result[ (int) $row->id ] = $row->name;
        }
        return $result;
    }
}
