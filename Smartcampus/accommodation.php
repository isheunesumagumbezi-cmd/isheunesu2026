<?php
$pageTitle = "Accommodation - Smart Campus Connect";
include 'includes/config.php';
include 'includes/header.php';

$conn = getDBConnection();

// Get filter parameters
$location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 10000;

// Build query
$query = "SELECT a.*, l.company_name, u.name as landlord_name FROM accommodations a 
          JOIN landlords l ON a.landlord_id = l.id 
          JOIN users u ON l.user_id = u.id 
          WHERE a.available = 1 AND l.verification_status = 'verified'";

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

$query .= " ORDER BY a.created_at DESC LIMIT 20";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$accommodations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<div class="page-hero mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="page-kicker"><i class="fas fa-bed me-2"></i> accommodation search</span>
            <h1 class="display-6 fw-bold mb-3">Find a place that feels like home.</h1>
            <p class="text-white-50 mb-0">Browse trusted listings, compare prices, and secure well-located stays with confidence.</p>
        </div>
        <div class="col-lg-5 text-lg-end">
            <div class="hero-badges">
                <span><i class="fas fa-shield-alt me-2"></i> Verified</span>
                <span><i class="fas fa-clock me-2"></i> Instant access</span>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card search-shell">
            <div class="card-body">
                <form method="GET" action="" class="search-form">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="location" class="form-label">Location</label>
                            <div class="input-wrap">
                                <i class="fas fa-location-dot"></i>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo e($location); ?>" placeholder="Enter location">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="min_price" class="form-label">Min Price ($)</label>
                            <input type="number" class="form-control" id="min_price" name="min_price" value="<?php echo $min_price; ?>" min="0">
                        </div>
                        <div class="col-md-3">
                            <label for="max_price" class="form-label">Max Price ($)</label>
                            <input type="number" class="form-control" id="max_price" name="max_price" value="<?php echo $max_price; ?>" min="0">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 search-button">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Accommodations List -->
<div class="row">
    <?php if (empty($accommodations)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                No accommodations found matching your criteria. Try adjusting your filters.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($accommodations as $accommodation): ?>
            <div class="col-md-6 mb-4">
                <div class="card accommodation-card h-100">
                    <?php
                    $images = json_decode($accommodation['images'], true);
                    $image_url = !empty($images) ? normalizeStoredImageUrl($images[0]) : normalizeStoredImageUrl('');
                    ?>
                    <img src="<?php echo htmlspecialchars($image_url); ?>" class="card-img-top" alt="Accommodation" style="height: 200px; object-fit: cover;">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($accommodation['title']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars(substr($accommodation['description'], 0, 100)) . '...'; ?></p>
                        <p class="card-text">
                            <strong>Location:</strong> <?php echo htmlspecialchars($accommodation['location']); ?><br>
                            <strong>Price:</strong> $<?php echo number_format($accommodation['price'], 2); ?>/day<br>
                            <strong>Landlord:</strong> <?php echo htmlspecialchars($accommodation['company_name'] ?: $accommodation['landlord_name']); ?>
                        </p>
                        <div class="mt-auto">
                            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#accommodationModal<?php echo $accommodation['id']; ?>">
                                View Details
                            </button>
                            <?php if (isLoggedIn() && $_SESSION['user_role'] == 'student'): ?>
                                <button class="btn btn-success negotiate-btn" data-accommodation-id="<?php echo $accommodation['id']; ?>" data-price="<?php echo $accommodation['price']; ?>">
                                    Negotiate Price
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accommodation Modal -->
            <div class="modal fade" id="accommodationModal<?php echo $accommodation['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?php echo htmlspecialchars($accommodation['title']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <?php if (!empty($images)): ?>
                                <div id="accommodationCarousel<?php echo $accommodation['id']; ?>" class="carousel slide mb-3">
                                    <div class="carousel-inner">
                                        <?php foreach ($images as $index => $image): ?>
                                            <div class="carousel-item <?php echo $index == 0 ? 'active' : ''; ?>">
                                                <img src="<?php echo htmlspecialchars(normalizeStoredImageUrl($image)); ?>" class="d-block w-100" alt="Accommodation Image">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <p><strong>Description:</strong> <?php echo htmlspecialchars($accommodation['description']); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($accommodation['location']); ?></p>
                            <p><strong>Price:</strong> $<?php echo number_format($accommodation['price'], 2); ?>/day</p>
                            <p><strong>Amenities:</strong> <?php echo htmlspecialchars($accommodation['amenities']); ?></p>
                            <p><strong>Landlord:</strong> <?php echo htmlspecialchars($accommodation['company_name'] ?: $accommodation['landlord_name']); ?></p>
                        </div>
                        <div class="modal-footer">
                            <?php if (isLoggedIn() && $_SESSION['user_role'] == 'student'): ?>
                                <button class="btn btn-success" onclick="openBookingModal(<?php echo $accommodation['id']; ?>, <?php echo number_format($accommodation['price'], 2); ?>)">Book Now</button>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-success">Login to Book</a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Book Accommodation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="bookingError" class="alert alert-danger d-none" role="alert"></div>
                <form id="bookingForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" id="booking_accommodation_id" name="accommodation_id">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="booking_price" class="form-label">Daily Price</label>
                                <input type="text" class="form-control" id="booking_price" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="total_amount" class="form-label">Total Amount</label>
                                <input type="text" class="form-control" id="total_amount" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="check_in_date" class="form-label">Check-in Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="check_in_date" name="check_in_date" required onchange="calculateTotal()">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="check_out_date" class="form-label">Check-out Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="check_out_date" name="check_out_date" required onchange="calculateTotal()">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="booking_notes" class="form-label">Special Requests (Optional)</label>
                        <textarea class="form-control" id="booking_notes" name="notes" rows="3" placeholder="Any special requests or questions..."></textarea>
                    </div>

                    <!-- Payment Section -->
                    <hr>
                    <h6><i class="fas fa-credit-card"></i> Payment Information</h6>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Full payment is required to confirm your booking. You can pay using EcoCash, OneMoney, TeleCash, or bank card.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Gateway <span class="text-danger">*</span></label>
                        <div class="paynow-card border rounded-4 p-3">
                            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                <div>
                                    <div class="fw-bold text-white">PayNow Zimbabwe</div>
                                    <small class="text-white-50">Secure wallet and card payments through Zimbabwe’s trusted payment gateway.</small>
                                </div>
                                <div class="paynow-badge">
                                    <i class="fas fa-shield-check me-2"></i> Secure
                                </div>
                            </div>
                            <input type="hidden" name="payment_method" value="ecocash">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitBooking()" id="submitBtn">
                    <i class="fas fa-credit-card"></i> Book & Pay Now
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Negotiation Modal -->
<div class="modal fade" id="negotiationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Negotiate Price</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="negotiationForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" id="negotiation_accommodation_id" name="accommodation_id">
                    <div class="mb-3">
                        <label for="proposed_price" class="form-label">Your Proposed Price ($)</label>
                        <input type="number" class="form-control" id="proposed_price" name="proposed_price" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="negotiation_message" class="form-label">Message (Optional)</label>
                        <textarea class="form-control" id="negotiation_message" name="message" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="submitNegotiation()">Submit Negotiation</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
function openBookingModal(accommodationId, price) {
    $('#booking_accommodation_id').val(accommodationId);
    $('#booking_price').val('$' + parseFloat(price).toFixed(2) + '/day');
    $('#check_in_date').val('');
    $('#check_out_date').val('');
    $('#booking_notes').val('');
    $('#total_amount').val('$0.00');

    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    $('#check_in_date').attr('min', today);
    $('#check_out_date').attr('min', today);

    $('#bookingModal').modal('show');
}

function calculateTotal() {
    const checkIn = new Date($('#check_in_date').val());
    const checkOut = new Date($('#check_out_date').val());
    const price = parseFloat($('#booking_price').val().replace('$', '').replace('/day', ''));

    if (checkIn && checkOut && checkOut > checkIn && price > 0) {
        const days = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
        const total = price * days;
        $('#total_amount').val('$' + total.toFixed(2));
    } else {
        $('#total_amount').val('$0.00');
    }
}

function submitBooking() {
    const form = document.getElementById('bookingForm');
    const formData = new FormData(form);

    // Validate dates
    const checkIn = new Date(formData.get('check_in_date'));
    const checkOut = new Date(formData.get('check_out_date'));

    const bookingError = document.getElementById('bookingError');
    const setBookingError = (message) => {
        if (!bookingError) return;
        bookingError.textContent = message;
        bookingError.classList.remove('d-none');
    };
    const clearBookingError = () => {
        if (!bookingError) return;
        bookingError.textContent = '';
        bookingError.classList.add('d-none');
    };

    clearBookingError();

    if (isNaN(checkIn.valueOf()) || isNaN(checkOut.valueOf())) {
        setBookingError('Please select valid check-in and check-out dates.');
        return;
    }

    if (checkIn >= checkOut) {
        setBookingError('Check-out date must be after check-in date.');
        return;
    }

    // PayNow Zimbabwe is the only payment gateway for this checkout.
    const paymentMethod = 'ecocash';
    formData.set('payment_method', paymentMethod);

    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    submitBtn.disabled = true;

    fetch('api/book_accommodation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Booking created, now initiate payment
            initiatePayment(data.booking_id, paymentMethod);
        } else {
            alert('Error: ' + data.message);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        setBookingError('An error occurred while creating your booking. Please try again.');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function initiatePayment(bookingId, paymentMethod) {
    const bookingError = document.getElementById('bookingError');
    const setBookingError = (message) => {
        if (!bookingError) return;
        bookingError.textContent = message;
        bookingError.classList.remove('d-none');
    };

    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    formData.append('booking_id', bookingId);
    formData.append('payment_method', paymentMethod);

    fetch('api/initiate_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect to Pay Now Zimbabwe
            if (data.payment_url) {
                window.location.href = data.payment_url;
            } else {
                alert('Payment initiated. You will be redirected to complete payment.');
                // Fallback: redirect to payment status page
                window.location.href = 'payment_success.php?reference=' + data.reference;
            }
        } else {
            alert('Payment initiation failed: ' + data.message);
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-credit-card"></i> Book & Pay Now';
            document.getElementById('submitBtn').disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        setBookingError('Payment initiation failed. Please refresh and try again.');
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-credit-card"></i> Book & Pay Now';
            submitBtn.disabled = false;
        }
    });
}

$('.negotiate-btn').on('click', function() {
    const accommodationId = $(this).data('accommodation-id');
    const currentPrice = $(this).data('price');
    $('#negotiation_accommodation_id').val(accommodationId);
    $('#proposed_price').attr('max', currentPrice - 1);
    $('#negotiationModal').modal('show');
});

function submitNegotiation() {
    const formData = new FormData(document.getElementById('negotiationForm'));
    fetch('api/negotiate_price.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Negotiation request sent successfully!');
            $('#negotiationModal').modal('hide');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}
</script>

<?php include 'includes/footer.php'; ?>
