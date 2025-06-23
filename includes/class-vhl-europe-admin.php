<?php

if (!defined('ABSPATH')) {
    exit;
}

class VHL_Europe_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_vhl_europe_clear_logs', array($this, 'clear_logs_ajax'));
        add_action('wp_ajax_vhl_europe_get_log_details', array($this, 'get_log_details_ajax'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('VHL Europe Settings', 'vhl-europe-woocommerce'),
            __('VHL Europe', 'vhl-europe-woocommerce'),
            'manage_woocommerce',
            'vhl-europe-settings',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'woocommerce',
            __('VHL Europe API Logs', 'vhl-europe-woocommerce'),
            __('VHL Europe Logs', 'vhl-europe-woocommerce'),
            'manage_woocommerce',
            'vhl-europe-logs',
            array($this, 'logs_page')
        );
    }
    
    public function admin_init() {
        register_setting('vhl_europe_settings', 'vhl_europe_options');
        
        add_settings_section(
            'vhl_europe_api_section',
            __('API Settings', 'vhl-europe-woocommerce'),
            array($this, 'api_section_callback'),
            'vhl_europe_settings'
        );
        
        add_settings_field(
            'api_bearer_token',
            __('API Bearer Token', 'vhl-europe-woocommerce'),
            array($this, 'api_bearer_token_callback'),
            'vhl_europe_settings',
            'vhl_europe_api_section'
        );
        
        add_settings_section(
            'vhl_europe_shipping_section',
            __('Shipping Settings', 'vhl-europe-woocommerce'),
            array($this, 'shipping_section_callback'),
            'vhl_europe_settings'
        );
        
        add_settings_field(
            'enable_dpd',
            __('Enable DPD', 'vhl-europe-woocommerce'),
            array($this, 'enable_dpd_callback'),
            'vhl_europe_settings',
            'vhl_europe_shipping_section'
        );
        
        add_settings_field(
            'dpd_shipping_method',
            __('Map DPD to WooCommerce Shipping Method', 'vhl-europe-woocommerce'),
            array($this, 'dpd_shipping_method_callback'),
            'vhl_europe_settings',
            'vhl_europe_shipping_section'
        );
        
        add_settings_field(
            'enable_packeta',
            __('Enable Packeta', 'vhl-europe-woocommerce'),
            array($this, 'enable_packeta_callback'),
            'vhl_europe_settings',
            'vhl_europe_shipping_section'
        );
        
        add_settings_section(
            'vhl_europe_payment_section',
            __('Payment Settings', 'vhl-europe-woocommerce'),
            array($this, 'payment_section_callback'),
            'vhl_europe_settings'
        );
        
        add_settings_field(
            'enable_cod',
            __('Enable Cash on Delivery', 'vhl-europe-woocommerce'),
            array($this, 'enable_cod_callback'),
            'vhl_europe_settings',
            'vhl_europe_payment_section'
        );
        
        add_settings_field(
            'cod_payment_method',
            __('Map COD to WooCommerce Payment Method', 'vhl-europe-woocommerce'),
            array($this, 'cod_payment_method_callback'),
            'vhl_europe_settings',
            'vhl_europe_payment_section'
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if ($this->is_packeta_available()): ?>
                <div class="notice notice-success">
                    <p><?php _e('Packeta plugin is installed and active.', 'vhl-europe-woocommerce'); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><?php _e('Packeta plugin is not installed. Install it to enable Packeta shipping option.', 'vhl-europe-woocommerce'); ?></p>
                </div>
            <?php endif; ?>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('vhl_europe_settings');
                do_settings_sections('vhl_europe_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function api_section_callback() {
        echo '<p>' . __('Configure API settings for VHL Europe integration.', 'vhl-europe-woocommerce') . '</p>';
    }
    
    public function shipping_section_callback() {
        echo '<p>' . __('Configure shipping methods mapping.', 'vhl-europe-woocommerce') . '</p>';
    }
    
    public function payment_section_callback() {
        echo '<p>' . __('Configure payment methods mapping.', 'vhl-europe-woocommerce') . '</p>';
    }
    
    public function api_bearer_token_callback() {
        $options = get_option('vhl_europe_options');
        $value = isset($options['api_bearer_token']) ? $options['api_bearer_token'] : '';
        ?>
        <input type="password" name="vhl_europe_options[api_bearer_token]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('Enter your VHL Europe API bearer token.', 'vhl-europe-woocommerce'); ?></p>
        <?php
    }
    
    public function enable_dpd_callback() {
        $options = get_option('vhl_europe_options');
        $checked = isset($options['enable_dpd']) && $options['enable_dpd'] ? 'checked' : '';
        ?>
        <input type="checkbox" name="vhl_europe_options[enable_dpd]" value="1" <?php echo $checked; ?> />
        <label><?php _e('Enable DPD shipping method', 'vhl-europe-woocommerce'); ?></label>
        <?php
    }
    
    public function dpd_shipping_method_callback() {
        $options = get_option('vhl_europe_options');
        $selected = isset($options['dpd_shipping_method']) ? $options['dpd_shipping_method'] : '';
        $shipping_methods = $this->get_available_shipping_methods();
        ?>
        <select name="vhl_europe_options[dpd_shipping_method]">
            <option value=""><?php _e('Select shipping method', 'vhl-europe-woocommerce'); ?></option>
            <?php foreach ($shipping_methods as $method_id => $method_title): ?>
                <option value="<?php echo esc_attr($method_id); ?>" <?php selected($selected, $method_id); ?>>
                    <?php echo esc_html($method_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php _e('Select which WooCommerce shipping method should be mapped to DPD.', 'vhl-europe-woocommerce'); ?></p>
        <?php
    }
    
    public function enable_packeta_callback() {
        $options = get_option('vhl_europe_options');
        $checked = isset($options['enable_packeta']) && $options['enable_packeta'] ? 'checked' : '';
        $disabled = !$this->is_packeta_available() ? 'disabled' : '';
        ?>
        <input type="checkbox" name="vhl_europe_options[enable_packeta]" value="1" <?php echo $checked; ?> <?php echo $disabled; ?> />
        <label><?php _e('Enable Packeta shipping method', 'vhl-europe-woocommerce'); ?></label>
        <?php if (!$this->is_packeta_available()): ?>
            <p class="description" style="color: #d63638;"><?php _e('Packeta plugin must be installed and active to enable this option.', 'vhl-europe-woocommerce'); ?></p>
        <?php endif; ?>
        <?php
    }
    
    public function enable_cod_callback() {
        $options = get_option('vhl_europe_options');
        $checked = isset($options['enable_cod']) && $options['enable_cod'] ? 'checked' : '';
        ?>
        <input type="checkbox" name="vhl_europe_options[enable_cod]" value="1" <?php echo $checked; ?> />
        <label><?php _e('Enable Cash on Delivery', 'vhl-europe-woocommerce'); ?></label>
        <?php
    }
    
    public function cod_payment_method_callback() {
        $options = get_option('vhl_europe_options');
        $selected = isset($options['cod_payment_method']) ? $options['cod_payment_method'] : '';
        $payment_methods = $this->get_available_payment_methods();
        ?>
        <select name="vhl_europe_options[cod_payment_method]">
            <option value=""><?php _e('Select payment method', 'vhl-europe-woocommerce'); ?></option>
            <?php foreach ($payment_methods as $method_id => $method_title): ?>
                <option value="<?php echo esc_attr($method_id); ?>" <?php selected($selected, $method_id); ?>>
                    <?php echo esc_html($method_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php _e('Select which WooCommerce payment method should be mapped to COD.', 'vhl-europe-woocommerce'); ?></p>
        <?php
    }
    
    private function get_available_shipping_methods() {
        $methods = array();
        $shipping_methods = WC()->shipping->get_shipping_methods();
        
        foreach ($shipping_methods as $method_id => $method) {
            $methods[$method_id] = $method->get_method_title();
        }
        
        return $methods;
    }
    
    private function get_available_payment_methods() {
        $methods = array();
        $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
        
        foreach ($payment_gateways as $gateway_id => $gateway) {
            $methods[$gateway_id] = $gateway->get_title();
        }
        
        return $methods;
    }
    
    private function is_packeta_available() {
        return class_exists('Packetery\Core\CoreHelper') || function_exists('packeta_init');
    }
    
    public function logs_page() {
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($current_page - 1) * $per_page;
        
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
        
        $logs = VHL_Europe_Logger::get_logs($per_page, $offset, $order_id);
        $total_logs = VHL_Europe_Logger::get_logs_count($order_id);
        $total_pages = ceil($total_logs / $per_page);
        
        ?>
        <div class="wrap">
            <h1><?php _e('VHL Europe API Logs', 'vhl-europe-woocommerce'); ?></h1>
            
            <div style="margin: 20px 0;">
                <form method="get" style="display: inline-block;">
                    <input type="hidden" name="page" value="vhl-europe-logs" />
                    <input type="number" name="order_id" placeholder="<?php _e('Order ID', 'vhl-europe-woocommerce'); ?>" value="<?php echo esc_attr($order_id); ?>" />
                    <input type="submit" class="button" value="<?php _e('Filter', 'vhl-europe-woocommerce'); ?>" />
                </form>
                
                <?php if ($order_id): ?>
                    <a href="?page=vhl-europe-logs" class="button"><?php _e('Show All', 'vhl-europe-woocommerce'); ?></a>
                <?php endif; ?>
                
                <button type="button" class="button button-secondary" id="clear-logs-btn" style="margin-left: 20px;">
                    <?php _e('Clear Old Logs (30+ days)', 'vhl-europe-woocommerce'); ?>
                </button>
            </div>
            
            <?php if (empty($logs)): ?>
                <p><?php _e('No API logs found.', 'vhl-europe-woocommerce'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'vhl-europe-woocommerce'); ?></th>
                            <th><?php _e('Order ID', 'vhl-europe-woocommerce'); ?></th>
                            <th><?php _e('Status', 'vhl-europe-woocommerce'); ?></th>
                            <th><?php _e('Response Code', 'vhl-europe-woocommerce'); ?></th>
                            <th><?php _e('Created', 'vhl-europe-woocommerce'); ?></th>
                            <th><?php _e('Actions', 'vhl-europe-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->id); ?></td>
                                <td>
                                    <?php if ($log->order_id): ?>
                                        <a href="<?php echo admin_url('post.php?post=' . $log->order_id . '&action=edit'); ?>" target="_blank">
                                            #<?php echo esc_html($log->order_id); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-<?php echo esc_attr($log->status); ?>" style="padding: 2px 8px; border-radius: 3px; font-size: 12px; color: white; background-color: <?php echo $log->status === 'success' ? '#46b450' : ($log->status === 'error' ? '#dc3232' : '#ffb900'); ?>">
                                        <?php echo esc_html(ucfirst($log->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo $log->response_code ? esc_html($log->response_code) : '-'; ?></td>
                                <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->created_at))); ?></td>
                                <td>
                                    <button type="button" class="button button-small view-log-details" data-log-id="<?php echo esc_attr($log->id); ?>">
                                        <?php _e('View Details', 'vhl-europe-woocommerce'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            $page_links = paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page,
                                'add_args' => $order_id ? array('order_id' => $order_id) : array()
                            ));
                            echo $page_links;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div id="log-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; max-width: 80%; max-height: 80%; overflow: auto;">
                <h3><?php _e('API Log Details', 'vhl-europe-woocommerce'); ?></h3>
                <div id="log-details-content"></div>
                <button type="button" id="close-modal" class="button" style="margin-top: 20px;"><?php _e('Close', 'vhl-europe-woocommerce'); ?></button>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.view-log-details').on('click', function() {
                var logId = $(this).data('log-id');
                
                $.post(ajaxurl, {
                    action: 'vhl_europe_get_log_details',
                    log_id: logId,
                    nonce: '<?php echo wp_create_nonce('vhl_europe_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#log-details-content').html(response.data);
                        $('#log-details-modal').show();
                    } else {
                        alert('<?php _e('Error loading log details', 'vhl-europe-woocommerce'); ?>');
                    }
                });
            });
            
            $('#close-modal, #log-details-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#log-details-modal').hide();
                }
            });
            
            $('#clear-logs-btn').on('click', function() {
                if (confirm('<?php _e('Are you sure you want to clear logs older than 30 days?', 'vhl-europe-woocommerce'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'vhl_europe_clear_logs',
                        nonce: '<?php echo wp_create_nonce('vhl_europe_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert(response.data);
                            location.reload();
                        } else {
                            alert('<?php _e('Error clearing logs', 'vhl-europe-woocommerce'); ?>');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    public function clear_logs_ajax() {
        check_ajax_referer('vhl_europe_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'vhl-europe-woocommerce'));
        }
        
        $deleted = VHL_Europe_Logger::delete_old_logs(30);
        
        wp_send_json_success(sprintf(__('Deleted %d old log entries.', 'vhl-europe-woocommerce'), $deleted));
    }
    
    public function get_log_details_ajax() {
        check_ajax_referer('vhl_europe_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'vhl-europe-woocommerce'));
        }
        
        $log_id = intval($_POST['log_id']);
        $log = VHL_Europe_Logger::get_log_by_id($log_id);
        
        if (!$log) {
            wp_send_json_error(__('Log not found', 'vhl-europe-woocommerce'));
        }
        
        $html = '<div style="font-family: monospace; font-size: 12px;">';
        
        $html .= '<h4>' . __('Basic Information', 'vhl-europe-woocommerce') . '</h4>';
        $html .= '<p><strong>' . __('Log ID:', 'vhl-europe-woocommerce') . '</strong> ' . esc_html($log->id) . '</p>';
        $html .= '<p><strong>' . __('Order ID:', 'vhl-europe-woocommerce') . '</strong> ' . ($log->order_id ? esc_html($log->order_id) : '-') . '</p>';
        $html .= '<p><strong>' . __('Status:', 'vhl-europe-woocommerce') . '</strong> ' . esc_html($log->status) . '</p>';
        $html .= '<p><strong>' . __('Response Code:', 'vhl-europe-woocommerce') . '</strong> ' . ($log->response_code ? esc_html($log->response_code) : '-') . '</p>';
        $html .= '<p><strong>' . __('Created:', 'vhl-europe-woocommerce') . '</strong> ' . esc_html($log->created_at) . '</p>';
        
        if ($log->error_message) {
            $html .= '<h4 style="color: #dc3232;">' . __('Error Message', 'vhl-europe-woocommerce') . '</h4>';
            $html .= '<p style="color: #dc3232; background: #ffeaea; padding: 10px; border-radius: 3px;">' . esc_html($log->error_message) . '</p>';
        }
        
        $html .= '<h4>' . __('Request Data', 'vhl-europe-woocommerce') . '</h4>';
        $html .= '<pre style="background: #f5f5f5; padding: 15px; border-radius: 3px; max-height: 300px; overflow: auto; white-space: pre-wrap;">' . esc_html($log->request_data) . '</pre>';
        
        if ($log->response_data) {
            $html .= '<h4>' . __('Response Data', 'vhl-europe-woocommerce') . '</h4>';
            $html .= '<pre style="background: #f5f5f5; padding: 15px; border-radius: 3px; max-height: 300px; overflow: auto; white-space: pre-wrap;">' . esc_html($log->response_data) . '</pre>';
        }
        
        $html .= '</div>';
        
        wp_send_json_success($html);
    }
}