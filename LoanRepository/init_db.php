<?php
// Database initialization script
require_once 'Config/Database.php';

use Loan\Config\Database;

try {
    // Connect to MySQL without specifying a database
    $pdo = new PDO(
        "mysql:host=localhost;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS loan_management");
    
    echo "Database created or already exists.\n";
    
    // Now connect to the database and create the tables
    $db = Database::getConnection();
    
    // Create loans table
    $db->exec("CREATE TABLE IF NOT EXISTS loans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        interest_rate DECIMAL(5, 2) NOT NULL,
        term_months INT NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        INDEX (user_id)
    )");
    
    // Create users table (for reference)
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )");
    
    // Create payments table (for tracking loan payments)
    $db->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        payment_date DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
        INDEX (loan_id)
    )");
    
    echo "Tables created successfully.\n";
    
    // Add some sample data if needed
    $checkStmt = $db->query("SELECT COUNT(*) FROM users");
    $userCount = $checkStmt->fetchColumn();
    
    if ($userCount == 0) {
        // Add sample users
        $db->exec("INSERT INTO users (name, email, created_at, updated_at) VALUES 
            ('John Doe', 'john@example.com', NOW(), NOW()),
            ('Jane Smith', 'jane@example.com', NOW(), NOW()),
            ('Bob Johnson', 'bob@example.com', NOW(), NOW())
        ");
        
        echo "Sample users added.\n";
        
        // Add sample loans
        $db->exec("INSERT INTO loans (user_id, amount, interest_rate, term_months, created_at, updated_at) VALUES 
            (1, 10000.00, 5.0, 12, NOW(), NOW()),
            (1, 5000.00, 4.5, 6, NOW(), NOW()),
            (2, 15000.00, 6.0, 24, NOW(), NOW()),
            (3, 7500.00, 5.5, 18, NOW(), NOW())
        ");
        
        echo "Sample loans added.\n";
    }
    
    echo "Database initialization completed successfully.\n";
    
} catch (PDOException $e) {
    echo "Database initialization failed: " . $e->getMessage() . "\n";
    exit(1);
}