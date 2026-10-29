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

// ── Validación de los 3 Ejes de Paz y Salvo Integral ──
$tot_debt = floatval( $enroll_stats->total_debt ?? 0 );
$tot_cost = floatval( $enroll_stats->total_cost ?? 0 );
$tot_paid = floatval( $enroll_stats->total_paid ?? 0 );
$wp_uid   = intval( $student->wp_user_id ?? get_current_user_id() );

// 1. Recursos: Libros adeudados en Biblioteca
$table_lib_loans = $wpdb->prefix . 'aura_library_loans';
$pending_books   = 0;
if ( $wp_uid > 0 && $wpdb->get_var( "SHOW TABLES LIKE '{$table_lib_loans}'" ) === $table_lib_loans ) {
    $pending_books = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_lib_loans} WHERE borrower_user_id = %d AND status IN ('active','overdue')",
        $wp_uid
    ) );
}

// 2. Recursos: Equipos/herramientas adeudados en Inventario
$table_inv_loans = $wpdb->prefix . 'aura_inventory_loans';
$pending_tools   = 0;
if ( $wp_uid > 0 && $wpdb->get_var( "SHOW TABLES LIKE '{$table_inv_loans}'" ) === $table_inv_loans ) {
    $name_like = '%' . $wpdb->esc_like( $full_name ) . '%';
    $pending_tools = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_inv_loans} WHERE (borrowed_by_user_id = %d OR (borrowed_to_name != '' AND borrowed_to_name LIKE %s)) AND actual_return_date IS NULL AND returned_at IS NULL",
        $wp_uid,
        $name_like
    ) );
}

// 3. Certificados y Diplomas emitidos
$table_certs  = $wpdb->prefix . 'aura_certificates';
$active_certs = [];
if ( ! empty( $student->id ) && $wpdb->get_var( "SHOW TABLES LIKE '{$table_certs}'" ) === $table_certs ) {
    $active_certs = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, folio, course_name, program_name, issued_at, status, verify_url
         FROM {$table_certs}
         WHERE student_id = %d AND status = 'active'
         ORDER BY issued_at DESC",
        $student->id
    ) );
}

$is_financially_clear = ( $tot_debt <= 0.009 );
$is_fully_paz_salvo   = ( $is_financially_clear && $pending_books === 0 && $pending_tools === 0 );
$is_graduated         = ( $stu_status === 'graduated' || ! empty( $active_certs ) );
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

    <!-- ══════════════ BANNER DE ESTADO FINANCIERO Y PAZ Y SALVO INTEGRAL ══════════════ -->
    <?php if ( $enroll_stats && (int) $enroll_stats->total_courses > 0 ) : ?>
        <div class="adp-card" style="margin-bottom: 20px; padding: 16px 20px; border-radius: 12px; background: <?php echo $is_fully_paz_salvo ? 'rgba(16, 185, 129, 0.08)' : 'rgba(245, 158, 11, 0.08)'; ?>; border: 1.5px solid <?php echo $is_fully_paz_salvo ? '#10b981' : '#f59e0b'; ?>;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                <div style="display: flex; align-items: flex-start; gap: 14px; max-width: 780px;">
                    <div style="font-size: 32px; line-height: 1;">
                        <?php echo $is_fully_paz_salvo ? '🎖️' : '⚠️'; ?>
                    </div>
                    <div>
                        <?php if ( $is_fully_paz_salvo ) : ?>
                            <div style="font-weight: 700; font-size: 15px; color: #047857; display: flex; align-items: center; gap: 6px;">
                                <span>✅ <?php esc_html_e( 'Paz y Salvo Integral: Financiero, Académico y de Recursos', 'aura-suite' ); ?></span>
                            </div>
                            <div style="font-size: 13px; color: var(--aura-text-secondary, #64748b); margin-top: 3px;">
                                <?php esc_html_e( '¡Felicitaciones! Te encuentras al día en tus cuotas de programa, no adeudas libros de biblioteca ni herramientas de inventario.', 'aura-suite' ); ?>
                            </div>

                            <?php if ( ! empty( $active_certs ) ) : 
                                $main_cert = $active_certs[0];
                                $dl_url = add_query_arg( [
                                    'action' => 'aura_cert_download',
                                    'folio'  => $main_cert->folio,
                                    'nonce'  => wp_create_nonce( 'aura_download_' . $main_cert->folio ),
                                ], admin_url( 'admin-ajax.php' ) );
                            ?>
                                <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <a href="<?php echo esc_url( $dl_url ); ?>" class="btn btn-emerald btn-lift" style="font-size: 12.5px; padding: 7px 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                                        🎓 <?php esc_html_e( 'Descargar Diploma Oficial (PDF)', 'aura-suite' ); ?>
                                    </a>
                                    <?php if ( ! empty( $main_cert->verify_url ) ) : ?>
                                        <a href="<?php echo esc_url( $main_cert->verify_url ); ?>" target="_blank" class="btn btn-ghost" style="font-size: 12px; padding: 6px 12px; text-decoration: none;">
                                            🔍 <?php esc_html_e( 'Verificar QR Oficial', 'aura-suite' ); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ( $is_graduated ) : ?>
                                <div style="margin-top: 6px; font-size: 12.5px; color: #065f46; font-weight: 600;">
                                    🎓 <?php esc_html_e( 'Estado: Graduado. Tu diploma oficial se encuentra en emisión final por la directiva académica.', 'aura-suite' ); ?>
                                </div>
                            <?php endif; ?>

                        <?php else : ?>
                            <div style="font-weight: 700; font-size: 15px; color: #b45309;">
                                ⚠️ <?php esc_html_e( 'Requisitos Pendientes para Paz y Salvo Oficial', 'aura-suite' ); ?>
                            </div>
                            <div style="font-size: 12.5px; color: var(--aura-text-secondary, #64748b); margin-top: 4px; display: flex; flex-direction: column; gap: 3px;">
                                <?php if ( ! $is_financially_clear ) : ?>
                                    <div>💳 <strong><?php esc_html_e( 'Saldo pendiente del programa:', 'aura-suite' ); ?></strong> <?php printf( esc_html__( '%s (Abonado: %s de %s)', 'aura-suite' ), '<strong>$' . number_format( $tot_debt, 2 ) . '</strong>', '$' . number_format( $tot_paid, 2 ), '$' . number_format( $tot_cost, 2 ) ); ?></div>
                                <?php endif; ?>
                                <?php if ( $pending_books > 0 ) : ?>
                                    <div>📖 <strong><?php esc_html_e( 'Biblioteca:', 'aura-suite' ); ?></strong> <?php printf( esc_html__( 'Tienes %d libro(s) pendiente(s) de devolución.', 'aura-suite' ), $pending_books ); ?></div>
                                <?php endif; ?>
                                <?php if ( $pending_tools > 0 ) : ?>
                                    <div>🛠️ <strong><?php esc_html_e( 'Inventario:', 'aura-suite' ); ?></strong> <?php printf( esc_html__( 'Tienes %d equipo(s) o herramienta(s) bajo tu cargo sin retornar.', 'aura-suite' ), $pending_tools ); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php if ( ! $is_financially_clear ) : ?>
                        <button type="button" class="btn btn-sm btn-ghost aura-portal-tab-btn" data-target="payments" style="font-size: 12px; padding: 6px 14px; border: 1px solid rgba(245, 158, 11, 0.4);">
                            💰 <?php esc_html_e( 'Ver Historial de Pagos', 'aura-suite' ); ?>
                        </button>
                    <?php endif; ?>
                    <?php if ( $pending_tools > 0 ) : ?>
                        <button type="button" class="btn btn-sm btn-ghost aura-portal-tab-btn" data-target="equipment" style="font-size: 12px; padding: 6px 14px; border: 1px solid rgba(245, 158, 11, 0.4);">
                            🧰 <?php esc_html_e( 'Mis Herramientas', 'aura-suite' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
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
