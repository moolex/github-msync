<?php

/**
 * Github Service Hook Direct Callback
 */

// Depend
defined('ABSPATH') || require ('../../../wp-load.php');
function_exists('wp_create_category') || require (ABSPATH . 'wp-admin/includes/taxonomy.php');

// Callback
mgi_service_callback();

?>