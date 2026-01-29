<?php
/**
 * Matching Engine for Club ID Mapping
 *
 * Core matching logic that finds database clubs matching CSV rows using
 * exact name matching with country validation. Connects CSV parser output
 * to database lookups using name normalizer and country mapper utilities.
 *
 * @package FootyForums_Club_ID_Mapper
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/db-footyforums-data.php';

/**
 * FFM_Matching_Engine class.
 *
 * Provides methods to match CSV rows against database clubs,
 * returning confidence levels (exact_match, alias_match, uncertain, no_match).
 */
class FFM_Matching_Engine {

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
	 * Find clubs matching a name (exact or normalized).
	 *
	 * Queries the clubs table by canonical_name with case-insensitive matching.
	 * Also attempts normalized comparison using FFM_Name_Normalizer.
	 *
	 * @param string $name Club name to search for.
	 * @return array Array of matching club objects with id, canonical_name, competition_code.
	 */
	public function find_clubs_by_name( $name ) {
		if ( empty( $name ) || ! is_string( $name ) ) {
			return array();
		}

		$trimmed = trim( $name );
		if ( '' === $trimmed ) {
			return array();
		}

		// First, try exact case-insensitive match on canonical_name.
		$sql = $this->db->prepare(
			"SELECT c.id, c.canonical_name, pc.competition_code
			FROM clubs c
			LEFT JOIN v_club_primary_competition pc ON pc.club_id = c.id
			WHERE LOWER(c.canonical_name) = LOWER(%s)",
			$trimmed
		);
		$results = $this->db->get_results( $sql, ARRAY_A );

		if ( ! empty( $results ) ) {
			return $results;
		}

		// If no exact match, try normalized comparison.
		// Query clubs and filter in PHP for normalized match.
		$normalized_search = FFM_Name_Normalizer::normalize( $trimmed );
		if ( '' === $normalized_search ) {
			return array();
		}

		// Search for clubs where normalized canonical_name matches.
		// For performance, we search LIKE with normalized base letters.
		$like_pattern = '%' . $this->db->esc_like( substr( $normalized_search, 0, 3 ) ) . '%';

		$sql = $this->db->prepare(
			"SELECT c.id, c.canonical_name, pc.competition_code
			FROM clubs c
			LEFT JOIN v_club_primary_competition pc ON pc.club_id = c.id
			WHERE LOWER(c.canonical_name) LIKE %s",
			$like_pattern
		);
		$candidates = $this->db->get_results( $sql, ARRAY_A );

		$matches = array();
		foreach ( $candidates as $club ) {
			$club_normalized = FFM_Name_Normalizer::normalize( $club['canonical_name'] );
			if ( $club_normalized === $normalized_search ) {
				$matches[] = $club;
			}
		}

		return $matches;
	}

	/**
	 * Find clubs via the club_aliases table.
	 *
	 * Queries the club_aliases table by alias_name with case-insensitive matching,
	 * then fetches full club records from the clubs table.
	 *
	 * @param string $name Alias name to search for.
	 * @return array Array of club objects with id, canonical_name, competition_code, and alias_name.
	 */
	public function find_clubs_by_alias( $name ) {
		if ( empty( $name ) || ! is_string( $name ) ) {
			return array();
		}

		$trimmed = trim( $name );
		if ( '' === $trimmed ) {
			return array();
		}

		// Query club_aliases for matching alias.
		$sql = $this->db->prepare(
			"SELECT DISTINCT ca.club_id, ca.alias_name
			FROM club_aliases ca
			WHERE LOWER(ca.alias_name) = LOWER(%s)",
			$trimmed
		);
		$alias_results = $this->db->get_results( $sql, ARRAY_A );

		if ( empty( $alias_results ) ) {
			return array();
		}

		// Fetch full club records for each matched club_id.
		$results = array();
		foreach ( $alias_results as $alias_row ) {
			$club_id = (int) $alias_row['club_id'];
			$sql     = $this->db->prepare(
				"SELECT c.id, c.canonical_name, pc.competition_code
				FROM clubs c
				LEFT JOIN v_club_primary_competition pc ON pc.club_id = c.id
				WHERE c.id = %d",
				$club_id
			);
			$club = $this->db->get_row( $sql, ARRAY_A );

			if ( $club ) {
				$club['alias_name'] = $alias_row['alias_name'];
				$results[]          = $club;
			}
		}

		return $results;
	}

	/**
	 * Get competition code for a club.
	 *
	 * Queries the v_club_primary_competition view to get the primary competition code.
	 *
	 * @param int $club_id Club ID.
	 * @return string|null Competition code string or null if not found.
	 */
	public function get_club_competition_code( $club_id ) {
		if ( empty( $club_id ) || ! is_numeric( $club_id ) ) {
			return null;
		}

		$sql = $this->db->prepare(
			"SELECT competition_code
			FROM v_club_primary_competition
			WHERE club_id = %d",
			(int) $club_id
		);
		$result = $this->db->get_var( $sql );

		return $result ? (string) $result : null;
	}

	/**
	 * Match a single CSV row against database clubs.
	 *
	 * Returns a structured result with match status, club_id, confidence level,
	 * match type, and candidate clubs for uncertain matches.
	 *
	 * @param array          $csv_row    Associative array representing a single CSV row.
	 * @param FFM_CSV_Parser $csv_parser CSV parser instance for extracting data.
	 * @return array Match result with keys: status, club_id, club_name, confidence, match_type, candidates.
	 */
	public function match_csv_row( $csv_row, $csv_parser ) {
		// Default result structure.
		$result = array(
			'status'     => 'no_match',
			'club_id'    => null,
			'club_name'  => null,
			'confidence' => 'none',
			'match_type' => null,
			'candidates' => array(),
		);

		// Extract data from CSV row using parser methods.
		$primary_name    = $csv_parser->get_primary_name( $csv_row );
		$csv_country     = $csv_parser->get_country( $csv_row );
		$name_variations = $csv_parser->get_name_variations( $csv_row );

		if ( '' === $primary_name ) {
			return $result;
		}

		// Step 1: Try exact canonical name match.
		$canonical_matches = $this->find_clubs_by_name( $primary_name );
		$country_validated = $this->filter_by_country( $canonical_matches, $csv_country );

		if ( 1 === count( $country_validated ) ) {
			// Exactly one match with valid country = exact_match.
			$match               = $country_validated[0];
			$result['status']    = 'exact_match';
			$result['club_id']   = (int) $match['id'];
			$result['club_name'] = $match['canonical_name'];
			$result['confidence'] = 'high';
			$result['match_type'] = 'canonical';
			return $result;
		}

		// Step 2: Try alias matches using all name variations.
		$alias_club_ids = array();
		$alias_matches  = array();

		foreach ( $name_variations as $name ) {
			$alias_results = $this->find_clubs_by_alias( $name );
			foreach ( $alias_results as $alias_club ) {
				$club_id = (int) $alias_club['id'];
				if ( ! isset( $alias_club_ids[ $club_id ] ) ) {
					$alias_club_ids[ $club_id ] = $alias_club;
					$alias_matches[]            = $alias_club;
				}
			}
		}

		$alias_country_validated = $this->filter_by_country( $alias_matches, $csv_country );

		if ( 1 === count( $alias_country_validated ) ) {
			// Exactly one alias match with valid country = alias_match.
			$match               = $alias_country_validated[0];
			$result['status']    = 'alias_match';
			$result['club_id']   = (int) $match['id'];
			$result['club_name'] = $match['canonical_name'];
			$result['confidence'] = 'high';
			$result['match_type'] = 'alias';
			return $result;
		}

		// Step 3: Handle multiple candidates (uncertain).
		$all_candidates = array_merge( $country_validated, $alias_country_validated );

		// Deduplicate by club_id.
		$unique_candidates = array();
		$seen_ids          = array();
		foreach ( $all_candidates as $candidate ) {
			$cid = (int) $candidate['id'];
			if ( ! isset( $seen_ids[ $cid ] ) ) {
				$seen_ids[ $cid ]    = true;
				$unique_candidates[] = $candidate;
			}
		}

		if ( count( $unique_candidates ) > 1 ) {
			// Multiple clubs pass country validation = uncertain.
			$result['status']     = 'uncertain';
			$result['confidence'] = 'medium';
			$result['candidates'] = $unique_candidates;
			return $result;
		}

		// Step 4: Check if clubs were found but failed country validation.
		$all_found = array_merge( $canonical_matches, $alias_matches );
		if ( ! empty( $all_found ) && empty( $unique_candidates ) ) {
			// Clubs found but country mismatch = uncertain (country mismatch).
			$result['status']     = 'uncertain';
			$result['confidence'] = 'low';
			$result['candidates'] = $all_found;
			return $result;
		}

		// Step 5: No match found at all.
		$result['status']     = 'no_match';
		$result['confidence'] = 'none';
		return $result;
	}

	/**
	 * Match all CSV rows against database clubs (batch operation).
	 *
	 * Iterates through all rows in the CSV parser and matches each one,
	 * returning per-row results and aggregate statistics.
	 *
	 * @param FFM_CSV_Parser $csv_parser Loaded CSV parser instance.
	 * @return array Array with 'results' (per-row match data) and 'stats' (aggregate counts).
	 */
	public function match_all_csv_rows( $csv_parser ) {
		// Default structure.
		$output = array(
			'results' => array(),
			'stats'   => array(
				'total'       => 0,
				'exact_match' => 0,
				'alias_match' => 0,
				'uncertain'   => 0,
				'no_match'    => 0,
			),
			'error'   => null,
		);

		// Check if CSV is loaded.
		if ( ! $csv_parser->is_loaded() ) {
			$output['error'] = 'CSV not loaded. Call $csv_parser->load() first.';
			return $output;
		}

		$rows = $csv_parser->get_rows();
		$output['stats']['total'] = count( $rows );

		foreach ( $rows as $row_index => $row ) {
			// Match this row.
			$match_result = $this->match_csv_row( $row, $csv_parser );

			// Add row context for traceability.
			$result_with_context = array_merge(
				array(
					'row_index' => $row_index,
					'csv_name'  => $csv_parser->get_primary_name( $row ),
				),
				$match_result
			);

			$output['results'][] = $result_with_context;

			// Update statistics by status.
			$status = $match_result['status'];
			if ( isset( $output['stats'][ $status ] ) ) {
				$output['stats'][ $status ]++;
			}
		}

		return $output;
	}

	/**
	 * Filter clubs by country validation.
	 *
	 * Uses FFM_Country_Mapper::validate_club_country() to filter clubs
	 * whose competition code prefix matches the expected country.
	 *
	 * @param array  $clubs       Array of club records with competition_code.
	 * @param string $csv_country Country string from CSV row.
	 * @return array Filtered array of clubs that pass country validation.
	 */
	private function filter_by_country( $clubs, $csv_country ) {
		if ( empty( $clubs ) || '' === trim( $csv_country ) ) {
			return $clubs;
		}

		$filtered = array();
		foreach ( $clubs as $club ) {
			$competition_code = isset( $club['competition_code'] ) ? $club['competition_code'] : '';
			if ( FFM_Country_Mapper::validate_club_country( $csv_country, $competition_code ) ) {
				$filtered[] = $club;
			}
		}

		return $filtered;
	}
}
