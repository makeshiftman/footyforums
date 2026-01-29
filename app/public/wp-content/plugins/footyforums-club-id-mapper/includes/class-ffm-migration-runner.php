<?php
/**
 * FFM Migration Runner
 *
 * Handles database migrations for the Club ID Mapper plugin.
 * Supports up/down migrations, state tracking, and rollback capability.
 *
 * @package FootyForums_Club_ID_Mapper
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/db-footyforums-data.php';

/**
 * Class FFM_Migration_Runner
 *
 * Manages database schema migrations with full up/down support.
 */
class FFM_Migration_Runner {

	/**
	 * Option name for storing completed migrations.
	 *
	 * @var string
	 */
	const MIGRATIONS_OPTION = 'ffm_migrations_run';

	/**
	 * Directory containing migration files.
	 *
	 * @var string
	 */
	private $migrations_dir;

	/**
	 * Database connection instance.
	 *
	 * @var wpdb
	 */
	private $db;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->migrations_dir = __DIR__ . '/migrations';
		$this->db = kt_ffdb();
	}

	/**
	 * Get list of completed migrations from database option.
	 *
	 * @return array Array of completed migration IDs.
	 */
	public function get_completed_migrations(): array {
		$completed = get_option( self::MIGRATIONS_OPTION, array() );
		return is_array( $completed ) ? $completed : array();
	}

	/**
	 * Save completed migrations to database option.
	 *
	 * @param array $migrations Array of migration IDs.
	 * @return bool Whether the option was updated.
	 */
	private function save_completed_migrations( array $migrations ): bool {
		return update_option( self::MIGRATIONS_OPTION, $migrations );
	}

	/**
	 * Discover all migration files in the migrations directory.
	 *
	 * @return array Array of migration file paths sorted by ID.
	 */
	public function discover_migrations(): array {
		$migrations = array();

		if ( ! is_dir( $this->migrations_dir ) ) {
			return $migrations;
		}

		$files = scandir( $this->migrations_dir );
		if ( false === $files ) {
			return $migrations;
		}

		foreach ( $files as $file ) {
			// Match pattern: NNN-*.php (e.g., 001-add-provider-columns.php)
			if ( preg_match( '/^(\d{3}-.+)\.php$/', $file, $matches ) ) {
				$migration_id = $matches[1];
				$migrations[ $migration_id ] = $this->migrations_dir . '/' . $file;
			}
		}

		// Sort by migration ID
		ksort( $migrations );

		return $migrations;
	}

	/**
	 * Get pending migrations (discovered but not completed).
	 *
	 * @return array Array of pending migration IDs to file paths.
	 */
	public function get_pending_migrations(): array {
		$all_migrations = $this->discover_migrations();
		$completed = $this->get_completed_migrations();

		return array_diff_key( $all_migrations, array_flip( $completed ) );
	}

	/**
	 * Load a migration file and return its definition.
	 *
	 * @param string $file_path Path to the migration file.
	 * @return array|false Migration definition array or false on failure.
	 */
	private function load_migration( string $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$migration = require $file_path;

		if ( ! is_array( $migration ) ) {
			return false;
		}

		// Validate required keys
		if ( ! isset( $migration['id'], $migration['up'], $migration['down'] ) ) {
			return false;
		}

		if ( ! is_callable( $migration['up'] ) || ! is_callable( $migration['down'] ) ) {
			return false;
		}

		return $migration;
	}

	/**
	 * Run all pending migrations.
	 *
	 * @return array Array with 'success' (bool), 'executed' (array of migration IDs), and 'error' (string if failed).
	 */
	public function run_pending(): array {
		$executed = array();
		$pending = $this->get_pending_migrations();
		$completed = $this->get_completed_migrations();

		if ( empty( $pending ) ) {
			return array(
				'success'  => true,
				'executed' => array(),
				'error'    => '',
			);
		}

		foreach ( $pending as $migration_id => $file_path ) {
			$migration = $this->load_migration( $file_path );

			if ( false === $migration ) {
				return array(
					'success'  => false,
					'executed' => $executed,
					'error'    => "Failed to load migration file: {$migration_id}",
				);
			}

			try {
				error_log( "FFM Migration: Running up() for {$migration_id}" );
				$result = call_user_func( $migration['up'], $this->db );

				if ( true === $result ) {
					$completed[] = $migration_id;
					$this->save_completed_migrations( $completed );
					$executed[] = $migration_id;
					error_log( "FFM Migration: Completed {$migration_id}" );
				} else {
					$error_msg = is_string( $result ) ? $result : 'Unknown error';
					error_log( "FFM Migration: Failed {$migration_id} - {$error_msg}" );
					return array(
						'success'  => false,
						'executed' => $executed,
						'error'    => "{$migration_id}: {$error_msg}",
					);
				}
			} catch ( Exception $e ) {
				error_log( "FFM Migration: Exception in {$migration_id} - " . $e->getMessage() );
				return array(
					'success'  => false,
					'executed' => $executed,
					'error'    => "{$migration_id}: " . $e->getMessage(),
				);
			}
		}

		return array(
			'success'  => true,
			'executed' => $executed,
			'error'    => '',
		);
	}

	/**
	 * Rollback a specific migration.
	 *
	 * @param string $migration_id The migration ID to rollback.
	 * @return array Array with 'success' (bool) and 'error' (string if failed).
	 */
	public function rollback( string $migration_id ): array {
		$completed = $this->get_completed_migrations();

		if ( ! in_array( $migration_id, $completed, true ) ) {
			return array(
				'success' => false,
				'error'   => 'Migration not found in completed list.',
			);
		}

		$all_migrations = $this->discover_migrations();

		if ( ! isset( $all_migrations[ $migration_id ] ) ) {
			return array(
				'success' => false,
				'error'   => 'Migration file not found.',
			);
		}

		$migration = $this->load_migration( $all_migrations[ $migration_id ] );

		if ( false === $migration ) {
			return array(
				'success' => false,
				'error'   => 'Failed to load migration file.',
			);
		}

		try {
			error_log( "FFM Migration: Running down() for {$migration_id}" );
			$result = call_user_func( $migration['down'], $this->db );

			if ( true === $result ) {
				// Remove from completed list
				$completed = array_diff( $completed, array( $migration_id ) );
				$this->save_completed_migrations( $completed );
				error_log( "FFM Migration: Rolled back {$migration_id}" );
				return array(
					'success' => true,
					'error'   => '',
				);
			} else {
				$error_msg = is_string( $result ) ? $result : 'Unknown error';
				error_log( "FFM Migration: Rollback failed {$migration_id} - {$error_msg}" );
				return array(
					'success' => false,
					'error'   => $error_msg,
				);
			}
		} catch ( Exception $e ) {
			error_log( "FFM Migration: Exception in rollback {$migration_id} - " . $e->getMessage() );
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Get migration status report.
	 *
	 * @return array Status array with completed, pending, and total counts.
	 */
	public function get_status(): array {
		$all_migrations = $this->discover_migrations();
		$completed = $this->get_completed_migrations();
		$pending = $this->get_pending_migrations();

		return array(
			'completed'     => $completed,
			'pending'       => array_keys( $pending ),
			'total'         => count( $all_migrations ),
			'completed_count' => count( $completed ),
			'pending_count' => count( $pending ),
		);
	}

	/**
	 * Verify schema by checking if expected columns/tables exist.
	 *
	 * @return array Verification results.
	 */
	public function verify_schema(): array {
		$results = array();

		// Check for new provider columns in clubs table
		$expected_columns = array( 'fmob_id', 'sm_id', 'is_id', 'sc_id', 'fmgr_id', 'id_source' );

		foreach ( $expected_columns as $column ) {
			$exists = $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(*) FROM information_schema.COLUMNS
					 WHERE TABLE_SCHEMA = %s
					 AND TABLE_NAME = 'clubs'
					 AND COLUMN_NAME = %s",
					$this->db->dbname,
					$column
				)
			);
			$results[ "clubs.{$column}" ] = $exists > 0 ? 'exists' : 'missing';
		}

		// Check for club_aliases table
		$table_exists = $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLES
				 WHERE TABLE_SCHEMA = %s
				 AND TABLE_NAME = 'club_aliases'",
				$this->db->dbname
			)
		);
		$results['club_aliases table'] = $table_exists > 0 ? 'exists' : 'missing';

		return $results;
	}
}
