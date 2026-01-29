# Codebase Concerns

**Analysis Date:** 2026-01-28

## Tech Debt

**Hardcoded Developer Paths:**
- Issue: Absolute paths to developer's machine hardcoded in database connection code
- Files: `includes/class-fdm-daily-updater.php` (line ~30), `includes/class-fdm-e-master-datasource.php` (line ~30)
- Why: Rapid development with Local by Flywheel
- Impact: Code cannot run on any other machine without modification
- Fix approach: Move paths to wp-config.php constants or environment variables

**Giant Monolithic File:**
- Issue: Single file contains 6,305 lines of code
- File: `includes/e_datasource_v2.php`
- Why: Incremental feature additions without refactoring
- Impact: Hard to navigate, test, and maintain
- Fix approach: Extract into separate class files by domain (leagues, clubs, fixtures, players)

**Hardcoded League Configuration:**
- Issue: 218 leagues hardcoded in PHP array
- File: `includes/e_datasource_v2.php` (lines 1-500)
- Why: Started with a few leagues, grew organically
- Impact: Config changes require code deployment
- Fix approach: Move to database table or JSON configuration file

**Dual Database Connection Patterns:**
- Issue: Two different patterns for connecting to external database
- Files: `includes/db-helper.php` (wpdb), `class-fdm-daily-updater.php` (MySQLi)
- Why: Different requirements evolved over time
- Impact: Inconsistent error handling, harder to maintain
- Fix approach: Consolidate on single pattern (wpdb with external connection)

## Known Bugs

**No Known Runtime Bugs Documented**
- Symptoms: N/A
- The codebase appears functional but lacks formal bug tracking

## Security Considerations

**Default Credentials in Fallback Logic:**
- Risk: Hardcoded 'root' password as fallback in database connection
- Files: `includes/class-fdm-daily-updater.php` (line ~36), `includes/class-fdm-e-master-datasource.php` (line ~36)
- Current mitigation: Only runs in local development
- Recommendations: Remove default credentials, fail explicitly if not configured

**Missing Environment Configuration:**
- Risk: Database credentials in wp-config.php, no .env pattern
- Files: `app/public/wp-config.php`
- Current mitigation: Local-only development
- Recommendations: Add .env support for production deployment

**Debug Mode Enabled:**
- Risk: WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY all true
- File: `app/public/wp-config.php`
- Current mitigation: Local development only
- Recommendations: Ensure disabled in production deployment

## Performance Bottlenecks

**Cron Overlap Potential:**
- Problem: 5-minute cron with 600-second (10-minute) lease could overlap
- File: `football-data-manager.php` (line ~47)
- Measurement: Not measured, potential issue under high load
- Cause: Lease duration longer than cron interval
- Improvement path: Reduce lease time or increase cron interval

**Full Table Scans:**
- Problem: Queries without LIMIT on potentially large tables
- Files: Multiple locations in `football-data-manager.php`
- Measurement: Not measured
- Cause: Development-time assumptions about data size
- Improvement path: Add pagination/limits to all queries

## Fragile Areas

**Database Connection Logic:**
- File: `includes/db-helper.php`, `class-fdm-daily-updater.php`
- Why fragile: Multiple fallback paths, my.cnf parsing, socket detection
- Common failures: Connection fails silently, wrong database selected
- Safe modification: Test all fallback paths before changes
- Test coverage: None

**Ingest Job Leasing:**
- File: `includes/ingest/ingest-runner.php`
- Why fragile: Atomic UPDATE with time comparisons
- Common failures: Stale jobs, duplicate execution
- Safe modification: Understand lease recovery logic before changes
- Test coverage: None (manual verification only)

## Scaling Limits

**Local Development Only:**
- Current capacity: Single developer, single machine
- Limit: Cannot scale beyond development environment
- Symptoms at limit: Hardcoded paths fail on other machines
- Scaling path: Extract configuration, add deployment scripts

**MySQL Connection Limits:**
- Current capacity: Local by Flywheel default limits
- Limit: Concurrent connections limited by MySQL config
- Symptoms at limit: "Too many connections" errors
- Scaling path: Connection pooling, query optimization

## Dependencies at Risk

**Local by Flywheel:**
- Risk: Development environment tied to specific tool
- Impact: Difficult to onboard developers without Local
- Migration plan: Document generic LAMP/LEMP setup as alternative

**ESPN API (Public Endpoints):**
- Risk: Undocumented public API, no SLA, could change without notice
- Impact: Data ingestion breaks if API changes
- Migration plan: Contract tests detect changes; alternative providers documented in `app/docs/providers/`

## Missing Critical Features

**Production Deployment:**
- Problem: No deployment configuration for production
- Current workaround: Development only
- Blocks: Cannot deploy to production server
- Implementation complexity: Medium (Nginx config, MySQL setup, secrets management)

**Automated Testing:**
- Problem: No PHPUnit or automated test suite
- Current workaround: Manual verification, contract tests
- Blocks: Cannot verify changes don't break functionality
- Implementation complexity: Medium (PHPUnit setup, mock infrastructure)

**Environment Configuration:**
- Problem: No .env file or environment variable pattern
- Current workaround: Hardcoded values in wp-config.php
- Blocks: Multi-environment deployment
- Implementation complexity: Low (add .env loader, update configs)

## Test Coverage Gaps

**Core Datasource Logic:**
- What's not tested: `e_datasource_v2.php` methods (6,305 lines)
- Risk: Regressions in data processing undetected
- Priority: High
- Difficulty to test: High (large file, many dependencies)

**Database Helpers:**
- What's not tested: `db-helper.php` connection logic
- Risk: Database connection failures not caught
- Priority: High
- Difficulty to test: Medium (need database fixtures)

**Job Processing:**
- What's not tested: `ingest-runner.php` lease logic
- Risk: Concurrent job issues, stale job recovery failures
- Priority: Medium
- Difficulty to test: Medium (need time mocking)

---

*Concerns audit: 2026-01-28*
*Update as issues are fixed or new ones discovered*
