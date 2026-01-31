#!/usr/bin/env php
<?php
/**
 * Data Quality Verification Script
 * Run: php verify-data-quality.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

// Bootstrap WordPress
$wp_load = dirname(__DIR__, 5) . '/wp-load.php';
if (!file_exists($wp_load)) {
    $wp_load = dirname(__DIR__, 4) . '/wp-load.php';
}
require_once $wp_load;

// Connect to e_db
$cnf = '/Users/kevincasey/Local Sites/footyforums/app/tools/transitional/my.cnf';
$ini = parse_ini_file($cnf, false, INI_SCANNER_RAW);
$mysqli = new mysqli('localhost', $ini['user'], $ini['password'], 'e_db', 0, $ini['socket']);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

echo "=== DATA QUALITY CHECK ===\n\n";

// 1. Lineups
echo "1. LINEUPS - Player names?\n";
$res = $mysqli->query("SELECT athletedisplayname, jersey, position FROM lineups LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   {$row['athletedisplayname']} (#{$row['jersey']}, {$row['position']})\n";
}

// 2. Commentary
echo "\n2. COMMENTARY - Actual text?\n";
$res = $mysqli->query("SELECT clockdisplayvalue, LEFT(commentarytext, 70) as txt FROM commentary LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   [{$row['clockdisplayvalue']}] {$row['txt']}...\n";
}

// 3. Key Events
echo "\n3. KEY EVENTS - Types?\n";
$res = $mysqli->query("SELECT keyeventtypeid, clockdisplayvalue, LEFT(keyeventtext, 50) as txt FROM keyEvents LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   [Type {$row['keyeventtypeid']}] {$row['clockdisplayvalue']}: {$row['txt']}...\n";
}

// 4. Plays
echo "\n4. PLAYS - Timestamps?\n";
$res = $mysqli->query("SELECT period, clockdisplayvalue, LEFT(text, 50) as txt FROM plays LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   [P{$row['period']} {$row['clockdisplayvalue']}] {$row['txt']}...\n";
}

// 5. Players
echo "\n5. PLAYERS - Positions/nationalities?\n";
$res = $mysqli->query("SELECT displayname, positionname, citizenship FROM players WHERE citizenship != '' LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   {$row['displayname']} - {$row['positionname']} ({$row['citizenship']})\n";
}

// 6. Standings
echo "\n6. STANDINGS - Points?\n";
$res = $mysqli->query("SELECT teamrank, gamesplayed, wins, ties, losses, points FROM standings LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   Rank {$row['teamrank']}: P{$row['gamesplayed']} W{$row['wins']} D{$row['ties']} L{$row['losses']} Pts{$row['points']}\n";
}

// 7. Teams
echo "\n7. TEAMS - Names?\n";
$res = $mysqli->query("SELECT abbreviation, displayname FROM teams LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   {$row['abbreviation']}: {$row['displayname']}\n";
}

// 8. Venues
echo "\n8. VENUES - Locations?\n";
$res = $mysqli->query("SELECT fullname, city, country, capacity FROM venues LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   {$row['fullname']} - {$row['city']}, {$row['country']} (Cap: {$row['capacity']})\n";
}

// 9. Team Rosters
echo "\n9. TEAM ROSTERS - Details?\n";
$res = $mysqli->query("SELECT teamname, playerdisplayname, jersey, position FROM teamRoster LIMIT 3");
while ($row = $res->fetch_assoc()) {
    echo "   {$row['teamname']}: {$row['playerdisplayname']} (#{$row['jersey']}, {$row['position']})\n";
}

// Summary counts
echo "\n=== TOTAL COUNTS ===\n";
$tables = ['fixtures', 'lineups', 'commentary', 'keyEvents', 'teamStats', 'teams', 'venues', 'standings', 'teamRoster', 'players', 'plays'];
foreach ($tables as $tbl) {
    $res = $mysqli->query("SELECT COUNT(*) as cnt FROM $tbl");
    $cnt = $res->fetch_assoc()['cnt'];
    $status = $cnt > 0 ? '✓' : '✗';
    printf("  %s %-15s %10d\n", $status, $tbl, $cnt);
}

echo "\n=== CHECK COMPLETE ===\n";
