# External Integrations

**Analysis Date:** 2026-01-28

## APIs & External Services

**ESPN API (Primary Data Source):**
- Site API: `https://site.api.espn.com/apis/site/v2/`
  - SDK/Client: WordPress HTTP API (`wp_remote_get()`) - `includes/e_datasource_v2.php`
  - Auth: None required (public endpoints)
  - Endpoints used:
    - `/sports/soccer/{league_code}/teams` - Team rosters
    - `/sports/soccer/{league_code}/scoreboard` - Live scores
    - `/sports/soccer/{league_code}/scoreboard?dates={date}` - Historical scores
    - `/sports/soccer/{league_code}/teams/{team_id}/schedule` - Team schedules
    - `/sports/soccer/summary?event={event_id}` - Match details

- Core API: `https://sports.core.api.espn.com/v2/`
  - Endpoints:
    - `/sports/soccer/leagues/{league_code}/seasons/{season}/types/{type_id}/events` - Season fixtures
    - `/sports/soccer/leagues/{league_code}/seasons/{season}/types/1/leaders` - Season leaders

- Scorepanel API: `https://site.api.espn.com/apis/site/v2/sports/soccer/scorepanel`
  - Used by: `class-fdm-daily-updater.php` for daily sync

**Email/SMS:**
- Not detected

**External APIs:**
- No other external APIs detected

## Data Storage

**Databases:**
- WordPress Database (`local`) - WordPress content via wpdb
  - Connection: `DB_HOST`, `DB_USER`, `DB_PASSWORD` in `wp-config.php`
  - Client: WordPress wpdb class

- External Data Database (`footyforums_data`) - Canonical football data
  - Connection: `FOOTYFORUMS_DB_*` constants in `wp-config.php`
  - Client: Custom wpdb instance (`includes/db-helper.php`)
  - Tables: `ingest_jobs`, `ingest_job_runs`, competitions, clubs, players

- Legacy ESPN Database (`e_db`) - Historical ESPN data
  - Connection: MySQLi via my.cnf (`class-fdm-e-master-datasource.php`)
  - Seasonal schemas: `e_0102` through `e_2425`

**File Storage:**
- Local filesystem only - No cloud storage integration

**Caching:**
- None detected (database queries only)

## Authentication & Identity

**Auth Provider:**
- WordPress authentication (admin functions only)
- Capability check: `manage_options` for admin pages (`includes/admin/admin-menu.php`)

**OAuth Integrations:**
- Not applicable

## Monitoring & Observability

**Error Tracking:**
- WordPress debug logging (`WP_DEBUG_LOG = true`)
- Custom error logging via `fdm_log_datasource_error()` (`includes/db-helper.php`)
- Errors stored in `datasource_errors` table

**Analytics:**
- None detected

**Logs:**
- `error_log()` calls throughout plugin code
- WordPress debug.log for PHP errors

## CI/CD & Deployment

**Hosting:**
- Local by Flywheel (development only)
- No production deployment configured

**CI Pipeline:**
- None detected

## Environment Configuration

**Development:**
- Required: Local by Flywheel installation
- Database config: `FOOTYFORUMS_DB_NAME`, `FOOTYFORUMS_DB_USER`, `FOOTYFORUMS_DB_PASSWORD`, `FOOTYFORUMS_DB_HOST`
- Secrets location: `wp-config.php` (committed with local dev values)

**Staging:**
- Not configured

**Production:**
- Not configured

## Webhooks & Callbacks

**Incoming:**
- None

**Outgoing:**
- None

## Data Provider Documentation

Provider specs documented in `app/docs/providers/`:
- `e/` - ESPN (primary, fully documented)
- `s/` - Sofascore
- `w/` - Whoscored
- `u/` - Understat
- `t/` - Transfermarkt
- `sf/` - Sofifa
- `sb/` - StatsBomb
- `wp/` - Wikipedia
- `wd/` - Wikidata
- `fb/` - FBref

Each provider has: `ENDPOINTS.md`, `CONTRACT_TESTS.md`, `KNOWN_LIMITATIONS.md`, `CHANGELOG.md`

---

*Integration audit: 2026-01-28*
*Update when adding/removing external services*
