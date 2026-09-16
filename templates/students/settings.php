<?php
/**
 * Template: Configuración del Módulo Estudiantes
 * Adaptado 100% al Design System de Aura Business Suite (Protocolo P.E.E.)
 * Soporte dual Modo Claro / Modo Oscuro, micro-animaciones y variables universales.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_students_settings' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

// Obtener configuración actual (valores guardados + defaults)
$s = Aura_Students_Settings::get_all();

// Lista de páginas WordPress publicadas para los selectores
$pages = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title' ] );

// Monedas disponibles
$currencies = [
    'USD' => 'USD — Dólar estadounidense ($)',
    'EUR' => 'EUR — Euro (€)',
    'COP' => 'COP — Peso colombiano ($)',
    'MXN' => 'MXN — Peso mexicano ($)',
    'ARS' => 'ARS — Peso argentino ($)',
    'PEN' => 'PEN — Sol peruano (S/)',
    'CLP' => 'CLP — Peso chileno ($)',
    'BRL' => 'BRL — Real brasileño (R$)',
    'VES' => 'VES — Bolívar venezolano (Bs.)',
    'GTQ' => 'GTQ — Quetzal guatemalteco (Q)',
    'HNL' => 'HNL — Lempira hondureño (L)',
    'BOB' => 'BOB — Boliviano (Bs)',
    'PYG' => 'PYG — Guaraní paraguayo (Gs)',
    'UYU' => 'UYU — Peso uruguayo ($)',
    'CRC' => 'CRC — Colón costarricense (₡)',
    'DOP' => 'DOP — Peso dominicano (RD$)',
    'NIO' => 'NIO — Córdoba nicaragüense (C$)',
];

$available_fields = Aura_Students_Settings::available_form_fields();
$active_fields    = is_array( $s['enrollment_form_fields'] ?? null ) ? $s['enrollment_form_fields'] : array_keys( $available_fields );
?>

<div class="aura-app-wrapper aura-students-wrap" id="aura-students-settings-app">
<div class="wrap aura-app-context" style="max-width:1300px;margin:0 auto;padding:0 16px;">

    <!-- ═══════════════════════════════════════════════════════════════
         1. CABECERA PRINCIPAL HERO GLASS CARD (.hero-card)
         ═══════════════════════════════════════════════════════════════ -->
    <header class="aura-glass-card aura-page-header hero-card fade-up" style="margin-bottom: 24px;">
        <div class="aura-header-left" style="display:flex;align-items:center;gap:18px;">
            <div class="avatar avatar-lg" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;box-shadow:0 8px 24px rgba(139,92,246,0.35);font-size:26px;display:flex;align-items:center;justify-content:center;border-radius:14px;width:54px;height:54px;min-width:54px;">
                <span class="dashicons dashicons-welcome-learn-more" style="font-size:30px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;"></span>
            </div>
            <div class="aura-header-text">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
                    <h1 style="margin:0;font-size:1.5rem;font-weight:700;color:var(--aura-text-primary, #0f172a);letter-spacing:-0.02em;">
                        <?php esc_html_e( 'Configuración de Estudiantes y Portales', 'aura-suite' ); ?>
                    </h1>
                    <span class="live-chip live-primary" style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;font-size:12px;font-weight:600;">
                        <span class="pulse-dot dot-indigo"></span>
                        <?php esc_html_e( 'Fase 11 &amp; Portales', 'aura-suite' ); ?>
                    </span>
                </div>
                <p class="hero-desc" style="margin:0;font-size:13px;color:var(--aura-text-secondary, #64748b);">
                    <?php esc_html_e( 'Gestión de páginas públicas de estudiantes, sincronización financiera, campos de inscripción y credenciales automáticas.', 'aura-suite' ); ?>
                </p>
            </div>
        </div>

        <div class="aura-header-actions" style="margin-left:auto;display:flex;align-items:center;gap:10px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students' ) ); ?>" class="btn btn-secondary btn-lift" style="text-decoration:none;">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:16px;width:16px;height:16px;margin-top:2px;"></span>
                <?php esc_html_e( 'Volver al Dashboard', 'aura-suite' ); ?>
            </a>
        </div>
    </header>

    <!-- Aviso de éxito / error flotante -->
    <div id="st-settings-notice" class="alert-card alert-success fade-up" style="display:none;margin-bottom:20px;border-radius:12px;">
        <span id="st-settings-notice-msg" style="display:flex;align-items:center;gap:8px;font-weight:600;"></span>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         2. TARJETA CONTENEDORA CON PESTAÑAS
         ═══════════════════════════════════════════════════════════════ -->
    <div class="glass-card" style="border-radius:16px;overflow:hidden;padding:0;background:var(--aura-surface, #ffffff);border:1px solid var(--aura-border, #e2e8f0);box-shadow:0 4px 20px rgba(0,0,0,0.04);">

        <!-- Nav de pestañas -->
        <nav class="aura-settings-tabs-nav" role="tablist" style="display:flex;gap:6px;padding:12px 18px;background:var(--aura-surface-alt, #f8fafc);border-bottom:1px solid var(--aura-border, #e2e8f0);overflow-x:auto;">
            <button type="button" class="aura-tab-btn active" data-tab="general" role="tab" style="padding:10px 18px;border:1px solid transparent;border-radius:10px;cursor:pointer;font-size:13px;font-weight:600;color:var(--aura-text-secondary, #64748b);transition:all .18s ease;display:flex;align-items:center;gap:8px;">
                <span class="dashicons dashicons-admin-site" style="font-size:16px;width:16px;height:16px;"></span>
                <?php esc_html_e( 'General y Portales', 'aura-suite' ); ?>
            </button>
            <button type="button" class="aura-tab-btn" data-tab="finance" role="tab" style="padding:10px 18px;border:1px solid transparent;border-radius:10px;cursor:pointer;font-size:13px;font-weight:600;color:var(--aura-text-secondary, #64748b);transition:all .18s ease;display:flex;align-items:center;gap:8px;">
                <span class="dashicons dashicons-money-alt" style="font-size:16px;width:16px;height:16px;"></span>
                <?php esc_html_e( 'Pagos y Finanzas', 'aura-suite' ); ?>
            </button>
            <button type="button" class="aura-tab-btn" data-tab="notifications" role="tab" style="padding:10px 18px;border:1px solid transparent;border-radius:10px;cursor:pointer;font-size:13px;font-weight:600;color:var(--aura-text-secondary, #64748b);transition:all .18s ease;display:flex;align-items:center;gap:8px;">
                <span class="dashicons dashicons-bell" style="font-size:16px;width:16px;height:16px;"></span>
                <?php esc_html_e( 'Notificaciones y Accesos', 'aura-suite' ); ?>
            </button>
        </nav>

        <div style="padding:28px 24px;max-width:1050px;">

            <!-- ═══════════════════════════════════════════════════════════
                 PESTAÑA 1: GENERAL Y PORTALES
                 ═══════════════════════════════════════════════════════════ -->
            <div class="aura-tab-panel active" id="tab-general" role="tabpanel">

                <!-- Bloque A: Portales Frontend de Estudiantes (Destacado) -->
                <div class="aura-settings-card" style="margin-bottom:32px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;padding-bottom:16px;border-bottom:1px solid var(--aura-border, #e2e8f0);margin-bottom:20px;">
                        <div>
                            <h3 style="margin:0 0 6px;font-size:1.1rem;font-weight:700;color:var(--aura-text-primary, #0f172a);display:flex;align-items:center;gap:8px;">
                                🌐 <?php esc_html_e( 'Páginas del Portal Frontend (Acceso Externo)', 'aura-suite' ); ?>
                            </h3>
                            <p style="margin:0;color:var(--aura-text-secondary, #64748b);font-size:13px;max-width:680px;line-height:1.5;">
                                <?php esc_html_e( 'Para que los estudiantes inicien sesión, vean sus cursos y se inscriban sin ingresar al panel de administración de WordPress, vincule las páginas correspondientes o haga clic en el botón para crearlas automáticamente.', 'aura-suite' ); ?>
                            </p>
                        </div>
                        <div>
                            <button type="button" id="btn-st-auto-create-pages" class="btn btn-indigo btn-shimmer btn-lift" style="white-space:nowrap;font-size:13px;padding:9px 16px;">
                                <span class="dashicons dashicons-magic" style="font-size:16px;width:16px;height:16px;margin-top:2px;"></span>
                                <?php esc_html_e( 'Crear / Vincular Páginas Automáticamente', 'aura-suite' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Cuadrícula de 3 páginas requeridas -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:16px;margin-bottom:24px;">

                        <!-- Página 1: Login de Estudiantes -->
                        <div class="aura-card card-lift" style="padding:16px;border-radius:12px;border:1px solid var(--aura-border, #e2e8f0);background:var(--aura-surface-alt, #f8fafc);">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                <span style="font-weight:700;font-size:13px;color:var(--aura-text-primary, #0f172a);">
                                    🔑 <?php esc_html_e( 'Acceso / Login Estudiantes', 'aura-suite' ); ?>
                                </span>
                                <?php
                                $login_url = ! empty( $s['login_page_id'] ) ? get_permalink( $s['login_page_id'] ) : '';
                                ?>
                                <a id="link-login-page" href="<?php echo esc_url( $login_url ?: '#' ); ?>" target="_blank" rel="noopener noreferrer"
                                   class="btn btn-sm btn-secondary" style="padding:2px 8px;font-size:11px;text-decoration:none;<?php echo ! $login_url ? 'display:none;' : ''; ?>">
                                    <?php esc_html_e( 'Ver página ↗', 'aura-suite' ); ?>
                                </a>
                            </div>
                            <p style="margin:0 0 10px;font-size:12px;color:var(--aura-text-secondary, #64748b);">
                                <?php esc_html_e( 'Shortcode maestro:', 'aura-suite' ); ?> <code>[aura_login]</code> <span style="font-size:11px;opacity:0.8;">(alias: <code>[aura_student_login]</code>)</span>
                            </p>
                            <div class="input-group">
                                <span class="input-group-text">📄</span>
                                <select id="login-page-id" name="login_page_id" class="form-control" style="width:100%;">
                                    <option value="0"><?php esc_html_e( '— Seleccionar página —', 'aura-suite' ); ?></option>
                                    <?php foreach ( $pages as $page ) : ?>
                                        <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( (int) ( $s['login_page_id'] ?? 0 ), $page->ID ); ?>>
                                            <?php echo esc_html( $page->post_title ); ?> (ID: <?php echo esc_html( $page->ID ); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Página 2: Portal del Estudiante -->
                        <div class="aura-card card-lift" style="padding:16px;border-radius:12px;border:1px solid var(--aura-border, #e2e8f0);background:var(--aura-surface-alt, #f8fafc);">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                <span style="font-weight:700;font-size:13px;color:var(--aura-text-primary, #0f172a);">
                                    🎓 <?php esc_html_e( 'Portal del Estudiante (Dashboard)', 'aura-suite' ); ?>
                                </span>
                                <?php
                                $portal_url = ! empty( $s['portal_page_id'] ) ? get_permalink( $s['portal_page_id'] ) : '';
                                ?>
                                <a id="link-portal-page" href="<?php echo esc_url( $portal_url ?: '#' ); ?>" target="_blank" rel="noopener noreferrer"
                                   class="btn btn-sm btn-secondary" style="padding:2px 8px;font-size:11px;text-decoration:none;<?php echo ! $portal_url ? 'display:none;' : ''; ?>">
                                    <?php esc_html_e( 'Ver página ↗', 'aura-suite' ); ?>
                                </a>
                            </div>
                            <p style="margin:0 0 10px;font-size:12px;color:var(--aura-text-secondary, #64748b);">
                                <?php esc_html_e( 'Shortcode:', 'aura-suite' ); ?> <code>[aura_student_portal]</code>
                            </p>
                            <div class="input-group">
                                <span class="input-group-text">📄</span>
                                <select id="portal-page-id" name="portal_page_id" class="form-control" style="width:100%;">
                                    <option value="0"><?php esc_html_e( '— Seleccionar página —', 'aura-suite' ); ?></option>
                                    <?php foreach ( $pages as $page ) : ?>
                                        <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( (int) ( $s['portal_page_id'] ?? 0 ), $page->ID ); ?>>
                                            <?php echo esc_html( $page->post_title ); ?> (ID: <?php echo esc_html( $page->ID ); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Página 3: Formulario de Inscripción -->
                        <div class="aura-card card-lift" style="padding:16px;border-radius:12px;border:1px solid var(--aura-border, #e2e8f0);background:var(--aura-surface-alt, #f8fafc);">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                <span style="font-weight:700;font-size:13px;color:var(--aura-text-primary, #0f172a);">
                                    📝 <?php esc_html_e( 'Formulario de Inscripción', 'aura-suite' ); ?>
                                </span>
                                <?php
                                $enrollment_url = ! empty( $s['enrollment_page_id'] ) ? get_permalink( $s['enrollment_page_id'] ) : '';
                                ?>
                                <a id="link-enrollment-page" href="<?php echo esc_url( $enrollment_url ?: '#' ); ?>" target="_blank" rel="noopener noreferrer"
                                   class="btn btn-sm btn-secondary" style="padding:2px 8px;font-size:11px;text-decoration:none;<?php echo ! $enrollment_url ? 'display:none;' : ''; ?>">
                                    <?php esc_html_e( 'Ver página ↗', 'aura-suite' ); ?>
                                </a>
                            </div>
                            <p style="margin:0 0 10px;font-size:12px;color:var(--aura-text-secondary, #64748b);">
                                <?php esc_html_e( 'Shortcode:', 'aura-suite' ); ?> <code>[aura_enrollment_form]</code>
                            </p>
                            <div class="input-group">
                                <span class="input-group-text">📄</span>
                                <select id="enrollment-page-id" name="enrollment_page_id" class="form-control" style="width:100%;">
                                    <option value="0"><?php esc_html_e( '— Seleccionar página —', 'aura-suite' ); ?></option>
                                    <?php foreach ( $pages as $page ) : ?>
                                        <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( (int) ( $s['enrollment_page_id'] ?? 0 ), $page->ID ); ?>>
                                            <?php echo esc_html( $page->post_title ); ?> (ID: <?php echo esc_html( $page->ID ); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Bloque B: Identificación y Moneda -->
                <div class="aura-settings-card" style="margin-bottom:32px;padding-top:16px;border-top:1px solid var(--aura-border, #e2e8f0);">
                    <h3 style="margin:0 0 16px;font-size:1.1rem;font-weight:700;color:var(--aura-text-primary, #0f172a);">
                        🏷️ <?php esc_html_e( 'Parámetros Básicos y Nomenclatura', 'aura-suite' ); ?>
                    </h3>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:20px;">
                        <div>
                            <label for="student-code-prefix" style="display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--aura-text-secondary, #64748b);margin-bottom:6px;">
                                <?php esc_html_e( 'Prefijo del código de estudiante', 'aura-suite' ); ?>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">🔖</span>
                                <input type="text" id="student-code-prefix" name="student_code_prefix"
                                       class="form-control" maxlength="20"
                                       value="<?php echo esc_attr( $s['student_code_prefix'] ); ?>"
                                       placeholder="CEM-EST">
                            </div>
                            <small style="display:block;color:var(--aura-text-muted, #94a3b8);font-size:12px;margin-top:4px;">
                                <?php esc_html_e( 'Se usa para generar matrículas únicas (Ej: CEM-EST-001).', 'aura-suite' ); ?>
                            </small>
                        </div>

                        <div>
                            <label for="default-currency" style="display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--aura-text-secondary, #64748b);margin-bottom:6px;">
                                <?php esc_html_e( 'Moneda por defecto', 'aura-suite' ); ?>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">💵</span>
                                <select id="default-currency" name="default_currency" class="form-control">
                                    <?php foreach ( $currencies as $code => $label ) : ?>
                                        <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['default_currency'], $code ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small style="display:block;color:var(--aura-text-muted, #94a3b8);font-size:12px;margin-top:4px;">
                                <?php esc_html_e( 'Moneda predeterminada para cursos, cuotas y matrículas.', 'aura-suite' ); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Bloque C: Campos del Formulario Público de Inscripción -->
                <div class="aura-settings-card" style="padding-top:16px;border-top:1px solid var(--aura-border, #e2e8f0);">
                    <div style="margin-bottom:14px;">
                        <h3 style="margin:0 0 4px;font-size:1.1rem;font-weight:700;color:var(--aura-text-primary, #0f172a);">
                            📋 <?php esc_html_e( 'Campos del Formulario Público de Inscripción', 'aura-suite' ); ?>
                        </h3>
                        <p style="margin:0;color:var(--aura-text-secondary, #64748b);font-size:13px;">
                            <?php esc_html_e( 'Seleccione qué campos opcionales u obligatorios se mostrarán en la solicitud de matrícula pública ([aura_enrollment_form]).', 'aura-suite' ); ?>
                        </p>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;background:var(--aura-surface-alt, #f8fafc);padding:18px;border-radius:12px;border:1px solid var(--aura-border, #e2e8f0);">
                        <?php foreach ( $available_fields as $fkey => $flabel ) : ?>
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;color:var(--aura-text-primary, #0f172a);padding:4px 0;">
                                <input type="checkbox" name="enrollment_form_fields[]" value="<?php echo esc_attr( $fkey ); ?>"
                                    <?php checked( in_array( $fkey, $active_fields, true ) ); ?>
                                    style="border-radius:4px;border-color:var(--aura-border, #cbd5e1);width:16px;height:16px;">
                                <span><?php echo esc_html( $flabel ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- /#tab-general -->

            <!-- ═══════════════════════════════════════════════════════════
                 PESTAÑA 2: PAGOS Y FINANZAS
                 ═══════════════════════════════════════════════════════════ -->
            <div class="aura-tab-panel" id="tab-finance" role="tabpanel" style="display:none;">
                <div class="aura-settings-card">
                    <div style="margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--aura-border, #e2e8f0);">
                        <h3 style="margin:0 0 4px;font-size:1.1rem;font-weight:700;color:var(--aura-text-primary, #0f172a);">
                            💰 <?php esc_html_e( 'Integración con el Módulo de Finanzas', 'aura-suite' ); ?>
                        </h3>
                        <p style="margin:0;color:var(--aura-text-secondary, #64748b);font-size:13px;">
                            <?php esc_html_e( 'Controle la sincronización automática de ingresos de inscripciones con la tesorería de la organización.', 'aura-suite' ); ?>
                        </p>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:20px;">
                        <div class="aura-card card-lift" style="padding:18px;background:var(--aura-surface-alt, #f8fafc);border-radius:12px;border:1px solid var(--aura-border, #e2e8f0);">
                            <label style="display:flex;align-items:flex-start;gap:14px;cursor:pointer;margin:0;">
                                <input type="checkbox" id="finance-integration-enabled"
                                       name="finance_integration_enabled" value="1"
                                       style="margin-top:3px;width:18px;height:18px;"
                                    <?php checked( ! empty( $s['finance_integration_enabled'] ) ); ?>>
                                <div>
                                    <strong style="color:var(--aura-text-primary, #0f172a);font-size:14px;display:block;">
                                        <?php esc_html_e( 'Registrar automáticamente pagos en el módulo Finanzas', 'aura-suite' ); ?>
                                    </strong>
                                    <span style="color:var(--aura-text-secondary, #64748b);font-size:13px;display:block;margin-top:3px;line-height:1.5;">
                                        <?php esc_html_e( 'Al aprobar un comprobante de pago en el módulo de estudiantes, se creará una transacción contable de tipo "Ingreso" en la base de datos de Finanzas.', 'aura-suite' ); ?>
                                    </span>
                                </div>
                            </label>
                        </div>

                        <div>
                            <label for="default-currency-finance" style="display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--aura-text-secondary, #64748b);margin-bottom:6px;">
                                <?php esc_html_e( 'Moneda por defecto para transacciones', 'aura-suite' ); ?>
                            </label>
                            <div class="input-group" style="max-width:380px;">
                                <span class="input-group-text">💵</span>
                                <select name="default_currency" class="form-control" id="default-currency-finance">
                                    <?php foreach ( $currencies as $code => $label ) : ?>
                                        <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['default_currency'], $code ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small style="display:block;color:var(--aura-text-muted, #94a3b8);font-size:12px;margin-top:4px;">
                                <?php esc_html_e( '(Se mantiene sincronizado con la pestaña General).', 'aura-suite' ); ?>
                            </small>
                        </div>

                        <div class="alert-card alert-info" style="border-radius:12px;padding:14px 18px;">
                            <strong><?php esc_html_e( 'Nota de imputación contable:', 'aura-suite' ); ?></strong>
                            <?php esc_html_e( 'La categoría financiera se asigna de forma individual en cada Curso o Programa educativo (campo "Categoría financiera"). Si no se define una categoría, el ingreso se asienta en la categoría general del programa.', 'aura-suite' ); ?>
                        </div>
                    </div>
                </div>
            </div><!-- /#tab-finance -->

            <!-- ═══════════════════════════════════════════════════════════
                 PESTAÑA 3: NOTIFICACIONES Y ACCESOS
                 ═══════════════════════════════════════════════════════════ -->
            <div class="aura-tab-panel" id="tab-notifications" role="tabpanel" style="display:none;">
                <div class="aura-settings-card">
                    <div style="margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--aura-border, #e2e8f0);">
                        <h3 style="margin:0 0 4px;font-size:1.1rem;font-weight:700;color:var(--aura-text-primary, #0f172a);">
                            🔔 <?php esc_html_e( 'Automatizaciones, Credenciales y Alertas', 'aura-suite' ); ?>
                        </h3>
                        <p style="margin:0;color:var(--aura-text-secondary, #64748b);font-size:13px;">
                            <?php esc_html_e( 'Generación automática de usuarios WordPress para estudiantes, envío de credenciales y recordatorios de cuotas.', 'aura-suite' ); ?>
                        </p>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:18px;">

                        <div class="aura-card card-lift" style="padding:18px;background:var(--aura-surface-alt, #f8fafc);border-radius:12px;border:1px solid var(--aura-border, #e2e8f0);display:flex;flex-direction:column;gap:14px;">
                            <label style="display:flex;align-items:flex-start;gap:14px;cursor:pointer;">
                                <input type="checkbox" id="auto-generate-password"
                                       name="auto_generate_password" value="1"
                                       style="margin-top:3px;width:18px;height:18px;"
                                    <?php checked( ! empty( $s['auto_generate_password'] ) ); ?>>
                                <div>
                                    <strong style="color:var(--aura-text-primary, #0f172a);font-size:14px;display:block;">
                                        <?php esc_html_e( 'Generar contraseña automáticamente al aprobar la inscripción', 'aura-suite' ); ?>
                                    </strong>
                                    <span style="color:var(--aura-text-secondary, #64748b);font-size:13px;display:block;margin-top:2px;">
                                        <?php esc_html_e( 'Crea una contraseña segura de 12 caracteres y asocia el usuario WordPress con el rol de estudiante.', 'aura-suite' ); ?>
                                    </span>
                                </div>
                            </label>

                            <hr style="border:none;border-top:1px solid var(--aura-border, #e2e8f0);margin:0;">

                            <label style="display:flex;align-items:flex-start;gap:14px;cursor:pointer;">
                                <input type="checkbox" id="send-credentials-email"
                                       name="send_credentials_email" value="1"
                                       style="margin-top:3px;width:18px;height:18px;"
                                    <?php checked( ! empty( $s['send_credentials_email'] ) ); ?>>
                                <div>
                                    <strong style="color:var(--aura-text-primary, #0f172a);font-size:14px;display:block;">
                                        <?php esc_html_e( 'Enviar correo con credenciales de acceso al portal tras la aprobación', 'aura-suite' ); ?>
                                    </strong>
                                    <span style="color:var(--aura-text-secondary, #64748b);font-size:13px;display:block;margin-top:2px;">
                                        <?php esc_html_e( 'El estudiante recibirá su usuario, contraseña temporal y el enlace directo a la página de acceso.', 'aura-suite' ); ?>
                                    </span>
                                </div>
                            </label>
                        </div>

                        <div>
                            <label for="reminder-days-before" style="display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--aura-text-secondary, #64748b);margin-bottom:6px;">
                                <?php esc_html_e( 'Recordatorio preventivo de vencimiento de cuota', 'aura-suite' ); ?>
                            </label>
                            <div class="input-group" style="max-width:240px;">
                                <span class="input-group-text">📅</span>
                                <input type="number" id="reminder-days-before"
                                       name="reminder_days_before" class="form-control"
                                       min="1" max="30"
                                       value="<?php echo esc_attr( $s['reminder_days_before'] ); ?>">
                                <span class="input-group-text"><?php esc_html_e( 'días antes', 'aura-suite' ); ?></span>
                            </div>
                            <small style="display:block;color:var(--aura-text-muted, #94a3b8);font-size:12px;margin-top:4px;">
                                <?php esc_html_e( 'Se enviará un correo de recordatorio al estudiante con esta anticipación respecto a la fecha límite.', 'aura-suite' ); ?>
                            </small>
                        </div>

                        <div class="aura-card card-lift" style="padding:18px;background:var(--aura-surface-alt, #f8fafc);border-radius:12px;border:1px solid var(--aura-border, #e2e8f0);">
                            <label style="display:flex;align-items:flex-start;gap:14px;cursor:pointer;margin:0;">
                                <input type="checkbox" id="overdue-alert-enabled"
                                       name="overdue_alert_enabled" value="1"
                                       style="margin-top:3px;width:18px;height:18px;"
                                    <?php checked( ! empty( $s['overdue_alert_enabled'] ) ); ?>>
                                <div>
                                    <strong style="color:var(--aura-text-primary, #0f172a);font-size:14px;display:block;">
                                        <?php esc_html_e( 'Alerta de cuota vencida para coordinadores', 'aura-suite' ); ?>
                                    </strong>
                                    <span style="color:var(--aura-text-secondary, #64748b);font-size:13px;display:block;margin-top:2px;">
                                        <?php esc_html_e( 'Notifica en el centro de alertas cuando un estudiante pasa a estado de mora.', 'aura-suite' ); ?>
                                    </span>
                                </div>
                            </label>
                        </div>

                        <div class="alert-card alert-warning" style="border-radius:12px;padding:14px 18px;">
                            <strong><?php esc_html_e( 'Canales de Notificación:', 'aura-suite' ); ?></strong>
                            <?php esc_html_e( 'La configuración del servidor de correo SMTP y las notificaciones de WhatsApp se gestionan en la pestaña global del sistema (Aura Suite → Notificaciones).', 'aura-suite' ); ?>
                        </div>

                    </div>
                </div>
            </div><!-- /#tab-notifications -->

        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             3. FOOTER DEL FORMULARIO CON BOTÓN DE GUARDADO
             ═══════════════════════════════════════════════════════════════ -->
        <div class="aura-settings-footer" style="padding:18px 24px;border-top:1px solid var(--aura-border, #e2e8f0);background:var(--aura-surface-alt, #f8fafc);display:flex;align-items:center;gap:16px;">
            <button type="button" id="btn-st-save-settings" class="btn btn-primary btn-shimmer btn-lift" style="padding:10px 24px;font-size:14px;">
                <span class="dashicons dashicons-saved" style="font-size:18px;width:18px;height:18px;margin-top:1px;"></span>
                <?php esc_html_e( 'Guardar configuración', 'aura-suite' ); ?>
            </button>
            <span id="st-settings-spinner" style="display:none;align-items:center;gap:8px;color:var(--aura-text-secondary, #64748b);font-size:13px;">
                <div class="aura-spinner" style="width:20px;height:20px;border-width:2px;border-top-color:var(--aura-primary-600, #4f46e5);border-radius:50%;animation:auraSpin .7s linear infinite;display:inline-block;"></div>
                <?php esc_html_e( 'Guardando cambios…', 'aura-suite' ); ?>
            </span>
        </div>

    </div><!-- /.glass-card -->

    <!-- ═══════════════════════════════════════════════════════════════
         4. FOOTER GLOBAL CANÓNICO (.adp-footer)
         ═══════════════════════════════════════════════════════════════ -->
    <div class="adp-footer" style="margin-top:20px;text-align:center;padding:12px 20px;font-size:12px;color:var(--aura-text-secondary, #64748b);">
        <p style="margin:0;">
            Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link" style="color:inherit;text-decoration:none;font-weight:600;"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon" style="vertical-align:text-bottom;margin-right:4px;"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.216.694.825.576C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>DiGiraldo</a></strong> &nbsp;|&nbsp; © <?php echo esc_html( date( 'Y' ) ); ?> AURA Business Suite
        </p>
    </div>

</div><!-- /.aura-app-context -->
</div><!-- /.aura-app-wrapper -->

<style>
#aura-students-settings-app .aura-tab-btn.active {
    color: var(--aura-primary-600, #4f46e5) !important;
    background: var(--aura-surface, #ffffff) !important;
    border-color: var(--aura-border, #e2e8f0) !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
#aura-students-settings-app .aura-tab-btn:hover:not(.active) {
    color: var(--aura-text-primary, #0f172a);
    background: rgba(255,255,255,0.6);
}
@keyframes auraSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
