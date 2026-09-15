<?php
/**
 * Renderizado público de formulario — [aura_form id="X"]
 *
 * Variables disponibles al incluir este template:
 *  $form              — objeto de la tabla aura_forms
 *  $fields            — array de objetos de la tabla aura_form_fields, ordenados por sort_order
 *  $override_redirect — URL de redirección override (puede estar vacía)
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// El nonce ya fue generado en Aura_Forms_Frontend::enqueue_assets() via wp_localize_script.
// Aquí creamos uno adicional embebido en el formulario para fallback sin JS.
$form_nonce  = wp_create_nonce( 'aura_form_submit_' . $form->id );
$primary_css = esc_attr( $form->primary_color ?: '#2563eb' );
?>
<div class="aura-form-wrap" id="aura-form-<?php echo esc_attr( $form->id ); ?>"
     style="--af-primary: <?php echo $primary_css; ?>;">

    <?php // ── Cabecera del formulario ──────────────────────────── ?>
    <div class="aura-form-header">
        <?php if ( $form->logo_url ) : ?>
            <img src="<?php echo esc_url( $form->logo_url ); ?>"
                 alt="<?php echo esc_attr( $form->title ); ?>"
                 class="aura-form-logo">
        <?php endif; ?>
        <?php if ( ! empty( $form->company_name ) ) : ?>
            <p class="aura-form-company"><?php echo esc_html( $form->company_name ); ?></p>
        <?php endif; ?>
        <h2 class="aura-form-title"><?php echo esc_html( $form->title ); ?></h2>
        <?php if ( $form->description ) : ?>
            <div class="aura-form-description">
                <?php echo wp_kses_post( $form->description ); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php // ── Contenedor de mensajes de error / éxito ─────────── ?>
    <div class="aura-form-messages" role="alert" aria-live="polite"></div>

    <?php // ── Formulario principal ─────────────────────────────── ?>
    <form class="aura-form"
          id="aura-form-body-<?php echo esc_attr( $form->id ); ?>"
          method="post"
          enctype="multipart/form-data"
          novalidate
          data-form-id="<?php echo esc_attr( $form->id ); ?>"
          data-redirect="<?php echo esc_attr( $override_redirect ); ?>">

        <?php // ── Campos ocultos de seguridad ─────────────────── ?>
        <input type="hidden" name="action"   value="aura_form_submit">
        <input type="hidden" name="form_id"  value="<?php echo esc_attr( $form->id ); ?>">
        <input type="hidden" name="nonce"    value="<?php echo esc_attr( $form_nonce ); ?>">

        <?php // ── Honeypot (invisible para humanos) ──────────────
            // El campo tiene display:none vía CSS; los bots lo llenan. ?>
        <div class="aura-hp-field" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;">
            <label for="_aura_hp_<?php echo esc_attr( $form->id ); ?>"><?php esc_html_e( 'No rellenar', 'aura-suite' ); ?></label>
            <input type="text"
                   id="_aura_hp_<?php echo esc_attr( $form->id ); ?>"
                   name="_aura_hp"
                   value=""
                   tabindex="-1"
                   autocomplete="off">
        </div>

        <?php // ── Renderizar cada campo ─────────────────────────── ?>
        <?php foreach ( $fields as $field ) :
            $field_id   = (int) $field->id;
            $field_name = 'field_' . $field_id;
            $ftype      = $field->field_type;
            $label      = esc_html( $field->label );
            $desc       = $field->description ? wp_kses_post( $field->description ) : '';
            $required   = (bool) $field->is_required;
            $req_attr   = $required ? ' required' : '';
            $req_star   = $required ? ' <span class="aura-required" aria-hidden="true">*</span>' : '';
            $placeholder = esc_attr( $field->placeholder ?? '' );
            $default_val = esc_attr( $field->default_value ?? '' );
            $options     = $field->options_json ? json_decode( $field->options_json, true ) : [];
            if ( json_last_error() !== JSON_ERROR_NONE ) $options = [];
        ?>
        <?php // ────────────────────────────────────────────────────
           // SECTION TITLE (separador visual)
           // ─────────────────────────────────────────────────────── ?>
        <?php if ( $ftype === 'section_title' ) : ?>
        <div class="aura-field aura-field-section-title">
            <h3 class="aura-section-title"><?php echo $label; ?></h3>
            <?php if ( $desc ) : ?><p class="aura-section-desc"><?php echo $desc; ?></p><?php endif; ?>
        </div>

        <?php // PARAGRAPH (texto informativo) ?>
        <?php elseif ( $ftype === 'paragraph' ) : ?>
        <div class="aura-field aura-field-paragraph">
            <div class="aura-paragraph-text"><?php echo wp_kses_post( $field->label ); ?></div>
            <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
        </div>

        <?php // IMAGE (imagen decorativa) ?>
        <?php elseif ( $ftype === 'image' ) : ?>
        <?php
            // La URL se guarda directamente en la columna image_url
            $img_src = $field->image_url ?? '';
        ?>
        <div class="aura-field aura-field-image">
            <?php if ( $img_src ) : ?>
                <img src="<?php echo esc_url( $img_src ); ?>"
                     alt="<?php echo $label; ?>"
                     class="aura-field-image-img">
            <?php endif; ?>
            <?php if ( $desc ) : ?><p class="aura-field-image-caption"><?php echo $desc; ?></p><?php endif; ?>
        </div>

        <?php // DOWNLOADABLE (descarga de documento) ?>
        <?php elseif ( $ftype === 'downloadable' ) : ?>
        <?php
            // Los datos se guardan en columnas propias (file_uploaded, file_url, instructions)
            $dl_url   = '';
            if ( ! empty( $field->file_url ) ) {
                $dl_url = $field->file_url;
            } elseif ( ! empty( $field->file_uploaded ) ) {
                // Puede ser una URL completa (desde wp.media) o una ruta relativa
                $dl_url = $field->file_uploaded;
            }
            $dl_instr = $field->instructions ?? '';
        ?>
        <div class="aura-field aura-field-downloadable">
            <span class="aura-field-label"><?php echo $label; ?></span>
            <?php if ( $dl_instr ) : ?><p class="aura-field-desc"><?php echo esc_html( $dl_instr ); ?></p><?php endif; ?>
            <?php if ( $dl_url ) : ?>
            <a href="<?php echo esc_url( $dl_url ); ?>"
               class="aura-download-btn"
               download
               target="_blank"
               rel="noopener noreferrer">
                <span class="dashicons dashicons-download" aria-hidden="true"></span>
                <?php echo $label; ?>
            </a>
            <?php endif; ?>
        </div>

        <?php // HIDDEN (campo oculto, valor fijo) ?>
        <?php elseif ( $ftype === 'hidden' ) : ?>
        <input type="hidden"
               name="<?php echo esc_attr( $field_name ); ?>"
               value="<?php echo $default_val; ?>">

        <?php // TERMS (aceptar/rechazar términos) ?>
        <?php elseif ( $ftype === 'terms' ) : ?>
        <?php
            // Los datos se guardan en columnas propias (terms_text, disagreement_message)
            $terms_text   = $field->terms_text ?? '';
            $disagree_msg = $field->disagreement_message ? esc_html( $field->disagreement_message ) : '';
        ?>
        <div class="aura-field aura-field-terms" data-field-id="<?php echo esc_attr( $field_id ); ?>">
            <fieldset>
                <legend class="aura-field-label"><?php echo $label; ?><?php echo $req_star; ?></legend>
                <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
                <?php if ( $terms_text ) : ?>
                <div class="aura-terms-text"><?php echo wp_kses_post( $terms_text ); ?></div>
                <?php endif; ?>
                <div class="aura-terms-radios">
                    <label class="aura-radio-label aura-terms-agree">
                        <input type="radio"
                               name="<?php echo esc_attr( $field_name . '_agreement_response' ); ?>"
                               value="agree"
                               class="aura-terms-radio"
                               data-disagree-msg="<?php echo esc_attr( $disagree_msg ); ?>"
                               <?php echo $req_attr; ?>>
                        <?php esc_html_e( 'Acepto', 'aura-suite' ); ?>
                    </label>
                    <label class="aura-radio-label aura-terms-disagree">
                        <input type="radio"
                               name="<?php echo esc_attr( $field_name . '_agreement_response' ); ?>"
                               value="disagree"
                               class="aura-terms-radio">
                        <?php esc_html_e( 'No acepto', 'aura-suite' ); ?>
                    </label>
                </div>
                <?php if ( $disagree_msg ) : ?>
                <p class="aura-terms-disagree-msg" style="display:none;"><?php echo $disagree_msg; ?></p>
                <?php endif; ?>
            </fieldset>
        </div>

        <?php // ACCEPT_ONLY_TERMS (solo acepto) ?>
        <?php elseif ( $ftype === 'accept_only_terms' ) : ?>
        <?php
            // El texto se guarda en la columna terms_text
            $ao_text = $field->terms_text ?? '';
        ?>
        <div class="aura-field aura-field-accept-only-terms">
            <?php if ( $ao_text ) : ?>
            <div class="aura-terms-text"><?php echo wp_kses_post( $ao_text ); ?></div>
            <?php endif; ?>
            <label class="aura-checkbox-label">
                <input type="checkbox"
                       name="<?php echo esc_attr( $field_name ); ?>"
                       value="1"
                       class="aura-input"
                       <?php echo $req_attr; ?>>
                <?php echo $label; ?><?php echo $req_star; ?>
            </label>
            <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
        </div>

        <?php // SCALE (NPS/Likert) ?>
        <?php elseif ( $ftype === 'scale' ) : ?>
        <?php $scale_max = (int) ( $field->max_value ?: 10 ); ?>
        <div class="aura-field aura-field-scale" data-field-id="<?php echo esc_attr( $field_id ); ?>">
            <label class="aura-field-label"><?php echo $label; ?><?php echo $req_star; ?></label>
            <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
            <div class="aura-scale-wrap">
                <?php if ( $scale_max >= 10 ) : ?>
                <span class="aura-scale-hint aura-scale-hint--low"><?php esc_html_e( 'Nada probable', 'aura-suite' ); ?></span>
                <?php endif; ?>
                <div class="aura-scale-buttons" role="group" aria-label="<?php echo esc_attr( $label ); ?>">
                    <?php for ( $i = 1; $i <= $scale_max; $i++ ) : ?>
                    <button type="button"
                            class="aura-scale-btn"
                            data-value="<?php echo esc_attr( $i ); ?>"
                            aria-label="<?php echo esc_attr( $i ); ?>">
                        <?php echo esc_html( $i ); ?>
                    </button>
                    <?php endfor; ?>
                </div>
                <?php if ( $scale_max >= 10 ) : ?>
                <span class="aura-scale-hint aura-scale-hint--high"><?php esc_html_e( 'Muy probable', 'aura-suite' ); ?></span>
                <?php endif; ?>
            </div>
            <input type="hidden"
                   name="<?php echo esc_attr( $field_name ); ?>"
                   id="<?php echo esc_attr( $field_name ); ?>"
                   value=""
                   <?php if ( $required ) echo 'data-required="1"'; ?>>
        </div>

        <?php // RADIO (opción única) ?>
        <?php elseif ( $ftype === 'radio' ) : ?>
        <div class="aura-field aura-field-radio">
            <fieldset>
                <legend class="aura-field-label"><?php echo $label; ?><?php echo $req_star; ?></legend>
                <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
                <div class="aura-radio-group">
                    <?php foreach ( (array) $options as $opt ) :
                        $opt_label = is_array( $opt ) ? ( $opt['label'] ?? $opt[0] ?? '' ) : $opt;
                        $opt_val   = esc_attr( $opt_label );
                    ?>
                    <label class="aura-radio-label">
                        <input type="radio"
                               name="<?php echo esc_attr( $field_name ); ?>"
                               value="<?php echo $opt_val; ?>"
                               class="aura-input"
                               <?php echo $req_attr; ?>>
                        <?php echo esc_html( $opt_label ); ?>
                    </label>
                    <?php endforeach; ?>
                    <?php if ( ! empty( $field->has_other ) ) : ?>
                    <label class="aura-radio-label aura-radio-other">
                        <input type="radio"
                               name="<?php echo esc_attr( $field_name ); ?>"
                               value="__other__"
                               class="aura-input aura-other-radio"
                               <?php echo $req_attr; ?>>
                        <?php esc_html_e( 'Otro:', 'aura-suite' ); ?>
                        <input type="text"
                               name="<?php echo esc_attr( $field_name . '_other_text' ); ?>"
                               class="aura-other-text-input"
                               placeholder="<?php esc_attr_e( 'Especifique...', 'aura-suite' ); ?>"
                               disabled>
                    </label>
                    <?php endif; ?>
                </div>
            </fieldset>
        </div>
        <?php elseif ( $ftype === 'checkbox' ) : ?>
        <div class="aura-field aura-field-checkbox">
            <fieldset>
                <legend class="aura-field-label"><?php echo $label; ?><?php echo $req_star; ?></legend>
                <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
                <div class="aura-checkbox-group">
                    <?php foreach ( (array) $options as $opt ) :
                        $opt_label = is_array( $opt ) ? ( $opt['label'] ?? $opt[0] ?? '' ) : $opt;
                        $opt_val   = esc_attr( $opt_label );
                    ?>
                    <label class="aura-checkbox-label">
                        <input type="checkbox"
                               name="<?php echo esc_attr( $field_name ); ?>[]"
                               value="<?php echo $opt_val; ?>"
                               class="aura-input"
                               <?php if ( $required ) echo 'data-required-group="' . esc_attr( $field_name ) . '"'; ?>>
                        <?php echo esc_html( $opt_label ); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>

        <?php // SELECT (desplegable simple o múltiple) ?>
        <?php elseif ( $ftype === 'select' ) : ?>
        <?php
            // multiple_select viene de su columna propia; las opciones del array $options ya decodificado
            $is_multiple   = (bool) $field->multiple_select;
            $multiple_attr = $is_multiple ? ' multiple' : '';
            $select_name   = $is_multiple ? $field_name . '[]' : $field_name;
        ?>
        <div class="aura-field aura-field-select">
            <label class="aura-field-label" for="<?php echo esc_attr( $field_name ); ?>">
                <?php echo $label; ?><?php echo $req_star; ?>
            </label>
            <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
            <select name="<?php echo esc_attr( $select_name ); ?>"
                    id="<?php echo esc_attr( $field_name ); ?>"
                    class="aura-input"
                    <?php echo $multiple_attr; ?>
                    <?php echo $req_attr; ?>>
                <?php if ( ! $is_multiple ) : ?>
                <option value=""><?php esc_html_e( '— Selecciona una opción —', 'aura-suite' ); ?></option>
                <?php endif; ?>
                <?php foreach ( (array) $options as $opt ) :
                    $opt_label = is_array( $opt ) ? ( $opt['label'] ?? $opt[0] ?? '' ) : $opt;
                    $opt_val   = esc_attr( $opt_label );
                ?>
                <option value="<?php echo $opt_val; ?>"><?php echo esc_html( $opt_label ); ?></option>
                <?php endforeach; ?>
                <?php if ( ! empty( $field->has_other ) ) : ?>
                <option value="__other__"><?php esc_html_e( 'Otro...', 'aura-suite' ); ?></option>
                <?php endif; ?>
            </select>
            <?php if ( ! empty( $field->has_other ) ) : ?>
            <input type="text"
                   name="<?php echo esc_attr( $field_name . '_other_text' ); ?>"
                   class="aura-other-text-input aura-input"
                   placeholder="<?php esc_attr_e( 'Especifique...', 'aura-suite' ); ?>"
                   style="display:none;">
            <?php endif; ?>
        </div>

        <?php // FILE (subida de archivo) ?>
        <?php elseif ( $ftype === 'file' ) : ?>
        <?php
            $allowed_ext = $field->allowed_extensions ?: 'jpg,jpeg,png,pdf';
            $max_kb      = (int) ( $field->max_file_size_kb ?: 5120 );
            $accept_str  = implode( ',', array_map( fn( $e ) => '.' . trim( $e ), explode( ',', $allowed_ext ) ) );
        ?>
        <div class="aura-field aura-field-file">
            <label class="aura-field-label" for="<?php echo esc_attr( $field_name ); ?>">
                <?php echo $label; ?><?php echo $req_star; ?>
            </label>
            <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
            <input type="file"
                   name="<?php echo esc_attr( $field_name ); ?>"
                   id="<?php echo esc_attr( $field_name ); ?>"
                   class="aura-input aura-file-input"
                   accept="<?php echo esc_attr( $accept_str ); ?>"
                   data-max-kb="<?php echo esc_attr( $max_kb ); ?>"
                   data-allowed-ext="<?php echo esc_attr( $allowed_ext ); ?>"
                   <?php echo $req_attr; ?>>
            <p class="aura-file-hint">
                <?php
                printf(
                    /* translators: 1: allowed extensions, 2: max KB */
                    esc_html__( 'Formatos: %1$s · Máximo: %2$s KB', 'aura-suite' ),
                    esc_html( strtoupper( $allowed_ext ) ),
                    esc_html( number_format_i18n( $max_kb ) )
                );
                ?>
            </p>
        </div>

        <?php // DATE (fecha genérica) ?>
        <?php elseif ( $ftype === 'date' ) : ?>
        <div class="aura-field aura-field-date">
            <label class="aura-field-label" for="<?php echo esc_attr( $field_name ); ?>">
                <?php echo $label; ?><?php echo $req_star; ?>
            </label>
            <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
            <input type="date"
                   name="<?php echo esc_attr( $field_name ); ?>"
                   id="<?php echo esc_attr( $field_name ); ?>"
                   class="aura-input"
                   value="<?php echo $default_val; ?>"
                   <?php if ( $field->min_value ) echo 'min="' . esc_attr( $field->min_value ) . '"'; ?>
                   <?php if ( $field->max_value ) echo 'max="' . esc_attr( $field->max_value ) . '"'; ?>
                   <?php echo $req_attr; ?>>
        </div>

        <?php // BIRTHDATE (fecha de nacimiento con cálculo de edad) ?>
        <?php elseif ( $ftype === 'birthdate' ) : ?>
        <div class="aura-field aura-field-birthdate">
            <label class="aura-field-label" for="<?php echo esc_attr( $field_name ); ?>">
                <?php echo $label; ?><?php echo $req_star; ?>
            </label>
            <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
            <input type="date"
                   name="<?php echo esc_attr( $field_name ); ?>"
                   id="<?php echo esc_attr( $field_name ); ?>"
                   class="aura-input aura-birthdate-input"
                   max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"
                   <?php echo $req_attr; ?>>
            <p class="aura-birthdate-age" id="<?php echo esc_attr( $field_name . '_age_display' ); ?>"></p>
        </div>

        <?php // NUMBER (numérico con min/max) ?>
        <?php elseif ( $ftype === 'number' ) : ?>
        <div class="aura-field aura-field-number">
            <label class="aura-field-label" for="<?php echo esc_attr( $field_name ); ?>">
                <?php echo $label; ?><?php echo $req_star; ?>
            </label>
            <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
            <input type="number"
                   name="<?php echo esc_attr( $field_name ); ?>"
                   id="<?php echo esc_attr( $field_name ); ?>"
                   class="aura-input"
                   placeholder="<?php echo $placeholder; ?>"
                   value="<?php echo $default_val; ?>"
                   <?php if ( $field->min_value !== null ) echo 'min="' . esc_attr( $field->min_value ) . '"'; ?>
                   <?php if ( $field->max_value !== null ) echo 'max="' . esc_attr( $field->max_value ) . '"'; ?>
                   <?php echo $req_attr; ?>>
        </div>

        <?php // TEXTAREA (párrafo largo) ?>
        <?php elseif ( $ftype === 'textarea' ) : ?>
        <div class="aura-field aura-field-textarea">
            <label class="aura-field-label" for="<?php echo esc_attr( $field_name ); ?>">
                <?php echo $label; ?><?php echo $req_star; ?>
            </label>
            <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
            <textarea name="<?php echo esc_attr( $field_name ); ?>"
                      id="<?php echo esc_attr( $field_name ); ?>"
                      class="aura-input"
                      placeholder="<?php echo $placeholder; ?>"
                      rows="5"
                      <?php echo $req_attr; ?>><?php echo esc_textarea( $field->default_value ?? '' ); ?></textarea>
        </div>

        <?php // TEXT, EMAIL, TEL, TIME (campos de una línea) — default ?>
        <?php else : ?>
        <?php
            $input_type = ( $ftype === 'email' ) ? 'email'
                        : ( ( $ftype === 'tel' )  ? 'tel'
                        : ( ( $ftype === 'time' ) ? 'time'
                        : 'text' ) );
        ?>
        <div class="aura-field aura-field-<?php echo esc_attr( $ftype ); ?>">
            <label class="aura-field-label" for="<?php echo esc_attr( $field_name ); ?>">
                <?php echo $label; ?><?php echo $req_star; ?>
            </label>
            <?php if ( $desc ) : ?><p class="aura-field-desc"><?php echo $desc; ?></p><?php endif; ?>
            <input type="<?php echo esc_attr( $input_type ); ?>"
                   name="<?php echo esc_attr( $field_name ); ?>"
                   id="<?php echo esc_attr( $field_name ); ?>"
                   class="aura-input"
                   placeholder="<?php echo $placeholder; ?>"
                   value="<?php echo $default_val; ?>"
                   <?php echo $req_attr; ?>>
        </div>
        <?php endif; ?>

        <?php endforeach; ?>

        <?php // ── Botón de envío ───────────────────────────────── ?>
        <div class="aura-form-submit-wrap">
            <button type="submit" class="aura-form-submit-btn">
                <?php echo esc_html( $form->submit_button_label ?: __( 'Enviar', 'aura-suite' ) ); ?>
            </button>
        </div>

    </form>
</div>

