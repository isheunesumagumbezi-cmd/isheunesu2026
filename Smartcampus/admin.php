<?php
$pageTitle = "Admin Panel - Smart Campus Connect";
include 'includes/config.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    header("Location: login.php");
    exit();
}

include 'includes/header.php';

$conn = getDBConnection();

// Get statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'total_accommodations' => $conn->query("SELECT COUNT(*) as count FROM accommodations")->fetch_assoc()['count'],
    'total_bus_bookings' => $conn->query("SELECT COUNT(*) as count FROM bus_bookings WHERE status = 'confirmed'")->fetch_assoc()['count']
];

// Get recent activities
$recent_users = $conn->query("SELECT name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$recent_bookings = $conn->query("SELECT bb.id, u.name as student_name, b.route, bb.booking_date FROM bus_bookings bb JOIN buses b ON bb.bus_id = b.id JOIN students s ON bb.student_id = s.id JOIN users u ON s.user_id = u.id ORDER BY bb.booking_date DESC LIMIT 5");
$pending_landlords = $conn->query("SELECT l.id, l.verification_status, l.verification_notes, l.national_id_path, l.proof_of_residence_path, u.name, u.email FROM landlords l JOIN users u ON l.user_id = u.id WHERE l.verification_status IN ('pending', 'rejected') ORDER BY l.submitted_at DESC");

$conn->close();
?>

<div class="row">
    <div class="col-12">
        <h2>Admin Dashboard</h2>
        <p class="text-muted">Manage the Smart Campus Connect platform</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center dashboard-stats">
            <div class="card-body">
                <h3 class="card-title"><?php echo $stats['total_users']; ?></h3>
                <p class="card-text">Total Users</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center dashboard-stats">
            <div class="card-body">
                <h3 class="card-title"><?php echo $stats['total_accommodations']; ?></h3>
                <p class="card-text">Accommodations</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center dashboard-stats">
            <div class="card-body">
                <h3 class="card-title"><?php echo $stats['total_bus_bookings']; ?></h3>
                <p class="card-text">Bus Bookings</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Recent Users</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $recent_users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo ucfirst($user['role']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Recent Bus Bookings</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Student</th>
                                <th>Route</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['route']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5>Pending Landlord Verification</h5>
            </div>
            <div class="card-body">
                <?php if ($pending_landlords->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Landlord</th>
                                    <th>Email</th>
                                    <th>National ID</th>
                                    <th>Proof of Residence</th>
                                    <th>Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($landlord = $pending_landlords->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($landlord['name']); ?></td>
                                        <td><?php echo htmlspecialchars($landlord['email']); ?></td>
                                        <td><?php echo !empty($landlord['national_id_path']) ? '<a href="' . htmlspecialchars($landlord['national_id_path']) . '" target="_blank">View</a>' : 'Not uploaded'; ?></td>
                                        <td><?php echo !empty($landlord['proof_of_residence_path']) ? '<a href="' . htmlspecialchars($landlord['proof_of_residence_path']) . '" target="_blank">View</a>' : 'Not uploaded'; ?></td>
                                        <td><?php echo htmlspecialchars($landlord['verification_notes'] ?? ''); ?></td>
                                        <td>
                                            <form method="POST" action="api/verify_landlord.php" class="d-flex flex-wrap gap-2 align-items-center">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="landlord_id" value="<?php echo (int)$landlord['id']; ?>">
                                                <input type="text" class="form-control form-control-sm" name="notes" placeholder="Notes" value="<?php echo e($landlord['verification_notes'] ?? ''); ?>">
                                                <button type="submit" name="decision" value="verified" class="btn btn-sm btn-success">Approve</button>
                                                <button type="submit" name="decision" value="rejected" class="btn btn-sm btn-danger">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">No landlord verification requests are pending.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="manage_users.php" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-users"></i> Manage Users
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="manage_accommodations.php" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-home"></i> Manage Accommodations
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="manage_exams.php" class="btn btn-warning btn-lg w-100">
                            <i class="fas fa-graduation-cap"></i> Manage Exams
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
