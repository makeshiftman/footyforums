<?php
/**
 * Class FDM_E_Master_Datasource
 * 
 * The "Platinum" Engine for backfilling 25 years of E data.
 * Handles Fixtures, Deep Data (Lineups/Commentary), and Platinum Data (Transfers/Season Stats).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class FDM_E_Master_Datasource {

    private $db;

    public function __construct() {
        $this->db = $this->connect_db();
    }

    /**
     * Connect to e_db using my.cnf credentials
     */
    private function connect_db() {
        $cnf_file = dirname( dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) ) ) . '/app/tools/transitional/my.cnf';
        
        if ( ! file_exists( $cnf_file ) ) {
             $cnf_file = ABSPATH . '../tools/transitional/my.cnf';
             if ( ! file_exists( $cnf_file ) ) {
                 $cnf_file = '/Users/kevincasey/Local Sites/footyforums/app/tools/transitional/my.cnf';
             }
        }

        if ( file_exists( $cnf_file ) ) {
            $ini = parse_ini_file( $cnf_file, false, INI_SCANNER_RAW );
            $user = isset( $ini['user'] ) ? $ini['user'] : 'root';
            $pass = isset( $ini['password'] ) ? $ini['password'] : 'root';
            $host = 'localhost'; 
            $socket = isset( $ini['socket'] ) ? $ini['socket'] : null;

            $mysqli = new mysqli( $host, $user, $pass, 'e_db', 0, $socket );
            if ( $mysqli->connect_error ) {
                error_log( "FDM_E_Master_Datasource: Connection failed: " . $mysqli->connect_error );
                return null;
            }
            return $mysqli;
        }
        
        return null;
    }

    /**
     * Get the 218 Target Leagues (Based on 2024 "Golden Year" Coverage)
     */
    public function get_platinum_competitions() {
        if ( ! $this->db ) return [];

        $sql = "SELECT league_code FROM league_permissions WHERE is_active = 1 ORDER BY league_code ASC";
        $result = $this->db->query( $sql );
        
        $leagues = [];
        if ( $result ) {
            while ( $row = $result->fetch_assoc() ) {
                $leagues[] = $row['league_code'];
            }
        }
        return $leagues;
    }

    /**
     * Assess the "Health" of a specific Season for a League
     */
    public function get_season_health( $league_code, $season ) {
        if ( ! $this->db ) return ['status' => 'ERROR', 'details' => []];

        // 1. Fixture Count
        $stmt = $this->db->prepare( "SELECT COUNT(*) as count FROM fixtures WHERE league_code = ? AND season = ?" );
        $stmt->bind_param( "si", $league_code, $season );
        $stmt->execute();
        $res = $stmt->get_result();
        $fixtures = $res->fetch_assoc()['count'];

        // 2. Deep Data (Lineups OR Commentary)
        $stmt_deep = $this->db->prepare( "
            SELECT 
                (SELECT COUNT(*) FROM commentary c INNER JOIN fixtures f ON c.eventid = f.eventid WHERE f.league_code = ? AND f.season = ?)
                 +
                (SELECT COUNT(*) FROM lineup l INNER JOIN fixtures f ON l.gameid = f.eventid WHERE f.league_code = ? AND f.season = ?)
            as deep_count
        " );
        
        if ($stmt_deep) {
            $stmt_deep->bind_param( "sisi", $league_code, $season, $league_code, $season );
            $stmt_deep->execute();
            $res_deep = $stmt_deep->get_result();
            $deep_data = $res_deep->fetch_assoc()['deep_count'];
        } else {
             $deep_data = 0; 
        }

        // 3. Platinum Data
        $stmt_stats = $this->db->prepare("SELECT COUNT(*) as count FROM season_player_stats WHERE league_code = ? AND season = ?");
        $stmt_stats->bind_param("si", $league_code, $season);
        $stmt_stats->execute();
        $stats_count = $stmt_stats->get_result()->fetch_assoc()['count'];
        
        $platinum_data = $stats_count; 

        // Logic
        $status = 'COMPLETE';
        if ( $fixtures < 10 ) {
            $status = 'MISSING';
        } elseif ( $deep_data == 0 || $platinum_data == 0 ) {
            $status = 'HOLLOW';
        }

        return [
            'status' => $status,
            'details' => [
                'fixtures' => $fixtures,
                'deep_data' => $deep_data,
                'platinum_data' => $platinum_data
            ]
        ];
    }

    /**
     * Scrape a single Season for a specific League
     */
    public function scrape_season( $league_code, $season, $mode = 'full' ) {
        error_log( "FDM_E_Master_Datasource: Initiating Backfill for [{$league_code}] [{$season}] (Mode: {$mode})..." );
        
        // Audit counters
        $audit = [
            'fixtures_found' => 0,
            'fixtures_inserted' => 0,
            'deep_attempts' => 0,
            'lineups_found' => 0,
            'commentary_found' => 0,
            'lineups_found' => 0,
            'commentary_found' => 0,
            'platinum_transfers' => 0,
            'platinum_stats' => 0
        ];

        // 1. Fetch Calendar (The Skeleton)
        // Using core.events.index to enumerate all events for the season (CANON)
        // Defaulting to type_id=1 (Regular Season).
        $type_id = 1; 
        $page = 1;
        $more_pages = true;
        
        $base_url = "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/seasons/{$season}/types/{$type_id}/events";
        
        while ( $more_pages ) {
            $url = add_query_arg( [
                'lang' => 'en',
                'region' => 'us',
                'page' => $page
            ], $base_url );
            
            $json = $this->fetch_json( $url );
            if ( ! $json || empty( $json['items'] ) ) {
                $more_pages = false;
                break;
            }
            
            foreach ( $json['items'] as $item_ref ) {
                if ( ! empty( $item_ref['$ref'] ) ) {
                    $this->process_event_ref( $item_ref['$ref'], $league_code, $season, $mode, $audit );
                }
            }
            
            // Pagination Check
            $page_count = isset($json['pageCount']) ? $json['pageCount'] : ( isset($json['count']) ? ceil($json['count'] / count($json['items'])) : 1 );
            if ( $page >= $page_count ) {
                $more_pages = false;
            } else {
                $page++;
            }
        }
        
        error_log( "FDM_E_Master_Datasource: Season [{$season}] for [{$league_code}] complete. Audit: " . json_encode( $audit ) );
        
        // 3. Platinum Data (Transfers & Season Stats)
        if ( $mode === 'full' || $mode === 'deep_only' ) {
            $t_count = $this->fetch_transfers( $league_code, $season );
            if ( $t_count !== false ) {
                $audit['platinum_transfers'] = $t_count;
            }

             $s_count = $this->fetch_season_stats( $league_code, $season );
            if ( $s_count !== false ) {
                $audit['platinum_stats'] = $s_count;
            }
        }

        return $audit;
    }

    /**
     * Process a single event reference (Fetch Metadata + Deep Data)
     */
    private function process_event_ref( $ref_url, $league_code, $season, $mode, &$audit ) {
        // 1. Dereference Event (Core API)
        $event_data = $this->fetch_json( $ref_url );
        if ( ! $event_data || empty( $event_data['id'] ) ) {
            return;
        }
        
        $match_id = $event_data['id'];
        $date = isset( $event_data['date'] ) ? $event_data['date'] : null;
        $status_state = isset( $event_data['competitions'][0]['status']['type']['state'] ) ? $event_data['competitions'][0]['status']['type']['state'] : 'unknown';
        
        $this->upsert_fixture( $league_code, $season, $match_id, $date, $status_state, $event_data );
        $audit['fixtures_found']++;
        $audit['fixtures_inserted']++;

        // 2. Deep Data (The Meat)
        if ( $mode === 'full' || $mode === 'deep_only' ) {
            // Only fetch for finished/active games
            if ( in_array( $status_state, ['post', 'final', 'full-time'] ) ) {
                $this->process_deep_data( $league_code, $match_id, $season, $audit );
            }
        }
    }

    /**
     * Fetch and Store Deep Data (Lineups + Commentary)
     */
    private function process_deep_data( $league_code, $match_id, $season, &$audit ) {
        $audit['deep_attempts']++;
        
        // Using site.summary.league (CANON)
        $summary_url = "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/summary?event={$match_id}";
        $summary = $this->fetch_json( $summary_url );
        
        if ( ! $summary ) return;
        
        // Lineups
        if ( ! empty( $summary['rosters'] ) ) {
            $this->store_lineups( $match_id, $season, $summary['rosters'] );
            $audit['lineups_found']++;
        }

        // Commentary
        if ( ! empty( $summary['commentary'] ) ) {
            $this->store_commentary( $match_id, $season, $summary['commentary'] );
            $audit['commentary_found']++;
        } elseif ( ! empty( $summary['keyEvents'] ) ) {
             $this->store_commentary( $match_id, $season, $summary['keyEvents'] );
        }
    }

    /**
     * Helper: Fetch JSON with basic error handling
     */
    private function fetch_json( $url ) {
        $response = wp_remote_get( $url, ['timeout' => 15, 'sslverify' => false] );
        if ( is_wp_error( $response ) ) {
            error_log( "FDM_E_Master_Datasource: Request failed [{$url}] - " . $response->get_error_message() );
            return null;
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return null;
        }
        $body = wp_remote_retrieve_body( $response );
        return json_decode( $body, true );
    }

    /**
     * DB Wrappers (Mocked for Phase 2, ready for Phase 3 DB integration)
     */
    private function upsert_fixture( $league_code, $season, $match_id, $date, $status, $data ) {
        if ( ! $this->db ) return;
        $stmt = $this->db->prepare("
            INSERT INTO fixtures (eventid, league_code, season, date, status_state, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE status_state = VALUES(status_state), updated_at = NOW()
        ");
        if ($stmt) {
            $stmt->bind_param("isiss", $match_id, $league_code, $season, $date, $status);
            $stmt->execute();
        }
    }

    private function store_lineups( $match_id, $season, $rosters ) {
        if ( empty( $rosters ) ) return;

        // Note: Schema uses 'lineups' (plural) and 'eventid'
        // Columns: eventid, teamid, athleteid, athletedisplayname, jersey, position, starter, subbedout, season
        
        $sql = "INSERT INTO lineups (eventid, teamid, athleteid, athletedisplayname, jersey, position, starter, subbedout, season) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare( $sql );
        
        if ( ! $stmt ) return;

        foreach ( $rosters as $team_roster ) {
            $team_id = isset( $team_roster['team']['id'] ) ? $team_roster['team']['id'] : 0;
            if ( empty( $team_roster['roster'] ) ) continue;

            foreach ( $team_roster['roster'] as $player ) {
                $athlete = isset( $player['athlete'] ) ? $player['athlete'] : [];
                $player_id = isset( $athlete['id'] ) ? $athlete['id'] : 0;
                $player_name = isset( $athlete['displayName'] ) ? $athlete['displayName'] : 'Unknown';
                $jersey = isset( $player['jersey'] ) ? $player['jersey'] : 0;
                $position = isset( $player['position']['name'] ) ? $player['position']['name'] : 'Unknown';
                $starter = ( isset( $player['starter'] ) && $player['starter'] ) ? 1 : 0;
                $subbed_out = ( isset( $player['subbedOut'] ) && $player['subbedOut'] ) ? 1 : 0;

                // Bind and Execute
                // i (eventid), i (teamid), i (athleteid), s (name), i (jersey), s (pos), i (start), i (sub), i (season)
                $stmt->bind_param( "iiisisiii", $match_id, $team_id, $player_id, $player_name, $jersey, $position, $starter, $subbed_out, $season );
                $stmt->execute();
            }
        }
    }

    private function store_commentary( $match_id, $season, $commentary ) {
        if ( empty( $commentary ) ) return;

        // Schema uses 'commentary'
        // Columns: eventid, commentarytext, clockdisplayvalue, season
        
        $sql = "INSERT INTO commentary (eventid, commentarytext, clockdisplayvalue, season) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare( $sql );
        
        if ( ! $stmt ) return;
        
        foreach ( $commentary as $comm ) {
            $text = isset( $comm['text'] ) ? $comm['text'] : '';
            $clock = isset( $comm['time']['displayValue'] ) ? $comm['time']['displayValue'] : '';
            if ( empty( $text ) ) continue;
            
            // i (eventid), s (text), s (clock), i (season)
            $stmt->bind_param( "issi", $match_id, $text, $clock, $season );
            $stmt->execute();
        }
    }

    /**
     * Fetch and Store Transfers (Platinum)
     */
    private function fetch_transfers( $league_code, $season ) {
        $url = "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/transactions?season={$season}";
        
        $response = wp_remote_get( $url, ['timeout' => 15, 'sslverify' => false] );
        if ( is_wp_error( $response ) ) return false;
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( empty( $data['transactions'] ) ) return false;

        $count = 0;
        foreach ( $data['transactions'] as $group ) {
            // structure can be grouped by date, or flat list. 
            // The probe showed 'transactions' -> array of groups? Or items?
            // The user provided logic assumes $data['transactions'] as the list of items?
            // Wait, looking at the probe output: $data['transactions'] was an array of objects.
            // Probe output sample keys: [date, status, athlete, from, to, type, displayAmount]
            // So $t is the item.
            
            // However, typically ESPN API returns grouped transactions. 
            // Let's stick strictly to the user's provided logic snippet but wrapped in the class structure.
            // User provided: foreach ($data['transactions'] as $t) { ... }
            
            $t = $group; // Assuming flat list based on User's request logic
            
            // Safe extraction with fallbacks
            $date = isset($t['date']) ? date('Y-m-d', strtotime($t['date'])) : null;
            $player_id = isset($t['athlete']['id']) ? $t['athlete']['id'] : 0;
            $player_name = isset($t['athlete']['displayName']) ? $t['athlete']['displayName'] : 'Unknown';
            $from_id = isset($t['from']['id']) ? $t['from']['id'] : 0;
            $from_name = isset($t['from']['displayName']) ? $t['from']['displayName'] : '';
            $to_id = isset($t['to']['id']) ? $t['to']['id'] : 0;
            $to_name = isset($t['to']['displayName']) ? $t['to']['displayName'] : '';
            $fee = isset($t['displayAmount']) ? $t['displayAmount'] : ''; 
            $type = isset($t['type']) ? $t['type'] : 'Transfer';

            if ( $this->db ) {
                $stmt = $this->db->prepare(
                    "REPLACE INTO transfers 
                    (season, date, player_id, player_name, from_team_id, from_team_name, to_team_id, to_team_name, fee, type) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                if ($stmt) {
                    $stmt->bind_param( "isisisssss", 
                        $season, $date, $player_id, $player_name, $from_id, $from_name, $to_id, $to_name, $fee, $type 
                    );
                    $stmt->execute();
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Fetch and Store Season Player Stats (Platinum)
     * Uses Core API to ensure historical data access
     */
    private function fetch_season_stats( $league_code, $season ) {
        // Core API Endpoint (types/1 = Regular Season)
        $url = "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/seasons/{$season}/types/1/leaders";
        
        $response = wp_remote_get( $url, ['timeout' => 15, 'sslverify' => false] );
        if ( is_wp_error( $response ) ) return false;
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( empty( $data['categories'] ) ) return false;

        $buffer = []; // [player_id => [goals=>0, assists=>0, team_id=>0, yellow_cards=>0, red_cards=>0]]

        // 1. Parse Data into Buffer
        foreach ( $data['categories'] as $cat ) {
            $metric = $cat['name']; // 'goals', 'assists', 'yellowCards', 'redCards', etc.
            if ( empty( $cat['leaders'] ) ) continue;

            foreach ( $cat['leaders'] as $leader ) {
                // Extract Player ID from $ref URL
                $athlete_url = isset($leader['athlete']['$ref']) ? $leader['athlete']['$ref'] : '';
                if ( ! preg_match( '/athletes\/(\d+)/', $athlete_url, $m ) ) continue;
                $player_id = (int)$m[1];

                // Extract Team ID
                $team_url = isset($leader['team']['$ref']) ? $leader['team']['$ref'] : '';
                $team_id = 0;
                if ( preg_match( '/teams\/(\d+)/', $team_url, $tm ) ) $team_id = (int)$tm[1];

                // Initialize Buffer
                if ( ! isset( $buffer[$player_id] ) ) {
                    $buffer[$player_id] = [
                        'team_id' => $team_id,
                        'goals' => 0, 
                        'assists' => 0, 
                        'yellow_cards' => 0, 
                        'red_cards' => 0
                    ];
                }

                // Map Metrics
                $val = (int)$leader['value'];
                if ( $metric === 'goals' ) $buffer[$player_id]['goals'] = $val;
                elseif ( $metric === 'assists' ) $buffer[$player_id]['assists'] = $val;
                elseif ( $metric === 'yellowCards' ) $buffer[$player_id]['yellow_cards'] = $val;
                elseif ( $metric === 'redCards' ) $buffer[$player_id]['red_cards'] = $val;
            }
        }

        // 2. Write Buffer to DB
        $count = 0;
        if ( $this->db ) {
            // Prepare statement once is tricky with varying params, but here structure is uniform.
            // Using a loop for now (max 50-100 players usually).
            $stmt = $this->db->prepare(
                "INSERT INTO season_player_stats 
                (season, league_code, player_id, team_id, goals, assists, yellow_cards, red_cards)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                goals = VALUES(goals), assists = VALUES(assists), 
                yellow_cards = VALUES(yellow_cards), red_cards = VALUES(red_cards)"
            );

            if ( $stmt ) {
                foreach ( $buffer as $pid => $stats ) {
                    $stmt->bind_param( "isiiiiii", 
                        $season, $league_code, $pid, $stats['team_id'], 
                        $stats['goals'], $stats['assists'], $stats['yellow_cards'], $stats['red_cards'] 
                    );
                    $stmt->execute();
                    $count++;
                }
            }
        }
        
        return $count;
    }

    /**
     * Main Backfill Loop
     */
    public function run_backfill_loop( $start_year, $end_year ) {
        $leagues = $this->get_platinum_competitions();
        if ( empty( $leagues ) ) {
            error_log( "FDM_E_Master_Datasource: No leagues found in Wishlist." );
            return;
        }

        foreach ( $leagues as $league_code ) {
            for ( $season = $start_year; $season <= $end_year; $season++ ) {
                $health = $this->get_season_health( $league_code, $season );
                
                if ( $health['status'] === 'MISSING' ) {
                    $this->scrape_season( $league_code, $season, 'full' );
                } elseif ( $health['status'] === 'HOLLOW' ) {
                    $this->scrape_season( $league_code, $season, 'deep_only' );
                } else {
                    // COMPLETE
                }
            }
        }
    }
}
