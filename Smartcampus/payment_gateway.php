<?php
$pageTitle = "Payment Gateway - Smart Campus Connect";
include 'includes/config.php';
redirectIfNotLoggedIn();

$reference = sanitizeInput($_GET['reference'] ?? '');
if ($reference === '') {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT p.*, a.title, u.name
    FROM payments p
    JOIN accommodation_bookings ab ON p.booking_id = ab.id
    JOIN accommodations a ON ab.accommodation_id = a.id
    JOIN students s ON p.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE p.reference_number = ? AND u.id = ?
");
$stmt->bind_param("si", $reference, $_SESSION['user_id']);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$payment) {
    include 'includes/header.php';
    echo "<div class='alert alert-danger'>Payment not found.</div>";
    include 'includes/footer.php';
    exit();
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h4>Complete Payment</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Reference:</strong> <?php echo e($payment['reference_number']); ?><br>
                    <strong>Accommodation:</strong> <?php echo e($payment['title']); ?><br>
                    <strong>Amount:</strong> $<?php echo number_format($payment['amount'], 2); ?> <?php echo e($payment['currency']); ?>
                </div>

                <form method="POST" action="api/complete_payment.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="reference" value="<?php echo e($payment['reference_number']); ?>">
                    <div class="mb-3">
                        <label for="payer_phone" class="form-label">Mobile Money / Card Phone</label>
                        <input type="tel" class="form-control" id="payer_phone" name="payer_phone" placeholder="e.g. 0771234567" required>
                    </div>
                    <div class="mb-3">
                        <label for="payment_code" class="form-label">Confirmation Code</label>
                        <input type="text" class="form-control" id="payment_code" name="payment_code" minlength="4" placeholder="Any 4+ characters for local testing" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Pay Now</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
