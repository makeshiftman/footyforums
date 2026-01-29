<?php
/**
 * Uncertain Queue for Club ID Mapping
 *
 * Stores uncertain and no_match results from the matching engine for manual review.
 * Works with Phase 7 (Review UI) and Phase 8 (Import Execution).
 *
 * @package FootyForums_Club_ID_Mapper
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/db-footyforums-data.php';

/**
 * FFM_Uncertain_Queue class.
 *
 * Handles queuing of uncertain/no_match results for manual review workflow.
 */
class FFM_Uncertain_Queue {

	/**
	 * Database connection.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Table name for the review queue.
	 *
	 * @var string
	 */
	const TABLE_NAME = 'csv_import_review_queue';

	/**
	 * Migration ID for table creation.
	 *
	 * @var string
	 */
	const MIGRATION_ID = '002-csv-import-review-queue';

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $db Optional database connection. Uses kt_ffdb() if not provided.
	 */
	public function __construct( $db = null ) {
		$this->db = $db ?? kt_ffdb();
	}

	/**
	 * Check if the review queue table exists.
	 *
	 * @return bool True if table exists.
	 */
	public function table_exists() {
		$table_exists = $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLES
				 WHERE TABLE_SCHEMA = %s
				 AND TABLE_NAME = %s",
				$this->db->dbname,
				self::TABLE_NAME
			)
		);
		return $table_exists > 0;
	}

	/**
	 * Ensure the review queue table exists, creating it if necessary.
	 *
	 * @return bool True if table exists after method call.
	 */
	public function ensure_table_exists() {
		if ( $this->table_exists() ) {
			return true;
		}

		return $this->create_table();
	}

	/**
	 * Create the csv_import_review_queue table.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function create_table() {
		$sql = "CREATE TABLE `" . self::TABLE_NAME . "` (
			`id` INT NOT NULL AUTO_INCREMENT,
			`csv_row_index` INT NOT NULL COMMENT 'Original row number in CSV',
			`csv_name` VARCHAR(255) NOT NULL COMMENT 'Primary name from CSV for display',
			`csv_country` VARCHAR(100) DEFAULT NULL COMMENT 'Country from CSV',
			`csv_row_data` TEXT NOT NULL COMMENT 'JSON-encoded full CSV row for provider IDs extraction',
			`match_status` VARCHAR(50) NOT NULL COMMENT 'uncertain or no_match',
			`confidence` VARCHAR(20) NOT NULL COMMENT 'medium, low, or none',
			`candidate_club_ids` TEXT DEFAULT NULL COMMENT 'JSON array of candidate club IDs',
			`review_status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected, skipped',
			`approved_club_id` INT DEFAULT NULL COMMENT 'Club ID chosen during review',
			`reviewed_at` DATETIME DEFAULT NULL COMMENT 'When review was completed',
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `idx_review_status` (`review_status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Queue for manual review of uncertain CSV matches'";

		error_log( 'FFM_Uncertain_Queue: Creating csv_import_review_queue table' );

		$result = $this->db->query( $sql );

		if ( false === $result ) {
			error_log( 'FFM_Uncertain_Queue: Failed to create table - ' . $this->db->last_error );
			return false;
		}

		error_log( 'FFM_Uncertain_Queue: Created csv_import_review_queue table' );
		return true;
	}

	/**
	 * Queue a match result for manual review.
	 *
	 * Only queues results with status 'uncertain' or 'no_match'.
	 *
	 * @param array          $match_result Result from FFM_Matching_Engine::match_csv_row().
	 * @param array          $csv_row      Full CSV row array.
	 * @param FFM_CSV_Parser $csv_parser   CSV parser instance for extracting data.
	 * @return int|false Inserted ID on success, false on failure or if not queueable.
	 */
	public function queue_match( $match_result, $csv_row, $csv_parser ) {
		// Only queue uncertain or no_match status.
		$status = isset( $match_result['status'] ) ? $match_result['status'] : '';
		$valid_statuses = array( 'uncertain', 'no_match' );

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		// Extract data from CSV row using parser.
		$csv_name    = $csv_parser->get_primary_name( $csv_row );
		$csv_country = $csv_parser->get_country( $csv_row );

		// Get row index if provided in match_result.
		$row_index = isset( $match_result['row_index'] ) ? (int) $match_result['row_index'] : 0;

		// Get confidence level.
		$confidence = isset( $match_result['confidence'] ) ? $match_result['confidence'] : 'none';

		// Extract candidate club IDs as JSON array.
		$candidate_ids = array();
		if ( isset( $match_result['candidates'] ) && is_array( $match_result['candidates'] ) ) {
			foreach ( $match_result['candidates'] as $candidate ) {
				if ( isset( $candidate['id'] ) ) {
					$candidate_ids[] = (int) $candidate['id'];
				}
			}
		}
		$candidate_ids_json = ! empty( $candidate_ids ) ? wp_json_encode( $candidate_ids ) : null;

		// Encode full CSV row data for later provider ID extraction.
		$csv_row_json = wp_json_encode( $csv_row );

		// Insert into queue.
		$sql = $this->db->prepare(
			"INSERT INTO `" . self::TABLE_NAME . "`
			(csv_row_index, csv_name, csv_country, csv_row_data, match_status, confidence, candidate_club_ids)
			VALUES (%d, %s, %s, %s, %s, %s, %s)",
			$row_index,
			$csv_name,
			$csv_country,
			$csv_row_json,
			$status,
			$confidence,
			$candidate_ids_json
		);

		$result = $this->db->query( $sql );

		if ( false === $result ) {
			error_log( 'FFM_Uncertain_Queue: Failed to insert row - ' . $this->db->last_error );
			return false;
		}

		return $this->db->insert_id;
	}

	/**
	 * Queue all uncertain matches from batch match results.
	 *
	 * Iterates through match results from FFM_Matching_Engine::match_all_csv_rows()
	 * and queues each uncertain/no_match result.
	 *
	 * @param array          $match_results Output from FFM_Matching_Engine::match_all_csv_rows().
	 * @param FFM_CSV_Parser $csv_parser    Loaded CSV parser instance.
	 * @return array Statistics: queued, skipped, errors, total.
	 */
	public function queue_all_uncertain( $match_results, $csv_parser ) {
		$stats = array(
			'queued'  => 0,
			'skipped' => 0,
			'errors'  => 0,
			'total'   => 0,
		);

		// Check if results array exists.
		if ( ! isset( $match_results['results'] ) || ! is_array( $match_results['results'] ) ) {
			return $stats;
		}

		// Ensure table exists before processing.
		if ( ! $this->ensure_table_exists() ) {
			error_log( 'FFM_Uncertain_Queue: Failed to ensure table exists' );
			$stats['errors'] = count( $match_results['results'] );
			$stats['total']  = count( $match_results['results'] );
			return $stats;
		}

		$results  = $match_results['results'];
		$csv_rows = $csv_parser->get_rows();

		$stats['total'] = count( $results );

		foreach ( $results as $match_result ) {
			// Get the original CSV row using row_index.
			$row_index = isset( $match_result['row_index'] ) ? (int) $match_result['row_index'] : -1;

			if ( $row_index < 0 || ! isset( $csv_rows[ $row_index ] ) ) {
				$stats['errors']++;
				continue;
			}

			$csv_row = $csv_rows[ $row_index ];
			$status  = isset( $match_result['status'] ) ? $match_result['status'] : '';

			// Skip confident matches (exact_match or alias_match).
			if ( in_array( $status, array( 'exact_match', 'alias_match' ), true ) ) {
				$stats['skipped']++;
				continue;
			}

			// Queue uncertain/no_match results.
			$insert_id = $this->queue_match( $match_result, $csv_row, $csv_parser );

			if ( false === $insert_id ) {
				$stats['errors']++;
			} else {
				$stats['queued']++;
			}
		}

		return $stats;
	}

	/**
	 * Get pending review items with candidate club details.
	 *
	 * @param int $limit  Max items to return.
	 * @param int $offset Offset for pagination.
	 * @return array Array of pending items with resolved candidate club details.
	 */
	public function get_pending_items( $limit = 50, $offset = 0 ) {
		$limit  = max( 1, (int) $limit );
		$offset = max( 0, (int) $offset );

		$sql = $this->db->prepare(
			"SELECT id, csv_row_index, csv_name, csv_country, csv_row_data, match_status, confidence, candidate_club_ids, created_at
			 FROM `" . self::TABLE_NAME . "`
			 WHERE review_status = 'pending'
			 ORDER BY id ASC
			 LIMIT %d OFFSET %d",
			$limit,
			$offset
		);

		$items = $this->db->get_results( $sql, ARRAY_A );

		if ( empty( $items ) ) {
			return array();
		}

		// Resolve candidate club details for each item.
		foreach ( $items as &$item ) {
			$item['candidates'] = array();

			if ( ! empty( $item['candidate_club_ids'] ) ) {
				$candidate_ids = json_decode( $item['candidate_club_ids'], true );

				if ( is_array( $candidate_ids ) && ! empty( $candidate_ids ) ) {
					// Fetch club details for all candidate IDs.
					$placeholders = implode( ',', array_fill( 0, count( $candidate_ids ), '%d' ) );
					$club_sql     = $this->db->prepare(
						"SELECT id, canonical_name, competition_code FROM clubs WHERE id IN ({$placeholders})",
						...$candidate_ids
					);
					$clubs        = $this->db->get_results( $club_sql, ARRAY_A );

					if ( $clubs ) {
						// Index by ID for easy lookup.
						$clubs_by_id = array();
						foreach ( $clubs as $club ) {
							$clubs_by_id[ (int) $club['id'] ] = $club;
						}

						// Build candidates array in same order as candidate_ids.
						foreach ( $candidate_ids as $cid ) {
							if ( isset( $clubs_by_id[ (int) $cid ] ) ) {
								$item['candidates'][] = $clubs_by_id[ (int) $cid ];
							}
						}
					}
				}
			}
		}
		unset( $item );

		return $items;
	}

	/**
	 * Get count of pending review items.
	 *
	 * @return int Number of pending items.
	 */
	public function get_pending_count() {
		$count = $this->db->get_var(
			"SELECT COUNT(*) FROM `" . self::TABLE_NAME . "` WHERE review_status = 'pending'"
		);
		return (int) $count;
	}

	/**
	 * Approve a review item with selected club ID.
	 *
	 * @param int $queue_id Queue item ID.
	 * @param int $club_id  Selected club ID.
	 * @return bool True on success, false on failure.
	 */
	public function approve_item( $queue_id, $club_id ) {
		$queue_id = (int) $queue_id;
		$club_id  = (int) $club_id;

		if ( $queue_id <= 0 || $club_id <= 0 ) {
			return false;
		}

		$sql = $this->db->prepare(
			"UPDATE `" . self::TABLE_NAME . "`
			 SET review_status = 'approved', approved_club_id = %d, reviewed_at = NOW()
			 WHERE id = %d",
			$club_id,
			$queue_id
		);

		$result = $this->db->query( $sql );
		return false !== $result && $result > 0;
	}

	/**
	 * Reject a review item (no valid match).
	 *
	 * @param int $queue_id Queue item ID.
	 * @return bool True on success, false on failure.
	 */
	public function reject_item( $queue_id ) {
		$queue_id = (int) $queue_id;

		if ( $queue_id <= 0 ) {
			return false;
		}

		$sql = $this->db->prepare(
			"UPDATE `" . self::TABLE_NAME . "`
			 SET review_status = 'rejected', reviewed_at = NOW()
			 WHERE id = %d",
			$queue_id
		);

		$result = $this->db->query( $sql );
		return false !== $result && $result > 0;
	}

	/**
	 * Skip a review item for later.
	 *
	 * @param int $queue_id Queue item ID.
	 * @return bool True on success, false on failure.
	 */
	public function skip_item( $queue_id ) {
		$queue_id = (int) $queue_id;

		if ( $queue_id <= 0 ) {
			return false;
		}

		$sql = $this->db->prepare(
			"UPDATE `" . self::TABLE_NAME . "`
			 SET review_status = 'skipped', reviewed_at = NOW()
			 WHERE id = %d",
			$queue_id
		);

		$result = $this->db->query( $sql );
		return false !== $result && $result > 0;
	}

	/**
	 * Clear all pending items from the queue.
	 *
	 * Called at the start of each import to prevent duplicates.
	 * Only removes pending items - preserves approved/rejected/skipped for audit.
	 *
	 * @return int Number of rows deleted.
	 */
	public function clear_pending() {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		$result = $this->db->query(
			"DELETE FROM `" . self::TABLE_NAME . "` WHERE review_status = 'pending'"
		);

		return false !== $result ? $result : 0;
	}
}
