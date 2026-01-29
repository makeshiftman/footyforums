<?php

class FDM_Admin_Data_Status {

    public function __construct() {
        // Register Menu
        add_action('admin_menu', [$this, 'register_menu'], 20);
        // Register AJAX action for auto-refresh
        add_action('wp_ajax_fdm_dashboard_refresh', [$this, 'ajax_refresh_data']);
    }

    public function register_menu() {
        add_menu_page(
            'Data Status', 'FDM Status', 'manage_options', 'fdm-data-status', 
            [$this, 'render_page'], 'dashicons-chart-bar', 6
        );
    }

    // 1. THE CONTROLLER
    public function render_page() {
        $season = isset($_GET['season']) ? intval($_GET['season']) : 0;
        
        echo '<div class="wrap" id="fdm-dashboard-wrapper">';
        
        if ($season > 0) {
            $this->render_detail_view($season);
        } else {
            $this->render_main_view();
        }
        
        echo '</div>';
    }

    // 2. THE MAIN VIEW (Years)
    private function render_main_view() {
        echo '<h1 class="wp-heading-inline">Data Status Dashboard <span class="dashicons dashicons-update spin" style="font-size: 20px; color: #666;"></span></h1>';
        echo '<p>Live monitoring of the historical backfill. Auto-refreshes every 5 seconds.</p>';
        
        // Table Container
        echo '<table class="widefat fixed striped" id="fdm-status-table">';
        echo '<thead><tr><th>Year</th><th>Leagues Found</th><th>Total Matches</th><th>Transfers</th><th>Status</th></tr></thead>';
        echo '<tbody id="fdm-table-body">';
        echo '<tr><td colspan="5">Loading data...</td></tr>'; // Initial State
        echo '</tbody></table>';

        // Auto-Refresh Script
        ?>
        <script>
        jQuery(document).ready(function($) {
            function refreshDashboard() {
                $.post(ajaxurl, { action: 'fdm_dashboard_refresh' }, function(response) {
                    if (response.success) {
                        $('#fdm-table-body').html(response.data.html);
                        $('.dashicons-update').addClass('spin'); // Visual cue
                        setTimeout(function(){ $('.dashicons-update').removeClass('spin'); }, 500);
                    }
                });
            }
            // Run immediately and then every 5 seconds
            refreshDashboard();
            setInterval(refreshDashboard, 5000);
        });
        </script>
        <style>.spin { animation: rotation 2s infinite linear; } @keyframes rotation { from {transform: rotate(0deg);} to {transform: rotate(359deg);} }</style>
        <?php
    }

    // 3. THE DETAIL VIEW (Leagues in a Year)
    private function render_detail_view($season) {
        $e_db = new wpdb('root', 'root', 'e_db', DB_HOST); // CREDENTIALS
        
        // Back Button
        echo '<h1 class="wp-heading-inline">Season ' . esc_html($season) . ' Details</h1>';
        echo ' <a href="?page=fdm-data-status" class="page-title-action">Back to Overview</a>';
        
        // REPAIRED QUERY: Uses LEFT JOIN with DISTINCT counting to handle 1:N relationship
        // Table 'lineup' (singular) and join on 'eventid'
        $results = $e_db->get_results($e_db->prepare("
            SELECT 
                f.league_code, 
                COUNT(DISTINCT f.eventid) as fixtures,
                COUNT(DISTINCT l.eventid) as lineups
            FROM fixtures f
            LEFT JOIN lineup l ON l.eventid = f.eventid
            WHERE f.season = %d 
            GROUP BY f.league_code 
            ORDER BY fixtures DESC
        ", $season));

        // Transfer/Stats Count
        $transfers = $e_db->get_var($e_db->prepare("SELECT count(*) FROM transfers WHERE season = %d", $season));
        $stats = $e_db->get_var($e_db->prepare("SELECT count(*) FROM season_player_stats WHERE season = %d", $season));

        // Summary Card
        echo "<div class='card' style='margin-top:20px; max-width: 100%; padding: 15px; background: #fff; border: 1px solid #ccd0d4;'>";
        echo "<h3 style='margin-top:0;'>Platinum Data Check</h3>";
        echo "<p><strong>Transfers Fetched:</strong> " . number_format($transfers) . "</p>";
        echo "<p><strong>Season Stats Records:</strong> " . number_format($stats) . "</p>";
        echo "</div>";

        // League Table
        echo '<table class="widefat fixed striped" style="margin-top:20px;">';
        echo '<thead><tr><th>League Code</th><th>Fixtures</th><th>Lineups Scraped</th><th>Coverage</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($results)) {
            echo '<tr><td colspan="4">No data found for this season (or Query Failed).</td></tr>';
        } else {
            foreach ($results as $row) {
                // Heuristic: A typical league has ~380 games.
                $color = ($row->fixtures > 300) ? 'green' : (($row->fixtures > 100) ? 'orange' : 'red');
                $coverage_pct = ($row->fixtures > 0) ? round(($row->lineups / $row->fixtures) * 100) : 0;
                $coverage_label = ($coverage_pct > 0) ? $coverage_pct . '%' : '0%';
                
                echo "<tr>
                    <td>{$row->league_code}</td>
                    <td>" . number_format($row->fixtures) . "</td>
                    <td>" . number_format($row->lineups) . "</td>
                    <td style='color:{$color}; font-weight:bold;'>{$coverage_label}</td>
                </tr>";
            }
        }
        echo '</tbody></table>';
    }

    // 4. THE DATA FEED (AJAX)
    public function ajax_refresh_data() {
        $e_db = new wpdb('root', 'root', 'e_db', DB_HOST); // CREDENTIALS
        
        $results = $e_db->get_results("
            SELECT season, count(DISTINCT league_code) as leagues, count(*) as matches 
            FROM fixtures GROUP BY season ORDER BY season DESC
        ");

        ob_start();
        foreach ($results as $row) {
            // Check Platinum Data (Lightweight subqueries)
            $transfers = $e_db->get_var($e_db->prepare("SELECT count(*) FROM transfers WHERE season=%d", $row->season));
            
            $status = 'EMPTY'; $color = 'gray';
            if ($row->matches > 10000) { $status = 'PLATINUM (Running)'; $color = 'green'; }
            elseif ($row->matches > 1000) { $status = 'GOLD'; $color = 'orange'; }
            elseif ($row->matches > 0) { $status = 'BRONZE'; $color = 'red'; }

            // Link to Detail View
            $url = admin_url('admin.php?page=fdm-data-status&season=' . $row->season);
            
            echo "<tr>
                <td><a href='{$url}' style='font-weight:bold;'>{$row->season}</a></td>
                <td>{$row->leagues}</td>
                <td>" . number_format($row->matches) . "</td>
                <td>" . number_format($transfers) . "</td>
                <td style='color:{$color}; font-weight:bold;'>{$status}</td>
            </tr>";
        }
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
}
