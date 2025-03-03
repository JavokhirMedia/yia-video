<?php
// src/Models/User.php

namespace App\Models;

use App\Database\Database;

class User
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    public function create(array $userData): int
    {
        $sql = "INSERT INTO users (telegram_id, username, full_name, phone_number, registration_date) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $params = [
            $userData['telegram_id'],
            $userData['username'] ?? null,
            $userData['full_name'],
            $userData['phone_number']
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    public function getByTelegramId(int $telegramId): ?array
    {
        $sql = "SELECT * FROM users WHERE telegram_id = ?";
        return $this->db->fetch($sql, [$telegramId]);
    }
    
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM users WHERE id = ?";
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
        
        $sql = "UPDATE users SET " . implode(", ", $setParts) . " WHERE id = ?";
        
        return $this->db->update($sql, $params) !== false;
    }
    
    public function updateByTelegramId(int $telegramId, array $data): bool
    {
        $setParts = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $setParts[] = "$key = ?";
            $params[] = $value;
        }
        
        $params[] = $telegramId;
        
        $sql = "UPDATE users SET " . implode(", ", $setParts) . " WHERE telegram_id = ?";
        
        return $this->db->update($sql, $params) !== false;
    }
    
    public function getAllAdmins(): array
    {
        $sql = "SELECT * FROM users WHERE is_admin = 1 AND is_active = 1";
        return $this->db->fetchAll($sql);
    }
    
    public function getTopEditorsByMonth(int $month, int $year, int $limit = 3): array
    {
        $sql = "SELECT u.id, u.full_name, u.username, mr.approval_rate, mr.submitted_videos, mr.approved_videos, mr.rank 
                FROM monthly_ratings mr
                JOIN users u ON mr.user_id = u.id
                WHERE mr.month = ? AND mr.year = ?
                ORDER BY mr.approval_rate DESC, mr.approved_videos DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$month, $year, $limit]);
    }
    
    public function getTotalUsers(): int
    {
        $sql = "SELECT COUNT(*) as total FROM users WHERE is_admin = 0";
        $result = $this->db->fetch($sql);
        
        return $result['total'] ?? 0;
    }
    
    public function getActiveUsers(int $days = 30): int
    {
        $sql = "SELECT COUNT(DISTINCT user_id) as total 
                FROM videos 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND user_id IN (SELECT id FROM users WHERE is_admin = 0)";
        
        $result = $this->db->fetch($sql, [$days]);
        
        return $result['total'] ?? 0;
    }
}
