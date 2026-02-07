<?php
/**
 * Class FDM_Manual_Verification
 *
 * Handles manual verification of ESPN data availability.
 * When the prober can't find data, it logs URLs here for manual checking.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FDM_Manual_Verification {

    /**
     * Database connection to e_db
     *
     * @var mysqli|null
     */
    private $e_db;

    /**
     * Constructor
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
        $cnf_paths = [
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

        if ( ! $socket ) {
            $socket = '/Users/kevincasey/Library/Application Support/Local/run/0WeAfCFCz/mysql/mysqld.sock';
        }

        $mysqli = @new mysqli( 'localhost', $user, $pass, 'e_db', 0, $socket );
        if ( $mysqli->connect_error ) {
            error_log( '[FDM Manual Verification] DB connection failed: ' . $mysqli->connect_error );
            error_log( '[FDM Manual Verification] Socket used: ' . $socket );
            return null;
        }

        return $mysqli;
    }

    /**
     * Create the verification table if it doesn't exist
     */
    public function create_table() {
        if ( ! $this->e_db ) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS espn_manual_verification (
            id INT AUTO_INCREMENT PRIMARY KEY,
            league_code VARCHAR(32) NOT NULL,
            league_name VARCHAR(128) DEFAULT '',
            season_year INT NOT NULL,
            data_type VARCHAR(32) NOT NULL,
            check_url VARCHAR(512) NOT NULL,
            status ENUM('pending', 'verified_exists', 'verified_missing', 'skipped') DEFAULT 'pending',
            verified_at DATETIME DEFAULT NULL,
            verified_by VARCHAR(64) DEFAULT NULL,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_check (league_code, season_year, data_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        return $this->e_db->query( $sql );
    }

    /**
     * Log a URL for manual verification
     *
     * @param string $league_code ESPN league code
     * @param string $league_name Human-readable league name
     * @param int    $season_year Season year
     * @param string $data_type   Type of data (plays, teams, standings, etc.)
     * @param string $url         API URL to check
     * @param string $site_url    ESPN website URL (optional)
     * @return bool
     */
    public function log_for_verification( $league_code, $league_name, $season_year, $data_type, $url, $site_url = null ) {
        if ( ! $this->e_db ) {
            return false;
        }

        $sql = "INSERT INTO espn_manual_verification
            (league_code, league_name, season_year, data_type, check_url, site_url, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ON DUPLICATE KEY UPDATE
                check_url = VALUES(check_url),
                site_url = VALUES(site_url),
                league_name = VALUES(league_name),
                created_at = NOW()";

        $stmt = $this->e_db->prepare( $sql );
        if ( ! $stmt ) {
            return false;
        }

        $stmt->bind_param( 'ssisss', $league_code, $league_name, $season_year, $data_type, $url, $site_url );
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Get pending verifications
     *
     * @param string $filter_type Optional data type filter
     * @param int    $limit       Max results
     * @param int    $offset      Offset for pagination
     * @return array
     */
    public function get_pending( $filter_type = '', $limit = 100, $offset = 0 ) {
        if ( ! $this->e_db ) {
            return [];
        }

        $sql = "SELECT * FROM espn_manual_verification WHERE status = 'pending'";

        if ( $filter_type ) {
            $sql .= " AND data_type = '" . $this->e_db->real_escape_string( $filter_type ) . "'";
        }

        $sql .= " ORDER BY league_code, season_year, data_type LIMIT ? OFFSET ?";

        $stmt = $this->e_db->prepare( $sql );
        $stmt->bind_param( 'ii', $limit, $offset );
        $stmt->execute();

        $result = $stmt->get_result();
        $items = [];

        while ( $row = $result->fetch_assoc() ) {
            $items[] = $row;
        }

        $stmt->close();
        return $items;
    }

    /**
     * Get verification counts by status
     *
     * @return array
     */
    public function get_counts() {
        if ( ! $this->e_db ) {
            return [];
        }

        $sql = "SELECT
            status,
            data_type,
            COUNT(*) as count
            FROM espn_manual_verification
            GROUP BY status, data_type
            ORDER BY status, data_type";

        $result = $this->e_db->query( $sql );
        $counts = [
            'pending' => [],
            'verified_exists' => [],
            'verified_missing' => [],
            'skipped' => [],
            'totals' => [
                'pending' => 0,
                'verified_exists' => 0,
                'verified_missing' => 0,
                'skipped' => 0,
            ],
        ];

        while ( $row = $result->fetch_assoc() ) {
            $counts[ $row['status'] ][ $row['data_type'] ] = (int) $row['count'];
            $counts['totals'][ $row['status'] ] += (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Update verification status
     *
     * @param int    $id     Record ID
     * @param string $status New status
     * @param string $notes  Optional notes
     * @return bool
     */
    public function update_status( $id, $status, $notes = '' ) {
        if ( ! $this->e_db ) {
            return false;
        }

        $valid_statuses = [ 'pending', 'verified_exists', 'verified_missing', 'skipped' ];
        if ( ! in_array( $status, $valid_statuses ) ) {
            return false;
        }

        $sql = "UPDATE espn_manual_verification
            SET status = ?, verified_at = NOW(), notes = ?
            WHERE id = ?";

        $stmt = $this->e_db->prepare( $sql );
        $stmt->bind_param( 'ssi', $status, $notes, $id );
        $result = $stmt->execute();
        $stmt->close();

        // If verified as existing, update espn_availability table
        if ( $result && $status === 'verified_exists' ) {
            $this->update_availability_from_verification( $id );
        }

        return $result;
    }

    /**
     * Bulk update verification status
     *
     * @param array  $ids    Record IDs
     * @param string $status New status
     * @return int Number of records updated
     */
    public function bulk_update_status( $ids, $status ) {
        if ( ! $this->e_db || empty( $ids ) ) {
            return 0;
        }

        $valid_statuses = [ 'pending', 'verified_exists', 'verified_missing', 'skipped' ];
        if ( ! in_array( $status, $valid_statuses ) ) {
            return 0;
        }

        $ids = array_map( 'intval', $ids );
        $id_list = implode( ',', $ids );

        $sql = "UPDATE espn_manual_verification
            SET status = ?, verified_at = NOW()
            WHERE id IN ({$id_list})";

        $stmt = $this->e_db->prepare( $sql );
        $stmt->bind_param( 's', $status );
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        // Update availability for verified items
        if ( $status === 'verified_exists' ) {
            foreach ( $ids as $id ) {
                $this->update_availability_from_verification( $id );
            }
        }

        return $affected;
    }

    /**
     * Update espn_availability based on manual verification
     *
     * @param int $verification_id
     */
    private function update_availability_from_verification( $verification_id ) {
        if ( ! $this->e_db ) {
            return;
        }

        // Get the verification record
        $stmt = $this->e_db->prepare( "SELECT league_code, season_year, data_type FROM espn_manual_verification WHERE id = ?" );
        $stmt->bind_param( 'i', $verification_id );
        $stmt->execute();
        $result = $stmt->get_result();
        $record = $result->fetch_assoc();
        $stmt->close();

        if ( ! $record ) {
            return;
        }

        // Map data_type to column name
        $column_map = [
            'plays' => 'plays_available',
            'teams' => 'teams_available',
            'players' => 'players_available',
            'standings' => 'standings_available',
            'lineups' => 'lineups_available',
            'commentary' => 'commentary_available',
            'key_events' => 'key_events_available',
            'team_stats' => 'team_stats_available',
            'player_stats' => 'player_stats_available',
        ];

        $column = isset( $column_map[ $record['data_type'] ] ) ? $column_map[ $record['data_type'] ] : null;
        if ( ! $column ) {
            return;
        }

        // Update the availability table
        $sql = "UPDATE espn_availability SET {$column} = 1 WHERE league_code = ? AND season_year = ?";
        $stmt = $this->e_db->prepare( $sql );
        $stmt->bind_param( 'si', $record['league_code'], $record['season_year'] );
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Clear all verification records
     *
     * @return bool
     */
    public function clear_all() {
        if ( ! $this->e_db ) {
            return false;
        }

        return $this->e_db->query( 'TRUNCATE TABLE espn_manual_verification' );
    }

    /**
     * Get data types that have pending verifications
     *
     * @return array
     */
    public function get_pending_data_types() {
        if ( ! $this->e_db ) {
            return [];
        }

        $sql = "SELECT DISTINCT data_type, COUNT(*) as count
            FROM espn_manual_verification
            WHERE status = 'pending'
            GROUP BY data_type
            ORDER BY count DESC";

        $result = $this->e_db->query( $sql );
        $types = [];

        while ( $row = $result->fetch_assoc() ) {
            $types[ $row['data_type'] ] = (int) $row['count'];
        }

        return $types;
    }
}
