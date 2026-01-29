<?php
/**
 * E Datasource V2 - Uses footyforums_data database
 * Rewritten to use canonical clubs, seasons, and matches tables
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';

/**
 * Centralized league configuration
 * Single source of truth for all supported leagues
 * Keyed by e_league_code for direct lookup
 */
$GLOBALS['fdm_e_supported_leagues'] = array(
    // EUROPE - Top-tier domestic leagues
    'eng.1' => array(
        'e_league_code' => 'eng.1',
        'name' => 'English Premier League',
        'region' => 'europe',
        'country' => 'England',
        'tier' => 1,
        'type' => 'league',
        'priority' => 10,
    ),
    'eng.2' => array(
        'name'        => 'English Championship',
        'region'      => 'europe',
        'country'     => 'England',
        'tier'        => 2,
        'type'        => 'league',
        'priority'    => 8,
        'espn_uid'    => 's:600~l:392'
    ),

    'eng.3' => array(
        'name'        => 'English League One',
        'region'      => 'europe',
        'country'     => 'England',
        'tier'        => 3,
        'type'        => 'league',
        'priority'    => 7,
        'espn_uid'    => 's:600~l:393'
    ),

    'eng.4' => array(
        'name'        => 'English League Two',
        'region'      => 'europe',
        'country'     => 'England',
        'tier'        => 4,
        'type'        => 'league',
        'priority'    => 6,
        'espn_uid'    => 's:600~l:394'
    ),

    'eng.5' => array(
        'name'        => 'English National League',
        'region'      => 'europe',
        'country'     => 'England',
        'tier'        => 5,
        'type'        => 'league',
        'priority'    => 5,
        'espn_uid'    => 's:600~l:395'
    ),
    'esp.1' => array(
        'e_league_code' => 'esp.1',
        'name' => 'La Liga',
        'region' => 'europe',
        'country' => 'Spain',
        'tier' => 1,
        'type' => 'league',
        'priority' => 10,
    ),
    'ger.1' => array(
        'e_league_code' => 'ger.1',
        'name' => 'German Bundesliga',
        'region' => 'europe',
        'country' => 'Germany',
        'tier' => 1,
        'type' => 'league',
        'priority' => 10,
    ),
    'ita.1' => array(
        'e_league_code' => 'ita.1',
        'name' => 'Italian Serie A',
        'region' => 'europe',
        'country' => 'Italy',
        'tier' => 1,
        'type' => 'league',
        'priority' => 10,
    ),
    'fra.1' => array(
        'e_league_code' => 'fra.1',
        'name' => 'French Ligue 1',
        'region' => 'europe',
        'country' => 'France',
        'tier' => 1,
        'type' => 'league',
        'priority' => 10,
    ),
    'por.1' => array(
        'e_league_code' => 'por.1',
        'name' => 'Portuguese Primeira Liga',
        'region' => 'europe',
        'country' => 'Portugal',
        'tier' => 1,
        'type' => 'league',
        'priority' => 9,
    ),
    'ned.1' => array(
        'e_league_code' => 'ned.1',
        'name' => 'Dutch Eredivisie',
        'region' => 'europe',
        'country' => 'Netherlands',
        'tier' => 1,
        'type' => 'league',
        'priority' => 8,
    ),
    'sco.1' => array(
        'e_league_code' => 'sco.1',
        'name' => 'Scottish Premiership',
        'region' => 'europe',
        'country' => 'Scotland',
        'tier' => 1,
        'type' => 'league',
        'priority' => 7,
    ),
    'aut.1' => array(
        'e_league_code' => 'aut.1',
        'name' => 'Austrian Bundesliga',
        'region' => 'europe',
        'country' => 'Austria',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    'sui.1' => array(
        'e_league_code' => 'sui.1',
        'name' => 'Swiss Super League',
        'region' => 'europe',
        'country' => 'Switzerland',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    'pol.1' => array(
        'e_league_code' => 'pol.1',
        'name' => 'Polish Ekstraklasa',
        'region' => 'europe',
        'country' => 'Poland',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    'ukr.1' => array(
        'e_league_code' => 'ukr.1',
        'name' => 'Ukrainian Premier League',
        'region' => 'europe',
        'country' => 'Ukraine',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    'cze.1' => array(
        'e_league_code' => 'cze.1',
        'name' => 'Czech First League',
        'region' => 'europe',
        'country' => 'Czech Republic',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    'rus.1' => array(
        'e_league_code' => 'rus.1',
        'name' => 'Russian Premier League',
        'region' => 'europe',
        'country' => 'Russia',
        'tier' => 1,
        'type' => 'league',
        'priority' => 5,
        // Note: Verify if still available in E API
    ),
    'tur.1' => array(
        'e_league_code' => 'tur.1',
        'name' => 'Turkish Super Lig',
        'region' => 'europe',
        'country' => 'Turkey',
        'tier' => 1,
        'type' => 'league',
        'priority' => 7,
    ),
    'bel.1' => array(
        'e_league_code' => 'bel.1',
        'name' => 'Belgian Pro League',
        'region' => 'europe',
        'country' => 'Belgium',
        'tier' => 1,
        'type' => 'league',
        'priority' => 7,
    ),
    'gre.1' => array(
        'e_league_code' => 'gre.1',
        'name' => 'Greek Super League',
        'region' => 'europe',
        'country' => 'Greece',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    'nor.1' => array(
        'e_league_code' => 'nor.1',
        'name' => 'Norwegian Eliteserien',
        'region' => 'europe',
        'country' => 'Norway',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    'swe.1' => array(
        'e_league_code' => 'swe.1',
        'name' => 'Swedish Allsvenskan',
        'region' => 'europe',
        'country' => 'Sweden',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    'den.1' => array(
        'e_league_code' => 'den.1',
        'name' => 'Danish Superliga',
        'region' => 'europe',
        'country' => 'Denmark',
        'tier' => 1,
        'type' => 'league',
        'priority' => 7,
    ),
    'irl.1' => array(
        'e_league_code' => 'irl.1',
        'name' => 'Irish Premier Division',
        'region' => 'europe',
        'country' => 'Ireland',
        'tier' => 1,
        'type' => 'league',
        'priority' => 5,
    ),
    
    // SOUTH AMERICA - Top-tier domestic leagues
    'arg.1' => array(
        'e_league_code' => 'arg.1',
        'name' => 'Argentine Liga Profesional',
        'region' => 'south_america',
        'country' => 'Argentina',
        'tier' => 1,
        'type' => 'league',
        'priority' => 8,
    ),
    'bra.1' => array(
        'e_league_code' => 'bra.1',
        'name' => 'Brazilian Serie A',
        'region' => 'south_america',
        'country' => 'Brazil',
        'tier' => 1,
        'type' => 'league',
        'priority' => 9,
    ),
    'bra.2' => array(
        'e_league_code' => 'bra.2',
        'name' => 'Brazilian Serie B',
        'region' => 'south_america',
        'country' => 'Brazil',
        'tier' => 2,
        'type' => 'league',
        'priority' => 8,
    ),
    'bra.3' => array(
        'e_league_code' => 'bra.3',
        'name' => 'Brazilian Serie C',
        'region' => 'south_america',
        'country' => 'Brazil',
        'tier' => 3,
        'type' => 'league',
        'priority' => 7,
    ),
    'bra.copa_do_brazil' => array(
        'e_league_code' => 'bra.copa_do_brazil',
        'name' => 'Copa do Brasil',
        'region' => 'south_america',
        'country' => 'Brazil',
        'tier' => 1,
        'type' => 'cup',
        'priority' => 8,
    ),
    'bra.camp.paulista' => array(
        'e_league_code' => 'bra.camp.paulista',
        'name' => 'Campeonato Paulista',
        'region' => 'south_america',
        'country' => 'Brazil',
        'tier' => 2,
        'type' => 'state_league',
        'priority' => 6,
    ),
    'bra.camp.carioca' => array(
        'e_league_code' => 'bra.camp.carioca',
        'name' => 'Campeonato Carioca',
        'region' => 'south_america',
        'country' => 'Brazil',
        'tier' => 2,
        'type' => 'state_league',
        'priority' => 6,
    ),
    'bra.camp.gaucho' => array(
        'e_league_code' => 'bra.camp.gaucho',
        'name' => 'Campeonato Gaúcho',
        'region' => 'south_america',
        'country' => 'Brazil',
        'tier' => 2,
        'type' => 'state_league',
        'priority' => 6,
    ),
    'bra.camp.mineiro' => array(
        'e_league_code' => 'bra.camp.mineiro',
        'name' => 'Campeonato Mineiro',
        'region' => 'south_america',
        'country' => 'Brazil',
        'tier' => 2,
        'type' => 'state_league',
        'priority' => 6,
    ),
    'bra.copa_do_nordeste' => array(
        'e_league_code' => 'bra.copa_do_nordeste',
        'name' => 'Copa do Nordeste',
        'region' => 'south_america',
        'country' => 'Brazil',
        'tier' => 3,
        'type' => 'regional_cup',
        'priority' => 5,
    ),
    'bra.supercopa_do_brazil' => array(
        'e_league_code' => 'bra.supercopa_do_brazil',
        'name' => 'Supercopa do Brasil',
        'region' => 'south_america',
        'country' => 'Brazil',
        'tier' => 1,
        'type' => 'supercup',
        'priority' => 7,
    ),
    'chi.1' => array(
        'e_league_code' => 'chi.1',
        'name' => 'Chilean Primera División',
        'region' => 'south_america',
        'country' => 'Chile',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    'col.1' => array(
        'e_league_code' => 'col.1',
        'name' => 'Colombian Primera A',
        'region' => 'south_america',
        'country' => 'Colombia',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    'uru.1' => array(
        'e_league_code' => 'uru.1',
        'name' => 'Uruguayan Primera División',
        'region' => 'south_america',
        'country' => 'Uruguay',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    'per.1' => array(
        'e_league_code' => 'per.1',
        'name' => 'Peruvian Liga 1',
        'region' => 'south_america',
        'country' => 'Peru',
        'tier' => 1,
        'type' => 'league',
        'priority' => 5,
    ),
    'ecu.1' => array(
        'e_league_code' => 'ecu.1',
        'name' => 'LigaPro Ecuador',
        'region' => 'south_america',
        'country' => 'Ecuador',
        'tier' => 1,
        'type' => 'league',
        'priority' => 5,
    ),
    'par.1' => array(
        'e_league_code' => 'par.1',
        'name' => 'Paraguayan Primera División',
        'region' => 'south_america',
        'country' => 'Paraguay',
        'tier' => 1,
        'type' => 'league',
        'priority' => 5,
    ),
    'bol.1' => array(
        'e_league_code' => 'bol.1',
        'name' => 'Bolivian Liga Profesional',
        'region' => 'south_america',
        'country' => 'Bolivia',
        'tier' => 1,
        'type' => 'league',
        'priority' => 5,
    ),
    'ven.1' => array(
        'e_league_code' => 'ven.1',
        'name' => 'Venezuelan Primera División',
        'region' => 'south_america',
        'country' => 'Venezuela',
        'tier' => 1,
        'type' => 'league',
        'priority' => 5,
    ),
    
    // OTHER KEY LEAGUES
    'ksa.1' => array(
        'e_league_code' => 'ksa.1',
        'name' => 'Saudi Pro League',
        'region' => 'asia',
        'country' => 'Saudi Arabia',
        'tier' => 1,
        'type' => 'league',
        'priority' => 8,
    ),
    'jpn.1' => array(
        'e_league_code' => 'jpn.1',
        'name' => 'Japanese J.League',
        'region' => 'asia',
        'country' => 'Japan',
        'tier' => 1,
        'type' => 'league',
        'priority' => 7,
    ),
    'aus.1' => array(
        'e_league_code' => 'aus.1',
        'name' => 'Australian A-League Men',
        'region' => 'oceania',
        'country' => 'Australia',
        'tier' => 1,
        'type' => 'league',
        'priority' => 6,
    ),
    
    // EUROPEAN AND ENGLISH CUPS
    'uefa.champions' => array(
        'e_league_code' => 'uefa.champions',
        'name' => 'UEFA Champions League',
        'region' => 'europe',
        'country' => null,
        'tier' => null,
        'type' => 'cup',
        'priority' => 10,
    ),
    'uefa.europa' => array(
        'e_league_code' => 'uefa.europa',
        'name' => 'UEFA Europa League',
        'region' => 'europe',
        'country' => null,
        'tier' => null,
        'type' => 'cup',
        'priority' => 9,
    ),
    'uefa.europa_conference' => array(
        'e_league_code' => 'uefa.europa_conference',
        'name' => 'UEFA Europa Conference League',
        'region' => 'europe',
        'country' => null,
        'tier' => null,
        'type' => 'cup',
        'priority' => 8,
    ),
    'eng.fa' => array(
        'e_league_code' => 'eng.fa',
        'name' => 'English FA Cup',
        'region' => 'europe',
        'country' => 'England',
        'tier' => null,
        'type' => 'cup',
        'priority' => 8,
    ),
    'eng.league_cup' => array(
        'e_league_code' => 'eng.league_cup',
        'name' => 'English League Cup',
        'region' => 'europe',
        'country' => 'England',
        'tier' => null,
        'type' => 'cup',
        'priority' => 7,
    ),
);

/**
 * Legacy competition configuration mapping
 * @deprecated This global is now generated dynamically by get_competitions_config()
 * which converts get_supported_leagues() into the legacy format for backward compatibility.
 * Direct access to this global is no longer needed.
 */

/**
 * HTTP request delay between calls (in microseconds)
 * Default: 200ms (200000 microseconds)
 */
if ( ! defined( 'FDM_E_HTTP_DELAY' ) ) {
    define( 'FDM_E_HTTP_DELAY', 200000 ); // 0.2 seconds
}

class FDM_E_Datasource_V2 {
    
    /**
     * Cache flag for whether matches.competition_code exists
     * null = unknown, true/false = checked
     *
     * @var bool|null
     */
    private static $has_competition_code = null;
    
    /**
     * HTTP wrapper for E API requests
     * Handles retries, throttling, and error logging
     * 
     * @param string $url Full URL to request
     * @param array $args Optional wp_remote_get arguments
     * @return array|WP_Error Response array with 'body' and 'code', or WP_Error on failure
     */
    private static function e_http_request( $url, $args = array() ) {
        $default_args = array(
            'timeout' => 15,
            'user-agent' => 'FootyForums Data Manager/1.0',
        );
        $args = wp_parse_args( $args, $default_args );
        
        // Log request start
        fdm_log_datasource_info( 'debug', 'E API request started', array( 'url' => $url ) );
        
        $response = wp_remote_get( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            fdm_log_datasource_error( 'api_error', 'E API request failed', array(
                'url' => $url,
                'error' => $error_message,
            ) );
            
            // Retry once on transient network errors
            if ( strpos( $error_message, 'timeout' ) !== false || 
                 strpos( $error_message, 'connection' ) !== false ) {
                fdm_log_datasource_info( 'info', 'Retrying E API request after transient error', array( 'url' => $url ) );
                usleep( 500000 ); // 0.5 second delay before retry
                $response = wp_remote_get( $url, $args );
                
                if ( is_wp_error( $response ) ) {
                    fdm_log_datasource_error( 'api_error', 'E API request failed on retry', array(
                        'url' => $url,
                        'error' => $response->get_error_message(),
                    ) );
                    return $response;
                }
            } else {
                return $response;
            }
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        
        // Retry once on 5xx server errors
        if ( $code >= 500 && $code < 600 ) {
            fdm_log_datasource_info( 'warning', 'E API returned 5xx, retrying', array(
                'url' => $url,
                'status' => $code,
            ) );
            usleep( 500000 ); // 0.5 second delay before retry
            $response = wp_remote_get( $url, $args );
            
            if ( is_wp_error( $response ) ) {
                fdm_log_datasource_error( 'api_error', 'E API request failed on retry', array(
                    'url' => $url,
                    'error' => $response->get_error_message(),
                ) );
                return $response;
            }
            
            $code = wp_remote_retrieve_response_code( $response );
        }
        
        // Log non-200 responses (but 404 is acceptable for date ranges with no matches)
        if ( $code !== 200 && $code !== 404 ) {
            fdm_log_datasource_error( 'api_error', 'E API returned non-200 status', array(
                'url' => $url,
                'status' => $code,
            ) );
        }
        
        // Throttle between requests
        usleep( FDM_E_HTTP_DELAY );
        
        return array(
            'body' => wp_remote_retrieve_body( $response ),
            'code' => $code,
            'response' => $response,
        );
    }
    
    /**
     * Normalize E status code to internal status_code
     * 
     * @param string $e_status E status string from API
     * @return string Normalized status_code
     */
    private static function normalize_status_code( $e_status ) {
        if ( empty( $e_status ) ) {
            return 'scheduled';
        }
        
        $status_lower = strtolower( $e_status );
        
        $status_map = array(
            'scheduled' => 'scheduled',
            'in progress' => 'in_progress',
            'live' => 'in_progress',
            'halftime' => 'in_progress',
            'final' => 'final',
            'full-time' => 'final',
            'ft' => 'final',
            'postponed' => 'postponed',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            'suspended' => 'suspended',
            'delayed' => 'delayed',
        );
        
        if ( isset( $status_map[ $status_lower ] ) ) {
            return $status_map[ $status_lower ];
        }
        
        // Default to scheduled if unknown
        return 'scheduled';
    }
    
    /**
     * Get or create club by e_team_id, with auto-creation for unknown teams
     * 
     * @param string $e_team_id E team ID
     * @param string $team_name Team display name (for placeholder creation)
     * @param bool $create_placeholder If true, create placeholder when not found
     * @return int|false Club ID or false on failure
     */
    private static function get_or_create_club_by_e_team_id( $e_team_id, $team_name = '', $create_placeholder = true ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            fdm_log_datasource_error( 'database_error', 'Cannot connect to footyforums_data database', array( 'e_team_id' => $e_team_id ) );
            return false;
        }
        
        // Try e_team_id first (preferred)
        $club = $db->get_row(
            $db->prepare(
                "SELECT id, canonical_name, e_team_id, e_id FROM clubs WHERE e_team_id = %s LIMIT 1",
                $e_team_id
            ),
            ARRAY_A
        );
        
        // Fallback to e_id for backward compatibility
        if ( ! $club ) {
            $club = $db->get_row(
                $db->prepare(
                    "SELECT id, canonical_name, e_team_id, e_id FROM clubs WHERE e_id = %s LIMIT 1",
                    $e_team_id
                ),
                ARRAY_A
            );
            
            // If found by e_id but e_team_id is NULL, populate it
            if ( $club && empty( $club['e_team_id'] ) ) {
                $db->update(
                    'clubs',
                    array( 'e_team_id' => $e_team_id, 'last_updated' => current_time( 'mysql' ) ),
                    array( 'id' => $club['id'] ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
            }
        }
        
        if ( $club ) {
            return (int) $club['id'];
        }
        
        // Club doesn't exist - create placeholder if allowed
        if ( ! $create_placeholder ) {
            return false;
        }
        
        $canonical_name = ! empty( $team_name ) ? $team_name : 'Unknown Team ' . $e_team_id;
        
        $result = $db->insert(
            'clubs',
            array(
                'canonical_name' => $canonical_name,
                'e_id' => $e_team_id, // Keep e_id for backward compatibility
                'e_team_id' => $e_team_id,
                'active_flag' => 0, // Placeholder clubs are inactive by default
                'needs_mapping' => 1, // Mark as needing manual mapping
                'date_added' => current_time( 'mysql' ),
                'last_updated' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );
        
        if ( $result === false ) {
            fdm_log_datasource_error( 'club_creation_failed', 'Failed to create placeholder club', array(
                'e_team_id' => $e_team_id,
                'name' => $canonical_name,
                'error' => $db->last_error,
            ) );
            return false;
        }
        
        fdm_log_datasource_info( 'warning', 'Created placeholder club for unknown e_team_id', array(
            'club_id' => $db->insert_id,
            'e_team_id' => $e_team_id,
            'name' => $canonical_name,
        ) );
        
        return (int) $db->insert_id;
    }
    
    /**
     * Get all supported leagues configuration
     * Single source of truth for league metadata
     * 
     * @return array Array keyed by e_league_code, each value contains:
     *               - e_league_code, name, region, country, tier, type, priority
     */
    public static function get_supported_leagues() {
        if ( isset( $GLOBALS['fdm_e_supported_leagues'] ) && is_array( $GLOBALS['fdm_e_supported_leagues'] ) ) {
            return $GLOBALS['fdm_e_supported_leagues'];
        }
        
        // Fallback: return minimal config if global not set
        return array();
    }
    
    /**
     * Get list of all supported e_league_code values
     * Sorted by priority (descending) so higher priority leagues are processed first
     * 
     * @return array Array of e_league_code strings, sorted by priority
     */
    public static function get_supported_league_codes() {
        $leagues = self::get_supported_leagues();
        
        // Sort by priority (descending), then by name for same priority
        uasort( $leagues, function( $a, $b ) {
            $priority_a = isset( $a['priority'] ) ? (int) $a['priority'] : 0;
            $priority_b = isset( $b['priority'] ) ? (int) $b['priority'] : 0;
            
            // First sort by priority (descending)
            if ( $priority_a !== $priority_b ) {
                return $priority_b - $priority_a;
            }
            
            // Then by name (ascending) for same priority
            $name_a = isset( $a['name'] ) ? $a['name'] : '';
            $name_b = isset( $b['name'] ) ? $b['name'] : '';
            return strcmp( $name_a, $name_b );
        } );
        
        return array_keys( $leagues );
    }
    
    /**
     * Get supported leagues sorted by priority (descending)
     * Useful for admin displays and CLI help text
     * 
     * @return array Array of league configs, sorted by priority
     */
    public static function get_supported_leagues_sorted() {
        $leagues = self::get_supported_leagues();
        
        // Sort by priority (descending), then by name for same priority
        uasort( $leagues, function( $a, $b ) {
            $priority_a = isset( $a['priority'] ) ? (int) $a['priority'] : 0;
            $priority_b = isset( $b['priority'] ) ? (int) $b['priority'] : 0;
            
            // First sort by priority (descending)
            if ( $priority_a !== $priority_b ) {
                return $priority_b - $priority_a;
            }
            
            // Then by name (ascending) for same priority
            $name_a = isset( $a['name'] ) ? $a['name'] : '';
            $name_b = isset( $b['name'] ) ? $b['name'] : '';
            return strcmp( $name_a, $name_b );
        } );
        
        return $leagues;
    }
    
    /**
     * Get competition configuration (legacy method, kept for backward compatibility)
     * 
     * @return array Competition configuration array
     * @deprecated Use get_supported_leagues() for new code
     */
    public static function get_competitions_config() {
        // Convert new structure to old format for backward compatibility
        $supported = self::get_supported_leagues();
        $legacy = array();
        
        foreach ( $supported as $e_league_code => $league_info ) {
            // Create a legacy key from league code (e.g., 'eng.1' -> 'eng_1' or use code as key)
            $key = str_replace( array( '.', '-', '_' ), '_', $e_league_code );
            $legacy[ $key ] = array(
                'league_code'      => $e_league_code,
                'competition_code' => $e_league_code,
                'division_name'    => $league_info['name'],
                'tier'             => $league_info['tier'],
                'backfill_method'  => 'scoreboard_dates',
            );
        }
        
        // Also check old global for any additional entries
        if ( isset( $GLOBALS['fdm_e_competitions'] ) && is_array( $GLOBALS['fdm_e_competitions'] ) ) {
            foreach ( $GLOBALS['fdm_e_competitions'] as $key => $config ) {
                if ( ! isset( $legacy[ $key ] ) && isset( $config['league_code'] ) ) {
                    $legacy[ $key ] = $config;
                }
            }
        }
        
        return $legacy;
    }
    
    /**
     * Sync leagues and seasons metadata from E API
     * 
     * @param array $leagueCodes Array of e_league_code values (e.g., ['eng.1', 'esp.1'])
     * @return array Result array with counts and per-league details
     */
    public static function e_datasource_sync_leagues( $leagueCodes = array() ) {
        $result = array(
            'input' => array(
                'league_codes' => $leagueCodes,
            ),
            'count_inserted' => 0,
            'count_updated' => 0,
            'count_errors' => 0,
            'count_skipped' => 0,
            'leagues' => array(),
            'warnings' => array(),
            'errors' => array(),
        );
        
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            $result['errors'][] = 'Cannot connect to footyforums_data database';
            $result['count_errors']++;
            return $result;
        }
        
        // If no league codes provided, get all from supported leagues config
        if ( empty( $leagueCodes ) ) {
            $leagueCodes = self::get_supported_league_codes();
        }
        
        foreach ( $leagueCodes as $e_league_code ) {
            $league_result = array(
                'e_league_code' => $e_league_code,
                'action' => 'skipped',
                'error' => null,
            );
            
            // Fetch league info from E teams endpoint (primary source for league metadata)
            $url = 'https://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $e_league_code ) . '/teams';
            $response = self::e_http_request( $url );
            
            // Only mark as error if HTTP request failed (after retry)
            if ( is_wp_error( $response ) || $response['code'] !== 200 ) {
                $error_msg = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $response['code'];
                $league_result['error'] = $error_msg;
                $league_result['action'] = 'error';
                $result['errors'][] = "Failed to fetch league $e_league_code: $error_msg";
                $result['count_errors']++;
                $result['leagues'][] = $league_result;
                continue;
            }
            
            $data = json_decode( $response['body'], true );
            
            // Validate JSON structure - must be able to find league name at minimum
            if ( ! is_array( $data ) ) {
                $league_result['error'] = 'Invalid JSON response';
                $league_result['action'] = 'error';
                $result['errors'][] = "Invalid JSON for league $e_league_code";
                $result['count_errors']++;
                $result['leagues'][] = $league_result;
                continue;
            }
            
            // Get league name from config as fallback (for off-season or missing ESPN data)
            $supported_leagues = self::get_supported_leagues();
            $config_name = isset( $supported_leagues[ $e_league_code ]['name'] ) 
                ? $supported_leagues[ $e_league_code ]['name'] 
                : $e_league_code;
            
            // Parse league metadata from teams endpoint structure
            // Expected structure: $data['sports'][0]['leagues'][0]
            $league_data = null;
            $league_name = $config_name; // Use config name as default fallback
            $season_year = null;
            $season_id = null;
            $calendar_start = null;
            $calendar_end = null;
            
            if ( isset( $data['sports'] ) && 
                 is_array( $data['sports'] ) && 
                 ! empty( $data['sports'][0] ) &&
                 isset( $data['sports'][0]['leagues'] ) &&
                 is_array( $data['sports'][0]['leagues'] ) &&
                 ! empty( $data['sports'][0]['leagues'][0] ) ) {
                $league_data = $data['sports'][0]['leagues'][0];
                
                // Extract league name
                if ( isset( $league_data['name'] ) ) {
                    $league_name = $league_data['name'];
                }
                
                // Extract season information
                if ( isset( $league_data['season'] ) && is_array( $league_data['season'] ) ) {
                    $season = $league_data['season'];
                    
                    if ( isset( $season['year'] ) ) {
                        $season_year = (int) $season['year'];
                    }
                    
                    if ( isset( $season['id'] ) ) {
                        $season_id = (string) $season['id'];
                    }
                    
                    // Get dates from season object if available
                    if ( isset( $season['startDate'] ) ) {
                        $calendar_start = $season['startDate'];
                    }
                    if ( isset( $season['endDate'] ) ) {
                        $calendar_end = $season['endDate'];
                    }
                }
                
                // If dates not in season object, check league level
                if ( empty( $calendar_start ) && isset( $league_data['seasonStartDate'] ) ) {
                    $calendar_start = $league_data['seasonStartDate'];
                }
                if ( empty( $calendar_end ) && isset( $league_data['seasonEndDate'] ) ) {
                    $calendar_end = $league_data['seasonEndDate'];
                }
            } else {
                // JSON structure doesn't match expected format
                // For off-season leagues, we still create/update the row with config data
                // Only mark as error if we truly cannot proceed
                // Use config name as fallback (already set above)
                // Continue to create/update league row with minimal data
                $result['warnings'][] = "League $e_league_code: API response structure unexpected, using config data only";
            }
            
            // Fallback to scoreboard endpoint only if season info is missing
            // This is optional - missing dates are not an error
            if ( empty( $season_year ) || empty( $season_id ) ) {
                $scoreboard_url = 'https://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $e_league_code ) . '/scoreboard';
                $scoreboard_response = self::e_http_request( $scoreboard_url );
                
                // Only use scoreboard if request succeeds, but don't fail if it doesn't
                if ( ! is_wp_error( $scoreboard_response ) && $scoreboard_response['code'] === 200 ) {
                    $scoreboard_data = json_decode( $scoreboard_response['body'], true );
                    
                    if ( is_array( $scoreboard_data ) ) {
                        // Check for season info in scoreboard response
                        if ( isset( $scoreboard_data['season'] ) && is_array( $scoreboard_data['season'] ) ) {
                            $scoreboard_season = $scoreboard_data['season'];
                            if ( empty( $season_year ) && isset( $scoreboard_season['year'] ) ) {
                                $season_year = (int) $scoreboard_season['year'];
                            }
                            if ( empty( $season_id ) && isset( $scoreboard_season['id'] ) ) {
                                $season_id = (string) $scoreboard_season['id'];
                            }
                            if ( empty( $calendar_start ) && isset( $scoreboard_season['startDate'] ) ) {
                                $calendar_start = $scoreboard_season['startDate'];
                            }
                            if ( empty( $calendar_end ) && isset( $scoreboard_season['endDate'] ) ) {
                                $calendar_end = $scoreboard_season['endDate'];
                            }
                        }
                        
                        // Also check league level in scoreboard
                        if ( isset( $scoreboard_data['leagues'] ) && 
                             is_array( $scoreboard_data['leagues'] ) &&
                             ! empty( $scoreboard_data['leagues'][0] ) ) {
                            $scoreboard_league = $scoreboard_data['leagues'][0];
                            if ( empty( $league_name ) && isset( $scoreboard_league['name'] ) ) {
                                $league_name = $scoreboard_league['name'];
                            }
                        }
                    }
                }
            }
            
            // Final fallback: use config name if ESPN didn't provide one
            if ( empty( $league_name ) || $league_name === $e_league_code ) {
                $league_name = $config_name;
            }
            
            // Convert date strings to YYYY-MM-DD format if present
            if ( ! empty( $calendar_start ) ) {
                $start_timestamp = strtotime( $calendar_start );
                if ( $start_timestamp !== false ) {
                    $calendar_start = gmdate( 'Y-m-d', $start_timestamp );
                } else {
                    $calendar_start = null; // Invalid date string, set to NULL
                }
            }
            
            if ( ! empty( $calendar_end ) ) {
                $end_timestamp = strtotime( $calendar_end );
                if ( $end_timestamp !== false ) {
                    $calendar_end = gmdate( 'Y-m-d', $end_timestamp );
                } else {
                    $calendar_end = null; // Invalid date string, set to NULL
                }
            }
            
            // Check if league exists
            $existing = $db->get_row(
                $db->prepare( "SELECT id, name, season_year FROM leagues WHERE e_league_code = %s LIMIT 1", $e_league_code ),
                ARRAY_A
            );
            
            // Build league data array (dates already converted to YYYY-MM-DD format above)
            $league_data = array(
                'e_league_code' => $e_league_code,
                'name' => $league_name,
                'current_season_id' => $season_id,
                'season_year' => $season_year,
                'calendar_start_date' => $calendar_start,
                'calendar_end_date' => $calendar_end,
                'last_synced' => current_time( 'mysql' ),
            );
            
            // Log Brazilian competitions sync attempt
            $brazilian_competitions = array(
                'bra.2', 'bra.3', 'bra.copa_do_brazil', 'bra.camp.paulista',
                'bra.camp.carioca', 'bra.camp.gaucho', 'bra.camp.mineiro',
                'bra.copa_do_nordeste', 'bra.supercopa_do_brazil'
            );
            if ( in_array( $e_league_code, $brazilian_competitions, true ) ) {
                $has_season_data = ! empty( $season_year ) || ! empty( $season_id );
                fdm_log_datasource_info( 'info', 'Brazilian competition sync attempt', array(
                    'e_league_code' => $e_league_code,
                    'name' => $league_name,
                    'has_season_data' => $has_season_data,
                    'season_year' => $season_year,
                    'season_id' => $season_id,
                    'calendar_start' => $calendar_start,
                    'calendar_end' => $calendar_end,
                ) );
            }
            
            if ( $existing ) {
                // Update existing league
                $updated = $db->update(
                    'leagues',
                    $league_data,
                    array( 'id' => $existing['id'] ),
                    array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' ),
                    array( '%d' )
                );
                
                if ( $updated !== false ) {
                    $league_result['action'] = 'updated';
                    $league_result['league_id'] = (int) $existing['id'];
                    $result['count_updated']++;
                } else {
                    $league_result['action'] = 'error';
                    $league_result['error'] = $db->last_error;
                    $result['count_errors']++;
                }
            } else {
                // Insert new league
                $inserted = $db->insert( 'leagues', $league_data, array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' ) );
                
                if ( $inserted !== false ) {
                    $league_result['action'] = 'inserted';
                    $league_result['league_id'] = (int) $db->insert_id;
                    $result['count_inserted']++;
                } else {
                    $league_result['action'] = 'error';
                    $league_result['error'] = $db->last_error;
                    $result['count_errors']++;
                }
            }
            
            $result['leagues'][] = $league_result;
        }
        
        fdm_log_datasource_info( 'info', 'League sync completed', array(
            'inserted' => $result['count_inserted'],
            'updated' => $result['count_updated'],
            'errors' => $result['count_errors'],
        ) );
        
        return $result;
    }
    
    /**
     * Sync clubs/teams for a specific league from E API
     * 
     * @param string $eLeagueCode E league code (e.g., 'eng.1')
     * @return array Result array with counts and details
     */
    public static function e_datasource_sync_clubs_for_league( $eLeagueCode ) {
        $result = array(
            'input' => array(
                'e_league_code' => $eLeagueCode,
            ),
            'count_inserted' => 0,
            'count_updated' => 0,
            'count_marked_inactive' => 0,
            'count_errors' => 0,
            'count_skipped' => 0,
            'clubs' => array(),
            'warnings' => array(),
            'errors' => array(),
        );
        
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            $result['errors'][] = 'Cannot connect to footyforums_data database';
            $result['count_errors']++;
            return $result;
        }
        
        if ( empty( $eLeagueCode ) ) {
            $result['errors'][] = 'e_league_code is required';
            $result['count_errors']++;
            return $result;
        }
        
        // Fetch teams from E teams endpoint
        $url = 'https://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $eLeagueCode ) . '/teams';
        $response = self::e_http_request( $url );
        
        if ( is_wp_error( $response ) || $response['code'] !== 200 ) {
            $error_msg = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $response['code'];
            $result['errors'][] = "Failed to fetch teams for $eLeagueCode: $error_msg";
            $result['count_errors']++;
            return $result;
        }
        
        $data = json_decode( $response['body'], true );
        if ( ! is_array( $data ) || empty( $data['sports'][0]['leagues'][0]['teams'] ) ) {
            $result['errors'][] = "Invalid API response structure for $eLeagueCode";
            $result['count_errors']++;
            return $result;
        }
        
        $teams = $data['sports'][0]['leagues'][0]['teams'];
        $found_e_team_ids = array();
        
        foreach ( $teams as $team_data ) {
            $team = isset( $team_data['team'] ) ? $team_data['team'] : $team_data;
            
            $e_team_id = isset( $team['id'] ) ? (string) $team['id'] : '';
            if ( empty( $e_team_id ) ) {
                $result['count_skipped']++;
                continue;
            }
            
            $found_e_team_ids[] = $e_team_id;
            
            // Extract team data
            $canonical_name = isset( $team['displayName'] ) ? $team['displayName'] : '';
            $full_name = isset( $team['name'] ) ? $team['name'] : $canonical_name;
            $short_name = isset( $team['shortDisplayName'] ) ? $team['shortDisplayName'] : '';
            $abbreviation = isset( $team['abbreviation'] ) ? $team['abbreviation'] : '';
            $slug = isset( $team['slug'] ) ? $team['slug'] : '';
            
            // Logos
            $logo_url_primary = null;
            $logo_url_alt = null;
            if ( isset( $team['logos'] ) && is_array( $team['logos'] ) ) {
                foreach ( $team['logos'] as $logo ) {
                    if ( isset( $logo['href'] ) ) {
                        if ( $logo_url_primary === null ) {
                            $logo_url_primary = $logo['href'];
                        } else {
                            $logo_url_alt = $logo['href'];
                            break;
                        }
                    }
                }
            }
            
            // Colors
            $primary_colour_hex = isset( $team['color'] ) ? $team['color'] : null;
            $secondary_colour_hex = isset( $team['alternateColor'] ) ? $team['alternateColor'] : null;
            
            // Venue
            $e_venue_id = null;
            $home_city = null;
            if ( isset( $team['location'] ) ) {
                $home_city = $team['location'];
            }
            if ( isset( $team['venue'] ) && isset( $team['venue']['id'] ) ) {
                $e_venue_id = (string) $team['venue']['id'];
            }
            
            // Check if club exists
            $existing = $db->get_row(
                $db->prepare( "SELECT id, e_team_id, e_id FROM clubs WHERE e_team_id = %s OR e_id = %s LIMIT 1", $e_team_id, $e_team_id ),
                ARRAY_A
            );
            
            $club_data = array(
                'e_team_id' => $e_team_id,
                'e_league_code' => $eLeagueCode,
                'canonical_name' => $canonical_name,
                'full_name' => $full_name,
                'short_name' => $short_name,
                'abbreviation' => $abbreviation,
                'slug' => $slug,
                'logo_url_primary' => $logo_url_primary,
                'logo_url_alt' => $logo_url_alt,
                'primary_colour_hex' => $primary_colour_hex,
                'secondary_colour_hex' => $secondary_colour_hex,
                'e_venue_id' => $e_venue_id,
                'home_city' => $home_city,
                'active_flag' => 1,
                'last_updated' => current_time( 'mysql' ),
            );
            
            // Ensure e_id is set for backward compatibility
            if ( empty( $club_data['e_id'] ) ) {
                $club_data['e_id'] = $e_team_id;
            }
            
            if ( $existing ) {
                // Update existing club
                $updated = $db->update(
                    'clubs',
                    $club_data,
                    array( 'id' => (int) $existing['id'] ),
                    array(
                        '%s', // e_team_id
                        '%s', // e_league_code
                        '%s', // canonical_name
                        '%s', // full_name
                        '%s', // short_name
                        '%s', // abbreviation
                        '%s', // slug
                        '%s', // logo_url_primary
                        '%s', // logo_url_alt
                        '%s', // primary_colour_hex
                        '%s', // secondary_colour_hex
                        '%s', // e_venue_id
                        '%s', // home_city
                        '%d', // active_flag
                        '%s', // last_updated
                        '%s', // e_id
                    ),
                    array( '%d' )
                );
                
                if ( $updated !== false ) {
                    // Backfill e_league_code on any duplicate rows for this ESPN team
                    // This also fixes rows that already exist with an e_id / e_team_id but no e_league_code
                    if ( ! empty( $club_data['e_league_code'] ) && ! empty( $e_team_id ) ) {
                        $db->query(
                            $db->prepare(
                                "UPDATE {$db->prefix}clubs
                                 SET e_league_code = %s
                                 WHERE e_team_id = %s
                                   AND (e_league_code IS NULL OR e_league_code = '')",
                                $club_data['e_league_code'],
                                $e_team_id
                            )
                        );
                    }
                    
                    $result['count_updated']++;
                } else {
                    $result['count_errors']++;
                    $result['errors'][] = "Failed to update club $e_team_id: " . $db->last_error;
                }
            } else {
                // Insert new club
                $club_data['date_added'] = current_time( 'mysql' );
                $inserted = $db->insert( 'clubs', $club_data, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ) );
                
                if ( $inserted !== false ) {
                    $result['count_inserted']++;
                } else {
                    $result['count_errors']++;
                    $result['errors'][] = "Failed to insert club $e_team_id: " . $db->last_error;
                }
            }
        }
        
        // Mark clubs that no longer appear in this league as inactive
        if ( ! empty( $found_e_team_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $found_e_team_ids ), '%s' ) );
            $marked = $db->query( $db->prepare(
                "UPDATE clubs SET active_flag = 0, last_updated = %s 
                 WHERE e_league_code = %s 
                 AND (e_team_id NOT IN ($placeholders) OR e_team_id IS NULL)
                 AND active_flag = 1",
                array_merge( array( current_time( 'mysql' ), $eLeagueCode ), $found_e_team_ids )
            ) );
            
            if ( $marked !== false && $marked > 0 ) {
                $result['count_marked_inactive'] = $marked;
            }
        }
        
        fdm_log_datasource_info( 'info', 'Club sync completed for league', array(
            'e_league_code' => $eLeagueCode,
            'inserted' => $result['count_inserted'],
            'updated' => $result['count_updated'],
            'marked_inactive' => $result['count_marked_inactive'],
        ) );
        
        return $result;
    }
    
    /**
     * Sync fixtures for a league over a date range
     * 
     * @param string $eLeagueCode E league code (e.g., 'eng.1')
     * @param string $dateFrom Start date (YYYY-MM-DD)
     * @param string $dateTo End date (YYYY-MM-DD)
     * @return array Result array with counts and per-date details
     */
    public static function e_datasource_sync_fixtures_for_league_range( $eLeagueCode, $dateFrom, $dateTo ) {
        $result = array(
            'input' => array(
                'e_league_code' => $eLeagueCode,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ),
            'count_inserted' => 0,
            'count_updated' => 0,
            'count_skipped' => 0,
            'count_errors' => 0,
            'dates' => array(),
            'warnings' => array(),
            'errors' => array(),
        );
        
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            $result['errors'][] = 'Cannot connect to footyforums_data database';
            $result['count_errors']++;
            return $result;
        }
        
        if ( empty( $eLeagueCode ) || empty( $dateFrom ) || empty( $dateTo ) ) {
            $result['errors'][] = 'e_league_code, date_from, and date_to are required';
            $result['count_errors']++;
            return $result;
        }
        
        // Validate date range
        $start_timestamp = strtotime( $dateFrom );
        $end_timestamp = strtotime( $dateTo );
        
        if ( $start_timestamp === false || $end_timestamp === false || $start_timestamp > $end_timestamp ) {
            $result['errors'][] = 'Invalid date range';
            $result['count_errors']++;
            return $result;
        }
        
        // Get competition_code (for fixtures, this mirrors e_league_code)
        // No change in behavior - competition_code equals e_league_code for all leagues
        $competition_code = $eLeagueCode;
        
        // Process each date in range
        $current_timestamp = $start_timestamp;
        $consecutive_errors = 0;
        $max_consecutive_errors = 5;
        
        while ( $current_timestamp <= $end_timestamp ) {
            $date_str = gmdate( 'Ymd', $current_timestamp );
            $date_display = gmdate( 'Y-m-d', $current_timestamp );
            
            $date_result = array(
                'date' => $date_display,
                'inserted' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
            );
            
            $url = 'https://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $eLeagueCode ) . '/scoreboard?dates=' . $date_str;
            $response = self::e_http_request( $url );
            
            if ( is_wp_error( $response ) ) {
                $consecutive_errors++;
                $date_result['errors']++;
                $result['count_errors']++;
                $result['errors'][] = "API error for $date_display: " . $response->get_error_message();
                
                if ( $consecutive_errors >= $max_consecutive_errors ) {
                    $result['errors'][] = "Aborting after $max_consecutive_errors consecutive errors";
                    break;
                }
                
                $current_timestamp = strtotime( '+1 day', $current_timestamp );
                $result['dates'][] = $date_result;
                continue;
            }
            
            if ( $response['code'] === 404 ) {
                // No matches on this date - not an error
                $consecutive_errors = 0;
                $current_timestamp = strtotime( '+1 day', $current_timestamp );
                $result['dates'][] = $date_result;
                continue;
            }
            
            if ( $response['code'] !== 200 ) {
                $consecutive_errors++;
                $date_result['errors']++;
                $result['count_errors']++;
                $result['errors'][] = "HTTP {$response['code']} for $date_display";
                
                if ( $consecutive_errors >= $max_consecutive_errors ) {
                    $result['errors'][] = "Aborting after $max_consecutive_errors consecutive errors";
                    break;
                }
                
                $current_timestamp = strtotime( '+1 day', $current_timestamp );
                $result['dates'][] = $date_result;
                continue;
            }
            
            $consecutive_errors = 0;
            
            $data = json_decode( $response['body'], true );
            if ( ! is_array( $data ) || empty( $data['events'] ) || ! is_array( $data['events'] ) ) {
                $current_timestamp = strtotime( '+1 day', $current_timestamp );
                $result['dates'][] = $date_result;
                continue;
            }
            
            // Process each event
            foreach ( $data['events'] as $event ) {
                $event_result = self::process_e_event_for_sync( $event, $competition_code, $eLeagueCode );
                
                if ( $event_result['action'] === 'inserted' ) {
                    $date_result['inserted']++;
                    $result['count_inserted']++;
                } elseif ( $event_result['action'] === 'updated' ) {
                    $date_result['updated']++;
                    $result['count_updated']++;
                } elseif ( $event_result['action'] === 'skipped' ) {
                    $date_result['skipped']++;
                    $result['count_skipped']++;
                } else {
                    $date_result['errors']++;
                    $result['count_errors']++;
                    if ( ! empty( $event_result['error'] ) ) {
                        $result['errors'][] = $event_result['error'];
                    }
                }
            }
            
            $current_timestamp = strtotime( '+1 day', $current_timestamp );
            $result['dates'][] = $date_result;
        }
        
        fdm_log_datasource_info( 'info', 'Fixture sync completed for league range', array(
            'e_league_code' => $eLeagueCode,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'inserted' => $result['count_inserted'],
            'updated' => $result['count_updated'],
            'errors' => $result['count_errors'],
        ) );
        
        return $result;
    }
    
    /**
     * Sync fixtures for a league
     * 
     * @param string $league_code League code (e.g., 'eng.1')
     * @return bool True on success
     * @throws Exception On failure
     */
    public function sync_fixtures_for_league( string $league_code ): bool {
        // Supported leagues for fixtures sync
        $supported_leagues = array( 'eng.1', 'esp.1', 'uefa.champions' );
        
        if ( ! in_array( $league_code, $supported_leagues, true ) ) {
            throw new Exception( "Fixtures sync not implemented for {$league_code}" );
        }
        
        // Calculate date window: now - 14 days to now + 180 days (UTC)
        $now_utc = time();
        $start_utc = $now_utc - ( 14 * DAY_IN_SECONDS );
        $end_utc = $now_utc + ( 180 * DAY_IN_SECONDS );
        
        // Format dates as Y-m-d (ISO date format, UTC)
        $date_from = gmdate( 'Y-m-d', $start_utc );
        $date_to = gmdate( 'Y-m-d', $end_utc );
        
        // Call the range-based sync method
        $result = self::e_datasource_sync_fixtures_for_league_range( $league_code, $date_from, $date_to );
        
        // Check for errors in the result
        if ( ! is_array( $result ) ) {
            throw new Exception( "Fixture sync failed: invalid result from range method" );
        }
        
        if ( isset( $result['count_errors'] ) && $result['count_errors'] > 0 ) {
            $error_messages = isset( $result['errors'] ) && is_array( $result['errors'] ) 
                ? implode( '; ', $result['errors'] ) 
                : 'Unknown error';
            throw new Exception( "Fixture sync failed for {$league_code}: {$error_messages}" );
        }
        
        return true;
    }
    
    /**
     * Sync fixtures for a league (static wrapper for backward compatibility)
     * 
     * @param string $league_code League code (e.g., 'eng.1')
     * @return bool True on success
     * @throws Exception On failure
     */
    public static function e_datasource_sync_fixtures_for_league( string $league_code ): bool {
        $instance = new self();
        return $instance->sync_fixtures_for_league( $league_code );
    }
    
    /**
     * Process a single E event and save to database
     * Internal helper for sync_fixtures_for_league_range
     * 
     * @param array $event E event data
     * @param string $competition_code Competition code
     * @param string $e_league_code League code
     * @return array Result with 'action' and optional 'error'
     */
    private static function process_e_event_for_sync( $event, $competition_code, $e_league_code ) {
        $result = array(
            'action' => 'skipped',
            'error' => null,
        );
        
        $e_event_id = isset( $event['id'] ) ? (string) $event['id'] : '';
        if ( empty( $e_event_id ) ) {
            $result['error'] = 'Event missing ID';
            return $result;
        }
        
        $e_match_id = (int) $e_event_id;
        
        // Extract team data
        $home_e_team_id = null;
        $away_e_team_id = null;
        $home_team_name = '';
        $away_team_name = '';
        $home_score = null;
        $away_score = null;
        
        if ( isset( $event['competitions'][0]['competitors'] ) && is_array( $event['competitions'][0]['competitors'] ) ) {
            foreach ( $event['competitions'][0]['competitors'] as $competitor ) {
                $team = isset( $competitor['team'] ) ? $competitor['team'] : array();
                $e_team_id = isset( $team['id'] ) ? (string) $team['id'] : '';
                $team_name = isset( $team['displayName'] ) ? $team['displayName'] : '';
                $score = isset( $competitor['score'] ) ? intval( $competitor['score'] ) : null;
                
                if ( isset( $competitor['homeAway'] ) && $competitor['homeAway'] === 'home' ) {
                    $home_e_team_id = $e_team_id;
                    $home_team_name = $team_name;
                    $home_score = $score;
                } else {
                    $away_e_team_id = $e_team_id;
                    $away_team_name = $team_name;
                    $away_score = $score;
                }
            }
        }
        
        if ( empty( $home_e_team_id ) || empty( $away_e_team_id ) ) {
            $result['error'] = "Event $e_event_id missing team IDs";
            return $result;
        }
        
        // Get or create clubs (with auto-creation for unknown teams)
        $home_club_id = self::get_or_create_club_by_e_team_id( $home_e_team_id, $home_team_name, true );
        $away_club_id = self::get_or_create_club_by_e_team_id( $away_e_team_id, $away_team_name, true );
        
        if ( ! $home_club_id || ! $away_club_id ) {
            $result['error'] = "Failed to resolve clubs for event $e_event_id";
            return $result;
        }
        
        // Extract date/time
        $event_date_raw = isset( $event['date'] ) ? $event['date'] : '';
        if ( empty( $event_date_raw ) ) {
            $result['error'] = "Event $e_event_id missing date";
            return $result;
        }
        
        $match_date = gmdate( 'Y-m-d', strtotime( $event_date_raw ) );
        $match_time = gmdate( 'H:i:s', strtotime( $event_date_raw ) );
        
        // Extract status
        $status = isset( $event['status']['type']['name'] ) ? $event['status']['type']['name'] : 'STATUS_SCHEDULED';
        $status_code = self::normalize_status_code( $status );
        $status_detail = isset( $event['status']['type']['shortDetail'] ) ? $event['status']['type']['shortDetail'] : null;
        
        // Check for postponed status
        $postponed_needs_review = 0;
        if ( $status_code === 'postponed' ) {
            $postponed_needs_review = 1;
        }
        
        // Extract venue info
        $neutral_venue = 0;
        $e_venue_id = null;
        if ( isset( $event['competitions'][0]['venue'] ) ) {
            $venue = $event['competitions'][0]['venue'];
            if ( isset( $venue['id'] ) ) {
                $e_venue_id = (string) $venue['id'];
            }
            // Check if neutral venue (usually indicated by neutralSite flag)
            if ( isset( $event['competitions'][0]['neutralSite'] ) && $event['competitions'][0]['neutralSite'] ) {
                $neutral_venue = 1;
            }
        }
        
        // Check if match exists
        $db = fdm_get_footyforums_db();
        $existing = $db->get_var(
            $db->prepare( "SELECT e_match_id FROM matches WHERE e_match_id = %d LIMIT 1", $e_match_id )
        );
        
        $match_data = array(
            'e_match_id' => $e_match_id,
            'competition_code' => $competition_code,
            'home_club_id' => $home_club_id,
            'away_club_id' => $away_club_id,
            'match_date' => $match_date,
            'home_goals' => $home_score,
            'away_goals' => $away_score,
            'status_code' => $status_code,
            'status_detail' => $status_detail,
            'neutral_venue_flag' => $neutral_venue,
            'postponed_needs_review_flag' => $postponed_needs_review,
            'last_synced' => current_time( 'mysql' ),
        );
        
        // Derive season_year from match_date if not already set
        if ( empty( $match_data['season_year'] ) && ! empty( $match_date ) ) {
            $derived_season_year = self::season_year_from_match_date( $match_date );
            if ( $derived_season_year !== null ) {
                $match_data['season_year'] = $derived_season_year;
                // Log when season_year is derived (debug level to avoid spam)
                fdm_log_datasource_info( 'debug', 'Derived season_year from match_date', array(
                    'e_match_id' => $e_match_id,
                    'league_code' => $competition_code,
                    'match_date' => $match_date,
                    'season_year' => $derived_season_year,
                ) );
            }
        }
        
        // Calculate result_code from scores
        if ( $home_score !== null && $away_score !== null ) {
            if ( $home_score > $away_score ) {
                $match_data['result_code'] = 'H';
            } elseif ( $away_score > $home_score ) {
                $match_data['result_code'] = 'A';
            } else {
                $match_data['result_code'] = 'D';
            }
        }
        
        // Stadium and referee (from existing logic)
        if ( isset( $event['competitions'][0]['venue']['fullName'] ) && ! empty( $event['competitions'][0]['venue']['fullName'] ) ) {
            $match_data['stadium'] = $event['competitions'][0]['venue']['fullName'];
        }
        
        if ( isset( $event['competitions'][0]['officials'] ) && is_array( $event['competitions'][0]['officials'] ) ) {
            foreach ( $event['competitions'][0]['officials'] as $official ) {
                if ( isset( $official['type']['name'] ) && $official['type']['name'] === 'Referee' ) {
                    if ( isset( $official['displayName'] ) && ! empty( $official['displayName'] ) ) {
                        $match_data['referee'] = $official['displayName'];
                        break;
                    }
                }
            }
        }
        
        // If postponed flag was set but we have a new date, clear it
        if ( $postponed_needs_review && $existing ) {
            $old_match = $db->get_row(
                $db->prepare( "SELECT match_date, postponed_needs_review_flag FROM matches WHERE e_match_id = %d", $e_match_id ),
                ARRAY_A
            );
            if ( $old_match && $old_match['match_date'] !== $match_date ) {
                // Date changed - clear review flag
                $match_data['postponed_needs_review_flag'] = 0;
            }
        }
        
        // Save match to footyforums_data.matches
        $saved = self::save_match_for_sync( $match_data );
        
        if ( $saved ) {
            $result['action'] = $existing ? 'updated' : 'inserted';
        } else {
            $result['action'] = 'error';
            $result['error'] = "Failed to save match $e_match_id";
        }
        
        // Also save to season database fixtures table
        $season_db_saved = self::save_fixture_to_season_db( $event, $e_match_id, $match_date, $competition_code, $home_e_team_id, $away_e_team_id, $home_score, $away_score, $status_code, $status_detail );
        if ( ! $season_db_saved && $saved ) {
            // Log warning but don't fail the sync
            fdm_log_datasource_info( 'warning', 'Failed to save fixture to season database', array( 'e_match_id' => $e_match_id ) );
        }
        
        return $result;
    }
    
    /**
     * Save match data for sync operations
     * Internal helper that handles INSERT/UPDATE with new columns
     * 
     * @param array $match_data Match data array
     * @return bool True on success, false on failure
     */
    private static function save_match_for_sync( $match_data ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            return false;
        }
        
        $e_match_id = (int) $match_data['e_match_id'];
        $home_club_id = (int) $match_data['home_club_id'];
        $away_club_id = (int) $match_data['away_club_id'];
        $match_date = $match_data['match_date'];
        
        // Build INSERT ... ON DUPLICATE KEY UPDATE
        $sql = "
            INSERT INTO matches (
                e_match_id,
                competition_code,
                home_club_id,
                away_club_id,
                match_date,
                season_year,
                home_goals,
                away_goals,
                result_code,
                status_code,
                status_detail,
                neutral_venue_flag,
                postponed_needs_review_flag,
                stadium,
                referee,
                last_synced
            ) VALUES (
                %d, %s, %d, %d, %s, %d, %s, %s, %s, %s, %s, %d, %d, %s, %s, %s
            )
            ON DUPLICATE KEY UPDATE
                home_club_id = VALUES(home_club_id),
                away_club_id = VALUES(away_club_id),
                match_date = VALUES(match_date),
                season_year = VALUES(season_year),
                home_goals = COALESCE(VALUES(home_goals), matches.home_goals),
                away_goals = COALESCE(VALUES(away_goals), matches.away_goals),
                result_code = COALESCE(VALUES(result_code), matches.result_code),
                status_code = VALUES(status_code),
                status_detail = VALUES(status_detail),
                neutral_venue_flag = VALUES(neutral_venue_flag),
                postponed_needs_review_flag = VALUES(postponed_needs_review_flag),
                stadium = COALESCE(VALUES(stadium), matches.stadium),
                referee = COALESCE(VALUES(referee), matches.referee),
                competition_code = COALESCE(VALUES(competition_code), matches.competition_code),
                last_synced = VALUES(last_synced)
        ";
        
        $prepared = $db->prepare(
            $sql,
            $e_match_id,
            $match_data['competition_code'],
            $home_club_id,
            $away_club_id,
            $match_date,
            isset( $match_data['season_year'] ) ? $match_data['season_year'] : null,
            $match_data['home_goals'],
            $match_data['away_goals'],
            isset( $match_data['result_code'] ) ? $match_data['result_code'] : null,
            $match_data['status_code'],
            $match_data['status_detail'],
            $match_data['neutral_venue_flag'],
            $match_data['postponed_needs_review_flag'],
            isset( $match_data['stadium'] ) ? $match_data['stadium'] : null,
            isset( $match_data['referee'] ) ? $match_data['referee'] : null,
            $match_data['last_synced']
        );
        
        $query_result = $db->query( $prepared );
        
        return $query_result !== false;
    }
    
    /**
     * Create fixtures table in season database with required structure
     * Uses canonical SQL template from sql/schema/templates/season_fixtures_table.sql
     * 
     * @param wpdb $db Database connection
     * @param string $season_db_name Season database name (e.g., 'e_2425')
     * @return bool True on success, false on failure
     */
    private static function create_season_fixtures_table( $db, $season_db_name ) {
        // Check if table already exists (idempotent)
        $table_exists = $db->get_var(
            $db->prepare( "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = 'fixtures'", $season_db_name )
        );
        if ( $table_exists ) {
            return true; // Table exists, nothing to do
        }
        
        // Load SQL template from canonical file
        // Path: app/sql/schema/templates/season_fixtures_table.sql (relative to WordPress root)
        $template_path = ABSPATH . '../sql/schema/templates/season_fixtures_table.sql';
        
        if ( ! file_exists( $template_path ) ) {
            // Fallback: try relative to plugin directory
            $template_path = FDM_PLUGIN_DIR . '../../../../sql/schema/templates/season_fixtures_table.sql';
            $template_path = realpath( $template_path );
        }
        
        if ( ! file_exists( $template_path ) || ! is_readable( $template_path ) ) {
            fdm_log_datasource_info( 'error', 'Season fixtures table template file not found', array(
                'season_db_name' => $season_db_name,
                'template_path' => $template_path,
            ) );
            return false;
        }
        
        // Read template file
        $template_sql = file_get_contents( $template_path );
        if ( $template_sql === false ) {
            fdm_log_datasource_info( 'error', 'Failed to read season fixtures table template', array(
                'season_db_name' => $season_db_name,
                'template_path' => $template_path,
            ) );
            return false;
        }
        
        // Escape database name for use in SQL (template already has backticks, so only escape internal ones)
        $db_name_escaped = str_replace( '`', '``', $season_db_name );
        
        // Replace placeholder with actual database name
        $sql = str_replace( '{DATABASE_NAME}', $db_name_escaped, $template_sql );
        
        // Execute the SQL
        $result = $db->query( $sql );
        
        if ( $result === false ) {
            fdm_log_datasource_info( 'error', 'Failed to create season fixtures table from template', array(
                'season_db_name' => $season_db_name,
                'error' => $db->last_error,
            ) );
            return false;
        }
        
        return true;
    }
    
    /**
     * Save fixture to season database (e_YYYY format)
     * Writes to both ESPN source columns and normalized columns
     * 
     * @param array $event Event data from ESPN API
     * @param int $e_match_id Match ID
     * @param string $match_date Match date (Y-m-d format)
     * @param string $league_code League code (e.g., 'eng.1')
     * @param string $home_e_team_id Home team ESPN ID
     * @param string $away_e_team_id Away team ESPN ID
     * @param int|null $home_score Home score
     * @param int|null $away_score Away score
     * @param string $status_code Status code
     * @param string|null $status_detail Status detail
     * @return bool True on success, false on failure
     */
    private static function save_fixture_to_season_db( $event, $e_match_id, $match_date, $league_code, $home_e_team_id, $away_e_team_id, $home_score, $away_score, $status_code, $status_detail ) {
        // Rate limiting: track logged messages per job run (static array)
        static $logged_missing_db = array();
        static $logged_missing_table = array();
        
        // Calculate season database name from match date
        // Format: e_YYZZ where YY is start year (2 digits) and ZZ is end year (2 digits)
        $timestamp = strtotime( $match_date );
        $year = (int) gmdate( 'Y', $timestamp );
        $month = (int) gmdate( 'n', $timestamp );
        
        // Season starts in July/August, so if month >= 7, season is YY-(YY+1), else (YY-1)-YY
        if ( $month >= 7 ) {
            $season_start_year = $year;
        } else {
            $season_start_year = $year - 1;
        }
        $season_end_year = $season_start_year + 1;
        
        // Format as 2-digit years: e_2425 for 2024-25
        $season_db_name = 'e_' . sprintf( '%02d%02d', $season_start_year % 100, $season_end_year % 100 );
        
        // Use provided league_code, or extract from event if not provided
        if ( empty( $league_code ) && is_array( $event ) ) {
            if ( isset( $event['league']['slug'] ) ) {
                $league_code = $event['league']['slug'];
            } elseif ( isset( $event['competitions'][0]['league']['slug'] ) ) {
                $league_code = $event['competitions'][0]['league']['slug'];
            }
        }
        
        // Get database connection to the season database
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            return false;
        }
        
        // Check if season database exists, create if missing
        $db_exists = $db->get_var(
            $db->prepare( "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = %s", $season_db_name )
        );
        if ( ! $db_exists ) {
            // Season database doesn't exist - create it
            $db_name_escaped = '`' . str_replace( '`', '``', $season_db_name ) . '`';
            $create_db_result = $db->query( "CREATE DATABASE IF NOT EXISTS {$db_name_escaped} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );
            
            if ( $create_db_result === false ) {
                // Log failure but don't hard-fail the ingest
                if ( ! isset( $logged_missing_db[ $season_db_name ] ) ) {
                    $logged_missing_db[ $season_db_name ] = true;
                    fdm_log_datasource_info( 'info', 'Failed to create season database', array(
                        'season_db_name' => $season_db_name,
                        'e_match_id' => $e_match_id,
                        'error' => $db->last_error,
                    ) );
                }
                return true; // Return true to not fail the ingest
            }
            
            // Log successful creation once per job run
            if ( ! isset( $logged_missing_db[ $season_db_name ] ) ) {
                $logged_missing_db[ $season_db_name ] = true;
                fdm_log_datasource_info( 'info', 'Created season database', array(
                    'season_db_name' => $season_db_name,
                    'season_range' => sprintf( '%d-%d', $season_start_year, $season_end_year ),
                ) );
            }
        }
        
        // Check if fixtures table exists in the season database, create if missing
        $table_exists = $db->get_var(
            $db->prepare( "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = 'fixtures'", $season_db_name )
        );
        if ( ! $table_exists ) {
            // Fixtures table doesn't exist - create it
            $create_table_result = self::create_season_fixtures_table( $db, $season_db_name );
            
            if ( $create_table_result === false ) {
                // Log failure but don't hard-fail the ingest
                if ( ! isset( $logged_missing_table[ $season_db_name ] ) ) {
                    $logged_missing_table[ $season_db_name ] = true;
                    fdm_log_datasource_info( 'info', 'Failed to create season fixtures table', array(
                        'season_db_name' => $season_db_name,
                        'e_match_id' => $e_match_id,
                        'error' => $db->last_error,
                    ) );
                }
                return true; // Return true to not fail the ingest
            }
            
            // Log successful creation once per job run
            if ( ! isset( $logged_missing_table[ $season_db_name ] ) ) {
                $logged_missing_table[ $season_db_name ] = true;
                fdm_log_datasource_info( 'info', 'Created season fixtures table', array(
                    'season_db_name' => $season_db_name,
                ) );
            }
        }
        
        // Extract statusid from event if available
        $statusid = null;
        if ( isset( $event['status']['type']['id'] ) ) {
            $statusid = (int) $event['status']['type']['id'];
        }
        
        // TODO: Map statusid to status_state/status_detail if there's a status lookup table in the season DB
        // For now, using status_code directly as status_state
        // If a status table exists (keyed by statusid), we should look up status_state and status_detail from it
        
        // Prepare raw_json if available
        $raw_json = null;
        if ( is_array( $event ) ) {
            $raw_json = wp_json_encode( $event );
        }
        
        // Derive season_year from match_date
        $season_year = null;
        if ( ! empty( $match_date ) ) {
            $season_year = self::season_year_from_match_date( $match_date );
        }
        
        // Escape database name (we control the format, but be safe)
        $db_name_escaped = '`' . str_replace( '`', '``', $season_db_name ) . '`';
        
        // Build INSERT ... ON DUPLICATE KEY UPDATE query
        // ESPN source columns: hometeamid, awayteamid, hometeamscore, awayteamscore, statusid
        // Normalized columns: home_team_id, away_team_id, home_score, away_score, status_state, status_detail, updated_at, raw_json, season_year
        $sql = $db->prepare(
            "
            INSERT INTO {$db_name_escaped}.`fixtures` (
                e_match_id,
                league_code,
                season_year,
                match_date,
                hometeamid,
                awayteamid,
                hometeamscore,
                awayteamscore,
                statusid,
                home_team_id,
                away_team_id,
                home_score,
                away_score,
                status_state,
                status_detail,
                updated_at,
                raw_json
            ) VALUES (
                %d, %s, %d, %s, %s, %s, %d, %d, %d, %s, %s, %d, %d, %s, %s, UTC_TIMESTAMP(), %s
            )
            ON DUPLICATE KEY UPDATE
                hometeamid = VALUES(hometeamid),
                awayteamid = VALUES(awayteamid),
                hometeamscore = COALESCE(VALUES(hometeamscore), fixtures.hometeamscore),
                awayteamscore = COALESCE(VALUES(awayteamscore), fixtures.awayteamscore),
                statusid = COALESCE(VALUES(statusid), fixtures.statusid),
                home_team_id = VALUES(home_team_id),
                away_team_id = VALUES(away_team_id),
                home_score = VALUES(home_score),
                away_score = VALUES(away_score),
                status_state = VALUES(status_state),
                status_detail = VALUES(status_detail),
                season_year = VALUES(season_year),
                updated_at = UTC_TIMESTAMP(),
                raw_json = COALESCE(VALUES(raw_json), fixtures.raw_json)
            ",
            $e_match_id,
            $league_code,
            $season_year,
            $match_date,
            $home_e_team_id,
            $away_e_team_id,
            $home_score,
            $away_score,
            $statusid,
            $home_e_team_id, // home_team_id = hometeamid
            $away_e_team_id, // away_team_id = awayteamid
            $home_score,     // home_score = hometeamscore
            $away_score,     // away_score = awayteamscore
            $status_code,    // status_state
            $status_detail,  // status_detail
            $raw_json        // raw_json
        );
        
        $query_result = $db->query( $sql );
        
        if ( $query_result === false ) {
            // Log failure but don't hard-fail the ingest
            fdm_log_datasource_info( 'info', 'Failed to save fixture to season database', array(
                'season_db' => $season_db_name,
                'e_match_id' => $e_match_id,
                'error' => $db->last_error,
            ) );
            return false;
        }
        
        // Log successful upsert (lightweight, no rate limit needed for success)
        fdm_log_datasource_info( 'info', 'Season fixtures upsert ok', array(
            'season_db_name' => $season_db_name,
            'e_match_id' => $e_match_id,
        ) );
        
        return true;
    }
    
    /**
     * Get or create canonical club by E ID
     * 
     * @param string $e_id E team ID
     * @param string $e_name E team display name
     * @return int|false Club ID or false on failure
     */
    public static function get_or_create_club( $e_id, $e_name ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            fdm_log_datasource_error( 'database_error', 'Cannot connect to footyforums_data database', array( 'e_id' => $e_id ) );
            return false;
        }
        
        // Look up by e_id first (strongest identifier)
        $club = $db->get_row(
            $db->prepare(
                "SELECT id, canonical_name, e_id FROM clubs WHERE e_id = %s",
                $e_id
            ),
            ARRAY_A
        );
        
        if ( $club ) {
            // Club exists - update last_updated if e_id was missing before
            if ( empty( $club['e_id'] ) ) {
                $db->update(
                    'clubs',
                    array( 'e_id' => $e_id, 'last_updated' => current_time( 'mysql' ) ),
                    array( 'id' => $club['id'] ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
            }
            return (int) $club['id'];
        }
        
        // Club doesn't exist - create new one
        $result = $db->insert(
            'clubs',
            array(
                'canonical_name' => $e_name,
                'e_id' => $e_id,
                'date_added' => current_time( 'mysql' ),
                'last_updated' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );
        
        if ( $result === false ) {
            fdm_log_datasource_error( 'club_creation_failed', 'Failed to create club', array( 'e_id' => $e_id, 'name' => $e_name, 'error' => $db->last_error ) );
            return false;
        }
        
        fdm_log_datasource_info( 'info', 'Created new club', array( 'club_id' => $db->insert_id, 'e_id' => $e_id, 'name' => $e_name ) );
        return (int) $db->insert_id;
    }
    
    /**
     * Get or create season
     * 
     * @param int $year Season start year (e.g., 2024 for 2024-25)
     * @param string $division Division name (e.g., 'Premier League')
     * @param int|null $tier Tier number (e.g., 1 for Premier League)
     * @return int|false Season ID or false on failure
     */
    public static function get_or_create_season( $year, $division, $tier = null ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            fdm_log_datasource_error( 'database_error', 'Cannot connect to footyforums_data database', array( 'year' => $year, 'division' => $division ) );
            return false;
        }
        
        // Look up existing season
        $season = $db->get_row(
            $db->prepare(
                "SELECT id FROM seasons WHERE year = %d AND division = %s" . ( $tier !== null ? " AND tier = %d" : "" ),
                array_merge( array( $year, $division ), $tier !== null ? array( $tier ) : array() )
            ),
            ARRAY_A
        );
        
        if ( $season ) {
            return (int) $season['id'];
        }
        
        // Season doesn't exist - create it
        $insert_data = array(
            'year' => $year,
            'division' => $division,
            'date_added' => current_time( 'mysql' ),
            'last_updated' => current_time( 'mysql' ),
        );
        $insert_format = array( '%d', '%s', '%s', '%s' );
        
        if ( $tier !== null ) {
            $insert_data['tier'] = $tier;
            $insert_format[] = '%d';
        }
        
        $result = $db->insert( 'seasons', $insert_data, $insert_format );
        
        if ( $result === false ) {
            fdm_log_datasource_error( 'season_creation_failed', 'Failed to create season', array( 'year' => $year, 'division' => $division, 'error' => $db->last_error ) );
            return false;
        }
        
        fdm_log_datasource_info( 'info', 'Created new season', array( 'season_id' => $db->insert_id, 'year' => $year, 'division' => $division ) );
        return (int) $db->insert_id;
    }
    
    /**
     * Get competition mapping (E code to division)
     * 
     * @param string $e_code E competition code (e.g., 'eng.1')
     * @return array|false Array with 'division_name' and 'tier', or false on failure
     */
    public static function get_competition_mapping( $e_code ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            return false;
        }
        
        $mapping = $db->get_row(
            $db->prepare( "SELECT division_name, tier FROM competition_map WHERE espn_code = %s", $e_code ),
            ARRAY_A
        );
        
        if ( $mapping ) {
            return $mapping;
        }
        
        // Default mapping for Premier League if not found
        if ( $e_code === 'eng.1' ) {
            return array( 'division_name' => 'Premier League', 'tier' => 1 );
        }
        
        return false;
    }
    
    /**
     * Calculate season year from match date
     * August 2024 → 2024, May 2025 → still 2024
     * 
     * @param string $match_date Match date (Y-m-d format)
     * @return int Season start year
     */
    public static function calculate_season_year( $match_date ) {
        $timestamp = strtotime( $match_date );
        $year = (int) date( 'Y', $timestamp );
        $month = (int) date( 'n', $timestamp );
        
        if ( $month >= 7 ) {
            return $year;
        } else {
            return $year - 1;
        }
    }
    
    /**
     * Derive season year from match date (UTC-based)
     * If month >= 7, season_year = YEAR(match_date)
     * Else season_year = YEAR(match_date) - 1
     * 
     * @param string $match_date Match date string (YYYY-MM-DD format)
     * @return int|null Season year, or null if input is empty or invalid
     */
    private static function season_year_from_match_date( $match_date ) {
        if ( empty( $match_date ) ) {
            return null;
        }
        
        $timestamp = strtotime( $match_date );
        if ( $timestamp === false ) {
            return null;
        }
        
        $year = (int) gmdate( 'Y', $timestamp );
        $month = (int) gmdate( 'n', $timestamp );
        
        if ( $month >= 7 ) {
            return $year;
        } else {
            return $year - 1;
        }
    }
    
    /**
     * Validate match data before insertion
     * 
     * @param array $match_data Match data array
     * @return array|false Validated match data or false on validation failure
     */
    public static function validate_match_data( $match_data ) {
        if ( empty( $match_data['e_match_id'] ) ) {
            fdm_log_datasource_error( 'validation_error', 'e_match_id is required', $match_data );
            return false;
        }
        
        if ( empty( $match_data['home_club_id'] ) || ! is_numeric( $match_data['home_club_id'] ) || (int) $match_data['home_club_id'] <= 0 ) {
            fdm_log_datasource_error( 'validation_error', 'home_club_id must be integer > 0', $match_data );
            return false;
        }
        
        if ( empty( $match_data['away_club_id'] ) || ! is_numeric( $match_data['away_club_id'] ) || (int) $match_data['away_club_id'] <= 0 ) {
            fdm_log_datasource_error( 'validation_error', 'away_club_id must be integer > 0', $match_data );
            return false;
        }
        
        if ( ! empty( $match_data['match_date'] ) ) {
            $year = (int) date( 'Y', strtotime( $match_data['match_date'] ) );
            if ( $year < 1800 ) {
                fdm_log_datasource_error( 'validation_error', 'Match date must be >= 1800', $match_data );
                return false;
            }
        }
        
        return $match_data;
    }
    
        /**
     * Insert or update match in matches table using e_match_id as unique key
     * 
     * @param array $match_data Match data with exact schema column names
     * @return bool True on success, false on failure
     */
    public static function save_match( $match_data ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            fdm_log_datasource_error( 'database_error', 'Cannot connect to footyforums_data database', $match_data );
            return false;
        }
        
        // Validate match data
        $validated = self::validate_match_data( $match_data );
        if ( $validated === false ) {
            return false;
        }
        
        // Required fields
        $e_match_id    = (int) $match_data['e_match_id'];
        $home_club_id  = (int) $match_data['home_club_id'];
        $away_club_id  = (int) $match_data['away_club_id'];
        $match_date    = $match_data['match_date'];
        
        // Optional fields - scores
        $home_goals = isset( $match_data['home_goals'] ) && $match_data['home_goals'] !== null
            ? (int) $match_data['home_goals']
            : null;
        $away_goals = isset( $match_data['away_goals'] ) && $match_data['away_goals'] !== null
            ? (int) $match_data['away_goals']
            : null;
        
        // Calculate result_code from scores
        $result_code = null;
        if ( $home_goals !== null && $away_goals !== null ) {
            if ( $home_goals > $away_goals ) {
                $result_code = 'H';
            } elseif ( $away_goals > $home_goals ) {
                $result_code = 'A';
            } else {
                $result_code = 'D';
            }
        }
        
        // Optional fields - stadium and referee
        $stadium = isset( $match_data['stadium'] ) && $match_data['stadium'] !== ''
            ? $match_data['stadium']
            : null;
        $referee = isset( $match_data['referee'] ) && $match_data['referee'] !== ''
            ? $match_data['referee']
            : null;
        
        // Optional field - competition_code
        $competition_code = isset( $match_data['competition_code'] ) && $match_data['competition_code'] !== ''
            ? $match_data['competition_code']
            : null;
        
        // Build SQL with ON DUPLICATE KEY UPDATE
        $sql = "
            INSERT INTO matches (
                e_match_id,
                competition_code,
                home_club_id,
                away_club_id,
                match_date,
                home_goals,
                away_goals,
                result_code,
                stadium,
                referee
            ) VALUES (
                %d,
                %s,
                %d,
                %d,
                %s,
                %s,
                %s,
                %s,
                %s,
                %s
            )
            ON DUPLICATE KEY UPDATE
                home_club_id     = VALUES(home_club_id),
                away_club_id     = VALUES(away_club_id),
                match_date       = VALUES(match_date),
                home_goals       = COALESCE(VALUES(home_goals), matches.home_goals),
                away_goals       = COALESCE(VALUES(away_goals), matches.away_goals),
                result_code      = COALESCE(VALUES(result_code), matches.result_code),
                stadium          = COALESCE(VALUES(stadium), matches.stadium),
                referee          = COALESCE(VALUES(referee), matches.referee),
                competition_code = COALESCE(VALUES(competition_code), matches.competition_code)
        ";
        
        $prepared = $db->prepare(
            $sql,
            $e_match_id,
            $competition_code,
            $home_club_id,
            $away_club_id,
            $match_date,
            $home_goals,
            $away_goals,
            $result_code,
            $stadium,
            $referee
        );
        
        $result = $db->query( $prepared );
        
        if ( $result === false ) {
            fdm_log_datasource_error(
                'match_save_failed',
                'Failed to save match',
                array(
                    'e_match_id' => $e_match_id,
                    'error'      => $db->last_error,
                )
            );
            return false;
        }
        
        return true;
    }
    /**
     * Save match extras data
     * 
     * @param string $match_id Match ID
     * @param array $extras_data Extras data
     * @return bool True on success, false on failure
     */
    public static function save_match_extras( $match_id, $extras_data ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            return false;
        }
        
        // We no longer write venue/referee into match_extras.
        // Only attendance, match_status, and half-time scores are maintained.
        $existing = $db->get_var(
            $db->prepare(
                "SELECT match_id FROM match_extras WHERE match_id = %s LIMIT 1",
                $match_id
            )
        );
        
        if ( ! $existing ) {
            // Insert new row with extras-only fields.
            $insert_data = array( 'match_id' => $match_id );
            $insert_format = array( '%s' );
            
            if ( isset( $extras_data['attendance'] ) && $extras_data['attendance'] !== null ) {
                $insert_data['attendance'] = (int) $extras_data['attendance'];
                $insert_format[]           = '%d';
            }
            
            if ( ! empty( $extras_data['match_status'] ) ) {
                $insert_data['match_status'] = $extras_data['match_status'];
                $insert_format[]             = '%s';
            }
            
            if ( isset( $extras_data['half_time_home_score'] ) && $extras_data['half_time_home_score'] !== null ) {
                $insert_data['half_time_home_score'] = (int) $extras_data['half_time_home_score'];
                $insert_format[]                     = '%d';
            }
            
            if ( isset( $extras_data['half_time_away_score'] ) && $extras_data['half_time_away_score'] !== null ) {
                $insert_data['half_time_away_score'] = (int) $extras_data['half_time_away_score'];
                $insert_format[]                     = '%d';
            }
            
            return $db->insert( 'match_extras', $insert_data, $insert_format ) !== false;
        }
        
        // Update existing row, touching only extras columns so older venue/referee values remain untouched.
        $update_data = array();
        $update_format = array();
        
        if ( isset( $extras_data['attendance'] ) && $extras_data['attendance'] !== null ) {
            $update_data['attendance'] = (int) $extras_data['attendance'];
            $update_format[]           = '%d';
        }
        
        if ( ! empty( $extras_data['match_status'] ) ) {
            $update_data['match_status'] = $extras_data['match_status'];
            $update_format[]             = '%s';
        }
        
        if ( isset( $extras_data['half_time_home_score'] ) && $extras_data['half_time_home_score'] !== null ) {
            $update_data['half_time_home_score'] = (int) $extras_data['half_time_home_score'];
            $update_format[]                     = '%d';
        }
        
        if ( isset( $extras_data['half_time_away_score'] ) && $extras_data['half_time_away_score'] !== null ) {
            $update_data['half_time_away_score'] = (int) $extras_data['half_time_away_score'];
            $update_format[]                     = '%d';
        }
        
        if ( empty( $update_data ) ) {
            // Nothing to update.
            return true;
        }
        
        return $db->update(
            'match_extras',
            $update_data,
            array( 'match_id' => $match_id ),
            $update_format,
            array( '%s' )
        ) !== false;
    }
    
    /**
     * Process E event and save to database
     * 
     * @param array  $event            E event data
     * @param string $competition_code Competition code to store in matches.competition_code (e.g., 'eng.1')
     * @return bool True on success, false on failure
     */
    public static function process_e_event( $event, $competition_code ) {
        $event_id = isset( $event['id'] ) ? (string) $event['id'] : '';
        if ( empty( $event_id ) ) {
            fdm_log_datasource_error( 'invalid_event', 'Event missing ID', $event );
            return false;
        }
        $e_match_id = (int) $event_id;
        
        $home_e_team_id = null;
        $away_e_team_id = null;
        $home_team_name = '';
        $away_team_name = '';
        $home_score     = null;
        $away_score     = null;
        
        if ( isset( $event['competitions'][0]['competitors'] ) && is_array( $event['competitions'][0]['competitors'] ) ) {
            foreach ( $event['competitions'][0]['competitors'] as $competitor ) {
                $e_team_id = isset( $competitor['team']['id'] ) ? (string) $competitor['team']['id'] : '';
                $team_name = isset( $competitor['team']['displayName'] ) ? $competitor['team']['displayName'] : '';
                $score     = isset( $competitor['score'] ) ? intval( $competitor['score'] ) : null;
                
                if ( isset( $competitor['homeAway'] ) && $competitor['homeAway'] === 'home' ) {
                    $home_e_team_id = $e_team_id;
                    $home_team_name = $team_name;
                    $home_score     = $score;
                } else {
                    $away_e_team_id = $e_team_id;
                    $away_team_name = $team_name;
                    $away_score     = $score;
                }
            }
        }
        
        if ( empty( $home_e_team_id ) || empty( $away_e_team_id ) ) {
            fdm_log_datasource_error( 'invalid_event', 'Event missing team IDs', array( 'e_match_id' => $e_match_id ) );
            return false;
        }
        
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            fdm_log_datasource_error( 'database_error', 'Cannot connect to footyforums_data database', array( 'e_match_id' => $e_match_id ) );
            return false;
        }
        
        $home_club = $db->get_row(
            $db->prepare( "SELECT id FROM clubs WHERE e_id = %s LIMIT 1", $home_e_team_id ),
            ARRAY_A
        );
        
        $away_club = $db->get_row(
            $db->prepare( "SELECT id FROM clubs WHERE e_id = %s LIMIT 1", $away_e_team_id ),
            ARRAY_A
        );
        
        if ( ! $home_club || ! $away_club ) {
            fdm_log_datasource_error(
                'club_mapping_failed',
                'Failed to map clubs by e_id',
                array(
                    'e_match_id'       => $e_match_id,
                    'home_e_id'        => $home_e_team_id,
                    'home_name'        => $home_team_name,
                    'away_e_id'        => $away_e_team_id,
                    'away_name'        => $away_team_name,
                    'home_club_found'  => ! empty( $home_club ),
                    'away_club_found'  => ! empty( $away_club ),
                )
            );
            return false;
        }
        
        $home_club_id = (int) $home_club['id'];
        $away_club_id = (int) $away_club['id'];
        
        $event_date_raw = isset( $event['date'] ) ? $event['date'] : '';
        if ( empty( $event_date_raw ) ) {
            fdm_log_datasource_error( 'invalid_event', 'Event missing date', array( 'e_match_id' => $e_match_id ) );
            return false;
        }
        
        $match_date = gmdate( 'Y-m-d', strtotime( $event_date_raw ) );
        
        $stadium = null;
        $referee = null;
        
        if ( isset( $event['competitions'][0]['venue']['fullName'] ) && ! empty( $event['competitions'][0]['venue']['fullName'] ) ) {
            $stadium = $event['competitions'][0]['venue']['fullName'];
        }
        
        if ( isset( $event['competitions'][0]['officials'] ) && is_array( $event['competitions'][0]['officials'] ) ) {
            foreach ( $event['competitions'][0]['officials'] as $official ) {
                if ( isset( $official['type']['name'] ) && $official['type']['name'] === 'Referee' ) {
                    if ( isset( $official['displayName'] ) && ! empty( $official['displayName'] ) ) {
                        $referee = $official['displayName'];
                        break;
                    }
                }
            }
        }
        
         
     // Build match data with exact schema column names
        // Build match data with exact schema column names
        $match_data = array(
            'e_match_id'       => $e_match_id,
            'home_club_id'     => $home_club_id,
            'away_club_id'     => $away_club_id,
            'match_date'       => $match_date,
            'home_goals'       => $home_score,      // NULL if no score yet
            'away_goals'       => $away_score,      // NULL if no score yet
            'stadium'          => $stadium,         // NULL if not provided
            'referee'          => $referee,         // NULL if not provided
            'competition_code' => $competition_code // competition/league code such as 'eng.1'
            // result_code will be calculated in save_match() from scores
        );
        
        // Save match
        return self::save_match( $match_data );
    }
    
    /**
     * Import matches from E scoreboard API
     * 
     * @param array $league_codes Array of E league codes
     * @param int $batch_size Number of matches to process in each batch
     * @return array Statistics (inserted, updated, errors, skipped)
     */
    public static function import_from_scoreboard( $league_codes = array( 'eng.1' ), $batch_size = 50 ) {
        $stats = array(
            'inserted' => 0,
            'updated'  => 0,
            'errors'   => 0,
            'skipped'  => 0,
        );
        
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            fdm_log_datasource_error( 'database_error', 'Cannot connect to footyforums_data database' );
            $stats['errors']++;
            return $stats;
        }
        
        $competitions_config = self::get_competitions_config();
        
        foreach ( $league_codes as $league_code ) {
            // Derive competition_code from config if available, otherwise fall back to league_code.
            $competition_code = $league_code;
            foreach ( $competitions_config as $config ) {
                if ( isset( $config['league_code'] ) && $config['league_code'] === $league_code && ! empty( $config['competition_code'] ) ) {
                    $competition_code = $config['competition_code'];
                    break;
                }
            }
            
            $url = 'http://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $league_code ) . '/scoreboard';
            
            $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
            
            if ( is_wp_error( $response ) ) {
                fdm_log_datasource_error( 'api_error', 'E API request failed', array( 'url' => $url, 'error' => $response->get_error_message() ) );
                $stats['errors']++;
                continue;
            }
            
            $code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== $code ) {
                fdm_log_datasource_error( 'api_error', 'E API returned non-200 status', array( 'url' => $url, 'status' => $code ) );
                $stats['errors']++;
                continue;
            }
            
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( ! is_array( $data ) || ! isset( $data['events'] ) || ! is_array( $data['events'] ) ) {
                fdm_log_datasource_error( 'api_error', 'Invalid E API response structure', array( 'url' => $url ) );
                $stats['errors']++;
                continue;
            }
            
            $events = $data['events'];
            $batch  = array();
            
            foreach ( $events as $event ) {
                $batch[] = array(
                    'event'           => $event,
                    'competition_code'=> $competition_code,
                );
                
                if ( count( $batch ) >= $batch_size ) {
                    $batch_stats = self::process_batch( $batch, $db );
                    $stats['inserted'] += $batch_stats['inserted'];
                    $stats['updated']  += $batch_stats['updated'];
                    $stats['errors']   += $batch_stats['errors'];
                    $stats['skipped']  += $batch_stats['skipped'];
                    $batch = array();
                    
                    usleep( 200000 );
                }
            }
            
            if ( ! empty( $batch ) ) {
                $batch_stats = self::process_batch( $batch, $db );
                $stats['inserted'] += $batch_stats['inserted'];
                $stats['updated']  += $batch_stats['updated'];
                $stats['errors']   += $batch_stats['errors'];
                $stats['skipped']  += $batch_stats['skipped'];
            }
            
            sleep( 1 );
        }
        
        return $stats;
    }
    
    /**
     * Import team schedule for a specific team and league
     * 
     * @param string $league_code E league code (e.g., 'eng.1')
     * @param string $e_team_id E team ID
     * @return array Statistics (inserted, updated, errors, skipped)
     */
    public static function import_team_schedule( $league_code, $e_team_id ) {
        $stats = array(
            'inserted' => 0,
            'updated'  => 0,
            'errors'   => 0,
            'skipped'  => 0,
        );
        
        if ( empty( $league_code ) || empty( $e_team_id ) ) {
            fdm_log_datasource_error( 'validation_error', 'league_code and e_team_id are required', array( 'league_code' => $league_code, 'e_team_id' => $e_team_id ) );
            $stats['errors']++;
            return $stats;
        }
        
        // Derive competition_code from config if available, otherwise fall back to league_code.
        $competition_code = $league_code;
        $competitions_config = self::get_competitions_config();
        foreach ( $competitions_config as $config ) {
            if ( isset( $config['league_code'] ) && $config['league_code'] === $league_code && ! empty( $config['competition_code'] ) ) {
                $competition_code = $config['competition_code'];
                break;
            }
        }
        
        $url = 'https://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $league_code ) . '/teams/' . rawurlencode( $e_team_id ) . '/schedule';
        
        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
        
        if ( is_wp_error( $response ) ) {
            fdm_log_datasource_error( 'api_error', 'Team schedule API request failed', array( 'url' => $url, 'error' => $response->get_error_message() ) );
            $stats['errors']++;
            return $stats;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            fdm_log_datasource_error( 'api_error', 'Team schedule API returned non-200 status', array( 'url' => $url, 'status' => $code ) );
            $stats['errors']++;
            return $stats;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! is_array( $data ) || ! isset( $data['events'] ) || ! is_array( $data['events'] ) ) {
            fdm_log_datasource_error( 'api_error', 'Invalid team schedule API response structure', array( 'url' => $url ) );
            $stats['errors']++;
            return $stats;
        }
        
        $events = $data['events'];
        
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            fdm_log_datasource_error( 'database_error', 'Cannot connect to footyforums_data database' );
            $stats['errors']++;
            return $stats;
        }
        
        foreach ( $events as $event ) {
            $event_id = isset( $event['id'] ) ? (string) $event['id'] : '';
            $e_match_id = (int) $event_id;
            
            if ( empty( $e_match_id ) ) {
                $stats['skipped']++;
                continue;
            }
            
            // Check if match already exists before processing
            $existing = $db->get_var(
                $db->prepare(
                    "SELECT e_match_id FROM matches WHERE e_match_id = %d LIMIT 1",
                    $e_match_id
                )
            );
            
            $result = self::process_e_event( $event, $competition_code );
            
            if ( $result ) {
                if ( $existing ) {
                    $stats['updated']++;
                } else {
                    $stats['inserted']++;
                }
            } else {
                $stats['errors']++;
            }
        }
        
        fdm_log_datasource_info( 'info', 'Team schedule import complete', array( 'league_code' => $league_code, 'e_team_id' => $e_team_id, 'stats' => $stats ) );
        
        return $stats;
    }
    
    /**
     * Import league matches by date range using scoreboard API
     * 
     * @param string $league_code E league code (e.g., 'uefa.champions')
     * @param string $start_date Start date (YYYY-MM-DD)
     * @param string $end_date End date (YYYY-MM-DD)
     * @return array Statistics (inserted, updated, errors, skipped)
     */
    public static function import_league_by_dates( $league_code, $start_date, $end_date ) {
        $stats = array(
            'inserted' => 0,
            'updated'  => 0,
            'errors'   => 0,
            'skipped'  => 0,
            // Extra flags/metadata for callers
            'api_aborted'      => false, // set to true if we abort due to repeated API errors
            'api_error_status' => null,  // last HTTP status code seen
            'api_error_count'  => 0,     // number of non-404 API errors
        );
        
        if ( empty( $league_code ) || empty( $start_date ) || empty( $end_date ) ) {
            fdm_log_datasource_error( 'validation_error', 'league_code, start_date, and end_date are required', array( 'league_code' => $league_code, 'start_date' => $start_date, 'end_date' => $end_date ) );
            $stats['errors']++;
            return $stats;
        }
        
        // Derive competition_code from config if available, otherwise fall back to league_code.
        $competition_code = $league_code;
        $competitions_config = self::get_competitions_config();
        foreach ( $competitions_config as $config ) {
            if ( isset( $config['league_code'] ) && $config['league_code'] === $league_code && ! empty( $config['competition_code'] ) ) {
                $competition_code = $config['competition_code'];
                break;
            }
        }
        
        $start_timestamp = strtotime( $start_date );
        $end_timestamp = strtotime( $end_date );
        
        if ( $start_timestamp === false || $end_timestamp === false || $start_timestamp > $end_timestamp ) {
            fdm_log_datasource_error( 'validation_error', 'Invalid date range', array( 'start_date' => $start_date, 'end_date' => $end_date ) );
            $stats['errors']++;
            return $stats;
        }
        
        // Validate date range is not too large (max 5 years to prevent very long imports)
        $days_diff = ( $end_timestamp - $start_timestamp ) / ( 60 * 60 * 24 );
        $max_days = 365 * 5; // 5 years
        
        if ( $days_diff > $max_days ) {
            fdm_log_datasource_error( 'validation_error', 'Date range too large (max 5 years)', array( 'start_date' => $start_date, 'end_date' => $end_date, 'days' => round( $days_diff ) ) );
            $stats['errors']++;
            return $stats;
        }
        
        // Warn if going back more than 3 years (ESPN API may have limited historical data)
        $years_back = ( time() - $start_timestamp ) / ( 60 * 60 * 24 * 365 );
        if ( $years_back > 3 ) {
            fdm_log_datasource_info( 'warning', 'Importing data more than 3 years old - ESPN API may have limited historical coverage', array( 'years_back' => round( $years_back, 1 ), 'start_date' => $start_date ) );
        }
        
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            fdm_log_datasource_error( 'database_error', 'Cannot connect to footyforums_data database' );
            $stats['errors']++;
            return $stats;
        }
        
        // Loop day by day, with protection against repeated API failures
        $current_timestamp = $start_timestamp;
        $consecutive_api_errors = 0;
        $max_consecutive_api_errors = 5; // after this many failures in a row, abort this league
        
        while ( $current_timestamp <= $end_timestamp ) {
            $date_str = date( 'Ymd', $current_timestamp ); // YYYYMMDD format for ESPN API
            $date_display = date( 'Y-m-d', $current_timestamp );
            
            $url = 'https://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $league_code ) . '/scoreboard?dates=' . $date_str;
            
            $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
            
            if ( is_wp_error( $response ) ) {
                $consecutive_api_errors++;
                $stats['api_error_count']++;
                fdm_log_datasource_error(
                    'api_error',
                    'Scoreboard API request failed',
                    array(
                        'url'   => $url,
                        'date'  => $date_display,
                        'error' => $response->get_error_message(),
                        'consecutive_errors' => $consecutive_api_errors,
                    )
                );
                $stats['errors']++;
                
                // If we keep failing for several days in a row, abort this league import to avoid long hangs
                if ( $consecutive_api_errors >= $max_consecutive_api_errors ) {
                    $stats['api_aborted'] = true;
                    fdm_log_datasource_error(
                        'api_abort',
                        'Aborting scoreboard import due to repeated API failures',
                        array(
                            'league_code'        => $league_code,
                            'start_date'         => $start_date,
                            'end_date'           => $end_date,
                            'last_date_attempted'=> $date_display,
                            'consecutive_errors' => $consecutive_api_errors,
                        )
                    );
                    break;
                }
                
                $current_timestamp = strtotime( '+1 day', $current_timestamp );
                usleep( 200000 ); // Rate limiting
                continue;
            }
            
            $code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== $code ) {
                // Non-200 might mean no matches on this date, not necessarily an error
                if ( $code !== 404 ) {
                    $consecutive_api_errors++;
                    $stats['api_error_count']++;
                    $stats['api_error_status'] = $code;
                    fdm_log_datasource_error(
                        'api_error',
                        'Scoreboard API returned non-200 status',
                        array(
                            'url'   => $url,
                            'date'  => $date_display,
                            'status'=> $code,
                            'consecutive_errors' => $consecutive_api_errors,
                        )
                    );
                    $stats['errors']++;
                    
                    // Abort this league if we see too many consecutive non-404 errors
                    if ( $consecutive_api_errors >= $max_consecutive_api_errors ) {
                        $stats['api_aborted'] = true;
                        fdm_log_datasource_error(
                            'api_abort',
                            'Aborting scoreboard import due to repeated non-200 responses',
                            array(
                                'league_code'        => $league_code,
                                'start_date'         => $start_date,
                                'end_date'           => $end_date,
                                'last_date_attempted'=> $date_display,
                                'status'             => $code,
                                'consecutive_errors' => $consecutive_api_errors,
                            )
                        );
                        break;
                    }
                } else {
                    // 404 just means "no matches for this date" – not an API health problem
                    $consecutive_api_errors = 0;
                }
                $current_timestamp = strtotime( '+1 day', $current_timestamp );
                usleep( 200000 ); // Rate limiting
                continue;
            }
            
            // Successful response – reset consecutive error counter
            $consecutive_api_errors = 0;
            
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( ! is_array( $data ) || ! isset( $data['events'] ) ) {
                // No events on this date is not an error
                $current_timestamp = strtotime( '+1 day', $current_timestamp );
                usleep( 200000 ); // Rate limiting
                continue;
            }
            
            $events = $data['events'];
            if ( ! is_array( $events ) ) {
                $current_timestamp = strtotime( '+1 day', $current_timestamp );
                usleep( 200000 ); // Rate limiting
                continue;
            }
            
            // Process each event
            foreach ( $events as $event ) {
                $event_id = isset( $event['id'] ) ? (string) $event['id'] : '';
                $e_match_id = (int) $event_id;
                
                if ( empty( $e_match_id ) ) {
                    $stats['skipped']++;
                    continue;
                }
                
                // Check if match already exists before processing
                $existing = $db->get_var(
                    $db->prepare(
                        "SELECT e_match_id FROM matches WHERE e_match_id = %d LIMIT 1",
                        $e_match_id
                    )
                );
                
                $result = self::process_e_event( $event, $competition_code );
                
                if ( $result ) {
                    if ( $existing ) {
                        $stats['updated']++;
                    } else {
                        $stats['inserted']++;
                    }
                } else {
                    $stats['errors']++;
                }
            }
            
            // Rate limiting between dates
            usleep( 200000 ); // 0.2 seconds
            
            // Move to next day
            $current_timestamp = strtotime( '+1 day', $current_timestamp );
        }
        
        fdm_log_datasource_info( 'info', 'League date range import complete', array( 'league_code' => $league_code, 'start_date' => $start_date, 'end_date' => $end_date, 'stats' => $stats ) );
        
        return $stats;
    }
    
    /**
     * Resolve competition_code for a single match by querying the E summary endpoint.
     * 
     * For now this only supports Premier League (maps the E league slug for EPL to 'eng.1'),
     * but the structure allows additional competitions to be added later.
     *
     * @param int $e_match_id E event ID
     * @return string|null competition_code (e.g. 'eng.1') or null if unknown
     */
    public static function resolve_competition_code_for_match( $e_match_id ) {
        if ( empty( $e_match_id ) ) {
            return null;
        }
        
        $url = 'https://site.api.espn.com/apis/site/v2/sports/soccer/summary?event=' . rawurlencode( (string) $e_match_id );
        
        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
        if ( is_wp_error( $response ) ) {
            fdm_log_datasource_error(
                'competition_repair_api_error',
                'Failed to fetch summary for competition_code repair',
                array(
                    'e_match_id' => $e_match_id,
                    'url'        => $url,
                    'error'      => $response->get_error_message(),
                )
            );
            return null;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            fdm_log_datasource_error(
                'competition_repair_http_error',
                'Summary endpoint returned non-200 status for competition_code repair',
                array(
                    'e_match_id' => $e_match_id,
                    'url'        => $url,
                    'status'     => $code,
                )
            );
            return null;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( ! is_array( $data ) || ! isset( $data['header']['competitions'][0]['league'] ) ) {
            fdm_log_datasource_error(
                'competition_repair_invalid_json',
                'Summary endpoint returned unexpected structure when resolving competition_code',
                array(
                    'e_match_id' => $e_match_id,
                )
            );
            return null;
        }
        
        $league = $data['header']['competitions'][0]['league'];
        $slug   = isset( $league['slug'] ) ? $league['slug'] : '';
        $abbr   = isset( $league['abbreviation'] ) ? $league['abbreviation'] : '';
        
        // Minimal, explicit mapping: only Premier League for now.
        $slug_map = array(
            'english-premier-league' => 'eng.1',
        );
        
        if ( $slug && isset( $slug_map[ $slug ] ) ) {
            return $slug_map[ $slug ];
        }
        
        // As a fallback for PL, try abbreviation patterns if present.
        if ( $abbr === 'ENG1' ) {
            return 'eng.1';
        }
        
        fdm_log_datasource_info(
            'info',
            'Could not resolve competition_code for match during repair',
            array(
                'e_match_id' => $e_match_id,
                'slug'       => $slug,
                'abbr'       => $abbr,
            )
        );
        
        return null;
    }
    
    /**
     * Repair existing matches with NULL competition_code by querying E
     * and filling in the correct competition_code where possible.
     *
     * Currently supports Premier League only (eng.1).
     *
     * @param int $limit Max number of matches to repair in one run
     * @return array Stats: ['processed' => ..., 'updated' => ..., 'errors' => ..., 'skipped' => ...]
     */
    public static function repair_competition_codes( $limit = 500 ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            fdm_log_datasource_error(
                'database_error',
                'Cannot connect to footyforums_data database in repair_competition_codes',
                array()
            );
            return array(
                'processed' => 0,
                'updated'   => 0,
                'errors'    => 1,
                'skipped'   => 0,
            );
        }
        
        $limit = max( 1, (int) $limit );
        
        $matches = $db->get_results(
            $db->prepare(
                "SELECT e_match_id
                 FROM matches
                 WHERE competition_code IS NULL
                 ORDER BY e_match_id
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        if ( empty( $matches ) ) {
            return array(
                'processed' => 0,
                'updated'   => 0,
                'errors'    => 0,
                'skipped'   => 0,
            );
        }
        
        $stats = array(
            'processed' => 0,
            'updated'   => 0,
            'errors'    => 0,
            'skipped'   => 0,
        );
        
        foreach ( $matches as $row ) {
            $e_match_id = (int) $row['e_match_id'];
            $stats['processed']++;
            
            $competition_code = self::resolve_competition_code_for_match( $e_match_id );
            if ( $competition_code === null ) {
                fdm_log_datasource_error(
                    'competition_repair_not_found',
                    'Could not resolve competition_code for existing match',
                    array(
                        'e_match_id' => $e_match_id,
                    )
                );
                $stats['skipped']++;
                continue;
            }
            
            $updated = $db->update(
                'matches',
                array( 'competition_code' => $competition_code ),
                array( 'e_match_id' => $e_match_id ),
                array( '%s' ),
                array( '%d' )
            );
            
            if ( $updated === false ) {
                fdm_log_datasource_error(
                    'competition_repair_update_failed',
                    'Failed to update competition_code for match',
                    array(
                        'e_match_id' => $e_match_id,
                        'error'      => $db->last_error,
                    )
                );
                $stats['errors']++;
            } elseif ( $updated > 0 ) {
                $stats['updated']++;
            } else {
                $stats['skipped']++;
            }
        }
        
        fdm_log_datasource_info(
            'info',
            'Competition code repair run completed',
            $stats
        );
        
        return $stats;
    }
    
    /**
     * Process a batch of events in a transaction
     * 
     * @param array $batch Array of event data
     * @param wpdb $db Database connection
     * @return array Statistics
     */
    private static function process_batch( $batch, $db ) {
        $stats = array(
            'inserted' => 0,
            'updated'  => 0,
            'errors'   => 0,
            'skipped'  => 0,
        );
        
        $db->query( 'START TRANSACTION' );
        
        try {
            foreach ( $batch as $item ) {
                $event_id   = isset( $item['event']['id'] ) ? (string) $item['event']['id'] : '';
                $e_match_id = (int) $event_id;
                
                if ( empty( $e_match_id ) ) {
                    $stats['skipped']++;
                    continue;
                }
                
                $existing = $db->get_var(
                    $db->prepare(
                        "SELECT e_match_id FROM matches WHERE e_match_id = %d LIMIT 1",
                        $e_match_id
                    )
                );
                
                $competition_code = isset( $item['competition_code'] ) ? $item['competition_code'] : '';
                $result = self::process_e_event( $item['event'], $competition_code );
                
                if ( $result ) {
                    if ( $existing ) {
                        $stats['updated']++;
                    } else {
                        $stats['inserted']++;
                    }
                } else {
                    $stats['errors']++;
                }
            }
            
            $db->query( 'COMMIT' );
        } catch ( Exception $e ) {
            $db->query( 'ROLLBACK' );
            fdm_log_datasource_error( 'batch_error', 'Batch processing failed', array( 'error' => $e->getMessage() ) );
            $stats['errors'] += count( $batch );
        }
        
        return $stats;
    }
    
    /**
     * Get live scores from footyforums_data database
     * Returns matches in format compatible with existing widget
     * 
     * @param array $league_codes E league codes to filter by (not used currently - matches table doesn't have league info)
     * @param string $team_filter Optional team name to filter by
     * @param string $match_id_filter Optional e_match_id to filter by
     * @return array Array of match data
     */
    public static function get_live_scores_from_db( $league_codes = array(), $team_filter = '', $match_id_filter = '' ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            return array();
        }
        
        $where        = array();
        $where_values = array();
        
        if ( ! empty( $match_id_filter ) ) {
            $where[]        = "m.e_match_id = %d";
            $where_values[] = (int) $match_id_filter;
        }
        
        if ( ! empty( $team_filter ) ) {
            $where[] = "(home_club.canonical_name LIKE %s OR away_club.canonical_name LIKE %s)";
            $team_like = '%' . $db->esc_like( $team_filter ) . '%';
            $where_values[] = $team_like;
            $where_values[] = $team_like;
        }
        
        $where[] = "m.match_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        
        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
        
        $query = "
            SELECT 
                m.e_match_id,
                m.competition_code,
                m.match_date,
                m.home_goals,
                m.away_goals,
                m.result_code,
                m.stadium,
                m.referee,
                home_club.canonical_name as home_team_name,
                away_club.canonical_name as away_team_name
            FROM matches m
            INNER JOIN clubs home_club ON m.home_club_id = home_club.id
            INNER JOIN clubs away_club ON m.away_club_id = away_club.id
            $where_sql
            ORDER BY m.match_date DESC, m.e_match_id DESC
            LIMIT 100
        ";
        
        if ( ! empty( $where_values ) ) {
            $prepared_query = $db->prepare( $query, $where_values );
        } else {
            $prepared_query = $query;
        }
        
        $matches = $db->get_results( $prepared_query, ARRAY_A );
        
        $formatted_matches = array();
        foreach ( $matches as $match ) {
            $status = 'Scheduled';
            if ( $match['result_code'] !== null ) {
                $status = 'Finished';
            } elseif ( $match['home_goals'] !== null && $match['away_goals'] !== null ) {
                $status = 'In Progress';
            }
            
            $formatted_match = array(
                'id'         => (string) $match['e_match_id'],
                'match_id'   => (string) $match['e_match_id'],
                'home_team'  => $match['home_team_name'],
                'away_team'  => $match['away_team_name'],
                'home_score' => $match['home_goals'],
                'away_score' => $match['away_goals'],
                'status'     => $status,
                'competition'=> isset( $match['competition_code'] ) ? $match['competition_code'] : '',
            );
            
            if ( ! empty( $match['stadium'] ) ) {
                $formatted_match['venue'] = $match['stadium'];
            }
            
            $formatted_matches[] = $formatted_match;
        }
        
        return $formatted_matches;
    }
    
    /**
     * Get Premier League participants for a given season year from E standings,
     * then map to canonical clubs via clubs.e_id.
     *
     * @param int $season_year Season start year (e.g. 2024 for 2024-25)
     * @return array Array keyed by e_id => ['id' => club_id, 'canonical_name' => ..., 'e_id' => ...]
     */
    public static function get_pl_participants_from_e( $season_year ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            fdm_log_datasource_error(
                'database_error',
                'Cannot connect to footyforums_data when discovering PL participants',
                array( 'season_year' => $season_year )
            );
            return array();
        }
        
        // Fetch standings for eng.1 for the given season.
        $url = sprintf(
            'https://site.api.espn.com/apis/site/v2/sports/soccer/eng.1/standings?season=%d',
            (int) $season_year
        );
        
        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            fdm_log_datasource_error(
                'pl_standings_fetch_failed',
                'Failed to fetch PL standings',
                array(
                    'season_year' => $season_year,
                    'url'         => $url,
                    'error'       => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ),
                )
            );
            return array();
        }
        
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            fdm_log_datasource_error(
                'pl_standings_invalid_json',
                'Invalid PL standings JSON',
                array( 'season_year' => $season_year )
            );
            return array();
        }
        
        $team_ids = array();
        
        // ESPN standings format: children -> standings -> entries -> team
        foreach ( $data['children'] ?? array() as $group ) {
            $standings = $group['standings']['entries'] ?? array();
            foreach ( $standings as $entry ) {
                $team = $entry['team'] ?? array();
                if ( ! empty( $team['id'] ) ) {
                    $team_ids[] = (string) $team['id'];
                }
            }
        }
        
        $team_ids = array_values( array_unique( $team_ids ) );
        if ( empty( $team_ids ) ) {
            fdm_log_datasource_error(
                'pl_standings_no_teams',
                'No teams found in PL standings response',
                array( 'season_year' => $season_year )
            );
            return array();
        }
        
        // Map participants to canonical clubs via clubs.e_id
        $placeholders = implode( ',', array_fill( 0, count( $team_ids ), '%s' ) );
        $sql = "SELECT id, canonical_name, e_id
                FROM clubs
                WHERE e_id IN ($placeholders)";
        
        $club_rows = $db->get_results( $db->prepare( $sql, $team_ids ), ARRAY_A );
        
        $mapped_clubs = array();
        $found_e_ids  = array();
        foreach ( $club_rows as $row ) {
            $mapped_clubs[ $row['e_id'] ] = array(
                'id'             => (int) $row['id'],
                'canonical_name' => $row['canonical_name'],
                'e_id'           => $row['e_id'],
            );
            $found_e_ids[] = $row['e_id'];
        }
        
        // Log any team IDs that could not be mapped to clubs
        $missing = array_diff( $team_ids, $found_e_ids );
        foreach ( $missing as $missing_e_id ) {
            fdm_log_datasource_error(
                'club_mapping_failed',
                'No clubs.e_id mapping for PL participant',
                array(
                    'competition'  => 'eng.1',
                    'season_year'  => $season_year,
                    'e_team_id'    => $missing_e_id,
                )
            );
        }
        
        return $mapped_clubs;
    }
    
    /**
     * Discover competition participants for non-PL competitions using the scoreboard API
     * across a date range, then map via clubs.e_id.
     *
     * @param string $league_code E league code (e.g. 'uefa.champions')
     * @param string $start_date YYYY-MM-DD
     * @param string $end_date YYYY-MM-DD
     * @return array Array keyed by e_id => ['id' => club_id, 'canonical_name' => ..., 'e_id' => ...]
     */
    public static function get_competition_participants_from_scoreboard( $league_code, $start_date, $end_date ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            fdm_log_datasource_error(
                'database_error',
                'Cannot connect to footyforums_data when discovering competition participants',
                array( 'league_code' => $league_code )
            );
            return array();
        }
        
        if ( empty( $league_code ) || empty( $start_date ) || empty( $end_date ) ) {
            fdm_log_datasource_error(
                'validation_error',
                'league_code, start_date, and end_date are required for participant discovery',
                array(
                    'league_code' => $league_code,
                    'start_date'  => $start_date,
                    'end_date'    => $end_date,
                )
            );
            return array();
        }
        
        $start_timestamp = strtotime( $start_date );
        $end_timestamp   = strtotime( $end_date );
        
        if ( $start_timestamp === false || $end_timestamp === false || $start_timestamp > $end_timestamp ) {
            fdm_log_datasource_error(
                'validation_error',
                'Invalid date range for participant discovery',
                array(
                    'league_code' => $league_code,
                    'start_date'  => $start_date,
                    'end_date'    => $end_date,
                )
            );
            return array();
        }
        
        $team_ids = array();
        $current_timestamp = $start_timestamp;
        $consecutive_api_errors = 0;
        $max_consecutive_api_errors = 5;
        
        while ( $current_timestamp <= $end_timestamp ) {
            $date_param   = gmdate( 'Ymd', $current_timestamp );
            $date_display = gmdate( 'Y-m-d', $current_timestamp );
            
            $url = 'https://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $league_code ) . '/scoreboard?dates=' . $date_param;
            
            $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
            if ( is_wp_error( $response ) ) {
                $consecutive_api_errors++;
                fdm_log_datasource_error(
                    'api_error',
                    'Scoreboard API request failed during participant discovery',
                    array(
                        'league_code'        => $league_code,
                        'date'               => $date_display,
                        'url'                => $url,
                        'error'              => $response->get_error_message(),
                        'consecutive_errors' => $consecutive_api_errors,
                    )
                );
                
                if ( $consecutive_api_errors >= $max_consecutive_api_errors ) {
                    fdm_log_datasource_error(
                        'api_abort',
                        'Aborting participant discovery due to repeated API errors',
                        array(
                            'league_code'        => $league_code,
                            'start_date'         => $start_date,
                            'end_date'           => $end_date,
                            'last_date_attempted'=> $date_display,
                        )
                    );
                    break;
                }
                
                $current_timestamp = strtotime( '+1 day', $current_timestamp );
                usleep( 200000 );
                continue;
            }
            
            $code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== $code ) {
                if ( $code !== 404 ) {
                    $consecutive_api_errors++;
                    fdm_log_datasource_error(
                        'api_error',
                        'Scoreboard API returned non-200 during participant discovery',
                        array(
                            'league_code'        => $league_code,
                            'date'               => $date_display,
                            'url'                => $url,
                            'status'             => $code,
                            'consecutive_errors' => $consecutive_api_errors,
                        )
                    );
                    
                    if ( $consecutive_api_errors >= $max_consecutive_api_errors ) {
                        fdm_log_datasource_error(
                            'api_abort',
                            'Aborting participant discovery due to repeated non-200 responses',
                            array(
                                'league_code'        => $league_code,
                                'start_date'         => $start_date,
                                'end_date'           => $end_date,
                                'last_date_attempted'=> $date_display,
                                'status'             => $code,
                            )
                        );
                        break;
                    }
                } else {
                    // 404 just means no matches on that date.
                    $consecutive_api_errors = 0;
                }
                
                $current_timestamp = strtotime( '+1 day', $current_timestamp );
                usleep( 200000 );
                continue;
            }
            
            $consecutive_api_errors = 0;
            
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( ! is_array( $data ) || ! isset( $data['events'] ) || ! is_array( $data['events'] ) ) {
                $current_timestamp = strtotime( '+1 day', $current_timestamp );
                usleep( 200000 );
                continue;
            }
            
            foreach ( $data['events'] as $event ) {
                foreach ( $event['competitions'][0]['competitors'] ?? array() as $comp ) {
                    if ( ! empty( $comp['team']['id'] ) ) {
                        $team_ids[] = (string) $comp['team']['id'];
                    }
                }
            }
            
            $current_timestamp = strtotime( '+1 day', $current_timestamp );
            usleep( 200000 );
        }
        
        $team_ids = array_values( array_unique( $team_ids ) );
        if ( empty( $team_ids ) ) {
            fdm_log_datasource_info(
                'info',
                'No participants discovered for competition within date range',
                array(
                    'league_code' => $league_code,
                    'start_date'  => $start_date,
                    'end_date'    => $end_date,
                )
            );
            return array();
        }
        
        $placeholders = implode( ',', array_fill( 0, count( $team_ids ), '%s' ) );
        $sql = "SELECT id, canonical_name, e_id
                FROM clubs
                WHERE e_id IN ($placeholders)";
        
        $club_rows = $db->get_results( $db->prepare( $sql, $team_ids ), ARRAY_A );
        
        $mapped_clubs = array();
        $found_e_ids  = array();
        foreach ( $club_rows as $row ) {
            $mapped_clubs[ $row['e_id'] ] = array(
                'id'             => (int) $row['id'],
                'canonical_name' => $row['canonical_name'],
                'e_id'           => $row['e_id'],
            );
            $found_e_ids[] = $row['e_id'];
        }
        
        $missing = array_diff( $team_ids, $found_e_ids );
        foreach ( $missing as $missing_e_id ) {
            fdm_log_datasource_error(
                'club_mapping_failed',
                'No clubs.e_id mapping for competition participant',
                array(
                    'competition' => $league_code,
                    'e_team_id'   => $missing_e_id,
                )
            );
        }
        
        return $mapped_clubs;
    }
    
    /**
     * Sync match team statistics from ESPN API
     * 
     * @param string $league_code E league code (e.g., 'eng.1')
     * @param int $season_year Season year (e.g., 2024)
     * @return array|WP_Error Array with matches_processed, rows_written, errors_count on success
     */
    public static function e_datasource_sync_match_team_stats( $league_code, $season_year ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            return new WP_Error( 'database_error', 'Cannot connect to footyforums_data database' );
        }
        
        $league_code = trim( (string) $league_code );
        $season_year = (int) $season_year;
        
        if ( empty( $league_code ) || $season_year < 1800 || $season_year > 3000 ) {
            return new WP_Error( 'invalid_argument', 'Invalid league_code or season_year' );
        }
        
        // Build e_team_id to club_id mapping, independent of league
        $clubs = $db->get_results(
            "SELECT id, canonical_name, e_team_id
             FROM clubs
             WHERE e_team_id IS NOT NULL
               AND e_team_id <> ''",
            ARRAY_A
        );

        $team_id_map = array();
        foreach ( $clubs as $club ) {
            $e_team_id = (string) $club['e_team_id'];
            $team_id_map[ $e_team_id ] = array(
                'club_id'        => (int) $club['id'],
                'canonical_name' => $club['canonical_name'],
            );
        }
        
        if ( empty( $team_id_map ) ) {
            fdm_log_datasource_info(
                'info',
                'Match team stats sync, no clubs found for league',
                array(
                    'e_league_code' => $league_code,
                    'season_year' => $season_year,
                    'type' => 'match_team_stats',
                )
            );
            return array(
                'matches_processed' => 0,
                'rows_written' => 0,
                'errors_count' => 0,
            );
        }
        
        // Clear existing match team stats for this league and season to avoid duplicates
        $db->query(
            $db->prepare(
                "DELETE FROM fdm_match_team_stats
                 WHERE competition_code = %s
                   AND season_year = %d",
                $league_code,
                $season_year
            )
        );
        
        // Fetch fixtures for this league and season
        // Derive season_year from match_date if not stored directly
        $fixtures = $db->get_results(
            $db->prepare(
                "SELECT e_match_id, home_club_id, away_club_id, competition_code, match_date, home_goals, away_goals
                 FROM matches
                 WHERE competition_code = %s
             AND season_year = %d
                 ORDER BY match_date ASC",
                $league_code,
                $season_year
            ),
            ARRAY_A
        );
        
        if ( empty( $fixtures ) ) {
            fdm_log_datasource_info(
                'info',
                'Match team stats sync, no fixtures found',
                array(
                    'e_league_code' => $league_code,
                    'season_year' => $season_year,
                    'type' => 'match_team_stats',
                )
            );
            return array(
                'matches_processed' => 0,
                'rows_written' => 0,
                'errors_count' => 0,
            );
        }
        
        $matches_processed = 0;
        $rows_written = 0;
        $errors_count = 0;
        
        foreach ( $fixtures as $fixture ) {
            $e_match_id = (string) $fixture['e_match_id'];
            $home_club_id = (int) $fixture['home_club_id'];
            $away_club_id = (int) $fixture['away_club_id'];
            $competition_code = $fixture['competition_code'];
            $home_goals = isset( $fixture['home_goals'] ) ? (int) $fixture['home_goals'] : null;
            $away_goals = isset( $fixture['away_goals'] ) ? (int) $fixture['away_goals'] : null;
            
            // Call ESPN summary endpoint
            $url = 'https://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $competition_code ) . '/summary?event=' . rawurlencode( $e_match_id );
            $response = self::e_http_request( $url );
            
            if ( is_wp_error( $response ) ) {
                $errors_count++;
                fdm_log_datasource_error(
                    'match_team_stats_http_error',
                    'Failed to fetch match summary',
                    array(
                        'e_match_id' => $e_match_id,
                        'e_league_code' => $league_code,
                        'season_year' => $season_year,
                        'error' => $response->get_error_message(),
                        'type' => 'match_team_stats',
                    )
                );
                continue;
            }
            
            if ( $response['code'] !== 200 ) {
                $errors_count++;
                fdm_log_datasource_error(
                    'match_team_stats_non_200',
                    'Match summary returned non-200 status',
                    array(
                        'e_match_id' => $e_match_id,
                        'e_league_code' => $league_code,
                        'season_year' => $season_year,
                        'status_code' => $response['code'],
                        'type' => 'match_team_stats',
                    )
                );
                continue;
            }
            
            $data = json_decode( $response['body'], true );
            if ( ! is_array( $data ) || empty( $data['boxscore'] ) ) {
                $errors_count++;
                fdm_log_datasource_error(
                    'match_team_stats_invalid_json',
                    'Match summary missing boxscore data',
                    array(
                        'e_match_id' => $e_match_id,
                        'e_league_code' => $league_code,
                        'season_year' => $season_year,
                        'type' => 'match_team_stats',
                    )
                );
                continue;
            }
            
            // Extract competitors - try multiple possible locations
            $competitors = array();
            if ( isset( $data['boxscore']['players'] ) && is_array( $data['boxscore']['players'] ) ) {
                $competitors = $data['boxscore']['players'];
            } elseif ( isset( $data['boxscore']['teams'] ) && is_array( $data['boxscore']['teams'] ) ) {
                $competitors = $data['boxscore']['teams'];
            } elseif ( isset( $data['header']['competitions'][0]['competitors'] ) && is_array( $data['header']['competitions'][0]['competitors'] ) ) {
                $competitors = $data['header']['competitions'][0]['competitors'];
            }
            
            if ( empty( $competitors ) || ! is_array( $competitors ) ) {
                $errors_count++;
                fdm_log_datasource_error(
                    'match_team_stats_no_competitors',
                    'Match summary has no competitors data',
                    array(
                        'e_match_id' => $e_match_id,
                        'e_league_code' => $league_code,
                        'season_year' => $season_year,
                        'type' => 'match_team_stats',
                    )
                );
                continue;
            }
            
            // TEMP DEBUG: log competitor statistics structure for one match
            if ( defined( 'WP_CLI' ) && WP_CLI && $league_code === 'eng.1' && $season_year === 2024 && $e_match_id === '671231' ) {
                $debug_competitors = array();
                
                foreach ( $competitors as $comp_index => $competitor ) {
                    $team_id   = isset( $competitor['team']['id'] ) ? $competitor['team']['id'] : null;
                    $team_name = isset( $competitor['team']['name'] ) ? $competitor['team']['name'] : null;
                    
                    $stats_debug = array();
                    
                    if ( isset( $competitor['statistics'] ) && is_array( $competitor['statistics'] ) ) {
                        foreach ( $competitor['statistics'] as $stat ) {
                            if ( ! is_array( $stat ) ) {
                                continue;
                            }
                            
                            $stats_debug[] = array(
                                'name'          => isset( $stat['name'] ) ? $stat['name'] : null,
                                'shortName'     => isset( $stat['shortDisplayName'] ) ? $stat['shortDisplayName'] : null,
                                'value'         => isset( $stat['value'] ) ? $stat['value'] : null,
                                'displayValue'  => isset( $stat['displayValue'] ) ? $stat['displayValue'] : null,
                            );
                        }
                    }
                    
                    $debug_competitors[] = array(
                        'index'     => $comp_index,
                        'team_id'   => $team_id,
                        'team_name' => $team_name,
                        'stats'     => $stats_debug,
                    );
                }
                
                WP_CLI::log( '[FDM DEBUG STATS] match ' . $e_match_id . ' competitors: ' . json_encode( $debug_competitors ) );
            }
            
            // Process each competitor (home and away)
            foreach ( $competitors as $competitor ) {
                $e_team_id = isset( $competitor['team']['id'] ) ? (string) $competitor['team']['id'] : '';
                $is_home = isset( $competitor['homeAway'] ) && $competitor['homeAway'] === 'home';
                
                if ( empty( $e_team_id ) || ! isset( $team_id_map[ $e_team_id ] ) ) {
                    $errors_count++;
                    fdm_log_datasource_error(
                        'match_team_stats_mapping_failed',
                        'Cannot map ESPN team_id to club_id',
                        array(
                            'e_match_id' => $e_match_id,
                            'e_team_id' => $e_team_id,
                            'e_league_code' => $league_code,
                            'season_year' => $season_year,
                            'type' => 'match_team_stats',
                        )
                    );
                    continue;
                }
                
                $club_id = $team_id_map[ $e_team_id ]['club_id'];
                
                // Extract statistics - try multiple possible locations
                $statistics = array();
                if ( isset( $competitor['statistics'] ) && is_array( $competitor['statistics'] ) ) {
                    $statistics = $competitor['statistics'];
                } elseif ( isset( $competitor['statistics'][0] ) && is_array( $competitor['statistics'][0] ) ) {
                    $statistics = $competitor['statistics'][0];
                }
                
                // Extract individual stat values
                $goals = isset( $competitor['score'] ) ? (int) $competitor['score'] : 0;
                if ( $goals === 0 && $is_home && $home_goals !== null ) {
                    $goals = $home_goals;
                } elseif ( $goals === 0 && ! $is_home && $away_goals !== null ) {
                    $goals = $away_goals;
                }
                
                // Extract stats from statistics array
                $shots          = 0;
                $shots_on_target = 0;
                $possession     = null; // percentage, e.g. 60.6
                $pass_accuracy  = null; // percentage, e.g. 90.1
                $fouls          = 0;
                $tackles        = 0;
                $offsides       = 0;
                $corners        = 0;
                
                if ( ! empty( $statistics ) ) {
                    foreach ( $statistics as $stat ) {
                        if ( ! is_array( $stat ) || ! isset( $stat['name'] ) ) {
                            continue;
                        }
                        
                        $raw_name  = (string) $stat['name'];
                        $stat_name = strtolower( $raw_name );
                        
                        // ESPN often leaves "value" null and uses "displayValue" as a string
                        $numeric = null;
                        
                        if ( isset( $stat['value'] ) && is_numeric( $stat['value'] ) ) {
                            $numeric = (float) $stat['value'];
                        } elseif ( isset( $stat['displayValue'] ) ) {
                            $display = (string) $stat['displayValue'];
                            
                            // Strip percent sign and convert to float if needed
                            $display = str_replace( '%', '', $display );
                            if ( is_numeric( $display ) ) {
                                $numeric = (float) $display;
                            }
                        }
                        
                        // If we still do not have a numeric value, skip this stat
                        if ( $numeric === null ) {
                            continue;
                        }
                        
                        // Map ESPN stat names to our fields
                        if ( $stat_name === 'totalshots' ) {
                            $shots = (int) round( $numeric );
                        } elseif ( $stat_name === 'shotsontarget' ) {
                            $shots_on_target = (int) round( $numeric );
                        } elseif ( $stat_name === 'possessionpct' ) {
                            // Already in percentage form, e.g. 60.6
                            $possession = $numeric;
                        } elseif ( $stat_name === 'passpct' ) {
                            // ESPN uses 0.9 for 90 percent, or sometimes 90.1
                            if ( $numeric <= 1.0 ) {
                                $pass_accuracy = $numeric * 100.0;
                            } else {
                                $pass_accuracy = $numeric;
                            }
                        } elseif ( $stat_name === 'foulscommitted' ) {
                            $fouls = (int) round( $numeric );
                        } elseif ( $stat_name === 'offsides' ) {
                            $offsides = (int) round( $numeric );
                        } elseif ( $stat_name === 'woncorners' ) {
                            $corners = (int) round( $numeric );
                        } elseif ( $stat_name === 'totaltackles' ) {
                            $tackles = (int) round( $numeric );
                        } elseif ( $stat_name === 'effectivetackles' && $tackles === 0 ) {
                            // Fallback if only effective tackles is present
                            $tackles = (int) round( $numeric );
                        }
                    }
                }
                
                // Determine result
                $result = null;
                if ( $home_goals !== null && $away_goals !== null ) {
                    if ( $is_home ) {
                        if ( $home_goals > $away_goals ) {
                            $result = 'win';
                        } elseif ( $home_goals < $away_goals ) {
                            $result = 'loss';
                        } else {
                            $result = 'draw';
                        }
                    } else {
                        if ( $away_goals > $home_goals ) {
                            $result = 'win';
                        } elseif ( $away_goals < $home_goals ) {
                            $result = 'loss';
                        } else {
                            $result = 'draw';
                        }
                    }
                }
                
                // Insert/update match team stats
                $result_db = $db->replace(
                    'fdm_match_team_stats',
                    array(
                        'e_match_id'      => $e_match_id,
                        'club_id'         => $club_id,
                        'e_team_id'       => $e_team_id,
                        'competition_code'=> $competition_code,
                        'season_year'     => $season_year,
                        'is_home'         => $is_home ? 1 : 0,
                        'goals'           => $goals,
                        'shots'           => $shots,
                        'shots_on_target' => $shots_on_target,
                        'possession'      => $possession,
                        'pass_accuracy'   => $pass_accuracy,
                        'fouls'           => $fouls,
                        'tackles'         => $tackles,
                        'offsides'        => $offsides,
                        'corners'         => $corners,
                        'result'          => $result,
                    ),
                    array(
                        '%s', // e_match_id
                        '%d', // club_id
                        '%s', // e_team_id
                        '%s', // competition_code
                        '%d', // season_year
                        '%d', // is_home
                        '%d', // goals
                        '%d', // shots
                        '%d', // shots_on_target
                        '%f', // possession
                        '%f', // pass_accuracy
                        '%d', // fouls
                        '%d', // tackles
                        '%d', // offsides
                        '%d', // corners
                        '%s', // result
                    )
                );
                
                if ( $result_db === false ) {
                    $errors_count++;
                    fdm_log_datasource_error(
                        'match_team_stats_db_error',
                        'Failed to save match team stats',
                        array(
                            'e_match_id' => $e_match_id,
                            'club_id' => $club_id,
                            'e_league_code' => $league_code,
                            'season_year' => $season_year,
                            'db_error' => $db->last_error,
                            'type' => 'match_team_stats',
                        )
                    );
                } else {
                    $rows_written++;
                }
            }
            
            $matches_processed++;
        }
        
        // Log summary
        fdm_log_datasource_info(
            'info',
            'Match team stats sync completed',
            array(
                'e_league_code' => $league_code,
                'season_year' => $season_year,
                'type' => 'match_team_stats',
                'matches_processed' => $matches_processed,
                'rows_written' => $rows_written,
                'errors_count' => $errors_count,
            )
        );
        
        return array(
            'matches_processed' => $matches_processed,
            'rows_written' => $rows_written,
            'errors_count' => $errors_count,
        );
    }
    
    /**
     * Aggregate team season statistics from match team stats
     * 
     * @param string $league_code E league code (e.g., 'eng.1')
     * @param int $season_year Season year (e.g., 2024)
     * @return array|WP_Error Array with 'team_rows' count on success, or WP_Error on failure
     */
    public static function e_datasource_sync_season_stats( $league_code, $season_year ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            return new WP_Error( 'database_error', 'Cannot connect to footyforums_data database' );
        }
        
        $league_code = trim( (string) $league_code );
        $season_year = (int) $season_year;
        
        if ( empty( $league_code ) || $season_year < 1800 || $season_year > 3000 ) {
            return new WP_Error( 'invalid_argument', 'Invalid league_code or season_year' );
        }
        
        // Clear existing season stats for this league and season so the sync is idempotent
        $db->query(
            $db->prepare(
                "DELETE FROM fdm_team_season_stats
                 WHERE competition_code = %s
                   AND season_year = %d",
                $league_code,
                $season_year
            )
        );
        
        // Get all clubs with match stats for this league and season
        $clubs_with_stats = $db->get_results(
            $db->prepare(
                "SELECT DISTINCT club_id 
                 FROM fdm_match_team_stats 
                 WHERE competition_code = %s 
                   AND season_year = %d",
                $league_code,
                $season_year
            ),
            ARRAY_A
        );
        
        if ( empty( $clubs_with_stats ) ) {
            fdm_log_datasource_info(
                'info',
                'Season team stats sync, no match stats found',
                array(
                    'e_league_code' => $league_code,
                    'season_year' => $season_year,
                    'type' => 'season_team_stats',
                )
            );
            return array(
                'team_rows' => 0,
                'errors_count' => 0,
            );
        }
        
        $rows_written = 0;
        $errors_count = 0;
        
        foreach ( $clubs_with_stats as $club_row ) {
            $club_id = (int) $club_row['club_id'];
            
            // Aggregate statistics for this club
            $aggregates = $db->get_row(
                $db->prepare(
                    "SELECT 
                        COUNT(DISTINCT e_match_id) as matches_played,
                        SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) as wins,
                        SUM(CASE WHEN result = 'draw' THEN 1 ELSE 0 END) as draws,
                        SUM(CASE WHEN result = 'loss' THEN 1 ELSE 0 END) as losses,
                        SUM(goals) as goals_for,
                        SUM(shots) as shots,
                        SUM(shots_on_target) as shots_on_target,
                        AVG(CASE WHEN possession IS NOT NULL THEN possession ELSE NULL END) as possession_avg,
                        AVG(CASE WHEN pass_accuracy IS NOT NULL THEN pass_accuracy ELSE NULL END) as pass_accuracy_avg,
                        AVG(fouls) as fouls_per_game,
                        AVG(tackles) as tackles_per_game,
                        AVG(offsides) as offsides_per_game,
                        AVG(corners) as corners_per_game
                     FROM fdm_match_team_stats
                     WHERE club_id = %d 
                       AND competition_code = %s 
                       AND season_year = %d",
                    $club_id,
                    $league_code,
                    $season_year
                ),
                ARRAY_A
            );
            
            if ( ! $aggregates ) {
                $errors_count++;
                continue;
            }
            
            // Calculate goals_against by summing opponent goals
            // For each match this club played, get the opponent's goals
            $goals_against_result = $db->get_var(
                $db->prepare(
                    "SELECT SUM(opponent_goals)
                     FROM (
                         SELECT 
                             mts1.e_match_id,
                             mts2.goals as opponent_goals
                         FROM fdm_match_team_stats mts1
                         INNER JOIN fdm_match_team_stats mts2 
                             ON mts1.e_match_id = mts2.e_match_id 
                             AND mts1.club_id != mts2.club_id
                         WHERE mts1.club_id = %d
                           AND mts1.competition_code = %s
                           AND mts1.season_year = %d
                     ) as opponent_stats",
                    $club_id,
                    $league_code,
                    $season_year
                )
            );
            
            $goals_against = $goals_against_result !== null ? (int) $goals_against_result : 0;
            
            // Prepare data for insert/update
            $result = $db->replace(
                'fdm_team_season_stats',
                array(
                    'club_id' => $club_id,
                    'competition_code' => $league_code,
                    'season_year' => $season_year,
                    'matches_played' => (int) $aggregates['matches_played'],
                    'wins' => (int) $aggregates['wins'],
                    'draws' => (int) $aggregates['draws'],
                    'losses' => (int) $aggregates['losses'],
                    'goals_for' => (int) $aggregates['goals_for'],
                    'goals_against' => $goals_against,
                    'shots' => (int) $aggregates['shots'],
                    'shots_on_target' => (int) $aggregates['shots_on_target'],
                    'possession_avg' => $aggregates['possession_avg'] !== null ? (float) $aggregates['possession_avg'] : null,
                    'pass_accuracy_avg' => $aggregates['pass_accuracy_avg'] !== null ? (float) $aggregates['pass_accuracy_avg'] : null,
                    'fouls_per_game' => $aggregates['fouls_per_game'] !== null ? (float) $aggregates['fouls_per_game'] : null,
                    'tackles_per_game' => $aggregates['tackles_per_game'] !== null ? (float) $aggregates['tackles_per_game'] : null,
                    'offsides_per_game' => $aggregates['offsides_per_game'] !== null ? (float) $aggregates['offsides_per_game'] : null,
                    'corners_per_game' => $aggregates['corners_per_game'] !== null ? (float) $aggregates['corners_per_game'] : null,
                    'last_synced' => current_time( 'mysql' ),
                ),
                array(
                    '%d', // club_id
                    '%s', // competition_code
                    '%d', // season_year
                    '%d', // matches_played
                    '%d', // wins
                    '%d', // draws
                    '%d', // losses
                    '%d', // goals_for
                    '%d', // goals_against
                    '%d', // shots
                    '%d', // shots_on_target
                    '%f', // possession_avg
                    '%f', // pass_accuracy_avg
                    '%f', // fouls_per_game
                    '%f', // tackles_per_game
                    '%f', // offsides_per_game
                    '%f', // corners_per_game
                    '%s', // last_synced
                )
            );
            
            if ( $result === false ) {
                $errors_count++;
                fdm_log_datasource_error(
                    'season_team_stats_db_error',
                    'Failed to save aggregated season stats',
                    array(
                        'club_id' => $club_id,
                        'e_league_code' => $league_code,
                        'season_year' => $season_year,
                        'db_error' => $db->last_error,
                        'type' => 'season_team_stats',
                    )
                );
            } else {
                $rows_written++;
            }
        }
        
        // Log summary
        fdm_log_datasource_info(
            'info',
            'Season team stats sync completed',
            array(
                'e_league_code' => $league_code,
                'season_year' => $season_year,
                'type' => 'season_team_stats',
                'rows_written' => $rows_written,
                'errors_count' => $errors_count,
            )
        );
        
        return array(
            'team_rows' => $rows_written,
            'errors_count' => $errors_count,
        );
    }
    
    /**
     * Recursively summarize JSON structure for debugging
     * 
     * @param mixed $value Value to summarize
     * @param int $depth Current depth
     * @param int $max_depth Maximum depth to traverse
     * @return mixed Summarized structure
     */
    private static function summarize_structure( $value, $depth = 0, $max_depth = 3 ) {
        if ( $depth > $max_depth ) {
            return '[...]';
        }
        
        if ( is_array( $value ) ) {
            if ( empty( $value ) ) {
                return array( '_type' => 'empty_array' );
            }
            
            // Check if associative array
            $keys = array_keys( $value );
            $is_assoc = array_keys( $keys ) !== $keys;
            
            if ( $is_assoc ) {
                // Associative array - summarize each key
                $result = array();
                foreach ( $value as $key => $val ) {
                    $result[ $key ] = self::summarize_structure( $val, $depth + 1, $max_depth );
                }
                return $result;
            } else {
                // Indexed array - show count and sample
                $sample = reset( $value );
                return array(
                    '_type' => 'list',
                    'count' => count( $value ),
                    'sample' => self::summarize_structure( $sample, $depth + 1, $max_depth ),
                );
            }
        } elseif ( is_object( $value ) ) {
            // Convert object to array for summarization
            return self::summarize_structure( (array) $value, $depth, $max_depth );
        } else {
            // Scalar value
            $type = gettype( $value );
            if ( is_string( $value ) ) {
                $preview = mb_substr( $value, 0, 30 );
                if ( mb_strlen( $value ) > 30 ) {
                    $preview .= '...';
                }
                return array(
                    '_type' => $type,
                    '_value' => $preview,
                    '_length' => mb_strlen( $value ),
                );
            } elseif ( is_numeric( $value ) ) {
                return array(
                    '_type' => $type,
                    '_value' => $value,
                );
            } else {
                return array(
                    '_type' => $type,
                    '_value' => $value,
                );
            }
        }
    }
    
    /**
     * Debug helper: Log the structure/shape of an ESPN match summary JSON
     * 
     * @param string $competition_code Competition code (e.g., 'eng.1')
     * @param string $e_match_id ESPN event/match ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function debug_log_match_summary_shape( $competition_code, $e_match_id ) {
        $summary_data = self::get_match_summary_from_e( $competition_code, $e_match_id );
        
        if ( is_wp_error( $summary_data ) ) {
            fdm_log_datasource_error(
                'debug_summary_fetch_failed',
                'Failed to fetch match summary for debugging',
                array(
                    'competition_code' => $competition_code,
                    'e_match_id' => $e_match_id,
                    'error' => $summary_data->get_error_message(),
                    'type' => 'player_match_stats_debug',
                )
            );
            return $summary_data;
        }
        
        // Get top-level keys
        $top_keys = is_array( $summary_data ) ? array_keys( $summary_data ) : array();
        
        // Summarize boxscore if present
        $boxscore_shape = null;
        if ( isset( $summary_data['boxscore'] ) ) {
            $boxscore_shape = self::summarize_structure( $summary_data['boxscore'], 0, 4 );
        }
        
        // Summarize any obvious player/statistics paths
        $players_shape = null;
        if ( isset( $summary_data['boxscore']['players'] ) ) {
            $players_shape = self::summarize_structure( $summary_data['boxscore']['players'], 0, 4 );
        } elseif ( isset( $summary_data['players'] ) ) {
            $players_shape = self::summarize_structure( $summary_data['players'], 0, 4 );
        }
        
        $statistics_shape = null;
        if ( isset( $summary_data['boxscore']['statistics'] ) ) {
            $statistics_shape = self::summarize_structure( $summary_data['boxscore']['statistics'], 0, 4 );
        } elseif ( isset( $summary_data['statistics'] ) ) {
            $statistics_shape = self::summarize_structure( $summary_data['statistics'], 0, 4 );
        }
        
        $teams_shape = null;
        if ( isset( $summary_data['boxscore']['teams'] ) ) {
            $teams_shape = self::summarize_structure( $summary_data['boxscore']['teams'], 0, 4 );
        } elseif ( isset( $summary_data['teams'] ) ) {
            $teams_shape = self::summarize_structure( $summary_data['teams'], 0, 4 );
        }
        
        // Log the shape
        fdm_log_datasource_info(
            'debug',
            'E summary shape',
            array(
                'competition_code' => $competition_code,
                'e_match_id' => $e_match_id,
                'top_keys' => $top_keys,
                'boxscore_shape' => $boxscore_shape,
                'players_shape' => $players_shape,
                'statistics_shape' => $statistics_shape,
                'teams_shape' => $teams_shape,
                'type' => 'player_match_stats_debug',
            )
        );
        
        return true;
    }
    
    /**
     * Fetch match summary JSON from ESPN API
     * 
     * @param string $competition_code Competition code (e.g., 'eng.1')
     * @param string $e_match_id ESPN event/match ID
     * @return array|WP_Error Decoded JSON array on success, WP_Error on failure
     */
    public static function get_match_summary_from_e( $competition_code, $e_match_id ) {
        if ( empty( $competition_code ) || empty( $e_match_id ) ) {
            return new WP_Error( 'invalid_argument', 'competition_code and e_match_id are required' );
        }
        
        $url = 'https://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $competition_code ) . '/summary?event=' . rawurlencode( $e_match_id );
        $response = self::e_http_request( $url );
        
        if ( is_wp_error( $response ) ) {
            fdm_log_datasource_error(
                'player_match_stats_http_error',
                'Failed to fetch match summary from ESPN',
                array(
                    'competition_code' => $competition_code,
                    'e_match_id' => $e_match_id,
                    'error' => $response->get_error_message(),
                    'type' => 'player_match_stats',
                )
            );
            return $response;
        }
        
        if ( $response['code'] !== 200 ) {
            $error = new WP_Error(
                'http_error',
                sprintf( 'ESPN API returned status %d', $response['code'] )
            );
            fdm_log_datasource_error(
                'player_match_stats_non_200',
                'Match summary returned non-200 status',
                array(
                    'competition_code' => $competition_code,
                    'e_match_id' => $e_match_id,
                    'status_code' => $response['code'],
                    'type' => 'player_match_stats',
                )
            );
            return $error;
        }
        
        $data = json_decode( $response['body'], true );
        if ( ! is_array( $data ) ) {
            $error = new WP_Error( 'json_decode_failed', 'Failed to decode JSON response' );
            fdm_log_datasource_error(
                'player_match_stats_json_error',
                'Failed to decode match summary JSON',
                array(
                    'competition_code' => $competition_code,
                    'e_match_id' => $e_match_id,
                    'type' => 'player_match_stats',
                )
            );
            return $error;
        }
        
        return $data;
    }
    
    /**
     * Get or create a player in wp_fdm_players table using ESPN player ID
     * 
     * @param string $e_player_id ESPN athlete/player ID
     * @param array $athlete_data Optional athlete data from ESPN JSON for creating new players
     * @return string|WP_Error ESPN player ID (e_id) on success, WP_Error on failure
     */
    protected static function get_or_create_player( $e_player_id, $athlete_data = array() ) {
        global $wpdb;
        
        if ( empty( $e_player_id ) ) {
            return new WP_Error( 'invalid_argument', 'e_player_id is required' );
        }
        
        $players_table = $wpdb->prefix . 'fdm_players';
        
        // Check if player exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT e_id FROM {$players_table} WHERE e_id = %s LIMIT 1",
                $e_player_id
            )
        );
        
        if ( $existing ) {
            return $e_player_id;
        }
        
        // Create new player record
        $player_data = array(
            'e_id' => $e_player_id,
            'canonical_name' => isset( $athlete_data['fullName'] ) ? $athlete_data['fullName'] : ( isset( $athlete_data['displayName'] ) ? $athlete_data['displayName'] : 'Unknown Player' ),
            'position' => isset( $athlete_data['position']['abbreviation'] ) ? $athlete_data['position']['abbreviation'] : ( isset( $athlete_data['position'] ) ? $athlete_data['position'] : null ),
            'shirt_number' => isset( $athlete_data['jersey'] ) ? (int) $athlete_data['jersey'] : null,
            'e_club_id' => isset( $athlete_data['team']['id'] ) ? (string) $athlete_data['team']['id'] : null,
            'club' => isset( $athlete_data['team']['name'] ) ? $athlete_data['team']['name'] : null,
            'status' => 'active',
        );
        
        $result = $wpdb->insert(
            $players_table,
            $player_data,
            array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );
        
        if ( $result === false ) {
            fdm_log_datasource_error(
                'player_creation_failed',
                'Failed to create player record',
                array(
                    'e_player_id' => $e_player_id,
                    'db_error' => $wpdb->last_error,
                    'type' => 'player_match_stats',
                )
            );
            return new WP_Error( 'db_error', 'Failed to create player: ' . $wpdb->last_error );
        }
        
        return $e_player_id;
    }
    
    /**
     * Import player match statistics for a single match
     * 
     * @param object|array $match_row Match row from matches table (must have e_match_id, competition_code, etc.)
     * @return array Array with 'players_processed', 'rows_written', 'errors_count'
     */
    public static function import_player_match_stats_for_match( $match_row ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            return new WP_Error( 'database_error', 'Cannot connect to footyforums_data database' );
        }
        
        // Normalize match_row to array if object
        if ( is_object( $match_row ) ) {
            $match_row = (array) $match_row;
        }
        
        $match_id = isset( $match_row['id'] ) ? (int) $match_row['id'] : 0;
        $e_match_id = isset( $match_row['e_match_id'] ) ? (string) $match_row['e_match_id'] : '';
        $competition_code = isset( $match_row['competition_code'] ) ? $match_row['competition_code'] : '';
        
        if ( empty( $e_match_id ) || $match_id <= 0 ) {
            fdm_log_datasource_error(
                'player_match_stats_no_match_id',
                'Match row missing id or e_match_id',
                array(
                    'match_row' => $match_row,
                    'type' => 'player_match_stats',
                )
            );
            return array(
                'players_processed' => 0,
                'rows_written' => 0,
                'errors_count' => 1,
            );
        }
        
        // Build e_team_id to club_id mapping (same as match-team stats)
        $clubs = $db->get_results(
            "SELECT id, canonical_name, e_team_id
             FROM clubs
             WHERE e_team_id IS NOT NULL
               AND e_team_id <> ''",
            ARRAY_A
        );
        
        $team_id_map = array();
        foreach ( $clubs as $club ) {
            $e_team_id = (string) $club['e_team_id'];
            $team_id_map[ $e_team_id ] = array(
                'club_id'        => (int) $club['id'],
                'canonical_name' => $club['canonical_name'],
            );
        }
        
        // Fetch match summary from ESPN
        $summary_data = self::get_match_summary_from_e( $competition_code, $e_match_id );
        if ( is_wp_error( $summary_data ) ) {
            return array(
                'players_processed' => 0,
                'rows_written' => 0,
                'errors_count' => 1,
            );
        }
        
        // Handle gamepackageJSON wrapper if present
        $data = $summary_data;
        if ( isset( $data['gamepackageJSON'] ) && is_array( $data['gamepackageJSON'] ) ) {
            $root = $data['gamepackageJSON'];
        } else {
            $root = $data;
        }
        
        // Extract players using multiple patterns (ported from legacy import_match_boxscore)
        $players_data = array();
        
        // Pattern 1: gamepackageJSON.boxscore.players[*].statistics[*].athletes[*]
        if ( isset( $root['boxscore']['players'] ) && is_array( $root['boxscore']['players'] ) ) {
            foreach ( $root['boxscore']['players'] as $team_players ) {
                $team_id = isset( $team_players['team']['id'] ) ? (string) $team_players['team']['id'] : '';
                if ( isset( $team_players['statistics'] ) && is_array( $team_players['statistics'] ) ) {
                    foreach ( $team_players['statistics'] as $player_stat ) {
                        if ( isset( $player_stat['athletes'] ) && is_array( $player_stat['athletes'] ) ) {
                            foreach ( $player_stat['athletes'] as $athlete_entry ) {
                                if ( isset( $athlete_entry['athlete']['id'] ) ) {
                                    $player_entry = array(
                                        'athlete' => $athlete_entry['athlete'],
                                        'team' => isset( $team_players['team'] ) ? $team_players['team'] : array(),
                                        'statistics' => isset( $athlete_entry['statistics'] ) ? $athlete_entry['statistics'] : array(),
                                        'starter' => isset( $athlete_entry['starter'] ) ? (bool) $athlete_entry['starter'] : false,
                                    );
                                    $player_entry['_team_id'] = $team_id;
                                    $players_data[] = $player_entry;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Pattern 2: boxscore.players[*].statistics[*].athletes[*] (without gamepackageJSON wrapper)
        // Only check if we haven't already found players in Pattern 1 (same structure, different wrapper)
        if ( empty( $players_data ) && isset( $data['boxscore']['players'] ) && is_array( $data['boxscore']['players'] ) ) {
            foreach ( $data['boxscore']['players'] as $team_players ) {
                $team_id = isset( $team_players['team']['id'] ) ? (string) $team_players['team']['id'] : '';
                if ( isset( $team_players['statistics'] ) && is_array( $team_players['statistics'] ) ) {
                    foreach ( $team_players['statistics'] as $player_stat ) {
                        if ( isset( $player_stat['athletes'] ) && is_array( $player_stat['athletes'] ) ) {
                            foreach ( $player_stat['athletes'] as $athlete_entry ) {
                                if ( isset( $athlete_entry['athlete']['id'] ) ) {
                                    $player_entry = array(
                                        'athlete' => $athlete_entry['athlete'],
                                        'team' => isset( $team_players['team'] ) ? $team_players['team'] : array(),
                                        'statistics' => isset( $athlete_entry['statistics'] ) ? $athlete_entry['statistics'] : array(),
                                        'starter' => isset( $athlete_entry['starter'] ) ? (bool) $athlete_entry['starter'] : false,
                                    );
                                    $player_entry['_team_id'] = $team_id;
                                    $players_data[] = $player_entry;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Pattern 3: gamepackageJSON.boxscore.teams[*].statistics[*].athletes[*]
        if ( empty( $players_data ) && isset( $root['boxscore']['teams'] ) && is_array( $root['boxscore']['teams'] ) ) {
            foreach ( $root['boxscore']['teams'] as $team ) {
                $team_id = isset( $team['team']['id'] ) ? (string) $team['team']['id'] : '';
                if ( isset( $team['statistics'] ) && is_array( $team['statistics'] ) ) {
                    foreach ( $team['statistics'] as $stat_group ) {
                        if ( isset( $stat_group['athletes'] ) && is_array( $stat_group['athletes'] ) ) {
                            foreach ( $stat_group['athletes'] as $athlete_entry ) {
                                if ( isset( $athlete_entry['athlete']['id'] ) ) {
                                    $player_entry = array(
                                        'athlete' => $athlete_entry['athlete'],
                                        'team' => isset( $team['team'] ) ? $team['team'] : array(),
                                        'statistics' => isset( $athlete_entry['statistics'] ) ? $athlete_entry['statistics'] : array(),
                                        'starter' => isset( $athlete_entry['starter'] ) ? (bool) $athlete_entry['starter'] : false,
                                    );
                                    $player_entry['_team_id'] = $team_id;
                                    $players_data[] = $player_entry;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Pattern 4: boxscore.teams[*].statistics[*].athletes[*] (without gamepackageJSON wrapper)
        // Only check if we haven't already found players in Pattern 3 (same structure, different wrapper)
        if ( empty( $players_data ) && isset( $data['boxscore']['teams'] ) && is_array( $data['boxscore']['teams'] ) ) {
            foreach ( $data['boxscore']['teams'] as $team ) {
                $team_id = isset( $team['team']['id'] ) ? (string) $team['team']['id'] : '';
                if ( isset( $team['statistics'] ) && is_array( $team['statistics'] ) ) {
                    foreach ( $team['statistics'] as $stat_group ) {
                        if ( isset( $stat_group['athletes'] ) && is_array( $stat_group['athletes'] ) ) {
                            foreach ( $stat_group['athletes'] as $athlete_entry ) {
                                if ( isset( $athlete_entry['athlete']['id'] ) ) {
                                    $player_entry = array(
                                        'athlete' => $athlete_entry['athlete'],
                                        'team' => isset( $team['team'] ) ? $team['team'] : array(),
                                        'statistics' => isset( $athlete_entry['statistics'] ) ? $athlete_entry['statistics'] : array(),
                                        'starter' => isset( $athlete_entry['starter'] ) ? (bool) $athlete_entry['starter'] : false,
                                    );
                                    $player_entry['_team_id'] = $team_id;
                                    $players_data[] = $player_entry;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Pattern 5: gamepackageJSON.players[*] variants
        if ( isset( $root['players'] ) && is_array( $root['players'] ) ) {
            foreach ( $root['players'] as $player_entry_raw ) {
                if ( isset( $player_entry_raw['athlete']['id'] ) ) {
                    $player_entry = array(
                        'athlete' => $player_entry_raw['athlete'],
                        'team' => isset( $player_entry_raw['team'] ) ? $player_entry_raw['team'] : array(),
                        'statistics' => isset( $player_entry_raw['statistics'] ) ? $player_entry_raw['statistics'] : array(),
                        'starter' => isset( $player_entry_raw['starter'] ) ? (bool) $player_entry_raw['starter'] : false,
                    );
                    $player_entry['_team_id'] = isset( $player_entry_raw['team']['id'] ) ? (string) $player_entry_raw['team']['id'] : '';
                    $players_data[] = $player_entry;
                }
            }
        }
        
        // Pattern 6: Top-level rosters[] (crucial for 2019 data)
        // Always check rosters as it's a different structure that may have additional players
        if ( isset( $root['rosters'] ) && is_array( $root['rosters'] ) ) {
            foreach ( $root['rosters'] as $team_roster ) {
                $roster_team_id = isset( $team_roster['team']['id'] ) ? (string) $team_roster['team']['id'] : '';
                
                // Check roster.roster[] array
                if ( isset( $team_roster['roster'] ) && is_array( $team_roster['roster'] ) ) {
                    foreach ( $team_roster['roster'] as $roster_entry ) {
                        if ( isset( $roster_entry['athlete']['id'] ) ) {
                            $player_entry = array(
                                'athlete' => $roster_entry['athlete'],
                                'team' => isset( $team_roster['team'] ) ? $team_roster['team'] : array(),
                                'statistics' => isset( $roster_entry['stats'] ) ? $roster_entry['stats'] : ( isset( $roster_entry['statistics'] ) ? $roster_entry['statistics'] : array() ),
                                'starter' => isset( $roster_entry['starter'] ) ? (bool) $roster_entry['starter'] : false,
                            );
                            $player_entry['_team_id'] = $roster_team_id;
                            $players_data[] = $player_entry;
                        } elseif ( isset( $roster_entry['player']['id'] ) ) {
                            $player_entry = array(
                                'athlete' => $roster_entry['player'],
                                'team' => isset( $team_roster['team'] ) ? $team_roster['team'] : array(),
                                'statistics' => isset( $roster_entry['stats'] ) ? $roster_entry['stats'] : ( isset( $roster_entry['statistics'] ) ? $roster_entry['statistics'] : array() ),
                                'starter' => isset( $roster_entry['starter'] ) ? (bool) $roster_entry['starter'] : false,
                            );
                            $player_entry['_team_id'] = $roster_team_id;
                            $players_data[] = $player_entry;
                        }
                    }
                }
                
                // Check roster.entries[] array
                if ( isset( $team_roster['entries'] ) && is_array( $team_roster['entries'] ) ) {
                    foreach ( $team_roster['entries'] as $entry ) {
                        if ( isset( $entry['athlete']['id'] ) ) {
                            $player_entry = array(
                                'athlete' => $entry['athlete'],
                                'team' => isset( $team_roster['team'] ) ? $team_roster['team'] : array(),
                                'statistics' => isset( $entry['stats'] ) ? $entry['stats'] : ( isset( $entry['statistics'] ) ? $entry['statistics'] : array() ),
                                'starter' => isset( $entry['starter'] ) ? (bool) $entry['starter'] : false,
                            );
                            $player_entry['_team_id'] = $roster_team_id;
                            $players_data[] = $player_entry;
                        } elseif ( isset( $entry['player']['id'] ) ) {
                            $player_entry = array(
                                'athlete' => $entry['player'],
                                'team' => isset( $team_roster['team'] ) ? $team_roster['team'] : array(),
                                'statistics' => isset( $entry['stats'] ) ? $entry['stats'] : ( isset( $entry['statistics'] ) ? $entry['statistics'] : array() ),
                                'starter' => isset( $entry['starter'] ) ? (bool) $entry['starter'] : false,
                            );
                            $player_entry['_team_id'] = $roster_team_id;
                            $players_data[] = $player_entry;
                        }
                    }
                }
                
                // Check roster.athletes[] array
                if ( isset( $team_roster['athletes'] ) && is_array( $team_roster['athletes'] ) ) {
                    foreach ( $team_roster['athletes'] as $athlete ) {
                        if ( isset( $athlete['id'] ) ) {
                            $player_entry = array(
                                'athlete' => $athlete,
                                'team' => isset( $team_roster['team'] ) ? $team_roster['team'] : array(),
                                'statistics' => array(),
                                'starter' => false,
                            );
                            $player_entry['_team_id'] = $roster_team_id;
                            $players_data[] = $player_entry;
                        }
                    }
                }
            }
        }
        
        // Pattern 7: boxscore.teams[].roster.entries[]
        // Always check as it's a different structure (roster.entries vs statistics.athletes)
        if ( isset( $root['boxscore']['teams'] ) && is_array( $root['boxscore']['teams'] ) ) {
            foreach ( $root['boxscore']['teams'] as $team ) {
                $team_id = isset( $team['team']['id'] ) ? (string) $team['team']['id'] : '';
                
                if ( isset( $team['roster']['entries'] ) && is_array( $team['roster']['entries'] ) ) {
                    foreach ( $team['roster']['entries'] as $entry ) {
                        if ( isset( $entry['athlete']['id'] ) ) {
                            $player_entry = array(
                                'athlete' => $entry['athlete'],
                                'team' => isset( $team['team'] ) ? $team['team'] : array(),
                                'statistics' => isset( $entry['stats'] ) ? $entry['stats'] : ( isset( $entry['statistics'] ) ? $entry['statistics'] : array() ),
                                'starter' => isset( $entry['starter'] ) ? (bool) $entry['starter'] : false,
                            );
                            $player_entry['_team_id'] = $team_id;
                            $players_data[] = $player_entry;
                        } elseif ( isset( $entry['player']['id'] ) ) {
                            $player_entry = array(
                                'athlete' => $entry['player'],
                                'team' => isset( $team['team'] ) ? $team['team'] : array(),
                                'statistics' => isset( $entry['stats'] ) ? $entry['stats'] : ( isset( $entry['statistics'] ) ? $entry['statistics'] : array() ),
                                'starter' => isset( $entry['starter'] ) ? (bool) $entry['starter'] : false,
                            );
                            $player_entry['_team_id'] = $team_id;
                            $players_data[] = $player_entry;
                        }
                    }
                }
            }
        }
        
        if ( empty( $players_data ) ) {
            fdm_log_datasource_error(
                'player_match_stats_no_players',
                'Match summary has no player data',
                array(
                    'e_match_id' => $e_match_id,
                    'competition_code' => $competition_code,
                    'type' => 'player_match_stats',
                )
            );
            return array(
                'players_processed' => 0,
                'rows_written' => 0,
                'errors_count' => 1,
            );
        }
        
        // Log successful player extraction
        fdm_log_datasource_info(
            'debug',
            'Player match stats players found',
            array(
                'e_match_id' => $e_match_id,
                'count' => count( $players_data ),
                'type' => 'player_match_stats',
            )
        );
        
        // Delete existing player stats for this match (idempotency)
        // Use local match id, not ESPN event id
        $db->query(
            $db->prepare(
                "DELETE FROM fdm_player_match_stats WHERE match_id = %d",
                $match_id
            )
        );
        
        // Consolidate players - a player may appear in multiple stat groups, so merge all their stats
        $players_consolidated = array();
        foreach ( $players_data as $player_entry ) {
            $athlete = $player_entry['athlete'];
            $team = $player_entry['team'];
            $statistics = $player_entry['statistics'];
            
            // Use _team_id if set (from pattern matching), otherwise fall back to team.id
            $e_team_id = isset( $player_entry['_team_id'] ) && ! empty( $player_entry['_team_id'] ) 
                ? $player_entry['_team_id'] 
                : ( isset( $team['id'] ) ? (string) $team['id'] : '' );
            
            $e_player_id = isset( $athlete['id'] ) ? (string) $athlete['id'] : '';
            
            if ( empty( $e_player_id ) ) {
                continue;
            }
            
            // Initialize player entry if not seen before
            if ( ! isset( $players_consolidated[ $e_player_id ] ) ) {
                $players_consolidated[ $e_player_id ] = array(
                    'athlete' => $athlete,
                    'team' => $team,
                    'team_id' => $e_team_id,
                    'stats_map' => array(),
                    'starter' => isset( $player_entry['starter'] ) ? $player_entry['starter'] : false,
                );
            }
            
            // Merge statistics - if same stat appears multiple times, take the max value
            if ( is_array( $statistics ) ) {
                foreach ( $statistics as $stat ) {
                    if ( ! is_array( $stat ) || ! isset( $stat['name'] ) ) {
                        continue;
                    }
                    $stat_name = strtolower( $stat['name'] );
                    $stat_value = isset( $stat['value'] ) ? $stat['value'] : ( isset( $stat['displayValue'] ) ? $stat['displayValue'] : 0 );
                    
                    // Try to extract numeric value
                    $numeric = 0;
                    if ( is_numeric( $stat_value ) ) {
                        $numeric = (float) $stat_value;
                    } elseif ( is_string( $stat_value ) ) {
                        // Try to extract number from string like "90%" or "15/20"
                        if ( preg_match( '/^(\d+)/', $stat_value, $matches ) ) {
                            $numeric = (float) $matches[1];
                        }
                    }
                    
                    // Merge: take max if already exists (handles cases where player appears in multiple stat groups)
                    if ( ! isset( $players_consolidated[ $e_player_id ]['stats_map'][ $stat_name ] ) ) {
                        $players_consolidated[ $e_player_id ]['stats_map'][ $stat_name ] = $numeric;
                    } else {
                        $players_consolidated[ $e_player_id ]['stats_map'][ $stat_name ] = max(
                            $players_consolidated[ $e_player_id ]['stats_map'][ $stat_name ],
                            $numeric
                        );
                    }
                }
            }
        }
        
        $players_processed = 0;
        $rows_written = 0;
        $errors_count = 0;
        
        // Process each consolidated player
        foreach ( $players_consolidated as $e_player_id => $player_data ) {
            $athlete = $player_data['athlete'];
            $team = $player_data['team'];
            $stats_map = $player_data['stats_map'];
            $e_team_id = $player_data['team_id'];
            
            // Map ESPN team_id to local club_id
            if ( empty( $e_team_id ) || ! isset( $team_id_map[ $e_team_id ] ) ) {
                $errors_count++;
                fdm_log_datasource_error(
                    'player_match_stats_team_mapping_failed',
                    'Cannot map ESPN team_id to club_id',
                    array(
                        'e_match_id' => $e_match_id,
                        'e_team_id' => $e_team_id,
                        'e_player_id' => $e_player_id,
                        'type' => 'player_match_stats',
                    )
                );
                continue;
            }
            $club_id = $team_id_map[ $e_team_id ]['club_id'];
            
            // Cast ESPN player_id to BIGINT
            $player_id = (int) $e_player_id;
            if ( $player_id <= 0 ) {
                $errors_count++;
                continue;
            }
            
            // Map ESPN stat names to our columns
            $minutes_played = isset( $stats_map['minutes'] ) ? (int) round( $stats_map['minutes'] ) : ( isset( $athlete['minutes'] ) ? (int) $athlete['minutes'] : 0 );
            $goals = isset( $stats_map['goals'] ) ? (int) round( $stats_map['goals'] ) : 0;
            $assists = isset( $stats_map['assists'] ) ? (int) round( $stats_map['assists'] ) : 0;
            $shots = isset( $stats_map['shots'] ) ? (int) round( $stats_map['shots'] ) : ( isset( $stats_map['totalshots'] ) ? (int) round( $stats_map['totalshots'] ) : 0 );
            $shots_on_target = isset( $stats_map['shotsontarget'] ) ? (int) round( $stats_map['shotsontarget'] ) : ( isset( $stats_map['shots on goal'] ) ? (int) round( $stats_map['shots on goal'] ) : 0 );
            $passes_attempted = isset( $stats_map['passes'] ) ? (int) round( $stats_map['passes'] ) : ( isset( $stats_map['passesattempted'] ) ? (int) round( $stats_map['passesattempted'] ) : 0 );
            $passes_completed = isset( $stats_map['passescompleted'] ) ? (int) round( $stats_map['passescompleted'] ) : 0;
            $tackles = isset( $stats_map['tackles'] ) ? (int) round( $stats_map['tackles'] ) : ( isset( $stats_map['totaltackles'] ) ? (int) round( $stats_map['totaltackles'] ) : 0 );
            $interceptions = isset( $stats_map['interceptions'] ) ? (int) round( $stats_map['interceptions'] ) : 0;
            $clearances = isset( $stats_map['clearances'] ) ? (int) round( $stats_map['clearances'] ) : 0;
            $fouls_committed = isset( $stats_map['foulscommitted'] ) ? (int) round( $stats_map['foulscommitted'] ) : 0;
            $fouls_drawn = isset( $stats_map['foulsdrawn'] ) ? (int) round( $stats_map['foulsdrawn'] ) : 0;
            $offsides = isset( $stats_map['offsides'] ) ? (int) round( $stats_map['offsides'] ) : 0;
            $yellow_cards = isset( $stats_map['yellowcards'] ) ? (int) round( $stats_map['yellowcards'] ) : 0;
            $red_cards = isset( $stats_map['redcards'] ) ? (int) round( $stats_map['redcards'] ) : 0;
            
            $position_code = isset( $athlete['position']['abbreviation'] ) ? $athlete['position']['abbreviation'] : ( isset( $athlete['position'] ) ? $athlete['position'] : null );
            $shirt_number = isset( $athlete['jersey'] ) ? (int) $athlete['jersey'] : null;
            $is_starting = isset( $player_data['starter'] ) && $player_data['starter'] ? 1 : 0;
            
            // Insert player match stats with correct schema
            $result = $db->replace(
                'fdm_player_match_stats',
                array(
                    'match_id' => $match_id,
                    'player_id' => $player_id,
                    'club_id' => $club_id,
                    'is_starting' => $is_starting,
                    'minutes_played' => $minutes_played,
                    'position_code' => $position_code,
                    'shirt_number' => $shirt_number,
                    'goals' => $goals,
                    'assists' => $assists,
                    'shots' => $shots,
                    'shots_on_target' => $shots_on_target,
                    'yellow_cards' => $yellow_cards,
                    'red_cards' => $red_cards,
                    'passes_attempted' => $passes_attempted,
                    'passes_completed' => $passes_completed,
                    'tackles' => $tackles,
                    'interceptions' => $interceptions,
                    'clearances' => $clearances,
                    'fouls_committed' => $fouls_committed,
                    'fouls_drawn' => $fouls_drawn,
                    'offsides' => $offsides,
                ),
                array(
                    '%d', // match_id
                    '%d', // player_id
                    '%d', // club_id
                    '%d', // is_starting
                    '%d', // minutes_played
                    '%s', // position_code
                    '%d', // shirt_number
                    '%d', // goals
                    '%d', // assists
                    '%d', // shots
                    '%d', // shots_on_target
                    '%d', // yellow_cards
                    '%d', // red_cards
                    '%d', // passes_attempted
                    '%d', // passes_completed
                    '%d', // tackles
                    '%d', // interceptions
                    '%d', // clearances
                    '%d', // fouls_committed
                    '%d', // fouls_drawn
                    '%d', // offsides
                )
            );
            
            if ( $result === false ) {
                $errors_count++;
                fdm_log_datasource_error(
                    'player_match_stats_db_error',
                    'Failed to save player match stats',
                    array(
                        'match_id' => $match_id,
                        'player_id' => $player_id,
                        'club_id' => $club_id,
                        'db_error' => $db->last_error,
                        'type' => 'player_match_stats',
                    )
                );
            } else {
                $rows_written++;
            }
            
            $players_processed++;
        }
        
        return array(
            'players_processed' => $players_processed,
            'rows_written' => $rows_written,
            'errors_count' => $errors_count,
        );
    }
    
    /**
     * Import player match statistics for all matches in a season
     * 
     * @param string $competition_code Competition code (e.g., 'eng.1')
     * @param int $season_year Season year (e.g., 2019)
     * @return array|WP_Error Array with 'matches_processed', 'players_processed', 'rows_written', 'errors_count'
     */
    public static function import_player_match_stats_for_season( $competition_code, $season_year ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            return new WP_Error( 'database_error', 'Cannot connect to footyforums_data database' );
        }
        
        $competition_code = trim( (string) $competition_code );
        $season_year = (int) $season_year;
        
        if ( empty( $competition_code ) || $season_year < 1800 || $season_year > 3000 ) {
            return new WP_Error( 'invalid_argument', 'Invalid competition_code or season_year' );
        }
        
        // Fetch all completed matches for this competition and season
        // Filter by matches that have results (home_goals and away_goals are set)
        // Note: status_code may be 'scheduled' even for completed matches, so we check for goals instead
        $matches = $db->get_results(
            $db->prepare(
                "SELECT * FROM matches
                 WHERE competition_code = %s
                   AND season_year = %d
                   AND home_goals IS NOT NULL
                   AND away_goals IS NOT NULL
                 ORDER BY match_date ASC",
                $competition_code,
                $season_year
            ),
            ARRAY_A
        );
        
        if ( empty( $matches ) ) {
            fdm_log_datasource_info(
                'info',
                'Player match stats season import, no completed matches found',
                array(
                    'competition_code' => $competition_code,
                    'season_year' => $season_year,
                    'type' => 'player_match_stats',
                )
            );
            return array(
                'matches_processed' => 0,
                'players_processed' => 0,
                'rows_written' => 0,
                'errors_count' => 0,
            );
        }
        
        $matches_processed = 0;
        $total_players_processed = 0;
        $total_rows_written = 0;
        $total_errors_count = 0;
        
        foreach ( $matches as $match ) {
            $result = self::import_player_match_stats_for_match( $match );
            
            if ( is_wp_error( $result ) ) {
                $total_errors_count++;
                continue;
            }
            
            $matches_processed++;
            $total_players_processed += $result['players_processed'];
            $total_rows_written += $result['rows_written'];
            $total_errors_count += $result['errors_count'];
            
            // Log progress every 10 matches
            if ( $matches_processed % 10 === 0 ) {
                fdm_log_datasource_info(
                    'info',
                    sprintf( 'Player match stats import progress: %d/%d matches processed', $matches_processed, count( $matches ) ),
                    array(
                        'competition_code' => $competition_code,
                        'season_year' => $season_year,
                        'matches_processed' => $matches_processed,
                        'total_matches' => count( $matches ),
                        'type' => 'player_match_stats',
                    )
                );
            }
        }
        
        // Log summary
        fdm_log_datasource_info(
            'info',
            'Player match stats season import completed',
            array(
                'competition_code' => $competition_code,
                'season_year' => $season_year,
                'matches_processed' => $matches_processed,
                'players_processed' => $total_players_processed,
                'rows_written' => $total_rows_written,
                'errors_count' => $total_errors_count,
                'type' => 'player_match_stats',
            )
        );
        
        return array(
            'matches_processed' => $matches_processed,
            'players_processed' => $total_players_processed,
            'rows_written' => $total_rows_written,
            'errors_count' => $total_errors_count,
        );
    }
    
    /**
     * Rebuild player season statistics by aggregating from player match stats
     * 
     * @param string $competition_code Competition code (e.g., 'eng.1')
     * @param int $season_year Season year (e.g., 2019)
     * @return array|WP_Error Array with 'rows_written', 'errors_count'
     */
    public static function rebuild_player_season_stats( $competition_code, $season_year ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            return new WP_Error( 'database_error', 'Cannot connect to footyforums_data database' );
        }
        
        $competition_code = trim( (string) $competition_code );
        $season_year = (int) $season_year;
        
        if ( empty( $competition_code ) || $season_year < 1800 || $season_year > 3000 ) {
            return new WP_Error( 'invalid_argument', 'Invalid competition_code or season_year' );
        }
        
        // Delete existing season stats for this competition and season (idempotency)
        $db->query(
            $db->prepare(
                "DELETE FROM fdm_player_season_stats
                 WHERE competition_code = %s
                   AND season_year = %d",
                $competition_code,
                $season_year
            )
        );
        
        // Aggregate from fdm_player_match_stats joined to matches
        // Join on matches.id = fdm_player_match_stats.match_id (local match id)
        // Group by (player_id, competition_code, season_year) to match unique index
        $insert_query = $db->prepare(
            "INSERT IGNORE INTO fdm_player_season_stats (
                player_id,
                competition_code,
                season_year,
                club_id,
                appearances,
                starts,
                minutes,
                goals,
                assists,
                shots,
                shots_on_target,
                yellow_cards,
                red_cards,
                passes_attempted,
                passes_completed,
                chances_created,
                tackles,
                interceptions,
                clearances,
                blocks,
                fouls_committed,
                fouls_drawn,
                offsides,
                penalties_scored,
                penalties_missed,
                penalties_saved
            )
            SELECT
                p.player_id,
                m.competition_code AS competition_code,
                m.season_year AS season_year,
                MAX(p.club_id) AS club_id,
                COUNT(DISTINCT p.match_id) AS appearances,
                SUM(CASE WHEN p.is_starting = 1 THEN 1 ELSE 0 END) AS starts,
                SUM(p.minutes_played) AS minutes,
                SUM(p.goals) AS goals,
                SUM(p.assists) AS assists,
                SUM(p.shots) AS shots,
                SUM(p.shots_on_target) AS shots_on_target,
                SUM(p.yellow_cards) AS yellow_cards,
                SUM(p.red_cards) AS red_cards,
                SUM(p.passes_attempted) AS passes_attempted,
                SUM(p.passes_completed) AS passes_completed,
                0 AS chances_created,
                SUM(p.tackles) AS tackles,
                SUM(p.interceptions) AS interceptions,
                SUM(p.clearances) AS clearances,
                0 AS blocks,
                SUM(p.fouls_committed) AS fouls_committed,
                SUM(p.fouls_drawn) AS fouls_drawn,
                SUM(p.offsides) AS offsides,
                0 AS penalties_scored,
                0 AS penalties_missed,
                0 AS penalties_saved
            FROM fdm_player_match_stats p
            INNER JOIN matches m ON m.id = p.match_id
            WHERE m.competition_code = %s
              AND m.season_year = %d
            GROUP BY p.player_id, m.competition_code, m.season_year",
            $competition_code,
            $season_year
        );
        
        $result = $db->query( $insert_query );
        
        if ( $result === false ) {
            fdm_log_datasource_error(
                'player_season_stats_db_error',
                'Failed to rebuild player season stats',
                array(
                    'competition_code' => $competition_code,
                    'season_year' => $season_year,
                    'db_error' => $db->last_error,
                    'type' => 'player_season_stats',
                )
            );
            return array(
                'rows_written' => 0,
                'errors_count' => 1,
            );
        }
        
        $rows_written = isset( $db->rows_affected ) ? (int) $db->rows_affected : 0;
        
        // Log summary
        fdm_log_datasource_info(
            'info',
            'Player season stats rebuild completed',
            array(
                'competition_code' => $competition_code,
                'season_year' => $season_year,
                'rows_written' => $rows_written,
                'errors_count' => 0,
                'type' => 'player_season_stats',
            )
        );
        
        return array(
            'rows_written' => $rows_written,
            'errors_count' => 0,
        );
    }
    
    /**
     * ESPN competitions configuration
     * Maps competition_code to ESPN API parameters
     * 
     * @return array Competition config keyed by competition_code
     */
    private static function getEspnCompetitionsConfig() {
        return array(
            'uefa.champions' => array(
                'espn_league_code' => 'UEFA.CHAMPIONS',
                'description'      => 'UEFA Champions League',
            ),
            'uefa.europa' => array(
                'espn_league_code' => 'UEFA.EUROPA',
                'description'      => 'UEFA Europa League',
            ),
            'uefa.europa_conf' => array(
                'espn_league_code' => 'UEFA.EUROPA.CONF',
                'description'      => 'UEFA Europa Conference League',
            ),
        );
    }
    
    /**
     * Map our e_league_code to ESPN league code used in the soccer scoreboard API.
     */
    private static function getEspnLeagueConfig() {
        return array(
            'eng.1' => array( 'espn_league_code' => 'eng.1', 'description' => 'Premier League' ),
            'esp.1' => array( 'espn_league_code' => 'esp.1', 'description' => 'La Liga' ),
            'ita.1' => array( 'espn_league_code' => 'ita.1', 'description' => 'Serie A' ),
            'ger.1' => array( 'espn_league_code' => 'ger.1', 'description' => 'Bundesliga' ),
            'fra.1' => array( 'espn_league_code' => 'fra.1', 'description' => 'Ligue 1' ),
            'por.1' => array( 'espn_league_code' => 'por.1', 'description' => 'Primeira Liga' ),
            'ned.1' => array( 'espn_league_code' => 'ned.1', 'description' => 'Eredivisie' ),
            'bel.1' => array( 'espn_league_code' => 'bel.1', 'description' => 'Belgian Pro League' ),
            'aut.1' => array( 'espn_league_code' => 'aut.1', 'description' => 'Austrian Bundesliga' ),
            'sui.1' => array( 'espn_league_code' => 'sui.1', 'description' => 'Swiss Super League' ),
            'tur.1' => array( 'espn_league_code' => 'tur.1', 'description' => 'Turkish Super Lig' ),
            'rus.1' => array( 'espn_league_code' => 'rus.1', 'description' => 'Russian Premier League' ),
            'ukr.1' => array( 'espn_league_code' => 'ukr.1', 'description' => 'Ukrainian Premier League' ),
            'sco.1' => array( 'espn_league_code' => 'sco.1', 'description' => 'Scottish Premiership' ),
            'gre.1' => array( 'espn_league_code' => 'gre.1', 'description' => 'Greek Super League' ),
            'swe.1' => array( 'espn_league_code' => 'swe.1', 'description' => 'Allsvenskan' ),
            'nor.1' => array( 'espn_league_code' => 'nor.1', 'description' => 'Eliteserien' ),
            'den.1' => array( 'espn_league_code' => 'den.1', 'description' => 'Superliga' ),
            'fin.1' => array( 'espn_league_code' => 'fin.1', 'description' => 'Veikkausliiga' ),
            'isl.1' => array( 'espn_league_code' => 'isl.1', 'description' => 'Urvalsdeild' ),
            'pol.1' => array( 'espn_league_code' => 'pol.1', 'description' => 'Ekstraklasa' ),
            'cze.1' => array( 'espn_league_code' => 'cze.1', 'description' => 'Czech First League' ),
            'srb.1' => array( 'espn_league_code' => 'srb.1', 'description' => 'Serbian SuperLiga' ),
            'cro.1' => array( 'espn_league_code' => 'cro.1', 'description' => 'Croatian First Football League' ),
            'hun.1' => array( 'espn_league_code' => 'hun.1', 'description' => 'NB I' ),
            'bul.1' => array( 'espn_league_code' => 'bul.1', 'description' => 'First Professional Football League' ),
            'svn.1' => array( 'espn_league_code' => 'svn.1', 'description' => 'Slovenian PrvaLiga' ),
            'svk.1' => array( 'espn_league_code' => 'svk.1', 'description' => 'Slovak Super Liga' ),
            'rou.1' => array( 'espn_league_code' => 'rou.1', 'description' => 'Liga I' ),
            'geo.1' => array( 'espn_league_code' => 'geo.1', 'description' => 'Erovnuli Liga' ),
            'arm.1' => array( 'espn_league_code' => 'arm.1', 'description' => 'Armenian Premier League' ),
            'cyp.1' => array( 'espn_league_code' => 'cyp.1', 'description' => 'Cypriot First Division' ),
            'mlt.1' => array( 'espn_league_code' => 'mlt.1', 'description' => 'Maltese Premier League' ),
            'bra.1' => array( 'espn_league_code' => 'bra.1', 'description' => 'Brasileirão Serie A' ),
            'bra.2' => array( 'espn_league_code' => 'bra.2', 'description' => 'Brasileirão Serie B' ),
            'arg.1' => array( 'espn_league_code' => 'arg.1', 'description' => 'Argentine Primera División' ),
            'uru.1' => array( 'espn_league_code' => 'uru.1', 'description' => 'Uruguayan Primera División' ),
            'chi.1' => array( 'espn_league_code' => 'chi.1', 'description' => 'Chilean Primera División' ),
            'col.1' => array( 'espn_league_code' => 'col.1', 'description' => 'Categoría Primera A' ),
            'ecu.1' => array( 'espn_league_code' => 'ecu.1', 'description' => 'Ecuadorian Serie A' ),
            'par.1' => array( 'espn_league_code' => 'par.1', 'description' => 'Paraguayan Primera División' ),
            'per.1' => array( 'espn_league_code' => 'per.1', 'description' => 'Peruvian Primera División' ),
            'bol.1' => array( 'espn_league_code' => 'bol.1', 'description' => 'Bolivian Primera División' ),
            'ven.1' => array( 'espn_league_code' => 'ven.1', 'description' => 'Venezuelan Primera División' ),
        );
    }
    
    /**
     * Map stage/round for competitions (keeps existing Champions League logic)
     *
     * @param string      $competitionCode Competition code
     * @param string      $stageType       main|qualifying
     * @param string|null $roundName       Round/series text from API
     * @return array{0:string,1:int} [stage_round, stage_round_sort]
     */
    private static function map_stage_round( $competitionCode, $stageType, $roundName ) {
        $stage_round      = 'main';
        $stage_round_sort = 0;
        
        if ( $competitionCode === 'uefa.champions' && $stageType === 'qualifying' ) {
            $normalized = is_string( $roundName ) ? strtolower( $roundName ) : '';
            
            if ( strpos( $normalized, 'first qualifying' ) !== false ) {
                $stage_round      = 'q1';
                $stage_round_sort = 1;
            } elseif ( strpos( $normalized, 'second qualifying' ) !== false ) {
                $stage_round      = 'q2';
                $stage_round_sort = 2;
            } elseif ( strpos( $normalized, 'third qualifying' ) !== false ) {
                $stage_round      = 'q3';
                $stage_round_sort = 3;
            } elseif (
                strpos( $normalized, 'play-off' ) !== false ||
                strpos( $normalized, 'playoff' ) !== false
            ) {
                $stage_round      = 'playoff';
                $stage_round_sort = 4;
            }
        }
        
        return array( $stage_round, $stage_round_sort );
    }
    
    /**
     * Build season date ranges (month-by-month)
     * Returns array of ['from' => 'YYYYMMDD', 'to' => 'YYYYMMDD'] for each month
     * Season runs from 1 July of $seasonYear to 30 June of $seasonYear + 1
     * 
     * @param int $seasonYear Season start year (e.g., 2024 for 2024-25)
     * @return array Array of date ranges
     */
    private static function build_season_date_ranges( $seasonYear ) {
        $ranges = array();
        
        // Season starts 1 July of $seasonYear
        $start = new DateTime( "{$seasonYear}-07-01" );
        // Season ends 30 June of $seasonYear + 1
        $end = new DateTime( ( $seasonYear + 1 ) . "-06-30" );
        
        $current = clone $start;
        
        while ( $current <= $end ) {
            // First day of current month
            $from = clone $current;
            $from->modify( 'first day of this month' );
            
            // Last day of current month
            $to = clone $current;
            $to->modify( 'last day of this month' );
            
            // If we're before the season start, use season start
            if ( $from < $start ) {
                $from = clone $start;
            }
            
            // If we're after the season end, use season end
            if ( $to > $end ) {
                $to = clone $end;
            }
            
            $ranges[] = array(
                'from' => $from->format( 'Ymd' ),
                'to'   => $to->format( 'Ymd' ),
            );
            
            // Move to first day of next month
            $current->modify( 'first day of next month' );
        }
        
        return $ranges;
    }
    
    /**
     * Fetch JSON from URL using WordPress HTTP API
     * 
     * @param string $url URL to fetch
     * @return array Response array with 'ok', 'http_code', 'content', 'error', 'raw_body'
     */
    private static function fetch_json_from_url( $url ) {
        $response = wp_remote_get( $url, array(
            'timeout'     => 20,
            'user-agent'  => 'footyforums-importer/1.0',
        ) );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'ok'         => false,
                'http_code'  => 0,
                'content'    => null,
                'error'      => 'WP_Error: ' . $response->get_error_message(),
                'raw_body'   => null,
            );
        }
        
        $httpCode = wp_remote_retrieve_response_code( $response );
        $body     = wp_remote_retrieve_body( $response );
        
        if ( $httpCode !== 200 || empty( $body ) ) {
            return array(
                'ok'         => false,
                'http_code'  => $httpCode,
                'content'    => null,
                'error'      => "HTTP {$httpCode} or empty body",
                'raw_body'   => $body,
            );
        }
        
        $decoded = json_decode( $body, true );
        
        if ( ! is_array( $decoded ) ) {
            return array(
                'ok'         => false,
                'http_code'  => $httpCode,
                'content'    => null,
                'error'      => 'Invalid JSON in response',
                'raw_body'   => $body,
            );
        }
        
        return array(
            'ok'         => true,
            'http_code'  => $httpCode,
            'content'    => $decoded,
            'error'      => null,
            'raw_body'   => $body,
        );
    }
    
    /**
     * Import one competition for one season from ESPN
     * Creates/updates job in e_import_jobs and stores raw JSON in e_fixtures_raw
     * 
     * @param string $competitionCode Competition code (e.g., 'uefa.champions')
     * @param int $seasonYear Season start year (e.g., 2024 for 2024-25)
     * @return array Result array with 'job_id', 'status', 'message'
     * @throws InvalidArgumentException If competition code is invalid
     * @throws RuntimeException If database operations fail
     */
    public static function importEspnCompetitionSeason( $competitionCode, $seasonYear ) {
        // Get config and validate competition code
        $config = self::getEspnCompetitionsConfig();
        if ( ! isset( $config[ $competitionCode ] ) ) {
            throw new InvalidArgumentException( "Unknown competition code: {$competitionCode}" );
        }
        
        $espnLeagueCode = $config[ $competitionCode ]['espn_league_code'];
        
        // Validate season year
        if ( ! is_numeric( $seasonYear ) || $seasonYear < 2000 || $seasonYear > 2100 ) {
            throw new InvalidArgumentException( "Invalid season year: {$seasonYear}" );
        }
        
        // Shortcut rule for UCL pre-2001
        if ( $competitionCode === 'uefa.champions' && $seasonYear < 2001 ) {
            throw new RuntimeException( "Pre-2001 UCL season not supported in this importer" );
        }
        
        // Get database connection
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            throw new RuntimeException( 'Cannot connect to footyforums_data database' );
        }
        
        // Create or update job in e_import_jobs
        $jobLabel = $config[ $competitionCode ]['description'] . ' ' . $seasonYear . '-' . ( $seasonYear + 1 );
        
        // Check if job already exists
        $existingJob = $db->get_row(
            $db->prepare(
                "SELECT id, status FROM e_import_jobs 
                 WHERE competition_code = %s AND season_year = %d 
                 LIMIT 1",
                $competitionCode,
                $seasonYear
            ),
            ARRAY_A
        );
        
        if ( $existingJob ) {
            $jobId = (int) $existingJob['id'];
            // Update job status to pending if it's not already done
            if ( $existingJob['status'] !== 'ok' ) {
                $db->update(
                    'e_import_jobs',
                    array(
                        'status'        => 'pending',
                        'error_message' => null,
                        'job_label'     => $jobLabel,
                    ),
                    array( 'id' => $jobId ),
                    array( '%s', '%s', '%s' ),
                    array( '%d' )
                );
            }
        } else {
            // Insert new job
            $inserted = $db->insert(
                'e_import_jobs',
                array(
                    'competition_code' => $competitionCode,
                    'season_year'      => $seasonYear,
                    'status'           => 'pending',
                    'job_label'        => $jobLabel,
                    'error_message'    => null,
                ),
                array( '%s', '%d', '%s', '%s', '%s' )
            );
            
            if ( $inserted === false ) {
                throw new RuntimeException( 'Failed to create job in e_import_jobs: ' . $db->last_error );
            }
            
            $jobId = (int) $db->insert_id;
        }
        
        // Update job status to running when starting
        $db->query(
            $db->prepare(
                "UPDATE e_import_jobs 
                 SET status = 'running', started_at = NOW() 
                 WHERE id = %d",
                $jobId
            )
        );
        
        // Build ESPN scoreboard URL and fetch data for all date ranges
        $dateRanges = self::build_season_date_ranges( $seasonYear );
        
        $baseContent     = null;
        $allEvents       = array();
        $successfulCalls = 0;
        
        foreach ( $dateRanges as $range ) {
            $dateRange = $range['from'] . '-' . $range['to'];
            $url = "https://site.web.api.espn.com/apis/site/v2/sports/soccer/{$espnLeagueCode}/scoreboard?dates={$dateRange}";
            
            $response = self::fetch_json_from_url( $url );
            
            if ( ! $response['ok'] || $response['http_code'] !== 200 ) {
                fdm_log_datasource_error(
                    'api_error',
                    'ESPN scoreboard API request failed for date range',
                    array(
                        'url'       => $url,
                        'http_code' => $response['http_code'],
                        'error'     => $response['error'],
                        'competition_code' => $competitionCode,
                        'season_year' => $seasonYear,
                    )
                );
                continue;
            }
            
            $content = $response['content'];
            
            if ( ! is_array( $content ) ) {
                continue;
            }
            
            // On first successful response, capture base structure (without events)
            if ( $baseContent === null ) {
                $baseContent = $content;
                if ( isset( $baseContent['events'] ) ) {
                    unset( $baseContent['events'] );
                }
            }
            
            // Merge events from this range
            if ( isset( $content['events'] ) && is_array( $content['events'] ) ) {
                foreach ( $content['events'] as $event ) {
                    $allEvents[] = $event;
                }
            }
            
            $successfulCalls++;
        }
        
        // Check if we got any events
        if ( $successfulCalls === 0 || empty( $allEvents ) ) {
            $msg = "No events found across all date ranges. HTTP calls for scoreboard failed or returned no events.";
            $db->query(
                $db->prepare(
                    "UPDATE e_import_jobs 
                     SET status = 'skipped', finished_at = NOW(), notes = %s 
                     WHERE id = %d",
                    $msg,
                    $jobId
                )
            );
            
            return array(
                'job_id'  => $jobId,
                'status'  => 'skipped',
                'message' => $msg,
            );
        }
        
        // Ensure baseContent exists
        if ( $baseContent === null ) {
            $baseContent = array();
        }
        
        // Attach merged events
        $baseContent['events'] = $allEvents;
        $data = $baseContent;
        $eventCount = count( $allEvents );
        
        // Store raw JSON into e_fixtures_raw with ON DUPLICATE KEY UPDATE
        $providerMatchId = 'season-' . $competitionCode . '-' . $seasonYear . '-job-' . $jobId;
        $rawJson = json_encode( $data, JSON_UNESCAPED_UNICODE );
        
        // Use raw query for ON DUPLICATE KEY UPDATE since WordPress insert doesn't support it directly
        $inserted = $db->query(
            $db->prepare(
                "INSERT INTO e_fixtures_raw
                    (job_id, provider_id, provider_match_id, provider_season_id, competition_code, season_year, raw_json)
                VALUES
                    (%d, %d, %s, %s, %s, %d, %s)
                ON DUPLICATE KEY UPDATE
                    raw_json = VALUES(raw_json),
                    job_id = VALUES(job_id)",
                $jobId,
                1, // ESPN
                $providerMatchId,
                (string) $seasonYear,
                $competitionCode,
                $seasonYear,
                $rawJson
            )
        );
        
        if ( $inserted === false ) {
            $errorMsg = 'Failed to insert raw JSON into e_fixtures_raw: ' . $db->last_error;
            $db->query(
                $db->prepare(
                    "UPDATE e_import_jobs 
                     SET status = 'error', finished_at = NOW(), notes = %s 
                     WHERE id = %d",
                    $errorMsg,
                    $jobId
                )
            );
            throw new RuntimeException( $errorMsg );
        }
        
        // Update job status to ok on successful completion
        $notes = "Imported {$eventCount} events";
        $db->query(
            $db->prepare(
                "UPDATE e_import_jobs 
                 SET status = 'ok', finished_at = NOW(), notes = %s 
                 WHERE id = %d",
                $notes,
                $jobId
            )
        );
        
        return array(
            'job_id'  => $jobId,
            'status'  => 'ok',
            'message' => "Stored raw fixtures JSON for {$competitionCode} {$seasonYear}. Imported {$eventCount} events.",
        );
    }
    
    /**
     * Import league fixtures for a given e_league_code and season_year.
     */
    public static function importLeagueSeasonFixtures( $league_code, $season_year ) {
        $league_code = trim( $league_code );
        $season_year = (int) $season_year;
        
        $league_config = self::getEspnLeagueConfig();
        
        if ( ! isset( $league_config[ $league_code ] ) ) {
            throw new InvalidArgumentException( "Unknown league code: {$league_code}" );
        }
        
        if ( $season_year < 2000 || $season_year > (int) gmdate( 'Y' ) + 1 ) {
            throw new InvalidArgumentException( "Invalid season year: {$season_year}" );
        }
        
        $espnLeagueCode = $league_config[ $league_code ]['espn_league_code'];
        $competition_code = $league_code;
        
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            throw new RuntimeException( 'Database connection not available' );
        }
        
        // Ensure competition exists
        $exists = (int) $db->get_var(
            $db->prepare( "SELECT COUNT(*) FROM competitions WHERE competition_code = %s", $competition_code )
        );
        
        if ( ! $exists ) {
            $insert = $db->query(
                $db->prepare(
                    "INSERT INTO competitions (competition_code, name, country_code, comp_type, level, region, active_flag)
                     VALUES (%s, %s, %s, 'league', %d, 'domestic', 1)",
                    $competition_code,
                    $league_config[ $league_code ]['description'],
                    strtoupper( substr( $league_code, 0, 3 ) ),
                    1
                )
            );
            if ( $insert === false ) {
                throw new RuntimeException( "Failed to insert competition row for {$competition_code}" );
            }
        }
        
        // Season window
        $seasonStart = "{$season_year}-07-01";
        $seasonEnd = ( $season_year + 1 ) . "-07-01";
        
        $current = new DateTime( $seasonStart, new DateTimeZone( 'UTC' ) );
        $end = new DateTime( $seasonEnd, new DateTimeZone( 'UTC' ) );
        
        $insertedEvents = 0;
        
        while ( $current < $end ) {
            $from = $current->format( 'Ymd' );
            $to = clone $current;
            $to->modify( '+6 days' );
            if ( $to > $end ) {
                $to = clone $end;
            }
            $toStr = $to->format( 'Ymd' );
            
            $url = sprintf(
                'https://site.web.api.espn.com/apis/site/v2/sports/soccer/%s/scoreboard?dates=%s-%s',
                rawurlencode( $espnLeagueCode ),
                $from,
                $toStr
            );
            
            $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
            
            if ( is_wp_error( $response ) ) {
                throw new RuntimeException(
                    "HTTP error fetching scoreboard for {$league_code} {$season_year}: " .
                    $response->get_error_message()
                );
            }
            
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code !== 200 ) {
                throw new RuntimeException(
                    "Unexpected HTTP status {$code} fetching scoreboard for {$league_code} {$season_year}"
                );
            }
            
            $body = wp_remote_retrieve_body( $response );
            if ( ! $body ) {
                $current->modify( '+7 days' );
                continue;
            }
            
            $data = json_decode( $body, true );
            if ( ! is_array( $data ) ) {
                $current->modify( '+7 days' );
                continue;
            }
            
            $events_in_chunk = 0;
            if ( ! empty( $data['events'] ) && is_array( $data['events'] ) ) {
                $events_in_chunk = count( $data['events'] );
            }
            
            if ( $events_in_chunk > 0 ) {
                $insert = $db->query(
                    $db->prepare(
                        "INSERT INTO e_fixtures_raw (competition_code, season_year, size, preview, raw_json, created_at)
                         VALUES (%s, %d, %d, %s, %s, NOW())",
                        $competition_code,
                        $season_year,
                        strlen( $body ),
                        substr( $body, 0, 1024 ),
                        $body
                    )
                );
                
                if ( $insert === false ) {
                    throw new RuntimeException(
                        "Failed to insert raw fixtures JSON for {$league_code} {$season_year}"
                    );
                }
                
                $insertedEvents += $events_in_chunk;
            }
            
            $current->modify( '+7 days' );
        }
        
        return array(
            'league_code'     => $league_code,
            'season_year'     => $season_year,
            'inserted_events' => $insertedEvents,
        );
    }
    
    /**
     * Parse raw ESPN fixtures JSON into e_fixtures_events
     *
     * @param string $competition_code Competition code (uefa.champions, uefa.europa, uefa.europa_conf)
     * @param int    $season_year      Season start year (e.g. 2024 for 2024-25)
     * @return array{competition_code:string,season_year:int,inserted:int,updated:int,total:int}
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function syncFixturesFromRawToEvents( $competition_code, $season_year ) {
        $db = fdm_get_footyforums_db();
        if ( ! $db ) {
            throw new RuntimeException( 'Cannot connect to footyforums_data database' );
        }
        
        // Validate competition exists
        $exists = (int) $db->get_var(
            $db->prepare(
                "SELECT COUNT(*) FROM competitions WHERE competition_code = %s",
                $competition_code
            )
        );
        if ( $exists === 0 ) {
            throw new InvalidArgumentException( "Unknown competition code: {$competition_code}" );
        }
        
        // Load latest raw row for competition/season
        $raw_row = $db->get_row(
            $db->prepare(
                "SELECT * FROM e_fixtures_raw WHERE competition_code = %s AND season_year = %d ORDER BY id DESC LIMIT 1",
                $competition_code,
                $season_year
            ),
            ARRAY_A
        );
        
        if ( ! $raw_row ) {
            throw new RuntimeException( "No raw fixtures found for {$competition_code} {$season_year}" );
        }
        
        $raw_json = isset( $raw_row['raw_json'] ) ? $raw_row['raw_json'] : '';
        $decoded  = json_decode( $raw_json, true );
        
        if ( ! is_array( $decoded ) || ! isset( $decoded['events'] ) || ! is_array( $decoded['events'] ) ) {
            throw new RuntimeException( "Invalid or missing events JSON for {$competition_code} {$season_year}" );
        }
        
        $events   = $decoded['events'];
        $inserted = 0;
        $updated  = 0;
        
        foreach ( $events as $event ) {
            $provider_event_id = isset( $event['id'] ) ? (string) $event['id'] : '';
            if ( $provider_event_id === '' ) {
                continue;
            }
            
            $date_raw       = isset( $event['date'] ) ? $event['date'] : null;
            $match_date_utc = null;
            if ( $date_raw ) {
                try {
                    $dt             = new DateTime( $date_raw );
                    $match_date_utc = $dt->format( 'Y-m-d H:i:s' );
                } catch ( Exception $e ) {
                    $match_date_utc = null;
                }
            }
            
            $comp0 = isset( $event['competitions'][0] ) && is_array( $event['competitions'][0] ) ? $event['competitions'][0] : array();
            
            $status_code   = isset( $comp0['status']['type']['name'] ) ? $comp0['status']['type']['name'] : null;
            $status_detail = isset( $comp0['status']['type']['detail'] ) ? $comp0['status']['type']['detail'] : null;
            
            $stage_name = isset( $comp0['type']['text'] ) ? $comp0['type']['text'] : null;
            $group_name = isset( $comp0['group']['name'] ) ? $comp0['group']['name'] : null;
            $round_name = null;
            if ( isset( $comp0['series']['type']['text'] ) ) {
                $round_name = $comp0['series']['type']['text'];
            } elseif ( isset( $comp0['series']['summary'] ) ) {
                $round_name = $comp0['series']['summary'];
            }
            
            $home_team_provider_id = null;
            $home_team_name        = null;
            $home_goals            = null;
            $away_team_provider_id = null;
            $away_team_name        = null;
            $away_goals            = null;
            
            if ( isset( $comp0['competitors'] ) && is_array( $comp0['competitors'] ) ) {
                foreach ( $comp0['competitors'] as $c ) {
                    $id   = isset( $c['id'] ) ? $c['id'] : ( isset( $c['team']['id'] ) ? $c['team']['id'] : null );
                    $name = isset( $c['team']['displayName'] ) ? $c['team']['displayName'] : null;
                    $score_val = null;
                    if ( isset( $c['score'] ) && $c['score'] !== '' ) {
                        $score_val = (int) $c['score'];
                    }
                    
                    if ( isset( $c['homeAway'] ) && $c['homeAway'] === 'home' ) {
                        $home_team_provider_id = $id;
                        $home_team_name        = $name;
                        $home_goals            = $score_val;
                    } else {
                        $away_team_provider_id = $id;
                        $away_team_name        = $name;
                        $away_goals            = $score_val;
                    }
                }
            }
            
            $season_slug = '';
            if ( isset( $event['season']['slug'] ) && is_string( $event['season']['slug'] ) ) {
                $season_slug = $event['season']['slug'];
            }
            $stage_type = ( strpos( $season_slug, 'qualifying' ) === 0 ) ? 'qualifying' : 'main';
            
            list( $stage_round, $stage_round_sort ) = self::map_stage_round( $competition_code, $stage_type, $round_name );
            
            $raw_json_event = json_encode( $event, JSON_UNESCAPED_UNICODE );
            
            $result = $db->query(
                $db->prepare(
                    "INSERT INTO e_fixtures_events
                    (provider_id, provider_event_id, provider_season_id, competition_code, season_year, match_date_utc, status_code, status_detail, stage_name, group_name, round_name, home_team_provider_id, home_team_name, away_team_provider_id, away_team_name, home_goals, away_goals, raw_json, raw_row_id, stage_type, stage_round, stage_round_sort)
                    VALUES
                    (%d, %s, %s, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d, %s, %d, %s, %s, %d)
                    ON DUPLICATE KEY UPDATE
                        match_date_utc        = VALUES(match_date_utc),
                        status_code           = VALUES(status_code),
                        status_detail         = VALUES(status_detail),
                        stage_name            = VALUES(stage_name),
                        group_name            = VALUES(group_name),
                        round_name            = VALUES(round_name),
                        home_team_provider_id = VALUES(home_team_provider_id),
                        home_team_name        = VALUES(home_team_name),
                        away_team_provider_id = VALUES(away_team_provider_id),
                        away_team_name        = VALUES(away_team_name),
                        home_goals            = VALUES(home_goals),
                        away_goals            = VALUES(away_goals),
                        raw_json              = VALUES(raw_json),
                        raw_row_id            = VALUES(raw_row_id),
                        stage_type            = VALUES(stage_type),
                        stage_round           = VALUES(stage_round),
                        stage_round_sort      = VALUES(stage_round_sort)",
                    1, // provider_id ESPN
                    $provider_event_id,
                    (string) $season_year,
                    $competition_code,
                    $season_year,
                    $match_date_utc,
                    $status_code,
                    $status_detail,
                    $stage_name,
                    $group_name,
                    $round_name,
                    $home_team_provider_id,
                    $home_team_name,
                    $away_team_provider_id,
                    $away_team_name,
                    $home_goals,
                    $away_goals,
                    $raw_json_event,
                    (int) $raw_row['id'],
                    $stage_type,
                    $stage_round,
                    $stage_round_sort
                )
            );
            
            if ( $result === false ) {
                throw new RuntimeException( 'Failed to upsert fixture event: ' . $db->last_error );
            }
            
            // MySQL returns 1 for insert, 2 for update in ON DUPLICATE KEY UPDATE
            $affected = isset( $db->rows_affected ) ? (int) $db->rows_affected : 0;
            if ( $affected === 1 ) {
                $inserted++;
            } elseif ( $affected === 2 ) {
                $updated++;
            } else {
                // treat as update if ambiguous
                $updated++;
            }
        }
        
        return array(
            'competition_code' => $competition_code,
            'season_year'      => (int) $season_year,
            'inserted'         => $inserted,
            'updated'          => $updated,
            'total'            => $inserted + $updated,
        );
    }
}

/**
 * Standalone function wrapper for WP-CLI compatibility
 * 
 * @param string $league_code E league code (e.g., 'eng.1')
 * @param int $season_year Season year (e.g., 2024)
 * @return array|WP_Error Array with 'team_rows' count on success, or WP_Error on failure
 */
function fdm_e_sync_season_stats( $league_code, $season_year ) {
    return FDM_E_Datasource_V2::e_datasource_sync_season_stats( $league_code, $season_year );
}

/**
 * Standalone function wrapper for ESPN competition import
 * Can be called from CLI or other entry points
 * 
 * @param string $competition_code Competition code (e.g., 'uefa.champions', 'uefa.europa', 'uefa.europa_conf')
 * @param int $season_year Season start year (e.g., 2024 for 2024-25)
 * @return array Result array
 * @throws InvalidArgumentException|RuntimeException
 */
function fdm_import_espn_competition_season( $competition_code, $season_year ) {
    return FDM_E_Datasource_V2::importEspnCompetitionSeason( $competition_code, $season_year );
}

/**
 * CLI entry point for ESPN competition import
 * Usage: php e_datasource_v2.php uefa.champions 2024
 *        php e_datasource_v2.php uefa.europa 2024
 *        php e_datasource_v2.php uefa.europa_conf 2024
 * 
 * This function is only executed when the file is run directly from CLI
 */
if ( php_sapi_name() === 'cli' && basename( __FILE__ ) === basename( $_SERVER['argv'][0] ) ) {
    // Load WordPress if not already loaded (for database access via fdm_get_footyforums_db)
    if ( ! defined( 'ABSPATH' ) ) {
        // Try to find WordPress
        $wp_load_paths = array(
            dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php',
            dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php',
        );
        
        foreach ( $wp_load_paths as $wp_load ) {
            if ( file_exists( $wp_load ) ) {
                require_once $wp_load;
                break;
            }
        }
        
        if ( ! defined( 'ABSPATH' ) ) {
            fwrite( STDERR, "Error: WordPress not found. Cannot load database connection.\n" );
            exit( 1 );
        }
    }
    
    if ( $argc < 3 ) {
        fwrite( STDERR, "Usage: php e_datasource_v2.php <competition_code> <season_year>\n" );
        exit( 1 );
    }
    
    $competitionCode = $argv[1];
    $seasonYear      = (int) $argv[2];
    
    try {
        // Use the static method which internally uses WordPress database helpers
        $result = FDM_E_Datasource_V2::importEspnCompetitionSeason( $competitionCode, $seasonYear );
        
        fwrite( STDOUT, "Import completed for {$competitionCode} {$seasonYear}\n" );
        fwrite( STDOUT, "Job ID: " . $result['job_id'] . "\n" );
        fwrite( STDOUT, "Status: " . $result['status'] . "\n" );
        fwrite( STDOUT, "Message: " . $result['message'] . "\n" );
        
    } catch ( Throwable $e ) {
        fwrite( STDERR, "Error: " . $e->getMessage() . "\n" );
        exit( 1 );
    }
}