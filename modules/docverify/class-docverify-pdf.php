<?php
/**
 * Motor PDF y QR del Módulo de Validación Documental
 *
 * Responsabilidades:
 *  - Validar cabeceras reales de archivos PDF (%PDF-) y calcular SHA-256
 *  - Generar códigos QR locales (PNG + SVG) con \chillerlan\QRCode\QRCode
 *  - Estampar sello de verificación o Hoja Anexa Oficial ES/EN vía FPDI + mPDF
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_DocVerify_PDF {

    /**
     * Valida que un archivo sea un PDF auténtico y dentro del límite configurado.
     *
     * @param string $filepath
     * @return true|WP_Error
     */
    public static function validate_pdf_file( string $filepath ) {
        if ( ! file_exists( $filepath ) || ! is_file( $filepath ) ) {
            return new WP_Error( 'pdf_missing', __( 'No se pudo encontrar el archivo PDF subido.', 'aura-suite' ) );
        }

        $max_mb   = max( 2, (int) Aura_DocVerify_Settings::get( 'max_upload_mb', 15 ) );
        $max_bytes = $max_mb * 1024 * 1024;
        $size     = (int) filesize( $filepath );

        if ( $size <= 0 ) {
            return new WP_Error( 'pdf_empty', __( 'El archivo PDF está vacío.', 'aura-suite' ) );
        }

        if ( $size > $max_bytes ) {
            return new WP_Error(
                'pdf_too_large',
                sprintf(
                    /* translators: %d = límite MB */
                    __( 'El PDF supera el tamaño máximo permitido (%d MB).', 'aura-suite' ),
                    $max_mb
                )
            );
        }

        $handle = fopen( $filepath, 'rb' );
        if ( ! $handle ) {
            return new WP_Error( 'pdf_unreadable', __( 'Error al inspeccionar el archivo PDF.', 'aura-suite' ) );
        }
        $header = fread( $handle, 1024 );
        fclose( $handle );

        if ( ! is_string( $header ) || strpos( $header, '%PDF-' ) === false ) {
            return new WP_Error( 'pdf_invalid_magic', __( 'El archivo no tiene una firma binaria PDF válida (%PDF-).', 'aura-suite' ) );
        }

        return true;
    }

    /**
     * Calcula el hash criptográfico SHA-256 de un archivo local.
     */
    public static function compute_sha256( string $filepath ): string {
        if ( ! file_exists( $filepath ) || ! is_file( $filepath ) ) {
            return '';
        }
        $hash = @hash_file( 'sha256', $filepath );
        return is_string( $hash ) ? strtolower( $hash ) : '';
    }

    /**
     * Genera los archivos PNG y SVG del código QR para la URL de verificación.
     *
     * @param string $folio
     * @param string $verify_url
     * @return array{png_path:string, svg_path:string}
     */
    public static function generate_qr_files( string $folio, string $verify_url ): array {
        $result = [
            'png_path' => '',
            'svg_path' => '',
        ];

        $base   = Aura_DocVerify_Setup::ensure_protected_storage_dir();
        $qr_dir = $base . '/qr';
        $safe   = sanitize_file_name( $folio );
        $scale  = max( 4, min( 10, (int) Aura_DocVerify_Settings::get( 'qr_scale', 6 ) ) );

        $png_path = $qr_dir . '/qr-' . $safe . '.png';
        $svg_path = $qr_dir . '/qr-' . $safe . '.svg';
        $ecc_opt  = strtoupper( (string) Aura_DocVerify_Settings::get( 'qr_ecc', 'M' ) );
        $ecc      = ( $ecc_opt === 'H' ) ? Aura_DocVerify_QRCode::ECC_H : Aura_DocVerify_QRCode::ECC_M;

        // Motor nativo PHP ISO/IEC 18004 (cero dependencias externas)
        if ( class_exists( 'Aura_DocVerify_QRCode' ) ) {
            try {
                if ( Aura_DocVerify_QRCode::to_png_file( $verify_url, $png_path, $ecc, $scale, 2 ) ) {
                    $result['png_path'] = $png_path;
                }
            } catch ( \Throwable $e ) {
                error_log( 'Aura DocVerify QR PNG error: ' . $e->getMessage() );
            }

            try {
                $svg = Aura_DocVerify_QRCode::to_svg( $verify_url, $ecc, $scale, 2 );
                if ( $svg !== '' && @file_put_contents( $svg_path, $svg ) !== false && file_exists( $svg_path ) ) {
                    $result['svg_path'] = $svg_path;
                }
            } catch ( \Throwable $e ) {
                error_log( 'Aura DocVerify QR SVG error: ' . $e->getMessage() );
            }

            if ( $result['png_path'] !== '' || $result['svg_path'] !== '' ) {
                return $result;
            }
        }

        // Fallback opcional si existiera chillerlan/php-qrcode
        if ( class_exists( '\chillerlan\QRCode\QRCode' ) ) {
            try {
                $opts_png              = new \chillerlan\QRCode\QROptions();
                $opts_png->outputType  = \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG;
                $opts_png->eccLevel    = ( $ecc_opt === 'H' ) ? \chillerlan\QRCode\QRCode::ECC_H : \chillerlan\QRCode\QRCode::ECC_M;
                $opts_png->imageBase64 = false;
                $opts_png->scale       = $scale;
                ( new \chillerlan\QRCode\QRCode( $opts_png ) )->render( $verify_url, $png_path );
                if ( file_exists( $png_path ) ) {
                    $result['png_path'] = $png_path;
                }
            } catch ( \Throwable $e ) {}
        }

        return $result;
    }

    /**
     * Genera un PDF sellado o con Hoja Anexa Oficial a partir del PDF firmado original.
     *
     * @param string $source_pdf Ruta del PDF firmado original.
     * @param string $dest_pdf   Ruta destino donde escribir el PDF resultante.
     * @param array  $doc_data   Metadatos del documento registrado.
     * @param string $mode       'annex_page' | 'overlay_corner'
     * @param string $position   'bottom_right' | 'bottom_left' | 'bottom_bar' (para overlay_corner)
     * @return true|WP_Error
     */
    public static function create_stamped_pdf(
        string $source_pdf,
        string $dest_pdf,
        array $doc_data,
        string $mode = 'annex_page',
        string $position = 'bottom_right'
    ) {
        if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
            return new WP_Error( 'mpdf_missing', __( 'La librería mPDF no está disponible para sellar el PDF.', 'aura-suite' ) );
        }

        if ( ! file_exists( $source_pdf ) ) {
            return new WP_Error( 'source_missing', __( 'No se encontró el PDF base para sellar.', 'aura-suite' ) );
        }

        $base    = Aura_DocVerify_Setup::ensure_protected_storage_dir();
        $tmp_dir = wp_normalize_path( $base . '/tmp' );
        wp_mkdir_p( $tmp_dir . '/mpdf' );

        try {
            $mpdf_config = [
                'default_font'     => 'dejavusans',
                'margin_left'      => 0,
                'margin_right'     => 0,
                'margin_top'       => 0,
                'margin_bottom'    => 0,
                'margin_header'    => 0,
                'margin_footer'    => 0,
                'autoScriptToLang' => true,
                'autoLangToFont'   => true,
            ];
            if ( is_writable( $tmp_dir . '/mpdf' ) ) {
                $mpdf_config['tempDir'] = $tmp_dir;
            }
            $mpdf = new \Mpdf\Mpdf( $mpdf_config );

            $mpdf->SetTitle( (string) ( $doc_data['folio'] ?? '' ) . ' - ' . (string) ( $doc_data['title_subject'] ?? '' ) );
            $mpdf->SetAuthor( (string) Aura_DocVerify_Settings::get( 'org_name', get_bloginfo( 'name' ) ) );

            $page_count = $mpdf->setSourceFile( $source_pdf );
            if ( $page_count < 1 ) {
                return new WP_Error( 'pdf_no_pages', __( 'El PDF no contiene páginas importables.', 'aura-suite' ) );
            }

            $qr_path = (string) ( $doc_data['qr_png_path'] ?? '' );
            if ( $qr_path === '' || ! file_exists( $qr_path ) ) {
                $svg_fallback = (string) ( $doc_data['qr_svg_path'] ?? '' );
                if ( $svg_fallback !== '' && file_exists( $svg_fallback ) ) {
                    $qr_path = $svg_fallback;
                }
            }

            for ( $page_no = 1; $page_no <= $page_count; $page_no++ ) {
                $tpl_id = $mpdf->importPage( $page_no );
                $size   = $mpdf->getTemplateSize( $tpl_id );
                $w      = (float) ( $size['width'] ?? 215.9 );
                $h      = (float) ( $size['height'] ?? 279.4 );
                $orient = ( $w > $h ) ? 'L' : 'P';

                $mpdf->AddPageByArray( [
                    'orientation' => $orient,
                    'sheet-size'  => [ $w, $h ],
                    'margin-left'   => 0,
                    'margin-right'  => 0,
                    'margin-top'    => 0,
                    'margin-bottom' => 0,
                ] );

                $mpdf->useTemplate( $tpl_id, 0, 0, $w, $h );

                // Modo estampado en la última página del documento original
                if ( $mode === 'overlay_corner' && $page_no === $page_count ) {
                    self::render_overlay_stamp( $mpdf, $w, $h, $doc_data, $qr_path, $position );
                }
            }

            // Modo Hoja Anexa Oficial bilingüe ES/EN al final del PDF
            if ( $mode === 'annex_page' ) {
                self::render_annex_page( $mpdf, $doc_data, $qr_path );
            }

            wp_mkdir_p( dirname( $dest_pdf ) );
            $mpdf->Output( $dest_pdf, \Mpdf\Output\Destination::FILE );

            if ( ! file_exists( $dest_pdf ) ) {
                return new WP_Error( 'pdf_write_failed', __( 'No se pudo generar el archivo PDF sellado.', 'aura-suite' ) );
            }

            return true;
        } catch ( \Throwable $e ) {
            error_log( 'Aura DocVerify FPDI/mPDF error: ' . $e->getMessage() );
            return new WP_Error(
                'pdf_stamp_error',
                sprintf(
                    /* translators: %s = mensaje técnico */
                    __( 'No fue posible estampar este PDF automáticamente (%s). Puede usar el modo "Solo QR" e insertar el código QR manualmente antes de exportarlo.', 'aura-suite' ),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Dibuja el bloque de sello QR compacto sobre la última página del PDF.
     */
    private static function render_overlay_stamp(
        \Mpdf\Mpdf $mpdf,
        float $page_w,
        float $page_h,
        array $doc_data,
        string $qr_path,
        string $position
    ): void {
        $folio   = (string) ( $doc_data['folio'] ?? '' );
        $code    = (string) ( $doc_data['verification_code'] ?? '' );
        $sha     = (string) ( $doc_data['sha256_original'] ?? '' );
        $url     = (string) ( $doc_data['verify_url'] ?? '' );
        $org     = (string) Aura_DocVerify_Settings::get( 'org_name', get_bloginfo( 'name' ) );
        $sha_cut = $sha ? strtoupper( substr( $sha, 0, 16 ) ) . '…' : '';

        $qr_html = ( $qr_path && file_exists( $qr_path ) )
            ? '<img src="' . esc_attr( $qr_path ) . '" style="width:19mm;height:19mm;display:block;" />'
            : '';

        if ( $position === 'bottom_bar' ) {
            $box_w = max( 120.0, $page_w - 20.0 );
            $box_h = 22.0;
            $x     = 10.0;
            $y     = max( 8.0, $page_h - $box_h - 6.0 );
        } elseif ( $position === 'bottom_left' ) {
            $box_w = 88.0;
            $box_h = 23.0;
            $x     = 10.0;
            $y     = max( 8.0, $page_h - $box_h - 8.0 );
        } else {
            // bottom_right
            $box_w = 88.0;
            $box_h = 23.0;
            $x     = max( 10.0, $page_w - $box_w - 10.0 );
            $y     = max( 8.0, $page_h - $box_h - 8.0 );
        }

        $html = '
        <div style="border:0.35mm solid #0284c7;background:#ffffff;border-radius:2mm;padding:1.8mm 2.2mm;font-family:dejavusans,sans-serif;">
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="width:20mm;vertical-align:middle;padding-right:2mm;">' . $qr_html . '</td>
                    <td style="vertical-align:middle;font-size:6.8pt;line-height:1.32;color:#0f172a;">
                        <div style="font-weight:bold;color:#0369a1;font-size:7.2pt;">' . esc_html( $org ) . '</div>
                        <div><strong>Folio:</strong> ' . esc_html( $folio ) . ' &nbsp;|&nbsp; <strong>Code:</strong> ' . esc_html( $code ) . '</div>
                        ' . ( $sha_cut ? '<div style="color:#475569;font-size:6.1pt;"><strong>SHA-256:</strong> ' . esc_html( $sha_cut ) . '</div>' : '' ) . '
                        <div style="color:#0284c7;font-size:5.9pt;">Verify / Verificar: ' . esc_html( preg_replace( '#^https?://#', '', $url ) ) . '</div>
                    </td>
                </tr>
            </table>
        </div>';

        $mpdf->WriteFixedPosHTML( $html, $x, $y, $box_w, $box_h );
    }

    /**
     * Añade una Hoja Anexa Oficial bilingüe ES/EN preservando intacto el documento original.
     */
    private static function render_annex_page( \Mpdf\Mpdf $mpdf, array $doc_data, string $qr_path ): void {
        $mpdf->AddPageByArray( [
            'orientation'   => 'P',
            'sheet-size'    => 'LETTER',
            'margin-left'   => 16,
            'margin-right'  => 16,
            'margin-top'    => 14,
            'margin-bottom' => 14,
        ] );

        $settings      = Aura_DocVerify_Settings::get_all();
        $org_name      = (string) $settings['org_name'];
        $org_tax_id    = (string) ( $settings['org_tax_id'] ?? '' );
        $org_address   = (string) ( $settings['org_address'] ?? '' );
        $org_country   = (string) ( $settings['org_country'] ?? '' );
        $consular_mail = (string) ( $settings['consular_email'] ?? '' );
        $consular_tel  = (string) ( $settings['consular_phone'] ?? '' );
        $org_logo      = self::resolve_local_logo_path( (string) ( $settings['org_logo_url'] ?? '' ) );

        $type_labels   = Aura_DocVerify_Settings::get_document_type_labels( (string) ( $doc_data['doc_type'] ?? 'other' ) );
        $folio         = (string) ( $doc_data['folio'] ?? '' );
        $code          = (string) ( $doc_data['verification_code'] ?? '' );
        $verify_url    = (string) ( $doc_data['verify_url'] ?? '' );
        $sha256        = strtoupper( (string) ( $doc_data['sha256_original'] ?? '' ) );

        $logo_html = $org_logo
            ? '<img src="' . esc_attr( $org_logo ) . '" style="max-height:18mm;max-width:45mm;display:block;" />'
            : '';

        $qr_html = ( $qr_path && file_exists( $qr_path ) )
            ? '<img src="' . esc_attr( $qr_path ) . '" style="width:36mm;height:36mm;display:block;margin:0 auto;" />'
            : '';

        $rows = [
            [ 'Folio Institucional / Document Folio', $folio ],
            [ 'Código de Verificación / Verification Code', $code ],
            [ 'Tipo de Documento / Document Type', $type_labels['es'] . ' / ' . $type_labels['en'] ],
            [ 'Asunto / Subject', (string) ( $doc_data['title_subject'] ?? '' ) ],
            [ 'Titular o Invitado / Holder or Invitee', (string) ( $doc_data['holder_name'] ?? '—' ) ],
        ];

        if ( ! empty( $doc_data['holder_doc_masked'] ) ) {
            $rows[] = [ 'Documento / ID / Passport (Enmascarado / Masked)', (string) $doc_data['holder_doc_masked'] ];
        }
        if ( ! empty( $doc_data['recipient_entity'] ) ) {
            $dest = (string) $doc_data['recipient_entity'];
            if ( ! empty( $doc_data['recipient_country'] ) ) {
                $dest .= ' (' . $doc_data['recipient_country'] . ')';
            }
            $rows[] = [ 'Entidad Destinataria / Recipient Entity', $dest ];
        }
        if ( ! empty( $doc_data['purpose'] ) ) {
            $rows[] = [ 'Motivo / Purpose', (string) $doc_data['purpose'] ];
        }
        if ( ! empty( $doc_data['travel_or_event_start'] ) || ! empty( $doc_data['travel_or_event_end'] ) ) {
            $period = trim( (string) ( $doc_data['travel_or_event_start'] ?? '' ) . ' → ' . (string) ( $doc_data['travel_or_event_end'] ?? '' ), ' →' );
            $rows[] = [ 'Período / Event or Stay Period', $period ];
        }
        if ( ! empty( $doc_data['stay_address'] ) ) {
            $rows[] = [ 'Lugar de Estadía / Accommodation & Host Address', (string) $doc_data['stay_address'] ];
        }
        if ( ! empty( $doc_data['signer_name'] ) ) {
            $signer = (string) $doc_data['signer_name'];
            if ( ! empty( $doc_data['signer_title'] ) ) {
                $signer .= ' — ' . $doc_data['signer_title'];
            }
            $rows[] = [ 'Firmante Autorizado / Authorized Signatory', $signer ];
        }
        $rows[] = [ 'Fecha de Emisión / Issue Date', (string) ( $doc_data['issued_at'] ?? '' ) ];
        $rows[] = [ 'Vigencia hasta / Valid Until', ! empty( $doc_data['expires_at'] ) ? (string) $doc_data['expires_at'] : 'Sin vencimiento / No expiration' ];

        $tbody = '';
        foreach ( $rows as $idx => $r ) {
            $bg     = ( $idx % 2 === 0 ) ? '#f8fafc' : '#ffffff';
            $tbody .= '<tr style="background:' . $bg . ';">'
                . '<td style="padding:2.6mm 3mm;border-bottom:0.2mm solid #e2e8f0;font-weight:bold;color:#334155;width:44%;font-size:8.5pt;">' . esc_html( $r[0] ) . '</td>'
                . '<td style="padding:2.6mm 3mm;border-bottom:0.2mm solid #e2e8f0;color:#0f172a;font-size:8.8pt;">' . esc_html( $r[1] ) . '</td>'
                . '</tr>';
        }

        $org_meta_parts = array_filter( [
            $org_tax_id ? 'ID / NIT: ' . $org_tax_id : '',
            $org_address,
            $org_country,
            $consular_tel ? 'Tel: ' . $consular_tel : '',
            $consular_mail ? 'Email: ' . $consular_mail : '',
        ] );

        $html = '
        <div style="font-family:dejavusans,sans-serif;color:#0f172a;">
            <table style="width:100%;border-bottom:0.7mm solid #0284c7;padding-bottom:4mm;margin-bottom:5mm;">
                <tr>
                    <td style="width:26%;vertical-align:middle;">' . $logo_html . '</td>
                    <td style="vertical-align:middle;text-align:right;">
                        <div style="font-size:13pt;font-weight:bold;color:#0369a1;">' . esc_html( $org_name ) . '</div>
                        <div style="font-size:8pt;color:#475569;margin-top:1mm;">' . esc_html( implode( ' · ', $org_meta_parts ) ) . '</div>
                    </td>
                </tr>
            </table>

            <div style="background:#eff6ff;border-left:1.2mm solid #0284c7;padding:3.5mm 4mm;margin-bottom:5mm;">
                <div style="font-size:11.5pt;font-weight:bold;color:#0f172a;">HOJA ANEXA DE AUTENTICIDAD DOCUMENTAL</div>
                <div style="font-size:9.5pt;color:#0369a1;font-weight:bold;margin-top:0.5mm;">OFFICIAL DOCUMENT VERIFICATION &amp; INTEGRITY ANNEX</div>
                <div style="font-size:8pt;color:#475569;margin-top:1.5mm;">
                    Este anexo vincula criptográficamente el documento adjunto con el registro institucional oficial. Escanee el código QR o ingrese el folio y código de verificación en la web oficial para validar vigencia, integridad SHA-256 y posible revocación.<br>
                    <em>This annex cryptographically binds the attached document to our official institutional registry. Scan the QR code or enter the folio and verification code online to verify current validity, SHA-256 integrity, and status.</em>
                </div>
            </div>

            <table style="width:100%;border-collapse:collapse;border:0.25mm solid #cbd5e1;margin-bottom:5mm;">
                ' . $tbody . '
            </table>

            <table style="width:100%;border:0.35mm solid #0284c7;background:#f8fafc;border-collapse:collapse;">
                <tr>
                    <td style="width:42mm;padding:4mm;text-align:center;border-right:0.25mm solid #cbd5e1;background:#ffffff;">
                        ' . $qr_html . '
                        <div style="font-size:7pt;color:#475569;margin-top:1.5mm;">Scan to Verify / Escanear para Verificar</div>
                    </td>
                    <td style="padding:4mm;vertical-align:top;font-size:8.2pt;line-height:1.45;">
                        <div style="font-weight:bold;color:#0369a1;margin-bottom:1.5mm;">URL Oficial de Verificación / Official Verification URL</div>
                        <div style="word-break:break-all;background:#ffffff;border:0.2mm solid #cbd5e1;padding:2mm;font-size:7.6pt;color:#0f172a;margin-bottom:3mm;">' . esc_html( $verify_url ) . '</div>
                        <div style="font-weight:bold;color:#0369a1;margin-bottom:1mm;">Huella Criptográfica Original / Original PDF SHA-256 Digest</div>
                        <div style="word-break:break-all;font-family:dejavusansmono,monospace;font-size:7.2pt;background:#ffffff;border:0.2mm solid #cbd5e1;padding:2mm;color:#0f172a;">' . esc_html( $sha256 ) . '</div>
                    </td>
                </tr>
            </table>
        </div>';

        $mpdf->WriteHTML( $html );
    }

    /**
     * Convierte la URL del logo institucional a ruta física en disco si pertenece al sitio actual.
     */
    private static function resolve_local_logo_path( string $url ): string {
        if ( $url === '' ) {
            return '';
        }
        $upload = wp_upload_dir();
        if ( ! empty( $upload['baseurl'] ) && strpos( $url, $upload['baseurl'] ) === 0 ) {
            $rel  = substr( $url, strlen( $upload['baseurl'] ) );
            $path = wp_normalize_path( $upload['basedir'] . $rel );
            if ( file_exists( $path ) ) {
                return $path;
            }
        }
        if ( defined( 'AURA_PLUGIN_URL' ) && defined( 'AURA_PLUGIN_DIR' ) && strpos( $url, AURA_PLUGIN_URL ) === 0 ) {
            $rel  = substr( $url, strlen( AURA_PLUGIN_URL ) );
            $path = wp_normalize_path( AURA_PLUGIN_DIR . $rel );
            if ( file_exists( $path ) ) {
                return $path;
            }
        }
        return '';
    }
}
