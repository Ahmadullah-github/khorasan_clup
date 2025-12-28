<?php
/**
 * Settings API
 * Handles app settings storage and retrieval
 * Used by setup wizard and settings management
 */

require_once 'config.php';

// Get database connection
$db = Database::getInstance()->getConnection();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Route requests
switch ($method) {
    case 'GET':
        handleGet($action, $db);
        break;
    case 'POST':
        handlePost($action, $db);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

/**
 * Handle GET requests
 */
function handleGet($action, $db) {
    switch ($action) {
        case 'all':
            getAllSettings($db);
            break;
        case 'check-setup':
            checkSetupStatus($db);
            break;
        case 'categories':
            getCategories($db);
            break;
        default:
            getSetting($db, $action);
    }
}

/**
 * Handle POST requests
 */
function handlePost($action, $db) {
    Session::requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'setup':
            saveSetupWizard($db, $input);
            break;
        case 'update':
            updateSetting($db, $input);
            break;
        case 'categories':
            updateCategories($db, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

/**
 * Check if initial setup has been completed
 */
function checkSetupStatus($db) {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'setup_complete'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        $isComplete = $result && $result['setting_value'] === 'true';
        
        echo json_encode([
            'success' => true,
            'data' => [
                'setup_complete' => $isComplete
            ]
        ]);
    } catch (PDOException $e) {
        // Table might not exist yet
        echo json_encode([
            'success' => true,
            'data' => [
                'setup_complete' => false
            ]
        ]);
    }
}

/**
 * Get all settings
 */
function getAllSettings($db) {
    try {
        $stmt = $db->prepare("SELECT setting_key, setting_value, setting_group FROM app_settings ORDER BY setting_group, setting_key");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        
        // Group settings
        $settings = [];
        foreach ($rows as $row) {
            $group = $row['setting_group'] ?: 'general';
            if (!isset($settings[$group])) {
                $settings[$group] = [];
            }
            
            // Try to decode JSON values
            $value = $row['setting_value'];
            $decoded = json_decode($value, true);
            $settings[$group][$row['setting_key']] = $decoded !== null ? $decoded : $value;
        }
        
        echo json_encode(['success' => true, 'data' => $settings]);
    } catch (PDOException $e) {
        error_log("Get settings error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'خطا در دریافت تنظیمات']);
    }
}

/**
 * Get a specific setting
 */
function getSetting($db, $key) {
    if (empty($key)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Setting key required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        if ($result) {
            $value = $result['setting_value'];
            $decoded = json_decode($value, true);
            echo json_encode([
                'success' => true,
                'data' => $decoded !== null ? $decoded : $value
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Setting not found']);
        }
    } catch (PDOException $e) {
        error_log("Get setting error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'خطا در دریافت تنظیم']);
    }
}

/**
 * Get expense categories
 */
function getCategories($db) {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'expense_categories'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result) {
            $categories = json_decode($result['setting_value'], true);
            echo json_encode(['success' => true, 'data' => $categories]);
        } else {
            // Return defaults
            $defaults = [
                ['key' => 'Rent', 'label' => 'اجاره'],
                ['key' => 'Equipment', 'label' => 'تجهیزات'],
                ['key' => 'Taxes', 'label' => 'مالیات'],
                ['key' => 'Services', 'label' => 'خدمات'],
                ['key' => 'Other', 'label' => 'سایر']
            ];
            echo json_encode(['success' => true, 'data' => $defaults]);
        }
    } catch (PDOException $e) {
        error_log("Get categories error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'خطا در دریافت دسته‌بندی‌ها']);
    }
}

/**
 * Save setup wizard data
 */
function saveSetupWizard($db, $data) {
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No data provided']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Ensure settings table exists
        createSettingsTableIfNotExists($db);
        
        // Save organization settings
        if (isset($data['organization'])) {
            $org = $data['organization'];
            saveSetting($db, 'org_name_fa', $org['name_fa'] ?? '', 'organization');
            saveSetting($db, 'org_name_en', $org['name_en'] ?? '', 'organization');
            saveSetting($db, 'org_slogan', $org['slogan'] ?? '', 'organization');
            saveSetting($db, 'org_phone', $org['phone'] ?? '', 'organization');
            saveSetting($db, 'org_city', $org['city'] ?? '', 'organization');
            saveSetting($db, 'org_address', $org['address'] ?? '', 'organization');
        }
        
        // Save manager settings
        if (isset($data['manager'])) {
            $mgr = $data['manager'];
            saveSetting($db, 'manager_name_fa', $mgr['name_fa'] ?? '', 'manager');
            saveSetting($db, 'manager_name_en', $mgr['name_en'] ?? '', 'manager');
            saveSetting($db, 'manager_title', $mgr['title'] ?? '', 'manager');
            saveSetting($db, 'manager_phone', $mgr['phone'] ?? '', 'manager');
            saveSetting($db, 'manager_email', $mgr['email'] ?? '', 'manager');
        }
        
        // Save financial settings
        if (isset($data['financial'])) {
            $fin = $data['financial'];
            saveSetting($db, 'currency', $fin['currency'] ?? 'AFN', 'financial');
            saveSetting($db, 'currency_label', $fin['currency_label'] ?? 'افغانی', 'financial');
            saveSetting($db, 'default_percentage', $fin['default_percentage'] ?? 50, 'financial');
            saveSetting($db, 'default_hybrid_percentage', $fin['default_hybrid_percentage'] ?? 25, 'financial');
            saveSetting($db, 'fiscal_year_start', $fin['fiscal_year_start'] ?? 1404, 'financial');
        }
        
        // Save categories
        if (isset($data['categories'])) {
            saveSetting($db, 'expense_categories', json_encode($data['categories']), 'categories');
        }
        
        // Mark setup as complete
        saveSetting($db, 'setup_complete', 'true', 'system');
        saveSetting($db, 'setup_date', date('Y-m-d H:i:s'), 'system');
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'تنظیمات با موفقیت ذخیره شد'
        ]);
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Save setup error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'خطا در ذخیره تنظیمات: ' . $e->getMessage()]);
    }
}

/**
 * Update a single setting
 */
function updateSetting($db, $data) {
    if (!isset($data['key']) || !isset($data['value'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Key and value required']);
        return;
    }
    
    try {
        $value = is_array($data['value']) ? json_encode($data['value']) : $data['value'];
        $group = $data['group'] ?? 'general';
        
        saveSetting($db, $data['key'], $value, $group);
        
        echo json_encode(['success' => true, 'message' => 'تنظیم ذخیره شد']);
    } catch (PDOException $e) {
        error_log("Update setting error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'خطا در ذخیره تنظیم']);
    }
}

/**
 * Update expense categories
 */
function updateCategories($db, $data) {
    if (!isset($data['categories']) || !is_array($data['categories'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Categories array required']);
        return;
    }
    
    // Validate categories
    foreach ($data['categories'] as $cat) {
        if (!isset($cat['key']) || !isset($cat['label'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Each category must have key and label']);
            return;
        }
        
        // Validate key format
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $cat['key'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid category key format: ' . $cat['key']]);
            return;
        }
    }
    
    try {
        saveSetting($db, 'expense_categories', json_encode($data['categories']), 'categories');
        echo json_encode(['success' => true, 'message' => 'دسته‌بندی‌ها ذخیره شد']);
    } catch (PDOException $e) {
        error_log("Update categories error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'خطا در ذخیره دسته‌بندی‌ها']);
    }
}

/**
 * Helper: Save a setting (insert or update)
 */
function saveSetting($db, $key, $value, $group = 'general') {
    $stmt = $db->prepare("
        INSERT INTO app_settings (setting_key, setting_value, setting_group, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            setting_group = VALUES(setting_group),
            updated_at = NOW()
    ");
    $stmt->execute([$key, $value, $group]);
}

/**
 * Helper: Create settings table if it doesn't exist
 */
function createSettingsTableIfNotExists($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_group VARCHAR(50) DEFAULT 'general',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_group (setting_group),
            INDEX idx_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
