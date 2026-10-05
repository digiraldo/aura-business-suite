<?php
/**
 * Template: Página de Configuración (UX Modernizada v2.0)
 * Estándar §5.7: Tailwind CSS + Glassmorphism + ApexCharts + DataTables §5.6 Responsive
 * Mobile-first, 100% responsive.
 *
 * @package AuraBusinessSuite
 * @updated 2026-05 — Rediseño completo con Tailwind CDN, tabs, glassmorphism, DataTables §5.6
 *
 * ASSETS cargados desde aura-business-suite.php (hook: aura-suite_page_aura-settings):
 *  - Inter Font            (wp_enqueue_style  'aura-inter-font')
 *  - Tailwind CSS CDN      (wp_enqueue_script 'tailwindcss-cdn')
 *  - ApexCharts            (wp_enqueue_script 'apexcharts')
 *  - DataTables §5.6 CSS  (wp_enqueue_style  'datatables-css', 'datatables-responsive-css')
 *  - DataTables §5.6 JS   (wp_enqueue_script 'datatables-js', 'datatables-responsive-js')
 *  - wp_enqueue_media()   (para el media uploader del logo)
 * NO se deben duplicar aquí con add_action / echo de <script> / <link>.
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('aura_admin_settings')) {
    wp_die(__('No tienes permiso para acceder a esta página.', 'aura-suite'));
}

// ── Configuración de Tailwind (debe ir antes del render del HTML) ──────────
// Se inyecta via wp_head inline (después de que Tailwind CDN ya fue encolado).
add_action('admin_head', function () {
    ?>
    <script>
    // Tailwind config — scoped con prefix "tw-" para no colisionar con estilos WP
    if (typeof tailwind !== 'undefined') {
        tailwind.config = {
            prefix: 'tw-',
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'] },
                    colors: {
                        aura: {
                            50:  '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe',
                            400: '#818cf8', 500: '#6366f1', 600: '#4f46e5',
                            700: '#4338ca', 800: '#3730a3', 900: '#312e81',
                        }
                    }
                }
            }
        };
    }
    </script>
    <?php
}, 1);



// ── Guardar configuración ──────────────────────────────────────────────────
$save_success = false;
if (isset($_POST['aura_save_settings']) && wp_verify_nonce($_POST['aura_settings_nonce'], 'save_aura_settings')) {

    update_option('aura_notification_email',       sanitize_email($_POST['notification_email']));
    update_option('aura_notification_from_name',   sanitize_text_field($_POST['notification_from_name']));

    $auto_approval_mode = sanitize_text_field($_POST['auto_approval_mode'] ?? 'expenses_threshold_income_auto');
    update_option('aura_finance_auto_approval_enabled',               isset($_POST['auto_approval_enabled']));
    update_option('aura_finance_auto_approval_threshold',             floatval($_POST['auto_approval_threshold'] ?? 0));
    update_option('aura_finance_auto_approval_mode',                  $auto_approval_mode);
    if ($auto_approval_mode === 'expenses_threshold_income_auto' || $auto_approval_mode === 'expenses_only_threshold') {
        update_option('aura_finance_auto_approval_apply_to_expenses_only', true);
        update_option('aura_finance_auto_approval_apply_to_income_only', false);
    } elseif ($auto_approval_mode === 'income_only_threshold') {
        update_option('aura_finance_auto_approval_apply_to_expenses_only', false);
        update_option('aura_finance_auto_approval_apply_to_income_only', true);
    } else {
        update_option('aura_finance_auto_approval_apply_to_expenses_only', false);
        update_option('aura_finance_auto_approval_apply_to_income_only', false);
    }

    update_option('aura_electric_threshold',  floatval($_POST['electric_threshold']));
    update_option('aura_electric_cost_kwh',   floatval($_POST['electric_cost_kwh']));

    $org_logo_id = absint($_POST['aura_org_logo_id'] ?? 0);
    update_option('aura_org_name',        sanitize_text_field($_POST['org_name'] ?? ''));
    update_option('aura_org_tagline',     sanitize_text_field($_POST['org_tagline'] ?? ''));
    update_option('aura_org_logo_id',     $org_logo_id);
    update_option('aura_org_logo_url',    $org_logo_id ? wp_get_attachment_image_url($org_logo_id, 'medium') : '');
    update_option('aura_org_logo_in_login', isset($_POST['org_logo_in_login']));
    update_option('aura_org_logo_in_email', isset($_POST['org_logo_in_email']));

    update_option('aura_whatsapp_enabled',         isset($_POST['whatsapp_enabled']) ? '1' : '0');
    update_option('aura_whatsapp_provider',          sanitize_key($_POST['whatsapp_provider'] ?? 'green_api'));
    update_option('aura_whatsapp_green_instance_id', sanitize_text_field($_POST['whatsapp_green_instance_id'] ?? ''));
    update_option('aura_whatsapp_from',              sanitize_text_field($_POST['whatsapp_from'] ?? ''));
    update_option('aura_whatsapp_twilio_sid',        sanitize_text_field($_POST['whatsapp_twilio_sid'] ?? ''));
    update_option('aura_whatsapp_meta_phone_id',     sanitize_text_field($_POST['whatsapp_meta_phone_id'] ?? ''));
    update_option('aura_whatsapp_signature',         sanitize_text_field($_POST['whatsapp_signature'] ?? ''));
    if (!empty($_POST['whatsapp_api_token'])) {
        update_option('aura_whatsapp_api_token', sanitize_text_field($_POST['whatsapp_api_token']));
    }

    update_option('aura_gcal_enabled', isset($_POST['gcal_enabled']) ? '1' : '0');
    if (isset($_POST['gcal_share_email'])) {
        $gcal_emails = array_filter(array_map(fn($e) => sanitize_email(trim($e)), explode(',', $_POST['gcal_share_email'])));
        update_option('aura_gcal_share_email', implode(', ', $gcal_emails));
    }
    if (isset($_POST['gcal_reminder_days'])) {
        $gcal_days = implode(',', array_filter(array_map(function ($v) {
            $n = intval($v);
            return ($n >= 1 && $n <= 28) ? $n : null;
        }, explode(',', $_POST['gcal_reminder_days']))));
        update_option('aura_gcal_reminder_days', $gcal_days ?: '15,7,3,1');
    }
    if (!empty($_POST['gcal_service_account_json'])) {
        $gcal_json_raw = wp_unslash($_POST['gcal_service_account_json']);
        $gcal_parsed   = json_decode($gcal_json_raw, true);
        if (is_array($gcal_parsed) && ($gcal_parsed['type'] ?? '') === 'service_account') {
            update_option('aura_gcal_service_account_json', $gcal_json_raw);
            delete_transient('aura_gcal_token');
            delete_option('aura_gcal_calendar_id_resolved');
        }
    }

    if (isset($_POST['gdrive_folder_id'])) {
        update_option('aura_gdrive_folder_id', sanitize_text_field($_POST['gdrive_folder_id']));
    }
    if (isset($_POST['gdrive_type'])) {
        $gdrive_type = sanitize_key($_POST['gdrive_type']);
        if (in_array($gdrive_type, ['my_drive', 'shared_drive'], true)) {
            update_option('aura_gdrive_type', $gdrive_type);
        }
    }
    if (!empty($_POST['gdrive_credentials_json'])) {
        $gdrive_json_raw = wp_unslash($_POST['gdrive_credentials_json']);
        $gdrive_parsed   = json_decode($gdrive_json_raw, true);
        if (is_array($gdrive_parsed) && ($gdrive_parsed['type'] ?? '') === 'service_account') {
            update_option('aura_gdrive_credentials_json', $gdrive_json_raw);
        }
    }

    $save_success = true;
}

// ── Obtener configuración actual ──────────────────────────────────────────
$notification_email       = get_option('aura_notification_email', get_option('admin_email'));
$notification_from_name   = get_option('aura_notification_from_name', get_bloginfo('name'));
$auto_approval_enabled    = get_option('aura_finance_auto_approval_enabled', false);
$auto_approval_threshold  = get_option('aura_finance_auto_approval_threshold', 0);
$auto_approval_mode       = get_option('aura_finance_auto_approval_mode', '');
$apply_to_expenses_only   = get_option('aura_finance_auto_approval_apply_to_expenses_only', true);
$apply_to_income_only     = get_option('aura_finance_auto_approval_apply_to_income_only', false);
if (empty($auto_approval_mode)) {
    if ($apply_to_expenses_only) {
        $auto_approval_mode = 'expenses_threshold_income_auto';
    } elseif ($apply_to_income_only) {
        $auto_approval_mode = 'income_only_threshold';
    } else {
        $auto_approval_mode = 'both_threshold';
    }
}
$electric_threshold       = get_option('aura_electric_threshold', 500);
$electric_cost_kwh        = get_option('aura_electric_cost_kwh', 0.12);
$org_name       = aura_get_org_name();
$org_tagline    = get_option('aura_org_tagline', '');
$org_logo_id    = (int) get_option('aura_org_logo_id', 0);
$org_logo_url   = $org_logo_id ? wp_get_attachment_image_url($org_logo_id, 'medium') : '';
$org_logo_in_login = get_option('aura_org_logo_in_login', false);
$org_logo_in_email = get_option('aura_org_logo_in_email', true);

// Stats ApexCharts
$stats_chart = ['auto_approved' => 0, 'manual_approved' => 0, 'rejected' => 0, 'pending' => 0, 'time_saved_hours' => 0];
if ($auto_approval_enabled && $auto_approval_threshold > 0) {
    $stats_chart = Aura_Financial_Settings::get_auto_approval_stats('month');
}

// Categorías financieras stats
$cat_version = get_option('aura_finance_categories_installed', 'No instaladas');
$setup       = new Aura_Financial_Setup();
$cat_stats   = $setup->get_categories_stats();

// GCal
$has_gcal_json  = !empty(get_option('aura_gcal_service_account_json', ''));
$gcal_stored    = $has_gcal_json ? json_decode(get_option('aura_gcal_service_account_json'), true) : [];
$gcal_resolved  = get_option('aura_gcal_calendar_id_resolved', '');
$gcal_share_url = $gcal_resolved ? 'https://calendar.google.com/calendar/render?cid=' . rawurlencode($gcal_resolved) : '';

// IoT API Key
$api_key = get_option('aura_electricity_api_key', '');
if (empty($api_key)) {
    $api_key = wp_generate_password(32, false);
    update_option('aura_electricity_api_key', $api_key);
}
?>



<?php if ($save_success): ?>
<div id="aura-save-toast">
    <span style="font-size:18px;">✅</span>
    <?php _e('Configuración guardada exitosamente', 'aura-suite'); ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toast = document.getElementById('aura-save-toast');
    if (toast) {
        setTimeout(function () {
            toast.style.animation = 'auraFadeOut 0.4s ease forwards';
            setTimeout(function () { toast.remove(); }, 400);
        }, 3000);
    }
});
</script>
<?php endif; ?>

<div class="aura-app-wrapper aura-settings-wrap">
<div class="wrap aura-app-context" style="max-width:1200px;margin:0 auto;padding:0 16px;">

    <!-- ── CABECERA PRINCIPAL HERO GLASS CARD ── -->
    <header class="aura-glass-card aura-page-header hero-card fade-up">
        <div class="aura-page-header__icon">
            <span class="dashicons dashicons-admin-generic"></span>
        </div>
        <div class="aura-page-header__content">
            <h1><?php esc_html_e('Configuración de Aura Business Suite', 'aura-suite'); ?></h1>
            <p><?php printf(esc_html__('v%s — Configuración global del sistema y preferencias modulares', 'aura-suite'), AURA_VERSION); ?></p>
        </div>
        <div class="aura-page-header__badges">
            <span class="badge badge-blue" data-tooltip="<?php esc_attr_e('Versión activa del CMS WordPress', 'aura-suite'); ?>">
                <span class="dashicons dashicons-wordpress" style="font-size:14px;width:14px;height:14px;"></span> WP <?php echo esc_html(get_bloginfo('version')); ?>
            </span>
            <span class="badge badge-emerald" data-tooltip="<?php esc_attr_e('Versión del motor PHP en ejecución', 'aura-suite'); ?>">
                <span class="pulse-dot dot-emerald"></span> PHP <?php echo esc_html(phpversion()); ?>
            </span>
            <span class="badge badge-indigo" data-tooltip="<?php esc_attr_e('Total de usuarios registrados en el sistema', 'aura-suite'); ?>">
                <span class="dashicons dashicons-groups" style="font-size:14px;width:14px;height:14px;"></span> <?php echo esc_html(count_users()['total_users']); ?> <?php esc_html_e('usuarios', 'aura-suite'); ?>
            </span>
        </div>
    </header>

    <!-- ── BARRA DE NAVEGACIÓN DE PESTAÑAS (TABS NAVBAR) ── -->
    <nav class="aura-navbar aura-nav aura-glass-card tabs-bar fade-up delay-1" id="aura-settings-nav" role="tablist">
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn active" data-tab="tab-org" role="tab">
            <span class="dashicons dashicons-building"></span>
            <span><?php esc_html_e('Organización', 'aura-suite'); ?></span>
        </button>
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn" data-tab="tab-notifications" role="tab">
            <span class="dashicons dashicons-email-alt"></span>
            <span><?php esc_html_e('Notificaciones', 'aura-suite'); ?></span>
        </button>
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn" data-tab="tab-finance" role="tab">
            <span class="dashicons dashicons-chart-pie"></span>
            <span><?php esc_html_e('Finanzas', 'aura-suite'); ?></span>
        </button>
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn" data-tab="tab-electricity" role="tab">
            <span class="dashicons dashicons-lightbulb"></span>
            <span><?php esc_html_e('Electricidad', 'aura-suite'); ?></span>
        </button>
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn" data-tab="tab-whatsapp" role="tab">
            <span class="dashicons dashicons-smartphone"></span>
            <span><?php esc_html_e('WhatsApp', 'aura-suite'); ?></span>
        </button>
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn" data-tab="tab-gdrive" role="tab">
            <span class="dashicons dashicons-cloud"></span>
            <span><?php esc_html_e('Google Drive', 'aura-suite'); ?></span>
        </button>
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn" data-tab="tab-gcal" role="tab">
            <span class="dashicons dashicons-calendar-alt"></span>
            <span><?php esc_html_e('Google Calendar', 'aura-suite'); ?></span>
        </button>
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn" data-tab="tab-migration" role="tab">
            <span class="dashicons dashicons-database-export"></span>
            <span><?php esc_html_e('Portabilidad (.ZIP)', 'aura-suite'); ?></span>
        </button>
    </nav>

    <!-- ── FORMULARIO PRINCIPAL Y CONTENIDO ── -->
    <div class="aura-content">
        <form method="post" action="" id="aura-settings-form">
            <?php wp_nonce_field('save_aura_settings', 'aura_settings_nonce'); ?>

            <!-- ═══════════════════════════════════════════
                 TAB: ORGANIZACIÓN
                 ═══════════════════════════════════════════ -->
            <div class="aura-tab-panel active" id="tab-org">
                <div class="aura-glass-card glass-card fade-up">
                    <h2 class="aura-section-title">
                        <span class="dashicons dashicons-building"></span>
                        <?php esc_html_e('Identidad de la Organización', 'aura-suite'); ?>
                    </h2>
                    <p class="aura-desc" style="margin-bottom:20px;">
                        <?php esc_html_e('Personaliza cómo aparece tu organización en reportes exportados, emails de notificación, cabeceras del dashboard y presupuestos.', 'aura-suite'); ?>
                    </p>

                    <div class="aura-field-group cols-2">
                        <div class="aura-field">
                            <label class="aura-label" for="org_name">
                                <?php esc_html_e('Nombre de la Organización', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Razón social o nombre público que figurará en reportes oficiales y membretes.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">
                                    <span class="dashicons dashicons-admin-home"></span>
                                </span>
                                <input type="text" id="org_name" name="org_name" class="aura-input input-fancy"
                                       value="<?php echo esc_attr($org_name); ?>"
                                       placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                            </div>
                            <span class="aura-desc"><?php esc_html_e('Si se deja vacío se usará el nombre configurado en WordPress.', 'aura-suite'); ?></span>
                        </div>

                        <div class="aura-field">
                            <label class="aura-label" for="org_tagline">
                                <?php esc_html_e('Slogan / Descripción breve', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Frase institucional o subtítulo que complementa el nombre en documentos.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">
                                    <span class="dashicons dashicons-format-quote"></span>
                                </span>
                                <input type="text" id="org_tagline" name="org_tagline" class="aura-input input-fancy"
                                       value="<?php echo esc_attr($org_tagline); ?>"
                                       placeholder="<?php esc_attr_e('Ej: Instituto de Educación Superior', 'aura-suite'); ?>">
                            </div>
                            <span class="aura-desc"><?php esc_html_e('Aparece en reportes exportados PDF y encabezados institucionales.', 'aura-suite'); ?></span>
                        </div>
                    </div>

                    <div style="margin-top:24px;padding-top:20px;border-top:1px dashed #cbd5e1;">
                        <label class="aura-label" style="margin-bottom:12px;">
                            <?php esc_html_e('Logo de la Organización', 'aura-suite'); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Se recomienda un archivo PNG o SVG transparente de 300x100 px para máxima nitidez.', 'aura-suite'); ?>">?</span>
                        </label>
                        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
                            <div id="aura-org-logo-preview" style="min-width:120px;min-height:70px;border:2px dashed #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;padding:10px;background:rgba(248,250,252,0.6);">
                                <?php if ($org_logo_url): ?>
                                    <img src="<?php echo esc_url($org_logo_url); ?>" style="max-height:70px;width:auto;border-radius:6px;" alt="<?php echo esc_attr($org_name); ?>">
                                <?php else: ?>
                                    <span id="aura-logo-no-preview" class="aura-desc"><?php esc_html_e('Sin logo cargado', 'aura-suite'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <input type="hidden" id="aura_org_logo_id" name="aura_org_logo_id" value="<?php echo esc_attr($org_logo_id); ?>">
                                <button type="button" id="aura-select-logo" class="aura-btn-secondary btn-glass">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <?php echo $org_logo_id ? esc_html__('Cambiar Logo', 'aura-suite') : esc_html__('Seleccionar Logo', 'aura-suite'); ?>
                                </button>
                                <?php if ($org_logo_id): ?>
                                <button type="button" id="aura-remove-logo" class="aura-btn-secondary btn-glass" style="color:#dc2626;border-color:#fca5a5;">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php esc_html_e('Quitar Logo', 'aura-suite'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="aura-desc">
                                <?php esc_html_e('Formato sugerido: PNG o SVG transparente. Aparecerá en reportes, membretes y emails oficiales.', 'aura-suite'); ?>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:22px;display:flex;gap:32px;flex-wrap:wrap;">
                        <div class="aura-toggle-wrap">
                            <label class="aura-toggle">
                                <input type="checkbox" name="org_logo_in_login" value="1" <?php checked($org_logo_in_login, true); ?>>
                                <span class="aura-toggle-slider"></span>
                            </label>
                            <div>
                                <span class="aura-label">
                                    <?php esc_html_e('Mostrar logo en pantalla de login', 'aura-suite'); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Reemplaza el logo de WordPress por el logo corporativo en la página de acceso wp-login.php.', 'aura-suite'); ?>">?</span>
                                </span>
                                <p class="aura-desc"><?php esc_html_e('Aplica automáticamente si hay un logo seleccionado.', 'aura-suite'); ?></p>
                            </div>
                        </div>
                        <div class="aura-toggle-wrap">
                            <label class="aura-toggle">
                                <input type="checkbox" name="org_logo_in_email" value="1" <?php checked($org_logo_in_email, true); ?>>
                                <span class="aura-toggle-slider"></span>
                            </label>
                            <div>
                                <span class="aura-label">
                                    <?php esc_html_e('Incluir logo en notificaciones por email', 'aura-suite'); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Inserta el logotipo institucional en el encabezado de todas las alertas por correo emitidas por Aura.', 'aura-suite'); ?>">?</span>
                                </span>
                                <p class="aura-desc"><?php esc_html_e('Incluye el logo en la cabecera de las plantillas de correo.', 'aura-suite'); ?></p>
                            </div>
                        </div>
                    </div>

                    <script>
                    jQuery(document).ready(function($){
                        var mediaUploader;
                        $('#aura-select-logo').on('click', function(e){
                            e.preventDefault();
                            if (mediaUploader){ mediaUploader.open(); return; }
                            mediaUploader = wp.media({
                                title: '<?php echo esc_js(__('Seleccionar Logo de la Organización','aura-suite')); ?>',
                                button: { text: '<?php echo esc_js(__('Usar este logo','aura-suite')); ?>' },
                                multiple: false, library: { type: ['image'] }
                            });
                            mediaUploader.on('select', function(){
                                var att = mediaUploader.state().get('selection').first().toJSON();
                                $('#aura_org_logo_id').val(att.id);
                                var previewUrl = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                                $('#aura-org-logo-preview').html('<img src="' + previewUrl + '" style="max-height:70px;width:auto;border-radius:6px;">');
                                $('#aura-select-logo').html('<span class="dashicons dashicons-format-image"></span> <?php echo esc_js(__('Cambiar Logo','aura-suite')); ?>');
                                if ($('#aura-remove-logo').length === 0){
                                    $('#aura-select-logo').after('<button type="button" id="aura-remove-logo" class="aura-btn-secondary" style="color:#dc2626;border-color:#fca5a5;margin-top:8px;"><span class="dashicons dashicons-trash"></span> <?php echo esc_js(__('Quitar Logo','aura-suite')); ?></button>');
                                    bindRemoveLogo();
                                }
                            });
                            mediaUploader.open();
                        });
                        function bindRemoveLogo(){
                            $(document).on('click','#aura-remove-logo',function(e){
                                e.preventDefault();
                                $('#aura_org_logo_id').val('');
                                $('#aura-org-logo-preview').html('<span class="aura-desc"><?php echo esc_js(__('Sin logo cargado','aura-suite')); ?></span>');
                                $(this).remove();
                                $('#aura-select-logo').html('<span class="dashicons dashicons-format-image"></span> <?php echo esc_js(__('Seleccionar Logo','aura-suite')); ?>');
                            });
                        }
                        bindRemoveLogo();
                    });
                    </script>
                </div>

                <!-- Tarjeta de Información del Sistema dentro de Organización -->
                <div class="aura-glass-card glass-card fade-up delay-1" style="margin-top:20px;">
                    <h2 class="aura-section-title">
                        <span class="dashicons dashicons-info-outline"></span>
                        <?php esc_html_e('Información del Entorno del Sistema', 'aura-suite'); ?>
                    </h2>
                    <p class="aura-desc" style="margin-bottom:20px;">
                        <?php esc_html_e('Parámetros de ejecución del servidor, versiones de librerías y estado de la plataforma.', 'aura-suite'); ?>
                    </p>

                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
                        <div class="aura-stat-card stat-glass-card hover-lift" style="display:flex;align-items:center;gap:14px;padding:16px;">
                            <span class="dashicons dashicons-admin-plugins" style="font-size:32px;width:32px;height:32px;color:var(--aura-primary);"></span>
                            <div>
                                <div class="aura-stat-label"><?php esc_html_e('Versión de Aura Suite', 'aura-suite'); ?></div>
                                <div class="aura-stat-val" style="color:var(--aura-primary);font-size:20px;font-weight:700;">v<?php echo esc_html(AURA_VERSION); ?></div>
                            </div>
                        </div>

                        <div class="aura-stat-card stat-glass-card hover-lift" style="display:flex;align-items:center;gap:14px;padding:16px;">
                            <span class="dashicons dashicons-wordpress" style="font-size:32px;width:32px;height:32px;color:#2563eb;"></span>
                            <div>
                                <div class="aura-stat-label"><?php esc_html_e('WordPress Core', 'aura-suite'); ?></div>
                                <div class="aura-stat-val" style="font-size:20px;font-weight:700;"><?php echo esc_html(get_bloginfo('version')); ?></div>
                            </div>
                        </div>

                        <div class="aura-stat-card stat-glass-card hover-lift" style="display:flex;align-items:center;gap:14px;padding:16px;">
                            <span class="dashicons dashicons-rest-api" style="font-size:32px;width:32px;height:32px;color:#059669;"></span>
                            <div>
                                <div class="aura-stat-label"><?php esc_html_e('Versión PHP', 'aura-suite'); ?></div>
                                <div class="aura-stat-val" style="color:#059669;font-size:20px;font-weight:700;"><?php echo esc_html(phpversion()); ?></div>
                            </div>
                        </div>

                        <div class="aura-stat-card stat-glass-card hover-lift" style="display:flex;align-items:center;gap:14px;padding:16px;">
                            <span class="dashicons dashicons-groups" style="font-size:32px;width:32px;height:32px;color:#4f46e5;"></span>
                            <div>
                                <div class="aura-stat-label"><?php esc_html_e('Usuarios Registrados', 'aura-suite'); ?></div>
                                <div class="aura-stat-val" style="color:#4f46e5;font-size:20px;font-weight:700;"><?php echo esc_html(count_users()['total_users']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════
                 TAB: NOTIFICACIONES
                 ═══════════════════════════════════════════ -->
            <div class="aura-tab-panel" id="tab-notifications">
                <div class="aura-glass-card glass-card fade-up">
                    <h2 class="aura-section-title">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php esc_html_e('Configuración de Notificaciones', 'aura-suite'); ?>
                    </h2>
                    <p class="aura-desc" style="margin-bottom:20px;">
                        <?php esc_html_e('Define la identidad y los remitentes predeterminados para las alertas y avisos automáticos por correo electrónico.', 'aura-suite'); ?>
                    </p>

                    <div class="aura-field-group cols-2">
                        <div class="aura-field">
                            <label class="aura-label" for="notification_email">
                                <?php esc_html_e('Email de Notificaciones', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Dirección de correo remitente que verán los usuarios al recibir alertas del sistema.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">
                                    <span class="dashicons dashicons-email-alt"></span>
                                </span>
                                <input type="email" id="notification_email" name="notification_email" class="aura-input input-fancy" autocomplete="off"
                                       value="<?php echo esc_attr($notification_email); ?>" required>
                            </div>
                            <span class="aura-desc"><?php esc_html_e('Email desde el cual se despacharán los avisos del sistema.', 'aura-suite'); ?></span>
                        </div>

                        <div class="aura-field">
                            <label class="aura-label" for="notification_from_name">
                                <?php esc_html_e('Nombre del Remitente', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Nombre institucional o del departamento que encabezará los correos.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">
                                    <span class="dashicons dashicons-admin-users"></span>
                                </span>
                                <input type="text" id="notification_from_name" name="notification_from_name" class="aura-input input-fancy" autocomplete="off"
                                       value="<?php echo esc_attr($notification_from_name); ?>" required>
                            </div>
                            <span class="aura-desc"><?php esc_html_e('Nombre visible como remitente en la bandeja de entrada del usuario.', 'aura-suite'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════
                 TAB: FINANZAS
                 ═══════════════════════════════════════════ -->
            <div class="aura-tab-panel" id="tab-finance">
                <!-- Aprobación Automática -->
                <div class="aura-glass-card glass-card fade-up">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
                        <h2 class="aura-section-title" style="margin:0;">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e('Aprobación Automática de Transacciones', 'aura-suite'); ?>
                        </h2>
                        <span class="badge <?php echo $auto_approval_enabled ? 'badge-emerald' : 'badge-gray'; ?>" id="aura-status-policy-badge">
                            <?php echo $auto_approval_enabled ? esc_html__('Motor Activo', 'aura-suite') : esc_html__('Motor Pausado', 'aura-suite'); ?>
                        </span>
                    </div>
                    <p class="aura-desc" style="margin-bottom:20px;">
                        <?php esc_html_e('Configura las reglas contables inteligentes para admitir ingresos directos y fijar un límite de autorización manual para egresos.', 'aura-suite'); ?>
                    </p>

                    <!-- Switch General -->
                    <div class="aura-toggle-wrap" style="margin-bottom:24px;padding:14px 18px;background:rgba(241,245,249,0.6);border:1px solid #e2e8f0;border-radius:12px;">
                        <label class="aura-toggle">
                            <input type="checkbox" id="auto_approval_enabled" name="auto_approval_enabled" value="1" <?php checked($auto_approval_enabled, true); ?>>
                            <span class="aura-toggle-slider"></span>
                        </label>
                        <div>
                            <span class="aura-label" style="font-size:14px;">
                                <?php esc_html_e('Habilitar Motor de Aprobación Automática', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Si está activo, las transacciones se evalúan al crearse o importarse. Si se desactiva, absolutamente todo queda en estado Pendiente.', 'aura-suite'); ?>">?</span>
                            </span>
                            <p class="aura-desc" style="margin:2px 0 0;"><?php esc_html_e('Activa o pausa globalmente las políticas de auto-aprobación en la suite financiera.', 'aura-suite'); ?></p>
                        </div>
                    </div>

                    <div id="aura-approval-fields" style="<?php echo !$auto_approval_enabled ? 'display:none;' : ''; ?>">
                        
                        <!-- BARRA DE CONFIGURACIONES PREDETERMINADAS (PRESETS EN 1 CLIC) -->
                        <div class="aura-presets-card" style="margin-bottom:24px;padding:16px;background:linear-gradient(135deg,rgba(37,99,235,0.04) 0%,rgba(16,185,129,0.04) 100%);border:1px solid rgba(37,99,235,0.15);border-radius:12px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span style="font-size:18px;">⚡</span>
                                    <span style="font-weight:700;font-size:13.5px;color:#1e293b;">
                                        <?php esc_html_e('Ajustes Predeterminados Recomendados (1 Clic)', 'aura-suite'); ?>
                                    </span>
                                </div>
                                <span class="aura-desc" style="font-size:12px;"><?php esc_html_e('Haz clic en un perfil para autoconfigurar las opciones recomendadas:', 'aura-suite'); ?></span>
                            </div>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <button type="button" class="aura-btn-preset <?php echo ($auto_approval_mode === 'expenses_threshold_income_auto' && (float)$auto_approval_threshold == 500) ? 'is-active' : ''; ?>" data-mode="expenses_threshold_income_auto" data-threshold="500">
                                    <span class="dashicons dashicons-star-filled" style="color:#eab308;font-size:15px;width:15px;height:15px;"></span>
                                    <strong><?php esc_html_e('Estándar Empresarial', 'aura-suite'); ?></strong>
                                    <span style="font-size:11px;opacity:.85;">(Ingresos Libres + Egresos &lt; $500)</span>
                                </button>

                                <button type="button" class="aura-btn-preset <?php echo ($auto_approval_mode === 'expenses_threshold_income_auto' && (float)$auto_approval_threshold == 1500) ? 'is-active' : ''; ?>" data-mode="expenses_threshold_income_auto" data-threshold="1500">
                                    <span class="dashicons dashicons-chart-line" style="color:#059669;font-size:15px;width:15px;height:15px;"></span>
                                    <strong><?php esc_html_e('Operación Ágil', 'aura-suite'); ?></strong>
                                    <span style="font-size:11px;opacity:.85;">(Ingresos Libres + Egresos &lt; $1,500)</span>
                                </button>

                                <button type="button" class="aura-btn-preset <?php echo ($auto_approval_mode === 'both_threshold' && (float)$auto_approval_threshold == 100) ? 'is-active' : ''; ?>" data-mode="both_threshold" data-threshold="100">
                                    <span class="dashicons dashicons-shield" style="color:#2563eb;font-size:15px;width:15px;height:15px;"></span>
                                    <strong><?php esc_html_e('Auditoría Estricta', 'aura-suite'); ?></strong>
                                    <span style="font-size:11px;opacity:.85;">(Umbral $100 Ambos)</span>
                                </button>
                            </div>
                        </div>

                        <!-- FILA: UMBRAL DE MONTO + SELECTOR DE POLÍTICA -->
                        <div class="aura-field-group cols-2" style="margin-bottom:20px;">
                            <!-- Campo de Umbral -->
                            <div class="aura-field">
                                <label class="aura-label" for="auto_approval_threshold">
                                    <?php esc_html_e('Monto Umbral de Aprobación ($)', 'aura-suite'); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Monto límite para la regla. Los egresos menores a este valor se aprueban automáticamente; montos iguales o mayores quedan pendientes de revisión.', 'aura-suite'); ?>">?</span>
                                </label>
                                <div class="aura-input-group aura-amount-group">
                                    <span class="aura-input-group-text currency-symbol">$</span>
                                    <input type="number" id="auto_approval_threshold" name="auto_approval_threshold" class="aura-input input-fancy"
                                           value="<?php echo esc_attr($auto_approval_threshold); ?>"
                                           step="0.01" min="0" placeholder="500.00">
                                </div>
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px;flex-wrap:wrap;gap:6px;">
                                    <span class="aura-desc"><?php esc_html_e('Menores a este valor se aprueban automáticamente.', 'aura-suite'); ?></span>
                                    <span class="aura-threshold-examples">
                                        <a href="#" data-amount="100">$100</a> |
                                        <a href="#" data-amount="250">$250</a> |
                                        <a href="#" data-amount="500">$500</a> |
                                        <a href="#" data-amount="1000">$1,000</a> |
                                        <a href="#" data-amount="2000">$2,000</a>
                                    </span>
                                </div>
                            </div>

                            <!-- Selector de Política de Aprobación -->
                            <div class="aura-field">
                                <label class="aura-label">
                                    <?php esc_html_e('Política de Aprobación por Tipo:', 'aura-suite'); ?>
                                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Selecciona la regla deseada. Para que los ingresos no requieran aprobación y los egresos sí requieran revisión si superan el umbral, elige la primera opción.', 'aura-suite'); ?>">?</span>
                                </label>
                                
                                <div class="aura-policy-options" style="display:flex;flex-direction:column;gap:10px;">
                                    <!-- Opción 1: Ingresos libres + Egresos por umbral (Recomendada) -->
                                    <label class="aura-policy-card <?php echo $auto_approval_mode === 'expenses_threshold_income_auto' ? 'is-selected' : ''; ?>">
                                        <input type="radio" name="auto_approval_mode" value="expenses_threshold_income_auto" <?php checked($auto_approval_mode, 'expenses_threshold_income_auto'); ?>>
                                        <div class="aura-policy-card__content">
                                            <div class="aura-policy-card__header">
                                                <strong>🟢 <?php esc_html_e('Ingresos Libres + Egresos por Umbral', 'aura-suite'); ?></strong>
                                                <span class="aura-badge aura-badge-green" style="font-size:10.5px;"><?php esc_html_e('Recomendado', 'aura-suite'); ?></span>
                                            </div>
                                            <p class="aura-policy-card__desc">
                                                <?php esc_html_e('Todo ingreso se aprueba de inmediato. Los egresos menores al umbral se aprueban solos; los que superen el umbral quedan pendientes.', 'aura-suite'); ?>
                                            </p>
                                        </div>
                                    </label>

                                    <!-- Opción 2: Ambos tipos con umbral -->
                                    <label class="aura-policy-card <?php echo $auto_approval_mode === 'both_threshold' ? 'is-selected' : ''; ?>">
                                        <input type="radio" name="auto_approval_mode" value="both_threshold" <?php checked($auto_approval_mode, 'both_threshold'); ?>>
                                        <div class="aura-policy-card__content">
                                            <div class="aura-policy-card__header">
                                                <strong>⚖️ <?php esc_html_e('Umbral Común para Ambos (Ingresos y Egresos)', 'aura-suite'); ?></strong>
                                            </div>
                                            <p class="aura-policy-card__desc">
                                                <?php esc_html_e('Cualquier transacción (ingreso o egreso) que supere el umbral quedará pendiente de aprobación manual.', 'aura-suite'); ?>
                                            </p>
                                        </div>
                                    </label>

                                    <!-- Opción 3: Solo egresos se auto-aprueban; ingresos manuales -->
                                    <label class="aura-policy-card <?php echo $auto_approval_mode === 'expenses_only_threshold' ? 'is-selected' : ''; ?>">
                                        <input type="radio" name="auto_approval_mode" value="expenses_only_threshold" <?php checked($auto_approval_mode, 'expenses_only_threshold'); ?>>
                                        <div class="aura-policy-card__content">
                                            <div class="aura-policy-card__header">
                                                <strong>🔒 <?php esc_html_e('Solo Egresos por Umbral (Ingresos siempre manuales)', 'aura-suite'); ?></strong>
                                            </div>
                                            <p class="aura-policy-card__desc">
                                                <?php esc_html_e('Los ingresos siempre requieren aprobación manual. Los egresos menores al umbral se aprueban automáticamente.', 'aura-suite'); ?>
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- CAJA DE RESUMEN EN VIVO (SIMULADOR DE REGLA) -->
                        <div class="aura-rule-summary-box" id="aura-live-rule-summary" style="margin-top:14px;padding:16px 20px;background:rgba(248,250,252,0.85);border:1px solid #cbd5e1;border-radius:12px;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                                <span class="dashicons dashicons-visibility" style="color:var(--aura-primary);font-size:18px;width:18px;height:18px;"></span>
                                <strong style="font-size:13.5px;color:#1e293b;"><?php esc_html_e('Resumen del Comportamiento en Tiempo Real:', 'aura-suite'); ?></strong>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:12px;font-size:13px;line-height:1.6;">
                                <div style="padding:10px 14px;background:#ffffff;border-radius:8px;border:1px solid #e2e8f0;">
                                    <div style="font-weight:700;color:#059669;margin-bottom:3px;">💰 <?php esc_html_e('Tratamiento de INGRESOS:', 'aura-suite'); ?></div>
                                    <span id="summary-income-text"><?php esc_html_e('Aprobación inmediata automática sin importar el monto.', 'aura-suite'); ?></span>
                                </div>
                                <div style="padding:10px 14px;background:#ffffff;border-radius:8px;border:1px solid #e2e8f0;">
                                    <div style="font-weight:700;color:#dc2626;margin-bottom:3px;">💸 <?php esc_html_e('Tratamiento de EGRESOS:', 'aura-suite'); ?></div>
                                    <span id="summary-expense-text"><?php printf(esc_html__('Menores a $%s se aprueban solos. De $%s o más quedan PENDIENTES.', 'aura-suite'), number_format((float)$auto_approval_threshold, 2), number_format((float)$auto_approval_threshold, 2)); ?></span>
                                </div>
                                <div style="padding:10px 14px;background:#ffffff;border-radius:8px;border:1px solid #e2e8f0;">
                                    <div style="font-weight:700;color:#4f46e5;margin-bottom:3px;">🛡️ <?php esc_html_e('Categorías con Excepción:', 'aura-suite'); ?></div>
                                    <span><?php esc_html_e('Siempre requieren aprobación manual, sin importar el monto.', 'aura-suite'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- ApexChart de estadísticas -->
                        <?php if ($auto_approval_enabled && $auto_approval_threshold > 0): ?>
                        <div style="margin-top:24px;display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
                            <div>
                                <label class="aura-label" style="margin-bottom:14px;"><?php esc_html_e('Estadísticas del Mes', 'aura-suite'); ?></label>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <div class="aura-stat-card" style="background:rgba(16,185,129,.06);border-color:rgba(16,185,129,.25);">
                                        <div class="aura-stat-val" style="color:#059669;"><?php echo (int)$stats_chart['auto_approved']; ?></div>
                                        <div class="aura-stat-label"><?php esc_html_e('Auto-aprobadas', 'aura-suite'); ?></div>
                                        <span class="aura-badge aura-badge-green" style="margin-top:6px;"><?php echo esc_html($stats_chart['auto_approved_percent']); ?>%</span>
                                    </div>
                                    <div class="aura-stat-card" style="background:rgba(59,130,246,.06);border-color:rgba(59,130,246,.25);">
                                        <div class="aura-stat-val" style="color:#2563eb;"><?php echo (int)$stats_chart['manual_approved']; ?></div>
                                        <div class="aura-stat-label"><?php esc_html_e('Aprobación manual', 'aura-suite'); ?></div>
                                        <span class="aura-badge aura-badge-blue" style="margin-top:6px;"><?php echo esc_html($stats_chart['manual_approved_percent']); ?>%</span>
                                    </div>
                                    <div class="aura-stat-card" style="background:rgba(239,68,68,.06);border-color:rgba(239,68,68,.25);">
                                        <div class="aura-stat-val" style="color:#dc2626;"><?php echo (int)$stats_chart['rejected']; ?></div>
                                        <div class="aura-stat-label"><?php esc_html_e('Rechazadas', 'aura-suite'); ?></div>
                                        <span class="aura-badge aura-badge-red" style="margin-top:6px;"><?php echo esc_html($stats_chart['rejected_percent']); ?>%</span>
                                    </div>
                                    <?php if ($stats_chart['time_saved_hours'] > 0): ?>
                                     <div class="aura-stat-card" style="background:rgba(99,102,241,.06);border-color:rgba(99,102,241,.25);">
                                        <div class="aura-stat-val" style="color:#4f46e5;">⏱ <?php echo esc_html($stats_chart['time_saved_hours']); ?>h</div>
                                        <div class="aura-stat-label"><?php esc_html_e('Tiempo ahorrado', 'aura-suite'); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label class="aura-label" style="margin-bottom:14px;"><?php esc_html_e('Distribución de Aprobaciones', 'aura-suite'); ?></label>
                                <div id="aura-approval-chart" style="height:200px;"></div>
                            </div>
                        </div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function(){
                            if(typeof ApexCharts !== 'undefined'){
                                var isDark = document.documentElement.getAttribute('data-wp-dark-mode-scheme') === 'dark' || document.body.classList.contains('wp-dark-mode-active');
                                var options = {
                                    chart: { type: 'donut', height: 200, fontFamily: 'Inter, sans-serif',
                                             toolbar: { show: false }, animations: { enabled: true, speed: 500 } },
                                    series: [<?php echo (int)$stats_chart['auto_approved']; ?>, <?php echo (int)$stats_chart['manual_approved']; ?>, <?php echo (int)$stats_chart['rejected']; ?>],
                                    labels: ['<?php echo esc_js(__('Auto-aprobadas','aura-suite')); ?>', '<?php echo esc_js(__('Aprobación Manual','aura-suite')); ?>', '<?php echo esc_js(__('Rechazadas','aura-suite')); ?>'],
                                    colors: ['#10b981','#3b82f6','#ef4444'],
                                    legend: { position: 'bottom', fontSize: '12px', labels: { colors: isDark ? '#94a3b8' : '#64748b' } },
                                    dataLabels: { enabled: true, formatter: function(val){ return Math.round(val) + '%'; } },
                                    plotOptions: { pie: { donut: { size: '60%' } } },
                                    stroke: { width: 2, colors: [isDark ? '#1e293b' : '#ffffff'] }
                                };
                                var chart = new ApexCharts(document.querySelector('#aura-approval-chart'), options);
                                chart.render();
                            }
                        });
                        </script>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Excepciones de Aprobación Automática -->
                <div class="aura-glass-card">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:10px;">
                        <h2 class="aura-section-title" style="margin:0;">
                            <span class="dashicons dashicons-shield"></span>
                            <?php esc_html_e('Excepciones de Aprobación Automática', 'aura-suite'); ?>
                        </h2>
                        <span class="aura-badge aura-badge-blue" id="aura-exceptions-badge-count">
                            <span class="dashicons dashicons-yes" style="font-size:14px;width:14px;height:14px;"></span>
                            <span id="aura-exceptions-num">0</span> <?php esc_html_e('excepciones activas', 'aura-suite'); ?>
                        </span>
                    </div>
                    <p class="aura-desc" style="margin-bottom:16px;">
                        <?php esc_html_e('Las categorías marcadas requerirán SIEMPRE revisión y aprobación manual por un supervisor contable, ignorando las reglas de umbral de monto.', 'aura-suite'); ?>
                    </p>

                    <!-- Barra de Búsqueda y Acciones Rápidas -->
                    <div class="aura-cat-filter-bar" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:18px;padding:12px 16px;background:rgba(248,250,252,0.85);border:1px solid #e2e8f0;border-radius:10px;">
                        <div style="flex:1;min-width:240px;position:relative;">
                            <span class="dashicons dashicons-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:16px;"></span>
                            <input type="text" id="aura-cat-search-input" class="aura-input" style="padding-left:32px;font-size:13px;" placeholder="<?php esc_attr_e('🔍 Buscar categoría en tiempo real (ej: Combustible, Nómina, Donación)...', 'aura-suite'); ?>">
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" class="aura-btn-quick" id="btn-select-all-income">
                                💚 <?php esc_html_e('Marcar Todos Ingresos', 'aura-suite'); ?>
                            </button>
                            <button type="button" class="aura-btn-quick" id="btn-select-all-expense">
                                🔴 <?php esc_html_e('Marcar Todos Egresos', 'aura-suite'); ?>
                            </button>
                            <button type="button" class="aura-btn-quick" id="btn-deselect-all-cats" style="color:#64748b;">
                                🧹 <?php esc_html_e('Desmarcar Todo', 'aura-suite'); ?>
                            </button>
                        </div>
                    </div>

                    <?php
                    global $wpdb;
                    $table      = $wpdb->prefix . 'aura_finance_categories';
                    $categories = $wpdb->get_results("SELECT id, name, type, always_require_approval FROM $table WHERE is_active = 1 ORDER BY type, name");
                    if ($categories):
                        $income_cats  = array_filter($categories, fn($c) => $c->type === 'income');
                        $expense_cats = array_filter($categories, fn($c) => $c->type !== 'income');
                    ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" id="aura-categories-container">
                        <!-- Columna Ingresos -->
                        <div>
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                                <label class="aura-label" style="color:#059669;margin:0;font-size:13.5px;font-weight:700;">
                                    💚 <?php esc_html_e('Ingresos', 'aura-suite'); ?> (<?php echo count($income_cats); ?>)
                                </label>
                                <a href="#" id="toggle-income-select" style="font-size:12px;color:#059669;text-decoration:none;font-weight:600;"><?php esc_html_e('Invertir', 'aura-suite'); ?></a>
                            </div>
                            <div class="aura-category-exception-box aura-categories-scroll-box" id="aura-income-cats-box">
                                <?php foreach ($income_cats as $cat): $checked = !empty($cat->always_require_approval) ? 'checked' : ''; ?>
                                <label class="aura-category-exception-item <?php echo $checked ? 'is-checked' : ''; ?>" data-name="<?php echo esc_attr(strtolower($cat->name)); ?>">
                                    <input type="checkbox" class="category-exception cat-type-income" data-category-id="<?php echo (int)$cat->id; ?>" <?php echo $checked; ?>>
                                    <span class="cat-title"><?php echo esc_html($cat->name); ?></span>
                                    <span class="aura-badge aura-badge-blue badge-forced" style="font-size:10px;<?php echo $checked ? '' : 'display:none;'; ?>">
                                        <?php esc_html_e('Manual Obligatorio', 'aura-suite'); ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Columna Egresos -->
                        <div>
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                                <label class="aura-label" style="color:#dc2626;margin:0;font-size:13.5px;font-weight:700;">
                                    🔴 <?php esc_html_e('Egresos', 'aura-suite'); ?> (<?php echo count($expense_cats); ?>)
                                </label>
                                <a href="#" id="toggle-expense-select" style="font-size:12px;color:#dc2626;text-decoration:none;font-weight:600;"><?php esc_html_e('Invertir', 'aura-suite'); ?></a>
                            </div>
                            <div class="aura-category-exception-box aura-categories-scroll-box" id="aura-expense-cats-box">
                                <?php foreach ($expense_cats as $cat): $checked = !empty($cat->always_require_approval) ? 'checked' : ''; ?>
                                <label class="aura-category-exception-item <?php echo $checked ? 'is-checked' : ''; ?>" data-name="<?php echo esc_attr(strtolower($cat->name)); ?>">
                                    <input type="checkbox" class="category-exception cat-type-expense" data-category-id="<?php echo (int)$cat->id; ?>" <?php echo $checked; ?>>
                                    <span class="cat-title"><?php echo esc_html($cat->name); ?></span>
                                    <span class="aura-badge aura-badge-red badge-forced" style="font-size:10px;<?php echo $checked ? '' : 'display:none;'; ?>">
                                        <?php esc_html_e('Manual Obligatorio', 'aura-suite'); ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top:20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                        <button type="button" id="save-category-exceptions" class="aura-btn-primary btn-shimmer btn-indigo" style="padding:10px 20px;font-weight:600;">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e('Guardar Lista de Excepciones', 'aura-suite'); ?>
                        </button>
                        <span id="category-save-status"></span>
                    </div>

                    <div class="aura-info-box info" style="margin-top:20px;">
                        <p style="font-size:13.5px;font-weight:700;margin:0 0 8px;">ℹ️ <?php esc_html_e('Casos de Uso Típicos:', 'aura-suite'); ?></p>
                        <ul style="margin:0;padding-left:18px;font-size:13px;line-height:1.75;">
                            <li><strong><?php esc_html_e('Empresa / Negocio Comercial:', 'aura-suite'); ?></strong> <?php esc_html_e('Ingresos Libres + Egresos con umbral $500. Se cobra rápido sin demoras, y gastos superiores a $500 requieren firma del administrador.', 'aura-suite'); ?></li>
                            <li><strong><?php esc_html_e('Asociación / ONG con Auditoría:', 'aura-suite'); ?></strong> <?php esc_html_e('Umbral común de $200 tanto para ingresos como egresos para asegurar comprobantes en todo movimiento relevante.', 'aura-suite'); ?></li>
                            <li><strong><?php esc_html_e('Nómina y Compras Mayores:', 'aura-suite'); ?></strong> <?php esc_html_e('Marca "Nómina" o "Honorarios" en las excepciones de arriba para que siempre requieran aprobación formal sin importar la regla.', 'aura-suite'); ?></li>
                        </ul>
                    </div>
                </div>

                <!-- Categorías Financieras -->
                <div class="aura-glass-card glass-card fade-up delay-1">
                    <h2 class="aura-section-title">
                        <span class="dashicons dashicons-category"></span>
                        <?php esc_html_e('Categorías Financieras', 'aura-suite'); ?>
                    </h2>
                    <p class="aura-desc" style="margin-bottom:18px;">
                        <?php esc_html_e('Resumen de categorías activas en el árbol contable y herramientas de mantenimiento.', 'aura-suite'); ?>
                    </p>

                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;">
                        <div class="aura-stat-card stat-glass-card hover-lift" style="padding:16px;">
                            <div class="aura-stat-val" style="color:var(--aura-primary);font-size:22px;font-weight:700;"><?php echo esc_html($cat_stats['total']); ?></div>
                            <div class="aura-stat-label"><?php esc_html_e('Total categorías', 'aura-suite'); ?></div>
                        </div>
                        <div class="aura-stat-card stat-glass-card hover-lift" style="padding:16px;background:rgba(16,185,129,.06);border-color:rgba(16,185,129,.25);">
                            <div class="aura-stat-val" style="color:#059669;font-size:22px;font-weight:700;"><?php echo esc_html($cat_stats['income']); ?></div>
                            <div class="aura-stat-label"><?php esc_html_e('Ingresos', 'aura-suite'); ?></div>
                        </div>
                        <div class="aura-stat-card stat-glass-card hover-lift" style="padding:16px;background:rgba(239,68,68,.06);border-color:rgba(239,68,68,.25);">
                            <div class="aura-stat-val" style="color:#dc2626;font-size:22px;font-weight:700;"><?php echo esc_html($cat_stats['expense']); ?></div>
                            <div class="aura-stat-label"><?php esc_html_e('Egresos', 'aura-suite'); ?></div>
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <button type="button" id="aura-reinstall-categories" class="aura-btn-secondary btn-glass">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Reinstalar Categorías Predeterminadas', 'aura-suite'); ?>
                        </button>
                        <span class="badge badge-gray"><?php esc_html_e('Versión catálogo:', 'aura-suite'); ?> v<?php echo esc_html($cat_version); ?></span>
                    </div>
                    <p class="aura-desc" style="margin-top:10px;"><?php esc_html_e('Las categorías y subcategorías personalizadas creadas por tu organización no se eliminarán.', 'aura-suite'); ?></p>
                    <div id="reinstall-result" style="margin-top:10px;"></div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════
                 TAB: ELECTRICIDAD
                 ═══════════════════════════════════════════ -->
            <div class="aura-tab-panel" id="tab-electricity">
                <div class="aura-glass-card glass-card fade-up">
                    <h2 class="aura-section-title">
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php esc_html_e('Configuración de Electricidad y Consumo', 'aura-suite'); ?>
                    </h2>
                    <p class="aura-desc" style="margin-bottom:20px;">
                        <?php esc_html_e('Parámetros de cálculo y alertas automáticas para monitoreo de medidores y consumo energético.', 'aura-suite'); ?>
                    </p>

                    <div class="aura-field-group cols-2">
                        <div class="aura-field">
                            <label class="aura-label" for="electric_threshold">
                                <?php esc_html_e('Umbral de Alerta (kWh/día)', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Límite diario de consumo a partir del cual el sistema disparará una notificación de alerta.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">kWh/d</span>
                                <input type="number" id="electric_threshold" name="electric_threshold" class="aura-input input-fancy"
                                       value="<?php echo esc_attr($electric_threshold); ?>" step="0.01" min="0" required>
                            </div>
                            <span class="aura-desc"><?php esc_html_e('Se enviará alerta cuando el consumo diario supere este límite.', 'aura-suite'); ?></span>
                        </div>

                        <div class="aura-field">
                            <label class="aura-label" for="electric_cost_kwh">
                                <?php esc_html_e('Costo por kWh ($)', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Precio por kilovatio hora utilizado para proyectar los costos financieros en el panel.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group aura-amount-group">
                                <span class="aura-input-group-text currency-symbol">$</span>
                                <input type="number" id="electric_cost_kwh" name="electric_cost_kwh" class="aura-input input-fancy"
                                       value="<?php echo esc_attr($electric_cost_kwh); ?>" step="0.001" min="0" required>
                            </div>
                            <span class="aura-desc"><?php esc_html_e('Tarifa de costo por kWh para cálculo de gasto proyectado.', 'aura-suite'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="aura-glass-card glass-card fade-up delay-1">
                    <h2 class="aura-section-title">
                        <span class="dashicons dashicons-rest-api"></span>
                        <?php esc_html_e('API IoT para Lecturas Remotas', 'aura-suite'); ?>
                    </h2>
                    <p class="aura-desc" style="margin-bottom:16px;">
                        <?php esc_html_e('Permite a medidores inteligentes o microcontroladores (ESP32/Arduino) registrar lecturas vía REST API.', 'aura-suite'); ?>
                    </p>

                    <div class="aura-field">
                        <label class="aura-label"><?php esc_html_e('API Key del Sistema', 'aura-suite'); ?></label>
                        <div style="display:flex;align-items:center;gap:10px;margin-top:4px;flex-wrap:wrap;">
                            <code style="padding:10px 14px;font-size:13px;border-radius:8px;border:1px solid #cbd5e1;display:inline-block;letter-spacing:0.04em;">
                                <?php echo esc_html($api_key); ?>
                            </code>
                            <button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($api_key); ?>').then(()=>{this.textContent='✅ Copiado!';setTimeout(()=>{this.textContent='📋 Copiar';},2000);})" class="aura-btn-secondary btn-glass">📋 <?php esc_html_e('Copiar','aura-suite'); ?></button>
                        </div>
                    </div>

                    <div class="aura-info-box neutral" style="margin-top:18px;">
                        <p style="margin:0 0 6px;font-size:13px;font-weight:700;"><?php esc_html_e('Endpoint POST para dispositivos IoT:', 'aura-suite'); ?></p>
                        <code><?php echo esc_html(rest_url('aura/v1/electricity/reading')); ?></code>
                        <p style="margin:8px 0 0;font-size:12px;"><?php esc_html_e('Cuerpo JSON de ejemplo:', 'aura-suite'); ?> <code>{"reading_kwh": 450.5, "api_key": "..."}</code></p>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════
                 TAB: WHATSAPP
                 ═══════════════════════════════════════════ -->
            <div class="aura-tab-panel" id="tab-whatsapp">
                <div class="aura-glass-card glass-card fade-up">
                    <h2 class="aura-section-title" style="color:#075e54;">
                        <span class="dashicons dashicons-smartphone" style="color:#25d366;"></span>
                        <?php esc_html_e('WhatsApp — Configuración Global', 'aura-suite'); ?>
                    </h2>
                    <p class="aura-desc" style="margin-bottom:20px;">
                        <?php esc_html_e('Configura el proveedor de WhatsApp para enviar notificaciones y alertas desde cualquier módulo de Aura Suite.', 'aura-suite'); ?>
                    </p>

                    <div id="js-global-wa-msg" style="display:none;margin-bottom:12px;"></div>

                    <div class="aura-toggle-wrap" style="margin-bottom:20px;">
                        <label class="aura-toggle">
                            <input type="checkbox" name="whatsapp_enabled" value="1" <?php checked(get_option('aura_whatsapp_enabled', '0'), '1'); ?>>
                            <span class="aura-toggle-slider" style="<?php echo get_option('aura_whatsapp_enabled','0')==='1' ? 'background-color:#25d366;' : ''; ?>"></span>
                        </label>
                        <div>
                            <span class="aura-label">
                                <?php esc_html_e('Activar envío de WhatsApp', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Habilita la salida de mensajes automáticos por WhatsApp a los números de contacto.', 'aura-suite'); ?>">?</span>
                            </span>
                            <p class="aura-desc"><?php esc_html_e('Activa las notificaciones instantáneas vía WhatsApp.', 'aura-suite'); ?></p>
                        </div>
                    </div>

                    <div class="aura-field-group">
                        <div class="aura-field">
                            <label class="aura-label" for="gset-wa-provider">
                                <?php esc_html_e('Proveedor de Servicio', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Selecciona la pasarela con la que tienes vinculada tu línea de WhatsApp.', 'aura-suite'); ?>">?</span>
                            </label>
                            <select name="whatsapp_provider" id="gset-wa-provider" class="aura-input input-fancy">
                                <option value="green_api" <?php selected(get_option('aura_whatsapp_provider','green_api'),'green_api'); ?>>✅ GREEN-API (recomendado — bajo costo / plan gratuito)</option>
                                <option value="callmebot" <?php selected(get_option('aura_whatsapp_provider','green_api'),'callmebot'); ?>>CallMeBot (gratuito personal)</option>
                                <option value="twilio"    <?php selected(get_option('aura_whatsapp_provider','green_api'),'twilio'); ?>>Twilio (WhatsApp Business API)</option>
                                <option value="meta"      <?php selected(get_option('aura_whatsapp_provider','green_api'),'meta'); ?>>Meta / WhatsApp Cloud API Oficial</option>
                            </select>
                            <span class="aura-desc gwa-desc-green_api">
                                <?php printf(__('GREEN-API: plan gratuito (3 chats) o $12/mes ilimitado. Usa tu propio número. <a href="%s" target="_blank" rel="noopener" style="color:var(--aura-primary);font-weight:600;">Regístrate gratis →</a>', 'aura-suite'), 'https://console.green-api.com'); ?>
                            </span>
                            <span class="aura-desc gwa-desc-callmebot" style="display:none;"><?php printf(__('CallMeBot: gratuito. El destinatario debe enviar un mensaje previo de activación. <a href="%s" target="_blank" style="color:var(--aura-primary);font-weight:600;">Instrucciones</a>.', 'aura-suite'), 'https://www.callmebot.com/blog/free-api-whatsapp-messages/'); ?></span>
                            <span class="aura-desc gwa-desc-twilio" style="display:none;"><?php esc_html_e('Requiere una cuenta Twilio con número verificado en WhatsApp Business.', 'aura-suite'); ?></span>
                            <span class="aura-desc gwa-desc-meta" style="display:none;"><?php esc_html_e('Requiere aplicación en Meta for Developers con permiso whatsapp_business_messaging.', 'aura-suite'); ?></span>
                        </div>

                        <div class="aura-field">
                            <label class="aura-label" for="gset-wa-token">
                                <?php esc_html_e('API Token / Secret Key', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Clave secreta provista por el servicio para autenticar las peticiones de envío.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">
                                    <span class="dashicons dashicons-lock"></span>
                                </span>
                                <input type="password" name="whatsapp_api_token" id="gset-wa-token" class="aura-input input-fancy"
                                       autocomplete="new-password"
                                       value="<?php echo esc_attr(get_option('aura_whatsapp_api_token','')); ?>"
                                       placeholder="<?php esc_attr_e('API Token Instance / Auth Token / Bearer Key', 'aura-suite'); ?>">
                            </div>
                            <span class="aura-desc gwa-tokendesc-green_api"><?php esc_html_e('GREEN-API: Ingresa el API Token Instance de tu consola.', 'aura-suite'); ?></span>
                            <span class="aura-desc gwa-tokendesc-callmebot" style="display:none;"><?php esc_html_e('CallMeBot: Ingresa el apikey recibido tras activar el servicio.', 'aura-suite'); ?></span>
                            <span class="aura-desc"><?php esc_html_e('Déjalo en blanco para conservar la clave guardada.', 'aura-suite'); ?></span>
                        </div>

                        <div class="aura-field gwa-row-from">
                            <label class="aura-label" for="gset-wa-from">
                                <?php esc_html_e('Número origen (Remitente)', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Número internacional emisor con código de país (ej. +521...).', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">+</span>
                                <input type="text" name="whatsapp_from" id="gset-wa-from" class="aura-input input-fancy"
                                       value="<?php echo esc_attr(get_option('aura_whatsapp_from','')); ?>"
                                       placeholder="521234567890">
                            </div>
                            <span class="aura-desc gwa-desc-from-green_api"><?php esc_html_e('GREEN-API: no requerido en este campo; usa el campo Instance ID abajo.', 'aura-suite'); ?></span>
                            <span class="aura-desc gwa-desc-from-twilio" style="display:none;"><?php esc_html_e('Número Twilio habilitado para WhatsApp, ej. +14155238886', 'aura-suite'); ?></span>
                        </div>

                        <div class="aura-field gwa-row-green_api">
                            <label class="aura-label" for="gset-wa-green-instance">
                                <?php esc_html_e('GREEN-API Instance ID', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Identificador numérico de la instancia provisto en console.green-api.com.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">#</span>
                                <input type="text" name="whatsapp_green_instance_id" id="gset-wa-green-instance" class="aura-input input-fancy"
                                       value="<?php echo esc_attr(get_option('aura_whatsapp_green_instance_id','')); ?>"
                                       placeholder="1101234567">
                            </div>
                            <span class="aura-desc"><?php printf(__('ID de tu instancia en <a href="%s" target="_blank" rel="noopener" style="color:var(--aura-primary);font-weight:600;">console.green-api.com</a>.', 'aura-suite'), 'https://console.green-api.com'); ?></span>
                        </div>

                        <div class="aura-field gwa-row-twilio" style="display:none;">
                            <label class="aura-label" for="gset-wa-twilio-sid"><?php esc_html_e('Twilio Account SID', 'aura-suite'); ?></label>
                            <input type="text" name="whatsapp_twilio_sid" id="gset-wa-twilio-sid" class="aura-input input-fancy"
                                   value="<?php echo esc_attr(get_option('aura_whatsapp_twilio_sid','')); ?>"
                                   placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                        </div>

                        <div class="aura-field gwa-row-meta" style="display:none;">
                            <label class="aura-label" for="gset-wa-meta-phone"><?php esc_html_e('Meta Phone Number ID', 'aura-suite'); ?></label>
                            <input type="text" name="whatsapp_meta_phone_id" id="gset-wa-meta-phone" class="aura-input input-fancy"
                                   value="<?php echo esc_attr(get_option('aura_whatsapp_meta_phone_id','')); ?>"
                                   placeholder="123456789012345">
                        </div>

                        <div class="aura-field">
                            <label class="aura-label" for="gset-wa-signature">
                                <?php esc_html_e('Firma del mensaje', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Firma al pie de cada mensaje enviado automáticamente.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">
                                    <span class="dashicons dashicons-edit"></span>
                                </span>
                                <input type="text" name="whatsapp_signature" id="gset-wa-signature" class="aura-input input-fancy"
                                       value="<?php echo esc_attr(get_option('aura_whatsapp_signature', aura_get_org_name())); ?>"
                                       placeholder="<?php echo esc_attr(aura_get_org_name()); ?>">
                            </div>
                            <span class="aura-desc"><?php esc_html_e('Texto al final de cada mensaje emitido por el sistema.', 'aura-suite'); ?></span>
                        </div>

                        <!-- Prueba de Envío -->
                        <div class="aura-field" style="margin-top:10px;padding-top:16px;border-top:1px dashed #cbd5e1;">
                            <label class="aura-label"><?php esc_html_e('Prueba de Envío', 'aura-suite'); ?></label>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                <div class="aura-input-group" style="max-width:260px;">
                                    <span class="aura-input-group-text">
                                        <span class="dashicons dashicons-phone"></span>
                                    </span>
                                    <input type="text" id="gset-wa-test-phone" class="aura-input input-fancy" placeholder="+521234567890">
                                </div>
                                <button type="button" class="aura-btn-primary btn-shimmer btn-emerald" id="js-btn-global-wa-test">
                                    <span class="dashicons dashicons-smartphone"></span>
                                    <?php esc_html_e('Enviar prueba', 'aura-suite'); ?>
                                </button>
                            </div>
                            <span class="aura-desc"><?php esc_html_e('Guarda los cambios primero, luego ingresa un número de prueba y envía.', 'aura-suite'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════
                 TAB: GOOGLE DRIVE
                 ═══════════════════════════════════════════ -->
            <div class="aura-tab-panel" id="tab-gdrive">
                <div class="aura-glass-card glass-card fade-up">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px;margin-bottom:12px;">
                        <h2 class="aura-section-title" style="color:#2563eb;margin:0;">
                            <span class="dashicons dashicons-cloud"></span>
                            <?php esc_html_e('Google Drive — Almacenamiento en la Nube', 'aura-suite'); ?>
                            <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Permite archivar comprobantes de transacciones, facturas y evidencias en carpetas organizadas de Google Drive.', 'aura-suite'); ?>">?</span>
                        </h2>
                        <button type="button" id="js-btn-global-gdrive-test" class="aura-btn-secondary btn-glass" style="border-color:#3b82f6;color:#2563eb;">
                            <span class="dashicons dashicons-cloud"></span>
                            <?php esc_html_e('Probar Conexión con Google Drive', 'aura-suite'); ?>
                        </button>
                    </div>
                    <p class="aura-desc" style="margin-bottom:20px;">
                        <?php esc_html_e('Almacena comprobantes de pago, facturas e imágenes de inventario en carpetas organizadas (compatible con Mi Unidad y Unidades Compartidas de Google Workspace).', 'aura-suite'); ?>
                    </p>

                    <div id="js-global-gdrive-msg" style="display:none;margin-bottom:16px;"></div>

                    <?php
                    $gdrive_type_val = get_option('aura_gdrive_type', 'my_drive');
                    $stored_json = get_option('aura_gdrive_credentials_json', get_option('aura_finance_gdrive_credentials_json', ''));
                    $stored_email = '';
                    if (!empty($stored_json)) {
                        $parsed_sa = json_decode($stored_json, true);
                        if (is_array($parsed_sa) && !empty($parsed_sa['client_email'])) {
                            $stored_email = $parsed_sa['client_email'];
                        }
                    }
                    ?>

                    <!-- Selector de Ubicación: Mi Unidad vs Unidad Compartida -->
                    <div class="aura-field" style="margin-bottom:22px;">
                        <label class="aura-label" style="font-size:14px;font-weight:700;margin-bottom:10px;">
                            <?php esc_html_e('Tipo de Almacenamiento en Google Drive', 'aura-suite'); ?>
                        </label>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:14px;">
                            <label class="aura-storage-type-card hover-lift <?php echo $gdrive_type_val === 'my_drive' ? 'is-selected' : ''; ?>">
                                <input type="radio" name="gdrive_type" value="my_drive" <?php checked($gdrive_type_val, 'my_drive'); ?> style="margin-top:4px;">
                                <div>
                                    <div class="card-title">👤 <?php esc_html_e('Mi Unidad (Carpeta Personal o Compartida)', 'aura-suite'); ?></div>
                                    <div class="card-desc">
                                        <?php esc_html_e('Para cuentas estándar @gmail.com. Debes crear la carpeta y compartirla con la cuenta de servicio como Editor.', 'aura-suite'); ?>
                                    </div>
                                </div>
                            </label>

                            <label class="aura-storage-type-card hover-lift <?php echo $gdrive_type_val === 'shared_drive' ? 'is-selected' : ''; ?>">
                                <input type="radio" name="gdrive_type" value="shared_drive" <?php checked($gdrive_type_val, 'shared_drive'); ?> style="margin-top:4px;">
                                <div>
                                    <div class="card-title">👥 <?php esc_html_e('Unidad Compartida (Shared Drive)', 'aura-suite'); ?></div>
                                    <div class="card-desc">
                                        <?php esc_html_e('Para organizaciones Google Workspace. Agrega la cuenta de servicio como Administrador de Contenido de la Unidad Compartida.', 'aura-suite'); ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="aura-field-group">
                        <div class="aura-field">
                            <label class="aura-label" for="gdrive_folder_id">
                                <?php esc_html_e('ID de Carpeta Principal o Unidad Compartida', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Identificador alfanumérico visible en la URL de Google Drive.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">
                                    <span class="dashicons dashicons-category"></span>
                                </span>
                                <input type="text" name="gdrive_folder_id" id="gdrive_folder_id" class="aura-input input-fancy"
                                       value="<?php echo esc_attr(get_option('aura_gdrive_folder_id', get_option('aura_finance_gdrive_folder_id', ''))); ?>"
                                       placeholder="1A2b3C4d5E6f...">
                            </div>
                            <span class="aura-desc"><?php esc_html_e('El ID es la cadena al final de la URL en tu navegador: drive.google.com/drive/folders/ID_DE_CARPETA', 'aura-suite'); ?></span>
                        </div>

                        <div class="aura-field">
                            <label class="aura-label" for="gdrive_credentials_json">
                                <?php esc_html_e('Credenciales JSON del Service Account', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Archivo JSON descargado desde Google Cloud Console correspondiente a la Service Account.', 'aura-suite'); ?>">?</span>
                            </label>

                            <?php if (!empty($stored_email)): ?>
                            <div class="aura-info-box" style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                <div>
                                    <span>✅ <strong><?php esc_html_e('Cuenta de servicio activa:', 'aura-suite'); ?></strong></span>
                                    <code style="display:block;margin-top:3px;user-select:all;"><?php echo esc_html($stored_email); ?></code>
                                </div>
                                <button type="button" class="aura-btn-secondary btn-glass" id="btn-copy-gdrive-email" data-email="<?php echo esc_attr($stored_email); ?>">
                                    <span class="dashicons dashicons-clipboard"></span>
                                    <?php esc_html_e('Copiar correo', 'aura-suite'); ?>
                                </button>
                            </div>
                            <?php elseif (!empty($stored_json)): ?>
                            <div class="aura-info-box" style="margin-bottom:10px;">
                                <span>✅ <strong><?php esc_html_e('Credenciales configuradas actualmente.', 'aura-suite'); ?></strong></span>
                                <p class="aura-desc" style="margin-top:4px;"><?php esc_html_e('Deja el campo vacío para conservarlas.', 'aura-suite'); ?></p>
                            </div>
                            <?php endif; ?>

                            <textarea name="gdrive_credentials_json" id="gdrive_credentials_json" rows="5" class="aura-input input-fancy"
                                      placeholder='{"type": "service_account", "project_id": "...", ...}'></textarea>
                            <span class="aura-desc"><?php esc_html_e('Pega aquí el contenido del archivo JSON generado en Google Cloud Console.', 'aura-suite'); ?></span>
                        </div>
                    </div>

                    <div class="aura-info-box neutral" style="margin-top:20px;">
                        <p style="font-size:13.5px;font-weight:700;margin:0 0 8px;">🛠️ <?php esc_html_e('¿Cómo configurar Google Drive paso a paso?', 'aura-suite'); ?></p>
                        <ol style="margin:0;padding-left:18px;font-size:13px;line-height:1.75;">
                            <li><?php printf(__('Accede a tu consola en <a href="%s" target="_blank" style="color:var(--aura-primary);font-weight:600;">Google Cloud Console</a> y crea un proyecto.', 'aura-suite'), 'https://console.cloud.google.com/'); ?></li>
                            <li><?php esc_html_e('En el menú APIs y servicios → Biblioteca, habilita la Google Drive API.', 'aura-suite'); ?></li>
                            <li><?php esc_html_e('Ve a IAM y administración → Cuentas de servicio, crea una y genera una clave en formato JSON.', 'aura-suite'); ?></li>
                            <li><?php esc_html_e('Pega el JSON arriba; el sistema extraerá automáticamente el correo de la cuenta de servicio.', 'aura-suite'); ?></li>
                            <li><?php esc_html_e('Comparte tu carpeta de Google Drive con ese correo otorgándole rol de Editor (o Administrador de Contenido si es Shared Drive).', 'aura-suite'); ?></li>
                            <li><?php esc_html_e('Pega el ID de la carpeta y presiona "Probar Conexión con Google Drive" para validar.', 'aura-suite'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════
                 TAB: GOOGLE CALENDAR
                 ═══════════════════════════════════════════ -->
            <div class="aura-tab-panel" id="tab-gcal">
                <div class="aura-glass-card glass-card fade-up">
                    <h2 class="aura-section-title" style="color:#2563eb;">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e('Google Calendar — Agenda y Mantenimientos', 'aura-suite'); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Sincroniza eventos de mantenimiento y alertas de equipos en Google Calendar mediante Service Account.', 'aura-suite'); ?>">?</span>
                    </h2>
                    <p class="aura-desc" style="margin-bottom:20px;">
                        <?php esc_html_e('Sincroniza automáticamente los eventos de mantenimiento y revisiones periódicas con Google Calendar.', 'aura-suite'); ?>
                    </p>

                    <div id="js-global-gcal-msg" style="display:none;margin-bottom:12px;"></div>

                    <div class="aura-toggle-wrap" style="margin-bottom:20px;">
                        <label class="aura-toggle">
                            <input type="checkbox" name="gcal_enabled" value="1" <?php checked(get_option('aura_gcal_enabled','0'),'1'); ?>>
                            <span class="aura-toggle-slider"></span>
                        </label>
                        <div>
                            <span class="aura-label">
                                <?php esc_html_e('Activar integración con Google Calendar', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Permite crear eventos en el calendario institucional de forma desatendida.', 'aura-suite'); ?>">?</span>
                            </span>
                            <p class="aura-desc"><?php esc_html_e('Requiere una cuenta de servicio de Google Cloud con Calendar API habilitada.', 'aura-suite'); ?></p>
                        </div>
                    </div>

                    <div class="aura-field-group">
                        <div class="aura-field">
                            <label class="aura-label" for="gset-gcal-email">
                                <?php esc_html_e('Correos para compartir calendario', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Correos electrónicos que recibirán acceso o invitación al calendario generado.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">
                                    <span class="dashicons dashicons-email-alt"></span>
                                </span>
                                <input type="text" name="gcal_share_email" id="gset-gcal-email" class="aura-input input-fancy"
                                       value="<?php echo esc_attr(get_option('aura_gcal_share_email','')); ?>"
                                       placeholder="correo1@gmail.com, correo2@gmail.com">
                            </div>
                            <span class="aura-desc"><?php esc_html_e('Correos Gmail separados por coma. Recibirán invitación al calendario institucional.', 'aura-suite'); ?></span>
                        </div>

                        <div class="aura-field">
                            <label class="aura-label" for="gset-gcal-days">
                                <?php esc_html_e('Días de recordatorio previo', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Anticipación en días para las alertas antes de la fecha del evento.', 'aura-suite'); ?>">?</span>
                            </label>
                            <div class="aura-input-group">
                                <span class="aura-input-group-text">
                                    <span class="dashicons dashicons-clock"></span>
                                </span>
                                <input type="text" name="gcal_reminder_days" id="gset-gcal-days" class="aura-input input-fancy"
                                       value="<?php echo esc_attr(get_option('aura_gcal_reminder_days','15,7,3,1')); ?>"
                                       placeholder="15,7,3,1">
                            </div>
                            <span class="aura-desc"><?php esc_html_e('Días de anticipación separados por coma (ej. 15,7,3,1).', 'aura-suite'); ?></span>
                        </div>

                        <div class="aura-field">
                            <label class="aura-label" for="gset-gcal-json">
                                <?php esc_html_e('Service Account JSON', 'aura-suite'); ?>
                                <span class="aura-help-icon" data-tooltip="<?php esc_attr_e('Credenciales JSON descargadas de Google Cloud con permisos de Google Calendar API.', 'aura-suite'); ?>">?</span>
                            </label>

                            <?php if ($has_gcal_json): ?>
                            <div class="aura-info-box" style="margin-bottom:10px;">
                                <span>✅ <strong><?php esc_html_e('Credenciales guardadas activas.', 'aura-suite'); ?></strong></span>
                                <?php if (!empty($gcal_stored['client_email'])): ?>
                                <code style="display:block;margin-top:4px;user-select:all;"><?php echo esc_html($gcal_stored['client_email']); ?></code>
                                <?php endif; ?>
                                <p class="aura-desc" style="margin-top:4px;"><?php esc_html_e('Deja el campo vacío para conservar las credenciales actuales.', 'aura-suite'); ?></p>
                            </div>
                            <?php endif; ?>

                            <textarea name="gcal_service_account_json" id="gset-gcal-json" class="aura-input input-fancy" rows="5"
                                      placeholder="<?php esc_attr_e('Pega aquí el contenido del archivo .json de la cuenta de servicio...','aura-suite'); ?>"></textarea>
                            <span class="aura-desc"><?php esc_html_e('JSON completo con type: service_account, client_email, private_key, etc.', 'aura-suite'); ?></span>
                        </div>

                        <?php if ($gcal_resolved): ?>
                        <div class="aura-info-box" style="background:#ecfdf5;border-color:#a7f3d0;color:#065f46;">
                            <p style="margin:0 0 10px;font-weight:700;">✅ <?php esc_html_e('Calendario institucional vinculado en Google Calendar', 'aura-suite'); ?></p>
                            <a href="<?php echo esc_url($gcal_share_url); ?>" target="_blank" rel="noopener"
                               class="aura-btn-primary btn-shimmer btn-emerald" style="text-decoration:none;">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php esc_html_e('Agregar a mi Google Calendar', 'aura-suite'); ?>
                            </a>
                        </div>
                        <?php endif; ?>

                        <div class="aura-field" style="margin-top:10px;">
                            <button type="button" class="aura-btn-secondary btn-glass" id="js-btn-global-gcal-test">
                                <span class="dashicons dashicons-networking"></span>
                                <?php esc_html_e('Probar conexión Google Calendar', 'aura-suite'); ?>
                            </button>
                            <span class="aura-desc" style="margin-top:4px;"><?php esc_html_e('Verifica las credenciales y crea el calendario si no existe.', 'aura-suite'); ?></span>
                        </div>
                    </div>

                    <div class="aura-info-box neutral" style="margin-top:20px;">
                        <p style="font-size:13.5px;font-weight:700;margin:0 0 8px;">🛠️ <?php esc_html_e('¿Cómo obtener las credenciales de Google Calendar?', 'aura-suite'); ?></p>
                        <ol style="margin:0;padding-left:18px;font-size:13px;line-height:1.75;">
                            <li><?php printf(__('Accede a <a href="%s" target="_blank" style="color:var(--aura-primary);font-weight:600;">Google Cloud Console</a> y habilita la Google Calendar API.', 'aura-suite'), 'https://console.cloud.google.com/'); ?></li>
                            <li><?php esc_html_e('Crea una cuenta de servicio, genera una clave en formato JSON y descárgala.', 'aura-suite'); ?></li>
                            <li><?php esc_html_e('Pega el archivo JSON en el campo superior y haz clic en "Guardar Configuración".', 'aura-suite'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════
                 TAB: PORTABILIDAD Y MIGRACIÓN (.ZIP)
                 ══════════════════════════════════════════════════════ -->
            <div class="aura-tab-panel" id="tab-migration">
                <div class="aura-panel-card">
                    <div class="aura-card-header">
                        <div class="card-icon indigo"><span class="dashicons dashicons-database-export"></span></div>
                        <div class="card-title">
                            <h3><?php esc_html_e('Migración y Portabilidad Multimedia', 'aura-suite'); ?></h3>
                            <p><?php esc_html_e('Exporta o importa paquetes autónomos comprimidos (.ZIP) con base de datos e imágenes reales.', 'aura-suite'); ?></p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px;">
                            <h4 style="margin:0 0 8px; color:#1e293b;"><?php esc_html_e('¿Cómo funciona el Paquete de Portabilidad?', 'aura-suite'); ?></h4>
                            <p style="margin:0; font-size:0.88rem; color:#64748b; line-height:1.5;">
                                <?php esc_html_e('A diferencia de las exportaciones en texto plano (CSV), este asistente genera un archivo .ZIP con los datos en JSON y los archivos binarios de imágenes (logotipos de terceros, portadas de áreas y fotos de perfil de usuarios de WordPress). Al restaurarlo en tu hosting (ej. Hostinger), las imágenes se vuelven a registrar automáticamente en la Biblioteca de Medios sin generar enlaces rotos.', 'aura-suite'); ?>
                            </p>
                        </div>
                        <div style="display:flex; gap:16px; flex-wrap:wrap;">
                            <button type="button" class="button button-primary" onclick="openAuraBundleModal('export')" style="background:linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%); border:none; padding:10px 20px; font-weight:600; border-radius:8px; display:inline-flex; align-items:center; gap:8px;">
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e('Abrir Asistente de Exportación (.ZIP)', 'aura-suite'); ?>
                            </button>
                            <button type="button" class="button button-secondary" onclick="openAuraBundleModal('import')" style="padding:10px 20px; font-weight:600; border-radius:8px; display:inline-flex; align-items:center; gap:8px;">
                                <span class="dashicons dashicons-upload"></span>
                                <?php esc_html_e('Abrir Asistente de Importación (.ZIP)', 'aura-suite'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── BARRA DE GUARDADO FIJA (.aura-sticky-bar) ── -->
            <div class="aura-sticky-bar glass-frosted" id="aura-sticky-save">
                <span class="save-hint">
                    <span class="dashicons dashicons-shield"></span>
                    <?php esc_html_e('Los cambios se almacenarán de forma segura en la base de datos.', 'aura-suite'); ?>
                </span>
                <button type="submit" name="aura_save_settings" class="aura-btn-primary btn-shimmer btn-indigo">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e('Guardar Configuración', 'aura-suite'); ?>
                </button>
            </div>

        </form><!-- /form -->
    </div><!-- /content -->

    <!-- ════════════════════════════════════════════════════════════════
         FOOTER GLOBAL CANÓNICO
    ═══════════════════════════════════════════════════════════════════ -->
    <div class="adp-footer">
        <p>
            <?php
            printf(
                __('Desarrollado con ❤️ por %s &nbsp;|&nbsp; © %s AURA Business Suite', 'aura-suite'),
                '<strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.216.694.825.576C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>DiGiraldo</a></strong>',
                date('Y')
            );
            ?>
        </p>
    </div>

</div><!-- /wrap -->
</div><!-- /aura-settings-wrap -->

<!-- ══════════════════════════════════════════════════════
     JAVASCRIPT
     ══════════════════════════════════════════════════════ -->
<script>
if (typeof jQuery !== 'undefined' && !jQuery.fn.select2) {
    jQuery.fn.select2 = function() { return this; };
}
jQuery(document).ready(function ($) {

    var auraNonce = '<?php echo wp_create_nonce('save_aura_settings'); ?>';

    // ── TABS: Navegación, Sincronización de URL y Persistencia ───
    function switchSettingsTab(targetTab) {
        if (!targetTab) return;
        var panelId = targetTab.startsWith('tab-') ? targetTab : 'tab-' + targetTab;
        var tabKey  = panelId.replace('tab-', '');

        $('#aura-settings-nav .aura-tab-btn').removeClass('active');
        $('[data-tab="' + panelId + '"]').addClass('active');

        $('.aura-tab-panel').removeClass('active');
        $('#' + panelId).addClass('active');

        // Persistencia en sessionStorage
        sessionStorage.setItem('aura_settings_tab', panelId);

        // Sincronización fluida con la URL sin recargar
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tabKey);
            window.history.replaceState(null, '', url.toString());
        }
    }

    $('#aura-settings-nav').on('click', '.aura-tab-btn', function (e) {
        e.preventDefault();
        var target = $(this).data('tab');
        switchSettingsTab(target);
    });

    // Restaurar tab activa desde URL o sessionStorage
    var urlParams = new URLSearchParams(window.location.search);
    var tabFromUrl = urlParams.get('tab');
    var savedTab = tabFromUrl ? 'tab-' + tabFromUrl : sessionStorage.getItem('aura_settings_tab');
    if (savedTab === 'tab-system') savedTab = 'tab-org';
    if (savedTab && $('#' + savedTab).length) {
        switchSettingsTab(savedTab);
    }

    // Resaltar tarjeta seleccionada en selector de Google Drive
    $('input[name="gdrive_type"]').on('change', function () {
        $('.aura-storage-type-card').removeClass('is-selected');
        $(this).closest('.aura-storage-type-card').addClass('is-selected');
    });

    // ── WhatsApp: visibilidad de campos según proveedor ───────────
    function gwaUpdateProvider() {
        var p = $('#gset-wa-provider').val();
        $('.gwa-desc-green_api, .gwa-desc-callmebot, .gwa-desc-twilio, .gwa-desc-meta').hide();
        $('.gwa-desc-' + p).show();
        $('.gwa-tokendesc-green_api, .gwa-tokendesc-callmebot').hide();
        $('.gwa-tokendesc-' + p).show();
        $('.gwa-desc-from-green_api, .gwa-desc-from-callmebot, .gwa-desc-from-twilio, .gwa-desc-from-meta').hide();
        $('.gwa-desc-from-' + p).show();
        $('.gwa-row-green_api').toggle(p === 'green_api');
        $('.gwa-row-twilio').toggle(p === 'twilio');
        $('.gwa-row-meta').toggle(p === 'meta');
    }
    gwaUpdateProvider();
    $('#gset-wa-provider').on('change', gwaUpdateProvider);

    // ── WhatsApp: prueba de envío ──────────────────────────────────
    $('#js-btn-global-wa-test').on('click', function () {
        var phone = $.trim($('#gset-wa-test-phone').val());
        if (!phone) { alert('<?php echo esc_js(__('Ingresa un número de teléfono de prueba.','aura-suite')); ?>'); return; }
        var $btn = $(this).prop('disabled', true);
        var $msg = $('#js-global-wa-msg').show();
        $msg.html('<div class="aura-info-box" style="font-size:13px;">⏳ <?php echo esc_js(__('Enviando mensaje de prueba...','aura-suite')); ?></div>');
        $.post(ajaxurl, { action: 'aura_global_gcal_test_whatsapp', nonce: auraNonce, phone: phone }, function (r) {
            $msg.html(r.success
                ? '<div class="aura-info-box" style="font-size:13px;">✅ ' + r.data.message + '</div>'
                : '<div class="aura-info-box" style="background:rgba(239,68,68,.08);border-color:#ef4444;color:#dc2626;font-size:13px;">❌ ' + r.data.message + '</div>'
            );
        }).fail(function () { $msg.html('<div class="aura-info-box" style="background:rgba(239,68,68,.08);border-color:#ef4444;color:#dc2626;">❌ Error en la comunicación con el servidor</div>'); })
          .always(function () { $btn.prop('disabled', false); });
    });

    // ── Google Calendar: probar conexión ──────────────────────────
    $('#js-btn-global-gcal-test').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var $msg = $('#js-global-gcal-msg').show();
        $msg.html('<div class="aura-info-box" style="font-size:13px;">⏳ <?php echo esc_js(__('Probando conexión con Google Calendar...','aura-suite')); ?></div>');
        $.post(ajaxurl, {
            action: 'aura_global_gcal_test',
            nonce: auraNonce,
            service_account_json: $('#gset-gcal-json').val(),
        }, function (r) {
            $msg.html(r.success
                ? '<div class="aura-info-box" style="font-size:13px;">' + r.data.message + '</div>'
                : '<div class="aura-info-box" style="background:rgba(239,68,68,.08);border-color:#ef4444;color:#dc2626;font-size:13px;">❌ ' + r.data.message + '</div>'
            );
        }).fail(function () { $msg.html('<div class="aura-info-box" style="background:rgba(239,68,68,.08);border-color:#ef4444;color:#dc2626;">❌ Error AJAX</div>'); })
          .always(function () { $btn.prop('disabled', false); });
    });

    // ── Google Drive: probar conexión ─────────────────────────────
    $('#js-btn-global-gdrive-test').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var $msg = $('#js-global-gdrive-msg').show();
        $msg.html('<div class="aura-info-box" style="font-size:13px;">⏳ <?php echo esc_js(__('Probando conexión y permisos en Google Drive...','aura-suite')); ?></div>');
        $.post(ajaxurl, {
            action: 'aura_test_gdrive_connection',
            nonce: '<?php echo wp_create_nonce('aura_admin_nonce'); ?>',
            folder_id: $('#gdrive_folder_id').val(),
            credentials_json: $('#gdrive_credentials_json').val(),
            drive_type: $('input[name="gdrive_type"]:checked').val()
        }, function (r) {
            if (r.success) {
                $msg.html('<div class="aura-info-box" style="font-size:13px;">✅ ' + r.data.message + '</div>');
            } else {
                $msg.html('<div class="aura-info-box" style="background:rgba(239,68,68,.08);border-color:#ef4444;color:#dc2626;font-size:13px;">❌ ' + (r.data ? r.data.message : 'Error al probar conexión') + '</div>');
            }
        }).fail(function () {
            $msg.html('<div class="aura-info-box" style="background:rgba(239,68,68,.08);border-color:#ef4444;color:#dc2626;">❌ Error de comunicación con el servidor</div>');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // Copiar email de service account
    $('#btn-copy-gdrive-email').on('click', function (e) {
        e.preventDefault();
        var email = $(this).data('email');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(email).then(function () {
                alert('<?php echo esc_js(__('Correo copiado al portapapeles: ', 'aura-suite')); ?>' + email);
            });
        } else {
            prompt('<?php echo esc_js(__('Copia este correo para Google Drive: ', 'aura-suite')); ?>', email);
        }
    });

    // ── Toggle Aprobación Automática ──────────────────────────────
    $('#auto_approval_enabled').on('change', function () {
        var isEnabled = $(this).is(':checked');
        if (isEnabled) {
            $('#aura-approval-fields').slideDown(250);
            $('#aura-status-policy-badge').removeClass('badge-gray aura-badge-gray').addClass('badge-emerald aura-badge-green').text('<?php echo esc_js(__('Motor Activo', 'aura-suite')); ?>');
        } else {
            $('#aura-approval-fields').slideUp(250);
            $('#aura-status-policy-badge').removeClass('badge-emerald aura-badge-green').addClass('badge-gray aura-badge-gray').text('<?php echo esc_js(__('Motor Pausado', 'aura-suite')); ?>');
        }
    });

    // ── Simulador / Resumen en Vivo de la Regla de Aprobación ──────
    function updateRuleSummary() {
        var threshold = parseFloat($('#auto_approval_threshold').val()) || 0;
        var mode = $('input[name="auto_approval_mode"]:checked').val() || 'expenses_threshold_income_auto';
        var thFormatted = '$' + threshold.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        if (mode === 'expenses_threshold_income_auto') {
            $('#summary-income-text').html('✅ <strong><?php echo esc_js(__('Ingresos Libres:','aura-suite')); ?></strong> <?php echo esc_js(__('Se aprueban de forma inmediata automática sin importar el monto.','aura-suite')); ?>');
            if (threshold > 0) {
                $('#summary-expense-text').html('💸 <?php echo esc_js(__('Menores a ','aura-suite')); ?><strong>' + thFormatted + '</strong> <?php echo esc_js(__('se aprueban automáticamente. De ','aura-suite')); ?><strong>' + thFormatted + '</strong> <?php echo esc_js(__('o más quedan PENDIENTES para aprobación manual.','aura-suite')); ?>');
            } else {
                $('#summary-expense-text').html('⚠️ <?php echo esc_js(__('Umbral en $0: Todos los egresos requerirán aprobación manual.','aura-suite')); ?>');
            }
        } else if (mode === 'both_threshold') {
            if (threshold > 0) {
                $('#summary-income-text').html('💰 <?php echo esc_js(__('Menores a ','aura-suite')); ?><strong>' + thFormatted + '</strong> <?php echo esc_js(__('se auto-aprueban. De ','aura-suite')); ?><strong>' + thFormatted + '</strong> <?php echo esc_js(__('o más quedan PENDIENTES.','aura-suite')); ?>');
                $('#summary-expense-text').html('💸 <?php echo esc_js(__('Menores a ','aura-suite')); ?><strong>' + thFormatted + '</strong> <?php echo esc_js(__('se auto-aprueban. De ','aura-suite')); ?><strong>' + thFormatted + '</strong> <?php echo esc_js(__('o más quedan PENDIENTES.','aura-suite')); ?>');
            } else {
                $('#summary-income-text').html('⚠️ <?php echo esc_js(__('Umbral en $0: Todos los ingresos requerirán aprobación manual.','aura-suite')); ?>');
                $('#summary-expense-text').html('⚠️ <?php echo esc_js(__('Umbral en $0: Todos los egresos requerirán aprobación manual.','aura-suite')); ?>');
            }
        } else if (mode === 'expenses_only_threshold') {
            $('#summary-income-text').html('🔒 <strong><?php echo esc_js(__('Ingresos en Revisión:','aura-suite')); ?></strong> <?php echo esc_js(__('Siempre quedan PENDIENTES de revisión contable.','aura-suite')); ?>');
            if (threshold > 0) {
                $('#summary-expense-text').html('💸 <?php echo esc_js(__('Menores a ','aura-suite')); ?><strong>' + thFormatted + '</strong> <?php echo esc_js(__('se auto-aprueban. De ','aura-suite')); ?><strong>' + thFormatted + '</strong> <?php echo esc_js(__('o más quedan PENDIENTES.','aura-suite')); ?>');
            } else {
                $('#summary-expense-text').html('⚠️ <?php echo esc_js(__('Umbral en $0: Todos los egresos requerirán aprobación manual.','aura-suite')); ?>');
            }
        }
    }

    // Al escribir monto o cambiar radio
    $('#auto_approval_threshold').on('input change', updateRuleSummary);
    $('input[name="auto_approval_mode"]').on('change', function () {
        $('.aura-policy-card').removeClass('is-selected');
        $(this).closest('.aura-policy-card').addClass('is-selected');
        updateRuleSummary();
    });

    // ── Botones de Presets / Configuraciones Predeterminadas ────────
    $('.aura-btn-preset').on('click', function (e) {
        e.preventDefault();
        $('.aura-btn-preset').removeClass('is-active');
        $(this).addClass('is-active');

        var mode = $(this).data('mode');
        var threshold = $(this).data('threshold');

        $('#auto_approval_threshold').val(threshold);
        $('input[name="auto_approval_mode"][value="' + mode + '"]').prop('checked', true).trigger('change');
        updateRuleSummary();
    });

    // Quick set threshold amounts
    $('.aura-threshold-examples a').on('click', function (e) {
        e.preventDefault();
        var amt = $(this).data('amount');
        $('#auto_approval_threshold').val(amt).trigger('change').focus();
    });

    // ── GESTIÓN DE EXCEPCIONES DE CATEGORÍAS ──────────────────────
    function updateExceptionsCount() {
        var count = $('.category-exception:checked').length;
        $('#aura-exceptions-num').text(count);
        if (count > 0) {
            $('#aura-exceptions-badge-count').removeClass('aura-badge-gray').addClass('aura-badge-blue');
        } else {
            $('#aura-exceptions-badge-count').removeClass('aura-badge-blue').addClass('aura-badge-gray');
        }
    }
    updateExceptionsCount();

    // Filtro / Buscador en tiempo real de categorías
    $('#aura-cat-search-input').on('input', function () {
        var q = $.trim($(this).val().toLowerCase());
        $('.aura-category-exception-item').each(function () {
            var name = $(this).data('name') || '';
            if (!q || name.indexOf(q) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Clic en la tarjeta de categoría para marcar/desmarcar
    $(document).on('click', '.aura-category-exception-item', function (e) {
        if ($(e.target).is('input[type="checkbox"]')) {
            var $chk = $(this).find('input[type="checkbox"]');
            var isChecked = $chk.is(':checked');
            $(this).toggleClass('is-checked', isChecked);
            $(this).find('.badge-forced').toggle(isChecked);
            updateExceptionsCount();
            return;
        }
        var $checkbox = $(this).find('input[type="checkbox"]');
        var newState = !$checkbox.is(':checked');
        $checkbox.prop('checked', newState);
        $(this).toggleClass('is-checked', newState);
        $(this).find('.badge-forced').toggle(newState);
        updateExceptionsCount();
    });

    // Acciones rápidas de selección
    $('#btn-select-all-income').on('click', function () {
        $('.cat-type-income').each(function () {
            $(this).prop('checked', true);
            $(this).closest('.aura-category-exception-item').addClass('is-checked').find('.badge-forced').show();
        });
        updateExceptionsCount();
    });

    $('#btn-select-all-expense').on('click', function () {
        $('.cat-type-expense').each(function () {
            $(this).prop('checked', true);
            $(this).closest('.aura-category-exception-item').addClass('is-checked').find('.badge-forced').show();
        });
        updateExceptionsCount();
    });

    $('#btn-deselect-all-cats').on('click', function () {
        $('.category-exception').each(function () {
            $(this).prop('checked', false);
            $(this).closest('.aura-category-exception-item').removeClass('is-checked').find('.badge-forced').hide();
        });
        updateExceptionsCount();
    });

    $('#toggle-income-select').on('click', function (e) {
        e.preventDefault();
        $('.cat-type-income').each(function () {
            var curr = $(this).is(':checked');
            $(this).prop('checked', !curr);
            $(this).closest('.aura-category-exception-item').toggleClass('is-checked', !curr).find('.badge-forced').toggle(!curr);
        });
        updateExceptionsCount();
    });

    $('#toggle-expense-select').on('click', function (e) {
        e.preventDefault();
        $('.cat-type-expense').each(function () {
            var curr = $(this).is(':checked');
            $(this).prop('checked', !curr);
            $(this).closest('.aura-category-exception-item').toggleClass('is-checked', !curr).find('.badge-forced').toggle(!curr);
        });
        updateExceptionsCount();
    });

    // ── Guardar excepciones de categorías (AJAX) ──────────────────
    $('#save-category-exceptions').on('click', function (e) {
        e.preventDefault();
        var $button = $(this);
        var $status = $('#category-save-status');
        var exceptions = [];
        $('.category-exception:checked').each(function () { exceptions.push($(this).data('category-id')); });
        $button.prop('disabled', true);
        $status.html('<span style="color:var(--aura-primary);font-size:13px;">⏳ <?php echo esc_js(__('Guardando excepciones...','aura-suite')); ?></span>');
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'aura_save_category_exceptions', nonce: '<?php echo wp_create_nonce('aura_category_exceptions'); ?>', category_ids: exceptions },
            success: function (response) {
                if (response.success) {
                    $status.html('<span style="color:#10b981;font-size:13px;font-weight:600;">✓ ' + response.data.message + '</span>');
                    setTimeout(function () { $status.fadeOut(function () { $(this).html('').show(); }); }, 3000);
                } else {
                    $status.html('<span style="color:#ef4444;font-size:13px;font-weight:600;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function () { $status.html('<span style="color:#ef4444;font-size:13px;font-weight:600;">✗ <?php echo esc_js(__('Error al guardar excepciones','aura-suite')); ?></span>'); },
            complete: function () { $button.prop('disabled', false); }
        });
    });

    // ── Reinstalar categorías ──────────────────────────────────────
    $('#aura-reinstall-categories').on('click', function (e) {
        e.preventDefault();
        if (!confirm('<?php echo esc_js(__('¿Está seguro que desea reinstalar las categorías predeterminadas?','aura-suite')); ?>')) return;
        var $button = $(this);
        var $result = $('#reinstall-result');
        $button.prop('disabled', true)
               .html('<span class="dashicons dashicons-update aura-spin"></span> <?php echo esc_js(__('Reinstalando...','aura-suite')); ?>');
        $result.html('');
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'aura_reinstall_categories', nonce: '<?php echo wp_create_nonce('aura_reinstall_categories_nonce'); ?>' },
            success: function (response) {
                if (response.success) {
                    $result.html('<div class="aura-info-box" style="font-size:13px;"><strong>' + response.data.message + '</strong><br>Total: ' + response.data.stats.total + ' | Ingresos: ' + response.data.stats.income + ' | Gastos: ' + response.data.stats.expense + '</div>');
                    setTimeout(function () { location.reload(); }, 2000);
                } else {
                    $result.html('<div class="aura-info-box" style="background:rgba(239,68,68,.08);border-color:#ef4444;color:#dc2626;font-size:13px;">' + response.data.message + '</div>');
                }
            },
            error: function (xhr, status, error) {
                $result.html('<div class="aura-info-box" style="background:rgba(239,68,68,.08);border-color:#ef4444;color:#dc2626;font-size:13px;">Error: ' + error + '</div>');
            },
            complete: function () {
                $button.prop('disabled', false)
                       .html('<span class="dashicons dashicons-update"></span> <?php echo esc_js(__('Reinstalar Categorías Predeterminadas','aura-suite')); ?>');
            }
        });
    });

    // Inicializar simulación al cargar
    updateRuleSummary();

});
</script>

<?php
// Modal de Migración y Portabilidad Multimedia (ZIP Bundle)
include AURA_PLUGIN_DIR . 'templates/common/portable-bundle-modal.php';
?>