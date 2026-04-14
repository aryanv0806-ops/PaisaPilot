<?php
// Database Configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'Aryan@0446';
$db_name = 'paisa_pilot';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");
?>
