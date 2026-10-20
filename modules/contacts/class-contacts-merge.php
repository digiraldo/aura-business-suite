<?php
/**
 * Motor Inteligente de Detección de Duplicados y Fusión (Merge) de Contactos en Aura Suite.
 *
 * Permite identificar coincidencias inteligentes por Correo Electrónico,
 * Teléfono normalizado, similitud fonética/ortográfica de Nombres y Organizaciones,
 * consolidando clusters conexos y permitiendo al usuario configurar campo por campo
 * la ficha maestra final sin perder historial, canales de contacto ni vinculaciones.
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
     * Normaliza cadenas de texto para comparaciones inteligentes:
     * minúsculas, remueve acentos/diacríticos y signos de puntuación.
     *
     * @param string $str
     * @return string
     */
    public static function clean_for_comparison( string $str ): string {
        $str = mb_strtolower( trim( $str ), 'UTF-8' );
        $unwanted = array(
            'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u', 'ñ'=>'n',
            'à'=>'a', 'è'=>'e', 'ì'=>'i', 'ò'=>'o', 'ù'=>'u',
            'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u', 'Ü'=>'u', 'Ñ'=>'n',
        );
        $str = strtr( $str, $unwanted );
        $str = preg_replace( '/[^a-z0-9\s]/', '', $str );
        return preg_replace( '/\s+/', ' ', trim( $str ) );
    }

    /**
     * Normaliza un número telefónico extrayendo sus dígitos canónicos
     * (omitiendo prefijos internacionales si son más de 10 dígitos).
     *
     * @param string $phone
     * @return string
     */
    public static function normalize_phone( string $phone ): string {
        $digits = preg_replace( '/\D+/', '', $phone );
        if ( strlen( $digits ) > 10 ) {
            return substr( $digits, -10 );
        }
        return $digits;
    }

    /**
     * Analiza el directorio de contactos y encuentra grupos (clusters) de posibles duplicados
     * utilizando análisis multi-criterio y consolidación de grafos conectados.
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

        // Estructura de adyacencia para unir duplicados conexos (Disjoint Set / Graph)
        $matches = array(); // [ [ 'c1' => id, 'c2' => id, 'type' => ..., 'label' => ..., 'value' => ..., 'confidence' => ... ] ]

        // 1. Detección por Correo Electrónico
        $by_email = array();
        foreach ( $contacts as $c ) {
            $e1 = strtolower( trim( (string) $c['email_primary'] ) );
            $e2 = strtolower( trim( (string) $c['email_secondary'] ) );
            if ( ! empty( $e1 ) ) {
                $by_email[ $e1 ][] = $c['id'];
            }
            if ( ! empty( $e2 ) && $e2 !== $e1 ) {
                $by_email[ $e2 ][] = $c['id'];
            }
        }

        foreach ( $by_email as $email => $ids ) {
            $ids = array_unique( $ids );
            $count = count( $ids );
            if ( $count > 1 ) {
                for ( $i = 0; $i < $count; $i++ ) {
                    for ( $j = $i + 1; $j < $count; $j++ ) {
                        $matches[] = array(
                            'c1'         => $ids[ $i ],
                            'c2'         => $ids[ $j ],
                            'type'       => 'email',
                            'type_label' => __( 'Correo Coincidente', 'aura-suite' ),
                            'value'      => $email,
                            'confidence' => 100,
                            'badge'      => '#10b981',
                        );
                    }
                }
            }
        }

        // 2. Detección por Teléfono Normalizado
        $by_phone = array();
        foreach ( $contacts as $c ) {
            $phones = array();
            $p1 = self::normalize_phone( (string) $c['phone_primary'] );
            $p2 = self::normalize_phone( (string) $c['phone_secondary'] );
            $p3 = self::normalize_phone( (string) $c['whatsapp'] );

            if ( strlen( $p1 ) >= 8 ) $phones[] = $p1;
            if ( strlen( $p2 ) >= 8 ) $phones[] = $p2;
            if ( strlen( $p3 ) >= 8 ) $phones[] = $p3;

            foreach ( array_unique( $phones ) as $phone ) {
                $by_phone[ $phone ][] = $c['id'];
            }
        }

        foreach ( $by_phone as $phone => $ids ) {
            $ids = array_unique( $ids );
            $count = count( $ids );
            if ( $count > 1 ) {
                for ( $i = 0; $i < $count; $i++ ) {
                    for ( $j = $i + 1; $j < $count; $j++ ) {
                        $matches[] = array(
                            'c1'         => $ids[ $i ],
                            'c2'         => $ids[ $j ],
                            'type'       => 'phone',
                            'type_label' => __( 'Teléfono Coincidente', 'aura-suite' ),
                            'value'      => $phone,
                            'confidence' => 95,
                            'badge'      => '#3b82f6',
                        );
                    }
                }
            }
        }

        // 3. Detección Avanzada por Nombres y Organizaciones
        $c_count = count( $contacts );
        for ( $i = 0; $i < $c_count; $i++ ) {
            $c1 = $contacts[ $i ];
            $fn1 = self::clean_for_comparison( (string) $c1['first_name'] );
            $ln1 = self::clean_for_comparison( (string) $c1['last_name'] );
            $full1 = trim( $fn1 . ' ' . $ln1 );
            $inv1  = trim( $ln1 . ' ' . $fn1 );
            $org1  = self::clean_for_comparison( (string) $c1['organization'] );

            for ( $j = $i + 1; $j < $c_count; $j++ ) {
                $c2 = $contacts[ $j ];
                $fn2 = self::clean_for_comparison( (string) $c2['first_name'] );
                $ln2 = self::clean_for_comparison( (string) $c2['last_name'] );
                $full2 = trim( $fn2 . ' ' . $ln2 );
                $inv2  = trim( $ln2 . ' ' . $fn2 );
                $org2  = self::clean_for_comparison( (string) $c2['organization'] );

                // A. Nombre completo idéntico (normalizado)
                if ( ! empty( $full1 ) && strlen( $full1 ) >= 5 && $full1 === $full2 ) {
                    $matches[] = array(
                        'c1'         => $c1['id'],
                        'c2'         => $c2['id'],
                        'type'       => 'name',
                        'type_label' => __( 'Nombre Idéntico', 'aura-suite' ),
                        'value'      => trim( $c1['first_name'] . ' ' . $c1['last_name'] ),
                        'confidence' => 92,
                        'badge'      => '#8b5cf6',
                    );
                    continue;
                }

                // B. Nombre invertido idéntico (ej: Diego Giraldo vs Giraldo Diego)
                if ( ! empty( $full1 ) && ! empty( $ln1 ) && ( $full1 === $inv2 || $inv1 === $full2 ) ) {
                    $matches[] = array(
                        'c1'         => $c1['id'],
                        'c2'         => $c2['id'],
                        'type'       => 'name',
                        'type_label' => __( 'Nombre Transpuesto', 'aura-suite' ),
                        'value'      => trim( $c1['first_name'] . ' ' . $c1['last_name'] ) . ' ↔ ' . trim( $c2['first_name'] . ' ' . $c2['last_name'] ),
                        'confidence' => 90,
                        'badge'      => '#8b5cf6',
                    );
                    continue;
                }

                // C. Similitud de texto elevada en nombres (>= 86%)
                if ( ! empty( $full1 ) && ! empty( $full2 ) && strlen( $full1 ) >= 6 && strlen( $full2 ) >= 6 ) {
                    similar_text( $full1, $full2, $pct_name );
                    if ( $pct_name >= 86 ) {
                        $matches[] = array(
                            'c1'         => $c1['id'],
                            'c2'         => $c2['id'],
                            'type'       => 'name',
                            'type_label' => __( 'Nombre Muy Similar', 'aura-suite' ),
                            'value'      => trim( $c1['first_name'] . ' ' . $c1['last_name'] ) . ' ≈ ' . trim( $c2['first_name'] . ' ' . $c2['last_name'] ),
                            'confidence' => round( (float) $pct_name ),
                            'badge'      => '#a855f7',
                        );
                        continue;
                    }
                }

                // D. Misma Organización y mismo primer nombre
                if ( ! empty( $org1 ) && strlen( $org1 ) >= 4 && $org1 === $org2 && ! empty( $fn1 ) && $fn1 === $fn2 ) {
                    $matches[] = array(
                        'c1'         => $c1['id'],
                        'c2'         => $c2['id'],
                        'type'       => 'org',
                        'type_label' => __( 'Misma Empresa y Nombre', 'aura-suite' ),
                        'value'      => $c1['organization'] . ' (' . $c1['first_name'] . ')',
                        'confidence' => 88,
                        'badge'      => '#f59e0b',
                    );
                    continue;
                }
            }
        }

        if ( empty( $matches ) ) {
            return array();
        }

        // 4. Consolidar componentes conexos (Grafo de Clusters)
        // Agrupa contactos que están conectados directa o indirectamente
        $parent = array();
        $find = function( $i ) use ( &$parent, &$find ) {
            if ( ! isset( $parent[ $i ] ) ) {
                $parent[ $i ] = $i;
            }
            if ( $parent[ $i ] !== $i ) {
                $parent[ $i ] = $find( $parent[ $i ] );
            }
            return $parent[ $i ];
        };

        $union = function( $i, $j ) use ( &$parent, &$find ) {
            $root_i = $find( $i );
            $root_j = $find( $j );
            if ( $root_i !== $root_j ) {
                $parent[ $root_i ] = $root_j;
            }
        };

        foreach ( $matches as $m ) {
            $union( $m['c1'], $m['c2'] );
        }

        // Agrupar razones e IDs por raíz de cluster
        $cluster_ids     = array();
        $cluster_reasons = array();
        $cluster_conf    = array();

        foreach ( $matches as $m ) {
            $root = $find( $m['c1'] );
            $cluster_ids[ $root ][ $m['c1'] ] = true;
            $cluster_ids[ $root ][ $m['c2'] ] = true;

            $reason_key = $m['type'] . '_' . $m['value'];
            if ( ! isset( $cluster_reasons[ $root ][ $reason_key ] ) ) {
                $cluster_reasons[ $root ][ $reason_key ] = array(
                    'type'  => $m['type'],
                    'label' => $m['type_label'],
                    'value' => $m['value'],
                    'badge' => $m['badge'],
                );
            }
            $cluster_conf[ $root ] = max( $cluster_conf[ $root ] ?? 0, $m['confidence'] );
        }

        // Indexar contactos por ID para armado ágil
        $indexed_contacts = array();
        foreach ( $contacts as $c ) {
            $indexed_contacts[ $c['id'] ] = $c;
        }

        $clusters = array();
        foreach ( $cluster_ids as $root => $members ) {
            $member_ids = array_keys( $members );
            sort( $member_ids );

            $formatted_contacts = array();
            foreach ( $member_ids as $mid ) {
                if ( isset( $indexed_contacts[ $mid ] ) ) {
                    $formatted_contacts[] = Aura_Contacts::format_contact_row( $indexed_contacts[ $mid ] );
                }
            }

            if ( count( $formatted_contacts ) < 2 ) {
                continue;
            }

            $reasons = array_values( $cluster_reasons[ $root ] ?? array() );
            $main_reason = $reasons[0] ?? array( 'type' => 'mixed', 'label' => __( 'Coincidencia Múltiple', 'aura-suite' ), 'value' => '' );

            $clusters[] = array(
                'id'          => 'cluster_' . implode( '_', $member_ids ),
                'type'        => $main_reason['type'],
                'type_label'  => $main_reason['label'],
                'match_value' => $main_reason['value'],
                'confidence'  => $cluster_conf[ $root ] ?? 90,
                'badge_color' => $main_reason['badge'] ?? '#6366f1',
                'reasons'     => $reasons,
                'contacts'    => $formatted_contacts,
            );
        }

        // Ordenar clusters por confianza descendente
        usort( $clusters, function( $a, $b ) {
            return $b['confidence'] <=> $a['confidence'];
        } );

        return array_values( $clusters );
    }

    /**
     * Ejecuta la fusión atómica de uno o varios contactos de origen hacia un contacto de destino,
     * aplicando las elecciones y personalizaciones campo a campo elegidas por el usuario.
     *
     * @param int   $target_id        ID del contacto maestro resultante.
     * @param array $source_ids       IDs de los contactos secundarios a fusionar y desactivar.
     * @param array $field_overrides  Sobrescribir campos específicos definidos por el usuario en el modal.
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

        // Iniciar transacción atómica de base de datos
        $wpdb->query( 'START TRANSACTION' );

        try {
            $updated_target = array();

            // Lista completa de campos elegibles/modificables
            $all_configurable_fields = array(
                'first_name',
                'last_name',
                'organization',
                'job_title',
                'category',
                'contact_type',
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

            // 1. Aplicar selecciones del usuario o heredar valores no vacíos
            foreach ( $all_configurable_fields as $f ) {
                if ( isset( $field_overrides[ $f ] ) && $field_overrides[ $f ] !== '' ) {
                    $updated_target[ $f ] = sanitize_text_field( $field_overrides[ $f ] );
                } elseif ( empty( $target[ $f ] ) ) {
                    foreach ( $sources as $s ) {
                        if ( ! empty( $s[ $f ] ) ) {
                            $updated_target[ $f ] = $s[ $f ];
                            break;
                        }
                    }
                }
            }

            // 2. Inteligencia de Preservación de Canales de Contacto Alternos
            // Si el correo primario elegido desplazó a otro correo válido, moverlo a secundario si está disponible
            $chosen_email_primary = $updated_target['email_primary'] ?? ( $target['email_primary'] ?? '' );
            $chosen_email_secondary = $updated_target['email_secondary'] ?? ( $target['email_secondary'] ?? '' );

            if ( empty( $chosen_email_secondary ) ) {
                foreach ( array_merge( array( $target ), $sources ) as $item ) {
                    $cand_e1 = strtolower( trim( (string) $item['email_primary'] ) );
                    $cand_e2 = strtolower( trim( (string) $item['email_secondary'] ) );
                    if ( ! empty( $cand_e1 ) && $cand_e1 !== strtolower( $chosen_email_primary ) ) {
                        $updated_target['email_secondary'] = $cand_e1;
                        break;
                    } elseif ( ! empty( $cand_e2 ) && $cand_e2 !== strtolower( $chosen_email_primary ) ) {
                        $updated_target['email_secondary'] = $cand_e2;
                        break;
                    }
                }
            }

            // Misma preservación inteligente para teléfonos:
            $chosen_phone_primary = $updated_target['phone_primary'] ?? ( $target['phone_primary'] ?? '' );
            $chosen_phone_secondary = $updated_target['phone_secondary'] ?? ( $target['phone_secondary'] ?? '' );
            if ( empty( $chosen_phone_secondary ) ) {
                foreach ( array_merge( array( $target ), $sources ) as $item ) {
                    $cand_p1 = trim( (string) $item['phone_primary'] );
                    $cand_p2 = trim( (string) $item['phone_secondary'] );
                    if ( ! empty( $cand_p1 ) && $cand_p1 !== $chosen_phone_primary ) {
                        $updated_target['phone_secondary'] = $cand_p1;
                        break;
                    } elseif ( ! empty( $cand_p2 ) && $cand_p2 !== $chosen_phone_primary ) {
                        $updated_target['phone_secondary'] = $cand_p2;
                        break;
                    }
                }
            }

            // 3. Unificar vinculaciones de entidades si target no las tiene o si se forzaron
            if ( isset( $field_overrides['wp_user_id'] ) && $field_overrides['wp_user_id'] !== '' ) {
                $updated_target['wp_user_id'] = absint( $field_overrides['wp_user_id'] );
            } elseif ( empty( $target['wp_user_id'] ) ) {
                foreach ( $sources as $s ) {
                    if ( ! empty( $s['wp_user_id'] ) ) {
                        $updated_target['wp_user_id'] = $s['wp_user_id'];
                        break;
                    }
                }
            }

            if ( isset( $field_overrides['third_party_id'] ) && $field_overrides['third_party_id'] !== '' ) {
                $updated_target['third_party_id'] = absint( $field_overrides['third_party_id'] );
            } elseif ( empty( $target['third_party_id'] ) ) {
                foreach ( $sources as $s ) {
                    if ( ! empty( $s['third_party_id'] ) ) {
                        $updated_target['third_party_id'] = $s['third_party_id'];
                        break;
                    }
                }
            }

            if ( isset( $field_overrides['student_id'] ) && $field_overrides['student_id'] !== '' ) {
                $updated_target['student_id'] = absint( $field_overrides['student_id'] );
            } elseif ( empty( $target['student_id'] ) ) {
                foreach ( $sources as $s ) {
                    if ( ! empty( $s['student_id'] ) ) {
                        $updated_target['student_id'] = $s['student_id'];
                        break;
                    }
                }
            }

            // 4. Unificar o personalizar etiquetas (Tags) de las fichas
            $tags_mode = isset( $field_overrides['tags_mode'] ) ? sanitize_key( $field_overrides['tags_mode'] ) : 'combine';
            $all_tags  = array();

            if ( $tags_mode === 'replace' || ( isset( $field_overrides['custom_tags'] ) && $tags_mode !== 'combine' ) ) {
                $raw_custom = $field_overrides['custom_tags'] ?? ( $field_overrides['tags'] ?? '' );
                if ( is_array( $raw_custom ) ) {
                    $all_tags = $raw_custom;
                } elseif ( is_string( $raw_custom ) && trim( $raw_custom ) !== '' ) {
                    $all_tags = array_map( 'trim', explode( ',', $raw_custom ) );
                }
            } else {
                // Modo combinar todas las etiquetas
                $target_tags = is_array( $target['tags'] ) ? $target['tags'] : ( is_string( $target['tags'] ) ? json_decode( $target['tags'], true ) : array() );
                $all_tags    = is_array( $target_tags ) ? $target_tags : array();

                foreach ( $sources as $s ) {
                    $s_tags = is_array( $s['tags'] ) ? $s['tags'] : ( is_string( $s['tags'] ) ? json_decode( $s['tags'], true ) : array() );
                    if ( ! empty( $s_tags ) && is_array( $s_tags ) ) {
                        $all_tags = array_merge( $all_tags, $s_tags );
                    }
                }

                if ( ! empty( $field_overrides['custom_tags'] ) ) {
                    $custom_tags = array_map( 'trim', explode( ',', $field_overrides['custom_tags'] ) );
                    $all_tags    = array_merge( $all_tags, $custom_tags );
                }
            }

            $all_tags = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $all_tags ) ) ) );
            $updated_target['tags'] = ! empty( $all_tags ) ? wp_json_encode( $all_tags ) : null;

            // 5. Consolidar notas con registro de auditoría claro
            $merged_notes = trim( (string) ( $target['notes'] ?? '' ) );
            if ( ! empty( $field_overrides['custom_notes'] ) ) {
                $merged_notes = trim( $field_overrides['custom_notes'] ) . "\n\n" . $merged_notes;
            }

            $timestamp = current_time( 'mysql' );
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

            // 6. Si target no tiene Google Resource Name mapeado, transferir el de algún origen
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

            // 7. Actualizar referencias externas de llaves foráneas hacia target_id
            $foreign_tables = array(
                $wpdb->prefix . 'aura_vehicle_trips'   => 'contact_id',
                $wpdb->prefix . 'aura_inventory_loans' => 'contact_id',
                $wpdb->prefix . 'aura_library_loans'   => 'contact_id',
                $wpdb->prefix . 'aura_calendar_events' => 'contact_id',
            );

            foreach ( $foreign_tables as $tbl => $col ) {
                $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) );
                if ( $table_exists ) {
                    foreach ( $sources as $s ) {
                        $wpdb->query( $wpdb->prepare( "UPDATE {$tbl} SET {$col} = %d WHERE {$col} = %d", $target_id, $s['id'] ) );
                    }
                }
            }

            // 8. Desactivar contactos de origen y marcarlos como fusionados
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

            // 9. Actualizar contacto maestro de destino
            $updated_target['sync_status'] = 'pending'; // Listo para propagar a Google People API si está conectado
            $updated_target['updated_at']  = current_time( 'mysql' );
            $updated_target['updated_by']  = get_current_user_id() ?: 1;

            $wpdb->update( $t_contacts, $updated_target, array( 'id' => $target_id ) );

            // 10. Registrar en logs de auditoría
            $wpdb->insert( $t_sync_log, array(
                'contact_id' => $target_id,
                'direction'  => 'push',
                'action'     => 'merge',
                'status'     => 'success',
                'message'    => sprintf( __( 'Fusión inteligente completada: Contactos [%s] integrados en el contacto maestro #%d con personalización campo a campo.', 'aura-suite' ), implode( ', ', $source_ids ), $target_id ),
                'created_at' => current_time( 'mysql' ),
            ) );

            $wpdb->query( 'COMMIT' );

            return array(
                'success'        => true,
                'target_id'      => $target_id,
                'merged_sources' => count( $source_ids ),
                'message'        => sprintf( __( 'Fusión completada con éxito. %d contacto(s) unificados en el registro #%d.', 'aura-suite' ), count( $source_ids ), $target_id ),
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
     * AJAX: Buscar posibles duplicados con motor enriquecido.
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
     * AJAX: Ejecutar fusión personalizada campo a campo de contactos.
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
