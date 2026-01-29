function fdm_schedule_automation() {
    if (!wp_next_scheduled('fdm_daily_scrape')) {
        wp_schedule_event(time(), 'hourly', 'fdm_daily_scrape');
    }
}
register_activation_hook(__FILE__, 'fdm_schedule_automation');

function fdm_daily_scrape_callback() {
    // Trigger player data update
    wp_remote_post(FDM_API_ENDPOINT . '/scrape-players', [
        'body' => json_encode(['leagues' => ['EPL', 'La Liga', 'Bundesliga']]),
        'blocking' => false, // Run in background
    ]);
    
    // Update live scores
    wp_remote_get(FDM_API_ENDPOINT . '/live-scores', ['blocking' => false]);
}
add_action('fdm_daily_scrape', 'fdm_daily_scrape_callback');