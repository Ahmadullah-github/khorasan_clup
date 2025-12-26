<?php
/**
 * Sports Camp Management System - Offline Sync API
 * Handles synchronization of offline changes from IndexedDB
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

Session::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$db = Database::getInstance()->getConnection();
$user = Session::getUser();

switch ($method) {
    case 'POST':
        if ($action === 'push') {
            handlePushChanges();
        } else {
            Response::error('عملیات نامعتبر');
        }
        break;
        
    case 'GET':
        if ($action === 'pull') {
            handlePullChanges();
        } else {
            Response::error('عملیات نامعتبر');
        }
        break;
        
    default:
        Response::error('روش مجاز نیست', 405);
}

/**
 * Push offline changes to server
 */
function handlePushChanges() {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['changes']) || !is_array($data['changes'])) {
        Response::error('داده‌های تغییرات نامعتبر');
    }
    
    $results = [];
    $errors = [];
    
    foreach ($data['changes'] as $change) {
        if (!isset($change['table']) || !isset($change['action']) || !isset($change['data'])) {
            $errors[] = 'فرمت تغییر نامعتبر';
            continue;
        }
        
        $table = $change['table'];
        $action = $change['action'];
        $changeData = $change['data'];
        $localId = $change['local_id'] ?? null;
        
        try {
            switch ($table) {
                case 'students':
                    $result = syncStudent($action, $changeData, $localId);
                    break;
                case 'coaches':
                    $result = syncCoach($action, $changeData, $localId);
                    break;
                case 'expenses':
                    $result = syncExpense($action, $changeData, $localId);
                    break;
                case 'registrations':
                    $result = syncRegistration($action, $changeData, $localId);
                    break;
                default:
                    $errors[] = "Unknown table: {$table}";
                    continue 2;
            }
            
            $results[] = [
                'local_id' => $localId,
                'server_id' => $result['id'] ?? null,
                'status' => 'success',
                'table' => $table
            ];
            
        } catch (Exception $e) {
            $errors[] = [
                'local_id' => $localId,
                'table' => $table,
                'error' => $e->getMessage()
            ];
        }
    }
    
    Response::success([
        'results' => $results,
        'errors' => $errors
    ]);
}

/**
 * Pull changes from server since last sync
 */
function handlePullChanges() {
    global $db;
    
    $lastSync = $_GET['last_sync'] ?? null;
    $tables = isset($_GET['tables']) ? explode(',', $_GET['tables']) : ['students', 'coaches', 'expenses', 'registrations'];
    
    $changes = [];
    
    foreach ($tables as $table) {
        $where = '';
        $params = [];
        
        if ($lastSync) {
            // Convert Jalali date to compare
            $where = "WHERE created_at > ?";
            $params = [$lastSync];
        }
        
        switch ($table) {
            case 'students':
                $stmt = $db->prepare("SELECT * FROM students {$where} ORDER BY created_at DESC LIMIT 100");
                $stmt->execute($params);
                $changes[$table] = $stmt->fetchAll();
                break;
            case 'coaches':
                $stmt = $db->prepare("SELECT * FROM coaches {$where} ORDER BY created_at DESC LIMIT 100");
                $stmt->execute($params);
                $changes[$table] = $stmt->fetchAll();
                break;
            case 'expenses':
                $stmt = $db->prepare("SELECT * FROM expenses {$where} ORDER BY created_at DESC LIMIT 100");
                $stmt->execute($params);
                $changes[$table] = $stmt->fetchAll();
                break;
            case 'registrations':
                $stmt = $db->prepare("SELECT * FROM registrations {$where} ORDER BY created_at DESC LIMIT 100");
                $stmt->execute($params);
                $changes[$table] = $stmt->fetchAll();
                break;
        }
    }
    
    Response::success([
        'changes' => $changes,
        'sync_timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Sync helper functions
function syncStudent($action, $data, $localId) {
    global $db, $user;
    
    if ($action === 'create') {
        $jalaliDate = JalaliDate::now();
        $stmt = $db->prepare("
            INSERT INTO students (first_name, last_name, father_name, contact_number, photo_path, created_at_jalali, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['father_name'] ?? null,
            $data['contact_number'] ?? null,
            $data['photo_path'] ?? null,
            $data['created_at_jalali'] ?? $jalaliDate,
            $data['status'] ?? 'active',
            $data['notes'] ?? null
        ]);
        return ['id' => $db->lastInsertId()];
    }
    
    // Update and delete would require server_id
    return ['id' => null];
}

function syncCoach($action, $data, $localId) {
    global $db, $user;
    
    if ($action === 'create') {
        $jalaliDate = JalaliDate::now();
        $stmt = $db->prepare("
            INSERT INTO coaches (first_name, last_name, photo_path, created_at_jalali, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['photo_path'] ?? null,
            $data['created_at_jalali'] ?? $jalaliDate,
            $data['notes'] ?? null
        ]);
        return ['id' => $db->lastInsertId()];
    }
    
    return ['id' => null];
}

function syncExpense($action, $data, $localId) {
    global $db, $user;
    
    if ($action === 'create') {
        $jalaliDate = JalaliDate::now();
        $stmt = $db->prepare("
            INSERT INTO expenses (title, category, details, amount, expense_date_jalali, receipt_path, created_by, created_at_jalali)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['title'],
            $data['category'],
            $data['details'] ?? null,
            $data['amount'],
            $data['expense_date_jalali'],
            $data['receipt_path'] ?? null,
            $user['id'],
            $data['created_at_jalali'] ?? $jalaliDate
        ]);
        return ['id' => $db->lastInsertId()];
    }
    
    return ['id' => null];
}

function syncRegistration($action, $data, $localId) {
    global $db, $user;
    
    if ($action === 'create') {
        $jalaliDate = JalaliDate::now();
        $stmt = $db->prepare("
            INSERT INTO registrations (student_id, coach_id, time_slot_id, fee_amount, registration_date_jalali, start_date_jalali, end_date_jalali, status, created_at_jalali)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['student_id'],
            $data['coach_id'],
            $data['time_slot_id'],
            $data['fee_amount'],
            $data['registration_date_jalali'],
            $data['start_date_jalali'],
            $data['end_date_jalali'],
            $data['status'] ?? 'active',
            $data['created_at_jalali'] ?? $jalaliDate
        ]);
        return ['id' => $db->lastInsertId()];
    }
    
    return ['id' => null];
}

