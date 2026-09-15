<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico de Permisos - pepito-obrero</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #0073aa; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .success { color: #46b450; font-weight: bold; }
        .error { color: #dc3232; font-weight: bold; }
        .warning { color: #ffb900; font-weight: bold; }
        .info { background: #e5f5fa; padding: 15px; border-left: 4px solid #00a0d2; margin: 20px 0; }
        .code { background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; overflow-x: auto; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; margin: 2px; }
        .badge-yes { background: #46b450; color: white; }
        .badge-no { background: #dc3232; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Diagnóstico de Permisos - Usuario: pepito-obrero</h1>
    
    <?php
    // Cargar WordPress
    require_once(dirname(__FILE__) . '/../../../wp-load.php');
    
    global $wpdb;
    
    // Buscar el usuario pepito-obrero
    $username = 'pepito-obrero';
    $user = get_user_by('login', $username);
    
    if (!$user) {
        echo '<div class="error">❌ Usuario "' . esc_html($username) . '" no encontrado en la base de datos.</div>';
        echo '</div></body></html>';
        exit;
    }
    
    $user_id = $user->ID;
    
    echo '<div class="info">';
    echo '<strong>Usuario encontrado:</strong><br>';
    echo 'ID: ' . $user_id . '<br>';
    echo 'Username: ' . $user->user_login . '<br>';
    echo 'Email: ' . $user->user_email . '<br>';
    echo 'Roles: ' . implode(', ', $user->roles) . '<br>';
    echo '</div>';
    
    // 1. VERIFICAR CAPACIDADES
    echo '<h2>1. Capacidades del Usuario</h2>';
    $capabilities_to_check = array(
        'aura_finance_create',
        'aura_finance_edit_own',
        'aura_finance_view_own',
        'aura_finance_delete_own',
        'aura_finance_view_own_dashboard',
        'aura_finance_view_all',
        'aura_areas_view_own',
        'aura_areas_view_all',
        'manage_options'
    );
    
    echo '<table>';
    echo '<tr><th>Capacidad</th><th>Estado</th></tr>';
    foreach ($capabilities_to_check as $cap) {
        $has_cap = user_can($user_id, $cap);
        $status_class = $has_cap ? 'success' : 'error';
        $status_text = $has_cap ? '✅ SÍ' : '❌ NO';
        echo '<tr><td>' . esc_html($cap) . '</td><td class="' . $status_class . '">' . $status_text . '</td></tr>';
    }
    echo '</table>';
    
    // 2. VERIFICAR TRANSACCIONES EN LA BD
    echo '<h2>2. Transacciones del Usuario en la Base de Datos</h2>';
    $transactions = $wpdb->get_results($wpdb->prepare(
        "SELECT id, transaction_type, amount, transaction_date, description, status, created_by, deleted_at 
         FROM {$wpdb->prefix}aura_finance_transactions 
         WHERE created_by = %d 
         ORDER BY created_at DESC",
        $user_id
    ));
    
    if (empty($transactions)) {
        echo '<div class="warning">⚠️ No se encontraron transacciones creadas por este usuario.</div>';
    } else {
        echo '<p class="success">✅ Se encontraron ' . count($transactions) . ' transacciones creadas por este usuario:</p>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Tipo</th><th>Monto</th><th>Fecha</th><th>Descripción</th><th>Estado</th><th>Created By</th><th>Eliminada</th></tr>';
        foreach ($transactions as $t) {
            $deleted = $t->deleted_at ? '<span class="badge badge-no">ELIMINADA</span>' : '<span class="badge badge-yes">ACTIVA</span>';
            echo '<tr>';
            echo '<td>' . esc_html($t->id) . '</td>';
            echo '<td>' . esc_html($t->transaction_type) . '</td>';
            echo '<td>$' . number_format($t->amount, 2) . '</td>';
            echo '<td>' . esc_html($t->transaction_date) . '</td>';
            echo '<td>' . esc_html($t->description) . '</td>';
            echo '<td>' . esc_html($t->status) . '</td>';
            echo '<td>' . esc_html($t->created_by) . '</td>';
            echo '<td>' . $deleted . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    // 3. SIMULAR CONSULTA DE prepare_items()
    echo '<h2>3. Simulación de la Consulta prepare_items()</h2>';
    echo '<div class="info">Esta es la consulta que ejecuta la tabla de transacciones con los filtros de permisos actuales:</div>';
    
    // Simular la lógica de prepare_items()
    $where_clauses = array('deleted_at IS NULL');
    $where_values = array();
    
    // Simular el filtro de permisos tal como está en el código
    $has_view_own = user_can($user_id, 'aura_finance_view_own');
    $has_view_all = user_can($user_id, 'aura_finance_view_all');
    
    echo '<div class="code">';
    echo '<strong>Evaluación de condiciones:</strong><br>';
    echo 'current_user_can(\'aura_finance_view_own\'): ' . ($has_view_own ? 'TRUE' : 'FALSE') . '<br>';
    echo 'current_user_can(\'aura_finance_view_all\'): ' . ($has_view_all ? 'TRUE' : 'FALSE') . '<br>';
    echo '<br>';
    
    if ($has_view_own && !$has_view_all) {
        echo '<strong>✅ Entra en la condición IF:</strong><br>';
        echo 'if (current_user_can(\'aura_finance_view_own\') && !current_user_can(\'aura_finance_view_all\'))<br>';
        echo 'Se agrega filtro: created_by = ' . $user_id . '<br>';
        $where_clauses[] = 'created_by = %d';
        $where_values[] = $user_id;
    } else {
        echo '<strong>❌ NO entra en la condición IF</strong><br>';
        echo 'No se agrega filtro de created_by<br>';
    }
    echo '</div>';
    
    // Construir WHERE
    $where_sql = implode(' AND ', $where_clauses);
    if (!empty($where_values)) {
        $where_sql = $wpdb->prepare($where_sql, $where_values);
    }
    
    echo '<div class="code">';
    echo '<strong>Consulta SQL resultante:</strong><br>';
    $query = "SELECT t.*, c.name AS category_name, a.name AS area_name 
              FROM {$wpdb->prefix}aura_finance_transactions t
              LEFT JOIN {$wpdb->prefix}aura_finance_categories c ON c.id = t.category_id
              LEFT JOIN {$wpdb->prefix}aura_areas a ON a.id = t.area_id
              WHERE $where_sql
              ORDER BY t.transaction_date DESC
              LIMIT 20";
    echo nl2br(esc_html($query));
    echo '</div>';
    
    // Ejecutar la consulta simulada
    $results = $wpdb->get_results("SELECT t.*, c.name AS category_name, a.name AS area_name 
                                   FROM {$wpdb->prefix}aura_finance_transactions t
                                   LEFT JOIN {$wpdb->prefix}aura_finance_categories c ON c.id = t.category_id
                                   LEFT JOIN {$wpdb->prefix}aura_areas a ON a.id = t.area_id
                                   WHERE $where_sql
                                   ORDER BY t.transaction_date DESC
                                   LIMIT 20");
    
    echo '<h3>Resultados de la consulta:</h3>';
    
    // DEBUG: Mostrar detalles de las transacciones Y categorías
    echo '<div class="info">';
    echo '<strong>🔍 DEBUG - Verificación de categorías:</strong><br>';
    
    // Obtener las transacciones con todos los campos
    $trans_full = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aura_finance_transactions WHERE created_by = %d",
        $user_id
    ));
    
    foreach ($trans_full as $t) {
        echo '<hr>';
        echo '<strong>Transacción ID: ' . $t->id . '</strong><br>';
        echo 'Category ID: ' . $t->category_id . '<br>';
        
        // Verificar si la categoría existe y su estado
        $cat_info = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, is_active, deleted_at FROM {$wpdb->prefix}aura_finance_categories WHERE id = %d",
            $t->category_id
        ));
        
        if ($cat_info) {
            echo 'Categoría existe: ✅ ' . $cat_info->name . '<br>';
            echo 'Categoría is_active: ' . ($cat_info->is_active ? '✅ 1' : '❌ 0') . '<br>';
            echo 'Categoría deleted_at: ' . ($cat_info->deleted_at ? '❌ ' . $cat_info->deleted_at : '✅ NULL') . '<br>';
        } else {
            echo 'Categoría existe: ❌ NO ENCONTRADA<br>';
        }
        
        // Verificar si deleted_at de la transacción está NULL
        echo 'Transacción deleted_at: ' . ($t->deleted_at ? '❌ ' . $t->deleted_at : '✅ NULL') . '<br>';
    }
    echo '</div>';
    
    // Probar consulta sin JOIN
    echo '<div class="info">';
    echo '<strong>🔬 Prueba sin LEFT JOIN:</strong><br>';
    $simple_query = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aura_finance_transactions 
         WHERE deleted_at IS NULL AND created_by = %d
         ORDER BY transaction_date DESC LIMIT 20",
        $user_id
    ));
    echo 'Resultados sin JOIN: ' . count($simple_query) . ' transacciones<br>';
    if (!empty($simple_query)) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Category ID</th><th>Area ID</th><th>Deleted At</th></tr>';
        foreach ($simple_query as $sq) {
            echo '<tr>';
            echo '<td>' . $sq->id . '</td>';
            echo '<td>' . $sq->category_id . '</td>';
            echo '<td>' . ($sq->area_id ?? 'NULL') . '</td>';
            echo '<td>' . ($sq->deleted_at ?? '✅ NULL') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // Probar consulta CON JOIN pero especificando tabla en WHERE
    echo '<div class="info">';
    echo '<strong>🔬 Prueba CON LEFT JOIN (calificando deleted_at con t.):</strong><br>';
    $join_query = $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, c.name AS category_name, a.name AS area_name
         FROM {$wpdb->prefix}aura_finance_transactions t
         LEFT JOIN {$wpdb->prefix}aura_finance_categories c ON c.id = t.category_id
         LEFT JOIN {$wpdb->prefix}aura_areas a ON a.id = t.area_id
         WHERE t.deleted_at IS NULL AND t.created_by = %d
         ORDER BY t.transaction_date DESC LIMIT 20",
        $user_id
    ));
    echo 'Resultados con JOIN (t.deleted_at): ' . count($join_query) . ' transacciones<br>';
    if (!empty($join_query)) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Category Name</th><th>Area Name</th></tr>';
        foreach ($join_query as $jq) {
            echo '<tr>';
            echo '<td>' . $jq->id . '</td>';
            echo '<td>' . ($jq->category_name ?? 'NULL') . '</td>';
            echo '<td>' . ($jq->area_name ?? 'NULL') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    if (empty($results)) {
        echo '<div class="error">❌ La consulta con LEFT JOIN no devolvió ningún resultado.</div>';
    } else {
        echo '<div class="success">✅ La consulta devolvió ' . count($results) . ' transacciones:</div>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Tipo</th><th>Monto</th><th>Fecha</th><th>Categoría</th><th>Área</th><th>Created By</th></tr>';
        foreach ($results as $r) {
            echo '<tr>';
            echo '<td>' . esc_html($r->id) . '</td>';
            echo '<td>' . esc_html($r->transaction_type) . '</td>';
            echo '<td>$' . number_format($r->amount, 2) . '</td>';
            echo '<td>' . esc_html($r->transaction_date) . '</td>';
            echo '<td>' . esc_html($r->category_name) . '</td>';
            echo '<td>' . esc_html($r->area_name) . '</td>';
            echo '<td>' . esc_html($r->created_by) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    // 4. VERIFICAR ÁREAS
    echo '<h2>4. Verificación de Áreas</h2>';
    $has_areas_view_own = user_can($user_id, 'aura_areas_view_own');
    $has_areas_view_all = user_can($user_id, 'aura_areas_view_all');
    $has_manage_options = user_can($user_id, 'manage_options');
    
    echo '<div class="code">';
    echo '<strong>Evaluación de filtro de áreas:</strong><br>';
    echo 'current_user_can(\'aura_areas_view_own\'): ' . ($has_areas_view_own ? 'TRUE' : 'FALSE') . '<br>';
    echo 'current_user_can(\'aura_areas_view_all\'): ' . ($has_areas_view_all ? 'TRUE' : 'FALSE') . '<br>';
    echo 'current_user_can(\'manage_options\'): ' . ($has_manage_options ? 'TRUE' : 'FALSE') . '<br>';
    echo 'current_user_can(\'aura_finance_view_own\'): ' . ($has_view_own ? 'TRUE' : 'FALSE') . '<br>';
    echo '<br>';
    
    $applies_area_filter = $has_areas_view_own && !$has_areas_view_all && !$has_manage_options && !$has_view_own;
    if ($applies_area_filter) {
        echo '<strong>⚠️ Se aplicaría filtro de área (responsable de área)</strong><br>';
        $user_area_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aura_areas WHERE responsible_user_id = %d AND status = 'active' LIMIT 1",
            $user_id
        ));
        echo 'Área asignada como responsable: ' . ($user_area_id ? $user_area_id : 'Ninguna') . '<br>';
    } else {
        echo '<strong>✅ NO se aplica filtro de área (view_own tiene prioridad o no es responsable de área)</strong><br>';
    }
    echo '</div>';
    
    // 5. CONCLUSIÓN Y RECOMENDACIONES
    echo '<h2>5. Conclusión y Diagnóstico</h2>';
    
    $has_transactions = !empty($transactions);
    $can_view = !empty($results);
    
    echo '<table>';
    echo '<tr><th>Verificación</th><th>Estado</th></tr>';
    echo '<tr><td>Usuario tiene transacciones en la BD</td><td class="' . ($has_transactions ? 'success' : 'error') . '">' . ($has_transactions ? '✅ SÍ' : '❌ NO') . '</td></tr>';
    echo '<tr><td>Usuario tiene capacidad view_own</td><td class="' . ($has_view_own ? 'success' : 'error') . '">' . ($has_view_own ? '✅ SÍ' : '❌ NO') . '</td></tr>';
    echo '<tr><td>Usuario NO tiene capacidad view_all</td><td class="' . (!$has_view_all ? 'success' : 'error') . '">' . (!$has_view_all ? '✅ SÍ (correcto)' : '❌ NO') . '</td></tr>';
    echo '<tr><td>La consulta devuelve resultados</td><td class="' . ($can_view ? 'success' : 'error') . '">' . ($can_view ? '✅ SÍ' : '❌ NO') . '</td></tr>';
    echo '</table>';
    
    if ($has_transactions && $has_view_own && !$has_view_all && !$can_view) {
        echo '<div class="error">';
        echo '<h3>❌ PROBLEMA DETECTADO</h3>';
        echo '<p>El usuario tiene permisos correctos y transacciones en la BD, pero la consulta NO devuelve resultados.</p>';
        echo '<p><strong>Posibles causas:</strong></p>';
        echo '<ul>';
        echo '<li>Error en la lógica de prepare_items()</li>';
        echo '<li>Filtro adicional que está bloqueando la visualización</li>';
        echo '<li>Problema con get_current_user_id() en el contexto de WordPress</li>';
        echo '</ul>';
        echo '</div>';
    } elseif ($has_transactions && $can_view) {
        echo '<div class="success">';
        echo '<h3>✅ TODO CORRECTO</h3>';
        echo '<p>El usuario puede ver sus transacciones correctamente. El problema podría estar en:</p>';
        echo '<ul>';
        echo '<li>Cache del navegador o de WordPress</li>';
        echo '<li>Sesión de usuario no actualizada</li>';
        echo '<li>Necesita cerrar sesión y volver a iniciar</li>';
        echo '</ul>';
        echo '</div>';
    } elseif (!$has_transactions) {
        echo '<div class="warning">';
        echo '<h3>⚠️ NO HAY TRANSACCIONES</h3>';
        echo '<p>El usuario no tiene transacciones en la base de datos. Debe crear una transacción primero.</p>';
        echo '</div>';
    }
    
    ?>
    
    <hr>
    <p style="text-align: center; color: #666; margin-top: 30px;">
        <small>Diagnóstico generado el <?php echo date('d/m/Y H:i:s'); ?></small>
    </p>
</div>
</body>
</html>
