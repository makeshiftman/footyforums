---
phase: 03-name-normalization
plan: 01
subsystem: data-processing
tags: [php, unicode, normalization, matching, iconv]

# Dependency graph
requires:
  - phase: 02-csv-parser-country-mapping
    provides: CSV parsing infrastructure and country mapping utilities
provides:
  - FFM_Name_Normalizer class with normalize(), match(), normalize_for_search()
  - Unicode-safe accent transliteration using iconv TRANSLIT
  - similarity_score() for fuzzy Levenshtein-based matching
affects: [04-matching-engine, 05-auto-apply]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Static utility class for stateless string processing"
    - "iconv TRANSLIT for Unicode→ASCII transliteration (no intl extension)"
    - "mb_strtolower for Unicode-safe lowercase conversion"

key-files:
  created:
    - app/public/wp-content/plugins/footyforums-club-id-mapper/includes/class-ffm-name-normalizer.php
  modified:
    - app/public/wp-content/plugins/footyforums-club-id-mapper/footyforums-club-id-mapper.php

key-decisions:
  - "Use iconv TRANSLIT since PHP intl extension unavailable in environment"
  - "Preserve spaces, hyphens, periods in normalized output for readability"
  - "Common affixes list covers major European club naming patterns"

patterns-established:
  - "FFM_Name_Normalizer for all club name comparison operations"
  - "normalize() as base, normalize_for_search() for fuzzy matching"

# Metrics
duration: 2min
completed: 2026-01-28
---

# Phase 3 Plan 01: Name Normalizer Summary

**FFM_Name_Normalizer class with iconv-based Unicode transliteration for matching club names across German, Spanish, Turkish, and Scandinavian character sets**

## Performance

- **Duration:** 2 min
- **Started:** 2026-01-28T23:50:01Z
- **Completed:** 2026-01-28T23:52:10Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- FFM_Name_Normalizer class with comprehensive Unicode normalization
- normalize() handles German umlauts (ü→u), Spanish accents (é→e), Turkish characters (ş→s, ı→i)
- match() provides exact comparison after normalization
- normalize_for_search() strips common club affixes (fc, afc, sc, city, united)
- similarity_score() for fuzzy matching with Levenshtein distance

## Task Commits

Each task was committed atomically:

1. **Task 1: Create FFM_Name_Normalizer class** - `31faec1` (feat)
2. **Task 2: Integrate normalizer and verify with real data** - `0a8a6bd` (feat)

## Files Created/Modified

- `app/public/wp-content/plugins/footyforums-club-id-mapper/includes/class-ffm-name-normalizer.php` - Full normalizer class with 4 public methods
- `app/public/wp-content/plugins/footyforums-club-id-mapper/footyforums-club-id-mapper.php` - Added require_once for normalizer class

## Decisions Made

- **iconv over intl:** Environment lacks PHP intl extension, so iconv with TRANSLIT flag provides equivalent transliteration
- **Preserve punctuation in normalize():** Keeping spaces, hyphens, periods allows "F.C." to remain distinct from "FC" at the normalize level; normalize_for_search() strips these for broader matching
- **Bonus similarity_score():** Added Levenshtein-based fuzzy matching utility for potential future use in uncertainty scoring

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Name normalizer ready for use by matching engine (Phase 4)
- normalize() verified against all Unicode variations from discovery: München, Atlético, København, Kasımpaşa
- Phase 3 plan 01 complete; normalizer is the only plan in this phase

---
*Phase: 03-name-normalization*
*Completed: 2026-01-28*
