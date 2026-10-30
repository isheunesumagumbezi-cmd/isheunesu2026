<?php
header('Content-Type: application/json');
include '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$csrfToken = $input['csrf_token'] ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid accommodation ID']);
    exit();
}

if (!validateCSRFToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
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

// First, get the accommodation to check ownership and get images for cleanup
$query = "SELECT images FROM accommodations WHERE id = ? AND landlord_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $id, $landlord_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Accommodation not found or access denied']);
    exit();
}

$row = $result->fetch_assoc();
$images = json_decode($row['images'] ?? '[]', true);
$stmt->close();

// Delete the accommodation
$query = "DELETE FROM accommodations WHERE id = ? AND landlord_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $id, $landlord_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Clean up image files
        foreach ($images as $image) {
            $file_path = realpath(__DIR__ . '/../' . ltrim($image, '/\\'));
            $root_path = realpath(__DIR__ . '/..');
            if ($file_path && $root_path && strpos($file_path, $root_path) === 0 && file_exists($file_path)) {
                unlink($file_path);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Accommodation deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Accommodation not found or access denied']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting accommodation']);
}

$stmt->close();
$conn->close();
?>
