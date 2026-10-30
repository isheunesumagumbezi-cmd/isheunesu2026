<?php
header('Content-Type: application/json');
include '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$available = (bool)($input['available'] ?? false);

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid accommodation ID']);
    exit();
}

$conn = getDBConnection();

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

// Verify ownership and update
$query = "UPDATE accommodations SET available = ? WHERE id = ? AND landlord_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $available, $id, $landlord_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Accommodation status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Accommodation not found or access denied']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating accommodation status']);
}

$stmt->close();
$conn->close();
?>