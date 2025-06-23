<?php

if (!defined('ABSPATH')) {
    exit;
}

class VHL_Europe_API {
    
    const API_URL = 'https://test-mage.pack.vhleurope.com/rest/all/V2/vhl-fulfillment/create-order-simple';
    const DPD_SHIPPING_METHOD_ID = 'vhl_dpd_sk';
    const PACKETA_SHIPPING_METHOD_ID = 'vhl_packeta_sk';
    
    private $api_token;
    
    public function __construct() {
        $options = get_option('vhl_europe_options');
        $this->api_token = isset($options['api_bearer_token']) ? $options['api_bearer_token'] : '';
    }
    
    public function create_order($order_data, $order_id = null) {
        if (empty($this->api_token)) {
            return new WP_Error('no_api_token', __('API token is not configured.', 'vhl-europe-woocommerce'));
        }
        
        $log_id = VHL_Europe_Logger::log_api_request($order_id, $order_data);
        
        $response = wp_remote_post(self::API_URL, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($order_data),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            if ($log_id) {
                VHL_Europe_Logger::log_api_response($log_id, $response->get_error_message(), 0, $response->get_error_message());
            }
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_message = sprintf(__('API returned error: %s', 'vhl-europe-woocommerce'), $response_body);
            if ($log_id) {
                VHL_Europe_Logger::log_api_response($log_id, $response_body, $response_code, $error_message);
            }
            return new WP_Error('api_error', $error_message);
        }
        
        $decoded_response = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = __('Invalid JSON response from API.', 'vhl-europe-woocommerce');
            if ($log_id) {
                VHL_Europe_Logger::log_api_response($log_id, $response_body, $response_code, $error_message);
            }
            return new WP_Error('json_error', $error_message);
        }
        
        if ($log_id) {
            VHL_Europe_Logger::log_api_response($log_id, $decoded_response, $response_code);
        }
        
        return $decoded_response;
    }
    
    public function prepare_order_data($order) {
        if (!$order instanceof WC_Order) {
            return new WP_Error('invalid_order', __('Invalid order object.', 'vhl-europe-woocommerce'));
        }
        
        $options = get_option('vhl_europe_options');
        $billing_address = $order->get_address('billing');
        $shipping_address = $order->get_address('shipping');
        
        $address = !empty($shipping_address['address_1']) ? $shipping_address : $billing_address;
        
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $ean = $product ? $product->get_sku() : '';
            
            $items[] = array(
                'ean' => $ean,
                'qty' => (int) $item->get_quantity()
            );
        }
        
        $street_parts = $this->parse_street_address($address['address_1']);
        
        $order_data = array(
            'items' => $items,
            'firstname' => $address['first_name'],
            'lastname' => $address['last_name'],
            'companyName' => $address['company'],
            'countryCode' => $address['country'],
            'city' => $address['city'],
            'street' => $street_parts['street'],
            'streetNumber' => $street_parts['number'],
            'zipCode' => $address['postcode'],
            'phone' => $billing_address['phone'],
            'email' => $billing_address['email'],
            'parcels' => 1,
            'variableSymbol' => (string) $order->get_order_number(),
            'shippingMethod' => $this->get_shipping_method_id($order),
            'county' => $address['state'],
            'insuranceAmount' => (float) $order->get_total(),
            'codValue' => $this->get_cod_value($order),
            'note' => $order->get_customer_note(),
            'deliveryPointId' => $this->get_packeta_delivery_point($order)
        );
        
        return $order_data;
    }
    
    private function parse_street_address($address) {
        $street = $address;
        $number = '';
        
        if (preg_match('/^(.+?)\s+(\d+[a-zA-Z]?)$/', $address, $matches)) {
            $street = trim($matches[1]);
            $number = trim($matches[2]);
        }
        
        return array(
            'street' => $street,
            'number' => $number
        );
    }
    
    private function get_shipping_method_id($order) {
        $options = get_option('vhl_europe_options');
        $shipping_methods = $order->get_shipping_methods();
        
        if (empty($shipping_methods)) {
            return '';
        }
        
        $shipping_method = reset($shipping_methods);
        $method_id = $shipping_method->get_method_id();
        
        if (isset($options['enable_dpd']) && $options['enable_dpd'] && 
            isset($options['dpd_shipping_method']) && $options['dpd_shipping_method'] === $method_id) {
            return self::DPD_SHIPPING_METHOD_ID;
        }
        
        if (isset($options['enable_packeta']) && $options['enable_packeta'] && 
            $this->is_packeta_shipping_method($method_id)) {
            return self::PACKETA_SHIPPING_METHOD_ID;
        }
        
        return '';
    }
    
    private function get_cod_value($order) {
        $options = get_option('vhl_europe_options');
        
        if (!isset($options['enable_cod']) || !$options['enable_cod']) {
            return 0;
        }
        
        $payment_method = $order->get_payment_method();
        
        if (isset($options['cod_payment_method']) && $options['cod_payment_method'] === $payment_method) {
            return (float) $order->get_total();
        }
        
        return 0;
    }
    
    private function get_packeta_delivery_point($order) {
        if (!$this->is_packeta_available()) {
            return '';
        }
        
        $delivery_point_id = $order->get_meta('_packeta_point_id');
        if (!$delivery_point_id) {
            $delivery_point_id = $order->get_meta('packetery_point_id');
        }
        
        return $delivery_point_id ? (string) $delivery_point_id : '';
    }
    
    private function is_packeta_shipping_method($method_id) {
        return strpos($method_id, 'packeta') !== false || strpos($method_id, 'packetery') !== false;
    }
    
    private function is_packeta_available() {
        return class_exists('Packetery\Core\CoreHelper') || function_exists('packeta_init');
    }
}