<?php
// src/Database/Database.php

namespace App\Database;

use App\Config\Config;
use App\Helpers\Logger;
use mysqli;
use mysqli_sql_exception;

class Database
{
    private static ?Database $instance = null;
    private mysqli $connection;
    private Logger $logger;

    private function __construct()
    {
        $this->logger = new Logger('database');
        $config = Config::getInstance();
        
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        try {
            $this->connection = new mysqli(
                $config->get('database.host'),
                $config->get('database.username'),
                $config->get('database.password'),
                $config->get('database.dbname')
            );
            
            $this->connection->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            $this->logger->error('Database connection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    public function getConnection(): mysqli
    {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): mixed
    {
        try {
            $stmt = $this->connection->prepare($sql);
            
            if (!empty($params)) {
                $types = '';
                $bindParams = [];
                
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } elseif (is_string($param)) {
                        $types .= 's';
                    } else {
                        $types .= 'b';
                    }
                    $bindParams[] = $param;
                }
                
                $stmt->bind_param($types, ...$bindParams);
            }
            
            $stmt->execute();
            
            if (str_starts_with(trim(strtoupper($sql)), 'SELECT')) {
                $result = $stmt->get_result();
                return $result;
            }
            
            return true;
        } catch (mysqli_sql_exception $e) {
            $this->logger->error('Query execution failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw $e;
        }
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $result = $this->query($sql, $params);
        $rows = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        
        return $rows;
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return $this->connection->insert_id;
    }

    public function update(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return $this->connection->affected_rows;
    }

    public function delete(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return $this->connection->affected_rows;
    }

    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollback(): void
    {
        $this->connection->rollback();
    }
}
