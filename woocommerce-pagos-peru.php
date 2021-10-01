<?php

/**
 * Plugin Name: Perú Payments for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/woocommerce-peru-payments
 * Description: Agrega Yape, Plin, Tunki y Lukita como formas de pago en tu tienda WooCommerce. Recibe pagos sin comisiones desde Yape, Plin, Tunki y Lukita.
 * Version: 1.0
 * Author: Array Peru
 * Author URI: https://arrayperu.com/
 * Text Domain: woocommerce-peru-payments
 * Domain Path: /languages
 **/

/**
 * Include WP Plugin Functions
 */
include_once(ABSPATH . 'wp-admin/includes/plugin.php');

/**
 * Check if woocommerce plugin is active
 */
if (!is_plugin_active('woocommerce/woocommerce.php')) {
    echo "WooCommerce not found or disabled";
    exit;
}

/**
 * Check if the call is made within WordPress
 */
if (!defined('ABSPATH') && !in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit;
}

/**
 * Call $wpdb to use it in this plugin
 */
global $wpdb;

define('WOOCOMMERCE_PERU_PAYMENTS_URI', __FILE__);
define('WOOCOMMERCE_PERU_PAYMENTS_PATH', plugin_dir_path(__FILE__));
define('WOOCOMMERCE_PERU_PAYMENTS_VERSION', '1.0');
define('WOOCOMMERCE_PERU_PAYMENTS_ASSETS', plugin_dir_url(__FILE__) . 'assets/');
define('WOOCOMMERCE_PERU_PAYMENTS_TEXT_DOMAIN', basename(dirname(__FILE__)) . '/languages/');
define('WOOCOMMERCE_PERU_PAYMENTS_DB_TABLE', $wpdb->prefix . 'woocommerce_peru_payments');

define('WOOCOMMERCE_PERU_PAYMENTS_PLIN_CODE', 'plin');
define('WOOCOMMERCE_PERU_PAYMENTS_TUNKI_CODE', 'tunki');
define('WOOCOMMERCE_PERU_PAYMENTS_YAPE_CODE', 'yape');
define('WOOCOMMERCE_PERU_PAYMENTS_LUKITA_CODE', 'lukita');

/**
 * Import Class Init
 */
require_once WOOCOMMERCE_PERU_PAYMENTS_PATH . 'peru-payments.class.php';

add_filter('woocommerce_payment_gateways', 'peru_payments_wc_add_gateway_class');
function peru_payments_wc_add_gateway_class($gateways)
{
    $gateways[] = 'PeruPaymentsWC_PLIN';
    $gateways[] = 'PeruPaymentsWC_TUNKI';
    $gateways[] = 'PeruPaymentsWC_YAPE';
    $gateways[] = 'PeruPaymentsWC_LUKITA';
    return $gateways;
}

add_action('plugins_loaded', 'peru_payments_wc_init_gateway_class');

function peru_payments_wc_init_gateway_class()
{
    class PeruPaymentsWC_Gateway extends WC_Payment_Gateway
    {
        public $type_qr;

        public function __construct($id, $icon, $method_title, $method_description, $type_qr)
        {
            $this->id = $id;
            $this->icon = apply_filters('woocommerce_gateway_icon', WOOCOMMERCE_PERU_PAYMENTS_ASSETS . $icon);
            $this->has_fields = true;
            $this->domain = 'peru-payments-wc';
            $this->method_title = $method_title;
            $this->method_description = $method_description;

            $this->type_qr = $type_qr;

            $this->supports = array(
                'products'
            );

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->info = $this->get_option('info');
            $this->name = $this->get_option('name');
            $this->phone = $this->get_option('phone');
            $this->instructions = $this->get_option('instructions');
            $this->needcapture = $this->get_option('needcapture');
            $this->info_detail = $this->get_option('info_detail');
            $this->payed_message = $this->get_option('payed_message');
            $this->qrcode = $this->get_qr_code();

            add_action('woocommerce_before_order_notes', array($this, 'custom_checkout_field'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'show_type_field_order'), 10, 1);
            add_action('woocommerce_email_after_order_table', array($this, 'show_type_field_emails'), 20, 4);
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            add_action('woocommerce_after_order_details', array($this, 'init_order_details'));
        }

        public function init_order_details($order)
        {
            global $wpdb;

            if ($order->payment_method == $this->id && !empty($order_metadata = $order->get_meta_data())) {
                $key_section = 'woocommerce-order-pay';

                $data = $wpdb->get_row("SELECT qrcode FROM " . WOOCOMMERCE_PERU_PAYMENTS_DB_TABLE . " WHERE type = '" . $this->type_qr . "' LIMIT 1");

                $payed = null;

                foreach ($order_metadata as $metadata) {
                    $meta = $metadata->get_data();
                    if ($meta['key'] == '_' . $this->type_qr . '_img') {
                        $payed = $meta['value'];
                    }
                }

                echo '<section class="woocommerce-order-details ' . $key_section . '">';
                echo '<h2 class="' . $key_section . '__title">Detalle de pago con ' . $this->method_title . '</h2>';
                echo '<table class ="woocommerce-table woocommerce-table--order-pay shop_table order_pay">';
                echo '<tbody>';

                if (!empty($payed)) {
                    echo '<tr>
                        <td>
                            <strong>Método de pago</strong>
                        </td>
                        <td>
                            <strong>' . $this->method_title . '</strong>
                        </td>
                    </tr>';

                    echo '<tr>
                        <td>
                            <strong>QR</strong>
                        </td>
                        <td>
                            <img src="' . $data->qrcode . '" style="width:100%; max-width: 12rem;" />
                        </td>
                    </tr>';

                    echo '<tr>
                        <td>
                            <strong>Títular</strong>
                        </td>
                        <td>' . $this->name . '</td>
                    </tr>';

                    echo '<tr>
                        <td>
                            <strong>Celular</strong>
                        </td>
                        <td>' . $this->phone . '</td>
                    </tr>';

                    echo '<tr>
                        <td>
                            <strong>Captura subida</strong>
                        </td>
                        <td>
                            <img src="' . $payed . '" style="width:100%; max-width: 500px;" />
                        </td>
                    </tr>';

                    echo '<tr>
                        <td colspan="2" style="text-align:center; color: red;">
                            <strong>' . $this->payed_message . '</strong>
                        </td>
                    </tr>';
                } else {
                    /* Start Payed successful */
                    echo '<tr>
                        <td style="text-align:center;">
                            <strong>' . $this->method_title . '</strong><br />
                            <img src="' . $this->icon . '" />';

                    echo '<div style="padding: 15px 0; font-weight: bold; font-size: 1.3rem; line-height: initial;">' . $this->info . '</div>';

                    if (!empty($data->qrcode)) {
                        echo '<div>
                            <img src="' . $data->qrcode . '" style="width:100%; max-width: 12rem;" />
                        </div>';
                    }

                    echo '<strong>Títular</strong><br />
                        <strong>' . $this->name . '</strong>';

                    echo '<div style="font-size: 1.5rem; padding-top:10px;">
                            <strong>' . $this->phone . '</strong>
                        </div>';

                    echo '<div style="padding-top: 10px;">
                            <strong>Total a pagar</strong><br />
                            <span style="font-size: 2rem; line-height: initial;">' . get_woocommerce_currency_symbol($order->currency) . ' ' . $order->total . '</span>
                        </div>';

                    if (!empty($this->info_detail)) {
                        echo '<div style="padding-top: 10px;">
                                <strong>Instrucciones</strong><br />
                                ' . nl2br($this->info_detail) . '
                            </div>';
                    }
                    echo '</tr>';
                    /* End */
                }


                echo '</tbody>';
                echo '</table>';
                echo '</section>';
            }
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'title' => array(
                    'title'       => __('Title', $this->domain),
                    'type'        => 'text',
                    'description' => __('This is the title of the payment method on the customer checkout', $this->domain),
                    'default'     => $this->method_title,
                    'desc_tip'    => false,
                ),
                'name' => array(
                    'title'       => __('Name', $this->domain),
                    'type'        => 'text',
                    'description' => sprintf(
                        esc_html__('Name of the %1$s account holder', $this->domain),
                        $this->method_title
                    ),
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'phone' => array(
                    'title'       => __('Phone', $this->domain),
                    'type'        => 'text',
                    'description' => sprintf(
                        esc_html__('This is the phone number associated to %1$s account', $this->domain),
                        $this->method_title
                    ),
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'instructions' => array(
                    'title'       => __('Instructions', $this->domain),
                    'type'        => 'textarea',
                    'description' => __('A short instruction to show at the bottom of the QR code', $this->domain),
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'info' => array(
                    'title'       => __('Info', $this->domain),
                    'type'        => 'text',
                    'description' => __('A short instruction to show in order detail', $this->domain),
                    'default'     => 'Escanea nuestro código QR o añade nuestro número celular a tus contactos y paga con ' . $this->method_title . '.',
                    'desc_tip'    => false,
                ),
                'info_detail' => array(
                    'title'       => __('Info Detail', $this->domain),
                    'type'        => 'textarea',
                    'description' => __('A short instruction to show in order detail', $this->domain),
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'payed_message' => array(
                    'title'       => __('Message when making the payment', $this->domain),
                    'type'        => 'text',
                    'description' => '',
                    'default'     => '',
                    'desc_tip'    => false,
                ),
                'qrcode' => array(
                    'title'       => __('Upload QR Code', $this->domain),
                    'type'        => 'file',
                    'description' => __('Upload the QR of your business here (must be in .jpg or .png image)', $this->domain),
                    'accept'     => 'image/*',
                ),
                'needcapture' => array(
                    'title'       => __('Capture required', $this->domain),
                    'label'       => __('Transaction screenshot required', $this->domain),
                    'type'        => 'checkbox',
                    'description' => __('Check this if you want the screenshot required on the checkout process', $this->domain),
                ),
                'description' => array(
                    'type'        => 'title',
                    'description' => '<b>' . __('Only upload the QR image, minimun dimension 200 x 200', $this->domain) . '</b>',
                ),
            );
        }

        public function custom_checkout_field($checkout)
        {
            echo wp_kses('<div id="' . $this->type_qr . '_img_field"><input type="hidden" name="' . $this->type_qr . '_img" id="' . $this->type_qr . '_img"></div>', array(
                'div' => array(
                    'id' => array(),
                ),
                'input' => array(
                    'type' => array(),
                    'name' => array(),
                    'id' => array(),
                    'value' => array(),
                ),
            ));
        }

        public function process_admin_options()
        {
            global $wpdb;
            parent::process_admin_options();

            $qrcode = $_FILES['woocommerce_peru_payments_wc_' . $this->type_qr . '_qrcode'];

            if (empty($_POST['woocommerce_peru_payments_wc_' . $this->type_qr . '_title'])) {
                WC_Admin_Settings::add_error(__('Error: Please fill the payment title', $this->domain));
                return false;
            }

            if (empty($_POST['woocommerce_peru_payments_wc_' . $this->type_qr . '_instructions'])) {
                WC_Admin_Settings::add_error(__('Error: Please fill the payment instructions', $this->domain));
                return false;
            }

            if (empty($_POST['woocommerce_peru_payments_wc_' . $this->type_qr . '_phone'])) {
                WC_Admin_Settings::add_error(__('Error: Please fill the payment phone', $this->domain));
                return false;
            }

            if (empty($_FILES) || empty($qrcode['name'])) {
                $data = $wpdb->get_row("SELECT qrcode FROM " . WOOCOMMERCE_PERU_PAYMENTS_DB_TABLE . " WHERE type = " . $this->type_qr . " LIMIT 1");

                if ($data->qrcode) {
                    WC_Admin_Settings::add_message(__('No QR code submitted, the previous one will be used', $this->domain));
                } else {
                    WC_Admin_Settings::add_error(__('Error: Please add some QR code image', $this->domain));
                }

                return false;
            }

            $data = file_get_contents($qrcode['tmp_name']);
            $base64 = 'data:' . $qrcode['type'] . ';base64,' . base64_encode($data);

            $wpdb->delete(
                WOOCOMMERCE_PERU_PAYMENTS_DB_TABLE,
                array('type' => $this->type_qr)
            );

            $wpdb->insert(
                WOOCOMMERCE_PERU_PAYMENTS_DB_TABLE,
                array('type' => $this->type_qr, 'qrcode' => $base64)
            );
        }

        public function show_type_field_order($order)
        {
            $order_id = $order->get_id();
            if (get_post_meta($order_id, '_' . $this->type_qr . '_img', true)) {
                $this->payment_scripts(true);

                echo sprintf(wp_kses('<div id="' . $this->type_qr . 'Modal" class="modal">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <p><img src="%1$s" alt="' . $this->type_qr . '_capture"/></p>
                    </div>
                </div>', array(
                    'div' => array(
                        'id' => array(),
                        'class' => array(),
                    ),
                    'span' => array(
                        'class' => array(),
                    ),
                    'p' => array(),
                    'img' => array(
                        'src' => array(),
                        'alt' => array(),
                    ),
                )), get_post_meta($order_id, '_' . $this->type_qr . '_img', true));

                echo sprintf(
                    esc_html__('%1$s%5$s%8$s Capture:%6$s%7$s%3$sview image%4$s %2$s', 'peru-payments-wc'),
                    '<p>',
                    '</p>',
                    '<a href="#!" id="' . $this->type_qr . 'Btn">',
                    '</a>',
                    '<strong>',
                    '</strong>',
                    '<br/>',
                    $this->method_title
                );
            }
        }

        public function show_type_field_emails($order, $sent_to_admin, $plain_text, $email)
        {
            if (get_post_meta($order->get_id(), '_' . $this->type_qr . '_img', true)) {
                $href = '<a target="_blank" href="' . get_post_meta($order->get_id(), '_' . $this->type_qr . '_img', true) . '">';
                $message = sprintf(
                    esc_html__('%1$s%5$s%7$s Capture:%6$s%7$s%3$sview image%4$s %2$s', 'peru-payments-wc'),
                    '<p>',
                    '</p>',
                    $href,
                    '</a>',
                    '<strong>',
                    '</strong>',
                    '<br/>',
                    $this->method_title
                );
                echo $message;
            }
        }

        public function payment_scripts($force = false)
        {
            if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !$force) {
                return;
            }

            if ('no' === $this->enabled) {
                return;
            }

            wp_register_style('peru_payments_wc_css', WOOCOMMERCE_PERU_PAYMENTS_ASSETS . 'style.css');
            wp_enqueue_style('peru_payments_wc_css');

            wp_register_script('peru_payments_wc_js', WOOCOMMERCE_PERU_PAYMENTS_ASSETS . 'script.js');
            wp_enqueue_script('peru_payments_wc_js');

            wp_localize_script('peru_payments_wc_js', 'ajax_var', array(
                'url'    => admin_url('admin-ajax.php'),
                'nonce'  => wp_create_nonce('peru_payments_wc_type_nonce'),
                'action' => 'upload_type_capture'
            ));
        }

        public function get_qr_code()
        {
            global $wpdb;

            $data = $wpdb->get_row("SELECT qrcode FROM " . WOOCOMMERCE_PERU_PAYMENTS_DB_TABLE . " WHERE type = '" . $this->type_qr . "' LIMIT 1");

            return (!empty($data->qrcode) ? $data->qrcode : '');
        }

        public function payment_fields()
        {
            global $wpdb;

            $data = $wpdb->get_row("SELECT qrcode FROM " . WOOCOMMERCE_PERU_PAYMENTS_DB_TABLE . " WHERE type = '" . $this->type_qr . "' LIMIT 1");

            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form">';

            do_action('woocommerce_credit_card_form_start', $this->id);
            echo sprintf(
                wp_kses('<div class="peru_payments_wc_detail">
                <div class="img-qr"><img class="qrcode-image" src="%1$s" alt="" style="float: inherit; width: 10rem; height: 10rem; max-height: inherit; margin: 0px auto 20px auto;"></div>
                %6$s
                %5$s
                <div class="text-bold">%3$s</div>
                <div class="upload_qr_input">
                <label>' . sprintf(
                    esc_html__('Upload your payment capture %1$s', $this->domain),
                    $this->method_title
                ) . ' %4$s</label>
                <div><input id="' . $this->id . '_trf_image" name="' . $this->id . '_trf_image" type="file" onchange="prepareImage(this, \'' . $this->type_qr . '\')" /></div>
                </div>
                </div>', array(
                    'div' => array(
                        'class' => array(),
                    ),
                    'br' => array(),
                    'a' => array(),
                    'img' => array(
                        'src' => array(),
                        'alt' => array(),
                        'style' => array(),
                        'class' => array(),
                    ),
                    'span' => array(
                        'class' => array(),
                    ),
                    'input' => array(
                        'id' => array(),
                        'name' => array(),
                        'type' => array(),
                        'onchange' => array(),
                        'value' => array(),
                    ),
                    'label' => array(),
                )),
                (!empty($data->qrcode) ? $data->qrcode : ''),
                $this->phone,
                nl2br($this->instructions),
                $this->needcapture == 'yes' ? '<span class="required">*</span>' : '',
                (!empty($this->name) ? '<div class="text-bold name-qr"><b>Títular.</b> ' . $this->name . '</div>' : ''),
                (!empty($this->phone) ? '<div class="text-bold phone-number"><b>Cel.</b> ' . $this->phone . '</div>' : '')
            );

            do_action('woocommerce_credit_card_form_end', $this->id);
            echo '</fieldset>';
        }

        public function process_payment($order_id)
        {
            global $woocommerce;

            $order = wc_get_order($order_id);

            $order->payment_complete();
            $order->reduce_order_stock();

            if ($_POST['payment_method'] == $this->id) {
                $order->update_status('on-hold');

                $order_note = __('Thanks for your order, we will shortly validate it', $this->domain);
                $order->add_order_note($order_note, true);

                update_post_meta($order_id, '_' . $this->type_qr . '_img', esc_url($_POST[$this->type_qr . '_img']));
            }

            $woocommerce->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        public function validate_fields()
        {
            if ($this->needcapture == 'yes' && empty($_POST[$this->type_qr . '_img']) && $_POST['payment_method'] == $this->id) {
                wc_add_notice(sprintf(
                    esc_html__('You must upload the %1$s transaction capture', $this->domain),
                    $this->method_title
                ), 'error');
                return false;
            }
            return true;
        }

        private function sanitize(string $data)
        {
            return strip_tags(stripslashes(sanitize_text_field($data)));
        }

        private function convertImage($originalImage, $outputImage, $quality)
        {
            $exploded = explode('.', $originalImage);
            $ext = $exploded[count($exploded) - 1];

            if (preg_match('/jpg|jpeg|jfif/i', $ext))
                $imageTmp = imagecreatefromjpeg($originalImage);
            else if (preg_match('/png/i', $ext))
                $imageTmp = imagecreatefrompng($originalImage);
            else if (preg_match('/gif/i', $ext))
                $imageTmp = imagecreatefromgif($originalImage);
            else if (preg_match('/bmp/i', $ext))
                $imageTmp = imagecreatefrombmp($originalImage);
            else
                return 0;

            imagejpeg($imageTmp, $outputImage, $quality);
            imagedestroy($imageTmp);

            return 1;
        }
    }

    class PeruPaymentsWC_PLIN extends PeruPaymentsWC_Gateway
    {
        public function __construct()
        {
            parent::__construct('peru_payments_wc_' . WOOCOMMERCE_PERU_PAYMENTS_PLIN_CODE, 'icon_plin.png', 'PLIN', 'Haz que tus clientes paguen con PLIN', WOOCOMMERCE_PERU_PAYMENTS_PLIN_CODE);
        }
    }

    class PeruPaymentsWC_TUNKI extends PeruPaymentsWC_Gateway
    {
        public function __construct()
        {
            parent::__construct('peru_payments_wc_' . WOOCOMMERCE_PERU_PAYMENTS_TUNKI_CODE, 'icon_tunki.png', 'TUNKI', 'Haz que tus clientes paguen con TUNKI', WOOCOMMERCE_PERU_PAYMENTS_TUNKI_CODE);
        }
    }

    class PeruPaymentsWC_YAPE extends PeruPaymentsWC_Gateway
    {
        public function __construct()
        {
            parent::__construct('peru_payments_wc_' . WOOCOMMERCE_PERU_PAYMENTS_YAPE_CODE, 'icon_yape.png', 'YAPE', 'Haz que tus clientes paguen con YAPE', WOOCOMMERCE_PERU_PAYMENTS_YAPE_CODE);
        }
    }

    class PeruPaymentsWC_LUKITA extends PeruPaymentsWC_Gateway
    {
        public function __construct()
        {
            parent::__construct('peru_payments_wc_' . WOOCOMMERCE_PERU_PAYMENTS_LUKITA_CODE, 'icon_lukita.png', 'LUKITA', 'Haz que tus clientes paguen con LUKITA', WOOCOMMERCE_PERU_PAYMENTS_LUKITA_CODE);
        }
    }
}
