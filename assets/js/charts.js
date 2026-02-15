/**
 * Configuración de Gráficos con Chart.js
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // === Gráfico Financiero: Ingresos vs Egresos ===
        if ($('#aura-income-expense-chart').length && typeof auraChartData !== 'undefined') {
            const ctx1 = document.getElementById('aura-income-expense-chart').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: auraChartData.incomeExpense,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': $' + context.parsed.y.toLocaleString('es-ES', {minimumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-ES');
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // === Gráfico Financiero: Distribución por Categorías ===
        if ($('#aura-category-chart').length && typeof auraChartData !== 'undefined') {
            const ctx2 = document.getElementById('aura-category-chart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: auraChartData.categories,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': $' + value.toLocaleString('es-ES', {minimumFractionDigits: 2}) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // === Gráfico de Electricidad: Consumo Diario ===
        if ($('#aura-electricity-chart').length && typeof auraElectricityData !== 'undefined') {
            const ctx3 = document.getElementById('aura-electricity-chart').getContext('2d');
            new Chart(ctx3, {
                type: 'line',
                data: auraElectricityData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' kWh';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' kWh';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // === Gráfico de Electricidad: Comparativa Mensual ===
        if ($('#aura-electricity-comparison-chart').length) {
            const ctx4 = document.getElementById('aura-electricity-comparison-chart').getContext('2d');
            
            // Datos de ejemplo - deberían venir del backend
            const comparisonData = {
                labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
                datasets: [{
                    label: 'Mes Actual',
                    data: [450, 480, 520, 490, 510, 0],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Mes Anterior',
                    data: [420, 460, 500, 470, 490, 505],
                    borderColor: 'rgb(156, 163, 175)',
                    backgroundColor: 'rgba(156, 163, 175, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderDash: [5, 5]
                }]
            };
            
            new Chart(ctx4, {
                type: 'line',
                data: comparisonData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' kWh';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // === Gráfico de Vehículos: Salidas por Tipo ===
        if ($('#aura-exits-by-type-chart').length) {
            const ctx5 = document.getElementById('aura-exits-by-type-chart').getContext('2d');
            
            // Datos de ejemplo - deberían venir del backend
            const exitsData = {
                labels: ['Mantenimiento', 'Reparación', 'Renta', 'Uso Personal'],
                datasets: [{
                    label: 'Cantidad de Salidas',
                    data: [12, 8, 15, 25],
                    backgroundColor: [
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(59, 130, 246, 0.8)'
                    ],
                    borderColor: [
                        'rgb(245, 158, 11)',
                        'rgb(239, 68, 68)',
                        'rgb(16, 185, 129)',
                        'rgb(59, 130, 246)'
                    ],
                    borderWidth: 2
                }]
            };
            
            new Chart(ctx5, {
                type: 'pie',
                data: exitsData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ' + value + ' salidas (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Configuración global de Chart.js
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 15;
    });
    
})(jQuery);
