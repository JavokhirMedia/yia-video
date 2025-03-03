<?php
// src/Bot/TelegramBot.php

namespace App\Bot;

use App\Config\Config;
use App\Helpers\Logger;
use App\Bot\Handlers\MessageHandler;
use App\Bot\Handlers\CommandHandler;
use App\Bot\Handlers\CallbackHandler;

class TelegramBot
{
    private string $token;
    private string $apiUrl = 'https://api.telegram.org/bot';
    private Logger $logger;
    private Config $config;
    
    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->token = $this->config->get('TELEGRAM_BOT_TOKEN');
        $this->logger = new Logger('telegram');
        $this->apiUrl .= $this->token . '/';
    }
    
    public function handleRequest(array $update): void
    {
        try {
            // Log the incoming update for debugging
            if ($this->config->get('ENVIRONMENT') === 'development') {
                $this->logger->debug('Received update', ['update' => json_encode($update)]);
            }
            
            if (isset($update['message'])) {
                $handler = new MessageHandler($this);
                $handler->handle($update['message']);
            } elseif (isset($update['callback_query'])) {
                $handler = new CallbackHandler($this);
                $handler->handle($update['callback_query']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error handling update: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
    
    public function setWebhook(string $url): array
    {
        return $this->request('setWebhook', [
            'url' => $url,
            'allowed_updates' => json_encode(['message', 'callback_query'])
        ]);
    }
    
    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
    }
    
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }
    
    public function sendMessage(int $chatId, string $text, array $options = []): array
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $text
        ];
        
        return $this->request('sendMessage', array_merge($data, $options));
    }
    
    public function sendVideo(int $chatId, string $videoFileId, array $options = []): array
    {
        $data = [
            'chat_id' => $chatId,
            'video' => $videoFileId
        ];
        
        return $this->request('sendVideo', array_merge($data, $options));
    }
    
    public function forwardMessage(int $toChatId, int $fromChatId, int $messageId): array
    {
        return $this->request('forwardMessage', [
            'chat_id' => $toChatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId
        ]);
    }
    
    public function answerCallbackQuery(string $callbackQueryId, array $options = []): array
    {
        $data = [
            'callback_query_id' => $callbackQueryId
        ];
        
        return $this->request('answerCallbackQuery', array_merge($data, $options));
    }
    
    public function editMessageText(int $chatId, int $messageId, string $text, array $options = []): array
    {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text
        ];
        
        return $this->request('editMessageText', array_merge($data, $options));
    }
    
    public function editMessageReplyMarkup(int $chatId, int $messageId, array $replyMarkup = []): array
    {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ];
        
        if (!empty($replyMarkup)) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->request('editMessageReplyMarkup', $data);
    }
    
    private function request(string $method, array $data = []): array
    {
        $url = $this->apiUrl . $method;
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            $this->logger->error("API request failed: {$method}", ['data' => $data]);
            return ['ok' => false, 'description' => 'Failed to connect to Telegram API'];
        }
        
        $result = json_decode($response, true);
        
        if (!$result['ok']) {
            $this->logger->error("API request error: {$method}", [
                'data' => $data,
                'error' => $result['description'] ?? 'Unknown error'
            ]);
        }
        
        return $result;
    }
    
    public function createInlineKeyboard(array $buttons): array
    {
        $inlineKeyboard = [];
        
        foreach ($buttons as $row) {
            $keyboardRow = [];
            
            foreach ($row as $button) {
                $keyboardRow[] = [
                    'text' => $button['text'],
                    'callback_data' => $button['callback_data'] ?? null,
                    'url' => $button['url'] ?? null
                ];
            }
            
            $inlineKeyboard[] = $keyboardRow;
        }
        
        return ['inline_keyboard' => $inlineKeyboard];
    }
    
    public function createKeyboard(array $buttons, bool $resizeKeyboard = true, bool $oneTimeKeyboard = false): array
    {
        return [
            'keyboard' => $buttons,
            'resize_keyboard' => $resizeKeyboard,
            'one_time_keyboard' => $oneTimeKeyboard
        ];
    }
}
