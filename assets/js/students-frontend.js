/**
 * Portal Frontend del Estudiante — Scripts
 * AURA Business Suite
 *
 * Cubre:
 *  - [aura_student_login]            — Login AJAX desde el portal
 *  - [aura_student_portal]           — Mis Cursos / Mis Pagos / Mis Certificados
 *  - [aura_enrollment_form]          — Formulario público de inscripción
 *  - [aura_student_paz_salvo_check]  — Consulta pública de paz y salvo
 *
 * Objeto localizado: auraStudentsFrontend { ajaxUrl, nonce, strings }
 */

/* global jQuery, auraStudentsFrontend */

( function ( $ ) {
    'use strict';

    var CFG   = auraStudentsFrontend;
    var NONCE = CFG.nonce;
    var AJAX  = CFG.ajaxUrl;
    var STR   = CFG.strings;

    // ================================================================
    // UTILIDADES
    // ================================================================

    function escHtml( str ) {
        return String( str || '' )
            .replace( /&/g,  '&amp;'  )
            .replace( /</g,  '&lt;'   )
            .replace( />/g,  '&gt;'   )
            .replace( /"/g,  '&quot;' )
            .replace( /'/g,  '&#39;'  );
    }

    function fmtMoney( val ) {
        return '$' + parseFloat( val || 0 )
            .toFixed( 2 )
            .replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
    }

    function statusBadge( status ) {
        var map = {
            unpaid : '<span class="aura-status-badge status-gray">⬜ '   + ( STR.pay_unpaid  || 'Sin pagar'  ) + '</span>',
            partial: '<span class="aura-status-badge status-orange">🔶 ' + ( STR.pay_partial || 'Parcial'    ) + '</span>',
            paid   : '<span class="aura-status-badge status-green">✅ '  + ( STR.pay_paid    || 'Pagado'     ) + '</span>',
            overdue: '<span class="aura-status-badge status-red">🔴 '    + ( STR.pay_overdue || 'Vencido'    ) + '</span>',
            pending: '<span class="aura-status-badge status-blue">⏳ '   + ( STR.pay_pending || 'Pendiente'  ) + '</span>',
        };
        return map[ status ] || escHtml( status );
    }

    function enrollmentStatusLabel( s ) {
        var map = {
            active   : '<span class="aura-status-badge status-green">'  + ( STR.enrl_active    || 'Activo'     ) + '</span>',
            completed: '<span class="aura-status-badge status-gold">'   + ( STR.enrl_completed || 'Completado' ) + '</span>',
            pending  : '<span class="aura-status-badge status-blue">'   + ( STR.enrl_pending   || 'Pendiente'  ) + '</span>',
            withdrawn: '<span class="aura-status-badge status-orange">' + ( STR.enrl_withdrawn || 'Retirado'   ) + '</span>',
            suspended: '<span class="aura-status-badge status-red">'    + ( STR.enrl_suspended || 'Suspendido' ) + '</span>',
        };
        return map[ s ] || escHtml( s );
    }

    function progressBar( paid, net ) {
        var total = parseFloat( net ) || 0;
        var done  = parseFloat( paid ) || 0;
        var pct   = total > 0 ? Math.min( 100, Math.round( done / total * 100 ) ) : 0;
        var cls   = pct >= 100 ? 'bar-green' : ( pct > 0 ? 'bar-blue' : 'bar-gray' );
        return '<div class="aura-progress-wrap">'
             + '<div class="aura-progress-bar ' + cls + '" style="width:' + pct + '%"></div>'
             + '</div>'
             + '<small>' + pct + '% ' + ( STR.covered || 'cubierto' ) + '</small>';
    }

    // ================================================================
    // LOGIN
    // ================================================================

    function initLogin() {

        // Mostrar/ocultar contraseña
        $( document ).on( 'click', '.aura-toggle-pass', function () {
            var $inp = $( this ).siblings( 'input' );
            $inp.attr( 'type', $inp.attr( 'type' ) === 'password' ? 'text' : 'password' );
        } );

        // Submit AJAX
        $( '#aura-login-form' ).on( 'submit', function ( e ) {
            e.preventDefault();

            var $btn    = $( '#aura-login-btn' );
            var $notice = $( '#aura-login-notice' );

            $btn.prop( 'disabled', true ).text( STR.checking || 'Verificando…' );
            $notice.hide().removeClass( 'aura-notice-error aura-notice-ok' );

            $.post( AJAX, {
                action  : 'aura_students_ajax_login',
                nonce   : NONCE,
                username: $( '#aura-login-user' ).val().trim(),
                password: $( '#aura-login-pass' ).val(),
                remember: $( '#aura-remember' ).is( ':checked' ) ? 1 : 0,
                redirect: $( 'input[name="redirect"]' ).val() || '',
            }, function ( res ) {
                if ( res.success ) {
                    $notice.addClass( 'aura-notice-ok' ).text( STR.login_ok ).show();
                    setTimeout( function () { window.location.href = res.data.redirect; }, 800 );
                } else {
                    $btn.prop( 'disabled', false ).text( STR.login_btn || 'Ingresar al portal' );
                    $notice.addClass( 'aura-notice-error' )
                           .text( ( res.data && res.data.message ) || STR.error )
                           .show();
                }
            } ).fail( function () {
                $btn.prop( 'disabled', false ).text( STR.login_btn || 'Ingresar al portal' );
                $notice.addClass( 'aura-notice-error' ).text( STR.error ).show();
            } );
        } );
    }

    // ================================================================
    // PORTAL: MIS CURSOS
    // ================================================================

    function loadCourses() {
        $( '#aura-courses-loading' ).show();
        $( '#aura-courses-container, #aura-courses-empty' ).hide();

        $.post( AJAX, {
            action: 'aura_student_portal_my_courses',
            nonce : NONCE,
        }, function ( res ) {
            $( '#aura-courses-loading' ).hide();

            if ( ! res.success || ! res.data.total ) {
                $( '#aura-courses-empty' ).show();
                return;
            }

            var html = '';
            res.data.areas.forEach( function ( area ) {
                html += '<div class="aura-area-block">'
                     +  '<h4 class="aura-area-title">📂 ' + escHtml( area.area_name ) + '</h4>';

                area.courses.forEach( function ( c ) {
                    var enrStatus = enrollmentStatusLabel( c.enrollment_status );

                    html += '<div class="aura-course-card">'
                         +  '<div class="aura-course-card-header">'
                         +  '<span class="aura-course-name">' + escHtml( c.course_name ) + '</span>'
                         +  enrStatus
                         +  '</div>'
                         +  '<div class="aura-course-dates">';

                    if ( c.start_date ) {
                        html += '📅 ' + escHtml( c.start_date );
                        if ( c.end_date ) { html += ' → ' + escHtml( c.end_date ); }
                    }
                    html += '</div>';

                    if ( parseFloat( c.net_cost ) > 0 ) {
                        var schInfo = '';
                        if ( c.scholarship_pct > 0 ) {
                            var schType = c.scholarship_type === 'internal'
                                ? ( STR.internal || 'interna' )
                                : ( STR.external || 'externa' );
                            schInfo = '<span class="aura-badge-pct">🎓 ' + c.scholarship_pct + '% '
                                    + ( STR.scholarship || 'beca' ) + ' (' + schType + ')</span> ';
                        }
                        var balance = parseFloat( c.balance_due ) || 0;
                        var balHtml = balance > 0.009
                            ? '<span class="text-red">' + ( STR.balance_lbl || 'Saldo:' ) + ' ' + fmtMoney( c.balance_due ) + '</span>'
                            : '<span class="text-green">✅ ' + ( STR.up_to_date || 'Al día' ) + '</span>';

                        html += '<div class="aura-course-payment">'
                             +  schInfo
                             +  '<span class="aura-cost-net">' + fmtMoney( c.net_cost ) + '</span>'
                             +  progressBar( c.total_paid, c.net_cost )
                             +  '<div class="aura-balance">' + balHtml + '</div>'
                             +  '</div>';
                    }

                    html += '</div>'; // /aura-course-card
                } );

                html += '</div>'; // /aura-area-block
            } );

            $( '#aura-courses-container' ).html( html ).show().data( 'loaded', true );

        } ).fail( function () {
            $( '#aura-courses-loading' ).hide();
            $( '#aura-courses-empty' ).text( STR.err_courses || 'Error al cargar los cursos.' ).show();
        } );
    }

    // ================================================================
    // PORTAL: MIS PAGOS
    // ================================================================

    function loadPayments() {
        $( '#aura-payments-loading' ).show();
        $( '#aura-payments-table, #aura-payments-empty, #aura-pending-section' ).hide();

        $.post( AJAX, {
            action: 'aura_student_portal_my_payments',
            nonce : NONCE,
        }, function ( res ) {
            $( '#aura-payments-loading' ).hide();

            if ( ! res.success ) {
                $( '#aura-payments-empty' ).text( ( res.data && res.data.message ) || STR.error ).show();
                return;
            }

            var d = res.data;

            // KPI summary
            if ( d.summary ) {
                $( '#summary-paid'    ).text( fmtMoney( d.summary.total_paid    ) );
                $( '#summary-balance' ).text( fmtMoney( d.summary.total_balance ) );
                $( '#summary-net'     ).text( fmtMoney( d.summary.total_net     ) );
            }

            // Cuotas pendientes
            if ( d.pending_installments && d.pending_installments.length ) {
                var today = new Date().toISOString().slice( 0, 10 );
                var pRows = '';
                d.pending_installments.forEach( function ( ins ) {
                    var isOverdue = ins.due_date < today;
                    pRows += '<tr class="' + ( isOverdue ? 'row-overdue' : '' ) + '">'
                           + '<td>#' + escHtml( String( ins.installment_num ) ) + '</td>'
                           + '<td>' + escHtml( ins.course_name ) + '</td>'
                           + '<td>' + escHtml( ins.due_date ) + ( isOverdue ? ' 🔴' : '' ) + '</td>'
                           + '<td>' + fmtMoney( ins.expected_amount ) + '</td>'
                           + '<td>' + statusBadge( ins.status ) + '</td>'
                           + '</tr>';
                } );
                $( '#aura-pending-tbody' ).html( pRows );
                $( '#aura-pending-section' ).show();
            }

            // Historial de pagos
            if ( ! d.payments || ! d.payments.length ) {
                $( '#aura-payments-empty' ).show();
                $( '#aura-payments-tbody' ).data( 'loaded', true );
                return;
            }

            var methodLabels = {
                cash    : STR.mth_cash     || 'Efectivo',
                transfer: STR.mth_transfer || 'Transferencia',
                card    : STR.mth_card     || 'Tarjeta',
                check   : STR.mth_check    || 'Cheque',
                other   : STR.mth_other    || 'Otro',
            };

            var rows = '';
            d.payments.forEach( function ( p ) {
                var receipt = p.receipt_url
                    ? '<a href="' + escHtml( p.receipt_url ) + '" target="_blank" class="aura-link">'
                      + ( STR.receipt_view || 'Ver' ) + ' 📄</a>'
                    : '—';

                rows += '<tr>'
                      + '<td>' + escHtml( p.payment_date ) + '</td>'
                      + '<td>' + escHtml( p.course_name   ) + '</td>'
                      + '<td>' + ( p.installment_num ? '#' + escHtml( String( p.installment_num ) ) : '—' ) + '</td>'
                      + '<td><strong>' + fmtMoney( p.amount ) + '</strong></td>'
                      + '<td>' + escHtml( methodLabels[ p.payment_method ] || p.payment_method ) + '</td>'
                      + '<td>' + escHtml( p.reference_number || '—' ) + '</td>'
                      + '<td>' + receipt + '</td>'
                      + '</tr>';
            } );

            $( '#aura-payments-tbody' ).html( rows );
            $( '#aura-payments-table' ).show();
            $( '#aura-payments-tbody' ).data( 'loaded', true );

        } ).fail( function () {
            $( '#aura-payments-loading' ).hide();
            $( '#aura-payments-empty' ).text( STR.err_payments || 'Error al cargar los pagos.' ).show();
        } );
    }

    // ================================================================
    // PORTAL: MIS CERTIFICADOS
    // ================================================================

    function loadCerts() {
        $( '#aura-certs-loading' ).show();
        $( '#aura-certs-container, #aura-certs-empty' ).hide();

        $.post( AJAX, {
            action: 'aura_student_portal_my_certs',
            nonce : NONCE,
        }, function ( res ) {
            $( '#aura-certs-loading' ).hide();

            if ( ! res.success || ! res.data.certificates || ! res.data.certificates.length ) {
                $( '#aura-certs-empty' ).show();
                $( '#aura-certs-container' ).data( 'loaded', true );
                return;
            }

            var html = '<div class="aura-certs-grid">';
            res.data.certificates.forEach( function ( c ) {
                html += '<div class="aura-cert-card">'
                     +  '<div class="aura-cert-icon">🏅</div>'
                     +  '<div class="aura-cert-info">'
                     +  '<strong>' + escHtml( c.course_name ) + '</strong><br/>'
                     +  '<small>' + escHtml( c.area_name || '' ) + '</small><br/>'
                     +  '<small>' + ( STR.cert_issued || 'Emitido:' ) + ' ' + escHtml( c.issued_date ) + '</small>'
                     +  '</div>'
                     +  '<div class="aura-cert-actions">';

                if ( c.certificate_url ) {
                    html += '<a href="' + escHtml( c.certificate_url ) + '" target="_blank" class="aura-btn aura-btn-secondary aura-btn-sm">'
                         +  '📄 ' + ( STR.cert_pdf || 'Descargar PDF' ) + '</a>';
                }
                if ( c.qr_url ) {
                    html += '<a href="' + escHtml( c.qr_url ) + '" target="_blank" class="aura-btn aura-btn-ghost aura-btn-sm">'
                         +  '🔲 ' + ( STR.cert_qr || 'Ver QR' ) + '</a>';
                }

                html += '</div></div>';
            } );
            html += '</div>';

            $( '#aura-certs-container' ).html( html ).show().data( 'loaded', true );

        } ).fail( function () {
            $( '#aura-certs-loading' ).hide();
            $( '#aura-certs-empty' ).text( STR.err_certs || 'Error al cargar los certificados.' ).show();
        } );
    }

    // ================================================================
    // PORTAL: MIS FORMULARIOS
    // ================================================================

    function loadForms() {
        $( '#aura-forms-loading' ).show();
        $( '#aura-forms-container' ).hide();
        $( '#aura-forms-error' ).hide();

        $.post( AJAX, {
            action: 'aura_student_portal_my_forms',
            nonce : NONCE,
        }, function ( res ) {
            $( '#aura-forms-loading' ).hide();

            if ( ! res || ! res.success ) {
                var msg = ( res && res.data && res.data.message ) || ( STR.error || 'Error al cargar formularios.' );
                $( '#aura-forms-error' ).text( msg ).show();
                $( '#aura-forms-container' ).data( 'loaded', true );
                return;
            }

            $( '#aura-forms-container' ).html( res.data.html || '' ).show().data( 'loaded', true );

        } ).fail( function () {
            $( '#aura-forms-loading' ).hide();
            $( '#aura-forms-error' ).text( STR.error || 'Error al cargar formularios.' ).show();
        } );
    }

    // ================================================================
    // PORTAL: TABS (inicialización general)
    // ================================================================

    function initPortal() {
        $( '.aura-portal-tab-btn' ).on( 'click', function () {
            var target = $( this ).data( 'target' );
            $( '.aura-portal-tab-btn' ).removeClass( 'active' );
            $( this ).addClass( 'active' );
            $( '.aura-portal-tab-content' ).hide();
            $( '#aura-tab-' + target ).show();

            // Lazy load por pestaña
            if ( target === 'courses' && ! $( '#aura-courses-container' ).data( 'loaded' ) ) {
                loadCourses();
            } else if ( target === 'payments' && ! $( '#aura-payments-tbody' ).data( 'loaded' ) ) {
                loadPayments();
            } else if ( target === 'certs' && ! $( '#aura-certs-container' ).data( 'loaded' ) ) {
                loadCerts();
            } else if ( target === 'forms' && ! $( '#aura-forms-container' ).data( 'loaded' ) ) {
                loadForms();
            }
        } );

        // Carga inicial: pestaña activa = Mis Cursos
        loadCourses();
    }

    // ================================================================
    // FORMULARIO PÚBLICO DE INSCRIPCIÓN
    // ================================================================

    function initEnrollmentForm() {
        $( '#aura-enrollment-form' ).on( 'submit', function ( e ) {
            e.preventDefault();

            // Honeypot
            if ( $( 'input[name="hp_field"]' ).val() !== '' ) { return; }

            var $btn    = $( '#aura-enroll-btn' );
            var $notice = $( '#aura-enroll-notice' );

            $btn.prop( 'disabled', true ).text( STR.submitting || 'Enviando…' );
            $notice.hide().removeClass( 'aura-notice-ok aura-notice-error' );

            var formData = $( this ).serializeArray();
            formData.push( { name: 'action', value: 'aura_students_submit_enrollment' } );
            formData.push( { name: 'nonce',  value: NONCE } );

            $.post( AJAX, formData, function ( res ) {
                $btn.prop( 'disabled', false ).text( STR.submit_btn || 'Enviar solicitud' );

                if ( res.success ) {
                    $( '#aura-enrollment-form' ).fadeOut( 300, function () {
                        $( '#aura-enroll-success' ).fadeIn( 400 );
                    } );
                } else {
                    $notice.addClass( 'aura-notice-error' )
                           .text( ( res.data && res.data.message ) || STR.submit_error )
                           .show();
                    $( 'html, body' ).animate( { scrollTop: $notice.offset().top - 80 }, 400 );
                }
            } ).fail( function () {
                $btn.prop( 'disabled', false ).text( STR.submit_btn || 'Enviar solicitud' );
                $notice.addClass( 'aura-notice-error' ).text( STR.error ).show();
            } );
        } );
    }

    // ================================================================
    // VERIFICACIÓN PÚBLICA DE PAZ Y SALVO
    // ================================================================

    function initPazSalvoCheck() {
        $( '#aura-ps-btn' ).on( 'click', function () {
            var email = $( '#aura-ps-email' ).val().trim();
            if ( ! email ) { alert( STR.ps_enter_email || 'Ingresa tu correo.' ); return; }

            $( this ).prop( 'disabled', true ).text( STR.ps_checking || 'Consultando…' );

            $.post( AJAX, {
                action: 'aura_student_paz_salvo_public',
                nonce : NONCE,
                email : email,
            }, function ( res ) {
                $( '#aura-ps-btn' ).prop( 'disabled', false ).text( STR.ps_check_btn || 'Consultar' );
                var html;
                if ( res.success ) {
                    var d    = res.data;
                    var icon = d.is_clear ? '✅' : '❌';
                    var cls  = d.is_clear ? 'aura-ps-ok' : 'aura-ps-debt';
                    var msg  = d.is_clear
                        ? ( STR.ps_ok   || 'Estás al día con tus pagos.' )
                        : ( STR.ps_debt || 'Tienes pagos pendientes.'    );
                    html = '<div class="aura-ps-card ' + cls + '">' + icon + ' ' + msg + '</div>';
                } else {
                    html = '<div class="aura-ps-card aura-ps-not-found">' + ( STR.ps_not_found || 'Correo no encontrado.' ) + '</div>';
                }
                $( '#aura-ps-result' ).html( html ).show();
            } ).fail( function () {
                $( '#aura-ps-btn' ).prop( 'disabled', false ).text( STR.ps_check_btn || 'Consultar' );
            } );
        } );
    }

    // ================================================================
    // INICIALIZACIÓN
    // ================================================================

    $( document ).ready( function () {
        if ( $( '#aura-login-form'      ).length ) { initLogin();          }
        if ( $( '#aura-student-portal'  ).length ) { initPortal();         }
        if ( $( '#aura-enrollment-form' ).length ) { initEnrollmentForm(); }
        if ( $( '.aura-paz-salvo-check' ).length ) { initPazSalvoCheck();  }
    } );

} )( jQuery );
