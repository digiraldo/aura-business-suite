<?php
/**
 * Orquestador de Sincronización Bidireccional para Aura Contacts
 *
 * Coordina la sincronización entre WordPress (fuente de verdad interna) y Google Contacts:
 * - Push: Envía altas, modificaciones y archivados locales hacia Google People API.
 * - Pull: Consulta cambios remotos incrementales usando syncToken.
 * - Anti-conflictos: Resolución basada en marcas de tiempo y protección de modificaciones pendientes.
 * - Cron: Ejecución desatendida periódica con log de auditoría.
 *
 * @package    Aura_Business_Suite
 * @subpackage Contacts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-google-contacts.php';
require_once __DIR__ . '/class-contacts.php';
require_once __DIR__ . '/class-contacts-setup.php';

class Aura_Contacts_Sync {

    const OPTION_SYNC_TOKEN     = 'aura_contacts_google_sync_token';
    const OPTION_LAST_SYNC_TIME = 'aura_contacts_last_sync_time';
    const OPTION_SYNC_CATEGORIES = 'aura_contacts_sync_categories';
    const OPTION_CRON_INTERVAL  = 'aura_contacts_cron_interval';

    const CRON_HOOK             = 'aura_contacts_sync_cron';

    /**
     * Inicializa los hooks de cron y endpoints AJAX de sincronización.
     */
    public static function init(): void {
        // Cron de WordPress
        add_action( self::CRON_HOOK, array( __CLASS__, 'cron_sync' ) );
        self::ensure_cron_schedule();

        // Endpoints AJAX
        add_action( 'wp_ajax_aura_contacts_sync_all', array( __CLASS__, 'ajax_sync_all' ) );
        add_action( 'wp_ajax_aura_contacts_sync_single', array( __CLASS__, 'ajax_sync_single' ) );
        add_action( 'wp_ajax_aura_contacts_get_sync_logs', array( __CLASS__, 'ajax_get_sync_logs' ) );
    }

    /**
     * Asegura que el evento recurrente de cron esté activo si Google está conectado.
     */
    public static function ensure_cron_schedule(): void {
        $provider = Aura_Google_Auth_Factory::get_provider();
        if ( ! $provider->is_connected() ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
            return;
        }

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            $interval = get_option( self::OPTION_CRON_INTERVAL, 'hourly' );
            wp_schedule_event( time() + 300, $interval, self::CRON_HOOK );
        }
    }

    /**
     * Callback invocado por el cron de WordPress.
     */
    public static function cron_sync(): void {
        $provider = Aura_Google_Auth_Factory::get_provider();
        if ( ! $provider->is_connected() ) {
            return;
        }

        self::sync_all( 'both' );
    }

    // ─────────────────────────────────────────────────────────────
    // MOTOR DE SINCRONIZACIÓN (PUSH / PULL)
    // ─────────────────────────────────────────────────────────────

    /**
     * Ejecuta el ciclo completo de sincronización.
     *
     * @param string $direction 'both'|'push'|'pull'
     * @return array Resumen de la operación
     */
    public static function sync_all( string $direction = 'both' ): array {
        $provider = Aura_Google_Auth_Factory::get_provider();
        if ( ! $provider->is_connected() ) {
            return array(
                'success' => false,
                'message' => __( 'Google Contacts no está conectado.', 'aura-suite' ),
            );
        }

        $summary = array(
            'pushed_success' => 0,
            'pushed_errors'  => 0,
            'pulled_created' => 0,
            'pulled_updated' => 0,
            'pulled_deleted' => 0,
            'pulled_errors'  => 0,
            'errors'         => array(),
        );

        // 1. PUSH: Enviar cambios locales pendientes hacia Google
        if ( in_array( $direction, array( 'both', 'push' ), true ) ) {
            $push_res = self::push_pending_contacts( 50 );
            $summary['pushed_success'] = $push_res['success'];
            $summary['pushed_errors']  = $push_res['errors'];
            if ( ! empty( $push_res['error_messages'] ) ) {
                $summary['errors'] = array_merge( $summary['errors'], $push_res['error_messages'] );
            }
        }

        // 2. PULL: Traer cambios desde Google People API
        if ( in_array( $direction, array( 'both', 'pull' ), true ) ) {
            $pull_res = self::pull_changes();
            $summary['pulled_created'] = $pull_res['created'];
            $summary['pulled_updated'] = $pull_res['updated'];
            $summary['pulled_deleted'] = $pull_res['deleted'];
            $summary['pulled_errors']  = $pull_res['errors'];
            if ( ! empty( $pull_res['error_messages'] ) ) {
                $summary['errors'] = array_merge( $summary['errors'], $pull_res['error_messages'] );
            }
        }

        update_option( self::OPTION_LAST_SYNC_TIME, current_time( 'mysql' ) );

        return array(
            'success'   => empty( $summary['errors'] ),
            'summary'   => $summary,
            'timestamp' => get_option( self::OPTION_LAST_SYNC_TIME ),
        );
    }

    /**
     * Envía todos los contactos con estado 'pending' a Google en lotes.
     *
     * @param int $limit
     * @return array
     */
    public static function push_pending_contacts( int $limit = 50 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $pending_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE sync_status = 'pending' ORDER BY updated_at ASC LIMIT %d",
                $limit
            )
        );

        $success_count = 0;
        $error_count   = 0;
        $error_msgs    = array();

        foreach ( (array) $pending_ids as $cid ) {
            $res = self::push_contact( (int) $cid );
            if ( $res['success'] ) {
                $success_count++;
            } else {
                $error_count++;
                if ( ! empty( $res['message'] ) ) {
                    $error_msgs[] = sprintf( '#%d: %s', $cid, $res['message'] );
                }
            }
        }

        return array(
            'success'        => $success_count,
            'errors'         => $error_count,
            'error_messages' => $error_msgs,
        );
    }

    /**
     * Sincroniza un contacto individual desde WordPress hacia Google Contacts (PUSH).
     *
     * @param int $contact_id
     * @return array { success: bool, message: string, action: string }
     */
    public static function push_contact( int $contact_id ): array {
        global $wpdb;
        $t_contacts   = $wpdb->prefix . 'aura_contacts';
        $t_google_map = $wpdb->prefix . 'aura_contacts_google_map';

        $contact = Aura_Contacts::get_contact( $contact_id );
        if ( ! $contact ) {
            return array( 'success' => false, 'message' => 'Contacto no encontrado' );
        }

        // Formatear contacto para resolver avatares heredados de WP User, Terceros o Gravatar si no vienen directos
        $formatted = Aura_Contacts::format_contact_row( $contact );
        if ( empty( $contact['avatar_url'] ) && ! empty( $formatted['avatar_url'] ) ) {
            $contact['avatar_url'] = $formatted['avatar_url'];
            $wpdb->update( $t_contacts, array( 'avatar_url' => $contact['avatar_url'] ), array( 'id' => $contact_id ) );
        }

        $provider       = Aura_Google_Auth_Factory::get_provider();
        $google_account = $provider->get_account_email();

        // Buscar mapeo previo para esta cuenta de Google específica
        $map = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$t_google_map} WHERE contact_id = %d AND google_account = %s LIMIT 1",
                $contact_id,
                $google_account
            ),
            ARRAY_A
        );

        // Caso A: Contacto archivado o desactivado (is_active = 0)
        if ( 0 === (int) $contact['is_active'] ) {
            if ( $map && ! empty( $map['resource_name'] ) ) {
                $del_res = Aura_Google_Contacts::delete_person( $map['resource_name'] );
                if ( is_wp_error( $del_res ) ) {
                    Aura_Contacts_Setup::log_sync( $contact_id, $google_account, 'push', 'delete', 'error', $del_res->get_error_message() );
                    return array( 'success' => false, 'message' => $del_res->get_error_message() );
                }
                $wpdb->delete( $t_google_map, array( 'id' => $map['id'] ), array( '%d' ) );
                Aura_Contacts_Setup::log_sync( $contact_id, $google_account, 'push', 'delete', 'success', 'Eliminado de Google Contacts tras archivarse' );
            }
            $wpdb->update( $t_contacts, array( 'sync_status' => 'local_only', 'sync_error' => null ), array( 'id' => $contact_id ) );
            return array( 'success' => true, 'message' => 'Archivado y retirado de Google', 'action' => 'delete' );
        }

        // Caso B: Actualizar contacto existente en Google
        if ( $map && ! empty( $map['resource_name'] ) ) {
            $payload = Aura_Google_Contacts::map_contact_to_google( $contact, $map['google_etag'] );
            $res     = Aura_Google_Contacts::update_person( $map['resource_name'], $payload );

            if ( is_wp_error( $res ) ) {
                // Si el contacto ya no existe en Google (404), recrearlo
                $err_data = $res->get_error_data();
                if ( isset( $err_data['status'] ) && 404 === $err_data['status'] ) {
                    $wpdb->delete( $t_google_map, array( 'id' => $map['id'] ), array( '%d' ) );
                    $map = null; // Pasará al Caso C de creación
                } else {
                    $err_msg = $res->get_error_message();
                    $wpdb->update( $t_contacts, array( 'sync_status' => 'error', 'sync_error' => $err_msg ), array( 'id' => $contact_id ) );
                    Aura_Contacts_Setup::log_sync( $contact_id, $google_account, 'push', 'update', 'error', $err_msg );
                    return array( 'success' => false, 'message' => $err_msg );
                }
            } else {
                $new_etag = $res['etag'] ?? $map['google_etag'];

                // Sincronizar foto de perfil si el contacto tiene avatar_url
                if ( ! empty( $contact['avatar_url'] ) ) {
                    $photo_res = self::sync_contact_photo( $map['resource_name'], $contact['avatar_url'], $contact_id );
                    if ( is_array( $photo_res ) && ! empty( $photo_res['person']['etag'] ) ) {
                        $new_etag = $photo_res['person']['etag'];
                    }
                }

                // Sincronizar etiqueta/grupo en español según categoría si está disponible
                if ( ! empty( $contact['category'] ) ) {
                    $group_id = Aura_Google_Contacts::ensure_category_group( $contact['category'] );
                    if ( $group_id ) {
                        Aura_Google_Contacts::add_to_group( $group_id, array( $map['resource_name'] ) );
                    }
                }

                $wpdb->update(
                    $t_google_map,
                    array(
                        'google_etag'    => $new_etag,
                        'last_synced_at' => current_time( 'mysql' ),
                    ),
                    array( 'id' => $map['id'] )
                );
                $wpdb->update( $t_contacts, array( 'sync_status' => 'synced', 'sync_error' => null ), array( 'id' => $contact_id ) );
                Aura_Contacts_Setup::log_sync( $contact_id, $google_account, 'push', 'update', 'success', 'Actualizado en Google Contacts' );
                return array( 'success' => true, 'message' => 'Actualizado en Google', 'action' => 'update' );
            }
        }

        // Caso C: Crear nuevo contacto en Google
        $payload = Aura_Google_Contacts::map_contact_to_google( $contact );
        $created = Aura_Google_Contacts::create_person( $payload );

        if ( is_wp_error( $created ) ) {
            $err_msg = $created->get_error_message();
            $wpdb->update( $t_contacts, array( 'sync_status' => 'error', 'sync_error' => $err_msg ), array( 'id' => $contact_id ) );
            Aura_Contacts_Setup::log_sync( $contact_id, $google_account, 'push', 'create', 'error', $err_msg );
            return array( 'success' => false, 'message' => $err_msg );
        }

        $resource_name = $created['resourceName'] ?? '';
        $etag          = $created['etag'] ?? '';

        if ( ! empty( $resource_name ) ) {
            // Sincronizar foto de perfil si el contacto tiene avatar_url
            if ( ! empty( $contact['avatar_url'] ) ) {
                $photo_res = self::sync_contact_photo( $resource_name, $contact['avatar_url'], $contact_id );
                if ( is_array( $photo_res ) && ! empty( $photo_res['person']['etag'] ) ) {
                    $etag = $photo_res['person']['etag'];
                }
            }

            $wpdb->insert(
                $t_google_map,
                array(
                    'contact_id'     => $contact_id,
                    'google_account' => $google_account,
                    'resource_name'  => $resource_name,
                    'google_etag'    => $etag,
                    'last_synced_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s', '%s' )
            );

            // Asignar etiqueta/grupo según categoría si está disponible
            if ( ! empty( $contact['category'] ) ) {
                $group_id = Aura_Google_Contacts::ensure_category_group( $contact['category'] );
                if ( $group_id ) {
                    Aura_Google_Contacts::add_to_group( $group_id, array( $resource_name ) );
                }
            }

            $wpdb->update( $t_contacts, array( 'sync_status' => 'synced', 'sync_error' => null ), array( 'id' => $contact_id ) );
            Aura_Contacts_Setup::log_sync( $contact_id, $google_account, 'push', 'create', 'success', 'Creado en Google Contacts: ' . $resource_name );

            return array( 'success' => true, 'message' => 'Creado en Google', 'action' => 'create' );
        }

        return array( 'success' => false, 'message' => 'Respuesta incompleta de Google' );
    }

    /**
     * Trae cambios desde Google Contacts hacia WordPress usando syncToken incremental (PULL).
     *
     * @return array { created: int, updated: int, deleted: int, errors: int, error_messages: array }
     */
    public static function pull_changes(): array {
        global $wpdb;
        $t_contacts   = $wpdb->prefix . 'aura_contacts';
        $t_google_map = $wpdb->prefix . 'aura_contacts_google_map';

        $provider       = Aura_Google_Auth_Factory::get_provider();
        $google_account = $provider->get_account_email();

        $sync_token = get_option( self::OPTION_SYNC_TOKEN, null );

        $connections_res = Aura_Google_Contacts::list_connections( $sync_token, 200 );

        // Si el syncToken expiró (Google responde 410 Gone / Sync token is expired)
        if ( is_wp_error( $connections_res ) ) {
            $data = $connections_res->get_error_data();
            if ( isset( $data['status'] ) && ( 410 === $data['status'] || 400 === $data['status'] ) ) {
                delete_option( self::OPTION_SYNC_TOKEN );
                $connections_res = Aura_Google_Contacts::list_connections( null, 200 );
            }
        }

        if ( is_wp_error( $connections_res ) ) {
            return array(
                'created'        => 0,
                'updated'        => 0,
                'deleted'        => 0,
                'errors'         => 1,
                'error_messages' => array( $connections_res->get_error_message() ),
            );
        }

        $connections    = $connections_res['connections'] ?? array();
        $next_sync_tok  = $connections_res['nextSyncToken'] ?? null;

        $created_count = 0;
        $updated_count = 0;
        $deleted_count = 0;
        $error_count   = 0;
        $error_msgs    = array();

        foreach ( $connections as $person ) {
            $resource_name = $person['resourceName'] ?? '';
            if ( empty( $resource_name ) ) {
                continue;
            }

            $is_deleted = ! empty( $person['metadata']['deleted'] );

            // Buscar mapeo previo
            $map = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$t_google_map} WHERE resource_name = %s AND google_account = %s LIMIT 1",
                    $resource_name,
                    $google_account
                ),
                ARRAY_A
            );

            // Manejo de eliminación en Google
            if ( $is_deleted ) {
                if ( $map ) {
                    $cid = (int) $map['contact_id'];
                    // Archivado suave local
                    $wpdb->update(
                        $t_contacts,
                        array(
                            'is_active'   => 0,
                            'sync_status' => 'local_only',
                            'updated_at'  => current_time( 'mysql' ),
                        ),
                        array( 'id' => $cid )
                    );
                    $wpdb->delete( $t_google_map, array( 'id' => $map['id'] ) );
                    Aura_Contacts_Setup::log_sync( $cid, $google_account, 'pull', 'delete', 'success', 'Archivado localmente al eliminarse en Google' );
                    $deleted_count++;
                }
                continue;
            }

            // Contacto activo en Google: mapear a formato Aura
            $mapped = Aura_Google_Contacts::map_google_to_contact( $person );

            // Si ya existe en el mapa: Actualizar
            if ( $map ) {
                $cid = (int) $map['contact_id'];
                $existing = Aura_Contacts::get_contact( $cid );

                // Si el contacto local tiene cambios pendientes, resolver conflicto (gana el más reciente)
                if ( $existing && 'pending' === $existing['sync_status'] ) {
                    // Si el cambio local fue muy reciente, conservamos el local
                    continue;
                }

                $update_fields = array(
                    'first_name'      => $mapped['first_name'] ?: ( $existing['first_name'] ?? '' ),
                    'last_name'       => $mapped['last_name'] ?: ( $existing['last_name'] ?? '' ),
                    'organization'    => $mapped['organization'] ?: ( $existing['organization'] ?? null ),
                    'job_title'       => $mapped['job_title'] ?: ( $existing['job_title'] ?? null ),
                    'email_primary'   => $mapped['email_primary'] ?: ( $existing['email_primary'] ?? null ),
                    'email_secondary' => $mapped['email_secondary'] ?: ( $existing['email_secondary'] ?? null ),
                    'phone_primary'   => $mapped['phone_primary'] ?: ( $existing['phone_primary'] ?? null ),
                    'phone_secondary' => $mapped['phone_secondary'] ?: ( $existing['phone_secondary'] ?? null ),
                    'notes'           => $mapped['notes'] ?: ( $existing['notes'] ?? null ),
                    'sync_status'     => 'synced',
                    'sync_error'      => null,
                    'updated_at'      => current_time( 'mysql' ),
                );

                $wpdb->update( $t_contacts, $update_fields, array( 'id' => $cid ) );
                $wpdb->update(
                    $t_google_map,
                    array(
                        'google_etag'    => $mapped['google_etag'],
                        'last_synced_at' => current_time( 'mysql' ),
                    ),
                    array( 'id' => $map['id'] )
                );

                Aura_Contacts_Setup::log_sync( $cid, $google_account, 'pull', 'update', 'success', 'Actualizado desde Google Contacts' );
                $updated_count++;
                continue;
            }

            // Si no está en el mapa: buscar si coincide con un contacto local por email
            $matched_contact = null;
            if ( ! empty( $mapped['email_primary'] ) ) {
                $matched_contact = Aura_Contacts::get_contact_by_email( $mapped['email_primary'] );
            }

            if ( $matched_contact ) {
                // Vincular al contacto local existente
                $cid = (int) $matched_contact['id'];
                $wpdb->insert(
                    $t_google_map,
                    array(
                        'contact_id'     => $cid,
                        'google_account' => $google_account,
                        'resource_name'  => $mapped['resource_name'],
                        'google_etag'    => $mapped['google_etag'],
                        'last_synced_at' => current_time( 'mysql' ),
                    )
                );
                $wpdb->update( $t_contacts, array( 'sync_status' => 'synced', 'sync_error' => null ), array( 'id' => $cid ) );
                Aura_Contacts_Setup::log_sync( $cid, $google_account, 'pull', 'update', 'success', 'Vinculado a contacto existente por email' );
                $updated_count++;
            } else {
                // Crear nuevo contacto en WordPress importado de Google
                $new_id = Aura_Contacts::create_contact( array(
                    'first_name'      => $mapped['first_name'],
                    'last_name'       => $mapped['last_name'],
                    'organization'    => $mapped['organization'],
                    'job_title'       => $mapped['job_title'],
                    'email_primary'   => $mapped['email_primary'],
                    'email_secondary' => $mapped['email_secondary'],
                    'phone_primary'   => $mapped['phone_primary'],
                    'phone_secondary' => $mapped['phone_secondary'],
                    'address_street'  => $mapped['address_street'],
                    'address_city'    => $mapped['address_city'],
                    'address_state'   => $mapped['address_state'],
                    'address_postal'  => $mapped['address_postal'],
                    'address_country' => $mapped['address_country'],
                    'category'        => $mapped['category'] ?: 'General',
                    'contact_type'    => $mapped['contact_type'] ?: 'person',
                    'notes'           => $mapped['notes'],
                    'avatar_url'      => $mapped['avatar_url'],
                ) );

                if ( ! is_wp_error( $new_id ) ) {
                    $wpdb->insert(
                        $t_google_map,
                        array(
                            'contact_id'     => $new_id,
                            'google_account' => $google_account,
                            'resource_name'  => $mapped['resource_name'],
                            'google_etag'    => $mapped['google_etag'],
                            'last_synced_at' => current_time( 'mysql' ),
                        )
                    );
                    $wpdb->update( $t_contacts, array( 'sync_status' => 'synced', 'sync_error' => null ), array( 'id' => $new_id ) );
                    Aura_Contacts_Setup::log_sync( $new_id, $google_account, 'pull', 'create', 'success', 'Importado desde Google Contacts' );
                    $created_count++;
                } else {
                    $error_count++;
                    $error_msgs[] = $new_id->get_error_message();
                }
            }
        }

        if ( ! empty( $next_sync_tok ) ) {
            update_option( self::OPTION_SYNC_TOKEN, $next_sync_tok );
        }

        return array(
            'created'        => $created_count,
            'updated'        => $updated_count,
            'deleted'        => $deleted_count,
            'errors'         => $error_count,
            'error_messages' => $error_msgs,
        );
    }

    /**
     * Obtiene los registros recientes del log de auditoría.
     *
     * @param int $limit
     * @return array
     */
    public static function get_recent_logs( int $limit = 50, string $status = '' ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts_sync_log';

        $where = '';
        if ( in_array( $status, array( 'error', 'success' ), true ) ) {
            $where = $wpdb->prepare( "WHERE l.status = %s", $status );
        }

        $limit_val = min( 200, max( 5, $limit ) );
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, c.first_name, c.last_name, c.organization, c.email_primary 
                 FROM {$table} l
                 LEFT JOIN {$wpdb->prefix}aura_contacts c ON (l.contact_id = c.id)
                 {$where}
                 ORDER BY l.id DESC LIMIT %d",
                $limit_val
            ),
            ARRAY_A
        );

        return $logs ?: array();
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX ENDPOINTS
    // ─────────────────────────────────────────────────────────────

    /**
     * AJAX: Disparar sincronización bidireccional completa.
     */
    public static function ajax_sync_all(): void {
        check_ajax_referer( Aura_Contacts::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_sync_google' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos para sincronizar contactos.', 'aura-suite' ) ) );
        }

        $direction = sanitize_key( $_POST['direction'] ?? 'both' );
        $result    = self::sync_all( $direction );

        if ( ! $result['success'] ) {
            wp_send_json_error( array(
                'message' => __( 'La sincronización terminó con advertencias o errores.', 'aura-suite' ),
                'details' => $result,
            ) );
        }

        wp_send_json_success( array(
            'message' => __( '¡Sincronización completada con éxito!', 'aura-suite' ),
            'details' => $result,
        ) );
    }

    /**
     * AJAX: Sincronizar un único contacto hacia Google (push).
     */
    public static function ajax_sync_single(): void {
        check_ajax_referer( Aura_Contacts::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_sync_google' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'aura-suite' ) ) );
        }

        $contact_id = intval( $_POST['contact_id'] ?? 0 );
        $result     = self::push_contact( $contact_id );

        if ( ! $result['success'] ) {
            wp_send_json_error( array( 'message' => $result['message'] ) );
        }

        wp_send_json_success( array(
            'message' => sprintf( __( 'Contacto sincronizado con Google (%s).', 'aura-suite' ), $result['action'] ),
            'contact' => Aura_Contacts::get_contact( $contact_id ),
        ) );
    }

    /**
     * AJAX: Obtener logs de auditoría de sincronización.
     */
    public static function ajax_get_sync_logs(): void {
        check_ajax_referer( Aura_Contacts::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'aura-suite' ) ) );
        }

        $limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 50;
        $status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
        $logs   = self::get_recent_logs( $limit, $status );
        wp_send_json_success( array( 'logs' => $logs ) );
    }

    // ─────────────────────────────────────────────────────────────
    // SINCRONIZACIÓN DE FOTOS DE CONTACTO (Google People API)
    // ─────────────────────────────────────────────────────────────

    /**
     * Sincroniza la foto de perfil hacia Google Contacts mediante people.updateContactPhoto.
     *
     * @param string      $resource_name (ej: 'people/c123456789')
     * @param string|null $avatar_url    URL local o remota de la imagen
     * @param int         $contact_id    ID del contacto en Aura
     * @return array|bool|WP_Error
     */
    public static function sync_contact_photo( string $resource_name, ?string $avatar_url, int $contact_id = 0 ) {
        if ( empty( $avatar_url ) || empty( $resource_name ) ) {
            return false;
        }

        $avatar_url = trim( $avatar_url );
        // Limpieza de prefijos de URL residuales
        if ( false !== strpos( $avatar_url, '/:/' ) ) {
            $avatar_url = str_replace( '/:/', '/', $avatar_url );
        }
        if ( false !== strpos( $avatar_url, ':/wp-content' ) ) {
            $avatar_url = str_replace( ':/wp-content', site_url( '/wp-content' ), $avatar_url );
        }

        // Si es un Gravatar por defecto genérico sin avatar real registrado en gravatar.com,
        // no enviarlo a Google para evitar pisar la foto con siluetas genéricas.
        if ( false !== strpos( $avatar_url, 'gravatar.com' ) ) {
            $test_url = add_query_arg( 'd', '404', $avatar_url );
            $check    = wp_safe_remote_head( $test_url, array( 'timeout' => 5, 'sslverify' => false ) );
            if ( ! is_wp_error( $check ) && 404 === (int) wp_remote_retrieve_response_code( $check ) ) {
                return false; // Gravatar confirmó que no hay imagen real
            }
        }

        // Optimización de caché: evitar llamadas redundantes a Google People API si la foto no ha cambiado
        $hash      = md5( (string) $avatar_url );
        $cache_key = 'aura_gc_photo_' . $contact_id;
        if ( $contact_id > 0 && get_transient( $cache_key ) === $hash ) {
            return true;
        }

        $image_bytes = self::get_image_bytes( $avatar_url );
        if ( empty( $image_bytes ) ) {
            return false;
        }

        // Optimizar/redimensionar la imagen para Google Contacts (JPEG máx 720x720)
        $optimized   = self::optimize_image_for_google( $image_bytes );
        $final_bytes = $optimized ?: $image_bytes;

        $photo_base64 = base64_encode( $final_bytes );
        if ( empty( $photo_base64 ) ) {
            return false;
        }

        $response = Aura_Google_Contacts::update_contact_photo( $resource_name, $photo_base64 );

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( '[Aura Google Contacts Photo] Error para %s (#%d): %s', $resource_name, $contact_id, $response->get_error_message() ) );
            }
            return $response;
        }

        if ( $contact_id > 0 ) {
            set_transient( $cache_key, $hash, 30 * DAY_IN_SECONDS );
        }

        return $response;
    }

    /**
     * Resuelve los bytes binarios de una imagen a partir de su URL (local o remota).
     *
     * @param string $url
     * @return string|null
     */
    public static function get_image_bytes( string $url ): ?string {
        $url = trim( $url );
        if ( empty( $url ) ) {
            return null;
        }

        // 1. Resolver ruta directa si está dentro de wp-content/uploads
        $upload_dir = wp_upload_dir();
        $base_url   = $upload_dir['baseurl'];
        $base_dir   = $upload_dir['basedir'];

        if ( 0 === strpos( $url, $base_url ) ) {
            $rel_path  = substr( $url, strlen( $base_url ) );
            $file_path = $base_dir . $rel_path;
            if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
                $content = file_get_contents( $file_path );
                if ( false !== $content && strlen( $content ) > 0 ) {
                    return $content;
                }
            }
        } elseif ( false !== strpos( $url, '/wp-content/uploads/' ) ) {
            $rel_sub   = substr( $url, strpos( $url, '/wp-content/uploads/' ) + strlen( '/wp-content/uploads/' ) );
            $file_path = $base_dir . '/' . ltrim( $rel_sub, '/\\' );
            if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
                $content = file_get_contents( $file_path );
                if ( false !== $content && strlen( $content ) > 0 ) {
                    return $content;
                }
            }
        }

        // 2. Resolver por ID de adjunto de WordPress
        $attachment_id = attachment_url_to_postid( $url );
        if ( $attachment_id > 0 ) {
            $attached_file = get_attached_file( $attachment_id );
            if ( $attached_file && file_exists( $attached_file ) && is_readable( $attached_file ) ) {
                $content = file_get_contents( $attached_file );
                if ( false !== $content && strlen( $content ) > 0 ) {
                    return $content;
                }
            }
        }

        // 3. Si la URL es relativa al root (/wp-content/...)
        if ( 0 === strpos( $url, '/' ) && ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
            $doc_root  = rtrim( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ), '/\\' );
            $file_path = $doc_root . $url;
            if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
                $content = file_get_contents( $file_path );
                if ( false !== $content && strlen( $content ) > 0 ) {
                    return $content;
                }
            }
        }

        // 4. Fallback HTTP para Gravatar o URLs externas
        $response = wp_safe_remote_get( $url, array(
            'timeout'    => 15,
            'sslverify'  => false,
            'user-agent' => 'Aura-Suite/Contacts-Photo-Sync',
        ) );

        if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            if ( ! empty( $body ) ) {
                return $body;
            }
        }

        return null;
    }

    /**
     * Optimiza y redimensiona una imagen binaria para Google Contacts (JPEG máx 720x720).
     *
     * @param string $image_bytes
     * @return string|null
     */
    public static function optimize_image_for_google( string $image_bytes ): ?string {
        if ( empty( $image_bytes ) ) {
            return null;
        }

        if ( ! function_exists( 'wp_get_image_editor' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $temp_file = wp_tempnam( 'aura_gc_img_' );
        if ( ! $temp_file ) {
            return null;
        }

        file_put_contents( $temp_file, $image_bytes );

        $editor = wp_get_image_editor( $temp_file );
        if ( is_wp_error( $editor ) ) {
            @unlink( $temp_file );
            return null;
        }

        $size = $editor->get_size();
        $w    = $size['width'] ?? 0;
        $h    = $size['height'] ?? 0;

        if ( $w > 720 || $h > 720 ) {
            $editor->resize( 720, 720, false );
        }

        $dest_file = $temp_file . '.jpg';
        $saved     = $editor->save( $dest_file, 'image/jpeg' );

        @unlink( $temp_file );

        if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) && file_exists( $saved['path'] ) ) {
            $opt = file_get_contents( $saved['path'] );
            @unlink( $saved['path'] );
            return ( false !== $opt && strlen( $opt ) > 0 ) ? $opt : null;
        }

        return null;
    }
}
