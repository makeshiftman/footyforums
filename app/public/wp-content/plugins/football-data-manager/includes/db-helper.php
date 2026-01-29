<?php
/**
 * Database Helper for footyforums_data Database
 * Provides connection and table management for the external football database
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the external database name with fallback logic
 * 
 * Priority:
 * 1. FOOTYFORUMS_DB_NAME constant (if defined)
 * 2. fdm_external_db_name plugin option
 * 3. Current WordPress database name (fallback)
 * 
 * @return string Database name
 */
function fdm_get_external_db_name() {
    global $wpdb;
    
    // 1. Constant wins
    if ( defined( 'FOOTYFORUMS_DB_NAME' ) && FOOTYFORUMS_DB_NAME ) {
        return FOOTYFORUMS_DB_NAME;
    }
    
    // 2. Plugin option
    $opt = get_option( 'fdm_external_db_name' );
    if ( ! empty( $opt ) ) {
        return $opt;
    }
    
    // 3. Fallback to current WP DB
    return $wpdb->dbname;
}

/**
 * Get the footyforums_data database connection
 * 
 * @return wpdb|false Database connection object or false on failure
 */
function fdm_get_footyforums_db() {
    global $footyforums_db;
    
    // Check if connection already exists
    if ( isset( $footyforums_db ) && $footyforums_db instanceof wpdb ) {
        return $footyforums_db;
    }
    
    // Get database name using helper function
    $db_name = fdm_get_external_db_name();
    
    if ( empty( $db_name ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'fdm_get_footyforums_db: No external database name available' );
        }
        return false;
    }
    
    // Use FOOTYFORUMS_DB_* constants if defined, fall back to DB_* constants
    $user = defined( 'FOOTYFORUMS_DB_USER' ) ? FOOTYFORUMS_DB_USER : DB_USER;
    $pass = defined( 'FOOTYFORUMS_DB_PASSWORD' ) ? FOOTYFORUMS_DB_PASSWORD : DB_PASSWORD;
    $host = defined( 'FOOTYFORUMS_DB_HOST' ) ? FOOTYFORUMS_DB_HOST : DB_HOST;
    
    // Create new database connection
    $footyforums_db = new wpdb(
        $user,
        $pass,
        $db_name,
        $host
    );
    
    // Test connection
    if ( $footyforums_db->last_error ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'footyforums_data database connection error: ' . $footyforums_db->last_error );
        }
        return false;
    }
    
    return $footyforums_db;
}

/**
 * Create required tables in footyforums_data database
 * Called on plugin activation or manually
 */
function fdm_create_footyforums_tables() {
    $db = fdm_get_footyforums_db();
    if ( ! $db ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'fdm_create_footyforums_tables: Cannot connect to footyforums_data database' );
        }
        return false;
    }
    
    // Get charset and collate from the database connection
    $charset_collate = $db->get_charset_collate();
    if ( empty( $charset_collate ) ) {
        $charset_collate = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }
    
    // Table: match_extras
    $sql_match_extras = "CREATE TABLE IF NOT EXISTS `match_extras` (
        `match_id` VARCHAR(50) NOT NULL PRIMARY KEY,
        `venue` VARCHAR(255) DEFAULT NULL,
        `referee` VARCHAR(255) DEFAULT NULL,
        `attendance` INT(11) DEFAULT NULL,
        `match_status` VARCHAR(50) DEFAULT NULL,
        `half_time_home_score` INT(11) DEFAULT NULL,
        `half_time_away_score` INT(11) DEFAULT NULL,
        INDEX `idx_match_status` (`match_status`)
    ) $charset_collate;";
    
    $result1 = $db->query( $sql_match_extras );
    if ( $result1 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating match_extras: ' . $db->last_error );
    }
    
    // Table: datasource_errors
    $sql_datasource_errors = "CREATE TABLE IF NOT EXISTS `datasource_errors` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `error_type` VARCHAR(50) NOT NULL,
        `error_message` TEXT NOT NULL,
        `context_data` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_error_type` (`error_type`),
        INDEX `idx_created_at` (`created_at`)
    ) $charset_collate;";
    
    $result2 = $db->query( $sql_datasource_errors );
    if ( $result2 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating datasource_errors: ' . $db->last_error );
    }
    
    // Table: datasource_log
    $sql_datasource_log = "CREATE TABLE IF NOT EXISTS `datasource_log` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `log_level` VARCHAR(20) NOT NULL DEFAULT 'info',
        `log_message` TEXT NOT NULL,
        `context_data` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_log_level` (`log_level`),
        INDEX `idx_created_at` (`created_at`)
    ) $charset_collate;";
    
    $result3 = $db->query( $sql_datasource_log );
    if ( $result3 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating datasource_log: ' . $db->last_error );
    }
    
    // Table: competition_map
    $sql_competition_map = "CREATE TABLE IF NOT EXISTS `competition_map` (
        `espn_code` VARCHAR(50) NOT NULL PRIMARY KEY,
        `division_name` VARCHAR(100) NOT NULL,
        `tier` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    $result4 = $db->query( $sql_competition_map );
    if ( $result4 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating competition_map: ' . $db->last_error );
    }
    
    // Populate competition_map with default mappings
    $default_mappings = array(
        array( 'espn_code' => 'eng.1', 'division_name' => 'Premier League', 'tier' => 1 ),
        array( 'espn_code' => 'eng.fa', 'division_name' => 'FA Cup', 'tier' => NULL ),
        array( 'espn_code' => 'eng.league_cup', 'division_name' => 'EFL Cup', 'tier' => NULL ),
        array( 'espn_code' => 'uefa.champions', 'division_name' => 'Champions League', 'tier' => NULL ),
        array( 'espn_code' => 'uefa.europa', 'division_name' => 'Europa League', 'tier' => NULL ),
        array( 'espn_code' => 'uefa.europa_conference', 'division_name' => 'Conference League', 'tier' => NULL ),
    );
    
    foreach ( $default_mappings as $mapping ) {
        $db->replace(
            'competition_map',
            $mapping,
            array( '%s', '%s', '%d' )
        );
    }
    
    // Table: fdm_player_season_stats
    $sql_player_season_stats = "CREATE TABLE IF NOT EXISTS `fdm_player_season_stats` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `player_id` VARCHAR(50) NOT NULL,
        `season_id` VARCHAR(50) NOT NULL,
        `league_code` VARCHAR(50) NOT NULL,
        `team_id` VARCHAR(50) DEFAULT NULL,
        `games_played` INT(11) DEFAULT 0,
        `goals` INT(11) DEFAULT 0,
        `assists` INT(11) DEFAULT 0,
        `yellow_cards` INT(11) DEFAULT 0,
        `red_cards` INT(11) DEFAULT 0,
        `minutes_played` INT(11) DEFAULT 0,
        `stats_json` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_player_season_league` (`player_id`, `season_id`, `league_code`),
        INDEX `idx_player_id` (`player_id`),
        INDEX `idx_season_id` (`season_id`),
        INDEX `idx_league_code` (`league_code`),
        INDEX `idx_team_id` (`team_id`)
    ) ENGINE=InnoDB $charset_collate;";
    
    $result5 = $db->query( $sql_player_season_stats );
    if ( $result5 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating fdm_player_season_stats: ' . $db->last_error );
    }
    
    // Table: fdm_team_season_stats
    $sql_team_season_stats = "CREATE TABLE IF NOT EXISTS `fdm_team_season_stats` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `team_id` VARCHAR(50) NOT NULL,
        `season_id` VARCHAR(50) NOT NULL,
        `league_code` VARCHAR(50) NOT NULL,
        `games_played` INT(11) DEFAULT 0,
        `wins` INT(11) DEFAULT 0,
        `draws` INT(11) DEFAULT 0,
        `losses` INT(11) DEFAULT 0,
        `goals_for` INT(11) DEFAULT 0,
        `goals_against` INT(11) DEFAULT 0,
        `points` INT(11) DEFAULT 0,
        `stats_json` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_team_season_league` (`team_id`, `season_id`, `league_code`),
        INDEX `idx_team_id` (`team_id`),
        INDEX `idx_season_id` (`season_id`),
        INDEX `idx_league_code` (`league_code`)
    ) ENGINE=InnoDB $charset_collate;";
    
    $result6 = $db->query( $sql_team_season_stats );
    if ( $result6 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating fdm_team_season_stats: ' . $db->last_error );
    }
    
    // Table: fdm_standings
    $sql_standings = "CREATE TABLE IF NOT EXISTS `fdm_standings` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `league_code` VARCHAR(50) NOT NULL,
        `season_id` VARCHAR(50) NOT NULL,
        `team_id` VARCHAR(50) NOT NULL,
        `position` INT(11) NOT NULL,
        `points` INT(11) DEFAULT 0,
        `games_played` INT(11) DEFAULT 0,
        `wins` INT(11) DEFAULT 0,
        `draws` INT(11) DEFAULT 0,
        `losses` INT(11) DEFAULT 0,
        `goals_for` INT(11) DEFAULT 0,
        `goals_against` INT(11) DEFAULT 0,
        `goal_difference` INT(11) DEFAULT 0,
        `form` VARCHAR(10) DEFAULT NULL,
        `standings_json` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_league_season_team` (`league_code`, `season_id`, `team_id`),
        INDEX `idx_league_code` (`league_code`),
        INDEX `idx_season_id` (`season_id`),
        INDEX `idx_team_id` (`team_id`),
        INDEX `idx_position` (`position`)
    ) ENGINE=InnoDB $charset_collate;";
    
    $result7 = $db->query( $sql_standings );
    if ( $result7 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating fdm_standings: ' . $db->last_error );
    }
    
    // Table: fdm_injuries
    $sql_injuries = "CREATE TABLE IF NOT EXISTS `fdm_injuries` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `player_id` VARCHAR(50) NOT NULL,
        `team_id` VARCHAR(50) DEFAULT NULL,
        `injury_type` VARCHAR(100) DEFAULT NULL,
        `injury_status` VARCHAR(50) DEFAULT NULL,
        `expected_return_date` DATE DEFAULT NULL,
        `injury_details` TEXT DEFAULT NULL,
        `source` VARCHAR(50) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_player_id` (`player_id`),
        INDEX `idx_team_id` (`team_id`),
        INDEX `idx_injury_status` (`injury_status`),
        INDEX `idx_expected_return_date` (`expected_return_date`)
    ) ENGINE=InnoDB $charset_collate;";
    
    $result8 = $db->query( $sql_injuries );
    if ( $result8 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating fdm_injuries: ' . $db->last_error );
    }
    
    // Table: fdm_suspensions
    $sql_suspensions = "CREATE TABLE IF NOT EXISTS `fdm_suspensions` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `player_id` VARCHAR(50) NOT NULL,
        `team_id` VARCHAR(50) DEFAULT NULL,
        `suspension_type` VARCHAR(100) DEFAULT NULL,
        `suspension_status` VARCHAR(50) DEFAULT NULL,
        `start_date` DATE DEFAULT NULL,
        `end_date` DATE DEFAULT NULL,
        `matches_remaining` INT(11) DEFAULT NULL,
        `suspension_details` TEXT DEFAULT NULL,
        `source` VARCHAR(50) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_player_id` (`player_id`),
        INDEX `idx_team_id` (`team_id`),
        INDEX `idx_suspension_status` (`suspension_status`),
        INDEX `idx_end_date` (`end_date`)
    ) ENGINE=InnoDB $charset_collate;";
    
    $result9 = $db->query( $sql_suspensions );
    if ( $result9 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating fdm_suspensions: ' . $db->last_error );
    }
    
    // Table: fdm_tournament_groups
    $sql_tournament_groups = "CREATE TABLE IF NOT EXISTS `fdm_tournament_groups` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `tournament_id` VARCHAR(50) NOT NULL,
        `group_name` VARCHAR(100) NOT NULL,
        `group_letter` VARCHAR(10) DEFAULT NULL,
        `season_id` VARCHAR(50) DEFAULT NULL,
        `league_code` VARCHAR(50) DEFAULT NULL,
        `group_json` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_tournament_group` (`tournament_id`, `group_name`),
        INDEX `idx_tournament_id` (`tournament_id`),
        INDEX `idx_season_id` (`season_id`),
        INDEX `idx_league_code` (`league_code`)
    ) ENGINE=InnoDB $charset_collate;";
    
    $result10 = $db->query( $sql_tournament_groups );
    if ( $result10 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating fdm_tournament_groups: ' . $db->last_error );
    }
    
    // Table: fdm_tournament_brackets
    $sql_tournament_brackets = "CREATE TABLE IF NOT EXISTS `fdm_tournament_brackets` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `tournament_id` VARCHAR(50) NOT NULL,
        `bracket_type` VARCHAR(50) NOT NULL,
        `round_name` VARCHAR(100) DEFAULT NULL,
        `match_id` VARCHAR(50) DEFAULT NULL,
        `team1_id` VARCHAR(50) DEFAULT NULL,
        `team2_id` VARCHAR(50) DEFAULT NULL,
        `winner_id` VARCHAR(50) DEFAULT NULL,
        `bracket_json` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_tournament_id` (`tournament_id`),
        INDEX `idx_bracket_type` (`bracket_type`),
        INDEX `idx_match_id` (`match_id`),
        INDEX `idx_team1_id` (`team1_id`),
        INDEX `idx_team2_id` (`team2_id`)
    ) ENGINE=InnoDB $charset_collate;";
    
    $result11 = $db->query( $sql_tournament_brackets );
    if ( $result11 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating fdm_tournament_brackets: ' . $db->last_error );
    }
    
    // Table: fdm_player_match_stats
    $sql_player_match_stats = "CREATE TABLE IF NOT EXISTS `fdm_player_match_stats` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `match_id` VARCHAR(50) NOT NULL,
        `player_id` VARCHAR(50) NOT NULL,
        `team_id` VARCHAR(50) DEFAULT NULL,
        `position` VARCHAR(20) DEFAULT NULL,
        `minutes_played` INT(11) DEFAULT 0,
        `goals` INT(11) DEFAULT 0,
        `assists` INT(11) DEFAULT 0,
        `yellow_cards` INT(11) DEFAULT 0,
        `red_cards` INT(11) DEFAULT 0,
        `shots` INT(11) DEFAULT 0,
        `shots_on_target` INT(11) DEFAULT 0,
        `passes` INT(11) DEFAULT 0,
        `passes_completed` INT(11) DEFAULT 0,
        `tackles` INT(11) DEFAULT 0,
        `interceptions` INT(11) DEFAULT 0,
        `stats_json` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_match_player` (`match_id`, `player_id`),
        INDEX `idx_match_id` (`match_id`),
        INDEX `idx_player_id` (`player_id`),
        INDEX `idx_team_id` (`team_id`)
    ) ENGINE=InnoDB $charset_collate;";
    
    $result12 = $db->query( $sql_player_match_stats );
    if ( $result12 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating fdm_player_match_stats: ' . $db->last_error );
    }
    
    // Table: fdm_match_team_stats
    $sql_match_team_stats = "CREATE TABLE IF NOT EXISTS `fdm_match_team_stats` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `e_match_id` VARCHAR(64) NOT NULL,
        `club_id` BIGINT(20) UNSIGNED NOT NULL,
        `e_team_id` VARCHAR(32) NOT NULL,
        `competition_code` VARCHAR(64) NOT NULL,
        `season_year` INT(11) NOT NULL,
        `is_home` TINYINT(1) NOT NULL DEFAULT 0,
        `goals` INT(11) DEFAULT 0,
        `shots` INT(11) DEFAULT 0,
        `shots_on_target` INT(11) DEFAULT 0,
        `possession` DECIMAL(5,2) DEFAULT NULL,
        `pass_accuracy` DECIMAL(5,2) DEFAULT NULL,
        `fouls` INT(11) DEFAULT 0,
        `tackles` INT(11) DEFAULT 0,
        `offsides` INT(11) DEFAULT 0,
        `corners` INT(11) DEFAULT 0,
        `result` ENUM('win','draw','loss') DEFAULT NULL,
        `last_synced` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_match_club` (`e_match_id`, `club_id`),
        INDEX `idx_comp_season_club` (`competition_code`, `season_year`, `club_id`)
    ) ENGINE=InnoDB $charset_collate;";
    
    $result13 = $db->query( $sql_match_team_stats );
    if ( $result13 === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating fdm_match_team_stats: ' . $db->last_error );
    }
    
    // Table: ingest_jobs
    $sql_ingest_jobs = "CREATE TABLE IF NOT EXISTS `ingest_jobs` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `provider` VARCHAR(32) NOT NULL,
        `job_type` VARCHAR(64) NOT NULL,
        `status` ENUM('pending','running','success','failed','paused') NOT NULL DEFAULT 'pending',
        `priority` INT NOT NULL DEFAULT 100,
        `season_year` INT DEFAULT NULL,
        `competition_code` VARCHAR(64) DEFAULT NULL,
        `payload_json` JSON DEFAULT NULL,
        `schedule_rule` VARCHAR(128) DEFAULT NULL,
        `next_run_at` DATETIME DEFAULT NULL,
        `last_run_at` DATETIME DEFAULT NULL,
        `attempts` INT NOT NULL DEFAULT 0,
        `max_attempts` INT NOT NULL DEFAULT 5,
        `lease_expires_at` DATETIME DEFAULT NULL,
        `last_error` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_due` (`status`,`next_run_at`,`priority`),
        KEY `idx_provider_type` (`provider`,`job_type`),
        KEY `idx_lease` (`lease_expires_at`)
    ) $charset_collate;";

    $result_ingest_jobs = $db->query( $sql_ingest_jobs );
    if ( $result_ingest_jobs === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating ingest_jobs: ' . $db->last_error );
    }

    // Table: ingest_job_runs
    $sql_ingest_job_runs = "CREATE TABLE IF NOT EXISTS `ingest_job_runs` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `job_id` BIGINT(20) UNSIGNED NOT NULL,
        `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `finished_at` DATETIME DEFAULT NULL,
        `exit_status` ENUM('success','failed') DEFAULT NULL,
        `runtime_ms` INT DEFAULT NULL,
        `output_text` MEDIUMTEXT DEFAULT NULL,
        `error_text` MEDIUMTEXT DEFAULT NULL,
        KEY `idx_job_started` (`job_id`,`started_at`)
    ) $charset_collate;";

    $result_ingest_job_runs = $db->query( $sql_ingest_job_runs );
    if ( $result_ingest_job_runs === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'fdm_create_footyforums_tables: Error creating ingest_job_runs: ' . $db->last_error );
    }
    
    return true;
}

/**
 * Migrate and extend footyforums_data database schema
 * Adds new columns to clubs and matches, creates leagues table
 * Idempotent - safe to run multiple times
 * 
 * @return array Results with 'success', 'messages', 'errors'
 */
function fdm_migrate_footyforums_schema() {
    $db = fdm_get_footyforums_db();
    if ( ! $db ) {
        return array(
            'success' => false,
            'messages' => array(),
            'errors' => array( 'Cannot connect to footyforums_data database' ),
        );
    }
    
    $charset_collate = $db->get_charset_collate();
    if ( empty( $charset_collate ) ) {
        $charset_collate = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }
    
    $results = array(
        'success' => true,
        'messages' => array(),
        'errors' => array(),
    );
    
    // 1. Create leagues table
    $sql_leagues = "CREATE TABLE IF NOT EXISTS `leagues` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `e_league_code` VARCHAR(50) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `current_season_id` VARCHAR(50) DEFAULT NULL,
        `season_year` INT(11) DEFAULT NULL,
        `calendar_start_date` DATE DEFAULT NULL,
        `calendar_end_date` DATE DEFAULT NULL,
        `last_synced` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_e_league_code` (`e_league_code`),
        INDEX `idx_season_year` (`season_year`)
    ) $charset_collate;";
    
    $result = $db->query( $sql_leagues );
    if ( $result === false ) {
        $results['errors'][] = 'Error creating leagues table: ' . $db->last_error;
        $results['success'] = false;
    } else {
        $results['messages'][] = 'Leagues table created/verified';
    }
    
    // 2. Extend clubs table with new columns
    $clubs_columns = array(
        'e_team_id' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `e_team_id` VARCHAR(50) DEFAULT NULL AFTER `e_id`",
        'f_id' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `f_id` VARCHAR(50) DEFAULT NULL AFTER `e_team_id`",
        's_id' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `s_id` VARCHAR(50) DEFAULT NULL AFTER `f_id`",
        'w_id' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `w_id` VARCHAR(50) DEFAULT NULL AFTER `s_id`",
        'e_league_code' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `e_league_code` VARCHAR(50) DEFAULT NULL AFTER `w_id`",
        'full_name' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `full_name` VARCHAR(255) DEFAULT NULL AFTER `canonical_name`",
        'short_name' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `short_name` VARCHAR(100) DEFAULT NULL AFTER `full_name`",
        'abbreviation' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `abbreviation` VARCHAR(10) DEFAULT NULL AFTER `short_name`",
        'slug' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `slug` VARCHAR(100) DEFAULT NULL AFTER `abbreviation`",
        'active_flag' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `active_flag` TINYINT(1) DEFAULT 1 AFTER `slug`",
        'logo_url_primary' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `logo_url_primary` VARCHAR(500) DEFAULT NULL AFTER `active_flag`",
        'logo_url_alt' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `logo_url_alt` VARCHAR(500) DEFAULT NULL AFTER `logo_url_primary`",
        'primary_colour_hex' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `primary_colour_hex` VARCHAR(7) DEFAULT NULL AFTER `logo_url_alt`",
        'secondary_colour_hex' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `secondary_colour_hex` VARCHAR(7) DEFAULT NULL AFTER `primary_colour_hex`",
        'e_venue_id' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `e_venue_id` VARCHAR(50) DEFAULT NULL AFTER `secondary_colour_hex`",
        'home_city' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `home_city` VARCHAR(100) DEFAULT NULL AFTER `e_venue_id`",
        'country' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `country` VARCHAR(100) DEFAULT NULL AFTER `home_city`",
        'needs_mapping' => "ALTER TABLE `clubs` ADD COLUMN IF NOT EXISTS `needs_mapping` TINYINT(1) DEFAULT 0 AFTER `country`",
    );
    
    // MySQL doesn't support IF NOT EXISTS for ALTER TABLE, so check first
    foreach ( $clubs_columns as $column_name => $sql ) {
        // Check if column exists using SHOW COLUMNS (more reliable than INFORMATION_SCHEMA)
        $columns = $db->get_results( "SHOW COLUMNS FROM `clubs` LIKE '$column_name'", ARRAY_A );
        $column_exists = ! empty( $columns );
        
        if ( ! $column_exists ) {
            // Remove IF NOT EXISTS from SQL since we're checking manually
            $clean_sql = str_replace( ' IF NOT EXISTS', '', $sql );
            $result = $db->query( $clean_sql );
            if ( $result === false ) {
                // Check if error is due to duplicate column (might have been added between check and execution)
                if ( strpos( $db->last_error, 'Duplicate column name' ) === false ) {
                    $results['errors'][] = "Error adding column clubs.$column_name: " . $db->last_error;
                    $results['success'] = false;
                } else {
                    // Column was added by another process, treat as success
                    $results['messages'][] = "Column clubs.$column_name already exists";
                }
            } else {
                $results['messages'][] = "Added column clubs.$column_name";
            }
        }
    }
    
    // Populate e_team_id from e_id where e_team_id is NULL
    $populated = $db->query( "UPDATE `clubs` SET `e_team_id` = `e_id` WHERE `e_team_id` IS NULL AND `e_id` IS NOT NULL" );
    if ( $populated !== false && $populated > 0 ) {
        $results['messages'][] = "Populated e_team_id from e_id for $populated clubs";
    }
    
    // Add index on e_team_id if it doesn't exist
    $indexes = $db->get_results( "SHOW INDEX FROM `clubs` WHERE Key_name = 'idx_e_team_id'", ARRAY_A );
    $index_exists = ! empty( $indexes );
    
    if ( ! $index_exists ) {
        $result = $db->query( "ALTER TABLE `clubs` ADD INDEX `idx_e_team_id` (`e_team_id`)" );
        if ( $result === false ) {
            // Check if error is due to duplicate index
            if ( strpos( $db->last_error, 'Duplicate key name' ) === false ) {
                $results['errors'][] = 'Error adding index on clubs.e_team_id: ' . $db->last_error;
            } else {
                $results['messages'][] = 'Index idx_e_team_id already exists';
            }
        } else {
            $results['messages'][] = 'Added index on clubs.e_team_id';
        }
    }
    
    // 3. Extend matches table with new columns
    $matches_columns = array(
        'status_code' => "ALTER TABLE `matches` ADD COLUMN IF NOT EXISTS `status_code` VARCHAR(50) DEFAULT 'scheduled' AFTER `result_code`",
        'status_detail' => "ALTER TABLE `matches` ADD COLUMN IF NOT EXISTS `status_detail` VARCHAR(50) DEFAULT NULL AFTER `status_code`",
        'neutral_venue_flag' => "ALTER TABLE `matches` ADD COLUMN IF NOT EXISTS `neutral_venue_flag` TINYINT(1) DEFAULT 0 AFTER `status_detail`",
        'postponed_needs_review_flag' => "ALTER TABLE `matches` ADD COLUMN IF NOT EXISTS `postponed_needs_review_flag` TINYINT(1) DEFAULT 0 AFTER `neutral_venue_flag`",
        'manual_reschedule_date' => "ALTER TABLE `matches` ADD COLUMN IF NOT EXISTS `manual_reschedule_date` DATETIME DEFAULT NULL AFTER `postponed_needs_review_flag`",
        'last_synced' => "ALTER TABLE `matches` ADD COLUMN IF NOT EXISTS `last_synced` DATETIME DEFAULT NULL AFTER `manual_reschedule_date`",
    );
    
    foreach ( $matches_columns as $column_name => $sql ) {
        $columns = $db->get_results( "SHOW COLUMNS FROM `matches` LIKE '$column_name'", ARRAY_A );
        $column_exists = ! empty( $columns );
        
        if ( ! $column_exists ) {
            $clean_sql = str_replace( ' IF NOT EXISTS', '', $sql );
            $result = $db->query( $clean_sql );
            if ( $result === false ) {
                if ( strpos( $db->last_error, 'Duplicate column name' ) === false ) {
                    $results['errors'][] = "Error adding column matches.$column_name: " . $db->last_error;
                    $results['success'] = false;
                } else {
                    $results['messages'][] = "Column matches.$column_name already exists";
                }
            } else {
                $results['messages'][] = "Added column matches.$column_name";
            }
        }
    }
    
    // Add index on status_code and postponed_needs_review_flag
    $status_indexes = $db->get_results( "SHOW INDEX FROM `matches` WHERE Key_name = 'idx_status_code'", ARRAY_A );
    $status_index_exists = ! empty( $status_indexes );
    
    if ( ! $status_index_exists ) {
        $result = $db->query( "ALTER TABLE `matches` ADD INDEX `idx_status_code` (`status_code`)" );
        if ( $result === false ) {
            if ( strpos( $db->last_error, 'Duplicate key name' ) === false ) {
                $results['errors'][] = 'Error adding index on matches.status_code: ' . $db->last_error;
            } else {
                $results['messages'][] = 'Index idx_status_code already exists';
            }
        } else {
            $results['messages'][] = 'Added index on matches.status_code';
        }
    }
    
    $postponed_indexes = $db->get_results( "SHOW INDEX FROM `matches` WHERE Key_name = 'idx_postponed_review'", ARRAY_A );
    $postponed_index_exists = ! empty( $postponed_indexes );
    
    if ( ! $postponed_index_exists ) {
        $result = $db->query( "ALTER TABLE `matches` ADD INDEX `idx_postponed_review` (`postponed_needs_review_flag`)" );
        if ( $result === false ) {
            if ( strpos( $db->last_error, 'Duplicate key name' ) === false ) {
                $results['errors'][] = 'Error adding index on matches.postponed_needs_review_flag: ' . $db->last_error;
            } else {
                $results['messages'][] = 'Index idx_postponed_review already exists';
            }
        } else {
            $results['messages'][] = 'Added index on matches.postponed_needs_review_flag';
        }
    }
    
    // Log migration completion
    if ( $results['success'] ) {
        fdm_log_datasource_info( 'info', 'Schema migration completed successfully', array(
            'messages' => $results['messages'],
        ) );
    } else {
        fdm_log_datasource_error( 'migration_error', 'Schema migration completed with errors', array(
            'messages' => $results['messages'],
            'errors' => $results['errors'],
        ) );
    }
    
    return $results;
}

/**
 * Log an error to datasource_errors table
 * 
 * @param string $error_type Type of error (e.g., 'club_mapping', 'season_not_found', 'database_error')
 * @param string $error_message Error message
 * @param array $context_data Additional context data (will be JSON encoded)
 */
function fdm_log_datasource_error( $error_type, $error_message, $context_data = array() ) {
    $db = fdm_get_footyforums_db();
    if ( ! $db ) {
        // Fallback to WordPress error log
        error_log( sprintf( '[Datasource Error] %s: %s', $error_type, $error_message ) );
        return;
    }
    
    $db->insert(
        'datasource_errors',
        array(
            'error_type' => $error_type,
            'error_message' => $error_message,
            'context_data' => ! empty( $context_data ) ? json_encode( $context_data ) : null,
        ),
        array( '%s', '%s', '%s' )
    );
    
    // Also log to WordPress debug log if enabled
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf( '[Datasource Error] %s: %s', $error_type, $error_message ) );
    }
    
    // Log to WP-CLI if running under WP-CLI
    if ( defined( 'WP_CLI' ) && class_exists( 'WP_CLI' ) ) {
        $msg = '[FDM ERROR] ' . $error_type . ' - ' . $error_message;
        if ( ! empty( $context_data ) && is_array( $context_data ) ) {
            // Keep context short to avoid flooding the console
            $snippet = $context_data;
            // If there is a "type", "e_match_id", "e_team_id", "e_league_code", "season_year", keep those
            // and drop anything very large like full JSON payloads if present.
            if ( isset( $snippet['payload'] ) ) {
                unset( $snippet['payload'] );
            }
            $msg .= ' | context: ' . wp_json_encode( $snippet );
        }
        WP_CLI::log( $msg );
    }
}

/**
 * Log an informational message to datasource_log table
 * 
 * @param string $log_level Log level (info, warning, error, debug)
 * @param string $log_message Log message
 * @param array $context_data Additional context data (will be JSON encoded)
 */
function fdm_log_datasource_info( $log_level, $log_message, $context_data = array() ) {
    $db = fdm_get_footyforums_db();
    if ( ! $db ) {
        // Fallback to WordPress error log
        error_log( sprintf( '[Datasource %s] %s', strtoupper( $log_level ), $log_message ) );
        return;
    }
    
    $db->insert(
        'datasource_log',
        array(
            'log_level' => $log_level,
            'log_message' => $log_message,
            'context_data' => ! empty( $context_data ) ? json_encode( $context_data ) : null,
        ),
        array( '%s', '%s', '%s' )
    );
    
    // Also log to WordPress debug log if enabled
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $log_level === 'debug' ) {
        error_log( sprintf( '[Datasource %s] %s', strtoupper( $log_level ), $log_message ) );
    }
    
    // Log to WP-CLI if running under WP-CLI
    if ( defined( 'WP_CLI' ) && class_exists( 'WP_CLI' ) ) {
        $msg = '[FDM INFO] ' . $log_level . ' - ' . $log_message;
        if ( ! empty( $context_data ) && is_array( $context_data ) ) {
            $snippet = $context_data;
            if ( isset( $snippet['payload'] ) ) {
                unset( $snippet['payload'] );
            }
            $msg .= ' | context: ' . wp_json_encode( $snippet );
        }
        WP_CLI::log( $msg );
    }
}

