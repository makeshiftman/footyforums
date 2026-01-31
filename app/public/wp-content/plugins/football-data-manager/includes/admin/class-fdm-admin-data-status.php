<?php

/**
 * FDM Admin Data Status - ESPN Data Tracking
 *
 * Shows ALL data types available from ESPN with scraped/available counts.
 * Visual tracking of what ESPN has vs what we've collected.
 */
class FDM_Admin_Data_Status {

    /** @var wpdb External database connection */
    private $e_db;

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu'], 20);
        add_action('wp_ajax_fdm_dashboard_refresh', [$this, 'ajax_refresh_data']);
        add_action('wp_ajax_fdm_leagues_refresh', [$this, 'ajax_leagues_refresh']);
        add_action('wp_ajax_fdm_prober_progress', [$this, 'ajax_prober_progress']);
        add_action('wp_ajax_fdm_scraper_progress', [$this, 'ajax_scraper_progress']);
    }

    /**
     * Get external database connection
     */
    private function get_e_db() {
        if (!$this->e_db) {
            $this->e_db = new wpdb('root', 'root', 'e_db', DB_HOST);
        }
        return $this->e_db;
    }

    public function register_menu() {
        add_menu_page(
            'ESPN Data Status',
            'FDM Status',
            'manage_options',
            'fdm-data-status',
            [$this, 'render_page'],
            'dashicons-chart-bar',
            6
        );
    }

    /**
     * Main page controller
     */
    public function render_page() {
        $season = isset($_GET['season']) ? intval($_GET['season']) : 0;
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'by-year';

        echo '<div class="wrap" id="fdm-dashboard-wrapper">';
        $this->render_styles();

        if ($season > 0) {
            $this->render_detail_view($season);
        } else {
            // Render tabs
            $this->render_tabs($tab);

            if ($tab === 'by-league') {
                $this->render_leagues_view();
            } else {
                $this->render_main_view();
            }
        }

        echo '</div>';
    }

    /**
     * Get prober progress data
     */
    private function get_prober_progress() {
        $e_db = $this->get_e_db();

        $probed_count = intval($e_db->get_var("SELECT COUNT(DISTINCT league_code) FROM espn_availability"));

        // Use probed count as total once prober has run, otherwise estimate 220
        // (ESPN has ~216-224 leagues, prober discovers the actual count)
        $total_leagues = $probed_count > 0 ? $probed_count : 220;

        $percentage = $total_leagues > 0 ? round(($probed_count / $total_leagues) * 100, 1) : 0;
        $is_complete = $probed_count >= $total_leagues;

        // Check if prober is running (log modified in last 60 seconds)
        $prober_running = false;
        $log_file = '/tmp/probe-availability.log';
        if (file_exists($log_file)) {
            $mtime = filemtime($log_file);
            if ($mtime && (time() - $mtime) < 60) {
                $prober_running = true;
            }
        }

        return [
            'probed' => $probed_count,
            'total' => $total_leagues,
            'percentage' => $percentage,
            'is_complete' => $is_complete,
            'is_running' => $prober_running
        ];
    }

    /**
     * Get scraper progress from log file
     */
    private function get_scraper_progress() {
        $log_file = '/tmp/historical-scrape.log';
        $completed = 0;
        $total = 4752; // Default: 198 leagues × 24 years
        $is_running = false;

        if (file_exists($log_file)) {
            // Check if running (modified in last 120 seconds - scraper is slower)
            $mtime = filemtime($log_file);
            if ($mtime && (time() - $mtime) < 120) {
                $is_running = true;
            }

            // Parse log to find progress - look for [X/Y] pattern
            $log_content = file_get_contents($log_file);
            if (preg_match_all('/\[(\d+)\/(\d+)\]/', $log_content, $matches)) {
                $last_idx = count($matches[1]) - 1;
                $completed = intval($matches[1][$last_idx]);
                $total = intval($matches[2][$last_idx]);
            }
        }

        $percentage = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        $is_complete = $completed >= $total && $total > 0;

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $percentage,
            'is_complete' => $is_complete,
            'is_running' => $is_running
        ];
    }

    /**
     * Render inline progress bars (for header)
     */
    private function render_prober_inline() {
        $p = $this->get_prober_progress();
        $s = $this->get_scraper_progress();

        ?>
        <div style="display: flex; align-items: center; gap: 20px; font-size: 12px;">
            <!-- Prober progress -->
            <div id="fdm-prober-progress" style="display: flex; align-items: center; gap: 8px;">
                <span style="color: #666;">Prober:</span>
                <div style="width: 100px; background: #e0e0e0; border-radius: 3px; height: 14px; overflow: hidden;">
                    <div id="prober-bar" style="width: <?php echo $p['percentage']; ?>%; height: 100%; background: <?php echo $p['is_complete'] ? '#4caf50' : '#2196f3'; ?>; transition: width 0.5s;"></div>
                </div>
                <span id="prober-text"><?php echo $p['probed']; ?>/<?php echo $p['total']; ?></span>
                <?php if ($p['is_running']): ?>
                    <span style="color: #2196f3;">●</span>
                <?php elseif ($p['is_complete']): ?>
                    <span style="color: #4caf50;">✓</span>
                <?php else: ?>
                    <span style="color: #ff9800;">○</span>
                <?php endif; ?>
            </div>

            <!-- Scraper progress -->
            <div id="fdm-scraper-progress" style="display: flex; align-items: center; gap: 8px;">
                <span style="color: #666;">Scraper:</span>
                <div style="width: 100px; background: #e0e0e0; border-radius: 3px; height: 14px; overflow: hidden;">
                    <div id="scraper-bar" style="width: <?php echo $s['percentage']; ?>%; height: 100%; background: <?php echo $s['is_complete'] ? '#4caf50' : '#9c27b0'; ?>; transition: width 0.5s;"></div>
                </div>
                <span id="scraper-text"><?php echo number_format($s['completed']); ?>/<?php echo number_format($s['total']); ?> (<?php echo $s['percentage']; ?>%)</span>
                <?php if ($s['is_running']): ?>
                    <span style="color: #9c27b0;">●</span>
                <?php elseif ($s['is_complete']): ?>
                    <span style="color: #4caf50;">✓</span>
                <?php else: ?>
                    <span style="color: #ff9800;">○</span>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            function refreshProgress() {
                $.post(ajaxurl, { action: 'fdm_prober_progress' }, function(response) {
                    if (response.success) {
                        var d = response.data;
                        var pct = d.total > 0 ? ((d.probed / d.total) * 100).toFixed(1) : 0;
                        $('#prober-bar').css('width', pct + '%');
                        if (d.probed >= d.total) $('#prober-bar').css('background', '#4caf50');
                        $('#prober-text').html(d.probed + '/' + d.total);
                    }
                });
                $.post(ajaxurl, { action: 'fdm_scraper_progress' }, function(response) {
                    if (response.success) {
                        var d = response.data;
                        var pct = d.total > 0 ? ((d.completed / d.total) * 100).toFixed(1) : 0;
                        $('#scraper-bar').css('width', pct + '%');
                        if (d.completed >= d.total) $('#scraper-bar').css('background', '#4caf50');
                        $('#scraper-text').html(d.completed.toLocaleString() + '/' + d.total.toLocaleString() + ' (' + pct + '%)');
                    }
                });
            }
            setInterval(refreshProgress, 5000);
        });
        </script>
        <?php
    }

    /**
     * Render tab navigation
     */
    private function render_tabs($active_tab) {
        $tabs = [
            'by-year' => 'By Year',
            'by-league' => 'By League',
        ];

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab_key => $tab_label) {
            $active = ($active_tab === $tab_key) ? ' nav-tab-active' : '';
            $url = admin_url('admin.php?page=fdm-data-status&tab=' . $tab_key);
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . $active . '">' . esc_html($tab_label) . '</a>';
        }
        echo '</h2>';
        echo '<br>';
    }

    /**
     * Render CSS styles for status display
     */
    private function render_styles() {
        ?>
        <style>
            /* Status cell backgrounds - subtle indication of state */
            .fdm-cell-complete { background-color: #e8f5e9 !important; } /* Light green - done with data */
            .fdm-cell-partial { background-color: #fff8e1 !important; }  /* Light amber - in progress */
            .fdm-cell-missing { background-color: #ffebee !important; }  /* Light red - needs scraping */
            .fdm-cell-na { background-color: #fff !important; }          /* White - not available/empty */
            .fdm-cell-unknown { background-color: #e3f2fd !important; }  /* Light blue - unknown/not probed */

            /* Status icon colors */
            .fdm-status-complete { color: #2e7d32; font-weight: bold; }
            .fdm-status-partial { color: #f57c00; font-weight: bold; }
            .fdm-status-missing { color: #c62828; font-weight: bold; }
            .fdm-status-na { color: #bdbdbd; }
            .fdm-status-unknown { color: #1976d2; }

            /* Table container for horizontal scroll */
            .fdm-table-container {
                overflow-x: auto;
                margin-top: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }

            /* Table styling */
            .fdm-status-table {
                border-collapse: collapse;
                width: max-content;
                min-width: 100%;
            }
            .fdm-status-table th {
                text-align: center;
                background: #1d2327 !important;
                color: #ffffff !important;
                padding: 10px 12px;
                font-size: 12px;
                font-weight: 600;
                border: 1px solid #1d2327;
                white-space: nowrap;
            }
            .fdm-status-table th.group-header {
                background: #0073aa !important;
                border-bottom: 2px solid #005a87;
            }
            .fdm-status-table td {
                text-align: center;
                font-family: 'Monaco', 'Consolas', monospace;
                font-size: 12px;
                padding: 8px 10px;
                white-space: nowrap;
                border: 1px solid #e5e5e5;
            }
            .fdm-status-table .year-cell {
                font-weight: bold;
                text-align: left;
                padding-left: 15px;
                background: #f9f9f9;
                position: sticky;
                left: 0;
                z-index: 1;
            }
            .fdm-status-table .year-cell a {
                text-decoration: none;
                color: #0073aa;
                font-size: 13px;
            }
            .fdm-status-table .year-cell a:hover {
                text-decoration: underline;
            }
            .fdm-status-table tr:nth-child(even) td { background: #f9f9f9; }
            .fdm-status-table tr:nth-child(even) td.year-cell { background: #f0f0f0; }
            .fdm-status-table tr:hover td { background: #eaf3fa; }
            .fdm-status-table tr:hover td.year-cell { background: #dde9f3; }

            /* Totals row */
            .fdm-status-table .totals-row td {
                background: #f5f5f5 !important;
                font-weight: bold;
                border-top: 2px solid #23282d;
            }

            /* Header area */
            .fdm-header {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 10px;
            }
            .fdm-header h1 { margin: 0; }
            .fdm-subheader {
                color: #666;
                margin: 0 0 15px 0;
            }

            /* Legend */
            .fdm-legend {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
                margin-bottom: 15px;
                padding: 10px 15px;
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .fdm-legend-item {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 13px;
            }

        </style>
        <?php
    }

    /**
     * Format status cell with icon, content, and CSS class
     * Returns array: ['class' => 'fdm-cell-*', 'html' => '...']
     *
     * States:
     * - Complete (green): scraped >= 95% of expected, and scraped > 0
     * - Partial (yellow): scraped > 0 but < 95%
     * - Missing (red): expected > 0 but scraped = 0
     * - N/A (white): expected = 0 (ESPN has nothing - confirmed empty)
     * - Unknown (blue): expected = null (not probed yet)
     */
    private function format_status_cell($scraped, $expected, $compact = false, $return_array = false) {
        $scraped = intval($scraped);

        // Unknown: haven't probed ESPN yet for this league/year
        if ($expected === null) {
            if ($scraped > 0) {
                // We have data but don't know the total - show as partial
                $result = [
                    'class' => 'fdm-cell-partial',
                    'html' => '<span class="fdm-status-partial">~</span> ' . number_format($scraped) . '/?'
                ];
            } else {
                // No data and no availability info - unknown
                $result = [
                    'class' => 'fdm-cell-unknown',
                    'html' => '<span class="fdm-status-unknown">?</span>'
                ];
            }
            return $return_array ? $result : $result['html'];
        }

        $expected = intval($expected);

        // N/A: ESPN confirmed to have 0 data for this league/year
        if ($expected === 0) {
            $result = [
                'class' => 'fdm-cell-na',
                'html' => '<span class="fdm-status-na">—</span>'
            ];
            return $return_array ? $result : $result['html'];
        }

        if ($compact) {
            $formatted = number_format($scraped);
        } else {
            $formatted = number_format($scraped) . '/' . number_format($expected);
        }

        // Complete: >= 95% coverage
        if ($scraped >= $expected * 0.95) {
            $result = [
                'class' => 'fdm-cell-complete',
                'html' => '<span class="fdm-status-complete">✓</span> ' . $formatted
            ];
            return $return_array ? $result : $result['html'];
        }

        // Missing: 0 scraped but expected > 0
        if ($scraped === 0) {
            $result = [
                'class' => 'fdm-cell-missing',
                'html' => '<span class="fdm-status-missing">✗</span> ' . $formatted
            ];
            return $return_array ? $result : $result['html'];
        }

        // Partial: some scraped but < 95%
        $result = [
            'class' => 'fdm-cell-partial',
            'html' => '<span class="fdm-status-partial">~</span> ' . $formatted
        ];
        return $return_array ? $result : $result['html'];
    }

    /**
     * Render a table cell with proper class and content
     */
    private function render_cell($scraped, $expected) {
        $cell = $this->format_status_cell($scraped, $expected, false, true);
        return '<td class="' . $cell['class'] . '">' . $cell['html'] . '</td>';
    }

    /**
     * Main View - Year Overview
     * Shows year-by-year status with columns for ALL data types
     */
    private function render_main_view() {
        echo '<div class="fdm-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">';
        echo '<h1 class="wp-heading-inline" style="margin: 0;">ESPN Data Status</h1>';
        $this->render_prober_inline();
        echo '</div>';
        echo '<p class="fdm-subheader">Each cell shows: <strong>scraped / available</strong> (what we have vs what ESPN has). Click a year for league breakdown.</p>';

        // Show note if prober is incomplete
        $prober = $this->get_prober_progress();
        if (!$prober['is_complete']) {
            echo '<p style="color: #666; font-size: 12px; margin: -10px 0 15px 0;"><em>Note: Fixture counts only include leagues checked by the prober (' . $prober['probed'] . '/' . $prober['total'] . '). Run prober to completion for accurate totals.</em></p>';
        }

        // Legend with color boxes
        echo '<div class="fdm-legend">';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#e8f5e9;border:1px solid #c8e6c9;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-complete">✓</span> Complete</div>';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#fff8e1;border:1px solid #ffecb3;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-partial">~</span> Partial</div>';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#ffebee;border:1px solid #ffcdd2;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-missing">✗</span> Needs scraping</div>';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#f5f5f5;border:1px solid #e0e0e0;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-na">—</span> N/A (ESPN doesn\'t have)</div>';
        echo '</div>';


        // Table with horizontal scroll
        echo '<div class="fdm-table-container">';
        echo '<table class="fdm-status-table" id="fdm-status-table">';
        echo '<thead>';
        // Group headers
        echo '<tr>';
        echo '<th rowspan="2" style="text-align:left; padding-left:15px;">Year</th>';
        echo '<th colspan="7" class="group-header">Match Data</th>';
        echo '<th colspan="5" class="group-header">Reference Data</th>';
        echo '</tr>';
        // Column headers
        echo '<tr>';
        echo '<th>Fixtures</th><th>Lineups</th><th>Commentary</th><th>Key Events</th><th>Plays</th><th>Player Stats</th><th>Team Stats</th>';
        echo '<th>Teams</th><th>Players</th><th>Standings</th><th>Rosters</th><th>Venues</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody id="fdm-table-body">';
        echo '<tr><td colspan="13" style="text-align:center; padding:20px;">Loading data...</td></tr>';
        echo '</tbody></table>';
        echo '</div>';

        // Auto-refresh script
        ?>
        <script>
        jQuery(document).ready(function($) {
            function refreshDashboard() {
                $.post(ajaxurl, { action: 'fdm_dashboard_refresh' }, function(response) {
                    if (response.success) {
                        $('#fdm-table-body').html(response.data.html);
                    }
                });
            }
            refreshDashboard();
            setInterval(refreshDashboard, 10000);
        });
        </script>
        <?php
    }

    /**
     * Leagues View - All leagues with years as columns
     * Shows which years have data for each league
     */
    private function render_leagues_view() {
        echo '<div class="fdm-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">';
        echo '<h1 class="wp-heading-inline" style="margin: 0;">ESPN Data Status - By League</h1>';
        $this->render_prober_inline();
        echo '</div>';
        echo '<p class="fdm-subheader">Each cell shows fixture count: <strong>scraped / available</strong>. Leagues as rows, years as columns.</p>';

        // Legend with color boxes
        echo '<div class="fdm-legend">';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#e8f5e9;border:1px solid #c8e6c9;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-complete">✓</span> Complete</div>';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#fff8e1;border:1px solid #ffecb3;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-partial">~</span> Partial</div>';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#ffebee;border:1px solid #ffcdd2;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-missing">✗</span> Needs scraping</div>';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#f5f5f5;border:1px solid #e0e0e0;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-na">—</span> N/A (not available)</div>';
        echo '</div>';

        // Years for columns (2024 down to 2001)
        $years = range(2024, 2001);

        // Table with horizontal scroll
        echo '<div class="fdm-table-container">';
        echo '<table class="fdm-status-table" id="fdm-leagues-table">';
        echo '<thead><tr>';
        echo '<th style="text-align:left; padding-left:15px; position:sticky; left:0; z-index:2; background:#1d2327 !important;">League</th>';
        foreach ($years as $year) {
            echo '<th>' . $year . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody id="fdm-leagues-body">';
        echo '<tr><td colspan="' . (count($years) + 1) . '" style="text-align:center; padding:20px;">Loading data...</td></tr>';
        echo '</tbody></table>';
        echo '</div>';

        // Auto-refresh script
        ?>
        <script>
        jQuery(document).ready(function($) {
            function refreshLeagues() {
                $.post(ajaxurl, { action: 'fdm_leagues_refresh' }, function(response) {
                    if (response.success) {
                        $('#fdm-leagues-body').html(response.data.html);
                    }
                });
            }
            refreshLeagues();
            setInterval(refreshLeagues, 10000);
        });
        </script>
        <?php
    }

    /**
     * Detail View - League breakdown for a specific year
     */
    private function render_detail_view($year) {
        $e_db = $this->get_e_db();

        echo '<div class="fdm-header">';
        echo '<h1 class="wp-heading-inline">' . esc_html($year) . ' Data Status</h1>';
        echo '<a href="' . admin_url('admin.php?page=fdm-data-status') . '" class="page-title-action">← Back to Overview</a>';
        echo '</div>';
        echo '<p class="fdm-subheader">League-by-league breakdown for season ' . esc_html($year) . '</p>';

        // Legend with color boxes
        echo '<div class="fdm-legend">';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#e8f5e9;border:1px solid #c8e6c9;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-complete">✓</span> Complete</div>';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#fff8e1;border:1px solid #ffecb3;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-partial">~</span> Partial</div>';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#ffebee;border:1px solid #ffcdd2;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-missing">✗</span> Needs scraping</div>';
        echo '<div class="fdm-legend-item"><span style="display:inline-block;width:16px;height:16px;background:#f5f5f5;border:1px solid #e0e0e0;margin-right:4px;vertical-align:middle;"></span> <span class="fdm-status-na">—</span> N/A</div>';
        echo '</div>';

        // Get expected counts from espn_availability
        $expected_data = $e_db->get_results($e_db->prepare("
            SELECT * FROM espn_availability WHERE season_year = %d ORDER BY league_code ASC
        ", $year), ARRAY_A);

        $expected = [];
        foreach ($expected_data as $row) {
            $expected[$row['league_code']] = $row;
        }

        // Get scraped counts from all tables
        $fixtures_map = $this->get_count_by_league($e_db, 'fixtures', 'eventid', $year);
        $lineups_map = $this->get_joined_count_by_league($e_db, 'lineups', $year);
        $commentary_map = $this->get_joined_count_by_league($e_db, 'commentary', $year);
        $keyevents_map = $this->get_joined_count_by_league($e_db, 'keyEvents', $year);
        $plays_map = $this->get_joined_count_by_league($e_db, 'plays', $year);
        $playerstats_map = $this->get_player_stats_count_by_league($e_db, $year);
        $teamstats_map = $this->get_joined_count_by_league($e_db, 'teamStats', $year);
        $teams_map = $this->get_teams_count_by_league($e_db, $year);
        $standings_map = $this->get_standings_count_by_league($e_db, $year);
        $rosters_map = $this->get_rosters_count_by_league($e_db, $year);

        // Get all leagues
        $all_leagues = array_unique(array_merge(
            array_keys($expected),
            array_keys($fixtures_map)
        ));
        sort($all_leagues);

        // Render table
        echo '<div class="fdm-table-container">';
        echo '<table class="fdm-status-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th rowspan="2" style="text-align:left; padding-left:15px;">League</th>';
        echo '<th colspan="7" class="group-header">Match Data</th>';
        echo '<th colspan="4" class="group-header">Reference Data</th>';
        echo '</tr>';
        echo '<tr>';
        echo '<th>Fixtures</th><th>Lineups</th><th>Commentary</th><th>Key Events</th><th>Plays</th><th>Player Stats</th><th>Team Stats</th>';
        echo '<th>Teams</th><th>Standings</th><th>Rosters</th><th>Venues</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if (empty($all_leagues)) {
            echo '<tr><td colspan="12" style="text-align:center; padding:20px;">No data found for ' . esc_html($year) . '</td></tr>';
        } else {
            foreach ($all_leagues as $league) {
                $exp = isset($expected[$league]) ? $expected[$league] : [];
                $fix_exp = isset($exp['fixtures_available']) ? intval($exp['fixtures_available']) : 0;

                // For match-level data, expected = fixtures count if available flag is set
                $lin_exp = !empty($exp['lineups_available']) ? $fix_exp : null;
                $com_exp = !empty($exp['commentary_available']) ? $fix_exp : null;
                $key_exp = !empty($exp['key_events_available']) ? $fix_exp : null;
                $ply_exp = !empty($exp['plays_available']) ? $fix_exp : null;
                $pst_exp = !empty($exp['player_stats_available']) ? $fix_exp : null;
                $tst_exp = !empty($exp['team_stats_available']) ? $fix_exp : null;
                $tea_exp = isset($exp['teams_available']) ? intval($exp['teams_available']) : null;
                $std_exp = !empty($exp['standings_available']) ? 1 : null;
                $ros_exp = !empty($exp['roster_available']) ? 1 : null;

                echo '<tr>';
                echo '<td class="year-cell">' . esc_html($league) . '</td>';
                echo $this->render_cell($fixtures_map[$league] ?? 0, $fix_exp);
                echo $this->render_cell($lineups_map[$league] ?? 0, $lin_exp);
                echo $this->render_cell($commentary_map[$league] ?? 0, $com_exp);
                echo $this->render_cell($keyevents_map[$league] ?? 0, $key_exp);
                echo $this->render_cell($plays_map[$league] ?? 0, $ply_exp);
                echo $this->render_cell($playerstats_map[$league] ?? 0, $pst_exp);
                echo $this->render_cell($teamstats_map[$league] ?? 0, $tst_exp);
                echo $this->render_cell($teams_map[$league] ?? 0, $tea_exp);
                echo $this->render_cell($standings_map[$league] ?? 0, $std_exp);
                echo $this->render_cell($rosters_map[$league] ?? 0, $ros_exp);
                echo $this->render_cell(0, null); // Venues - TODO
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Helper: Get count by league from a table with league_code column
     */
    private function get_count_by_league($e_db, $table, $id_col, $year) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT league_code, COUNT(DISTINCT {$id_col}) as cnt
            FROM {$table}
            WHERE season_year = %d AND league_code IS NOT NULL
            GROUP BY league_code
        ", $year), ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[$row['league_code']] = intval($row['cnt']);
        }
        return $map;
    }

    /**
     * Helper: Get count of matches with data in a joined table
     */
    private function get_joined_count_by_league($e_db, $table, $year) {
        // For teamStats, only count records with actual data (not empty zeros)
        $extra_where = '';
        if ($table === 'teamStats') {
            $extra_where = ' AND (t.possessionpct > 0 OR t.totalshots > 0 OR t.foulscommitted > 0)';
        }

        $results = $e_db->get_results($e_db->prepare("
            SELECT f.league_code, COUNT(DISTINCT t.eventid) as cnt
            FROM fixtures f
            INNER JOIN {$table} t ON t.eventid = f.eventid
            WHERE f.season_year = %d AND f.league_code IS NOT NULL{$extra_where}
            GROUP BY f.league_code
        ", $year), ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[$row['league_code']] = intval($row['cnt']);
        }
        return $map;
    }

    /**
     * Helper: Get teams count by league
     */
    private function get_teams_count_by_league($e_db, $year) {
        // Teams don't have season_year, count by league from fixtures
        $results = $e_db->get_results($e_db->prepare("
            SELECT f.league_code, COUNT(DISTINCT f.hometeamid) + COUNT(DISTINCT f.awayteamid) as cnt
            FROM fixtures f
            WHERE f.season_year = %d AND f.league_code IS NOT NULL
            GROUP BY f.league_code
        ", $year), ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            // Divide by 2 roughly since home and away overlap
            $map[$row['league_code']] = intval($row['cnt'] / 2);
        }
        return $map;
    }

    /**
     * Helper: Get standings count by league
     */
    private function get_standings_count_by_league($e_db, $year) {
        // standings table uses 'leagueid' (int), not 'leaguecode' (string)
        // We can't directly map to league_code without a lookup table
        // For now, count by leagueid and return empty map (standings aren't league-specific in current schema)
        $results = $e_db->get_results($e_db->prepare("
            SELECT leagueid, COUNT(*) as cnt
            FROM standings
            WHERE season = %d AND leagueid IS NOT NULL
            GROUP BY leagueid
        ", $year), ARRAY_A);

        // TODO: Map leagueid to league_code if mapping table exists
        $map = [];
        // Return empty for now - standings don't have league_code
        return $map;
    }

    /**
     * Helper: Get rosters count by league
     */
    private function get_rosters_count_by_league($e_db, $year) {
        // teamRoster table doesn't have leaguecode column
        // It has: season, seasonyear, teamid, athleteid, etc.
        // We can't map to league_code without a lookup
        // For now return empty map
        $map = [];
        return $map;
    }

    /**
     * Helper: Get player stats count by league
     * playerStats table has 'league' column (text) which stores league_code
     */
    private function get_player_stats_count_by_league($e_db, $year) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT league as league_code, COUNT(DISTINCT athleteid) as cnt
            FROM playerStats
            WHERE season = %d AND league IS NOT NULL
            GROUP BY league
        ", $year), ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[$row['league_code']] = intval($row['cnt']);
        }
        return $map;
    }

    /**
     * AJAX handler for dashboard refresh
     * Returns HTML for the table body
     */
    public function ajax_refresh_data() {
        $e_db = $this->get_e_db();

        // Get all years from espn_availability
        $years_expected = $e_db->get_results("
            SELECT
                season_year,
                SUM(fixtures_available) as fix_exp,
                SUM(CASE WHEN lineups_available = 1 THEN fixtures_available ELSE 0 END) as lin_exp,
                SUM(CASE WHEN commentary_available = 1 THEN fixtures_available ELSE 0 END) as com_exp,
                SUM(CASE WHEN key_events_available = 1 THEN fixtures_available ELSE 0 END) as key_exp,
                SUM(CASE WHEN plays_available = 1 THEN fixtures_available ELSE 0 END) as ply_exp,
                SUM(CASE WHEN player_stats_available = 1 THEN fixtures_available ELSE 0 END) as pst_exp,
                SUM(CASE WHEN team_stats_available = 1 THEN fixtures_available ELSE 0 END) as tst_exp,
                SUM(teams_available) as tea_exp,
                SUM(players_available) as pla_exp,
                SUM(CASE WHEN standings_available = 1 THEN 1 ELSE 0 END) as std_exp,
                SUM(CASE WHEN roster_available = 1 THEN 1 ELSE 0 END) as ros_exp
            FROM espn_availability
            GROUP BY season_year
            ORDER BY season_year DESC
        ", ARRAY_A);

        $expected_by_year = [];
        foreach ($years_expected as $row) {
            $expected_by_year[intval($row['season_year'])] = $row;
        }

        // Get probed leagues per year (to filter scraped counts)
        $probed_leagues = $e_db->get_results("
            SELECT season_year, league_code FROM espn_availability
        ", ARRAY_A);
        $probed_by_year = [];
        foreach ($probed_leagues as $row) {
            $year = intval($row['season_year']);
            if (!isset($probed_by_year[$year])) {
                $probed_by_year[$year] = [];
            }
            $probed_by_year[$year][] = $row['league_code'];
        }

        // Get scraped counts by year (only for probed leagues)
        $fix_by_year = $this->get_count_by_year_probed($e_db, 'fixtures', 'eventid', $probed_by_year);
        $lin_by_year = $this->get_joined_count_by_year($e_db, 'lineups');
        $com_by_year = $this->get_joined_count_by_year($e_db, 'commentary');
        $key_by_year = $this->get_joined_count_by_year($e_db, 'keyEvents');
        $ply_by_year = $this->get_joined_count_by_year($e_db, 'plays');
        $pst_by_year = $this->get_player_stats_count_by_year($e_db);
        $tst_by_year = $this->get_joined_count_by_year($e_db, 'teamStats');
        $tea_by_year = $this->get_teams_count_by_year($e_db);
        $pla_by_year = $this->get_players_count_by_year($e_db);
        $std_by_year = $this->get_standings_count_by_year($e_db);
        $ros_by_year = $this->get_rosters_count_by_year($e_db);
        $ven_by_year = $this->get_venues_count_by_year($e_db);

        // Combine all years
        $all_years = array_unique(array_merge(
            array_keys($expected_by_year),
            array_keys($fix_by_year)
        ));
        rsort($all_years);

        ob_start();

        if (empty($all_years)) {
            echo '<tr><td colspan="13" style="text-align:center; padding:20px;">No data available. Run the ESPN availability prober to populate expected counts.</td></tr>';
        } else {
            foreach ($all_years as $year) {
                $exp = isset($expected_by_year[$year]) ? $expected_by_year[$year] : [];

                $url = admin_url('admin.php?page=fdm-data-status&season=' . $year);

                echo '<tr>';
                echo '<td class="year-cell"><a href="' . esc_url($url) . '">' . esc_html($year) . '</a></td>';
                echo $this->render_cell($fix_by_year[$year] ?? 0, $exp['fix_exp'] ?? 0);
                echo $this->render_cell($lin_by_year[$year] ?? 0, $exp['lin_exp'] ?? null);
                echo $this->render_cell($com_by_year[$year] ?? 0, $exp['com_exp'] ?? null);
                echo $this->render_cell($key_by_year[$year] ?? 0, $exp['key_exp'] ?? null);
                echo $this->render_cell($ply_by_year[$year] ?? 0, $exp['ply_exp'] ?? null);
                echo $this->render_cell($pst_by_year[$year] ?? 0, $exp['pst_exp'] ?? null);
                echo $this->render_cell($tst_by_year[$year] ?? 0, $exp['tst_exp'] ?? null);
                echo $this->render_cell($tea_by_year[$year] ?? 0, $exp['tea_exp'] ?? null);
                echo $this->render_cell($pla_by_year[$year] ?? 0, $exp['pla_exp'] ?? null);
                echo $this->render_cell($std_by_year[$year] ?? 0, $exp['std_exp'] ?? null);
                echo $this->render_cell($ros_by_year[$year] ?? 0, $exp['ros_exp'] ?? null);
                echo $this->render_cell($ven_by_year[$year] ?? 0, null);
                echo '</tr>';
            }
        }

        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    // === Year-level count helpers ===

    private function get_count_by_year($e_db, $table, $id_col) {
        $results = $e_db->get_results("
            SELECT season_year, COUNT(DISTINCT {$id_col}) as cnt
            FROM {$table}
            WHERE season_year IS NOT NULL
            GROUP BY season_year
        ", ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season_year'])] = intval($row['cnt']);
        }
        return $map;
    }

    /**
     * Get count by year, but only for leagues that have been probed
     */
    private function get_count_by_year_probed($e_db, $table, $id_col, $probed_by_year) {
        $map = [];
        foreach ($probed_by_year as $year => $leagues) {
            if (empty($leagues)) continue;

            $placeholders = implode(',', array_fill(0, count($leagues), '%s'));
            $query = $e_db->prepare(
                "SELECT COUNT(DISTINCT {$id_col}) as cnt FROM {$table} WHERE season_year = %d AND league_code IN ({$placeholders})",
                array_merge([$year], $leagues)
            );
            $count = $e_db->get_var($query);
            $map[$year] = intval($count);
        }
        return $map;
    }

    private function get_joined_count_by_year($e_db, $table) {
        // For teamStats, only count records with actual data (not empty zeros)
        $extra_where = '';
        if ($table === 'teamStats') {
            $extra_where = ' AND (t.possessionpct > 0 OR t.totalshots > 0 OR t.foulscommitted > 0)';
        }

        $results = $e_db->get_results("
            SELECT f.season_year, COUNT(DISTINCT t.eventid) as cnt
            FROM fixtures f
            INNER JOIN {$table} t ON t.eventid = f.eventid
            WHERE f.season_year IS NOT NULL{$extra_where}
            GROUP BY f.season_year
        ", ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season_year'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_teams_count_by_year($e_db) {
        $results = $e_db->get_results("
            SELECT season_year, COUNT(DISTINCT hometeamid) + COUNT(DISTINCT awayteamid) as cnt
            FROM fixtures
            WHERE season_year IS NOT NULL
            GROUP BY season_year
        ", ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season_year'])] = intval($row['cnt'] / 2);
        }
        return $map;
    }

    private function get_players_count_by_year($e_db) {
        // Count distinct players from lineups per year
        $results = $e_db->get_results("
            SELECT f.season_year, COUNT(DISTINCT l.athleteid) as cnt
            FROM fixtures f
            INNER JOIN lineups l ON l.eventid = f.eventid
            WHERE f.season_year IS NOT NULL
            GROUP BY f.season_year
        ", ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season_year'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_standings_count_by_year($e_db) {
        // standings table uses 'season' and 'leagueid', not 'seasonyear' and 'leaguecode'
        $results = $e_db->get_results("
            SELECT season, COUNT(DISTINCT leagueid) as cnt
            FROM standings
            WHERE season IS NOT NULL
            GROUP BY season
        ", ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_rosters_count_by_year($e_db) {
        // teamRoster uses 'season' or 'seasonyear', and has 'teamid' but no 'leaguecode'
        // Count distinct team-season combinations
        $results = $e_db->get_results("
            SELECT COALESCE(seasonyear, season) as yr, COUNT(DISTINCT teamid) as cnt
            FROM teamRoster
            WHERE seasonyear IS NOT NULL OR season IS NOT NULL
            GROUP BY COALESCE(seasonyear, season)
        ", ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            if ($row['yr']) {
                $map[intval($row['yr'])] = intval($row['cnt']);
            }
        }
        return $map;
    }

    private function get_player_stats_count_by_year($e_db) {
        // playerStats table stores cumulative stats per player per season (no eventid)
        // Count distinct players with stats per year
        $results = $e_db->get_results("
            SELECT season, COUNT(DISTINCT athleteid) as cnt
            FROM playerStats
            WHERE season IS NOT NULL
            GROUP BY season
        ", ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_venues_count_by_year($e_db) {
        // Venues don't have year - count total
        $count = $e_db->get_var("SELECT COUNT(*) FROM venues");
        return []; // Return empty - venues aren't year-specific
    }

    /**
     * AJAX handler for leagues view refresh
     * Returns HTML showing all leagues with years as columns
     */
    public function ajax_leagues_refresh() {
        $e_db = $this->get_e_db();
        $years = range(2024, 2001);

        // Get ALL configured leagues from league_permissions
        $all_leagues_data = $e_db->get_col("
            SELECT DISTINCT league_code FROM league_permissions ORDER BY league_code
        ");

        // If no leagues in permissions, fall back to what we have
        if (empty($all_leagues_data)) {
            $all_leagues_data = [];
        }

        // Get all expected data from espn_availability (league × year → fixtures_available)
        $expected_data = $e_db->get_results("
            SELECT league_code, season_year, fixtures_available
            FROM espn_availability
            ORDER BY league_code, season_year DESC
        ", ARRAY_A);

        $expected = [];
        foreach ($expected_data as $row) {
            $league = $row['league_code'];
            $year = intval($row['season_year']);
            if (!isset($expected[$league])) {
                $expected[$league] = [];
            }
            $expected[$league][$year] = intval($row['fixtures_available']);
        }

        // Get all scraped fixture counts (league × year → count)
        $scraped_data = $e_db->get_results("
            SELECT league_code, season_year, COUNT(DISTINCT eventid) as cnt
            FROM fixtures
            WHERE league_code IS NOT NULL AND season_year IS NOT NULL
            GROUP BY league_code, season_year
        ", ARRAY_A);

        $scraped = [];
        foreach ($scraped_data as $row) {
            $league = $row['league_code'];
            $year = intval($row['season_year']);
            if (!isset($scraped[$league])) {
                $scraped[$league] = [];
            }
            $scraped[$league][$year] = intval($row['cnt']);
        }

        // Combine all leagues: configured + any with data
        $all_leagues = array_unique(array_merge(
            $all_leagues_data,
            array_keys($expected),
            array_keys($scraped)
        ));
        sort($all_leagues);

        ob_start();

        if (empty($all_leagues)) {
            echo '<tr><td colspan="' . (count($years) + 1) . '" style="text-align:center; padding:20px;">No leagues configured. Check league_permissions table.</td></tr>';
        } else {
            foreach ($all_leagues as $league) {
                echo '<tr>';
                echo '<td class="year-cell">' . esc_html($league) . '</td>';

                foreach ($years as $year) {
                    $scr = isset($scraped[$league][$year]) ? $scraped[$league][$year] : 0;

                    // If we have expected data from espn_availability, use it
                    // Otherwise show as "unknown" (null) until prober runs
                    if (isset($expected[$league][$year])) {
                        $expected_val = $expected[$league][$year];
                    } else {
                        // No availability data yet - unknown state
                        $expected_val = null;
                    }

                    echo $this->render_cell($scr, $expected_val);
                }

                echo '</tr>';
            }
        }

        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX handler for prober progress
     */
    public function ajax_prober_progress() {
        $e_db = $this->get_e_db();

        $probed = intval($e_db->get_var("SELECT COUNT(DISTINCT league_code) FROM espn_availability"));
        // Use probed count as total once complete, otherwise estimate
        $total = $probed > 0 ? $probed : 220;

        wp_send_json_success([
            'probed' => $probed,
            'total' => $total
        ]);
    }

    /**
     * AJAX handler for scraper progress
     */
    public function ajax_scraper_progress() {
        $progress = $this->get_scraper_progress();
        wp_send_json_success([
            'completed' => $progress['completed'],
            'total' => $progress['total']
        ]);
    }
}
