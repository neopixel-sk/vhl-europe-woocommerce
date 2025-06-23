<?php
/**
 * Plugin Name: VHL Europe WooCommerce
 * Plugin URI: https://vhleurope.com
 * Description: WooCommerce integration for VHL Europe shipping services (DPD, Packeta)
 * Version: 1.0.0
 * Author: VHL Europe
 * Author URI: https://vhleurope.com
 * Text Domain: vhl-europe-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0.0
 * WC tested up to: 9.7.1
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('VHL_EUROPE_WC_VERSION', '1.0.0');
define('VHL_EUROPE_WC_PLUGIN_FILE', __FILE__);
define('VHL_EUROPE_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('VHL_EUROPE_WC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('VHL_EUROPE_WC_PLUGIN_URL', plugin_dir_url(__FILE__));

class VHL_Europe_WooCommerce {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->load_textdomain();
        $this->includes();
        $this->hooks();
        
        VHL_Europe_Logger::init();
    }
    
    private function load_textdomain() {
        load_plugin_textdomain('vhl-europe-woocommerce', false, dirname(VHL_EUROPE_WC_PLUGIN_BASENAME) . '/languages');
    }
    
    private function includes() {
        include_once VHL_EUROPE_WC_PLUGIN_PATH . 'includes/class-vhl-europe-logger.php';
        include_once VHL_EUROPE_WC_PLUGIN_PATH . 'includes/class-vhl-europe-admin.php';
        include_once VHL_EUROPE_WC_PLUGIN_PATH . 'includes/class-vhl-europe-api.php';
        include_once VHL_EUROPE_WC_PLUGIN_PATH . 'includes/class-vhl-europe-order-handler.php';
    }
    
    private function hooks() {
        add_action('woocommerce_order_status_completed', array($this, 'handle_order_completion'));
        add_action('woocommerce_order_status_processing', array($this, 'handle_order_processing'));
        add_action('before_woocommerce_init', array($this, 'declare_compatibility'));
        
        if (is_admin()) {
            VHL_Europe_Admin::get_instance();
        }
    }
    
    public function handle_order_completion($order_id) {
        VHL_Europe_Order_Handler::process_order($order_id);
    }
    
    public function handle_order_processing($order_id) {
        VHL_Europe_Order_Handler::process_order($order_id);
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('VHL Europe WooCommerce requires WooCommerce to be installed and active.', 'vhl-europe-woocommerce'); ?></p>
        </div>
        <?php
    }
    
    public static function activate() {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(VHL_EUROPE_WC_PLUGIN_BASENAME);
            wp_die(__('VHL Europe WooCommerce requires WooCommerce to be installed and active.', 'vhl-europe-woocommerce'));
        }
    }
    
    public function declare_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('orders_cache', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }
    
    public static function deactivate() {
        
    }
}

register_activation_hook(__FILE__, array('VHL_Europe_WooCommerce', 'activate'));
register_deactivation_hook(__FILE__, array('VHL_Europe_WooCommerce', 'deactivate'));

VHL_Europe_WooCommerce::get_instance();