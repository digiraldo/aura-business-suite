<?php
/**
 * Componente: Modal de Migración y Portabilidad Multimedia (ZIP Bundle)
 * Permite exportar e importar Terceros, Áreas y Usuarios con sus imágenes.
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bundle_nonce = wp_create_nonce( 'aura_bundle_nonce' );
?>

<!-- ── MODAL DE PORTABILIDAD MULTIMEDIA ── -->
<div id="aura-bundle-modal" class="aura-modal-backdrop" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); z-index:99999; align-items:center; justify-content:center;">
    <div class="aura-modal-content" style="background:var(--aura-surface, #ffffff); border-radius:16px; max-width:640px; width:92%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); border:1px solid rgba(226,232,240,0.8); position:relative; padding:0; display:flex; flex-direction:column; font-family:Inter,system-ui,sans-serif;">
        
        <!-- Cabecera del Modal -->
        <div style="padding:22px 28px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; background:linear-gradient(135deg, #faf5ff 0%, #ffffff 100%); border-top-left-radius:16px; border-top-right-radius:16px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:42px; height:42px; border-radius:10px; background:linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%); color:#ffffff; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(124,58,237,0.25);">
                    <span class="dashicons dashicons-database-export" style="font-size:22px; width:22px; height:22px;"></span>
                </div>
                <div>
                    <h3 style="margin:0; font-size:1.15rem; font-weight:700; color:#0f172a;"><?php esc_html_e( 'Portabilidad y Migración Multimedia', 'aura-suite' ); ?></h3>
                    <p style="margin:2px 0 0; font-size:0.83rem; color:#64748b;"><?php esc_html_e( 'Exporta e importa datos con fotos reales y avatares (.ZIP)', 'aura-suite' ); ?></p>
                </div>
            </div>
            <button type="button" onclick="closeAuraBundleModal()" style="background:none; border:none; color:#94a3b8; font-size:24px; cursor:pointer; width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseover="this.style.background='#f1f5f9';this.style.color='#0f172a';" onmouseout="this.style.background='none';this.style.color='#94a3b8';">✕</button>
        </div>

        <!-- Pestañas del Modal -->
        <div style="display:flex; border-bottom:1px solid #e2e8f0; background:#f8fafc; padding:0 28px;">
            <button type="button" id="aura-bundle-tab-btn-export" onclick="switchBundleTab('export')" style="padding:14px 20px; font-weight:600; font-size:0.9rem; border:none; background:none; cursor:pointer; border-bottom:2px solid #7c3aed; color:#7c3aed; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e( 'Exportar Paquete (.ZIP)', 'aura-suite' ); ?>
            </button>
            <button type="button" id="aura-bundle-tab-btn-import" onclick="switchBundleTab('import')" style="padding:14px 20px; font-weight:600; font-size:0.9rem; border:none; background:none; cursor:pointer; border-bottom:2px solid transparent; color:#64748b; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e( 'Importar Paquete (.ZIP)', 'aura-suite' ); ?>
            </button>
        </div>

        <!-- Contenido Pestaña 1: EXPORTAR -->
        <div id="aura-bundle-panel-export" style="padding:28px;">
            <div style="background:#f5f3ff; border:1px solid #ddd6fe; border-radius:12px; padding:14px 18px; margin-bottom:20px; display:flex; gap:12px; align-items:flex-start;">
                <span class="dashicons dashicons-info" style="color:#7c3aed; font-size:20px; margin-top:2px;"></span>
                <div style="font-size:0.86rem; color:#4c1d95; line-height:1.45;">
                    <?php esc_html_e( 'Este proceso genera un archivo .ZIP que incluye la información en JSON y todas las imágenes binarias físicas en su interior. Al importarlo en tu servidor de destino (Hostinger o producción), todas las fotos y avatares se registrarán automáticamente en la Biblioteca de Medios.', 'aura-suite' ); ?>
                </div>
            </div>

            <div style="font-weight:600; font-size:0.9rem; color:#1e293b; margin-bottom:12px;">
                <?php esc_html_e( 'Selecciona los elementos que deseas empaquetar:', 'aura-suite' ); ?>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:24px;">
                <label style="display:flex; align-items:center; gap:12px; padding:12px 16px; border:1px solid #e2e8f0; border-radius:10px; cursor:pointer; background:#ffffff; transition:all 0.2s;" onmouseover="this.style.borderColor='#a78bfa';this.style.background='#faf5ff';" onmouseout="this.style.borderColor='#e2e8f0';this.style.background='#ffffff';">
                    <input type="checkbox" id="aura-exp-third-parties" checked style="width:18px; height:18px; accent-color:#7c3aed;">
                    <div>
                        <div style="font-weight:600; color:#0f172a; font-size:0.9rem;"><?php esc_html_e( '👥 Directorio de Terceros y Entidades', 'aura-suite' ); ?></div>
                        <div style="font-size:0.8rem; color:#64748b;"><?php esc_html_e( 'Empresas, personas, proveedores y fundaciones con sus logotipos adjuntos.', 'aura-suite' ); ?></div>
                    </div>
                </label>

                <label style="display:flex; align-items:center; gap:12px; padding:12px 16px; border:1px solid #e2e8f0; border-radius:10px; cursor:pointer; background:#ffffff; transition:all 0.2s;" onmouseover="this.style.borderColor='#a78bfa';this.style.background='#faf5ff';" onmouseout="this.style.borderColor='#e2e8f0';this.style.background='#ffffff';">
                    <input type="checkbox" id="aura-exp-areas" checked style="width:18px; height:18px; accent-color:#7c3aed;">
                    <div>
                        <div style="font-weight:600; color:#0f172a; font-size:0.9rem;"><?php esc_html_e( '🏷️ Áreas y Programas Institucionales', 'aura-suite' ); ?></div>
                        <div style="font-size:0.8rem; color:#64748b;"><?php esc_html_e( 'Estructura organizativa, jerarquías, colores y logotipos de cada área.', 'aura-suite' ); ?></div>
                    </div>
                </label>

                <label style="display:flex; align-items:center; gap:12px; padding:12px 16px; border:1px solid #e2e8f0; border-radius:10px; cursor:pointer; background:#ffffff; transition:all 0.2s;" onmouseover="this.style.borderColor='#a78bfa';this.style.background='#faf5ff';" onmouseout="this.style.borderColor='#e2e8f0';this.style.background='#ffffff';">
                    <input type="checkbox" id="aura-exp-users" checked style="width:18px; height:18px; accent-color:#7c3aed;">
                    <div>
                        <div style="font-weight:600; color:#0f172a; font-size:0.9rem;"><?php esc_html_e( '👤 Usuarios de WordPress', 'aura-suite' ); ?></div>
                        <div style="font-size:0.8rem; color:#64748b;"><?php esc_html_e( 'Cuentas de usuario, roles de acceso y fotos de perfil / avatares reales.', 'aura-suite' ); ?></div>
                    </div>
                </label>
            </div>

            <!-- Botón Exportar -->
            <div style="display:flex; justify-content:flex-end; gap:12px; align-items:center;">
                <button type="button" onclick="closeAuraBundleModal()" class="button button-secondary" style="padding:8px 16px; height:auto; border-radius:8px;">
                    <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                </button>
                <button type="button" id="aura-btn-do-export-bundle" onclick="executeAuraBundleExport(this)" style="background:linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%); color:#ffffff; border:none; padding:10px 22px; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer; display:flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(124,58,237,0.3); transition:all 0.2s;">
                    <span class="dashicons dashicons-download" style="font-size:18px; width:18px; height:18px;"></span>
                    <span id="aura-btn-exp-text"><?php esc_html_e( 'Generar y Descargar Paquete (.ZIP)', 'aura-suite' ); ?></span>
                </button>
            </div>

            <!-- Progreso Exportar -->
            <div id="aura-export-progress-box" style="display:none; margin-top:16px;">
                <div style="height:6px; background:#e2e8f0; border-radius:99px; overflow:hidden;">
                    <div style="height:100%; background:linear-gradient(90deg, #7c3aed, #3b82f6); width:100%; animation: aura-pulse-bar 1.5s infinite;"></div>
                </div>
                <div style="font-size:0.82rem; color:#64748b; text-align:center; margin-top:8px;">
                    <?php esc_html_e( 'Empaquetando imágenes y estructurando datos... Por favor espera.', 'aura-suite' ); ?>
                </div>
            </div>
        </div>

        <!-- Contenido Pestaña 2: IMPORTAR -->
        <div id="aura-bundle-panel-import" style="padding:28px; display:none;">
            <!-- Dropzone -->
            <div id="aura-bundle-dropzone" style="border:2px dashed #cbd5e1; border-radius:14px; padding:32px 20px; text-align:center; background:#f8fafc; cursor:pointer; transition:all 0.2s;" onclick="document.getElementById('aura-bundle-file-input').click()" ondragover="event.preventDefault();this.style.borderColor='#7c3aed';this.style.background='#faf5ff';" ondragleave="this.style.borderColor='#cbd5e1';this.style.background='#f8fafc';" ondrop="handleBundleDrop(event)">
                <input type="file" id="aura-bundle-file-input" accept=".zip" style="display:none" onchange="handleBundleFileSelect(this)">
                <div style="width:52px; height:52px; border-radius:50%; background:#e0e7ff; color:#4f46e5; margin:0 auto 12px; display:flex; align-items:center; justify-content:center;">
                    <span class="dashicons dashicons-upload" style="font-size:26px; width:26px; height:26px;"></span>
                </div>
                <div style="font-weight:600; color:#1e293b; font-size:1rem; margin-bottom:4px;">
                    <?php esc_html_e( 'Haz clic para seleccionar o arrastra tu archivo .ZIP aquí', 'aura-suite' ); ?>
                </div>
                <div style="font-size:0.83rem; color:#64748b;">
                    <?php esc_html_e( 'Paquete generado previamente por Aura Business Suite', 'aura-suite' ); ?>
                </div>
            </div>

            <!-- Archivo Seleccionado y Análisis -->
            <div id="aura-bundle-inspect-box" style="display:none; margin-top:20px;">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="dashicons dashicons-media-archive" style="color:#7c3aed; font-size:22px;"></span>
                            <strong id="aura-bundle-inspect-filename" style="color:#0f172a; font-size:0.92rem;"></strong>
                        </div>
                        <button type="button" onclick="resetBundleImport()" style="background:none; border:none; color:#ef4444; font-size:0.82rem; cursor:pointer; font-weight:600;">
                            <?php esc_html_e( 'Cambiar archivo', 'aura-suite' ); ?>
                        </button>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; margin-bottom:14px;">
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                            <div style="font-size:0.75rem; color:#64748b;"><?php esc_html_e( 'Terceros', 'aura-suite' ); ?></div>
                            <div id="aura-count-tp" style="font-size:1.25rem; font-weight:700; color:#0f172a;">0</div>
                        </div>
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                            <div style="font-size:0.75rem; color:#64748b;"><?php esc_html_e( 'Áreas', 'aura-suite' ); ?></div>
                            <div id="aura-count-areas" style="font-size:1.25rem; font-weight:700; color:#0f172a;">0</div>
                        </div>
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                            <div style="font-size:0.75rem; color:#64748b;"><?php esc_html_e( 'Usuarios WP', 'aura-suite' ); ?></div>
                            <div id="aura-count-users" style="font-size:1.25rem; font-weight:700; color:#0f172a;">0</div>
                        </div>
                    </div>

                    <div style="font-size:0.83rem; color:#475569; display:flex; justify-content:space-between; padding-top:6px; border-top:1px dashed #e2e8f0;">
                        <span>📷 <strong id="aura-count-media">0</strong> <?php esc_html_e( 'imágenes listas para registrar en Biblioteca de Medios', 'aura-suite' ); ?></span>
                        <span id="aura-bundle-origin" style="color:#64748b; font-size:0.8rem;"></span>
                    </div>
                </div>

                <!-- Checkboxes de qué importar -->
                <div style="margin-top:16px; display:flex; gap:16px; flex-wrap:wrap;">
                    <label style="font-size:0.88rem; color:#1e293b; display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" id="aura-imp-tp-chk" checked style="accent-color:#7c3aed;">
                        <?php esc_html_e( 'Importar Terceros', 'aura-suite' ); ?>
                    </label>
                    <label style="font-size:0.88rem; color:#1e293b; display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" id="aura-imp-areas-chk" checked style="accent-color:#7c3aed;">
                        <?php esc_html_e( 'Importar Áreas', 'aura-suite' ); ?>
                    </label>
                    <label style="font-size:0.88rem; color:#1e293b; display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" id="aura-imp-users-chk" checked style="accent-color:#7c3aed;">
                        <?php esc_html_e( 'Importar Usuarios', 'aura-suite' ); ?>
                    </label>
                </div>

                <!-- Botón Importar -->
                <div style="display:flex; justify-content:flex-end; margin-top:20px;">
                    <button type="button" id="aura-btn-do-import-bundle" onclick="executeAuraBundleImport(this)" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:#ffffff; border:none; padding:10px 24px; border-radius:8px; font-weight:600; font-size:0.92rem; cursor:pointer; display:flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(16,185,129,0.3); transition:all 0.2s;">
                        <span class="dashicons dashicons-update" style="font-size:18px; width:18px; height:18px;"></span>
                        <span id="aura-btn-imp-text"><?php esc_html_e( 'Iniciar Importación y Restaurar Imágenes', 'aura-suite' ); ?></span>
                    </button>
                </div>
            </div>

            <!-- Progreso / Resultado Importar -->
            <div id="aura-import-status-box" style="display:none; margin-top:16px;"></div>
        </div>

    </div>
</div>

<style>
@keyframes aura-pulse-bar {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
</style>

<script>
var auraBundleCurrentToken = '';

function openAuraBundleModal(defaultTab) {
    var modal = document.getElementById('aura-bundle-modal');
    if (modal) {
        modal.style.display = 'flex';
        switchBundleTab(defaultTab || 'export');
    }
}

function closeAuraBundleModal() {
    var modal = document.getElementById('aura-bundle-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function switchBundleTab(tab) {
    var pExp = document.getElementById('aura-bundle-panel-export');
    var pImp = document.getElementById('aura-bundle-panel-import');
    var bExp = document.getElementById('aura-bundle-tab-btn-export');
    var bImp = document.getElementById('aura-bundle-tab-btn-import');

    if (tab === 'export') {
        pExp.style.display = 'block';
        pImp.style.display = 'none';
        bExp.style.borderBottomColor = '#7c3aed';
        bExp.style.color = '#7c3aed';
        bImp.style.borderBottomColor = 'transparent';
        bImp.style.color = '#64748b';
    } else {
        pExp.style.display = 'none';
        pImp.style.display = 'block';
        bImp.style.borderBottomColor = '#7c3aed';
        bImp.style.color = '#7c3aed';
        bExp.style.borderBottomColor = 'transparent';
        bExp.style.color = '#64748b';
    }
}

// ── Exportar Bundle ──
function executeAuraBundleExport(btn) {
    var expTP    = document.getElementById('aura-exp-third-parties').checked ? 1 : 0;
    var expAreas = document.getElementById('aura-exp-areas').checked ? 1 : 0;
    var expUsers = document.getElementById('aura-exp-users').checked ? 1 : 0;

    if (!expTP && !expAreas && !expUsers) {
        alert('<?php echo esc_js( __( 'Por favor selecciona al menos un elemento para exportar.', 'aura-suite' ) ); ?>');
        return;
    }

    var originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="dashicons dashicons-update aura-spin"></span> <?php echo esc_js( __( 'Empaquetando...', 'aura-suite' ) ); ?>';
    document.getElementById('aura-export-progress-box').style.display = 'block';

    var formData = new FormData();
    formData.append('action', 'aura_bundle_export');
    formData.append('nonce', '<?php echo esc_js( $bundle_nonce ); ?>');
    formData.append('export_third_parties', expTP);
    formData.append('export_areas', expAreas);
    formData.append('export_users', expUsers);

    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(function(res){ return res.json(); })
    .then(function(res){
        btn.disabled = false;
        btn.innerHTML = originalText;
        document.getElementById('aura-export-progress-box').style.display = 'none';

        if (!res.success) {
            alert(res.data && res.data.message ? res.data.message : 'Error al exportar el paquete');
            return;
        }

        // Descargar archivo ZIP
        var link = document.createElement('a');
        link.href = res.data.download_url;
        link.download = res.data.filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    })
    .catch(function(err){
        btn.disabled = false;
        btn.innerHTML = originalText;
        document.getElementById('aura-export-progress-box').style.display = 'none';
        alert('Error en la solicitud: ' + err.message);
    });
}

// ── Manejadores de Dropzone para Importar ──
function handleBundleDrop(e) {
    e.preventDefault();
    document.getElementById('aura-bundle-dropzone').style.borderColor = '#cbd5e1';
    document.getElementById('aura-bundle-dropzone').style.background = '#f8fafc';
    if (e.dataTransfer.files && e.dataTransfer.files.length) {
        inspectBundleFile(e.dataTransfer.files[0]);
    }
}

function handleBundleFileSelect(input) {
    if (input.files && input.files.length) {
        inspectBundleFile(input.files[0]);
    }
}

function inspectBundleFile(file) {
    if (!file.name.toLowerCase().endsWith('.zip')) {
        alert('<?php echo esc_js( __( 'Por favor selecciona un archivo .ZIP válido.', 'aura-suite' ) ); ?>');
        return;
    }

    var dropzone = document.getElementById('aura-bundle-dropzone');
    dropzone.innerHTML = '<span class="dashicons dashicons-update aura-spin" style="font-size:28px;"></span><p><?php echo esc_js( __( 'Analizando e inspeccionando paquete ZIP...', 'aura-suite' ) ); ?></p>';

    var formData = new FormData();
    formData.append('action', 'aura_bundle_inspect');
    formData.append('nonce', '<?php echo esc_js( $bundle_nonce ); ?>');
    formData.append('bundle_file', file);

    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(function(res){ return res.json(); })
    .then(function(res){
        if (!res.success) {
            resetBundleImport();
            alert(res.data && res.data.message ? res.data.message : 'Error al inspeccionar archivo');
            return;
        }

        auraBundleCurrentToken = res.data.token;
        dropzone.style.display = 'none';
        var inspectBox = document.getElementById('aura-bundle-inspect-box');
        inspectBox.style.display = 'block';

        document.getElementById('aura-bundle-inspect-filename').innerText = file.name;
        document.getElementById('aura-count-tp').innerText = res.data.third_parties || 0;
        document.getElementById('aura-count-areas').innerText = res.data.areas || 0;
        document.getElementById('aura-count-users').innerText = res.data.users || 0;
        document.getElementById('aura-count-media').innerText = res.data.counts && res.data.counts.media_files ? res.data.counts.media_files : 0;
        if (res.data.source_site) {
            document.getElementById('aura-bundle-origin').innerText = 'Origen: ' + res.data.source_site;
        }
    })
    .catch(function(err){
        resetBundleImport();
        alert('Error: ' + err.message);
    });
}

function resetBundleImport() {
    auraBundleCurrentToken = '';
    var dropzone = document.getElementById('aura-bundle-dropzone');
    dropzone.style.display = 'block';
    dropzone.innerHTML = '<input type="file" id="aura-bundle-file-input" accept=".zip" style="display:none" onchange="handleBundleFileSelect(this)"><div style="width:52px; height:52px; border-radius:50%; background:#e0e7ff; color:#4f46e5; margin:0 auto 12px; display:flex; align-items:center; justify-content:center;"><span class="dashicons dashicons-upload" style="font-size:26px; width:26px; height:26px;"></span></div><div style="font-weight:600; color:#1e293b; font-size:1rem; margin-bottom:4px;"><?php echo esc_js( __( 'Haz clic para seleccionar o arrastra tu archivo .ZIP aquí', 'aura-suite' ) ); ?></div><div style="font-size:0.83rem; color:#64748b;"><?php echo esc_js( __( 'Paquete generado previamente por Aura Business Suite', 'aura-suite' ) ); ?></div>';
    document.getElementById('aura-bundle-inspect-box').style.display = 'none';
    document.getElementById('aura-import-status-box').style.display = 'none';
}

function executeAuraBundleImport(btn) {
    if (!auraBundleCurrentToken) {
        alert('<?php echo esc_js( __( 'No hay un paquete cargado válido.', 'aura-suite' ) ); ?>');
        return;
    }

    var impTP    = document.getElementById('aura-imp-tp-chk').checked ? 1 : 0;
    var impAreas = document.getElementById('aura-imp-areas-chk').checked ? 1 : 0;
    var impUsers = document.getElementById('aura-imp-users-chk').checked ? 1 : 0;

    var originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="dashicons dashicons-update aura-spin"></span> <?php echo esc_js( __( 'Importando registros y medios...', 'aura-suite' ) ); ?>';

    var statusBox = document.getElementById('aura-import-status-box');
    statusBox.style.display = 'block';
    statusBox.innerHTML = '<div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:14px; font-size:0.86rem; color:#1e40af; text-align:center;"><span class="dashicons dashicons-update aura-spin"></span> <?php echo esc_js( __( 'Descargando medios en la Biblioteca de WordPress y asociando IDs... Esto puede tomar unos segundos.', 'aura-suite' ) ); ?></div>';

    var formData = new FormData();
    formData.append('action', 'aura_bundle_import');
    formData.append('nonce', '<?php echo esc_js( $bundle_nonce ); ?>');
    formData.append('token', auraBundleCurrentToken);
    formData.append('import_third_parties', impTP);
    formData.append('import_areas', impAreas);
    formData.append('import_users', impUsers);

    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(function(res){ return res.json(); })
    .then(function(res){
        btn.disabled = false;
        btn.innerHTML = originalText;

        if (!res.success) {
            statusBox.innerHTML = '<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:14px; font-size:0.86rem; color:#991b1b;">❌ ' + (res.data && res.data.message ? res.data.message : 'Error durante la importación') + '</div>';
            return;
        }

        var r = res.data.results;
        var summaryHtml = '<div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:16px; font-size:0.88rem; color:#166534;">';
        summaryHtml += '<div style="font-weight:700; margin-bottom:8px; font-size:0.95rem;">✅ ' + res.data.message + '</div>';
        summaryHtml += '<ul style="margin:0; padding-left:18px; line-height:1.6;">';
        if (r.areas) {
            summaryHtml += '<li><strong>Áreas:</strong> ' + r.areas.created + ' creadas, ' + r.areas.updated + ' actualizadas, ' + r.areas.images + ' imágenes vinculadas.</li>';
        }
        if (r.users) {
            summaryHtml += '<li><strong>Usuarios:</strong> ' + r.users.created + ' creados, ' + r.users.updated + ' actualizados, ' + r.users.images + ' avatares vinculados.</li>';
        }
        if (r.third_parties) {
            summaryHtml += '<li><strong>Terceros:</strong> ' + r.third_parties.created + ' creados, ' + r.third_parties.updated + ' actualizados, ' + r.third_parties.images + ' logotipos vinculados.</li>';
        }
        summaryHtml += '</ul></div>';

        statusBox.innerHTML = summaryHtml;
        btn.style.display = 'none';
    })
    .catch(function(err){
        btn.disabled = false;
        btn.innerHTML = originalText;
        statusBox.innerHTML = '<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:14px; font-size:0.86rem; color:#991b1b;">❌ Error de red: ' + err.message + '</div>';
    });
}
</script>
