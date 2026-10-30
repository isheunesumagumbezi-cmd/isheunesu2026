<?php
include '../includes/config.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin.php');
    exit();
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Location: ../admin.php');
    exit();
}

$conn = getDBConnection();
$landlord_id = (int)($_POST['landlord_id'] ?? 0);
$decision = sanitizeInput($_POST['decision'] ?? 'rejected');
$notes = sanitizeInput($_POST['notes'] ?? '');

if ($landlord_id <= 0 || !in_array($decision, ['verified', 'rejected'], true)) {
    header('Location: ../admin.php');
    exit();
}

$stmt = $conn->prepare("SELECT user_id FROM landlords WHERE id = ?");
$stmt->bind_param('i', $landlord_id);
$stmt->execute();
$landlord_result = $stmt->get_result();
if ($landlord_result->num_rows === 0) {
    header('Location: ../admin.php');
    exit();
}
$landlord_user_id = $landlord_result->fetch_assoc()['user_id'];
$stmt->close();

$stmt = $conn->prepare("UPDATE landlords SET verification_status = ?, verification_notes = ?, verified_at = CASE WHEN ? = 'verified' THEN NOW() ELSE NULL END WHERE id = ?");
$stmt->bind_param('sssi', $decision, $notes, $decision, $landlord_id);
$stmt->execute();
$stmt->close();

createReminderNotification($conn, $landlord_user_id, 'Verification update', $decision === 'verified' ? 'Your landlord verification has been approved.' : 'Your landlord verification was rejected. Please upload clearer documents.', $decision === 'verified' ? 'success' : 'warning');

$conn->close();
header('Location: ../admin.php');
exit();
