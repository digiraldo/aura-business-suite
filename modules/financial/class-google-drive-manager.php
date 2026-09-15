<?php
/**
 * Gestor de Google Drive para Aura Business Suite
 * 
 * Permite subir archivos directamente a Google Drive (Mi Unidad y Unidades Compartidas) y
 * obtener los enlaces de visualización y previsualización para usarlos como evidencia.
 * 
 * @package AuraBusinessSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Cargar el autoloader de Composer si existe en cualquiera de las rutas habituales
if (!class_exists('Google\Client')) {
    $autoload_paths = [
        defined('AURA_PLUGIN_DIR') ? AURA_PLUGIN_DIR . 'vendor/autoload.php' : '',
        dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php',
        dirname(__DIR__, 2) . '/vendor/autoload.php',
    ];
    foreach ($autoload_paths as $path) {
        if (!empty($path) && file_exists($path)) {
            require_once $path;
            if (class_exists('Google\Client')) {
                break;
            }
        }
    }
}

class Aura_Drive_Manager {
    
    private $client = null;
    private $service = null;
    private $root_folder_id = '';
    private $drive_type = 'my_drive'; // 'my_drive' o 'shared_drive'
    private $service_account_email = '';
    private $is_configured = false;
    
    public function __construct() {
        $this->init_client();
    }
    
    /**
     * Inicializar el cliente de Google Drive
     */
    private function init_client() {
        if (!class_exists('Google\Client')) {
            return;
        }
        
        $json_key = get_option('aura_gdrive_credentials_json', get_option('aura_finance_gdrive_credentials_json', ''));
        $this->root_folder_id = trim(get_option('aura_gdrive_folder_id', get_option('aura_finance_gdrive_folder_id', '')));
        $this->drive_type = get_option('aura_gdrive_type', 'my_drive');
        
        if (empty($json_key) || empty($this->root_folder_id)) {
            return;
        }
        
        try {
            $key_data = json_decode($json_key, true);
            if (!is_array($key_data) || ($key_data['type'] ?? '') !== 'service_account') {
                return;
            }
            
            $this->service_account_email = $key_data['client_email'] ?? '';
            
            $this->client = new \Google\Client();
            if (class_exists('\GuzzleHttp\Client')) {
                $this->client->setHttpClient(new \GuzzleHttp\Client([
                    'http_errors' => false,
                ]));
            }
            $this->client->setAuthConfig($key_data);
            $this->client->addScope(\Google\Service\Drive::DRIVE);
            
            $this->service = new \Google\Service\Drive($this->client);
            $this->is_configured = true;
        } catch (\Exception $e) {
            error_log('Error inicializando Aura Drive Manager: ' . $e->getMessage());
        }
    }
    
    /**
     * Verificar si el gestor está configurado correctamente
     */
    public function is_ready() {
        return $this->is_configured && $this->service !== null;
    }

    /**
     * Obtener el correo del Service Account
     */
    public function get_service_account_email() {
        return $this->service_account_email;
    }

    /**
     * Obtener el tipo de unidad configurado
     */
    public function get_drive_type() {
        return $this->drive_type;
    }

    /**
     * Extraer el ID de archivo de un enlace o ID de Google Drive
     */
    public static function extract_file_id($url_or_id) {
        if (is_array($url_or_id)) {
            if (!empty($url_or_id['file_id'])) return (string) $url_or_id['file_id'];
            $url_or_id = $url_or_id['url'] ?? ($url_or_id['file_url'] ?? ($url_or_id['preview_url'] ?? ''));
        }
        if (!is_string($url_or_id) || empty($url_or_id)) return '';
        $url_or_id = trim($url_or_id);
        
        // Si ya parece un ID alfanumérico directo
        if (preg_match('/^[a-zA-Z0-9_-]{25,}$/', $url_or_id)) {
            return $url_or_id;
        }
        
        // Si es URL con /file/d/{ID}
        if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/i', $url_or_id, $m)) {
            return $m[1];
        }
        
        // Si es URL con id={ID}
        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/i', $url_or_id, $m)) {
            return $m[1];
        }
        
        return $url_or_id;
    }

    /**
     * Comprobar si una URL corresponde a Google Drive
     */
    public static function is_drive_url($url) {
        if (is_array($url)) {
            if (!empty($url['is_drive'])) return true;
            $url = $url['url'] ?? ($url['file_url'] ?? ($url['preview_url'] ?? ''));
        }
        if (!is_string($url) || empty($url)) return false;
        return (strpos($url, 'drive.google.com') !== false || strpos($url, 'googleusercontent.com') !== false);
    }

    /**
     * Generar URL de vista previa embebible (Iframe para PDF o visor)
     */
    public static function get_preview_url($url_or_id) {
        if (is_array($url_or_id)) {
            if (!empty($url_or_id['preview_url'])) return (string) $url_or_id['preview_url'];
            $url_or_id = $url_or_id['url'] ?? ($url_or_id['file_url'] ?? '');
        }
        if (!is_string($url_or_id) || empty($url_or_id)) return '';
        $file_id = self::extract_file_id($url_or_id);
        if ($file_id) {
            return 'https://drive.google.com/file/d/' . $file_id . '/preview';
        }
        return $url_or_id;
    }

    /**
     * Generar URL directa de miniatura o imagen
     */
    public static function get_thumbnail_url($url_or_id, $size = 1600) {
        if (is_array($url_or_id)) {
            if (!empty($url_or_id['thumbnail_url'])) return (string) $url_or_id['thumbnail_url'];
            $url_or_id = $url_or_id['url'] ?? ($url_or_id['file_url'] ?? '');
        }
        if (!is_string($url_or_id) || empty($url_or_id)) return '';
        $file_id = self::extract_file_id($url_or_id);
        if ($file_id) {
            return 'https://drive.google.com/thumbnail?id=' . $file_id . '&sz=w' . intval($size);
        }
        return $url_or_id;
    }

    /**
     * Generar URL de descarga directa
     */
    public static function get_download_url($url_or_id) {
        if (is_array($url_or_id)) {
            if (!empty($url_or_id['download_url'])) return (string) $url_or_id['download_url'];
            $url_or_id = $url_or_id['url'] ?? ($url_or_id['file_url'] ?? '');
        }
        if (!is_string($url_or_id) || empty($url_or_id)) return '';
        $file_id = self::extract_file_id($url_or_id);
        if ($file_id) {
            return 'https://drive.google.com/uc?export=download&id=' . $file_id;
        }
        return $url_or_id;
    }
    
    /**
     * Subir un archivo a Google Drive (compatible con Mi Unidad y Unidades Compartidas)
     * 
     * @param string $file_path Ruta temporal del archivo en el servidor local
     * @param string $file_name Nombre original del archivo
     * @param string $mime_type Tipo MIME del archivo
     * @param string $module_folder_name Nombre de la carpeta del módulo (ej. 'Finanzas')
     * @param string|null $target_date Fecha de operación de la transacción (YYYY-MM-DD o timestamp)
     * @return array|false Datos del archivo subido o false si falla
     */
    public function upload_file($file_path, $file_name, $mime_type, $module_folder_name = 'Finanzas', $target_date = null) {
        if (!$this->is_ready() || !file_exists($file_path)) {
            return false;
        }
        
        try {
            // Resolver año y mes basados en la Fecha de Operación de la transacción (soporta d/m/Y, Y-m-d, timestamps)
            $target_year = null;
            $target_month = null;

            if (!empty($target_date)) {
                $raw_target = trim((string) $target_date);
                // Caso 1: Formato dd/mm/YYYY (ej. 17/08/2026 del datepicker)
                if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $raw_target, $m)) {
                    $target_year = sprintf('%04d', (int) $m[3]);
                    $target_month = sprintf('%02d', (int) $m[2]);
                }
                // Caso 2: Formato YYYY-MM-DD o YYYY/MM/DD (ej. 2026-08-17 de MySQL)
                elseif (preg_match('#^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})#', $raw_target, $m)) {
                    $target_year = sprintf('%04d', (int) $m[1]);
                    $target_month = sprintf('%02d', (int) $m[2]);
                }
                // Caso 3: Reemplazo / por - para compatibilidad europea en strtotime
                else {
                    $ts = strtotime(str_replace('/', '-', $raw_target));
                    if ($ts) {
                        $target_year = date('Y', $ts);
                        $target_month = date('m', $ts);
                    }
                }
            }

            if (!$target_year || !$target_month) {
                $now = time();
                $target_year = date('Y', $now);
                $target_month = date('m', $now);
            }

            // 1. Obtener/crear carpeta del módulo dentro de la raíz (soporta Mi Unidad y Unidad Compartida)
            $module_folder_id = $this->create_or_get_folder($module_folder_name, $this->root_folder_id);
            if (!$module_folder_id) return false;
            
            // 2. Obtener/crear carpeta del año según Fecha de Operación (ej. '2026')
            $year_folder_id = $this->create_or_get_folder($target_year, $module_folder_id);
            if (!$year_folder_id) return false;
            
            // 3. Obtener/crear carpeta del mes según Fecha de Operación (ej. '08')
            $month_folder_id = $this->create_or_get_folder($target_month, $year_folder_id);
            if (!$month_folder_id) return false;
            
            // 4. Subir el archivo
            $file = new \Google\Service\Drive\DriveFile();
            $safe_filename = uniqid() . '_' . sanitize_file_name($file_name);
            $file->setName($safe_filename);
            $file->setParents([$month_folder_id]);
            
            $content = file_get_contents($file_path);
            
            $result = $this->service->files->create($file, [
                'data' => $content,
                'mimeType' => $mime_type,
                'uploadType' => 'multipart',
                'fields' => 'id, name, mimeType, webViewLink, webContentLink, thumbnailLink',
                'supportsAllDrives' => true
            ]);
            
            if (!$result || empty($result->id)) {
                return false;
            }

            // 5. Asignar permiso público de lectura (anyone / reader) para previsualización in-app
            try {
                $permission = new \Google\Service\Drive\Permission([
                    'role' => 'reader',
                    'type' => 'anyone'
                ]);
                $this->service->permissions->create($result->id, $permission, [
                    'supportsAllDrives' => true
                ]);
            } catch (\Exception $perm_err) {
                error_log('Aura Drive: Aviso de permisos al compartir comprobante (posible política de dominio Workspace): ' . $perm_err->getMessage());
            }

            $file_id = $result->id;
            $view_link = $result->webViewLink ?: ('https://drive.google.com/file/d/' . $file_id . '/view');
            $preview_link = 'https://drive.google.com/file/d/' . $file_id . '/preview';
            $thumbnail_link = 'https://drive.google.com/thumbnail?id=' . $file_id . '&sz=w1600';
            $download_link = 'https://drive.google.com/uc?export=download&id=' . $file_id;

            return [
                'success'       => true,
                'file_id'       => $file_id,
                'file_url'      => $view_link,
                'view_url'      => $view_link,
                'preview_url'   => $preview_link,
                'thumbnail_url' => $thumbnail_link,
                'download_url'  => $download_link,
                'filename'      => $file_name,
                'mime_type'     => $mime_type,
                'is_drive'      => true
            ];
            
        } catch (\Exception $e) {
            error_log('Error subiendo archivo a Google Drive: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Buscar una subcarpeta por nombre o crearla si no existe
     */
    private function create_or_get_folder($folder_name, $parent_folder_id) {
        try {
            // Escapar comillas en el nombre de la carpeta para la query de Drive
            $safe_name = str_replace("'", "\'", $folder_name);
            
            $query = sprintf(
                "name = '%s' and mimeType = 'application/vnd.google-apps.folder' and '%s' in parents and trashed = false",
                $safe_name,
                $parent_folder_id
            );
            
            $optParams = [
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true
            ];
            
            $results = $this->service->files->listFiles($optParams);
            
            if ($results && count($results->getFiles()) > 0) {
                return $results->getFiles()[0]->getId();
            }
            
            // No existe, crearla
            $folder = new \Google\Service\Drive\DriveFile();
            $folder->setName($folder_name);
            $folder->setMimeType('application/vnd.google-apps.folder');
            $folder->setParents([$parent_folder_id]);
            
            $created_folder = $this->service->files->create($folder, [
                'fields' => 'id',
                'supportsAllDrives' => true
            ]);
            
            return $created_folder ? $created_folder->getId() : false;
            
        } catch (\Google\Service\Exception $g_err) {
            error_log(sprintf(
                'Aura Drive API Error (%d) buscando/creando carpeta "%s" en padre "%s": %s',
                $g_err->getCode(),
                $folder_name,
                $parent_folder_id,
                $g_err->getMessage()
            ));
            return false;
        } catch (\Exception $e) {
            error_log(sprintf(
                'Aura Drive Exception buscando/creando carpeta "%s" en padre "%s": %s',
                $folder_name,
                $parent_folder_id,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Probar conexión con Google Drive (lectura y escritura en la carpeta configurada)
     * 
     * @param string|null $override_key JSON opcional para probar antes de guardar
     * @param string|null $override_folder_id ID de carpeta opcional
     * @param string|null $override_type Tipo opcional ('my_drive' o 'shared_drive')
     * @return array Resultado con status, mensaje y detalles
     */
    public function test_connection($override_key = null, $override_folder_id = null, $override_type = null) {
        if (!class_exists('Google\Client')) {
            $autoload_paths = [
                defined('AURA_PLUGIN_DIR') ? AURA_PLUGIN_DIR . 'vendor/autoload.php' : '',
                dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php',
                dirname(__DIR__, 2) . '/vendor/autoload.php',
            ];
            foreach ($autoload_paths as $path) {
                if (!empty($path) && file_exists($path)) {
                    require_once $path;
                    if (class_exists('Google\Client')) {
                        break;
                    }
                }
            }
        }

        if (!class_exists('Google\Client')) {
            return [
                'success' => false,
                'message' => __('El SDK de Google Client no está instalado en la carpeta vendor.', 'aura-suite')
            ];
        }

        $json_key = $override_key !== null ? $override_key : get_option('aura_gdrive_credentials_json', get_option('aura_finance_gdrive_credentials_json', ''));
        $folder_id = $override_folder_id !== null ? trim($override_folder_id) : trim(get_option('aura_gdrive_folder_id', get_option('aura_finance_gdrive_folder_id', '')));
        $drive_type = $override_type !== null ? $override_type : $this->drive_type;

        if (empty($json_key)) {
            return [
                'success' => false,
                'message' => __('No se han configurado las credenciales JSON del Service Account.', 'aura-suite')
            ];
        }

        if (empty($folder_id)) {
            return [
                'success' => false,
                'message' => __('No se ha especificado el ID de la carpeta principal o Unidad Compartida.', 'aura-suite')
            ];
        }

        $client = null;
        $service = null;
        $service_account_email = $this->service_account_email;

        // Si se pasaron credenciales personalizadas o el cliente actual no está listo
        try {
            $key_data = json_decode($json_key, true);
            if (!is_array($key_data) || ($key_data['type'] ?? '') !== 'service_account') {
                return [
                    'success' => false,
                    'message' => __('El archivo JSON no parece ser una clave de Service Account válida de Google Cloud.', 'aura-suite')
                ];
            }
            $service_account_email = $key_data['client_email'] ?? '';

            $client = new \Google\Client();
            if (class_exists('\GuzzleHttp\Client')) {
                $client->setHttpClient(new \GuzzleHttp\Client([
                    'http_errors' => false,
                ]));
            }
            $client->setAuthConfig($key_data);
            $client->addScope(\Google\Service\Drive::DRIVE);
            $service = new \Google\Service\Drive($client);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Error al inicializar credenciales: ', 'aura-suite') . $e->getMessage()
            ];
        }

        try {
            // 1. Verificar acceso a la carpeta / unidad
            $folder = $service->files->get($folder_id, [
                'fields' => 'id, name, mimeType, driveId, capabilities',
                'supportsAllDrives' => true
            ]);

            if (!$folder) {
                return [
                    'success' => false,
                    'message' => __('No se pudo encontrar la carpeta con el ID proporcionado.', 'aura-suite')
                ];
            }

            $folder_name = $folder->getName() ?: __('(Carpeta raíz / Unidad)', 'aura-suite');
            $is_shared_drive = !empty($folder->getDriveId());

            // 2. Probar permisos de escritura creando un archivo temporal de prueba
            $test_file = new \Google\Service\Drive\DriveFile();
            $test_file->setName('.aura_connection_test_' . time() . '.txt');
            $test_file->setParents([$folder_id]);
            
            $created_test = $service->files->create($test_file, [
                'data' => 'Prueba de conexión exitosa desde Aura Business Suite (' . current_time('mysql') . ')',
                'mimeType' => 'text/plain',
                'uploadType' => 'multipart',
                'fields' => 'id',
                'supportsAllDrives' => true
            ]);

            // 3. Eliminar el archivo de prueba
            if ($created_test && !empty($created_test->id)) {
                try {
                    $service->files->delete($created_test->id, [
                        'supportsAllDrives' => true
                    ]);
                } catch (\Exception $del_err) {}
            }

            return [
                'success' => true,
                'message' => sprintf(
                    __('¡Conexión exitosa! Se verificó acceso de lectura y escritura a "%s" (%s).', 'aura-suite'),
                    $folder_name,
                    $is_shared_drive ? __('Unidad Compartida', 'aura-suite') : __('Mi Unidad', 'aura-suite')
                ),
                'details' => [
                    'folder_name'     => $folder_name,
                    'folder_id'       => $folder_id,
                    'is_shared_drive' => $is_shared_drive,
                    'account_email'   => $service_account_email,
                    'drive_type'      => $drive_type
                ]
            ];

        } catch (\Google\Service\Exception $g_err) {
            $code = $g_err->getCode();
            $err_msg = $g_err->getMessage();
            
            if ($code == 404) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        __('Error 404: No se encontró la carpeta con ID "%s" o el Service Account no tiene acceso. Recuerda compartirla con: %s', 'aura-suite'),
                        $folder_id,
                        $service_account_email
                    )
                ];
            }

            if ($code == 403) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        __('Error 403: Permiso denegado. Asegúrate de otorgar rol de "Editor" (en Mi Unidad) o "Administrador de contenido" (en Unidad Compartida) a: %s', 'aura-suite'),
                        $service_account_email
                    )
                ];
            }

            return [
                'success' => false,
                'message' => 'Error de Google Drive API (' . $code . '): ' . $err_msg
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ];
        }
    }

    /**
     * Endpoint AJAX para probar la conexión con Google Drive
     */
    public static function ajax_test_connection() {
        check_ajax_referer('aura_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('aura_admin_settings') && !current_user_can('aura_finance_settings') && !current_user_can('aura_finance_manage') && !current_user_can('aura_admin_gdrive_config')) {
            wp_send_json_error([
                'message' => __('No tienes permisos suficientes para realizar esta prueba.', 'aura-suite')
            ]);
        }

        $folder_id = isset($_POST['folder_id']) ? sanitize_text_field(wp_unslash($_POST['folder_id'])) : null;
        $credentials_json = !empty($_POST['credentials_json']) ? wp_unslash($_POST['credentials_json']) : null;
        $drive_type = isset($_POST['drive_type']) ? sanitize_key($_POST['drive_type']) : null;

        $manager = new self();
        $result = $manager->test_connection($credentials_json, $folder_id, $drive_type);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Eliminar un archivo en Google Drive por ID
     * 
     * @param string $file_id ID del archivo en Google Drive
     * @return bool
     */
    public static function delete_file($file_id) {
        if (empty($file_id)) {
            return false;
        }

        try {
            $manager = new self();
            $client = $manager->get_client();
            if (!$client) {
                return false;
            }

            $service = new \Google\Service\Drive($client);
            $service->files->delete($file_id, [
                'supportsAllDrives' => true
            ]);
            return true;
        } catch (\Throwable $e) {
            error_log('Aura Business Suite - Error al eliminar archivo de Drive (' . $file_id . '): ' . $e->getMessage());
            return false;
        }
    }
}

// Registrar el hook AJAX de prueba
add_action('wp_ajax_aura_test_gdrive_connection', ['Aura_Drive_Manager', 'ajax_test_connection']);
