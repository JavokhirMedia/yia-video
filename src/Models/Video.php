<?php
// src/Models/Video.php

namespace App\Models;

use App\Database\Database;

class Video
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    public function create(array $videoData): int
    {
        $sql = "INSERT INTO videos (user_id, file_id, file_unique_id, message_id, status) 
                VALUES (?, ?, ?, ?, 'pending')";
        
        $params = [
            $videoData['user_id'],
            $videoData['file_id'],
            $videoData['file_unique_id'],
            $videoData['message_id']
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    public function getById(int $id): ?array
    {
        $sql = "SELECT v.*, u.telegram_id, u.full_name 
                FROM videos v
                JOIN users u ON v.user_id = u.id 
                WHERE v.id = ?";
        
        return $this->db->fetch($sql, [$id]);
    }
    
    public function update(int $id, array $data): bool
    {
        $setParts = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $setParts[] = "$key = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE videos SET " . implode(", ", $setParts) . " WHERE id = ?";
        
        return $this->db->update($sql, $params) !== false;
    }
    
    public function approve(int $id, int $reviewerId): bool
    {
        $sql = "UPDATE videos SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$reviewerId, $id]) !== false;
    }
    
    public function reject(int $id, int $reviewerId, string $reason): bool
    {
        $sql = "UPDATE videos SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ? WHERE id = ?";
        return $this->db->update($sql, [$reviewerId, $reason, $id]) !== false;
    }
    
    public function getVideosByUser(int $userId, string $status = null): array
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
    
    public function getPendingVideos(): array
    {
        $sql = "SELECT v.*, u.telegram_id, u.full_name 
                FROM videos v
                JOIN users u ON v.user_id = u.id 
                WHERE v.status = 'pending'
                ORDER BY v.created_at ASC";
        
        return $this->db->fetchAll($sql);
    }
    
    public function getVideoStats(int $userId, int $month = null, int $year = null): array
    {
        $params = [$userId];
        $whereClause = "";
        
        if ($month !== null && $year !== null) {
            $whereClause = " AND MONTH(created_at) = ? AND YEAR(created_at) = ?";
            $params[] = $month;
            $params[] = $year;
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_videos,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_videos,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_videos,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_videos
                FROM videos 
                WHERE user_id = ?" . $whereClause;
        
        $result = $this->db->fetch($sql, $params);
        
        // Calculate approval rate
        if ($result && $result['total_videos'] > 0) {
            $totalReviewed = $result['approved_videos'] + $result['rejected_videos'];
            $result['approval_rate'] = $totalReviewed > 0 
                ? round(($result['approved_videos'] / $totalReviewed) * 100, 2) 
                : 0;
        } else {
            $result['approval_rate'] = 0;
        }
        
        return $result;
    }
    
    public function getSystemStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_videos,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_videos,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_videos,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_videos
                FROM videos";
        
        $result = $this->db->fetch($sql);
        
        // Add stats for current month
        $currentMonth = date('n');
        $currentYear = date('Y');
        
        $sql = "SELECT 
                    COUNT(*) as total_videos_month,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_videos_month,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_videos_month
                FROM videos
                WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?";
        
        $monthResult = $this->db->fetch($sql, [$currentMonth, $currentYear]);
        
        return array_merge($result, $monthResult);
    }
}
