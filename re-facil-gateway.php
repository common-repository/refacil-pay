<?php
/*
Plugin Name: Refácil Pay
Description: Pasarela de pagos para Woocommerce.
Version: 1.0.4
Author: Refácil
Author URI: https://www.refacil.co/
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: refacil-pay
*/

use Automattic\WooCommerce\Utilities\FeaturesUtil;

add_action('plugins_loaded', 'woocommerce_refacil_plugin', 0);

/**
 * @return void
 */
function woocommerce_refacil_plugin(): void
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    include plugin_dir_path(__FILE__) . 'class-gateway.php';
    require_once plugin_dir_path(__FILE__) . '/includes/callback-refacil.php';
    require_once plugin_dir_path(__FILE__) . '/includes/event-log-refacil.php';
    new Event_Log_Re_Facil_Table_Creator();
}
register_activation_hook(__FILE__, 'create_re_facil_table_on_activation');
add_filter('woocommerce_payment_gateways', 'add_re_facil_gateway');
add_filter('cron_schedules', 'update_pending_orders_schedule');
add_action('update_pending_orders_hook', 'update_pending_orders');
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');
add_action('woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type');

register_deactivation_hook(__FILE__, 'deactivate_refacil_cronjob');
register_uninstall_hook(__FILE__, 're_facil_uninstall_cleanup');

/**
 * @return void
 */
function create_re_facil_table_on_activation(): void
{
    create_refacil_cronjob();

}

/**
 * @return void
 */
function create_refacil_cronjob(): void
{
    if (!wp_next_scheduled('update_pending_orders_hook')) {
        wp_schedule_event(current_time('timestamp'), '10minutes', 'update_pending_orders_hook');
    }
}

/**
 * @return array
 */
function update_pending_orders_schedule(): array
{
    $schedules['10minutes'] = array(
        'interval' => 600,
        'display' => '10 minutes'
    );
    return $schedules;
}

/**
 * @return void
 */
function deactivate_refacil_cronjob(): void
{
    wp_clear_scheduled_hook('update_pending_orders_hook');
}

/**
 * @return void
 */
function update_pending_orders(): void
{
    $logger = wc_get_logger();
    $logger->info("Inicia cron órdenes pendientes ReFacilPay.");
    try {
        $pending_orders = wc_get_orders(array(
            'limit' => -1,
            'status' => 'pending',
            'date_created' => '<' . (time() - 600),
            'payment_method' => 're_facil_gateway'
        ));
        if ($pending_orders) {
            process_pending_orders($pending_orders);
        }
    } catch (Exception $e) {
        error_log('Error al actualizar órdenes pendientes: ' . $e->getMessage());
    }
}

/**
 * @param $pending_orders
 * @return void
 */
function process_pending_orders($pending_orders): void
{
    global $wpdb;
    $re_facil_pay_gateway = new Re_Facil_Gateway();
    $event_name = 'update-order-cronjob';
    $status_mapping = array(
        0 => 'failed',
        1 => 'on-hold',
        2 => 'processing',
        3 => 'failed',
        9 => 'pending',
    );
    foreach ($pending_orders as $order) {
        $order_id = $order->get_id();
        $table_name = $wpdb->prefix . 're_facil_payments';
        $table_name = sanitize_text_field($table_name);
        $order_id = sanitize_text_field($order_id);
        $query = $wpdb->prepare("select re_facil_reference from $table_name where order_id = $order_id");
        $result = $wpdb->get_row($query);
       if ($result) {
            $data = array(
                'reference' => $result->re_facil_reference,
            );
            $response = wp_remote_post(
                $re_facil_pay_gateway->get_refacil_url() . 'payment/status',
                array(
                    'body' => wp_json_encode($data),
                    'headers' => array('Content-Type' => 'application/json',
                        'Authorization' => $re_facil_pay_gateway->get_token_auth()),
                )
            );
            if (!is_wp_error($response)) {
                $response_body = wp_remote_retrieve_body($response);
                $response_data = json_decode($response_body, true);
                if (isset($response_data['data']['status'])) {
                    $status = $response_data['data']['status'];
                    $previous_state = $order->get_status();
                    if (isset($status_mapping[$status])) {
                        $new_status = $status_mapping[$status];
                        $order->update_status($new_status);
                    }
                }
            }
            $re_facil_pay_gateway->register_transaction_log('Orden número: ' . $order_id .
             ' actualizada de estado: ' . $previous_state . ' a estado: ' . $new_status, $event_name);
        }
    }
}

/**
 * @return void
 */
function re_facil_uninstall_cleanup(): void
{
    global $wpdb;
    $table_name_1 = sanitize_text_field($wpdb->prefix . 're_facil_event_logs');
    $table_name_2 = sanitize_text_field($wpdb->prefix . 're_facil_payments');
    $table_name_3 = sanitize_text_field($wpdb->prefix . 're_facil_webhook_consumption_logs');
    $table_name_4 = sanitize_text_field($wpdb->prefix . 're_facil_store_id');
    $wpdb->query("DROP TABLE IF EXISTS $table_name_1");
    $wpdb->query("DROP TABLE IF EXISTS $table_name_2");
    $wpdb->query("DROP TABLE IF EXISTS $table_name_3");
    $wpdb->query("UPDATE $table_name_4 SET token = NULL WHERE id = 1");
}

/**
 * @param $gateways
 * @return mixed
 */
function add_re_facil_gateway($gateways)
{
    $gateways[] = 'Re_Facil_Gateway';
    return $gateways;
}

/**
 * @return void
 */
function declare_cart_checkout_blocks_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__);
    }
}

/**
 * @return void
 */
function oawoo_register_order_approval_payment_method_type(): void
{
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }
    require_once plugin_dir_path(__FILE__) . 'class-block.php';
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new Re_Facil_Gateway_Blocks);
        }
    );
}
