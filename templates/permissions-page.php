<?php
/**
 * Template: Página de Gestión de Permisos
 *
 * @package AuraBusinessSuite
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('aura_admin_permissions_assign') && !current_user_can('aura_admin_users_create')) {
    wp_die(__('No tienes permiso para acceder a esta página.', 'aura-suite'));
}

// Procesar asignación de permisos
if (isset($_POST['aura_assign_permissions']) && isset($_POST['user_id']) && wp_verify_nonce($_POST['aura_permissions_nonce'], 'assign_permissions')) {
    $user_id = intval($_POST['user_id']);
    $user = get_user_by('id', $user_id);
    
    if ($user) {
        // Obtener todas las capabilities de Aura
        $all_caps = Aura_Roles_Manager::get_all_capabilities();
        
        // Remover todas las capabilities de Aura primero
        foreach ($all_caps as $module => $caps) {
            foreach ($caps as $cap => $desc) {
                $user->remove_cap($cap);
            }
        }
        
        // Agregar capabilities seleccionadas
        if (isset($_POST['capabilities']) && is_array($_POST['capabilities'])) {
            foreach ($_POST['capabilities'] as $capability) {
                $user->add_cap(sanitize_text_field($capability));
            }
        }
        
        // Procesar asignación de áreas
        $selected_areas = isset($_POST['user_areas']) && is_array($_POST['user_areas']) 
            ? array_map('absint', $_POST['user_areas']) 
            : [];
        
        // Obtener todas las áreas para actualizar relaciones
        $all_areas = Aura_Areas_Setup::get_all_areas();
        
        foreach ($all_areas as $area) {
            $area_id = (int) $area->id;
            
            if (in_array($area_id, $selected_areas)) {
                // El usuario debe estar en esta área
                if (!Aura_Areas_Setup::is_user_in_area($area_id, $user_id)) {
                    // Agregar usuario al área (manteniendo otros usuarios existentes)
                    $current_users = Aura_Areas_Setup::get_area_users($area_id);
                    $user_ids = array_column($current_users, 'user_id');
                    $user_ids[] = $user_id;
                    $user_ids = array_unique($user_ids);
                    Aura_Areas_Setup::assign_users_to_area($area_id, $user_ids);
                }
            } else {
                // El usuario NO debe estar en esta área - removerlo si está
                if (Aura_Areas_Setup::is_user_in_area($area_id, $user_id)) {
                    $current_users = Aura_Areas_Setup::get_area_users($area_id);
                    $user_ids = array_column($current_users, 'user_id');
                    $user_ids = array_diff($user_ids, [$user_id]);
                    Aura_Areas_Setup::assign_users_to_area($area_id, array_values($user_ids));
                }
            }
        }
        
        // Redirigir de vuelta al usuario con mensaje de éxito
        wp_redirect(add_query_arg([
            'page' => 'aura-permissions',
            'user_id' => $user_id,
            'updated' => 'true'
        ], admin_url('admin.php')));
        exit;
    }
}

// Mostrar mensaje de éxito si viene de actualización
if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Permisos y áreas actualizados exitosamente.', 'aura-suite') . '</p></div>';
}

// Obtener usuario seleccionado
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$selected_user = $selected_user_id ? get_user_by('id', $selected_user_id) : null;

// Obtener todos los usuarios
$all_users = get_users(array('orderby' => 'display_name'));

// Usuarios que hacen parte de Aura Suite (por capabilities Aura o por asignación a áreas)
$aura_caps_map  = Aura_Roles_Manager::get_all_capabilities();
$aura_caps_flat = array();
foreach ( $aura_caps_map as $module_caps ) {
    foreach ( $module_caps as $cap_key => $cap_label ) {
        $aura_caps_flat[] = $cap_key;
    }
}

$aura_users = array();
foreach ( $all_users as $listed_user ) {
    $aura_caps_count = 0;
    foreach ( $aura_caps_flat as $aura_cap_key ) {
        if ( user_can( $listed_user, $aura_cap_key ) ) {
            $aura_caps_count++;
        }
    }

    $user_areas_for_list = Aura_Areas_Setup::get_user_areas( $listed_user->ID );
    $areas_count = is_array( $user_areas_for_list ) ? count( $user_areas_for_list ) : 0;

    if ( $aura_caps_count === 0 && $areas_count === 0 ) {
        continue;
    }

    $aura_users[] = array(
        'user'            => $listed_user,
        'avatar'          => get_avatar_url( $listed_user->ID, array( 'size' => 44 ) ),
        'caps_count'      => $aura_caps_count,
        'areas_count'     => $areas_count,
        'roles'           => implode( ', ', array_map( 'ucfirst', $listed_user->roles ) ),
        'is_selected'     => (int) $selected_user_id === (int) $listed_user->ID,
    );
}

?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-admin-users" style="font-size: 32px; margin-right: 10px;"></span>
        <?php _e('Gestión de Permisos Granulares (CBAC)', 'aura-suite'); ?>
    </h1>
    
    <p class="description" style="font-size: 14px; margin-bottom: 20px;">
        <?php _e('Asigna capabilities específicas a cada usuario según sus responsabilidades. Los permisos se organizan por módulo.', 'aura-suite'); ?>
    </p>
    
    <!-- Mostrar mensaje de usuario recién creado -->
    <?php if (isset($_GET['created']) && $_GET['created'] === '1' && $selected_user): ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <strong><?php _e('Usuario creado exitosamente.', 'aura-suite'); ?></strong>
            <?php printf(__('Ahora puedes asignar los permisos de %s.', 'aura-suite'), esc_html($selected_user->display_name)); ?>
        </p>
    </div>
    <?php endif; ?>

    <?php if ( $selected_user ) :
        $selected_avatar = get_avatar_url( $selected_user->ID, array( 'size' => 64 ) );
        $selected_caps_count = 0;
        $selected_caps_ui = Aura_Roles_Manager::get_capabilities_for_ui();
        foreach ( $selected_caps_ui as $selected_module_caps ) {
            foreach ( $selected_module_caps['capabilities'] as $selected_cap_name => $selected_cap_info ) {
                if ( ! empty( $selected_user->allcaps[ $selected_cap_name ] ) ) {
                    $selected_caps_count++;
                }
            }
        }
        $selected_user_areas = Aura_Areas_Setup::get_user_areas( $selected_user->ID );
        $selected_areas_count = is_array( $selected_user_areas ) ? count( $selected_user_areas ) : 0;
    ?>
    <div class="aura-selected-user-card aura-selected-user-card--sticky aura-selected-user-card--global">
        <img src="<?php echo esc_url( $selected_avatar ); ?>"
             alt="<?php echo esc_attr( $selected_user->display_name ); ?>"
             class="aura-selected-user-card__avatar" />
        <div class="aura-selected-user-card__meta">
            <h3><?php echo esc_html( $selected_user->display_name ); ?></h3>
            <p><?php echo esc_html( $selected_user->user_email ); ?></p>
            <div class="aura-selected-user-card__badges">
                <span class="aura-count-badge aura-count-badge--blue"><?php printf( esc_html__( 'Permisos activos: %d', 'aura-suite' ), (int) $selected_caps_count ); ?></span>
                <span class="aura-count-badge aura-count-badge--green"><?php printf( esc_html__( 'Áreas: %d', 'aura-suite' ), (int) $selected_areas_count ); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (current_user_can('aura_admin_users_create')): ?>
    <!-- Crear Nuevo Usuario -->
    <div class="aura-config-section">
        <h2><?php _e('➕ Crear Nuevo Usuario', 'aura-suite'); ?></h2>
        <p class="description"><?php _e('Registra un nuevo usuario con rol Suscriptor. Luego podrás asignarle los permisos necesarios.', 'aura-suite'); ?></p>
        <button type="button" id="aura-btn-nuevo-usuario" class="button button-primary" style="margin-top:8px;">
            <span class="dashicons dashicons-plus-alt" style="margin-top:4px;"></span>
            <?php _e('Nuevo Usuario', 'aura-suite'); ?>
        </button>

        <!-- Modal Crear Usuario -->
        <div id="aura-modal-crear-usuario" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:99999; align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:12px; padding:32px; width:100%; max-width:480px; box-shadow:0 8px 32px rgba(0,0,0,.2); position:relative;">
                <button type="button" id="aura-modal-cerrar" style="position:absolute; top:12px; right:14px; background:none; border:none; font-size:22px; cursor:pointer; color:#6b7280;">&times;</button>
                <h3 style="margin:0 0 20px 0; font-size:18px; color:#1f2937;">
                    <span class="dashicons dashicons-admin-users" style="color:#2271b1; margin-right:6px;"></span>
                    <?php _e('Crear Nuevo Usuario', 'aura-suite'); ?>
                </h3>
                <div id="aura-crear-usuario-error" style="display:none; background:#fef2f2; border-left:4px solid #ef4444; padding:10px 14px; border-radius:4px; margin-bottom:16px; color:#b91c1c; font-size:13px;"></div>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="padding:8px 0; width:130px;"><label for="aura_cu_first_name"><?php _e('Nombre *', 'aura-suite'); ?></label></th>
                        <td style="padding:8px 0;"><input type="text" id="aura_cu_first_name" class="regular-text" placeholder="<?php esc_attr_e('Nombre', 'aura-suite'); ?>" required></td>
                    </tr>
                    <tr>
                        <th style="padding:8px 0;"><label for="aura_cu_last_name"><?php _e('Apellido', 'aura-suite'); ?></label></th>
                        <td style="padding:8px 0;"><input type="text" id="aura_cu_last_name" class="regular-text" placeholder="<?php esc_attr_e('Apellido', 'aura-suite'); ?>"></td>
                    </tr>
                    <tr>
                        <th style="padding:8px 0;"><label for="aura_cu_email"><?php _e('Email *', 'aura-suite'); ?></label></th>
                        <td style="padding:8px 0;"><input type="email" id="aura_cu_email" class="regular-text" placeholder="correo@ejemplo.com" required></td>
                    </tr>
                    <tr>
                        <th style="padding:8px 0;"><label for="aura_cu_phone"><?php _e('Teléfono', 'aura-suite'); ?></label></th>
                        <td style="padding:8px 0;"><input type="text" id="aura_cu_phone" class="regular-text" placeholder="+57 300 000 0000"></td>
                    </tr>
                    <tr>
                        <th style="padding:8px 0;"><label for="aura_cu_password"><?php _e('Contraseña *', 'aura-suite'); ?></label></th>
                        <td style="padding:8px 0;">
                            <div style="position:relative; display:inline-block;">
                                <input type="password" id="aura_cu_password" class="regular-text" placeholder="<?php esc_attr_e('Mín. 8 caracteres', 'aura-suite'); ?>" required style="padding-right:36px;">
                                <button type="button" id="aura-toggle-pwd" style="position:absolute; right:6px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#6b7280; padding:0;" title="<?php esc_attr_e('Mostrar/ocultar', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </div>
                            <button type="button" id="aura-generar-pwd" class="button" style="margin-left:6px;"><?php _e('Generar', 'aura-suite'); ?></button>
                        </td>
                    </tr>
                </table>
                <p style="font-size:12px; color:#6b7280; margin:12px 0 20px;">
                    <?php _e('El usuario se creará con rol <strong>Suscriptor</strong>. Podrás asignarle permisos de Aura inmediatamente después.', 'aura-suite'); ?>
                </p>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" id="aura-modal-cancelar" class="button"><?php _e('Cancelar', 'aura-suite'); ?></button>
                    <button type="button" id="aura-btn-guardar-usuario" class="button button-primary">
                        <span class="dashicons dashicons-saved" style="margin-top:4px;"></span>
                        <?php _e('Crear y asignar permisos', 'aura-suite'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="aura-config-section">
        <h2><?php _e('1️⃣ Usuarios Activos en Aura Suite', 'aura-suite'); ?></h2>
        <p class="description"><?php _e('Usa esta tabla como flujo único: busca el usuario y haz clic en "Editar permisos".', 'aura-suite'); ?></p>

        <div class="aura-user-picker__search-wrap" style="margin-top:10px; max-width:460px;">
            <span class="dashicons dashicons-search"></span>
            <input type="text" id="aura-users-table-search" class="aura-user-picker__search"
                   placeholder="<?php esc_attr_e('Buscar por nombre, correo o rol...', 'aura-suite'); ?>">
        </div>

        <div class="aura-users-table-wrap">
            <table id="aura-users-table" class="widefat fixed striped aura-users-table display nowrap" style="width:100%;">
                <thead>
                    <tr>
                        <th><?php _e('Usuario', 'aura-suite'); ?></th>
                        <th><?php _e('Rol(es)', 'aura-suite'); ?></th>
                        <th><?php _e('Permisos Aura', 'aura-suite'); ?></th>
                        <th><?php _e('Áreas', 'aura-suite'); ?></th>
                        <th><?php _e('Acción', 'aura-suite'); ?></th>
                    </tr>
                </thead>
                <tbody id="aura-users-table-body">
                    <?php if ( ! empty( $aura_users ) ) : ?>
                        <?php foreach ( $aura_users as $aura_row ) :
                            $row_user = $aura_row['user'];
                            $row_url  = add_query_arg(
                                array(
                                    'page'    => 'aura-permissions',
                                    'user_id' => (int) $row_user->ID,
                                ),
                                admin_url( 'admin.php' )
                            );
                            $row_search = strtolower( $row_user->display_name . ' ' . $row_user->user_email . ' ' . $aura_row['roles'] );
                        ?>
                        <tr data-user-search="<?php echo esc_attr( $row_search ); ?>" class="<?php echo $aura_row['is_selected'] ? 'is-selected' : ''; ?>">
                            <td>
                                <div class="aura-users-table__identity">
                                    <img src="<?php echo esc_url( $aura_row['avatar'] ); ?>" alt="<?php echo esc_attr( $row_user->display_name ); ?>">
                                    <div>
                                        <strong><?php echo esc_html( $row_user->display_name ); ?></strong>
                                        <span><?php echo esc_html( $row_user->user_email ); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo esc_html( $aura_row['roles'] ?: '—' ); ?></td>
                            <td>
                                <span class="aura-count-badge aura-count-badge--blue"><?php echo (int) $aura_row['caps_count']; ?></span>
                            </td>
                            <td>
                                <span class="aura-count-badge aura-count-badge--green"><?php echo (int) $aura_row['areas_count']; ?></span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $row_url ); ?>" class="button button-secondary">
                                    <?php _e( 'Editar permisos', 'aura-suite' ); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5"><?php _e( 'Aún no hay usuarios con permisos o asignaciones de Aura Suite.', 'aura-suite' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
    
    <?php if ($selected_user): ?>
    
    <!-- Plantillas Predefinidas -->
    <div class="aura-config-section">
        <h2><?php _e('2️⃣ Aplicar Plantilla Predefinida (Opcional)', 'aura-suite'); ?></h2>
        <p class="description"><?php _e('Puedes cargar un perfil predefinido como punto de partida:', 'aura-suite'); ?></p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
            <?php
            $templates = Aura_Roles_Manager::get_profile_templates();
            foreach ($templates as $template_id => $template):
            ?>
            <div style="border: 2px solid #e5e7eb; border-radius: 8px; padding: 15px; cursor: pointer;" 
                 onclick="applyTemplate('<?php echo $template_id; ?>')">
                <h4 style="margin: 0 0 8px 0;"><?php echo esc_html($template['name']); ?></h4>
                <p style="margin: 0; font-size: 12px; color: #6b7280;"><?php echo esc_html($template['description']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Formulario de Permisos -->
    <form method="post" action="" id="aura-perm-form">
        <?php wp_nonce_field('assign_permissions', 'aura_permissions_nonce'); ?>
        <input type="hidden" name="user_id" value="<?php echo $selected_user->ID; ?>">
        
        <!-- Sección: Asignar Áreas/Programas al Usuario -->
        <div class="aura-config-section">
            <h2><?php _e('🏢 Asignar Áreas/Programas', 'aura-suite'); ?></h2>
            <p class="description"><?php _e('Asigna este usuario como responsable de una o más áreas/programas. Podrá ver presupuestos y transacciones relacionadas a estas áreas.', 'aura-suite'); ?></p>
            
            <div id="user-areas-assignment" style="margin-top: 15px;">
                <?php
                // Obtener áreas asignadas al usuario
                $user_areas = Aura_Areas_Setup::get_user_areas( $selected_user->ID );
                
                // Obtener todas las áreas disponibles
                $all_areas = Aura_Areas_Setup::get_all_areas();
                ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                    <?php foreach ($all_areas as $area): 
                        $is_assigned = false;
                        foreach ($user_areas as $user_area) {
                            if ($user_area->id == $area->id) {
                                $is_assigned = true;
                                break;
                            }
                        }
                    ?>
                    <label style="border: 2px solid <?php echo $is_assigned ? $area->color : '#e5e7eb'; ?>; border-radius: 8px; padding: 12px; cursor: pointer; display: flex; align-items: center; background: <?php echo $is_assigned ? $area->color . '10' : '#fff'; ?>;">
                        <input type="checkbox" 
                               name="user_areas[]" 
                               value="<?php echo $area->id; ?>"
                               <?php checked($is_assigned); ?>
                               style="margin-right: 10px;">
                        <span class="dashicons <?php echo esc_attr($area->icon); ?>" 
                              style="color: <?php echo esc_attr($area->color); ?>; margin-right: 8px;"></span>
                        <div>
                            <strong><?php echo esc_html($area->name); ?></strong>
                            <br>
                            <small style="color: #6b7280;"><?php echo esc_html($area->type); ?></small>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($all_areas)): ?>
                <p style="color: #6b7280; font-style: italic;">
                    <?php _e('No hay áreas disponibles. Crea áreas primero en Áreas y Programas.', 'aura-suite'); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ══ 3️⃣ Asignar Capabilities Individuales ══ -->
        <div class="aura-config-section" id="aura-perm-caps-section">

            <!-- Header de sección con controles globales -->
            <div class="aura-perm-section-header">
                <div>
                    <h2 style="margin:0 0 4px;"><?php _e('3️⃣ Asignar Capabilities Individuales', 'aura-suite'); ?></h2>
                    <p class="description" style="margin:0;"><?php _e('Selecciona los permisos que necesita este usuario. Los módulos siguen el orden del dashboard principal.', 'aura-suite'); ?></p>
                </div>
                <div class="aura-perm-global-controls">
                    <button type="button" class="button" id="aura-expand-all-btn">
                        <span class="dashicons dashicons-arrow-down-alt2" style="margin-top:4px;"></span>
                        <?php _e('Expandir', 'aura-suite'); ?>
                    </button>
                    <button type="button" class="button" id="aura-collapse-all-btn">
                        <span class="dashicons dashicons-arrow-up-alt2" style="margin-top:4px;"></span>
                        <?php _e('Colapsar', 'aura-suite'); ?>
                    </button>
                </div>
            </div>

            <!-- Barra de búsqueda -->
            <div class="aura-perm-search-bar">
                <span class="dashicons dashicons-search" style="color:#9ca3af;"></span>
                <input type="text" id="aura-cap-search"
                       placeholder="<?php esc_attr_e('Buscar permiso por nombre...', 'aura-suite'); ?>"
                       class="aura-perm-search-input">
                <span id="aura-search-count" class="aura-perm-search-count" style="display:none;"></span>
            </div>

            <?php
            $modules    = Aura_Roles_Manager::get_capabilities_for_ui();
            $user_caps  = $selected_user->allcaps;
            ?>

            <!-- Leyenda -->
            <p style="font-size:12px; color:#9ca3af; margin:0 0 12px;">
                <abbr title="<?php esc_attr_e('Permiso administrativo sensible — asignar con precaución', 'aura-suite'); ?>">⭐</abbr>
                <?php _e('= Permiso administrativo sensible', 'aura-suite'); ?>
            </p>

            <!-- Accordion de módulos -->
            <div class="aura-perm-accordion" id="aura-perm-accordion">

                <?php foreach ($modules as $module):
                    $module_key   = $module['module'];
                    $total_caps   = count($module['capabilities']);
                    $active_count = 0;
                    foreach ($module['capabilities'] as $cap_name => $cap_info) {
                        if (!empty($user_caps[$cap_name])) $active_count++;
                    }
                    $all_selected = ($active_count === $total_caps && $total_caps > 0);
                    $some_selected = ($active_count > 0 && $active_count < $total_caps);
                    $is_open = ($active_count > 0);
                ?>
                <div class="aura-perm-module aura-perm-module--<?php echo esc_attr($module_key); ?><?php echo $is_open ? ' is-open' : ''; ?>"
                     data-module="<?php echo esc_attr($module_key); ?>">

                    <!-- Cabecera clickeable del módulo -->
                    <div class="aura-perm-module-header"
                         role="button" tabindex="0"
                         aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">

                        <div class="aura-perm-module-meta">
                            <span class="aura-perm-module-emoji"><?php echo $module['icon']; ?></span>
                            <span class="aura-perm-module-name"><?php echo esc_html($module['title']); ?></span>
                            <?php if ($active_count > 0): ?>
                            <span class="aura-perm-module-badge aura-perm-module-badge--active">
                                <?php echo $active_count; ?>/<?php echo $total_caps; ?>
                            </span>
                            <?php else: ?>
                            <span class="aura-perm-module-badge"><?php echo $total_caps; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="aura-perm-module-actions">
                            <label class="aura-perm-select-all" onclick="event.stopPropagation()" title="<?php esc_attr_e('Seleccionar / deseleccionar todos los permisos de este módulo', 'aura-suite'); ?>">
                                <input type="checkbox"
                                       class="select-all-module"
                                       data-module="<?php echo esc_attr($module_key); ?>"
                                       <?php checked($all_selected); ?>
                                       data-indeterminate="<?php echo $some_selected ? 'true' : 'false'; ?>">
                                <span><?php _e('Todos', 'aura-suite'); ?></span>
                            </label>
                            <span class="aura-perm-chevron dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                    </div>

                    <!-- Cuerpo colapsable -->
                    <div class="aura-perm-module-body">
                        <div class="aura-perm-cap-grid">
                            <?php foreach ($module['capabilities'] as $cap_name => $cap_info):
                                $is_active = !empty($user_caps[$cap_name]);
                                $is_star   = !empty($cap_info['star']);
                                $item_classes = 'aura-perm-cap-item';
                                if ($is_active) $item_classes .= ' is-active';
                                if ($is_star)   $item_classes .= ' is-star';
                            ?>
                            <label class="<?php echo $item_classes; ?>"
                                   data-cap-label="<?php echo esc_attr(strtolower($cap_info['label'])); ?>">
                                <input type="checkbox"
                                       id="cap_<?php echo esc_attr($cap_name); ?>"
                                       name="capabilities[]"
                                       value="<?php echo esc_attr($cap_name); ?>"
                                       data-module="<?php echo esc_attr($module_key); ?>"
                                       <?php checked($is_active); ?>>
                                <span class="aura-perm-cap-text">
                                    <?php echo esc_html($cap_info['label']); ?>
                                    <?php if ($is_star): ?>
                                    <abbr class="aura-perm-star"
                                          title="<?php esc_attr_e('Permiso administrativo — úsalo con cuidado', 'aura-suite'); ?>">⭐</abbr>
                                    <?php endif; ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div><!-- /.aura-perm-module-body -->
                </div><!-- /.aura-perm-module -->
                <?php endforeach; ?>

            </div><!-- /.aura-perm-accordion -->
        </div><!-- /.aura-config-section capabilities -->

        <p class="submit" style="margin-bottom:80px;">
            <button type="submit" name="aura_assign_permissions" class="button button-primary button-hero">
                <span class="dashicons dashicons-saved" style="margin-top: 6px;"></span>
                <?php _e('Guardar Permisos', 'aura-suite'); ?>
            </button>
        </p>
    </form>

    <!-- Barra guardado flotante -->
    <div class="aura-perm-sticky-save" id="aura-perm-sticky-save">
        <div class="aura-perm-sticky-save-inner">
            <span class="aura-perm-sticky-total" id="aura-perm-sticky-total">
                <?php
                $all_caps_ui  = Aura_Roles_Manager::get_capabilities_for_ui();
                $total_active = 0;
                foreach ($all_caps_ui as $_m) {
                    foreach ($_m['capabilities'] as $_cn => $_ci) {
                        if (!empty($user_caps[$_cn])) $total_active++;
                    }
                }
                echo '<strong>' . $total_active . '</strong> ' . _n('permiso activo', 'permisos activos', $total_active, 'aura-suite');
                ?>
            </span>
            <button type="submit"
                    id="aura-perm-sticky-btn"
                    name="aura_assign_permissions"
                    value="1"
                    form="aura-perm-form"
                    class="button button-primary button-hero">
                <span class="dashicons dashicons-saved" style="margin-top:6px;"></span>
                <?php _e('Guardar Permisos', 'aura-suite'); ?>
            </button>
        </div>
    </div>
    
    <!-- Resumen de Permisos Actuales -->
    <div class="aura-config-section">
        <h2><?php _e('Resumen de Permisos y Áreas Actuales', 'aura-suite'); ?></h2>
        <p><strong><?php _e('Usuario:', 'aura-suite'); ?></strong> <?php echo $selected_user->display_name; ?></p>
        
        <!-- Áreas asignadas -->
        <h3><?php _e('Áreas/Programas Asignados:', 'aura-suite'); ?></h3>
        <?php
        $user_areas = Aura_Areas_Setup::get_user_areas( $selected_user->ID );
        if (!empty($user_areas)):
        ?>
        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin: 15px 0;">
            <?php foreach ($user_areas as $area): ?>
            <div style="border: 2px solid <?php echo esc_attr($area->color); ?>; border-radius: 6px; padding: 8px 12px; display: inline-flex; align-items: center; background: <?php echo esc_attr($area->color); ?>15;">
                <span class="dashicons <?php echo esc_attr($area->icon); ?>" style="color: <?php echo esc_attr($area->color); ?>; margin-right: 6px;"></span>
                <strong><?php echo esc_html($area->name); ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color: #6b7280;"><?php _e('No tiene áreas asignadas.', 'aura-suite'); ?></p>
        <?php endif; ?>
        
        <!-- Capabilities -->
        <h3><?php _e('Capabilities Asignadas:', 'aura-suite'); ?></h3>
        <?php
        $active_caps = array();
        $all_caps = Aura_Roles_Manager::get_all_capabilities();
        
        foreach ($all_caps as $module => $caps) {
            foreach ($caps as $cap => $desc) {
                if (isset($user_caps[$cap])) {
                    if (!isset($active_caps[$module])) {
                        $active_caps[$module] = array();
                    }
                    $active_caps[$module][] = $desc;
                }
            }
        }
        
        if (!empty($active_caps)):
        ?>
        <ul>
            <?php foreach ($active_caps as $module => $caps): ?>
            <li>
                <strong><?php echo ucfirst($module); ?>:</strong>
                <?php echo implode(', ', $caps); ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p><?php _e('Este usuario no tiene capabilities de Aura asignadas.', 'aura-suite'); ?></p>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {

    var auraUsersDt = null;
    var auraPermDtStateKey = 'aura_perm_users_dt_state_<?php echo (int) get_current_user_id(); ?>';
    var auraPermSearchKey  = 'aura_perm_users_search_<?php echo (int) get_current_user_id(); ?>';

    if ( $.fn.DataTable && $('#aura-users-table').length ) {
        auraUsersDt = $('#aura-users-table').DataTable({
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[0, 'asc']],
            responsive: true,
            autoWidth: false,
            stateSave: true,
            stateDuration: -1,
            stateSaveCallback: function(settings, data) {
                try {
                    // Persistimos solo lo solicitado: página, orden y tamaño.
                    var compactState = {
                        start: data.start,
                        length: data.length,
                        order: data.order || [[0, 'asc']]
                    };
                    localStorage.setItem(auraPermDtStateKey, JSON.stringify(compactState));
                } catch (e) {
                    // Silencioso si el navegador bloquea localStorage.
                }
            },
            stateLoadCallback: function() {
                try {
                    var raw = localStorage.getItem(auraPermDtStateKey);
                    if (!raw) return null;
                    var saved = JSON.parse(raw);
                    return {
                        time: +new Date(),
                        start: typeof saved.start === 'number' ? saved.start : 0,
                        length: typeof saved.length === 'number' ? saved.length : 10,
                        order: Array.isArray(saved.order) ? saved.order : [[0, 'asc']]
                    };
                } catch (e) {
                    return null;
                }
            },
            columnDefs: [
                { orderable: false, targets: [4] }
            ],
            language: {
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_ usuarios',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ usuarios',
                infoEmpty: 'Mostrando 0 a 0 de 0 usuarios',
                infoFiltered: '(filtrado de _MAX_ usuarios)',
                zeroRecords: 'No se encontraron usuarios',
                paginate: {
                    first: 'Primero',
                    last: 'Último',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            }
        });
    }

    // ═══ FILTRO DE USUARIOS (tabla Aura, flujo único) ═══════════
    $('#aura-users-table-search').on('input', function() {
        var term = ($(this).val() || '').toLowerCase().trim();

        try {
            localStorage.setItem(auraPermSearchKey, term);
        } catch (e) {
            // Silencioso si localStorage no está disponible.
        }

        if ( auraUsersDt ) {
            auraUsersDt.search(term).draw();
        } else {
            $('#aura-users-table-body tr[data-user-search]').each(function() {
                var haystack = ($(this).attr('data-user-search') || '').toLowerCase();
                $(this).toggle(!term || haystack.indexOf(term) !== -1);
            });
        }
    });

    // Restaurar filtro superior de usuarios al volver a la página.
    try {
        var savedSearch = localStorage.getItem(auraPermSearchKey);
        if (savedSearch) {
            $('#aura-users-table-search').val(savedSearch).trigger('input');
        }
    } catch (e) {
        // No-op
    }

    // ═══ ACCORDION ════════════════════════════════════════════
    $(document).on('click', '.aura-perm-module-header', function() {
        var $module = $(this).closest('.aura-perm-module');
        $module.toggleClass('is-open');
        $(this).attr('aria-expanded', $module.hasClass('is-open') ? 'true' : 'false');
    });
    $(document).on('keydown', '.aura-perm-module-header', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $(this).trigger('click'); }
    });
    $('#aura-expand-all-btn').on('click', function() {
        $('.aura-perm-module').addClass('is-open');
        $('.aura-perm-module-header').attr('aria-expanded', 'true');
    });
    $('#aura-collapse-all-btn').on('click', function() {
        $('.aura-perm-module').removeClass('is-open');
        $('.aura-perm-module-header').attr('aria-expanded', 'false');
    });

    // ═══ SEARCH ═══════════════════════════════════════════════
    $('#aura-cap-search').on('input', function() {
        var term = $(this).val().toLowerCase().trim();
        var totalVisible = 0;
        var $count = $('#aura-search-count');

        if (!term) {
            $('.aura-perm-cap-item').removeClass('is-hidden-by-search');
            $('.aura-perm-module').removeClass('is-hidden-by-search');
            $count.hide();
            // Restore badges to real counts
            refreshAllBadges();
            return;
        }

        $('.aura-perm-module').each(function() {
            var $module = $(this);
            var moduleVisible = 0;
            $module.find('.aura-perm-cap-item').each(function() {
                var label = $(this).attr('data-cap-label') || $(this).find('.aura-perm-cap-text').text().toLowerCase();
                if (label.indexOf(term) !== -1) {
                    $(this).removeClass('is-hidden-by-search');
                    moduleVisible++;
                    totalVisible++;
                } else {
                    $(this).addClass('is-hidden-by-search');
                }
            });
            if (moduleVisible === 0) {
                $module.addClass('is-hidden-by-search');
            } else {
                $module.removeClass('is-hidden-by-search').addClass('is-open');
            }
        });

        if (totalVisible > 0) {
            $count.text(totalVisible + ' resultado' + (totalVisible !== 1 ? 's' : '')).show();
        } else {
            $count.text('Sin resultados').show();
        }
    });

    // ═══ SELECT-ALL (con supports active-class + badge) ════════
    $(document).on('change', '.select-all-module', function() {
        var moduleKey = $(this).data('module');
        var checked   = $(this).is(':checked');
        $('input[name="capabilities[]"][data-module="' + moduleKey + '"]').prop('checked', checked).each(function() {
            var $item = $(this).closest('.aura-perm-cap-item');
            $item.toggleClass('is-active', checked);
        });
        refreshModuleBadge($(this).closest('.aura-perm-module'));
        updateStickyCount();
    });

    // ═══ INDIVIDUAL CAP CHANGE ═════════════════════════════════
    $(document).on('change', 'input[name="capabilities[]"]', function() {
        var $module = $(this).closest('.aura-perm-module');
        var $item   = $(this).closest('.aura-perm-cap-item');
        $item.toggleClass('is-active', $(this).is(':checked'));
        refreshModuleBadge($module);
        updateStickyCount();
    });

    // ═══ STICKY SAVE BAR ═══════════════════════════════════════
    var $stickyBar = $('#aura-perm-sticky-save');
    if ($stickyBar.length) {
        $(window).on('scroll.stickyPerm', function() {
            $stickyBar.toggleClass('is-visible', $(this).scrollTop() > 250);
        });
    }

    // ═══ HELPERS ═══════════════════════════════════════════════
    function refreshModuleBadge($module) {
        var $allCaps = $module.find('input[name="capabilities[]"]');
        var total    = $allCaps.length;
        var active   = $allCaps.filter(':checked').length;
        var $badge   = $module.find('.aura-perm-module-badge');
        var $selAll  = $module.find('.select-all-module');
        if (active > 0) {
            $badge.text(active + '/' + total).addClass('aura-perm-module-badge--active');
        } else {
            $badge.text(total).removeClass('aura-perm-module-badge--active');
        }
        $selAll.prop('checked', active === total && total > 0);
        $selAll.prop('indeterminate', active > 0 && active < total);
    }
    function refreshAllBadges() {
        $('.aura-perm-module').each(function() { refreshModuleBadge($(this)); });
    }
    function updateStickyCount() {
        var total = $('input[name="capabilities[]"]').filter(':checked').length;
        $('#aura-perm-sticky-total').html('<strong>' + total + '</strong> ' + (total === 1 ? 'permiso activo' : 'permisos activos'));
    }

    // Init: set indeterminate state on page load
    $('.aura-perm-module').each(function() {
        var $allCaps = $(this).find('input[name="capabilities[]"]');
        var total    = $allCaps.length;
        var active   = $allCaps.filter(':checked').length;
        if (active > 0 && active < total) {
            $(this).find('.select-all-module').prop('indeterminate', true);
        }
    });
    // Init: set indeterminate for data-indeterminate attr
    $('[data-indeterminate="true"]').prop('indeterminate', true);

    // ═══════════════════════════════════════════════════════════

    // Aplicar plantilla predefinida
    window.applyTemplate = function(templateId) {
        if (confirm('¿Aplicar esta plantilla? Se marcarán los permisos correspondientes.')) {
            var templates = {
                'treasurer': ['aura_finance_create', 'aura_finance_edit_own', 'aura_finance_delete_own', 'aura_finance_view_own', 'aura_finance_charts'],
                'auditor': ['aura_finance_view_all', 'aura_finance_charts', 'aura_finance_export', 'aura_vehicles_view_all', 'aura_vehicles_reports', 'aura_electric_view_dashboard', 'aura_electric_view_charts', 'aura_electric_export', 'aura_forms_view_responses_all', 'aura_forms_export'],
                'field_operator': ['aura_vehicles_exits_create', 'aura_vehicles_km_update', 'aura_vehicles_view_all', 'aura_electric_reading_create', 'aura_electric_view_dashboard', 'aura_forms_submit'],
                'director': ['aura_finance_approve', 'aura_finance_view_all', 'aura_finance_charts', 'aura_finance_export', 'aura_vehicles_view_all', 'aura_vehicles_reports', 'aura_electric_view_dashboard', 'aura_electric_view_charts', 'aura_forms_view_responses_all', 'aura_forms_analytics']
            };
            
            // Desmarcar todos y quitar clase is-active
            $('input[name="capabilities[]"]').prop('checked', false);
            $('.aura-perm-cap-item').removeClass('is-active');
            
            // Marcar capabilities de la plantilla y abrir módulos afectados
            if (templates[templateId]) {
                templates[templateId].forEach(function(cap) {
                    var $cb = $('#cap_' + cap);
                    $cb.prop('checked', true);
                    $cb.closest('.aura-perm-cap-item').addClass('is-active');
                    $cb.closest('.aura-perm-module').addClass('is-open');
                });
            }
            // Refrescar badges y contador sticky
            refreshAllBadges();
            updateStickyCount();
        }
    };

    // ── Modal Crear Usuario ────────────────────────────────
    var $modal   = $('#aura-modal-crear-usuario');
    var $error   = $('#aura-crear-usuario-error');
    var $btnSave = $('#aura-btn-guardar-usuario');

    function openModal() {
        $modal.css('display', 'flex');
        $error.hide().text('');
        $('#aura_cu_first_name, #aura_cu_last_name, #aura_cu_email, #aura_cu_phone, #aura_cu_password').val('');
    }
    function closeModal() { $modal.hide(); }

    $('#aura-btn-nuevo-usuario').on('click', openModal);
    $('#aura-modal-cerrar, #aura-modal-cancelar').on('click', closeModal);
    $modal.on('click', function(e) { if ($(e.target).is($modal)) closeModal(); });

    // Mostrar/ocultar contraseña
    $('#aura-toggle-pwd').on('click', function() {
        var $inp = $('#aura_cu_password');
        var visible = $inp.attr('type') === 'text';
        $inp.attr('type', visible ? 'password' : 'text');
        $(this).find('.dashicons').toggleClass('dashicons-visibility', visible).toggleClass('dashicons-hidden', !visible);
    });

    // Generar contraseña aleatoria
    $('#aura-generar-pwd').on('click', function() {
        var chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$';
        var pwd = '';
        for (var i = 0; i < 12; i++) pwd += chars.charAt(Math.floor(Math.random() * chars.length));
        $('#aura_cu_password').attr('type', 'text').val(pwd);
        $('#aura-toggle-pwd .dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
    });

    // Guardar usuario vía AJAX
    $btnSave.on('click', function() {
        $error.hide().text('');
        var first = $.trim($('#aura_cu_first_name').val());
        var email = $.trim($('#aura_cu_email').val());
        var pwd   = $('#aura_cu_password').val();

        if (!first || !email || !pwd) {
            $error.text('<?php echo esc_js(__('Nombre, email y contraseña son obligatorios.', 'aura-suite')); ?>').show();
            return;
        }

        $btnSave.prop('disabled', true).text('<?php echo esc_js(__('Creando...', 'aura-suite')); ?>');

        $.post(ajaxurl, {
            action:     'aura_create_user',
            nonce:      '<?php echo wp_create_nonce('aura_create_user_nonce'); ?>',
            first_name: first,
            last_name:  $.trim($('#aura_cu_last_name').val()),
            email:      email,
            phone:      $.trim($('#aura_cu_phone').val()),
            password:   pwd
        }, function(res) {
            if (res.success) {
                window.location.href = res.data.redirect_url;
            } else {
                $error.text(res.data.message).show();
                $btnSave.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top:4px;"></span> <?php echo esc_js(__('Crear y asignar permisos', 'aura-suite')); ?>');
            }
        }).fail(function() {
            $error.text('<?php echo esc_js(__('Error de conexión. Inténtalo de nuevo.', 'aura-suite')); ?>').show();
            $btnSave.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top:4px;"></span> <?php echo esc_js(__('Crear y asignar permisos', 'aura-suite')); ?>');
        });
    });
});
</script>
