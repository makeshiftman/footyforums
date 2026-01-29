<?php
/**
 * Migration 001: Add Provider Columns and Club Aliases Table
 *
 * UP:
 * - Adds 5 new provider ID columns to clubs table (fmob_id, sm_id, is_id, sc_id, fmgr_id)
 * - Adds id_source column for provenance tracking
 * - Creates club_aliases table for name variations
 *
 * DOWN:
 * - Drops club_aliases table
 * - Drops the 6 new columns from clubs table
 *
 * @package FootyForums_Club_ID_Mapper
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id' => '001-add-provider-columns',

	/**
	 * Run the migration (up).
	 *
	 * @param wpdb $db Database connection.
	 * @return bool|string True on success, error message on failure.
	 */
	'up' => function ( $db ) {
		$errors = array();

		// Helper to check if column exists
		$column_exists = function ( $table, $column ) use ( $db ) {
			$result = $db->get_var(
				$db->prepare(
					"SELECT COUNT(*) FROM information_schema.COLUMNS
					 WHERE TABLE_SCHEMA = %s
					 AND TABLE_NAME = %s
					 AND COLUMN_NAME = %s",
					$db->dbname,
					$table,
					$column
				)
			);
			return $result > 0;
		};

		// Define new columns to add
		$new_columns = array(
			'fmob_id'   => "VARCHAR(50) DEFAULT NULL COMMENT 'FotMob ID'",
			'sm_id'     => "VARCHAR(50) DEFAULT NULL COMMENT 'SportMonks ID'",
			'is_id'     => "VARCHAR(50) DEFAULT NULL COMMENT 'InStat ID'",
			'sc_id'     => "VARCHAR(50) DEFAULT NULL COMMENT 'SkillCorner ID'",
			'fmgr_id'   => "VARCHAR(50) DEFAULT NULL COMMENT 'Football Manager ID'",
			'id_source' => "VARCHAR(50) DEFAULT NULL COMMENT 'Source of ID mappings (csv_import, manual, etc.)'",
		);

		// Add each column if it doesn't exist
		foreach ( $new_columns as $column => $definition ) {
			if ( ! $column_exists( 'clubs', $column ) ) {
				$sql = "ALTER TABLE `clubs` ADD COLUMN `{$column}` {$definition}";
				error_log( "FFM Migration 001: Adding column {$column} to clubs table" );

				$result = $db->query( $sql );

				if ( false === $result ) {
					$errors[] = "Failed to add column {$column}: " . $db->last_error;
					error_log( "FFM Migration 001: Failed to add column {$column} - " . $db->last_error );
				} else {
					error_log( "FFM Migration 001: Added column {$column}" );
				}
			} else {
				error_log( "FFM Migration 001: Column {$column} already exists, skipping" );
			}
		}

		// Create club_aliases table
		$table_exists = $db->get_var(
			$db->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLES
				 WHERE TABLE_SCHEMA = %s
				 AND TABLE_NAME = 'club_aliases'",
				$db->dbname
			)
		);

		if ( ! $table_exists ) {
			$sql = "CREATE TABLE `club_aliases` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`club_id` INT NOT NULL,
				`provider` VARCHAR(50) NOT NULL COMMENT 'Provider code (w, t, sf, fmob, sm, etc.)',
				`alias_name` VARCHAR(255) NOT NULL COMMENT 'Name variation used by this provider',
				`date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `idx_club_provider` (`club_id`, `provider`),
				KEY `idx_alias_lookup` (`alias_name`, `provider`),
				CONSTRAINT `fk_club_aliases_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Name variations for clubs per provider'";

			error_log( 'FFM Migration 001: Creating club_aliases table' );

			$result = $db->query( $sql );

			if ( false === $result ) {
				$errors[] = 'Failed to create club_aliases table: ' . $db->last_error;
				error_log( 'FFM Migration 001: Failed to create club_aliases table - ' . $db->last_error );
			} else {
				error_log( 'FFM Migration 001: Created club_aliases table' );
			}
		} else {
			error_log( 'FFM Migration 001: club_aliases table already exists, skipping' );
		}

		if ( ! empty( $errors ) ) {
			return implode( '; ', $errors );
		}

		return true;
	},

	/**
	 * Reverse the migration (down).
	 *
	 * @param wpdb $db Database connection.
	 * @return bool|string True on success, error message on failure.
	 */
	'down' => function ( $db ) {
		$errors = array();

		// Drop club_aliases table first (has foreign key to clubs)
		$table_exists = $db->get_var(
			$db->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLES
				 WHERE TABLE_SCHEMA = %s
				 AND TABLE_NAME = 'club_aliases'",
				$db->dbname
			)
		);

		if ( $table_exists ) {
			error_log( 'FFM Migration 001 DOWN: Dropping club_aliases table' );

			$result = $db->query( 'DROP TABLE `club_aliases`' );

			if ( false === $result ) {
				$errors[] = 'Failed to drop club_aliases table: ' . $db->last_error;
				error_log( 'FFM Migration 001 DOWN: Failed to drop club_aliases table - ' . $db->last_error );
			} else {
				error_log( 'FFM Migration 001 DOWN: Dropped club_aliases table' );
			}
		}

		// Helper to check if column exists
		$column_exists = function ( $table, $column ) use ( $db ) {
			$result = $db->get_var(
				$db->prepare(
					"SELECT COUNT(*) FROM information_schema.COLUMNS
					 WHERE TABLE_SCHEMA = %s
					 AND TABLE_NAME = %s
					 AND COLUMN_NAME = %s",
					$db->dbname,
					$table,
					$column
				)
			);
			return $result > 0;
		};

		// Drop added columns from clubs table
		$columns_to_drop = array( 'fmob_id', 'sm_id', 'is_id', 'sc_id', 'fmgr_id', 'id_source' );

		foreach ( $columns_to_drop as $column ) {
			if ( $column_exists( 'clubs', $column ) ) {
				$sql = "ALTER TABLE `clubs` DROP COLUMN `{$column}`";
				error_log( "FFM Migration 001 DOWN: Dropping column {$column}" );

				$result = $db->query( $sql );

				if ( false === $result ) {
					$errors[] = "Failed to drop column {$column}: " . $db->last_error;
					error_log( "FFM Migration 001 DOWN: Failed to drop column {$column} - " . $db->last_error );
				} else {
					error_log( "FFM Migration 001 DOWN: Dropped column {$column}" );
				}
			} else {
				error_log( "FFM Migration 001 DOWN: Column {$column} doesn't exist, skipping" );
			}
		}

		if ( ! empty( $errors ) ) {
			return implode( '; ', $errors );
		}

		return true;
	},
);
