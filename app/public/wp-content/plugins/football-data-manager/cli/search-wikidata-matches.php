<?php
/**
 * Search Wikidata for potential club matches and queue for manual verification
 *
 * Uses multiple signals to calculate confidence:
 * - Name similarity (Levenshtein)
 * - Country match (critical)
 * - City match
 * - League match
 *
 * Usage: php search-wikidata-matches.php [--limit=N] [--country=ENG] [--min-confidence=medium] [--skip-placeholders] [--delay=2] [--auto-approve]
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

// Parse arguments
$limit = 10;
$country_filter = null;
$min_confidence = null;
$skip_placeholders = false;
$delay = 2; // Increased default delay for rate limiting
$auto_approve = false;

foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int) substr($arg, 8);
    }
    if (strpos($arg, '--country=') === 0) {
        $country_filter = strtoupper(substr($arg, 10));
    }
    if (strpos($arg, '--min-confidence=') === 0) {
        $min_confidence = substr($arg, 17);
    }
    if ($arg === '--skip-placeholders') {
        $skip_placeholders = true;
    }
    if (strpos($arg, '--delay=') === 0) {
        $delay = (int) substr($arg, 8);
    }
    if ($arg === '--auto-approve') {
        $auto_approve = true;
    }
}

echo "=== Search Wikidata for Club Matches ===\n";
echo "Limit: $limit clubs\n";
echo $country_filter ? "Country filter: $country_filter\n" : "";
echo $skip_placeholders ? "Skipping placeholder names\n" : "";
echo "Delay between requests: {$delay}s\n";
echo $auto_approve ? "Auto-approve: ON (name>=90%, country match, score>=75)\n" : "";
echo "\n";

// ESPN league code to country mapping
$league_to_country = [
    'eng' => 'England', 'esp' => 'Spain', 'ger' => 'Germany', 'ita' => 'Italy',
    'fra' => 'France', 'ned' => 'Netherlands', 'por' => 'Portugal', 'bel' => 'Belgium',
    'sco' => 'Scotland', 'tur' => 'Turkey', 'rus' => 'Russia', 'ukr' => 'Ukraine',
    'gre' => 'Greece', 'aut' => 'Austria', 'sui' => 'Switzerland', 'den' => 'Denmark',
    'nor' => 'Norway', 'swe' => 'Sweden', 'pol' => 'Poland', 'cze' => 'Czech Republic',
    'bra' => 'Brazil', 'arg' => 'Argentina', 'mex' => 'Mexico', 'usa' => 'United States',
    'col' => 'Colombia', 'chi' => 'Chile', 'aus' => 'Australia', 'jpn' => 'Japan',
    'chn' => 'China', 'kor' => 'South Korea', 'sau' => 'Saudi Arabia', 'uae' => 'United Arab Emirates',
];

// Connect to database
$cnf = '/Users/kevincasey/Local Sites/footyforums/app/tools/transitional/my.cnf';
$ini = parse_ini_file($cnf, false, INI_SCANNER_RAW);

$db = new mysqli('localhost', $ini['user'], $ini['password'], 'footyforums_data', 0, $ini['socket']);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error . "\n");
}
$db->set_charset('utf8mb4');

// Get clubs without Wikidata IDs that haven't been searched yet
// Only search actual clubs (not national teams, youth teams)
$sql = "SELECT c.id, c.canonical_name, c.full_name, c.country, c.home_city, c.e_league_code, c.e_team_id
        FROM clubs c
        WHERE (c.wd_id IS NULL OR c.wd_id = '')
        AND (c.wd_searched IS NULL OR c.wd_searched = 0)
        AND c.team_type = 'club'
        AND (c.slug IS NULL OR (c.slug NOT LIKE '%.w' AND c.slug NOT LIKE '%.w.%'))";

if ($country_filter) {
    $sql .= " AND c.e_league_code LIKE '" . $db->real_escape_string(strtolower($country_filter)) . ".%'";
}

// Filter out placeholder names (1A, 2B, 3rd Place Group X, etc.)
if ($skip_placeholders) {
    $sql .= " AND c.canonical_name NOT REGEXP '^[0-9][A-Z]?$'";
    $sql .= " AND c.canonical_name NOT REGEXP '^[A-Z][0-9]$'";
    $sql .= " AND c.canonical_name NOT LIKE '%3rd Place%'";
    $sql .= " AND c.canonical_name NOT LIKE '%3rd Group%'";
    $sql .= " AND c.canonical_name NOT LIKE 'Group %'";
    $sql .= " AND c.canonical_name NOT LIKE 'Winner %'";
    $sql .= " AND c.canonical_name NOT LIKE 'Loser %'";
    $sql .= " AND c.canonical_name NOT LIKE 'TBD%'";
    $sql .= " AND LENGTH(c.canonical_name) > 3";
}

$sql .= " ORDER BY c.canonical_name LIMIT $limit";

$result = $db->query($sql);
$total = $result->num_rows;

echo "Found $total clubs to search\n\n";

$processed = 0;
$matches_found = 0;
$auto_approved_count = 0;

while ($club = $result->fetch_assoc()) {
    $processed++;
    $name = $club['canonical_name'];
    $full_name = $club['full_name'] ?: $name;

    // Determine country from league code
    $country = $club['country'];
    if (!$country && $club['e_league_code']) {
        $league_prefix = explode('.', $club['e_league_code'])[0];
        $country = $league_to_country[$league_prefix] ?? null;
    }

    echo "[$processed/$total] Searching: $name";
    if ($country) echo " ($country)";
    echo "...\n";

    // Search Wikidata for football clubs with this name
    $search_name = trim($name);
    // Remove country suffix in parentheses if present
    $search_name = preg_replace('/\s*\([^)]+\)\s*$/', '', $search_name);

    // Also create a simplified search term (strip common prefixes for fuzzy matching)
    // This helps match "AC Ajaccio" to "A.C. Ajaccio", "FC Barcelona" to "F.C. Barcelona", etc.
    $search_simple = preg_replace('/^(A\.?C\.?|F\.?C\.?|S\.?C\.?|C\.?F\.?|A\.?S\.?|S\.?S\.?|U\.?S\.?|C\.?D\.?|R\.?C\.?|S\.?V\.?|T\.?S\.?V\.?|V\.?f\.?[BL]\.?|1\.\s*F\.?C\.?)\s*/i', '', $search_name);
    $search_simple = trim($search_simple);

    // Use the simpler term if it's significantly shorter and still meaningful
    if (strlen($search_simple) >= 4 && strlen($search_simple) < strlen($search_name) - 2) {
        $search_name = $search_simple;
    }

    // Simplified SPARQL query - women's team filtering done in PHP
    $sparql = '
    SELECT DISTINCT ?club ?clubLabel ?clubDescription ?countryLabel ?cityLabel ?leagueLabel ?founded ?article ?venueLabel ?nickname ?website ?logoUrl WHERE {
      ?club wdt:P31/wdt:P279* wd:Q476028 .
      ?club rdfs:label ?label .
      FILTER(LANG(?label) = "en")
      FILTER(CONTAINS(LCASE(?label), LCASE("' . addslashes($search_name) . '")))

      OPTIONAL { ?club wdt:P17 ?country . }
      OPTIONAL { ?club wdt:P131 ?city . }
      OPTIONAL { ?club wdt:P118 ?league . }
      OPTIONAL { ?club wdt:P571 ?founded . }
      OPTIONAL { ?club wdt:P115 ?venue . }
      OPTIONAL { ?club wdt:P1449 ?nickname . }
      OPTIONAL { ?club wdt:P856 ?website . }
      OPTIONAL { ?club wdt:P154 ?logoUrl . }
      OPTIONAL {
        ?article schema:about ?club .
        ?article schema:isPartOf <https://en.wikipedia.org/> .
      }

      SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
    }
    LIMIT 20
    ';

    // Women's team keywords to filter out in PHP
    $womens_keywords = ['women', 'ladies', 'girls', 'female', 'femenino', 'femenil', 'femení',
                        'feminino', 'feminina', 'femminile', 'féminine', 'frauen',
                        'damen', 'vrouwen', 'dames', 'w.f.c', 'wfc'];

    $url = 'https://query.wikidata.org/sparql?' . http_build_query(['query' => $sparql]);

    // Use curl instead of file_get_contents for better HTTPS support
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: FootyForums/1.0 (football data research)'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    // Retry logic with exponential backoff
    $response = false;
    $retries = 3;
    $retry_delay = $delay;

    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response !== false && $http_code === 200) {
            break;
        }
        $response = false;
        if ($attempt < $retries) {
            $err = curl_error($ch) ?: "HTTP $http_code";
            echo "  API error ($err), retry $attempt/$retries in {$retry_delay}s...\n";
            sleep($retry_delay);
            $retry_delay *= 2;
        }
    }
    curl_close($ch);

    if ($response === false) {
        echo "  API error after $retries attempts, skipping\n";
        sleep($delay * 2);
        continue;
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['results']['bindings'])) {
        echo "  Parse error, skipping\n";
        sleep($delay);
        continue;
    }

    $candidates = $data['results']['bindings'];
    if (empty($candidates)) {
        echo "  No matches found\n";
        sleep($delay);
        continue;
    }

    echo "  Found " . count($candidates) . " candidate(s)\n";

    foreach ($candidates as $candidate) {
        $wd_id = str_replace('http://www.wikidata.org/entity/', '', $candidate['club']['value']);
        $wd_label = $candidate['clubLabel']['value'] ?? '';
        $wd_description = $candidate['clubDescription']['value'] ?? '';
        $wd_country = $candidate['countryLabel']['value'] ?? '';
        $wd_city = $candidate['cityLabel']['value'] ?? '';
        $wd_league = $candidate['leagueLabel']['value'] ?? '';
        $wd_founded = isset($candidate['founded']) ? substr($candidate['founded']['value'], 0, 4) : '';
        $wd_wikipedia = $candidate['article']['value'] ?? '';
        $wd_venue = $candidate['venueLabel']['value'] ?? '';
        $wd_nickname = $candidate['nickname']['value'] ?? '';
        $wd_website = $candidate['website']['value'] ?? '';
        $wd_logo_url = $candidate['logoUrl']['value'] ?? '';

        // Skip women's teams (filter in PHP for faster SPARQL queries)
        // Check both label and description for women's keywords
        $text_to_check = strtolower($wd_label . ' ' . $wd_description);
        $is_womens = false;
        foreach ($womens_keywords as $keyword) {
            if (strpos($text_to_check, $keyword) !== false) {
                $is_womens = true;
                break;
            }
        }
        if ($is_womens) continue;

        // Calculate confidence signals
        // Strip common prefixes and suffixes for better name matching
        // e.g., "Celtic" vs "Celtic F.C.", "Barcelona" vs "FC Barcelona"
        $prefixes = '/^(F\.?C\.?|A\.?C\.?|S\.?C\.?|C\.?F\.?|A\.?S\.?|S\.?S\.?|U\.?S\.?|C\.?D\.?|R\.?C\.?|S\.?V\.?|Real|Sporting|Athletic)\s+/i';
        $suffixes = '/\s*(F\.?C\.?|A\.?C\.?|S\.?C\.?|C\.?F\.?|A\.?F\.?C\.?|S\.?S\.?C\.?|A\.?S\.?D\.?|S\.?V\.?|1\.?\s*F\.?C\.?)\.?\s*$/i';
        $name_clean = trim(preg_replace($prefixes, '', preg_replace($suffixes, '', $name)));
        $wd_label_clean = trim(preg_replace($prefixes, '', preg_replace($suffixes, '', $wd_label)));

        // Compare both raw and cleaned versions, take best match
        $name_similarity = 0;
        similar_text(strtolower($name), strtolower($wd_label), $name_similarity);
        $name_similarity_clean = 0;
        similar_text(strtolower($name_clean), strtolower($wd_label_clean), $name_similarity_clean);
        $name_similarity = max($name_similarity, $name_similarity_clean);
        $name_similarity = round($name_similarity, 2);

        // Country match (critical signal) with aliases
        // Include both full names and common codes
        $country_aliases = [
            'England' => ['United Kingdom', 'UK', 'Great Britain', 'GB', 'ENG'],
            'ENG' => ['United Kingdom', 'UK', 'Great Britain', 'GB', 'England'],
            'Scotland' => ['United Kingdom', 'UK', 'Great Britain', 'GB', 'SCO'],
            'SCO' => ['United Kingdom', 'UK', 'Great Britain', 'GB', 'Scotland'],
            'Wales' => ['United Kingdom', 'UK', 'Great Britain', 'GB', 'WAL'],
            'WAL' => ['United Kingdom', 'UK', 'Great Britain', 'GB', 'Wales'],
            'Northern Ireland' => ['United Kingdom', 'UK', 'Great Britain', 'GB', 'NIR'],
            'NIR' => ['United Kingdom', 'UK', 'Great Britain', 'GB', 'Northern Ireland'],
            'United States' => ['USA', 'US', 'America'],
            'USA' => ['United States', 'US', 'America'],
            'South Korea' => ['Korea', 'Republic of Korea', 'KOR'],
            'KOR' => ['Korea', 'Republic of Korea', 'South Korea'],
            'China' => ['People\'s Republic of China', 'PRC', 'CHN'],
            'CHN' => ['People\'s Republic of China', 'PRC', 'China'],
            'Germany' => ['GER', 'Deutschland'],
            'GER' => ['Germany', 'Deutschland'],
            'Spain' => ['ESP', 'España'],
            'ESP' => ['Spain', 'España'],
            'Italy' => ['ITA', 'Italia'],
            'ITA' => ['Italy', 'Italia'],
            'France' => ['FRA'],
            'FRA' => ['France'],
        ];

        $country_match = 0;
        if ($country && $wd_country) {
            // Direct match
            if (stripos($wd_country, $country) !== false || stripos($country, $wd_country) !== false) {
                $country_match = 1;
            }
            // Check aliases
            elseif (isset($country_aliases[$country])) {
                foreach ($country_aliases[$country] as $alias) {
                    if (stripos($wd_country, $alias) !== false) {
                        $country_match = 1;
                        break;
                    }
                }
            }
        }

        // City match
        $city_match = 0;
        if ($club['home_city'] && $wd_city) {
            $city_match = (stripos($wd_city, $club['home_city']) !== false ||
                          stripos($club['home_city'], $wd_city) !== false) ? 1 : 0;
        }

        // League match (basic check)
        $league_match = 0;
        if ($club['e_league_code'] && $wd_league) {
            // Check if league contains country name
            if ($country && stripos($wd_league, $country) !== false) {
                $league_match = 1;
            }
        }

        // Calculate overall confidence score (0-100)
        // Weights: name similarity is most important, country is critical qualifier
        $confidence_score = 0;
        $confidence_score += $name_similarity * 0.5;  // 50% weight for name (max 50 pts)
        $confidence_score += $country_match * 35;      // 35 pts for country match
        $confidence_score += $city_match * 10;         // 10 pts for city match
        $confidence_score += $league_match * 5;        // 5 pts for league match

        // Determine confidence level
        $confidence = 'low';
        if ($confidence_score >= 80 && $country_match) {
            $confidence = 'high';
        } elseif ($confidence_score >= 50 && $country_match) {
            $confidence = 'medium';
        } elseif ($confidence_score >= 70) {
            $confidence = 'medium';
        }

        // Skip low confidence if min_confidence is set
        if ($min_confidence === 'high' && $confidence !== 'high') continue;
        if ($min_confidence === 'medium' && $confidence === 'low') continue;

        // Fetch external IDs for this Wikidata entity
        $ext_ids = fetchExternalIds($wd_id);

        // Auto-approve very high confidence matches
        // Criteria: name very similar (90%+), country matches, and overall score 75%+
        // With new weights: perfect name (50) + country (35) = 85, so 90% name + country = ~80
        if ($auto_approve && $name_similarity >= 90 && $country_match && $confidence_score >= 75) {
            // Directly update the club with this Wikidata ID
            $db->query("UPDATE clubs SET wd_id = '" . $db->real_escape_string($wd_id) . "', wd_searched = 1 WHERE id = " . intval($club['id']));

            // Also update external IDs if available
            $updates = [];
            if (!empty($ext_ids['Transfermarkt team ID'])) {
                $updates[] = "t_id = '" . $db->real_escape_string($ext_ids['Transfermarkt team ID']) . "'";
            }
            if (!empty($ext_ids['FBref squad ID'])) {
                $updates[] = "f_id = '" . $db->real_escape_string($ext_ids['FBref squad ID']) . "'";
            }
            if (!empty($ext_ids['Scorebar / Soccerway team ID'])) {
                $updates[] = "s_id = '" . $db->real_escape_string($ext_ids['Scorebar / Soccerway team ID']) . "'";
            }
            if (!empty($updates)) {
                $db->query("UPDATE clubs SET " . implode(', ', $updates) . " WHERE id = " . intval($club['id']));
            }

            $auto_approved_count++;
            echo "    ✓ AUTO-APPROVED: $wd_id ($wd_label) [" . round($name_similarity) . "% name, " . round($confidence_score) . "% total]\n";

            // Skip to next club - we found our match
            break;
        }

        // Insert into queue for manual review
        $stmt = $db->prepare("
            INSERT INTO wikidata_match_queue
            (club_id, club_name, club_country, club_city, club_league, espn_id,
             wd_id, wd_label, wd_description, wd_country, wd_city, wd_league, wd_founded, wd_wikipedia_url,
             wd_venue, wd_nickname, wd_website, wd_logo_url,
             wd_external_ids, name_similarity, country_match, city_match, league_match,
             confidence, confidence_score)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            confidence_score = VALUES(confidence_score),
            confidence = VALUES(confidence),
            wd_venue = VALUES(wd_venue),
            wd_nickname = VALUES(wd_nickname),
            wd_website = VALUES(wd_website),
            wd_logo_url = VALUES(wd_logo_url)
        ");

        $ext_ids_json = json_encode($ext_ids);

        $stmt->bind_param('issssssssssssssssssdiiisd',
            $club['id'], $name, $country, $club['home_city'], $club['e_league_code'], $club['e_team_id'],
            $wd_id, $wd_label, $wd_description, $wd_country, $wd_city, $wd_league, $wd_founded, $wd_wikipedia,
            $wd_venue, $wd_nickname, $wd_website, $wd_logo_url,
            $ext_ids_json, $name_similarity, $country_match, $city_match, $league_match,
            $confidence, $confidence_score
        );

        if ($stmt->execute()) {
            $matches_found++;
            $signals = [];
            if ($country_match) $signals[] = "country✓";
            if ($city_match) $signals[] = "city✓";
            if ($league_match) $signals[] = "league✓";

            echo "    → $wd_id: $wd_label";
            if ($wd_country) echo " ($wd_country)";
            echo " [$confidence " . round($confidence_score) . "%]";
            if ($signals) echo " " . implode(' ', $signals);
            echo "\n";
        }
    }

    // Mark club as searched (even if no match found)
    $db->query("UPDATE clubs SET wd_searched = 1 WHERE id = " . intval($club['id']));

    // Rate limit - respect Wikidata's query service
    sleep($delay);
}

echo "\n=== Summary ===\n";
echo "Clubs processed: $processed\n";
if ($auto_approve) {
    echo "Auto-approved: $auto_approved_count\n";
}
echo "Candidates queued: $matches_found\n";
if ($matches_found > 0) {
    echo "\nRun: wp admin to review matches\n";
}

$db->close();

/**
 * Fetch key external IDs from Wikidata entity
 */
function fetchExternalIds($wd_id) {
    $sparql = "
    SELECT ?prop ?propLabel ?value WHERE {
      wd:$wd_id ?p ?value .
      ?prop wikibase:directClaim ?p .
      VALUES ?prop {
        wd:P7223   # Transfermarkt
        wd:P8642   # FBref
        wd:P6131   # Soccerway
        wd:P13590  # ESPN
        wd:P7361   # UEFA
      }
      SERVICE wikibase:label { bd:serviceParam wikibase:language \"en\". }
    }
    ";

    $url = 'https://query.wikidata.org/sparql?' . http_build_query(['query' => $sparql]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: FootyForums/1.0'
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $http_code !== 200) return [];

    $data = json_decode($response, true);
    if (!$data || !isset($data['results']['bindings'])) return [];

    $ids = [];
    foreach ($data['results']['bindings'] as $binding) {
        $prop = $binding['propLabel']['value'] ?? '';
        $value = $binding['value']['value'] ?? '';
        if ($prop && $value) {
            $ids[$prop] = $value;
        }
    }

    return $ids;
}
