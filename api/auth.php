<?php
/**
 * Sports Camp Management System - Authentication API
 * Handles login, logout, user management, lockout mechanism
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$db = Database::getInstance()->getConnection();

// Route handling
switch ($method) {
    case 'POST':
        if ($action === 'login') {
            handleLogin();
        } elseif ($action === 'logout') {
            handleLogout();
        } elseif ($action === 'users') {
            Session::requireAdmin();
            handleCreateUser();
        } else {
            Response::error('عملیات نامعتبر', 400);
        }
        break;
        
    case 'GET':
        if ($action === 'check') {
            handleCheckAuth();
        } elseif ($action === 'users') {
            Session::requireAdmin();
            handleListUsers();
        } else {
            Response::error('عملیات نامعتبر', 400);
        }
        break;
        
    case 'DELETE':
        if ($action === 'users') {
            Session::requireAdmin();
            handleDeleteUser();
        } else {
            Response::error('عملیات نامعتبر', 400);
        }
        break;
        
    default:
        Response::error('روش مجاز نیست', 405);
}

/**
 * Handle user login
 */
function handleLogin() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        Response::error('نام کاربری و رمز عبور الزامی است');
    }
    
    $username = Sanitizer::sanitizeInput($data['username']);
    $password = $data['password'];
    
    // Check if user is locked
    $stmt = $db->prepare("
        SELECT id, username, password_hash, role, failed_attempts, locked_until
        FROM users
        WHERE username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        Response::error('نام کاربری یا رمز عبور نادرست', 401);
    }
    
    // Check lockout
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
        Response::error("حساب قفل شده. {$remaining} دقیقه دیگر تلاش کنید.", 423);
    }
    
    // Verify password
    if (!Password::verify($password, $user['password_hash'])) {
        // Increment failed attempts
        $failedAttempts = $user['failed_attempts'] + 1;
        $lockedUntil = null;
        
        if ($failedAttempts >= MAX_LOGIN_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
        }
        
        $stmt = $db->prepare("
            UPDATE users
            SET failed_attempts = ?, locked_until = ?
            WHERE id = ?
        ");
        $stmt->execute([$failedAttempts, $lockedUntil, $user['id']]);
        
        $remaining = MAX_LOGIN_ATTEMPTS - $failedAttempts;
        if ($remaining <= 0) {
            Response::error("حساب برای " . (LOCKOUT_DURATION / 60) . " دقیقه قفل شد.", 423);
        }
        
        Response::error("نام کاربری یا رمز عبور نادرست. {$remaining} تلاش باقی مانده.", 401);
    }
    
    // Successful login - reset failed attempts
    $jalaliDate = JalaliDate::now();
    $stmt = $db->prepare("
        UPDATE users
        SET failed_attempts = 0, locked_until = NULL, last_login_jalali = ?
        WHERE id = ?
    ");
    $stmt->execute([$jalaliDate, $user['id']]);
    
    // Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    
    // Log login
    Audit::log($user['id'], 'login', 'users', $user['id'], 'کاربر وارد شد');
    
    Response::success([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]
    ], 'Login successful');
}

/**
 * Handle logout
 */
function handleLogout() {
    $user = Session::getUser();
    if ($user) {
        Audit::log($user['id'], 'logout', 'users', $user['id'], 'کاربر خارج شد');
    }
    Session::destroy();
    Response::success(null, 'با موفقیت خارج شدید');
}

/**
 * Check authentication status
 */
function handleCheckAuth() {
    if (!Session::isLoggedIn()) {
        Response::error('احراز هویت نشده', 401);
    }
    
    $user = Session::getUser();
    Response::success(['user' => $user]);
}

/**
 * Create new user (admin only)
 */
function handleCreateUser() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['password']) || !isset($data['role'])) {
        Response::error('نام کاربری، رمز عبور و نقش الزامی است');
    }
    
    $username = Sanitizer::sanitizeInput($data['username']);
    $password = $data['password'];
    $role = in_array($data['role'], ['admin', 'staff']) ? $data['role'] : 'staff';
    
    // Validate password strength
    if (strlen($password) < 6) {
        Response::error('رمز عبور باید حداقل 6 کاراکتر باشد');
    }
    
    // Check if username exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        Response::error('نام کاربری قبلاً وجود دارد');
    }
    
    // Create user
    $passwordHash = Password::hash($password);
    $jalaliDate = JalaliDate::now();
    
    $stmt = $db->prepare("
        INSERT INTO users (username, password_hash, role, created_at_jalali)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$username, $passwordHash, $role, $jalaliDate]);
    
    $userId = $db->lastInsertId();
    
    $user = Session::getUser();
    Audit::log($user['id'], 'create', 'users', $userId, "ایجاد کاربر: {$username}");
    
    Response::success([
        'id' => $userId,
        'username' => $username,
        'role' => $role
    ], 'User created successfully');
}

/**
 * List all users (admin only)
 */
function handleListUsers() {
    global $db;
    
    $stmt = $db->query("
        SELECT id, username, role, created_at_jalali, last_login_jalali
        FROM users
        ORDER BY created_at_jalali DESC
    ");
    $users = $stmt->fetchAll();
    
    Response::success($users);
}

/**
 * Delete user (admin only)
 */
function handleDeleteUser() {
    global $db;
    
    try {
        $userId = $_GET['id'] ?? null;
        if (!$userId) {
            Response::error('شناسه کاربر الزامی است');
        }
        
        $userId = (int)$userId;
        $user = Session::getUser();
        
        if (!$user) {
            Response::error('جلسه کاربر یافت نشد', 401);
        }
        
        // Prevent self-deletion
        if ($userId === $user['id']) {
            Response::error('نمی‌توانید حساب خود را حذف کنید');
        }
        
        // Check if user exists
        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch();
        
        if (!$targetUser) {
            Response::error('کاربر یافت نشد');
        }
        
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Delete audit logs for this user first (to avoid foreign key constraint)
            $stmt = $db->prepare("DELETE FROM audit_logs WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Also check for expenses created by this user
            // We need to handle this - either delete expenses or reassign them
            // For now, check if user has created expenses
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM expenses WHERE created_by = ?");
            $stmt->execute([$userId]);
            $expenseCount = $stmt->fetch()['count'];
            
            if ($expenseCount > 0) {
                // Reassign expenses to the current admin user
                $stmt = $db->prepare("UPDATE expenses SET created_by = ? WHERE created_by = ?");
                $stmt->execute([$user['id'], $userId]);
            }
            
            // Now delete the user
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            // Commit transaction
            $db->commit();
            
            // Log the deletion (after successful deletion)
            try {
                Audit::log($user['id'], 'delete', 'users', $userId, "حذف کاربر: {$targetUser['username']}");
            } catch (Exception $e) {
                // Log error but don't fail - user is already deleted
                error_log("Audit log error: " . $e->getMessage());
            }
            
            Response::success(null, 'کاربر با موفقیت حذف شد');
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        error_log("Delete user error: " . $e->getMessage());
        Response::error('خطا در حذف کاربر: ' . $e->getMessage());
    }
}

