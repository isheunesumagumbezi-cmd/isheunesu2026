<?php
$pageTitle = "Payment Status - Smart Campus Connect";
include 'includes/config.php';
include 'includes/paynow.php';

$reference = sanitizeInput($_GET['reference'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');

if (empty($reference)) {
    header("Location: dashboard.php");
    exit();
}

$conn = getDBConnection();

function getPaymentByReference($conn, $reference) {
    $payment_stmt = $conn->prepare("
        SELECT p.*, 
               CASE
                   WHEN p.booking_type = 'bus' THEN bb.id
                   ELSE ab.id
               END AS booking_id,
               CASE
                   WHEN p.booking_type = 'bus' THEN b.route
                   ELSE a.title
               END AS accommodation_title,
               u.name as student_name, u.email
        FROM payments p
        LEFT JOIN accommodation_bookings ab ON p.booking_type = 'accommodation' AND p.booking_id = ab.id
        LEFT JOIN accommodations a ON ab.accommodation_id = a.id
        LEFT JOIN bus_bookings bb ON p.booking_type = 'bus' AND p.booking_id = bb.id
        LEFT JOIN buses b ON bb.bus_id = b.id
        JOIN students s ON p.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE p.reference_number = ?
    ");
    $payment_stmt->bind_param("s", $reference);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    $payment = $payment_result && $payment_result->num_rows > 0
        ? $payment_result->fetch_assoc()
        : null;
    $payment_stmt->close();

    return $payment;
}

$payment = getPaymentByReference($conn, $reference);

if (
    $payment
    && $payment['status'] === 'pending'
    && !empty($payment['transaction_id'])
    && PayNowZimbabwe::hasConfiguredCredentials()
) {
    $poll_url = $payment['transaction_id'];
    $conn->close();

    $paynow = new PayNowZimbabwe();
    $paynow->pollTransaction($poll_url, $reference);

    $conn = getDBConnection();
    $payment = getPaymentByReference($conn, $reference);
}

$conn->close();

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-credit-card"></i> Payment Status
                </h4>
            </div>
            <div class="card-body text-center">
                <?php if ($payment): ?>
                    <?php if ($payment['status'] === 'completed'): ?>
                        <!-- Success State -->
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h4>Payment Successful!</h4>
                            <p class="mb-0">Your payment has been processed successfully.</p>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <h6>Payment Details</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Reference:</strong></td>
                                        <td><?php echo htmlspecialchars($payment['reference_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Amount:</strong></td>
                                        <td>$<?php echo number_format($payment['amount'], 2); ?> <?php echo $payment['currency']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Method:</strong></td>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Date:</strong></td>
                                        <td><?php echo date('d M Y H:i', strtotime($payment['payment_date'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Booking Details</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Accommodation:</strong></td>
                                        <td><?php echo htmlspecialchars($payment['accommodation_title']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Student:</strong></td>
                                        <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?php echo htmlspecialchars($payment['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Booking ID:</strong></td>
                                        <td>#<?php echo $payment['booking_id']; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Next Steps:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Your booking has been confirmed</li>
                                <li>You will receive a confirmation email shortly</li>
                                <li>Contact the landlord for check-in details</li>
                                <li>Keep this reference number for your records</li>
                            </ul>
                        </div>

                    <?php elseif ($payment['status'] === 'failed'): ?>
                        <!-- Failed State -->
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                            <h4>Payment Failed</h4>
                            <p class="mb-0">Your payment could not be processed.</p>
                        </div>

                        <p>Please try again or contact support if the problem persists.</p>

                    <?php elseif ($payment['status'] === 'pending'): ?>
                        <!-- Pending State -->
                        <div class="alert alert-warning">
                            <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                            <h4>Payment Processing</h4>
                            <p class="mb-0">Your payment is being processed. This may take a few minutes.</p>
                        </div>

                        <p>Please wait while we confirm your payment...</p>
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>

                    <?php else: ?>
                        <!-- Unknown State -->
                        <div class="alert alert-secondary">
                            <i class="fas fa-question-circle fa-3x text-secondary mb-3"></i>
                            <h4>Payment Status Unknown</h4>
                            <p class="mb-0">We're unable to determine the payment status at this time.</p>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <a href="my_bookings.php" class="btn btn-primary">
                            <i class="fas fa-list"></i> View My Bookings
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Go to Dashboard
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Payment Not Found -->
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h4>Payment Not Found</h4>
                        <p class="mb-0">We couldn't find the payment you're looking for.</p>
                    </div>

                    <p>If you believe this is an error, please contact support with reference: <strong><?php echo htmlspecialchars($reference); ?></strong></p>

                    <div class="mt-4">
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fas fa-home"></i> Go to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh for pending payments
<?php if ($payment && $payment['status'] === 'pending'): ?>
setTimeout(function() {
    window.location.reload();
}, 10000); // Refresh every 10 seconds
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
