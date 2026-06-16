<?php

namespace Aurora\Repositories;

use Aurora\Core\Database;
use PDO;

class CombatRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getRandomMonsterByArea(int $level): ?array
    {
        // Encontra área compatível
        $stmt = $this->db->prepare("SELECT id FROM areas WHERE min_level <= :level AND max_level >= :level ORDER BY id DESC LIMIT 1");
        $stmt->execute(['level' => $level]);
        $areaId = $stmt->fetchColumn();

        if (!$areaId) {
            $areaId = 1; // Fallback
        }

        // Pega monstro aleatório
        $stmt = $this->db->prepare("SELECT * FROM monsters WHERE area_id = :area_id ORDER BY RANDOM() LIMIT 1");
        $stmt->execute(['area_id' => $areaId]);
        $monster = $stmt->fetch();

        return $monster ?: null;
    }

    public function getCombatInstance(int $characterId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ci.*, m.name, m.level, m.damage_min, m.damage_max, m.defense, m.base_xp, m.base_gold, m.hp as max_hp
            FROM combat_instances ci
            JOIN monsters m ON ci.monster_id = m.id
            WHERE ci.character_id = :char_id
        ");
        $stmt->execute(['char_id' => $characterId]);
        $combat = $stmt->fetch();
        return $combat ?: null;
    }

    public function createCombatInstance(int $characterId, int $monsterId, int $monsterHp): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO combat_instances (character_id, monster_id, monster_current_hp) 
            VALUES (:char_id, :monster_id, :hp)
        ");
        $stmt->execute([
            'char_id' => $characterId,
            'monster_id' => $monsterId,
            'hp' => $monsterHp
        ]);
    }

    public function updateMonsterHp(int $combatId, int $newHp, int $turnCount): void
    {
        $stmt = $this->db->prepare("UPDATE combat_instances SET monster_current_hp = :hp, turn_count = :turn WHERE id = :id");
        $stmt->execute(['hp' => $newHp, 'turn' => $turnCount, 'id' => $combatId]);
    }

    public function deleteCombatInstance(int $combatId): void
    {
        $stmt = $this->db->prepare("DELETE FROM combat_instances WHERE id = :id");
        $stmt->execute(['id' => $combatId]);
    }

    public function updateCharacterHpAndRewards(int $characterId, int $hp, int $xpGain = 0, int $goldGain = 0): void
    {
        $stmt = $this->db->prepare("UPDATE characters SET hp = :hp, xp = xp + :xp, gold = gold + :gold WHERE id = :id");
        $stmt->execute(['hp' => $hp, 'xp' => $xpGain, 'gold' => $goldGain, 'id' => $characterId]);
    }
}
