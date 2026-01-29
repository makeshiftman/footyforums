---
phase: 04-matching-engine
plan: 01
subsystem: data-processing
tags: [php, matching, database, country-validation, confidence-scoring]

# Dependency graph
requires:
  - phase: 02-csv-parser-country-mapping
    provides: FFM_CSV_Parser for row extraction, FFM_Country_Mapper for country validation
  - phase: 03-name-normalization
    provides: FFM_Name_Normalizer for Unicode-safe name comparison
provides:
  - FFM_Matching_Engine class with complete matching API
  - find_clubs_by_name() and find_clubs_by_alias() for database lookups
  - match_csv_row() with confidence scoring (exact_match, alias_match, uncertain, no_match)
  - match_all_csv_rows() batch method with statistics for downstream phases
affects: [05-auto-apply, 06-uncertain-queue, 08-import-execution]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Instance class with constructor dependency injection for database connection"
    - "Confidence levels: high (single match), medium (multiple candidates), low (country mismatch), none"
    - "Country validation as filter step in matching pipeline"

key-files:
  created:
    - app/public/wp-content/plugins/footyforums-club-id-mapper/includes/class-ffm-matching-engine.php
  modified:
    - app/public/wp-content/plugins/footyforums-club-id-mapper/footyforums-club-id-mapper.php

key-decisions:
  - "Exact canonical match takes priority over alias match"
  - "Country validation via competition_code prefix comparison"
  - "Multiple candidates with country match = uncertain (medium), country mismatch = uncertain (low)"

patterns-established:
  - "FFM_Matching_Engine for all CSV-to-database club matching"
  - "match_csv_row() returns structured result array for consistent downstream handling"

# Metrics
duration: 3min
completed: 2026-01-29
---

# Phase 4 Plan 01: Matching Engine Summary

**FFM_Matching_Engine class with canonical and alias matching, country validation, and batch statistics for 1712+ CSV rows**

## Performance

- **Duration:** 3 min
- **Started:** 2026-01-28T23:58:29Z
- **Completed:** 2026-01-29T00:01:11Z
- **Tasks:** 3
- **Files modified:** 2

## Accomplishments

- FFM_Matching_Engine class with 5 public methods for database club lookups
- find_clubs_by_name() with case-insensitive and normalized matching via FFM_Name_Normalizer
- find_clubs_by_alias() for club_aliases table lookups with full club record retrieval
- match_csv_row() returns status (exact_match/alias_match/uncertain/no_match), club_id, confidence, candidates
- match_all_csv_rows() batch processing with aggregate statistics for Phase 5/6/8

## Task Commits

Each task was committed atomically:

1. **Task 1: Create FFM_Matching_Engine class with database lookup methods** - `2a3c44b` (feat)
2. **Task 2: Add match_csv_row() method with confidence scoring** - `54933f4` (feat)
3. **Task 3: Add match_all_csv_rows() batch method with statistics** - `ec03543` (feat)

## Files Created/Modified

- `app/public/wp-content/plugins/footyforums-club-id-mapper/includes/class-ffm-matching-engine.php` - Full matching engine class with 5 public methods
- `app/public/wp-content/plugins/footyforums-club-id-mapper/footyforums-club-id-mapper.php` - Added require_once for matching engine class

## Decisions Made

- **Matching priority:** Canonical name match checked first, then alias matching via name variations
- **Country validation:** Applied as filter after initial matches using FFM_Country_Mapper::validate_club_country()
- **Confidence scoring:** Single match with country validation = high, multiple candidates = medium, country mismatch = low
- **Batch result structure:** Includes row_index and csv_name for traceability in downstream phases

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Matching engine ready for Phase 5 (Auto-Apply) to get exact/alias matches
- match_all_csv_rows() returns stats needed for import preview UI
- uncertain results include candidates array for Phase 6 (Uncertain Queue)
- Requirements MATCH-01, MATCH-02, MATCH-04 satisfied

---
*Phase: 04-matching-engine*
*Completed: 2026-01-29*
