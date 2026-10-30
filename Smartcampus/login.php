<?php
$pageTitle = "Login - Smart Campus Connect";
include 'includes/config.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid form token. Please try again.";
    } elseif (empty($email) || empty($password)) {
        $errors[] = "Please fill in all fields.";
    } else {
        $conn = getDBConnection();
        ensureAuthColumns($conn);

        $stmt = $conn->prepare("SELECT id, name, password, role, failed_login_attempts, locked_until FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $lockedUntil = !empty($user['locked_until']) ? strtotime($user['locked_until']) : 0;

            if ($lockedUntil > time()) {
                $errors[] = "Too many failed attempts. Try again after " . date('H:i', $lockedUntil) . ".";
            } elseif (password_verify($password, $user['password'])) {
                $reset_stmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
                $reset_stmt->bind_param("i", $user['id']);
                $reset_stmt->execute();
                $reset_stmt->close();

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];

                header($user['role'] === 'admin' ? "Location: admin.php" : "Location: dashboard.php");
                exit();
            } else {
                $attempts = ((int)$user['failed_login_attempts']) + 1;
                $locked_until = null;

                if ($attempts >= 5) {
                    $locked_until = date('Y-m-d H:i:s', time() + (15 * 60));
                    $attempts = 0;
                    $errors[] = "Too many failed attempts. Account locked for 15 minutes.";
                } else {
                    $remaining = 5 - $attempts;
                    $errors[] = "Invalid email or password. {$remaining} attempts remaining.";
                }

                $update_stmt = $conn->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
                $update_stmt->bind_param("isi", $attempts, $locked_until, $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
        } else {
            password_verify($password, password_hash('invalid-password-padding', PASSWORD_DEFAULT));
            $errors[] = "Invalid email or password.";
        }

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
                <h4>Login to Smart Campus Connect</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo e($email); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <p><a href="forgot_password.php">Forgot your password?</a></p>
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
