<?php
/**
 * Clase: Gestor de Portabilidad y Paquetes Multimedia (ZIP Bundle)
 * Permite exportar e importar Terceros, Áreas y Usuarios de WordPress
 * conservando íntegramente sus imágenes, avatares y relaciones.
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Portable_Bundle {

    private static $temp_dir_name = 'aura-portable-bundle';

    /**
     * Inicializar hooks y endpoints AJAX
     */
    public static function init() {
        add_action( 'wp_ajax_aura_bundle_export',  [ __CLASS__, 'ajax_export' ] );
        add_action( 'wp_ajax_aura_bundle_inspect', [ __CLASS__, 'ajax_inspect' ] );
        add_action( 'wp_ajax_aura_bundle_import',  [ __CLASS__, 'ajax_import' ] );
    }

    /**
     * Directorio base temporal para bundles
     */
    public static function get_bundle_dir() {
        $upload_dir = wp_upload_dir();
        $dir = trailingslashit( $upload_dir['basedir'] ) . self::$temp_dir_name . '/';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            // Proteger con .htaccess o index.php
            @file_put_contents( $dir . 'index.php', '<?php // Silence is golden' );
        }
        return $dir;
    }

    /**
     * =========================================================================
     * EXPORTACIÓN DE PAQUETE PORTÁTIL (.ZIP)
     * =========================================================================
     */
    public static function ajax_export() {
        check_ajax_referer( 'aura_bundle_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_admin_settings' ) && ! current_user_can( 'aura_third_parties_view' ) ) {
            wp_send_json_error( [ 'message' => __( 'No tienes permisos suficientes para realizar esta exportación.', 'aura-suite' ) ], 403 );
        }

        if ( ! class_exists( 'ZipArchive' ) ) {
            wp_send_json_error( [ 'message' => __( 'La extensión PHP ZipArchive no está habilitada en tu servidor.', 'aura-suite' ) ] );
        }

        // Entidades seleccionadas para exportar
        $export_tp    = ! empty( $_POST['export_third_parties'] );
        $export_areas = ! empty( $_POST['export_areas'] );
        $export_users = ! empty( $_POST['export_users'] );

        // Si no se marcó ninguna, exportar todas por defecto
        if ( ! $export_tp && ! $export_areas && ! $export_users ) {
            $export_tp = $export_areas = $export_users = true;
        }

        $base_dir = self::get_bundle_dir();
        $zip_filename = 'aura-data-bundle-' . gmdate( 'Y-m-d-His' ) . '.zip';
        $zip_filepath = $base_dir . $zip_filename;

        $zip = new ZipArchive();
        if ( $zip->open( $zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            wp_send_json_error( [ 'message' => __( 'No se pudo crear el archivo ZIP temporal.', 'aura-suite' ) ] );
        }

        $manifest = [
            'generator'    => 'Aura Business Suite Portable Bundle',
            'version'      => '1.0',
            'exported_at'  => current_time( 'mysql' ),
            'site_url'     => home_url(),
            'counts'       => [
                'third_parties' => 0,
                'areas'         => 0,
                'users'         => 0,
                'media_files'   => 0,
            ],
            'third_parties' => [],
            'areas'         => [],
            'users'         => [],
        ];

        $media_count = 0;

        // ── 1. Exportar Terceros ──────────────────────────────────────────
        if ( $export_tp ) {
            global $wpdb;
            $tp_table = $wpdb->prefix . 'aura_finance_third_parties';
            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tp_table}'" ) === $tp_table ) {
                $rows = $wpdb->get_results( "SELECT * FROM {$tp_table} ORDER BY id ASC", ARRAY_A );
                foreach ( $rows as $row ) {
                    $media_rel_path = '';
                    $logo_id = (int) ( $row['logo_id'] ?? 0 );

                    if ( $logo_id > 0 ) {
                        $source_file = get_attached_file( $logo_id );
                        if ( $source_file && file_exists( $source_file ) ) {
                            $ext = strtolower( pathinfo( $source_file, PATHINFO_EXTENSION ) );
                            $safe_name = sanitize_title( $row['full_name'] ?: 'tercero' );
                            $zip_path = "media/third_parties/tp_{$row['id']}_{$safe_name}.{$ext}";
                            if ( $zip->addFile( $source_file, $zip_path ) ) {
                                $media_rel_path = $zip_path;
                                $media_count++;
                            }
                        }
                    }

                    // Identificar si tiene un usuario WP vinculado para exportar su login
                    $linked_login = '';
                    if ( ! empty( $row['wp_user_id'] ) ) {
                        $u = get_user_by( 'id', (int) $row['wp_user_id'] );
                        if ( $u ) {
                            $linked_login = $u->user_login;
                        }
                    }

                    $row['media_path']   = $media_rel_path;
                    $row['linked_login'] = $linked_login;
                    $manifest['third_parties'][] = $row;
                }
                $manifest['counts']['third_parties'] = count( $manifest['third_parties'] );
            }
        }

        // ── 2. Exportar Áreas ─────────────────────────────────────────────
        if ( $export_areas ) {
            global $wpdb;
            $areas_table = $wpdb->prefix . 'aura_areas';
            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$areas_table}'" ) === $areas_table ) {
                $areas = $wpdb->get_results( "SELECT * FROM {$areas_table} ORDER BY id ASC", ARRAY_A );
                foreach ( $areas as $area ) {
                    $media_rel_path = '';
                    $logo_id = (int) ( $area['logo_id'] ?? 0 );

                    if ( $logo_id > 0 ) {
                        $source_file = get_attached_file( $logo_id );
                        if ( $source_file && file_exists( $source_file ) ) {
                            $ext = strtolower( pathinfo( $source_file, PATHINFO_EXTENSION ) );
                            $safe_slug = sanitize_title( $area['slug'] ?: $area['name'] );
                            $zip_path = "media/areas/area_{$area['id']}_{$safe_slug}.{$ext}";
                            if ( $zip->addFile( $source_file, $zip_path ) ) {
                                $media_rel_path = $zip_path;
                                $media_count++;
                            }
                        }
                    }

                    // Responsable por login
                    $resp_login = '';
                    if ( ! empty( $area['responsible_user_id'] ) ) {
                        $u = get_user_by( 'id', (int) $area['responsible_user_id'] );
                        if ( $u ) {
                            $resp_login = $u->user_login;
                        }
                    }

                    // Usuarios asignados (muchos a muchos)
                    $assigned_users = [];
                    $rel_table = $wpdb->prefix . 'aura_area_users';
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$rel_table}'" ) === $rel_table ) {
                        $rel_rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, role FROM {$rel_table} WHERE area_id = %d", $area['id'] ), ARRAY_A );
                        foreach ( $rel_rows as $rel ) {
                            $u = get_user_by( 'id', (int) $rel['user_id'] );
                            if ( $u ) {
                                $assigned_users[] = [
                                    'login' => $u->user_login,
                                    'role'  => $rel['role']
                                ];
                            }
                        }
                    }

                    $area['media_path']         = $media_rel_path;
                    $area['responsible_login']  = $resp_login;
                    $area['assigned_logins']    = $assigned_users;
                    $manifest['areas'][] = $area;
                }
                $manifest['counts']['areas'] = count( $manifest['areas'] );
            }
        }

        // ── 3. Exportar Usuarios de WordPress ─────────────────────────────
        if ( $export_users ) {
            $all_users = get_users( [ 'number' => 2000 ] );
            foreach ( $all_users as $user ) {
                $avatar_rel_path = '';
                $avatar_id = (int) ( get_user_meta( $user->ID, 'aura_avatar_id', true )
                                  ?: get_user_meta( $user->ID, 'wp_user_avatar', true ) );

                // Si no tiene avatar por ID, verificar si hay simple_local_avatar
                if ( ! $avatar_id ) {
                    $sla = get_user_meta( $user->ID, 'simple_local_avatar', true );
                    if ( is_array( $sla ) && ! empty( $sla['media_id'] ) ) {
                        $avatar_id = (int) $sla['media_id'];
                    }
                }

                if ( $avatar_id > 0 ) {
                    $source_file = get_attached_file( $avatar_id );
                    if ( $source_file && file_exists( $source_file ) ) {
                        $ext = strtolower( pathinfo( $source_file, PATHINFO_EXTENSION ) );
                        $safe_login = sanitize_title( $user->user_login );
                        $zip_path = "media/users/user_{$user->ID}_{$safe_login}.{$ext}";
                        if ( $zip->addFile( $source_file, $zip_path ) ) {
                            $avatar_rel_path = $zip_path;
                            $media_count++;
                        }
                    }
                }

                $user_data = [
                    'ID'           => $user->ID,
                    'user_login'   => $user->user_login,
                    'user_email'   => $user->user_email,
                    'user_nicename'=> $user->user_nicename,
                    'display_name' => $user->display_name,
                    'first_name'   => get_user_meta( $user->ID, 'first_name', true ),
                    'last_name'    => get_user_meta( $user->ID, 'last_name', true ),
                    'roles'        => $user->roles,
                    'media_path'   => $avatar_rel_path,
                ];

                $manifest['users'][] = $user_data;
            }
            $manifest['counts']['users'] = count( $manifest['users'] );
        }

        $manifest['counts']['media_files'] = $media_count;

        // Añadir manifest.json al ZIP
        $zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
        $zip->close();

        $upload_dir = wp_upload_dir();
        $download_url = trailingslashit( $upload_dir['baseurl'] ) . self::$temp_dir_name . '/' . $zip_filename;

        wp_send_json_success( [
            'download_url' => $download_url,
            'filename'     => $zip_filename,
            'filesize'     => size_format( @filesize( $zip_filepath ) ),
            'counts'       => $manifest['counts'],
            'message'      => __( 'Paquete generado con éxito.', 'aura-suite' ),
        ] );
    }

    /**
     * =========================================================================
     * INSPECCIÓN / VISTA PREVIA DEL PAQUETE (.ZIP)
     * =========================================================================
     */
    public static function ajax_inspect() {
        check_ajax_referer( 'aura_bundle_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_admin_settings' ) && ! current_user_can( 'aura_finance_import' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para importar datos.', 'aura-suite' ) ], 403 );
        }

        if ( empty( $_FILES['bundle_file'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No se recibió ningún archivo.', 'aura-suite' ) ] );
        }

        $file = $_FILES['bundle_file'];
        $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $ext !== 'zip' ) {
            wp_send_json_error( [ 'message' => __( 'El archivo debe ser un paquete comprimido .ZIP.', 'aura-suite' ) ] );
        }

        $base_dir = self::get_bundle_dir();
        $token = wp_generate_uuid4();
        $target_dir = $base_dir . 'import_' . $token . '/';
        wp_mkdir_p( $target_dir );

        $zip = new ZipArchive();
        if ( $zip->open( $file['tmp_name'] ) !== true ) {
            self::delete_dir( $target_dir );
            wp_send_json_error( [ 'message' => __( 'No se pudo abrir o descomprimir el archivo ZIP.', 'aura-suite' ) ] );
        }

        $zip->extractTo( $target_dir );
        $zip->close();

        $manifest_file = $target_dir . 'manifest.json';
        if ( ! file_exists( $manifest_file ) ) {
            self::delete_dir( $target_dir );
            wp_send_json_error( [ 'message' => __( 'El paquete ZIP es inválido: no contiene manifest.json.', 'aura-suite' ) ] );
        }

        $manifest_content = file_get_contents( $manifest_file );
        $manifest = json_decode( $manifest_content, true );

        if ( ! is_array( $manifest ) || empty( $manifest['generator'] ) ) {
            self::delete_dir( $target_dir );
            wp_send_json_error( [ 'message' => __( 'El archivo manifest.json no tiene el formato estándar de Aura Suite.', 'aura-suite' ) ] );
        }

        // Guardar token temporal para la ejecución
        set_transient( 'aura_bundle_' . $token, [
            'dir'       => $target_dir,
            'manifest'  => $manifest,
            'uploaded'  => current_time( 'mysql' ),
        ], 2 * HOUR_IN_SECONDS );

        wp_send_json_success( [
            'token'         => $token,
            'counts'        => $manifest['counts'] ?? [],
            'exported_at'   => $manifest['exported_at'] ?? 'Desconocida',
            'source_site'   => $manifest['site_url'] ?? '',
            'third_parties' => count( $manifest['third_parties'] ?? [] ),
            'areas'         => count( $manifest['areas'] ?? [] ),
            'users'         => count( $manifest['users'] ?? [] ),
        ] );
    }

    /**
     * =========================================================================
     * EJECUCIÓN DE IMPORTACIÓN DEL PAQUETE (.ZIP)
     * =========================================================================
     */
    public static function ajax_import() {
        check_ajax_referer( 'aura_bundle_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_admin_settings' ) && ! current_user_can( 'aura_finance_import' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para importar datos.', 'aura-suite' ) ], 403 );
        }

        $token = sanitize_text_field( $_POST['token'] ?? '' );
        $bundle_data = get_transient( 'aura_bundle_' . $token );

        if ( ! $bundle_data || empty( $bundle_data['dir'] ) || ! file_exists( $bundle_data['dir'] ) ) {
            wp_send_json_error( [ 'message' => __( 'La sesión de importación ha expirado o el archivo fue eliminado. Por favor vuelve a cargar el paquete.', 'aura-suite' ) ] );
        }

        $extract_dir = untrailingslashit( $bundle_data['dir'] );
        $manifest    = $bundle_data['manifest'];

        // Opciones de importación
        $import_users_opt = ! empty( $_POST['import_users'] );
        $import_areas_opt = ! empty( $_POST['import_areas'] );
        $import_tp_opt    = ! empty( $_POST['import_third_parties'] );

        // Si no se pasaron filtros específicos, importar todo lo que contenga el bundle
        if ( ! isset( $_POST['import_users'] ) && ! isset( $_POST['import_areas'] ) && ! isset( $_POST['import_third_parties'] ) ) {
            $import_users_opt = ! empty( $manifest['users'] );
            $import_areas_opt = ! empty( $manifest['areas'] );
            $import_tp_opt    = ! empty( $manifest['third_parties'] );
        }

        $results = [
            'users'         => [ 'created' => 0, 'updated' => 0, 'images' => 0, 'failed' => 0 ],
            'areas'         => [ 'created' => 0, 'updated' => 0, 'images' => 0, 'failed' => 0 ],
            'third_parties' => [ 'created' => 0, 'updated' => 0, 'images' => 0, 'failed' => 0 ],
        ];

        $user_login_to_id = [];
        $area_slug_to_id  = [];

        // ── 1. Procesar Usuarios de WordPress ─────────────────────────────
        if ( $import_users_opt && ! empty( $manifest['users'] ) ) {
            foreach ( $manifest['users'] as $u_data ) {
                $login = sanitize_user( $u_data['user_login'] ?? '' );
                $email = sanitize_email( $u_data['user_email'] ?? '' );
                if ( empty( $login ) || empty( $email ) ) {
                    continue;
                }

                $user = get_user_by( 'login', $login ) ?: get_user_by( 'email', $email );
                $user_id = 0;

                if ( $user ) {
                    // Usuario existente: actualizar nombres si corresponde
                    $user_id = $user->ID;
                    $update_args = [
                        'ID'           => $user_id,
                        'display_name' => sanitize_text_field( $u_data['display_name'] ?? $user->display_name ),
                    ];
                    if ( ! empty( $u_data['first_name'] ) ) {
                        $update_args['first_name'] = sanitize_text_field( $u_data['first_name'] );
                    }
                    if ( ! empty( $u_data['last_name'] ) ) {
                        $update_args['last_name'] = sanitize_text_field( $u_data['last_name'] );
                    }
                    wp_update_user( $update_args );
                    $results['users']['updated']++;
                } else {
                    // Crear nuevo usuario con contraseña aleatoria segura
                    $random_pass = wp_generate_password( 18, true, true );
                    $new_user_args = [
                        'user_login'   => $login,
                        'user_email'   => $email,
                        'user_pass'    => $random_pass,
                        'display_name' => sanitize_text_field( $u_data['display_name'] ?? $login ),
                        'first_name'   => sanitize_text_field( $u_data['first_name'] ?? '' ),
                        'last_name'    => sanitize_text_field( $u_data['last_name'] ?? '' ),
                        'role'         => ! empty( $u_data['roles'][0] ) ? sanitize_key( $u_data['roles'][0] ) : 'subscriber',
                    ];
                    $created_id = wp_insert_user( $new_user_args );
                    if ( ! is_wp_error( $created_id ) ) {
                        $user_id = $created_id;
                        $results['users']['created']++;
                    } else {
                        $results['users']['failed']++;
                        continue;
                    }
                }

                $user_login_to_id[ $login ] = $user_id;

                // Restaurar avatar si viene en el bundle
                if ( ! empty( $u_data['media_path'] ) ) {
                    $new_attach_id = self::import_media_attachment( $u_data['media_path'], $extract_dir, "Avatar de {$login}" );
                    if ( $new_attach_id > 0 ) {
                        // Sincronizar en usermeta para Aura Suite y plugins de avatar
                        update_user_meta( $user_id, 'aura_avatar_id', $new_attach_id );
                        update_user_meta( $user_id, 'wp_user_avatar', $new_attach_id );
                        update_user_meta( $user_id, 'aura_avatar_url', wp_get_attachment_url( $new_attach_id ) );

                        $full_path = get_attached_file( $new_attach_id );
                        $full_url  = wp_get_attachment_url( $new_attach_id );
                        $sla_meta  = [
                            'media_id' => $new_attach_id,
                            'full'     => $full_url,
                            'file'     => $full_path,
                        ];
                        update_user_meta( $user_id, 'simple_local_avatar', $sla_meta );
                        $results['users']['images']++;
                    }
                }
            }
        }

        // ── 2. Procesar Áreas ─────────────────────────────────────────────
        if ( $import_areas_opt && ! empty( $manifest['areas'] ) ) {
            global $wpdb;
            $areas_table = $wpdb->prefix . 'aura_areas';
            Aura_Areas_Setup::ensure_table_exists();

            foreach ( $manifest['areas'] as $a_data ) {
                $slug = sanitize_title( $a_data['slug'] ?? $a_data['name'] ?? '' );
                $name = sanitize_text_field( $a_data['name'] ?? '' );
                if ( empty( $name ) || empty( $slug ) ) {
                    continue;
                }

                // Importar logo del área
                $new_logo_id = null;
                if ( ! empty( $a_data['media_path'] ) ) {
                    $new_logo_id = self::import_media_attachment( $a_data['media_path'], $extract_dir, "Logo {$name}" );
                    if ( $new_logo_id > 0 ) {
                        $results['areas']['images']++;
                    } else {
                        $new_logo_id = null;
                    }
                }

                // Resolver responsable
                $resp_id = null;
                if ( ! empty( $a_data['responsible_login'] ) && isset( $user_login_to_id[ $a_data['responsible_login'] ] ) ) {
                    $resp_id = $user_login_to_id[ $a_data['responsible_login'] ];
                } elseif ( ! empty( $a_data['responsible_user_id'] ) ) {
                    $resp_id = (int) $a_data['responsible_user_id'];
                }

                $area_row_data = [
                    'name'                => $name,
                    'slug'                => $slug,
                    'type'                => sanitize_key( $a_data['type'] ?? 'program' ),
                    'description'         => sanitize_textarea_field( $a_data['description'] ?? '' ),
                    'color'               => sanitize_hex_color( $a_data['color'] ?? '#2271b1' ) ?: '#2271b1',
                    'icon'                => sanitize_text_field( $a_data['icon'] ?? 'dashicons-groups' ),
                    'status'              => in_array( $a_data['status'] ?? 'active', [ 'active', 'archived' ], true ) ? $a_data['status'] : 'active',
                    'sort_order'          => (int) ( $a_data['sort_order'] ?? 0 ),
                    'responsible_user_id' => $resp_id,
                ];

                if ( $new_logo_id ) {
                    $area_row_data['logo_id'] = $new_logo_id;
                }

                $existing_area_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$areas_table} WHERE slug = %s LIMIT 1", $slug ) );

                if ( $existing_area_id ) {
                    $area_id = (int) $existing_area_id;
                    $wpdb->update( $areas_table, $area_row_data, [ 'id' => $area_id ] );
                    $results['areas']['updated']++;
                } else {
                    $area_row_data['created_by'] = get_current_user_id() ?: 1;
                    $area_row_data['created_at'] = current_time( 'mysql' );
                    $wpdb->insert( $areas_table, $area_row_data );
                    $area_id = (int) $wpdb->insert_id;
                    $results['areas']['created']++;
                }

                $area_slug_to_id[ $slug ] = $area_id;

                // Asignar usuarios al área si la tabla relacional existe
                if ( ! empty( $a_data['assigned_logins'] ) && is_array( $a_data['assigned_logins'] ) ) {
                    $rel_table = $wpdb->prefix . 'aura_area_users';
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$rel_table}'" ) === $rel_table ) {
                        foreach ( $a_data['assigned_logins'] as $assigned ) {
                            $u_login = $assigned['login'] ?? '';
                            $u_id = $user_login_to_id[ $u_login ] ?? ( get_user_by( 'login', $u_login ) ? get_user_by( 'login', $u_login )->ID : 0 );
                            if ( $u_id > 0 ) {
                                $wpdb->replace( $rel_table, [
                                    'area_id'     => $area_id,
                                    'user_id'     => $u_id,
                                    'role'        => sanitize_key( $assigned['role'] ?? 'responsible' ),
                                    'assigned_at' => current_time( 'mysql' ),
                                    'assigned_by' => get_current_user_id() ?: 1,
                                ] );
                            }
                        }
                    }
                }
            }
        }

        // ── 3. Procesar Terceros ──────────────────────────────────────────
        if ( $import_tp_opt && ! empty( $manifest['third_parties'] ) ) {
            global $wpdb;
            $tp_table = $wpdb->prefix . 'aura_finance_third_parties';
            Aura_Third_Parties::ensure_table();

            foreach ( $manifest['third_parties'] as $tp_data ) {
                $full_name   = sanitize_text_field( $tp_data['full_name'] ?? '' );
                $document_id = sanitize_text_field( $tp_data['document_id'] ?? '' );

                if ( empty( $full_name ) ) {
                    continue;
                }

                // Importar imagen de logo
                $new_logo_id = null;
                if ( ! empty( $tp_data['media_path'] ) ) {
                    $new_logo_id = self::import_media_attachment( $tp_data['media_path'], $extract_dir, "Logo {$full_name}" );
                    if ( $new_logo_id > 0 ) {
                        $results['third_parties']['images']++;
                    } else {
                        $new_logo_id = null;
                    }
                }

                // Resolver usuario WP vinculado
                $linked_user_id = null;
                if ( ! empty( $tp_data['linked_login'] ) && isset( $user_login_to_id[ $tp_data['linked_login'] ] ) ) {
                    $linked_user_id = $user_login_to_id[ $tp_data['linked_login'] ];
                } elseif ( ! empty( $tp_data['email'] ) ) {
                    $u = get_user_by( 'email', sanitize_email( $tp_data['email'] ) );
                    if ( $u ) {
                        $linked_user_id = $u->ID;
                    }
                }

                $tp_row = [
                    'full_name'       => $full_name,
                    'commercial_name' => sanitize_text_field( $tp_data['commercial_name'] ?? '' ),
                    'party_type'      => sanitize_key( $tp_data['party_type'] ?? 'company' ),
                    'accounting_role' => sanitize_key( $tp_data['accounting_role'] ?? 'supplier' ),
                    'tax_id_type'     => strtoupper( sanitize_text_field( $tp_data['tax_id_type'] ?? 'NIT' ) ) ?: 'NIT',
                    'document_id'     => $document_id,
                    'phone'           => sanitize_text_field( $tp_data['phone'] ?? '' ),
                    'email'           => sanitize_email( $tp_data['email'] ?? '' ),
                    'website'         => esc_url_raw( $tp_data['website'] ?? '' ),
                    'address'         => sanitize_textarea_field( $tp_data['address'] ?? '' ),
                    'notes'           => sanitize_textarea_field( $tp_data['notes'] ?? '' ),
                    'is_active'       => (int) ( $tp_data['is_active'] ?? 1 ),
                    'wp_user_id'      => $linked_user_id,
                ];

                if ( $new_logo_id ) {
                    $tp_row['logo_id'] = $new_logo_id;
                }

                // Buscar si ya existe por document_id o full_name
                $existing_tp_id = null;
                if ( ! empty( $document_id ) ) {
                    $existing_tp_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tp_table} WHERE document_id = %s LIMIT 1", $document_id ) );
                }
                if ( ! $existing_tp_id ) {
                    $existing_tp_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tp_table} WHERE full_name = %s LIMIT 1", $full_name ) );
                }

                if ( $existing_tp_id ) {
                    $tp_row['updated_at'] = current_time( 'mysql' );
                    $wpdb->update( $tp_table, $tp_row, [ 'id' => (int) $existing_tp_id ] );
                    $results['third_parties']['updated']++;
                    $saved_tp_id = (int) $existing_tp_id;
                } else {
                    $tp_row['created_by'] = get_current_user_id() ?: 1;
                    $tp_row['created_at'] = current_time( 'mysql' );
                    $tp_row['updated_at'] = current_time( 'mysql' );
                    $wpdb->insert( $tp_table, $tp_row );
                    $saved_tp_id = (int) $wpdb->insert_id;
                    $results['third_parties']['created']++;
                }

                // Sincronizar avatar con el usuario WP vinculado si existe
                if ( $linked_user_id && $new_logo_id && class_exists( 'Aura_Third_Parties' ) ) {
                    Aura_Third_Parties::sync_avatar_to_wp_user( $linked_user_id, $new_logo_id );
                }
            }
        }

        // Limpieza de carpeta temporal
        self::delete_dir( $extract_dir );
        delete_transient( 'aura_bundle_' . $token );

        wp_send_json_success( [
            'results' => $results,
            'message' => __( 'La importación del paquete multimedia se completó con éxito.', 'aura-suite' ),
        ] );
    }

    /**
     * =========================================================================
     * FUNCIÓN AUXILIAR: Inserción de imagen en la Biblioteca de Medios
     * =========================================================================
     */
    private static function import_media_attachment( $rel_path, $extract_dir, $title = '' ) {
        $source_file = $extract_dir . '/' . ltrim( $rel_path, '/\\' );
        if ( ! file_exists( $source_file ) ) {
            return 0;
        }

        $upload_dir = wp_upload_dir();
        $filename   = wp_basename( $source_file );
        $unique_fn  = wp_unique_filename( $upload_dir['path'], $filename );
        $dest_file  = $upload_dir['path'] . '/' . $unique_fn;

        if ( ! @copy( $source_file, $dest_file ) ) {
            return 0;
        }

        $wp_filetype = wp_check_filetype( $dest_file, null );
        $attachment = [
            'post_mime_type' => $wp_filetype['type'] ?: 'image/jpeg',
            'post_title'     => $title ?: sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $upload_dir['url'] . '/' . $unique_fn,
        ];

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attach_id = wp_insert_attachment( $attachment, $dest_file );
        if ( ! is_wp_error( $attach_id ) && $attach_id > 0 ) {
            $attach_data = wp_generate_attachment_metadata( $attach_id, $dest_file );
            wp_update_attachment_metadata( $attach_id, $attach_data );
            return $attach_id;
        }

        return 0;
    }

    /**
     * Eliminar directorio recursivamente
     */
    private static function delete_dir( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }
        $items = scandir( $dir );
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) {
                continue;
            }
            $path = $dir . '/' . $item;
            if ( is_dir( $path ) ) {
                self::delete_dir( $path );
            } else {
                @unlink( $path );
            }
        }
        @rmdir( $dir );
    }
}
