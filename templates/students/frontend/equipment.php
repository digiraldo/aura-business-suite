<?php
/**
 * Template: Mis Herramientas y Equipos en Préstamo — Portal del Estudiante
 *
 * Muestra los equipos y herramientas asignadas a cargo del estudiante desde el
 * inventario institucional, fechas de retiro/devolución y responsable de entrega.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_loans = $wpdb->prefix . 'aura_inventory_loans';
$table_equip = $wpdb->prefix . 'aura_inventory_equipment';

$wp_user_id = intval( $student->wp_user_id ?? 0 );
$full_name  = trim( ( $student->first_name ?? '' ) . ' ' . ( $student->last_name ?? '' ) );

$has_loans_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_loans}'" ) === $table_loans;
$has_equip_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table_equip}'" ) === $table_equip;

$my_loans = [];
if ( $has_loans_table && $has_equip_table ) {
    $name_like = '%' . $wpdb->esc_like( $full_name ) . '%';

    $my_loans = $wpdb->get_results( $wpdb->prepare(
        "SELECT l.*,
                e.name AS equipment_name, e.code AS equipment_code, e.brand, e.model, e.serial_number, e.photo_url, e.accessories,
                u.display_name AS registered_by_name
         FROM {$table_loans} l
         LEFT JOIN {$table_equip} e ON e.id = l.equipment_id
         LEFT JOIN {$wpdb->users} u ON u.ID = l.registered_by
         WHERE (l.borrowed_by_user_id = %d OR (l.borrowed_to_name != '' AND l.borrowed_to_name LIKE %s))
         ORDER BY (l.actual_return_date IS NULL AND l.returned_at IS NULL) DESC, l.loan_date DESC",
        $wp_user_id,
        $name_like
    ) );
}
?>

<div class="aura-student-equipment-wrapper" style="margin-top: 10px;">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 14px;">
        <div>
            <h3 class="adp-card-title" style="font-size: 18px; margin: 0; display: inline-flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-admin-tools" style="color: #6366f1;"></span>
                <span><?php esc_html_e( 'Herramientas y Equipos a Cargo', 'aura-suite' ); ?></span>
            </h3>
            <p class="adp-card-desc" style="margin: 4px 0 0 0; font-size: 13px; color: var(--aura-text-secondary, #64748b);">
                <?php esc_html_e( 'Control de materiales, herramientas y activos de inventario prestados bajo tu responsabilidad.', 'aura-suite' ); ?>
            </p>
        </div>
        <div style="font-size: 12px; color: var(--aura-text-muted, #94a3b8);">
            📦 <?php echo count( $my_loans ); ?> <?php esc_html_e( 'registros de inventario', 'aura-suite' ); ?>
        </div>
    </div>

    <?php if ( empty( $my_loans ) ) : ?>
        <div class="adp-card" style="text-align: center; padding: 48px 24px; border-radius: 12px; background: var(--aura-surface, #ffffff); border: 1px solid var(--aura-border, #e2e8f0);">
            <div style="font-size: 40px; margin-bottom: 12px;">🧰</div>
            <h4 style="font-size: 17px; margin-bottom: 6px; color: var(--aura-text-primary, #0f172a);">
                <?php esc_html_e( 'No tienes herramientas ni equipos a tu cargo', 'aura-suite' ); ?>
            </h4>
            <p style="color: var(--aura-text-secondary, #64748b); max-width: 440px; margin: 0 auto; font-size: 13px;">
                <?php esc_html_e( 'Cuando te asignen equipo o herramientas para prácticas, proyectos o talleres, se registrarán aquí con sus fechas y responsable.', 'aura-suite' ); ?>
            </p>
        </div>
    <?php else : ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 18px;">
            <?php foreach ( $my_loans as $loan ) :
                $is_returned = ! empty( $loan->actual_return_date ) || ! empty( $loan->returned_at );
                $today       = current_time( 'Y-m-d' );
                $is_overdue  = ! $is_returned && ! empty( $loan->expected_return_date ) && ( $loan->expected_return_date < $today );
            ?>
                <div class="adp-card" style="padding: 20px; border-radius: 12px; background: var(--aura-surface, #ffffff); border: 1px solid var(--aura-border, #e2e8f0); display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; gap: 8px;">
                            <span class="adp-badge badge-indigo" style="font-family: monospace; font-size: 11px;">
                                🏷️ <?php echo esc_html( $loan->equipment_code ?: 'EQ-#' . $loan->equipment_id ); ?>
                            </span>

                            <?php if ( $is_returned ) : ?>
                                <span class="adp-badge badge-slate" style="font-size: 11px;">
                                    ✅ <?php esc_html_e( 'Devuelto', 'aura-suite' ); ?>
                                </span>
                            <?php elseif ( $is_overdue ) : ?>
                                <span class="adp-badge badge-amber" style="color: #ef4444; border-color: rgba(239,68,68,0.3); font-size: 11px;">
                                    ⚠️ <?php esc_html_e( 'Devolución Atrasada', 'aura-suite' ); ?>
                                </span>
                            <?php else : ?>
                                <span class="adp-badge badge-emerald has-dot" style="font-size: 11px;">
                                    <span class="pulse-dot"></span> <?php esc_html_e( 'En Uso / A tu cargo', 'aura-suite' ); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; gap: 14px; align-items: flex-start; margin-bottom: 14px;">
                            <?php if ( ! empty( $loan->photo_url ) ) : ?>
                                <img src="<?php echo esc_url( $loan->photo_url ); ?>" alt="<?php echo esc_attr( $loan->equipment_name ); ?>" style="width: 52px; height: 52px; border-radius: 8px; object-fit: cover; border: 1px solid var(--aura-border, #e2e8f0); flex-shrink: 0;">
                            <?php else : ?>
                                <div style="width: 52px; height: 52px; border-radius: 8px; background: rgba(99,102,241,0.1); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;">
                                    🔧
                                </div>
                            <?php endif; ?>

                            <div style="line-height: 1.35;">
                                <h4 style="font-size: 15px; font-weight: 700; margin: 0 0 4px 0; color: var(--aura-text-primary, #0f172a);">
                                    <?php echo esc_html( $loan->equipment_name ?: __( 'Herramienta de Inventario', 'aura-suite' ) ); ?>
                                </h4>
                                <?php if ( ! empty( $loan->brand ) || ! empty( $loan->model ) ) : ?>
                                    <div style="font-size: 12px; color: var(--aura-text-muted, #94a3b8);">
                                        <?php echo esc_html( trim( ( $loan->brand ?? '' ) . ' ' . ( $loan->model ?? '' ) ) ); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ( ! empty( $loan->serial_number ) ) : ?>
                                    <div style="font-size: 11px; color: var(--aura-text-muted, #94a3b8); font-family: monospace;">
                                        S/N: <?php echo esc_html( $loan->serial_number ); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Detalles del Préstamo -->
                        <div style="background: var(--aura-surface-alt, #f8fafc); border-radius: 8px; padding: 10px 12px; font-size: 12px; display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px;">
                            <?php if ( ! empty( $loan->registered_by_name ) ) : ?>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--aura-text-muted, #94a3b8);">👤 <?php esc_html_e( 'Entregado por:', 'aura-suite' ); ?></span>
                                    <strong style="color: var(--aura-text-primary, #0f172a);"><?php echo esc_html( $loan->registered_by_name ); ?></strong>
                                </div>
                            <?php endif; ?>

                            <?php if ( ! empty( $loan->project ) ) : ?>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--aura-text-muted, #94a3b8);">🎯 <?php esc_html_e( 'Destino / Proyecto:', 'aura-suite' ); ?></span>
                                    <span style="color: var(--aura-text-primary, #0f172a);"><?php echo esc_html( $loan->project ); ?></span>
                                </div>
                            <?php endif; ?>

                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--aura-text-muted, #94a3b8);">📅 <?php esc_html_e( 'Fecha Asignación:', 'aura-suite' ); ?></span>
                                <span style="color: var(--aura-text-primary, #0f172a);"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $loan->loan_date ) ) ); ?></span>
                            </div>

                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--aura-text-muted, #94a3b8);">⏰ <?php esc_html_e( 'Límite Devolución:', 'aura-suite' ); ?></span>
                                <strong style="color: <?php echo $is_overdue ? '#ef4444' : 'var(--aura-text-primary, #0f172a)'; ?>;">
                                    <?php echo ! empty( $loan->expected_return_date ) ? esc_html( date_i18n( 'd/m/Y', strtotime( $loan->expected_return_date ) ) ) : __( 'Indefinido', 'aura-suite' ); ?>
                                </strong>
                            </div>

                            <?php if ( $is_returned ) : ?>
                                <div style="display: flex; justify-content: space-between; border-top: 1px dashed var(--aura-border, #e2e8f0); padding-top: 4px; margin-top: 2px;">
                                    <span style="color: #10b981;">✅ <?php esc_html_e( 'Fecha de Entrega:', 'aura-suite' ); ?></span>
                                    <span style="color: #10b981; font-weight: 600;"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $loan->actual_return_date ?: $loan->returned_at ) ) ); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ( ! empty( $loan->accessories ) ) : ?>
                            <div style="font-size: 11.5px; color: var(--aura-text-secondary, #64748b); margin-bottom: 6px;">
                                <strong><?php esc_html_e( 'Accesorios:', 'aura-suite' ); ?></strong> <?php echo esc_html( $loan->accessories ); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="border-top: 1px solid var(--aura-border, #e2e8f0); padding-top: 10px; margin-top: 10px; font-size: 11.5px; color: var(--aura-text-muted, #94a3b8); display: flex; justify-content: space-between; align-items: center;">
                        <span><?php esc_html_e( 'Estado inicial:', 'aura-suite' ); ?> <strong><?php echo esc_html( ucfirst( $loan->equipment_state_out ?? 'good' ) ); ?></strong></span>
                        <?php if ( ! $is_returned ) : ?>
                            <span style="color: #6366f1; font-weight: 600;">🔒 <?php esc_html_e( 'Bajo resguardo', 'aura-suite' ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
