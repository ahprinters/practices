<?php

class LoanRepository {

    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Creates a new loan.
     *
     * @param int $userId
     * @param float $amount
     * @param float $interestRate
     * @param int $termMonths
     * @return int|false The ID of the inserted loan, or false on failure.
     */
    public function createLoan(int $userId, float $amount, float $interestRate, int $termMonths) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO loans (user_id, amount, interest_rate, term_months, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$userId, $amount, $interestRate, $termMonths]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating loan: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a loan by ID.
     *
     * @param int $loanId
     * @return array|false The loan data as an associative array, or false if not found.
     */
    public function getLoanById(int $loanId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM loans WHERE id = ?");
            $stmt->execute([$loanId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting loan by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all loans for a user.
     *
     * @param int $userId
     * @return array|false An array of loan data, or false if an error occurs.
     */
    public function getLoansByUserId(int $userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM loans WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting loans by user ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates a loan.
     *
     * @param int $loanId
     * @param float $amount
     * @param float $interestRate
     * @param int $termMonths
     * @return bool True on success, false on failure.
     */
    public function updateLoan(int $loanId, float $amount, float $interestRate, int $termMonths) {
        try {
            $stmt = $this->pdo->prepare("UPDATE loans SET amount = ?, interest_rate = ?, term_months = ?, updated_at = NOW() WHERE id = ?");
            return $stmt->execute([$amount, $interestRate, $termMonths, $loanId]);
        } catch (PDOException $e) {
            error_log("Error updating loan: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a loan by ID.
     *
     * @param int $loanId
     * @return bool True on success, false on failure.
     */
    public function deleteLoan(int $loanId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM loans WHERE id = ?");
            return $stmt->execute([$loanId]);
        } catch (PDOException $e) {
            error_log("Error deleting loan: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculates the monthly payment for a loan.
     *
     * @param float $amount
     * @param float $interestRate
     * @param int $termMonths
     * @return float The monthly payment amount.
     */
    public function calculateMonthlyPayment(float $amount, float $interestRate, int $termMonths) {
        $monthlyInterestRate = $interestRate / 12 / 100; //Convert annual rate to monthly rate
        if ($monthlyInterestRate == 0) {
            return $amount/$termMonths;
        }

        $monthlyPayment = ($amount * $monthlyInterestRate) / (1 - pow(1 + $monthlyInterestRate, -$termMonths));
        return round($monthlyPayment, 2);
    }
}

// Example usage (assuming you have a PDO connection):
/*
$dsn = 'mysql:host=localhost;dbname=your_database';
$username = 'your_username';
$password = 'your_password';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $loanRepository = new LoanRepository($pdo);

    // Example: Creating a loan
    $loanId = $loanRepository->createLoan(1, 10000, 5.0, 12);
    if ($loanId) {
        echo "Loan created with ID: " . $loanId . "\n";
    }

    //Example: Get Loan by ID
    $loan = $loanRepository->getLoanById($loanId);
    print_r($loan);

    //Example: Get Loans by User ID
    $loans = $loanRepository->getLoansByUserId(1);
    print_r($loans);

    //Example: update loan
    $updateResult = $loanRepository->updateLoan($loanId, 12000, 6.0, 24);
    echo "loan updated: ". ($updateResult ? "true" : "false")."\n";

    //Example: delete loan
    $deleteResult = $loanRepository->deleteLoan($loanId);
    echo "loan deleted: ". ($deleteResult ? "true" : "false")."\n";

    //Example: calculate monthly payment
    $monthlyPayment = $loanRepository->calculateMonthlyPayment(10000, 5.0, 12);
    echo "Monthly payment: " . $monthlyPayment . "\n";

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
*/
?>