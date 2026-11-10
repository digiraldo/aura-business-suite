<?php
/**
 * Motor Unificado de Divisas y Formateo Monetario Internacional
 * Estándar ISO 4217 & NIC 21 / IAS 21
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.8.5
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aura_Financial_Currency {

    /**
     * Catálogo estándar de divisas ISO 4217
     */
    public static function get_currencies(): array {
        return [
            'MXN' => ['name' => __('Peso Mexicano', 'aura-suite'), 'symbol' => '$', 'decimals' => 2, 'pos' => 'before', 'dec' => '.', 'tho' => ','],
            'USD' => ['name' => __('Dólar Estadounidense', 'aura-suite'), 'symbol' => '$', 'decimals' => 2, 'pos' => 'before', 'dec' => '.', 'tho' => ','],
            'EUR' => ['name' => __('Euro', 'aura-suite'), 'symbol' => '€', 'decimals' => 2, 'pos' => 'after', 'dec' => ',', 'tho' => '.'],
            'COP' => ['name' => __('Peso Colombiano', 'aura-suite'), 'symbol' => '$', 'decimals' => 2, 'pos' => 'before', 'dec' => ',', 'tho' => '.'],
            'CLP' => ['name' => __('Peso Chileno', 'aura-suite'), 'symbol' => '$', 'decimals' => 0, 'pos' => 'before', 'dec' => ',', 'tho' => '.'],
            'PEN' => ['name' => __('Sol Peruano', 'aura-suite'), 'symbol' => 'S/', 'decimals' => 2, 'pos' => 'before', 'dec' => '.', 'tho' => ','],
            'ARS' => ['name' => __('Peso Argentino', 'aura-suite'), 'symbol' => '$', 'decimals' => 2, 'pos' => 'before', 'dec' => ',', 'tho' => '.'],
            'BOB' => ['name' => __('Boliviano', 'aura-suite'), 'symbol' => 'Bs', 'decimals' => 2, 'pos' => 'before', 'dec' => ',', 'tho' => '.'],
            'BRL' => ['name' => __('Real Brasileño', 'aura-suite'), 'symbol' => 'R$', 'decimals' => 2, 'pos' => 'before', 'dec' => ',', 'tho' => '.'],
            'CAD' => ['name' => __('Dólar Canadiense', 'aura-suite'), 'symbol' => 'C$', 'decimals' => 2, 'pos' => 'before', 'dec' => '.', 'tho' => ','],
            'CRC' => ['name' => __('Colón Costarricense', 'aura-suite'), 'symbol' => '₡', 'decimals' => 2, 'pos' => 'before', 'dec' => ',', 'tho' => '.'],
            'DOP' => ['name' => __('Peso Dominicano', 'aura-suite'), 'symbol' => 'RD$', 'decimals' => 2, 'pos' => 'before', 'dec' => '.', 'tho' => ','],
            'GBP' => ['name' => __('Libra Esterlina', 'aura-suite'), 'symbol' => '£', 'decimals' => 2, 'pos' => 'before', 'dec' => '.', 'tho' => ','],
            'GTQ' => ['name' => __('Quetzal Guatemalteco', 'aura-suite'), 'symbol' => 'Q', 'decimals' => 2, 'pos' => 'before', 'dec' => '.', 'tho' => ','],
            'HNL' => ['name' => __('Lempira Hondureño', 'aura-suite'), 'symbol' => 'L', 'decimals' => 2, 'pos' => 'before', 'dec' => '.', 'tho' => ','],
            'JPY' => ['name' => __('Yen Japonés', 'aura-suite'), 'symbol' => '¥', 'decimals' => 0, 'pos' => 'before', 'dec' => '.', 'tho' => ','],
            'NIO' => ['name' => __('Córdoba Nicaragüense', 'aura-suite'), 'symbol' => 'C$', 'decimals' => 2, 'pos' => 'before', 'dec' => '.', 'tho' => ','],
            'PAB' => ['name' => __('Balboa Panameño', 'aura-suite'), 'symbol' => 'B/.', 'decimals' => 2, 'pos' => 'before', 'dec' => '.', 'tho' => ','],
            'PYG' => ['name' => __('Guaraní Paraguayo', 'aura-suite'), 'symbol' => '₲', 'decimals' => 0, 'pos' => 'before', 'dec' => ',', 'tho' => '.'],
            'UYU' => ['name' => __('Peso Uruguayo', 'aura-suite'), 'symbol' => '$U', 'decimals' => 2, 'pos' => 'before', 'dec' => ',', 'tho' => '.'],
            'VES' => ['name' => __('Bolívar Venezolano', 'aura-suite'), 'symbol' => 'Bs.', 'decimals' => 2, 'pos' => 'before', 'dec' => ',', 'tho' => '.'],
            'CHF' => ['name' => __('Franco Suizo', 'aura-suite'), 'symbol' => 'CHF', 'decimals' => 2, 'pos' => 'before', 'dec' => '.', 'tho' => '\''],
        ];
    }

    /**
     * Obtener moneda base configurada
     */
    public static function get_base_currency(): string {
        $currency = get_option('aura_finance_base_currency', get_option('aura_finance_currency', 'MXN'));
        return !empty($currency) ? strtoupper(trim($currency)) : 'MXN';
    }

    /**
     * Obtener símbolo de moneda
     */
    public static function get_currency_symbol(string $currency = ''): string {
        if (!empty($currency)) {
            $currencies = self::get_currencies();
            $curr = strtoupper(trim($currency));
            if (isset($currencies[$curr]['symbol'])) {
                return $currencies[$curr]['symbol'];
            }
        }
        $symbol = get_option('aura_currency_symbol', '$');
        return !empty($symbol) ? trim($symbol) : '$';
    }

    /**
     * Obtener configuración completa de moneda y formato
     */
    public static function get_config(): array {
        $base = self::get_base_currency();
        $currencies = self::get_currencies();
        $curr_meta = $currencies[$base] ?? null;

        $def_symbol   = $curr_meta ? $curr_meta['symbol'] : '$';
        $def_pos      = $curr_meta ? $curr_meta['pos'] : 'before';
        $def_dec      = $curr_meta ? $curr_meta['dec'] : '.';
        $def_tho      = $curr_meta ? $curr_meta['tho'] : ',';
        $def_decimals = $curr_meta ? $curr_meta['decimals'] : 2;

        return [
            'base_currency'      => $base,
            'symbol'             => get_option('aura_currency_symbol', $def_symbol),
            'symbol_position'    => get_option('aura_currency_symbol_position', $def_pos),
            'decimal_separator'  => get_option('aura_currency_decimal_separator', $def_dec),
            'thousand_separator' => get_option('aura_currency_thousand_separator', $def_tho),
            'decimals'           => intval(get_option('aura_currency_decimals', $def_decimals)),
        ];
    }

    /**
     * Formatear monto monetario
     *
     * @param float|int|string $amount
     * @param string|null      $currency
     * @param array            $args
     * @return string
     */
    public static function format($amount, ?string $currency = null, array $args = []): string {
        $config = self::get_config();

        $amount  = is_numeric($amount) ? (float) $amount : 0.0;
        $curr    = !empty($currency) ? strtoupper(trim($currency)) : $config['base_currency'];
        $is_base = ($curr === $config['base_currency']);

        $decimals = isset($args['decimals']) ? intval($args['decimals']) : $config['decimals'];
        $dec_sep  = $args['decimal_separator'] ?? $config['decimal_separator'];
        $tho_sep  = $args['thousand_separator'] ?? $config['thousand_separator'];
        $pos      = $args['symbol_position'] ?? $config['symbol_position'];
        
        $symbol   = $is_base ? $config['symbol'] : self::get_currency_symbol($curr);

        $formatted_number = number_format($amount, $decimals, $dec_sep, $tho_sep);

        if ($pos === 'after') {
            $formatted = $formatted_number . ' ' . $symbol;
        } else {
            $formatted = $symbol . $formatted_number;
        }

        // Si es divisa extranjera y no se indica explícitamente lo contrario, agregar el código ISO
        $show_code = $args['show_code'] ?? (!$is_base);
        if ($show_code && !empty($curr)) {
            $formatted .= ' ' . $curr;
        }

        return $formatted;
    }
}

/**
 * Helper global para formatear dinero
 */
if (!function_exists('aura_format_money')) {
    function aura_format_money($amount, ?string $currency = null, array $args = []): string {
        return Aura_Financial_Currency::format($amount, $currency, $args);
    }
}

/**
 * Helper global para obtener la moneda base
 */
if (!function_exists('aura_get_base_currency')) {
    function aura_get_base_currency(): string {
        return Aura_Financial_Currency::get_base_currency();
    }
}

/**
 * Helper global para obtener el símbolo de la moneda
 */
if (!function_exists('aura_get_currency_symbol')) {
    function aura_get_currency_symbol(string $currency = ''): string {
        return Aura_Financial_Currency::get_currency_symbol($currency);
    }
}

