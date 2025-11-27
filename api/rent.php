<?php
/**
 * Sports Camp Management System - Rent API
 * Handles rent history, payment status
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

Session::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$db = Database::getInstance()->getConnection();
$user = Session::getUser();

switch ($method) {
    case 'GET':
        if ($action === 'status') {
            handleGetRentStatus();
        } else {
            handleGetRentHistory();
        }
        break;
        
    case 'POST':
        if ($action === 'mark-paid') {
            handleMarkPaid();
        } else {
            Response::error('Invalid action');
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}

/**
 * Get rent history by month
 */
function handleGetRentHistory() {
    global $db;
    
    $month = $_GET['month'] ?? null;
    $year = $_GET['year'] ?? null;
    
    $where = [];
    $params = [];
    
    if ($month) {
        $where[] = "r.month_jalali = ?";
        $params[] = (int)$month;
    }
    
    if ($year) {
        $where[] = "r.year_jalali = ?";
        $params[] = (int)$year;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $db->prepare("
        SELECT r.*, 
               e.title, e.amount, e.expense_date_jalali, e.receipt_path,
               u.username as created_by_username
        FROM rents r
        INNER JOIN expenses e ON r.expense_id = e.id
        LEFT JOIN users u ON e.created_by = u.id
        {$whereClause}
        ORDER BY r.year_jalali DESC, r.month_jalali DESC
    ");
    $stmt->execute($params);
    $rents = $stmt->fetchAll();
    
    // Format month names
    foreach ($rents as &$rent) {
        $rent['month_name'] = JalaliDate::getMonthName($rent['month_jalali']);
    }
    
    Response::success($rents);
}

/**
 * Get rent payment status (paid/unpaid months)
 */
function handleGetRentStatus() {
    global $db;
    
    // Get all months with rent records
    $stmt = $db->query("
        SELECT r.month_jalali, r.year_jalali, r.paid,
               e.amount, e.expense_date_jalali
        FROM rents r
        INNER JOIN expenses e ON r.expense_id = e.id
        ORDER BY r.year_jalali DESC, r.month_jalali DESC
    ");
    $rents = $stmt->fetchAll();
    
    // Group by month/year
    $status = [];
    foreach ($rents as $rent) {
        $key = $rent['year_jalali'] . '-' . str_pad($rent['month_jalali'], 2, '0', STR_PAD_LEFT);
        if (!isset($status[$key])) {
            $status[$key] = [
                'month_jalali' => $rent['month_jalali'],
                'year_jalali' => $rent['year_jalali'],
                'month_name' => JalaliDate::getMonthName($rent['month_jalali']),
                'paid' => (bool)$rent['paid'],
                'amount' => $rent['amount'],
                'expense_date_jalali' => $rent['expense_date_jalali']
            ];
        }
    }
    
    // Sort by date descending
    krsort($status);
    
    Response::success(array_values($status));
}

/**
 * Mark rent month as paid
 */
function handleMarkPaid() {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['month_jalali']) || !isset($data['year_jalali'])) {
        Response::error('Month and year required');
    }
    
    $month = (int)$data['month_jalali'];
    $year = (int)$data['year_jalali'];
    
    // Find rent record
    $stmt = $db->prepare("SELECT id FROM rents WHERE month_jalali = ? AND year_jalali = ?");
    $stmt->execute([$month, $year]);
    $rent = $stmt->fetch();
    
    if (!$rent) {
        Response::error('Rent record not found for this month/year');
    }
    
    $paid = isset($data['paid']) ? (int)(bool)$data['paid'] : 1;
    
    $stmt = $db->prepare("UPDATE rents SET paid = ? WHERE id = ?");
    $stmt->execute([$paid, $rent['id']]);
    
    Audit::log($user['id'], 'update', 'rents', $rent['id'], "Marked rent as " . ($paid ? 'paid' : 'unpaid'));
    
    Response::success(null, 'Rent status updated successfully');
}


