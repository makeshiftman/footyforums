<?php
/**
 * WP-CLI Commands for E Datasource Sync
 * 
 * Provides command-line interface for syncing leagues, clubs, and fixtures
 * All commands use the "footy" namespace to avoid branding leaks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Only load if WP-CLI is available
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

require_once FDM_PLUGIN_DIR . 'includes/e_datasource_v2.php';
require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';

/**
 * Sync league definitions from ESPN into the leagues table.
 *
 * ## OPTIONS
 *
 * [<e_league_codes>]
 * : Optional argument. One or more e_league_code values separated by commas.
 *   Example: eng.1,esp.1,ger.1
 *   If omitted, all supported leagues are synced.
 *
 * ## EXAMPLES
 *
 *     wp footy e_sync_leagues
 *     wp footy e_sync_leagues eng.1,esp.1,ger.1
 */
function wp_cli_footy_e_sync_leagues( $args, $assoc_args ) {
    $league_codes = array();
    
    // Parse --leagues flag if provided
    if ( ! empty( $assoc_args['leagues'] ) ) {
        $league_codes = array_map( 'trim', explode( ',', $assoc_args['leagues'] ) );
        $league_codes = array_filter( $league_codes ); // Remove empty values
        
        if ( empty( $league_codes ) ) {
            WP_CLI::error( 'Invalid --leagues value. Provide comma-separated e_league_code values.' );
        }
    }
    
    WP_CLI::log( 'Starting league sync...' );
    
    $result = FDM_E_Datasource_V2::e_datasource_sync_leagues( $league_codes );
    
    if ( ! is_array( $result ) ) {
        WP_CLI::error( 'Unexpected result from sync function' );
    }
    
    // Display summary table
    $table_data = array();
    foreach ( $result['leagues'] as $league ) {
        $table_data[] = array(
            'League Code' => $league['e_league_code'],
            'Action' => $league['action'],
            'League ID' => isset( $league['league_id'] ) ? $league['league_id'] : '-',
            'Error' => ! empty( $league['error'] ) ? $league['error'] : '-',
        );
    }
    
    if ( ! empty( $table_data ) ) {
        WP_CLI\Utils\format_items( 'table', $table_data, array( 'League Code', 'Action', 'League ID', 'Error' ) );
    }
    
    // Summary
    WP_CLI::log( '' );
    WP_CLI::log( sprintf( 'Summary: Inserted %d, Updated %d, Errors %d, Skipped %d',
        $result['count_inserted'],
        $result['count_updated'],
        $result['count_errors'],
        $result['count_skipped']
    ) );
    
    // Show errors if any
    if ( ! empty( $result['errors'] ) ) {
        WP_CLI::log( '' );
        WP_CLI::warning( 'Errors encountered:' );
        foreach ( $result['errors'] as $error ) {
            WP_CLI::warning( '  - ' . $error );
        }
    }
    
    // Exit with error code if there were errors
    if ( $result['count_errors'] > 0 ) {
        WP_CLI::halt( 1 );
    }
    
    WP_CLI::success( 'League sync completed successfully' );
}

/**
 * Sync clubs/teams for a specific league
 * 
 * ## OPTIONS
 * 
 * <e_league_code>
 * : E league code (e.g., eng.1, esp.1, ger.1)
 * 
 * ## EXAMPLES
 * 
 *     # Sync clubs for Premier League
 *     $ wp footy e_sync_clubs eng.1
 * 
 *     # Sync clubs for La Liga
 *     $ wp footy e_sync_clubs esp.1
 * 
 * @param array $args Positional arguments
 * @param array $assoc_args Associative arguments
 */
function wp_cli_footy_e_sync_clubs( $args, $assoc_args ) {
    if ( empty( $args[0] ) ) {
        WP_CLI::error( 'e_league_code is required. Usage: wp footy e_sync_clubs <e_league_code>' );
    }
    
    $e_league_code = trim( $args[0] );
    
    if ( empty( $e_league_code ) ) {
        WP_CLI::error( 'e_league_code cannot be empty' );
    }
    
    WP_CLI::log( sprintf( 'Starting club sync for league: %s', $e_league_code ) );
    
    $result = FDM_E_Datasource_V2::e_datasource_sync_clubs_for_league( $e_league_code );
    
    if ( ! is_array( $result ) ) {
        WP_CLI::error( 'Unexpected result from sync function' );
    }
    
    // Display summary
    WP_CLI::log( '' );
    WP_CLI::log( sprintf( 'League: %s', $e_league_code ) );
    WP_CLI::log( sprintf( 'Inserted: %d, Updated: %d, Marked Inactive: %d, Errors: %d',
        $result['count_inserted'],
        $result['count_updated'],
        $result['count_marked_inactive'],
        $result['count_errors']
    ) );
    
    // Show warnings about placeholder clubs if any
    if ( ! empty( $result['warnings'] ) ) {
        WP_CLI::log( '' );
        WP_CLI::warning( 'Warnings:' );
        foreach ( $result['warnings'] as $warning ) {
            WP_CLI::warning( '  - ' . $warning );
        }
    }
    
    // Show errors if any
    if ( ! empty( $result['errors'] ) ) {
        WP_CLI::log( '' );
        WP_CLI::warning( 'Errors encountered:' );
        foreach ( $result['errors'] as $error ) {
            WP_CLI::warning( '  - ' . $error );
        }
    }
    
    // Exit with error code if there were errors
    if ( $result['count_errors'] > 0 ) {
        WP_CLI::halt( 1 );
    }
    
    WP_CLI::success( 'Club sync completed successfully' );
}

/**
 * Sync fixtures for a league over a date range
 * 
 * ## OPTIONS
 * 
 * <e_league_code>
 * : E league code (e.g., eng.1, esp.1, ger.1)
 * 
 * <from_date>
 * : Start date in YYYYMMDD format (e.g., 20240801)
 * 
 * <to_date>
 * : End date in YYYYMMDD format (e.g., 20240831)
 * 
 * ## EXAMPLES
 * 
 *     # Sync fixtures for Premier League in August 2024
 *     $ wp footy e_sync_fixtures eng.1 20240801 20240831
 * 
 *     # Sync fixtures for La Liga in September 2024
 *     $ wp footy e_sync_fixtures esp.1 20240901 20240930
 * 
 * @param array $args Positional arguments
 * @param array $assoc_args Associative arguments
 */
function wp_cli_footy_e_sync_fixtures( $args, $assoc_args ) {
    // Validate required arguments
    if ( empty( $args[0] ) ) {
        WP_CLI::error( 'e_league_code is required. Usage: wp footy e_sync_fixtures <e_league_code> <from_date> <to_date>' );
    }
    
    if ( empty( $args[1] ) ) {
        WP_CLI::error( 'from_date is required in YYYYMMDD format. Usage: wp footy e_sync_fixtures <e_league_code> <from_date> <to_date>' );
    }
    
    if ( empty( $args[2] ) ) {
        WP_CLI::error( 'to_date is required in YYYYMMDD format. Usage: wp footy e_sync_fixtures <e_league_code> <from_date> <to_date>' );
    }
    
    $e_league_code = trim( $args[0] );
    $from_date_raw = trim( $args[1] );
    $to_date_raw = trim( $args[2] );
    
    // Validate e_league_code
    if ( empty( $e_league_code ) ) {
        WP_CLI::error( 'e_league_code cannot be empty' );
    }
    
    // Validate and convert date formats
    if ( ! preg_match( '/^\d{8}$/', $from_date_raw ) ) {
        WP_CLI::error( sprintf( 'Invalid from_date format: %s. Expected YYYYMMDD (e.g., 20240801)', $from_date_raw ) );
    }
    
    if ( ! preg_match( '/^\d{8}$/', $to_date_raw ) ) {
        WP_CLI::error( sprintf( 'Invalid to_date format: %s. Expected YYYYMMDD (e.g., 20240831)', $to_date_raw ) );
    }
    
    // Convert YYYYMMDD to YYYY-MM-DD
    $from_date = sprintf( '%s-%s-%s',
        substr( $from_date_raw, 0, 4 ),
        substr( $from_date_raw, 4, 2 ),
        substr( $from_date_raw, 6, 2 )
    );
    
    $to_date = sprintf( '%s-%s-%s',
        substr( $to_date_raw, 0, 4 ),
        substr( $to_date_raw, 4, 2 ),
        substr( $to_date_raw, 6, 2 )
    );
    
    // Validate dates are actually valid
    $from_timestamp = strtotime( $from_date );
    $to_timestamp = strtotime( $to_date );
    
    if ( $from_timestamp === false ) {
        WP_CLI::error( sprintf( 'Invalid from_date: %s (converted to %s)', $from_date_raw, $from_date ) );
    }
    
    if ( $to_timestamp === false ) {
        WP_CLI::error( sprintf( 'Invalid to_date: %s (converted to %s)', $to_date_raw, $to_date ) );
    }
    
    // Validate date range
    if ( $from_timestamp > $to_timestamp ) {
        WP_CLI::error( sprintf( 'from_date (%s) must be before or equal to to_date (%s)', $from_date, $to_date ) );
    }
    
    WP_CLI::log( sprintf( 'Starting fixture sync for league: %s, from %s to %s', $e_league_code, $from_date, $to_date ) );
    WP_CLI::log( '' );
    
    $result = FDM_E_Datasource_V2::e_datasource_sync_fixtures_for_league_range( $e_league_code, $from_date, $to_date );
    
    if ( ! is_array( $result ) ) {
        WP_CLI::error( 'Unexpected result from sync function' );
    }
    
    // Display progress per date
    if ( ! empty( $result['dates'] ) ) {
        foreach ( $result['dates'] as $date_result ) {
            $date_display = str_replace( '-', ' ', $date_result['date'] );
            WP_CLI::log( sprintf( 'Processing %s: inserted %d, updated %d, skipped %d, errors %d',
                $date_display,
                $date_result['inserted'],
                $date_result['updated'],
                $date_result['skipped'],
                $date_result['errors']
            ) );
        }
    }
    
    // Display totals
    WP_CLI::log( '' );
    WP_CLI::log( 'Summary:' );
    WP_CLI::log( sprintf( '  Inserted: %d', $result['count_inserted'] ) );
    WP_CLI::log( sprintf( '  Updated: %d', $result['count_updated'] ) );
    WP_CLI::log( sprintf( '  Skipped: %d', $result['count_skipped'] ) );
    WP_CLI::log( sprintf( '  Errors: %d', $result['count_errors'] ) );
    
    // Show warnings if any
    if ( ! empty( $result['warnings'] ) ) {
        WP_CLI::log( '' );
        WP_CLI::warning( 'Warnings:' );
        foreach ( $result['warnings'] as $warning ) {
            WP_CLI::warning( '  - ' . $warning );
        }
    }
    
    // Show errors if any
    if ( ! empty( $result['errors'] ) ) {
        WP_CLI::log( '' );
        WP_CLI::warning( 'Errors encountered:' );
        foreach ( $result['errors'] as $error ) {
            WP_CLI::warning( '  - ' . $error );
        }
    }
    
    // Exit with error code if there were errors
    if ( $result['count_errors'] > 0 ) {
        WP_CLI::halt( 1 );
    }
    
    WP_CLI::success( 'Fixture sync completed successfully' );
}

/**
 * Sync season statistics for players and teams
 * 
 * ## OPTIONS
 * 
 * <league_code>
 * : League code (e.g., eng.1)
 * 
 * <season_year>
 * : Season year (e.g., 2024)
 * 
 * ## EXAMPLES
 * 
 *     wp footy e_sync_season_stats eng.1 2024
 * 
 * @param array $args Positional arguments
 * @param array $assoc_args Associative arguments
 */
function wp_cli_footy_e_sync_season_stats( $args, $assoc_args ) {
    if ( empty( $args[0] ) ) {
        WP_CLI::error( 'league_code is required. Usage: wp footy e_sync_season_stats <league_code> <season_year>' );
    }
    
    if ( empty( $args[1] ) ) {
        WP_CLI::error( 'season_year is required. Usage: wp footy e_sync_season_stats <league_code> <season_year>' );
    }
    
    $league_code = trim( $args[0] );
    $season_year = (int) $args[1];
    
    if ( empty( $league_code ) ) {
        WP_CLI::error( 'league_code cannot be empty' );
    }
    
    if ( $season_year < 1800 || $season_year > 3000 ) {
        WP_CLI::error( sprintf( 'Invalid season_year: %d. Expected a valid year between 1800 and 3000', $season_year ) );
    }
    
    WP_CLI::log( sprintf( 'Starting season stats sync for %s, season %d...', $league_code, $season_year ) );
    
    if ( function_exists( 'fdm_e_sync_season_stats' ) ) {
        $result = fdm_e_sync_season_stats( $league_code, $season_year );
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
        }
        WP_CLI::success( sprintf( 'Season stats sync completed. %d team rows written.', $result['team_rows'] ) );
    } else {
        WP_CLI::error( 'fdm_e_sync_season_stats not implemented yet' );
    }
}

/**
 * Sync standings for leagues
 * 
 * ## OPTIONS
 * 
 * [<league_code>]
 * : Optional league code (e.g., eng.1). If omitted, syncs all leagues.
 * 
 * [--season=<season_id>]
 * : Optional season ID. If omitted, uses current season.
 * 
 * ## EXAMPLES
 * 
 *     wp footy e_sync_standings
 *     wp footy e_sync_standings eng.1
 *     wp footy e_sync_standings eng.1 --season=2024
 * 
 * @param array $args Positional arguments
 * @param array $assoc_args Associative arguments
 */
function wp_cli_footy_e_sync_standings( $args, $assoc_args ) {
    $league_code = ! empty( $args[0] ) ? trim( $args[0] ) : null;
    $season_id = ! empty( $assoc_args['season'] ) ? trim( $assoc_args['season'] ) : null;
    
    WP_CLI::log( 'Starting standings sync...' );
    
    if ( function_exists( 'fdm_e_sync_standings' ) ) {
        $result = fdm_e_sync_standings( $league_code, $season_id );
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
        }
        WP_CLI::success( 'Standings sync completed' );
    } else {
        WP_CLI::error( 'fdm_e_sync_standings not implemented yet' );
    }
}

/**
 * Sync tournament data (groups and brackets)
 * 
 * ## OPTIONS
 * 
 * [<tournament_id>]
 * : Optional tournament ID. If omitted, syncs all tournaments.
 * 
 * [--season=<season_id>]
 * : Optional season ID. If omitted, uses current season.
 * 
 * ## EXAMPLES
 * 
 *     wp footy e_sync_tournaments
 *     wp footy e_sync_tournaments uefa.champions
 *     wp footy e_sync_tournaments uefa.champions --season=2024
 * 
 * @param array $args Positional arguments
 * @param array $assoc_args Associative arguments
 */
function wp_cli_footy_e_sync_tournaments( $args, $assoc_args ) {
    $tournament_id = ! empty( $args[0] ) ? trim( $args[0] ) : null;
    $season_id = ! empty( $assoc_args['season'] ) ? trim( $assoc_args['season'] ) : null;
    
    WP_CLI::log( 'Starting tournament sync...' );
    
    if ( function_exists( 'fdm_e_sync_tournaments' ) ) {
        $result = fdm_e_sync_tournaments( $tournament_id, $season_id );
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
        }
        WP_CLI::success( 'Tournament sync completed' );
    } else {
        WP_CLI::error( 'fdm_e_sync_tournaments not implemented yet' );
    }
}

/**
 * Run full coverage test for ESPN data sync
 * 
 * ## OPTIONS
 * 
 * [--league=<league_code>]
 * : Optional league code to test (e.g., eng.1). If omitted, tests all leagues.
 * 
 * [--season=<season_id>]
 * : Optional season ID. If omitted, uses current season.
 * 
 * ## EXAMPLES
 * 
 *     wp footy e_full_test
 *     wp footy e_full_test --league=eng.1
 *     wp footy e_full_test --league=eng.1 --season=2024
 * 
 * @param array $args Positional arguments
 * @param array $assoc_args Associative arguments
 */
function wp_cli_footy_e_full_test( $args, $assoc_args ) {
    $league_code = ! empty( $assoc_args['league'] ) ? trim( $assoc_args['league'] ) : null;
    $season_id = ! empty( $assoc_args['season'] ) ? trim( $assoc_args['season'] ) : null;
    
    WP_CLI::log( 'Starting full coverage test...' );
    
    if ( function_exists( 'fdm_e_run_full_coverage_test' ) ) {
        $result = fdm_e_run_full_coverage_test( $league_code, $season_id );
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
        }
        WP_CLI::success( 'Full coverage test completed' );
    } else {
        WP_CLI::error( 'fdm_e_run_full_coverage_test not implemented yet' );
    }
}

// Register WP-CLI commands
WP_CLI::add_command( 'footy e_sync_leagues', 'wp_cli_footy_e_sync_leagues' );
WP_CLI::add_command( 'footy e_sync_clubs', 'wp_cli_footy_e_sync_clubs' );
WP_CLI::add_command( 'footy e_sync_fixtures', 'wp_cli_footy_e_sync_fixtures' );
/**
 * Sync match team statistics from ESPN API
 * 
 * ## OPTIONS
 * 
 * <league_code>
 * : League code (e.g., eng.1)
 * 
 * <season_year>
 * : Season year (e.g., 2024)
 * 
 * ## EXAMPLES
 * 
 *     wp footy e_sync_match_stats eng.1 2024
 * 
 * @param array $args Positional arguments
 * @param array $assoc_args Associative arguments
 */
function wp_cli_footy_e_sync_match_stats( $args, $assoc_args ) {
    if ( empty( $args[0] ) ) {
        WP_CLI::error( 'league_code is required. Usage: wp footy e_sync_match_stats <league_code> <season_year>' );
    }
    
    if ( empty( $args[1] ) ) {
        WP_CLI::error( 'season_year is required. Usage: wp footy e_sync_match_stats <league_code> <season_year>' );
    }
    
    $league_code = trim( $args[0] );
    $season_year = (int) $args[1];
    
    if ( empty( $league_code ) ) {
        WP_CLI::error( 'league_code cannot be empty' );
    }
    
    if ( $season_year < 1800 || $season_year > 3000 ) {
        WP_CLI::error( sprintf( 'Invalid season_year: %d. Expected a valid year between 1800 and 3000', $season_year ) );
    }
    
    WP_CLI::log( sprintf( 'Starting match team stats sync for %s, season %d...', $league_code, $season_year ) );
    
    $result = FDM_E_Datasource_V2::e_datasource_sync_match_team_stats( $league_code, $season_year );
    
    if ( is_wp_error( $result ) ) {
        WP_CLI::error( $result->get_error_message() );
    }
    
    WP_CLI::success( sprintf(
        'Match stats sync completed. Matches processed: %d, rows written: %d, errors: %d',
        $result['matches_processed'],
        $result['rows_written'],
        $result['errors_count']
    ) );
}

WP_CLI::add_command( 'footy e_sync_season_stats', 'wp_cli_footy_e_sync_season_stats' );
WP_CLI::add_command( 'footy e_sync_standings', 'wp_cli_footy_e_sync_standings' );
WP_CLI::add_command( 'footy e_sync_tournaments', 'wp_cli_footy_e_sync_tournaments' );
WP_CLI::add_command( 'footy e_full_test', 'wp_cli_footy_e_full_test' );
WP_CLI::add_command( 'footy e_sync_match_stats', 'wp_cli_footy_e_sync_match_stats' );

/**
 * Sync player match statistics from ESPN API
 * 
 * ## OPTIONS
 * 
 * <competition_code>
 * : Competition code (e.g., eng.1)
 * 
 * <season_year>
 * : Season year (e.g., 2019)
 * 
 * ## EXAMPLES
 * 
 *     wp footy e_sync_player_match_stats eng.1 2019
 * 
 * @param array $args Positional arguments
 * @param array $assoc_args Associative arguments
 */
function wp_cli_footy_e_sync_player_match_stats( $args, $assoc_args ) {
    if ( empty( $args[0] ) ) {
        WP_CLI::error( 'competition_code is required. Usage: wp footy e_sync_player_match_stats <competition_code> <season_year>' );
    }
    
    if ( empty( $args[1] ) ) {
        WP_CLI::error( 'season_year is required. Usage: wp footy e_sync_player_match_stats <competition_code> <season_year>' );
    }
    
    $competition_code = trim( $args[0] );
    $season_year = (int) $args[1];
    
    if ( empty( $competition_code ) ) {
        WP_CLI::error( 'competition_code cannot be empty' );
    }
    
    if ( $season_year < 1800 || $season_year > 3000 ) {
        WP_CLI::error( sprintf( 'Invalid season_year: %d. Expected a valid year between 1800 and 3000', $season_year ) );
    }
    
    WP_CLI::log( sprintf( 'Starting player match stats sync for %s, season %d...', $competition_code, $season_year ) );
    
    $result = FDM_E_Datasource_V2::import_player_match_stats_for_season( $competition_code, $season_year );
    
    if ( is_wp_error( $result ) ) {
        WP_CLI::error( $result->get_error_message() );
    }
    
    WP_CLI::success( sprintf(
        'Player match stats sync completed. Matches processed: %d, players processed: %d, rows written: %d, errors: %d',
        $result['matches_processed'],
        $result['players_processed'],
        $result['rows_written'],
        $result['errors_count']
    ) );
}

/**
 * Rebuild player season statistics from aggregated match stats
 * 
 * ## OPTIONS
 * 
 * <competition_code>
 * : Competition code (e.g., eng.1)
 * 
 * <season_year>
 * : Season year (e.g., 2019)
 * 
 * ## EXAMPLES
 * 
 *     wp footy e_sync_player_season_stats eng.1 2019
 * 
 * @param array $args Positional arguments
 * @param array $assoc_args Associative arguments
 */
function wp_cli_footy_e_sync_player_season_stats( $args, $assoc_args ) {
    if ( empty( $args[0] ) ) {
        WP_CLI::error( 'competition_code is required. Usage: wp footy e_sync_player_season_stats <competition_code> <season_year>' );
    }
    
    if ( empty( $args[1] ) ) {
        WP_CLI::error( 'season_year is required. Usage: wp footy e_sync_player_season_stats <competition_code> <season_year>' );
    }
    
    $competition_code = trim( $args[0] );
    $season_year = (int) $args[1];
    
    if ( empty( $competition_code ) ) {
        WP_CLI::error( 'competition_code cannot be empty' );
    }
    
    if ( $season_year < 1800 || $season_year > 3000 ) {
        WP_CLI::error( sprintf( 'Invalid season_year: %d. Expected a valid year between 1800 and 3000', $season_year ) );
    }
    
    WP_CLI::log( sprintf( 'Starting player season stats rebuild for %s, season %d...', $competition_code, $season_year ) );
    
    $result = FDM_E_Datasource_V2::rebuild_player_season_stats( $competition_code, $season_year );
    
    if ( is_wp_error( $result ) ) {
        WP_CLI::error( $result->get_error_message() );
    }
    
    WP_CLI::success( sprintf(
        'Player season stats rebuild completed. Rows written: %d, errors: %d',
        $result['rows_written'],
        $result['errors_count']
    ) );
}

WP_CLI::add_command( 'footy e_sync_player_match_stats', 'wp_cli_footy_e_sync_player_match_stats' );
WP_CLI::add_command( 'footy e_sync_player_season_stats', 'wp_cli_footy_e_sync_player_season_stats' );

/**
 * Debug helper: Log the structure of an ESPN match summary JSON
 * 
 * ## OPTIONS
 * 
 * <competition_code>
 * : Competition code (e.g., eng.1)
 * 
 * <e_match_id>
 * : ESPN event/match ID
 * 
 * ## EXAMPLES
 * 
 *     wp footy e_debug_match_summary eng.1 541482
 * 
 * @param array $args Positional arguments
 * @param array $assoc_args Associative arguments
 */
function wp_cli_footy_e_debug_match_summary( $args, $assoc_args ) {
    if ( empty( $args[0] ) ) {
        WP_CLI::error( 'competition_code is required. Usage: wp footy e_debug_match_summary <competition_code> <e_match_id>' );
    }
    
    if ( empty( $args[1] ) ) {
        WP_CLI::error( 'e_match_id is required. Usage: wp footy e_debug_match_summary <competition_code> <e_match_id>' );
    }
    
    $competition_code = trim( $args[0] );
    $e_match_id = trim( $args[1] );
    
    if ( empty( $competition_code ) || empty( $e_match_id ) ) {
        WP_CLI::error( 'competition_code and e_match_id cannot be empty' );
    }
    
    require_once FDM_PLUGIN_DIR . 'includes/e_datasource_v2.php';
    
    WP_CLI::log( sprintf( 'Fetching summary for %s, event %s...', $competition_code, $e_match_id ) );
    
    $result = FDM_E_Datasource_V2::debug_log_match_summary_shape( $competition_code, $e_match_id );
    
    if ( is_wp_error( $result ) ) {
        WP_CLI::error( 'Error: ' . $result->get_error_message() );
    }
    
    WP_CLI::success( 'Summary shape logged. Check wp-content/debug.log for details.' );
}

WP_CLI::add_command( 'footy e_debug_match_summary', 'wp_cli_footy_e_debug_match_summary' );

function wp_cli_footy_ingest_enqueue( $args, $assoc_args ) {
    $db = fdm_get_footyforums_db();
    if ( ! $db ) {
        WP_CLI::error( 'DB connection failed' );
    }

    if ( empty( $assoc_args['provider'] ) || empty( $assoc_args['job_type'] ) ) {
        WP_CLI::error( 'provider and job_type required' );
    }

    $payload_json = null;
    if ( isset( $assoc_args['payload'] ) ) {
        $decoded = json_decode( $assoc_args['payload'], true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            WP_CLI::error( 'Invalid JSON in --payload: ' . json_last_error_msg() );
        }
        $payload_json = wp_json_encode( $decoded );
    }

    $db->insert(
        'ingest_jobs',
        [
            'provider' => $assoc_args['provider'],
            'job_type' => $assoc_args['job_type'],
            'status' => 'pending',
            'priority' => 100,
            'season_year' => $assoc_args['season_year'] ?? null,
            'competition_code' => $assoc_args['competition_code'] ?? null,
            'payload_json' => $payload_json,
            'next_run_at' => current_time( 'mysql', true ),
            'attempts' => 0,
            'max_attempts' => 5
        ]
    );

    WP_CLI::success( 'Job enqueued id ' . $db->insert_id );
}

function wp_cli_footy_ingest_run_due( $args, $assoc_args ) {
    $lease_seconds = isset( $assoc_args['lease_seconds'] ) ? (int) $assoc_args['lease_seconds'] : 600;
    
    $result = fdm_ingest_run_due_once( $lease_seconds );
    
    if ( ! $result['ran'] ) {
        if ( $result['message'] === 'No jobs due' ) {
            WP_CLI::line( 'No jobs due' );
        } else {
            WP_CLI::error( $result['message'] );
        }
        return;
    }
    
    if ( strpos( $result['message'], 'failed' ) !== false ) {
        WP_CLI::warning( $result['message'] );
    } else {
        WP_CLI::success( $result['message'] );
    }
}

WP_CLI::add_command( 'footy ingest_enqueue', 'wp_cli_footy_ingest_enqueue' );
WP_CLI::add_command( 'footy ingest_run_due', 'wp_cli_footy_ingest_run_due' );

function wp_cli_footy_ingest_seed_espn( $args, $assoc_args ) {
    $db = fdm_get_footyforums_db();
    if ( ! $db ) {
        WP_CLI::error( 'DB connection failed' );
    }

    // Define jobs to seed
    $jobs = array(
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_leagues',
            'schedule_rule' => 'interval:86400',
            'priority' => 10
        ),
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_clubs',
            'schedule_rule' => 'interval:86400',
            'priority' => 20
        ),
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_fixtures',
            'schedule_rule' => 'interval:86400',
            'priority' => 30
        ),
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_results',
            'schedule_rule' => 'interval:900',
            'priority' => 5
        )
    );

    foreach ( $jobs as $job_def ) {
        // Find all existing rows with this provider+job_type, ordered by id asc
        $all_existing = $db->get_results(
            $db->prepare(
                "SELECT id, status FROM ingest_jobs WHERE provider=%s AND job_type=%s ORDER BY id ASC",
                $job_def['provider'],
                $job_def['job_type']
            ),
            ARRAY_A
        );

        if ( ! empty( $all_existing ) ) {
            // Keep the lowest id as canonical
            $canonical = $all_existing[0];
            $canonical_id = (int) $canonical['id'];
            $canonical_status = $canonical['status'];
            
            // Delete all other rows
            $to_delete = array();
            foreach ( $all_existing as $row ) {
                if ( (int) $row['id'] !== $canonical_id ) {
                    $to_delete[] = (int) $row['id'];
                }
            }
            
            $deleted_count = 0;
            if ( ! empty( $to_delete ) ) {
                $ids_escaped = array_map( 'intval', $to_delete );
                $ids_string = implode( ',', $ids_escaped );
                $deleted_count = $db->query( "DELETE FROM ingest_jobs WHERE id IN ($ids_string)" );
            }
            
            // Prepare update data
            $update_data = array(
                'schedule_rule' => $job_def['schedule_rule'],
                'priority' => $job_def['priority'],
                'lease_expires_at' => null,
                'last_error' => null
            );
            
            // Preserve 'paused' status, otherwise set to 'pending'
            if ( $canonical_status !== 'paused' ) {
                $update_data['status'] = 'pending';
                $update_data['next_run_at'] = current_time( 'mysql', true );
            }
            
            // Update the canonical row
            $db->update(
                'ingest_jobs',
                $update_data,
                array( 'id' => $canonical_id )
            );
            
            if ( $deleted_count > 0 ) {
                WP_CLI::log( 'deduped ' . $job_def['job_type'] . ' kept ' . $canonical_id . ' deleted ' . $deleted_count );
            } else {
                WP_CLI::log( 'updated ' . $job_def['job_type'] . ' id ' . $canonical_id );
            }
        } else {
            // Insert new job
            $db->insert(
                'ingest_jobs',
                array(
                    'provider' => $job_def['provider'],
                    'job_type' => $job_def['job_type'],
                    'status' => 'pending',
                    'schedule_rule' => $job_def['schedule_rule'],
                    'priority' => $job_def['priority'],
                    'next_run_at' => current_time( 'mysql', true ),
                    'attempts' => 0,
                    'max_attempts' => 5
                )
            );
            WP_CLI::log( 'inserted ' . $job_def['job_type'] . ' id ' . $db->insert_id );
        }
    }

    WP_CLI::success( 'ESPN ingest jobs seeded' );
}

WP_CLI::add_command( 'footy ingest_seed_espn', 'wp_cli_footy_ingest_seed_espn' );

