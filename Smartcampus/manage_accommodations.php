<?php
$pageTitle = "Manage Accommodations - Smart Campus Connect";
include 'includes/config.php';
redirectIfNotLoggedIn();

$user = getCurrentUser();
if ($user['role'] !== 'landlord') {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();
$landlord_id = 0;
$stmt = $conn->prepare("SELECT id FROM landlords WHERE user_id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stmt->bind_result($landlord_id);
$stmt->fetch();
$stmt->close();

$accommodations = [];
if ($landlord_id) {
    $stmt = $conn->prepare("SELECT id, title, description, price, location, available, images, created_at FROM accommodations WHERE landlord_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $landlord_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $accommodations = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$conn->close();

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2>Manage Accommodations</h2>
            <p class="text-muted">Review and manage your accommodation listings.</p>
        </div>
        <a href="add_accommodation.php" class="btn btn-primary">Add New Accommodation</a>
    </div>
</div>

<?php if (empty($accommodations)): ?>
    <div class="alert alert-info">You don't have any accommodation listings yet. Add one to get started.</div>
<?php else: ?>
    <div class="row">
        <?php foreach ($accommodations as $accommodation): ?>
            <?php
            $images = json_decode($accommodation['images'] ?? '[]', true);
            $image_url = normalizeStoredImageUrl('');
            if (!empty($images) && is_array($images)) {
                $image_url = normalizeStoredImageUrl($images[0]) . '?v=' . time();
            }
            ?>
            <div class="col-lg-6 col-xl-4 mb-4">
                <div class="card h-100">
                    <img src="<?php echo htmlspecialchars($image_url); ?>" class="card-img-top" alt="Accommodation">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($accommodation['title']); ?></h5>
                        <p class="card-text text-muted small"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($accommodation['location']); ?></p>
                        <p class="card-text"><strong>$<?php echo number_format($accommodation['price'], 2); ?>/month</strong></p>
                        <p class="card-text"><?php echo htmlspecialchars(substr($accommodation['description'], 0, 85)); ?>...</p>
                        <div class="mt-auto">
                            <div class="btn-group w-100" role="group">
                                <a href="accommodation.php" class="btn btn-outline-primary btn-sm">View</a>
                                <a href="add_accommodation.php?id=<?php echo (int)$accommodation['id']; ?>" class="btn btn-outline-secondary btn-sm">Edit</a>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteAccommodation(<?php echo (int)$accommodation['id']; ?>)">Delete</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <small class="text-<?php echo $accommodation['available'] ? 'success' : 'danger'; ?>"><?php echo $accommodation['available'] ? 'Available' : 'Hidden'; ?></small>
                        <small class="text-muted"><?php echo date('M d, Y', strtotime($accommodation['created_at'])); ?></small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function deleteAccommodation(id) {
    if (!confirm('Delete this accommodation listing?')) {
        return;
    }

    fetch('api/delete_accommodation.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            id: id,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(() => alert('Delete failed. Please try again.'));
}
</script>

<?php include 'includes/footer.php'; ?>
