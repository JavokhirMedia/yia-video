<?php

namespace App\Config;

class Config
{
    private static ?Config $instance = null;
    private array $config;

    private function __construct()
    {
        $this->loadEnvFile();
        $this->config = [
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'user' => $_ENV['DB_USER'],
                'pass' => $_ENV['DB_PASS'],
                'name' => $_ENV['DB_NAME'],
            ],
            'telegram' => [
                'bot_token' => $_ENV['BOT_TOKEN'],
                'webhook_url' => $_ENV['WEBHOOK_URL'],
                'admin_chat_id' => $_ENV['ADMIN_CHAT_ID'],
                'review_channel_id' => $_ENV['REVIEW_CHANNEL_ID'],
            ],
            'payment' => [
                'video_reward' => 100000, // in UZS
                'min_withdrawal' => 300000, // in UZS
            ]
        ];
    }

    private function loadEnvFile(): void
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($envFile)) {
            throw new \RuntimeException('.env file not found');
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}<?php
// src/Config/Config.php

namespace App\Config;

use Dotenv\Dotenv;

class Config
{
    private static $instance = null;
    private array $config = [];

    private function __construct()
    {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->load();
        
        // Set timezone
        date_default_timezone_set($_ENV['TIMEZONE'] ?? 'UTC');
        
        // Load database config
        $this->loadDatabaseConfig();
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    private function loadDatabaseConfig(): void
    {
        $this->config['database'] = [
            'host'     => $_ENV['DB_HOST'] ?? 'localhost',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'dbname'   => $_ENV['DB_NAME'] ?? 'telegram_bot_db',
        ];
    }

    public function get(string $key, $default = null)
    {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        $keys = explode('.', $key);
        $config = $this->config;
        
        foreach ($keys as $part) {
            if (!isset($config[$part])) {
                return $default;
            }
            $config = $config[$part];
        }
        
        return $config;
    }
}
