<?php
$pageTitle = "Manage Accommodation Bookings - Smart Campus Connect";
include 'includes/config.php';

// Check if user is logged in and is a landlord
redirectIfNotLoggedIn();
$user = getCurrentUser();
if ($user['role'] !== 'landlord') {
    header("Location: dashboard.php");
    exit();
}

include 'includes/header.php';

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id FROM landlords WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$landlord_id = $result->fetch_assoc()['id'];
$stmt->close();

$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'pending';
$error = '';
$success = '';

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    $booking_id = (int)($_POST['booking_id'] ?? 0);

    if ($action === 'confirm') {
        $stmt = $conn->prepare(
            "UPDATE accommodation_bookings SET status = 'confirmed' WHERE id = ? AND accommodation_id IN 
             (SELECT id FROM accommodations WHERE landlord_id = ?)"
        );
        $stmt->bind_param("ii", $booking_id, $landlord_id);
        if ($stmt->execute()) {
            $success = "Booking confirmed successfully!";
        } else {
            $error = "Error confirming booking.";
        }
        $stmt->close();
    } elseif ($action === 'cancel') {
        $stmt = $conn->prepare(
            "UPDATE accommodation_bookings SET status = 'cancelled' WHERE id = ? AND accommodation_id IN 
             (SELECT id FROM accommodations WHERE landlord_id = ?)"
        );
        $stmt->bind_param("ii", $booking_id, $landlord_id);
        if ($stmt->execute()) {
            $success = "Booking cancelled successfully!";
        } else {
            $error = "Error cancelling booking.";
        }
        $stmt->close();
    }
}

// Get bookings based on filter
$query = "SELECT ab.*, a.title, a.location, a.price, s.user_id, u.name as student_name, u.email as student_email, u.phone as student_phone
          FROM accommodation_bookings ab
          JOIN accommodations a ON ab.accommodation_id = a.id
          JOIN students s ON ab.student_id = s.id
          JOIN users u ON s.user_id = u.id
          WHERE a.landlord_id = ?";

if ($filter !== 'all') {
    $query .= " AND ab.status = ?";
}

$query .= " ORDER BY ab.booking_date DESC";

$stmt = $conn->prepare($query);

if ($filter !== 'all') {
    $stmt->bind_param("is", $landlord_id, $filter);
} else {
    $stmt->bind_param("i", $landlord_id);
}

$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Count bookings by status
$conn = getDBConnection();
$pending_count = countRows($conn, "SELECT COUNT(*) as count FROM accommodation_bookings ab JOIN accommodations a ON ab.accommodation_id = a.id WHERE a.landlord_id = ? AND ab.status = 'pending'", "i", $landlord_id);
$confirmed_count = countRows($conn, "SELECT COUNT(*) as count FROM accommodation_bookings ab JOIN accommodations a ON ab.accommodation_id = a.id WHERE a.landlord_id = ? AND ab.status = 'confirmed'", "i", $landlord_id);
$cancelled_count = countRows($conn, "SELECT COUNT(*) as count FROM accommodation_bookings ab JOIN accommodations a ON ab.accommodation_id = a.id WHERE a.landlord_id = ? AND ab.status = 'cancelled'", "i", $landlord_id);

$conn->close();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Manage Accommodation Bookings</h2>
        <p class="text-muted">Review and manage booking requests from students.</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="card-title"><?php echo $pending_count; ?></h3>
                <p class="card-text">Pending</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="card-title"><?php echo $confirmed_count; ?></h3>
                <p class="card-text">Confirmed</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="card-title"><?php echo $cancelled_count; ?></h3>
                <p class="card-text">Cancelled</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="card-title"><?php echo $pending_count + $confirmed_count + $cancelled_count; ?></h3>
                <p class="card-text">Total</p>
            </div>
        </div>
    </div>
</div>

<!-- Filter Buttons -->
<div class="row mb-4">
    <div class="col-12">
        <div class="btn-group" role="group">
            <a href="?filter=pending" class="btn btn-outline-primary <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                Pending
            </a>
            <a href="?filter=confirmed" class="btn btn-outline-success <?php echo $filter === 'confirmed' ? 'active' : ''; ?>">
                Confirmed
            </a>
            <a href="?filter=cancelled" class="btn btn-outline-danger <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                Cancelled
            </a>
            <a href="?filter=all" class="btn btn-outline-secondary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                All
            </a>
        </div>
    </div>
</div>

<!-- Bookings Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5>Bookings (<?php echo count($bookings); ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Accommodation</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Status</th>
                            <th>Booking Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No bookings found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['student_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($booking['student_email']); ?></small>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($booking['title']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['location']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo !empty($booking['check_in_date']) ? date('d M Y', strtotime($booking['check_in_date'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($booking['check_out_date']) ? date('d M Y', strtotime($booking['check_out_date'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $booking['status'] === 'pending' ? 'warning' : 
                                                 ($booking['status'] === 'confirmed' ? 'success' : 'danger');
                                        ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('d M Y H:i', strtotime($booking['booking_date'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Confirm Booking">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Reject Booking">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                            <!-- View Details Button -->
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#bookingModal<?php echo $booking['id']; ?>" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#bookingModal<?php echo $booking['id']; ?>" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Modal for booking details -->
                                <div class="modal fade" id="bookingModal<?php echo $booking['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Booking Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>Student:</strong> <?php echo htmlspecialchars($booking['student_name']); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['student_email']); ?></p>
                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking['student_phone'] ?? 'N/A'); ?></p>
                                                <p><strong>Accommodation:</strong> <?php echo htmlspecialchars($booking['title']); ?></p>
                                                <p><strong>Price:</strong> $<?php echo number_format($booking['price'], 2); ?>/month</p>
                                                <p><strong>Check-in:</strong> <?php echo !empty($booking['check_in_date']) ? date('d M Y', strtotime($booking['check_in_date'])) : '-'; ?></p>
                                                <p><strong>Check-out:</strong> <?php echo !empty($booking['check_out_date']) ? date('d M Y', strtotime($booking['check_out_date'])) : '-'; ?></p>
                                                <p><strong>Notes:</strong> <?php echo !empty($booking['notes']) ? htmlspecialchars($booking['notes']) : 'None'; ?></p>
                                                <p><strong>Status:</strong> <span class="badge bg-<?php 
                                                    echo $booking['status'] === 'pending' ? 'warning' : 
                                                         ($booking['status'] === 'confirmed' ? 'success' : 'danger');
                                                    ?>"><?php echo ucfirst($booking['status']); ?></span></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
