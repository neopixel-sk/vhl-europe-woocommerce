<?php

if (!defined('ABSPATH')) {
    exit;
}

class VHL_Europe_Order_Handler {
    
    public static function process_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            error_log('VHL Europe: Invalid order ID: ' . $order_id);
            return false;
        }
        
        if ($order->get_meta('_vhl_europe_processed', true)) {
            return true;
        }
        
        if (!self::should_process_order($order)) {
            return false;
        }
        
        $api = new VHL_Europe_API();
        $order_data = $api->prepare_order_data($order);
        
        if (is_wp_error($order_data)) {
            error_log('VHL Europe: Error preparing order data: ' . $order_data->get_error_message());
            $order->add_order_note(sprintf(__('VHL Europe: Error preparing order data: %s', 'vhl-europe-woocommerce'), $order_data->get_error_message()));
            return false;
        }
        
        $response = $api->create_order($order_data, $order_id);
        
        if (is_wp_error($response)) {
            error_log('VHL Europe: API Error: ' . $response->get_error_message());
            $order->add_order_note(sprintf(__('VHL Europe: API Error: %s', 'vhl-europe-woocommerce'), $response->get_error_message()));
            return false;
        }
        
        $order->update_meta_data('_vhl_europe_processed', true);
        $order->update_meta_data('_vhl_europe_response', $response);
        $order->save();
        
        $order->add_order_note(__('Order successfully sent to VHL Europe for fulfillment.', 'vhl-europe-woocommerce'));
        
        do_action('vhl_europe_order_processed', $order_id, $response);
        
        return true;
    }
    
    private static function should_process_order($order) {
        $options = get_option('vhl_europe_options');
        
        if (empty($options['api_bearer_token'])) {
            return false;
        }
        
        $shipping_methods = $order->get_shipping_methods();
        if (empty($shipping_methods)) {
            return false;
        }
        
        $shipping_method = reset($shipping_methods);
        $method_id = $shipping_method->get_method_id();
        
        $is_dpd_enabled = isset($options['enable_dpd']) && $options['enable_dpd'] && 
                         isset($options['dpd_shipping_method']) && $options['dpd_shipping_method'] === $method_id;
        
        $is_packeta_enabled = isset($options['enable_packeta']) && $options['enable_packeta'] && 
                             self::is_packeta_shipping_method($method_id);
        
        return $is_dpd_enabled || $is_packeta_enabled;
    }
    
    private static function is_packeta_shipping_method($method_id) {
        return strpos($method_id, 'packeta') !== false || strpos($method_id, 'packetery') !== false;
    }
}