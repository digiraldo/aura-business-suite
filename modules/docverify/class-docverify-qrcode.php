<?php
/**
 * Generador QR ISO/IEC 18004 en PHP puro (Byte Mode, Versiones 1–12, ECC M y H)
 *
 * Permite generar códigos QR PNG (vía GD) y SVG vectorial localmente sin
 * dependencias externas ni APIs de terceros.
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_DocVerify_QRCode {

    /** Nivel de corrección: Medium (15%) = format bits 0 */
    const ECC_M = 0;
    /** Nivel de corrección: High (30%) = format bits 2 */
    const ECC_H = 2;

    /**
     * Tabla ISO/IEC 18004 [ecCodewordsPerBlock, numBlocks] para versiones 1..12.
     */
    private const BLOCK_SPEC = [
        self::ECC_M => [
            1  => [ 10, 1 ],
            2  => [ 16, 1 ],
            3  => [ 26, 1 ],
            4  => [ 18, 2 ],
            5  => [ 24, 2 ],
            6  => [ 16, 4 ],
            7  => [ 18, 4 ],
            8  => [ 22, 4 ],
            9  => [ 22, 5 ],
            10 => [ 26, 5 ],
            11 => [ 30, 5 ],
            12 => [ 22, 8 ],
        ],
        self::ECC_H => [
            1  => [ 17, 1 ],
            2  => [ 28, 1 ],
            3  => [ 22, 2 ],
            4  => [ 16, 4 ],
            5  => [ 22, 4 ],
            6  => [ 28, 4 ],
            7  => [ 26, 5 ],
            8  => [ 26, 6 ],
            9  => [ 24, 8 ],
            10 => [ 28, 8 ],
            11 => [ 24, 11 ],
            12 => [ 28, 11 ],
        ],
    ];

    /**
     * Genera matriz booleana del código QR para un texto UTF-8 en Byte Mode.
     *
     * @param string $text Texto/URL a codificar.
     * @param int    $ecc  self::ECC_H o self::ECC_M.
     * @return array{size:int, modules:array<int,array<int,bool>>, version:int}
     */
    public static function encode( string $text, int $ecc = self::ECC_H ): array {
        $data = array_values( unpack( 'C*', $text ) ?: [] );
        $len  = count( $data );

        $chosen_version = 0;
        $chosen_ecc     = $ecc;

        // Buscar versión mínima (1..12) que soporte el payload en el ECC pedido, o degradar a ECC_M si es muy largo
        foreach ( [ $ecc, self::ECC_M ] as $try_ecc ) {
            if ( ! isset( self::BLOCK_SPEC[ $try_ecc ] ) ) {
                continue;
            }
            for ( $ver = 1; $ver <= 12; $ver++ ) {
                $total_cw  = self::get_num_raw_data_modules( $ver ) >> 3;
                [ $ec_per_b, $blocks ] = self::BLOCK_SPEC[ $try_ecc ][ $ver ];
                $data_cw   = $total_cw - ( $ec_per_b * $blocks );
                $hdr_bits  = 4 + ( $ver >= 10 ? 16 : 8 );
                $need_bits = $hdr_bits + ( $len * 8 );
                if ( $need_bits <= $data_cw * 8 ) {
                    $chosen_version = $ver;
                    $chosen_ecc     = $try_ecc;
                    break 2;
                }
            }
        }

        if ( $chosen_version === 0 ) {
            throw new \RuntimeException( 'QR payload exceeds supported version 12 capacity.' );
        }

        $ver      = $chosen_version;
        $total_cw = self::get_num_raw_data_modules( $ver ) >> 3;
        [ $ec_per_b, $blocks ] = self::BLOCK_SPEC[ $chosen_ecc ][ $ver ];
        $data_cw  = $total_cw - ( $ec_per_b * $blocks );

        // 1. Construir flujo de bits (Byte mode 0100 + count + bytes + terminator + pad)
        $bits = [];
        self::append_bits( $bits, 0b0100, 4 );
        self::append_bits( $bits, $len, $ver >= 10 ? 16 : 8 );
        foreach ( $data as $byte ) {
            self::append_bits( $bits, $byte, 8 );
        }
        $cap_bits = $data_cw * 8;
        self::append_bits( $bits, 0, min( 4, $cap_bits - count( $bits ) ) );
        while ( count( $bits ) % 8 !== 0 ) {
            $bits[] = 0;
        }
        $pad_bytes = [ 0xEC, 0x11 ];
        $pad_idx   = 0;
        while ( count( $bits ) < $cap_bits ) {
            self::append_bits( $bits, $pad_bytes[ $pad_idx % 2 ], 8 );
            $pad_idx++;
        }

        $data_bytes = [];
        for ( $i = 0; $i < $data_cw; $i++ ) {
            $val = 0;
            for ( $b = 0; $b < 8; $b++ ) {
                $val = ( $val << 1 ) | $bits[ $i * 8 + $b ];
            }
            $data_bytes[] = $val;
        }

        // 2. Dividir en bloques y calcular ECC Reed-Solomon
        $codewords = self::add_ecc_and_interleave( $data_bytes, $ver, $chosen_ecc );

        // 3. Dibujar patrones de función y colocar datos + mejor máscara
        $size       = $ver * 4 + 17;
        $modules    = array_fill( 0, $size, array_fill( 0, $size, false ) );
        $is_func    = array_fill( 0, $size, array_fill( 0, $size, false ) );

        self::draw_function_patterns( $ver, $size, $modules, $is_func );
        self::draw_codewords( $codewords, $size, $modules, $is_func );

        $best_mask    = 0;
        $min_penalty  = PHP_INT_MAX;
        for ( $m = 0; $m < 8; $m++ ) {
            self::apply_mask( $m, $size, $modules, $is_func );
            self::draw_format_bits( $chosen_ecc, $m, $size, $modules );
            $penalty = self::get_penalty_score( $size, $modules );
            if ( $penalty < $min_penalty ) {
                $min_penalty = $penalty;
                $best_mask   = $m;
            }
            self::apply_mask( $m, $size, $modules, $is_func );
        }

        self::apply_mask( $best_mask, $size, $modules, $is_func );
        self::draw_format_bits( $chosen_ecc, $best_mask, $size, $modules );

        return [
            'size'    => $size,
            'modules' => $modules,
            'version' => $ver,
        ];
    }

    /**
     * Renderiza el QR como SVG vectorial limpio.
     */
    public static function to_svg( string $text, int $ecc = self::ECC_H, int $scale = 6, int $border = 2 ): string {
        $qr   = self::encode( $text, $ecc );
        $size = $qr['size'];
        $dim  = ( $size + $border * 2 ) * $scale;

        $paths = [];
        for ( $y = 0; $y < $size; $y++ ) {
            for ( $x = 0; $x < $size; $x++ ) {
                if ( $qr['modules'][ $y ][ $x ] ) {
                    $px      = ( $x + $border ) * $scale;
                    $py      = ( $y + $border ) * $scale;
                    $paths[] = "M{$px},{$py}h{$scale}v{$scale}h-{$scale}z";
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" width="' . $dim . '" height="' . $dim . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>'
            . '<path d="' . implode( '', $paths ) . '" fill="#0f172a"/>'
            . '</svg>';
    }

    /**
     * Guarda el QR en formato PNG usando extensión GD.
     */
    public static function to_png_file( string $text, string $filepath, int $ecc = self::ECC_H, int $scale = 6, int $border = 2 ): bool {
        if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagepng' ) ) {
            return false;
        }

        $qr   = self::encode( $text, $ecc );
        $size = $qr['size'];
        $dim  = ( $size + $border * 2 ) * $scale;

        $img = imagecreatetruecolor( $dim, $dim );
        if ( ! $img ) {
            return false;
        }

        $white = imagecolorallocate( $img, 255, 255, 255 );
        $dark  = imagecolorallocate( $img, 15, 23, 42 );
        imagefill( $img, 0, 0, $white );

        for ( $y = 0; $y < $size; $y++ ) {
            for ( $x = 0; $x < $size; $x++ ) {
                if ( $qr['modules'][ $y ][ $x ] ) {
                    $x0 = ( $x + $border ) * $scale;
                    $y0 = ( $y + $border ) * $scale;
                    imagefilledrectangle( $img, $x0, $y0, $x0 + $scale - 1, $y0 + $scale - 1, $dark );
                }
            }
        }

        $ok = @imagepng( $img, $filepath );
        imagedestroy( $img );
        return (bool) $ok && file_exists( $filepath );
    }

    // ─────────────────────────────────────────────────────────────
    // IMPLEMENTACIÓN INTERNA ISO/IEC 18004
    // ─────────────────────────────────────────────────────────────

    private static function append_bits( array &$bits, int $val, int $len ): void {
        for ( $i = $len - 1; $i >= 0; $i-- ) {
            $bits[] = ( $val >> $i ) & 1;
        }
    }

    private static function get_num_raw_data_modules( int $ver ): int {
        $result = ( 16 * $ver + 128 ) * $ver + 64;
        if ( $ver >= 2 ) {
            $num_align = intdiv( $ver, 7 ) + 2;
            $result   -= ( 25 * $num_align - 10 ) * $num_align - 55;
            if ( $ver >= 7 ) {
                $result -= 36;
            }
        }
        return $result;
    }

    private static function add_ecc_and_interleave( array $data, int $ver, int $ecc ): array {
        $total_cw              = self::get_num_raw_data_modules( $ver ) >> 3;
        [ $block_ec, $blocks ] = self::BLOCK_SPEC[ $ecc ][ $ver ];
        $num_short             = $blocks - ( $total_cw % $blocks );
        $short_len             = intdiv( $total_cw, $blocks );
        $rs_div                = self::reed_solomon_divisor( $block_ec );

        $all_blocks = [];
        $k          = 0;
        for ( $i = 0; $i < $blocks; $i++ ) {
            $dat_len   = $short_len - $block_ec + ( $i < $num_short ? 0 : 1 );
            $dat_slice = array_slice( $data, $k, $dat_len );
            $k        += $dat_len;
            $ecc_slice = self::reed_solomon_remainder( $dat_slice, $rs_div );
            if ( $i < $num_short ) {
                $dat_slice[] = 0;
            }
            $all_blocks[] = array_merge( $dat_slice, $ecc_slice );
        }

        $result = [];
        $cols   = count( $all_blocks[0] );
        for ( $c = 0; $c < $cols; $c++ ) {
            for ( $b = 0; $b < $blocks; $b++ ) {
                if ( $c !== $short_len - $block_ec || $b >= $num_short ) {
                    $result[] = $all_blocks[ $b ][ $c ];
                }
            }
        }
        return $result;
    }

    private static function reed_solomon_divisor( int $degree ): array {
        $res                = array_fill( 0, $degree, 0 );
        $res[ $degree - 1 ] = 1;
        $root               = 1;
        for ( $i = 0; $i < $degree; $i++ ) {
            for ( $j = 0; $j < $degree; $j++ ) {
                $res[ $j ] = self::gf_mul( $res[ $j ], $root );
                if ( $j + 1 < $degree ) {
                    $res[ $j ] ^= $res[ $j + 1 ];
                }
            }
            $root = self::gf_mul( $root, 0x02 );
        }
        return $res;
    }

    private static function reed_solomon_remainder( array $data, array $divisor ): array {
        $deg = count( $divisor );
        $rem = array_fill( 0, $deg, 0 );
        foreach ( $data as $byte ) {
            $factor = $byte ^ array_shift( $rem );
            $rem[]  = 0;
            for ( $i = 0; $i < $deg; $i++ ) {
                $rem[ $i ] ^= self::gf_mul( $divisor[ $i ], $factor );
            }
        }
        return $rem;
    }

    private static function gf_mul( int $x, int $y ): int {
        $z = 0;
        for ( $i = 7; $i >= 0; $i-- ) {
            $z = ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D );
            $z ^= ( ( $y >> $i ) & 1 ) * $x;
        }
        return $z;
    }

    private static function set_func_mod( int $x, int $y, bool $dark, int $size, array &$modules, array &$is_func ): void {
        if ( $x >= 0 && $x < $size && $y >= 0 && $y < $size ) {
            $modules[ $y ][ $x ] = $dark;
            $is_func[ $y ][ $x ] = true;
        }
    }

    private static function draw_finder_pattern( int $cx, int $cy, int $size, array &$modules, array &$is_func ): void {
        for ( $dy = -4; $dy <= 4; $dy++ ) {
            for ( $dx = -4; $dx <= 4; $dx++ ) {
                $dist = max( abs( $dx ), abs( $dy ) );
                self::set_func_mod( $cx + $dx, $cy + $dy, ( $dist !== 2 && $dist !== 4 ), $size, $modules, $is_func );
            }
        }
    }

    private static function draw_alignment_pattern( int $cx, int $cy, int $size, array &$modules, array &$is_func ): void {
        for ( $dy = -2; $dy <= 2; $dy++ ) {
            for ( $dx = -2; $dx <= 2; $dx++ ) {
                self::set_func_mod( $cx + $dx, $cy + $dy, max( abs( $dx ), abs( $dy ) ) !== 1, $size, $modules, $is_func );
            }
        }
    }

    private static function get_alignment_positions( int $ver, int $size ): array {
        if ( $ver === 1 ) {
            return [];
        }
        $num   = intdiv( $ver, 7 ) + 2;
        $step  = ( $ver === 32 ) ? 26 : (int) ceil( ( $ver * 4 + 4 ) / ( $num * 2 - 2 ) ) * 2;
        $pos   = [ 6 ];
        $other = [];
        for ( $i = 0, $cur = $size - 7; $i < $num - 1; $i++, $cur -= $step ) {
            $other[] = $cur;
        }
        return array_merge( $pos, array_reverse( $other ) );
    }

    private static function draw_function_patterns( int $ver, int $size, array &$modules, array &$is_func ): void {
        // Timing patterns
        for ( $i = 0; $i < $size; $i++ ) {
            self::set_func_mod( 6, $i, $i % 2 === 0, $size, $modules, $is_func );
            self::set_func_mod( $i, 6, $i % 2 === 0, $size, $modules, $is_func );
        }

        // 3 Finder patterns
        self::draw_finder_pattern( 3, 3, $size, $modules, $is_func );
        self::draw_finder_pattern( $size - 4, 3, $size, $modules, $is_func );
        self::draw_finder_pattern( 3, $size - 4, $size, $modules, $is_func );

        // Alignment patterns
        $align_pos = self::get_alignment_positions( $ver, $size );
        $n_align   = count( $align_pos );
        for ( $i = 0; $i < $n_align; $i++ ) {
            for ( $j = 0; $j < $n_align; $j++ ) {
                if ( ! ( ( $i === 0 && $j === 0 ) || ( $i === 0 && $j === $n_align - 1 ) || ( $i === $n_align - 1 && $j === 0 ) ) ) {
                    self::draw_alignment_pattern( $align_pos[ $i ], $align_pos[ $j ], $size, $modules, $is_func );
                }
            }
        }

        // Reserve format bits (draw dummy first) + dark module
        self::draw_format_bits( self::ECC_M, 0, $size, $modules, $is_func );

        // Version bits si >= 7
        if ( $ver >= 7 ) {
            $rem = $ver;
            for ( $i = 0; $i < 12; $i++ ) {
                $rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
            }
            $bits = ( $ver << 12 ) | $rem;
            for ( $i = 0; $i < 18; $i++ ) {
                $dark = ( ( $bits >> $i ) & 1 ) !== 0;
                $a    = $size - 11 + ( $i % 3 );
                $b    = intdiv( $i, 3 );
                self::set_func_mod( $a, $b, $dark, $size, $modules, $is_func );
                self::set_func_mod( $b, $a, $dark, $size, $modules, $is_func );
            }
        }
    }

    private static function draw_format_bits( int $ecc, int $mask, int $size, array &$modules, ?array &$is_func = null ): void {
        $data = ( $ecc << 3 ) | $mask;
        $rem  = $data;
        for ( $i = 0; $i < 10; $i++ ) {
            $rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
        }
        $bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;

        $put = static function ( int $x, int $y, bool $dark ) use ( &$modules, &$is_func ): void {
            $modules[ $y ][ $x ] = $dark;
            if ( $is_func !== null ) {
                $is_func[ $y ][ $x ] = true;
            }
        };

        for ( $i = 0; $i <= 5; $i++ ) {
            $put( 8, $i, ( ( $bits >> $i ) & 1 ) !== 0 );
        }
        $put( 8, 7, ( ( $bits >> 6 ) & 1 ) !== 0 );
        $put( 8, 8, ( ( $bits >> 7 ) & 1 ) !== 0 );
        $put( 7, 8, ( ( $bits >> 8 ) & 1 ) !== 0 );
        for ( $i = 9; $i < 15; $i++ ) {
            $put( 14 - $i, 8, ( ( $bits >> $i ) & 1 ) !== 0 );
        }

        for ( $i = 0; $i < 8; $i++ ) {
            $put( $size - 1 - $i, 8, ( ( $bits >> $i ) & 1 ) !== 0 );
        }
        for ( $i = 8; $i < 15; $i++ ) {
            $put( 8, $size - 15 + $i, ( ( $bits >> $i ) & 1 ) !== 0 );
        }
        $put( 8, $size - 8, true ); // Dark module
    }

    private static function draw_codewords( array $codewords, int $size, array &$modules, array $is_func ): void {
        $i         = 0;
        $total_bit = count( $codewords ) * 8;
        for ( $right = $size - 1; $right >= 1; $right -= 2 ) {
            if ( $right === 6 ) {
                $right = 5;
            }
            for ( $vert = 0; $vert < $size; $vert++ ) {
                for ( $j = 0; $j < 2; $j++ ) {
                    $x      = $right - $j;
                    $upward = ( ( $right + 1 ) & 2 ) === 0;
                    $y      = $upward ? ( $size - 1 - $vert ) : $vert;
                    if ( ! $is_func[ $y ][ $x ] && $i < $total_bit ) {
                        $modules[ $y ][ $x ] = ( ( $codewords[ $i >> 3 ] >> ( 7 - ( $i & 7 ) ) ) & 1 ) !== 0;
                        $i++;
                    }
                }
            }
        }
    }

    private static function apply_mask( int $mask, int $size, array &$modules, array $is_func ): void {
        for ( $y = 0; $y < $size; $y++ ) {
            for ( $x = 0; $x < $size; $x++ ) {
                if ( $is_func[ $y ][ $x ] ) {
                    continue;
                }
                switch ( $mask ) {
                    case 0: $inv = ( ( $x + $y ) % 2 ) === 0; break;
                    case 1: $inv = ( $y % 2 ) === 0; break;
                    case 2: $inv = ( $x % 3 ) === 0; break;
                    case 3: $inv = ( ( $x + $y ) % 3 ) === 0; break;
                    case 4: $inv = ( ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2 ) === 0; break;
                    case 5: $inv = ( ( $x * $y ) % 2 + ( $x * $y ) % 3 ) === 0; break;
                    case 6: $inv = ( ( ( $x * $y ) % 2 + ( $x * $y ) % 3 ) % 2 ) === 0; break;
                    default: $inv = ( ( ( $x + $y ) % 2 + ( $x * $y ) % 3 ) % 2 ) === 0; break;
                }
                if ( $inv ) {
                    $modules[ $y ][ $x ] = ! $modules[ $y ][ $x ];
                }
            }
        }
    }

    private static function get_penalty_score( int $size, array $modules ): int {
        $result = 0;

        // N1: filas y columnas de color igual >= 5
        for ( $y = 0; $y < $size; $y++ ) {
            $run_col = false;
            $run_len = 0;
            for ( $x = 0; $x < $size; $x++ ) {
                if ( $modules[ $y ][ $x ] === $run_col ) {
                    $run_len++;
                    if ( $run_len === 5 ) {
                        $result += 3;
                    } elseif ( $run_len > 5 ) {
                        $result++;
                    }
                } else {
                    $run_col = $modules[ $y ][ $x ];
                    $run_len = 1;
                }
            }
        }
        for ( $x = 0; $x < $size; $x++ ) {
            $run_col = false;
            $run_len = 0;
            for ( $y = 0; $y < $size; $y++ ) {
                if ( $modules[ $y ][ $x ] === $run_col ) {
                    $run_len++;
                    if ( $run_len === 5 ) {
                        $result += 3;
                    } elseif ( $run_len > 5 ) {
                        $result++;
                    }
                } else {
                    $run_col = $modules[ $y ][ $x ];
                    $run_len = 1;
                }
            }
        }

        // N2: bloques 2x2
        for ( $y = 0; $y < $size - 1; $y++ ) {
            for ( $x = 0; $x < $size - 1; $x++ ) {
                $c = $modules[ $y ][ $x ];
                if ( $c === $modules[ $y ][ $x + 1 ] && $c === $modules[ $y + 1 ][ $x ] && $c === $modules[ $y + 1 ][ $x + 1 ] ) {
                    $result += 3;
                }
            }
        }

        // N4: proporción módulos oscuros
        $dark = 0;
        foreach ( $modules as $row ) {
            foreach ( $row as $cell ) {
                if ( $cell ) {
                    $dark++;
                }
            }
        }
        $total = $size * $size;
        $k     = (int) ceil( abs( $dark * 20 - $total * 10 ) / $total ) - 1;
        $result += max( 0, $k ) * 10;

        return $result;
    }
}
