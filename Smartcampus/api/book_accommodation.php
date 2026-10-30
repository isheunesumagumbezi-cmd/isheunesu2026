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

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$conn = getDBConnection();

$accommodation_id = (int)($_POST['accommodation_id'] ?? 0);
$check_in_date = sanitizeInput($_POST['check_in_date'] ?? '');
$check_out_date = sanitizeInput($_POST['check_out_date'] ?? '');
$notes = sanitizeInput($_POST['notes'] ?? '');

// Validation
if ($accommodation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid accommodation ID']);
    exit();
}

if (empty($check_in_date) || empty($check_out_date)) {
    echo json_encode(['success' => false, 'message' => 'Check-in and check-out dates are required']);
    exit();
}

// Validate dates format and logic
if (!strtotime($check_in_date) || !strtotime($check_out_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

if (strtotime($check_out_date) <= strtotime($check_in_date)) {
    echo json_encode(['success' => false, 'message' => 'Check-out date must be after check-in date']);
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

// Check if accommodation exists and is available
$stmt = $conn->prepare("SELECT id, available FROM accommodations WHERE id = ?");
$stmt->bind_param("i", $accommodation_id);
$stmt->execute();
$accom_result = $stmt->get_result();
if (!$accom_result || $accom_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Accommodation not found']);
    exit();
}

$accom_data = $accom_result->fetch_assoc();
if (!$accom_data['available']) {
    echo json_encode(['success' => false, 'message' => 'Accommodation is not available']);
    exit();
}
$stmt->close();

// Check for overlapping bookings
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM accommodation_bookings 
    WHERE accommodation_id = ? 
    AND status IN ('pending', 'confirmed')
    AND (
        (check_in_date <= ? AND check_out_date >= ?)
    )
");
$stmt->bind_param("iss", $accommodation_id, $check_out_date, $check_in_date);
$stmt->execute();
$overlap_check = $stmt->get_result();

if ($overlap_check && $overlap_check->fetch_assoc()['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'This accommodation is already booked for the selected dates']);
    exit();
}
$stmt->close();

// Insert booking
$stmt = $conn->prepare("
    INSERT INTO accommodation_bookings (student_id, accommodation_id, check_in_date, check_out_date, notes, status)
    VALUES (?, ?, ?, ?, ?, 'pending')
");

$stmt->bind_param("iisss", $student_id, $accommodation_id, $check_in_date, $check_out_date, $notes);

if ($stmt->execute()) {
    $booking_id = $stmt->insert_id;
    
    // Get landlord info for notification
    $stmt = $conn->prepare("
        SELECT l.user_id, l.verification_status FROM landlords l 
        JOIN accommodations a ON a.landlord_id = l.id 
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $accommodation_id);
    $stmt->execute();
    $landlord_result = $stmt->get_result();
    
    if ($landlord_result && $landlord_result->num_rows > 0) {
        $landlord_data = $landlord_result->fetch_assoc();
        $landlord_user_id = $landlord_data['user_id'];
        
        $notification_title = "New Booking Request";
        $notification_message = "A student has booked your accommodation from {$check_in_date} to {$check_out_date}";
        
        $notif_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            VALUES (?, ?, ?, 'info')
        ");
        $notif_stmt->bind_param("iss", $landlord_user_id, $notification_title, $notification_message);
        $notif_stmt->execute();
        $notif_stmt->close();

        if (($landlord_data['verification_status'] ?? 'pending') !== 'verified') {
            createReminderNotification($conn, $landlord_user_id, 'Verification required', 'Please upload your national ID and proof of residence to make your accommodation listings active.', 'warning');
        }
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Booking request submitted successfully', 'booking_id' => $booking_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create booking: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
