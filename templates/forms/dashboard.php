<?php
/**
 * Dashboard — Módulo de Formularios y Encuestas
 *
 * Muestra KPIs clave, resumen de actividad reciente y accesos rápidos.
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'aura_forms_view_responses_all' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

global $wpdb;

// ── KPI 1: Formularios activos ────────────────────────────────
$active_forms = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_forms WHERE is_active = 1 AND deleted_at IS NULL"
);
$total_forms  = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_forms WHERE deleted_at IS NULL"
);

// ── KPI 2: Submissions este mes ───────────────────────────────
$month_start = gmdate( 'Y-m-01 00:00:00' );
$subs_month  = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_submissions WHERE submitted_at >= %s",
        $month_start
    )
);
$subs_total = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_submissions"
);

// ── KPI 3: Inscripciones pendientes ───────────────────────────
$pending_enrollments = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_student_enrollments WHERE status = 'pending' OR status = 'pending_review'"
);

// ── KPI 4: Encuestas pendientes de responder ──────────────────
$pending_surveys = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_assignments WHERE status = 'pending'"
);

// ── Actividad últimos 7 días ─────────────────────────────────
$last7 = [];
for ( $i = 6; $i >= 0; $i-- ) {
    $day_key = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
    $last7[ $day_key ] = 0;
}

$rows_7d = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT DATE(submitted_at) AS day, COUNT(*) AS cnt
           FROM {$wpdb->prefix}aura_form_submissions
          WHERE submitted_at >= %s
       GROUP BY DATE(submitted_at)",
        gmdate( 'Y-m-d', strtotime( '-6 days' ) ) . ' 00:00:00'
    )
);

foreach ( $rows_7d as $row ) {
    if ( isset( $last7[ $row->day ] ) ) {
        $last7[ $row->day ] = (int) $row->cnt;
    }
}

$max7 = max( array_values( $last7 ) ?: [ 1 ] );

// ── Formularios más activos (top 5) ──────────────────────────
$top_forms = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT f.id, f.title, f.type, f.slug,
                COUNT(s.id) AS total
           FROM {$wpdb->prefix}aura_forms f
      LEFT JOIN {$wpdb->prefix}aura_form_submissions s ON s.form_id = f.id
          WHERE f.deleted_at IS NULL
       GROUP BY f.id
       ORDER BY total DESC
          LIMIT %d",
        5
    )
);

// ── Submissions recientes (últimas 5) ─────────────────────────
$recent_subs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT s.id, s.submitted_name, s.submitted_email, s.status, s.submitted_at,
                f.title AS form_title, f.id AS form_id
           FROM {$wpdb->prefix}aura_form_submissions s
           JOIN {$wpdb->prefix}aura_forms f ON f.id = s.form_id
       ORDER BY s.submitted_at DESC
          LIMIT %d",
        5
    )
);

$type_labels = [
    'generic'    => __( 'Genérico',    'aura-suite' ),
    'enrollment' => __( 'Inscripción', 'aura-suite' ),
    'survey'     => __( 'Encuesta',    'aura-suite' ),
    'feedback'   => __( 'Feedback',    'aura-suite' ),
];
$status_class = [
    'received' => 'aura-badge-info',
    'reviewed' => 'aura-badge-success',
    'spam'     => 'aura-badge-secondary',
];
?>
<div class="wrap aura-forms-wrap aura-forms-dashboard">
    <h1><?php esc_html_e( 'Formularios y Encuestas', 'aura-suite' ); ?></h1>

    <!-- ── KPI Cards ───────────────────────────────────────────── -->
    <div class="aura-kpi-grid">

        <div class="aura-kpi-card aura-kpi-primary">
            <div class="aura-kpi-icon"><span class="dashicons dashicons-feedback"></span></div>
            <div class="aura-kpi-content">
                <div class="aura-kpi-number"><?php echo absint( $active_forms ); ?></div>
                <div class="aura-kpi-label"><?php esc_html_e( 'Formularios activos', 'aura-suite' ); ?></div>
                <div class="aura-kpi-sub">
                    <?php printf(
                        /* translators: %d total form count */
                        esc_html__( '%d en total', 'aura-suite' ),
                        absint( $total_forms )
                    ); ?>
                </div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list' ) ); ?>" class="aura-kpi-link"></a>
        </div>

        <div class="aura-kpi-card aura-kpi-blue">
            <div class="aura-kpi-icon"><span class="dashicons dashicons-email-alt"></span></div>
            <div class="aura-kpi-content">
                <div class="aura-kpi-number"><?php echo absint( $subs_month ); ?></div>
                <div class="aura-kpi-label"><?php esc_html_e( 'Respuestas este mes', 'aura-suite' ); ?></div>
                <div class="aura-kpi-sub">
                    <?php printf(
                        /* translators: %d total submissions */
                        esc_html__( '%d en total', 'aura-suite' ),
                        absint( $subs_total )
                    ); ?>
                </div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list' ) ); ?>" class="aura-kpi-link"></a>
        </div>

        <div class="aura-kpi-card <?php echo $pending_enrollments > 0 ? 'aura-kpi-warning' : 'aura-kpi-neutral'; ?>">
            <div class="aura-kpi-icon"><span class="dashicons dashicons-groups"></span></div>
            <div class="aura-kpi-content">
                <div class="aura-kpi-number"><?php echo absint( $pending_enrollments ); ?></div>
                <div class="aura-kpi-label"><?php esc_html_e( 'Inscripciones pendientes', 'aura-suite' ); ?></div>
                <div class="aura-kpi-sub">
                    <?php $pending_enrollments > 0
                        ? esc_html_e( 'Requieren revisión', 'aura-suite' )
                        : esc_html_e( 'Sin pendientes', 'aura-suite' ); ?>
                </div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-enrollments' ) ); ?>" class="aura-kpi-link"></a>
        </div>

        <div class="aura-kpi-card <?php echo $pending_surveys > 0 ? 'aura-kpi-purple' : 'aura-kpi-neutral'; ?>">
            <div class="aura-kpi-icon"><span class="dashicons dashicons-chart-bar"></span></div>
            <div class="aura-kpi-content">
                <div class="aura-kpi-number"><?php echo absint( $pending_surveys ); ?></div>
                <div class="aura-kpi-label"><?php esc_html_e( 'Encuestas asignadas', 'aura-suite' ); ?></div>
                <div class="aura-kpi-sub"><?php esc_html_e( 'pendientes de completar', 'aura-suite' ); ?></div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-assignments' ) ); ?>" class="aura-kpi-link"></a>
        </div>

    </div><!-- .aura-kpi-grid -->

    <div class="aura-dash-row">

        <!-- ── Actividad últimos 7 días ─────────────────────────── -->
        <div class="aura-dash-col aura-dash-col-wide">
            <div class="aura-panel-box">
                <h2 class="aura-panel-title"><?php esc_html_e( 'Actividad — últimos 7 días', 'aura-suite' ); ?></h2>
                <div class="aura-sparkbar-wrap">
                    <?php foreach ( $last7 as $day => $cnt ) :
                        $pct   = $max7 > 0 ? round( ( $cnt / $max7 ) * 100 ) : 0;
                        $label = wp_date( 'D', strtotime( $day ) );
                    ?>
                    <div class="aura-sparkbar-col">
                        <div class="aura-sparkbar-count"><?php echo $cnt > 0 ? absint( $cnt ) : ''; ?></div>
                        <div class="aura-sparkbar-bar-wrap">
                            <div class="aura-sparkbar-bar" style="height:<?php echo $pct; ?>%"></div>
                        </div>
                        <div class="aura-sparkbar-label"><?php echo esc_html( $label ); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── Top 5 formularios ───────────────────────────────── -->
        <div class="aura-dash-col">
            <div class="aura-panel-box">
                <h2 class="aura-panel-title"><?php esc_html_e( 'Formularios más activos', 'aura-suite' ); ?></h2>
                <?php if ( empty( $top_forms ) ) : ?>
                    <p class="aura-text-muted"><?php esc_html_e( 'Sin formularios aún.', 'aura-suite' ); ?></p>
                <?php else : ?>
                <ul class="aura-top-list">
                    <?php foreach ( $top_forms as $tf ) : ?>
                    <li>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list&action=responses&id=' . $tf->id ) ); ?>">
                            <?php echo esc_html( $tf->title ); ?>
                        </a>
                        <span class="aura-top-type"><?php echo esc_html( $type_labels[ $tf->type ] ?? $tf->type ); ?></span>
                        <strong class="aura-top-count"><?php echo absint( $tf->total ); ?></strong>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- .aura-dash-row -->

    <!-- ── Respuestas recientes ───────────────────────────────── -->
    <div class="aura-panel-box" style="margin-top:16px">
        <h2 class="aura-panel-title" style="display:flex;justify-content:space-between;align-items:center">
            <span><?php esc_html_e( 'Respuestas recientes', 'aura-suite' ); ?></span>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list' ) ); ?>" class="button button-small">
                <?php esc_html_e( 'Ver todas', 'aura-suite' ); ?>
            </a>
        </h2>
        <?php if ( empty( $recent_subs ) ) : ?>
            <p class="aura-text-muted"><?php esc_html_e( 'Aún no se han recibido respuestas.', 'aura-suite' ); ?></p>
        <?php else : ?>
        <table class="wp-list-table widefat fixed striped" style="margin-top:0">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th><?php esc_html_e( 'Nombre', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Formulario', 'aura-suite' ); ?></th>
                    <th style="width:100px"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                    <th style="width:130px"><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $recent_subs as $rs ) : ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list&action=view-submission&sub_id=' . $rs->id . '&form_id=' . $rs->form_id ) ); ?>">
                            #<?php echo absint( $rs->id ); ?>
                        </a>
                    </td>
                    <td>
                        <?php echo $rs->submitted_name
                            ? esc_html( $rs->submitted_name )
                            : '<em class="aura-text-muted">' . esc_html__( '(anónimo)', 'aura-suite' ) . '</em>'; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-list&action=responses&id=' . $rs->form_id ) ); ?>">
                            <?php echo esc_html( $rs->form_title ); ?>
                        </a>
                    </td>
                    <td>
                        <span class="aura-badge <?php echo esc_attr( $status_class[ $rs->status ] ?? '' ); ?>">
                            <?php echo esc_html( ucfirst( $rs->status ) ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( wp_date( 'j M, H:i', strtotime( $rs->submitted_at ) ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ── Accesos rápidos ───────────────────────────────────── -->
    <div class="aura-quick-links" style="margin-top:16px">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-new' ) ); ?>" class="aura-quick-btn">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php esc_html_e( 'Nuevo formulario', 'aura-suite' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-enrollments' ) ); ?>" class="aura-quick-btn">
            <span class="dashicons dashicons-groups"></span>
            <?php esc_html_e( 'Revisar postulantes', 'aura-suite' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-assignments' ) ); ?>" class="aura-quick-btn">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e( 'Asignar encuestas', 'aura-suite' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-analytics' ) ); ?>" class="aura-quick-btn">
            <span class="dashicons dashicons-chart-area"></span>
            <?php esc_html_e( 'Análisis', 'aura-suite' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-reports' ) ); ?>" class="aura-quick-btn">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php esc_html_e( 'Reportes', 'aura-suite' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-forms-settings' ) ); ?>" class="aura-quick-btn">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'Configuración', 'aura-suite' ); ?>
        </a>
    </div>

</div><!-- .aura-forms-dashboard -->

<style>
/* ── KPI Grid ───────────────────────────────────────────────── */
.aura-forms-dashboard { max-width: 1400px; }
.aura-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin: 16px 0;
}
.aura-kpi-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 14px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 16px 18px;
    overflow: hidden;
    transition: box-shadow .15s;
}
.aura-kpi-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.1); }
.aura-kpi-link {
    position: absolute; inset: 0;
    z-index: 1;
}
.aura-kpi-icon { font-size: 32px; line-height: 1; flex-shrink: 0; }
.aura-kpi-number { font-size: 32px; font-weight: 700; line-height: 1.1; }
.aura-kpi-label  { font-size: 12px; color: #555; margin-top: 2px; }
.aura-kpi-sub    { font-size: 11px; color: #999; margin-top: 2px; }
.aura-kpi-primary { border-left: 4px solid #0073aa; }
.aura-kpi-primary .aura-kpi-icon  { color: #0073aa; }
.aura-kpi-primary .aura-kpi-number { color: #0073aa; }
.aura-kpi-blue { border-left: 4px solid #3182ce; }
.aura-kpi-blue .aura-kpi-icon  { color: #3182ce; }
.aura-kpi-blue .aura-kpi-number { color: #3182ce; }
.aura-kpi-warning { border-left: 4px solid #d97706; }
.aura-kpi-warning .aura-kpi-icon  { color: #d97706; }
.aura-kpi-warning .aura-kpi-number { color: #d97706; }
.aura-kpi-purple { border-left: 4px solid #7c3aed; }
.aura-kpi-purple .aura-kpi-icon  { color: #7c3aed; }
.aura-kpi-purple .aura-kpi-number { color: #7c3aed; }
.aura-kpi-neutral { border-left: 4px solid #9ca3af; }
.aura-kpi-neutral .aura-kpi-icon  { color: #9ca3af; }
.aura-kpi-neutral .aura-kpi-number { color: #9ca3af; }
/* ── Dashboard row ──────────────────────────────────────────── */
.aura-dash-row {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 14px;
    margin-top: 14px;
}
.aura-panel-box {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 16px 20px;
}
.aura-panel-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: #666;
    margin: 0 0 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}
/* ── Sparkbar Chart ─────────────────────────────────────────── */
.aura-sparkbar-wrap {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    height: 90px;
}
.aura-sparkbar-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
}
.aura-sparkbar-count {
    font-size: 10px;
    color: #888;
    margin-bottom: 2px;
    height: 14px;
    line-height: 14px;
}
.aura-sparkbar-bar-wrap {
    flex: 1;
    width: 100%;
    background: #f1f5f9;
    border-radius: 2px 2px 0 0;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
}
.aura-sparkbar-bar {
    width: 100%;
    background: #0073aa;
    border-radius: 2px 2px 0 0;
    transition: height .3s ease;
    min-height: 2px;
}
.aura-sparkbar-label {
    font-size: 10px;
    color: #888;
    margin-top: 4px;
}
/* ── Top list ───────────────────────────────────────────────── */
.aura-top-list { margin: 0; padding: 0; list-style: none; }
.aura-top-list li {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 0;
    border-bottom: 1px solid #f6f6f6;
    font-size: 12px;
}
.aura-top-list li:last-child { border-bottom: none; }
.aura-top-list li a { flex: 1; color: #1d2327; text-decoration: none; font-size: 12px; }
.aura-top-list li a:hover { color: #0073aa; }
.aura-top-type { color: #999; font-size: 10px; white-space: nowrap; }
.aura-top-count { font-size: 13px; color: #0073aa; min-width: 24px; text-align: right; }
/* ── Quick links ────────────────────────────────────────────── */
.aura-quick-links { display: flex; gap: 8px; flex-wrap: wrap; }
.aura-quick-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 14px;
    font-size: 12px;
    color: #444;
    text-decoration: none;
    transition: border-color .15s, color .15s;
}
.aura-quick-btn:hover { border-color: #0073aa; color: #0073aa; }
.aura-quick-btn .dashicons { font-size: 16px; width: 16px; height: 16px; }
/* ── Misc ───────────────────────────────────────────────────── */
.aura-text-muted { color: #bbb; font-style: italic; }
@media (max-width: 1024px) {
    .aura-kpi-grid  { grid-template-columns: repeat(2, 1fr); }
    .aura-dash-row  { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .aura-kpi-grid { grid-template-columns: 1fr; }
}
</style>
