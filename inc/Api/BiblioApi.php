<?php
namespace Inc\Biblio\Api;

class BiblioApi {
    public function debug_log(string $title, string $message) {
        $filepath = ABSPATH . 'wp-content/plugins/biblio/logs/debug.log';
        $message = '[BIBLIO DEBUG -  '. $title . ' ] ' . date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
        file_put_contents($filepath, $message, FILE_APPEND);
    }
}