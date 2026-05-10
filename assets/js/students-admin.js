/**
 * Módulo de Estudiantes e Inscripciones — Admin Scripts
 * AURA Business Suite
 *
 * Cubre: Dashboard, Cursos, Lista de Estudiantes, Inscripciones,
 *        Becas, Reportes y Configuración.
 *
 * NOTA: Pagos y Paz-y-Salvo tienen scripts inline propios en sus templates.
 */

/* global jQuery, auraStudents, ApexCharts */

( function ( $ ) {
    'use strict';

    // ================================================================
    // UTILIDADES COMPARTIDAS
    // ================================================================

    var Students = {

        ajax: function ( action, data, success, error ) {
            $.ajax( {
                url:    auraStudents.ajax_url,
                method: 'POST',
                data:   Object.assign( { action: action, nonce: auraStudents.nonce }, data ),
                success: function ( res ) {
                    if ( res.success ) {
                        if ( typeof success === 'function' ) { success( res.data ); }
                    } else {
                        var msg = ( res.data && res.data.message ) ? res.data.message : ( auraStudents.i18n.error || 'Error.' );
                        Students.notify( msg, 'error' );
                        if ( typeof error === 'function' ) { error( res.data ); }
                    }
                },
                error: function () {
                    Students.notify( auraStudents.i18n.error || 'Error de conexión.', 'error' );
                    if ( typeof error === 'function' ) { error(); }
                },
            } );
        },

        notify: function ( message, type ) {
            type = type || 'success';
            var $wrap = $( '.wrap' );
            var cls   = 'notice notice-' + type + ' is-dismissible aura-stu-flash';
            var $n    = $( '<div class="' + cls + '"><p>' + message + '</p></div>' );
            $wrap.find( '.aura-stu-flash' ).remove();
            $n.insertAfter( $wrap.find( 'hr.wp-header-end' ) );
            setTimeout( function () { $n.fadeOut( 400, function () { $( this ).remove(); } ); }, 4000 );
        },

        confirm: function ( msg ) {
            return window.confirm( msg || auraStudents.i18n.confirm_del || '¿Confirmar?' );
        },
    };

    // Helper: escapar HTML
    function escHtml( str ) {
        if ( str === null || str === undefined ) { return ''; }
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#39;' );
    }

    // Helper: formatear fecha YYYY-MM-DD
    function fmtDate( d ) {
        return d.getFullYear() + '-' +
            String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' +
            String( d.getDate() ).padStart( 2, '0' );
    }

    // Helper: moneda
    function fmtMoney( val ) {
        return parseFloat( val || 0 ).toLocaleString( undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
    }

    // ================================================================
    // HELPERS DE MODAL
    // ================================================================

    // Tipo .aura-stu-modal (cursos, estudiantes)
    function openStuModal( id ) {
        $( '#' + id ).addClass( 'is-open' ).css( 'display', 'flex' );
        $( 'body' ).addClass( 'aura-modal-open' );
    }
    function closeStuModal( id ) {
        $( '#' + id ).removeClass( 'is-open' ).hide();
        $( 'body' ).removeClass( 'aura-modal-open' );
    }

    // Tipo .aura-modal-overlay (inscripciones)
    function openOverlayModal( id ) {
        $( '#' + id ).css( 'display', 'flex' );
        $( 'body' ).addClass( 'aura-modal-open' );
    }
    function closeOverlayModal( id ) {
        $( '#' + id ).hide();
        $( 'body' ).removeClass( 'aura-modal-open' );
    }

    // Tipo .aura-modal  (becas)
    function openAuraModal( id ) {
        $( '#' + id ).css( 'display', 'flex' );
        $( 'body' ).addClass( 'aura-modal-open' );
    }
    function closeAuraModal( id ) {
        $( '#' + id ).hide();
        $( 'body' ).removeClass( 'aura-modal-open' );
    }

    // Cierre genérico: botones con data-modal="id"
    $( document ).on( 'click', '.aura-modal-close[data-modal]', function () {
        closeOverlayModal( $( this ).data( 'modal' ) );
    } );

    // ================================================================
    // HELPER: PAGINACIÓN CENTRALIZADA
    // ================================================================
    function renderPagination( $container, page, total, perPage, onChange ) {
        var totalPages = Math.ceil( total / perPage );
        $container.empty();
        if ( totalPages <= 1 ) { $container.hide(); return; }
        $container.show();
        if ( page > 1 ) {
            $container.append(
                $( '<button class="button">&laquo;</button>' ).on( 'click', function () { onChange( page - 1 ); } )
            );
        }
        var start = Math.max( 1, page - 2 );
        var end   = Math.min( totalPages, page + 2 );
        for ( var i = start; i <= end; i++ ) {
            ( function ( num ) {
                var $btn = $( '<button class="button">' + num + '</button>' );
                if ( num === page ) { $btn.addClass( 'current' ); }
                $btn.on( 'click', function () { onChange( num ); } );
                $container.append( $btn );
            } )( i );
        }
        if ( page < totalPages ) {
            $container.append(
                $( '<button class="button">&raquo;</button>' ).on( 'click', function () { onChange( page + 1 ); } )
            );
        }
    }

    // ================================================================
    // DASHBOARD
    // ================================================================

    function refreshKpis() {
        Students.ajax( 'aura_students_dashboard_kpis', {}, function ( data ) {
            $( '#kpi-active-students' ).text( ( data.active_students || 0 ).toLocaleString() );
            $( '#kpi-applicants' ).text( ( data.applicants_pending || 0 ).toLocaleString() );
            $( '#kpi-graduated' ).text( ( data.graduated_year || 0 ).toLocaleString() );
            $( '#kpi-overdue' ).text( ( data.overdue_installments || 0 ).toLocaleString() );
            $( '#kpi-total-students' ).text( ( data.total_students || 0 ).toLocaleString() );
            $( '#kpi-active-courses' ).text( ( data.active_courses || 0 ).toLocaleString() );
            if ( data.income_month !== undefined ) {
                $( '#kpi-income-month' ).text( fmtMoney( data.income_month ) );
            }
            if ( data.projected_income !== undefined ) {
                $( '#kpi-projected' ).text( fmtMoney( data.projected_income ) );
            }
        } );
    }

    function loadRecentActivity() {
        Students.ajax( 'aura_students_recent_activity', {}, function ( data ) {
            var $tbody = $( '#recent-activity-tbody' );
            var items  = data.activities || [];
            if ( items.length === 0 ) {
                $tbody.html( '<tr><td colspan="3" style="text-align:center;color:#6b7280;padding:16px;">— Sin actividad reciente —</td></tr>' );
                return;
            }
            var rowColor = { applicant:'#fef9c3', payment:'#dcfce7', enrollment:'#dbeafe', overdue:'#fee2e2' };
            var html = '';
            items.forEach( function ( item ) {
                html += '<tr style="background:' + ( rowColor[ item.type ] || '#fff' ) + ';">' +
                    '<td style="font-size:1.2em;text-align:center;width:36px;">' + escHtml( item.icon || '📌' ) + '</td>' +
                    '<td>' + ( item.message || '' ) + '</td>' +
                    '<td style="color:#6b7280;font-size:.87em;white-space:nowrap;">' + escHtml( item.time ? item.time.substring( 0, 16 ) : '' ) + '</td>' +
                    '</tr>';
            } );
            $tbody.html( html );
        } );
    }

    function initDashboard() {
        refreshKpis();
        loadRecentActivity();
        setInterval( refreshKpis, 60000 );
    }

    // ================================================================
    // CURSOS
    // ================================================================

    var coursePage    = 1;
    var courseFilters = {};
    var courseMeta    = {};

    function loadCourses( page ) {
        coursePage = page || 1;
        var $tbody = $( '#courses-tbody' );
        var $load  = $( '#courses-loading' );
        if ( $load.length ) { $load.show(); }
        $tbody.html( '' );

        Students.ajax( 'aura_students_list_courses', Object.assign( { page: coursePage, per_page: 15 }, courseFilters ), function ( data ) {
            if ( $load.length ) { $load.hide(); }
            var courses = data.courses || [];
            if ( courses.length === 0 ) {
                $tbody.html( '<tr><td colspan="8" style="text-align:center;padding:20px;color:#6b7280;">— Sin cursos —</td></tr>' );
                $( '#courses-pagination' ).hide();
                $( '#pagination-count' ).text( '0' );
                return;
            }
            var statusLabel = { active: '✅ Activo', draft: '📝 Borrador', finished: '🏁 Terminado', archived: '📦 Archivado' };
            var html = '';
            courses.forEach( function ( c ) {
                html += '<tr>' +
                    '<td><strong>' + escHtml( c.name ) + '</strong>' +
                        ( c.code ? '<br><small style="color:#6b7280;">' + escHtml( c.code ) + '</small>' : '' ) + '</td>' +
                    '<td>' + escHtml( c.area_name || '—' ) + '</td>' +
                    '<td>' + escHtml( c.instructor_name || '—' ) + '</td>' +
                    '<td style="text-align:center;">' + escHtml( String( c.duration_weeks || '—' ) ) + '</td>' +
                    '<td style="text-align:right;">$' + fmtMoney( c.cost ) + ' ' + escHtml( c.currency || '' ) + '</td>' +
                    '<td style="text-align:center;">' + escHtml( statusLabel[ c.status ] || c.status ) + '</td>' +
                    '<td style="text-align:center;">' + escHtml( String( c.enrolled_count || 0 ) ) + '</td>' +
                    '<td style="white-space:nowrap;">' +
                        '<button class="button button-small btn-edit-course" data-id="' + escHtml( String( c.id ) ) + '">✏️</button> ' +
                        '<button class="button button-small btn-del-course" data-id="' + escHtml( String( c.id ) ) + '" style="color:#dc2626;">🗑</button>' +
                    '</td></tr>';
            } );
            $tbody.html( html );
            var total = data.total || 0;
            $( '#pagination-count' ).text( total );
            renderPagination( $( '#courses-pagination' ), coursePage, total, 15, loadCourses );
            var totalPages = Math.ceil( total / 15 );
            $( '#page-prev' ).prop( 'disabled', coursePage <= 1 );
            $( '#page-next' ).prop( 'disabled', coursePage >= totalPages );
            $( '#page-info' ).text( coursePage + ' / ' + Math.max( 1, totalPages ) );
        }, function () {
            if ( $load.length ) { $load.hide(); }
        } );
    }

    function loadCourseMeta( cb ) {
        if ( courseMeta._loaded ) { cb(); return; }
        var done = 0;
        function check() { done++; if ( done === 3 ) { courseMeta._loaded = true; cb(); } }
        Students.ajax( 'aura_students_get_areas', {}, function ( d ) {
            var opts = '<option value="">— Área —</option>';
            ( d.areas || [] ).forEach( function ( a ) { opts += '<option value="' + a.id + '">' + escHtml( a.name ) + '</option>'; } );
            courseMeta.areasHtml = opts;
            check();
        }, check );
        Students.ajax( 'aura_students_get_instructors', {}, function ( d ) {
            var opts = '<option value="">— Instructor —</option>';
            ( d.instructors || [] ).forEach( function ( i ) { opts += '<option value="' + i.id + '">' + escHtml( i.name ) + '</option>'; } );
            courseMeta.instructorsHtml = opts;
            check();
        }, check );
        Students.ajax( 'aura_students_get_finance_cats', {}, function ( d ) {
            var opts = '<option value="">— Sin categoría —</option>';
            ( d.categories || [] ).forEach( function ( c ) { opts += '<option value="' + c.id + '">' + escHtml( c.name ) + '</option>'; } );
            courseMeta.finCatsHtml = opts;
            check();
        }, check );
    }

    function openCourseModal( courseId ) {
        $( '#form-course' )[ 0 ].reset();
        $( '#course-id' ).val( '' );
        $( '#calc-net' ).val( '' );
        loadCourseMeta( function () {
            $( '#course-area' ).html( courseMeta.areasHtml );
            $( '#course-instructor' ).html( courseMeta.instructorsHtml );
            $( '#course-finance-cat' ).html( courseMeta.finCatsHtml );
            if ( courseId ) {
                $( '#modal-course-title' ).text( 'Editar Curso' );
                Students.ajax( 'aura_students_get_course', { id: courseId }, function ( d ) {
                    var c = d.course || d;
                    $( '#course-id' ).val( c.id );
                    $( '#course-name' ).val( c.name );
                    $( '#course-status' ).val( c.status );
                    $( '#course-desc' ).val( c.description );
                    $( '#course-area' ).val( c.area_id );
                    $( '#course-instructor' ).val( c.instructor_id );
                    $( '#course-weeks' ).val( c.duration_weeks );
                    $( '#course-max' ).val( c.max_students );
                    $( '#course-start' ).val( c.start_date );
                    $( '#course-end' ).val( c.end_date );
                    $( '#course-cost' ).val( c.cost );
                    $( '#course-currency' ).val( c.currency );
                    $( '#course-finance-cat' ).val( c.finance_category_id );
                    $( '#calc-base' ).val( fmtMoney( c.cost ) );
                    openStuModal( 'modal-course' );
                } );
            } else {
                $( '#modal-course-title' ).text( 'Nuevo Curso' );
                openStuModal( 'modal-course' );
            }
        } );
    }

    function saveCourse() {
        var formData = {};
        $( '#form-course' ).serializeArray().forEach( function ( f ) { formData[ f.name ] = f.value; } );
        var id = $( '#course-id' ).val();
        if ( id ) { formData.id = id; }
        Students.ajax( 'aura_students_save_course', formData, function () {
            Students.notify( auraStudents.i18n.saved || 'Guardado.' );
            closeStuModal( 'modal-course' );
            loadCourses( id ? coursePage : 1 );
        } );
    }

    function deleteCourse( courseId ) {
        if ( ! Students.confirm() ) { return; }
        Students.ajax( 'aura_students_delete_course', { id: courseId }, function () {
            Students.notify( 'Curso eliminado.' );
            loadCourses( 1 );
        } );
    }

    function updateCalcNet() {
        var base = parseFloat( $( '#calc-base' ).val() ) || 0;
        var pct  = parseFloat( $( '#calc-pct' ).val() ) || 0;
        $( '#calc-net' ).val( fmtMoney( base * ( 1 - pct / 100 ) ) );
    }

    function initCourses() {
        loadCourses( 1 );

        $( '#btn-apply-filters' ).on( 'click', function () {
            courseFilters = {
                search: $( '#filter-search' ).val().trim(),
                status: $( '#filter-status' ).val(),
                area:   $( '#filter-area' ).val(),
            };
            loadCourses( 1 );
        } );
        $( '#filter-search' ).on( 'keydown', function ( e ) {
            if ( e.key === 'Enter' ) { $( '#btn-apply-filters' ).trigger( 'click' ); }
        } );
        $( '#btn-clear-filters' ).on( 'click', function () {
            $( '#filter-search, #filter-status, #filter-area' ).val( '' );
            courseFilters = {};
            loadCourses( 1 );
        } );

        $( '#page-prev' ).on( 'click', function () { loadCourses( coursePage - 1 ); } );
        $( '#page-next' ).on( 'click', function () { loadCourses( coursePage + 1 ); } );

        $( '#btn-nuevo-curso' ).on( 'click', function () { openCourseModal( null ); } );

        $( document ).on( 'click', '.btn-edit-course', function () { openCourseModal( $( this ).data( 'id' ) ); } );
        $( document ).on( 'click', '.btn-del-course',  function () { deleteCourse( $( this ).data( 'id' ) ); } );

        $( '#form-course' ).on( 'submit', function ( e ) { e.preventDefault(); saveCourse(); } );

        $( '#modal-course' ).on( 'click', '.aura-stu-modal-close, .aura-stu-modal-backdrop', function () {
            closeStuModal( 'modal-course' );
        } );

        // Calculadora de costo neto (beca/descuento)
        $( '#course-cost' ).on( 'input', function () {
            $( '#calc-base' ).val( parseFloat( $( this ).val() || 0 ).toFixed( 2 ) );
            updateCalcNet();
        } );
        $( '#calc-pct' ).on( 'input', updateCalcNet );
        $( document ).on( 'click', '.calc-pct-btn', function () {
            $( '#calc-pct' ).val( $( this ).data( 'pct' ) );
            updateCalcNet();
        } );
    }

    // ================================================================
    // LISTA DE ESTUDIANTES
    // ================================================================

    var studentPage    = 1;
    var studentFilters = {};

    function loadStudents( page ) {
        studentPage = page || 1;
        var $tbody = $( '#students-tbody' );
        $tbody.html( '<tr><td colspan="7" style="text-align:center;padding:20px;"><span class="spinner is-active" style="float:none;margin:0 auto;"></span></td></tr>' );

        Students.ajax( 'aura_students_list', Object.assign( { page: studentPage, per_page: 20 }, studentFilters ), function ( data ) {
            var students = data.students || [];
            if ( students.length === 0 ) {
                $tbody.html( '<tr><td colspan="7" style="text-align:center;padding:20px;color:#6b7280;">— Sin estudiantes —</td></tr>' );
                $( '#students-pagination' ).hide();
                $( '#pagination-count' ).text( '0' );
                return;
            }
            var statusLabel = {
                applicant: '⏳ Postulante', active: '✅ Activo',
                graduated: '🎓 Egresado', inactive: '⏸ Inactivo', suspended: '🚫 Suspendido',
            };
            var html = '';
            students.forEach( function ( s ) {
                html += '<tr>' +
                    '<td><strong>' + escHtml( s.first_name + ' ' + s.last_name ) + '</strong>' +
                        ( s.student_code ? '<br><small style="color:#6b7280;">' + escHtml( s.student_code ) + '</small>' : '' ) + '</td>' +
                    '<td>' + escHtml( s.email ) + '</td>' +
                    '<td>' + escHtml( s.phone || '—' ) + '</td>' +
                    '<td>' + escHtml( s.profile_type || 'student' ) + '</td>' +
                    '<td>' + escHtml( statusLabel[ s.status ] || s.status ) + '</td>' +
                    '<td>' + escHtml( s.created_at_human || s.created_at || '—' ) + '</td>' +
                    '<td style="white-space:nowrap;">' +
                        '<button class="button button-small btn-view-student" data-id="' + s.id + '">👁</button> ' +
                        '<button class="button button-small btn-edit-student" data-id="' + s.id + '">✏️</button> ' +
                        '<button class="button button-small btn-del-student" data-id="' + s.id + '" style="color:#dc2626;">🗑</button>' +
                    '</td></tr>';
            } );
            $tbody.html( html );
            var total = data.total || 0;
            $( '#pagination-count' ).text( total );
            renderPagination( $( '#students-pagination' ), studentPage, total, 20, loadStudents );
            var totalPages = Math.ceil( total / 20 );
            $( '#page-prev' ).prop( 'disabled', studentPage <= 1 );
            $( '#page-next' ).prop( 'disabled', studentPage >= totalPages );
            $( '#page-info' ).text( studentPage + ' / ' + Math.max( 1, totalPages ) );
        } );
    }

    function openStudentDetail( studentId ) {
        var $body = $( '#student-detail-body' );
        $body.html( '<div style="text-align:center;padding:30px;"><span class="spinner is-active" style="float:none;margin:0 auto;display:block;"></span></div>' );
        openStuModal( 'modal-student-detail' );
        Students.ajax( 'aura_students_get', { id: studentId }, function ( data ) {
            var s           = data.student || data;
            var enrollments = data.enrollments || [];
            var enrollHtml  = '';
            if ( enrollments.length > 0 ) {
                enrollHtml = '<table style="width:100%;margin-top:8px;font-size:.87em;border-collapse:collapse;">' +
                    '<thead><tr>' +
                    '<th style="text-align:left;padding:4px 8px;border-bottom:1px solid #e5e7eb;">Curso</th>' +
                    '<th style="text-align:left;padding:4px 8px;border-bottom:1px solid #e5e7eb;">Estado</th>' +
                    '<th style="text-align:right;padding:4px 8px;border-bottom:1px solid #e5e7eb;">Pagado</th>' +
                    '</tr></thead><tbody>';
                enrollments.forEach( function ( e ) {
                    enrollHtml += '<tr>' +
                        '<td style="padding:4px 8px;">' + escHtml( e.course_name ) + '</td>' +
                        '<td style="padding:4px 8px;">' + escHtml( e.status ) + '</td>' +
                        '<td style="padding:4px 8px;text-align:right;">$' + fmtMoney( e.total_paid ) + '</td>' +
                        '</tr>';
                } );
                enrollHtml += '</tbody></table>';
            } else {
                enrollHtml = '<p style="color:#6b7280;margin:4px 0;">— Sin inscripciones activas —</p>';
            }
            $body.html(
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;font-size:.95em;">' +
                '<p style="margin:0;"><strong>Nombre:</strong> ' + escHtml( ( s.first_name || '' ) + ' ' + ( s.last_name || '' ) ) + '</p>' +
                '<p style="margin:0;"><strong>Código:</strong> ' + escHtml( s.student_code || '—' ) + '</p>' +
                '<p style="margin:0;"><strong>Email:</strong> ' + escHtml( s.email || '—' ) + '</p>' +
                '<p style="margin:0;"><strong>Teléfono:</strong> ' + escHtml( s.phone || '—' ) + '</p>' +
                '<p style="margin:0;"><strong>Tipo:</strong> ' + escHtml( s.profile_type || 'student' ) + '</p>' +
                '<p style="margin:0;"><strong>Estado:</strong> ' + escHtml( s.status || '—' ) + '</p>' +
                '<p style="margin:0;"><strong>Ciudad:</strong> ' + escHtml( s.city || '—' ) + '</p>' +
                '<p style="margin:0;"><strong>País:</strong> ' + escHtml( s.country || '—' ) + '</p>' +
                '</div>' +
                '<hr style="margin:12px 0;">' +
                '<h4 style="margin:0 0 6px;">Inscripciones</h4>' +
                enrollHtml
            );
            $( '#btn-edit-from-detail' ).data( 'id', studentId );

            // F7.4 — Sección de Préstamos Biblioteca (solo si el módulo está activo y el estudiante tiene wp_user_id)
            if ( auraStudents.library_active === '1' && s.wp_user_id ) {
                var $libSection = $( '<div id="stu-library-section" style="margin-top:16px;">' +
                    '<hr style="margin:0 0 10px;">' +
                    '<h4 style="margin:0 0 6px;">📚 ' + ( auraStudents.i18n.lib_loans || 'Préstamos Biblioteca' ) + '</h4>' +
                    '<p style="color:#6b7280;font-size:.9em;">Cargando…</p>' +
                    '</div>' );
                $body.append( $libSection );

                $.post( auraStudents.ajax_url, {
                    action  : 'aura_library_loans_student_summary',
                    nonce   : auraStudents.nonce,
                    wp_user_id: s.wp_user_id
                } ).done( function( resp ) {
                    if ( ! resp.success ) { $libSection.find( 'p' ).text( '—' ); return; }
                    var d = resp.data;
                    var badges = '';
                    if ( d.has_overdue ) {
                        badges += '<span style="background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;border-radius:4px;padding:2px 8px;font-size:.82em;margin-right:6px;">' +
                            escHtml( auraStudents.i18n.lib_overdue || '⚠ Préstamos vencidos' ) + '</span>';
                    }
                    if ( d.has_fines ) {
                        badges += '<span style="background:#fffbeb;color:#d97706;border:1px solid #fcd34d;border-radius:4px;padding:2px 8px;font-size:.82em;">' +
                            escHtml( auraStudents.i18n.lib_fines || '💸 Multas pendientes' ) + '</span>';
                    }
                    var loansHtml = '';
                    if ( d.loans && d.loans.length > 0 ) {
                        loansHtml = '<table style="width:100%;margin-top:8px;font-size:.87em;border-collapse:collapse;">' +
                            '<thead><tr>' +
                            '<th style="text-align:left;padding:4px 8px;border-bottom:1px solid #e5e7eb;">Libro</th>' +
                            '<th style="text-align:left;padding:4px 8px;border-bottom:1px solid #e5e7eb;">Dewey</th>' +
                            '<th style="text-align:left;padding:4px 8px;border-bottom:1px solid #e5e7eb;">' + escHtml( auraStudents.i18n.lib_due || 'Vence:' ) + '</th>' +
                            '<th style="text-align:left;padding:4px 8px;border-bottom:1px solid #e5e7eb;">Estado</th>' +
                            '</tr></thead><tbody>';
                        d.loans.forEach( function( l ) {
                            var rowColor = l.status === 'overdue' ? '#fef2f2' : '';
                            loansHtml += '<tr style="background:' + rowColor + ';">' +
                                '<td style="padding:4px 8px;">' + escHtml( l.book_title ) + '</td>' +
                                '<td style="padding:4px 8px;">' + escHtml( l.dewey ) + '</td>' +
                                '<td style="padding:4px 8px;">' + escHtml( l.due_date ) + '</td>' +
                                '<td style="padding:4px 8px;">' + escHtml( l.status ) + '</td>' +
                                '</tr>';
                        } );
                        loansHtml += '</tbody></table>';
                    } else {
                        loansHtml = '<p style="color:#6b7280;margin:4px 0;">— ' + escHtml( auraStudents.i18n.lib_no_loans || 'Sin préstamos activos.' ) + ' —</p>';
                    }
                    $libSection.html(
                        '<hr style="margin:0 0 10px;">' +
                        '<h4 style="margin:0 0 6px;">📚 ' + escHtml( auraStudents.i18n.lib_loans || 'Préstamos Biblioteca' ) + '</h4>' +
                        ( badges ? '<div style="margin-bottom:8px;">' + badges + '</div>' : '' ) +
                        loansHtml
                    );
                } );
            }
        } );
    }

    function openStudentForm( studentId ) {
        $( '#form-student' )[ 0 ].reset();
        $( '#student-id' ).val( '' );
        // Reset tabs
        $( '.aura-stu-tab' ).first().addClass( 'active' ).siblings().removeClass( 'active' );
        $( '.aura-stu-tab-panel' ).hide().first().show();

        // Cargar programas/áreas
        Students.ajax( 'aura_students_get_programs', {}, function ( data ) {
            var html = '';
            ( data.programs || [] ).forEach( function ( p ) {
                html += '<label style="display:flex;align-items:center;gap:6px;font-size:.9em;">' +
                    '<input type="checkbox" name="programs[]" value="' + escHtml( String( p.id ) ) + '"> ' +
                    escHtml( p.name ) + '</label>';
            } );
            $( '#programs-checkboxes' ).html( html || '<p style="color:#6b7280;">Sin programas disponibles.</p>' );
        } );

        if ( studentId ) {
            $( '#modal-form-title' ).text( 'Editar Estudiante' );
            Students.ajax( 'aura_students_get', { id: studentId }, function ( data ) {
                var s = data.student || data;
                $( '#student-id' ).val( s.id );
                $( '#stu-first-name' ).val( s.first_name );
                $( '#stu-last-name' ).val( s.last_name );
                $( '#stu-email' ).val( s.email );
                $( '#stu-phone' ).val( s.phone );
                $( '#stu-id-type' ).val( s.id_type );
                $( '#stu-id-number' ).val( s.id_number );
                $( '#stu-birthdate' ).val( s.birthdate );
                $( '#stu-gender' ).val( s.gender );
                $( '#stu-address' ).val( s.address );
                $( '#stu-city' ).val( s.city );
                $( '#stu-country' ).val( s.country );
                $( '#stu-photo-url' ).val( s.photo_url );
                $( '#stu-motivation' ).val( s.motivation );
                $( '#stu-supported-by' ).val( s.supported_by );
                $( '#stu-talent' ).val( s.talent );
                $( '#stu-experience' ).val( s.experience );
                $( '#stu-extra-info' ).val( s.extra_info );
                $( '#stu-profile-type' ).val( s.profile_type );
                // Marcar programas
                ( data.student_programs || [] ).forEach( function ( pid ) {
                    $( 'input[name="programs[]"][value="' + pid + '"]' ).prop( 'checked', true );
                } );
                openStuModal( 'modal-student-form' );
            } );
        } else {
            $( '#modal-form-title' ).text( 'Nuevo Estudiante' );
            openStuModal( 'modal-student-form' );
        }
    }

    function saveStudent() {
        var programs = [];
        $( 'input[name="programs[]"]:checked' ).each( function () { programs.push( $( this ).val() ); } );
        var formData = {};
        $( '#form-student' ).serializeArray().forEach( function ( f ) {
            if ( f.name !== 'programs[]' ) { formData[ f.name ] = f.value; }
        } );
        formData.programs = programs.join( ',' );
        var id = $( '#student-id' ).val();
        if ( id ) { formData.id = id; }
        Students.ajax( 'aura_students_save', formData, function () {
            Students.notify( auraStudents.i18n.saved || 'Guardado.' );
            closeStuModal( 'modal-student-form' );
            loadStudents( id ? studentPage : 1 );
        } );
    }

    function deleteStudent( studentId ) {
        if ( ! Students.confirm() ) { return; }
        Students.ajax( 'aura_students_delete', { id: studentId }, function () {
            Students.notify( 'Estudiante eliminado.' );
            loadStudents( 1 );
        } );
    }

    function initStudentsList() {
        loadStudents( 1 );

        $( '#btn-apply-filters' ).on( 'click', function () {
            studentFilters = {
                search:  $( '#filter-search' ).val().trim(),
                status:  $( '#filter-status' ).val(),
                profile: $( '#filter-profile' ).val(),
                area:    $( '#filter-area' ).val(),
            };
            loadStudents( 1 );
        } );
        $( '#filter-search' ).on( 'keydown', function ( e ) {
            if ( e.key === 'Enter' ) { $( '#btn-apply-filters' ).trigger( 'click' ); }
        } );
        $( '#btn-clear-filters' ).on( 'click', function () {
            $( '#filter-search, #filter-status, #filter-profile, #filter-area' ).val( '' );
            studentFilters = {};
            loadStudents( 1 );
        } );

        $( '#page-prev' ).on( 'click', function () { loadStudents( studentPage - 1 ); } );
        $( '#page-next' ).on( 'click', function () { loadStudents( studentPage + 1 ); } );

        $( '#btn-nuevo-estudiante' ).on( 'click', function () { openStudentForm( null ); } );

        $( document ).on( 'click', '.btn-view-student', function () { openStudentDetail( $( this ).data( 'id' ) ); } );
        $( document ).on( 'click', '.btn-edit-student', function () { openStudentForm( $( this ).data( 'id' ) ); } );
        $( document ).on( 'click', '.btn-del-student',  function () { deleteStudent( $( this ).data( 'id' ) ); } );

        $( document ).on( 'click', '#btn-edit-from-detail', function () {
            var id = $( this ).data( 'id' );
            closeStuModal( 'modal-student-detail' );
            openStudentForm( id );
        } );

        // Tabs del formulario de estudiante
        $( document ).on( 'click', '.aura-stu-tab', function () {
            var tab = $( this ).data( 'tab' );
            $( '.aura-stu-tab' ).removeClass( 'active' );
            $( this ).addClass( 'active' );
            $( '.aura-stu-tab-panel' ).hide();
            $( '#' + tab ).show();
        } );

        $( '#form-student' ).on( 'submit', function ( e ) { e.preventDefault(); saveStudent(); } );

        $( document ).on( 'click', '#modal-student-detail .aura-stu-modal-close, #modal-student-detail .aura-stu-modal-backdrop', function () {
            closeStuModal( 'modal-student-detail' );
        } );
        $( document ).on( 'click', '#modal-student-form .aura-stu-modal-close, #modal-student-form .aura-stu-modal-backdrop', function () {
            closeStuModal( 'modal-student-form' );
        } );
    }

    // ================================================================
    // INSCRIPCIONES
    // ================================================================

    var applicantPage     = 1;
    var enrollmentPage    = 1;
    var applicantFilters  = {};
    var enrollmentFilters = { status: 'active' };

    function loadApplicants( page ) {
        applicantPage = page || 1;
        var $tbody = $( '#applicants-tbody' );
        $tbody.html( '<tr><td colspan="7" style="text-align:center;padding:16px;"><span class="spinner is-active" style="float:none;margin:0 auto;"></span></td></tr>' );
        Students.ajax( 'aura_students_list_applicants', Object.assign( { page: applicantPage, per_page: 15 }, applicantFilters ), function ( data ) {
            var badge = parseInt( data.total || 0, 10 );
            $( '#badge-applicants' ).text( badge ).toggle( badge > 0 );
            var items = data.applicants || data.students || [];
            if ( items.length === 0 ) {
                $tbody.html( '<tr><td colspan="7" style="text-align:center;padding:20px;color:#6b7280;">— Sin postulantes pendientes —</td></tr>' );
                $( '#applicants-pagination' ).hide();
                return;
            }
            var html = '';
            items.forEach( function ( a ) {
                html += '<tr>' +
                    '<td><img src="' + escHtml( a.photo_url || '' ) + '" style="width:36px;height:36px;border-radius:50%;object-fit:cover;background:#e5e7eb;" onerror="this.style.display=\'none\'"></td>' +
                    '<td><strong>' + escHtml( a.first_name + ' ' + a.last_name ) + '</strong></td>' +
                    '<td>' + escHtml( a.email ) + '</td>' +
                    '<td>' + escHtml( a.profile_type || 'student' ) + '</td>' +
                    '<td>' + escHtml( a.created_at_human || a.created_at || '—' ) + '</td>' +
                    '<td style="max-width:180px;white-space:normal;font-size:.85em;">' + escHtml( ( a.motivation || '' ).substring( 0, 100 ) ) + '</td>' +
                    '<td style="white-space:nowrap;">' +
                        '<button class="button button-primary button-small btn-open-approve" data-id="' + a.id + '" style="background:#8b5cf6;border-color:#7c3aed;">✅ Procesar</button> ' +
                        '<button class="button button-small btn-open-reject" data-id="' + a.id + '" data-name="' + escHtml( a.first_name + ' ' + a.last_name ) + '" style="color:#dc2626;">❌</button>' +
                    '</td></tr>';
            } );
            $tbody.html( html );
            renderPagination( $( '#applicants-pagination' ), applicantPage, data.total || 0, 15, loadApplicants );
        } );
    }

    function loadEnrollments( page ) {
        enrollmentPage = page || 1;
        var $tbody = $( '#enrollments-tbody' );
        $tbody.html( '<tr><td colspan="8" style="text-align:center;padding:16px;"><span class="spinner is-active" style="float:none;margin:0 auto;"></span></td></tr>' );
        Students.ajax( 'aura_students_list_enrollments', Object.assign( { page: enrollmentPage, per_page: 20 }, enrollmentFilters ), function ( data ) {
            var items = data.enrollments || [];
            if ( items.length === 0 ) {
                $tbody.html( '<tr><td colspan="8" style="text-align:center;padding:20px;color:#6b7280;">— Sin inscripciones —</td></tr>' );
                $( '#enrollments-pagination' ).hide();
                return;
            }
            var statusBadge = {
                active:    '<span style="color:#059669;">✅ Activo</span>',
                pending:   '<span style="color:#d97706;">⏳ Pend.</span>',
                graduated: '<span style="color:#8b5cf6;">🎓 Egresado</span>',
                withdrawn: '<span style="color:#6b7280;">↩ Retirado</span>',
                suspended: '<span style="color:#dc2626;">🚫 Susp.</span>',
            };
            var html = '';
            items.forEach( function ( e ) {
                var balance = parseFloat( e.net_cost || 0 ) - parseFloat( e.total_paid || 0 );
                var schPct  = e.scholarship_pct ? e.scholarship_pct + '%' : '—';
                html += '<tr>' +
                    '<td><strong>' + escHtml( e.student_name ) + '</strong>' +
                        ( e.student_code ? '<br><small style="color:#6b7280;">' + escHtml( e.student_code ) + '</small>' : '' ) + '</td>' +
                    '<td>' + escHtml( e.course_name ) + '<br><small>' + escHtml( e.area_name || '' ) + '</small></td>' +
                    '<td style="text-align:center;">' + escHtml( schPct ) + '</td>' +
                    '<td style="text-align:right;">$' + fmtMoney( e.net_cost ) + '</td>' +
                    '<td style="text-align:right;">$' + fmtMoney( e.total_paid ) + '</td>' +
                    '<td style="text-align:right;color:' + ( balance > 0.009 ? '#dc2626' : '#059669' ) + ';">$' + fmtMoney( balance ) + '</td>' +
                    '<td>' + escHtml( e.payment_scheme || '—' ) + '</td>' +
                    '<td style="white-space:nowrap;">' +
                        ( e.status === 'active' ? '<button class="button button-small btn-graduate" data-id="' + e.id + '">🎓</button> ' : '' ) +
                        '<button class="button button-small btn-edit-enrollment" data-id="' + e.id + '" data-student="' + escHtml( e.student_name ) + '" data-course="' + escHtml( e.course_name ) + '">✏️</button>' +
                    '</td></tr>';
            } );
            $tbody.html( html );
            renderPagination( $( '#enrollments-pagination' ), enrollmentPage, data.total || 0, 20, loadEnrollments );
        } );
    }

    // Cálculo de resumen en el formulario de inscripción
    function updateEnrollSummary() {
        var base    = parseFloat( $( '#enroll-base-cost' ).val() ) || 0;
        var pct     = parseFloat( $( '#enroll-scholarship-pct' ).val() ) || 0;
        var scheme  = $( '#enroll-payment-scheme' ).val();
        var count   = parseInt( $( '#enroll-installment-count' ).val() || 1, 10 );
        if ( count < 1 ) { count = 1; }
        var discount     = base * pct / 100;
        var net          = base - discount;
        var installment  = scheme === 'installments' && count > 0 ? net / count : 0;
        $( '#summary-base' ).text( '$' + fmtMoney( base ) );
        $( '#summary-discount' ).text( '−$' + fmtMoney( discount ) );
        $( '#summary-net' ).text( '$' + fmtMoney( net ) );
        $( '#summary-installment-row' ).toggle( scheme === 'installments' );
        $( '#summary-installment' ).text( '$' + fmtMoney( installment ) );
    }

    function openApproveModal( studentId ) {
        Students.ajax( 'aura_students_get', { id: studentId }, function ( data ) {
            var s = data.student || data;
            $( '#approve-student-id' ).val( s.id );
            $( '#approve-student-name' ).text( ( s.first_name || '' ) + ' ' + ( s.last_name || '' ) );
            $( '#approve-student-email' ).text( s.email || '—' );
            $( '#approve-student-phone' ).text( s.phone || '—' );
            $( '#approve-student-motivation' ).text( s.motivation || '—' );
            $( '#approve-step-1-notice' ).hide();
            $( '#approve-step-2' ).hide();
            $( '#btn-do-approve' ).prop( 'disabled', false ).text( '✅ Aprobar postulante' ).show();
            // Si ya es activo, ofrecer saltar al paso 2
            if ( s.status === 'active' ) {
                $( '#btn-skip-approve-to-enroll' ).show();
            } else {
                $( '#btn-skip-approve-to-enroll' ).hide();
            }
            $( '#enroll-course' ).html( '<option value="">— Seleccionar área primero —</option>' );
            $( '#enroll-base-cost' ).val( '0' );
            $( '#enroll-scholarship-type' ).val( 'none' );
            $( '#enroll-scholarship-pct' ).val( '0' );
            $( '#enroll-payment-scheme' ).val( 'full' );
            $( '#row-scholarship-pct, #row-scholarship-sponsor, #row-installments' ).hide();
            updateEnrollSummary();
            openOverlayModal( 'modal-approve' );
        } );
    }

    function submitEnroll( studentId ) {
        var courseId = $( '#enroll-course' ).val();
        if ( ! courseId ) { alert( 'Selecciona un curso.' ); return; }
        var $btn = $( '#btn-do-enroll' );
        $btn.prop( 'disabled', true ).text( '⏳ Inscribiendo…' );
        Students.ajax( 'aura_students_enroll', {
            student_id:           studentId,
            course_id:            courseId,
            base_cost:            $( '#enroll-base-cost' ).val(),
            scholarship_type:     $( '#enroll-scholarship-type' ).val(),
            scholarship_pct:      $( '#enroll-scholarship-pct' ).val(),
            scholarship_sponsor:  $( '#enroll-scholarship-sponsor' ).val(),
            payment_scheme:       $( '#enroll-payment-scheme' ).val(),
            installment_count:    $( '#enroll-installment-count' ).val(),
            first_payment:        $( '#enroll-first-payment' ).val(),
            notes:                $( '#enroll-notes' ).val(),
        }, function () {
            $btn.prop( 'disabled', false ).text( '📋 Inscribir estudiante' );
            Students.notify( '✅ Inscripción creada correctamente.' );
            closeOverlayModal( 'modal-approve' );
            loadApplicants( 1 );
            loadEnrollments( 1 );
        }, function () {
            $btn.prop( 'disabled', false ).text( '📋 Inscribir estudiante' );
        } );
    }

    function initEnrollments() {
        // Tabs de nav
        $( document ).on( 'click', '.aura-enrollment-tab', function ( e ) {
            e.preventDefault();
            var tab = $( this ).data( 'tab' );
            $( '.aura-enrollment-tab' ).removeClass( 'nav-tab-active' );
            $( this ).addClass( 'nav-tab-active' );
            $( '.aura-enrollment-panel' ).hide();
            $( '#tab-' + tab ).show();
        } );

        loadApplicants( 1 );
        loadEnrollments( 1 );

        // Filtros postulantes
        $( '#btn-refresh-applicants' ).on( 'click', function () {
            applicantFilters = {
                search:  $( '#applicant-search' ).val().trim(),
                profile: $( '#applicant-profile' ).val(),
            };
            loadApplicants( 1 );
        } );

        // Filtros inscripciones
        $( '#btn-refresh-enrollments' ).on( 'click', function () {
            enrollmentFilters = {
                search: $( '#enrollment-search' ).val().trim(),
                area:   $( '#enrollment-area' ).val(),
                status: $( '#enrollment-status' ).val(),
            };
            loadEnrollments( 1 );
        } );

        // Abrir modal aprobar
        $( document ).on( 'click', '.btn-open-approve', function () {
            openApproveModal( $( this ).data( 'id' ) );
        } );

        // Step 1: Aprobar
        $( document ).on( 'click', '#btn-do-approve', function () {
            var studentId = $( '#approve-student-id' ).val();
            $( this ).prop( 'disabled', true ).text( '⏳ Procesando…' );
            Students.ajax( 'aura_students_approve', { student_id: studentId }, function () {
                $( '#approve-step-1-notice' )
                    .show()
                    .html( '<span style="color:#059669;">✅ Aprobado. Ahora inscribe al estudiante en un curso.</span>' );
                $( '#btn-do-approve, #btn-skip-approve-to-enroll' ).hide();
                $( '#approve-step-2' ).slideDown( 200 );
            }, function () {
                $( '#btn-do-approve' ).prop( 'disabled', false ).text( '✅ Aprobar postulante' );
            } );
        } );

        // Step 1: Saltar a inscripción (ya aprobado)
        $( document ).on( 'click', '#btn-skip-approve-to-enroll', function () {
            $( '#btn-do-approve, #btn-skip-approve-to-enroll' ).hide();
            $( '#approve-step-2' ).slideDown( 200 );
        } );

        // Área → cargar cursos
        $( document ).on( 'change', '#enroll-area', function () {
            var areaId = $( this ).val();
            $( '#enroll-course' ).html( '<option value="">Cargando…</option>' ).prop( 'disabled', true );
            if ( ! areaId ) {
                $( '#enroll-course' ).html( '<option value="">— Seleccionar área primero —</option>' ).prop( 'disabled', false );
                return;
            }
            Students.ajax( 'aura_students_get_courses_by_area', { area_id: areaId }, function ( d ) {
                var opts = '<option value="">— Seleccionar curso —</option>';
                ( d.courses || [] ).forEach( function ( c ) {
                    opts += '<option value="' + c.id + '" data-cost="' + c.cost + '" data-currency="' + escHtml( c.currency || '' ) + '">' + escHtml( c.name ) + '</option>';
                } );
                $( '#enroll-course' ).html( opts ).prop( 'disabled', false );
            } );
        } );

        // Curso seleccionado → actualizar costo base
        $( document ).on( 'change', '#enroll-course', function () {
            var $opt = $( this ).find( ':selected' );
            $( '#enroll-base-cost' ).val( $opt.data( 'cost' ) || 0 );
            $( '#enroll-currency' ).text( $opt.data( 'currency' ) || 'USD' );
            updateEnrollSummary();
        } );

        // Costo base manual → recalcular
        $( document ).on( 'input', '#enroll-base-cost, #enroll-scholarship-pct, #enroll-installment-count', updateEnrollSummary );

        // Tipo de beca → mostrar/ocultar campos
        $( document ).on( 'change', '#enroll-scholarship-type', function () {
            var type = $( this ).val();
            $( '#row-scholarship-pct' ).toggle( type !== 'none' );
            $( '#row-scholarship-sponsor' ).toggle( type === 'external' );
            updateEnrollSummary();
        } );

        // Quick pct buttons
        $( document ).on( 'click', '.aura-pct-btn', function () {
            $( '#enroll-scholarship-pct' ).val( $( this ).data( 'pct' ) );
            updateEnrollSummary();
        } );

        // Esquema de pago → mostrar/ocultar cuotas
        $( document ).on( 'change', '#enroll-payment-scheme', function () {
            $( '#row-installments' ).toggle( $( this ).val() === 'installments' );
            updateEnrollSummary();
        } );

        // Inscribir
        $( document ).on( 'click', '#btn-do-enroll', function () {
            submitEnroll( $( '#approve-student-id' ).val() );
        } );

        // Modal Rechazar: abrir
        $( document ).on( 'click', '.btn-open-reject', function () {
            var id   = $( this ).data( 'id' );
            var name = $( this ).data( 'name' );
            $( '#reject-student-id' ).val( id );
            $( '#reject-student-name' ).text( name );
            $( '#reject-reason' ).val( '' );
            $( '#reject-notice' ).hide();
            openOverlayModal( 'modal-reject' );
        } );

        // Modal Rechazar: confirmar
        $( document ).on( 'click', '#btn-do-reject', function () {
            var studentId = $( '#reject-student-id' ).val();
            var reason    = $( '#reject-reason' ).val();
            $( this ).prop( 'disabled', true ).text( '⏳…' );
            Students.ajax( 'aura_students_reject', { student_id: studentId, reason: reason }, function () {
                $( '#btn-do-reject' ).prop( 'disabled', false ).text( '❌ Confirmar rechazo' );
                Students.notify( 'Postulación rechazada.' );
                closeOverlayModal( 'modal-reject' );
                loadApplicants( applicantPage );
            }, function () {
                $( '#btn-do-reject' ).prop( 'disabled', false ).text( '❌ Confirmar rechazo' );
            } );
        } );

        // Modal Editar Inscripción: abrir
        $( document ).on( 'click', '.btn-edit-enrollment', function () {
            var id     = $( this ).data( 'id' );
            var name   = $( this ).data( 'student' );
            var course = $( this ).data( 'course' );
            $( '#edit-enrollment-id' ).val( id );
            $( '#edit-enrollment-student' ).text( name );
            $( '#edit-enrollment-course' ).text( course );
            Students.ajax( 'aura_students_get_enrollment', { enrollment_id: id }, function ( d ) {
                var e = d.enrollment || d;
                $( '#edit-scholarship-type' ).val( e.scholarship_type || 'none' );
                $( '#edit-scholarship-pct' ).val( e.scholarship_pct || 0 );
                $( '#edit-scholarship-sponsor' ).val( e.scholarship_sponsor || '' );
            } );
            openOverlayModal( 'modal-edit-enrollment' );
        } );

        // Graduar
        $( document ).on( 'click', '.btn-graduate', function () {
            var id = $( this ).data( 'id' );
            if ( ! Students.confirm( '¿Confirmas graduar a este estudiante?' ) ) { return; }
            Students.ajax( 'aura_students_graduate', { enrollment_id: id }, function () {
                Students.notify( '🎓 Estudiante graduado correctamente.' );
                loadEnrollments( enrollmentPage );
            } );
        } );
    }

    // ================================================================
    // BECAS
    // ================================================================

    var becaPage    = 1;
    var becaFilters = { only_with_beca: 1 };

    function loadScholarships( page ) {
        becaPage = page || 1;
        var $tbody = $( '#becas-tbody' );
        $tbody.html( '<tr><td colspan="11" class="aura-loading" style="text-align:center;padding:20px;"><span class="spinner is-active" style="float:none;"></span> Cargando…</td></tr>' );
        Students.ajax( 'aura_students_list_scholarships', Object.assign( { page: becaPage, per_page: 20 }, becaFilters ), function ( data ) {
            var items = data.items || data.enrollments || [];
            if ( items.length === 0 ) {
                $tbody.html( '<tr><td colspan="11" style="text-align:center;padding:20px;color:#6b7280;">— Sin resultados —</td></tr>' );
                $( '#becas-pagination' ).hide();
                return;
            }
            var schLabel = { none: '—', internal: 'Interna', external: 'Externa' };
            var html = '';
            items.forEach( function ( e ) {
                var baseCost   = parseFloat( e.base_cost || 0 );
                var netCost    = parseFloat( e.net_cost || 0 );
                var totalPaid  = parseFloat( e.total_paid || 0 );
                var discount   = baseCost - netCost;
                var balance    = netCost - totalPaid;
                var schType    = e.scholarship_type || 'none';
                var pctBadge   = e.scholarship_pct > 0 ? '<span class="aura-badge badge-pct">' + e.scholarship_pct + '%</span>' : '—';
                var typeBadge  = '<span class="aura-badge badge-' + schType + '">' + escHtml( schLabel[ schType ] || schType ) + '</span>';
                html += '<tr>' +
                    '<td><strong>' + escHtml( e.student_name ) + '</strong></td>' +
                    '<td>' + escHtml( e.course_name ) + '<br><small>' + escHtml( e.area_name || '' ) + '</small></td>' +
                    '<td class="col-center">' + pctBadge + '</td>' +
                    '<td class="col-center">' + typeBadge + '</td>' +
                    '<td class="col-right">$' + fmtMoney( baseCost ) + '</td>' +
                    '<td class="col-right text-red">−$' + fmtMoney( discount ) + '</td>' +
                    '<td class="col-right">$' + fmtMoney( netCost ) + '</td>' +
                    '<td class="col-right text-green">$' + fmtMoney( totalPaid ) + '</td>' +
                    '<td class="col-right ' + ( balance > 0.009 ? 'text-red' : 'text-green' ) + '">$' + fmtMoney( balance ) + '</td>' +
                    '<td>' + escHtml( e.scholarship_sponsor || '—' ) + '</td>' +
                    '<td class="col-center"><button class="button button-small btn-edit-scholarship" data-id="' + e.id + '">✏️ Beca</button></td>' +
                    '</tr>';
            } );
            $tbody.html( html );
            renderPagination( $( '#becas-pagination' ), becaPage, data.total || 0, 20, loadScholarships );
        } );
    }

    function updateSchCalc() {
        var base     = parseFloat( $( '#sch-base-cost' ).val() ) || 0;
        var pct      = parseFloat( $( '#sch-pct' ).val() ) || 0;
        var discount = base * pct / 100;
        var net      = base - discount;
        var show     = pct > 0;
        $( '#sch-calc-preview' ).toggle( show );
        if ( show ) {
            $( '#calc-base' ).text( '$' + fmtMoney( base ) );
            $( '#calc-discount' ).text( '$' + fmtMoney( discount ) );
            $( '#calc-net' ).html( '<strong>$' + fmtMoney( net ) + '</strong>' );
        }
        $( '#sch-installments-warning' ).toggle( show && $( '#sch-payment-scheme' ).val() === 'installments' );
    }

    function openScholarshipModal( enrollmentId ) {
        Students.ajax( 'aura_students_get_enrollment_for_scholarship', { enrollment_id: enrollmentId }, function ( data ) {
            var e = data.enrollment || data;
            $( '#sch-enrollment-id' ).val( e.id );
            $( '#sch-base-cost' ).val( e.base_cost );
            $( '#sch-payment-scheme' ).val( e.payment_scheme );
            $( '#sch-info-student' ).val( e.student_name );
            $( '#sch-info-course' ).val( e.course_name );
            $( '#sch-info-base' ).val( '$' + fmtMoney( e.base_cost ) + ' ' + ( e.currency || '' ) );
            $( '#sch-type' ).val( e.scholarship_type || 'none' );
            $( '#sch-pct' ).val( e.scholarship_pct || 0 );
            $( '#sch-sponsor' ).val( e.scholarship_sponsor || '' );
            $( '#sch-notes' ).val( '' );
            $( '#modal-sch-title' ).text( ( e.scholarship_type && e.scholarship_type !== 'none' ) ? 'Editar Beca' : 'Asignar Beca' );
            $( '#row-sponsor' ).toggle( e.scholarship_type === 'external' );
            updateSchCalc();
            openAuraModal( 'modal-scholarship' );
        } );
    }

    function saveScholarship() {
        var enrollmentId = $( '#sch-enrollment-id' ).val();
        var $btn = $( '#btn-save-scholarship' );
        $btn.prop( 'disabled', true ).text( '⏳ Guardando…' );
        Students.ajax( 'aura_students_assign_scholarship', {
            enrollment_id:       enrollmentId,
            scholarship_type:    $( '#sch-type' ).val(),
            scholarship_pct:     $( '#sch-pct' ).val(),
            scholarship_sponsor: $( '#sch-sponsor' ).val(),
            scholarship_notes:   $( '#sch-notes' ).val(),
        }, function () {
            $btn.prop( 'disabled', false ).text( '💾 Guardar beca' );
            Students.notify( '✅ Beca guardada correctamente.' );
            closeAuraModal( 'modal-scholarship' );
            loadScholarships( becaPage );
        }, function () {
            $btn.prop( 'disabled', false ).text( '💾 Guardar beca' );
        } );
    }

    function initScholarships() {
        loadScholarships( 1 );

        var schFilterTimer = null;
        $( '#sch-search, #sch-filter-type, #sch-filter-course, #sch-filter-pct' ).on( 'input change', function () {
            clearTimeout( schFilterTimer );
            schFilterTimer = setTimeout( function () {
                becaFilters = {
                    search:         $( '#sch-search' ).val().trim(),
                    type:           $( '#sch-filter-type' ).val(),
                    course_id:      $( '#sch-filter-course' ).val(),
                    min_pct:        $( '#sch-filter-pct' ).val(),
                    only_with_beca: $( '#btn-only-with-beca' ).hasClass( 'active' ) ? 1 : 0,
                };
                loadScholarships( 1 );
            }, 400 );
        } );

        $( '#btn-only-with-beca' ).on( 'click', function () {
            $( this ).addClass( 'active' );
            $( '#btn-show-all' ).removeClass( 'active' );
            becaFilters.only_with_beca = 1;
            loadScholarships( 1 );
        } );
        $( '#btn-show-all' ).on( 'click', function () {
            $( this ).addClass( 'active' );
            $( '#btn-only-with-beca' ).removeClass( 'active' );
            becaFilters.only_with_beca = 0;
            loadScholarships( 1 );
        } );
        $( '#btn-reload-scholarships' ).on( 'click', function () { loadScholarships( becaPage ); } );

        $( document ).on( 'click', '.btn-edit-scholarship', function () {
            openScholarshipModal( $( this ).data( 'id' ) );
        } );

        $( document ).on( 'change', '#sch-type', function () {
            $( '#row-sponsor' ).toggle( $( this ).val() === 'external' );
            updateSchCalc();
        } );
        $( document ).on( 'input', '#sch-pct', updateSchCalc );
        $( document ).on( 'click', '.sch-pct-btn', function () {
            $( '#sch-pct' ).val( $( this ).data( 'pct' ) );
            updateSchCalc();
        } );

        $( document ).on( 'click', '#btn-save-scholarship', saveScholarship );
        $( document ).on( 'click', '#btn-close-sch-modal, #btn-cancel-sch', function () {
            closeAuraModal( 'modal-scholarship' );
        } );
        $( document ).on( 'click', '#modal-scholarship .aura-modal-overlay', function () {
            closeAuraModal( 'modal-scholarship' );
        } );
    }

    // ================================================================
    // REPORTES
    // ================================================================

    var NO_PDF_TYPES   = [ 'income_by_area', 'income_projection', 'scholarships' ];
    var lastReportArgs = null;

    function buildReportTable( data ) {
        if ( ! data.rows || data.rows.length === 0 ) {
            return '<p style="text-align:center;color:#6b7280;padding:20px;">— Sin datos para los filtros seleccionados —</p>';
        }
        var cols = Object.keys( data.rows[ 0 ] );
        var html = '<table class="wp-list-table widefat fixed striped" style="margin-top:12px;"><thead><tr>';
        cols.forEach( function ( c ) { html += '<th>' + escHtml( c ) + '</th>'; } );
        html += '</tr></thead><tbody>';
        data.rows.forEach( function ( row ) {
            html += '<tr>';
            cols.forEach( function ( c ) { html += '<td>' + escHtml( String( row[ c ] !== null && row[ c ] !== undefined ? row[ c ] : '—' ) ) + '</td>'; } );
            html += '</tr>';
        } );
        html += '</tbody></table>';
        if ( data.summary && Object.keys( data.summary ).length > 0 ) {
            html += '<div style="margin-top:12px;background:#f9f5ff;border:1px solid #e9d5ff;border-radius:6px;padding:12px;">';
            Object.keys( data.summary ).forEach( function ( k ) {
                html += '<span style="margin-right:16px;"><strong>' + escHtml( k ) + ':</strong> ' + escHtml( String( data.summary[ k ] ) ) + '</span>';
            } );
            html += '</div>';
        }
        return html;
    }

    function generateReport() {
        var params = {
            type:         $( '#rep-type' ).val(),
            date_start:   $( '#rep-start' ).val(),
            date_end:     $( '#rep-end' ).val(),
            area_id:      $( '#rep-area' ).val(),
            course_id:    $( '#rep-course' ).val(),
            profile_type: $( '#rep-profile-type' ).val(),
            status:       $( '#rep-status' ).val(),
        };
        lastReportArgs = params;
        var $btn = $( '#btn-st-generate' );
        $btn.prop( 'disabled', true ).text( '⏳ Generando…' );

        var $results = $( '#st-report-results' );
        if ( ! $results.length ) {
            $results = $( '<div id="st-report-results" style="margin-top:20px;"></div>' );
            $( '#aura-students-report-form' ).after( $results );
        }
        $results.html( '<div style="text-align:center;padding:30px;"><span class="spinner is-active" style="float:none;margin:0 auto;display:block;"></span><p style="color:#6b7280;margin-top:8px;">Generando reporte…</p></div>' );

        Students.ajax( 'aura_students_generate_report', params, function ( data ) {
            $btn.prop( 'disabled', false ).text( '🔍 Generar Reporte' );
            if ( data.html ) {
                $results.html( data.html );
            } else {
                $results.html( buildReportTable( data ) );
            }
            $( '#st-export-card' ).show();
            $( '#btn-st-pdf' ).toggle( NO_PDF_TYPES.indexOf( params.type ) === -1 );
        }, function () {
            $btn.prop( 'disabled', false ).text( '🔍 Generar Reporte' );
            $results.html( '' );
        } );
    }

    function exportReport( format ) {
        if ( ! lastReportArgs ) { return; }
        var action = format === 'excel' ? 'aura_students_export_excel' : 'aura_students_export_pdf';
        var label  = format === 'excel' ? '📊 Excel' : '📄 PDF';
        var $btn   = format === 'excel' ? $( '#btn-st-excel' ) : $( '#btn-st-pdf' );
        $btn.prop( 'disabled', true ).text( '⏳ Exportando…' );
        Students.ajax( action, lastReportArgs, function ( data ) {
            $btn.prop( 'disabled', false ).text( label );
            if ( data.download_url ) {
                window.open( data.download_url, '_blank' );
            }
        }, function () {
            $btn.prop( 'disabled', false ).text( label );
        } );
    }

    function initReports() {
        $( '#btn-st-generate' ).prop( 'disabled', true );
        $( '#st-export-card' ).hide();

        $( '#rep-type' ).on( 'change', function () {
            var type = $( this ).val();
            $( '#btn-st-generate' ).prop( 'disabled', ! type );
            $( '#st-export-card' ).hide();
        } );

        $( document ).on( 'click', '.aura-preset-btn', function () {
            var preset = $( this ).data( 'preset' );
            var today  = new Date();
            var start, end;
            switch ( preset ) {
                case 'this_month':
                    start = new Date( today.getFullYear(), today.getMonth(), 1 );
                    end   = new Date( today.getFullYear(), today.getMonth() + 1, 0 );
                    break;
                case 'last_month':
                    start = new Date( today.getFullYear(), today.getMonth() - 1, 1 );
                    end   = new Date( today.getFullYear(), today.getMonth(), 0 );
                    break;
                case 'this_year':
                    start = new Date( today.getFullYear(), 0, 1 );
                    end   = new Date( today.getFullYear(), 11, 31 );
                    break;
                case 'last_year':
                    start = new Date( today.getFullYear() - 1, 0, 1 );
                    end   = new Date( today.getFullYear() - 1, 11, 31 );
                    break;
            }
            if ( start && end ) {
                $( '#rep-start' ).val( fmtDate( start ) );
                $( '#rep-end' ).val( fmtDate( end ) );
            }
        } );

        $( '#btn-st-generate' ).on( 'click', generateReport );
        $( '#btn-st-excel' ).on( 'click', function () { exportReport( 'excel' ); } );
        $( '#btn-st-pdf' ).on( 'click', function () { exportReport( 'pdf' ); } );
    }

    // ================================================================
    // CONFIGURACIÓN
    // ================================================================

    function saveSettings() {
        var $btn     = $( '#btn-st-save-settings' );
        var $spinner = $( '#st-settings-spinner' );
        $btn.prop( 'disabled', true );
        $spinner.show();

        var data       = {};
        var formFields = [];

        $( '#aura-students-settings-app' ).find( 'input, select, textarea' ).each( function () {
            var $el  = $( this );
            var name = $el.attr( 'name' );
            if ( ! name ) { return; }
            if ( name === 'enrollment_form_fields[]' ) {
                if ( $el.is( ':checked' ) ) { formFields.push( $el.val() ); }
            } else if ( $el.attr( 'type' ) === 'checkbox' ) {
                data[ name ] = $el.is( ':checked' ) ? '1' : '0';
            } else {
                data[ name ] = $el.val();
            }
        } );
        data.enrollment_form_fields = formFields;

        Students.ajax( 'aura_students_save_settings', data, function () {
            $btn.prop( 'disabled', false );
            $spinner.hide();
            var $notice = $( '#st-settings-notice' );
            if ( $notice.length ) {
                $( '#st-settings-notice-msg' ).text( '✅ Configuración guardada correctamente.' );
                $notice.show();
                setTimeout( function () { $notice.fadeOut(); }, 4000 );
            } else {
                Students.notify( '✅ Configuración guardada correctamente.' );
            }
        }, function () {
            $btn.prop( 'disabled', false );
            $spinner.hide();
        } );
    }

    function initSettings() {
        // Tabs
        $( '.aura-tab-btn' ).on( 'click', function () {
            var tab = $( this ).data( 'tab' );
            $( '.aura-tab-btn' ).removeClass( 'active' );
            $( this ).addClass( 'active' );
            $( '.aura-tab-panel' ).removeClass( 'active' );
            $( '#tab-' + tab ).addClass( 'active' );
        } );

        // Sincronizar moneda: General ↔ Finance
        $( '#default-currency' ).on( 'change', function () {
            $( '#default-currency-finance' ).val( $( this ).val() );
        } );
        $( '#default-currency-finance' ).on( 'change', function () {
            $( '#default-currency' ).val( $( this ).val() );
        } );

        $( '#btn-st-save-settings' ).on( 'click', saveSettings );
    }

    // ================================================================
    // INICIALIZACIÓN
    // ================================================================

    $( document ).ready( function () {
        if ( $( '.aura-students-dashboard' ).length )        { initDashboard(); }
        if ( $( '.aura-students-courses' ).length )          { initCourses(); }
        if ( $( '.aura-students-list' ).length )             { initStudentsList(); }
        if ( $( '.aura-students-enrollments-wrap' ).length ) { initEnrollments(); }
        if ( $( '.aura-students-becas' ).length )            { initScholarships(); }
        if ( $( '#aura-students-reports-app' ).length )      { initReports(); }
        if ( $( '#aura-students-settings-app' ).length )     { initSettings(); }
    } );

    // Exponer API pública
    window.AuraStudents = Students;

} )( jQuery );
