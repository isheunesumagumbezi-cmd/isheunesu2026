<?php
$pageTitle = "Home - Smart Campus Connect";
include 'includes/config.php';
include 'includes/header.php';

// Get some stats for the homepage
$conn = getDBConnection();
$stats = [
    'accommodations' => $conn->query("SELECT COUNT(*) as count FROM accommodations WHERE available = 1")->fetch_assoc()['count'],
    'bus_routes' => $conn->query("SELECT COUNT(DISTINCT route) as count FROM buses")->fetch_assoc()['count'],
    'students' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count']
];
$conn->close();
?>

<div class="hero-shell mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="page-kicker"><i class="fas fa-graduation-cap me-2"></i> Campus life, simplified</span>
            <h1 class="display-5 fw-bold mb-3">Everything your campus journey needs in one smart platform.</h1>
            <p class="lead text-white-50 mb-4">Secure housing, reliable transport, smooth bookings, and exam access—all designed for a more confident, connected student experience.</p>
            <div class="d-flex flex-wrap gap-3">
                <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-light btn-lg premium-cta">Get Started</a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-light btn-lg premium-cta">Open Dashboard</a>
                <?php endif; ?>
                <a href="accommodation.php" class="btn btn-outline-light btn-lg">Explore listings</a>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="hero-panel-card">
                <div class="hero-metric">
                    <span class="label">Live inventory</span>
                    <strong><?php echo $stats['accommodations']; ?></strong>
                    <small>Verified listings ready now</small>
                </div>
                <div class="hero-mini-grid">
                    <div>
                        <i class="fas fa-bus"></i>
                        <span><?php echo $stats['bus_routes']; ?> routes</span>
                    </div>
                    <div>
                        <i class="fas fa-user-graduate"></i>
                        <span><?php echo $stats['students']; ?> students</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 g-3">
    <div class="col-md-4">
        <div class="card dashboard-stats h-100">
            <div class="card-body">
                <div class="stat-icon"><i class="fas fa-house-user"></i></div>
                <h3 class="card-title"><?php echo $stats['accommodations']; ?></h3>
                <p class="card-text">Available accommodations</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-stats h-100">
            <div class="card-body">
                <div class="stat-icon"><i class="fas fa-route"></i></div>
                <h3 class="card-title"><?php echo $stats['bus_routes']; ?></h3>
                <p class="card-text">Bus routes</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card dashboard-stats h-100">
            <div class="card-body">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <h3 class="card-title"><?php echo $stats['students']; ?></h3>
                <p class="card-text">Community members</p>
            </div>
        </div>
    </div>
</div>

<div class="showcase-grid row g-4">
    <div class="col-md-6">
        <div class="card feature-card accommodation-card mb-0">
            <img src="https://images.unsplash.com/photo-1560185127-6ed189bf02f4?auto=format&fit=crop&w=900&q=80" class="card-img-top" alt="Student accommodation">
            <div class="card-body">
                <span class="section-tag">Housing</span>
                <h5 class="card-title">Find your perfect accommodation</h5>
                <p class="card-text">Browse verified listings with clear pricing, flexible options, and hassle-free booking support.</p>
                <a href="accommodation.php" class="btn btn-primary feature-cta">Explore listings</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card feature-card bus-card mb-0">
            <img src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=900&q=80" class="card-img-top" alt="Bus travel">
            <div class="card-body">
                <span class="section-tag alt">Transport</span>
                <h5 class="card-title">Book bus tickets in minutes</h5>
                <p class="card-text">Reserve seats quickly, compare routes, and keep every trip organized from a single dashboard.</p>
                <a href="bus.php" class="btn btn-primary feature-cta">Book now</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card feature-card exam-card mb-0">
            <img src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=900&q=80" class="card-img-top" alt="Exam venue">
            <div class="card-body">
                <span class="section-tag">Campus access</span>
                <h5 class="card-title">Locate exam venues with confidence</h5>
                <p class="card-text">See locations clearly and save time when you need to move fast before your next exam.</p>
                <a href="exam.php" class="btn btn-primary feature-cta">Find venues</a>
            </div>
        </div>
    </div>
</div>

<?php if (isLoggedIn()): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5>Recent Activity</h5>
            </div>
            <div class="card-body">
                <p>Welcome back, <?php echo $_SESSION['user_name']; ?>! Check your dashboard for the latest updates.</p>
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
