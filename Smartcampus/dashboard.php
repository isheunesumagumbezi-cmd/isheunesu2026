<?php
$pageTitle = "Dashboard - Smart Campus Connect";
include 'includes/config.php';
redirectIfNotLoggedIn();

$user = getCurrentUser();
$role = $user['role'];

include 'includes/header.php';

// Get dashboard data based on role
$conn = getDBConnection();
$dashboardData = [];

switch ($role) {
    case 'student':
        $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $student_id = $result->fetch_assoc()['id'];
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM accommodation_bookings WHERE student_id = ? AND status = 'confirmed'");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $bookings = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bus_bookings WHERE student_id = ? AND status = 'confirmed'");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $bus_bookings = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_status = 0");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $dashboardData = [
            'bookings' => $bookings,
            'bus_bookings' => $bus_bookings,
            'notifications' => $notifications
        ];
        break;
    case 'landlord':
        $stmt = $conn->prepare("SELECT id FROM landlords WHERE user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $landlord_id = $result->fetch_assoc()['id'];
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM accommodations WHERE landlord_id = ?");
        $stmt->bind_param("i", $landlord_id);
        $stmt->execute();
        $listings = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM accommodation_bookings ab JOIN accommodations a ON ab.accommodation_id = a.id WHERE a.landlord_id = ? AND ab.status = 'pending'");
        $stmt->bind_param("i", $landlord_id);
        $stmt->execute();
        $bookings = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_status = 0");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $dashboardData = [
            'listings' => $listings,
            'bookings' => $bookings,
            'negotiations' => 0, // Placeholder for negotiations
            'notifications' => $notifications
        ];
        break;
    case 'bus_company':
        $stmt = $conn->prepare("SELECT id FROM bus_companies WHERE user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $bus_company_id = $result->fetch_assoc()['id'];
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT route) as count FROM buses WHERE bus_company_id = ?");
        $stmt->bind_param("i", $bus_company_id);
        $stmt->execute();
        $routes = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bus_bookings bb JOIN buses b ON bb.bus_id = b.id WHERE b.bus_company_id = ? AND bb.status = 'confirmed'");
        $stmt->bind_param("i", $bus_company_id);
        $stmt->execute();
        $bookings = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT SUM(b.price * bb.seats_booked) as total FROM bus_bookings bb JOIN buses b ON bb.bus_id = b.id WHERE b.bus_company_id = ? AND bb.status = 'confirmed'");
        $stmt->bind_param("i", $bus_company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $revenue = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_status = 0");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $dashboardData = [
            'routes' => $routes,
            'bookings' => $bookings,
            'revenue' => $revenue,
            'notifications' => $notifications
        ];
        break;
    case 'admin':
        $dashboardData = [
            'users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
            'accommodations' => $conn->query("SELECT COUNT(*) as count FROM accommodations")->fetch_assoc()['count'],
            'bus_bookings' => $conn->query("SELECT COUNT(*) as count FROM bus_bookings WHERE status = 'confirmed'")->fetch_assoc()['count']
        ];
        break;
}

$conn->close();

$roleLabel = ucfirst(str_replace('_', ' ', $role));
$statIcons = [
    'bookings' => 'fa-calendar-check',
    'bus_bookings' => 'fa-ticket-alt',
    'notifications' => 'fa-bell',
    'listings' => 'fa-building',
    'negotiations' => 'fa-handshake',
    'routes' => 'fa-route',
    'revenue' => 'fa-wallet',
    'users' => 'fa-users',
    'accommodations' => 'fa-house-user'
];
?>

<div class="row mb-4 align-items-center page-heading">
    <div class="col-12">
        <div class="page-kicker">Dashboard</div>
        <h2>Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h2>
        <p class="text-muted mb-0">
            <span class="role-pill"><i class="fas fa-id-badge"></i> <?php echo $roleLabel; ?></span>
        </p>
    </div>
</div>

<div class="card hero-panel mb-4 dashboard-hero-panel">
    <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
        <div>
            <h5 class="mb-2"><i class="fas fa-shield-alt"></i> Your campus hub is ready</h5>
            <p class="mb-0 text-muted">
                Keep track of bookings, notifications, and opportunities in one place. Everything is organized to make daily tasks faster and clearer.
            </p>
        </div>
        <div class="text-lg-end">
            <div class="badge bg-success">Live</div>
            <div class="small text-muted mt-2">Updated automatically with your latest activity.</div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <?php foreach ($dashboardData as $key => $value): ?>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card text-center dashboard-stats h-100">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas <?php echo $statIcons[$key] ?? 'fa-chart-simple'; ?>"></i>
                    </div>
                    <h3 class="card-title"><?php echo $value; ?></h3>
                    <p class="card-text"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card quick-actions-card">
            <div class="card-header">
                <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if ($role == 'student'): ?>
                        <div class="col-md-6 mb-3">
                            <a href="accommodation.php" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-home"></i> Find Accommodation
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="my_bookings.php" class="btn btn-info btn-lg w-100">
                                <i class="fas fa-calendar"></i> My Bookings
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="bus.php" class="btn btn-warning btn-lg w-100">
                                <i class="fas fa-bus"></i> Book Bus Ticket
                            </a>
                        </div>
                    <?php elseif ($role == 'landlord'): ?>
                        <div class="col-md-4 mb-3">
                            <a href="add_accommodation.php" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-plus"></i> Add Accommodation
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="manage_accommodations.php" class="btn btn-info btn-lg w-100">
                                <i class="fas fa-edit"></i> Manage Listings
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="manage_bookings.php" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-list"></i> Manage Bookings
                            </a>
                        </div>
                    <?php elseif ($role == 'bus_company'): ?>
                        <div class="col-md-4 mb-3">
                            <a href="add_bus.php" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-plus"></i> Add Bus Route
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="manage_buses.php" class="btn btn-info btn-lg w-100">
                                <i class="fas fa-edit"></i> Manage Routes
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="manage_bus_bookings.php" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-ticket-alt"></i> Manage Bookings
                            </a>
                        </div>
                    <?php elseif ($role == 'admin'): ?>
                        <div class="col-md-4 mb-3">
                            <a href="admin.php" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-cog"></i> Admin Panel
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="manage_users.php" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="manage_exams.php" class="btn btn-warning btn-lg w-100">
                                <i class="fas fa-map-location-dot"></i> Manage Exams
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card notifications-card">
            <div class="card-header">
                <h5><i class="fas fa-inbox"></i> Recent Notifications</h5>
            </div>
            <div class="card-body">
                <?php
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $notifications = $stmt->get_result();
                if ($notifications->num_rows > 0):
                    while ($notification = $notifications->fetch_assoc()):
                ?>
                    <div class="alert alert-<?php echo $notification['type']; ?> mb-2">
                        <small><?php echo htmlspecialchars($notification['message']); ?></small>
                        <br><small class="text-muted"><?php echo date('M d, H:i', strtotime($notification['created_at'])); ?></small>
                    </div>
                <?php
                    endwhile;
                else:
                ?>
                    <p class="text-muted">No recent notifications.</p>
                <?php endif; ?>
                <a href="notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
