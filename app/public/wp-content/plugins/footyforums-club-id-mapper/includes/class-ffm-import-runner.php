<?php
/**
 * Import Runner for Club ID Mapping
 *
 * Orchestrates the full CSV import pipeline: parse -> match -> auto-apply confident -> queue uncertain.
 * Ties together CSV Parser, Matching Engine, Auto-Applier, and Uncertain Queue classes.
 *
 * @package FootyForums_Club_ID_Mapper
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/db-footyforums-data.php';

/**
 * FFM_Import_Runner class.
 *
 * Orchestrates the full import workflow and provides import status tracking.
 */
class FFM_Import_Runner {

	/**
	 * CSV Parser instance.
	 *
	 * @var FFM_CSV_Parser
	 */
	private $csv_parser;

	/**
	 * Matching Engine instance.
	 *
	 * @var FFM_Matching_Engine
	 */
	private $matching_engine;

	/**
	 * Auto-Applier instance.
	 *
	 * @var FFM_Auto_Applier
	 */
	private $auto_applier;

	/**
	 * Uncertain Queue instance.
	 *
	 * @var FFM_Uncertain_Queue
	 */
	private $uncertain_queue;

	/**
	 * WP option key for last import timestamp.
	 *
	 * @var string
	 */
	const OPTION_LAST_IMPORT_TIMESTAMP = 'ffm_last_import_timestamp';

	/**
	 * WP option key for last import results.
	 *
	 * @var string
	 */
	const OPTION_IMPORT_RESULTS = 'ffm_import_results';

	/**
	 * Constructor.
	 *
	 * @param FFM_CSV_Parser|null      $csv_parser      Optional CSV parser. Defaults to new instance.
	 * @param FFM_Matching_Engine|null $matching_engine Optional matching engine. Defaults to new instance.
	 * @param FFM_Auto_Applier|null    $auto_applier    Optional auto-applier. Defaults to new instance.
	 * @param FFM_Uncertain_Queue|null $uncertain_queue Optional uncertain queue. Defaults to new instance.
	 */
	public function __construct(
		$csv_parser = null,
		$matching_engine = null,
		$auto_applier = null,
		$uncertain_queue = null
	) {
		$this->csv_parser      = $csv_parser ?? new FFM_CSV_Parser();
		$this->matching_engine = $matching_engine ?? new FFM_Matching_Engine();
		$this->auto_applier    = $auto_applier ?? new FFM_Auto_Applier();
		$this->uncertain_queue = $uncertain_queue ?? new FFM_Uncertain_Queue();
	}

	/**
	 * Run the full CSV import pipeline.
	 *
	 * Pipeline:
	 * 1. Load CSV via FFM_CSV_Parser::load()
	 * 2. Run FFM_Matching_Engine::match_all_csv_rows() to get all match results
	 * 3. Run FFM_Auto_Applier::apply_all_confident() for confident matches
	 * 4. Run FFM_Uncertain_Queue::queue_all_uncertain() for uncertain/no_match
	 *
	 * @return array Combined statistics array with:
	 *               - csv_rows: total rows in CSV
	 *               - matching: stats from matching engine
	 *               - applied: count from auto-applier
	 *               - queued: count from uncertain queue
	 *               - errors: any errors encountered
	 *               - duration_seconds: time taken
	 */
	public function run_import() {
		$start_time = microtime( true );

		// Default result structure.
		$result = array(
			'csv_rows'         => 0,
			'matching'         => array(
				'total'       => 0,
				'exact_match' => 0,
				'alias_match' => 0,
				'uncertain'   => 0,
				'no_match'    => 0,
			),
			'applied'          => array(
				'applied' => 0,
				'skipped' => 0,
				'errors'  => 0,
			),
			'queued'           => array(
				'queued'  => 0,
				'skipped' => 0,
				'errors'  => 0,
			),
			'errors'           => array(),
			'duration_seconds' => 0,
			'success'          => false,
		);

		// Step 0: Clear pending review queue (each import is fresh).
		$this->uncertain_queue->clear_pending();

		// Step 1: Load CSV.
		if ( ! $this->csv_parser->load() ) {
			$result['errors'][] = 'Failed to load CSV file.';
			$result['duration_seconds'] = round( microtime( true ) - $start_time, 2 );
			$this->store_import_results( $result );
			return $result;
		}

		$result['csv_rows'] = $this->csv_parser->get_row_count();

		// Step 2: Match all CSV rows.
		$match_results = $this->matching_engine->match_all_csv_rows( $this->csv_parser );

		if ( ! empty( $match_results['error'] ) ) {
			$result['errors'][] = 'Matching error: ' . $match_results['error'];
			$result['duration_seconds'] = round( microtime( true ) - $start_time, 2 );
			$this->store_import_results( $result );
			return $result;
		}

		$result['matching'] = $match_results['stats'];

		// Step 3: Apply all confident matches.
		$apply_stats = $this->auto_applier->apply_all_confident( $match_results, $this->csv_parser );
		$result['applied'] = array(
			'applied' => $apply_stats['applied'],
			'skipped' => $apply_stats['skipped'],
			'errors'  => count( $apply_stats['errors'] ),
		);

		// Collect apply errors.
		if ( ! empty( $apply_stats['errors'] ) ) {
			foreach ( $apply_stats['errors'] as $error ) {
				$result['errors'][] = 'Apply error (row ' . $error['row_index'] . '): ' . $error['message'];
			}
		}

		// Step 4: Queue all uncertain/no_match.
		$queue_stats = $this->uncertain_queue->queue_all_uncertain( $match_results, $this->csv_parser );
		$result['queued'] = array(
			'queued'  => $queue_stats['queued'],
			'skipped' => $queue_stats['skipped'],
			'errors'  => $queue_stats['errors'],
		);

		$result['duration_seconds'] = round( microtime( true ) - $start_time, 2 );
		$result['success']          = true;

		// Store results for later retrieval.
		$this->store_import_results( $result );

		return $result;
	}

	/**
	 * Store import results in WP options.
	 *
	 * @param array $result Import result array.
	 */
	private function store_import_results( $result ) {
		update_option( self::OPTION_LAST_IMPORT_TIMESTAMP, current_time( 'mysql' ) );
		update_option( self::OPTION_IMPORT_RESULTS, $result );
	}

	/**
	 * Get import status.
	 *
	 * @return array Status array with:
	 *               - has_been_run: boolean
	 *               - last_run: datetime string or null
	 *               - last_stats: stored stats from last run or null
	 */
	public static function get_import_status() {
		$last_timestamp = get_option( self::OPTION_LAST_IMPORT_TIMESTAMP, null );
		$last_results   = get_option( self::OPTION_IMPORT_RESULTS, null );

		return array(
			'has_been_run' => ! empty( $last_timestamp ),
			'last_run'     => $last_timestamp,
			'last_stats'   => $last_results,
		);
	}
}
