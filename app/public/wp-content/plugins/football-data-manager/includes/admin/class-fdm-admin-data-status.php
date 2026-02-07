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
        add_action('wp_ajax_fdm_league_detail', [$this, 'ajax_league_detail']);
        add_action('wp_ajax_fdm_update_verification_status', [$this, 'ajax_update_verification_status']);
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
            } elseif ($tab === 'verification') {
                $this->render_verification_tab();
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
        $progress_file = '/tmp/probe-progress.json';
        $log_file = '/tmp/probe-availability.log';

        // Default values
        $current = 0;
        $total = 0;
        $status = 'idle';
        $is_running = false;

        // First check if log file was recently modified (prober writes to it frequently)
        if (file_exists($log_file)) {
            $mtime = filemtime($log_file);
            clearstatcache(true, $log_file);
            if ($mtime && (time() - $mtime) < 120) {
                $is_running = true;
            }
        }

        // Read progress from JSON file written by prober
        if (file_exists($progress_file)) {
            $data = json_decode(file_get_contents($progress_file), true);
            if ($data) {
                $current = intval($data['current'] ?? 0);
                $total = intval($data['total'] ?? 0);
                $status = $data['status'] ?? 'idle';

                // If status is complete/error, respect that even if log file is recent
                if (in_array($status, ['complete', 'error'])) {
                    $is_running = false;
                }
            }
        }

        // If prober is running but we have progress data, use it
        // If prober is running but no progress yet (starting phase), show that
        if ($is_running && $total === 0) {
            // Still in startup phase - show as running with unknown total
            return [
                'probed' => 0,
                'total' => 224, // Estimate
                'percentage' => 0,
                'is_complete' => false,
                'is_running' => true
            ];
        }

        // If not running and no progress file data, check DB for completed state
        if (!$is_running && $total === 0) {
            $e_db = $this->get_e_db();
            $probed_count = intval($e_db->get_var("SELECT COUNT(DISTINCT league_code) FROM espn_availability"));
            if ($probed_count > 0) {
                $current = $probed_count;
                $total = $probed_count;
                $status = 'complete';
            }
        }

        $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
        $is_complete = ($status === 'complete') || ($current >= $total && $total > 0 && !$is_running);

        return [
            'probed' => $current,
            'total' => $total,
            'percentage' => $percentage,
            'is_complete' => $is_complete,
            'is_running' => $is_running
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
        // Get pending verification count for badge
        $verification_count = $this->get_verification_pending_count();

        $tabs = [
            'by-year' => ['label' => 'By Year', 'count' => 0],
            'by-league' => ['label' => 'By League', 'count' => 0],
            'verification' => ['label' => 'Manual Verification', 'count' => $verification_count],
        ];

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab_key => $tab_data) {
            $active = ($active_tab === $tab_key) ? ' nav-tab-active' : '';
            $url = admin_url('admin.php?page=fdm-data-status&tab=' . $tab_key);
            $count_badge = $tab_data['count'] > 0 ? ' <span class="count" style="background:#ca4a1f; color:#fff; padding:2px 6px; border-radius:10px; font-size:11px; margin-left:4px;">' . number_format($tab_data['count']) . '</span>' : '';
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . $active . '">' . esc_html($tab_data['label']) . $count_badge . '</a>';
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
        echo '<th colspan="7" class="group-header">Reference Data</th>';
        echo '</tr>';
        // Column headers
        echo '<tr>';
        echo '<th>Fixtures</th><th>Lineups</th><th>Commentary</th><th>Key Events</th><th>Plays</th><th>Player Stats</th><th>Team Stats</th>';
        echo '<th>Teams</th><th>Players</th><th>Standings</th><th>Rosters</th><th>Venues</th><th>Transfers</th><th>Season Stats</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody id="fdm-table-body">';
        echo '<tr><td colspan="15" style="text-align:center; padding:20px;">Loading data...</td></tr>';
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

        // Modal for league detail
        ?>
        <div id="fdm-league-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:100000;">
            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:8px; max-width:95%; max-height:90%; overflow:auto; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <div style="padding:20px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:#fff; z-index:1;">
                    <h2 id="fdm-modal-title" style="margin:0;">League Detail</h2>
                    <button type="button" id="fdm-modal-close" style="background:none; border:none; font-size:24px; cursor:pointer; padding:0 10px;">&times;</button>
                </div>
                <div id="fdm-modal-content" style="padding:20px; min-width:800px;">
                    Loading...
                </div>
            </div>
        </div>

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

            // League click handler - show modal
            $(document).on('click', '.fdm-league-link', function(e) {
                e.preventDefault();
                var league = $(this).data('league');
                var name = $(this).text();
                $('#fdm-modal-title').text(name + ' (' + league + ')');
                $('#fdm-modal-content').html('<p style="text-align:center; padding:40px;">Loading...</p>');
                $('#fdm-league-modal').show();

                $.post(ajaxurl, { action: 'fdm_league_detail', league: league }, function(response) {
                    if (response.success) {
                        $('#fdm-modal-content').html(response.data.html);
                    } else {
                        $('#fdm-modal-content').html('<p style="color:red;">Error loading data</p>');
                    }
                });
            });

            // Close modal
            $('#fdm-modal-close, #fdm-league-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#fdm-league-modal').hide();
                }
            });

            // ESC to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('#fdm-league-modal').hide();
                }
            });
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

        // Get expected counts from espn_availability (excluding women's leagues)
        $expected_data = $e_db->get_results($e_db->prepare("
            SELECT * FROM espn_availability WHERE season_year = %d
            AND league_code NOT LIKE '%%.w.%%'
            AND league_code NOT LIKE '%%nwsl%%'
            AND league_code NOT LIKE '%%wchampions%%'
            ORDER BY league_code ASC
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
        $players_map = $this->get_players_count_by_league($e_db, $year);
        $standings_map = $this->get_standings_count_by_league($e_db, $year);
        $rosters_map = $this->get_rosters_count_by_league($e_db, $year);
        $venues_map = $this->get_venues_count_by_league($e_db, $year);
        $transfers_map = $this->get_transfers_count_by_league($e_db, $year);
        $season_stats_map = $this->get_season_stats_count_by_league($e_db, $year);

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
        echo '<th colspan="7" class="group-header">Reference Data</th>';
        echo '</tr>';
        echo '<tr>';
        echo '<th>Fixtures</th><th>Lineups</th><th>Commentary</th><th>Key Events</th><th>Plays</th><th>Player Stats</th><th>Team Stats</th>';
        echo '<th>Teams</th><th>Players</th><th>Standings</th><th>Rosters</th><th>Venues</th><th>Transfers</th><th>Season Stats</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if (empty($all_leagues)) {
            echo '<tr><td colspan="15" style="text-align:center; padding:20px;">No data found for ' . esc_html($year) . '</td></tr>';
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
                $pla_exp = isset($exp['players_available']) ? intval($exp['players_available']) : null;
                $std_exp = !empty($exp['standings_available']) ? 1 : null;
                $ros_exp = !empty($exp['roster_available']) ? 1 : null;
                $ven_exp = !empty($exp['venues_available']) ? $fix_exp : null;
                $tra_exp = isset($exp['transfers_available']) ? intval($exp['transfers_available']) : null;
                $sst_exp = !empty($exp['season_stats_available']) ? 1 : null;

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
                echo $this->render_cell($players_map[$league] ?? 0, $pla_exp);
                echo $this->render_cell($standings_map[$league] ?? 0, $std_exp);
                echo $this->render_cell($rosters_map[$league] ?? 0, $ros_exp);
                echo $this->render_cell($venues_map[$league] ?? 0, $ven_exp);
                echo $this->render_cell($transfers_map[$league] ?? 0, $tra_exp);
                echo $this->render_cell($season_stats_map[$league] ?? 0, $sst_exp);
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
     * Helper: Get players count by league (from lineups)
     */
    private function get_players_count_by_league($e_db, $year) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT f.league_code, COUNT(DISTINCT l.athleteid) as cnt
            FROM fixtures f
            INNER JOIN lineups l ON l.eventid = f.eventid
            WHERE f.season_year = %d AND f.league_code IS NOT NULL
            GROUP BY f.league_code
        ", $year), ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[$row['league_code']] = intval($row['cnt']);
        }
        return $map;
    }

    /**
     * Helper: Get venues count by league
     */
    private function get_venues_count_by_league($e_db, $year) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT f.league_code, COUNT(DISTINCT f.venueid) as cnt
            FROM fixtures f
            WHERE f.season_year = %d AND f.league_code IS NOT NULL AND f.venueid IS NOT NULL
            GROUP BY f.league_code
        ", $year), ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[$row['league_code']] = intval($row['cnt']);
        }
        return $map;
    }

    /**
     * Helper: Get transfers count by league
     */
    private function get_transfers_count_by_league($e_db, $year) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT league as league_code, COUNT(*) as cnt
            FROM transfers
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
     * Helper: Get season stats count by league
     */
    private function get_season_stats_count_by_league($e_db, $year) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT league as league_code, COUNT(DISTINCT player_id) as cnt
            FROM season_player_stats
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

        // Get all years from espn_availability (excluding women's leagues)
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
                SUM(CASE WHEN roster_available = 1 THEN 1 ELSE 0 END) as ros_exp,
                SUM(CASE WHEN venues_available = 1 THEN fixtures_available ELSE 0 END) as ven_exp,
                SUM(COALESCE(transfers_available, 0)) as tra_exp,
                SUM(CASE WHEN season_stats_available = 1 THEN 1 ELSE 0 END) as sst_exp
            FROM espn_availability
            WHERE league_code NOT LIKE '%.w.%'
            AND league_code NOT LIKE '%nwsl%'
            AND league_code NOT LIKE '%wchampions%'
            GROUP BY season_year
            ORDER BY season_year DESC
        ", ARRAY_A);

        $expected_by_year = [];
        foreach ($years_expected as $row) {
            $expected_by_year[intval($row['season_year'])] = $row;
        }

        // Get probed leagues per year (to filter scraped counts), excluding women's leagues
        $probed_leagues = $e_db->get_results("
            SELECT season_year, league_code FROM espn_availability
            WHERE league_code NOT LIKE '%.w.%'
            AND league_code NOT LIKE '%nwsl%'
            AND league_code NOT LIKE '%wchampions%'
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
        $tra_by_year = $this->get_transfers_count_by_year($e_db);
        $sst_by_year = $this->get_season_stats_count_by_year($e_db);

        // Always show years 2001-2025 (descending)
        $all_years = range(2025, 2001);

        ob_start();

        {
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
                echo $this->render_cell($ven_by_year[$year] ?? 0, $exp['ven_exp'] ?? null);
                echo $this->render_cell($tra_by_year[$year] ?? 0, $exp['tra_exp'] ?? null);
                echo $this->render_cell($sst_by_year[$year] ?? 0, $exp['sst_exp'] ?? null);
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
        // Venues don't have year - count distinct venues from fixtures per year
        $results = $e_db->get_results("
            SELECT f.season_year, COUNT(DISTINCT f.venueid) as cnt
            FROM fixtures f
            WHERE f.season_year IS NOT NULL AND f.venueid IS NOT NULL
            GROUP BY f.season_year
        ", ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season_year'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_transfers_count_by_year($e_db) {
        // transfers table has 'season' column (e.g., "2024")
        $results = $e_db->get_results("
            SELECT season, COUNT(*) as cnt
            FROM transfers
            WHERE season IS NOT NULL
            GROUP BY season
        ", ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_season_stats_count_by_year($e_db) {
        // season_player_stats table has 'season' column
        $results = $e_db->get_results("
            SELECT season, COUNT(DISTINCT player_id) as cnt
            FROM season_player_stats
            WHERE season IS NOT NULL
            GROUP BY season
        ", ARRAY_A);

        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season'])] = intval($row['cnt']);
        }
        return $map;
    }

    /**
     * AJAX handler for leagues view refresh
     * Returns HTML showing all leagues with years as columns
     */
    public function ajax_leagues_refresh() {
        $e_db = $this->get_e_db();
        $years = range(2024, 2001);

        // Get ALL configured leagues from league_permissions (excluding women's leagues)
        $all_leagues_data = $e_db->get_col("
            SELECT DISTINCT league_code FROM league_permissions
            WHERE league_code NOT LIKE '%.w.%'
            AND league_code NOT LIKE '%nwsl%'
            AND league_code NOT LIKE '%wchampions%'
            ORDER BY league_code
        ");

        // If no leagues in permissions, fall back to what we have
        if (empty($all_leagues_data)) {
            $all_leagues_data = [];
        }

        // Get all expected data from espn_availability (league × year → fixtures_available)
        // Excluding women's leagues
        $expected_data = $e_db->get_results("
            SELECT league_code, season_year, fixtures_available
            FROM espn_availability
            WHERE league_code NOT LIKE '%.w.%'
            AND league_code NOT LIKE '%nwsl%'
            AND league_code NOT LIKE '%wchampions%'
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
        // Excluding women's leagues
        $scraped_data = $e_db->get_results("
            SELECT league_code, season_year, COUNT(DISTINCT eventid) as cnt
            FROM fixtures
            WHERE league_code IS NOT NULL AND season_year IS NOT NULL
            AND league_code NOT LIKE '%.w.%'
            AND league_code NOT LIKE '%nwsl%'
            AND league_code NOT LIKE '%wchampions%'
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
                echo '<td class="year-cell"><a href="#" class="fdm-league-link" data-league="' . esc_attr($league) . '">' . esc_html($league) . '</a></td>';

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
        $progress = $this->get_prober_progress();
        wp_send_json_success([
            'probed' => $progress['probed'],
            'total' => $progress['total'],
            'is_running' => $progress['is_running'],
            'is_complete' => $progress['is_complete']
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

    /**
     * AJAX handler for league detail popup
     * Shows all 14 data types for a single league across all years
     */
    public function ajax_league_detail() {
        $league = isset($_POST['league']) ? sanitize_text_field($_POST['league']) : '';
        if (empty($league)) {
            wp_send_json_error('No league specified');
        }

        $e_db = $this->get_e_db();
        $years = range(2025, 2001);

        // Get expected data for this league
        $expected_data = $e_db->get_results($e_db->prepare("
            SELECT * FROM espn_availability WHERE league_code = %s ORDER BY season_year DESC
        ", $league), ARRAY_A);

        $expected = [];
        foreach ($expected_data as $row) {
            $expected[intval($row['season_year'])] = $row;
        }

        // Get scraped counts for this league
        $fix_map = $this->get_count_by_year_for_league($e_db, 'fixtures', 'eventid', $league);
        $lin_map = $this->get_joined_count_by_year_for_league($e_db, 'lineups', $league);
        $com_map = $this->get_joined_count_by_year_for_league($e_db, 'commentary', $league);
        $key_map = $this->get_joined_count_by_year_for_league($e_db, 'keyEvents', $league);
        $ply_map = $this->get_joined_count_by_year_for_league($e_db, 'plays', $league);
        $pst_map = $this->get_player_stats_count_by_year_for_league($e_db, $league);
        $tst_map = $this->get_joined_count_by_year_for_league($e_db, 'teamStats', $league);
        $tea_map = $this->get_teams_count_by_year_for_league($e_db, $league);
        $pla_map = $this->get_players_count_by_year_for_league($e_db, $league);
        $ven_map = $this->get_venues_count_by_year_for_league($e_db, $league);
        $tra_map = $this->get_transfers_count_by_year_for_league($e_db, $league);

        ob_start();
        ?>
        <table class="fdm-status-table" style="min-width:100%;">
            <thead>
                <tr>
                    <th rowspan="2" style="text-align:left; padding-left:15px;">Year</th>
                    <th colspan="7" class="group-header">Match Data</th>
                    <th colspan="5" class="group-header">Reference Data</th>
                </tr>
                <tr>
                    <th>Fixtures</th><th>Lineups</th><th>Commentary</th><th>Key Events</th><th>Plays</th><th>Player Stats</th><th>Team Stats</th>
                    <th>Teams</th><th>Players</th><th>Venues</th><th>Transfers</th><th>Standings</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($years as $year):
                $exp = isset($expected[$year]) ? $expected[$year] : [];
                $fix_exp = isset($exp['fixtures_available']) ? intval($exp['fixtures_available']) : null;

                // For match-level data, expected = fixtures count if available flag is set
                $lin_exp = !empty($exp['lineups_available']) ? $fix_exp : (!empty($fix_exp) ? 0 : null);
                $com_exp = !empty($exp['commentary_available']) ? $fix_exp : (!empty($fix_exp) ? 0 : null);
                $key_exp = !empty($exp['key_events_available']) ? $fix_exp : (!empty($fix_exp) ? 0 : null);
                $ply_exp = !empty($exp['plays_available']) ? $fix_exp : (!empty($fix_exp) ? 0 : null);
                $pst_exp = !empty($exp['player_stats_available']) ? $fix_exp : (!empty($fix_exp) ? 0 : null);
                $tst_exp = !empty($exp['team_stats_available']) ? $fix_exp : (!empty($fix_exp) ? 0 : null);
                $tea_exp = isset($exp['teams_available']) ? intval($exp['teams_available']) : null;
                $pla_exp = isset($exp['players_available']) ? intval($exp['players_available']) : null;
                $ven_exp = !empty($exp['venues_available']) ? $fix_exp : (!empty($fix_exp) ? 0 : null);
                $tra_exp = isset($exp['transfers_available']) ? intval($exp['transfers_available']) : null;
                $std_exp = !empty($exp['standings_available']) ? 1 : (!empty($fix_exp) ? 0 : null);
            ?>
                <tr>
                    <td class="year-cell" style="font-weight:bold;"><?php echo esc_html($year); ?></td>
                    <?php
                    echo $this->render_cell($fix_map[$year] ?? 0, $fix_exp);
                    echo $this->render_cell($lin_map[$year] ?? 0, $lin_exp);
                    echo $this->render_cell($com_map[$year] ?? 0, $com_exp);
                    echo $this->render_cell($key_map[$year] ?? 0, $key_exp);
                    echo $this->render_cell($ply_map[$year] ?? 0, $ply_exp);
                    echo $this->render_cell($pst_map[$year] ?? 0, $pst_exp);
                    echo $this->render_cell($tst_map[$year] ?? 0, $tst_exp);
                    echo $this->render_cell($tea_map[$year] ?? 0, $tea_exp);
                    echo $this->render_cell($pla_map[$year] ?? 0, $pla_exp);
                    echo $this->render_cell($ven_map[$year] ?? 0, $ven_exp);
                    echo $this->render_cell($tra_map[$year] ?? 0, $tra_exp);
                    echo $this->render_cell(0, $std_exp); // Standings - would need separate query
                    ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    // Helper methods for league-specific counts
    private function get_count_by_year_for_league($e_db, $table, $id_col, $league) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT season_year, COUNT(DISTINCT {$id_col}) as cnt
            FROM {$table}
            WHERE league_code = %s AND season_year IS NOT NULL
            GROUP BY season_year
        ", $league), ARRAY_A);
        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season_year'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_joined_count_by_year_for_league($e_db, $table, $league) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT f.season_year, COUNT(DISTINCT t.eventid) as cnt
            FROM fixtures f
            INNER JOIN {$table} t ON t.eventid = f.eventid
            WHERE f.league_code = %s AND f.season_year IS NOT NULL
            GROUP BY f.season_year
        ", $league), ARRAY_A);
        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season_year'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_player_stats_count_by_year_for_league($e_db, $league) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT season, COUNT(DISTINCT athleteid) as cnt
            FROM playerStats
            WHERE league = %s AND season IS NOT NULL
            GROUP BY season
        ", $league), ARRAY_A);
        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_teams_count_by_year_for_league($e_db, $league) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT season_year, (COUNT(DISTINCT hometeamid) + COUNT(DISTINCT awayteamid)) / 2 as cnt
            FROM fixtures
            WHERE league_code = %s AND season_year IS NOT NULL
            GROUP BY season_year
        ", $league), ARRAY_A);
        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season_year'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_players_count_by_year_for_league($e_db, $league) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT f.season_year, COUNT(DISTINCT l.athleteid) as cnt
            FROM fixtures f
            INNER JOIN lineups l ON l.eventid = f.eventid
            WHERE f.league_code = %s AND f.season_year IS NOT NULL
            GROUP BY f.season_year
        ", $league), ARRAY_A);
        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season_year'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_venues_count_by_year_for_league($e_db, $league) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT season_year, COUNT(DISTINCT venueid) as cnt
            FROM fixtures
            WHERE league_code = %s AND season_year IS NOT NULL AND venueid IS NOT NULL
            GROUP BY season_year
        ", $league), ARRAY_A);
        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season_year'])] = intval($row['cnt']);
        }
        return $map;
    }

    private function get_transfers_count_by_year_for_league($e_db, $league) {
        $results = $e_db->get_results($e_db->prepare("
            SELECT season, COUNT(*) as cnt
            FROM transfers
            WHERE league = %s AND season IS NOT NULL
            GROUP BY season
        ", $league), ARRAY_A);
        $map = [];
        foreach ($results as $row) {
            $map[intval($row['season'])] = intval($row['cnt']);
        }
        return $map;
    }

    /**
     * AJAX handler for updating verification status
     */
    public function ajax_update_verification_status() {
        $id = intval($_POST['id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        if (!$id || !in_array($status, ['verified_exists', 'verified_missing', 'skipped'])) {
            wp_send_json_error('Invalid parameters');
        }

        $e_db = $this->get_e_db();
        $result = $e_db->update(
            'espn_manual_verification',
            ['status' => $status, 'verified_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Update failed');
        }
    }

    /**
     * Get pending verification count
     */
    private function get_verification_pending_count() {
        $e_db = $this->get_e_db();
        return intval($e_db->get_var("SELECT COUNT(*) FROM espn_manual_verification WHERE status = 'pending'"));
    }

    /**
     * Render Manual Verification tab
     */
    private function render_verification_tab() {
        $e_db = $this->get_e_db();

        // Get counts by status
        $counts = $e_db->get_results("
            SELECT status, COUNT(*) as cnt
            FROM espn_manual_verification
            GROUP BY status
        ", ARRAY_A);

        $totals = ['pending' => 0, 'verified_exists' => 0, 'verified_missing' => 0, 'skipped' => 0];
        foreach ($counts as $row) {
            $totals[$row['status']] = intval($row['cnt']);
        }

        // Get counts by data type
        $data_types = $e_db->get_results("
            SELECT data_type, COUNT(*) as cnt
            FROM espn_manual_verification
            WHERE status = 'pending'
            GROUP BY data_type
            ORDER BY cnt DESC
        ", ARRAY_A);

        $filter_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';

        // Get pending items
        $where = "status = 'pending'";
        if ($filter_type) {
            $where .= $e_db->prepare(" AND data_type = %s", $filter_type);
        }
        $pending = $e_db->get_results("
            SELECT * FROM espn_manual_verification
            WHERE {$where}
            ORDER BY league_code, season_year, data_type
            LIMIT 200
        ", ARRAY_A);

        ?>
        <div class="fdm-header">
            <h1 class="wp-heading-inline">ESPN Manual Verification</h1>
        </div>
        <p class="fdm-subheader">Data the prober couldn't verify automatically. Click URLs to check manually, then mark status.</p>

        <!-- Summary Stats -->
        <div class="fdm-verification-stats" style="display:flex; gap:20px; margin:20px 0;">
            <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:15px 25px; text-align:center;">
                <span style="display:block; font-size:28px; font-weight:600; color:#996800;"><?php echo number_format($totals['pending']); ?></span>
                <span style="color:#666; font-size:12px; text-transform:uppercase;">Pending</span>
            </div>
            <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:15px 25px; text-align:center;">
                <span style="display:block; font-size:28px; font-weight:600; color:#00a32a;"><?php echo number_format($totals['verified_exists']); ?></span>
                <span style="color:#666; font-size:12px; text-transform:uppercase;">Verified Exists</span>
            </div>
            <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:15px 25px; text-align:center;">
                <span style="display:block; font-size:28px; font-weight:600; color:#d63638;"><?php echo number_format($totals['verified_missing']); ?></span>
                <span style="color:#666; font-size:12px; text-transform:uppercase;">Verified Missing</span>
            </div>
            <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:15px 25px; text-align:center;">
                <span style="display:block; font-size:28px; font-weight:600; color:#787c82;"><?php echo number_format($totals['skipped']); ?></span>
                <span style="color:#666; font-size:12px; text-transform:uppercase;">Skipped</span>
            </div>
        </div>

        <!-- Filter by Data Type -->
        <div style="margin:20px 0; padding:15px; background:#fff; border:1px solid #ccd0d4;">
            <strong>Filter by type:</strong>
            <a href="<?php echo esc_url(admin_url('admin.php?page=fdm-data-status&tab=verification')); ?>"
               class="button <?php echo empty($filter_type) ? 'button-primary' : ''; ?>">
                All (<?php echo number_format($totals['pending']); ?>)
            </a>
            <?php foreach ($data_types as $dt): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=fdm-data-status&tab=verification&type=' . $dt['data_type'])); ?>"
                   class="button <?php echo $filter_type === $dt['data_type'] ? 'button-primary' : ''; ?>">
                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $dt['data_type']))); ?>
                    (<?php echo number_format($dt['cnt']); ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Verification Table -->
        <?php if (empty($pending)): ?>
            <div style="padding:40px; text-align:center; background:#fff; border:1px solid #ccd0d4;">
                <p>No pending verifications<?php echo $filter_type ? ' for ' . esc_html($filter_type) : ''; ?>.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:200px;">League</th>
                        <th style="width:60px; text-align:center;">Year</th>
                        <th style="width:120px;">Data Type</th>
                        <th>URL to Check</th>
                        <th style="width:200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $item): ?>
                        <tr data-id="<?php echo esc_attr($item['id']); ?>">
                            <td>
                                <strong><?php echo esc_html($item['league_name'] ?: $item['league_code']); ?></strong>
                                <br><code style="font-size:11px;"><?php echo esc_html($item['league_code']); ?></code>
                            </td>
                            <td style="text-align:center;"><?php echo esc_html($item['season_year']); ?></td>
                            <td>
                                <span style="display:inline-block; padding:3px 8px; border-radius:3px; font-size:11px; font-weight:500; background:#e7f5ff; color:#0969da;">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $item['data_type']))); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($item['check_url']); ?>" target="_blank" style="font-family:monospace; font-size:11px; word-break:break-all;" title="API URL">
                                    API
                                </a>
                                <?php if (!empty($item['site_url'])): ?>
                                    &nbsp;|&nbsp;
                                    <a href="<?php echo esc_url($item['site_url']); ?>" target="_blank" style="font-size:11px;" title="ESPN Website">
                                        Website
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small button-primary fdm-verify-action" data-id="<?php echo esc_attr($item['id']); ?>" data-status="verified_exists">Exists</button>
                                <button type="button" class="button button-small fdm-verify-action" data-id="<?php echo esc_attr($item['id']); ?>" data-status="verified_missing">Missing</button>
                                <button type="button" class="button button-small fdm-verify-action" data-id="<?php echo esc_attr($item['id']); ?>" data-status="skipped">Skip</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <script>
        jQuery(document).ready(function($) {
            $('.fdm-verify-action').on('click', function() {
                var btn = $(this);
                var id = btn.data('id');
                var status = btn.data('status');
                var row = btn.closest('tr');

                btn.prop('disabled', true).text('...');

                $.post(ajaxurl, {
                    action: 'fdm_update_verification_status',
                    id: id,
                    status: status
                }, function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() { $(this).remove(); });
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
}
