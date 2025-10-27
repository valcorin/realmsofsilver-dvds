<?php
// Simple, safe image fetch proxy for upload.wikimedia.org
// Usage: /api/fetch-image.php?url=<url-encoded-wikimedia-upload-url>
// This script validates the hostname and returns JSON: { ok: true, contentType, data }

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (PHP_SAPI === 'cli') {
    echo json_encode(['ok' => false, 'error' => 'CLI not supported']);
    exit(1);
}

// New: support three modes:
// 1) ?url=<upload.wikimedia.org url> (existing behavior)
// 2) ?file=File:Name.jpg -> resolve via Commons API and fetch resulting upload URL
// 3) ?title=Some title&tryPatterns=1 -> server-side deterministic File: pattern checks

$url = isset($_GET['url']) ? trim($_GET['url']) : '';
$fileParam = isset($_GET['file']) ? trim($_GET['file']) : '';
$titleParam = isset($_GET['title']) ? trim($_GET['title']) : '';
$tryPatterns = isset($_GET['tryPatterns']) ? boolval($_GET['tryPatterns']) : false;
// Optional debug flag: when ?debug=1 is present, include attempt logs in JSON output
$debug = isset($_GET['debug']) && ($_GET['debug'] === '1' || $_GET['debug'] === 'true');

// Collect debug attempt logs when debug mode is enabled
$debugAttempts = [];

// Helper: resolve a Commons File: title to the direct upload URL using Commons API
function resolve_commons_file_url(string $fileTitle) {
    global $debug, $debugAttempts;
    $api = 'https://commons.wikimedia.org/w/api.php?action=query&titles=' . urlencode($fileTitle) . '&prop=imageinfo&iiprop=url|size&format=json';
    $ch = curl_init($api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'realmsofsilver-dvds/1.0 (+https://example.org)');
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $httpCode < 200 || $httpCode >= 300) return null;
    $j = json_decode($resp, true);
    if (!$j || !isset($j['query']['pages']) || !is_array($j['query']['pages'])) return null;
    $pg = array_values($j['query']['pages'])[0] ?? null;
    if (!$pg) return null;
    $ii = $pg['imageinfo'][0] ?? null;
    if (!$ii || empty($ii['url'])) return null;
    return ['url' => $ii['url'], 'width' => $ii['width'] ?? 0, 'height' => $ii['height'] ?? 0];
}

// Helper: try resolving a filename via en.wikipedia Special:FilePath which will
// typically redirect to an upload.wikimedia.org URL. Returns ['url'=>...]
// on success, or null on failure.
function resolve_enwiki_file_url(string $fileName) {
    // Special:FilePath wants just the filename (no "File:")
    $special = 'https://en.wikipedia.org/wiki/Special:FilePath/' . rawurlencode($fileName);
    $ch = curl_init($special);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // We only need to follow redirects and get the effective URL; don't download body
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'realmsofsilver-dvds/1.0 (+https://example.org)');
    curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if (!$effective) return null;
    if ($debug) $debugAttempts[] = ['resolve_enwiki' => ['file' => $fileName, 'effective' => $effective, 'http' => $httpCode]];
    $parts = parse_url($effective);
    if (!$parts || !isset($parts['host'])) return null;
    $host = strtolower($parts['host']);
    // Accept direct upload.wikimedia.org URLs
    if ($host === 'upload.wikimedia.org' || str_ends_with($host, '.upload.wikimedia.org')) {
        return ['url' => $effective];
    }
    return null;
}

// Helper: resolve a full Special:FilePath URL (on en.wikipedia.org or commons.wikimedia.org)
// to its final effective URL (usually upload.wikimedia.org) by following redirects.
function resolve_special_filepath_url(string $fullUrl) {
    $ch = curl_init($fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'realmsofsilver-dvds/1.0 (+https://example.org)');
    curl_exec($ch);
    $effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    if (!$effective) return null;
    $parts = parse_url($effective);
    if (!$parts || !isset($parts['host'])) return null;
    $host = strtolower($parts['host']);
    if ($host === 'upload.wikimedia.org' || str_ends_with($host, '.upload.wikimedia.org')) {
        return $effective;
    }
    return null;
}

// If caller asked to resolve by file title, do that first
if ($fileParam) {
    // Accept both 'File:Name' and bare filename
    $fileTitle = preg_match('/^File:/i', $fileParam) ? $fileParam : 'File:' . $fileParam;
    $resolved = resolve_commons_file_url($fileTitle);
    if (!$resolved) {
        echo json_encode(['ok' => false, 'error' => 'could not resolve file']);
        exit;
    }
    $url = $resolved['url'];
}

// If caller asked to try deterministic patterns for a title, attempt server-side patterns
if ($titleParam && $tryPatterns && !$url) {
    // build slug similar to client: remove parentheticals and unsafe chars
    $clean = preg_replace('/\s*\([^)]*\)\s*/', '', $titleParam);
    $clean = preg_replace('/["' . "'" . '\\/:?<>#%\[\]|{}]/', '', $clean);
    $clean = trim($clean);
    $slug = preg_replace('/\s+/', '_', $clean);
    $basePatterns = [
        $slug . '_Film_Poster.jpg',
        $slug . '_film_poster.jpg',
        $slug . '_Poster.jpg',
        $slug . '_poster.jpg',
        $slug . '_theatrical_poster.jpg',
        $slug . '_poster_art.jpg',
        $slug . '_poster_artwork.jpg'
    ];
    $patterns = $basePatterns;

    // Also try compact / no-underscore variants (e.g. HungerGamesPoster.jpg)
    $compact = str_replace(['_', '-'], '', $slug);
    if ($compact !== $slug) {
        $compactBase = [
            $compact . '_Poster.jpg',
            $compact . 'Poster.jpg'
        ];
        $patterns = array_merge($patterns, $compactBase);
    }

    // If the cleaned title does NOT already start with 'The', also try 'The_' prefixed variants
    if (!preg_match('/^\s*the\b/i', $clean)) {
        foreach ($basePatterns as $p) $patterns[] = 'The_' . $p;
        if (isset($compactBase)) {
            foreach ($compactBase as $cp) $patterns[] = 'The_' . $cp;
        }
    }

    // Additionally, try variants with a leading 'The' removed (so "The Hunger Games" will
    // also try patterns for "Hunger_Games" and compact "HungerGamesPoster.jpg")
    try {
        $cleanNoThe = preg_replace('/^\s*the\s+/i', '', $clean);
        if ($cleanNoThe && strcasecmp($cleanNoThe, $clean) !== 0) {
            $slugNoThe = preg_replace('/\s+/', '_', trim($cleanNoThe));
            $compactNoThe = str_replace(['_', '-'], '', $slugNoThe);
            $baseNoThe = [
                $slugNoThe . '_Film_Poster.jpg',
                $slugNoThe . '_film_poster.jpg',
                $slugNoThe . '_Poster.jpg',
                $slugNoThe . '_poster.jpg',
                $slugNoThe . '_theatrical_poster.jpg',
                $slugNoThe . '_poster_art.jpg',
                $slugNoThe . '_poster_artwork.jpg'
            ];
            $patterns = array_merge($patterns, $baseNoThe);
            if ($compactNoThe !== $slugNoThe) {
                $compactBaseNoThe = [$compactNoThe . '_Poster.jpg', $compactNoThe . 'Poster.jpg'];
                $patterns = array_merge($patterns, $compactBaseNoThe);
            }
        }
    } catch (Exception $e) {
        // ignore variant generation failures
    }
    $found = null;
    $resolved_file = null;
    $resolved_width = null;
    $resolved_height = null;
    $resolved_source = null;
    foreach ($patterns as $p) {
        if ($debug) $debugAttempts[] = ['pattern_try' => $p];
        $fileTitle = 'File:' . $p;
        // 1) Try en.wikipedia.org Special:FilePath first (server-side) to follow redirects there
        $enResolved = resolve_enwiki_file_url($p);
        if ($enResolved && !empty($enResolved['url'])) {
            $found = $enResolved['url'];
            $resolved_file = $fileTitle;
            $resolved_source = 'enwiki';
            break;
        }

        // 2) Fallback to Commons API resolution
        $resolved = resolve_commons_file_url($fileTitle);
        if ($resolved && !empty($resolved['url'])) {
            $found = $resolved['url'];
            $resolved_file = $fileTitle;
            $resolved_width = $resolved['width'] ?? null;
            $resolved_height = $resolved['height'] ?? null;
            $resolved_source = 'commons';
            break;
        }

        // small delay to be polite
        usleep(100000);
    }
    if ($found) {
        $url = $found;
    } else {
        echo json_encode(['ok' => false, 'error' => 'no deterministic pattern resolved']);
        exit;
    }
}

if (!$url) {
    echo json_encode(['ok' => false, 'error' => 'missing url']);
    exit;
}

// Basic validation: only allow upload.wikimedia.org (final fetch must be an upload URL)
$parts = parse_url($url);
if (!$parts || !isset($parts['host'])) {
    echo json_encode(['ok' => false, 'error' => 'invalid url']);
    exit;
}

$host = strtolower($parts['host']);
// If the caller passed a Special:FilePath or commons/wikipedia URL, attempt to
// resolve it server-side to the canonical upload.wikimedia.org URL before rejecting.
if ($host !== 'upload.wikimedia.org' && !str_ends_with($host, '.upload.wikimedia.org')) {
    // If it's a commons or wikipedia host and looks like a Special:FilePath, try resolving
    if (preg_match('/(commons\.wikimedia\.org|wikipedia\.org)$/i', $host) && (strpos($parts['path'], 'Special:FilePath') !== false || stripos($parts['path'], '/wiki/File:') !== false)) {
        $resolvedEf = resolve_special_filepath_url($url);
        if ($resolvedEf) {
            $url = $resolvedEf;
            $parts = parse_url($url);
            $host = strtolower($parts['host']);
        }
    }
}

if ($host !== 'upload.wikimedia.org' && !str_ends_with($host, '.upload.wikimedia.org')) {
    echo json_encode(['ok' => false, 'error' => 'host not allowed']);
    exit;
}

// Fetch image with cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'realmsofsilver-dvds/1.0 (+https://example.org)');
$data = curl_exec($ch);
$err = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($data === false || $httpCode < 200 || $httpCode >= 300) {
    echo json_encode(['ok' => false, 'error' => 'fetch failed', 'httpCode' => $httpCode, 'curlError' => $err]);
    exit;
}

$b64 = base64_encode($data);
// Include resolved filename and basic metadata when available to help the caller
$response = ['ok' => true, 'contentType' => $contentType, 'data' => $b64];
if (isset($resolved_file)) $response['resolved_file'] = $resolved_file;
if (isset($resolved_source)) $response['resolved_source'] = $resolved_source;
if (isset($resolved_width)) $response['width'] = $resolved_width;
if (isset($resolved_height)) $response['height'] = $resolved_height;
echo json_encode($response);
exit;

?>