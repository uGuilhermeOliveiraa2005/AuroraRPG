<?php

namespace Aurora\Repositories;

use Aurora\Core\Database;

class AreaRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAreas(): array
    {
        $stmt = $this->db->query("SELECT * FROM areas ORDER BY min_level ASC");
        return $stmt->fetchAll();
    }

    public function getAreaById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM areas WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $area = $stmt->fetch();
        return $area ?: null;
    }

    public function updateCharacterArea(int $characterId, int $areaId): void
    {
        $stmt = $this->db->prepare("UPDATE characters SET current_area_id = :area_id WHERE id = :char_id");
        $stmt->execute(['area_id' => $areaId, 'char_id' => $characterId]);
    }
}
