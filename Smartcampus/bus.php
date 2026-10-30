<?php
$pageTitle = "Bus Booking - Smart Campus Connect";
include 'includes/config.php';
include 'includes/header.php';

function formatBusDate($value) {
    if (empty($value)) {
        return 'TBA';
    }
    $timestamp = strtotime((string)$value);
    return $timestamp ? date('M d, Y', $timestamp) : 'TBA';
}

function formatBusTime($value) {
    if (empty($value)) {
        return 'TBA';
    }
    $timestamp = strtotime((string)$value);
    return $timestamp ? date('H:i', $timestamp) : 'TBA';
}

$conn = getDBConnection();

// Get filter parameters
$route = isset($_GET['route']) ? sanitizeInput($_GET['route']) : '';
$date = isset($_GET['date']) ? sanitizeInput($_GET['date']) : '';
$dateError = '';
$dateValid = false;
if ($date !== '') {
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if ($dateObj && $dateObj->format('Y-m-d') === $date) {
        $dateValid = true;
    } else {
        $dateError = 'The travel date was invalid and has been ignored.';
        $date = '';
    }
}

// Build query
$query = "SELECT b.*, bc.company_name FROM buses b 
          JOIN bus_companies bc ON b.bus_company_id = bc.id 
          WHERE b.available_seats > 0";

$params = [];
$types = '';

if (!empty($route)) {
    $query .= " AND b.route LIKE ?";
    $params[] = "%$route%";
    $types .= 's';
}

if (!empty($date)) {
    $query .= " AND b.date = ?";
    $params[] = $date;
    $types .= 's';
}

$query .= " ORDER BY b.date, b.departure_time LIMIT 20";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$buses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<div class="bus-hero mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="page-kicker"><i class="fas fa-bus me-2"></i> Smart Campus travel</span>
            <h1 class="display-5 fw-bold mb-3">Book your next ride with ease.</h1>
            <p class="lead text-white-50 mb-4">Discover safe, affordable, and comfortable routes across Zimbabwe with trusted operators, flexible dates, and a smooth booking experience.</p>
            <div class="bus-hero-stats">
                <div>
                    <strong>20+</strong>
                    <span>Routes</span>
                </div>
                <div>
                    <strong>24/7</strong>
                    <span>Support</span>
                </div>
                <div>
                    <strong>100%</strong>
                    <span>Verified</span>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="bus-hero-panel">
                <div class="bus-hero-panel-header">
                    <span class="status-dot"></span>
                    <span>Live route availability</span>
                </div>
                <div class="bus-hero-panel-body">
                    <div class="route-mini">
                        <span>Harare</span>
                        <i class="fas fa-arrow-right"></i>
                        <span>Chinhoyi</span>
                    </div>
                    <p class="mb-3">From the campus to your destination, with seat selection and instant confirmation.</p>
                    <div class="hero-price-row">
                        <span>From</span>
                        <strong>$18.00</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bus-search-card">
            <div class="card-body">
                <form method="GET" action="" class="bus-search-form">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="route" class="form-label">Route</label>
                            <div class="input-wrap">
                                <i class="fas fa-route"></i>
                                <input type="text" class="form-control" id="route" name="route" value="<?php echo e($route); ?>" placeholder="e.g. Harare to Chinhoyi">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label for="date" class="form-label">Travel Date</label>
                            <div class="input-wrap">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo e($date); ?>" min="<?php echo date('Y-m-d'); ?>">
                            </div>
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

<!-- Buses List -->
<div class="row">
    <?php if ($dateError): ?>
        <div class="col-12 mb-3">
            <div class="alert alert-warning mb-0">
                <?php echo htmlspecialchars($dateError); ?>
            </div>
        </div>
    <?php endif; ?>
    <?php if (empty($buses)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                No bus routes found matching your criteria. Try adjusting your search terms.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($buses as $bus): ?>
            <?php
            $storedTransportImage = trim((string)($bus['transport_image'] ?? ''));
            $transportImage = $storedTransportImage === ''
                ? 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=900&q=80'
                : normalizeStoredImageUrl($storedTransportImage);
            ?>
            <div class="col-md-6 mb-4">
                <div class="card bus-card h-100">
                    <div class="bus-card-image-wrap">
                        <img src="<?php echo e($transportImage); ?>" class="card-img-top" alt="<?php echo e($bus['route'] ?? ''); ?> transport">
                        <div class="bus-price-badge">$<?php echo number_format($bus['price'], 2); ?></div>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="bus-card-header-row">
                            <div>
                                <span class="company-pill"><?php echo e($bus['company_name'] ?? ''); ?></span>
                                <h5 class="card-title mt-3"><?php echo e($bus['route'] ?? ''); ?></h5>
                            </div>
                            <span class="seat-pill"><?php echo (int)($bus['available_seats'] ?? 0); ?> seats</span>
                        </div>
                        <div class="bus-meta-grid mt-3">
                            <div><i class="fas fa-calendar-day"></i> <?php echo formatBusDate($bus['date'] ?? ''); ?></div>
                            <div><i class="fas fa-clock"></i> <?php echo formatBusTime($bus['departure_time'] ?? ''); ?> → <?php echo formatBusTime($bus['arrival_time'] ?? ''); ?></div>
                        </div>
                        <div class="mt-auto d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-primary flex-fill" data-bs-toggle="modal" data-bs-target="#busModal<?php echo $bus['id']; ?>">
                                View Details
                            </button>
                            <?php if (isLoggedIn() && $_SESSION['user_role'] == 'student'): ?>
                                <button type="button" class="btn btn-outline-primary book-bus-btn flex-fill" data-bus-id="<?php echo $bus['id']; ?>" data-available-seats="<?php echo $bus['available_seats']; ?>">
                                    Book Now
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bus Modal -->
            <div class="modal fade" id="busModal<?php echo $bus['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?php echo e($bus['route'] ?? ''); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <img src="<?php echo e($transportImage); ?>" class="img-fluid rounded mb-3" alt="<?php echo e($bus['route'] ?? ''); ?> transport">
                            <p><strong>Company:</strong> <?php echo e($bus['company_name'] ?? ''); ?></p>
                            <p><strong>Date:</strong> <?php echo formatBusDate($bus['date'] ?? ''); ?></p>
                            <p><strong>Departure Time:</strong> <?php echo formatBusTime($bus['departure_time'] ?? ''); ?></p>
                            <p><strong>Arrival Time:</strong> <?php echo formatBusTime($bus['arrival_time'] ?? ''); ?></p>
                            <p><strong>Price per Seat:</strong> $<?php echo number_format($bus['price'], 2); ?></p>
                            <p><strong>Available Seats:</strong> <?php echo $bus['available_seats']; ?>/<?php echo $bus['total_seats']; ?></p>
                        </div>
                        <div class="modal-footer">
                            <?php if (isLoggedIn() && $_SESSION['user_role'] == 'student'): ?>
                                <button type="button" class="btn btn-success" onclick="bookBus(<?php echo $bus['id']; ?>, <?php echo $bus['available_seats']; ?>)">Book Now</button>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Book Bus Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bookingForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" id="booking_bus_id" name="bus_id">
                    <div class="mb-3">
                        <label for="seats" class="form-label">Number of Seats</label>
                        <input type="number" class="form-control" id="seats" name="seats" min="1" max="5" value="1" required>
                        <div class="form-text">Maximum 5 seats per booking</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="submitBooking()">Confirm Booking</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
function bookBus(busId, availableSeats) {
    $('#booking_bus_id').val(busId);
    $('#seats').attr('max', Math.min(5, availableSeats));
    $('#seats').val(1);
    $('#bookingModal').modal('show');
}

function submitBooking() {
    const form = document.getElementById('bookingForm');
    const formData = new FormData(form);
    const busId = formData.get('bus_id');
    const seats = parseInt(formData.get('seats') || '0', 10);

    if (!busId || !seats || seats < 1) {
        alert('Please select a valid number of seats.');
        return;
    }

    const submitBtn = document.querySelector('#bookingModal .btn-primary');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    fetch('api/book_bus.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const paymentForm = new FormData();
            paymentForm.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
            paymentForm.append('booking_id', data.booking_id);
            paymentForm.append('payment_method', 'ecocash');
            paymentForm.append('booking_type', 'bus');

            return fetch('api/initiate_payment.php', {
                method: 'POST',
                body: paymentForm
            }).then(payResponse => payResponse.json()).then(payData => {
                if (payData.success && payData.payment_url) {
                    $('#bookingModal').modal('hide');
                    window.location.href = payData.payment_url;
                    return;
                }

                throw new Error(payData.message || 'Payment initiation failed');
            });
        }

        throw new Error(data.message || 'Booking failed');
    })
    .then(() => {
        // success handled by redirect
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + (error.message || 'Unable to complete bus booking.'));
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

$(document).on('click', '.book-bus-btn', function() {
    const busId = $(this).data('bus-id');
    const availableSeats = $(this).data('available-seats');
    bookBus(busId, availableSeats);
});
</script>

<?php include 'includes/footer.php'; ?>
