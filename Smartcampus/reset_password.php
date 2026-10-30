<?php
$pageTitle = "Set New Password - Smart Campus Connect";
include 'includes/config.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$tokenHash = $token ? hash('sha256', $token) : '';
$errors = [];
$success = '';
$validToken = false;

$conn = getDBConnection();
ensureAuthColumns($conn);

if ($tokenHash) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token_hash = ? AND reset_token_expires_at > NOW()");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $validToken = $result->num_rows === 1;
    $user = $validToken ? $result->fetch_assoc() : null;
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid form token. Please try again.";
    } elseif (!$validToken) {
        $errors[] = "This reset link is invalid or expired.";
    } elseif (!isStrongPassword($password)) {
        $errors[] = "Password must be at least 8 characters long and include a letter, a number, and a special character.";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("
            UPDATE users
            SET password = ?, failed_login_attempts = 0, locked_until = NULL,
                reset_token_hash = NULL, reset_token_expires_at = NULL
            WHERE id = ?
        ");
        $update->bind_param("si", $hashedPassword, $user['id']);
        $update->execute();
        $update->close();
        $success = "Password updated. You can now log in.";
        $validToken = false;
    }
}

$conn->close();
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4>Set New Password</h4>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                    <a href="login.php" class="btn btn-primary w-100">Go to Login</a>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($validToken): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="token" value="<?php echo e($token); ?>">
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">Use at least 8 characters with a letter, a number, and a special character.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Update Password</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-danger">This reset link is invalid or expired.</div>
                        <a href="forgot_password.php" class="btn btn-primary w-100">Request New Link</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
