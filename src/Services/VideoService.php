<?php
// src/Services/VideoService.php

namespace App\Services;

use App\Database\Database;
use App\Config\Config;
use App\Helpers\Logger;
use App\Services\RatingService;
use App\Services\BalanceService;

class VideoService
{
    private Database $db;
    private Logger $logger;
    private Config $config;
    private RatingService $ratingService;
    private BalanceService $balanceService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('video');
        $this->config = Config::getInstance();
        $this->ratingService = new RatingService();
        $this->balanceService = new BalanceService();
    }

    public function submitVideo(int $userId, string $fileId, string $fileUniqueId, int $messageId): int
    {
        try {
            $this->db->beginTransaction();

            $videoId = $this->db->insert(
                "INSERT INTO videos (user_id, file_id, file_unique_id, message_id, status) 
                VALUES (?, ?, ?, ?, 'pending')",
                [$userId, $fileId, $fileUniqueId, $messageId]
            );

            $this->db->commit();

            $this->logger->info("Video submitted successfully", [
                'video_id' => $videoId,
                'user_id' => $userId
            ]);

            return $videoId;
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->logger->error("Error submitting video: " . $e->getMessage(), ['user_id' => $userId]);
            throw $e;
        }
    }

    public function getVideoById(int $videoId): ?array
    {
        return $this->db->fetch(
            "SELECT v.*, u.telegram_id, u.username, u.full_name 
            FROM videos v
            JOIN users u ON v.user_id = u.id
            WHERE v.id = ?",
            [$videoId]
        );
    }

    public function getPendingVideos(): array
    {
        return $this->db->fetchAll(
            "SELECT v.*, u.telegram_id, u.username, u.full_name 
            FROM videos v
            JOIN users u ON v.user_id = u.id
            WHERE v.status = 'pending'
            ORDER BY v.created_at ASC"
        );
    }

    public function getUserVideos(int $userId, string $status = null): array
    {
        $sql = "SELECT * FROM videos WHERE user_id = ?";
        $params = [$userId];

        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function countUserVideos(int $userId, string $status = null): int
    {
        $sql = "SELECT COUNT(*) as count FROM videos WHERE user_id = ?";
        $params = [$userId];

        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $result = $this->db->fetch($sql, $params);
        return (int) ($result['count'] ?? 0);
    }

    public function approveVideo(int $videoId, int $adminId): bool
    {
        try {
            $this->db->beginTransaction();

            // Get video info
            $video = $this->getVideoById($videoId);

            if (!$video || $video['status'] !== 'pending') {
                $this->db->rollback();
                return false;
            }

            // Update video status
            $this->db->update(
                "UPDATE videos SET 
                status = 'approved', 
                reviewed_by = ?, 
                reviewed_at = NOW() 
                WHERE id = ?",
                [$adminId, $videoId]
            );

            // Add payment to user's balance
            $amount = (float) $this->config->get('VIDEO_APPROVAL_AMOUNT', 100000);
            $this->balanceService->addBalance(
                $video['user_id'],
                $amount,
                'deposit',
                'video',
                $videoId,
                "Payment for approved video #$videoId"
            );

            // Update user's rating
            $this->ratingService->updateVideoStats($video['user_id'], 'approved');

            $this->db->commit();

            $this->logger->info("Video approved", [
                'video_id' => $videoId,
                'user_id' => $video['user_id'],
                'admin_id' => $adminId,
                'amount' => $amount
            ]);

            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->logger->error("Error approving video: " . $e->getMessage(), ['video_id' => $videoId]);
            return false;
        }
    }

    public function rejectVideo(int $videoId, int $adminId, string $reason): bool
    {
        try {
            $this->db->beginTransaction();

            // Get video info
            $video = $this->getVideoById($videoId);

            if (!$video || $video['status'] !== 'pending') {
                $this->db->rollback();
                return false;
            }

            // Update video status
            $this->db->update(
                "UPDATE videos SET 
                status = 'rejected', 
                rejection_reason = ?, 
                reviewed_by = ?, 
                reviewed_at = NOW() 
                WHERE id = ?",
                [$reason, $adminId, $videoId]
            );

            // Update user's rating
            $this->ratingService->updateVideoStats($video['user_id'], 'rejected');

            $this->db->commit();

            $this->logger->info("Video rejected", [
                'video_id' => $videoId,
                'user_id' => $video['user_id'],
                'admin_id' => $adminId,
                'reason' => $reason
            ]);

            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->logger->error("Error rejecting video: " . $e->getMessage(), ['video_id' => $videoId]);
            return false;
        }
    }

    public function getVideoStats(?int $userId = null): array
    {
        try {
            $whereClause = "";
            $params = [];

            if ($userId !== null) {
                $whereClause = "WHERE user_id = ?";
                $params = [$userId];
            }

            $sql = "SELECT 
                COUNT(*) as total_videos,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_videos,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_videos,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_videos
                FROM videos
                $whereClause";

            $stats = $this->db->fetch($sql, $params);

            if ($stats) {
                $stats['approval_rate'] = $stats['total_videos'] > 0
                    ? round(($stats['approved_videos'] / $stats['total_videos']) * 100, 2)
                    : 0;
            }

            return $stats ?? [
                'total_videos' => 0,
                'pending_videos' => 0,
                'approved_videos' => 0,
                'rejected_videos' => 0,
                'approval_rate' => 0
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error getting video stats: " . $e->getMessage());
            return [
                'total_videos' => 0,
                'pending_videos' => 0,
                'approved_videos' => 0,
                'rejected_videos' => 0,
                'approval_rate' => 0
            ];
        }
    }
}