<?php
// Load environment variables if .env file exists
$envFile = __DIR__ . '/.env';
$env = file_exists($envFile) ? parse_ini_file($envFile) : [];

// Database Configuration
$db_host = $env['DB_HOST'] ?? 'sql100.infinityfree.com';
$db_user = $env['DB_USER'] ?? 'if0_41675899';
$db_pass = $env['DB_PASS'] ?? 'Arya7575123';
$db_name = $env['DB_NAME'] ?? 'if0_41675899_paisapilot';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");
?>
