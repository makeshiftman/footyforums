<?php
/**
 * Name Normalizer for Club Matching
 *
 * Provides Unicode-safe name normalization utilities for matching
 * football club names between CSV import data and database records.
 *
 * @package FootyForums_Club_ID_Mapper
 */

defined( 'ABSPATH' ) || exit;

/**
 * FFM_Name_Normalizer class.
 *
 * Static utility class for normalizing football club names for comparison.
 * Uses iconv with TRANSLIT for accent removal as intl extension is not available.
 */
class FFM_Name_Normalizer {

	/**
	 * Common suffixes/prefixes to strip for search normalization.
	 *
	 * @var array<string>
	 */
	const COMMON_AFFIXES = array(
		'fc',
		'afc',
		'cf',
		'sc',
		'fk',
		'if',
		'bk',
		'sk',
		'as',
		'ss',
		'ac',
		'bc',
		'united',
		'city',
		'town',
		'athletic',
		'athletico',
		'sporting',
		'real',
	);

	/**
	 * Normalize a club name for comparison.
	 *
	 * Performs the following transformations:
	 * 1. Unicode-safe lowercase conversion
	 * 2. Transliteration of accented characters to ASCII
	 * 3. Removal of remaining non-alphanumeric characters (keeps spaces, hyphens, periods)
	 * 4. Collapse multiple spaces and trim
	 *
	 * @param string $name Club name to normalize.
	 * @return string Normalized name.
	 */
	public static function normalize( $name ) {
		// Input validation.
		if ( null === $name || ! is_string( $name ) || '' === trim( $name ) ) {
			return '';
		}

		// Unicode-safe lowercase.
		$result = mb_strtolower( $name, 'UTF-8' );

		// Transliterate accented characters to ASCII equivalents.
		// Using iconv with TRANSLIT since intl extension is not available.
		$transliterated = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $result );
		if ( false !== $transliterated ) {
			$result = $transliterated;
		}

		// Remove remaining non-alphanumeric characters (keep spaces, hyphens, periods).
		$result = preg_replace( '/[^a-z0-9\s\-\.]/', '', $result );

		// Collapse multiple spaces.
		$result = preg_replace( '/\s+/', ' ', $result );

		return trim( $result );
	}

	/**
	 * Compare two club names after normalization.
	 *
	 * @param string $name1 First club name.
	 * @param string $name2 Second club name.
	 * @return bool True if normalized names match exactly.
	 */
	public static function match( $name1, $name2 ) {
		$normalized1 = self::normalize( $name1 );
		$normalized2 = self::normalize( $name2 );

		// Both empty is not a match.
		if ( '' === $normalized1 || '' === $normalized2 ) {
			return false;
		}

		return $normalized1 === $normalized2;
	}

	/**
	 * Aggressive normalization for fuzzy matching.
	 *
	 * Applies standard normalization plus:
	 * 1. Strips common club suffixes/prefixes (FC, AFC, SC, etc.)
	 * 2. Removes all punctuation and hyphens
	 * 3. Returns condensed form for broader matching
	 *
	 * @param string $name Club name to normalize for search.
	 * @return string Aggressively normalized name.
	 */
	public static function normalize_for_search( $name ) {
		// Start with standard normalization.
		$result = self::normalize( $name );

		if ( '' === $result ) {
			return '';
		}

		// Remove all punctuation and hyphens.
		$result = preg_replace( '/[\.\-]/', ' ', $result );

		// Split into words.
		$words = preg_split( '/\s+/', $result, -1, PREG_SPLIT_NO_EMPTY );

		// Filter out common affixes.
		$filtered = array_filter(
			$words,
			function ( $word ) {
				return ! in_array( $word, self::COMMON_AFFIXES, true );
			}
		);

		// If we removed everything, return original words minus affixes.
		// This handles edge cases like "AFC" alone.
		if ( empty( $filtered ) ) {
			return implode( ' ', $words );
		}

		// Collapse spaces.
		$result = implode( ' ', $filtered );

		return trim( $result );
	}

	/**
	 * Get similarity score between two names.
	 *
	 * Uses Levenshtein distance on normalized names for fuzzy matching.
	 * Returns a score from 0 (no similarity) to 100 (exact match).
	 *
	 * @param string $name1 First club name.
	 * @param string $name2 Second club name.
	 * @return int Similarity score (0-100).
	 */
	public static function similarity_score( $name1, $name2 ) {
		$normalized1 = self::normalize( $name1 );
		$normalized2 = self::normalize( $name2 );

		if ( '' === $normalized1 || '' === $normalized2 ) {
			return 0;
		}

		if ( $normalized1 === $normalized2 ) {
			return 100;
		}

		$max_len = max( strlen( $normalized1 ), strlen( $normalized2 ) );
		if ( 0 === $max_len ) {
			return 0;
		}

		$distance = levenshtein( $normalized1, $normalized2 );

		return (int) round( ( 1 - ( $distance / $max_len ) ) * 100 );
	}
}
