# Roadmap: FootyForums Data Platform

## Overview

Build a comprehensive football data platform with accurate, reconciled data from multiple sources. Starting with club ID mapping import, then expanding to complete ESPN data acquisition with full provenance tracking.

## Domain Expertise

- Provider e documentation: `app/docs/providers/e/` (ENDPOINTS.md, DATASETS.md, REBUILD_RECIPE.md, etc.)

## Milestones

- 🚧 **v1.0 Club ID Mapping** - Phases 1-8 (blocked at Phase 8)
- 🚧 **v2.0 ESPN Complete Rebuild** - Phases 9-16 (Phase 15 in progress)
- 🚧 **v3.0 Club Mapping Completion** - Phases 17-22 (in progress)
- 📋 **v3.1 Competition Tier Validation** - After ESPN scrape (parked)

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

### 🚧 v1.0 Club ID Mapping (In Progress)

**Milestone Goal:** Import ~1,712 club mappings from CSV with matching, auto-apply, and review workflow.

- [x] **Phase 1: Schema & Plugin Setup** — Database and plugin ready for new providers
- [x] **Phase 2: CSV Parser & Country Mapping** — Parse CSV with country validation
- [x] **Phase 3: Name Normalization** — Unicode-safe name comparison utilities
- [x] **Phase 4: Matching Engine** — Match CSV rows to clubs with validation
- [x] **Phase 5: Auto-Apply** — Automatically apply confident matches
- [x] **Phase 6: Uncertain Match Queue** — Queue uncertain matches for review
- [x] **Phase 7: Review UI** — Batch review interface for uncertain matches
- [ ] **Phase 8: Import Execution & Verification** — Run full import with safeguards

#### Phase 1: Schema & Plugin Setup
**Goal**: Prepare database schema and plugin for new provider data
**Depends on**: Nothing (first phase)
**Research**: Unlikely (standard MySQL ALTER TABLE, PHP array updates)
**Plans**: 2/2 complete

#### Phase 2: CSV Parser & Country Mapping
**Goal**: Parse CSV file and map countries to league prefixes
**Depends on**: Phase 1
**Research**: Unlikely (standard PHP CSV parsing)
**Plans**: 2/2 complete

#### Phase 3: Name Normalization
**Goal**: Build utilities for Unicode-safe name comparison
**Depends on**: Nothing (can run parallel to Phase 2)
**Research**: Unlikely (patterns established)
**Plans**: 1/1 complete

#### Phase 4: Matching Engine
**Goal**: Match CSV rows to clubs using names and country validation
**Depends on**: Phase 2, Phase 3
**Research**: Unlikely (SQL queries with JOIN on aliases)
**Plans**: 1/1 complete

#### Phase 5: Auto-Apply
**Goal**: Automatically apply confident matches to database
**Depends on**: Phase 4
**Research**: Unlikely (database UPDATE/INSERT operations)
**Plans**: 1/1 complete

#### Phase 6: Uncertain Match Queue
**Goal**: Queue uncertain matches for manual review
**Depends on**: Phase 4
**Research**: Unlikely (database inserts, task status updates)
**Plans**: 1/1 complete

#### Phase 7: Review UI
**Goal**: Build batch review interface in WordPress admin
**Depends on**: Phase 6
**Research**: Unlikely (WordPress admin UI patterns exist in codebase)
**Plans**: 1/1 complete

#### Phase 8: Import Execution & Verification
**Goal**: Run full import, verify results, update task statuses
**Depends on**: Phase 5, Phase 6, Phase 7
**Research**: Unlikely (orchestration of previous phases)
**Plans**: 3 (08-01 Import Runner, 08-02 Task Status & Overwrite Protection, 08-03 Apply Approved UI)

Plans:
- [ ] 08-01: Import Runner
- [ ] 08-02: Task Status & Overwrite Protection
- [ ] 08-03: Apply Approved UI

---

### 🚧 v2.0 ESPN Complete Rebuild (In Progress)

**Milestone Goal:** Archive current e_db and rebuild from scratch with exhaustive endpoint research, manual verification of data availability per year (2001-2025), and a UI to track exactly what data exists and scrape progress.

- [x] **Phase 9: Endpoint Research & Discovery** — Exhaustive research of all ESPN endpoints
- [x] **Phase 10: Data Availability Testing** — Test every endpoint per year, manual verification when API fails
- [x] **Phase 11: Availability Matrix Documentation** — Document ground truth for each data type × year
- [ ] **Phase 12: Archive & New Schema** — Archive current e_db, design new schema for complete data (SKIPPED - using existing schema with updates)
- [x] **Phase 13: Scraper Architecture** — Design scraper for all data types without duplication/errors
- [x] **Phase 14: Progress Tracking UI** — Build UI showing availability per year and scrape progress
- [ ] **Phase 15: Scrape Execution** — Run complete scrape 2001-2025
- [ ] **Phase 16: Verification & Audit** — Verify completeness against availability matrix

#### Phase 9: Endpoint Research & Discovery
**Goal**: Exhaustive half-day research of GitHub repos, online resources, and ESPN API documentation to find ALL available endpoints
**Depends on**: v1.0 complete (or can start in parallel)
**Status**: ✅ COMPLETE (2026-01-31)
**Deliverables**:
- `docs/providers/e/ENDPOINTS.md` fully updated with all 14 data types
- Documented: site.transactions, site.athlete.stats, rosters.stats extraction
- Player stats: 15 stat types identified (goals, assists, cards, shots, saves, etc.)

#### Phase 10: Data Availability Testing
**Goal**: Test every discovered endpoint against ESPN for years 2001-2025. When API returns 404/empty, manually verify on ESPN website if data actually exists there.
**Depends on**: Phase 9
**Status**: ✅ COMPLETE (2026-01-31)
**Deliverables**:
- `class-fdm-availability-prober.php` updated to check ALL data types
- Added: probe_teams(), probe_standings(), probe_players(), probe_plays(), probe_transfers(), probe_season_stats()
- Manual verification system built for data prober can't auto-detect

#### Phase 11: Availability Matrix Documentation
**Goal**: Create comprehensive matrix documenting ground truth for each data type × year. Mark source as API/Manual/Not Available.
**Depends on**: Phase 10
**Status**: ✅ COMPLETE (2026-01-31)
**Deliverables**:
- `espn_availability` table with 16 columns tracking all data types
- Schema: fixtures, lineups, commentary, key_events, plays, player_stats, team_stats, teams, players, standings, roster, venues, transfers, season_stats
- `espn_manual_verification` table for manual verification workflow

#### Phase 12: Archive & New Schema
**Goal**: Archive current e_db (backup), design new schema based on research findings to capture ALL discovered data types
**Depends on**: Phase 11
**Status**: ⏭️ SKIPPED - Using existing schema with column additions
**Notes**: Added columns to espn_availability (transfers_available, season_stats_available, venues_available). Added eventid to playerStats, league to transfers.

#### Phase 13: Scraper Architecture
**Goal**: Design scraper to handle all data types, API + HTML scraping where needed, without duplications or errors
**Depends on**: Phase 12
**Status**: ✅ COMPLETE (2026-01-31)
**Deliverables**:
- Fixed critical bug: `store_player_stats()` was counting but NOT inserting - now works
- Fixed `upsert_player()` bind_param mismatch (20 chars for 21 vars)
- `class-fdm-e-master-datasource.php` collects all 14 data types
- Verified: 46 player stats rows inserted for test match

#### Phase 14: Progress Tracking UI
**Goal**: Build WordPress admin UI showing data availability per year and real-time scrape progress per data type
**Depends on**: Phase 12
**Status**: ✅ COMPLETE (2026-01-31)
**Deliverables**:
- `class-fdm-admin-data-status.php` - Dashboard showing all 14 data columns
- By Year view with scraped/available counts per data type
- By League view showing fixture coverage per league
- Real-time prober and scraper progress bars
- `class-fdm-admin-manual-verification.php` - Manual verification admin page

#### Phase 15: Scrape Execution
**Goal**: Run complete scrape for all leagues 2001-2025, all data types where available
**Depends on**: Phase 13, Phase 14
**Status**: 🚧 IN PROGRESS (since 2026-02-01)
**Runtime**: 40+ hours, processing 2024→2001
**Accomplishments**:
- 2024 complete: 31,561 fixtures, 69% with lineups, 95% with teamStats
- 2023 partial: 64 leagues complete, ~72 remaining
- Skip logic implemented for resumable scraping
- OOM crash fixed (4G memory limit)
- Prober false positives fixed
- Database backed up: `e_db_backup_before_resume_20260202_185048.sql`
**Monitor**: `tail -50 /tmp/historical-scrape.log`

#### Phase 16: Verification & Audit
**Goal**: Verify scrape completeness against availability matrix, spot checks, reconciliation
**Depends on**: Phase 15
**Status**: Not started
**Tools ready**:
- `verify-data-quality.php` - Audit all 14 data tables
- Dashboard shows scraped vs available counts

---

### 🚧 v3.0 Club Mapping Completion (In Progress)

**Milestone Goal:** Complete club ID mapping using Rosetta Stone approach — build ID bridges iteratively until all clubs are mapped across ESPN, Transfermarkt, FBref, WhoScored, SoFIFA.

**Current state:**
- 4,042 clubs with ESPN IDs (394 newly imported from scrape)
- 389 clubs with at least one other provider ID
- 2,312 mapping tasks done, 33,707 pending
- Professor's CSV: 976 no_match, 570 uncertain, ~164 auto-applied

**Target state:**
- All viable clubs mapped across providers
- Professor's CSV fully processed
- Club aliases populated for all matched clubs

- [ ] **Phase 17: Soccer API Bootstrap** — Import pre-built ID bridges from Soccer API
- [ ] **Phase 18: CSV Re-Processing** — Re-run matching with new bridges, reduce no_match count
- [ ] **Phase 19: Bulk Mapping Tools** — Batch operations, fuzzy search, accelerated review UI
- [ ] **Phase 20: Mapping Dashboard** — Coverage visualization, identify gaps, prioritize work
- [ ] **Phase 21: ID Chain Matching** — Use transitive relationships (ESPN→TM→FBref) as matching signals
- [ ] **Phase 22: Final Push & Verification** — Process remaining uncertain matches, verify coverage

#### Phase 17: Soccer API Bootstrap
**Goal**: Clone Soccer API repo, extract club/team ID mappings, import bridges to clubs table
**Depends on**: v2.0 Phase 15 (ESPN scrape provides club data)
**Research**: Likely (need to understand Soccer API data structure)
**Research topics**: Soccer API schema, how team mappings are stored, extraction scripts
**Plans**: TBD

Plans:
- [ ] 17-01: TBD (run /gsd:plan-phase 17 to break down)

#### Phase 18: CSV Re-Processing
**Goal**: Re-run Professor's CSV matching using new ID bridges, convert no_match to matches
**Depends on**: Phase 17
**Research**: Unlikely (existing matching engine, new data)
**Plans**: TBD

Plans:
- [ ] 18-01: TBD

#### Phase 19: Bulk Mapping Tools
**Goal**: Build batch operations, fuzzy search, "map all similar" to accelerate manual review
**Depends on**: Phase 18
**Research**: Unlikely (WordPress admin patterns established)
**Plans**: TBD

Plans:
- [ ] 19-01: TBD

#### Phase 20: Mapping Dashboard
**Goal**: Visualize coverage — clubs with 1/2/3+ IDs, sources well-mapped vs sparse, gaps to prioritize
**Depends on**: Phase 17
**Research**: Unlikely (dashboard patterns exist)
**Plans**: TBD

Plans:
- [ ] 20-01: TBD

#### Phase 21: ID Chain Matching
**Goal**: Use transitive ID relationships as matching signals (if ESPN→TM and CSV has same TM = match)
**Depends on**: Phase 17, Phase 18
**Research**: Unlikely (algorithm design, existing infrastructure)
**Plans**: TBD

Plans:
- [ ] 21-01: TBD

#### Phase 22: Final Push & Verification
**Goal**: Process all remaining uncertain matches, verify coverage, document unmatchable clubs
**Depends on**: Phase 19, Phase 20, Phase 21
**Research**: Unlikely (execution and verification)
**Plans**: TBD

Plans:
- [ ] 22-01: TBD

---

### 📋 v3.1 Competition Tier Validation (Parked)

**Milestone Goal:** Validate and complete the competition tier system after ESPN scrape fills data gaps.

**Current state (2026-02-05):**
- `competition_tiers` table: 208 definitions, 100% match coverage
- `club_seasons` table: 9,009 records, 460 clubs, 1889-2025
- Data gap: 2001-2022 has only Premier League data (lower divisions missing)
- 1,164 tier changes detected (570 promotions, 594 relegations)
- No suspicious multi-tier jumps found

**Blocked by:** v2.0 Phase 15 (ESPN scrape must complete to fill 2001-2022 gaps)

**Tasks when unblocked:**
- [ ] Re-populate `club_seasons` from complete match data
- [ ] Review 1,164 tier changes against Wikipedia/historical records
- [ ] Fix ~130 clubs with wrong `clubs.e_league_code` (cups/UEFA as primary)
- [ ] Build tier change validation UI (similar to Wikidata review)
- [ ] Document final accuracy assessment

**Related tables:**
- `competition_tiers` — Competition code → type/tier mapping
- `club_seasons` — Club → season → primary league tracking

---

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → ... → 22

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Schema & Plugin Setup | v1.0 | 2/2 | Complete | 2026-01-28 |
| 2. CSV Parser & Country Mapping | v1.0 | 2/2 | Complete | 2026-01-28 |
| 3. Name Normalization | v1.0 | 1/1 | Complete | 2026-01-28 |
| 4. Matching Engine | v1.0 | 1/1 | Complete | 2026-01-29 |
| 5. Auto-Apply | v1.0 | 1/1 | Complete | 2026-01-29 |
| 6. Uncertain Match Queue | v1.0 | 1/1 | Complete | 2026-01-29 |
| 7. Review UI | v1.0 | 1/1 | Complete | 2026-01-29 |
| 8. Import Execution & Verification | v1.0 | 0/3 | Blocked (needs ESPN data) | - |
| 9. Endpoint Research & Discovery | v2.0 | ✅ | Complete | 2026-01-31 |
| 10. Data Availability Testing | v2.0 | ✅ | Complete | 2026-01-31 |
| 11. Availability Matrix Documentation | v2.0 | ✅ | Complete | 2026-01-31 |
| 12. Archive & New Schema | v2.0 | - | Skipped (using existing) | 2026-01-31 |
| 13. Scraper Architecture | v2.0 | ✅ | Complete | 2026-01-31 |
| 14. Progress Tracking UI | v2.0 | ✅ | Complete | 2026-01-31 |
| 15. Scrape Execution | v2.0 | 1/2 | **IN PROGRESS** | 2026-02-01 |
| 16. Verification & Audit | v2.0 | 0/1 | Not started | - |
| 17. Soccer API Bootstrap | v3.0 | 0/? | Not started | - |
| 18. CSV Re-Processing | v3.0 | 0/? | Not started | - |
| 19. Bulk Mapping Tools | v3.0 | 0/? | Not started | - |
| 20. Mapping Dashboard | v3.0 | 0/? | Not started | - |
| 21. ID Chain Matching | v3.0 | 0/? | Not started | - |
| 22. Final Push & Verification | v3.0 | 0/? | Not started | - |

**v2.0 Rapid Progress (2026-01-31):**
- 5 phases completed in single session
- All 14 data types documented and tracked
- Prober, scraper, and dashboard fully operational
- Critical bugs fixed (playerStats INSERT, bind_param)
- Manual verification system built

**v3.0 Created (2026-02-03):**
- Club Mapping Completion milestone with 6 phases
- Rosetta Stone approach: build ID bridges iteratively
- 394 new ESPN clubs imported before milestone start
- Absorbs v1.0 Phase 8 work (Professor's CSV processing)
