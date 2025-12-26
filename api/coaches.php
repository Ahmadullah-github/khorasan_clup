<?php
/**
 * Sports Camp Management System - Coaches API
 * Handles coach CRUD, time slot management, contract management, photo upload
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

Session::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$coachId = $_GET['id'] ?? null;
$action = $_GET['action'] ?? '';

$db = Database::getInstance()->getConnection();
$user = Session::getUser();

switch ($method) {
    case 'GET':
        if ($action === 'time-slots') {
            handleGetTimeSlots();
        } elseif ($action === 'contract-history' && $coachId) {
            handleGetContractHistory($coachId);
        } elseif ($action === 'cleanup-preview') {
            handleCleanupPreview();
        } elseif ($coachId) {
            handleGetCoach($coachId);
        } else {
            handleListCoaches();
        }
        break;
        
    case 'POST':
        if ($action === 'upload-photo') {
            handlePhotoUpload();
        } elseif ($action === 'cleanup') {
            handleCleanupDeletedCoaches();
        } else {
            handleCreateCoach();
        }
        break;
        
    case 'PUT':
        if ($coachId) {
            handleUpdateCoach($coachId);
        } else {
            Response::error('شناسه مربی الزامی است');
        }
        break;
        
    case 'DELETE':
        if ($coachId) {
            handleDeleteCoach($coachId);
        } else {
            Response::error('شناسه مربی الزامی است');
        }
        break;
        
    default:
        Response::error('روش مجاز نیست', 405);
}

/**
 * List coaches with pagination and search
 */
function handleListCoaches() {
    global $db;
    
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? PAGINATION_PER_PAGE);
    $search = $_GET['q'] ?? '';
    $status = $_GET['status'] ?? 'active'; // Filter by status
    $includeDeleted = $_GET['include_deleted'] ?? 'no';
    
    $where = [];
    $params = [];
    
    // Status filter
    if ($includeDeleted !== 'yes') {
        if ($status === 'all') {
            $where[] = "status != 'inactive' OR deleted_at IS NULL";
        } else {
            $where[] = "status = ?";
            $params[] = $status;
        }
    }
    
    if ($search) {
        $where[] = "(first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM coaches {$whereClause}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Get paginated results
    $pagination = Pagination::calculate($page, $perPage, $total);
    $sql = "SELECT * FROM coaches {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $pagination['per_page'];
    $params[] = $pagination['offset'];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $coaches = $stmt->fetchAll();
    
    // Add time slots and statistics for each coach
    foreach ($coaches as &$coach) {
        $coach = enrichCoachData($coach);
    }
    
    Response::success([
        'coaches' => $coaches,
        'pagination' => $pagination
    ]);
}

/**
 * Get all available time slots
 */
function handleGetTimeSlots() {
    global $db;
    
    $stmt = $db->query("SELECT * FROM time_slots ORDER BY id");
    $timeSlots = $stmt->fetchAll();
    
    Response::success($timeSlots);
}

/**
 * Get coach details with statistics
 */
function handleGetCoach($coachId) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM coaches WHERE id = ?");
    $stmt->execute([$coachId]);
    $coach = $stmt->fetch();
    
    if (!$coach) {
        Response::error('مربی یافت نشد', 404);
    }
    
    $coach = enrichCoachData($coach);
    
    // Get recent contract history
    $stmt = $db->prepare("
        SELECT cch.*, u.username as created_by_name
        FROM coach_contract_history cch
        LEFT JOIN users u ON cch.created_by = u.id
        WHERE cch.coach_id = ?
        ORDER BY cch.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$coachId]);
    $coach['recent_contract_history'] = $stmt->fetchAll();
    
    Response::success($coach);
}

/**
 * Get full contract history for a coach
 */
function handleGetContractHistory($coachId) {
    global $db;
    
    // Verify coach exists
    $stmt = $db->prepare("SELECT id, first_name, last_name FROM coaches WHERE id = ?");
    $stmt->execute([$coachId]);
    $coach = $stmt->fetch();
    
    if (!$coach) {
        Response::error('مربی یافت نشد', 404);
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);
    
    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM coach_contract_history WHERE coach_id = ?");
    $countStmt->execute([$coachId]);
    $total = $countStmt->fetch()['total'];
    
    $pagination = Pagination::calculate($page, $perPage, $total);
    
    $stmt = $db->prepare("
        SELECT cch.*, u.username as created_by_name
        FROM coach_contract_history cch
        LEFT JOIN users u ON cch.created_by = u.id
        WHERE cch.coach_id = ?
        ORDER BY cch.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$coachId, $pagination['per_page'], $pagination['offset']]);
    $history = $stmt->fetchAll();
    
    Response::success([
        'coach' => $coach,
        'history' => $history,
        'pagination' => $pagination
    ]);
}

/**
 * Create new coach
 */
function handleCreateCoach() {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['first_name']) || !isset($data['last_name'])) {
        Response::error('نام و نام خانوادگی الزامی است');
    }
    
    if (!isset($data['time_slot_ids']) || !is_array($data['time_slot_ids']) || empty($data['time_slot_ids'])) {
        Response::error('حداقل یک زمان کلاس الزامی است');
    }
    
    // Validate contract type
    $contractType = $data['contract_type'] ?? 'percentage';
    if (!in_array($contractType, ['percentage', 'salary', 'hybrid'])) {
        Response::error('نوع قرارداد نامعتبر');
    }
    
    // Validate percentage rate
    $percentageRate = floatval($data['percentage_rate'] ?? 50);
    if ($percentageRate < 0 || $percentageRate > 100) {
        Response::error('نرخ درصد باید بین 0 تا 100 باشد');
    }
    
    // Validate monthly salary
    $monthlySalary = floatval($data['monthly_salary'] ?? 0);
    if ($monthlySalary < 0) {
        Response::error('حقوق ماهانه نمی‌تواند منفی باشد');
    }
    
    try {
        $db->beginTransaction();
        
        $jalaliDate = JalaliDate::now();
        
        // Validate fee_calculation_slots
        $feeCalcSlots = $data['fee_calculation_slots'] ?? 'all';
        if (!in_array($feeCalcSlots, ['all', 'morning_evening', 'custom'])) {
            $feeCalcSlots = 'all';
        }
        
        $stmt = $db->prepare("
            INSERT INTO coaches (
                first_name, last_name, phone, photo_path, 
                contract_type, percentage_rate, monthly_salary, fee_calculation_slots,
                contract_start_jalali, contract_end_jalali,
                created_at_jalali, notes, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        $stmt->execute([
            Sanitizer::sanitizeInput($data['first_name']),
            Sanitizer::sanitizeInput($data['last_name']),
            isset($data['phone']) ? Sanitizer::sanitizeInput($data['phone']) : null,
            isset($data['photo_path']) ? Sanitizer::sanitizeInput($data['photo_path']) : null,
            $contractType,
            $percentageRate,
            $monthlySalary,
            $feeCalcSlots,
            isset($data['contract_start_jalali']) ? Sanitizer::sanitizeInput($data['contract_start_jalali']) : $jalaliDate,
            isset($data['contract_end_jalali']) ? Sanitizer::sanitizeInput($data['contract_end_jalali']) : null,
            $jalaliDate,
            isset($data['notes']) ? Sanitizer::sanitizeInput($data['notes']) : null
        ]);
        
        $coachId = $db->lastInsertId();
        
        // Assign time slots
        $stmt = $db->prepare("INSERT INTO coach_time_slot (coach_id, time_slot_id) VALUES (?, ?)");
        foreach ($data['time_slot_ids'] as $timeSlotId) {
            $stmt->execute([$coachId, (int)$timeSlotId]);
        }
        
        // Log initial contract to history
        logContractHistory($coachId, $contractType, $percentageRate, $monthlySalary, 
            $data['contract_start_jalali'] ?? $jalaliDate, 
            $data['contract_end_jalali'] ?? null,
            'Initial contract', $user['id']);
        
        $db->commit();
        
        Audit::log($user['id'], 'create', 'coaches', $coachId, "ایجاد مربی: {$data['first_name']} {$data['last_name']}");
        
        Response::success([
            'id' => $coachId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name']
        ], 'مربی با موفقیت ایجاد شد');
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Coach creation error: " . $e->getMessage());
        Response::error('خطا در ایجاد مربی: ' . $e->getMessage());
    }
}


/**
 * Update coach
 */
function handleUpdateCoach($coachId) {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if coach exists
    $stmt = $db->prepare("SELECT * FROM coaches WHERE id = ?");
    $stmt->execute([$coachId]);
    $existingCoach = $stmt->fetch();
    
    if (!$existingCoach) {
        Response::error('مربی یافت نشد', 404);
    }
    
    // Check if coach is soft-deleted
    if ($existingCoach['status'] === 'inactive' && $existingCoach['deleted_at'] !== null) {
        Response::error('نمی‌توان مربی حذف شده را به‌روزرسانی کرد');
    }
    
    try {
        $db->beginTransaction();
        
        $fields = [];
        $params = [];
        
        // Basic fields
        $allowedFields = ['first_name', 'last_name', 'phone', 'photo_path', 'notes'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = Sanitizer::sanitizeInput($data[$field]);
            }
        }
        
        // Contract fields - check if contract terms changed
        $contractChanged = false;
        $newContractType = $existingCoach['contract_type'];
        $newPercentageRate = $existingCoach['percentage_rate'];
        $newMonthlySalary = $existingCoach['monthly_salary'];
        $newContractStart = $existingCoach['contract_start_jalali'];
        $newContractEnd = $existingCoach['contract_end_jalali'];
        
        if (isset($data['contract_type']) && in_array($data['contract_type'], ['percentage', 'salary', 'hybrid'])) {
            if ($data['contract_type'] !== $existingCoach['contract_type']) {
                $contractChanged = true;
            }
            $newContractType = $data['contract_type'];
            $fields[] = "contract_type = ?";
            $params[] = $data['contract_type'];
        }
        
        if (isset($data['percentage_rate'])) {
            $rate = floatval($data['percentage_rate']);
            if ($rate < 0 || $rate > 100) {
                $db->rollBack();
                Response::error('نرخ درصد باید بین 0 تا 100 باشد');
            }
            if ($rate != floatval($existingCoach['percentage_rate'])) {
                $contractChanged = true;
            }
            $newPercentageRate = $rate;
            $fields[] = "percentage_rate = ?";
            $params[] = $rate;
        }
        
        if (isset($data['monthly_salary'])) {
            $salary = floatval($data['monthly_salary']);
            if ($salary < 0) {
                $db->rollBack();
                Response::error('حقوق ماهانه نمی‌تواند منفی باشد');
            }
            if ($salary != floatval($existingCoach['monthly_salary'])) {
                $contractChanged = true;
            }
            $newMonthlySalary = $salary;
            $fields[] = "monthly_salary = ?";
            $params[] = $salary;
        }
        
        if (isset($data['contract_start_jalali'])) {
            if ($data['contract_start_jalali'] !== $existingCoach['contract_start_jalali']) {
                $contractChanged = true;
            }
            $newContractStart = Sanitizer::sanitizeInput($data['contract_start_jalali']);
            $fields[] = "contract_start_jalali = ?";
            $params[] = $newContractStart;
        }
        
        if (isset($data['contract_end_jalali'])) {
            if ($data['contract_end_jalali'] !== $existingCoach['contract_end_jalali']) {
                $contractChanged = true;
            }
            $newContractEnd = $data['contract_end_jalali'] ? Sanitizer::sanitizeInput($data['contract_end_jalali']) : null;
            $fields[] = "contract_end_jalali = ?";
            $params[] = $newContractEnd;
        }
        
        // Fee calculation slots update
        if (isset($data['fee_calculation_slots'])) {
            $feeCalcSlots = $data['fee_calculation_slots'];
            if (in_array($feeCalcSlots, ['all', 'morning_evening', 'custom'])) {
                $fields[] = "fee_calculation_slots = ?";
                $params[] = $feeCalcSlots;
            }
        }
        
        // Status update
        if (isset($data['status']) && in_array($data['status'], ['active', 'inactive', 'on_leave'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
            
            // If reactivating, clear deleted_at
            if ($data['status'] === 'active' && $existingCoach['deleted_at'] !== null) {
                $fields[] = "deleted_at = NULL";
            }
        }
        
        if (!empty($fields)) {
            $params[] = $coachId;
            $sql = "UPDATE coaches SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
        
        // Update time slots if provided
        if (isset($data['time_slot_ids']) && is_array($data['time_slot_ids'])) {
            // Delete existing
            $stmt = $db->prepare("DELETE FROM coach_time_slot WHERE coach_id = ?");
            $stmt->execute([$coachId]);
            
            // Insert new
            if (!empty($data['time_slot_ids'])) {
                $stmt = $db->prepare("INSERT INTO coach_time_slot (coach_id, time_slot_id) VALUES (?, ?)");
                foreach ($data['time_slot_ids'] as $timeSlotId) {
                    $stmt->execute([$coachId, (int)$timeSlotId]);
                }
            }
        }
        
        // Log contract change to history if terms changed
        if ($contractChanged) {
            $changeNotes = $data['contract_change_notes'] ?? 'Contract terms updated';
            logContractHistory($coachId, $newContractType, $newPercentageRate, $newMonthlySalary,
                $newContractStart, $newContractEnd, $changeNotes, $user['id']);
        }
        
        $db->commit();
        
        Audit::log($user['id'], 'update', 'coaches', $coachId, 'به‌روزرسانی مربی');
        
        Response::success(null, 'مربی با موفقیت به‌روزرسانی شد');
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Coach update error: " . $e->getMessage());
        Response::error('خطا در به‌روزرسانی مربی: ' . $e->getMessage());
    }
}

/**
 * Soft delete coach (marks as inactive with deleted_at timestamp)
 */
function handleDeleteCoach($coachId) {
    global $db, $user;
    
    // Check if coach exists
    $stmt = $db->prepare("SELECT first_name, last_name, status, deleted_at FROM coaches WHERE id = ?");
    $stmt->execute([$coachId]);
    $coach = $stmt->fetch();
    
    if (!$coach) {
        Response::error('مربی یافت نشد', 404);
    }
    
    // Check if already deleted
    if ($coach['status'] === 'inactive' && $coach['deleted_at'] !== null) {
        Response::error('مربی قبلاً حذف شده است');
    }
    
    // Check if coach has active registrations
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM registrations WHERE coach_id = ? AND status = 'active'");
    $stmt->execute([$coachId]);
    $count = $stmt->fetch()['count'];
    
    if ($count > 0) {
        Response::error('نمی‌توان مربی با ثبت‌نام‌های فعال را حذف کرد. لطفاً ابتدا ثبت‌نام‌ها را منتقل یا لغو کنید.');
    }
    
    // Soft delete: set status to inactive and record deletion time
    $stmt = $db->prepare("UPDATE coaches SET status = 'inactive', deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$coachId]);
    
    Audit::log($user['id'], 'delete', 'coaches', $coachId, 
        "حذف نرم مربی: {$coach['first_name']} {$coach['last_name']} (پس از 60 روز به طور دائم حذف خواهد شد)");
    
    Response::success([
        'deleted_at' => date('Y-m-d H:i:s'),
        'permanent_deletion_date' => date('Y-m-d', strtotime('+60 days'))
    ], 'مربی با موفقیت حذف شد. داده‌ها پس از 60 روز به طور دائم حذف خواهند شد.');
}

/**
 * Handle coach photo upload
 */
function handlePhotoUpload() {
    global $user;
    
    if (!isset($_FILES['photo'])) {
        Response::error('هیچ عکسی آپلود نشده است');
    }
    
    $file = $_FILES['photo'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        Response::error('خطا در آپلود فایل: ' . $file['error']);
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        Response::error('فایل بیش از حد بزرگ است. حداکثر اندازه: ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' مگابایت');
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        Response::error('نوع فایل نامعتبر. مجاز: JPEG, PNG, GIF, WebP');
    }
    
    // Upload directory for coach photos
    $uploadDir = UPLOAD_DIR . 'coaches/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $coachId = $_POST['coach_id'] ?? 'new';
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "coach_{$coachId}_" . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        Response::error('خطا در ذخیره فایل');
    }
    
    // Return relative path for database storage
    $relativePath = 'assets/uploads/coaches/' . $filename;
    
    Audit::log($user['id'], 'upload', 'coaches', $coachId !== 'new' ? (int)$coachId : null, "آپلود عکس مربی: {$filename}");
    
    Response::success([
        'file_path' => $relativePath,
        'filename' => $filename,
        'size' => $file['size'],
        'type' => $file['type']
    ], 'Photo uploaded successfully');
}

/**
 * Preview coaches that will be permanently deleted
 */
function handleCleanupPreview() {
    global $db;
    
    $stmt = $db->query("
        SELECT id, first_name, last_name, deleted_at,
               DATEDIFF(NOW(), deleted_at) as days_since_deletion
        FROM coaches 
        WHERE status = 'inactive' 
        AND deleted_at IS NOT NULL 
        AND deleted_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
    ");
    $coaches = $stmt->fetchAll();
    
    Response::success([
        'coaches_to_delete' => $coaches,
        'count' => count($coaches)
    ]);
}

/**
 * Manually trigger cleanup of coaches deleted more than 60 days ago
 */
function handleCleanupDeletedCoaches() {
    global $db, $user;
    
    // Only admin can run cleanup
    if ($user['role'] !== 'admin') {
        Response::error('دسترسی مدیر مورد نیاز است', 403);
    }
    
    try {
        // Get coaches to be deleted for logging
        $stmt = $db->query("
            SELECT id, first_name, last_name 
            FROM coaches 
            WHERE status = 'inactive' 
            AND deleted_at IS NOT NULL 
            AND deleted_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");
        $coachesToDelete = $stmt->fetchAll();
        
        if (empty($coachesToDelete)) {
            Response::success(['deleted_count' => 0], 'هیچ مربی برای پاکسازی وجود ندارد');
        }
        
        // Delete old coach photos
        foreach ($coachesToDelete as $coach) {
            deleteCoachPhoto($coach['id']);
        }
        
        // Permanently delete coaches
        $stmt = $db->prepare("
            DELETE FROM coaches 
            WHERE status = 'inactive' 
            AND deleted_at IS NOT NULL 
            AND deleted_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");
        $stmt->execute();
        $deletedCount = $stmt->rowCount();
        
        // Log the cleanup
        $coachNames = array_map(function($c) {
            return "{$c['first_name']} {$c['last_name']} (ID: {$c['id']})";
        }, $coachesToDelete);
        
        Audit::log($user['id'], 'cleanup', 'coaches', null, 
            "حذف دائم {$deletedCount} مربی: " . implode(', ', $coachNames));
        
        Response::success([
            'deleted_count' => $deletedCount,
            'deleted_coaches' => $coachesToDelete
        ], "Permanently deleted {$deletedCount} coach(es)");
        
    } catch (Exception $e) {
        error_log("Coach cleanup error: " . $e->getMessage());
        Response::error('خطا در پاکسازی مربیان: ' . $e->getMessage());
    }
}


// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Enrich coach data with time slots and statistics
 */
function enrichCoachData($coach) {
    global $db;
    
    // Get time slots
    $stmt = $db->prepare("
        SELECT ts.*
        FROM time_slots ts
        INNER JOIN coach_time_slot cts ON ts.id = cts.time_slot_id
        WHERE cts.coach_id = ?
    ");
    $stmt->execute([$coach['id']]);
    $coach['time_slots'] = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT r.student_id) as student_count,
               COUNT(r.id) as registration_count,
               COALESCE(SUM(r.fee_amount), 0) as total_fees
        FROM registrations r
        WHERE r.coach_id = ? AND r.status = 'active'
    ");
    $stmt->execute([$coach['id']]);
    $coach['statistics'] = $stmt->fetch();
    
    // Calculate days until permanent deletion if soft-deleted
    if ($coach['status'] === 'inactive' && $coach['deleted_at'] !== null) {
        $deletedAt = new DateTime($coach['deleted_at']);
        $now = new DateTime();
        $diff = $now->diff($deletedAt);
        $daysSinceDeletion = $diff->days;
        $daysUntilPermanentDeletion = max(0, 60 - $daysSinceDeletion);
        $coach['days_until_permanent_deletion'] = $daysUntilPermanentDeletion;
    }
    
    // Format contract type for display
    $contractTypeLabels = [
        'percentage' => 'درصدی',
        'salary' => 'حقوق ثابت',
        'hybrid' => 'ترکیبی'
    ];
    $coach['contract_type_label'] = $contractTypeLabels[$coach['contract_type']] ?? $coach['contract_type'];
    
    return $coach;
}

/**
 * Log contract change to history
 */
function logContractHistory($coachId, $contractType, $percentageRate, $monthlySalary, $startDate, $endDate, $notes, $userId) {
    global $db;
    
    $jalaliDate = JalaliDate::now();
    
    $stmt = $db->prepare("
        INSERT INTO coach_contract_history 
        (coach_id, contract_type, percentage_rate, monthly_salary, start_date_jalali, end_date_jalali, notes, created_at_jalali, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $coachId,
        $contractType,
        $percentageRate,
        $monthlySalary,
        $startDate,
        $endDate,
        $notes,
        $jalaliDate,
        $userId
    ]);
}

/**
 * Delete coach photo files
 */
function deleteCoachPhoto($coachId) {
    $uploadDir = UPLOAD_DIR . 'coaches/';
    
    // Find and delete all photos for this coach
    $pattern = $uploadDir . "coach_{$coachId}_*";
    $files = glob($pattern);
    
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

/**
 * Restore a soft-deleted coach (admin only)
 */
function handleRestoreCoach($coachId) {
    global $db, $user;
    
    if ($user['role'] !== 'admin') {
        Response::error('دسترسی مدیر مورد نیاز است', 403);
    }
    
    $stmt = $db->prepare("SELECT * FROM coaches WHERE id = ? AND status = 'inactive' AND deleted_at IS NOT NULL");
    $stmt->execute([$coachId]);
    $coach = $stmt->fetch();
    
    if (!$coach) {
        Response::error('مربی حذف شده یافت نشد', 404);
    }
    
    $stmt = $db->prepare("UPDATE coaches SET status = 'active', deleted_at = NULL WHERE id = ?");
    $stmt->execute([$coachId]);
    
    Audit::log($user['id'], 'restore', 'coaches', $coachId, 
        "بازیابی مربی: {$coach['first_name']} {$coach['last_name']}");
    
    Response::success(null, 'مربی با موفقیت بازیابی شد');
}
