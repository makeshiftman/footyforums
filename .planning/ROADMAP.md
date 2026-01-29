# Roadmap: FootyForums Data Platform

## Overview

Build a comprehensive football data platform with accurate, reconciled data from multiple sources. Starting with club ID mapping import, then expanding to complete ESPN data acquisition with full provenance tracking.

## Domain Expertise

- Provider e documentation: `app/docs/providers/e/` (ENDPOINTS.md, DATASETS.md, REBUILD_RECIPE.md, etc.)

## Milestones

- 🚧 **v1.0 Club ID Mapping** - Phases 1-8 (in progress)
- 📋 **v2.0 ESPN Complete Rebuild** - Phases 9-16 (planned)

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

### 📋 v2.0 ESPN Complete Rebuild (Planned)

**Milestone Goal:** Archive current e_db and rebuild from scratch with exhaustive endpoint research, manual verification of data availability per year (2001-2025), and a UI to track exactly what data exists and scrape progress.

- [ ] **Phase 9: Endpoint Research & Discovery** — Exhaustive research of all ESPN endpoints
- [ ] **Phase 10: Data Availability Testing** — Test every endpoint per year, manual verification when API fails
- [ ] **Phase 11: Availability Matrix Documentation** — Document ground truth for each data type × year
- [ ] **Phase 12: Archive & New Schema** — Archive current e_db, design new schema for complete data
- [ ] **Phase 13: Scraper Architecture** — Design scraper for all data types without duplication/errors
- [ ] **Phase 14: Progress Tracking UI** — Build UI showing availability per year and scrape progress
- [ ] **Phase 15: Scrape Execution** — Run complete scrape 2001-2025
- [ ] **Phase 16: Verification & Audit** — Verify completeness against availability matrix

#### Phase 9: Endpoint Research & Discovery
**Goal**: Exhaustive half-day research of GitHub repos, online resources, and ESPN API documentation to find ALL available endpoints
**Depends on**: v1.0 complete (or can start in parallel)
**Research**: Likely (external APIs, GitHub search, online resources)
**Research topics**: GitHub repos with ESPN scrapers, unofficial API documentation, endpoint patterns, authentication requirements, rate limits
**Plans**: TBD

#### Phase 10: Data Availability Testing
**Goal**: Test every discovered endpoint against ESPN for years 2001-2025. When API returns 404/empty, manually verify on ESPN website if data actually exists there.
**Depends on**: Phase 9
**Research**: Likely (manual verification on ESPN website)
**Research topics**: Per-year data availability, API vs website discrepancies, HTML scraping candidates
**Plans**: TBD

#### Phase 11: Availability Matrix Documentation
**Goal**: Create comprehensive matrix documenting ground truth for each data type × year. Mark source as API/Manual/Not Available.
**Depends on**: Phase 10
**Research**: Unlikely (documentation from Phase 10 results)
**Plans**: TBD

#### Phase 12: Archive & New Schema
**Goal**: Archive current e_db (backup), design new schema based on research findings to capture ALL discovered data types
**Depends on**: Phase 11
**Research**: Unlikely (database operations)
**Plans**: TBD

#### Phase 13: Scraper Architecture
**Goal**: Design scraper to handle all data types, API + HTML scraping where needed, without duplications or errors
**Depends on**: Phase 12
**Research**: Unlikely (internal design using availability matrix)
**Plans**: TBD

#### Phase 14: Progress Tracking UI
**Goal**: Build WordPress admin UI showing data availability per year and real-time scrape progress per data type
**Depends on**: Phase 12
**Research**: Unlikely (WordPress admin patterns)
**Plans**: TBD

#### Phase 15: Scrape Execution
**Goal**: Run complete scrape for all leagues 2001-2025, all data types where available
**Depends on**: Phase 13, Phase 14
**Research**: Unlikely (orchestration)
**Plans**: TBD

#### Phase 16: Verification & Audit
**Goal**: Verify scrape completeness against availability matrix, spot checks, reconciliation
**Depends on**: Phase 15
**Research**: Unlikely (verification procedures)
**Plans**: TBD

---

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → ... → 16

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Schema & Plugin Setup | v1.0 | 2/2 | Complete | 2026-01-28 |
| 2. CSV Parser & Country Mapping | v1.0 | 2/2 | Complete | 2026-01-28 |
| 3. Name Normalization | v1.0 | 1/1 | Complete | 2026-01-28 |
| 4. Matching Engine | v1.0 | 1/1 | Complete | 2026-01-29 |
| 5. Auto-Apply | v1.0 | 1/1 | Complete | 2026-01-29 |
| 6. Uncertain Match Queue | v1.0 | 1/1 | Complete | 2026-01-29 |
| 7. Review UI | v1.0 | 1/1 | Complete | 2026-01-29 |
| 8. Import Execution & Verification | v1.0 | 0/3 | In progress | - |
| 9. Endpoint Research & Discovery | v2.0 | 0/? | Not started | - |
| 10. Data Availability Testing | v2.0 | 0/? | Not started | - |
| 11. Availability Matrix Documentation | v2.0 | 0/? | Not started | - |
| 12. Archive & New Schema | v2.0 | 0/? | Not started | - |
| 13. Scraper Architecture | v2.0 | 0/? | Not started | - |
| 14. Progress Tracking UI | v2.0 | 0/? | Not started | - |
| 15. Scrape Execution | v2.0 | 0/? | Not started | - |
| 16. Verification & Audit | v2.0 | 0/? | Not started | - |
