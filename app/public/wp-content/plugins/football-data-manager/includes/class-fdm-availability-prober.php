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
     * Manual verification handler
     *
     * @var FDM_Manual_Verification|null
     */
    private $verification;

    /**
     * Current league name being processed (for verification logging)
     *
     * @var string
     */
    private $current_league_name = '';

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

        // Initialize manual verification handler
        $verification_path = dirname( __FILE__ ) . '/class-fdm-manual-verification.php';
        if ( file_exists( $verification_path ) ) {
            require_once $verification_path;
            $this->verification = new FDM_Manual_Verification();
            $this->verification->create_table();
        }
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
     * Progress file path for admin UI tracking
     */
    const PROGRESS_FILE = '/tmp/probe-progress.json';

    /**
     * Run full availability probe for all leagues and years
     */
    public function run() {
        if ( ! $this->e_db ) {
            $this->log( "Cannot run - no database connection", 'error' );
            return;
        }

        // Clear log file at start of new run
        $log_file = '/tmp/probe-availability.log';
        file_put_contents( $log_file, '' );

        // Initialize progress tracking
        $this->update_progress( 0, 0, 'starting' );

        $this->log( "Starting ESPN availability probe..." );

        // Fetch all leagues from ESPN
        $this->fetch_leagues();

        if ( empty( $this->leagues ) ) {
            $this->log( "No leagues found - aborting", 'error' );
            $this->update_progress( 0, 0, 'error' );
            return;
        }

        $this->log( sprintf( "Found %d leagues (filtered)", count( $this->leagues ) ) );

        // Probe each league for years 2001-2025
        $this->probe_all_leagues();

        $this->update_progress( count( $this->leagues ), count( $this->leagues ), 'complete' );
        $this->log( "ESPN availability probe complete!" );
    }

    /**
     * Update progress file for admin UI tracking
     *
     * @param int    $current Current league index
     * @param int    $total   Total leagues to probe
     * @param string $status  Status: starting, running, complete, error
     */
    private function update_progress( $current, $total, $status = 'running' ) {
        $data = [
            'current'    => $current,
            'total'      => $total,
            'status'     => $status,
            'updated_at' => time(),
        ];
        file_put_contents( self::PROGRESS_FILE, json_encode( $data ), LOCK_EX );
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
        $processed = 0;

        $this->log( sprintf( "Found %d league references, dereferencing...", $total ) );

        foreach ( $response['items'] as $item ) {
            $processed++;

            // Log progress every 25 leagues
            if ( $processed % 25 === 0 ) {
                $this->log( sprintf( "  Fetching league info: %d/%d", $processed, $total ) );
            }

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
            // Update progress for admin UI
            $this->update_progress( $index, $total_leagues, 'running' );

            $this->log( sprintf(
                "\n[%d/%d] Probing %s (%s)",
                $index + 1,
                $total_leagues,
                $league['name'],
                $league['code']
            ) );

            // Store current league name for verification logging
            $this->current_league_name = $league['name'];

            for ( $year = $start_year; $year <= $end_year; $year++ ) {
                $probed++;

                if ( $probed % 100 === 0 ) {
                    $this->log( sprintf( "Progress: %d/%d probes (%.1f%%)", $probed, $total_probes, ( $probed / $total_probes ) * 100 ) );
                }

                $availability = $this->probe_league( $league['code'], $year );

                if ( $availability && $availability['fixtures_available'] > 0 ) {
                    $this->save_availability( $availability );

                    // Log missing data types for manual verification
                    $this->log_missing_for_verification( $league['code'], $league['name'], $year, $availability );

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

        // Probe teams for this league
        $teams_count = $this->probe_teams( $league_code );

        // Probe standings for this league/year
        $standings_available = $this->probe_standings( $league_code, $year );

        // Probe players (estimate from roster data)
        $players_count = $this->probe_players( $league_code, $year );

        // Probe transfers
        $transfers_count = $this->probe_transfers( $league_code, $year );

        // Probe season stats (leaders endpoint)
        $season_stats_available = $this->probe_season_stats( $league_code, $year );

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
            'roster_available'       => $availability['lineups'] ? 1 : 0,
            'teams_available'        => $teams_count,
            'players_available'      => $players_count,
            'standings_available'    => $standings_available ? 1 : 0,
            'transfers_available'    => $transfers_count,
            'season_stats_available' => $season_stats_available ? 1 : 0,
            'venues_available'       => $availability['venues'] ? 1 : 0,
            'sample_event_id'        => ! empty( $sample_event_ids ) ? $sample_event_ids[0] : null,
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
            'venues'       => false,
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
            // Note: rosters array may exist with just team info but no actual player lineups
            // We must check for actual player data in the roster sub-array
            if ( ! empty( $summary['rosters'] ) ) {
                foreach ( $summary['rosters'] as $team_roster ) {
                    // Only set lineups=true if there's actual player data
                    if ( ! empty( $team_roster['roster'] ) && is_array( $team_roster['roster'] ) ) {
                        $availability['lineups'] = true;

                        // Check for player stats within rosters
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

            // Check plays endpoint for this match
            if ( ! $availability['plays'] ) {
                $plays_url = "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/events/{$event_id}/competitions/{$event_id}/plays?limit=1";
                $plays_response = $this->fetch_json( $plays_url );
                if ( $plays_response && ! empty( $plays_response['items'] ) ) {
                    $availability['plays'] = true;
                }
                usleep( $this->rate_limit_delay );
            }

            // Check for venue data in the summary
            if ( ! $availability['venues'] ) {
                if ( ! empty( $summary['gameInfo']['venue']['id'] ) ) {
                    $availability['venues'] = true;
                }
            }

            // If we found all data types, no need to check more matches
            if ( $availability['lineups'] && $availability['commentary'] &&
                 $availability['key_events'] && $availability['team_stats'] &&
                 $availability['plays'] && $availability['venues'] ) {
                break;
            }

            usleep( $this->rate_limit_delay );
        }

        return $availability;
    }

    /**
     * Probe teams endpoint for a league
     *
     * @param string $league_code ESPN league code
     * @return int Number of teams found
     */
    private function probe_teams( $league_code ) {
        $teams_url = "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/teams?limit=100";
        $response = $this->fetch_json( $teams_url );
        usleep( $this->rate_limit_delay );

        if ( $response && isset( $response['count'] ) ) {
            return (int) $response['count'];
        }

        return 0;
    }

    /**
     * Probe standings endpoint for a league/year
     *
     * @param string $league_code ESPN league code
     * @param int    $year        Season year
     * @return bool Whether standings are available
     */
    private function probe_standings( $league_code, $year ) {
        // Try common group IDs (1 is most common for league standings)
        $standings_url = "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/seasons/{$year}/types/1/groups/1/standings";
        $response = $this->fetch_json( $standings_url );
        usleep( $this->rate_limit_delay );

        if ( $response && ! empty( $response['standings'] ) ) {
            return true;
        }

        // Try without group specification
        $standings_url2 = "https://site.api.espn.com/apis/v2/sports/soccer/{$league_code}/standings?season={$year}";
        $response2 = $this->fetch_json( $standings_url2 );
        usleep( $this->rate_limit_delay );

        if ( $response2 && ! empty( $response2['children'] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Probe players/roster data for a league/year
     *
     * @param string $league_code ESPN league code
     * @param int    $year        Season year
     * @return int Estimated player count (teams * ~25 players avg)
     */
    private function probe_players( $league_code, $year ) {
        // Get team count and estimate players (avg 25 per team)
        $teams_url = "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/seasons/{$year}/teams?limit=100";
        $response = $this->fetch_json( $teams_url );
        usleep( $this->rate_limit_delay );

        if ( $response && isset( $response['count'] ) ) {
            // Estimate ~25 players per team roster
            return (int) $response['count'] * 25;
        }

        return 0;
    }

    /**
     * Probe transfers endpoint for a league/year
     *
     * @param string $league_code ESPN league code
     * @param int    $year        Season year
     * @return int Number of transfers found
     */
    private function probe_transfers( $league_code, $year ) {
        $url = "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/transactions?season={$year}";
        $response = $this->fetch_json( $url );
        usleep( $this->rate_limit_delay );

        if ( $response && ! empty( $response['transactions'] ) ) {
            return count( $response['transactions'] );
        }

        return 0;
    }

    /**
     * Probe season stats (leaders) endpoint for a league/year
     *
     * @param string $league_code ESPN league code
     * @param int    $year        Season year
     * @return bool Whether season stats are available
     */
    private function probe_season_stats( $league_code, $year ) {
        $url = "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/seasons/{$year}/types/1/leaders";
        $response = $this->fetch_json( $url );
        usleep( $this->rate_limit_delay );

        if ( $response && ! empty( $response['categories'] ) ) {
            return true;
        }

        return false;
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
             teams_available, players_available, standings_available, transfers_available,
             season_stats_available, venues_available,
             verified_at, verified_method)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'api_probe')
            ON DUPLICATE KEY UPDATE
                fixtures_available = VALUES(fixtures_available),
                lineups_available = VALUES(lineups_available),
                commentary_available = VALUES(commentary_available),
                key_events_available = VALUES(key_events_available),
                roster_available = VALUES(roster_available),
                team_stats_available = VALUES(team_stats_available),
                player_stats_available = VALUES(player_stats_available),
                plays_available = VALUES(plays_available),
                teams_available = VALUES(teams_available),
                players_available = VALUES(players_available),
                standings_available = VALUES(standings_available),
                transfers_available = VALUES(transfers_available),
                season_stats_available = VALUES(season_stats_available),
                venues_available = VALUES(venues_available),
                verified_at = NOW(),
                verified_method = 'api_probe'";

        $stmt = $this->e_db->prepare( $sql );

        if ( ! $stmt ) {
            $this->log( "Failed to prepare statement: " . $this->e_db->error, 'error' );
            return;
        }

        $stmt->bind_param(
            'siiiiiiiiiiiiiii',
            $data['league_code'],
            $data['season_year'],
            $data['fixtures_available'],
            $data['lineups_available'],
            $data['commentary_available'],
            $data['key_events_available'],
            $data['roster_available'],
            $data['team_stats_available'],
            $data['player_stats_available'],
            $data['plays_available'],
            $data['teams_available'],
            $data['players_available'],
            $data['standings_available'],
            $data['transfers_available'],
            $data['season_stats_available'],
            $data['venues_available']
        );

        if ( ! $stmt->execute() ) {
            $this->log( "Failed to save availability: " . $stmt->error, 'error' );
        }

        $stmt->close();
    }

    /**
     * Log missing data types for manual verification
     *
     * @param string $league_code ESPN league code
     * @param string $league_name Human-readable league name
     * @param int    $year        Season year
     * @param array  $availability Availability data from probe
     */
    private function log_missing_for_verification( $league_code, $league_name, $year, $availability ) {
        if ( ! $this->verification ) {
            return;
        }

        // Only log if there are fixtures but missing data types
        if ( $availability['fixtures_available'] <= 0 ) {
            return;
        }

        // Get sample event ID for match-level URLs
        $event_id = isset( $availability['sample_event_id'] ) ? $availability['sample_event_id'] : 'NO_EVENT_ID';

        // Check each data type and log URLs for missing ones
        $data_type_urls = [
            'plays' => [
                'check' => 'plays_available',
                'url' => "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/events/{$event_id}/competitions/{$event_id}/plays",
            ],
            'teams' => [
                'check' => 'teams_available',
                'url' => "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/teams?limit=100",
            ],
            'standings' => [
                'check' => 'standings_available',
                'url' => "https://site.api.espn.com/apis/v2/sports/soccer/{$league_code}/standings?season={$year}",
            ],
            'lineups' => [
                'check' => 'lineups_available',
                'url' => "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/summary?event={$event_id}",
            ],
            'commentary' => [
                'check' => 'commentary_available',
                'url' => "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/summary?event={$event_id}",
            ],
            'key_events' => [
                'check' => 'key_events_available',
                'url' => "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/summary?event={$event_id}",
            ],
            'team_stats' => [
                'check' => 'team_stats_available',
                'url' => "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/summary?event={$event_id}",
            ],
            'player_stats' => [
                'check' => 'player_stats_available',
                'url' => "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/summary?event={$event_id}",
            ],
            'transfers' => [
                'check' => 'transfers_available',
                'url' => "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/transactions?season={$year}",
            ],
        ];

        // ESPN website URL for match (if we have an event ID)
        $site_url = $event_id !== 'NO_EVENT_ID' ? "https://www.espn.com/soccer/match/_/gameId/{$event_id}" : null;

        foreach ( $data_type_urls as $data_type => $config ) {
            $check_key = $config['check'];
            if ( isset( $availability[ $check_key ] ) && ! $availability[ $check_key ] ) {
                $this->verification->log_for_verification(
                    $league_code,
                    $league_name,
                    $year,
                    $data_type,
                    $config['url'],
                    $site_url
                );
            }
        }
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
     * Log message to CLI, error log, and progress file for admin UI tracking
     *
     * @param string $message Message to log
     * @param string $level   Log level (info, error, warning)
     */
    private function log( $message, $level = 'info' ) {
        $prefix = '[FDM Prober]';
        $timestamp = date( 'Y-m-d H:i:s' );
        $formatted = sprintf( "[%s] %s %s\n", $timestamp, $prefix, $message );

        // Always write to progress file for admin UI tracking
        $log_file = '/tmp/probe-availability.log';
        file_put_contents( $log_file, $formatted, FILE_APPEND | LOCK_EX );

        if ( php_sapi_name() === 'cli' ) {
            echo $formatted;
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
