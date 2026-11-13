<?php
/**
 * Template: Editor de Plantillas (Fabric.js Canvas Builder)
 *
 * Esta página ocupa toda la pantalla. Los assets de Fabric.js se cargan
 * condicionalmente por class-certificates-admin.php cuando action=edit.
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$template_id = absint( $_GET['id'] ?? 0 );
$is_new      = $template_id === 0 || ( $_GET['id'] ?? '' ) === 'new';

$template_data = null;
if ( ! $is_new && $template_id > 0 ) {
    global $wpdb;
    $table         = $wpdb->prefix . 'aura_certificate_templates';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $template_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $template_id ) );
}

$page_title = $is_new
    ? __( 'Nueva Plantilla', 'aura-suite' )
    : sprintf( __( 'Editar: %s', 'aura-suite' ), $template_data->name ?? '' );
?>
<div class="wrap aura-cert-builder-wrap" style="margin:0;padding:0;max-width:100%;">

    <!-- Barra superior del editor -->
    <div class="aura-cert-builder-topbar">
        <div class="aura-cert-builder-topbar-left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-templates' ) ); ?>"
               class="aura-cert-builder-back" title="<?php esc_attr_e( 'Volver al listado', 'aura-suite' ); ?>">
                ← <?php esc_html_e( 'Plantillas', 'aura-suite' ); ?>
            </a>
            <span class="aura-cert-builder-title" id="aura-cb-topbar-title"><?php echo esc_html( $page_title ); ?></span>
            <span id="aura-cb-save-status" class="aura-cb-save-status" style="display:none;"></span>
        </div>

        <div class="aura-cert-builder-topbar-center">
            <button type="button" id="aura-cb-undo" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Deshacer (Ctrl+Z)', 'aura-suite' ); ?>">↩</button>
            <button type="button" id="aura-cb-redo" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Rehacer (Ctrl+Y)', 'aura-suite' ); ?>">↪</button>
            <span class="aura-cb-divider-v"></span>
            <button type="button" id="aura-cb-zoom-out"   class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Alejar', 'aura-suite' ); ?>">－</button>
            <span id="aura-cb-zoom-label" style="min-width:44px;text-align:center;font-size:.8rem;">100%</span>
            <button type="button" id="aura-cb-zoom-in"    class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Acercar', 'aura-suite' ); ?>">＋</button>
            <button type="button" id="aura-cb-zoom-reset" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Ajustar a pantalla', 'aura-suite' ); ?>">⊡</button>
            <span class="aura-cb-divider-v"></span>
            <button type="button" id="aura-cb-toggle-guides" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Mostrar/ocultar guías de margen y centro', 'aura-suite' ); ?>">📏 <?php esc_html_e( 'Guías', 'aura-suite' ); ?></button>
            <button type="button" id="aura-cb-toggle-snap" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Ajuste a cuadrícula (10px)', 'aura-suite' ); ?>">⊞ <?php esc_html_e( 'Snap', 'aura-suite' ); ?></button>
        </div>

        <div class="aura-cert-builder-topbar-right">
            <button type="button" id="aura-cb-preview-sample" class="button" title="<?php esc_attr_e( 'Previsualizar reemplazando variables con datos de ejemplo', 'aura-suite' ); ?>">
                ✨ <?php esc_html_e( 'Preview Ejemplo', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-cb-preview" class="button">
                👁 <?php esc_html_e( 'Vista Previa', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-cb-save" class="button button-primary">
                💾 <?php esc_html_e( 'Guardar', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-cb-save-exit" class="button">
                <?php esc_html_e( 'Guardar y Salir', 'aura-suite' ); ?>
            </button>
        </div>
    </div>

    <div class="aura-cert-builder-layout">

        <!-- Panel de herramientas (izquierda) -->
        <div class="aura-cert-builder-tools">
            <h3><?php esc_html_e( 'Elementos', 'aura-suite' ); ?></h3>
            <div class="aura-cb-elem-grid">
                <button type="button" class="aura-cb-elem-btn" data-type="textbox">
                    <span>T</span><?php esc_html_e( 'Texto', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="heading">
                    <span>H</span><?php esc_html_e( 'Título', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="image">
                    <span>🖼</span><?php esc_html_e( 'Imagen', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="logo">
                    <span>🏛</span><?php esc_html_e( 'Logo Org.', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="rect">
                    <span>▭</span><?php esc_html_e( 'Rectángulo', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="frame">
                    <span>▣</span><?php esc_html_e( 'Marco', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="circle">
                    <span>⬤</span><?php esc_html_e( 'Círculo', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="triangle">
                    <span>▲</span><?php esc_html_e( 'Triángulo', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="star">
                    <span>★</span><?php esc_html_e( 'Estrella', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="badge">
                    <span>🏷</span><?php esc_html_e( 'Insignia', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="line">
                    <span>—</span><?php esc_html_e( 'Línea', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="divider">
                    <span>❖</span><?php esc_html_e( 'Separador', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="qr">
                    <span>🔲</span><?php esc_html_e( 'Código QR', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="signature-block">
                    <span>✍</span><?php esc_html_e( 'Bloque Firma', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-elem-btn" data-type="seal">
                    <span>💮</span><?php esc_html_e( 'Sello Oficial', 'aura-suite' ); ?>
                </button>
            </div>

            <h3 style="margin-top:16px;"><?php esc_html_e( 'Variables Dinámicas', 'aura-suite' ); ?></h3>
            <input type="search" id="aura-cb-var-search" class="aura-input" style="width:100%;margin-bottom:8px;font-size:12px;"
                   placeholder="<?php esc_attr_e( 'Filtrar variables…', 'aura-suite' ); ?>">
            <div id="aura-cb-vars-list" class="aura-cb-vars-list">
                <!-- Poblado por JS desde auraCertBuilder.dynamicVars -->
            </div>

            <h3 style="margin-top:16px;"><?php esc_html_e( 'Diseños Base', 'aura-suite' ); ?></h3>
            <div id="aura-cb-prebuilt-list" class="aura-cb-prebuilt-list">
                <!-- Poblado por JS desde auraCertBuilder.prebuiltDesigns -->
            </div>
        </div>

        <!-- Canvas (centro) -->
        <div class="aura-cert-builder-canvas-wrap">
            <div id="aura-cb-stage" class="aura-cb-stage">
                <canvas id="aura-cert-canvas"></canvas>
                <div id="aura-cb-guides-overlay" class="aura-cb-guides-overlay" style="display:none;"></div>
            </div>
        </div>

        <!-- Panel de propiedades (derecha) -->
        <div class="aura-cert-builder-props">
            <!-- Propiedades del documento -->
            <div id="aura-cb-doc-props" class="aura-cb-props-section">
                <h3><?php esc_html_e( 'Documento', 'aura-suite' ); ?></h3>
                <label><?php esc_html_e( 'Nombre de la plantilla', 'aura-suite' ); ?>
                    <input type="text" id="aura-cb-tmpl-name" class="aura-input" style="width:100%;"
                           value="<?php echo $template_data ? esc_attr( $template_data->name ) : ''; ?>"
                           placeholder="<?php esc_attr_e( 'Ej: Diploma Graduación 2026', 'aura-suite' ); ?>">
                </label>
                <label style="margin-top:8px;display:block;"><?php esc_html_e( 'Descripción', 'aura-suite' ); ?>
                    <textarea id="aura-cb-tmpl-desc" class="aura-input" style="width:100%;" rows="2"
                              placeholder="<?php esc_attr_e( 'Descripción opcional', 'aura-suite' ); ?>"><?php echo $template_data ? esc_textarea( $template_data->description ) : ''; ?></textarea>
                </label>
                <label style="margin-top:8px;display:block;"><?php esc_html_e( 'Tamaño y Orientación', 'aura-suite' ); ?>
                    <select id="aura-cb-orientation" class="aura-input" style="width:100%;">
                        <optgroup label="A4 (297 × 210 mm)">
                            <option value="landscape"         <?php selected( $template_data->orientation ?? 'landscape', 'landscape' ); ?>><?php esc_html_e( 'A4 Horizontal', 'aura-suite' ); ?></option>
                            <option value="portrait"          <?php selected( $template_data->orientation ?? '', 'portrait' ); ?>><?php esc_html_e( 'A4 Vertical', 'aura-suite' ); ?></option>
                        </optgroup>
                        <optgroup label="Carta / Letter (279 × 216 mm)">
                            <option value="letter_landscape"  <?php selected( $template_data->orientation ?? '', 'letter_landscape' ); ?>><?php esc_html_e( 'Carta Horizontal', 'aura-suite' ); ?></option>
                            <option value="letter_portrait"   <?php selected( $template_data->orientation ?? '', 'letter_portrait' ); ?>><?php esc_html_e( 'Carta Vertical', 'aura-suite' ); ?></option>
                        </optgroup>
                    </select>
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px;">
                    <label style="font-size:11px;"><?php esc_html_e( 'Ancho (mm)', 'aura-suite' ); ?>
                        <input type="number" id="aura-cb-width-mm" class="aura-input" style="width:100%;"
                               value="<?php echo esc_attr( $template_data->width_mm ?? 297 ); ?>" readonly>
                    </label>
                    <label style="font-size:11px;"><?php esc_html_e( 'Alto (mm)', 'aura-suite' ); ?>
                        <input type="number" id="aura-cb-height-mm" class="aura-input" style="width:100%;"
                               value="<?php echo esc_attr( $template_data->height_mm ?? 210 ); ?>" readonly>
                    </label>
                </div>
                <div style="margin-top:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:6px;margin:0;">
                        <span><?php esc_html_e( 'Fondo:', 'aura-suite' ); ?></span>
                        <input type="color" id="aura-cb-bg-color" value="#ffffff" style="width:40px;height:30px;padding:2px;">
                    </label>
                    <button type="button" id="aura-cb-bg-image-btn" class="button button-small">
                        🖼 <?php esc_html_e( 'Imagen', 'aura-suite' ); ?>
                    </button>
                    <button type="button" id="aura-cb-bg-image-remove" class="button button-small" style="display:none;color:#dc2626;" title="<?php esc_attr_e( 'Quitar imagen de fondo', 'aura-suite' ); ?>">
                        ✕
                    </button>
                </div>
            </div>

            <!-- Propiedades del objeto seleccionado -->
            <div id="aura-cb-obj-props" class="aura-cb-props-section" style="display:none;">
                <h3><?php esc_html_e( 'Elemento Seleccionado', 'aura-suite' ); ?></h3>

                <!-- Alineación rápida en el lienzo -->
                <div class="aura-cb-subblock">
                    <span class="aura-cb-sublabel"><?php esc_html_e( 'Alinear en el lienzo', 'aura-suite' ); ?></span>
                    <div class="aura-cb-align-bar">
                        <button type="button" class="button button-small aura-cb-align-btn" data-align="left" title="<?php esc_attr_e( 'Alinear izquierda', 'aura-suite' ); ?>">⇤</button>
                        <button type="button" class="button button-small aura-cb-align-btn" data-align="center-h" title="<?php esc_attr_e( 'Centrar horizontal', 'aura-suite' ); ?>">↔</button>
                        <button type="button" class="button button-small aura-cb-align-btn" data-align="right" title="<?php esc_attr_e( 'Alinear derecha', 'aura-suite' ); ?>">⇥</button>
                        <button type="button" class="button button-small aura-cb-align-btn" data-align="top" title="<?php esc_attr_e( 'Alinear arriba', 'aura-suite' ); ?>">⤒</button>
                        <button type="button" class="button button-small aura-cb-align-btn" data-align="center-v" title="<?php esc_attr_e( 'Centrar vertical', 'aura-suite' ); ?>">↕</button>
                        <button type="button" class="button button-small aura-cb-align-btn" data-align="bottom" title="<?php esc_attr_e( 'Alinear abajo', 'aura-suite' ); ?>">⤓</button>
                    </div>
                </div>

                <!-- Propiedades de Texto -->
                <div id="aura-cb-text-props" style="display:none;">
                    <label><?php esc_html_e( 'Contenido', 'aura-suite' ); ?>
                        <textarea id="aura-cb-text-content" class="aura-input" style="width:100%;" rows="3"></textarea>
                    </label>
                    <select id="aura-cb-insert-var-inline" class="aura-input" style="width:100%;margin-top:4px;font-size:12px;">
                        <option value=""><?php esc_html_e( '+ Insertar variable en este texto…', 'aura-suite' ); ?></option>
                    </select>

                    <label style="margin-top:8px;display:block;"><?php esc_html_e( 'Fuente', 'aura-suite' ); ?>
                        <select id="aura-cb-obj-font-family" class="aura-input" style="width:100%;"></select>
                    </label>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px;margin-top:6px;">
                        <label style="font-size:11px;"><?php esc_html_e( 'Tamaño', 'aura-suite' ); ?>
                            <input type="number" id="aura-cb-obj-font-size" class="aura-input" style="width:100%;" min="6" max="300" value="24">
                        </label>
                        <label style="font-size:11px;"><?php esc_html_e( 'Interlineado', 'aura-suite' ); ?>
                            <input type="number" id="aura-cb-obj-line-height" class="aura-input" style="width:100%;" min="0.5" max="3" step="0.05" value="1.16">
                        </label>
                        <label style="font-size:11px;"><?php esc_html_e( 'Espaciado', 'aura-suite' ); ?>
                            <input type="number" id="aura-cb-obj-char-spacing" class="aura-input" style="width:100%;" min="-100" max="1000" step="10" value="0">
                        </label>
                    </div>

                    <div style="margin-top:8px;display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
                        <button type="button" id="aura-cb-obj-bold" class="button aura-cb-toggle-btn" title="<?php esc_attr_e( 'Negrita', 'aura-suite' ); ?>"><strong>B</strong></button>
                        <button type="button" id="aura-cb-obj-italic" class="button aura-cb-toggle-btn" title="<?php esc_attr_e( 'Cursiva', 'aura-suite' ); ?>"><em>I</em></button>
                        <button type="button" id="aura-cb-obj-underline" class="button aura-cb-toggle-btn" title="<?php esc_attr_e( 'Subrayado', 'aura-suite' ); ?>"><u>U</u></button>
                        <button type="button" id="aura-cb-obj-linethrough" class="button aura-cb-toggle-btn" title="<?php esc_attr_e( 'Tachado', 'aura-suite' ); ?>"><s>S</s></button>
                        <button type="button" id="aura-cb-obj-uppercase" class="button button-small" title="<?php esc_attr_e( 'Alternar MAYÚSCULAS', 'aura-suite' ); ?>">AA</button>
                        <input type="color" id="aura-cb-obj-font-color" value="#000000" title="<?php esc_attr_e( 'Color del texto', 'aura-suite' ); ?>" style="width:32px;height:30px;padding:1px;">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr auto;gap:6px;align-items:center;margin-top:6px;">
                        <label style="font-size:11px;display:flex;align-items:center;gap:6px;">
                            <span><?php esc_html_e( 'Fondo texto:', 'aura-suite' ); ?></span>
                            <input type="color" id="aura-cb-obj-text-bg" value="#ffffff" style="width:28px;height:26px;padding:1px;">
                        </label>
                        <button type="button" id="aura-cb-obj-text-bg-clear" class="button button-small">
                            <?php esc_html_e( 'Sin fondo', 'aura-suite' ); ?>
                        </button>
                    </div>

                    <label style="margin-top:6px;display:block;"><?php esc_html_e( 'Alineación', 'aura-suite' ); ?>
                        <select id="aura-cb-obj-text-align" class="aura-input" style="width:100%;">
                            <option value="left"><?php esc_html_e( 'Izquierda', 'aura-suite' ); ?></option>
                            <option value="center"><?php esc_html_e( 'Centrado', 'aura-suite' ); ?></option>
                            <option value="right"><?php esc_html_e( 'Derecha', 'aura-suite' ); ?></option>
                            <option value="justify"><?php esc_html_e( 'Justificado', 'aura-suite' ); ?></option>
                        </select>
                    </label>
                </div>

                <!-- Propiedades de Forma y Trazo -->
                <div id="aura-cb-shape-props" class="aura-cb-subblock" style="display:none;">
                    <span class="aura-cb-sublabel"><?php esc_html_e( 'Relleno y Trazo', 'aura-suite' ); ?></span>
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                        <label style="display:flex;align-items:center;gap:4px;font-size:11px;margin:0;">
                            <span><?php esc_html_e( 'Relleno:', 'aura-suite' ); ?></span>
                            <input type="color" id="aura-cb-obj-fill" value="#8b5cf6" style="width:32px;height:28px;padding:1px;">
                        </label>
                        <button type="button" id="aura-cb-obj-fill-transparent" class="button button-small">
                            <?php esc_html_e( 'Transparente', 'aura-suite' ); ?>
                        </button>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;align-items:end;">
                        <label style="font-size:11px;"><?php esc_html_e( 'Color borde', 'aura-suite' ); ?>
                            <div style="display:flex;gap:4px;align-items:center;margin-top:2px;">
                                <input type="color" id="aura-cb-obj-stroke" value="#000000" style="width:32px;height:28px;padding:1px;">
                                <button type="button" id="aura-cb-obj-stroke-none" class="button button-small" title="<?php esc_attr_e( 'Sin borde', 'aura-suite' ); ?>">∅</button>
                            </div>
                        </label>
                        <label style="font-size:11px;"><?php esc_html_e( 'Grosor (px)', 'aura-suite' ); ?>
                            <input type="number" id="aura-cb-obj-stroke-width" class="aura-input" style="width:100%;" min="0" max="40" step="0.5" value="0">
                        </label>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px;">
                        <label style="font-size:11px;"><?php esc_html_e( 'Estilo línea', 'aura-suite' ); ?>
                            <select id="aura-cb-obj-stroke-dash" class="aura-input" style="width:100%;">
                                <option value="solid"><?php esc_html_e( 'Sólida', 'aura-suite' ); ?></option>
                                <option value="dashed"><?php esc_html_e( 'Guiones', 'aura-suite' ); ?></option>
                                <option value="dotted"><?php esc_html_e( 'Punteada', 'aura-suite' ); ?></option>
                            </select>
                        </label>
                        <label id="aura-cb-obj-rx-wrap" style="font-size:11px;"><?php esc_html_e( 'Esquinas (rx)', 'aura-suite' ); ?>
                            <input type="number" id="aura-cb-obj-rx" class="aura-input" style="width:100%;" min="0" max="150" value="0">
                        </label>
                    </div>
                </div>

                <!-- Posición / tamaño / opacidad / sombra / orden -->
                <div id="aura-cb-pos-props" class="aura-cb-subblock">
                    <span class="aura-cb-sublabel"><?php esc_html_e( 'Posición y Apariencia', 'aura-suite' ); ?></span>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;">
                        <label style="font-size:11px;"><?php esc_html_e( 'X', 'aura-suite' ); ?><input type="number" id="aura-cb-obj-x" class="aura-input" style="width:100%;"></label>
                        <label style="font-size:11px;"><?php esc_html_e( 'Y', 'aura-suite' ); ?><input type="number" id="aura-cb-obj-y" class="aura-input" style="width:100%;"></label>
                        <label style="font-size:11px;"><?php esc_html_e( 'Ancho', 'aura-suite' ); ?><input type="number" id="aura-cb-obj-w" class="aura-input" style="width:100%;"></label>
                        <label style="font-size:11px;"><?php esc_html_e( 'Alto', 'aura-suite' ); ?><input type="number" id="aura-cb-obj-h" class="aura-input" style="width:100%;"></label>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px;align-items:center;">
                        <label style="font-size:11px;"><?php esc_html_e( 'Rotación (°)', 'aura-suite' ); ?>
                            <input type="number" id="aura-cb-obj-angle" class="aura-input" style="width:100%;" min="-360" max="360" value="0">
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:11px;margin-top:14px;">
                            <input type="checkbox" id="aura-cb-obj-lock">
                            <span>🔒 <?php esc_html_e( 'Bloquear', 'aura-suite' ); ?></span>
                        </label>
                    </div>

                    <label style="margin-top:6px;display:block;font-size:11px;"><?php esc_html_e( 'Opacidad', 'aura-suite' ); ?>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <input type="range" id="aura-cb-obj-opacity" min="0" max="100" step="5" value="100" style="flex:1;">
                            <span id="aura-cb-obj-opacity-label" style="font-size:.75rem;color:var(--cert-gray-500);min-width:32px;">100%</span>
                        </div>
                    </label>

                    <div style="margin-top:6px;display:flex;align-items:center;gap:8px;">
                        <label style="display:flex;align-items:center;gap:6px;font-size:11px;margin:0;">
                            <input type="checkbox" id="aura-cb-obj-shadow">
                            <span><?php esc_html_e( 'Sombra', 'aura-suite' ); ?></span>
                        </label>
                        <input type="color" id="aura-cb-obj-shadow-color" value="#0f172a" style="width:28px;height:24px;padding:1px;">
                    </div>

                    <div style="margin-top:8px;display:flex;gap:4px;flex-wrap:wrap;">
                        <button type="button" id="aura-cb-bring-front"    class="button button-small" title="<?php esc_attr_e( 'Traer al frente', 'aura-suite' ); ?>">⇈</button>
                        <button type="button" id="aura-cb-bring-forward"  class="button button-small" title="<?php esc_attr_e( 'Subir capa', 'aura-suite' ); ?>">↑</button>
                        <button type="button" id="aura-cb-send-backward"  class="button button-small" title="<?php esc_attr_e( 'Bajar capa', 'aura-suite' ); ?>">↓</button>
                        <button type="button" id="aura-cb-send-back"      class="button button-small" title="<?php esc_attr_e( 'Enviar al fondo', 'aura-suite' ); ?>">⇊</button>
                        <button type="button" id="aura-cb-clone-obj"      class="button button-small" title="<?php esc_attr_e( 'Duplicar (Ctrl+D)', 'aura-suite' ); ?>">⎘ <?php esc_html_e( 'Clonar', 'aura-suite' ); ?></button>
                        <button type="button" id="aura-cb-delete-obj"     class="button button-small" style="color:#dc2626;" title="<?php esc_attr_e( 'Eliminar (Supr)', 'aura-suite' ); ?>">🗑</button>
                    </div>
                </div>
            </div>

            <!-- Capas -->
            <div class="aura-cb-props-section" style="margin-top:12px;">
                <h3><?php esc_html_e( 'Capas del Diseño', 'aura-suite' ); ?></h3>
                <div id="aura-cb-layers-list" class="aura-cb-layers-list"></div>
            </div>

            <!-- Firmantes disponibles -->
            <div class="aura-cb-props-section" style="margin-top:12px;">
                <h3><?php esc_html_e( 'Firmantes Activos', 'aura-suite' ); ?></h3>
                <div id="aura-cb-signers-list" class="aura-cb-signers">
                    <!-- Poblado por JS desde auraCertBuilder.signers -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden inputs para el save -->
<input type="hidden" id="aura-cb-template-id"
       value="<?php echo esc_attr( $is_new ? '0' : $template_id ); ?>">
<input type="hidden" id="aura-cb-templates-list-url"
       value="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-templates' ) ); ?>">
