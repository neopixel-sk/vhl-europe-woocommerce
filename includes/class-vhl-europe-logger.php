<?php

if (!defined('ABSPATH')) {
    exit;
}

class VHL_Europe_Logger {
    
    const LOG_TABLE = 'vhl_europe_api_logs';
    
    public static function init() {
        add_action('init', array(__CLASS__, 'create_table'));
    }
    
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) DEFAULT NULL,
            request_data longtext NOT NULL,
            response_data longtext DEFAULT NULL,
            response_code int(11) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public static function log_api_request($order_id, $request_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'request_data' => json_encode($request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function log_api_response($log_id, $response_data, $response_code, $error_message = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        $status = $response_code === 200 ? 'success' : 'error';
        
        $wpdb->update(
            $table_name,
            array(
                'response_data' => is_array($response_data) ? json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $response_data,
                'response_code' => $response_code,
                'status' => $status,
                'error_message' => $error_message
            ),
            array('id' => $log_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
    }
    
    public static function get_logs($limit = 50, $offset = 0, $order_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        $where_clause = '';
        $params = array();
        
        if ($order_id) {
            $where_clause = ' WHERE order_id = %d';
            $params[] = $order_id;
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $sql = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    public static function get_logs_count($order_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        $where_clause = '';
        $params = array();
        
        if ($order_id) {
            $where_clause = ' WHERE order_id = %d';
            $params[] = $order_id;
        }
        
        $sql = "SELECT COUNT(*) FROM $table_name $where_clause";
        
        if ($params) {
            return $wpdb->get_var($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_var($sql);
        }
    }
    
    public static function delete_old_logs($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $date
        ));
    }
    
    public static function get_log_by_id($log_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $log_id
        ));
    }
}