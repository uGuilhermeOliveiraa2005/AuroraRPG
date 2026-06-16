<?php

namespace Aurora\Repositories;

use Aurora\Core\Database;

class QuestRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAvailableQuests(int $characterId, int $level): array
    {
        // Pega quests que o jogador ainda NÃO completou e atende o nível
        $stmt = $this->db->prepare("
            SELECT q.* FROM quests q
            WHERE q.min_level <= :lvl
            AND q.id NOT IN (
                SELECT quest_id FROM character_quests WHERE character_id = :char_id
            )
        ");
        $stmt->execute(['lvl' => $level, 'char_id' => $characterId]);
        return $stmt->fetchAll();
    }

    public function getActiveQuest(int $characterId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT cq.*, q.name, q.description, q.target_monster_id, q.target_kill_count, q.reward_xp, q.reward_gold 
            FROM character_quests cq
            JOIN quests q ON cq.quest_id = q.id
            WHERE cq.character_id = :char_id AND cq.status = 'active'
        ");
        $stmt->execute(['char_id' => $characterId]);
        $quest = $stmt->fetch();
        return $quest ?: null;
    }

    public function startQuest(int $characterId, int $questId): bool
    {
        // Verifica se já tem quest ativa
        if ($this->getActiveQuest($characterId)) {
            return false;
        }

        $stmt = $this->db->prepare("INSERT INTO character_quests (character_id, quest_id) VALUES (:char_id, :quest_id)");
        return $stmt->execute(['char_id' => $characterId, 'quest_id' => $questId]);
    }

    public function incrementKillCount(int $characterId, int $monsterId): void
    {
        $quest = $this->getActiveQuest($characterId);
        if (!$quest) return;

        if ($quest['target_monster_id'] == $monsterId) {
            $newProgress = $quest['progress_kills'] + 1;
            
            $stmt = $this->db->prepare("UPDATE character_quests SET progress_kills = :p WHERE character_id = :char_id AND quest_id = :q_id");
            $stmt->execute(['p' => $newProgress, 'char_id' => $characterId, 'q_id' => $quest['quest_id']]);
        }
    }

    public function completeQuest(int $characterId, int $questId): void
    {
        $stmt = $this->db->prepare("UPDATE character_quests SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE character_id = :char_id AND quest_id = :q_id");
        $stmt->execute(['char_id' => $characterId, 'q_id' => $questId]);
    }
}
