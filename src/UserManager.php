<?php
namespace App;

class UserManager
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function createUser(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (telegram_id, phone, first_name, last_name, username, session_file) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['telegram_id'],
            $data['phone'],
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['username'] ?? '',
            $data['session_file']
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getUserByTelegramId(int $telegramId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getUserById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getAllActiveUsers(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function updateUser(int $telegramId, array $data): bool
    {
        if (empty($data)) return false;
        
        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $telegramId;
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE telegram_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function deleteUser(int $telegramId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM users WHERE telegram_id = ?");
        return $stmt->execute([$telegramId]);
    }

    public function setPrefix(int $telegramId, string $prefix): bool
    {
        return $this->updateUser($telegramId, ['prefix' => $prefix]);
    }

    public function getPrefix(int $telegramId): string
    {
        $user = $this->getUserByTelegramId($telegramId);
        return $user ? $user['prefix'] : '.';
    }

    public function setStatus(int $telegramId, string $status): bool
    {
        return $this->updateUser($telegramId, ['status' => $status]);
    }

    public function log(int $userId, string $action, string $details = ''): void
    {
        $stmt = $this->db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $details]);
    }

    public function getLogs(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("SELECT * FROM logs WHERE user_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getUserSettings(int $userId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
