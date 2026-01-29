<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/db-footyforums-data.php';

/**
 * Mapping: source_code => clubs table column name
 */
function ffm_get_clubs_column_map(): array {
	return [
		// Existing mappings
		'transfermarkt' => 't_id',
		'fbref'         => 'f_id',
		'sofascore'     => 's_id',
		'sofifa'        => 'sf_id',
		'whoscored'     => 'w_id',
		'wikipedia'     => 'wp_id',
		'wikidata'      => 'wd_id',
		'statsbomb'     => 'sb_id',
		'understat'     => 'us_id',
		'opta'          => 'o_id',
		// New providers from CSV import
		'fotmob'          => 'fmob_id',
		'sportmonks'      => 'sm_id',
		'instat'          => 'is_id',
		'skillcorner'     => 'sc_id',
		'footballmanager' => 'fmgr_id',
	];
}

/**
 * Fetch clubs with pending tasks (one row per club, domestic leagues only).
 */
function ffm_get_queue_page_clubs(int $limit, int $offset = 0, string $region_group_code = '', string $competition_code = '', bool $include_opta = false): array {
	$db = kt_ffdb();

	$where = [];
	$params = [];

	$where[] = "c.e_team_id IS NOT NULL";
	$where[] = "TRIM(c.e_team_id) <> ''";
	$where[] = "comp.comp_type = 'league'";
	$where[] = "comp.region = 'domestic'";
	
	$source_codes_list = ['transfermarkt', 'fbref', 'sofascore', 'statsbomb', 'understat', 'wikipedia', 'wikidata', 'whoscored', 'sofifa'];
	if ($include_opta) {
		$source_codes_list[] = 'opta';
	}
	$source_codes_placeholders = implode(',', array_fill(0, count($source_codes_list), '%s'));
	$where[] = "EXISTS (
		SELECT 1 FROM club_id_map_tasks t2
		WHERE t2.club_id = c.id
		AND t2.source_code IN ($source_codes_placeholders)
		AND t2.status = 'pending'
	)";
	$params = array_merge($params, $source_codes_list);

	if ($region_group_code !== '') {
		$where[] = "crm.region_group_code = %s";
		$params[] = $region_group_code;
	}

	if ($competition_code !== '') {
		$where[] = "pc.competition_code = %s";
		$params[] = $competition_code;
	}

	$sql = "
		SELECT DISTINCT
			c.id AS club_id,
			c.canonical_name,
			c.e_team_id,
			rg.region_group_code,
			rg.name AS region_name,
			pc.competition_code,
			COALESCE(comp.name, pc.competition_code, 'Unassigned') AS league_name

		FROM clubs c

		INNER JOIN v_club_primary_competition pc
			ON pc.club_id = c.id

		INNER JOIN competitions comp
			ON comp.competition_code = pc.competition_code
			AND comp.comp_type = 'league'
			AND comp.region = 'domestic'

		LEFT JOIN competition_region_map crm
			ON crm.competition_code = pc.competition_code

		LEFT JOIN region_groups rg
			ON rg.region_group_code = crm.region_group_code

		WHERE " . implode(' AND ', $where) . "

		ORDER BY
			COALESCE(rg.sort_order, 999) ASC,
			COALESCE(rg.name, 'Unassigned') ASC,
			COALESCE(comp.name, pc.competition_code, 'Unassigned') ASC,
			c.canonical_name ASC

		LIMIT %d OFFSET %d
	";

	$params[] = $limit;
	$params[] = $offset;

	$sql = $db->prepare($sql, ...$params);

	return $db->get_results($sql, ARRAY_A) ?: [];
}

/**
 * Get total count of clubs with pending tasks (domestic leagues only).
 */
function ffm_get_queue_count_clubs(string $region_group_code = '', string $competition_code = '', bool $include_opta = false): int {
	$db = kt_ffdb();

	$where = [];
	$params = [];

	$where[] = "c.e_team_id IS NOT NULL";
	$where[] = "TRIM(c.e_team_id) <> ''";
	$where[] = "comp.comp_type = 'league'";
	$where[] = "comp.region = 'domestic'";
	
	$source_codes_list = ['transfermarkt', 'fbref', 'sofascore', 'statsbomb', 'understat', 'wikipedia', 'wikidata', 'whoscored', 'sofifa'];
	if ($include_opta) {
		$source_codes_list[] = 'opta';
	}
	$source_codes_placeholders = implode(',', array_fill(0, count($source_codes_list), '%s'));
	$where[] = "EXISTS (
		SELECT 1 FROM club_id_map_tasks t2
		WHERE t2.club_id = c.id
		AND t2.source_code IN ($source_codes_placeholders)
		AND t2.status = 'pending'
	)";
	$params = array_merge($params, $source_codes_list);

	if ($region_group_code !== '') {
		$where[] = "crm.region_group_code = %s";
		$params[] = $region_group_code;
	}

	if ($competition_code !== '') {
		$where[] = "pc.competition_code = %s";
		$params[] = $competition_code;
	}

	$sql = "
		SELECT COUNT(DISTINCT c.id) as cnt
		FROM clubs c

		INNER JOIN v_club_primary_competition pc
			ON pc.club_id = c.id

		INNER JOIN competitions comp
			ON comp.competition_code = pc.competition_code
			AND comp.comp_type = 'league'
			AND comp.region = 'domestic'

		LEFT JOIN competition_region_map crm
			ON crm.competition_code = pc.competition_code

		WHERE " . implode(' AND ', $where) . "
	";

	if ($params) {
		$sql = $db->prepare($sql, ...$params);
	}

	$result = $db->get_var($sql);
	return (int) ($result ?? 0);
}

/**
 * Fetch regions for dropdown (domestic leagues only).
 */
function ffm_get_regions(): array {
	$db = kt_ffdb();
	$sql = "
		SELECT DISTINCT
			rg.region_group_code,
			rg.name,
			rg.sort_order
		FROM region_groups rg
		INNER JOIN competition_region_map crm
			ON crm.region_group_code = rg.region_group_code
		INNER JOIN competitions comp
			ON comp.competition_code = crm.competition_code
			AND comp.comp_type = 'league'
			AND comp.region = 'domestic'
		ORDER BY rg.sort_order, rg.name
	";
	$rows = $db->get_results($sql, ARRAY_A);
	return $rows ?: [];
}

/**
 * Fetch leagues for dropdown (domestic leagues only), optionally filtered by region.
 */
function ffm_get_leagues_for_region(string $region_group_code = ''): array {
	$db = kt_ffdb();

	if ($region_group_code !== '') {
		$sql = $db->prepare("
			SELECT DISTINCT
				c.competition_code,
				COALESCE(c.name, c.competition_code) AS name
			FROM competitions c
			INNER JOIN competition_region_map crm
				ON crm.competition_code = c.competition_code
			WHERE crm.region_group_code = %s
			AND c.comp_type = 'league'
			AND c.region = 'domestic'
			ORDER BY c.name, c.competition_code
		", $region_group_code);
	} else {
		$sql = "
			SELECT DISTINCT
				c.competition_code,
				COALESCE(c.name, c.competition_code) AS name
			FROM competitions c
			INNER JOIN competition_region_map crm
				ON crm.competition_code = c.competition_code
			WHERE c.comp_type = 'league'
			AND c.region = 'domestic'
			ORDER BY c.name, c.competition_code
		";
	}

	$rows = $db->get_results($sql, ARRAY_A);
	return $rows ?: [];
}

/**
 * Get task statuses for given clubs (all statuses, not just pending).
 * Returns: $result[club_id][source_code] = status
 * If task row missing but clubs column has value, status = 'done'
 * If task row missing and clubs column empty, status = 'pending'
 */
function ffm_get_task_statuses_for_clubs(array $club_ids, bool $include_opta = false): array {
	if (empty($club_ids)) {
		return [];
	}

	$db = kt_ffdb();
	$in = implode(',', array_fill(0, count($club_ids), '%d'));
	$result = [];

	$clubs_column_map = ffm_get_clubs_column_map();

	$source_codes_list = ['transfermarkt', 'fbref', 'sofascore', 'statsbomb', 'understat', 'wikipedia', 'wikidata', 'whoscored', 'sofifa'];
	if ($include_opta) {
		$source_codes_list[] = 'opta';
	}
	$source_codes_placeholders = implode(',', array_fill(0, count($source_codes_list), '%s'));

	$sql = $db->prepare(
		"SELECT club_id, source_code, status
		FROM club_id_map_tasks
		WHERE club_id IN ($in)
		AND source_code IN ($source_codes_placeholders)",
		...array_merge($club_ids, $source_codes_list)
	);

	$rows = $db->get_results($sql, ARRAY_A);
	$task_map = [];
	foreach ($rows as $r) {
		$cid = (int) $r['club_id'];
		$code = trim((string) $r['source_code']);
		$task_map[$cid][$code] = trim((string) $r['status']);
	}

	$select_cols = [];
	foreach ($clubs_column_map as $code => $col) {
		if ($code === 'opta' && !$include_opta) {
			continue;
		}
		$col_escaped = str_replace('`', '``', $col);
		$code_escaped = str_replace('`', '``', $code);
		$select_cols[] = "`{$col_escaped}` AS `{$code_escaped}`";
	}

	$sql = "SELECT `id` AS club_id, " . implode(', ', $select_cols) . "
		FROM clubs
		WHERE id IN ($in)";
	$sql = $db->prepare($sql, ...$club_ids);
	$club_rows = $db->get_results($sql, ARRAY_A);

	foreach ($club_rows as $cr) {
		$cid = (int) $cr['club_id'];
		foreach ($clubs_column_map as $code => $col) {
			if ($code === 'opta' && !$include_opta) {
				continue;
			}
			$val = isset($cr[$code]) ? trim((string) $cr[$code]) : '';
			if ($val !== '') {
				$result[$cid][$code] = 'done';
			} elseif (isset($task_map[$cid][$code])) {
				$result[$cid][$code] = $task_map[$cid][$code];
			} else {
				$result[$cid][$code] = 'pending';
			}
		}
	}

	return $result;
}

/**
 * Get pending tasks for given clubs (for backward compatibility and has_pending check).
 */
function ffm_get_pending_tasks_for_clubs(array $club_ids, bool $include_opta = false): array {
	if (empty($club_ids)) {
		return [];
	}

	$db = kt_ffdb();
	$in = implode(',', array_fill(0, count($club_ids), '%d'));

	$source_codes_list = ['transfermarkt', 'fbref', 'sofascore', 'statsbomb', 'understat', 'wikipedia', 'wikidata', 'whoscored', 'sofifa'];
	if ($include_opta) {
		$source_codes_list[] = 'opta';
	}
	$source_codes_placeholders = implode(',', array_fill(0, count($source_codes_list), '%s'));

	$sql = $db->prepare(
		"SELECT club_id, source_code, status
		FROM club_id_map_tasks
		WHERE club_id IN ($in)
		AND source_code IN ($source_codes_placeholders)
		AND status = 'pending'",
		...array_merge($club_ids, $source_codes_list)
	);

	$rows = $db->get_results($sql, ARRAY_A);
	$result = [];

	foreach ($rows as $r) {
		$cid = (int) $r['club_id'];
		$code = (string) $r['source_code'];
		$result[$cid][$code] = (string) $r['status'];
	}

	return $result;
}

/**
 * Get existing provider IDs for given clubs.
 * Returns: $result[club_id][source_code] = clubs column value
 * Reads ONLY from clubs table using ffm_get_clubs_column_map()
 */
function ffm_get_existing_provider_ids(array $club_ids, bool $include_opta = false): array {
	if (empty($club_ids)) {
		return [];
	}

	$db = kt_ffdb();
	$in = implode(',', array_fill(0, count($club_ids), '%d'));
	$result = [];

	$clubs_column_map = ffm_get_clubs_column_map();

	if (!empty($clubs_column_map)) {
		$select_cols = [];
		foreach ($clubs_column_map as $code => $col) {
			if ($code === 'opta' && !$include_opta) {
				continue;
			}
			$col_escaped = str_replace('`', '``', $col);
			$code_escaped = str_replace('`', '``', $code);
			$select_cols[] = "`{$col_escaped}` AS `{$code_escaped}`";
		}

		$sql = "SELECT `id` AS club_id, " . implode(', ', $select_cols) . "
			FROM clubs
			WHERE id IN ($in)";

		$sql = $db->prepare($sql, ...$club_ids);

		$rows = $db->get_results($sql, ARRAY_A);
		foreach ($rows as $r) {
			$cid = (int) $r['club_id'];
			foreach ($clubs_column_map as $code => $col) {
				if ($code === 'opta' && !$include_opta) {
					continue;
				}
				$val = isset($r[$code]) ? trim((string) $r[$code]) : '';
				if ($val !== '') {
					$result[$cid][$code] = $val;
				}
			}
		}
	}

	return $result;
}
