#!/usr/bin/env php
<?php
/**
 * One-time script to populate the `director` column in the `dvds` table
 * by querying Wikipedia for each title. Reads DB config from api/config.ini.
 *
 * Usage:
 *   php scripts/fill_directors.php [--dry-run] [--limit=N] [--offset=N] [--force] [--sleep=SECONDS]
 *
 * Options:
 *   --dry-run    Do not write updates, only show what would change
 *   --limit=N    Process at most N rows
 *   --offset=N   Skip first N rows
 *   --force      Overwrite existing director values
 *   --sleep=S    Seconds to sleep between requests (default 1)
 */

// Very small dependency-free script.
error_reporting(E_ALL);
ini_set('display_errors', 1);

$opts = getopt('', ['dry-run', 'limit::', 'offset::', 'force', 'sleep::', 'help']);
if (isset($opts['help'])) {
    echo file_get_contents(__FILE__);
    exit(0);
}

$dryRun = isset($opts['dry-run']);
$limit = isset($opts['limit']) ? (int)$opts['limit'] : null;
$offset = isset($opts['offset']) ? (int)$opts['offset'] : 0;
$force = isset($opts['force']);
$sleep = isset($opts['sleep']) ? (float)$opts['sleep'] : 1.0;

$configFile = __DIR__ . './api/config.ini';
if (!file_exists($configFile)) {
    fwrite(STDERR, "Missing config file: $configFile\n");
    exit(2);
}

$cfg = parse_ini_file($configFile, true);
if (!$cfg || !isset($cfg['database'])) {
    fwrite(STDERR, "Invalid config file or missing [database] section\n");
    exit(2);
}

$db = $cfg['database'];
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['dbname'], $db['charset'] ?? 'utf8mb4');
try {
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(2);
}

// Fetch rows
$sql = "SELECT dkey, title, year, director, stars FROM dvds";
if ($offset) $sql .= " LIMIT 18446744073709551615 OFFSET " . (int)$offset; // big limit to allow offset without limit
if ($limit) $sql .= ($offset ? " LIMIT " . (int)$limit : " LIMIT " . (int)$limit);

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();
if (!$rows) {
    echo "No rows found\n";
    exit(0);
}

echo "Found " . count($rows) . " rows to process\n";

$updateStmt = $pdo->prepare("UPDATE dvds SET director = :director WHERE dkey = :dkey");

foreach ($rows as $i => $row) {
    $dkey = $row['dkey'];
    $title = trim($row['title']);
    $year = isset($row['year']) ? trim($row['year']) : '';
    $existing = isset($row['director']) ? trim($row['director']) : '';

    if ($existing !== '' && !$force) {
        echo "[{$dkey}] '{$title}' — director already set ('{$existing}'), skipping\n";
        continue;
    }

    echo "[{$dkey}] Processing: {$title}" . ($year ? " ({$year})" : "") . "\n";

    $director = fetchDirectorFromWikipedia($title, $year);

    if ($director === null) {
        echo "  → director not found\n";
    } else {
        echo "  → director: {$director}\n";
        if (!$dryRun) {
            try {
                $updateStmt->execute([':director' => $director, ':dkey' => $dkey]);
            } catch (Exception $e) {
                echo "  ! Failed to update DB: " . $e->getMessage() . "\n";
            }
        }
    }

    // politeness
    usleep((int)($sleep * 1e6));
}

echo "Done.\n";

// --- helpers ---
function fetchDirectorFromWikipedia(string $title, string $year = '') {
    // 1) Search for the page
    $searchQuery = $title;
    if ($year) {
        // include year to help disambiguation
        $searchQuery .= ' ' . $year;
    }

    $searchUrl = 'https://en.wikipedia.org/w/api.php?action=query&list=search&srsearch=' . urlencode($searchQuery) . '&format=json&utf8=1&srlimit=1';
    $searchJson = httpGet($searchUrl);
    if (!$searchJson) return null;
    $searchData = json_decode($searchJson, true);
    if (empty($searchData['query']['search'][0]['title'])) return null;
    $pageTitle = $searchData['query']['search'][0]['title'];

    // 2) Get page wikitext content
    $revUrl = 'https://en.wikipedia.org/w/api.php?action=query&prop=revisions&rvprop=content&rvslots=*&titles=' . urlencode($pageTitle) . '&format=json&utf8=1';
    $revJson = httpGet($revUrl);
    if (!$revJson) return null;
    $revData = json_decode($revJson, true);
    if (empty($revData['query']['pages'])) return null;
    $pages = $revData['query']['pages'];
    $page = reset($pages);
    if (!isset($page['revisions'][0]['slots'])) return null;
    $slots = $page['revisions'][0]['slots'];
    $content = '';
    foreach ($slots as $s) {
        if (isset($s['*'])) { $content = $s['*']; break; }
        if (isset($s['content'])) { $content = $s['content']; break; }
    }
    if (!$content) return null;

    // 3) Extract director from infobox wikitext
    // Look for lines like '| director = ...' or '| directed by = ...' (case-insensitive)
    if (preg_match('/^\\|\\s*(?:director|directed by|directors?)\\s*=\\s*(.+)$/mi', $content, $m)) {
        $raw = trim($m[1]);
        $clean = cleanWikiText($raw);
        return $clean ?: null;
    }

    // 4) Fallback: look for 'Directed by' in the plain text extract
    $extractUrl = 'https://en.wikipedia.org/w/api.php?action=query&prop=extracts&explaintext=1&titles=' . urlencode($pageTitle) . '&format=json&utf8=1&exintro=1';
    $extractJson = httpGet($extractUrl);
    if ($extractJson) {
        $extractData = json_decode($extractJson, true);
        $pages2 = $extractData['query']['pages'] ?? [];
        $p2 = reset($pages2);
        $text = $p2['extract'] ?? '';
        if ($text && preg_match('/Directed by\\s*[:\\-]?\\s*(.+)/i', $text, $m2)) {
            $candidate = trim($m2[1]);
            // stop at first sentence end
            $candidate = preg_split('/[\\.\\n]/', $candidate, 2)[0];
            return $candidate ?: null;
        }
    }

    return null;
}

function httpGet($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'realmsofsilver-dvds/1.0 (+https://realmsofsilver.com)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        fwrite(STDERR, "HTTP request failed: $err\n");
        return null;
    }
    return $res;
}

function cleanWikiText($s) {
    // Remove templates {{...}}
    $s = preg_replace('/\\{\\{[^\\}]*\\}\\}/', '', $s);
    // Replace wiki links [[a|b]] or [[b]] -> b
    $s = preg_replace_callback('/\\[\\[([^\\]]+)\\]\\]/', function($m) {
        $parts = explode('|', $m[1]);
        return end($parts);
    }, $s);
    // Remove HTML tags and refs
    $s = preg_replace('/<ref[^>]*>.*?<\\/ref>/is', '', $s);
    $s = strip_tags($s);
    // Replace newlines and HTML breaks with commas
    $s = preg_replace('/(\\r?\\n|<br\\s*\\/?>)+/i', ', ', $s);
    // Collapse multiple commas/spaces
    $s = preg_replace('/\\s*,\\s*/', ', ', $s);
    $s = trim($s, " \t\\n,;");
    return $s;
}
