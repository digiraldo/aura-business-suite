<?php
/**
 * Template: Formulario de Libro (crear / editar) — Wizard 3 pasos
 *
 * Paso 1: Datos esenciales  (título, autor, Dewey, ISBN, editorial, año, idioma, páginas)
 * Paso 2: Clasificación, Ubicación & Inventario
 * Paso 3: Descripción, Palabras clave & Portada
 *
 * @package Aura_Business_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$areas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY name ASC"
) ?: [];

$dewey_classes = [
    '0' => '000 — Informática y Generalidades',
    '1' => '100 — Filosofía y Psicología',
    '2' => '200 — Religión',
    '3' => '300 — Ciencias Sociales',
    '4' => '400 — Lengua y Lingüística',
    '5' => '500 — Ciencias Puras',
    '6' => '600 — Tecnología Aplicada',
    '7' => '700 — Artes y Recreación',
    '8' => '800 — Literatura',
    '9' => '900 — Historia y Geografía',
];
?>
<form id="aura-lib-book-form" class="aura-lib-form" novalidate>
    <input type="hidden" id="aura-lib-book-id" name="id" value="0">

    <!-- ══ BANNER: PROMPT IA ════════════════════════════════════════ -->
    <div class="aura-lib-ai-prompt-banner">
        <div class="aura-lib-ai-prompt-icon">🤖</div>
        <div class="aura-lib-ai-prompt-text">
            <strong><?php esc_html_e( '¿No tienes todos los datos? Usa IA para obtenerlos', 'aura-business-suite' ); ?></strong>
            <span><?php esc_html_e( 'Copia el prompt, pégalo en ChatGPT u otra IA y rellena los campos automáticamente.', 'aura-business-suite' ); ?></span>
        </div>
        <button type="button" id="aura-lib-copy-prompt-btn" class="aura-lib-ai-prompt-copy-btn">
            <span class="dashicons dashicons-clipboard"></span>
            <?php esc_html_e( 'Copiar prompt', 'aura-business-suite' ); ?>
        </button>
    </div>
    <textarea id="aura-lib-ai-prompt-text" style="position:absolute;left:-9999px;top:-9999px;" readonly
              aria-hidden="true"><?php echo esc_textarea( 'Actúa como un bibliotecario experto. Proporcióname los datos del libro [INSERTAR NOMBRE DEL LIBRO Y AUTOR AQUÍ] para ingresarlos en un sistema de inventario. Necesito la información organizada exactamente de la siguiente manera:

Título:

Subtítulo: (Si no tiene, indicar \'N/A\')

Autor(es):

Editorial:

Número Dewey:

ISBN-10 o ISBN-13:

Año: (De la edición más reciente o estándar en español)

Idioma:

Edición (Prioriza la edición en español más conocida):

Páginas:

Categoría: (Ej: Literatura, Ciencia, Religión, etc.)

Subcategoría: (Ej: Novela, Física Cuántica, Vida Cristiana, etc.)

Descripción / Resumen: (Un párrafo conciso pero completo)

Palabras clave: (Al menos 5 términos separados por comas)

Por favor, asegúrate de que el Número Dewey sea el correcto según la clasificación temática del libro.' ); ?></textarea>

    <!-- ══ BARRA DE PROGRESO (Wizard) ══════════════════════════════ -->
    <div class="aura-lib-wizard-progress" aria-label="<?php esc_attr_e( 'Pasos del formulario', 'aura-business-suite' ); ?>">
        <div class="aura-lib-wizard-step active" data-step="1">
            <div class="aura-lib-wstep-circle">1</div>
            <span><?php esc_html_e( 'Datos esenciales', 'aura-business-suite' ); ?></span>
        </div>
        <div class="aura-lib-wizard-connector"></div>
        <div class="aura-lib-wizard-step" data-step="2">
            <div class="aura-lib-wstep-circle">2</div>
            <span><?php esc_html_e( 'Clasificación', 'aura-business-suite' ); ?></span>
        </div>
        <div class="aura-lib-wizard-connector"></div>
        <div class="aura-lib-wizard-step" data-step="3">
            <div class="aura-lib-wstep-circle">3</div>
            <span><?php esc_html_e( 'Descripción & Portada', 'aura-business-suite' ); ?></span>
        </div>
    </div>

    <!-- ══ PASO 1: DATOS ESENCIALES ════════════════════════════════ -->
    <div class="aura-lib-wstep-panel" id="aura-lib-wstep-1" style="display:block;">

        <div class="aura-lib-form-grid">

            <!-- Título -->
            <div class="aura-lib-form-row aura-lib-col-half">
                <label for="aura-lib-f-title">
                    <?php esc_html_e( 'Título', 'aura-business-suite' ); ?>
                    <span class="required">*</span>
                </label>
                <div class="aura-lib-input-wrap">
                    <input type="text" id="aura-lib-f-title" name="title" class="widefat"
                           maxlength="255" required autocomplete="off"
                           placeholder="<?php esc_attr_e( 'Título completo del libro', 'aura-business-suite' ); ?>">
                    <span class="aura-lib-field-icon"></span>
                </div>
                <span class="aura-lib-char-counter">0 / 255</span>
            </div>

            <!-- Subtítulo -->
            <div class="aura-lib-form-row aura-lib-col-half">
                <label for="aura-lib-f-subtitle"><?php esc_html_e( 'Subtítulo', 'aura-business-suite' ); ?></label>
                <div class="aura-lib-input-wrap">
                    <input type="text" id="aura-lib-f-subtitle" name="subtitle" class="widefat"
                           maxlength="255"
                           placeholder="<?php esc_attr_e( 'Opcional', 'aura-business-suite' ); ?>">
                </div>
            </div>

            <!-- Autor(es) -->
            <div class="aura-lib-form-row aura-lib-col-half">
                <label for="aura-lib-f-author">
                    <?php esc_html_e( 'Autor(es)', 'aura-business-suite' ); ?>
                    <span class="required">*</span>
                </label>
                <div class="aura-lib-input-wrap">
                    <input type="text" id="aura-lib-f-author" name="author" class="widefat"
                           maxlength="255" required autocomplete="off"
                           placeholder="<?php esc_attr_e( 'Ej: García Márquez, Gabriel', 'aura-business-suite' ); ?>">
                    <span class="aura-lib-field-icon"></span>
                </div>
            </div>

            <!-- Editorial -->
            <div class="aura-lib-form-row aura-lib-col-half">
                <label for="aura-lib-f-publisher"><?php esc_html_e( 'Editorial', 'aura-business-suite' ); ?></label>
                <div class="aura-lib-input-wrap">
                    <input type="text" id="aura-lib-f-publisher" name="publisher" class="widefat"
                           maxlength="150"
                           placeholder="<?php esc_attr_e( 'Nombre de la editorial', 'aura-business-suite' ); ?>">
                </div>
            </div>

            <!-- Número Dewey -->
            <div class="aura-lib-form-row aura-lib-col-full">
                <label for="aura-lib-f-dewey">
                    <?php esc_html_e( 'Número Dewey', 'aura-business-suite' ); ?>
                    <span class="aura-lib-hint"><?php esc_html_e( 'Ej: 220.5, 629.8, R92 GAR', 'aura-business-suite' ); ?></span>
                </label>
                <!-- Pills de clase rápida -->
                <div class="aura-lib-dewey-pills" role="group" aria-label="<?php esc_attr_e( 'Clase Dewey rápida', 'aura-business-suite' ); ?>">
                    <?php foreach ( $dewey_classes as $prefix => $label ) : ?>
                        <button type="button" class="aura-lib-dewey-pill" data-prefix="<?php echo esc_attr( $prefix * 100 ); ?>"
                                title="<?php echo esc_attr( $label ); ?>">
                            <?php echo esc_html( $prefix . '00' ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="aura-lib-input-wrap" style="max-width:200px;">
                    <input type="text" id="aura-lib-f-dewey" name="dewey_number" class="widefat"
                           maxlength="30" autocomplete="off"
                           placeholder="<?php esc_attr_e( 'xxx.x…', 'aura-business-suite' ); ?>">
                </div>
                <span id="aura-lib-dewey-label" class="aura-lib-dewey-desc"></span>
            </div>

            <!-- ISBN -->
            <div class="aura-lib-form-row aura-lib-col-third">
                <label for="aura-lib-f-isbn">
                    <?php esc_html_e( 'ISBN', 'aura-business-suite' ); ?>
                    <span class="aura-lib-hint"><?php esc_html_e( 'ISBN-10 o ISBN-13', 'aura-business-suite' ); ?></span>
                </label>
                <div class="aura-lib-input-wrap">
                    <input type="text" id="aura-lib-f-isbn" name="isbn" class="widefat"
                           maxlength="30" autocomplete="off"
                           placeholder="978-0-00-000000-0">
                    <span class="aura-lib-field-icon" id="aura-lib-isbn-icon"></span>
                </div>
                <span class="aura-lib-isbn-error aura-lib-field-error" style="display:none;">
                    <?php esc_html_e( 'ISBN inválido — verifica el formato', 'aura-business-suite' ); ?>
                </span>
            </div>

            <!-- Año -->
            <div class="aura-lib-form-row aura-lib-col-sixth">
                <label for="aura-lib-f-year"><?php esc_html_e( 'Año', 'aura-business-suite' ); ?></label>
                <input type="number" id="aura-lib-f-year" name="year_published"
                       min="1800" max="<?php echo esc_attr( (string) ( (int) gmdate( 'Y' ) + 1 ) ); ?>"
                       class="small-text" placeholder="<?php echo esc_attr( gmdate( 'Y' ) ); ?>">
            </div>

            <!-- Idioma -->
            <div class="aura-lib-form-row aura-lib-col-quarter">
                <label for="aura-lib-f-language">
                    <?php esc_html_e( 'Idioma', 'aura-business-suite' ); ?>
                    <span class="required">*</span>
                </label>
                <select id="aura-lib-f-language" name="language" class="widefat">
                    <option value="Español"><?php   esc_html_e( 'Español',   'aura-business-suite' ); ?></option>
                    <option value="Inglés"><?php    esc_html_e( 'Inglés',    'aura-business-suite' ); ?></option>
                    <option value="Francés"><?php   esc_html_e( 'Francés',   'aura-business-suite' ); ?></option>
                    <option value="Portugués"><?php esc_html_e( 'Portugués', 'aura-business-suite' ); ?></option>
                    <option value="Alemán"><?php    esc_html_e( 'Alemán',    'aura-business-suite' ); ?></option>
                    <option value="Italiano"><?php  esc_html_e( 'Italiano',  'aura-business-suite' ); ?></option>
                    <option value="Otro"><?php      esc_html_e( 'Otro',      'aura-business-suite' ); ?></option>
                </select>
            </div>

            <!-- Edición / Páginas -->
            <div class="aura-lib-form-row aura-lib-col-sixth">
                <label for="aura-lib-f-edition"><?php esc_html_e( 'Edición', 'aura-business-suite' ); ?></label>
                <input type="text" id="aura-lib-f-edition" name="edition" class="widefat"
                       maxlength="50" placeholder="<?php esc_attr_e( '1ª ed.', 'aura-business-suite' ); ?>">
            </div>

            <div class="aura-lib-form-row aura-lib-col-sixth">
                <label for="aura-lib-f-pages"><?php esc_html_e( 'Páginas', 'aura-business-suite' ); ?></label>
                <input type="number" id="aura-lib-f-pages" name="pages" class="small-text"
                       min="1" max="9999" placeholder="0">
            </div>

        </div><!-- .aura-lib-form-grid -->

        <!-- Navegación paso 1 -->
        <div class="aura-lib-wizard-nav">
            <span class="aura-lib-required-note">
                <span class="required">*</span> <?php esc_html_e( 'Campo obligatorio', 'aura-business-suite' ); ?>
            </span>
            <button type="button" class="button button-primary aura-lib-btn-next" data-from="1">
                <?php esc_html_e( 'Siguiente', 'aura-business-suite' ); ?> →
            </button>
        </div>

    </div><!-- #aura-lib-wstep-1 -->

    <!-- ══ PASO 2: CLASIFICACIÓN, UBICACIÓN & INVENTARIO ═══════════ -->
    <div class="aura-lib-wstep-panel" id="aura-lib-wstep-2" style="display:none;">

        <div class="aura-lib-form-grid">

            <!-- Categoría -->
            <div class="aura-lib-form-row aura-lib-col-third">
                <label for="aura-lib-f-category"><?php esc_html_e( 'Categoría', 'aura-business-suite' ); ?></label>
                <input type="text" id="aura-lib-f-category" name="category" class="widefat"
                       maxlength="100"
                       placeholder="<?php esc_attr_e( 'Ej: Literatura, Ciencia…', 'aura-business-suite' ); ?>">
            </div>

            <div class="aura-lib-form-row aura-lib-col-third">
                <label for="aura-lib-f-subcategory"><?php esc_html_e( 'Subcategoría', 'aura-business-suite' ); ?></label>
                <input type="text" id="aura-lib-f-subcategory" name="subcategory" class="widefat"
                       maxlength="100"
                       placeholder="<?php esc_attr_e( 'Ej: Novela, Física cuántica…', 'aura-business-suite' ); ?>">
            </div>

            <?php if ( ! empty( $areas ) ) : ?>
            <div class="aura-lib-form-row aura-lib-col-third">
                <label for="aura-lib-f-area"><?php esc_html_e( 'Área organizacional', 'aura-business-suite' ); ?></label>
                <select id="aura-lib-f-area" name="area_id" class="widefat">
                    <option value="0"><?php esc_html_e( '— Sin área (global) —', 'aura-business-suite' ); ?></option>
                    <?php foreach ( $areas as $area ) : ?>
                    <option value="<?php echo esc_attr( $area->id ); ?>"><?php echo esc_html( $area->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else : ?>
            <input type="hidden" id="aura-lib-f-area" name="area_id" value="0">
            <?php endif; ?>

            <!-- Sala / Estante -->
            <div class="aura-lib-form-row aura-lib-col-third">
                <label for="aura-lib-f-location">
                    <?php esc_html_e( 'Sala / Ubicación física', 'aura-business-suite' ); ?>
                </label>
                <input type="text" id="aura-lib-f-location" name="physical_location" class="widefat"
                       maxlength="100"
                       placeholder="<?php esc_attr_e( 'Ej: Sala Principal', 'aura-business-suite' ); ?>">
            </div>

            <div class="aura-lib-form-row aura-lib-col-quarter">
                <label for="aura-lib-f-shelf"><?php esc_html_e( 'Código de estante', 'aura-business-suite' ); ?></label>
                <input type="text" id="aura-lib-f-shelf" name="shelf_code" class="widefat"
                       maxlength="50"
                       placeholder="<?php esc_attr_e( 'Ej: A3-F2', 'aura-business-suite' ); ?>">
            </div>

        </div>

        <!-- Inventario — separado visualmente -->
        <div class="aura-lib-inventory-card">
            <div class="aura-lib-inventory-card-title">
                📦 <?php esc_html_e( 'Inventario', 'aura-business-suite' ); ?>
            </div>
            <div class="aura-lib-form-grid">

                <div class="aura-lib-form-row aura-lib-col-sixth">
                    <label for="aura-lib-f-copies">
                        <?php esc_html_e( 'Total ejemplares', 'aura-business-suite' ); ?>
                        <span class="required">*</span>
                    </label>
                    <div class="aura-lib-copies-stepper">
                        <button type="button" class="aura-lib-stepper-btn" id="aura-lib-copies-minus">−</button>
                        <input type="number" id="aura-lib-f-copies" name="total_copies"
                               class="small-text" min="1" value="1" required>
                        <button type="button" class="aura-lib-stepper-btn" id="aura-lib-copies-plus">+</button>
                    </div>
                </div>

                <div class="aura-lib-form-row aura-lib-col-third">
                    <label for="aura-lib-f-status">
                        <?php esc_html_e( 'Estado', 'aura-business-suite' ); ?>
                        <span class="required">*</span>
                    </label>
                    <select id="aura-lib-f-status" name="status" class="widefat" required>
                        <option value="available"><?php      esc_html_e( '✅ Disponible',    'aura-business-suite' ); ?></option>
                        <option value="unavailable"><?php    esc_html_e( '🔴 Sin stock',     'aura-business-suite' ); ?></option>
                        <option value="reference_only"><?php esc_html_e( '📖 Solo consulta', 'aura-business-suite' ); ?></option>
                        <option value="lost"><?php           esc_html_e( '⚠️ Perdido',       'aura-business-suite' ); ?></option>
                        <option value="withdrawn"><?php      esc_html_e( '🗄️ Retirado',      'aura-business-suite' ); ?></option>
                    </select>
                    <span id="aura-lib-status-hint" class="aura-lib-hint" style="margin-left:0;"></span>
                </div>

            </div>
        </div>

        <!-- Navegación paso 2 -->
        <div class="aura-lib-wizard-nav">
            <button type="button" class="button aura-lib-btn-back" data-to="1">
                ← <?php esc_html_e( 'Anterior', 'aura-business-suite' ); ?>
            </button>
            <button type="button" class="button button-primary aura-lib-btn-next" data-from="2">
                <?php esc_html_e( 'Siguiente', 'aura-business-suite' ); ?> →
            </button>
        </div>

    </div><!-- #aura-lib-wstep-2 -->

    <!-- ══ PASO 3: DESCRIPCIÓN, PALABRAS CLAVE & PORTADA ═══════════ -->
    <div class="aura-lib-wstep-panel" id="aura-lib-wstep-3" style="display:none;">

        <div class="aura-lib-form-grid">

            <!-- Descripción -->
            <div class="aura-lib-form-row aura-lib-col-full">
                <label for="aura-lib-f-description">
                    <?php esc_html_e( 'Descripción / Resumen', 'aura-business-suite' ); ?>
                </label>
                <textarea id="aura-lib-f-description" name="description" class="widefat"
                          rows="4" maxlength="2000"
                          placeholder="<?php esc_attr_e( 'Breve sinopsis o descripción del contenido del libro…', 'aura-business-suite' ); ?>"></textarea>
                <span class="aura-lib-char-counter" id="aura-lib-desc-counter">0 / 2000</span>
            </div>

            <!-- Palabras clave — Tag Chips -->
            <div class="aura-lib-form-row aura-lib-col-full">
                <label>
                    <?php esc_html_e( 'Palabras clave', 'aura-business-suite' ); ?>
                    <span class="aura-lib-hint"><?php esc_html_e( 'Escribe y presiona Enter o coma para agregar', 'aura-business-suite' ); ?></span>
                </label>
                <input type="hidden" id="aura-lib-f-keywords" name="keywords" value="">
                <div class="aura-lib-tags-container" id="aura-lib-tags-container" tabindex="0">
                    <input type="text" class="aura-lib-tags-input" id="aura-lib-tags-text"
                           placeholder="<?php esc_attr_e( 'Ej: historia, guerra, ficción…', 'aura-business-suite' ); ?>"
                           maxlength="60" autocomplete="off">
                </div>
            </div>

        </div>

        <!-- Portada — Zona drag & drop -->
        <div class="aura-lib-step3-cover">
            <label class="aura-lib-step3-label">
                <?php esc_html_e( 'Imagen de Portada', 'aura-business-suite' ); ?>
            </label>
            <input type="hidden" id="aura-lib-f-cover-id" name="cover_image_id" value="0">

            <div class="aura-lib-dropzone" id="aura-lib-dropzone">
                <div id="aura-lib-cover-preview" class="aura-lib-dz-preview-wrap">
                    <span class="dashicons dashicons-format-image aura-lib-cover-placeholder"></span>
                    <div class="aura-lib-dz-hint">
                        <strong><?php esc_html_e( 'Arrastra una imagen aquí', 'aura-business-suite' ); ?></strong>
                        <span><?php esc_html_e( 'o', 'aura-business-suite' ); ?></span>
                    </div>
                </div>
                <div class="aura-lib-dz-actions">
                    <button type="button" id="aura-lib-btn-select-cover" class="button">
                        <span class="dashicons dashicons-upload"></span>
                        <?php esc_html_e( 'Seleccionar imagen', 'aura-business-suite' ); ?>
                    </button>
                    <button type="button" id="aura-lib-btn-remove-cover" class="button button-link-delete" style="display:none;">
                        🗑 <?php esc_html_e( 'Quitar portada', 'aura-business-suite' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Navegación paso 3 + guardar -->
        <div class="aura-lib-wizard-nav">
            <button type="button" class="button aura-lib-btn-back" data-to="2">
                ← <?php esc_html_e( 'Anterior', 'aura-business-suite' ); ?>
            </button>
            <div class="aura-lib-save-area">
                <span class="aura-lib-save-msg" style="display:none;"></span>
                <button type="submit" id="aura-lib-btn-save" class="button button-primary aura-lib-btn-save-main">
                    <span class="aura-lib-btn-label">💾 <?php esc_html_e( 'Guardar libro', 'aura-business-suite' ); ?></span>
                    <span class="aura-lib-btn-loading" style="display:none;">
                        <span class="spinner is-active" style="float:none;margin:0;vertical-align:middle;"></span>
                        <?php esc_html_e( 'Guardando…', 'aura-business-suite' ); ?>
                    </span>
                </button>
            </div>
        </div>

    </div><!-- #aura-lib-wstep-3 -->

</form><!-- #aura-lib-book-form -->
