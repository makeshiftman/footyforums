# Codebase Structure

**Analysis Date:** 2026-01-28

## Directory Layout

```
footyforums/
├── app/                              # Main application root
│   ├── public/                       # WordPress web root
│   │   ├── wp-content/plugins/       # Custom plugins (4 active)
│   │   │   ├── football-data-manager/    # PRIMARY: Data sync engine
│   │   │   ├── footyforums-club-id-mapper/   # Club ID mapping UI
│   │   │   ├── fdm-leagues-admin/        # League management
│   │   │   └── footyforums-db-backup/    # Backup utility
│   │   ├── wp-content/themes/        # WordPress themes
│   │   ├── wp-admin/                 # WordPress admin
│   │   ├── wp-includes/              # WordPress core
│   │   └── wp-config.php             # WordPress configuration
│   ├── tools/                        # CLI tools
│   │   ├── canon/                    # Production-ready scripts
│   │   └── transitional/             # Legacy/experimental
│   ├── sql/                          # Database schemas
│   │   ├── schema/                   # Table definitions
│   │   └── migrations/               # Schema upgrades
│   ├── docs/                         # Documentation
│   │   ├── providers/                # Data source specs
│   │   └── registry/                 # Data mappings
│   ├── manifesto/                    # Project principles
│   ├── backups/                      # Database snapshots
│   └── logs/                         # Application logs
├── conf/                             # Infrastructure config
│   ├── mysql/                        # MySQL configuration
│   ├── nginx/                        # Nginx configuration
│   └── php/                          # PHP configuration
├── logs/                             # System logs
└── .planning/                        # GSD project tracking
```

## Directory Purposes

**app/public/wp-content/plugins/football-data-manager/**
- Purpose: Primary data ingestion plugin
- Contains: PHP classes, admin pages, CLI commands, cron handlers
- Key files:
  - `football-data-manager.php` - Plugin entry point
  - `includes/e_datasource_v2.php` - Main datasource engine (6,305 lines)
  - `includes/class-fdm-daily-updater.php` - Daily sync
  - `includes/class-fdm-e-master-datasource.php` - Historical backfill
  - `includes/db-helper.php` - Database abstraction
  - `includes/wp-cli-commands.php` - CLI interface
- Subdirectories:
  - `includes/` - All PHP includes
  - `includes/admin/` - Admin UI components
  - `includes/ingest/` - Job processing

**app/public/wp-content/plugins/footyforums-club-id-mapper/**
- Purpose: Club identifier mapping workflow
- Contains: Admin page for team ID reconciliation
- Key files:
  - `footyforums-club-id-mapper.php` - Plugin entry
  - `includes/admin-page.php` - Mapping UI
  - `includes/db-footyforums-data.php` - Database helpers

**app/tools/canon/**
- Purpose: Production-ready CLI tools
- Contains: Standalone PHP scripts
- Key files:
  - `run_daily_update.php` - Daily sync entry point
  - `run_espn_backfill.php` - Historical backfill
  - `e_contract_test.php` - API contract validation

**app/tools/transitional/**
- Purpose: Legacy and experimental scripts
- Contains: Bash and PHP utilities
- Key files: 13+ scripts for batch operations, probing, analysis

**app/sql/schema/**
- Purpose: Database schema definitions
- Contains: SQL files for table creation
- Key files:
  - `footyforums_data_schema_live.sql` - Canonical schema (30+ tables)
  - `FIXTURE_UPSERT_VERIFICATION.md` - Validation checklist
  - `SCHEMA_NOTES.md` - Schema documentation

**app/docs/providers/**
- Purpose: Data source specifications
- Contains: Provider documentation (ESPN, Sofascore, etc.)
- Structure per provider:
  - `ENDPOINTS.md` - API contract documentation
  - `CONTRACT_TESTS.md` - Test specifications
  - `KNOWN_LIMITATIONS.md` - Coverage gaps

**app/manifesto/**
- Purpose: Project principles and rules
- Contains: High-level project documentation
- Key files:
  - `manifesto.md` - System principles
  - `footyforumsmanifesto.md` - Project vision
  - `CHANGELOG.md` - Change history

## Key File Locations

**Entry Points:**
- `app/public/wp-content/plugins/football-data-manager/football-data-manager.php` - Plugin init
- `app/tools/canon/run_daily_update.php` - CLI daily sync
- `app/tools/canon/run_espn_backfill.php` - CLI backfill

**Configuration:**
- `app/public/wp-config.php` - WordPress and database config
- `conf/nginx/nginx.conf.hbs` - Nginx server config
- `conf/php/php.ini` - PHP configuration
- `app/tools/transitional/my.cnf` - MySQL CLI config

**Core Logic:**
- `includes/e_datasource_v2.php` - ESPN datasource (6,305 lines)
- `includes/class-fdm-daily-updater.php` - Daily sync
- `includes/class-fdm-e-master-datasource.php` - Backfill engine
- `includes/db-helper.php` - Database helpers
- `includes/ingest/ingest-runner.php` - Job executor

**Testing:**
- `app/tools/canon/e_contract_test.php` - Contract testing
- `app/tools/transitional/probe_*.php` - Data probing utilities

**Documentation:**
- `app/manifesto/` - Project principles
- `app/docs/providers/` - Data source specs
- `app/sql/schema/SCHEMA_NOTES.md` - Database documentation

## Naming Conventions

**Files:**
- `class-{name}.php` - Class definitions (WordPress standard)
- `{name}-helper.php` - Utility functions
- `{provider}_datasource*.php` - Provider-specific code (e.g., `e_datasource_v2.php`)
- `run_{task}.php` - Executable entry points
- `*.hbs` - Handlebars templates

**Directories:**
- `includes/` - PHP includes (WordPress standard)
- `admin/` - Admin-specific code
- `ingest/` - Job processing
- `canon/` - Production-ready tools
- `transitional/` - Legacy/experimental

**Special Patterns:**
- `fdm_*` - Football Data Manager functions
- `FDM_*` - Football Data Manager classes
- `wp_cli_footy_*` - WP-CLI command functions

## Where to Add New Code

**New Feature:**
- Primary code: `app/public/wp-content/plugins/football-data-manager/includes/`
- Admin UI: `includes/admin/`
- Tests: `app/tools/canon/` (contract tests)
- Config if needed: `wp-config.php` constants

**New Data Provider:**
- Implementation: `includes/{provider}_datasource.php`
- Documentation: `app/docs/providers/{provider}/`
- Mapping: Register in `e_datasource_v2.php` league config

**New CLI Command:**
- Definition: `includes/wp-cli-commands.php`
- Handler: Add method to appropriate service class
- Documentation: Add to WP-CLI inline docs

**New Admin Page:**
- Registration: `includes/admin/admin-menu.php`
- Implementation: `includes/admin/{page-name}.php`
- Capability: `manage_options`

**Utilities:**
- Shared helpers: `includes/db-helper.php` or new helper file
- CLI tools: `app/tools/canon/` (production) or `app/tools/transitional/` (experimental)

## Special Directories

**app/public/wp-content/plugins/**
- Purpose: Custom WordPress plugins
- Source: Hand-written code
- Committed: Yes

**app/sql/schema/**
- Purpose: Database schema definitions
- Source: Manual SQL files
- Committed: Yes

**app/tools/transitional/**
- Purpose: Legacy and experimental scripts
- Source: Development utilities
- Committed: Yes (but not production-critical)

**conf/**
- Purpose: Local by Flywheel configuration
- Source: Generated by Local, some customized
- Committed: Partial (templates committed)

---

*Structure analysis: 2026-01-28*
*Update when directory structure changes*
