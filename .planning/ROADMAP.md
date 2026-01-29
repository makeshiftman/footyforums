# Roadmap: FootyForums Club ID Mapping Import

## Overview

Import ~1,712 club mappings from CSV into the FootyForums database. Build schema for new providers, parse and validate CSV data, match against existing clubs, auto-apply confident matches, and provide a review workflow for uncertain matches.

## Domain Expertise

None — WordPress/PHP data processing with standard patterns.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

- [x] **Phase 1: Schema & Plugin Setup** — Database and plugin ready for new providers
- [x] **Phase 2: CSV Parser & Country Mapping** — Parse CSV with country validation
- [x] **Phase 3: Name Normalization** — Unicode-safe name comparison utilities
- [x] **Phase 4: Matching Engine** — Match CSV rows to clubs with validation
- [x] **Phase 5: Auto-Apply** — Automatically apply confident matches
- [x] **Phase 6: Uncertain Match Queue** — Queue uncertain matches for review
- [x] **Phase 7: Review UI** — Batch review interface for uncertain matches
- [ ] **Phase 8: Import Execution & Verification** — Run full import with safeguards

## Phase Details

### Phase 1: Schema & Plugin Setup
**Goal**: Prepare database schema and plugin for new provider data
**Depends on**: Nothing (first phase)
**Requirements**: SCHEMA-01, SCHEMA-02, SCHEMA-03, SCHEMA-04, SCHEMA-05, SCHEMA-06, SCHEMA-07, PLUGIN-01, PLUGIN-02
**Research**: Unlikely (standard MySQL ALTER TABLE, PHP array updates)
**Plans**: TBD

### Phase 2: CSV Parser & Country Mapping
**Goal**: Parse CSV file and map countries to league prefixes
**Depends on**: Phase 1
**Requirements**: IMPORT-01, IMPORT-02, IMPORT-03, IMPORT-04
**Research**: Unlikely (standard PHP CSV parsing)
**Plans**: 2 (02-01 CSV Parser, 02-02 Country Mapper)

### Phase 3: Name Normalization
**Goal**: Build utilities for Unicode-safe name comparison
**Depends on**: Nothing (can run parallel to Phase 2)
**Requirements**: MATCH-03
**Research**: Likely (Unicode normalization, accent handling)
**Research topics**: PHP intl extension, transliteration patterns, common football name variations (ü→u, ø→o, etc.)
**Plans**: TBD

### Phase 4: Matching Engine
**Goal**: Match CSV rows to clubs using names and country validation
**Depends on**: Phase 2, Phase 3
**Requirements**: MATCH-01, MATCH-02, MATCH-04
**Research**: Unlikely (SQL queries with JOIN on aliases)
**Plans**: TBD

### Phase 5: Auto-Apply
**Goal**: Automatically apply confident matches to database
**Depends on**: Phase 4
**Requirements**: MATCH-05, UPDATE-01, UPDATE-02
**Research**: Unlikely (database UPDATE/INSERT operations)
**Plans**: TBD

### Phase 6: Uncertain Match Queue
**Goal**: Queue uncertain matches for manual review
**Depends on**: Phase 4
**Requirements**: MATCH-06, PLUGIN-03
**Research**: Unlikely (database inserts, task status updates)
**Plans**: TBD

### Phase 7: Review UI
**Goal**: Build batch review interface in WordPress admin
**Depends on**: Phase 6
**Requirements**: REVIEW-01, REVIEW-02, REVIEW-03
**Research**: Unlikely (WordPress admin UI patterns exist in codebase)
**Plans**: TBD

### Phase 8: Import Execution & Verification
**Goal**: Run full import, verify results, update task statuses
**Depends on**: Phase 5, Phase 6, Phase 7
**Requirements**: IMPORT-05, UPDATE-03, UPDATE-04, PLUGIN-04
**Research**: Unlikely (orchestration of previous phases)
**Plans**: 3 (08-01 Import Runner, 08-02 Task Status & Overwrite Protection, 08-03 Apply Approved UI)

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8
(Note: Phase 3 can run parallel to Phase 2)

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Schema & Plugin Setup | 2/2 | Complete | 2026-01-28 |
| 2. CSV Parser & Country Mapping | 2/2 | Complete | 2026-01-28 |
| 3. Name Normalization | 1/1 | Complete | 2026-01-28 |
| 4. Matching Engine | 1/1 | Complete | 2026-01-29 |
| 5. Auto-Apply | 1/1 | Complete | 2026-01-29 |
| 6. Uncertain Match Queue | 1/1 | Complete | 2026-01-29 |
| 7. Review UI | 1/1 | Complete | 2026-01-29 |
| 8. Import Execution & Verification | 0/3 | Planned | - |
