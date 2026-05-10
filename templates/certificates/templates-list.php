<?php
/**
 * Template: Listado de Plantillas de Diseño
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table = $wpdb->prefix . 'aura_certificate_templates';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$templates = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY is_default DESC, name ASC" );

$can_create = current_user_can( 'aura_cert_template_create' ) || current_user_can( 'manage_options' );
$can_delete = current_user_can( 'aura_cert_template_delete' ) || current_user_can( 'manage_options' );
$builder_url = admin_url( 'admin.php?page=aura-certificates-templates&action=edit' );
?>
<div class="wrap aura-certificates-wrap">
    <h1 class="wp-heading-inline">🎨 <?php esc_html_e( 'Plantillas de Certificado', 'aura-suite' ); ?></h1>
    <?php if ( $can_create ) : ?>
    <a href="<?php echo esc_url( $builder_url . '&id=new' ); ?>" class="page-title-action">
        <?php esc_html_e( '+ Nueva Plantilla', 'aura-suite' ); ?>
    </a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php if ( empty( $templates ) ) : ?>
        <div class="notice notice-info inline">
            <p>
                <?php esc_html_e( 'No hay plantillas creadas. Crea la primera plantilla con el editor visual.', 'aura-suite' ); ?>
                <?php if ( $can_create ) : ?>
                <a href="<?php echo esc_url( $builder_url . '&id=new' ); ?>" class="button button-primary" style="margin-left:8px;">
                    <?php esc_html_e( 'Crear Plantilla', 'aura-suite' ); ?>
                </a>
                <?php endif; ?>
            </p>
        </div>
    <?php else : ?>
    <div class="aura-cert-templates-grid">
        <?php foreach ( $templates as $tpl ) : ?>
        <div class="aura-cert-template-card <?php echo $tpl->is_active ? '' : 'aura-cert-template-card--inactive'; ?>"
             data-id="<?php echo esc_attr( $tpl->id ); ?>">

            <!-- Thumbnail -->
            <div class="aura-cert-template-thumb">
                <?php if ( ! empty( $tpl->thumbnail_url ) ) : ?>
                    <img src="<?php echo esc_url( $tpl->thumbnail_url ); ?>" alt="<?php echo esc_attr( $tpl->name ); ?>">
                <?php else : ?>
                    <div class="aura-cert-template-no-thumb">
                        <span>🖼️</span>
                        <small><?php esc_html_e( 'Sin preview', 'aura-suite' ); ?></small>
                    </div>
                <?php endif; ?>

                <?php if ( $tpl->is_default ) : ?>
                <span class="aura-cert-badge aura-cert-badge--default">
                    <?php esc_html_e( 'Predeterminada', 'aura-suite' ); ?>
                </span>
                <?php endif; ?>

                <?php if ( ! $tpl->is_active ) : ?>
                <span class="aura-cert-badge aura-cert-badge--inactive">
                    <?php esc_html_e( 'Inactiva', 'aura-suite' ); ?>
                </span>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="aura-cert-template-info">
                <h3 class="aura-cert-template-name"><?php echo esc_html( $tpl->name ); ?></h3>
                <?php if ( $tpl->description ) : ?>
                <p class="aura-cert-template-desc"><?php echo esc_html( $tpl->description ); ?></p>
                <?php endif; ?>
                <span class="aura-cert-template-meta">
                    <?php echo esc_html( ucfirst( $tpl->orientation ) ); ?> •
                    <?php echo esc_html( $tpl->width_mm . ' × ' . $tpl->height_mm . ' mm' ); ?>
                </span>
            </div>

            <!-- Acciones -->
            <div class="aura-cert-template-actions">
                <a href="<?php echo esc_url( $builder_url . '&id=' . $tpl->id ); ?>"
                   class="button button-primary button-small">
                    <?php esc_html_e( 'Editar', 'aura-suite' ); ?>
                </a>

                <?php if ( ! $tpl->is_default ) : ?>
                <button type="button" class="button button-small aura-cert-set-default-btn"
                        data-id="<?php echo esc_attr( $tpl->id ); ?>">
                    <?php esc_html_e( 'Usar como predeterminada', 'aura-suite' ); ?>
                </button>
                <?php endif; ?>

                <button type="button" class="button button-small aura-cert-toggle-active-btn"
                        data-id="<?php echo esc_attr( $tpl->id ); ?>"
                        data-active="<?php echo $tpl->is_active ? '1' : '0'; ?>">
                    <?php echo $tpl->is_active ? esc_html__( 'Desactivar', 'aura-suite' ) : esc_html__( 'Activar', 'aura-suite' ); ?>
                </button>

                <?php if ( $can_delete && ! $tpl->is_default ) : ?>
                <button type="button" class="button button-small aura-cert-delete-template-btn"
                        data-id="<?php echo esc_attr( $tpl->id ); ?>"
                        data-name="<?php echo esc_attr( $tpl->name ); ?>">
                    <?php esc_html_e( 'Eliminar', 'aura-suite' ); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
