<?php
/**
 * Sports Camp Management System - Audit Logs API
 * Retrieves audit log entries
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

Session::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$db = Database::getInstance()->getConnection();
$user = Session::getUser();

// Only admin can view audit logs
Session::requireAdmin();

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            handleListLogs();
        } elseif ($action === 'by-user') {
            handleLogsByUser();
        } elseif ($action === 'by-table') {
            handleLogsByTable();
        } else {
            Response::error('Invalid action');
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}

/**
 * List audit logs with pagination
 */
function handleListLogs() {
    global $db;
    
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 50);
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    $where = [];
    $params = [];
    
    if ($startDate) {
        $where[] = "timestamp_jalali >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $where[] = "timestamp_jalali <= ?";
        $params[] = $endDate;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM audit_logs {$whereClause}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Get paginated results
    $pagination = Pagination::calculate($page, $perPage, $total);
    $sql = "
        SELECT al.*, u.username
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        {$whereClause}
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $pagination['per_page'];
    $params[] = $pagination['offset'];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    Response::success([
        'logs' => $logs,
        'pagination' => $pagination
    ]);
}

/**
 * Get logs by user
 */
function handleLogsByUser() {
    global $db;
    
    $userId = (int)($_GET['user_id'] ?? 0);
    if (!$userId) {
        Response::error('User ID required');
    }
    
    $stmt = $db->prepare("
        SELECT al.*, u.username
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.user_id = ?
        ORDER BY al.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$userId]);
    $logs = $stmt->fetchAll();
    
    Response::success($logs);
}

/**
 * Get logs by table
 */
function handleLogsByTable() {
    global $db;
    
    $tableName = $_GET['table'] ?? '';
    if (!$tableName) {
        Response::error('Table name required');
    }
    
    $stmt = $db->prepare("
        SELECT al.*, u.username
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.table_name = ?
        ORDER BY al.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$tableName]);
    $logs = $stmt->fetchAll();
    
    Response::success($logs);
}

