<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Read configuration from file
    $config = parse_ini_file(__DIR__ . '/config.ini', true);
    
    if (!$config || !isset($config['database'])) {
        throw new Exception('Configuration file not found or invalid');
    }
    
    $db = $config['database'];
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}", 
        $db['username'], 
        $db['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Optional auth: if an API key is configured, require it for mutating requests
    $apiKey = '';
    if (isset($config['auth']) && isset($config['auth']['api_key'])) {
        $apiKey = trim($config['auth']['api_key']);
    }

    // Helper to fetch the Authorization header in various SAPIs
    $getAuthHeader = function() {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) return trim($_SERVER['HTTP_AUTHORIZATION']);
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            if (isset($h['Authorization'])) return trim($h['Authorization']);
            if (isset($h['authorization'])) return trim($h['authorization']);
        }
        return null;
    };

    // Enforce auth only when an apiKey is configured and the request method mutates state
    $mutating = in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT','DELETE']);
    if ($apiKey && $mutating) {
        $authHeader = $getAuthHeader();
        $authorized = false;
        if ($authHeader) {
            // Accept 'Bearer <token>' format
            if (stripos($authHeader, 'Bearer ') === 0) {
                $tok = substr($authHeader, 7);
                if (hash_equals($apiKey, $tok)) $authorized = true;
            }
            // Also accept the raw token in the header
            if (!$authorized && hash_equals($apiKey, $authHeader)) $authorized = true;
        }
        if (!$authorized) {
            // Log the auth failure for audit (do not store raw token)
            try {
                $logFile = __DIR__ . '/auth_failures.log';
                $masked = $authHeader ? (strlen($authHeader) > 8 ? '****' . substr($authHeader, -8) : '****') : '';
                $entry = json_encode([
                    'time' => date('c'),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                    'uri' => $_SERVER['REQUEST_URI'] ?? '',
                    'provided' => $masked
                ]) . PHP_EOL;
                @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            } catch (Exception $e) {}
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit(0);
        }
    }

    // Helper to normalize notes/description text returned to clients
    $normalize_notes = function($s) {
        if ($s === null) return '';
        $s = (string)$s;
        // Replace literal "?s" (mojibake for apostrophe-s) with "'s"
        // Also handle Unicode replacement char U+FFFD followed by s
        $s = preg_replace('/\?s\b/u', "'s", $s);
        $s = preg_replace('/\x{FFFD}s\b/u', "'s", $s);
        return $s;
    };
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        // Support server-side search 'q' parameter
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        // Support server-side sorting: 'sort' (column) and 'dir' (asc|desc)
        $sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';
        $dir = isset($_GET['dir']) ? strtolower(trim($_GET['dir'])) : 'asc';

        // Whitelist of allowed sort columns (map frontend names to DB columns)
        $allowedSortColumns = [
            'title' => 'title',
            'year' => 'year',
            'type' => 'type',
            'format' => 'type',
            'director' => 'director',
            'actors' => 'stars',
            'stars' => 'stars',
            'genre' => 'genre'
        ];

        if (isset($allowedSortColumns[$sort])) {
            $orderCol = $allowedSortColumns[$sort];
        } else {
            $orderCol = 'title';
        }
        $dir = ($dir === 'desc') ? 'DESC' : 'ASC';
        // Special-case numeric ordering for `year` which is stored as text in the DB
        if ($orderCol === 'year') {
            // Only cast purely numeric years to unsigned for numeric ordering; non-numeric values sort as NULL.
            // Tie-break by title to provide a stable order.
            $orderClause = "ORDER BY (CASE WHEN `year` REGEXP '^[0-9]+' THEN CAST(`year` AS UNSIGNED) ELSE NULL END) $dir, `title` ASC";
        } else {
            $orderClause = "ORDER BY `$orderCol` $dir";
        }
        if ($q !== '') {
            $like = "%" . $q . "%";
            // Get total count for filtered results
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM dvds WHERE title LIKE :q OR stars LIKE :q OR director LIKE :q OR genre LIKE :q OR year LIKE :q");
            $countStmt->bindValue(':q', $like, PDO::PARAM_STR);
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            $totalPages = ceil($totalRecords / $limit);

            // Get paginated filtered results (apply validated ORDER BY)
            $stmt = $pdo->prepare("SELECT * FROM dvds WHERE title LIKE :q OR stars LIKE :q OR director LIKE :q OR genre LIKE :q OR year LIKE :q $orderClause LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':q', $like, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM dvds");
            $countStmt->execute();
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            $totalPages = ceil($totalRecords / $limit);
            
            // Get paginated results (apply validated ORDER BY)
            $stmt = $pdo->prepare("SELECT * FROM dvds $orderClause LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        $dvds = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Map database columns to front-end shape. The primary key in this DB is `dkey`.
            $dvd = [
                'id' => isset($row['dkey']) ? (int)$row['dkey'] : (isset($row['id']) ? (int)$row['id'] : 0),
                'title' => $row['title'] ?? $row['Title'] ?? '',
                // year is stored as tinytext in this schema; return as number when possible
                    'year' => is_numeric($row['year'] ?? '') ? (int)$row['year'] : ($row['year'] ?? 0),
                // map `stars` column to `actors` for the frontend; leave `director` separate (DB may add later)
                'actors' => $row['stars'] ?? '',
                'director' => $row['director'] ?? '',
                'genre' => $row['genre'] ?? $row['Genre'] ?? '',
                    'rating' => $row['rating'] ?? '',
                    // runtime is stored as text in the DB (e.g. "120 min", "130", "2h 10m")
                    'runtime' => isset($row['runtime']) && $row['runtime'] !== null ? (string)$row['runtime'] : null,
                // database uses `type` column for format
                'format' => $row['format'] ?? $row['type'] ?? $row['Type'] ?? 'DVD',
                    'music' => $row['music'] ?? '',
                // description column maps to notes
                'notes' => $normalize_notes($row['description'] ?? $row['notes'] ?? '')
            ];
            
            // Add image data if available
            if (!empty($row['picdata']) && !empty($row['pictype'])) {
                $dvd['image'] = [
                    'name' => $row['picname'] ?? '',
                    'type' => $row['pictype'],
                    'data' => $row['picdata']
                ];
            }
            
            $dvds[] = $dvd;
        }
        
        // Return paginated response
        echo json_encode([
            'data' => $dvds,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => (int)$totalRecords,
                'per_page' => $limit,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create new DVD from JSON payload
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (!$data || !is_array($data)) {
            echo json_encode(['error' => 'Invalid JSON payload']);
            exit(0);
        }

        // Prepare insert using DB columns that exist in this schema
        $sql = "INSERT INTO dvds
            (`title`, `director`, `stars`, `genre`, `year`, `runtime`, `type`, `music`, `description`, `picdata`, `pictype`, `picname`)
            VALUES
            (:title, :director, :stars, :genre, :year, :runtime, :type, :music, :description, :picdata, :pictype, :picname)";

        $stmt = $pdo->prepare($sql);

        // Image handling: expect image => ['name','type','data'] or absent
        // Some older schemas may declare picdata as NOT NULL. To avoid inserting NULL
        // and triggering integrity constraint violations, default to empty strings when
        // no image data is supplied. When an image is supplied, use its values.
        $picdata = '';
        $pictype = '';
        $picname = '';
        if (isset($data['image']) && is_array($data['image'])) {
            $img = $data['image'];
            if (!empty($img['data'])) {
                $picdata = $img['data'];
                $pictype = $img['type'] ?? '';
                $picname = $img['name'] ?? '';
            }
        }

        // Map incoming frontend fields to DB columns
    $stmt->bindValue(':title', $data['title'] ?? '', PDO::PARAM_STR);
    // map actors (frontend) or stars (legacy) into DB `stars` column
    $stmt->bindValue(':stars', $data['actors'] ?? $data['stars'] ?? '', PDO::PARAM_STR);
    // director is its own column
    $stmt->bindValue(':director', $data['director'] ?? '', PDO::PARAM_STR);
        // genre and year stored as text
    $stmt->bindValue(':genre', $data['genre'] ?? '', PDO::PARAM_STR);
    // music (composer) optional text field
    $stmt->bindValue(':music', $data['music'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':year', isset($data['year']) ? (string)$data['year'] : '', PDO::PARAM_STR);
        // runtime stored as text in DB. Accept any string (e.g. "120 min", "130", "2h 10m").
        $runtimeVal = null;
        if (isset($data['runtime'])) {
            $rv = trim((string)$data['runtime']);
            if ($rv === '') {
                $runtimeVal = null;
            } else {
                $runtimeVal = $rv;
            }
        }
        if ($runtimeVal === null) {
            $stmt->bindValue(':runtime', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':runtime', $runtimeVal, PDO::PARAM_STR);
        }
    // format -> type
    $stmt->bindValue(':type', $data['format'] ?? $data['type'] ?? 'DVD', PDO::PARAM_STR);
        // notes -> description
        $stmt->bindValue(':description', $data['notes'] ?? $data['description'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':picdata', $picdata, PDO::PARAM_STR);
    $stmt->bindValue(':pictype', $pictype, PDO::PARAM_STR);
    $stmt->bindValue(':picname', $picname, PDO::PARAM_STR);

        $stmt->execute();
        $newId = (int)$pdo->lastInsertId();

        // Return the created object (map dkey -> id)
        $rowStmt = $pdo->prepare("SELECT * FROM dvds WHERE dkey = :dkey");
        $rowStmt->bindValue(':dkey', $newId, PDO::PARAM_INT);
        $rowStmt->execute();
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);

        $dvd = [
            'id' => isset($row['dkey']) ? (int)$row['dkey'] : (int)($row['id'] ?? 0),
            'title' => $row['title'] ?? '',
            'year' => is_numeric($row['year'] ?? '') ? (int)$row['year'] : ($row['year'] ?? 0),
            'actors' => $row['stars'] ?? '',
            'director' => $row['director'] ?? '',
            'genre' => $row['genre'] ?? '',
            'runtime' => isset($row['runtime']) && $row['runtime'] !== null ? (string)$row['runtime'] : null,
            'format' => $row['type'] ?? $row['format'] ?? 'DVD',
            'music' => $row['music'] ?? '',
            'notes' => $normalize_notes($row['description'] ?? $row['notes'] ?? '')
        ];

        if (!empty($row['picdata']) && !empty($row['pictype'])) {
            $dvd['image'] = [
                'name' => $row['picname'] ?? '',
                'type' => $row['pictype'],
                'data' => $row['picdata']
            ];
        }

        echo json_encode($dvd);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update existing DVD from JSON payload
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (!$data || !is_array($data) || (empty($data['id']) && empty($data['dkey']))) {
            echo json_encode(['error' => 'Invalid JSON payload or missing id/dkey']);
            exit(0);
        }

        $id = !empty($data['dkey']) ? (int)$data['dkey'] : (int)$data['id'];

        // Build update fields dynamically to support partial updates
        $fields = [];
        $params = [':id' => $id];

        // Only update columns that exist in this schema. Map frontend names to DB columns.
        $updatableMap = [
            'title' => 'title',
            'year' => 'year',
            'actors' => 'stars',
            'stars' => 'stars',
            'director' => 'director',
            'genre' => 'genre',
            'music' => 'music',
            'format' => 'type',
            'type' => 'type',
            'runtime' => 'runtime',
            'notes' => 'description',
            'description' => 'description'
        ];
        $updatable = array_keys($updatableMap);
        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $dbcol = $updatableMap[$field];
                $fields[] = "`$dbcol` = :$dbcol";
                // runtime should be stored as text; accept any string value provided
                if ($dbcol === 'runtime') {
                    $val = $data[$field];
                    $runtimeVal = null;
                    if ($val !== '' && $val !== null) {
                        $runtimeVal = (string)$val;
                    }
                    $params[":$dbcol"] = $runtimeVal;
                } else {
                    $params[":$dbcol"] = $data[$field] === '' ? '' : $data[$field];
                }
            }
        }

        // Handle image: if 'image' present and is array -> set fields; if explicitly null -> remove image; if omitted -> leave unchanged
        if (array_key_exists('image', $data)) {
            if (is_array($data['image'])) {
                $fields[] = "`picdata` = :picdata";
                $fields[] = "`pictype` = :pictype";
                $fields[] = "`picname` = :picname";
                // default to empty strings instead of NULL to satisfy NOT NULL schemas
                $params[':picdata'] = $data['image']['data'] ?? '';
                $params[':pictype'] = $data['image']['type'] ?? '';
                $params[':picname'] = $data['image']['name'] ?? '';
            } else {
                // explicitly null -> clear image fields; set to empty strings to avoid inserting NULL
                $fields[] = "`picdata` = :picdata";
                $fields[] = "`pictype` = :pictype";
                $fields[] = "`picname` = :picname";
                $params[':picdata'] = '';
                $params[':pictype'] = '';
                $params[':picname'] = '';
            }
        }

        if (empty($fields)) {
            echo json_encode(['error' => 'No fields to update']);
            exit(0);
        }

        $sql = "UPDATE dvds SET " . implode(', ', $fields) . " WHERE dkey = :id";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            if ($k === ':id') {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } elseif ($k === ':year' || $k === ':runtime') {
                if ($v === '' || $v === null) {
                    $stmt->bindValue($k, null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($k, $v, PDO::PARAM_STR);
                }
            } elseif ($k === ':music') {
                // music is a free-text column
                $stmt->bindValue($k, $v === null ? '' : $v, PDO::PARAM_STR);
            } else {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
        }

        $stmt->execute();

        // Return updated row
        $rowStmt = $pdo->prepare("SELECT * FROM dvds WHERE dkey = :id");
        $rowStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $rowStmt->execute();
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['error' => 'DVD not found']);
            exit(0);
        }

        $dvd = [
            'id' => isset($row['dkey']) ? (int)$row['dkey'] : (int)($row['id'] ?? 0),
            'title' => $row['title'] ?? '',
            'year' => is_numeric($row['year'] ?? '') ? (int)$row['year'] : ($row['year'] ?? 0),
            'actors' => $row['stars'] ?? '',
            'director' => $row['director'] ?? '',
            'genre' => $row['genre'] ?? '',
            'runtime' => isset($row['runtime']) && $row['runtime'] !== null ? (string)$row['runtime'] : null,
            'format' => $row['type'] ?? $row['format'] ?? 'DVD',
            'music' => $row['music'] ?? '',
            'notes' => $normalize_notes($row['description'] ?? $row['notes'] ?? '')
        ];

        if (!empty($row['picdata']) && !empty($row['pictype'])) {
            $dvd['image'] = [
                'name' => $row['picname'] ?? '',
                'type' => $row['pictype'],
                'data' => $row['picdata']
            ];
        }

        echo json_encode($dvd);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete DVD by id (provided as query param)
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['error' => 'Missing id']);
            exit(0);
        }

        // Use dkey as the primary key in this schema
        $stmt = $pdo->prepare("DELETE FROM dvds WHERE dkey = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Unsupported method']);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>