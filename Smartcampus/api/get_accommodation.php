<?php
header('Content-Type: application/json');
include '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid accommodation ID']);
    exit();
}

$conn = getDBConnection();
$accommodation_id = (int)$_GET['id'];

// Check if user is logged in and is a landlord
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user = getCurrentUser();
if ($user['role'] !== 'landlord') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$stmt = $conn->prepare("SELECT id FROM landlords WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$landlord_id = $result->fetch_assoc()['id'];
$stmt->close();

// Get accommodation and verify ownership
$query = "SELECT * FROM accommodations WHERE id = ? AND landlord_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $accommodation_id, $landlord_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Accommodation not found']);
    exit();
}

$accommodation = $result->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'accommodation' => $accommodation
]);
?>