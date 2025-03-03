<?php
// src/Models/Balance.php

namespace App\Models;

use App\Database\Database;

class Balance
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    public function getByUserId(int $userId): ?array
    {
        $sql = "SELECT * FROM balances WHERE user_id = ?";
        return $this->db->fetch($sql, [$userId]);
    }
    
    public function create(int $userId, float $initialAmount = 0): int
    {
        $sql = "INSERT INTO balances (user_id, amount) VALUES (?, ?)";
        return $this->db->insert($sql, [$userId, $initialAmount]);
    }
    
    public function updateBalance(int $userId, float $amount): bool
    {
        // First check if balance exists
        $balance = $this->getByUserId($userId);
        
        if ($balance) {
            $sql = "UPDATE balances SET amount = ? WHERE user_id = ?";
            return $this->db->update($sql, [$amount, $userId]) !== false;
        } else {
            // Create balance if it doesn't exist
            $this->create($userId, $amount);
            return true;
        }
    }
    
    public function addToBalance(int $userId, float $amount): bool
    {
        $balance = $this->getByUserId($userId);
        
        if ($balance) {
            $newAmount = $balance['amount'] + $amount;
            return $this->updateBalance($userId, $newAmount);
        } else {
            // Create balance if it doesn't exist
            $this->create($userId, $amount);
            return true;
        }
    }
    
    public function subtractFromBalance(int $userId, float $amount): bool
    {
        $balance = $this->getByUserId($userId);
        
        if ($balance && $balance['amount'] >= $amount) {
            $newAmount = $balance['amount'] - $amount;
            return $this->updateBalance($userId, $newAmount);
        }
        
        return false;
    }
    
    public function hasEnoughBalance(int $userId, float $amount): bool
    {
        $balance = $this->getByUserId($userId);
        return $balance && $balance['amount'] >= $amount;
    }
    
    public function getTotalPaidAmount(): float
    {
        $sql = "SELECT SUM(amount) as total FROM transactions 
                WHERE type = 'withdrawal' AND status = 'completed'";
        
        $result = $this->db->fetch($sql);
        return $result ? floatval($result['total']) : 0;
    }
}
