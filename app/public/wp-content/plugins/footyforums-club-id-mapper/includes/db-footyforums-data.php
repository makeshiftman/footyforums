<?php
defined('ABSPATH') || exit;

/**
 * Secondary DB connection to football data database (footyforums_data).
 * Uses the same DB user/pass/host as WordPress, but a different DB name.
 */
function kt_ffdb(): wpdb {
	static $ffdb = null;

	if ($ffdb instanceof wpdb) {
		return $ffdb;
	}

	// Use FOOTYFORUMS_DB_* constants if defined, fall back to DB_* constants
	$user = defined( 'FOOTYFORUMS_DB_USER' ) ? FOOTYFORUMS_DB_USER : DB_USER;
	$pass = defined( 'FOOTYFORUMS_DB_PASSWORD' ) ? FOOTYFORUMS_DB_PASSWORD : DB_PASSWORD;
	$host = defined( 'FOOTYFORUMS_DB_HOST' ) ? FOOTYFORUMS_DB_HOST : DB_HOST;
	$db_name = defined( 'FOOTYFORUMS_DB_NAME' ) ? FOOTYFORUMS_DB_NAME : 'footyforums_data';

	$ffdb = new wpdb($user, $pass, $db_name, $host);

	if (!empty($GLOBALS['wpdb']->charset)) {
		$ffdb->set_charset($ffdb->dbh, $GLOBALS['wpdb']->charset, $GLOBALS['wpdb']->collate);
	}

	$ffdb->hide_errors();

	return $ffdb;
}
