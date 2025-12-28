<?php
/**
 * Dynamic Manifest Generator
 * Generates PWA manifest with dynamic organization settings
 */

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Try to load settings from database
$orgName = 'کمپ خراسان';
$orgNameShort = 'کمپ خراسان';
$orgDescription = 'سیستم مدیریت دانش‌آموزان، مربیان و مالی';

try {
    require_once '../api/config.php';
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('org_name_fa', 'org_slogan')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (!empty($settings['org_name_fa'])) {
        $orgName = $settings['org_name_fa'] . ' - سیستم مدیریت';
        $orgNameShort = $settings['org_name_fa'];
    }
    if (!empty($settings['org_slogan'])) {
        $orgDescription = $settings['org_slogan'];
    }
} catch (Exception $e) {
    // Use defaults if database not available
}

$manifest = [
    'name' => $orgName,
    'short_name' => $orgNameShort,
    'description' => $orgDescription,
    'start_url' => './',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#3b82f6',
    'orientation' => 'portrait-primary',
    'scope' => './',
    'lang' => 'fa',
    'dir' => 'rtl',
    'categories' => ['education', 'business', 'productivity'],
    'icons' => [
        [
            'src' => 'assets/icons/favicon-48x48.png',
            'sizes' => '48x48',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => 'assets/icons/apple-touch-icon-72x72.png',
            'sizes' => '72x72',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => 'assets/icons/apple-touch-icon-120x120.png',
            'sizes' => '120x120',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => 'assets/icons/mstile-150x150.png',
            'sizes' => '150x150',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => 'assets/icons/apple-touch-icon-152x152.png',
            'sizes' => '152x152',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => 'assets/icons/apple-touch-icon-180x180.png',
            'sizes' => '180x180',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => 'assets/icons/android-chrome-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => 'assets/icons/android-chrome-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ]
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
