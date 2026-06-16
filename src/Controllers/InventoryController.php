<?php

namespace Aurora\Controllers;

use Aurora\Core\TelegramBot;
use Aurora\Repositories\UserRepository;
use Aurora\Repositories\CharacterRepository;
use Aurora\Repositories\InventoryRepository;

class InventoryController
{
    private TelegramBot $bot;
    private UserRepository $userRepo;
    private CharacterRepository $charRepo;
    private InventoryRepository $invRepo;

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
        $this->userRepo = new UserRepository();
        $this->charRepo = new CharacterRepository();
        $this->invRepo = new InventoryRepository();
    }

    public function showInventory(int|string $chatId, int $userId, ?int $messageId = null): void
    {
        $user = $this->userRepo->findById($userId);
        if (!$user || $user['state'] === 'combat') {
            $msg = "⚔️ Você não pode acessar o inventário enquanto está em combate!";
            if ($messageId) $this->bot->editMessageText($chatId, $messageId, $msg);
            else $this->bot->sendMessage($chatId, $msg);
            return;
        }

        $character = $this->charRepo->findByUserId($userId);
        if (!$character) {
            $msg = "Você precisa de um personagem.";
            if ($messageId) $this->bot->editMessageText($chatId, $messageId, $msg);
            else $this->bot->sendMessage($chatId, $msg);
            return;
        }

        $items = $this->invRepo->getInventory($character['id']);

        if (empty($items)) {
            $msg = "🎒 <b>Sua mochila está vazia!</b>\nVá explorar o mundo para encontrar itens.";
            if ($messageId) $this->bot->editMessageText($chatId, $messageId, $msg);
            else $this->bot->sendMessage($chatId, $msg);
            return;
        }

        $text = "🎒 <b>Inventário de {$character['name']}</b>\n\n";
        $keyboard = ['inline_keyboard' => []];

        $typeEmojis = [
            'weapon' => '🗡️',
            'helmet' => '🪖',
            'armor'  => '🛡️',
            'boots'  => '👢',
            'amulet' => '📿',
            'potion' => '🧪',
            'material' => '📦'
        ];

        foreach ($items as $item) {
            $icon = $typeEmojis[$item['type']] ?? '🎒';
            $equipStr = $item['is_equipped'] ? " <b>[Equipado]</b>" : "";
            $text .= "{$icon} <b>{$item['quantity']}x {$item['name']}</b>{$equipStr}\n";

            if ($item['type'] === 'potion') {
                $keyboard['inline_keyboard'][] = [
                    ['text' => "🧪 Usar {$item['name']}", 'callback_data' => "inv_use:{$item['id']}"]
                ];
            } elseif (in_array($item['type'], ['weapon', 'helmet', 'armor', 'boots', 'amulet'])) {
                if ($item['is_equipped']) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => "❌ Remover {$item['name']}", 'callback_data' => "inv_unequip:{$item['id']}"]
                    ];
                } else {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => "👕 Equipar {$item['name']}", 'callback_data' => "inv_equip:{$item['id']}"]
                    ];
                }
            }
        }

        if ($messageId) {
            $this->bot->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->bot->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function useItem(int|string $chatId, int $userId, int $messageId, string $callbackId, int $inventoryId): void
    {
        $character = $this->charRepo->findByUserId($userId);
        $itemInfo = $this->invRepo->getItemInfo($inventoryId, $character['id']);

        if (!$itemInfo || $itemInfo['quantity'] <= 0) {
            $this->bot->answerCallbackQuery($callbackId, "Item não encontrado.", true);
            return;
        }

        if ($itemInfo['type'] === 'potion') {
            $heal = $itemInfo['bonus_hp'];
            $newHp = min($character['total_max_hp'], $character['hp'] + $heal);
            
            // Consome item
            $this->invRepo->removeItem($inventoryId, 1);
            
            // Atualiza HP
            $db = \Aurora\Core\Database::getInstance();
            $stmt = $db->prepare("UPDATE characters SET hp = :hp WHERE id = :id");
            $stmt->execute(['hp' => $newHp, 'id' => $character['id']]);

            $this->bot->answerCallbackQuery($callbackId, "Você recuperou {$heal} HP!");
            $this->showInventory($chatId, $userId, $messageId);
        } else {
            $this->bot->answerCallbackQuery($callbackId, "Você não pode usar isso.", true);
        }
    }

    public function equipItem(int|string $chatId, int $userId, int $messageId, string $callbackId, int $inventoryId): void
    {
        $character = $this->charRepo->findByUserId($userId);
        $itemInfo = $this->invRepo->getItemInfo($inventoryId, $character['id']);

        if (!$itemInfo) {
            $this->bot->answerCallbackQuery($callbackId, "Item não encontrado.", true);
            return;
        }

        if (!in_array($itemInfo['type'], ['weapon', 'helmet', 'armor', 'boots', 'amulet'])) {
            $this->bot->answerCallbackQuery($callbackId, "Este item não é equipável.", true);
            return;
        }

        $this->invRepo->toggleEquip($inventoryId, $character['id'], $itemInfo['type'], true);
        $this->bot->answerCallbackQuery($callbackId, "{$itemInfo['name']} equipado!");
        $this->showInventory($chatId, $userId, $messageId);
    }

    public function unequipItem(int|string $chatId, int $userId, int $messageId, string $callbackId, int $inventoryId): void
    {
        $character = $this->charRepo->findByUserId($userId);
        $itemInfo = $this->invRepo->getItemInfo($inventoryId, $character['id']);

        if (!$itemInfo) {
            $this->bot->answerCallbackQuery($callbackId, "Item não encontrado.", true);
            return;
        }

        $this->invRepo->toggleEquip($inventoryId, $character['id'], $itemInfo['type'], false);
        $this->bot->answerCallbackQuery($callbackId, "{$itemInfo['name']} desequipado!");
        $this->showInventory($chatId, $userId, $messageId);
    }
}
