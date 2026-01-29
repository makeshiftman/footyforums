<?php
// Temporary one-off script to repair competition_code for existing matches.
// Delete this file after you have run it once.

define( 'SHORTINIT', false );

// Bootstrap WordPress (from wp-content/plugins/football-data-manager/includes)
require_once __DIR__ . '/../../../../wp-load.php';

// Make sure the main plugin constants and classes are loaded
if ( ! defined( 'FDM_PLUGIN_DIR' ) ) {
    // This file lives in .../plugins/football-data-manager/includes/
    // The main plugin file is one level up.
    require_once __DIR__ . '/../football-data-manager.php';
}

// Load the helper that wraps the repair method (same directory)
require_once __DIR__ . '/repair-competition-codes-helper.php';

if ( ! function_exists( 'fdm_repair_competition_codes' ) ) {
    wp_die( 'fdm_repair_competition_codes() not available. Check helper include.' );
}

// Safer: process matches in smaller batches so each run finishes quickly.
// You can re-run this script multiple times until COUNT(*) WHERE competition_code IS NULL = 0.
$limit = 100;

$result = fdm_repair_competition_codes( $limit );

// Simple text output
header( 'Content-Type: text/plain; charset=utf-8' );
echo "Repair competition_code run complete.\n";
echo "Processed: " . (int) $result['processed'] . "\n";
echo "Updated: "  . (int) $result['updated'] . "\n";
echo "Skipped: "  . (int) $result['skipped'] . "\n";
echo "Errors: "   . (int) $result['errors'] . "\n";