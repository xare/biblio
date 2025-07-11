<?php
namespace Inc\Geslib\Api;
use WP_List_Table;

class GeslibDebugListTable extends WP_List_Table { 

    public function __construct() {
        parent::__construct( array(
            'singular'  => 'geslib_debug',
            'plural'    => 'geslib_debug',
            'ajax'      => false
        ) );
    }

    public function prepare_items() {

        // Set how many records per page to show
        $per_page = 20;

        // Calculate the total number of pages

        $this->items = $this->wp_list_table_data($per_page)['items'];

        $total_items = $this->wp_list_table_data($per_page)['total'];
        $total_pages = ceil($total_items / $per_page);
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => $total_pages  // Total number of pages
        ]);
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
    }

    public function wp_list_table_data($per_page = 20,  $search_term = '') {
        $filepath = ABSPATH . 'wp-content/plugins/biblio/logs/debug.log';
        $lines = file($filepath, FILE_IGNORE_NEW_LINES);
        $lines_count = count($lines);
        $lines = array_filter($lines, function($line) {
            return str_starts_with('[BIBLIO', $line);
        });
        $items = array_slice($lines, 0, $per_page);
        return [
            'items' => $items,
            'total' => $lines_count
        ];
    }

    public function get_columns() {
        $columns = [
            'id' => 'ID',
            'date' => 'Fecha',
            'message' => 'Mensaje',
        ];
        return $columns;
    }

    public function get_hidden_columns() {
        return [];
    }

    public function get_sortable_columns() {
        return [];
    }

    public function column_default($item, $column_name) {
        return $item[$column_name];
    }
}
