<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/query-club-queue.php';
require_once __DIR__ . '/db-footyforums-data.php';

// Canonical provider list - EXACT order and keys used everywhere
$sources_non_opta = [
	// Existing providers
	'transfermarkt' => 'Transfermarkt',
	'fbref'         => 'FBref',
	'sofascore'     => 'SofaScore',
	'statsbomb'     => 'StatsBomb',
	'understat'     => 'Understat',
	'wikipedia'     => 'Wikipedia',
	'wikidata'      => 'Wikidata',
	'whoscored'     => 'WhoScored',
	'sofifa'        => 'Sofifa',
	'opta'          => 'Opta',
	// New providers from CSV import
	'fotmob'          => 'FotMob',
	'sportmonks'      => 'SportMonks',
	'instat'          => 'InStat',
	'skillcorner'     => 'SkillCorner',
	'footballmanager' => 'Football Manager',
];

add_action('admin_menu', function () {
	add_menu_page(
		'Club ID Mapper',
		'Club ID Mapper',
		'manage_options',
		'ffm-club-id-mapper',
		'ffm_render_club_id_mapper_page',
		'dashicons-database',
		58
	);
});

add_action('admin_init', function () {
	if (!is_admin()) {
		return;
	}
	if (!current_user_can('manage_options')) {
		return;
	}
	// Only handle posts for our page
	$page = isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : '';
	if ($page !== 'ffm-club-id-mapper') {
		return;
	}
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}

	// Handle migration actions
	if (isset($_POST['ffm_run_migrations'])) {
		if (empty($_POST['ffm_run_migrations_nonce']) || !wp_verify_nonce($_POST['ffm_run_migrations_nonce'], 'ffm_run_migrations')) {
			add_settings_error('ffm', 'nonce_error', 'Security check failed.', 'error');
			return;
		}
		$runner = new FFM_Migration_Runner();
		$result = $runner->run_pending();
		if ($result['success']) {
			$count = count($result['executed']);
			if ($count > 0) {
				add_settings_error('ffm', 'migrations_success', "Successfully ran {$count} migration(s).", 'success');
			} else {
				add_settings_error('ffm', 'migrations_none', 'No pending migrations to run.', 'info');
			}
		} else {
			add_settings_error('ffm', 'migrations_error', 'Migration error: ' . esc_html($result['error']), 'error');
		}
		return;
	}

	// Handle rollback action
	if (isset($_POST['ffm_rollback_migration'])) {
		if (empty($_POST['ffm_rollback_migration_nonce']) || !wp_verify_nonce($_POST['ffm_rollback_migration_nonce'], 'ffm_rollback_migration')) {
			add_settings_error('ffm', 'nonce_error', 'Security check failed.', 'error');
			return;
		}
		$migration_id = isset($_POST['migration_id']) ? sanitize_text_field($_POST['migration_id']) : '';
		if ($migration_id === '') {
			add_settings_error('ffm', 'rollback_error', 'No migration ID specified.', 'error');
			return;
		}
		$runner = new FFM_Migration_Runner();
		$result = $runner->rollback($migration_id);
		if ($result['success']) {
			add_settings_error('ffm', 'rollback_success', "Successfully rolled back migration: {$migration_id}", 'success');
		} else {
			add_settings_error('ffm', 'rollback_error', 'Rollback error: ' . esc_html($result['error']), 'error');
		}
		return;
	}

	// Handle CSV import action
	if (isset($_POST['ffm_run_import'])) {
		if (empty($_POST['ffm_run_import_nonce']) || !wp_verify_nonce($_POST['ffm_run_import_nonce'], 'ffm_run_import')) {
			add_settings_error('ffm', 'nonce_error', 'Security check failed.', 'error');
			return;
		}
		$import_runner = new FFM_Import_Runner();
		$result = $import_runner->run_import();
		if ($result['success']) {
			$message = sprintf(
				'Import complete: %d rows processed, %d auto-applied, %d queued for review, %d errors',
				$result['csv_rows'],
				$result['applied']['applied'],
				$result['queued']['queued'],
				count($result['errors'])
			);
			add_settings_error('ffm', 'import_success', $message, 'success');
		} else {
			$error_msg = 'Import failed';
			if (!empty($result['errors'])) {
				$error_msg .= ': ' . implode('; ', array_slice($result['errors'], 0, 3));
			}
			add_settings_error('ffm', 'import_error', $error_msg, 'error');
		}
		return;
	}

	// Handle review queue actions (approve/reject/skip)
	if (isset($_POST['ffm_review_action'])) {
		if (empty($_POST['ffm_review_action_nonce']) || !wp_verify_nonce($_POST['ffm_review_action_nonce'], 'ffm_review_action')) {
			add_settings_error('ffm', 'nonce_error', 'Security check failed.', 'error');
			return;
		}
		$action = sanitize_text_field($_POST['ffm_review_action']);
		$queue_id = isset($_POST['queue_id']) ? (int) $_POST['queue_id'] : 0;
		$club_id = isset($_POST['club_id']) ? (int) $_POST['club_id'] : 0;
		$review_paged = isset($_POST['review_paged']) ? (int) $_POST['review_paged'] : 1;

		if ($queue_id <= 0) {
			add_settings_error('ffm', 'review_error', 'Invalid queue item ID.', 'error');
			return;
		}

		$queue = new FFM_Uncertain_Queue();
		$success = false;
		$message = '';

		if ($action === 'approve' && $club_id > 0) {
			$success = $queue->approve_item($queue_id, $club_id);
			$message = $success ? 'Item approved successfully.' : 'Failed to approve item.';
		} elseif ($action === 'reject') {
			$success = $queue->reject_item($queue_id);
			$message = $success ? 'Item rejected.' : 'Failed to reject item.';
		} elseif ($action === 'skip') {
			$success = $queue->skip_item($queue_id);
			$message = $success ? 'Item skipped.' : 'Failed to skip item.';
		} else {
			$message = 'Invalid action or missing club selection for approve.';
		}

		add_settings_error('ffm', 'review_result', $message, $success ? 'success' : 'error');

		// Redirect back to review tab with same pagination
		$redirect_url = admin_url('admin.php?page=ffm-club-id-mapper&tab=review');
		if ($review_paged > 1) {
			$redirect_url = add_query_arg('review_paged', $review_paged, $redirect_url);
		}
		wp_safe_redirect($redirect_url);
		exit;
	}

	// Handle batch review actions (bulk reject/skip)
	if (isset($_POST['ffm_batch_review'])) {
		if (empty($_POST['ffm_batch_review_nonce']) || !wp_verify_nonce($_POST['ffm_batch_review_nonce'], 'ffm_batch_review')) {
			add_settings_error('ffm', 'nonce_error', 'Security check failed.', 'error');
			return;
		}
		$batch_action = isset($_POST['batch_action']) ? sanitize_text_field($_POST['batch_action']) : '';
		$review_ids = isset($_POST['review_ids']) && is_array($_POST['review_ids']) ? array_map('intval', $_POST['review_ids']) : [];
		$review_paged = isset($_POST['review_paged']) ? (int) $_POST['review_paged'] : 1;

		if (empty($review_ids)) {
			add_settings_error('ffm', 'batch_error', 'No items selected.', 'error');
			return;
		}

		if (!in_array($batch_action, ['reject_all', 'skip_all'], true)) {
			add_settings_error('ffm', 'batch_error', 'Please select a bulk action.', 'error');
			return;
		}

		$queue = new FFM_Uncertain_Queue();
		$success_count = 0;
		$fail_count = 0;

		foreach ($review_ids as $queue_id) {
			if ($queue_id <= 0) {
				$fail_count++;
				continue;
			}

			$result = false;
			if ($batch_action === 'reject_all') {
				$result = $queue->reject_item($queue_id);
			} elseif ($batch_action === 'skip_all') {
				$result = $queue->skip_item($queue_id);
			}

			if ($result) {
				$success_count++;
			} else {
				$fail_count++;
			}
		}

		$action_label = ($batch_action === 'reject_all') ? 'rejected' : 'skipped';
		$message = "Batch complete: {$success_count} item(s) {$action_label}";
		if ($fail_count > 0) {
			$message .= ", {$fail_count} failed";
		}
		add_settings_error('ffm', 'batch_result', $message, $success_count > 0 ? 'success' : 'error');

		// Redirect back to review tab with same pagination
		$redirect_url = admin_url('admin.php?page=ffm-club-id-mapper&tab=review');
		if ($review_paged > 1) {
			$redirect_url = add_query_arg('review_paged', $review_paged, $redirect_url);
		}
		wp_safe_redirect($redirect_url);
		exit;
	}

	if (!isset($_POST['ffm_save_club'])) {
		return;
	}

	ffm_handle_save_club();
	exit;
});

function ffm_handle_save_club(): void {
	if (!current_user_can('manage_options')) {
		return;
	}

	if (empty($_POST['ffm_save_club_nonce']) || !wp_verify_nonce($_POST['ffm_save_club_nonce'], 'ffm_save_club')) {
		return;
	}

	$club_id = isset($_POST['club_id']) ? (int) $_POST['club_id'] : 0;
	if ($club_id <= 0) {
		return;
	}

	global $sources_non_opta;
	$db = kt_ffdb();

	$ffm_id = isset($_POST['ffm_id']) && is_array($_POST['ffm_id']) ? $_POST['ffm_id'] : [];
	$ffm_na = isset($_POST['ffm_na']) && is_array($_POST['ffm_na']) ? $_POST['ffm_na'] : [];

	$db->query('START TRANSACTION');

	$clubs_column_map = ffm_get_clubs_column_map();

	foreach ($sources_non_opta as $source_code => $label) {
		$na_checked = !empty($ffm_na[$source_code]);
		$value = isset($ffm_id[$source_code]) ? trim(sanitize_text_field($ffm_id[$source_code])) : '';

		if ($na_checked) {
			$db->query(
				$db->prepare(
					"UPDATE club_id_map_tasks SET status = 'na' WHERE club_id = %d AND source_code = %s",
					$club_id,
					$source_code
				)
			);
		} elseif ($value !== '') {
			$clubs_column = $clubs_column_map[$source_code];
			$col_escaped = str_replace('`', '``', $clubs_column);
			$db->query(
				$db->prepare(
					"UPDATE clubs SET `{$col_escaped}` = %s WHERE id = %d",
					$value,
					$club_id
				)
			);

			$db->query(
				$db->prepare(
					"UPDATE club_id_map_tasks SET status = 'done' WHERE club_id = %d AND source_code = %s",
					$club_id,
					$source_code
				)
			);
		}
	}

	$db->query('COMMIT');

	$redirect_url = admin_url('admin.php?page=ffm-club-id-mapper');
	$qs = [];
	if (!empty($_POST['region_group_code'])) {
		$qs['region_group_code'] = sanitize_text_field($_POST['region_group_code']);
	} elseif (!empty($_GET['region_group_code'])) {
		$qs['region_group_code'] = sanitize_text_field($_GET['region_group_code']);
	}
	if (!empty($_POST['competition_code'])) {
		$qs['competition_code'] = sanitize_text_field($_POST['competition_code']);
	} elseif (!empty($_GET['competition_code'])) {
		$qs['competition_code'] = sanitize_text_field($_GET['competition_code']);
	}
	if (!empty($_POST['paged'])) {
		$qs['paged'] = (int) $_POST['paged'];
	} elseif (!empty($_GET['paged'])) {
		$qs['paged'] = (int) $_GET['paged'];
	}
	if ($qs) {
		$redirect_url = add_query_arg($qs, $redirect_url);
	}

	wp_safe_redirect($redirect_url);
	exit;
}

function ffm_backfill_missing_tasks(): void {
	$db = kt_ffdb();
	$clubs_column_map = ffm_get_clubs_column_map();

	$db->query('START TRANSACTION');

	foreach (['whoscored' => 'w_id', 'sofifa' => 'sf_id'] as $source_code => $column) {
		$col_escaped = str_replace('`', '``', $column);
		$sql = "INSERT INTO club_id_map_tasks (club_id, source_code, status)
			SELECT c.id, %s,
				CASE WHEN c.`{$col_escaped}` IS NOT NULL AND TRIM(c.`{$col_escaped}`) <> '' THEN 'done' ELSE 'pending' END
			FROM clubs c
			WHERE c.e_team_id IS NOT NULL
			AND TRIM(c.e_team_id) <> ''
			AND NOT EXISTS (
				SELECT 1 FROM club_id_map_tasks t
				WHERE t.club_id = c.id AND t.source_code = %s
			)";
		$db->query($db->prepare($sql, $source_code, $source_code));
	}

	$db->query('COMMIT');
}

function ffm_sync_task_status_from_clubs(): void {
	$db = kt_ffdb();
	$clubs_column_map = ffm_get_clubs_column_map();

	foreach ($clubs_column_map as $source_code => $column) {
		$col_escaped = str_replace('`', '``', $column);
		$db->query(
			$db->prepare(
				"UPDATE club_id_map_tasks t
				INNER JOIN clubs c ON c.id = t.club_id
				SET t.status = 'done'
				WHERE t.source_code = %s
				AND c.`{$col_escaped}` IS NOT NULL
				AND TRIM(c.`{$col_escaped}`) <> ''
				AND t.status = 'pending'",
				$source_code
			)
		);
	}
}

/**
 * Get schema status for migration verification display.
 * Returns array of column/table names => exists (boolean)
 */
function ffm_get_schema_status(): array {
	$db = kt_ffdb();
	$status = [];

	// Expected new columns in clubs table
	$expected_columns = [
		'fmob_id',
		'sm_id',
		'is_id',
		'sc_id',
		'fmgr_id',
		'id_source',
	];

	// Check which columns exist in clubs table
	$columns_result = $db->get_results("SHOW COLUMNS FROM clubs", ARRAY_A);
	$existing_columns = [];
	if ($columns_result) {
		foreach ($columns_result as $col) {
			$existing_columns[] = $col['Field'];
		}
	}

	foreach ($expected_columns as $col) {
		$status["clubs.{$col}"] = in_array($col, $existing_columns, true);
	}

	// Check if club_aliases table exists
	$table_exists = $db->get_var("SHOW TABLES LIKE 'club_aliases'");
	$status['club_aliases table'] = !empty($table_exists);

	return $status;
}

/**
 * Render the migration controls section (card only, no wrap div).
 * This is called inside the main wrap div.
 */
function ffm_render_migration_controls(): void {
	$runner = new FFM_Migration_Runner();
	$migration_status = $runner->get_status();
	$schema_status = ffm_get_schema_status();

	$pending_count = count($migration_status['pending']);
	$completed_count = count($migration_status['completed']);

	echo '<div class="card" style="max-width: 800px; margin-bottom: 20px; padding: 15px;">';
	echo '<h2 style="margin-top: 0;">Schema Migrations</h2>';

	// Migration status display
	echo '<p>';
	echo '<strong>Pending:</strong> ' . esc_html($pending_count) . ' migration(s) &nbsp; | &nbsp; ';
	echo '<strong>Completed:</strong> ' . esc_html($completed_count) . ' migration(s)';
	echo '</p>';

	// Run migrations button (only if pending migrations exist)
	if ($pending_count > 0) {
		echo '<form method="post" style="margin-bottom: 15px;">';
		wp_nonce_field('ffm_run_migrations', 'ffm_run_migrations_nonce');
		echo '<button type="submit" name="ffm_run_migrations" class="button button-primary">';
		echo 'Run Pending Migrations';
		echo '</button>';
		echo '<span style="margin-left: 10px; color: #666;">';
		echo 'Pending: ' . esc_html(implode(', ', $migration_status['pending']));
		echo '</span>';
		echo '</form>';
	} else {
		echo '<p style="color: #46b450; margin-bottom: 15px;"><strong>All migrations are up to date.</strong></p>';
	}

	// Rollback controls (only if completed migrations exist)
	if ($completed_count > 0) {
		echo '<details style="margin-bottom: 15px;">';
		echo '<summary style="cursor: pointer; color: #666;">Rollback options</summary>';
		echo '<div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 3px solid #ddd;">';
		foreach ($migration_status['completed'] as $migration_id) {
			echo '<form method="post" style="display: inline-block; margin-right: 10px; margin-bottom: 5px;">';
			wp_nonce_field('ffm_rollback_migration', 'ffm_rollback_migration_nonce');
			echo '<input type="hidden" name="migration_id" value="' . esc_attr($migration_id) . '" />';
			echo '<button type="submit" name="ffm_rollback_migration" class="button button-secondary" ';
			echo 'onclick="return confirm(\'Are you sure you want to rollback ' . esc_attr($migration_id) . '? This will remove the schema changes.\');">';
			echo 'Rollback: ' . esc_html($migration_id);
			echo '</button>';
			echo '</form>';
		}
		echo '</div>';
		echo '</details>';
	}

	// Schema verification section
	echo '<h3 style="margin-bottom: 10px;">Current Schema Status</h3>';
	echo '<table class="widefat" style="max-width: 400px;">';
	echo '<thead><tr><th>Column/Table</th><th style="width: 80px;">Status</th></tr></thead>';
	echo '<tbody>';
	foreach ($schema_status as $name => $exists) {
		echo '<tr>';
		echo '<td><code>' . esc_html($name) . '</code></td>';
		if ($exists) {
			echo '<td style="color: #46b450; font-weight: bold;">&#10003; EXISTS</td>';
		} else {
			echo '<td style="color: #dc3232; font-weight: bold;">&#10007; MISSING</td>';
		}
		echo '</tr>';
	}
	echo '</tbody>';
	echo '</table>';

	echo '</div>'; // End card
}

/**
 * Render the CSV Import controls section (card only, no wrap div).
 * This is called inside the main wrap div after migration controls.
 */
function ffm_render_import_controls(): void {
	$import_status = FFM_Import_Runner::get_import_status();

	echo '<div class="card" style="max-width: 800px; margin-bottom: 20px; padding: 15px;">';
	echo '<h2 style="margin-top: 0;">CSV Import</h2>';

	// Run import button with confirmation dialog
	echo '<form method="post" style="margin-bottom: 15px;">';
	wp_nonce_field('ffm_run_import', 'ffm_run_import_nonce');
	echo '<button type="submit" name="ffm_run_import" class="button button-primary" ';
	echo 'onclick="return confirm(\'Run CSV import? This will process all 1,712 clubs from the CSV.\');">';
	echo 'Run CSV Import';
	echo '</button>';
	echo '<span style="margin-left: 10px; color: #666;">';
	echo 'Processes mapping.teamsAlias.csv: matches, auto-applies, and queues uncertain';
	echo '</span>';
	echo '</form>';

	// Display last import stats if available
	if ($import_status['has_been_run']) {
		echo '<h3 style="margin-bottom: 10px;">Last Import</h3>';
		echo '<p><strong>Run at:</strong> ' . esc_html($import_status['last_run']) . '</p>';

		if (!empty($import_status['last_stats'])) {
			$stats = $import_status['last_stats'];
			echo '<table class="widefat" style="max-width: 400px;">';
			echo '<thead><tr><th>Metric</th><th style="width: 100px;">Value</th></tr></thead>';
			echo '<tbody>';

			echo '<tr><td>CSV Rows Processed</td><td><strong>' . esc_html($stats['csv_rows']) . '</strong></td></tr>';

			// Matching stats
			if (!empty($stats['matching'])) {
				echo '<tr><td style="padding-left: 20px;">Exact Matches</td><td>' . esc_html($stats['matching']['exact_match']) . '</td></tr>';
				echo '<tr><td style="padding-left: 20px;">Alias Matches</td><td>' . esc_html($stats['matching']['alias_match']) . '</td></tr>';
				echo '<tr><td style="padding-left: 20px;">Uncertain</td><td>' . esc_html($stats['matching']['uncertain']) . '</td></tr>';
				echo '<tr><td style="padding-left: 20px;">No Match</td><td>' . esc_html($stats['matching']['no_match']) . '</td></tr>';
			}

			// Applied stats
			if (!empty($stats['applied'])) {
				echo '<tr><td>Auto-Applied</td><td style="color: #46b450; font-weight: bold;">' . esc_html($stats['applied']['applied']) . '</td></tr>';
			}

			// Queued stats
			if (!empty($stats['queued'])) {
				echo '<tr><td>Queued for Review</td><td style="color: #f0b849; font-weight: bold;">' . esc_html($stats['queued']['queued']) . '</td></tr>';
			}

			// Errors
			$error_count = !empty($stats['errors']) ? count($stats['errors']) : 0;
			if ($error_count > 0) {
				echo '<tr><td>Errors</td><td style="color: #dc3232; font-weight: bold;">' . esc_html($error_count) . '</td></tr>';
			}

			// Duration
			if (!empty($stats['duration_seconds'])) {
				echo '<tr><td>Duration</td><td>' . esc_html($stats['duration_seconds']) . ' seconds</td></tr>';
			}

			echo '</tbody>';
			echo '</table>';

			// Show errors if any
			if ($error_count > 0) {
				echo '<details style="margin-top: 10px;">';
				echo '<summary style="cursor: pointer; color: #dc3232;">View errors (' . $error_count . ')</summary>';
				echo '<div style="margin-top: 10px; padding: 10px; background: #fef7f1; border-left: 3px solid #dc3232; max-height: 200px; overflow-y: auto;">';
				echo '<ul style="margin: 0; padding-left: 20px;">';
				foreach (array_slice($stats['errors'], 0, 20) as $error) {
					echo '<li>' . esc_html($error) . '</li>';
				}
				if ($error_count > 20) {
					echo '<li>... and ' . ($error_count - 20) . ' more</li>';
				}
				echo '</ul>';
				echo '</div>';
				echo '</details>';
			}
		}
	} else {
		echo '<p style="color: #666; font-style: italic;">Import has not been run yet.</p>';
	}

	echo '</div>'; // End card
}

/**
 * Render the Review Queue tab for uncertain CSV matches.
 */
function ffm_render_review_queue_tab(): void {
	$queue = new FFM_Uncertain_Queue();

	// Check if table exists
	if (!$queue->table_exists()) {
		echo '<div class="notice notice-warning">';
		echo '<p><strong>Review queue table not found.</strong> Run migrations first on the Club Mapper tab.</p>';
		echo '</div>';
		return;
	}

	// Pagination
	$per_page = 50;
	$review_paged = isset($_GET['review_paged']) ? max(1, (int) $_GET['review_paged']) : 1;
	$offset = ($review_paged - 1) * $per_page;

	$pending_count = $queue->get_pending_count();
	$total_pages = (int) ceil($pending_count / $per_page);
	$items = $queue->get_pending_items($per_page, $offset);

	echo '<h2>Review Queue</h2>';
	echo '<p>Pending items for review: <strong>' . esc_html($pending_count) . '</strong></p>';

	if (empty($items)) {
		echo '<div class="notice notice-info">';
		echo '<p>No pending items in the review queue. Import a CSV with uncertain matches to populate this queue.</p>';
		echo '</div>';
		return;
	}

	echo '<form method="post" id="ffm-review-form">';
	wp_nonce_field('ffm_review_action', 'ffm_review_action_nonce');
	wp_nonce_field('ffm_batch_review', 'ffm_batch_review_nonce');
	echo '<input type="hidden" name="review_paged" value="' . esc_attr($review_paged) . '" />';

	// Batch action bar
	echo '<div style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">';
	echo '<select name="batch_action" id="ffm-batch-action">';
	echo '<option value="">Bulk Actions</option>';
	echo '<option value="reject_all">Reject Selected</option>';
	echo '<option value="skip_all">Skip Selected</option>';
	echo '</select>';
	echo '<button type="submit" name="ffm_batch_review" value="1" class="button">Apply</button>';
	echo '<span id="ffm-selected-count" style="color: #666; margin-left: 10px;"></span>';
	echo '</div>';

	echo '<table class="widefat striped">';
	echo '<thead><tr>';
	echo '<th style="width:40px;"><input type="checkbox" id="ffm-select-all" /></th>';
	echo '<th style="width:200px;">CSV Name</th>';
	echo '<th style="width:100px;">Country</th>';
	echo '<th style="width:100px;">Status</th>';
	echo '<th style="width:80px;">Confidence</th>';
	echo '<th style="width:350px;">Candidates</th>';
	echo '<th style="width:200px;">Actions</th>';
	echo '</tr></thead><tbody>';

	foreach ($items as $item) {
		$queue_id = (int) $item['id'];
		$csv_name = esc_html($item['csv_name']);
		$csv_country = esc_html($item['csv_country'] ?? 'N/A');
		$match_status = esc_html($item['match_status']);
		$confidence = esc_html($item['confidence']);
		$candidates = $item['candidates'];

		echo '<tr>';
		echo '<td><input type="checkbox" name="review_ids[]" value="' . esc_attr($queue_id) . '" class="ffm-review-checkbox" /></td>';
		echo '<td><strong>' . $csv_name . '</strong><br><small style="color:#666;">Row ' . esc_html($item['csv_row_index']) . '</small></td>';
		echo '<td>' . $csv_country . '</td>';
		echo '<td><span style="color:' . ($match_status === 'no_match' ? '#dc3232' : '#f0b849') . ';">' . $match_status . '</span></td>';
		echo '<td>' . $confidence . '</td>';
		echo '<td>';

		if (!empty($candidates)) {
			echo '<div style="max-height: 150px; overflow-y: auto;">';
			foreach ($candidates as $idx => $candidate) {
				$checked = ($idx === 0) ? 'checked' : '';
				echo '<label style="display: block; margin-bottom: 5px;">';
				echo '<input type="radio" name="candidate_club_id[' . $queue_id . ']" value="' . esc_attr($candidate['id']) . '" ' . $checked . ' /> ';
				echo esc_html($candidate['canonical_name']);
				if (!empty($candidate['competition_code'])) {
					echo ' <small style="color:#666;">(' . esc_html($candidate['competition_code']) . ')</small>';
				}
				echo '</label>';
			}
			echo '</div>';
		} else {
			echo '<span style="color:#999;">No candidates found</span>';
		}

		echo '</td>';
		echo '<td>';

		// Approve button (only if candidates exist)
		if (!empty($candidates)) {
			echo '<button type="submit" name="ffm_review_action" value="approve" class="button button-primary button-small" ';
			echo 'onclick="document.querySelector(\'input[name=queue_id]\').value=\'' . $queue_id . '\'; document.querySelector(\'input[name=club_id]\').value=document.querySelector(\'input[name=candidate_club_id[' . $queue_id . ']]:checked\').value;" ';
			echo 'style="margin-right: 5px;">Approve</button>';
		}

		// Reject button
		echo '<button type="submit" name="ffm_review_action" value="reject" class="button button-secondary button-small" ';
		echo 'onclick="document.querySelector(\'input[name=queue_id]\').value=\'' . $queue_id . '\';" ';
		echo 'style="margin-right: 5px;">Reject</button>';

		// Skip button
		echo '<button type="submit" name="ffm_review_action" value="skip" class="button button-small" ';
		echo 'onclick="document.querySelector(\'input[name=queue_id]\').value=\'' . $queue_id . '\';" ';
		echo '>Skip</button>';

		echo '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';

	// Hidden fields for form submission
	echo '<input type="hidden" name="queue_id" value="" />';
	echo '<input type="hidden" name="club_id" value="" />';

	echo '</form>';

	// Pagination
	if ($total_pages > 1) {
		$base_url = admin_url('admin.php?page=ffm-club-id-mapper&tab=review');
		$prev_paged = max(1, $review_paged - 1);
		$next_paged = min($total_pages, $review_paged + 1);

		$prev_url = add_query_arg('review_paged', $prev_paged, $base_url);
		$next_url = add_query_arg('review_paged', $next_paged, $base_url);

		echo '<div style="margin-top:12px; display:flex; gap:8px; align-items:center;">';
		if ($review_paged > 1) {
			echo '<a class="button" href="' . esc_url($prev_url) . '">Prev</a>';
		} else {
			echo '<span class="button" style="opacity:0.5; cursor:not-allowed;">Prev</span>';
		}
		if ($review_paged < $total_pages) {
			echo '<a class="button button-primary" href="' . esc_url($next_url) . '">Next</a>';
		} else {
			echo '<span class="button button-primary" style="opacity:0.5; cursor:not-allowed;">Next</span>';
		}
		echo '<span>Page ' . (int) $review_paged . ' of ' . (int) $total_pages . '</span>';
		echo '</div>';
	}

	// JavaScript for Select All and selected count
	?>
	<script>
	(function() {
		var selectAllCheckbox = document.getElementById('ffm-select-all');
		var rowCheckboxes = document.querySelectorAll('.ffm-review-checkbox');
		var selectedCountSpan = document.getElementById('ffm-selected-count');

		function updateSelectedCount() {
			var checked = document.querySelectorAll('.ffm-review-checkbox:checked').length;
			if (checked > 0) {
				selectedCountSpan.textContent = checked + ' item(s) selected';
			} else {
				selectedCountSpan.textContent = '';
			}
		}

		if (selectAllCheckbox) {
			selectAllCheckbox.addEventListener('change', function() {
				rowCheckboxes.forEach(function(cb) {
					cb.checked = selectAllCheckbox.checked;
				});
				updateSelectedCount();
			});
		}

		rowCheckboxes.forEach(function(cb) {
			cb.addEventListener('change', function() {
				// Update Select All state
				var allChecked = true;
				var anyChecked = false;
				rowCheckboxes.forEach(function(c) {
					if (!c.checked) allChecked = false;
					if (c.checked) anyChecked = true;
				});
				if (selectAllCheckbox) {
					selectAllCheckbox.checked = allChecked;
					selectAllCheckbox.indeterminate = anyChecked && !allChecked;
				}
				updateSelectedCount();
			});
		});

		updateSelectedCount();
	})();
	</script>
	<?php
}

function ffm_render_club_id_mapper_page(): void {
	if (!current_user_can('manage_options')) {
		wp_die('Not allowed.');
	}

	global $sources_non_opta;

	echo '<div class="wrap">';
	echo '<h1>Club ID Mapper</h1>';

	settings_errors('ffm');

	// Tab navigation
	$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'mapper';
	echo '<nav class="nav-tab-wrapper" style="margin-bottom: 20px;">';
	echo '<a href="?page=ffm-club-id-mapper&tab=mapper" class="nav-tab ' . ($current_tab === 'mapper' ? 'nav-tab-active' : '') . '">Club Mapper</a>';
	$queue = new FFM_Uncertain_Queue();
	$pending_count = $queue->table_exists() ? $queue->get_pending_count() : 0;
	echo '<a href="?page=ffm-club-id-mapper&tab=review" class="nav-tab ' . ($current_tab === 'review' ? 'nav-tab-active' : '') . '">Review Queue';
	if ($pending_count > 0) {
		echo ' <span class="count" style="background: #d63638; color: white; border-radius: 10px; padding: 2px 8px; font-size: 11px; margin-left: 5px;">' . esc_html($pending_count) . '</span>';
	}
	echo '</a>';
	echo '</nav>';

	// Route to appropriate tab
	if ($current_tab === 'review') {
		ffm_render_review_queue_tab();
		echo '</div>'; // End wrap
		return;
	}

	// Mapper tab content below
	$db = kt_ffdb();

	// Render migration controls section at top of page
	ffm_render_migration_controls();

	// Render CSV import controls section
	ffm_render_import_controls();

	ffm_backfill_missing_tasks();
	ffm_sync_task_status_from_clubs();

	$db_exists = $db->get_var("SHOW DATABASES LIKE 'footyforums_data'");
	$table_clubs = $db->get_var("SHOW TABLES LIKE 'clubs'");
	$table_club_id_map_tasks = $db->get_var("SHOW TABLES LIKE 'club_id_map_tasks'");

	if (empty($db_exists) || empty($table_clubs) || empty($table_club_id_map_tasks)) {
		echo '<div class="notice notice-error">';
		echo '<p><strong>Database or table check failed</strong></p>';
		echo '<ul>';
		echo '<li>Target database: <code>footyforums_data</code></li>';
		echo '<li>Database exists: ' . (empty($db_exists) ? '<strong>MISSING</strong>' : 'FOUND') . '</li>';
		echo '<li>Table clubs: ' . (empty($table_clubs) ? '<strong>MISSING</strong>' : 'FOUND') . '</li>';
		echo '<li>Table club_id_map_tasks: ' . (empty($table_club_id_map_tasks) ? '<strong>MISSING</strong>' : 'FOUND') . '</li>';
		if (!empty($db->last_error)) {
			echo '<li>Database error: <code>' . esc_html($db->last_error) . '</code></li>';
		}
		echo '</ul>';
		echo '</div>';
		echo '</div>';
		return;
	}

	$region_group_code = isset($_GET['region_group_code']) ? sanitize_text_field($_GET['region_group_code']) : '';
	$competition_code = isset($_GET['competition_code']) ? sanitize_text_field($_GET['competition_code']) : '';
	$include_opta = isset($_GET['include_opta']) && $_GET['include_opta'] === '1';
	$per_page = 200;
	$paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
	$offset = ($paged - 1) * $per_page;

	$clubs = ffm_get_queue_page_clubs($per_page, $offset, $region_group_code, $competition_code, $include_opta);
	$total_clubs = ffm_get_queue_count_clubs($region_group_code, $competition_code, $include_opta);
	$total_pages = (int) ceil($total_clubs / $per_page);

	$regions = ffm_get_regions();
	$leagues = ffm_get_leagues_for_region($region_group_code);

	$club_ids = array_map(function($c) { return (int) $c['club_id']; }, $clubs);
	$task_statuses = ffm_get_task_statuses_for_clubs($club_ids, $include_opta);
	$pending_tasks = ffm_get_pending_tasks_for_clubs($club_ids, $include_opta);
	$existing_ids = ffm_get_existing_provider_ids($club_ids, $include_opta);

	echo '<form method="get" id="ffm-filter-form" style="margin:12px 0;">';
	echo '<input type="hidden" name="page" value="ffm-club-id-mapper" />';
	if ($paged > 1) {
		echo '<input type="hidden" name="paged" id="ffm-paged-input" value="' . esc_attr($paged) . '" />';
	}

	echo '<label style="margin-right:8px;">Region</label>';
	echo '<select name="region_group_code" id="ffm-region-filter">';
	echo '<option value="">All</option>';
	foreach ($regions as $r) {
		$sel = ($region_group_code === $r['region_group_code']) ? 'selected' : '';
		echo '<option value="' . esc_attr($r['region_group_code']) . '" ' . $sel . '>' . esc_html($r['name']) . '</option>';
	}
	echo '</select>';

	echo '<label style="margin-left:12px;margin-right:8px;">League</label>';
	echo '<select name="competition_code" id="ffm-competition-filter">';
	echo '<option value="">All</option>';
	foreach ($leagues as $l) {
		$sel = ($competition_code === $l['competition_code']) ? 'selected' : '';
		echo '<option value="' . esc_attr($l['competition_code']) . '" ' . $sel . '>' . esc_html($l['name']) . '</option>';
	}
	echo '</select>';

	echo '<label style="margin-left:12px;margin-right:8px;">';
	echo '<input type="checkbox" name="include_opta" value="1" id="ffm-include-opta" ' . ($include_opta ? 'checked' : '') . ' />';
	echo ' Including Opta';
	echo '</label>';

	echo '<button class="button" type="submit">Apply filters</button>';
	echo '</form>';

	echo '<table class="widefat striped">';
	echo '<thead><tr>';
	echo '<th style="width:80px">Copy</th>';
	echo '<th style="width:200px">Club</th>';
	echo '<th style="width:120px">Region</th>';
	echo '<th style="width:180px">League</th>';

	foreach ($sources_non_opta as $code => $label) {
		if ($code === 'opta' && !$include_opta) {
			continue;
		}
		echo '<th style="width:140px">' . esc_html($label) . '</th>';
	}

	echo '<th style="width:100px">Save</th>';
	echo '</tr></thead><tbody>';

	if (!$clubs) {
		$colspan = 5 + count($sources_non_opta);
		echo '<tr><td colspan="' . $colspan . '">No clubs found.</td></tr>';
	} else {
		foreach ($clubs as $club) {
			$club_id = (int) $club['club_id'];
			$club_name = (string) $club['canonical_name'];
			$espn_id = (string) $club['e_team_id'];
			$region_name = (string) ($club['region_name'] ?? 'Unassigned');
			$league_name = (string) ($club['league_name'] ?? 'Unassigned');

			$has_pending = isset($pending_tasks[$club_id]) && !empty($pending_tasks[$club_id]);

			echo '<form method="post">';
			wp_nonce_field('ffm_save_club', 'ffm_save_club_nonce');
			echo '<input type="hidden" name="ffm_save_club" value="1" />';
			echo '<input type="hidden" name="club_id" value="' . esc_attr($club_id) . '" />';
			if ($region_group_code !== '') {
				echo '<input type="hidden" name="region_group_code" value="' . esc_attr($region_group_code) . '" />';
			}
			if ($competition_code !== '') {
				echo '<input type="hidden" name="competition_code" value="' . esc_attr($competition_code) . '" />';
			}
			if ($include_opta) {
				echo '<input type="hidden" name="include_opta" value="1" />';
			}
			if ($paged > 0) {
				echo '<input type="hidden" name="paged" value="' . esc_attr($paged) . '" />';
			}

			echo '<tr>';
			echo '<td><button type="button" class="button button-small ffm-copy" data-copy="' . esc_attr($club_name) . '">Copy</button></td>';
			echo '<td>' . esc_html($club_name) . '<br><small style="color:#666;">club_id ' . $club_id . ' | espn ' . esc_html($espn_id) . '</small></td>';
			echo '<td>' . esc_html($region_name) . '</td>';
			echo '<td>' . esc_html($league_name) . '</td>';

			foreach ($sources_non_opta as $code => $label) {
				if ($code === 'opta' && !$include_opta) {
					continue;
				}
				// $code is the provider_code - use it directly as the key for lookups
				$existing_value = isset($existing_ids[$club_id][$code]) ? trim((string) $existing_ids[$club_id][$code]) : '';
				$task_status = isset($task_statuses[$club_id][$code]) ? trim((string) $task_statuses[$club_id][$code]) : 'pending';
				$is_pending = ($task_status === 'pending');

				if ($is_pending) {
					echo '<td style="width:140px;">';
					echo '<input type="text" name="ffm_id[' . esc_attr($code) . ']" value="" placeholder="' . esc_attr($existing_value) . '" style="width:100px;" />';
					if ($existing_value !== '') {
						echo '<br><small style="color:#666; font-size:11px;">Current: ' . esc_html($existing_value) . '</small>';
					}
					echo '<br><label style="font-size:11px;"><input type="checkbox" name="ffm_na[' . esc_attr($code) . ']" value="1" /> NA</label>';
					echo '</td>';
				} elseif ($task_status === 'done') {
					echo '<td style="width:140px;">';
					if ($existing_value !== '') {
						echo '<span style="color:#46b450;">✓ ' . esc_html($existing_value) . '</span>';
					} else {
						echo '<span style="color:#dc3232; font-weight:bold;">DONE (missing id)</span>';
					}
					echo '</td>';
				} else {
					echo '<td style="width:140px;">';
					echo '<span style="color:#999;">NA</span>';
					echo '</td>';
				}
			}

			echo '<td>';
			if ($has_pending) {
				echo '<button class="button button-small button-primary" type="submit">Save row</button>';
			} else {
				echo '<span style="color:#46b450;">✓ Done</span>';
			}
			echo '</td>';

			echo '</tr>';
			echo '</form>';
		}
	}

	echo '</tbody></table>';

	$base_url = admin_url('admin.php?page=ffm-club-id-mapper');
	$qs = [];
	if ($region_group_code !== '') {
		$qs['region_group_code'] = $region_group_code;
	}
	if ($competition_code !== '') {
		$qs['competition_code'] = $competition_code;
	}
	if ($include_opta) {
		$qs['include_opta'] = '1';
	}

	$prev_paged = max(1, $paged - 1);
	$next_paged = min($total_pages, $paged + 1);

	$prev_url = add_query_arg(array_merge($qs, ['paged' => $prev_paged]), $base_url);
	$next_url = add_query_arg(array_merge($qs, ['paged' => $next_paged]), $base_url);

	echo '<div style="margin-top:12px; display:flex; gap:8px; align-items:center;">';
	if ($paged > 1) {
	echo '<a class="button" href="' . esc_url($prev_url) . '">Prev</a>';
	} else {
		echo '<span class="button" style="opacity:0.5; cursor:not-allowed;">Prev</span>';
	}
	if ($paged < $total_pages) {
	echo '<a class="button button-primary" href="' . esc_url($next_url) . '">Next</a>';
	} else {
		echo '<span class="button button-primary" style="opacity:0.5; cursor:not-allowed;">Next</span>';
	}
	echo '<span>Page ' . (int) $paged . ' of ' . (int) $total_pages . '</span>';
	echo '</div>';

	echo '</div>';

	?>
	<script>
	(function() {
		function copyText(text) {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				return navigator.clipboard.writeText(text);
			}
			const ta = document.createElement('textarea');
			ta.value = text;
			document.body.appendChild(ta);
			ta.select();
			document.execCommand('copy');
			document.body.removeChild(ta);
			return Promise.resolve();
		}

		document.addEventListener('click', function(e) {
			const btn = e.target.closest('.ffm-copy');
			if (!btn) return;
			const text = btn.getAttribute('data-copy') || '';
			copyText(text).then(function() {
				btn.textContent = 'Copied';
				setTimeout(function(){ btn.textContent = 'Copy'; }, 700);
			});
		});

		let submitting = false;

		const regionSelect = document.getElementById('ffm-region-filter');
		const leagueSelect = document.getElementById('ffm-competition-filter');
		const optaCheckbox = document.getElementById('ffm-include-opta');
		const form = document.getElementById('ffm-filter-form');

		if (regionSelect && form) {
			regionSelect.addEventListener('change', function() {
				if (submitting) return;
				submitting = true;
				
				// Reset league to "All"
				if (leagueSelect) {
					leagueSelect.value = '';
				}
				
				// Reset paged to 1
				let pagedInput = document.getElementById('ffm-paged-input');
				if (pagedInput) {
					pagedInput.value = '1';
				} else {
					pagedInput = document.createElement('input');
					pagedInput.type = 'hidden';
					pagedInput.name = 'paged';
					pagedInput.id = 'ffm-paged-input';
					pagedInput.value = '1';
					form.appendChild(pagedInput);
				}
				
				form.submit();
			});
		}

		if (leagueSelect && form) {
			leagueSelect.addEventListener('change', function() {
				if (submitting) return;
				submitting = true;
				
				// Reset paged to 1
				let pagedInput = document.getElementById('ffm-paged-input');
				if (pagedInput) {
					pagedInput.value = '1';
				} else {
					pagedInput = document.createElement('input');
					pagedInput.type = 'hidden';
					pagedInput.name = 'paged';
					pagedInput.id = 'ffm-paged-input';
					pagedInput.value = '1';
					form.appendChild(pagedInput);
				}
				
				form.submit();
			});
		}
	})();
	</script>
	<?php
}
