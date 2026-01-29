<?php
/**
 * Class FDM_Daily_Updater
 * 
 * "The Daily Updater"
 * Fetches global match feeds (Scorepanel) to perform daily sync/catch-up.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class FDM_Daily_Updater {

    private $db;

    public function __construct() {
        $this->db = $this->connect_db();
    }

    /**
     * Connect to e_db
     */
    private function connect_db() {
        // Reuse logic from Master Datasource pathing or just use standard constants if available.
        // For robustness in CLI mode, we parse my.cnf
        $cnf_file = dirname( dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) ) ) . '/app/tools/transitional/my.cnf';
        
        if ( ! file_exists( $cnf_file ) ) {
             $cnf_file = '/Users/kevincasey/Local Sites/footyforums/app/tools/transitional/my.cnf';
        }

        if ( file_exists( $cnf_file ) ) {
            $ini = parse_ini_file( $cnf_file, false, INI_SCANNER_RAW );
            $user = isset( $ini['user'] ) ? $ini['user'] : 'root';
            $pass = isset( $ini['password'] ) ? $ini['password'] : 'root';
            $host = 'localhost'; 
            $socket = isset( $ini['socket'] ) ? $ini['socket'] : null;

            $mysqli = new mysqli( $host, $user, $pass, 'e_db', 0, $socket );
            if ( $mysqli->connect_error ) {
                echo "DB Connection Failed: " . $mysqli->connect_error . "\n";
                return null;
            }
            return $mysqli;
        }
        return null;
    }

    /**
     * The Main Event: Fetches global scores for a date and updates DB.
     * @param string|null $date_str YYYYMMDD
     */
    public function run_daily_sync($date_str = null) {
        if (!$this->db) {
            echo "Error: No Database Connection.\n";
            return;
        }

        if (!$date_str) $date_str = date('Ymd', strtotime('yesterday'));
        
        echo "Running Daily Sync for: $date_str\n";

        // 1. Fetch Global Feed
        $url = "https://site.api.espn.com/apis/site/v2/sports/soccer/scorepanel?dates=$date_str";
        $response = wp_remote_get($url, ['timeout' => 20, 'sslverify' => false]);
        
        if (is_wp_error($response)) {
            echo "API Error: " . $response->get_error_message() . "\n";
            return;
        }

        $json = wp_remote_retrieve_body($response);
        $data = json_decode($json, true);

        if (empty($data['scores'])) {
            echo "No scores found for $date_str.\n";
            return;
        }

        // 2. Iterate Scores (Grouped by League)
        $total_updates = 0;
        foreach ($data['scores'] as $score_group) {
            // Extract League Slug from metadata (usually only 1 league per score group)
            $league_slug = isset($score_group['leagues'][0]['slug']) ? $score_group['leagues'][0]['slug'] : 'unknown';
            
            if (empty($score_group['events'])) continue;

            foreach ($score_group['events'] as $event) {
                // 3. Upsert Fixture
                $fixture_data = $this->map_scorepanel_to_fixture($event, $league_slug);
                
                if ($fixture_data) {
                    $this->upsert_fixture($fixture_data);
                    $total_updates++;
                }
            }
        }

        echo "Sync Complete. Processed $total_updates matches.\n";
    }

    /**
     * Helper: Maps the Global Feed format to our Schema
     */
    private function map_scorepanel_to_fixture($event, $league_slug) {
        if ( empty($event['id']) ) return null;

        $id = $event['id'];
        $date = isset($event['date']) ? date('Y-m-d H:i:s', strtotime($event['date'])) : null;
        $status = isset($event['status']['type']['state']) ? $event['status']['type']['state'] : 'unknown';
        // 'details' sometimes holds current time, but state is what we use in DB.
        
        // Parse Season from date? Or is it in the event?
        // Scorepanel events usually have 'season': { 'year': 2023, 'type': 1 }
        $season = isset($event['season']['year']) ? $event['season']['year'] : ( $date ? date('Y', strtotime($date)) : 0 );

        return [
            'id' => $id,
            'date' => $date,
            'status' => $status,
            'league_code' => $league_slug,
            'season' => $season
        ];
    }

    /**
     * Local Upsert Logic
     */
    private function upsert_fixture($data) {
        $stmt = $this->db->prepare("
            INSERT INTO fixtures (eventid, league_code, season, date, status_state, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            status_state = VALUES(status_state), 
            date = VALUES(date),
            updated_at = NOW()
        ");
        
        if ($stmt) {
            // i (eventid), s (league), i (season), s (date), s (status)
            $stmt->bind_param("isiss", $data['id'], $data['league_code'], $data['season'], $data['date'], $data['status']);
            $stmt->execute();
        }
    }
}
