<?php
/**
 * Plugin Name: FootyForums Database Backup
 * Description: Backup and restore selected MySQL databases from the WordPress admin.
 * Version: 1.0.1
 * Author: Me
 * Text Domain: footyforums-db-backup
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FootyForums_DB_Backup {

    private $option_host           = 'ffdb_host';
    private $option_port           = 'ffdb_port';
    private $option_user           = 'ffdb_user';
    private $option_password       = 'ffdb_password';
    private $option_wp_db_name     = 'ffdb_wp_db_name';
    private $option_shared_db_name = 'ffdb_shared_db_name';

    private $backup_root;

    // Absolute paths to binaries (Homebrew default on Apple Silicon)
    // On a live server you can change these to just 'mysqldump' and 'mysql'
    // or to whatever path the host provides.
    private $mysqldump_bin = '/opt/homebrew/bin/mysqldump';
    private $mysql_bin     = '/opt/homebrew/bin/mysql';

    private $notices = array();

    public function __construct() {
        $this->backup_root = WP_CONTENT_DIR . '/footyforums_backups';

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
    }

    public function add_admin_menu() {
        add_management_page(
            'Database Backup',
            'Database backup',
            'manage_options',
            'footyforums-db-backup',
            array( $this, 'render_admin_page' )
        );
    }

    public function handle_form_submissions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['ffdb_save_settings'] ) && check_admin_referer( 'ffdb_save_settings' ) ) {
            $this->save_connection_settings();
        }

        if ( isset( $_POST['ffdb_backup_now'] ) && check_admin_referer( 'ffdb_backup_now' ) ) {
            $this->perform_backup();
        }

        if ( isset( $_POST['ffdb_restore'] ) && check_admin_referer( 'ffdb_restore' ) ) {
            $this->perform_restore();
        }
    }

    private function save_connection_settings() {
        $host           = sanitize_text_field( $_POST['ffdb_host'] ?? '' );
        $port           = sanitize_text_field( $_POST['ffdb_port'] ?? '' );
        $user           = sanitize_text_field( $_POST['ffdb_user'] ?? '' );
        $password       = sanitize_text_field( $_POST['ffdb_password'] ?? '' );
        $wp_db_name     = sanitize_text_field( $_POST['ffdb_wp_db_name'] ?? '' );
        $shared_db_name = sanitize_text_field( $_POST['ffdb_shared_db_name'] ?? '' );

        if ( empty( $host ) || empty( $user ) || empty( $wp_db_name ) ) {
            $this->add_notice( 'error', 'Host, User, and WordPress database name are required.' );
            return;
        }

        update_option( $this->option_host, $host );
        update_option( $this->option_port, $port ?: '3306' );
        update_option( $this->option_user, $user );

        if ( ! empty( $password ) ) {
            update_option( $this->option_password, $password );
        }

        update_option( $this->option_wp_db_name, $wp_db_name );
        update_option( $this->option_shared_db_name, $shared_db_name ?: 'footyforums_data' );

        $this->add_notice( 'success', 'Connection settings saved successfully.' );
    }

    private function perform_backup() {
        if ( ! function_exists( 'exec' ) ) {
            $this->add_notice( 'error', 'PHP exec() function is not available. Please contact your hosting provider.' );
            return;
        }

        $disabled_functions = ini_get( 'disable_functions' );
        if ( $disabled_functions && strpos( $disabled_functions, 'exec' ) !== false ) {
            $this->add_notice( 'error', 'PHP exec() function is disabled. Please contact your hosting provider.' );
            return;
        }

        $host           = get_option( $this->option_host );
        $port           = get_option( $this->option_port, '3306' );
        $user           = get_option( $this->option_user );
        $password       = get_option( $this->option_password );
        $wp_db_name     = get_option( $this->option_wp_db_name );
        $shared_db_name = get_option( $this->option_shared_db_name, 'footyforums_data' );

        if ( empty( $host ) || empty( $user ) || empty( $wp_db_name ) || empty( $shared_db_name ) ) {
            $this->add_notice( 'error', 'Please configure all database settings before backing up.' );
            return;
        }

        if ( ! file_exists( $this->backup_root ) ) {
            if ( ! wp_mkdir_p( $this->backup_root ) ) {
                $this->add_notice( 'error', 'Failed to create backup directory: ' . $this->backup_root );
                return;
            }
            chmod( $this->backup_root, 0755 );
        }

        $timestamp  = date( 'Y-m-d_His' );
        $backup_dir = $this->backup_root . '/' . $timestamp;

        if ( ! wp_mkdir_p( $backup_dir ) ) {
            $this->add_notice( 'error', 'Failed to create backup directory: ' . $backup_dir );
            return;
        }
        chmod( $backup_dir, 0755 );

        $wp_file = $backup_dir . '/wp_' . $wp_db_name . '_' . date( 'Ymd_His' ) . '.sql';

        $mysql_socket = '';
        $host_for_cmd = $host;
        $port_for_cmd = $port;

        if ( strpos( $host, '/' ) === 0 ) {
            $mysql_socket = $host;
            $host_for_cmd = 'localhost';
        } elseif ( defined( 'FFDB_MYSQL_SOCKET' ) && FFDB_MYSQL_SOCKET ) {
            $mysql_socket = FFDB_MYSQL_SOCKET;
            $host_for_cmd = 'localhost';
        }

        $wp_cmd_parts = array(
            escapeshellcmd( $this->mysqldump_bin ),
        );

        if ( $mysql_socket ) {
            $wp_cmd_parts[] = '--socket=' . escapeshellarg( $mysql_socket );
        } else {
            $wp_cmd_parts[] = '-h ' . escapeshellarg( $host_for_cmd );
            $wp_cmd_parts[] = '-P ' . escapeshellarg( $port_for_cmd );
        }

        $wp_cmd_parts[] = '-u ' . escapeshellarg( $user );
        if ( $password !== '' && $password !== null ) {
            $wp_cmd_parts[] = '-p' . escapeshellarg( $password );
        }

        $wp_cmd_parts[] = '--single-transaction';
        $wp_cmd_parts[] = '--quick';
        $wp_cmd_parts[] = '--routines';
        $wp_cmd_parts[] = '--events';
        $wp_cmd_parts[] = escapeshellarg( $wp_db_name );
        $wp_cmd_parts[] = '> ' . escapeshellarg( $wp_file ) . ' 2>&1';

        $wp_cmd = implode( ' ', $wp_cmd_parts );

        $wp_output = array();
        $wp_return = 0;
        exec( $wp_cmd, $wp_output, $wp_return );

        if ( $wp_return !== 0 || ! file_exists( $wp_file ) || filesize( $wp_file ) === 0 ) {
            $error_msg = implode( "\n", $wp_output );
            error_log(
                sprintf(
                    '[FFDB Backup] WordPress DB backup failed: %s | Command: %s',
                    $error_msg,
                    str_replace( $password, '***', $wp_cmd )
                )
            );
            $this->add_notice( 'error', 'WordPress database backup failed. Check error log for details.' );
            return;
        }
        chmod( $wp_file, 0644 );

        $shared_file = $backup_dir . '/ff_' . $shared_db_name . '_' . date( 'Ymd_His' ) . '.sql';

        $shared_cmd_parts = array(
            escapeshellcmd( $this->mysqldump_bin ),
        );

        if ( $mysql_socket ) {
            $shared_cmd_parts[] = '--socket=' . escapeshellarg( $mysql_socket );
        } else {
            $shared_cmd_parts[] = '-h ' . escapeshellarg( $host_for_cmd );
            $shared_cmd_parts[] = '-P ' . escapeshellarg( $port_for_cmd );
        }

        $shared_cmd_parts[] = '-u ' . escapeshellarg( $user );
        if ( $password !== '' && $password !== null ) {
            $shared_cmd_parts[] = '-p' . escapeshellarg( $password );
        }

        $shared_cmd_parts[] = '--single-transaction';
        $shared_cmd_parts[] = '--quick';
        $shared_cmd_parts[] = '--routines';
        $shared_cmd_parts[] = '--events';
        $shared_cmd_parts[] = escapeshellarg( $shared_db_name );
        $shared_cmd_parts[] = '> ' . escapeshellarg( $shared_file ) . ' 2>&1';

        $shared_cmd = implode( ' ', $shared_cmd_parts );

        $shared_output = array();
        $shared_return = 0;
        exec( $shared_cmd, $shared_output, $shared_return );

        if ( $shared_return !== 0 || ! file_exists( $shared_file ) || filesize( $shared_file ) === 0 ) {
            $error_msg = implode( "\n", $shared_output );
            error_log(
                sprintf(
                    '[FFDB Backup] Shared DB backup failed: %s | Command: %s',
                    $error_msg,
                    str_replace( $password, '***', $shared_cmd )
                )
            );
            $this->add_notice( 'error', 'Shared database backup failed. Check error log for details.' );
            @unlink( $wp_file );
            @rmdir( $backup_dir );
            return;
        }
        chmod( $shared_file, 0644 );

        error_log(
            sprintf(
                '[FFDB Backup] Success: Both databases backed up to %s | WP: %s, Shared: %s',
                $backup_dir,
                $wp_db_name,
                $shared_db_name
            )
        );

        $this->add_notice(
            'success',
            sprintf(
                'Backup completed successfully. Files saved to: %s',
                $backup_dir
            )
        );
    }

    private function perform_restore() {
        if ( ! function_exists( 'exec' ) ) {
            $this->add_notice( 'error', 'PHP exec() function is not available. Please contact your hosting provider.' );
            return;
        }

        $disabled_functions = ini_get( 'disable_functions' );
        if ( $disabled_functions && strpos( $disabled_functions, 'exec' ) !== false ) {
            $this->add_notice( 'error', 'PHP exec() function is disabled. Please contact your hosting provider.' );
            return;
        }

        $backup_folder = sanitize_text_field( $_POST['ffdb_backup_folder'] ?? '' );

        if ( empty( $backup_folder ) ) {
            $this->add_notice( 'error', 'Please select a backup to restore.' );
            return;
        }

        if ( preg_match( '/[^a-zA-Z0-9\-_]/', $backup_folder ) ) {
            $this->add_notice( 'error', 'Invalid backup folder name.' );
            return;
        }

        $backup_dir = $this->backup_root . '/' . $backup_folder;

        if ( ! file_exists( $backup_dir ) || ! is_dir( $backup_dir ) ) {
            $this->add_notice( 'error', 'Backup directory not found.' );
            return;
        }

        $real_backup_dir  = realpath( $backup_dir );
        $real_backup_root = realpath( $this->backup_root );
        if ( strpos( $real_backup_dir, $real_backup_root ) !== 0 ) {
            $this->add_notice( 'error', 'Invalid backup path.' );
            return;
        }

        $wp_file     = null;
        $shared_file = null;

        $files = glob( $backup_dir . '/*.sql' );
        foreach ( $files as $file ) {
            $basename = basename( $file );
            if ( strpos( $basename, 'wp_' ) === 0 ) {
                $wp_file = $file;
            } elseif ( strpos( $basename, 'ff_' ) === 0 ) {
                $shared_file = $file;
            }
        }

        if ( ! $wp_file || ! $shared_file ) {
            $this->add_notice( 'error', 'Backup files not found. Expected wp_*.sql and ff_*.sql files.' );
            return;
        }

        $host           = get_option( $this->option_host );
        $port           = get_option( $this->option_port, '3306' );
        $user           = get_option( $this->option_user );
        $password       = get_option( $this->option_password );
        $wp_db_name     = get_option( $this->option_wp_db_name );
        $shared_db_name = get_option( $this->option_shared_db_name, 'footyforums_data' );

        if ( empty( $host ) || empty( $user ) || empty( $wp_db_name ) || empty( $shared_db_name ) ) {
            $this->add_notice( 'error', 'Please configure all database settings before restoring.' );
            return;
        }

        $mysql_socket = '';
        $host_for_cmd = $host;
        $port_for_cmd = $port;

        if ( strpos( $host, '/' ) === 0 ) {
            $mysql_socket = $host;
            $host_for_cmd = 'localhost';
        } elseif ( defined( 'FFDB_MYSQL_SOCKET' ) && FFDB_MYSQL_SOCKET ) {
            $mysql_socket = FFDB_MYSQL_SOCKET;
            $host_for_cmd = 'localhost';
        }

        $wp_cmd_parts = array(
            escapeshellcmd( $this->mysql_bin ),
        );

        if ( $mysql_socket ) {
            $wp_cmd_parts[] = '--socket=' . escapeshellarg( $mysql_socket );
        } else {
            $wp_cmd_parts[] = '-h ' . escapeshellarg( $host_for_cmd );
            $wp_cmd_parts[] = '-P ' . escapeshellarg( $port_for_cmd );
        }

        $wp_cmd_parts[] = '-u ' . escapeshellarg( $user );
        if ( $password !== '' && $password !== null ) {
            $wp_cmd_parts[] = '-p' . escapeshellarg( $password );
        }

        $wp_cmd_parts[] = escapeshellarg( $wp_db_name );
        $wp_cmd_parts[] = '< ' . escapeshellarg( $wp_file ) . ' 2>&1';

        $wp_cmd = implode( ' ', $wp_cmd_parts );

        $wp_output = array();
        $wp_return = 0;
        exec( $wp_cmd, $wp_output, $wp_return );

        if ( $wp_return !== 0 ) {
            $error_msg = implode( "\n", $wp_output );
            error_log(
                sprintf(
                    '[FFDB Restore] WordPress DB restore failed: %s | Command: %s',
                    $error_msg,
                    str_replace( $password, '***', $wp_cmd )
                )
            );
            $this->add_notice( 'error', 'WordPress database restore failed. Check error log for details.' );
            return;
        }

        $shared_cmd_parts = array(
            escapeshellcmd( $this->mysql_bin ),
        );

        if ( $mysql_socket ) {
            $shared_cmd_parts[] = '--socket=' . escapeshellarg( $mysql_socket );
        } else {
            $shared_cmd_parts[] = '-h ' . escapeshellarg( $host_for_cmd );
            $shared_cmd_parts[] = '-P ' . escapeshellarg( $port_for_cmd );
        }

        $shared_cmd_parts[] = '-u ' . escapeshellarg( $user );
        if ( $password !== '' && $password !== null ) {
            $shared_cmd_parts[] = '-p' . escapeshellarg( $password );
        }

        $shared_cmd_parts[] = escapeshellarg( $shared_db_name );
        $shared_cmd_parts[] = '< ' . escapeshellarg( $shared_file ) . ' 2>&1';

        $shared_cmd = implode( ' ', $shared_cmd_parts );

        $shared_output = array();
        $shared_return = 0;
        exec( $shared_cmd, $shared_output, $shared_return );

        if ( $shared_return !== 0 ) {
            $error_msg = implode( "\n", $shared_output );
            error_log(
                sprintf(
                    '[FFDB Restore] Shared DB restore failed: %s | Command: %s',
                    $error_msg,
                    str_replace( $password, '***', $shared_cmd )
                )
            );
            $this->add_notice( 'error', 'Shared database restore failed. Check error log for details.' );
            return;
        }

        error_log(
            sprintf(
                '[FFDB Restore] Success: Both databases restored from %s | WP: %s, Shared: %s',
                $backup_dir,
                $wp_db_name,
                $shared_db_name
            )
        );

        $this->add_notice(
            'success',
            sprintf(
                'Restore completed successfully from: %s',
                $backup_folder
            )
        );
    }

    private function get_backup_sets() {
        $backup_sets = array();

        if ( ! file_exists( $this->backup_root ) || ! is_dir( $this->backup_root ) ) {
            return $backup_sets;
        }

        $dirs = glob( $this->backup_root . '/*', GLOB_ONLYDIR );
        foreach ( $dirs as $dir ) {
            $folder_name = basename( $dir );
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}_\d{6}$/', $folder_name ) ) {
                $wp_files = glob( $dir . '/wp_*.sql' );
                $ff_files = glob( $dir . '/ff_*.sql' );
                if ( ! empty( $wp_files ) && ! empty( $ff_files ) ) {
                    $parts      = explode( '_', $folder_name );
                    $date_part  = $parts[0];
                    $time_part  = $parts[1];
                    $formatted  = $date_part . ' ' . substr( $time_part, 0, 2 ) . ':' . substr( $time_part, 2, 2 ) . ':' . substr( $time_part, 4, 2 );
                    $backup_sets[ $folder_name ] = $formatted;
                }
            }
        }

        krsort( $backup_sets );

        return $backup_sets;
    }

    private function get_defaults() {
        return array(
            'host'           => defined( 'DB_HOST' ) ? DB_HOST : '127.0.0.1',
            'port'           => '3306',
            'user'           => defined( 'DB_USER' ) ? DB_USER : '',
            'password'       => defined( 'DB_PASSWORD' ) ? DB_PASSWORD : '',
            'wp_db_name'     => defined( 'DB_NAME' ) ? DB_NAME : '',
            'shared_db_name' => 'footyforums_data',
        );
    }

    private function get_settings() {
        $defaults = $this->get_defaults();

        return array(
            'host'           => get_option( $this->option_host, $defaults['host'] ),
            'port'           => get_option( $this->option_port, $defaults['port'] ),
            'user'           => get_option( $this->option_user, $defaults['user'] ),
            'password'       => get_option( $this->option_password, $defaults['password'] ),
            'wp_db_name'     => get_option( $this->option_wp_db_name, $defaults['wp_db_name'] ),
            'shared_db_name' => get_option( $this->option_shared_db_name, $defaults['shared_db_name'] ),
        );
    }

    private function add_notice( $type, $message ) {
        $this->notices[] = array(
            'type'    => $type,
            'message' => $message,
        );
    }

    public function show_admin_notices() {
        $screen = get_current_screen();
        if ( $screen && $screen->id !== 'tools_page_footyforums-db-backup' ) {
            return;
        }

        foreach ( $this->notices as $notice ) {
            $class = $notice['type'] === 'error' ? 'notice-error' : 'notice-success';
            printf(
                '<div class="notice %s is-dismissible"><p>%s</p></div>',
                esc_attr( $class ),
                esc_html( $notice['message'] )
            );
        }
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        $settings    = $this->get_settings();
        $backup_sets = $this->get_backup_sets();
        ?>
        <div class="wrap">
            <h1>Database Backup</h1>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Connection Settings</h2>
                <form method="post" action="">
                    <?php wp_nonce_field( 'ffdb_save_settings' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ffdb_host">MySQL Host</label>
                            </th>
                            <td>
                                <input type="text" id="ffdb_host" name="ffdb_host" value="<?php echo esc_attr( $settings['host'] ); ?>" class="regular-text" required />
                                <p class="description">For local with socket, use the socket path here instead of host name.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ffdb_port">MySQL Port</label>
                            </th>
                            <td>
                                <input type="text" id="ffdb_port" name="ffdb_port" value="<?php echo esc_attr( $settings['port'] ); ?>" class="regular-text" />
                                <p class="description">Ignored when using a socket.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ffdb_user">MySQL User</label>
                            </th>
                            <td>
                                <input type="text" id="ffdb_user" name="ffdb_user" value="<?php echo esc_attr( $settings['user'] ); ?>" class="regular-text" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ffdb_password">MySQL Password</label>
                            </th>
                            <td>
                                <input type="password" id="ffdb_password" name="ffdb_password" value="" class="regular-text" placeholder="Leave empty to keep current password" />
                                <p class="description">Leave empty to keep current password. Enter new password only if you want to change it.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ffdb_wp_db_name">WordPress Database Name</label>
                            </th>
                            <td>
                                <input type="text" id="ffdb_wp_db_name" name="ffdb_wp_db_name" value="<?php echo esc_attr( $settings['wp_db_name'] ); ?>" class="regular-text" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ffdb_shared_db_name">Shared Database Name</label>
                            </th>
                            <td>
                                <input type="text" id="ffdb_shared_db_name" name="ffdb_shared_db_name" value="<?php echo esc_attr( $settings['shared_db_name'] ); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="ffdb_save_settings" class="button button-primary" value="Save Settings" />
                    </p>
                </form>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Backup Databases</h2>
                <p>The following databases will be backed up:</p>
                <ul>
                    <li><strong>WordPress DB:</strong> <?php echo esc_html( $settings['wp_db_name'] ?: 'Not configured' ); ?></li>
                    <li><strong>Shared DB:</strong> <?php echo esc_html( $settings['shared_db_name'] ?: 'Not configured' ); ?></li>
                </ul>
                <form method="post" action="" onsubmit="return confirm('This will create a backup of both databases. Continue?');">
                    <?php wp_nonce_field( 'ffdb_backup_now' ); ?>
                    <p class="submit">
                        <input type="submit" name="ffdb_backup_now" class="button button-primary" value="Backup databases now" />
                    </p>
                </form>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Restore from Backup</h2>
                <?php if ( empty( $backup_sets ) ) : ?>
                    <p>No backups found. Create a backup first.</p>
                <?php else : ?>
                    <form method="post" action="" id="ffdb-restore-form" onsubmit="return confirm('This will overwrite both databases using the selected backup files. This action cannot be undone. Are you sure?');">
                        <?php wp_nonce_field( 'ffdb_restore' ); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ffdb_backup_folder">Select Backup</label>
                                </th>
                                <td>
                                    <select id="ffdb_backup_folder" name="ffdb_backup_folder" class="regular-text" required>
                                        <option value="">-- Select a backup --</option>
                                        <?php foreach ( $backup_sets as $folder => $display ) : ?>
                                            <option value="<?php echo esc_attr( $folder ); ?>"><?php echo esc_html( $display ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="ffdb_restore" class="button button-primary" value="Restore from backup" />
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

new FootyForums_DB_Backup();