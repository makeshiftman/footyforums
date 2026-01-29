<?php
/**
 * Migration 002: Create CSV Import Review Queue Table
 *
 * UP:
 * - Creates csv_import_review_queue table for storing uncertain/no_match results
 *
 * DOWN:
 * - Drops csv_import_review_queue table
 *
 * @package FootyForums_Club_ID_Mapper
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id' => '002-csv-import-review-queue',

	/**
	 * Run the migration (up).
	 *
	 * @param wpdb $db Database connection.
	 * @return bool|string True on success, error message on failure.
	 */
	'up' => function ( $db ) {
		// Check if table already exists.
		$table_exists = $db->get_var(
			$db->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLES
				 WHERE TABLE_SCHEMA = %s
				 AND TABLE_NAME = 'csv_import_review_queue'",
				$db->dbname
			)
		);

		if ( $table_exists ) {
			error_log( 'FFM Migration 002: csv_import_review_queue table already exists, skipping' );
			return true;
		}

		$sql = "CREATE TABLE `csv_import_review_queue` (
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

		error_log( 'FFM Migration 002: Creating csv_import_review_queue table' );

		$result = $db->query( $sql );

		if ( false === $result ) {
			$error_msg = 'Failed to create csv_import_review_queue table: ' . $db->last_error;
			error_log( 'FFM Migration 002: ' . $error_msg );
			return $error_msg;
		}

		error_log( 'FFM Migration 002: Created csv_import_review_queue table' );
		return true;
	},

	/**
	 * Reverse the migration (down).
	 *
	 * @param wpdb $db Database connection.
	 * @return bool|string True on success, error message on failure.
	 */
	'down' => function ( $db ) {
		// Check if table exists.
		$table_exists = $db->get_var(
			$db->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLES
				 WHERE TABLE_SCHEMA = %s
				 AND TABLE_NAME = 'csv_import_review_queue'",
				$db->dbname
			)
		);

		if ( ! $table_exists ) {
			error_log( 'FFM Migration 002 DOWN: csv_import_review_queue table does not exist, skipping' );
			return true;
		}

		error_log( 'FFM Migration 002 DOWN: Dropping csv_import_review_queue table' );

		$result = $db->query( 'DROP TABLE `csv_import_review_queue`' );

		if ( false === $result ) {
			$error_msg = 'Failed to drop csv_import_review_queue table: ' . $db->last_error;
			error_log( 'FFM Migration 002 DOWN: ' . $error_msg );
			return $error_msg;
		}

		error_log( 'FFM Migration 002 DOWN: Dropped csv_import_review_queue table' );
		return true;
	},
);
