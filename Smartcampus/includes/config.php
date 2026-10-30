<?php
function envValue($key, $default = '') {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

function envBool($key, $default = false) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
}

function isSecureRequest() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || strpos(APP_URL, 'https://') === 0;
}

define('APP_ENV', envValue('APP_ENV', 'local'));
define('APP_URL', rtrim(envValue('APP_URL', 'http://localhost/Smartcampus'), '/'));
define('APP_DEBUG', envBool('APP_DEBUG', APP_ENV !== 'production'));

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

// Database configuration
define('DB_HOST', envValue('DB_HOST', 'localhost'));
define('DB_USER', envValue('DB_USER', 'root')); // Default XAMPP user
define('DB_PASS', envValue('DB_PASS', '')); // Default XAMPP password
define('DB_NAME', envValue('DB_NAME', 'smartcampus'));
define('DB_PORT', (int) envValue('DB_PORT', '3307')); // XAMPP MySQL port

// Google Maps API Key
// Get your API key from: https://console.cloud.google.com/
// Instructions: 
// 1. Create a Google Cloud project
// 2. Enable Maps JavaScript API
// 3. Create an API key (Credentials > Create Credentials > API Key)
// 4. Add your key below (replace YOUR_GOOGLE_MAPS_API_KEY_HERE)
define('GOOGLE_MAPS_API_KEY', envValue('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY_HERE'));

// Pay Now Zimbabwe Configuration
// Get your credentials from: https://paynow.co.zw/
// Instructions:
// 1. Sign up for a Pay Now Zimbabwe merchant account
// 2. Get your integration ID and key
// 3. Configure your callback URLs
define('PAYNOW_INTEGRATION_ID', envValue('PAYNOW_INTEGRATION_ID', 'YOUR_PAYNOW_INTEGRATION_ID'));
define('PAYNOW_INTEGRATION_KEY', envValue('PAYNOW_INTEGRATION_KEY', 'YOUR_PAYNOW_INTEGRATION_KEY'));
define('PAYNOW_RETURN_URL', envValue('PAYNOW_RETURN_URL', APP_URL . '/payment_success.php'));
define('PAYNOW_RESULT_URL', envValue('PAYNOW_RESULT_URL', APP_URL . '/api/paynow_callback.php'));
define('PAYNOW_SANDBOX', envBool('PAYNOW_SANDBOX', true)); // Set to false for production

// Payment Configuration
define('PAYMENT_CURRENCY', envValue('PAYMENT_CURRENCY', 'USD'));
define('PAYMENT_DEPOSIT_PERCENTAGE', (int) envValue('PAYMENT_DEPOSIT_PERCENTAGE', '30')); // 30% deposit required
define('PAYMENT_TIMEOUT_MINUTES', (int) envValue('PAYMENT_TIMEOUT_MINUTES', '15')); // Payment timeout in minutes

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    ensureAccommodationPaymentColumns($conn);
    ensureBusPaymentColumns($conn);
    ensurePaymentColumns($conn);
    ensureLandlordVerificationColumns($conn);
    return $conn;
}

function ensureColumnExists($conn, $table, $column, $definition) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc()['count'] > 0;
    $stmt->close();

    if (!$exists) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function ensureAccommodationPaymentColumns($conn) {
    ensureColumnExists($conn, 'accommodation_bookings', 'payment_status', "ENUM('unpaid', 'pending', 'paid', 'failed') DEFAULT 'unpaid'");
    ensureColumnExists($conn, 'accommodation_bookings', 'total_amount', 'DECIMAL(10,2) NULL');
}

function ensureBusPaymentColumns($conn) {
    ensureColumnExists($conn, 'bus_bookings', 'payment_status', "ENUM('unpaid', 'pending', 'paid', 'failed') DEFAULT 'unpaid'");
    ensureColumnExists($conn, 'bus_bookings', 'total_amount', 'DECIMAL(10,2) NULL');
}

function ensurePaymentColumns($conn) {
    ensureColumnExists($conn, 'payments', 'booking_type', "ENUM('accommodation', 'bus') NOT NULL DEFAULT 'accommodation'");
}

function ensureLandlordVerificationColumns($conn) {
    ensureColumnExists($conn, 'landlords', 'verification_status', "ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending'");
    ensureColumnExists($conn, 'landlords', 'verification_notes', 'TEXT NULL');
    ensureColumnExists($conn, 'landlords', 'national_id_path', 'VARCHAR(255) NULL');
    ensureColumnExists($conn, 'landlords', 'proof_of_residence_path', 'VARCHAR(255) NULL');
    ensureColumnExists($conn, 'landlords', 'submitted_at', 'DATETIME NULL');
    ensureColumnExists($conn, 'landlords', 'verified_at', 'DATETIME NULL');
}

function createReminderNotification($conn, $userId, $title, $message, $type = 'info') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    $stmt->execute();
    $stmt->close();
}

function ensureAuthColumns($conn) {
    ensureColumnExists($conn, 'users', 'failed_login_attempts', 'INT NOT NULL DEFAULT 0');
    ensureColumnExists($conn, 'users', 'locked_until', 'DATETIME NULL');
    ensureColumnExists($conn, 'users', 'reset_token_hash', 'VARCHAR(255) NULL');
    ensureColumnExists($conn, 'users', 'reset_token_expires_at', 'DATETIME NULL');
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function countRows($conn, $sql, $types = '', ...$params) {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    return $count;
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    $secureCookie = isSecureRequest();
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $secureCookie, true);
    }
    session_start();
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;

    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    return $user;
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function sanitizeInput($data) {
    if ($data === null) {
        return '';
    }

    $data = trim((string)$data);
    $data = stripslashes($data);
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

function isStrongPassword($password) {
    $password = (string)$password;

    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^A-Za-z\d]/', $password);
}

// CSRF protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function ensureAuthSecuritySchema($conn) {
    $columns = [
        'failed_login_attempts' => "ALTER TABLE users ADD COLUMN failed_login_attempts INT NOT NULL DEFAULT 0",
        'locked_until' => "ALTER TABLE users ADD COLUMN locked_until DATETIME NULL",
        'password_reset_at' => "ALTER TABLE users ADD COLUMN password_reset_at DATETIME NULL"
    ];

    foreach ($columns as $column => $alterSql) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as count
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?"
        );
        $stmt->bind_param("s", $column);
        $stmt->execute();
        $exists = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

        if ($exists === 0) {
            $conn->query($alterSql);
        }
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token_hash (token_hash),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
}

function ensureUploadDirectory($directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    return $directory;
}

function validateUploadedFile(array $file, array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], int $maxSizeBytes = 2 * 1024 * 1024) {
    if (!isset($file['error']) || !is_array($file)) {
        return ['ok' => false, 'error' => 'Invalid file upload.'];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file was uploaded.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'The file upload failed.'];
    }

    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok' => false, 'error' => 'The uploaded file is invalid.'];
    }

    if (($file['size'] ?? 0) > $maxSizeBytes) {
        return ['ok' => false, 'error' => 'The file is too large. Maximum size is 2MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    if ($mimeType === false || !in_array($mimeType, $allowedMimeTypes, true)) {
        return ['ok' => false, 'error' => 'Only JPEG, PNG, WEBP, and PDF files are allowed.'];
    }

    return ['ok' => true, 'mime_type' => $mimeType];
}

function storeUploadedFile(array $file, string $directory, string $prefix = 'upload') {
    $directory = ensureUploadDirectory($directory);
    $extension = strtolower(pathinfo($file['name'] ?? 'upload', PATHINFO_EXTENSION));
    $safeExtension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true) ? $extension : 'bin';
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safeExtension;
    $targetPath = rtrim($directory, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['ok' => false, 'error' => 'The file could not be stored.'];
    }

    return ['ok' => true, 'path' => $targetPath];
}

function normalizeStoredImageUrl($image) {
    $image = trim((string)$image);
    if ($image === '') {
        return 'https://images.unsplash.com/photo-1560185127-6ed189bf02f4?auto=format&fit=crop&w=900&q=80';
    }
    if (preg_match('#^(https?:)?//#i', $image) || strpos($image, 'data:') === 0) {
        return $image;
    }
    if (strpos($image, '/') === 0) {
        return APP_URL . $image;
    }
    return APP_URL . '/' . ltrim($image, '/');
}
?>
