/**
 * Scripts del Admin de Aura Business Suite
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Confirmación de eliminación
        $('.delete-link').on('click', function(e) {
            if (!confirm(auraData.strings.confirmDelete)) {
                e.preventDefault();
                return false;
            }
        });
        
        // Exportar a PDF
        $('#aura-export-pdf').on('click', function(e) {
            e.preventDefault();
            alert('Función de exportación a PDF en desarrollo');
            // TODO: Implementar exportación real
        });
        
        // Exportar a Excel
        $('#aura-export-excel').on('click', function(e) {
            e.preventDefault();
            alert('Función de exportación a Excel en desarrollo');
            // TODO: Implementar exportación real
        });
        
        // Auto-completar kilometraje al seleccionar vehículo en salidas
        $('#aura_vehicle').on('change', function() {
            var vehicleId = $(this).val();
            if (!vehicleId) return;
            
            // AJAX para obtener kilometraje actual del vehículo
            $.ajax({
                url: auraData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aura_get_vehicle_km',
                    nonce: auraData.nonce,
                    vehicle_id: vehicleId
                },
                success: function(response) {
                    if (response.success) {
                        $('#aura_exit_km').val(response.data.current_km);
                    }
                }
            });
        });
        
        // Validación en tiempo real para transacciones
        $('#aura_amount').on('input', function() {
            var amount = parseFloat($(this).val());
            if (isNaN(amount) || amount < 0) {
                $(this).css('border-color', '#ef4444');
            } else {
                $(this).css('border-color', '#d1d5db');
            }
        });
        
        // Validación de fechas (excluye campos marcados con data-allow-future)
        $('input[type="date"]').on('change', function() {
            if ($(this).data('allow-future')) return; // presupuestos u otros campos que permiten fechas futuras
            var selectedDate = new Date($(this).val());
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate > today) {
                alert('La fecha no puede ser futura');
                $(this).val('');
            }
        });
        
        // Contador de caracteres en textareas
        $('textarea').each(function() {
            var maxLength = $(this).attr('maxlength');
            if (maxLength) {
                var counter = $('<div class="char-counter"></div>');
                $(this).after(counter);
                updateCounter($(this), counter);
                
                $(this).on('input', function() {
                    updateCounter($(this), counter);
                });
            }
        });
        
        function updateCounter($textarea, $counter) {
            var current = $textarea.val().length;
            var max = $textarea.attr('maxlength');
            $counter.text(current + ' / ' + max);
            
            if (current > max * 0.9) {
                $counter.css('color', '#ef4444');
            } else {
                $counter.css('color', '#6b7280');
            }
        }
        
        // Tooltip para iconos
        $('[data-tooltip]').hover(
            function() {
                var tooltip = $('<div class="aura-tooltip">' + $(this).data('tooltip') + '</div>');
                $('body').append(tooltip);
                
                var pos = $(this).offset();
                tooltip.css({
                    top: pos.top - tooltip.outerHeight() - 10,
                    left: pos.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2)
                }).fadeIn(200);
            },
            function() {
                $('.aura-tooltip').fadeOut(200, function() {
                    $(this).remove();
                });
            }
        );
        
        // Búsqueda en tiempo real en tablas
        $('#aura-table-search').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });
        
        // Seleccionar/deseleccionar todas las capabilities
        $('.aura-select-all-caps').on('change', function() {
            var module = $(this).data('module');
            $('.permission-checkbox[data-module="' + module + '"]').prop('checked', $(this).is(':checked'));
        });
        
        // Copiar permisos de otro usuario
        $('#aura-copy-permissions').on('change', function() {
            var userId = $(this).val();
            if (!userId) return;
            
            if (confirm('¿Estás seguro de copiar los permisos de este usuario? Se reemplazarán los permisos actuales.')) {
                // AJAX para copiar permisos
                $.ajax({
                    url: auraData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aura_copy_permissions',
                        nonce: auraData.nonce,
                        source_user_id: userId,
                        target_user_id: $('#user_id').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(auraData.strings.error);
                        }
                    }
                });
            }
        });
    });
    
})(jQuery);

/**
 * AJAX handler: Obtener kilometraje del vehículo
 */
jQuery(document).ready(function($) {
    window.addEventListener('load', function() {
        wp.hooks.addAction('aura_vehicle_selected', 'aura', function(vehicleId) {
            console.log('Vehículo seleccionado:', vehicleId);
        });
    });
});
