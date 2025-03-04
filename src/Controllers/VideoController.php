<?php
// src/Controllers/VideoController.php

namespace App\Controllers;

use App\Services\VideoService;
use App\Bot\TelegramBot;

class VideoController
{
    private VideoService $videoService;
    private TelegramBot $telegramBot;

    public function __construct(TelegramBot $telegramBot)
    {
        $this->videoService = new VideoService();
        $this->telegramBot = $telegramBot;
    }

    // Handle a video submission from a user.
    public function handleVideoSubmission(array $message, array $user): void
    {
        if (!isset($message['video'])) {
            $this->telegramBot->sendMessage($message['chat']['id'], "No video detected.");
            return;
        }
        $fileId = $message['video']['file_id'] ?? '';
        $fileUniqueId = $message['video']['file_unique_id'] ?? '';
        $messageId = $message['message_id'] ?? 0;
        if (!$fileId || !$fileUniqueId || !$messageId) {
            $this->telegramBot->sendMessage($message['chat']['id'], "Invalid video data.");
            return;
        }
        $videoId = $this->videoService->submitVideo($user['id'], $fileId, $fileUniqueId, $messageId);
        $this->telegramBot->sendMessage($message['chat']['id'], "Your video has been submitted for review (ID: {$videoId}).");
        // Optionally forward the video to a review channel.
    }

    // Approve a video (admin action).
    public function approveVideo($videoId, int $adminId): void
    {
        $result = $this->videoService->approveVideo($videoId, $adminId);
        if ($result) {
            $this->telegramBot->sendMessage($adminId, "Video #{$videoId} approved successfully.");
        } else {
            $this->telegramBot->sendMessage($adminId, "Failed to approve video #{$videoId}.");
        }
    }

    // Reject a video (admin action).
    public function rejectVideo($videoId, int $adminId, string $reason): void
    {
        $result = $this->videoService->rejectVideo($videoId, $adminId, $reason);
        if ($result) {
            $this->telegramBot->sendMessage($adminId, "Video #{$videoId} rejected successfully with reason: {$reason}");
        } else {
            $this->telegramBot->sendMessage($adminId, "Failed to reject video #{$videoId}.");
        }
    }
}