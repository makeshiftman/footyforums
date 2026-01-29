---
phase: 06-uncertain-match-queue
plan: 01
subsystem: data-processing
tags: [php, queue, database, review-workflow, json]

# Dependency graph
requires:
  - phase: 04-matching-engine
    provides: FFM_Matching_Engine::match_all_csv_rows() output structure with status, confidence, candidates
provides:
  - FFM_Uncertain_Queue class for storing uncertain/no_match results
  - queue_match() method for individual result storage
  - queue_all_uncertain() batch method with statistics
  - csv_import_review_queue table via migration 002
affects: [07-review-ui, 08-import-execution]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "JSON-encoded csv_row_data for deferred provider ID extraction"
    - "review_status workflow: pending -> approved/rejected/skipped"
    - "ensure_table_exists() lazy table creation pattern"

key-files:
  created:
    - app/public/wp-content/plugins/footyforums-club-id-mapper/includes/class-ffm-uncertain-queue.php
    - app/public/wp-content/plugins/footyforums-club-id-mapper/includes/migrations/002-csv-import-review-queue.php
  modified:
    - app/public/wp-content/plugins/footyforums-club-id-mapper/footyforums-club-id-mapper.php

key-decisions:
  - "Store full CSV row as JSON for Phase 8 provider ID extraction"
  - "Index on review_status for efficient filtering in Review UI"
  - "Candidate club IDs stored as JSON array for Review UI display"

patterns-established:
  - "FFM_Uncertain_Queue for all uncertain/no_match queueing"
  - "queue_all_uncertain() returns stats array consistent with FFM_Auto_Applier"

# Metrics
duration: 2min
completed: 2026-01-29
---

# Phase 6 Plan 01: Uncertain Match Queue Summary

**FFM_Uncertain_Queue class with queue_match() and queue_all_uncertain() methods for storing uncertain/no_match results for Phase 7 Review UI**

## Performance

- **Duration:** 2 min
- **Started:** 2026-01-29T00:20:54Z
- **Completed:** 2026-01-29T00:23:15Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments

- FFM_Uncertain_Queue class with constructor DI pattern (consistent with existing classes)
- queue_match() stores individual uncertain/no_match results with full csv_row_data JSON
- queue_all_uncertain() batch method processes FFM_Matching_Engine output with statistics
- Migration 002 creates csv_import_review_queue table with review workflow columns
- ensure_table_exists() enables lazy table creation when first needed

## Task Commits

Each task was committed atomically:

1. **Task 1-2: Create FFM_Uncertain_Queue class with queue_match() and queue_all_uncertain()** - `6983f1e` (feat)
2. **Task 3: Register class in plugin bootstrap** - `abf3b6c` (feat)

## Files Created/Modified

- `app/public/wp-content/plugins/footyforums-club-id-mapper/includes/class-ffm-uncertain-queue.php` - Full queue class with queue_match(), queue_all_uncertain(), ensure_table_exists()
- `app/public/wp-content/plugins/footyforums-club-id-mapper/includes/migrations/002-csv-import-review-queue.php` - Migration for csv_import_review_queue table
- `app/public/wp-content/plugins/footyforums-club-id-mapper/footyforums-club-id-mapper.php` - Added require_once for new class

## Decisions Made

- **JSON storage:** csv_row_data stored as JSON to preserve full row for Phase 8 provider ID extraction
- **Candidate IDs:** Stored as JSON array rather than joining to candidates table (simpler, denormalized for review context)
- **Review status index:** Added INDEX on review_status for efficient pending queue filtering

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- FFM_Uncertain_Queue ready for Phase 7 (Review UI) to display pending items
- queue_all_uncertain() integrates with FFM_Matching_Engine::match_all_csv_rows() output
- Phase 8 (Import Execution) can use csv_row_data JSON for provider ID extraction on approved items
- Migration 002 will run via FFM_Migration_Runner when plugin activates

---
*Phase: 06-uncertain-match-queue*
*Completed: 2026-01-29*
