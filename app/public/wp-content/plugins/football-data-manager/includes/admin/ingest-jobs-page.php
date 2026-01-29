<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get database connection (needed for POST handler)
require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
$db = fdm_get_footyforums_db();

/**
 * Seed ESPN ingest jobs in the database
 * Idempotent: ensures exactly one row per (provider, job_type) for the four ESPN job types
 * 
 * @param wpdb $db Database connection
 * @return void
 */
if ( ! function_exists( 'fdm_seed_espn_jobs_db' ) ) {
    function fdm_seed_espn_jobs_db( $db ) {
    // Define jobs to seed
    $jobs = array(
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_results',
            'schedule_rule' => 'interval:900',
            'priority' => 5
        ),
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_leagues',
            'schedule_rule' => 'interval:86400',
            'priority' => 10
        ),
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_clubs',
            'schedule_rule' => 'interval:86400',
            'priority' => 20
        ),
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_fixtures',
            'schedule_rule' => 'interval:86400',
            'priority' => 30
        )
    );

    foreach ( $jobs as $job_def ) {
        // Find all existing rows with this provider+job_type, ordered by id asc
        $all_existing = $db->get_results(
            $db->prepare(
                "SELECT id, status FROM ingest_jobs WHERE provider=%s AND job_type=%s ORDER BY id ASC",
                $job_def['provider'],
                $job_def['job_type']
            ),
            ARRAY_A
        );

        if ( ! empty( $all_existing ) ) {
            // Keep the lowest id as canonical
            $canonical = $all_existing[0];
            $canonical_id = (int) $canonical['id'];
            $canonical_status = $canonical['status'];
            
            // Delete all other rows
            $to_delete = array();
            foreach ( $all_existing as $row ) {
                if ( (int) $row['id'] !== $canonical_id ) {
                    $to_delete[] = (int) $row['id'];
                }
            }
            
            if ( ! empty( $to_delete ) ) {
                $ids_escaped = array_map( 'intval', $to_delete );
                $ids_string = implode( ',', $ids_escaped );
                $db->query( "DELETE FROM ingest_jobs WHERE id IN ($ids_string)" );
            }
            
            // Prepare update data
            $update_data = array(
                'schedule_rule' => $job_def['schedule_rule'],
                'priority' => $job_def['priority'],
                'lease_expires_at' => null,
                'last_error' => null
            );
            
            // Preserve 'paused' status, otherwise set to 'pending'
            if ( $canonical_status !== 'paused' ) {
                $update_data['status'] = 'pending';
                // Use UTC_TIMESTAMP() in SQL for next_run_at
                $db->query(
                    $db->prepare(
                        "UPDATE ingest_jobs SET next_run_at=UTC_TIMESTAMP() WHERE id=%d",
                        $canonical_id
                    )
                );
            }
            
            // Update the canonical row
            $db->update(
                'ingest_jobs',
                $update_data,
                array( 'id' => $canonical_id )
            );
        } else {
            // Insert new job
            $db->query(
                $db->prepare(
                    "INSERT INTO ingest_jobs (provider, job_type, status, schedule_rule, priority, next_run_at, attempts, max_attempts)
                     VALUES (%s, %s, 'pending', %s, %d, UTC_TIMESTAMP(), 0, 5)",
                    $job_def['provider'],
                    $job_def['job_type'],
                    $job_def['schedule_rule'],
                    $job_def['priority']
                )
            );
        }
    }
}

/**
 * Ingest Jobs Admin Page
 * Displays the ingest job queue from the external footyforums_data database
 */

if ( ! $db ) {
    // Define jobs to seed
    $jobs = array(
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_results',
            'schedule_rule' => 'interval:900',
            'priority' => 5
        ),
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_leagues',
            'schedule_rule' => 'interval:86400',
            'priority' => 10
        ),
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_clubs',
            'schedule_rule' => 'interval:86400',
            'priority' => 20
        ),
        array(
            'provider' => 'espn',
            'job_type' => 'e_sync_fixtures',
            'schedule_rule' => 'interval:86400',
            'priority' => 30
        )
    );

    foreach ( $jobs as $job_def ) {
        // Find all existing rows with this provider+job_type, ordered by id asc
        $all_existing = $db->get_results(
            $db->prepare(
                "SELECT id, status FROM ingest_jobs WHERE provider=%s AND job_type=%s ORDER BY id ASC",
                $job_def['provider'],
                $job_def['job_type']
            ),
            ARRAY_A
        );

        if ( ! empty( $all_existing ) ) {
            // Keep the lowest id as canonical
            $canonical = $all_existing[0];
            $canonical_id = (int) $canonical['id'];
            $canonical_status = $canonical['status'];
            
            // Delete all other rows
            $to_delete = array();
            foreach ( $all_existing as $row ) {
                if ( (int) $row['id'] !== $canonical_id ) {
                    $to_delete[] = (int) $row['id'];
                }
            }
            
            if ( ! empty( $to_delete ) ) {
                $ids_escaped = array_map( 'intval', $to_delete );
                $ids_string = implode( ',', $ids_escaped );
                $db->query( "DELETE FROM ingest_jobs WHERE id IN ($ids_string)" );
            }
            
            // Prepare update data
            $update_data = array(
                'schedule_rule' => $job_def['schedule_rule'],
                'priority' => $job_def['priority'],
                'lease_expires_at' => null,
                'last_error' => null
            );
            
            // Preserve 'paused' status, otherwise set to 'pending'
            if ( $canonical_status !== 'paused' ) {
                $update_data['status'] = 'pending';
                // Use UTC_TIMESTAMP() in SQL for next_run_at
                $db->query(
                    $db->prepare(
                        "UPDATE ingest_jobs SET next_run_at=UTC_TIMESTAMP() WHERE id=%d",
                        $canonical_id
                    )
                );
            }
            
            // Update the canonical row
            $db->update(
                'ingest_jobs',
                $update_data,
                array( 'id' => $canonical_id )
            );
        } else {
            // Insert new job
            $db->query(
                $db->prepare(
                    "INSERT INTO ingest_jobs (provider, job_type, status, schedule_rule, priority, next_run_at, attempts, max_attempts)
                     VALUES (%s, %s, 'pending', %s, %d, UTC_TIMESTAMP(), 0, 5)",
                    $job_def['provider'],
                    $job_def['job_type'],
                    $job_def['schedule_rule'],
                    $job_def['priority']
                )
            );
        }
    }
    }
}

/**
 * Ingest Jobs Admin Page
 * Displays the ingest job queue from the external footyforums_data database
 */

if ( ! $db ) {
    ?>
    <div class="wrap">
        <h1>Ingest Jobs</h1>
        <div class="notice notice-error">
            <p><strong>Error:</strong> Cannot connect to footyforums_data database. Please check your database configuration.</p>
        </div>
    </div>
    <?php
    return;
}

// Handle admin actions
if ( isset( $_GET['fdm_action'], $_GET['job_id'], $_GET['_wpnonce'] ) ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=fdm-football-data&fdm_msg=deny' ) );
        exit;
    }
    
    $action = sanitize_text_field( $_GET['fdm_action'] );
    $job_id = intval( $_GET['job_id'] );
    $nonce = sanitize_text_field( $_GET['_wpnonce'] );
    
    if ( ! is_numeric( $_GET['job_id'] ) || $job_id <= 0 ) {
        wp_safe_redirect( admin_url( 'admin.php?page=fdm-football-data&fdm_msg=deny' ) );
        exit;
    }
    
    if ( ! wp_verify_nonce( $nonce, 'fdm_ingest_job_action_' . $job_id ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=fdm-football-data&fdm_msg=deny' ) );
        exit;
    }
    
    $allowed_actions = array( 'run_now', 'pause', 'resume', 'retry' );
    if ( ! in_array( $action, $allowed_actions, true ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=fdm-football-data&fdm_msg=deny' ) );
        exit;
    }
    
    // Perform the action
    $update_result = false;
    switch ( $action ) {
        case 'run_now':
            $update_result = $db->query(
                $db->prepare(
                    "UPDATE ingest_jobs
                     SET status='pending',
                         next_run_at=UTC_TIMESTAMP(),
                         lease_expires_at=NULL,
                         last_error=NULL
                     WHERE id=%d",
                    $job_id
                )
            );
            break;
            
        case 'pause':
            $update_result = $db->query(
                $db->prepare(
                    "UPDATE ingest_jobs
                     SET status='paused',
                         lease_expires_at=NULL
                     WHERE id=%d",
                    $job_id
                )
            );
            break;
            
        case 'resume':
            $update_result = $db->query(
                $db->prepare(
                    "UPDATE ingest_jobs
                     SET status='pending',
                         next_run_at=UTC_TIMESTAMP(),
                         lease_expires_at=NULL
                     WHERE id=%d",
                    $job_id
                )
            );
            break;
            
        case 'retry':
            $update_result = $db->query(
                $db->prepare(
                    "UPDATE ingest_jobs
                     SET status='pending',
                         next_run_at=UTC_TIMESTAMP(),
                         lease_expires_at=NULL,
                         last_error=NULL
                     WHERE id=%d",
                    $job_id
                )
            );
            break;
    }
    
    // Determine redirect message based on update result
    if ( $update_result !== false && $update_result > 0 ) {
        wp_safe_redirect( admin_url( 'admin.php?page=fdm-football-data&fdm_msg=ok' ) );
    } else {
        wp_safe_redirect( admin_url( 'admin.php?page=fdm-football-data&fdm_msg=update_fail' ) );
    }
    exit;
}

// Fetch latest 100 jobs
$jobs = $db->get_results(
    "SELECT * FROM ingest_jobs ORDER BY id DESC LIMIT 100",
    ARRAY_A
);

// Fetch latest 10 runs
$runs = $db->get_results(
    "SELECT * FROM ingest_job_runs ORDER BY id DESC LIMIT 10",
    ARRAY_A
);

// Query health indicators
$pending_count = (int) $db->get_var( "SELECT COUNT(*) FROM ingest_jobs WHERE status='pending'" );
$running_count = (int) $db->get_var( "SELECT COUNT(*) FROM ingest_jobs WHERE status='running'" );
$failed_count = (int) $db->get_var( "SELECT COUNT(*) FROM ingest_jobs WHERE status='failed'" );
$stale_count = (int) $db->get_var( "SELECT COUNT(*) FROM ingest_jobs WHERE status='running' AND lease_expires_at IS NOT NULL AND lease_expires_at < UTC_TIMESTAMP()" );

?>
<div class="wrap">
    <meta http-equiv="refresh" content="15">
    <h1>Ingest Jobs</h1>
    
    <?php
    // Display admin notice based on fdm_msg parameter
    if ( isset( $_GET['fdm_msg'] ) ) {
        $msg = sanitize_text_field( $_GET['fdm_msg'] );
        if ( $msg === 'ok' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Action applied</p></div>';
        } elseif ( $msg === 'deny' ) {
            echo '<div class="notice notice-error is-dismissible"><p>Action denied</p></div>';
        } elseif ( $msg === 'update_fail' ) {
            echo '<div class="notice notice-error is-dismissible"><p>Update failed</p></div>';
        } elseif ( $msg === 'seeded' ) {
            echo '<div class="notice notice-success is-dismissible"><p>ESPN ingest jobs seeded</p></div>';
        }
    }
    ?>
    
    <table class="widefat striped" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th>Pending</th>
                <th>Running</th>
                <th>Failed</th>
                <th>Stale</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php echo esc_html( $pending_count ); ?></td>
                <td><?php echo esc_html( $running_count ); ?></td>
                <td><?php echo esc_html( $failed_count ); ?></td>
                <td><?php echo esc_html( $stale_count ); ?></td>
            </tr>
        </tbody>
    </table>
    
    <?php if ( $stale_count > 0 ) : ?>
        <div class="notice notice-warning">
            <p>There are stale running jobs. They will be recovered automatically.</p>
        </div>
    <?php endif; ?>
    
    <form method="post" style="margin-bottom: 20px;">
        <?php wp_nonce_field( 'fdm_seed_espn_jobs', '_wpnonce' ); ?>
        <input type="hidden" name="action" value="fdm_seed_espn_jobs">
        <button type="submit" class="button">Seed ESPN jobs</button>
    </form>
    
    <h2>Latest 100 Jobs</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Provider</th>
                <th>Job Type</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Season Year</th>
                <th>Competition Code</th>
                <th>Attempts</th>
                <th>Max Attempts</th>
                <th>Next Run At</th>
                <th>Last Run At</th>
                <th>Lease Expires At</th>
                <th>Last Error</th>
                <th>Schedule Rule</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $jobs ) ) : ?>
                <tr>
                    <td colspan="15">No jobs found.</td>
                </tr>
            <?php else : ?>
                <?php foreach ( $jobs as $job ) : ?>
                    <?php
                    $job_id = intval( $job['id'] ?? 0 );
                    $status = $job['status'] ?? '';
                    $nonce = wp_create_nonce( 'fdm_ingest_job_action_' . $job_id );
                    $base_url = admin_url( 'admin.php?page=fdm-football-data' );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $job['id'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $job['provider'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $job['job_type'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $job['status'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $job['priority'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $job['season_year'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $job['competition_code'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $job['attempts'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $job['max_attempts'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $job['next_run_at'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $job['last_run_at'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $job['lease_expires_at'] ?? '' ); ?></td>
                        <td><?php echo esc_html( mb_substr( $job['last_error'] ?? '', 0, 100 ) ); ?></td>
                        <td><?php echo esc_html( $job['schedule_rule'] ?? '' ); ?></td>
                        <td>
                            <?php if ( $status === 'paused' ) : ?>
                                <a href="<?php echo esc_url( $base_url . '&fdm_action=resume&job_id=' . $job_id . '&_wpnonce=' . $nonce ); ?>">Resume</a>
                            <?php else : ?>
                                <a href="<?php echo esc_url( $base_url . '&fdm_action=pause&job_id=' . $job_id . '&_wpnonce=' . $nonce ); ?>">Pause</a>
                            <?php endif; ?>
                            | <a href="<?php echo esc_url( $base_url . '&fdm_action=run_now&job_id=' . $job_id . '&_wpnonce=' . $nonce ); ?>">Run now</a>
                            <?php if ( $status === 'failed' ) : ?>
                                | <a href="<?php echo esc_url( $base_url . '&fdm_action=retry&job_id=' . $job_id . '&_wpnonce=' . $nonce ); ?>">Retry</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <h2>Latest 10 Runs</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Job ID</th>
                <th>Started At</th>
                <th>Finished At</th>
                <th>Exit Status</th>
                <th>Runtime (ms)</th>
                <th>Error Text</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $runs ) ) : ?>
                <tr>
                    <td colspan="7">No runs found.</td>
                </tr>
            <?php else : ?>
                <?php foreach ( $runs as $run ) : ?>
                    <tr>
                        <td><?php echo esc_html( $run['id'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $run['job_id'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $run['started_at'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $run['finished_at'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $run['exit_status'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $run['runtime_ms'] ?? '' ); ?></td>
                        <td><?php echo esc_html( mb_substr( $run['error_text'] ?? '', 0, 100 ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
