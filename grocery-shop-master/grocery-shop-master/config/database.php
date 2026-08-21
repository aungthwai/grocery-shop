<?php

// Database server
$host = "127.0.0.1";

// MySQL username
$username = "root";

// MySQL password
$password = "";

// Database name
$database = "grocery_shop";

// Create database connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set character encoding
$conn->set_charset("utf8mb4");

?>