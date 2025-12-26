<?php
/**
 * Sports Camp Management System - Invoices API
 * Handles invoice viewing, PDF generation, WhatsApp sharing
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

Session::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$invoiceId = $_GET['id'] ?? null;
$action = $_GET['action'] ?? '';

$db = Database::getInstance()->getConnection();
$user = Session::getUser();

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            handleListInvoices();
        } elseif ($invoiceId && $action === 'pdf') {
            handleGetPDF($invoiceId);
        } elseif ($invoiceId) {
            handleGetInvoice($invoiceId);
        } else {
            Response::error('شناسه فاکتور الزامی است');
        }
        break;
        
    case 'POST':
        if ($invoiceId && $action === 'whatsapp') {
            handleWhatsAppShare($invoiceId);
        } elseif ($invoiceId && $action === 'mark-paid') {
            handleMarkAsPaid($invoiceId);
        } else {
            Response::error('عملیات نامعتبر است');
        }
        break;
        
    default:
        Response::error('متد پشتیبانی نمی‌شود', 405);
}

/**
 * List invoices with pagination and filters
 */
function handleListInvoices() {
    global $db;
    
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? PAGINATION_PER_PAGE);
    $search = $_GET['q'] ?? '';
    $status = $_GET['status'] ?? '';
    $month = $_GET['month'] ?? '';
    
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR i.invoice_number LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($status && in_array($status, ['paid', 'pending'])) {
        $where[] = "i.status = ?";
        $params[] = $status;
    }
    
    if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
        $where[] = "i.issued_date_jalali LIKE ?";
        $params[] = $month . '%';
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) as total 
        FROM invoices i
        INNER JOIN registrations r ON i.registration_id = r.id
        INNER JOIN students s ON r.student_id = s.id
        {$whereClause}
    ";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Get paginated results
    $pagination = Pagination::calculate($page, $perPage, $total);
    
    $sql = "
        SELECT i.*, 
               s.first_name, s.last_name,
               c.first_name as coach_first_name, c.last_name as coach_last_name
        FROM invoices i
        INNER JOIN registrations r ON i.registration_id = r.id
        INNER JOIN students s ON r.student_id = s.id
        INNER JOIN coaches c ON r.coach_id = c.id
        {$whereClause}
        ORDER BY i.issued_date_jalali DESC, i.id DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $pagination['per_page'];
    $params[] = $pagination['offset'];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
    
    Response::success([
        'invoices' => $invoices,
        'pagination' => $pagination
    ]);
}

/**
 * Get invoice details
 */
function handleGetInvoice($invoiceId) {
    global $db;
    
    // Validate invoice ID
    if (!is_numeric($invoiceId) || $invoiceId <= 0) {
        Response::error('شناسه فاکتور نامعتبر است');
    }
    
    try {
        $stmt = $db->prepare("
            SELECT i.*,
                   r.fee_amount, r.registration_date_jalali, r.start_date_jalali, r.end_date_jalali,
                   s.first_name, s.last_name, s.father_name, s.contact_number, s.photo_path,
                   c.first_name as coach_first_name, c.last_name as coach_last_name,
                   ts.name as time_slot_name
            FROM invoices i
            INNER JOIN registrations r ON i.registration_id = r.id
            INNER JOIN students s ON r.student_id = s.id
            INNER JOIN coaches c ON r.coach_id = c.id
            INNER JOIN time_slots ts ON r.time_slot_id = ts.id
            WHERE i.id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        
        if (!$invoice) {
            Response::error('فاکتور یافت نشد', 404);
        }
        
        // Add status if not set (for backward compatibility)
        if (!isset($invoice['status'])) {
            $invoice['status'] = 'paid';
        }
        
        Response::success($invoice);
        
    } catch (PDOException $e) {
        error_log("Invoice fetch error: " . $e->getMessage());
        Response::error('خطا در بارگذاری فاکتور');
    }
}

/**
 * Generate/retrieve PDF invoice
 */
function handleGetPDF($invoiceId) {
    global $db;
    
    if (!is_numeric($invoiceId) || $invoiceId <= 0) {
        Response::error('شناسه فاکتور نامعتبر است');
    }
    
    try {
        $stmt = $db->prepare("
            SELECT i.*,
                   r.fee_amount, r.registration_date_jalali, r.start_date_jalali, r.end_date_jalali,
                   s.first_name, s.last_name, s.father_name, s.contact_number,
                   c.first_name as coach_first_name, c.last_name as coach_last_name,
                   ts.name as time_slot_name
            FROM invoices i
            INNER JOIN registrations r ON i.registration_id = r.id
            INNER JOIN students s ON r.student_id = s.id
            INNER JOIN coaches c ON r.coach_id = c.id
            INNER JOIN time_slots ts ON r.time_slot_id = ts.id
            WHERE i.id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        
        if (!$invoice) {
            Response::error('فاکتور یافت نشد', 404);
        }
        
        // Check if PDF already exists
        if (!empty($invoice['pdf_path']) && defined('INVOICE_DIR') && file_exists(INVOICE_DIR . $invoice['pdf_path'])) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="invoice_' . $invoice['invoice_number'] . '.pdf"');
            readfile(INVOICE_DIR . $invoice['pdf_path']);
            exit;
        }
        
        // Return invoice data for client-side PDF generation
        Response::success([
            'invoice' => $invoice,
            'pdf_path' => null,
            'message' => 'برای تولید PDF از قابلیت چاپ مرورگر استفاده کنید'
        ]);
        
    } catch (PDOException $e) {
        error_log("PDF generation error: " . $e->getMessage());
        Response::error('خطا در تولید PDF');
    }
}

/**
 * Generate WhatsApp share link
 */
function handleWhatsAppShare($invoiceId) {
    global $db;
    
    if (!is_numeric($invoiceId) || $invoiceId <= 0) {
        Response::error('شناسه فاکتور نامعتبر است');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $method = $data['method'] ?? 'link';
    
    try {
        $stmt = $db->prepare("
            SELECT i.*,
                   r.fee_amount, r.registration_date_jalali, r.start_date_jalali, r.end_date_jalali,
                   s.first_name, s.last_name, s.contact_number,
                   c.first_name as coach_first_name, c.last_name as coach_last_name,
                   ts.name as time_slot_name
            FROM invoices i
            INNER JOIN registrations r ON i.registration_id = r.id
            INNER JOIN students s ON r.student_id = s.id
            INNER JOIN coaches c ON r.coach_id = c.id
            INNER JOIN time_slots ts ON r.time_slot_id = ts.id
            WHERE i.id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        
        if (!$invoice) {
            Response::error('فاکتور یافت نشد', 404);
        }
        
        // Format invoice message in Dari/Persian - clean format without emojis
        $formattedAmount = number_format($invoice['total_amount'], 0);
        // Use registration date (when student actually registered/paid) instead of invoice issue date
        $formattedDate = JalaliDate::format($invoice['registration_date_jalali']);
        
        // Build clean WhatsApp message
        $message = "*فاکتور کمپ خراسان*\n\n";
        
        $message .= "شماره: {$invoice['invoice_number']}\n";
        $message .= "تاریخ: {$formattedDate}\n\n";
        
        $message .= "دانش آموز: {$invoice['first_name']} {$invoice['last_name']}\n";
        $message .= "مربی: {$invoice['coach_first_name']} {$invoice['coach_last_name']}\n";
        $message .= "زمان: {$invoice['time_slot_name']}\n\n";
        
        $message .= "مبلغ: {$formattedAmount} افغانی\n\n";
        
        $message .= "با تشکر از اعتماد شما\n";
        $message .= "کمپ خراسان";
        
        $encodedMessage = urlencode($message);
        
        // Clean phone number
        $phone = $invoice['contact_number'] ?? '';
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Convert Afghan phone format to international
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            $phone = '93' . substr($phone, 1); // Afghanistan country code
        }
        
        $whatsappLink = "https://wa.me/{$phone}?text={$encodedMessage}";
        
        Response::success([
            'method' => 'link',
            'whatsapp_link' => $whatsappLink,
            'phone' => $phone,
            'message' => $message
        ]);
        
    } catch (PDOException $e) {
        error_log("WhatsApp share error: " . $e->getMessage());
        Response::error('خطا در ایجاد لینک واتساپ');
    }
}

/**
 * Mark invoice as paid
 */
function handleMarkAsPaid($invoiceId) {
    global $db, $user;
    
    if (!is_numeric($invoiceId) || $invoiceId <= 0) {
        Response::error('شناسه فاکتور نامعتبر است');
    }
    
    try {
        // Check if invoice exists
        $stmt = $db->prepare("SELECT id, invoice_number, status FROM invoices WHERE id = ?");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        
        if (!$invoice) {
            Response::error('فاکتور یافت نشد', 404);
        }
        
        if ($invoice['status'] === 'paid') {
            Response::error('این فاکتور قبلاً پرداخت شده است');
        }
        
        // Update status
        $stmt = $db->prepare("UPDATE invoices SET status = 'paid', paid_date_jalali = ? WHERE id = ?");
        $stmt->execute([JalaliDate::now(), $invoiceId]);
        
        Audit::log($user['id'], 'update', 'invoices', $invoiceId, "علامت‌گذاری فاکتور {$invoice['invoice_number']} به عنوان پرداخت شده");
        
        Response::success(null, 'فاکتور با موفقیت به عنوان پرداخت شده علامت‌گذاری شد');
        
    } catch (PDOException $e) {
        error_log("Mark paid error: " . $e->getMessage());
        Response::error('خطا در به‌روزرسانی وضعیت فاکتور');
    }
}
