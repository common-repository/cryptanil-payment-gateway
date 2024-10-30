<?php
/*
Plugin Name: Cryptanil Payment Gateway
Plugin URI: #
Description: Pay with crypto. We support BTC, ETH, USDT, USDC, SOL, TON, TRX, BUSD, BNB and many more.
Version: 1.0.0
Author: PrimeSoft LLC
Author URI: https://www.cryptanil.com
License: GPLv2 or later
 */

$pluginDomainCryptanil = 'cryptanil-payment-gateway';
$pluginDirUrlCryptanil = plugin_dir_url(__FILE__);

if( !function_exists('get_plugin_data') ){
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
$pluginDataCryptanil = get_plugin_data(__FILE__);

/**
 *
 * @param $gateways
 * @return array
 */
function CPG_WCCryptanilGateway($gateways)
{
    $gateways[] = 'CPG_WC_Cryptanil_Gateway';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'CPG_WCCryptanilGateway');

include dirname(__FILE__) . '/includes/main.php';
include dirname(__FILE__) . '/includes/redirect.php';

// WP cron

function cpg_cryptanil_cronCheckOrder()
{
    global $wpdb;
    global $pluginDomainCryptanil;
    $orders = $wpdb->get_results("
            SELECT p.*
            FROM {$wpdb->prefix}postmeta AS pm
            LEFT JOIN {$wpdb->prefix}posts AS p
            ON pm.post_id = p.ID
            WHERE p.post_type = 'shop_order'
            AND ( p.post_status = 'wc-on-hold' OR p.post_status = 'wc-pending')
            AND pm.meta_key = '_payment_method'
            AND pm.meta_value = '{$pluginDomainCryptanil}'
            ORDER BY pm.meta_value ASC, pm.post_id DESC
        ");
       
    foreach ($orders as $order) {
        $cryptoOrder = (new CPG_WC_Cryptanil_Gateway())->cpg_CryptanilGetOrderInfo($order->ID);
        error_log('$cryptoOrder: '. json_encode($cryptoOrder));
        $statuses = [
            '',
            'pending',
            'pending',
            'failed',
            'processing',
            'completed',
            'failed',
            'processing',
            'refunded',
            'failed',
            'failed',
            'failed'
        ];

        if($cryptoOrder && isset($statuses[$cryptoOrder['status']])) {
            $order = wc_get_order($order->ID);
            $order->update_status($statuses[$cryptoOrder['status']]);
            continue;
        }

        $postDateGmt = $order->post_date_gmt;
        $diffTimeMinutes=(strtotime(date("Y-m-d H:i:s"))-strtotime($postDateGmt))/60;
        if($diffTimeMinutes > 120){
            $order = wc_get_order($order->ID);
            $order->update_status('failed');
        }
    }
}


function cpg_cronSchedulesForCryptanil($schedules)
{
    if (!isset($schedules["30min"])) {
        $schedules["30min"] = array(
            'interval' => 1 * 60,
            'display' => __('Once every 30 minutes'));
    }
    return $schedules;
}

function cpg_initCryptanilPlugin()
{

    if (!wp_next_scheduled('cpg_cryptanil_cronCheckOrder')) {
        wp_schedule_event(time(), '30min', 'cpg_cryptanil_cronCheckOrder');
    }
}

add_action('cronCheckOrder', 'cpg_cryptanil_cronCheckOrder');
                
add_filter('cron_schedules', 'cpg_cronSchedulesForCryptanil');
add_action('init', 'cpg_initCryptanilPlugin');

/**
 * @param $links
 * @return array
 */
function cpg_cryptanil_gateway_setting_link($links)
{
    $links = array_merge(array(
        '<a href="' . esc_url(admin_url('/admin.php')) . '?page=wc-settings&tab=checkout&section=' . $pluginDomainCryptanil . '">' . __('Settings', $pluginDomainCryptanil) . '</a>'
    ), $links);
    return $links;
}

add_action('plugin_action_links_' . plugin_basename(__FILE__), 'cpg_cryptanil_gateway_setting_link');