<?php
/**
 * Plugin Name: FDM Leagues Admin
 * Description: Admin UI for Regions -> Countries -> Leagues -> Clubs (footyforums_data).
 * Version: 0.5.0
 * Author: Kevin Casey
 */

if (!defined('ABSPATH')) { exit; }

final class FDM_Leagues_Admin {
  const MENU_SLUG = 'fdm-leagues-admin';
  const DATA_DB_NAME = 'footyforums_data';

  public function __construct() {
    add_action('admin_menu', [$this, 'register_menu']);
    add_action('admin_init', [$this, 'handle_post']);
    add_action('admin_head', [$this, 'add_admin_styles']);
  }

  public function register_menu() {
    add_menu_page(
      'Leagues',
      'Leagues',
      'manage_options',
      self::MENU_SLUG,
      [$this, 'render_router'],
      'dashicons-awards',
      58
    );
  }

  public function add_admin_styles() {
    echo '<style>
.fdm-season-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 8px 0 16px 0;
}
.fdm-season-chip {
  display: inline-block;
  padding: 2px 8px;
  border: 1px solid #c3c4c7;
  border-radius: 999px;
  background: #fff;
  font-size: 12px;
  line-height: 20px;
}
a.fdm-season-chip {
  text-decoration: none;
  color: inherit;
}
a.fdm-season-chip:hover {
  background: #f0f0f1;
  border-color: #8c8f94;
}
</style>';
  }

  private function db_data() {
    global $wpdb;
    // Use FOOTYFORUMS_DB_* constants if defined, fall back to DB_* constants
    $user = defined( 'FOOTYFORUMS_DB_USER' ) ? FOOTYFORUMS_DB_USER : $wpdb->dbuser;
    $pass = defined( 'FOOTYFORUMS_DB_PASSWORD' ) ? FOOTYFORUMS_DB_PASSWORD : $wpdb->dbpassword;
    $host = defined( 'FOOTYFORUMS_DB_HOST' ) ? FOOTYFORUMS_DB_HOST : $wpdb->dbhost;
    $db_name = defined( 'FOOTYFORUMS_DB_NAME' ) ? FOOTYFORUMS_DB_NAME : self::DATA_DB_NAME;
    $db = new wpdb($user, $pass, $db_name, $host);
    $db->set_charset($db->dbh, $wpdb->charset);
    return $db;
  }

  private function admin_url_page($params = []) {
    $base = admin_url('admin.php?page=' . self::MENU_SLUG);
    if (!$params) return $base;
    return add_query_arg($params, $base);
  }

  public function handle_post() {
    // Return immediately unless conditions are met
    if (!is_admin()) return;
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!isset($_GET['page']) || $_GET['page'] !== self::MENU_SLUG) return;
    if (!isset($_POST['fdm_action']) || $_POST['fdm_action'] !== 'save_club') return;
    
    // Enforce permissions
    if (!current_user_can('manage_options')) return;
    
    // Validate club_id
    $club_id = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
    if ($club_id <= 0) return;
    
    // Validate nonce
    check_admin_referer('fdm_save_club_' . $club_id);
    
    $save_error = null;
    
    // Helper function to normalize hex colors
    $normalise_hex = function($raw) {
      $value = isset($raw) ? sanitize_text_field($raw) : '';
      $value = trim($value);
      
      // Empty string returns [null, null] (no error)
      if ($value === '') {
        return array(null, null);
      }
      
      // If matches exactly 6 hex chars without #, prepend # and uppercase to "#RRGGBB"
      if (preg_match('/^[0-9A-Fa-f]{6}$/', $value)) {
        return array('#' . strtoupper($value), null);
      }
      
      // If starts with # and has 6 hex chars, uppercase entire string to "#RRGGBB"
      if (preg_match('/^#[0-9A-Fa-f]{6}$/i', $value)) {
        return array(strtoupper($value), null);
      }
      
      // Invalid format
      return array(null, 'Invalid hex colour. Use RRGGBB or #RRGGBB.');
    };
    
    // Normalize primary_colour_hex
    $primary_result = $normalise_hex($_POST['primary_colour_hex'] ?? '');
    $primary_colour = $primary_result[0];
    $primary_error = $primary_result[1];
    
    if ($primary_error) {
      $save_error = 'Primary colour: ' . $primary_error;
    }
    
    // Normalize secondary_colour_hex (only if primary was valid)
    $secondary_colour = null;
    if (!$save_error) {
      $secondary_result = $normalise_hex($_POST['secondary_colour_hex'] ?? '');
      $secondary_colour = $secondary_result[0];
      $secondary_error = $secondary_result[1];
      
      if ($secondary_error) {
        $save_error = 'Secondary colour: ' . $secondary_error;
      }
    }
    
    if (!$save_error) {
      // Sanitize and prepare data
      $data = array();
      $format = array();
      
      // Required field
      $data['canonical_name'] = sanitize_text_field($_POST['canonical_name'] ?? '');
      $format[] = '%s';
      
      // Optional text fields
      $optional_fields = array(
        'short_name', 'abbreviation', 'slug', 'home_city', 'country',
        'e_team_id', 'ef_team_id', 'f_id', 't_id', 'sf_id'
      );
      
      foreach ($optional_fields as $field) {
        $value = isset($_POST[$field]) ? trim(sanitize_text_field($_POST[$field])) : '';
        $data[$field] = $value === '' ? null : $value;
        $format[] = '%s';
      }
      
      // Uppercase country
      if (!empty($data['country'])) {
        $data['country'] = strtoupper($data['country']);
      }
      
      // Hex colours (already normalized to NULL or "#RRGGBB")
      $data['primary_colour_hex'] = $primary_colour;
      $format[] = '%s';
      $data['secondary_colour_hex'] = $secondary_colour;
      $format[] = '%s';
      
      // Checkboxes
      $data['needs_mapping'] = isset($_POST['needs_mapping']) ? 1 : 0;
      $format[] = '%d';
      $data['is_locked'] = isset($_POST['is_locked']) ? 1 : 0;
      $format[] = '%d';
      
      // Validate canonical_name is not empty
      if (empty($data['canonical_name'])) {
        $save_error = 'canonical_name is required';
      } else {
        // Update database
        $db = $this->db_data();
        $where = array('id' => $club_id);
        $where_format = array('%d');
        
        $result = $db->update('clubs', $data, $where, $format, $where_format);
        
        if ($result === false) {
          $save_error = 'Database update failed: ' . $db->last_error;
        }
      }
    }
    
    // Get URL parameters for redirect
    $region_group_code = isset($_GET['region']) ? $this->skey($_GET['region']) : '';
    $country_code = isset($_GET['country']) ? $this->country_code($_GET['country']) : '';
    $competition_code = isset($_GET['competition']) ? $this->skey($_GET['competition']) : '';
    
    if ($save_error) {
      // Store error in transient
      $transient_key = 'fdm_leagues_admin_save_error_' . get_current_user_id();
      set_transient($transient_key, $save_error, 30);
      
      // Redirect with saved=0
      $redirect_url = $this->admin_url_page(array(
        'view' => 'club',
        'club_id' => $club_id,
        'region' => $region_group_code,
        'country' => $country_code,
        'competition' => $competition_code,
        'saved' => '0'
      ));
      wp_safe_redirect($redirect_url);
      exit;
    } else {
      // Success - redirect with saved=1
      $redirect_url = $this->admin_url_page(array(
        'view' => 'club',
        'club_id' => $club_id,
        'region' => $region_group_code,
        'country' => $country_code,
        'competition' => $competition_code,
        'saved' => '1'
      ));
      wp_safe_redirect($redirect_url);
      exit;
    }
  }

  private function skey($v) {
    $v = is_string($v) ? $v : '';
    return preg_replace('/[^a-z0-9_\.]/', '', strtolower($v));
  }

  private function country_code($v) {
    $v = is_string($v) ? $v : '';
    $v = preg_replace('/[^A-Za-z]/', '', $v);
    return strtoupper($v);
  }

  private function get_regions(&$error_msg = null) {
    $db = $this->db_data();
    $sql = "
      SELECT
        region_group_code,
        region_name,
        countries_in_region,
        competitions_in_region
      FROM v_admin_regions
      ORDER BY sort_order, region_group_code
    ";
    $rows = $db->get_results($sql);
    if ($db->last_error) $error_msg = $db->last_error;
    return $rows;
  }

  private function get_countries($region_group_code, &$error_msg = null) {
    $db = $this->db_data();
    $sql = $db->prepare("
      SELECT
        region_group_code,
        region_name,
        country_code,
        competitions_total,
        leagues_total,
        cups_total
      FROM v_admin_countries
      WHERE region_group_code = %s
      ORDER BY country_code
    ", $region_group_code);
    $rows = $db->get_results($sql);
    if ($db->last_error) $error_msg = $db->last_error;
    return $rows;
  }

  private function get_competitions($country_code, &$error_msg = null) {
    $db = $this->db_data();
    $sql = $db->prepare("
      SELECT
        vc.region_group_code,
        vc.region_name,
        vc.sort_order,
        c.country_code,
        c.competition_code,
        c.name AS competition_name,
        c.level,
        c.comp_type,
        c.active_flag,
        mr.most_recent_season_year,
        COALESCE(latest.distinct_clubs, 0) AS distinct_clubs_latest,
        COALESCE(latest.club_season_rows, 0) AS club_season_rows_latest
      FROM competitions c
      JOIN (
        SELECT
          region_group_code,
          region_name,
          sort_order,
          country_code
        FROM v_admin_countries
      ) vc
        ON vc.country_code = c.country_code
      LEFT JOIN (
        SELECT competition_code, MAX(season_year) AS most_recent_season_year
        FROM club_seasons
        GROUP BY competition_code
      ) mr
        ON mr.competition_code = c.competition_code
      LEFT JOIN (
        SELECT
          cs.competition_code,
          cs.season_year,
          COUNT(*) AS club_season_rows,
          COUNT(DISTINCT cs.club_id) AS distinct_clubs
        FROM club_seasons cs
        GROUP BY cs.competition_code, cs.season_year
      ) latest
        ON latest.competition_code = c.competition_code
       AND latest.season_year = mr.most_recent_season_year
      WHERE c.comp_type = 'league'
        AND c.country_code = %s
      ORDER BY
        CASE WHEN c.level IS NULL THEN 9999 ELSE c.level END,
        c.name
    ", $country_code);
    $rows = $db->get_results($sql);
    if ($db->last_error) $error_msg = $db->last_error;
    return $rows;
  }

  private function get_competition_clubs_latest($competition_code, &$error_msg = null) {
    $db = $this->db_data();
    $sql = $db->prepare("
      SELECT
        cs.competition_code,
        mr.most_recent_season_year AS season_year,
        c.country_code,
        c.name AS competition_name,
        c.level,
        cl.id AS club_id,
        cl.canonical_name,
        cl.short_name,
        cl.country,
        cl.home_city,
        cl.needs_mapping,
        cl.is_locked,
        (cl.e_team_id IS NOT NULL) AS has_e_id,
        (cl.ef_team_id IS NOT NULL) AS has_ef_team_id,
        (cl.f_id IS NOT NULL) AS has_f_id,
        (cl.t_id IS NOT NULL) AS has_t_id,
        (cl.sf_id IS NOT NULL) AS has_sf_id,
        cl.logo_url_primary,
        COALESCE(NULLIF(cl.country,''), c.country_code) AS club_country
      FROM
        (SELECT competition_code, MAX(season_year) AS most_recent_season_year
         FROM club_seasons
         GROUP BY competition_code) mr
      JOIN club_seasons cs
        ON cs.competition_code = mr.competition_code
       AND cs.season_year = mr.most_recent_season_year
      JOIN competitions c
        ON c.competition_code = cs.competition_code
      JOIN clubs cl
        ON cl.id = cs.club_id
      WHERE c.comp_type = 'league'
        AND cs.competition_code = %s
      ORDER BY cl.canonical_name
    ", $competition_code);

    $rows = $db->get_results($sql);
    if ($db->last_error) $error_msg = $db->last_error;
    return $rows;
  }

  private function get_club_by_id($club_id, &$error_msg = null) {
    $db = $this->db_data();
    $sql = $db->prepare("
      SELECT
        id,
        canonical_name,
        full_name,
        short_name,
        abbreviation,
        slug,
        active_flag,
        logo_url_primary,
        logo_url_alt,
        primary_colour_hex,
        secondary_colour_hex,
        e_venue_id,
        home_city,
        country,
        needs_mapping,
        ef_team_id,
        s_id,
        w_id,
        e_league_code,
        t_id,
        sf_id,
        is_locked,
        e_team_id,
        f_id,
        fds_id,
        former_team_names,
        founding_member_flag,
        prem_ever_flag,
        dissolved,
        first_appearance_year
      FROM clubs
      WHERE id = %d
      LIMIT 1
    ", $club_id);

    $row = $db->get_row($sql);
    if ($db->last_error) $error_msg = $db->last_error;
    return $row;
  }

  private function get_club_history($club_id, &$error_msg = null) {
    $db = $this->db_data();
    $sql = $db->prepare("
      SELECT
        c.competition_code,
        c.name AS competition_name,
        c.country_code,
        c.level,
        c.comp_type,
        cs.season_year
      FROM club_seasons cs
      JOIN competitions c ON c.competition_code = cs.competition_code
      WHERE cs.club_id = %d
      ORDER BY
        c.name ASC,
        CASE WHEN c.level IS NULL THEN 9999 ELSE c.level END ASC,
        cs.season_year DESC
    ", $club_id);

    $rows = $db->get_results($sql);
    if ($db->last_error) $error_msg = $db->last_error;
    return $rows;
  }


  public function render_router() {
    $view = isset($_GET['view']) ? $this->skey($_GET['view']) : 'regions';

    if ($view === 'countries') {
      $region = isset($_GET['region']) ? $this->skey($_GET['region']) : '';
      $this->render_countries($region);
      return;
    }

    if ($view === 'competitions') {
      $region = isset($_GET['region']) ? $this->skey($_GET['region']) : '';
      $country = isset($_GET['country']) ? $this->country_code($_GET['country']) : '';
      $this->render_competitions($region, $country);
      return;
    }

    if ($view === 'league') {
      $region = isset($_GET['region']) ? $this->skey($_GET['region']) : '';
      $country = isset($_GET['country']) ? $this->country_code($_GET['country']) : '';
      $comp = isset($_GET['competition']) ? $this->skey($_GET['competition']) : '';
      $this->render_league($region, $country, $comp);
      return;
    }

    if ($view === 'club') {
      // Support both 'club' and 'club_id' for backward compatibility
      $club_id = isset($_GET['club']) ? intval($_GET['club']) : (isset($_GET['club_id']) ? intval($_GET['club_id']) : 0);
      $region = isset($_GET['region']) ? $this->skey($_GET['region']) : '';
      $country = isset($_GET['country']) ? $this->country_code($_GET['country']) : '';
      $comp = isset($_GET['competition']) ? $this->skey($_GET['competition']) : '';
      $this->render_club($club_id, $region, $country, $comp);
      return;
    }


    $this->render_regions();
  }

  public function render_regions() {
    $error = null;
    $regions = $this->get_regions($error);

    echo '<div class="wrap">';
    echo '<h1>Leagues</h1>';

    if ($error) {
      echo '<div class="notice notice-error"><p><strong>DB error:</strong> ' . esc_html($error) . '</p></div>';
      echo '</div>';
      return;
    }

    if (empty($regions)) {
      echo '<p>No regions found.</p>';
      echo '</div>';
      return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Region</th><th>Countries</th><th>Competitions</th></tr></thead><tbody>';

    foreach ($regions as $r) {
      $link = $this->admin_url_page([
        'view' => 'countries',
        'region' => $r->region_group_code,
      ]);

      echo '<tr>';
      echo '<td><a href="' . esc_url($link) . '">' . esc_html($r->region_name) . '</a></td>';
      echo '<td>' . intval($r->countries_in_region) . '</td>';
      echo '<td>' . intval($r->competitions_in_region) . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table></div>';
  }

  public function render_countries($region_group_code) {
    echo '<div class="wrap">';
    echo '<h1>Leagues</h1>';

    if (!$region_group_code) {
      echo '<div class="notice notice-error"><p><strong>Error:</strong> Missing region.</p></div>';
      echo '<p><a href="' . esc_url($this->admin_url_page()) . '">Back to regions</a></p>';
      echo '</div>';
      return;
    }

    $error = null;
    $rows = $this->get_countries($region_group_code, $error);

    if ($error) {
      echo '<div class="notice notice-error"><p><strong>DB error:</strong> ' . esc_html($error) . '</p></div>';
      echo '<p><a href="' . esc_url($this->admin_url_page()) . '">Back to regions</a></p>';
      echo '</div>';
      return;
    }

    $region_name = !empty($rows) ? $rows[0]->region_name : $region_group_code;

    echo '<h2>Region: ' . esc_html($region_name) . '</h2>';
    echo '<p><a href="' . esc_url($this->admin_url_page()) . '">Back to regions</a></p>';

    if (empty($rows)) {
      echo '<p>No countries found for this region.</p>';
      echo '</div>';
      return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Country code</th><th>Competitions</th><th>Leagues</th><th>Cups</th></tr></thead><tbody>';

    foreach ($rows as $c) {
      $link = $this->admin_url_page([
        'view' => 'competitions',
        'region' => $region_group_code,
        'country' => $c->country_code,
      ]);

      echo '<tr>';
      echo '<td><a href="' . esc_url($link) . '">' . esc_html($c->country_code) . '</a></td>';
      echo '<td>' . intval($c->competitions_total) . '</td>';
      echo '<td>' . intval($c->leagues_total) . '</td>';
      echo '<td>' . intval($c->cups_total) . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table></div>';
  }

  public function render_competitions($region_group_code, $country_code) {
    echo '<div class="wrap">';
    echo '<h1>Leagues</h1>';

    if (!$region_group_code || !$country_code) {
      echo '<div class="notice notice-error"><p><strong>Error:</strong> Missing region or country.</p></div>';
      echo '<p><a href="' . esc_url($this->admin_url_page()) . '">Back to regions</a></p>';
      echo '</div>';
      return;
    }

    $error = null;
    $rows = $this->get_competitions($country_code, $error);

    if ($error) {
      echo '<div class="notice notice-error"><p><strong>DB error:</strong> ' . esc_html($error) . '</p></div>';
      echo '<p><a href="' . esc_url($this->admin_url_page(['view'=>'countries','region'=>$region_group_code])) . '">Back to countries</a></p>';
      echo '</div>';
      return;
    }

    $region_name = !empty($rows) ? $rows[0]->region_name : $region_group_code;

    echo '<h2>Region: ' . esc_html($region_name) . ' | Country: ' . esc_html($country_code) . '</h2>';
    echo '<p><a href="' . esc_url($this->admin_url_page(['view'=>'countries','region'=>$region_group_code])) . '">Back to countries</a></p>';

    if (empty($rows)) {
      echo '<p>No leagues found for this country.</p>';
      echo '</div>';
      return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>League</th><th>Competition code</th><th>Tier</th><th>Most recent season</th><th>Clubs</th></tr></thead><tbody>';

    foreach ($rows as $r) {
      $tier_label = is_null($r->level) ? '' : ('Tier ' . intval($r->level));
      $season_label = is_null($r->most_recent_season_year) ? '' : intval($r->most_recent_season_year);

      $league_link = $this->admin_url_page([
        'view' => 'league',
        'region' => $region_group_code,
        'country' => $country_code,
        'competition' => $r->competition_code,
      ]);

      echo '<tr>';
      echo '<td><a href="' . esc_url($league_link) . '">' . esc_html($r->competition_name) . '</a></td>';
      echo '<td>' . esc_html($r->competition_code) . '</td>';
      echo '<td>' . esc_html($tier_label) . '</td>';
      echo '<td>' . esc_html($season_label) . '</td>';
      echo '<td>' . intval($r->distinct_clubs_latest) . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table></div>';
  }

  public function render_league($region_group_code, $country_code, $competition_code) {
    echo '<div class="wrap">';
    echo '<h1>Leagues</h1>';

    if (!$region_group_code || !$country_code || !$competition_code) {
      echo '<div class="notice notice-error"><p><strong>Error:</strong> Missing region, country, or competition.</p></div>';
      echo '<p><a href="' . esc_url($this->admin_url_page()) . '">Back to regions</a></p>';
      echo '</div>';
      return;
    }

    $error = null;
    $clubs = $this->get_competition_clubs_latest($competition_code, $error);

    echo '<h2>Region: ' . esc_html($region_group_code) . ' | Country: ' . esc_html($country_code) . '</h2>';
    echo '<p><a href="' . esc_url($this->admin_url_page(['view'=>'competitions','region'=>$region_group_code,'country'=>$country_code])) . '">Back to leagues</a></p>';

    if ($error) {
      echo '<div class="notice notice-error"><p><strong>DB error:</strong> ' . esc_html($error) . '</p></div>';
      echo '</div>';
      return;
    }

    if (empty($clubs)) {
      echo '<p>No clubs found for this league.</p>';
      echo '</div>';
      return;
    }

    $comp_name = $clubs[0]->competition_name;
    $season_year = $clubs[0]->season_year;

    echo '<h2>' . esc_html($comp_name) . ' | Season ' . esc_html($season_year) . '</h2>';

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>
      <th>Badge</th>
      <th>Club</th>
      <th>Club ID</th>
      <th>Country</th>
      <th>Home city</th>
      <th>Needs mapping</th>
      <th>Locked</th>
      <th>ESPN</th>
      <th>EF</th>
      <th>FBref</th>
      <th>Transfermarkt</th>
      <th>Sofifa</th>
    </tr></thead><tbody>';

    foreach ($clubs as $cl) {
      echo '<tr>';

      $club_link = $this->admin_url_page([
        'view' => 'club',
        'club' => $cl->club_id,
        'tab' => 'overview',
        'region' => $region_group_code,
        'country' => $country_code,
        'competition' => $competition_code,
      ]);

      // Badge
      if (!empty($cl->logo_url_primary)) {
        echo '<td><img src="' . esc_url($cl->logo_url_primary) . '" alt="" style="height:20px; width:20px; object-fit:contain; vertical-align:middle;"></td>';
      } else {
        echo '<td></td>';
      }

      // Club (clickable)
      echo '<td><a href="' . esc_url($club_link) . '">' . esc_html($cl->canonical_name) . '</a></td>';
      
      // Club ID
      echo '<td>' . intval($cl->club_id) . '</td>';
      
      // Country
      echo '<td>' . esc_html($cl->club_country ?? '') . '</td>';
      
      // Home city
      echo '<td>' . esc_html($cl->home_city ?? '') . '</td>';
      
      // Flags
      echo '<td>' . (intval($cl->needs_mapping) ? 'Yes' : 'No') . '</td>';
      echo '<td>' . (intval($cl->is_locked) ? 'Yes' : 'No') . '</td>';
      echo '<td>' . (intval($cl->has_e_id) ? 'Yes' : 'No') . '</td>';
      echo '<td>' . (intval($cl->has_ef_team_id) ? 'Yes' : 'No') . '</td>';
      echo '<td>' . (intval($cl->has_f_id) ? 'Yes' : 'No') . '</td>';
      echo '<td>' . (intval($cl->has_t_id) ? 'Yes' : 'No') . '</td>';
      echo '<td>' . (intval($cl->has_sf_id) ? 'Yes' : 'No') . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table></div>';
  }
  public function render_club($club_id, $region_group_code = '', $country_code = '', $competition_code = '') {
    // Read current tab
    $tab = isset($_GET['tab']) ? $this->skey($_GET['tab']) : 'overview';
    $allowed_tabs = array('overview', 'history', 'aliases', 'mappings', 'season');
    if (!in_array($tab, $allowed_tabs)) {
      $tab = 'overview';
    }
    
    echo '<div class="wrap">';
    echo '<h1>Club</h1>';

    if (!$club_id) {
      echo '<div class="notice notice-error"><p><strong>Error:</strong> Missing club_id.</p></div>';
      echo '<p><a href="' . esc_url($this->admin_url_page()) . '">Back to regions</a></p>';
      echo '</div>';
      return;
    }

    $error = null;
    $club = $this->get_club_by_id($club_id, $error);

    if ($error) {
      echo '<div class="notice notice-error"><p><strong>DB error:</strong> ' . esc_html($error) . '</p></div>';
      echo '</div>';
      return;
    }

    if (!$club) {
      echo '<div class="notice notice-error"><p><strong>Error:</strong> Club not found.</p></div>';
      echo '</div>';
      return;
    }
    
    // Show save success/error notices
    if (isset($_GET['saved']) && $_GET['saved'] === '1') {
      echo '<div class="notice notice-success is-dismissible"><p><strong>Saved.</strong></p></div>';
    }
    
    // Check for error transient
    $transient_key = 'fdm_leagues_admin_save_error_' . get_current_user_id();
    $save_error = get_transient($transient_key);
    if ($save_error) {
      echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> ' . esc_html($save_error) . '</p></div>';
      delete_transient($transient_key);
    }

    $back_link = $competition_code
      ? $this->admin_url_page(['view'=>'league','region'=>$region_group_code,'country'=>$country_code,'competition'=>$competition_code])
      : $this->admin_url_page();

    echo '<p><a href="' . esc_url($back_link) . '">Back</a></p>';

    // Club badge
    if (!empty($club->logo_url_primary)) {
      echo '<img src="' . esc_url($club->logo_url_primary) . '" alt="" style="height:96px; width:96px; object-fit:contain; display:block; margin:8px 0 12px 0;">';
    }

    echo '<h2>' . esc_html($club->canonical_name) . ' (ID ' . intval($club->id) . ')</h2>';

    // Tabs navigation
    $tab_url_base = $this->admin_url_page(array('view' => 'club', 'club_id' => $club_id));
    echo '<h3>Tabs</h3>';
    echo '<ul style="display:flex; gap:12px; margin:0 0 16px 0; padding:0; list-style:none;">';
    
    $tabs = array('overview' => 'Overview', 'history' => 'History', 'aliases' => 'Aliases', 'mappings' => 'Mappings');
    foreach ($tabs as $tab_key => $tab_label) {
      $tab_url = add_query_arg('tab', $tab_key, $tab_url_base);
      $is_active = ($tab === $tab_key);
      $style = $is_active ? 'font-weight:bold;' : '';
      echo '<li><a href="' . esc_url($tab_url) . '" style="' . esc_attr($style) . '">' . esc_html($tab_label) . '</a></li>';
    }
    echo '</ul>';

    // Render tab content
    if ($tab === 'overview') {
    echo '<h3>Overview</h3>';
    
    // Edit form
    echo '<form method="post" action="">';
    wp_nonce_field('fdm_save_club_' . $club_id, '_wpnonce');
    echo '<input type="hidden" name="club_id" value="' . esc_attr($club_id) . '">';
    echo '<input type="hidden" name="fdm_action" value="save_club">';
    
    echo '<table class="form-table">';
    
    // canonical_name (required)
    echo '<tr>';
    echo '<th scope="row"><label for="canonical_name">canonical_name <span style="color:#d63638">*</span></label></th>';
    echo '<td><input type="text" id="canonical_name" name="canonical_name" value="' . esc_attr($club->canonical_name ?? '') . '" class="regular-text" required></td>';
    echo '</tr>';
    
    // short_name
    echo '<tr>';
    echo '<th scope="row"><label for="short_name">short_name</label></th>';
    echo '<td><input type="text" id="short_name" name="short_name" value="' . esc_attr($club->short_name ?? '') . '" class="regular-text"></td>';
    echo '</tr>';
    
    // abbreviation
    echo '<tr>';
    echo '<th scope="row"><label for="abbreviation">abbreviation</label></th>';
    echo '<td><input type="text" id="abbreviation" name="abbreviation" value="' . esc_attr($club->abbreviation ?? '') . '" class="regular-text"></td>';
    echo '</tr>';
    
    // slug
    echo '<tr>';
    echo '<th scope="row"><label for="slug">slug</label></th>';
    echo '<td><input type="text" id="slug" name="slug" value="' . esc_attr($club->slug ?? '') . '" class="regular-text"></td>';
    echo '</tr>';
    
    // home_city
    echo '<tr>';
    echo '<th scope="row"><label for="home_city">home_city</label></th>';
    echo '<td><input type="text" id="home_city" name="home_city" value="' . esc_attr($club->home_city ?? '') . '" class="regular-text"></td>';
    echo '</tr>';
    
    // country
    echo '<tr>';
    echo '<th scope="row"><label for="country">country</label></th>';
    echo '<td><input type="text" id="country" name="country" value="' . esc_attr($club->country ?? '') . '" class="regular-text" maxlength="3" size="3" pattern="[A-Za-z]{2,3}" placeholder="e.g., GB or ENG"></td>';
    echo '</tr>';
    
    // primary_colour_hex
    echo '<tr>';
    echo '<th scope="row"><label for="primary_colour_hex">primary_colour_hex</label></th>';
    echo '<td><input type="text" id="primary_colour_hex" name="primary_colour_hex" value="' . esc_attr($club->primary_colour_hex ?? '') . '" class="regular-text" placeholder="#D11317 or D11317"></td>';
    echo '</tr>';
    
    // secondary_colour_hex
    echo '<tr>';
    echo '<th scope="row"><label for="secondary_colour_hex">secondary_colour_hex</label></th>';
    echo '<td><input type="text" id="secondary_colour_hex" name="secondary_colour_hex" value="' . esc_attr($club->secondary_colour_hex ?? '') . '" class="regular-text" placeholder="#D11317 or D11317"></td>';
    echo '</tr>';
    
    // needs_mapping
    echo '<tr>';
    echo '<th scope="row"><label for="needs_mapping">needs_mapping</label></th>';
    echo '<td><input type="checkbox" id="needs_mapping" name="needs_mapping" value="1" ' . checked(1, intval($club->needs_mapping ?? 0), false) . '></td>';
    echo '</tr>';
    
    // is_locked
    echo '<tr>';
    echo '<th scope="row"><label for="is_locked">is_locked</label></th>';
    echo '<td><input type="checkbox" id="is_locked" name="is_locked" value="1" ' . checked(1, intval($club->is_locked ?? 0), false) . '></td>';
    echo '</tr>';
    
    // e_team_id
    echo '<tr>';
    echo '<th scope="row"><label for="e_team_id">e_team_id (ESPN)</label></th>';
    echo '<td><input type="text" id="e_team_id" name="e_team_id" value="' . esc_attr($club->e_team_id ?? '') . '" class="regular-text"></td>';
    echo '</tr>';
    
    // ef_team_id
    echo '<tr>';
    echo '<th scope="row"><label for="ef_team_id">ef_team_id (EF)</label></th>';
    echo '<td><input type="text" id="ef_team_id" name="ef_team_id" value="' . esc_attr($club->ef_team_id ?? '') . '" class="regular-text"></td>';
    echo '</tr>';
    
    // f_id
    echo '<tr>';
    echo '<th scope="row"><label for="f_id">f_id (FBref)</label></th>';
    echo '<td><input type="text" id="f_id" name="f_id" value="' . esc_attr($club->f_id ?? '') . '" class="regular-text"></td>';
    echo '</tr>';
    
    // t_id
    echo '<tr>';
    echo '<th scope="row"><label for="t_id">t_id (Transfermarkt)</label></th>';
    echo '<td><input type="text" id="t_id" name="t_id" value="' . esc_attr($club->t_id ?? '') . '" class="regular-text"></td>';
    echo '</tr>';
    
    // sf_id
    echo '<tr>';
    echo '<th scope="row"><label for="sf_id">sf_id (Sofifa)</label></th>';
    echo '<td><input type="text" id="sf_id" name="sf_id" value="' . esc_attr($club->sf_id ?? '') . '" class="regular-text"></td>';
    echo '</tr>';
    
    echo '</table>';
    
    echo '<p class="submit">';
    echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">';
    echo '</p>';
    
    echo '</form>';
    
    // Refresh club data after potential save
    $club = $this->get_club_by_id($club_id, $error);
    
    // Read-only overview table
    echo '<h3>Current Values</h3>';
    echo '<table class="widefat fixed striped"><tbody>';

    $row = function($label, $val) {
      return '<tr><th style="width:240px">' . esc_html($label) . '</th><td>' . $val . '</td></tr>';
    };

    $needs = intval($club->needs_mapping ?? 0) ? 'Yes' : 'No';
    $locked = intval($club->is_locked ?? 0) ? 'Yes' : 'No';
    $e_team_id = esc_html($club->e_team_id ?? '');
    $ef_team_id = esc_html($club->ef_team_id ?? '');
    $f_id = esc_html($club->f_id ?? '');
    $t_id = esc_html($club->t_id ?? '');
    $sf_id = esc_html($club->sf_id ?? '');

    echo $row('canonical_name', esc_html($club->canonical_name));
    echo $row('short_name', esc_html($club->short_name ?? ''));
    echo $row('abbreviation', esc_html($club->abbreviation ?? ''));
    echo $row('slug', esc_html($club->slug ?? ''));
    echo $row('home_city', esc_html($club->home_city ?? ''));
    echo $row('country', esc_html($club->country ?? ''));
    echo $row('primary_colour_hex', esc_html($club->primary_colour_hex ?? ''));
    echo $row('secondary_colour_hex', esc_html($club->secondary_colour_hex ?? ''));
    echo $row('needs_mapping', $needs);
    echo $row('is_locked', $locked);
    echo $row('e_team_id (ESPN)', $e_team_id);
    echo $row('ef_team_id (EF)', $ef_team_id);
    echo $row('f_id (FBref)', $f_id);
    echo $row('t_id (Transfermarkt)', $t_id);
    echo $row('sf_id (Sofifa)', $sf_id);

    echo '</tbody></table>';
    } elseif ($tab === 'history') {
      echo '<h3>History</h3>';
      
      $error = null;
      $history_rows = $this->get_club_history($club_id, $error);
      
      if ($error) {
        echo '<div class="notice notice-error"><p><strong>DB error:</strong> ' . esc_html($error) . '</p></div>';
      } elseif (empty($history_rows)) {
        echo '<p>No history found for this club.</p>';
      } else {
        // Group by competition
        $grouped = array();
        foreach ($history_rows as $row) {
          $comp_key = $row->competition_code;
          if (!isset($grouped[$comp_key])) {
            $grouped[$comp_key] = array(
              'competition_name' => $row->competition_name,
              'competition_code' => $row->competition_code,
              'country_code' => $row->country_code,
              'level' => $row->level,
              'comp_type' => $row->comp_type,
              'seasons' => array()
            );
          }
          $grouped[$comp_key]['seasons'][] = $row->season_year;
        }
        
        // Display grouped data
        foreach ($grouped as $comp) {
          $tier_text = !is_null($comp['level']) ? ', Tier ' . intval($comp['level']) : '';
          echo '<p><strong>' . esc_html($comp['competition_name']) . '</strong> (' . esc_html($comp['competition_code']) . $tier_text . ', ' . esc_html($comp['country_code']) . ')</p>';
          echo '<div class="fdm-season-chips">';
          foreach ($comp['seasons'] as $season_year) {
            $season_url = $this->admin_url_page(array(
              'view' => 'club',
              'club' => $club_id,
              'tab' => 'season',
              'competition' => $comp['competition_code'],
              'season' => $season_year
            ));
            echo '<a class="fdm-season-chip" href="' . esc_url($season_url) . '">' . esc_html($season_year) . '</a>';
          }
          echo '</div>';
        }
      }
    } elseif ($tab === 'aliases') {
      echo '<h3>Aliases</h3>';
      echo '<p>Aliases. Coming soon.</p>';
    } elseif ($tab === 'mappings') {
      echo '<h3>Mappings</h3>';
      echo '<p>Mappings. Coming soon.</p>';
    } elseif ($tab === 'season') {
      echo '<h3>Season view</h3>';
      
      $competition = isset($_GET['competition']) ? $this->skey($_GET['competition']) : '';
      $season = isset($_GET['season']) ? intval($_GET['season']) : 0;
      
      if (empty($competition) || $season <= 0) {
        echo '<div class="notice notice-error"><p><strong>Error:</strong> Missing or invalid competition or season parameters.</p></div>';
      } else {
        echo '<p>Competition: <code>' . esc_html($competition) . '</code></p>';
        echo '<p>Season: <code>' . esc_html($season) . '</code></p>';
      }
    }

    echo '</div>';
  }


}

new FDM_Leagues_Admin();
