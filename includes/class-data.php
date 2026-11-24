<?php
class CoreSpect_Data {

    public static function database() {

        $stats = CoreSpect_DB::get_db_stats();

        $size_mb = round($stats['total_size'] / 1024 / 1024, 2);
        $overhead_mb = round($stats['total_overhead'] / 1024 / 1024, 2);

        return [

            'tables' => $stats['tables'],
            'total_rows' => $stats['total_rows'],

            'total_size_bytes' => $stats['total_size'],
            'total_size_mb' => $size_mb,

            'total_overhead_bytes' => $stats['total_overhead'],
            'total_overhead_mb' => $overhead_mb,

            'revisions' => CoreSpect_DB::get_post_revisions(),
            'transients' => CoreSpect_DB::get_transients(),
            'spam_comments' => CoreSpect_DB::get_spam_comments(),

            'plugin_tables' => CoreSpect_DB::get_plugin_tables()

        ];
    }

    public static function performance() {

        $raw = CoreSpect_Performance::get_performance_data();

        return [

            'largest_resources' => $raw['largest'],

            'cache_plugin' => $raw['cache']['label'],
            'cache_state'  => $raw['cache']['state'],
            'cache_reason' => $raw['cache']['reason'],

            'php_version' => $raw['php_version'],

            'memory_limit'    => $raw['memory_limit'],
            'memory_limit_mb' => $raw['memory_limit_mb']

        ];

    }


}