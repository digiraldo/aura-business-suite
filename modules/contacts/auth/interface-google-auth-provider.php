<?php
/**
 * Interfaz para Proveedores de Autenticación de Google en Aura Suite
 *
 * Permite desacoplar el cliente de People API / Calendar / Drive del método
 * específico de autenticación (OAuth2 de Usuario vs Service Account con Domain-Wide Delegation).
 *
 * @package    Aura_Business_Suite
 * @subpackage Contacts\Auth
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Aura_Google_Auth_Provider {

    /**
     * Obtiene un Access Token válido para realizar peticiones a las APIs de Google.
     * Si el token actual expiró o está próximo a expirar, se renueva automáticamente.
     *
     * @return string|WP_Error Token de acceso en string o WP_Error si falló la renovación.
     */
    public function get_access_token();

    /**
     * Obtiene la dirección de correo electrónico de la cuenta de Google vinculada.
     *
     * @return string Correo electrónico (ej: centromateo@gmail.com) o cadena vacía si no está conectado.
     */
    public function get_account_email(): string;

    /**
     * Verifica si las credenciales base del proveedor están configuradas.
     *
     * @return bool
     */
    public function is_configured(): bool;

    /**
     * Verifica si el proveedor tiene autorización activa (autenticado).
     *
     * @return bool
     */
    public function is_authenticated(): bool;

    /**
     * Verifica si el proveedor está actualmente configurado y conectado con autorización válida.
     *
     * @return bool
     */
    public function is_connected(): bool;

    /**
     * Desconecta la cuenta actual, limpiando tokens de acceso/refresh guardados.
     * No borra los contactos locales de la base de datos de WordPress.
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Devuelve el slug identificador del modo de autenticación.
     *
     * @return string 'oauth_user' | 'service_account_dwd'
     */
    public function get_auth_mode(): string;

    /**
     * Devuelve una etiqueta amigable y legible para la interfaz de administración.
     *
     * @return string
     */
    public function get_label(): string;
}
