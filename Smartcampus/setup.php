<?php
// Database setup script
$servername = "localhost";
$username = "root";
$password = "";
$port = 3307;

// Create connection without database
$conn = new mysqli($servername, $username, $password, null, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS smartcampus";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db("smartcampus");

// Read and execute SQL file
$sql = file_get_contents('db_schema.sql');

// Remove the CREATE DATABASE and USE statements
$sql = preg_replace('/CREATE DATABASE.*;/i', '', $sql);
$sql = preg_replace('/USE.*;/i', '', $sql);

// Split into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        if ($conn->query($statement) === TRUE) {
            echo "Executed: " . substr($statement, 0, 50) . "...<br>";
        } else {
            echo "Error executing statement: " . $conn->error . "<br>";
            echo "Statement: " . $statement . "<br><br>";
        }
    }
}

$conn->close();
echo "Database setup completed!";
?>