<?php
$pageTitle = "My Bookings - Smart Campus Connect";
include 'includes/config.php';

// Check if user is logged in and is a student
redirectIfNotLoggedIn();
$user = getCurrentUser();
if ($user['role'] !== 'student') {
    header("Location: dashboard.php");
    exit();
}

include 'includes/header.php';

$conn = getDBConnection();
ensureAccommodationPaymentColumns($conn);

$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$student_result = $stmt->get_result();
if (!$student_result || $student_result->num_rows == 0) {
    echo "<div class='alert alert-danger'>Student profile not found.</div>";
    include 'includes/footer.php';
    exit();
}
$student_id = $student_result->fetch_assoc()['id'];
$stmt->close();

$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'all';
$error = '';
$success = '';

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    $booking_id = (int)($_POST['booking_id'] ?? 0);

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form token. Please try again.";
    } elseif ($action === 'cancel') {
        // Check if booking is pending and belongs to student
        $check_stmt = $conn->prepare("SELECT status FROM accommodation_bookings WHERE id = ? AND student_id = ?");
        $check_stmt->bind_param("ii", $booking_id, $student_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0 && $check_result->fetch_assoc()['status'] === 'pending') {
            $update_stmt = $conn->prepare("UPDATE accommodation_bookings SET status = 'cancelled' WHERE id = ? AND student_id = ?");
            $update_stmt->bind_param("ii", $booking_id, $student_id);
            if ($update_stmt->execute()) {
                $success = "Booking cancelled successfully!";
            } else {
                $error = "Error cancelling booking.";
            }
            $update_stmt->close();
        } else {
            $error = "You cannot cancel this booking.";
        }
        $check_stmt->close();
    }
}

// Get bookings
$query = "SELECT ab.*, a.title, a.location, a.price, l.user_id as landlord_user_id, u.name as landlord_name, u.email as landlord_email, u.phone as landlord_phone
          FROM accommodation_bookings ab
          JOIN accommodations a ON ab.accommodation_id = a.id
          JOIN landlords l ON a.landlord_id = l.id
          JOIN users u ON l.user_id = u.id
          WHERE ab.student_id = ?";

if ($filter !== 'all') {
    $query .= " AND ab.status = ?";
}

$query .= " ORDER BY ab.booking_date DESC";

$stmt = $conn->prepare($query);

if ($filter !== 'all') {
    $stmt->bind_param("is", $student_id, $filter);
} else {
    $stmt->bind_param("i", $student_id);
}

$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get booking counts
$pending_count = countRows($conn, "SELECT COUNT(*) as count FROM accommodation_bookings WHERE student_id = ? AND status = 'pending'", "i", $student_id);
$confirmed_count = countRows($conn, "SELECT COUNT(*) as count FROM accommodation_bookings WHERE student_id = ? AND status = 'confirmed'", "i", $student_id);
$cancelled_count = countRows($conn, "SELECT COUNT(*) as count FROM accommodation_bookings WHERE student_id = ? AND status = 'cancelled'", "i", $student_id);

$conn->close();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>My Bookings</h2>
        <p class="text-muted">View and manage your accommodation bookings.</p>
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
            <a href="?filter=all" class="btn btn-outline-secondary <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?filter=pending" class="btn btn-outline-warning <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?filter=confirmed" class="btn btn-outline-success <?php echo $filter === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
            <a href="?filter=cancelled" class="btn btn-outline-danger <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
        </div>
    </div>
</div>

<!-- Bookings List -->
<div class="row">
    <div class="col-12">
        <?php if (empty($bookings)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No bookings found. 
                <a href="accommodation.php">Browse accommodations</a> to make a booking.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($bookings as $booking): ?>
                    <?php
                        $paymentStatus = $booking['payment_status'] ?? 'unpaid';
                        $totalAmount = (float)($booking['total_amount'] ?? 0);
                    ?>
                    <div class="col-md-12 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5 class="card-title"><?php echo htmlspecialchars($booking['title']); ?></h5>
                                        <p class="card-text">
                                            <strong>Location:</strong> <?php echo htmlspecialchars($booking['location']); ?><br>
                                            <strong>Price:</strong> $<?php echo number_format($booking['price'], 2); ?>/month<br>
                                            <strong>Check-in:</strong> <?php echo date('d M Y', strtotime($booking['check_in_date'])); ?><br>
                                            <strong>Check-out:</strong> <?php echo date('d M Y', strtotime($booking['check_out_date'])); ?><br>
                                            <strong>Status:</strong>
                                            <span class="badge bg-<?php
                                                echo $booking['status'] === 'pending' ? 'warning' :
                                                     ($booking['status'] === 'confirmed' ? 'success' :
                                                     ($booking['status'] === 'paid' ? 'success' : 'danger'));
                                            ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span><br>
                                            <strong>Payment:</strong>
                                            <span class="badge bg-<?php
                                                echo $paymentStatus === 'paid' ? 'success' :
                                                     ($paymentStatus === 'pending' ? 'warning' : 'secondary');
                                            ?>">
                                                <?php echo ucfirst($paymentStatus); ?>
                                            </span>
                                            <?php if ($totalAmount > 0): ?>
                                                <br><strong>Total:</strong> $<?php echo number_format($totalAmount, 2); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <h6>Landlord Contact</h6>
                                        <p class="mb-0">
                                            <strong><?php echo htmlspecialchars($booking['landlord_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['landlord_email']); ?></small><br>
                                            <?php if (!empty($booking['landlord_phone'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($booking['landlord_phone']); ?></small>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <?php if ($booking['status'] === 'pending' && $paymentStatus !== 'paid'): ?>
                                            <button class="btn btn-sm btn-success" onclick="payForBooking(<?php echo $booking['id']; ?>)">
                                                <i class="fas fa-credit-card"></i> Pay Now
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this booking?')">
                                                    <i class="fas fa-times"></i> Cancel Booking
                                                </button>
                                            </form>
                                        <?php elseif ($booking['status'] === 'confirmed' && $paymentStatus === 'paid'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> Fully Paid & Confirmed
                                            </span>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $booking['id']; ?>">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Details Modal -->
                    <div class="modal fade" id="detailsModal<?php echo $booking['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Booking Details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p><strong>Accommodation:</strong> <?php echo htmlspecialchars($booking['title']); ?></p>
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($booking['location']); ?></p>
                                    <p><strong>Price:</strong> $<?php echo number_format($booking['price'], 2); ?>/month</p>
                                    <p><strong>Check-in:</strong> <?php echo date('d M Y', strtotime($booking['check_in_date'])); ?></p>
                                    <p><strong>Check-out:</strong> <?php echo date('d M Y', strtotime($booking['check_out_date'])); ?></p>
                                    <p><strong>Status:</strong> <span class="badge bg-<?php 
                                        echo $booking['status'] === 'pending' ? 'warning' : 
                                             ($booking['status'] === 'confirmed' ? 'success' : 'danger');
                                        ?>"><?php echo ucfirst($booking['status']); ?></span></p>
                                    <p><strong>Landlord:</strong> <?php echo htmlspecialchars($booking['landlord_name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['landlord_email']); ?></p>
                                    <?php if (!empty($booking['landlord_phone'])): ?>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking['landlord_phone']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($booking['notes'])): ?>
                                        <p><strong>Your Notes:</strong> <?php echo htmlspecialchars($booking['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Complete Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="paymentContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Preparing payment...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <a href="accommodation.php" class="btn btn-primary">
            <i class="fas fa-search"></i> Browse More Accommodations
        </a>
    </div>
</div>

<script>
function payForBooking(bookingId) {
    // Show payment modal
    $('#paymentModal').modal('show');

    const paymentContent = document.getElementById('paymentContent');
    paymentContent.innerHTML = `
        <div class="mb-3">
            <label class="form-label">Payment Gateway</label>
            <div class="paynow-card border rounded-4 p-3">
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <div>
                        <div class="fw-bold text-white">PayNow Zimbabwe</div>
                        <small class="text-white-50">Secure, trusted checkout for mobile money and card payments.</small>
                    </div>
                    <div class="paynow-badge">
                        <i class="fas fa-shield-check me-2"></i> Secure
                    </div>
                </div>
                <input type="hidden" name="payment_method" value="ecocash">
            </div>
        </div>

        <div class="alert alert-warning">
            <small>
                <i class="fas fa-exclamation-triangle"></i>
                You will be redirected to complete your payment. Do not close the window until payment is complete.
            </small>
        </div>

        <div class="text-center">
            <button type="button" class="btn btn-success" onclick="processPayment(${bookingId})">
                <i class="fas fa-credit-card"></i> Proceed to Payment
            </button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
    `;
}

function processPayment(bookingId) {
    const paymentMethod = 'ecocash';

    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    formData.append('booking_id', bookingId);
    formData.append('payment_method', paymentMethod);

    // Show loading
    const paymentContent = document.getElementById('paymentContent');
    paymentContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Redirecting to payment gateway...</p>
        </div>
    `;

    fetch('api/initiate_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.payment_url) {
            // Redirect to the configured payment gateway.
            window.location.href = data.payment_url;
        } else {
            paymentContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Payment initiation failed: ${data.message || 'Unknown error'}
                </div>
                <div class="text-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        paymentContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                An error occurred. Please try again.
            </div>
            <div class="text-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        `;
    });
}
</script>

<?php include 'includes/footer.php'; ?>

