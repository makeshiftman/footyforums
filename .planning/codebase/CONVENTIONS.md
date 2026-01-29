# Coding Conventions

**Analysis Date:** 2026-01-28

## Naming Patterns

**Files:**
- `class-{name}.php` - Class definitions (e.g., `class-fdm-daily-updater.php`)
- `{name}-helper.php` - Utility functions (e.g., `db-helper.php`)
- `e_*.php` - ESPN-specific code (e.g., `e_datasource_v2.php`)
- `*.test.php` - Not used (no automated tests)
- Shell scripts: `{task_name}.sh` (e.g., `overnight_eng1_2019_2025.sh`)

**Functions:**
- `fdm_*` - Football Data Manager functions (e.g., `fdm_get_footyforums_db()`)
- `wp_cli_footy_*` - WP-CLI command functions
- `snake_case` - All function names (WordPress convention)
- No special prefix for async functions

**Variables:**
- `$snake_case` - Local variables (e.g., `$league_codes`, `$fixture_data`)
- `$this->property` - Instance properties (snake_case)
- `$GLOBALS['key']` - Global state (e.g., `$GLOBALS['fdm_e_supported_leagues']`)
- `UPPER_SNAKE_CASE` - Constants and shell variables

**Types:**
- `FDM_*` - Plugin classes (e.g., `FDM_Daily_Updater`)
- `FDM_E_*` - ESPN-specific classes (e.g., `FDM_E_Datasource_V2`)
- `FDM_Admin_*` - Admin-specific classes
- PascalCase with underscores (WordPress standard)

**Provider Aliases:**
- Use `e_` prefix for ESPN-related code (never "espn" in code)
- Pattern: `e_league_code`, `e_db`, `e_datasource`

## Code Style

**Formatting:**
- 4-space indentation (WordPress standard)
- Single quotes preferred for array keys
- Semicolons required
- Braces on same line as control structure

**Security Checks:**
- Required at file start: `if ( ! defined( 'ABSPATH' ) ) exit;`
- Capability checks on admin functions: `current_user_can( 'manage_options' )`
- Nonce verification in AJAX handlers

**Linting:**
- PHPCS (PHP_CodeSniffer) comments suggest WordPress Coding Standards
- No automated linting configuration in repo
- Manual style enforcement

## Import Organization

**Order:**
1. Security check (`if ( ! defined( 'ABSPATH' ) ) exit;`)
2. `require_once` for dependencies
3. Class definition or function definitions

**Patterns:**
- `require_once __DIR__ . '/path/to/file.php'`
- Conditional loading for admin: `if ( is_admin() ) { require_once ... }`
- WP-CLI loading: `if ( defined( 'WP_CLI' ) && WP_CLI ) { require_once ... }`

**Path Aliases:**
- `__DIR__` - Current file directory
- `FDM_PLUGIN_DIR` - Plugin root constant
- `ABSPATH` - WordPress root

## Error Handling

**Patterns:**
- Log errors via `error_log()` with context
- Custom logging: `fdm_log_datasource_error()` to database
- Continue execution where possible
- Return `false` or empty array on failure

**Database Errors:**
- Check `$wpdb->last_error` after operations
- Log connection failures with debug info
- Fallback to alternative connection methods

**API Errors:**
- Check `is_wp_error()` on remote requests
- Log response codes and bodies
- Store failed requests in `datasource_errors` table

## Logging

**Framework:**
- `error_log()` - Standard PHP logging
- `fdm_log_datasource_error()` - Custom error table
- `fdm_log_datasource_info()` - Custom info table

**Patterns:**
- Include context: function name, parameters, state
- Conditional on WP_DEBUG: `if ( defined( 'WP_DEBUG' ) && WP_DEBUG )`
- Structured data: JSON-encode context arrays

**Levels:**
- Debug: Development-time logging
- Info: Data processing milestones
- Error: Failures requiring attention

## Comments

**When to Comment:**
- File headers with purpose description
- Complex business logic
- Non-obvious algorithms
- TODO items for incomplete work

**PHPDoc:**
- Required for public functions
- Use `@param`, `@return`, `@throws` tags
- Include type hints

**WP-CLI Commands:**
- Structured help: `## OPTIONS`, `## EXAMPLES`, `## DESCRIPTION`
- Parameter descriptions with type and default

**Poetic Names:**
- Major systems have descriptive names: "The Daily Updater", "Platinum Master Datasource Engine"
- Used in class-level docblocks

**TODO Format:**
- `// TODO: description`
- No username prefix (use git blame)

## Function Design

**Size:**
- Keep methods focused
- Extract helpers for repeated logic
- Large files exist (6,305 lines in `e_datasource_v2.php`) - needs refactoring

**Parameters:**
- Use arrays for complex configurations
- Default values for optional parameters
- Destructure arrays in function body

**Return Values:**
- Return `false` on failure
- Return arrays for multi-value results
- Early return for guard clauses

## Module Design

**Exports:**
- Static methods for stateless operations
- Instance methods for stateful operations
- Functions for simple utilities

**Class Patterns:**
- Singleton-like: Global instance in `$GLOBALS`
- Service classes with private DB connections
- Static method collections (no state)

**WordPress Integration:**
- Hooks via `add_action()`, `add_filter()`
- Admin pages via `add_menu_page()`
- CLI commands via `WP_CLI::add_command()`

---

*Convention analysis: 2026-01-28*
*Update when patterns change*
