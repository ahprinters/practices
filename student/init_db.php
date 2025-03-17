<?php
// Database initialization script
require_once 'config/database.php';

use Student\Config\Database;

try {
    // Connect to MySQL without specifying a database
    $pdo = new PDO(
        "mysql:host=localhost;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS student_management");
    
    echo "Database created or already exists.\n";
    
    // Now connect to the database and create the table
    $db = Database::getConnection();
    $db->exec("CREATE TABLE IF NOT EXISTS students (
        id INT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        class VARCHAR(50) NOT NULL
    )");
    
    echo "Students table created or already exists.\n";
    echo "Database initialization completed successfully.\n";
    
} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage());
}
?>