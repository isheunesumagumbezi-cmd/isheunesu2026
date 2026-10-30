<?php
$pageTitle = 'Notifications - Smart Campus Connect';
include 'includes/config.php';
redirectIfNotLoggedIn();

$user = getCurrentUser();
$conn = getDBConnection();

if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $stmt = $conn->prepare("UPDATE notifications SET read_status = 1 WHERE user_id = ?");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stmt->close();
    header('Location: notifications.php');
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    $notification_id = (int)$_GET['id'];
    if ($notification_id > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET read_status = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $notification_id, $user['id']);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: notifications.php');
    exit();
}

$stmt = $conn->prepare("SELECT id, title, message, type, read_status, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Notifications</h2>
        <p class="text-muted">View all your notifications and stay updated on bookings, messages, and system alerts.</p>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-end">
        <a href="notifications.php?action=mark_all_read" class="btn btn-outline-primary">Mark all as read</a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <?php if (empty($notifications)): ?>
            <div class="alert alert-info">You have no notifications yet.</div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $badgeClass = 'secondary';
                    switch ($notification['type']) {
                        case 'success': $badgeClass = 'success'; break;
                        case 'warning': $badgeClass = 'warning'; break;
                        case 'error': $badgeClass = 'danger'; break;
                        default: $badgeClass = 'info'; break;
                    }
                    ?>
                    <div class="list-group-item list-group-item-action <?php echo $notification['read_status'] ? '' : 'border-primary'; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">
                                <?php echo htmlspecialchars($notification['title'] ?: 'Notification'); ?>
                                <span class="badge bg-<?php echo $badgeClass; ?> ms-2 text-capitalize"><?php echo htmlspecialchars($notification['type']); ?></span>
                            </h5>
                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                        <?php if (!$notification['read_status']): ?>
                            <small class="text-primary">Unread</small>
                            <a href="notifications.php?action=mark_read&id=<?php echo (int)$notification['id']; ?>" class="btn btn-sm btn-outline-success">Mark as read</a>
                        <?php else: ?>
                            <small class="text-muted">Read</small>
                        <?php endif; ?>
                    </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>