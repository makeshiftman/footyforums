<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class FDM_Scheduler {

    const HOOK_DAILY_SYNC = 'fdm_daily_global_sync';

    public function __construct() {
        // 1. Register the Worker (What happens when the bell rings)
        add_action(self::HOOK_DAILY_SYNC, [$this, 'run_daily_job']);

        // 2. Schedule the Bell (If not already scheduled)
        add_action('init', [$this, 'ensure_schedule_exists']);
    }

    public function ensure_schedule_exists() {
        if (!wp_next_scheduled(self::HOOK_DAILY_SYNC)) {
            // Schedule for 02:00 tomorrow
            $time = strtotime('tomorrow 02:00:00');
            wp_schedule_event($time, 'daily', self::HOOK_DAILY_SYNC);
        }
    }

    public function run_daily_job() {
        // Load the Updater
        if (!class_exists('FDM_Daily_Updater')) {
            require_once plugin_dir_path(__FILE__) . 'class-fdm-daily-updater.php';
        }

        $updater = new FDM_Daily_Updater();
        
        // Log start
        error_log("FDM Scheduler: Starting Daily Global Sync...");
        
        // Run with "Catch-Up" logic (defaults to 'yesterday')
        $updater->run_daily_sync();
        
        error_log("FDM Scheduler: Daily Global Sync Complete.");
    }
}
