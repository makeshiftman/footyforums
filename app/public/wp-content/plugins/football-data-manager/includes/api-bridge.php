function fdm_run_scraper() {
    // Security check
    check_ajax_referer('fdm_scraper_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }
    
    $endpoint = sanitize_text_field($_POST['endpoint']);
    $data = $_POST['data'] ?? [];
    
    // Call Python API
    $response = wp_remote_post(FDM_API_ENDPOINT . '/' . $endpoint, [
        'method' => 'POST',
        'body' => json_encode($data),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'timeout' => 300, // 5 minutes for long scrapes
    ]);
    
    if (is_wp_error($response)) {
        wp_send_json_error('API Error: ' . $response->get_error_message());
    }
    
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    
    if ($result['success']) {
        // Update last scrape time
        update_option('fdm_last_scrape_time', current_time('mysql'));
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['error']);
    }
}
add_action('wp_ajax_fdm_run_scraper', 'fdm_run_scraper');
