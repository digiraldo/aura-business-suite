<?php
/**
 * Componente Reutilizable: Selector Universal de Contactos (Aura_Contact_Selector)
 *
 * Proporciona un control UI enriquecido con autocompletado en vivo,
 * búsqueda multifiltro (nombre, email, teléfono, organización, cargo),
 * badges de categoría canónicos y sincronización automática de campos en formularios.
 *
 * @package AuraBusinessSuite
 * @subpackage Contacts
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Contact_Selector {

    const NONCE_ACTION = 'aura_contacts_nonce';

    /**
     * Inicializar hooks AJAX y assets.
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_contacts_selector_search', [ __CLASS__, 'ajax_search' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    /**
     * Encolar estilos y scripts del selector universal.
     */
    public static function enqueue_assets(): void {
        $plugin_url = defined( 'AURA_PLUGIN_URL' ) ? AURA_PLUGIN_URL : plugin_dir_url( dirname( dirname( __FILE__ ) ) );
        $plugin_dir = defined( 'AURA_PLUGIN_DIR' ) ? AURA_PLUGIN_DIR : plugin_dir_path( dirname( dirname( __FILE__ ) ) );

        $css_file = 'assets/css/contact-selector.css';
        $js_file  = 'assets/js/contact-selector.js';

        $css_ver = file_exists( $plugin_dir . $css_file ) ? filemtime( $plugin_dir . $css_file ) : '1.8.0';
        $js_ver  = file_exists( $plugin_dir . $js_file ) ? filemtime( $plugin_dir . $js_file ) : '1.8.0';

        wp_register_style(
            'aura-contact-selector',
            $plugin_url . $css_file,
            [],
            $css_ver
        );

        wp_register_script(
            'aura-contact-selector',
            $plugin_url . $js_file,
            [ 'jquery' ],
            $js_ver,
            true
        );

        wp_localize_script( 'aura-contact-selector', 'auraContactSelectorCfg', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
            'i18n'    => [
                'placeholder'      => __( 'Buscar por nombre, correo, teléfono u organización…', 'aura-suite' ),
                'searching'        => __( 'Buscando contactos…', 'aura-suite' ),
                'noResults'        => __( 'No se encontraron contactos.', 'aura-suite' ),
                'minChars'         => __( 'Escribe al menos 2 caracteres…', 'aura-suite' ),
                'selected'         => __( 'Contacto seleccionado', 'aura-suite' ),
                'change'           => __( 'Cambiar contacto', 'aura-suite' ),
                'clear'            => __( 'Quitar selección', 'aura-suite' ),
            ],
        ] );
    }

    /**
     * Endpoint AJAX para búsqueda ligera y rápida de contactos.
     */
    public static function ajax_search(): void {
        // Verificar nonce con fallback a nonce de inventario, biblioteca o vehículos si viene de esos módulos
        $nonce = $_REQUEST['nonce'] ?? '';
        $valid_nonce = wp_verify_nonce( $nonce, self::NONCE_ACTION )
                    || wp_verify_nonce( $nonce, 'aura_inventory_nonce' )
                    || wp_verify_nonce( $nonce, 'aura_library_nonce' )
                    || wp_verify_nonce( $nonce, 'aura_vehicles_nonce' )
                    || wp_verify_nonce( $nonce, 'wp_rest' );

        if ( ! $valid_nonce ) {
            wp_send_json_error( [ 'message' => __( 'Fallo de seguridad (nonce inválido).', 'aura-suite' ) ], 403 );
        }

        // Permisos mínimos
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'No autenticado.', 'aura-suite' ) ], 401 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $q        = sanitize_text_field( $_REQUEST['q'] ?? '' );
        $category = sanitize_text_field( $_REQUEST['category'] ?? '' );
        $limit    = min( 30, max( 5, intval( $_REQUEST['limit'] ?? 15 ) ) );

        $where  = [ "is_active = 1" ];
        $params = [];

        // Filtro por categoría (soporta múltiples separadas por coma)
        if ( ! empty( $category ) && 'all' !== $category ) {
            $cats = array_map( 'trim', explode( ',', $category ) );
            $cats = array_filter( $cats );
            if ( ! empty( $cats ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $cats ), '%s' ) );
                $where[] = "category IN ($placeholders)";
                foreach ( $cats as $c ) {
                    $params[] = sanitize_key( $c );
                }
            }
        }

        // Filtro de búsqueda textual
        if ( strlen( $q ) >= 1 ) {
            $like = '%' . $wpdb->esc_like( $q ) . '%';
            $where[] = "(
                first_name LIKE %s OR
                last_name LIKE %s OR
                organization LIKE %s OR
                email_primary LIKE %s OR
                email_secondary LIKE %s OR
                phone_primary LIKE %s OR
                phone_secondary LIKE %s OR
                whatsapp LIKE %s OR
                job_title LIKE %s
            )";
            for ( $i = 0; $i < 9; $i++ ) {
                $params[] = $like;
            }
        }

        $where_sql = implode( ' AND ', $where );
        $sql = "SELECT id, first_name, last_name, organization, job_title,
                       email_primary, email_secondary, phone_primary, phone_secondary, whatsapp,
                       category, avatar_url, wp_user_id, third_party_id, student_id
                FROM {$table}
                WHERE {$where_sql}
                ORDER BY 
                    CASE 
                        WHEN first_name LIKE %s THEN 1
                        WHEN last_name LIKE %s THEN 2
                        WHEN organization LIKE %s THEN 3
                        ELSE 4
                    END,
                    first_name ASC, last_name ASC
                LIMIT %d";

        $order_like = '%' . $wpdb->esc_like( $q ) . '%';
        $full_params = array_merge( $params, [ $order_like, $order_like, $order_like, $limit ] );

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$full_params ) );

        $results = [];
        foreach ( $rows as $row ) {
            $full_name = trim( $row->first_name . ' ' . $row->last_name );
            if ( empty( $full_name ) ) {
                $full_name = $row->organization ?: __( 'Sin nombre', 'aura-suite' );
            }

            $cat_info = self::get_category_badge_info( $row->category );
            $email    = $row->email_primary ?: ( $row->email_secondary ?: '' );
            $phone    = $row->phone_primary ?: ( $row->whatsapp ?: ( $row->phone_secondary ?: '' ) );

            // Si no tiene avatar_url personalizada, usar mystery man o gravatar si hay email
            $avatar = $row->avatar_url;
            if ( empty( $avatar ) && ! empty( $email ) ) {
                $avatar = get_avatar_url( $email, [ 'size' => 64, 'default' => 'mystery' ] );
            }

            $results[] = [
                'id'              => (int) $row->id,
                'name'            => $full_name,
                'first_name'      => $row->first_name,
                'last_name'       => $row->last_name,
                'email'           => $email,
                'phone'           => $phone,
                'organization'    => $row->organization ?: '',
                'job_title'       => $row->job_title ?: '',
                'id_number'       => '',
                'category'        => $row->category,
                'category_label'  => $cat_info['label'],
                'category_color'  => $cat_info['color'],
                'category_bg'     => $cat_info['bg'],
                'avatar'          => $avatar ?: '',
                'wp_user_id'      => (int) ( $row->wp_user_id ?? 0 ),
                'third_party_id'  => (int) ( $row->third_party_id ?? 0 ),
                'student_id'      => (int) ( $row->student_id ?? 0 ),
            ];
        }

        wp_send_json_success( $results );
    }

    /**
     * Renderizar el componente UI del selector.
     *
     * @param array $args Opciones del selector:
     *   - 'id'             => (string) ID HTML base único.
     *   - 'name'           => (string) Nombre del input hidden para contact_id.
     *   - 'value'          => (int|string) ID del contacto actual.
     *   - 'initial_contact'=> (array|object) Datos del contacto preseleccionado.
     *   - 'placeholder'    => (string) Placeholder de búsqueda.
     *   - 'category'       => (string) Filtro de categoría(s) (ej: 'cliente', 'empleado,chofer').
     *   - 'required'       => (bool) Si el campo es obligatorio.
     *   - 'allow_clear'    => (bool) Permitir deseleccionar.
     *   - 'sync_fields'    => (array) Mapeo a otros selectores (ej: ['name' => '#input-name', 'phone' => '#input-phone']).
     *   - 'label'          => (string) Etiqueta superior opcional.
     *   - 'hint'           => (string) Texto de ayuda inferior.
     *   - 'echo'           => (bool) Si debe imprimir directamente o retornar string.
     * @return string HTML generado.
     */
    public static function render( array $args = [] ): string {
        wp_enqueue_style( 'aura-contact-selector' );
        wp_enqueue_script( 'aura-contact-selector' );

        $defaults = [
            'id'              => 'aura-contact-sel-' . wp_generate_password( 6, false, false ),
            'name'            => 'contact_id',
            'value'           => 0,
            'initial_contact' => null,
            'placeholder'     => __( 'Buscar contacto por nombre, email, teléfono…', 'aura-suite' ),
            'category'        => 'all',
            'required'        => false,
            'allow_clear'     => true,
            'sync_fields'     => [],
            'label'           => '',
            'hint'            => '',
            'echo'            => false,
        ];

        $opts = wp_parse_args( $args, $defaults );

        $contact_id = (int) $opts['value'];
        $contact    = $opts['initial_contact'];

        // Si tenemos un value > 0 pero no un initial_contact, consultarlo de la BD
        if ( $contact_id > 0 && empty( $contact ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'aura_contacts';
            $db_row = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, first_name, last_name, email_primary, email_secondary, phone_primary, phone_secondary, whatsapp, organization, job_title, category, avatar_url, wp_user_id
                 FROM {$table} WHERE id = %d LIMIT 1",
                $contact_id
            ) );
            if ( $db_row ) {
                $full_name = trim( $db_row->first_name . ' ' . $db_row->last_name );
                $cat_info  = self::get_category_badge_info( $db_row->category );
                $email     = $db_row->email_primary ?: ( $db_row->email_secondary ?: '' );
                $phone     = $db_row->phone_primary ?: ( $db_row->whatsapp ?: ( $db_row->phone_secondary ?: '' ) );
                $contact   = [
                    'id'             => (int) $db_row->id,
                    'name'           => $full_name ?: ( $db_row->organization ?: __( 'Contacto #' . $contact_id, 'aura-suite' ) ),
                    'email'          => $email,
                    'phone'          => $phone,
                    'organization'   => $db_row->organization ?: '',
                    'category'       => $db_row->category,
                    'category_label' => $cat_info['label'],
                    'category_color' => $cat_info['color'],
                    'category_bg'    => $cat_info['bg'],
                    'avatar'         => $db_row->avatar_url ?: ( ! empty( $email ) ? get_avatar_url( $email, [ 'size' => 64 ] ) : '' ),
                    'wp_user_id'     => (int) ( $db_row->wp_user_id ?? 0 ),
                ];
            }
        }

        $sync_json = is_array( $opts['sync_fields'] ) ? wp_json_encode( $opts['sync_fields'] ) : '{}';

        ob_start();
        ?>
        <div class="aura-contact-selector-container"
             id="<?php echo esc_attr( $opts['id'] ); ?>"
             data-name="<?php echo esc_attr( $opts['name'] ); ?>"
             data-category="<?php echo esc_attr( $opts['category'] ); ?>"
             data-sync-fields="<?php echo esc_attr( $sync_json ); ?>"
             data-allow-clear="<?php echo $opts['allow_clear'] ? '1' : '0'; ?>">

            <?php if ( ! empty( $opts['label'] ) ) : ?>
                <label class="aura-cs-label" for="<?php echo esc_attr( $opts['id'] ); ?>-input">
                    <?php echo esc_html( $opts['label'] ); ?>
                    <?php if ( $opts['required'] ) : ?>
                        <span class="aura-cs-required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>

            <!-- Input Hidden con el contact_id real -->
            <input type="hidden"
                   class="aura-cs-hidden-id"
                   id="<?php echo esc_attr( $opts['id'] ); ?>-val"
                   name="<?php echo esc_attr( $opts['name'] ); ?>"
                   value="<?php echo esc_attr( $contact_id > 0 ? $contact_id : '' ); ?>"
                   <?php echo $opts['required'] ? 'required' : ''; ?>>

            <!-- Caja de Selección Activa (Card) -->
            <div class="aura-cs-selected-card" style="<?php echo empty( $contact ) ? 'display:none;' : ''; ?>">
                <div class="aura-cs-avatar-wrap">
                    <?php if ( ! empty( $contact['avatar'] ) ) : ?>
                        <img src="<?php echo esc_url( $contact['avatar'] ); ?>" class="aura-cs-avatar" alt="">
                    <?php else : ?>
                        <div class="aura-cs-avatar-initials">
                            <?php
                            $init = ! empty( $contact['name'] ) ? mb_substr( $contact['name'], 0, 1 ) : '?';
                            echo esc_html( mb_strtoupper( $init ) );
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="aura-cs-info">
                    <div class="aura-cs-name-row">
                        <strong class="aura-cs-name"><?php echo esc_html( $contact['name'] ?? '' ); ?></strong>
                        <span class="aura-cs-badge"
                              style="color: <?php echo esc_attr( $contact['category_color'] ?? '#3b82f6' ); ?>; background-color: <?php echo esc_attr( $contact['category_bg'] ?? '#eff6ff' ); ?>;">
                            <?php echo esc_html( $contact['category_label'] ?? '' ); ?>
                        </span>
                    </div>
                    <div class="aura-cs-meta-row">
                        <?php if ( ! empty( $contact['email'] ) ) : ?>
                            <span class="aura-cs-meta-item aura-cs-meta-email">
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php echo esc_html( $contact['email'] ); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( ! empty( $contact['phone'] ) ) : ?>
                            <span class="aura-cs-meta-item aura-cs-meta-phone">
                                <span class="dashicons dashicons-phone"></span>
                                <?php echo esc_html( $contact['phone'] ); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( ! empty( $contact['organization'] ) ) : ?>
                            <span class="aura-cs-meta-item aura-cs-meta-org">
                                <span class="dashicons dashicons-building"></span>
                                <?php echo esc_html( $contact['organization'] ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ( $opts['allow_clear'] ) : ?>
                    <button type="button" class="aura-cs-btn-clear" title="<?php esc_attr_e( 'Quitar selección', 'aura-suite' ); ?>" aria-label="Limpiar contacto">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Input de Búsqueda y Dropdown desplegable -->
            <div class="aura-cs-search-box" style="<?php echo ! empty( $contact ) ? 'display:none;' : ''; ?>">
                <div class="aura-cs-input-wrap">
                    <span class="dashicons dashicons-search aura-cs-search-icon"></span>
                    <input type="text"
                           class="aura-cs-search-input"
                           id="<?php echo esc_attr( $opts['id'] ); ?>-input"
                           placeholder="<?php echo esc_attr( $opts['placeholder'] ); ?>"
                           autocomplete="off">
                    <span class="aura-cs-spinner dashicons dashicons-update spin" style="display:none;"></span>
                </div>

                <!-- Dropdown Flotante -->
                <div class="aura-cs-dropdown" style="display:none;">
                    <ul class="aura-cs-results-list" role="listbox"></ul>
                </div>
            </div>

            <?php if ( ! empty( $opts['hint'] ) ) : ?>
                <p class="aura-cs-hint"><?php echo esc_html( $opts['hint'] ); ?></p>
            <?php endif; ?>

        </div>
        <?php
        $html = ob_get_clean();

        if ( $opts['echo'] ) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return '';
        }

        return $html;
    }

    /**
     * Obtener label y colores para badges de categoría.
     *
     * @param string $category
     * @return array
     */
    public static function get_category_badge_info( string $category ): array {
        $cats = [
            'cliente' => [
                'label' => __( 'Cliente', 'aura-suite' ),
                'color' => '#059669', // Emerald
                'bg'    => '#ecfdf5',
            ],
            'proveedor' => [
                'label' => __( 'Proveedor', 'aura-suite' ),
                'color' => '#d97706', // Amber
                'bg'    => '#fffbeb',
            ],
            'empleado' => [
                'label' => __( 'Empleado / Staff', 'aura-suite' ),
                'color' => '#2563eb', // Blue
                'bg'    => '#eff6ff',
            ],
            'estudiante' => [
                'label' => __( 'Estudiante', 'aura-suite' ),
                'color' => '#7c3aed', // Purple
                'bg'    => '#f5f3ff',
            ],
            'contacto' => [
                'label' => __( 'Contacto', 'aura-suite' ),
                'color' => '#4b5563', // Slate
                'bg'    => '#f3f4f6',
            ],
            'chofer' => [
                'label' => __( 'Conductor', 'aura-suite' ),
                'color' => '#0891b2', // Cyan
                'bg'    => '#ecfeff',
            ],
        ];

        return $cats[ $category ] ?? [
            'label' => ucfirst( $category ?: 'General' ),
            'color' => '#6b7280',
            'bg'    => '#f9fafb',
        ];
    }
}
