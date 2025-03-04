<?php
// src/Services/BalanceService.php

namespace App\Services;

use App\Database\Database;
use App\Config\Config;
use App\Helpers\Logger;

class BalanceService
{
    private Database $db;
    private Logger $logger;
    private Config $config;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('balance');
        $this->config = Config::getInstance();
    }

    public function getUserBalance(int $userId): float
    {
        $balance = $this->db->fetch(
            "SELECT amount FROM balances WHERE user_id = ?",
            [$userId]
        );

        return (float) ($balance['amount'] ?? 0);
    }

    public function addBalance(
        int $userId,
        float $amount,
        string $type,
        string $referenceType = null,
        int $referenceId = null,
        string $description = null
    ): bool {
        try {
            $this->db->beginTransaction();

            // Update user balance
            $this->db->update(
                "UPDATE balances SET amount = amount + ? WHERE user_id = ?",
                [$amount, $userId]
            );

            // Create transaction record
            $transactionId = $this->db->insert(
                "INSERT INTO transactions 
                (user_id, amount, type, reference_type, reference_id, description, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'completed')",
                [$userId, $amount, $type, $referenceType, $referenceId, $description]
            );

            $this->db->commit();

            // Log transaction
            $transactionLogger = new Logger('transactions');
            $transactionLogger->logTransaction(
                $userId,
                $type,
                $amount,
                $description ?? "Balance {$type} transaction",
                [
                    'transaction_id' => $transactionId,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId
                ]
            );

            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->logger->error("Error adding balance: " . $e->getMessage(), [
                'user_id' => $userId,
                'amount' => $amount,
                'type' => $type
            ]);
            return false;
        }
    }

    public function subtractBalance(
        int $userId,
        float $amount,
        string $type,
        string $referenceType = null,
        int $referenceId = null,
        string $description = null
    ): bool {
        try {
            $this->db->beginTransaction();

            // Check if user has enough balance
            $currentBalance = $this->getUserBalance($userId);

            if ($currentBalance < $amount) {
                $this->db->rollback();
                return false;
            }

            // Update user balance
            $this->db->update(
                "UPDATE balances SET amount = amount - ? WHERE user_id = ?",
                [$amount, $userId]
            );

            // Create transaction record
            $transactionId = $this->db->insert(
                "INSERT INTO transactions 
                (user_id, amount, type, reference_type, reference_id, description, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'completed')",
                [$userId, $amount, $type, $referenceType, $referenceId, $description]
            );

            $this->db->commit();

            // Log transaction
            $transactionLogger = new Logger('transactions');
            $transactionLogger->logTransaction(
                $userId,
                $type,
                $amount,
                $description ?? "Balance {$type} transaction",
                [
                    'transaction_id' => $transactionId,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId
                ]
            );

            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->logger->error("Error subtracting balance: " . $e->getMessage(), [
                'user_id' => $userId,
                'amount' => $amount,
                'type' => $type
            ]);
            return false;
        }
    }

    public function requestWithdrawal(int $userId, float $amount, string $paymentDetails): int
    {
        try {
            $this->db->beginTransaction();

            // Check minimum withdrawal amount
            $minWithdrawalAmount = (float) $this->config->get('MIN_WITHDRAWAL_AMOUNT', 300000);

            if ($amount < $minWithdrawalAmount) {
                throw new \Exception("Withdrawal amount must be at least {$minWithdrawalAmount}");
            }

            // Check if user has enough balance
            $currentBalance = $this->getUserBalance($userId);

            if ($currentBalance < $amount) {
                throw new \Exception("Insufficient balance");
            }

            // Create withdrawal request
            $withdrawalId = $this->db->insert(
                "INSERT INTO withdrawal_requests 
                (user_id, amount, payment_details, status) 
                VALUES (?, ?, ?, 'pending')",
                [$userId, $amount, $paymentDetails]
            );

            // Create a pending transaction
            $transactionId = $this->db->insert(
                "INSERT INTO transactions 
                (user_id, amount, type, reference_type, reference_id, description, status) 
                VALUES (?, ?, 'withdrawal', 'withdrawal', ?, 'Withdrawal request', 'pending')",
                [$userId, $amount, $withdrawalId]
            );

            // Update the withdrawal request with the transaction ID
            $this->db->update(
                "UPDATE withdrawal_requests SET transaction_id = ? WHERE id = ?",
                [$transactionId, $withdrawalId]
            );

            // Update user balance (lock the funds)
            $this->db->update(
                "UPDATE balances SET amount = amount - ? WHERE user_id = ?",
                [$amount, $userId]
            );

            $this->db->commit();

            $this->logger->info("Withdrawal requested", [
                'user_id' => $userId,
                'amount' => $amount,
                'withdrawal_id' => $withdrawalId
            ]);

            return $withdrawalId;
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->logger->error("Error requesting withdrawal: " . $e->getMessage(), [
                'user_id' => $userId,
                'amount' => $amount
            ]);
            throw $e;
        }
    }

    public function approveWithdrawal(int $withdrawalId, int $adminId): bool
    {
        try {
            $this->db->beginTransaction();

            // Get withdrawal request
            $withdrawal = $this->db->fetch(
                "SELECT * FROM withdrawal_requests WHERE id = ? AND status = 'pending'",
                [$withdrawalId]
            );

            if (!$withdrawal) {
                $this->db->rollback();
                return false;
            }

            // Update withdrawal status
            $this->db->update(
                "UPDATE withdrawal_requests SET 
                status = 'completed', 
                processed_by = ?, 
                processed_at = NOW() 
                WHERE id = ?",
                [$adminId, $withdrawalId]
            );

            // Update transaction status
            $this->db->update(
                "UPDATE transactions SET 
                status = 'completed', 
                processed_by = ?, 
                processed_at = NOW() 
                WHERE id = ?",
                [$adminId, $withdrawal['transaction_id']]
            );

            $this->db->commit();

            $this->logger->info("Withdrawal approved", [
                'withdrawal_id' => $withdrawalId,
                'user_id' => $withdrawal['user_id'],
                'admin_id' => $adminId,
                'amount' => $withdrawal['amount']
            ]);

            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->logger->error("Error approving withdrawal: " . $e->getMessage(), [
                'withdrawal_id' => $withdrawalId
            ]);
            return false;
        }
    }

    public function rejectWithdrawal(int $withdrawalId, int $adminId, string $reason): bool
    {
        try {
            $this->db->beginTransaction();

            // Get withdrawal request
            $withdrawal = $this->db->fetch(
                "SELECT * FROM withdrawal_requests WHERE id = ? AND status = 'pending'",
                [$withdrawalId]
            );

            if (!$withdrawal) {
                $this->db->rollback();
                return false;
            }

            // Update withdrawal status
            $this->db->update(
                "UPDATE withdrawal_requests SET 
                status = 'rejected', 
                rejection_reason = ?,
                processed_by = ?, 
                processed_at = NOW() 
                WHERE id = ?",
                [$reason, $adminId, $withdrawalId]
            );

            // Update transaction status
            $this->db->update(
                "UPDATE transactions SET 
                status = 'rejected', 
                processed_by = ?, 
                processed_at = NOW() 
                WHERE id = ?",
                [$adminId, $withdrawal['transaction_id']]
            );

            // Return funds to user's balance
            $this->db->update(
                "UPDATE balances SET amount = amount + ? WHERE user_id = ?",
                [$withdrawal['amount'], $withdrawal['user_id']]
            );

            $this->db->commit();

            $this->logger->info("Withdrawal rejected", [
                'withdrawal_id' => $withdrawalId,
                'user_id' => $withdrawal['user_id'],
                'admin_id' => $adminId,
                'amount' => $withdrawal['amount'],
                'reason' => $reason
            ]);

            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->logger->error("Error rejecting withdrawal: " . $e->getMessage(), [
                'withdrawal_id' => $withdrawalId
            ]);
            return false;
        }
    }

    public function getPendingWithdrawals(): array
    {
        return $this->db->fetchAll(
            "SELECT w.*, u.telegram_id, u.username, u.full_name 
            FROM withdrawal_requests w
            JOIN users u ON w.user_id = u.id
            WHERE w.status = 'pending'
            ORDER BY w.created_at ASC"
        );
    }

    public function getUserTransactionHistory(int $userId, ?int $limit = null): array
    {
        $sql = "SELECT t.*, 
                CASE 
                    WHEN t.reference_type = 'video' THEN (SELECT status FROM videos WHERE id = t.reference_id)
                    WHEN t.reference_type = 'withdrawal' THEN (SELECT status FROM withdrawal_requests WHERE id = t.reference_id)
                    ELSE NULL
                END as reference_status
                FROM transactions t
                WHERE t.user_id = ?
                ORDER BY t.created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT ?";
            return $this->db->fetchAll($sql, [$userId, $limit]);
        }

        return $this->db->fetchAll($sql, [$userId]);
    }

    public function getUserWithdrawalHistory(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM withdrawal_requests 
            WHERE user_id = ?
            ORDER BY created_at DESC",
            [$userId]
        );
    }
}