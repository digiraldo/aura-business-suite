<?php
/**
 * Gestor Centralizado de Idioma por Usuario e Internacionalización (i18n) — Aura Business Suite
 *
 * Permite que cada usuario elija el idioma de la interfaz de AURA sin modificar
 * el idioma global del sitio ni alterar los datos almacenados en la base de datos.
 *
 * @package AuraBusinessSuite
 * @subpackage Common
 * @since 1.8.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_I18n {

    const META_KEY              = 'aura_user_locale';
    const COOKIE_NAME           = 'aura_user_locale';
    const OPTION_DEFAULT_LOCALE = 'aura_default_locale';
    const NONCE_ACT             = 'aura_switch_locale';
    const NONCE_MANAGE          = 'aura_i18n_manage';
    const TEXT_DOMAIN           = 'aura-suite';

    /** @var bool */
    private static $initialized = false;

    /**
     * Inicializar hooks de idioma por usuario, barra superior y estilos.
     */
    public static function init(): void {
        if ( self::$initialized ) {
            return;
        }
        self::$initialized = true;

        // Aplicar preferencia de idioma de forma segura (sin alterar datos en BD)
        add_filter( 'determine_locale', [ __CLASS__, 'filter_determine_locale' ], 20 );
        add_filter( 'plugin_locale', [ __CLASS__, 'filter_plugin_locale' ], 20, 2 );
        add_filter( 'override_load_textdomain', [ __CLASS__, 'maybe_use_native_spanish' ], 10, 3 );
        add_filter( 'load_script_translation_file', [ __CLASS__, 'filter_script_translation_file' ], 10, 3 );
        add_filter( 'pre_load_script_translations', [ __CLASS__, 'fallback_read_script_translations' ], 10, 4 );

        // Recargar textdomain en 'init' prioridad 0 cuando la sesión del usuario ya está disponible
        add_action( 'init', [ __CLASS__, 'reload_plugin_textdomain' ], 0 );

        // Endpoint para cambiar de idioma (admin y frontend / nopriv)
        add_action( 'admin_post_aura_switch_locale', [ __CLASS__, 'handle_switch_locale' ] );
        add_action( 'admin_post_nopriv_aura_switch_locale', [ __CLASS__, 'handle_switch_locale' ] );

        // Endpoints del Gestor de Idiomas en Ajustes (Fase 4)
        add_action( 'wp_ajax_aura_i18n_scan_compile', [ __CLASS__, 'ajax_scan_compile' ] );
        add_action( 'wp_ajax_aura_i18n_upload_po', [ __CLASS__, 'ajax_upload_po' ] );
        add_action( 'admin_post_aura_i18n_download', [ __CLASS__, 'handle_download_file' ] );

        // Selector visible en la Barra Superior de WordPress (#wpadminbar)
        add_action( 'admin_bar_menu', [ __CLASS__, 'add_admin_bar_switcher' ], 95 );

        // Estilos del selector en Admin y Frontend
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // Cableado centralizado de traducciones JS (wp-i18n + JED JSON) para scripts propios de AURA
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'attach_script_translations' ], 999 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'attach_script_translations' ], 999 );
        add_action( 'admin_print_footer_scripts', [ __CLASS__, 'attach_script_translations' ], 1 );
        add_action( 'wp_print_footer_scripts', [ __CLASS__, 'attach_script_translations' ], 1 );
    }

    /**
     * Tabla compacta de banderas SVG inline por código ISO 3166-1 alpha-2 / clave de idioma.
     * Cualquier archivo en assets/flags/{codigo}.svg tiene prioridad automática.
     *
     * @return array<string, string>
     */
    private static function get_builtin_flag_svgs(): array {
        return [
            'es' => '<rect width="24" height="18" fill="#AA151B"/><rect y="4.5" width="24" height="9" fill="#F1BF00"/><rect x="5" y="6.5" width="3" height="5" rx="0.8" fill="#AA151B" opacity="0.85"/>',
            'mx' => '<rect width="8" height="18" fill="#006847"/><rect x="8" width="8" height="18" fill="#FFFFFF"/><rect x="16" width="8" height="18" fill="#CE1126"/><circle cx="12" cy="9" r="2.1" fill="#8C6239"/>',
            'co' => '<rect width="24" height="9" fill="#FCD116"/><rect y="9" width="24" height="4.5" fill="#003893"/><rect y="13.5" width="24" height="4.5" fill="#CE1126"/>',
            'cl' => '<rect width="24" height="9" fill="#FFFFFF"/><rect y="9" width="24" height="9" fill="#D52B1E"/><rect width="9" height="9" fill="#0039A6"/><polygon points="4.5,2.1 5.2,4.1 7.3,4.1 5.6,5.3 6.2,7.3 4.5,6.1 2.8,7.3 3.4,5.3 1.7,4.1 3.8,4.1" fill="#FFFFFF"/>',
            'ar' => '<rect width="24" height="6" fill="#74ACDF"/><rect y="6" width="24" height="6" fill="#FFFFFF"/><rect y="12" width="24" height="6" fill="#74ACDF"/><circle cx="12" cy="9" r="2" fill="#F6B40E"/>',
            'pe' => '<rect width="8" height="18" fill="#D91023"/><rect x="8" width="8" height="18" fill="#FFFFFF"/><rect x="16" width="8" height="18" fill="#D91023"/>',
            'ec' => '<rect width="24" height="9" fill="#FFD100"/><rect y="9" width="24" height="4.5" fill="#0033A0"/><rect y="13.5" width="24" height="4.5" fill="#EF3340"/><circle cx="12" cy="9.2" r="1.8" fill="#8C6239"/>',
            've' => '<rect width="24" height="6" fill="#FFCC00"/><rect y="6" width="24" height="6" fill="#00247D"/><rect y="12" width="24" height="6" fill="#CF142B"/>',
            'bo' => '<rect width="24" height="6" fill="#D52B1E"/><rect y="6" width="24" height="6" fill="#F9E300"/><rect y="12" width="24" height="6" fill="#007934"/>',
            'cr' => '<rect width="24" height="18" fill="#002B7F"/><rect y="3" width="24" height="12" fill="#FFFFFF"/><rect y="5.5" width="24" height="7" fill="#CE1126"/>',
            'pa' => '<rect width="12" height="9" fill="#FFFFFF"/><rect x="12" width="12" height="9" fill="#DA121A"/><rect y="9" width="12" height="9" fill="#072357"/><rect x="12" y="9" width="12" height="9" fill="#FFFFFF"/>',
            'gt' => '<rect width="8" height="18" fill="#4997D0"/><rect x="8" width="8" height="18" fill="#FFFFFF"/><rect x="16" width="8" height="18" fill="#4997D0"/><circle cx="12" cy="9" r="1.8" fill="#6AA84F"/>',
            'us' => '<rect width="24" height="18" fill="#FFFFFF"/><rect y="0" width="24" height="2.6" fill="#B22234"/><rect y="5.2" width="24" height="2.6" fill="#B22234"/><rect y="10.4" width="24" height="2.6" fill="#B22234"/><rect y="15.4" width="24" height="2.6" fill="#B22234"/><rect width="11" height="10.4" fill="#3C3B6E"/><circle cx="3" cy="3" r="0.8" fill="#FFFFFF"/><circle cx="6" cy="3" r="0.8" fill="#FFFFFF"/><circle cx="9" cy="3" r="0.8" fill="#FFFFFF"/><circle cx="4.5" cy="5.2" r="0.8" fill="#FFFFFF"/><circle cx="7.5" cy="5.2" r="0.8" fill="#FFFFFF"/><circle cx="3" cy="7.4" r="0.8" fill="#FFFFFF"/><circle cx="6" cy="7.4" r="0.8" fill="#FFFFFF"/><circle cx="9" cy="7.4" r="0.8" fill="#FFFFFF"/>',
            'gb' => '<rect width="24" height="18" fill="#012169"/><path d="M0,0 L24,18 M24,0 L0,18" stroke="#FFFFFF" stroke-width="3.2"/><path d="M0,0 L24,18 M24,0 L0,18" stroke="#C8102E" stroke-width="1.6"/><path d="M12,0 V18 M0,9 H24" stroke="#FFFFFF" stroke-width="5"/><path d="M12,0 V18 M0,9 H24" stroke="#C8102E" stroke-width="3"/>',
            'ca' => '<rect width="6" height="18" fill="#FF0000"/><rect x="6" width="12" height="18" fill="#FFFFFF"/><rect x="18" width="6" height="18" fill="#FF0000"/><polygon points="12,4 13.5,8 16,8 13.8,10 14.5,13 12,11.5 9.5,13 10.2,10 8,8 10.5,8" fill="#FF0000"/>',
            'au' => '<rect width="24" height="18" fill="#00008B"/><rect width="11" height="9" fill="#012169"/><path d="M0,0 L11,9 M11,0 L0,9" stroke="#FFFFFF" stroke-width="1.6"/><path d="M5.5,0 V9 M0,4.5 H11" stroke="#FFFFFF" stroke-width="2.4"/><path d="M5.5,0 V9 M0,4.5 H11" stroke="#C8102E" stroke-width="1.3"/><circle cx="18" cy="5" r="1.1" fill="#FFFFFF"/><circle cx="16" cy="11" r="1" fill="#FFFFFF"/><circle cx="20" cy="10" r="1" fill="#FFFFFF"/><circle cx="18" cy="14" r="1.2" fill="#FFFFFF"/>',
            'br' => '<rect width="24" height="18" fill="#009C3B"/><polygon points="12,2.2 21.5,9 12,15.8 2.5,9" fill="#FFDF00"/><circle cx="12" cy="9" r="3.6" fill="#002776"/>',
            'pt' => '<rect width="9.5" height="18" fill="#006600"/><rect x="9.5" width="14.5" height="18" fill="#FF0000"/><circle cx="9.5" cy="9" r="3.4" fill="#FFCC00"/><circle cx="9.5" cy="9" r="2.1" fill="#FFFFFF"/>',
            'fr' => '<rect width="8" height="18" fill="#0055A4"/><rect x="8" width="8" height="18" fill="#FFFFFF"/><rect x="16" width="8" height="18" fill="#EF4135"/>',
            'de' => '<rect width="24" height="6" fill="#000000"/><rect y="6" width="24" height="6" fill="#DD0000"/><rect y="12" width="24" height="6" fill="#FFCE00"/>',
            'ch' => '<rect width="24" height="18" fill="#D52B1E"/><rect x="10" y="3.5" width="4" height="11" fill="#FFFFFF"/><rect x="6.5" y="7" width="11" height="4" fill="#FFFFFF"/>',
            'at' => '<rect width="24" height="6" fill="#ED2939"/><rect y="6" width="24" height="6" fill="#FFFFFF"/><rect y="12" width="24" height="6" fill="#ED2939"/>',
            'it' => '<rect width="8" height="18" fill="#009246"/><rect x="8" width="8" height="18" fill="#FFFFFF"/><rect x="16" width="8" height="18" fill="#CE2B37"/>',
            'nl' => '<rect width="24" height="6" fill="#AE1C28"/><rect y="6" width="24" height="6" fill="#FFFFFF"/><rect y="12" width="24" height="6" fill="#21468B"/>',
            'be' => '<rect width="8" height="18" fill="#000000"/><rect x="8" width="8" height="18" fill="#FDDA24"/><rect x="16" width="8" height="18" fill="#EF3340"/>',
            'pl' => '<rect width="24" height="9" fill="#FFFFFF"/><rect y="9" width="24" height="9" fill="#DC143C"/>',
            'ru' => '<rect width="24" height="6" fill="#FFFFFF"/><rect y="6" width="24" height="6" fill="#0039A6"/><rect y="12" width="24" height="6" fill="#D52B1E"/>',
            'ua' => '<rect width="24" height="9" fill="#0057B7"/><rect y="9" width="24" height="9" fill="#FFD700"/>',
            'se' => '<rect width="24" height="18" fill="#006AA7"/><rect x="6" width="3.5" height="18" fill="#FECC00"/><rect y="7.25" width="24" height="3.5" fill="#FECC00"/>',
            'no' => '<rect width="24" height="18" fill="#BA0C2F"/><rect x="6" width="4.5" height="18" fill="#FFFFFF"/><rect y="6.75" width="24" height="4.5" fill="#FFFFFF"/><rect x="7.2" width="2.1" height="18" fill="#00205B"/><rect y="7.95" width="24" height="2.1" fill="#00205B"/>',
            'dk' => '<rect width="24" height="18" fill="#C60C30"/><rect x="6" width="3.2" height="18" fill="#FFFFFF"/><rect y="7.4" width="24" height="3.2" fill="#FFFFFF"/>',
            'fi' => '<rect width="24" height="18" fill="#FFFFFF"/><rect x="6.5" width="3.8" height="18" fill="#003580"/><rect y="7.1" width="24" height="3.8" fill="#003580"/>',
            'gr' => '<rect width="24" height="18" fill="#0D5EAF"/><rect y="2" width="24" height="2" fill="#FFFFFF"/><rect y="6" width="24" height="2" fill="#FFFFFF"/><rect y="10" width="24" height="2" fill="#FFFFFF"/><rect y="14" width="24" height="2" fill="#FFFFFF"/><rect width="10" height="10" fill="#0D5EAF"/><rect x="4" width="2" height="10" fill="#FFFFFF"/><rect y="4" width="10" height="2" fill="#FFFFFF"/>',
            'cz' => '<rect width="24" height="9" fill="#FFFFFF"/><rect y="9" width="24" height="9" fill="#D7141A"/><polygon points="0,0 12,9 0,18" fill="#11457E"/>',
            'ro' => '<rect width="8" height="18" fill="#002B7F"/><rect x="8" width="8" height="18" fill="#FCD116"/><rect x="16" width="8" height="18" fill="#CE1126"/>',
            'hu' => '<rect width="24" height="6" fill="#CE2939"/><rect y="6" width="24" height="6" fill="#FFFFFF"/><rect y="12" width="24" height="6" fill="#477050"/>',
            'tr' => '<rect width="24" height="18" fill="#E30A17"/><circle cx="10.2" cy="9" r="4.2" fill="#FFFFFF"/><circle cx="11.4" cy="9" r="3.3" fill="#E30A17"/><polygon points="15.2,9 16.9,9.6 15.8,8.2 15.8,9.8 16.9,8.4" fill="#FFFFFF"/>',
            'jp' => '<rect width="24" height="18" fill="#FFFFFF"/><circle cx="12" cy="9" r="4.8" fill="#BC002D"/>',
            'kr' => '<rect width="24" height="18" fill="#FFFFFF"/><path d="M12,4.5 A4.5,4.5 0 0,1 16.5,9 A2.25,2.25 0 0,1 12,9 A2.25,2.25 0 0,0 7.5,9 A4.5,4.5 0 0,1 12,4.5 Z" fill="#CD2E3A"/><path d="M12,13.5 A4.5,4.5 0 0,1 7.5,9 A2.25,2.25 0 0,1 12,9 A2.25,2.25 0 0,0 16.5,9 A4.5,4.5 0 0,1 12,13.5 Z" fill="#0047A0"/>',
            'cn' => '<rect width="24" height="18" fill="#DE2910"/><polygon points="5.2,3 5.9,5 8,5 6.3,6.2 6.9,8.2 5.2,7 3.5,8.2 4.1,6.2 2.4,5 4.5,5" fill="#FFDE00"/>',
            'tw' => '<rect width="24" height="18" fill="#FE0000"/><rect width="12" height="9" fill="#000095"/><circle cx="6" cy="4.5" r="2.3" fill="#FFFFFF"/>',
            'sa' => '<rect width="24" height="18" fill="#006C35"/><rect x="5" y="11" width="14" height="1.8" rx="0.8" fill="#FFFFFF"/><rect x="6" y="6" width="12" height="2.4" rx="1" fill="#FFFFFF" opacity="0.9"/>',
            'ae' => '<rect width="24" height="6" fill="#00732F"/><rect y="6" width="24" height="6" fill="#FFFFFF"/><rect y="12" width="24" height="6" fill="#000000"/><rect width="6.5" height="18" fill="#FF0000"/>',
            'il' => '<rect width="24" height="18" fill="#FFFFFF"/><rect y="2.2" width="24" height="2.6" fill="#0038B8"/><rect y="13.2" width="24" height="2.6" fill="#0038B8"/><polygon points="12,5.6 14.8,10.6 9.2,10.6" fill="none" stroke="#0038B8" stroke-width="1.2"/><polygon points="12,12.4 14.8,7.4 9.2,7.4" fill="none" stroke="#0038B8" stroke-width="1.2"/>',
            'in' => '<rect width="24" height="6" fill="#FF9933"/><rect y="6" width="24" height="6" fill="#FFFFFF"/><rect y="12" width="24" height="6" fill="#138808"/><circle cx="12" cy="9" r="2.1" fill="none" stroke="#000080" stroke-width="1.1"/>',
            'id' => '<rect width="24" height="9" fill="#FF0000"/><rect y="9" width="24" height="9" fill="#FFFFFF"/>',
            'vn' => '<rect width="24" height="18" fill="#DA251D"/><polygon points="12,4.2 13.4,8.2 17.6,8.2 14.2,10.7 15.5,14.7 12,12.2 8.5,14.7 9.8,10.7 6.4,8.2 10.6,8.2" fill="#FFFF00"/>',
            'th' => '<rect width="24" height="3" fill="#A51931"/><rect y="3" width="24" height="3" fill="#F4F5F8"/><rect y="6" width="24" height="6" fill="#2D2A4A"/><rect y="12" width="24" height="3" fill="#F4F5F8"/><rect y="15" width="24" height="3" fill="#A51931"/>',
            'ph' => '<rect width="24" height="9" fill="#0038A8"/><rect y="9" width="24" height="9" fill="#CE1126"/><polygon points="0,0 11,9 0,18" fill="#FFFFFF"/><circle cx="4" cy="9" r="1.8" fill="#FCD116"/>',
        ];
    }

    /**
     * Intenta cargar un SVG personalizado desde assets/flags/{key}.svg si existe.
     */
    private static function load_custom_flag_svg( string $key ): string {
        static $cache = [];
        $key = strtolower( preg_replace( '/[^a-z0-9_-]/', '', $key ) );
        if ( $key === '' ) {
            return '';
        }
        if ( array_key_exists( $key, $cache ) ) {
            return $cache[ $key ];
        }

        $flag_dir = defined( 'AURA_PLUGIN_DIR' ) ? AURA_PLUGIN_DIR . 'assets/flags/' : '';
        $file     = $flag_dir ? $flag_dir . $key . '.svg' : '';
        if ( ! $file || ! is_file( $file ) || ! is_readable( $file ) ) {
            $cache[ $key ] = '';
            return '';
        }

        $raw = (string) file_get_contents( $file );
        if ( stripos( $raw, '<svg' ) === false ) {
            $cache[ $key ] = '';
            return '';
        }

        // Normalizar clase y dimensiones para integración uniforme en pills/dropdowns
        $clean = preg_replace( '/<\?xml[^>]*\?>\s*/i', '', trim( $raw ) );
        $clean = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', (string) $clean );
        $clean = preg_replace( '/<svg\b([^>]*)>/i', '<svg class="aura-flag-svg" width="18" height="13" aria-hidden="true"$1>', (string) $clean, 1 );
        $cache[ $key ] = (string) $clean;
        return $cache[ $key ];
    }

    /**
     * Devuelve una bandera SVG inline nítida y multiplataforma (funciona en Windows/Linux/macOS/Móvil).
     * Resuelve automáticamente: archivo local assets/flags/{pais}.svg -> catálogo interno -> prefijo idioma -> badge ISO.
     */
    public static function get_locale_flag_svg( string $locale ): string {
        $base = strtolower( trim( $locale ) );
        if ( $base === 'auto' ) {
            return '<svg class="aura-globe-svg" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
        }

        $svgs = self::get_builtin_flag_svgs();

        // Construir candidatos ordenados: locale completo -> código país (xx_YY -> yy) -> mapa idioma -> prefijo
        $candidates = [];
        if ( $base !== '' ) {
            $candidates[] = str_replace( '-', '_', $base );
        }
        if ( preg_match( '/^[a-z]{2,3}[_-]([a-z]{2})\b/', $base, $m ) ) {
            $candidates[] = $m[1];
        }
        $lang = substr( $base, 0, 2 );
        $lang_to_country = [
            'es' => 'es', 'en' => 'us', 'pt' => 'br', 'fr' => 'fr', 'de' => 'de',
            'it' => 'it', 'nl' => 'nl', 'ja' => 'jp', 'ko' => 'kr', 'zh' => 'cn',
            'ru' => 'ru', 'uk' => 'ua', 'ar' => 'sa', 'pl' => 'pl', 'tr' => 'tr',
            'sv' => 'se', 'nb' => 'no', 'nn' => 'no', 'da' => 'dk', 'fi' => 'fi',
            'el' => 'gr', 'cs' => 'cz', 'ro' => 'ro', 'hu' => 'hu', 'he' => 'il',
            'hi' => 'in', 'id' => 'id', 'vi' => 'vn', 'th' => 'th', 'tl' => 'ph',
        ];
        if ( isset( $lang_to_country[ $lang ] ) ) {
            $candidates[] = $lang_to_country[ $lang ];
        }
        if ( $lang !== '' ) {
            $candidates[] = $lang;
        }

        foreach ( array_unique( $candidates ) as $cand ) {
            $custom = self::load_custom_flag_svg( $cand );
            if ( $custom !== '' ) {
                return $custom;
            }
            if ( isset( $svgs[ $cand ] ) ) {
                return '<svg class="aura-flag-svg" viewBox="0 0 24 18" width="18" height="13" aria-hidden="true">' . $svgs[ $cand ] . '</svg>';
            }
        }

        $code_short = esc_html( strtoupper( substr( $locale, 0, 2 ) ?: 'GL' ) );
        return '<svg class="aura-flag-svg" viewBox="0 0 24 18" width="18" height="13" aria-hidden="true"><rect width="24" height="18" rx="2" fill="#334155"/><text x="12" y="12.5" text-anchor="middle" fill="#FFFFFF" font-size="9" font-family="sans-serif" font-weight="700">' . $code_short . '</text></svg>';
    }

    /**
     * Convierte un código ISO-3166 alpha-2 (p. ej. "US", "ES", "BR") en emoji de bandera regional. (p. ej. "US", "ES", "BR") en emoji de bandera regional.
     */
    private static function country_to_flag_emoji( string $country ): string {
        $country = strtoupper( preg_replace( '/[^A-Za-z]/', '', $country ) );
        if ( strlen( $country ) !== 2 ) {
            return '🌐';
        }
        $out = '';
        for ( $i = 0; $i < 2; $i++ ) {
            $cp = 0x1F1E6 + ( ord( $country[ $i ] ) - 65 );
            $out .= function_exists( 'mb_chr' )
                ? mb_chr( $cp, 'UTF-8' )
                : html_entity_decode( '&#' . $cp . ';', ENT_NOQUOTES, 'UTF-8' );
        }
        return $out ?: '🌐';
    }

    /**
     * Obtiene el emoji de bandera correspondiente a un código de locale (p. ej. es_ES -> 🇪🇸, en_US -> 🇺🇸).
     */
    public static function get_locale_flag( string $locale ): string {
        $catalog = self::get_locale_catalog();
        if ( isset( $catalog[ $locale ]['flag'] ) ) {
            return $catalog[ $locale ]['flag'];
        }
        if ( preg_match( '/^[a-z]{2,3}_([A-Za-z]{2})\b/', $locale, $m ) ) {
            return self::country_to_flag_emoji( $m[1] );
        }
        $lang = strtolower( substr( $locale, 0, 2 ) );
        $fallbacks = [
            'es' => '🇪🇸',
            'en' => '🇺🇸',
            'pt' => '🇧🇷',
            'fr' => '🇫🇷',
            'de' => '🇩🇪',
            'it' => '🇮🇹',
        ];
        return $fallbacks[ $lang ] ?? '🌐';
    }

    /**
     * Catálogo conocido de metadatos visuales por locale.
     *
     * @return array<string, array<string, string>>
     */
    private static function get_locale_catalog(): array {
        return [
            'es_ES' => [ 'code' => 'es_ES', 'short' => 'ES', 'flag' => '🇪🇸', 'label' => 'Español',           'native' => 'Español' ],
            'es_MX' => [ 'code' => 'es_MX', 'short' => 'ES', 'flag' => '🇲🇽', 'label' => 'Español (MX)',      'native' => 'Español (México)' ],
            'es_CO' => [ 'code' => 'es_CO', 'short' => 'ES', 'flag' => '🇨🇴', 'label' => 'Español (CO)',      'native' => 'Español (Colombia)' ],
            'es_CL' => [ 'code' => 'es_CL', 'short' => 'ES', 'flag' => '🇨🇱', 'label' => 'Español (CL)',      'native' => 'Español (Chile)' ],
            'es_AR' => [ 'code' => 'es_AR', 'short' => 'ES', 'flag' => '🇦🇷', 'label' => 'Español (AR)',      'native' => 'Español (Argentina)' ],
            'es_PE' => [ 'code' => 'es_PE', 'short' => 'ES', 'flag' => '🇵🇪', 'label' => 'Español (PE)',      'native' => 'Español (Perú)' ],
            'en_US' => [ 'code' => 'en_US', 'short' => 'EN', 'flag' => '🇺🇸', 'label' => 'English (US)',      'native' => 'English (US)' ],
            'en_GB' => [ 'code' => 'en_GB', 'short' => 'EN', 'flag' => '🇬🇧', 'label' => 'English (UK)',      'native' => 'English (UK)' ],
            'en_CA' => [ 'code' => 'en_CA', 'short' => 'EN', 'flag' => '🇨🇦', 'label' => 'English (CA)',      'native' => 'English (Canada)' ],
            'en_AU' => [ 'code' => 'en_AU', 'short' => 'EN', 'flag' => '🇦🇺', 'label' => 'English (AU)',      'native' => 'English (Australia)' ],
            'pt_BR' => [ 'code' => 'pt_BR', 'short' => 'PT', 'flag' => '🇧🇷', 'label' => 'Português (BR)',    'native' => 'Português (Brasil)' ],
            'pt_PT' => [ 'code' => 'pt_PT', 'short' => 'PT', 'flag' => '🇵🇹', 'label' => 'Português (PT)',    'native' => 'Português' ],
            'fr_FR' => [ 'code' => 'fr_FR', 'short' => 'FR', 'flag' => '🇫🇷', 'label' => 'Français',          'native' => 'Français' ],
            'fr_CA' => [ 'code' => 'fr_CA', 'short' => 'FR', 'flag' => '🇨🇦', 'label' => 'Français (CA)',     'native' => 'Français (Canada)' ],
            'de_DE' => [ 'code' => 'de_DE', 'short' => 'DE', 'flag' => '🇩🇪', 'label' => 'Deutsch',           'native' => 'Deutsch' ],
            'it_IT' => [ 'code' => 'it_IT', 'short' => 'IT', 'flag' => '🇮🇹', 'label' => 'Italiano',          'native' => 'Italiano' ],
            'nl_NL' => [ 'code' => 'nl_NL', 'short' => 'NL', 'flag' => '🇳🇱', 'label' => 'Nederlands',        'native' => 'Nederlands' ],
            'pl_PL' => [ 'code' => 'pl_PL', 'short' => 'PL', 'flag' => '🇵🇱', 'label' => 'Polski',            'native' => 'Polski' ],
            'ru_RU' => [ 'code' => 'ru_RU', 'short' => 'RU', 'flag' => '🇷🇺', 'label' => 'Русский',           'native' => 'Русский' ],
            'uk'    => [ 'code' => 'uk',    'short' => 'UK', 'flag' => '🇺🇦', 'label' => 'Українська',        'native' => 'Українська' ],
            'tr_TR' => [ 'code' => 'tr_TR', 'short' => 'TR', 'flag' => '🇹🇷', 'label' => 'Türkçe',            'native' => 'Türkçe' ],
            'ja'    => [ 'code' => 'ja',    'short' => 'JA', 'flag' => '🇯🇵', 'label' => '日本語',             'native' => '日本語' ],
            'ja_JP' => [ 'code' => 'ja_JP', 'short' => 'JA', 'flag' => '🇯🇵', 'label' => '日本語',             'native' => '日本語' ],
            'ko_KR' => [ 'code' => 'ko_KR', 'short' => 'KO', 'flag' => '🇰🇷', 'label' => '한국어',             'native' => '한국어' ],
            'zh_CN' => [ 'code' => 'zh_CN', 'short' => 'ZH', 'flag' => '🇨🇳', 'label' => '简体中文',           'native' => '简体中文' ],
            'zh_TW' => [ 'code' => 'zh_TW', 'short' => 'ZH', 'flag' => '🇹🇼', 'label' => '繁體中文',           'native' => '繁體中文' ],
            'ar'    => [ 'code' => 'ar',    'short' => 'AR', 'flag' => '🇸🇦', 'label' => 'العربية',           'native' => 'العربية' ],
            'he_IL' => [ 'code' => 'he_IL', 'short' => 'HE', 'flag' => '🇮🇱', 'label' => 'עברית',             'native' => 'עברית' ],
            'hi_IN' => [ 'code' => 'hi_IN', 'short' => 'HI', 'flag' => '🇮🇳', 'label' => 'हिन्दी',            'native' => 'हिन्दी' ],
            'sv_SE' => [ 'code' => 'sv_SE', 'short' => 'SV', 'flag' => '🇸🇪', 'label' => 'Svenska',           'native' => 'Svenska' ],
            'nb_NO' => [ 'code' => 'nb_NO', 'short' => 'NB', 'flag' => '🇳🇴', 'label' => 'Norsk bokmål',      'native' => 'Norsk bokmål' ],
            'da_DK' => [ 'code' => 'da_DK', 'short' => 'DA', 'flag' => '🇩🇰', 'label' => 'Dansk',             'native' => 'Dansk' ],
            'fi'    => [ 'code' => 'fi',    'short' => 'FI', 'flag' => '🇫🇮', 'label' => 'Suomi',             'native' => 'Suomi' ],
            'el'    => [ 'code' => 'el',    'short' => 'EL', 'flag' => '🇬🇷', 'label' => 'Ελληνικά',          'native' => 'Ελληνικά' ],
            'cs_CZ' => [ 'code' => 'cs_CZ', 'short' => 'CS', 'flag' => '🇨🇿', 'label' => 'Čeština',           'native' => 'Čeština' ],
            'ro_RO' => [ 'code' => 'ro_RO', 'short' => 'RO', 'flag' => '🇷🇴', 'label' => 'Română',            'native' => 'Română' ],
            'hu_HU' => [ 'code' => 'hu_HU', 'short' => 'HU', 'flag' => '🇭🇺', 'label' => 'Magyar',            'native' => 'Magyar' ],
            'vi'    => [ 'code' => 'vi',    'short' => 'VI', 'flag' => '🇻🇳', 'label' => 'Tiếng Việt',        'native' => 'Tiếng Việt' ],
            'th'    => [ 'code' => 'th',    'short' => 'TH', 'flag' => '🇹🇭', 'label' => 'ไทย',               'native' => 'ไทย' ],
            'id_ID' => [ 'code' => 'id_ID', 'short' => 'ID', 'flag' => '🇮🇩', 'label' => 'Bahasa Indonesia',  'native' => 'Bahasa Indonesia' ],
        ];
    }

    /**
     * Devuelve los idiomas disponibles en el plugin (Español base + paquetes compilados en /languages).
     *
     * @return array<string, array<string, string>>
     */
    public static function get_available_locales(): array {
        $catalog   = self::get_locale_catalog();
        $available = [
            'es_ES' => $catalog['es_ES'],
        ];

        $lang_dir = defined( 'AURA_PLUGIN_DIR' ) ? AURA_PLUGIN_DIR . 'languages/' : '';
        if ( $lang_dir && is_dir( $lang_dir ) ) {
            $files = array_merge(
                glob( $lang_dir . 'aura-suite-*.mo' ) ?: [],
                glob( $lang_dir . 'aura-suite-*.l10n.php' ) ?: []
            );
            foreach ( $files as $file ) {
                $base = basename( $file );
                if ( preg_match( '/^aura-suite-([A-Za-z0-9_@-]+)\.(?:mo|l10n\.php)$/', $base, $m ) ) {
                    $code = $m[1];
                    if ( isset( $available[ $code ] ) ) {
                        continue;
                    }
                    if ( isset( $catalog[ $code ] ) ) {
                        $available[ $code ] = $catalog[ $code ];
                    } else {
                        $short  = strtoupper( substr( $code, 0, 2 ) );
                        $native = $code;
                        if ( class_exists( 'Locale' ) && method_exists( 'Locale', 'getDisplayName' ) ) {
                            $disp = Locale::getDisplayName( $code, $code );
                            if ( is_string( $disp ) && $disp !== '' && strtolower( $disp ) !== strtolower( $code ) ) {
                                $native = function_exists( 'mb_convert_case' )
                                    ? mb_convert_case( $disp, MB_CASE_TITLE, 'UTF-8' )
                                    : ucfirst( $disp );
                            }
                        }
                        $available[ $code ] = [
                            'code'   => $code,
                            'short'  => $short,
                            'flag'   => self::get_locale_flag( $code ),
                            'label'  => $native,
                            'native' => $native,
                        ];
                    }
                }
            }
        }

        return $available;
    }

    /**
     * Obtiene la preferencia explícita de idioma del usuario actual ('', 'es_ES', 'en_US', etc.).
     */
    public static function get_user_preferred_locale(): string {
        static $resolving = false;
        if ( $resolving ) {
            return '';
        }
        $resolving = true;

        $pref = '';
        if ( function_exists( 'is_user_logged_in' ) && function_exists( 'get_current_user_id' ) && did_action( 'plugins_loaded' ) ) {
            $uid = get_current_user_id();
            if ( $uid > 0 ) {
                $pref = (string) get_user_meta( $uid, self::META_KEY, true );
            }
        }

        if ( $pref === '' && ! empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $pref = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
        }

        $resolving = false;

        if ( $pref === '' || $pref === 'auto' ) {
            return '';
        }

        $available = self::get_available_locales();
        return isset( $available[ $pref ] ) ? $pref : '';
    }

    /**
     * Obtiene el idioma global configurado en Ajustes de AURA ('', 'es_ES', 'en_US', etc.).
     * '' significa respetar el idioma nativo de WordPress.
     */
    public static function get_global_default_locale(): string {
        if ( ! function_exists( 'get_option' ) ) {
            return '';
        }
        $opt = (string) get_option( self::OPTION_DEFAULT_LOCALE, '' );
        if ( $opt === '' || $opt === 'auto' ) {
            return '';
        }
        $available = self::get_available_locales();
        return isset( $available[ $opt ] ) ? $opt : '';
    }

    /**
     * Devuelve el idioma de anulación efectivo para AURA:
     * 1) Preferencia personal del usuario (selector rápido / cookie),
     * 2) Idioma global forzado en Ajustes de AURA,
     * 3) '' (usar el de WordPress).
     */
    public static function get_effective_override_locale(): string {
        $pref = self::get_user_preferred_locale();
        if ( $pref !== '' ) {
            return $pref;
        }
        return self::get_global_default_locale();
    }

    /**
     * Devuelve el código de idioma efectivo actual para renderizar el selector.
     */
    public static function get_active_locale(): string {
        $override = self::get_effective_override_locale();
        if ( $override !== '' ) {
            return $override;
        }
        $wp_loc = function_exists( 'get_locale' ) ? get_locale() : 'es_ES';
        if ( strpos( $wp_loc, 'es_' ) === 0 ) {
            return 'es_ES';
        }
        $available = self::get_available_locales();
        if ( isset( $available[ $wp_loc ] ) ) {
            return $wp_loc;
        }
        $lang_prefix = substr( $wp_loc, 0, 2 );
        foreach ( $available as $code => $meta ) {
            if ( strpos( $code, $lang_prefix . '_' ) === 0 ) {
                return $code;
            }
        }
        return 'es_ES';
    }

    /**
     * Filtro de WordPress `determine_locale`: aplica la preferencia personal del usuario
     * o el idioma global forzado cuando la petición corresponde a una pantalla de AURA.
     */
    public static function filter_determine_locale( $locale ) {
        $pref = self::get_user_preferred_locale();
        if ( $pref !== '' ) {
            return $pref;
        }
        $global = self::get_global_default_locale();
        if ( $global !== '' && self::is_aura_request() ) {
            return $global;
        }
        return $locale;
    }

    /**
     * Determina si la petición actual pertenece a una pantalla administrativa o parámetro de AURA.
     */
    private static function is_aura_request(): bool {
        if ( isset( $_GET['page'] ) && strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'aura' ) === 0 ) {
            return true;
        }
        if ( isset( $_REQUEST['action'] ) && strpos( sanitize_key( wp_unslash( $_REQUEST['action'] ) ), 'aura' ) === 0 ) {
            return true;
        }
        return false;
    }

    /**
     * Filtro `plugin_locale` específico para aura-suite.
     */
    public static function filter_plugin_locale( $locale, $domain ) {
        if ( $domain !== self::TEXT_DOMAIN ) {
            return $locale;
        }
        $override = self::get_effective_override_locale();
        return $override !== '' ? $override : $locale;
    }

    /**
     * Cuando el idioma activo es español, usar directamente las cadenas nativas del código fuente.
     */
    public static function maybe_use_native_spanish( $override, $domain, $mofile ) {
        if ( $domain !== self::TEXT_DOMAIN ) {
            return $override;
        }
        $active = self::get_effective_override_locale();
        if ( $active !== '' && strpos( $active, 'es_' ) === 0 ) {
            return true;
        }
        return $override;
    }

    /**
     * Ajusta la ruta del archivo JSON JED si AURA tiene un idioma anulado (personal o global).
     */
    public static function filter_script_translation_file( $file, $handle, $domain ) {
        if ( $domain !== self::TEXT_DOMAIN || ! $file ) {
            return $file;
        }
        $active = self::get_effective_override_locale();
        if ( $active === '' ) {
            return $file;
        }
        if ( preg_match( '#^(.+[\\\\/]aura-suite-)[A-Za-z0-9_@-]+(-[a-f0-9]{32}\.json)$#i', (string) $file, $m ) ) {
            return $m[1] . $active . $m[2];
        }
        return $file;
    }

    /**
     * Respaldo para entornos Windows donde `is_readable()` devuelve false sobre JSON recién compilados,
     * y soporte para español nativo cuando WP esté en otro idioma.
     */
    public static function fallback_read_script_translations( $translations, $file, $handle, $domain ) {
        if ( null !== $translations || $domain !== self::TEXT_DOMAIN ) {
            return $translations;
        }
        $active = self::get_effective_override_locale();
        if ( $active !== '' && strpos( $active, 'es_' ) === 0 ) {
            return wp_json_encode( [
                'domain'      => 'messages',
                'locale_data' => [
                    'messages' => [
                        '' => [ 'domain' => 'messages', 'lang' => $active ],
                    ],
                ],
            ] );
        }
        $filtered = apply_filters( 'load_script_translation_file', $file, $handle, $domain );
        if ( $filtered && is_file( $filtered ) ) {
            $raw = @file_get_contents( $filtered );
            if ( is_string( $raw ) && $raw !== '' ) {
                return $raw;
            }
        }
        return $translations;
    }

    /**
     * Recarga las traducciones de aura-suite en `init` (prioridad 0) una vez autenticado el usuario.
     */
    public static function reload_plugin_textdomain(): void {
        $pref = self::get_effective_override_locale();
        if ( $pref === '' ) {
            return;
        }

        if ( strpos( $pref, 'es_' ) === 0 ) {
            unload_textdomain( self::TEXT_DOMAIN );
            return;
        }

        unload_textdomain( self::TEXT_DOMAIN );
        $loaded = load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname( AURA_PLUGIN_BASENAME ) . '/languages'
        );

        // Respaldo directo si el locale exacto no coincidió vía load_plugin_textdomain
        if ( ! $loaded && defined( 'AURA_PLUGIN_DIR' ) ) {
            $php_file = AURA_PLUGIN_DIR . 'languages/aura-suite-' . $pref . '.l10n.php';
            $mo_file  = AURA_PLUGIN_DIR . 'languages/aura-suite-' . $pref . '.mo';
            if ( file_exists( $php_file ) && function_exists( 'load_textdomain' ) ) {
                $loaded = load_textdomain( self::TEXT_DOMAIN, $php_file, $pref );
            }
            if ( ! $loaded && file_exists( $mo_file ) && function_exists( 'load_textdomain' ) ) {
                $loaded = load_textdomain( self::TEXT_DOMAIN, $mo_file, $pref );
            }
            if ( ! $loaded && file_exists( $mo_file ) && class_exists( 'MO' ) ) {
                global $l10n;
                $mo = new MO();
                if ( $mo->import_from_file( $mo_file ) ) {
                    $l10n[ self::TEXT_DOMAIN ] = $mo;
                }
            }
        }
    }

    /**
     * Construye la URL nonce-protegida para cambiar a un idioma y volver a la pantalla actual.
     */
    public static function get_switch_url( string $target_locale, string $redirect_to = '' ): string {
        if ( $redirect_to === '' ) {
            $scheme = is_ssl() ? 'https://' : 'http://';
            $host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
            $uri    = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
            $redirect_to = $host ? ( $scheme . $host . $uri ) : ( is_admin() ? admin_url() : home_url() );
        }

        return wp_nonce_url(
            add_query_arg(
                [
                    'action'      => 'aura_switch_locale',
                    'locale'      => $target_locale,
                    'redirect_to' => $redirect_to,
                ],
                admin_url( 'admin-post.php' )
            ),
            self::NONCE_ACT
        );
    }

    /**
     * Handler admin_post para guardar la preferencia de idioma del usuario y redirigir.
     */
    public static function handle_switch_locale(): void {
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE_ACT ) ) {
            wp_die( esc_html__( 'Enlace de cambio de idioma inválido o expirado.', 'aura-suite' ) );
        }

        $requested = isset( $_GET['locale'] ) ? sanitize_text_field( wp_unslash( $_GET['locale'] ) ) : '';
        $available = self::get_available_locales();

        if ( $requested === 'auto' ) {
            if ( is_user_logged_in() ) {
                delete_user_meta( get_current_user_id(), self::META_KEY );
            }
            setcookie( self::COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        } elseif ( isset( $available[ $requested ] ) ) {
            if ( is_user_logged_in() ) {
                update_user_meta( get_current_user_id(), self::META_KEY, $requested );
            }
            setcookie( self::COOKIE_NAME, $requested, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }

        $redirect = isset( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : '';
        $fallback = wp_get_referer() ?: ( is_admin() ? admin_url() : home_url() );
        wp_safe_redirect( $redirect ?: $fallback );
        exit;
    }

    /**
     * Añade el selector rápido con bandera y código en la Barra Superior de WordPress (#wpadminbar).
     */
    public static function add_admin_bar_switcher( $wp_admin_bar ): void {
        if ( ! is_user_logged_in() || ! $wp_admin_bar ) {
            return;
        }

        $available = self::get_available_locales();
        if ( count( $available ) < 2 ) {
            return;
        }

        $active_code = self::get_active_locale();
        $active_meta = $available[ $active_code ] ?? reset( $available );
        $user_pref   = self::get_user_preferred_locale();

        $trigger_html = sprintf(
            '<span class="aura-ab-lang-trigger"><span class="aura-ab-lang-flag" aria-hidden="true">%s</span><span class="aura-ab-lang-code">%s</span><span class="aura-ab-lang-caret" aria-hidden="true">▾</span></span>',
            self::get_locale_flag_svg( $active_code ),
            esc_html( $active_meta['short'] )
        );

        $wp_admin_bar->add_node( [
            'id'     => 'aura-lang-switcher',
            'parent' => 'top-secondary',
            'title'  => $trigger_html,
            'href'   => '#',
            'meta'   => [
                'title' => __( 'Cambiar idioma de interfaz AURA (preferencia personal)', 'aura-suite' ),
                'class' => 'aura-ab-lang-node',
            ],
        ] );

        foreach ( $available as $code => $meta ) {
            $is_current = ( $active_code === $code );
            $item_html  = sprintf(
                '<span class="aura-ab-lang-option%s"><span class="aura-ab-lang-opt-flag" aria-hidden="true">%s</span><span class="aura-ab-lang-opt-name">%s</span><span class="aura-ab-lang-opt-badge">%s</span>%s</span>',
                $is_current ? ' is-current' : '',
                self::get_locale_flag_svg( $code ),
                esc_html( $meta['native'] ),
                esc_html( $meta['short'] ),
                $is_current ? '<span class="aura-ab-lang-opt-check" aria-hidden="true">✓</span>' : ''
            );

            $wp_admin_bar->add_node( [
                'id'     => 'aura-lang-' . sanitize_key( $code ),
                'parent' => 'aura-lang-switcher',
                'title'  => $item_html,
                'href'   => self::get_switch_url( $code ),
                'meta'   => [
                    'class' => 'aura-ab-lang-subnode',
                ],
            ] );
        }

        $auto_html = sprintf(
            '<span class="aura-ab-lang-option aura-ab-lang-option--auto%s"><span class="aura-ab-lang-opt-flag" aria-hidden="true">' . self::get_locale_flag_svg( 'auto' ) . '</span><span class="aura-ab-lang-opt-name">%s</span>%s</span>',
            $user_pref === '' ? ' is-current' : '',
            esc_html__( 'Usar idioma de WordPress', 'aura-suite' ),
            $user_pref === '' ? '<span class="aura-ab-lang-opt-check" aria-hidden="true">✓</span>' : ''
        );

        $wp_admin_bar->add_node( [
            'id'     => 'aura-lang-auto',
            'parent' => 'aura-lang-switcher',
            'title'  => $auto_html,
            'href'   => self::get_switch_url( 'auto' ),
            'meta'   => [
                'class' => 'aura-ab-lang-subnode aura-ab-lang-subnode--auto',
            ],
        ] );
    }

    /**
     * Renderiza el selector desplegable compacto con bandera para Portales Frontend.
     *
     * @param array $args ['compact' => bool]
     */
    public static function get_switcher_html( array $args = [] ): string {
        static $script_emitted = false;

        $available = self::get_available_locales();
        if ( count( $available ) < 2 ) {
            return '';
        }

        if ( function_exists( 'wp_style_is' ) && ! wp_style_is( 'aura-lang-switcher', 'enqueued' ) ) {
            self::enqueue_assets();
        }
        if ( function_exists( 'wp_style_is' ) && ! wp_style_is( 'dashicons', 'enqueued' ) ) {
            wp_enqueue_style( 'dashicons' );
        }

        $active_code  = self::get_active_locale();
        $active_meta  = $available[ $active_code ] ?? reset( $available );
        $compact      = ! empty( $args['compact'] );
        $trigger_text = $compact ? $active_meta['short'] : $active_meta['native'];

        ob_start();
        ?>
        <div class="aura-lang-switcher aura-lang-dropdown <?php echo $compact ? 'aura-lang-switcher--compact' : ''; ?>"
             data-aura-lang-dropdown
             data-tooltip="<?php esc_attr_e( 'Idioma personal de la interfaz (no altera los datos en base de datos)', 'aura-suite' ); ?>">
            <button type="button"
                    class="aura-lang-trigger"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-label="<?php esc_attr_e( 'Idioma de interfaz', 'aura-suite' ); ?>">
                <span class="aura-lang-trigger__flag" aria-hidden="true"><?php echo self::get_locale_flag_svg( $active_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="aura-lang-trigger__label"><?php echo esc_html( $trigger_text ); ?></span>
                <span class="aura-lang-trigger__caret" aria-hidden="true">▾</span>
            </button>
            <ul class="aura-lang-menu" role="listbox" aria-label="<?php esc_attr_e( 'Idioma de interfaz', 'aura-suite' ); ?>">
                <?php foreach ( $available as $code => $meta ) :
                    $is_active = ( $active_code === $code );
                ?>
                    <li role="none">
                        <a href="<?php echo esc_url( self::get_switch_url( $code ) ); ?>"
                           class="aura-lang-option <?php echo $is_active ? 'is-active' : ''; ?>"
                           role="option"
                           aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                           title="<?php echo esc_attr( $meta['native'] ); ?>">
                            <span class="aura-lang-option__flag" aria-hidden="true"><?php echo self::get_locale_flag_svg( $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            <span class="aura-lang-option__name"><?php echo esc_html( $meta['native'] ); ?></span>
                            <span class="aura-lang-option__code"><?php echo esc_html( $meta['short'] ); ?></span>
                            <?php if ( $is_active ) : ?>
                                <span class="aura-lang-option__check" aria-hidden="true">✓</span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ( ! $script_emitted ) : $script_emitted = true; ?>
        <script>
        (function () {
            if (window.__auraLangDropdownInit) return;
            window.__auraLangDropdownInit = true;

            function closeAll(except) {
                document.querySelectorAll('[data-aura-lang-dropdown].is-open').forEach(function (el) {
                    if (el !== except) {
                        el.classList.remove('is-open');
                        var btn = el.querySelector('.aura-lang-trigger');
                        if (btn) btn.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            document.addEventListener('click', function (e) {
                var trigger = e.target.closest ? e.target.closest('.aura-lang-trigger') : null;
                if (trigger) {
                    var wrap = trigger.closest('[data-aura-lang-dropdown]');
                    if (!wrap) return;
                    e.preventDefault();
                    e.stopPropagation();
                    var willOpen = !wrap.classList.contains('is-open');
                    closeAll(wrap);
                    wrap.classList.toggle('is-open', willOpen);
                    trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                    return;
                }
                if (!e.target.closest || !e.target.closest('[data-aura-lang-dropdown]')) {
                    closeAll(null);
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeAll(null);
                }
            });
        })();
        </script>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Imprime el selector compacto.
     */
    public static function render_switcher( array $args = [] ): void {
        echo self::get_switcher_html( $args );
    }

    /**
     * Encola los estilos del selector en Admin y Frontend.
     */
    public static function enqueue_assets(): void {
        if ( ! defined( 'AURA_PLUGIN_URL' ) || ! defined( 'AURA_PLUGIN_DIR' ) ) {
            return;
        }
        $rel = 'assets/css/aura-lang-switcher.css';
        $ver = file_exists( AURA_PLUGIN_DIR . $rel ) ? filemtime( AURA_PLUGIN_DIR . $rel ) : AURA_VERSION;
        wp_enqueue_style( 'aura-lang-switcher', AURA_PLUGIN_URL . $rel, [], $ver );
    }

    /**
     * Adjunta automáticamente 'wp-i18n' y wp_set_script_translations() a los scripts JS propios de AURA.
     * Evita duplicar la llamada en cada módulo y cubre scripts encolados tanto en hook como en plantilla.
     */
    public static function attach_script_translations(): void {
        if ( ! function_exists( 'wp_scripts' ) || ! function_exists( 'wp_set_script_translations' ) || ! defined( 'AURA_PLUGIN_URL' ) || ! defined( 'AURA_PLUGIN_DIR' ) ) {
            return;
        }
        $wp_scripts = wp_scripts();
        if ( ! $wp_scripts || empty( $wp_scripts->registered ) ) {
            return;
        }
        $plugin_url = AURA_PLUGIN_URL;
        $lang_dir   = AURA_PLUGIN_DIR . 'languages';

        foreach ( $wp_scripts->registered as $handle => $dep ) {
            $src = (string) ( $dep->src ?? '' );
            if ( $src === '' || strpos( $src, $plugin_url ) !== 0 ) {
                continue;
            }
            $rel = substr( $src, strlen( $plugin_url ) );
            if ( ! preg_match( '#^(assets/js/|modules/[^/]+/assets/js/)#', $rel ) ) {
                continue;
            }
            if ( strpos( $rel, '/vendor/' ) !== false ) {
                continue;
            }
            if ( ! in_array( 'wp-i18n', (array) $dep->deps, true ) ) {
                $dep->deps[] = 'wp-i18n';
            }
            wp_set_script_translations( $handle, self::TEXT_DOMAIN, $lang_dir );
        }
    }

    /**
     * Carga las clases POMO de WordPress necesarias para leer y compilar archivos Gettext (.po / .mo).
     */
    private static function load_pomo_classes(): bool {
        if ( ! class_exists( 'PO' ) && defined( 'ABSPATH' ) && defined( 'WPINC' ) ) {
            $po_inc = ABSPATH . WPINC . '/pomo/po.php';
            if ( file_exists( $po_inc ) ) {
                require_once $po_inc;
            }
        }
        if ( ! class_exists( 'MO' ) && defined( 'ABSPATH' ) && defined( 'WPINC' ) ) {
            $mo_inc = ABSPATH . WPINC . '/pomo/mo.php';
            if ( file_exists( $mo_inc ) ) {
                require_once $mo_inc;
            }
        }
        return class_exists( 'PO' ) && class_exists( 'MO' );
    }

    /**
     * Serializador compacto de arrays PHP para archivos `.l10n.php` nativos de WordPress 6.5+.
     */
    private static function short_export( $value ): string {
        if ( ! is_array( $value ) ) {
            return var_export( $value, true );
        }
        $parts   = [];
        $is_list = array_is_list( $value );
        foreach ( $value as $k => $v ) {
            $parts[] = $is_list
                ? self::short_export( $v )
                : var_export( $k, true ) . '=>' . self::short_export( $v );
        }
        return '[' . implode( ',', $parts ) . ']';
    }

    /**
     * Escanea la carpeta `/languages` y devuelve metadatos y cobertura de cada paquete instalado.
     *
     * @return array{pot_exists:bool,pot_count:int,pot_mtime:int,packages:array<int,array<string,mixed>>}
     */
    public static function get_installed_packages_report(): array {
        $lang_dir = defined( 'AURA_PLUGIN_DIR' ) ? AURA_PLUGIN_DIR . 'languages/' : '';
        $pot_file = $lang_dir . 'aura-suite.pot';
        $pot_exists = $lang_dir && file_exists( $pot_file );
        $pot_count  = 0;

        self::load_pomo_classes();

        if ( $pot_exists && class_exists( 'PO' ) ) {
            $pot = new PO();
            if ( $pot->import_from_file( $pot_file ) ) {
                $pot_count = count( $pot->entries );
            }
        }

        $catalog  = self::get_locale_catalog();
        $packages = [];

        // Español nativo integrado
        $packages['es_ES'] = [
            'code'             => 'es_ES',
            'label'            => $catalog['es_ES']['native'],
            'native'           => $catalog['es_ES']['native'],
            'short'            => 'ES',
            'flag'             => $catalog['es_ES']['flag'],
            'flag_svg'         => self::get_locale_flag_svg( 'es_ES' ),
            'is_native'        => true,
            'has_po'           => false,
            'has_mo'           => false,
            'has_l10n_php'     => false,
            'json_count'       => 0,
            'translated_count' => $pot_count,
            'total_count'      => $pot_count,
            'percent'          => 100.0,
            'needs_compile'    => false,
            'po_mtime'         => 0,
        ];

        if ( ! $lang_dir || ! is_dir( $lang_dir ) ) {
            return [
                'pot_exists' => $pot_exists,
                'pot_count'  => $pot_count,
                'pot_mtime'  => $pot_exists ? (int) filemtime( $pot_file ) : 0,
                'packages'   => array_values( $packages ),
            ];
        }

        $found_codes = [];
        foreach ( glob( $lang_dir . 'aura-suite-*.*' ) ?: [] as $f ) {
            $base = basename( $f );
            if ( preg_match( '/^aura-suite-([A-Za-z0-9_@-]+)\.(?:po|mo|l10n\.php)$/', $base, $m ) ) {
                $found_codes[ $m[1] ] = true;
            }
        }

        foreach ( array_keys( $found_codes ) as $code ) {
            if ( $code === 'es_ES' ) {
                continue;
            }
            $po_file  = $lang_dir . "aura-suite-{$code}.po";
            $mo_file  = $lang_dir . "aura-suite-{$code}.mo";
            $php_file = $lang_dir . "aura-suite-{$code}.l10n.php";

            $has_po  = file_exists( $po_file );
            $has_mo  = file_exists( $mo_file );
            $has_php = file_exists( $php_file );
            $jsons   = glob( $lang_dir . "aura-suite-{$code}-*.json" ) ?: [];

            $translated = 0;
            $total      = $pot_count;

            if ( $has_po && class_exists( 'PO' ) ) {
                $po = new PO();
                if ( $po->import_from_file( $po_file ) ) {
                    $po_total = count( $po->entries );
                    if ( $po_total > $total ) {
                        $total = $po_total;
                    }
                    foreach ( $po->entries as $entry ) {
                        if ( ! empty( $entry->singular ) && ! empty( array_filter( $entry->translations, static fn( $t ) => $t !== '' ) ) ) {
                            $translated++;
                        }
                    }
                }
            } elseif ( $has_php ) {
                $php_data = @include $php_file;
                if ( is_array( $php_data ) && ! empty( $php_data['messages'] ) ) {
                    $translated = count( $php_data['messages'] );
                }
            }

            $po_mtime      = $has_po ? (int) filemtime( $po_file ) : 0;
            $mo_mtime      = $has_mo ? (int) filemtime( $mo_file ) : 0;
            $needs_compile = $has_po && ( ! $has_mo || ! $has_php || $po_mtime > $mo_mtime );
            $pct           = $total > 0 ? min( 100.0, round( ( $translated / $total ) * 100, 1 ) ) : 0.0;

            $meta = $catalog[ $code ] ?? [
                'code'   => $code,
                'short'  => strtoupper( substr( $code, 0, 2 ) ),
                'flag'   => self::get_locale_flag( $code ),
                'label'  => $code,
                'native' => $code,
            ];

            $packages[ $code ] = [
                'code'             => $code,
                'label'            => $meta['label'],
                'native'           => $meta['native'],
                'short'            => $meta['short'],
                'flag'             => $meta['flag'] ?? self::get_locale_flag( $code ),
                'flag_svg'         => self::get_locale_flag_svg( $code ),
                'is_native'        => false,
                'has_po'           => $has_po,
                'has_mo'           => $has_mo,
                'has_l10n_php'     => $has_php,
                'json_count'       => count( $jsons ),
                'translated_count' => $translated,
                'total_count'      => $total,
                'percent'          => $pct,
                'needs_compile'    => $needs_compile,
                'po_mtime'         => $po_mtime,
            ];
        }

        return [
            'pot_exists' => $pot_exists,
            'pot_count'  => $pot_count,
            'pot_mtime'  => $pot_exists ? (int) filemtime( $pot_file ) : 0,
            'packages'   => array_values( $packages ),
        ];
    }

    /**
     * Compila un archivo `.po` hacia `.mo`, `.l10n.php` y archivos JED `.json` compatibles con Poedit / Loco Translate.
     *
     * @return array<string,mixed>|\WP_Error
     */
    public static function compile_po_file( string $po_file ) {
        if ( ! file_exists( $po_file ) || ! is_readable( $po_file ) ) {
            return new WP_Error( 'aura_i18n_missing_po', __( 'Archivo .po no encontrado o ilegible.', 'aura-suite' ) );
        }
        if ( ! self::load_pomo_classes() ) {
            return new WP_Error( 'aura_i18n_no_pomo', __( 'Las librerías Gettext POMO de WordPress no están disponibles.', 'aura-suite' ) );
        }

        $po = new PO();
        if ( ! $po->import_from_file( $po_file ) ) {
            return new WP_Error( 'aura_i18n_invalid_po', __( 'El archivo .po no tiene un formato Gettext válido.', 'aura-suite' ) );
        }

        $locale = '';
        if ( preg_match( '/^aura-suite-([A-Za-z0-9_@-]+)\.po$/', basename( $po_file ), $m ) ) {
            $locale = $m[1];
        }

        $lang_dir         = dirname( $po_file );
        $mo               = new MO();
        $mo->headers      = $po->headers;
        $messages         = [];
        $js_bundles       = [];
        $translated_count = 0;

        foreach ( $po->entries as $entry ) {
            if ( empty( $entry->singular ) ) {
                continue;
            }
            $has_trans = ! empty( array_filter( $entry->translations, static fn( $t ) => $t !== '' ) );
            if ( ! $has_trans ) {
                continue;
            }

            $mo->add_entry( $entry );
            $msg_key               = ( ! empty( $entry->context ) ? $entry->context . "\x04" : '' ) . $entry->singular;
            $messages[ $msg_key ]  = implode( "\0", $entry->translations );
            $translated_count++;

            if ( ! empty( $entry->references ) && is_array( $entry->references ) ) {
                $seen_js = [];
                foreach ( $entry->references as $ref ) {
                    $file_only = preg_replace( '/:\d+$/', '', str_replace( '\\', '/', trim( (string) $ref ) ) );
                    if ( preg_match( '#^(?:assets/js/|modules/[^/]+/assets/js/).+\.js$#i', $file_only ) && ! isset( $seen_js[ $file_only ] ) ) {
                        $seen_js[ $file_only ]               = true;
                        $js_bundles[ $file_only ][ $msg_key ] = array_values( $entry->translations );
                    }
                }
            }
        }

        $mo_file  = preg_replace( '/\.po$/', '.mo', $po_file );
        $php_file = preg_replace( '/\.po$/', '.l10n.php', $po_file );

        if ( ! $mo->export_to_file( $mo_file ) ) {
            return new WP_Error( 'aura_i18n_mo_write', __( 'No se pudo escribir el archivo compilado .mo (revisa permisos de escritura).', 'aura-suite' ) );
        }

        $php_headers = [];
        foreach ( $po->headers as $hk => $hv ) {
            $php_headers[ strtolower( trim( (string) $hk ) ) ] = (string) $hv;
        }
        $php_data     = array_merge( $php_headers, [ 'messages' => $messages ] );
        $php_contents = '<?php' . PHP_EOL . 'return ' . self::short_export( $php_data ) . ';' . PHP_EOL;
        if ( file_put_contents( $php_file, $php_contents ) === false ) {
            return new WP_Error( 'aura_i18n_php_write', __( 'No se pudo escribir el archivo .l10n.php.', 'aura-suite' ) );
        }

        $json_count = 0;
        if ( $locale !== '' ) {
            foreach ( glob( $lang_dir . DIRECTORY_SEPARATOR . "aura-suite-{$locale}-*.json" ) ?: [] as $old_json ) {
                @unlink( $old_json );
            }
            foreach ( $js_bundles as $js_rel => $js_msgs ) {
                $hash      = md5( $js_rel );
                $json_file = $lang_dir . DIRECTORY_SEPARATOR . "aura-suite-{$locale}-{$hash}.json";
                $jed       = [
                    'translation-revision-date' => $po->headers['PO-Revision-Date'] ?? gmdate( 'Y-m-d H:i+0000' ),
                    'generator'                 => 'Aura i18n Compiler',
                    'source'                    => $js_rel,
                    'domain'                    => 'messages',
                    'locale_data'               => [
                        'messages' => array_merge(
                            [
                                '' => [
                                    'domain'       => 'messages',
                                    'lang'         => $locale,
                                    'plural-forms' => $po->headers['Plural-Forms'] ?? 'nplurals=2; plural=(n != 1);',
                                ],
                            ],
                            $js_msgs
                        ),
                    ],
                ];
                $encoded = wp_json_encode( $jed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                if ( $encoded !== false ) {
                    file_put_contents( $json_file, $encoded );
                    $json_count++;
                }
            }
        }

        return [
            'locale'     => $locale,
            'translated' => $translated_count,
            'total'      => count( $po->entries ),
            'json_count' => $json_count,
        ];
    }

    /**
     * AJAX: Escanea `/languages` y compila uno o todos los archivos `.po`.
     */
    public static function ajax_scan_compile(): void {
        check_ajax_referer( self::NONCE_MANAGE, 'nonce' );
        if ( ! current_user_can( 'aura_admin_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'No tienes permiso para realizar esta acción.', 'aura-suite' ) ] );
        }

        $lang_dir = defined( 'AURA_PLUGIN_DIR' ) ? AURA_PLUGIN_DIR . 'languages/' : '';
        $target   = isset( $_POST['locale'] ) ? preg_replace( '/[^A-Za-z0-9_@-]/', '', sanitize_text_field( wp_unslash( $_POST['locale'] ) ) ) : '';

        $po_files = $target !== ''
            ? [ $lang_dir . "aura-suite-{$target}.po" ]
            : ( glob( $lang_dir . 'aura-suite-*.po' ) ?: [] );

        $compiled = [];
        $errors   = [];
        foreach ( $po_files as $po_file ) {
            if ( ! file_exists( $po_file ) ) {
                continue;
            }
            $res = self::compile_po_file( $po_file );
            if ( is_wp_error( $res ) ) {
                $errors[] = basename( $po_file ) . ': ' . $res->get_error_message();
            } else {
                $compiled[] = $res;
            }
        }

        if ( ! empty( $errors ) && empty( $compiled ) ) {
            wp_send_json_error( [ 'message' => implode( ' | ', $errors ) ] );
        }

        $report = self::get_installed_packages_report();
        $msg    = empty( $compiled )
            ? __( 'Escaneo completado. No se encontraron archivos .po pendientes.', 'aura-suite' )
            : sprintf(
                /* translators: %d: number of compiled language files */
                _n( 'Compilado %d paquete de idioma correctamente (.mo, .l10n.php y JED JSON).', 'Compilados %d paquetes de idioma correctamente (.mo, .l10n.php y JED JSON).', count( $compiled ), 'aura-suite' ),
                count( $compiled )
            );

        wp_send_json_success( [
            'message'  => $msg,
            'compiled' => $compiled,
            'warnings' => $errors,
            'report'   => $report,
        ] );
    }

    /**
     * AJAX: Sube un archivo `.po` (Poedit / Loco Translate) a `/languages` y lo compila al instante.
     */
    public static function ajax_upload_po(): void {
        check_ajax_referer( self::NONCE_MANAGE, 'nonce' );
        if ( ! current_user_can( 'aura_admin_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'No tienes permiso para realizar esta acción.', 'aura-suite' ) ] );
        }

        if ( empty( $_FILES['po_file'] ) || empty( $_FILES['po_file']['tmp_name'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Debes seleccionar un archivo .po válido.', 'aura-suite' ) ] );
        }

        $orig_name = sanitize_file_name( wp_unslash( $_FILES['po_file']['name'] ?? '' ) );
        if ( strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) ) !== 'po' ) {
            wp_send_json_error( [ 'message' => __( 'Formato inválido: solo se admiten archivos .po de Gettext.', 'aura-suite' ) ] );
        }

        $locale = isset( $_POST['locale'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['locale'] ) ) ) : '';
        if ( $locale === '' && preg_match( '/(?:aura-suite-)?([a-z]{2,3}(?:_[A-Z]{2})?)\.po$/', $orig_name, $m ) ) {
            $locale = $m[1];
        }
        $locale = preg_replace( '/[^A-Za-z0-9_@-]/', '', $locale );
        if ( ! preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]{2,4})?$/', $locale ) ) {
            wp_send_json_error( [ 'message' => __( 'Especifica un código de idioma válido (ej: en_US, pt_BR, fr_FR).', 'aura-suite' ) ] );
        }

        $tmp_file = $_FILES['po_file']['tmp_name'];
        if ( ! self::load_pomo_classes() ) {
            wp_send_json_error( [ 'message' => __( 'Librerías POMO no disponibles.', 'aura-suite' ) ] );
        }

        // Validar integridad del .po antes de moverlo a /languages
        $check_po = new PO();
        if ( ! $check_po->import_from_file( $tmp_file ) || empty( $check_po->entries ) ) {
            wp_send_json_error( [ 'message' => __( 'El archivo subido no contiene entradas Gettext (.po) válidas.', 'aura-suite' ) ] );
        }

        $lang_dir = defined( 'AURA_PLUGIN_DIR' ) ? AURA_PLUGIN_DIR . 'languages/' : '';
        if ( ! is_dir( $lang_dir ) ) {
            wp_mkdir_p( $lang_dir );
        }

        $dest_po = $lang_dir . "aura-suite-{$locale}.po";
        $raw_po  = file_get_contents( $tmp_file );
        if ( $raw_po === false || file_put_contents( $dest_po, $raw_po ) === false ) {
            wp_send_json_error( [ 'message' => __( 'Error al guardar el archivo .po en la carpeta /languages.', 'aura-suite' ) ] );
        }

        $compiled = self::compile_po_file( $dest_po );
        if ( is_wp_error( $compiled ) ) {
            wp_send_json_error( [ 'message' => $compiled->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'  => sprintf(
                /* translators: 1: locale code, 2: translated strings count, 3: total strings count */
                __( 'Idioma %1$s instalado y compilado (%2$d / %3$d cadenas traducidas).', 'aura-suite' ),
                $locale,
                (int) $compiled['translated'],
                (int) $compiled['total']
            ),
            'compiled' => $compiled,
            'report'   => self::get_installed_packages_report(),
        ] );
    }

    /**
     * admin_post: Descarga `aura-suite.pot` o `aura-suite-<locale>.po` para Poedit / Loco Translate.
     */
    public static function handle_download_file(): void {
        if ( ! current_user_can( 'aura_admin_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permiso para descargar este archivo.', 'aura-suite' ) );
        }
        check_admin_referer( self::NONCE_MANAGE );

        $type     = isset( $_GET['file'] ) ? sanitize_key( wp_unslash( $_GET['file'] ) ) : 'pot';
        $locale   = isset( $_GET['locale'] ) ? preg_replace( '/[^A-Za-z0-9_@-]/', '', sanitize_text_field( wp_unslash( $_GET['locale'] ) ) ) : '';
        $lang_dir = defined( 'AURA_PLUGIN_DIR' ) ? AURA_PLUGIN_DIR . 'languages/' : '';

        if ( $type === 'po' && $locale !== '' ) {
            $filepath = $lang_dir . "aura-suite-{$locale}.po";
            $filename = "aura-suite-{$locale}.po";
        } else {
            $filepath = $lang_dir . 'aura-suite.pot';
            $filename = 'aura-suite.pot';
        }

        if ( ! file_exists( $filepath ) ) {
            wp_die( esc_html__( 'El archivo de idioma solicitado no existe.', 'aura-suite' ) );
        }

        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $filepath ) );
        readfile( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        exit;
    }

    /**
     * Renderiza la pestaña de Configuración y Gestor de Idiomas (i18n) dentro de `settings-page.php`.
     */
    public static function render_settings_panel(): void {
        $available       = self::get_available_locales();
        $global_default  = (string) get_option( self::OPTION_DEFAULT_LOCALE, '' );
        $report          = self::get_installed_packages_report();
        $manage_nonce    = wp_create_nonce( self::NONCE_MANAGE );
        $pot_dl_url      = wp_nonce_url(
            add_query_arg( [ 'action' => 'aura_i18n_download', 'file' => 'pot' ], admin_url( 'admin-post.php' ) ),
            self::NONCE_MANAGE
        );
        ?>
        <!-- ═══════════════════════════════════════════
             TAB: IDIOMAS Y TRADUCCIONES (i18n)
             ═══════════════════════════════════════════ -->
        <div class="aura-tab-panel" id="tab-i18n">

            <!-- Tarjeta 1: Idioma predeterminado de AURA -->
            <div class="aura-glass-card glass-card fade-up">
                <h2 class="aura-section-title">
                    <span class="dashicons dashicons-translation"></span>
                    <?php esc_html_e( 'Idioma de la Interfaz (AURA Business Suite)', 'aura-suite' ); ?>
                </h2>
                <p class="aura-desc" style="margin-bottom:20px;">
                    <?php esc_html_e( 'Controla el idioma global con el que se renderizan los menús, pantallas, correos y reportes de AURA. Los datos capturados por los usuarios en base de datos permanecen intactos sin importar el idioma seleccionado.', 'aura-suite' ); ?>
                </p>

                <table class="aura-form-table form-table">
                    <tr>
                        <th scope="row">
                            <label for="aura_default_locale"><?php esc_html_e( 'Idioma Predeterminado de AURA', 'aura-suite' ); ?></label>
                            <span class="field-hint"><?php esc_html_e( 'Por defecto hereda el idioma de WordPress, o puedes forzar un idioma fijo para toda la suite.', 'aura-suite' ); ?></span>
                        </th>
                        <td>
                            <select name="aura_default_locale" id="aura_default_locale" class="aura-select" style="max-width:380px;">
                                <option value="" <?php selected( $global_default, '' ); ?>>
                                    🌐 <?php esc_html_e( 'Usar idioma de WordPress (Recomendado)', 'aura-suite' ); ?>
                                </option>
                                <?php foreach ( $available as $code => $meta ) : ?>
                                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $global_default, $code ); ?>>
                                        <?php echo esc_html( ( $meta['flag'] ?? self::get_locale_flag( $code ) ) . ' ' . $meta['native'] . ' (' . $code . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: current WordPress site locale */
                                    esc_html__( 'Idioma actual de WordPress: %s. Cada usuario también puede alternar su idioma personal desde el selector 🌐 en la barra superior o en los portales frontend.', 'aura-suite' ),
                                    '<code>' . esc_html( function_exists( 'get_locale' ) ? get_locale() : 'es_ES' ) . '</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div class="aura-info-box neutral" style="margin-top:16px;">
                    <strong><?php esc_html_e( 'Orden de prioridad:', 'aura-suite' ); ?></strong>
                    <?php esc_html_e( '1) Preferencia personal del usuario (selector 🌐) → 2) Idioma forzado en esta configuración de AURA → 3) Idioma general de WordPress.', 'aura-suite' ); ?>
                </div>
            </div>

            <!-- Tarjeta 2: Escaneo, Compilación y Subida de Idiomas (/languages) -->
            <div class="aura-glass-card glass-card fade-up" style="margin-top:20px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:16px;">
                    <div>
                        <h2 class="aura-section-title" style="margin-bottom:4px;">
                            <span class="dashicons dashicons-admin-site-alt3"></span>
                            <?php esc_html_e( 'Gestor de Paquetes de Idioma (/languages)', 'aura-suite' ); ?>
                        </h2>
                        <p class="aura-desc" style="margin:0;">
                            <?php
                            printf(
                                /* translators: %d: total translatable strings in POT */
                                esc_html__( 'Compatible con Poedit y Loco Translate. Plantilla maestra actual: %d cadenas traducibles.', 'aura-suite' ),
                                (int) $report['pot_count']
                            );
                            ?>
                        </p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <?php if ( $report['pot_exists'] ) : ?>
                            <a href="<?php echo esc_url( $pot_dl_url ); ?>" class="aura-btn-secondary">
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e( 'Descargar aura-suite.pot', 'aura-suite' ); ?>
                            </a>
                        <?php endif; ?>
                        <button type="button" id="js-aura-i18n-scan-btn" class="aura-btn-primary btn-indigo">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e( 'Escanear y Recompilar Todos', 'aura-suite' ); ?>
                        </button>
                    </div>
                </div>

                <div id="js-aura-i18n-feedback" style="display:none;margin-bottom:16px;"></div>

                <div style="overflow-x:auto;">
                    <table class="wp-list-table widefat fixed striped" id="aura-i18n-packages-table" style="border-radius:10px;overflow:hidden;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Idioma', 'aura-suite' ); ?></th>
                                <th style="width:110px;"><?php esc_html_e( 'Código', 'aura-suite' ); ?></th>
                                <th style="width:200px;"><?php esc_html_e( 'Cobertura', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Archivos compilados', 'aura-suite' ); ?></th>
                                <th style="width:210px;text-align:right;"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="js-aura-i18n-packages-body">
                            <?php echo self::render_packages_rows_html( $report['packages'], $manage_nonce ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </tbody>
                    </table>
                </div>

                <!-- Subida directa de archivo .po -->
                <div style="margin-top:22px;padding:18px;border-radius:12px;border:1px dashed var(--aura-border, #cbd5e1);background:rgba(99,102,241,0.03);">
                    <h3 style="margin:0 0 8px;font-size:14.5px;display:flex;align-items:center;gap:6px;">
                        <span class="dashicons dashicons-upload"></span>
                        <?php esc_html_e( 'Instalar o Actualizar Idioma (.po)', 'aura-suite' ); ?>
                    </h3>
                    <p class="description" style="margin:0 0 14px;">
                        <?php esc_html_e( 'Sube un archivo .po traducido con Poedit o Loco Translate (por ejemplo aura-suite-pt_BR.po o aura-suite-fr_FR.po). AURA lo validará y compilará automáticamente a .mo, .l10n.php y JED .json.', 'aura-suite' ); ?>
                    </p>
                    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                        <div>
                            <label for="js-aura-i18n-upload-locale" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">
                                <?php esc_html_e( 'Código Locale', 'aura-suite' ); ?>
                            </label>
                            <input type="text" id="js-aura-i18n-upload-locale" class="aura-input" placeholder="pt_BR, fr_FR, en_US" style="width:170px;" />
                        </div>
                        <div style="flex:1;min-width:220px;">
                            <label for="js-aura-i18n-upload-file" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">
                                <?php esc_html_e( 'Archivo Gettext (.po)', 'aura-suite' ); ?>
                            </label>
                            <input type="file" id="js-aura-i18n-upload-file" accept=".po" />
                        </div>
                        <button type="button" id="js-aura-i18n-upload-btn" class="aura-btn-secondary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e( 'Subir y Compilar Idioma', 'aura-suite' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            var i18nNonce = '<?php echo esc_js( $manage_nonce ); ?>';

            function showI18nFeedback(msg, isOk) {
                var $box = $('#js-aura-i18n-feedback').show();
                $box.html(
                    '<div class="aura-info-box" style="' +
                    (isOk ? '' : 'background:rgba(239,68,68,.08);border-color:#ef4444;color:#dc2626;') +
                    'font-size:13px;">' + (isOk ? '✅ ' : '❌ ') + $('<div>').text(msg).html() + '</div>'
                );
            }

            function renderRowFromPkg(pkg) {
                var badges = [];
                if (pkg.is_native) {
                    badges.push('<span class="badge badge-emerald"><?php echo esc_js( __( 'Nativo (Base PHP/JS)', 'aura-suite' ) ); ?></span>');
                } else {
                    if (pkg.has_po) badges.push('<span class="badge badge-blue">.po</span>');
                    if (pkg.has_mo) badges.push('<span class="badge badge-emerald">.mo</span>');
                    if (pkg.has_l10n_php) badges.push('<span class="badge badge-indigo">.l10n.php</span>');
                    if (pkg.json_count > 0) badges.push('<span class="badge badge-blue">' + pkg.json_count + ' JED JSON</span>');
                    if (pkg.needs_compile) badges.push('<span class="badge badge-amber"><?php echo esc_js( __( 'Requiere compilar', 'aura-suite' ) ); ?></span>');
                }
                var dlUrl = '<?php echo esc_js( admin_url( 'admin-post.php?action=aura_i18n_download&file=po&_wpnonce=' . $manage_nonce ) ); ?>&locale=' + encodeURIComponent(pkg.code);
                var actions = pkg.is_native
                    ? '<span style="color:#64748b;font-size:12px;">—</span>'
                    : (pkg.has_po
                        ? '<button type="button" class="button button-small js-aura-i18n-compile-one" data-locale="' + $('<div>').text(pkg.code).html() + '"><?php echo esc_js( __( 'Compilar', 'aura-suite' ) ); ?></button> ' +
                          '<a class="button button-small" href="' + dlUrl + '"><?php echo esc_js( __( 'Descargar .po', 'aura-suite' ) ); ?></a>'
                        : '<span style="color:#64748b;font-size:12px;"><?php echo esc_js( __( 'Solo binario', 'aura-suite' ) ); ?></span>');

                return '<tr>' +
                    '<td><strong style="display:inline-flex;align-items:center;gap:7px;">' + (pkg.flag_svg || '') + '<span>' + $('<div>').text(pkg.native).html() + '</span></strong></td>' +
                    '<td><code>' + $('<div>').text(pkg.code).html() + '</code></td>' +
                    '<td><div style="font-size:12px;font-weight:600;">' + pkg.translated_count + ' / ' + pkg.total_count + ' (' + pkg.percent + '%)</div>' +
                    '<div style="height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:4px;"><div style="height:100%;width:' + pkg.percent + '%;background:#10b981;"></div></div></td>' +
                    '<td style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">' + badges.join(' ') + '</td>' +
                    '<td style="text-align:right;white-space:nowrap;">' + actions + '</td>' +
                    '</tr>';
            }

            function refreshPackagesTable(report) {
                if (!report || !report.packages) return;
                var html = '';
                report.packages.forEach(function (p) { html += renderRowFromPkg(p); });
                $('#js-aura-i18n-packages-body').html(html);
            }

            $('#js-aura-i18n-scan-btn').on('click', function () {
                var $btn = $(this).prop('disabled', true);
                $.post(ajaxurl, { action: 'aura_i18n_scan_compile', nonce: i18nNonce }, function (r) {
                    if (r.success) {
                        showI18nFeedback(r.data.message, true);
                        refreshPackagesTable(r.data.report);
                    } else {
                        showI18nFeedback(r.data && r.data.message ? r.data.message : 'Error', false);
                    }
                }).fail(function () {
                    showI18nFeedback('<?php echo esc_js( __( 'Error de comunicación con el servidor', 'aura-suite' ) ); ?>', false);
                }).always(function () { $btn.prop('disabled', false); });
            });

            $(document).on('click', '.js-aura-i18n-compile-one', function () {
                var $btn = $(this).prop('disabled', true);
                var loc  = $btn.data('locale');
                $.post(ajaxurl, { action: 'aura_i18n_scan_compile', nonce: i18nNonce, locale: loc }, function (r) {
                    if (r.success) {
                        showI18nFeedback(r.data.message, true);
                        refreshPackagesTable(r.data.report);
                    } else {
                        showI18nFeedback(r.data && r.data.message ? r.data.message : 'Error', false);
                    }
                }).fail(function () {
                    showI18nFeedback('<?php echo esc_js( __( 'Error de comunicación con el servidor', 'aura-suite' ) ); ?>', false);
                }).always(function () { $btn.prop('disabled', false); });
            });

            $('#js-aura-i18n-upload-file').on('change', function () {
                var file = this.files && this.files[0];
                if (!file) return;
                var m = file.name.match(/(?:aura-suite-)?([a-z]{2,3}(?:_[A-Z]{2})?)\.po$/);
                if (m && !$('#js-aura-i18n-upload-locale').val()) {
                    $('#js-aura-i18n-upload-locale').val(m[1]);
                }
            });

            $('#js-aura-i18n-upload-btn').on('click', function () {
                var fileInput = document.getElementById('js-aura-i18n-upload-file');
                var file = fileInput && fileInput.files ? fileInput.files[0] : null;
                if (!file) {
                    showI18nFeedback('<?php echo esc_js( __( 'Selecciona primero un archivo .po para subir.', 'aura-suite' ) ); ?>', false);
                    return;
                }
                var fd = new FormData();
                fd.append('action', 'aura_i18n_upload_po');
                fd.append('nonce', i18nNonce);
                fd.append('locale', $.trim($('#js-aura-i18n-upload-locale').val()));
                fd.append('po_file', file);

                var $btn = $(this).prop('disabled', true);
                $.ajax({
                    url: ajaxurl, type: 'POST', data: fd, processData: false, contentType: false,
                    success: function (r) {
                        if (r.success) {
                            showI18nFeedback(r.data.message, true);
                            refreshPackagesTable(r.data.report);
                            fileInput.value = '';
                        } else {
                            showI18nFeedback(r.data && r.data.message ? r.data.message : 'Error', false);
                        }
                    },
                    error: function () {
                        showI18nFeedback('<?php echo esc_js( __( 'Error de comunicación con el servidor', 'aura-suite' ) ); ?>', false);
                    },
                    complete: function () { $btn.prop('disabled', false); }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Genera las filas HTML de la tabla de paquetes de idioma instalados.
     */
    private static function render_packages_rows_html( array $packages, string $manage_nonce ): string {
        ob_start();
        foreach ( $packages as $pkg ) :
            $dl_url = wp_nonce_url(
                add_query_arg(
                    [ 'action' => 'aura_i18n_download', 'file' => 'po', 'locale' => $pkg['code'] ],
                    admin_url( 'admin-post.php' )
                ),
                self::NONCE_MANAGE
            );
            ?>
            <tr>
                <td><strong style="display:inline-flex;align-items:center;gap:7px;"><?php echo self::get_locale_flag_svg( $pkg['code'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $pkg['native'] ); ?></span></strong></td>
                <td><code><?php echo esc_html( $pkg['code'] ); ?></code></td>
                <td>
                    <div style="font-size:12px;font-weight:600;">
                        <?php echo esc_html( number_format_i18n( $pkg['translated_count'] ) . ' / ' . number_format_i18n( $pkg['total_count'] ) . ' (' . $pkg['percent'] . '%)' ); ?>
                    </div>
                    <div style="height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:4px;">
                        <div style="height:100%;width:<?php echo esc_attr( $pkg['percent'] ); ?>%;background:#10b981;"></div>
                    </div>
                </td>
                <td style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
                    <?php if ( ! empty( $pkg['is_native'] ) ) : ?>
                        <span class="badge badge-emerald"><?php esc_html_e( 'Nativo (Base PHP/JS)', 'aura-suite' ); ?></span>
                    <?php else : ?>
                        <?php if ( $pkg['has_po'] ) : ?><span class="badge badge-blue">.po</span><?php endif; ?>
                        <?php if ( $pkg['has_mo'] ) : ?><span class="badge badge-emerald">.mo</span><?php endif; ?>
                        <?php if ( $pkg['has_l10n_php'] ) : ?><span class="badge badge-indigo">.l10n.php</span><?php endif; ?>
                        <?php if ( $pkg['json_count'] > 0 ) : ?><span class="badge badge-blue"><?php echo esc_html( $pkg['json_count'] . ' JED JSON' ); ?></span><?php endif; ?>
                        <?php if ( $pkg['needs_compile'] ) : ?><span class="badge badge-amber"><?php esc_html_e( 'Requiere compilar', 'aura-suite' ); ?></span><?php endif; ?>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;white-space:nowrap;">
                    <?php if ( ! empty( $pkg['is_native'] ) ) : ?>
                        <span style="color:#64748b;font-size:12px;">—</span>
                    <?php elseif ( $pkg['has_po'] ) : ?>
                        <button type="button" class="button button-small js-aura-i18n-compile-one" data-locale="<?php echo esc_attr( $pkg['code'] ); ?>">
                            <?php esc_html_e( 'Compilar', 'aura-suite' ); ?>
                        </button>
                        <a class="button button-small" href="<?php echo esc_url( $dl_url ); ?>">
                            <?php esc_html_e( 'Descargar .po', 'aura-suite' ); ?>
                        </a>
                    <?php else : ?>
                        <span style="color:#64748b;font-size:12px;"><?php esc_html_e( 'Solo binario', 'aura-suite' ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        endforeach;
        return ob_get_clean();
    }
}
