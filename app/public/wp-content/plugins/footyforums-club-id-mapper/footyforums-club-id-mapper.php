<?php
/**
 * Plugin Name: FootyForums Club ID Mapper
 * Description: One-page workflow to map external team IDs for all clubs (fast data entry UI).
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-ffm-migration-runner.php';
require_once __DIR__ . '/includes/class-ffm-csv-parser.php';
require_once __DIR__ . '/includes/class-ffm-country-mapper.php';
require_once __DIR__ . '/includes/class-ffm-import-runner.php';
require_once __DIR__ . '/includes/class-ffm-name-normalizer.php';
require_once __DIR__ . '/includes/class-ffm-matching-engine.php';
require_once __DIR__ . '/includes/class-ffm-auto-applier.php';
require_once __DIR__ . '/includes/class-ffm-uncertain-queue.php';
require_once __DIR__ . '/includes/admin-page.php';
