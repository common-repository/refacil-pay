<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Re_Facil_Gateway_Blocks extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name = 're_facil_gateway';

    /**
     * @return void
     */
    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_re_facil_gateway_settings', []);
        $this->gateway = new Re_Facil_Gateway();
    }

    /**
     * @return bool
     */
    public function is_active(): bool
    {
        return $this->gateway->is_available();
    }

    /**
     * @return string[]
     */
    public function get_payment_method_script_handles(): array
    {
        wp_register_script(
            're_facil_gateway-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('re_facil_gateway-blocks-integration');
        }
        return ['re_facil_gateway-blocks-integration'];
    }

    /**
     * @return array
     */
    public function get_payment_method_data(): array
    {
        return [
            'title' => $this->gateway->title
        ];
    }
}
