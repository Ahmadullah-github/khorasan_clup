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
 * 
 * BUSINESS DECISION: Income is calculated based on registration_date, not payment_date.
 * This is because:
 * 1. Registrations and payments are created together in the current workflow
 * 2. Registration date represents when the student became active
 * 3. For accrual-based accounting, revenue is recognized when service is provided
 * 
 * The payments table is still queried for verification and audit purposes.
 */
function handleGetNetIncome() {
    global $db;
    
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    
    if (!$month || !$year) {
        Response::error('Month and year required');
    }
    
    $dateRange = JalaliDate::getMonthDateRange($year, $month);
    $startDate = $dateRange['start'];
    $endDate = $dateRange['end'];
    
    // Calculate total income based on registrations (primary method)
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
    
    // Also query payments table for verification (secondary/audit)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(p.amount), 0) as total_payments,
               COUNT(p.id) as payment_count
        FROM payments p
        WHERE p.payment_date_jalali >= ? 
          AND p.payment_date_jalali <= ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $payments = $stmt->fetch();
    $totalPayments = (float)($payments['total_payments'] ?? 0);
    
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
    
    // Calculate net income with proper rounding
    $netIncome = Money::subtract($totalIncome, $totalExpenses);
    
    // Check for discrepancy between registrations and payments
    $incomeDiscrepancy = Money::subtract($totalIncome, $totalPayments);
    
    Response::success([
        'month' => $month,
        'year' => $year,
        'month_name' => JalaliDate::getMonthName($month),
        'total_income' => $totalIncome,
        'total_expenses' => $totalExpenses,
        'net_income' => $netIncome,
        'income_count' => (int)$income['registration_count'],
        'expense_count' => (int)$expenses['expense_count'],
        // Payment verification data
        'total_payments' => $totalPayments,
        'payment_count' => (int)$payments['payment_count'],
        'income_discrepancy' => $incomeDiscrepancy,
        'has_discrepancy' => !Money::equals($totalIncome, $totalPayments)
    ]);
}

// Time slot detection is now centralized in TimeSlotDetector class (utils.php)
// Use TimeSlotDetector::isMorningEvening($slotName) for PHP-side checks
// Use TimeSlotDetector::getSqlCaseExpression() for SQL queries

// Date calculation functions are now centralized in JalaliDate class (utils.php)
// - JalaliDate::isLeapYear($year)
// - JalaliDate::getDaysInMonth($month, $year)
// - JalaliDate::getMonthDateRange($year, $month)

/**
 * Calculate coach payment based on contract type
 * Uses Money class for proper rounding to avoid floating-point precision issues
 * 
 * @param array $coach Coach data with contract settings and fees
 * @return float Calculated payment amount (rounded to 2 decimal places)
 */
function calculateCoachPayment($coach) {
    $contractType = $coach['contract_type'] ?? 'percentage';
    $percentageRate = (float)($coach['percentage_rate'] ?? 50);
    $monthlySalary = (float)($coach['monthly_salary'] ?? 0);
    $eligibleFees = (float)($coach['eligible_fees'] ?? 0);
    
    switch ($contractType) {
        case 'salary':
            return Money::round($monthlySalary);
            
        case 'percentage':
            return Money::percentage($eligibleFees, $percentageRate);
            
        case 'hybrid':
            $percentagePart = Money::percentage($eligibleFees, $percentageRate);
            return Money::add($monthlySalary, $percentagePart);
            
        default:
            return 0.0;
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
    
    $dateRange = JalaliDate::getMonthDateRange($year, $month);
    $startDate = $dateRange['start'];
    $endDate = $dateRange['end'];
    
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
            -- Fees from morning/evening students only (consistent with TimeSlotDetector)
            COALESCE(SUM(
                CASE WHEN LOWER(ts.name) LIKE '%morning%' 
                       OR LOWER(ts.name) LIKE '%evening%' 
                       OR ts.name LIKE '%صبح%' 
                       OR ts.name LIKE '%صبحانه%'
                       OR ts.name LIKE '%شب%'
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
    
    // Calculate camp net income with proper rounding
    $campNetIncome = Money::subtract($totalStudentFees, $totalCoachPayments, $totalExpenses);
    
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
    
    $dateRange = JalaliDate::getMonthDateRange($year, $month);
    $startDate = $dateRange['start'];
    $endDate = $dateRange['end'];
    
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


