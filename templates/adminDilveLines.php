<?php

use Inc\Covers\Api\CoversApiLinesListTable;
use Inc\Covers\Api\CoversApiLogListTable;
?>

<div class="wrap">
    <h1>Dilve LINES</h1>
    <?php settings_errors(); ?>
    <!-- FILTERS -->
    <?php
        global $wpdb;
        $linesTable = $wpdb->prefix . 'covers_lines';

        // Fetch distinct types
        $isbn_sql = "SELECT DISTINCT isbn FROM {$linesTable}";
        $isbns = $wpdb->get_col($isbn_sql);

        $path_sql = "SELECT DISTINCT path FROM {$linesTable}";
        $paths = $wpdb->get_col($path_sql);

        $url_origin_sql = "SELECT DISTINCT url_origin FROM {$linesTable}";
        $url_origins = $wpdb->get_col($url_origin_sql);

        $url_target_sql = "SELECT DISTINCT url_target FROM {$linesTable}";
        $url_targets = $wpdb->get_col($url_target_sql);

        $isError_sql = "SELECT DISTINCT isError FROM {$linesTable}";
        $isError = $wpdb->get_col($isError_sql);

        $error_sql = "SELECT DISTINCT error FROM {$linesTable}";
        $errors = $wpdb->get_col($error_sql);

        $attempts_sql = "SELECT DISTINCT attempts FROM {$linesTable}";
        $attempts = $wpdb->get_col($attempts_sql);

        $type_sql = "SELECT DISTINCT type FROM {$linesTable}";
        $types = $wpdb->get_col($type_sql);
        ?>
    <form method="post">
        <select name="filter_isbn">
            <option value="">All ISBN</option>
            <?php foreach ($isbns as $isbn): ?>
                <option value="<?php echo esc_attr($isbn); ?>" <?php selected(isset($_POST['filter_isbn']) && $_POST['filter_isbn'] === $isbn); ?>>
                    <?php echo esc_html($isbn); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="filter_path">
            <option value="">All Paths</option>
            <?php foreach ($paths as $path): ?>
                <option value="<?php echo esc_attr($path); ?>" <?php selected(isset($_POST['filter_path']) && $_POST['filter_path'] === $path); ?>>
                    <?php echo esc_html($path); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="filter_url_origin">
            <option value="">All URL Origins</option>
            <?php foreach ($url_origins as $url_origin): ?>
                <option value="<?php echo esc_attr($url_origin); ?>" <?php selected(isset($_POST['filter_url_origin']) && $_POST['filter_url_origin'] === $url_origin); ?>>
                    <?php echo esc_html($url_origin); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="filter_url_target">
            <option value="">All URL Targets</option>
            <?php foreach ($url_targets as $url_target): ?>
                <option value="<?php echo esc_attr($url_target); ?>" <?php selected(isset($_POST['filter_url_target']) && $_POST['filter_url_target'] === $url_target); ?>>
                    <?php echo esc_html($url_target); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="filter_isError"></select>
        <select name="filter_error">
            <option value="">All URL Errors</option>
            <?php foreach ($errors as $error): ?>
                <option value="<?php echo esc_attr($error); ?>" <?php selected(isset($_POST['filter_error']) && $_POST['filter_error'] === $error); ?>>
                    <?php echo esc_html($error); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="filter_type">
            <option value="">All Types</option>
            <?php foreach ($types as $type): ?>
                <option value="<?php echo esc_attr($type); ?>" <?php selected(isset($_POST['filter_type']) && $_POST['filter_type'] === $type); ?>>
                    <?php echo esc_html($type); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="submit" value="Filter"/>
    </form>
    <!-- Page display -->
    <?php
        $wp_list_table = new CoversApiLinesListTable;
        $wp_list_table->prepare_items();
        // Render the table
        echo "<form method='post' name='covers_lines_search' action='".$_SERVER['PHP_SELF']."?page=covers_lines'>";
        $wp_list_table->search_box("Covers Lines Search", "search_covers_lines");
        echo "</form>";
        $wp_list_table->display();
    ?>

</div>