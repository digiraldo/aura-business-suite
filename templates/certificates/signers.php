<?php
/**
 * Template: Firmantes
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$signers   = Aura_Certificates_Signers::get_all_signers();
$max_active = (int) Aura_Certificates_Settings::get( 'max_active_signers', 4 );
$active_cnt = count( array_filter( $signers, fn( $s ) => $s['is_active'] ) );
?>
<div class="wrap aura-certificates-wrap">
    <h1 class="wp-heading-inline">✍️ <?php esc_html_e( 'Firmantes', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">

    <div class="notice notice-info inline">
        <p>
            <?php printf(
                /* translators: 1=activos, 2=máximo */
                esc_html__( 'Firmantes activos: %1$d de %2$d máximo. Las firmas se muestran en los certificados según el orden definido.', 'aura-suite' ),
                $active_cnt,
                $max_active
            ); ?>
        </p>
    </div>

    <!-- Formulario nuevo firmante -->
    <div class="aura-card" style="max-width:520px;margin-bottom:24px;">
        <h2><?php esc_html_e( 'Agregar Firmante', 'aura-suite' ); ?></h2>
        <div id="aura-signer-form-msg"></div>
        <label><?php esc_html_e( 'Nombre', 'aura-suite' ); ?>
            <input type="text" id="aura-signer-name" class="aura-input" style="width:100%;"
                   placeholder="<?php esc_attr_e( 'Ej: Dr. Juan García', 'aura-suite' ); ?>">
        </label>
        <label style="margin-top:8px;display:block;"><?php esc_html_e( 'Cargo / Título', 'aura-suite' ); ?>
            <input type="text" id="aura-signer-title" class="aura-input" style="width:100%;"
                   placeholder="<?php esc_attr_e( 'Ej: Director Académico', 'aura-suite' ); ?>">
        </label>
        <label style="margin-top:8px;display:block;"><?php esc_html_e( 'Imagen de Firma (PNG)', 'aura-suite' ); ?>
            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                <input type="hidden" id="aura-signer-attachment-id">
                <div id="aura-signer-sig-preview" style="border:1px dashed #ccc;min-width:160px;min-height:60px;display:flex;align-items:center;justify-content:center;background:#fafafa;">
                    <span style="color:#aaa;"><?php esc_html_e( 'Sin imagen', 'aura-suite' ); ?></span>
                </div>
                <button type="button" id="aura-signer-upload-btn" class="button">
                    <?php esc_html_e( 'Seleccionar imagen', 'aura-suite' ); ?>
                </button>
            </div>
        </label>
        <label style="margin-top:8px;display:flex;align-items:center;gap:8px;">
            <input type="checkbox" id="aura-signer-active" checked>
            <?php esc_html_e( 'Activo', 'aura-suite' ); ?>
        </label>
        <div style="margin-top:12px;">
            <button type="button" id="aura-signer-save-btn" class="button button-primary">
                <?php esc_html_e( 'Guardar Firmante', 'aura-suite' ); ?>
            </button>
        </div>
        <input type="hidden" id="aura-signer-editing-id" value="0">
    </div>

    <!-- Listado de firmantes -->
    <div id="aura-signers-list">
        <?php if ( empty( $signers ) ) : ?>
        <p class="aura-empty"><?php esc_html_e( 'No hay firmantes configurados.', 'aura-suite' ); ?></p>
        <?php else : ?>
        <table class="wp-list-table widefat fixed striped" id="aura-signers-table">
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th style="width:120px;"><?php esc_html_e( 'Firma', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Nombre', 'aura-suite' ); ?></th>
                    <th><?php esc_html_e( 'Cargo', 'aura-suite' ); ?></th>
                    <th style="width:80px;"><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                    <th style="width:180px;"><?php esc_html_e( 'Acciones', 'aura-suite' ); ?></th>
                </tr>
            </thead>
            <tbody id="aura-signers-sortable">
                <?php foreach ( $signers as $s ) : ?>
                <tr data-id="<?php echo esc_attr( $s['id'] ); ?>">
                    <td style="cursor:grab;" class="aura-drag-handle">⠿</td>
                    <td>
                        <?php if ( $s['signature_url'] ) : ?>
                        <img src="<?php echo esc_url( $s['signature_url'] ); ?>"
                             style="max-height:50px;max-width:120px;object-fit:contain;"
                             alt="<?php echo esc_attr( $s['name'] ); ?>">
                        <?php else : ?>
                        <span style="color:#aaa;"><?php esc_html_e( 'Sin imagen', 'aura-suite' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo esc_html( $s['name'] ); ?></strong></td>
                    <td><?php echo esc_html( $s['title'] ); ?></td>
                    <td>
                        <span class="aura-cert-status aura-cert-status--<?php echo $s['is_active'] ? 'active' : 'revoked'; ?>">
                            <?php echo $s['is_active'] ? esc_html__( 'Activo', 'aura-suite' ) : esc_html__( 'Inactivo', 'aura-suite' ); ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="button button-small aura-signer-edit-btn"
                                data-id="<?php echo esc_attr( $s['id'] ); ?>"
                                data-name="<?php echo esc_attr( $s['name'] ); ?>"
                                data-title="<?php echo esc_attr( $s['title'] ); ?>"
                                data-active="<?php echo $s['is_active'] ? '1' : '0'; ?>"
                                data-attachment="<?php echo esc_attr( $s['attachment_id'] ); ?>"
                                data-sig-url="<?php echo esc_url( $s['signature_url'] ); ?>">
                            <?php esc_html_e( 'Editar', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="button button-small aura-signer-toggle-btn"
                                data-id="<?php echo esc_attr( $s['id'] ); ?>"
                                data-active="<?php echo $s['is_active'] ? '1' : '0'; ?>">
                            <?php echo $s['is_active'] ? esc_html__( 'Desactivar', 'aura-suite' ) : esc_html__( 'Activar', 'aura-suite' ); ?>
                        </button>
                        <button type="button" class="button button-small aura-signer-delete-btn"
                                data-id="<?php echo esc_attr( $s['id'] ); ?>">
                            🗑
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
