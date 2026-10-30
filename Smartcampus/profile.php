<?php
$pageTitle = "Profile - Smart Campus Connect";
include 'includes/config.php';
redirectIfNotLoggedIn();

$user = getCurrentUser();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_submit'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please try again.';
    } elseif ($name === '') {
        $errors[] = 'Name is required.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $phone, $user['id']);
        $stmt->execute();
        $stmt->close();

        if ($newPassword !== '') {
            if (!isStrongPassword($newPassword)) {
                $errors[] = 'New password must be at least 8 characters and include a letter, a number, and a special character.';
            } elseif (!password_verify($currentPassword, $user['password'])) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hash, $user['id']);
                $stmt->execute();
                $stmt->close();
            }
        }

        if (empty($errors)) {
            $_SESSION['user_name'] = $name;
            $success = 'Profile updated.';
            $user = getCurrentUser();
        }
        $conn->close();
    }
}

$conn = getDBConnection();
ensureLandlordVerificationColumns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'landlord') {
    if (isset($_FILES['national_id']) && $_FILES['national_id']['error'] !== UPLOAD_ERR_NO_FILE) {
        $validation = validateUploadedFile($_FILES['national_id']);
        if (!$validation['ok']) {
            $errors[] = $validation['error'];
        } else {
            $stored = storeUploadedFile($_FILES['national_id'], 'uploads/landlords/', 'nid');
            if ($stored['ok']) {
                $stmt = $conn->prepare("UPDATE landlords SET national_id_path = ?, verification_status = 'pending', submitted_at = NOW() WHERE user_id = ?");
                $stmt->bind_param("si", $stored['path'], $user['id']);
                $stmt->execute();
                $stmt->close();
                $success = 'National ID submitted successfully.';
            } else {
                $errors[] = $stored['error'];
            }
        }
    }

    if (isset($_FILES['proof_of_residence']) && $_FILES['proof_of_residence']['error'] !== UPLOAD_ERR_NO_FILE) {
        $validation = validateUploadedFile($_FILES['proof_of_residence']);
        if (!$validation['ok']) {
            $errors[] = $validation['error'];
        } else {
            $stored = storeUploadedFile($_FILES['proof_of_residence'], 'uploads/landlords/', 'por');
            if ($stored['ok']) {
                $stmt = $conn->prepare("UPDATE landlords SET proof_of_residence_path = ?, verification_status = 'pending', submitted_at = NOW() WHERE user_id = ?");
                $stmt->bind_param("si", $stored['path'], $user['id']);
                $stmt->execute();
                $stmt->close();
                $success = 'Proof of residence submitted successfully.';
            } else {
                $errors[] = $stored['error'];
            }
        }
    }
}

if ($user['role'] === 'landlord') {
    $landlord_stmt = $conn->prepare("SELECT id, verification_status, verification_notes, national_id_path, proof_of_residence_path FROM landlords WHERE user_id = ?");
    $landlord_stmt->bind_param("i", $user['id']);
    $landlord_stmt->execute();
    $landlord_profile = $landlord_stmt->get_result()->fetch_assoc();
    $landlord_stmt->close();
}

$conn->close();

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Profile</h4>
            </div>
            <div class="card-body">
                <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="profile_submit" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo e($user['name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" value="<?php echo e($user['email']); ?>" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($user['phone'] ?? ''); ?>">
                    </div>
                    <hr>
                    <h5>Change Password</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <div class="form-text">Use 8+ characters with a letter, a number, and a special character.</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </form>

                <?php if ($user['role'] === 'landlord'): ?>
                <hr>
                <h5>Landlord Verification</h5>
                <div class="alert alert-info">
                    Upload your national ID and proof of residence so your listings can be verified before they go live.
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="national_id" class="form-label">National ID</label>
                            <input type="file" class="form-control" id="national_id" name="national_id" accept="image/*,.pdf">
                            <?php if (!empty($landlord_profile['national_id_path'])): ?><div class="small mt-2"><a href="<?php echo e($landlord_profile['national_id_path']); ?>" target="_blank">View uploaded ID</a></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="proof_of_residence" class="form-label">Proof of Residence</label>
                            <input type="file" class="form-control" id="proof_of_residence" name="proof_of_residence" accept="image/*,.pdf">
                            <?php if (!empty($landlord_profile['proof_of_residence_path'])): ?><div class="small mt-2"><a href="<?php echo e($landlord_profile['proof_of_residence_path']); ?>" target="_blank">View proof of residence</a></div><?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Upload Verification Documents</button>
                </form>
                <div class="mt-3">
                    <strong>Status:</strong> <?php echo e(ucfirst($landlord_profile['verification_status'] ?? 'pending')); ?>
                    <?php if (!empty($landlord_profile['verification_notes'])): ?><div class="small text-muted mt-1"><?php echo e($landlord_profile['verification_notes']); ?></div><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
