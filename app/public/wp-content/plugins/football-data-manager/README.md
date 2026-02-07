# Football Data Manager

WordPress plugin for managing football data ingestion, job queues, and datasource operations.

## Data Collection Overview

The plugin collects **14 data types** from provider e (ESPN):

| Type | Table | Description |
|------|-------|-------------|
| Fixtures | `fixtures` | Match records with scores, dates, status |
| Lineups | `lineups` | Player lineups per match |
| Commentary | `commentary` | Match commentary text |
| Key Events | `keyEvents` | Goals, cards, substitutions |
| Plays | `plays` | Play-by-play data |
| Player Stats | `playerStats` | Per-match player statistics (15 stat types) |
| Team Stats | `teamStats` | Per-match team statistics |
| Teams | `teams` | Club information |
| Players | `players` | Player biographical data |
| Standings | `standings` | League table positions |
| Team Rosters | `teamRoster` | Season squad lists |
| Venues | `venues` | Stadium information |
| Transfers | `transfers` | Player movements with fees |
| Season Stats | `season_player_stats` | Aggregated season statistics |

## Admin Pages

- **FDM Status** - Dashboard showing data collection progress by year and league
- **Manual Verification** - URLs to manually verify when prober can't auto-detect data
- **Wikidata Review** - Review and approve Wikidata ID matches for clubs

## Wikidata Club Mapping

The plugin includes a Wikidata matching system to link clubs to their Wikidata entities:

**How it works:**
1. CLI script searches Wikidata SPARQL endpoint for club matches
2. Auto-approves high-confidence matches (name >= 90%, country match, score >= 75%)
3. Lower-confidence matches queued for manual review in WP admin

**CLI usage:**
```bash
php search-wikidata-matches.php --limit=100 --skip-placeholders --auto-approve
php search-wikidata-matches.php --country=ENG --limit=200
```

**Tables:**
- `wikidata_match_queue` — Candidates awaiting review
- `clubs.wd_id` — Approved Wikidata ID

## Competition Tier System

Tracks which league tier each club plays in per season:

**Tables:**
- `competition_tiers` — Maps competition codes to types and tiers
- `club_seasons` — Tracks club's primary league per season

**Competition types:**
| Type | Description | Tiers |
|------|-------------|-------|
| `lea` | Leagues | 1-5 (Premier League = 1, National League = 5) |
| `dom` | Domestic cups | 1-3 (FA Cup = 1, EFL Trophy = 3) |
| `con` | Continental | 1-3 (Champions League = 1, Conference League = 3) |
| `intl` | International | 1-3 (World Cup = 1, Friendlies = 3) |

**Example queries:**
```sql
-- What tier was Arsenal in 2023?
SELECT primary_tier FROM club_seasons
WHERE club_id = (SELECT id FROM clubs WHERE canonical_name = 'Arsenal')
AND season = '2023';

-- All Premier League clubs in 2024
SELECT c.canonical_name FROM club_seasons cs
JOIN clubs c ON cs.club_id = c.id
WHERE cs.season = '2024' AND cs.primary_tier = 1
AND cs.primary_league_code LIKE 'eng.%';
```

## Team Type Classification

The `clubs` table contains both actual clubs and national teams. The `team_type` column distinguishes between them:

**Column:** `clubs.team_type ENUM('club', 'national', 'national_youth', 'club_youth')`

| Type | Description | Count |
|------|-------------|-------|
| `club` | Regular football clubs (default) | ~3,283 |
| `national` | Senior national teams (England, Brazil, etc.) | 218 |
| `national_youth` | Youth national teams (U-17, U-19, U-20, U-21, U-23) | 192 |
| `club_youth` | Club youth/reserve teams (B teams, U-23 squads) | 24 |

**Usage:**
```sql
-- Get only actual clubs
SELECT * FROM clubs WHERE team_type = 'club';

-- Get all national teams (senior + youth)
SELECT * FROM clubs WHERE team_type IN ('national', 'national_youth');
```

**Note:** Wikidata search only queries `team_type = 'club'` to avoid matching national teams.

## CLI Scripts

Located in `/cli/`:

| Script | Purpose |
|--------|---------|
| `probe-availability.php` | Check what data ESPN has available |
| `historical-scrape.php` | Run full historical data collection |
| `verify-data-quality.php` | Audit data in all tables |
| `clear-probe-data.php` | Reset availability data for fresh probe |
| `test-playerstats-fix.php` | Verify playerStats INSERT works |
| `search-wikidata-matches.php` | Search Wikidata for club matches |

## Ingestion Policy

This plugin owns provider-e ingestion. All ESPN ingestion code must live within this plugin.

**Rules:**
- New endpoints or mapping logic must be documented in `docs/providers/e/ENDPOINTS.md`
- All changes must include a corresponding entry in `docs/providers/e/CHANGELOG.md`
- Do not create standalone ingestion scripts outside this plugin
- Legacy scripts in `public/_legacy/espn_scripts_quarantine/` are reference-only

## Key Files

**ESPN Data Collection:**
- `includes/class-fdm-e-master-datasource.php` - Main scraper with all data collection methods
- `includes/class-fdm-availability-prober.php` - Checks ESPN data availability
- `includes/admin/class-fdm-admin-data-status.php` - Admin dashboard
- `includes/admin/class-fdm-admin-manual-verification.php` - Manual verification page

**Wikidata Mapping:**
- `cli/search-wikidata-matches.php` - SPARQL search with auto-approve logic
- `includes/admin/class-fdm-admin-wikidata-review.php` - Review UI for candidates

## Last Updated

2026-02-07 - Added team type classification documentation
