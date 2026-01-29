# Testing Patterns

**Analysis Date:** 2026-01-28

## Test Framework

**Runner:**
- None (no automated test framework)
- Custom contract testing via CLI

**Assertion Library:**
- Manual assertions in contract tests
- No formal assertion library

**Run Commands:**
```bash
php app/tools/canon/e_contract_test.php          # Run contract tests
wp footy e_sync_leagues                          # Manual verification via CLI
# No unit test command available
```

## Test File Organization

**Location:**
- Contract tests: `app/tools/canon/e_contract_test.php`
- Probe scripts: `app/tools/transitional/probe_*.php`
- No colocated test files (no `*.test.php` pattern)

**Naming:**
- `e_contract_test.php` - API contract validation
- `probe_*.php` - Data exploration utilities

**Structure:**
```
app/
  tools/
    canon/
      e_contract_test.php     # Contract testing
    transitional/
      probe_transfers.php     # Data probing
      probe_daily_feed.php    # Feed exploration
```

## Test Structure

**Contract Test Organization:**
```php
// app/tools/canon/e_contract_test.php

// Configuration
$config = [
    'event_ids' => [...],
    'seasons' => [...],
    'output_file' => '_out/e_contract_test_results.json'
];

// Test execution
foreach ($endpoints as $endpoint) {
    $response = fetch_endpoint($endpoint);
    $result = validate_response($response, $expected_keys);
    $results[] = $result;
}

// Results output
file_put_contents($output_file, json_encode($results));
```

**Patterns:**
- Configuration-driven tests (event IDs, seasons defined in code)
- Curl-based HTTP requests
- JSON validation against expected keys
- Results written to JSON file

## Mocking

**Framework:**
- Not applicable (no automated tests)

**What to Mock (if tests existed):**
- ESPN API responses
- Database connections
- WordPress functions

**Current Approach:**
- No mocking
- Tests hit real APIs (contract tests)
- Manual verification against live data

## Fixtures and Factories

**Test Data:**
- Event IDs hardcoded in contract test config
- Season years defined in test setup
- No factory pattern implemented

**Location:**
- Inline in test files
- No separate fixtures directory

## Coverage

**Requirements:**
- No coverage targets (no automated tests)
- Manual verification of critical paths

**Configuration:**
- Not applicable

**Current State:**
- No unit test coverage
- Contract tests cover ESPN API endpoints
- WP-CLI commands serve as integration verification

## Test Types

**Unit Tests:**
- Not implemented
- Would test: datasource methods, data transformations, helpers

**Integration Tests:**
- Not implemented formally
- WP-CLI commands provide manual integration verification

**Contract Tests:**
- `app/tools/canon/e_contract_test.php`
- Validates ESPN API response structure
- Checks for required JSON keys
- Records HTTP status codes

**E2E Tests:**
- Not implemented
- Manual testing via WordPress admin UI

## Common Patterns

**Contract Testing:**
```php
// Fetch and validate API response
function validate_response($response, $expected_keys) {
    $data = json_decode($response, true);

    if (!$data) {
        return ['status' => 'error', 'message' => 'Invalid JSON'];
    }

    $missing = array_diff($expected_keys, array_keys($data));

    if (!empty($missing)) {
        return ['status' => 'fail', 'missing' => $missing];
    }

    return ['status' => 'pass'];
}
```

**Manual Verification:**
```bash
# Run sync and check results
wp footy e_sync_leagues eng.1

# Verify database state
mysql -e "SELECT COUNT(*) FROM fixtures WHERE league_code = 'eng.1'"
```

**Data Validation (in code):**
```php
// Validation in service methods
if (empty($league_code)) {
    error_log('Invalid league code');
    return false;
}

// Response validation
if (is_wp_error($response)) {
    fdm_log_datasource_error('api_error', $response->get_error_message());
    return false;
}
```

**Snapshot Testing:**
- Not used

## Verification Checklists

Manual verification documented in:
- `app/sql/schema/FIXTURE_UPSERT_VERIFICATION.md` - Fixture sync verification steps
- WP-CLI command output tables for visual inspection

## Recommendations for Testing

**Priority 1 - Contract Tests:**
- Expand `e_contract_test.php` to cover all ESPN endpoints
- Add automated running to CI/CD (when implemented)

**Priority 2 - Unit Tests:**
- Add PHPUnit configuration
- Test `fdm_get_footyforums_db()` connection logic
- Test data transformation methods in `e_datasource_v2.php`

**Priority 3 - Integration Tests:**
- Test full sync workflow with mock API responses
- Verify database state after sync operations

---

*Testing analysis: 2026-01-28*
*Update when test patterns change*
