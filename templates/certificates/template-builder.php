<?php
/**
 * Template: Editor de Plantillas (Fabric.js Studio Fullscreen Builder)
 *
 * Ocupa el 100% de la ventana ocultando la barra/menú lateral de WordPress
 * para aprovechar el máximo espacio visual. Los assets de Fabric.js se cargan
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
<script>
document.documentElement.classList.add('aura-cb-fullscreen-mode');
</script>
<div class="wrap aura-cert-builder-wrap">

    <!-- Barra superior del editor Studio Pro -->
    <div class="aura-cert-builder-topbar">
        <div class="aura-cert-builder-topbar-left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-templates' ) ); ?>"
               class="aura-cert-builder-back" title="<?php esc_attr_e( 'Volver al listado de plantillas', 'aura-suite' ); ?>">
                ← <?php esc_html_e( 'Plantillas', 'aura-suite' ); ?>
            </a>
            <button type="button" id="aura-cb-toggle-left" class="aura-cb-tool-btn is-active" title="<?php esc_attr_e( 'Mostrar / ocultar panel izquierdo', 'aura-suite' ); ?>">◧</button>
            <div class="aura-cb-title-group">
                <span class="aura-cert-builder-title" id="aura-cb-topbar-title"><?php echo esc_html( $page_title ); ?></span>
                <span id="aura-cb-save-status" class="aura-cb-save-status" style="display:none;"></span>
            </div>
        </div>

        <div class="aura-cert-builder-topbar-center">
            <button type="button" id="aura-cb-undo" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Deshacer (Ctrl+Z)', 'aura-suite' ); ?>">↩</button>
            <button type="button" id="aura-cb-redo" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Rehacer (Ctrl+Y)', 'aura-suite' ); ?>">↪</button>
            <span class="aura-cb-divider-v"></span>
            <button type="button" id="aura-cb-zoom-out"   class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Alejar (-)', 'aura-suite' ); ?>">－</button>
            <select id="aura-cb-zoom-select" class="aura-cb-zoom-select" title="<?php esc_attr_e( 'Nivel de zoom', 'aura-suite' ); ?>">
                <option value="fit"><?php esc_html_e( 'Ajustar', 'aura-suite' ); ?></option>
                <option value="0.5">50%</option>
                <option value="0.75">75%</option>
                <option value="1">100%</option>
                <option value="1.25">125%</option>
                <option value="1.5">150%</option>
                <option value="2">200%</option>
            </select>
            <span id="aura-cb-zoom-label" class="aura-cb-zoom-pill">100%</span>
            <button type="button" id="aura-cb-zoom-in"    class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Acercar (+)', 'aura-suite' ); ?>">＋</button>
            <button type="button" id="aura-cb-zoom-reset" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Ajustar a pantalla', 'aura-suite' ); ?>">⊡</button>
            <span class="aura-cb-divider-v"></span>
            <button type="button" id="aura-cb-toggle-guides" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Mostrar/ocultar guías de margen y centro', 'aura-suite' ); ?>">📏 <span class="aura-cb-hide-sm"><?php esc_html_e( 'Guías', 'aura-suite' ); ?></span></button>
            <button type="button" id="aura-cb-toggle-snap" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Ajuste a cuadrícula (10px)', 'aura-suite' ); ?>">⊞ <span class="aura-cb-hide-sm"><?php esc_html_e( 'Snap', 'aura-suite' ); ?></span></button>
            <button type="button" id="aura-cb-native-fullscreen" class="aura-cb-tool-btn" title="<?php esc_attr_e( 'Pantalla completa del navegador (F11)', 'aura-suite' ); ?>">⛶</button>
        </div>

        <div class="aura-cert-builder-topbar-right">
            <button type="button" id="aura-cb-preview-sample" class="aura-cb-topbar-btn" title="<?php esc_attr_e( 'Previsualizar reemplazando variables con datos de ejemplo', 'aura-suite' ); ?>">
                ✨ <span class="aura-cb-hide-md"><?php esc_html_e( 'Preview Ejemplo', 'aura-suite' ); ?></span>
            </button>
            <button type="button" id="aura-cb-preview" class="aura-cb-topbar-btn" title="<?php esc_attr_e( 'Vista previa en nueva pestaña', 'aura-suite' ); ?>">
                👁 <span class="aura-cb-hide-md"><?php esc_html_e( 'Vista Previa', 'aura-suite' ); ?></span>
            </button>
            <button type="button" id="aura-cb-save" class="aura-cb-topbar-btn is-primary" title="<?php esc_attr_e( 'Guardar plantilla (Ctrl+S)', 'aura-suite' ); ?>">
                💾 <?php esc_html_e( 'Guardar', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-cb-save-exit" class="aura-cb-topbar-btn is-dark">
                <?php esc_html_e( 'Guardar y Salir', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-cb-toggle-right" class="aura-cb-tool-btn is-active" title="<?php esc_attr_e( 'Mostrar / ocultar panel derecho', 'aura-suite' ); ?>">◨</button>
        </div>
    </div>

    <div class="aura-cert-builder-layout" id="aura-cb-layout">

        <!-- Panel izquierdo con pestañas: Elementos | Cliparts | Variables | Diseños -->
        <aside class="aura-cert-builder-tools" id="aura-cb-left-panel">
            <div class="aura-cb-side-tabs aura-cb-side-tabs--4" role="tablist">
                <button type="button" class="aura-cb-side-tab is-active" data-lpane="elements" role="tab">
                    <span>✦</span><?php esc_html_e( 'Elem.', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-side-tab" data-lpane="cliparts" role="tab">
                    <span>🎭</span><?php esc_html_e( 'Cliparts', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-side-tab" data-lpane="vars" role="tab">
                    <span>{}</span><?php esc_html_e( 'Vars', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-side-tab" data-lpane="designs" role="tab">
                    <span>🎨</span><?php esc_html_e( 'Diseños', 'aura-suite' ); ?>
                </button>
            </div>

            <div class="aura-cb-side-body">
                <!-- Pane 1: Elementos -->
                <div class="aura-cb-side-pane is-active" data-lpane-content="elements">
                    <div class="aura-cb-pane-label"><?php esc_html_e( 'Insertar en el lienzo', 'aura-suite' ); ?></div>
                    <div class="aura-cb-elem-grid">
                        <button type="button" class="aura-cb-elem-btn" data-type="textbox">
                            <span>T</span><?php esc_html_e( 'Texto', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="aura-cb-elem-btn" data-type="heading">
                            <span>H</span><?php esc_html_e( 'Título', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="aura-cb-elem-btn" data-type="image">
                            <span>🖼</span><?php esc_html_e( 'Imagen / SVG', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="aura-cb-elem-btn" data-type="cliparts-tab">
                            <span>🎭</span><?php esc_html_e( 'Clipart Library', 'aura-suite' ); ?>
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
                            <span>✍</span><?php esc_html_e( '1 Firma', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="aura-cb-elem-btn" data-type="seal">
                            <span>💮</span><?php esc_html_e( 'Sello Oficial', 'aura-suite' ); ?>
                        </button>
                    </div>

                    <!-- Inserción rápida de 1 a 4 firmas dinámicas alineadas -->
                    <div class="aura-cb-sig-quickbox">
                        <div class="aura-cb-pane-label" style="margin-bottom:6px;">
                            ✍ <?php esc_html_e( 'Bloque de Firmas (1 a 4)', 'aura-suite' ); ?>
                        </div>
                        <p class="aura-cb-help-tiny">
                            <?php esc_html_e( 'Inserta automáticamente ranuras de firma + nombre y cargo distribuidos en fila:', 'aura-suite' ); ?>
                        </p>
                        <div class="aura-cb-sig-count-bar">
                            <button type="button" class="aura-cb-sig-row-btn" data-sig-count="1" title="<?php esc_attr_e( 'Insertar 1 firma centrada', 'aura-suite' ); ?>">1 <?php esc_html_e( 'Firma', 'aura-suite' ); ?></button>
                            <button type="button" class="aura-cb-sig-row-btn" data-sig-count="2" title="<?php esc_attr_e( 'Insertar 2 firmas en paralelo', 'aura-suite' ); ?>">2 <?php esc_html_e( 'Firmas', 'aura-suite' ); ?></button>
                            <button type="button" class="aura-cb-sig-row-btn" data-sig-count="3" title="<?php esc_attr_e( 'Insertar 3 firmas alineadas', 'aura-suite' ); ?>">3 <?php esc_html_e( 'Firmas', 'aura-suite' ); ?></button>
                            <button type="button" class="aura-cb-sig-row-btn" data-sig-count="4" title="<?php esc_attr_e( 'Insertar 4 firmas alineadas', 'aura-suite' ); ?>">4 <?php esc_html_e( 'Firmas', 'aura-suite' ); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Pane 2: Clipart Library & Subida SVG/PNG/JPG -->
                <div class="aura-cb-side-pane" data-lpane-content="cliparts">
                    <div class="aura-cb-pane-label"><?php esc_html_e( 'Subir Imagen o Vector SVG', 'aura-suite' ); ?></div>
                    <label class="aura-cb-upload-box" id="aura-cb-upload-dropzone" for="aura-cb-file-upload">
                        <input type="file" id="aura-cb-file-upload" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp" hidden>
                        <span class="aura-cb-upload-icon">⬆</span>
                        <strong><?php esc_html_e( 'Sube archivos SVG, PNG o JPG', 'aura-suite' ); ?></strong>
                        <small><?php esc_html_e( 'Haz clic o arrastra un archivo aquí. Los SVG editan color en vivo.', 'aura-suite' ); ?></small>
                    </label>
                    <div id="aura-cb-upload-status" class="aura-cb-upload-status" style="display:none;"></div>
                    <button type="button" id="aura-cb-open-wp-media" class="button button-small" style="width:100%;margin:6px 0 12px;">
                        🖼 <?php esc_html_e( 'Elegir de Biblioteca Multimedia WP', 'aura-suite' ); ?>
                    </button>

                    <div class="aura-cb-pane-label"><?php esc_html_e( 'Clipart Library (SVG Editable)', 'aura-suite' ); ?></div>
                    <p class="aura-cb-help-tiny">
                        <?php esc_html_e( 'Los cliparts SVG admiten cambios de color en tiempo real desde el editor.', 'aura-suite' ); ?>
                    </p>
                    <input type="search" id="aura-cb-clipart-search" class="aura-input" style="width:100%;margin-bottom:6px;font-size:12px;"
                           placeholder="<?php esc_attr_e( 'Buscar clipart (laurel, medalla, biblia…)', 'aura-suite' ); ?>">
                    <select id="aura-cb-clipart-cat" class="aura-input" style="width:100%;margin-bottom:8px;font-size:12px;">
                        <option value=""><?php esc_html_e( 'Todas las categorías', 'aura-suite' ); ?></option>
                    </select>
                    <div id="aura-cb-clipart-grid" class="aura-cb-clipart-grid">
                        <!-- Poblado por JS desde auraCertBuilder.clipartLibrary -->
                    </div>
                </div>

                <!-- Pane 3: Variables -->
                <div class="aura-cb-side-pane" data-lpane-content="vars">
                    <div class="aura-cb-pane-label"><?php esc_html_e( 'Campos Dinámicos', 'aura-suite' ); ?></div>
                    <input type="search" id="aura-cb-var-search" class="aura-input" style="width:100%;margin-bottom:8px;font-size:12px;"
                           placeholder="<?php esc_attr_e( 'Buscar variable (ej. firmante_3)…', 'aura-suite' ); ?>">
                    <div id="aura-cb-vars-list" class="aura-cb-vars-list">
                        <!-- Poblado por JS desde auraCertBuilder.dynamicVars -->
                    </div>
                </div>

                <!-- Pane 3: Diseños Base -->
                <div class="aura-cb-side-pane" data-lpane-content="designs">
                    <div class="aura-cb-pane-label"><?php esc_html_e( 'Plantillas de Inicio Rápido', 'aura-suite' ); ?></div>
                    <p class="aura-cb-help-tiny" style="margin-bottom:8px;">
                        <?php esc_html_e( 'Haz clic en un diseño base para cargarlo en el lienzo (incluye diseño de 4 firmas).', 'aura-suite' ); ?>
                    </p>
                    <div id="aura-cb-prebuilt-list" class="aura-cb-prebuilt-list">
                        <!-- Poblado por JS desde auraCertBuilder.prebuiltDesigns -->
                    </div>
                </div>
            </div>
        </aside>

        <!-- Área de lienzo (centro ampliado al máximo) -->
        <main class="aura-cert-builder-canvas-wrap" id="aura-cb-canvas-wrap">
            <div class="aura-cb-canvas-viewport">
                <div id="aura-cb-stage" class="aura-cb-stage">
                    <canvas id="aura-cert-canvas"></canvas>
                    <div id="aura-cb-guides-overlay" class="aura-cb-guides-overlay" style="display:none;"></div>
                </div>
            </div>
            <div class="aura-cb-canvas-hud" aria-hidden="true">
                <span id="aura-cb-hud-dims">A4 · 297 × 210 mm</span>
                <span class="aura-cb-hud-dot">•</span>
                <span><?php esc_html_e( 'Ctrl+S Guardar · Ctrl+D Duplicar · Supr Eliminar', 'aura-suite' ); ?></span>
            </div>
        </main>

        <!-- Panel derecho con pestañas: Ajustes | Capas | Firmantes -->
        <aside class="aura-cert-builder-props" id="aura-cb-right-panel">
            <div class="aura-cb-side-tabs" role="tablist">
                <button type="button" class="aura-cb-side-tab aura-cb-rpane-tab is-active" data-rpane="props" role="tab">
                    <span>⚙</span><?php esc_html_e( 'Ajustes', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-cb-side-tab aura-cb-rpane-tab" data-rpane="layers" role="tab">
                    <span>📑</span><?php esc_html_e( 'Capas', 'aura-suite' ); ?> <em id="aura-cb-layers-count" class="aura-cb-tab-badge">0</em>
                </button>
                <button type="button" class="aura-cb-side-tab aura-cb-rpane-tab" data-rpane="signers" role="tab">
                    <span>✍</span><?php esc_html_e( 'Firmas', 'aura-suite' ); ?> <em id="aura-cb-signers-count" class="aura-cb-tab-badge">0</em>
                </button>
            </div>

            <div class="aura-cb-side-body">
                <!-- Right Pane 1: Documento + Propiedades del objeto -->
                <div class="aura-cb-side-pane is-active" data-rpane-content="props">
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

                        <!-- Propiedades de Clipart / SVG Vectorial (Colores en tiempo real) -->
                        <div id="aura-cb-svg-props" class="aura-cb-subblock" style="display:none;">
                            <span class="aura-cb-sublabel">🎨 <?php esc_html_e( 'Colores del SVG / Clipart', 'aura-suite' ); ?></span>
                            <p class="aura-cb-help-tiny">
                                <?php esc_html_e( 'Cambia cualquier color del SVG en tiempo real.', 'aura-suite' ); ?>
                            </p>
                            <div id="aura-cb-svg-colors-list" class="aura-cb-svg-colors-list"></div>
                            <div class="aura-cb-svg-mono-row">
                                <input type="color" id="aura-cb-svg-mono-color" value="#1e293b" title="<?php esc_attr_e( 'Elegir color único', 'aura-suite' ); ?>" style="width:32px;height:28px;padding:1px;">
                                <button type="button" id="aura-cb-svg-mono-apply" class="button button-small" style="flex:1;">
                                    <?php esc_html_e( 'Aplicar un solo color', 'aura-suite' ); ?>
                                </button>
                            </div>
                            <button type="button" id="aura-cb-svg-reset-colors" class="button button-small" style="width:100%;margin-top:6px;">
                                ↺ <?php esc_html_e( 'Restaurar colores originales', 'aura-suite' ); ?>
                            </button>
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
                </div>

                <!-- Right Pane 2: Capas -->
                <div class="aura-cb-side-pane" data-rpane-content="layers">
                    <div class="aura-cb-pane-label"><?php esc_html_e( 'Organizador de Capas', 'aura-suite' ); ?></div>
                    <div class="aura-cb-layers-toolbar" title="<?php esc_attr_e( 'Mover capa seleccionada', 'aura-suite' ); ?>">
                        <button type="button" id="aura-cb-layer-front" class="button button-small" title="<?php esc_attr_e( 'Enviar encima de todo', 'aura-suite' ); ?>">⇈ <?php esc_html_e( 'Frente', 'aura-suite' ); ?></button>
                        <button type="button" id="aura-cb-layer-up"    class="button button-small" title="<?php esc_attr_e( 'Subir 1 nivel', 'aura-suite' ); ?>">↑</button>
                        <button type="button" id="aura-cb-layer-down"  class="button button-small" title="<?php esc_attr_e( 'Bajar 1 nivel', 'aura-suite' ); ?>">↓</button>
                        <button type="button" id="aura-cb-layer-back"  class="button button-small" title="<?php esc_attr_e( 'Enviar al fondo', 'aura-suite' ); ?>">⇊ <?php esc_html_e( 'Fondo', 'aura-suite' ); ?></button>
                    </div>
                    <p class="aura-cb-help-tiny" style="margin-bottom:8px;">
                        <?php esc_html_e( 'Arrastra o usa ↑↓ para apilar capas encima de otras · Ojo: ver/ocultar · Eslabón: bloquear.', 'aura-suite' ); ?>
                    </p>
                    <div id="aura-cb-layers-list" class="aura-cb-layers-list"></div>
                </div>

                <!-- Right Pane 3: Firmantes (Hasta 4) -->
                <div class="aura-cb-side-pane" data-rpane-content="signers">
                    <div class="aura-cb-pane-label"><?php esc_html_e( 'Firmantes Activos (Hasta 4)', 'aura-suite' ); ?></div>
                    <p class="aura-cb-help-tiny" style="margin-bottom:8px;">
                        <?php esc_html_e( 'Cada espacio #1–#4 se llena dinámicamente con los firmantes activos según su orden.', 'aura-suite' ); ?>
                    </p>
                    <div class="aura-cb-sig-count-bar" style="margin-bottom:10px;">
                        <button type="button" class="aura-cb-sig-row-btn" data-sig-count="1">1 <?php esc_html_e( 'Firma', 'aura-suite' ); ?></button>
                        <button type="button" class="aura-cb-sig-row-btn" data-sig-count="2">2 <?php esc_html_e( 'Firmas', 'aura-suite' ); ?></button>
                        <button type="button" class="aura-cb-sig-row-btn" data-sig-count="3">3 <?php esc_html_e( 'Firmas', 'aura-suite' ); ?></button>
                        <button type="button" class="aura-cb-sig-row-btn" data-sig-count="4">4 <?php esc_html_e( 'Firmas', 'aura-suite' ); ?></button>
                    </div>
                    <div id="aura-cb-signers-list" class="aura-cb-signers">
                        <!-- Poblado por JS desde auraCertBuilder.signers -->
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<!-- Hidden inputs para el save -->
<input type="hidden" id="aura-cb-template-id"
       value="<?php echo esc_attr( $is_new ? '0' : $template_id ); ?>">
<input type="hidden" id="aura-cb-templates-list-url"
       value="<?php echo esc_url( admin_url( 'admin.php?page=aura-certificates-templates' ) ); ?>">
