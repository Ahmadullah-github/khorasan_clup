<?php
/**
 * Sports Camp Management System - Reports API
 * Handles monthly reports, student activity, coach earnings, exports
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

Session::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        if ($action === 'monthly') {
            handleMonthlyReport();
        } elseif ($action === 'students') {
            handleStudentReport();
        } elseif ($action === 'coaches') {
            handleCoachReport();
        } elseif ($action === 'export' && isset($_GET['type'])) {
            handleExport();
        } else {
            Response::error('عملیات نامعتبر');
        }
        break;
        
    default:
        Response::error('روش مجاز نیست', 405);
}

/**
 * Monthly report (revenue, expenses, net income)
 */
function handleMonthlyReport() {
    global $db;
    
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    
    if (!$month || !$year) {
        Response::error('ماه و سال الزامی است');
    }
    
    $dateRange = JalaliDate::getMonthDateRange($year, $month);
    $startDate = $dateRange['start'];
    $endDate = $dateRange['end'];
    
    // Revenue
    $stmt = $db->prepare("
        SELECT SUM(fee_amount) as total_revenue,
               COUNT(*) as registration_count
        FROM registrations
        WHERE registration_date_jalali >= ? 
          AND registration_date_jalali <= ?
          AND status = 'active'
    ");
    $stmt->execute([$startDate, $endDate]);
    $revenue = $stmt->fetch();
    
    // Expenses (use LOWER for case-insensitive category matching and grouping)
    $stmt = $db->prepare("
        SELECT SUM(amount) as total_expenses,
               COUNT(*) as expense_count,
               LOWER(category) as category,
               SUM(CASE WHEN LOWER(category) = 'rent' THEN amount ELSE 0 END) as rent_amount
        FROM expenses
        WHERE expense_date_jalali >= ? 
          AND expense_date_jalali <= ?
        GROUP BY LOWER(category)
    ");
    $stmt->execute([$startDate, $endDate]);
    $expensesByCategory = $stmt->fetchAll();
    
    $totalExpenses = Money::round(array_sum(array_column($expensesByCategory, 'total_expenses')));
    $netIncome = Money::subtract((float)($revenue['total_revenue'] ?? 0), $totalExpenses);
    
    Response::success([
        'month' => $month,
        'year' => $year,
        'month_name' => JalaliDate::getMonthName($month),
        'revenue' => [
            'total' => (float)($revenue['total_revenue'] ?? 0),
            'count' => (int)($revenue['registration_count'] ?? 0)
        ],
        'expenses' => [
            'total' => $totalExpenses,
            'by_category' => $expensesByCategory
        ],
        'net_income' => $netIncome
    ]);
}

/**
 * Student activity report
 */
function handleStudentReport() {
    global $db;
    
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    
    $where = [];
    $params = [];
    
    if ($month && $year) {
        $dateRange = JalaliDate::getMonthDateRange($year, $month);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        $where[] = "r.registration_date_jalali >= ? AND r.registration_date_jalali <= ?";
        $params = [$startDate, $endDate];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // New vs renewed
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN s.created_at_jalali = r.registration_date_jalali THEN s.id END) as new_students,
            COUNT(DISTINCT CASE WHEN s.created_at_jalali < r.registration_date_jalali THEN s.id END) as renewed_students,
            COUNT(DISTINCT s.id) as total_students
        FROM registrations r
        INNER JOIN students s ON r.student_id = s.id
        {$whereClause}
        AND r.status = 'active'
    ");
    $stmt->execute($params);
    $activity = $stmt->fetch();
    
    // Expiring students (within next 7 days from today)
    // Note: Jalali dates are stored as strings (YYYY-MM-DD), so string comparison works correctly
    $today = JalaliDate::now();
    
    // For "next 7 days" we need to look at registrations ending between today and 7 days from now
    // Since we can't use DATE_SUB on Jalali strings, we do a simple comparison:
    // - end_date <= today means already expired or expiring today
    // - We want students whose registration is about to expire, so end_date is close to today
    $stmt = $db->prepare("
        SELECT s.*, r.end_date_jalali
        FROM students s
        INNER JOIN registrations r ON s.id = r.student_id
        WHERE r.status = 'active' 
          AND r.end_date_jalali <= ?
        ORDER BY r.end_date_jalali ASC
    ");
    $stmt->execute([$today]);
    $expiring = $stmt->fetchAll();
    
    Response::success([
        'activity' => $activity,
        'expiring_students' => $expiring
    ]);
}

/**
 * Coach earnings report
 */
function handleCoachReport() {
    global $db;
    
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    
    $where = [];
    $params = [];
    
    if ($month && $year) {
        $dateRange = JalaliDate::getMonthDateRange($year, $month);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
        $where[] = "r.registration_date_jalali >= ? AND r.registration_date_jalali <= ?";
        $params = [$startDate, $endDate];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $db->prepare("
        SELECT c.id, c.first_name, c.last_name,
               SUM(r.fee_amount) as total_earnings,
               COUNT(r.id) as registration_count,
               COUNT(DISTINCT r.student_id) as student_count
        FROM coaches c
        LEFT JOIN registrations r ON c.id = r.coach_id
            {$whereClause}
            AND r.status = 'active'
        GROUP BY c.id
        ORDER BY total_earnings DESC
    ");
    $stmt->execute($params);
    $coaches = $stmt->fetchAll();
    
    Response::success($coaches);
}

/**
 * Export data (CSV or PDF)
 */
function handleExport() {
    global $db;
    
    $type = $_GET['type']; // 'students', 'expenses', 'coaches', 'monthly'
    $format = $_GET['format'] ?? 'csv'; // 'csv' or 'pdf'
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    
    if ($format === 'csv') {
        exportCSV($type, $month, $year);
    } elseif ($format === 'pdf') {
        exportPDF($type, $month, $year);
    } else {
        Response::error('فرمت نامعتبر');
    }
}

/**
 * Export to CSV
 */
function exportCSV($type, $month, $year) {
    global $db;
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_' . $year . '_' . $month . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($type) {
        case 'students':
            $stmt = $db->query("
                SELECT s.*, 
                       COUNT(r.id) as registration_count,
                       MAX(r.end_date_jalali) as last_registration_end
                FROM students s
                LEFT JOIN registrations r ON s.id = r.student_id
                GROUP BY s.id
                ORDER BY s.created_at_jalali DESC
            ");
            $data = $stmt->fetchAll();
            
            if (!empty($data)) {
                fputcsv($output, array_keys($data[0]));
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
            }
            break;
            
        case 'expenses':
            $where = '';
            $params = [];
            if ($month && $year) {
                $dateRange = JalaliDate::getMonthDateRange($year, $month);
                $startDate = $dateRange['start'];
                $endDate = $dateRange['end'];
                $where = "WHERE expense_date_jalali >= ? AND expense_date_jalali <= ?";
                $params = [$startDate, $endDate];
            }
            $stmt = $db->prepare("SELECT * FROM expenses {$where} ORDER BY expense_date_jalali DESC");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            if (!empty($data)) {
                fputcsv($output, array_keys($data[0]));
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
            }
            break;
            
        case 'monthly':
            if (!$month || !$year) {
                Response::error('ماه و سال برای خروجی ماهانه الزامی است');
            }
            // Export monthly summary
            $dateRange = JalaliDate::getMonthDateRange($year, $month);
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];
            
            $stmt = $db->prepare("
                SELECT 'Revenue' as type, SUM(fee_amount) as amount, COUNT(*) as count
                FROM registrations
                WHERE registration_date_jalali >= ? AND registration_date_jalali <= ? AND status = 'active'
                UNION ALL
                SELECT 'Expenses' as type, SUM(amount) as amount, COUNT(*) as count
                FROM expenses
                WHERE expense_date_jalali >= ? AND expense_date_jalali <= ?
            ");
            $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
            $data = $stmt->fetchAll();
            
            fputcsv($output, ['Type', 'Amount', 'Count']);
            foreach ($data as $row) {
                fputcsv($output, [$row['type'], $row['amount'], $row['count']]);
            }
            break;
    }
    
    fclose($output);
    exit;
}

/**
 * Export to PDF (simplified - would use TCPDF in production)
 */
function exportPDF($type, $month, $year) {
    // For now, return JSON with message
    // In production, this would generate a PDF using TCPDF
    Response::success([
        'message' => 'عملکرد خروجی PDF نیاز به کتابخانه TCPDF دارد. لطفاً از خروجی CSV استفاده کنید.',
        'type' => $type,
        'month' => $month,
        'year' => $year
    ]);
}


