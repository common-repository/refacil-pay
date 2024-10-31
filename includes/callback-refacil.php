<?php

class WC_REST_Custom_Controller
{
    protected string $namespace_payments = 'api/payments';
    protected string $namespace_auth = 'api/auth';
    protected string $rest_base = 'refacil';

    /**
     * @param WP_REST_Request $data
     * @return WP_REST_Response
     */
    public function process_payment(WP_REST_Request $data): WP_REST_Response
    {
        if (!$this->validate_request_params($data)) {
            return new WP_REST_Response(['error' => 'Parámetros inválidos'], 422);
        }

        $has_key = 'e58a219892b0795a629b84a1279ea4702581c0cacfb1b2432327a080fad7ef2913a8cfe30a8b8b13d1d03bd527c311f32b6d32487d9cd998140b870b58dcedd5';
        $signature = $this->generate_signature($data, $has_key);

        if ($this->verify_signature($signature, $data)) {
            $this->add_payment_record($data->get_body());
            return $this->process_payment_data($data);
        } else {
            return new WP_REST_Response(['error' => 'Error de hash'], 404);
        }
    }

    /**
     * @param WP_REST_Request $data
     * @return WP_REST_Response
     */
    public function process_auth(WP_REST_Request $data): WP_REST_Response
    {
        if (is_null($data['storeId']) || is_null($data['token'])) {
            return new WP_REST_Response(['error' => 'Parámetros inválidos'], 422);
        }
        return $this->associate_token($data);
    }

    /**
     * @return void
     */
    public function register_routes(): void
    {
        register_rest_route($this->namespace_payments, '/' . $this->rest_base, [
            'methods' => 'POST',
            'callback' => [$this, 'process_payment'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->namespace_auth, '/' . $this->rest_base, [
            'methods' => 'POST',
            'callback' => [$this, 'process_auth'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * @param $payment_data
     * @param $status
     * @return WP_REST_Response
     */
    private function update_status($payment_data, $status): WP_REST_Response
    {
        $order = wc_get_order($payment_data->order_id);
        if (!$order) {
            return new WP_REST_Response(['error' => 'No se encontró ningún pago con ese referenceKey'], 404);
        }
        switch ($status) {
            case 0:
            case 3:
                $order->update_status('failed');
                break;
            case 1:
                $order->update_status('on-hold');
                break;
            case 2:
                $order->update_status('processing');
                break;
            case 9:
                $order->update_status('pending');
                break;
            default:
                break;
        }
        return new WP_REST_Response(['message' => 'Orden actualizada a estado: ' . $status], 200);
    }

    /**
     * @param $data
     * @return WP_REST_Response
     */
    private function associate_token($data): WP_REST_Response
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 're_facil_store_id';
        $storeId = $wpdb->get_var("SELECT storeId FROM $table_name");
        if ($storeId === $data['storeId']) {
            $result = $wpdb->update(
                $table_name,
                array('token' => $data['token']),
                array('storeId' => $storeId)
            );

            if ($result !== false) {
                return new WP_REST_Response(['message' => 'Se le asignó correctamente el token al storeid: ' .
                    $storeId], 200);
            } else {
                return new WP_REST_Response(['error' => 'Error no se encontró registro relacionado al storeId: ' .
                    $data['storeId']], 404);
            }
        }
        return new WP_REST_Response(['error' => 'Error al asignar el token del storeId: ' . $storeId], 404);
    }

    /**
     * @param $data
     * @return void
     */
    private function add_payment_record($data): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 're_facil_webhook_consumption_logs';
        $wpdb->insert(
            $table_name,
            array(
                'request_body' => $data
            )
        );
    }

    /**
     * @param $data
     * @return bool
     */
    private function validate_request_params($data): bool
    {
        return !(is_null($data['referenceId']) || is_null($data['resourceId']) || is_null($data['status'])
            || is_null($data['amount']) || is_null($data['updatedAt']));
    }

    /**
     * @param $data
     * @param $has_key
     * @return string
     */
    private function generate_signature($data, $has_key): string
    {
        return hash_hmac('sha1', $data['referenceId'] . '-' . $data['resourceId'] . '-' . $data['amount']
            . '-' . $data['updatedAt'] . '-' . $has_key, $has_key);
    }

    /**
     * @param $generatedHash
     * @param $data
     * @return bool
     */
    private function verify_signature($generatedHash, $data): bool
    {
        return hash_equals($generatedHash, $data['sign']);
    }

    /**
     * @param $data
     * @return WP_REST_Response
     */
    private function process_payment_data($data): WP_REST_Response
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 're_facil_payments';
        $resource_id  = sanitize_text_field($data['resourceId']);
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE re_facil_resource_id = %s", $resource_id);
        $payment_data = $wpdb->get_row($sql);

        if ($payment_data) {
            return $this->update_status($payment_data, $data['status']);
        } else {
            return new WP_REST_Response(['error' => 'No se encontró ningún pago con ese resourceId'], 404);
        }
    }
}
