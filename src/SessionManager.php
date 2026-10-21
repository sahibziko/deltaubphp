<?php
namespace App;

class SessionManager
{
    private string $sessionsDir;

    public function __construct(string $sessionsDir)
    {
        $this->sessionsDir = rtrim($sessionsDir, '/\\');
        if (!is_dir($this->sessionsDir)) {
            mkdir($this->sessionsDir, 0777, true);
        }
    }

    public function getSessionPath(int $telegramId): string
    {
        return $this->sessionsDir . DIRECTORY_SEPARATOR . $telegramId . '.madeline';
    }

    public function sessionExists(int $telegramId): bool
    {
        return file_exists($this->getSessionPath($telegramId));
    }

    public function deleteSession(int $telegramId): bool
    {
        $path = $this->getSessionPath($telegramId);
        if (file_exists($path)) {
            if (is_file($path)) {
                return unlink($path);
            }
        }
        return false;
    }

    public function getAllSessions(): array
    {
        $sessions = [];
        $files = scandir($this->sessionsDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && str_ends_with($file, '.madeline')) {
                $sessions[] = $this->sessionsDir . DIRECTORY_SEPARATOR . $file;
            }
        }
        return $sessions;
    }
}
