<?php
/**
 * Sports Camp Management System - Accounting API
 * Handles net income calculation and coach payout breakdown
 * Algorithm: 50% of net_income split equally among morning+evening coaches,
 * remaining 50% distributed proportionally by fees collected
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

Session::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        if ($action === 'net-income') {
            handleGetNetIncome();
        } elseif ($action === 'payouts') {
            handleGetPayouts();
        } elseif ($action === 'breakdown') {
            handleGetBreakdown();
        } else {
            Response::error('Invalid action');
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}

/**
 * Calculate net income for a given month/year
 */
function handleGetNetIncome() {
    global $db;
    
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    
    if (!$month || !$year) {
        Response::error('Month and year required');
    }
    
    // Calculate total income (fees from registrations in this month)
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = sprintf('%04d-%02d-%02d', $year, $month, getJalaliDaysInMonth($month, $year));
    
    $stmt = $db->prepare("
        SELECT SUM(r.fee_amount) as total_income,
               COUNT(r.id) as registration_count
        FROM registrations r
        WHERE r.registration_date_jalali >= ? 
          AND r.registration_date_jalali <= ?
          AND r.status = 'active'
    ");
    $stmt->execute([$startDate, $endDate]);
    $income = $stmt->fetch();
    $totalIncome = (float)($income['total_income'] ?? 0);
    
    // Calculate total expenses (including rent)
    $stmt = $db->prepare("
        SELECT SUM(amount) as total_expenses,
               COUNT(id) as expense_count
        FROM expenses
        WHERE expense_date_jalali >= ? 
          AND expense_date_jalali <= ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $expenses = $stmt->fetch();
    $totalExpenses = (float)($expenses['total_expenses'] ?? 0);
    
    $netIncome = $totalIncome - $totalExpenses;
    
    Response::success([
        'month' => $month,
        'year' => $year,
        'month_name' => JalaliDate::getMonthName($month),
        'total_income' => $totalIncome,
        'total_expenses' => $totalExpenses,
        'net_income' => $netIncome,
        'income_count' => (int)$income['registration_count'],
        'expense_count' => (int)$expenses['expense_count']
    ]);
}

/**
 * Check if a time slot name indicates morning or evening
 * Supports both English and Dari/Persian keywords
 * 
 * @param string $slotName The time slot name to check
 * @return bool True if the slot is morning or evening
 */
function isMorningEveningSlot($slotName) {
    // Use mb_strtolower for proper Unicode handling
    $slotLower = mb_strtolower($slotName, 'UTF-8');
    
    // English keywords
    $englishKeywords = ['morning', 'evening'];
    
    // Dari/Persian keywords for morning: صبح (morning), صبحانه (morning time)
    // Dari/Persian keywords for evening: شب (night), شام (evening), عصر (afternoon/evening)
    $dariKeywords = ['صبح', 'صبحانه', 'شب', 'شام', 'عصر'];
    
    // Check English keywords
    foreach ($englishKeywords as $keyword) {
        if (strpos($slotLower, $keyword) !== false) {
            return true;
        }
    }
    
    // Check Dari/Persian keywords (no need for lowercase conversion for these)
    foreach ($dariKeywords as $keyword) {
        if (mb_strpos($slotName, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if a Jalali year is a leap year
 * Uses the 33-year cycle approximation
 * 
 * @param int $year The Jalali year to check
 * @return bool True if the year is a leap year
 */
function isJalaliLeapYear($year) {
    $leapYearsInCycle = [1, 5, 9, 13, 17, 22, 26, 30];
    $yearInCycle = $year % 33;
    return in_array($yearInCycle, $leapYearsInCycle);
}

/**
 * Get the number of days in a Jalali month
 * 
 * @param int $month The month (1-12)
 * @param int $year The Jalali year
 * @return int Number of days in the month
 */
function getJalaliDaysInMonth($month, $year) {
    // Months 1-6 have 31 days
    if ($month >= 1 && $month <= 6) {
        return 31;
    }
    // Months 7-11 have 30 days
    if ($month >= 7 && $month <= 11) {
        return 30;
    }
    // Month 12 (Esfand/حوت) has 29 days, or 30 in leap years
    if ($month == 12) {
        return isJalaliLeapYear($year) ? 30 : 29;
    }
    return 30; // fallback
}

/**
 * Calculate coach payment based on contract type
 * 
 * @param array $coach Coach data with contract settings and fees
 * @return float Calculated payment amount
 */
function calculateCoachPayment($coach) {
    $contractType = $coach['contract_type'] ?? 'percentage';
    $percentageRate = (float)($coach['percentage_rate'] ?? 50);
    $monthlySalary = (float)($coach['monthly_salary'] ?? 0);
    $eligibleFees = (float)($coach['eligible_fees'] ?? 0);
    
    switch ($contractType) {
        case 'salary':
            return $monthlySalary;
            
        case 'percentage':
            return $eligibleFees * ($percentageRate / 100);
            
        case 'hybrid':
            $percentagePart = $eligibleFees * ($percentageRate / 100);
            return $monthlySalary + $percentagePart;
            
        default:
            return 0;
    }
}

/**
 * Get contract type label in Dari
 * 
 * @param string $contractType Contract type enum value
 * @return string Dari label
 */
function getContractTypeLabel($contractType) {
    $labels = [
        'percentage' => 'درصدی',
        'salary' => 'حقوق ثابت',
        'hybrid' => 'ترکیبی'
    ];
    return $labels[$contractType] ?? $contractType;
}

/**
 * Generate calculation breakdown string
 * 
 * @param array $coach Coach data
 * @param float $payment Calculated payment
 * @return string Human-readable breakdown
 */
function getCalculationBreakdown($coach, $payment) {
    $contractType = $coach['contract_type'] ?? 'percentage';
    $percentageRate = (float)($coach['percentage_rate'] ?? 50);
    $monthlySalary = (float)($coach['monthly_salary'] ?? 0);
    $eligibleFees = (float)($coach['eligible_fees'] ?? 0);
    
    switch ($contractType) {
        case 'salary':
            return "حقوق ثابت: " . number_format($monthlySalary, 0);
            
        case 'percentage':
            return number_format($eligibleFees, 0) . " × " . $percentageRate . "% = " . number_format($payment, 0);
            
        case 'hybrid':
            $percentagePart = $eligibleFees * ($percentageRate / 100);
            return "حقوق: " . number_format($monthlySalary, 0) . " + (" . 
                   number_format($eligibleFees, 0) . " × " . $percentageRate . "% = " . 
                   number_format($percentagePart, 0) . ") = " . number_format($payment, 0);
            
        default:
            return '';
    }
}

/**
 * Calculate coach payouts based on individual contract types
 * 
 * Algorithm:
 * - salary: Fixed monthly_salary
 * - percentage: eligible_fees × (percentage_rate / 100)
 * - hybrid: monthly_salary + (eligible_fees × (percentage_rate / 100))
 * 
 * Eligible fees determined by fee_calculation_slots setting:
 * - morning_evening: Only fees from morning/evening time slots
 * - all: All fees regardless of time slot
 * - custom: Based on counts_for_fee flag in coach_time_slot
 */
function handleGetPayouts() {
    global $db;
    
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    
    if (!$month || !$year) {
        Response::error('Month and year required');
    }
    
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = sprintf('%04d-%02d-%02d', $year, $month, getJalaliDaysInMonth($month, $year));
    
    // Get total student fees for the period
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(r.fee_amount), 0) as total_fees
        FROM registrations r
        WHERE r.registration_date_jalali >= ? 
          AND r.registration_date_jalali <= ?
          AND r.status = 'active'
    ");
    $stmt->execute([$startDate, $endDate]);
    $totalStudentFees = (float)$stmt->fetch()['total_fees'];
    
    // Get total expenses for the period
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_expenses
        FROM expenses
        WHERE expense_date_jalali >= ? 
          AND expense_date_jalali <= ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $totalExpenses = (float)$stmt->fetch()['total_expenses'];
    
    // Get coaches with contract settings and fees collected
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.first_name,
            c.last_name,
            c.contract_type,
            c.percentage_rate,
            c.monthly_salary,
            c.fee_calculation_slots,
            -- Total fees from this coach's students (all slots)
            COALESCE(SUM(r.fee_amount), 0) as total_fees_collected,
            -- Fees from morning/evening students only
            COALESCE(SUM(
                CASE WHEN ts.name LIKE '%morning%' 
                       OR ts.name LIKE '%evening%' 
                       OR ts.name LIKE '%صبح%' 
                       OR ts.name LIKE '%شام%' 
                       OR ts.name LIKE '%عصر%'
                THEN r.fee_amount ELSE 0 END
            ), 0) as morning_evening_fees,
            -- Fees from custom-marked slots (counts_for_fee = 1)
            COALESCE(SUM(
                CASE WHEN cts_fee.counts_for_fee = 1
                THEN r.fee_amount ELSE 0 END
            ), 0) as custom_eligible_fees,
            COUNT(DISTINCT r.id) as registration_count
        FROM coaches c
        LEFT JOIN registrations r ON c.id = r.coach_id
            AND r.registration_date_jalali >= ?
            AND r.registration_date_jalali <= ?
            AND r.status = 'active'
        LEFT JOIN time_slots ts ON r.time_slot_id = ts.id
        LEFT JOIN coach_time_slot cts_fee ON c.id = cts_fee.coach_id 
            AND r.time_slot_id = cts_fee.time_slot_id
        WHERE c.status = 'active'
        GROUP BY c.id, c.first_name, c.last_name, c.contract_type, 
                 c.percentage_rate, c.monthly_salary, c.fee_calculation_slots
    ");
    $stmt->execute([$startDate, $endDate]);
    $coaches = $stmt->fetchAll();
    
    // Calculate payouts for each coach
    $payouts = [];
    $totalCoachPayments = 0;
    
    foreach ($coaches as $coach) {
        // Determine eligible fees based on fee_calculation_slots setting
        // Default is 'all' - coach gets percentage from all their students
        $feeCalcSlots = $coach['fee_calculation_slots'] ?? 'all';
        
        switch ($feeCalcSlots) {
            case 'morning_evening':
                $eligibleFees = (float)$coach['morning_evening_fees'];
                break;
            case 'custom':
                $eligibleFees = (float)$coach['custom_eligible_fees'];
                break;
            case 'all':
            default:
                $eligibleFees = (float)$coach['total_fees_collected'];
                break;
        }
        
        $coach['eligible_fees'] = $eligibleFees;
        
        // Calculate payment based on contract type
        $calculatedPayment = calculateCoachPayment($coach);
        $totalCoachPayments += $calculatedPayment;
        
        $payouts[] = [
            'coach_id' => (int)$coach['id'],
            'coach_name' => $coach['first_name'] . ' ' . $coach['last_name'],
            'contract_type' => $coach['contract_type'],
            'contract_type_label' => getContractTypeLabel($coach['contract_type']),
            'percentage_rate' => (float)$coach['percentage_rate'],
            'monthly_salary' => (float)$coach['monthly_salary'],
            'fee_calculation_slots' => $feeCalcSlots,
            'total_fees_collected' => (float)$coach['total_fees_collected'],
            'morning_evening_fees' => (float)$coach['morning_evening_fees'],
            'eligible_fees' => $eligibleFees,
            'registration_count' => (int)$coach['registration_count'],
            'calculated_payment' => $calculatedPayment,
            'calculation_breakdown' => getCalculationBreakdown($coach, $calculatedPayment)
        ];
    }
    
    // Sort by calculated payment descending
    usort($payouts, function($a, $b) {
        return $b['calculated_payment'] <=> $a['calculated_payment'];
    });
    
    // Calculate camp net income
    $campNetIncome = $totalStudentFees - $totalCoachPayments - $totalExpenses;
    
    Response::success([
        'month' => $month,
        'year' => $year,
        'month_name' => JalaliDate::getMonthName($month),
        'total_student_fees' => $totalStudentFees,
        'total_coach_payments' => $totalCoachPayments,
        'total_expenses' => $totalExpenses,
        'camp_net_income' => $campNetIncome,
        'payouts' => $payouts
    ]);
}

/**
 * Get detailed breakdown with transaction links
 */
function handleGetBreakdown() {
    global $db;
    
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    $type = $_GET['type'] ?? ''; // 'income', 'expense', 'coach'
    $id = (int)($_GET['id'] ?? 0);
    
    if (!$month || !$year) {
        Response::error('Month and year required');
    }
    
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = sprintf('%04d-%02d-%02d', $year, $month, getJalaliDaysInMonth($month, $year));
    
    $breakdown = [];
    
    if ($type === 'income' || $type === '') {
        // Income breakdown
        $stmt = $db->prepare("
            SELECT r.id, r.fee_amount, r.registration_date_jalali,
                   s.first_name, s.last_name,
                   c.first_name as coach_first_name, c.last_name as coach_last_name,
                   ts.name as time_slot_name
            FROM registrations r
            INNER JOIN students s ON r.student_id = s.id
            INNER JOIN coaches c ON r.coach_id = c.id
            INNER JOIN time_slots ts ON r.time_slot_id = ts.id
            WHERE r.registration_date_jalali >= ? 
              AND r.registration_date_jalali <= ?
              AND r.status = 'active'
            ORDER BY r.registration_date_jalali DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $breakdown['income'] = $stmt->fetchAll();
    }
    
    if ($type === 'expense' || $type === '') {
        // Expense breakdown
        $stmt = $db->prepare("
            SELECT e.id, e.title, e.category, e.amount, e.expense_date_jalali, e.receipt_path
            FROM expenses e
            WHERE e.expense_date_jalali >= ? 
              AND e.expense_date_jalali <= ?
            ORDER BY e.expense_date_jalali DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $breakdown['expenses'] = $stmt->fetchAll();
    }
    
    if ($type === 'coach' && $id) {
        // Coach-specific transactions
        $stmt = $db->prepare("
            SELECT r.id, r.fee_amount, r.registration_date_jalali,
                   s.first_name, s.last_name,
                   ts.name as time_slot_name
            FROM registrations r
            INNER JOIN students s ON r.student_id = s.id
            INNER JOIN time_slots ts ON r.time_slot_id = ts.id
            WHERE r.coach_id = ?
              AND r.registration_date_jalali >= ? 
              AND r.registration_date_jalali <= ?
              AND r.status = 'active'
            ORDER BY r.registration_date_jalali DESC
        ");
        $stmt->execute([$id, $startDate, $endDate]);
        $breakdown['coach_transactions'] = $stmt->fetchAll();
    }
    
    Response::success($breakdown);
}


