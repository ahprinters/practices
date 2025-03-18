<?php
namespace Loan\Service;

use Loan\Model\Loan;
use Loan\Repository\LoanRepository;

class LoanService {
    private $loanRepository;
    
    public function __construct(LoanRepository $loanRepository) {
        $this->loanRepository = $loanRepository;
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
        return $this->loanRepository->createLoan($userId, $amount, $interestRate, $termMonths);
    }
    
    /**
     * Get a loan by ID
     * 
     * @param int $loanId
     * @return Loan|null
     */
    public function getLoanById(int $loanId) {
        $loanData = $this->loanRepository->getLoanById($loanId);
        
        if (!$loanData) {
            return null;
        }
        
        return Loan::fromArray($loanData);
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
        return $this->loanRepository->updateLoan($loanId, $amount, $interestRate, $termMonths);
    }
    
    /**
     * Delete a loan
     * 
     * @param int $loanId
     * @return bool
     */
    public function deleteLoan(int $loanId) {
        return $this->loanRepository->deleteLoan($loanId);
    }
    
    /**
     * Get all loans with pagination
     * 
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getAllLoans(int $page = 1, int $perPage = 10) {
        $result = $this->loanRepository->getAllLoans($page, $perPage);
        
        // Convert loan data to Loan objects
        $loans = [];
        foreach ($result['loans'] as $loanData) {
            $loans[] = Loan::fromArray($loanData);
        }
        
        return [
            'loans' => $loans,
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'totalPages' => $result['totalPages']
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
        $result = $this->loanRepository->searchLoans($criteria, $page, $perPage);
        
        // Convert loan data to Loan objects
        $loans = [];
        foreach ($result['loans'] as $loanData) {
            $loans[] = Loan::fromArray($loanData);
        }
        
        return [
            'loans' => $loans,
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'totalPages' => $result['totalPages']
        ];
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
     * Calculate total repayment amount
     * 
     * @param float $amount
     * @param float $interestRate
     * @param int $termMonths
     * @return float
     */
    public function calculateTotalRepayment(float $amount, float $interestRate, int $termMonths) {
        $monthlyPayment = $this->calculateMonthlyPayment($amount, $interestRate, $termMonths);
        $totalRepayment = $monthlyPayment * $termMonths;
        
        return round($totalRepayment, 2);
    }
    
    /**
     * Calculate total interest paid
     * 
     * @param float $amount
     * @param float $interestRate
     * @param int $termMonths
     * @return float
     */
    public function calculateTotalInterest(float $amount, float $interestRate, int $termMonths) {
        $totalRepayment = $this->calculateTotalRepayment($amount, $interestRate, $termMonths);
        $totalInterest = $totalRepayment - $amount;
        
        return round($totalInterest, 2);
    }
    
    /**
     * Generate amortization schedule
     * 
     * @param float $amount
     * @param float $interestRate
     * @param int $termMonths
     * @param string $startDate
     * @return array
     */
    public function generateAmortizationSchedule(float $amount, float $interestRate, int $termMonths, string $startDate = null) {
        $monthlyPayment = $this->calculateMonthlyPayment($amount, $interestRate, $termMonths);
        $balance = $amount;
        $monthlyInterestRate = $interestRate / 12 / 100;
        
        if ($startDate === null) {
            $startDate = date('Y-m-d');
        }
        
        $startDateObj = new \DateTime($startDate);
        $schedule = [];
        
        for ($month = 1; $month <= $termMonths; $month++) {
            $interest = $balance * $monthlyInterestRate;
            $principal = $monthlyPayment - $interest;
            
            // Adjust for final payment rounding
            if ($month == $termMonths) {
                $principal = $balance;
                $monthlyPayment = $principal + $interest;
            }
            
            $balance -= $principal;
            
            // Ensure balance doesn't go below zero due to rounding
            if ($balance < 0) {
                $balance = 0;
            }
            
            $paymentDate = clone $startDateObj;
            $paymentDate->modify("+$month months");
            
            $schedule[] = [
                'payment_number' => $month,
                'payment_date' => $paymentDate->format('Y-m-d'),
                'payment_amount' => round($monthlyPayment, 2),
                'principal' => round($principal, 2),
                'interest' => round($interest, 2),
                'balance' => round($balance, 2)
            ];
        }
        
        return $schedule;
    }
    
    /**
     * Get loans by user ID
     * 
     * @param int $userId
     * @return array
     */
    public function getLoansByUserId(int $userId) {
        $loansData = $this->loanRepository->getLoansByUserId($userId);
        
        $loans = [];
        foreach ($loansData as $loanData) {
            $loans[] = Loan::fromArray($loanData);
        }
        
        return $loans;
    }
}