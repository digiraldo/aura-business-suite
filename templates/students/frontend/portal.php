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
$photo_url = $student->photo_url ?? '';
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
            <h2><?php
                printf(
                    /* translators: %s: first name */
                    esc_html__( '👋 Hola, %s', 'aura-suite' ),
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
        <div class="aura-portal-logout">
            <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"
               class="aura-btn aura-btn-secondary aura-btn-sm">
                <?php esc_html_e( 'Cerrar sesión', 'aura-suite' ); ?>
            </a>
        </div>
    </div>

    <!-- ══════════════ NAVEGACIÓN DE PESTAÑAS ══════════════ -->
    <nav class="aura-portal-nav">
        <button class="aura-portal-tab-btn active" data-target="courses">
            📚 <?php esc_html_e( 'Mis Cursos', 'aura-suite' ); ?>
        </button>
        <button class="aura-portal-tab-btn" data-target="payments">
            💰 <?php esc_html_e( 'Mis Pagos', 'aura-suite' ); ?>
        </button>
        <button class="aura-portal-tab-btn" data-target="certs">
            🏅 <?php esc_html_e( 'Mis Certificados', 'aura-suite' ); ?>
        </button>
        <button class="aura-portal-tab-btn" data-target="forms">
            📋 <?php esc_html_e( 'Mis Formularios', 'aura-suite' ); ?>
        </button>
    </nav>

    <!-- ══════════════ PESTAÑA: MIS CURSOS ══════════════ -->
    <div id="aura-tab-courses" class="aura-portal-tab-content" data-tab="courses">
        <div id="aura-courses-loading" class="aura-loading">
            <?php esc_html_e( 'Cargando cursos…', 'aura-suite' ); ?>
        </div>
        <div id="aura-courses-container" style="display:none;"></div>
        <p id="aura-courses-empty" style="display:none;color:#6b7280;">
            <?php esc_html_e( 'Aún no estás inscrito en ningún curso.', 'aura-suite' ); ?>
        </p>
    </div>

    <!-- ══════════════ PESTAÑA: MIS PAGOS ══════════════ -->
    <?php include AURA_PLUGIN_DIR . 'templates/students/frontend/payment-history.php'; ?>

    <!-- ══════════════ PESTAÑA: MIS CERTIFICADOS ══════════════ -->
    <div id="aura-tab-certs" class="aura-portal-tab-content" data-tab="certs" style="display:none;">
        <div id="aura-certs-loading" class="aura-loading">
            <?php esc_html_e( 'Cargando certificados…', 'aura-suite' ); ?>
        </div>
        <div id="aura-certs-container" style="display:none;"></div>
        <p id="aura-certs-empty" style="display:none;color:#6b7280;">
            <?php esc_html_e( 'Aún no tienes certificados emitidos.', 'aura-suite' ); ?>
        </p>
    </div>

    <!-- ══════════════ PESTAÑA: MIS FORMULARIOS ══════════════ -->
    <div id="aura-tab-forms" class="aura-portal-tab-content" data-tab="forms" style="display:none;">
        <div id="aura-forms-loading" class="aura-loading">
            <?php esc_html_e( 'Cargando formularios…', 'aura-suite' ); ?>
        </div>
        <div id="aura-forms-container" style="display:none;"></div>
        <p id="aura-forms-error" style="display:none;color:#dc2626;"></p>
    </div>

</div><!-- /aura-student-portal -->
