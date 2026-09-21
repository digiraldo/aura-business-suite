<?php
/**
 * Template: Portal del Estudiante (pestañas completas)
 * Usado por shortcode [aura_student_portal]
 *
 * Variables disponibles:
 *  $student   — objeto de aura_students (fila completa)
 *  $nonce     — nonce de seguridad
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$status_labels = [
    'applicant' => [ 'label' => __( 'Postulante', 'aura-suite' ),  'cls' => 'status-gray'   ],
    'approved'  => [ 'label' => __( 'Aprobado',   'aura-suite' ),  'cls' => 'status-blue'   ],
    'active'    => [ 'label' => __( 'Activo',      'aura-suite' ),  'cls' => 'status-green'  ],
    'graduated' => [ 'label' => __( 'Graduado',    'aura-suite' ),  'cls' => 'status-gold'   ],
    'withdrawn' => [ 'label' => __( 'Retirado',    'aura-suite' ),  'cls' => 'status-orange' ],
    'rejected'  => [ 'label' => __( 'Rechazado',   'aura-suite' ),  'cls' => 'status-red'    ],
];

$stu_status     = $student->status ?? 'active';
$status_info    = $status_labels[ $stu_status ] ?? [ 'label' => ucfirst( $stu_status ), 'cls' => 'status-gray' ];

$profile_labels = [
    'student'     => __( 'Estudiante', 'aura-suite' ),
    'volunteer'   => __( 'Voluntario', 'aura-suite' ),
    'teacher'     => __( 'Instructor', 'aura-suite' ),
    'participant' => __( 'Participante', 'aura-suite' ),
    'intern'      => __( 'Practicante', 'aura-suite' ),
];
$profile_label = $profile_labels[ $student->profile_type ?? 'student' ] ?? ucfirst( $student->profile_type ?? '' );

$full_name = trim( ( $student->first_name ?? '' ) . ' ' . ( $student->last_name ?? '' ) );
global $wpdb;
$table_enroll = $wpdb->prefix . 'aura_student_enrollments';
$has_enroll_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_enroll}'" ) === $table_enroll;
$enroll_stats = null;
if ( $has_enroll_table && ! empty( $student->id ) ) {
    $enroll_stats = $wpdb->get_row( $wpdb->prepare(
        "SELECT SUM(net_cost) AS total_cost, SUM(total_paid) AS total_paid, SUM(balance_due) AS total_debt,
                COUNT(*) AS total_courses
         FROM {$table_enroll}
         WHERE student_id = %d AND status != 'cancelled'",
        $student->id
    ) );
}
?>
<div class="aura-portal-wrap" id="aura-student-portal">

    <!-- ══════════════ ENCABEZADO ══════════════ -->
    <div class="aura-portal-header">
        <div class="aura-portal-avatar">
            <?php if ( $photo_url ) : ?>
                <img src="<?php echo esc_url( $photo_url ); ?>"
                     alt="<?php echo esc_attr( $full_name ); ?>"
                     class="aura-avatar-img" />
            <?php else : ?>
                <div class="aura-avatar-placeholder"><?php echo esc_html( mb_strtoupper( mb_substr( $student->first_name ?? 'S', 0, 1 ) ) ); ?></div>
            <?php endif; ?>
        </div>
        <div class="aura-portal-greeting">
            <h2 style="display: flex; align-items: center; gap: 8px;"><?php
                echo '<span class="dashicons dashicons-admin-users" style="font-size: 22px; width: 22px; height: 22px;"></span>';
                printf(
                    /* translators: %s: first name */
                    esc_html__( 'Hola, %s', 'aura-suite' ),
                    esc_html( $student->first_name ?? '' )
                );
            ?></h2>
            <p>
                <?php echo esc_html( $profile_label ); ?>
                &nbsp;·&nbsp;
                <span class="aura-status-badge <?php echo esc_attr( $status_info['cls'] ); ?>">
                    <?php echo esc_html( $status_info['label'] ); ?>
                </span>
            </p>
        </div>
        <div class="aura-portal-logout" style="display: flex; align-items: center; gap: 8px;">
            <button type="button" class="aura-theme-toggle" aria-label="<?php esc_attr_e( 'Cambiar tema', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-moon"></span>
                <span class="aura-theme-toggle-label"><?php esc_html_e( 'Modo oscuro', 'aura-suite' ); ?></span>
            </button>
            <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"
               class="aura-btn aura-btn-secondary aura-btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="dashicons dashicons-migrate"></span>
                <span><?php esc_html_e( 'Cerrar sesión', 'aura-suite' ); ?></span>
            </a>
        </div>
    </div>

    <!-- ══════════════ BANNER DE ESTADO FINANCIERO Y PAZ Y SALVO ══════════════ -->
    <?php if ( $enroll_stats && (int) $enroll_stats->total_courses > 0 ) : 
        $tot_debt = floatval( $enroll_stats->total_debt ?? 0 );
        $tot_cost = floatval( $enroll_stats->total_cost ?? 0 );
        $tot_paid = floatval( $enroll_stats->total_paid ?? 0 );
    ?>
        <div class="adp-card" style="margin-bottom: 20px; padding: 14px 18px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: <?php echo $tot_debt > 0 ? 'rgba(245, 158, 11, 0.08)' : 'rgba(16, 185, 129, 0.08)'; ?>; border: 1px solid <?php echo $tot_debt > 0 ? '#f59e0b' : '#10b981'; ?>;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="font-size: 26px;">
                    <?php echo $tot_debt > 0 ? '💳' : '🎖️'; ?>
                </div>
                <div>
                    <?php if ( $tot_debt > 0 ) : ?>
                        <div style="font-weight: 700; font-size: 14px; color: #b45309;">
                            <?php esc_html_e( 'Estado Financiero del Programa: Saldo Pendiente de Pago', 'aura-suite' ); ?>
                        </div>
                        <div style="font-size: 12.5px; color: var(--aura-text-secondary, #64748b); margin-top: 2px;">
                            <?php printf( esc_html__( 'Tienes un saldo pendiente de %s (Abonado: %s de %s en tus inscripciones).', 'aura-suite' ), '<strong>$' . number_format( $tot_debt, 2 ) . '</strong>', '$' . number_format( $tot_paid, 2 ), '$' . number_format( $tot_cost, 2 ) ); ?>
                        </div>
                    <?php else : ?>
                        <div style="font-weight: 700; font-size: 14px; color: #047857;">
                            ✅ <?php esc_html_e( 'Paz y Salvo Académico y Financiero', 'aura-suite' ); ?>
                        </div>
                        <div style="font-size: 12.5px; color: var(--aura-text-secondary, #64748b); margin-top: 2px;">
                            <?php esc_html_e( '¡Felicitaciones! Te encuentras 100% al día con todos los costos de tus programas académicos.', 'aura-suite' ); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-ghost aura-portal-tab-btn" data-target="payments" style="font-size: 12px; padding: 6px 14px;">
                    💰 <?php esc_html_e( 'Ver Historial de Pagos', 'aura-suite' ); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- ══════════════ NAVEGACIÓN DE PESTAÑAS ══════════════ -->
    <nav class="aura-portal-nav">
        <button class="aura-portal-tab-btn active" data-target="courses" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="dashicons dashicons-book-alt"></span>
            <span><?php esc_html_e( 'Mis Cursos', 'aura-suite' ); ?></span>
        </button>
        <button class="aura-portal-tab-btn" data-target="schedule" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="dashicons dashicons-calendar-alt"></span>
            <span><?php esc_html_e( 'Mi Horario', 'aura-suite' ); ?></span>
        </button>
        <button class="aura-portal-tab-btn" data-target="payments" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="dashicons dashicons-money-alt"></span>
            <span><?php esc_html_e( 'Mis Pagos', 'aura-suite' ); ?></span>
        </button>
        <button class="aura-portal-tab-btn" data-target="tasks" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="dashicons dashicons-welcome-write-blog"></span>
            <span><?php esc_html_e( 'Mis Tareas y Lecturas', 'aura-suite' ); ?></span>
        </button>
        <button class="aura-portal-tab-btn" data-target="equipment" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="dashicons dashicons-admin-tools"></span>
            <span><?php esc_html_e( 'Mis Herramientas', 'aura-suite' ); ?></span>
        </button>
        <button class="aura-portal-tab-btn" data-target="certs" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="dashicons dashicons-awards"></span>
            <span><?php esc_html_e( 'Mis Certificados', 'aura-suite' ); ?></span>
        </button>
        <button class="aura-portal-tab-btn" data-target="forms" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="dashicons dashicons-clipboard"></span>
            <span><?php esc_html_e( 'Mis Encuestas', 'aura-suite' ); ?></span>
        </button>
    </nav>

    <!-- ══════════════ PESTAÑA 1: MIS CURSOS ══════════════ -->
    <div id="aura-tab-courses" class="aura-portal-tab-content" data-tab="courses">
        <div id="aura-courses-loading" class="aura-loading">
            <?php esc_html_e( 'Cargando cursos…', 'aura-suite' ); ?>
        </div>
        <div id="aura-courses-container" style="display:none;"></div>
        <p id="aura-courses-empty" style="display:none;color:#6b7280;">
            <?php esc_html_e( 'Aún no estás inscrito en ningún curso.', 'aura-suite' ); ?>
        </p>
    </div>

    <!-- ══════════════ PESTAÑA 2: MI HORARIO Y CALENDARIO ══════════════ -->
    <div id="aura-tab-schedule" class="aura-portal-tab-content" data-tab="schedule" style="display:none;">
        <?php echo do_shortcode( '[aura_student_schedule]' ); ?>
    </div>

    <!-- ══════════════ PESTAÑA 3: MIS PAGOS ══════════════ -->
    <?php include AURA_PLUGIN_DIR . 'templates/students/frontend/payment-history.php'; ?>

    <!-- ══════════════ PESTAÑA 4: MIS TAREAS Y CONTROLES DE LECTURA ══════════════ -->
    <div id="aura-tab-tasks" class="aura-portal-tab-content" data-tab="tasks" style="display:none;">
        <?php
        if ( file_exists( AURA_PLUGIN_DIR . 'templates/students/frontend/tasks.php' ) ) {
            include AURA_PLUGIN_DIR . 'templates/students/frontend/tasks.php';
        }
        ?>
    </div>

    <!-- ══════════════ PESTAÑA 5: MIS HERRAMIENTAS E INVENTARIO ══════════════ -->
    <div id="aura-tab-equipment" class="aura-portal-tab-content" data-tab="equipment" style="display:none;">
        <?php
        if ( file_exists( AURA_PLUGIN_DIR . 'templates/students/frontend/equipment.php' ) ) {
            include AURA_PLUGIN_DIR . 'templates/students/frontend/equipment.php';
        }
        ?>
    </div>

    <!-- ══════════════ PESTAÑA 6: MIS CERTIFICADOS ══════════════ -->
    <div id="aura-tab-certs" class="aura-portal-tab-content" data-tab="certs" style="display:none;">
        <div id="aura-certs-container" data-loaded="true">
            <?php
            if ( class_exists( 'Aura_Certificates_Frontend' ) ) {
                echo Aura_Certificates_Frontend::shortcode_mis_certificados();
            } else {
                echo do_shortcode( '[aura_mis_certificados]' );
            }
            ?>
        </div>
    </div>

    <!-- ══════════════ PESTAÑA 7: MIS ENCUESTAS Y FORMULARIOS ══════════════ -->
    <div id="aura-tab-forms" class="aura-portal-tab-content" data-tab="forms" style="display:none;">
        <div id="aura-forms-container" data-loaded="true">
            <?php
            if ( class_exists( 'Aura_Forms_Frontend' ) ) {
                echo Aura_Forms_Frontend::shortcode_portal( [] );
            } else {
                echo do_shortcode( '[aura_form_portal]' );
            }
            ?>
        </div>
    </div>

</div><!-- /aura-student-portal -->
