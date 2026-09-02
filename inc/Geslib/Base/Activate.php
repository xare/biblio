<?php

/**
 * @package Geslib
 */

namespace Inc\Geslib\Base;

 class Activate {
  public static function activate() {
    global $wpdb;
    flush_rewrite_rules();

    $default = ['geslib_folder_index' => 'geslib'];

    if ( !get_option('geslib_settings')) {
      update_option('geslib_settings', $default);
    }

    wp_mkdir_p( WP_CONTENT_DIR . '/uploads/geslib' );

    $charset_collate = $wpdb->get_charset_collate();
    $log_table_name = $wpdb->prefix . 'geslib_log';
    $queue_table_name = $wpdb->prefix. 'geslib_queues';

    $log_sql = "CREATE TABLE IF NOT EXISTS $log_table_name (
      id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
      filename text NOT NULL,
      cycle int(11) unsigned NOT NULL DEFAULT 1,
      start_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
      end_date datetime DEFAULT NULL,
      status text NOT NULL,
      lines_count int(11) NOT NULL,
      PRIMARY KEY (id),
      KEY idx_filename (filename(191)),
      KEY idx_status (status(50))
    ) $charset_collate;";

    $queue_sql = "CREATE TABLE IF NOT EXISTS $queue_table_name (
        id int(11) unsigned NOT NULL AUTO_INCREMENT,
        log_id mediumint(9) unsigned,
        geslib_id text,
        type varchar(255) NOT NULL,
        data text,
        PRIMARY KEY (id)
      ) $charset_collate;";


      require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
      dbDelta( $log_sql );
      dbDelta( $queue_sql );

      // Migration: add 'cycle' column to existing geslib_log tables
      $column_exists = $wpdb->get_var(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = '" . DB_NAME . "'
           AND TABLE_NAME = '{$log_table_name}'
           AND COLUMN_NAME = 'cycle'"
      );
      if ( !$column_exists ) {
        $wpdb->query( "ALTER TABLE {$log_table_name} ADD COLUMN cycle int(11) unsigned NOT NULL DEFAULT 1 AFTER filename" );
      }
  }
 }