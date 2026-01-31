#!/usr/bin/env php
<?php
/**
 * Master script for historical ESPN scrape.
 *
 * Scrapes all leagues for all years in order: 2024 -> 2023 -> ... -> 2001
 * (Recent years first, as they're most valuable)
 *
 * Usage:
 *   php run-historical-scrape.php                              Full scrape (all leagues, 2001-2024)
 *   php run-historical-scrape.php --year=2024                  Single year only
 *   php run-historical-scrape.php --years=2020-2024            Year range only
 *   php run-historical-scrape.php --league=eng.1               Single league only
 *   php run-historical-scrape.php --leagues=eng.1,esp.1,ger.1  Multiple leagues
 *   php run-historical-scrape.php --help                       Show this help
 *
 * @package FootyForums
 */

// Ensure we're running from CLI
if ( php_sapi_name() !== 'cli' ) {
    die( "This script can only be run from the command line.\n" );
}

// Parse command line options
$options = getopt( '', [ 'year::', 'years::', 'league::', 'leagues::', 'mode::', 'help' ] );

// Show help if requested
if ( isset( $options['help'] ) ) {
    echo <<<HELP
ESPN Historical Scrape
======================

Scrapes ESPN for historical football data and populates e_db.
Processes years in reverse order (most recent first).

Usage:
  php run-historical-scrape.php [options]

Options:
  --year=YYYY           Scrape single year only (e.g., --year=2024)
  --years=START-END     Scrape year range (e.g., --years=2020-2024)
  --league=CODE         Scrape single league (e.g., --league=eng.1)
  --leagues=CODE,CODE   Scrape multiple leagues (comma-separated)
  --mode=MODE           Scrape mode: full, deep_only, fixtures_only (default: full)
  --help                Show this help message

Examples:
  php run-historical-scrape.php                                    # Full scrape
  php run-historical-scrape.php --year=2024                        # 2024 only
  php run-historical-scrape.php --years=2020-2024                  # 2020-2024
  php run-historical-scrape.php --leagues=eng.1,esp.1,ger.1        # Priority leagues
  php run-historical-scrape.php --league=eng.1 --years=2020-2024   # Combine filters

Notes:
  - Full scrape (all leagues x all years) takes many hours
  - Rate limiting: 200ms between API requests
  - Progress is logged to stdout (redirect to file for background)
  - Scrape mode 'full' includes fixtures + lineups + commentary + stats
  - Scrape mode 'deep_only' adds lineups/commentary to existing fixtures
  - Scrape mode 'fixtures_only' only scrapes fixture list

HELP;
    exit( 0 );
}

// Bootstrap WordPress
$wp_load_path = dirname( __DIR__, 5 ) . '/wp-load.php';

if ( ! file_exists( $wp_load_path ) ) {
    // Try alternate path structure
    $wp_load_path = dirname( __DIR__, 4 ) . '/wp-load.php';
}

if ( file_exists( $wp_load_path ) ) {
    // Silence WordPress output during bootstrap
    ob_start();
    require_once $wp_load_path;
    ob_end_clean();
    echo "[" . date( 'Y-m-d H:i:s' ) . "] WordPress loaded successfully.\n";
} else {
    die( "Error: WordPress not found. Cannot proceed without database connection.\n" );
}

// Load the master datasource class
$master_path = dirname( __DIR__ ) . '/includes/class-fdm-e-master-datasource.php';

if ( ! file_exists( $master_path ) ) {
    die( "Error: Master datasource class not found at: $master_path\n" );
}

require_once $master_path;

// Create datasource instance
$datasource = new FDM_E_Master_Datasource();

// Configuration: All years in reverse order (recent first)
$all_years = range( 2024, 2001 );

// Get leagues from database (active leagues)
$all_leagues = $datasource->get_platinum_competitions();

if ( empty( $all_leagues ) ) {
    die( "Error: No active leagues found in league_permissions table.\n" );
}

// Scrape mode
$mode = isset( $options['mode'] ) ? $options['mode'] : 'full';
if ( ! in_array( $mode, [ 'full', 'deep_only', 'fixtures_only' ] ) ) {
    echo "Warning: Invalid mode '$mode', defaulting to 'full'\n";
    $mode = 'full';
}

// Apply filters from command line options

// Filter years
if ( ! empty( $options['year'] ) ) {
    $year = intval( $options['year'] );
    if ( $year >= 2001 && $year <= 2025 ) {
        $all_years = [ $year ];
    } else {
        die( "Error: Year must be between 2001 and 2025.\n" );
    }
}

if ( ! empty( $options['years'] ) ) {
    // Parse range like "2020-2024"
    $parts = explode( '-', $options['years'] );
    if ( count( $parts ) === 2 ) {
        $start = intval( trim( $parts[0] ) );
        $end = intval( trim( $parts[1] ) );
        if ( $start >= 2001 && $end <= 2025 && $start <= $end ) {
            // Range from end to start (recent first)
            $all_years = range( $end, $start );
        } else {
            die( "Error: Invalid year range. Years must be 2001-2025.\n" );
        }
    } else {
        die( "Error: Invalid years format. Use START-END (e.g., 2020-2024).\n" );
    }
}

// Filter leagues
if ( ! empty( $options['league'] ) ) {
    $league = trim( $options['league'] );
    if ( in_array( $league, $all_leagues ) ) {
        $all_leagues = [ $league ];
    } else {
        echo "Warning: League '$league' not in active leagues. Proceeding anyway.\n";
        $all_leagues = [ $league ];
    }
}

if ( ! empty( $options['leagues'] ) ) {
    $leagues_list = array_map( 'trim', explode( ',', $options['leagues'] ) );
    $all_leagues = $leagues_list;
}

// Rate limiting delay (200ms = 200000 microseconds)
$delay_ms = 200;

// Progress tracking
$total_combinations = count( $all_years ) * count( $all_leagues );
$completed = 0;
$start_time = time();
$total_fixtures = 0;
$errors = 0;

// Display configuration
echo "\n";
echo "==============================================\n";
echo "       ESPN HISTORICAL SCRAPE                 \n";
echo "==============================================\n";
echo "\n";
echo "Configuration:\n";
echo "  Years:   " . count( $all_years ) . " (" . min( $all_years ) . "-" . max( $all_years ) . ")\n";
echo "  Leagues: " . count( $all_leagues ) . "\n";
echo "  Mode:    $mode\n";
echo "  Total:   $total_combinations league-year combinations\n";
echo "\n";
echo "Rate limiting: {$delay_ms}ms between requests\n";
echo "Started at: " . date( 'Y-m-d H:i:s' ) . "\n";
echo "\n";
echo "Press Ctrl+C to abort.\n";
echo "\n";
echo "==============================================\n";
echo "\n";

// Main scrape loop
foreach ( $all_years as $year ) {
    echo "\n";
    echo "========================================\n";
    echo "  YEAR: $year\n";
    echo "========================================\n";
    echo "\n";

    $year_start = time();
    $year_fixtures = 0;

    foreach ( $all_leagues as $league_code ) {
        $completed++;

        // Format progress
        $progress_pct = round( ( $completed / $total_combinations ) * 100, 1 );
        $timestamp = date( 'H:i:s' );

        echo "[$timestamp] [$completed/$total_combinations] $league_code $year... ";

        try {
            // Call the scraper
            $result = $datasource->scrape_season( $league_code, $year, $mode );

            if ( $result && is_array( $result ) ) {
                $fixtures_count = isset( $result['fixtures_found'] ) ? $result['fixtures_found'] : 0;
                $deep_count = isset( $result['lineups_found'] ) ? $result['lineups_found'] : 0;

                $total_fixtures += $fixtures_count;
                $year_fixtures += $fixtures_count;

                if ( $fixtures_count > 0 ) {
                    echo "OK ($fixtures_count fixtures, $deep_count deep)\n";
                } else {
                    echo "OK (no fixtures)\n";
                }
            } else {
                echo "OK (empty result)\n";
            }
        } catch ( Exception $e ) {
            $errors++;
            echo "ERROR: " . $e->getMessage() . "\n";
            error_log( "Historical scrape error [$league_code $year]: " . $e->getMessage() );
        }

        // Progress summary every 50 items
        if ( $completed % 50 === 0 ) {
            $elapsed = time() - $start_time;
            $rate = $completed / max( 1, $elapsed );
            $remaining_seconds = ( $total_combinations - $completed ) / max( 0.001, $rate );

            echo "\n";
            echo ">>> PROGRESS UPDATE <<<\n";
            echo "  Completed: $completed / $total_combinations ($progress_pct%)\n";
            echo "  Fixtures:  $total_fixtures total\n";
            echo "  Errors:    $errors\n";
            echo "  Elapsed:   " . gmdate( 'H:i:s', $elapsed ) . "\n";
            echo "  ETA:       " . gmdate( 'H:i:s', (int) $remaining_seconds ) . " remaining\n";
            echo "\n";
        }

        // Rate limiting
        usleep( $delay_ms * 1000 );
    }

    // Year summary
    $year_duration = time() - $year_start;
    echo "\n";
    echo "  Year $year complete: $year_fixtures fixtures in " . gmdate( 'H:i:s', $year_duration ) . "\n";
}

// Final summary
$total_duration = time() - $start_time;

echo "\n";
echo "==============================================\n";
echo "       SCRAPE COMPLETE                        \n";
echo "==============================================\n";
echo "\n";
echo "Summary:\n";
echo "  Combinations: $completed\n";
echo "  Fixtures:     $total_fixtures\n";
echo "  Errors:       $errors\n";
echo "  Duration:     " . gmdate( 'H:i:s', $total_duration ) . "\n";
echo "\n";
echo "Finished at: " . date( 'Y-m-d H:i:s' ) . "\n";
echo "\n";
echo "==============================================\n";
