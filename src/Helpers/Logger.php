<?php
// src/Helpers/Logger.php

namespace App\Helpers;

use App\Config\Config;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private MonologLogger $logger;

    public function __construct(string $channel = 'app')
    {
        $this->logger = new MonologLogger($channel);
        
        $config = Config::getInstance();
        $logDir = dirname(__DIR__, 2) . '/logs';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);
        
        // Create a handler for errors
        $errorHandler = new RotatingFileHandler(
            $logDir . '/error.log',
            30,
            $config->get('ENVIRONMENT') === 'production' ? MonologLogger::ERROR : MonologLogger::DEBUG
        );
        $errorHandler->setFormatter($formatter);
        
        // Create a handler for transactions
        $transactionHandler = new StreamHandler(
            $logDir . '/transactions.log',
            MonologLogger::INFO
        );
        $transactionHandler->setFormatter($formatter);
        
        // Add handlers based on channel
        if ($channel === 'transactions') {
            $this->logger->pushHandler($transactionHandler);
        } else {
            $this->logger->pushHandler($errorHandler);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function logTransaction(int $userId, string $type, float $amount, string $description, array $context = []): void
    {
        $transactionLogger = new Logger('transactions');
        $context = array_merge([
            'user_id' => $userId,
            'amount' => $amount,
            'type' => $type
        ], $context);
        
        $transactionLogger->info($description, $context);
    }
}
