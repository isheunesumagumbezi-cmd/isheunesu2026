<?php
header('Content-Type: application/json');
include '../includes/config.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND read_status = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unread = $result->fetch_assoc()['unread'];
$stmt->close();
$conn->close();

echo json_encode(['unread' => $unread]);
?>