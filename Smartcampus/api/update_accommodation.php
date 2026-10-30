<?php
header('Content-Type: application/json');
include '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
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

// Get form data
$id = (int)($_POST['id'] ?? 0);
$title = sanitizeInput($_POST['title'] ?? '');
$description = sanitizeInput($_POST['description'] ?? '');
$price = (float)($_POST['price'] ?? 0);
$location = sanitizeInput($_POST['location'] ?? '');
$latitude = (float)($_POST['latitude'] ?? 0);
$longitude = (float)($_POST['longitude'] ?? 0);
$amenities = sanitizeInput($_POST['amenities'] ?? '');
$current_images = json_decode($_POST['current_images'] ?? '[]', true);

// Validate inputs
if (empty($id) || empty($title) || empty($price) || empty($location)) {
    echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
    exit();
}

if ($price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Price must be greater than 0']);
    exit();
}

// Verify ownership
$query = "SELECT id FROM accommodations WHERE id = ? AND landlord_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $id, $landlord_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Accommodation not found or access denied']);
    exit();
}
$stmt->close();

// Use current images as-is
$images_json = json_encode($current_images);

// Update accommodation
$query = "UPDATE accommodations SET
    title = ?, description = ?, price = ?, location = ?,
    latitude = ?, longitude = ?, amenities = ?, images = ?
    WHERE id = ? AND landlord_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param(
    "ssdssdssii",
    $title, $description, $price, $location,
    $latitude, $longitude, $amenities, $images_json,
    $id, $landlord_id
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Accommodation updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating accommodation: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>