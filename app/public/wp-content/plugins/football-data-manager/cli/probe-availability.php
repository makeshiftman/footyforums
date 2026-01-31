#!/usr/bin/env php
<?php
/**
 * CLI script to probe ESPN availability and populate espn_availability table.
 *
 * Usage:
 *   php probe-availability.php                      # Full probe (all leagues, 2001-2025)
 *   php probe-availability.php --league=eng.1 --year=2024   # Single probe
 *   php probe-availability.php --help               # Show help
 *
 * This script queries ESPN's API to discover what data is available
 * for each league/year combination. Results are stored in e_db.espn_availability.
 *
 * Rate limiting: 200ms between API requests (safe for ESPN's API)
 * Estimated runtime for full probe: 30-60 minutes for 250 leagues x 25 years
 *
 * @package FootyForums
 */

// Ensure we're running from CLI
if ( php_sapi_name() !== 'cli' ) {
    die( "This script can only be run from the command line.\n" );
}

// Parse command line options
$options = getopt( '', [ 'league::', 'year::', 'help' ] );

// Show help if requested
if ( isset( $options['help'] ) ) {
    echo <<<HELP
ESPN Availability Prober
========================

Queries ESPN's API to discover what historical data is available
for each league/year combination.

Usage:
  php probe-availability.php                           Full probe (all leagues, 2001-2025)
  php probe-availability.php --league=eng.1 --year=2024    Single league/year probe
  php probe-availability.php --help                    Show this help

Options:
  --league=CODE    ESPN league code (e.g., eng.1, esp.1, uefa.champions)
  --year=YYYY      Season year (2001-2025)
  --help           Show this help message

Examples:
  php probe-availability.php --league=eng.1 --year=2024
  php probe-availability.php --league=esp.1 --year=2010
  php probe-availability.php --league=uefa.champions --year=2020

Notes:
  - Full probe takes approximately 30-60 minutes
  - Rate limiting: 200ms between API requests
  - Results stored in e_db.espn_availability table
  - Women's leagues are automatically filtered out

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
} else {
    echo "Warning: WordPress not loaded - running in standalone mode.\n";
    echo "Database connection will use fallback settings.\n\n";
}

// Load the prober class
$prober_path = dirname( __DIR__ ) . '/includes/class-fdm-availability-prober.php';

if ( ! file_exists( $prober_path ) ) {
    die( "Error: Prober class not found at: $prober_path\n" );
}

require_once $prober_path;

// Create prober instance
$prober = new FDM_Availability_Prober();

// Run based on options
if ( ! empty( $options['league'] ) && ! empty( $options['year'] ) ) {
    // Single probe mode
    $league = $options['league'];
    $year = (int) $options['year'];

    if ( $year < 2001 || $year > 2025 ) {
        die( "Error: Year must be between 2001 and 2025.\n" );
    }

    echo "=== ESPN Availability Prober ===\n";
    echo "Mode: Single probe\n";
    echo "League: $league\n";
    echo "Year: $year\n";
    echo "================================\n\n";

    $result = $prober->probe_single( $league, $year );

    if ( $result ) {
        echo "\nResults:\n";
        echo "  Fixtures available:     " . $result['fixtures_available'] . "\n";
        echo "  Lineups available:      " . ( $result['lineups_available'] ? 'Yes' : 'No' ) . "\n";
        echo "  Commentary available:   " . ( $result['commentary_available'] ? 'Yes' : 'No' ) . "\n";
        echo "  Key events available:   " . ( $result['key_events_available'] ? 'Yes' : 'No' ) . "\n";
        echo "  Team stats available:   " . ( $result['team_stats_available'] ? 'Yes' : 'No' ) . "\n";
        echo "  Player stats available: " . ( $result['player_stats_available'] ? 'Yes' : 'No' ) . "\n";
        echo "  Roster available:       " . ( $result['roster_available'] ? 'Yes' : 'No' ) . "\n";
        echo "\nData saved to e_db.espn_availability table.\n";
    } else {
        echo "\nNo data available for $league in $year.\n";
    }
} else {
    // Full probe mode
    echo "=== ESPN Availability Prober ===\n";
    echo "Mode: Full probe\n";
    echo "Years: 2001-2025\n";
    echo "================================\n\n";
    echo "Starting full ESPN availability probe...\n";
    echo "This will take approximately 30-60 minutes for 250 leagues x 25 years.\n";
    echo "Rate limiting: 200ms between requests.\n\n";
    echo "Press Ctrl+C to abort.\n\n";

    $start_time = time();
    $prober->run();
    $elapsed = time() - $start_time;

    echo "\n================================\n";
    echo "Probe complete!\n";
    echo "Total runtime: " . gmdate( 'H:i:s', $elapsed ) . "\n";
    echo "================================\n";
}
