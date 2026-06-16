<?php

namespace Aurora\Repositories;

use Aurora\Core\Database;
use PDO;

class InventoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getInventory(int $characterId): array
    {
        $stmt = $this->db->prepare("
            SELECT inv.*, i.name, i.description, i.type, i.rarity, i.sell_price, i.is_stackable,
                   i.bonus_str, i.bonus_agi, i.bonus_int, i.bonus_vit, i.bonus_hp, i.bonus_mana
            FROM inventory inv
            JOIN items i ON inv.item_id = i.id
            LEFT JOIN market m ON m.inventory_id = inv.id
            WHERE inv.character_id = :char_id AND m.id IS NULL
            ORDER BY inv.id ASC
        ");
        $stmt->execute(['char_id' => $characterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getItemInfo(int $inventoryId, int $characterId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT inv.*, i.name, i.description, i.type, i.rarity, i.is_stackable,
                   i.bonus_str, i.bonus_agi, i.bonus_int, i.bonus_vit, i.bonus_hp, i.bonus_mana
            FROM inventory inv
            JOIN items i ON inv.item_id = i.id
            LEFT JOIN market m ON m.inventory_id = inv.id
            WHERE inv.id = :inv_id AND inv.character_id = :char_id AND m.id IS NULL
        ");
        $stmt->execute(['inv_id' => $inventoryId, 'char_id' => $characterId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    public function addItem(int $characterId, int $itemId, int $quantity = 1): void
    {
        // Verifica se é stackable
        $stmt = $this->db->prepare("SELECT is_stackable FROM items WHERE id = :item_id");
        $stmt->execute(['item_id' => $itemId]);
        $isStackable = (bool)$stmt->fetchColumn();

        if ($isStackable) {
            // Tenta encontrar se já existe
            $stmt = $this->db->prepare("
                SELECT id FROM inventory 
                WHERE character_id = :char_id AND item_id = :item_id
            ");
            $stmt->execute(['char_id' => $characterId, 'item_id' => $itemId]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                // Atualiza quantidade
                $stmt = $this->db->prepare("UPDATE inventory SET quantity = quantity + :qty WHERE id = :id");
                $stmt->execute(['qty' => $quantity, 'id' => $existingId]);
                return;
            }
        }

        // Se não é stackable ou não existe, cria novo registro. Para não stackables, cria N registros.
        if (!$isStackable && $quantity > 1) {
            for ($i = 0; $i < $quantity; $i++) {
                $stmt = $this->db->prepare("INSERT INTO inventory (character_id, item_id, quantity) VALUES (:char_id, :item_id, 1)");
                $stmt->execute(['char_id' => $characterId, 'item_id' => $itemId]);
            }
        } else {
            $stmt = $this->db->prepare("INSERT INTO inventory (character_id, item_id, quantity) VALUES (:char_id, :item_id, :qty)");
            $stmt->execute(['char_id' => $characterId, 'item_id' => $itemId, 'qty' => $quantity]);
        }
    }

    public function removeItem(int $inventoryId, int $quantity = 1): void
    {
        $stmt = $this->db->prepare("SELECT quantity FROM inventory WHERE id = :id");
        $stmt->execute(['id' => $inventoryId]);
        $currentQty = (int)$stmt->fetchColumn();

        if ($currentQty <= $quantity) {
            $stmt = $this->db->prepare("DELETE FROM inventory WHERE id = :id");
            $stmt->execute(['id' => $inventoryId]);
        } else {
            $stmt = $this->db->prepare("UPDATE inventory SET quantity = quantity - :qty WHERE id = :id");
            $stmt->execute(['qty' => $quantity, 'id' => $inventoryId]);
        }
    }

    public function toggleEquip(int $inventoryId, int $characterId, string $itemType, bool $equip): void
    {
        if ($equip) {
            // Desequipar itens do mesmo tipo primeiro
            $stmt = $this->db->prepare("
                UPDATE inventory inv
                SET is_equipped = false
                FROM items i
                WHERE inv.item_id = i.id 
                  AND inv.character_id = :char_id 
                  AND i.type = :type
                  AND inv.is_equipped = true
            ");
            $stmt->execute(['char_id' => $characterId, 'type' => $itemType]);

            // Equipar o novo item
            $stmt = $this->db->prepare("UPDATE inventory SET is_equipped = true WHERE id = :inv_id AND character_id = :char_id");
            $stmt->execute(['inv_id' => $inventoryId, 'char_id' => $characterId]);
        } else {
            // Apenas desequipar
            $stmt = $this->db->prepare("UPDATE inventory SET is_equipped = false WHERE id = :inv_id AND character_id = :char_id");
            $stmt->execute(['inv_id' => $inventoryId, 'char_id' => $characterId]);
        }
    }

    public function getMonsterDrops(int $monsterId): array
    {
        $stmt = $this->db->prepare("
            SELECT md.item_id, md.drop_chance, md.min_quantity, md.max_quantity, i.name 
            FROM monster_drops md
            JOIN items i ON md.item_id = i.id
            WHERE md.monster_id = :monster_id
        ");
        $stmt->execute(['monster_id' => $monsterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
