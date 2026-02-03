<?php
/**
 * Import new ESPN clubs from e_db.teams to footyforums_data.clubs
 *
 * Usage: php import-new-espn-clubs.php [--dry-run]
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

$dry_run = in_array( '--dry-run', $argv ?? [] );

echo "=== Import New ESPN Clubs ===\n";
echo $dry_run ? "DRY RUN MODE - No changes will be made\n\n" : "\n";

// Connect to databases using my.cnf
$cnf = '/Users/kevincasey/Local Sites/footyforums/app/tools/transitional/my.cnf';
$ini = parse_ini_file($cnf, false, INI_SCANNER_RAW);

$e_db = new mysqli('localhost', $ini['user'], $ini['password'], 'e_db', 0, $ini['socket']);
if ($e_db->connect_error) {
    die("e_db connection failed: " . $e_db->connect_error . "\n");
}

$ff_db = new mysqli('localhost', $ini['user'], $ini['password'], 'footyforums_data', 0, $ini['socket']);
if ($ff_db->connect_error) {
    die("footyforums_data connection failed: " . $ff_db->connect_error . "\n");
}

// Step 1: Get all unique team IDs from e_db.teams
echo "Fetching ESPN team IDs from e_db.teams...\n";
$result = $e_db->query("
    SELECT
        teamid,
        MAX(displayname) as displayname,
        MAX(shortdisplayname) as shortdisplayname,
        MAX(abbreviation) as abbreviation,
        MAX(color) as color,
        MAX(alternatecolor) as alternatecolor,
        MAX(logourl) as logourl,
        MAX(slug) as slug,
        MAX(location) as location,
        MAX(venueid) as venueid
    FROM teams
    WHERE teamid IS NOT NULL
    GROUP BY teamid
    ORDER BY teamid
");

if (!$result) {
    echo "Error fetching from e_db: " . $e_db->error . "\n";
    exit(1);
}

$espn_teams = [];
while ($row = $result->fetch_assoc()) {
    $espn_teams[] = $row;
}
$result->free();

echo "Found " . count($espn_teams) . " unique teams in e_db\n";

// Step 2: Get existing ESPN IDs from clubs table
echo "Fetching existing club ESPN IDs...\n";
$result = $ff_db->query("SELECT e_team_id FROM clubs WHERE e_team_id IS NOT NULL");

if (!$result) {
    echo "Error fetching from clubs: " . $ff_db->error . "\n";
    exit(1);
}

$existing_ids_map = [];
while ($row = $result->fetch_assoc()) {
    $existing_ids_map[$row['e_team_id']] = true;
}
$result->free();

echo "Found " . count($existing_ids_map) . " clubs with ESPN IDs\n";

// Step 3: Find missing teams
$missing_teams = [];
foreach ( $espn_teams as $team ) {
    $tid = (string) $team['teamid'];
    if ( ! isset( $existing_ids_map[ $tid ] ) ) {
        $missing_teams[] = $team;
    }
}

echo "Found " . count( $missing_teams ) . " new teams to import\n\n";

if ( empty( $missing_teams ) ) {
    echo "Nothing to import!\n";
    exit( 0 );
}

// Step 4: Import missing teams
$imported = 0;
$errors = 0;

foreach ( $missing_teams as $team ) {
    $canonical_name = $team['displayname'] ?: $team['location'];

    // Clean up color values (remove # if present, ensure valid hex)
    $primary_color = null;
    if ( ! empty( $team['color'] ) && preg_match( '/^[0-9a-fA-F]{6}$/', $team['color'] ) ) {
        $primary_color = '#' . strtoupper( $team['color'] );
    }

    $secondary_color = null;
    if ( ! empty( $team['alternatecolor'] ) && preg_match( '/^[0-9a-fA-F]{6}$/', $team['alternatecolor'] ) ) {
        $secondary_color = '#' . strtoupper( $team['alternatecolor'] );
    }

    // Generate unique slug by appending ESPN ID
    $base_slug = $team['slug'] ?: preg_replace('/[^a-z0-9]+/', '-', strtolower($canonical_name));
    $slug = $base_slug . '-e' . $team['teamid'];

    $data = [
        'e_team_id'           => (string) $team['teamid'],
        'canonical_name'      => $canonical_name,
        'full_name'           => $team['location'] ? $team['location'] . ' ' . $team['displayname'] : $team['displayname'],
        'short_name'          => $team['shortdisplayname'] ?: null,
        'abbreviation'        => $team['abbreviation'] ?: null,
        'slug'                => $slug,
        'logo_url_primary'    => $team['logourl'] ?: null,
        'primary_colour_hex'  => $primary_color,
        'secondary_colour_hex'=> $secondary_color,
        'e_venue_id'          => $team['venueid'] ? (string) $team['venueid'] : null,
        'needs_mapping'       => 1,
        'active_flag'         => 1,
    ];

    if ($dry_run) {
        echo "Would import: {$data['e_team_id']} - {$data['canonical_name']}\n";
        $imported++;
    } else {
        $stmt = $ff_db->prepare("
            INSERT INTO clubs (
                e_team_id, canonical_name, full_name, short_name, abbreviation,
                slug, logo_url_primary, primary_colour_hex, secondary_colour_hex,
                e_venue_id, needs_mapping, active_flag
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            'ssssssssssii',
            $data['e_team_id'],
            $data['canonical_name'],
            $data['full_name'],
            $data['short_name'],
            $data['abbreviation'],
            $data['slug'],
            $data['logo_url_primary'],
            $data['primary_colour_hex'],
            $data['secondary_colour_hex'],
            $data['e_venue_id'],
            $data['needs_mapping'],
            $data['active_flag']
        );

        if ($stmt->execute()) {
            echo "Imported: {$data['e_team_id']} - {$data['canonical_name']}\n";
            $imported++;
        } else {
            echo "ERROR importing {$team['teamid']} ({$canonical_name}): {$stmt->error}\n";
            $errors++;
        }
        $stmt->close();
    }
}

echo "\n=== Summary ===\n";
echo "Imported: $imported\n";
echo "Errors: $errors\n";

if ( $dry_run ) {
    echo "\nRun without --dry-run to actually import.\n";
}
