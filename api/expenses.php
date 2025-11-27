<?php
/**
 * Sports Camp Management System - Expenses API
 * Handles expense CRUD, categories
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

Session::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$expenseId = $_GET['id'] ?? null;

$db = Database::getInstance()->getConnection();
$user = Session::getUser();

switch ($method) {
    case 'GET':
        if ($expenseId) {
            handleGetExpense($expenseId);
        } else {
            handleListExpenses();
        }
        break;
        
    case 'POST':
        handleCreateExpense();
        break;
        
    case 'PUT':
        if ($expenseId) {
            handleUpdateExpense($expenseId);
        } else {
            Response::error('Expense ID required');
        }
        break;
        
    case 'DELETE':
        if ($expenseId) {
            handleDeleteExpense($expenseId);
        } else {
            Response::error('Expense ID required');
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}

/**
 * List expenses with pagination and search
 */
function handleListExpenses() {
    global $db;
    
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? PAGINATION_PER_PAGE);
    $search = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(title LIKE ? OR details LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm]);
    }
    
    if ($category) {
        $where[] = "category = ?";
        $params[] = Sanitizer::sanitizeInput($category);
    }
    
    if ($startDate) {
        if (!JalaliDate::validate($startDate)) {
            Response::error('Invalid start date format');
        }
        $where[] = "expense_date_jalali >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        if (!JalaliDate::validate($endDate)) {
            Response::error('Invalid end date format');
        }
        $where[] = "expense_date_jalali <= ?";
        $params[] = $endDate;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM expenses {$whereClause}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Get paginated results
    $pagination = Pagination::calculate($page, $perPage, $total);
    $sql = "SELECT e.*, u.username as created_by_username
            FROM expenses e
            LEFT JOIN users u ON e.created_by = u.id
            {$whereClause}
            ORDER BY e.expense_date_jalali DESC, e.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $pagination['per_page'];
    $params[] = $pagination['offset'];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll();
    
    Response::success([
        'expenses' => $expenses,
        'pagination' => $pagination
    ]);
}

/**
 * Get expense details
 */
function handleGetExpense($expenseId) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT e.*, u.username as created_by_username
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$expenseId]);
    $expense = $stmt->fetch();
    
    if (!$expense) {
        Response::error('Expense not found', 404);
    }
    
    // Check if it's a rent expense
    $stmt = $db->prepare("SELECT * FROM rents WHERE expense_id = ?");
    $stmt->execute([$expenseId]);
    $rent = $stmt->fetch();
    if ($rent) {
        $expense['rent_info'] = $rent;
    }
    
    Response::success($expense);
}

/**
 * Create new expense
 */
function handleCreateExpense() {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['title']) || !isset($data['category']) || !isset($data['amount']) || !isset($data['expense_date_jalali'])) {
        Response::error('Title, category, amount, and date are required');
    }
    
    // Validate category (case-insensitive, matching frontend values)
    $allowedCategories = ['rent', 'equipment', 'taxes', 'services', 'other'];
    $categoryLower = strtolower($data['category']);
    if (!in_array($categoryLower, $allowedCategories, true)) {
        Response::error('دسته‌بندی نامعتبر است');
    }
    // Normalize category to match frontend format (capitalize first letter)
    $data['category'] = ucfirst($categoryLower);
    
    if (!JalaliDate::validate($data['expense_date_jalali'])) {
        Response::error('تاریخ هزینه نامعتبر است');
    }
    
    if (!Sanitizer::validateAmount($data['amount'])) {
        Response::error('Invalid amount');
    }
    
    try {
        $db->beginTransaction();
        
        $jalaliDate = JalaliDate::now();
        $stmt = $db->prepare("
            INSERT INTO expenses (title, category, details, amount, expense_date_jalali, receipt_path, created_by, created_at_jalali)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            Sanitizer::sanitizeInput($data['title']),
            Sanitizer::sanitizeInput($data['category']),
            isset($data['details']) ? Sanitizer::sanitizeInput($data['details']) : null,
            $data['amount'],
            $data['expense_date_jalali'],
            isset($data['receipt_path']) ? Sanitizer::sanitizeInput($data['receipt_path']) : null,
            $user['id'],
            $jalaliDate
        ]);
        
        $expenseId = $db->lastInsertId();
        
        // If category is "Rent", create rent record
        if (strtolower($data['category']) === 'rent' && !empty($data['expense_date_jalali'])) {
            $expenseDate = $data['expense_date_jalali'];
            $dateParts = explode('-', $expenseDate);
            $month = (int)$dateParts[1];
            $year = (int)$dateParts[0];
            
            // Check if rent for this month/year already exists
            $stmt = $db->prepare("SELECT id FROM rents WHERE month_jalali = ? AND year_jalali = ?");
            $stmt->execute([$month, $year]);
            if (!$stmt->fetch()) {
                $stmt = $db->prepare("
                    INSERT INTO rents (expense_id, month_jalali, year_jalali, paid, created_at_jalali)
                    VALUES (?, ?, ?, 1, ?)
                ");
                $stmt->execute([$expenseId, $month, $year, $jalaliDate]);
            }
        }
        
        $db->commit();
        
        Audit::log($user['id'], 'create', 'expenses', $expenseId, "Created expense: {$data['title']}");
        
        Response::success([
            'id' => $expenseId,
            'title' => $data['title']
        ], 'Expense created successfully');
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Expense creation error: " . $e->getMessage());
        Response::error('Failed to create expense: ' . $e->getMessage());
    }
}

/**
 * Update expense
 */
function handleUpdateExpense($expenseId) {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if expense exists
    $stmt = $db->prepare("SELECT id FROM expenses WHERE id = ?");
    $stmt->execute([$expenseId]);
    if (!$stmt->fetch()) {
        Response::error('Expense not found', 404);
    }
    
    $fields = [];
    $params = [];
    
    $allowedCategories = ['rent', 'equipment', 'taxes', 'services', 'other'];
    $allowedFields = ['title', 'category', 'details', 'amount', 'expense_date_jalali', 'receipt_path'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            if ($field === 'category') {
                $categoryLower = strtolower($data[$field]);
                if (!in_array($categoryLower, $allowedCategories, true)) {
                    Response::error('دسته‌بندی نامعتبر است');
                }
                $data[$field] = ucfirst($categoryLower);
            }
            if ($field === 'expense_date_jalali' && !JalaliDate::validate($data[$field])) {
                Response::error('تاریخ هزینه نامعتبر است');
            }
            if ($field === 'amount' && !Sanitizer::validateAmount($data[$field])) {
                Response::error('Invalid amount');
            }
            $fields[] = "{$field} = ?";
            $params[] = Sanitizer::sanitizeInput($data[$field]);
        }
    }
    
    if (empty($fields)) {
        Response::error('No fields to update');
    }
    
    $params[] = $expenseId;
    $sql = "UPDATE expenses SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    Audit::log($user['id'], 'update', 'expenses', $expenseId, 'Updated expense');
    
    Response::success(null, 'Expense updated successfully');
}

/**
 * Delete expense
 */
function handleDeleteExpense($expenseId) {
    global $db, $user;
    
    // Check if expense exists
    $stmt = $db->prepare("SELECT title FROM expenses WHERE id = ?");
    $stmt->execute([$expenseId]);
    $expense = $stmt->fetch();
    
    if (!$expense) {
        Response::error('Expense not found', 404);
    }
    
    $stmt = $db->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->execute([$expenseId]);
    
    Audit::log($user['id'], 'delete', 'expenses', $expenseId, "Deleted expense: {$expense['title']}");
    
    Response::success(null, 'Expense deleted successfully');
}


