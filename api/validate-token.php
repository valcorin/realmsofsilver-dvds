<?php
header('Content-Type: application/json; charset=utf-8');

// Load config
$configPath = __DIR__ . '/config.ini';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "config.ini not found"]);
    exit;
}

$config = parse_ini_file($configPath, true);
$apiKey = '';
if (isset($config['auth']) && isset($config['auth']['api_key'])) {
    $apiKey = trim($config['auth']['api_key']);
}

// If no api_key configured, treat as open (validation passes)
if ($apiKey === '') {
    echo json_encode(["ok" => true, "message" => "no api_key configured, open access"]);
    exit;
}

// Get Authorization header (Bearer token)
$headers = null;
if (function_exists('getallheaders')) {
    $headers = getallheaders();
} else {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
}

$token = null;
if (isset($headers['Authorization'])) {
    $auth = $headers['Authorization'];
    if (stripos($auth, 'Bearer ') === 0) {
        $token = substr($auth, 7);
    } else {
        $token = $auth; // accept raw token too
    }
} elseif (isset($_REQUEST['api_key'])) {
    $token = $_REQUEST['api_key'];
}

if ($token === null) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "missing token"]);
    exit;
}

if (hash_equals($apiKey, $token)) {
    echo json_encode(["ok" => true]);
    exit;
}

http_response_code(401);
echo json_encode(["ok" => false, "error" => "invalid token"]);
exit;
