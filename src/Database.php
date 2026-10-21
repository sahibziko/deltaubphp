<?php
namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    private static ?array $cachedConfig = null;

    private function __construct(array $config)
    {
        self::$cachedConfig = $config;
        $this->connect();
        $this->initSchema();
    }

    private function connect(): void
    {
        $config = self::$cachedConfig;
        if (!$config) {
            throw new \Exception("Database config is missing");
        }

        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        
        try {
            $this->connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function initSchema(): void
    {
        try {
            $this->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                telegram_id BIGINT UNIQUE NOT NULL,
                prefix VARCHAR(10) DEFAULT '.',
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->exec("CREATE TABLE IF NOT EXISTS user_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_setting (user_id, setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->exec("CREATE TABLE IF NOT EXISTS bot_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->exec("INSERT IGNORE INTO bot_settings (setting_key, setting_value) VALUES ('helper_bot_token', '')");
            $this->exec("INSERT IGNORE INTO bot_settings (setting_key, setting_value) VALUES ('log_group', '')");
            $this->exec("INSERT IGNORE INTO bot_settings (setting_key, setting_value) VALUES ('official_channel', '')");
            $this->exec("INSERT IGNORE INTO bot_settings (setting_key, setting_value) VALUES ('official_group', '')");

            $this->exec("CREATE TABLE IF NOT EXISTS ub_admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                telegram_id BIGINT UNIQUE NOT NULL,
                added_by BIGINT NOT NULL,
                level ENUM('owner', 'admin') DEFAULT 'admin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->exec("CREATE TABLE IF NOT EXISTS user_clones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                telegram_id BIGINT NOT NULL,
                first_name VARCHAR(255) DEFAULT '',
                last_name VARCHAR(255) DEFAULT '',
                about TEXT,
                photo_path VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_clone (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->exec("CREATE TABLE IF NOT EXISTS snips (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                keyword VARCHAR(100) NOT NULL,
                content TEXT DEFAULT NULL,
                media_type VARCHAR(20) DEFAULT NULL,
                media_path LONGTEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_snip (user_id, keyword)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            try {
                $this->exec("ALTER TABLE snips ADD COLUMN media_type VARCHAR(20) DEFAULT NULL");
            } catch (\Throwable $t) {}

            try {
                $this->exec("ALTER TABLE snips ADD COLUMN media_path LONGTEXT DEFAULT NULL");
            } catch (\Throwable $t) {}

            $this->exec("CREATE TABLE IF NOT EXISTS pm_approved (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                chat_id BIGINT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_pm (user_id, chat_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->exec("CREATE TABLE IF NOT EXISTS custom_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                msg_key VARCHAR(50) NOT NULL,
                msg_content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_msgkey (user_id, msg_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Throwable $e) {}
    }

    public static function getInstance(array $config = null): self
    {
        if ($config !== null) {
            self::$cachedConfig = $config;
        }
        if (self::$instance === null) {
            if (self::$cachedConfig === null) {
                throw new \Exception("Database config not provided. Call getInstance(config) first.");
            }
            self::$instance = new self(self::$cachedConfig);
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        $this->ensureConnection();
        return $this->connection;
    }

    public function ensureConnection(): void
    {
        if ($this->connection === null) {
            $this->connect();
            return;
        }

        try {
            @$this->connection->query("SELECT 1");
        } catch (\Throwable $e) {
            $this->connect();
        }
    }

    private function isGoneAway(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'gone away') ||
               str_contains($msg, 'lost connection') ||
               str_contains($msg, '2006') ||
               str_contains($msg, '2013');
    }

    public function prepare(string $query): \PDOStatement
    {
        try {
            return $this->connection->prepare($query);
        } catch (\Throwable $e) {
            if ($this->isGoneAway($e)) {
                $this->connect();
                return $this->connection->prepare($query);
            }
            throw $e;
        }
    }

    public function query(string $query): \PDOStatement
    {
        try {
            return $this->connection->query($query);
        } catch (\Throwable $e) {
            if ($this->isGoneAway($e)) {
                $this->connect();
                return $this->connection->query($query);
            }
            throw $e;
        }
    }

    public function exec(string $query): int|false
    {
        try {
            return $this->connection->exec($query);
        } catch (\Throwable $e) {
            if ($this->isGoneAway($e)) {
                $this->connect();
                return $this->connection->exec($query);
            }
            throw $e;
        }
    }

    public function lastInsertId(?string $name = null): string|false
    {
        try {
            return $this->connection->lastInsertId($name);
        } catch (\Throwable $e) {
            if ($this->isGoneAway($e)) {
                $this->connect();
                return $this->connection->lastInsertId($name);
            }
            throw $e;
        }
    }
}
