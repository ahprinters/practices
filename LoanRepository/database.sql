-- Create database
CREATE DATABASE IF NOT EXISTS loan_management;
USE loan_management;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

-- Create loans table
CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    interest_rate DECIMAL(5, 2) NOT NULL,
    term_months INT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);

-- Insert sample users
INSERT INTO users (name, email, phone, address, created_at, updated_at) VALUES
('John Doe', 'john@example.com', '555-123-4567', '123 Main St, Anytown, USA', NOW(), NOW()),
('Jane Smith', 'jane@example.com', '555-987-6543', '456 Oak Ave, Somewhere, USA', NOW(), NOW()),
('Michael Johnson', 'michael@example.com', '555-555-5555', '789 Pine Rd, Nowhere, USA', NOW(), NOW()),
('Sarah Williams', 'sarah@example.com', '555-222-3333', '321 Elm Blvd, Anywhere, USA', NOW(), NOW());

-- Insert sample loans
INSERT INTO loans (user_id, amount, interest_rate, term_months, created_at, updated_at) VALUES
(1, 10000.00, 5.25, 24, NOW(), NOW()),
(2, 5000.00, 4.75, 12, NOW(), NOW()),
(3, 15000.00, 6.50, 36, NOW(), NOW()),
(1, 2500.00, 3.25, 6, NOW(), NOW());

-- Insert sample payments
INSERT INTO payments (loan_id, amount, payment_date, created_at) VALUES
(1, 438.71, DATE_SUB(NOW(), INTERVAL 2 MONTH), NOW()),
(1, 438.71, DATE_SUB(NOW(), INTERVAL 1 MONTH), NOW()),
(2, 427.83, DATE_SUB(NOW(), INTERVAL 2 MONTH), NOW()),
(2, 427.83, DATE_SUB(NOW(), INTERVAL 1 MONTH), NOW()),
(3, 458.77, DATE_SUB(NOW(), INTERVAL 2 MONTH), NOW()),
(4, 421.02, DATE_SUB(NOW(), INTERVAL 1 MONTH), NOW());