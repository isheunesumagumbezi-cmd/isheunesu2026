<?php
/**
 * Smart Campus Connect - System Health Check
 * Run this to verify all components are working correctly
 */

include 'includes/config.php';

// Start the health check
echo "==================================================\n";
echo "Smart Campus Connect - System Health Check\n";
echo "==================================================\n\n";

// Check 1: Database Connection
echo "[1/7] Checking Database Connection...";
try {
    $conn = getDBConnection();
    echo " ✓ PASS\n";
    $conn->close();
} catch (Exception $e) {
    echo " ✗ FAIL - {$e->getMessage()}\n";
    exit(1);
}

// Check 2: Required Tables
echo "[2/7] Checking Required Tables...";
$conn = getDBConnection();
$tables = [
    'users', 'students', 'landlords', 'accommodations', 
    'accommodation_bookings', 'notifications'
];
$missing = [];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows == 0) {
        $missing[] = $table;
    }
}

if (empty($missing)) {
    echo " ✓ PASS\n";
} else {
    echo " ✗ FAIL - Missing tables: " . implode(', ', $missing) . "\n";
    echo "   Run: mysql -u root smartcampus < db_schema.sql\n";
}
$conn->close();

// Check 3: Upload Directories
echo "[3/7] Checking Upload Directories...";
$dirs = ['uploads', 'uploads/accommodations', 'uploads/buses'];
$missing_dirs = [];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        $missing_dirs[] = $dir;
    }
}

if (empty($missing_dirs)) {
    echo " ✓ PASS\n";
} else {
    echo " ⚠ WARNING - Creating missing directories...\n";
    foreach ($missing_dirs as $dir) {
        @mkdir($dir, 0755, true);
        echo "   Created: $dir\n";
    }
}

// Check 4: File Permissions
echo "[4/7] Checking File Permissions...";
$uploads_writable = is_writable('uploads');
if ($uploads_writable) {
    echo " ✓ PASS\n";
} else {
    echo " ⚠ WARNING - uploads directory may not be writable\n";
    echo "   Run: chmod 755 uploads/\n";
}

// Check 5: Session Support
echo "[5/7] Checking Session Support...";
if (session_status() != PHP_SESSION_NONE) {
    echo " ✓ PASS\n";
} else {
    echo " ✗ FAIL - Session not started\n";
}

// Check 6: CSRF Token Generation
echo "[6/7] Checking CSRF Token Generation...";
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$token = generateCSRFToken();
if (!empty($token) && strlen($token) > 0) {
    echo " ✓ PASS\n";
} else {
    echo " ✗ FAIL - Could not generate CSRF token\n";
}

// Check 7: Key Files
echo "[7/7] Checking Key Files Exist...";
$files = [
    'includes/config.php',
    'includes/header.php',
    'includes/footer.php',
    'accommodation.php',
    'add_accommodation.php',
    'manage_bookings.php',
    'my_bookings.php',
    'api/book_accommodation.php',
    'api/negotiate_price.php',
    'dashboard.php',
    'login.php',
    'register.php'
];

$missing_files = [];
foreach ($files as $file) {
    if (!file_exists($file)) {
        $missing_files[] = $file;
    }
}

if (empty($missing_files)) {
    echo " ✓ PASS\n";
} else {
    echo " ✗ FAIL - Missing files: " . implode(', ', $missing_files) . "\n";
}

// Summary
echo "\n==================================================\n";
echo "Health Check Complete!\n";
echo "==================================================\n\n";

// Database Statistics
$conn = getDBConnection();
$queries = [
    'users' => 'SELECT COUNT(*) as count FROM users',
    'accommodations' => 'SELECT COUNT(*) as count FROM accommodations',
    'bookings' => 'SELECT COUNT(*) as count FROM accommodation_bookings',
    'students' => 'SELECT COUNT(*) as count FROM students',
    'landlords' => 'SELECT COUNT(*) as count FROM landlords'
];

echo "Database Statistics:\n";
foreach ($queries as $name => $query) {
    $result = $conn->query($query);
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "  - $name: $count\n";
    }
}
$conn->close();

echo "\nNext Steps:\n";
echo "1. Set up a test account: php register.php\n";
echo "2. Login: http://localhost/Smartcampus/login.php\n";
echo "3. For landlords: Add accommodation at add_accommodation.php\n";
echo "4. For students: Browse accommodations at accommodation.php\n";
echo "5. View your bookings at my_bookings.php\n\n";

echo "For detailed setup, see README.md\n";
?>
