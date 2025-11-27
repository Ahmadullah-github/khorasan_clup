<?php
/**
 * Script to add test users to the Sports Camp Management System
 * Run this script once to add the test users
 * 
 * Usage via browser: http://localhost:8080/add_test_users.php
 * Or via Docker: docker exec sports-camp-php php /var/www/api/add_test_users.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

$db = Database::getInstance()->getConnection();

// Get current Jalali date
$now = new DateTime();
$jalali = JalaliDate::gregorianToJalali(
    (int)$now->format('Y'),
    (int)$now->format('m'),
    (int)$now->format('d')
);
$jalaliDate = sprintf('%04d-%02d-%02d', $jalali[0], $jalali[1], $jalali[2]);

// Test users to add
$users = [
    // Admin users
    [
        'username' => 'sardar_ahadi',
        'password' => 'Admin123!',
        'role' => 'admin',
        'display_name' => 'Sardar Ahadi'
    ],
    [
        'username' => 'kamran_kabuly',
        'password' => 'Admin123!',
        'role' => 'admin',
        'display_name' => 'Kamran Kabuly'
    ],
    [
        'username' => 'ahmadullah_ahmadi',
        'password' => 'Admin123!',
        'role' => 'admin',
        'display_name' => 'Ahmadullah Ahmadi'
    ],
    // Staff user
    [
        'username' => 'shafiq_murid',
        'password' => 'Staff123!',
        'role' => 'staff',
        'display_name' => 'Shafiq Murid'
    ]
];

$results = [];
$errors = [];

foreach ($users as $userData) {
    try {
        // Check if user already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$userData['username']]);
        if ($stmt->fetch()) {
            $errors[] = "User '{$userData['username']}' already exists. Skipping.";
            continue;
        }

        // Hash password
        $passwordHash = Password::hash($userData['password']);

        // Insert user
        $stmt = $db->prepare("
            INSERT INTO users (username, password_hash, role, created_at_jalali)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $userData['username'],
            $passwordHash,
            $userData['role'],
            $jalaliDate
        ]);

        $userId = $db->lastInsertId();
        $results[] = [
            'id' => $userId,
            'username' => $userData['username'],
            'display_name' => $userData['display_name'],
            'role' => $userData['role'],
            'password' => $userData['password'],
            'status' => 'created'
        ];
    } catch (Exception $e) {
        $errors[] = "Error creating user '{$userData['username']}': " . $e->getMessage();
    }
}

// Output results
if (php_sapi_name() === 'cli') {
    // Command line output
    echo "\n=== Test Users Creation Results ===\n\n";
    
    if (!empty($results)) {
        echo "Successfully created users:\n";
        echo str_repeat("-", 80) . "\n";
        printf("%-20s %-25s %-10s %-15s\n", "Username", "Display Name", "Role", "Password");
        echo str_repeat("-", 80) . "\n";
        foreach ($results as $result) {
            printf("%-20s %-25s %-10s %-15s\n", 
                $result['username'], 
                $result['display_name'],
                $result['role'],
                $result['password']
            );
        }
        echo str_repeat("-", 80) . "\n\n";
    }
    
    if (!empty($errors)) {
        echo "Errors/Warnings:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
        echo "\n";
    }
} else {
    // Web output
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Test Users Created</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { padding: 20px; background: #f5f5f5; }
            .card { margin-top: 20px; }
            table { margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Test Users Creation Results</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($results)): ?>
                        <h5 class="text-success">Successfully Created Users:</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Username</th>
                                        <th>Display Name</th>
                                        <th>Role</th>
                                        <th>Password</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($result['username']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($result['display_name']); ?></td>
                                            <td><span class="badge bg-<?php echo $result['role'] === 'admin' ? 'danger' : 'secondary'; ?>">
                                                <?php echo $result['role'] === 'admin' ? 'Admin' : 'Staff'; ?>
                                            </span></td>
                                            <td><code><?php echo htmlspecialchars($result['password']); ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-warning mt-3">
                            <h5>Warnings/Errors:</h5>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info mt-3">
                        <strong>Note:</strong> Please save these credentials. You can delete this file (api/add_test_users.php) after use for security.
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

