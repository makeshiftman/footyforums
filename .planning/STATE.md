# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-28)

**Core value:** Accurate, reconciled football data from multiple sources with clear provenance
**Current focus:** v3.0 Club Mapping Completion - Rosetta Stone approach to complete all club mappings

## Current Position

Phase: 17 of 22 (Soccer API Bootstrap)
Plan: Not started
Status: Ready to plan Phase 17
Last activity: 2026-02-03 — Created v3.0 milestone, imported 394 new ESPN clubs

Progress: ████████░░ 73% (16/22 phases across all milestones)

## Session: 2026-02-03 (Major Scrape Execution Session)

### Scrape Status
- **Started:** Sunday 2026-02-01
- **Runtime:** 40+ hours
- **Progress:** ~4% when checked (200/4752 items) — but 2024 is largest year
- **Strategy:** 2024 first (most data), then backwards to 2001
- **Memory:** Increased to 4G after OOM crash

### Issues Encountered & Fixed

1. **OOM Crash on arg.copa 2023**
   - Error: "Allowed memory size of 134217728 bytes exhausted"
   - Fix: Increased memory limit to 4G: `php -d memory_limit=4G`

2. **Prober False Positives**
   - Problem: Prober flagged `lineups_available=1` for leagues with no actual player data
   - Affected: arg.3, arg.4, nga.1 and others
   - Fix: Updated `class-fdm-availability-prober.php` to check for actual player data in `$team_roster['roster']`

3. **Skip Logic Implemented**
   - Problem: After crash, no way to resume without re-scraping
   - Solution: Season-level skip logic in `class-fdm-e-master-datasource.php`
   - Added `get_season_completion_status()` method
   - Added `--force` flag to CLI for re-scraping when needed

4. **Partial Data Cleanup**
   - Identified partial 2023 leagues: eng.4 (101/557), esp.2 (107/468), arg.copa (34)
   - Deleted all partial 2023 data to ensure clean resume
   - Verified: 181 leagues for 2024 (SKIP), 64 complete for 2023 (SKIP)

### Database Backup
- File: `e_db_backup_before_resume_20260202_185048.sql` (945MB)
- Created before resume to protect 40+ hours of work

### Data Verification (2024)
- 31,561 fixtures scraped
- 21,806 events with lineups (69%)
- 17,994 with commentary (57%)
- 30,124 with teamStats (95%)
- Lower-tier leagues genuinely don't have deep data on ESPN

### Research Completed: Alternative Data Sources
Investigated complementary data sources for v3.0:
- **FBref**: xG, xA, npxG, shot events, player match stats
- **Understat**: Shot maps with xG per shot, PPDA pressing stats
- **Sofascore**: Live ratings, match momentum
- **SoFIFA**: FIFA player attributes (pace, shooting, etc.), ratings history
- **Transfermarkt**: Player valuations, transfer history
- **soccerdata library**: Python wrapper supporting all above sources
- **Soccer API**: 82,000 players pre-mapped across FBref, Transfermarkt, Understat

### Vision: v3.0 Multi-Source Data Platform
Proposed ambitious expansion:
- Unified player/team master tables with cross-source ID mapping
- Python scraper framework using soccerdata library
- xG enrichment for all ESPN matches via FBref
- Player attributes from SoFIFA for FIFA ratings
- Transfer valuations from Transfermarkt
- WordPress admin UI for data exploration

## Rapid Progress: 2026-01-31

Completed in single session:
- Phase 9: Endpoint Research - COMPLETE (ENDPOINTS.md fully documents all 14 data types)
- Phase 10: Data Availability Testing - COMPLETE (prober checks all data types)
- Phase 11: Availability Matrix - COMPLETE (espn_availability table with 16 columns)
- Phase 13: Scraper Architecture - COMPLETE (store_player_stats fixed, all endpoints wired)
- Phase 14: Progress Tracking UI - COMPLETE (FDM Status dashboard with 14 columns, Manual Verification submenu)

Remaining:
- Phase 15: Scrape Execution - IN PROGRESS (historical scrape running)
- Phase 16: Verification & Audit - After scrape completes

## Milestones

| Milestone | Phases | Status |
|-----------|--------|--------|
| v1.0 Club ID Mapping | 1-8 | Blocked at Phase 8 (absorbed into v3.0) |
| v2.0 ESPN Complete Rebuild | 9-16 | Phase 15 IN PROGRESS (scrape running) |
| v3.0 Club Mapping Completion | 17-22 | **ACTIVE** - Ready to start Phase 17 |
| v4.0 Player Mapping | TBD | Future (after v3.0) |

## Performance Metrics

**Velocity:**
- Total plans completed: 9
- Average duration: 4 min
- Total execution time: 0.6 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 1 | 2 | 17 min | 8.5 min |
| 2 | 2 | 4 min | 2 min |
| 3 | 1 | 2 min | 2 min |
| 4 | 1 | 3 min | 3 min |
| 5 | 1 | 2 min | 2 min |
| 6 | 1 | 2 min | 2 min |
| 7 | 1 | 4 min | 4 min |

**Recent Trend:**
- Last 5 plans: 04-01 (3 min), 05-01 (2 min), 06-01 (2 min), 07-01 (4 min)
- Trend: Fast

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- Schema: Separate `club_aliases` table for name variations (normalized, queryable)
- Schema: 5 new provider ID columns (fmob_id, sm_id, is_id, sc_id, fmgr_id)
- Matching: Auto-apply only exact match + country validation
- Matching: All uncertainty queued for manual review
- Migrations: Closure pattern for up/down functions, tracked via WP option
- Matching priority: Canonical name match first, then alias matching
- Confidence scoring: high (single match), medium (multiple), low (country mismatch)
- Auto-apply: Only exact_match/alias_match with high confidence
- Provenance: id_source column set to 'csv_import' for all CSV-sourced data
- Queue storage: Full CSV row stored as JSON for deferred provider ID extraction
- Review workflow: review_status column with pending/approved/rejected/skipped states
- Review UI: Radio buttons for candidate selection (first pre-selected)
- Batch actions: Batch approve excluded (each approval needs specific club_id)

**Phase 15 Decisions (2026-02-03):**
- Skip logic: Season-level (not match-level) for performance — entire seasons skip if fixtures exist
- Force flag: `--force` CLI option allows re-scraping completed seasons when needed
- Memory limit: 4G for historical scrape to handle large API responses
- Prober validation: Check `$team_roster['roster']` array for actual player data, not just existence
- Data cleanup: Delete partial seasons before resume rather than trying to fill gaps

**v3.0 Architecture Decisions (Proposed):**
- Master tables: `ff_players_master`, `ff_teams_master` for source-agnostic entities
- ID mapping: `ff_player_ids`, `ff_team_ids` tables for cross-source linking
- Python scraper: Use soccerdata library, write directly to MySQL
- Entity matching: Bootstrap from Soccer API (82K pre-mapped players)

### Pending Todos

None yet.

### Blockers/Concerns

- **Phase 8 blocked:** Can't complete CSV import until ESPN club data is scraped.
  - After Phase 15 completes (scrape execution), return to Phase 8
- **Working directory:** Must use `/Users/kevincasey/Local Sites/footyforums` (NOT Desktop copy)
- **Scrape still running:** Monitor progress, check for crashes
- **Lower-tier leagues:** Some leagues (arg.3, arg.4, nga.1) genuinely have no deep data on ESPN — not a bug

### Roadmap Evolution

- 2026-01-29: Milestone v2.0 ESPN Complete Rebuild created (8 phases: 9-16)
- 2026-01-31: Major overhaul completed Phases 9-11, 13-14 in single session:
  - All 14 data types identified and documented
  - Prober updated to check all data types
  - Dashboard updated to display all data types
  - Manual verification system built
  - Critical bugs fixed (playerStats INSERT, bind_param)
  - Schema updated (transfers, venues, season_stats columns)
- 2026-02-03: Phase 15 execution and v3.0 creation:
  - Historical scrape launched (40+ hours running)
  - OOM crash recovered with 4G memory limit
  - Skip logic added for resumable scraping
  - Prober false positives fixed
  - 394 new ESPN clubs imported from scrape data
  - v3.0 Club Mapping Completion milestone created (6 phases)
  - Rosetta Stone approach defined: iterative ID bridge building
  - v4.0 Player Mapping deferred (clubs first, then players)

## Session Continuity

Last session: 2026-02-03
Current state: v3.0 milestone created, ready to plan Phase 17

### Files Modified This Session
- `class-fdm-availability-prober.php` - Fixed false positive lineup detection
- `class-fdm-e-master-datasource.php` - Added skip logic, `get_season_completion_status()`, `has_deep_data()`
- `cli/run-historical-scrape.php` - Added `--force` flag support
- `cli/import-new-espn-clubs.php` - NEW: Import ESPN clubs from e_db to clubs table

### Resume Commands
Check scrape status:
```bash
tail -50 /tmp/historical-scrape.log
```

If scrape crashed, resume with:
```bash
cd /Users/kevincasey/Local\ Sites/footyforums/app/public && php -d memory_limit=4G wp-content/plugins/football-data-manager/cli/run-historical-scrape.php --years=2023,2022,2021,2020,2019,2018,2017,2016,2015,2014,2013,2012,2011,2010,2009,2008,2007,2006,2005,2004,2003,2002,2001 --mode=full 2>&1 | tee -a /tmp/historical-scrape.log
```

### Next Steps
1. `/gsd:plan-phase 17` - Plan Soccer API Bootstrap
2. Monitor v2.0 Phase 15 scrape completion
3. Execute v3.0 phases 17-22 for club mapping completion
4. v4.0 Player Mapping after clubs are done
