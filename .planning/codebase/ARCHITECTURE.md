# Architecture

**Analysis Date:** 2026-01-28

## Pattern Overview

**Overall:** Plugin-Based Data Pipeline with CLI Tools

**Key Characteristics:**
- WordPress as data management platform (not traditional CMS)
- Multiple databases (WordPress, footyforums_data, e_db)
- Scheduled job queue for data ingestion
- Dual execution paths: WordPress admin UI + CLI tools

## Layers

**Entry Point Layer:**
- Purpose: Bootstrap plugins and handle requests
- Contains: Plugin main files, CLI entry points
- Key files: `football-data-manager.php`, `app/tools/canon/*.php`
- Used by: WordPress, WP-CLI, standalone CLI

**Scheduler/Cron Layer:**
- Purpose: Manage job timing and execution
- Contains: Cron hooks, job dispatcher, scheduler
- Key files: `includes/class-fdm-scheduler.php`, `includes/cron-manager.php`, `includes/ingest/ingest-runner.php`
- Depends on: Service layer
- Used by: WordPress cron system

**Service Layer:**
- Purpose: Core business logic for data operations
- Contains: Datasource engines, updaters, sync logic
- Key files: `includes/e_datasource_v2.php` (6,305 lines), `includes/class-fdm-daily-updater.php`, `includes/class-fdm-e-master-datasource.php`
- Depends on: Data access layer
- Used by: Scheduler, CLI commands, admin pages

**Data Access Layer:**
- Purpose: Database connection pooling and table management
- Contains: Connection helpers, table utilities
- Key files: `includes/db-helper.php`
- Depends on: MySQL, wpdb
- Used by: Service layer

**Admin UI Layer:**
- Purpose: WordPress dashboard interface
- Contains: Admin pages, status displays
- Key files: `includes/admin/admin-menu.php`, `includes/admin/ingest-jobs-page.php`, `includes/admin/class-fdm-admin-data-status.php`
- Depends on: Service layer
- Used by: WordPress admin

**API/Command Layer:**
- Purpose: Programmatic interfaces
- Contains: WP-CLI commands, AJAX handlers
- Key files: `includes/wp-cli-commands.php`, `includes/api-bridge.php`
- Depends on: Service layer
- Used by: CLI users, admin UI

## Data Flow

**Daily Sync (Scheduled):**

1. WordPress cron fires `fdm_daily_global_sync` at 02:00 UTC
2. `FDM_Scheduler::run_daily_job()` instantiates updater
3. `FDM_Daily_Updater::run_daily_sync()` fetches Scorepanel API
4. Service layer processes results via `e_datasource_v2.php`
5. Updates `footyforums_data.e_matches` table
6. Triggers downstream updates (stats, player data)

**Ingest Job Processing:**

1. `fdm_ingest_cron_tick` runs every 5 minutes
2. `fdm_ingest_run_due_once()` polls `ingest_jobs` table
3. Atomically leases job with 600-second timeout
4. Dispatches to handler (e_sync_leagues, e_sync_clubs, etc.)
5. Updates job status to 'completed' or 'failed'
6. Creates run record in `ingest_job_runs`

**WP-CLI Command:**

1. User runs `wp footy e_sync_leagues`
2. WordPress loads, WP-CLI dispatches command
3. `wp_cli_commands.php` invokes service method
4. `FDM_E_Datasource_V2::e_datasource_sync_leagues()` executes
5. Results formatted as WP-CLI table output

**State Management:**
- Database-driven: All state in `ingest_jobs` and `ingest_job_runs` tables
- Lease-based locking: Atomic UPDATE for job claims
- No persistent in-memory state

## Key Abstractions

**FDM_E_Datasource_V2:**
- Purpose: Central ESPN data operations engine
- Location: `includes/e_datasource_v2.php`
- Pattern: Static methods (stateless, ~40+ methods)
- Config: 218 leagues hardcoded with priority/region/tier

**FDM_Daily_Updater:**
- Purpose: Daily sync via Scorepanel API
- Location: `includes/class-fdm-daily-updater.php`
- Pattern: Instance-based with private DB connection

**FDM_E_Master_Datasource:**
- Purpose: Historical backfill engine (25 years of data)
- Location: `includes/class-fdm-e-master-datasource.php`
- Pattern: Instance-based with MySQLi connection

**Ingest Job:**
- Purpose: Queued work unit with lease management
- Location: `ingest_jobs` table, `includes/ingest/ingest-runner.php`
- Pattern: Database-backed job queue with atomic leasing

## Entry Points

**WordPress Plugin Init:**
- Location: `app/public/wp-content/plugins/football-data-manager/football-data-manager.php`
- Triggers: WordPress activation, every page load
- Responsibilities: Register hooks, load includes, initialize scheduler

**5-Minute Cron:**
- Hook: `fdm_ingest_cron_tick`
- Location: `includes/ingest/ingest-runner.php`
- Responsibilities: Lease and execute one pending job

**Daily Scheduler:**
- Hook: `fdm_daily_global_sync` (02:00 UTC)
- Location: `includes/class-fdm-scheduler.php`
- Responsibilities: Trigger full daily sync

**WP-CLI:**
- Namespace: `wp footy`
- Location: `includes/wp-cli-commands.php`
- Commands: e_sync_leagues, e_sync_clubs, e_sync_fixtures, e_sync_results, etc.

**CLI Tools:**
- Location: `app/tools/canon/`
- Scripts: `run_daily_update.php`, `run_espn_backfill.php`, `e_contract_test.php`
- Responsibilities: Bootstrap WordPress, invoke service classes

## Error Handling

**Strategy:** Log errors, continue processing where possible

**Patterns:**
- Database errors logged via `error_log()` and `fdm_log_datasource_error()`
- API failures logged with context (endpoint, response code)
- Job failures recorded in `ingest_job_runs` with error details
- Stale jobs recovered (running > 10 minutes reverts to pending)

## Cross-Cutting Concerns

**Logging:**
- WordPress debug log via `error_log()`
- Custom `fdm_log_datasource_error()` to `datasource_errors` table
- Custom `fdm_log_datasource_info()` to `datasource_log` table

**Validation:**
- ABSPATH check at file start for security
- Nonce verification in AJAX handlers (`api-bridge.php`)
- Capability checks (`manage_options`) for admin pages

**Database Connections:**
- Connection pooling via global singleton (`$GLOBALS['footyforums_db']`)
- Fallback chain: constant > option > WordPress DB
- Separate MySQLi connections for e_db legacy access

---

*Architecture analysis: 2026-01-28*
*Update when major patterns change*
