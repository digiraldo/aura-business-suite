<?php
/**
 * Tab 2: Programas Académicos y Materias
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Helper: avatar para tooltip stack ───────────────────────────────────────
/**
 * Genera un avatar pequeño con datos para tooltip enriquecido.
 * Sin texto inline — la info aparece solo en el tooltip HTML.
 */
function aura_avatar_stack_item( int $user_id, string $role_label = '' ): string {
    $user = get_userdata( $user_id );
    if ( ! $user ) return '';

    $avatar_url = get_avatar_url( $user_id, [ 'size' => 72, 'default' => 'identicon' ] );
    $name       = esc_attr( $user->display_name );
    $email      = esc_attr( $user->user_email );
    $initials   = strtoupper( mb_substr( $user->first_name ?: $user->display_name, 0, 1 )
                  . mb_substr( $user->last_name ?: '', 0, 1 ) );
    $role       = esc_attr( $role_label );

    // Color determinista según inicial
    $colors = [ 'avatar-primary', 'avatar-green', 'avatar-amber', 'avatar-rose', 'avatar-cyan' ];
    $color_class = $colors[ ord( $user->display_name ) % count( $colors ) ];

    return sprintf(
        '<span class="aura-avatar-stack-item %s avatar avatar-sm" '
        . 'data-av-name="%s" data-av-email="%s" data-av-role="%s" data-av-img="%s" '
        . 'aria-label="%s">'
        . '<img src="%s" alt="%s" onerror="this.style.display=\'none\';this.nextSibling.style.display=\'inline\';" />'
        . '<span style="display:none;">%s</span>'
        . '</span>',
        esc_attr( $color_class ),
        $name, $email, $role,
        esc_url( $avatar_url ),
        $name,
        esc_url( $avatar_url ), $name,
        esc_html( $initials )
    );
}

// ─── Filtro activo ────────────────────────────────────────────────────────────
$prog_view_filter = sanitize_key( $_GET['prog_filter'] ?? 'active' );
if ( ! in_array( $prog_view_filter, [ 'active', 'archived', 'all' ], true ) ) {
    $prog_view_filter = 'active';
}

$prog_query_args = [ 'status' => '', 'limit' => 100 ];
if ( $prog_view_filter === 'archived' ) {
    $prog_query_args['include_archived'] = 'only';
    $prog_query_args['status']           = 'archived';
} elseif ( $prog_view_filter === 'all' ) {
    $prog_query_args['include_archived'] = true;
}

$programs       = Aura_Calendar_Programs::get_all( $prog_query_args );
$archived_count = count( Aura_Calendar_Programs::get_all( [ 'include_archived' => 'only', 'limit' => 100 ] ) );
$all_areas      = class_exists( 'Aura_Areas_Setup' ) ? Aura_Areas_Setup::get_all_areas() : [];
$prog_base_url  = add_query_arg( 'tab', 'programs', admin_url( 'admin.php?page=aura-calendar' ) );
$can_manage     = current_user_can( 'aura_cal_manage_programs' ) || current_user_can( 'manage_options' );
$can_edit       = $can_manage || current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_create_calendar_events' );
$can_delete     = current_user_can( 'aura_cal_delete_programs' ) || $can_manage;
?>

<!-- ══ TOOLTIP GLOBAL (singleton) ══ -->
<div id="aura-av-tooltip" class="aura-av-tooltip" role="tooltip" aria-hidden="true">
    <div class="aura-av-tooltip-avatar"></div>
    <div class="aura-av-tooltip-body">
        <div class="aura-av-tooltip-name"></div>
        <div class="aura-av-tooltip-role"></div>
        <div class="aura-av-tooltip-email"></div>
    </div>
</div>

<!-- ══ TOOLTIP ENRIQUECIDO DE FECHAS EN CALENDARIO (singleton) ══ -->
<div id="aura-subj-cal-tooltip" class="aura-subj-cal-tooltip" role="tooltip" aria-hidden="true">
    <div class="aura-tip-card">
        <div class="aura-tip-card-header">
            <div class="aura-tip-avatar-large" id="aura-subj-cal-tip-avatar">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="aura-tip-info">
                <div class="aura-tip-title" id="aura-subj-cal-tip-name"></div>
                <div class="aura-tip-subtitle" id="aura-subj-cal-tip-prog"></div>
                <div class="aura-tip-badges" id="aura-subj-cal-tip-meta-badges">
                    <!-- Badges canónicos inyectados dinámicamente vía JS -->
                </div>
            </div>
        </div>
        <div class="aura-tip-card-body">
            <div class="aura-tip-section-header">
                <span class="aura-tip-section-label"><?php esc_html_e( 'Fechas y Sesiones Programadas', 'aura' ); ?></span>
                <span class="aura-tip-section-count" id="aura-subj-cal-tip-count"></span>
            </div>
            <div class="aura-tip-session-list" id="aura-subj-cal-tip-list">
                <!-- Se inyectan dinámicamente las sesiones con Date Tiles tipográficos -->
            </div>
        </div>
        <div class="aura-tip-card-footer">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-calendar' ) ); ?>" class="btn btn-sm btn-indigo btn-shimmer btn-lift" style="width:100%;justify-content:center;text-decoration:none;display:flex;align-items:center;gap:6px;">
                <span class="dashicons dashicons-calendar-alt" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
                <span><?php esc_html_e( 'Abrir en Calendario Principal', 'aura' ); ?></span> &rarr;
            </a>
        </div>
    </div>
</div>

<style>
/* ── Avatar Stack + Tooltip ─────────────────────────────────────── */
.aura-avatar-group { display: flex; align-items: center; }
.aura-avatar-group .aura-avatar-stack-item {
    position: relative; cursor: pointer;
    border: 2px solid var(--aura-surface, #fff);
    margin-left: -8px; transition: transform .2s, z-index 0s;
    border-radius: 50%; overflow: hidden;
    width: 30px; height: 30px; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: #fff;
}
.aura-avatar-group .aura-avatar-stack-item:first-child { margin-left: 0; }
.aura-avatar-group .aura-avatar-stack-item:hover { transform: translateY(-3px) scale(1.12); z-index: 10; }
.aura-avatar-group .aura-avatar-stack-item img { width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 50%; }
.aura-av-more {
    width: 30px; height: 30px; border-radius: 50%; background: var(--glass-bg,rgba(99,102,241,.15));
    border: 2px solid var(--aura-surface,#fff); margin-left: -8px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; color: var(--aura-primary,#6366f1); flex-shrink: 0;
}
/* Ring animado en el último avatar del stack */
.aura-avatar-group .aura-avatar-stack-item:last-of-type { position: relative; }
.aura-avatar-group .aura-avatar-stack-item:last-of-type::before {
    content: ''; position: absolute; inset: -3px; border-radius: 50%;
    background: linear-gradient(135deg, var(--aura-violet,#7c3aed), var(--aura-cyan,#06b6d4));
    z-index: -1; animation: ringPulse 2s ease-in-out infinite;
}
/* Tooltip enriquecido adaptativo (Modo Claro / Modo Oscuro) */
.aura-av-tooltip {
    position: fixed; z-index: 99999; pointer-events: none;
    background: var(--aura-surface, #ffffff);
    backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
    border: 1px solid var(--aura-border, #e2e8f0);
    border-radius: 14px; padding: 12px 14px;
    box-shadow: 0 12px 35px -5px rgba(0,0,0,.15), 0 0 0 1px rgba(99,102,241,.12);
    display: flex; align-items: center; gap: 10px; min-width: 210px;
    opacity: 0; transform: translateY(6px) scale(.97);
    transition: opacity .18s ease, transform .18s ease;
    color: var(--aura-text-primary, #0f172a);
}
.aura-av-tooltip.visible { opacity: 1; transform: translateY(0) scale(1); pointer-events: none; }
.aura-av-tooltip-avatar {
    width: 40px; height: 40px; border-radius: 50%; overflow: hidden; flex-shrink: 0;
    background: linear-gradient(135deg, var(--aura-indigo,#6366f1), var(--aura-violet,#7c3aed));
    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px;
    color: #ffffff;
}
.aura-av-tooltip-avatar img { width: 100%; height: 100%; object-fit: cover; }
.aura-av-tooltip-name  { font-weight: 700; font-size: 14px; line-height: 1.2; color: var(--aura-text-primary, #0f172a); }
.aura-av-tooltip-role  { font-size: 11px; color: var(--aura-primary, #4f46e5); font-weight: 600; margin-top: 2px; }
.aura-av-tooltip-email { font-size: 11px; color: var(--aura-text-secondary, #64748b); margin-top: 1px; word-break: break-all; }

/* Adaptación a Modo Oscuro para Tooltip de Avatares y Avatar Stack */
body.aura-dark-mode .aura-av-tooltip,
[data-theme="dark"] .aura-av-tooltip,
.dark .aura-av-tooltip {
    background: #181b21 !important;
    border: 1px solid #3c4043 !important;
    box-shadow: 0 20px 50px rgba(0,0,0,.6), 0 0 0 1px rgba(99,102,241,.25) !important;
    color: #f1f5f9 !important;
}
body.aura-dark-mode .aura-av-tooltip .aura-av-tooltip-name,
[data-theme="dark"] .aura-av-tooltip .aura-av-tooltip-name,
.dark .aura-av-tooltip .aura-av-tooltip-name {
    color: #f8fafc !important;
}
body.aura-dark-mode .aura-av-tooltip .aura-av-tooltip-role,
[data-theme="dark"] .aura-av-tooltip .aura-av-tooltip-role,
.dark .aura-av-tooltip .aura-av-tooltip-role {
    color: #38bdf8 !important;
}
body.aura-dark-mode .aura-av-tooltip .aura-av-tooltip-email,
[data-theme="dark"] .aura-av-tooltip .aura-av-tooltip-email,
.dark .aura-av-tooltip .aura-av-tooltip-email {
    color: #94a3b8 !important;
}
body.aura-dark-mode .aura-avatar-group .aura-avatar-stack-item,
[data-theme="dark"] .aura-avatar-group .aura-avatar-stack-item,
.dark .aura-avatar-group .aura-avatar-stack-item {
    border-color: #20242c !important;
}
body.aura-dark-mode .aura-av-more,
[data-theme="dark"] .aura-av-more,
.dark .aura-av-more {
    border-color: #20242c !important;
    background: rgba(99, 102, 241, 0.25) !important;
    color: #a5b4fc !important;
}

/* ── Tooltip Enriquecido de Fechas en Calendario (.aura-subj-cal-tooltip) ── */
.aura-subj-cal-tooltip {
    position: fixed !important;
    z-index: 999999 !important;
    width: 380px;
    max-width: calc(100vw - 28px);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity .18s cubic-bezier(0.16, 1, 0.3, 1), transform .18s cubic-bezier(0.16, 1, 0.3, 1), visibility .18s;
    font-family: inherit;
}
.aura-subj-cal-tooltip.visible {
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: auto !important;
}

/* Tarjeta base con fondo sólido institucional */
.aura-subj-cal-tooltip .aura-tip-card {
    background: #ffffff !important;
    color: #0f172a !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 16px 45px -5px rgba(15, 23, 42, 0.22), 0 0 0 1px rgba(99, 102, 241, 0.15) !important;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Cabecera */
.aura-subj-cal-tooltip .aura-tip-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
    border-bottom: 1px solid #e2e8f0 !important;
}
.aura-subj-cal-tooltip .aura-tip-avatar-large {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #4f46e5;
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}
.aura-subj-cal-tooltip .aura-tip-info {
    flex: 1 1 auto;
    min-width: 0;
}
.aura-subj-cal-tooltip .aura-tip-title {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a !important;
    line-height: 1.25;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.aura-subj-cal-tooltip .aura-tip-subtitle {
    font-size: 11px;
    color: #64748b !important;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.aura-subj-cal-tooltip .aura-tip-badges {
    display: flex;
    gap: 5px;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 5px;
}
.aura-subj-cal-tooltip .aura-tip-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 7px;
    border-radius: 9999px !important;
    font-size: 10.5px;
    font-weight: 700;
    line-height: 1.2;
}
.aura-subj-cal-tooltip .aura-tip-badge--code {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5 !important;
    border: 1px solid rgba(99, 102, 241, 0.3);
}
.aura-subj-cal-tooltip .aura-tip-badge--hours {
    background: rgba(100, 116, 139, 0.12);
    color: #475569 !important;
    border: 1px solid rgba(100, 116, 139, 0.25);
}
.aura-subj-cal-tooltip .aura-tip-badge--count {
    background: rgba(16, 185, 129, 0.12);
    color: #059669 !important;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

/* Cuerpo y listado */
.aura-subj-cal-tooltip .aura-tip-card-body {
    padding: 10px 14px;
    background: #ffffff !important;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.aura-subj-cal-tooltip .aura-tip-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b !important;
    margin-bottom: 2px;
}
.aura-subj-cal-tooltip .aura-tip-section-count {
    font-size: 10px;
    font-weight: 700;
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.08);
    padding: 1px 6px;
    border-radius: 9999px;
}
.aura-subj-cal-tooltip .aura-tip-session-list {
    display: flex;
    flex-direction: column;
    gap: 7px;
    max-height: 220px;
    overflow-y: auto;
    padding-right: 2px;
}
.aura-subj-cal-tooltip .aura-tip-session-list::-webkit-scrollbar {
    width: 5px;
}
.aura-subj-cal-tooltip .aura-tip-session-list::-webkit-scrollbar-track {
    background: transparent;
}
.aura-subj-cal-tooltip .aura-tip-session-list::-webkit-scrollbar-thumb {
    background: rgba(100, 116, 139, 0.3);
    border-radius: 4px;
}

/* Tarjeta individual de sesión */
.aura-subj-cal-tooltip .aura-tip-session-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.aura-subj-cal-tooltip .aura-tip-session-card:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

/* Date Calendar Tile */
.aura-subj-cal-tooltip .aura-tip-date-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 44px;
    min-width: 44px;
    border-radius: 7px;
    padding: 3px 2px;
    text-align: center;
    flex-shrink: 0;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-top: 3px solid #4f46e5;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}
.aura-subj-cal-tooltip .aura-tip-date-month {
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #4f46e5;
    line-height: 1.1;
}
.aura-subj-cal-tooltip .aura-tip-date-day {
    font-size: 16px;
    font-weight: 800;
    line-height: 1.1;
    margin: 1px 0;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}
.aura-subj-cal-tooltip .aura-tip-date-wday {
    font-size: 8.5px;
    font-weight: 600;
    color: #64748b;
    line-height: 1;
}

/* Info de la sesión */
.aura-subj-cal-tooltip .aura-tip-session-info {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.aura-subj-cal-tooltip .aura-tip-session-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
}
.aura-subj-cal-tooltip .aura-tip-session-title {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}
.aura-subj-cal-tooltip .aura-tip-status-pill {
    font-size: 9.5px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 9999px;
    white-space: nowrap;
    flex-shrink: 0;
}
.aura-subj-cal-tooltip .status-scheduled {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
}
.aura-subj-cal-tooltip .status-completed {
    background: rgba(100, 116, 139, 0.15);
    color: #475569;
}
.aura-subj-cal-tooltip .status-cancelled {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
}
.aura-subj-cal-tooltip .aura-tip-session-time {
    font-size: 11px;
    font-weight: 600;
    color: #475569;
}
.aura-subj-cal-tooltip .aura-tip-type-label {
    font-size: 9.5px;
    color: #64748b;
    background: #e2e8f0;
    padding: 1px 5px;
    border-radius: 4px;
    font-weight: 600;
}
.aura-subj-cal-tooltip .aura-tip-session-meta {
    font-size: 10.5px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 220px;
}
.aura-subj-cal-tooltip .aura-tip-session-meta strong {
    color: #334155;
}

/* Botón de acceso directo al día en el calendario */
.aura-subj-cal-tooltip .aura-tip-goto-day-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 8px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    color: #4338ca !important;
    text-decoration: none !important;
    font-size: 11px;
    font-weight: 600;
    line-height: 1;
    flex-shrink: 0;
    cursor: pointer;
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.aura-subj-cal-tooltip .aura-tip-goto-day-btn:hover {
    background: #4f46e5 !important;
    border-color: #4338ca !important;
    color: #ffffff !important;
    box-shadow: 0 3px 8px rgba(79, 70, 229, 0.28);
    transform: translateY(-1px);
}
.aura-subj-cal-tooltip .aura-tip-goto-day-btn:active {
    transform: translateY(0);
}
.aura-subj-cal-tooltip .aura-tip-goto-icon-cal,
.aura-subj-cal-tooltip .aura-tip-goto-icon-arrow {
    flex-shrink: 0;
    display: inline-block;
    transition: transform 0.18s ease;
}
.aura-subj-cal-tooltip .aura-tip-goto-day-btn:hover .aura-tip-goto-icon-arrow {
    transform: translateX(2px);
}

/* Footer */
.aura-subj-cal-tooltip .aura-tip-card-footer {
    padding: 9px 14px;
    background: #f8fafc !important;
    border-top: 1px solid #e2e8f0 !important;
}

/* ── MODO OSCURO (Universal Design System) ── */
body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-card,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-card,
.dark .aura-subj-cal-tooltip .aura-tip-card,
html.wp-dark-mode-active .aura-subj-cal-tooltip .aura-tip-card {
    background: #181b21 !important;
    color: #f8fafc !important;
    border-color: #3c4043 !important;
    box-shadow: 0 24px 50px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(99, 102, 241, 0.3) !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-card-header,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-card-header,
.dark .aura-subj-cal-tooltip .aura-tip-card-header,
html.wp-dark-mode-active .aura-subj-cal-tooltip .aura-tip-card-header {
    background: linear-gradient(135deg, #1e2430 0%, #181b21 100%) !important;
    border-bottom-color: #334155 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-title,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-title,
.dark .aura-subj-cal-tooltip .aura-tip-title,
html.wp-dark-mode-active .aura-subj-cal-tooltip .aura-tip-title {
    color: #f8fafc !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-subtitle,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-subtitle,
.dark .aura-subj-cal-tooltip .aura-tip-subtitle,
html.wp-dark-mode-active .aura-subj-cal-tooltip .aura-tip-subtitle {
    color: #94a3b8 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-badge--code,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-badge--code,
.dark .aura-subj-cal-tooltip .aura-tip-badge--code {
    background: rgba(99, 102, 241, 0.25) !important;
    color: #a5b4fc !important;
    border-color: rgba(99, 102, 241, 0.5) !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-badge--hours,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-badge--hours,
.dark .aura-subj-cal-tooltip .aura-tip-badge--hours {
    background: rgba(100, 116, 139, 0.25) !important;
    color: #cbd5e1 !important;
    border-color: rgba(100, 116, 139, 0.4) !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-badge--count,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-badge--count,
.dark .aura-subj-cal-tooltip .aura-tip-badge--count {
    background: rgba(16, 185, 129, 0.25) !important;
    color: #6ee7b7 !important;
    border-color: rgba(16, 185, 129, 0.5) !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-card-body,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-card-body,
.dark .aura-subj-cal-tooltip .aura-tip-card-body,
html.wp-dark-mode-active .aura-subj-cal-tooltip .aura-tip-card-body {
    background: #181b21 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-section-header,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-section-header,
.dark .aura-subj-cal-tooltip .aura-tip-section-header {
    color: #94a3b8 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-session-card,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-session-card,
.dark .aura-subj-cal-tooltip .aura-tip-session-card,
html.wp-dark-mode-active .aura-subj-cal-tooltip .aura-tip-session-card {
    background: #1e2430 !important;
    border-color: #334155 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-session-card:hover,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-session-card:hover,
.dark .aura-subj-cal-tooltip .aura-tip-session-card:hover {
    background: #252d3d !important;
    border-color: #475569 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-date-tile,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-date-tile,
.dark .aura-subj-cal-tooltip .aura-tip-date-tile {
    background: #15181e !important;
    border-color: #3c4043 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-date-month,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-date-month,
.dark .aura-subj-cal-tooltip .aura-tip-date-month {
    color: #818cf8 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-date-day,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-date-day,
.dark .aura-subj-cal-tooltip .aura-tip-date-day {
    color: #ffffff !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-date-wday,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-date-wday,
.dark .aura-subj-cal-tooltip .aura-tip-date-wday {
    color: #94a3b8 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-session-title,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-session-title,
.dark .aura-subj-cal-tooltip .aura-tip-session-title {
    color: #f8fafc !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-session-time,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-session-time,
.dark .aura-subj-cal-tooltip .aura-tip-session-time {
    color: #cbd5e1 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-type-label,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-type-label,
.dark .aura-subj-cal-tooltip .aura-tip-type-label {
    background: #334155 !important;
    color: #94a3b8 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-session-meta,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-session-meta,
.dark .aura-subj-cal-tooltip .aura-tip-session-meta {
    color: #94a3b8 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-session-meta strong,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-session-meta strong,
.dark .aura-subj-cal-tooltip .aura-tip-session-meta strong {
    color: #cbd5e1 !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-goto-day-btn,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-goto-day-btn,
.dark .aura-subj-cal-tooltip .aura-tip-goto-day-btn,
html.wp-dark-mode-active .aura-subj-cal-tooltip .aura-tip-goto-day-btn {
    background: #181b21 !important;
    border-color: #3c4043 !important;
    color: #a5b4fc !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25) !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-goto-day-btn:hover,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-goto-day-btn:hover,
.dark .aura-subj-cal-tooltip .aura-tip-goto-day-btn:hover,
html.wp-dark-mode-active .aura-subj-cal-tooltip .aura-tip-goto-day-btn:hover {
    background: #6366f1 !important;
    border-color: #4f46e5 !important;
    color: #ffffff !important;
    box-shadow: 0 3px 8px rgba(99, 102, 241, 0.4) !important;
}

body.aura-dark-mode .aura-subj-cal-tooltip .aura-tip-card-footer,
body[data-theme="dark"] .aura-subj-cal-tooltip .aura-tip-card-footer,
.dark .aura-subj-cal-tooltip .aura-tip-card-footer,
html.wp-dark-mode-active .aura-subj-cal-tooltip .aura-tip-card-footer {
    background: #181b21 !important;
    border-top-color: #334155 !important;
}

/* ── Program Card ───────────────────────────────────────────────── */
.aura-prog-card {
    border-radius: 14px;
    border: 1px solid var(--aura-border, #e2e8f0);
    background: var(--bg-surface, #fff);
    overflow: hidden;
    transition: box-shadow .2s, transform .2s;
}
.aura-prog-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,.1); }
.aura-prog-card-header {
    padding: 18px 22px;
    border-left: 6px solid var(--prog-color, #6366f1);
    position: relative;
}
.aura-prog-card-header.archived-header {
    opacity: .85;
    background: var(--aura-surface-alt, #f8fafc);
}
.aura-prog-toggle-btn {
    background: none; border: none; cursor: pointer; padding: 4px 8px;
    color: var(--aura-text-secondary); transition: transform .25s;
    font-size: 18px; line-height: 1;
}
.aura-prog-toggle-btn.collapsed { transform: rotate(-90deg); }
.aura-prog-subjects-panel {
    border-top: 1px solid var(--aura-border, #e2e8f0);
    padding: 18px 22px;
    background: var(--aura-surface-alt2, var(--aura-surface-alt, #f8fafc));
    overflow: hidden;
    transition: max-height .35s ease, padding .35s ease;
}
.aura-prog-subjects-panel.collapsed { max-height: 0 !important; padding-top: 0; padding-bottom: 0; overflow: hidden; }

/* ── Subject Cards in Grid ──────────────────────────────────────── */
.aura-subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px;
}
.aura-subject-card {
    background: var(--bg-surface, #fff);
    border: 1px solid var(--aura-border, #e2e8f0);
    border-top: 3px solid var(--subj-color, #3b82f6);
    border-radius: 10px;
    padding: 12px 14px;
    position: relative;
    transition: box-shadow .18s, transform .18s;
}
.aura-subject-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.09); transform: translateY(-1px); }
.aura-subject-card-code {
    font-size: 10px; font-weight: 800; letter-spacing: .8px;
    color: var(--aura-text-muted); text-transform: uppercase; margin-bottom: 3px;
}
.aura-subject-card-name {
    font-size: 13px; font-weight: 700; color: var(--aura-text-primary); line-height: 1.3;
    margin-bottom: 6px;
}
.aura-subject-card-footer {
    display: flex; justify-content: space-between; align-items: center; margin-top: 8px;
}
.aura-subject-card-actions { display: flex; gap: 3px; opacity: 0; transition: opacity .18s; }
.aura-subject-card:hover .aura-subject-card-actions { opacity: 1; }

/* ── Export dropdown ────────────────────────────────────────────── */
.aura-export-dropdown { position: relative; display: inline-flex; }
.aura-export-menu {
    position: absolute; top: calc(100% + 6px); right: 0; z-index: 200;
    background: var(--bg-surface, #fff);
    border: 1px solid var(--aura-border, #e2e8f0);
    border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,.12);
    min-width: 190px; overflow: hidden; display: none;
}
.aura-export-menu.open { display: block; animation: fadeInDown .15s ease; }
.aura-export-menu-item {
    display: flex; align-items: center; gap: 8px; padding: 9px 14px;
    font-size: 13px; font-weight: 500; cursor: pointer;
    color: var(--aura-text-primary); transition: background .12s;
    border: none; background: none; width: 100%; text-align: left;
}
.aura-export-menu-item:hover { background: var(--aura-surface-alt, #f8fafc); }
.aura-export-menu-sep { border-top: 1px solid var(--aura-border, #e2e8f0); margin: 4px 0; }
@keyframes fadeInDown { from { opacity:0; transform: translateY(-6px); } to { opacity:1; transform: translateY(0); } }

/* ── Import modal drag-drop zone ────────────────────────────────── */
.aura-import-dropzone {
    border: 2px dashed var(--aura-border, #e2e8f0);
    border-radius: 12px; padding: 32px 24px; text-align: center;
    cursor: pointer; transition: border-color .18s, background .18s;
}
.aura-import-dropzone.dragover {
    border-color: var(--aura-primary, #6366f1);
    background: rgba(99,102,241,.05);
}
</style>

<div class="aura-programs-view-container">

    <!-- ── BARRA SUPERIOR ── -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 14px;">
        <div>
            <h2 class="adp-card-title" style="font-size: 20px; margin: 0;">
                🎓 <?php esc_html_e( 'Programas y Cursos de Capacitación', 'aura' ); ?>
            </h2>
            <p class="adp-card-desc" style="margin: 4px 0 0 0; font-size: 13px;">
                <?php esc_html_e( 'Administra programas de formación, materias, profesores y material pedagógico.', 'aura' ); ?>
            </p>
        </div>

        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <?php if ( $can_manage ) : ?>
                <!-- Botón Sincronizar -->
                <button type="button" class="btn btn-ghost btn-lift" id="btn-sync-student-courses"
                        title="<?php esc_attr_e( 'Importar cursos de Estudiantes como Programas del Calendario', 'aura' ); ?>">
                    🔄 <?php esc_html_e( 'Sincronizar', 'aura' ); ?>
                </button>

                <!-- Botón Importar -->
                <button type="button" class="btn btn-ghost btn-lift" id="btn-open-import-modal">
                    📤 <?php esc_html_e( 'Importar JSON', 'aura' ); ?>
                </button>

                <!-- Dropdown Exportar Todos -->
                <div class="aura-export-dropdown" id="export-all-dropdown">
                    <button type="button" class="btn btn-ghost btn-lift" id="btn-export-all-toggle">
                        📥 <?php esc_html_e( 'Exportar Todos', 'aura' ); ?> ▾
                    </button>
                    <div class="aura-export-menu" id="export-all-menu">
                        <button class="aura-export-menu-item btn-export-all" data-format="json">
                            📄 JSON <small style="opacity:.6;margin-left:auto;">Jerarquía completa</small>
                        </button>
                        <div class="aura-export-menu-sep"></div>
                        <button class="aura-export-menu-item btn-export-all" data-format="csv">
                            📊 CSV <small style="opacity:.6;margin-left:auto;">Editable en Excel</small>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $can_edit ) : ?>
                <button type="button" class="btn btn-indigo btn-shimmer btn-lift" id="btn-create-program">
                    ➕ <?php esc_html_e( 'Nuevo Programa', 'aura' ); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── TABS DE FILTRO ── -->
    <div style="display: flex; gap: 4px; border-bottom: 2px solid var(--aura-border, #e2e8f0); margin-bottom: 24px;">
        <a href="<?php echo esc_url( add_query_arg( 'prog_filter', 'active', $prog_base_url ) ); ?>"
           style="text-decoration:none;padding:8px 16px;font-size:13px;font-weight:600;border-radius:8px 8px 0 0;
                  border-bottom:3px solid <?php echo $prog_view_filter === 'active' ? 'var(--aura-primary,#6366f1)' : 'transparent'; ?>;
                  color:<?php echo $prog_view_filter === 'active' ? 'var(--aura-primary,#6366f1)' : 'var(--aura-text-secondary,#64748b)'; ?>;
                  background:<?php echo $prog_view_filter === 'active' ? 'rgba(99,102,241,.07)' : 'transparent'; ?>">
            ✅ <?php esc_html_e( 'Activos', 'aura' ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'prog_filter', 'archived', $prog_base_url ) ); ?>"
           style="text-decoration:none;padding:8px 16px;font-size:13px;font-weight:600;border-radius:8px 8px 0 0;
                  border-bottom:3px solid <?php echo $prog_view_filter === 'archived' ? '#f59e0b' : 'transparent'; ?>;
                  color:<?php echo $prog_view_filter === 'archived' ? '#d97706' : 'var(--aura-text-secondary,#64748b)'; ?>;
                  background:<?php echo $prog_view_filter === 'archived' ? 'rgba(245,158,11,.07)' : 'transparent'; ?>">
            📦 <?php esc_html_e( 'Archivados', 'aura' ); ?>
            <?php if ( $archived_count > 0 ) : ?>
                <span style="margin-left:5px;background:#f59e0b;color:#fff;border-radius:10px;font-size:11px;padding:1px 6px;font-weight:700;"><?php echo intval( $archived_count ); ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'prog_filter', 'all', $prog_base_url ) ); ?>"
           style="text-decoration:none;padding:8px 16px;font-size:13px;font-weight:600;border-radius:8px 8px 0 0;
                  border-bottom:3px solid <?php echo $prog_view_filter === 'all' ? '#64748b' : 'transparent'; ?>;
                  color:<?php echo $prog_view_filter === 'all' ? '#475569' : 'var(--aura-text-secondary,#64748b)'; ?>;
                  background:<?php echo $prog_view_filter === 'all' ? 'rgba(100,116,139,.07)' : 'transparent'; ?>">
            📋 <?php esc_html_e( 'Todos', 'aura' ); ?>
        </a>
    </div>

    <!-- ── LISTA DE PROGRAMAS ── -->
    <?php if ( empty( $programs ) ) : ?>
        <div class="adp-card" style="text-align:center;padding:48px 24px;border-radius:14px;">
            <div style="font-size:48px;margin-bottom:14px;"><?php echo $prog_view_filter === 'archived' ? '📦' : '🎓'; ?></div>
            <h3 style="font-size:18px;margin-bottom:6px;">
                <?php $prog_view_filter === 'archived' ? esc_html_e( 'No hay programas archivados', 'aura' ) : esc_html_e( 'No hay programas registrados', 'aura' ); ?>
            </h3>
            <p style="color:var(--aura-text-secondary);max-width:420px;margin:0 auto 20px;">
                <?php $prog_view_filter === 'archived'
                    ? esc_html_e( 'Los programas archivados aparecerán aquí para poder restaurarlos.', 'aura' )
                    : esc_html_e( 'Crea tu primer programa de formación para empezar a estructurar materias y horarios.', 'aura' ); ?>
            </p>
            <?php if ( $prog_view_filter !== 'archived' && $can_edit ) : ?>
                <button type="button" class="btn btn-indigo btn-shimmer btn-lift" id="btn-create-first-program">
                    ➕ <?php esc_html_e( 'Crear Primer Programa', 'aura' ); ?>
                </button>
            <?php endif; ?>
        </div>
    <?php else : ?>

        <div style="display:flex;flex-direction:column;gap:18px;">
        <?php foreach ( $programs as $p ) :
            $subjects    = Aura_Calendar_Subjects::get_all( [ 'program_id' => $p->id, 'include_archived' => $prog_view_filter === 'archived' ] );
            $p_color     = ! empty( $p->color ) ? $p->color : '#6366f1';
            $is_archived = ! empty( $p->deleted_at ) || $p->status === 'archived';
            $subj_count  = count( $subjects );
        ?>
            <!-- PROGRAM CARD -->
            <div class="adp-card aura-prog-card program-card" data-program-id="<?php echo esc_attr( $p->id ); ?>"
                 style="--prog-color: <?php echo esc_attr( $p_color ); ?>; <?php echo $is_archived ? 'opacity:.82;' : ''; ?>">

                <!-- ── HEADER DEL PROGRAMA ── -->
                <div class="aura-prog-card-header <?php echo $is_archived ? 'archived-header' : ''; ?>">

                    <?php if ( $is_archived ) : ?>
                        <div style="margin-bottom:8px;">
                            <span class="adp-badge badge-amber" style="font-size:11px;">
                                📦 <?php esc_html_e( 'Archivado', 'aura' ); ?>
                                <?php if ( ! empty( $p->deleted_at ) ) : ?>
                                    &mdash; <?php echo esc_html( date_i18n( 'j M Y', strtotime( $p->deleted_at ) ) ); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Fila superior: badges + botones -->
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span class="adp-badge badge-slate" style="font-weight:700;font-size:11px;letter-spacing:.5px;"><?php echo esc_html( $p->code ); ?></span>
                            <?php if ( $p->status === 'active' ) : ?>
                                <span class="adp-badge badge-emerald has-dot"><span class="pulse-dot"></span> <?php esc_html_e( 'Activo', 'aura' ); ?></span>
                            <?php elseif ( $p->status !== 'archived' ) : ?>
                                <span class="adp-badge badge-amber"><?php esc_html_e( 'Borrador', 'aura' ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $p->area_name ) ) :
                                $ac = ! empty( $p->area_color ) ? $p->area_color : '#6366f1'; ?>
                                <span class="adp-badge" style="background:<?php echo esc_attr($ac.'18'); ?>;color:<?php echo esc_attr($ac); ?>;border:1px solid <?php echo esc_attr($ac.'35'); ?>;font-size:11px;">
                                    🏢 <?php echo esc_html( $p->area_name ); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ( ! empty( $p->academic_period ) ) : ?>
                                <span style="font-size:12px;color:var(--aura-text-secondary);">📅 <?php echo esc_html( $p->academic_period ); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Botones de acción -->
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <?php if ( $can_edit ) : ?>
                                <?php if ( $is_archived ) : ?>
                                    <button type="button" class="btn btn-ghost btn-restore-program"
                                            data-program-id="<?php echo esc_attr( $p->id ); ?>"
                                            style="font-size:12px;padding:5px 11px;color:#10b981;border-color:#10b981;">
                                        ♻️ <?php esc_html_e( 'Restaurar', 'aura' ); ?>
                                    </button>
                                    <?php if ( $can_delete ) : ?>
                                        <button type="button" class="btn btn-ghost btn-delete-program"
                                                data-program-id="<?php echo esc_attr( $p->id ); ?>"
                                                data-force="1"
                                                title="<?php esc_attr_e( 'Eliminar permanentemente este programa archivado', 'aura' ); ?>"
                                                style="font-size:12px;padding:5px 11px;color:#ef4444;border-color:rgba(239,68,68,0.4);">
                                            🗑️ <?php esc_html_e( 'Eliminar def.', 'aura' ); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <button type="button" class="btn btn-ghost btn-edit-program"
                                            data-program-id="<?php echo esc_attr( $p->id ); ?>"
                                            style="font-size:12px;padding:5px 11px;">
                                        ✏️ <?php esc_html_e( 'Editar', 'aura' ); ?>
                                    </button>
                                    <button type="button" class="btn btn-indigo btn-lift btn-add-subject"
                                            data-program-id="<?php echo esc_attr( $p->id ); ?>"
                                            data-program-name="<?php echo esc_attr( $p->name ); ?>"
                                            style="font-size:12px;padding:5px 11px;">
                                        ➕ <?php esc_html_e( 'Añadir Materia', 'aura' ); ?>
                                    </button>
                                    <?php if ( $can_delete ) : ?>
                                        <button type="button" class="btn btn-ghost btn-delete-program"
                                                data-program-id="<?php echo esc_attr( $p->id ); ?>"
                                                data-force="0"
                                                title="<?php esc_attr_e( 'Archivar / Eliminar programa', 'aura' ); ?>"
                                                style="font-size:12px;padding:5px 11px;color:#ef4444;border-color:rgba(239,68,68,0.4);">
                                            🗑️ <?php esc_html_e( 'Eliminar', 'aura' ); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ( $can_manage ) : ?>
                                <!-- Dropdown exportar ESTE programa -->
                                <div class="aura-export-dropdown">
                                    <button type="button" class="btn btn-ghost" style="font-size:12px;padding:5px 10px;"
                                            data-export-toggle="prog-<?php echo esc_attr($p->id); ?>">
                                        📥 ▾
                                    </button>
                                    <div class="aura-export-menu" id="export-prog-menu-<?php echo esc_attr($p->id); ?>">
                                        <button class="aura-export-menu-item btn-export-program" data-format="json" data-program-id="<?php echo esc_attr($p->id); ?>">
                                            📄 JSON
                                        </button>
                                        <div class="aura-export-menu-sep"></div>
                                        <button class="aura-export-menu-item btn-export-program" data-format="csv" data-program-id="<?php echo esc_attr($p->id); ?>">
                                            📊 CSV
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Toggle colapsar materias -->
                            <button type="button" class="aura-prog-toggle-btn" data-prog-panel="panel-<?php echo esc_attr($p->id); ?>"
                                    title="<?php esc_attr_e('Mostrar/Ocultar materias','aura'); ?>">▾</button>
                        </div>
                    </div>

                    <!-- Nombre del programa -->
                    <h3 style="font-size:18px;font-weight:800;margin:10px 0 4px;color:var(--aura-text-primary);">
                        <?php echo esc_html( $p->name ); ?>
                    </h3>

                    <?php if ( ! empty( $p->description ) ) : ?>
                        <p style="font-size:13px;color:var(--aura-text-secondary);margin:0 0 8px;max-width:720px;line-height:1.5;">
                            <?php echo esc_html( $p->description ); ?>
                        </p>
                    <?php endif; ?>

                    <!-- Meta row -->
                    <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;margin-top:8px;font-size:12px;color:var(--aura-text-muted);">
                        <?php if ( ! empty( $p->start_date ) && ! empty( $p->end_date ) ) : ?>
                            <span>🗓️ <?php echo esc_html( date_i18n( 'j M Y', strtotime( $p->start_date ) ) . ' — ' . date_i18n( 'j M Y', strtotime( $p->end_date ) ) ); ?></span>
                        <?php endif; ?>

                        <span>📚 <strong><?php echo intval( $p->subjects_count ); ?></strong> <?php esc_html_e( 'materias', 'aura' ); ?></span>
                        <span>📅 <strong><?php echo intval( $p->events_count ); ?></strong> <?php esc_html_e( 'clases', 'aura' ); ?></span>

                        <!-- Avatares de coordinadores -->
                        <?php if ( ! empty( $p->coordinator_ids ) && is_array( $p->coordinator_ids ) ) :
                            $coord_count = count( $p->coordinator_ids );
                            $max_visible = 4;
                        ?>
                            <span style="display:inline-flex;align-items:center;gap:6px;">
                                <span style="font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
                                    <?php echo $coord_count > 1 ? esc_html__('Coords.','aura') : esc_html__('Coord.','aura'); ?>
                                </span>
                                <span class="aura-avatar-group">
                                    <?php
                                    $shown = 0;
                                    foreach ( $p->coordinator_ids as $c_id ) {
                                        if ( $shown >= $max_visible ) break;
                                        echo aura_avatar_stack_item( (int) $c_id, __('Coordinador','aura') );
                                        $shown++;
                                    }
                                    if ( $coord_count > $max_visible ) : ?>
                                        <span class="aura-av-more">+<?php echo $coord_count - $max_visible; ?></span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div><!-- /.aura-prog-card-header -->

                <!-- ── PANEL DE MATERIAS (colapsable) ── -->
                <div class="aura-prog-subjects-panel" id="panel-<?php echo esc_attr($p->id); ?>"
                     style="max-height: 2000px;">

                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                        <h4 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;
                                   color:var(--aura-text-muted);margin:0;">
                            📚 <?php printf( esc_html__( 'Materias del Programa (%d)', 'aura' ), $subj_count ); ?>
                        </h4>
                    </div>

                    <?php if ( empty( $subjects ) ) : ?>
                        <div style="text-align:center;padding:20px;border:2px dashed var(--aura-border,#e2e8f0);border-radius:10px;color:var(--aura-text-muted);font-size:13px;">
                            <?php esc_html_e( 'Aún no hay materias en este programa.', 'aura' ); ?>
                            <?php if ( $can_edit && ! $is_archived ) : ?>
                                <br><button type="button" class="btn btn-ghost btn-add-subject" style="margin-top:10px;font-size:12px;"
                                            data-program-id="<?php echo esc_attr($p->id); ?>" data-program-name="<?php echo esc_attr($p->name); ?>">
                                    ➕ <?php esc_html_e( 'Añadir primera materia', 'aura' ); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <div class="aura-subjects-grid">
                            <?php foreach ( $subjects as $s ) :
                                $s_color     = ! empty( $s->color ) ? $s->color : '#3b82f6';
                                $t_mat_count = (int) ( $s->teacher_materials_count ?? 0 );
                                $st_mat_count= (int) ( $s->student_materials_count ?? 0 );
                            ?>
                                <div class="aura-subject-card" style="--subj-color: <?php echo esc_attr($s_color); ?>;">
                                    <div class="aura-subject-card-top" style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 4px;">
                                        <div class="aura-subject-card-code">
                                            <?php echo esc_html( $s->code ); ?>
                                            <?php if ( ! empty( $s->total_hours ) ) : ?>
                                                &bull; <?php echo intval( $s->total_hours ); ?> hrs
                                            <?php endif; ?>
                                        </div>
                                        <?php if ( ! empty( $s->scheduled_events ) ) :
                                            $ev_count = count( $s->scheduled_events );
                                        ?>
                                            <span class="aura-subj-cal-badge"
                                                  tabindex="0"
                                                  role="button"
                                                  data-subj-name="<?php echo esc_attr( $s->name ); ?>"
                                                  data-subj-code="<?php echo esc_attr( $s->code ); ?>"
                                                  data-subj-color="<?php echo esc_attr( $s_color ); ?>"
                                                  data-subj-hours="<?php echo intval( $s->total_hours ?? 0 ); ?>"
                                                  data-prog-name="<?php echo esc_attr( $p->name ); ?>"
                                                  data-events='<?php echo esc_attr( wp_json_encode( $s->scheduled_events ) ); ?>'
                                                  aria-label="<?php echo esc_attr( sprintf( _n( '%d fecha programada', '%d fechas programadas', $ev_count, 'aura' ), $ev_count ) ); ?>">
                                                <span class="aura-subj-cal-icon">📅</span>
                                                <span class="aura-subj-cal-count"><?php echo $ev_count; ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="aura-subject-card-name"><?php echo esc_html( $s->name ); ?></div>

                                    <!-- Profesores en avatar stack -->
                                    <?php if ( ! empty( $s->teacher_ids ) && is_array( $s->teacher_ids ) ) :
                                        $teach_count = count( $s->teacher_ids );
                                        $max_teach   = 3;
                                    ?>
                                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                                            <span class="aura-avatar-group">
                                                <?php
                                                $t_shown = 0;
                                                foreach ( $s->teacher_ids as $t_id ) {
                                                    if ( $t_shown >= $max_teach ) break;
                                                    echo aura_avatar_stack_item( (int) $t_id, __('Profesor','aura') );
                                                    $t_shown++;
                                                }
                                                if ( $teach_count > $max_teach ) : ?>
                                                    <span class="aura-av-more">+<?php echo $teach_count - $max_teach; ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php elseif ( ! empty( $s->teachers_names ) || ! empty( $s->default_teacher_name ) ) : ?>
                                        <div style="font-size:11px;color:var(--aura-text-secondary);margin-bottom:4px;">
                                            👤 <?php echo esc_html( $s->teachers_names ?: $s->default_teacher_name ); ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Material + acciones -->
                                    <div class="aura-subject-card-footer">
                                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                            <?php if ( $t_mat_count > 0 ) : ?>
                                                <span style="font-size:10px;background:rgba(93,95,239,.1);color:var(--aura-primary,#5d5fef);padding:2px 6px;border-radius:6px;font-weight:600;" title="<?php esc_attr_e('Material Docente','aura'); ?>">
                                                    📁 <?php echo $t_mat_count; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( $st_mat_count > 0 ) : ?>
                                                <span style="font-size:10px;background:rgba(16,185,129,.1);color:#10b981;padding:2px 6px;border-radius:6px;font-weight:600;" title="<?php esc_attr_e('Material Alumnos','aura'); ?>">
                                                    📖 <?php echo $st_mat_count; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="aura-subject-card-actions">
                                            <?php if ( $can_edit ) : ?>
                                                <button type="button" class="btn btn-ghost btn-edit-subject"
                                                        data-subject-id="<?php echo esc_attr($s->id); ?>"
                                                        style="padding:3px 7px;font-size:12px;" title="<?php esc_attr_e('Editar','aura'); ?>">✏️</button>
                                                <button type="button" class="btn btn-ghost btn-delete-subject"
                                                        data-subject-id="<?php echo esc_attr($s->id); ?>"
                                                        style="padding:3px 7px;font-size:12px;color:#ef4444;" title="<?php esc_attr_e('Eliminar','aura'); ?>">🗑️</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div><!-- /.aura-prog-subjects-panel -->
            </div><!-- /.aura-prog-card -->
        <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div><!-- /.aura-programs-view-container -->


<!-- ══════════════════════════════════════════════════════════════════
     MODAL A: CREAR / EDITAR PROGRAMA
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-program-editor" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 580px;">
        <div class="aura-modal-header">
            <h3 id="modal-prog-title" class="adp-card-title" style="margin: 0; font-size: 18px;">
                🎓 <?php esc_html_e( 'Nuevo Programa Académico', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-program-editor">&times;</button>
        </div>

        <form id="form-program-editor" class="aura-modal-form" novalidate>
            <div class="aura-modal-body">
                <input type="hidden" name="id" id="prog-id" value="0">

                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            <?php esc_html_e( 'Nombre del Programa / Capacitación', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="name" id="prog-name" required class="form-control" placeholder="<?php esc_attr_e( 'Ej: Capacitación Ministerial Hadime 2025', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Código Corto', 'aura' ); ?>
                            </label>
                            <input type="text" name="code" id="prog-code" class="form-control" placeholder="<?php esc_attr_e( 'HADIME25', 'aura' ); ?>" style="width: 100%; border-radius: 8px; text-transform: uppercase;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Período Académico', 'aura' ); ?>
                            </label>
                            <input type="text" name="academic_period" id="prog-period" class="form-control" placeholder="<?php esc_attr_e( '2025-1 / Ene-Jun', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Fecha Inicio', 'aura' ); ?>
                            </label>
                            <input type="date" name="start_date" id="prog-start-date" class="form-control" style="width: 100%; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Fecha Fin', 'aura' ); ?>
                            </label>
                            <input type="date" name="end_date" id="prog-end-date" class="form-control" style="width: 100%; border-radius: 8px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            🎨 <?php esc_html_e( 'Color del Programa', 'aura' ); ?>
                        </label>
                        <div class="aura-color-picker-box">
                            <div class="aura-color-picker-row">
                                <input type="color" name="color" id="prog-color" value="#5D5FEF" class="aura-color-custom-input" title="<?php esc_attr_e( 'Color personalizado', 'aura' ); ?>">
                                <span style="font-size: 12px; color: var(--aura-text-secondary);"><?php esc_html_e( 'Paleta de colores oficial:', 'aura' ); ?></span>
                            </div>
                            <?php echo Aura_Calendar_Admin::render_color_palette( 'prog-color', '#5D5FEF' ); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            🏢 <?php esc_html_e( 'Área Institucional (Opcional)', 'aura' ); ?>
                        </label>
                        <select name="area_id" id="prog-area-id" class="form-control" style="width: 100%; border-radius: 8px;">
                            <option value=""><?php esc_html_e( '— Ninguna / Programa General —', 'aura' ); ?></option>
                            <?php foreach ( $all_areas as $area ) : ?>
                                <option value="<?php echo esc_attr( $area->id ); ?>">
                                    <?php echo esc_html( $area->name . ( ! empty( $area->code ) ? ' (' . $area->code . ')' : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="font-size: 11.5px; color: var(--aura-text-muted); display: block; margin-top: 4px;">
                            <?php esc_html_e( 'Vincular el programa a un área habilita permisos y presupuestos descentralizados para sus coordinadores y líderes de área.', 'aura' ); ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            👥 <?php esc_html_e( 'Coordinador(es) del Programa', 'aura' ); ?>
                        </label>
                        <div id="prog-coordinators-container" class="aura-user-chips-container">
                            <!-- Inyectado dinámicamente con checkboxes desde JS -->
                        </div>
                        <small style="font-size: 11.5px; color: var(--aura-text-muted); display: block; margin-top: 4px;">
                            <?php esc_html_e( 'Puedes seleccionar uno o varios coordinadores para este programa.', 'aura' ); ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                            <?php esc_html_e( 'Descripción u Objetivos', 'aura' ); ?>
                        </label>
                        <textarea name="description" id="prog-desc" rows="2" class="form-control" placeholder="<?php esc_attr_e( 'Breve descripción del programa...', 'aura' ); ?>" style="width: 100%; border-radius: 8px;"></textarea>
                    </div>
                </div>
            </div>

            <div class="aura-modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <?php if ( $can_delete ) : ?>
                        <button type="button" class="btn btn-ghost" id="btn-delete-program-modal" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.4); display: none;">
                            🗑️ <?php esc_html_e( 'Eliminar Programa', 'aura' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-ghost" data-close-modal="#modal-program-editor">
                        <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                    </button>
                    <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-save-program">
                        💾 <?php esc_html_e( 'Guardar Programa', 'aura' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL B: CREAR / EDITAR MATERIA + MATERIALES PEDAGÓGICOS
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-subject-editor" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 660px;">
        <div class="aura-modal-header">
            <h3 id="modal-subj-title" class="adp-card-title" style="margin: 0; font-size: 18px;">
                📚 <?php esc_html_e( 'Añadir Materia', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-subject-editor">&times;</button>
        </div>

        <!-- Sub-navegación de pestañas en modal de materia -->
        <div class="aura-modal-subtabs" style="display: flex; gap: 4px; border-bottom: 1px solid var(--aura-border, #e2e8f0); padding: 0 20px; background: var(--aura-surface-alt, #f8fafc);">
            <button type="button" class="aura-modal-subtab-btn active" data-subtab="subj-tab-general" style="padding: 10px 14px; font-size: 13px; font-weight: 600; border: none; background: transparent; border-bottom: 2px solid var(--aura-primary, #5d5fef); color: var(--aura-primary, #5d5fef); cursor: pointer;">
                ℹ️ <?php esc_html_e( 'General', 'aura' ); ?>
            </button>
            <button type="button" class="aura-modal-subtab-btn" data-subtab="subj-tab-teacher-mat" style="padding: 10px 14px; font-size: 13px; font-weight: 600; border: none; background: transparent; border-bottom: 2px solid transparent; color: var(--aura-text-secondary, #64748b); cursor: pointer;">
                📁 <?php esc_html_e( 'Material Docente (Cátedra)', 'aura' ); ?>
            </button>
            <button type="button" class="aura-modal-subtab-btn" data-subtab="subj-tab-student-mat" style="padding: 10px 14px; font-size: 13px; font-weight: 600; border: none; background: transparent; border-bottom: 2px solid transparent; color: var(--aura-text-secondary, #64748b); cursor: pointer;">
                📖 <?php esc_html_e( 'Material para Alumnos', 'aura' ); ?>
            </button>
        </div>

        <form id="form-subject-editor" class="aura-modal-form">
            <div class="aura-modal-body" style="max-height: 70vh; overflow-y: auto;">
                <input type="hidden" name="id" id="subj-id" value="0">
                <input type="hidden" name="program_id" id="subj-prog-id" value="0">

                <!-- ── TAB 1: INFORMACIÓN GENERAL ── -->
                <div id="subj-tab-general" class="aura-modal-subtab-pane">
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <div>
                            <span style="font-size: 12px; color: var(--aura-text-secondary);"><?php esc_html_e( 'Programa:', 'aura' ); ?></span>
                            <strong id="subj-prog-name-display" style="display: block; font-size: 14px; color: var(--aura-primary);"></strong>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                <?php esc_html_e( 'Nombre de la Materia', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="text" name="name" id="subj-name" required class="form-control" placeholder="<?php esc_attr_e( 'Ej: Teología Sistemática I', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                    <?php esc_html_e( 'Código de Materia', 'aura' ); ?>
                                </label>
                                <input type="text" name="code" id="subj-code" class="form-control" placeholder="<?php esc_attr_e( 'TEO101', 'aura' ); ?>" style="width: 100%; border-radius: 8px; text-transform: uppercase;">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                    <?php esc_html_e( 'Horas Académicas', 'aura' ); ?>
                                </label>
                                <input type="number" name="total_hours" id="subj-hours" value="30" min="0" class="form-control" style="width: 100%; border-radius: 8px;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                🎨 <?php esc_html_e( 'Color de la Materia', 'aura' ); ?>
                            </label>
                            <div class="aura-color-picker-box">
                                <div class="aura-color-picker-row">
                                    <input type="color" name="color" id="subj-color" value="#3A86FF" class="aura-color-custom-input" title="<?php esc_attr_e( 'Color personalizado', 'aura' ); ?>">
                                    <span style="font-size: 12px; color: var(--aura-text-secondary);"><?php esc_html_e( 'Paleta de colores oficial:', 'aura' ); ?></span>
                                </div>
                                <?php echo Aura_Calendar_Admin::render_color_palette( 'subj-color', '#3A86FF' ); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                                👨‍🏫 <?php esc_html_e( 'Profesor(es) Titular(es)', 'aura' ); ?>
                            </label>
                            <div id="subj-teachers-container" class="aura-user-chips-container">
                                <!-- Inyectado dinámicamente con checkboxes desde JS -->
                            </div>
                            <small style="font-size: 11.5px; color: var(--aura-text-muted); display: block; margin-top: 4px;">
                                <?php esc_html_e( 'Puedes seleccionar uno o varios profesores titulares para esta materia.', 'aura' ); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- ── TAB 2: MATERIAL DOCENTE (CÁTEDRA) ── -->
                <div id="subj-tab-teacher-mat" class="aura-modal-subtab-pane" style="display: none;">
                    <div style="background: rgba(93,95,239,0.06); border: 1px solid rgba(93,95,239,0.2); border-radius: 8px; padding: 12px; margin-bottom: 14px; font-size: 12.5px; color: var(--aura-text-secondary);">
                        <strong style="color: var(--aura-primary); display: block; margin-bottom: 3px;">ℹ️ <?php esc_html_e( 'Material Pedagógico de Cátedra', 'aura' ); ?></strong>
                        <?php esc_html_e( 'Este material es exclusivo para el instructor. Al reasignar esta materia a otro profesor en el futuro, el nuevo docente tendrá acceso a estos documentos, guías de clase y presentaciones para continuar la enseñanza sin perder información.', 'aura' ); ?>
                    </div>

                    <!-- Botones de Acción de Material -->
                    <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
                        <input type="file" id="upload-teacher-file-input" style="display: none;">
                        <button type="button" class="btn btn-outline btn-upload-material" data-type="teacher" style="font-size: 12px; padding: 6px 12px;">
                            ☁️ <?php esc_html_e( 'Subir Archivo (Drive / Nube)', 'aura' ); ?>
                        </button>
                        <button type="button" class="btn btn-ghost btn-add-drive-link" data-type="teacher" style="font-size: 12px; padding: 6px 12px;">
                            🔗 <?php esc_html_e( 'Añadir Enlace Google Drive', 'aura' ); ?>
                        </button>
                    </div>

                    <!-- Lista de Materiales Docente -->
                    <div id="subj-teacher-materials-list" class="aura-materials-list" style="display: flex; flex-direction: column; gap: 8px;">
                        <p class="aura-empty-hint" style="font-size: 12px; color: var(--aura-text-muted); font-style: italic;">
                            <?php esc_html_e( 'No hay materiales de cátedra cargados aún.', 'aura' ); ?>
                        </p>
                    </div>
                </div>

                <!-- ── TAB 3: MATERIAL PARA ESTUDIANTES ── -->
                <div id="subj-tab-student-mat" class="aura-modal-subtab-pane" style="display: none;">
                    <div style="background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.2); border-radius: 8px; padding: 12px; margin-bottom: 14px; font-size: 12.5px; color: var(--aura-text-secondary);">
                        <strong style="color: #10b981; display: block; margin-bottom: 3px;">📖 <?php esc_html_e( 'Material de Estudio para Alumnos', 'aura' ); ?></strong>
                        <?php esc_html_e( 'Lecturas requeridas, guías de estudio, cuestionarios y documentos que los estudiantes matriculados en esta materia podrán consultar y descargar directamente desde su portal.', 'aura' ); ?>
                    </div>

                    <!-- Botones de Acción de Material Estudiantes -->
                    <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
                        <input type="file" id="upload-student-file-input" style="display: none;">
                        <button type="button" class="btn btn-outline btn-upload-material" data-type="student" style="font-size: 12px; padding: 6px 12px;">
                            ☁️ <?php esc_html_e( 'Subir Archivo para Alumnos', 'aura' ); ?>
                        </button>
                        <button type="button" class="btn btn-ghost btn-add-drive-link" data-type="student" style="font-size: 12px; padding: 6px 12px;">
                            🔗 <?php esc_html_e( 'Añadir Enlace Google Drive', 'aura' ); ?>
                        </button>
                    </div>

                    <!-- Lista de Materiales Estudiantes -->
                    <div id="subj-student-materials-list" class="aura-materials-list" style="display: flex; flex-direction: column; gap: 8px;">
                        <p class="aura-empty-hint" style="font-size: 12px; color: var(--aura-text-muted); font-style: italic;">
                            <?php esc_html_e( 'No hay materiales para alumnos cargados aún.', 'aura' ); ?>
                        </p>
                    </div>
                </div>

            </div>

            <div class="aura-modal-footer">
                <button type="button" class="btn btn-ghost" data-close-modal="#modal-subject-editor">
                    <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                </button>
                <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-save-subject">
                    💾 <?php esc_html_e( 'Guardar Materia', 'aura' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL C: IMPORTAR PROGRAMAS (JSON)
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-import-programs" class="aura-modal-overlay" style="display: none;">
    <div class="aura-modal-container" style="max-width: 500px;">
        <div class="aura-modal-header">
            <h3 class="adp-card-title" style="margin: 0; font-size: 18px;">
                📤 <?php esc_html_e( 'Importar Programas desde JSON', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" id="btn-cancel-import">&times;</button>
        </div>

        <div class="aura-modal-body" style="padding: 20px 24px;">
            <!-- Info box -->
            <div style="background: rgba(99,102,241,.07); border: 1px solid rgba(99,102,241,.2); border-radius: 10px; padding: 12px 14px; margin-bottom: 18px; font-size: 12.5px; color: var(--aura-text-secondary);">
                <strong style="color: var(--aura-primary); display: block; margin-bottom: 4px;">ℹ️ <?php esc_html_e( '¿Cómo funciona la importación?', 'aura' ); ?></strong>
                <?php esc_html_e( 'Sube un archivo .json exportado desde Aura. Se importarán los programas y sus materias. Los duplicados se pueden omitir automáticamente.', 'aura' ); ?>
            </div>

            <!-- Zona Drag & Drop -->
            <div id="import-dropzone" class="aura-import-dropzone" style="margin-bottom: 16px;">
                <div style="font-size: 36px; margin-bottom: 10px;">📤</div>
                <p style="font-size: 14px; font-weight: 600; margin: 0 0 4px;"><?php esc_html_e( 'Arrastra tu archivo JSON aquí', 'aura' ); ?></p>
                <p style="font-size: 12px; color: var(--aura-text-muted); margin: 0 0 14px;"><?php esc_html_e( 'o haz clic para seleccionar', 'aura' ); ?></p>
                <label for="import-file-input" class="btn btn-ghost" style="cursor: pointer; font-size: 13px;">
                    📂 <?php esc_html_e( 'Seleccionar archivo', 'aura' ); ?>
                </label>
                <input type="file" id="import-file-input" accept=".json" style="display: none;">
            </div>

            <!-- Opciones -->
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; margin-bottom: 16px;">
                <input type="checkbox" id="import-skip-existing" checked style="width: 16px; height: 16px; accent-color: var(--aura-primary);">
                <span><?php esc_html_e( 'Omitir programas que ya existen (recomendado)', 'aura' ); ?></span>
            </label>

            <!-- Área de resultado (oculta hasta que se importa) -->
            <div id="import-result" style="display: none; padding: 14px; background: var(--aura-surface-alt, #f8fafc); border-radius: 10px; border: 1px solid var(--aura-border); margin-bottom: 4px;"></div>
        </div>

        <div class="aura-modal-footer">
            <button type="button" class="btn btn-ghost" id="btn-cancel-import-2">
                <?php esc_html_e( 'Cancelar', 'aura' ); ?>
            </button>
            <button type="button" class="btn btn-indigo btn-shimmer btn-lift" id="btn-do-import" disabled>
                📤 <?php esc_html_e( 'Importar', 'aura' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL D: AÑADIR ENLACE DE MATERIAL (GOOGLE DRIVE / NUBE / WEB)
     ══════════════════════════════════════════════════════════════════ -->
<div id="modal-add-material-link" class="aura-modal-overlay" style="display: none; z-index: 100050;">
    <div class="aura-modal-container" style="max-width: 480px;">
        <div class="aura-modal-header">
            <h3 id="modal-add-link-title" class="adp-card-title" style="margin: 0; font-size: 17px;">
                🔗 <?php esc_html_e( 'Añadir Enlace de Material', 'aura' ); ?>
            </h3>
            <button type="button" class="aura-modal-close" data-close-modal="#modal-add-material-link">&times;</button>
        </div>
        <form id="form-add-material-link" class="aura-modal-form">
            <input type="hidden" id="add-link-type" value="teacher">
            <div class="aura-modal-body" style="padding: 20px 24px;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                        🔗 <?php esc_html_e( 'Enlace Compartido (Google Drive, Dropbox, Web)', 'aura' ); ?> <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="url" id="add-link-url" required class="form-control" placeholder="https://drive.google.com/file/d/..." style="width: 100%; border-radius: 8px;">
                </div>
                <div class="form-group" style="margin-bottom: 8px;">
                    <label class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
                        🏷️ <?php esc_html_e( 'Título descriptivo del documento', 'aura' ); ?>
                    </label>
                    <input type="text" id="add-link-title" class="form-control" placeholder="<?php esc_attr_e( 'Ej: Sílabo Oficial / Guía de Estudio', 'aura' ); ?>" style="width: 100%; border-radius: 8px;">
                </div>
            </div>
            <div class="aura-modal-footer">
                <button type="button" class="btn btn-ghost" data-close-modal="#modal-add-material-link">
                    <?php esc_html_e( 'Cancelar', 'aura' ); ?>
                </button>
                <button type="submit" class="btn btn-indigo btn-shimmer btn-lift" id="btn-save-material-link">
                    🔗 <?php esc_html_e( 'Añadir Enlace', 'aura' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     TOOLTIP FLOTANTE ENRIQUECIDO TIPO TARJETA (.aura-tip-card)
     DIRECTRIZ CANÓNICA: documentacion/TRACKER-MIGRACION-DESIGN-SYSTEM.md
     ══════════════════════════════════════════════════════════════════ -->
<div id="aura-subj-cal-tooltip" class="aura-subj-cal-tooltip aura-tip-card--subject" role="tooltip" aria-hidden="true">
    <div class="aura-tip-card">
        <!-- Header con avatar grande temático, título y badges -->
        <div class="aura-tip-card-header">
            <div class="aura-tip-avatar-large" id="aura-subj-cal-tip-avatar">
                <span id="aura-subj-cal-tip-code-badge" style="font-size: 13px; font-weight: 800; color: #ffffff;"></span>
            </div>
            <div class="aura-tip-info">
                <div class="aura-tip-title" id="aura-subj-cal-tip-name"></div>
                <div class="aura-tip-subtitle" id="aura-subj-cal-tip-prog"></div>
                <div class="aura-tip-badges" id="aura-subj-cal-tip-meta-badges">
                    <!-- Badges generados dinámicamente: Código, Horas y Conteo -->
                </div>
            </div>
        </div>

        <!-- Body: Bloques de fecha tipográficos (Date Tiles) sin emojis repetidos -->
        <div class="aura-tip-card-body">
            <div class="aura-tip-section-header">
                <span class="aura-tip-section-title"><?php esc_html_e( 'Fechas y Sesiones Programadas', 'aura' ); ?></span>
                <span id="aura-subj-cal-tip-count" class="badge badge-indigo badge-sm"></span>
            </div>
            <div class="aura-tip-sessions-list" id="aura-subj-cal-tip-list">
                <!-- Inyección dinámica de sesiones con Date Calendar Tile sin iconos repetidos -->
            </div>
        </div>

        <!-- Footer: Botón de acción con estilo WOW del Design System -->
        <div class="aura-tip-card-footer">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-calendar&tab=calendar' ) ); ?>" class="btn btn-sm btn-indigo btn-shimmer btn-lift" style="width: 100%; justify-content: center; text-decoration: none;">
                <?php esc_html_e( 'Ver Sesiones en el Calendario →', 'aura' ); ?>
            </a>
        </div>
    </div>
</div>


