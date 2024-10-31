<?php

class Event_Log_Re_Facil_Table_Creator
{
    public function __construct()
    {
        $this->create_table_event_log();
        $this->create_table_refacil_payment();
        $this->create_table_re_facil_webhook_consumption_log();
        $this->create_table_store_id();
        $this->create_store_id();
    }

    /**
     * @return void
     */
    private function create_table_event_log(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 're_facil_event_logs';
        $table_name = sanitize_text_field($table_name);
        $charset_collate = $wpdb->get_charset_collate();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if ($table_exists !== $table_name) {
            $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_name varchar(100) NOT NULL,
            request_body text NOT NULL,
            response_code varchar(3),
            response_data varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate";
            $wpdb->query($sql);
        }
    }

    /**
     * @return void
     */
    private function create_table_refacil_payment(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 're_facil_payments';
        $table_name = sanitize_text_field($table_name);
        $charset_collate = $wpdb->get_charset_collate();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if ($table_exists !== $table_name) {
            $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        re_facil_reference varchar(255) NOT NULL,
        reference1 varchar(20) NOT NULL,
        re_facil_resource_id varchar(255) NOT NULL,
        re_facil_status varchar(10) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
        ) $charset_collate";
            $wpdb->query($sql);
        }
    }

    /**
     * @return void
     */
    private function create_table_store_id(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 're_facil_store_id';
        $table_name = sanitize_text_field($table_name);
        $charset_collate = $wpdb->get_charset_collate();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if ($table_exists !== $table_name) {
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                storeId VARCHAR(32) NOT NULL UNIQUE,
                token VARCHAR(255),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate";
            $wpdb->query($sql);
        }
    }

    /**
     * @return void
     */
    private function create_table_re_facil_webhook_consumption_log(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 're_facil_webhook_consumption_logs';
        $table_name = sanitize_text_field($table_name);
        $charset_collate = $wpdb->get_charset_collate();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if ($table_exists !== $table_name) {
            $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        request_body text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
        ) $charset_collate";
            $wpdb->query($sql);
        }
    }

    /**
     * @return void
     */
    private function create_store_id(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 're_facil_store_id';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($count == 0) {
            $store_id = uniqid();
            $wpdb->insert(
                $table_name,
                array(
                    'storeId' => $store_id,
                    'created_at' => current_time('mysql')
                ),
                array(
                    '%s',
                    '%s'
                )
            );
        }
    }
}
