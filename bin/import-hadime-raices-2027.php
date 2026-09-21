<?php
/**
 * Script de importación del formulario 'Inscripciones Hadime Raíces 2027'
 * y sus respuestas desde el backup RBAC hacia las tablas de Formularios de Aura Suite.
 *
 * Ejecución: php bin/import-hadime-raices-2027.php
 *
 * @package AuraBusinessSuite
 */

// Cargar WordPress si no está cargado
if ( ! defined( 'ABSPATH' ) ) {
    // Configurar variables globales para entorno CLI de WordPress en Laragon
    if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
        $_SERVER['HTTP_HOST'] = 'diserwp.test';
    }
    if ( ! isset( $_SERVER['REQUEST_SCHEME'] ) ) {
        $_SERVER['REQUEST_SCHEME'] = 'https';
    }
    if ( ! isset( $_SERVER['SERVER_PORT'] ) ) {
        $_SERVER['SERVER_PORT'] = '443';
    }
    if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
        $_SERVER['REQUEST_URI'] = '/';
    }

    $wp_load = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php';
    if ( ! file_exists( $wp_load ) ) {
        $wp_load = 'C:/laragon/www/diserwp/wp-load.php';
    }
    if ( ! file_exists( $wp_load ) ) {
        die( "Error: No se encontró wp-load.php\n" );
    }
    require_once $wp_load;
}

global $wpdb;

echo "=== INICIANDO IMPORTACIÓN DE HADIME RAÍCES 2027 ===\n";

$base_dir = dirname( __DIR__ );
$backup_dir = $base_dir . '/documentacion/backup_formularios_rbac_2026-09-21_03-31-30';
$form_file = $backup_dir . '/data/forms/2aaaa0.json';
$responses_file = $backup_dir . '/data/responses/2aaaa0_responses.json';
$banner_src = $backup_dir . '/uploads/23237f_field_image_2_8ddb08a0.jpeg';

if ( ! file_exists( $form_file ) ) {
    die( "Error: No se encontró el archivo del formulario en {$form_file}\n" );
}
if ( ! file_exists( $responses_file ) ) {
    die( "Error: No se encontró el archivo de respuestas en {$responses_file}\n" );
}

// 1. Copiar banner decorativo
$uploads_aura = WP_CONTENT_DIR . '/uploads/aura-forms';
if ( ! is_dir( $uploads_aura ) ) {
    wp_mkdir_p( $uploads_aura );
}
$banner_dest = $uploads_aura . '/23237f_field_image_2_8ddb08a0.jpeg';
if ( file_exists( $banner_src ) ) {
    copy( $banner_src, $banner_dest );
    echo "✓ Banner copiado a: {$banner_dest}\n";
} else {
    echo "! Advertencia: Banner origen no encontrado en {$banner_src}\n";
}
$banner_url = 'https://diserwp.test/wp-content/uploads/aura-forms/23237f_field_image_2_8ddb08a0.jpeg';

// 2. Leer definición del formulario
$form_data = json_decode( file_get_contents( $form_file ), true );
if ( ! $form_data ) {
    die( "Error: El JSON del formulario es inválido.\n" );
}

$table_forms       = $wpdb->prefix . 'aura_forms';
$table_fields      = $wpdb->prefix . 'aura_form_fields';
$table_submissions = $wpdb->prefix . 'aura_form_submissions';

$slug = 'inscripciones-hadime-raices-2027';

// Verificar si ya existe el formulario por slug
$existing_form_id = $wpdb->get_var( $wpdb->prepare(
    "SELECT id FROM {$table_forms} WHERE slug = %s AND deleted_at IS NULL",
    $slug
) );

$form_row = [
    'title'               => 'Inscripciones Hadime Raíces 2027',
    'slug'                => $slug,
    'description'         => 'Enero 25 a Julio 12 de 2027',
    'type'                => 'generic',
    'course_id'           => null,
    'area_id'             => 5, // Hadime Raíces
    'submit_button_label' => 'Enviar',
    'success_message'     => 'Formulario Enviado con Éxito',
    'redirect_url'        => null,
    'is_active'           => 1,
    'requires_login'      => 0,
    'accept_multiple'     => 0,
    'max_submissions'     => null,
    'close_date'          => '2027-01-25 23:59:59',
    'primary_color'       => '#a32636',
    'logo_url'            => 'https://diserwp.test/wp-content/uploads/2026/04/Logos-Hadime-Raices-300x64.png',
    'company_name'        => 'Centro de Entrenamiento Mateo',
    'notify_admin_emails' => 'digiraldo0@gmail.com',
    'notify_submitter'    => 1,
    'auto_assign_trigger' => 'none',
    'auto_assign_days'    => 0,
    'created_by'          => 1,
    'updated_by'          => 1,
    'created_at'          => '2026-05-18 21:41:08',
    'updated_at'          => '2026-05-18 21:44:26',
    'deleted_at'          => null,
];

if ( $existing_form_id ) {
    $wpdb->update( $table_forms, $form_row, [ 'id' => $existing_form_id ] );
    $form_id = (int) $existing_form_id;
    echo "✓ Formulario existente actualizado con ID: {$form_id}\n";

    // Limpiar campos y respuestas antiguas para reimportar limpiamente
    $wpdb->delete( $table_fields, [ 'form_id' => $form_id ] );
    $wpdb->delete( $table_submissions, [ 'form_id' => $form_id ] );
} else {
    $wpdb->insert( $table_forms, $form_row );
    $form_id = (int) $wpdb->insert_id;
    echo "✓ Formulario nuevo creado con ID: {$form_id}\n";
}

// 3. Importar los 24 campos
$fields_source = $form_data['fields'] ?? [];
echo "Importando " . count( $fields_source ) . " campos...\n";

$sort_order = 0;
foreach ( $fields_source as $f ) {
    $field_uid   = sanitize_text_field( $f['field_uid'] );
    $label       = sanitize_text_field( $f['label'] );
    $description = ! empty( $f['description'] ) ? sanitize_textarea_field( $f['description'] ) : null;
    $field_type  = sanitize_key( $f['type'] );
    $placeholder = ! empty( $f['placeholder'] ) ? sanitize_text_field( $f['placeholder'] ) : null;
    $is_required = ! empty( $f['required'] ) ? 1 : 0;

    $options_json = null;
    if ( ! empty( $f['options'] ) ) {
        $lines = preg_split( '/\r\n|\r|\n/', trim( $f['options'] ) );
        $options_arr = [];
        foreach ( $lines as $line ) {
            $cleaned = trim( $line );
            if ( $cleaned !== '' ) {
                $options_arr[] = $cleaned;
            }
        }
        $options_json = wp_json_encode( $options_arr );
    }

    $image_url     = null;
    $file_uploaded = null;
    if ( $field_type === 'image' ) {
        $image_url     = $banner_url;
        $file_uploaded = '23237f_field_image_2_8ddb08a0.jpeg';
    }

    // Claves canónicas de mapeo
    $mapping_key = null;
    if ( $field_uid === 'fu_b06386a788' ) {
        $mapping_key = 'first_name';
    } elseif ( $field_uid === 'fu_82cc686e75' ) {
        $mapping_key = 'phone';
    } elseif ( $field_uid === 'fu_5332f0a5fd' ) {
        $mapping_key = 'address';
    } elseif ( $field_uid === 'fu_e9a8b55d3f' ) {
        $mapping_key = 'birth_date';
    } elseif ( $field_uid === 'fu_1f9b39a83b' ) {
        $mapping_key = 'document_id';
    }

    $field_row = [
        'form_id'            => $form_id,
        'field_uid'          => $field_uid,
        'label'              => $label,
        'description'        => $description,
        'field_type'         => $field_type,
        'options_json'       => $options_json,
        'min_value'          => null,
        'max_value'          => null,
        'allowed_extensions' => 'jpg,jpeg,png,pdf',
        'max_file_size_kb'   => 5120,
        'placeholder'        => $placeholder,
        'default_value'      => null,
        'is_required'        => $is_required,
        'multiple_select'    => 0,
        'has_other'          => 0,
        'image_url'          => $image_url,
        'file_uploaded'      => $file_uploaded,
        'file_url'           => null,
        'instructions'       => null,
        'terms_text'         => null,
        'disagreement_message' => null,
        'mapping_key'        => $mapping_key,
        'sort_order'         => $sort_order,
    ];

    $wpdb->insert( $table_fields, $field_row );
    $sort_order += 10;
}

$count_fields = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_fields} WHERE form_id = %d",
    $form_id
) );
echo "✓ Total campos guardados en BD: {$count_fields}\n";

// 4. Importar las 10 respuestas de usuarios
$responses_data = json_decode( file_get_contents( $responses_file ), true );
if ( ! is_array( $responses_data ) ) {
    die( "Error: El JSON de respuestas no contiene un arreglo.\n" );
}

echo "Importando " . count( $responses_data ) . " respuestas...\n";

foreach ( $responses_data as $sub ) {
    $submitted_at = ! empty( $sub['submitted_at'] ) ? $sub['submitted_at'] : current_time( 'mysql' );
    $ip_address   = ! empty( $sub['ip_address'] ) ? $sub['ip_address'] : '127.0.0.1';
    $user_agent   = ! empty( $sub['user_agent'] ) && $sub['user_agent'] !== 'N/A' ? $sub['user_agent'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';

    // Extraer nombre completo
    $submitted_name = '';
    if ( ! empty( $sub['data']['Nombre Completo'] ) ) {
        $submitted_name = trim( $sub['data']['Nombre Completo'] );
    } elseif ( ! empty( $sub['data_uid']['fu_b06386a788'] ) ) {
        $submitted_name = trim( $sub['data_uid']['fu_b06386a788'] );
    }

    // Extraer email si aparece en los datos
    $submitted_email = null;
    $church_info = $sub['data']['Información De la Iglesia (Dirección, Teléfono,Email)'] ?? '';
    if ( preg_match( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $church_info, $matches ) ) {
        $submitted_email = sanitize_email( $matches[0] );
    }

    // Construir data_map para data_json
    $data_map = $sub['data_uid'] ?? [];

    // Agregar fecha ISO para birthdate si existe
    $birthdate_iso = $sub['data']['Edad  (fecha)'] ?? '';
    if ( $birthdate_iso ) {
        $data_map['fu_e9a8b55d3f_iso_date'] = $birthdate_iso;
    }

    $data_json = wp_json_encode( $data_map );

    $submission_row = [
        'form_id'         => $form_id,
        'wp_user_id'      => null,
        'submitted_name'  => $submitted_name ?: null,
        'submitted_email' => $submitted_email ?: null,
        'data_json'       => $data_json,
        'source_url'      => 'https://diserwp.test/formulario/inscripciones-hadime-raices-2027/',
        'ip_address'      => $ip_address,
        'user_agent'      => $user_agent,
        'status'          => 'received',
        'enrollment_id'   => null,
        'submitted_at'    => $submitted_at,
        'reviewed_by'     => null,
        'reviewed_at'     => null,
    ];

    $wpdb->insert( $table_submissions, $submission_row );
}

$count_submissions = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_submissions} WHERE form_id = %d",
    $form_id
) );
echo "✓ Total respuestas guardadas en BD: {$count_submissions}\n";

echo "=== MIGRACIÓN COMPLETADA CON ÉXITO ===\n";
echo "ID del Formulario: {$form_id}\n";
echo "Ver en Admin: https://diserwp.test/wp-admin/admin.php?page=aura-forms-list\n";
echo "Ver Respuestas: https://diserwp.test/wp-admin/admin.php?page=aura-forms-list&action=responses&id={$form_id}\n";
