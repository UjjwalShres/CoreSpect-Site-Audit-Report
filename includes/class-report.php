<?php
if (!defined('ABSPATH')) exit;

class CoreSpect_Builder {

    public static function generate() {

        return [

            'database' => CoreSpect_Data::database(),

            'performance' => CoreSpect_Data::performance(),

            //'security' => CoreSpect_Data::security(),

            //'files' => CoreSpect_Data::files(),

            //'plugins' => CoreSpect_Data::plugins()

        ];

    }

}