<?php
/**
 * Template: Página de Gestión de Permisos y Roles (CBAC)
 * Modernizada y estandarizada rigurosamente según prompt-maestro.md
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

// Procesar asignación de permisos y áreas
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
        
        // Procesar asignación de áreas con roles específicos (admin, editor, collaborator)
        $selected_areas = isset($_POST['user_areas']) && is_array($_POST['user_areas']) 
            ? array_map('absint', $_POST['user_areas']) 
            : [];
        $user_area_roles = isset($_POST['user_area_roles']) && is_array($_POST['user_area_roles'])
            ? $_POST['user_area_roles']
            : [];
        
        // Obtener todas las áreas para actualizar relaciones
        $all_areas = Aura_Areas_Setup::get_all_areas();
        
        foreach ($all_areas as $area) {
            $area_id = (int) $area->id;
            $current_users = Aura_Areas_Setup::get_area_users($area_id);
            
            // Construir mapa actual [user_id => role]
            $users_map = [];
            foreach ($current_users as $cu) {
                $users_map[(int)$cu['user_id']] = $cu['role'];
            }
            
            if (in_array($area_id, $selected_areas)) {
                $selected_role = isset($user_area_roles[$area_id]) ? sanitize_text_field($user_area_roles[$area_id]) : 'admin';
                if (!in_array($selected_role, ['admin', 'editor', 'collaborator'])) {
                    $selected_role = 'admin';
                }
                $users_map[$user_id] = $selected_role;
                Aura_Areas_Setup::assign_users_to_area($area_id, $users_map);
            } else {
                if (isset($users_map[$user_id])) {
                    unset($users_map[$user_id]);
                    Aura_Areas_Setup::assign_users_to_area($area_id, $users_map);
                }
            }
        }
        
        // Redirigir de vuelta al usuario con mensaje de éxito
        wp_redirect(add_query_arg([
            'page' => 'aura-permissions',
            'user_id' => $user_id,
            'updated' => 'true',
            'tab' => 'user-permissions'
        ], admin_url('admin.php')));
        exit;
    }
}

// Obtener usuario seleccionado
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$selected_user    = $selected_user_id ? get_user_by('id', $selected_user_id) : null;

// Obtener todos los usuarios
$all_users = get_users(array('orderby' => 'display_name'));

// Mapa de vinculación con Estudiantes (wp_aura_students)
global $wpdb;
$students_table = $wpdb->prefix . 'aura_students';
$students_map   = array();
if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $students_table ) ) === $students_table ) {
    $students_records = $wpdb->get_results(
        "SELECT id, wp_user_id, student_code, status, first_name, last_name, email, profile_type 
         FROM {$students_table} 
         WHERE wp_user_id IS NOT NULL AND deleted_at IS NULL"
    );
    if ( ! empty( $students_records ) ) {
        foreach ( $students_records as $st_rec ) {
            $students_map[(int)$st_rec->wp_user_id] = $st_rec;
        }
    }
}

// Preparar capabilities planas
$aura_caps_map  = Aura_Roles_Manager::get_all_capabilities();
$aura_caps_flat = array();
foreach ( $aura_caps_map as $module_caps ) {
    foreach ( $module_caps as $cap_key => $cap_label ) {
        $aura_caps_flat[] = $cap_key;
    }
}

// Clasificar usuarios: Activos en Aura vs Disponibles en WordPress
$aura_users        = array();
$inactive_wp_users = array();
$total_area_assignments = 0;

foreach ( $all_users as $listed_user ) {
    $aura_caps_count = 0;
    foreach ( $aura_caps_flat as $aura_cap_key ) {
        if ( user_can( $listed_user, $aura_cap_key ) ) {
            $aura_caps_count++;
        }
    }

    $user_areas_for_list = Aura_Areas_Setup::get_user_areas( $listed_user->ID );
    $areas_count = is_array( $user_areas_for_list ) ? count( $user_areas_for_list ) : 0;
    $total_area_assignments += $areas_count;
    $user_roles  = !empty($listed_user->roles) ? implode( ', ', array_map( 'ucfirst', $listed_user->roles ) ) : __('Sin rol', 'aura-suite');
    $user_avatar = get_avatar_url( $listed_user->ID, array( 'size' => 64 ) );

    if ( $aura_caps_count > 0 || $areas_count > 0 ) {
        $aura_users[] = array(
            'user'        => $listed_user,
            'avatar'      => $user_avatar,
            'caps_count'  => $aura_caps_count,
            'areas_count' => $areas_count,
            'areas'       => $user_areas_for_list,
            'roles'       => $user_roles,
            'is_selected' => (int) $selected_user_id === (int) $listed_user->ID,
        );
    } else {
        $inactive_wp_users[] = array(
            'user'        => $listed_user,
            'avatar'      => $user_avatar,
            'roles'       => $user_roles,
            'is_selected' => (int) $selected_user_id === (int) $listed_user->ID,
        );
    }
}

$count_active_aura = count($aura_users);
$count_inactive_wp = count($inactive_wp_users);
$profile_templates = Aura_Roles_Manager::get_profile_templates();
$count_profile_templates = count($profile_templates);

// Determinar tab activo
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : ($selected_user ? 'user-permissions' : 'active-users');
?>

<div class="aura-app-wrapper aura-permissions-wrap">
<div class="wrap aura-app-context" style="max-width:1440px;margin:0 auto;padding:0 16px;">

    <!-- ====================================================================
         1. CABECERA PRINCIPAL HERO GLASS CARD (.hero-card)
         ==================================================================== -->
    <header class="aura-glass-card aura-page-header hero-card fade-up">
        <div class="aura-header-left">
            <div class="avatar avatar-lg" style="background:linear-gradient(135deg,var(--aura-blue,#2563eb),var(--aura-indigo,#4f46e5));color:#fff;box-shadow:0 8px 24px rgba(37,99,235,0.35);font-size:24px;display:flex;align-items:center;justify-content:center;border-radius:14px;width:52px;height:52px;min-width:52px;">
                <span class="dashicons dashicons-admin-users" style="font-size:28px;width:28px;height:28px;"></span>
            </div>
            <div class="aura-header-text">
                <h1>
                    <span><?php _e('Gestión de Permisos y Roles (CBAC)', 'aura-suite'); ?></span>
                </h1>
                <p class="hero-desc">
                    <?php _e('Asigne capabilities específicas y áreas de responsabilidad a usuarios de WordPress para Aura Business Suite.', 'aura-suite'); ?>
                </p>
                <div class="aura-header-badges">
                    <span class="badge badge-emerald" title="<?php esc_attr_e('Usuarios de WordPress que tienen al menos un permiso o área asignada en Aura', 'aura-suite'); ?>">
                        <span class="pulse-dot dot-emerald"></span>
                        <span><?php echo sprintf(__('%d Activos en Aura', 'aura-suite'), $count_active_aura); ?></span>
                    </span>
                    <span class="badge badge-amber" title="<?php esc_attr_e('Usuarios registrados en WordPress pendientes por configurar en Aura', 'aura-suite'); ?>">
                        <span class="pulse-dot dot-amber"></span>
                        <span><?php echo sprintf(__('%d Disponibles WP', 'aura-suite'), $count_inactive_wp); ?></span>
                    </span>
                    <span class="badge badge-blue" title="<?php esc_attr_e('Total de vinculaciones activas entre usuarios y áreas de trabajo', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-networking" style="font-size:13px; width:13px; height:13px;"></span>
                        <span><?php echo sprintf(__('%d Áreas Asignadas', 'aura-suite'), $total_area_assignments); ?></span>
                    </span>
                    <span class="badge badge-violet" title="<?php esc_attr_e('Plantillas de perfiles de negocio predefinidas para asignación rápida', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-groups" style="font-size:13px; width:13px; height:13px;"></span>
                        <span><?php echo sprintf(__('%d Perfiles Estándar', 'aura-suite'), $count_profile_templates); ?></span>
                    </span>
                </div>
            </div>
        </div>

        <div class="aura-header-right">
            <?php if ($selected_user): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=aura-permissions&tab=active-users')); ?>" class="btn btn-glass aura-btn-icon-left">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <span><?php _e('Volver al Directorio', 'aura-suite'); ?></span>
            </a>
            <?php endif; ?>

            <?php if (current_user_can('aura_admin_users_create') || current_user_can('aura_admin_permissions_assign')): ?>
            <button type="button" id="aura-btn-open-user-modal" class="btn btn-emerald btn-shimmer aura-btn-open-user-modal">
                <span class="dashicons dashicons-plus-alt2"></span>
                <span><?php _e('Asignar o Crear Usuario', 'aura-suite'); ?></span>
            </button>
            <?php endif; ?>
        </div>
    </header>

    <!-- ====================================================================
         2. TARJETAS KPI DE RESUMEN (DESIGN SYSTEM)
         ==================================================================== -->
    <div class="aura-kpi-grid">
        <!-- KPI 1: Usuarios Activos Aura -->
        <div class="kpi-card kpi-emerald card-lift fade-up delay-1">
            <div class="kpi-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="kpi-value"><?php echo number_format_i18n($count_active_aura); ?></div>
            <div class="kpi-label">
                <span><?php _e('Usuarios Activos Aura', 'aura-suite'); ?></span>
                <span class="aura-help-icon" title="<?php esc_attr_e('Total de usuarios de WordPress que cuentan con al menos un permiso o área de responsabilidad activa en Aura Business Suite.', 'aura-suite'); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                </span>
            </div>
            <div class="kpi-change up">
                <span class="pulse-dot dot-emerald"></span>
                <span><?php _e('Configurados con acceso CBAC', 'aura-suite'); ?></span>
            </div>
        </div>

        <!-- KPI 2: Usuarios WP Disponibles -->
        <div class="kpi-card kpi-amber card-lift fade-up delay-2">
            <div class="kpi-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="kpi-value"><?php echo number_format_i18n($count_inactive_wp); ?></div>
            <div class="kpi-label">
                <span><?php _e('Usuarios WP Disponibles', 'aura-suite'); ?></span>
                <span class="aura-help-icon" title="<?php esc_attr_e('Usuarios registrados en WordPress que aún no tienen permisos ni áreas asignadas en Aura Suite, listos para ser configurados.', 'aura-suite'); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                </span>
            </div>
            <div class="kpi-change up">
                <span class="pulse-dot dot-amber"></span>
                <span><?php _e('Listos para asignación', 'aura-suite'); ?></span>
            </div>
        </div>

        <!-- KPI 3: Áreas Asignadas -->
        <div class="kpi-card kpi-blue card-lift fade-up delay-3">
            <div class="kpi-icon">
                <span class="dashicons dashicons-networking"></span>
            </div>
            <div class="kpi-value"><?php echo number_format_i18n($total_area_assignments); ?></div>
            <div class="kpi-label">
                <span><?php _e('Áreas Asignadas', 'aura-suite'); ?></span>
                <span class="aura-help-icon" title="<?php esc_attr_e('Número total acumulado de vinculaciones de áreas de trabajo y programas asignadas a usuarios del sistema.', 'aura-suite'); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                </span>
            </div>
            <div class="kpi-change up">
                <span class="pulse-dot dot-blue"></span>
                <span><?php _e('Vinculaciones de responsabilidad', 'aura-suite'); ?></span>
            </div>
        </div>

        <!-- KPI 4: Perfiles Estándar -->
        <div class="kpi-card kpi-violet card-lift fade-up delay-4">
            <div class="kpi-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="kpi-value"><?php echo number_format_i18n($count_profile_templates); ?></div>
            <div class="kpi-label">
                <span><?php _e('Perfiles Predefinidos', 'aura-suite'); ?></span>
                <span class="aura-help-icon" title="<?php esc_attr_e('Plantillas de perfiles de negocio (Finanzas, Dirección, Auditoría, Logística, etc.) listas para aplicar en 1 solo clic.', 'aura-suite'); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                </span>
            </div>
            <div class="kpi-change up">
                <span class="pulse-dot dot-violet"></span>
                <span><?php _e('Plantillas listas para usar', 'aura-suite'); ?></span>
            </div>
        </div>
    </div>

    <!-- Notificaciones del Sistema -->
    <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true'): ?>
    <div class="alert-card alert-success fade-up" style="margin-bottom:20px;">
        <span class="dashicons dashicons-yes-alt" style="font-size:22px;width:22px;height:22px;"></span>
        <div>
            <strong><?php _e('¡Éxito!', 'aura-suite'); ?></strong> 
            <span><?php _e('Permisos y áreas actualizados exitosamente.', 'aura-suite'); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['created']) && $_GET['created'] === '1' && $selected_user): ?>
    <div class="alert-card alert-success fade-up" style="margin-bottom:20px;">
        <span class="dashicons dashicons-yes-alt" style="font-size:22px;width:22px;height:22px;"></span>
        <div>
            <strong><?php _e('Usuario de WordPress creado exitosamente.', 'aura-suite'); ?></strong>
            <span><?php printf(__('Ahora configure los permisos y áreas para <strong>%s</strong>.', 'aura-suite'), esc_html($selected_user->display_name)); ?></span>
        </div>
    </div>
    <?php elseif (isset($_GET['activated']) && $_GET['activated'] === '1' && $selected_user): ?>
    <div class="alert-card alert-info fade-up" style="margin-bottom:20px;">
        <span class="dashicons dashicons-info" style="font-size:22px;width:22px;height:22px;"></span>
        <div>
            <strong><?php _e('Usuario de WordPress seleccionado.', 'aura-suite'); ?></strong>
            <span><?php printf(__('Asigne al menos una capability o área a <strong>%s</strong> para activarlo formalmente en Aura Suite.', 'aura-suite'), esc_html($selected_user->display_name)); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ====================================================================
         3. BARRA DE NAVEGACIÓN DE PESTAÑAS (.tabs-bar)
         ==================================================================== -->
    <nav class="aura-navbar aura-nav aura-glass-card tabs-bar fade-up delay-2" aria-label="<?php esc_attr_e('Navegación de Permisos', 'aura-suite'); ?>" role="tablist">
        <button type="button" class="aura-tab-btn tab-btn <?php echo ($active_tab === 'active-users') ? 'is-active active' : ''; ?>" data-tab="active-users" role="tab">
            <span class="dashicons dashicons-yes-alt"></span>
            <span><?php _e('Usuarios Activos en Aura', 'aura-suite'); ?></span>
            <span class="aura-tab-badge badge badge-emerald" style="font-size:11px;padding:2px 8px;"><?php echo $count_active_aura; ?></span>
        </button>
        <button type="button" class="aura-tab-btn tab-btn <?php echo ($active_tab === 'inactive-users') ? 'is-active active' : ''; ?>" data-tab="inactive-users" role="tab">
            <span class="dashicons dashicons-clock"></span>
            <span><?php _e('Usuarios WP Disponibles', 'aura-suite'); ?></span>
            <span class="aura-tab-badge badge badge-amber" style="font-size:11px;padding:2px 8px;"><?php echo $count_inactive_wp; ?></span>
        </button>

        <?php if ($selected_user): ?>
        <button type="button" class="aura-tab-btn tab-btn <?php echo ($active_tab === 'user-permissions') ? 'is-active active' : ''; ?>" data-tab="user-permissions" role="tab">
            <span class="dashicons dashicons-admin-users"></span>
            <span><?php printf(__('Permisos de: %s', 'aura-suite'), esc_html($selected_user->display_name)); ?></span>
        </button>
        <?php endif; ?>

        <button type="button" class="aura-tab-btn tab-btn <?php echo ($active_tab === 'profile-templates') ? 'is-active active' : ''; ?>" data-tab="profile-templates" role="tab">
            <span class="dashicons dashicons-groups"></span>
            <span><?php _e('Perfiles Predefinidos', 'aura-suite'); ?></span>
            <span class="aura-tab-badge badge badge-violet" style="font-size:11px;padding:2px 8px;"><?php echo $count_profile_templates; ?></span>
        </button>
    </nav>

    <!-- ====================================================================
         PANEL 1: USUARIOS ACTIVOS EN AURA
         ==================================================================== -->
    <div id="aura-tab-panel-active-users" class="aura-tab-panel" style="<?php echo ($active_tab === 'active-users') ? '' : 'display:none;'; ?>">
        <div class="aura-config-section glass-card fade-up">
            <div class="aura-section-header">
                <div>
                    <h2>
                        <span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span>
                        <span><?php _e('Directorio de Usuarios Activos en Aura Suite', 'aura-suite'); ?></span>
                    </h2>
                    <p class="description">
                        <?php _e('Usuarios de WordPress que poseen permisos granulares (CBAC) o áreas de responsabilidad asignadas.', 'aura-suite'); ?>
                    </p>
                </div>
                <div class="input-group" style="max-width: 440px;">
                    <span class="input-group-text">🔍</span>
                    <input type="text" id="aura-users-table-search" class="form-control aura-input-control" placeholder="<?php esc_attr_e('Buscar usuario activo por nombre, correo o rol...', 'aura-suite'); ?>">
                </div>
            </div>

            <div class="aura-users-table-wrap">
                <table class="widefat striped aura-users-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width: 44px; text-align:center;">
                                <span class="dashicons dashicons-menu-alt2" style="font-size:16px; width:16px; height:16px; color:#94a3b8;"></span>
                            </th>
                            <th>
                                <?php _e('Usuario', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Nombre completo, correo electrónico y avatar del usuario en WordPress', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                            <th class="aura-col-mobile-hidden">
                                <?php _e('Login (@usuario)', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Nombre de usuario único para iniciar sesión en el sistema', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                            <th class="aura-col-mobile-hidden">
                                <?php _e('Rol(es) WP', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Rol base en WordPress (Administrador, Suscriptor, Editor, etc.)', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                            <th>
                                <?php _e('Permisos Aura', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Cantidad de capabilities específicas de Aura Suite otorgadas', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                            <th class="aura-col-mobile-hidden">
                                <?php _e('Áreas Asignadas', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Departamentos y programas bajo su responsabilidad', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                            <th style="text-align:right;">
                                <?php _e('Acciones', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Configurar permisos o gestionar áreas del usuario', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="aura-users-table-body">
                        <?php if (!empty($aura_users)): ?>
                            <?php foreach ($aura_users as $aura_row):
                                $row_user = $aura_row['user'];
                                $row_url  = add_query_arg([
                                    'page'    => 'aura-permissions',
                                    'user_id' => (int) $row_user->ID,
                                    'tab'     => 'user-permissions'
                                ], admin_url('admin.php'));

                                $row_student = isset($students_map[(int)$row_user->ID]) ? $students_map[(int)$row_user->ID] : null;
                                $row_search = strtolower($row_user->display_name . ' ' . $row_user->user_login . ' ' . $row_user->user_email . ' ' . $aura_row['roles'] . ( $row_student ? ' estudiante ' . ($row_student->student_code ?? '') : '' ));

                                // Construir Tooltip Enriquecido .aura-tip-card
                                $tip_student_badge = $row_student ? '<span class="aura-tip-badge" style="background:rgba(124,58,237,0.2);color:#7c3aed;">🎓 ' . esc_html($row_student->student_code ?: __('Estudiante', 'aura-suite')) . '</span>' : '';
                                $tip_student_row   = $row_student ? '<div class="aura-tip-row"><span>🎓 Expediente:</span><strong style="color:#7c3aed;">#' . (int)$row_student->id . ' (' . esc_html(ucfirst($row_student->status)) . ')</strong></div>' : '';

                                $tip_user_html = '<div class="aura-tip-card">'
                                    . '<div class="aura-tip-card-header">'
                                    . '<img src="' . esc_url($aura_row['avatar']) . '" class="aura-tip-avatar-large" alt="' . esc_attr($row_user->display_name) . '">'
                                    . '<div class="aura-tip-info">'
                                    . '<div class="aura-tip-title">' . esc_html($row_user->display_name) . '</div>'
                                    . '<div class="aura-tip-subtitle">@' . esc_html($row_user->user_login) . ' &bull; ' . esc_html($aura_row['roles']) . '</div>'
                                    . '<div class="aura-tip-badges">'
                                    . '<span class="aura-tip-badge" style="background:rgba(37,99,235,0.2);color:#3b82f6;">' . sprintf(__('%d Permisos', 'aura-suite'), $aura_row['caps_count']) . '</span>'
                                    . '<span class="aura-tip-badge" style="background:rgba(16,185,129,0.2);color:#10b981;">' . sprintf(__('%d Áreas', 'aura-suite'), $aura_row['areas_count']) . '</span>'
                                    . $tip_student_badge
                                    . '</div>'
                                    . '</div>'
                                    . '</div>'
                                    . '<div class="aura-tip-card-body">'
                                    . '<div class="aura-tip-row"><span>📧 Email:</span><strong>' . esc_html($row_user->user_email) . '</strong></div>'
                                    . '<div class="aura-tip-row"><span>🆔 ID WP:</span><strong>#' . (int)$row_user->ID . '</strong></div>'
                                    . '<div class="aura-tip-row"><span>📅 Registro:</span><strong>' . esc_html(date_i18n(get_option('date_format'), strtotime($row_user->user_registered))) . '</strong></div>'
                                    . $tip_student_row
                                    . '</div>'
                                    . '</div>';
                            ?>
                            <!-- Fila Principal -->
                            <tr class="aura-parent-row <?php echo $aura_row['is_selected'] ? 'is-selected' : ''; ?>" data-user-search="<?php echo esc_attr($row_search); ?>">
                                <td style="text-align:center;">
                                    <button type="button" class="aura-row-toggle" aria-expanded="false" title="<?php esc_attr_e('Ver detalles del usuario', 'aura-suite'); ?>">+</button>
                                </td>
                                <td>
                                    <div class="aura-user-identity aura-has-tooltip" data-aura-tooltip="<?php echo esc_attr(rawurlencode($tip_user_html)); ?>">
                                        <img src="<?php echo esc_url($aura_row['avatar']); ?>" alt="<?php echo esc_attr($row_user->display_name); ?>" class="aura-user-avatar">
                                        <div class="aura-user-meta">
                                            <span class="aura-user-name"><?php echo esc_html($row_user->display_name); ?></span>
                                            <span class="aura-user-email"><?php echo esc_html($row_user->user_email); ?></span>
                                            <?php if ($row_student): ?>
                                            <div style="margin-top:3px;">
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=aura-students-list&search=' . urlencode($row_student->student_code ?: $row_user->user_email))); ?>" class="badge badge-violet" style="text-decoration:none; font-size:11px; padding:2px 7px; display:inline-flex; align-items:center; gap:4px; font-weight:700;" title="<?php esc_attr_e('Ver expediente académico en el módulo de Estudiantes', 'aura-suite'); ?>">
                                                    <span>🎓</span>
                                                    <span><?php echo esc_html($row_student->student_code ?: __('Estudiante AURA', 'aura-suite')); ?></span>
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="aura-col-mobile-hidden">
                                    <code>@<?php echo esc_html($row_user->user_login); ?></code>
                                </td>
                                <td class="aura-col-mobile-hidden">
                                    <span style="font-weight:600; color:var(--tx-secondary,#475569);"><?php echo esc_html($aura_row['roles'] ?: '—'); ?></span>
                                </td>
                                <td>
                                    <span class="aura-count-badge aura-count-badge--blue badge badge-blue">
                                        <span class="dashicons dashicons-shield" style="font-size:13px; width:13px; height:13px;"></span>
                                        <?php echo (int) $aura_row['caps_count']; ?>
                                    </span>
                                </td>
                                <td class="aura-col-mobile-hidden">
                                    <?php if (!empty($aura_row['areas'])): ?>
                                        <div class="aura-user-areas-pill-group">
                                            <?php foreach ($aura_row['areas'] as $u_area): 
                                                $u_area_logo = !empty($u_area->logo_id) ? wp_get_attachment_image_url((int)$u_area->logo_id, 'thumbnail') : '';
                                            ?>
                                                <span class="aura-area-badge-pill" style="--area-accent: <?php echo esc_attr($u_area->color ?: '#3b82f6'); ?>;" title="<?php echo esc_attr($u_area->name); ?>">
                                                    <?php if ($u_area_logo): ?>
                                                        <img src="<?php echo esc_url($u_area_logo); ?>" alt="<?php echo esc_attr($u_area->name); ?>" class="aura-area-badge-logo">
                                                    <?php else: ?>
                                                        <span class="dashicons <?php echo esc_attr(!empty($u_area->icon) ? $u_area->icon : 'dashicons-networking'); ?>" style="color:<?php echo esc_attr($u_area->color ?: '#3b82f6'); ?>; font-size:13px; width:13px; height:13px;"></span>
                                                    <?php endif; ?>
                                                    <span style="font-weight:600;"><?php echo esc_html($u_area->name); ?></span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="aura-count-badge badge" style="background:var(--bg-alt,#f1f5f9); color:var(--tx-muted,#94a3b8);"><?php _e('Sin áreas', 'aura-suite'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <a href="<?php echo esc_url($row_url); ?>" class="btn btn-primary btn-sm btn-shimmer aura-btn-edit-user">
                                        <span class="dashicons dashicons-admin-generic"></span>
                                        <span><?php _e('Editar permisos', 'aura-suite'); ?></span>
                                    </a>
                                </td>
                            </tr>

                            <!-- Fila Hija WOW (.aura-child-card) -->
                            <tr class="aura-child-row" style="display:none;">
                                <td colspan="7">
                                    <div class="aura-child-card">
                                        <div class="aura-child-hero">
                                            <div class="aura-child-hero-title">
                                                <span class="dashicons dashicons-id-alt"></span>
                                                <span><?php printf(__('Ficha de Permisos y Responsabilidades: %s', 'aura-suite'), esc_html($row_user->display_name)); ?></span>
                                            </div>
                                            <span class="aura-badge aura-badge-glass badge badge-indigo">
                                                @<?php echo esc_html($row_user->user_login); ?> &bull; ID #<?php echo (int)$row_user->ID; ?>
                                            </span>
                                        </div>

                                        <div class="aura-child-grid">
                                            <!-- Subtarjeta 1: Áreas Asignadas -->
                                            <div class="aura-child-subcard">
                                                <div class="aura-child-subcard-title">
                                                    <span class="dashicons dashicons-networking"></span>
                                                    <span><?php _e('Áreas y Programas Asignados', 'aura-suite'); ?></span>
                                                </div>
                                                <?php if (!empty($aura_row['areas'])): ?>
                                                    <div class="aura-user-areas-pill-group">
                                                        <?php foreach ($aura_row['areas'] as $u_area): 
                                                            $u_role = $u_area->role ?? 'admin';
                                                            $u_role_icon = ($u_role === 'collaborator') ? '🤝' : (($u_role === 'editor') ? '✏️' : '👑');
                                                            $u_role_name = ($u_role === 'collaborator') ? 'Colaborador' : (($u_role === 'editor') ? 'Editor' : 'Admin');
                                                        ?>
                                                        <span class="aura-area-badge-pill" style="--area-accent: <?php echo esc_attr($u_area->color ?: '#3b82f6'); ?>;">
                                                            <strong><?php echo esc_html($u_area->name); ?></strong>
                                                            <span style="font-size:11px; opacity:0.85; margin-left:3px;"><?php echo $u_role_icon . ' ' . esc_html($u_role_name); ?></span>
                                                        </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <p style="font-size:12.5px; color:var(--tx-muted,#94a3b8); margin:0;"><?php _e('Este usuario no tiene áreas vinculadas actualmente.', 'aura-suite'); ?></p>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Subtarjeta 2: Resumen de Capabilities -->
                                            <div class="aura-child-subcard">
                                                <div class="aura-child-subcard-title">
                                                    <span class="dashicons dashicons-shield"></span>
                                                    <span><?php _e('Permisos Granulares Activos', 'aura-suite'); ?></span>
                                                </div>
                                                <p style="font-size:13px; margin:0 0 6px;">
                                                    <strong><?php echo (int) $aura_row['caps_count']; ?></strong> <?php _e('capabilities activas de Aura Suite.', 'aura-suite'); ?>
                                                </p>
                                                <span style="font-size:12px; color:var(--tx-muted,#64748b);">
                                                    <?php _e('Haga clic en "Configurar Permisos y Áreas" para auditar cada módulo detalladamente.', 'aura-suite'); ?>
                                                </span>
                                            </div>

                                            <!-- Subtarjeta 3: Información de Cuenta WP -->
                                            <div class="aura-child-subcard">
                                                <div class="aura-child-subcard-title">
                                                    <span class="dashicons dashicons-wordpress"></span>
                                                    <span><?php _e('Cuenta de WordPress', 'aura-suite'); ?></span>
                                                </div>
                                                <div style="font-size:12.5px; line-height:1.6; color:var(--tx-secondary,#475569);">
                                                    <div><strong>Email:</strong> <?php echo esc_html($row_user->user_email); ?></div>
                                                    <div><strong>Rol WP:</strong> <?php echo esc_html($aura_row['roles']); ?></div>
                                                </div>
                                            </div>

                                            <?php if ($row_student): ?>
                                            <!-- Subtarjeta 4: Expediente Académico AURA -->
                                            <div class="aura-child-subcard" style="border-left:3px solid #7c3aed;">
                                                <div class="aura-child-subcard-title" style="color:#7c3aed;">
                                                    <span class="dashicons dashicons-welcome-learn-more"></span>
                                                    <span><?php _e('Expediente Académico (Estudiante)', 'aura-suite'); ?></span>
                                                </div>
                                                <div style="font-size:12.5px; line-height:1.6; color:var(--tx-secondary,#475569);">
                                                    <div><strong>Expediente:</strong> #<?php echo (int)$row_student->id; ?></div>
                                                    <div><strong>Código:</strong> <code><?php echo esc_html($row_student->student_code ?: '—'); ?></code></div>
                                                    <div><strong>Estado:</strong> <span class="badge badge-violet" style="font-size:10.5px; padding:1px 6px;"><?php echo esc_html(ucfirst($row_student->status)); ?></span></div>
                                                    <div style="margin-top:6px;">
                                                        <a href="<?php echo esc_url(admin_url('admin.php?page=aura-students-list&search=' . urlencode($row_student->student_code ?: $row_user->user_email))); ?>" class="btn btn-sm btn-glass" style="font-size:11px; padding:2px 8px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                                                            <span class="dashicons dashicons-external"></span>
                                                            <span><?php _e('Abrir en Estudiantes', 'aura-suite'); ?></span>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="aura-child-dock">
                                            <a href="<?php echo esc_url($row_url); ?>" class="btn btn-primary btn-shimmer aura-btn-dock-action">
                                                <span class="dashicons dashicons-admin-generic"></span>
                                                <span><?php _e('Configurar Permisos y Áreas', 'aura-suite'); ?></span>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="aura-empty-state empty-card">
                                        <span class="dashicons dashicons-admin-users aura-empty-state-icon"></span>
                                        <h4 class="aura-empty-state-title"><?php _e('Aún no hay usuarios activos en Aura Suite', 'aura-suite'); ?></h4>
                                        <p class="aura-empty-state-desc"><?php _e('Utilice el botón "Asignar o Crear Usuario" para configurar el primer usuario del sistema.', 'aura-suite'); ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ====================================================================
         PANEL 2: USUARIOS WP DISPONIBLES PARA ACTIVAR
         ==================================================================== -->
    <div id="aura-tab-panel-inactive-users" class="aura-tab-panel" style="<?php echo ($active_tab === 'inactive-users') ? '' : 'display:none;'; ?>">
        <div class="aura-config-section glass-card fade-up">
            <div class="aura-section-header">
                <div>
                    <h2>
                        <span class="dashicons dashicons-clock" style="color:#f59e0b;"></span>
                        <span><?php _e('Usuarios de WordPress Disponibles', 'aura-suite'); ?></span>
                    </h2>
                    <p class="description">
                        <?php _e('Usuarios registrados en WordPress que aún no cuentan con permisos ni áreas configuradas en Aura Business Suite.', 'aura-suite'); ?>
                    </p>
                </div>
                <div class="input-group" style="max-width: 440px;">
                    <span class="input-group-text">🔍</span>
                    <input type="text" id="aura-inactive-users-search" class="form-control aura-input-control" placeholder="<?php esc_attr_e('Buscar usuario de WordPress...', 'aura-suite'); ?>">
                </div>
            </div>

            <div class="aura-users-table-wrap">
                <table class="widefat striped aura-users-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width: 44px; text-align:center;">
                                <span class="dashicons dashicons-menu-alt2" style="font-size:16px; width:16px; height:16px; color:#94a3b8;"></span>
                            </th>
                            <th>
                                <?php _e('Usuario', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Nombre y correo del usuario registrado en WordPress', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                            <th class="aura-col-mobile-hidden">
                                <?php _e('Login (@usuario)', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Nombre de usuario único de WordPress', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                            <th class="aura-col-mobile-hidden">
                                <?php _e('Rol(es) WP', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Rol estándar actual asignado en WordPress', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                            <th>
                                <?php _e('Estado en Aura', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Indica si el usuario tiene acceso otorgado en los módulos de Aura', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                            <th style="text-align:right;">
                                <?php _e('Acción', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Abrir el configurador para otorgar permisos a este usuario', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-editor-help"></span>
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="aura-inactive-users-body">
                        <?php if (!empty($inactive_wp_users)): ?>
                            <?php foreach ($inactive_wp_users as $in_row):
                                $u = $in_row['user'];
                                $activate_url = add_query_arg([
                                    'page'      => 'aura-permissions',
                                    'user_id'   => (int) $u->ID,
                                    'activated' => '1',
                                    'tab'       => 'user-permissions'
                                ], admin_url('admin.php'));

                                $in_student = isset($students_map[(int)$u->ID]) ? $students_map[(int)$u->ID] : null;
                                $row_search = strtolower($u->display_name . ' ' . $u->user_login . ' ' . $u->user_email . ' ' . $in_row['roles'] . ( $in_student ? ' estudiante ' . ($in_student->student_code ?? '') : '' ));

                                // Tooltip enriquecido
                                $tip_in_student_badge = $in_student ? '<span class="aura-tip-badge" style="background:rgba(124,58,237,0.2);color:#7c3aed;">🎓 ' . esc_html($in_student->student_code ?: __('Estudiante', 'aura-suite')) . '</span>' : '';
                                $tip_in_student_row   = $in_student ? '<div class="aura-tip-row"><span>🎓 Expediente:</span><strong style="color:#7c3aed;">#' . (int)$in_student->id . ' (' . esc_html(ucfirst($in_student->status)) . ')</strong></div>' : '';

                                $tip_in_html = '<div class="aura-tip-card">'
                                    . '<div class="aura-tip-card-header">'
                                    . '<img src="' . esc_url($in_row['avatar']) . '" class="aura-tip-avatar-large" alt="' . esc_attr($u->display_name) . '">'
                                    . '<div class="aura-tip-info">'
                                    . '<div class="aura-tip-title">' . esc_html($u->display_name) . '</div>'
                                    . '<div class="aura-tip-subtitle">@' . esc_html($u->user_login) . ' &bull; ' . esc_html($in_row['roles']) . '</div>'
                                    . '<div class="aura-tip-badges">'
                                    . '<span class="aura-tip-badge" style="background:rgba(245,158,11,0.2);color:#f59e0b;">' . esc_html__('Pendiente de Configurar', 'aura-suite') . '</span>'
                                    . $tip_in_student_badge
                                    . '</div>'
                                    . '</div>'
                                    . '</div>'
                                    . '<div class="aura-tip-card-body">'
                                    . '<div class="aura-tip-row"><span>📧 Email:</span><strong>' . esc_html($u->user_email) . '</strong></div>'
                                    . '<div class="aura-tip-row"><span>🆔 ID WP:</span><strong>#' . (int)$u->ID . '</strong></div>'
                                    . $tip_in_student_row
                                    . '</div>'
                                    . '</div>';
                            ?>
                            <tr class="aura-parent-row" data-user-search="<?php echo esc_attr($row_search); ?>">
                                <td style="text-align:center;">
                                    <button type="button" class="aura-row-toggle" aria-expanded="false" title="<?php esc_attr_e('Ver detalles del usuario', 'aura-suite'); ?>">+</button>
                                </td>
                                <td>
                                    <div class="aura-user-identity aura-has-tooltip" data-aura-tooltip="<?php echo esc_attr(rawurlencode($tip_in_html)); ?>">
                                        <img src="<?php echo esc_url($in_row['avatar']); ?>" alt="<?php echo esc_attr($u->display_name); ?>" class="aura-user-avatar">
                                        <div class="aura-user-meta">
                                            <span class="aura-user-name"><?php echo esc_html($u->display_name); ?></span>
                                            <span class="aura-user-email"><?php echo esc_html($u->user_email); ?></span>
                                            <?php if ($in_student): ?>
                                            <div style="margin-top:3px;">
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=aura-students-list&search=' . urlencode($in_student->student_code ?: $u->user_email))); ?>" class="badge badge-violet" style="text-decoration:none; font-size:11px; padding:2px 7px; display:inline-flex; align-items:center; gap:4px; font-weight:700;" title="<?php esc_attr_e('Ver expediente académico en el módulo de Estudiantes', 'aura-suite'); ?>">
                                                    <span>🎓</span>
                                                    <span><?php echo esc_html($in_student->student_code ?: __('Estudiante AURA', 'aura-suite')); ?></span>
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="aura-col-mobile-hidden">
                                    <code>@<?php echo esc_html($u->user_login); ?></code>
                                </td>
                                <td class="aura-col-mobile-hidden">
                                    <span style="font-weight:600; color:var(--tx-secondary,#475569);"><?php echo esc_html($in_row['roles']); ?></span>
                                </td>
                                <td>
                                    <span class="aura-count-badge badge badge-amber">
                                        <?php _e('Sin permisos en Aura', 'aura-suite'); ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="<?php echo esc_url($activate_url); ?>" class="btn btn-emerald btn-sm btn-shimmer aura-btn-activate-user">
                                        <span class="dashicons dashicons-plus-alt"></span>
                                        <span><?php _e('Activar y Asignar Permisos', 'aura-suite'); ?></span>
                                    </a>
                                </td>
                            </tr>

                            <!-- Fila Hija WOW (.aura-child-card) -->
                            <tr class="aura-child-row" style="display:none;">
                                <td colspan="6">
                                    <div class="aura-child-card">
                                        <div class="aura-child-hero">
                                            <div class="aura-child-hero-title">
                                                <span class="dashicons dashicons-wordpress"></span>
                                                <span><?php printf(__('Usuario Disponible: %s', 'aura-suite'), esc_html($u->display_name)); ?></span>
                                            </div>
                                            <span class="aura-badge aura-badge-glass badge badge-amber">
                                                @<?php echo esc_html($u->user_login); ?> &bull; ID #<?php echo (int)$u->ID; ?>
                                            </span>
                                        </div>

                                        <div class="aura-child-grid">
                                            <div class="aura-child-subcard">
                                                <div class="aura-child-subcard-title">
                                                    <span class="dashicons dashicons-info"></span>
                                                    <span><?php _e('Estado del Usuario', 'aura-suite'); ?></span>
                                                </div>
                                                <p style="font-size:13px; color:var(--tx-secondary,#475569); margin:0;">
                                                    <?php _e('Este usuario está creado en WordPress pero aún no tiene acceso a ninguno de los módulos o áreas de Aura Business Suite.', 'aura-suite'); ?>
                                                </p>
                                            </div>

                                            <div class="aura-child-subcard">
                                                <div class="aura-child-subcard-title">
                                                    <span class="dashicons dashicons-email"></span>
                                                    <span><?php _e('Contacto y Rol', 'aura-suite'); ?></span>
                                                </div>
                                                <div style="font-size:12.5px; line-height:1.6; color:var(--tx-secondary,#475569);">
                                                    <div><strong>Email:</strong> <?php echo esc_html($u->user_email); ?></div>
                                                    <div><strong>Rol en WordPress:</strong> <?php echo esc_html($in_row['roles']); ?></div>
                                                </div>
                                            </div>

                                            <?php if ($in_student): ?>
                                            <!-- Subtarjeta 3: Expediente Académico AURA -->
                                            <div class="aura-child-subcard" style="border-left:3px solid #7c3aed;">
                                                <div class="aura-child-subcard-title" style="color:#7c3aed;">
                                                    <span class="dashicons dashicons-welcome-learn-more"></span>
                                                    <span><?php _e('Expediente Académico (Estudiante)', 'aura-suite'); ?></span>
                                                </div>
                                                <div style="font-size:12.5px; line-height:1.6; color:var(--tx-secondary,#475569);">
                                                    <div><strong>Expediente:</strong> #<?php echo (int)$in_student->id; ?></div>
                                                    <div><strong>Código:</strong> <code><?php echo esc_html($in_student->student_code ?: '—'); ?></code></div>
                                                    <div><strong>Estado:</strong> <span class="badge badge-violet" style="font-size:10.5px; padding:1px 6px;"><?php echo esc_html(ucfirst($in_student->status)); ?></span></div>
                                                    <div style="margin-top:6px;">
                                                        <a href="<?php echo esc_url(admin_url('admin.php?page=aura-students-list&search=' . urlencode($in_student->student_code ?: $u->user_email))); ?>" class="btn btn-sm btn-glass" style="font-size:11px; padding:2px 8px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                                                            <span class="dashicons dashicons-external"></span>
                                                            <span><?php _e('Abrir en Estudiantes', 'aura-suite'); ?></span>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="aura-child-dock">
                                            <a href="<?php echo esc_url($activate_url); ?>" class="btn btn-emerald btn-shimmer aura-btn-dock-action">
                                                <span class="dashicons dashicons-plus-alt"></span>
                                                <span><?php _e('Activar y Asignar Permisos Ahora', 'aura-suite'); ?></span>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="aura-empty-state empty-card">
                                        <span class="dashicons dashicons-yes-alt aura-empty-state-icon" style="color:#10b981;"></span>
                                        <h4 class="aura-empty-state-title"><?php _e('¡Todos los usuarios están configurados!', 'aura-suite'); ?></h4>
                                        <p class="aura-empty-state-desc"><?php _e('Todos los usuarios registrados en WordPress ya tienen permisos o áreas asignadas en Aura Suite.', 'aura-suite'); ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ====================================================================
         PANEL 3: CONFIGURACIÓN DE PERMISOS Y ÁREAS DEL USUARIO SELECCIONADO
         ==================================================================== -->
    <?php if ($selected_user): 
        $selected_avatar = get_avatar_url($selected_user->ID, array('size' => 64));
        $selected_caps_count = 0;
        $selected_caps_ui = Aura_Roles_Manager::get_capabilities_for_ui();
        foreach ($selected_caps_ui as $selected_module_caps) {
            foreach ($selected_module_caps['capabilities'] as $selected_cap_name => $selected_cap_info) {
                if (!empty($selected_user->allcaps[$selected_cap_name])) {
                    $selected_caps_count++;
                }
            }
        }
        $selected_user_areas = Aura_Areas_Setup::get_user_areas($selected_user->ID);
        $selected_areas_count = is_array($selected_user_areas) ? count($selected_user_areas) : 0;
        $selected_roles_label = !empty($selected_user->roles) ? implode(', ', array_map('ucfirst', $selected_user->roles)) : __('Sin rol', 'aura-suite');
    ?>
    <div id="aura-tab-panel-user-permissions" class="aura-tab-panel" style="<?php echo ($active_tab === 'user-permissions') ? '' : 'display:none;'; ?>">
        
        <!-- Tarjeta Hero del Usuario Seleccionado -->
        <div class="aura-selected-user-card glass-card fade-up">
            <img src="<?php echo esc_url($selected_avatar); ?>"
                 alt="<?php echo esc_attr($selected_user->display_name); ?>"
                 class="aura-selected-user-card__avatar avatar-ring" />
            <div class="aura-selected-user-card__meta">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px;">
                    <div>
                        <h3 style="margin:0 0 4px; font-size:16px; font-weight:800; color:var(--tx-primary,#0f172a); display:flex; align-items:center; gap:8px;">
                            <span><?php echo esc_html($selected_user->display_name); ?></span>
                            <span style="font-size:12px; font-weight:600; color:var(--tx-muted,#64748b);">(@<?php echo esc_html($selected_user->user_login); ?>)</span>
                        </h3>
                        <p style="margin:0; font-size:12.5px; color:var(--tx-muted,#64748b);">
                            <strong>Email:</strong> <?php echo esc_html($selected_user->user_email); ?> &nbsp;|&nbsp; 
                            <strong>Rol WP:</strong> <?php echo esc_html($selected_roles_label); ?> &nbsp;|&nbsp;
                            <strong>ID:</strong> #<?php echo (int)$selected_user->ID; ?>
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=aura-permissions&tab=active-users')); ?>" class="btn btn-secondary btn-sm btn-lift">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                            <span><?php _e('Cambiar de usuario', 'aura-suite'); ?></span>
                        </a>
                    </div>
                </div>

                <div style="display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-top:12px;">
                    <span class="aura-count-badge aura-count-badge--blue badge badge-blue">
                        <span class="dashicons dashicons-shield"></span>
                        <?php printf(esc_html__('Permisos activos: %d', 'aura-suite'), (int)$selected_caps_count); ?>
                    </span>
                    <span class="aura-count-badge aura-count-badge--green badge badge-emerald">
                        <span class="dashicons dashicons-networking"></span>
                        <?php printf(esc_html__('Áreas asignadas: %d', 'aura-suite'), (int)$selected_areas_count); ?>
                    </span>

                    <?php
                    $sel_is_student = isset($students_map[(int)$selected_user->ID]);
                    $sel_student_data = $sel_is_student ? $students_map[(int)$selected_user->ID] : null;
                    if ($sel_is_student): ?>
                        <span class="aura-count-badge badge badge-violet" style="background:rgba(124,58,237,0.15); color:#7c3aed; border:1px solid rgba(124,58,237,0.3); display:inline-flex; align-items:center; gap:5px;">
                            <span class="dashicons dashicons-welcome-learn-more" style="font-size:14px; width:14px; height:14px;"></span>
                            <span><?php printf(esc_html__('Estudiante (%s)', 'aura-suite'), esc_html($sel_student_data->student_code ?: '#' . $sel_student_data->id)); ?></span>
                        </span>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=aura-students-list&search=' . urlencode($sel_student_data->student_code ?: $selected_user->user_email))); ?>" class="btn btn-sm btn-glass" style="font-size:12px; padding:3px 10px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;" target="_blank">
                            <span class="dashicons dashicons-external"></span>
                            <span><?php _e('Ver expediente académico', 'aura-suite'); ?></span>
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-glass btn-sync-wp-as-student" data-user-id="<?php echo (int)$selected_user->ID; ?>" data-user-name="<?php echo esc_attr($selected_user->display_name); ?>" title="<?php esc_attr_e('Crear o vincular expediente académico para este usuario en el módulo de Estudiantes', 'aura-suite'); ?>" style="font-size:12px; padding:3px 10px; border:1px dashed var(--aura-blue,#2563eb); color:var(--aura-blue,#2563eb); display:inline-flex; align-items:center; gap:4px;">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                            <span><?php _e('⚡ Registrar / Vincular como Estudiante', 'aura-suite'); ?></span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Formulario Principal de Permisos y Áreas -->
        <form method="post" action="" id="aura-perm-form">
            <?php wp_nonce_field('assign_permissions', 'aura_permissions_nonce'); ?>
            <input type="hidden" name="user_id" value="<?php echo $selected_user->ID; ?>">

            <!-- SECCIÓN: ASIGNAR ÁREAS Y PROGRAMAS -->
            <div class="aura-config-section glass-card fade-up delay-1">
                <div class="aura-section-header">
                    <div>
                        <h2>
                            <span class="dashicons dashicons-networking" style="color:#2563eb;"></span>
                            <span><?php _e('1️⃣ Asignar Áreas y Programas de Responsabilidad', 'aura-suite'); ?></span>
                        </h2>
                        <p class="description">
                            <?php _e('Seleccione las áreas en las que este usuario participará y asigne su rol específico (Admin, Editor o Colaborador).', 'aura-suite'); ?>
                        </p>
                    </div>
                </div>

                <?php
                $user_areas = Aura_Areas_Setup::get_user_areas($selected_user->ID);
                $all_areas  = Aura_Areas_Setup::get_all_areas();
                ?>

                <div class="aura-areas-assign-grid">
                    <?php foreach ($all_areas as $area): 
                        $is_assigned = false;
                        $assigned_role = 'admin';
                        foreach ($user_areas as $user_area) {
                            if ($user_area->id == $area->id) {
                                $is_assigned = true;
                                $assigned_role = $user_area->role ?? 'admin';
                                break;
                            }
                        }
                        $area_logo_url = !empty($area->logo_id) ? wp_get_attachment_image_url((int)$area->logo_id, 'thumbnail') : '';
                    ?>
                    <div class="aura-area-assign-card <?php echo $is_assigned ? 'is-assigned' : ''; ?>" style="--area-color: <?php echo esc_attr($area->color ?: '#2271b1'); ?>;">
                        <label style="display:flex; align-items:center; gap:10px; flex:1; cursor:pointer; min-width:0; margin:0;">
                            <input type="checkbox" 
                                   name="user_areas[]" 
                                   value="<?php echo $area->id; ?>"
                                   <?php checked($is_assigned); ?>
                                   class="aura-area-checkbox">
                            <div class="aura-area-icon-wrap">
                                <?php if ($area_logo_url): ?>
                                    <img src="<?php echo esc_url($area_logo_url); ?>" alt="<?php echo esc_attr($area->name); ?>" class="aura-area-logo-thumb">
                                <?php else: ?>
                                    <span class="dashicons <?php echo esc_attr(!empty($area->icon) ? $area->icon : 'dashicons-networking'); ?>" style="color: <?php echo esc_attr($area->color ?: '#2271b1'); ?>;"></span>
                                <?php endif; ?>
                            </div>
                            <div style="min-width:0;">
                                <div style="font-weight:700; font-size:13px; color:var(--tx-primary,#0f172a); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo esc_html($area->name); ?></div>
                                <div style="font-size:11px; color:var(--tx-muted,#64748b);"><?php echo esc_html($area->type); ?></div>
                            </div>
                        </label>
                        <div style="flex-shrink:0;">
                            <select name="user_area_roles[<?php echo $area->id; ?>]" class="aura-area-role-select select-fancy" style="font-size:11.5px; height:30px; padding:0 8px; border-radius:6px; font-weight:600;">
                                <option value="admin" <?php selected($assigned_role, 'admin'); ?>>👑 <?php _e('Admin', 'aura-suite'); ?></option>
                                <option value="editor" <?php selected($assigned_role, 'editor'); ?>>✏️ <?php _e('Editor', 'aura-suite'); ?></option>
                                <option value="collaborator" <?php selected($assigned_role, 'collaborator'); ?>>🤝 <?php _e('Colaborador', 'aura-suite'); ?></option>
                            </select>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($all_areas)): ?>
                <div class="aura-empty-state empty-card">
                    <span class="dashicons dashicons-networking aura-empty-state-icon"></span>
                    <h4 class="aura-empty-state-title"><?php _e('No hay áreas creadas actualmente', 'aura-suite'); ?></h4>
                    <p class="aura-empty-state-desc"><?php _e('Cree áreas desde el módulo de Áreas y Programas para poder asignarlas a los usuarios.', 'aura-suite'); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- SECCIÓN: CAPABILITIES GRANULARES POR MÓDULO -->
            <div class="aura-config-section glass-card fade-up delay-2" id="aura-perm-caps-section">
                <div class="aura-perm-section-header">
                    <div>
                        <h2>
                            <span class="dashicons dashicons-shield" style="color:#10b981;"></span>
                            <span><?php _e('2️⃣ Asignar Capabilities Granulares por Módulo', 'aura-suite'); ?></span>
                        </h2>
                        <p class="description">
                            <?php _e('Active o desactive permisos puntuales para los diferentes submódulos de Aura Business Suite.', 'aura-suite'); ?>
                        </p>
                    </div>
                    <div class="aura-perm-global-controls">
                        <button type="button" class="btn btn-violet btn-shimmer btn-pulse-icon" id="aura-open-templates-btn">
                            <span class="dashicons dashicons-groups"></span>
                            <span><?php _e('Perfiles Predefinidos (10)', 'aura-suite'); ?></span>
                        </button>
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-expand-all-btn">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                            <span><?php _e('Expandir todo', 'aura-suite'); ?></span>
                        </button>
                        <button type="button" class="btn btn-secondary btn-lift" id="aura-collapse-all-btn">
                            <span class="dashicons dashicons-arrow-up-alt2"></span>
                            <span><?php _e('Colapsar todo', 'aura-suite'); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Barra de búsqueda Bootstrap 5.3 Input Group -->
                <div class="input-group" style="max-width: 520px; margin-bottom: 16px;">
                    <span class="input-group-text">🔍</span>
                    <input type="text" id="aura-cap-search" class="form-control aura-input-control" placeholder="<?php esc_attr_e('Buscar capability por nombre o módulo...', 'aura-suite'); ?>">
                    <span id="aura-search-count" class="input-group-text" style="display:none; font-weight:700; color:var(--aura-blue, #2563eb); font-size:12px;"></span>
                </div>

                <p style="font-size:12px; color:var(--tx-muted,#64748b); margin:0 0 16px;">
                    <abbr title="<?php esc_attr_e('Permiso administrativo sensible — asignar con precaución', 'aura-suite'); ?>">⭐</abbr>
                    <?php _e('= Permiso administrativo sensible (asignar con autorización previa).', 'aura-suite'); ?>
                </p>

                <?php
                $modules    = Aura_Roles_Manager::get_capabilities_for_ui();
                $user_caps  = $selected_user->allcaps;
                $current_group = '';
                $group_meta = array(
                    'admin'      => array('title' => __('👑 ADMINISTRACIÓN Y GOBIERNO GLOBAL', 'aura-suite'), 'desc' => __('Permisos maestros de gestión de usuarios, seguridad, áreas y directorio maestro.', 'aura-suite')),
                    'finance'    => array('title' => __('💰 GESTIÓN FINANCIERA Y CONTABLE', 'aura-suite'), 'desc' => __('Flujos de caja, transacciones, libro mayor, presupuestos y reportes.', 'aura-suite')),
                    'academic'   => array('title' => __('🎓 ACADÉMICO, INSCRIPCIONES Y FORMULARIOS', 'aura-suite'), 'desc' => __('Estudiantes, becas, pagos, certificados y formularios dinámicos.', 'aura-suite')),
                    'operations' => array('title' => __('📦 OPERACIONES, LOGÍSTICA Y RECURSOS', 'aura-suite'), 'desc' => __('Inventario de equipos, mantenimientos, flota vehicular, biblioteca y energía.', 'aura-suite')),
                );
                ?>

                <div class="aura-perm-accordion" id="aura-perm-accordion">
                    <?php foreach ($modules as $module):
                        $module_group = $module['group'] ?? 'operations';
                        $module_key   = $module['module'];
                        $total_caps   = count($module['capabilities']);
                        $active_count = 0;
                        foreach ($module['capabilities'] as $cap_name => $cap_info) {
                            if (!empty($user_caps[$cap_name])) $active_count++;
                        }
                        $all_selected = ($active_count === $total_caps && $total_caps > 0);
                        $some_selected = ($active_count > 0 && $active_count < $total_caps);
                        $is_open = ($active_count > 0);

                        if ($module_group !== $current_group):
                            $current_group = $module_group;
                            $g_info = $group_meta[$current_group] ?? array('title' => strtoupper($current_group), 'desc' => '');
                        ?>
                            <div class="aura-perm-group-header" data-group="<?php echo esc_attr($current_group); ?>">
                                <h3 class="aura-perm-group-title"><?php echo esc_html($g_info['title']); ?></h3>
                            </div>
                        <?php endif; ?>

                        <div class="aura-perm-module <?php echo $is_open ? 'is-open' : ''; ?>"
                             data-module="<?php echo esc_attr($module_key); ?>"
                             data-group="<?php echo esc_attr($module_group); ?>">

                            <div class="aura-perm-module-header" role="button" tabindex="0" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
                                <div class="aura-perm-module-meta">
                                    <span style="font-size:18px;"><?php echo $module['icon']; ?></span>
                                    <span class="aura-perm-module-name"><?php echo esc_html($module['title']); ?></span>
                                    <span class="aura-perm-module-badge <?php echo ($active_count > 0) ? 'aura-perm-module-badge--active' : ''; ?>">
                                        <?php echo ($active_count > 0) ? $active_count . '/' . $total_caps : $total_caps; ?>
                                    </span>
                                </div>

                                <div style="display:flex; align-items:center; gap:12px;">
                                    <label class="aura-perm-select-all" onclick="event.stopPropagation()" style="font-size:12px; cursor:pointer; font-weight:600; color:var(--tx-muted,#64748b);">
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

                            <div class="aura-perm-module-body">
                                <div class="aura-perm-cap-grid">
                                    <?php foreach ($module['capabilities'] as $cap_name => $cap_info):
                                        $is_active = !empty($user_caps[$cap_name]);
                                        $is_star   = !empty($cap_info['star']);
                                    ?>
                                    <label class="aura-perm-cap-item <?php echo $is_active ? 'is-active' : ''; ?>"
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
                                             <abbr title="<?php esc_attr_e('Permiso administrativo sensible', 'aura-suite'); ?>">⭐</abbr>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Botón Hero de Guardado -->
            <p class="submit" style="margin-bottom:60px;">
                <button type="submit" name="aura_assign_permissions" class="btn btn-emerald btn-hero btn-shimmer btn-pulse-icon aura-btn-hero-save">
                    <span class="dashicons dashicons-saved"></span>
                    <span><?php _e('Guardar Permisos y Áreas del Usuario', 'aura-suite'); ?></span>
                </button>
            </p>
        </form>

        <!-- Barra Flotante Sticky de Guardado -->
        <div class="aura-perm-sticky-save" id="aura-perm-sticky-save">
            <span id="aura-perm-sticky-total">
                <strong><?php echo (int)$selected_caps_count; ?></strong> <?php _e('permisos activos', 'aura-suite'); ?>
            </span>
            <button type="submit"
                    name="aura_assign_permissions"
                    value="1"
                    form="aura-perm-form"
                    class="btn btn-emerald btn-shimmer btn-pulse-icon aura-perm-sticky-btn"
                    style="border-radius:9999px !important;">
                <span class="dashicons dashicons-saved"></span>
                <span><?php _e('Guardar Cambios', 'aura-suite'); ?></span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- ====================================================================
         PANEL 4: PLANTILLAS DE PERFILES PREDEFINIDOS
         ==================================================================== -->
    <div id="aura-tab-panel-profile-templates" class="aura-tab-panel" style="<?php echo ($active_tab === 'profile-templates') ? '' : 'display:none;'; ?>">
        <div class="aura-config-section glass-card fade-up">
            <div class="aura-section-header">
                <div>
                    <h2>
                        <span class="dashicons dashicons-groups" style="color:#8b5cf6;"></span>
                        <span><?php _e('Catálogo de Perfiles de Negocio Predefinidos', 'aura-suite'); ?></span>
                    </h2>
                    <p class="description">
                        <?php _e('Conjuntos de capabilities probadas para roles departamentales específicos. Al hacer clic se aplican en 1 solo paso.', 'aura-suite'); ?>
                    </p>
                </div>
                <div id="aura-templates-count-indicator" style="font-weight:700; font-size:13px; color:var(--tx-muted, #64748b);">
                    <?php printf(esc_html__('Mostrando %d perfiles estándar', 'aura-suite'), count($profile_templates)); ?>
                </div>
            </div>

            <!-- Banner Informativo del Usuario Seleccionado -->
            <?php if ($selected_user): ?>
            <div class="aura-user-context-banner alert-card alert-success" style="border-radius: 12px; padding: 14px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span class="dashicons dashicons-admin-users" style="font-size:24px; width:24px; height:24px; color:var(--aura-emerald, #059669);"></span>
                    <div>
                        <div style="font-weight:700; font-size:14px; color:var(--tx-primary, #065f46);">
                            <?php printf(__('Aplicando plantillas a: %s (%s)', 'aura-suite'), esc_html($selected_user->display_name), esc_html($selected_user->user_email)); ?>
                        </div>
                        <div style="font-size:12.5px; color:var(--tx-secondary, #047857);">
                            <?php _e('Al hacer clic en "Aplicar esta plantilla", los capabilities del perfil se asignarán y guardarán automáticamente en este usuario.', 'aura-suite'); ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary btn-lift" onclick="switchTab('user-permissions')">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <span><?php _e('Volver a Configurar Permisos', 'aura-suite'); ?></span>
                </button>
            </div>
            <?php else: ?>
            <div class="aura-user-context-banner alert-card alert-info" style="border-radius: 12px; padding: 14px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span class="dashicons dashicons-info" style="font-size:24px; width:24px; height:24px; color:var(--aura-blue, #2563eb);"></span>
                    <div>
                        <div style="font-weight:700; font-size:13.5px; color:var(--tx-primary, #1e40af);">
                            <?php _e('Modo de Exploración de Perfiles', 'aura-suite'); ?>
                        </div>
                        <div style="font-size:12.5px; color:var(--tx-secondary, #3b82f6);">
                            <?php _e('Seleccione primero un usuario activo para poder aplicar directamente cualquiera de estas 10 plantillas predefinidas.', 'aura-suite'); ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary btn-lift" onclick="switchTab('active-users')">
                    <span class="dashicons dashicons-admin-users"></span>
                    <span><?php _e('Seleccionar Usuario Activo', 'aura-suite'); ?></span>
                </button>
            </div>
            <?php endif; ?>

            <!-- Filtros y Buscador de Plantillas con Input Group -->
            <div class="aura-templates-filter-bar">
                <div class="aura-templates-pills">
                    <button type="button" class="aura-template-filter-btn is-active" data-filter="all">
                        <span><?php _e('Todos', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" class="aura-template-filter-btn" data-filter="finance">
                        <span>💰 <?php _e('Finanzas', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" class="aura-template-filter-btn" data-filter="direction">
                        <span>👔 <?php _e('Dirección', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" class="aura-template-filter-btn" data-filter="audit">
                        <span>🔍 <?php _e('Auditoría', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" class="aura-template-filter-btn" data-filter="projects">
                        <span>🏢 <?php _e('Proyectos', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" class="aura-template-filter-btn" data-filter="logistics">
                        <span>🚗 <?php _e('Logística', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" class="aura-template-filter-btn" data-filter="education">
                        <span>🎓 <?php _e('Educación', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" class="aura-template-filter-btn" data-filter="library">
                        <span>📚 <?php _e('Biblioteca', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" class="aura-template-filter-btn" data-filter="field">
                        <span>📍 <?php _e('Campo', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" class="aura-template-filter-btn" data-filter="admin">
                        <span>🛠️ <?php _e('TI & Admin', 'aura-suite'); ?></span>
                    </button>
                </div>

                <div class="input-group" style="max-width: 380px;">
                    <span class="input-group-text">🔍</span>
                    <input type="search" id="aura-template-search" class="form-control aura-input-control" placeholder="<?php esc_attr_e('Buscar perfil por nombre o función...', 'aura-suite'); ?>">
                </div>
            </div>

            <!-- Grid de Plantillas -->
            <div class="aura-templates-grid" id="aura-templates-grid">
                <?php foreach ($profile_templates as $template_id => $template):
                    $t_icon = !empty($template['icon']) ? $template['icon'] : 'dashicons-admin-users';
                    $t_color = !empty($template['color']) ? $template['color'] : '#2271b1';
                    $t_category = !empty($template['category']) ? $template['category'] : __('General', 'aura-suite');
                    $t_slug = !empty($template['category_slug']) ? $template['category_slug'] : 'all';
                    $t_caps_count = !empty($template['capabilities']) ? count($template['capabilities']) : 0;
                    $t_highlights = !empty($template['highlights']) ? $template['highlights'] : array();
                ?>
                <div class="aura-template-card glass-card" 
                     data-template-id="<?php echo esc_attr($template_id); ?>"
                     data-template-name="<?php echo esc_attr($template['name']); ?>"
                     data-caps-count="<?php echo esc_attr($t_caps_count); ?>"
                     data-category="<?php echo esc_attr($t_slug); ?>"
                     data-keywords="<?php echo esc_attr(strtolower($template['name'] . ' ' . $template['description'] . ' ' . $t_category)); ?>"
                     style="--role-color: <?php echo esc_attr($t_color); ?>;"
                     onclick="applyTemplate('<?php echo esc_attr($template_id); ?>')">
                    <div class="aura-template-card__header">
                        <div class="aura-template-card__icon-box">
                            <span class="dashicons <?php echo esc_attr($t_icon); ?>"></span>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                <span style="font-size:11px; font-weight:700; color:var(--role-color); text-transform:uppercase;"><?php echo esc_html($t_category); ?></span>
                                <span class="aura-count-badge aura-count-badge--blue badge badge-blue" style="font-size:10.5px; padding:2px 6px;">
                                    <?php echo sprintf(__('%d caps', 'aura-suite'), $t_caps_count); ?>
                                </span>
                            </div>
                            <h4 class="aura-template-card__title"><?php echo esc_html($template['name']); ?></h4>
                        </div>
                    </div>

                    <p class="aura-template-card__desc"><?php echo esc_html($template['description']); ?></p>

                    <?php if (!empty($t_highlights)): ?>
                    <ul style="margin:0 0 12px 16px; padding:0; font-size:11.5px; color:var(--tx-secondary, #475569); line-height:1.4;">
                        <?php foreach ($t_highlights as $highlight): ?>
                        <li><?php echo esc_html($highlight); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <div class="aura-template-card__footer">
                        <button type="button" class="btn btn-primary btn-shimmer aura-template-card__btn" 
                                data-template-id="<?php echo esc_attr($template_id); ?>"
                                onclick="event.stopPropagation(); applyTemplate('<?php echo esc_attr($template_id); ?>');"
                                style="background:var(--role-color) !important; border:none !important;">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <span><?php _e('Aplicar esta plantilla', 'aura-suite'); ?></span>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="aura-template-empty-message" id="aura-template-empty" style="display:none; grid-column: 1/-1;">
                    <div class="aura-empty-state empty-card">
                        <span class="dashicons dashicons-search aura-empty-state-icon"></span>
                        <h4 class="aura-empty-state-title"><?php _e('No se encontraron perfiles predefinidos', 'aura-suite'); ?></h4>
                        <p class="aura-empty-state-desc"><?php _e('Pruebe con otros términos de búsqueda o seleccione otra categoría.', 'aura-suite'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================================================
         MODAL UNIFICADO DE ASIGNACIÓN O CREACIÓN DE USUARIOS
         ==================================================================== -->
    <div id="aura-modal-user-manager" class="aura-user-modal-overlay aura-modal-overlay">
        <div class="aura-modal-container glass-card" style="max-width: 620px; border-radius:16px;">
            <div class="aura-modal-header" style="padding:18px 24px; border-bottom:1px solid var(--border-color, #e2e8f0);">
                <div class="aura-modal-header-meta" style="display:flex; align-items:center; gap:12px;">
                    <div class="aura-header-icon-box" style="width:42px; height:42px; min-width:42px; background:var(--badge-blue-bg, #eff6ff); color:var(--aura-blue, #2563eb); border:none; border-radius:10px;">
                        <span class="dashicons dashicons-admin-users" style="font-size:22px; width:22px; height:22px;"></span>
                    </div>
                    <div>
                        <h3 style="margin:0 0 2px; font-size:16px; font-weight:800; color:var(--tx-primary, #0f172a);"><?php _e('Asignar o Crear Usuario', 'aura-suite'); ?></h3>
                        <p style="margin:0; font-size:12.5px; color:var(--tx-muted, #64748b);"><?php _e('Seleccione un usuario existente o registre uno nuevo con acceso rápido.', 'aura-suite'); ?></p>
                    </div>
                </div>
                <button type="button" class="aura-modal-close" id="aura-modal-btn-close" style="font-size:24px; line-height:1; border:none; background:transparent; cursor:pointer; color:var(--tx-muted, #64748b);">&times;</button>
            </div>

            <!-- Pestañas del Modal -->
            <div class="aura-modal-tabs-wrapper">
                <div class="aura-modal-tabs">
                    <button type="button" class="aura-modal-tab-btn is-active" data-modal-tab="select-wp">
                        <span><?php _e('Seleccionar de WordPress', 'aura-suite'); ?></span>
                        <span class="aura-count-badge badge badge-blue" style="margin-left:4px; font-size:11px;"><?php echo $count_inactive_wp; ?></span>
                    </button>
                    <button type="button" class="aura-modal-tab-btn" data-modal-tab="create-new">
                        <span>+ <?php _e('Crear Nuevo Usuario', 'aura-suite'); ?></span>
                    </button>
                </div>
            </div>

            <div class="aura-modal-body">
                <!-- Pestaña 1: Seleccionar de WP -->
                <div class="aura-modal-tab-panel is-active" id="aura-panel-select-wp">
                    <?php if (!empty($inactive_wp_users)): ?>
                    <div class="input-group" style="margin-bottom:14px;">
                        <span class="input-group-text">🔍</span>
                        <input type="text" id="aura-modal-wp-search" class="form-control"
                               placeholder="<?php esc_attr_e('Buscar usuario por nombre, usuario (@login) o email...', 'aura-suite'); ?>">
                    </div>

                    <div class="aura-wp-users-list" id="aura-modal-wp-users-list" style="display:flex; flex-direction:column; gap:8px;">
                        <?php foreach ($inactive_wp_users as $in_user): 
                            $u = $in_user['user'];
                            $search_str = strtolower($u->display_name . ' ' . $u->user_login . ' ' . $u->user_email . ' ' . $in_user['roles']);
                        ?>
                        <div class="aura-wp-user-card" data-wp-user-id="<?php echo (int) $u->ID; ?>" data-search="<?php echo esc_attr($search_str); ?>">
                            <input type="radio" name="aura_selected_modal_user" value="<?php echo (int) $u->ID; ?>" style="margin:0;">
                            <img src="<?php echo esc_url($in_user['avatar']); ?>" class="aura-user-avatar" alt="<?php echo esc_attr($u->display_name); ?>">
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; font-size:13px; color:inherit;"><?php echo esc_html($u->display_name); ?> <span style="font-weight:400; opacity:0.7; font-size:12px;">(@<?php echo esc_html($u->user_login); ?>)</span></div>
                                <div style="font-size:11.5px; opacity:0.75;"><?php echo esc_html($u->user_email); ?></div>
                            </div>
                            <span class="badge" style="font-size:11px;"><?php echo esc_html($in_user['roles']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="aura-empty-state empty-card" style="padding:24px 16px;">
                        <span class="dashicons dashicons-yes-alt aura-empty-state-icon" style="color:var(--aura-emerald, #10b981);"></span>
                        <h4 class="aura-empty-state-title"><?php _e('¡Todos los usuarios configurados!', 'aura-suite'); ?></h4>
                        <p class="aura-empty-state-desc"><?php _e('No hay usuarios de WordPress pendientes por configurar. Puede registrar un nuevo usuario con la pestaña "+ Crear Nuevo Usuario".', 'aura-suite'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pestaña 2: Crear Nuevo Usuario con Bootstrap 5.3 Input Groups y emojis -->
                <div class="aura-modal-tab-panel" id="aura-panel-create-new">
                    <div id="aura-crear-usuario-error" style="display:none; background:#fef2f2; border-left:4px solid #ef4444; padding:10px 14px; border-radius:6px; margin-bottom:16px; color:#b91c1c; font-size:13px;"></div>
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label for="aura_cu_first_name" style="font-weight:700; font-size:12.5px; display:block; margin-bottom:6px; color:var(--tx-primary, inherit);">
                                <?php _e('Nombre *', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Nombre de pila del nuevo usuario', 'aura-suite'); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">👤</span>
                                <input type="text" id="aura_cu_first_name" class="form-control" placeholder="<?php esc_attr_e('Ej. Carlos', 'aura-suite'); ?>" required>
                            </div>
                        </div>

                        <div>
                            <label for="aura_cu_last_name" style="font-weight:700; font-size:12.5px; display:block; margin-bottom:6px; color:var(--tx-primary, inherit);">
                                <?php _e('Apellido', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Apellido familiar del nuevo usuario', 'aura-suite'); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">👤</span>
                                <input type="text" id="aura_cu_last_name" class="form-control" placeholder="<?php esc_attr_e('Ej. Mendoza', 'aura-suite'); ?>">
                            </div>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label for="aura_cu_email" style="font-weight:700; font-size:12.5px; display:block; margin-bottom:6px; color:var(--tx-primary, inherit);">
                                <?php _e('Correo Electrónico *', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Correo electrónico corporativo para el acceso y notificaciones', 'aura-suite'); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">✉️</span>
                                <input type="email" id="aura_cu_email" class="form-control" placeholder="correo@ejemplo.com" required>
                            </div>
                        </div>

                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <label for="aura_cu_user_login" style="font-weight:700; font-size:12.5px; color:var(--tx-primary, inherit);">
                                    <?php _e('Nombre de usuario (@login)', 'aura-suite'); ?>
                                </label>
                                <label style="font-size:11px; cursor:pointer; color:var(--aura-blue, #2563eb); display:inline-flex; align-items:center; gap:3px;">
                                    <input type="checkbox" id="aura_cu_auto_username" checked style="margin:0;">
                                    <span>⚡ <?php _e('Auto de email', 'aura-suite'); ?></span>
                                </label>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text">🏷️</span>
                                <input type="text" id="aura_cu_user_login" class="form-control" placeholder="cmendoza">
                            </div>
                        </div>

                        <div>
                            <label for="aura_cu_phone" style="font-weight:700; font-size:12.5px; display:block; margin-bottom:6px; color:var(--tx-primary, inherit);">
                                <?php _e('Teléfono / WhatsApp', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Número de contacto opcional para alertas o mensajes', 'aura-suite'); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">📱</span>
                                <input type="text" id="aura_cu_phone" class="form-control" placeholder="+57 300 000 0000">
                            </div>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label for="aura_cu_password" style="font-weight:700; font-size:12.5px; display:block; margin-bottom:6px; color:var(--tx-primary, inherit);">
                                <?php _e('Contraseña de Acceso *', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Mínimo 8 caracteres, o genere una contraseña segura aleatoria', 'aura-suite'); ?>"><span class="dashicons dashicons-editor-help"></span></span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">🔒</span>
                                <input type="password" id="aura_cu_password" class="form-control" placeholder="<?php esc_attr_e('Mínimo 8 caracteres', 'aura-suite'); ?>" required>
                                <button type="button" id="aura-toggle-pwd" class="btn btn-secondary" title="<?php esc_attr_e('Mostrar/ocultar contraseña', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                                <button type="button" id="aura-generar-pwd" class="btn btn-secondary" title="<?php esc_attr_e('Generar clave aleatoria', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-randomize"></span>
                                    <span><?php _e('Generar', 'aura-suite'); ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="aura-modal-footer">
                <button type="button" class="btn btn-secondary" id="aura-modal-btn-cancel"><?php _e('Cancelar', 'aura-suite'); ?></button>
                <button type="button" class="btn btn-primary btn-shimmer" id="aura-modal-btn-action-select">
                    <span class="dashicons dashicons-saved"></span>
                    <span><?php _e('Configurar permisos del usuario', 'aura-suite'); ?></span>
                </button>
                <button type="button" class="btn btn-emerald btn-shimmer" id="aura-modal-btn-action-create" style="display:none;">
                    <span class="dashicons dashicons-saved"></span>
                    <span><?php _e('Crear y configurar permisos', 'aura-suite'); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ── FOOTER GLOBAL CANÓNICO AURA BUSINESS SUITE ── -->
    <div class="adp-footer">
        <p>
            Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; Versión <?php echo esc_html(AURA_VERSION); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
        </p>
    </div>

</div><!-- /.wrap.aura-app-context -->
</div><!-- /.aura-app-wrapper.aura-permissions-wrap -->

<script>
window.auraProfileTemplatesJson = <?php echo wp_json_encode($profile_templates); ?>;
</script>
