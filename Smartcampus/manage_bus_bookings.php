<?php
$pageTitle = "Manage Bus Bookings - Smart Campus Connect";
include 'includes/config.php';
redirectIfNotLoggedIn();

$user = getCurrentUser();
if ($user['role'] !== 'bus_company') {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id FROM bus_companies WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
$stmt->close();

$bookings = [];
if ($company) {
    $stmt = $conn->prepare("
        SELECT bb.*, b.route, b.date, b.departure_time, u.name, u.email, u.phone
        FROM bus_bookings bb
        JOIN buses b ON bb.bus_id = b.id
        JOIN students s ON bb.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE b.bus_company_id = ?
        ORDER BY bb.booking_date DESC
    ");
    $stmt->bind_param("i", $company['id']);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$conn->close();

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2>Bus Bookings</h2>
            <p class="text-muted">View passengers booked on your routes.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_buses.php" class="btn btn-outline-primary">Manage Routes</a>
            <a href="add_bus.php" class="btn btn-primary">Add Route</a>
        </div>
    </div>
</div>

<?php if (!$bookings): ?>
    <div class="alert alert-info">No bus bookings yet.</div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Passenger</th>
                        <th>Route</th>
                        <th>Date</th>
                        <th>Departure</th>
                        <th>Seats</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td>
                                <?php echo e($booking['name']); ?><br>
                                <small class="text-muted"><?php echo e($booking['email']); ?></small>
                            </td>
                            <td><?php echo e($booking['route']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($booking['date'])); ?></td>
                            <td><?php echo date('H:i', strtotime($booking['departure_time'])); ?></td>
                            <td><?php echo (int)$booking['seats_booked']; ?></td>
                            <td><span class="badge bg-success"><?php echo e(ucfirst($booking['status'])); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
