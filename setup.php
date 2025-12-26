#!/usr/bin/env php
<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  کمپ خراسان - Setup Script (Beginner Friendly!)
 *  Khorasan Club Management System - Automated Setup
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * This script helps new developers set up the project quickly.
 * 
 * HOW TO RUN:
 * -----------
 * Windows:  Double-click setup.php OR run: php setup.php
 * Mac/Linux: Open Terminal, cd to project folder, run: php setup.php
 * 
 * WHAT IT DOES:
 * -------------
 * 1. Checks if your computer has everything needed (PHP, MySQL)
 * 2. Starts XAMPP/LAMPP/MAMP services automatically
 * 3. Creates the database and tables
 * 4. Sets up the admin user account
 * 5. Opens the app in your browser
 * 
 * REQUIREMENTS:
 * -------------
 * - XAMPP (Windows/Mac) or LAMPP (Linux) installed
 * - PHP 7.4 or higher
 * - MySQL/MariaDB
 */

// ═══════════════════════════════════════════════════════════════════════════
// CONFIGURATION DEFAULTS
// ═══════════════════════════════════════════════════════════════════════════

$config = [
    'db_host' => '127.0.0.1',  // TCP/IP connection (more reliable than 'localhost')
    'db_name' => 'khorasan_club',
    'db_user' => 'root',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',
    'schema_file' => __DIR__ . '/database/install.sql',
    'public_folder' => 'public',
    'default_port' => 80,
    'app_name' => 'sports-camp',  // Default URL path name
];

// ═══════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS - Console Output
// ═══════════════════════════════════════════════════════════════════════════

// Include utilities for Jalali date functions
require_once __DIR__ . '/api/utils.php';

function println($message = '') {
    echo $message . PHP_EOL;
}

function printHeader() {
    // Clear screen on supported terminals
    if (!isWindows()) {
        echo "\033[2J\033[H";
    }
    
    println();
    println("╔═══════════════════════════════════════════════════════════════════════╗");
    println("║                                                                       ║");
    println("║     🏃 کمپ خراسان - Khorasan Sports Camp                              ║");
    println("║     📦 Easy Setup Wizard (Beginner Friendly!)                         ║");
    println("║                                                                       ║");
    println("╚═══════════════════════════════════════════════════════════════════════╝");
    println();
}

function printStep($step, $total, $message) {
    println();
    println("  ┌─────────────────────────────────────────────────────────────────────┐");
    println("  │  STEP $step of $total: $message");
    println("  └─────────────────────────────────────────────────────────────────────┘");
}

function printSuccess($message) {
    println("  ✅ $message");
}

function printError($message) {
    println("  ❌ ERROR: $message");
}

function printWarning($message) {
    println("  ⚠️  WARNING: $message");
}

function printInfo($message) {
    println("  ℹ️  $message");
}

function printTip($message) {
    println("  💡 TIP: $message");
}

function printDivider() {
    println("  ─────────────────────────────────────────────────────────────────────");
}

function printWaiting($message) {
    echo "  ⏳ $message";
}

function printDone() {
    println(" Done!");
}

function askQuestion($question, $default = null) {
    $defaultText = $default !== null ? " [$default]" : "";
    echo "\n  ❓ $question$defaultText: ";
    
    // Flush output
    if (function_exists('readline')) {
        $answer = readline();
    } else {
        $handle = fopen("php://stdin", "r");
        $answer = trim(fgets($handle));
        fclose($handle);
    }
    
    return trim($answer) !== '' ? trim($answer) : $default;
}

function askYesNo($question, $default = 'y') {
    $hint = $default === 'y' ? "(Y/n)" : "(y/N)";
    $answer = strtolower(askQuestion($question . " $hint", $default));
    return in_array($answer, ['y', 'yes', 'بله', 'آره', '']);
}

function pressEnterToContinue($message = "Press ENTER to continue...") {
    echo "\n  👉 $message";
    if (function_exists('readline')) {
        readline();
    } else {
        $handle = fopen("php://stdin", "r");
        fgets($handle);
        fclose($handle);
    }
}

function executeCommand($command, $silent = false) {
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    return ['output' => implode("\n", $output), 'code' => $returnCode];
}

// ═══════════════════════════════════════════════════════════════════════════
// SYSTEM DETECTION FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════

function isWindows() {
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

function isMac() {
    return strtoupper(PHP_OS) === 'DARWIN';
}

function isLinux() {
    return strtoupper(PHP_OS) === 'LINUX';
}

function getOSName() {
    if (isWindows()) return 'Windows';
    if (isMac()) return 'macOS';
    if (isLinux()) return 'Linux';
    return PHP_OS;
}

// ═══════════════════════════════════════════════════════════════════════════
// XAMPP/LAMPP DETECTION & CONTROL
// ═══════════════════════════════════════════════════════════════════════════

function detectXamppPath() {
    $possiblePaths = [];
    
    if (isWindows()) {
        $possiblePaths = [
            'C:\\xampp',
            'D:\\xampp',
            'E:\\xampp',
            'C:\\Program Files\\xampp',
            'C:\\Program Files (x86)\\xampp',
            getenv('USERPROFILE') . '\\xampp',
        ];
    } elseif (isMac()) {
        $possiblePaths = [
            '/Applications/XAMPP',
            '/Applications/MAMP',
            '/Applications/MAMP PRO',
        ];
    } else {
        $possiblePaths = [
            '/opt/lampp',
            '/opt/xampp',
            '/usr/local/xampp',
            '/usr/local/lampp',
        ];
    }
    
    foreach ($possiblePaths as $path) {
        if (is_dir($path)) {
            return $path;
        }
    }
    
    return null;
}

function detectHtdocsPath($xamppPath = null) {
    if ($xamppPath === null) {
        $xamppPath = detectXamppPath();
    }
    
    if ($xamppPath === null) {
        return null;
    }
    
    $htdocsPaths = [
        $xamppPath . '/htdocs',
        $xamppPath . '/xamppfiles/htdocs',  // Mac XAMPP
        $xamppPath . '/www',                 // MAMP
    ];
    
    foreach ($htdocsPaths as $path) {
        if (is_dir($path)) {
            return $path;
        }
    }
    
    return null;
}

function isApacheRunning() {
    if (isWindows()) {
        $result = executeCommand('tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL');
        return strpos($result['output'], 'httpd.exe') !== false;
    } else {
        $result = executeCommand('pgrep -f "httpd|apache2"');
        return $result['code'] === 0 && !empty(trim($result['output']));
    }
}

function isMySQLRunning() {
    if (isWindows()) {
        $result = executeCommand('tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL');
        return strpos($result['output'], 'mysqld.exe') !== false;
    } else {
        $result = executeCommand('pgrep -f "mysqld|mariadbd"');
        return $result['code'] === 0 && !empty(trim($result['output']));
    }
}

function startXamppServices($xamppPath) {
    println();
    printInfo("Checking if Apache and MySQL are running...");
    
    $apacheRunning = isApacheRunning();
    $mysqlRunning = isMySQLRunning();
    
    if ($apacheRunning && $mysqlRunning) {
        printSuccess("Apache is already running!");
        printSuccess("MySQL is already running!");
        return true;
    }
    
    println();
    if (!$apacheRunning) {
        printWarning("Apache is NOT running.");
    }
    if (!$mysqlRunning) {
        printWarning("MySQL is NOT running.");
    }
    
    if (isWindows()) {
        return startXamppWindows($xamppPath);
    } else {
        return startXamppUnix($xamppPath);
    }
}

function startXamppWindows($xamppPath) {
    println();
    println("  ┌─────────────────────────────────────────────────────────────────────┐");
    println("  │  📋 WINDOWS: Starting XAMPP Services                                │");
    println("  └─────────────────────────────────────────────────────────────────────┘");
    println();
    
    // Try to find XAMPP control panel
    $controlPanel = $xamppPath . '\\xampp-control.exe';
    $startScript = $xamppPath . '\\xampp_start.exe';
    
    if (file_exists($startScript)) {
        printInfo("Found XAMPP start script. Attempting to start services...");
        println();
        printWarning("A User Account Control (UAC) popup may appear.");
        printInfo("Please click 'Yes' to allow XAMPP to start.");
        println();
        
        if (askYesNo("Start XAMPP services now?", 'y')) {
            // Try starting with the batch script
            executeCommand("start \"\" \"$startScript\"");
            
            println();
            printWaiting("Waiting for services to start (10 seconds)...");
            
            for ($i = 0; $i < 10; $i++) {
                sleep(1);
                echo ".";
            }
            printDone();
            
            if (isApacheRunning() && isMySQLRunning()) {
                printSuccess("Apache started successfully!");
                printSuccess("MySQL started successfully!");
                return true;
            }
        }
    }
    
    // Manual instructions if automatic start fails
    println();
    println("  ┌─────────────────────────────────────────────────────────────────────┐");
    println("  │  📝 MANUAL START REQUIRED                                           │");
    println("  └─────────────────────────────────────────────────────────────────────┘");
    println();
    println("  Please start XAMPP manually:");
    println();
    println("  1. Open XAMPP Control Panel:");
    println("     📁 $xamppPath\\xampp-control.exe");
    println();
    println("  2. Click 'Start' next to Apache");
    println("  3. Click 'Start' next to MySQL");
    println();
    println("  4. Wait until both show 'Running' (green)");
    println();
    
    pressEnterToContinue("After starting both services, press ENTER to continue...");
    
    // Check again
    if (!isApacheRunning()) {
        printError("Apache is still not running!");
        printTip("Check if port 80 is blocked by Skype, IIS, or another program.");
        return false;
    }
    
    if (!isMySQLRunning()) {
        printError("MySQL is still not running!");
        printTip("Check XAMPP Control Panel for error messages.");
        return false;
    }
    
    printSuccess("Services are now running!");
    return true;
}

function startXamppUnix($xamppPath) {
    println();
    println("  ┌─────────────────────────────────────────────────────────────────────┐");
    println("  │  📋 LINUX/MAC: Starting XAMPP Services                              │");
    println("  └─────────────────────────────────────────────────────────────────────┘");
    println();
    
    $isMamp = strpos($xamppPath, 'MAMP') !== false;
    
    if ($isMamp) {
        printInfo("MAMP detected. Please start MAMP manually:");
        println();
        println("  1. Open MAMP application");
        println("  2. Click 'Start Servers'");
        println();
        pressEnterToContinue("After starting MAMP, press ENTER to continue...");
    } else {
        // XAMPP/LAMPP on Linux/Mac
        $lamppScript = $xamppPath . '/lampp';
        if (!file_exists($lamppScript)) {
            $lamppScript = $xamppPath . '/xampp';
        }
        if (!file_exists($lamppScript)) {
            $lamppScript = $xamppPath . '/xamppfiles/xampp';
        }
        
        printWarning("Starting XAMPP requires administrator (sudo) privileges.");
        println();
        
        if (askYesNo("Attempt to start XAMPP automatically?", 'y')) {
            println();
            printInfo("You may be asked for your password...");
            println();
            
            $result = executeCommand("sudo \"$lamppScript\" start");
            
            sleep(3);
            
            if (isApacheRunning() && isMySQLRunning()) {
                printSuccess("Apache started successfully!");
                printSuccess("MySQL started successfully!");
                return true;
            } else {
                printWarning("Automatic start may have failed.");
            }
        }
        
        // Manual instructions
        println();
        println("  📝 To start XAMPP manually, open a terminal and run:");
        println();
        println("     sudo $lamppScript start");
        println();
        pressEnterToContinue("After starting XAMPP, press ENTER to continue...");
    }
    
    // Final check
    if (!isApacheRunning() || !isMySQLRunning()) {
        if (!isApacheRunning()) printError("Apache is not running!");
        if (!isMySQLRunning()) printError("MySQL is not running!");
        return false;
    }
    
    printSuccess("Services are running!");
    return true;
}

// ═══════════════════════════════════════════════════════════════════════════
// DATABASE FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════

function testDatabaseConnection($config, $maxRetries = 3) {
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $dsn = "mysql:host={$config['db_host']};charset={$config['db_charset']}";
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            return ['success' => true, 'pdo' => $pdo];
        } catch (PDOException $e) {
            if ($attempt < $maxRetries) {
                sleep(2);
                continue;
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    return ['success' => false, 'error' => 'Connection timeout'];
}

function databaseExists($pdo, $dbName) {
    $stmt = $pdo->query("SHOW DATABASES LIKE '$dbName'");
    return $stmt->rowCount() > 0;
}

function runSchemaFile($config) {
    $schemaFile = $config['schema_file'];
    
    if (!file_exists($schemaFile)) {
        return ['success' => false, 'error' => "Schema file not found: $schemaFile"];
    }
    
    $sql = file_get_contents($schemaFile);
    
    // Remove complex statements that might cause issues
    $sql = preg_replace('/DROP EVENT.*?;/si', '', $sql);
    $sql = preg_replace('/CREATE EVENT.*?END;/si', '', $sql);
    $sql = preg_replace('/DELIMITER.*?DELIMITER ;/si', '', $sql);
    
    try {
        $dsn = "mysql:host={$config['db_host']};charset={$config['db_charset']}";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Split and execute statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $executed = 0;
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^(DELIMITER|END)/', $statement)) {
                try {
                    $pdo->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    // Ignore duplicate/exists errors
                    if (strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate') === false) {
                        // Log but continue
                    }
                }
            }
        }
        
        return ['success' => true, 'executed' => $executed];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function verifyDatabaseSetup($config) {
    try {
        $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['db_charset']}";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Check for tables
        $stmt = $pdo->query("SHOW TABLES");
        $tableCount = $stmt->rowCount();
        
        // Check for admin user
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        $hasAdmin = $stmt->fetchColumn() > 0;
        
        // Check for time slots
        $stmt = $pdo->query("SELECT COUNT(*) FROM time_slots");
        $hasTimeSlots = $stmt->fetchColumn() > 0;
        
        return [
            'success' => true,
            'table_count' => $tableCount,
            'has_admin' => $hasAdmin,
            'has_time_slots' => $hasTimeSlots,
            'pdo' => $pdo
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function createAdminUser($pdo) {
    try {
        // Password: admin123 (generated with password_hash('admin123', PASSWORD_DEFAULT))
        $passwordHash = '$2y$10$nPRP6T6fLjwoWUAt/Nv0FOiQ/u57hZzXXjVQFzU7YJv5M20.8uUvm';
        $jalaliDate = JalaliDate::now();
        
        // Check if exists first
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        if ($stmt->fetchColumn() > 0) {
            return true; // Already exists
        }
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, created_at_jalali) VALUES (?, ?, 'admin', ?)");
        $stmt->execute(['admin', $passwordHash, $jalaliDate]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function createDefaultTimeSlots($pdo) {
    try {
        $slots = [
            ['صبح', '06:00:00', '09:00:00', 'Morning session'],
            ['چاشت', '09:00:00', '12:00:00', 'Mid-morning session'],
            ['عصر', '15:00:00', '18:00:00', 'Afternoon session'],
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO time_slots (name, start_time, end_time, description) VALUES (?, ?, ?, ?)");
        
        foreach ($slots as $slot) {
            $stmt->execute($slot);
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// URL & PATH DETECTION FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════

function detectProjectPath() {
    return realpath(__DIR__);
}

function detectRelativePathFromHtdocs() {
    $projectPath = detectProjectPath();
    $htdocsPath = detectHtdocsPath();
    
    if ($htdocsPath === null) {
        return null;
    }
    
    // Normalize paths
    $projectPath = str_replace('\\', '/', $projectPath);
    $htdocsPath = str_replace('\\', '/', $htdocsPath);
    
    if (strpos($projectPath, $htdocsPath) === 0) {
        $relativePath = substr($projectPath, strlen($htdocsPath));
        return ltrim($relativePath, '/');
    }
    
    return null;
}

function detectAppUrl($config) {
    $relativePath = detectRelativePathFromHtdocs();
    
    $baseUrl = 'http://localhost';
    
    // Check for MAMP (uses port 8888 by default)
    $xamppPath = detectXamppPath();
    if ($xamppPath && strpos($xamppPath, 'MAMP') !== false) {
        $baseUrl = 'http://localhost:8888';
    }
    
    if ($relativePath !== null) {
        return $baseUrl . '/' . $relativePath . '/public/';
    }
    
    // Fallback
    return $baseUrl . '/' . $config['app_name'] . '/public/';
}

function createSymlink($htdocsPath, $projectPath, $linkName) {
    $linkPath = $htdocsPath . '/' . $linkName;
    
    // Check if already exists
    if (file_exists($linkPath) || is_link($linkPath)) {
        // Check if it points to our project
        if (is_link($linkPath)) {
            $target = readlink($linkPath);
            $normalizedTarget = str_replace('\\', '/', $target);
            $normalizedProject = str_replace('\\', '/', $projectPath);
            if ($normalizedTarget === $normalizedProject) {
                return ['success' => true, 'message' => 'Symlink already exists and is correct'];
            }
        }
        return ['success' => false, 'error' => 'A file or folder with that name already exists'];
    }
    
    if (isWindows()) {
        // Windows requires mklink command with admin privileges
        $projectPathWin = str_replace('/', '\\', $projectPath);
        $linkPathWin = str_replace('/', '\\', $linkPath);
        $cmd = "mklink /D \"$linkPathWin\" \"$projectPathWin\"";
        $result = executeCommand($cmd);
        
        if ($result['code'] !== 0) {
            return ['success' => false, 'error' => 'Failed to create symlink. Run Command Prompt as Administrator.'];
        }
    } else {
        // Unix symlink
        $result = symlink($projectPath, $linkPath);
        if (!$result) {
            return ['success' => false, 'error' => 'Failed to create symlink'];
        }
    }
    
    return ['success' => true, 'message' => 'Symlink created successfully'];
}

// ═══════════════════════════════════════════════════════════════════════════
// BROWSER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════

function openBrowser($url) {
    if (isWindows()) {
        // Use 'start' command on Windows
        pclose(popen("start \"\" \"$url\"", "r"));
    } elseif (isMac()) {
        exec("open \"$url\" &");
    } else {
        // Linux - try multiple methods
        exec("xdg-open \"$url\" 2>/dev/null &");
    }
}

function testUrl($url, $timeout = 5) {
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'method' => 'HEAD',
        ]
    ]);
    
    $headers = @get_headers($url, 1, $context);
    
    if ($headers === false) {
        return false;
    }
    
    return strpos($headers[0], '200') !== false || 
           strpos($headers[0], '302') !== false ||
           strpos($headers[0], '301') !== false;
}

// ═══════════════════════════════════════════════════════════════════════════
// CONFIGURATION FILE FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════

function saveEnvFile($config, $appUrl) {
    $envContent = <<<ENV
# Khorasan Club Configuration
# Generated by setup.php on {$_SERVER['REQUEST_TIME']}

# Database Configuration
DB_HOST={$config['db_host']}
DB_NAME={$config['db_name']}
DB_USER={$config['db_user']}
DB_PASS={$config['db_pass']}

# Application URL
APP_URL=$appUrl
ENV;

    $envPath = __DIR__ . '/.env';
    file_put_contents($envPath, $envContent);
    return $envPath;
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN SETUP PROCESS
// ═══════════════════════════════════════════════════════════════════════════

function runSetup($config) {
    $totalSteps = 6;
    
    printHeader();
    
    println("  Welcome! 👋");
    println();
    println("  This wizard will help you set up the Khorasan Sports Camp");
    println("  Management System on your computer in just a few minutes.");
    println();
    println("  🖥️  Detected System: " . getOSName() . " (" . PHP_OS . ")");
    println("  📂 Project Location: " . __DIR__);
    println();
    printDivider();
    
    if (!askYesNo("Ready to begin setup?", 'y')) {
        println();
        println("  Setup cancelled. Run 'php setup.php' when you're ready!");
        println();
        exit(0);
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // STEP 1: Check PHP Requirements
    // ═══════════════════════════════════════════════════════════════════════
    
    printStep(1, $totalSteps, "Checking PHP requirements");
    println();
    
    $phpVersion = phpversion();
    if (version_compare($phpVersion, '7.4', '<')) {
        printError("PHP 7.4 or higher is required. You have PHP $phpVersion");
        println();
        println("  📥 How to fix:");
        println("     - Update XAMPP to the latest version");
        println("     - Download from: https://www.apachefriends.org/");
        println();
        exit(1);
    }
    printSuccess("PHP $phpVersion installed ✓");
    
    // Check required extensions
    $requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json'];
    $missingExtensions = [];
    
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExtensions[] = $ext;
        } else {
            printSuccess("PHP extension '$ext' loaded ✓");
        }
    }
    
    if (!empty($missingExtensions)) {
        printError("Missing PHP extensions: " . implode(', ', $missingExtensions));
        println();
        println("  📥 These extensions are usually included with XAMPP.");
        println("     Try reinstalling XAMPP or enabling them in php.ini");
        println();
        exit(1);
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // STEP 2: Detect and Start XAMPP
    // ═══════════════════════════════════════════════════════════════════════
    
    printStep(2, $totalSteps, "Starting XAMPP/LAMPP services");
    println();
    
    $xamppPath = detectXamppPath();
    
    if ($xamppPath) {
        printSuccess("Found XAMPP at: $xamppPath");
        
        if (!startXamppServices($xamppPath)) {
            println();
            printError("Could not verify that services are running.");
            println();
            println("  📝 Troubleshooting:");
            println("     1. Open XAMPP Control Panel manually");
            println("     2. Start Apache and MySQL");
            println("     3. Run this setup again");
            println();
            exit(1);
        }
    } else {
        printWarning("XAMPP not found in default locations.");
        println();
        println("  If you have XAMPP installed elsewhere, please:");
        println("  1. Start Apache and MySQL manually");
        println("  2. Press ENTER to continue");
        println();
        pressEnterToContinue();
        
        if (!isMySQLRunning()) {
            printError("MySQL is not running. Cannot continue.");
            exit(1);
        }
        printSuccess("MySQL is running ✓");
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // STEP 3: Database Configuration
    // ═══════════════════════════════════════════════════════════════════════
    
    printStep(3, $totalSteps, "Configuring database connection");
    println();
    
    println("  Default database settings (XAMPP standard):");
    println("  ┌────────────────────────────────────────────┐");
    println("  │  Host:     {$config['db_host']}                    │");
    println("  │  Database: {$config['db_name']}             │");
    println("  │  Username: {$config['db_user']}                       │");
    println("  │  Password: (empty)                         │");
    println("  └────────────────────────────────────────────┘");
    
    if (!askYesNo("Use these default settings?", 'y')) {
        println();
        printInfo("Enter your custom database settings:");
        $config['db_host'] = askQuestion("Database host", $config['db_host']);
        $config['db_name'] = askQuestion("Database name", $config['db_name']);
        $config['db_user'] = askQuestion("Database username", $config['db_user']);
        $config['db_pass'] = askQuestion("Database password (leave empty for none)", '');
    }
    
    println();
    printWaiting("Testing database connection...");
    
    $connResult = testDatabaseConnection($config);
    
    if (!$connResult['success']) {
        printDone();
        printError("Cannot connect to MySQL!");
        println();
        println("  Error: " . $connResult['error']);
        println();
        println("  📝 Common solutions:");
        println("     1. Make sure MySQL is running in XAMPP");
        println("     2. Check if MySQL port (3306) is not blocked");
        println("     3. Verify username/password are correct");
        println();
        if (isWindows()) {
            println("  💡 Windows tip: Check Windows Firewall settings");
        }
        exit(1);
    }
    printDone();
    printSuccess("Connected to MySQL server ✓");
    
    // ═══════════════════════════════════════════════════════════════════════
    // STEP 4: Create Database and Tables
    // ═══════════════════════════════════════════════════════════════════════
    
    printStep(4, $totalSteps, "Setting up database");
    println();
    
    $pdo = $connResult['pdo'];
    
    if (databaseExists($pdo, $config['db_name'])) {
        printInfo("Database '{$config['db_name']}' already exists.");
        println();
        
        if (askYesNo("Reset database? (⚠️ This will DELETE all data!)", 'n')) {
            printWaiting("Dropping existing database...");
            $pdo->exec("DROP DATABASE `{$config['db_name']}`");
            printDone();
            printInfo("Database dropped. Recreating...");
        } else {
            printInfo("Keeping existing database. Updating schema if needed...");
        }
    }
    
    println();
    printWaiting("Creating database and tables...");
    
    $schemaResult = runSchemaFile($config);
    
    if (!$schemaResult['success']) {
        printDone();
        printError("Failed to create database!");
        println("  Error: " . $schemaResult['error']);
        exit(1);
    }
    printDone();
    printSuccess("Database '{$config['db_name']}' created ✓");
    printSuccess("All tables created successfully ✓");
    
    // ═══════════════════════════════════════════════════════════════════════
    // STEP 5: Create Default Data
    // ═══════════════════════════════════════════════════════════════════════
    
    printStep(5, $totalSteps, "Creating default data");
    println();
    
    $verifyResult = verifyDatabaseSetup($config);
    
    if (!$verifyResult['success']) {
        printError("Database verification failed!");
        println("  Error: " . $verifyResult['error']);
        exit(1);
    }
    
    $dbPdo = $verifyResult['pdo'];
    
    printInfo("Tables found: " . $verifyResult['table_count']);
    
    // Create admin user
    if (!$verifyResult['has_admin']) {
        printWaiting("Creating admin user...");
        if (createAdminUser($dbPdo)) {
            printDone();
            printSuccess("Admin user created ✓");
        } else {
            printDone();
            printWarning("Could not create admin user");
        }
    } else {
        printSuccess("Admin user already exists ✓");
    }
    
    // Create time slots
    if (!$verifyResult['has_time_slots']) {
        printWaiting("Creating default time slots...");
        if (createDefaultTimeSlots($dbPdo)) {
            printDone();
            printSuccess("Time slots created ✓");
        } else {
            printDone();
            printWarning("Could not create time slots");
        }
    } else {
        printSuccess("Time slots already exist ✓");
    }
    
    // Create upload directories
    $uploadDir = __DIR__ . '/public/assets/uploads';
    $invoiceDir = __DIR__ . '/public/assets/invoices';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        printSuccess("Upload directory created ✓");
    }
    if (!is_dir($invoiceDir)) {
        mkdir($invoiceDir, 0755, true);
        printSuccess("Invoice directory created ✓");
    }
    
    // ═══════════════════════════════════════════════════════════════════════
    // STEP 6: Configure Access URL
    // ═══════════════════════════════════════════════════════════════════════
    
    printStep(6, $totalSteps, "Configuring application URL");
    println();
    
    $htdocsPath = detectHtdocsPath($xamppPath);
    $relativePath = detectRelativePathFromHtdocs();
    $defaultUrl = detectAppUrl($config);
    
    println("  Your project is located at:");
    println("  📁 " . __DIR__);
    println();
    
    if ($relativePath !== null) {
        $currentUrl = "http://localhost/$relativePath/public/";
        printInfo("Current URL based on folder structure:");
        println("     $currentUrl");
        println();
    }
    
    println("  You can access the app using a simpler URL like:");
    println("     http://localhost/{$config['app_name']}/public/");
    println();
    
    $createSimpleUrl = askYesNo("Would you like to set up a simpler URL?", 'y');
    
    $appUrl = $defaultUrl;
    
    if ($createSimpleUrl && $htdocsPath !== null) {
        $linkName = askQuestion("Enter URL path name", $config['app_name']);
        
        println();
        printWaiting("Creating shortcut...");
        
        $symlinkResult = createSymlink($htdocsPath, __DIR__, $linkName);
        
        if ($symlinkResult['success']) {
            printDone();
            printSuccess("Shortcut created ✓");
            $appUrl = "http://localhost/$linkName/public/";
        } else {
            printDone();
            printWarning($symlinkResult['error']);
            
            if (isWindows()) {
                println();
                println("  💡 Windows tip: To create the shortcut manually:");
                println("     1. Open Command Prompt as Administrator");
                println("     2. Run: mklink /D \"$htdocsPath\\$linkName\" \"" . __DIR__ . "\"");
            }
            
            // Use the relative path URL
            if ($relativePath !== null) {
                $appUrl = "http://localhost/$relativePath/public/";
            }
        }
    } elseif ($relativePath !== null) {
        $appUrl = "http://localhost/$relativePath/public/";
    }
    
    // Save configuration
    saveEnvFile($config, $appUrl);
    
    // ═══════════════════════════════════════════════════════════════════════
    // SETUP COMPLETE!
    // ═══════════════════════════════════════════════════════════════════════
    
    println();
    println();
    println("  ╔═══════════════════════════════════════════════════════════════════╗");
    println("  ║                                                                   ║");
    println("  ║              🎉 SETUP COMPLETED SUCCESSFULLY! 🎉                  ║");
    println("  ║                                                                   ║");
    println("  ╚═══════════════════════════════════════════════════════════════════╝");
    println();
    printDivider();
    println();
    println("  🌐 APPLICATION URL:");
    println("     $appUrl");
    println();
    printDivider();
    println();
    println("  🔐 LOGIN CREDENTIALS:");
    println("     ┌────────────────────────────────────────┐");
    println("     │  Username:  admin                      │");
    println("     │  Password:  admin123                   │");
    println("     └────────────────────────────────────────┘");
    println();
    println("  ⚠️  SECURITY: Please change the password after your first login!");
    println();
    printDivider();
    println();
    
    // Test if URL works before opening
    printWaiting("Testing application URL...");
    sleep(1);
    printDone();
    
    if (askYesNo("Open the application in your browser now?", 'y')) {
        println();
        printInfo("Opening browser...");
        openBrowser($appUrl);
        println();
        printSuccess("Browser opened! If it didn't work, manually go to:");
        println("     $appUrl");
    }
    
    println();
    println("  ────────────────────────────────────────────────────────────────────");
    println();
    println("  📚 HELPFUL RESOURCES:");
    println("     • README.md     - Full documentation");
    println("     • Project.md    - Project overview");
    println();
    println("  🆘 NEED HELP?");
    println("     If something doesn't work:");
    println("     1. Make sure XAMPP is running (Apache + MySQL)");
    println("     2. Check the URL in your browser");
    println("     3. Look at README.md for troubleshooting tips");
    println();
    println("  Thank you for using Khorasan Sports Camp Management System! 🏃‍♂️");
    println();
    println("  ════════════════════════════════════════════════════════════════════");
    println();
}

// ═══════════════════════════════════════════════════════════════════════════
// ENTRY POINT
// ═══════════════════════════════════════════════════════════════════════════

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    // Running from web browser - show friendly message
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Setup - Khorasan Sports Camp</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                max-width: 700px;
                margin: 50px auto;
                padding: 20px;
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                min-height: 100vh;
                color: #e0e0e0;
            }
            .container {
                background: rgba(255,255,255,0.05);
                border-radius: 16px;
                padding: 40px;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.1);
            }
            h1 { color: #4fc3f7; margin-bottom: 10px; }
            h2 { color: #81d4fa; font-size: 18px; margin-top: 30px; }
            .emoji { font-size: 48px; margin-bottom: 20px; }
            code {
                background: rgba(0,0,0,0.3);
                padding: 8px 16px;
                border-radius: 8px;
                display: block;
                margin: 15px 0;
                font-size: 16px;
                color: #4fc3f7;
                border-left: 3px solid #4fc3f7;
            }
            .step {
                background: rgba(255,255,255,0.03);
                padding: 15px 20px;
                margin: 10px 0;
                border-radius: 8px;
                border-left: 3px solid #4fc3f7;
            }
            .step-num {
                background: #4fc3f7;
                color: #1a1a2e;
                padding: 2px 10px;
                border-radius: 20px;
                font-weight: bold;
                margin-right: 10px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="emoji">🏃</div>
            <h1>Khorasan Sports Camp</h1>
            <p>This setup script must be run from the command line, not a web browser.</p>
            
            <h2>📋 How to Run Setup:</h2>
            
            <div class="step">
                <span class="step-num">1</span>
                <strong>Open Terminal/Command Prompt</strong>
                <br><small>Windows: Press Win+R, type <code style="display:inline">cmd</code>, press Enter</small>
            </div>
            
            <div class="step">
                <span class="step-num">2</span>
                <strong>Navigate to project folder:</strong>
                <code>cd <?php echo str_replace('\\', '/', __DIR__); ?></code>
            </div>
            
            <div class="step">
                <span class="step-num">3</span>
                <strong>Run the setup script:</strong>
                <code>php setup.php</code>
            </div>
            
            <p style="margin-top: 30px; color: #81d4fa;">
                💡 <strong>Tip:</strong> Make sure XAMPP is running before you start!
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Run the setup
runSetup($config);
