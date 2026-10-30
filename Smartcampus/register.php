<?php
$pageTitle = "Register - Smart Campus Connect";
include 'includes/config.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$name = $email = $role = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid form token. Please try again.";
    } elseif (empty($name) || empty($email) || empty($password) || empty($role)) {
        $errors[] = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } elseif (!isStrongPassword($password)) {
        $errors[] = "Password must be at least 8 characters long and include a letter, a number, and a special character.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    } elseif (!in_array($role, ['student', 'landlord', 'bus_company'])) {
        $errors[] = "Invalid role selected.";
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errors[] = "Email address already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;

                // Create role-specific table entry
                switch ($role) {
                    case 'student':
                        $stmt = $conn->prepare("INSERT INTO students (user_id) VALUES (?)");
                        break;
                    case 'landlord':
                        $stmt = $conn->prepare("INSERT INTO landlords (user_id) VALUES (?)");
                        break;
                    case 'bus_company':
                        $stmt = $conn->prepare("INSERT INTO bus_companies (user_id) VALUES (?)");
                        break;
                }
                $stmt->bind_param("i", $user_id);
                $stmt->execute();

                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = $role;

                header("Location: dashboard.php");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
        $conn->close();
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Register for Smart Campus Connect</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">Use at least 8 characters with a letter, a number, and a special character.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">I am a:</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select your role</option>
                            <option value="student" <?php echo ($role == 'student') ? 'selected' : ''; ?>>Student</option>
                            <option value="landlord" <?php echo ($role == 'landlord') ? 'selected' : ''; ?>>Landlord</option>
                            <option value="bus_company" <?php echo ($role == 'bus_company') ? 'selected' : ''; ?>>Bus Company</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
