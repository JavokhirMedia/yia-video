<?php
// src/Services/RatingService.php

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;

class RatingService
{
    private Database $db;
    private Logger $logger;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('rating');
    }

    public function updateVideoStats(int $userId, string $status): bool
    {
        try {
            $month = date('n');
            $year = date('Y');

            // Check if monthly rating record exists
            $rating = $this->db->fetch(
                "SELECT * FROM monthly_ratings WHERE user_id = ? AND month = ? AND year = ?",
                [$userId, $month, $year]
            );

            if (!$rating) {
                // Create new rating record if not exists
                $this->db->insert(
                    "INSERT INTO monthly_ratings (user_id, month, year, submitted_videos, approved_videos) 
                    VALUES (?, ?, ?, 1, ?)",
                    [$userId, $month, $year, ($status === 'approved' ? 1 : 0)]
                );
            } else {
                // Update existing rating record
                $this->db->update(
                    "UPDATE monthly_ratings SET 
                    submitted_videos = submitted_videos + 1,
                    approved_videos = approved_videos " . ($status === 'approved' ? '+ 1' : '') . "
                    WHERE id = ?",
                    [$rating['id']]
                );
            }

            // Update approval rate
            $this->updateUserApprovalRate($userId, $month, $year);

            // Update leaderboard rankings
            $this->updateLeaderboardRankings($month, $year);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error updating video stats: " . $e->getMessage(), [
                'user_id' => $userId,
                'status' => $status
            ]);
            return false;
        }
    }

    private function updateUserApprovalRate(int $userId, int $month, int $year): void
    {
        try {
            $this->db->update(
                "UPDATE monthly_ratings SET
                approval_rate = CASE
                    WHEN submitted_videos > 0 THEN (approved_videos / submitted_videos) * 100
                    ELSE 0
                END
                WHERE user_id = ? AND month = ? AND year = ?",
                [$userId, $month, $year]
            );
        } catch (\Exception $e) {
            $this->logger->error("Error updating approval rate: " . $e->getMessage(), [
                'user_id' => $userId,
                'month' => $month,
                'year' => $year
            ]);
        }
    }

}