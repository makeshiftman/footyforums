# Football Data Manager

WordPress plugin for managing football data ingestion, job queues, and datasource operations.

## Ingestion Policy

This plugin owns provider-e ingestion. All ESPN ingestion code must live within this plugin.

**Rules:**
- New endpoints or mapping logic must be documented in `docs/providers/e/ENDPOINTS.md`
- All changes must include a corresponding entry in `docs/providers/e/CHANGELOG.md`
- Do not create standalone ingestion scripts outside this plugin
- Legacy scripts in `public/_legacy/espn_scripts_quarantine/` are reference-only
