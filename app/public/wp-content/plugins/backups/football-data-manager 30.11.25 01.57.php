<?php
/**
 * Plugin Name: Football Data Manager
 * Description: Control datasourcing tools and manage football data.
 * Version: 1.0
 * Author: Your Name
 */

// Security: Prevent direct access
if (!defined('ABSPATH')) exit;

// Define plugin path
define('FDM_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Helper function to get table names
function fdm_table_players() {
    global $wpdb;
    return $wpdb->prefix . 'fdm_players';
}

function fdm_table_players_backup() {
    global $wpdb;
    return $wpdb->prefix . 'fdm_players_backup';
}

function fdm_table_name_variants() {
    global $wpdb;
    return $wpdb->prefix . 'fdm_name_variants';
}

function fdm_table_stats_f() {
    global $wpdb;
    return $wpdb->prefix . 'fdm_stats_f';
}

function fdm_table_match_stats_e() {
    global $wpdb;
    return $wpdb->prefix . 'fdm_match_stats_e';
}

function fdm_table_match_events_e() {
    global $wpdb;
    return $wpdb->prefix . 'fdm_match_events_e';
}
/**
 * Normalize player names for matching
 * Handles case, whitespace, accents, and common variations
 */
function fdm_normalize_player_name($name) {
    if (empty($name)) {
        return '';
    }
    
    // Convert to lowercase
    $normalized = strtolower(trim($name));
    
    // Remove accents and special characters
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
    
    // Normalize whitespace (multiple spaces to single space)
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    
    // Remove common prefixes/suffixes that might differ
    $normalized = str_replace(array('jr.', 'jr', 'sr.', 'sr', 'ii', 'iii', 'iv'), '', $normalized);
    
    // Remove periods and commas
    $normalized = str_replace(array('.', ','), '', $normalized);
    
    // Trim again after removals
    $normalized = trim($normalized);
    
    return $normalized;
}

/**
 * Compare two player names with fuzzy matching
 * Returns true if names are likely the same person
 */
function fdm_names_match($name1, $name2) {
    $norm1 = fdm_normalize_player_name($name1);
    $norm2 = fdm_normalize_player_name($name2);
    
    // Exact match after normalization
    if ($norm1 === $norm2) {
        return true;
    }
    
    // Split into parts
    $parts1 = explode(' ', $norm1);
    $parts2 = explode(' ', $norm2);
    
    // If both have at least 2 parts, check if last names match and at least one first name part matches
    if (count($parts1) >= 2 && count($parts2) >= 2) {
        $last1 = end($parts1);
        $last2 = end($parts2);
        
        // Last names must match
        if ($last1 === $last2) {
            // Check if any first name parts match (handles "Mohamed Salah" vs "Mo Salah")
            $first1 = array_slice($parts1, 0, -1);
            $first2 = array_slice($parts2, 0, -1);
            
            foreach ($first1 as $f1) {
                foreach ($first2 as $f2) {
                    // Exact match or one is abbreviation of the other
                    if ($f1 === $f2 || 
                        (strlen($f1) >= 2 && strlen($f2) >= 2 && 
                         (strpos($f1, $f2) === 0 || strpos($f2, $f1) === 0))) {
                        return true;
                    }
                }
            }
        }
    }
    
    // Check if one name is contained in the other (for single-word names or nicknames)
    if (strlen($norm1) >= 3 && strlen($norm2) >= 3) {
        if (strpos($norm1, $norm2) !== false || strpos($norm2, $norm1) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Normalize club names for matching
 * Converts common variations to standard names
 */
function fdm_normalize_club_name($club_name) {
    if (empty($club_name)) {
        return '';
    }
    
    // Convert to lowercase for comparison
    $normalized = strtolower(trim($club_name));
    
    // Club name mappings (add more as needed)
    $club_mappings = array(
        // Manchester variations
        'man city' => 'manchester city',
        'manchester city' => 'manchester city',
        'mancity' => 'manchester city',
        'man utd' => 'manchester united',
        'manchester united' => 'manchester united',
        'manchester utd' => 'manchester united',
        'manu' => 'manchester united',
        
        // London clubs
        'tottenham' => 'tottenham hotspur',
        'spurs' => 'tottenham hotspur',
        'tottenham hotspur' => 'tottenham hotspur',
        'arsenal' => 'arsenal',
        'chelsea' => 'chelsea',
        'west ham' => 'west ham united',
        'west ham united' => 'west ham united',
        'west ham utd' => 'west ham united',
        'crystal palace' => 'crystal palace',
        'fulham' => 'fulham',
        'brentford' => 'brentford',
        
        // Liverpool
        'liverpool' => 'liverpool',
        'lfc' => 'liverpool',
        'liverpool fc' => 'liverpool',
        
        // Other Premier League
        'brighton' => 'brighton & hove albion',
        'brighton & hove' => 'brighton & hove albion',
        'brighton and hove albion' => 'brighton & hove albion',
        'newcastle' => 'newcastle united',
        'newcastle utd' => 'newcastle united',
        'newcastle united' => 'newcastle united',
        'everton' => 'everton',
        'aston villa' => 'aston villa',
        'villa' => 'aston villa',
        'wolves' => 'wolverhampton wanderers',
        'wolverhampton' => 'wolverhampton wanderers',
        'wolverhampton wanderers' => 'wolverhampton wanderers',
        'leicester' => 'leicester city',
        'leicester city' => 'leicester city',
        'southampton' => 'southampton',
        'burnley' => 'burnley',
        'watford' => 'watford',
        'norwich' => 'norwich city',
        'norwich city' => 'norwich city',
    );
    
    // Check if we have a mapping
    if (isset($club_mappings[$normalized])) {
        return $club_mappings[$normalized];
    }
    
    // Return original if no mapping found
    return trim($club_name);
}

/**
 * Backup the unified players table before imports
 * Similar to the old wp_fb_ktpl_players backup system
 */
function fdm_backup_players_table() {
    global $wpdb;
    
    $players_table = fdm_table_players();
    $backup_table = fdm_table_players_backup();
    
    // Always drop and recreate backup table to ensure structure matches
    $wpdb->query("DROP TABLE IF EXISTS {$backup_table}");
    
    // Get the CREATE TABLE statement from the main table and modify it for backup
    $create_statement = $wpdb->get_row("SHOW CREATE TABLE {$players_table}", ARRAY_A);
    if (!$create_statement) {
        return 'Backup failed: Could not read table structure.';
    }
    
    $create_sql = $create_statement['Create Table'];
    // Replace table name with backup table name
    $create_sql = str_replace($players_table, $backup_table, $create_sql);
    
    // Create the backup table
    $wpdb->query($create_sql);
    
    // Copy current data to backup
    $copied = $wpdb->query("INSERT INTO {$backup_table} SELECT * FROM {$players_table}");
    
    if ($copied !== false) {
        return sprintf('Backup created: %d players backed up.', $copied);
    } else {
        return 'Backup failed: ' . $wpdb->last_error;
    }
}
// Create admin menu
add_action('admin_menu', 'fdm_add_admin_menu');

function fdm_add_admin_menu() {
    add_menu_page(
        'Football Data',
        'Football Data',
        'manage_options',
        'football-data-manager',
        'fdm_admin_page',
        'dashicons-groups',
        30
    );
    
    // Database E submenu (same as main page)
    add_submenu_page(
        'football-data-manager',
        'Database E',
        'Database E',
        'manage_options',
        'football-data-manager',
        'fdm_admin_page'
    );
    
    // Database F submenu
    add_submenu_page(
        'football-data-manager',
        'Database F',
        'Database F',
        'manage_options',
        'database-f-manager',
        'fdm_database_f_page'
    );
    
    // Database S submenu
    add_submenu_page(
        'football-data-manager',
        'Database S',
        'Database S',
        'manage_options',
        'database-s-manager',
        'fdm_database_s_page'
    );
}

// Settings registration
add_action( 'admin_init', 'fdm_register_settings' );

function fdm_register_settings() {
    register_setting(
        'fdm_settings_group',
        'fdm_external_db_name',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        )
    );
    
    add_settings_section(
        'fdm_db_section',
        'External Database',
        function () {
            echo '<p>Configure the external database name for shared football tables.</p>';
        },
        'fdm_settings_page'
    );
    
    add_settings_field(
        'fdm_external_db_name',
        'External DB name',
        'fdm_external_db_name_field',
        'fdm_settings_page',
        'fdm_db_section'
    );
}

function fdm_external_db_name_field() {
    $value = get_option( 'fdm_external_db_name', '' );
    echo '<input type="text" name="fdm_external_db_name" value="' . esc_attr( $value ) . '" class="regular-text" />';
    echo '<p class="description">Example: footyforums_data</p>';
}

// Settings page render
function fdm_render_settings_page() {
    // Handle wp-config.php write request
    $write_result = null;
    if ( isset( $_POST['fdm_write_constant'] ) && check_admin_referer( 'fdm_write_constant' ) ) {
        $db_name = isset( $_POST['fdm_external_db_name'] ) ? sanitize_text_field( $_POST['fdm_external_db_name'] ) : '';
        if ( ! empty( $db_name ) ) {
            $write_result = fdm_write_constant_to_wp_config( $db_name );
        } else {
            $write_result = new WP_Error( 'fdm_empty_db', 'External DB name is empty.' );
        }
    }
    
    ?>
    <div class="wrap">
        <h1>Football Data Manager Settings</h1>
        
        <?php if ( defined( 'FOOTYFORUMS_DB_NAME' ) ) : ?>
            <div class="notice notice-info">
                <p>FOOTYFORUMS_DB_NAME is defined in wp-config.php as <code><?php echo esc_html( FOOTYFORUMS_DB_NAME ); ?></code>. That value overrides this field.</p>
            </div>
        <?php endif; ?>
        
        <?php if ( $write_result instanceof WP_Error ) : ?>
            <div class="notice notice-error">
                <p><strong>Error:</strong> <?php echo esc_html( $write_result->get_error_message() ); ?></p>
            </div>
        <?php elseif ( $write_result === true ) : ?>
            <div class="notice notice-success">
                <p><strong>Success:</strong> Constant written to wp-config.php. Please refresh the page to see the updated status.</p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="options.php">
            <?php
            settings_fields( 'fdm_settings_group' );
            do_settings_sections( 'fdm_settings_page' );
            submit_button();
            ?>
        </form>
        
        <h2>Optional wp-config.php snippet</h2>
        <p>For a separate shared DB (recommended for production), add this line to <code>wp-config.php</code>:</p>
        <pre>define('FOOTYFORUMS_DB_NAME', 'footyforums_data');</pre>
        
        <?php
        // Only show write button on Local/dev environments
        $is_local = defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'local';
        $is_local = $is_local || ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV );
        $is_local = $is_local || ( strpos( home_url(), '.local' ) !== false );
        
        if ( $is_local && ! defined( 'FOOTYFORUMS_DB_NAME' ) ) :
            $current_db_name = get_option( 'fdm_external_db_name', '' );
            ?>
            <h2>Write constant to wp-config.php (Local/Dev only)</h2>
            <p>This will automatically add the constant to your wp-config.php file. <strong>Only use this on local development environments.</strong></p>
            <form method="post" action="">
                <?php wp_nonce_field( 'fdm_write_constant' ); ?>
                <input type="hidden" name="fdm_write_constant" value="1" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fdm_external_db_name_write">Database Name</label></th>
                        <td>
                            <input type="text" name="fdm_external_db_name" id="fdm_external_db_name_write" value="<?php echo esc_attr( $current_db_name ); ?>" class="regular-text" />
                            <p class="description">The database name to write to wp-config.php</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Write constant into wp-config.php', 'secondary' ); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

// Add settings submenu
add_action( 'admin_menu', function () {
    add_submenu_page(
        'football-data-manager',      // parent slug
        'FDM Settings',
        'Settings',
        'manage_options',
        'fdm-settings',
        'fdm_render_settings_page'
    );
}, 20 );

// wp-config.php writer function
function fdm_write_constant_to_wp_config( $db_name ) {
    if ( empty( $db_name ) ) {
        return new WP_Error( 'fdm_empty_db', 'External DB name is empty.' );
    }
    
    $config_path = ABSPATH . 'wp-config.php';
    if ( ! file_exists( $config_path ) ) {
        $config_path = dirname( ABSPATH ) . '/wp-config.php';
    }
    
    if ( ! file_exists( $config_path ) ) {
        return new WP_Error( 'fdm_no_config', 'wp-config.php not found.' );
    }
    
    $contents = file_get_contents( $config_path );
    if ( $contents === false ) {
        return new WP_Error( 'fdm_read_failed', 'Failed to read wp-config.php. Check file permissions.' );
    }
    
    if ( strpos( $contents, 'FOOTYFORUMS_DB_NAME' ) !== false ) {
        return new WP_Error( 'fdm_already_defined', 'FOOTYFORUMS_DB_NAME already defined in wp-config.php.' );
    }
    
    $define_line = "define('FOOTYFORUMS_DB_NAME', '" . addslashes( $db_name ) . "');\n";
    $marker = "/* That's all, stop editing! Happy publishing. */";
    
    $pos = strpos( $contents, $marker );
    if ( $pos === false ) {
        // Fallback, append to end
        $contents .= "\n" . $define_line;
    } else {
        $contents = substr( $contents, 0, $pos ) . $define_line . substr( $contents, $pos );
    }
    
    // Use WP_Filesystem if available, otherwise fall back to file_put_contents
    $write_result = false;
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    
    $credentials = request_filesystem_credentials( '', '', false, false, null );
    if ( WP_Filesystem( $credentials ) ) {
        global $wp_filesystem;
        $write_result = $wp_filesystem->put_contents( $config_path, $contents, FS_CHMOD_FILE );
    } else {
        // Fallback to file_put_contents if WP_Filesystem fails
        $write_result = file_put_contents( $config_path, $contents );
    }
    
    if ( $write_result === false ) {
        return new WP_Error( 'fdm_write_failed', 'Failed to write wp-config.php. Check file permissions.' );
    }
    
    return true;
}

add_action('admin_enqueue_scripts', 'fdm_admin_scripts');

add_action('admin_menu', 'fdm_add_player_stats_menu');

function fdm_add_player_stats_menu() {
    add_submenu_page(
        'football-data-manager',          // Parent menu slug
        'Player Stats',                   // Page title
        'Player Stats',                   // Menu title
        'manage_options',                 // Capability
        'fdm-player-stats',               // Menu slug
        'fdm_player_stats_page'           // Function name
    );
    
    // Player Statistics (Match stats)
    add_submenu_page(
        'football-data-manager',          // Parent menu slug
        'Player Statistics',              // Page title
        'Player Statistics',              // Menu title
        'manage_options',                 // Capability
        'fdm-player-statistics',          // Menu slug
        'fdm_player_statistics_page'     // Function name
    );
    
    // Match Data imports submenu
    add_submenu_page(
        'football-data-manager',          // Parent menu slug
        'Match Data Imports',             // Page title
        'Match Data Imports',             // Menu title
        'manage_options',                 // Capability
        'fdm-e-imports',                // Menu slug
        'fdm_render_e_imports_page'    // Function name
    );
        // Manual matching submenu
        add_submenu_page(
            'football-data-manager',          // Parent menu slug
            'Player Matching',                 // Page title
            'Player Matching',                // Menu title
            'manage_options',                  // Capability
            'fdm-player-matching',             // Menu slug
            'fdm_player_matching_page'         // Function name
    );
}

function fdm_admin_scripts($hook) {
    // Only load on our plugin's admin pages
    if ($hook != 'toplevel_page_football-data-manager' && 
        $hook != 'football-data_page_fdm-player-stats' && 
        $hook != 'football-data_page_fdm-e-imports' &&
        $hook != 'football-data_page_fdm-player-matching' &&
        $hook != 'football-data_page_fdm-player-statistics') {
        return;
    }
    
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'fdm_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('fdm_ajax_nonce')
    ));
}


function fdm_admin_page() {
    // Get current tab from URL or default to 'overview'
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    // Define tabs
    $tabs = array(
        'overview' => array(
            'title' => 'Overview',
            'icon' => 'dashicons-dashboard'
        ),
        'database-e' => array(
            'title' => 'Database E',
            'icon' => 'dashicons-database'
        ),
        'imports' => array(
            'title' => 'Data Imports',
            'icon' => 'dashicons-download'
        ),
        'statistics' => array(
            'title' => 'Statistics',
            'icon' => 'dashicons-chart-bar'
        ),
        'settings' => array(
            'title' => 'Settings',
            'icon' => 'dashicons-admin-settings'
        )
    );
    ?>
    <div class="wrap">
        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper" style="margin: 20px 0 0 0;">
            <?php foreach ($tabs as $tab_key => $tab_data): ?>
                <a href="?page=football-data-manager&tab=<?php echo esc_attr($tab_key); ?>" 
                   class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($tab_data['icon']); ?>" style="margin-top: 3px;"></span>
                    <?php echo esc_html($tab_data['title']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        
        <!-- Tab Content -->
        <div class="fdm-tab-content" style="margin-top: 20px;">
            <?php
            switch ($current_tab) {
                case 'overview':
                    ?>
                    <div class="fdm-dashboard">
                        <div class="fdm-stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                            <h3 style="margin: 0 0 10px 0;">Last Scrape</h3>
                            <p style="font-size: 18px; margin: 0;"><?php echo esc_html(get_option('fdm_last_scrape_time', 'Never')); ?></p>
                        </div>
                        <div class="fdm-stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                            <h3 style="margin: 0 0 10px 0;">Players in DB</h3>
                            <p style="font-size: 18px; margin: 0;"><?php 
                                $counts = wp_count_posts('fdm_player');
                                echo esc_html($counts->publish ?? 0); 
                            ?></p>
                        </div>
                        <div class="fdm-stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                            <h3 style="margin: 0 0 10px 0;">Automation Status</h3>
                            <p style="font-size: 18px; margin: 0;"><?php echo get_option('fdm_automation_enabled') ? '✅ Active' : '❌ Inactive'; ?></p>
                        </div>
                    </div>
                    <style>
                        .fdm-dashboard { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
                        @media (max-width: 1200px) { .fdm-dashboard { grid-template-columns: repeat(2, 1fr); } }
                        @media (max-width: 768px) { .fdm-dashboard { grid-template-columns: 1fr; } }
                    </style>
                    <?php
                    break;
                    
                case 'database-e':
                    ?>
                    <h2>Database E Management</h2>
                    <p>Manage your Database E content and settings.</p>
                    
                    <!-- Sub-navigation within Database E tab -->
                    <div style="margin: 20px 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                        <a href="#" class="button" style="margin-right: 10px;">View Tables</a>
                        <a href="#" class="button" style="margin-right: 10px;">Backup Database</a>
                        <a href="#" class="button" style="margin-right: 10px;">Optimize Tables</a>
                        <a href="#" class="button">Export Data</a>
        </div>
                    
                    <div class="postbox" style="margin-top: 20px;">
                        <div class="inside">
                            <h3>Database E Statistics</h3>
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th>Table Name</th>
                                        <th>Rows</th>
                                        <th>Size</th>
                                        <th>Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Players</td>
                                        <td>1,234</td>
                                        <td>2.5 MB</td>
                                        <td>2 hours ago</td>
                                    </tr>
                                    <tr>
                                        <td>Matches</td>
                                        <td>567</td>
                                        <td>1.2 MB</td>
                                        <td>1 hour ago</td>
                                    </tr>
                                    <tr>
                                        <td>Statistics</td>
                                        <td>8,901</td>
                                        <td>5.8 MB</td>
                                        <td>30 minutes ago</td>
                                    </tr>
                                </tbody>
                            </table>
    </div>
                    </div>
                    <?php
                    break;
                    
                case 'imports':
                    ?>
                    <h2>Data Imports</h2>
                    <p>Import and manage football data from various sources.</p>
                    
                    <div class="postbox" style="margin-top: 20px;">
                        <div class="inside">
                            <h3>E Datasource (New Database)</h3>
                            <p>Import matches from E API into footyforums_data database.</p>
                            <p><strong>Note:</strong> Configure the external database name in <a href="<?php echo esc_url( admin_url( 'admin.php?page=fdm-settings' ) ); ?>">Settings</a> or define <code>FOOTYFORUMS_DB_NAME</code> in wp-config.php</p>
                            <?php
                            require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
                            $db_name = fdm_get_external_db_name();
                            if ( defined( 'FOOTYFORUMS_DB_NAME' ) ) {
                                echo '<p style="color: green;">✓ Database constant defined: ' . esc_html( FOOTYFORUMS_DB_NAME ) . '</p>';
                            } elseif ( get_option( 'fdm_external_db_name' ) ) {
                                echo '<p style="color: green;">✓ Database name configured: ' . esc_html( $db_name ) . ' (from plugin settings)</p>';
                            } else {
                                echo '<p style="color: orange;"><strong>Info:</strong> Using current WordPress database: ' . esc_html( $db_name ) . '</p>';
                                echo '<p>To use a separate database, configure it in <a href="' . admin_url( 'admin.php?page=fdm-settings' ) . '">Settings</a> or add this line to wp-config.php:</p>';
                                echo '<code>define(\'FOOTYFORUMS_DB_NAME\', \'footyforums_data\');</code>';
                            }
                                
                                // Check if tables exist
                                require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
                                $db = fdm_get_footyforums_db();
                                if ( $db ) {
                                    $required_tables = array( 'clubs', 'seasons', 'matches', 'match_extras', 'competition_map', 'datasource_errors' );
                                    $missing_tables = array();
                                    foreach ( $required_tables as $table ) {
                                        $exists = $db->get_var( $db->prepare( "SHOW TABLES LIKE %s", $table ) );
                                        if ( ! $exists ) {
                                            $missing_tables[] = $table;
                                        }
                                    }
                                    
                                    if ( ! empty( $missing_tables ) ) {
                                        echo '<p style="color: orange;"><strong>Warning:</strong> The following tables are missing: ' . implode( ', ', $missing_tables ) . '</p>';
                                        echo '<p>Click "Create/Update Tables" button below to create them.</p>';
                                    } else {
                                        echo '<p style="color: green;">✓ All required tables exist</p>';
                                    }
                                } else {
                                    echo '<p style="color: red;"><strong>Error:</strong> Cannot connect to footyforums_data database. Check database credentials.</p>';
                                }
                            }
                            ?>
                            <form method="post" action="">
                                <?php wp_nonce_field( 'fdm_e_import', 'fdm_e_import_nonce' ); ?>
                                <p>
                                    <label>League Codes (comma-separated):</label><br>
                                    <input type="text" name="e_leagues" value="eng.1,uefa.champions,uefa.europa" style="width: 300px;" placeholder="eng.1,uefa.champions">
                                    <br><small>Available: eng.1, eng.fa, eng.league_cup, uefa.champions, uefa.europa, uefa.europa_conference</small>
                                </p>
                                <p>
                                    <input type="submit" name="fdm_import_e" class="button button-primary" value="Import E Matches">
                                    <input type="submit" name="fdm_create_tables" class="button" value="Create/Update Tables">
                                </p>
                            </form>
                            <?php
                            if ( isset( $_POST['fdm_import_e'] ) && check_admin_referer( 'fdm_e_import', 'fdm_e_import_nonce' ) ) {
                                require_once FDM_PLUGIN_DIR . 'includes/e_datasource_v2.php';
                                require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
                                
                                $leagues_input = isset( $_POST['e_leagues'] ) ? sanitize_text_field( $_POST['e_leagues'] ) : 'eng.1';
                                $leagues = array_map( 'trim', explode( ',', $leagues_input ) );
                                
                                echo '<div class="notice notice-info"><p>Starting import...</p></div>';
                                
                                $stats = FDM_E_Datasource_V2::import_from_scoreboard( $leagues, 50 );
                                
                                if ( $stats['errors'] > 0 ) {
                                    echo '<div class="notice notice-error"><p>';
                                    echo sprintf( 'Import complete with errors! Inserted: %d, Updated: %d, Errors: %d, Skipped: %d', 
                                        $stats['inserted'], $stats['updated'], $stats['errors'], $stats['skipped'] );
                                    echo '</p></div>';
                                    
                                    // Show recent errors from database
                                    $db = fdm_get_footyforums_db();
                                    if ( $db ) {
                                        $recent_errors = $db->get_results(
                                            "SELECT error_type, error_message, context_data, created_at 
                                             FROM datasource_errors 
                                             ORDER BY created_at DESC 
                                             LIMIT 20",
                                            ARRAY_A
                                        );
                                        
                                        if ( ! empty( $recent_errors ) ) {
                                            echo '<div class="notice notice-error" style="margin-top: 10px;">';
                                            echo '<h4>Recent Errors (last 20):</h4>';
                                            echo '<table class="widefat" style="margin-top: 10px;">';
                                            echo '<thead><tr><th>Time</th><th>Type</th><th>Message</th><th>Context</th></tr></thead>';
                                            echo '<tbody>';
                                            foreach ( $recent_errors as $error ) {
                                                $context = ! empty( $error['context_data'] ) ? json_decode( $error['context_data'], true ) : array();
                                                $context_str = ! empty( $context ) ? '<pre style="font-size: 11px; max-width: 300px; overflow: auto;">' . esc_html( print_r( $context, true ) ) . '</pre>' : '-';
                                                echo '<tr>';
                                                echo '<td>' . esc_html( $error['created_at'] ) . '</td>';
                                                echo '<td><strong>' . esc_html( $error['error_type'] ) . '</strong></td>';
                                                echo '<td>' . esc_html( $error['error_message'] ) . '</td>';
                                                echo '<td>' . $context_str . '</td>';
                                                echo '</tr>';
                                            }
                                            echo '</tbody></table>';
                                            echo '</div>';
                                        }
                                    }
                                } else {
                                    echo '<div class="notice notice-success"><p>';
                                    echo sprintf( 'Import complete! Inserted: %d, Updated: %d, Errors: %d, Skipped: %d', 
                                        $stats['inserted'], $stats['updated'], $stats['errors'], $stats['skipped'] );
                                    echo '</p></div>';
                                }
                            }
                            
                            if ( isset( $_POST['fdm_create_tables'] ) && check_admin_referer( 'fdm_e_import', 'fdm_e_import_nonce' ) ) {
                                require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
                                
                                // Check database connection first
                                $db = fdm_get_footyforums_db();
                                if ( ! $db ) {
                                    $db_name = fdm_get_external_db_name();
                                    echo '<div class="notice notice-error"><p><strong>Error:</strong> Cannot connect to external database. Check that:</p>';
                                    echo '<ul style="margin-left: 20px;">';
                                    echo '<li>Database name is configured (currently: ' . esc_html( $db_name ) . ')</li>';
                                    echo '<li>Configure in <a href="' . admin_url( 'admin.php?page=fdm-settings' ) . '">Settings</a> or define FOOTYFORUMS_DB_NAME in wp-config.php</li>';
                                    echo '<li>The database exists and is accessible</li>';
                                    echo '<li>DB_USER, DB_PASSWORD, and DB_HOST are correct</li>';
                                    echo '</ul></div>';
                                } else {
                                    $result = fdm_create_footyforums_tables();
                                    if ( $result ) {
                                        // Verify tables were actually created
                                        $required_tables = array( 'match_extras', 'datasource_errors', 'datasource_log', 'competition_map' );
                                        $created_tables = array();
                                        $missing_tables = array();
                                        
                                        foreach ( $required_tables as $table ) {
                                            $exists = $db->get_var( $db->prepare( "SHOW TABLES LIKE %s", $table ) );
                                            if ( $exists ) {
                                                $created_tables[] = $table;
                                            } else {
                                                $missing_tables[] = $table;
                                            }
                                        }
                                        
                                        if ( empty( $missing_tables ) ) {
                                            echo '<div class="notice notice-success"><p>Tables created/updated successfully! Created: ' . implode( ', ', $created_tables ) . '</p></div>';
                                        } else {
                                            echo '<div class="notice notice-error"><p><strong>Warning:</strong> Some tables were not created:</p>';
                                            echo '<ul style="margin-left: 20px;">';
                                            echo '<li>Created: ' . ( ! empty( $created_tables ) ? implode( ', ', $created_tables ) : 'None' ) . '</li>';
                                            echo '<li>Missing: ' . implode( ', ', $missing_tables ) . '</li>';
                                            if ( $db->last_error ) {
                                                echo '<li>Database error: ' . esc_html( $db->last_error ) . '</li>';
                                            }
                                            echo '</ul>';
                                            echo '<p>Check WordPress debug log for more details.</p></div>';
                                        }
                                    } else {
                                        echo '<div class="notice notice-error"><p>Failed to create tables. ';
                                        if ( $db->last_error ) {
                                            echo 'Database error: ' . esc_html( $db->last_error );
                                        } else {
                                            echo 'Check database connection and permissions.';
                                        }
                                        echo '</p></div>';
                                    }
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="postbox" style="margin-top: 20px;">
                        <div class="inside">
                            <h3>Full Season Import (footyforums_data)</h3>
                            <p>Import full season data for multiple competitions at once.</p>
                            <?php
                            // Show any PHP errors (for debugging)
                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_get_last' ) ) {
                                $last_error = error_get_last();
                                if ( $last_error && $last_error['type'] === E_ERROR ) {
                                    echo '<div class="notice notice-error"><p><strong>PHP Error:</strong> ' . esc_html( $last_error['message'] ) . ' in ' . esc_html( $last_error['file'] ) . ' on line ' . $last_error['line'] . '</p></div>';
                                }
                            }
                            
                            // ALWAYS VISIBLE DEBUG - Remove this later
                            $transient_debug = get_transient( 'fdm_full_season_import_running' );
                            $transient_info = 'NONE';
                            if ( $transient_debug ) {
                                if ( is_array( $transient_debug ) && isset( $transient_debug['timestamp'] ) ) {
                                    $transient_info = 'EXISTS - Array with timestamp: ' . date( 'H:i:s', $transient_debug['timestamp'] ) . ' (' . human_time_diff( $transient_debug['timestamp'] ) . ' ago)';
                                    $transient_info .= ' - Competitions: ' . ( isset( $transient_debug['competitions'] ) ? count( $transient_debug['competitions'] ) : '0' );
                                } elseif ( is_numeric( $transient_debug ) ) {
                                    $transient_info = 'EXISTS - Timestamp: ' . date( 'H:i:s', $transient_debug ) . ' (' . human_time_diff( $transient_debug ) . ' ago)';
                                } else {
                                    $transient_type = gettype( $transient_debug );
                                    $transient_info = 'EXISTS - Type: ' . $transient_type;
                                    if ( is_string( $transient_debug ) ) {
                                        $transient_info .= ' - Value: ' . esc_html( substr( $transient_debug, 0, 100 ) );
                                    }
                                }
                            }
                            
                            echo '<div style="background: #e7f3ff; border: 2px solid #0073aa; padding: 10px; margin: 10px 0; font-size: 12px;">';
                            echo '<strong>🔍 DEBUG INFO:</strong><br>';
                            echo 'POST keys: ' . ( ! empty( $_POST ) ? implode( ', ', array_keys( $_POST ) ) : 'EMPTY' ) . '<br>';
                            echo 'fdm_full_season_import in POST: ' . ( isset( $_POST['fdm_full_season_import'] ) ? 'YES' : 'NO' ) . '<br>';
                            echo 'GET import_started: ' . ( isset( $_GET['import_started'] ) ? 'YES (' . esc_html( $_GET['import_started'] ) . ')' : 'NO' ) . '<br>';
                            $get_keys = ! empty( $_GET ) ? array_keys( $_GET ) : array();
                            echo 'GET all params: ' . ( ! empty( $get_keys ) ? esc_html( implode( ', ', $get_keys ) ) : 'EMPTY' ) . '<br>';
                            echo 'Cookie fdm_import_started: ' . ( isset( $_COOKIE['fdm_import_started'] ) ? 'YES (' . esc_html( $_COOKIE['fdm_import_started'] ) . ')' : 'NO' ) . '<br>';
                            echo 'Transient value: ' . esc_html( $transient_info ) . '<br>';
                            echo 'Current time: ' . date( 'H:i:s' );
                            echo '</div>';
                            
                            // Load competition config
                            require_once FDM_PLUGIN_DIR . 'includes/e_datasource_v2.php';
                            $fdm_e_competitions = FDM_E_Datasource_V2::get_competitions_config();
                            
                            if ( empty( $fdm_e_competitions ) || ! is_array( $fdm_e_competitions ) ) {
                                echo '<p style="color: red;"><strong>Error:</strong> Competition configuration not found.</p>';
                            }
                            else {
                                // Handle form submission - check at the very top
                                // Check POST, GET parameter, cookie, and transient (in case WordPress redirects)
                                $is_submitting = isset( $_POST['fdm_full_season_import'] );
                                $get_param_set = isset( $_GET['import_started'] ) && $_GET['import_started'] == '1';
                                $cookie_set = isset( $_COOKIE['fdm_import_started'] ) && $_COOKIE['fdm_import_started'] == '1';
                                $transient_data = get_transient( 'fdm_full_season_import_running' );
                                
                                // If GET parameter, cookie, or transient exists, treat as submission
                                if ( ! $is_submitting && ( $get_param_set || $cookie_set || $transient_data ) ) {
                                    $is_submitting = true;
                                    // Clear cookie after detecting it (only if headers not sent)
                                    if ( $cookie_set && ! headers_sent() ) {
                                        @setcookie( 'fdm_import_started', '', time() - 3600, '/' );
                                    }
                                }
                                
                                // Debug: Log what's in $_POST
                                if ( ! empty( $_POST ) ) {
                                    error_log( 'FDM Full Season Import - POST data keys: ' . implode( ', ', array_keys( $_POST ) ) );
                                    error_log( 'FDM Full Season Import - is_submitting: ' . ( $is_submitting ? 'YES' : 'NO' ) );
                                }
                                
                                if ( $is_submitting ) {
                                    // Check nonce (only if POST data exists)
                                    $nonce = isset( $_POST['fdm_full_season_import_nonce'] ) ? $_POST['fdm_full_season_import_nonce'] : '';
                                    $nonce_valid = true;
                                    if ( ! empty( $nonce ) ) {
                                        $nonce_valid = wp_verify_nonce( $nonce, 'fdm_full_season_import' );
                                    } elseif ( $transient_data ) {
                                        // If POST is empty but transient exists, skip nonce check (already validated when transient was set)
                                        $nonce_valid = true;
                                    }
                                    
                                    if ( ! $nonce_valid || ! current_user_can( 'manage_options' ) ) {
                                        echo '<div class="notice notice-error" style="margin: 20px 0; padding: 15px; border-left: 4px solid #d63638; background: #fcf0f1;"><p><strong>✗ Error:</strong> Security check failed. Please refresh the page and try again.</p>';
                                        echo '<p><small>Nonce value: ' . ( ! empty( $nonce ) ? 'Present' : 'Missing' ) . '</small></p></div>';
                                    } else {
                                        require_once FDM_PLUGIN_DIR . 'includes/e_datasource_v2.php';
                                        require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
                                        
                                        // Get form data from POST if available, otherwise from transient
                                        if ( ! empty( $_POST['fdm_competitions'] ) ) {
                                            $selected_competitions = array_map( 'sanitize_text_field', $_POST['fdm_competitions'] );
                                            $season_start = isset( $_POST['fdm_season_start'] ) ? sanitize_text_field( $_POST['fdm_season_start'] ) : '';
                                            $season_end   = isset( $_POST['fdm_season_end'] ) ? sanitize_text_field( $_POST['fdm_season_end'] ) : '';
                                            $season_year  = isset( $_POST['fdm_season_year'] ) ? intval( $_POST['fdm_season_year'] ) : 0;
                                        } elseif ( $transient_data && is_array( $transient_data ) ) {
                                            // Retrieve from transient
                                            $selected_competitions = isset( $transient_data['competitions'] ) ? array_map( 'sanitize_text_field', $transient_data['competitions'] ) : array();
                                            $season_start          = isset( $transient_data['season_start'] ) ? sanitize_text_field( $transient_data['season_start'] ) : '';
                                            $season_end            = isset( $transient_data['season_end'] ) ? sanitize_text_field( $transient_data['season_end'] ) : '';
                                            $season_year           = isset( $transient_data['season_year'] ) ? intval( $transient_data['season_year'] ) : 0;
                                        } else {
                                            $selected_competitions = array();
                                            $season_start          = '';
                                            $season_end            = '';
                                            $season_year           = 0;
                                        }
                                        
                                        // Debug: Show what was submitted (visible at top of page)
                                        echo '<div class="notice notice-info" style="margin: 20px 0; padding: 20px; border: 3px solid #2271b1; background: #f0f6fc; font-size: 14px;">';
                                        echo '<p style="margin: 0 0 10px 0; font-size: 16px; font-weight: bold;"><strong>✓ Form submitted successfully!</strong></p>';
                                        echo '<p style="margin: 5px 0;"><strong>Selected competitions:</strong> ' . count( $selected_competitions ) . '</p>';
                                        echo '<p style="margin: 5px 0;"><strong>Date range:</strong> ' . esc_html( $season_start ) . ' to ' . esc_html( $season_end ) . '</p>';
                                        echo '<p style="margin: 5px 0;"><strong>Starting import process...</strong></p>';
                                        echo '</div>';
                                        
                                        // Force flush output immediately
                                        if ( ob_get_level() ) {
                                            ob_flush();
                                        }
                                        flush();
                                        
                                        if ( empty( $selected_competitions ) ) {
                                            echo '<div class="notice notice-error"><p><strong>Error:</strong> Please select at least one competition.</p></div>';
                                        } elseif ( empty( $season_start ) || empty( $season_end ) ) {
                                            echo '<div class="notice notice-error"><p><strong>Error:</strong> Please provide both start and end dates.</p></div>';
                                        } else {
                                            // Validate date range
                                            $start_timestamp = strtotime( $season_start );
                                            $end_timestamp = strtotime( $season_end );
                                            
                                            if ( $start_timestamp === false || $end_timestamp === false ) {
                                                echo '<div class="notice notice-error"><p>Invalid date format. Please use YYYY-MM-DD format.</p></div>';
                                            } elseif ( $start_timestamp > $end_timestamp ) {
                                                echo '<div class="notice notice-error"><p>Start date must be before or equal to end date.</p></div>';
                                            } else {
                                                $days_diff = ( $end_timestamp - $start_timestamp ) / ( 60 * 60 * 24 );
                                                $max_days = 365 * 5; // 5 years
                                                
                                                if ( $days_diff > $max_days ) {
                                                    echo '<div class="notice notice-error"><p>Date range is too large. Maximum allowed is 5 years (' . $max_days . ' days). Your range is ' . round( $days_diff ) . ' days. Please select a smaller date range.</p></div>';
                                                } else {
                                                    // Check database connection first
                                                    require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
                                                    $db = fdm_get_footyforums_db();
                                                    if ( ! $db ) {
                                                        echo '<div class="notice notice-error"><p><strong>Error:</strong> Cannot connect to footyforums_data database. Please check your database configuration.</p></div>';
                                                    } else {
                                                        // Increase execution time for long imports
                                                        @set_time_limit( 600 ); // 10 minutes
                                                        @ini_set( 'max_execution_time', 600 );
                                                        
                                                        // Enable output buffering if not already enabled
                                                        if ( ! ob_get_level() ) {
                                                            ob_start();
                                                        }
                                                        
                                                        // Flush output buffer to show message immediately
                                                        if ( ob_get_level() ) {
                                                            ob_flush();
                                                        }
                                                        flush();
                                                        
                                                        $import_start_time = time();
                                                        $start_time_formatted = date( 'H:i:s' );
                                                        
                                                        echo '<div class="notice notice-info" id="fdm-import-status" style="border-left: 4px solid #2271b1; background: #f0f6fc; padding: 15px; margin: 20px 0;">';
                                                        echo '<p style="margin: 0 0 10px 0;"><strong>🔄 Import Started at ' . esc_html( $start_time_formatted ) . '</strong></p>';
                                                        echo '<p style="margin: 0; font-size: 13px; color: #666;">This may take several minutes. The page will update automatically as progress is made.</p>';
                                                        echo '<p id="fdm-import-elapsed" style="margin: 5px 0 0 0; font-weight: bold; color: #2271b1;">⏱️ Elapsed time: <span id="fdm-elapsed-seconds">0:00</span> <span style="color: #00a32a; font-size: 11px;">● Running</span></p>';
                                                        echo '</div>';
                                                        
                                                        echo '<div id="fdm-import-progress" style="background: #fff; border: 1px solid #ddd; border-left: 4px solid #2271b1; padding: 15px; margin: 10px 0; max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 12px;">';
                                                        
                                                        // Flush again to show the message
                                                        if ( ob_get_level() ) {
                                                            ob_flush();
                                                        }
                                                        flush();
                                                        
                                                        $overall_stats = array(
                                                            'inserted' => 0,
                                                            'updated'  => 0,
                                                            'errors'   => 0,
                                                            'skipped'  => 0,
                                                        );
                                                        
                                                        $competition_stats = array();
                                                        
                                                        // Helper function to show elapsed time
                                                        function fdm_show_elapsed( $start_time ) {
                                                            $elapsed = time() - $start_time;
                                                            $minutes = floor( $elapsed / 60 );
                                                            $seconds = $elapsed % 60;
                                                            return sprintf( '%d:%02d', $minutes, $seconds );
                                                        }
                                                        
                                                        foreach ( $selected_competitions as $comp_key ) {
                                                            if ( ! isset( $fdm_e_competitions[ $comp_key ] ) ) {
                                                                continue;
                                                            }
                                                            
                                                            $comp_config = $fdm_e_competitions[ $comp_key ];
                                                            $league_code = $comp_config['league_code'];
                                                            $division_name = $comp_config['division_name'];
                                                            $backfill_method = $comp_config['backfill_method'];
                                                            
                                                            $current_time = date( 'H:i:s' );
                                                            $elapsed = fdm_show_elapsed( $import_start_time );
                                                            echo '<p style="margin: 5px 0;"><strong>[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . ']</strong> Processing ' . esc_html( $division_name ) . '...</p>';
                                                            
                                                            // Flush to show progress
                                                            if ( ob_get_level() ) {
                                                                ob_flush();
                                                            }
                                                            flush();
                                                            
                                                            $comp_stats = array(
                                                                'inserted' => 0,
                                                                'updated'  => 0,
                                                                'errors'   => 0,
                                                                'skipped'  => 0,
                                                            );
                                                            
                                                            if ( $backfill_method === 'team_schedule' ) {
                                                                // Premier League: derive participants from E standings for selected season,
                                                                // then import team schedules only for those mapped clubs.
                                                                if ( $comp_key === 'premier_league' ) {
                                                                    $pl_clubs = FDM_E_Datasource_V2::get_pl_participants_from_e( $season_year );
                                                                    
                                                                    if ( ! empty( $pl_clubs ) ) {
                                                                        $current_time = date( 'H:i:s' );
                                                                        $elapsed = fdm_show_elapsed( $import_start_time );
                                                                        echo '<p style="margin: 5px 0; padding-left: 20px;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] Found ' . count( $pl_clubs ) . ' Premier League participants from E standings. Importing schedules...</p>';
                                                                        if ( ob_get_level() ) { ob_flush(); }
                                                                        flush();
                                                                        
                                                                        $team_count    = 0;
                                                                        $total_teams   = count( $pl_clubs );
                                                                        $last_heartbeat = time();
                                                                        
                                                                        foreach ( $pl_clubs as $club ) {
                                                                            $team_count++;
                                                                            $current_time = date( 'H:i:s' );
                                                                            $elapsed      = fdm_show_elapsed( $import_start_time );
                                                                            echo '<p style="margin: 2px 0; padding-left: 40px; color: #666;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] Team ' . $team_count . '/' . $total_teams . ': ' . esc_html( $club['canonical_name'] ) . '...</p>';
                                                                            if ( ob_get_level() ) { ob_flush(); }
                                                                            flush();
                                                                            
                                                                            // Heartbeat every 10 seconds
                                                                            if ( time() - $last_heartbeat >= 10 ) {
                                                                                $current_time = date( 'H:i:s' );
                                                                                $elapsed      = fdm_show_elapsed( $import_start_time );
                                                                                echo '<p style="margin: 2px 0; padding-left: 40px; color: #999; font-style: italic;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] ⏳ Still running... (' . $team_count . '/' . $total_teams . ' teams processed)</p>';
                                                                                if ( ob_get_level() ) { ob_flush(); }
                                                                                flush();
                                                                                $last_heartbeat = time();
                                                                            }
                                                                            
                                                                            $team_stats = FDM_E_Datasource_V2::import_team_schedule( $league_code, $club['e_id'] );
                                                                            
                                                                            $comp_stats['inserted'] += $team_stats['inserted'];
                                                                            $comp_stats['updated']  += $team_stats['updated'];
                                                                            $comp_stats['errors']   += $team_stats['errors'];
                                                                            $comp_stats['skipped']  += $team_stats['skipped'];
                                                                            
                                                                            // Small delay between teams
                                                                            usleep( 200000 );
                                                                        }
                                                                        
                                                                        echo '<p style="margin: 5px 0; padding-left: 20px; color: green;">✓ ' . esc_html( $division_name ) . ' complete: ' . $comp_stats['inserted'] . ' inserted, ' . $comp_stats['updated'] . ' updated</p>';
                                                                    } else {
                                                                        echo '<p style="margin: 5px 0; padding-left: 20px; color: red;">✗ No Premier League participants could be derived from E standings for season ' . esc_html( $season_year ) . '</p>';
                                                                        fdm_log_datasource_error(
                                                                            'no_clubs',
                                                                            'No Premier League clubs discovered from E standings',
                                                                            array(
                                                                                'league_code' => $league_code,
                                                                                'season_year' => $season_year,
                                                                            )
                                                                        );
                                                                        $comp_stats['errors']++;
                                                                    }
                                                                }
                                                            } elseif ( $backfill_method === 'scoreboard_dates' ) {
                                                                // Discover competition participants from scoreboard data (for logging and future use).
                                                                $participants = FDM_E_Datasource_V2::get_competition_participants_from_scoreboard( $league_code, $season_start, $season_end );
                                                                $participant_count = is_array( $participants ) ? count( $participants ) : 0;
                                                                
                                                                $days = ( strtotime( $season_end ) - strtotime( $season_start ) ) / ( 60 * 60 * 24 );
                                                                $current_time = date( 'H:i:s' );
                                                                $elapsed = fdm_show_elapsed( $import_start_time );
                                                                echo '<p style="margin: 5px 0; padding-left: 20px;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] Found ' . intval( $participant_count ) . ' participants from scoreboard for ' . esc_html( $division_name ) . '.</p>';
                                                                echo '<p style="margin: 5px 0; padding-left: 20px;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] Importing ' . round( $days ) . ' days of matches (this may take a few minutes)...</p>';
                                                                if ( ob_get_level() ) { ob_flush(); }
                                                                flush();
                                                                
                                                                $comp_stats = FDM_E_Datasource_V2::import_league_by_dates( $league_code, $season_start, $season_end );
                                                                
                                                                $current_time = date( 'H:i:s' );
                                                                $elapsed = fdm_show_elapsed( $import_start_time );
                                                                
                                                                if ( ! empty( $comp_stats['api_aborted'] ) ) {
                                                                    echo '<p style="margin: 5px 0; padding-left: 20px; color: #d63638;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] ⚠ ' . esc_html( $division_name ) . ' aborted due to repeated API errors (status ' . esc_html( isset( $comp_stats['api_error_status'] ) ? $comp_stats['api_error_status'] : 'unknown' ) . '). Inserted ' . $comp_stats['inserted'] . ', updated ' . $comp_stats['updated'] . '.</p>';
                                                                } else {
                                                                    echo '<p style="margin: 5px 0; padding-left: 20px; color: green;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] ✓ ' . esc_html( $division_name ) . ' complete: ' . $comp_stats['inserted'] . ' inserted, ' . $comp_stats['updated'] . ' updated</p>';
                                                                }
                                                            }
                                                            
                                                            $competition_stats[ $division_name ] = $comp_stats;
                                                            
                                                            $overall_stats['inserted'] += $comp_stats['inserted'];
                                                            $overall_stats['updated']  += $comp_stats['updated'];
                                                            $overall_stats['errors']   += $comp_stats['errors'];
                                                            $overall_stats['skipped']  += $comp_stats['skipped'];
                                                        }
                                                        
                                                        echo '</div>'; // Close progress div
                                                        
                                                        $end_time = time();
                                                        $total_elapsed = fdm_show_elapsed( $import_start_time );
                                                        $end_time_formatted = date( 'H:i:s' );
                                                        
                                                        // Display final results
                                                        $notice_class = ( $overall_stats['errors'] > 0 ) ? 'notice-error' : 'notice-success';
                                                        echo '<div class="notice ' . $notice_class . '" style="margin-top: 20px;"><p><strong>✅ Full season import complete!</strong></p>';
                                                        echo '<p style="margin: 5px 0;"><strong>Started:</strong> ' . esc_html( $start_time_formatted ) . ' | <strong>Completed:</strong> ' . esc_html( $end_time_formatted ) . ' | <strong>Total time:</strong> ' . esc_html( $total_elapsed ) . '</p>';
                                                        
                                                        foreach ( $competition_stats as $comp_name => $stats ) {
                                                            echo '<p>' . esc_html( $comp_name ) . ': Inserted ' . $stats['inserted'] . ', Updated ' . $stats['updated'] . ', Skipped ' . $stats['skipped'] . ', Errors ' . $stats['errors'] . '.</p>';
                                                        }
                                                        
                                                        echo '<p><strong>Overall:</strong> Inserted ' . $overall_stats['inserted'] . ', Updated ' . $overall_stats['updated'] . ', Skipped ' . $overall_stats['skipped'] . ', Errors ' . $overall_stats['errors'] . '.</p>';
                                                        echo '</div>';
                                                        
                                                        // Clear the transient since import is complete
                                                        delete_transient( 'fdm_full_season_import_running' );
                                                        
                                                        // Stop the JavaScript timer and update status
                                                        echo '<script>
                                                        if (typeof fdmImportTimer !== "undefined") { 
                                                            clearInterval(fdmImportTimer); 
                                                        }
                                                        jQuery(document).ready(function($) {
                                                            $("#fdm-import-elapsed").html("<span style=\"color: #00a32a;\">✅ Completed at ' . esc_html( $end_time_formatted ) . ' (Total: ' . esc_html( $total_elapsed ) . ')</span>");
                                                            $("#fdm-import-status").css("border-left-color", "#00a32a");
                                                        });
                                                        </script>';
                                                    } // End database connection check
                                                } // End date range validation
                                            } // End date format validation
                                        } // End form validation else
                                    } // End nonce check else
                                } // End form submission if
                                
                                // Get current year and month
                                $current_year  = (int) date( 'Y' );
                                $current_month = (int) date( 'n' );
                                
                                // Default season year: July–December => current year, Jan–June => previous year
                                $default_season_year = ( $current_month >= 7 ) ? $current_year : ( $current_year - 1 );
                                
                                // Default: July 1 of season year to June 30 of season_year+1
                                if ( $current_month >= 7 ) {
                                    $default_start = $current_year . '-07-01';
                                    $default_end   = ( $current_year + 1 ) . '-06-30';
                                } else {
                                    $default_start = ( $current_year - 1 ) . '-07-01';
                                    $default_end   = $current_year . '-06-30';
                                }
                                
                                $season_start = isset( $_POST['fdm_season_start'] ) ? sanitize_text_field( $_POST['fdm_season_start'] ) : $default_start;
                                $season_end   = isset( $_POST['fdm_season_end'] ) ? sanitize_text_field( $_POST['fdm_season_end'] ) : $default_end;
                                
                                $season_year = isset( $_POST['fdm_season_year'] ) ? intval( $_POST['fdm_season_year'] ) : $default_season_year;
                                if ( $season_year < 1800 || $season_year > ( $current_year + 1 ) ) {
                                    $season_year = $default_season_year;
                                }
                                ?>
                                <?php 
                                $form_action = admin_url( 'admin.php?page=football-data-manager&tab=imports' );
                                if ( isset( $_GET['import_started'] ) ) {
                                    $form_action .= '&import_started=1';
                                }
                                ?>
                                <form method="post" action="<?php echo esc_url( $form_action ); ?>" id="fdm-full-season-import-form">
                                    <?php wp_nonce_field( 'fdm_full_season_import', 'fdm_full_season_import_nonce' ); ?>
                                    <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( admin_url( 'admin.php?page=football-data-manager&tab=imports' ) ); ?>">
                                    
                                    <h4>Select Competitions:</h4>
                                    <fieldset style="margin: 10px 0;">
                                        <?php
                                        $submitted_competitions = isset( $_POST['fdm_competitions'] ) ? array_map( 'sanitize_text_field', $_POST['fdm_competitions'] ) : array();
                                        
                                        foreach ( $fdm_e_competitions as $comp_key => $comp_config ) {
                                            // Default: all checked if form hasn't been submitted, otherwise use submitted values
                                            $checked = empty( $submitted_competitions ) || in_array( $comp_key, $submitted_competitions );
                                            echo '<label style="display: block; margin: 5px 0;">';
                                            echo '<input type="checkbox" name="fdm_competitions[]" value="' . esc_attr( $comp_key ) . '"' . ( $checked ? ' checked' : '' ) . '> ';
                                            echo esc_html( $comp_config['division_name'] ) . ' (' . esc_html( $comp_config['league_code'] ) . ')';
                                            echo '</label>';
                                        }
                                        ?>
                                    </fieldset>
                                    
                                    <h4>Season Year:</h4>
                                    <p>
                                        <label>Season start year:
                                            <input type="number" name="fdm_season_year" value="<?php echo esc_attr( $season_year ); ?>" min="1800" max="<?php echo esc_attr( $current_year + 1 ); ?>" style="width: 120px;">
                                        </label>
                                    </p>
                                    
                                    <h4>Season Date Range:</h4>
                                    <p>
                                        <label>Start Date: 
                                            <input type="date" name="fdm_season_start" value="<?php echo esc_attr( $season_start ); ?>" required>
                                        </label>
                                        <label style="margin-left: 20px;">End Date: 
                                            <input type="date" name="fdm_season_end" value="<?php echo esc_attr( $season_end ); ?>" required>
                                        </label>
                                    </p>
                                    
                                    <p>
                                        <input type="submit" name="fdm_full_season_import" id="fdm-full-season-import-btn" class="button button-primary" value="Run Full Season Import">
                                    </p>
                                    
                                    <?php 
                                    $is_submitting_debug = isset( $_POST['fdm_full_season_import'] );
                                    $get_param_debug = isset( $_GET['import_started'] ) && $_GET['import_started'] == '1';
                                    $transient_data_debug = get_transient( 'fdm_full_season_import_running' );
                                    if ( $is_submitting_debug || $get_param_debug || $transient_data_debug ): 
                                    ?>
                                    <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 20px 0;">
                                        <p style="margin: 0; font-weight: bold; color: #856404;">🔍 DEBUG: Form submission detected! Check below for import status.</p>
                                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                                            POST detected: <?php echo $is_submitting_debug ? 'YES' : 'NO'; ?> | 
                                            GET param: <?php echo $get_param_debug ? 'YES' : 'NO'; ?> | 
                                            Transient: <?php echo $transient_data_debug ? 'YES' . ( is_array( $transient_data_debug ) && isset( $transient_data_debug['timestamp'] ) ? ' (started ' . human_time_diff( $transient_data_debug['timestamp'] ) . ' ago)' : '' ) : 'NO'; ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
    
    <script>
    jQuery(document).ready(function($) {
                                        console.log('Full Season Import script loaded');
                                        
                                        // Check if form was submitted (check for debug box or status elements)
                                        var formSubmitted = $('div:contains("DEBUG: Form submission detected")').length > 0 || 
                                                           $('#fdm-import-status').length > 0 ||
                                                           $('div:contains("Form submitted successfully")').length > 0;
                                        
                                        console.log('Checking form submission status:', {
                                            debugBox: $('div:contains("DEBUG: Form submission detected")').length,
                                            statusDiv: $('#fdm-import-status').length,
                                            successBox: $('div:contains("Form submitted successfully")').length,
                                            formSubmitted: formSubmitted
                                        });
                                        
                                        // Check for cookie (JavaScript can read it immediately)
                                        function getCookie(name) {
                                            var value = "; " + document.cookie;
                                            var parts = value.split("; " + name + "=");
                                            if (parts.length == 2) return parts.pop().split(";").shift();
                                            return null;
                                        }
                                        
                                        var cookieSet = getCookie('fdm_import_started') === '1';
                                        
                                        // Function to initialize timer (with retry) - defined outside PHP block so it's always available
                                        function initImportTimer(retryCount) {
                                            retryCount = retryCount || 0;
                                            var maxRetries = 10;
                                            
                                            var $elapsedSpan = $('#fdm-elapsed-seconds');
                                            var $statusDiv = $('#fdm-import-status');
                                            var $progressDiv = $('#fdm-import-progress');
                                            
                                            console.log('Attempt ' + (retryCount + 1) + ' - Looking for elements:', {
                                                elapsedSpan: $elapsedSpan.length,
                                                statusDiv: $statusDiv.length,
                                                progressDiv: $progressDiv.length
                                            });
                                            
                                            if ( $elapsedSpan.length && $statusDiv.length ) {
                                                console.log('✅ Elements found! Starting elapsed time timer');
                                                var startTime = Math.floor(Date.now() / 1000);
                                                
                                                // Update elapsed time every second
                                                window.fdmImportTimer = setInterval(function() {
                                                    var elapsed = Math.floor(Date.now() / 1000) - startTime;
                                                    var minutes = Math.floor(elapsed / 60);
                                                    var seconds = elapsed % 60;
                                                    var timeStr = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                                                    $elapsedSpan.text(timeStr);
                                                    
                                                    // Add a visual pulse indicator every 2 seconds
                                                    if ( elapsed % 2 === 0 ) {
                                                        $statusDiv.css('border-left-color', '#2271b1');
                                                    } else {
                                                        $statusDiv.css('border-left-color', '#00a32a');
                                                    }
                                                    
                                                    // Auto-scroll progress div to bottom to show latest messages
                                                    if ( $progressDiv.length ) {
                                                        $progressDiv.scrollTop($progressDiv[0].scrollHeight);
                                                    }
                                                }, 1000);
                                                
                                                // Auto-scroll progress div every 2 seconds
                                                setInterval(function() {
                                                    if ( $progressDiv.length ) {
                                                        $progressDiv.scrollTop($progressDiv[0].scrollHeight);
                                                    }
                                                }, 2000);
                                            } else if ( retryCount < maxRetries ) {
                                                console.log('Elements not found yet, retrying in 500ms...');
                                                setTimeout(function() {
                                                    initImportTimer(retryCount + 1);
                                                }, 500);
                                            } else {
                                                console.error('❌ Import status elements not found after ' + maxRetries + ' attempts!');
                                                console.log('Available elements on page:', {
                                                    allDivs: $('div').length,
                                                    notices: $('.notice').length,
                                                    statusDivs: $('[id*="import"]').length
                                                });
                                            }
                                        }
                                        
                                        // Scroll to top on page load if form was just submitted
                                        <?php 
                                        $is_submitting_js = isset( $_POST['fdm_full_season_import'] );
                                        $get_param_js = isset( $_GET['import_started'] ) && $_GET['import_started'] == '1';
                                        $cookie_set_js = isset( $_COOKIE['fdm_import_started'] ) && $_COOKIE['fdm_import_started'] == '1';
                                        $transient_data_js = get_transient( 'fdm_full_season_import_running' );
                                        if ( $is_submitting_js || $get_param_js || $cookie_set_js || $transient_data_js ): 
                                        ?>
                                        console.log('✅ Form submission detected via PHP:', {
                                            post: <?php echo $is_submitting_js ? 'true' : 'false'; ?>,
                                            get: <?php echo $get_param_js ? 'true' : 'false'; ?>,
                                            cookie_php: <?php echo $cookie_set_js ? 'true' : 'false'; ?>,
                                            transient: <?php echo $transient_data_js ? 'true' : 'false'; ?>
                                        });
                                        console.log('PHP detected form submission - initializing import timer');
                                        $('html, body').animate({ scrollTop: 0 }, 300);
                                        
                                        // Also check cookie via JavaScript
                                        if (cookieSet) {
                                            console.log('✅ Cookie also detected via JavaScript');
                                        }
                                        
                                        // Start initialization (with retry)
                                        initImportTimer();
                                        
                                        <?php else: ?>
                                        console.log('PHP: Form not submitted – waiting for submission');
                                        
                                        // JavaScript fallback: Check cookie even if PHP didn't detect it
                                        if (cookieSet) {
                                            console.log('⚠️ Cookie detected but PHP didn\'t - this might be a redirect issue');
                                            console.log('Will check for status elements anyway...');
                                            // Try to initialize timer if elements exist
                                            setTimeout(function() {
                                                var $elapsedSpan = $('#fdm-elapsed-seconds');
                                                var $statusDiv = $('#fdm-import-status');
                                                if ($elapsedSpan.length && $statusDiv.length) {
                                                    console.log('✅ Found elements via JavaScript fallback - starting timer');
                                                    initImportTimer();
                                                }
                                            }, 1000);
                                        }
                                        <?php endif; ?>
                                        
                                        $('#fdm-full-season-import-btn').on('click', function(e) {
                                            e.preventDefault(); // Prevent normal form submission
                                            console.log('🚀 Import button clicked!');
                                            var $btn = $(this);
                                            var originalText = $btn.val();
                                            var $form = $btn.closest('form');
                                            
                                            // Validate form has selections
                                            var checked = $form.find('input[name="fdm_competitions[]"]:checked').length;
                                            console.log('Checked competitions:', checked);
                                            if ( checked === 0 ) {
                                                alert('Please select at least one competition.');
                                                return false;
                                            }
                                            
                                            // Get form data
                                            var formDataObj = {
                                                fdm_competitions: [],
                                                fdm_season_start: $form.find('input[name=\"fdm_season_start\"]').val(),
                                                fdm_season_end: $form.find('input[name=\"fdm_season_end\"]').val(),
                                                fdm_season_year: $form.find('input[name=\"fdm_season_year\"]').val(),
                                                fdm_full_season_import: '1',
                                                fdm_full_season_import_nonce: $form.find('input[name=\"fdm_full_season_import_nonce\"]').val()
                                            };
                                            $form.find('input[name="fdm_competitions[]"]:checked').each(function() {
                                                formDataObj.fdm_competitions.push($(this).val());
                                            });
                                            
                                            // Disable button and show loading state
                                            $btn.prop('disabled', true).val('Processing... Please wait...');
                                            
                                            // Scroll to top immediately
                                            $('html, body').animate({ scrollTop: 0 }, 300);
                                            
                                            // Show status box immediately
                                            var $statusBox = $('#fdm-import-status');
                                            var $progressBox = $('#fdm-import-progress');
                                            
                                            if (!$statusBox.length) {
                                                var startTime = new Date().toLocaleTimeString();
                                                var statusHtml = '<div class="notice notice-info" id="fdm-import-status" style="border-left: 4px solid #2271b1; background: #f0f6fc; padding: 15px; margin: 20px 0;">' +
                                                    '<p style="margin: 0 0 10px 0;"><strong>🔄 Import Started at ' + startTime + '</strong></p>' +
                                                    '<p style="margin: 0; font-size: 13px; color: #666;">This may take several minutes. Progress will update below.</p>' +
                                                    '<p id="fdm-import-elapsed" style="margin: 5px 0 0 0; font-weight: bold; color: #2271b1;">⏱️ Elapsed time: <span id="fdm-elapsed-seconds">0:00</span> <span style="color: #00a32a; font-size: 11px;">● Running</span></p>' +
                                                    '</div>';
                                                var progressHtml = '<div id="fdm-import-progress" style="background: #fff; border: 1px solid #ddd; border-left: 4px solid #2271b1; padding: 15px; margin: 10px 0; max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 12px;"><p style="margin: 5px 0; color: #666;"><em>⏳ Import is running... Progress will appear here as each competition and team is processed. This may take several minutes depending on the number of competitions and date range selected.</em></p><p style="margin: 5px 0; color: #666;"><strong>Note:</strong> The page will update automatically when the import completes. You can continue using other parts of WordPress while this runs.</p></div>';
                                                $form.before(statusHtml + progressHtml);
                                                
                                                // Start elapsed time timer
                                                var timerStart = Math.floor(Date.now() / 1000);
                                                window.fdmImportTimer = setInterval(function() {
                                                    var elapsed = Math.floor(Date.now() / 1000) - timerStart;
                                                    var minutes = Math.floor(elapsed / 60);
                                                    var seconds = elapsed % 60;
                                                    var timeStr = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                                                    $('#fdm-elapsed-seconds').text(timeStr);
                                                }, 1000);
                                            }
                                            
                                            // Submit via AJAX
                                            console.log('Submitting form via AJAX...');
            $.ajax({
                                                url: fdm_ajax.ajax_url,
                                                type: 'POST',
                                                data: {
                                                    action: 'fdm_run_full_season_import',
                                                    nonce: fdm_ajax.nonce,
                                                    competitions: formDataObj.fdm_competitions,
                                                    season_start: formDataObj.fdm_season_start,
                                                    season_end: formDataObj.fdm_season_end,
                                                    season_year: formDataObj.fdm_season_year
                                                },
                success: function(response) {
                                                    console.log('AJAX response:', response);
                                                    if (response.success) {
                                                        // Show results
                                                        var $progress = $('#fdm-import-progress');
                                                        if ($progress.length) {
                                                            $progress.html(response.data.html || '<p>Import completed!</p>');
                                                            // Auto-scroll to bottom to show latest messages
                                                            $progress.scrollTop($progress[0].scrollHeight);
                                                        }
                                                        $('#fdm-import-elapsed').html('<span style="color: #00a32a;">✅ Completed</span>');
                                                        $('#fdm-import-status').css('border-left-color', '#00a32a');
                                                        if (window.fdmImportTimer) {
                                                            clearInterval(window.fdmImportTimer);
                                                        }
                                                        // Re-enable button
                                                        $btn.prop('disabled', false).val(originalText);
                                                    } else {
                                                        alert('Error: ' + (response.data.message || 'Unknown error'));
                                                        $btn.prop('disabled', false).val(originalText);
                                                    }
                },
                error: function(xhr, status, error) {
                                                    console.error('AJAX error:', status, error);
                                                    alert('Error submitting form. Check console for details.');
                                                    $btn.prop('disabled', false).val(originalText);
                }
            });
        });
    });
    </script>
                                    
                                    <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 10px; margin: 10px 0;">
                                        <p><strong>Date Range Guidelines:</strong></p>
                                        <ul style="margin: 5px 0 5px 20px;">
                                            <li><strong>Maximum range:</strong> 5 years (1,825 days)</li>
                                            <li><strong>Recommended:</strong> 1-2 seasons at a time for best performance</li>
                                            <li><strong>Historical data:</strong> ESPN API typically has data going back 3-5 years, but coverage may be limited for older dates</li>
                                            <li><strong>Performance:</strong> Scoreboard-by-date method makes one API call per day, so a full year takes ~1-2 minutes per competition</li>
                                        </ul>
                                        <p><strong>Note:</strong> Premier League uses team schedule method (one request per team, faster). Other competitions use scoreboard-by-date method (one request per day).</p>
                                    </div>
                                </form>
                                <?php
                            } // End else block
                            ?>
                        </div>
                    </div>
                    
                    <div class="postbox" style="margin-top: 20px;">
                        <div class="inside">
                            <h3>Quick Import Actions</h3>
                            <p>
                                <button class="button button-primary" style="margin-right: 10px;">Import Player Data</button>
                                <button class="button" style="margin-right: 10px;">Import Match Data</button>
                                <button class="button">Import Statistics</button>
                            </p>
                        </div>
                    </div>
                    
                    <div class="postbox" style="margin-top: 20px;">
                        <div class="inside">
                            <h3>Import History</h3>
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Records</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>2024-01-15 14:30</td>
                                        <td>Player Data</td>
                                        <td><span style="color: green;">✓ Success</span></td>
                                        <td>150 records</td>
                                    </tr>
                                    <tr>
                                        <td>2024-01-15 10:15</td>
                                        <td>Match Data</td>
                                        <td><span style="color: green;">✓ Success</span></td>
                                        <td>25 records</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                   
        </div>
    </div>
    <?php
}

function fdm_database_f_page() {
    ?>
    <div class="wrap">
        <h1>Database F</h1>
        <p>Manage Database F content and settings.</p>
    </div>
    <?php
}

function fdm_database_s_page() {
    ?>
    <div class="wrap">
        <h1>Database S</h1>
        <p>Manage Database S content and settings.</p>
    </div>
    <?php
}

function fdm_player_stats_page() {
    ?>
    <div class="wrap">
        <h1>F Player Stats Manager</h1>
        
        <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ddd;">
            <h2>Datasource New Data</h2>
            <p>Click button to fetch latest Premier League player stats from F.com</p>
            
            <button id="fdm-player-stats-btn" class="button button-primary">
                Fetch Player Stats
            </button>
            
            <div id="fdm-player-stats-result" style="margin-top: 15px;">
                <!-- Results will appear here -->
            </div>
        </div>
        
        <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ddd;">
            <h2>Database Overview</h2>
            <p>Current players in database: 
                <strong>
                    <?php
                    global $wpdb;
                    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fb_ktpl_players");
                    echo $count ?: '0';
                    ?>
                </strong>
            </p>
            <p>Last backup created: 
                <strong>
                    <?php
                    $backup_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fb_ktpl_players_backup");
                    echo $backup_count ?: 'No backup';
                    ?>
                </strong>
            </p>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Player Stats Button Handler
        $('#fdm-player-stats-btn').click(function() {
            var button = $(this);
            var resultDiv = $('#fdm-player-stats-result');
            
            // Show loading spinner
            button.prop('disabled', true).text('Fetching Player Stats...');
            resultDiv.html('<p style="color: #0073aa;">⏳ Datasourcing data from F...</p>');
            
            $.ajax({
                url: 'http://127.0.0.1:5001/player-stats',
                method: 'GET',
                success: function(response) {
                    button.prop('disabled', false).text('Fetch Player Stats');
                    
                    if (response.success && response.players) {
                        // Send data to WordPress for database storage
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'fdm_save_player_stats',
                                players: JSON.stringify(response.players),
                                nonce: fdm_ajax.nonce
                            },
                            success: function(wpResponse) {
                                if (wpResponse.success) {
                                    resultDiv.html(
                                        '<p style="color: green;">✅ Success! ' + 
                                        wpResponse.data.message + '</p>'
                                    );
                                } else {
                                    resultDiv.html(
                                        '<p style="color: red;">❌ WordPress Error: ' + 
                                        wpResponse.data.message + '</p>'
                                    );
                                }
                            }
                        });
                    } else {
                        resultDiv.html(
                            '<p style="color: red;">❌ Datasource Error: ' + 
                            response.message + '</p>'
                        );
                    }
                },
                error: function() {
                    button.prop('disabled', false).text('Fetch Player Stats');
                    resultDiv.html(
                        '<p style="color: red;">❌ Could not connect to Python API. Is it running?</p>'
                    );
                }
            });
        });
    });
    </script>
    <?php
}



// Create new tables on plugin activation
register_activation_hook(__FILE__, 'fdm_create_tables');

// Also create footyforums_data tables on activation
register_activation_hook(__FILE__, 'fdm_create_footyforums_tables_on_activation');
function fdm_create_footyforums_tables_on_activation() {
    require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
    fdm_create_footyforums_tables();
}

// Also run on admin init to ensure tables are up to date
add_action('admin_init', 'fdm_check_and_update_tables');

function fdm_check_and_update_tables() {
    global $wpdb;
    
    // Legacy migration from espn_* to e_* naming is complete - no longer needed
    // fdm_migrate_table_names(); // Obsolete - removed
    
    // Optionally call footyforums_data schema migration if needed
    // require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
    // fdm_migrate_footyforums_schema(); // Uncomment if you want to auto-migrate footyforums_data schema
    
    $players_table = fdm_table_players();
    $stats_table = fdm_table_match_stats_e();
    $events_table = fdm_table_match_events_e();
    
    // Check if main players table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$players_table}'");
    if (!$table_exists) {
        fdm_create_tables();
        return;
    }
    
    // Check if match stats table exists, create if missing
    $stats_exists = $wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'");
    if (!$stats_exists) {
        fdm_create_tables();
    }
    
    // Check if match events table exists, create if missing
    $events_exists = $wpdb->get_var("SHOW TABLES LIKE '{$events_table}'");
    if (!$events_exists) {
        fdm_create_tables();
    }
    
    // Check which columns exist
    $columns = $wpdb->get_col("SHOW COLUMNS FROM {$players_table}");
    
    // Add missing columns (check each one individually to avoid errors)
    if (!in_array('name_variant1', $columns)) {
        $wpdb->query("ALTER TABLE {$players_table} ADD COLUMN name_variant1 VARCHAR(100) AFTER canonical_name");
    }
    if (!in_array('name_variant2', $columns)) {
        $wpdb->query("ALTER TABLE {$players_table} ADD COLUMN name_variant2 VARCHAR(100) AFTER name_variant1");
    }
    if (!in_array('nickname', $columns)) {
        $wpdb->query("ALTER TABLE {$players_table} ADD COLUMN nickname VARCHAR(50) AFTER name_variant2");
    }
    if (!in_array('nickname2', $columns)) {
        $wpdb->query("ALTER TABLE {$players_table} ADD COLUMN nickname2 VARCHAR(50) AFTER nickname");
    }
    if (!in_array('age', $columns)) {
        $wpdb->query("ALTER TABLE {$players_table} ADD COLUMN age INT AFTER nationality");
    }
    if (!in_array('date_of_birth', $columns)) {
        $wpdb->query("ALTER TABLE {$players_table} ADD COLUMN date_of_birth DATE AFTER age");
    }
    if (!in_array('r_l_footed', $columns)) {
        $wpdb->query("ALTER TABLE {$players_table} ADD COLUMN r_l_footed ENUM('R','L','Both') DEFAULT 'R' AFTER date_of_birth");
    }
    if (!in_array('injury_status', $columns)) {
        $wpdb->query("ALTER TABLE {$players_table} ADD COLUMN injury_status ENUM('injured','not_injured') DEFAULT 'not_injured' AFTER r_l_footed");
    }
    if (!in_array('sofascore_id', $columns)) {
        $wpdb->query("ALTER TABLE {$players_table} ADD COLUMN sofascore_id VARCHAR(50) UNIQUE AFTER transfermarkt_id");
    }
    if (!in_array('whoscored_id', $columns)) {
        $wpdb->query("ALTER TABLE {$players_table} ADD COLUMN whoscored_id VARCHAR(50) UNIQUE AFTER sofascore_id");
    }
    if (!in_array('e_club_id', $columns)) {
        $wpdb->query("ALTER TABLE {$players_table} ADD COLUMN e_club_id VARCHAR(20) AFTER nickname2");
    }
}

function fdm_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    
    // Master players table (replaces wp_fb_ktpl_players)
    $sql_players = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fdm_players (
        e_id VARCHAR(50) PRIMARY KEY,
        f_id VARCHAR(50) UNIQUE,
        transfermarkt_id VARCHAR(50) UNIQUE,
        sofascore_id VARCHAR(50) UNIQUE,
        whoscored_id VARCHAR(50) UNIQUE,
        canonical_name VARCHAR(100) NOT NULL,
        name_variant1 VARCHAR(100),
        name_variant2 VARCHAR(100),
        nickname VARCHAR(50),
        nickname2 VARCHAR(50),
        e_club_id VARCHAR(20),
        club VARCHAR(100),
        position VARCHAR(20),
        shirt_number INT,
        nationality VARCHAR(50),
        age INT,
        date_of_birth DATE,
        r_l_footed ENUM('R','L','Both') DEFAULT 'R',
        injury_status ENUM('injured','not_injured') DEFAULT 'not_injured',
        status ENUM('active','loan','injured','retired') DEFAULT 'active',
        is_locked TINYINT(1) DEFAULT 0,
        date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset;";
    
    // Name variants for matching (handles different spellings)
    $sql_variants = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fdm_name_variants (
        id MEDIUMINT PRIMARY KEY AUTO_INCREMENT,
        e_id VARCHAR(50) NOT NULL,
        variant_name VARCHAR(100) NOT NULL,
        source VARCHAR(20) NOT NULL,
        added_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_variant (variant_name, source),
        INDEX idx_e (e_id)
    ) $charset;";
    
    // F stats table (holds daily snapshots)
    $sql_f = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fdm_stats_f (
        id MEDIUMINT PRIMARY KEY AUTO_INCREMENT,
        e_id VARCHAR(50) NOT NULL,
        season VARCHAR(10) NOT NULL,
        games INT DEFAULT 0,
        goals INT DEFAULT 0,
        assists INT DEFAULT 0,
        minutes_played INT DEFAULT 0,
        date_scraped DATE NOT NULL,
        UNIQUE KEY unique_stat (e_id, season, date_scraped)
    ) $charset;";
    
    // E match stats table (boxscore data per match)
    $sql_match_stats = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fdm_match_stats_e (
        id MEDIUMINT PRIMARY KEY AUTO_INCREMENT,
        e_match_id VARCHAR(50) NOT NULL,
        e_player_id VARCHAR(50) NOT NULL,
        team_id VARCHAR(20),
        position VARCHAR(20),
        minutes_played INT DEFAULT 0,
        goals INT DEFAULT 0,
        assists INT DEFAULT 0,
        shots INT DEFAULT 0,
        shots_on_target INT DEFAULT 0,
        passes INT DEFAULT 0,
        passes_completed INT DEFAULT 0,
        tackles INT DEFAULT 0,
        interceptions INT DEFAULT 0,
        fouls_committed INT DEFAULT 0,
        fouls_suffered INT DEFAULT 0,
        yellow_cards INT DEFAULT 0,
        red_cards INT DEFAULT 0,
        saves INT DEFAULT 0,
        date_scraped DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_match_player (e_match_id, e_player_id),
        INDEX idx_player (e_player_id),
        INDEX idx_match (e_match_id)
    ) $charset;";
    
    // E match events table (play-by-play data)
    $sql_match_events = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fdm_match_events_e (
        id MEDIUMINT PRIMARY KEY AUTO_INCREMENT,
        e_match_id VARCHAR(50) NOT NULL,
        event_type VARCHAR(50) NOT NULL,
        period INT DEFAULT 1,
        minute INT,
        second INT,
        e_player_id VARCHAR(50),
        player_name VARCHAR(100),
        team_id VARCHAR(20),
        description TEXT,
        event_data TEXT,
        date_scraped DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_match (e_match_id),
        INDEX idx_type (event_type),
        INDEX idx_player (e_player_id)
    ) $charset;";
    
    // Backup table for master players (same structure as fdm_players)
    $sql_backup = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fdm_players_backup (
        e_id VARCHAR(50) PRIMARY KEY,
        f_id VARCHAR(50) UNIQUE,
        transfermarkt_id VARCHAR(50) UNIQUE,
        sofascore_id VARCHAR(50) UNIQUE,
        whoscored_id VARCHAR(50) UNIQUE,
        canonical_name VARCHAR(100) NOT NULL,
        name_variant1 VARCHAR(100),
        name_variant2 VARCHAR(100),
        nickname VARCHAR(50),
        nickname2 VARCHAR(50),
        e_club_id VARCHAR(20),
        club VARCHAR(100),
        position VARCHAR(20),
        shirt_number INT,
        nationality VARCHAR(50),
        age INT,
        date_of_birth DATE,
        r_l_footed ENUM('R','L','Both') DEFAULT 'R',
        injury_status ENUM('injured','not_injured') DEFAULT 'not_injured',
        status ENUM('active','loan','injured','retired') DEFAULT 'active',
        is_locked TINYINT(1) DEFAULT 0,
        date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
    dbDelta($sql_players);
    dbDelta($sql_variants);
    dbDelta($sql_f);
    dbDelta($sql_backup);
    dbDelta($sql_match_stats);
    dbDelta($sql_match_events);
}

/**
 * AJAX handler to set import transient before form submission
 */
function fdm_set_import_transient_callback() {
    check_ajax_referer( 'fdm_set_transient', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    // Get form data from POST
    $season_year = isset( $_POST['fdm_season_year'] ) ? intval( $_POST['fdm_season_year'] ) : 0;
    
    $form_data = array(
        'competitions' => isset( $_POST['fdm_competitions'] ) ? $_POST['fdm_competitions'] : array(),
        'season_start' => isset( $_POST['fdm_season_start'] ) ? sanitize_text_field( $_POST['fdm_season_start'] ) : '',
        'season_end'   => isset( $_POST['fdm_season_end'] ) ? sanitize_text_field( $_POST['fdm_season_end'] ) : '',
        'season_year'  => $season_year,
        'timestamp'    => time(),
    );
    
    // Set transient to track that import is starting, including form data
    set_transient( 'fdm_full_season_import_running', $form_data, 600 );
    
    wp_send_json_success( array( 'message' => 'Transient set', 'data' => $form_data ) );
}

/**
 * AJAX handler to run full season import
 */
function fdm_run_full_season_import_callback() {
    // Increase limits for long-running import
    if ( function_exists( 'ignore_user_abort' ) ) {
        ignore_user_abort( true );
    }

    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 1200 ); // 1200 seconds = 20 minutes
    }

    @ini_set( 'max_execution_time', '1200' );

    // Verify nonce
    $nonce = isset( $_POST['nonce'] ) ? $_POST['nonce'] : '';
    if ( ! wp_verify_nonce( $nonce, 'fdm_ajax_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page and try again.' ) );
        return;
    }
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    require_once FDM_PLUGIN_DIR . 'includes/e_datasource_v2.php';
    require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
    
    // Handle competitions array - can come as array or comma-separated string
    $competitions_raw = isset( $_POST['competitions'] ) ? $_POST['competitions'] : array();
    if ( is_string( $competitions_raw ) ) {
        $competitions_raw = explode( ',', $competitions_raw );
    }
    if ( ! is_array( $competitions_raw ) ) {
        $competitions_raw = array();
    }
    $selected_competitions = array_map( 'sanitize_text_field', $competitions_raw );
    
    $season_start = isset( $_POST['season_start'] ) ? sanitize_text_field( $_POST['season_start'] ) : '';
    $season_end = isset( $_POST['season_end'] ) ? sanitize_text_field( $_POST['season_end'] ) : '';
    
    // Validate
    if ( empty( $selected_competitions ) ) {
        wp_send_json_error( array( 'message' => 'Please select at least one competition.' ) );
        return;
    }
    
    if ( empty( $season_start ) || empty( $season_end ) ) {
        wp_send_json_error( array( 'message' => 'Please provide both start and end dates.' ) );
        return;
    }
    
    // Validate date range
    $start_timestamp = strtotime( $season_start );
    $end_timestamp = strtotime( $season_end );
    
    if ( $start_timestamp === false || $end_timestamp === false ) {
        wp_send_json_error( array( 'message' => 'Invalid date format. Please use YYYY-MM-DD format.' ) );
        return;
    }
    
    if ( $start_timestamp > $end_timestamp ) {
        wp_send_json_error( array( 'message' => 'Start date must be before or equal to end date.' ) );
        return;
    }
    
    $days_diff = ( $end_timestamp - $start_timestamp ) / ( 60 * 60 * 24 );
    $max_days = 365 * 5; // 5 years
    
    if ( $days_diff > $max_days ) {
        wp_send_json_error( array( 'message' => 'Date range is too large. Maximum allowed is 5 years.' ) );
        return;
    }
    
    // Check database connection
    $db = fdm_get_footyforums_db();
    if ( ! $db ) {
        wp_send_json_error( array( 'message' => 'Cannot connect to footyforums_data database. Please check your database configuration.' ) );
        return;
    }
    
    // Increase execution time
    @set_time_limit( 600 ); // 10 minutes
    @ini_set( 'max_execution_time', 600 );
    
    // Start output buffering to capture progress
    ob_start();
    
    $import_start_time = time();
    $start_time_formatted = date( 'H:i:s' );
    
    echo '<p style="margin: 5px 0;"><strong>[' . esc_html( $start_time_formatted ) . '] Import started</strong></p>';
    
    $fdm_e_competitions = FDM_E_Datasource_V2::get_competitions_config();
    
    // Determine season year (for Premier League team-schedule imports)
    $current_year  = (int) date( 'Y' );
    $current_month = (int) date( 'n' );
    $default_season_year = ( $current_month >= 7 ) ? $current_year : ( $current_year - 1 );
    $season_year = isset( $_POST['season_year'] ) ? intval( $_POST['season_year'] ) : $default_season_year;
    if ( $season_year < 1800 || $season_year > ( $current_year + 1 ) ) {
        $season_year = $default_season_year;
    }
    
    // Calculate total work items for progress tracking
    $total_competitions = count( $selected_competitions );
    $total_work_items = 0;
    $work_items_detail = array();
    
    foreach ( $selected_competitions as $comp_key ) {
        if ( ! isset( $fdm_e_competitions[ $comp_key ] ) ) {
            continue;
        }
        $comp_config = $fdm_e_competitions[ $comp_key ];
        $backfill_method = $comp_config['backfill_method'];
        
        if ( $backfill_method === 'team_schedule' && $comp_key === 'premier_league' ) {
            // TEMPORARY: estimate work items as all clubs with an E ID mapped.
            // Once the canonical standings table is ready, this will be restricted per season.
            $clubs = $db->get_results(
                "SELECT COUNT(*) AS count FROM clubs WHERE e_id IS NOT NULL",
                ARRAY_A
            );
            $team_count = isset( $clubs[0]['count'] ) ? intval( $clubs[0]['count'] ) : 0;
            $total_work_items += $team_count;
            $work_items_detail[ $comp_key ] = array( 'type' => 'teams', 'count' => $team_count );
        } elseif ( $backfill_method === 'scoreboard_dates' ) {
            $days = ( strtotime( $season_end ) - strtotime( $season_start ) ) / ( 60 * 60 * 24 );
            $total_work_items += max( 1, round( $days ) );
            $work_items_detail[ $comp_key ] = array( 'type' => 'days', 'count' => round( $days ) );
        } else {
            $total_work_items += 1;
            $work_items_detail[ $comp_key ] = array( 'type' => 'competition', 'count' => 1 );
        }
    }
    
    echo '<p style="margin: 5px 0; color: #666;">📊 <strong>Total work:</strong> ' . $total_competitions . ' competition(s), ' . $total_work_items . ' item(s) to process</p>';
    
    $overall_stats = array(
        'inserted' => 0,
        'updated'  => 0,
        'errors'   => 0,
        'skipped'  => 0,
    );
    
    $competition_stats = array();
    $completed_items = 0;
    $last_progress_time = $import_start_time;
    
    // Helper function to show elapsed time
    if ( ! function_exists( 'fdm_show_elapsed_ajax' ) ) {
        function fdm_show_elapsed_ajax( $start_time ) {
            $elapsed = time() - $start_time;
            $minutes = floor( $elapsed / 60 );
            $seconds = $elapsed % 60;
            return sprintf( '%d:%02d', $minutes, $seconds );
        }
    }
    
    // Helper function to estimate remaining time
    if ( ! function_exists( 'fdm_estimate_remaining_time' ) ) {
        function fdm_estimate_remaining_time( $completed, $total, $elapsed_seconds, $last_progress_time ) {
            if ( $completed <= 0 || $total <= 0 ) {
                return 'Calculating...';
            }
            
            $current_time = time();
            $recent_elapsed = $current_time - $last_progress_time;
            
            // Use average time per item if we have progress, otherwise use overall average
            if ( $recent_elapsed > 0 && $completed > 0 ) {
                $avg_time_per_item = $elapsed_seconds / $completed;
            } else {
                $avg_time_per_item = 2; // Default estimate: 2 seconds per item
            }
            
            $remaining_items = $total - $completed;
            $estimated_seconds = $remaining_items * $avg_time_per_item;
            
            if ( $estimated_seconds < 60 ) {
                return '~' . round( $estimated_seconds ) . ' seconds';
            } elseif ( $estimated_seconds < 3600 ) {
                return '~' . round( $estimated_seconds / 60 ) . ' minutes';
            } else {
                return '~' . round( $estimated_seconds / 3600, 1 ) . ' hours';
            }
        }
    }
    
    $comp_index = 0;
    foreach ( $selected_competitions as $comp_key ) {
        if ( ! isset( $fdm_e_competitions[ $comp_key ] ) ) {
            continue;
        }
        
        $comp_config = $fdm_e_competitions[ $comp_key ];
        $league_code = $comp_config['league_code'];
        $division_name = $comp_config['division_name'];
        $backfill_method = $comp_config['backfill_method'];
        
        $comp_index++;
        $current_time = date( 'H:i:s' );
        $elapsed_seconds = time() - $import_start_time;
        $elapsed = fdm_show_elapsed_ajax( $import_start_time );
        $progress_percent = $total_work_items > 0 ? round( ( $completed_items / $total_work_items ) * 100 ) : 0;
        $estimated_remaining = fdm_estimate_remaining_time( $completed_items, $total_work_items, $elapsed_seconds, $last_progress_time );
        
        echo '<p style="margin: 5px 0;"><strong>[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . ']</strong> Processing ' . esc_html( $division_name ) . '... <span style="color: #2271b1;">(' . $comp_index . '/' . $total_competitions . ' competitions, ' . $progress_percent . '% complete, ~' . $estimated_remaining . ' remaining)</span></p>';
        
        $comp_stats = array(
            'inserted' => 0,
            'updated'  => 0,
            'errors'   => 0,
            'skipped'  => 0,
        );
        
        if ( $backfill_method === 'team_schedule' ) {
            if ( $comp_key === 'premier_league' ) {
                // Premier League: discover participants from E standings for the chosen season year.
                $pl_clubs = FDM_E_Datasource_V2::get_pl_participants_from_e( $season_year );
                
                if ( ! empty( $pl_clubs ) ) {
                    $total_teams = count( $pl_clubs );
                    echo '<p style="margin: 5px 0; padding-left: 20px;">Found ' . $total_teams . ' Premier League participants from E standings. Importing schedules...</p>';
                    
                    $team_count = 0;
                    foreach ( $pl_clubs as $club ) {
                        $team_count++;
                        $completed_items++;
                        $current_time = date( 'H:i:s' );
                        $elapsed_seconds = time() - $import_start_time;
                        $elapsed = fdm_show_elapsed_ajax( $import_start_time );
                        $progress_percent = $total_work_items > 0 ? round( ( $completed_items / $total_work_items ) * 100 ) : 0;
                        $estimated_remaining = fdm_estimate_remaining_time( $completed_items, $total_work_items, $elapsed_seconds, $last_progress_time );
                        $last_progress_time = time();
                        
                        echo '<p style="margin: 2px 0; padding-left: 40px; color: #666;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] Team ' . $team_count . '/' . $total_teams . ': ' . esc_html( $club['canonical_name'] ) . '... <span style="color: #2271b1;">(' . $progress_percent . '% complete, ~' . $estimated_remaining . ' remaining)</span></p>';
                        
                        $team_stats = FDM_E_Datasource_V2::import_team_schedule( $league_code, $club['e_id'] );
                        
                        $comp_stats['inserted'] += $team_stats['inserted'];
                        $comp_stats['updated']  += $team_stats['updated'];
                        $comp_stats['errors']   += $team_stats['errors'];
                        $comp_stats['skipped']  += $team_stats['skipped'];
                        
                        usleep( 200000 );
                    }
                    $current_time = date( 'H:i:s' );
                    $elapsed = fdm_show_elapsed_ajax( $import_start_time );
                    echo '<p style="margin: 5px 0; padding-left: 20px; color: green;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] ✓ ' . esc_html( $division_name ) . ' complete: ' . $comp_stats['inserted'] . ' inserted, ' . $comp_stats['updated'] . ' updated</p>';
                } else {
                    echo '<p style="margin: 5px 0; padding-left: 20px; color: red;">No Premier League participants discovered from E standings for season ' . esc_html( $season_year ) . '.</p>';
                    fdm_log_datasource_error(
                        'no_clubs',
                        'No Premier League clubs discovered from E standings (AJAX full season import)',
                        array(
                            'league_code' => $league_code,
                            'season_year' => $season_year,
                        )
                    );
                }
            }
        } elseif ( $backfill_method === 'scoreboard_dates' ) {
            // Discover competition participants from scoreboard data (for logging and future use).
            $participants = FDM_E_Datasource_V2::get_competition_participants_from_scoreboard( $league_code, $season_start, $season_end );
            $participant_count = is_array( $participants ) ? count( $participants ) : 0;
            
            $days = ( strtotime( $season_end ) - strtotime( $season_start ) ) / ( 60 * 60 * 24 );
            $total_days = max( 1, round( $days ) );
            $current_time = date( 'H:i:s' );
            $elapsed_seconds = time() - $import_start_time;
            $elapsed = fdm_show_elapsed_ajax( $import_start_time );
            $progress_percent = $total_work_items > 0 ? round( ( $completed_items / $total_work_items ) * 100 ) : 0;
            $estimated_remaining = fdm_estimate_remaining_time( $completed_items, $total_work_items, $elapsed_seconds, $last_progress_time );
            
            echo '<p style="margin: 5px 0; padding-left: 20px;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] Found ' . intval( $participant_count ) . ' participants from scoreboard for ' . esc_html( $division_name ) . '.</p>';
            echo '<p style="margin: 5px 0; padding-left: 20px;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] Importing ' . $total_days . ' days of matches... <span style="color: #2271b1;">(' . $progress_percent . '% complete, ~' . $estimated_remaining . ' remaining)</span></p>';
            
            $comp_stats = FDM_E_Datasource_V2::import_league_by_dates( $league_code, $season_start, $season_end );
            
            $completed_items += $total_days;
            $last_progress_time = time();
            
            $current_time = date( 'H:i:s' );
            $elapsed = fdm_show_elapsed_ajax( $import_start_time );
            $final_progress = $total_work_items > 0 ? round( ( $completed_items / $total_work_items ) * 100 ) : 100;
            
            if ( ! empty( $comp_stats['api_aborted'] ) ) {
                echo '<p style="margin: 5px 0; padding-left: 20px; color: #d63638;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] ⚠ ' . esc_html( $division_name ) . ' aborted due to repeated API errors (status ' . esc_html( isset( $comp_stats['api_error_status'] ) ? $comp_stats['api_error_status'] : 'unknown' ) . '). Inserted ' . $comp_stats['inserted'] . ', updated ' . $comp_stats['updated'] . ' <span style="color: #2271b1;">(' . $final_progress . '% overall)</span></p>';
            } else {
            echo '<p style="margin: 5px 0; padding-left: 20px; color: green;">[' . esc_html( $current_time ) . ' | ' . esc_html( $elapsed ) . '] ✓ ' . esc_html( $division_name ) . ' complete: ' . $comp_stats['inserted'] . ' inserted, ' . $comp_stats['updated'] . ' updated <span style="color: #2271b1;">(' . $final_progress . '% overall)</span></p>';
            }
        }
        
        $competition_stats[ $division_name ] = $comp_stats;
        
        $overall_stats['inserted'] += $comp_stats['inserted'];
        $overall_stats['updated']  += $comp_stats['updated'];
        $overall_stats['errors']   += $comp_stats['errors'];
        $overall_stats['skipped']  += $comp_stats['skipped'];
    }
    
    $end_time = time();
    $total_elapsed = fdm_show_elapsed_ajax( $import_start_time );
    $end_time_formatted = date( 'H:i:s' );
    
    // Display final results
    $notice_class = ( $overall_stats['errors'] > 0 ) ? 'notice-error' : 'notice-success';
    echo '<div class="notice ' . $notice_class . '" style="margin-top: 20px;"><p><strong>✅ Full season import complete!</strong></p>';
    echo '<p style="margin: 5px 0;"><strong>Started:</strong> ' . esc_html( $start_time_formatted ) . ' | <strong>Completed:</strong> ' . esc_html( $end_time_formatted ) . ' | <strong>Total time:</strong> ' . esc_html( $total_elapsed ) . '</p>';
    
    foreach ( $competition_stats as $comp_name => $stats ) {
        echo '<p>' . esc_html( $comp_name ) . ': Inserted ' . $stats['inserted'] . ', Updated ' . $stats['updated'] . ', Skipped ' . $stats['skipped'] . ', Errors ' . $stats['errors'] . '.</p>';
    }
    
    echo '<p><strong>Overall:</strong> Inserted ' . $overall_stats['inserted'] . ', Updated ' . $overall_stats['updated'] . ', Skipped ' . $overall_stats['skipped'] . ', Errors ' . $overall_stats['errors'] . '.</p>';
    echo '</div>';
    
    $html_output = ob_get_clean();
    
    wp_send_json_success( array(
        'message' => 'Import completed',
        'html' => $html_output,
        'stats' => $overall_stats
    ) );
}

add_action('wp_ajax_fdm_save_player_stats', 'fdm_save_player_stats_callback');
add_action('wp_ajax_fdm_merge_players', 'fdm_merge_players_callback');
add_action('wp_ajax_fdm_get_e_live_scores', 'fdm_get_e_live_scores_callback');
add_action('wp_ajax_fdm_get_live_scores_frontend', 'fdm_get_live_scores_frontend_callback');
add_action('wp_ajax_nopriv_fdm_get_live_scores_frontend', 'fdm_get_live_scores_frontend_callback');
add_action('wp_ajax_fdm_set_import_transient', 'fdm_set_import_transient_callback');
add_action('wp_ajax_fdm_run_full_season_import', 'fdm_run_full_season_import_callback');

// Register shortcode
add_shortcode('fdm_live_scores', 'fdm_live_scores_shortcode');

// Enqueue frontend scripts
add_action('wp_enqueue_scripts', 'fdm_enqueue_frontend_scripts');

/**
 * AJAX handler to get live scores from E
 * Now reads from footyforums_data database instead of API
 */
function fdm_get_e_live_scores_callback() {
    check_ajax_referer( 'fdm_e_live_scores', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
    }
    
    require_once FDM_PLUGIN_DIR . 'includes/e_datasource_v2.php';
    
    // Get live scores from multiple leagues (reads from footyforums_data)
    $leagues = array( 'eng.1', 'eng.fa', 'eng.league_cup', 'uefa.champions', 'uefa.europa' );
    $matches = FDM_E_Datasource_V2::get_live_scores_from_db( $leagues );
    
    // Format for compatibility
    $formatted_matches = array();
    foreach ( $matches as $match ) {
        $formatted_matches[] = array(
            'id' => $match['id'],
            'match_id' => $match['id'],
            'home_team' => $match['home_team'],
            'away_team' => $match['away_team'],
            'home_score' => $match['home_score'],
            'away_score' => $match['away_score'],
            'status' => $match['status'],
            'league' => isset( $match['competition'] ) ? $match['competition'] : '',
        );
    }
    
    wp_send_json_success( array(
        'live_matches' => $formatted_matches,
        'source' => 'e',
        'count' => count( $formatted_matches )
    ) );
}
function fdm_save_player_stats_callback() {
    check_ajax_referer('fdm_ajax_nonce', 'nonce');
    
    // Get JSON data from AJAX request
    $players_json = isset($_POST['players']) ? $_POST['players'] : '';
    $players = json_decode(stripslashes($players_json), true);
    
    if (empty($players)) {
        wp_send_json_error(array('message' => 'No player data received'));
    }
    
    // Use the new unified import function
    $result_message = fdm_import_f_players_to_unified_table($players);
    
    wp_send_json_success(array(
        'message' => $result_message
    ));
}
/**
 * AJAX handler to merge a PENDING F player with an E player
 */
function fdm_merge_players_callback() {
    check_ajax_referer('fdm_ajax_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    global $wpdb;
    $players_table = fdm_table_players();
    $stats_table = fdm_table_stats_f();
    
    $e_id = isset($_POST['e_id']) ? sanitize_text_field($_POST['e_id']) : '';
    $pending_id = isset($_POST['pending_id']) ? sanitize_text_field($_POST['pending_id']) : '';
    $f_id = isset($_POST['f_id']) ? sanitize_text_field($_POST['f_id']) : '';
    
    if (empty($e_id) || empty($pending_id) || empty($f_id)) {
        wp_send_json_error(array(
            'message' => 'Missing required data',
            'debug' => array(
                'e_id' => $e_id,
                'pending_id' => $pending_id,
                'f_id' => $f_id
            )
        ));
    }
    
    // Verify the PENDING entry exists
    $pending_player = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$players_table} WHERE e_id = %s",
            $pending_id
        )
    );
    
    if (!$pending_player) {
        wp_send_json_error(array('message' => 'PENDING player not found'));
    }
    
    // Verify the f_id matches what's in the PENDING player record
    if ($pending_player->f_id !== $f_id) {
        // Use the f_id from the database instead of what was sent
        $f_id = $pending_player->f_id;
        if (empty($f_id)) {
            wp_send_json_error(array(
                'message' => 'PENDING player has no F ID',
                'debug' => array(
                    'pending_id' => $pending_id,
                    'pending_f_id' => $pending_player->f_id,
                    'sent_f_id' => $_POST['f_id']
                )
            ));
            return;
        }
    }
    
    // Verify the API player exists
    $e_player = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$players_table} WHERE e_id = %s",
            $e_id
        )
    );
    
    if (!$e_player) {
        wp_send_json_error(array('message' => 'API player not found'));
    }
    
    // Check if f_id is already assigned to a different player
    // Exclude both the API player we're merging to AND the PENDING entry we're merging from
    $existing_f = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT e_id FROM {$players_table} WHERE f_id = %s AND e_id != %s AND e_id != %s",
            $f_id,
            $e_id,
            $pending_id
        )
    );
    
    if ($existing_f) {
        wp_send_json_error(array(
            'message' => 'F ID ' . $f_id . ' is already assigned to player ' . $existing_f
        ));
        return;
    }
    
    // First, clear the f_id from the PENDING entry to avoid UNIQUE constraint violation
    // We'll set it to NULL temporarily, then update the API player, then delete the PENDING entry
    $wpdb->update(
        $players_table,
        array('f_id' => null),
        array('e_id' => $pending_id),
        array('%s'),
        array('%s')
    );
    
    // Merge F data into API player
    // Start with F ID (required)
    $update_data = array('f_id' => $f_id);
    $format = array('%s'); // Format for f_id
    
    // Copy position if API doesn't have it or if F has it
    if (!empty($pending_player->position)) {
        $update_data['position'] = $pending_player->position;
        $format[] = '%s';
    }
    
    // Copy club if API doesn't have it or if F has it
    if (!empty($pending_player->club)) {
        $update_data['club'] = $pending_player->club;
        $format[] = '%s';
    }
    
    // Update API player with merged data
    $update_result = $wpdb->update(
        $players_table,
        $update_data,
        array('e_id' => $e_id),
        $format,
        array('%s')
    );
    
    // Check if update succeeded
    if ($update_result === false) {
        wp_send_json_error(array(
            'message' => 'Failed to update API player: ' . $wpdb->last_error
        ));
        return;
    }
    
    // Verify the update actually worked by checking the row
    $verify = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT f_id FROM {$players_table} WHERE e_id = %s",
            $e_id
        )
    );
    
    if ($verify !== $f_id) {
        wp_send_json_error(array(
            'message' => 'Update appeared to succeed but f_id was not set. Expected: ' . $f_id . ', Got: ' . ($verify ?: 'NULL')
        ));
        return;
    }
    
    // Update all stats records that reference the PENDING ID to use the E ID
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$stats_table} SET e_id = %s WHERE e_id = %s",
            $e_id,
            $pending_id
        )
    );
    
    // Delete the PENDING entry
    $delete_result = $wpdb->delete(
        $players_table,
        array('e_id' => $pending_id),
        array('%s')
    );
    
    if ($delete_result === false) {
        // Log warning but don't fail - the merge still succeeded
        error_log('Warning: Failed to delete PENDING entry ' . $pending_id . ': ' . $wpdb->last_error);
    }
    
    wp_send_json_success(array(
        'message' => 'Players merged successfully. F ID: ' . $f_id . ' assigned to API ID: ' . $e_id
    ));
}
/**
 * Match Data Imports Page - Moved from kopthis-prediction-league.php
 * Handles importing match results and team data from the match data API
 * Now independent - uses trait methods directly
 */
function fdm_render_e_imports_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Load the datasource trait
    require_once FDM_PLUGIN_DIR . 'includes/e_datasource.php';

    // Create a temporary class to use the trait
    if ( ! class_exists( 'FDM_E_Datasource' ) ) {
        class FDM_E_Datasource {
            use KopThis_Prediction_League_Datasource;
        }
    }

    $message = '';

    // Handle submits
    if (
        isset( $_POST['ktpl_import_eng1'] ) ||
        isset( $_POST['ktpl_import_engfa'] ) ||
        isset( $_POST['ktpl_import_efl'] ) ||
        isset( $_POST['ktpl_import_ucl'] ) ||
        isset( $_POST['ktpl_import_uel'] ) ||
        isset( $_POST['ktpl_import_all'] ) ||
        isset( $_POST['ktpl_import_teams_all'] ) ||
        isset( $_POST['ktpl_import_squads_all'] ) ||
        isset( $_POST['ktpl_sync_club_ids'] ) ||
        isset( $_POST['fdm_import_to_unified'] ) ||
        isset( $_POST['fdm_update_club_names'] ) ||
        isset( $_POST['fdm_import_boxscore'] ) ||
        isset( $_POST['fdm_import_playbyplay'] ) ||
        isset( $_POST['fdm_import_both'] ) ||
        isset( $_POST['fdm_import_all_boxscores'] ) ||
        isset( $_POST['fdm_import_all_playbyplay'] ) ||
        isset( $_POST['fdm_import_all_both'] ) ||
        isset( $_POST['fdm_create_tables'] ) ||
        isset( $_POST['fdm_clear_cache'] ) ||
        isset( $_POST['fdm_populate_e_match_ids'] ) ||
        isset( $_POST['fdm_import_historical_matches'] ) ||
        isset( $_POST['fdm_import_team_schedule'] ) ||
        isset( $_POST['fdm_import_all_teams_schedules'] ) ||
        isset( $_POST['fdm_import_by_match_ids'] )
    ) {
        check_admin_referer( 'ktpl_e_imports_action', 'ktpl_e_imports_nonce' );

        $messages = array();

        // Sync Club IDs button handler
        if ( isset( $_POST['ktpl_sync_club_ids'] ) ) {
            $messages[] = FDM_E_Datasource::sync_e_club_ids();
        }
        // Import E players to unified table
        if ( isset( $_POST['fdm_import_to_unified'] ) ) {
            $messages[] = fdm_import_e_players_to_unified_table();
        }
        
        // Update club names for existing E players
        if ( isset( $_POST['fdm_update_club_names'] ) ) {
            $messages[] = fdm_update_e_player_club_names();
        }
        
        // Create/update database tables
        if ( isset( $_POST['fdm_create_tables'] ) ) {
            fdm_create_tables();
            $messages[] = 'Database tables created/updated successfully.';
        }
        
        // Clear E cache
        if ( isset( $_POST['fdm_clear_cache'] ) ) {
            require_once FDM_PLUGIN_DIR . 'includes/e_datasource.php';
            if ( ! class_exists( 'FDM_E_Datasource' ) ) {
                class FDM_E_Datasource {
                    use KopThis_Prediction_League_Datasource;
                }
            }
            $deleted = FDM_E_Datasource::clear_e_cache();
            $messages[] = sprintf( 'API cache cleared: %d cached files deleted.', $deleted );
        }
        
        // Import historical matches from E
        if ( isset( $_POST['fdm_import_historical_matches'] ) ) {
            require_once FDM_PLUGIN_DIR . 'includes/e_datasource.php';
            if ( ! class_exists( 'FDM_E_Datasource' ) ) {
                class FDM_E_Datasource {
                    use KopThis_Prediction_League_Datasource;
                }
            }
            $start_date = isset( $_POST['fdm_historical_start_date'] ) ? sanitize_text_field( $_POST['fdm_historical_start_date'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
            $end_date = isset( $_POST['fdm_historical_end_date'] ) ? sanitize_text_field( $_POST['fdm_historical_end_date'] ) : date( 'Y-m-d' );
            $league = isset( $_POST['fdm_historical_league'] ) ? sanitize_text_field( $_POST['fdm_historical_league'] ) : 'eng.1';
            $result = FDM_E_Datasource::import_historical_matches_from_e( $league, $start_date, $end_date );
            $messages[] = $result;
        }
        
        // Populate E Match IDs for historical matches
        if ( isset( $_POST['fdm_populate_e_match_ids'] ) ) {
            require_once FDM_PLUGIN_DIR . 'includes/e_datasource.php';
            if ( ! class_exists( 'FDM_E_Datasource' ) ) {
                class FDM_E_Datasource {
                    use KopThis_Prediction_League_Datasource;
                }
            }
            $result = FDM_E_Datasource::populate_e_match_ids_for_historical_matches();
            $messages[] = $result;
        }
        
        // Import match statistics
        if ( isset( $_POST['fdm_import_boxscore'] ) || isset( $_POST['fdm_import_both'] ) ) {
            $match_id = isset( $_POST['fdm_e_match_id'] ) ? sanitize_text_field( $_POST['fdm_e_match_id'] ) : '';
            if ( ! empty( $match_id ) ) {
                // Use first league code as default, could be improved to detect from match
                $messages[] = FDM_E_Datasource::import_match_boxscore( 'eng.1', $match_id );
            } else {
                $messages[] = 'Error: Match ID required for boxscore import.';
            }
        }
        
        if ( isset( $_POST['fdm_import_playbyplay'] ) || isset( $_POST['fdm_import_both'] ) ) {
            $match_id = isset( $_POST['fdm_e_match_id'] ) ? sanitize_text_field( $_POST['fdm_e_match_id'] ) : '';
            if ( ! empty( $match_id ) ) {
                $messages[] = FDM_E_Datasource::import_match_playbyplay( 'eng.1', $match_id );
            } else {
                $messages[] = 'Error: Match ID required for play-by-play import.';
            }
        }
        
        // Bulk import for all matches
        if ( isset( $_POST['fdm_import_all_boxscores'] ) || isset( $_POST['fdm_import_all_both'] ) ) {
            $league_filter = isset( $_POST['fdm_league_filter'] ) ? sanitize_text_field( $_POST['fdm_league_filter'] ) : null;
            $limit = isset( $_POST['fdm_import_limit'] ) ? intval( $_POST['fdm_import_limit'] ) : null;
            $messages[] = FDM_E_Datasource::import_all_match_boxscores( $league_filter, $limit );
        }
        
        if ( isset( $_POST['fdm_import_all_playbyplay'] ) || isset( $_POST['fdm_import_all_both'] ) ) {
            $league_filter = isset( $_POST['fdm_league_filter'] ) ? sanitize_text_field( $_POST['fdm_league_filter'] ) : null;
            $limit = isset( $_POST['fdm_import_limit'] ) ? intval( $_POST['fdm_import_limit'] ) : null;
            $messages[] = FDM_E_Datasource::import_all_match_playbyplay( $league_filter, $limit );
        }
        // Results and matches
        if ( isset( $_POST['ktpl_import_eng1'] ) || isset( $_POST['ktpl_import_all'] ) ) {
            $messages[] = FDM_E_Datasource::update_liverpool_results_from_e( 'eng.1' );
        }
        if ( isset( $_POST['ktpl_import_engfa'] ) || isset( $_POST['ktpl_import_all'] ) ) {
            $messages[] = FDM_E_Datasource::update_liverpool_results_from_e( 'eng.fa' );
        }
        if ( isset( $_POST['ktpl_import_efl'] ) || isset( $_POST['ktpl_import_all'] ) ) {
            $messages[] = FDM_E_Datasource::update_liverpool_results_from_e( 'eng.league_cup' );
        }
        if ( isset( $_POST['ktpl_import_ucl'] ) || isset( $_POST['ktpl_import_all'] ) ) {
            $messages[] = FDM_E_Datasource::update_liverpool_results_from_e( 'uefa.champions' );
        }
        if ( isset( $_POST['ktpl_import_uel'] ) || isset( $_POST['ktpl_import_all'] ) ) {
            $messages[] = FDM_E_Datasource::update_liverpool_results_from_e( 'uefa.europa' );
        }

        // Team directory for all leagues
        if ( isset( $_POST['ktpl_import_teams_all'] ) ) {
            $leagues = array( 'eng.1', 'eng.fa', 'eng.league_cup', 'uefa.champions', 'uefa.europa' );
            foreach ( $leagues as $lg ) {
                $added = FDM_E_Datasource::import_teams_from_e_league( $lg );
                $messages[] = "Teams {$lg}: {$added} added/updated.";
            }
        }

        // Squads for all known teams
        if ( isset( $_POST['ktpl_import_squads_all'] ) ) {
            $added = FDM_E_Datasource::import_squads_for_known_teams();
            $messages[] = "Squads: {$added} players added/updated.";
        }
        
        // Import team schedule matches (single team)
        if ( isset( $_POST['fdm_import_team_schedule'] ) ) {
            require_once FDM_PLUGIN_DIR . 'includes/e_datasource.php';
            if ( ! class_exists( 'FDM_E_Datasource' ) ) {
                class FDM_E_Datasource {
                    use KopThis_Prediction_League_Datasource;
                }
            }
            $team_id = isset( $_POST['fdm_team_e_id'] ) ? sanitize_text_field( $_POST['fdm_team_e_id'] ) : '';
            $league = isset( $_POST['fdm_team_league'] ) ? sanitize_text_field( $_POST['fdm_team_league'] ) : 'eng.1';
            if ( ! empty( $team_id ) ) {
                $result = FDM_E_Datasource::import_matches_from_team_schedule( $league, $team_id );
                $messages[] = $result;
            } else {
                $messages[] = 'Error: Team selection required for team schedule import.';
            }
        }
        
        // Import all teams' schedules for a league
        if ( isset( $_POST['fdm_import_all_teams_schedules'] ) ) {
            require_once FDM_PLUGIN_DIR . 'includes/e_datasource.php';
            if ( ! class_exists( 'FDM_E_Datasource' ) ) {
                class FDM_E_Datasource {
                    use KopThis_Prediction_League_Datasource;
                }
            }
            $league = isset( $_POST['fdm_all_teams_league'] ) ? sanitize_text_field( $_POST['fdm_all_teams_league'] ) : 'eng.1';
            $result = FDM_E_Datasource::import_all_teams_schedules( $league );
            $messages[] = $result;
        }
        
        // Import by match IDs
        if ( isset( $_POST['fdm_import_by_match_ids'] ) ) {
            require_once FDM_PLUGIN_DIR . 'includes/e_datasource.php';
            if ( ! class_exists( 'FDM_E_Datasource' ) ) {
                class FDM_E_Datasource {
                    use KopThis_Prediction_League_Datasource;
                }
            }
            $match_ids_string = isset( $_POST['fdm_match_ids'] ) ? sanitize_text_field( $_POST['fdm_match_ids'] ) : '';
            $league = isset( $_POST['fdm_match_ids_league'] ) ? sanitize_text_field( $_POST['fdm_match_ids_league'] ) : 'eng.1';
            if ( ! empty( $match_ids_string ) ) {
                $result = FDM_E_Datasource::import_matches_by_ids( $league, $match_ids_string );
                $messages[] = $result;
            } else {
                $messages[] = 'Error: Match IDs required.';
            }
        }

        $messages = array_filter( $messages );
        $message  = ! empty( $messages ) ? implode( ' ', $messages ) : 'Match data imports finished.';
    }

    // Output
    echo '<div class="wrap">';
    echo '<h1>Match Data Imports</h1>';

    if ( $message !== '' ) {
        echo '<div class="updated notice"><p>' . esc_html( $message ) . '</p></div>';
    }

    echo '<form method="post" action="">';
    wp_nonce_field( 'ktpl_e_imports_action', 'ktpl_e_imports_nonce' );

    echo '<p>Update matches and results from the match data API for each competition.</p>';

    echo '<p>';
    echo '<input type="submit" name="ktpl_import_eng1" class="button button-secondary" value="Import Premier League"> ';
    echo '<input type="submit" name="ktpl_import_engfa" class="button button-secondary" value="Import FA Cup"> ';
    echo '<input type="submit" name="ktpl_import_efl" class="button button-secondary" value="Import Carabao Cup"> ';
    echo '<input type="submit" name="ktpl_import_ucl" class="button button-secondary" value="Import Champions League"> ';
    echo '<input type="submit" name="ktpl_import_uel" class="button button-secondary" value="Import Europa League">';
    echo '</p>';

    echo '<p><input type="submit" name="ktpl_import_all" class="button button-primary" value="Import all competitions"></p>';

    echo '<hr>';

    echo '<h2>Club ID Management</h2>';
    echo '<p>Sync E numeric club IDs with the master database:</p>';
    echo '<p><input type="submit" name="ktpl_sync_club_ids" class="button button-primary" value="Sync Club IDs"></p>';

    echo '<hr>';

    echo '<p>';
    echo '<input type="submit" name="ktpl_import_teams_all" class="button" value="Import Teams (all competitions)"> ';
    echo '<input type="submit" name="ktpl_import_squads_all" class="button" value="Import Squads for Known Teams">';
    echo '</p>';
    echo '<hr>';
    
    echo '<h2>Unified Database Import</h2>';
    echo '<p>Import E players from wp_ktpl_players into the new unified wp_fdm_players table:</p>';
    echo '<p><input type="submit" name="fdm_import_to_unified" class="button button-primary" value="Import to Unified Table"></p>';
    
    echo '<hr>';
    
    echo '<h2>Update Club Names</h2>';
    echo '<p>Update club names for existing E players (run after "Sync Club IDs"):</p>';
    echo '<p><input type="submit" name="fdm_update_club_names" class="button" value="Update Club Names for E Players"></p>';
    
    echo '<hr>';
    
    echo '<h2>Import Historical Matches</h2>';
    echo '<p><strong>Note:</strong> The scoreboard API only returns recent/future matches. For truly historical matches, try the team schedule method below or use match IDs directly.</p>';
    echo '<p>Fetch historical matches from E API by date range and import them into the database:</p>';
    echo '<p>';
    echo '<label>Start Date: <input type="date" name="fdm_historical_start_date" value="' . date('Y-m-d', strtotime('-30 days')) . '" style="width: 150px;"></label> ';
    echo '<label>End Date: <input type="date" name="fdm_historical_end_date" value="' . date('Y-m-d') . '" style="width: 150px;"></label><br><br>';
    echo '<label>League: ';
    echo '<select name="fdm_historical_league">';
    echo '<option value="eng.1">Premier League</option>';
    echo '<option value="eng.fa">FA Cup</option>';
    echo '<option value="eng.league_cup">Carabao Cup</option>';
    echo '<option value="uefa.champions">Champions League</option>';
    echo '<option value="uefa.europa">Europa League</option>';
    echo '</select></label><br><br>';
    echo '<input type="submit" name="fdm_import_historical_matches" class="button button-primary" value="Import Historical Matches (Scoreboard API)">';
    echo '</p>';
    echo '<p><small>This will fetch completed matches from E scoreboard API for the specified date range. <strong>Limitation:</strong> Only returns recent matches (last few weeks), not old historical data.</small></p>';
    
    echo '<hr>';
    
    echo '<h2>Import Matches from Team Schedule (Alternative Method)</h2>';
    echo '<p>Try fetching matches from team schedule endpoints (may have more historical data):</p>';
    
    // Get teams for dropdown
    global $wpdb;
    $clubs_table = $wpdb->prefix . 'fdm_clubs';
    $teams_table = $wpdb->prefix . 'ktpl_teams';
    
    // Try to get teams from fdm_clubs first, fallback to ktpl_teams
    $all_teams = $wpdb->get_results(
        "SELECT DISTINCT e_team_id, canonical_name 
         FROM {$clubs_table} 
         WHERE e_team_id IS NOT NULL AND e_team_id != ''
         ORDER BY canonical_name ASC"
    );
    
    if ( empty( $all_teams ) ) {
        $all_teams = $wpdb->get_results(
            "SELECT DISTINCT e_team_id, name as canonical_name 
             FROM {$teams_table} 
             WHERE e_team_id IS NOT NULL AND e_team_id != ''
             ORDER BY name ASC"
        );
    }
    
    echo '<h3>Single Team Import</h3>';
    echo '<p>';
    echo '<label>Team: ';
    echo '<select name="fdm_team_e_id" style="width: 250px;">';
    echo '<option value="">-- Select Team --</option>';
    if ( ! empty( $all_teams ) ) {
        foreach ( $all_teams as $team ) {
            echo '<option value="' . esc_attr( $team->e_team_id ) . '">' . esc_html( $team->canonical_name ) . '</option>';
        }
    }
    echo '</select></label><br><br>';
    echo '<label>League: ';
    echo '<select name="fdm_team_league">';
    echo '<option value="eng.1">Premier League</option>';
    echo '<option value="eng.fa">FA Cup</option>';
    echo '<option value="eng.league_cup">Carabao Cup</option>';
    echo '<option value="uefa.champions">Champions League</option>';
    echo '<option value="uefa.europa">Europa League</option>';
    echo '</select></label><br><br>';
    echo '<input type="submit" name="fdm_import_team_schedule" class="button" value="Import Team Schedule Matches">';
    echo '</p>';
    
    echo '<h3>Bulk Import (All Teams)</h3>';
    echo '<p>Import matches from all teams in a competition:</p>';
    echo '<p>';
    echo '<label>League: ';
    echo '<select name="fdm_all_teams_league">';
    echo '<option value="eng.1">Premier League</option>';
    echo '<option value="eng.fa">FA Cup</option>';
    echo '<option value="eng.league_cup">Carabao Cup</option>';
    echo '<option value="uefa.champions">Champions League</option>';
    echo '<option value="uefa.europa">Europa League</option>';
    echo '</select></label><br><br>';
    echo '<input type="submit" name="fdm_import_all_teams_schedules" class="button button-primary" value="Import All Teams\' Schedules">';
    echo '</p>';
    echo '<p><small><strong>Note:</strong> This will process all teams in the selected league. It may take several minutes depending on the number of teams. Rate limiting is applied automatically.</small></p>';
    
    if ( empty( $all_teams ) ) {
        echo '<p style="color: #d63638;"><strong>Warning:</strong> No teams found in database. Please run "Sync Club IDs" or "Import Teams (all competitions)" first to populate the team list.</p>';
    }
    
    echo '<p><small>This queries the team endpoint which may include more historical match data than the scoreboard API.</small></p>';
    
    echo '<hr>';
    
    echo '<h2>Import by Match IDs (Manual Entry)</h2>';
    echo '<p>If you have match IDs from another source, import them directly:</p>';
    echo '<p>';
    echo '<label>Match IDs (comma-separated): <input type="text" name="fdm_match_ids" placeholder="e.g., 401131040,401131041" style="width: 400px;"></label><br><br>';
    echo '<label>League: ';
    echo '<select name="fdm_match_ids_league">';
    echo '<option value="eng.1">Premier League</option>';
    echo '<option value="eng.fa">FA Cup</option>';
    echo '<option value="eng.league_cup">Carabao Cup</option>';
    echo '<option value="uefa.champions">Champions League</option>';
    echo '<option value="uefa.europa">Europa League</option>';
    echo '</select></label><br><br>';
    echo '<input type="submit" name="fdm_import_by_match_ids" class="button" value="Import by Match IDs">';
    echo '</p>';
    echo '<p><small>Enter match IDs. This will fetch match details and import them.</small></p>';
    
    echo '<hr>';
    
    echo '<h2>Populate Match IDs</h2>';
    echo '<p>Match existing historical matches to match IDs by date and team names. This allows bulk import to work with historical matches.</p>';
    echo '<p><input type="submit" name="fdm_populate_e_match_ids" class="button" value="Populate Match IDs for Historical Matches"></p>';
    echo '<p><small>This will query the API for matches by date and team names to find missing api_match_id values.</small></p>';
    
    echo '<hr>';
    
    echo '<h2>Database Setup</h2>';
    echo '<p>Create or update database tables (including match stats tables):</p>';
    echo '<p><input type="submit" name="fdm_create_tables" class="button button-primary" value="Create/Update Database Tables"></p>';
    echo '<p><small>This will create: wp_fdm_players, wp_fdm_match_stats_e, wp_fdm_match_events_e, and other required tables.</small></p>';
    
    echo '<hr>';
    
    echo '<h2>Match Statistics Import</h2>';
    
    // Single match import
    echo '<h3>Single Match Import</h3>';
    echo '<p>Import detailed match statistics (boxscore) and events (play-by-play) for a specific match:</p>';
    echo '<p>';
    echo '<label>Match ID: <input type="text" name="fdm_e_match_id" placeholder="e.g., 401131040" style="width: 200px;"></label><br><br>';
    echo '<input type="submit" name="fdm_import_boxscore" class="button" value="Import Boxscore"> ';
    echo '<input type="submit" name="fdm_import_playbyplay" class="button" value="Import Play-by-Play"> ';
    echo '<input type="submit" name="fdm_import_both" class="button button-primary" value="Import Both">';
    echo '</p>';
    echo '<p><small>Note: Match IDs can be found in match URLs or from the scoreboard data.</small></p>';
    
    echo '<hr>';
    
    // Bulk import
    echo '<h3>Bulk Import (All Matches)</h3>';
    echo '<p>Import statistics for all matches in your database:</p>';
    echo '<p>';
    echo '<label>Filter by League (optional): ';
    echo '<select name="fdm_league_filter">';
    echo '<option value="">All Leagues</option>';
    echo '<option value="eng.1">Premier League</option>';
    echo '<option value="eng.fa">FA Cup</option>';
    echo '<option value="eng.league_cup">Carabao Cup</option>';
    echo '<option value="uefa.champions">Champions League</option>';
    echo '<option value="uefa.europa">Europa League</option>';
    echo '</select></label><br><br>';
    echo '<label>Limit (optional, leave empty for all): <input type="number" name="fdm_import_limit" placeholder="e.g., 50" style="width: 100px;" min="1"></label><br><br>';
    echo '<input type="submit" name="fdm_import_all_boxscores" class="button" value="Import All Boxscores"> ';
    echo '<input type="submit" name="fdm_import_all_playbyplay" class="button" value="Import All Play-by-Play"> ';
    echo '<input type="submit" name="fdm_import_all_both" class="button button-primary" value="Import All (Both)">';
    echo '</p>';
    echo '<p><small><strong>Warning:</strong> This will process all matches in your database. Use the limit field to test with a smaller batch first. This may take a while.</small></p>';
    echo '<p><small><strong>Rate Limiting:</strong> The system automatically adds 2-3 second delays between requests to respect API rate limits. For 100 matches, expect ~5-10 minutes. If you encounter rate limit errors (429), wait 30-60 minutes before retrying.</small></p>';
    echo '<p><small><strong>Caching:</strong> API responses are cached for 24 hours in <code>wp-content/uploads/fdm-e-cache/</code>. Cached matches won\'t make new requests to the API.</small></p>';
    
    echo '<hr>';
    
    echo '<h2>Cache Management</h2>';
    echo '<p>Clear cached API data (useful if you need fresh data or want to free up space):</p>';
    echo '<p><input type="submit" name="fdm_clear_cache" class="button button-secondary" value="Clear API Cache" onclick="return confirm(\'Are you sure you want to clear all cached API data? This will force fresh requests on next import.\');"></p>';
    
    echo '<hr>';
    
    // Show verification stats
    global $wpdb;
    $clubs_table = $wpdb->prefix . 'fdm_clubs';
    $teams_table = $wpdb->prefix . 'ktpl_teams';
    $players_table = fdm_table_players();
    
    $clubs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$clubs_table}");
    $teams_count = $wpdb->get_var("SELECT COUNT(DISTINCT e_team_id) FROM {$teams_table} WHERE e_team_id IS NOT NULL AND e_team_id != ''");
    $api_players_with_clubs = $wpdb->get_var("SELECT COUNT(*) FROM {$players_table} WHERE e_id NOT LIKE 'PENDING_%' AND club IS NOT NULL AND club != ''");
    $api_players_without_clubs = $wpdb->get_var("SELECT COUNT(*) FROM {$players_table} WHERE e_id NOT LIKE 'PENDING_%' AND (club IS NULL OR club = '')");
    
    echo '<h2>Verification Stats</h2>';
    echo '<table class="widefat">';
    echo '<tr><th>Item</th><th>Count</th></tr>';
    echo '<tr><td>Teams in wp_ktpl_teams (source)</td><td>' . $teams_count . '</td></tr>';
    echo '<tr><td>Clubs in wp_fdm_clubs (synced)</td><td>' . $clubs_count . '</td></tr>';
    echo '<tr><td>API players WITH club names</td><td>' . $api_players_with_clubs . '</td></tr>';
    echo '<tr><td>API players WITHOUT club names</td><td>' . $api_players_without_clubs . '</td></tr>';
    echo '</table>';
    
    // Show sample clubs
    if ($clubs_count > 0) {
        $sample_clubs = $wpdb->get_results("SELECT e_team_id, canonical_name FROM {$clubs_table} LIMIT 10");
        echo '<h3>Sample Clubs (first 10):</h3>';
        echo '<ul>';
        foreach ($sample_clubs as $club) {
            echo '<li>ID: ' . esc_html($club->e_team_id) . ' → Name: ' . esc_html($club->canonical_name) . '</li>';
        }
        echo '</ul>';
    }
    
    echo '<hr>';
    echo '</form>';
    echo '</div>';
}

/**
 * Update club names for existing API players in unified table
 * Looks up club IDs from old table and converts to names using fdm_clubs
 */
function fdm_update_e_player_club_names() {
    global $wpdb;
    
    $players_table = fdm_table_players();
    $old_table = $wpdb->prefix . 'ktpl_players';
    $clubs_table = $wpdb->prefix . 'fdm_clubs';
    
    // Get all API players without club names
    $e_players = $wpdb->get_results(
        "SELECT e_id FROM {$players_table} 
         WHERE e_id NOT LIKE 'PENDING_%' 
         AND (club IS NULL OR club = '')"
    );
    
    if (empty($e_players)) {
        return 'No API players found without club names.';
    }
    
    $updated = 0;
    $not_found = 0;
    
    // Check which column name exists in old table
    $old_table_columns = $wpdb->get_col("SHOW COLUMNS FROM {$old_table}");
    $team_id_column = in_array('current_team_e_id', $old_table_columns) ? 'current_team_e_id' : null;
    
    foreach ($e_players as $player) {
        // Get club ID from old table
        if (!$team_id_column) {
            $not_found++;
            continue;
        }
        
        $old_player = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$team_id_column} FROM {$old_table} WHERE external_key = %s",
                'e:' . $player->e_id
            )
        );
        
        if (!$old_player || empty($old_player->$team_id_column)) {
            $not_found++;
            continue;
        }
        
        // Get club name from clubs table (store both ID and name)
        $club_id = !empty($old_player->$team_id_column) ? sanitize_text_field($old_player->$team_id_column) : null;
        $club_name = '';
        if (!empty($club_id)) {
            $club = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT canonical_name FROM {$clubs_table} WHERE e_team_id = %s",
                    $club_id
                )
            );
            if ($club) {
                $club_name = $club->canonical_name;
            }
        }
        
        if ($club || !empty($club_id)) {
            $update_data = array(
                'e_club_id' => $club_id,
                'club' => !empty($club_name) ? $club_name : null
            );
            $wpdb->update(
                $players_table,
                $update_data,
                array('e_id' => $player->e_id),
                array('%s', '%s'),
                array('%s')
            );
            $updated++;
        } else {
            $not_found++;
        }
    }
    
    return sprintf('Updated %d players with club names. %d players had no club ID or club not found in clubs table.', $updated, $not_found);
}

/**
 * Import API players into the new unified fdm_players table
 * Converts club IDs to club names and extracts API ID from external_key
 */
function fdm_import_e_players_to_unified_table() {
    global $wpdb;
    
    $old_table = $wpdb->prefix . 'ktpl_players';
    $new_table = fdm_table_players();
    $clubs_table = $wpdb->prefix . 'fdm_clubs';
    
    // Ensure table structure is up to date
    fdm_check_and_update_tables();
    
    // Auto-sync club IDs from API if clubs table is empty
    $clubs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$clubs_table}");
    if ($clubs_count == 0) {
        // Load the scraper trait
        require_once FDM_PLUGIN_DIR . 'includes/e_datasource.php';
        
        // Create a temporary class to use the trait
        if ( ! class_exists( 'FDM_E_Datasource_Temp' ) ) {
            class FDM_E_Datasource_Temp {
                use KopThis_Prediction_League_Datasource;
            }
        }
        
        $sync_message = FDM_E_Datasource::sync_e_club_ids();
        // Note: sync_message is logged but we continue with import
    }
    
    // Backup before import
    $backup_message = fdm_backup_players_table();

    // Check if old table exists
    $old_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$old_table}'");
    if (!$old_table_exists) {
        return 'Error: wp_ktpl_players table does not exist.';
    }
    
    // Check which column name exists in old table
    $old_table_columns = $wpdb->get_col("SHOW COLUMNS FROM {$old_table}");
    $team_id_column = in_array('current_team_e_id', $old_table_columns) ? 'current_team_e_id' : 'current_team_e_id';
    
    // Get all API players from old table
    $e_players = $wpdb->get_results(
        "SELECT external_key, full_name, short_name, position, shirt_number, 
                {$team_id_column} as current_team_e_id, active_in_squad, created_at
         FROM {$old_table}
         WHERE external_key LIKE 'e:%'"
    );
    
    if (empty($e_players)) {
        $total_in_old = $wpdb->get_var("SELECT COUNT(*) FROM {$old_table}");
        return sprintf('No API players found in wp_ktpl_players table. Total players in old table: %d', $total_in_old);
    }
    
    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $error_messages = array();
    
    foreach ($e_players as $player) {
        // Extract E ID from external_key (remove 'e:' prefix)
        $e_id = str_replace('e:', '', $player->external_key);
        
        if (empty($e_id) || empty($player->full_name)) {
            $skipped++;
            continue;
        }
        
        // Get club name from club ID (store both ID and name)
        $club_id = !empty($player->current_team_e_id) ? sanitize_text_field($player->current_team_e_id) : null;
        $club_name = '';
        if (!empty($club_id)) {
            $club = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT canonical_name FROM {$clubs_table} WHERE e_team_id = %s",
                    $club_id
                )
            );
            if ($club) {
                $club_name = $club->canonical_name;
            }
        }
        
        // Check if player already exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT e_id FROM {$new_table} WHERE e_id = %s",
                $e_id
            )
        );
        
        $canonical_name = sanitize_text_field($player->full_name);
        if (empty($canonical_name)) {
            $skipped++;
            continue;
        }
        
        $player_data = array(
            'e_id' => $e_id,
            'canonical_name' => $canonical_name,
            'name_variant1' => !empty($player->short_name) ? sanitize_text_field($player->short_name) : null,
            'e_club_id' => $club_id,
            'club' => !empty($club_name) ? sanitize_text_field($club_name) : null,
            'position' => !empty($player->position) ? sanitize_text_field($player->position) : null,
            'shirt_number' => !empty($player->shirt_number) ? intval($player->shirt_number) : null,
            'status' => !empty($player->active_in_squad) && $player->active_in_squad == 1 ? 'active' : 'active',
        );
        
        // Define format array for insert
        $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s');
        
        if ($existing) {
            // Update existing player
            $result = $wpdb->update(
                $new_table,
                $player_data,
                array('e_id' => $e_id),
                $format,
                array('%s')
            );
            if ($result !== false) {
                $updated++;
            } else {
                if (count($error_messages) < 3) {
                    $error_messages[] = 'Update failed for ' . $e_id . ': ' . $wpdb->last_error;
                }
                $skipped++;
            }
        } else {
            // Insert new player
            $result = $wpdb->insert($new_table, $player_data, $format);
            if ($result !== false) {
                // For tables with non-auto-increment primary keys, insert_id might be 0
                // Check if the row was actually inserted by querying for it
                $verify = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT e_id FROM {$new_table} WHERE e_id = %s",
                        $e_id
                    )
                );
                if ($verify === $e_id) {
            $inserted++;
                } else {
                    if (count($error_messages) < 3) {
                        $error_messages[] = 'Insert appeared to succeed but row not found for ' . $e_id . ' (' . $canonical_name . '): ' . $wpdb->last_error;
                    }
                    $skipped++;
                }
            } else {
                // Log first few errors for debugging
                if (count($error_messages) < 3) {
                    $error_msg = $wpdb->last_error ? $wpdb->last_error : 'Unknown error (check for duplicate key or constraint violation)';
                    $error_messages[] = 'Insert failed for ' . $e_id . ' (' . $canonical_name . '): ' . $error_msg;
                }
                $skipped++;
            }
        }
    }
    
    // Add error messages to return if any
    $error_text = '';
    if (!empty($error_messages)) {
        $error_text = ' Errors: ' . implode(' | ', $error_messages);
    }
    
    return sprintf(
        '%s API import complete: %d inserted, %d updated, %d skipped.%s',
        $backup_message . ' ',
        $inserted,
        $updated,
        $skipped,
        $error_text
    );
}

/**
 * Import F players into unified table with automatic matching
 * Matches by name and club, creates PENDING entries for unmatched players
 */
function fdm_import_f_players_to_unified_table($f_players_data) {
    global $wpdb;
    
    $players_table = fdm_table_players();
    $stats_table = fdm_table_stats_f();
    $current_season = date('Y'); // Simple season year, adjust if needed
    
    if (empty($f_players_data) || !is_array($f_players_data)) {
        return 'No F player data provided.';
    }
    
// Backup before import
    $backup_message = fdm_backup_players_table();

    $matched = 0;
    $created_pending = 0;
    $updated = 0;
    $stats_saved = 0;
    
    foreach ($f_players_data as $player) {
        $f_id = isset($player['f_id']) ? sanitize_text_field($player['f_id']) : '';
        $name = isset($player['name']) ? sanitize_text_field($player['name']) : '';
        $club = isset($player['club']) ? sanitize_text_field($player['club']) : '';
        $position = isset($player['position']) ? sanitize_text_field($player['position']) : '';
        $games = isset($player['games']) ? intval($player['games']) : 0;
        $goals = isset($player['goals']) ? intval($player['goals']) : 0;
        $assists = isset($player['assists']) ? intval($player['assists']) : 0;
        
        if (empty($name) || empty($f_id)) {
            continue;
        }
        
        // Try to find existing player by F ID first (if already matched)
        $existing_by_f = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT e_id, canonical_name, club FROM {$players_table} WHERE f_id = %s",
                $f_id
            )
        );
        
        if ($existing_by_f) {
            // Already matched - just update stats
            $e_id = $existing_by_f->e_id;
            $matched++;
        } else {
            // Try to match by name and club (with fuzzy name matching and normalized club names)
            $normalized_club = fdm_normalize_club_name($club);
            $matched_player = null;
            
            // Get all unmatched E players for fuzzy matching
            $all_e_players = $wpdb->get_results(
                "SELECT e_id, canonical_name, name_variant1, club, position 
                 FROM {$players_table} 
                 WHERE f_id IS NULL 
                 AND e_id NOT LIKE 'PENDING_%'"
            );
            
            // Debug: Log first few attempts (remove after testing)
            $debug_count = 0;
            
            // Strategy 1: Try fuzzy name match + exact club match
            if (!empty($club)) {
                foreach ($all_e_players as $e_player) {
                    // Check if names match (fuzzy)
                    $name_matches = fdm_names_match($name, $e_player->canonical_name) ||
                                   (!empty($e_player->name_variant1) && fdm_names_match($name, $e_player->name_variant1));
                    
                    // Check if clubs match (exact or normalized)
                    $club_matches = false;
                    if (!empty($e_player->club)) {
                        $e_club_normalized = fdm_normalize_club_name($e_player->club);
                        $club_matches = ($e_player->club === $club || $e_club_normalized === $normalized_club);
                    }
                    
                    // Debug logging (first 5 attempts)
                    if ($debug_count < 5) {
                        error_log(sprintf(
                            "F: '%s' (club: '%s') vs E: '%s' (club: '%s') - Name match: %s, Club match: %s",
                            $name, $club ?: 'NULL',
                            $e_player->canonical_name, $e_player->club ?: 'NULL',
                            $name_matches ? 'YES' : 'NO',
                            $club_matches ? 'YES' : 'NO'
                        ));
                        $debug_count++;
                    }
                    
                    if ($name_matches && $club_matches) {
                        $matched_player = $e_player;
                        break;
                    }
                }
            }
            
            // Strategy 2: Try fuzzy name match + normalized club match (if club exists)
            if (!$matched_player && !empty($club)) {
                foreach ($all_e_players as $e_player) {
                    // Check if names match (fuzzy)
                    $name_matches = fdm_names_match($name, $e_player->canonical_name) ||
                                   (!empty($e_player->name_variant1) && fdm_names_match($name, $e_player->name_variant1));
                    
                    // Check if clubs match (normalized)
                    if ($name_matches && !empty($e_player->club)) {
                        $e_club_normalized = fdm_normalize_club_name($e_player->club);
                        if ($e_club_normalized === $normalized_club) {
                            $matched_player = $e_player;
                            break;
                        }
                    }
                }
            }
            
            // Strategy 3: Try fuzzy name-only match (works even when clubs are NULL)
            if (!$matched_player) {
                $name_matches = array();
                foreach ($all_e_players as $e_player) {
                    // Check if names match (fuzzy)
                    if (fdm_names_match($name, $e_player->canonical_name) ||
                        (!empty($e_player->name_variant1) && fdm_names_match($name, $e_player->name_variant1))) {
                        $name_matches[] = $e_player;
                    }
                }
                
                if (count($name_matches) === 1) {
                    // Only one match - safe to auto-match
                    $matched_player = $name_matches[0];
                } elseif (count($name_matches) > 1) {
                    // Multiple name matches - try to use club as tiebreaker if available
                    if (!empty($club)) {
                        foreach ($name_matches as $candidate) {
                            if (!empty($candidate->club)) {
                                $candidate_club_normalized = fdm_normalize_club_name($candidate->club);
                                if ($candidate_club_normalized === $normalized_club) {
                                    $matched_player = $candidate;
                                    break;
                                }
                            }
                        }
                    }
                    
                    // If no club match found (or no club info), try position as tiebreaker
                    if (!$matched_player && !empty($position)) {
                        foreach ($name_matches as $candidate) {
                            if (!empty($candidate->position) && 
                                strtolower(trim($candidate->position)) === strtolower(trim($position))) {
                                $matched_player = $candidate;
                                break;
                            }
                        }
                    }
                    
                    // Last resort: use first name match (better than creating PENDING)
                    if (!$matched_player) {
                        $matched_player = $name_matches[0];
                    }
                }
            }
            
            if ($matched_player) {
                // Found a match! Update with F ID and any missing data
                $e_id = $matched_player->e_id;
                $update_data = array('f_id' => $f_id);
                
                // Update position if E doesn't have it but F does
                if (empty($matched_player->position) && !empty($position)) {
                    $update_data['position'] = $position;
                }
                
                // Update club if E doesn't have it but F does
                if (empty($matched_player->club) && !empty($club)) {
                    $update_data['club'] = $club;
                }
                
                $wpdb->update(
                    $players_table,
                    $update_data,
                    array('e_id' => $e_id)
                );
                $matched++;
            } else {
                // No match found - create PENDING entry
                $e_id = 'PENDING_' . $f_id;
                $wpdb->insert(
                    $players_table,
                    array(
                        'e_id' => $e_id,
                        'f_id' => $f_id,
                        'canonical_name' => $name,
                        'club' => $club,
                        'position' => $position,
                        'status' => 'active'
                    )
                );
                $created_pending++;
            }
        }
        
        // Save stats to stats table (daily snapshot)
        $wpdb->replace(
            $stats_table,
            array(
                'e_id' => $e_id,
                'season' => $current_season,
                'games' => $games,
                'goals' => $goals,
                'assists' => $assists,
                'date_scraped' => current_time('Y-m-d')
            )
        );
        $stats_saved++;
    }
    
    return sprintf(
        '%s F import complete: %d matched, %d pending created, %d stats saved.',
        $backup_message . ' ',
        $matched,
        $created_pending,
        $stats_saved
    );
}

/**
 * Manual Player Matching Page
 * Shows unmatched E and F players for manual drag-and-drop matching
 */
function fdm_player_matching_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $players_table = fdm_table_players();
    
    // Get API players without F ID (not PENDING entries)
    $e_unmatched = $wpdb->get_results(
        "SELECT e_id, canonical_name, name_variant1, club, position, shirt_number, f_id
         FROM {$players_table}
         WHERE e_id NOT LIKE 'PENDING_%'
         AND (f_id IS NULL OR f_id = '')
         ORDER BY canonical_name ASC"
    );
    
    // Get F players (PENDING entries)
    $f_unmatched = $wpdb->get_results(
        "SELECT e_id, f_id, canonical_name, club, position
         FROM {$players_table}
         WHERE e_id LIKE 'PENDING_%'
         ORDER BY canonical_name ASC"
    );
    
    // Debug: Check total counts
    $total_all = $wpdb->get_var("SELECT COUNT(*) FROM {$players_table}");
    $total_api = $wpdb->get_var("SELECT COUNT(*) FROM {$players_table} WHERE e_id NOT LIKE 'PENDING_%'");
    $total_pending = $wpdb->get_var("SELECT COUNT(*) FROM {$players_table} WHERE e_id LIKE 'PENDING_%'");
    $total_with_f = $wpdb->get_var("SELECT COUNT(*) FROM {$players_table} WHERE e_id NOT LIKE 'PENDING_%' AND f_id IS NOT NULL AND f_id != ''");
    $total_without_f = $wpdb->get_var("SELECT COUNT(*) FROM {$players_table} WHERE e_id NOT LIKE 'PENDING_%' AND (f_id IS NULL OR f_id = '')");
    
    // Sample a few records to see what's actually in there
    $sample_records = $wpdb->get_results("SELECT e_id, canonical_name, f_id, club FROM {$players_table} LIMIT 5");
    
    ?>
    <div class="wrap">
        <h1>Player Matching</h1>
        <p>Match F players to API players by dragging from right to left.</p>
        
        <!-- Debug Info -->
        <div style="background: #f0f0f1; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;">
            <strong>Debug Info:</strong><br>
            Total ALL players in table: <?php echo $total_all; ?><br>
            Total API players (not PENDING): <?php echo $total_api; ?><br>
            Total PENDING players: <?php echo $total_pending; ?><br>
            API players WITH F ID: <?php echo $total_with_f; ?><br>
            API players WITHOUT F ID: <?php echo $total_without_f; ?><br>
            F PENDING players: <?php echo count($f_unmatched); ?><br>
            <br>
            <strong>Sample records (first 5):</strong><br>
            <?php foreach ($sample_records as $sample): ?>
                E ID: <?php echo esc_html($sample->e_id); ?> | 
                Name: <?php echo esc_html($sample->canonical_name); ?> | 
                F ID: <?php echo esc_html($sample->f_id ?: 'NULL'); ?> | 
                Club: <?php echo esc_html($sample->club ?: 'NULL'); ?><br>
            <?php endforeach; ?>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
            <!-- API Players (Left Column) -->
            <div>
                <h2>API Players (No F ID)</h2>
                <p style="color: #666;"><?php echo count($e_unmatched); ?> unmatched</p>
                <div id="e-players-list" style="border: 2px dashed #ccc; padding: 15px; min-height: 400px; background: #f9f9f9;">
                    <?php if (empty($e_unmatched)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">No unmatched API players</p>
                    <?php else: ?>
                        <?php foreach ($e_unmatched as $player): ?>
                            <div class="player-card e-player" 
                                 data-e-id="<?php echo esc_attr($player->e_id); ?>"
                                 style="background: white; padding: 10px; margin: 5px 0; border: 1px solid #ddd; cursor: default;">
                                <strong><?php echo esc_html($player->canonical_name); ?></strong>
                                <?php if (!empty($player->name_variant1)): ?>
                                    <span style="color: #666;">(<?php echo esc_html($player->name_variant1); ?>)</span>
                                <?php endif; ?>
                                <br>
                                <small style="color: #666;">
                                    <?php echo esc_html($player->club); ?> | 
                                    <?php echo esc_html($player->position); ?>
                                    <?php if (!empty($player->shirt_number)): ?>
                                        | #<?php echo esc_html($player->shirt_number); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- F Players (Right Column) -->
            <div>
                <h2>F Players (Unmatched)</h2>
                <p style="color: #666;"><?php echo count($f_unmatched); ?> pending</p>
                <div id="f-players-list" style="border: 2px dashed #ccc; padding: 15px; min-height: 400px; background: #f9f9f9;">
                    <?php if (empty($f_unmatched)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">No unmatched F players</p>
                    <?php else: ?>
                        <?php foreach ($f_unmatched as $player): ?>
                            <div class="player-card f-player draggable" 
                                 data-f-id="<?php echo esc_attr($player->f_id); ?>"
                                 data-pending-id="<?php echo esc_attr($player->e_id); ?>"
                                 style="background: #e8f4f8; padding: 10px; margin: 5px 0; border: 1px solid #0073aa; cursor: move;">
                                <strong><?php echo esc_html($player->canonical_name); ?></strong>
                                <br>
                                <small style="color: #666;">
                                    <?php echo esc_html($player->club); ?> | 
                                    <?php echo esc_html($player->position); ?>
                                </small>
                                <br>
                                <small style="color: #0073aa;">F ID: <?php echo esc_html($player->f_id); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .player-card {
            transition: all 0.2s;
        }
        .player-card:hover {
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .draggable {
            cursor: move !important;
        }
        .e-player.drag-over {
            background: #fff3cd !important;
            border: 2px solid #ffc107 !important;
        }
    </style>
        <script>
    jQuery(document).ready(function($) {
        // Make F players draggable
        $('.f-player').each(function() {
            this.addEventListener('dragstart', function(e) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', $(this).attr('data-pending-id'));
                $(this).css('opacity', '0.5');
            });
            
            this.addEventListener('dragend', function(e) {
                $(this).css('opacity', '1');
            });
            
            this.setAttribute('draggable', 'true');
        });
        
        // Make E players drop zones
        $('.e-player').each(function() {
            this.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                $(this).addClass('drag-over');
            });
            
            this.addEventListener('dragleave', function(e) {
                $(this).removeClass('drag-over');
            });
            
            this.addEventListener('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                
                var pendingId = e.dataTransfer.getData('text/plain');
                var eId = $(this).attr('data-e-id');
                var fPlayer = $('.f-player[data-pending-id="' + pendingId + '"]');
                var fId = fPlayer.attr('data-f-id');
                
                // Confirm merge
                if (!confirm('Merge ' + fPlayer.find('strong').text() + ' with ' + $(this).find('strong').text() + '?')) {
                    return;
                }
                
                // Show loading
                var $eCard = $(this);
                $eCard.css('opacity', '0.6');
                
                // Debug: Log what we're sending
                console.log('Merging players:', {
                    e_id: eId,
                    pending_id: pendingId,
                    f_id: fId
                });
                
                // Send AJAX request to merge
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'fdm_merge_players',
                        e_id: eId,
                        pending_id: pendingId,
                        f_id: fId,
                        nonce: fdm_ajax.nonce
                    },
                    success: function(response) {
                        console.log('Merge response:', response);
                        if (response.success) {
                            // Remove both cards
                            fPlayer.fadeOut(300, function() {
                                $(this).remove();
                            });
                            $eCard.fadeOut(300, function() {
                                $(this).remove();
                            });
                            
                            // Show success message with details
                            alert('Players merged successfully!\n' + (response.data.message || ''));
                            
                            // Reload page after a moment to refresh lists
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            var errorMsg = 'Error: ' + (response.data.message || 'Unknown error');
                            if (response.data.debug) {
                                errorMsg += '\nDebug: ' + JSON.stringify(response.data.debug);
                            }
                            alert(errorMsg);
                            console.error('Merge failed:', response);
                            $eCard.css('opacity', '1');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', xhr, status, error);
                        alert('Error merging players. Check browser console for details.');
                        $eCard.css('opacity', '1');
                    }
                });
            });
        });
     });
     </script>
     <?php
}

/**
 * Player Statistics Page
 * Shows match statistics linked to unified players
 */
function fdm_player_statistics_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $players_table = fdm_table_players();
    $stats_table = fdm_table_match_stats_e();
    $events_table = fdm_table_match_events_e();
    
    // Get player filter
    $player_filter = isset($_GET['player_id']) ? sanitize_text_field($_GET['player_id']) : '';
    $match_filter = isset($_GET['match_id']) ? sanitize_text_field($_GET['match_id']) : '';
    
    ?>
    <div class="wrap">
        <h1>Player Statistics (Match Data)</h1>
        
        <!-- Filters -->
        <div style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ddd;">
            <form method="get" action="">
                <input type="hidden" name="page" value="fdm-player-statistics">
                <label>Filter by Player (API ID): 
                    <input type="text" name="player_id" value="<?php echo esc_attr($player_filter); ?>" placeholder="e.g., 111114">
                </label>
                <label style="margin-left: 20px;">Filter by Match ID: 
                    <input type="text" name="match_id" value="<?php echo esc_attr($match_filter); ?>" placeholder="e.g., 401131040">
                </label>
                <input type="submit" class="button" value="Filter">
                <a href="?page=fdm-player-statistics" class="button">Clear</a>
            </form>
        </div>
        
        <?php
        // Show aggregated stats if player filter is set
        if (!empty($player_filter)) {
            $player = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$players_table} WHERE e_id = %s",
                    $player_filter
                )
            );
            
            if ($player) {
                echo '<h2>Player: ' . esc_html($player->canonical_name) . '</h2>';
                
                // Get aggregated stats
                $aggregated = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT 
                            COUNT(*) as matches_played,
                            SUM(minutes_played) as total_minutes,
                            SUM(goals) as total_goals,
                            SUM(assists) as total_assists,
                            SUM(shots) as total_shots,
                            SUM(shots_on_target) as total_shots_on_target,
                            SUM(passes) as total_passes,
                            SUM(passes_completed) as total_passes_completed,
                            SUM(tackles) as total_tackles,
                            SUM(interceptions) as total_interceptions,
                            SUM(yellow_cards) as total_yellow_cards,
                            SUM(red_cards) as total_red_cards
                         FROM {$stats_table}
                         WHERE e_player_id = %s",
                        $player_filter
                    )
                );
                
                if ($aggregated && $aggregated->matches_played > 0) {
                    echo '<h3>Aggregated Statistics</h3>';
                    echo '<table class="widefat fixed striped">';
                    echo '<tr><th>Stat</th><th>Total</th><th>Per Match</th></tr>';
                    echo '<tr><td>Matches Played</td><td>' . $aggregated->matches_played . '</td><td>-</td></tr>';
                    echo '<tr><td>Minutes</td><td>' . ($aggregated->total_minutes ?: 0) . '</td><td>' . round(($aggregated->total_minutes ?: 0) / $aggregated->matches_played, 1) . '</td></tr>';
                    echo '<tr><td>Goals</td><td>' . ($aggregated->total_goals ?: 0) . '</td><td>' . round(($aggregated->total_goals ?: 0) / $aggregated->matches_played, 2) . '</td></tr>';
                    echo '<tr><td>Assists</td><td>' . ($aggregated->total_assists ?: 0) . '</td><td>' . round(($aggregated->total_assists ?: 0) / $aggregated->matches_played, 2) . '</td></tr>';
                    echo '<tr><td>Shots</td><td>' . ($aggregated->total_shots ?: 0) . '</td><td>' . round(($aggregated->total_shots ?: 0) / $aggregated->matches_played, 1) . '</td></tr>';
                    echo '<tr><td>Shots on Target</td><td>' . ($aggregated->total_shots_on_target ?: 0) . '</td><td>' . round(($aggregated->total_shots_on_target ?: 0) / $aggregated->matches_played, 1) . '</td></tr>';
                    echo '<tr><td>Passes</td><td>' . ($aggregated->total_passes ?: 0) . '</td><td>' . round(($aggregated->total_passes ?: 0) / $aggregated->matches_played, 0) . '</td></tr>';
                    if ($aggregated->total_passes > 0) {
                        $pass_accuracy = round(($aggregated->total_passes_completed ?: 0) / $aggregated->total_passes * 100, 1);
                        echo '<tr><td>Pass Accuracy</td><td>' . $pass_accuracy . '%</td><td>-</td></tr>';
                    }
                    echo '<tr><td>Tackles</td><td>' . ($aggregated->total_tackles ?: 0) . '</td><td>' . round(($aggregated->total_tackles ?: 0) / $aggregated->matches_played, 1) . '</td></tr>';
                    echo '<tr><td>Interceptions</td><td>' . ($aggregated->total_interceptions ?: 0) . '</td><td>' . round(($aggregated->total_interceptions ?: 0) / $aggregated->matches_played, 1) . '</td></tr>';
                    echo '<tr><td>Yellow Cards</td><td>' . ($aggregated->total_yellow_cards ?: 0) . '</td><td>-</td></tr>';
                    echo '<tr><td>Red Cards</td><td>' . ($aggregated->total_red_cards ?: 0) . '</td><td>-</td></tr>';
                    echo '</table>';
                }
                
                // Get match-by-match stats
                $match_stats_query = "SELECT * FROM {$stats_table} WHERE e_player_id = %s";
                if (!empty($match_filter)) {
                    $match_stats_query .= $wpdb->prepare(" AND e_match_id = %s", $match_filter);
                }
                $match_stats_query .= " ORDER BY date_scraped DESC LIMIT 50";
                
                $match_stats = $wpdb->get_results(
                    $wpdb->prepare($match_stats_query, $player_filter)
                );
                
                if ($match_stats) {
                    echo '<h3>Recent Match Statistics (Last 50)</h3>';
                    echo '<table class="widefat fixed striped">';
                    echo '<thead><tr><th>Match ID</th><th>Position</th><th>Minutes</th><th>Goals</th><th>Assists</th><th>Shots</th><th>Passes</th><th>Tackles</th><th>Cards</th><th>Date</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($match_stats as $stat) {
                        $cards = '';
                        if ($stat->yellow_cards > 0) $cards .= $stat->yellow_cards . 'Y';
                        if ($stat->red_cards > 0) $cards .= ($cards ? ', ' : '') . $stat->red_cards . 'R';
                        echo '<tr>';
                        echo '<td>' . esc_html($stat->e_match_id) . '</td>';
                        echo '<td>' . esc_html($stat->position ?: '-') . '</td>';
                        echo '<td>' . ($stat->minutes_played ?: '-') . '</td>';
                        echo '<td>' . ($stat->goals ?: '-') . '</td>';
                        echo '<td>' . ($stat->assists ?: '-') . '</td>';
                        echo '<td>' . ($stat->shots ?: '-') . '</td>';
                        echo '<td>' . ($stat->passes ?: '-') . '</td>';
                        echo '<td>' . ($stat->tackles ?: '-') . '</td>';
                        echo '<td>' . ($cards ?: '-') . '</td>';
                        echo '<td>' . esc_html($stat->date_scraped) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
                
                // Get match events for this player
                $events = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$events_table} 
                         WHERE e_player_id = %s 
                         ORDER BY period, minute, second 
                         LIMIT 100",
                        $player_filter
                    )
                );
                
                if ($events) {
                    echo '<h3>Match Events (Last 100)</h3>';
                    echo '<table class="widefat fixed striped">';
                    echo '<thead><tr><th>Match ID</th><th>Event</th><th>Time</th><th>Description</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($events as $event) {
                        $time = '';
                        if ($event->minute !== null) {
                            $time = $event->minute . "'";
                            if ($event->second > 0) {
                                $time .= ' ' . $event->second . '"';
                            }
                        }
                        echo '<tr>';
                        echo '<td>' . esc_html($event->e_match_id) . '</td>';
                        echo '<td>' . esc_html($event->event_type) . '</td>';
                        echo '<td>' . ($time ?: '-') . '</td>';
                        echo '<td>' . esc_html($event->description ?: '-') . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
            } else {
                echo '<p>Player not found in unified database.</p>';
            }
        } else {
            // Show all players with stats
            $players_with_stats = $wpdb->get_results(
                "SELECT p.e_id, p.canonical_name, p.club, p.position,
                        COUNT(s.id) as match_count,
                        SUM(s.goals) as total_goals,
                        SUM(s.assists) as total_assists
                 FROM {$players_table} p
                 INNER JOIN {$stats_table} s ON p.e_id = s.e_player_id
                 WHERE p.e_id NOT LIKE 'PENDING_%'
                 GROUP BY p.e_id
                 ORDER BY total_goals DESC, total_assists DESC
                 LIMIT 100"
            );
            
            if ($players_with_stats) {
                echo '<h2>Top Players by Goals (with Match Statistics)</h2>';
                echo '<table class="widefat fixed striped">';
                echo '<thead><tr><th>Player</th><th>Club</th><th>Position</th><th>Matches</th><th>Goals</th><th>Assists</th><th>Actions</th></tr></thead>';
                echo '<tbody>';
                foreach ($players_with_stats as $player) {
                    echo '<tr>';
                    echo '<td>' . esc_html($player->canonical_name) . '</td>';
                    echo '<td>' . esc_html($player->club ?: '-') . '</td>';
                    echo '<td>' . esc_html($player->position ?: '-') . '</td>';
                    echo '<td>' . $player->match_count . '</td>';
                    echo '<td>' . ($player->total_goals ?: 0) . '</td>';
                    echo '<td>' . ($player->total_assists ?: 0) . '</td>';
                    echo '<td><a href="?page=fdm-player-statistics&player_id=' . esc_attr($player->e_id) . '" class="button button-small">View Stats</a></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>No match statistics found. Import boxscore data first.</p>';
            }
        }
        ?>
    </div>
    <?php
}

/**
 * Enqueue frontend scripts for live scores widget
 */
function fdm_enqueue_frontend_scripts() {
    // Only load on pages that might have the shortcode
    if ( is_singular() || is_home() || is_front_page() ) {
        wp_enqueue_script( 'jquery' );
        wp_localize_script( 'jquery', 'fdm_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'fdm_frontend_live_scores' )
        ) );
        
        // Inline CSS for the widget
        wp_add_inline_style( 'wp-block-library', '
            .fdm-live-scores-widget {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 8px;
                margin: 10px 0;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
            .fdm-live-scores-widget.hidden {
                display: none;
            }
            .fdm-live-scores-widget .widget-header {
                display: none;
            }
            .fdm-live-scores-widget .match-item {
                padding: 6px 8px;
                margin: 5px 0;
                background: #f9f9f9;
                border-radius: 3px;
                border-left: 3px solid #0073aa;
            }
            .fdm-live-scores-widget .match-item.live {
                border-left-color: #d63638;
                background: #fff3cd;
            }
            .fdm-live-scores-widget .match-teams {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 3px;
            }
            .fdm-live-scores-widget .team {
                flex: 1;
                font-weight: 600;
                font-size: 16px;
            }
            .fdm-live-scores-widget .team.liverpool {
                color: #c8102e;
            }
            .fdm-live-scores-widget .score {
                font-size: 20px;
                font-weight: bold;
                margin: 0 12px;
                min-width: 45px;
                text-align: center;
            }
            .fdm-live-scores-widget .match-info {
                display: flex;
                justify-content: space-between;
                font-size: 10px;
                color: #666;
                margin-top: 3px;
            }
            .fdm-live-scores-widget .status {
                font-weight: 600;
                color: #333;
            }
            .fdm-live-scores-widget .status.live {
                color: #d63638;
            }
            .fdm-live-scores-widget .match-time {
                color: #333;
            }
            .fdm-live-scores-widget .loading {
                text-align: center;
                padding: 15px;
                color: #666;
            }
            .fdm-live-scores-widget .error {
                color: #d63638;
                padding: 8px;
                background: #ffeaea;
                border-radius: 3px;
                font-size: 12px;
            }
            .fdm-live-scores-widget .no-matches {
                text-align: center;
                padding: 15px;
                color: #999;
            }
            .fdm-live-scores-widget .match-events {
                margin-top: 5px;
                padding-top: 5px;
                border-top: 1px solid #e0e0e0;
                font-size: 10px;
            }
            .fdm-live-scores-widget .team-events {
                font-size: 11px;
            }
            .fdm-live-scores-widget .scorers,
            .fdm-live-scores-widget .cards {
                margin: 3px 0;
                color: #555;
                line-height: 1.4;
            }
            .fdm-live-scores-widget .scorers strong,
            .fdm-live-scores-widget .cards strong {
                color: #333;
            }
            
            /* Expandable events on hover */
            .fdm-live-scores-widget .match-item {
                position: relative;
                cursor: pointer;
            }
            .fdm-live-scores-widget .match-events-expandable {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #fff;
                border: 1px solid #ddd;
                border-top: none;
                border-radius: 0 0 4px 4px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
                z-index: 10000;
                overflow: hidden;
                max-height: 0;
                opacity: 0;
                transition: max-height 0.3s ease-out, opacity 0.3s ease-out, padding 0.3s ease-out;
                padding: 0 15px;
            }
            .fdm-live-scores-widget .match-item:hover .match-events-expandable,
            .fdm-live-scores-widget .match-item.expanded .match-events-expandable {
                max-height: 2000px;
                opacity: 1;
                padding: 12px 15px;
            }
            .fdm-live-scores-widget .match-events-content {
                padding: 0;
            }
            .fdm-live-scores-widget .event-group {
                margin-bottom: 10px;
            }
            .fdm-live-scores-widget .event-group:last-child {
                margin-bottom: 0;
            }
            .fdm-live-scores-widget .event-group.goals {
                color: #2c5530;
            }
            .fdm-live-scores-widget .event-group.yellow-cards {
                color: #d4a017;
            }
            .fdm-live-scores-widget .event-group.red-cards {
                color: #c8102e;
            }
            .fdm-live-scores-widget .event-item {
                font-size: 11px;
                line-height: 1.6;
                padding: 2px 0;
            }
            .fdm-live-scores-widget .team-events {
                font-size: 11px;
            }
        ' );
    }
}

/**
 * Live Scores Shortcode
 * Usage: [fdm_live_scores team="Liverpool" leagues="eng.1,uefa.champions" auto_refresh="60" show_all="no" start_time="14:00" end_time="18:00" days="0,6"]
 */
function fdm_live_scores_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'team' => '', // Filter by team name (e.g., "Liverpool")
        'team_id' => '', // Filter by team ID (e.g., "357" for Leeds)
        'match_id' => '', // Filter by specific match ID (e.g., "740709")
        'leagues' => 'eng.1', // Comma-separated league codes
        'auto_refresh' => '0', // Auto-refresh interval in seconds (0 = disabled)
        'show_all' => 'no', // Show all matches or just filtered team
        'start_time' => '', // Start showing at this time (HH:MM format, 24h)
        'end_time' => '', // Stop showing at this time (HH:MM format, 24h)
        'days' => '', // Comma-separated days to show (0=Sunday, 1=Monday, etc.) or "all"
    ), $atts, 'fdm_live_scores' );
    
    // Check time-based visibility
    $current_time = current_time( 'H:i' );
    $current_day = (int) current_time( 'w' ); // 0 = Sunday, 6 = Saturday
    
    // Check start/end time
    if ( ! empty( $atts['start_time'] ) && ! empty( $atts['end_time'] ) ) {
        if ( $current_time < $atts['start_time'] || $current_time > $atts['end_time'] ) {
            return ''; // Widget is hidden outside time window
        }
    }
    
    // Check days
    if ( ! empty( $atts['days'] ) && $atts['days'] !== 'all' ) {
        $allowed_days = array_map( 'trim', explode( ',', $atts['days'] ) );
        $allowed_days = array_map( 'intval', $allowed_days );
        if ( ! in_array( $current_day, $allowed_days, true ) ) {
            return ''; // Widget is hidden on this day
        }
    }
    
    $team_filter = ! empty( $atts['team'] ) ? sanitize_text_field( $atts['team'] ) : '';
    $match_id_filter = ! empty( $atts['match_id'] ) ? sanitize_text_field( $atts['match_id'] ) : '';
    $leagues = ! empty( $atts['leagues'] ) ? array_map( 'trim', explode( ',', $atts['leagues'] ) ) : array( 'eng.1' );
    $auto_refresh = (int) $atts['auto_refresh'];
    $show_all = $atts['show_all'] === 'yes' || $atts['show_all'] === 'true';
    
    $widget_id = 'fdm-live-scores-' . uniqid();
    
    ob_start();
    ?>
    <div id="<?php echo esc_attr( $widget_id ); ?>" class="fdm-live-scores-widget" 
         data-team="<?php echo esc_attr( $team_filter ); ?>"
         data-team-id="<?php echo esc_attr( ! empty( $atts['team_id'] ) ? sanitize_text_field( $atts['team_id'] ) : '' ); ?>"
         data-match-id="<?php echo esc_attr( $match_id_filter ); ?>"
         data-leagues="<?php echo esc_attr( implode( ',', $leagues ) ); ?>"
         data-show-all="<?php echo $show_all ? 'yes' : 'no'; ?>"
         data-auto-refresh="<?php echo esc_attr( $auto_refresh ); ?>">
        <div class="widget-content">
            <div class="loading">Loading live scores...</div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var widgetId = '<?php echo esc_js( $widget_id ); ?>';
        var autoRefresh = <?php echo (int) $auto_refresh; ?>;
        var timeIncrementInterval = null;
        
        // Initial load
        fdmLoadLiveScores(widgetId, true);
        
        // Auto-refresh if enabled
        if (autoRefresh > 0) {
            setInterval(function() {
                fdmLoadLiveScores(widgetId, false);
            }, autoRefresh * 1000);
            
            // Increment match times every minute for live matches
            timeIncrementInterval = setInterval(function() {
                fdmIncrementMatchTimes(widgetId);
            }, 60000); // Every 60 seconds
        }
    });
    
    function fdmLoadLiveScores(widgetId, showLoading) {
        // Default showLoading to true if not provided (for backward compatibility)
        if (typeof showLoading === 'undefined') {
            showLoading = true;
        }
        
        var $widget = jQuery('#' + widgetId);
        var $content = $widget.find('.widget-content');
        
        // Only show loading on initial load
        if (showLoading) {
            $content.html('<div class="loading">Loading live scores...</div>');
        }
        
        jQuery.ajax({
            url: fdm_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'fdm_get_live_scores_frontend',
                team: $widget.data('team'),
                team_id: $widget.data('team-id'),
                match_id: $widget.data('match-id'),
                leagues: $widget.data('leagues'),
                show_all: $widget.data('show-all'),
                nonce: fdm_ajax.nonce
            },
            beforeSend: function() {
                // Debug logging
                console.log('AJAX request data:', {
                    team: $widget.data('team'),
                    match_id: $widget.data('match-id'),
                    leagues: $widget.data('leagues'),
                    show_all: $widget.data('show-all')
                });
            },
            success: function(response) {
                // Debug logging
                console.log('AJAX response:', {
                    success: response.success,
                    match_count: response.data ? response.data.count : 0,
                    matches: response.data ? response.data.matches : null,
                    debug: response.data ? response.data.debug : null
                });
                
                // If debug info exists, log it prominently
                if (response.data && response.data.debug) {
                    console.warn('Match ID lookup failed:', response.data.debug);
                }
                
                if (response.success && response.data && response.data.matches) {
                    // Debug: log first match to see what status/time we're getting
                    if (response.data.matches.length > 0) {
                        console.log('First match data:', {
                            match_id: response.data.matches[0].match_id,
                            status: response.data.matches[0].status,
                            time: response.data.matches[0].time,
                            home_team: response.data.matches[0].home_team,
                            away_team: response.data.matches[0].away_team
                        });
                    } else {
                        console.log('No matches in response. Filter used:', {
                            match_id: $widget.data('match-id'),
                            team: $widget.data('team')
                        });
                    }
                    // Always update with new data, even during auto-refresh
                    fdmRenderLiveScores($content, response.data.matches);
                } else {
                    // Only show error on initial load
                    if (showLoading) {
                        $content.html('<div class="error">' + (response.data ? response.data.message : 'No matches available') + '</div>');
                    }
                    // During auto-refresh, silently ignore errors and keep existing content
                }
            },
            error: function(xhr, status, error) {
                // Only show errors on initial load
                if (showLoading) {
                    console.error('Live scores AJAX error:', xhr, status, error);
                    var errorMsg = 'Error loading live scores. ';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg += xhr.responseJSON.data.message;
                    } else {
                        errorMsg += 'Please check browser console for details.';
                    }
                    $content.html('<div class="error">' + errorMsg + '</div>');
                }
                // During auto-refresh, silently ignore errors and keep existing content
            }
        });
    }
    
    function fdmRefreshLiveScores(widgetId) {
        fdmLoadLiveScores(widgetId, true);
    }
    
    function fdmIncrementMatchTimes(widgetId) {
        var $widget = jQuery('#' + widgetId);
        $widget.find('.status[data-is-live="1"]').each(function() {
            var $status = jQuery(this);
            var $timeSpan = $status.find('.match-time');
            var isFinished = $status.attr('data-is-finished') === '1';
            var isHalfTime = $status.attr('data-is-half-time') === '1';
            var matchStatus = $status.attr('data-status') || '';
            var currentDisplay = $status.attr('data-display-text') || $timeSpan.text();
            var currentTime = $status.attr('data-match-time') || currentDisplay;
            
            // Check if match is finished - show FT and stop incrementing
            if (isFinished || (matchStatus && (matchStatus === 'Finished' || matchStatus === 'Full Time' || matchStatus === 'FT' || 
                matchStatus.toUpperCase().indexOf('FULL TIME') !== -1))) {
                if (currentDisplay.toUpperCase() !== 'FT') {
                    $timeSpan.text('FT');
                    $status.attr('data-display-text', 'FT');
                    $status.attr('data-is-finished', '1');
                    $status.attr('data-is-live', '0'); // Mark as not live anymore
                }
                return; // Don't increment finished matches
            }
            
            // Check if status from API indicates half-time (be specific - only exact matches)
            if (matchStatus && (matchStatus === 'Halftime' || matchStatus === 'HT')) {
                // Update display to HT if it's not already
                if (currentDisplay.toUpperCase() !== 'HT') {
                    $timeSpan.text('HT');
                    $status.attr('data-display-text', 'HT');
                    $status.attr('data-is-half-time', '1');
                }
                return; // Don't increment during half-time
            }
            
            // Skip if it's HT (half-time) - don't increment
            if (isHalfTime || (currentDisplay && currentDisplay.toUpperCase() === 'HT')) {
                return;
            }
            
            if (currentTime && currentTime.trim() !== '') {
                // Check if it's injury time format (e.g., "45+6", "90+3")
                var injuryTimeMatch = currentTime.match(/^(\d+)\+(\d+)/);
                if (injuryTimeMatch) {
                    var baseMinute = parseInt(injuryTimeMatch[1], 10);
                    var injuryMinutes = parseInt(injuryTimeMatch[2], 10);
                    
                    // If we're at 45+something, don't increment past 45+10 (reasonable max injury time)
                    if (baseMinute === 45 && injuryMinutes < 10) {
                        injuryMinutes += 1;
                        var newTime = baseMinute + '+' + injuryMinutes + "'";
                        $timeSpan.text(newTime);
                        $status.attr('data-match-time', newTime);
                        $status.attr('data-display-text', newTime);
                    } else if (baseMinute === 45 && injuryMinutes >= 10) {
                        // At 45+10 or more, switch to HT
                        $timeSpan.text('HT');
                        $status.attr('data-display-text', 'HT');
                        $status.attr('data-is-half-time', '1');
                    } else if (baseMinute > 45 && baseMinute < 90) {
                        // Second half, continue incrementing
                        baseMinute += 1;
                        var newTime = baseMinute + "'";
                        $timeSpan.text(newTime);
                        $status.attr('data-match-time', newTime);
                        $status.attr('data-display-text', newTime);
                    } else if (baseMinute >= 90) {
                        // Full time or extra time, handle similarly
                        if (injuryMinutes < 10) {
                            injuryMinutes += 1;
                            var newTime = baseMinute + '+' + injuryMinutes + "'";
                            $timeSpan.text(newTime);
                            $status.attr('data-match-time', newTime);
                            $status.attr('data-display-text', newTime);
                        }
                    }
                } else {
                    // Regular time format (e.g., "45'", "67'")
                    var timeMatch = currentTime.match(/(\d+)/);
                    if (timeMatch) {
                        var minute = parseInt(timeMatch[1], 10);
                        // If we hit 45', check if we should switch to HT on next increment
                        if (minute === 45) {
                            // Next increment should show HT (half-time)
                            $timeSpan.text('HT');
                            $status.attr('data-display-text', 'HT');
                            $status.attr('data-is-half-time', '1');
                        } else if (minute >= 1 && minute < 45) {
                            // First half, continue incrementing
                            minute += 1;
                            var newTime = minute + "'";
                            $timeSpan.text(newTime);
                            $status.attr('data-match-time', newTime);
                            $status.attr('data-display-text', newTime);
                        } else if (minute > 45 && minute < 120) {
                            // Second half, continue incrementing
                            minute += 1;
                            var newTime = minute + "'";
                            $timeSpan.text(newTime);
                            $status.attr('data-match-time', newTime);
                            $status.attr('data-display-text', newTime);
                        }
                    }
                }
            }
        });
    }
    
    function fdmRenderLiveScores($container, matches) {
        if (matches.length === 0) {
            $container.html('<div class="no-matches">No live matches at the moment.</div>');
            return;
        }
        
        var html = '';
        matches.forEach(function(match, index) {
            var isFinished = match.status === 'Finished' || match.status === 'Full Time' || match.status === 'FT' || 
                            (match.status && match.status.toUpperCase().indexOf('FULL TIME') !== -1);
            var isLive = (match.status === 'In Progress' || match.status === 'LIVE' || match.status === 'Halftime') && !isFinished;
            var liveClass = isLive ? ' live' : '';
            var statusClass = isLive ? ' live' : '';
            var matchId = 'match-' + index + '-' + (match.match_id || match.home_team + '-' + match.away_team).replace(/\s+/g, '-').toLowerCase();
            
            html += '<div class="match-item' + liveClass + '" data-match-id="' + matchId + '">';
            html += '<div class="match-teams">';
            html += '<div class="team' + (match.home_team.toLowerCase().indexOf('liverpool') !== -1 ? ' liverpool' : '') + '">' + match.home_team + '</div>';
            html += '<div class="score">' + (match.home_score !== null && match.away_score !== null ? match.home_score + ' - ' + match.away_score : '-') + '</div>';
            html += '<div class="team' + (match.away_team.toLowerCase().indexOf('liverpool') !== -1 ? ' liverpool' : '') + '">' + match.away_team + '</div>';
            html += '</div>';
            html += '<div class="match-info">';
            html += '<span class="league">' + match.league + '</span>';
            
            // Determine what to display: time, HT, FT, or status
            var displayText = '';
            var isHalfTime = false;
            var isFinished = match.status === 'Finished' || match.status === 'Full Time' || match.status === 'FT' || 
                            (match.status && match.status.toUpperCase().indexOf('FULL TIME') !== -1);
            
            // Check if match is finished - show FT
            if (isFinished) {
                displayText = 'FT';
            } else if (match.status && (match.status === 'Halftime' || match.status === 'HT')) {
                // Check if status indicates half-time
                isHalfTime = true;
                displayText = 'HT';
            } else if (match.time) {
                // Check if time string indicates half-time
                var timeStr = match.time.toString().toUpperCase();
                if (timeStr === 'HT' || timeStr === 'HALF TIME' || timeStr === 'HALFTIME') {
                    isHalfTime = true;
                    displayText = 'HT';
                } else {
                    // Check if it's 45+ (injury time at end of first half)
                    var timeMatch = timeStr.match(/^45\+?\d*/);
                    if (timeMatch) {
                        // Only show HT if status explicitly says "Halftime" or "HT"
                        // Otherwise, show the actual time (45+6, etc.)
                        if (match.status && (match.status === 'Halftime' || match.status === 'HT')) {
                            isHalfTime = true;
                            displayText = 'HT';
                        } else {
                            // Still in first half injury time, show the time
                            displayText = match.time;
                        }
                    } else {
                        displayText = match.time;
                    }
                }
            } else if (match.status && isLive) {
                // For live matches without time, show status as fallback
                displayText = match.status;
            } else if (match.status) {
                displayText = match.status;
            }
            
            html += '<span class="status' + statusClass + '" data-match-time="' + (match.time || '') + '" data-is-live="' + (isLive ? '1' : '0') + '" data-is-finished="' + (isFinished ? '1' : '0') + '" data-display-text="' + displayText + '" data-is-half-time="' + (isHalfTime ? '1' : '0') + '" data-status="' + (match.status || '') + '">';
            html += '<span class="match-time">' + displayText + '</span>';
            html += '</span>';
            html += '</div>';
            
            // Group scorers and cards by team
            var homeScorers = [];
            var awayScorers = [];
            var homeCards = [];
            var awayCards = [];
            
            // Process scorers
            if (match.scorers && Array.isArray(match.scorers) && match.scorers.length > 0) {
                match.scorers.forEach(function(scorer) {
                    var isHome = scorer.team === 'home' || scorer.team === true;
                    var minute = scorer.minute || scorer.min || '';
                    var player = scorer.player || scorer.name || '';
                    
                    if (player && minute) {
                        if (isHome) {
                            homeScorers.push({player: player, minute: minute});
                        } else {
                            awayScorers.push({player: player, minute: minute});
                        }
                    }
                });
            }
            
            // Process cards
            if (match.cards && Array.isArray(match.cards) && match.cards.length > 0) {
                match.cards.forEach(function(card) {
                    var isHome = card.team === 'home' || card.team === true;
                    var minute = card.minute || card.min || '';
                    var player = card.player || card.name || '';
                    var cardType = card.type || 'yellow';
                    
                    if (player && minute) {
                        if (isHome) {
                            homeCards.push({player: player, minute: minute, type: cardType});
                        } else {
                            awayCards.push({player: player, minute: minute, type: cardType});
                        }
                    }
                });
            }
            
            // Create expandable events section (hidden by default, shown on hover)
            if (homeScorers.length > 0 || homeCards.length > 0 || awayScorers.length > 0 || awayCards.length > 0) {
                // Sort events by type (Goals, Yellows, Reds) then chronologically
                homeScorers.sort(function(a, b) { return parseInt(a.minute) - parseInt(b.minute); });
                awayScorers.sort(function(a, b) { return parseInt(a.minute) - parseInt(b.minute); });
                homeCards.sort(function(a, b) { 
                    // Sort by type first (yellow before red), then by minute
                    if (a.type !== b.type) {
                        return a.type === 'red' ? 1 : -1;
                    }
                    return parseInt(a.minute) - parseInt(b.minute);
                });
                awayCards.sort(function(a, b) { 
                    if (a.type !== b.type) {
                        return a.type === 'red' ? 1 : -1;
                    }
                    return parseInt(a.minute) - parseInt(b.minute);
                });
                
                html += '<div class="match-events-expandable">';
                html += '<div class="match-events-content">';
                html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; padding: 10px 0;">';
                
                // Home team events - grouped by type
                html += '<div class="team-events">';
                
                // Goals first
                if (homeScorers.length > 0) {
                    html += '<div class="event-group goals">';
                    homeScorers.forEach(function(scorer) {
                        html += '<div class="event-item">⚽ ' + scorer.player + ' ' + scorer.minute + '\'</div>';
                    });
                    html += '</div>';
                }
                
                // Yellow cards
                var homeYellows = homeCards.filter(function(card) { return card.type === 'yellow'; });
                if (homeYellows.length > 0) {
                    html += '<div class="event-group yellow-cards">';
                    homeYellows.forEach(function(card) {
                        html += '<div class="event-item">🟨 ' + card.player + ' ' + card.minute + '\'</div>';
                    });
                    html += '</div>';
                }
                
                // Red cards
                var homeReds = homeCards.filter(function(card) { return card.type === 'red'; });
                if (homeReds.length > 0) {
                    html += '<div class="event-group red-cards">';
                    homeReds.forEach(function(card) {
                        html += '<div class="event-item">🟥 ' + card.player + ' ' + card.minute + '\'</div>';
                    });
                    html += '</div>';
                }
                
                html += '</div>';
                
                // Away team events - grouped by type
                html += '<div class="team-events">';
                
                // Goals first
                if (awayScorers.length > 0) {
                    html += '<div class="event-group goals">';
                    awayScorers.forEach(function(scorer) {
                        html += '<div class="event-item">⚽ ' + scorer.player + ' ' + scorer.minute + '\'</div>';
                    });
                    html += '</div>';
                }
                
                // Yellow cards
                var awayYellows = awayCards.filter(function(card) { return card.type === 'yellow'; });
                if (awayYellows.length > 0) {
                    html += '<div class="event-group yellow-cards">';
                    awayYellows.forEach(function(card) {
                        html += '<div class="event-item">🟨 ' + card.player + ' ' + card.minute + '\'</div>';
                    });
                    html += '</div>';
                }
                
                // Red cards
                var awayReds = awayCards.filter(function(card) { return card.type === 'red'; });
                if (awayReds.length > 0) {
                    html += '<div class="event-group red-cards">';
                    awayReds.forEach(function(card) {
                        html += '<div class="event-item">🟥 ' + card.player + ' ' + card.minute + '\'</div>';
                    });
                    html += '</div>';
                }
                
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }
            
            html += '</div>';
        });
        
        $container.html(html);
        
        // Add hover and touch handlers for expandable events
        $container.find('.match-item').each(function() {
            var $matchItem = $(this);
            var $expandable = $matchItem.find('.match-events-expandable');
            
            if ($expandable.length === 0) {
                return; // No events to expand
            }
            
            var isExpanded = false;
            var hoverTimeout = null;
            
            // Mouse enter - show events
            $matchItem.on('mouseenter', function() {
                clearTimeout(hoverTimeout);
                $matchItem.addClass('expanded');
                isExpanded = true;
            });
            
            // Mouse leave - hide events
            $matchItem.on('mouseleave', function() {
                hoverTimeout = setTimeout(function() {
                    $matchItem.removeClass('expanded');
                    isExpanded = false;
                }, 100); // Small delay to prevent flickering
            });
            
            // Touch devices - toggle on tap
            var touchStartTime = 0;
            $matchItem.on('touchstart', function(e) {
                touchStartTime = Date.now();
            });
            
            $matchItem.on('touchend', function(e) {
                var touchDuration = Date.now() - touchStartTime;
                // Only toggle if it was a quick tap (not a scroll)
                if (touchDuration < 300) {
                    e.preventDefault();
                    if (isExpanded) {
                        $matchItem.removeClass('expanded');
                        isExpanded = false;
                    } else {
                        $matchItem.addClass('expanded');
                        isExpanded = true;
                    }
                }
            });
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

/**
 * AJAX handler for frontend live scores
 */
function fdm_get_live_scores_frontend_callback() {
    check_ajax_referer( 'fdm_frontend_live_scores', 'nonce' );
    
    $team_filter = isset( $_POST['team'] ) ? sanitize_text_field( $_POST['team'] ) : '';
    $team_id_filter = isset( $_POST['team_id'] ) ? sanitize_text_field( $_POST['team_id'] ) : ''; // New: filter by team ID
    $match_id_filter = isset( $_POST['match_id'] ) ? sanitize_text_field( $_POST['match_id'] ) : '';
    $leagues_string = isset( $_POST['leagues'] ) ? sanitize_text_field( $_POST['leagues'] ) : 'eng.1';
    $show_all = isset( $_POST['show_all'] ) && ( $_POST['show_all'] === 'yes' || $_POST['show_all'] === 'true' );
    
    $leagues = array_map( 'trim', explode( ',', $leagues_string ) );
    
    $source = get_option( 'fdm_live_scores_source', 'f' );
    $matches = array();
    
    // Initialize debug variables
    $all_possible_leagues = $leagues; // Default to configured leagues
    $scoreboard_matches_for_debug = array(); // Store scoreboard matches for debug info
    
    if ( $source === 'e' ) {
        // Use new datasource that reads from footyforums_data database
        require_once FDM_PLUGIN_DIR . 'includes/e_datasource_v2.php';
        
        // Get matches from database
        $matches = FDM_E_Datasource_V2::get_live_scores_from_db( $leagues, $team_filter, $match_id_filter );
        
        // Format matches for widget compatibility
        $formatted_matches = array();
        foreach ( $matches as $match ) {
            $formatted_match = array(
                'id' => $match['id'],
                'home_team' => $match['home_team'],
                'away_team' => $match['away_team'],
                'home_score' => $match['home_score'],
                'away_score' => $match['away_score'],
                'status' => $match['status'],
                'competition' => isset( $match['competition'] ) ? $match['competition'] : '',
            );
            
            if ( isset( $match['venue'] ) ) {
                $formatted_match['venue'] = $match['venue'];
            }
            
            $formatted_matches[] = $formatted_match;
        }
        
        wp_send_json_success( array(
            'live_matches' => $formatted_matches,
            'source' => 'e',
            'count' => count( $formatted_matches )
        ) );
        return;
        
        // Old code below - keeping for reference but not used
        require_once FDM_PLUGIN_DIR . 'includes/e_datasource.php';
        
        if ( ! class_exists( 'FDM_E_Datasource' ) ) {
            class FDM_E_Datasource {
                use KopThis_Prediction_League_Datasource;
            }
        }
        
        // If match_id is specified, try to fetch directly from summary endpoint first
        if ( ! empty( $match_id_filter ) ) {
            // Try configured leagues first, then fallback to all common leagues
            $all_possible_leagues = array_unique( array_merge( $leagues, array( 'eng.1', 'eng.fa', 'eng.league_cup', 'uefa.champions', 'uefa.europa' ) ) );
            
            // Also try without league code (some ESPN endpoints work with just the match ID)
            $all_possible_leagues[] = ''; // Empty string to try without league
            
            // Try each league to find the match
            foreach ( $all_possible_leagues as $league_code ) {
                // Skip empty league code for now (we'll try it as a last resort with a different URL format)
                if ( empty( $league_code ) ) {
                    // Try a generic endpoint without league code
                    $summary_url = 'http://site.api.espn.com/apis/site/v2/sports/soccer/summary?event=' . rawurlencode( $match_id_filter );
                    $boxscore_url = 'http://site.api.espn.com/apis/site/v2/sports/soccer/boxscore?event=' . rawurlencode( $match_id_filter );
                } else {
                    // Try summary endpoint first
                    $summary_url = 'http://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $league_code ) . '/summary?event=' . rawurlencode( $match_id_filter );
                    
                    // Also try boxscore endpoint as alternative
                    $boxscore_url = 'http://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode( $league_code ) . '/boxscore?event=' . rawurlencode( $match_id_filter );
                }
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Trying to fetch match ' . $match_id_filter . ' from league ' . $league_code . ' via summary URL: ' . $summary_url );
                }
                
                $summary_response = wp_remote_get( $summary_url, array( 'timeout' => 10 ) );
                
                // If summary fails, try boxscore endpoint
                if ( is_wp_error( $summary_response ) || wp_remote_retrieve_response_code( $summary_response ) !== 200 ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'Summary endpoint failed, trying boxscore URL: ' . $boxscore_url );
                    }
                    $summary_response = wp_remote_get( $boxscore_url, array( 'timeout' => 10 ) );
                }
                
                if ( is_wp_error( $summary_response ) ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'Summary endpoint error for ' . $league_code . ': ' . $summary_response->get_error_message() );
                    }
                    continue;
                }
                
                $response_code = wp_remote_retrieve_response_code( $summary_response );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Summary endpoint response code for ' . $league_code . ': ' . $response_code );
                }
                
                if ( $response_code === 200 ) {
                    $summary_body = wp_remote_retrieve_body( $summary_response );
                    $summary_data = json_decode( $summary_body, true );
                    
                    // Log response for debugging (first 500 chars)
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'Summary response body (first 500 chars): ' . substr( $summary_body, 0, 500 ) );
                        error_log( 'Summary data decoded: ' . ( is_array( $summary_data ) ? 'Yes' : 'No' ) );
                        if ( is_array( $summary_data ) ) {
                            error_log( 'Has header: ' . ( isset( $summary_data['header'] ) ? 'Yes' : 'No' ) );
                            error_log( 'Has boxscore: ' . ( isset( $summary_data['boxscore'] ) ? 'Yes' : 'No' ) );
                            if ( isset( $summary_data['header'] ) ) {
                                error_log( 'Header keys: ' . implode( ', ', array_keys( $summary_data['header'] ) ) );
                            }
                            if ( isset( $summary_data['boxscore'] ) ) {
                                error_log( 'Boxscore keys: ' . implode( ', ', array_keys( $summary_data['boxscore'] ) ) );
                            }
                        }
                    }
                    
                    // Try boxscore format first (team schedule format)
                    if ( is_array( $summary_data ) && isset( $summary_data['boxscore'] ) && isset( $summary_data['boxscore']['form'] ) ) {
                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            error_log( 'Match found via boxscore format!' );
                        }
                        
                        // Search through form events to find the matching match ID
                        $form_events = $summary_data['boxscore']['form'];
                        foreach ( $form_events as $form_item ) {
                            if ( isset( $form_item['events'] ) && is_array( $form_item['events'] ) ) {
                                foreach ( $form_item['events'] as $event ) {
                                    $event_id = isset( $event['id'] ) ? (string) $event['id'] : '';
                                    if ( $event_id === $match_id_filter ) {
                                        // Found the match!
                                        $home_team_id = isset( $event['homeTeamId'] ) ? (string) $event['homeTeamId'] : '';
                                        $away_team_id = isset( $event['awayTeamId'] ) ? (string) $event['awayTeamId'] : '';
                                        $home_score = isset( $event['homeTeamScore'] ) ? intval( $event['homeTeamScore'] ) : null;
                                        $away_score = isset( $event['awayTeamScore'] ) ? intval( $event['awayTeamScore'] ) : null;
                                        $score_string = isset( $event['score'] ) ? $event['score'] : '';
                                        
                                        // Get team names from opponent or parse from score string
                                        $home_team = '';
                                        $away_team = '';
                                        if ( isset( $event['opponent'] ) ) {
                                            // Determine which team is home/away based on atVs
                                            $at_vs = isset( $event['atVs'] ) ? $event['atVs'] : '';
                                            if ( $at_vs === 'vs' ) {
                                                // Team in form is home
                                                $home_team = isset( $form_item['team']['displayName'] ) ? $form_item['team']['displayName'] : '';
                                                $away_team = isset( $event['opponent']['displayName'] ) ? $event['opponent']['displayName'] : '';
                                            } else {
                                                // Team in form is away
                                                $away_team = isset( $form_item['team']['displayName'] ) ? $form_item['team']['displayName'] : '';
                                                $home_team = isset( $event['opponent']['displayName'] ) ? $event['opponent']['displayName'] : '';
                                            }
                                        }
                                        
                                        // Parse score if not already parsed
                                        if ( $home_score === null && $away_score === null && ! empty( $score_string ) ) {
                                            $score_parts = explode( '-', $score_string );
                                            if ( count( $score_parts ) === 2 ) {
                                                $home_score = intval( trim( $score_parts[0] ) );
                                                $away_score = intval( trim( $score_parts[1] ) );
                                            }
                                        }
                                        
                                        // Determine status
                                        $status = 'Scheduled';
                                        $match_time = '';
                                        $game_date = isset( $event['gameDate'] ) ? $event['gameDate'] : '';
                                        if ( $home_score !== null && $away_score !== null ) {
                                            // Check if game date is in the past
                                            if ( ! empty( $game_date ) ) {
                                                $game_timestamp = strtotime( $game_date );
                                                if ( $game_timestamp < time() ) {
                                                    $status = 'Finished';
                                                } else {
                                                    $status = 'Scheduled';
                                                }
                                            } else {
                                                $status = 'Finished'; // Has score, assume finished
                                            }
                                        }
                                        
                                        $league_name = isset( $event['leagueName'] ) ? $event['leagueName'] : 'Premier League';
                                        
                                        $direct_match = array(
                                            'match_id' => $match_id_filter,
                                            'id' => $match_id_filter,
                                            'home_team' => $home_team,
                                            'away_team' => $away_team,
                                            'home_score' => $home_score,
                                            'away_score' => $away_score,
                                            'home_logo' => $home_team_id ? 'https://a.espncdn.com/i/teamlogos/soccer/500/' . $home_team_id . '.png' : '',
                                            'away_logo' => $away_team_id ? 'https://a.espncdn.com/i/teamlogos/soccer/500/' . $away_team_id . '.png' : '',
                                            'league' => $league_name,
                                            'status' => $status,
                                            'time' => $match_time,
                                            'scorers' => array(),
                                            'cards' => array(),
                                            'team1' => $home_team,
                                            'team2' => $away_team,
                                            'score' => ( $home_score !== null && $away_score !== null ) ? $home_score . ' - ' . $away_score : '',
                                        );
                                        
                                        $matches[] = $direct_match;
                                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                            error_log( 'Match added to results from boxscore: ' . $home_team . ' vs ' . $away_team );
                                        }
                                        break 2; // Break out of both loops
                                    }
                                }
                            }
                        }
                        
                        // If we found a match, break out of league loop
                        if ( ! empty( $matches ) ) {
                            break;
                        }
                    }
                    // Try header format (summary endpoint format)
                    elseif ( is_array( $summary_data ) && isset( $summary_data['header'] ) ) {
                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            error_log( 'Match found via summary endpoint!' );
                        }
                        // Parse match data from summary endpoint
                        $header = $summary_data['header'];
                        $competition = isset( $header['competitions'][0] ) ? $header['competitions'][0] : array();
                        $competitors = isset( $competition['competitors'] ) ? $competition['competitors'] : array();
                        
                        $home_team = '';
                        $away_team = '';
                        $home_score = null;
                        $away_score = null;
                        $home_logo = '';
                        $away_logo = '';
                        $status = 'Scheduled';
                        $match_time = '';
                        
                        foreach ( $competitors as $comp ) {
                            $team_name = isset( $comp['team']['displayName'] ) ? $comp['team']['displayName'] : '';
                            $score = isset( $comp['score'] ) ? intval( $comp['score'] ) : null;
                            $homeAway = isset( $comp['homeAway'] ) ? $comp['homeAway'] : '';
                            $team_id = isset( $comp['team']['id'] ) ? (string) $comp['team']['id'] : '';
                            
                            if ( $homeAway === 'home' ) {
                                $home_team = $team_name;
                                $home_score = $score;
                                if ( $team_id ) {
                                    $home_logo = 'https://a.espncdn.com/i/teamlogos/soccer/500/' . $team_id . '.png';
                                }
                            } elseif ( $homeAway === 'away' ) {
                                $away_team = $team_name;
                                $away_score = $score;
                                if ( $team_id ) {
                                    $away_logo = 'https://a.espncdn.com/i/teamlogos/soccer/500/' . $team_id . '.png';
                                }
                            }
                        }
                        
                        // Get status and time
                        if ( isset( $header['status']['type']['name'] ) ) {
                            $status_name = $header['status']['type']['name'];
                            if ( $status_name === 'STATUS_HALFTIME' ) {
                                $status = 'Halftime';
                            } elseif ( in_array( $status_name, array( 'STATUS_FINAL', 'STATUS_FULL_TIME' ), true ) ) {
                                $status = 'Finished';
                            } elseif ( in_array( $status_name, array( 'STATUS_IN_PROGRESS', 'STATUS_DELAYED' ), true ) ) {
                                $status = 'In Progress';
                            }
                        }
                        
                        if ( isset( $header['status']['displayClock'] ) && $header['status']['displayClock'] !== '' ) {
                            $match_time = $header['status']['displayClock'];
                        } elseif ( isset( $header['status']['clock'] ) && $header['status']['clock'] !== '' ) {
                            $match_time = $header['status']['clock'];
                        }
                        
                        // Get league name
                        $league_name = isset( $competition['league']['name'] ) ? $competition['league']['name'] : 'Premier League';
                        
                        // Build match array
                        $direct_match = array(
                            'match_id' => $match_id_filter,
                            'id' => $match_id_filter,
                            'home_team' => $home_team,
                            'away_team' => $away_team,
                            'home_score' => $home_score,
                            'away_score' => $away_score,
                            'home_logo' => $home_logo,
                            'away_logo' => $away_logo,
                            'league' => $league_name,
                            'status' => $status,
                            'time' => $match_time,
                            'scorers' => array(),
                            'cards' => array(),
                            'team1' => $home_team,
                            'team2' => $away_team,
                            'score' => ( $home_score !== null && $away_score !== null ) ? $home_score . ' - ' . $away_score : '',
                        );
                        
                        // Try to get events from summary if available
                        if ( isset( $summary_data['keyEvents'] ) && is_array( $summary_data['keyEvents'] ) ) {
                            // Parse events similar to get_e_live_scores
                            // This is simplified - full parsing would be more complex
                        }
                        
                        $matches[] = $direct_match;
                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            error_log( 'Match added to results: ' . $home_team . ' vs ' . $away_team );
                        }
                        break; // Found the match, no need to check other leagues
                    } else {
                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            error_log( 'Summary data does not have header structure' );
                        }
                    }
                } else {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'Summary endpoint returned non-200 status: ' . $response_code );
                    }
                }
            }
            
            if ( empty( $matches ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'No match found via direct summary lookup for match_id: ' . $match_id_filter );
            }
        }
        
        // If no match found via direct lookup, fall back to scoreboard filtering
        if ( empty( $matches ) && ! empty( $match_id_filter ) ) {
            try {
                $all_matches = FDM_E_Datasource::get_e_live_scores( $leagues );
                $scoreboard_matches_for_debug = $all_matches; // Store for debug info
            } catch ( Exception $e ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Error calling get_e_live_scores: ' . $e->getMessage() );
                }
                $all_matches = array();
            }
            
            // Debug logging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Match ID filter: ' . $match_id_filter );
                error_log( 'Total matches from scoreboard API: ' . count( $all_matches ) );
                if ( ! empty( $all_matches ) ) {
                    error_log( 'First match sample: ' . print_r( $all_matches[0], true ) );
                    // Log all match IDs found
                    $found_ids = array();
                    foreach ( $all_matches as $m ) {
                        $mid = isset( $m['match_id'] ) ? $m['match_id'] : ( isset( $m['id'] ) ? $m['id'] : 'N/A' );
                        $found_ids[] = $mid . ' (' . ( isset( $m['home_team'] ) ? $m['home_team'] : '' ) . ' vs ' . ( isset( $m['away_team'] ) ? $m['away_team'] : '' ) . ')';
                    }
                    error_log( 'All match IDs from scoreboard: ' . implode( ', ', $found_ids ) );
                } else {
                    error_log( 'Scoreboard API returned 0 matches for leagues: ' . implode( ', ', $leagues ) );
                }
            }
            
            // Filter by match ID from scoreboard
            foreach ( $all_matches as $match ) {
                // Check if match_id matches (could be in match_id, e_match_id, or id field)
                $match_id = isset( $match['match_id'] ) ? $match['match_id'] : 
                           ( isset( $match['e_match_id'] ) ? $match['e_match_id'] : 
                           ( isset( $match['id'] ) ? $match['id'] : '' ) );
                
                // Debug logging
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Comparing: API match_id = "' . $match_id . '" (type: ' . gettype( $match_id ) . ') vs filter = "' . $match_id_filter . '" (type: ' . gettype( $match_id_filter ) . ')' );
                }
                
                // Use loose comparison to handle string vs int
                if ( $match_id == $match_id_filter || (string) $match_id === (string) $match_id_filter ) {
                    $matches[] = $match;
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'Match found in scoreboard! Added to results: ' . ( isset( $match['home_team'] ) ? $match['home_team'] : '' ) . ' vs ' . ( isset( $match['away_team'] ) ? $match['away_team'] : '' ) );
                    }
                    break; // Only one match should match
                }
            }
            
            // Debug if no match found
            if ( empty( $matches ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'No match found with ID: ' . $match_id_filter . ' in scoreboard. Total matches checked: ' . count( $all_matches ) );
            }
            
            // If match_id filtering failed, try team ID or team name filtering as fallback
            if ( empty( $matches ) && ! empty( $all_matches ) ) {
                // Try team ID first (more reliable)
                if ( ! empty( $team_id_filter ) ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'Match ID filtering failed, trying team ID fallback for: ' . $team_id_filter );
                    }
                    foreach ( $all_matches as $match ) {
                        $home_id = isset( $match['home_team_id'] ) ? (string) $match['home_team_id'] : '';
                        $away_id = isset( $match['away_team_id'] ) ? (string) $match['away_team_id'] : '';
                        if ( $home_id === $team_id_filter || $away_id === $team_id_filter ) {
                            $matches[] = $match;
                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( 'Match found via team ID fallback: ' . ( isset( $match['home_team'] ) ? $match['home_team'] : '' ) . ' vs ' . ( isset( $match['away_team'] ) ? $match['away_team'] : '' ) );
                            }
                        }
                    }
                }
                // Try team name as secondary fallback
                elseif ( ! empty( $team_filter ) ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'Match ID filtering failed, trying team name fallback for: ' . $team_filter );
                    }
                    foreach ( $all_matches as $match ) {
                        // Flexible team name matching - try various forms
                        $home_team = isset( $match['home_team'] ) ? strtolower( $match['home_team'] ) : '';
                        $away_team = isset( $match['away_team'] ) ? strtolower( $match['away_team'] ) : '';
                        $team_filter_lower = strtolower( $team_filter );
                        
                        // Try exact match, partial match, and common variations
                        $team_variations = array(
                            $team_filter_lower,
                            str_replace( ' united', ' utd', $team_filter_lower ),
                            str_replace( ' utd', ' united', $team_filter_lower ),
                            str_replace( ' ', '', $team_filter_lower ),
                        );
                        
                        $matches_team = false;
                        foreach ( $team_variations as $variation ) {
                            if ( stripos( $home_team, $variation ) !== false || stripos( $away_team, $variation ) !== false ||
                                 stripos( $variation, $home_team ) !== false || stripos( $variation, $away_team ) !== false ) {
                                $matches_team = true;
                                break;
                            }
                        }
                        
                        if ( $matches_team ) {
                            $matches[] = $match;
                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( 'Match found via team name fallback: ' . ( isset( $match['home_team'] ) ? $match['home_team'] : '' ) . ' vs ' . ( isset( $match['away_team'] ) ? $match['away_team'] : '' ) );
                            }
                        }
                    }
                }
            }
        } elseif ( empty( $match_id_filter ) ) {
            // No match_id filter, use team filter or show all
            $all_matches = FDM_E_Datasource::get_e_live_scores( $leagues );
            $scoreboard_matches_for_debug = $all_matches; // Store for potential debug info
            
            // Filter by team ID if specified (takes priority over team name)
            if ( ! empty( $team_id_filter ) && ! $show_all ) {
                foreach ( $all_matches as $match ) {
                    $home_id = isset( $match['home_team_id'] ) ? (string) $match['home_team_id'] : '';
                    $away_id = isset( $match['away_team_id'] ) ? (string) $match['away_team_id'] : '';
                    if ( $home_id === $team_id_filter || $away_id === $team_id_filter ) {
                        $matches[] = $match;
                    }
                }
            }
            // Filter by team name if specified (only if no team_id filter and no match_id filter and not show_all)
            elseif ( ! empty( $team_filter ) && ! $show_all ) {
                foreach ( $all_matches as $match ) {
                    if ( stripos( $match['home_team'], $team_filter ) !== false || 
                         stripos( $match['away_team'], $team_filter ) !== false ) {
                        $matches[] = $match;
                    }
                }
            } else {
                $matches = $all_matches;
            }
        }
    } else {
        // F Python API
        $response = wp_remote_get( 'http://127.0.0.1:5001/live-scores', array( 'timeout' => 30 ) );
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 
                'message' => 'F API connection error: ' . $response->get_error_message() . '. Make sure the Python API is running on port 5001.'
            ) );
            return;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            wp_send_json_error( array( 
                'message' => 'F API returned HTTP status ' . $code . '. Make sure the Python API is running on port 5001.'
            ) );
            return;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! is_array( $data ) ) {
            wp_send_json_error( array( 'message' => 'F API returned invalid JSON response.' ) );
            return;
        }
        
        if ( isset( $data['live_matches'] ) && is_array( $data['live_matches'] ) ) {
            $all_matches = $data['live_matches'];
            
            // Convert to standard format
            foreach ( $all_matches as $match ) {
                // Parse score from "2-1" format to separate home_score and away_score
                $home_score = null;
                $away_score = null;
                if ( isset( $match['score'] ) && ! empty( $match['score'] ) ) {
                    $score_parts = explode( '-', $match['score'] );
                    if ( count( $score_parts ) === 2 ) {
                        $home_score = intval( trim( $score_parts[0] ) );
                        $away_score = intval( trim( $score_parts[1] ) );
                    }
                } elseif ( isset( $match['home_score'] ) && isset( $match['away_score'] ) ) {
                    // Already in separate format
                    $home_score = intval( $match['home_score'] );
                    $away_score = intval( $match['away_score'] );
                }
                
                // Determine status based on time/score
                $status = 'Scheduled';
                if ( isset( $match['time'] ) && ! empty( $match['time'] ) ) {
                    $status = 'In Progress';
                } elseif ( $home_score !== null && $away_score !== null ) {
                    $status = 'Finished';
                }
                
                $formatted = array(
                    'match_id' => isset( $match['match_id'] ) ? $match['match_id'] : '',
                    'home_team' => isset( $match['team1'] ) ? $match['team1'] : ( isset( $match['home_team'] ) ? $match['home_team'] : '' ),
                    'away_team' => isset( $match['team2'] ) ? $match['team2'] : ( isset( $match['away_team'] ) ? $match['away_team'] : '' ),
                    'home_score' => $home_score,
                    'away_score' => $away_score,
                    'league' => isset( $match['league'] ) ? $match['league'] : 'Premier League',
                    'status' => isset( $match['status'] ) ? $match['status'] : $status,
                    'time' => isset( $match['time'] ) ? $match['time'] : '',
                    'scorers' => isset( $match['scorers'] ) ? $match['scorers'] : array(),
                    'cards' => isset( $match['cards'] ) ? $match['cards'] : array(),
                );
                
                // Filter by match ID if specified (takes priority over team filter)
                if ( ! empty( $match_id_filter ) ) {
                    if ( $formatted['match_id'] == $match_id_filter ) {
                        $matches[] = $formatted;
                    }
                }
                // Filter by team if specified (only if no match_id filter)
                elseif ( ! empty( $team_filter ) && ! $show_all ) {
                    if ( stripos( $formatted['home_team'], $team_filter ) !== false || 
                         stripos( $formatted['away_team'], $team_filter ) !== false ) {
                        $matches[] = $formatted;
                    }
                } else {
                    $matches[] = $formatted;
                }
            }
        } else {
            // No live matches or invalid format
            $matches = array();
        }
    }
    
    // Add debug info if match_id filter was used
    $debug_info = null;
    if ( ! empty( $match_id_filter ) && empty( $matches ) ) {
        // Use the scoreboard matches we already fetched (if available)
        $available_match_ids = array();
        if ( ! empty( $scoreboard_matches_for_debug ) ) {
            foreach ( $scoreboard_matches_for_debug as $m ) {
                $mid = isset( $m['match_id'] ) ? $m['match_id'] : ( isset( $m['id'] ) ? $m['id'] : 'N/A' );
                $available_match_ids[] = $mid;
            }
        }
        
        $debug_info = array(
            'match_id_filter' => $match_id_filter,
            'leagues_tried' => $all_possible_leagues,
            'scoreboard_matches_found' => is_array( $scoreboard_matches_for_debug ) ? count( $scoreboard_matches_for_debug ) : 0,
            'available_match_ids' => $available_match_ids,
            'message' => 'No match found with specified match_id. Check WordPress debug log for details.'
        );
    }
    
    wp_send_json_success( array(
        'matches' => $matches,
        'source' => $source,
        'count' => count( $matches ),
        'debug' => $debug_info
    ) );
}

// Load WP-CLI commands if WP-CLI is available
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once FDM_PLUGIN_DIR . 'includes/wp-cli-commands.php';
}