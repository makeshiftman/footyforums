# Phase 17: Soccer API Bootstrap - Context

**Gathered:** 2026-02-03
**Status:** Ready for research

<vision>
## How This Should Work

This is a research-first phase. Before importing anything, I want to understand what Soccer API actually has. Clone the repo, dig through its data structure, and report back what's there.

Key questions to answer:
- Does Soccer API have ESPN IDs? (If yes, we're in great shape for the Rosetta Stone plan)
- What other provider IDs does it have? (FBref, Transfermarkt, Understat, etc.)
- How are teams/clubs structured in their data?
- How many mappings could we potentially import?

Once we understand what's there, we'll have a discussion about the best strategy. If there are ESPN IDs, that's a direct match. If not, we may need to look at using the Professor's CSV data as a bridge instead.

After the investigation, show me 10-20 sample matches so I can verify the mappings look correct before any import happens.

</vision>

<essential>
## What Must Be Nailed

- **Data accuracy over coverage** — Only import mappings we're confident are correct. No false positives. Better to miss matches than create wrong ones.
- **Protect existing data** — Don't overwrite IDs I've already manually verified in the clubs table.
- **Validate incoming data** — Soccer API might have errors. Want sanity checks before trusting it.

</essential>

<specifics>
## Specific Ideas

- Review-first approach: Extract mappings, show samples, then I approve before applying
- ESPN ID matching is the gold standard — if Soccer API has ESPN IDs, use those as the primary link
- If no ESPN IDs in Soccer API, we need to discuss alternative strategies (Professor's CSV, name matching, etc.)
- This phase is investigation + report back, NOT investigation + auto-import

</specifics>

<notes>
## Additional Context

The Rosetta Stone plan depends on having reliable ID bridges. Soccer API claims 82K players mapped across FBref, Transfermarkt, Understat — but we need to verify their team/club mappings are equally good before relying on them.

User preference: Conservative approach. Accuracy > Speed. Manual review for anything uncertain.

</notes>

---

*Phase: 17-soccer-api-bootstrap*
*Context gathered: 2026-02-03*
