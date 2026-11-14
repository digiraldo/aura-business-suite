<?php
/**
 * Aura Nav Login Menu — Ícono/Botón de Acceso y Perfil en Menús de Navegación.
 *
 * Permite configurar desde Ajustes → Páginas (tab=pages):
 *  - Inserción automática al final de un menú de WordPress (wp_nav_menu_items)
 *    sin editar nav-menus.php ni pegar CSS personalizado en Divi.
 *  - Compatibilidad retroactiva automática con ítems que usen la clase .menu-icono-login.
 *  - Selector visual de íconos SVG inline, modo solo ícono / ícono+texto / solo texto,
 *    tamaño, zoom hover y color.
 *  - Modo para usuario con sesión iniciada: reemplaza el ícono SVG por la foto de perfil 1:1
 *    y el Primer Nombre del usuario, abriendo directamente su Portal.
 *
 * @package AuraBusinessSuite
 * @subpackage Common
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Nav_Login_Menu {

    const OPTION_NAME  = 'aura_nav_login_settings';
    const NONCE_ACTION = 'aura_nav_login_nonce';
    const LEGACY_CLASS = 'menu-icono-login';

    /**
     * Inicializa hooks de frontend y AJAX.
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_pages_save_nav_login', [ __CLASS__, 'ajax_save' ] );

        if ( ! is_admin() ) {
            add_filter( 'wp_nav_menu_items', [ __CLASS__, 'filter_nav_menu_items' ], 20, 2 );
            add_filter( 'walker_nav_menu_start_el', [ __CLASS__, 'filter_legacy_menu_item' ], 20, 4 );
            add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_styles' ], 999 );
        }
    }

    /**
     * Configuración por defecto.
     *
     * @return array<string,mixed>
     */
    public static function defaults(): array {
        return [
            'menu_target'        => '',
            'apply_legacy_class' => true,
            'display_mode'       => 'icon_only',
            'icon_key'           => 'user',
            'label_text'         => __( 'Acceso', 'aura-suite' ),
            'icon_size'          => 18,
            'hover_scale'        => 1.15,
            'custom_color'       => '',
            'logged_in_mode'     => 'portal_profile',
            'avatar_shape'       => 'circle',
        ];
    }

    /**
     * Devuelve la configuración normalizada.
     *
     * @return array<string,mixed>
     */
    public static function get_settings(): array {
        $saved = get_option( self::OPTION_NAME, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        $cfg = array_merge( self::defaults(), $saved );

        $cfg['menu_target']        = sanitize_text_field( (string) $cfg['menu_target'] );
        $cfg['apply_legacy_class'] = ! empty( $cfg['apply_legacy_class'] );
        $cfg['display_mode']       = in_array( $cfg['display_mode'], [ 'icon_only', 'icon_text', 'text_only' ], true ) ? $cfg['display_mode'] : 'icon_only';
        $icons                     = self::get_icons();
        $cfg['icon_key']           = isset( $icons[ $cfg['icon_key'] ] ) ? $cfg['icon_key'] : 'user';
        $cfg['label_text']         = trim( sanitize_text_field( (string) $cfg['label_text'] ) ) ?: __( 'Acceso', 'aura-suite' );
        $cfg['icon_size']          = max( 14, min( 32, (int) $cfg['icon_size'] ) );
        $cfg['hover_scale']        = max( 1.0, min( 1.35, (float) $cfg['hover_scale'] ) );
        $color                     = sanitize_hex_color( (string) $cfg['custom_color'] );
        $cfg['custom_color']       = $color ? $color : '';
        $cfg['logged_in_mode']     = in_array( $cfg['logged_in_mode'], [ 'portal_profile', 'keep_login' ], true ) ? $cfg['logged_in_mode'] : 'portal_profile';
        $cfg['avatar_shape']       = in_array( $cfg['avatar_shape'], [ 'circle', 'square' ], true ) ? $cfg['avatar_shape'] : 'circle';

        return $cfg;
    }

    /**
     * Catálogo de íconos SVG inline nítidos (sin dependencia de fuentes externas).
     *
     * @return array<string,array{label:string,svg:string}>
     */
    public static function get_icons(): array {
        $common = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"';
        return [
            'user' => [
                'label' => __( 'Usuario', 'aura-suite' ),
                'svg'   => '<svg ' . $common . '><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            ],
            'user_circle' => [
                'label' => __( 'Perfil circular', 'aura-suite' ),
                'svg'   => '<svg ' . $common . '><circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3"/><path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"/></svg>',
            ],
            'login_door' => [
                'label' => __( 'Puerta / Ingreso', 'aura-suite' ),
                'svg'   => '<svg ' . $common . '><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
            ],
            'lock' => [
                'label' => __( 'Candado', 'aura-suite' ),
                'svg'   => '<svg ' . $common . '><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            ],
            'key' => [
                'label' => __( 'Llave', 'aura-suite' ),
                'svg'   => '<svg ' . $common . '><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>',
            ],
            'graduation' => [
                'label' => __( 'Academia / Birrete', 'aura-suite' ),
                'svg'   => '<svg ' . $common . '><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>',
            ],
            'shield' => [
                'label' => __( 'Escudo', 'aura-suite' ),
                'svg'   => '<svg ' . $common . '><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            ],
        ];
    }

    /**
     * Indica si el CSS/funcionalidad debe cargarse en el frontend.
     */
    public static function is_active( ?array $cfg = null ): bool {
        $cfg = $cfg ?? self::get_settings();
        return '' !== $cfg['menu_target'] || ! empty( $cfg['apply_legacy_class'] );
    }

    /**
     * Determina la URL de la página de Acceso a la Plataforma.
     */
    public static function get_login_url(): string {
        if ( class_exists( 'Aura_Page_Manager' ) ) {
            $login_id = Aura_Page_Manager::get_id( 'student_login' );
            if ( $login_id > 0 && 'publish' === get_post_status( $login_id ) ) {
                $url = get_permalink( $login_id );
                if ( $url ) {
                    return $url;
                }
            }
        }
        return home_url( '/acceso/' );
    }

    /**
     * Resuelve el primer nombre visible del usuario logueado.
     */
    public static function get_user_first_name( WP_User $user ): string {
        $first = trim( (string) $user->first_name );
        if ( '' === $first ) {
            $first = trim( (string) get_user_meta( $user->ID, 'first_name', true ) );
        }
        if ( '' === $first ) {
            $display = trim( (string) $user->display_name );
            if ( '' !== $display ) {
                $parts = preg_split( '/\s+/', $display );
                $first = $parts[0] ?? $display;
            }
        }
        if ( '' === $first ) {
            $first = (string) $user->user_login;
        }
        return $first;
    }

    /**
     * Resuelve la URL de la foto de perfil real del usuario (o vacío si no tiene).
     */
    public static function get_user_avatar_url( WP_User $user ): string {
        $user_id = (int) $user->ID;

        if ( class_exists( 'Aura_Students_Frontend' ) && method_exists( 'Aura_Students_Frontend', 'get_student_by_wp_user' ) ) {
            $student = Aura_Students_Frontend::get_student_by_wp_user( $user_id );
            if ( ! empty( $student->photo_url ) && filter_var( $student->photo_url, FILTER_VALIDATE_URL ) ) {
                return (string) $student->photo_url;
            }
        }

        $sla = get_user_meta( $user_id, 'simple_local_avatar', true );
        if ( is_array( $sla ) && ! empty( $sla['full'] ) && filter_var( $sla['full'], FILTER_VALIDATE_URL ) ) {
            return (string) $sla['full'];
        }

        foreach ( [ 'aura_avatar_url', 'profile_photo' ] as $meta_key ) {
            $url = get_user_meta( $user_id, $meta_key, true );
            if ( is_string( $url ) && '' !== $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
                return $url;
            }
        }

        $wp_avatar_id = get_user_meta( $user_id, 'wp_user_avatar', true );
        if ( is_numeric( $wp_avatar_id ) && (int) $wp_avatar_id > 0 ) {
            $att_url = wp_get_attachment_image_url( (int) $wp_avatar_id, 'thumbnail' );
            if ( $att_url ) {
                return $att_url;
            }
        }

        if ( function_exists( 'get_avatar_data' ) ) {
            $avatar_data = get_avatar_data( $user_id, [ 'size' => 96, 'default' => '404' ] );
            if ( ! empty( $avatar_data['found_avatar'] ) && ! empty( $avatar_data['url'] ) ) {
                return (string) $avatar_data['url'];
            }
        }

        return '';
    }

    /**
     * Resuelve la URL de destino cuando el usuario ya inició sesión.
     * Apunta a la página de Acceso a la Plataforma (student_login), donde se muestran
     * sus accesos permitidos (Panel Administrativo si aplica y portales frontend).
     */
    public static function get_user_portal_url( WP_User $user ): string {
        return self::get_login_url();
    }

    /**
     * Construye el HTML interno del enlace según estado de sesión y configuración.
     *
     * @return array{url:string,html:string,aria_label:string,is_logged_card:bool}
     */
    public static function build_link_payload( ?array $cfg = null ): array {
        $cfg   = $cfg ?? self::get_settings();
        $icons = self::get_icons();

        if ( is_user_logged_in() && 'portal_profile' === $cfg['logged_in_mode'] ) {
            $user       = wp_get_current_user();
            $first_name = self::get_user_first_name( $user );
            $portal_url = self::get_user_portal_url( $user );
            $avatar_url = self::get_user_avatar_url( $user );
            $shape_cls  = 'square' === $cfg['avatar_shape'] ? 'is-square' : 'is-circle';

            if ( '' !== $avatar_url ) {
                $media_html = '<span class="aura-nav-user-avatar-wrap ' . esc_attr( $shape_cls ) . '">'
                    . '<img src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr( $first_name ) . '" class="aura-nav-user-avatar" loading="lazy" />'
                    . '</span>';
            } else {
                $initial    = function_exists( 'mb_substr' ) ? mb_substr( $first_name, 0, 1, 'UTF-8' ) : substr( $first_name, 0, 1 );
                $initial    = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initial, 'UTF-8' ) : strtoupper( $initial );
                $media_html = '<span class="aura-nav-user-avatar-fallback ' . esc_attr( $shape_cls ) . '" aria-hidden="true">' . esc_html( $initial ?: 'U' ) . '</span>';
            }

            return [
                'url'            => $portal_url,
                'aria_label'     => sprintf( __( 'Ir al portal de %s', 'aura-suite' ), $first_name ),
                'is_logged_card' => true,
                'html'           => $media_html . '<span class="aura-nav-login-label aura-nav-user-name">' . esc_html( $first_name ) . '</span>',
            ];
        }

        $svg_html  = $icons[ $cfg['icon_key'] ]['svg'] ?? $icons['user']['svg'];
        $label     = $cfg['label_text'];
        $mode      = $cfg['display_mode'];
        $login_url = self::get_login_url();

        if ( 'text_only' === $mode ) {
            $inner = '<span class="aura-nav-login-label">' . esc_html( $label ) . '</span>';
        } elseif ( 'icon_text' === $mode ) {
            $inner = '<span class="aura-nav-login-icon">' . $svg_html . '</span>'
                . '<span class="aura-nav-login-label">' . esc_html( $label ) . '</span>';
        } else {
            $inner = '<span class="aura-nav-login-icon">' . $svg_html . '</span>'
                . '<span class="aura-nav-sr-only">' . esc_html( $label ) . '</span>';
        }

        return [
            'url'            => $login_url,
            'aria_label'     => $label,
            'is_logged_card' => false,
            'html'           => $inner,
        ];
    }

    /**
     * Comprueba si este render de wp_nav_menu corresponde al menú configurado.
     */
    private static function matches_menu_target( string $target, object $args ): bool {
        if ( '' === $target ) {
            return false;
        }
        if ( 'all' === $target ) {
            return true;
        }
        if ( 0 === strpos( $target, 'menu:' ) ) {
            $target_id = (int) substr( $target, 5 );
            $menu_obj  = $args->menu ?? null;
            if ( is_object( $menu_obj ) && isset( $menu_obj->term_id ) ) {
                return (int) $menu_obj->term_id === $target_id;
            }
            if ( is_numeric( $menu_obj ) ) {
                return (int) $menu_obj === $target_id;
            }
            if ( is_string( $menu_obj ) && '' !== $menu_obj ) {
                $term = wp_get_nav_menu_object( $menu_obj );
                return $term && (int) $term->term_id === $target_id;
            }
            // Fallback por ubicación cuando Divi pasa theme_location sin menu term_id explícito
            $loc = (string) ( $args->theme_location ?? '' );
            if ( '' !== $loc && function_exists( 'get_nav_menu_locations' ) ) {
                $locations = get_nav_menu_locations();
                return isset( $locations[ $loc ] ) && (int) $locations[ $loc ] === $target_id;
            }
            return false;
        }
        if ( 0 === strpos( $target, 'loc:' ) ) {
            $loc = substr( $target, 4 );
            return (string) ( $args->theme_location ?? '' ) === $loc;
        }
        return false;
    }

    /**
     * Inserta automáticamente el botón/ícono al final del menú seleccionado.
     */
    public static function filter_nav_menu_items( string $items, object $args ): string {
        $cfg = self::get_settings();
        if ( ! self::matches_menu_target( $cfg['menu_target'], $args ) ) {
            return $items;
        }

        // Evitar duplicar si el menú ya tiene un ítem manual con .menu-icono-login
        if ( false !== strpos( $items, self::LEGACY_CLASS ) || false !== strpos( $items, 'aura-nav-login-item' ) ) {
            return $items;
        }

        $payload = self::build_link_payload( $cfg );
        $classes = 'menu-item aura-nav-login-item';
        if ( $payload['is_logged_card'] ) {
            $classes .= ' aura-nav-login-item--logged';
        }

        $item_html = sprintf(
            '<li class="%s"><a href="%s" class="aura-nav-login-link" aria-label="%s" title="%s">%s</a></li>',
            esc_attr( $classes ),
            esc_url( $payload['url'] ),
            esc_attr( $payload['aria_label'] ),
            esc_attr( $payload['aria_label'] ),
            $payload['html']
        );

        return $items . $item_html;
    }

    /**
     * Retrocompatibilidad: transforma ítems que ya tengan la clase CSS .menu-icono-login
     * en nav-menus.php usando la configuración SVG/perfil de Aura sin necesitar CSS extra en Divi.
     *
     * @param string   $item_output HTML actual del elemento.
     * @param WP_Post  $menu_item   Objeto del ítem de menú.
     * @param int      $depth       Profundidad.
     * @param stdClass $args        Argumentos de wp_nav_menu.
     */
    public static function filter_legacy_menu_item( string $item_output, $menu_item, int $depth, $args ): string {
        $cfg = self::get_settings();
        if ( empty( $cfg['apply_legacy_class'] ) ) {
            return $item_output;
        }
        $item_classes = isset( $menu_item->classes ) && is_array( $menu_item->classes ) ? $menu_item->classes : [];
        if ( ! in_array( self::LEGACY_CLASS, $item_classes, true ) ) {
            return $item_output;
        }

        $payload = self::build_link_payload( $cfg );
        $classes = 'aura-nav-login-link aura-nav-login-link--legacy';
        if ( $payload['is_logged_card'] ) {
            $classes .= ' aura-nav-login-link--logged';
        }

        return sprintf(
            '<a href="%s" class="%s" aria-label="%s" title="%s">%s</a>',
            esc_url( $payload['url'] ),
            esc_attr( $classes ),
            esc_attr( $payload['aria_label'] ),
            esc_attr( $payload['aria_label'] ),
            $payload['html']
        );
    }

    /**
     * Imprime el CSS ligero y compatible con Divi 4/5 (#top-menu, .et_pb_menu y móvil).
     */
    public static function enqueue_frontend_styles(): void {
        $cfg = self::get_settings();
        if ( ! self::is_active( $cfg ) ) {
            return;
        }

        $size       = (int) $cfg['icon_size'];
        $hover      = number_format( (float) $cfg['hover_scale'], 2, '.', '' );
        $avatar_px  = max( 24, $size + 10 );
        $color_rule = '' !== $cfg['custom_color'] ? 'color: ' . $cfg['custom_color'] . ' !important;' : '';

        // Anular el viejo hack font-size: 0 si el usuario olvidó borrar CSS manual previo
        $css = sprintf(
            '.aura-nav-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
.et_pb_menu li.%1$s a::before,#top-menu li.%1$s a::before,.%1$s a::before{content:none !important;display:none !important;}
.%1$s a,.aura-nav-login-item .aura-nav-login-link{font-size:inherit !important;display:inline-flex !important;align-items:center !important;gap:7px !important;line-height:1.25 !important;vertical-align:middle !important;%5$s}
.aura-nav-login-icon{display:inline-flex;align-items:center;justify-content:center;line-height:0;flex-shrink:0;}
.aura-nav-login-icon svg{width:%2$dpx;height:%2$dpx;display:block;transition:transform 0.2s ease-in-out;}
.aura-nav-login-link:hover .aura-nav-login-icon svg,.aura-nav-login-link:focus-visible .aura-nav-login-icon svg{transform:scale(%3$s);}
.aura-nav-login-label{display:inline-block;line-height:1.2;}
.aura-nav-user-avatar-wrap,.aura-nav-user-avatar-fallback{width:%4$dpx;height:%4$dpx;display:inline-flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;border:2px solid currentColor;box-shadow:0 1px 4px rgba(0,0,0,0.14);transition:transform 0.2s ease-in-out;}
.aura-nav-user-avatar-wrap.is-circle,.aura-nav-user-avatar-fallback.is-circle{border-radius:50%%;}
.aura-nav-user-avatar-wrap.is-square,.aura-nav-user-avatar-fallback.is-square{border-radius:6px;}
.aura-nav-user-avatar{width:100%% !important;height:100%% !important;object-fit:cover !important;display:block !important;margin:0 !important;}
.aura-nav-user-avatar-fallback{font-size:12px;font-weight:700;background:rgba(79,70,229,0.16);}
.aura-nav-login-link:hover .aura-nav-user-avatar-wrap,.aura-nav-login-link:hover .aura-nav-user-avatar-fallback{transform:scale(%3$s);}',
            self::LEGACY_CLASS,
            $size,
            $hover,
            $avatar_px,
            $color_rule
        );

        wp_register_style( 'aura-nav-login', false, [], defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' );
        wp_enqueue_style( 'aura-nav-login' );
        wp_add_inline_style( 'aura-nav-login', $css );
    }

    /**
     * AJAX: Guarda la configuración del ícono/botón de menú desde Ajustes → Páginas.
     */
    public static function ajax_save(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_admin_settings' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ], 403 );
        }

        $icons        = self::get_icons();
        $display_mode = sanitize_key( $_POST['display_mode'] ?? 'icon_only' );
        $icon_key     = sanitize_key( $_POST['icon_key'] ?? 'user' );
        $logged_mode  = sanitize_key( $_POST['logged_in_mode'] ?? 'portal_profile' );
        $avatar_shape = sanitize_key( $_POST['avatar_shape'] ?? 'circle' );
        $custom_color = ! empty( $_POST['use_custom_color'] ) ? sanitize_hex_color( (string) ( $_POST['custom_color'] ?? '' ) ) : '';

        $new = [
            'menu_target'        => sanitize_text_field( wp_unslash( $_POST['menu_target'] ?? '' ) ),
            'apply_legacy_class' => ! empty( $_POST['apply_legacy_class'] ),
            'display_mode'       => in_array( $display_mode, [ 'icon_only', 'icon_text', 'text_only' ], true ) ? $display_mode : 'icon_only',
            'icon_key'           => isset( $icons[ $icon_key ] ) ? $icon_key : 'user',
            'label_text'         => sanitize_text_field( wp_unslash( $_POST['label_text'] ?? __( 'Acceso', 'aura-suite' ) ) ) ?: __( 'Acceso', 'aura-suite' ),
            'icon_size'          => max( 14, min( 32, absint( $_POST['icon_size'] ?? 18 ) ) ),
            'hover_scale'        => max( 1.0, min( 1.35, floatval( $_POST['hover_scale'] ?? 1.15 ) ) ),
            'custom_color'       => $custom_color ?: '',
            'logged_in_mode'     => in_array( $logged_mode, [ 'portal_profile', 'keep_login' ], true ) ? $logged_mode : 'portal_profile',
            'avatar_shape'       => in_array( $avatar_shape, [ 'circle', 'square' ], true ) ? $avatar_shape : 'circle',
        ];

        update_option( self::OPTION_NAME, $new );

        wp_send_json_success( [
            'message'  => __( 'Configuración del menú guardada correctamente.', 'aura-suite' ),
            'settings' => self::get_settings(),
        ] );
    }

    /**
     * Renderiza la tarjeta de configuración dentro de Ajustes → Páginas (tab=pages).
     */
    public static function render_settings_card(): void {
        $cfg      = self::get_settings();
        $icons    = self::get_icons();
        $nonce    = wp_create_nonce( self::NONCE_ACTION );
        $menus    = function_exists( 'wp_get_nav_menus' ) ? wp_get_nav_menus() : [];
        $locs     = function_exists( 'get_registered_nav_menus' ) ? get_registered_nav_menus() : [];
        $cur_user = wp_get_current_user();
        $cur_name = ( $cur_user instanceof WP_User && $cur_user->ID > 0 ) ? self::get_user_first_name( $cur_user ) : 'Usuario';
        $cur_av   = ( $cur_user instanceof WP_User && $cur_user->ID > 0 ) ? self::get_user_avatar_url( $cur_user ) : '';
        ?>
        <div class="aura-panel-card" id="aura-nav-login-card" style="margin-top:20px;">
            <div class="aura-card-header">
                <div class="card-icon indigo"><span class="dashicons dashicons-menu"></span></div>
                <div class="card-title">
                    <h3><?php esc_html_e( 'Ícono / Botón de Acceso en el Menú de Navegación', 'aura-suite' ); ?></h3>
                    <p><?php esc_html_e( 'Agrega automáticamente el acceso a la plataforma (con ícono SVG o texto) al final del menú de WordPress/Divi sin editar nav-menus.php ni agregar CSS manual.', 'aura-suite' ); ?></p>
                </div>
            </div>
            <div class="card-body">
                <style>
                    #aura-nav-login-card .aura-nav-cfg-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
                    #aura-nav-login-card .aura-nav-icon-picker { display:grid; grid-template-columns:repeat(auto-fill, minmax(112px, 1fr)); gap:8px; margin-top:6px; }
                    #aura-nav-login-card .aura-nav-icon-opt { border:1.5px solid #cbd5e1; border-radius:10px; padding:10px 8px; background:#f8fafc; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:6px; transition:all 0.15s ease; color:#334155; }
                    #aura-nav-login-card .aura-nav-icon-opt svg { width:20px; height:20px; }
                    #aura-nav-login-card .aura-nav-icon-opt span { font-size:11.5px; font-weight:600; text-align:center; }
                    #aura-nav-login-card .aura-nav-icon-opt.is-selected { border-color:#4f46e5; background:#eef2ff; color:#3730a3; box-shadow:0 0 0 2px rgba(79,70,229,0.18); }
                    #aura-nav-login-card .aura-nav-preview-bar { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; padding:14px 18px; border-radius:12px; background:#0f172a; color:#f8fafc; margin-top:16px; }
                    #aura-nav-login-card .aura-nav-preview-pills { display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
                    #aura-nav-login-card .aura-nav-preview-slot { display:flex; flex-direction:column; gap:5px; }
                    #aura-nav-login-card .aura-nav-preview-slot small { font-size:11px; color:#94a3b8; }
                    #aura-nav-login-card .aura-nav-preview-link { display:inline-flex; align-items:center; gap:7px; color:#f8fafc; text-decoration:none; font-weight:600; font-size:14px; }
                    #aura-nav-login-card .aura-nav-preview-link svg { transition:transform 0.2s ease; }
                    #aura-nav-login-card .aura-nav-preview-link:hover svg { transform:scale(var(--aura-preview-scale, 1.15)); }
                    html[data-wp-dark-mode-scheme="dark"] #aura-nav-login-card .aura-nav-icon-opt,
                    html.wp-dark-mode-active #aura-nav-login-card .aura-nav-icon-opt,
                    body.aura-dark-mode #aura-nav-login-card .aura-nav-icon-opt { background:#0f172a; border-color:#334155; color:#cbd5e1; }
                    html[data-wp-dark-mode-scheme="dark"] #aura-nav-login-card .aura-nav-icon-opt.is-selected,
                    html.wp-dark-mode-active #aura-nav-login-card .aura-nav-icon-opt.is-selected,
                    body.aura-dark-mode #aura-nav-login-card .aura-nav-icon-opt.is-selected { background:rgba(79,70,229,0.25); border-color:#818cf8; color:#fff; }
                    @media (max-width:768px) { #aura-nav-login-card .aura-nav-cfg-grid { grid-template-columns:1fr; } }
                </style>

                <div class="aura-nav-cfg-grid">
                    <div>
                        <label for="aura_nav_menu_target" style="font-weight:700;display:block;margin-bottom:6px;">
                            <?php esc_html_e( 'Insertar automáticamente al final del menú', 'aura-suite' ); ?>
                        </label>
                        <select id="aura_nav_menu_target" class="regular-text" style="width:100%;max-width:100%;">
                            <option value="" <?php selected( $cfg['menu_target'], '' ); ?>>
                                <?php esc_html_e( '— No insertar automáticamente (solo clase .menu-icono-login) —', 'aura-suite' ); ?>
                            </option>
                            <option value="all" <?php selected( $cfg['menu_target'], 'all' ); ?>>
                                <?php esc_html_e( 'Todos los menús de navegación activos', 'aura-suite' ); ?>
                            </option>
                            <?php if ( ! empty( $menus ) ) : ?>
                                <optgroup label="<?php esc_attr_e( 'Menús creados en Apariencia → Menús', 'aura-suite' ); ?>">
                                    <?php foreach ( $menus as $menu_obj ) :
                                        $val = 'menu:' . (int) $menu_obj->term_id;
                                        ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $cfg['menu_target'], $val ); ?>>
                                            <?php echo esc_html( sprintf( '%s (ID: %d)', $menu_obj->name, (int) $menu_obj->term_id ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if ( ! empty( $locs ) ) : ?>
                                <optgroup label="<?php esc_attr_e( 'Ubicaciones del tema (Divi / WordPress)', 'aura-suite' ); ?>">
                                    <?php foreach ( $locs as $loc_key => $loc_name ) :
                                        $val = 'loc:' . $loc_key;
                                        ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $cfg['menu_target'], $val ); ?>>
                                            <?php echo esc_html( $loc_name . ' (' . $loc_key . ')' ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" id="aura_nav_apply_legacy" <?php checked( $cfg['apply_legacy_class'] ); ?>>
                            <span><?php esc_html_e( 'Compatibilidad automática con ítems manuales que tengan clase .menu-icono-login', 'aura-suite' ); ?></span>
                        </label>
                    </div>

                    <div>
                        <label for="aura_nav_display_mode" style="font-weight:700;display:block;margin-bottom:6px;">
                            <?php esc_html_e( 'Modo de visualización (visitantes sin sesión)', 'aura-suite' ); ?>
                        </label>
                        <select id="aura_nav_display_mode" style="width:100%;max-width:100%;">
                            <option value="icon_only" <?php selected( $cfg['display_mode'], 'icon_only' ); ?>><?php esc_html_e( 'Solo ícono SVG inline', 'aura-suite' ); ?></option>
                            <option value="icon_text" <?php selected( $cfg['display_mode'], 'icon_text' ); ?>><?php esc_html_e( 'Ícono SVG inline + Texto', 'aura-suite' ); ?></option>
                            <option value="text_only" <?php selected( $cfg['display_mode'], 'text_only' ); ?>><?php esc_html_e( 'Solo Texto', 'aura-suite' ); ?></option>
                        </select>

                        <label for="aura_nav_label_text" style="font-weight:700;display:block;margin:10px 0 6px;">
                            <?php esc_html_e( 'Texto del botón / etiqueta accesible (tooltip)', 'aura-suite' ); ?>
                        </label>
                        <input type="text" id="aura_nav_label_text" value="<?php echo esc_attr( $cfg['label_text'] ); ?>" class="regular-text" style="width:100%;max-width:100%;" placeholder="<?php esc_attr_e( 'Ej. Acceso a la Plataforma', 'aura-suite' ); ?>">
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <label style="font-weight:700;display:block;margin-bottom:6px;">
                        <?php esc_html_e( 'Selecciona el ícono SVG inline', 'aura-suite' ); ?>
                    </label>
                    <div class="aura-nav-icon-picker" id="aura-nav-icon-picker">
                        <?php foreach ( $icons as $icon_key => $icon_def ) : ?>
                            <button type="button"
                                    class="aura-nav-icon-opt <?php echo $cfg['icon_key'] === $icon_key ? 'is-selected' : ''; ?>"
                                    data-icon="<?php echo esc_attr( $icon_key ); ?>"
                                    data-svg="<?php echo esc_attr( $icon_def['svg'] ); ?>">
                                <?php echo $icon_def['svg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <span><?php echo esc_html( $icon_def['label'] ); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="aura-nav-cfg-grid" style="margin-top:16px;">
                    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                        <div>
                            <label for="aura_nav_icon_size" style="font-weight:700;display:block;margin-bottom:6px;">
                                <?php esc_html_e( 'Tamaño ícono (px)', 'aura-suite' ); ?>
                            </label>
                            <input type="number" id="aura_nav_icon_size" min="14" max="32" value="<?php echo esc_attr( $cfg['icon_size'] ); ?>" style="width:84px;">
                        </div>
                        <div>
                            <label for="aura_nav_hover_scale" style="font-weight:700;display:block;margin-bottom:6px;">
                                <?php esc_html_e( 'Efecto hover', 'aura-suite' ); ?>
                            </label>
                            <select id="aura_nav_hover_scale">
                                <option value="1.00" <?php selected( (string) $cfg['hover_scale'], '1' ); ?>><?php esc_html_e( 'Sin zoom (1.0x)', 'aura-suite' ); ?></option>
                                <option value="1.10" <?php selected( number_format( (float) $cfg['hover_scale'], 2 ), '1.10' ); ?>><?php esc_html_e( 'Suave (1.10x)', 'aura-suite' ); ?></option>
                                <option value="1.15" <?php selected( number_format( (float) $cfg['hover_scale'], 2 ), '1.15' ); ?>><?php esc_html_e( 'Estándar (1.15x)', 'aura-suite' ); ?></option>
                                <option value="1.25" <?php selected( number_format( (float) $cfg['hover_scale'], 2 ), '1.25' ); ?>><?php esc_html_e( 'Destacado (1.25x)', 'aura-suite' ); ?></option>
                            </select>
                        </div>
                        <div>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:700;margin-bottom:6px;cursor:pointer;">
                                <input type="checkbox" id="aura_nav_use_custom_color" <?php checked( '' !== $cfg['custom_color'] ); ?>>
                                <span><?php esc_html_e( 'Color personalizado', 'aura-suite' ); ?></span>
                            </label>
                            <input type="color" id="aura_nav_custom_color" value="<?php echo esc_attr( $cfg['custom_color'] ?: '#ffffff' ); ?>" <?php disabled( '' === $cfg['custom_color'] ); ?>>
                        </div>
                    </div>

                    <div>
                        <label for="aura_nav_logged_in_mode" style="font-weight:700;display:block;margin-bottom:6px;">
                            <?php esc_html_e( 'Cuando el usuario ya inició sesión', 'aura-suite' ); ?>
                        </label>
                        <select id="aura_nav_logged_in_mode" style="width:100%;max-width:100%;">
                            <option value="portal_profile" <?php selected( $cfg['logged_in_mode'], 'portal_profile' ); ?>>
                                <?php esc_html_e( 'Cambiar por foto de perfil + Primer Nombre y abrir su Portal', 'aura-suite' ); ?>
                            </option>
                            <option value="keep_login" <?php selected( $cfg['logged_in_mode'], 'keep_login' ); ?>>
                                <?php esc_html_e( 'Mantener ícono/texto de Acceso a la Plataforma', 'aura-suite' ); ?>
                            </option>
                        </select>
                        <div id="aura-nav-avatar-shape-wrap" style="margin-top:8px;display:flex;align-items:center;gap:12px;font-size:13px;">
                            <span style="font-weight:600;"><?php esc_html_e( 'Forma avatar en menú:', 'aura-suite' ); ?></span>
                            <label style="cursor:pointer;"><input type="radio" name="aura_nav_avatar_shape" value="circle" <?php checked( $cfg['avatar_shape'], 'circle' ); ?>> <?php esc_html_e( 'Circular', 'aura-suite' ); ?></label>
                            <label style="cursor:pointer;"><input type="radio" name="aura_nav_avatar_shape" value="square" <?php checked( $cfg['avatar_shape'], 'square' ); ?>> <?php esc_html_e( 'Cuadrada 1:1', 'aura-suite' ); ?></label>
                        </div>
                    </div>
                </div>

                <!-- Vista previa en vivo -->
                <div class="aura-nav-preview-bar">
                    <div class="aura-nav-preview-pills">
                        <div class="aura-nav-preview-slot">
                            <small><?php esc_html_e( 'Vista previa — Visitante (sin sesión):', 'aura-suite' ); ?></small>
                            <a href="javascript:void(0)" class="aura-nav-preview-link" id="aura-nav-preview-guest"></a>
                        </div>
                        <div class="aura-nav-preview-slot">
                            <small><?php esc_html_e( 'Vista previa — Usuario con sesión:', 'aura-suite' ); ?></small>
                            <a href="javascript:void(0)" class="aura-nav-preview-link" id="aura-nav-preview-logged"></a>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" id="aura-nav-login-save">
                        <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span>
                        <span><?php esc_html_e( 'Guardar configuración del menú', 'aura-suite' ); ?></span>
                    </button>
                </div>
                <p id="aura-nav-login-msg" style="margin:8px 0 0;font-weight:600;"></p>
            </div>
        </div>
        <script>
        jQuery(function ($) {
            var navNonce = '<?php echo esc_js( $nonce ); ?>';
            var curFirst = '<?php echo esc_js( $cur_name ); ?>';
            var curAvatar = '<?php echo esc_js( esc_url_raw( $cur_av ) ); ?>';
            var selectedIcon = '<?php echo esc_js( $cfg['icon_key'] ); ?>';

            function renderPreview() {
                var mode = $('#aura_nav_display_mode').val();
                var label = $.trim($('#aura_nav_label_text').val()) || 'Acceso';
                var size = Math.max(14, Math.min(32, parseInt($('#aura_nav_icon_size').val(), 10) || 18));
                var scale = parseFloat($('#aura_nav_hover_scale').val()) || 1.15;
                var useColor = $('#aura_nav_use_custom_color').is(':checked');
                var color = useColor ? $('#aura_nav_custom_color').val() : '#f8fafc';
                var loggedMode = $('#aura_nav_logged_in_mode').val();
                var shape = $('input[name="aura_nav_avatar_shape"]:checked').val() || 'circle';

                var $selBtn = $('#aura-nav-icon-picker .aura-nav-icon-opt.is-selected');
                var svg = $selBtn.attr('data-svg') || '';

                var guestHtml = '';
                if (mode === 'text_only') {
                    guestHtml = '<span>' + $('<div>').text(label).html() + '</span>';
                } else if (mode === 'icon_text') {
                    guestHtml = '<span style="display:inline-flex;">' + svg + '</span><span>' + $('<div>').text(label).html() + '</span>';
                } else {
                    guestHtml = '<span style="display:inline-flex;">' + svg + '</span>';
                }

                var $guest = $('#aura-nav-preview-guest').html(guestHtml).css({ color: color, '--aura-preview-scale': scale });
                $guest.find('svg').css({ width: size + 'px', height: size + 'px' });

                var $logged = $('#aura-nav-preview-logged').css({ color: color, '--aura-preview-scale': scale });
                if (loggedMode === 'portal_profile') {
                    var avPx = Math.max(24, size + 10);
                    var radius = shape === 'square' ? '6px' : '50%';
                    var media = curAvatar
                        ? '<img src="' + curAvatar + '" alt="" style="width:' + avPx + 'px;height:' + avPx + 'px;border-radius:' + radius + ';object-fit:cover;border:2px solid currentColor;display:block;">'
                        : '<span style="width:' + avPx + 'px;height:' + avPx + 'px;border-radius:' + radius + ';border:2px solid currentColor;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;background:rgba(79,70,229,0.28);">' + $('<div>').text(curFirst.charAt(0).toUpperCase()).html() + '</span>';
                    $logged.html(media + '<span>' + $('<div>').text(curFirst).html() + '</span>');
                } else {
                    $logged.html(guestHtml);
                    $logged.find('svg').css({ width: size + 'px', height: size + 'px' });
                }

                $('#aura-nav-avatar-shape-wrap').toggle(loggedMode === 'portal_profile');
            }

            $('#aura-nav-icon-picker').on('click', '.aura-nav-icon-opt', function () {
                $('#aura-nav-icon-picker .aura-nav-icon-opt').removeClass('is-selected');
                $(this).addClass('is-selected');
                selectedIcon = $(this).data('icon');
                renderPreview();
            });

            $('#aura_nav_use_custom_color').on('change', function () {
                $('#aura_nav_custom_color').prop('disabled', !$(this).is(':checked'));
                renderPreview();
            });

            $('#aura_nav_display_mode, #aura_nav_label_text, #aura_nav_icon_size, #aura_nav_hover_scale, #aura_nav_custom_color, #aura_nav_logged_in_mode, input[name="aura_nav_avatar_shape"]')
                .on('input change', renderPreview);

            $('#aura-nav-login-save').on('click', function () {
                var $btn = $(this).prop('disabled', true);
                var $msg = $('#aura-nav-login-msg').text('');
                $.post(ajaxurl, {
                    action: 'aura_pages_save_nav_login',
                    nonce: navNonce,
                    menu_target: $('#aura_nav_menu_target').val(),
                    apply_legacy_class: $('#aura_nav_apply_legacy').is(':checked') ? 1 : 0,
                    display_mode: $('#aura_nav_display_mode').val(),
                    icon_key: selectedIcon,
                    label_text: $('#aura_nav_label_text').val(),
                    icon_size: $('#aura_nav_icon_size').val(),
                    hover_scale: $('#aura_nav_hover_scale').val(),
                    use_custom_color: $('#aura_nav_use_custom_color').is(':checked') ? 1 : 0,
                    custom_color: $('#aura_nav_custom_color').val(),
                    logged_in_mode: $('#aura_nav_logged_in_mode').val(),
                    avatar_shape: $('input[name="aura_nav_avatar_shape"]:checked').val() || 'circle'
                }, function (r) {
                    $btn.prop('disabled', false);
                    if (r && r.success) {
                        $msg.text(r.data.message).css('color', '#059669');
                    } else {
                        $msg.text((r && r.data && r.data.message) || 'Error').css('color', '#dc2626');
                    }
                }).fail(function () {
                    $btn.prop('disabled', false);
                    $msg.text('Error de conexión.').css('color', '#dc2626');
                });
            });

            renderPreview();
        });
        </script>
        <?php
    }
}
