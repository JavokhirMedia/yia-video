<?php
// src/Services/AdminService.php

namespace App\Services;

use App\Models\Admin;
use App\Models\Video;
use App\Models\Transaction;
use App\Services\BalanceService;

class AdminService
{
    private Admin $adminModel;
    private Video $videoModel;
    private Transaction $transactionModel;
    private BalanceService $balanceService;

    public function __construct()
    {
        $this->adminModel = new Admin();
        $this->videoModel = new Video();
        $this->transactionModel = new Transaction();
        $this->balanceService = new BalanceService();
    }

    // Set the admin's state (e.g. awaiting reply).
    public function setAdminState(int $adminId, string $state): bool
    {
        return $this->adminModel->setAdminState($adminId, $state);
    }

    // Reject a video submission.
    public function rejectVideo(int $videoId, int $adminId, string $reason): bool
    {
        return $this->videoModel->reject($videoId, $adminId, $reason);
    }

    // Approve a withdrawal request.
    public function approveWithdrawal(int $withdrawalId, int $adminId): bool
    {
        return $this->balanceService->approveWithdrawal($withdrawalId, $adminId);
    }

    // Reject a withdrawal request.
    public function rejectWithdrawal(int $withdrawalId, int $adminId, string $reason): bool
    {
        return $this->balanceService->rejectWithdrawal($withdrawalId, $adminId, $reason);
    }
}