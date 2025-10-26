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

$url = isset($_GET['url']) ? trim($_GET['url']) : '';
if (!$url) {
    echo json_encode(['ok' => false, 'error' => 'missing url']);
    exit;
}

// Basic validation: only allow upload.wikimedia.org
$parts = parse_url($url);
if (!$parts || !isset($parts['host'])) {
    echo json_encode(['ok' => false, 'error' => 'invalid url']);
    exit;
}

$host = strtolower($parts['host']);
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
echo json_encode(['ok' => true, 'contentType' => $contentType, 'data' => $b64]);
exit;

?>