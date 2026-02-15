<?php
/**
 * FF_Admin — Competition Manager admin page
 *
 * Provides a WP admin interface to tag competitions with country, region,
 * type, and tier so they sort correctly on the /leagues/ frontend.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Admin {

    /** DB migration version — bump when adding new schema changes */
    const MIGRATION_VERSION = 1;

    /**
     * Country → geographic region mapping.
     * Must stay in sync with the JS version output by render_page().
     */
    private static $country_to_region = array(
        // Europe
        'England'            => 'europe',
        'Scotland'           => 'europe',
        'Wales'              => 'europe',
        'Northern Ireland'   => 'europe',
        'Spain'              => 'europe',
        'Germany'            => 'europe',
        'Italy'              => 'europe',
        'France'             => 'europe',
        'Netherlands'        => 'europe',
        'Portugal'           => 'europe',
        'Belgium'            => 'europe',
        'Austria'            => 'europe',
        'Turkey'             => 'europe',
        'Russia'             => 'europe',
        'Greece'             => 'europe',
        'Sweden'             => 'europe',
        'Norway'             => 'europe',
        'Denmark'            => 'europe',
        'Cyprus'             => 'europe',
        'Ireland'            => 'europe',
        'Switzerland'        => 'europe',
        'Poland'             => 'europe',
        'Czech Republic'     => 'europe',
        'Croatia'            => 'europe',
        'Serbia'             => 'europe',
        'Romania'            => 'europe',
        'Ukraine'            => 'europe',
        'Hungary'            => 'europe',
        'Bulgaria'           => 'europe',
        'Slovakia'           => 'europe',
        'Slovenia'           => 'europe',
        'Finland'            => 'europe',
        'Iceland'            => 'europe',
        'Bosnia and Herzegovina' => 'europe',
        'Albania'            => 'europe',
        'North Macedonia'    => 'europe',
        'Montenegro'         => 'europe',
        'Luxembourg'         => 'europe',
        'Malta'              => 'europe',
        'Georgia'            => 'europe',
        'Armenia'            => 'europe',
        'Azerbaijan'         => 'europe',
        'Belarus'            => 'europe',
        'Moldova'            => 'europe',
        'Kosovo'             => 'europe',
        'Estonia'            => 'europe',
        'Latvia'             => 'europe',
        'Lithuania'          => 'europe',
        'Faroe Islands'      => 'europe',
        'Gibraltar'          => 'europe',
        'Andorra'            => 'europe',
        'Liechtenstein'      => 'europe',
        'San Marino'         => 'europe',
        // South America
        'Argentina'          => 'south_america',
        'Brazil'             => 'south_america',
        'Chile'              => 'south_america',
        'Uruguay'            => 'south_america',
        'Colombia'           => 'south_america',
        'Peru'               => 'south_america',
        'Paraguay'           => 'south_america',
        'Ecuador'            => 'south_america',
        'Venezuela'          => 'south_america',
        'Bolivia'            => 'south_america',
        // North America
        'United States'      => 'north_america',
        'Mexico'             => 'north_america',
        'Honduras'           => 'north_america',
        'Costa Rica'         => 'north_america',
        'Guatemala'          => 'north_america',
        'El Salvador'        => 'north_america',
        'Canada'             => 'north_america',
        'Jamaica'            => 'north_america',
        'Panama'             => 'north_america',
        'Trinidad and Tobago' => 'north_america',
        'Haiti'              => 'north_america',
        'Cuba'               => 'north_america',
        // Asia
        'Saudi Arabia'       => 'asia',
        'Japan'              => 'asia',
        'China'              => 'asia',
        'India'              => 'asia',
        'Indonesia'          => 'asia',
        'Malaysia'           => 'asia',
        'Singapore'          => 'asia',
        'Thailand'           => 'asia',
        'South Korea'        => 'asia',
        'Iran'               => 'asia',
        'Iraq'               => 'asia',
        'Qatar'              => 'asia',
        'UAE'                => 'asia',
        'Uzbekistan'         => 'asia',
        'Vietnam'            => 'asia',
        'Bahrain'            => 'asia',
        'Jordan'             => 'asia',
        'Kuwait'             => 'asia',
        'Oman'               => 'asia',
        'Lebanon'            => 'asia',
        'Syria'              => 'asia',
        'Palestine'          => 'asia',
        'Philippines'        => 'asia',
        'Myanmar'            => 'asia',
        'Cambodia'           => 'asia',
        'Hong Kong'          => 'asia',
        'Tajikistan'         => 'asia',
        'Kyrgyzstan'         => 'asia',
        'Turkmenistan'       => 'asia',
        'Bangladesh'         => 'asia',
        'Nepal'              => 'asia',
        // Africa
        'South Africa'       => 'africa',
        'Nigeria'            => 'africa',
        'Ghana'              => 'africa',
        'Uganda'             => 'africa',
        'Kenya'              => 'africa',
        'Egypt'              => 'africa',
        'Morocco'            => 'africa',
        'Tunisia'            => 'africa',
        'Algeria'            => 'africa',
        'Cameroon'           => 'africa',
        'Ivory Coast'        => 'africa',
        'Senegal'            => 'africa',
        'DR Congo'           => 'africa',
        'Mali'               => 'africa',
        'Zambia'             => 'africa',
        'Zimbabwe'           => 'africa',
        'Tanzania'           => 'africa',
        'Ethiopia'           => 'africa',
        'Mozambique'         => 'africa',
        'Angola'             => 'africa',
        // Oceania
        'Australia'          => 'oceania',
        'New Zealand'        => 'oceania',
        'Fiji'               => 'oceania',
        'Papua New Guinea'   => 'oceania',
    );

    /** Valid display_region values */
    private static $regions = array(
        'europe', 'south_america', 'north_america', 'asia', 'africa', 'oceania', 'international',
    );

    /** Valid comp_type values matching the DB enum */
    private static $comp_types = array(
        'league', 'domestic_cup', 'league_cup', 'super_cup', 'continental_cup', 'other',
    );

    /**
     * Boot: register hooks.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ) );
    }

    /* ------------------------------------------------------------------
     * Admin Menu
     * ----------------------------------------------------------------*/

    public static function register_menu() {
        add_menu_page(
            'FootyForums',
            'FootyForums',
            'manage_options',
            'footyforums',
            array( __CLASS__, 'render_page' ),
            'dashicons-shield',
            30
        );

        add_submenu_page(
            'footyforums',
            'Competitions',
            'Competitions',
            'manage_options',
            'footyforums',
            array( __CLASS__, 'render_page' )
        );
    }

    /* ------------------------------------------------------------------
     * DB Migration
     * ----------------------------------------------------------------*/

    public static function maybe_migrate() {
        $current = (int) get_option( 'ff_migration_version', 0 );
        if ( $current >= self::MIGRATION_VERSION ) {
            return;
        }

        if ( ! function_exists( 'fdm_get_footyforums_db' ) ) {
            return;
        }

        $db = fdm_get_footyforums_db();

        // Check if columns already exist before adding
        $cols = $db->get_results( "SHOW COLUMNS FROM competitions", ARRAY_A );
        $col_names = array_column( $cols, 'Field' );

        if ( ! in_array( 'country_name', $col_names, true ) ) {
            $db->query( "ALTER TABLE competitions ADD COLUMN country_name VARCHAR(100) DEFAULT NULL" );
        }
        if ( ! in_array( 'display_region', $col_names, true ) ) {
            $db->query( "ALTER TABLE competitions ADD COLUMN display_region VARCHAR(50) DEFAULT NULL" );
        }

        // Auto-populate from supported leagues config
        self::seed_from_config( $db );

        update_option( 'ff_migration_version', self::MIGRATION_VERSION );
    }

    /**
     * Populate country_name and display_region from $GLOBALS config
     * for competitions that match, but only where DB values are NULL.
     */
    private static function seed_from_config( $db ) {
        $supported = isset( $GLOBALS['fdm_e_supported_leagues'] ) ? $GLOBALS['fdm_e_supported_leagues'] : array();
        if ( empty( $supported ) ) {
            return;
        }

        foreach ( $supported as $code => $info ) {
            $country = ! empty( $info['country'] ) ? $info['country'] : null;
            $region  = ! empty( $info['region'] ) ? $info['region'] : null;

            if ( ! $country && ! $region ) {
                continue;
            }

            $db->query( $db->prepare(
                "UPDATE competitions
                 SET country_name   = COALESCE(country_name, %s),
                     display_region = COALESCE(display_region, %s)
                 WHERE competition_code = %s",
                $country,
                $region,
                $code
            ) );
        }
    }

    /* ------------------------------------------------------------------
     * Form Handler
     * ----------------------------------------------------------------*/

    private static function handle_save() {
        if ( ! isset( $_POST['ff_comp_nonce'] ) || ! wp_verify_nonce( $_POST['ff_comp_nonce'], 'ff_save_competitions' ) ) {
            return 'Invalid security token. Please try again.';
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return 'You do not have permission to do this.';
        }

        if ( ! function_exists( 'fdm_get_footyforums_db' ) ) {
            return 'Database connection unavailable.';
        }

        $db = fdm_get_footyforums_db();

        $codes        = isset( $_POST['comp_code'] )        ? (array) $_POST['comp_code']        : array();
        $countries    = isset( $_POST['comp_country'] )      ? (array) $_POST['comp_country']      : array();
        $d_regions    = isset( $_POST['comp_region'] )       ? (array) $_POST['comp_region']       : array();
        $types        = isset( $_POST['comp_type'] )         ? (array) $_POST['comp_type']         : array();
        $levels       = isset( $_POST['comp_level'] )        ? (array) $_POST['comp_level']        : array();
        $actives      = isset( $_POST['comp_active'] )       ? (array) $_POST['comp_active']       : array();

        $updated = 0;

        foreach ( $codes as $i => $code ) {
            $code = sanitize_text_field( $code );
            if ( empty( $code ) ) continue;

            $country_val = isset( $countries[ $i ] ) ? sanitize_text_field( $countries[ $i ] ) : '';
            $region_val  = isset( $d_regions[ $i ] )  ? sanitize_text_field( $d_regions[ $i ] )  : '';
            $type_val    = isset( $types[ $i ] )      ? sanitize_text_field( $types[ $i ] )      : '';
            $level_val   = isset( $levels[ $i ] )     ? intval( $levels[ $i ] )                   : null;
            $active_val  = in_array( $code, $actives, true ) ? 1 : 0;

            // Validate region
            if ( $region_val !== '' && ! in_array( $region_val, self::$regions, true ) ) {
                $region_val = '';
            }
            // Validate type
            if ( $type_val !== '' && ! in_array( $type_val, self::$comp_types, true ) ) {
                $type_val = '';
            }

            $db->query( $db->prepare(
                "UPDATE competitions SET
                    country_name   = %s,
                    display_region = %s,
                    comp_type      = %s,
                    level          = %s,
                    active_flag    = %d
                 WHERE competition_code = %s",
                $country_val !== '' ? $country_val : null,
                $region_val  !== '' ? $region_val  : null,
                $type_val    !== '' ? $type_val    : null,
                $level_val   > 0   ? $level_val   : null,
                $active_val,
                $code
            ) );

            $updated++;
        }

        return $updated > 0 ? "Saved {$updated} competitions." : 'No changes to save.';
    }

    /* ------------------------------------------------------------------
     * Admin Page Render
     * ----------------------------------------------------------------*/

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        // Handle save
        $notice = '';
        $notice_type = 'info';
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['ff_comp_nonce'] ) ) {
            $result = self::handle_save();
            $notice = $result;
            $notice_type = strpos( $result, 'Saved' ) !== false ? 'success' : 'error';
        }

        // Fetch all competitions
        if ( ! function_exists( 'fdm_get_footyforums_db' ) ) {
            echo '<div class="wrap"><h1>Competition Manager</h1><div class="notice notice-error"><p>Database connection unavailable. Is the Football Data Manager plugin active?</p></div></div>';
            return;
        }

        $db = fdm_get_footyforums_db();
        $rows = $db->get_results(
            "SELECT competition_code, name, country_code, comp_type, level, region, active_flag,
                    country_name, display_region
             FROM competitions
             ORDER BY name ASC",
            ARRAY_A
        );

        $supported = isset( $GLOBALS['fdm_e_supported_leagues'] ) ? $GLOBALS['fdm_e_supported_leagues'] : array();

        // Gather countries list from our mapping
        $countries = array_keys( self::$country_to_region );
        sort( $countries );

        ?>
        <div class="wrap">
            <h1>Competition Manager</h1>
            <p class="description">Tag competitions with country, region, type and tier so they display correctly on the Leagues page.</p>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
                    <p><?php echo esc_html( $notice ); ?></p>
                </div>
            <?php endif; ?>

            <!-- Filter bar -->
            <div id="ff-filters" style="margin: 15px 0; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <label>
                    Region:
                    <select id="ff-filter-region">
                        <option value="">All</option>
                        <?php foreach ( self::$regions as $r ) : ?>
                            <option value="<?php echo esc_attr( $r ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $r ) ) ); ?></option>
                        <?php endforeach; ?>
                        <option value="__none__">No region set</option>
                    </select>
                </label>
                <label>
                    Type:
                    <select id="ff-filter-type">
                        <option value="">All</option>
                        <?php foreach ( self::$comp_types as $t ) : ?>
                            <option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $t ) ) ); ?></option>
                        <?php endforeach; ?>
                        <option value="__none__">No type set</option>
                    </select>
                </label>
                <label>
                    <input type="checkbox" id="ff-filter-untagged"> Show untagged only
                </label>
                <label>
                    <input type="checkbox" id="ff-filter-active" checked> Active only
                </label>
                <label>
                    Search:
                    <input type="text" id="ff-filter-search" placeholder="Name or code..." style="width: 200px;">
                </label>
                <span id="ff-filter-count" style="margin-left: auto; color: #666;"></span>
            </div>

            <form method="post" id="ff-comp-form">
                <?php wp_nonce_field( 'ff_save_competitions', 'ff_comp_nonce' ); ?>

                <table class="widefat fixed striped" id="ff-comp-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Code</th>
                            <th style="width: 250px;">Name</th>
                            <th style="width: 180px;">Country</th>
                            <th style="width: 150px;">Region</th>
                            <th style="width: 140px;">Type</th>
                            <th style="width: 70px;">Tier</th>
                            <th style="width: 60px;">Active</th>
                            <th style="width: 70px;">Config</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $rows as $idx => $row ) :
                        $code       = $row['competition_code'];
                        $in_config  = isset( $supported[ $code ] );
                        $is_untagged = empty( $row['country_name'] ) && empty( $row['display_region'] ) && ! $in_config;
                        $is_active   = (int) $row['active_flag'] === 1;
                    ?>
                        <tr class="ff-comp-row<?php echo $is_untagged ? ' ff-untagged' : ''; ?>"
                            data-code="<?php echo esc_attr( $code ); ?>"
                            data-region="<?php echo esc_attr( $row['display_region'] ); ?>"
                            data-type="<?php echo esc_attr( $row['comp_type'] ); ?>"
                            data-active="<?php echo $is_active ? '1' : '0'; ?>"
                            data-untagged="<?php echo $is_untagged ? '1' : '0'; ?>">

                            <td>
                                <code><?php echo esc_html( $code ); ?></code>
                                <input type="hidden" name="comp_code[]" value="<?php echo esc_attr( $code ); ?>">
                            </td>
                            <td><?php echo esc_html( $row['name'] ); ?></td>
                            <td>
                                <select name="comp_country[]" class="ff-country-select" style="width: 100%;">
                                    <option value="">— Select —</option>
                                    <?php foreach ( $countries as $c ) : ?>
                                        <option value="<?php echo esc_attr( $c ); ?>"<?php selected( $row['country_name'], $c ); ?>><?php echo esc_html( $c ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="comp_region[]" class="ff-region-select" style="width: 100%;">
                                    <option value="">— Select —</option>
                                    <?php foreach ( self::$regions as $r ) : ?>
                                        <option value="<?php echo esc_attr( $r ); ?>"<?php selected( $row['display_region'], $r ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $r ) ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="comp_type[]" style="width: 100%;">
                                    <option value="">— Select —</option>
                                    <?php foreach ( self::$comp_types as $t ) : ?>
                                        <option value="<?php echo esc_attr( $t ); ?>"<?php selected( $row['comp_type'], $t ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $t ) ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="comp_level[]" min="1" max="10"
                                       value="<?php echo esc_attr( $row['level'] ); ?>"
                                       style="width: 60px;">
                            </td>
                            <td style="text-align: center;">
                                <input type="checkbox" name="comp_active[]"
                                       value="<?php echo esc_attr( $code ); ?>"
                                       <?php checked( $is_active ); ?>>
                            </td>
                            <td style="text-align: center;">
                                <?php if ( $in_config ) : ?>
                                    <span title="In supported leagues config" style="color: #2271b1; cursor: help;">&#x2713;</span>
                                <?php else : ?>
                                    <span style="color: #ccc;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="Save Changes">
                </p>
            </form>
        </div>

        <style>
            #ff-comp-table .ff-untagged { background: #fff8e5 !important; }
            #ff-comp-table .ff-untagged:nth-child(odd) { background: #fff3cc !important; }
            #ff-comp-table .ff-changed { outline: 2px solid #2271b1; outline-offset: -2px; }
            #ff-comp-table select, #ff-comp-table input[type="number"] { font-size: 12px; padding: 2px 4px; }
            #ff-comp-table td { vertical-align: middle; padding: 4px 8px; }
            #ff-comp-table code { font-size: 11px; background: #f0f0f1; padding: 2px 5px; border-radius: 3px; }
            #ff-filters select, #ff-filters input[type="text"] { padding: 4px 8px; }
        </style>

        <script>
        (function() {
            // Country → region auto-fill map
            var countryToRegion = <?php echo wp_json_encode( self::$country_to_region ); ?>;

            // Country select → auto-fill region
            document.querySelectorAll('.ff-country-select').forEach(function(sel) {
                sel.addEventListener('change', function() {
                    var row = this.closest('tr');
                    var regionSel = row.querySelector('.ff-region-select');
                    var country = this.value;
                    if (country && countryToRegion[country]) {
                        regionSel.value = countryToRegion[country];
                    }
                    row.classList.add('ff-changed');
                });
            });

            // Mark changed rows
            document.querySelectorAll('#ff-comp-table select, #ff-comp-table input').forEach(function(el) {
                el.addEventListener('change', function() {
                    this.closest('tr').classList.add('ff-changed');
                });
            });

            // Filtering
            var filterRegion  = document.getElementById('ff-filter-region');
            var filterType    = document.getElementById('ff-filter-type');
            var filterUntagged = document.getElementById('ff-filter-untagged');
            var filterActive  = document.getElementById('ff-filter-active');
            var filterSearch  = document.getElementById('ff-filter-search');
            var filterCount   = document.getElementById('ff-filter-count');

            function applyFilters() {
                var rows = document.querySelectorAll('.ff-comp-row');
                var region = filterRegion.value;
                var type = filterType.value;
                var untaggedOnly = filterUntagged.checked;
                var activeOnly = filterActive.checked;
                var search = filterSearch.value.toLowerCase().trim();
                var visible = 0;
                var total = rows.length;

                rows.forEach(function(row) {
                    var show = true;

                    // Region filter
                    if (region) {
                        var rowRegion = row.querySelector('.ff-region-select').value;
                        if (region === '__none__') {
                            if (rowRegion !== '') show = false;
                        } else {
                            if (rowRegion !== region) show = false;
                        }
                    }

                    // Type filter
                    if (show && type) {
                        var rowType = row.querySelector('select[name="comp_type[]"]').value;
                        if (type === '__none__') {
                            if (rowType !== '') show = false;
                        } else {
                            if (rowType !== type) show = false;
                        }
                    }

                    // Untagged filter — show only rows that were originally untagged
                    if (show && untaggedOnly) {
                        if (row.dataset.untagged !== '1') show = false;
                    }

                    // Active filter
                    if (show && activeOnly) {
                        var activeCheck = row.querySelector('input[name="comp_active[]"]');
                        if (!activeCheck.checked) show = false;
                    }

                    // Search filter
                    if (show && search) {
                        var code = row.dataset.code.toLowerCase();
                        var name = row.children[1].textContent.toLowerCase();
                        if (code.indexOf(search) === -1 && name.indexOf(search) === -1) show = false;
                    }

                    row.style.display = show ? '' : 'none';
                    if (show) visible++;
                });

                filterCount.textContent = 'Showing ' + visible + ' of ' + total;
            }

            filterRegion.addEventListener('change', applyFilters);
            filterType.addEventListener('change', applyFilters);
            filterUntagged.addEventListener('change', applyFilters);
            filterActive.addEventListener('change', applyFilters);
            filterSearch.addEventListener('input', applyFilters);

            // Initial count
            applyFilters();
        })();
        </script>
        <?php
    }
}
