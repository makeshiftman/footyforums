<?php
/**
 * Auto-Applier for Club ID Mapping
 *
 * Applies confident matches (exact_match/alias_match with high confidence)
 * by updating provider IDs in clubs table and inserting name variations
 * into club_aliases table.
 *
 * @package FootyForums_Club_ID_Mapper
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/db-footyforums-data.php';

/**
 * FFM_Auto_Applier class.
 *
 * Handles automatic application of confident match results from the matching engine.
 */
class FFM_Auto_Applier {

	/**
	 * Database connection.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $db Optional database connection. Uses kt_ffdb() if not provided.
	 */
	public function __construct( $db = null ) {
		$this->db = $db ?? kt_ffdb();
	}

	/**
	 * Apply a single match result to the database.
	 *
	 * Only applies exact_match or alias_match with high confidence.
	 * Updates provider IDs in clubs table and inserts name variations into club_aliases.
	 *
	 * @param array          $match_result Result from FFM_Matching_Engine::match_csv_row().
	 * @param array          $csv_row      Original CSV row data.
	 * @param FFM_CSV_Parser $csv_parser   CSV parser instance for extracting data.
	 * @return array Result with keys: applied, club_id, provider_ids_updated, aliases_inserted, reason.
	 */
	public function apply_match( $match_result, $csv_row, $csv_parser ) {
		// Default result structure.
		$result = array(
			'applied'              => false,
			'club_id'              => null,
			'provider_ids_updated' => 0,
			'aliases_inserted'     => 0,
			'reason'               => '',
		);

		// Validate match is confident enough to auto-apply.
		$valid_statuses = array( 'exact_match', 'alias_match' );
		$status         = isset( $match_result['status'] ) ? $match_result['status'] : '';
		$confidence     = isset( $match_result['confidence'] ) ? $match_result['confidence'] : '';

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$result['reason'] = 'Not a confident match (status: ' . $status . ')';
			return $result;
		}

		if ( 'high' !== $confidence ) {
			$result['reason'] = 'Not a confident match (confidence: ' . $confidence . ')';
			return $result;
		}

		// Extract club_id from match result.
		$club_id = isset( $match_result['club_id'] ) ? (int) $match_result['club_id'] : 0;
		if ( $club_id <= 0 ) {
			$result['reason'] = 'Invalid club_id in match result';
			return $result;
		}

		$result['club_id'] = $club_id;

		// Update provider IDs in clubs table.
		$provider_ids_updated        = $this->update_provider_ids( $club_id, $csv_row, $csv_parser );
		$result['provider_ids_updated'] = $provider_ids_updated;

		// Insert name variations into club_aliases table.
		$aliases_inserted        = $this->insert_aliases( $club_id, $csv_row, $csv_parser );
		$result['aliases_inserted'] = $aliases_inserted;

		$result['applied'] = true;
		$result['reason']  = 'Match applied successfully';

		return $result;
	}

	/**
	 * Update provider IDs in clubs table.
	 *
	 * Sets provider ID columns (w_id, t_id, sf_id, etc.) and marks id_source as 'csv_import'.
	 * Only updates columns that have values in the CSV row.
	 *
	 * @param int            $club_id    Club ID to update.
	 * @param array          $csv_row    CSV row data.
	 * @param FFM_CSV_Parser $csv_parser CSV parser instance.
	 * @return int Number of provider ID columns updated.
	 */
	private function update_provider_ids( $club_id, $csv_row, $csv_parser ) {
		$provider_ids = $csv_parser->get_provider_ids( $csv_row );

		if ( empty( $provider_ids ) ) {
			return 0;
		}

		// Build SET clause for UPDATE query.
		$set_parts  = array();
		$set_values = array();

		foreach ( $provider_ids as $db_column => $value ) {
			// Sanitize column name (should be one of our known columns).
			$allowed_columns = array( 'w_id', 't_id', 'sf_id', 'o_id', 'fmob_id', 'sm_id', 'is_id', 'sc_id', 'fmgr_id' );
			if ( ! in_array( $db_column, $allowed_columns, true ) ) {
				continue;
			}

			$set_parts[]  = "`{$db_column}` = %s";
			$set_values[] = $value;
		}

		if ( empty( $set_parts ) ) {
			return 0;
		}

		// Add id_source for provenance tracking.
		$set_parts[]  = "`id_source` = %s";
		$set_values[] = 'csv_import';

		// Add club_id for WHERE clause.
		$set_values[] = $club_id;

		$sql = $this->db->prepare(
			'UPDATE clubs SET ' . implode( ', ', $set_parts ) . ' WHERE id = %d',
			$set_values
		);

		$this->db->query( $sql );

		// Return count of provider ID columns updated (excluding id_source).
		return count( $set_parts ) - 1;
	}

	/**
	 * Insert name variations into club_aliases table.
	 *
	 * Uses INSERT IGNORE to avoid duplicates. Sets provider to 'csv_import'.
	 *
	 * @param int            $club_id    Club ID to associate aliases with.
	 * @param array          $csv_row    CSV row data.
	 * @param FFM_CSV_Parser $csv_parser CSV parser instance.
	 * @return int Number of aliases inserted.
	 */
	private function insert_aliases( $club_id, $csv_row, $csv_parser ) {
		$name_variations = $csv_parser->get_name_variations( $csv_row );

		if ( empty( $name_variations ) ) {
			return 0;
		}

		$inserted = 0;

		foreach ( $name_variations as $alias_name ) {
			if ( '' === trim( $alias_name ) ) {
				continue;
			}

			// Use INSERT IGNORE to skip duplicates.
			$sql = $this->db->prepare(
				"INSERT IGNORE INTO club_aliases (club_id, provider, alias_name) VALUES (%d, %s, %s)",
				$club_id,
				'csv_import',
				$alias_name
			);

			$result = $this->db->query( $sql );

			// $result is the number of rows affected (1 if inserted, 0 if ignored).
			if ( $result > 0 ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	/**
	 * Apply all confident matches from batch match results.
	 *
	 * Iterates through match results from FFM_Matching_Engine::match_all_csv_rows()
	 * and applies each confident match (exact_match/alias_match with high confidence).
	 *
	 * @param array          $match_results Output from FFM_Matching_Engine::match_all_csv_rows().
	 * @param FFM_CSV_Parser $csv_parser    Loaded CSV parser instance.
	 * @return array Summary with keys: applied, skipped, errors, total.
	 */
	public function apply_all_confident( $match_results, $csv_parser ) {
		$summary = array(
			'applied' => 0,
			'skipped' => 0,
			'errors'  => array(),
			'total'   => 0,
		);

		// Check if results array exists.
		if ( ! isset( $match_results['results'] ) || ! is_array( $match_results['results'] ) ) {
			$summary['errors'][] = array(
				'row_index' => null,
				'message'   => 'Invalid match_results structure: missing results array',
			);
			return $summary;
		}

		$results = $match_results['results'];
		$summary['total'] = count( $results );

		// Get all CSV rows for access by row_index.
		$csv_rows = $csv_parser->get_rows();

		foreach ( $results as $match_result ) {
			// Get the original CSV row using row_index.
			$row_index = isset( $match_result['row_index'] ) ? (int) $match_result['row_index'] : -1;

			if ( $row_index < 0 || ! isset( $csv_rows[ $row_index ] ) ) {
				$summary['errors'][] = array(
					'row_index' => $row_index,
					'message'   => 'Invalid row_index in match result',
				);
				$summary['skipped']++;
				continue;
			}

			$csv_row = $csv_rows[ $row_index ];

			try {
				$apply_result = $this->apply_match( $match_result, $csv_row, $csv_parser );

				if ( $apply_result['applied'] ) {
					$summary['applied']++;
				} else {
					$summary['skipped']++;
				}
			} catch ( Exception $e ) {
				$summary['errors'][] = array(
					'row_index' => $row_index,
					'message'   => $e->getMessage(),
				);
				$summary['skipped']++;
			}
		}

		return $summary;
	}
}
