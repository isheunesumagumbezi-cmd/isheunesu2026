<?php
header('Content-Type: application/json');
include '../includes/config.php';

// Get pagination parameters
$offset = (int)($_GET['offset'] ?? 0);
$limit = (int)($_GET['limit'] ?? 10);
$location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 10000;

$conn = getDBConnection();

// Build query
$query = "SELECT a.*, l.company_name, u.name as landlord_name FROM accommodations a 
          JOIN landlords l ON a.landlord_id = l.id 
          JOIN users u ON l.user_id = u.id 
          WHERE a.available = 1";

$params = [];
$types = '';

if (!empty($location)) {
    $query .= " AND a.location LIKE ?";
    $params[] = "%$location%";
    $types .= 's';
}

if ($min_price > 0) {
    $query .= " AND a.price >= ?";
    $params[] = $min_price;
    $types .= 'i';
}

if ($max_price < 10000) {
    $query .= " AND a.price <= ?";
    $params[] = $max_price;
    $types .= 'i';
}

$query .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);

if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$accommodations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $accommodations]);
?>
