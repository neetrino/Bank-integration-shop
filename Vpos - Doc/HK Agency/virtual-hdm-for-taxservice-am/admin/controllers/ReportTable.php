<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}


/**
 * List table class
 */
if (!class_exists('ReportTable')) {
    class ReportTable extends \WP_List_Table
    {
        public $plugin_url;
        // Declare property to avoid dynamic property deprecation in PHP 8.2+
        public $page_status = '2';

        function __construct()
        {
            global $tax_service_plugin_url;
            $this->plugin_url = $tax_service_plugin_url;
            parent::__construct(array(
                'singular' => 'report',
                'plural' => 'reports',
                'ajax' => false
            ));
            $this->enqueueScriptsAndStyles();
        }


        public function enqueueScriptsAndStyles()
        {
            wp_enqueue_style('hkd-tax-service-admin-fontawesome-css', $this->plugin_url . "admin/assets/css/fontawesome-all.css");
            wp_enqueue_style('hkd-tax-service-admin-fonts-css', $this->plugin_url . "admin/assets/css/fonts.css");
            wp_enqueue_style('hkd-tax-service-report-css', $this->plugin_url . "admin/assets/css/report.css");
            wp_enqueue_script("jquery");
            wp_enqueue_script('hkd-print-js', $this->plugin_url . "admin/assets/js/print.js");
            wp_localize_script('hkd-print-js', 'my_ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
        }

        function get_table_classes()
        {
            return array('widefat', 'fixed', 'striped', $this->_args['plural']);
        }

        /**
         * Message to show if no designation found
         *
         * @return void
         */
        function no_items()
        {
            _e('No reports found', 'tax-service');
        }

        /**
         * Default column values if no callback found
         *
         * @param object $item
         * @param string $column_name
         *
         * @return string
         */
        function column_default($item, $column_name)
        {

            switch ($item->status){
                case 'print':
                    $status = '<mark class="order-status status-processing"><span>'. esc_html($item->status).'</span></mark>';
                    break;
                case 'copy':
                    $status = '<mark class="order-status status-completed"><span>'. esc_html($item->status).'</span></mark>';
                    break;
                case 'refund':
                    $status = '<mark class="order-status status-on-hold"><span>'. esc_html($item->status).'</span></mark>';
                    break;

            }
            switch ($column_name) {
                case 'order_id':
                    return '<a href="/wp-admin/post.php?post='.$item->order_id.'&action=edit" target="_blank">'.$item->order_id.'</a>';
                case 'crn':
                    return $item->crn;
                case 'status':
                    return $status;
                case 'sn':
                    return $item->sn;
                case 'tin':
                    return $item->tin;
                case 'address':
                    return $item->address;
                case 'time':
                    return $item->time;
                case 'fiscal':
                    return $item->fiscal;
                case 'total':
                    return $item->total;
                case 'qr':
                    return '<img src="' . $this->generate_qr_code($item->qr) . '">';
                case 'created_at':
                    return $item->created_at;
                case 'action':
                    return $this->column_name($item);
                default:
                    return isset($item->$column_name) ? $item->$column_name : '';
            }
        }

        public function generate_qr_code($qr)
        {
            $qr = trim($qr);
            if ($qr === '') {
                return '';
            }
            if($this->get_http_response_code('https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=' . str_replace(' ', '', $qr)) != "200"){
                if($this->get_http_response_code('https://quickchart.io/chart?cht=qr&chs=80x80&chl=' . str_replace(' ', '', $qr)) != "200"){
                    return 'https://qrcode.tec-it.com/API/QRCode?data=' . str_replace(' ', '', $qr);
                }else{
                    return 'https://quickchart.io/chart?cht=qr&chs=80x80&chl=' . str_replace(' ', '', $qr);
                }
            }else{
                return 'https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=' . str_replace(' ', '', $qr);
            }
        }

        public function get_http_response_code($url) {
            $headers = get_headers($url);
            return substr($headers[0], 9, 3);
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
                'status' => __('Status', 'tax-service'),
                'crn' => __('CRN', 'tax-service'),
                'sn' => __('SN', 'tax-service'),
                'tin' => __('TIN', 'tax-service'),
                'address' => __('Address', 'tax-service'),
                'time' => __('Time', 'tax-service'),
                'fiscal' => __('Fiscal', 'tax-service'),
                'total' => __('Total', 'tax-service'),
                'qr' => __('QR', 'tax-service'),
                'created_at' => __('Created at', 'tax-service'),
                'action' => __('Action', 'tax-service'),
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
         * Method for name column
         *
         * @param array $item an array of DB data
         *
         * @return string
         */
        public function column_name($item)
        {

            if ($item->status !== 'refund') {
                $actions = [
                    'delete' => sprintf('<a href="?page=%s&action=%s&report=%s"><i title="Delete Report" class="fa fa-trash" style="color:red"></i></a>', sanitize_text_field($_REQUEST['page']), 'delete', absint($item->id)),
                    'copy' => sprintf('<a href="?page=%s&action=%s&report=%s"><i title="Print again report" class="fa fa-clone" style="color: #5b841b"></i></a>', sanitize_text_field($_REQUEST['page']), 'copy', absint($item->id)),
                    'refund' => sprintf('<a href="?page=%s&action=%s&report=%s"><i title="Refund" class="fa fa-undo" style="color:royalblue"></i></a>', sanitize_text_field($_REQUEST['page']), 'refund', absint($item->id)),
                ];

                if($item->order_id != 0){
                    $actions['print'] =  sprintf('<a href="javascript:" class="print-tax-service" data-id="%s"><i title="print" class="fa fa-print" style="color: grey"></i></a>',absint($item->order_id));
                }
            } else {
                $actions = [
                    'delete' => sprintf('<a href="?page=%s&action=%s&report=%s"><i title="Delete Report" class="fa fa-trash" style="color:red"></i></a>', sanitize_text_field($_REQUEST['page']), 'delete', absint($item->id)),
                    'print' => sprintf('<a href="javascript:" class="print-tax-service" data-id="%s"><i title="Print" class="fa fa-print" style="color: #5b841b"></i></a>',absint($item->order_id)),
                    'printRefund' => sprintf('<a href="javascript:" class="printRefund-tax-service" data-id="%s"><i title="print Refund" class="fa fa-print" style="color: #f8dda7"></i></a>',absint($item->order_id))
                ];
            }
            return $this->row_actions($actions);
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

        private function hkd_recursive_sanitize_text_field( $array ) {
            foreach ( $array as $key => &$value ) {
                if ( is_array( $value ) ) {
                    $value = $this->hkd_recursive_sanitize_text_field( $value );
                } else {
                    $value = sanitize_text_field( $value );
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
            switch ($this->current_action()) {
                case 'trash':
                    $ids = isset($_REQUEST['id']) ? $request['id'] : array();
                    $this->deleteItems($ids);
                    wp_redirect('/wp-admin/admin.php?page=tax-service-report');
                    exit;
                    break;
                case 'delete':
                    $ids = isset($_REQUEST['report']) ? $request['report'] : '';
                    $this->deleteItems([$ids]);
                    wp_redirect('/wp-admin/admin.php?page=tax-service-report');
                    exit;
                    break;
                case 'copy':
                    $payment = new WCHKDTaxServicePaymentController();
                    $payment->copyPrintPayment($request['report']);
                    wp_redirect('/wp-admin/admin.php?page=tax-service-report');
                    exit;
                    break;
                case 'refund':
                    $payment = new WCHKDTaxServicePaymentController();
                    $payment->refundPayment($request['report']);
                    wp_redirect('/wp-admin/admin.php?page=tax-service-report');
                    exit;
                    break;
            }

            $columns = $this->get_columns();
            $hidden = array();
            $sortable = $this->get_sortable_columns();
            $this->_column_headers = array($columns, $hidden, $sortable);

            $per_page = 20;
            $current_page = $this->get_pagenum();
            $offset = ($current_page - 1) * $per_page;
            $this->page_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '2';

            // only ncessary because we have sample data
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
            $data = $this->tax_service_get_all_payments($args);
            $this->items = $data['data'];

            $this->set_pagination_args(array(
                'total_items' => $this->tax_service_get_count($args)['count'],
                'per_page' => $per_page
            ));
        }

        public function deleteItems($ids)
        {
            global $wpdb;
            $ids = array_filter(array_map('absint', (array) $ids));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $sql = "DELETE FROM " . $wpdb->prefix . "tax_service WHERE ID IN ($placeholders)";
                $wpdb->query($wpdb->prepare($sql, ...$ids));
            }
        }

        public function tax_service_get_count($args)
        {
            global $wpdb;
            $cache_key = 'tax-report-count';
            $items = wp_cache_get($cache_key, 'tax-service');
            if (false === $items) {
                $sql = 'SELECT COUNT(id) as countItems FROM ' . $wpdb->prefix . 'tax_service';
                if (!empty($args['s'])) {
                    $sql .= $wpdb->prepare(' WHERE order_id LIKE %s', '%' . $wpdb->esc_like($args['s']) . '%');
                }
                $allowed_columns = array('id','order_id','created_at','status','total');
                $orderby = !empty($args['orderby']) && in_array($args['orderby'], $allowed_columns, true) ? $args['orderby'] : 'id';
                $order   = !empty($args['order']) && strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
                $sql    .= " ORDER BY {$orderby} {$order}";
                $items = $wpdb->get_results($sql);

                wp_cache_set($cache_key, $items, 'tax-service');
            }
            return [
                'count' => isset($items[0]->countItems) ? $items[0]->countItems: 0
            ];
        }

        public function tax_service_get_all_payments($args = array())
        {
            global $wpdb;
            $cache_key = 'tax-report-all';
            $items = wp_cache_get($cache_key, 'tax-service');
            if (false === $items) {
                $sql = 'SELECT * FROM ' . $wpdb->prefix . 'tax_service';
                if (!empty($args['s'])) {
                    $sql .= $wpdb->prepare(' WHERE order_id LIKE %s', '%' . $args['s'] . '%');
                }
                $allowed_columns = array('id','order_id','created_at','status','total');
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
                'count' => count($items)];
        }
    }
}