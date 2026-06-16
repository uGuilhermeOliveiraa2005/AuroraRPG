<?php

namespace Aurora\Repositories;

use Aurora\Core\Database;
use PDO;

class CharacterRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, cl.name as class_name,
                   (c.str + COALESCE(SUM(i.bonus_str), 0)) as total_str,
                   (c.agi + COALESCE(SUM(i.bonus_agi), 0)) as total_agi,
                   (c.\"int\" + COALESCE(SUM(i.bonus_int), 0)) as total_int,
                   (c.vit + COALESCE(SUM(i.bonus_vit), 0)) as total_vit,
                   (c.max_hp + COALESCE(SUM(i.bonus_hp), 0)) as total_max_hp,
                   (c.max_mana + COALESCE(SUM(i.bonus_mana), 0)) as total_max_mana
            FROM characters c
            JOIN classes cl ON c.class_id = cl.id
            LEFT JOIN inventory inv ON inv.character_id = c.id AND inv.is_equipped = true
            LEFT JOIN items i ON inv.item_id = i.id
            WHERE c.user_id = :user_id
            GROUP BY c.id, cl.name
        ");
        $stmt->execute(['user_id' => $userId]);
        $char = $stmt->fetch();
        return $char ?: null;
    }

    public function getClasses(): array
    {
        $stmt = $this->db->query("SELECT * FROM classes ORDER BY id ASC");
        return $stmt->fetchAll();
    }
    
    public function getClassById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM classes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $class = $stmt->fetch();
        return $class ?: null;
    }

    public function addStatPoint(int $characterId, string $stat): bool
    {
        $validStats = ['str', 'agi', 'int', 'vit'];
        if (!in_array($stat, $validStats)) {
            return false;
        }

        // Aspas duplas necessárias para "int" no PostgreSQL
        $column = $stat === 'int' ? '"int"' : $stat;

        $stmt = $this->db->prepare("
            UPDATE characters 
            SET {$column} = {$column} + 1, stat_points = stat_points - 1 
            WHERE id = :id AND stat_points > 0
        ");
        
        $stmt->execute(['id' => $characterId]);
        return $stmt->rowCount() > 0;
    }

    public function create(int $userId, string $name, int $classId): bool
    {
        $class = $this->getClassById($classId);
        if (!$class) return false;

        $stmt = $this->db->prepare("
            INSERT INTO characters 
            (user_id, name, class_id, hp, max_hp, mana, max_mana, str, agi, \"int\", vit)
            VALUES 
            (:user_id, :name, :class_id, :hp, :max_hp, :mana, :max_mana, :str, :agi, :int, :vit)
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'class_id' => $classId,
            'hp' => $class['base_hp'],
            'max_hp' => $class['base_hp'],
            'mana' => $class['base_mana'],
            'max_mana' => $class['base_mana'],
            'str' => $class['base_str'],
            'agi' => $class['base_agi'],
            'int' => $class['base_int'],
            'vit' => $class['base_vit']
        ]);
    }
}
