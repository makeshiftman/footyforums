<?php
/**
 * Admin Menu Registration
 * Registers WordPress admin pages for Football Data Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register admin pages
 */
function fdm_register_admin_pages() {
    add_menu_page(
        'Football Data',
        'Football Data',
        'manage_options',
        'fdm-football-data',
        'fdm_render_ingest_jobs_page',
        'dashicons-chart-area',
        60
    );
}

/**
 * Render the ingest jobs page
 */
function fdm_render_ingest_jobs_page() {
    require __DIR__ . '/ingest-jobs-page.php';
}

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
}

/**
 * Handle seed ESPN jobs POST (runs before any output)
 */
function fdm_handle_seed_espn_jobs_post() {
    // Only process on our admin page
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fdm-football-data' ) {
        return;
    }
    
    if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'fdm_seed_espn_jobs' ) {
        return;
    }
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=fdm-football-data&fdm_msg=deny' ) );
        exit;
    }
    
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'fdm_seed_espn_jobs' ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=fdm-football-data&fdm_msg=deny' ) );
        exit;
    }
    
    // Get database connection
    require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
    $db = fdm_get_footyforums_db();
    
    if ( ! $db ) {
        wp_safe_redirect( admin_url( 'admin.php?page=fdm-football-data&fdm_msg=deny' ) );
        exit;
    }
    
    // Call the seed function
    fdm_seed_espn_jobs_db( $db );
    
    wp_safe_redirect( admin_url( 'admin.php?page=fdm-football-data&fdm_msg=seeded' ) );
    exit;
}

add_action( 'admin_menu', 'fdm_register_admin_pages' );
add_action( 'admin_init', 'fdm_handle_seed_espn_jobs_post' );
