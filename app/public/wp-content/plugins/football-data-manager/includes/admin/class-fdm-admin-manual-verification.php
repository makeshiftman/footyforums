<?php
/**
 * Class FDM_Admin_Manual_Verification
 *
 * Admin page for manually verifying ESPN data availability.
 * Shows URLs that need manual checking when the prober couldn't find data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FDM_Admin_Manual_Verification {

    /**
     * Verification handler
     *
     * @var FDM_Manual_Verification
     */
    private $verification;

    /**
     * Constructor
     */
    public function __construct() {
        require_once dirname( dirname( __FILE__ ) ) . '/class-fdm-manual-verification.php';
        $this->verification = new FDM_Manual_Verification();

        add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_fdm_update_verification', [ $this, 'ajax_update_verification' ] );
        add_action( 'wp_ajax_fdm_bulk_update_verification', [ $this, 'ajax_bulk_update' ] );
    }

    /**
     * Add submenu page
     */
    public function add_submenu_page() {
        add_submenu_page(
            'fdm-data-status',
            'Manual Verification',
            'Manual Verification',
            'manage_options',
            'fdm-manual-verification',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts( $hook ) {
        if ( 'football-data-manager_page_fdm-manual-verification' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'fdm-manual-verification',
            plugins_url( 'assets/css/manual-verification.css', dirname( dirname( __FILE__ ) ) ),
            [],
            '1.0.0'
        );
    }

    /**
     * Render the admin page
     */
    public function render_page() {
        $counts = $this->verification->get_counts();
        $data_types = $this->verification->get_pending_data_types();
        $filter_type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
        $pending = $this->verification->get_pending( $filter_type, 200 );

        ?>
        <div class="wrap fdm-manual-verification">
            <h1>ESPN Manual Verification</h1>

            <p class="description">
                These are data endpoints the prober couldn't automatically verify.
                Click URLs to check if data exists, then mark as verified or missing.
            </p>

            <!-- Summary Stats -->
            <div class="fdm-verification-stats">
                <div class="stat-box pending">
                    <span class="number"><?php echo esc_html( $counts['totals']['pending'] ?? 0 ); ?></span>
                    <span class="label">Pending</span>
                </div>
                <div class="stat-box verified">
                    <span class="number"><?php echo esc_html( $counts['totals']['verified_exists'] ?? 0 ); ?></span>
                    <span class="label">Verified Exists</span>
                </div>
                <div class="stat-box missing">
                    <span class="number"><?php echo esc_html( $counts['totals']['verified_missing'] ?? 0 ); ?></span>
                    <span class="label">Verified Missing</span>
                </div>
                <div class="stat-box skipped">
                    <span class="number"><?php echo esc_html( $counts['totals']['skipped'] ?? 0 ); ?></span>
                    <span class="label">Skipped</span>
                </div>
            </div>

            <!-- Filter by Data Type -->
            <div class="fdm-filter-bar">
                <strong>Filter by type:</strong>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fdm-manual-verification' ) ); ?>"
                   class="button <?php echo empty( $filter_type ) ? 'button-primary' : ''; ?>">
                    All (<?php echo esc_html( $counts['totals']['pending'] ?? 0 ); ?>)
                </a>
                <?php foreach ( $data_types as $type => $count ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=fdm-manual-verification&type=' . $type ) ); ?>"
                       class="button <?php echo $filter_type === $type ? 'button-primary' : ''; ?>">
                        <?php echo esc_html( ucfirst( str_replace( '_', ' ', $type ) ) ); ?>
                        (<?php echo esc_html( $count ); ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Bulk Actions -->
            <div class="fdm-bulk-actions">
                <button type="button" class="button" id="fdm-select-all">Select All</button>
                <button type="button" class="button" id="fdm-select-none">Select None</button>
                <span class="bulk-separator">|</span>
                <button type="button" class="button button-primary" id="fdm-bulk-exists">
                    Mark Selected as Exists
                </button>
                <button type="button" class="button" id="fdm-bulk-missing">
                    Mark Selected as Missing
                </button>
                <button type="button" class="button" id="fdm-bulk-skip">
                    Skip Selected
                </button>
            </div>

            <!-- Verification Table -->
            <?php if ( empty( $pending ) ) : ?>
                <div class="fdm-no-items">
                    <p>No pending verifications<?php echo $filter_type ? ' for ' . esc_html( $filter_type ) : ''; ?>.</p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped fdm-verification-table">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" id="fdm-check-all"></th>
                            <th class="column-league">League</th>
                            <th class="column-year">Year</th>
                            <th class="column-type">Data Type</th>
                            <th class="column-url">URL to Check</th>
                            <th class="column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $pending as $item ) : ?>
                            <tr data-id="<?php echo esc_attr( $item['id'] ); ?>">
                                <td class="check-column">
                                    <input type="checkbox" class="fdm-item-check" value="<?php echo esc_attr( $item['id'] ); ?>">
                                </td>
                                <td class="column-league">
                                    <strong><?php echo esc_html( $item['league_name'] ?: $item['league_code'] ); ?></strong>
                                    <br><code><?php echo esc_html( $item['league_code'] ); ?></code>
                                </td>
                                <td class="column-year"><?php echo esc_html( $item['season_year'] ); ?></td>
                                <td class="column-type">
                                    <span class="fdm-type-badge fdm-type-<?php echo esc_attr( $item['data_type'] ); ?>">
                                        <?php echo esc_html( ucfirst( str_replace( '_', ' ', $item['data_type'] ) ) ); ?>
                                    </span>
                                </td>
                                <td class="column-url">
                                    <a href="<?php echo esc_url( $item['check_url'] ); ?>" target="_blank" class="fdm-check-url">
                                        <?php echo esc_html( $this->truncate_url( $item['check_url'] ) ); ?>
                                    </a>
                                    <button type="button" class="button-link fdm-copy-url" data-url="<?php echo esc_attr( $item['check_url'] ); ?>">
                                        Copy
                                    </button>
                                </td>
                                <td class="column-actions">
                                    <button type="button" class="button button-small button-primary fdm-action-exists" data-id="<?php echo esc_attr( $item['id'] ); ?>">
                                        Exists
                                    </button>
                                    <button type="button" class="button button-small fdm-action-missing" data-id="<?php echo esc_attr( $item['id'] ); ?>">
                                        Missing
                                    </button>
                                    <button type="button" class="button button-small fdm-action-skip" data-id="<?php echo esc_attr( $item['id'] ); ?>">
                                        Skip
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <style>
            .fdm-manual-verification { max-width: 1400px; }
            .fdm-verification-stats {
                display: flex;
                gap: 20px;
                margin: 20px 0;
            }
            .fdm-verification-stats .stat-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 15px 25px;
                text-align: center;
                min-width: 120px;
            }
            .fdm-verification-stats .stat-box .number {
                display: block;
                font-size: 28px;
                font-weight: 600;
                line-height: 1.2;
            }
            .fdm-verification-stats .stat-box .label {
                display: block;
                color: #666;
                font-size: 12px;
                text-transform: uppercase;
            }
            .fdm-verification-stats .pending .number { color: #996800; }
            .fdm-verification-stats .verified .number { color: #00a32a; }
            .fdm-verification-stats .missing .number { color: #d63638; }
            .fdm-verification-stats .skipped .number { color: #787c82; }

            .fdm-filter-bar {
                margin: 20px 0;
                padding: 15px;
                background: #fff;
                border: 1px solid #ccd0d4;
            }
            .fdm-filter-bar .button { margin-left: 5px; }

            .fdm-bulk-actions {
                margin: 15px 0;
                padding: 10px 15px;
                background: #f0f0f1;
                border: 1px solid #ccd0d4;
            }
            .fdm-bulk-actions .bulk-separator {
                margin: 0 10px;
                color: #999;
            }

            .fdm-verification-table .column-league { width: 200px; }
            .fdm-verification-table .column-year { width: 60px; text-align: center; }
            .fdm-verification-table .column-type { width: 120px; }
            .fdm-verification-table .column-actions { width: 200px; }
            .fdm-verification-table .check-column { width: 30px; }

            .fdm-type-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 500;
                text-transform: uppercase;
            }
            .fdm-type-plays { background: #e7f5ff; color: #0969da; }
            .fdm-type-teams { background: #dafbe1; color: #1a7f37; }
            .fdm-type-standings { background: #fff8c5; color: #9a6700; }
            .fdm-type-lineups { background: #ffebe9; color: #cf222e; }
            .fdm-type-commentary { background: #f6f8fa; color: #57606a; }
            .fdm-type-key_events { background: #fbefff; color: #8250df; }
            .fdm-type-team_stats { background: #fff1e5; color: #bc4c00; }
            .fdm-type-player_stats { background: #eaeef2; color: #24292f; }

            .fdm-check-url {
                word-break: break-all;
                font-family: monospace;
                font-size: 11px;
            }
            .fdm-copy-url {
                margin-left: 5px;
                color: #2271b1;
                cursor: pointer;
            }

            .fdm-no-items {
                padding: 40px;
                text-align: center;
                background: #fff;
                border: 1px solid #ccd0d4;
            }

            tr.fdm-row-updated {
                background-color: #d4edda !important;
                transition: background-color 0.5s;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
            var nonce = '<?php echo wp_create_nonce( 'fdm_verification_nonce' ); ?>';

            // Single item actions
            $('.fdm-action-exists, .fdm-action-missing, .fdm-action-skip').on('click', function() {
                var button = $(this);
                var id = button.data('id');
                var status = 'verified_exists';

                if (button.hasClass('fdm-action-missing')) status = 'verified_missing';
                if (button.hasClass('fdm-action-skip')) status = 'skipped';

                button.prop('disabled', true).text('...');

                $.post(ajaxurl, {
                    action: 'fdm_update_verification',
                    id: id,
                    status: status,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        button.closest('tr').addClass('fdm-row-updated').fadeOut(500, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        button.prop('disabled', false).text(status === 'verified_exists' ? 'Exists' : (status === 'verified_missing' ? 'Missing' : 'Skip'));
                    }
                });
            });

            // Bulk actions
            $('#fdm-select-all').on('click', function() {
                $('.fdm-item-check').prop('checked', true);
            });

            $('#fdm-select-none').on('click', function() {
                $('.fdm-item-check').prop('checked', false);
            });

            $('#fdm-check-all').on('change', function() {
                $('.fdm-item-check').prop('checked', $(this).is(':checked'));
            });

            $('#fdm-bulk-exists, #fdm-bulk-missing, #fdm-bulk-skip').on('click', function() {
                var status = 'verified_exists';
                if ($(this).attr('id') === 'fdm-bulk-missing') status = 'verified_missing';
                if ($(this).attr('id') === 'fdm-bulk-skip') status = 'skipped';

                var ids = [];
                $('.fdm-item-check:checked').each(function() {
                    ids.push($(this).val());
                });

                if (ids.length === 0) {
                    alert('Please select items first');
                    return;
                }

                if (!confirm('Update ' + ids.length + ' items to "' + status + '"?')) {
                    return;
                }

                $(this).prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'fdm_bulk_update_verification',
                    ids: ids,
                    status: status,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                });
            });

            // Copy URL
            $('.fdm-copy-url').on('click', function() {
                var url = $(this).data('url');
                navigator.clipboard.writeText(url).then(function() {
                    alert('URL copied!');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Truncate URL for display
     */
    private function truncate_url( $url ) {
        if ( strlen( $url ) > 80 ) {
            return substr( $url, 0, 40 ) . '...' . substr( $url, -35 );
        }
        return $url;
    }

    /**
     * AJAX handler for updating verification status
     */
    public function ajax_update_verification() {
        check_ajax_referer( 'fdm_verification_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $id = intval( $_POST['id'] ?? 0 );
        $status = sanitize_text_field( $_POST['status'] ?? '' );

        if ( ! $id || ! $status ) {
            wp_send_json_error( 'Invalid parameters' );
        }

        $result = $this->verification->update_status( $id, $status );

        if ( $result ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( 'Update failed' );
        }
    }

    /**
     * AJAX handler for bulk updates
     */
    public function ajax_bulk_update() {
        check_ajax_referer( 'fdm_verification_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $ids = array_map( 'intval', $_POST['ids'] ?? [] );
        $status = sanitize_text_field( $_POST['status'] ?? '' );

        if ( empty( $ids ) || ! $status ) {
            wp_send_json_error( 'Invalid parameters' );
        }

        $updated = $this->verification->bulk_update_status( $ids, $status );

        wp_send_json_success( [ 'updated' => $updated ] );
    }
}

// Initialize
new FDM_Admin_Manual_Verification();
