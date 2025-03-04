<?php
// src/Models/Admin.php

namespace App\Models;

use App\Database\Database;

class Admin
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // Returns all active admin users.
    public function getAllAdmins(): array
    {
        $sql = "SELECT * FROM users WHERE is_admin = 1 AND is_active = 1";
        return $this->db->fetchAll($sql);
    }

    // Set the admin's current state (for example, when waiting for a rejection reason).
    public function setAdminState(int $adminId, string $state): bool
    {
        $sql = "UPDATE users SET state = ? WHERE id = ? AND is_admin = 1";
        return $this->db->update($sql, [$state, $adminId]) !== false;
    }

    // Retrieve a specific admin by ID.
    public function getAdminById(int $adminId): ?array
    {
        $sql = "SELECT * FROM users WHERE id = ? AND is_admin = 1";
        return $this->db->fetch($sql, [$adminId]);
    }
}