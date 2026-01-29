# Requirements: FootyForums Club ID Mapping Import

**Defined:** 2026-01-28
**Core Value:** Accurate, reconciled football data from multiple sources with clear provenance

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Schema

- [x] **SCHEMA-01**: Create `club_aliases` table with club_id, provider, alias_name, indexed for lookups
- [x] **SCHEMA-02**: Add `fmob_id` column to clubs table (FotMob)
- [x] **SCHEMA-03**: Add `sm_id` column to clubs table (SportMonks)
- [x] **SCHEMA-04**: Add `is_id` column to clubs table (InStat)
- [x] **SCHEMA-05**: Add `sc_id` column to clubs table (SkillCorner)
- [x] **SCHEMA-06**: Add `fmgr_id` column to clubs table (Football Manager)
- [x] **SCHEMA-07**: Add provenance column to track which source provided each mapping

### Data Import

- [x] **IMPORT-01**: Parse CSV file (1,712 rows, ~50 columns)
- [x] **IMPORT-02**: Extract provider IDs: whoScoredId, transfermarktId, sofifaId, optaId, fotmobId, sportmonksId, inStatId, skillCornerId, fmId
- [x] **IMPORT-03**: Extract all name variations from *Name columns
- [x] **IMPORT-04**: Map CSV country field to league prefix (England→eng, Germany→ger, Argentina→arg, etc.)
- [ ] **IMPORT-05**: Import ALL clubs from CSV (European and non-European)

### Matching

- [x] **MATCH-01**: Match CSV rows to clubs by exact name (case-insensitive)
- [x] **MATCH-02**: Match against existing aliases in club_aliases table
- [x] **MATCH-03**: Normalize names for comparison (strip accents, handle common variations)
- [x] **MATCH-04**: Validate matches with country (CSV country must match club's league prefix)
- [x] **MATCH-05**: Auto-apply when exact match + country validation passes
- [x] **MATCH-06**: Queue for manual review when any uncertainty exists

### Review Workflow

- [x] **REVIEW-01**: Display uncertain matches showing CSV data and candidate club(s)
- [x] **REVIEW-02**: Allow approve/reject actions for each uncertain match
- [x] **REVIEW-03**: Support batch review UI (review multiple matches at once)

### Data Updates

- [x] **UPDATE-01**: Update clubs table with all provider IDs (w_id, t_id, sf_id, o_id + 5 new)
- [x] **UPDATE-02**: Insert name variations into club_aliases table
- [ ] **UPDATE-03**: Update club_id_map_tasks status to 'done' after successful import
- [ ] **UPDATE-04**: Never overwrite existing data without confirmation

### Plugin Integration

- [x] **PLUGIN-01**: Add new providers to ffm_get_clubs_column_map() (fotmob, sportmonks, instat, skillcorner, footballmanager)
- [x] **PLUGIN-02**: Add new providers to source codes list in Club ID Mapper
- [x] **PLUGIN-03**: Import process creates/updates club_id_map_tasks records
- [ ] **PLUGIN-04**: Club ID Mapper UI reflects import progress for all providers

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Review Tracking

- **REVIEW-04**: Track decisions (who approved what and when)

### Extended Coverage

- **EXTEND-01**: FBref, StatsBomb, Understat, Wikipedia, Wikidata mappings (not in CSV)

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Automated fuzzy matching without approval | User wants zero false positives |
| New provider integrations | This milestone uses existing CSV data only |

## Traceability

Which phases cover which requirements. Updated by create-roadmap.

| Requirement | Phase | Status |
|-------------|-------|--------|
| SCHEMA-01 | Phase 1 | Complete |
| SCHEMA-02 | Phase 1 | Complete |
| SCHEMA-03 | Phase 1 | Complete |
| SCHEMA-04 | Phase 1 | Complete |
| SCHEMA-05 | Phase 1 | Complete |
| SCHEMA-06 | Phase 1 | Complete |
| SCHEMA-07 | Phase 1 | Complete |
| PLUGIN-01 | Phase 1 | Complete |
| PLUGIN-02 | Phase 1 | Complete |
| IMPORT-01 | Phase 2 | Complete |
| IMPORT-02 | Phase 2 | Complete |
| IMPORT-03 | Phase 2 | Complete |
| IMPORT-04 | Phase 2 | Complete |
| MATCH-03 | Phase 3 | Complete |
| MATCH-01 | Phase 4 | Complete |
| MATCH-02 | Phase 4 | Complete |
| MATCH-04 | Phase 4 | Complete |
| MATCH-05 | Phase 5 | Complete |
| UPDATE-01 | Phase 5 | Complete |
| UPDATE-02 | Phase 5 | Complete |
| MATCH-06 | Phase 6 | Complete |
| PLUGIN-03 | Phase 6 | Complete |
| REVIEW-01 | Phase 7 | Complete |
| REVIEW-02 | Phase 7 | Complete |
| REVIEW-03 | Phase 7 | Complete |
| IMPORT-05 | Phase 8 | Pending |
| UPDATE-03 | Phase 8 | Pending |
| UPDATE-04 | Phase 8 | Pending |
| PLUGIN-04 | Phase 8 | Pending |

**Coverage:**
- v1 requirements: 29 total
- Mapped to phases: 29
- Unmapped: 0 ✓

---
*Requirements defined: 2026-01-28*
*Last updated: 2026-01-29 after Phase 7 completion*
