<?php
/**
 * Sports Camp Management System - API Router/Dispatcher
 * Routes requests to appropriate API endpoints
 */

// Get request path
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];
$path = str_replace(dirname($scriptName), '', parse_url($requestUri, PHP_URL_PATH));
$path = trim($path, '/');

// Remove 'api' prefix if present
if (strpos($path, 'api/') === 0) {
    $path = substr($path, 4);
}

// Parse path segments
$segments = explode('/', $path);
$endpoint = $segments[0] ?? '';

// Route to appropriate API file
switch ($endpoint) {
    case 'auth':
        require __DIR__ . '/auth.php';
        break;
    case 'students':
        require __DIR__ . '/students.php';
        break;
    case 'coaches':
        require __DIR__ . '/coaches.php';
        break;
    case 'expenses':
        require __DIR__ . '/expenses.php';
        break;
    case 'rent':
        require __DIR__ . '/rent.php';
        break;
    case 'accounting':
        require __DIR__ . '/accounting.php';
        break;
    case 'reports':
        require __DIR__ . '/reports.php';
        break;
    case 'invoices':
        require __DIR__ . '/invoices.php';
        break;
    case 'sync':
        require __DIR__ . '/sync.php';
        break;
    case 'audit':
        require __DIR__ . '/audit.php';
        break;
    case 'upload':
        require __DIR__ . '/upload.php';
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'نقطه پایانی یافت نشد']);
        break;
}

