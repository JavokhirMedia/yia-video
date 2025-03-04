<?php
// src/Models/Rating.php

namespace App\Models;

use App\Database\Database;

class Rating
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // Retrieve a monthly rating record for a user.
    public function getRatingByUserAndMonth(int $userId, int $month, int $year): ?array
    {
        $sql = "SELECT * FROM monthly_ratings WHERE user_id = ? AND month = ? AND year = ?";
        return $this->db->fetch($sql, [$userId, $month, $year]);
    }

    // Update an existing rating record.
    public function updateRating(int $ratingId, array $data): bool
    {
        $setParts = [];
        $params = [];
        foreach ($data as $key => $value) {
            $setParts[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $ratingId;
        $sql = "UPDATE monthly_ratings SET " . implode(", ", $setParts) . " WHERE id = ?";
        return $this->db->update($sql, $params) !== false;
    }

    // Insert a new rating record.
    public function insertRating(int $userId, int $month, int $year, int $submittedVideos = 0, int $approvedVideos = 0): int
    {
        $sql = "INSERT INTO monthly_ratings (user_id, month, year, submitted_videos, approved_videos) VALUES (?, ?, ?, ?, ?)";
        return $this->db->insert($sql, [$userId, $month, $year, $submittedVideos, $approvedVideos]);
    }

    // Retrieve the leaderboard (top editors) for a given month and year.
    public function getLeaderboard(int $month, int $year, int $limit = 3): array
    {
        $sql = "SELECT u.id, u.full_name, u.username, mr.approval_rate, mr.submitted_videos, mr.approved_videos, mr.rank 
                FROM monthly_ratings mr
                JOIN users u ON mr.user_id = u.id
                WHERE mr.month = ? AND mr.year = ?
                ORDER BY mr.approval_rate DESC, mr.approved_videos DESC
                LIMIT ?";
        return $this->db->fetchAll($sql, [$month, $year, $limit]);
    }
}