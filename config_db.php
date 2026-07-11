<?php
// Database configuration
define('DB_HOST', 'localhost');

// Database credentials
define('DB_USER', 'root'); // ito yung username ng database
define('DB_PASS', ''); // ito yung password ng database
define('DB_NAME', 'softvote_db'); // ito yung name ng database

// Create connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Session start
?>