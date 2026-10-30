<!DOCTYPE html>
<html lang="en" data-bs-theme="dark" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Smart Campus Connect'; ?></title>
    <?php
    $googleMapsKey = defined('GOOGLE_MAPS_API_KEY') ? trim(GOOGLE_MAPS_API_KEY) : '';
    $hasGoogleMapsKey = $googleMapsKey !== '' && stripos($googleMapsKey, 'YOUR_') === false;
    ?>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (!empty($includeLeaflet)): ?>
        <!-- Leaflet map CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <?php endif; ?>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <?php if ($hasGoogleMapsKey): ?>
        <!-- Google Maps API -->
        <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode($googleMapsKey); ?>&libraries=places"></script>
    <?php endif; ?>
    <?php if (!empty($includeLeaflet)): ?>
        <!-- Leaflet map JS -->
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php endif; ?>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="app-shell">
    <nav class="navbar navbar-expand-lg navbar-dark app-navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-university"></i>
                <span>Smart Campus Connect</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="accommodation.php">Accommodation</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bus.php">Bus Booking</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="exam.php">Exam Locator</a>
                    </li>
                </ul>
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item me-2">
                        <a class="nav-link position-relative" href="notifications.php" title="Notifications">
                            <i class="fas fa-bell"></i>
                            <span id="notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none; font-size:.65rem; min-width: 18px; height: 18px; line-height: 18px;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <button class="theme-toggle" type="button" id="themeToggle" aria-label="Switch theme">
                            <i class="fas fa-moon" id="themeIcon"></i>
                        </button>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                                <?php if ($_SESSION['user_role'] == 'student'): ?>
                                    <li><a class="dropdown-item" href="my_bookings.php">My Bookings</a></li>
                                    <li><a class="dropdown-item" href="chat.php">Chat with Landlord</a></li>
                                <?php elseif ($_SESSION['user_role'] == 'landlord'): ?>
                                    <li><a class="dropdown-item" href="manage_accommodations.php">My Listings</a></li>
                                    <li><a class="dropdown-item" href="chat.php">Chat with Students</a></li>
                                <?php elseif ($_SESSION['user_role'] == 'admin'): ?>
                                    <li><a class="dropdown-item" href="manage_exams.php">Manage Exams</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container app-main mt-4">
<script>
window.currentUserId = <?php echo isLoggedIn() ? (int)$_SESSION['user_id'] : 0; ?>;
window.currentUserRole = <?php echo json_encode(isLoggedIn() ? $_SESSION['user_role'] : 'guest'); ?>;

(function() {
    const storedTheme = localStorage.getItem('scc-theme');
    const preferredTheme = storedTheme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', preferredTheme);
    document.documentElement.setAttribute('data-bs-theme', preferredTheme);
})();

window.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    const applyTheme = function(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('scc-theme', theme);
        if (icon) {
            icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
        }
    };
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    applyTheme(currentTheme);
    if (toggle) {
        toggle.addEventListener('click', function() {
            const nextTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme);
        });
    }
});
</script>
