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

    <!-- Formulario nuevo / editar firmante -->
    <form id="aura-signer-form" class="aura-card" style="max-width:600px;margin-bottom:24px;">
        <h2 id="aura-signer-form-title"><?php esc_html_e( 'Agregar Firmante', 'aura-suite' ); ?></h2>
        <div id="aura-signer-form-msg"></div>

        <!-- Vinculación opcional con Usuario WP o Tercero -->
        <div style="margin-bottom:12px;padding:10px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
            <label style="display:block;font-weight:600;margin-bottom:6px;font-size:12.5px;">
                <?php esc_html_e( 'Vincular con Usuario WP o Tercero (opcional):', 'aura-suite' ); ?>
            </label>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <input type="text" id="aura-signer-counterparty-search" class="aura-input" style="flex:1;min-width:200px;"
                       placeholder="<?php esc_attr_e( 'Buscar usuario WP o tercero para autocompletar…', 'aura-suite' ); ?>">
                <button type="button" id="aura-signer-counterparty-explore" class="button">
                    <span class="dashicons dashicons-groups" style="vertical-align:text-bottom;"></span>
                    <?php esc_html_e( 'Explorar', 'aura-suite' ); ?>
                </button>
                <button type="button" id="aura-signer-counterparty-clear" class="button" style="display:none;">
                    <?php esc_html_e( 'Desvincular', 'aura-suite' ); ?>
                </button>
            </div>
            <input type="hidden" id="aura-signer-wp-user-id" value="0">
            <input type="hidden" id="aura-signer-third-party-id" value="0">
            <div id="aura-signer-counterparty-preview" class="aura-selected-chip" style="display:none;margin-top:8px;"></div>
        </div>

        <label style="display:block;"><?php esc_html_e( 'Nombre', 'aura-suite' ); ?>
            <input type="text" id="aura-signer-name" class="aura-input" style="width:100%;" required
                   placeholder="<?php esc_attr_e( 'Ej: Dr. Juan García', 'aura-suite' ); ?>">
        </label>
        <label style="margin-top:8px;display:block;"><?php esc_html_e( 'Cargo / Título', 'aura-suite' ); ?>
            <input type="text" id="aura-signer-title" class="aura-input" style="width:100%;"
                   placeholder="<?php esc_attr_e( 'Ej: Director Académico', 'aura-suite' ); ?>">
        </label>
        <label style="margin-top:8px;display:block;"><?php esc_html_e( 'Imagen de Firma (PNG / WebP / JPG)', 'aura-suite' ); ?>
            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;">
                <input type="hidden" id="aura-signer-attachment-id" value="0">
                <input type="hidden" id="aura-signer-signature-url" value="">
                <div id="aura-signer-sig-preview" style="border:1px dashed #ccc;min-width:160px;min-height:60px;padding:4px 8px;display:flex;align-items:center;justify-content:center;background:#fafafa;border-radius:6px;">
                    <span style="color:#aaa;"><?php esc_html_e( 'Sin imagen', 'aura-suite' ); ?></span>
                </div>
                <button type="button" id="aura-signer-upload-btn" class="button">
                    <?php esc_html_e( 'Seleccionar imagen', 'aura-suite' ); ?>
                </button>
            </div>
        </label>
        <label style="margin-top:10px;display:flex;align-items:center;gap:8px;">
            <input type="checkbox" id="aura-signer-active" checked>
            <?php esc_html_e( 'Activo', 'aura-suite' ); ?>
        </label>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
            <button type="submit" id="aura-signer-save-btn" class="button button-primary">
                <?php esc_html_e( 'Guardar Firmante', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-signer-cancel-btn" class="button" style="display:none;">
                <?php esc_html_e( 'Cancelar edición', 'aura-suite' ); ?>
            </button>
        </div>
        <input type="hidden" id="aura-signer-id" value="0">
    </form>

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
                    <td>
                        <strong><?php echo esc_html( $s['name'] ); ?></strong>
                        <?php if ( ! empty( $s['linked_label'] ) ) : ?>
                            <div><span class="aura-cert-badge aura-cert-badge--wpuser"><?php echo esc_html( $s['linked_label'] ); ?></span></div>
                        <?php endif; ?>
                    </td>
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
                                data-sig-url="<?php echo esc_url( $s['signature_url'] ); ?>"
                                data-wp-user-id="<?php echo esc_attr( $s['wp_user_id'] ?? 0 ); ?>"
                                data-third-party-id="<?php echo esc_attr( $s['third_party_id'] ?? 0 ); ?>"
                                data-linked-label="<?php echo esc_attr( $s['linked_label'] ?? '' ); ?>">
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
