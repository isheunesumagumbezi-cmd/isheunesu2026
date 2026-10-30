<?php
header('Content-Type: application/json');
include '../includes/config.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$conn = getDBConnection(); 

$accommodation_id = (int)$_POST['accommodation_id'];
$proposed_price = (float)$_POST['proposed_price'];
$message = sanitizeInput($_POST['message'] ?? '');

if ($proposed_price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid price']);
    exit();
}

// Get student ID
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$student_result = $stmt->get_result();
if (!$student_result || $student_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Student profile not found']);
    exit();
}
$student_id = $student_result->fetch_assoc()['id'];
$stmt->close();

// Get accommodation and landlord info
$stmt = $conn->prepare("SELECT a.landlord_id, a.title FROM accommodations a WHERE a.id = ?");
$stmt->bind_param("i", $accommodation_id);
$stmt->execute();
$accom_result = $stmt->get_result();
if (!$accom_result || $accom_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Accommodation not found']);
    exit();
}
$accom_data = $accom_result->fetch_assoc();
$stmt->close();

// Get landlord user ID
$stmt = $conn->prepare("SELECT user_id FROM landlords WHERE id = ?");
$stmt->bind_param("i", $accom_data['landlord_id']);
$stmt->execute();
$landlord_result = $stmt->get_result();
if (!$landlord_result || $landlord_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Landlord not found']);
    exit();
}
$landlord_user_id = $landlord_result->fetch_assoc()['user_id'];
$stmt->close();

// Create notification message
$notification_message = "Student proposed \${$proposed_price}/month for '{$accom_data['title']}'. Message: {$message}";
$notification_title = "Price Negotiation Request";

// Insert notification
$stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')");
$stmt->bind_param("iss", $landlord_user_id, $notification_title, $notification_message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Negotiation request sent']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send negotiation request']);
}
$stmt->close();
$conn->close();
?>