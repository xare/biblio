<?php

namespace Inc\Geslib;

/**
 * Custom WP-Cron schedules owned by the Geslib module.
 *
 * WordPress only ships hourly/twicedaily/daily/weekly/monthly. The Geslib
 * cron requests a "twohourly" recurrence, which wp_schedule_event() silently
 * refuses to use unless it is registered here first — see F-05.
 */
class Schedules {

  /**
   * Interval in seconds for the twohourly recurrence.
   */
  const TWO_HOURLY_INTERVAL = 7200;

  /**
   * Register the cron_schedules filter.
   * Must run before Cron::register() schedules events.
   *
   * @return void
   */
  public function register() {
    add_filter( 'cron_schedules', [ $this, 'add_schedules' ] );
  }

  /**
   * Add the twohourly schedule to the available WP-Cron schedules.
   *
   * @param array $schedules Registered schedules.
   * @return array
   */
  public function add_schedules( array $schedules ): array {
    if ( ! isset( $schedules['twohourly'] ) ) {
      $schedules['twohourly'] = [
        'interval' => self::TWO_HOURLY_INTERVAL,
        'display'  => __( 'Every Two Hours', 'geslib' ),
      ];
    }
    return $schedules;
  }
}
