<?php
/**
 * Sports Camp Management System - Utility Functions
 * Jalali date helpers, validation, sanitization, audit logging
 */

require_once __DIR__ . '/config.php';

// Jalali Date Utilities
// Using simple conversion functions (can be replaced with Morilog\Jalali library)

class JalaliDate {
    // Jalali month names in Dari
    private static $monthNames = [
        1 => 'حمل', 2 => 'ثور', 3 => 'جوزا', 4 => 'سرطان',
        5 => 'اسد', 6 => 'سنبله', 7 => 'میزان', 8 => 'عقرب',
        9 => 'قوس', 10 => 'جدی', 11 => 'دلو', 12 => 'حوت'
    ];

    /**
     * Convert Gregorian to Jalali
     * @param int $gYear Gregorian year
     * @param int $gMonth Gregorian month
     * @param int $gDay Gregorian day
     * @return array [year, month, day]
     */
    public static function gregorianToJalali($gYear, $gMonth, $gDay) {
        $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        $gy = $gYear - 1600;
        $gm = $gMonth - 1;
        $gd = $gDay - 1;
        
        $gDayNo = 365 * $gy + (int)(($gy + 3) / 4) - (int)(($gy + 99) / 100) + 
                  (int)(($gy + 399) / 400) - 80 + $gd;
        
        for ($i = 0; $i < $gm; ++$i) {
            $gDayNo += $gDaysInMonth[$i];
        }
        if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
            $gDayNo++;
        }
        
        $jDayNo = $gDayNo - 79;
        $jNp = (int)($jDayNo / 12053);
        $jDayNo = $jDayNo % 12053;
        $jy = 979 + 33 * $jNp + 4 * (int)($jDayNo / 1461);
        $jDayNo %= 1461;
        
        if ($jDayNo >= 366) {
            $jy += (int)(($jDayNo - 1) / 365);
            $jDayNo = ($jDayNo - 1) % 365;
        }
        
        for ($i = 0; $i < 11 && $jDayNo >= $jDaysInMonth[$i]; ++$i) {
            $jDayNo -= $jDaysInMonth[$i];
        }
        $jm = $i + 1;
        $jd = $jDayNo + 1;
        
        return [$jy, $jm, $jd];
    }

    /**
     * Convert Jalali to Gregorian
     * @param int $jYear Jalali year
     * @param int $jMonth Jalali month
     * @param int $jDay Jalali day
     * @return array [year, month, day]
     */
    public static function jalaliToGregorian($jYear, $jMonth, $jDay) {
        $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        $jy = $jYear - 979;
        $jm = $jMonth - 1;
        $jd = $jDay - 1;
        
        $jDayNo = 365 * $jy + (int)($jy / 33) * 8 + (int)(($jy % 33 + 3) / 4) + 78 + $jd;
        
        for ($i = 0; $i < $jm; ++$i) {
            $jDayNo += $jDaysInMonth[$i];
        }
        
        $gDayNo = $jDayNo + 79;
        $gy = 1600 + 400 * (int)($gDayNo / 146097);
        $gDayNo = $gDayNo % 146097;
        
        $leap = 1;
        if ($gDayNo >= 36525) {
            $gDayNo--;
            $gy += 100 * (int)($gDayNo / 36524);
            $gDayNo = $gDayNo % 36524;
            if ($gDayNo >= 365) {
                $gDayNo++;
            } else {
                $leap = 0;
            }
        }
        
        $gy += 4 * (int)($gDayNo / 1461);
        $gDayNo %= 1461;
        
        if ($gDayNo >= 366) {
            $leap = 0;
            $gDayNo--;
            $gy += (int)($gDayNo / 365);
            $gDayNo = $gDayNo % 365;
        }
        
        for ($i = 0; $gDayNo >= $gDaysInMonth[$i] + ($i == 1 && $leap); $i++) {
            $gDayNo -= $gDaysInMonth[$i] + ($i == 1 && $leap);
        }
        $gm = $i + 1;
        $gd = $gDayNo + 1;
        
        return [$gy, $gm, $gd];
    }

    /**
     * Get current Jalali date as string (YYYY-MM-DD)
     */
    public static function now() {
        $now = new DateTime();
        list($jYear, $jMonth, $jDay) = self::gregorianToJalali(
            (int)$now->format('Y'),
            (int)$now->format('m'),
            (int)$now->format('d')
        );
        return sprintf('%04d-%02d-%02d', $jYear, $jMonth, $jDay);
    }

    /**
     * Format Jalali date for display
     * @param string $jalaliDate YYYY-MM-DD format
     * @return string Formatted date (e.g., "15 Hamal 1403")
     */
    public static function format($jalaliDate) {
        if (empty($jalaliDate) || $jalaliDate === '0000-00-00') {
            return '';
        }
        
        $parts = explode('-', $jalaliDate);
        if (count($parts) !== 3) {
            return $jalaliDate;
        }
        
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $day = (int)$parts[2];
        
        $monthName = isset(self::$monthNames[$month]) ? self::$monthNames[$month] : $month;
        
        return sprintf('%d %s %d', $day, $monthName, $year);
    }

    /**
     * Validate Jalali date string
     * @param string $date YYYY-MM-DD format
     * @return bool
     */
    public static function validate($date) {
        if (empty($date)) {
            return false;
        }
        
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return false;
        }
        
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $day = (int)$parts[2];
        
        if ($year < 1300 || $year > 1500) {
            return false;
        }
        if ($month < 1 || $month > 12) {
            return false;
        }
        if ($day < 1 || $day > 31) {
            return false;
        }
        
        // Basic day validation per month
        $daysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        if ($day > $daysInMonth[$month - 1]) {
            // Check for leap year in last month
            if ($month === 12 && $day === 30) {
                // Simple leap year check (not perfect but good enough)
                $leap = (($year + 2346) % 128) < 30;
                if (!$leap) {
                    return false;
                }
            } else {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get month name in Dari
     */
    public static function getMonthName($month) {
        return isset(self::$monthNames[$month]) ? self::$monthNames[$month] : '';
    }
}

// Input Sanitization
class Sanitizer {
    /**
     * Sanitize string input
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize for database (prepared statements handle this, but extra safety)
     */
    public static function sanitizeForDB($input) {
        return filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    }

    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number
     */
    public static function validatePhone($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return strlen($phone) >= 8 && strlen($phone) <= 15;
    }

    /**
     * Validate decimal amount
     */
    public static function validateAmount($amount) {
        return filter_var($amount, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]) !== false;
    }
}

// Password Utilities
class Password {
    /**
     * Hash password
     */
    public static function hash($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verify password
     */
    public static function verify($password, $hash) {
        return password_verify($password, $hash);
    }
}

// Audit Logging
class Audit {
    /**
     * Log an action
     * @param int $userId User ID
     * @param string $action Action type (create, update, delete, login, etc.)
     * @param string $tableName Table name
     * @param int|null $recordId Record ID
     * @param string|null $details Additional details
     */
    public static function log($userId, $action, $tableName, $recordId = null, $details = null) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, timestamp_jalali, details, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $jalaliDate = JalaliDate::now();
            
            $stmt->execute([
                $userId,
                $action,
                $tableName,
                $recordId,
                $jalaliDate,
                $details,
                $ipAddress
            ]);
        } catch (PDOException $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
}

// Response Helpers
class Response {
    /**
     * Send JSON response
     */
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        // Ensure UTF-8 encoding for JSON
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send error response
     */
    public static function error($message, $statusCode = 400) {
        self::json(['error' => $message], $statusCode);
    }

    /**
     * Send success response
     */
    public static function success($data = null, $message = null) {
        $response = ['success' => true];
        if ($message) {
            $response['message'] = $message;
        }
        if ($data !== null) {
            $response['data'] = $data;
        }
        self::json($response);
    }
}

// Pagination Helper
class Pagination {
    /**
     * Calculate pagination info
     */
    public static function calculate($page, $perPage, $total) {
        $page = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $total = max(0, (int)$total);
        
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'offset' => $offset,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ];
    }
}


