<?php
$pageTitle = "Bus Route - Smart Campus Connect";
include 'includes/config.php';
redirectIfNotLoggedIn();

$user = getCurrentUser();
if ($user['role'] !== 'bus_company') {
    header('Location: dashboard.php');
    exit();
}

function saveTransportImage($fieldName, &$errors) {
    $allowedImageTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Transport picture upload failed.';
        return null;
    }

    if ($_FILES[$fieldName]['size'] > 3 * 1024 * 1024) {
        $errors[] = 'Transport picture must be 3MB or smaller.';
        return null;
    }

    $mimeType = mime_content_type($_FILES[$fieldName]['tmp_name']);
    if (!isset($allowedImageTypes[$mimeType])) {
        $errors[] = 'Transport picture must be JPG, PNG, or WEBP.';
        return null;
    }

    $uploadDir = 'uploads/buses/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filePath = $uploadDir . uniqid('bus_', true) . '.' . $allowedImageTypes[$mimeType];
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $filePath)) {
        $errors[] = 'Could not save transport picture.';
        return null;
    }

    return $filePath;
}

function deleteLocalTransportImage($path) {
    $path = trim((string)$path);
    if ($path === '' || preg_match('#^(https?:)?//#i', $path) || strpos($path, 'data:') === 0) {
        return;
    }

    $filePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
    $rootPath = realpath(__DIR__);
    if ($filePath && $rootPath && strpos($filePath, $rootPath) === 0 && is_file($filePath)) {
        unlink($filePath);
    }
}

$conn = getDBConnection();
ensureColumnExists($conn, 'buses', 'transport_image', 'VARCHAR(255) NULL');

$stmt = $conn->prepare("SELECT id FROM bus_companies WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$company) {
    $conn->close();
    include 'includes/header.php';
    echo "<div class='alert alert-danger'>Bus company profile not found.</div>";
    include 'includes/footer.php';
    exit();
}

$busId = (int)($_GET['id'] ?? $_POST['bus_id'] ?? 0);
$isEdit = $busId > 0;
$bus = [
    'route' => '',
    'departure_time' => '',
    'arrival_time' => '',
    'price' => '',
    'total_seats' => '',
    'date' => '',
    'transport_image' => ''
];

if ($isEdit) {
    $stmt = $conn->prepare("SELECT * FROM buses WHERE id = ? AND bus_company_id = ?");
    $stmt->bind_param("ii", $busId, $company['id']);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        $conn->close();
        header('Location: manage_buses.php');
        exit();
    }

    $bus = $existing;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route = sanitizeInput($_POST['route'] ?? '');
    $departure = sanitizeInput($_POST['departure_time'] ?? '');
    $arrival = sanitizeInput($_POST['arrival_time'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $totalSeats = (int)($_POST['total_seats'] ?? 0);
    $date = sanitizeInput($_POST['date'] ?? '');

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please try again.';
    } elseif ($route === '' || $departure === '' || $arrival === '' || $date === '' || $price <= 0 || $totalSeats <= 0) {
        $errors[] = 'Fill in all route details with valid values.';
    } else {
        $newImage = saveTransportImage('transport_image', $errors);

        if (empty($errors)) {
            if ($isEdit) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(seats_booked), 0) AS booked FROM bus_bookings WHERE bus_id = ? AND status = 'confirmed'");
                $stmt->bind_param("i", $busId);
                $stmt->execute();
                $bookedSeats = (int)$stmt->get_result()->fetch_assoc()['booked'];
                $stmt->close();

                $availableSeats = max(0, $totalSeats - $bookedSeats);
                $transportImage = $newImage ?: $bus['transport_image'];

                $stmt = $conn->prepare("
                    UPDATE buses
                    SET route = ?, departure_time = ?, arrival_time = ?, price = ?, total_seats = ?, available_seats = ?, date = ?, transport_image = ?
                    WHERE id = ? AND bus_company_id = ?
                ");
                $stmt->bind_param("sssdiissii", $route, $departure, $arrival, $price, $totalSeats, $availableSeats, $date, $transportImage, $busId, $company['id']);
                if ($stmt->execute()) {
                    if ($newImage && !empty($bus['transport_image'])) {
                        deleteLocalTransportImage($bus['transport_image']);
                    }
                    $success = 'Bus route updated.';
                    $bus = [
                        'route' => $route,
                        'departure_time' => $departure,
                        'arrival_time' => $arrival,
                        'price' => $price,
                        'total_seats' => $totalSeats,
                        'date' => $date,
                        'transport_image' => $transportImage
                    ];
                } else {
                    $errors[] = 'Could not update bus route.';
                    if ($newImage) {
                        deleteLocalTransportImage($newImage);
                    }
                }
                $stmt->close();
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO buses (bus_company_id, route, departure_time, arrival_time, price, total_seats, available_seats, date, transport_image)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isssdiiss", $company['id'], $route, $departure, $arrival, $price, $totalSeats, $totalSeats, $date, $newImage);
                if ($stmt->execute()) {
                    $success = 'Bus route added.';
                    $bus = [
                        'route' => '',
                        'departure_time' => '',
                        'arrival_time' => '',
                        'price' => '',
                        'total_seats' => '',
                        'date' => '',
                        'transport_image' => ''
                    ];
                } else {
                    $errors[] = 'Could not add bus route.';
                    if ($newImage) {
                        deleteLocalTransportImage($newImage);
                    }
                }
                $stmt->close();
            }
        }
    }
}

$conn->close();
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><?php echo $isEdit ? 'Edit Bus Route' : 'Add Bus Route'; ?></h4>
                <a href="manage_buses.php" class="btn btn-sm btn-outline-secondary">Manage Routes</a>
            </div>
            <div class="card-body">
                <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
                <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="bus_id" value="<?php echo (int)$busId; ?>">
                    <div class="mb-3">
                        <label for="route" class="form-label">Route</label>
                        <input type="text" class="form-control" id="route" name="route" value="<?php echo e($bus['route']); ?>" placeholder="Harare to Chinhoyi" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo e($bus['date']); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="departure_time" class="form-label">Departure</label>
                            <input type="time" class="form-control" id="departure_time" name="departure_time" value="<?php echo e(substr((string)$bus['departure_time'], 0, 5)); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="arrival_time" class="form-label">Arrival</label>
                            <input type="time" class="form-control" id="arrival_time" name="arrival_time" value="<?php echo e(substr((string)$bus['arrival_time'], 0, 5)); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label">Price</label>
                            <input type="number" class="form-control" id="price" name="price" value="<?php echo e($bus['price']); ?>" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="total_seats" class="form-label">Total Seats</label>
                            <input type="number" class="form-control" id="total_seats" name="total_seats" value="<?php echo e($bus['total_seats']); ?>" min="1" required>
                        </div>
                    </div>
                    <?php if (!empty($bus['transport_image'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Current Transport Picture</label><br>
                            <img src="<?php echo e(normalizeStoredImageUrl($bus['transport_image'])); ?>" class="img-fluid rounded" style="max-height: 220px;" alt="Current transport picture">
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="transport_image" class="form-label"><?php echo $isEdit ? 'Replace Transport Picture' : 'Transport Picture'; ?></label>
                        <input type="file" class="form-control" id="transport_image" name="transport_image" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Upload a clear JPG, PNG, or WEBP image of the bus. Maximum 3MB.</div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Update Route' : 'Save Route'; ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
