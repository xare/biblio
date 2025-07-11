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
        foreach (['biblio', 'dilve', 'cegal'] as $settingItem) {
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


        $charset_collate = $wpdb->get_charset_collate();
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