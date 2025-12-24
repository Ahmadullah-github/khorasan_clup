<?php
/**
 * Sports Camp Management System - Students API
 * Handles student CRUD, registration, renewals, invoices
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

Session::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$studentId = $_GET['id'] ?? null;

$db = Database::getInstance()->getConnection();
$user = Session::getUser();

switch ($method) {
    case 'GET':
        if ($action === 'new') {
            handleGetFormData();
        } elseif ($action === 'expiring') {
            handleGetExpiring();
        } elseif ($studentId && $action === 'registrations') {
            handleGetRegistrations($studentId);
        } elseif ($studentId) {
            handleGetStudent($studentId);
        } else {
            handleListStudents();
        }
        break;
        
    case 'POST':
        if ($studentId && $action === 'renew') {
            handleRenewRegistration($studentId);
        } else {
            handleCreateStudent();
        }
        break;
        
    case 'PUT':
        if ($studentId) {
            handleUpdateStudent($studentId);
        } else {
            Response::error('Student ID required');
        }
        break;
        
    case 'DELETE':
        if ($studentId) {
            handleDeleteStudent($studentId);
        } else {
            Response::error('Student ID required');
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}

/**
 * List students with pagination and search
 */
function handleListStudents() {
    global $db;
    
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? PAGINATION_PER_PAGE);
    $search = $_GET['q'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(first_name LIKE ? OR last_name LIKE ? OR father_name LIKE ? OR contact_number LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($status && in_array($status, ['active', 'inactive'])) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM students {$whereClause}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Get paginated results
    $pagination = Pagination::calculate($page, $perPage, $total);
    $sql = "SELECT * FROM students {$whereClause} ORDER BY created_at_jalali DESC LIMIT ? OFFSET ?";
    $params[] = $pagination['per_page'];
    $params[] = $pagination['offset'];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    Response::success([
        'students' => $students,
        'pagination' => $pagination
    ]);
}

/**
 * Get form data for new student registration
 */
function handleGetFormData() {
    global $db;
    
    // Get coaches with their time slots
    $stmt = $db->query("
        SELECT c.id, c.first_name, c.last_name,
               GROUP_CONCAT(ts.id) as time_slot_ids,
               GROUP_CONCAT(ts.name) as time_slot_names
        FROM coaches c
        LEFT JOIN coach_time_slot cts ON c.id = cts.coach_id
        LEFT JOIN time_slots ts ON cts.time_slot_id = ts.id
        GROUP BY c.id
    ");
    $coaches = $stmt->fetchAll();
    
    // Get all time slots
    $stmt = $db->query("SELECT * FROM time_slots ORDER BY id");
    $timeSlots = $stmt->fetchAll();
    
    Response::success([
        'coaches' => $coaches,
        'time_slots' => $timeSlots
    ]);
}

/**
 * Create new student with registration
 */
function handleCreateStudent() {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('Invalid JSON data: ' . json_last_error_msg());
    }
    
    // Validate required fields
    $required = ['first_name', 'last_name', 'fee_amount', 'time_slot_id', 'coach_id', 'registration_date_jalali'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            Response::error("فیلد '{$field}' الزامی است");
        }
    }
    
    // Validate Jalali date
    if (!JalaliDate::validate($data['registration_date_jalali'])) {
        Response::error('تاریخ ثبت نامعتبر است. لطفاً یک تاریخ معتبر جلالی وارد کنید.');
    }
    
    // Validate amount
    if (!Sanitizer::validateAmount($data['fee_amount'])) {
        Response::error('مبلغ حق‌الاشتراک نامعتبر است. لطفاً یک عدد معتبر وارد کنید.');
    }
    
    // Validate coach exists
    $stmt = $db->prepare("SELECT id FROM coaches WHERE id = ?");
    $stmt->execute([(int)$data['coach_id']]);
    if (!$stmt->fetch()) {
        Response::error('مربی انتخاب شده یافت نشد');
    }
    
    // Validate time slot exists
    $stmt = $db->prepare("SELECT id FROM time_slots WHERE id = ?");
    $stmt->execute([(int)$data['time_slot_id']]);
    if (!$stmt->fetch()) {
        Response::error('زمان کلاس انتخاب شده یافت نشد');
    }
    
    // Validate contact number format if provided
    if (!empty($data['contact_number'])) {
        $contactNumber = preg_replace('/[^0-9]/', '', $data['contact_number']);
        if (strlen($contactNumber) < 8 || strlen($contactNumber) > 15) {
            Response::error('شماره تماس نامعتبر است');
        }
    }
    
    try {
        $db->beginTransaction();
        
        // Create student
        $jalaliDate = JalaliDate::now();
        $stmt = $db->prepare("
            INSERT INTO students (first_name, last_name, father_name, contact_number, photo_path, created_at_jalali, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
        ");
        
        $stmt->execute([
            Sanitizer::sanitizeInput($data['first_name']),
            Sanitizer::sanitizeInput($data['last_name']),
            isset($data['father_name']) ? Sanitizer::sanitizeInput($data['father_name']) : null,
            isset($data['contact_number']) ? Sanitizer::sanitizeInput($data['contact_number']) : null,
            isset($data['photo_path']) ? Sanitizer::sanitizeInput($data['photo_path']) : null,
            $jalaliDate,
            isset($data['notes']) ? Sanitizer::sanitizeInput($data['notes']) : null
        ]);
        
        $studentId = $db->lastInsertId();
        
        // Calculate start and end dates (registration date to end of month)
        $regDate = $data['registration_date_jalali'];
        $regParts = explode('-', $regDate);
        $regYear = (int)$regParts[0];
        $regMonth = (int)$regParts[1];
        
        // End date is last day of current month using centralized date logic
        $endDate = JalaliDate::getMonthEndDate($regYear, $regMonth);
        
        // Create registration
        $stmt = $db->prepare("
            INSERT INTO registrations (student_id, coach_id, time_slot_id, fee_amount, registration_date_jalali, start_date_jalali, end_date_jalali, status, created_at_jalali)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)
        ");
        
        $stmt->execute([
            $studentId,
            (int)$data['coach_id'],
            (int)$data['time_slot_id'],
            $data['fee_amount'],
            $regDate,
            $regDate,
            $endDate,
            $jalaliDate
        ]);
        
        $registrationId = $db->lastInsertId();
        
        // Create payment record
        $stmt = $db->prepare("
            INSERT INTO payments (registration_id, amount, payment_date_jalali, method)
            VALUES (?, ?, ?, 'cash')
        ");
        $stmt->execute([$registrationId, $data['fee_amount'], $regDate]);
        
        // Generate invoice
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($registrationId, 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("
            INSERT INTO invoices (registration_id, total_amount, issued_date_jalali, invoice_number)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$registrationId, $data['fee_amount'], $jalaliDate, $invoiceNumber]);
        
        $invoiceId = $db->lastInsertId();
        
        // Update registration with invoice_id
        $stmt = $db->prepare("UPDATE registrations SET invoice_id = ? WHERE id = ?");
        $stmt->execute([$invoiceId, $registrationId]);
        
        $db->commit();
        
        Audit::log($user['id'], 'create', 'students', $studentId, "Created student and registration");
        
        Response::success([
            'student_id' => $studentId,
            'registration_id' => $registrationId,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber
        ], 'Student registered successfully');
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Student creation PDO error: " . $e->getMessage());
        
        // Handle specific database errors
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        // Foreign key constraint violations
        if (strpos($errorMessage, 'foreign key constraint') !== false || $errorCode == 23000) {
            if (strpos($errorMessage, 'coach_id') !== false) {
                Response::error('مربی انتخاب شده معتبر نیست');
            } elseif (strpos($errorMessage, 'time_slot_id') !== false) {
                Response::error('زمان کلاس انتخاب شده معتبر نیست');
            } else {
                Response::error('خطا در ارتباط با داده‌های مرتبط. لطفاً دوباره تلاش کنید.');
            }
        }
        // Unique constraint violations
        elseif (strpos($errorMessage, 'Duplicate entry') !== false || strpos($errorMessage, 'unique constraint') !== false) {
            Response::error('این رکورد قبلاً ثبت شده است');
        }
        // General database errors
        else {
            Response::error('خطا در ذخیره اطلاعات: ' . (strpos($errorMessage, 'SQLSTATE') !== false ? 'خطای پایگاه داده' : 'لطفاً دوباره تلاش کنید'));
        }
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Student creation error: " . $e->getMessage());
        Response::error('خطا در ثبت دانش‌آموز: ' . $e->getMessage());
    }
}

/**
 * Get student details
 */
function handleGetStudent($studentId) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    
    if (!$student) {
        Response::error('Student not found', 404);
    }
    
    Response::success($student);
}

/**
 * Update student
 */
function handleUpdateStudent($studentId) {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('داده‌های ارسالی نامعتبر است');
    }
    
    // Check if student exists
    $stmt = $db->prepare("SELECT id, first_name, last_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $existingStudent = $stmt->fetch();
    
    if (!$existingStudent) {
        Response::error('دانش‌آموز یافت نشد', 404);
    }
    
    $fields = [];
    $params = [];
    $changes = [];
    
    $allowedFields = ['first_name', 'last_name', 'father_name', 'contact_number', 'photo_path', 'status', 'notes'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $value = Sanitizer::sanitizeInput($data[$field]);
            
            // Validate specific fields
            if ($field === 'first_name' && empty(trim($value))) {
                Response::error('نام نمی‌تواند خالی باشد');
            }
            if ($field === 'last_name' && empty(trim($value))) {
                Response::error('نام خانوادگی نمی‌تواند خالی باشد');
            }
            if ($field === 'contact_number' && !empty($value)) {
                $cleanNumber = preg_replace('/[^0-9]/', '', $value);
                if (strlen($cleanNumber) < 8 || strlen($cleanNumber) > 15) {
                    Response::error('شماره تماس نامعتبر است');
                }
            }
            if ($field === 'status' && !in_array($value, ['active', 'inactive'])) {
                Response::error('وضعیت نامعتبر است');
            }
            
            $fields[] = "{$field} = ?";
            $params[] = $value;
            $changes[] = $field;
        }
    }
    
    if (empty($fields)) {
        Response::error('هیچ فیلدی برای به‌روزرسانی ارسال نشده است');
    }
    
    try {
        $params[] = $studentId;
        $sql = "UPDATE students SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $changesStr = implode(', ', $changes);
        Audit::log($user['id'], 'update', 'students', $studentId, "Updated fields: {$changesStr}");
        
        Response::success(null, 'اطلاعات دانش‌آموز با موفقیت به‌روزرسانی شد');
        
    } catch (PDOException $e) {
        error_log("Student update error: " . $e->getMessage());
        Response::error('خطا در به‌روزرسانی اطلاعات. لطفاً دوباره تلاش کنید.');
    }
}

/**
 * Delete student
 */
function handleDeleteStudent($studentId) {
    global $db, $user;
    
    // Check if student exists
    $stmt = $db->prepare("SELECT first_name, last_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    
    if (!$student) {
        Response::error('دانش‌آموز یافت نشد', 404);
    }
    
    try {
        $db->beginTransaction();
        
        // Check for active registrations
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM registrations WHERE student_id = ? AND status = 'active'");
        $stmt->execute([$studentId]);
        $activeRegs = $stmt->fetch()['count'];
        
        if ($activeRegs > 0) {
            // Optionally: deactivate instead of blocking
            // For now, we'll allow deletion but warn in audit log
        }
        
        // Delete related records (cascade manually for safety)
        // Delete payments through registrations
        $stmt = $db->prepare("
            DELETE p FROM payments p 
            INNER JOIN registrations r ON p.registration_id = r.id 
            WHERE r.student_id = ?
        ");
        $stmt->execute([$studentId]);
        
        // Delete invoices through registrations
        $stmt = $db->prepare("
            DELETE i FROM invoices i 
            INNER JOIN registrations r ON i.registration_id = r.id 
            WHERE r.student_id = ?
        ");
        $stmt->execute([$studentId]);
        
        // Delete registrations
        $stmt = $db->prepare("DELETE FROM registrations WHERE student_id = ?");
        $stmt->execute([$studentId]);
        
        // Delete student
        $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        
        $db->commit();
        
        Audit::log($user['id'], 'delete', 'students', $studentId, "Deleted student: {$student['first_name']} {$student['last_name']}");
        
        Response::success(null, 'دانش‌آموز با موفقیت حذف شد');
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Student delete error: " . $e->getMessage());
        
        // Check for foreign key constraint
        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            Response::error('این دانش‌آموز دارای رکوردهای مرتبط است و قابل حذف نیست');
        }
        
        Response::error('خطا در حذف دانش‌آموز. لطفاً دوباره تلاش کنید.');
    }
}

/**
 * Get student registrations
 */
function handleGetRegistrations($studentId) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT r.*, 
               c.first_name as coach_first_name, c.last_name as coach_last_name,
               ts.name as time_slot_name,
               i.invoice_number
        FROM registrations r
        LEFT JOIN coaches c ON r.coach_id = c.id
        LEFT JOIN time_slots ts ON r.time_slot_id = ts.id
        LEFT JOIN invoices i ON r.invoice_id = i.id
        WHERE r.student_id = ?
        ORDER BY r.created_at_jalali DESC
    ");
    $stmt->execute([$studentId]);
    $registrations = $stmt->fetchAll();
    
    Response::success($registrations);
}

/**
 * Renew student registration
 */
function handleRenewRegistration($studentId) {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Get last registration
    $stmt = $db->prepare("
        SELECT * FROM registrations
        WHERE student_id = ?
        ORDER BY end_date_jalali DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $lastReg = $stmt->fetch();
    
    if (!$lastReg) {
        Response::error('No previous registration found');
    }
    
    // Calculate new dates (next month) using centralized date logic
    $endDate = $lastReg['end_date_jalali'];
    $endParts = explode('-', $endDate);
    $endYear = (int)$endParts[0];
    $endMonth = (int)$endParts[1];
    
    // Get next month using centralized function
    $nextMonth = JalaliDate::getNextMonth($endYear, $endMonth);
    $newYear = $nextMonth['year'];
    $newMonth = $nextMonth['month'];
    
    // Get date range for the new month
    $newStartDate = JalaliDate::getMonthStartDate($newYear, $newMonth);
    $newEndDate = JalaliDate::getMonthEndDate($newYear, $newMonth);
    
    $feeAmount = $data['fee_amount'] ?? $lastReg['fee_amount'];
    
    // Validate fee amount
    if (!Sanitizer::validateAmount($feeAmount)) {
        Response::error('مبلغ حق‌الاشتراک نامعتبر است. لطفاً یک عدد معتبر وارد کنید.');
    }
    
    $jalaliDate = JalaliDate::now();
    
    try {
        $db->beginTransaction();
        
        // Create new registration
        $stmt = $db->prepare("
            INSERT INTO registrations (student_id, coach_id, time_slot_id, fee_amount, registration_date_jalali, start_date_jalali, end_date_jalali, status, created_at_jalali)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)
        ");
        $stmt->execute([
            $studentId,
            $lastReg['coach_id'],
            $lastReg['time_slot_id'],
            $feeAmount,
            $jalaliDate,
            $newStartDate,
            $newEndDate,
            $jalaliDate
        ]);
        
        $registrationId = $db->lastInsertId();
        
        // Create payment
        $stmt = $db->prepare("
            INSERT INTO payments (registration_id, amount, payment_date_jalali, method)
            VALUES (?, ?, ?, 'cash')
        ");
        $stmt->execute([$registrationId, $feeAmount, $jalaliDate]);
        
        // Generate invoice
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($registrationId, 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("
            INSERT INTO invoices (registration_id, total_amount, issued_date_jalali, invoice_number)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$registrationId, $feeAmount, $jalaliDate, $invoiceNumber]);
        
        $invoiceId = $db->lastInsertId();
        
        $stmt = $db->prepare("UPDATE registrations SET invoice_id = ? WHERE id = ?");
        $stmt->execute([$invoiceId, $registrationId]);
        
        // Update old registration status
        $stmt = $db->prepare("UPDATE registrations SET status = 'expired' WHERE id = ?");
        $stmt->execute([$lastReg['id']]);
        
        $db->commit();
        
        Audit::log($user['id'], 'renew', 'registrations', $registrationId, "Renewed registration for student {$studentId}");
        
        Response::success([
            'registration_id' => $registrationId,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber
        ], 'Registration renewed successfully');
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Renewal error: " . $e->getMessage());
        Response::error('Failed to renew registration: ' . $e->getMessage());
    }
}

/**
 * Get expiring students (need renewal)
 */
function handleGetExpiring() {
    global $db;
    
    $today = JalaliDate::now();
    
    $stmt = $db->prepare("
        SELECT s.*, r.end_date_jalali, r.status as reg_status
        FROM students s
        INNER JOIN registrations r ON s.id = r.student_id
        WHERE r.status = 'active' AND r.end_date_jalali <= ?
        ORDER BY r.end_date_jalali ASC
    ");
    $stmt->execute([$today]);
    $students = $stmt->fetchAll();
    
    Response::success($students);
}


