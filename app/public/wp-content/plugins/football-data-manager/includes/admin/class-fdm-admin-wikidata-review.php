<?php
/**
 * Admin page for reviewing Wikidata match candidates
 *
 * Shows side-by-side comparison of our club data vs Wikidata candidate
 * with confidence signals highlighted for easy verification.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FDM_Admin_Wikidata_Review {

    private $db;

    private function get_db() {
        if (!$this->db) {
            $cnf = '/Users/kevincasey/Local Sites/footyforums/app/tools/transitional/my.cnf';
            if (file_exists($cnf)) {
                $ini = parse_ini_file($cnf, false, INI_SCANNER_RAW);
                $this->db = new mysqli('localhost', $ini['user'], $ini['password'], 'footyforums_data', 0, $ini['socket']);
                if ($this->db->connect_error) {
                    $this->db = null;
                } else {
                    $this->db->set_charset('utf8mb4');
                }
            }
        }
        return $this->db;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'register_submenu'], 30);
        add_action('admin_post_fdm_wikidata_approve', [$this, 'handle_approve']);
        add_action('admin_post_fdm_wikidata_reject', [$this, 'handle_reject']);
        add_action('admin_post_fdm_wikidata_skip', [$this, 'handle_skip']);
        add_action('admin_post_fdm_wikidata_manual', [$this, 'handle_manual']);
    }

    public function register_submenu() {
        add_submenu_page(
            'fdm-data-status',
            'Wikidata Review',
            'Wikidata Review',
            'manage_options',
            'fdm-wikidata-review',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        $db = $this->get_db();

        if (!$db) {
            echo '<div class="wrap"><h1>Wikidata Review</h1><p>Database connection failed.</p></div>';
            return;
        }

        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'pending';
        $confidence_filter = isset($_GET['confidence']) ? sanitize_text_field($_GET['confidence']) : '';
        $has_ids_filter = isset($_GET['has_ids']) ? sanitize_text_field($_GET['has_ids']) : '';

        // Get counts by status (pending excludes clubs that already have wd_id)
        $pending = $approved = $rejected = 0;
        $result = $db->query("SELECT review_status, COUNT(*) as count FROM wikidata_match_queue WHERE review_status != 'pending' GROUP BY review_status");
        while ($row = $result->fetch_assoc()) {
            if ($row['review_status'] === 'approved') $approved = $row['count'];
            if ($row['review_status'] === 'rejected') $rejected = $row['count'];
        }
        // Pending count excludes clubs that already have wd_id
        $result = $db->query("SELECT COUNT(*) as count FROM wikidata_match_queue WHERE review_status = 'pending' AND club_id NOT IN (SELECT id FROM clubs WHERE wd_id IS NOT NULL AND wd_id != '')");
        $row = $result->fetch_assoc();
        $pending = $row['count'];

        // Get confidence breakdown for pending (also excludes already-matched clubs)
        $high = $medium = $low = 0;
        $result = $db->query("SELECT confidence, COUNT(*) as count FROM wikidata_match_queue WHERE review_status = 'pending' AND club_id NOT IN (SELECT id FROM clubs WHERE wd_id IS NOT NULL AND wd_id != '') GROUP BY confidence");
        while ($row = $result->fetch_assoc()) {
            if ($row['confidence'] === 'high') $high = $row['count'];
            if ($row['confidence'] === 'medium') $medium = $row['count'];
            if ($row['confidence'] === 'low') $low = $row['count'];
        }

        // Count pending with external IDs (high-value reviews)
        $with_any_ids = 0;
        $with_all_ids = 0;
        $result = $db->query("SELECT COUNT(*) as count FROM wikidata_match_queue WHERE review_status = 'pending' AND club_id NOT IN (SELECT id FROM clubs WHERE wd_id IS NOT NULL AND wd_id != '') AND (wd_external_ids LIKE '%Transfermarkt%' OR wd_external_ids LIKE '%FBref%' OR wd_external_ids LIKE '%Soccerway%')");
        $with_any_ids = $result->fetch_assoc()['count'];

        $result = $db->query("SELECT COUNT(*) as count FROM wikidata_match_queue WHERE review_status = 'pending' AND club_id NOT IN (SELECT id FROM clubs WHERE wd_id IS NOT NULL AND wd_id != '') AND wd_external_ids LIKE '%Transfermarkt%' AND wd_external_ids LIKE '%FBref%'");
        $with_all_ids = $result->fetch_assoc()['count'];

        // Build query for items
        $where = "review_status = '" . $db->real_escape_string($filter) . "'";
        if ($confidence_filter) {
            $where .= " AND confidence = '" . $db->real_escape_string($confidence_filter) . "'";
        }

        // For pending items, exclude clubs that already have a wd_id
        if ($filter === 'pending') {
            $where .= " AND club_id NOT IN (SELECT id FROM clubs WHERE wd_id IS NOT NULL AND wd_id != '')";
        }

        // Filter by external IDs availability
        if ($has_ids_filter === 'any') {
            $where .= " AND (wd_external_ids LIKE '%Transfermarkt%' OR wd_external_ids LIKE '%FBref%' OR wd_external_ids LIKE '%Soccerway%')";
        } elseif ($has_ids_filter === 'all') {
            $where .= " AND wd_external_ids LIKE '%Transfermarkt%' AND wd_external_ids LIKE '%FBref%'";
        }

        $items = [];
        $result = $db->query("SELECT * FROM wikidata_match_queue WHERE $where ORDER BY confidence_score DESC, club_name ASC LIMIT 50");
        while ($row = $result->fetch_object()) {
            $items[] = $row;
        }

        ?>
        <div class="wrap">
            <h1>Wikidata Match Review</h1>

            <div class="fdm-stats-bar" style="background:#f0f0f1; padding:15px; margin:20px 0; border-radius:4px;">
                <strong>Queue Status:</strong>
                <a href="?page=fdm-wikidata-review&filter=pending" class="<?php echo $filter === 'pending' ? 'current' : ''; ?>">
                    Pending (<?php echo $pending; ?>)
                </a> |
                <a href="?page=fdm-wikidata-review&filter=approved">Approved (<?php echo $approved; ?>)</a> |
                <a href="?page=fdm-wikidata-review&filter=rejected">Rejected (<?php echo $rejected; ?>)</a>

                <?php if ($filter === 'pending' && $pending > 0): ?>
                <span style="margin-left:30px;">
                    <strong>By Confidence:</strong>
                    <a href="?page=fdm-wikidata-review&filter=pending&confidence=high" class="<?php echo $confidence_filter === 'high' && !$has_ids_filter ? 'current' : ''; ?>" style="color:green;">
                        High (<?php echo $high; ?>)
                    </a> |
                    <a href="?page=fdm-wikidata-review&filter=pending&confidence=medium" class="<?php echo $confidence_filter === 'medium' && !$has_ids_filter ? 'current' : ''; ?>" style="color:orange;">
                        Medium (<?php echo $medium; ?>)
                    </a> |
                    <a href="?page=fdm-wikidata-review&filter=pending&confidence=low" class="<?php echo $confidence_filter === 'low' && !$has_ids_filter ? 'current' : ''; ?>" style="color:red;">
                        Low (<?php echo $low; ?>)
                    </a> |
                    <a href="?page=fdm-wikidata-review&filter=pending">All</a>
                </span>
                <span style="margin-left:30px;">
                    <strong>By External IDs:</strong>
                    <a href="?page=fdm-wikidata-review&filter=pending&has_ids=all" class="<?php echo $has_ids_filter === 'all' ? 'current' : ''; ?>" style="color:#0073aa; font-weight:bold;">
                        TM+FBref (<?php echo $with_all_ids; ?>)
                    </a> |
                    <a href="?page=fdm-wikidata-review&filter=pending&has_ids=any" class="<?php echo $has_ids_filter === 'any' ? 'current' : ''; ?>" style="color:#0073aa;">
                        Any ID (<?php echo $with_any_ids; ?>)
                    </a>
                </span>
                <?php endif; ?>
            </div>

            <?php if (empty($items)): ?>
                <p>No items to review in this category.</p>
            <?php else: ?>

            <style>
                .fdm-review-card {
                    background: #fff;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    margin-bottom: 20px;
                    padding: 20px;
                }
                .fdm-review-card.high { border-left: 4px solid #46b450; }
                .fdm-review-card.medium { border-left: 4px solid #ffb900; }
                .fdm-review-card.low { border-left: 4px solid #dc3232; }

                .fdm-compare {
                    display: flex;
                    gap: 30px;
                }
                .fdm-compare > div {
                    flex: 1;
                }
                .fdm-compare h4 {
                    margin: 0 0 10px 0;
                    padding-bottom: 5px;
                    border-bottom: 2px solid #0073aa;
                }
                .fdm-compare table {
                    width: 100%;
                }
                .fdm-compare td {
                    padding: 4px 8px;
                    vertical-align: top;
                }
                .fdm-compare td:first-child {
                    font-weight: bold;
                    width: 100px;
                    color: #666;
                }
                .fdm-match { background: #d4edda !important; }
                .fdm-mismatch { background: #f8d7da !important; }

                .fdm-signals {
                    margin: 15px 0;
                    padding: 10px;
                    background: #f9f9f9;
                    border-radius: 4px;
                }
                .fdm-signal {
                    display: inline-block;
                    padding: 3px 8px;
                    margin-right: 10px;
                    border-radius: 3px;
                    font-size: 12px;
                }
                .fdm-signal.match { background: #46b450; color: #fff; }
                .fdm-signal.no-match { background: #ccc; color: #666; }

                .fdm-actions {
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid #eee;
                }
                .fdm-actions form {
                    display: inline-block;
                    margin-right: 10px;
                }
                .fdm-ext-ids {
                    margin-top: 10px;
                    font-size: 12px;
                    color: #666;
                }
            </style>

            <?php foreach ($items as $item):
                $ext_ids = json_decode($item->wd_external_ids, true) ?: [];
            ?>
            <div class="fdm-review-card <?php echo esc_attr($item->confidence); ?>">
                <div class="fdm-compare">
                    <div>
                        <h4>🏟️ Our Club Data</h4>
                        <?php if ($item->espn_id): ?>
                        <div style="margin-bottom:10px;">
                            <img src="https://a.espncdn.com/i/teamlogos/soccer/500/<?php echo esc_attr($item->espn_id); ?>.png"
                                 alt="ESPN logo"
                                 style="max-height:60px; max-width:60px; background:#f0f0f0; padding:5px; border-radius:4px;"
                                 onerror="this.style.display='none'">
                        </div>
                        <?php endif; ?>
                        <table>
                            <tr><td>Name</td><td><strong><?php echo esc_html($item->club_name); ?></strong></td></tr>
                            <tr><td>Country</td><td><?php echo esc_html($item->club_country ?: '—'); ?></td></tr>
                            <tr><td>City</td><td><?php echo esc_html($item->club_city ?: '—'); ?></td></tr>
                            <tr><td>League</td><td><?php echo esc_html($item->club_league ?: '—'); ?></td></tr>
                            <tr><td>ESPN ID</td><td>
                                <?php if ($item->espn_id): ?>
                                    <a href="https://www.espn.com/soccer/team/_/id/<?php echo esc_attr($item->espn_id); ?>" target="_blank"><?php echo esc_html($item->espn_id); ?> ↗</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td></tr>
                        </table>
                    </div>
                    <div>
                        <h4>📚 Wikidata Candidate</h4>
                        <?php
                        $tm_id = $ext_ids['Transfermarkt team ID'] ?? null;
                        if (!empty($item->wd_logo_url) || $tm_id):
                        ?>
                        <div style="margin-bottom:10px; display:flex; gap:10px; align-items:center;">
                            <?php if (!empty($item->wd_logo_url)): ?>
                            <img src="<?php echo esc_url($item->wd_logo_url); ?>"
                                 alt="Wikidata logo"
                                 title="Wikidata logo"
                                 style="max-height:60px; max-width:60px; background:#f0f0f0; padding:5px; border-radius:4px;"
                                 onerror="this.style.display='none'">
                            <?php endif; ?>
                            <?php if ($tm_id): ?>
                            <img src="https://tmssl.akamaized.net/images/wappen/head/<?php echo esc_attr($tm_id); ?>.png"
                                 alt="Transfermarkt logo"
                                 title="Transfermarkt logo"
                                 style="max-height:60px; max-width:60px; background:#f0f0f0; padding:5px; border-radius:4px;"
                                 onerror="this.style.display='none'">
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <table>
                            <tr class="<?php echo $item->name_similarity > 80 ? 'fdm-match' : ''; ?>">
                                <td>Name</td>
                                <td>
                                    <strong><?php echo esc_html($item->wd_label); ?></strong>
                                    <br><small><a href="https://www.wikidata.org/wiki/<?php echo esc_attr($item->wd_id); ?>" target="_blank"><?php echo esc_html($item->wd_id); ?> ↗</a></small>
                                </td>
                            </tr>
                            <tr class="<?php echo $item->country_match ? 'fdm-match' : 'fdm-mismatch'; ?>">
                                <td>Country</td><td><?php echo esc_html($item->wd_country ?: '—'); ?></td>
                            </tr>
                            <tr class="<?php echo $item->city_match ? 'fdm-match' : ''; ?>">
                                <td>City</td><td><?php echo esc_html($item->wd_city ?: '—'); ?></td>
                            </tr>
                            <tr><td>League</td><td><?php echo esc_html($item->wd_league ?: '—'); ?></td></tr>
                            <tr><td>Founded</td><td><?php echo esc_html($item->wd_founded ?: '—'); ?></td></tr>
                            <?php if (!empty($item->wd_venue)): ?>
                            <tr><td>Stadium</td><td><strong><?php echo esc_html($item->wd_venue); ?></strong></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($item->wd_nickname)): ?>
                            <tr><td>Nickname</td><td><?php echo esc_html($item->wd_nickname); ?></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($item->wd_website)): ?>
                            <tr><td>Website</td><td><a href="<?php echo esc_url($item->wd_website); ?>" target="_blank"><?php echo esc_html(parse_url($item->wd_website, PHP_URL_HOST)); ?> ↗</a></td></tr>
                            <?php endif; ?>
                            <tr><td>Description</td><td><em><?php echo esc_html($item->wd_description ?: '—'); ?></em></td></tr>
                        </table>

                        <?php if (!empty($ext_ids)): ?>
                        <div class="fdm-ext-ids">
                            <strong>External IDs:</strong><br>
                            <?php foreach ($ext_ids as $provider => $id):
                                $link = null;
                                if (stripos($provider, 'Transfermarkt') !== false) {
                                    $link = 'https://www.transfermarkt.com/-/startseite/verein/' . $id;
                                } elseif (stripos($provider, 'FBref') !== false) {
                                    $link = 'https://fbref.com/en/squads/' . $id;
                                } elseif (stripos($provider, 'Soccerway') !== false) {
                                    $link = 'https://int.soccerway.com/teams/-/-/' . $id . '/';
                                } elseif (stripos($provider, 'ESPN') !== false) {
                                    $link = 'https://www.espn.com/soccer/team/_/id/' . $id;
                                } elseif (stripos($provider, 'UEFA') !== false) {
                                    $link = 'https://www.uefa.com/teamsandplayers/teams/' . $id;
                                }
                            ?>
                                <?php if ($link): ?>
                                    <a href="<?php echo esc_url($link); ?>" target="_blank"><?php echo esc_html($provider); ?>: <?php echo esc_html($id); ?> ↗</a> &nbsp;
                                <?php else: ?>
                                    <?php echo esc_html($provider); ?>: <?php echo esc_html($id); ?> &nbsp;
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="fdm-signals">
                    <strong>Confidence: <?php echo ucfirst($item->confidence); ?> (<?php echo round($item->confidence_score); ?>%)</strong>
                    &nbsp;&nbsp;|&nbsp;&nbsp;
                    <span class="fdm-signal <?php echo $item->name_similarity > 80 ? 'match' : 'no-match'; ?>">
                        Name: <?php echo round($item->name_similarity); ?>%
                    </span>
                    <span class="fdm-signal <?php echo $item->country_match ? 'match' : 'no-match'; ?>">
                        Country <?php echo $item->country_match ? '✓' : '✗'; ?>
                    </span>
                    <span class="fdm-signal <?php echo $item->city_match ? 'match' : 'no-match'; ?>">
                        City <?php echo $item->city_match ? '✓' : '✗'; ?>
                    </span>
                    <span class="fdm-signal <?php echo $item->league_match ? 'match' : 'no-match'; ?>">
                        League <?php echo $item->league_match ? '✓' : '✗'; ?>
                    </span>

                    &nbsp;&nbsp;|&nbsp;&nbsp;
                    <?php if ($item->wd_wikipedia_url): ?>
                    <a href="<?php echo esc_url($item->wd_wikipedia_url); ?>" target="_blank">Wikipedia ↗</a> &nbsp;
                    <?php endif; ?>
                    <a href="https://www.wikidata.org/wiki/<?php echo esc_attr($item->wd_id); ?>" target="_blank">Wikidata ↗</a>
                </div>

                <div class="fdm-actions">
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('fdm_wikidata_action', 'fdm_nonce'); ?>
                        <input type="hidden" name="action" value="fdm_wikidata_approve">
                        <input type="hidden" name="queue_id" value="<?php echo $item->id; ?>">
                        <input type="hidden" name="redirect" value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
                        <button type="submit" class="button button-primary">✓ Approve Match</button>
                    </form>

                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('fdm_wikidata_action', 'fdm_nonce'); ?>
                        <input type="hidden" name="action" value="fdm_wikidata_reject">
                        <input type="hidden" name="queue_id" value="<?php echo $item->id; ?>">
                        <input type="hidden" name="redirect" value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
                        <button type="submit" class="button">✗ Reject</button>
                    </form>

                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('fdm_wikidata_action', 'fdm_nonce'); ?>
                        <input type="hidden" name="action" value="fdm_wikidata_skip">
                        <input type="hidden" name="queue_id" value="<?php echo $item->id; ?>">
                        <input type="hidden" name="redirect" value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
                        <button type="submit" class="button">Skip for now</button>
                    </form>

                    <span style="margin-left:20px; color:#666;">|</span>

                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-flex; align-items:center; gap:5px; margin-left:20px;">
                        <?php wp_nonce_field('fdm_wikidata_action', 'fdm_nonce'); ?>
                        <input type="hidden" name="action" value="fdm_wikidata_manual">
                        <input type="hidden" name="queue_id" value="<?php echo $item->id; ?>">
                        <input type="hidden" name="club_id" value="<?php echo $item->club_id; ?>">
                        <input type="hidden" name="redirect" value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
                        <input type="text" name="manual_wd_id" placeholder="Q12345" style="width:100px;" pattern="Q[0-9]+" title="Enter Wikidata ID (e.g. Q12345)">
                        <button type="submit" class="button">Set Manual ID</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_approve() {
        if (!check_admin_referer('fdm_wikidata_action', 'fdm_nonce')) {
            wp_die('Security check failed');
        }

        $db = $this->get_db();
        if (!$db) {
            wp_die('Database connection failed');
        }

        $queue_id = intval($_POST['queue_id']);
        $redirect = esc_url_raw($_POST['redirect']);

        // Get queue item
        $result = $db->query("SELECT * FROM wikidata_match_queue WHERE id = $queue_id");
        $item = $result->fetch_object();

        if (!$item) {
            wp_die('Queue item not found');
        }

        // Update club with Wikidata ID
        $db->query("UPDATE clubs SET wd_id = '" . $db->real_escape_string($item->wd_id) . "', last_updated = NOW() WHERE id = " . intval($item->club_id));

        // Also update any external IDs from Wikidata
        $ext_ids = json_decode($item->wd_external_ids, true) ?: [];

        // Get current club data to check what's empty
        $club_result = $db->query("SELECT t_id, f_id, s_id FROM clubs WHERE id = " . intval($item->club_id));
        $club = $club_result->fetch_object();

        $updates = [];
        if (empty($club->t_id) && !empty($ext_ids['Transfermarkt team ID'])) {
            $updates[] = "t_id = '" . $db->real_escape_string($ext_ids['Transfermarkt team ID']) . "'";
        }
        if (empty($club->f_id) && !empty($ext_ids['FBref squad ID'])) {
            $updates[] = "f_id = '" . $db->real_escape_string($ext_ids['FBref squad ID']) . "'";
        }
        if (empty($club->s_id) && !empty($ext_ids['Scorebar / Soccerway team ID'])) {
            $updates[] = "s_id = '" . $db->real_escape_string($ext_ids['Scorebar / Soccerway team ID']) . "'";
        }

        if (!empty($updates)) {
            $db->query("UPDATE clubs SET " . implode(', ', $updates) . " WHERE id = " . intval($item->club_id));
        }

        // Mark as approved
        $user = wp_get_current_user()->user_login;
        $db->query("UPDATE wikidata_match_queue SET review_status = 'approved', reviewed_at = NOW(), reviewed_by = '" . $db->real_escape_string($user) . "' WHERE id = $queue_id");

        // Auto-reject all other pending candidates for the same club
        $db->query("UPDATE wikidata_match_queue SET review_status = 'rejected', reviewed_at = NOW(), reviewed_by = 'auto-rejected' WHERE club_id = " . intval($item->club_id) . " AND id != $queue_id AND review_status = 'pending'");

        wp_redirect($redirect);
        exit;
    }

    public function handle_reject() {
        if (!check_admin_referer('fdm_wikidata_action', 'fdm_nonce')) {
            wp_die('Security check failed');
        }

        $db = $this->get_db();
        if (!$db) {
            wp_die('Database connection failed');
        }

        $queue_id = intval($_POST['queue_id']);
        $redirect = esc_url_raw($_POST['redirect']);
        $user = wp_get_current_user()->user_login;

        $db->query("UPDATE wikidata_match_queue SET review_status = 'rejected', reviewed_at = NOW(), reviewed_by = '" . $db->real_escape_string($user) . "' WHERE id = $queue_id");

        wp_redirect($redirect);
        exit;
    }

    public function handle_skip() {
        if (!check_admin_referer('fdm_wikidata_action', 'fdm_nonce')) {
            wp_die('Security check failed');
        }

        $db = $this->get_db();
        if (!$db) {
            wp_die('Database connection failed');
        }

        $queue_id = intval($_POST['queue_id']);
        $redirect = esc_url_raw($_POST['redirect']);
        $user = wp_get_current_user()->user_login;

        $db->query("UPDATE wikidata_match_queue SET review_status = 'skipped', reviewed_at = NOW(), reviewed_by = '" . $db->real_escape_string($user) . "' WHERE id = $queue_id");

        wp_redirect($redirect);
        exit;
    }
}

    public function handle_manual() {
        if (!check_admin_referer('fdm_wikidata_action', 'fdm_nonce')) {
            wp_die('Security check failed');
        }

        $db = $this->get_db();
        if (!$db) {
            wp_die('Database connection failed');
        }

        $queue_id = intval($_POST['queue_id']);
        $club_id = intval($_POST['club_id']);
        $manual_wd_id = sanitize_text_field($_POST['manual_wd_id']);
        $redirect = esc_url_raw($_POST['redirect']);
        $user = wp_get_current_user()->user_login;

        // Validate Wikidata ID format
        if (!preg_match('/^Q[0-9]+$/', $manual_wd_id)) {
            wp_die('Invalid Wikidata ID format. Must be like Q12345');
        }

        // Update the club with the manual Wikidata ID
        $db->query("UPDATE clubs SET wd_id = '" . $db->real_escape_string($manual_wd_id) . "', last_updated = NOW() WHERE id = " . $club_id);

        // Mark queue item as approved (manual)
        if ($queue_id > 0) {
            $db->query("UPDATE wikidata_match_queue SET review_status = 'approved', reviewed_at = NOW(), reviewed_by = '" . $db->real_escape_string($user) . " (manual: $manual_wd_id)' WHERE id = $queue_id");

            // Auto-reject other pending candidates for this club
            $db->query("UPDATE wikidata_match_queue SET review_status = 'rejected', reviewed_at = NOW(), reviewed_by = 'auto-rejected' WHERE club_id = $club_id AND id != $queue_id AND review_status = 'pending'");
        }

        wp_redirect($redirect);
        exit;
    }
}

// Initialize
new FDM_Admin_Wikidata_Review();
