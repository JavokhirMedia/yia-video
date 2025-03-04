<?php
// src/Controllers/AdminController.php

namespace App\Controllers;

use App\Services\AdminService;
use App\Models\Video;
use App\Models\User;
use App\Bot\TelegramBot;

class AdminController
{
    private AdminService $adminService;
    private Video $videoModel;
    private User $userModel;
    private TelegramBot $telegramBot;

    public function __construct(TelegramBot $telegramBot)
    {
        $this->adminService = new AdminService();
        $this->videoModel   = new Video();
        $this->userModel    = new User();
        $this->telegramBot  = $telegramBot;
    }

    // Set the admin's current state (e.g. awaiting a rejection reason).
    public function setAdminState(int $adminId, string $state): bool
    {
        return $this->adminService->setAdminState($adminId, $state);
    }

    // Returns a formatted system statistics message.
    public function getSystemStats(): string
    {
        $totalUsers = $this->userModel->getTotalUsers();
        $videoStats = $this->videoModel->getSystemStats();

        $stats  = "<b>System Statistics:</b>\n";
        $stats .= "Total Users: {$totalUsers}\n";
        $stats .= "Total Videos: {$videoStats['total_videos']}\n";
        $stats .= "Approved Videos: {$videoStats['approved_videos']}\n";
        $stats .= "Rejected Videos: {$videoStats['rejected_videos']}\n";
        $stats .= "Pending Videos: {$videoStats['pending_videos']}\n";
        return $stats;
    }

    // Process an admin reply message (for video or withdrawal rejection).
    public function handleReplyMessage(array $message, array $admin): void
    {
        if (isset($message['reply_to_message'])) {
            $adminState = $admin['state'] ?? '';
            if (strpos($adminState, 'awaiting_rejection_reason:') === 0) {
                $videoId = (int) str_replace('awaiting_rejection_reason:', '', $adminState);
                $reason = trim($message['text'] ?? '');
                if (!empty($reason)) {
                    $this->adminService->rejectVideo($videoId, $admin['id'], $reason);
                    $this->adminService->setAdminState($admin['id'], '');
                    $this->telegramBot->sendMessage($message['chat']['id'], "Video #{$videoId} has been rejected with reason: {$reason}");
                }
            } elseif (strpos($adminState, 'awaiting_withdrawal_rejection:') === 0) {
                $withdrawalId = (int) str_replace('awaiting_withdrawal_rejection:', '', $adminState);
                $reason = trim($message['text'] ?? '');
                if (!empty($reason)) {
                    $this->adminService->rejectWithdrawal($withdrawalId, $admin['id'], $reason);
                    $this->adminService->setAdminState($admin['id'], '');
                    $this->telegramBot->sendMessage($message['chat']['id'], "Withdrawal #{$withdrawalId} has been rejected with reason: {$reason}");
                }
            }
        }
    }

    // Display the admin panel by sending a message with system stats.
    public function showAdminPanel(int $chatId): void
    {
        $stats = $this->getSystemStats();
        $panelText = "<b>Admin Panel</b>\n" . $stats;
        // You can add inline buttons for further actions if desired.
        $keyboard = [
            [
                ['text' => 'Refresh Stats', 'callback_data' => 'refresh_stats']
            ]
        ];
        $replyMarkup = json_encode(['inline_keyboard' => $keyboard]);
        $this->telegramBot->sendMessage($chatId, $panelText, ['parse_mode' => 'HTML', 'reply_markup' => $replyMarkup]);
    }
}