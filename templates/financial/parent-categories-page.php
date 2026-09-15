<?php
/**
 * Template: Redirección a Categorías Padre (Ahora integrado en categories-page.php)
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */
if (!defined('ABSPATH')) { exit; }

// Las páginas ya no existen por separado; redirigir al hub con el tab activo
$target_url = admin_url('admin.php?page=aura-financial-categories&tab=parents');
?>
<script>
    window.location.replace("<?php echo esc_url_raw($target_url); ?>");
</script>
<div class="wrap">
    <p><?php _e('Redirigiendo al Hub de Categorías...', 'aura-suite'); ?></p>
</div>