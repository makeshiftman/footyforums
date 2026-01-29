function fdm_add_admin_menu() {
    add_menu_page(
        'Football Data Manager',
        'Football Data',
        'manage_options',
        'football-data-manager',
        'fdm_admin_page',
        'dashicons-groups', // Football-like icon
        30
    );
    
    add_submenu_page(
        'football-data-manager',
        'Scraping Tools',
        'Scraping Tools',
        'manage_options',
        'fdm-scraping-tools',
        'fdm_scraping_tools_page'
    );
}
add_action('admin_menu', 'fdm_add_admin_menu');

function fdm_admin_page() {
    ?>
    <div class="wrap">
        <h1>Football Data Manager</h1>
        
        <!-- Overview Dashboard -->
        <div class="fdm-dashboard">
            <div class="fdm-stat-box">
                <h3>Last Scrape</h3>
                <p><?php echo get_option('fdm_last_scrape_time', 'Never'); ?></p>
            </div>
            <div class="fdm-stat-box">
                <h3>Players in DB</h3>
                <p><?php echo wp_count_posts('fdm_player')->publish; ?></p>
            </div>
            <div class="fdm-stat-box">
                <h3>Automation Status</h3>
                <p><?php echo get_option('fdm_automation_enabled') ? '✅ Active' : '❌ Inactive'; ?></p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <h2>Quick Actions</h2>
        <button id="fdm-scrape-players" class="button button-primary">Scrape Player Data</button>
        <button id="fdm-scrape-fixtures" class="button">Scrape Fixtures</button>
        <button id="fdm-scrape-live" class="button">Fetch Live Scores</button>
        
        <!-- Progress Log -->
        <div id="fdm-progress-log" style="margin-top:20px; background:#f0f0f1; padding:15px; display:none;">
            <h3>Progress Log</h3>
            <pre id="fdm-log-content"></pre>
        </div>
    </div>
    
    <style>
        .fdm-dashboard { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .fdm-stat-box { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        function callScraper(endpoint, data) {
            $('#fdm-progress-log').show();
            $('#fdm-log-content').text('Starting scrape...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'fdm_run_scraper',
                    endpoint: endpoint,
                    data: data,
                    nonce: '<?php echo wp_create_nonce('fdm_scraper_nonce'); ?>'
                },
                success: function(response) {
                    $('#fdm-log-content').text(response.data.log || response.data);
                },
                error: function(xhr) {
                    $('#fdm-log-content').text('Error: ' + xhr.responseText);
                }
            });
        }
        
        $('#fdm-scrape-players').click(function() {
            callScraper('scrape-players', {leagues: ['EPL', 'La Liga']});
        });
    });
    });
    </script>
    <?php
}