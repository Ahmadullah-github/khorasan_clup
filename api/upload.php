<?php
/**
 * Sports Camp Management System - File Upload API
 * Handles photo and receipt uploads
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

Session::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$type = $_GET['type'] ?? ''; // 'photo' or 'receipt'

$user = Session::getUser();

if ($method !== 'POST') {
    Response::error('روش مجاز نیست', 405);
}

if (!isset($_FILES['file'])) {
    Response::error('هیچ فایلی آپلود نشده است');
}

$file = $_FILES['file'];

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    Response::error('خطا در آپلود فایل: ' . $file['error']);
}

if ($file['size'] > MAX_UPLOAD_SIZE) {
    Response::error('فایل بیش از حد بزرگ است. حداکثر اندازه: ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' مگابایت');
}

// Validate file type
$allowedTypes = [];
$uploadDir = '';

if ($type === 'photo') {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $uploadDir = UPLOAD_DIR . 'photos/';
} elseif ($type === 'receipt') {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $uploadDir = UPLOAD_DIR . 'receipts/';
} else {
    Response::error('نوع آپلود نامعتبر. از "photo" یا "receipt" استفاده کنید');
}

if (!in_array($file['type'], $allowedTypes)) {
    Response::error('نوع فایل نامعتبر. مجاز: ' . implode(', ', $allowedTypes));
}

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    Response::error('خطا در ذخیره فایل');
}

// Return relative path for database storage
$relativePath = 'assets/uploads/' . ($type === 'photo' ? 'photos/' : 'receipts/') . $filename;

Audit::log($user['id'], 'upload', 'files', null, "آپلود {$type}: {$filename}");

Response::success([
    'file_path' => $relativePath,
    'filename' => $filename,
    'size' => $file['size'],
    'type' => $file['type']
], 'فایل با موفقیت آپلود شد');

