<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

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
    
    // Get one record with image data to see the structure
    $stmt = $pdo->prepare("SELECT title, picname, pictype, LENGTH(picdata) as pic_size, SUBSTRING(picdata, 1, 50) as pic_sample FROM dvds WHERE picdata IS NOT NULL AND picdata != '' LIMIT 3");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Sample image data from database',
        'records' => $records
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>