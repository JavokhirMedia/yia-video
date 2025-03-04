<?php
// src/Controllers/UserController.php

namespace App\Controllers;

use App\Services\UserService;
use App\Bot\TelegramBot;

class UserController
{
    private UserService $userService;
    private TelegramBot $telegramBot;

    public function __construct(TelegramBot $telegramBot)
    {
        $this->userService = new UserService();
        $this->telegramBot = $telegramBot;
    }

    // Start the registration process.
    public function startRegistration(int $telegramId): void
    {
        $this->userService->setUserState($telegramId, 'awaiting_name');
        $this->telegramBot->sendMessage($telegramId, "Welcome! Please enter your full name to start registration.");
    }

    // Save the user's full name and prompt for phone number.
    public function setFullName(int $telegramId, string $fullName): void
    {
        $this->userService->updateUserProfile($telegramId, ['full_name' => $fullName]);
        $this->telegramBot->sendMessage($telegramId, "Thanks! Now please share your phone number.", [
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => 'Share Phone Number', 'request_contact' => true]]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
        $this->userService->setUserState($telegramId, 'awaiting_phone');
    }

    // Save the user's phone number and complete registration.
    public function setPhoneNumber(int $telegramId, string $phoneNumber): void
    {
        $this->userService->updateUserProfile($telegramId, ['phone_number' => $phoneNumber]);
        $this->userService->setUserState($telegramId, 'registered');
        $this->telegramBot->sendMessage($telegramId, "✅ Registration completed successfully! You can now use the bot.", [
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => '📤 Submit Video'], ['text' => '👤 My Profile']],
                    [['text' => '💰 My Balance'], ['text' => '📊 My Rating']]
                ],
                'resize_keyboard' => true
            ])
        ]);
    }

    // Get user data by Telegram ID.
    public function getUserByTelegramId(int $telegramId): ?array
    {
        return $this->userService->getUserByTelegramId($telegramId);
    }

    // Return a formatted user profile.
    public function getProfileInfo(int $userId): string
    {
        $user = $this->userService->getUserById($userId);
        if (!$user) {
            return "Profile not found.";
        }
        $profile  = "<b>Your Profile</b>\n";
        $profile .= "Name: " . $user['full_name'] . "\n";
        $profile .= "Phone: " . $user['phone_number'] . "\n";
        $profile .= "Username: " . $user['username'] . "\n";
        return $profile;
    }

    // Return rating information.
    public function getRatingInfo(int $userId): string
    {
        // For production, you might call a RatingService here.
        // Example: return $this->ratingService->getUserRating($userId);
        return "Your rating information is not available at the moment.";
    }

    // Reset the user's state (e.g., cancel an ongoing operation).
    public function resetUserState(int $telegramId): void
    {
        $this->userService->setUserState($telegramId, 'registered');
    }
}