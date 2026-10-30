<?php
// Sample data insertion script
include 'includes/config.php';

$conn = getDBConnection();

// Sample users
$users = [
    ['Admin User', 'admin@cut.ac.zw', password_hash('admin123', PASSWORD_DEFAULT), 'admin'],
    ['John Student', 'john@student.cut.ac.zw', password_hash('student123', PASSWORD_DEFAULT), 'student'],
    ['Jane Landlord', 'jane@landlord.com', password_hash('landlord123', PASSWORD_DEFAULT), 'landlord'],
    ['City Bus Co', 'info@citybus.com', password_hash('bus123', PASSWORD_DEFAULT), 'bus_company']
];

foreach ($users as $user) {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $user[0], $user[1], $user[2], $user[3]);
    $stmt->execute();
    $user_id = $conn->insert_id;

    // Create role-specific entries
    switch ($user[3]) {
        case 'student':
            $stmt = $conn->prepare("INSERT INTO students (user_id, student_id, course, year) VALUES (?, 'STU001', 'Computer Science', 3)");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            break;
        case 'landlord':
            $stmt = $conn->prepare("INSERT INTO landlords (user_id, company_name) VALUES (?, 'Jane Properties')");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            break;
        case 'bus_company':
            $stmt = $conn->prepare("INSERT INTO bus_companies (user_id, company_name) VALUES (?, 'City Bus Company')");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            break;
    }
}

// Sample accommodations
$landlord_id = $conn->query("SELECT id FROM landlords LIMIT 1")->fetch_assoc()['id'];
$stmt = $conn->prepare("INSERT INTO accommodations (landlord_id, title, description, price, location, latitude, longitude) VALUES (?, 'Modern Student Apartment', 'Spacious 2-bedroom apartment with modern amenities', 300.00, 'Chinhoyi Central', -17.3650, 30.2000)");
$stmt->bind_param("i", $landlord_id);
$stmt->execute();

// Sample buses
$bus_company_id = $conn->query("SELECT id FROM bus_companies LIMIT 1")->fetch_assoc()['id'];
$stmt = $conn->prepare("INSERT INTO buses (bus_company_id, route, departure_time, arrival_time, price, date) VALUES (?, 'Chinhoyi to Harare', '08:00:00', '12:00:00', 15.00, CURDATE() + INTERVAL 7 DAY)");
$stmt->bind_param("i", $bus_company_id);
$stmt->execute();

// Sample exams
$stmt = $conn->prepare("INSERT INTO exams (course, venue, latitude, longitude, date, time) VALUES ('Computer Science', 'Lecture Theatre A', -17.3650, 30.2000, CURDATE() + INTERVAL 14 DAY, '09:00:00')");
$stmt->execute();

$conn->close();
echo "Sample data inserted successfully!";
?>
