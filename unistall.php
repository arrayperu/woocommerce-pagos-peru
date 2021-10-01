<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit();

global $wpdb;

define('PAGO_MOVILES_PERU_DB_TABLE', $wpdb->prefix . 'pago_moviles_peru');

$wpdb->query("DROP TABLE IF EXISTS " . PAGO_MOVILES_PERU_DB_TABLE);
delete_option("peru_payments_wc_version");
