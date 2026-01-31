<?php
/**
 * Class FDM_Availability_Prober
 *
 * Probes ESPN API to discover what data is available for each league/year combination.
 * Populates espn_availability table for progress tracking during scraping.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class FDM_Availability_Prober {

    /**
     * Database connection to e_db
     *
     * @var mysqli|null
     */
    private $e_db;

    /**
     * Discovered leagues from ESPN API
     *
     * @var array
     */
    private $leagues = [];

    /**
     * Rate limiting delay in microseconds (200ms = 200000)
     *
     * @var int
     */
    private $rate_limit_delay = 200000;

    /**
     * Patterns to filter out women's leagues
     *
     * @var array
     */
    private $womens_league_patterns = [
        '/women/i',
        '/w-league/i',
        '/nwsl/i',
        '/wwsl/i',
        '/feminine/i',
        '/femenina/i',
        '/frauen/i',
        '/femminile/i',
        '/feminino/i',
        '/damer/i',
        '/kvinner/i',
        '/damallsvenskan/i',
        '/toppserien/i',
    ];

    /**
     * ESPN API base URLs
     */
    const LEAGUES_URL = 'https://sports.core.api.espn.com/v2/sports/soccer/leagues?limit=500';
    const SEASON_TYPES_URL = 'https://sports.core.api.espn.com/v2/sports/soccer/leagues/%s/seasons/%d/types';
    const EVENTS_URL = 'https://sports.core.api.espn.com/v2/sports/soccer/leagues/%s/seasons/%d/types/%d/events?page=1';

    /**
     * Constructor - establishes database connection
     */
    public function __construct() {
        $this->e_db = $this->connect_db();
    }

    /**
     * Connect to e_db database
     *
     * @return mysqli|null
     */
    private function connect_db() {
        // Try my.cnf first
        $cnf_paths = [
            dirname( dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) ) ) . '/app/tools/transitional/my.cnf',
            ABSPATH . '../tools/transitional/my.cnf',
            '/Users/kevincasey/Local Sites/footyforums/app/tools/transitional/my.cnf',
        ];

        $socket = null;
        $user = 'root';
        $pass = 'root';

        foreach ( $cnf_paths as $cnf_file ) {
            if ( file_exists( $cnf_file ) ) {
                $ini = parse_ini_file( $cnf_file, false, INI_SCANNER_RAW );
                $user = isset( $ini['user'] ) ? $ini['user'] : 'root';
                $pass = isset( $ini['password'] ) ? $ini['password'] : 'root';
                $socket = isset( $ini['socket'] ) ? $ini['socket'] : null;
                break;
            }
        }

        // Fallback socket path for Local by Flywheel
        if ( ! $socket ) {
            $socket = '/Users/kevincasey/Library/Application Support/Local/run/0WeAfCFCz/mysql/mysqld.sock';
        }

        $mysqli = new mysqli( 'localhost', $user, $pass, 'e_db', 0, $socket );
        if ( $mysqli->connect_error ) {
            $this->log( "Database connection failed: " . $mysqli->connect_error, 'error' );
            return null;
        }

        return $mysqli;
    }

    /**
     * Run full availability probe for all leagues and years
     */
    public function run() {
        if ( ! $this->e_db ) {
            $this->log( "Cannot run - no database connection", 'error' );
            return;
        }

        $this->log( "Starting ESPN availability probe..." );

        // Fetch all leagues from ESPN
        $this->fetch_leagues();

        if ( empty( $this->leagues ) ) {
            $this->log( "No leagues found - aborting", 'error' );
            return;
        }

        $this->log( sprintf( "Found %d leagues (filtered)", count( $this->leagues ) ) );

        // Probe each league for years 2001-2025
        $this->probe_all_leagues();

        $this->log( "ESPN availability probe complete!" );
    }

    /**
     * Probe a single league/year combination
     *
     * @param string $league_code ESPN league code (e.g., 'eng.1')
     * @param int    $year        Season year
     * @return array|null Availability data or null on failure
     */
    public function probe_single( $league_code, $year ) {
        if ( ! $this->e_db ) {
            $this->log( "Cannot probe - no database connection", 'error' );
            return null;
        }

        $this->log( sprintf( "Probing %s for %d...", $league_code, $year ) );

        $availability = $this->probe_league( $league_code, $year );

        if ( $availability ) {
            $this->save_availability( $availability );
            $this->log( sprintf(
                "  -> %d fixtures, lineups=%d, commentary=%d, key_events=%d",
                $availability['fixtures_available'],
                $availability['lineups_available'],
                $availability['commentary_available'],
                $availability['key_events_available']
            ) );
        } else {
            $this->log( sprintf( "  -> No data available for %s %d", $league_code, $year ) );
        }

        return $availability;
    }

    /**
     * Fetch all leagues from ESPN discovery endpoint
     */
    private function fetch_leagues() {
        $this->log( "Fetching leagues from ESPN..." );

        $response = $this->fetch_json( self::LEAGUES_URL );
        if ( ! $response || empty( $response['items'] ) ) {
            $this->log( "Failed to fetch leagues", 'error' );
            return;
        }

        $total = count( $response['items'] );
        $filtered = 0;

        foreach ( $response['items'] as $item ) {
            // Dereference league info
            if ( ! isset( $item['$ref'] ) ) {
                continue;
            }

            $league_data = $this->fetch_json( $item['$ref'] );
            if ( ! $league_data || empty( $league_data['slug'] ) ) {
                continue;
            }

            $league_code = $league_data['slug'];
            $league_name = isset( $league_data['name'] ) ? $league_data['name'] : $league_code;

            // Filter out women's leagues
            if ( $this->is_womens_league( $league_name, $league_code ) ) {
                $filtered++;
                continue;
            }

            $this->leagues[] = [
                'code' => $league_code,
                'name' => $league_name,
            ];

            usleep( $this->rate_limit_delay );
        }

        $this->log( sprintf( "Fetched %d leagues, filtered %d women's leagues", count( $this->leagues ), $filtered ) );
    }

    /**
     * Check if a league is a women's league
     *
     * @param string $name League name
     * @param string $code League code
     * @return bool
     */
    private function is_womens_league( $name, $code ) {
        // Check name patterns
        foreach ( $this->womens_league_patterns as $pattern ) {
            if ( preg_match( $pattern, $name ) ) {
                return true;
            }
        }

        // Check code patterns (e.g., .w or _women)
        if ( preg_match( '/\.w$/i', $code ) || preg_match( '/_women/i', $code ) ) {
            return true;
        }

        return false;
    }

    /**
     * Probe all leagues for years 2001-2025
     */
    private function probe_all_leagues() {
        $total_leagues = count( $this->leagues );
        $start_year = 2001;
        $end_year = 2025;
        $total_probes = $total_leagues * ( $end_year - $start_year + 1 );
        $probed = 0;

        foreach ( $this->leagues as $index => $league ) {
            $this->log( sprintf(
                "\n[%d/%d] Probing %s (%s)",
                $index + 1,
                $total_leagues,
                $league['name'],
                $league['code']
            ) );

            for ( $year = $start_year; $year <= $end_year; $year++ ) {
                $probed++;

                if ( $probed % 100 === 0 ) {
                    $this->log( sprintf( "Progress: %d/%d probes (%.1f%%)", $probed, $total_probes, ( $probed / $total_probes ) * 100 ) );
                }

                $availability = $this->probe_league( $league['code'], $year );

                if ( $availability && $availability['fixtures_available'] > 0 ) {
                    $this->save_availability( $availability );
                    $this->log( sprintf(
                        "  %d: %d fixtures",
                        $year,
                        $availability['fixtures_available']
                    ) );
                }

                usleep( $this->rate_limit_delay );
            }
        }
    }

    /**
     * Probe a single league/year for availability
     *
     * @param string $league_code ESPN league code
     * @param int    $year        Season year
     * @return array|null Availability data or null
     */
    private function probe_league( $league_code, $year ) {
        // Get season types for this league/year
        $types_url = sprintf( self::SEASON_TYPES_URL, $league_code, $year );
        $types_response = $this->fetch_json( $types_url );

        if ( ! $types_response || empty( $types_response['items'] ) ) {
            return null;
        }

        $total_fixtures = 0;
        $sample_event_ids = [];

        // Sum events across all season types and collect sample event IDs
        foreach ( $types_response['items'] as $type_item ) {
            if ( ! isset( $type_item['$ref'] ) ) {
                continue;
            }

            if ( preg_match( '/types\/(\d+)/', $type_item['$ref'], $matches ) ) {
                $type_id = (int) $matches[1];
                $events_url = sprintf( self::EVENTS_URL, $league_code, $year, $type_id );

                $events_response = $this->fetch_json( $events_url );

                if ( $events_response && isset( $events_response['count'] ) ) {
                    $total_fixtures += (int) $events_response['count'];

                    // Collect sample event IDs from first page (up to 3 per type)
                    if ( ! empty( $events_response['items'] ) && count( $sample_event_ids ) < 5 ) {
                        foreach ( array_slice( $events_response['items'], 0, 3 ) as $event_item ) {
                            if ( isset( $event_item['$ref'] ) && preg_match( '/events\/(\d+)/', $event_item['$ref'], $m ) ) {
                                $sample_event_ids[] = $m[1];
                                if ( count( $sample_event_ids ) >= 5 ) break;
                            }
                        }
                    }
                }

                usleep( $this->rate_limit_delay );
            }
        }

        if ( $total_fixtures === 0 ) {
            return null;
        }

        // Actually probe sample matches to determine deep data availability
        $availability = $this->probe_deep_data_availability( $league_code, $sample_event_ids );

        return [
            'league_code'            => $league_code,
            'season_year'            => $year,
            'fixtures_available'     => $total_fixtures,
            'lineups_available'      => $availability['lineups'] ? 1 : 0,
            'commentary_available'   => $availability['commentary'] ? 1 : 0,
            'key_events_available'   => $availability['key_events'] ? 1 : 0,
            'team_stats_available'   => $availability['team_stats'] ? 1 : 0,
            'player_stats_available' => $availability['player_stats'] ? 1 : 0,
            'plays_available'        => $availability['plays'] ? 1 : 0,
            'roster_available'       => $availability['lineups'] ? 1 : 0, // Same as lineups
        ];
    }

    /**
     * Probe sample matches to check what deep data is available
     *
     * @param string $league_code ESPN league code
     * @param array  $event_ids   Sample event IDs to check
     * @return array Availability flags for each data type
     */
    private function probe_deep_data_availability( $league_code, $event_ids ) {
        $availability = [
            'lineups'      => false,
            'commentary'   => false,
            'key_events'   => false,
            'team_stats'   => false,
            'player_stats' => false,
            'plays'        => false,
        ];

        if ( empty( $event_ids ) ) {
            return $availability;
        }

        // Check up to 3 sample matches
        $checked = 0;
        foreach ( array_slice( $event_ids, 0, 3 ) as $event_id ) {
            $summary_url = "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/summary?event={$event_id}";
            $summary = $this->fetch_json( $summary_url );

            if ( ! $summary ) {
                usleep( $this->rate_limit_delay );
                continue;
            }

            $checked++;

            // Check each data type
            if ( ! empty( $summary['rosters'] ) ) {
                $availability['lineups'] = true;

                // Check for player stats within rosters
                foreach ( $summary['rosters'] as $team_roster ) {
                    if ( ! empty( $team_roster['roster'] ) ) {
                        foreach ( $team_roster['roster'] as $player ) {
                            if ( ! empty( $player['stats'] ) ) {
                                $availability['player_stats'] = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            if ( ! empty( $summary['commentary'] ) ) {
                $availability['commentary'] = true;
            }

            if ( ! empty( $summary['keyEvents'] ) ) {
                $availability['key_events'] = true;
            }

            if ( ! empty( $summary['boxscore']['teams'] ) ) {
                foreach ( $summary['boxscore']['teams'] as $team ) {
                    if ( ! empty( $team['statistics'] ) ) {
                        $availability['team_stats'] = true;
                        break;
                    }
                }
            }

            // If we found all data types, no need to check more matches
            if ( $availability['lineups'] && $availability['commentary'] &&
                 $availability['key_events'] && $availability['team_stats'] ) {
                break;
            }

            usleep( $this->rate_limit_delay );
        }

        return $availability;
    }

    /**
     * Save availability data to espn_availability table
     *
     * @param array $data Availability data
     */
    private function save_availability( $data ) {
        if ( ! $this->e_db ) {
            return;
        }

        $sql = "INSERT INTO espn_availability
            (league_code, season_year, fixtures_available, lineups_available,
             commentary_available, key_events_available, roster_available,
             team_stats_available, player_stats_available, plays_available,
             verified_at, verified_method)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'api_probe')
            ON DUPLICATE KEY UPDATE
                fixtures_available = VALUES(fixtures_available),
                lineups_available = VALUES(lineups_available),
                commentary_available = VALUES(commentary_available),
                key_events_available = VALUES(key_events_available),
                roster_available = VALUES(roster_available),
                team_stats_available = VALUES(team_stats_available),
                player_stats_available = VALUES(player_stats_available),
                plays_available = VALUES(plays_available),
                verified_at = NOW(),
                verified_method = 'api_probe'";

        $stmt = $this->e_db->prepare( $sql );

        if ( ! $stmt ) {
            $this->log( "Failed to prepare statement: " . $this->e_db->error, 'error' );
            return;
        }

        $stmt->bind_param(
            'siiiiiiiii',
            $data['league_code'],
            $data['season_year'],
            $data['fixtures_available'],
            $data['lineups_available'],
            $data['commentary_available'],
            $data['key_events_available'],
            $data['roster_available'],
            $data['team_stats_available'],
            $data['player_stats_available'],
            $data['plays_available']
        );

        if ( ! $stmt->execute() ) {
            $this->log( "Failed to save availability: " . $stmt->error, 'error' );
        }

        $stmt->close();
    }

    /**
     * Fetch JSON from URL with error handling
     *
     * @param string $url URL to fetch
     * @return array|null Decoded JSON or null
     */
    private function fetch_json( $url ) {
        // Use WordPress HTTP API if available
        if ( function_exists( 'wp_remote_get' ) ) {
            $response = wp_remote_get( $url, [
                'timeout'   => 15,
                'sslverify' => false,
            ] );

            if ( is_wp_error( $response ) ) {
                return null;
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( $code !== 200 ) {
                return null;
            }

            $body = wp_remote_retrieve_body( $response );
            return json_decode( $body, true );
        }

        // Fallback to cURL
        $ch = curl_init();
        curl_setopt_array( $ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) FootyForums/1.0',
        ] );

        $body = curl_exec( $ch );
        $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( $code !== 200 || ! $body ) {
            return null;
        }

        return json_decode( $body, true );
    }

    /**
     * Log message to CLI or error log
     *
     * @param string $message Message to log
     * @param string $level   Log level (info, error, warning)
     */
    private function log( $message, $level = 'info' ) {
        $prefix = '[FDM Prober]';

        if ( php_sapi_name() === 'cli' ) {
            $timestamp = date( 'Y-m-d H:i:s' );
            echo sprintf( "[%s] %s %s\n", $timestamp, $prefix, $message );
        } else {
            error_log( sprintf( "%s %s", $prefix, $message ) );
        }
    }

    /**
     * Get list of discovered leagues
     *
     * @return array
     */
    public function get_leagues() {
        if ( empty( $this->leagues ) ) {
            $this->fetch_leagues();
        }
        return $this->leagues;
    }
}
