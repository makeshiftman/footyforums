# FootyForums

## What This Is

FootyForums is a WordPress-based data management platform that powers football fan sites. It handles data ingestion from multiple providers, stores canonical football data (clubs, leagues, fixtures, players), and serves that data via API to front-end club sites like KopThis.com. The platform is the backend/database layer — not a public-facing site itself.

## Core Value

**Accurate, reconciled football data from multiple sources with clear provenance.** Every data point must trace back to a source, and sources must be interchangeable without breaking downstream systems.

## Requirements

### Validated

*Existing capabilities confirmed working in codebase:*

- ✓ ESPN data pipeline with 218 leagues configured — existing
- ✓ Ingest job queue with lease-based execution — existing
- ✓ WP-CLI commands for manual data operations (`wp footy *`) — existing
- ✓ Daily sync via WordPress cron (02:00 UTC) — existing
- ✓ 5-minute cron tick for job processing — existing
- ✓ Admin UI for job management and data status — existing
- ✓ Club ID Mapper plugin with manual mapping workflow — existing
- ✓ Multi-database architecture (e_db → footyforums_data → WordPress) — existing
- ✓ Provider aliasing convention (e_, t_, w_, sf_ — never actual names) — existing
- ✓ Contract testing for ESPN API endpoints — existing
- ✓ Database backup utility — existing

### Active

*Current milestone: Club ID Mapping Import*

- [ ] Create `club_aliases` table to store name variations per club per provider
- [ ] Build CSV import process for provider ID mappings
- [ ] Implement exact-match auto-apply with country validation (CSV country ↔ league prefix)
- [ ] Implement uncertain-match queue for manual approval
- [ ] Populate provider IDs (`w_id`, `t_id`, `sf_id`, `o_id`) in clubs table from CSV
- [ ] Populate name variations in club_aliases table from CSV
- [ ] Update `club_id_map_tasks` status after successful imports
- [ ] Complete club mappings for ALL clubs in CSV (European and worldwide)

### Out of Scope
- FBref, StatsBomb, Understat, Wikipedia, Wikidata mappings — CSV doesn't have these; future manual work
- Automated fuzzy matching without approval — user explicitly wants to review uncertain matches
- New provider integrations — this milestone is about using existing CSV data, not new sources

## Context

### The Opportunity

Professor Ian McHale (formerly of Real Analytics, before Birmingham City acquisition) provided a comprehensive CSV with ~1,712 clubs mapped across multiple providers: WhoScored, Transfermarkt, Sofifa, Opta, FotMob, SportMonks, and others. This is production-quality data that can shortcut months of manual mapping work.

### Current State

- 3,675 clubs in database, 3,648 with ESPN IDs
- Only 337 clubs have any provider mappings currently
- European leagues have ~215 clubs across eng (4 tiers), esp, fra, ger, ita, ned, por, sco
- Club ID Mapper plugin exists for manual mapping but is tedious at scale

### The Challenge

Merging two datasets risks creating false matches. The CSV uses provider names (varying per source), while the database uses ESPN IDs. Join must happen on name + country validation. User wants:
- **Automatic:** Exact name match + country match
- **Manual review:** Any uncertainty whatsoever

### Data Files

- **Source CSV:** `/Users/kevincasey/Local Sites/footyforums/app/docs/providers/mapping.teamsAlias.csv`
- **Club ID Mapper plugin:** `app/public/wp-content/plugins/footyforums-club-id-mapper/`
- **Clubs table schema:** `footyforums_data.clubs`

### CSV → Database Mapping

| CSV Column | → DB Column | Notes |
|------------|-------------|-------|
| `whoScoredId` | `w_id` | WhoScored numeric ID |
| `transfermarktId` | `t_id` | Transfermarkt numeric ID |
| `sofifaId` | `sf_id` | Sofifa numeric ID |
| `optaId` | `o_id` | Opta ID |
| `name`, `*Name` columns | `club_aliases` | All name variations stored |
| `country` | validate against `competition_code` prefix | England → eng.*, Germany → ger.*, etc. |

### Country to League Prefix Mapping

| CSV Country | League Prefix |
|-------------|---------------|
| England | eng |
| Spain | esp |
| Italy | ita |
| France | fra |
| Germany | ger |
| Portugal | por |
| Netherlands | ned |
| Scotland | sco |
| Argentina | arg |
| Brazil | bra |
| (etc.) | (etc.) |

## Constraints

- **Provider aliasing**: Never reference actual provider names in code or database — use aliases (e_, t_, w_, sf_) per manifesto
- **Manual approval for uncertainty**: No automated fuzzy matching that applies without user review
- **Existing schema**: Must work with current `clubs` table structure; new `club_aliases` table extends but doesn't replace
- **All CSV clubs**: Import all clubs from CSV regardless of region

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Separate `club_aliases` table for name variations | Normalized, queryable for matching, supports multiple names per provider, extensible | — Pending |
| Auto-apply only exact match + country validation | User wants zero false positives in automated path | — Pending |
| Queue uncertain matches for manual review | Better to miss matches than create wrong ones | — Pending |
| All CSV clubs included | No reason to filter; import everything available | — Pending |

---
*Last updated: 2026-01-28 after initialization*
