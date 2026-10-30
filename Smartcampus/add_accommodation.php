<?php
$pageTitle = "Add Accommodation - Smart Campus Connect";
include 'includes/config.php';

// Check if user is logged in and is a landlord
redirectIfNotLoggedIn();
$user = getCurrentUser();
if ($user['role'] !== 'landlord') {
    header("Location: dashboard.php");
    exit();
}

include 'includes/header.php';

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id FROM landlords WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$landlord_id = $result->fetch_assoc()['id'];
$stmt->close();

$verification_status = 'pending';
$verification_stmt = $conn->prepare("SELECT verification_status FROM landlords WHERE user_id = ?");
$verification_stmt->bind_param("i", $user['id']);
$verification_stmt->execute();
$verification_result = $verification_stmt->get_result();
if ($verification_result && $verification_result->num_rows > 0) {
    $verification_status = $verification_result->fetch_assoc()['verification_status'] ?? 'pending';
}
$verification_stmt->close();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $location = sanitizeInput($_POST['location'] ?? '');
    $latitude = (float)($_POST['latitude'] ?? 0);
    $longitude = (float)($_POST['longitude'] ?? 0);
    $amenities = sanitizeInput($_POST['amenities'] ?? '');

    // Validate inputs
    if ($verification_status !== 'verified') {
        $error = "Your landlord verification is pending. Upload your national ID and proof of residence before your listing can go live.";
    } elseif (empty($title) || empty($price) || empty($location)) {
        $error = "Title, price, and location are required.";
    } elseif ($price <= 0) {
        $error = "Price must be greater than 0.";
    } else {
        // Handle image uploads
        $images = [];
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $upload_dir = __DIR__ . '/uploads/accommodations/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if (($_FILES['images']['error'][$key] ?? 4) == 0) {
                    $file_type = $_FILES['images']['type'][$key];
                    $file_size = $_FILES['images']['size'][$key];

                    if (!in_array($file_type, $allowed_types)) {
                        $error = "Only JPEG, PNG, GIF, and WebP images are allowed.";
                        break;
                    }

                    if ($file_size > $max_size) {
                        $error = "Image size must be less than 5MB.";
                        break;
                    }

                    $file_name = time() . '_' . basename($_FILES['images']['name'][$key]);
                    $file_path = $upload_dir . $file_name;

                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $images[] = 'uploads/accommodations/' . $file_name;
                    }
                }
            }
        }

        if (!empty($error)) {
            // Error already set
        } else {
            $images_json = json_encode($images);

            // Insert into database
            $stmt = $conn->prepare(
                "INSERT INTO accommodations (landlord_id, title, description, price, location, latitude, longitude, amenities, images, available)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
            );
            
            $stmt->bind_param(
                "issdsddss",
                $landlord_id,
                $title,
                $description,
                $price,
                $location,
                $latitude,
                $longitude,
                $amenities,
                $images_json
            );

            if ($stmt->execute()) {
                $success = "Accommodation added successfully!";
                // Clear form
                $title = $description = $location = $amenities = '';
                $price = $latitude = $longitude = 0;
            } else {
                $error = "Failed to add accommodation: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Add New Accommodation</h2>
        <p class="text-muted">List your property to attract student renters.</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <?php
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT verification_status, verification_notes FROM landlords WHERE user_id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $verification = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $conn->close();
                if (!empty($verification) && $verification['verification_status'] !== 'verified'):
                ?>
                <div class="alert alert-warning">
                    <strong>Landlord verification is required before listings appear publicly.</strong>
                    <?php if (!empty($verification['verification_notes'])): ?><div class="small mt-2"><?php echo e($verification['verification_notes']); ?></div><?php endif; ?>
                </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" id="accommodationForm">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                        <small class="text-muted">e.g., Cozy Studio Near Campus</small>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <small class="text-muted">Describe your accommodation in detail</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label">Price per Day ($) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="location" name="location" required value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="latitude" class="form-label">Latitude</label>
                            <input type="number" class="form-control" id="latitude" name="latitude" step="0.0000001" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="longitude" class="form-label">Longitude</label>
                            <input type="number" class="form-control" id="longitude" name="longitude" step="0.0000001" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="amenities" class="form-label">Amenities</label>
                        <input type="text" class="form-control" id="amenities" name="amenities" value="<?php echo htmlspecialchars($_POST['amenities'] ?? ''); ?>">
                        <small class="text-muted">Separate multiple amenities with commas (e.g., WiFi, Parking, Gym)</small>
                    </div>

                    <div class="mb-3">
                        <label for="images" class="form-label">Images</label>
                        <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                        <small class="text-muted">You can upload multiple images. Recommended: 1-5 images</small>
                        <div id="imagePreview" class="mt-2"></div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Accommodation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5>Tips for Success</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <strong>Quality Photos:</strong>
                        Upload clear, well-lit photos of your accommodation
                    </li>
                    <li class="mb-2">
                        <strong>Detailed Description:</strong>
                        Include information about size, furnishings, and house rules
                    </li>
                    <li class="mb-2">
                        <strong>Fair Pricing:</strong>
                        Research similar properties in your area
                    </li>
                    <li class="mb-2">
                        <strong>Highlight Amenities:</strong>
                        Mention WiFi, parking, utilities, and other conveniences
                    </li>
                    <li class="mb-2">
                        <strong>Accurate Location:</strong>
                        Provide exact address and GPS coordinates
                    </li>
                </ul>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5>Activity</h5>
            </div>
            <div class="card-body">
                <?php
                $conn = getDBConnection();
                $conn->select_db('smartcampus');
                $count = countRows($conn, "SELECT COUNT(*) as count FROM accommodations WHERE landlord_id = ?", "i", $landlord_id);
                $conn->close();
                ?>
                <p class="mb-0"><strong>Total Listings:</strong> <?php echo $count; ?></p>
            </div>
        </div>
    </div>
</div>

<script>
// Image preview
document.getElementById('images').addEventListener('change', function(e) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    for (let file of e.target.files) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const img = document.createElement('img');
            img.src = event.target.result;
            img.style.maxWidth = '100px';
            img.style.marginRight = '10px';
            img.style.marginBottom = '10px';
            img.className = 'rounded border';
            preview.appendChild(img);
        }
        reader.readAsDataURL(file);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
