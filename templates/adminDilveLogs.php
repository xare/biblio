<?php
use Inc\Covers\Api\CoversLogListTable;
use Inc\Covers\Api\CoversApiDbManager;
?>

<div class="wrap">
    <h1>Covers LOGS</h1>
    <?php settings_errors(); ?>
    <!-- FILTERS -->
    <?php
        global $wpdb;
        $coversApiDbManager = new CoversApiDbManager();
        $logTable = $wpdb->prefix . $coversApiDbManager::COVERS_LOG_TABLE;

        // Fetch distinct types
        $start_dates = $wpdb->get_col("SELECT DISTINCT start_date FROM {$logTable}");
        $end_dates = $wpdb->get_col("SELECT DISTINCT end_date FROM {$logTable}");
        $statuses = $wpdb->get_col("SELECT DISTINCT status FROM {$logTable}");
        $scanned_items = $wpdb->get_col("SELECT DISTINCT scanned_items FROM {$logTable}");
        $processed_items = $wpdb->get_col("SELECT DISTINCT processed_items FROM {$logTable}");
    ?>
    <form method="post">
        <select name="filter_start_date">
            <?php foreach($start_dates as $start_date): ?>
                <option value="<?php echo esc_attr($start_date); ?>" <?php selected(isset($_POST['filter_start_date']) && $_POST['filter_start_date'] === $start_date); ?>>
                    <?php echo esc_html($start_date); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="filter_end_date">
            <?php foreach($end_dates as $end_date): ?>
                <option value="<?php echo esc_attr($end_date); ?>" <?php selected(isset($_POST['filter_end_date']) && $_POST['filter_end_date'] === $end_date); ?>>
                    <?php echo esc_html($end_date); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="filter_status">
            <?php foreach($statuses as $status): ?>
                <option value="<?php echo esc_attr($status); ?>" <?php selected(isset($_POST['filter_status']) && $_POST['filter_status'] === $status); ?>>
                    <?php echo esc_html($status); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="filter_scanned_items">
            <?php foreach($scanned_items as $scanned_item): ?>
                <option value="<?php echo esc_attr($scanned_item); ?>" <?php selected(isset($_POST['filter_scanned_items']) && $_POST['filter_scanned_items'] === $scanned_item); ?>>
                    <?php echo esc_html($scanned_item); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="filter_processed_items">
            <?php foreach($processed_items as $processed_item): ?>
                <option value="<?php echo esc_attr($processed_item); ?>" <?php selected(isset($_POST['filter_processed_items']) && $_POST['filter_processed_items'] === $processed_item); ?>>
                    <?php echo esc_html($processed_item); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <!-- Page display -->
    <?php
        $wp_list_table = new CoversLogListTable;
        $wp_list_table->prepare_items();
        // Render the table
        echo "<form method='post' name='covers_log_search' action='".$_SERVER['PHP_SELF']."?page=covers_log'>";
        $wp_list_table->search_box("Covers Log Search", "search_dilve_log");
        echo "</form>";
        $wp_list_table->display();
    ?>

</div>