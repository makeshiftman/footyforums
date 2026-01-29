---
phase: 01-schema-plugin-setup
plan: 02
subsystem: admin-ui
tags: [php, wordpress, admin, migrations, ui]

# Dependency graph
requires:
  - 01-01 (Migration Infrastructure)
provides:
  - Migration controls in Club ID Mapper admin page
  - Schema verification display
  - Provider configuration for 5 new sources
affects: [03-csv-parser-country-mapping]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Migration controls embedded in admin page, not separate page"
    - "Schema verification via SHOW COLUMNS/SHOW TABLES"
    - "Admin notices for migration success/failure feedback"

key-files:
  created: []
  modified:
    - app/public/wp-content/plugins/footyforums-club-id-mapper/includes/admin-page.php
    - app/public/wp-content/plugins/footyforums-club-id-mapper/includes/query-club-queue.php
    - app/public/wp-content/plugins/footyforums-club-id-mapper/footyforums-club-id-mapper.php

key-decisions:
  - "Migration controls at top of existing Club ID Mapper page rather than separate page"
  - "Visual schema status with green checkmarks for easy verification"
  - "Provider display names defined centrally in $sources_non_opta array"

patterns-established:
  - "WordPress card component for admin UI sections"
  - "settings_errors() for migration feedback"

# Metrics
duration: 15min
completed: 2026-01-28
---

# Phase 1 Plan 02: Admin Migration Controls Summary

**Migration controls and provider configuration integrated into Club ID Mapper admin page**

## Performance

- **Duration:** 15 min
- **Started:** 2026-01-28
- **Completed:** 2026-01-28
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments

- Migration controls section added to top of Club ID Mapper admin page
- Schema verification table showing status of all required columns/tables
- "Run Pending Migrations" button with nonce protection
- Provider configuration updated with 5 new sources (FotMob, SportMonks, InStat, SkillCorner, Football Manager)
- Visual feedback with green checkmarks for existing columns, red X for missing

## Task Commits

Each task was committed atomically:

1. **Task 1: Add migration controls and verification to admin page** - `53e99bb` (feat)
2. **Task 2: Update provider configuration for new columns** - `e23ce42` (feat)
3. **Task 3: Verify migration controls in admin UI** - checkpoint, user approved

Additional fix commit:
- **Bug fix: align run_pending/rollback return format** - `88e8467` (fix)

## Files Created/Modified

- `app/public/wp-content/plugins/footyforums-club-id-mapper/includes/admin-page.php` - Added migration controls section, schema verification display, and 5 new providers to sources list
- `app/public/wp-content/plugins/footyforums-club-id-mapper/includes/query-club-queue.php` - Added 5 new provider column mappings
- `app/public/wp-content/plugins/footyforums-club-id-mapper/footyforums-club-id-mapper.php` - Added require for migration runner class

## Decisions Made

- **Inline migration controls:** Rather than a separate settings page, migration controls were added to the top of the existing Club ID Mapper page. This keeps all club data management in one place.
- **Schema verification table:** Real-time column/table existence checking provides immediate feedback after migrations run.
- **Provider naming:** Display names follow provider branding (FotMob, SportMonks, InStat, SkillCorner, Football Manager).

## Deviations from Plan

- **FK constraint removed from migration:** The Local by Flywheel MySQL user lacks REFERENCES permission required for foreign key constraints. The FK constraint was removed from the club_aliases table migration. Referential integrity will be enforced at the application layer.

## Issues Encountered

- **REFERENCES permission denied:** WordPress database user does not have REFERENCES privilege, causing FK constraint creation to fail. Resolved by removing FK constraint from migration - data integrity maintained via application-level checks.

## User Setup Required

None - migrations can be run from admin UI.

## Verification Checklist

- [x] Admin page loads without PHP errors
- [x] Migration section appears at top of page
- [x] Schema verification shows column status
- [x] "Run Pending Migrations" button works
- [x] After running migrations, verification shows all green checkmarks
- [x] Existing club mapping functionality still works
- [x] Provider configuration includes all 5 new providers

## Next Phase Readiness

- Schema changes applied to database
- All 5 new provider columns exist in clubs table
- club_aliases table created
- Provider configuration ready for CSV import (Phase 2)
- Admin UI ready to display new provider data once imported

---
*Phase: 01-schema-plugin-setup*
*Completed: 2026-01-28*
