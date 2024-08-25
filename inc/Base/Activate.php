<?php

/**
 * @package Biblio
 */

namespace Inc\Biblio\Base;

class Activate {

    public static function activate() {
        global $wpdb;
        flush_rewrite_rules();
        $default = [];
        foreach (['biblio', 'geslib', 'dilve', 'cegal'] as $settingItem) {
            update_option($settingItem . '_settings', $default);
        }
        /* if ( !get_option('biblio')) {
            update_option('biblio', $default);
          } */

          if ( !get_option('biblio_cpt')) {
            update_option('biblio_cpt', $default);
          }

          if ( !get_option('biblio_taxonomy')) {
            update_option('biblio_taxonomy', $default);
          }

        wp_mkdir_p( WP_CONTENT_DIR . '/uploads/'.get_option('geslib_settings') );

        $charset_collate = $wpdb->get_charset_collate();
        $log_table_name = $wpdb->prefix . 'geslib_log';
        $queue_table_name = $wpdb->prefix. 'geslib_queues';

        $log_sql = "CREATE TABLE IF NOT EXISTS $log_table_name (
            id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
            filename text NOT NULL,
            start_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            end_date datetime DEFAULT NULL,
            status text NOT NULL,
            lines_count int(11) NOT NULL,
            PRIMARY KEY (id)
          ) $charset_collate;";

        $queue_sql = "CREATE TABLE IF NOT EXISTS $queue_table_name (
            id int(11) unsigned NOT NULL AUTO_INCREMENT,
            log_id mediumint(9) unsigned,
            geslib_id mediumint(9) unsigned,
            type varchar(255) NOT NULL,
            entity varchar(255),
            action varchar(255),
            data text,
            PRIMARY KEY (id)
        ) $charset_collate;";


        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $log_sql );
        dbDelta( $queue_sql );

        $covers_log_table_name = $wpdb->prefix . 'covers_log';
        $covers_lines_table_name = $wpdb->prefix . 'covers_lines';

        $covers_log_sql = "CREATE TABLE IF NOT EXISTS $covers_log_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            `start_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `end_date` datetime NULL DEFAULT NULL,
            `status` varchar(255) NOT NULL,
            `scanned_items` mediumint(9) NOT NULL,
            `processed_items` mediumint(9) NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;";


        $covers_lines_sql = "CREATE TABLE IF NOT EXISTS $covers_lines_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            `log_id` mediumint(9) unsigned,
            `isbn` varchar(255) NOT NULL,
            `path` varchar(255) NOT NULL,
            `url_origin` varchar(255) NOT NULL,
            `url_target` varchar(255) NOT NULL,
            `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `isError` boolean,
            `error` varchar(255) NOT NULL,
            `attempts` mediumint(9) NOT NULL,
            `type` varchar(255) NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta( $covers_log_sql );
        dbDelta( $covers_lines_sql );
    }
}