<?php
namespace Inc\Geslib\Api;

use WP_List_Table;
use Inc\Geslib\Api\GeslibApiDbManager;

class GeslibQueuesListTable extends WP_List_Table {

    /**
     * Columns that may appear in ORDER BY. Anything else falls back to 'id'.
     * ORDER BY cannot be parameterised via $wpdb->prepare(), so the only safe
     * input handling is a whitelist.
     */
    private const ORDERABLE_COLUMNS = [ 'id', 'log_id', 'geslib_id', 'type', 'entity', 'action', 'data' ];

    /** Direction whitelist for ORDER BY. */
    private const ORDER_DIRECTIONS = [ 'ASC', 'DESC' ];

    private $geslibApiDbLinesManager;

    public function __construct() {
        parent::__construct([
            'singular' => 'geslib_queues',  // Singular label of the table
            'plural'   => 'geslib_queues', // Plural label of the table
            'ajax'     => true             // Does this table support ajax?
        ]);

    }
    public function prepare_items() {
        global $wpdb;

        // Read-only admin table: gate on capability before touching the DB.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to view the Geslib queues table.', 'geslib' ) );
        }

        $cegalApiDbLinesManager = new GeslibApiDbQueueManager;
        $linesTable = $wpdb->prefix . $cegalApiDbLinesManager::GESLIB_QUEUES_TABLE;
        // define data set for WP_List_Table => data
        $where = ''; // Initialize where clause
        $filters = [
            'log_id',
            'geslib_id',
            'type',
            'entity',
            'action',
        ];
        foreach($filters as $filtername){
            $postdata = isset($_POST[ 'filter_'.$filtername ]) ? wp_unslash($_POST[ 'filter_'.$filtername ]) : '';
            if ( !empty( $postdata )) {
                $value = sanitize_text_field($postdata);
                $where = $wpdb->prepare(' WHERE '.$filtername.' = %s', $value);
            }
        }

        // Whitelist ORDER BY inputs; they are interpolated into SQL because
        // prepare() cannot bind identifiers.
        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id';
        if ( ! in_array( $orderby, self::ORDERABLE_COLUMNS, true ) ) {
            $orderby = 'id';
        }
        $order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'ASC';
        if ( ! in_array( $order, self::ORDER_DIRECTIONS, true ) ) {
            $order = 'ASC';
        }

        // Escape LIKE wildcards and bind every user value with prepare().
        // The previous query also matched a non-existent 'queued' column, so
        // any search failed with "Unknown column"; that clause is gone.
        $search_term = isset($_POST['s'])? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : "";
        if ($search_term) {
            $like     = '%' . $wpdb->esc_like( $search_term ) . '%';
            $clauses  = [];
            $args     = [];
            if ( is_numeric( $search_term ) ) {
                $clauses[] = 'log_id = %d';
                $clauses[] = 'geslib_id = %d';
                $args[]    = (int) $search_term;
                $args[]    = (int) $search_term;
            }
            foreach ( [ 'type', 'entity', 'action', 'data' ] as $text_column ) {
                $clauses[] = $text_column . ' LIKE %s';
                $args[]    = $like;
            }
            $where = $wpdb->prepare( ' WHERE ' . implode( ' OR ', $clauses ), $args );
        }
        // First, get the total count of items
        $sql_count = "SELECT COUNT(*) FROM {$linesTable} {$where}";
        $total_items = $wpdb->get_var($sql_count);

        // Set how many records per page to show
        $per_page = 20;

        // Calculate the total number of pages
        $total_pages = ceil($total_items / $per_page);


        $this->items = $this->wp_list_table_data($where, $per_page,  $orderby, $order);

        // Set the pagination arguments
        $this->set_pagination_args([
            'total_items' => $total_items, // Total number of items
            'per_page'    => $per_page,    // How many items to show on a page
            'total_pages' => $total_pages  // Total number of pages
        ]);
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns(); // Define hidden columns here if any
        $sortable = $this->get_sortable_columns(); // Define sortable columns here if any
        $this->_column_headers = [$columns, $hidden, $sortable];
    }

    public function wp_list_table_data($where = '', $per_page = 20,  $orderby = '', $order = '', $search_term = '') {
        global $wpdb;
        $geslibApiDbManager = new GeslibApiDbManager;
        // Determine what page the user is currently looking at
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Defense in depth: re-whitelist in case this method is called directly.
        if ( ! in_array( $orderby, self::ORDERABLE_COLUMNS, true ) ) {
            $orderby = 'id';
        }
        $order = strtoupper( (string) $order );
        if ( ! in_array( $order, self::ORDER_DIRECTIONS, true ) ) {
            $order = 'ASC';
        }

        $table = $wpdb->prefix . $geslibApiDbManager::GESLIB_QUEUES_TABLE;
        $sql_data = $wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            (int) $per_page,
            (int) $offset
        );
        return  $wpdb->get_results($sql_data, ARRAY_A);
    }

    public function get_columns() {
        $columns = [
            'id' => 'ID',
            'geslib_id' => 'Geslib ID',
            'log_id' => 'Log ID',
            'entity' => 'Entity',
            'type' => 'Type',
            'action' => 'Action',
            'data' => 'data',
        ];
        return $columns;
    }

    public function get_hidden_columns() {
        return [];
    }

    public function get_sortable_columns() {
        return [
            'id' => ['id', false],
            'log_id' => ['log_id', false],
            'geslib_id' => ['geslib_id', false],
            'type' => ['type', false],
            'entity' => ['entity', false],
            'action' => ['action', false],
            'data' => ['data', false],
        ];
    }

    /**
     * A description of the entire PHP function.
     *
     * @param $item description
     * @param $column_name description
     * @return Some_Return_Value
     */
    public function column_default( $item, $column_name ) {
        $geslibApiDbLinesManager = new GeslibApiDbLinesManager;
        /* if ( $item['book_id'] == null || !$item['book_id'] ) {
            $book_id = 0;
        } else {
            $book_id = (int) $item['book_id'];
        }
        $assignButton = "<button
                            type='submit'
                            data-action='assign-to-product'
                            data-isbn='" . $item['isbn'] . "'
                            class='button button-primary'>
                                Asignar
                            </button />"; */
        return match($column_name) {
            'id' => $item[$column_name],
            'log_id' => $item[$column_name],
            'geslib_id' => $item[$column_name],
            'entity' => $item[$column_name],
            'type' => $item[$column_name],
            'action' => $item[$column_name],
            'data' => $item[$column_name],
            default => 'no value',
        };
    }
}