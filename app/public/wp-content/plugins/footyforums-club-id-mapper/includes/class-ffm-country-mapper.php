<?php
/**
 * Country to League Prefix Mapper
 *
 * Maps CSV country values to database league prefixes for validation
 * during the club matching process.
 *
 * @package FootyForums_Club_ID_Mapper
 */

defined( 'ABSPATH' ) || exit;

/**
 * FFM_Country_Mapper class.
 *
 * Provides static methods for mapping country names to league prefixes
 * and validating CSV country fields against database competition codes.
 */
class FFM_Country_Mapper {

	/**
	 * Country to league prefix mapping.
	 *
	 * Maps country names (as they appear in CSV files) to their
	 * corresponding league prefix codes used in competition_code field.
	 *
	 * @var array<string, string>
	 */
	const COUNTRY_TO_PREFIX = array(
		// Major European leagues.
		'England'            => 'eng',
		'Spain'              => 'esp',
		'Italy'              => 'ita',
		'France'             => 'fra',
		'Germany'            => 'ger',
		'Portugal'           => 'por',
		'Netherlands'        => 'ned',
		'Scotland'           => 'sco',
		'Belgium'            => 'bel',
		'Turkey'             => 'tur',
		'Greece'             => 'gre',
		'Austria'            => 'aut',
		'Switzerland'        => 'sui',
		'Russia'             => 'rus',
		'Ukraine'            => 'ukr',
		'Poland'             => 'pol',
		'Czech Republic'     => 'cze',
		'Denmark'            => 'den',
		'Sweden'             => 'swe',
		'Norway'             => 'nor',

		// South America.
		'Argentina'          => 'arg',
		'Brazil'             => 'bra',
		'Chile'              => 'chi',
		'Colombia'           => 'col',
		'Uruguay'            => 'uru',
		'Paraguay'           => 'par',
		'Peru'               => 'per',
		'Ecuador'            => 'ecu',
		'Venezuela'          => 'ven',
		'Bolivia'            => 'bol',

		// North/Central America.
		'Mexico'             => 'mex',
		'USA'                => 'usa',
		'United States'      => 'usa',

		// Asia.
		'Japan'              => 'jpn',
		'South Korea'        => 'kor',
		'China'              => 'chn',
		'Saudi Arabia'       => 'sau',
		'Australia'          => 'aus',

		// Africa.
		'Egypt'              => 'egy',
		'South Africa'       => 'rsa',
		'Morocco'            => 'mar',
		'Nigeria'            => 'nga',

		// Other European.
		'Croatia'            => 'cro',
		'Serbia'             => 'srb',
		'Romania'            => 'rom',
		'Hungary'            => 'hun',
		'Bulgaria'           => 'bul',
		'Cyprus'             => 'cyp',
		'Israel'             => 'isr',
		'Republic of Ireland' => 'irl',
		'Ireland'            => 'irl',
		'Northern Ireland'   => 'nir',
		'Wales'              => 'wal',
		'Finland'            => 'fin',
		'Iceland'            => 'isl',
	);

	/**
	 * Get the league prefix for a country.
	 *
	 * @param string $country Country name from CSV.
	 * @return string|null League prefix or null if not found.
	 */
	public static function get_prefix( $country ) {
		if ( empty( $country ) || ! is_string( $country ) ) {
			return null;
		}

		// Normalize: trim whitespace and convert to title case for lookup.
		$normalized = trim( $country );
		$normalized = ucwords( strtolower( $normalized ) );

		// Direct lookup.
		if ( isset( self::COUNTRY_TO_PREFIX[ $normalized ] ) ) {
			return self::COUNTRY_TO_PREFIX[ $normalized ];
		}

		// Try original casing in case title case broke multi-word entries.
		$original_trimmed = trim( $country );
		if ( isset( self::COUNTRY_TO_PREFIX[ $original_trimmed ] ) ) {
			return self::COUNTRY_TO_PREFIX[ $original_trimmed ];
		}

		// Case-insensitive search as fallback.
		$lower = strtolower( $normalized );
		foreach ( self::COUNTRY_TO_PREFIX as $key => $prefix ) {
			if ( strtolower( $key ) === $lower ) {
				return $prefix;
			}
		}

		return null;
	}

	/**
	 * Check if a country has a known mapping.
	 *
	 * @param string $country Country name to check.
	 * @return bool True if country has a known prefix mapping.
	 */
	public static function is_known_country( $country ) {
		return null !== self::get_prefix( $country );
	}

	/**
	 * Get all country-to-prefix mappings.
	 *
	 * Useful for reference and debugging.
	 *
	 * @return array<string, string> Full mapping array.
	 */
	public static function get_all_mappings() {
		return self::COUNTRY_TO_PREFIX;
	}

	/**
	 * Validate that a CSV country matches a club's competition code prefix.
	 *
	 * @param string $csv_country        Country name from CSV.
	 * @param string $club_competition_code Competition code from database (e.g., "eng.1").
	 * @return bool True if country matches competition prefix, false otherwise.
	 */
	public static function validate_club_country( $csv_country, $club_competition_code ) {
		$expected_prefix = self::get_prefix( $csv_country );
		if ( null === $expected_prefix ) {
			return false; // Unknown country.
		}

		if ( empty( $club_competition_code ) || ! is_string( $club_competition_code ) ) {
			return false;
		}

		// Extract prefix from competition_code (e.g., "eng.1" -> "eng").
		$parts         = explode( '.', $club_competition_code );
		$actual_prefix = $parts[0] ?? '';

		return $expected_prefix === $actual_prefix;
	}

	/**
	 * Get unknown countries from a CSV parser instance.
	 *
	 * Identifies country values in the CSV that have no known mapping.
	 * Useful for diagnostics and identifying gaps in country mapping.
	 *
	 * @param FFM_CSV_Parser $csv_parser Loaded CSV parser instance.
	 * @return array<string> Unique unknown country values.
	 */
	public static function get_unknown_countries( $csv_parser ) {
		$unknown = array();

		if ( ! method_exists( $csv_parser, 'get_rows' ) ) {
			return $unknown;
		}

		$rows = $csv_parser->get_rows();
		foreach ( $rows as $row ) {
			$country = $row['country'] ?? $row['Country'] ?? '';
			if ( ! empty( $country ) && ! self::is_known_country( $country ) ) {
				$unknown[ $country ] = true;
			}
		}

		return array_keys( $unknown );
	}
}
