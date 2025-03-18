<?php
namespace Loan\Repository;

use PDO;

class LoanRepository {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Create a new loan
     * 
     * @param int $userId
     * @param float $amount
     * @param float $interestRate
     * @param int $termMonths
     * @return int|false The ID of the new loan or false on failure
     */
    public function createLoan(int $userId, float $amount, float $interestRate, int $termMonths) {
        $stmt = $this->db->prepare("
            INSERT INTO loans (user_id, amount, interest_rate, term_months, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        
        $success = $stmt->execute([$userId, $amount, $interestRate, $termMonths]);
        
        if ($success) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Get a loan by ID
     * 
     * @param int $loanId
     * @return array|null
     */
    public function getLoanById(int $loanId) {
        $stmt = $this->db->prepare("SELECT * FROM loans WHERE id = ?");
        $stmt->execute([$loanId]);
        
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $loan ?: null;
    }
    
    /**
     * Get all loans for a user
     * 
     * @param int $userId
     * @return array
     */
    public function getLoansByUserId(int $userId) {
        $stmt = $this->db->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update a loan
     * 
     * @param int $loanId
     * @param float $amount
     * @param float $interestRate
     * @param int $termMonths
     * @return bool
     */
    public function updateLoan(int $loanId, float $amount, float $interestRate, int $termMonths) {
        $stmt = $this->db->prepare("
            UPDATE loans 
            SET amount = ?, interest_rate = ?, term_months = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$amount, $interestRate, $termMonths, $loanId]);
    }
    
    /**
     * Delete a loan
     * 
     * @param int $loanId
     * @return bool
     */
    public function deleteLoan(int $loanId) {
        $stmt = $this->db->prepare("DELETE FROM loans WHERE id = ?");
        
        return $stmt->execute([$loanId]);
    }
    
    /**
     * Calculate monthly payment for a loan
     * 
     * @param float $amount
     * @param float $interestRate
     * @param int $termMonths
     * @return float
     */
    public function calculateMonthlyPayment(float $amount, float $interestRate, int $termMonths) {
        // Convert annual interest rate to monthly rate
        $monthlyInterestRate = $interestRate / 12 / 100;
        
        // If interest rate is 0, simple division
        if ($monthlyInterestRate == 0) {
            return $amount / $termMonths;
        }
        
        // Calculate monthly payment using the formula:
        // P = (r * PV) / (1 - (1 + r)^-n)
        // Where:
        // P = Monthly Payment
        // r = Monthly Interest Rate (in decimal)
        // PV = Loan Amount
        // n = Number of Payments (term in months)
        
        $monthlyPayment = ($monthlyInterestRate * $amount) / 
                          (1 - pow(1 + $monthlyInterestRate, -$termMonths));
        
        return round($monthlyPayment, 2);
    }
    
    /**
     * Get all loans with pagination
     * 
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getAllLoans(int $page = 1, int $perPage = 10) {
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countStmt = $this->db->query("SELECT COUNT(*) FROM loans");
        $total = $countStmt->fetchColumn();
        
        // Calculate total pages
        $totalPages = ceil($total / $perPage);
        
        // Get loans for current page
        $stmt = $this->db->prepare("
            SELECT * FROM loans 
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'loans' => $loans,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages
        ];
    }
    
    /**
     * Search loans by criteria
     * 
     * @param array $criteria
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function searchLoans(array $criteria, int $page = 1, int $perPage = 10) {
        // Build WHERE clause based on criteria
        $whereClause = [];
        $params = [];
        
        if (isset($criteria['user_id']) && !empty($criteria['user_id'])) {
            $whereClause[] = "user_id = ?";
            $params[] = $criteria['user_id'];
        }
        
        if (isset($criteria['min_amount']) && !empty($criteria['min_amount'])) {
            $whereClause[] = "amount >= ?";
            $params[] = $criteria['min_amount'];
        }
        
        if (isset($criteria['max_amount']) && !empty($criteria['max_amount'])) {
            $whereClause[] = "amount <= ?";
            $params[] = $criteria['max_amount'];
        }
        
        $whereStr = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";
        
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM loans $whereStr";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        // Calculate total pages
        $totalPages = ceil($total / $perPage);
        
        // Get loans for current page
        $sql = "
            SELECT * FROM loans 
            $whereStr
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->db->prepare($sql);
        
        // Bind search parameters
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param);
        }
        
        // Bind pagination parameters
        $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'loans' => $loans,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages
        ];
    }
}