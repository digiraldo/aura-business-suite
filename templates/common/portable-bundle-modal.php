<?php
/**
 * Componente: Modal de Migración y Portabilidad Multimedia (ZIP Bundle)
 * Permite exportar e importar Terceros, Áreas y Usuarios con sus imágenes.
 * Totalmente migrado al Sistema de Diseño Canónico de Aura (P.E.E. / Dark & Light Mode).
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bundle_nonce = wp_create_nonce( 'aura_bundle_nonce' );
?>

<!-- ── MODAL DE PORTABILIDAD MULTIMEDIA (AURA DESIGN SYSTEM) ── -->
<div id="aura-bundle-modal" class="aura-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-bundle-modal-title">
    <div class="aura-modal-container" style="max-width:680px; width:100%; margin:auto !important; max-height:calc(100vh - 40px) !important; display:flex !important; flex-direction:column !important; overflow:hidden !important;">
        
        <!-- Cabecera del Modal -->
        <div class="aura-modal-header" style="flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:40px; height:40px; border-radius:10px; background:rgba(255,255,255,0.2); backdrop-filter:blur(8px); display:flex; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                    <span class="dashicons dashicons-database-export" style="font-size:22px; width:22px; height:22px; color:#ffffff;"></span>
                </div>
                <div>
                    <h3 id="aura-bundle-modal-title" style="margin:0; font-size:1.15rem; font-weight:700; color:#ffffff; line-height:1.2;">
                        <?php esc_html_e( 'Portabilidad y Migración Multimedia', 'aura-suite' ); ?>
                    </h3>
                    <p style="margin:4px 0 0; font-size:0.83rem; color:rgba(255,255,255,0.85); line-height:1.3;">
                        <?php esc_html_e( 'Exporta e importa datos con fotos reales y avatares (.ZIP)', 'aura-suite' ); ?>
                    </p>
                </div>
            </div>
            <button type="button" class="aura-modal-close" onclick="closeAuraBundleModal()" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>

        <!-- Pestañas del Modal -->
        <div class="aura-modal-tabs-wrapper" style="flex-shrink:0;">
            <div class="aura-modal-tabs">
                <button type="button" id="aura-bundle-tab-btn-export" class="aura-modal-tab-btn is-active" onclick="switchBundleTab('export')">
                    <span class="dashicons dashicons-download" style="vertical-align:middle; margin-right:4px;"></span>
                    <?php esc_html_e( 'Exportar Paquete (.ZIP)', 'aura-suite' ); ?>
                </button>
                <button type="button" id="aura-bundle-tab-btn-import" class="aura-modal-tab-btn" onclick="switchBundleTab('import')">
                    <span class="dashicons dashicons-upload" style="vertical-align:middle; margin-right:4px;"></span>
                    <?php esc_html_e( 'Importar Paquete (.ZIP)', 'aura-suite' ); ?>
                </button>
            </div>
        </div>

        <!-- Cuerpo del Modal (Scrollable) -->
        <div class="aura-modal-body" style="flex:1 1 auto; overflow-y:auto; padding:24px;">
            
            <!-- Pestaña 1: EXPORTAR -->
            <div id="aura-bundle-panel-export" class="aura-modal-tab-panel is-active">
                <div class="alert-card alert-primary" style="margin-bottom:20px; display:flex; gap:12px; align-items:flex-start;">
                    <span class="dashicons dashicons-info" style="font-size:20px; line-height:1.4; color:var(--primary, #2563eb);"></span>
                    <div style="font-size:0.86rem; line-height:1.5;">
                        <?php esc_html_e( 'Este proceso genera un archivo .ZIP que incluye la información en JSON y todas las imágenes binarias físicas en su interior. Al importarlo en tu servidor de destino (Hostinger o producción), todas las fotos y avatares se registrarán automáticamente en la Biblioteca de Medios.', 'aura-suite' ); ?>
                    </div>
                </div>

                <div style="font-weight:700; font-size:0.92rem; color:var(--tx-primary, #0f172a); margin-bottom:12px;">
                    <?php esc_html_e( 'Selecciona los elementos que deseas empaquetar:', 'aura-suite' ); ?>
                </div>

                <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:24px;">
                    <label class="aura-bundle-card-choice">
                        <input type="checkbox" id="aura-exp-third-parties" checked class="form-check-input" style="width:18px; height:18px; cursor:pointer;">
                        <div class="aura-bundle-card-choice-content">
                            <div class="aura-bundle-card-title"><?php esc_html_e( '👥 Directorio de Terceros y Entidades', 'aura-suite' ); ?></div>
                            <div class="aura-bundle-card-desc"><?php esc_html_e( 'Empresas, personas, proveedores y fundaciones con sus logotipos adjuntos.', 'aura-suite' ); ?></div>
                        </div>
                    </label>

                    <label class="aura-bundle-card-choice">
                        <input type="checkbox" id="aura-exp-areas" checked class="form-check-input" style="width:18px; height:18px; cursor:pointer;">
                        <div class="aura-bundle-card-choice-content">
                            <div class="aura-bundle-card-title"><?php esc_html_e( '🏷️ Áreas y Programas Institucionales', 'aura-suite' ); ?></div>
                            <div class="aura-bundle-card-desc"><?php esc_html_e( 'Estructura organizativa, jerarquías, colores y logotipos de cada área.', 'aura-suite' ); ?></div>
                        </div>
                    </label>

                    <label class="aura-bundle-card-choice">
                        <input type="checkbox" id="aura-exp-users" checked class="form-check-input" style="width:18px; height:18px; cursor:pointer;">
                        <div class="aura-bundle-card-choice-content">
                            <div class="aura-bundle-card-title"><?php esc_html_e( '👤 Usuarios de WordPress', 'aura-suite' ); ?></div>
                            <div class="aura-bundle-card-desc"><?php esc_html_e( 'Cuentas de usuario, roles de acceso y fotos de perfil / avatares reales.', 'aura-suite' ); ?></div>
                        </div>
                    </label>
                </div>

                <!-- Botones Exportar -->
                <div style="display:flex; justify-content:flex-end; gap:12px; align-items:center; margin-top:12px;">
                    <button type="button" onclick="closeAuraBundleModal()" class="btn btn-secondary btn-lift">
                        <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                    </button>
                    <button type="button" id="aura-btn-do-export-bundle" onclick="executeAuraBundleExport(this)" class="btn btn-primary btn-shimmer btn-lift">
                        <span class="dashicons dashicons-download" style="font-size:18px; width:18px; height:18px;"></span>
                        <span id="aura-btn-exp-text"><?php esc_html_e( 'Generar y Descargar Paquete (.ZIP)', 'aura-suite' ); ?></span>
                    </button>
                </div>

                <!-- Progreso Exportar -->
                <div id="aura-export-progress-box" style="display:none; margin-top:18px;">
                    <div class="loading-bar loading-bar-thick" style="height:8px; border-radius:99px; overflow:hidden;">
                        <div style="height:100%; background:linear-gradient(90deg, #2563eb, #8b5cf6, #10b981); width:100%; animation: aura-pulse-bar 1.5s infinite;"></div>
                    </div>
                    <div style="font-size:0.84rem; color:var(--tx-muted, #64748b); text-align:center; margin-top:8px;">
                        <?php esc_html_e( 'Empaquetando imágenes y estructurando datos... Por favor espera.', 'aura-suite' ); ?>
                    </div>
                </div>
            </div>

            <!-- Pestaña 2: IMPORTAR -->
            <div id="aura-bundle-panel-import" class="aura-modal-tab-panel" style="display:none;">
                <!-- Dropzone -->
                <div id="aura-bundle-dropzone" class="aura-bundle-dropzone" onclick="document.getElementById('aura-bundle-file-input').click()" ondragover="handleBundleDragOver(event)" ondragleave="handleBundleDragLeave(event)" ondrop="handleBundleDrop(event)">
                    <input type="file" id="aura-bundle-file-input" accept=".zip" style="display:none" onchange="handleBundleFileSelect(this)">
                    <div class="aura-bundle-dropzone-icon">
                        <span class="dashicons dashicons-upload"></span>
                    </div>
                    <div class="aura-bundle-dropzone-title">
                        <?php esc_html_e( 'Haz clic para seleccionar o arrastra tu archivo .ZIP aquí', 'aura-suite' ); ?>
                    </div>
                    <div class="aura-bundle-dropzone-subtitle">
                        <?php esc_html_e( 'Paquete generado previamente por Aura Business Suite', 'aura-suite' ); ?>
                    </div>
                </div>

                <!-- Archivo Seleccionado y Análisis -->
                <div id="aura-bundle-inspect-box" style="display:none; margin-top:20px;">
                    <div class="card" style="padding:18px; border:1px solid var(--border-color, #e2e8f0); border-radius:12px; background:var(--bg-alt, #f8fafc);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="dashicons dashicons-media-archive" style="color:var(--primary, #2563eb); font-size:22px; width:22px; height:22px;"></span>
                                <strong id="aura-bundle-inspect-filename" style="color:var(--tx-primary, #0f172a); font-size:0.95rem;"></strong>
                            </div>
                            <button type="button" onclick="resetBundleImport()" class="btn btn-glass" style="color:var(--aura-rose, #f43f5e); font-size:0.82rem; padding:4px 10px; font-weight:600; cursor:pointer;">
                                <?php esc_html_e( 'Cambiar archivo', 'aura-suite' ); ?>
                            </button>
                        </div>

                        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; margin-bottom:14px;">
                            <div class="kpi-card" style="padding:12px; text-align:center; background:var(--bg-surface, #ffffff); border:1px solid var(--border-color, #e2e8f0); border-radius:10px;">
                                <div style="font-size:0.75rem; color:var(--tx-muted, #64748b); font-weight:600;"><?php esc_html_e( 'Terceros', 'aura-suite' ); ?></div>
                                <div id="aura-count-tp" style="font-size:1.35rem; font-weight:800; color:var(--tx-primary, #0f172a); margin-top:4px;">0</div>
                            </div>
                            <div class="kpi-card" style="padding:12px; text-align:center; background:var(--bg-surface, #ffffff); border:1px solid var(--border-color, #e2e8f0); border-radius:10px;">
                                <div style="font-size:0.75rem; color:var(--tx-muted, #64748b); font-weight:600;"><?php esc_html_e( 'Áreas', 'aura-suite' ); ?></div>
                                <div id="aura-count-areas" style="font-size:1.35rem; font-weight:800; color:var(--tx-primary, #0f172a); margin-top:4px;">0</div>
                            </div>
                            <div class="kpi-card" style="padding:12px; text-align:center; background:var(--bg-surface, #ffffff); border:1px solid var(--border-color, #e2e8f0); border-radius:10px;">
                                <div style="font-size:0.75rem; color:var(--tx-muted, #64748b); font-weight:600;"><?php esc_html_e( 'Usuarios WP', 'aura-suite' ); ?></div>
                                <div id="aura-count-users" style="font-size:1.35rem; font-weight:800; color:var(--tx-primary, #0f172a); margin-top:4px;">0</div>
                            </div>
                        </div>

                        <div style="font-size:0.84rem; color:var(--tx-secondary, #475569); display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px dashed var(--border-color, #e2e8f0);">
                            <span>📷 <strong id="aura-count-media" style="color:var(--tx-primary, #0f172a);">0</strong> <?php esc_html_e( 'imágenes listas para registrar en Biblioteca de Medios', 'aura-suite' ); ?></span>
                            <span id="aura-bundle-origin" style="color:var(--tx-muted, #64748b); font-size:0.8rem;"></span>
                        </div>
                    </div>

                    <!-- Checkboxes de qué importar -->
                    <div style="margin-top:18px; display:flex; gap:18px; flex-wrap:wrap;">
                        <label style="font-size:0.88rem; color:var(--tx-primary, #0f172a); display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600;">
                            <input type="checkbox" id="aura-imp-tp-chk" checked class="form-check-input" style="width:18px; height:18px; cursor:pointer;">
                            <?php esc_html_e( 'Importar Terceros', 'aura-suite' ); ?>
                        </label>
                        <label style="font-size:0.88rem; color:var(--tx-primary, #0f172a); display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600;">
                            <input type="checkbox" id="aura-imp-areas-chk" checked class="form-check-input" style="width:18px; height:18px; cursor:pointer;">
                            <?php esc_html_e( 'Importar Áreas', 'aura-suite' ); ?>
                        </label>
                        <label style="font-size:0.88rem; color:var(--tx-primary, #0f172a); display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600;">
                            <input type="checkbox" id="aura-imp-users-chk" checked class="form-check-input" style="width:18px; height:18px; cursor:pointer;">
                            <?php esc_html_e( 'Importar Usuarios', 'aura-suite' ); ?>
                        </label>
                    </div>

                    <!-- Botón Importar -->
                    <div style="display:flex; justify-content:flex-end; margin-top:20px; gap:12px; align-items:center;">
                        <button type="button" onclick="closeAuraBundleModal()" class="btn btn-secondary btn-lift">
                            <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                        </button>
                        <button type="button" id="aura-btn-do-import-bundle" onclick="executeAuraBundleImport(this)" class="btn btn-emerald btn-shimmer btn-lift">
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
</div>

<style>
/* Estilos Scoped para el Modal de Migración y Portabilidad Multimedia */
.aura-bundle-card-choice {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: var(--r-md, 12px);
    cursor: pointer;
    background: var(--bg-surface, #ffffff);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.aura-bundle-card-choice:hover {
    border-color: var(--primary, #2563eb);
    background: var(--bg-alt, #f8fafc);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.aura-bundle-card-choice-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.aura-bundle-card-title {
    font-weight: 700;
    color: var(--tx-primary, #0f172a);
    font-size: 0.92rem;
}
.aura-bundle-card-desc {
    font-size: 0.82rem;
    color: var(--tx-muted, #64748b);
}
.aura-bundle-dropzone {
    border: 2px dashed var(--border-color, #cbd5e1);
    border-radius: 14px;
    padding: 36px 20px;
    text-align: center;
    background: var(--bg-alt, #f8fafc);
    cursor: pointer;
    transition: all 0.2s ease;
}
.aura-bundle-dropzone:hover,
.aura-bundle-dropzone.is-dragover {
    border-color: var(--primary, #2563eb);
    background: var(--bg-surface, #ffffff);
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.08);
}
.aura-bundle-dropzone-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(37, 99, 235, 0.1);
    color: var(--primary, #2563eb);
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.aura-bundle-dropzone-icon .dashicons {
    font-size: 26px;
    width: 26px;
    height: 26px;
}
.aura-bundle-dropzone-title {
    font-weight: 700;
    color: var(--tx-primary, #0f172a);
    font-size: 1rem;
    margin-bottom: 4px;
}
.aura-bundle-dropzone-subtitle {
    font-size: 0.83rem;
    color: var(--tx-muted, #64748b);
}
@keyframes aura-pulse-bar {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Modo oscuro específico para los componentes de Portabilidad */
[data-theme="dark"] .aura-bundle-card-choice,
.aura-dark-mode .aura-bundle-card-choice,
.wp-dark-mode-active .aura-bundle-card-choice,
body.dark-mode .aura-bundle-card-choice,
html[data-wp-dark-mode-scheme="dark"] .aura-bundle-card-choice {
    background: #1e1d38 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
[data-theme="dark"] .aura-bundle-card-choice:hover,
.aura-dark-mode .aura-bundle-card-choice:hover,
.wp-dark-mode-active .aura-bundle-card-choice:hover,
body.dark-mode .aura-bundle-card-choice:hover,
html[data-wp-dark-mode-scheme="dark"] .aura-bundle-card-choice:hover {
    background: #252445 !important;
    border-color: var(--aura-indigo-light, #818cf8) !important;
}
[data-theme="dark"] .aura-bundle-dropzone,
.aura-dark-mode .aura-bundle-dropzone,
.wp-dark-mode-active .aura-bundle-dropzone,
body.dark-mode .aura-bundle-dropzone,
html[data-wp-dark-mode-scheme="dark"] .aura-bundle-dropzone {
    background: #16152b !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
}
[data-theme="dark"] .aura-bundle-dropzone:hover,
.aura-dark-mode .aura-bundle-dropzone:hover,
[data-theme="dark"] .aura-bundle-dropzone.is-dragover,
.aura-dark-mode .aura-bundle-dropzone.is-dragover {
    background: #1e1d38 !important;
    border-color: var(--aura-indigo-light, #818cf8) !important;
}
[data-theme="dark"] #aura-bundle-inspect-box .card,
.aura-dark-mode #aura-bundle-inspect-box .card {
    background: #16152b !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
[data-theme="dark"] #aura-bundle-inspect-box .kpi-card,
.aura-dark-mode #aura-bundle-inspect-box .kpi-card {
    background: #1e1d38 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
</style>

<script>
var auraBundleCurrentToken = '';
var _auraBundleBackdropMouseDown = false;

function openAuraBundleModal(defaultTab) {
    var modal = document.getElementById('aura-bundle-modal');
    if (!modal) return;

    modal.classList.add('is-active');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    switchBundleTab(defaultTab || 'export');

    // Efecto Skeleton Loader Shimmer Wave Canónico
    if (typeof AuraUI !== 'undefined' && typeof AuraUI.showModalSkeleton === 'function') {
        AuraUI.showModalSkeleton('aura-bundle-modal', { duration: 280 });
    }

    // Protección anti-cierre accidental al seleccionar texto
    modal.onmousedown = function(e) {
        _auraBundleBackdropMouseDown = (e.target === modal);
    };
    modal.onmouseup = function(e) {
        if (_auraBundleBackdropMouseDown && e.target === modal) {
            closeAuraBundleModal();
        }
        _auraBundleBackdropMouseDown = false;
    };

    // Tecla Escape para cerrar
    document.addEventListener('keydown', handleAuraBundleKeyDown);
}

function handleAuraBundleKeyDown(e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
        closeAuraBundleModal();
    }
}

function closeAuraBundleModal() {
    var modal = document.getElementById('aura-bundle-modal');
    if (!modal) return;

    modal.classList.remove('is-active');
    modal.style.display = 'none';
    document.body.style.overflow = '';

    if (typeof AuraUI !== 'undefined' && typeof AuraUI.hideModalSkeleton === 'function') {
        AuraUI.hideModalSkeleton('aura-bundle-modal');
    }

    document.removeEventListener('keydown', handleAuraBundleKeyDown);
}

function switchBundleTab(tab) {
    var pExp = document.getElementById('aura-bundle-panel-export');
    var pImp = document.getElementById('aura-bundle-panel-import');
    var bExp = document.getElementById('aura-bundle-tab-btn-export');
    var bImp = document.getElementById('aura-bundle-tab-btn-import');

    if (!pExp || !pImp || !bExp || !bImp) return;

    if (tab === 'export') {
        pExp.style.display = 'block';
        pExp.classList.add('is-active');
        pImp.style.display = 'none';
        pImp.classList.remove('is-active');

        bExp.classList.add('is-active');
        bImp.classList.remove('is-active');
    } else {
        pExp.style.display = 'none';
        pExp.classList.remove('is-active');
        pImp.style.display = 'block';
        pImp.classList.add('is-active');

        bImp.classList.add('is-active');
        bExp.classList.remove('is-active');
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

    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="dashicons dashicons-update aura-spin"></span> <?php echo esc_js( __( 'Empaquetando...', 'aura-suite' ) ); ?>';
    
    var progressBox = document.getElementById('aura-export-progress-box');
    if (progressBox) progressBox.style.display = 'block';

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
        btn.innerHTML = originalHtml;
        if (progressBox) progressBox.style.display = 'none';

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
        btn.innerHTML = originalHtml;
        if (progressBox) progressBox.style.display = 'none';
        alert('Error en la solicitud: ' + err.message);
    });
}

// ── Drag & Drop Handlers ──
function handleBundleDragOver(e) {
    e.preventDefault();
    var dropzone = document.getElementById('aura-bundle-dropzone');
    if (dropzone) dropzone.classList.add('is-dragover');
}

function handleBundleDragLeave(e) {
    var dropzone = document.getElementById('aura-bundle-dropzone');
    if (dropzone) dropzone.classList.remove('is-dragover');
}

function handleBundleDrop(e) {
    e.preventDefault();
    var dropzone = document.getElementById('aura-bundle-dropzone');
    if (dropzone) dropzone.classList.remove('is-dragover');

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
    dropzone.innerHTML = '<div style="padding:24px 0;"><span class="dashicons dashicons-update aura-spin" style="font-size:32px; width:32px; height:32px; color:var(--primary, #2563eb);"></span><p style="margin:12px 0 0; font-weight:600; color:var(--tx-primary, #0f172a);"><?php echo esc_js( __( 'Analizando e inspeccionando paquete ZIP...', 'aura-suite' ) ); ?></p></div>';

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
    dropzone.innerHTML = '<input type="file" id="aura-bundle-file-input" accept=".zip" style="display:none" onchange="handleBundleFileSelect(this)"><div class="aura-bundle-dropzone-icon"><span class="dashicons dashicons-upload"></span></div><div class="aura-bundle-dropzone-title"><?php echo esc_js( __( 'Haz clic para seleccionar o arrastra tu archivo .ZIP aquí', 'aura-suite' ) ); ?></div><div class="aura-bundle-dropzone-subtitle"><?php echo esc_js( __( 'Paquete generado previamente por Aura Business Suite', 'aura-suite' ) ); ?></div>';
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

    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="dashicons dashicons-update aura-spin"></span> <?php echo esc_js( __( 'Importando registros y medios...', 'aura-suite' ) ); ?>';

    var statusBox = document.getElementById('aura-import-status-box');
    statusBox.style.display = 'block';
    statusBox.innerHTML = '<div class="alert-card alert-primary" style="text-align:center; padding:16px;"><span class="dashicons dashicons-update aura-spin" style="margin-right:8px; vertical-align:middle;"></span> <?php echo esc_js( __( 'Descargando medios en la Biblioteca de WordPress y asociando IDs... Esto puede tomar unos segundos.', 'aura-suite' ) ); ?></div>';

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
        btn.innerHTML = originalHtml;

        if (!res.success) {
            statusBox.innerHTML = '<div class="alert-card alert-danger" style="padding:14px;">❌ ' + (res.data && res.data.message ? res.data.message : 'Error durante la importación') + '</div>';
            return;
        }

        var r = res.data.results;
        var summaryHtml = '<div class="alert-card alert-success" style="padding:18px;">';
        summaryHtml += '<div style="font-weight:700; margin-bottom:10px; font-size:0.96rem;">✅ ' + res.data.message + '</div>';
        summaryHtml += '<ul style="margin:0; padding-left:18px; line-height:1.6; font-size:0.88rem;">';
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
        btn.innerHTML = originalHtml;
        statusBox.innerHTML = '<div class="alert-card alert-danger" style="padding:14px;">❌ Error de red: ' + err.message + '</div>';
    });
}
</script>
