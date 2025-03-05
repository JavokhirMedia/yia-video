<?php
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
