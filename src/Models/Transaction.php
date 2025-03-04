<?php
// src/Models/Transaction.php

namespace App\Models;

use App\Database\Database;

class Transaction
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // Create a new transaction record.
    public function createTransaction(array $data): int
    {
        $sql = "INSERT INTO transactions (user_id, amount, type, reference_type, reference_id, description, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        return $this->db->insert($sql, [
            $data['user_id'],
            $data['amount'],
            $data['type'],
            $data['reference_type'] ?? null,
            $data['reference_id'] ?? null,
            $data['description'] ?? null,
            $data['status'] ?? 'pending'
        ]);
    }

    // Update the status (and other processing details) of a transaction.
    public function updateTransactionStatus(int $transactionId, string $status, int $processedBy): bool
    {
        $sql = "UPDATE transactions SET status = ?, processed_by = ?, processed_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$status, $processedBy, $transactionId]) !== false;
    }

    // Retrieve a transaction record by its ID.
    public function getTransactionById(int $transactionId): ?array
    {
        $sql = "SELECT * FROM transactions WHERE id = ?";
        return $this->db->fetch($sql, [$transactionId]);
    }

    // Retrieve a list of transactions for a specific user.
    public function getUserTransactions(int $userId, ?int $limit = null): array
    {
        $sql = "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC";
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            return $this->db->fetchAll($sql, [$userId, $limit]);
        }
        return $this->db->fetchAll($sql, [$userId]);
    }
}