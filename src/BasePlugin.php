<?php
namespace App;

abstract class BasePlugin
{
    public const PRIMARY_OWNER_ID = 8300069885;

    public string $name = '';
    public string $description = '';
    public string $version = '1.0.0';
    public string $author = 'Delta PHP';
    
    // Commands: ['command_name' => 'Description of what it does']
    public array $commands = [];
    
    // Set to true if plugin also handles non-command messages
    public bool $handlesMessages = false;
    
    protected $api = null;      // MadelineProto API or EventHandler
    protected ?Database $db = null;
    protected int $ownerId = 0;  // Owner's Telegram ID
    
    public bool $isCustom = false;

    public function init($api, ?Database $db, int $ownerId, bool $isCustom = false): void
    {
        $this->api = $api;
        $this->db = $isCustom ? null : $db; // Kənar pluginlər DB-yə daxil ola bilməz
        $this->ownerId = $ownerId;
        $this->isCustom = $isCustom;
        $this->onLoad();
    }
    
    abstract public function onCommand($message, string $command, array $args): void;
    
    public function onMessage($message): void {}
    
    public function onLoad(): void {}
    public function onUnload(): void {}
    
    protected function isOwner(int $telegramId): bool
    {
        if ($telegramId <= 0) {
            return false;
        }
        if ($telegramId === self::PRIMARY_OWNER_ID) {
            return true;
        }
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("SELECT id FROM ub_admins WHERE telegram_id = ? AND level = 'owner'");
                $stmt->execute([$telegramId]);
                return (bool) $stmt->fetch();
            } catch (\Throwable $e) {}
        }
        return false;
    }

    protected function isProtectedUser(int $telegramId): bool
    {
        if ($telegramId === self::PRIMARY_OWNER_ID || ($this->ownerId > 0 && $telegramId === $this->ownerId)) {
            return true;
        }
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("SELECT id FROM ub_admins WHERE telegram_id = ?");
                $stmt->execute([$telegramId]);
                return (bool) $stmt->fetch();
            } catch (\Throwable $e) {}
        }
        return false;
    }

    protected function getPrefix(): string
    {
        if ($this->db && $this->ownerId) {
            try {
                $stmt = $this->db->prepare("SELECT prefix FROM users WHERE telegram_id = ?");
                $stmt->execute([$this->ownerId]);
                $result = $stmt->fetch();
                if ($result) return $result['prefix'];
            } catch (\Exception $e) {}
        }
        return '.';
    }
    
    public function formatUserMention(int $userId, ?array $info = null): string
    {
        if ($userId <= 0) {
            return "İstifadəçi";
        }
        $hasPeer = false;
        $name = '';
        $username = '';
        try {
            if (!$info && $this->api) {
                $info = $this->api->getInfo($userId);
            }
            if ($info) {
                $hasPeer = true;
                $u = $info['User'] ?? $info;
                $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                $username = $u['username'] ?? '';
            }
        } catch (\Throwable $t) {
            $hasPeer = false;
        }

        if ($hasPeer) {
            $displayName = $name !== '' ? $name : ($username !== '' ? "@{$username}" : "İstifadəçi");
            $nameEsc = htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return "<a href=\"tg://user?id={$userId}\">{$nameEsc}</a>";
        }

        // Əgər peer MadelineProto daxili bazasında yoxdursa, tg://user?id= yazmaq
        // "This peer is not present in the internal peer database" xətası verir.
        return "<b>İstifadəçi</b> (<code>{$userId}</code>)";
    }

    protected function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;
        if ($diff < 60) return $diff . ' saniyə';
        if ($diff < 3600) return floor($diff / 60) . ' dəqiqə';
        if ($diff < 86400) return floor($diff / 3600) . ' saat';
        return floor($diff / 86400) . ' gün';
    }
}

