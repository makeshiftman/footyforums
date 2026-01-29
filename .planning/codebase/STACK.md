# Technology Stack

**Analysis Date:** 2026-01-28

## Languages

**Primary:**
- PHP 8.2.27 - All application code (`conf/php/php-8.2.27/`)

**Secondary:**
- SQL/MySQL - Database queries and schema definitions
- Bash - Build and migration scripts (`app/tools/transitional/*.sh`)

## Runtime

**Environment:**
- Local by Flywheel - Development environment
- Nginx - Web server with FastCGI PHP support (`conf/nginx/nginx.conf.hbs`)
- PHP-FPM 8.2.27

**Package Manager:**
- None (WordPress plugin architecture, no Composer)
- Manual dependency management via WordPress plugin loading

## Frameworks

**Core:**
- WordPress 6.9 - CMS platform (`app/public/wp-includes/version.php`)
- WordPress Multisite - Multi-blog network configuration

**Testing:**
- None (no automated test framework)
- Custom contract testing via CLI (`app/tools/canon/e_contract_test.php`)

**Build/Dev:**
- Local by Flywheel - Local development environment
- Handlebars templates for config files (`.hbs` extension)

## Key Dependencies

**Critical:**
- WordPress Core APIs - `wp_remote_get()`, `wpdb`, hooks/actions (`app/public/wp-content/plugins/football-data-manager/`)
- MySQLi - Direct MySQL connections for legacy e_db access (`class-fdm-e-master-datasource.php`, `class-fdm-daily-updater.php`)
- WP-CLI - Command-line interface (`includes/wp-cli-commands.php`)

**Infrastructure:**
- MySQL - Database engine (socket-based connection via Local)
- WordPress wpdb class - Primary database abstraction (`includes/db-helper.php`)

**Theme:**
- GeneratePress - WordPress theme with child theme (`app/public/wp-content/themes/generatepress/`)

## Configuration

**Environment:**
- Database credentials in `app/public/wp-config.php`
- FOOTYFORUMS_DB_* constants for external database connection
- Socket-based MySQL connection via Local environment

**Build:**
- `conf/nginx/nginx.conf.hbs` - Nginx server configuration
- `conf/php/php.ini` - PHP configuration
- No build step (WordPress plugin architecture)

## Platform Requirements

**Development:**
- macOS (Local by Flywheel)
- MySQL via socket connection
- No external dependencies beyond Local environment

**Production:**
- Not yet configured (development-only codebase)
- WordPress hosting with MySQL 5.7+
- PHP 8.2+

---

*Stack analysis: 2026-01-28*
*Update after major dependency changes*
