<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table class
 */
if (!class_exists('ErrorReportTable')) {
    class ErrorReportTable extends \WP_List_Table
    {

        // Declare the page_status property to prevent deprecated warning
        private $page_status;

        public function __construct()
        {
            parent::__construct(array(
                'singular' => 'errorReport',
                'plural' => 'errorReports',
                'ajax' => false
            ));
            $this->enqueueScriptsAndStyles();
        }

        public function enqueueScriptsAndStyles()
        {
            // Optional: Enqueue necessary styles or scripts here
        }

        public function get_table_classes()
        {
            return array('widefat', 'fixed', 'striped', $this->_args['plural']);
        }

        public function no_items()
        {
            _e('No reports found', 'tax-service');
        }

        public function column_default($item, $column_name)
        {
            switch ($column_name) {
                case 'order_id':
                    return $item->order_id;
                case 'full_name':
                    $order = wc_get_order($item->order_id);
                    return $order ? $order->get_formatted_billing_full_name() : 'Not Found';
                case 'payment_gateway':
                    return $item->payment_gateway;
                case 'error_reason':
                    return $item->error_reason;
                case 'message':
                    return $item->message;
                case 'created_at':
                    return $item->created_at;
                default:
                    return isset($item->$column_name) ? $item->$column_name : '';
            }
        }

        public function column_name($item)
        {
            $title = '<strong>' . $item['name'] . '</strong>';
            $actions = [
                'delete' => sprintf('<a href="?page=%s&action=%s&customer=%s&_wpnonce=%s">Delete</a>', sanitize_text_field($_REQUEST['page']), 'delete', absint($item['id']))
            ];
            return $title . $this->row_actions($actions);
        }

        public function get_columns()
        {
            return array(
                'cb' => '<input type="checkbox" />',
                'id' => __('ID', 'tax-service'),
                'order_id' => __('Order Id', 'tax-service'),
                'full_name' => __('Full Name', 'tax-service'),
                'payment_gateway' => __('Payment Gateway', 'tax-service'),
                'error_reason' => __('Error Reason', 'tax-service'),
                'message' => __('Error Message', 'tax-service'),
                'created_at' => __('Created at', 'tax-service'),
            );
        }

        public function get_sortable_columns()
        {
            return array(
                'order_id' => array('order_id', true),
                'id' => array('id', true),
            );
        }

        public function get_bulk_actions()
        {
            return array(
                'trash' => __('Move to Trash', 'tax-service'),
            );
        }

        public function column_cb($item)
        {
            return sprintf('<input type="checkbox" name="id[]" value="%d" />', $item->id);
        }

        public function get_views_()
        {
            $status_links = array();
            $base_link = admin_url('admin.php?page=sample-page');
            foreach ($this->counts as $key => $value) {
                $class = ($key == $this->page_status) ? 'current' : 'status-' . $key;
                $status_links[$key] = sprintf('<a href="%s" class="%s">%s <span class="count">(%s)</span></a>', add_query_arg(array('status' => $key), $base_link), $class, $value['label'], $value['count']);
            }
            return $status_links;
        }

        private function hkd_recursive_sanitize_text_field($array)
        {
            foreach ($array as $key => &$value) {
                if (is_array($value)) {
                    $value = $this->hkd_recursive_sanitize_text_field($value);
                } else {
                    $value = sanitize_text_field($value);
                }
            }
            return $array;
        }

        public function prepare_items()
        {
            $request = $this->hkd_recursive_sanitize_text_field($_REQUEST);

            if ($this->current_action() === 'trash') {
                $ids = isset($request['id']) ? $request['id'] : array();
                $this->deleteItems($ids);
            }
            $columns = $this->get_columns();
            $hidden = array();
            $sortable = $this->get_sortable_columns();
            $this->_column_headers = array($columns, $hidden, $sortable);

            $per_page = 20;
            $current_page = $this->get_pagenum();
            $offset = ($current_page - 1) * $per_page;
            $this->page_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '2'; // Using declared $page_status

            // Arguments for fetching data
            $args = array(
                'offset' => $offset,
                'number' => $per_page,
            );

            if (isset($request['s'])) {
                $args['s'] = sanitize_text_field($request['s']);
            }

            if (isset($request['orderby']) && isset($request['order'])) {
                $args['orderby'] = sanitize_text_field($request['orderby']);
                $args['order'] = sanitize_text_field($request['order']);
            }
            $data = $this->get_all_error_tax_report($args);
            $this->items = $data['data'];

            $this->set_pagination_args(array(
                'total_items' => $this->get_all_error_tax_report_count($args)['count'],
                'per_page' => $per_page
            ));
        }

        public function deleteItems($ids)
        {
            global $wpdb;
            $ids = array_filter(array_map('absint', (array) $ids));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $sql = "DELETE FROM " . $wpdb->prefix . "tax_service_report WHERE ID IN ($placeholders)";
                $wpdb->query($wpdb->prepare($sql, ...$ids));
            }
        }

        public function get_all_error_tax_report_count($args)
        {
            global $wpdb;
            $cache_key = 'tax-report-count';
            $items = wp_cache_get($cache_key, 'tax-service');
            if (false === $items) {
                $sql = 'SELECT COUNT(id) as countItems FROM ' . $wpdb->prefix . 'tax_service_report';
                if (!empty($args['s'])) {
                    $sql .= $wpdb->prepare(' WHERE order_id LIKE %s', '%' . $wpdb->esc_like($args['s']) . '%');
                }
                $allowed_columns = array('id','order_id');
                $orderby = !empty($args['orderby']) && in_array($args['orderby'], $allowed_columns, true) ? $args['orderby'] : 'id';
                $order   = !empty($args['order']) && strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
                $sql    .= " ORDER BY {$orderby} {$order}";
                $items = $wpdb->get_results($sql);

                wp_cache_set($cache_key, $items, 'tax-service');
            }
            return ['count' => isset($items[0]->countItems) ? $items[0]->countItems : 0];
        }

        public function get_all_error_tax_report($args = array())
        {
            global $wpdb;
            $cache_key = 'tax-error_report-all';
            $items = wp_cache_get($cache_key, 'tax-service');
            if (false === $items) {

                $sql = "SELECT * FROM {$wpdb->prefix}tax_service_report";
                if (!empty($args['s'])) {
                    $sql .= $wpdb->prepare(' WHERE order_id LIKE %s', '%' . $args['s'] . '%');
                }
                $allowed_columns = array('id','order_id','payment_gateway','created_at');
                $orderby = !empty($args['orderby']) && in_array($args['orderby'], $allowed_columns, true) ? $args['orderby'] : 'id';
                $order   = !empty($args['order']) && strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
                $sql    .= " ORDER BY {$orderby} {$order}";
                $sql    .= ' LIMIT %d OFFSET %d';
                $sql     = $wpdb->prepare($sql, (int) $args['number'], (int) $args['offset']);

                $items = $wpdb->get_results($sql);
                wp_cache_set($cache_key, $items, 'tax-service');
            }
            return [
                'data' => $items,
                'count' => count($items)
            ];
        }
    }
}
