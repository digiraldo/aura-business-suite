/**
 * Javascript Principal del Módulo de Calendario y Horarios Académicos
 * Aura Business Suite
 */

(function($) {
    'use strict';

    var calendar = null;
    var currentDetailEvent = null;

    // ─────────────────────────────────────────────────────────────
    // UTILIDADES
    // ─────────────────────────────────────────────────────────────

    function showToast(message, type) {
        type = type || 'success';
        $('.aura-cal-toast').remove();

        var icon = type === 'success' ? '✅' : '⚠️';
        var toast = $('<div class="aura-cal-toast toast-' + type + '">' + icon + ' ' + message + '</div>');
        $('body').append(toast);

        setTimeout(function() {
            toast.fadeOut(300, function() {
                toast.remove();
            });
        }, 4000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function openModal(selector) {
        var $modal = $(selector);
        if (!$modal.length) return;
        $modal.removeClass('is-hidden')
              .addClass('active is-active')
              .attr('style', 'display: flex !important; opacity: 1 !important; visibility: visible !important; pointer-events: auto !important;');
        $('body').addClass('aura-modal-open');
    }

    function closeModal(selector) {
        var $modal = $(selector);
        if (!$modal.length) return;
        $modal.removeClass('active is-active')
              .addClass('is-hidden')
              .attr('style', 'display: none !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important;');
        if ($('.aura-modal-overlay.active:visible, .aura-modal-overlay.is-active:visible').length === 0) {
            $('body').removeClass('aura-modal-open');
        }
    }

    // Cerrar modales con botones de clase, clic fuera o tecla Escape
    $(document).on('click', '[data-close-modal], .aura-modal-close', function(e) {
        e.preventDefault();
        var target = $(this).data('close-modal');
        if (target) {
            closeModal(target);
        } else {
            closeModal($(this).closest('.aura-modal-overlay'));
        }
    });

    // Evitar que seleccionar texto o arrastrar el cursor dentro de un input/modal cierre el modal al soltar el ratón en el overlay
    var isBackdropMouseDown = false;

    $(document).on('mousedown', '.aura-modal-overlay', function(e) {
        if (e.target === this) {
            isBackdropMouseDown = true;
        } else {
            isBackdropMouseDown = false;
        }
    });

    $(document).on('click', '.aura-modal-overlay', function(e) {
        if (isBackdropMouseDown && e.target === this) {
            closeModal(this);
        }
        isBackdropMouseDown = false;
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('.aura-modal-overlay');
        }
    });

    // ─────────────────────────────────────────────────────────────
    // 1. INICIALIZACIÓN DE FULLCALENDAR
    // ─────────────────────────────────────────────────────────────

    function initFullCalendar() {
        var calEl = document.getElementById('aura-main-calendar');
        if (!calEl || typeof FullCalendar === 'undefined') {
            return;
        }

        calendar = new FullCalendar.Calendar(calEl, {
            initialView: 'timeGridWeek',
            locale: 'es',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            buttonText: {
                today: auraCalData.i18n.today,
                month: auraCalData.i18n.month,
                week: auraCalData.i18n.week,
                day: auraCalData.i18n.day,
                list: auraCalData.i18n.list
            },
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
            allDaySlot: false,
            nowIndicator: true,
            editable: !!auraCalData.user_can_edit,
            selectable: !!auraCalData.user_can_edit,
            selectMirror: true,

            // Renderizado personalizado de la tarjeta de evento con micro-avatar del docente y badge de líder
            eventContent: function(arg) {
                var p = arg.event.extendedProps || {};
                var title = p.raw_title || arg.event.title;
                var timeText = arg.timeText;
                
                var avatarImg = '';
                if (p.primary_avatar) {
                    avatarImg = '<img src="' + escapeHtml(p.primary_avatar) + '" alt="' + escapeHtml(p.primary_name || '') + '" title="' + escapeHtml(p.primary_name || '') + '" style="width:18px;height:18px;border-radius:50%;object-fit:cover;flex-shrink:0;border:1px solid rgba(255,255,255,0.7);vertical-align:middle;display:inline-block;" onerror="this.style.display=\'none\';" />';
                }

                var leadersBadge = '';
                if (p.student_leaders && p.student_leaders.length > 0) {
                    var lCount = p.student_leaders.length;
                    var firstLeader = p.student_leaders[0];
                    leadersBadge = '<span class="aura-event-leader-tag" title="' + escapeHtml(firstLeader.name + ' (' + firstLeader.role_label + ')') + (lCount > 1 ? ' +' + (lCount - 1) : '') + '" style="font-size:10px;background:rgba(255,255,255,0.28);color:inherit;border-radius:8px;padding:1px 5px;margin-left:auto;white-space:nowrap;display:inline-flex;align-items:center;gap:3px;font-weight:600;">⭐ ' + escapeHtml(firstLeader.name.split(' ')[0]) + '</span>';
                }

                var html = '<div class="fc-event-custom-row" style="display:flex;align-items:center;gap:5px;width:100%;overflow:hidden;padding:1px 2px;">' +
                    avatarImg +
                    (timeText ? '<span class="fc-event-time" style="font-weight:700;font-size:11px;flex-shrink:0;">' + escapeHtml(timeText) + '</span>' : '') +
                    '<span class="fc-event-title" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;font-weight:600;font-size:12px;">' + escapeHtml(title) + '</span>' +
                    leadersBadge +
                '</div>';

                return { html: html };
            },

            // Carga de eventos con filtros
            events: function(info, successCallback, failureCallback) {
                $.post(auraCalData.ajax_url, {
                    action: 'aura_cal_get_events',
                    nonce: auraCalData.nonce,
                    start: info.startStr,
                    end: info.endStr,
                    program_id: $('#filter-program').val() || 0,
                    subject_id: $('#filter-subject').val() || 0,
                    event_type: $('#filter-event-type').val() || ''
                }, function(res) {
                    if (res && res.success) {
                        successCallback(res.data.events || []);
                    } else {
                        failureCallback();
                    }
                }).fail(failureCallback);
            },

            // Tooltip HTML enriquecido al pasar el cursor
            eventMouseEnter: function(info) {
                showEventTooltip(info.event, info.el, info.jsEvent);
            },
            eventMouseLeave: function(info) {
                hideEventTooltip();
            },

            // Clic en fecha para agendar
            select: function(info) {
                if (!auraCalData.user_can_edit) return;
                openEventEditor({
                    start: info.startStr,
                    end: info.endStr
                });
            },

            // Clic en evento para ver detalles
            eventClick: function(info) {
                hideEventTooltip();
                openEventDetail(info.event);
            },

            // Drag & Drop de evento
            eventDrop: function(info) {
                if (!auraCalData.user_can_edit) return;
                hideEventTooltip();
                updateEventDates(info.event);
            },

            // Redimensionamiento de evento
            eventResize: function(info) {
                if (!auraCalData.user_can_edit) return;
                hideEventTooltip();
                updateEventDates(info.event);
            }
        });

        calendar.render();
    }

    // ─────────────────────────────────────────────────────────────
    // TOOLTIPS FLOTANTES ENRIQUECIDOS PARA EVENTOS
    // ─────────────────────────────────────────────────────────────

    function showEventTooltip(event, el, jsEvent) {
        var p = event.extendedProps || {};
        var $tt = $('#aura-cal-event-tooltip');
        if (!$tt.length) {
            $tt = $('<div id="aura-cal-event-tooltip" class="aura-cal-floating-tooltip"></div>');
            $('body').append($tt);
        }

        var typeLabels = {
            'class': '📖 Clase Regular',
            'exam': '📝 Examen / Evaluación',
            'workshop': '🔬 Taller / Práctica',
            'activity': '🎯 Actividad',
            'break': '☕ Receso',
            'other': '📍 Evento'
        };
        var typeLabel = typeLabels[p.event_type] || '📖 ' + (p.event_type || 'Clase');

        var statusBadges = {
            'scheduled': '<span class="adp-badge badge-indigo" style="font-size:10px;padding:2px 7px;">Programado</span>',
            'completed': '<span class="adp-badge badge-emerald" style="font-size:10px;padding:2px 7px;">Completado</span>',
            'cancelled': '<span class="adp-badge" style="font-size:10px;padding:2px 7px;background:#ef4444;color:#fff;">Cancelado</span>',
            'postponed': '<span class="adp-badge badge-amber" style="font-size:10px;padding:2px 7px;">Pospuesto</span>'
        };
        var statusBadge = statusBadges[p.status] || '';

        var gcalChip = (p.gcal_sync_status === 'synced') 
            ? '<span class="adp-badge badge-emerald" style="font-size:10px;padding:2px 7px;" title="Sincronizado con Google Calendar">✓ GCal</span>' 
            : '';

        var startStr = event.start ? event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
        var endStr = event.end ? event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
        var timeRange = startStr ? (startStr + (endStr ? ' — ' + endStr : '')) : '';
        var dateStr = event.start ? event.start.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' }) : '';

        var teachersHtml = '';
        if (p.instructors && p.instructors.length) {
            var instChips = p.instructors.map(function(inst) {
                var avHtml = inst.avatar ? '<img src="' + escapeHtml(inst.avatar) + '" style="width:16px;height:16px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:4px;" />' : '';
                return '<span style="display:inline-flex;align-items:center;margin-right:6px;">' + avHtml + escapeHtml(inst.name) + '</span>';
            }).join(' ');
            teachersHtml = '<div class="tooltip-meta-row" style="align-items:flex-start;"><strong>👨‍🏫 Docente:</strong> <div style="display:flex;flex-wrap:wrap;gap:4px;">' + instChips + '</div></div>';
        }

        var leadersHtml = '';
        if (p.student_leaders && p.student_leaders.length) {
            var leadChips = p.student_leaders.map(function(ldr) {
                var avHtml = ldr.avatar ? '<img src="' + escapeHtml(ldr.avatar) + '" style="width:16px;height:16px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:4px;" />' : '';
                return '<span style="display:inline-flex;align-items:center;background:rgba(255,255,255,0.1);padding:2px 6px;border-radius:8px;font-size:11px;margin-right:4px;">' + avHtml + escapeHtml(ldr.name) + ' <em style="opacity:0.8;margin-left:3px;">(' + escapeHtml(ldr.role_label) + ')</em></span>';
            }).join(' ');
            leadersHtml = '<div class="tooltip-meta-row" style="align-items:flex-start;"><strong>🌟 Liderazgo:</strong> <div style="display:flex;flex-wrap:wrap;gap:4px;">' + leadChips + '</div></div>';
        }

        var locHtml = '';
        if (p.location) {
            locHtml += '<div class="tooltip-meta-row"><strong>📍 Aula:</strong> <span>' + escapeHtml(p.location) + '</span></div>';
        }
        if (p.online_url) {
            locHtml += '<div class="tooltip-meta-row"><strong>💻 Virtual:</strong> <span style="color:#818cf8;text-decoration:underline;">Enlace disponible</span></div>';
        }

        var descHtml = '';
        if (p.description) {
            var cleanDesc = p.description.length > 120 ? p.description.substring(0, 117) + '...' : p.description;
            descHtml = '<div class="tooltip-desc">' + escapeHtml(cleanDesc) + '</div>';
        }

        var html = '' +
            '<div class="tooltip-header">' +
                '<div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">' +
                    '<span class="adp-badge badge-indigo" style="font-size:11px;font-weight:700;">' + typeLabel + '</span>' +
                    statusBadge +
                    gcalChip +
                '</div>' +
                '<div class="tooltip-date">' + dateStr + '</div>' +
            '</div>' +
            '<div class="tooltip-title">' + escapeHtml(p.raw_title || event.title) + '</div>' +
            '<div class="tooltip-meta-grid">' +
                (p.program_name ? '<div class="tooltip-meta-row"><strong>🎓 Programa:</strong> <span>' + escapeHtml(p.program_name) + '</span></div>' : '') +
                (p.subject_name ? '<div class="tooltip-meta-row"><strong>📚 Materia:</strong> <span>' + escapeHtml(p.subject_name) + '</span></div>' : '') +
                (timeRange ? '<div class="tooltip-meta-row"><strong>🕐 Horario:</strong> <span>' + timeRange + '</span></div>' : '') +
                teachersHtml +
                locHtml +
            '</div>' +
            descHtml +
            '<div class="tooltip-footer">💡 Clic para opciones, asistencia y detalles</div>';

        $tt.html(html);

        var rect = el.getBoundingClientRect();
        var ttWidth = 320;
        var ttHeight = $tt.outerHeight() || 180;
        var padding = 12;

        var left = rect.right + padding;
        var top = rect.top;

        if (left + ttWidth > window.innerWidth - 10) {
            left = rect.left - ttWidth - padding;
        }
        if (left < 10) {
            left = Math.max(10, (jsEvent ? jsEvent.clientX : rect.left) + 12);
        }

        if (top + ttHeight > window.innerHeight - 10) {
            top = Math.max(10, window.innerHeight - ttHeight - 15);
        }

        $tt.css({
            top: top + 'px',
            left: left + 'px',
            display: 'block'
        }).addClass('is-visible');
    }

    function hideEventTooltip() {
        var $tt = $('#aura-cal-event-tooltip');
        if ($tt.length) {
            $tt.removeClass('is-visible').hide();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PANTALLA COMPLETA DEL CALENDARIO
    // ─────────────────────────────────────────────────────────────

    function toggleFullscreen() {
        var $container = $('.aura-calendar-view-container');
        var $btn = $('#btn-toggle-fullscreen');
        var isFullscreen = $container.hasClass('aura-calendar-is-fullscreen');

        if (!isFullscreen) {
            $container.addClass('aura-calendar-is-fullscreen');
            $btn.html('🗗 ' + (auraCalData.i18n && auraCalData.i18n.exit_fullscreen ? auraCalData.i18n.exit_fullscreen : 'Salir de Pantalla Completa'))
                .addClass('btn-indigo').removeClass('btn-ghost');
            $('body').addClass('aura-cal-fullscreen-active');
            showToast('Pantalla completa activada. Presiona ESC para salir.');
        } else {
            $container.removeClass('aura-calendar-is-fullscreen');
            $btn.html('⛶ ' + (auraCalData.i18n && auraCalData.i18n.fullscreen ? auraCalData.i18n.fullscreen : 'Pantalla Completa'))
                .removeClass('btn-indigo').addClass('btn-ghost');
            $('body').removeClass('aura-cal-fullscreen-active');
        }

        if (calendar) {
            setTimeout(function() {
                calendar.updateSize();
            }, 120);
        }
    }

    $(document).on('click', '#btn-toggle-fullscreen', function(e) {
        e.preventDefault();
        toggleFullscreen();
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if ($('.aura-modal-overlay:visible').length) {
                $('.aura-modal-overlay:visible').hide();
                return;
            }
            if ($('.aura-calendar-view-container').hasClass('aura-calendar-is-fullscreen')) {
                toggleFullscreen();
            }
        }
    });

    function updateEventDates(event) {
        var startStr = event.start.toISOString();
        var endStr = event.end ? event.end.toISOString() : startStr;

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_update_event_dates',
            nonce: auraCalData.nonce,
            id: event.id,
            start: startStr,
            end: endStr
        }, function(res) {
            if (res && res.success) {
                showToast(res.data.message || auraCalData.i18n.saved);
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error, 'error');
                if (calendar) calendar.refetchEvents();
            }
        });
    }

    // ─────────────────────────────────────────────────────────────
    // 2. FILTROS DEL CALENDARIO
    // ─────────────────────────────────────────────────────────────

    $('#filter-program').on('change', function() {
        var progId = $(this).val();
        var $subSelect = $('#filter-subject');

        $subSelect.html('<option value="">Todas las materias</option>');

        if (progId) {
            $.post(auraCalData.ajax_url, {
                action: 'aura_cal_get_subjects',
                nonce: auraCalData.nonce,
                program_id: progId
            }, function(res) {
                if (res && res.success && res.data.subjects) {
                    $.each(res.data.subjects, function(i, s) {
                        $subSelect.append($('<option>', {
                            value: s.id,
                            text: s.name + (s.code ? ' (' + s.code + ')' : '')
                        }));
                    });
                }
            });
        }

        if (calendar) calendar.refetchEvents();
    });

    $('#filter-subject, #filter-event-type').on('change', function() {
        if (calendar) calendar.refetchEvents();
    });

    $('#btn-filter-reset').on('click', function() {
        $('#filter-program').val('');
        $('#filter-subject').html('<option value="">Todas las materias</option>').val('');
        $('#filter-event-type').val('');
        if (calendar) calendar.refetchEvents();
    });

    // ─────────────────────────────────────────────────────────────
    // 3. MODAL EDITOR DE EVENTOS (AGENDAR / EDITAR)
    // ─────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────
    // 3. PALETA DE 20 COLORES (COLORES20.JSON) Y USUARIOS
    // ─────────────────────────────────────────────────────────────

    function syncColorPalette(inputSelector, hex) {
        if (!hex) return;
        var $input = $(inputSelector);
        if ($input.length) {
            $input.val(hex);
        }

        var hexUpper = hex.toUpperCase();
        var $palette = $('.aura-color-palette[data-target-input="' + inputSelector + '"]');
        if ($palette.length) {
            $palette.find('.aura-swatch').each(function() {
                var swatchColor = ($(this).data('color') || '').toUpperCase();
                if (swatchColor === hexUpper) {
                    $(this).addClass('is-selected');
                } else {
                    $(this).removeClass('is-selected');
                }
            });
        }
    }

    // Clic en muestra de la paleta predefinida
    $(document).on('click', '.aura-swatch', function(e) {
        e.preventDefault();
        var color = $(this).data('color');
        var $palette = $(this).closest('.aura-color-palette');
        var targetInputSelector = $palette.data('target-input');

        $palette.find('.aura-swatch').removeClass('is-selected');
        $(this).addClass('is-selected');

        if (targetInputSelector) {
            $(targetInputSelector).val(color).trigger('input').trigger('change');
        }
    });

    // Sincronización cuando se usa el input type="color" libre
    $(document).on('input change', '#prog-color, #subj-color, #evt-color', function() {
        var hex = $(this).val();
        var inputId = '#' + $(this).attr('id');
        var hexUpper = (hex || '').toUpperCase();
        var $palette = $('.aura-color-palette[data-target-input="' + inputId + '"]');
        if ($palette.length) {
            $palette.find('.aura-swatch').each(function() {
                var swatchColor = ($(this).data('color') || '').toUpperCase();
                if (swatchColor === hexUpper) {
                    $(this).addClass('is-selected');
                } else {
                    $(this).removeClass('is-selected');
                }
            });
        }
    });

    // Toggle visual para chips de usuarios (coordinadores / profesores)
    $(document).on('change', '.aura-user-chip input[type="checkbox"]', function() {
        if ($(this).is(':checked')) {
            $(this).closest('.aura-user-chip').addClass('is-checked');
        } else {
            $(this).closest('.aura-user-chip').removeClass('is-checked');
        }
    });

    function renderTeacherCheckboxes(selectedIds) {
        selectedIds = (selectedIds || []).map(function(id) { return parseInt(id, 10); });
        var container = $('#evt-teachers-container');
        container.empty();

        if (!auraCalData.teachers || !auraCalData.teachers.length) {
            container.append('<span style="font-size:12px;color:var(--aura-text-muted);">No hay profesores registrados en el sistema.</span>');
            return;
        }

        $.each(auraCalData.teachers, function(i, t) {
            var tid = parseInt(t.id, 10);
            var isChecked = selectedIds.indexOf(tid) !== -1;
            var pill = $(
                '<label class="aura-user-chip ' + (isChecked ? 'is-checked' : '') + '">' +
                '<input type="checkbox" name="teacher_ids[]" value="' + t.id + '" ' + (isChecked ? 'checked' : '') + '> ' +
                '<span>' + t.name + '</span>' +
                '</label>'
            );
            container.append(pill);
        });
    }

    function renderCoordinatorCheckboxes(selectedIds) {
        selectedIds = (selectedIds || []).map(function(id) { return parseInt(id, 10); });
        var container = $('#prog-coordinators-container');
        container.empty();

        if (!auraCalData.teachers || !auraCalData.teachers.length) {
            container.append('<span style="font-size:12px;color:var(--aura-text-muted);">No hay usuarios registrados como coordinadores o docentes.</span>');
            return;
        }

        $.each(auraCalData.teachers, function(i, t) {
            var tid = parseInt(t.id, 10);
            var isChecked = selectedIds.indexOf(tid) !== -1;
            var chip = $(
                '<label class="aura-user-chip ' + (isChecked ? 'is-checked' : '') + '">' +
                '<input type="checkbox" name="coordinator_ids[]" value="' + t.id + '" ' + (isChecked ? 'checked' : '') + '> ' +
                '<span>' + t.name + '</span>' +
                '</label>'
            );
            container.append(chip);
        });
    }

    function renderSubjectTeacherCheckboxes(selectedIds) {
        selectedIds = (selectedIds || []).map(function(id) { return parseInt(id, 10); });
        var container = $('#subj-teachers-container');
        container.empty();

        if (!auraCalData.teachers || !auraCalData.teachers.length) {
            container.append('<span style="font-size:12px;color:var(--aura-text-muted);">No hay profesores disponibles en el sistema.</span>');
            return;
        }

        $.each(auraCalData.teachers, function(i, t) {
            var tid = parseInt(t.id, 10);
            var isChecked = selectedIds.indexOf(tid) !== -1;
            var chip = $(
                '<label class="aura-user-chip ' + (isChecked ? 'is-checked' : '') + '">' +
                '<input type="checkbox" name="teacher_ids[]" value="' + t.id + '" ' + (isChecked ? 'checked' : '') + '> ' +
                '<span>' + t.name + '</span>' +
                '</label>'
            );
            container.append(chip);
        });
    }

    // ─────────────────────────────────────────────────────────────
    // ESTUDIANTES LÍDERES / ROLES DE ACTIVIDAD
    // ─────────────────────────────────────────────────────────────
    var currentEventLeaders = [];

    function initStudentLeadersSelect() {
        var $sel = $('#select-add-leader-user');
        $sel.html('<option value="">' + (auraCalData.i18n.select_student || 'Seleccionar estudiante...') + '</option>');

        if (auraCalData.students && auraCalData.students.length) {
            $.each(auraCalData.students, function(i, st) {
                $sel.append($('<option>', {
                    value: st.id,
                    text: st.name + (st.email ? ' (' + st.email + ')' : '')
                }));
            });
        }
    }

    function renderStudentLeadersList() {
        var $box = $('#evt-student-leaders-list');
        $box.empty();

        if (!currentEventLeaders || !currentEventLeaders.length) {
            $box.html('<span style="font-size:12px;color:var(--aura-text-muted);font-style:italic;">No hay estudiantes con responsabilidad asignada en esta actividad.</span>');
            $('#evt-student-leaders-json').val('[]');
            return;
        }

        var roleBadges = {
            'program_leader': '👑 Líder Programa',
            'activity_leader': '🎯 Líder Actividad',
            'presenter': '🗣️ Expositor / Clase',
            'monitor': '🛡️ Monitor'
        };

        $.each(currentEventLeaders, function(idx, ldr) {
            var roleText = roleBadges[ldr.role] || ldr.role_label || ldr.role;
            var avImg = ldr.avatar
                ? '<img src="' + escapeHtml(ldr.avatar) + '" style="width:22px;height:22px;border-radius:50%;object-fit:cover;flex-shrink:0;" />'
                : '<span style="width:22px;height:22px;border-radius:50%;background:var(--aura-primary,#5d5fef);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0;">' + escapeHtml((ldr.name || 'E').substring(0, 1).toUpperCase()) + '</span>';

            var $chip = $(
                '<div class="aura-user-chip is-checked" style="display:inline-flex;align-items:center;gap:6px;padding:3px 8px 3px 4px;border-radius:18px;background:var(--aura-surface,#fff);border:1px solid var(--aura-border,#cbd5e1);font-size:12px;">' +
                    avImg +
                    '<span style="font-weight:600;">' + escapeHtml(ldr.name) + '</span>' +
                    '<span class="aura-badge" style="font-size:10.5px;padding:2px 6px;border-radius:10px;background:rgba(93,95,239,0.12);color:var(--aura-primary,#5d5fef);font-weight:600;">' + escapeHtml(roleText) + '</span>' +
                    '<button type="button" class="btn-remove-leader" data-index="' + idx + '" title="Remover" style="border:none;background:transparent;cursor:pointer;color:#ef4444;font-size:14px;line-height:1;padding:0 2px;">&times;</button>' +
                '</div>'
            );
            $box.append($chip);
        });

        $('#evt-student-leaders-json').val(JSON.stringify(currentEventLeaders));
    }

    // Añadir líder desde selector
    $(document).on('click', '#btn-add-leader-to-event', function(e) {
        e.preventDefault();
        var uid = parseInt($('#select-add-leader-user').val(), 10);
        var role = $('#select-add-leader-role').val() || 'activity_leader';

        if (!uid) {
            showToast('Por favor selecciona un estudiante.', 'warning');
            $('#select-add-leader-user').focus();
            return;
        }

        // Buscar datos del estudiante
        var studentData = null;
        if (auraCalData.students && auraCalData.students.length) {
            for (var i = 0; i < auraCalData.students.length; i++) {
                if (parseInt(auraCalData.students[i].id, 10) === uid) {
                    studentData = auraCalData.students[i];
                    break;
                }
            }
        }

        var stName = studentData ? studentData.name : 'Estudiante #' + uid;
        var stAvatar = studentData ? studentData.avatar : '';

        // Verificar si ya fue añadido con ese rol
        var already = currentEventLeaders.some(function(l) {
            return parseInt(l.user_id, 10) === uid && l.role === role;
        });

        if (already) {
            showToast('Este estudiante ya tiene este rol asignado en la actividad.', 'info');
            return;
        }

        currentEventLeaders.push({
            user_id: uid,
            name: stName,
            avatar: stAvatar,
            role: role,
            role_label: $('#select-add-leader-role option:selected').text(),
            assigned_at: new Date().toISOString()
        });

        renderStudentLeadersList();
        $('#select-add-leader-user').val('');
    });

    // Remover líder de la lista
    $(document).on('click', '.btn-remove-leader', function(e) {
        e.preventDefault();
        var idx = parseInt($(this).data('index'), 10);
        if (idx >= 0 && idx < currentEventLeaders.length) {
            currentEventLeaders.splice(idx, 1);
            renderStudentLeadersList();
        }
    });

    function openEventEditor(data) {
        data = data || {};
        var form = document.getElementById('form-event-editor');
        if (!form) {
            if (auraCalData && auraCalData.calendar_url) {
                window.location.href = auraCalData.calendar_url + '&action=create';
            }
            return;
        }

        form.reset();

        $('#evt-id').val(data.id || '0');
        $('#modal-event-title').text(data.id ? '✏️ Editar Clase' : '➕ Agendar Clase o Actividad');

        if (data.id) {
            $('#sec-recurrence-toggle').hide();
        } else {
            $('#sec-recurrence-toggle').show();
        }

        $('#evt-is-recurring').prop('checked', false);
        $('#box-recurrence-details').hide();
        $('#box-single-datetime').show();

        // Fechas por defecto si no vienen
        var now = new Date();
        var nextHour = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours() + 1, 0, 0);
        var endHour = new Date(nextHour.getTime() + 2 * 60 * 60 * 1000);

        function formatLocalDT(d) {
            var year = d.getFullYear();
            var month = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            var hours = String(d.getHours()).padStart(2, '0');
            var mins = String(d.getMinutes()).padStart(2, '0');
            return year + '-' + month + '-' + day + 'T' + hours + ':' + mins;
        }

        var startVal = data.start ? data.start.substring(0, 16) : formatLocalDT(nextHour);
        var endVal = data.end ? data.end.substring(0, 16) : formatLocalDT(endHour);

        $('#evt-start-dt').val(startVal);
        $('#evt-end-dt').val(endVal);

        $('#rec-date-start').val(startVal.substring(0, 10));
        $('#rec-date-end').val(endVal.substring(0, 10));
        $('#rec-time-start').val(startVal.substring(11, 16) || '09:00');
        $('#rec-time-end').val(endVal.substring(11, 16) || '11:00');

        // Valores de texto y selects si se proveen (ej: al editar o precargar)
        if (data.title) $('#evt-title').val(data.title);
        if (data.event_type) $('#evt-type').val(data.event_type);
        if (data.status) $('#evt-status').val(data.status);
        if (data.location) $('#evt-location').val(data.location);
        if (data.online_url) $('#evt-online-url').val(data.online_url);
        
        var evtColor = data.color || '#5D5FEF';
        $('#evt-color').val(evtColor);
        syncColorPalette('#evt-color', evtColor);

        if (data.description) $('#evt-description').val(data.description);

        // Preseleccionar programa si hay filtro activo o si viene en data
        var activeProgFilter = $('#filter-program').val();
        if (data.program_id) {
            $('#evt-program-id').val(data.program_id).trigger('change');
        } else if (activeProgFilter) {
            $('#evt-program-id').val(activeProgFilter).trigger('change');
        }

        renderTeacherCheckboxes(data.teacher_ids || []);

        // Cargar líderes de la sesión si existen
        currentEventLeaders = Array.isArray(data.student_leaders) ? data.student_leaders.slice() : [];
        initStudentLeadersSelect();
        renderStudentLeadersList();

        openModal('#modal-event-editor');
    }

    // Delegación global para botones de agendar clase
    $(document).on('click', '#btn-top-create-event, #btn-create-event-modal, .btn-trigger-agendar, [data-action="create-event"]', function(e) {
        e.preventDefault();
        openEventEditor();
    });

    // Toggle recurrencia
    $('#evt-is-recurring').on('change', function() {
        if ($(this).is(':checked')) {
            $('#box-recurrence-details').slideDown(150);
            $('#box-single-datetime').slideUp(150);
        } else {
            $('#box-recurrence-details').slideUp(150);
            $('#box-single-datetime').slideDown(150);
        }
    });

    // Cargar materias según programa seleccionado en modal
    $('#evt-program-id').on('change', function() {
        var progId = $(this).val();
        var $subSelect = $('#evt-subject-id');
        $subSelect.html('<option value="">General / Sin materia específica</option>');

        if (progId) {
            $.post(auraCalData.ajax_url, {
                action: 'aura_cal_get_subjects',
                nonce: auraCalData.nonce,
                program_id: progId
            }, function(res) {
                if (res && res.success && res.data.subjects) {
                    $.each(res.data.subjects, function(i, s) {
                        $subSelect.append($('<option>', {
                            value: s.id,
                            text: s.name + (s.code ? ' (' + s.code + ')' : '')
                        }));
                    });
                }
            });
        }
    });

    // Sincronización dinámica de fechas y horas en el editor de eventos
    $('#evt-start-dt').on('change', function() {
        var startVal = $(this).val();
        if (startVal) {
            $('#evt-end-dt').attr('min', startVal);
            var endVal = $('#evt-end-dt').val();
            if (endVal && endVal < startVal) {
                $('#evt-end-dt').val(startVal);
            }
        } else {
            $('#evt-end-dt').removeAttr('min');
        }
    });

    $('#rec-date-start').on('change', function() {
        var startVal = $(this).val();
        if (startVal) {
            $('#rec-date-end').attr('min', startVal);
            var endVal = $('#rec-date-end').val();
            if (endVal && endVal < startVal) {
                $('#rec-date-end').val(startVal);
            }
        } else {
            $('#rec-date-end').removeAttr('min');
        }
    });

    // Submit Guardar Evento
    $('#form-event-editor').on('submit', function(e) {
        e.preventDefault();

        // Validar rangos coherentes
        if ($('#evt-is-recurring').is(':checked')) {
            var rStart = $('#rec-date-start').val();
            var rEnd = $('#rec-date-end').val();
            if (rStart && rEnd && rEnd < rStart) {
                showToast('La fecha fin de la recurrencia no puede ser anterior a la de inicio.', 'error');
                $('#rec-date-end').focus();
                return false;
            }
        } else {
            var dtStart = $('#evt-start-dt').val();
            var dtEnd = $('#evt-end-dt').val();
            if (dtStart && dtEnd && dtEnd < dtStart) {
                showToast('La fecha y hora de fin debe ser posterior a la de inicio.', 'error');
                $('#evt-end-dt').focus();
                return false;
            }
        }

        var formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'aura_cal_save_event' });
        formData.push({ name: 'nonce', value: auraCalData.nonce });

        var $btn = $('#btn-save-event');
        $btn.prop('disabled', true).text('Guardando...');

        $.post(auraCalData.ajax_url, formData, function(res) {
            $btn.prop('disabled', false).text('💾 Guardar Clase');
            if (res && res.success) {
                showToast(res.data.message || auraCalData.i18n.saved);
                closeModal('#modal-event-editor');
                if (calendar) calendar.refetchEvents();
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error, 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('💾 Guardar Clase');
            showToast(auraCalData.i18n.error, 'error');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 4. MODAL DETALLE DE EVENTO
    // ─────────────────────────────────────────────────────────────

    function openEventDetail(event) {
        currentDetailEvent = event;
        var p = event.extendedProps || {};

        $('#det-title').text(p.raw_title || event.title);
        $('#det-type-badge').text(p.event_type ? p.event_type.toUpperCase() : 'CLASE');
        $('#det-status-badge').text(p.status ? p.status.toUpperCase() : 'PROGRAMADO');

        if (p.gcal_sync_status === 'synced') {
            $('#det-gcal-badge').text('✓ Google Calendar').css('background', '#10b981').css('color', '#fff').show();
        } else {
            $('#det-gcal-badge').hide();
        }

        $('#det-program').text(p.program_name || '—');
        $('#det-subject').text(p.subject_name || '—');

        var timeStr = event.start.toLocaleDateString() + ' ' + event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        if (event.end) {
            timeStr += ' - ' + event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        $('#det-time').text(timeStr);

        // Profesores con avatar
        if (p.instructors && p.instructors.length) {
            var teachHtml = p.instructors.map(function(inst) {
                var av = inst.avatar ? '<img src="' + escapeHtml(inst.avatar) + '" style="width:20px;height:20px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:4px;" />' : '';
                return '<span class="aura-user-chip-sm" style="display:inline-flex;align-items:center;background:var(--aura-surface,#fff);border:1px solid var(--aura-border,#cbd5e1);padding:2px 8px;border-radius:12px;font-size:12px;">' + av + escapeHtml(inst.name) + '</span>';
            }).join(' ');
            $('#det-teachers').html(teachHtml);
            $('#row-det-teachers').show();
        } else {
            $('#row-det-teachers').hide();
        }

        // Estudiantes Líderes / Responsables de la Actividad
        if (p.student_leaders && p.student_leaders.length) {
            var leadHtml = p.student_leaders.map(function(ldr) {
                var av = ldr.avatar
                    ? '<img src="' + escapeHtml(ldr.avatar) + '" style="width:22px;height:22px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:6px;" />'
                    : '<span style="width:22px;height:22px;border-radius:50%;background:var(--aura-primary,#5d5fef);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;margin-right:6px;">' + escapeHtml((ldr.name||'E').charAt(0).toUpperCase()) + '</span>';
                return '<div class="aura-leader-chip" style="display:inline-flex;align-items:center;background:var(--aura-surface,#fff);border:1px solid var(--aura-border,#cbd5e1);padding:3px 10px;border-radius:18px;font-size:12.5px;">' +
                    av +
                    '<span style="font-weight:600;margin-right:6px;">' + escapeHtml(ldr.name) + '</span>' +
                    '<span class="aura-badge aura-badge--sm" style="font-size:10px;padding:2px 6px;border-radius:10px;background:rgba(93,95,239,0.12);color:var(--aura-primary,#5d5fef);font-weight:600;">' + escapeHtml(ldr.role_label || 'Líder') + '</span>' +
                '</div>';
            }).join(' ');
            $('#det-leaders').html(leadHtml);
            $('#row-det-leaders').show();
        } else {
            $('#row-det-leaders').hide();
        }

        // Ubicación
        if (p.location) {
            $('#det-location').text(p.location);
            $('#row-det-location').show();
        } else {
            $('#row-det-location').hide();
        }

        // Online URL
        if (p.online_url) {
            $('#det-online').attr('href', p.online_url).text(p.online_url);
            $('#row-det-online').show();
        } else {
            $('#row-det-online').hide();
        }

        // Descripción
        if (p.description) {
            $('#box-det-desc').text(p.description).show();
        } else {
            $('#box-det-desc').hide();
        }

        openModal('#modal-event-detail');
    }

    // Botón Eliminar Evento
    $('#btn-det-delete').on('click', function() {
        if (!currentDetailEvent) return;
        var p = currentDetailEvent.extendedProps || {};
        var deleteSeries = false;

        if (p.recurrence_group_id) {
            if (confirm(auraCalData.i18n.confirm_delete_series)) {
                deleteSeries = true;
            } else if (!confirm(auraCalData.i18n.confirm_delete)) {
                return;
            }
        } else {
            if (!confirm(auraCalData.i18n.confirm_delete)) return;
        }

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_delete_event',
            nonce: auraCalData.nonce,
            id: currentDetailEvent.id,
            delete_series: deleteSeries ? '1' : '0'
        }, function(res) {
            if (res && res.success) {
                showToast(res.data.message || auraCalData.i18n.saved);
                closeModal('#modal-event-detail');
                if (calendar) calendar.refetchEvents();
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error, 'error');
            }
        });
    });

    // Botón Editar Evento desde Detalle
    $('#btn-det-edit').on('click', function() {
        if (!currentDetailEvent) return;
        var ev = currentDetailEvent;
        var p = ev.extendedProps || {};

        closeModal('#modal-event-detail');

        var teacherIds = (p.instructors || []).map(function(inst) { return parseInt(inst.id, 10); });

        openEventEditor({
            id: ev.id,
            title: p.raw_title || ev.title,
            program_id: p.program_id,
            subject_id: p.subject_id,
            event_type: p.event_type,
            status: p.status,
            start: ev.start ? ev.start.toISOString() : '',
            end: ev.end ? ev.end.toISOString() : '',
            location: p.location,
            online_url: p.online_url,
            color: ev.backgroundColor,
            description: p.description,
            teacher_ids: teacherIds,
            student_leaders: p.student_leaders || []
        });

        // Preseleccionar valores
        $('#evt-title').val(p.raw_title || ev.title);
        $('#evt-type').val(p.event_type || 'class');
        $('#evt-status').val(p.status || 'scheduled');
        $('#evt-location').val(p.location || '');
        $('#evt-online-url').val(p.online_url || '');
        $('#evt-color').val(ev.backgroundColor || '#6366f1');
        $('#evt-description').val(p.description || '');

        if (p.program_id) {
            $('#evt-program-id').val(p.program_id);
            // Cargar materias y luego seleccionar
            $.post(auraCalData.ajax_url, {
                action: 'aura_cal_get_subjects',
                nonce: auraCalData.nonce,
                program_id: p.program_id
            }, function(res) {
                var $subSelect = $('#evt-subject-id');
                $subSelect.html('<option value="">General / Sin materia específica</option>');
                if (res && res.success && res.data.subjects) {
                    $.each(res.data.subjects, function(i, s) {
                        $subSelect.append($('<option>', {
                            value: s.id,
                            text: s.name + (s.code ? ' (' + s.code + ')' : '')
                        }));
                    });
                    if (p.subject_id) {
                        $subSelect.val(p.subject_id);
                    }
                }
            });
        }
    });

    // ─────────────────────────────────────────────────────────────
    // 5. ASISTENCIA (MODAL Y ROSTER)
    // ─────────────────────────────────────────────────────────────

    $('#btn-det-take-attendance').on('click', function() {
        if (!currentDetailEvent) return;
        var eventId = currentDetailEvent.id;
        var title = currentDetailEvent.title;

        closeModal('#modal-event-detail');

        $('#att-modal-title').text('📋 Control de Asistencia — ' + title);
        $('#att-modal-subtitle').text(currentDetailEvent.start.toLocaleDateString() + ' ' + currentDetailEvent.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
        $('#att-table-body').html('<tr><td colspan="3" style="text-align:center;padding:20px;">Cargando lista de estudiantes...</td></tr>');

        openModal('#modal-attendance');

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_get_attendance_roster',
            nonce: auraCalData.nonce,
            event_id: eventId
        }, function(res) {
            if (res && res.success && res.data) {
                var roster = res.data.roster || [];
                $('#att-roster-count').text(roster.length);

                if (!roster.length) {
                    $('#att-table-body').html('<tr><td colspan="3" style="text-align:center;padding:24px;color:var(--aura-text-muted);">No hay estudiantes inscritos en este programa.</td></tr>');
                    return;
                }

                var tbody = '';
                $.each(roster, function(i, st) {
                    var status = st.attendance_status || 'present';
                    var notes = st.attendance_notes || '';

                    var avHtml = st.photo_url
                        ? '<img src="' + escapeHtml(st.photo_url) + '" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;vertical-align:middle;" onerror="this.style.display=\'none\';" />'
                        : '<span style="width:28px;height:28px;border-radius:50%;background:var(--aura-primary,#5d5fef);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">' + escapeHtml((st.first_name || 'E').substring(0, 1).toUpperCase()) + '</span>';

                    tbody += '<tr data-student-id="' + st.student_id + '" style="border-bottom: 1px solid var(--aura-border);">';
                    tbody += '<td style="padding: 10px 14px;"><div style="display:flex;align-items:center;gap:10px;">' + avHtml + '<div><strong>' + escapeHtml(st.last_name + ', ' + st.first_name) + '</strong><br><small style="color:var(--aura-text-muted);">' + escapeHtml(st.student_code || st.email) + '</small></div></div></td>';
                    tbody += '<td style="padding: 10px 14px; text-align: center;">';
                    tbody += '<div class="att-status-btn-group">';
                    tbody += '<button type="button" class="att-btn ' + (status === 'present' ? 'active-present' : '') + '" data-status="present">P</button>';
                    tbody += '<button type="button" class="att-btn ' + (status === 'late' ? 'active-late' : '') + '" data-status="late">T</button>';
                    tbody += '<button type="button" class="att-btn ' + (status === 'absent_excused' ? 'active-excused' : '') + '" data-status="absent_excused">FJ</button>';
                    tbody += '<button type="button" class="att-btn ' + (status === 'absent_unexcused' ? 'active-unexcused' : '') + '" data-status="absent_unexcused">FI</button>';
                    tbody += '</div>';
                    tbody += '</td>';
                    tbody += '<td style="padding: 10px 14px;"><input type="text" class="form-control att-notes-input" value="' + (notes ? notes.replace(/"/g, '&quot;') : '') + '" placeholder="Observación..." style="width:100%;font-size:12px;border-radius:6px;padding:4px 8px;"></td>';
                    tbody += '</tr>';
                });

                $('#att-table-body').html(tbody);
            }
        });
    });

    // Clic en botón de estado de asistencia
    $(document).on('click', '.att-btn', function() {
        var $group = $(this).closest('.att-status-btn-group');
        $group.find('.att-btn').removeClass('active-present active-late active-excused active-unexcused');
        var newStatus = $(this).data('status');
        if (newStatus === 'present') $(this).addClass('active-present');
        if (newStatus === 'late') $(this).addClass('active-late');
        if (newStatus === 'absent_excused') $(this).addClass('active-excused');
        if (newStatus === 'absent_unexcused') $(this).addClass('active-unexcused');
    });

    // Marcar todos presentes
    $('#btn-att-mark-all-present').on('click', function() {
        $('#att-table-body tr').each(function() {
            var $group = $(this).find('.att-status-btn-group');
            $group.find('.att-btn').removeClass('active-present active-late active-excused active-unexcused');
            $group.find('[data-status="present"]').addClass('active-present');
        });
    });

    // Guardar Asistencia
    $('#btn-save-attendance').on('click', function() {
        if (!currentDetailEvent) return;
        var records = [];

        $('#att-table-body tr').each(function() {
            var stId = $(this).data('student-id');
            if (!stId) return;

            var activeBtn = $(this).find('.att-btn.active-present, .att-btn.active-late, .att-btn.active-excused, .att-btn.active-unexcused');
            var status = activeBtn.length ? activeBtn.data('status') : 'present';
            var notes = $(this).find('.att-notes-input').val();

            records.push({
                student_id: stId,
                status: status,
                notes: notes
            });
        });

        var $btn = $(this);
        $btn.prop('disabled', true).text('Guardando...');

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_save_attendance',
            nonce: auraCalData.nonce,
            event_id: currentDetailEvent.id,
            records: records
        }, function(res) {
            $btn.prop('disabled', false).text('💾 Guardar Asistencia');
            if (res && res.success) {
                showToast(res.data.message || auraCalData.i18n.saved);
                closeModal('#modal-attendance');
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error, 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('💾 Guardar Asistencia');
            showToast(auraCalData.i18n.error, 'error');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 6. PROGRAMAS Y MATERIAS (TAB 2)
    // ─────────────────────────────────────────────────────────────

    $('#btn-create-program, #btn-create-first-program').on('click', function() {
        $('#form-program-editor')[0].reset();
        $('#prog-id').val('0');
        $('#prog-area-id').val('');
        $('#modal-prog-title').text('🎓 Nuevo Programa Académico');

        renderCoordinatorCheckboxes([]);
        syncColorPalette('#prog-color', '#5D5FEF');

        openModal('#modal-program-editor');
    });

    $('.btn-edit-program').on('click', function() {
        var progId = $(this).data('program-id');
        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_get_program',
            nonce: auraCalData.nonce,
            id: progId
        }, function(res) {
            if (res && res.success && res.data.program) {
                var p = res.data.program;
                $('#prog-id').val(p.id);
                $('#modal-prog-title').text('✏️ Editar Programa: ' + p.name);
                $('#prog-name').val(p.name);
                $('#prog-code').val(p.code);
                $('#prog-period').val(p.academic_period || '');
                $('#prog-start-date').val(p.start_date || '');
                $('#prog-end-date').val(p.end_date || '');
                $('#prog-area-id').val(p.area_id || '');
                
                var progColor = p.color || '#5D5FEF';
                $('#prog-color').val(progColor);
                syncColorPalette('#prog-color', progColor);

                $('#prog-desc').val(p.description || '');

                var coordIds = p.coordinator_ids || (p.coordinator_id ? [parseInt(p.coordinator_id, 10)] : []);
                renderCoordinatorCheckboxes(coordIds);

                openModal('#modal-program-editor');
            }
        });
    });

    // Sincronización dinámica de fechas del programa
    $('#prog-start-date').on('change', function() {
        var startVal = $(this).val();
        if (startVal) {
            $('#prog-end-date').attr('min', startVal);
            var endVal = $('#prog-end-date').val();
            if (endVal && endVal < startVal) {
                $('#prog-end-date').val(startVal);
                showToast('La fecha de fin se ajustó para no ser anterior a la de inicio.', 'info');
            }
        } else {
            $('#prog-end-date').removeAttr('min');
        }
    });

    $('#prog-end-date').on('change', function() {
        var endVal = $(this).val();
        var startVal = $('#prog-start-date').val();
        if (startVal && endVal && endVal < startVal) {
            showToast('La fecha de fin no puede ser anterior a la fecha de inicio.', 'warning');
            $(this).val(startVal);
        }
    });

    $('#form-program-editor').on('submit', function(e) {
        e.preventDefault();

        var startDate = $('#prog-start-date').val();
        var endDate = $('#prog-end-date').val();
        if (startDate && endDate && endDate < startDate) {
            showToast('La fecha de fin no puede ser anterior a la fecha de inicio.', 'error');
            $('#prog-end-date').focus();
            return false;
        }

        var formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'aura_cal_save_program' });
        formData.push({ name: 'nonce', value: auraCalData.nonce });

        var $btn = $('#btn-save-program');
        $btn.prop('disabled', true).text('Guardando...');

        $.post(auraCalData.ajax_url, formData, function(res) {
            $btn.prop('disabled', false).text('💾 Guardar Programa');
            if (res && res.success) {
                showToast(res.data.message || auraCalData.i18n.saved);
                closeModal('#modal-program-editor');
                setTimeout(function() { location.reload(); }, 600);
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error, 'error');
            }
        });
    });

    // Restaurar programa archivado
    $(document).on('click', '.btn-restore-program', function() {
        var $btn    = $(this);
        var progId  = $btn.data('program-id');
        var $card   = $btn.closest('.program-card');
        var name    = $card.find('h4, strong').first().text().trim() || 'este programa';

        if (!confirm('¿Restaurar "' + name + '"? El programa y sus materias volverán al estado Activo.')) {
            return;
        }

        $btn.prop('disabled', true).html('⏳ Restaurando...');

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_restore_program',
            nonce:  auraCalData.nonce,
            id:     progId
        }, function(res) {
            if (res && res.success) {
                showToast(res.data.message || 'Programa restaurado exitosamente.', 'success');
                $card.fadeOut(400, function() {
                    $(this).remove();
                });
                // Actualizar contador de archivados en el tab
                var $badge = $('a[href*="prog_filter=archived"] span');
                if ($badge.length) {
                    var cnt = parseInt($badge.text(), 10) - 1;
                    if (cnt <= 0) {
                        $badge.remove();
                    } else {
                        $badge.text(cnt);
                    }
                }
                setTimeout(function() { location.reload(); }, 800);
            } else {
                $btn.prop('disabled', false).html('♻️ Restaurar');
                showToast((res && res.data && res.data.message) ? res.data.message : 'No se pudo restaurar el programa.', 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('♻️ Restaurar');
            showToast('Error de conexión al restaurar el programa.', 'error');
        });
    });

    // Sincronizar cursos de Estudiantes → Programas del Calendario
    $('#btn-sync-student-courses').on('click', function() {
        var $btn = $(this);
        if (!confirm('¿Importar los Cursos de Estudiantes activos como Programas del Calendario?\n\nSolo se crearán los que aún no existan. Los programas ya existentes no se modificarán.')) {
            return;
        }

        $btn.prop('disabled', true).html('⏳ Sincronizando...');

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_sync_student_courses',
            nonce:  auraCalData.nonce
        }, function(res) {
            $btn.prop('disabled', false).html('🔄 Sincronizar desde Estudiantes');
            if (res && res.success) {
                showToast(res.data.message || 'Sincronización completada.', 'success');
                if (res.data.reload) {
                    setTimeout(function() { location.reload(); }, 1200);
                }
            } else {
                var msg = (res && res.data && res.data.message) ? res.data.message : 'Error al sincronizar los cursos.';
                showToast(msg, 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('🔄 Sincronizar desde Estudiantes');
            showToast('Error de conexión al sincronizar.', 'error');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // SUB-PESTAÑAS EN MODAL DE MATERIA Y MATERIALES DE ESTUDIO
    // ─────────────────────────────────────────────────────────────
    $(document).on('click', '.aura-modal-subtab-btn', function(e) {
        e.preventDefault();
        var target = $(this).data('subtab');
        var $modal = $(this).closest('.aura-modal-container');
        $modal.find('.aura-modal-subtab-btn').removeClass('active').css({
            'border-bottom-color': 'transparent',
            'color': 'var(--aura-text-secondary, #64748b)'
        });
        $(this).addClass('active').css({
            'border-bottom-color': 'var(--aura-primary, #5d5fef)',
            'color': 'var(--aura-primary, #5d5fef)'
        });
        $modal.find('.aura-modal-subtab-pane').hide();
        $('#' + target).show();
    });

    var currentTeacherMaterials = [];
    var currentStudentMaterials = [];

    function renderSubjectMaterialsList(type) {
        var list = (type === 'teacher') ? currentTeacherMaterials : currentStudentMaterials;
        var containerId = (type === 'teacher') ? '#subj-teacher-materials-list' : '#subj-student-materials-list';
        var $container = $(containerId);
        $container.empty();

        if (!list || !list.length) {
            var emptyHint = (type === 'teacher')
                ? 'No hay materiales de cátedra cargados aún.'
                : 'No hay materiales para alumnos cargados aún.';
            $container.html('<p class="aura-empty-hint" style="font-size:12px;color:var(--aura-text-muted);font-style:italic;margin:6px 0;">' + emptyHint + '</p>');
            return;
        }

        $.each(list, function(idx, item) {
            var isDrive = (item.storage === 'gdrive' || (item.file_url && item.file_url.indexOf('drive.google.com') !== -1));
            var icon = isDrive ? '☁️' : '📁';
            var sizeStr = item.file_size_formatted || (item.file_size ? (Math.round(item.file_size / 1024) + ' KB') : '');
            var link = item.download_url || item.file_url || item.url || '#';

            var $card = $(
                '<div class="aura-material-item" style="display:flex;align-items:center;justify-content:space-between;background:var(--aura-surface,#fff);border:1px solid var(--aura-border,#cbd5e1);padding:8px 12px;border-radius:8px;font-size:13px;">' +
                    '<div style="display:flex;align-items:center;gap:8px;overflow:hidden;flex:1;margin-right:10px;">' +
                        '<span style="font-size:16px;">' + icon + '</span>' +
                        '<div style="overflow:hidden;">' +
                            '<a href="' + escapeHtml(link) + '" target="_blank" style="font-weight:600;color:var(--aura-primary,#5d5fef);text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' +
                                escapeHtml(item.title || item.file_name) +
                            '</a>' +
                            '<span style="font-size:11px;color:var(--aura-text-muted);">' +
                                (isDrive ? 'Google Drive (Nube)' : 'Almacenamiento Local') +
                                (sizeStr ? ' &bull; ' + sizeStr : '') +
                            '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div style="display:flex;align-items:center;gap:6px;">' +
                        '<a href="' + escapeHtml(link) + '" target="_blank" class="btn btn-ghost" style="padding:4px 8px;font-size:11.5px;" title="Descargar / Abrir">📥</a>' +
                        '<button type="button" class="btn btn-ghost btn-remove-material" data-type="' + type + '" data-id="' + escapeHtml(item.id) + '" style="padding:4px 8px;font-size:11.5px;color:#ef4444;" title="Eliminar">&times;</button>' +
                    '</div>' +
                '</div>'
            );
            $container.append($card);
        });
    }

    // Disparar input de archivo
    $(document).on('click', '.btn-upload-material', function(e) {
        e.preventDefault();
        var type = $(this).data('type') || 'teacher';
        var subjId = parseInt($('#subj-id').val(), 10);
        if (!subjId) {
            showToast('Por favor guarda la materia primero antes de subir archivos adjuntos.', 'warning');
            return;
        }
        $('#upload-' + type + '-file-input').click();
    });

    // Subir archivo al seleccionar
    $(document).on('change', '#upload-teacher-file-input, #upload-student-file-input', function() {
        var file = this.files[0];
        if (!file) return;

        var type = $(this).attr('id').indexOf('teacher') !== -1 ? 'teacher' : 'student';
        var subjId = parseInt($('#subj-id').val(), 10);
        var inputEl = this;

        showToast('Subiendo archivo a la nube / Google Drive...', 'info');

        var formData = new FormData();
        formData.append('action', 'aura_cal_upload_subject_material');
        formData.append('nonce', auraCalData.nonce);
        formData.append('subject_id', subjId);
        formData.append('material_type', type);
        formData.append('material_file', file);

        $.ajax({
            url: auraCalData.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                inputEl.value = '';
                if (res && res.success && res.data && res.data.material) {
                    showToast(res.data.message || 'Archivo subido con éxito.');
                    if (type === 'teacher') {
                        currentTeacherMaterials.push(res.data.material);
                    } else {
                        currentStudentMaterials.push(res.data.material);
                    }
                    renderSubjectMaterialsList(type);
                } else {
                    showToast(res && res.data && res.data.message ? res.data.message : 'Error al subir archivo.', 'error');
                }
            },
            error: function() {
                inputEl.value = '';
                showToast('Error en la llamada de subida de archivo.', 'error');
            }
        });
    });

    // Añadir enlace directo a Google Drive
    $(document).on('click', '.btn-add-drive-link', function(e) {
        e.preventDefault();
        var type = $(this).data('type') || 'teacher';
        var subjId = parseInt($('#subj-id').val(), 10);
        if (!subjId) {
            showToast('Por favor guarda la materia primero antes de añadir enlaces.', 'warning');
            return;
        }

        var driveUrl = prompt('Introduce el enlace de Google Drive (URL compartida del archivo o carpeta):');
        if (!driveUrl || !driveUrl.trim()) return;

        var docTitle = prompt('Nombre o título descriptivo para este documento:', '') || 'Documento en Google Drive';

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_upload_subject_material',
            nonce: auraCalData.nonce,
            subject_id: subjId,
            material_type: type,
            drive_link: driveUrl.trim(),
            file_name: docTitle.trim()
        }, function(res) {
            if (res && res.success && res.data && res.data.material) {
                showToast(res.data.message || 'Enlace de Google Drive registrado con éxito.');
                if (type === 'teacher') {
                    currentTeacherMaterials.push(res.data.material);
                } else {
                    currentStudentMaterials.push(res.data.material);
                }
                renderSubjectMaterialsList(type);
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : 'Error al vincular enlace de Drive.', 'error');
            }
        });
    });

    // Eliminar material de materia
    $(document).on('click', '.btn-remove-material', function(e) {
        e.preventDefault();
        if (!confirm('¿Deseas eliminar este material de la materia?')) return;

        var type = $(this).data('type') || 'teacher';
        var matId = $(this).data('id');
        var subjId = parseInt($('#subj-id').val(), 10);

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_delete_subject_material',
            nonce: auraCalData.nonce,
            subject_id: subjId,
            material_type: type,
            material_id: matId
        }, function(res) {
            if (res && res.success) {
                showToast(res.data.message || 'Material eliminado.');
                if (type === 'teacher') {
                    currentTeacherMaterials = currentTeacherMaterials.filter(function(m) { return m.id !== matId; });
                } else {
                    currentStudentMaterials = currentStudentMaterials.filter(function(m) { return m.id !== matId; });
                }
                renderSubjectMaterialsList(type);
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : 'Error al eliminar material.', 'error');
            }
        });
    });

    // Añadir Materia
    $('.btn-add-subject').on('click', function() {
        var progId = $(this).data('program-id');
        var progName = $(this).data('program-name');

        $('#form-subject-editor')[0].reset();
        $('#subj-id').val('0');
        $('#subj-prog-id').val(progId);
        $('#subj-prog-name-display').text(progName);
        $('#modal-subj-title').text('📚 Añadir Materia');

        renderSubjectTeacherCheckboxes([]);
        syncColorPalette('#subj-color', '#3A86FF');

        currentTeacherMaterials = [];
        currentStudentMaterials = [];
        renderSubjectMaterialsList('teacher');
        renderSubjectMaterialsList('student');
        $('.aura-modal-subtab-btn[data-subtab="subj-tab-general"]').click();

        openModal('#modal-subject-editor');
    });

    // Editar Materia
    $('.btn-edit-subject').on('click', function() {
        var subjId = $(this).data('subject-id');
        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_get_subject',
            nonce: auraCalData.nonce,
            id: subjId
        }, function(res) {
            if (res && res.success && res.data.subject) {
                var s = res.data.subject;
                $('#subj-id').val(s.id);
                $('#subj-prog-id').val(s.program_id);
                $('#subj-prog-name-display').text(s.program_name || '');
                $('#modal-subj-title').text('✏️ Editar Materia: ' + s.name);
                $('#subj-name').val(s.name);
                $('#subj-code').val(s.code);
                $('#subj-hours').val(s.total_hours || 30);
                
                var subjColor = s.color || '#3A86FF';
                $('#subj-color').val(subjColor);
                syncColorPalette('#subj-color', subjColor);

                var teacherIds = s.teacher_ids || (s.default_teacher_id ? [parseInt(s.default_teacher_id, 10)] : []);
                renderSubjectTeacherCheckboxes(teacherIds);

                currentTeacherMaterials = s.teacher_materials_list || [];
                currentStudentMaterials = s.student_materials_list || [];
                renderSubjectMaterialsList('teacher');
                renderSubjectMaterialsList('student');
                $('.aura-modal-subtab-btn[data-subtab="subj-tab-general"]').click();

                openModal('#modal-subject-editor');
            }
        });
    });

    // Guardar Materia
    $('#form-subject-editor').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'aura_cal_save_subject' });
        formData.push({ name: 'nonce', value: auraCalData.nonce });

        var $btn = $('#btn-save-subject');
        $btn.prop('disabled', true).text('Guardando...');

        $.post(auraCalData.ajax_url, formData, function(res) {
            $btn.prop('disabled', false).text('💾 Guardar Materia');
            if (res && res.success) {
                showToast(res.data.message || auraCalData.i18n.saved);
                closeModal('#modal-subject-editor');
                setTimeout(function() { location.reload(); }, 600);
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error, 'error');
            }
        });
    });

    // Eliminar Materia
    $('.btn-delete-subject').on('click', function() {
        if (!confirm(auraCalData.i18n.confirm_delete)) return;
        var subjId = $(this).data('subject-id');
        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_delete_subject',
            nonce: auraCalData.nonce,
            id: subjId
        }, function(res) {
            if (res && res.success) {
                showToast(res.data.message || auraCalData.i18n.saved);
                setTimeout(function() { location.reload(); }, 600);
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error, 'error');
            }
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 7. CALIFICACIONES (TAB 3)
    // ─────────────────────────────────────────────────────────────

    $('#grades-filter-program').on('change', function() {
        var progId = $(this).val();
        var $subj = $('#grades-filter-subject');
        $subj.html('<option value="">Selecciona una materia...</option>').prop('disabled', true);
        $('#btn-load-grades').prop('disabled', true);

        if (progId) {
            $.post(auraCalData.ajax_url, {
                action: 'aura_cal_get_subjects',
                nonce: auraCalData.nonce,
                program_id: progId
            }, function(res) {
                if (res && res.success && res.data.subjects) {
                    $.each(res.data.subjects, function(i, s) {
                        $subj.append($('<option>', {
                            value: s.id,
                            text: s.name + (s.code ? ' (' + s.code + ')' : '')
                        }));
                    });
                    $subj.prop('disabled', false);
                }
            });
        }
    });

    $('#grades-filter-subject').on('change', function() {
        $('#btn-load-grades').prop('disabled', !$(this).val());
    });

    $('#btn-load-grades').on('click', function() {
        var progId = $('#grades-filter-program').val();
        var subjId = $('#grades-filter-subject').val();
        if (!progId || !subjId) return;

        $('#grades-placeholder').hide();
        $('#grades-table-container').show();
        $('#grades-table-body').html('<tr><td colspan="5" style="text-align:center;padding:24px;">Cargando calificaciones...</td></tr>');
        $('#btn-new-grade').show();

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_get_grades',
            nonce: auraCalData.nonce,
            program_id: progId,
            subject_id: subjId
        }, function(res) {
            if (res && res.success && res.data.matrix) {
                var matrix = res.data.matrix;
                if (!matrix.length) {
                    $('#grades-table-body').html('<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--aura-text-muted);">No hay estudiantes registrados o inscritos para esta materia.</td></tr>');
                    return;
                }

                var tbody = '';
                $.each(matrix, function(i, row) {
                    var st = row.student;
                    var grades = row.grades || [];
                    var avg = row.final_average;

                    tbody += '<tr style="border-bottom:1px solid var(--aura-border);">';
                    tbody += '<td style="padding:12px 16px;"><strong>' + st.last_name + ', ' + st.first_name + '</strong></td>';
                    tbody += '<td style="padding:12px 16px;color:var(--aura-text-muted);">' + (st.student_code || st.email) + '</td>';
                    tbody += '<td style="padding:12px 16px;">';

                    if (grades.length) {
                        $.each(grades, function(gi, g) {
                            tbody += '<span class="adp-badge badge-slate" style="margin:2px 4px;font-size:11px;" title="' + (g.feedback || '') + '">' +
                                     g.eval_title + ': <strong>' + parseFloat(g.score) + '</strong>/' + parseFloat(g.max_score) +
                                     '</span>';
                        });
                    } else {
                        tbody += '<span style="color:var(--aura-text-muted);font-size:12px;">Sin evaluaciones</span>';
                    }

                    tbody += '</td>';
                    tbody += '<td style="padding:12px 16px;text-align:center;font-weight:700;font-size:15px;color:' + (avg >= 70 ? '#10b981' : (avg !== null ? '#ef4444' : 'var(--aura-text-muted)')) + '">';
                    tbody += avg !== null ? avg + '%' : '—';
                    tbody += '</td>';
                    tbody += '<td style="padding:12px 16px;text-align:center;">';
                    tbody += '<button type="button" class="btn btn-ghost btn-add-st-grade" data-student-id="' + st.student_id + '" data-student-name="' + st.first_name + ' ' + st.last_name + '" style="padding:4px 8px;font-size:12px;" title="Añadir nota">';
                    tbody += '➕';
                    tbody += '</button>';
                    tbody += '</td>';
                    tbody += '</tr>';
                });

                $('#grades-table-body').html(tbody);
            }
        });
    });

    // Registrar nota a estudiante específico
    $(document).on('click', '.btn-add-st-grade', function() {
        var stId = $(this).data('student-id');
        var stName = $(this).data('student-name');
        var progId = $('#grades-filter-program').val();
        var subjId = $('#grades-filter-subject').val();

        $('#form-grade-editor')[0].reset();
        $('#grd-id').val('0');
        $('#grd-prog-id').val(progId);
        $('#grd-subj-id').val(subjId);
        $('#grd-stud-id').val(stId);
        $('#grd-stud-name-display').text(stName);

        openModal('#modal-grade-editor');
    });

    $('#form-grade-editor').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'aura_cal_save_grade' });
        formData.push({ name: 'nonce', value: auraCalData.nonce });

        var $btn = $('#btn-save-grade');
        $btn.prop('disabled', true).text('Guardando...');

        $.post(auraCalData.ajax_url, formData, function(res) {
            $btn.prop('disabled', false).text('💾 Guardar Nota');
            if (res && res.success) {
                showToast(res.data.message || auraCalData.i18n.saved);
                closeModal('#modal-grade-editor');
                $('#btn-load-grades').trigger('click');
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error, 'error');
            }
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 8. TAREAS Y EVALUACIONES (TAB 4)
    // ─────────────────────────────────────────────────────────────

    $('#btn-create-task, #btn-create-first-task').on('click', function() {
        $('#form-task-editor')[0].reset();
        $('#tsk-id').val('0');
        openModal('#modal-task-editor');
    });

    $('#tsk-prog-id').on('change', function() {
        var progId = $(this).val();
        var $subj = $('#tsk-subj-id');
        $subj.html('<option value="">General / Opcional</option>');

        if (progId) {
            $.post(auraCalData.ajax_url, {
                action: 'aura_cal_get_subjects',
                nonce: auraCalData.nonce,
                program_id: progId
            }, function(res) {
                if (res && res.success && res.data.subjects) {
                    $.each(res.data.subjects, function(i, s) {
                        $subj.append($('<option>', {
                            value: s.id,
                            text: s.name + (s.code ? ' (' + s.code + ')' : '')
                        }));
                    });
                }
            });
        }
    });

    $('#form-task-editor').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'aura_cal_save_task' });
        formData.push({ name: 'nonce', value: auraCalData.nonce });

        var $btn = $('#btn-save-task');
        $btn.prop('disabled', true).text('Guardando...');

        $.post(auraCalData.ajax_url, formData, function(res) {
            $btn.prop('disabled', false).text('💾 Guardar Tarea');
            if (res && res.success) {
                showToast(res.data.message || auraCalData.i18n.saved);
                closeModal('#modal-task-editor');
                setTimeout(function() { location.reload(); }, 600);
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error, 'error');
            }
        });
    });

    // Ver entregas de una tarea
    $('.btn-view-submissions').on('click', function() {
        var taskId = $(this).data('task-id');
        var taskTitle = $(this).data('task-title');

        $('#subs-modal-title').text('📥 Entregas — ' + taskTitle);
        $('#subs-container').html('<p style="text-align:center;padding:20px;">Cargando entregas...</p>');
        openModal('#modal-task-submissions');

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_get_submissions',
            nonce: auraCalData.nonce,
            task_id: taskId
        }, function(res) {
            if (res && res.success && res.data.submissions) {
                var subs = res.data.submissions;
                if (!subs.length) {
                    $('#subs-container').html('<p style="text-align:center;padding:24px;color:var(--aura-text-muted);">Aún no se han recibido entregas para esta tarea.</p>');
                    return;
                }

                var html = '<div style="display:flex;flex-direction:column;gap:12px;">';
                $.each(subs, function(i, s) {
                    html += '<div style="border:1px solid var(--aura-border);border-radius:8px;padding:14px;background:var(--aura-surface-alt);">';
                    html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">';
                    html += '<strong>' + s.first_name + ' ' + s.last_name + '</strong>';
                    html += '<span class="adp-badge ' + (s.status === 'graded' ? 'badge-emerald' : 'badge-amber') + '">' + s.status.toUpperCase() + '</span>';
                    html += '</div>';

                    if (s.submission_text) {
                        html += '<p style="font-size:13px;margin:0 0 8px 0;">' + s.submission_text + '</p>';
                    }

                    if (s.score !== null) {
                        html += '<div style="font-size:13px;color:#10b981;font-weight:600;">Nota asignada: ' + s.score + '</div>';
                    }
                    html += '</div>';
                });
                html += '</div>';
                $('#subs-container').html(html);
            }
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 9. AJUSTES Y SINCRONIZACIÓN GOOGLE CALENDAR (TAB 5)
    // ─────────────────────────────────────────────────────────────

    $('#form-calendar-settings').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#btn-save-settings');
        $btn.prop('disabled', true).text('Guardando...');

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_save_settings',
            nonce: auraCalData.nonce,
            cal_name: $('#set-cal-name').val(),
            auto_sync: $('#set-auto-sync').is(':checked') ? '1' : '0',
            teacher_portal_page_id: $('#set-teacher-portal-page').val(),
            teacher_code_prefix: $('#set-teacher-code-prefix').val()
        }, function(res) {
            $btn.prop('disabled', false).text('💾 Guardar Ajustes');
            if (res && res.success) {
                showToast(res.data.message || auraCalData.i18n.saved);
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error, 'error');
            }
        });
    });

    // Auto-crear página con el Portal del Instructor [aura_teacher_portal]
    $('#btn-create-teacher-portal-page').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.prop('disabled', true).text('Creando página...');

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_create_teacher_portal_page',
            nonce: auraCalData.nonce
        }, function(res) {
            $btn.prop('disabled', false).text('🪄 Crear Página Automáticamente');
            if (res && res.success) {
                showToast(res.data.message);
                if (res.data.page_id) {
                    var $select = $('#set-teacher-portal-page');
                    var exists = $select.find('option[value="' + res.data.page_id + '"]').length > 0;
                    if (!exists) {
                        $select.append($('<option>', {
                            value: res.data.page_id,
                            text: 'Portal del Docente (ID: ' + res.data.page_id + ')'
                        }));
                    }
                    $select.val(res.data.page_id);
                }
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                var err = res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error;
                showToast(err, 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('🪄 Crear Página Automáticamente');
            showToast('Error de red al crear la página', 'error');
        });
    });

    // Probar conexión y resolver calendario
    $('#btn-test-gcal-conn').on('click', function() {
        var $btn = $(this);
        var $fb = $('#settings-sync-feedback');

        $btn.prop('disabled', true).text('Verificando con Google...');
        $fb.html('<div class="alert-card alert-info">Conectando con Google Calendar API...</div>').show();

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_gcal_test_sync',
            nonce: auraCalData.nonce
        }, function(res) {
            $btn.prop('disabled', false).text('🔌 Probar Conexión y Vincular Calendario');
            if (res && res.success) {
                $fb.html('<div class="alert-card alert-success">✅ ' + res.data.message + '</div>');
                showToast(res.data.message);
                setTimeout(function() { location.reload(); }, 1200);
            } else {
                var err = res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error;
                $fb.html('<div class="alert-card alert-danger">⚠️ ' + err + '</div>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('🔌 Probar Conexión y Vincular Calendario');
            $fb.html('<div class="alert-card alert-danger">⚠️ Error en la llamada AJAX.</div>');
        });
    });

    // Sincronizar todas las clases futuras
    $('#btn-sync-all-future, #btn-top-sync-gcal').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Sincronizando...');

        showToast(auraCalData.i18n.syncing);

        $.post(auraCalData.ajax_url, {
            action: 'aura_cal_gcal_sync_all',
            nonce: auraCalData.nonce
        }, function(res) {
            $btn.prop('disabled', false).text('🔄 Sincronizar GCal');
            if (res && res.success) {
                showToast(res.data.message || auraCalData.i18n.saved);
                if (calendar) calendar.refetchEvents();
            } else {
                showToast(res && res.data && res.data.message ? res.data.message : auraCalData.i18n.error, 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('🔄 Sincronizar GCal');
            showToast(auraCalData.i18n.error, 'error');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // SINCRONIZACIÓN COLABORATIVA EN TIEMPO REAL (LIVE SYNC)
    // ─────────────────────────────────────────────────────────────
    var lastSyncVersion = auraCalData.last_sync_version || 0;

    function initLiveCollaboration() {
        if (!calendar) return;

        // Añadir indicador verde en la barra superior si no existe
        if (!$('#aura-live-sync-indicator').length) {
            var $indicator = $('<div id="aura-live-sync-indicator" title="' + escapeHtml(auraCalData.i18n.live_sync_active || 'Sincronización en vivo activa') + '" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--aura-text-secondary,#475569);padding:4px 10px;border-radius:20px;background:var(--aura-surface-alt,#f8fafc);border:1px solid var(--aura-border,#e2e8f0);margin-left:8px;"><span style="width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 0 2px rgba(16,185,129,0.25);"></span> <span style="font-weight:600;">En vivo</span></div>');
            $('.adp-section-header, .aura-calendar-toolbar-right, .aura-cal-header-left, .tab-navigation').first().append($indicator);
        }

        setInterval(function() {
            // No refrescar automáticamente si hay algún modal de edición abierto para no interrumpir al usuario
            var hasModalOpen = $('.aura-modal-overlay.active:visible, .aura-modal-overlay.is-active:visible').length > 0;

            $.post(auraCalData.ajax_url, {
                action: 'aura_cal_heartbeat_sync',
                nonce: auraCalData.nonce,
                last_version: lastSyncVersion
            }, function(res) {
                if (res && res.success && res.data) {
                    if (res.data.has_updates) {
                        lastSyncVersion = res.data.current_version;
                        if (!hasModalOpen) {
                            calendar.refetchEvents();
                            var byUser = res.data.updated_by ? ' por ' + res.data.updated_by : '';
                            showToast('🔄 Calendario actualizado' + byUser, 'info');
                        }
                    } else if (res.data.current_version) {
                        lastSyncVersion = res.data.current_version;
                    }
                }
            });
        }, 6500);
    }

    // ─────────────────────────────────────────────────────────────
    // INICIALIZACIÓN AL CARGAR DOM
    // ─────────────────────────────────────────────────────────────
    $(document).ready(function() {
        initFullCalendar();
        initLiveCollaboration();

        // Si la URL contiene action=create, abrir el editor automáticamente
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'create') {
            setTimeout(function() {
                openEventEditor();
            }, 250);
        }
    });

})(jQuery);

