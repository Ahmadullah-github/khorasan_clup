<?php
/**
 * Health check endpoint for Railway deployment monitoring
 */

header('Content-Type: application/json');

try {
    // Check if we can connect to database
    require_once '../api/config.php';
    
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Simple query to test database connectivity
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result && $result['test'] == 1) {
        http_response_code(200);
        echo json_encode([
            'status' => 'healthy',
            'database' => 'connected',
            'timestamp' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get()
        ]);
    } else {
        throw new Exception('Database test query failed');
    }
    
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode([
        'status' => 'unhealthy',
        'error' => 'Database connection failed',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>