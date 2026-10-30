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

$bus_id = (int)($_POST['bus_id'] ?? 0);
$seats = (int)($_POST['seats'] ?? 0);

if ($seats < 1 || $seats > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid number of seats']);
    exit();
}

if ($bus_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid bus selection']);
    exit();
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Student profile not found']);
    exit();
}

$student_id = (int)$student['id'];

// Check available seats and route price
$stmt = $conn->prepare("SELECT available_seats, price, route FROM buses WHERE id = ?");
$stmt->bind_param("i", $bus_id);
$stmt->execute();
$result = $stmt->get_result();
$bus = $result->fetch_assoc();
$stmt->close();

if (!$bus || $bus['available_seats'] < $seats) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Not enough seats available']);
    exit();
}

$total_amount = (float)$bus['price'] * (int)$seats;

// Book the seats
$stmt = $conn->prepare("INSERT INTO bus_bookings (student_id, bus_id, seats_booked, total_amount, payment_status) VALUES (?, ?, ?, ?, 'pending')");
$stmt->bind_param("iidd", $student_id, $bus_id, $seats, $total_amount);
$success = $stmt->execute();
$booking_id = $conn->insert_id;
$stmt->close();

// Update available seats
if ($success) {
    $stmt = $conn->prepare("UPDATE buses SET available_seats = available_seats - ? WHERE id = ?");
    $stmt->bind_param("ii", $seats, $bus_id);
    $stmt->execute();
    $stmt->close();

    // Send notification
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Bus Booking Confirmed', CONCAT('Your booking for ', ?, ' seats has been confirmed. Booking ID: ', ?), 'success')");
    $stmt->bind_param("isi", $_SESSION['user_id'], $seats, $booking_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Booking confirmed',
        'booking_id' => $booking_id,
        'total_amount' => number_format((float)$total_amount, 2, '.', ''),
        'route' => $bus['route']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to book seats']);
}
?>
