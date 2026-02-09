<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table class
 */
if (!class_exists('RequestLogTable')) {
    class RequestLogTable extends \WP_List_Table
    {

        public $page_status;

        public function __construct()
        {
            parent::__construct(array(
                'singular' => 'request',
                'plural' => 'requests',
                'ajax' => false
            ));
            $this->enqueueScriptsAndStyles();
        }

        public function enqueueScriptsAndStyles()
        {
            // Add custom scripts and styles if necessary
        }

        public function get_table_classes()
        {
            return array('widefat', 'fixed', 'striped', $this->_args['plural']);
        }

        /**
         * Message to show if no designation found
         *
         * @return void
         */
        public function no_items()
        {
            _e('No requests found', 'tax-service');
        }

        /**
         * Default column values if no callback found
         *
         * @param object $item
         * @param string $column_name
         *
         * @return string
         */
        public function column_default($item, $column_name)
        {
            switch ($column_name) {
                case 'order_id':
                    return $item->order_id;
                case 'full_name':
                    $order = wc_get_order($item->order_id);
                    if ($order)
                        return $order->get_formatted_billing_full_name();
                    else return 'ՉԻ ԳՏՆՎԵԼ';
                case 'payment_gateway':
                    return $item->payment_gateway;
                case 'url':
                    return $item->url;
                case 'request_data':
                    return $item->request_data;
                case 'response_data':
                    return $item->response_data;
                case 'created_at':
                    return $item->created_at;
                default:
                    return isset($item->$column_name) ? $item->$column_name : '';
            }
        }

        /**
         * Method for name column
         *
         * @param array $item an array of DB data
         *
         * @return string
         */
        public function column_name($item)
        {
            $title = '<strong>' . $item['name'] . '</strong>';
            $actions = [
                'delete' => sprintf('<a href="?page=%s&action=%s&customer=%s&_wpnonce=%s">Delete</a>', sanitize_text_field($_REQUEST['page']), 'delete', absint($item['id']))
            ];
            return $title . $this->row_actions($actions);
        }

        /**
         * Get the column names
         *
         * @return array
         */
        public function get_columns()
        {
            $columns = array(
                'cb' => '<input type="checkbox" />',
                'id' => __('ID', 'tax-service'),
                'order_id' => __('Order Id', 'tax-service'),
                'full_name' => __('Full Name', 'tax-service'),
                'payment_gateway' => __('Payment Gateway', 'tax-service'),
                'url' => __('Url', 'tax-service'),
                'request_data' => __('Request Data', 'tax-service'),
                'response_data' => __('Response Data', 'tax-service'),
                'created_at' => __('Created at', 'tax-service'),
            );
            return $columns;
        }

        /**
         * Get sortable columns
         *
         * @return array
         */
        public function get_sortable_columns()
        {
            $sortable_columns = array(
                'order_id' => array('order_id', true),
                'id' => array('id', true),
            );

            return $sortable_columns;
        }

        /**
         * Set the bulk actions
         *
         * @return array
         */
        public function get_bulk_actions()
        {
            $actions = array(
                'trash' => __('Move to Trash', 'tax-service'),
            );
            return $actions;
        }

        /**
         * Render the checkbox column
         *
         * @param object $item
         *
         * @return string
         */
        public function column_cb($item)
        {
            return sprintf(
                '<input type="checkbox" name="id[]" value="%d" />', $item->id
            );
        }

        /**
         * Set the views
         *
         * @return array
         */
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

        /**
         * Prepare the class items
         *
         * @return void
         */
        public function prepare_items()
        {
            $request = $this->hkd_recursive_sanitize_text_field($_REQUEST);

            if ($this->current_action() === 'trash') {
                $ids = isset($_REQUEST['id']) ? $request['id'] : array();
                $this->deleteItems($ids);
            }
            $columns = $this->get_columns();
            $hidden = array();
            $sortable = $this->get_sortable_columns();
            $this->_column_headers = array($columns, $hidden, $sortable);

            $per_page = 20;
            $current_page = $this->get_pagenum();
            $offset = ($current_page - 1) * $per_page;
            $this->page_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '2';

            // only necessary because we have sample data
            $args = array(
                'offset' => $offset,
                'number' => $per_page,
            );

            if (isset($request['s'])) {
                $args['s'] = $request['s'];
            }

            if (isset($request['orderby']) && isset($request['order'])) {
                $args['orderby'] = $request['orderby'];
                $args['order'] = $request['order'];
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
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '%d')); // Generate placeholders like '%d, %d, %d'
                $sql = "DELETE FROM " . $wpdb->prefix . "tax_service_report WHERE ID IN ($placeholders)";
                $wpdb->query($wpdb->prepare($sql, ...$ids));
            }
        }

        public function get_all_error_tax_report_count($args)
        {
            global $wpdb;
            $cache_key = 'tax-request-report-count';
            $items = wp_cache_get($cache_key, 'tax-service');
            if (false === $items) {
                $sql = 'SELECT COUNT(id) as countItems FROM ' . $wpdb->prefix . 'tax_service_requests ';
                $conditions = [];
                $values = [];

                // Handle search query (s)
                if (!empty($args['s'])) {
                    $conditions[] = "order_id LIKE %s";
                    $values[] = '%' . $wpdb->esc_like($args['s']) . '%';
                }

                if (!empty($conditions)) {
                    $sql .= ' WHERE ' . implode(' AND ', $conditions);
                }

                $items = $wpdb->get_results($wpdb->prepare($sql, ...$values));
                wp_cache_set($cache_key, $items, 'tax-service');
            }
            return [
                'count' => isset($items[0]->countItems) ? $items[0]->countItems : 0
            ];
        }

        public function get_all_error_tax_report($args = array())
        {
            global $wpdb;
            $cache_key = 'tax-service-request-all';
            $items = wp_cache_get($cache_key, 'tax-service');

            if (false === $items) {
                // Initialize the base query
                $sql = "SELECT * FROM {$wpdb->prefix}tax_service_requests";
                $conditions = [];
                $values = [];

                // Handle search query (s)
                if (!empty($args['s'])) {
                    $conditions[] = "order_id LIKE %s";
                    $values[] = '%' . $wpdb->esc_like($args['s']) . '%';
                }

                // Combine conditions with WHERE
                if (!empty($conditions)) {
                    $sql .= ' WHERE ' . implode(' AND ', $conditions);
                }

                // Handle ordering with whitelisting
                $allowed_columns = array('id','order_id','created_at');
                $orderby = !empty($args['orderby']) && in_array($args['orderby'], $allowed_columns, true) ? $args['orderby'] : 'id';
                $order   = !empty($args['order']) && strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
                $sql    .= " ORDER BY {$orderby} {$order}";

                // Add LIMIT and OFFSET (pagination)
                $sql .= ' LIMIT %d OFFSET %d';
                $values[] = (int) $args['number'];  // Limit
                $values[] = (int) $args['offset'];  // Offset

                // Prepare and execute the query
                $sql = $wpdb->prepare($sql, ...$values);

                // Get results
                $items = $wpdb->get_results($sql);

                // Cache results for better performance
                wp_cache_set($cache_key, $items, 'tax-service');
            }

            return [
                'data' => $items
            ];
        }
    }
}
