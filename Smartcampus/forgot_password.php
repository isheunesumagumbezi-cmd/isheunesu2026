<?php
$pageTitle = "Forgot Password - Smart Campus Connect";
include 'includes/config.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$email = '';
$message = '';
$resetLink = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid form token. Please try again.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    } else {
        $conn = getDBConnection();
        ensureAuthColumns($conn);

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $update = $conn->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?");
            $update->bind_param("ssi", $tokenHash, $expires, $user['id']);
            $update->execute();
            $update->close();

            $resetLink = "reset_password.php?token=" . urlencode($token);
        }

        $message = "If that email exists, a password reset link has been created.";
        $stmt->close();
        $conn->close();
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4>Reset Password</h4>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo e($message); ?></div>
                <?php endif; ?>
                <?php if ($resetLink): ?>
                    <div class="alert alert-info">
                        Local reset link: <a href="<?php echo e($resetLink); ?>"><?php echo e($resetLink); ?></a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo e($email); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Create Reset Link</button>
                </form>
                <div class="text-center mt-3">
                    <a href="login.php">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
