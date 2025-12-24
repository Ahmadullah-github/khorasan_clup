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
        
        // Validate format with regex for better strictness
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return false;
        }
        
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
        
        // Validate year range
        if ($year < 1300 || $year > 1500) {
            return false;
        }
        
        // Validate month
        if ($month < 1 || $month > 12) {
            return false;
        }
        
        // Validate day using centralized getDaysInMonth (handles leap years correctly)
        $maxDays = self::getDaysInMonth($month, $year);
        if ($day < 1 || $day > $maxDays) {
            return false;
        }
        
        return true;
    }

    /**
     * Get month name in Dari
     */
    public static function getMonthName($month) {
        return isset(self::$monthNames[$month]) ? self::$monthNames[$month] : '';
    }

    /**
     * Check if a Jalali year is a leap year
     * Uses the 33-year cycle algorithm (most accurate for Jalali calendar)
     * 
     * @param int $year The Jalali year to check
     * @return bool True if the year is a leap year
     */
    public static function isLeapYear($year) {
        // The 33-year cycle has 8 leap years at positions: 1, 5, 9, 13, 17, 22, 26, 30
        $leapYearsInCycle = [1, 5, 9, 13, 17, 22, 26, 30];
        $yearInCycle = $year % 33;
        return in_array($yearInCycle, $leapYearsInCycle);
    }

    /**
     * Get the number of days in a Jalali month
     * 
     * @param int $month The month (1-12)
     * @param int $year The Jalali year (required for month 12 leap year check)
     * @return int Number of days in the month
     */
    public static function getDaysInMonth($month, $year) {
        // Months 1-6 (Hamal to Sonbola) have 31 days
        if ($month >= 1 && $month <= 6) {
            return 31;
        }
        // Months 7-11 (Mizan to Dalv) have 30 days
        if ($month >= 7 && $month <= 11) {
            return 30;
        }
        // Month 12 (Hoot/Esfand) has 29 days, or 30 in leap years
        if ($month == 12) {
            return self::isLeapYear($year) ? 30 : 29;
        }
        // Fallback for invalid month
        return 30;
    }

    /**
     * Get the last day of a Jalali month as a formatted date string
     * 
     * @param int $year The Jalali year
     * @param int $month The month (1-12)
     * @return string Date in YYYY-MM-DD format
     */
    public static function getMonthEndDate($year, $month) {
        $lastDay = self::getDaysInMonth($month, $year);
        return sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
    }

    /**
     * Get the first day of a Jalali month as a formatted date string
     * 
     * @param int $year The Jalali year
     * @param int $month The month (1-12)
     * @return string Date in YYYY-MM-DD format
     */
    public static function getMonthStartDate($year, $month) {
        return sprintf('%04d-%02d-01', $year, $month);
    }

    /**
     * Get the date range for a Jalali month
     * 
     * @param int $year The Jalali year
     * @param int $month The month (1-12)
     * @return array ['start' => 'YYYY-MM-DD', 'end' => 'YYYY-MM-DD']
     */
    public static function getMonthDateRange($year, $month) {
        return [
            'start' => self::getMonthStartDate($year, $month),
            'end' => self::getMonthEndDate($year, $month)
        ];
    }

    /**
     * Calculate the next month and year
     * 
     * @param int $year Current Jalali year
     * @param int $month Current month (1-12)
     * @return array ['year' => int, 'month' => int]
     */
    public static function getNextMonth($year, $month) {
        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        return ['year' => $nextYear, 'month' => $nextMonth];
    }
    
    /**
     * Parse a Jalali date string
     * 
     * @param string $dateString Date in format YYYY-MM-DD
     * @return array|null ['year' => int, 'month' => int, 'day' => int] or null if invalid
     */
    public static function parse($dateString) {
        if (!self::validate($dateString)) {
            return null;
        }
        
        $parts = explode('-', $dateString);
        return [
            'year' => (int)$parts[0],
            'month' => (int)$parts[1],
            'day' => (int)$parts[2]
        ];
    }
    
    /**
     * Compare two Jalali dates
     * 
     * @param string $date1 First date (YYYY-MM-DD)
     * @param string $date2 Second date (YYYY-MM-DD)
     * @return int -1 if date1 < date2, 0 if equal, 1 if date1 > date2
     */
    public static function compare($date1, $date2) {
        // Simple string comparison works for YYYY-MM-DD format
        return strcmp($date1, $date2) <=> 0;
    }
    
    /**
     * Check if a date is within a range
     * 
     * @param string $date Date to check (YYYY-MM-DD)
     * @param string $startDate Range start (YYYY-MM-DD)
     * @param string $endDate Range end (YYYY-MM-DD)
     * @return bool True if date is within range (inclusive)
     */
    public static function isInRange($date, $startDate, $endDate) {
        return $date >= $startDate && $date <= $endDate;
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

// Money/Currency Calculation Helper
class Money {
    /**
     * Round a monetary amount to avoid floating-point precision issues
     * Uses banker's rounding (round half to even) for financial accuracy
     * 
     * @param float $amount The amount to round
     * @param int $precision Number of decimal places (default 2 for currency)
     * @return float Rounded amount
     */
    public static function round($amount, $precision = 2) {
        // Use PHP_ROUND_HALF_EVEN (banker's rounding) for financial calculations
        return round($amount, $precision, PHP_ROUND_HALF_EVEN);
    }
    
    /**
     * Calculate percentage of an amount with proper rounding
     * 
     * @param float $amount Base amount
     * @param float $percentage Percentage rate (0-100)
     * @return float Calculated amount, rounded to 2 decimal places
     */
    public static function percentage($amount, $percentage) {
        $result = (float)$amount * ((float)$percentage / 100);
        return self::round($result);
    }
    
    /**
     * Safely add monetary amounts
     * 
     * @param float ...$amounts Variable number of amounts to add
     * @return float Sum, rounded to 2 decimal places
     */
    public static function add(...$amounts) {
        $sum = array_reduce($amounts, function($carry, $amount) {
            return $carry + (float)$amount;
        }, 0.0);
        return self::round($sum);
    }
    
    /**
     * Safely subtract monetary amounts
     * 
     * @param float $from Base amount
     * @param float ...$amounts Amounts to subtract
     * @return float Difference, rounded to 2 decimal places
     */
    public static function subtract($from, ...$amounts) {
        $result = (float)$from;
        foreach ($amounts as $amount) {
            $result -= (float)$amount;
        }
        return self::round($result);
    }
    
    /**
     * Compare two monetary amounts for equality (within tolerance)
     * 
     * @param float $a First amount
     * @param float $b Second amount
     * @param float $tolerance Tolerance for comparison (default 0.01)
     * @return bool True if amounts are equal within tolerance
     */
    public static function equals($a, $b, $tolerance = 0.01) {
        return abs((float)$a - (float)$b) < $tolerance;
    }
    
    /**
     * Format amount for display (no currency symbol)
     * 
     * @param float $amount Amount to format
     * @param int $decimals Number of decimal places
     * @return string Formatted number
     */
    public static function format($amount, $decimals = 0) {
        return number_format(self::round($amount), $decimals);
    }
}

// Time Slot Detection Helper
class TimeSlotDetector {
    // English keywords for morning/evening slots
    private static $englishKeywords = ['morning', 'evening'];
    
    // Dari/Persian keywords for morning/evening slots
    // صبح (morning), صبحانه (breakfast/morning time)
    // شب (night), شام (evening), عصر (afternoon/evening)
    private static $dariKeywords = ['صبح', 'صبحانه', 'شب', 'شام', 'عصر'];
    
    /**
     * Check if a time slot name indicates morning or evening
     * Supports both English and Dari/Persian keywords
     * 
     * @param string $slotName The time slot name to check
     * @return bool True if the slot is morning or evening
     */
    public static function isMorningEvening($slotName) {
        // Use mb_strtolower for proper Unicode handling
        $slotLower = mb_strtolower($slotName, 'UTF-8');
        
        // Check English keywords
        foreach (self::$englishKeywords as $keyword) {
            if (strpos($slotLower, $keyword) !== false) {
                return true;
            }
        }
        
        // Check Dari/Persian keywords (no need for lowercase conversion for these)
        foreach (self::$dariKeywords as $keyword) {
            if (mb_strpos($slotName, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the SQL CASE expression for morning/evening detection
     * This ensures SQL and PHP logic are consistent
     * 
     * @param string $columnName The column name to check (e.g., 'ts.name')
     * @param string $valueColumn The column to return when matched (e.g., 'r.fee_amount')
     * @return string SQL CASE expression
     */
    public static function getSqlCaseExpression($columnName, $valueColumn) {
        // Combine all keywords for SQL LIKE patterns
        $patterns = [];
        
        // English keywords (case-insensitive with LOWER)
        foreach (self::$englishKeywords as $keyword) {
            $patterns[] = "LOWER({$columnName}) LIKE '%" . strtolower($keyword) . "%'";
        }
        
        // Dari/Persian keywords (direct matching for Unicode)
        foreach (self::$dariKeywords as $keyword) {
            $patterns[] = "{$columnName} LIKE '%{$keyword}%'";
        }
        
        $condition = implode(' OR ', $patterns);
        return "CASE WHEN {$condition} THEN {$valueColumn} ELSE 0 END";
    }
    
    /**
     * Get all keywords used for detection
     * 
     * @return array ['english' => [...], 'dari' => [...]]
     */
    public static function getKeywords() {
        return [
            'english' => self::$englishKeywords,
            'dari' => self::$dariKeywords
        ];
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


