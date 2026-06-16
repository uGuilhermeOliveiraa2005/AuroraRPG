<?php

namespace Aurora\Repositories;

use Aurora\Core\Database;
use PDO;
use Exception;

class MarketRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listMarket(int $offset = 0, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT m.id as market_id, m.price, m.created_at, 
                   inv.quantity, i.name as item_name, i.type, i.rarity,
                   c.name as seller_name
            FROM market m
            JOIN inventory inv ON m.inventory_id = inv.id
            JOIN items i ON inv.item_id = i.id
            JOIN characters c ON m.seller_id = c.id
            ORDER BY m.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMarketItem(int $marketId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, inv.quantity, i.name as item_name
            FROM market m
            JOIN inventory inv ON m.inventory_id = inv.id
            JOIN items i ON inv.item_id = i.id
            WHERE m.id = :id
        ");
        $stmt->execute(['id' => $marketId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    public function sellItem(int $characterId, int $inventoryId, int $price): bool
    {
        try {
            // Verifica se o item pertence ao jogador e se não está equipado
            $stmt = $this->db->prepare("SELECT is_equipped FROM inventory WHERE id = :inv_id AND character_id = :char_id");
            $stmt->execute(['inv_id' => $inventoryId, 'char_id' => $characterId]);
            $isEquipped = $stmt->fetchColumn();

            if ($isEquipped === false) { // false se não encontrou o item
                return false;
            }
            if ($isEquipped === true || $isEquipped === 1) {
                return false; // Não pode vender item equipado
            }

            // Verifica se já não está no mercado
            $stmt = $this->db->prepare("SELECT id FROM market WHERE inventory_id = :inv_id");
            $stmt->execute(['inv_id' => $inventoryId]);
            if ($stmt->fetchColumn()) {
                return false;
            }

            $stmt = $this->db->prepare("INSERT INTO market (seller_id, inventory_id, price) VALUES (:seller, :inv, :price)");
            return $stmt->execute([
                'seller' => $characterId,
                'inv' => $inventoryId,
                'price' => $price
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function buyItem(int $buyerId, int $marketId): array
    {
        $this->db->beginTransaction();

        try {
            $item = $this->getMarketItem($marketId);
            if (!$item) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Item não encontrado no mercado.'];
            }

            if ($item['seller_id'] === $buyerId) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Você não pode comprar seu próprio item.'];
            }

            // Verifica gold do comprador
            $stmt = $this->db->prepare("SELECT gold FROM characters WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $buyerId]);
            $buyerGold = (int)$stmt->fetchColumn();

            if ($buyerGold < $item['price']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Ouro insuficiente.'];
            }

            // Deduz gold do comprador
            $stmt = $this->db->prepare("UPDATE characters SET gold = gold - :price WHERE id = :id");
            $stmt->execute(['price' => $item['price'], 'id' => $buyerId]);

            // Adiciona gold ao vendedor
            $stmt = $this->db->prepare("UPDATE characters SET gold = gold + :price WHERE id = :id");
            $stmt->execute(['price' => $item['price'], 'id' => $item['seller_id']]);

            // Transfere o inventário
            $stmt = $this->db->prepare("UPDATE inventory SET character_id = :buyer_id WHERE id = :inv_id");
            $stmt->execute(['buyer_id' => $buyerId, 'inv_id' => $item['inventory_id']]);

            // Remove do mercado
            $stmt = $this->db->prepare("DELETE FROM market WHERE id = :id");
            $stmt->execute(['id' => $marketId]);

            $this->db->commit();
            return ['success' => true, 'message' => "Você comprou {$item['quantity']}x {$item['item_name']} por {$item['price']} Ouro!"];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Erro interno na transação.'];
        }
    }
}
