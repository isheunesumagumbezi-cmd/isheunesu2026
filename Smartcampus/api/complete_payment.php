<?php
include '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit();
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token.');
}

$reference = sanitizeInput($_POST['reference'] ?? '');
$paymentCode = trim($_POST['payment_code'] ?? '');

if ($reference === '') {
    header('Location: ../payment_success.php?reference=' . urlencode($reference) . '&status=failed');
    exit();
}

$conn = getDBConnection();
ensureAccommodationPaymentColumns($conn);

if (strlen($paymentCode) < 4) {
    $stmt = $conn->prepare("
        SELECT p.id, p.booking_id
        FROM payments p
        JOIN students s ON p.student_id = s.id
        WHERE p.reference_number = ? AND s.user_id = ? AND p.status = 'pending'
    ");
    $stmt->bind_param("si", $reference, $_SESSION['user_id']);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($payment) {
        $callbackData = json_encode([
            'gateway' => 'local',
            'error' => 'Confirmation code was too short',
            'processed_at' => date('c')
        ]);

        $stmt = $conn->prepare("UPDATE payments SET status = 'failed', callback_data = ?, payment_date = NOW() WHERE id = ?");
        $stmt->bind_param("si", $callbackData, $payment['id']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE accommodation_bookings SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'");
        $stmt->bind_param("i", $payment['booking_id']);
        $stmt->execute();
        $stmt->close();
    }

    $conn->close();
    header('Location: ../payment_success.php?reference=' . urlencode($reference) . '&status=failed');
    exit();
}

$conn->begin_transaction();

$stmt = $conn->prepare("
    SELECT p.id, p.booking_id, p.student_id, p.amount, p.status
    FROM payments p
    JOIN students s ON p.student_id = s.id
    WHERE p.reference_number = ? AND s.user_id = ?
    FOR UPDATE
");
$stmt->bind_param("si", $reference, $_SESSION['user_id']);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    $conn->rollback();
    $conn->close();
    header('Location: ../dashboard.php');
    exit();
}

if ($payment['status'] === 'completed') {
    $conn->commit();
    $conn->close();
    header('Location: ../payment_success.php?reference=' . urlencode($reference) . '&status=completed');
    exit();
}

$callbackData = json_encode([
    'gateway' => 'local',
    'confirmation_code' => substr(hash('sha256', $paymentCode), 0, 16),
    'processed_at' => date('c')
]);

$stmt = $conn->prepare("UPDATE payments SET status = 'completed', callback_data = ?, payment_date = NOW() WHERE id = ?");
$stmt->bind_param("si", $callbackData, $payment['id']);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("UPDATE accommodation_bookings SET payment_status = 'paid', status = 'confirmed' WHERE id = ?");
$stmt->bind_param("i", $payment['booking_id']);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("
    INSERT INTO notifications (user_id, title, message, type)
    VALUES (?, 'Payment Successful', ?, 'success')
");
$message = 'Your accommodation payment of $' . number_format((float)$payment['amount'], 2) . ' has been processed.';
$stmt->bind_param("is", $_SESSION['user_id'], $message);
$stmt->execute();
$stmt->close();
createReminderNotification($conn, $_SESSION['user_id'], 'Booking confirmed', 'Your accommodation booking is confirmed. Please keep your payment reference safe.', 'success');

$conn->commit();
$conn->close();
header('Location: ../payment_success.php?reference=' . urlencode($reference) . '&status=completed');
exit();
?>
