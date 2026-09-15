<!DOCTYPE html>
<html>
<head>
    <title>Test Fix Permisos - pepito-obrero</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; }
        .info { background: #e5f5fa; padding: 15px; border-left: 4px solid #00a0d2; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #0073aa; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .code { background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; overflow-x: auto; white-space: pre; }
    </style>
</head>
<body>
<div class="container">
    <h1>✅ Prueba de Corrección de Permisos</h1>
    
    <?php
    // Cargar WordPress
    require_once(dirname(__FILE__) . '/../../../wp-load.php');
    
    global $wpdb;
    
    $username = 'pepito-obrero';
    $user = get_user_by('login', $username);
    
    if (!$user) {
        echo '<div class="error">❌ Usuario "' . esc_html($username) . '" no encontrado.</div>';
        echo '</div></body></html>';
        exit;
    }
    
    $user_id = $user->ID;
    
    echo '<div class="info">';
    echo '<strong>Usuario:</strong> ' . $user->user_login . ' (ID: ' . $user_id . ')<br>';
    echo '<strong>Permisos:</strong><br>';
    echo '- aura_finance_view_own: ' . (user_can($user_id, 'aura_finance_view_own') ? '✅' : '❌') . '<br>';
    echo '- aura_finance_view_all: ' . (user_can($user_id, 'aura_finance_view_all') ? '✅' : '❌') . '<br>';
    echo '</div>';
    
    // Simular la condición de prepare_items()
    $has_view_own = user_can($user_id, 'aura_finance_view_own');
    $has_view_all = user_can($user_id, 'aura_finance_view_all');
    
    $where_clauses = array('t.deleted_at IS NULL');
    $where_values = array();
    
    if ($has_view_own && !$has_view_all) {
        $where_clauses[] = 't.created_by = %d';
        $where_values[] = $user_id;
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    if (!empty($where_values)) {
        $where_sql = $wpdb->prepare($where_sql, $where_values);
    }
    
    echo '<h2>Consulta SQL Corregida</h2>';
    echo '<div class="code">';
    $query = "SELECT t.*, c.name AS category_name, a.name AS area_name
FROM {$wpdb->prefix}aura_finance_transactions t
LEFT JOIN {$wpdb->prefix}aura_finance_categories c ON c.id = t.category_id
LEFT JOIN {$wpdb->prefix}aura_areas a ON a.id = t.area_id
WHERE $where_sql
ORDER BY t.transaction_date DESC
LIMIT 20";
    echo htmlspecialchars($query);
    echo '</div>';
    
    // Ejecutar la consulta
    $results = $wpdb->get_results($query);
    
    echo '<h2>Resultados</h2>';
    
    if (empty($results)) {
        echo '<div class="error">❌ No se encontraron transacciones (aún hay un problema)</div>';
    } else {
        echo '<div class="success">✅ ¡CORRECCIÓN EXITOSA! Se encontraron ' . count($results) . ' transacciones</div>';
        
        echo '<table>';
        echo '<tr><th>ID</th><th>Tipo</th><th>Monto</th><th>Fecha</th><th>Descripción</th><th>Categoría</th><th>Área</th><th>Estado</th></tr>';
        foreach ($results as $r) {
            $tipo_badge = $r->transaction_type === 'income' ? '💰 Ingreso' : '💸 Egreso';
            echo '<tr>';
            echo '<td>' . esc_html($r->id) . '</td>';
            echo '<td>' . $tipo_badge . '</td>';
            echo '<td>$' . number_format($r->amount, 2) . '</td>';
            echo '<td>' . esc_html($r->transaction_date) . '</td>';
            echo '<td>' . esc_html($r->description) . '</td>';
            echo '<td>' . esc_html($r->category_name ?? '-') . '</td>';
            echo '<td>' . esc_html($r->area_name ?? '-') . '</td>';
            echo '<td>' . esc_html($r->status) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    echo '<hr>';
    echo '<h2>Recomendación</h2>';
    echo '<div class="info">';
    echo '<strong>Cierra sesión y vuelve a iniciar sesión con pepito-obrero</strong> para que los cambios se apliquen completamente.<br><br>';
    echo 'Luego verifica:<br>';
    echo '1. Ve a Transacciones Financieras<br>';
    echo '2. Deberías ver las ' . count($results) . ' transacciones en la lista<br>';
    echo '3. Verifica que puedas crear nuevas transacciones';
    echo '</div>';
    ?>
    
    <hr>
    <p style="text-align: center; color: #666; margin-top: 30px;">
        <small>Prueba ejecutada el <?php echo date('d/m/Y H:i:s'); ?></small>
    </p>
</div>
</body>
</html>
