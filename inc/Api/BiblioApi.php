<?php
namespace Inc\Biblio\Api;

class BiblioApi {
    public function debug_log(string $title, string $message, string $plugintype = 'biblio') {
        $filepath = ABSPATH . 'wp-content/plugins/biblio/logs/debug-'.$plugintype.'-'.date('Y-m-d').'.log';
        $message = '[BIBLIO DEBUG -  '. $title . ' ] ' . date('Y-m-d H:i:s') . PHP_EOL . ' ' . $message . PHP_EOL;
        file_put_contents($filepath, $message, FILE_APPEND);
    }
}