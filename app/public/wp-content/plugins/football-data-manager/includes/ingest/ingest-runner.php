<?php
/**
 * Ingest Job Runner
 * Runs ingest jobs without WP-CLI dependencies
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once FDM_PLUGIN_DIR . 'includes/db-helper.php';
require_once FDM_PLUGIN_DIR . 'includes/e_datasource_v2.php';

/**
 * Run one due ingest job
 * 
 * @param int $lease_seconds Lease duration in seconds (default 600)
 * @return array Result array with 'ran', 'message', 'job_id', 'run_id'
 */
function fdm_ingest_run_due_once( $lease_seconds = 600 ) {
    $db = fdm_get_footyforums_db();
    if ( ! $db ) {
        return [
            'ran' => false,
            'message' => 'DB connection failed',
            'job_id' => null,
            'run_id' => null
        ];
    }

    // Recover stale running jobs
    $db->query(
        "UPDATE ingest_jobs
         SET status='pending', lease_expires_at=NULL
         WHERE status='running' AND lease_expires_at IS NOT NULL AND lease_expires_at < UTC_TIMESTAMP()"
    );

    // Find a due job that is leaseable and supported
    $job = $db->get_row(
        "SELECT * FROM ingest_jobs
         WHERE status='pending'
         AND (next_run_at IS NULL OR next_run_at <= UTC_TIMESTAMP())
         AND (lease_expires_at IS NULL OR lease_expires_at <= UTC_TIMESTAMP())
         AND job_type IN ('e_sync_leagues','e_sync_clubs','e_sync_fixtures')
         ORDER BY priority ASC, id ASC
         LIMIT 1",
        ARRAY_A
    );

    if ( ! $job ) {
        return [
            'ran' => false,
            'message' => 'No jobs due',
            'job_id' => null,
            'run_id' => null
        ];
    }

    // Atomically lease the job
    $updated = $db->query(
        $db->prepare(
            "UPDATE ingest_jobs
             SET status='running',
                 lease_expires_at=UTC_TIMESTAMP() + INTERVAL %d SECOND,
                 attempts=attempts+1,
                 last_run_at=UTC_TIMESTAMP()
             WHERE id=%d AND status='pending'",
            $lease_seconds,
            $job['id']
        )
    );

    if ( $updated === 0 ) {
        return [
            'ran' => false,
            'message' => 'Job was claimed by another process',
            'job_id' => null,
            'run_id' => null
        ];
    }

    // Reload job to get updated attempts count
    $job = $db->get_row(
        $db->prepare( "SELECT * FROM ingest_jobs WHERE id=%d", $job['id'] ),
        ARRAY_A
    );

    // Create run record at start
    $db->insert( 'ingest_job_runs', [ 'job_id' => $job['id'] ] );
    $run_id = $db->insert_id;

    $start_time = microtime( true );
    $ok = true;
    $error = '';
    $skipped = false;
    $not_implemented = false;
    $run_finalized = false;

    try {
        try {
            if ( $job['provider'] !== 'espn' ) {
                $skipped = true;
                $error = 'Unsupported provider';
            } elseif ( $job['job_type'] === 'e_sync_leagues' ) {
                FDM_E_Datasource_V2::e_datasource_sync_leagues([]);
            } elseif ( $job['job_type'] === 'e_sync_clubs' ) {
                // Sync clubs for all supported leagues
                $disabled_leagues = [
                    'uefa.europa_conference',
                ];
                
                $league_codes = FDM_E_Datasource_V2::get_supported_league_codes();
                $success_count = 0;
                $errors = array();
                
                foreach ( $league_codes as $league_code ) {
                    if ( in_array( $league_code, $disabled_leagues, true ) ) {
                        $errors[] = $league_code . ': skipped (disabled)';
                        continue;
                    }
                    
                    try {
                        $result = FDM_E_Datasource_V2::e_datasource_sync_clubs_for_league( $league_code );
                        if ( ! is_array( $result ) || $result['count_errors'] > 0 ) {
                            $error_msg = is_array( $result ) && ! empty( $result['errors'] ) 
                                ? implode( '; ', $result['errors'] ) 
                                : 'Unknown error syncing clubs for ' . $league_code;
                            $errors[] = $league_code . ': ' . $error_msg;
                            continue;
                        }
                        $success_count++;
                    } catch ( Exception $e ) {
                        $errors[] = $league_code . ': ' . $e->getMessage();
                        continue;
                    }
                }
                
                if ( $success_count === 0 ) {
                    throw new Exception( 'Club sync failed for leagues: ' . implode( '; ', $errors ) );
                }
            } elseif ( $job['job_type'] === 'e_sync_fixtures' ) {
                // Sync fixtures for all supported leagues
                $disabled_leagues = [
                    'uefa.europa_conference',
                ];
                
                $league_codes = FDM_E_Datasource_V2::get_supported_league_codes();
                $success_count = 0;
                $errors = array();
                $not_implemented_count = 0;
                $datasource = new FDM_E_Datasource_V2();
                
                foreach ( $league_codes as $league_code ) {
                    if ( in_array( $league_code, $disabled_leagues, true ) ) {
                        $errors[] = $league_code . ': skipped (disabled)';
                        continue;
                    }
                    
                    try {
                        $datasource->sync_fixtures_for_league( $league_code );
                        $success_count++;
                    } catch ( Exception $e ) {
                        $error_msg = $e->getMessage();
                        // Check if this is a "not implemented" exception
                        if ( stripos( $error_msg, 'Fixtures sync not implemented' ) !== false || 
                             stripos( $error_msg, 'not implemented' ) !== false ) {
                            $not_implemented_count++;
                        } else {
                            $errors[] = $league_code . ': ' . $error_msg;
                        }
                        continue;
                    }
                }
                
                // If all failures were "not implemented", treat as skipped
                if ( $success_count === 0 && $not_implemented_count > 0 && empty( $errors ) ) {
                    $not_implemented = true;
                    $error = 'Skipped: fixtures sync not implemented';
                } elseif ( $success_count === 0 ) {
                    throw new Exception( 'Fixture sync failed for leagues: ' . implode( '; ', $errors ) );
                }
            } else {
                $skipped = true;
                $error = 'Unsupported job_type';
            }
        } catch ( Exception $e ) {
            $ok = false;
            $error = $e->getMessage();
        }

        $end_time = microtime( true );
        $runtime_ms = (int) round( ( $end_time - $start_time ) * 1000 );

        // Finalize run record - guaranteed execution
        // Handle skipped jobs (unsupported provider/job_type or not implemented)
        if ( $skipped || $not_implemented ) {
            // Update run record as skipped
            $db->update(
                'ingest_job_runs',
                [
                    'finished_at' => gmdate( 'Y-m-d H:i:s' ),
                    'exit_status' => 'skipped',
                    'runtime_ms' => $runtime_ms,
                    'error_text' => $not_implemented ? 'Skipped: fixtures sync not implemented' : $error
                ],
                [ 'id' => $run_id ]
            );
            $run_finalized = true;
            
            // Reschedule job for later (6 hours for not implemented, 1 hour for other skips)
            if ( $not_implemented ) {
                // For "not implemented", reschedule 6 hours later and decrement attempts
                $db->query(
                    $db->prepare(
                        "UPDATE ingest_jobs
                         SET status='pending',
                             next_run_at=UTC_TIMESTAMP() + INTERVAL 6 HOUR,
                             lease_expires_at=NULL,
                             last_error=NULL,
                             attempts=GREATEST(0, attempts-1)
                         WHERE id=%d",
                        $job['id']
                    )
                );
            } else {
                // For other skips, reschedule 1 hour later, don't decrement attempts
                $db->query(
                    $db->prepare(
                        "UPDATE ingest_jobs
                         SET status='pending',
                             next_run_at=UTC_TIMESTAMP() + INTERVAL 3600 SECOND,
                             lease_expires_at=NULL,
                             last_error=NULL
                         WHERE id=%d",
                        $job['id']
                    )
                );
            }
        } else {
            // Update run record for success/failure
            $db->update(
                'ingest_job_runs',
                [
                    'finished_at' => gmdate( 'Y-m-d H:i:s' ),
                    'exit_status' => $ok ? 'success' : 'failed',
                    'runtime_ms' => $runtime_ms,
                    'error_text' => $ok ? null : $error
                ],
                [ 'id' => $run_id ]
            );
            $run_finalized = true;

            // Handle job completion or rescheduling
            if ( $ok ) {
                // Check for recurring schedule
                $schedule_rule = $job['schedule_rule'] ?? null;
                $should_reschedule = false;
                $interval_seconds = null;

                if ( $schedule_rule && strpos( $schedule_rule, 'interval:' ) === 0 ) {
                    $interval_str = substr( $schedule_rule, 9 );
                    $interval_seconds = (int) $interval_str;
                    if ( $interval_seconds > 0 ) {
                        $should_reschedule = true;
                    }
                }

                if ( $should_reschedule ) {
                    // Reschedule the job
                    $db->query(
                        $db->prepare(
                            "UPDATE ingest_jobs
                             SET status='pending',
                                 next_run_at=UTC_TIMESTAMP() + INTERVAL %d SECOND,
                                 lease_expires_at=NULL
                             WHERE id=%d",
                            $interval_seconds,
                            $job['id']
                        )
                    );
                } else {
                    // Mark as success (one-off job)
                    $db->update(
                        'ingest_jobs',
                        [
                            'status' => 'success',
                            'lease_expires_at' => null
                        ],
                        [ 'id' => $job['id'] ]
                    );
                }
            } else {
                // Update job record on failure
                $db->update(
                    'ingest_jobs',
                    [
                        'status' => 'failed',
                        'lease_expires_at' => null,
                        'last_error' => $error
                    ],
                    [ 'id' => $job['id'] ]
                );
            }
        }
    } finally {
        // Safety net: ensure run record is always finalized
        if ( $run_id ) {
            try {
                // Check if run was finalized
                $check = $db->get_var( $db->prepare( "SELECT finished_at FROM ingest_job_runs WHERE id=%d", $run_id ) );
                if ( empty( $check ) ) {
                    $final_runtime = (int) round( ( microtime( true ) - $start_time ) * 1000 );
                    $db->update(
                        'ingest_job_runs',
                        [
                            'finished_at' => gmdate( 'Y-m-d H:i:s' ),
                            'exit_status' => 'failed',
                            'runtime_ms' => $final_runtime,
                            'error_text' => 'Run finalised by safety net'
                        ],
                        [ 'id' => $run_id ]
                    );
                }
            } catch ( Exception $e ) {
                // If safety check fails, try one more time with minimal operations
                try {
                    $final_runtime = (int) round( ( microtime( true ) - $start_time ) * 1000 );
                    $db->query(
                        $db->prepare(
                            "UPDATE ingest_job_runs
                             SET finished_at=UTC_TIMESTAMP(),
                                 exit_status='failed',
                                 runtime_ms=%d,
                                 error_text='Run finalised by safety net'
                             WHERE id=%d AND finished_at IS NULL",
                            $final_runtime,
                            $run_id
                        )
                    );
                } catch ( Exception $e2 ) {
                    // Last resort - log but don't throw
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'fdm_ingest_run_due_once: Failed to finalize run ' . $run_id . ': ' . $e2->getMessage() );
                    }
                }
            }
        }
    }

    // Build return value
    if ( $skipped || $not_implemented ) {
        return [
            'ran' => true,
            'message' => $not_implemented ? 'Job skipped (not implemented)' : 'Job skipped (unsupported)',
            'job_id' => $job['id'],
            'run_id' => $run_id
        ];
    } elseif ( $ok ) {
        $schedule_rule = $job['schedule_rule'] ?? null;
        $should_reschedule = false;
        if ( $schedule_rule && strpos( $schedule_rule, 'interval:' ) === 0 ) {
            $interval_str = substr( $schedule_rule, 9 );
            $interval_seconds = (int) $interval_str;
            if ( $interval_seconds > 0 ) {
                $should_reschedule = true;
            }
        }
        return [
            'ran' => true,
            'message' => $should_reschedule ? 'Job completed and rescheduled' : 'Job completed',
            'job_id' => $job['id'],
            'run_id' => $run_id
        ];
    } else {
        return [
            'ran' => true,
            'message' => 'Job failed: ' . $error,
            'job_id' => $job['id'],
            'run_id' => $run_id
        ];
    }
}
