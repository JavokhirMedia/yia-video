<?php
// src/Bot/Handlers/MessageHandler.php

namespace App\Bot\Handlers;

use App\Bot\TelegramBot;
use App\Controllers\UserController;
use App\Controllers\VideoController;
use App\Controllers\BalanceController;
use App\Controllers\AdminController;

class MessageHandler
{
    private TelegramBot $bot;
    private UserController $userController;
    private VideoController $videoController;
    private BalanceController $balanceController;
    private AdminController $adminController;
    
    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
        $this->userController = new UserController();
        $this->videoController = new VideoController();
        $this->balanceController = new BalanceController();
        $this->adminController = new AdminController();
    }
    
    public function handle(array $message): void
    {
        $chatId = $message['chat']['id'];
        $telegramId = $message['from']['id'];
        $text = $message['text'] ?? null;
        
        // Check if the user exists or needs to register
        $user = $this->userController->getUserByTelegramId($telegramId);
        
        // Handle commands
        if ($text && str_starts_with($text, '/')) {
            $this->handleCommand($message, $user);
            return;
        }
        
        // Not registered yet
        if (!$user) {
            // Handle registration process
            $this->handleRegistration($message);
            return;
        }
        
        // Handle videos
        if (isset($message['video'])) {
            $this->videoController->handleVideoSubmission($message, $user);
            return;
        }
        
        // Handle specific admin commands
        if ($user['is_admin'] && isset($message['reply_to_message'])) {
            $this->adminController->handleReplyMessage($message, $user);
            return;
        }
        
        // Handle regular text messages based on user's current state
        $userState = $this->userController->getUserState($telegramId);
        
        switch ($userState) {
            case 'awaiting_name':
                $this->userController->setFullName($telegramId, $text);
                $this->bot->sendMessage($chatId, "Thanks! Now please send your phone number.", [
                    'reply_markup' => json_encode([
                        'keyboard' => [[['text' => 'Share Phone Number', 'request_contact' => true]]],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ])
                ]);
                $this->userController->setUserState($telegramId, 'awaiting_phone');
                break;
                
            case 'awaiting_phone':
                if (isset($message['contact'])) {
                    $phoneNumber = $message['contact']['phone_number'];
                    <?php
// src/Bot/Handlers/MessageHandler.php (continued)

                    $this->userController->setPhoneNumber($telegramId, $phoneNumber);
                    $this->userController->completeRegistration($telegramId);
                    $this->bot->sendMessage($chatId, "✅ Registration completed successfully! You can now use the bot to submit videos for review.", [
                        'reply_markup' => json_encode([
                            'keyboard' => [
                                [['text' => '📤 Submit Video'], ['text' => '👤 My Profile']],
                                [['text' => '💰 My Balance'], ['text' => '📊 My Rating']]
                            ],
                            'resize_keyboard' => true
                        ])
                    ]);
                } else {
                    $this->bot->sendMessage($chatId, "Please share your phone number using the button below.", [
                        'reply_markup' => json_encode([
                            'keyboard' => [[['text' => 'Share Phone Number', 'request_contact' => true]]],
                            'resize_keyboard' => true,
                            'one_time_keyboard' => true
                        ])
                    ]);
                }
                break;
                
            case 'awaiting_withdrawal_amount':
                if (is_numeric($text)) {
                    $amount = floatval($text);
                    $this->balanceController->handleWithdrawalRequest($user, $amount);
                } else {
                    $this->bot->sendMessage($chatId, "Please enter a valid amount.");
                }
                break;
                
            default:
                // Handle menu buttons
                switch ($text) {
                    case '📤 Submit Video':
                        $this->bot->sendMessage($chatId, "Please send your video for review. The video will be reviewed by our team and you will be notified of the result.");
                        break;
                        
                    case '👤 My Profile':
                        $profileInfo = $this->userController->getProfileInfo($user['id']);
                        $this->bot->sendMessage($chatId, $profileInfo);
                        break;
                        
                    case '💰 My Balance':
                        $balanceInfo = $this->balanceController->getBalanceInfo($user['id']);
                        $withdrawalButton = [[
                            'text' => '💸 Withdraw Funds',
                            'callback_data' => 'withdraw'
                        ]];
                        
                        $this->bot->sendMessage($chatId, $balanceInfo, [
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [$withdrawalButton]
                            ]),
                            'parse_mode' => 'HTML'
                        ]);
                        break;
                        
                    case '📊 My Rating':
                        $ratingInfo = $this->userController->getRatingInfo($user['id']);
                        $this->bot->sendMessage($chatId, $ratingInfo, [
                            'parse_mode' => 'HTML'
                        ]);
                        break;
                        
                    default:
                        if ($user['is_admin']) {
                            // Check for admin specific text commands
                            if (str_starts_with($text, 'stats')) {
                                $stats = $this->adminController->getSystemStats();
                                $this->bot->sendMessage($chatId, $stats, [
                                    'parse_mode' => 'HTML'
                                ]);
                            }
                        } else {
                            $this->bot->sendMessage($chatId, "Please use the menu buttons to interact with the bot.");
                        }
                        break;
                }
                break;
        }
    }
    
    private function handleCommand(array $message, ?array $user): void
    {
        $chatId = $message['chat']['id'];
        $command = explode(' ', $message['text'])[0];
        
        switch ($command) {
            case '/start':
                if (!$user) {
                    $this->bot->sendMessage($chatId, "Welcome to the Video Editors Bot! 🎬\n\nThis bot helps video editors submit their work for review and get paid for approved videos.\n\nTo get started, please register by providing your full name.");
                    $this->userController->startRegistration($message['from']['id']);
                } else {
                    $this->bot->sendMessage($chatId, "Welcome back! Use the menu to submit videos, check your balance, or view your rating.", [
                        'reply_markup' => json_encode([
                            'keyboard' => [
                                [['text' => '📤 Submit Video'], ['text' => '👤 My Profile']],
                                [['text' => '💰 My Balance'], ['text' => '📊 My Rating']]
                            ],
                            'resize_keyboard' => true
                        ])
                    ]);
                }
                break;
                
            case '/help':
                $helpText = "📋 *Bot Commands*:\n\n";
                $helpText .= "• /start - Start or restart the bot\n";
                $helpText .= "• /help - Show this help message\n";
                $helpText .= "• /profile - View your profile information\n";
                $helpText .= "• /balance - Check your current balance\n";
                $helpText .= "• /rating - View your current rating\n\n";
                $helpText .= "📤 *Submitting Videos*:\n";
                $helpText .= "Simply send a video to the bot, and it will be submitted for review.\n\n";
                $helpText .= "💰 *Withdrawals*:\n";
                $helpText .= "Use the 'Withdraw Funds' button in the balance menu to request a withdrawal.";
                
                $this->bot->sendMessage($chatId, $helpText, [
                    'parse_mode' => 'Markdown'
                ]);
                break;
                
            case '/profile':
                if ($user) {
                    $profileInfo = $this->userController->getProfileInfo($user['id']);
                    $this->bot->sendMessage($chatId, $profileInfo);
                } else {
                    $this->bot->sendMessage($chatId, "You need to register first. Use /start to begin the registration process.");
                }
                break;
                
            case '/balance':
                if ($user) {
                    $balanceInfo = $this->balanceController->getBalanceInfo($user['id']);
                    $withdrawalButton = [[
                        'text' => '💸 Withdraw Funds',
                        'callback_data' => 'withdraw'
                    ]];
                    
                    $this->bot->sendMessage($chatId, $balanceInfo, [
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [$withdrawalButton]
                        ]),
                        'parse_mode' => 'HTML'
                    ]);
                } else {
                    $this->bot->sendMessage($chatId, "You need to register first. Use /start to begin the registration process.");
                }
                break;
                
            case '/rating':
                if ($user) {
                    $ratingInfo = $this->userController->getRatingInfo($user['id']);
                    $this->bot->sendMessage($chatId, $ratingInfo, [
                        'parse_mode' => 'HTML'
                    ]);
                } else {
                    $this->bot->sendMessage($chatId, "You need to register first. Use /start to begin the registration process.");
                }
                break;
                
            // Admin commands
            case '/admin':
                if ($user && $user['is_admin']) {
                    $this->adminController->showAdminPanel($chatId);
                }
                break;
                
            default:
                // Unknown command
                $this->bot->sendMessage($chatId, "Unknown command. Use /help to see available commands.");
                break;
        }
    }
    
    private function handleRegistration(array $message): void
    {
        $chatId = $message['chat']['id'];
        $telegramId = $message['from']['id'];
        $text = $message['text'] ?? null;
        
        // Check the current registration state
        $userState = $this->userController->getUserState($telegramId);
        
        if (!$userState) {
            // New user, start registration
            $this->userController->startRegistration($telegramId);
            $this->bot->sendMessage($chatId, "Welcome to the Video Editors Bot! 🎬\n\nPlease enter your full name to start the registration.");
        }
    }
}
