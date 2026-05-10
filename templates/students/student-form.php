<?php
/**
 * Template: Formulario de Registro de Estudiante — Fase 3
 *
 * Página independiente para registrar un nuevo estudiante.
 * El guardado se realiza vía AJAX y redirige al listado.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$can_create  = current_user_can( 'aura_students_create' )   || current_user_can( 'manage_options' );
$can_edit    = current_user_can( 'aura_students_edit' )     || current_user_can( 'manage_options' );
$can_notes   = current_user_can( 'aura_students_view_all' ) || current_user_can( 'manage_options' );
$list_url    = admin_url( 'admin.php?page=aura-students-list' );
?>

<div class="wrap aura-student-form-page">

    <!-- ─── CABECERA ─────────────────────────────────────────── -->
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-plus-alt"
              style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#8b5cf6;"></span>
        <?php _e( 'Nuevo Estudiante', 'aura-suite' ); ?>
    </h1>

    <a href="<?php echo esc_url( $list_url ); ?>" class="page-title-action">
        ← <?php _e( 'Volver al listado', 'aura-suite' ); ?>
    </a>

    <hr class="wp-header-end">

    <!-- ─── AVISO (se muestra tras guardar/error) ────────────── -->
    <div id="form-notice" style="display:none;margin:12px 0;"></div>

    <!-- ─── FORMULARIO PRINCIPAL ─────────────────────────────── -->
    <div style="max-width:900px;">
        <form id="form-new-student" novalidate>
            <input type="hidden" id="student-id" name="id" value="0">

            <!-- ── Tabs de secciones ──────────────────────────── -->
            <div class="aura-sfp-tabs" style="border-bottom:2px solid #e2e0ef;margin-bottom:24px;display:flex;gap:4px;">
                <button type="button" class="aura-sfp-tab active" data-tab="personal">
                    👤 <?php _e( 'Datos personales', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-sfp-tab" data-tab="postul">
                    📋 <?php _e( 'Postulación', 'aura-suite' ); ?>
                </button>
                <button type="button" class="aura-sfp-tab" data-tab="areas">
                    🎯 <?php _e( 'Áreas de interés', 'aura-suite' ); ?>
                </button>
                <?php if ( $can_notes ) : ?>
                <button type="button" class="aura-sfp-tab" data-tab="notes">
                    📝 <?php _e( 'Notas internas', 'aura-suite' ); ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- ════════════════════════════════════════════════
                 SECCIÓN 1: DATOS PERSONALES
            ════════════════════════════════════════════════ -->
            <div class="aura-sfp-panel active" id="sfp-tab-personal">
                <div class="aura-sfp-card">

                    <div class="aura-sfp-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="aura-sfp-field">
                            <label for="sfp-first-name" class="aura-sfp-label">
                                <?php _e( 'Nombre(s)', 'aura-suite' ); ?> <span class="aura-sfp-req">*</span>
                            </label>
                            <input type="text" id="sfp-first-name" name="first_name"
                                   class="regular-text aura-sfp-input" maxlength="100" required>
                        </div>
                        <div class="aura-sfp-field">
                            <label for="sfp-last-name" class="aura-sfp-label">
                                <?php _e( 'Apellido(s)', 'aura-suite' ); ?> <span class="aura-sfp-req">*</span>
                            </label>
                            <input type="text" id="sfp-last-name" name="last_name"
                                   class="regular-text aura-sfp-input" maxlength="100" required>
                        </div>
                    </div>

                    <div class="aura-sfp-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:14px;">
                        <div class="aura-sfp-field">
                            <label for="sfp-email" class="aura-sfp-label">
                                <?php _e( 'Correo electrónico', 'aura-suite' ); ?> <span class="aura-sfp-req">*</span>
                            </label>
                            <input type="email" id="sfp-email" name="email"
                                   class="regular-text aura-sfp-input" maxlength="200" required>
                        </div>
                        <div class="aura-sfp-field">
                            <label class="aura-sfp-label"><?php _e( 'Teléfono', 'aura-suite' ); ?></label>
                            <div style="display:flex;gap:6px;">
                                <input type="text" id="sfp-phone-country" name="phone_country"
                                       class="aura-sfp-input" style="width:78px;" maxlength="6" placeholder="+1">
                                <input type="text" id="sfp-phone" name="phone"
                                       class="aura-sfp-input" maxlength="30" style="flex:1;">
                            </div>
                        </div>
                    </div>

                    <div class="aura-sfp-row" style="display:grid;grid-template-columns:150px 1fr;gap:16px;margin-top:14px;">
                        <div class="aura-sfp-field">
                            <label for="sfp-id-type" class="aura-sfp-label"><?php _e( 'Tipo de ID', 'aura-suite' ); ?></label>
                            <select id="sfp-id-type" name="id_type" class="aura-sfp-select">
                                <option value="cedula"><?php _e( 'Cédula',    'aura-suite' ); ?></option>
                                <option value="passport"><?php _e( 'Pasaporte', 'aura-suite' ); ?></option>
                                <option value="ruc"><?php _e( 'RUC',       'aura-suite' ); ?></option>
                                <option value="dni"><?php _e( 'DNI',       'aura-suite' ); ?></option>
                                <option value="other"><?php _e( 'Otro',      'aura-suite' ); ?></option>
                            </select>
                        </div>
                        <div class="aura-sfp-field">
                            <label for="sfp-id-number" class="aura-sfp-label"><?php _e( 'Número de identificación', 'aura-suite' ); ?></label>
                            <input type="text" id="sfp-id-number" name="id_number"
                                   class="regular-text aura-sfp-input" maxlength="50">
                        </div>
                    </div>

                    <div class="aura-sfp-row" style="display:grid;grid-template-columns:160px 160px 1fr;gap:16px;margin-top:14px;">
                        <div class="aura-sfp-field">
                            <label for="sfp-birthdate" class="aura-sfp-label"><?php _e( 'Fecha de nacimiento', 'aura-suite' ); ?></label>
                            <input type="date" id="sfp-birthdate" name="birthdate" class="aura-sfp-input">
                        </div>
                        <div class="aura-sfp-field">
                            <label for="sfp-gender" class="aura-sfp-label"><?php _e( 'Género', 'aura-suite' ); ?></label>
                            <select id="sfp-gender" name="gender" class="aura-sfp-select">
                                <option value=""><?php _e( '— Seleccionar —', 'aura-suite' ); ?></option>
                                <option value="M"><?php _e( 'Masculino',       'aura-suite' ); ?></option>
                                <option value="F"><?php _e( 'Femenino',        'aura-suite' ); ?></option>
                                <option value="O"><?php _e( 'Otro',            'aura-suite' ); ?></option>
                                <option value="P"><?php _e( 'Prefiero no decir','aura-suite' ); ?></option>
                            </select>
                        </div>
                        <div class="aura-sfp-field">
                            <label for="sfp-photo-url" class="aura-sfp-label"><?php _e( 'URL de foto de perfil', 'aura-suite' ); ?></label>
                            <input type="url" id="sfp-photo-url" name="photo_url"
                                   class="large-text aura-sfp-input" maxlength="500">
                        </div>
                    </div>

                    <div class="aura-sfp-field" style="margin-top:14px;">
                        <label for="sfp-address" class="aura-sfp-label"><?php _e( 'Dirección', 'aura-suite' ); ?></label>
                        <input type="text" id="sfp-address" name="address"
                               class="large-text aura-sfp-input" maxlength="300">
                    </div>

                    <div class="aura-sfp-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:14px;">
                        <div class="aura-sfp-field">
                            <label for="sfp-city" class="aura-sfp-label"><?php _e( 'Ciudad', 'aura-suite' ); ?></label>
                            <input type="text" id="sfp-city" name="city"
                                   class="regular-text aura-sfp-input" maxlength="100">
                        </div>
                        <div class="aura-sfp-field">
                            <label for="sfp-country" class="aura-sfp-label"><?php _e( 'País', 'aura-suite' ); ?></label>
                            <input type="text" id="sfp-country" name="country"
                                   class="regular-text aura-sfp-input" maxlength="100">
                        </div>
                    </div>

                </div><!-- .aura-sfp-card -->
            </div><!-- #sfp-tab-personal -->

            <!-- ════════════════════════════════════════════════
                 SECCIÓN 2: POSTULACIÓN
            ════════════════════════════════════════════════ -->
            <div class="aura-sfp-panel" id="sfp-tab-postul" style="display:none;">
                <div class="aura-sfp-card">

                    <div class="aura-sfp-field">
                        <label for="sfp-motivation" class="aura-sfp-label"><?php _e( 'Motivación para postular', 'aura-suite' ); ?></label>
                        <textarea id="sfp-motivation" name="motivation" rows="5"
                                  class="large-text aura-sfp-input" maxlength="3000"></textarea>
                    </div>

                    <div class="aura-sfp-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:14px;">
                        <div class="aura-sfp-field">
                            <label for="sfp-supported-by" class="aura-sfp-label"><?php _e( 'Apadrinado / Referido por', 'aura-suite' ); ?></label>
                            <input type="text" id="sfp-supported-by" name="supported_by"
                                   class="regular-text aura-sfp-input" maxlength="200">
                        </div>
                        <div class="aura-sfp-field">
                            <label for="sfp-talent" class="aura-sfp-label"><?php _e( 'Talentos destacados', 'aura-suite' ); ?></label>
                            <input type="text" id="sfp-talent" name="talent"
                                   class="regular-text aura-sfp-input" maxlength="500">
                        </div>
                    </div>

                    <div class="aura-sfp-field" style="margin-top:14px;">
                        <label for="sfp-experience" class="aura-sfp-label"><?php _e( 'Experiencia previa', 'aura-suite' ); ?></label>
                        <textarea id="sfp-experience" name="experience" rows="3"
                                  class="large-text aura-sfp-input" maxlength="3000"></textarea>
                    </div>

                    <div class="aura-sfp-field" style="margin-top:14px;">
                        <label for="sfp-extra-info" class="aura-sfp-label"><?php _e( 'Información adicional', 'aura-suite' ); ?></label>
                        <textarea id="sfp-extra-info" name="extra_info" rows="3"
                                  class="large-text aura-sfp-input" maxlength="3000"></textarea>
                    </div>

                </div>
            </div><!-- #sfp-tab-postul -->

            <!-- ════════════════════════════════════════════════
                 SECCIÓN 3: ÁREAS DE INTERÉS
            ════════════════════════════════════════════════ -->
            <div class="aura-sfp-panel" id="sfp-tab-areas" style="display:none;">
                <div class="aura-sfp-card">
                    <p style="color:#6b7280;font-size:.9rem;margin-top:0;">
                        <?php _e( 'Selecciona los programas/áreas de interés del estudiante. Se guardan como sus preferencias.', 'aura-suite' ); ?>
                    </p>
                    <div id="sfp-programs-checkboxes"
                         style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-top:12px;">
                        <p><span class="spinner is-active" style="float:none;"></span>
                        <?php _e( 'Cargando programas…', 'aura-suite' ); ?></p>
                    </div>
                </div>
            </div><!-- #sfp-tab-areas -->

            <!-- ════════════════════════════════════════════════
                 SECCIÓN 4: NOTAS INTERNAS
            ════════════════════════════════════════════════ -->
            <?php if ( $can_notes ) : ?>
            <div class="aura-sfp-panel" id="sfp-tab-notes" style="display:none;">
                <div class="aura-sfp-card">
                    <div class="aura-sfp-field">
                        <label for="sfp-notes" class="aura-sfp-label"><?php _e( 'Notas internas', 'aura-suite' ); ?></label>
                        <textarea id="sfp-notes" name="notes" rows="6"
                                  class="large-text aura-sfp-input" maxlength="5000"></textarea>
                        <small style="color:#6b7280;"><?php _e( 'Solo visible para administradores y coordinadores.', 'aura-suite' ); ?></small>
                    </div>
                </div>
            </div><!-- #sfp-tab-notes -->
            <?php endif; ?>

            <!-- ── Navegación entre secciones + botón guardar ─ -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;padding:14px 0;border-top:1px solid #e5e7eb;">
                <div>
                    <button type="button" id="sfp-btn-prev" class="button" style="display:none;">
                        ‹ <?php _e( 'Anterior', 'aura-suite' ); ?>
                    </button>
                    <button type="button" id="sfp-btn-next" class="button">
                        <?php _e( 'Siguiente', 'aura-suite' ); ?> ›
                    </button>
                </div>
                <button type="button" id="sfp-btn-save" class="button button-primary">
                    <?php _e( 'Registrar estudiante', 'aura-suite' ); ?>
                </button>
            </div>

        </form>
    </div>

</div><!-- .wrap -->


<!-- ══════════════════════════════════════════════════════════════
     ESTILOS INLINE
══════════════════════════════════════════════════════════════ -->
<style>
.aura-sfp-card { background:#fff;border:1px solid #e2e0ef;border-radius:8px;padding:20px 24px;margin-bottom:20px; }
.aura-sfp-label { display:block;font-weight:600;margin-bottom:4px;font-size:.87rem;color:#374151; }
.aura-sfp-input,.aura-sfp-select { width:100%;box-sizing:border-box; }
.aura-sfp-req { color:#dc2626; }

.aura-sfp-tab { border:none;border-bottom:3px solid transparent;background:none;cursor:pointer;padding:9px 16px;color:#6b7280;font-size:.88rem;font-weight:600;transition:color .15s,border-color .15s; }
.aura-sfp-tab:hover { color:#8b5cf6; }
.aura-sfp-tab.active { color:#8b5cf6;border-bottom-color:#8b5cf6; }

.aura-sfp-program-cb { display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer;transition:background .15s,border-color .15s; }
.aura-sfp-program-cb:hover, .aura-sfp-program-cb.selected { background:#f5f3ff;border-color:#8b5cf6; }
.aura-sfp-program-cb input[type="checkbox"] { margin:0;cursor:pointer; }

@media (max-width:640px) {
    .aura-sfp-row { grid-template-columns:1fr !important; }
    .aura-sfp-tabs { flex-wrap:wrap; }
}
</style>


<!-- ══════════════════════════════════════════════════════════════
     JS INLINE
══════════════════════════════════════════════════════════════ -->
<script>
(function($){
    'use strict';

    var nonce    = auraStudents.nonce;
    var ajaxUrl  = auraStudents.ajax_url;
    var listUrl  = <?php echo wp_json_encode( $list_url ); ?>;
    var canNotes = <?php echo $can_notes ? 'true' : 'false'; ?>;
    var programs = [];

    var tabs = ['personal', 'postul', 'areas'];
    <?php if ( $can_notes ) : ?>
    tabs.push('notes');
    <?php endif; ?>

    var currentTabIdx = 0;

    // ── INICIO ──────────────────────────────────────────────────
    $(function(){
        loadPrograms();
        updateNavButtons();
        bindEvents();
    });

    // ── CARGAR PROGRAMAS ────────────────────────────────────────
    function loadPrograms(){
        $.post(ajaxUrl, { action:'aura_students_get_programs', nonce:nonce }, function(res){
            if ( ! res.success ) {
                $('#sfp-programs-checkboxes').html('<p style="color:#6b7280;"><?php _e( "No hay programas activos.", "aura-suite" ); ?></p>');
                return;
            }
            programs = res.data.programs;
            renderPrograms([]);
        }).fail(function(){
            $('#sfp-programs-checkboxes').html('<p style="color:#9a3412;"><?php _e( "Error al cargar los programas.", "aura-suite" ); ?></p>');
        });
    }

    function renderPrograms(selectedIds){
        var $container = $('#sfp-programs-checkboxes');
        $container.empty();

        if ( ! programs.length ){
            $container.html('<p style="color:#6b7280;"><?php _e( "No hay programas de tipo área activos.", "aura-suite" ); ?></p>');
            return;
        }

        $.each(programs, function(i, p){
            var checked = selectedIds.indexOf(parseInt(p.id)) !== -1 ? 'checked' : '';
            var $lbl = $('<label class="aura-sfp-program-cb'+(checked?' selected':'')+'"></label>');
            var $cb  = $('<input type="checkbox" name="preferred_areas[]" value="'+p.id+'" '+checked+'>');
            $lbl.append($cb).append($('<span>').text(p.name));
            $container.append($lbl);
        });

        $container.on('change', 'input[type="checkbox"]', function(){
            $(this).closest('label').toggleClass('selected', this.checked);
        });
    }

    // ── NAVEGACIÓN ENTRE TABS ───────────────────────────────────
    function switchToTab(idx){
        if ( idx < 0 || idx >= tabs.length ) return;
        currentTabIdx = idx;

        $('.aura-sfp-tab').removeClass('active');
        $('.aura-sfp-tab[data-tab="'+tabs[idx]+'"]').addClass('active');
        $('.aura-sfp-panel').hide();
        $('#sfp-tab-'+tabs[idx]).show();

        updateNavButtons();
    }

    function updateNavButtons(){
        $('#sfp-btn-prev').toggle(currentTabIdx > 0);
        $('#sfp-btn-next').toggle(currentTabIdx < tabs.length - 1);
    }

    // ── GUARDAR ESTUDIANTE ──────────────────────────────────────
    function saveStudent(){
        var firstName = $.trim($('#sfp-first-name').val());
        var lastName  = $.trim($('#sfp-last-name').val());
        var email     = $.trim($('#sfp-email').val());

        if ( ! firstName ){
            switchToTab(0);
            $('#sfp-first-name').focus();
            showNotice('<?php _e( "El nombre es obligatorio.", "aura-suite" ); ?>', 'error');
            return;
        }
        if ( ! lastName ){
            switchToTab(0);
            $('#sfp-last-name').focus();
            showNotice('<?php _e( "El apellido es obligatorio.", "aura-suite" ); ?>', 'error');
            return;
        }
        if ( ! email ){
            switchToTab(0);
            $('#sfp-email').focus();
            showNotice('<?php _e( "El correo electrónico es obligatorio.", "aura-suite" ); ?>', 'error');
            return;
        }

        var selectedAreas = [];
        $('#sfp-programs-checkboxes input[type="checkbox"]:checked').each(function(){
            selectedAreas.push(parseInt($(this).val()));
        });

        var $btn = $('#sfp-btn-save');
        $btn.prop('disabled', true).text('<?php _e( "Registrando…", "aura-suite" ); ?>');

        var data = {
            action          : 'aura_students_save',
            nonce           : nonce,
            id              : 0,
            first_name      : firstName,
            last_name       : lastName,
            email           : email,
            phone           : $('#sfp-phone').val(),
            phone_country   : $('#sfp-phone-country').val(),
            id_type         : $('#sfp-id-type').val(),
            id_number       : $('#sfp-id-number').val(),
            birthdate       : $('#sfp-birthdate').val(),
            gender          : $('#sfp-gender').val(),
            photo_url       : $('#sfp-photo-url').val(),
            address         : $('#sfp-address').val(),
            city            : $('#sfp-city').val(),
            country         : $('#sfp-country').val(),
            motivation      : $('#sfp-motivation').val(),
            supported_by    : $('#sfp-supported-by').val(),
            talent          : $('#sfp-talent').val(),
            experience      : $('#sfp-experience').val(),
            extra_info      : $('#sfp-extra-info').val(),
            preferred_areas : JSON.stringify(selectedAreas)
        };

        if ( canNotes ){
            data.notes = $('#sfp-notes').val();
        }

        $.post(ajaxUrl, data, function(res){
            $btn.prop('disabled', false).text('<?php _e( "Registrar estudiante", "aura-suite" ); ?>');
            if ( res.success ){
                showNotice(res.data.message, 'success');
                // Redirige al listado tras 1.2s
                setTimeout(function(){
                    window.location.href = listUrl;
                }, 1200);
            } else {
                showNotice(res.data.message, 'error');
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('<?php _e( "Registrar estudiante", "aura-suite" ); ?>');
            showNotice(auraStudents.i18n.error, 'error');
        });
    }

    // ── AVISO INLINE ─────────────────────────────────────────────
    function showNotice(msg, type){
        var cls = type === 'success' ? 'notice-success' : 'notice-error';
        var $n = $('#form-notice');
        $n.removeClass('notice-success notice-error')
          .addClass('notice ' + cls)
          .html('<p>' + $('<div/>').text(msg).html() + '</p>')
          .show();
        $('html, body').animate({ scrollTop: $n.offset().top - 60 }, 300);
    }

    // ── EVENTOS ─────────────────────────────────────────────────
    function bindEvents(){
        // Tabs clic
        $(document).on('click', '.aura-sfp-tab', function(){
            var tabId = $(this).data('tab');
            switchToTab(tabs.indexOf(tabId));
        });

        // Navegación anterior/siguiente
        $('#sfp-btn-prev').on('click', function(){ switchToTab(currentTabIdx - 1); });
        $('#sfp-btn-next').on('click', function(){ switchToTab(currentTabIdx + 1); });

        // Guardar
        $('#sfp-btn-save').on('click', saveStudent);
    }

})(jQuery);
</script>
