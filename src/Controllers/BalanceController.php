<?php
// src/Controllers/BalanceController.php

namespace App\Controllers;

use App\Services\BalanceService;
use App\Bot\TelegramBot;

class BalanceController
{
    private BalanceService $balanceService;
    private TelegramBot $telegramBot;

    public function __construct(TelegramBot $telegramBot)
    {
        $this->balanceService = new BalanceService();
        $this->telegramBot = $telegramBot;
    }

    // Return a formatted balance message.
    public function getBalanceInfo(int $userId): string
    {
        $balance = $this->balanceService->getUserBalance($userId);
        return "Your current balance is: " . number_format($balance) . " UZS.";
    }

    // Process a withdrawal request and notify the user.
    public function handleWithdrawalRequest(array $user, float $amount): void
    {
        $paymentDetails = "Withdrawal request from user " . $user['id'];
        try {
            $withdrawalId = $this->balanceService->requestWithdrawal($user['id'], $amount, $paymentDetails);
            $message = "Withdrawal request for " . number_format($amount) . " UZS has been submitted (Request ID: {$withdrawalId}).";
            $this->telegramBot->sendMessage($user['telegram_id'], $message);
        } catch (\Exception $e) {
            $this->telegramBot->sendMessage($user['telegram_id'], "Error processing withdrawal: " . $e->getMessage());
        }
    }

    // Approve a withdrawal request (admin action).
    public function approveWithdrawal(int $withdrawalId, int $adminId): bool
    {
        return $this->balanceService->approveWithdrawal($withdrawalId, $adminId);
    }

    // Reject a withdrawal request (admin action).
    public function rejectWithdrawal(int $withdrawalId, int $adminId, string $reason): bool
    {
        return $this->balanceService->rejectWithdrawal($withdrawalId, $adminId, $reason);
    }
}