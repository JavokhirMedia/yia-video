<?php
// public/webhook.php

// Require the autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use App\Bot\TelegramBot;

// Get the input from the webhook
$content = file_get_contents('php://input');
$update = json_decode($content, true);

// Set error reporting based on environment
$config = \App\Config\Config::getInstance();
if ($config->get('ENVIRONMENT') === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Initialize the bot
$bot = new TelegramBot();

// Process the update
if ($update !== null) {
    $bot->handleRequest($update);
} else {
    // For debugging or setting up webhook
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'set_webhook':
            $webhookUrl = $config->get('TELEGRAM_WEBHOOK_URL');
            $result = $bot->setWebhook($webhookUrl);
            echo json_encode($result);
            break;
            
        case 'delete_webhook':
            $result = $bot->deleteWebhook();
            echo json_encode($result);
            break;
            
        case 'get_webhook_info':
            $result = $bot->getWebhookInfo();
            echo json_encode($result);
            break;
            
        default:
            // For initial webhook setup verification
            echo "Telegram Bot Webhook";
            break;
    }
}
