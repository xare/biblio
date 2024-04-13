<?php

/**
 * @package Biblio
 */
namespace Inc\Biblio\Base;

 class Deactivate {
  public static function deactivate() {
    flush_rewrite_rules();
  }
 }