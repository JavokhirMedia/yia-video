<?php
// src/Services/UserService.php

namespace App\Services;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\Validator;

class UserService
{
    private Database $db;
    private Logger $logger;
    private Validator $validator;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('user');
        $this->validator = new Validator();
    }

    public function registerUser(int $telegramId, string $username, string $fullName, string $phoneNumber): int
    {
        try {
            $this->db->beginTransaction();

            // Validate input
            $validation = $this->validator->validate(
                ['telegram_id' => $telegramId, 'full_name' => $fullName, 'phone_number' => $phoneNumber],
                [
                    'telegram_id' => 'required|integer',
                    'full_name' => 'required|min:2|max:255',
                    'phone_number' => 'required|phone'
                ]
            );

            if (!$validation) {
                throw new \Exception('Invalid registration data: ' . implode(', ', array_map(fn($err) => implode(', ', $err), $this->validator->getErrors())));
            }

            // Check if user already exists
            $existingUser = $this->getUserByTelegramId($telegramId);
            if ($existingUser) {
                $this->db->commit();
                return $existingUser['id'];
            }

            // Insert new user
            $userId = $this->db->insert(
                "INSERT INTO users (telegram_id, username, full_name, phone_number, registration_date) 
                VALUES (?, ?, ?, ?, NOW())",
                [$telegramId, $username, $fullName, $phoneNumber]
            );

            // Create balance record for user
            $this->db->insert(
                "INSERT INTO balances (user_id, amount) VALUES (?, 0.00)",
                [$userId]
            );

            // Initialize monthly rating for current month
            $month = date('n');
            $year = date('Y');
            $this->db->insert(
                "INSERT INTO monthly_ratings (user_id, month, year) VALUES (?, ?, ?)",
                [$userId, $month, $year]
            );

            $this->db->commit();
            $this->logger->info("User registered successfully", ['user_id' => $userId, 'telegram_id' => $telegramId]);

            return $userId;
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->logger->error("Error registering user: " . $e->getMessage(), ['telegram_id' => $telegramId]);
            throw $e;
        }
    }

    public function getUserByTelegramId(int $telegramId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM users WHERE telegram_id = ?",
            [$telegramId]
        );
    }

    public function getUserById(int $userId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM users WHERE id = ?",
            [$userId]
        );
    }

    public function getAllUsers(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM users";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY id DESC";

        return $this->db->fetchAll($sql);
    }

    public function setUserState(int $telegramId, string $state): bool
    {
        try {
            $sql = "UPDATE users SET state = ? WHERE telegram_id = ?";
            $this->db->update($sql, [$state, $telegramId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error setting user state: " . $e->getMessage(), [
                'telegram_id' => $telegramId,
                'state' => $state
            ]);
            return false;
        }
    }

    public function getUserState(int $telegramId): ?string
    {
        $user = $this->getUserByTelegramId($telegramId);
        return $user['state'] ?? null;
    }

    public function updateUserProfile(int $userId, array $data): bool
    {
        try {
            $updateFields = [];
            $params = [];

            if (isset($data['full_name'])) {
                $updateFields[] = "full_name = ?";
                $params[] = $data['full_name'];
            }

            if (isset($data['phone_number'])) {
                $updateFields[] = "phone_number = ?";
                $params[] = $data['phone_number'];
            }

            if (isset($data['is_active'])) {
                $updateFields[] = "is_active = ?";
                $params[] = $data['is_active'];
            }

            if (empty($updateFields)) {
                return false;
            }

            $params[] = $userId;

            $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $this->db->update($sql, $params);

            $this->logger->info("User profile updated", ['user_id' => $userId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error updating user profile: " . $e->getMessage(), ['user_id' => $userId]);
            return false;
        }
    }

    public function toggleAdminStatus(int $userId): bool
    {
        try {
            $user = $this->getUserById($userId);

            if (!$user) {
                return false;
            }

            $newStatus = $user['is_admin'] ? 0 : 1;

            $sql = "UPDATE users SET is_admin = ? WHERE id = ?";
            $this->db->update($sql, [$newStatus, $userId]);

            $this->logger->info("User admin status toggled", [
                'user_id' => $userId,
                'is_admin' => $newStatus
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error toggling admin status: " . $e->getMessage(), ['user_id' => $userId]);
            return false;
        }
    }

    public function deactivateUser(int $userId): bool
    {
        try {
            $sql = "UPDATE users SET is_active = 0 WHERE id = ?";
            $this->db->update($sql, [$userId]);

            $this->logger->info("User deactivated", ['user_id' => $userId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error deactivating user: " . $e->getMessage(), ['user_id' => $userId]);
            return false;
        }
    }

    public function activateUser(int $userId): bool
    {
        try {
            $sql = "UPDATE users SET is_active = 1 WHERE id = ?";
            $this->db->update($sql, [$userId]);

            $this->logger->info("User activated", ['user_id' => $userId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error activating user: " . $e->getMessage(), ['user_id' => $userId]);
            return false;
        }
    }

    public function getAdminList(): array
    {
        return $this->db->fetchAll(
            "SELECT id, telegram_id, username, full_name FROM users WHERE is_admin = 1 AND is_active = 1"
        );
    }

    public function isAdmin(int $telegramId): bool
    {
        $user = $this->getUserByTelegramId($telegramId);
        return $user && $user['is_admin'] == 1;
    }
}