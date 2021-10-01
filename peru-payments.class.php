<?php

/**
 * Check if the call is made within WordPress
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Import File Upload Class
 */
if (!function_exists('wp_handle_upload')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}

class PeruPayments
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->version_check();
    }

    /**
     * Check Version Plugin
     */
    private function version_check()
    {
        if (version_compare(phpversion(), '5.4', '<') || version_compare(get_bloginfo('version'), '5.5', '<')) {
            die($this->php_error());
        } else {
            add_action('init', array($this, 'prepare_translation'));
            add_action('plugins_loaded', array($this, 'db_check'));
        }
    }

    /**
     * Error PHP Version
     */
    function php_error()
    {
        $message = sprintf(esc_html__('The %2$sPeru Payments for WooCommerce%3$s plugin requires %2$sPHP 5.4+%3$s and %2$sWordPress 5.5%3$s to run properly. Your current version of PHP is %2$s%1$s%3$s, and version of WP is %2$s%4$s%3$s', 'peru-payments-wc'), phpversion(), '<strong>', '</strong>', get_bloginfo('version'));
        return sprintf('<div class="notice notice-error"><p>%1$s</p></div>', wp_kses_post($message));
    }

    /**
     * Check version
     */
    function db_check()
    {
        if (get_site_option('peru_payments_wc_version') != WOOCOMMERCE_PERU_PAYMENTS_VERSION) {
            $this->prepare_database();
        }
    }

    function prepare_translation()
    {
        load_plugin_textdomain('peru-payments-wc', FALSE, WOOCOMMERCE_PERU_PAYMENTS_TEXT_DOMAIN);
    }

    /**
     * Prepare DB for New Version Plugin
     */
    function prepare_database()
    {
        $table_name = WOOCOMMERCE_PERU_PAYMENTS_DB_TABLE;

        $charset_collate = $this->wpdb->get_charset_collate();

        $query = "CREATE TABLE $table_name (
		  id int(255) unsigned NOT NULL AUTO_INCREMENT,
          `type` VARCHAR(255) NOT NULL,
		  qrcode LONGTEXT NOT NULL,
		  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  PRIMARY KEY (id)
		) $charset_collate";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($query);

        add_option('peru_payments_wc_version', WOOCOMMERCE_PERU_PAYMENTS_VERSION);
    }
}

$plugin = new PeruPayments($wpdb);
register_activation_hook(WOOCOMMERCE_PERU_PAYMENTS_URI, array($plugin, 'prepare_database'));

add_action('wp_ajax_nopriv_upload_type_capture', 'upload_type_capture');
add_action('wp_ajax_upload_type_capture', 'upload_type_capture');

function upload_type_capture()
{
    $nonce = sanitize_text_field($_POST['nonce']);

    if (!wp_verify_nonce($nonce, 'peru_payments_wc_type_nonce')) {
        die('Busted!');
    }

    $uploadedfile = $_FILES['file'];

    $allowed = array("image/jpeg", "image/gif", "image/png");
    $filetype = wp_check_filetype(basename($uploadedfile['name']), $allowed);

    if (!isset($filetype['ext'])) {
        die("Only jpg, gif and png files are allowed.");
    }

    $movefile = wp_handle_upload($uploadedfile, array('test_form' => false));

    $response = ($movefile && !isset($movefile['error'])) ? $movefile : array('error' => $movefile['error']);
    echo json_encode($response);

    die();
}
