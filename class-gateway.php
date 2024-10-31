<?php

class Re_Facil_Gateway extends WC_Payment_Gateway
{
    const GATEWAY_ID = 're_facil_gateway';
    private $token;
    private $x_transaction_token;
    private $request_body_pay;
    private $reference1;
    private $store_id;

    public function __construct()
    {
        $this->fetch_store_id_from_bd();
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();
        $this->init_hooks();
    }

    /**
     * @return void
     */
    public function init_form_fields(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 're_facil_store_id';
        $this->token = $wpdb->get_var(
            $wpdb->prepare("SELECT token FROM $table_name WHERE id = %d", 1));
        $show_register_button = is_null($this->token);
        $this->form_fields = array(
            'success_registration' => array(
                'type' => 'hidden',
                'title' => __('Su plugin esta activo.', 're-facil-payments-woo'),
            ),
        );
        if ($show_register_button) {
            $this->form_fields = array(
                'customize_button' => array(
                    'title' => __('Registrate!', 're-facil-payments-woo'),
                    'type' => 'button',
                    'custom_attributes' => array(
                        'onclick' => "var button = this; window.open('" .
                            esc_url('https://autoregistro.refacilpay.co//?comm=1&storeid=' . $this->store_id .
                                '&returnurl=' . urlencode(get_site_url() . '/wp-json/api/auth/refacil')) .
                            "', '_blank'); button.style.display = 'none'; alert('Actualiza la página si ya te registraste.');"
                    ),
                    'description' => __('Debes registrate para poder usar este plugin.', 're-facil-payments-woo'),
                    'desc_tip' => true,
                )
            );
        }
    }

    /**
     * @param $key
     * @param $data
     * @return false|string
     */
    public function generate_button_html($key, $data)
    {
        $field = $this->plugin_id . $this->id . '_' . $key;
        $defaults = array(
            'class' => 'button-secondary',
            'css' => '',
            'custom_attributes' => array(),
            'desc_tip' => false,
            'description' => '',
            'title' => '',
        );
        $data = wp_parse_args($data, $defaults);
        ob_start();
        ?>
        <tr>
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                <?php echo $this->get_tooltip_html($data); ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <button class="<?php echo esc_attr($data['class']); ?>" type="button"
                            name="<?php echo esc_attr($field); ?>" id="<?php echo esc_attr($field); ?>"
                            style="<?php echo esc_attr($data['css']); ?>"
                        <?php echo $this->get_custom_attribute_html($data); ?>>
                        <?php echo wp_kses_post($data['title']); ?>
                    </button>
                    <?php echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * @param $order_id
     * @return array|string[]
     * @throws WC_Data_Exception
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        $this->get_trx_token();
        $this->create_payment_structure($order);
        $response_transaction = $this->create_transaction($order->get_id());
        $response_status_code = $response_transaction['response_status_code'];
        if ($response_status_code === '00' && isset($response_transaction['url_return'])) {
            $order->set_payment_method_title($this->method_title);
            $order->save();
            return array(
                'result' => 'success',
                'redirect' => $response_transaction['url_return'],
            );
        }
        return array(
            'result' => 'error',
            'message' => 'Error al procesar el pago: ' . $response_status_code
        );
    }

    /**
     * @return bool
     */
    public function is_available(): bool
    {
        if ($this->token) {
            return parent::is_available();
        }
        return false;
    }

    /**
     * @return string|null
     */
    public function get_token_auth(): ?string
    {
        if ($this->token === null) {
            return null;
        }
        return $this->token;
    }

    /**
     * @return string
     */
    public function get_refacil_url(): string
    {
        return 'https://pay-api.refacil.co/';
    }

    /**
     * @param $request_body
     * @param $event_name
     * @param null $response_code
     * @param null $response_data
     * @return int
     */
    public function register_transaction_log($request_body, $event_name, $response_code = null,
                                             $response_data = null): int
    {
        global $wpdb;
        $response_data_json = json_encode($response_data);
        $table_name = $wpdb->prefix . 're_facil_event_logs';
        $wpdb->insert(
            $table_name,
            array(
                'event_name' => $event_name,
                'request_body' => $request_body,
                'response_code' => $response_code,
                'response_data' => $response_data_json
            ),
            array('%s', '%s')
        );
        return $wpdb->insert_id;
    }

    /**
     * @param $controllers
     * @return array
     */
    public function add_custom_api($controllers): array
    {
        $controllers['wc/v3']['custom'] = 'WC_REST_Custom_Controller';
        return $controllers;
    }

    /**
     * @return void
     */
    private function setup_properties(): void
    {
        $this->id = self::GATEWAY_ID;
        $this->method_title = __('ReFacilPay', 're-facil-gateway');
        $this->method_description = __('Accept payments through Refacil', 're-facil-gateway');
        $this->has_fields = false;
        $this->title = 'REFÁCIL PAY';
        $this->icon = plugin_dir_url(__FILE__) . 'assets/images/refacil_logo.png';
        $this->description = 'Paga con  <b>Transfiya,PSE, Nequi, Daviplata, Bancolombia, TPAGA y Efectivo,' .
            ' </b> Refacil Pay un <b>centralizador de pagos</b> con múltiples <b>soluciones.</b>';

    }

    /**
     * @return void
     */
    private function init_hooks(): void
    {
        if (self::GATEWAY_ID === $this->id) {
            add_action('woocommerce_update_options_payment_gateways_' .
                $this->id, array($this, 'process_admin_options'));
            add_filter('woocommerce_rest_api_get_rest_namespaces', array($this, 'add_custom_api'));
        }
    }

    /**
     * @param $order
     * @return void
     */
    private function create_payment_structure($order): void
    {
        $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $email = $order->get_billing_email();
        $cell_phone = $order->get_billing_phone();
        $this->request_body_pay = ([
            'expiresIn' => 0,
            'amount' => WC()->cart->get_total(null),
            'brandId' => 62,
            //todo asociar urls de reales
            'webhookUrl' => get_site_url() . '/wp-json/api/payments/refacil',
            //'webhookUrl' => 'googole.com' . '/wp-json/api/payments/refacil',
            //'returnUrl' => 'google.com/return',
            'returnUrl' => $this->get_return_url($order),
            'showSummary' => true,
            'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'cell_phone' => $order->get_billing_phone(),
            'reference1' => null,
            'reference2' => [
                "Label" => [
                    "Name" => $name,
                    "Email" => base64_encode($email),
                    "CellPhone" => base64_encode($cell_phone)
                ],
                "Commerce" => [
                    "origin" => "1",
                    "storeId" => $this->store_id
                ]
            ]
        ]);
    }

    /**
     * @return void
     */
    private function get_trx_token(): void
    {
        $data = array(
            'service' => '/cash-in/generate/payment-link/token'
        );
        $response = wp_remote_post(
            $this->get_refacil_url() . 'trx-token/generate',
            array(
                'body' => wp_json_encode($data),
                'headers' => array('Content-Type' => 'application/json',
                    'Authorization' => $this->token),
            )
        );
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            $this->x_transaction_token = $response_data['data']['token'];
        }
    }

    /**
     * @param $order_id
     * @return array
     */
    private function create_transaction($order_id): array
    {
        $this->reference1 = time() . str_pad($order_id, 10, '0', STR_PAD_LEFT);
        $this->request_body_pay['reference1'] = $this->reference1;
        $authorizationHeader = "{'bearer'} {$this->token}";
        $response = wp_remote_post(
            $this->get_refacil_url() . 'cash-in/generate/payment-link/token',
            array(
                'body' => json_encode($this->request_body_pay),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => $authorizationHeader,
                    'x-transaction-token' => $this->x_transaction_token,
                ],
            )
        );
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        $response_code = wp_remote_retrieve_response_code($response);
        $response_status_code = $response_data['statusCode'] ?? null;
        $url_return = null;
        if ($response_code >= 200 && $response_code < 300 && $response_status_code === '00') {
            $url_return = $response_data['data']['url'];
            $this->add_payment_record($order_id, $response_data['data']['reference'],
                $this->reference1, $response_data['data']['resourceId'],
                $response_data['data']['status']);
        } elseif ($response_code === 401) {
            $this->delete_invalid_token();
            $this->register_transaction_log(json_encode($this->request_body_pay),
                'create_transaction_delete_invalid_token',
                $response_code, $response_data['data'] ?? $response_data['message']);

        } else {
            $this->register_transaction_log(json_encode($this->request_body_pay), 'create_transaction',
                $response_code, $response_data['data'] ?? $response_data['message']);
        }
        return array(
            'response_status_code' => $response_status_code,
            'url_return' => $url_return);
    }

    /**
     * @param $order_id
     * @param $re_facil_reference
     * @param $reference1
     * @param $re_facil_resource_id
     * @param $re_facil_status
     * @return void
     */
    private function add_payment_record($order_id, $re_facil_reference, $reference1, $re_facil_resource_id,
                                        $re_facil_status): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 're_facil_payments';
        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                're_facil_reference' => $re_facil_reference,
                'reference1' => $reference1,
                're_facil_resource_id' => $re_facil_resource_id,
                're_facil_status' => $re_facil_status
            )
        );
    }

    /**
     * @return void
     */
    private function fetch_store_id_from_bd(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 're_facil_store_id';
        $this->store_id = $wpdb->get_var("SELECT storeId FROM $table_name");
    }

    /**
     * @return void
     */
    private function delete_invalid_token(): void
    {
        global $wpdb;
        $table_name = sanitize_text_field($wpdb->prefix . 're_facil_store_id');
        $wpdb->query("UPDATE $table_name SET token = NULL WHERE id = 1");
    }
}
