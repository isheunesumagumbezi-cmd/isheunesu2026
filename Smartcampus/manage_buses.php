<?php
$pageTitle = "Manage Bus Routes - Smart Campus Connect";
include 'includes/config.php';
redirectIfNotLoggedIn();

$user = getCurrentUser();
if ($user['role'] !== 'bus_company') {
    header('Location: dashboard.php');
    exit();
}

function deleteBusImage($path) {
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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $busId = (int)($_POST['bus_id'] ?? 0);

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form token. Please try again.';
    } elseif ($busId <= 0) {
        $error = 'Invalid bus route.';
    } else {
        $stmt = $conn->prepare("SELECT transport_image FROM buses WHERE id = ? AND bus_company_id = ?");
        $stmt->bind_param("ii", $busId, $company['id']);
        $stmt->execute();
        $bus = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$bus) {
            $error = 'Bus route not found or access denied.';
        } else {
            $stmt = $conn->prepare("DELETE FROM buses WHERE id = ? AND bus_company_id = ?");
            $stmt->bind_param("ii", $busId, $company['id']);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                deleteBusImage($bus['transport_image'] ?? '');
                $success = 'Bus route deleted.';
            } else {
                $error = 'Could not delete bus route.';
            }
            $stmt->close();
        }
    }
}

$stmt = $conn->prepare("
    SELECT b.*,
           COALESCE(SUM(CASE WHEN bb.status = 'confirmed' THEN bb.seats_booked ELSE 0 END), 0) AS booked_seats,
           COUNT(bb.id) AS booking_count
    FROM buses b
    LEFT JOIN bus_bookings bb ON bb.bus_id = b.id
    WHERE b.bus_company_id = ?
    GROUP BY b.id
    ORDER BY b.date DESC, b.departure_time DESC
");
$stmt->bind_param("i", $company['id']);
$stmt->execute();
$routes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2>Manage Bus Routes</h2>
            <p class="text-muted">Edit or delete your transport listings.</p>
        </div>
        <a href="add_bus.php" class="btn btn-primary">Add Route</a>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

<?php if (!$routes): ?>
    <div class="alert alert-info">You have not added any bus routes yet.</div>
<?php else: ?>
    <div class="row">
        <?php foreach ($routes as $route): ?>
            <?php
            $image = trim((string)($route['transport_image'] ?? '')) === ''
                ? 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=900&q=80'
                : normalizeStoredImageUrl($route['transport_image']);
            ?>
            <div class="col-lg-6 mb-4">
                <div class="card h-100 bus-card">
                    <img src="<?php echo e($image); ?>" class="card-img-top" alt="<?php echo e($route['route']); ?> transport">
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-3">
                            <h5 class="card-title mb-2"><?php echo e($route['route']); ?></h5>
                            <span class="badge bg-<?php echo ((int)$route['available_seats'] > 0) ? 'success' : 'secondary'; ?> align-self-start">
                                <?php echo (int)$route['available_seats']; ?> seats left
                            </span>
                        </div>
                        <p class="card-text mb-3">
                            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($route['date'])); ?><br>
                            <strong>Departure:</strong> <?php echo date('H:i', strtotime($route['departure_time'])); ?><br>
                            <strong>Arrival:</strong> <?php echo date('H:i', strtotime($route['arrival_time'])); ?><br>
                            <strong>Price:</strong> $<?php echo number_format((float)$route['price'], 2); ?><br>
                            <strong>Booked Seats:</strong> <?php echo (int)$route['booked_seats']; ?>/<?php echo (int)$route['total_seats']; ?>
                        </p>
                        <div class="d-flex gap-2">
                            <a href="add_bus.php?id=<?php echo (int)$route['id']; ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                            <form method="POST" onsubmit="return confirm('Delete this bus route? Related bookings will also be removed.');">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="bus_id" value="<?php echo (int)$route['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <small class="text-muted"><?php echo (int)$route['booking_count']; ?> bookings</small>
                        <small class="text-muted">Route #<?php echo (int)$route['id']; ?></small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
