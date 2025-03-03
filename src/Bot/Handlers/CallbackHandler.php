<?php
// src/Bot/Handlers/CallbackHandler.php

namespace App\Bot\Handlers;

use App\Bot\TelegramBot;
use App\Controllers\UserController;
use App\Controllers\VideoController;
use App\Controllers\BalanceController;
use App\Controllers\AdminController;

class CallbackHandler
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
    
    public function handle(array $callbackQuery): void
    {
        $callbackQueryId = $callbackQuery['id'];
        $data = $callbackQuery['data'];
        $message = $callbackQuery['message'];
        $chatId = $message['chat']['id'];
        $messageId = $message['message_id'];
        $telegramId = $callbackQuery['from']['id'];
        
        // Check if the user exists
        $user = $this->userController->getUserByTelegramId($telegramId);
        
        if (!$user) {
            $this->bot->answerCallbackQuery($callbackQueryId, [
                'text' => 'You need to register first. Use /start to begin.',
                'show_alert' => true
            ]);
            return;
        }
        
        // Split data to support parameterized callbacks (format: action:param1:param2)
        $dataParts = explode(':', $data);
        $action = $dataParts[0];
        
        switch ($action) {
            case 'withdraw':
                $this->handleWithdrawAction($user, $chatId, $callbackQueryId);
                break;
                
            case 'approve_video':
                if (!$user['is_admin']) {
                    $this->bot->answerCallbackQuery($callbackQueryId, [
                        'text' => 'Only admins can approve videos',
                        'show_alert' => true
                    ]);
                    return;
                }
                
                $videoId = $dataParts[1] ?? null;
                if ($videoId) {
                    $this->videoController->approveVideo($videoId, $user['id']);
                    $this->bot->editMessageReplyMarkup($chatId, $messageId);
                    $this->bot->answerCallbackQuery($callbackQueryId, [
                        'text' => 'Video approved successfully'
                    ]);
                }
                break;
                
            case 'reject_video':
                if (!$user['is_admin']) {
                    $this->bot->answerCallbackQuery($callbackQueryId, [
                        'text' => 'Only admins can reject videos',
                        'show_alert' => true
                    ]);
                    return;
                }
                
                $videoId = $dataParts[1] ?? null;
                if ($videoId) {
                    // Set admin state to awaiting rejection reason
                    $this->adminController->setAdminState($user['id'], 'awaiting_rejection_reason:' . $videoId);
                    $this->bot->sendMessage($chatId, "Please reply with the reason for rejecting this video.");
                    $this->bot->answerCallbackQuery($callbackQueryId);
                }
                break;
                
            case 'approve_withdrawal':
                if (!$user['is_admin']) {
                    $this->bot->answerCallbackQuery($callbackQueryId, [
                        'text' => 'Only admins can approve withdrawals',
                        'show_alert' => true
                    ]);
                    return;
                }
                
                $withdrawalId = $dataParts[1] ?? null;
                if ($withdrawalId) {
                    $this->balanceController->approveWithdrawal($withdrawalId, $user['id']);
                    $this->bot->editMessageReplyMarkup($chatId, $messageId);
                    $this->bot->answerCallbackQuery($callbackQueryId, [
                        'text' => 'Withdrawal approved successfully'
                    ]);
                }
                break;
                
            case 'reject_withdrawal':
                if (!$user['is_admin']) {
                    $this->bot->answerCallbackQuery($callbackQueryId, [
                        'text' => 'Only admins can reject withdrawals',
                        'show_alert' => true
                    ]);
                    return;
                }
                
                $withdrawalId = $dataParts[1] ?? null;
                if ($withdrawalId) {
                    $this->adminController->setAdminState($user['id'], 'awaiting_withdrawal_rejection:' . $withdrawalId);
                    $this->bot->sendMessage($chatId, "Please reply with the reason for rejecting this withdrawal request.");
                    $this->bot->answerCallbackQuery($callbackQueryId);
                }
                break;
                
            case 'cancel':
                // Cancel the current operation
                $this->userController->resetUserState($telegramId);
                $this->bot->editMessageText($chatId, $messageId, "Operation cancelled.");
                $this->bot->answerCallbackQuery($callbackQueryId, [
                    'text' => 'Operation cancelled'
                ]);
                break;
                
            default:
                $this->bot->answerCallbackQuery($callbackQueryId, [
                    'text' => 'Unknown action'
                ]);
                break;
        }
    }
    
    private function handleWithdrawAction(array $user, int $chatId, string $callbackQueryId): void
    {
        // Check minimum withdrawal amount requirement
        $balanceData = $this->balanceController->getUserBalance($user['id']);
        $minWithdrawalAmount = $this->balanceController->getMinimumWithdrawalAmount();
        
        if ($balanceData['amount'] < $minWithdrawalAmount) {
            $this->bot->answerCallbackQuery($callbackQueryId, [
                'text' => "You need at least " . number_format($minWithdrawalAmount) . " UZS to withdraw.",
                'show_alert' => true
            ]);
            return;
        }
        
        // Set user state to awaiting withdrawal amount
        $this->userController->setUserState($user['telegram_id'], 'awaiting_withdrawal_amount');
        
        $this->bot->sendMessage($chatId, 
            "💰 *Withdrawal Request*\n\n" .
            "Your current balance: *" . number_format($balanceData['amount']) . " UZS*\n" .
            "Minimum withdrawal: *" . number_format($minWithdrawalAmount) . " UZS*\n\n" .
            "Please enter the amount you wish to withdraw:",
            [
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => 'Cancel', 'callback_data' => 'cancel']]
                    ]
                ])
            ]
        );
        
        $this->bot->answerCallbackQuery($callbackQueryId);
    }
}
