<?php

namespace Aurora\Repositories;

use Aurora\Core\Database;
use PDO;
use Exception;

class GuildRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getGuildByCharacter(int $characterId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT g.* 
            FROM guilds g
            JOIN guild_members gm ON g.id = gm.guild_id
            WHERE gm.character_id = :char_id
        ");
        $stmt->execute(['char_id' => $characterId]);
        $guild = $stmt->fetch(PDO::FETCH_ASSOC);
        return $guild ?: null;
    }

    public function getGuildByName(string $name): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM guilds WHERE LOWER(name) = LOWER(:name)");
        $stmt->execute(['name' => $name]);
        $guild = $stmt->fetch(PDO::FETCH_ASSOC);
        return $guild ?: null;
    }

    public function getGuildMembers(int $guildId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.name, c.level, gm.joined_at 
            FROM guild_members gm
            JOIN characters c ON gm.character_id = c.id
            WHERE gm.guild_id = :guild_id
            ORDER BY gm.joined_at ASC
        ");
        $stmt->execute(['guild_id' => $guildId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createGuild(string $name, int $leaderId): array
    {
        $this->db->beginTransaction();

        try {
            // Verifica se jogador já tem guilda
            if ($this->getGuildByCharacter($leaderId)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Você já pertence a uma guilda.'];
            }

            // Verifica se nome existe
            if ($this->getGuildByName($name)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Uma guilda com este nome já existe.'];
            }

            // Cobra ouro do líder (500)
            $stmt = $this->db->prepare("SELECT gold FROM characters WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $leaderId]);
            $gold = (int)$stmt->fetchColumn();

            if ($gold < 500) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Você precisa de 500 Ouro para criar uma guilda.'];
            }

            // Deduz ouro
            $stmt = $this->db->prepare("UPDATE characters SET gold = gold - 500 WHERE id = :id");
            $stmt->execute(['id' => $leaderId]);

            // Cria guilda
            $stmt = $this->db->prepare("INSERT INTO guilds (name, leader_id) VALUES (:name, :leader) RETURNING id");
            $stmt->execute(['name' => $name, 'leader' => $leaderId]);
            $guildId = $stmt->fetchColumn();

            // Adiciona líder aos membros
            $stmt = $this->db->prepare("INSERT INTO guild_members (guild_id, character_id) VALUES (:guild, :char)");
            $stmt->execute(['guild' => $guildId, 'char' => $leaderId]);

            $this->db->commit();
            return ['success' => true, 'message' => "A Guilda <b>{$name}</b> foi criada com sucesso!"];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Erro interno ao criar guilda.'];
        }
    }

    public function joinGuild(int $guildId, int $characterId): array
    {
        try {
            // Verifica se tem guilda
            if ($this->getGuildByCharacter($characterId)) {
                return ['success' => false, 'message' => 'Você já está em uma guilda. Saia primeiro.'];
            }

            // Verifica limite de membros
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM guild_members WHERE guild_id = :id");
            $stmt->execute(['id' => $guildId]);
            $count = (int)$stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT max_members FROM guilds WHERE id = :id");
            $stmt->execute(['id' => $guildId]);
            $max = (int)$stmt->fetchColumn();

            if ($count >= $max) {
                return ['success' => false, 'message' => 'A guilda está lotada.'];
            }

            $stmt = $this->db->prepare("INSERT INTO guild_members (guild_id, character_id) VALUES (:guild, :char)");
            $stmt->execute(['guild' => $guildId, 'char' => $characterId]);

            return ['success' => true, 'message' => 'Você entrou na guilda com sucesso!'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao entrar na guilda.'];
        }
    }

    public function leaveGuild(int $characterId): array
    {
        $guild = $this->getGuildByCharacter($characterId);
        if (!$guild) {
            return ['success' => false, 'message' => 'Você não está em nenhuma guilda.'];
        }

        if ($guild['leader_id'] === $characterId) {
            return ['success' => false, 'message' => 'O líder não pode sair da guilda. Transfira a liderança ou apague a guilda (função ainda não implementada).'];
        }

        $stmt = $this->db->prepare("DELETE FROM guild_members WHERE character_id = :char_id");
        $stmt->execute(['char_id' => $characterId]);

        return ['success' => true, 'message' => "Você saiu da guilda {$guild['name']}."];
    }
}
