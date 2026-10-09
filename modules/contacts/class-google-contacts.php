<?php
/**
 * Cliente de Google People API v1 para Aura Contacts
 *
 * Encapsula la comunicación HTTP con https://people.googleapis.com/v1:
 * - Creación, actualización y eliminación de contactos en Google.
 * - Sincronización incremental de conexiones mediante syncToken.
 * - Gestión de grupos / etiquetas de contactos (Contact Groups).
 * - Mapeo bidireccional entre la estructura local de WordPress y Google People API.
 *
 * @package    Aura_Business_Suite
 * @subpackage Contacts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/auth/class-google-auth-factory.php';

class Aura_Google_Contacts {

    const BASE_URL = 'https://people.googleapis.com/v1';

    const PERSON_FIELDS = 'names,emailAddresses,phoneNumbers,organizations,addresses,biographies,userDefined,metadata';

    private static ?string $last_error = null;

    /**
     * Obtiene el último mensaje de error registrado.
     *
     * @return string|null
     */
    public static function get_last_error(): ?string {
        return self::$last_error;
    }

    /**
     * Ejecuta una petición HTTP autenticada a la People API de Google.
     *
     * @param string     $method       'GET'|'POST'|'PATCH'|'DELETE'
     * @param string     $endpoint     Ruta relativa (ej: '/people:createContact') o URL absoluta
     * @param array|null $body         Cuerpo de la petición a serializar en JSON
     * @param array      $query_params Parámetros en query string
     * @return array|WP_Error Array con los datos decodificados o WP_Error en caso de fallo.
     */
    public static function api_request( string $method, string $endpoint, ?array $body = null, array $query_params = array() ) {
        self::$last_error = null;

        $provider = Aura_Google_Auth_Factory::get_provider();
        if ( ! $provider->is_connected() ) {
            self::$last_error = __( 'No hay ninguna cuenta de Google Contacts vinculada.', 'aura-suite' );
            return new WP_Error( 'not_connected', self::$last_error );
        }

        $access_token = $provider->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            self::$last_error = $access_token->get_error_message();
            return $access_token;
        }

        $url = 0 === strpos( $endpoint, 'http' ) ? $endpoint : self::BASE_URL . '/' . ltrim( $endpoint, '/' );

        if ( ! empty( $query_params ) ) {
            $url = add_query_arg( $query_params, $url );
        }

        $args = array(
            'method'  => strtoupper( $method ),
            'timeout' => 25,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
        );

        if ( null !== $body ) {
            $args['body'] = (string) wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            self::$last_error = $response->get_error_message();
            return $response;
        }

        $code      = wp_remote_retrieve_response_code( $response );
        $resp_body = wp_remote_retrieve_body( $response );

        // Código 204: No Content (ej. tras DELETE exitoso)
        if ( 204 === $code ) {
            return array( 'success' => true );
        }

        $data = json_decode( $resp_body, true );

        if ( $code >= 400 ) {
            $err_msg = $data['error']['message'] ?? sprintf( __( 'Error HTTP %d de Google People API', 'aura-suite' ), $code );
            $err_status = $data['error']['status'] ?? 'HTTP_' . $code;

            self::$last_error = sprintf( '%s: %s', $err_status, $err_msg );

            // Si Google responde 401 Unauthorized o 403 con token revocado
            if ( 401 === $code || ( 403 === $code && false !== stripos( $err_msg, 'token' ) ) ) {
                return new WP_Error( 'google_auth_expired', __( 'La sesión de Google Contacts ha expirado o el token es inválido. Reautoriza en Ajustes.', 'aura-suite' ), $data );
            }

            // Manejo de cuota superada (429)
            if ( 429 === $code ) {
                return new WP_Error( 'rate_limit_exceeded', __( 'Cuota de Google People API alcanzada temporalmente. Espera unos momentos.', 'aura-suite' ), $data );
            }

            return new WP_Error( 'google_api_error', self::$last_error, array(
                'status' => $code,
                'body'   => $data,
            ) );
        }

        return is_array( $data ) ? $data : array();
    }

    // ─────────────────────────────────────────────────────────────
    // OPERACIONES DE CONTACTOS (CRUD PEOPLE API)
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un contacto en Google Contacts.
     *
     * @param array $payload Estructura de Person de Google People API
     * @return array|WP_Error Datos del contacto creado (incluye resourceName y etag)
     */
    public static function create_person( array $payload ) {
        return self::api_request( 'POST', 'people:createContact', $payload );
    }

    /**
     * Actualiza un contacto en Google Contacts.
     *
     * @param string $resource_name (ej: 'people/c123456789')
     * @param array  $payload
     * @param array  $update_fields Campos a actualizar
     * @return array|WP_Error
     */
    public static function update_person( string $resource_name, array $payload, array $update_fields = array() ) {
        $default_fields = array( 'names', 'emailAddresses', 'phoneNumbers', 'organizations', 'addresses', 'biographies', 'userDefined' );
        $fields         = ! empty( $update_fields ) ? $update_fields : $default_fields;

        $endpoint = sprintf( '%s:updateContact', ltrim( $resource_name, '/' ) );
        $query    = array( 'updatePersonFields' => implode( ',', $fields ) );

        return self::api_request( 'PATCH', $endpoint, $payload, $query );
    }

    /**
     * Elimina un contacto de Google Contacts.
     *
     * @param string $resource_name (ej: 'people/c123456789')
     * @return bool|WP_Error
     */
    public static function delete_person( string $resource_name ) {
        $endpoint = sprintf( '%s:deleteContact', ltrim( $resource_name, '/' ) );
        $result   = self::api_request( 'DELETE', $endpoint );

        if ( is_wp_error( $result ) ) {
            // Si el contacto ya no existía en Google (404), se considera eliminado con éxito
            if ( isset( $result->get_error_data()['status'] ) && 404 === $result->get_error_data()['status'] ) {
                return true;
            }
            return $result;
        }

        return true;
    }

    /**
     * Obtiene los datos de una persona específica desde Google.
     *
     * @param string $resource_name
     * @return array|WP_Error
     */
    public static function get_person( string $resource_name ) {
        $endpoint = ltrim( $resource_name, '/' );
        return self::api_request( 'GET', $endpoint, null, array( 'personFields' => self::PERSON_FIELDS ) );
    }

    /**
     * Lista conexiones de Google Contacts con soporte para syncToken incremental.
     *
     * @param string|null $sync_token Si se proporciona, solo devuelve cambios desde la última sync.
     * @param int         $page_size
     * @param string|null $page_token Para paginación interna si hay más de $page_size contactos
     * @return array|WP_Error { connections: array, nextPageToken: string|null, nextSyncToken: string|null }
     */
    public static function list_connections( ?string $sync_token = null, int $page_size = 100, ?string $page_token = null ) {
        $query = array(
            'personFields' => self::PERSON_FIELDS,
            'pageSize'     => min( 500, max( 10, $page_size ) ),
        );

        if ( ! empty( $sync_token ) ) {
            $query['syncToken'] = $sync_token;
        } else {
            $query['requestSyncToken'] = 'true';
        }

        if ( ! empty( $page_token ) ) {
            $query['pageToken'] = $page_token;
        }

        return self::api_request( 'GET', 'people/me/connections', null, $query );
    }

    // ─────────────────────────────────────────────────────────────
    // GESTIÓN DE GRUPOS / ETIQUETAS (CONTACT GROUPS)
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene la lista de grupos o etiquetas de contactos del usuario.
     *
     * @return array|WP_Error
     */
    public static function list_contact_groups() {
        return self::api_request( 'GET', 'contactGroups', null, array( 'pageSize' => 100 ) );
    }

    /**
     * Crea un nuevo grupo de contactos en Google.
     *
     * @param string $name Nombre del grupo (ej: 'Aura: Proveedores')
     * @return array|WP_Error
     */
    public static function create_contact_group( string $name ) {
        return self::api_request( 'POST', 'contactGroups', array(
            'contactGroup' => array( 'name' => $name ),
        ) );
    }

    /**
     * Garantiza la existencia de una etiqueta/grupo en Google Contacts para una categoría de Aura.
     * Devuelve el resourceName del grupo (ej: 'contactGroups/123456789').
     *
     * @param string $category
     * @return string|null
     */
    public static function ensure_category_group( string $category ): ?string {
        if ( empty( $category ) ) {
            return null;
        }

        $group_name = 'Aura: ' . $category;
        $cache_key  = 'aura_gcontacts_group_' . sanitize_key( $category );
        $cached_id  = get_transient( $cache_key );

        if ( ! empty( $cached_id ) ) {
            return $cached_id;
        }

        // Consultar grupos existentes
        $groups = self::list_contact_groups();
        if ( ! is_wp_error( $groups ) && ! empty( $groups['contactGroups'] ) ) {
            foreach ( $groups['contactGroups'] as $grp ) {
                if ( ( $grp['name'] ?? '' ) === $group_name && ! empty( $grp['resourceName'] ) ) {
                    set_transient( $cache_key, $grp['resourceName'], DAY_IN_SECONDS );
                    return $grp['resourceName'];
                }
            }
        }

        // Si no existe, crearlo
        $created = self::create_contact_group( $group_name );
        if ( ! is_wp_error( $created ) && ! empty( $created['resourceName'] ) ) {
            set_transient( $cache_key, $created['resourceName'], DAY_IN_SECONDS );
            return $created['resourceName'];
        }

        return null;
    }

    /**
     * Añade contactos a un grupo de Google Contacts.
     *
     * @param string $group_resource_name (ej: 'contactGroups/123456789')
     * @param array  $resource_names_to_add Array de 'people/c123456789'
     * @return bool|WP_Error
     */
    public static function add_to_group( string $group_resource_name, array $resource_names_to_add ) {
        if ( empty( $resource_names_to_add ) ) {
            return true;
        }

        $endpoint = sprintf( '%s/members:modify', ltrim( $group_resource_name, '/' ) );
        $payload  = array(
            'resourceNamesToAdd' => array_values( $resource_names_to_add ),
        );

        $result = self::api_request( 'POST', $endpoint, $payload );
        return is_wp_error( $result ) ? $result : true;
    }

    // ─────────────────────────────────────────────────────────────
    // MAPEO BIDIRECCIONAL (Aura Suite <-> Google People API)
    // ─────────────────────────────────────────────────────────────

    /**
     * Transforma un array de contacto local (Aura Suite) al formato Person de People API.
     *
     * @param array       $contact Registro de wp_aura_contacts
     * @param string|null $etag    Etag de Google si es una actualización
     * @return array
     */
    public static function map_contact_to_google( array $contact, ?string $etag = null ): array {
        $person = array();

        if ( ! empty( $etag ) ) {
            $person['etag'] = $etag;
        }

        // 1. Nombres
        $given_name  = $contact['first_name'] ?? '';
        $family_name = $contact['last_name'] ?? '';

        if ( empty( $given_name ) && empty( $family_name ) && ! empty( $contact['organization'] ) ) {
            $given_name = $contact['organization'];
        }

        $person['names'] = array(
            array(
                'givenName'  => $given_name,
                'familyName' => $family_name,
            ),
        );

        // 2. Correos electrónicos
        $emails = array();
        if ( ! empty( $contact['email_primary'] ) ) {
            $emails[] = array(
                'value' => $contact['email_primary'],
                'type'  => 'work',
            );
        }
        if ( ! empty( $contact['email_secondary'] ) ) {
            $emails[] = array(
                'value' => $contact['email_secondary'],
                'type'  => 'home',
            );
        }
        if ( ! empty( $emails ) ) {
            $person['emailAddresses'] = $emails;
        }

        // 3. Teléfonos
        $phones = array();
        if ( ! empty( $contact['phone_primary'] ) ) {
            $phones[] = array(
                'value' => $contact['phone_primary'],
                'type'  => 'work',
            );
        }
        if ( ! empty( $contact['phone_secondary'] ) ) {
            $phones[] = array(
                'value' => $contact['phone_secondary'],
                'type'  => 'mobile',
            );
        }
        if ( ! empty( $contact['whatsapp'] ) && $contact['whatsapp'] !== ( $contact['phone_primary'] ?? '' ) ) {
            $phones[] = array(
                'value' => $contact['whatsapp'],
                'type'  => 'whatsapp',
            );
        }
        if ( ! empty( $phones ) ) {
            $person['phoneNumbers'] = $phones;
        }

        // 4. Organización y Puesto
        if ( ! empty( $contact['organization'] ) || ! empty( $contact['job_title'] ) ) {
            $person['organizations'] = array(
                array(
                    'name'  => $contact['organization'] ?? '',
                    'title' => $contact['job_title'] ?? '',
                ),
            );
        }

        // 5. Dirección Postal
        if ( ! empty( $contact['address_street'] ) || ! empty( $contact['address_city'] ) ) {
            $person['addresses'] = array(
                array(
                    'streetAddress' => $contact['address_street'] ?? '',
                    'city'          => $contact['address_city'] ?? '',
                    'region'        => $contact['address_state'] ?? '',
                    'postalCode'    => $contact['address_postal'] ?? '',
                    'country'       => $contact['address_country'] ?? 'México',
                    'type'          => 'work',
                ),
            );
        }

        // 6. Notas / Biografía
        if ( ! empty( $contact['notes'] ) ) {
            $person['biographies'] = array(
                array(
                    'value'       => (string) $contact['notes'],
                    'contentType' => 'TEXT_PLAIN',
                ),
            );
        }

        // 7. Campos definidos por usuario para trazabilidad en Google
        $user_defined = array(
            array( 'key' => 'Aura_ID', 'value' => (string) ( $contact['id'] ?? '' ) ),
            array( 'key' => 'Aura_Category', 'value' => (string) ( $contact['category'] ?? 'General' ) ),
            array( 'key' => 'Aura_Type', 'value' => (string) ( $contact['contact_type'] ?? 'person' ) ),
        );

        if ( ! empty( $contact['third_party_id'] ) ) {
            $user_defined[] = array( 'key' => 'Aura_ThirdParty_ID', 'value' => (string) $contact['third_party_id'] );
        }
        if ( ! empty( $contact['student_id'] ) ) {
            $user_defined[] = array( 'key' => 'Aura_Student_ID', 'value' => (string) $contact['student_id'] );
        }

        $person['userDefined'] = $user_defined;

        return $person;
    }

    /**
     * Transforma una estructura Person de Google People API al formato local de wp_aura_contacts.
     *
     * @param array $person
     * @return array
     */
    public static function map_google_to_contact( array $person ): array {
        $first_name = '';
        $last_name  = '';

        if ( ! empty( $person['names'][0] ) ) {
            $name_obj   = $person['names'][0];
            $first_name = $name_obj['givenName'] ?? ( $name_obj['displayName'] ?? '' );
            $last_name  = $name_obj['familyName'] ?? '';
        }

        $email_primary   = $person['emailAddresses'][0]['value'] ?? null;
        $email_secondary = $person['emailAddresses'][1]['value'] ?? null;

        $phone_primary   = $person['phoneNumbers'][0]['value'] ?? null;
        $phone_secondary = $person['phoneNumbers'][1]['value'] ?? null;

        $organization = $person['organizations'][0]['name'] ?? null;
        $job_title    = $person['organizations'][0]['title'] ?? null;

        $street  = $person['addresses'][0]['streetAddress'] ?? null;
        $city    = $person['addresses'][0]['city'] ?? null;
        $state   = $person['addresses'][0]['region'] ?? null;
        $postal  = $person['addresses'][0]['postalCode'] ?? null;
        $country = $person['addresses'][0]['country'] ?? null;

        $notes = $person['biographies'][0]['value'] ?? null;

        // Recuperar metadatos personalizados si fueron inyectados por Aura
        $category        = 'General';
        $contact_type    = 'person';
        $aura_id         = null;
        $third_party_id  = null;
        $student_id      = null;

        if ( ! empty( $person['userDefined'] ) && is_array( $person['userDefined'] ) ) {
            foreach ( $person['userDefined'] as $ud ) {
                $k = $ud['key'] ?? '';
                $v = $ud['value'] ?? '';
                if ( 'Aura_ID' === $k ) {
                    $aura_id = absint( $v );
                } elseif ( 'Aura_Category' === $k ) {
                    $category = sanitize_text_field( $v );
                } elseif ( 'Aura_Type' === $k ) {
                    $contact_type = in_array( $v, array( 'person', 'company', 'organization' ), true ) ? $v : 'person';
                } elseif ( 'Aura_ThirdParty_ID' === $k ) {
                    $third_party_id = absint( $v );
                } elseif ( 'Aura_Student_ID' === $k ) {
                    $student_id = absint( $v );
                }
            }
        }

        // Avatar / Foto
        $avatar_url = null;
        if ( ! empty( $person['photos'][0]['url'] ) && empty( $person['photos'][0]['default'] ) ) {
            $avatar_url = $person['photos'][0]['url'];
        }

        return array(
            'aura_id'         => $aura_id,
            'resource_name'   => $person['resourceName'] ?? '',
            'google_etag'     => $person['etag'] ?? '',
            'first_name'      => $first_name,
            'last_name'       => $last_name,
            'organization'    => $organization,
            'job_title'       => $job_title,
            'email_primary'   => $email_primary,
            'email_secondary' => $email_secondary,
            'phone_primary'   => $phone_primary,
            'phone_secondary' => $phone_secondary,
            'address_street'  => $street,
            'address_city'    => $city,
            'address_state'   => $state,
            'address_postal'  => $postal,
            'address_country' => $country,
            'category'        => $category,
            'contact_type'    => $contact_type,
            'notes'           => $notes,
            'avatar_url'      => $avatar_url,
            'third_party_id'  => $third_party_id,
            'student_id'      => $student_id,
        );
    }
}
