# Phase 1: Schema & Plugin Setup - Context

**Gathered:** 2026-01-28
**Status:** Ready for planning

<vision>
## How This Should Work

Run schema migrations from within the Club ID Mapper admin page — a button click that adds the new table and columns. The key is having a safety net: backup before changes AND down migrations that can reverse each change if something goes wrong.

After running, the admin page shows verification: which columns and tables exist, automatic checks that confirm success or report failures. This isn't a "run and hope" situation — it's visible, verifiable, and reversible.

</vision>

<essential>
## What Must Be Nailed

- **Safe rollback capability** — Nothing breaks existing data. If something goes wrong, we can restore from backup or run down migrations to undo changes.
- **Good backups before any changes** — This is the safety net. Backup is enough protection, but it must be solid.
- **Visible verification** — After running, clearly show what changed and confirm it worked.

</essential>

<specifics>
## Specific Ideas

- Migration controls live in the Club ID Mapper admin page (not a separate page)
- Button to run migrations with status display showing results
- Both backup snapshot AND reversible down migrations (belt and suspenders)
- Auto-verification after migration shows which columns/tables exist
- Keep provenance tracking simple — just source identifier, not full audit trail

</specifics>

<notes>
## Additional Context

Primary concern is breaking existing data. The 337 clubs already mapped plus the broader clubs table must remain intact. Good backups are the main protection — no need for test databases or dry-run modes, just solid backups and reversible migrations.

This phase sets up the foundation for all subsequent import work. If the schema isn't right, nothing else works.

</notes>

---

*Phase: 01-schema-plugin-setup*
*Context gathered: 2026-01-28*
