<?php
$host = 'localhost';     // or your database host
$username = 'root';
$password = '';
$database = 'Bentabeauty';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Connection failed: ' . $conn->connect_error
    ]));
}

// Set the charset to utf8mb4
$conn->set_charset("utf8mb4");
?> 