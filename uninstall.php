<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('vhl_europe_options');

global $wpdb;

$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_vhl_europe_processed', '_vhl_europe_response')");

if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
    $wpdb->query("DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key IN ('_vhl_europe_processed', '_vhl_europe_response')");
}

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}vhl_europe_api_logs");