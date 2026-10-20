<?php
/**
 * Plantilla del Directorio Maestro de Contactos y Google Sync
 *
 * Cumple rigurosamente con el Prompt Maestro Global de UX/UI, Responsive Design,
 * Glassmorphism, VIP Header, KPIs, Filtros Reactivos y Modo Oscuro de Aura Business Suite.
 *
 * @package AuraBusinessSuite
 * @subpackage Contacts
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$can_view   = current_user_can( 'manage_options' ) || current_user_can( 'aura_contacts_view' );
if ( ! $can_view ) {
    wp_die( esc_html__( 'No tienes permisos suficientes para acceder al Directorio de Contactos.', 'aura-suite' ) );
}

$can_create = current_user_can( 'manage_options' ) || current_user_can( 'aura_contacts_create' );
$can_edit   = current_user_can( 'manage_options' ) || current_user_can( 'aura_contacts_edit' );
$can_delete = current_user_can( 'manage_options' ) || current_user_can( 'aura_contacts_delete' );
$can_sync   = current_user_can( 'manage_options' ) || current_user_can( 'aura_contacts_sync' ) || current_user_can( 'aura_contacts_sync_google' );
$can_google = current_user_can( 'manage_options' ) || current_user_can( 'aura_contacts_manage_google' );
$can_export = current_user_can( 'manage_options' ) || current_user_can( 'aura_contacts_export' );

$kpis              = Aura_Contacts::get_stats();
$active_auth_mode  = Aura_Google_Auth_Factory::get_active_mode();
$auth_modes        = Aura_Google_Auth_Factory::get_available_modes();
$provider          = Aura_Google_Auth_Factory::get_provider();
$is_connected      = $provider->is_connected();
$account_email     = $provider->get_account_email();
$oauth_provider    = Aura_Google_Auth_Factory::get_oauth_provider();
$is_configured     = $oauth_provider->is_configured();
$auth_url          = $oauth_provider->get_auth_url();
$redirect_uri      = Aura_Google_Auth_OAuth_User::get_redirect_uri();
$last_error        = get_option( Aura_Google_Auth_OAuth_User::OPTION_LAST_ERROR, '' );

$sa_provider       = new Aura_Google_Auth_Service_Account();
$sa_is_configured  = $sa_provider->is_configured();
$sa_is_connected   = $sa_provider->is_connected();
$sa_delegated      = $sa_provider->get_delegated_user();
$sa_email          = $sa_provider->get_account_email();
$sa_last_error     = get_option( Aura_Google_Auth_Service_Account::OPTION_LAST_ERROR, '' );

$export_nonce      = wp_create_nonce( 'aura_contacts_export_action' );
$categories        = Aura_Contacts::CATEGORIES;

$category_icons = array(
    'student'     => '🎓',
    'teacher'     => '👨‍🏫',
    'staff'       => '👔',
    'donor'       => '🤝',
    'supplier'    => '🏢',
    'member'      => '⭐',
    'volunteer'   => '🤲',
    'facilitator' => '🎙️',
    'other'       => '🏷️',
);
?>

<div class="wrap aura-app-context aura-contacts-wrapper">
    <div class="aura-layout">
        <main class="aura-content">

            <!-- ── CABECERA PRINCIPAL CANÓNICA (HERO GLASS CARD) ──────────── -->
            <header class="aura-page-header hero-card aura-glass-card fade-up">
                <div class="aura-header-left" style="display: flex; align-items: center; gap: 18px; flex: 1 1 auto; min-width: 0;">
                    <div class="aura-page-header__icon aura-contacts-hero-icon">
                        <span class="dashicons dashicons-id-alt"></span>
                    </div>
                    <div class="aura-header-text" style="flex: 1 1 auto; min-width: 0;">
                        <div class="aura-title-with-badge" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <h1 class="aura-page-title" style="margin: 0; color: #ffffff !important; font-weight: 800; font-size: 1.45rem; line-height: 1.25;">
                                <?php esc_html_e( 'Directorio Maestro de Contactos', 'aura-suite' ); ?>
                            </h1>
                            <span class="badge badge-blue">
                                <span class="pulse-dot"></span>
                                <strong id="header-cnt-total"><?php echo esc_html( $kpis['total'] ?? 0 ); ?></strong> <?php esc_html_e( 'contactos', 'aura-suite' ); ?>
                            </span>
                            <?php if ( $is_connected ) : ?>
                                <span class="badge badge-emerald" title="<?php printf( esc_attr__( 'Conectado a Google: %s', 'aura-suite' ), esc_attr( $account_email ) ); ?>">
                                    <span class="pulse-dot dot-emerald"></span>
                                    <span>Google Sincronizado</span>
                                </span>
                            <?php else : ?>
                                <span class="badge badge-amber" title="<?php esc_attr_e( 'Google Contacts desconectado', 'aura-suite' ); ?>">
                                    <span class="dashicons dashicons-warning" style="font-size:12px;width:12px;height:12px;line-height:12px;"></span>
                                    <span>Google Desconectado</span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="aura-page-subtitle hero-desc" style="margin: 6px 0 0; color: rgba(255, 255, 255, 0.88) !important; font-size: 0.88rem; line-height: 1.45;">
                            <?php esc_html_e( 'Libreta unificada institucional de la organización con sincronización automática bidireccional en tiempo real con Google Contacts.', 'aura-suite' ); ?>
                        </p>
                    </div>
                </div>

                <div class="aura-header-right" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-left: auto; z-index: 10;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-suite' ) ); ?>" class="btn btn-glass btn-lift">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <span><?php esc_html_e( 'Dashboard', 'aura-suite' ); ?></span>
                    </a>

                    <?php if ( $can_sync ) : ?>
                        <?php if ( $is_connected ) : ?>
                            <button type="button" class="btn btn-glass btn-lift" id="btn-sync-all" title="<?php esc_attr_e( 'Sincronizar cambios bidireccionalmente con Google People API', 'aura-suite' ); ?>">
                                <span class="dashicons dashicons-update"></span>
                                <span><?php esc_html_e( 'Sincronizar Ahora', 'aura-suite' ); ?></span>
                            </button>
                        <?php else : ?>
                            <button type="button" class="btn btn-glass btn-lift" onclick="$('.aura-navbar-item[data-tab=\'google_settings\']').click();" style="border: 1px dashed rgba(245, 158, 11, 0.45); color: #fbbf24;" title="<?php esc_attr_e( 'Google Contacts no está conectado. Haz clic aquí para autorizar tu cuenta en Google & Workspace.', 'aura-suite' ); ?>">
                                <span class="dashicons dashicons-google" style="color: #f59e0b;"></span>
                                <span><?php esc_html_e( 'Conectar Google Sync', 'aura-suite' ); ?></span>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ( $can_create ) : ?>
                        <button type="button" class="btn btn-primary btn-shimmer btn-lift" id="btn-open-create-modal" onclick="if(window.openCreateModal) window.openCreateModal();">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <span><?php esc_html_e( 'Nuevo Contacto', 'aura-suite' ); ?></span>
                        </button>
                    <?php endif; ?>
                </div>
            </header>

            <!-- ── AVISO DE CONEXIÓN O ERROR DE GOOGLE (SI APLICA) ────────── -->
            <?php if ( ! empty( $last_error ) && $can_google ) : ?>
                <div class="aura-alert aura-alert--danger fade-up" style="margin-top: 15px;">
                    <span class="dashicons dashicons-warning"></span>
                    <div class="aura-alert__content">
                        <strong><?php esc_html_e( 'Alerta en sincronización con Google Contacts:', 'aura-suite' ); ?></strong>
                        <span><?php echo esc_html( $last_error ); ?></span>
                    </div>
                    <button type="button" class="aura-alert__close" onclick="this.parentElement.remove();">&times;</button>
                </div>
            <?php endif; ?>

            <!-- ── TARJETAS KPI RESUMEN ───────────────────────────────────── -->
            <div class="aura-kpi-grid fade-up delay-1">
                <!-- Total Contactos -->
                <div class="aura-glass-card kpi-card card-lift accent-blue" title="<?php esc_attr_e( 'Total de contactos activos en libreta institucional', 'aura-suite' ); ?>">
                    <div class="kpi-icon-wrap" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">
                        <span class="dashicons dashicons-id-alt"></span>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label"><?php esc_html_e( 'Total', 'aura-suite' ); ?></span>
                        <h3 class="kpi-value" id="kpi-total-val"><?php echo esc_html( $kpis['total'] ?? 0 ); ?></h3>
                    </div>
                </div>

                <!-- Sincronizados con Google -->
                <div class="aura-glass-card kpi-card card-lift accent-emerald" title="<?php esc_attr_e( 'Contactos sincronizados exitosamente con Google People API', 'aura-suite' ); ?>">
                    <div class="kpi-icon-wrap" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <span class="dashicons dashicons-cloud"></span>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label"><?php esc_html_e( 'Google Sync', 'aura-suite' ); ?></span>
                        <h3 class="kpi-value" id="kpi-synced-val" style="color: #10b981;"><?php echo esc_html( $kpis['synced'] ?? 0 ); ?></h3>
                    </div>
                </div>

                <!-- Pendientes de Subida -->
                <div class="aura-glass-card kpi-card card-lift accent-amber" title="<?php esc_attr_e( 'Contactos pendientes o en cola de sincronización', 'aura-suite' ); ?>">
                    <div class="kpi-icon-wrap" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                        <span class="dashicons dashicons-backup"></span>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label"><?php esc_html_e( 'Pendientes', 'aura-suite' ); ?></span>
                        <h3 class="kpi-value" id="kpi-pending-val" style="color: #f59e0b;"><?php echo esc_html( $kpis['pending'] ?? 0 ); ?></h3>
                    </div>
                </div>

                <!-- Con Errores -->
                <div class="aura-glass-card kpi-card card-lift accent-rose" title="<?php esc_attr_e( 'Contactos que reportaron errores de sincronización', 'aura-suite' ); ?>">
                    <div class="kpi-icon-wrap" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label"><?php esc_html_e( 'Errores', 'aura-suite' ); ?></span>
                        <h3 class="kpi-value" id="kpi-error-val" style="color: #ef4444;"><?php echo esc_html( $kpis['error'] ?? 0 ); ?></h3>
                    </div>
                </div>

                <!-- Vinculados (Terceros / WP) -->
                <div class="aura-glass-card kpi-card card-lift accent-purple" title="<?php esc_attr_e( 'Contactos vinculados a terceros o usuarios WordPress', 'aura-suite' ); ?>">
                    <div class="kpi-icon-wrap" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                        <span class="dashicons dashicons-admin-links"></span>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label"><?php esc_html_e( 'Vinculados', 'aura-suite' ); ?></span>
                        <h3 class="kpi-value" id="kpi-third-val"><?php echo esc_html( $kpis['with_third_party'] ?? 0 ); ?></h3>
                    </div>
                </div>
            </div>

            <!-- ── BARRA DE PESTAÑAS (TABS NAVBAR) ────────────────────────── -->
            <nav class="aura-navbar aura-nav aura-glass-card aura-contacts-navbar fade-up delay-2">
                <button type="button" class="aura-navbar-item aura-tab-btn is-active active" data-tab="directory" data-category="" title="<?php esc_attr_e( 'Todos los Contactos', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-grid-view"></span>
                    <span><?php esc_html_e( 'Todos', 'aura-suite' ); ?></span>
                    <span class="aura-tab-count" id="tab-count-all"><?php echo esc_html( $kpis['total'] ?? 0 ); ?></span>
                </button>

                <button type="button" class="aura-navbar-item aura-tab-btn" data-tab="directory" data-category="student" title="<?php esc_attr_e( 'Estudiantes', 'aura-suite' ); ?>">
                    <span>🎓</span>
                    <span><?php esc_html_e( 'Estudiantes', 'aura-suite' ); ?></span>
                    <span class="aura-tab-count" id="tab-count-student"><?php echo esc_html( $kpis['by_category']['student'] ?? 0 ); ?></span>
                </button>

                <button type="button" class="aura-navbar-item aura-tab-btn" data-tab="directory" data-category="teacher" title="<?php esc_attr_e( 'Docentes y Facilitadores', 'aura-suite' ); ?>">
                    <span>👨‍🏫</span>
                    <span><?php esc_html_e( 'Docentes', 'aura-suite' ); ?></span>
                    <span class="aura-tab-count" id="tab-count-teacher"><?php echo esc_html( ( $kpis['by_category']['teacher'] ?? 0 ) + ( $kpis['by_category']['facilitator'] ?? 0 ) ); ?></span>
                </button>

                <button type="button" class="aura-navbar-item aura-tab-btn" data-tab="directory" data-category="staff" title="<?php esc_attr_e( 'Personal y Staff', 'aura-suite' ); ?>">
                    <span>👔</span>
                    <span><?php esc_html_e( 'Personal', 'aura-suite' ); ?></span>
                    <span class="aura-tab-count" id="tab-count-staff"><?php echo esc_html( $kpis['by_category']['staff'] ?? 0 ); ?></span>
                </button>

                <button type="button" class="aura-navbar-item aura-tab-btn" data-tab="directory" data-category="supplier" title="<?php esc_attr_e( 'Proveedores', 'aura-suite' ); ?>">
                    <span>🏢</span>
                    <span><?php esc_html_e( 'Proveedores', 'aura-suite' ); ?></span>
                    <span class="aura-tab-count" id="tab-count-supplier"><?php echo esc_html( $kpis['by_category']['supplier'] ?? 0 ); ?></span>
                </button>

                <button type="button" class="aura-navbar-item aura-tab-btn" data-tab="directory" data-category="donor" title="<?php esc_attr_e( 'Donantes y Aliados', 'aura-suite' ); ?>">
                    <span>🤝</span>
                    <span><?php esc_html_e( 'Donantes', 'aura-suite' ); ?></span>
                    <span class="aura-tab-count" id="tab-count-donor"><?php echo esc_html( $kpis['by_category']['donor'] ?? 0 ); ?></span>
                </button>

                <button type="button" class="aura-navbar-item aura-tab-btn" data-tab="directory" data-category="member" title="<?php esc_attr_e( 'Miembros y Afiliados', 'aura-suite' ); ?>">
                    <span>⭐</span>
                    <span><?php esc_html_e( 'Miembros', 'aura-suite' ); ?></span>
                    <span class="aura-tab-count" id="tab-count-member"><?php echo esc_html( $kpis['by_category']['member'] ?? 0 ); ?></span>
                </button>

                <button type="button" class="aura-navbar-item aura-tab-btn" data-tab="import_export" title="<?php esc_attr_e( 'Importar y Exportar Contactos', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-database-export"></span>
                    <span><?php esc_html_e( 'Migración', 'aura-suite' ); ?></span>
                </button>

                <button type="button" class="aura-navbar-item aura-tab-btn" data-tab="duplicates_merge" title="<?php esc_attr_e( 'Detección de Duplicados y Fusión', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-randomize"></span>
                    <span><?php esc_html_e( 'Duplicados', 'aura-suite' ); ?></span>
                </button>

                <button type="button" class="aura-navbar-item aura-tab-btn" data-tab="sync_logs" title="<?php esc_attr_e( 'Historial de Sincronización', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-update"></span>
                    <span><?php esc_html_e( 'Historial', 'aura-suite' ); ?></span>
                </button>

                <?php if ( $can_google ) : ?>
                    <button type="button" class="aura-navbar-item aura-tab-btn" data-tab="google_settings" title="<?php esc_attr_e( 'Ajustes de Google & Workspace', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-google"></span>
                        <span><?php esc_html_e( 'Google Sync', 'aura-suite' ); ?></span>
                    </button>
                <?php endif; ?>
            </nav>

            <!-- ══════════════════════════════════════════════════════════════
                 SECCIÓN 1: VISTA DE DIRECTORIO (TAB: DIRECTORY)
            ═══════════════════════════════════════════════════════════════ -->
            <section id="contacts-tab-directory" class="contacts-tab-content is-active" style="margin-top: 15px;">
                <!-- Barra de Búsqueda y Filtros -->
                <div class="aura-glass-card contacts-filter-bar fade-up delay-2" style="padding: 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 12px; flex: 1 1 320px; min-width: 250px;">
                        <div class="contacts-search-input-wrap" style="position: relative; width: 100%;">
                            <span class="dashicons dashicons-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--aura-text-muted, #94a3b8);"></span>
                            <input type="search" id="contacts-search-input" class="aura-input" placeholder="<?php esc_attr_e( 'Buscar por nombre, email, teléfono, organización o tags...', 'aura-suite' ); ?>" style="padding-left: 38px; width: 100%;">
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <!-- Filtro Estado Sync -->
                        <select id="contacts-filter-sync" class="aura-select" style="min-width: 170px;">
                            <option value=""><?php esc_html_e( 'Estado Google: Todos', 'aura-suite' ); ?></option>
                            <option value="synced"><?php esc_html_e( '🟢 Sincronizados', 'aura-suite' ); ?></option>
                            <option value="pending"><?php esc_html_e( '🟡 Pendientes', 'aura-suite' ); ?></option>
                            <option value="not_synced"><?php esc_html_e( '⚪ No en Google', 'aura-suite' ); ?></option>
                            <option value="error"><?php esc_html_e( '🔴 Con Error', 'aura-suite' ); ?></option>
                        </select>

                        <!-- Filtro Categoría -->
                        <select id="contacts-filter-category" class="aura-select" style="min-width: 170px;">
                            <option value=""><?php esc_html_e( 'Categoría: Todas', 'aura-suite' ); ?></option>
                            <?php foreach ( $categories as $ckey => $clabel ) : ?>
                                <option value="<?php echo esc_attr( $ckey ); ?>">
                                    <?php echo esc_html( ( $category_icons[ $ckey ] ?? '🏷️' ) . ' ' . $clabel ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Selector por página -->
                        <select id="contacts-per-page" class="aura-select" style="width: 80px;">
                            <option value="15">15</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>

                        <?php if ( $can_edit ) : ?>
                            <button type="button" class="btn btn-secondary btn-sm" id="btn-contacts-bulk-edit" onclick="if(window.openBulkModal){window.openBulkModal();}else if(window.AuraContactsApp&&window.AuraContactsApp.openBulkEditModal){window.AuraContactsApp.openBulkEditModal();}" style="display: none; align-items: center; gap: 6px; font-weight: 600; background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3);" title="<?php esc_attr_e( 'Edición Masiva', 'aura-suite' ); ?>">
                                <span class="dashicons dashicons-edit"></span>
                                <span class="badge" id="contacts-bulk-count" style="background: #2563eb; color: #fff; border-radius: 10px; padding: 1px 7px; font-size: 0.75rem; font-weight: 700;">0</span>
                            </button>
                        <?php endif; ?>

                        <?php if ( $can_delete ) : ?>
                            <button type="button" class="btn btn-danger btn-sm" id="btn-contacts-bulk-delete" style="display: none; align-items: center; gap: 6px; font-weight: 600; background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.35);" title="<?php esc_attr_e( 'Borrar contactos seleccionados', 'aura-suite' ); ?>">
                                <span class="dashicons dashicons-trash"></span>
                                <span class="badge" id="contacts-bulk-delete-count" style="background: #dc2626; color: #fff; border-radius: 10px; padding: 1px 7px; font-size: 0.75rem; font-weight: 700;">0</span>
                            </button>
                        <?php endif; ?>

                        <button type="button" class="btn btn-glass btn-sm" id="btn-refresh-list" title="<?php esc_attr_e( 'Refrescar listado', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-image-rotate"></span>
                        </button>
                    </div>
                </div>

                <!-- Barra Flotante de Acciones Masivas (Aura Enterprise Bulk Toolbar) -->
                <div id="contacts-bulk-bar" style="display: none; align-items: center; justify-content: space-between; padding: 12px 20px; background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(59, 130, 246, 0.35); border-radius: 12px; margin-bottom: 14px; backdrop-filter: blur(12px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5); flex-wrap: wrap; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #2563eb; color: #fff; font-size: 13px; font-weight: 700;" id="contacts-bulk-badge-circle">0</span>
                        <span style="color: #f8fafc; font-weight: 600; font-size: 0.92rem;" id="contacts-bulk-bar-label">
                            <?php esc_html_e( 'contactos seleccionados', 'aura-suite' ); ?>
                        </span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <?php if ( $can_edit ) : ?>
                            <button type="button" class="btn btn-primary btn-sm btn-lift" id="btn-bulk-bar-edit" onclick="if(window.openBulkModal){window.openBulkModal();}else if(window.AuraContactsApp&&window.AuraContactsApp.openBulkEditModal){window.AuraContactsApp.openBulkEditModal();}" style="display: flex; align-items: center; gap: 6px;">
                                <span class="dashicons dashicons-edit"></span>
                                <span><?php esc_html_e( 'Editar Seleccionados', 'aura-suite' ); ?></span>
                            </button>
                        <?php endif; ?>
                        <?php if ( $can_sync && $is_connected ) : ?>
                            <button type="button" class="btn btn-glass btn-sm btn-lift" id="btn-bulk-bar-sync" style="display: flex; align-items: center; gap: 6px; color: #38bdf8; border-color: rgba(56, 189, 248, 0.3);">
                                <span class="dashicons dashicons-cloud"></span>
                                <span><?php esc_html_e( 'Re-sincronizar con Google', 'aura-suite' ); ?></span>
                            </button>
                        <?php endif; ?>
                        <?php if ( $can_delete ) : ?>
                            <button type="button" class="btn btn-danger btn-sm btn-lift" id="btn-bulk-bar-delete" style="display: flex; align-items: center; gap: 6px; background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.35);">
                                <span class="dashicons dashicons-trash"></span>
                                <span><?php esc_html_e( 'Borrar Seleccionados', 'aura-suite' ); ?></span>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-glass btn-sm" id="btn-bulk-bar-clear" style="color: #94a3b8;">
                            <span class="dashicons dashicons-no-alt"></span>
                            <span><?php esc_html_e( 'Deseleccionar', 'aura-suite' ); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Tabla de Contactos con Glassmorphism -->
                <div class="aura-glass-card contacts-table-card fade-up delay-3" style="overflow: hidden; padding: 0;">
                    <div class="contacts-table-responsive" style="overflow-x: auto;">
                        <table class="aura-table contacts-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">
                                        <input type="checkbox" id="contacts-select-all" class="aura-checkbox">
                                    </th>
                                    <th><?php esc_html_e( 'Contacto / Nombre', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Email / Teléfono', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Organización / Cargo', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Vinculación', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Estado Google', 'aura-suite' ); ?></th>
                                    <th style="text-align: right; min-width: 130px;"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                                </tr>
                            </thead>
                            <tbody id="contacts-tbody">
                                <!-- Filas renderizadas reactivamente por JavaScript -->
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">
                                        <div class="contacts-loading-spinner">
                                            <span class="dashicons dashicons-update spin"></span>
                                            <p><?php esc_html_e( 'Cargando directorio de contactos...', 'aura-suite' ); ?></p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pie de Tabla: Paginación y Contador -->
                    <div class="contacts-pagination-footer" style="padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--aura-border-color, rgba(255,255,255,0.08)); flex-wrap: wrap; gap: 12px;">
                        <div class="contacts-pagination-info" id="contacts-pagination-info">
                            <?php esc_html_e( 'Mostrando 0 contactos', 'aura-suite' ); ?>
                        </div>
                        <div class="contacts-pagination-controls" id="contacts-pagination-controls" style="display: flex; gap: 6px;">
                            <!-- Botones de paginación generados por JS -->
                        </div>
                    </div>
                </div>
            </section>

            <!-- ══════════════════════════════════════════════════════════════
                 SECCIÓN 2: HISTORIAL DE SINCRONIZACIÓN (TAB: SYNC LOGS)
            ═══════════════════════════════════════════════════════════════ -->
            <section id="contacts-tab-sync_logs" class="contacts-tab-content" style="display: none; margin-top: 15px;">
                <div class="aura-glass-card" style="padding: 24px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <h2 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: #fff;">
                                🔄 <?php esc_html_e( 'Historial y Registro de Auditoría de Sincronización', 'aura-suite' ); ?>
                            </h2>
                            <p style="margin: 4px 0 0; font-size: 0.85rem; color: var(--aura-text-muted, #94a3b8);">
                                <?php esc_html_e( 'Monitoreo de intercambios bidireccionales con la API de Google People, resolución de conflictos y cron en background.', 'aura-suite' ); ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn btn-glass btn-sm" id="btn-refresh-logs">
                                <span class="dashicons dashicons-image-rotate"></span>
                                <span><?php esc_html_e( 'Actualizar Logs', 'aura-suite' ); ?></span>
                            </button>
                            <?php if ( $can_sync ) : ?>
                                <?php if ( $is_connected ) : ?>
                                    <button type="button" class="btn btn-primary btn-sm" id="btn-sync-all-from-logs" title="<?php esc_attr_e( 'Forzar sincronización completa bidireccional con Google People API', 'aura-suite' ); ?>">
                                        <span class="dashicons dashicons-update"></span>
                                        <span><?php esc_html_e( 'Sincronizar Todo Ahora', 'aura-suite' ); ?></span>
                                    </button>
                                <?php else : ?>
                                    <button type="button" class="btn btn-glass btn-sm" onclick="$('.aura-navbar-item[data-tab=\'google_settings\']').click();" style="border: 1px dashed rgba(245, 158, 11, 0.45); color: #fbbf24;" title="<?php esc_attr_e( 'Google Contacts no está conectado. Haz clic para configurar tu cuenta.', 'aura-suite' ); ?>">
                                        <span class="dashicons dashicons-google" style="color: #f59e0b;"></span>
                                        <span><?php esc_html_e( 'Conectar Google Sync', 'aura-suite' ); ?></span>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── PANEL DE INTEGRACIÓN INTERNA DEL ECOSISTEMA (CORE WP & TERCEROS — FASE 4 P2) ── -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-bottom: 24px;">
                        
                        <!-- Tarjeta Sincronización Usuarios WP -->
                        <div class="aura-glass-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 18px; border-radius: 12px; display: flex; flex-direction: column; justify-content: space-between; gap: 14px;">
                            <div style="display: flex; align-items: flex-start; gap: 14px;">
                                <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(56, 189, 248, 0.15); color: #38bdf8; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                                    <span class="dashicons dashicons-admin-users" style="font-size: 24px; width: 24px; height: 24px;"></span>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="margin: 0; font-size: 1rem; color: #fff; font-weight: 700;">
                                        <?php esc_html_e( 'Usuarios de WordPress (Sistema)', 'aura-suite' ); ?>
                                    </h4>
                                    <p style="margin: 4px 0 0; font-size: 0.82rem; color: var(--aura-text-muted, #94a3b8); line-height: 1.4;">
                                        <?php esc_html_e( 'Sincroniza y mantiene enlazados en tiempo real los empleados, docentes y auxiliares de WordPress con el directorio de contactos.', 'aura-suite' ); ?>
                                    </p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 12px; flex-wrap: wrap; gap: 10px;">
                                <span style="font-size: 0.8rem; color: #38bdf8; font-weight: 600;">
                                    ⚡ <?php esc_html_e( 'Hooks automáticos: user_register & profile_update', 'aura-suite' ); ?>
                                </span>
                                <?php if ( $can_edit ) : ?>
                                    <button type="button" class="btn btn-secondary btn-sm" id="btn-sync-wp-users">
                                        <span class="dashicons dashicons-update"></span>
                                        <span><?php esc_html_e( 'Sincronizar Usuarios WP', 'aura-suite' ); ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tarjeta Sincronización Terceros -->
                        <div class="aura-glass-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 18px; border-radius: 12px; display: flex; flex-direction: column; justify-content: space-between; gap: 14px;">
                            <div style="display: flex; align-items: flex-start; gap: 14px;">
                                <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                                    <span class="dashicons dashicons-groups" style="font-size: 24px; width: 24px; height: 24px;"></span>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="margin: 0; font-size: 1rem; color: #fff; font-weight: 700;">
                                        <?php esc_html_e( 'Terceros y Entidades Comerciales', 'aura-suite' ); ?>
                                    </h4>
                                    <p style="margin: 4px 0 0; font-size: 0.82rem; color: var(--aura-text-muted, #94a3b8); line-height: 1.4;">
                                        <?php esc_html_e( 'Espejo bidireccional con proveedores, donantes y contratistas de Finanzas para que aparezcan en contactos y Google.', 'aura-suite' ); ?>
                                    </p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 12px; flex-wrap: wrap; gap: 10px;">
                                <span style="font-size: 0.8rem; color: #f59e0b; font-weight: 600;">
                                    ⚡ <?php esc_html_e( 'Hook activo: aura_third_party_saved', 'aura-suite' ); ?>
                                </span>
                                <?php if ( $can_edit ) : ?>
                                    <button type="button" class="btn btn-secondary btn-sm" id="btn-sync-third-parties">
                                        <span class="dashicons dashicons-update"></span>
                                        <span><?php esc_html_e( 'Sincronizar Terceros', 'aura-suite' ); ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tarjeta Sincronización Estudiantes & Formularios (Fase 4 P3) -->
                        <div class="aura-glass-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 18px; border-radius: 12px; display: flex; flex-direction: column; justify-content: space-between; gap: 14px;">
                            <div style="display: flex; align-items: flex-start; gap: 14px;">
                                <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                                    <span class="dashicons dashicons-welcome-learn-more" style="font-size: 24px; width: 24px; height: 24px;"></span>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="margin: 0; font-size: 1rem; color: #fff; font-weight: 700;">
                                        <?php esc_html_e( 'Estudiantes y Formularios Web', 'aura-suite' ); ?>
                                    </h4>
                                    <p style="margin: 4px 0 0; font-size: 0.82rem; color: var(--aura-text-muted, #94a3b8); line-height: 1.4;">
                                        <?php esc_html_e( 'Espejo con alumnos matriculados y captura de nuevos prospectos o inscripciones web para difusión y Gmail.', 'aura-suite' ); ?>
                                    </p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 12px; flex-wrap: wrap; gap: 10px;">
                                <span style="font-size: 0.8rem; color: #10b981; font-weight: 600;">
                                    ⚡ <?php esc_html_e( 'Hooks: aura_student_saved & aura_form_submission_saved', 'aura-suite' ); ?>
                                </span>
                                <?php if ( $can_edit ) : ?>
                                    <button type="button" class="btn btn-secondary btn-sm" id="btn-sync-students">
                                        <span class="dashicons dashicons-update"></span>
                                        <span><?php esc_html_e( 'Sincronizar Estudiantes', 'aura-suite' ); ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <div class="contacts-table-responsive">
                        <table class="aura-table logs-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Fecha / Hora', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Dirección', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Acción', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Contacto ID', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                                    <th><?php esc_html_e( 'Detalle / Error', 'aura-suite' ); ?></th>
                                </tr>
                            </thead>
                            <tbody id="contacts-logs-tbody">
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 30px;">
                                        <?php esc_html_e( 'Cargando logs de sincronización...', 'aura-suite' ); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ══════════════════════════════════════════════════════════════
                 SECCIÓN 3: CONFIGURACIÓN GOOGLE & WORKSPACE (TAB: GOOGLE SETTINGS)
            ═══════════════════════════════════════════════════════════════ -->
            <?php if ( $can_google ) : ?>
            <section id="contacts-tab-google_settings" class="contacts-tab-content" style="display: none; margin-top: 15px;">
                <div class="aura-glass-card" style="padding: 26px; max-width: 960px; margin: 0 auto;">
                    
                    <!-- Header de Configuración -->
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 18px; margin-bottom: 20px; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-google"></span>
                                <?php esc_html_e( 'Sincronización con Google Cloud & Workspace', 'aura-suite' ); ?>
                            </h2>
                            <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--aura-text-muted, #94a3b8);">
                                <?php esc_html_e( 'Sincroniza contactos con Google People API usando una cuenta personal/oficial vía OAuth 2.0 o toda la institución vía Cuenta de Servicio con Delegación de Dominio (DWD).', 'aura-suite' ); ?>
                            </p>
                        </div>

                        <!-- Selector de Modo Activo -->
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <label for="google_auth_mode_selector" style="font-size: 0.85rem; color: #94a3b8; font-weight: 600;">
                                <?php esc_html_e( 'Modo de Conexión:', 'aura-suite' ); ?>
                            </label>
                            <select id="google_auth_mode_selector" class="aura-select" style="min-width: 220px; padding: 6px 12px; font-size: 0.85rem;">
                                <option value="oauth_user" <?php selected( $active_auth_mode, 'oauth_user' ); ?>>
                                    👤 <?php esc_html_e( 'OAuth 2.0 de Usuario (Gmail)', 'aura-suite' ); ?>
                                </option>
                                <option value="service_account" <?php selected( $active_auth_mode, 'service_account' ); ?>>
                                    🏢 <?php esc_html_e( 'Service Account DWD (Workspace)', 'aura-suite' ); ?>
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- SUB-PANEL 1: OAUTH 2.0 USUARIO -->
                    <div id="auth-panel-oauth_user" class="google-auth-subpanel" style="<?php echo $active_auth_mode === 'service_account' ? 'display: none;' : ''; ?>">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 600; color: #38bdf8;">
                                    👤 <?php esc_html_e( 'Autenticación Individual OAuth 2.0', 'aura-suite' ); ?>
                                </h3>
                                <button type="button" class="btn btn-glass btn-sm" id="btn-open-oauth-tutorial" style="border: 1px solid rgba(56, 189, 248, 0.4); color: #38bdf8; font-weight: 600; font-size: 0.8rem; padding: 4px 10px;">
                                    <span class="dashicons dashicons-welcome-learn-more"></span>
                                    <span><?php esc_html_e( 'Tutorial Paso a Paso', 'aura-suite' ); ?></span>
                                </button>
                            </div>
                            <div>
                                <?php if ( $is_connected ) : ?>
                                    <span class="badge badge-emerald" style="padding: 5px 12px; font-size: 0.82rem;">
                                        <span class="pulse-dot dot-emerald"></span>
                                        <span><?php printf( esc_html__( 'Conectado: %s', 'aura-suite' ), esc_html( $account_email ) ); ?></span>
                                    </span>
                                <?php else : ?>
                                    <span class="badge badge-amber" style="padding: 5px 12px; font-size: 0.82rem;">
                                        <span class="pulse-dot dot-amber"></span>
                                        <span><?php esc_html_e( 'Desconectado', 'aura-suite' ); ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ( $is_connected ) : ?>
                            <div class="aura-glass-card" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); padding: 16px; border-radius: 10px; margin-bottom: 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                    <div>
                                        <h4 style="margin: 0; color: #10b981; font-weight: 700; font-size: 0.95rem;">
                                            ✅ <?php esc_html_e( 'Google People API conectada vía OAuth 2.0', 'aura-suite' ); ?>
                                        </h4>
                                        <p style="margin: 4px 0 0; font-size: 0.82rem; color: rgba(255,255,255,0.85);">
                                            <?php printf( esc_html__( 'Cuenta autorizada: %s', 'aura-suite' ), '<strong>' . esc_html( $account_email ) . '</strong>' ); ?>
                                        </p>
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <button type="button" class="btn btn-secondary btn-sm" id="btn-reconnect-google" onclick="if(confirm('¿Deseas re-autorizar los permisos en Google?')) window.location.href='<?php echo esc_url( $auth_url ); ?>';">
                                            <span class="dashicons dashicons-update"></span>
                                            <span><?php esc_html_e( 'Renovar Autorización', 'aura-suite' ); ?></span>
                                        </button>
                                        <button type="button" class="btn btn-rose btn-sm" id="btn-disconnect-google">
                                            <span class="dashicons dashicons-dismiss"></span>
                                            <span><?php esc_html_e( 'Desconectar', 'aura-suite' ); ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form id="contacts-google-form" style="display: flex; flex-direction: column; gap: 16px;">
                            <div class="aura-form-group">
                                <label for="google_client_id" class="aura-form-label" style="font-weight: 600; color: #fff;">
                                    <?php esc_html_e( 'Client ID de Google Cloud', 'aura-suite' ); ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="text" id="google_client_id" name="client_id" class="aura-input" value="<?php echo esc_attr( $oauth_provider->get_client_id() ); ?>" placeholder="123456789-xxxxxxxx.apps.googleusercontent.com" required style="width: 100%;">
                                <span class="aura-form-help" style="font-size: 0.8rem; color: var(--aura-text-muted, #94a3b8); margin-top: 4px; display: block;">
                                    <?php esc_html_e( 'Obtenido en Google Cloud Console > APIs & Services > Credentials > OAuth 2.0 Client IDs (Tipo: Web Application).', 'aura-suite' ); ?>
                                </span>
                            </div>

                            <div class="aura-form-group">
                                <label for="google_client_secret" class="aura-form-label" style="font-weight: 600; color: #fff;">
                                    <?php esc_html_e( 'Client Secret de Google Cloud', 'aura-suite' ); ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="password" id="google_client_secret" name="client_secret" class="aura-input" value="<?php echo ! empty( $oauth_provider->get_client_secret() ) ? '••••••••••••••••' : ''; ?>" placeholder="<?php esc_attr_e( 'Introduce el secreto del cliente OAuth', 'aura-suite' ); ?>" required style="width: 100%;">
                                <span class="aura-form-help" style="font-size: 0.8rem; color: var(--aura-text-muted, #94a3b8); margin-top: 4px; display: block;">
                                    <?php esc_html_e( 'Este valor se almacena cifrado en la base de datos mediante AES-256-CBC con clave derivada.', 'aura-suite' ); ?>
                                </span>
                            </div>

                            <div class="aura-form-group">
                                <label class="aura-form-label" style="font-weight: 600; color: #fff;">
                                    <?php esc_html_e( 'URI de Redirección Autorizada (Redirect URI)', 'aura-suite' ); ?>
                                </label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="text" id="google_redirect_uri" class="aura-input" value="<?php echo esc_url( $redirect_uri ); ?>" readonly style="background: rgba(0,0,0,0.25); color: #cbd5e1; width: 100%;">
                                    <button type="button" class="btn btn-glass btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('google_redirect_uri').value); alert('URI copiada al portapapeles');" title="<?php esc_attr_e( 'Copiar al portapapeles', 'aura-suite' ); ?>">
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                </div>
                                <span class="aura-form-help" style="font-size: 0.8rem; color: #38bdf8; margin-top: 4px; display: block;">
                                    ⚠️ <?php esc_html_e( 'Debes agregar exactamente esta URI en "Authorized redirect URIs" en tu proyecto de Google Cloud Console.', 'aura-suite' ); ?>
                                </span>
                            </div>

                            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 8px; flex-wrap: wrap; gap: 12px;">
                                <button type="submit" class="btn btn-primary btn-lift" id="btn-save-google-credentials">
                                    <span class="dashicons dashicons-saved"></span>
                                    <span><?php esc_html_e( 'Guardar Credenciales OAuth', 'aura-suite' ); ?></span>
                                </button>

                                <?php if ( $is_configured && ! $is_connected && ! empty( $auth_url ) ) : ?>
                                    <a href="<?php echo esc_url( $auth_url ); ?>" class="btn btn-emerald btn-shimmer btn-lift" style="display: inline-flex; align-items: center; gap: 8px;">
                                        <span class="dashicons dashicons-external"></span>
                                        <span><strong><?php esc_html_e( 'Autorizar y Conectar con Google', 'aura-suite' ); ?></strong></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- SUB-PANEL 2: SERVICE ACCOUNT DWD (GOOGLE WORKSPACE) -->
                    <div id="auth-panel-service_account" class="google-auth-subpanel" style="<?php echo $active_auth_mode === 'oauth_user' ? 'display: none;' : ''; ?>">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <h3 style="margin: 0; font-size: 1.05rem; font-weight: 600; color: #a78bfa;">
                                🏢 <?php esc_html_e( 'Google Workspace Domain-Wide Delegation (DWD)', 'aura-suite' ); ?>
                            </h3>
                            <div>
                                <?php if ( $sa_is_connected ) : ?>
                                    <span class="badge badge-emerald" style="padding: 5px 12px; font-size: 0.82rem;">
                                        <span class="pulse-dot dot-emerald"></span>
                                        <span><?php printf( esc_html__( 'DWD Conectado (%s)', 'aura-suite' ), esc_html( $sa_delegated ) ); ?></span>
                                    </span>
                                <?php else : ?>
                                    <span class="badge badge-amber" style="padding: 5px 12px; font-size: 0.82rem;">
                                        <span class="pulse-dot dot-amber"></span>
                                        <span><?php esc_html_e( 'DWD No Configurado', 'aura-suite' ); ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="aura-glass-card" style="background: rgba(167, 139, 250, 0.06); border: 1px solid rgba(167, 139, 250, 0.25); padding: 14px; border-radius: 10px; margin-bottom: 18px;">
                            <p style="margin: 0; font-size: 0.85rem; color: #e2e8f0; line-height: 1.5;">
                                💡 <strong><?php esc_html_e( '¿Cómo funciona DWD?', 'aura-suite' ); ?></strong>
                                <?php esc_html_e( 'Permite sincronizar contactos en segundo plano sin intervención manual del usuario, actuando en nombre del buzón institucional del dominio Workspace.', 'aura-suite' ); ?>
                                <br>
                                <?php esc_html_e( 'Scope requerido en la consola de Admin Workspace:', 'aura-suite' ); ?>
                                <code style="color: #38bdf8; background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;">https://www.googleapis.com/auth/contacts</code>
                            </p>
                        </div>

                        <form id="contacts-sa-form" style="display: flex; flex-direction: column; gap: 16px;">
                            <div class="aura-form-group">
                                <label for="sa_json_credentials" class="aura-form-label" style="font-weight: 600; color: #fff;">
                                    <?php esc_html_e( 'JSON de la Cuenta de Servicio (Clave Privada)', 'aura-suite' ); ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <textarea id="sa_json_credentials" name="sa_json" class="aura-textarea" rows="5" placeholder='{"type": "service_account", "project_id": "...", "private_key_id": "...", "private_key": "...", "client_email": "...", ...}' style="font-family: monospace; font-size: 0.82rem; width: 100%;"><?php echo $sa_is_configured ? '{"client_email": "' . esc_attr( $sa_email ) . '", "status": "••••• Llave privada cifrada guardada •••••"}' : ''; ?></textarea>
                                <span class="aura-form-help" style="font-size: 0.8rem; color: var(--aura-text-muted, #94a3b8); margin-top: 4px; display: block;">
                                    <?php esc_html_e( 'Descarga el archivo JSON de clave privada desde Google Cloud Console > IAM & Admin > Service Accounts.', 'aura-suite' ); ?>
                                </span>
                            </div>

                            <div class="aura-form-group">
                                <label for="sa_delegated_user" class="aura-form-label" style="font-weight: 600; color: #fff;">
                                    <?php esc_html_e( 'Usuario Delegado del Dominio (Google Workspace Sub)', 'aura-suite' ); ?> <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="email" id="sa_delegated_user" name="delegated_user" class="aura-input" value="<?php echo esc_attr( $sa_delegated ); ?>" placeholder="director@tudominio.org" style="width: 100%;">
                                <span class="aura-form-help" style="font-size: 0.8rem; color: var(--aura-text-muted, #94a3b8); margin-top: 4px; display: block;">
                                    <?php esc_html_e( 'Correo electrónico del usuario de Google Workspace cuyos contactos se van a sincronizar institucionalmente.', 'aura-suite' ); ?>
                                </span>
                            </div>

                            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 8px; flex-wrap: wrap; gap: 12px;">
                                <button type="submit" class="btn btn-primary btn-lift" id="btn-save-sa-credentials">
                                    <span class="dashicons dashicons-saved"></span>
                                    <span><?php esc_html_e( 'Guardar Credenciales DWD', 'aura-suite' ); ?></span>
                                </button>

                                <button type="button" class="btn btn-secondary btn-lift" id="btn-test-sa-connection">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    <span><?php esc_html_e( 'Probar Conexión en Vivo', 'aura-suite' ); ?></span>
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </section>
            <?php endif; ?>

            <!-- ══════════════════════════════════════════════════════════════
                 SECCIÓN 4: IMPORTAR Y EXPORTAR (TAB: IMPORT_EXPORT)
            ═══════════════════════════════════════════════════════════════ -->
            <section id="contacts-tab-import_export" class="contacts-tab-content" style="display: none; margin-top: 15px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 24px; max-width: 1200px; margin: 0 auto;">

                    <!-- CARD: EXPORTACIÓN -->
                    <div class="aura-glass-card" style="padding: 24px; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(56, 189, 248, 0.15); display: flex; align-items: center; justify-content: center; color: #38bdf8;">
                                    <span class="dashicons dashicons-download" style="font-size: 22px; width: 22px; height: 22px;"></span>
                                </div>
                                <div>
                                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: #fff;">
                                        <?php esc_html_e( 'Exportar Directorio', 'aura-suite' ); ?>
                                    </h3>
                                    <p style="margin: 2px 0 0; font-size: 0.82rem; color: var(--aura-text-muted, #94a3b8);">
                                        <?php esc_html_e( 'Descarga tus contactos en formatos estándares compatibles con cualquier gestor.', 'aura-suite' ); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="aura-form-group" style="margin-bottom: 16px;">
                                <label for="export_filter_category" class="aura-form-label" style="font-weight: 600; color: #fff;">
                                    <?php esc_html_e( 'Filtrar por Categoría a Exportar:', 'aura-suite' ); ?>
                                </label>
                                <select id="export_filter_category" class="aura-select" style="width: 100%;">
                                    <option value=""><?php esc_html_e( '⭐ Todos los Contactos (Directorio Completo)', 'aura-suite' ); ?></option>
                                    <?php foreach ( $categories as $ckey => $clabel ) : ?>
                                        <option value="<?php echo esc_attr( $ckey ); ?>">
                                            <?php echo esc_html( ( $category_icons[ $ckey ] ?? '🏷️' ) . ' ' . $clabel ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                                <ul style="margin: 0; padding-left: 18px; font-size: 0.82rem; color: #cbd5e1; display: flex; flex-direction: column; gap: 6px;">
                                    <li><strong>CSV Universal:</strong> Compatible con Excel, Google Sheets, Google Contacts (BOM UTF-8).</li>
                                    <li><strong>vCard 3.0 (.vcf):</strong> Formato estándar internacional para teléfonos móviles (iOS / Android) y Microsoft Outlook.</li>
                                </ul>
                            </div>
                        </div>

                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <button type="button" class="btn btn-primary btn-lift" id="btn-export-csv" style="flex: 1; justify-content: center;">
                                <span class="dashicons dashicons-media-spreadsheet"></span>
                                <span><?php esc_html_e( 'Descargar CSV', 'aura-suite' ); ?></span>
                            </button>
                            <button type="button" class="btn btn-secondary btn-lift" id="btn-export-vcf" style="flex: 1; justify-content: center;">
                                <span class="dashicons dashicons-id"></span>
                                <span><?php esc_html_e( 'Descargar vCard (.vcf)', 'aura-suite' ); ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- CARD: IMPORTACIÓN -->
                    <div class="aura-glass-card" style="padding: 24px; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(52, 211, 153, 0.15); display: flex; align-items: center; justify-content: center; color: #34d399;">
                                    <span class="dashicons dashicons-upload" style="font-size: 22px; width: 22px; height: 22px;"></span>
                                </div>
                                <div>
                                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: #fff;">
                                        <?php esc_html_e( 'Importar Contactos', 'aura-suite' ); ?>
                                    </h3>
                                    <p style="margin: 2px 0 0; font-size: 0.82rem; color: var(--aura-text-muted, #94a3b8);">
                                        <?php esc_html_e( 'Sube archivos CSV o vCard (.vcf) para cargar contactos de forma masiva.', 'aura-suite' ); ?>
                                    </p>
                                </div>
                            </div>

                            <form id="form-contacts-import" enctype="multipart/form-data">
                                <div class="aura-form-group" style="margin-bottom: 14px;">
                                    <label for="import_file_input" class="aura-form-label" style="font-weight: 600; color: #fff;">
                                        <?php esc_html_e( 'Seleccionar Archivo (.csv o .vcf):', 'aura-suite' ); ?> <span style="color: #ef4444;">*</span>
                                    </label>
                                    <input type="file" id="import_file_input" name="import_file" accept=".csv,.vcf" class="aura-input" required style="width: 100%; padding: 7px;">
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                                    <div class="aura-form-group">
                                        <label for="import_duplicate_strategy" class="aura-form-label" style="font-size: 0.82rem; font-weight: 600; color: #fff;">
                                            <?php esc_html_e( 'Si ya existe:', 'aura-suite' ); ?>
                                        </label>
                                        <select id="import_duplicate_strategy" name="duplicate_strategy" class="aura-select" style="width: 100%; font-size: 0.85rem;">
                                            <option value="skip"><?php esc_html_e( 'Omitir (No tocar)', 'aura-suite' ); ?></option>
                                            <option value="update"><?php esc_html_e( 'Actualizar datos', 'aura-suite' ); ?></option>
                                            <option value="create"><?php esc_html_e( 'Crear duplicado', 'aura-suite' ); ?></option>
                                        </select>
                                    </div>
                                    <div class="aura-form-group">
                                        <label for="import_default_category" class="aura-form-label" style="font-size: 0.82rem; font-weight: 600; color: #fff;">
                                            <?php esc_html_e( 'Categoría defecto:', 'aura-suite' ); ?>
                                        </label>
                                        <select id="import_default_category" name="default_category" class="aura-select" style="width: 100%; font-size: 0.85rem;">
                                            <?php foreach ( $categories as $ckey => $clabel ) : ?>
                                                <option value="<?php echo esc_attr( $ckey ); ?>">
                                                    <?php echo esc_html( ( $category_icons[ $ckey ] ?? '🏷️' ) . ' ' . $clabel ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-emerald btn-lift" id="btn-submit-import" style="width: 100%; justify-content: center;">
                                    <span class="dashicons dashicons-upload"></span>
                                    <span><?php esc_html_e( 'Iniciar Importación Masiva', 'aura-suite' ); ?></span>
                                </button>
                            </form>
                        </div>

                        <!-- Resumen de Resultados de Importación -->
                        <div id="import-results-card" style="display: none; margin-top: 16px; padding: 18px; border-radius: 12px; background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(255,255,255,0.12); box-shadow: 0 8px 24px rgba(0,0,0,0.3);">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                                <h4 style="margin: 0; font-size: 0.95rem; color: #38bdf8; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                                    📊 <?php esc_html_e( 'Resultado de la Importación Masiva', 'aura-suite' ); ?>
                                </h4>
                                <span id="res-badge-status" style="font-size: 0.75rem; padding: 3px 8px; border-radius: 6px; background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 600;">
                                    Proceso Finalizado
                                </span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(70px, 1fr)); gap: 10px; text-align: center; margin-bottom: 12px;">
                                <div style="background: rgba(255,255,255,0.03); padding: 8px 4px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06);">
                                    <div id="res-total" style="font-weight: 800; font-size: 1.25rem; color: #fff;">0</div>
                                    <div style="font-size: 0.72rem; color: #94a3b8; text-transform: uppercase;">Total</div>
                                </div>
                                <div style="background: rgba(16, 185, 129, 0.06); padding: 8px 4px; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2);">
                                    <div id="res-created" style="font-weight: 800; font-size: 1.25rem; color: #34d399;">0</div>
                                    <div style="font-size: 0.72rem; color: #34d399; text-transform: uppercase;">Creados</div>
                                </div>
                                <div style="background: rgba(56, 189, 248, 0.06); padding: 8px 4px; border-radius: 8px; border: 1px solid rgba(56, 189, 248, 0.2);">
                                    <div id="res-updated" style="font-weight: 800; font-size: 1.25rem; color: #38bdf8;">0</div>
                                    <div style="font-size: 0.72rem; color: #38bdf8; text-transform: uppercase;">Actualizados</div>
                                </div>
                                <div style="background: rgba(245, 158, 11, 0.06); padding: 8px 4px; border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.2);">
                                    <div id="res-skipped" style="font-weight: 800; font-size: 1.25rem; color: #f59e0b;">0</div>
                                    <div style="font-size: 0.72rem; color: #f59e0b; text-transform: uppercase;">Omitidos</div>
                                </div>
                                <div style="background: rgba(239, 68, 68, 0.06); padding: 8px 4px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.2);">
                                    <div id="res-errors" style="font-weight: 800; font-size: 1.25rem; color: #ef4444;">0</div>
                                    <div style="font-size: 0.72rem; color: #ef4444; text-transform: uppercase;">Errores</div>
                                </div>
                            </div>
                            <div id="res-message" style="font-size: 0.82rem; color: #cbd5e1; margin-bottom: 8px; line-height: 1.4;"></div>
                            <div id="res-details-wrapper" style="display: none; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 10px; margin-top: 10px;">
                                <div style="font-size: 0.75rem; font-weight: 700; color: #f59e0b; margin-bottom: 6px;">Observaciones y Detalles:</div>
                                <ul id="res-details-list" style="margin: 0; padding-left: 18px; font-size: 0.75rem; color: #94a3b8; max-height: 120px; overflow-y: auto;"></ul>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <!-- ══════════════════════════════════════════════════════════════
                 SECCIÓN 5: DUPLICADOS Y FUSIÓN (TAB: DUPLICATES_MERGE)
            ═══════════════════════════════════════════════════════════════ -->
            <section id="contacts-tab-duplicates_merge" class="contacts-tab-content" style="display: none; margin-top: 15px;">
                <div class="aura-glass-card" style="padding: 26px; max-width: 1100px; margin: 0 auto;">
                    
                    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 18px; margin-bottom: 20px; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-randomize"></span>
                                <?php esc_html_e( 'Detección y Fusión de Contactos Duplicados', 'aura-suite' ); ?>
                            </h2>
                            <p style="margin: 6px 0 0; font-size: 0.88rem; color: var(--aura-text-muted, #94a3b8);">
                                <?php esc_html_e( 'Detecta registros repetidos por coincidencia exacta de correo electrónico, teléfono o similitud de nombres, y fúsionalos preservando relaciones con terceros y estudiantes.', 'aura-suite' ); ?>
                            </p>
                        </div>
                        <button type="button" class="btn btn-primary btn-lift" id="btn-scan-duplicates">
                            <span class="dashicons dashicons-search"></span>
                            <span><?php esc_html_e( 'Escanear Duplicados Ahora', 'aura-suite' ); ?></span>
                        </button>
                    </div>

                    <!-- Contenedor Dinámico de Clusters -->
                    <div id="duplicates-container">
                        <div style="text-align: center; padding: 40px 20px; color: var(--aura-text-muted, #94a3b8);">
                            <span class="dashicons dashicons-randomize" style="font-size: 40px; width: 40px; height: 40px; margin-bottom: 12px; opacity: 0.4;"></span>
                            <p style="margin: 0; font-size: 0.95rem;">
                                <?php esc_html_e( 'Haz clic en "Escanear Duplicados Ahora" para inspeccionar la base de datos.', 'aura-suite' ); ?>
                            </p>
                        </div>
                    </div>

                </div>
            </section>

        </main>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL: CREAR / EDITAR CONTACTO
═══════════════════════════════════════════════════════════════════ -->
<!-- ══════════════════════════════════════════════════════════════════
     MODAL: CREAR / EDITAR CONTACTO (UX/UI PROFESIONAL Y ELEGANTE)
═══════════════════════════════════════════════════════════════════ -->
<div id="modal-contact-form" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal aura-glass-card contact-modal-dialog">
        <!-- Header del Modal -->
        <div class="contact-modal-header">
            <div class="contact-modal-title-wrap">
                <div class="contact-modal-icon-badge" id="contact-modal-icon-badge">
                    <span class="dashicons dashicons-id-alt"></span>
                </div>
                <div class="contact-modal-title-text">
                    <h3 id="contact-modal-title">
                        <?php esc_html_e( 'Nuevo Contacto', 'aura-suite' ); ?>
                    </h3>
                    <p id="contact-modal-subtitle">
                        <?php esc_html_e( 'Directorio institucional integrado con Google Contacts y el ecosistema Aura Suite.', 'aura-suite' ); ?>
                    </p>
                </div>
            </div>
            <button type="button" class="aura-modal__close" onclick="closeContactModal();" title="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>

        <form id="contact-edit-form">
            <input type="hidden" id="contact-form-id" name="id" value="0">
            <input type="hidden" id="contact-avatar-url" name="avatar_url" value="">
            <input type="hidden" id="contact-type-input" name="contact_type" value="person">
            <input type="hidden" id="contact-third-party-id" name="third_party_id" value="0">
            <input type="hidden" id="contact-wp-user-id" name="wp_user_id" value="0">

            <!-- Mini-Tabs del Modal -->
            <div class="contact-modal-tabs">
                <button type="button" class="contact-modal-tab-btn is-active" data-tab-target="#cmodal-tab-general">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e( 'Datos Principales', 'aura-suite' ); ?>
                </button>
                <button type="button" class="contact-modal-tab-btn" data-tab-target="#cmodal-tab-comms">
                    <span class="dashicons dashicons-phone"></span>
                    <?php esc_html_e( 'Comunicación y Dirección', 'aura-suite' ); ?>
                </button>
                <button type="button" class="contact-modal-tab-btn" data-tab-target="#cmodal-tab-relations">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e( 'Vinculaciones y Notas', 'aura-suite' ); ?>
                </button>
            </div>

            <!-- Cuerpo del Modal -->
            <div class="contact-modal-body">

                <!-- ════ PESTAÑA 1: DATOS PRINCIPALES ════ -->
                <div id="cmodal-tab-general" class="contact-modal-tab-pane">
                    
                    <!-- Selector de Tipo de Contacto (Segmented Pills) -->
                    <div style="margin-bottom: 16px;">
                        <label class="aura-form-label" style="margin-bottom: 6px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
                            <span><?php esc_html_e( 'Tipo de Entidad / Registro', 'aura-suite' ); ?></span>
                            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: normal;"><?php esc_html_e( 'Define la estructura del contacto', 'aura-suite' ); ?></span>
                        </label>
                        <div class="contact-type-selector">
                            <button type="button" class="contact-type-btn is-active" data-type="person">
                                <span>👤</span>
                                <span><?php esc_html_e( 'Persona Individual', 'aura-suite' ); ?></span>
                            </button>
                            <button type="button" class="contact-type-btn" data-type="company">
                                <span>🏢</span>
                                <span><?php esc_html_e( 'Empresa / Negocio', 'aura-suite' ); ?></span>
                            </button>
                            <button type="button" class="contact-type-btn" data-type="organization">
                                <span>🏛️</span>
                                <span><?php esc_html_e( 'Organización / ONG', 'aura-suite' ); ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- Hero de Identidad & Avatar Preview -->
                    <div class="contact-identity-hero" style="margin-bottom: 18px;">
                        <div class="contact-avatar-preview-box">
                            <img id="contact-avatar-preview-img" src="" alt="Avatar" class="contact-avatar-preview-img" style="display: none;">
                            <div id="contact-avatar-preview-initials" class="contact-avatar-preview-initials">AU</div>
                        </div>
                        <div class="contact-avatar-actions" style="flex: 1;">
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-glass btn-xs" id="btn-upload-contact-avatar">
                                    <span class="dashicons dashicons-camera" style="font-size:14px;width:14px;height:14px;"></span>
                                    <span><?php esc_html_e( 'Subir / Elegir Foto', 'aura-suite' ); ?></span>
                                </button>
                                <button type="button" class="btn btn-glass btn-xs" id="btn-remove-contact-avatar" style="display: none; color: #f87171;">
                                    <span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;"></span>
                                    <span><?php esc_html_e( 'Quitar Foto', 'aura-suite' ); ?></span>
                                </button>
                            </div>
                            <span style="font-size: 0.75rem; color: #94a3b8; margin-top: 4px;">
                                <?php esc_html_e( 'Avatar sincronizable con Google People API y Gravatar automático.', 'aura-suite' ); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Campos de Nombres y Apellidos -->
                    <div class="contact-form-grid contact-form-grid-2col" id="contact-name-fields-row" style="margin-bottom: 14px;">
                        <div class="aura-form-group">
                            <label for="contact-first-name" class="aura-form-label" id="contact-label-first-name">
                                <?php esc_html_e( 'Nombre(s)', 'aura-suite' ); ?> <span style="color:#ef4444;">*</span>
                            </label>
                            <div class="contact-input-icon-wrap">
                                <span class="contact-input-icon dashicons dashicons-admin-users"></span>
                                <input type="text" id="contact-first-name" name="first_name" class="aura-input" required placeholder="Ej: Carlos" autocomplete="off">
                            </div>
                        </div>
                        <div class="aura-form-group" id="contact-wrap-last-name">
                            <label for="contact-last-name" class="aura-form-label">
                                <?php esc_html_e( 'Apellido(s)', 'aura-suite' ); ?>
                            </label>
                            <div class="contact-input-icon-wrap">
                                <span class="contact-input-icon dashicons dashicons-id"></span>
                                <input type="text" id="contact-last-name" name="last_name" class="aura-input" placeholder="Ej: González" autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <!-- Organización y Categoría -->
                    <div class="contact-form-grid contact-form-grid-2col" style="margin-bottom: 14px;">
                        <div class="aura-form-group">
                            <label for="contact-organization" class="aura-form-label" id="contact-label-organization">
                                <?php esc_html_e( 'Organización / Empresa', 'aura-suite' ); ?>
                            </label>
                            <div class="contact-input-icon-wrap">
                                <span class="contact-input-icon dashicons dashicons-building"></span>
                                <input type="text" id="contact-organization" name="organization" class="aura-input" placeholder="Ej: Centro Cultural Mateo" autocomplete="off">
                            </div>
                        </div>
                        <div class="aura-form-group">
                            <label for="contact-category" class="aura-form-label">
                                <?php esc_html_e( 'Categoría Institucional', 'aura-suite' ); ?> <span style="color:#ef4444;">*</span>
                            </label>
                            <div class="contact-input-icon-wrap">
                                <span class="contact-input-icon dashicons dashicons-tag"></span>
                                <select id="contact-category" name="category" class="aura-select" required>
                                    <?php foreach ( $categories as $ckey => $clabel ) : ?>
                                        <option value="<?php echo esc_attr( $ckey ); ?>">
                                             <?php echo esc_html( ( $category_icons[ $ckey ] ?? '🏷️' ) . ' ' . $clabel ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Cargo / Puesto -->
                    <div class="aura-form-group">
                        <label for="contact-job-title" class="aura-form-label">
                            <?php esc_html_e( 'Puesto / Cargo o Rol Profesional', 'aura-suite' ); ?>
                        </label>
                        <div class="contact-input-icon-wrap">
                            <span class="contact-input-icon dashicons dashicons-businessman"></span>
                            <input type="text" id="contact-job-title" name="job_title" class="aura-input" placeholder="Ej: Director Administrativo, Facilitador de Taller...">
                        </div>
                    </div>

                </div>

                <!-- ════ PESTAÑA 2: COMUNICACIÓN Y DIRECCIÓN ════ -->
                <div id="cmodal-tab-comms" class="contact-modal-tab-pane" style="display: none;">
                    
                    <!-- Fila: Correo Primario y Secundario -->
                    <div class="contact-form-grid contact-form-grid-2col" style="margin-bottom: 14px;">
                        <div class="aura-form-group">
                            <label for="contact-email" class="aura-form-label">
                                <?php esc_html_e( 'Correo Electrónico Principal', 'aura-suite' ); ?>
                            </label>
                            <div class="contact-input-icon-wrap">
                                <span class="contact-input-icon dashicons dashicons-email"></span>
                                <input type="email" id="contact-email" name="email" class="aura-input" placeholder="ejemplo@correo.com">
                            </div>
                        </div>
                        <div class="aura-form-group">
                            <label for="contact-email-secondary" class="aura-form-label">
                                <?php esc_html_e( 'Correo Electrónico Secundario', 'aura-suite' ); ?>
                            </label>
                            <div class="contact-input-icon-wrap">
                                <span class="contact-input-icon dashicons dashicons-email-alt"></span>
                                <input type="email" id="contact-email-secondary" name="email_secondary" class="aura-input" placeholder="alternativo@correo.com">
                            </div>
                        </div>
                    </div>

                    <!-- Fila: Teléfono Primario y WhatsApp -->
                    <div class="contact-form-grid contact-form-grid-2col" style="margin-bottom: 14px;">
                        <div class="aura-form-group">
                            <label for="contact-phone" class="aura-form-label">
                                <?php esc_html_e( 'Teléfono / Móvil', 'aura-suite' ); ?>
                            </label>
                            <div class="contact-input-icon-wrap">
                                <span class="contact-input-icon dashicons dashicons-phone"></span>
                                <input type="tel" id="contact-phone" name="phone" class="aura-input" placeholder="+52 55 1234 5678">
                            </div>
                        </div>
                        <div class="aura-form-group">
                            <label for="contact-whatsapp" class="aura-form-label" style="display: flex; align-items: center; justify-content: space-between;">
                                <span><?php esc_html_e( 'WhatsApp Directo', 'aura-suite' ); ?></span>
                                <span style="color: #22c55e; font-size: 0.75rem;">🟢 Mensajería</span>
                            </label>
                            <div class="contact-input-icon-wrap">
                                <span class="contact-input-icon dashicons dashicons-smartphone" style="color: #22c55e;"></span>
                                <input type="tel" id="contact-whatsapp" name="whatsapp" class="aura-input" placeholder="+52 55 1234 5678">
                            </div>
                        </div>
                    </div>

                    <!-- Dirección Física (Calle y Número) -->
                    <div class="aura-form-group" style="margin-bottom: 14px;">
                        <label for="contact-address" class="aura-form-label">
                            <?php esc_html_e( 'Dirección (Calle, Número, Colonia)', 'aura-suite' ); ?>
                        </label>
                        <div class="contact-input-icon-wrap">
                            <span class="contact-input-icon dashicons dashicons-location"></span>
                            <input type="text" id="contact-address" name="address" class="aura-input" placeholder="Av. Revolución 123, Depto 401">
                        </div>
                    </div>

                    <!-- Ciudad, Estado, País, Código Postal -->
                    <div class="contact-form-grid contact-form-grid-address" style="margin-bottom: 14px;">
                        <div class="aura-form-group">
                            <label for="contact-address-city" class="aura-form-label"><?php esc_html_e( 'Ciudad', 'aura-suite' ); ?></label>
                            <div class="contact-input-icon-wrap">
                                <span class="contact-input-icon dashicons dashicons-location-alt"></span>
                                <input type="text" id="contact-address-city" name="address_city" class="aura-input" placeholder="Ciudad de México">
                            </div>
                        </div>
                        <div class="aura-form-group">
                            <label for="contact-address-state" class="aura-form-label"><?php esc_html_e( 'Estado / Región', 'aura-suite' ); ?></label>
                            <div class="contact-input-icon-wrap">
                                <span class="contact-input-icon dashicons dashicons-admin-site"></span>
                                <input type="text" id="contact-address-state" name="address_state" class="aura-input" placeholder="CDMX">
                            </div>
                        </div>
                        <div class="aura-form-group">
                            <label for="contact-address-postal" class="aura-form-label"><?php esc_html_e( 'C.P.', 'aura-suite' ); ?></label>
                            <div class="contact-input-icon-wrap">
                                <span class="contact-input-icon dashicons dashicons-post-status"></span>
                                <input type="text" id="contact-address-postal" name="address_postal" class="aura-input" placeholder="03100">
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ════ PESTAÑA 3: VINCULACIONES Y NOTAS ════ -->
                <div id="cmodal-tab-relations" class="contact-modal-tab-pane" style="display: none;">
                    
                    <!-- Etiquetas (Tags) -->
                    <div class="aura-form-group" style="margin-bottom: 14px;">
                        <label for="contact-tags" class="aura-form-label">
                            🏷️ <?php esc_html_e( 'Etiquetas / Tags (separadas por coma)', 'aura-suite' ); ?>
                        </label>
                        <div class="contact-input-icon-wrap">
                            <span class="contact-input-icon dashicons dashicons-tag"></span>
                            <input type="text" id="contact-tags" name="tags" class="aura-input" placeholder="diplomado-2026, facilitador-externo, benefactor, vip">
                        </div>
                        <div class="contact-tags-quick-selector" style="margin-top: 8px;">
                            <span style="font-size: 0.76rem; color: #94a3b8; display: block; margin-bottom: 5px;">
                                <?php esc_html_e( 'Etiquetas disponibles en el sistema (haz clic para añadir o quitar):', 'aura-suite' ); ?>
                            </span>
                            <div id="contact-tags-cloud" class="aura-tags-cloud" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
                        </div>
                    </div>

                    <!-- Notas / Observaciones -->
                    <div class="aura-form-group">
                        <label for="contact-notes" class="aura-form-label">
                            📝 <?php esc_html_e( 'Notas Internas / Observaciones', 'aura-suite' ); ?>
                        </label>
                        <textarea id="contact-notes" name="notes" class="aura-textarea" rows="3" placeholder="<?php esc_attr_e( 'Anotaciones confidenciales o historial relevante para la institución...', 'aura-suite' ); ?>"></textarea>
                    </div>

                </div>

            </div>

            <!-- Footer del Modal -->
            <div class="contact-modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeContactModal();">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" class="btn btn-primary btn-lift" id="btn-save-contact">
                        <span class="dashicons dashicons-saved"></span>
                        <span><?php esc_html_e( 'Guardar Contacto', 'aura-suite' ); ?></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL: VINCULAR TERCERO O USUARIO WP A CONTACTO EXISTENTE
═══════════════════════════════════════════════════════════════════ -->
<div id="modal-link-entity" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal aura-glass-card link-modal-dialog">
        <div class="aura-modal__header">
            <h3 class="aura-modal__title">
                🔗 <?php esc_html_e( 'Vincular Entidad al Contacto', 'aura-suite' ); ?>
            </h3>
            <button type="button" class="aura-modal__close" onclick="closeLinkModal();">&times;</button>
        </div>

        <div class="aura-modal__body link-modal-body" style="display: flex; flex-direction: column; gap: 16px;">
            <input type="hidden" id="link-target-contact-id" value="0">
            <p id="link-contact-name-banner" style="margin: 0; font-weight: 600; color: #38bdf8;"></p>

            <div class="aura-form-group">
                <label class="aura-form-label" style="font-weight: 600;">
                    <?php esc_html_e( 'Vincular con Tercero de Aura (Directorio Comercial)', 'aura-suite' ); ?>
                </label>
                <input type="text" id="link-modal-tp-search" class="aura-input" placeholder="<?php esc_attr_e( 'Buscar por nombre comercial, razón social o RFC...', 'aura-suite' ); ?>" style="width: 100%;">
                <div id="link-modal-tp-results" class="contacts-autocomplete-dropdown" style="display: none;"></div>
            </div>

            <!-- Crear Tercero Nuevo directamente -->
            <div class="aura-glass-card" style="background: rgba(56, 189, 248, 0.04); border: 1px solid rgba(56, 189, 248, 0.2); padding: 12px 14px; border-radius: 10px; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <div>
                    <div style="font-weight: 600; font-size: 0.86rem; color: #fff; display: flex; align-items: center; gap: 6px;">
                        <span>🏢</span>
                        <span><?php esc_html_e( '¿No existe aún en Terceros?', 'aura-suite' ); ?></span>
                    </div>
                    <div style="font-size: 0.74rem; color: #94a3b8;">
                        <?php esc_html_e( 'Crea y registra este contacto en el catálogo contable de Finanzas.', 'aura-suite' ); ?>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm btn-lift" id="btn-link-modal-create-tp" onclick="window.AuraContactsApp.createThirdPartyFromContact();" style="white-space: nowrap;">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <span><?php esc_html_e( 'Crear como Tercero', 'aura-suite' ); ?></span>
                </button>
            </div>

            <div class="aura-form-group">
                <label class="aura-form-label" style="font-weight: 600;">
                    <?php esc_html_e( 'Vincular con Usuario de WordPress', 'aura-suite' ); ?>
                </label>
                <input type="text" id="link-modal-wp-search" class="aura-input" placeholder="<?php esc_attr_e( 'Buscar por login, nombre o email de usuario...', 'aura-suite' ); ?>" style="width: 100%;">
                <div id="link-modal-wp-results" class="contacts-autocomplete-dropdown" style="display: none;"></div>
            </div>
        </div>

        <div class="aura-modal__footer" style="display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
            <button type="button" class="btn btn-glass" onclick="closeLinkModal();"><?php esc_html_e( 'Cerrar', 'aura-suite' ); ?></button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL: TUTORIAL PASO A PASO GOOGLE OAUTH 2.0 & PEOPLE API
═══════════════════════════════════════════════════════════════════ -->
<div id="modal-oauth-tutorial" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal aura-glass-card oauth-tutorial-dialog">
        <div class="aura-modal__header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(56, 189, 248, 0.15); display: flex; align-items: center; justify-content: center; color: #38bdf8;">
                    <span class="dashicons dashicons-google" style="font-size: 22px; width: 22px; height: 22px;"></span>
                </div>
                <div>
                    <h3 class="aura-modal__title" style="margin: 0; font-size: 1.15rem; color: #fff; font-weight: 700;">
                        <?php esc_html_e( 'Tutorial: Configuración de Google OAuth 2.0 & People API', 'aura-suite' ); ?>
                    </h3>
                    <p style="margin: 2px 0 0; font-size: 0.8rem; color: var(--aura-text-muted, #94a3b8);">
                        <?php esc_html_e( 'Guía visual paso a paso para conectar y sincronizar tus contactos de Google sin errores.', 'aura-suite' ); ?>
                    </p>
                </div>
            </div>
            <button type="button" class="aura-modal__close" onclick="closeOAuthTutorial();">&times;</button>
        </div>

        <div class="aura-modal__body oauth-tutorial-body" style="display: flex; flex-direction: column; gap: 18px; padding: 20px 24px;">
            
            <!-- Paso 1 -->
            <div style="display: flex; gap: 14px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 16px; border-radius: 10px;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #38bdf8; color: #0f172a; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.95rem;">
                    1
                </div>
                <div style="flex: 1;">
                    <h4 style="margin: 0 0 6px; font-size: 0.98rem; color: #fff; font-weight: 700;">
                        <?php esc_html_e( 'Crear o Seleccionar un Proyecto en Google Cloud', 'aura-suite' ); ?>
                    </h4>
                    <p style="margin: 0 0 10px; font-size: 0.85rem; color: #cbd5e1; line-height: 1.5;">
                        <?php esc_html_e( 'Ingresa a Google Cloud Console con la cuenta administradora o institucional y asegúrate de tener un proyecto activo.', 'aura-suite' ); ?>
                    </p>
                    <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener noreferrer" class="btn btn-glass btn-sm" style="display: inline-flex; align-items: center; gap: 6px; color: #38bdf8;">
                        <span class="dashicons dashicons-external"></span>
                        <span><?php esc_html_e( 'Ir a Google Cloud Console', 'aura-suite' ); ?></span>
                    </a>
                </div>
            </div>

            <!-- Paso 2: CRÍTICO PEOPLE API -->
            <div style="display: flex; gap: 14px; background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.25); padding: 16px; border-radius: 10px;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #ef4444; color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.95rem;">
                    2
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <h4 style="margin: 0; font-size: 0.98rem; color: #fff; font-weight: 700;">
                            <?php esc_html_e( 'Habilitar "Google People API" (Paso Fundamental)', 'aura-suite' ); ?>
                        </h4>
                        <span class="badge badge-rose" style="font-size: 0.72rem; padding: 2px 8px;"><?php esc_html_e( 'Requerido', 'aura-suite' ); ?></span>
                    </div>
                    <p style="margin: 0 0 10px; font-size: 0.85rem; color: #cbd5e1; line-height: 1.5;">
                        <?php esc_html_e( 'Si esta API no está habilitada en tu proyecto de Google Cloud, la sincronización fallará con error 403 o no podrá leer ni guardar ningún contacto.', 'aura-suite' ); ?>
                    </p>
                    <ol style="margin: 0 0 12px 18px; padding: 0; font-size: 0.82rem; color: #94a3b8; line-height: 1.6;">
                        <li><?php esc_html_e( 'Abre el enlace directo a la biblioteca de APIs.', 'aura-suite' ); ?></li>
                        <li><?php esc_html_e( 'Haz clic en el botón azul "Habilitar" (Enable).', 'aura-suite' ); ?></li>
                    </ol>
                    <a href="https://console.cloud.google.com/apis/library/people.googleapis.com" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm btn-lift" style="display: inline-flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-external"></span>
                        <span><?php esc_html_e( 'Habilitar Google People API en Cloud Console', 'aura-suite' ); ?></span>
                    </a>
                </div>
            </div>

            <!-- Paso 3: Pantalla de Consentimiento -->
            <div style="display: flex; gap: 14px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 16px; border-radius: 10px;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #38bdf8; color: #0f172a; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.95rem;">
                    3
                </div>
                <div style="flex: 1;">
                    <h4 style="margin: 0 0 6px; font-size: 0.98rem; color: #fff; font-weight: 700;">
                        <?php esc_html_e( 'Configurar la Pantalla de Consentimiento OAuth', 'aura-suite' ); ?>
                    </h4>
                    <p style="margin: 0 0 10px; font-size: 0.85rem; color: #cbd5e1; line-height: 1.5;">
                        <?php esc_html_e( 'Ve a APIs & Services > OAuth consent screen:', 'aura-suite' ); ?>
                    </p>
                    <ul style="margin: 0 0 12px 18px; padding: 0; font-size: 0.82rem; color: #94a3b8; line-height: 1.6;">
                        <li><strong><?php esc_html_e( 'Tipo de usuario:', 'aura-suite' ); ?></strong> <?php esc_html_e( 'Elige "Externo" (External) para cuentas Gmail o "Interno" si usas Google Workspace institucional.', 'aura-suite' ); ?></li>
                        <li><strong><?php esc_html_e( 'Nombre de la aplicación:', 'aura-suite' ); ?></strong> <?php esc_html_e( 'Ej: Aura Suite Contactos o el nombre de tu institución.', 'aura-suite' ); ?></li>
                        <li><strong><?php esc_html_e( 'Usuarios de prueba (Test Users):', 'aura-suite' ); ?></strong> <?php esc_html_e( 'Si tu app está en modo "Testing", agrega tu correo (ej: centromateo@gmail.com) en la sección "Test users".', 'aura-suite' ); ?></li>
                    </ul>
                    <a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank" rel="noopener noreferrer" class="btn btn-glass btn-sm" style="display: inline-flex; align-items: center; gap: 6px; color: #38bdf8;">
                        <span class="dashicons dashicons-external"></span>
                        <span><?php esc_html_e( 'Abrir Pantalla de Consentimiento OAuth', 'aura-suite' ); ?></span>
                    </a>
                </div>
            </div>

            <!-- Paso 4: Crear Credenciales y Copiar Redirect URI -->
            <div style="display: flex; gap: 14px; background: rgba(56, 189, 248, 0.05); border: 1px solid rgba(56, 189, 248, 0.2); padding: 16px; border-radius: 10px;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #38bdf8; color: #0f172a; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.95rem;">
                    4
                </div>
                <div style="flex: 1;">
                    <h4 style="margin: 0 0 6px; font-size: 0.98rem; color: #fff; font-weight: 700;">
                        <?php esc_html_e( 'Crear ID de Cliente OAuth (Web Application) y Configurar Redirect URI', 'aura-suite' ); ?>
                    </h4>
                    <p style="margin: 0 0 10px; font-size: 0.85rem; color: #cbd5e1; line-height: 1.5;">
                        <?php esc_html_e( 'Ve a APIs & Services > Credentials > Crear Credenciales > ID de cliente OAuth:', 'aura-suite' ); ?>
                    </p>
                    <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                        <label style="display: block; font-size: 0.78rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px;">
                            <?php esc_html_e( 'URI de Redirección Autorizada para pegar en Google Cloud:', 'aura-suite' ); ?>
                        </label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="tutorial-copy-redirect-uri" class="aura-input" value="<?php echo esc_url( $redirect_uri ); ?>" readonly style="font-size: 0.82rem; background: rgba(255,255,255,0.05); color: #38bdf8; width: 100%;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('tutorial-copy-redirect-uri').value); showToast('URI copiada al portapapeles');">
                                <span class="dashicons dashicons-admin-page"></span>
                                <span><?php esc_html_e( 'Copiar', 'aura-suite' ); ?></span>
                            </button>
                        </div>
                        <span style="font-size: 0.75rem; color: #f59e0b; margin-top: 5px; display: block;">
                            ⚠️ <?php esc_html_e( 'Pega esta URL en el campo "URIs de redireccionamiento autorizados" de Google Cloud.', 'aura-suite' ); ?>
                        </span>
                    </div>
                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer" class="btn btn-glass btn-sm" style="display: inline-flex; align-items: center; gap: 6px; color: #38bdf8;">
                        <span class="dashicons dashicons-external"></span>
                        <span><?php esc_html_e( 'Abrir Credenciales en Google Cloud', 'aura-suite' ); ?></span>
                    </a>
                </div>
            </div>

            <!-- Paso 5: Guardar y Conectar -->
            <div style="display: flex; gap: 14px; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.25); padding: 16px; border-radius: 10px;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #10b981; color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.95rem;">
                    5
                </div>
                <div style="flex: 1;">
                    <h4 style="margin: 0 0 6px; font-size: 0.98rem; color: #fff; font-weight: 700;">
                        <?php esc_html_e( 'Pegar Client ID / Secret en WordPress y Autorizar', 'aura-suite' ); ?>
                    </h4>
                    <p style="margin: 0 0 10px; font-size: 0.85rem; color: #cbd5e1; line-height: 1.5;">
                        <?php esc_html_e( 'Copia el Client ID y Client Secret generados por Google Cloud, pégalos en el formulario de la pestaña "Google & Workspace" y haz clic en "Guardar Credenciales OAuth". Luego pulsa el botón verde "Autorizar y Conectar con Google".', 'aura-suite' ); ?>
                    </p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <span class="badge badge-emerald" style="padding: 4px 10px; font-size: 0.78rem;">
                            ✅ <?php esc_html_e( 'Sincronización bidireccional automática activada', 'aura-suite' ); ?>
                        </span>
                        <span class="badge badge-blue" style="padding: 4px 10px; font-size: 0.78rem;">
                            ⚡ <?php esc_html_e( 'Tokens renovados offline sin interrupciones', 'aura-suite' ); ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <div class="aura-modal__footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; border-top: 1px solid rgba(255,255,255,0.08); padding: 14px 24px; background: rgba(15, 23, 42, 0.95);">
            <span style="font-size: 0.8rem; color: #94a3b8;">
                💡 <?php esc_html_e( 'Si tu sincronización muestra fallos, verifica en la pestaña "Historial Sync" el código de error devuelto por Google.', 'aura-suite' ); ?>
            </span>
            <button type="button" class="btn btn-primary btn-sm btn-lift" onclick="closeOAuthTutorial();">
                <?php esc_html_e( 'Entendido, Cerrar Tutorial', 'aura-suite' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL: FUSIÓN ASISTIDA INTELIGENTE DE CONTACTOS DUPLICADOS
═══════════════════════════════════════════════════════════════════ -->
<div id="modal-merge-contacts" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal aura-glass-card merge-modal-dialog">
        <!-- Header del Modal -->
        <div class="merge-modal-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%); display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 4px 14px rgba(139, 92, 246, 0.4);">
                    <span class="dashicons dashicons-randomize" style="font-size: 22px; width: 22px; height: 22px;"></span>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: #fff;">
                        <?php esc_html_e( 'Fusión Asistida de Contactos Duplicados', 'aura-suite' ); ?>
                    </h3>
                    <p style="margin: 2px 0 0; font-size: 0.82rem; color: #94a3b8;">
                        <?php esc_html_e( 'Selecciona o edita qué dato de cada contacto conformará la ficha maestra definitiva.', 'aura-suite' ); ?>
                    </p>
                </div>
            </div>
            <button type="button" class="aura-modal__close" onclick="closeMergeModal();" title="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">&times;</button>
        </div>

        <!-- Cuerpo del Modal -->
        <div class="merge-modal-body" id="merge-modal-content">
            <!-- Inyectado dinámicamente por JavaScript -->
        </div>

        <!-- Footer del Modal -->
        <div class="merge-modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeMergeModal();">
                <?php esc_html_e( 'Cancelar Fusión', 'aura-suite' ); ?>
            </button>
            <button type="button" class="btn btn-emerald btn-sm btn-shimmer btn-lift" id="btn-submit-merge-action" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="dashicons dashicons-randomize"></span>
                <span><?php esc_html_e( 'Confirmar y Fusionar Contactos', 'aura-suite' ); ?></span>
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL DE EDICIÓN MASIVA DE CONTACTOS (AURA ENTERPRISE BULK EDIT)
═══════════════════════════════════════════════════════════════════ -->
<div id="modal-bulk-edit-contacts" class="aura-modal-overlay is-hidden" style="display: none; z-index: 999999;">
    <div class="aura-modal aura-glass-card bulk-modal-dialog">
        <!-- Cabecera del Modal -->
        <div class="aura-modal__header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <span class="dashicons dashicons-edit"></span>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: #fff;">
                        <?php esc_html_e( 'Edición Masiva de Contactos', 'aura-suite' ); ?>
                    </h3>
                    <p style="margin: 2px 0 0; font-size: 0.82rem; color: #94a3b8;">
                        <?php esc_html_e( 'Aplica modificaciones en lote a ', 'aura-suite' ); ?>
                        <strong id="bulk-edit-modal-count" style="color: #38bdf8;">0</strong>
                        <?php esc_html_e( ' contactos seleccionados.', 'aura-suite' ); ?>
                    </p>
                </div>
            </div>
            <button type="button" class="aura-modal__close" id="btn-close-bulk-modal" onclick="if(window.closeBulkModal){window.closeBulkModal();}else if(window.AuraContactsApp&&window.AuraContactsApp.closeBulkEditModal){window.AuraContactsApp.closeBulkEditModal();}" style="background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>

        <!-- Cuerpo del Modal -->
        <div class="aura-modal__body bulk-modal-body" style="padding: 22px 24px;">
            <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 10px; padding: 12px 16px; margin-bottom: 18px; color: #93c5fd; font-size: 0.84rem; display: flex; align-items: flex-start; gap: 10px;">
                <span class="dashicons dashicons-info" style="font-size: 18px; width: 18px; height: 18px; margin-top: 1px; flex-shrink: 0;"></span>
                <span>
                    <strong><?php esc_html_e( 'Regla de Seguridad:', 'aura-suite' ); ?></strong>
                    <?php esc_html_e( 'Solo se actualizarán los campos cuya casilla esté activada. Los campos sin marcar conservarán intactos sus valores originales en cada contacto.', 'aura-suite' ); ?>
                </span>
            </div>

            <!-- Campo 1: Categoría -->
            <div class="bulk-field-group" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 10px; padding: 14px 16px; margin-bottom: 14px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700; font-size: 0.92rem; color: #f8fafc; margin-bottom: 8px;">
                    <input type="checkbox" id="bulk-chk-category" class="aura-checkbox" style="width: 17px; height: 17px;">
                    <span>🏷️ <?php esc_html_e( 'Cambiar Categoría', 'aura-suite' ); ?></span>
                </label>
                <div id="bulk-wrap-category" style="opacity: 0.45; pointer-events: none; transition: opacity 0.2s ease;">
                    <select id="bulk-val-category" class="aura-select" style="width: 100%; height: 38px; border-radius: 8px;">
                        <?php foreach ( $categories as $ckey => $clabel ) : ?>
                            <option value="<?php echo esc_attr( $ckey ); ?>">
                                <?php echo esc_html( ( $category_icons[ $ckey ] ?? '🏷️' ) . ' ' . $clabel ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Campo 2: Etiquetas / Tags -->
            <div class="bulk-field-group" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 10px; padding: 14px 16px; margin-bottom: 14px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700; font-size: 0.92rem; color: #f8fafc; margin-bottom: 8px;">
                    <input type="checkbox" id="bulk-chk-tags" class="aura-checkbox" style="width: 17px; height: 17px;">
                    <span>🔖 <?php esc_html_e( 'Modificar Etiquetas / Tags', 'aura-suite' ); ?></span>
                </label>
                <div id="bulk-wrap-tags" style="opacity: 0.45; pointer-events: none; transition: opacity 0.2s ease; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; gap: 12px; font-size: 0.82rem; color: #cbd5e1;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="radio" name="bulk-tag-mode" value="add" checked>
                            <span>➕ <?php esc_html_e( 'Añadir a las etiquetas existentes', 'aura-suite' ); ?></span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="radio" name="bulk-tag-mode" value="replace">
                            <span>🔄 <?php esc_html_e( 'Reemplazar todas las etiquetas', 'aura-suite' ); ?></span>
                        </label>
                    </div>
                    <input type="text" id="bulk-val-tags" class="aura-input" placeholder="<?php esc_attr_e( 'Ej: VIP, Egresado 2026, Comité Directivo (separar por comas)', 'aura-suite' ); ?>" style="width: 100%;">
                    <div class="bulk-tags-quick-selector" style="margin-top: 6px;">
                        <span style="font-size: 0.76rem; color: #94a3b8; display: block; margin-bottom: 5px;">
                            <?php esc_html_e( 'Etiquetas disponibles en el sistema (haz clic para añadir o quitar):', 'aura-suite' ); ?>
                        </span>
                        <div id="bulk-tags-cloud" class="aura-tags-cloud" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
                    </div>
                </div>
            </div>

            <!-- Campo 3: Estado Activo / Archivado -->
            <div class="bulk-field-group" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 10px; padding: 14px 16px; margin-bottom: 14px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700; font-size: 0.92rem; color: #f8fafc; margin-bottom: 8px;">
                    <input type="checkbox" id="bulk-chk-status" class="aura-checkbox" style="width: 17px; height: 17px;">
                    <span>🔘 <?php esc_html_e( 'Cambiar Estado', 'aura-suite' ); ?></span>
                </label>
                <div id="bulk-wrap-status" style="opacity: 0.45; pointer-events: none; transition: opacity 0.2s ease;">
                    <select id="bulk-val-status" class="aura-select" style="width: 100%; height: 38px; border-radius: 8px;">
                        <option value="1">🟢 <?php esc_html_e( 'Activo (Visible en Directorio)', 'aura-suite' ); ?></option>
                        <option value="0">⚪ <?php esc_html_e( 'Inactivo / Archivado', 'aura-suite' ); ?></option>
                    </select>
                </div>
            </div>

            <!-- Campo 4: Re-sincronizar con Google Contacts -->
            <div class="bulk-field-group" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 10px; padding: 14px 16px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700; font-size: 0.92rem; color: #f8fafc;">
                    <input type="checkbox" id="bulk-chk-sync" class="aura-checkbox" style="width: 17px; height: 17px;" checked>
                    <span>🔄 <?php esc_html_e( 'Marcar para sincronización con Google Contacts', 'aura-suite' ); ?></span>
                </label>
                <p style="margin: 6px 0 0 27px; font-size: 0.78rem; color: #94a3b8;">
                    <?php esc_html_e( 'Propaga automáticamente las categorías, etiquetas en español y cambios hacia Google People API.', 'aura-suite' ); ?>
                </p>
            </div>
        </div>

        <!-- Footer del Modal -->
        <div class="aura-modal__footer bulk-modal-footer" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 14px 24px; border-top: 1px solid rgba(255, 255, 255, 0.08); background: rgba(15, 23, 42, 0.95);">
            <div class="bulk-footer-left">
                <?php if ( $can_delete ) : ?>
                    <button type="button" class="btn btn-danger btn-sm btn-lift" id="btn-bulk-modal-delete" style="display: flex; align-items: center; gap: 6px; background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.35);">
                        <span class="dashicons dashicons-trash"></span>
                        <span><?php esc_html_e( 'Borrar Seleccionados', 'aura-suite' ); ?></span>
                    </button>
                <?php endif; ?>
            </div>
            <div class="bulk-footer-right" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary btn-sm" id="btn-cancel-bulk-modal" onclick="if(window.closeBulkModal){window.closeBulkModal();}else if(window.AuraContactsApp&&window.AuraContactsApp.closeBulkEditModal){window.AuraContactsApp.closeBulkEditModal();}">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
                <button type="button" class="btn btn-primary btn-sm btn-lift" id="btn-submit-bulk-modal" style="display: flex; align-items: center; gap: 6px;">
                    <span class="dashicons dashicons-saved"></span>
                    <span><?php esc_html_e( 'Guardar Cambios Masivos', 'aura-suite' ); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>


