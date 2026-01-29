---
phase: 05-auto-apply
plan: 01
subsystem: data-processing
tags: [php, database, auto-apply, provider-ids, aliases]

# Dependency graph
requires:
  - phase: 04-matching-engine
    provides: FFM_Matching_Engine for match_csv_row() and match_all_csv_rows() results
  - phase: 02-csv-parser-country-mapping
    provides: FFM_CSV_Parser for extracting provider IDs and name variations
provides:
  - FFM_Auto_Applier class for applying confident matches to database
  - apply_match() method for single match application
  - apply_all_confident() batch method for processing all confident matches
  - Provider ID updates with csv_import provenance tracking
  - Name variation inserts into club_aliases table
affects: [08-import-execution]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Instance class with constructor dependency injection for database connection"
    - "INSERT IGNORE for duplicate-safe alias insertion"
    - "Provenance tracking via id_source column"

key-files:
  created:
    - app/public/wp-content/plugins/footyforums-club-id-mapper/includes/class-ffm-auto-applier.php
  modified:
    - app/public/wp-content/plugins/footyforums-club-id-mapper/footyforums-club-id-mapper.php

key-decisions:
  - "Only apply exact_match and alias_match with high confidence"
  - "Set id_source to csv_import for provenance tracking"
  - "Use INSERT IGNORE for aliases to handle duplicates gracefully"

patterns-established:
  - "FFM_Auto_Applier for all confident match application"
  - "apply_all_confident() returns statistics for downstream reporting"

# Metrics
duration: 2min
completed: 2026-01-29
---

# Phase 5 Plan 01: Auto-Apply Summary

**FFM_Auto_Applier class with single-match and batch methods for applying confident CSV matches to clubs table and club_aliases**

## Performance

- **Duration:** 2 min
- **Started:** 2026-01-29T00:10:12Z
- **Completed:** 2026-01-29T00:12:18Z
- **Tasks:** 3
- **Files modified:** 2

## Accomplishments

- FFM_Auto_Applier class with constructor DI for database connection (defaults to kt_ffdb())
- apply_match() validates exact_match/alias_match with high confidence before applying
- update_provider_ids() sets provider ID columns and marks id_source as 'csv_import'
- insert_aliases() uses INSERT IGNORE to avoid duplicates in club_aliases
- apply_all_confident() batch method iterates through match results with error tracking

## Task Commits

Each task was committed atomically:

1. **Task 1: Create FFM_Auto_Applier class with single-match apply method** - `e4b893c` (feat)
2. **Task 2: Add batch apply method for all confident matches** - `10eb9aa` (feat)
3. **Task 3: Register auto-applier class in plugin bootstrap** - `a1dded9` (feat)

## Files Created/Modified

- `app/public/wp-content/plugins/footyforums-club-id-mapper/includes/class-ffm-auto-applier.php` - Full auto-applier class with 2 public methods and 2 private helpers
- `app/public/wp-content/plugins/footyforums-club-id-mapper/footyforums-club-id-mapper.php` - Added require_once for auto-applier class

## Decisions Made

- **Confidence validation:** Only apply matches with status exact_match/alias_match AND confidence high
- **Provenance tracking:** Set id_source column to 'csv_import' on all provider ID updates
- **Duplicate handling:** Use INSERT IGNORE for aliases to gracefully skip existing entries
- **Batch statistics:** apply_all_confident() returns applied/skipped/errors/total for reporting

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Auto-applier ready for Phase 8 (Import Execution) to use
- apply_all_confident() integrates directly with FFM_Matching_Engine::match_all_csv_rows() output
- Statistics returned enable progress reporting in import UI

---
*Phase: 05-auto-apply*
*Completed: 2026-01-29*
