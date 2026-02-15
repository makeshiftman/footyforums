<?php
/**
 * FF_Data — Data access layer for footyforums_data database
 *
 * Uses fdm_get_footyforums_db() from the football-data-manager plugin.
 * All queries target the footyforums_data database.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Data {

    /**
     * Get the database connection.
     *
     * @return wpdb|false
     */
    private static function db() {
        if ( ! function_exists( 'fdm_get_footyforums_db' ) ) {
            return false;
        }
        return fdm_get_footyforums_db();
    }

    /* ------------------------------------------------------------------
     * Competitions / Leagues
     * ----------------------------------------------------------------*/

    /**
     * Get all active competitions, enriched with league config data.
     * Groups results by region from $GLOBALS['fdm_e_supported_leagues'].
     *
     * @return array  Keyed by region => array of league rows
     */
    public static function get_all_leagues() {
        $db = self::db();
        if ( ! $db ) return array();

        $rows = $db->get_results(
            "SELECT c.competition_code, c.name, c.country_code, c.comp_type, c.level, c.region, c.active_flag,
                    c.country_name AS country_name_db, c.display_region AS display_region_db
             FROM competitions c
             WHERE c.active_flag = 1
             ORDER BY c.name ASC",
            ARRAY_A
        );

        if ( empty( $rows ) ) return array();

        // Enrich with supported leagues config
        $supported = isset( $GLOBALS['fdm_e_supported_leagues'] ) ? $GLOBALS['fdm_e_supported_leagues'] : array();

        // Structure: region => country => leagues[]
        $grouped = array();

        foreach ( $rows as $row ) {
            $code = $row['competition_code'];
            $info = isset( $supported[ $code ] ) ? $supported[ $code ] : null;

            // Config takes priority, then DB columns, then defaults
            $db_country = ! empty( $row['country_name_db'] ) ? $row['country_name_db'] : null;
            $db_region  = ! empty( $row['display_region_db'] ) ? $row['display_region_db'] : null;

            $row['country_name'] = $info && ! empty( $info['country'] ) ? $info['country'] : ( $db_country ?: $row['country_code'] );
            $row['display_region'] = $info ? $info['region'] : ( $db_region ?: null );
            $row['region_label'] = $row['display_region'] ? ucwords( str_replace( '_', ' ', $row['display_region'] ) ) : 'Other';
            $row['priority']     = $info ? (int) $info['priority'] : 0;
            $row['tier']         = $info && isset( $info['tier'] ) ? (int) $info['tier'] : ( $row['level'] ? (int) $row['level'] : null );
            $row['league_type']  = $info && isset( $info['type'] ) ? $info['type'] : $row['comp_type'];
            $row['display_name'] = $info && isset( $info['name'] ) ? $info['name'] : $row['name'];

            $region_key  = $row['display_region'] ?: 'other';
            $country_key = ! empty( $row['country_name'] ) ? $row['country_name'] : '__no_country__';

            $grouped[ $region_key ][ $country_key ][] = $row;
        }

        // Sort leagues within each country: tier ASC (nulls last), then priority DESC
        foreach ( $grouped as &$countries ) {
            foreach ( $countries as &$leagues ) {
                usort( $leagues, function ( $a, $b ) {
                    // Tier sort: numbered tiers first, nulls last
                    $ta = $a['tier'] !== null ? $a['tier'] : 999;
                    $tb = $b['tier'] !== null ? $b['tier'] : 999;
                    if ( $ta !== $tb ) return $ta - $tb;
                    // Then priority desc
                    return $b['priority'] - $a['priority'];
                });
            }
            unset( $leagues );
        }
        unset( $countries );

        return $grouped;
    }

    /**
     * Get a single competition with optional league metadata.
     *
     * @param  string $code  competition_code (e.g. eng.1)
     * @return array|null
     */
    public static function get_league( $code ) {
        $db = self::db();
        if ( ! $db ) return null;

        $row = $db->get_row(
            $db->prepare(
                "SELECT c.competition_code, c.name, c.country_code, c.comp_type, c.level, c.region,
                        c.country_name AS country_name_db, c.display_region AS display_region_db,
                        l.season_year AS current_season_year
                 FROM competitions c
                 LEFT JOIN leagues l ON l.e_league_code = c.competition_code
                 WHERE c.competition_code = %s
                 LIMIT 1",
                $code
            ),
            ARRAY_A
        );

        if ( ! $row ) return null;

        // Enrich: config takes priority, then DB columns, then defaults
        $supported = isset( $GLOBALS['fdm_e_supported_leagues'] ) ? $GLOBALS['fdm_e_supported_leagues'] : array();
        $db_country = ! empty( $row['country_name_db'] ) ? $row['country_name_db'] : null;
        $db_region  = ! empty( $row['display_region_db'] ) ? $row['display_region_db'] : null;

        if ( isset( $supported[ $code ] ) ) {
            $row['league_name']   = $supported[ $code ]['name'];
            $row['region_label']  = ucwords( str_replace( '_', ' ', $supported[ $code ]['region'] ) );
            $row['country_name']  = ! empty( $supported[ $code ]['country'] ) ? $supported[ $code ]['country'] : ( $db_country ?: $row['country_code'] );
        } else {
            $row['league_name']  = $row['name'];
            $row['country_name'] = $db_country ?: $row['country_code'];
            $row['region_label'] = $db_region ? ucwords( str_replace( '_', ' ', $db_region ) ) : 'Other';
        }

        return $row;
    }

    /* ------------------------------------------------------------------
     * Seasons
     * ----------------------------------------------------------------*/

    /**
     * Get the most recent season_year for a competition in fdm_standings.
     *
     * @param  string $code
     * @return int|null
     */
    public static function get_current_season_year( $code ) {
        $db = self::db();
        if ( ! $db ) return null;

        return (int) $db->get_var(
            $db->prepare(
                "SELECT MAX(season_year) FROM fdm_standings WHERE competition_code = %s",
                $code
            )
        ) ?: null;
    }

    /**
     * Get all available season years for a competition.
     *
     * @param  string $code
     * @return array  Descending list of ints
     */
    public static function get_available_seasons( $code ) {
        $db = self::db();
        if ( ! $db ) return array();

        $results = $db->get_col(
            $db->prepare(
                "SELECT DISTINCT season_year FROM fdm_standings
                 WHERE competition_code = %s
                 ORDER BY season_year DESC",
                $code
            )
        );

        return array_map( 'intval', $results );
    }

    /* ------------------------------------------------------------------
     * Standings
     * ----------------------------------------------------------------*/

    /**
     * Get standings for a competition + season, joined with club data.
     *
     * @param  string   $code
     * @param  int      $year
     * @return array
     */
    public static function get_standings( $code, $year ) {
        $db = self::db();
        if ( ! $db ) return array();

        return $db->get_results(
            $db->prepare(
                "SELECT s.position, s.group_name, s.stage_name,
                        s.matches_played, s.wins, s.draws, s.losses,
                        s.goals_for, s.goals_against, s.goal_difference,
                        s.points, s.form,
                        s.qualified_flag, s.relegation_flag,
                        c.id AS club_id, c.canonical_name, c.short_name, c.abbreviation,
                        c.logo_url_primary, c.primary_colour_hex
                 FROM fdm_standings s
                 LEFT JOIN clubs c ON c.id = s.club_id
                 WHERE s.competition_code = %s AND s.season_year = %d
                 ORDER BY s.group_name ASC, s.position ASC",
                $code,
                $year
            ),
            ARRAY_A
        );
    }

    /* ------------------------------------------------------------------
     * Fixtures / Matches
     * ----------------------------------------------------------------*/

    /**
     * Get matches for a competition + season.
     *
     * @param  string      $code
     * @param  int         $year
     * @param  string|null $filter  'results', 'upcoming', or null for all
     * @return array
     */
    public static function get_fixtures( $code, $year, $filter = null ) {
        $db = self::db();
        if ( ! $db ) return array();

        $where_extra = '';
        if ( $filter === 'results' ) {
            $where_extra = " AND m.result_code IS NOT NULL AND m.result_code != ''";
        } elseif ( $filter === 'upcoming' ) {
            $where_extra = " AND (m.result_code IS NULL OR m.result_code = '')";
        }

        $order = ( $filter === 'results' ) ? 'DESC' : 'ASC';

        return $db->get_results(
            $db->prepare(
                "SELECT m.id, m.e_match_id, m.match_date, m.home_goals, m.away_goals,
                        m.result_code, m.status_code, m.stadium, m.referee,
                        hc.id AS home_club_id, hc.canonical_name AS home_name,
                        hc.short_name AS home_short_name, hc.abbreviation AS home_abbr,
                        hc.logo_url_primary AS home_logo,
                        ac.id AS away_club_id, ac.canonical_name AS away_name,
                        ac.short_name AS away_short_name, ac.abbreviation AS away_abbr,
                        ac.logo_url_primary AS away_logo
                 FROM matches m
                 LEFT JOIN clubs hc ON hc.id = m.home_club_id
                 LEFT JOIN clubs ac ON ac.id = m.away_club_id
                 WHERE m.competition_code = %s AND m.season_year = %d
                 {$where_extra}
                 ORDER BY m.match_date {$order}, m.id ASC
                 LIMIT 200",
                $code,
                $year
            ),
            ARRAY_A
        );
    }

    /* ------------------------------------------------------------------
     * Player Stats
     * ----------------------------------------------------------------*/

    /** Allowed stat columns for get_top_players to prevent SQL injection */
    private static $allowed_stats = array(
        'goals', 'assists', 'appearances', 'yellow_cards', 'red_cards',
        'minutes', 'shots', 'shots_on_target',
    );

    /**
     * Get top players for a stat in a competition + season.
     *
     * @param  string $code
     * @param  int    $year
     * @param  string $stat  Column name from $allowed_stats
     * @param  int    $limit
     * @return array
     */
    public static function get_top_players( $code, $year, $stat = 'goals', $limit = 30 ) {
        $db = self::db();
        if ( ! $db ) return array();

        if ( ! in_array( $stat, self::$allowed_stats, true ) ) {
            $stat = 'goals';
        }

        $limit = min( max( (int) $limit, 1 ), 100 );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $stat is whitelisted above
        return $db->get_results(
            $db->prepare(
                "SELECT ps.player_id, ps.club_id, ps.appearances, ps.{$stat} AS stat_value,
                        p.canonical_name AS player_name, p.nationality, p.position AS player_position,
                        c.canonical_name AS club_name, c.short_name AS club_short_name,
                        c.logo_url_primary AS club_logo
                 FROM fdm_player_season_stats ps
                 LEFT JOIN players p ON p.id = ps.player_id
                 LEFT JOIN clubs c ON c.id = ps.club_id
                 WHERE ps.competition_code = %s AND ps.season_year = %d AND ps.{$stat} > 0
                 ORDER BY ps.{$stat} DESC, ps.appearances ASC
                 LIMIT %d",
                $code,
                $year,
                $limit
            ),
            ARRAY_A
        );
    }

    /* ------------------------------------------------------------------
     * Widget helpers
     * ----------------------------------------------------------------*/

    /**
     * Get today's matches across all competitions.
     *
     * @return array
     */
    public static function get_todays_matches() {
        $db = self::db();
        if ( ! $db ) return array();

        return $db->get_results(
            $db->prepare(
                "SELECT m.id, m.match_date, m.home_goals, m.away_goals,
                        m.result_code, m.status_code, m.competition_code,
                        hc.canonical_name AS home_name, hc.short_name AS home_short_name,
                        hc.abbreviation AS home_abbr,
                        ac.canonical_name AS away_name, ac.short_name AS away_short_name,
                        ac.abbreviation AS away_abbr
                 FROM matches m
                 LEFT JOIN clubs hc ON hc.id = m.home_club_id
                 LEFT JOIN clubs ac ON ac.id = m.away_club_id
                 WHERE m.match_date = %s
                 ORDER BY m.competition_code, m.id",
                current_time( 'Y-m-d' )
            ),
            ARRAY_A
        );
    }

    /**
     * Format a season year as a display string: "2024/25".
     *
     * @param  int $year
     * @return string
     */
    public static function format_season( $year ) {
        $next = ( $year + 1 ) % 100;
        return $year . '/' . str_pad( $next, 2, '0', STR_PAD_LEFT );
    }

    /* ------------------------------------------------------------------
     * Flag helpers
     * ----------------------------------------------------------------*/

    /** Country name => flag-icons code */
    private static $country_flags = array(
        'England'       => 'gb-eng',
        'Scotland'      => 'gb-sct',
        'Wales'         => 'gb-wls',
        'Northern Ireland' => 'gb-nir',
        'Spain'         => 'es',
        'Germany'       => 'de',
        'Italy'         => 'it',
        'France'        => 'fr',
        'Netherlands'   => 'nl',
        'Portugal'      => 'pt',
        'Belgium'       => 'be',
        'Austria'       => 'at',
        'Turkey'        => 'tr',
        'Russia'        => 'ru',
        'Greece'        => 'gr',
        'Sweden'        => 'se',
        'Norway'        => 'no',
        'Denmark'       => 'dk',
        'Cyprus'        => 'cy',
        'Ireland'       => 'ie',
        'Argentina'     => 'ar',
        'Brazil'        => 'br',
        'Chile'         => 'cl',
        'Uruguay'       => 'uy',
        'Colombia'      => 'co',
        'Peru'          => 'pe',
        'Paraguay'      => 'py',
        'Ecuador'       => 'ec',
        'Venezuela'     => 've',
        'Bolivia'       => 'bo',
        'United States' => 'us',
        'Mexico'        => 'mx',
        'Honduras'      => 'hn',
        'Costa Rica'    => 'cr',
        'Guatemala'     => 'gt',
        'El Salvador'   => 'sv',
        'Saudi Arabia'  => 'sa',
        'Japan'         => 'jp',
        'China'         => 'cn',
        'India'         => 'in',
        'Indonesia'     => 'id',
        'Malaysia'      => 'my',
        'Singapore'     => 'sg',
        'Thailand'      => 'th',
        'South Africa'  => 'za',
        'Nigeria'       => 'ng',
        'Ghana'         => 'gh',
        'Uganda'        => 'ug',
        'Kenya'         => 'ke',
        'Australia'     => 'au',
    );

    /** Region => flag-icons code (for multi-country competitions) */
    private static $region_flags = array(
        'europe'        => 'eu',
        'international' => 'un',
    );

    /**
     * Get flag-icons CSS class for a country name.
     *
     * @param  string|null $country
     * @param  string|null $region   Fallback region
     * @return string  e.g. "fi fi-gb-eng" or "" if no match
     */
    public static function get_flag_class( $country = null, $region = null ) {
        if ( $country && isset( self::$country_flags[ $country ] ) ) {
            return 'fi fi-' . self::$country_flags[ $country ];
        }
        if ( $region && isset( self::$region_flags[ $region ] ) ) {
            return 'fi fi-' . self::$region_flags[ $region ];
        }
        return '';
    }

    /**
     * Render a flag <span> for a country (returns HTML).
     *
     * @param  string|null $country
     * @param  string|null $region
     * @return string  HTML or empty string
     */
    public static function flag_html( $country = null, $region = null ) {
        $class = self::get_flag_class( $country, $region );
        if ( empty( $class ) ) return '';
        return '<span class="' . esc_attr( $class ) . '" aria-hidden="true"></span>';
    }
}
