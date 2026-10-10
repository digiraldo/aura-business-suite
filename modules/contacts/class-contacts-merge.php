<?php
/**
 * Motor de Detección de Duplicados y Fusión (Merge) de Contactos en Aura Suite.
 *
 * Permite identificar coincidencias inteligentes por Correo Electrónico,
 * Teléfono normalizado o similitud de Nombres, unificando registros
 * sin perder historial, notas, etiquetas ni vinculaciones de sistema.
 *
 * @package    Aura_Business_Suite
 * @subpackage Contacts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Contacts_Merge {

    /**
     * Inicializa los hooks y endpoints AJAX del motor de fusión.
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_contacts_find_duplicates', array( __CLASS__, 'ajax_find_duplicates' ) );
        add_action( 'wp_ajax_aura_contacts_merge', array( __CLASS__, 'ajax_merge' ) );
    }

    /**
     * Analiza el directorio de contactos y encuentra grupos (clusters) de posibles duplicados.
     *
     * @return array
     */
    public static function find_duplicates(): array {
        global $wpdb;
        $t_contacts = $wpdb->prefix . 'aura_contacts';

        $contacts = $wpdb->get_results( "SELECT * FROM {$t_contacts} WHERE is_active = 1 ORDER BY id ASC", ARRAY_A );
        if ( empty( $contacts ) || count( $contacts ) < 2 ) {
            return array();
        }

        $clusters = array();
        $seen_pairs = array();

        // 1. Indexar por correo electrónico (primario y secundario)
        $by_email = array();
        foreach ( $contacts as $c ) {
            $e1 = strtolower( trim( (string) $c['email_primary'] ) );
            $e2 = strtolower( trim( (string) $c['email_secondary'] ) );
            if ( ! empty( $e1 ) ) {
                $by_email[ $e1 ][] = $c;
            }
            if ( ! empty( $e2 ) && $e2 !== $e1 ) {
                $by_email[ $e2 ][] = $c;
            }
        }

        foreach ( $by_email as $email => $group ) {
            if ( count( $group ) > 1 ) {
                $ids = wp_list_pluck( $group, 'id' );
                sort( $ids );
                $key = 'email_' . implode( '_', $ids );
                if ( ! isset( $clusters[ $key ] ) ) {
                    $clusters[ $key ] = array(
                        'type'        => 'email',
                        'type_label'  => __( 'Correo Coincidente', 'aura-suite' ),
                        'match_value' => $email,
                        'confidence'  => 100,
                        'badge_color' => '#10b981',
                        'reasons'     => array(
                            array(
                                'type'  => 'email',
                                'label' => __( 'Correo Coincidente', 'aura-suite' ),
                                'value' => $email,
                            ),
                        ),
                        'contacts'    => array_values( array_map( array( 'Aura_Contacts', 'format_contact_row' ), $group ) ),
                    );
                }
            }
        }

        // 2. Indexar por teléfono normalizado (últimos 10 dígitos)
        $by_phone = array();
        foreach ( $contacts as $c ) {
            $p1 = self::normalize_phone( (string) $c['phone_primary'] );
            $p2 = self::normalize_phone( (string) $c['phone_secondary'] );
            $p3 = self::normalize_phone( (string) $c['whatsapp'] );

            if ( strlen( $p1 ) >= 8 ) {
                $by_phone[ $p1 ][] = $c;
            }
            if ( strlen( $p2 ) >= 8 && $p2 !== $p1 ) {
                $by_phone[ $p2 ][] = $c;
            }
            if ( strlen( $p3 ) >= 8 && $p3 !== $p1 && $p3 !== $p2 ) {
                $by_phone[ $p3 ][] = $c;
            }
        }

        foreach ( $by_phone as $phone => $group ) {
            if ( count( $group ) > 1 ) {
                $ids = wp_list_pluck( $group, 'id' );
                sort( $ids );
                $key = 'phone_' . implode( '_', $ids );
                if ( ! isset( $clusters[ $key ] ) ) {
                    $clusters[ $key ] = array(
                        'type'        => 'phone',
                        'type_label'  => __( 'Teléfono Coincidente', 'aura-suite' ),
                        'match_value' => $phone,
                        'confidence'  => 95,
                        'badge_color' => '#3b82f6',
                        'reasons'     => array(
                            array(
                                'type'  => 'phone',
                                'label' => __( 'Teléfono Coincidente', 'aura-suite' ),
                                'value' => $phone,
                            ),
                        ),
                        'contacts'    => array_values( array_map( array( 'Aura_Contacts', 'format_contact_row' ), $group ) ),
                    );
                }
            }
        }

        // 3. Comparación de Nombres y Organizaciones (Exacto y Fonético / Similitud)
        $count = count( $contacts );
        for ( $i = 0; $i < $count; $i++ ) {
            $c1    = $contacts[ $i ];
            $name1 = strtolower( trim( $c1['first_name'] . ' ' . $c1['last_name'] ) );
            if ( empty( $name1 ) ) {
                $name1 = strtolower( trim( (string) $c1['organization'] ) );
            }
            if ( strlen( $name1 ) < 3 ) {
                continue;
            }

            for ( $j = $i + 1; $j < $count; $j++ ) {
                $c2    = $contacts[ $j ];
                $name2 = strtolower( trim( $c2['first_name'] . ' ' . $c2['last_name'] ) );
                if ( empty( $name2 ) ) {
                    $name2 = strtolower( trim( (string) $c2['organization'] ) );
                }
                if ( strlen( $name2 ) < 3 ) {
                    continue;
                }

                // Si ya están en el mismo cluster de email o teléfono, ignorar
                $pair_key = min( $c1['id'], $c2['id'] ) . '_' . max( $c1['id'], $c2['id'] );
                if ( isset( $seen_pairs[ $pair_key ] ) ) {
                    continue;
                }

                $sim = 0;
                similar_text( $name1, $name2, $percent );
                $percent = (float) $percent;

                if ( $name1 === $name2 ) {
                    $sim = 90;
                } elseif ( $percent >= 85 ) {
                    $sim = round( $percent );
                }

                if ( $sim >= 85 ) {
                    $seen_pairs[ $pair_key ] = true;
                    $key = 'name_' . $pair_key;
                    $clusters[ $key ] = array(
                        'type'        => 'name',
                        'type_label'  => ( 90 <= $sim ) ? __( 'Nombre Idéntico', 'aura-suite' ) : __( 'Nombre Muy Similar', 'aura-suite' ),
                        'match_value' => $name1,
                        'confidence'  => $sim,
                        'badge_color' => '#8b5cf6',
                        'reasons'     => array(
                            array(
                                'type'  => 'name',
                                'label' => ( 90 <= $sim ) ? __( 'Nombre Idéntico', 'aura-suite' ) : __( 'Nombre Similar', 'aura-suite' ),
                                'value' => $name1 . ' ↔ ' . $name2,
                            ),
                        ),
                        'contacts'    => array_values( array_map( array( 'Aura_Contacts', 'format_contact_row' ), array( $c1, $c2 ) ) ),
                    );
                }
            }
        }

        return array_values( $clusters );
    }

    /**
     * Normaliza un número telefónico extrayendo solo los últimos 10 dígitos.
     *
     * @param string $phone
     * @return string
     */
    private static function normalize_phone( string $phone ): string {
        $digits = preg_replace( '/\D+/', '', $phone );
        if ( strlen( $digits ) > 10 ) {
            return substr( $digits, -10 );
        }
        return $digits;
    }

    /**
     * Ejecuta la fusión atómica de uno o varios contactos de origen hacia un contacto de destino.
     *
     * @param int   $target_id        ID del contacto maestro resultante.
     * @param array $source_ids       IDs de los contactos secundarios a fusionar y desactivar.
     * @param array $field_overrides  Sobrescribir campos específicos si se definieron en el modal.
     * @return array|WP_Error
     */
    public static function merge_contacts( int $target_id, array $source_ids, array $field_overrides = array() ) {
        global $wpdb;
        $t_contacts   = $wpdb->prefix . 'aura_contacts';
        $t_google_map = $wpdb->prefix . 'aura_contacts_google_map';
        $t_sync_log   = $wpdb->prefix . 'aura_contacts_sync_log';

        $target = Aura_Contacts::get_contact( $target_id );
        if ( ! $target || empty( $target['is_active'] ) ) {
            return new WP_Error( 'invalid_target', __( 'El contacto maestro de destino no existe o está inactivo.', 'aura-suite' ) );
        }

        // Limpiar fuentes
        $source_ids = array_values( array_unique( array_filter( array_map( 'absint', $source_ids ) ) ) );
        $source_ids = array_diff( $source_ids, array( $target_id ) );

        if ( empty( $source_ids ) ) {
            return new WP_Error( 'missing_sources', __( 'Debes seleccionar al menos un contacto secundario para fusionar.', 'aura-suite' ) );
        }

        $sources = array();
        foreach ( $source_ids as $sid ) {
            $s = Aura_Contacts::get_contact( $sid );
            if ( $s ) {
                $sources[] = $s;
            }
        }

        if ( empty( $sources ) ) {
            return new WP_Error( 'no_valid_sources', __( 'Ninguno de los contactos secundarios seleccionados existe.', 'aura-suite' ) );
        }

        // Iniciar transacción de base de datos
        $wpdb->query( 'START TRANSACTION' );

        try {
            $updated_target = array();
            $target_fields = array(
                'last_name',
                'organization',
                'job_title',
                'email_primary',
                'email_secondary',
                'phone_primary',
                'phone_secondary',
                'whatsapp',
                'address_street',
                'address_city',
                'address_state',
                'address_country',
                'address_postal',
                'avatar_url',
            );

            // Aplicar campos faltantes en target desde los fuentes
            foreach ( $target_fields as $f ) {
                if ( ! empty( $field_overrides[ $f ] ) ) {
                    $updated_target[ $f ] = $field_overrides[ $f ];
                } elseif ( empty( $target[ $f ] ) ) {
                    foreach ( $sources as $s ) {
                        if ( ! empty( $s[ $f ] ) ) {
                            $updated_target[ $f ] = $s[ $f ];
                            break;
                        }
                    }
                }
            }

            // Unificar vinculaciones de entidades si target no las tiene
            if ( empty( $target['wp_user_id'] ) ) {
                foreach ( $sources as $s ) {
                    if ( ! empty( $s['wp_user_id'] ) ) {
                        $updated_target['wp_user_id'] = $s['wp_user_id'];
                        break;
                    }
                }
            }
            if ( empty( $target['third_party_id'] ) ) {
                foreach ( $sources as $s ) {
                    if ( ! empty( $s['third_party_id'] ) ) {
                        $updated_target['third_party_id'] = $s['third_party_id'];
                        break;
                    }
                }
            }
            if ( empty( $target['student_id'] ) ) {
                foreach ( $sources as $s ) {
                    if ( ! empty( $s['student_id'] ) ) {
                        $updated_target['student_id'] = $s['student_id'];
                        break;
                    }
                }
            }

            // Unificar etiquetas (Tags)
            $all_tags = is_array( $target['tags'] ) ? $target['tags'] : array();
            foreach ( $sources as $s ) {
                if ( ! empty( $s['tags'] ) && is_array( $s['tags'] ) ) {
                    $all_tags = array_merge( $all_tags, $s['tags'] );
                }
            }
            $all_tags = array_values( array_unique( array_filter( $all_tags ) ) );
            if ( ! empty( $all_tags ) ) {
                $updated_target['tags'] = wp_json_encode( $all_tags );
            }

            // Consolidar notas con registro de auditoría
            $merged_notes = trim( (string) ( $target['notes'] ?? '' ) );
            $timestamp    = current_time( 'mysql' );
            foreach ( $sources as $s ) {
                $s_name  = trim( $s['first_name'] . ' ' . $s['last_name'] );
                $s_notes = trim( (string) ( $s['notes'] ?? '' ) );
                $audit_prefix = "\n\n[" . sprintf( __( 'Fusionado el %s desde Contacto #%d (%s)', 'aura-suite' ), $timestamp, $s['id'], $s_name ) . "]";
                if ( ! empty( $s_notes ) ) {
                    $merged_notes .= $audit_prefix . "\n" . $s_notes;
                } else {
                    $merged_notes .= $audit_prefix;
                }
            }
            $updated_target['notes'] = trim( $merged_notes );

            // Si target no tiene Google Resource Name mapeado, verificar si algún source lo tenía
            $target_map = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_google_map} WHERE contact_id = %d LIMIT 1", $target_id ), ARRAY_A );
            if ( ! $target_map ) {
                foreach ( $sources as $s ) {
                    $s_map = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_google_map} WHERE contact_id = %d LIMIT 1", $s['id'] ), ARRAY_A );
                    if ( $s_map ) {
                        $wpdb->update( $t_google_map, array( 'contact_id' => $target_id ), array( 'id' => $s_map['id'] ) );
                        break;
                    }
                }
            }

            // Actualizar referencias externas de llaves foráneas hacia target_id
            $foreign_tables = array(
                $wpdb->prefix . 'aura_vehicle_trips'       => 'contact_id',
                $wpdb->prefix . 'aura_inventory_loans'     => 'contact_id',
                $wpdb->prefix . 'aura_library_loans'       => 'contact_id',
                $wpdb->prefix . 'aura_calendar_events'     => 'contact_id',
            );

            foreach ( $foreign_tables as $tbl => $col ) {
                $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) );
                if ( $table_exists ) {
                    foreach ( $sources as $s ) {
                        $wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET {$col} = %d WHERE {$col} = %d", $target_id, $s['id'] ) );
                    }
                }
            }

            // Desactivar contactos de origen y marcarlos como fusionados
            foreach ( $sources as $s ) {
                $wpdb->update(
                    $t_contacts,
                    array(
                        'is_active'   => 0,
                        'sync_status' => 'local_only',
                        'notes'       => trim( (string) $s['notes'] ) . "\n\n[" . sprintf( __( 'Registro archivado y fusionado en el contacto maestro #%d el %s.', 'aura-suite' ), $target_id, $timestamp ) . ']',
                        'updated_at'  => current_time( 'mysql' ),
                        'updated_by'  => get_current_user_id() ?: 1,
                    ),
                    array( 'id' => $s['id'] )
                );
            }

            // Actualizar contacto maestro de destino
            $updated_target['sync_status'] = 'pending'; // Listo para actualizar en Google si está vinculado
            $updated_target['updated_at']  = current_time( 'mysql' );
            $updated_target['updated_by']  = get_current_user_id() ?: 1;

            $wpdb->update( $t_contacts, $updated_target, array( 'id' => $target_id ) );

            // Registrar en logs de auditoría
            $wpdb->insert( $t_sync_log, array(
                'contact_id'    => $target_id,
                'direction'     => 'push',
                'action'        => 'merge',
                'status'        => 'success',
                'message'       => sprintf( __( 'Fusión exitosa: Se unificaron los contactos IDs [%s] dentro del contacto #%d.', 'aura-suite' ), implode( ', ', $source_ids ), $target_id ),
                'created_at'    => current_time( 'mysql' ),
            ) );

            $wpdb->query( 'COMMIT' );

            return array(
                'success'        => true,
                'target_id'      => $target_id,
                'merged_sources' => count( $source_ids ),
                'message'        => sprintf( __( 'Se han fusionado con éxito %d contacto(s) en la ficha maestra #%d.', 'aura-suite' ), count( $source_ids ), $target_id ),
            );

        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'merge_exception', $e->getMessage() );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CONTROLADORES AJAX
    // ─────────────────────────────────────────────────────────────

    /**
     * AJAX: Buscar posibles duplicados.
     */
    public static function ajax_find_duplicates(): void {
        check_ajax_referer( Aura_Contacts::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ) );
        }

        $clusters = self::find_duplicates();

        wp_send_json_success( array(
            'clusters' => $clusters,
            'count'    => count( $clusters ),
        ) );
    }

    /**
     * AJAX: Ejecutar fusión de contactos.
     */
    public static function ajax_merge(): void {
        check_ajax_referer( Aura_Contacts::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos suficientes para fusionar contactos.', 'aura-suite' ) ) );
        }

        $target_id       = absint( $_POST['target_id'] ?? 0 );
        $source_ids_raw  = $_POST['source_ids'] ?? array();
        $source_ids      = is_array( $source_ids_raw ) ? array_map( 'absint', $source_ids_raw ) : array();
        $field_overrides = isset( $_POST['field_overrides'] ) && is_array( $_POST['field_overrides'] ) ? array_map( 'sanitize_text_field', $_POST['field_overrides'] ) : array();

        if ( ! $target_id || empty( $source_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Parámetros incompletos para ejecutar la fusión.', 'aura-suite' ) ) );
        }

        $result = self::merge_contacts( $target_id, $source_ids, $field_overrides );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }
}
