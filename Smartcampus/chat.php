<?php
$pageTitle = 'Chat - Smart Campus Connect';
include 'includes/config.php';
redirectIfNotLoggedIn();

$user = getCurrentUser();
if (!in_array($user['role'], ['student', 'landlord'], true)) {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();
$partners = [];
$partner_label = $user['role'] === 'student' ? 'landlord' : 'student';

if ($user['role'] === 'student') {
    $partner_stmt = $conn->prepare(
        "SELECT DISTINCT u.id AS user_id, u.name, u.email
         FROM users u
         JOIN landlords l ON u.id = l.user_id
         WHERE l.verification_status = 'verified'
         UNION
         SELECT DISTINCT u.id AS user_id, u.name, u.email
         FROM users u
         JOIN messages m ON ((m.sender_id = u.id AND m.receiver_id = ?) OR (m.receiver_id = u.id AND m.sender_id = ?))
         WHERE u.role = 'landlord'
         ORDER BY name"
    );
    $partner_stmt->bind_param('ii', $user['id'], $user['id']);
    $partner_stmt->execute();
    $partner_result = $partner_stmt->get_result();
    if ($partner_result) {
        $partners = $partner_result->fetch_all(MYSQLI_ASSOC);
    }
    $partner_stmt->close();
} else {
    $partner_stmt = $conn->prepare(
        "SELECT DISTINCT u.id AS user_id, u.name, u.email
         FROM users u
         JOIN students s ON u.id = s.user_id
         JOIN accommodation_bookings ab ON ab.student_id = s.id
         JOIN accommodations a ON ab.accommodation_id = a.id
         JOIN landlords l ON a.landlord_id = l.id
         WHERE l.user_id = ?
         UNION
         SELECT DISTINCT u.id AS user_id, u.name, u.email
         FROM users u
         JOIN students s ON u.id = s.user_id
         JOIN messages m ON ((m.sender_id = u.id AND m.receiver_id = ?) OR (m.receiver_id = u.id AND m.sender_id = ?))
         WHERE u.role = 'student'
         ORDER BY name"
    );
    $partner_stmt->bind_param('iii', $user['id'], $user['id'], $user['id']);
    $partner_stmt->execute();
    $partner_result = $partner_stmt->get_result();
    if ($partner_result) {
        $partners = $partner_result->fetch_all(MYSQLI_ASSOC);
    }
    $partner_stmt->close();
}

$partner_ids = array_column($partners, 'user_id');
$selected_partner_id = (int)($_GET['partner_id'] ?? ($partners[0]['user_id'] ?? 0));
if ($selected_partner_id <= 0 || !in_array($selected_partner_id, $partner_ids, true)) {
    $selected_partner_id = (int)($partners[0]['user_id'] ?? 0);
}
$messages = [];
if ($selected_partner_id > 0) {
    $msg_stmt = $conn->prepare("SELECT m.id, m.sender_id, m.receiver_id, m.message, m.sent_at, m.read_status, u.name as sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)) ORDER BY m.sent_at ASC");
    $msg_stmt->bind_param('iiii', $user['id'], $selected_partner_id, $selected_partner_id, $user['id']);
    $msg_stmt->execute();
    $messages = $msg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $msg_stmt->close();

    $read_stmt = $conn->prepare("UPDATE messages SET read_status = 1 WHERE sender_id = ? AND receiver_id = ? AND read_status = 0");
    $read_stmt->bind_param('ii', $selected_partner_id, $user['id']);
    $read_stmt->execute();
    $read_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $message_text = sanitizeInput($_POST['message'] ?? '');
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Location: chat.php?partner_id=' . $selected_partner_id);
        exit();
    }
    if ($receiver_id > 0 && $message_text !== '' && in_array($receiver_id, $partner_ids, true)) {
        $insert_stmt = $conn->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)');
        $insert_stmt->bind_param('iis', $user['id'], $receiver_id, $message_text);
        $insert_stmt->execute();
        $insert_stmt->close();

        createReminderNotification($conn, $receiver_id, 'New message', "You have a new message from {$user['name']}", 'info');
        header('Location: chat.php?partner_id=' . $receiver_id);
        exit();
    }
}

$conn->close();
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Chat with <?php echo ucfirst($partner_label); ?></h4>
                <span class="text-muted">Secure direct conversations</span>
            </div>
            <div class="card-body">
                <?php if (!empty($partners)): ?>
                    <form method="GET" class="mb-3">
                        <label for="partner_id" class="form-label">Select <?php echo ucfirst($partner_label); ?></label>
                        <select class="form-select" id="partner_id" name="partner_id" onchange="this.form.submit()">
                            <?php foreach ($partners as $partner): ?>
                                <option value="<?php echo (int)$partner['user_id']; ?>" <?php echo $selected_partner_id === (int)$partner['user_id'] ? 'selected' : ''; ?>><?php echo e($partner['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">No <?php echo e($partner_label); ?>s are available yet. Start by creating a booking or waiting for a message.</div>
                <?php endif; ?>

                <div class="border rounded p-3 mb-3" style="min-height: 260px; max-height: 360px; overflow-y: auto;">
                    <?php if (empty($messages)): ?>
                        <div class="text-muted">Start the conversation with this <?php echo htmlspecialchars($partner_label); ?>.</div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="mb-2">
                                <strong><?php echo e($message['sender_name']); ?></strong>
                                <div class="small text-muted"><?php echo date('M d, H:i', strtotime($message['sent_at'])); ?></div>
                                <div><?php echo e($message['message']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($partners)): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="receiver_id" value="<?php echo (int)$selected_partner_id; ?>">
                        <textarea class="form-control" name="message" rows="3" placeholder="Type your message here..."></textarea>
                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
