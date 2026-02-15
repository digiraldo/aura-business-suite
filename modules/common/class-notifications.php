<?php
/**
 * Sistema de Notificaciones
 * 
 * Gestión de notificaciones por email y alertas internas
 *
 * @package AuraBusinessSuite
 * @subpackage Common
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar notificaciones
 */
class Aura_Notifications {
    
    /**
     * Inicializar el sistema de notificaciones
     */
    public static function init() {
        // Hook para personalizar emails
        add_filter('wp_mail_from', array(__CLASS__, 'custom_mail_from'));
        add_filter('wp_mail_from_name', array(__CLASS__, 'custom_mail_from_name'));
        
        // Hook para agregar estilos a los emails
        add_filter('wp_mail_content_type', array(__CLASS__, 'set_html_content_type'));
    }
    
    /**
     * Personalizar remitente del email
     * 
     * @param string $email Email original
     * @return string Email personalizado
     */
    public static function custom_mail_from($email) {
        $custom_email = get_option('aura_notification_email', $email);
        return $custom_email;
    }
    
    /**
     * Personalizar nombre del remitente
     * 
     * @param string $name Nombre original
     * @return string Nombre personalizado
     */
    public static function custom_mail_from_name($name) {
        return get_option('aura_notification_from_name', __('Aura Business Suite', 'aura-suite'));
    }
    
    /**
     * Establecer tipo de contenido HTML
     * 
     * @return string Tipo de contenido
     */
    public static function set_html_content_type() {
        return 'text/html';
    }
    
    /**
     * Enviar notificación de aprobación pendiente
     * 
     * @param int    $transaction_id ID de la transacción
     * @param string $transaction_type Tipo de transacción
     */
    public static function send_approval_pending_notification($transaction_id, $transaction_type = 'financial') {
        // Obtener usuarios con capability de aprobar
        $approvers = self::get_users_with_capability('aura_finance_approve');
        
        if (empty($approvers)) {
            return;
        }
        
        // Obtener datos de la transacción
        $transaction = get_post($transaction_id);
        $author = get_user_by('id', $transaction->post_author);
        $amount = get_post_meta($transaction_id, '_aura_transaction_amount', true);
        $description = $transaction->post_excerpt;
        
        // Preparar email
        $subject = sprintf(
            __('[Aura] Nueva transacción pendiente de aprobación #%d', 'aura-suite'),
            $transaction_id
        );
        
        $message = self::get_email_template('approval-pending', array(
            'transaction_id'   => $transaction_id,
            'author_name'      => $author->display_name,
            'amount'           => number_format($amount, 2),
            'description'      => $description,
            'transaction_link' => admin_url('post.php?post=' . $transaction_id . '&action=edit'),
        ));
        
        // Enviar a todos los aprobadores
        foreach ($approvers as $approver) {
            wp_mail($approver->user_email, $subject, $message);
        }
    }
    
    /**
     * Enviar notificación de transacción aprobada/rechazada
     * 
     * @param int    $transaction_id ID de la transacción
     * @param string $status Estado (approved/rejected)
     * @param string $comment Comentario del aprobador
     */
    public static function send_approval_result_notification($transaction_id, $status, $comment = '') {
        $transaction = get_post($transaction_id);
        $author = get_user_by('id', $transaction->post_author);
        $approver = wp_get_current_user();
        $amount = get_post_meta($transaction_id, '_aura_transaction_amount', true);
        
        $status_text = ($status === 'approved') ? __('APROBADA', 'aura-suite') : __('RECHAZADA', 'aura-suite');
        
        $subject = sprintf(
            __('[Aura] Transacción #%d %s', 'aura-suite'),
            $transaction_id,
            $status_text
        );
        
        $message = self::get_email_template('approval-result', array(
            'transaction_id' => $transaction_id,
            'status'         => $status_text,
            'status_class'   => $status,
            'approver_name'  => $approver->display_name,
            'amount'         => number_format($amount, 2),
            'comment'        => $comment,
            'transaction_link' => admin_url('post.php?post=' . $transaction_id . '&action=edit'),
        ));
        
        wp_mail($author->user_email, $subject, $message);
    }
    
    /**
     * Enviar alerta de mantenimiento de vehículo
     * 
     * @param int   $vehicle_id ID del vehículo
     * @param array $alert_data Datos de la alerta
     */
    public static function send_vehicle_maintenance_alert($vehicle_id, $alert_data) {
        // Obtener usuarios con capability de recibir alertas
        $recipients = self::get_users_with_capability('aura_vehicles_alerts');
        
        if (empty($recipients)) {
            return;
        }
        
        $vehicle = get_post($vehicle_id);
        $plate = get_post_meta($vehicle_id, '_aura_vehicle_plate', true);
        $current_km = get_post_meta($vehicle_id, '_aura_vehicle_current_km', true);
        $next_maintenance_km = get_post_meta($vehicle_id, '_aura_vehicle_next_maintenance_km', true);
        $km_remaining = $next_maintenance_km - $current_km;
        
        $subject = sprintf(
            __('[Aura] Alerta de Mantenimiento - Vehículo %s', 'aura-suite'),
            $plate
        );
        
        $message = self::get_email_template('vehicle-maintenance', array(
            'vehicle_title'     => $vehicle->post_title,
            'plate'             => $plate,
            'current_km'        => number_format($current_km),
            'next_maintenance'  => number_format($next_maintenance_km),
            'km_remaining'      => number_format($km_remaining),
            'urgency_class'     => $km_remaining < 200 ? 'critical' : 'warning',
            'vehicle_link'      => admin_url('post.php?post=' . $vehicle_id . '&action=edit'),
        ));
        
        foreach ($recipients as $recipient) {
            wp_mail($recipient->user_email, $subject, $message);
        }
    }
    
    /**
     * Enviar alerta de consumo eléctrico alto
     * 
     * @param array $consumption_data Datos del consumo
     */
    public static function send_electricity_alert($consumption_data) {
        // Obtener usuarios con capability de recibir alertas
        $recipients = self::get_users_with_capability('aura_electric_alerts_receive');
        
        if (empty($recipients)) {
            return;
        }
        
        $subject = __('[Aura] Alerta de Consumo Eléctrico Alto', 'aura-suite');
        
        $message = self::get_email_template('electricity-alert', array(
            'current_consumption' => number_format($consumption_data['current'], 2),
            'threshold'           => number_format($consumption_data['threshold'], 2),
            'percentage_over'     => number_format($consumption_data['percentage_over'], 1),
            'date'                => date_i18n(get_option('date_format'), $consumption_data['timestamp']),
            'dashboard_link'      => admin_url('admin.php?page=aura-electricity-dashboard'),
        ));
        
        foreach ($recipients as $recipient) {
            wp_mail($recipient->user_email, $subject, $message);
        }
    }
    
    /**
     * Obtener usuarios con una capability específica
     * 
     * @param string $capability Capability a buscar
     * @return array Array de objetos WP_User
     */
    private static function get_users_with_capability($capability) {
        $args = array(
            'capability' => $capability,
            'fields'     => 'all',
        );
        
        return get_users($args);
    }
    
    /**
     * Obtener plantilla de email
     * 
     * @param string $template Nombre de la plantilla
     * @param array  $data Datos para reemplazar en la plantilla
     * @return string HTML del email
     */
    private static function get_email_template($template, $data = array()) {
        $logo_url = AURA_PLUGIN_URL . 'assets/images/logo-aura.png';
        $site_name = get_bloginfo('name');
        
        // Header común para todos los emails
        $header = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
                .header img { max-width: 80px; }
                .content { padding: 30px; background: #f9f9f9; }
                .card { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .status-approved { color: #10b981; font-weight: bold; }
                .status-rejected { color: #ef4444; font-weight: bold; }
                .status-warning { color: #f59e0b; font-weight: bold; }
                .status-critical { color: #dc2626; font-weight: bold; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .data-row { padding: 10px 0; border-bottom: 1px solid #eee; }
                .data-label { font-weight: bold; color: #667eea; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <img src="' . $logo_url . '" alt="Aura">
                    <h2 style="color: white; margin: 10px 0;">' . $site_name . '</h2>
                </div>
                <div class="content">
        ';
        
        $footer = '
                </div>
                <div class="footer">
                    <p>Este es un email automático de Aura Business Suite</p>
                    <p>&copy; ' . date('Y') . ' ' . $site_name . '</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        // Plantillas específicas
        $templates = array(
            'approval-pending' => '
                <div class="card">
                    <h2>🔔 Nueva Transacción Pendiente de Aprobación</h2>
                    <div class="data-row">
                        <span class="data-label">Transacción #:</span> ' . $data['transaction_id'] . '
                    </div>
                    <div class="data-row">
                        <span class="data-label">Solicitante:</span> ' . $data['author_name'] . '
                    </div>
                    <div class="data-row">
                        <span class="data-label">Monto:</span> $' . $data['amount'] . '
                    </div>
                    <div class="data-row">
                        <span class="data-label">Descripción:</span> ' . $data['description'] . '
                    </div>
                    <p style="margin-top: 20px;">
                        <a href="' . $data['transaction_link'] . '" class="button">Ver y Aprobar Transacción</a>
                    </p>
                </div>
            ',
            
            'approval-result' => '
                <div class="card">
                    <h2>📋 Resultado de Aprobación</h2>
                    <p style="font-size: 18px;">
                        Tu transacción <strong>#' . $data['transaction_id'] . '</strong> ha sido 
                        <span class="status-' . $data['status_class'] . '">' . $data['status'] . '</span>
                    </p>
                    <div class="data-row">
                        <span class="data-label">Aprobado por:</span> ' . $data['approver_name'] . '
                    </div>
                    <div class="data-row">
                        <span class="data-label">Monto:</span> $' . $data['amount'] . '
                    </div>
                    ' . (!empty($data['comment']) ? '
                    <div class="data-row">
                        <span class="data-label">Comentario:</span><br>' . $data['comment'] . '
                    </div>
                    ' : '') . '
                    <p style="margin-top: 20px;">
                        <a href="' . $data['transaction_link'] . '" class="button">Ver Transacción</a>
                    </p>
                </div>
            ',
            
            'vehicle-maintenance' => '
                <div class="card">
                    <h2>🚗 Alerta de Mantenimiento de Vehículo</h2>
                    <p class="status-' . $data['urgency_class'] . '">Un vehículo requiere mantenimiento pronto</p>
                    <div class="data-row">
                        <span class="data-label">Vehículo:</span> ' . $data['vehicle_title'] . '
                    </div>
                    <div class="data-row">
                        <span class="data-label">Placa:</span> ' . $data['plate'] . '
                    </div>
                    <div class="data-row">
                        <span class="data-label">Kilometraje Actual:</span> ' . $data['current_km'] . ' km
                    </div>
                    <div class="data-row">
                        <span class="data-label">Próximo Mantenimiento:</span> ' . $data['next_maintenance'] . ' km
                    </div>
                    <div class="data-row">
                        <span class="data-label">Kilómetros Restantes:</span> <strong>' . $data['km_remaining'] . ' km</strong>
                    </div>
                    <p style="margin-top: 20px;">
                        <a href="' . $data['vehicle_link'] . '" class="button">Ver Vehículo</a>
                    </p>
                </div>
            ',
            
            'electricity-alert' => '
                <div class="card">
                    <h2>⚡ Alerta de Consumo Eléctrico</h2>
                    <p class="status-critical">El consumo eléctrico ha superado el umbral configurado</p>
                    <div class="data-row">
                        <span class="data-label">Consumo Actual:</span> ' . $data['current_consumption'] . ' kWh
                    </div>
                    <div class="data-row">
                        <span class="data-label">Umbral Configurado:</span> ' . $data['threshold'] . ' kWh
                    </div>
                    <div class="data-row">
                        <span class="data-label">Exceso:</span> <strong>' . $data['percentage_over'] . '%</strong>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Fecha:</span> ' . $data['date'] . '
                    </div>
                    <p style="margin-top: 20px;">
                        <a href="' . $data['dashboard_link'] . '" class="button">Ver Dashboard de Electricidad</a>
                    </p>
                </div>
            ',
        );
        
        $body = isset($templates[$template]) ? $templates[$template] : '<p>Notificación de Aura Business Suite</p>';
        
        return $header . $body . $footer;
    }
}
