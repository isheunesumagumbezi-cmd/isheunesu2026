<?php
header('Content-Type: application/json');
include '../includes/config.php';
include '../includes/paynow.php';

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
ensureAccommodationPaymentColumns($conn);

$booking_id = (int)($_POST['booking_id'] ?? 0);
$booking_type = strtolower((string)($_POST['booking_type'] ?? 'accommodation'));
$payment_method = sanitizeInput($_POST['payment_method'] ?? 'ecocash');
$allowed_methods = ['ecocash', 'onemoney', 'telecash', 'card'];
$allowed_booking_types = ['accommodation', 'bus'];

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

if (!in_array($booking_type, $allowed_booking_types, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking type']);
    exit();
}

if (!in_array($payment_method, $allowed_methods, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
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

// Get booking details
if ($booking_type === 'bus') {
    $booking_stmt = $conn->prepare("
        SELECT bb.*, b.route AS title, b.price, u.name as student_name, u.email, u.phone
        FROM bus_bookings bb
        JOIN buses b ON bb.bus_id = b.id
        JOIN students s ON bb.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE bb.id = ? AND bb.student_id = ?
    ");
} else {
    $booking_stmt = $conn->prepare("
        SELECT ab.*, a.title, a.price, u.name as student_name, u.email, u.phone
        FROM accommodation_bookings ab
        JOIN accommodations a ON ab.accommodation_id = a.id
        JOIN students s ON ab.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE ab.id = ? AND ab.student_id = ?
    ");
}
$booking_stmt->bind_param("ii", $booking_id, $student_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if (!$booking_result || $booking_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit();
}

$booking = $booking_result->fetch_assoc();
$booking_stmt->close();

// Check if booking is already paid
if (!empty($booking['payment_status']) && $booking['payment_status'] === 'paid') {
    echo json_encode(['success' => false, 'message' => 'Booking is already paid']);
    exit();
}

if (!PayNowZimbabwe::hasConfiguredCredentials()) {
    echo json_encode([
        'success' => false,
        'message' => 'PayNow Zimbabwe is not configured. Please contact the platform administrator to enable the payment gateway.'
    ]);
    exit();
}

// Calculate amount (deposit or full payment)
$amount = $booking['total_amount'] ?? $booking['price'];

if ($booking_type === 'bus') {
    $amount = (float)($booking['total_amount'] ?? ($booking['price'] * ($booking['seats_booked'] ?? 1)));
} else {
    // For now, require full payment. In future, we can implement deposit system
    if (!$booking['total_amount']) {
        // Calculate total amount based on dates
        $check_in = new DateTime($booking['check_in_date']);
        $check_out = new DateTime($booking['check_out_date']);
        $days = $check_in->diff($check_out)->days;

        if ($days <= 0) $days = 1; // Minimum 1 day

        $amount = $booking['price'] * $days;

        // Update booking with calculated amount
        $update_stmt = $conn->prepare("UPDATE accommodation_bookings SET total_amount = ? WHERE id = ?");
        $update_stmt->bind_param("di", $amount, $booking_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
}

if ($booking_type === 'bus' && empty($booking['total_amount'])) {
    $update_stmt = $conn->prepare("UPDATE bus_bookings SET total_amount = ? WHERE id = ?");
    $update_stmt->bind_param("di", $amount, $booking_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Generate unique reference
$reference = 'SCC-' . $booking_id . '-' . time() . '-' . rand(100, 999);

// Create payment record
$conn->begin_transaction();

$payment_stmt = $conn->prepare("
    INSERT INTO payments (booking_id, booking_type, student_id, amount, currency, payment_method, reference_number, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
");

$currency = PAYMENT_CURRENCY;
$payment_stmt->bind_param("isidsss", $booking_id, $booking_type, $student_id, $amount, $currency, $payment_method, $reference);

if (!$payment_stmt->execute()) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to create payment record']);
    exit();
}

$payment_id = $payment_stmt->insert_id;
$payment_stmt->close();

$booking_table = $booking_type === 'bus' ? 'bus_bookings' : 'accommodation_bookings';
$booking_payment_stmt = $conn->prepare("
    UPDATE {$booking_table}
    SET payment_status = 'pending'
    WHERE id = ? AND payment_status <> 'paid'
");
$booking_payment_stmt->bind_param("i", $booking_id);

if (!$booking_payment_stmt->execute()) {
    $booking_payment_stmt->close();
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to mark booking as payment pending']);
    exit();
}

$booking_payment_stmt->close();
createReminderNotification($conn, $_SESSION['user_id'], 'Payment pending', 'Your accommodation payment is being processed. Please complete the payment to confirm your booking.', 'info');
$conn->commit();

// Initialize Pay Now Zimbabwe
$app_base_path = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
$paynow = new PayNowZimbabwe();

$additional_info = [
    'booking_id' => $booking_id,
    'student_name' => $booking['student_name'],
    'email' => $booking['email'],
    'phone' => $booking['phone'],
    'accommodation' => $booking['title'],
    'method' => $payment_method
];

$payment_data = $paynow->initiatePayment($amount, $reference, $additional_info);

// Make API call to Pay Now
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $paynow->getPaymentUrl());
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payment_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $callback_data = json_encode(['gateway_error' => curl_error($ch), 'failed_at' => date('c')]);
    $fail_payment = $conn->prepare("UPDATE payments SET status = 'failed', callback_data = ? WHERE id = ?");
    $fail_payment->bind_param("si", $callback_data, $payment_id);
    $fail_payment->execute();
    $fail_payment->close();

    $fail_booking = $conn->prepare("UPDATE accommodation_bookings SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'");
    $fail_booking->bind_param("i", $booking_id);
    $fail_booking->execute();
    $fail_booking->close();

    echo json_encode(['success' => false, 'message' => 'Payment gateway error: ' . curl_error($ch)]);
    curl_close($ch);
    exit();
}

curl_close($ch);

// Parse Pay Now response
parse_str($response, $response_data);
$response_status = $response_data['status'] ?? $response_data['Status'] ?? null;

if ($http_code == 200 && $response_status !== null && strcasecmp($response_status, 'Ok') === 0) {
    if (!$paynow->verifyCallback($response_data)) {
        $callback_data = json_encode(['gateway_error' => 'Invalid Pay Now response hash', 'response' => $response_data, 'failed_at' => date('c')]);

        $fail_payment = $conn->prepare("UPDATE payments SET status = 'failed', callback_data = ? WHERE id = ?");
        $fail_payment->bind_param("si", $callback_data, $payment_id);
        $fail_payment->execute();
        $fail_payment->close();

        $fail_booking = $conn->prepare("UPDATE {$booking_table} SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'");
        $fail_booking->bind_param("i", $booking_id);
        $fail_booking->execute();
        $fail_booking->close();

        echo json_encode(['success' => false, 'message' => 'Payment initiation failed: invalid payment gateway response']);
        $conn->close();
        exit();
    }

    $response_data = array_change_key_case($response_data, CASE_LOWER);

    // Update payment with transaction ID
    if (isset($response_data['pollurl'])) {
        $update_payment = $conn->prepare("UPDATE payments SET transaction_id = ? WHERE id = ?");
        $update_payment->bind_param("si", $response_data['pollurl'], $payment_id);
        $update_payment->execute();
        $update_payment->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment initiated successfully',
        'payment_url' => $response_data['browserurl'] ?? '',
        'poll_url' => $response_data['pollurl'] ?? '',
        'reference' => $reference,
        'amount' => $amount
    ]);
} else {
    // Payment initiation failed
    $response_data = array_change_key_case($response_data, CASE_LOWER);
    $error_msg = $response_data['error'] ?? 'Unknown error';
    $callback_data = json_encode(['gateway_error' => $error_msg, 'response' => $response_data, 'failed_at' => date('c')]);

    $fail_payment = $conn->prepare("UPDATE payments SET status = 'failed', callback_data = ? WHERE id = ?");
    $fail_payment->bind_param("si", $callback_data, $payment_id);
    $fail_payment->execute();
    $fail_payment->close();

    $fail_booking = $conn->prepare("UPDATE accommodation_bookings SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'");
    $fail_booking->bind_param("i", $booking_id);
    $fail_booking->execute();
    $fail_booking->close();

    echo json_encode(['success' => false, 'message' => 'Payment initiation failed: ' . $error_msg]);
}

$conn->close();
?>
