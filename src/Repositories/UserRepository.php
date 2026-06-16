<?php

namespace Aurora\Repositories;

use Aurora\Core\Database;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $telegramId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE telegram_id = :id");
        $stmt->execute(['id' => $telegramId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function create(int $telegramId, string $username, string $firstName): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (telegram_id, username, first_name)
            VALUES (:id, :username, :first_name)
            ON CONFLICT (telegram_id) DO UPDATE 
            SET username = EXCLUDED.username, first_name = EXCLUDED.first_name
        ");
        $stmt->execute([
            'id' => $telegramId,
            'username' => $username,
            'first_name' => $firstName
        ]);
    }

    public function updateState(int $telegramId, string $state): void
    {
        $stmt = $this->db->prepare("UPDATE users SET state = :state, last_interaction = CURRENT_TIMESTAMP WHERE telegram_id = :id");
        $stmt->execute([
            'state' => $state,
            'id' => $telegramId
        ]);
    }
}
