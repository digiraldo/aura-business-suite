<?php
/**
 * Template: Listado de Cursos y Programas — Fase 2
 *
 * Incluye el modal de creación/edición embebido.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$can_manage = current_user_can( 'aura_students_courses_manage' ) || current_user_can( 'manage_options' );
?>

<div class="wrap aura-students-courses">

    <!-- ─── CABECERA ─────────────────────────────────────────── -->
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-welcome-learn-more"
              style="font-size:26px;height:26px;vertical-align:middle;margin-right:6px;color:#8b5cf6;"></span>
        <?php _e( 'Cursos y Programas', 'aura-suite' ); ?>
    </h1>

    <?php if ( $can_manage ) : ?>
    <button type="button" id="btn-nuevo-curso" class="page-title-action">
        + <?php _e( 'Nuevo Curso', 'aura-suite' ); ?>
    </button>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- ─── BARRA DE FILTROS ──────────────────────────────────── -->
    <div class="aura-stu-filter-bar" style="margin:16px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">

        <input type="text"
               id="filter-search"
               placeholder="<?php esc_attr_e( 'Buscar por nombre…', 'aura-suite' ); ?>"
               class="regular-text"
               style="max-width:220px;">

        <select id="filter-status">
            <option value=""><?php _e( 'Todos los estados', 'aura-suite' ); ?></option>
            <option value="active"><?php _e( 'Activo', 'aura-suite' ); ?></option>
            <option value="inactive"><?php _e( 'Inactivo', 'aura-suite' ); ?></option>
            <option value="archived"><?php _e( 'Archivado', 'aura-suite' ); ?></option>
        </select>

        <select id="filter-area">
            <option value=""><?php _e( 'Todas las áreas', 'aura-suite' ); ?></option>
            <!-- Opciones cargadas vía JS -->
        </select>

        <button type="button" id="btn-apply-filters" class="button">
            <?php _e( 'Filtrar', 'aura-suite' ); ?>
        </button>

        <button type="button" id="btn-clear-filters" class="button button-link">
            <?php _e( 'Limpiar', 'aura-suite' ); ?>
        </button>
    </div>

    <!-- ─── TABLA DE CURSOS ──────────────────────────────────── -->
    <div id="courses-table-wrap">
        <table class="aura-stu-table wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:28%"><?php _e( 'Nombre', 'aura-suite' ); ?></th>
                    <th style="width:13%"><?php _e( 'Área', 'aura-suite' ); ?></th>
                    <th style="width:15%"><?php _e( 'Instructor', 'aura-suite' ); ?></th>
                    <th style="width:9%" class="num"><?php _e( 'Semanas', 'aura-suite' ); ?></th>
                    <th style="width:11%" class="num"><?php _e( 'Costo base', 'aura-suite' ); ?></th>
                    <th style="width:8%"><?php _e( 'Estado', 'aura-suite' ); ?></th>
                    <th style="width:7%" class="num"><?php _e( 'Inscritos', 'aura-suite' ); ?></th>
                    <th style="width:9%"><?php _e( 'Acciones', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody id="courses-tbody">
                <tr id="courses-loading">
                    <td colspan="8" style="text-align:center;padding:20px;">
                        <span class="spinner is-active" style="float:none;margin-right:8px;"></span>
                        <?php _e( 'Cargando cursos…', 'aura-suite' ); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ─── PAGINACIÓN ───────────────────────────────────────── -->
    <div id="courses-pagination" class="tablenav bottom" style="display:none;">
        <div class="tablenav-pages">
            <span id="pagination-count" class="displaying-num"></span>
            <span class="pagination-links">
                <button id="page-prev" class="button" disabled>‹</button>
                <span id="page-info" style="padding:0 8px;"></span>
                <button id="page-next" class="button" disabled>›</button>
            </span>
        </div>
    </div>

</div><!-- .wrap -->


<!-- ══════════════════════════════════════════════════════════════
     MODAL: CREAR / EDITAR CURSO
══════════════════════════════════════════════════════════════ -->
<div id="modal-course" class="aura-stu-modal" style="display:none;" role="dialog" aria-modal="true"
     aria-labelledby="modal-course-title">
    <div class="aura-stu-modal-backdrop"></div>
    <div class="aura-stu-modal-container" style="max-width:780px;">

        <div class="aura-stu-modal-header">
            <h2 id="modal-course-title"><?php _e( 'Nuevo Curso', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-stu-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>

        <div class="aura-stu-modal-body">
            <form id="form-course" novalidate>
                <input type="hidden" id="course-id" name="id" value="0">

                <!-- Fila 1: nombre + estado -->
                <div class="aura-stu-form-row" style="display:grid;grid-template-columns:1fr 200px;gap:16px;">
                    <div class="aura-stu-field">
                        <label for="course-name" class="aura-stu-label">
                            <?php _e( 'Nombre del curso', 'aura-suite' ); ?> <span class="required">*</span>
                        </label>
                        <input type="text" id="course-name" name="name" class="regular-text aura-stu-input"
                               maxlength="300" required>
                    </div>
                    <div class="aura-stu-field">
                        <label for="course-status" class="aura-stu-label"><?php _e( 'Estado', 'aura-suite' ); ?></label>
                        <select id="course-status" name="status" class="aura-stu-select">
                            <option value="active"><?php _e( 'Activo', 'aura-suite' ); ?></option>
                            <option value="inactive"><?php _e( 'Inactivo', 'aura-suite' ); ?></option>
                            <option value="archived"><?php _e( 'Archivado', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Fila 2: descripción -->
                <div class="aura-stu-field" style="margin-top:12px;">
                    <label for="course-desc" class="aura-stu-label"><?php _e( 'Descripción', 'aura-suite' ); ?></label>
                    <textarea id="course-desc" name="description" rows="3" class="large-text aura-stu-input"
                              maxlength="5000"></textarea>
                </div>

                <!-- Fila 3: área + instructor -->
                <div class="aura-stu-form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px;">
                    <div class="aura-stu-field">
                        <label for="course-area" class="aura-stu-label"><?php _e( 'Área / Programa', 'aura-suite' ); ?></label>
                        <select id="course-area" name="area_id" class="aura-stu-select">
                            <option value=""><?php _e( '— Sin área —', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                    <div class="aura-stu-field">
                        <label for="course-instructor" class="aura-stu-label"><?php _e( 'Instructor', 'aura-suite' ); ?></label>
                        <select id="course-instructor" name="instructor_id" class="aura-stu-select">
                            <option value=""><?php _e( '— Sin asignar —', 'aura-suite' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Fila 4: duración + máx. estudiantes + fechas -->
                <div class="aura-stu-form-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:12px;">
                    <div class="aura-stu-field">
                        <label for="course-weeks" class="aura-stu-label"><?php _e( 'Semanas', 'aura-suite' ); ?></label>
                        <input type="number" id="course-weeks" name="duration_weeks"
                               class="aura-stu-input" min="0" max="999" step="1" placeholder="0">
                    </div>
                    <div class="aura-stu-field">
                        <label for="course-max" class="aura-stu-label">
                            <?php _e( 'Máx. estudiantes', 'aura-suite' ); ?>
                        </label>
                        <input type="number" id="course-max" name="max_students"
                               class="aura-stu-input" min="0" step="1" placeholder="0">
                        <small><?php _e( '0 = sin límite', 'aura-suite' ); ?></small>
                    </div>
                    <div class="aura-stu-field">
                        <label for="course-start" class="aura-stu-label"><?php _e( 'Fecha inicio', 'aura-suite' ); ?></label>
                        <input type="date" id="course-start" name="start_date" class="aura-stu-input">
                    </div>
                    <div class="aura-stu-field">
                        <label for="course-end" class="aura-stu-label"><?php _e( 'Fecha fin', 'aura-suite' ); ?></label>
                        <input type="date" id="course-end" name="end_date" class="aura-stu-input">
                    </div>
                </div>

                <!-- Fila 5: costo + moneda + categoría financiera -->
                <div class="aura-stu-form-row" style="display:grid;grid-template-columns:160px 120px 1fr;gap:16px;margin-top:12px;">
                    <div class="aura-stu-field">
                        <label for="course-cost" class="aura-stu-label">
                            <?php _e( 'Costo base', 'aura-suite' ); ?>
                        </label>
                        <input type="number" id="course-cost" name="base_cost"
                               class="aura-stu-input" min="0" step="0.01" placeholder="0.00" id="course-cost">
                    </div>
                    <div class="aura-stu-field">
                        <label for="course-currency" class="aura-stu-label"><?php _e( 'Moneda', 'aura-suite' ); ?></label>
                        <select id="course-currency" name="currency" class="aura-stu-select">
                            <option value="USD">USD $</option>
                            <option value="EUR">EUR €</option>
                            <option value="COP">COP $</option>
                            <option value="MXN">MXN $</option>
                            <option value="PEN">PEN S/</option>
                            <option value="ARS">ARS $</option>
                            <option value="CLP">CLP $</option>
                            <option value="BRL">BRL R$</option>
                            <option value="GTQ">GTQ Q</option>
                            <option value="HNL">HNL L</option>
                            <option value="NIO">NIO C$</option>
                            <option value="DOP">DOP $</option>
                            <option value="CRC">CRC ₡</option>
                            <option value="BOB">BOB Bs</option>
                        </select>
                    </div>
                    <div class="aura-stu-field">
                        <label for="course-finance-cat" class="aura-stu-label">
                            <?php _e( 'Categoría financiera', 'aura-suite' ); ?>
                        </label>
                        <select id="course-finance-cat" name="finance_cat_id" class="aura-stu-select">
                            <option value=""><?php _e( '— Sin vincular —', 'aura-suite' ); ?></option>
                        </select>
                        <small><?php _e( 'Se usa al registrar pagos', 'aura-suite' ); ?></small>
                    </div>
                </div>

                <!-- Calculadora visual de beca -->
                <div class="aura-stu-scholarship-calc" style="margin-top:16px;padding:14px 16px;background:#f6f3ff;border:1px solid #ddd8f5;border-radius:6px;">
                    <strong style="color:#8b5cf6;">🧮 <?php _e( 'Calculadora de beca', 'aura-suite' ); ?></strong>
                    <div style="display:flex;gap:16px;align-items:center;margin-top:8px;flex-wrap:wrap;">
                        <span><?php _e( 'Costo base:', 'aura-suite' ); ?>
                            <strong id="calc-base">$0.00</strong>
                        </span>
                        <span style="display:flex;align-items:center;gap:6px;">
                            <?php _e( 'Beca:', 'aura-suite' ); ?>
                            <input type="number" id="calc-pct" min="0" max="100" value="0" step="5"
                                   style="width:60px;text-align:center;" class="aura-stu-input">%
                        </span>
                        <span>→ <?php _e( 'Costo neto:', 'aura-suite' ); ?>
                            <strong id="calc-net" style="color:#059669;">$0.00</strong>
                        </span>
                        <div style="display:flex;gap:6px;">
                            <?php foreach ( [ 0, 25, 50, 75, 100 ] as $pct ) : ?>
                            <button type="button" class="button button-small calc-pct-btn"
                                    data-pct="<?php echo $pct; ?>">
                                <?php echo $pct; ?>%
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </form>
        </div><!-- .modal-body -->

        <div class="aura-stu-modal-footer">
            <button type="button" class="button aura-stu-modal-close"><?php _e( 'Cancelar', 'aura-suite' ); ?></button>
            <button type="button" id="btn-save-course" class="button button-primary">
                <?php _e( 'Guardar curso', 'aura-suite' ); ?>
            </button>
        </div>

    </div><!-- .modal-container -->
</div><!-- #modal-course -->


<!-- ══════════════════════════════════════════════════════════════
     MODAL: VER DETALLE DEL CURSO
══════════════════════════════════════════════════════════════ -->
<div id="modal-course-detail" class="aura-stu-modal" style="display:none;" role="dialog" aria-modal="true"
     aria-labelledby="modal-detail-title">
    <div class="aura-stu-modal-backdrop"></div>
    <div class="aura-stu-modal-container" style="max-width:680px;">

        <div class="aura-stu-modal-header">
            <h2 id="modal-detail-title"><?php _e( 'Detalle del Curso', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-stu-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>

        <div class="aura-stu-modal-body" id="course-detail-body">
            <!-- Contenido cargado dinámicamente via JS -->
        </div>

        <div class="aura-stu-modal-footer">
            <button type="button" class="button aura-stu-modal-close"><?php _e( 'Cerrar', 'aura-suite' ); ?></button>
            <?php if ( $can_manage ) : ?>
            <button type="button" id="btn-edit-from-detail" class="button button-primary">
                <?php _e( 'Editar', 'aura-suite' ); ?>
            </button>
            <?php endif; ?>
        </div>

    </div>
</div><!-- #modal-course-detail -->


<!-- ══════════════════════════════════════════════════════════════
     ESTILOS INLINE PARA LOS MODALES Y TABLA
══════════════════════════════════════════════════════════════ -->
<style>
/* Modal overlay */
.aura-stu-modal { position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center; }
.aura-stu-modal-backdrop { position:absolute;inset:0;background:rgba(0,0,0,.55); }
.aura-stu-modal-container { position:relative;background:#fff;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.25);width:90%;max-height:92vh;display:flex;flex-direction:column;overflow:hidden; }
.aura-stu-modal-header { display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e2e0ef; }
.aura-stu-modal-header h2 { margin:0;font-size:1.2rem;color:#1e1b4b; }
.aura-stu-modal-close { background:none;border:none;cursor:pointer;font-size:18px;color:#6b7280;padding:0 4px; }
.aura-stu-modal-close:hover { color:#111; }
.aura-stu-modal-body { padding:20px;overflow-y:auto;flex:1; }
.aura-stu-modal-footer { padding:14px 20px;border-top:1px solid #e2e0ef;display:flex;justify-content:flex-end;gap:10px; }

/* Form helpers */
.aura-stu-label { display:block;font-weight:600;margin-bottom:4px;font-size:.85rem;color:#374151; }
.aura-stu-input,.aura-stu-select { width:100%;box-sizing:border-box; }
.aura-stu-field small { display:block;color:#6b7280;font-size:.78rem;margin-top:2px; }
.required { color:#dc2626; }

/* Status badges in table */
.stu-badge { display:inline-block;padding:2px 8px;border-radius:99px;font-size:.75rem;font-weight:600; }
.stu-badge-active   { background:#d1fae5;color:#065f46; }
.stu-badge-inactive { background:#fef3c7;color:#92400e; }
.stu-badge-archived { background:#f3f4f6;color:#6b7280; }

/* Detail view inside modal */
.aura-stu-detail-grid { display:grid;grid-template-columns:1fr 1fr;gap:12px 24px; }
.aura-stu-detail-item label { display:block;font-size:.78rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em; }
.aura-stu-detail-item span { display:block;color:#111827;margin-top:2px; }
.aura-stu-detail-full { grid-column:1/-1; }

@media (max-width:600px) {
    .aura-stu-form-row { grid-template-columns:1fr !important; }
    .aura-stu-detail-grid { grid-template-columns:1fr; }
}
</style>


<!-- ══════════════════════════════════════════════════════════════
     JS INLINE DEL TEMPLATE
══════════════════════════════════════════════════════════════ -->
<script>
(function($){
    'use strict';

    var nonce    = auraStudents.nonce;
    var ajaxUrl  = auraStudents.ajax_url;
    var currentPage   = 1;
    var currentCourse = null; // curso actualmente en modal-detalle
    var canManage     = <?php echo $can_manage ? 'true' : 'false'; ?>;

    // ── INICIALIZACIÓN ──────────────────────────────────────────
    $(function(){
        loadAreas();
        loadCourses(1);
        bindEvents();
    });

    // ── CARGAR ÁREAS EN SELECTS ─────────────────────────────────
    function loadAreas(){
        $.post(ajaxUrl, { action:'aura_students_get_areas', nonce:nonce }, function(res){
            if ( ! res.success ) return;
            var $filterArea = $('#filter-area');
            var $formArea   = $('#course-area');
            $.each(res.data.areas, function(i, a){
                var opt = '<option value="'+a.id+'">'+$('<div/>').text(a.name).html()+'</option>';
                $filterArea.append(opt);
                $formArea.append(opt);
            });
        });
    }

    // ── CARGAR INSTRUCTORES EN SELECT ───────────────────────────
    function loadInstructors(){
        $.post(ajaxUrl, { action:'aura_students_get_instructors', nonce:nonce }, function(res){
            if ( ! res.success ) return;
            var $sel = $('#course-instructor');
            $sel.find('option:not(:first)').remove();
            $.each(res.data.instructors, function(i, u){
                $sel.append('<option value="'+u.id+'">'+$('<div/>').text(u.name).html()+'</option>');
            });
        });
    }

    // ── CARGAR CATEGORÍAS FINANCIERAS ───────────────────────────
    function loadFinanceCats(){
        $.post(ajaxUrl, { action:'aura_students_get_finance_cats', nonce:nonce }, function(res){
            if ( ! res.success ) return;
            var $sel = $('#course-finance-cat');
            $sel.find('option:not(:first)').remove();
            $.each(res.data.categories, function(i, c){
                $sel.append('<option value="'+c.id+'">'+$('<div/>').text(c.name).html()+'</option>');
            });
        });
    }

    // ── LISTAR CURSOS ───────────────────────────────────────────
    function loadCourses(page){
        currentPage = page;
        $('#courses-tbody').html(
            '<tr><td colspan="8" style="text-align:center;padding:20px;">' +
            '<span class="spinner is-active" style="float:none;margin-right:8px;"></span>' +
            '<?php _e( "Cargando…", "aura-suite" ); ?></td></tr>'
        );
        $.post(ajaxUrl, {
            action   : 'aura_students_list_courses',
            nonce    : nonce,
            page     : page,
            status   : $('#filter-status').val(),
            area_id  : $('#filter-area').val(),
            search   : $('#filter-search').val()
        }, function(res){
            if ( ! res.success ){
                AuraStudents.notify(res.data.message, 'error');
                return;
            }
            renderTable(res.data);
        }).fail(function(){
            AuraStudents.notify(auraStudents.i18n.error, 'error');
        });
    }

    function renderTable(data){
        var $tbody = $('#courses-tbody');
        $tbody.empty();

        if ( ! data.courses.length ){
            $tbody.html('<tr><td colspan="8" style="text-align:center;padding:30px;color:#6b7280;"><?php _e( "No se encontraron cursos.", "aura-suite" ); ?></td></tr>');
            $('#courses-pagination').hide();
            return;
        }

        var statusLabels = {
            active  : '<?php _e( "Activo",    "aura-suite" ); ?>',
            inactive: '<?php _e( "Inactivo",  "aura-suite" ); ?>',
            archived: '<?php _e( "Archivado", "aura-suite" ); ?>'
        };

        $.each(data.courses, function(i, c){
            var cost = c.currency + ' ' + parseFloat(c.base_cost).toFixed(2);
            var duration = c.duration_weeks ? c.duration_weeks + ' sem.' : '—';
            var badgeClass = 'stu-badge stu-badge-' + c.status;
            var badge = '<span class="'+badgeClass+'">'+statusLabels[c.status]+'</span>';
            var actions = '';

            if ( canManage ){
                actions +=
                    '<button class="button button-small btn-view-course" data-id="'+c.id+'" style="margin-right:4px;">' +
                    '👁 <?php _e( "Ver", "aura-suite" ); ?></button>';
                if ( c.status !== 'archived' ){
                    actions +=
                        '<button class="button button-small btn-edit-course" data-id="'+c.id+'" style="margin-right:4px;">' +
                        '✏️</button>' +
                        '<button class="button button-small btn-archive-course" data-id="'+c.id+'" style="color:#b91c1c;">' +
                        '🗄️</button>';
                }
            } else {
                actions = '<button class="button button-small btn-view-course" data-id="'+c.id+'">👁</button>';
            }

            $tbody.append('<tr data-id="'+c.id+'">' +
                '<td><strong>'+esc(c.name)+'</strong></td>' +
                '<td>'+esc(c.area_name)+'</td>' +
                '<td>'+esc(c.instructor_name)+'</td>' +
                '<td class="num">'+esc(duration)+'</td>' +
                '<td class="num">'+esc(cost)+'</td>' +
                '<td>'+badge+'</td>' +
                '<td class="num">'+c.enrolled_count+'</td>' +
                '<td>'+actions+'</td>' +
            '</tr>');
        });

        // Paginación
        var total = data.total;
        var totalPages = data.total_pages;
        var page = data.page;

        var countText = '<?php printf( _n( '%s curso', '%s cursos', 0, 'aura-suite' ), '' ); ?>';
        $('#pagination-count').text(total + ' <?php _e( "cursos", "aura-suite" ); ?>');
        $('#page-info').text('<?php _e( "Pág.", "aura-suite" ); ?> ' + page + ' <?php _e( "de", "aura-suite" ); ?> ' + totalPages);
        $('#page-prev').prop('disabled', page <= 1);
        $('#page-next').prop('disabled', page >= totalPages);

        if ( total > 0 ){
            $('#courses-pagination').show();
        }
    }

    // ── ABRIR MODAL CREAR ───────────────────────────────────────
    function openCreateModal(){
        resetCourseForm();
        $('#modal-course-title').text('<?php _e( "Nuevo Curso", "aura-suite" ); ?>');
        loadInstructors();
        loadFinanceCats();
        openModal('#modal-course');
    }

    // ── ABRIR MODAL EDITAR ──────────────────────────────────────
    function openEditModal(id){
        $.post(ajaxUrl, { action:'aura_students_get_course', nonce:nonce, id:id }, function(res){
            if ( ! res.success ){
                AuraStudents.notify(res.data.message, 'error');
                return;
            }
            var c = res.data.course;
            loadInstructors();
            loadFinanceCats();

            $('#course-id').val(c.id);
            $('#course-name').val(c.name);
            $('#course-desc').val(c.description);
            $('#course-status').val(c.status);
            $('#course-weeks').val(c.duration_weeks || '');
            $('#course-max').val(c.max_students);
            $('#course-start').val(c.start_date);
            $('#course-end').val(c.end_date);
            $('#course-cost').val(c.base_cost);
            $('#course-currency').val(c.currency);
            updateCalc();

            // Selects con delay para que lleguen las opciones
            setTimeout(function(){
                $('#course-area').val(c.area_id || '');
                $('#course-instructor').val(c.instructor_id || '');
                $('#course-finance-cat').val(c.finance_cat_id || '');
            }, 300);

            $('#modal-course-title').text('<?php _e( "Editar Curso", "aura-suite" ); ?>');
            openModal('#modal-course');
        }).fail(function(){
            AuraStudents.notify(auraStudents.i18n.error, 'error');
        });
    }

    // ── ABRIR MODAL DETALLE ─────────────────────────────────────
    function openDetailModal(id){
        $('#course-detail-body').html(
            '<p style="text-align:center;padding:30px;">' +
            '<span class="spinner is-active" style="float:none;"></span></p>'
        );
        openModal('#modal-course-detail');

        $.post(ajaxUrl, { action:'aura_students_get_course', nonce:nonce, id:id }, function(res){
            if ( ! res.success ){
                AuraStudents.notify(res.data.message, 'error');
                closeModals();
                return;
            }
            var c = res.data.course;
            currentCourse = c;
            $('#btn-edit-from-detail').data('id', c.id);

            var statusLabels = {
                active  : '<?php _e( "Activo",    "aura-suite" ); ?>',
                inactive: '<?php _e( "Inactivo",  "aura-suite" ); ?>',
                archived: '<?php _e( "Archivado", "aura-suite" ); ?>'
            };

            var html =
                '<div class="aura-stu-detail-grid">' +
                    '<div class="aura-stu-detail-item aura-stu-detail-full">' +
                        '<label><?php _e( "Nombre", "aura-suite" ); ?></label>' +
                        '<span style="font-size:1.1rem;font-weight:700;">'+esc(c.name)+'</span>' +
                    '</div>' +
                    '<div class="aura-stu-detail-item aura-stu-detail-full">' +
                        '<label><?php _e( "Descripción", "aura-suite" ); ?></label>' +
                        '<span>'+(c.description ? esc(c.description) : '—')+'</span>' +
                    '</div>' +
                    makeDI('<?php _e( "Área", "aura-suite" ); ?>', c.area_name) +
                    makeDI('<?php _e( "Instructor", "aura-suite" ); ?>', c.instructor_name) +
                    makeDI('<?php _e( "Duración", "aura-suite" ); ?>', c.duration_weeks ? c.duration_weeks+' semanas' : '—') +
                    makeDI('<?php _e( "Máx. estudiantes", "aura-suite" ); ?>', c.max_students === 0 ? '<?php _e( "Sin límite", "aura-suite" ); ?>' : c.max_students) +
                    makeDI('<?php _e( "Costo base", "aura-suite" ); ?>', c.currency+' '+parseFloat(c.base_cost).toFixed(2)) +
                    makeDI('<?php _e( "Estado", "aura-suite" ); ?>', statusLabels[c.status]) +
                    makeDI('<?php _e( "Fecha inicio", "aura-suite" ); ?>', c.start_date || '—') +
                    makeDI('<?php _e( "Fecha fin", "aura-suite" ); ?>', c.end_date || '—') +
                    makeDI('<?php _e( "Cat. financiera", "aura-suite" ); ?>', c.finance_cat_name) +
                    makeDI('<?php _e( "Inscritos activos", "aura-suite" ); ?>', c.enrolled_count) +
                '</div>';

            $('#course-detail-body').html(html);
        }).fail(function(){
            AuraStudents.notify(auraStudents.i18n.error, 'error');
        });
    }

    function makeDI(label, val){
        return '<div class="aura-stu-detail-item"><label>'+label+'</label><span>'+esc(String(val))+'</span></div>';
    }

    // ── GUARDAR CURSO ───────────────────────────────────────────
    function saveCourse(){
        var $btn = $('#btn-save-course');
        var name = $.trim($('#course-name').val());

        if ( ! name ){
            $('#course-name').focus();
            AuraStudents.notify('<?php _e( "El nombre del curso es obligatorio.", "aura-suite" ); ?>', 'error');
            return;
        }

        $btn.prop('disabled', true).text('<?php _e( "Guardando…", "aura-suite" ); ?>');

        var data = {
            action         : 'aura_students_save_course',
            nonce          : nonce,
            id             : $('#course-id').val(),
            name           : name,
            description    : $('#course-desc').val(),
            area_id        : $('#course-area').val(),
            instructor_id  : $('#course-instructor').val(),
            duration_weeks : $('#course-weeks').val(),
            max_students   : $('#course-max').val(),
            base_cost      : $('#course-cost').val(),
            currency       : $('#course-currency').val(),
            status         : $('#course-status').val(),
            start_date     : $('#course-start').val(),
            end_date       : $('#course-end').val(),
            finance_cat_id : $('#course-finance-cat').val()
        };

        $.post(ajaxUrl, data, function(res){
            $btn.prop('disabled', false).text('<?php _e( "Guardar curso", "aura-suite" ); ?>');
            if ( res.success ){
                AuraStudents.notify(res.data.message, 'success');
                closeModals();
                loadCourses(currentPage);
            } else {
                AuraStudents.notify(res.data.message, 'error');
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('<?php _e( "Guardar curso", "aura-suite" ); ?>');
            AuraStudents.notify(auraStudents.i18n.error, 'error');
        });
    }

    // ── ARCHIVAR CURSO ──────────────────────────────────────────
    function archiveCourse(id){
        if ( ! confirm(auraStudents.i18n.confirm_del) ) return;

        $.post(ajaxUrl, { action:'aura_students_delete_course', nonce:nonce, id:id }, function(res){
            if ( res.success ){
                AuraStudents.notify(res.data.message, 'success');
                loadCourses(currentPage);
            } else {
                AuraStudents.notify(res.data.message, 'error');
            }
        }).fail(function(){
            AuraStudents.notify(auraStudents.i18n.error, 'error');
        });
    }

    // ── CALCULADORA DE BECA ─────────────────────────────────────
    function updateCalc(){
        var base = parseFloat($('#course-cost').val()) || 0;
        var pct  = parseFloat($('#calc-pct').val())   || 0;
        pct = Math.min(100, Math.max(0, pct));
        var net  = base * (1 - pct / 100);
        var cur  = $('#course-currency').val() || 'USD';
        $('#calc-base').text(cur + ' ' + base.toFixed(2));
        $('#calc-net').text(cur + ' ' + net.toFixed(2));
    }

    // ── UTILIDADES ──────────────────────────────────────────────
    function openModal(selector){
        $(selector).fadeIn(150);
        $('body').css('overflow','hidden');
    }

    function closeModals(){
        $('.aura-stu-modal').fadeOut(150);
        $('body').css('overflow','');
    }

    function resetCourseForm(){
        $('#form-course')[0].reset();
        $('#course-id').val(0);
    }

    function esc(str){
        return $('<div/>').text(str).html();
    }

    // ── EVENTOS ─────────────────────────────────────────────────
    function bindEvents(){
        // Botón nuevo curso
        $('#btn-nuevo-curso').on('click', openCreateModal);

        // Acciones en tabla (delegación)
        $('#courses-tbody').on('click', '.btn-view-course', function(){
            openDetailModal($(this).data('id'));
        }).on('click', '.btn-edit-course', function(){
            openEditModal($(this).data('id'));
        }).on('click', '.btn-archive-course', function(){
            archiveCourse($(this).data('id'));
        });

        // Editar desde modal de detalle
        $('#btn-edit-from-detail').on('click', function(){
            var id = $(this).data('id');
            closeModals();
            setTimeout(function(){ openEditModal(id); }, 200);
        });

        // Guardar
        $('#btn-save-course').on('click', saveCourse);
        $('#form-course').on('keydown', function(e){
            if ( e.key === 'Enter' && $(e.target).is('input') ) saveCourse();
        });

        // Cerrar modales
        $(document).on('click', '.aura-stu-modal-close, .aura-stu-modal-backdrop', closeModals);
        $(document).on('keydown', function(e){
            if ( e.key === 'Escape' ) closeModals();
        });

        // Filtros
        $('#btn-apply-filters').on('click', function(){ loadCourses(1); });
        $('#btn-clear-filters').on('click', function(){
            $('#filter-search, #filter-status, #filter-area').val('');
            loadCourses(1);
        });
        $('#filter-search').on('keydown', function(e){
            if ( e.key === 'Enter' ) loadCourses(1);
        });

        // Paginación
        $('#page-prev').on('click', function(){ if ( currentPage > 1 ) loadCourses(currentPage - 1); });
        $('#page-next').on('click', function(){ loadCourses(currentPage + 1); });

        // Calculadora
        $('#course-cost, #calc-pct, #course-currency').on('input change', updateCalc);
        $(document).on('click', '.calc-pct-btn', function(){
            $('#calc-pct').val($(this).data('pct'));
            updateCalc();
        });
    }

})(jQuery);
</script>
