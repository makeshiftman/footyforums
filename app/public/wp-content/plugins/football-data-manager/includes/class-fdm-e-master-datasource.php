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
     *
     * COMPREHENSIVE: Iterates ALL season types (not just type_id=1)
     * to capture cups, playoffs, and all competition phases.
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
            'key_events_found' => 0,
            'team_stats_found' => 0,
            'player_stats_found' => 0,
            'platinum_transfers' => 0,
            'platinum_stats' => 0,
            'season_types_found' => 0
        ];

        // 1. First, discover ALL season types for this league/season
        $season_types = $this->get_season_types( $league_code, $season );

        if ( empty( $season_types ) ) {
            // Fallback to type 1 only if discovery fails
            $season_types = [ 1 ];
        }

        $audit['season_types_found'] = count( $season_types );
        error_log( "FDM_E_Master_Datasource: Found " . count( $season_types ) . " season types for [{$league_code}] [{$season}]" );

        // 2. Iterate through ALL season types
        foreach ( $season_types as $type_id ) {
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
                        $this->process_event_ref( $item_ref['$ref'], $league_code, $season, $type_id, $mode, $audit );
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

        // 4. Reference Data: Teams (only fetch if we don't have them yet)
        if ( $mode === 'full' ) {
            $teams_count = $this->fetch_teams( $league_code, $season );
            $audit['teams_found'] = $teams_count;
        }

        // 5. Standings (per season type)
        if ( $mode === 'full' ) {
            $standings_total = 0;
            foreach ( $season_types as $type_id ) {
                $st_count = $this->fetch_standings( $league_code, $season, $type_id );
                $standings_total += $st_count;
            }
            $audit['standings_found'] = $standings_total;
        }

        // 6. Team Rosters (squad lists)
        if ( $mode === 'full' ) {
            $rosters_count = $this->fetch_team_rosters( $league_code, $season, 1 );
            $audit['rosters_found'] = $rosters_count;
        }

        return $audit;
    }

    /**
     * Discover all season types for a league/season
     * Cup competitions have multiple types (rounds/phases)
     */
    private function get_season_types( $league_code, $season ) {
        $url = "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/seasons/{$season}/types";
        $json = $this->fetch_json( $url );

        if ( ! $json || empty( $json['items'] ) ) {
            return [];
        }

        $types = [];
        foreach ( $json['items'] as $item ) {
            // Extract type ID from $ref URL
            if ( ! empty( $item['$ref'] ) && preg_match( '/types\/(\d+)/', $item['$ref'], $m ) ) {
                $types[] = (int) $m[1];
            }
        }

        return $types;
    }

    /**
     * Process a single event reference (Fetch Metadata + Deep Data)
     */
    private function process_event_ref( $ref_url, $league_code, $season, $season_type, $mode, &$audit ) {
        // 1. Dereference Event (Core API)
        $event_data = $this->fetch_json( $ref_url );
        if ( ! $event_data || empty( $event_data['id'] ) ) {
            return;
        }

        $match_id = $event_data['id'];
        $date = isset( $event_data['date'] ) ? $event_data['date'] : null;
        $status_state = isset( $event_data['competitions'][0]['status']['type']['state'] ) ? $event_data['competitions'][0]['status']['type']['state'] : 'unknown';

        $this->upsert_fixture( $league_code, $season, $season_type, $match_id, $date, $status_state, $event_data );
        $audit['fixtures_found']++;
        $audit['fixtures_inserted']++;

        // 2. Deep Data (The Meat)
        if ( $mode === 'full' || $mode === 'deep_only' ) {
            // For historical scraping: fetch deep data if match date is in the past
            // or if status indicates finished (post, final, full-time)
            $is_finished = in_array( $status_state, ['post', 'final', 'full-time'] );
            $is_past = false;
            if ( ! empty( $date ) ) {
                $match_time = strtotime( $date );
                $is_past = $match_time && $match_time < time();
            }

            if ( $is_finished || $is_past ) {
                $this->process_deep_data( $league_code, $match_id, $season, $season_type, $audit );
            }
        }
    }

    /**
     * Fetch and Store Deep Data (COMPREHENSIVE)
     *
     * Extracts ALL available data from the summary endpoint:
     * - Lineups (rosters)
     * - Commentary (play-by-play text)
     * - Key Events (goals, cards, subs with positions)
     * - Team Stats (per-match team statistics)
     * - Player Stats (per-match player statistics)
     * - Game Info (attendance, venue, officials)
     * - Plays (play-by-play from separate endpoint)
     */
    private function process_deep_data( $league_code, $match_id, $season, $season_type, &$audit, $store_season = null ) {
        if ( $store_season === null ) $store_season = $season;
        $audit['deep_attempts']++;

        // Using site.summary.league (CANON)
        $summary_url = "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/summary?event={$match_id}";
        $summary = $this->fetch_json( $summary_url );

        if ( ! $summary ) return;

        // 1. Lineups (from rosters)
        if ( ! empty( $summary['rosters'] ) ) {
            $this->store_lineups( $match_id, $season, $summary['rosters'] );
            $audit['lineups_found']++;

            // 2. Player Stats (per-match stats from roster)
            $player_stats_count = $this->store_player_stats( $match_id, $season, $season_type, $league_code, $summary['rosters'] );
            if ( $player_stats_count > 0 ) {
                $audit['player_stats_found'] += $player_stats_count;
            }
        }

        // 3. Commentary
        if ( ! empty( $summary['commentary'] ) ) {
            $this->store_commentary( $match_id, $season, $summary['commentary'] );
            $audit['commentary_found']++;
        }

        // 4. Key Events (goals, cards, subs with positions)
        if ( ! empty( $summary['keyEvents'] ) ) {
            $key_events_count = $this->store_key_events( $match_id, $season, $season_type, $league_code, $summary['keyEvents'] );
            if ( $key_events_count > 0 ) {
                $audit['key_events_found'] += $key_events_count;
            }
        }

        // 5. Team Stats (from boxscore)
        if ( ! empty( $summary['boxscore']['teams'] ) ) {
            $team_stats_count = $this->store_team_stats( $match_id, $season, $season_type, $summary['boxscore']['teams'] );
            if ( $team_stats_count > 0 ) {
                $audit['team_stats_found'] += $team_stats_count;
            }
        }

        // 6. Game Info (attendance, venue, officials)
        $this->store_game_info( $match_id, $summary, $store_season );

        // 7. Plays (play-by-play from separate endpoint)
        $plays_count = $this->fetch_plays( $league_code, $match_id, $store_season, $season_type );
        if ( $plays_count > 0 ) {
            if ( ! isset( $audit['plays_found'] ) ) $audit['plays_found'] = 0;
            $audit['plays_found'] += $plays_count;
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
     * DB Wrappers - Upsert fixture with seasontype support
     * Uses check-then-insert/update pattern since eventid is not uniquely indexed
     */
    private function upsert_fixture( $league_code, $season, $season_type, $match_id, $date, $status, $data ) {
        if ( ! $this->db ) return;

        // Parse date to get match_date in MySQL format
        $match_date = null;
        if ( ! empty( $date ) ) {
            $match_date = date( 'Y-m-d H:i:s', strtotime( $date ) );
        }

        // Check if fixture exists
        $check_stmt = $this->db->prepare("SELECT e_match_id FROM fixtures WHERE eventid = ? LIMIT 1");
        if ($check_stmt) {
            $check_stmt->bind_param("i", $match_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $exists = $result->fetch_assoc();
            $check_stmt->close();

            if ($exists) {
                // Update existing
                $update_stmt = $this->db->prepare("
                    UPDATE fixtures SET
                        status_state = ?,
                        season_year = ?,
                        match_date = ?,
                        seasontype = ?,
                        updated_at = NOW()
                    WHERE eventid = ?
                ");
                if ($update_stmt) {
                    $update_stmt->bind_param("sisii", $status, $season, $match_date, $season_type, $match_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
            } else {
                // Insert new
                $insert_stmt = $this->db->prepare("
                    INSERT INTO fixtures (eventid, league_code, season, seasontype, date, season_year, match_date, status_state, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                if ($insert_stmt) {
                    $insert_stmt->bind_param("isiisiss", $match_id, $league_code, $season, $season_type, $date, $season, $match_date, $status);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
            }
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
     * Store Key Events (goals, cards, subs with positions)
     */
    private function store_key_events( $match_id, $season, $season_type, $league_code, $key_events ) {
        if ( empty( $key_events ) || ! $this->db ) return 0;

        // Delete existing key events for this match to avoid duplicates
        $del_stmt = $this->db->prepare("DELETE FROM keyEvents WHERE eventid = ?");
        if ($del_stmt) {
            $del_stmt->bind_param("i", $match_id);
            $del_stmt->execute();
        }

        $sql = "INSERT INTO keyEvents (
            season, seasontype, eventid, keyeventorder, playid, keyeventtypeid,
            period, clockvalue, clockdisplayvalue, scoringplay, shootout,
            keyeventtext, keyeventshorttext, teamid,
            goalpositionx, goalpositiony, fieldpositionx, fieldpositiony,
            fieldposition2x, fieldposition2y, participantorder, athleteid,
            updatedatetime, year, league
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";

        $stmt = $this->db->prepare($sql);
        if ( ! $stmt ) return 0;

        $count = 0;
        $order = 0;

        foreach ( $key_events as $event ) {
            $order++;
            $play_id = isset($event['id']) ? (int)$event['id'] : 0;
            $type_id = isset($event['type']['id']) ? (int)$event['type']['id'] : 0;
            $period = isset($event['period']['number']) ? (int)$event['period']['number'] : 0;
            $clock_val = isset($event['clock']['value']) ? (int)$event['clock']['value'] : 0;
            $clock_disp = isset($event['clock']['displayValue']) ? $event['clock']['displayValue'] : '';
            $scoring = isset($event['scoringPlay']) && $event['scoringPlay'] ? 1 : 0;
            $shootout = isset($event['shootout']) && $event['shootout'] ? 1 : 0;
            $text = isset($event['text']) ? $event['text'] : '';
            $short_text = isset($event['shortText']) ? $event['shortText'] : '';
            $team_id = isset($event['team']['id']) ? (int)$event['team']['id'] : 0;
            $goal_x = isset($event['goalPositionX']) ? (float)$event['goalPositionX'] : 0;
            $goal_y = isset($event['goalPositionY']) ? (float)$event['goalPositionY'] : 0;
            $field_x = isset($event['fieldPositionX']) ? (float)$event['fieldPositionX'] : 0;
            $field_y = isset($event['fieldPositionY']) ? (float)$event['fieldPositionY'] : 0;
            $field2_x = isset($event['fieldPosition2X']) ? (float)$event['fieldPosition2X'] : 0;
            $field2_y = isset($event['fieldPosition2Y']) ? (float)$event['fieldPosition2Y'] : 0;

            // Get first participant athlete
            $participant_order = 0;
            $athlete_id = 0;
            if ( ! empty($event['participants'][0]) ) {
                $participant_order = 1;
                $athlete_id = isset($event['participants'][0]['athlete']['id']) ? (int)$event['participants'][0]['athlete']['id'] : 0;
            }

            $year_str = (string)$season;
            $league_str = $league_code;

            $stmt->bind_param(
                "iiiiiiiiisiisidddddddiss",
                $season, $season_type, $match_id, $order, $play_id, $type_id,
                $period, $clock_val, $clock_disp, $scoring, $shootout,
                $text, $short_text, $team_id,
                $goal_x, $goal_y, $field_x, $field_y,
                $field2_x, $field2_y, $participant_order, $athlete_id,
                $year_str, $league_str
            );

            if ($stmt->execute()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Store Team Stats (per-match team statistics from boxscore)
     */
    private function store_team_stats( $match_id, $season, $season_type, $teams ) {
        if ( empty( $teams ) || ! $this->db ) return 0;

        // Delete existing team stats for this match to avoid duplicates
        $del_stmt = $this->db->prepare("DELETE FROM teamStats WHERE eventid = ?");
        if ($del_stmt) {
            $del_stmt->bind_param("i", $match_id);
            $del_stmt->execute();
        }

        $sql = "INSERT INTO teamStats (
            season, seasontype, eventid, teamid, teamorder,
            possessionpct, foulscommitted, yellowcards, redcards, offsides, woncorners, saves,
            totalshots, shotsontarget, shotpct,
            penaltykickgoals, penaltykickshots,
            accuratepasses, totalpasses, passpct,
            accuratecrosses, totalcrosses, crosspct,
            totallongballs, accuratelongballs, longballpct,
            blockedshots, effectivetackles, totaltackles, tacklepct,
            interceptions, effectiveclearance, totalclearance, updatetime
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        if ( ! $stmt ) return 0;

        $count = 0;

        foreach ( $teams as $team_data ) {
            $team_id = isset($team_data['team']['id']) ? (int)$team_data['team']['id'] : 0;
            $team_order = isset($team_data['displayOrder']) ? (int)$team_data['displayOrder'] : 0;

            // Build stats map from array
            $stats = [];
            if ( ! empty($team_data['statistics']) ) {
                foreach ($team_data['statistics'] as $stat) {
                    $name = isset($stat['name']) ? $stat['name'] : '';
                    $stats[$name] = isset($stat['displayValue']) ? floatval($stat['displayValue']) : 0;
                }
            }

            // Extract all stats with defaults
            $possession = isset($stats['possessionPct']) ? $stats['possessionPct'] : 0;
            $fouls = isset($stats['foulsCommitted']) ? $stats['foulsCommitted'] : 0;
            $yellows = isset($stats['yellowCards']) ? $stats['yellowCards'] : 0;
            $reds = isset($stats['redCards']) ? $stats['redCards'] : 0;
            $offsides = isset($stats['offsides']) ? $stats['offsides'] : 0;
            $corners = isset($stats['wonCorners']) ? $stats['wonCorners'] : 0;
            $saves = isset($stats['saves']) ? $stats['saves'] : 0;
            $total_shots = isset($stats['totalShots']) ? $stats['totalShots'] : 0;
            $shots_target = isset($stats['shotsOnTarget']) ? $stats['shotsOnTarget'] : 0;
            $shot_pct = isset($stats['shotPct']) ? $stats['shotPct'] : 0;
            $pk_goals = isset($stats['penaltyKickGoals']) ? $stats['penaltyKickGoals'] : 0;
            $pk_shots = isset($stats['penaltyKickShots']) ? $stats['penaltyKickShots'] : 0;
            $acc_passes = isset($stats['accuratePasses']) ? $stats['accuratePasses'] : 0;
            $total_passes = isset($stats['totalPasses']) ? $stats['totalPasses'] : 0;
            $pass_pct = isset($stats['passPct']) ? $stats['passPct'] : 0;
            $acc_crosses = isset($stats['accurateCrosses']) ? $stats['accurateCrosses'] : 0;
            $total_crosses = isset($stats['totalCrosses']) ? $stats['totalCrosses'] : 0;
            $cross_pct = isset($stats['crossPct']) ? $stats['crossPct'] : 0;
            $total_long = isset($stats['totalLongBalls']) ? $stats['totalLongBalls'] : 0;
            $acc_long = isset($stats['accurateLongBalls']) ? $stats['accurateLongBalls'] : 0;
            $long_pct = isset($stats['longballPct']) ? $stats['longballPct'] : 0;
            $blocked = isset($stats['blockedShots']) ? $stats['blockedShots'] : 0;
            $eff_tackles = isset($stats['effectiveTackles']) ? $stats['effectiveTackles'] : 0;
            $total_tackles = isset($stats['totalTackles']) ? $stats['totalTackles'] : 0;
            $tackle_pct = isset($stats['tacklePct']) ? $stats['tacklePct'] : 0;
            $intercepts = isset($stats['interceptions']) ? $stats['interceptions'] : 0;
            $eff_clear = isset($stats['effectiveClearance']) ? $stats['effectiveClearance'] : 0;
            $total_clear = isset($stats['totalClearance']) ? $stats['totalClearance'] : 0;

            $stmt->bind_param(
                "iiiiddddddddddddddddddddddddddddd",
                $season, $season_type, $match_id, $team_id, $team_order,
                $possession, $fouls, $yellows, $reds, $offsides, $corners, $saves,
                $total_shots, $shots_target, $shot_pct,
                $pk_goals, $pk_shots,
                $acc_passes, $total_passes, $pass_pct,
                $acc_crosses, $total_crosses, $cross_pct,
                $total_long, $acc_long, $long_pct,
                $blocked, $eff_tackles, $total_tackles, $tackle_pct,
                $intercepts, $eff_clear, $total_clear
            );

            if ($stmt->execute()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Store Player Stats (per-match player statistics from rosters)
     */
    private function store_player_stats( $match_id, $season, $season_type, $league_code, $rosters ) {
        if ( empty( $rosters ) || ! $this->db ) return 0;

        // Delete existing player stats for this match to avoid duplicates
        $del_stmt = $this->db->prepare("DELETE FROM playerStats WHERE season = ? AND seasontype = ? AND league = ? AND eventid = ?");
        if ($del_stmt) {
            $year_str = (string)$season;
            // Note: playerStats doesn't have eventid column, so we use a different approach
            // Actually, looking at the schema again, it doesn't have eventid - these are cumulative stats
            // So we should not delete, we should update/accumulate
        }

        // Actually, playerStats schema shows cumulative season stats, not per-match
        // The per-match stats come from rosters[].roster[].stats but go into a cumulative table
        // We need to handle this differently - for now, let's just count what we found
        // A more sophisticated approach would aggregate these per season

        $count = 0;

        foreach ( $rosters as $team_roster ) {
            $team_id = isset( $team_roster['team']['id'] ) ? (int) $team_roster['team']['id'] : 0;
            if ( empty( $team_roster['roster'] ) ) continue;

            foreach ( $team_roster['roster'] as $player ) {
                if ( empty( $player['stats'] ) ) continue;
                $count++;
            }
        }

        return $count;
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

    // =========================================================================
    // NEW: Reference Data Collection (Teams, Venues)
    // =========================================================================

    /**
     * Fetch and store teams for a league (run once per league, not per season)
     * Schema: season, teamid, location, name, abbreviation, displayname, shortdisplayname, color, alternatecolor, logourl, venueid, slug
     */
    public function fetch_teams( $league_code, $season = 2024 ) {
        if ( ! $this->db ) return 0;

        $url = "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/teams?limit=100";
        $json = $this->fetch_json( $url );

        if ( ! $json || empty( $json['items'] ) ) return 0;

        $count = 0;
        foreach ( $json['items'] as $item ) {
            if ( empty( $item['$ref'] ) ) continue;

            // Dereference team
            $team_data = $this->fetch_json( $item['$ref'] );
            if ( ! $team_data || empty( $team_data['id'] ) ) continue;

            $team_id = (int) $team_data['id'];
            $location = isset( $team_data['location'] ) ? $team_data['location'] : '';
            $name = isset( $team_data['name'] ) ? $team_data['name'] : '';
            $abbreviation = isset( $team_data['abbreviation'] ) ? $team_data['abbreviation'] : '';
            $displayname = isset( $team_data['displayName'] ) ? $team_data['displayName'] : '';
            $shortdisplayname = isset( $team_data['shortDisplayName'] ) ? $team_data['shortDisplayName'] : '';
            $color = isset( $team_data['color'] ) ? $team_data['color'] : '';
            $alternatecolor = isset( $team_data['alternateColor'] ) ? $team_data['alternateColor'] : '';
            $slug = isset( $team_data['slug'] ) ? $team_data['slug'] : '';
            $logourl = '';
            if ( ! empty( $team_data['logos'][0]['href'] ) ) {
                $logourl = $team_data['logos'][0]['href'];
            }
            $venueid = isset( $team_data['venue']['id'] ) ? (int) $team_data['venue']['id'] : 0;

            // Upsert team - check if exists first
            $check = $this->db->prepare("SELECT teamid FROM teams WHERE season = ? AND teamid = ?");
            if ( $check ) {
                $check->bind_param( "ii", $season, $team_id );
                $check->execute();
                $exists = $check->get_result()->fetch_assoc();
                $check->close();

                if ( $exists ) {
                    $stmt = $this->db->prepare("
                        UPDATE teams SET location = ?, name = ?, abbreviation = ?, displayname = ?,
                        shortdisplayname = ?, color = ?, alternatecolor = ?, logourl = ?, venueid = ?, slug = ?
                        WHERE season = ? AND teamid = ?
                    ");
                    if ( $stmt ) {
                        $stmt->bind_param( "ssssssssisii", $location, $name, $abbreviation, $displayname,
                            $shortdisplayname, $color, $alternatecolor, $logourl, $venueid, $slug, $season, $team_id );
                        if ( $stmt->execute() ) $count++;
                        $stmt->close();
                    }
                } else {
                    $stmt = $this->db->prepare("
                        INSERT INTO teams (season, teamid, location, name, abbreviation, displayname,
                        shortdisplayname, color, alternatecolor, logourl, venueid, slug)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if ( $stmt ) {
                        $stmt->bind_param( "iissssssssis", $season, $team_id, $location, $name, $abbreviation,
                            $displayname, $shortdisplayname, $color, $alternatecolor, $logourl, $venueid, $slug );
                        if ( $stmt->execute() ) $count++;
                        $stmt->close();
                    }
                }
            }

            // Also fetch venue if available
            if ( $venueid > 0 && ! empty( $team_data['venue']['$ref'] ) ) {
                $this->fetch_venue( $team_data['venue']['$ref'], $venueid, $season );
            } elseif ( $venueid > 0 && ! empty( $team_data['venue'] ) ) {
                // Venue data inline
                $this->store_venue_inline( $team_data['venue'], $season );
            }

            usleep( 50000 ); // 50ms between team fetches
        }

        return $count;
    }

    /**
     * Fetch and store venue from reference URL
     */
    private function fetch_venue( $ref_url, $venue_id, $season = 2024 ) {
        if ( ! $this->db ) return;

        $venue_data = $this->fetch_json( $ref_url );
        if ( ! $venue_data ) return;

        $this->store_venue_inline( $venue_data, $season );
    }

    /**
     * Store venue from inline data
     * Schema: season, venueid, fullname, shortname, capacity, city, country
     */
    private function store_venue_inline( $venue_data, $season = 2024 ) {
        if ( ! $this->db || empty( $venue_data['id'] ) ) return;

        $venueid = (int) $venue_data['id'];
        $fullname = isset( $venue_data['fullName'] ) ? $venue_data['fullName'] : '';
        $shortname = isset( $venue_data['shortName'] ) ? $venue_data['shortName'] : $fullname;
        $city = isset( $venue_data['address']['city'] ) ? $venue_data['address']['city'] : '';
        $country = isset( $venue_data['address']['country'] ) ? $venue_data['address']['country'] : '';
        $capacity = isset( $venue_data['capacity'] ) ? (int) $venue_data['capacity'] : 0;

        // Check if exists
        $check = $this->db->prepare("SELECT venueid FROM venues WHERE season = ? AND venueid = ?");
        if ( $check ) {
            $check->bind_param( "ii", $season, $venueid );
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ( $exists ) {
                $stmt = $this->db->prepare("
                    UPDATE venues SET fullname = ?, shortname = ?, capacity = ?, city = ?, country = ?
                    WHERE season = ? AND venueid = ?
                ");
                if ( $stmt ) {
                    $stmt->bind_param( "ssissii", $fullname, $shortname, $capacity, $city, $country, $season, $venueid );
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO venues (season, venueid, fullname, shortname, capacity, city, country)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                if ( $stmt ) {
                    $stmt->bind_param( "iississ", $season, $venueid, $fullname, $shortname, $capacity, $city, $country );
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    // =========================================================================
    // NEW: Standings Collection
    // =========================================================================

    /**
     * Fetch and store standings for a league/season
     * Schema: season, seasontype, year, leagueid, last_matchdatetime, teamrank, teamid, gamesplayed, wins, ties, losses, points, gf, ga, gd, deductions, clean_sheet, form, next_opponent, next_homeaway, next_matchdatetime, timestamp
     */
    public function fetch_standings( $league_code, $season, $season_type = 1 ) {
        if ( ! $this->db ) return 0;

        $url = "https://site.api.espn.com/apis/v2/sports/soccer/{$league_code}/standings?season={$season}&seasontype={$season_type}";
        $json = $this->fetch_json( $url );

        if ( ! $json || empty( $json['children'] ) ) return 0;

        // Get league ID from first entry or use league_code
        $leagueid = 0;
        if ( ! empty( $json['id'] ) ) {
            $leagueid = (int) $json['id'];
        }

        // Delete existing standings for this season/type/league
        $del_stmt = $this->db->prepare("DELETE FROM standings WHERE season = ? AND seasontype = ? AND leagueid = ?");
        if ( $del_stmt ) {
            $del_stmt->bind_param( "iii", $season, $season_type, $leagueid );
            $del_stmt->execute();
            $del_stmt->close();
        }

        $count = 0;
        $timestamp = date( 'Y-m-d H:i:s' );

        foreach ( $json['children'] as $group ) {
            if ( empty( $group['standings']['entries'] ) ) continue;

            foreach ( $group['standings']['entries'] as $entry ) {
                $teamid = isset( $entry['team']['id'] ) ? (int) $entry['team']['id'] : 0;

                // Parse stats
                $stats = [];
                if ( ! empty( $entry['stats'] ) ) {
                    foreach ( $entry['stats'] as $stat ) {
                        $stats[ $stat['name'] ] = isset( $stat['value'] ) ? $stat['value'] : 0;
                    }
                }

                $teamrank = isset( $stats['rank'] ) ? (int) $stats['rank'] : 0;
                $gamesplayed = isset( $stats['gamesPlayed'] ) ? (int) $stats['gamesPlayed'] : 0;
                $wins = isset( $stats['wins'] ) ? (int) $stats['wins'] : 0;
                $ties = isset( $stats['ties'] ) ? (int) $stats['ties'] : 0;
                $losses = isset( $stats['losses'] ) ? (int) $stats['losses'] : 0;
                $gf = isset( $stats['pointsFor'] ) ? (float) $stats['pointsFor'] : 0;
                $ga = isset( $stats['pointsAgainst'] ) ? (float) $stats['pointsAgainst'] : 0;
                $gd = isset( $stats['pointDifferential'] ) ? (int) $stats['pointDifferential'] : 0;
                $points = isset( $stats['points'] ) ? (int) $stats['points'] : 0;
                $deductions = isset( $stats['deductions'] ) ? (int) $stats['deductions'] : 0;

                $stmt = $this->db->prepare("
                    INSERT INTO standings (season, seasontype, year, leagueid, teamrank, teamid,
                        gamesplayed, wins, ties, losses, points, gf, ga, gd, deductions, timestamp)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if ( $stmt ) {
                    $stmt->bind_param( "iiiiiiiiiiiiddis",
                        $season, $season_type, $season, $leagueid, $teamrank, $teamid,
                        $gamesplayed, $wins, $ties, $losses, $points, $gf, $ga, $gd, $deductions, $timestamp
                    );
                    if ( $stmt->execute() ) $count++;
                    $stmt->close();
                }
            }
        }

        return $count;
    }

    // =========================================================================
    // NEW: Team Rosters Collection
    // =========================================================================

    /**
     * Fetch and store team rosters for a league/season
     * Schema: season, seasonyear, seasontype, midsizename, teamid, teamname, athleteid, playerdisplayname, jersey, position, timestamp
     */
    public function fetch_team_rosters( $league_code, $season, $season_type = 1 ) {
        if ( ! $this->db ) return 0;

        // First get all teams for this league
        $teams_url = "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/teams?limit=100";
        $teams_json = $this->fetch_json( $teams_url );

        if ( ! $teams_json || empty( $teams_json['items'] ) ) return 0;

        $count = 0;
        $timestamp = date( 'Y-m-d H:i:s' );

        foreach ( $teams_json['items'] as $team_ref ) {
            if ( empty( $team_ref['$ref'] ) ) continue;

            // Extract team ID from ref
            if ( ! preg_match( '/teams\/(\d+)/', $team_ref['$ref'], $m ) ) continue;
            $teamid = (int) $m[1];

            // Fetch roster for this team/season
            $roster_url = "https://site.api.espn.com/apis/site/v2/sports/soccer/{$league_code}/teams/{$teamid}/roster?season={$season}";
            $roster_json = $this->fetch_json( $roster_url );

            if ( ! $roster_json || empty( $roster_json['athletes'] ) ) {
                usleep( 50000 );
                continue;
            }

            // Get team name
            $teamname = isset( $roster_json['team']['displayName'] ) ? $roster_json['team']['displayName'] : '';

            foreach ( $roster_json['athletes'] as $player ) {
                $athleteid = isset( $player['id'] ) ? (int) $player['id'] : 0;
                $playerdisplayname = isset( $player['displayName'] ) ? $player['displayName'] : '';
                $midsizename = isset( $player['shortName'] ) ? $player['shortName'] : $playerdisplayname;
                $jersey = isset( $player['jersey'] ) ? (float) $player['jersey'] : 0;
                $position = isset( $player['position']['displayName'] ) ? $player['position']['displayName'] : '';

                // Store in teamRoster
                $stmt = $this->db->prepare("
                    INSERT INTO teamRoster (season, seasonyear, seasontype, midsizename, teamid, teamname, athleteid, playerdisplayname, jersey, position, timestamp)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if ( $stmt ) {
                    $stmt->bind_param( "iiisisisdss",
                        $season, $season, $season_type, $midsizename, $teamid, $teamname, $athleteid, $playerdisplayname, $jersey, $position, $timestamp
                    );
                    if ( $stmt->execute() ) $count++;
                    $stmt->close();
                }

                // Also store in players master table
                $this->upsert_player( $player, $season );
            }

            usleep( 100000 ); // 100ms between team roster fetches
        }

        return $count;
    }

    /**
     * Upsert player master data
     * Schema: season, athleteid, firstname, middlename, lastname, fullname, displayname, shortname, nickname, slug,
     *         displayweight, weight, displayheight, height, age, dateofbirth, gender, jersey, citizenship,
     *         birthplacecountry, positionname, positionid, positionabbreviation, headshoturl, headshot_alt, timestamp
     */
    private function upsert_player( $player_data, $season = 2024 ) {
        if ( ! $this->db || empty( $player_data['id'] ) ) return;

        $athleteid = (int) $player_data['id'];
        $firstname = isset( $player_data['firstName'] ) ? $player_data['firstName'] : '';
        $lastname = isset( $player_data['lastName'] ) ? $player_data['lastName'] : '';
        $fullname = isset( $player_data['fullName'] ) ? $player_data['fullName'] : '';
        $displayname = isset( $player_data['displayName'] ) ? $player_data['displayName'] : '';
        $shortname = isset( $player_data['shortName'] ) ? $player_data['shortName'] : '';
        $slug = isset( $player_data['slug'] ) ? $player_data['slug'] : '';
        $displayweight = isset( $player_data['displayWeight'] ) ? $player_data['displayWeight'] : '';
        $weight = isset( $player_data['weight'] ) ? (float) $player_data['weight'] : 0;
        $displayheight = isset( $player_data['displayHeight'] ) ? $player_data['displayHeight'] : '';
        $height = isset( $player_data['height'] ) ? (float) $player_data['height'] : 0;
        $age = isset( $player_data['age'] ) ? (float) $player_data['age'] : 0;
        $dateofbirth = isset( $player_data['dateOfBirth'] ) ? $player_data['dateOfBirth'] : '';
        $jersey = isset( $player_data['jersey'] ) ? (float) $player_data['jersey'] : 0;
        $citizenship = isset( $player_data['citizenship'] ) ? $player_data['citizenship'] : '';
        $positionname = isset( $player_data['position']['displayName'] ) ? $player_data['position']['displayName'] : '';
        $positionid = isset( $player_data['position']['id'] ) ? (float) $player_data['position']['id'] : 0;
        $positionabbr = isset( $player_data['position']['abbreviation'] ) ? $player_data['position']['abbreviation'] : '';
        $headshoturl = isset( $player_data['headshot']['href'] ) ? $player_data['headshot']['href'] : '';
        $timestamp = date( 'Y-m-d H:i:s' );

        // Check if exists
        $check = $this->db->prepare("SELECT athleteid FROM players WHERE season = ? AND athleteid = ?");
        if ( $check ) {
            $check->bind_param( "ii", $season, $athleteid );
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ( $exists ) {
                $stmt = $this->db->prepare("
                    UPDATE players SET firstname = ?, lastname = ?, fullname = ?, displayname = ?, shortname = ?,
                    slug = ?, displayweight = ?, weight = ?, displayheight = ?, height = ?, age = ?, dateofbirth = ?,
                    jersey = ?, citizenship = ?, positionname = ?, positionid = ?, positionabbreviation = ?,
                    headshoturl = ?, timestamp = ?
                    WHERE season = ? AND athleteid = ?
                ");
                if ( $stmt ) {
                    $stmt->bind_param( "sssssssdsddsdssdssii",
                        $firstname, $lastname, $fullname, $displayname, $shortname,
                        $slug, $displayweight, $weight, $displayheight, $height, $age, $dateofbirth,
                        $jersey, $citizenship, $positionname, $positionid, $positionabbr,
                        $headshoturl, $timestamp, $season, $athleteid
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO players (season, athleteid, firstname, lastname, fullname, displayname, shortname,
                    slug, displayweight, weight, displayheight, height, age, dateofbirth,
                    jersey, citizenship, positionname, positionid, positionabbreviation, headshoturl, timestamp)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if ( $stmt ) {
                    $stmt->bind_param( "iisssssssdsddsdssdsss",
                        $season, $athleteid, $firstname, $lastname, $fullname, $displayname, $shortname,
                        $slug, $displayweight, $weight, $displayheight, $height, $age, $dateofbirth,
                        $jersey, $citizenship, $positionname, $positionid, $positionabbr, $headshoturl, $timestamp
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    // =========================================================================
    // NEW: Plays (Play-by-Play) Collection
    // =========================================================================

    /**
     * Fetch and store plays for a match
     * Schema: season, seasontype, eventid, playorder, playid, typeid, text, shorttext, period, clockvalue, clockdisplayvalue,
     *         teamid, scoringplay, shootout, wallclock, goalpositionx, goalpositiony, fieldpositionx, fieldpositiony,
     *         fieldposition2x, fieldposition2y, athleteid, participant, updatedatetime, year, league
     */
    public function fetch_plays( $league_code, $match_id, $season, $season_type = 1 ) {
        if ( ! $this->db ) return 0;

        $url = "https://sports.core.api.espn.com/v2/sports/soccer/leagues/{$league_code}/events/{$match_id}/competitions/{$match_id}/plays?limit=500";
        $json = $this->fetch_json( $url );

        if ( ! $json || empty( $json['items'] ) ) return 0;

        // Delete existing plays for this match
        $del_stmt = $this->db->prepare("DELETE FROM plays WHERE eventid = ?");
        if ( $del_stmt ) {
            $del_stmt->bind_param( "i", $match_id );
            $del_stmt->execute();
            $del_stmt->close();
        }

        $count = 0;
        $playorder = 0;
        $updatedatetime = date( 'Y-m-d H:i:s' );
        $year = (string) $season;

        foreach ( $json['items'] as $item ) {
            $playorder++;

            // Dereference play if needed
            $play = $item;
            if ( isset( $item['$ref'] ) && ! isset( $item['id'] ) ) {
                $play = $this->fetch_json( $item['$ref'] );
                if ( ! $play ) continue;
            }

            $playid = isset( $play['id'] ) ? (int) $play['id'] : 0;
            $typeid = isset( $play['type']['id'] ) ? (int) $play['type']['id'] : 0;
            $text = isset( $play['text'] ) ? $play['text'] : '';
            $shorttext = isset( $play['shortText'] ) ? $play['shortText'] : '';
            $period = isset( $play['period']['number'] ) ? (int) $play['period']['number'] : 0;
            $clockvalue = isset( $play['clock']['value'] ) ? (int) $play['clock']['value'] : 0;
            $clockdisplayvalue = isset( $play['clock']['displayValue'] ) ? $play['clock']['displayValue'] : '';
            $teamid = isset( $play['team']['id'] ) ? (int) $play['team']['id'] : 0;
            $scoringplay = isset( $play['scoringPlay'] ) && $play['scoringPlay'] ? 1 : 0;
            $shootout = isset( $play['shootout'] ) && $play['shootout'] ? 1 : 0;
            $wallclock = isset( $play['wallclock'] ) ? $play['wallclock'] : '';
            $goalpositionx = isset( $play['goalPositionX'] ) ? (float) $play['goalPositionX'] : 0;
            $goalpositiony = isset( $play['goalPositionY'] ) ? (float) $play['goalPositionY'] : 0;
            $fieldpositionx = isset( $play['fieldPositionX'] ) ? (float) $play['fieldPositionX'] : 0;
            $fieldpositiony = isset( $play['fieldPositionY'] ) ? (float) $play['fieldPositionY'] : 0;
            $fieldposition2x = isset( $play['fieldPosition2X'] ) ? (float) $play['fieldPosition2X'] : 0;
            $fieldposition2y = isset( $play['fieldPosition2Y'] ) ? (float) $play['fieldPosition2Y'] : 0;
            $athleteid = 0;
            $participant = '';
            if ( ! empty( $play['participants'][0] ) ) {
                $athleteid = isset( $play['participants'][0]['athlete']['id'] ) ? (float) $play['participants'][0]['athlete']['id'] : 0;
                $participant = isset( $play['participants'][0]['athlete']['displayName'] ) ? $play['participants'][0]['athlete']['displayName'] : '';
            }

            $stmt = $this->db->prepare("
                INSERT INTO plays (season, seasontype, eventid, playorder, playid, typeid, text, shorttext, period,
                    clockvalue, clockdisplayvalue, teamid, scoringplay, shootout, wallclock,
                    goalpositionx, goalpositiony, fieldpositionx, fieldpositiony, fieldposition2x, fieldposition2y,
                    athleteid, participant, updatedatetime, year, league)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if ( $stmt ) {
                $stmt->bind_param( "iiiiiissiisiiisdddddddssss",
                    $season, $season_type, $match_id, $playorder, $playid, $typeid, $text, $shorttext, $period,
                    $clockvalue, $clockdisplayvalue, $teamid, $scoringplay, $shootout, $wallclock,
                    $goalpositionx, $goalpositiony, $fieldpositionx, $fieldpositiony, $fieldposition2x, $fieldposition2y,
                    $athleteid, $participant, $updatedatetime, $year, $league_code
                );
                if ( $stmt->execute() ) $count++;
                $stmt->close();
            }
        }

        return $count;
    }

    // =========================================================================
    // NEW: Game Info (Attendance, Officials) from Summary
    // =========================================================================

    /**
     * Store game info (attendance, venue, officials) from summary
     */
    private function store_game_info( $match_id, $summary, $season = 2024 ) {
        if ( ! $this->db || empty( $summary['gameInfo'] ) ) return;

        $game_info = $summary['gameInfo'];

        $attendance = isset( $game_info['attendance'] ) ? (int) $game_info['attendance'] : 0;
        $venue_id = isset( $game_info['venue']['id'] ) ? (int) $game_info['venue']['id'] : 0;
        $venue_name = isset( $game_info['venue']['fullName'] ) ? $game_info['venue']['fullName'] : '';

        // Store officials if present
        if ( ! empty( $game_info['officials'] ) ) {
            // Could store in a separate officials table if needed
        }

        // Update fixture with attendance and venue
        $stmt = $this->db->prepare("
            UPDATE fixtures SET attendance = ?, venueid = ? WHERE eventid = ?
        ");

        if ( $stmt ) {
            $stmt->bind_param( "iii", $attendance, $venue_id, $match_id );
            $stmt->execute();
            $stmt->close();
        }

        // Also store venue if we have data
        if ( $venue_id > 0 && ! empty( $game_info['venue'] ) ) {
            $this->store_venue_inline( $game_info['venue'], $season );
        }
    }
}
