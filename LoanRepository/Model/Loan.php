<?php
namespace Loan\Model;

class Loan {
    private $id;
    private $userId;
    private $amount;
    private $interestRate;
    private $termMonths;
    private $createdAt;
    private $updatedAt;
    
    /**
     * Create a Loan object from an array of data
     * 
     * @param array $data
     * @return Loan
     */
    public static function fromArray(array $data): Loan {
        $loan = new self();
        $loan->id = $data['id'] ?? null;
        $loan->userId = $data['user_id'] ?? null;
        $loan->amount = $data['amount'] ?? null;
        $loan->interestRate = $data['interest_rate'] ?? null;
        $loan->termMonths = $data['term_months'] ?? null;
        $loan->createdAt = $data['created_at'] ?? null;
        $loan->updatedAt = $data['updated_at'] ?? null;
        
        return $loan;
    }
    
    /**
     * Convert the Loan object to an array
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'amount' => $this->amount,
            'interest_rate' => $this->interestRate,
            'term_months' => $this->termMonths,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
    
    // Getters
    public function getId() {
        return $this->id;
    }
    
    public function getUserId() {
        return $this->userId;
    }
    
    public function getAmount() {
        return $this->amount;
    }
    
    public function getInterestRate() {
        return $this->interestRate;
    }
    
    public function getTermMonths() {
        return $this->termMonths;
    }
    
    public function getCreatedAt() {
        return $this->createdAt;
    }
    
    public function getUpdatedAt() {
        return $this->updatedAt;
    }
    
    // Setters
    public function setId($id) {
        $this->id = $id;
        return $this;
    }
    
    public function setUserId($userId) {
        $this->userId = $userId;
        return $this;
    }
    
    public function setAmount($amount) {
        $this->amount = $amount;
        return $this;
    }
    
    public function setInterestRate($interestRate) {
        $this->interestRate = $interestRate;
        return $this;
    }
    
    public function setTermMonths($termMonths) {
        $this->termMonths = $termMonths;
        return $this;
    }
    
    public function setCreatedAt($createdAt) {
        $this->createdAt = $createdAt;
        return $this;
    }
    
    public function setUpdatedAt($updatedAt) {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}