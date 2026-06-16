<?php

namespace Aurora\Controllers;

use Aurora\Core\TelegramBot;
use Aurora\Repositories\UserRepository;
use Aurora\Repositories\CharacterRepository;
use Aurora\Repositories\MarketRepository;
use Aurora\Repositories\InventoryRepository;

class MarketController
{
    private TelegramBot $bot;
    private UserRepository $userRepo;
    private CharacterRepository $charRepo;
    private MarketRepository $marketRepo;
    private InventoryRepository $invRepo;

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
        $this->userRepo = new UserRepository();
        $this->charRepo = new CharacterRepository();
        $this->marketRepo = new MarketRepository();
        $this->invRepo = new InventoryRepository();
    }

    public function showMarket(int|string $chatId, int $userId, int $page = 0, ?int $messageId = null): void
    {
        $user = $this->userRepo->findById($userId);
        if (!$user || $user['state'] === 'combat') {
            $msg = "Você não pode acessar o mercado agora.";
            if ($messageId) $this->bot->editMessageText($chatId, $messageId, $msg);
            else $this->bot->sendMessage($chatId, $msg);
            return;
        }

        $items = $this->marketRepo->listMarket($page * 10, 10);
        $text = "🏪 <b>Mercado Global</b>\n\n";

        if (empty($items)) {
            $text .= "O mercado está vazio no momento.\n";
            $text .= "Use <code>/vender [id_inventario] [preco]</code> para vender itens.";
            $keyboard = null;
        } else {
            $keyboard = ['inline_keyboard' => []];
            foreach ($items as $item) {
                $text .= "• <b>{$item['quantity']}x {$item['item_name']}</b> por 🪙 {$item['price']}\n";
                $text .= "  <i>Vendido por {$item['seller_name']}</i>\n";

                $keyboard['inline_keyboard'][] = [
                    ['text' => "💰 Comprar {$item['item_name']} (🪙 {$item['price']})", 'callback_data' => "market_buy:{$item['market_id']}"]
                ];
            }
        }

        if ($messageId) {
            if ($keyboard) {
                $this->bot->editMessageText($chatId, $messageId, $text, $keyboard);
            } else {
                $this->bot->editMessageText($chatId, $messageId, $text);
            }
        } else {
            if ($keyboard) {
                $this->bot->sendMessage($chatId, $text, $keyboard);
            } else {
                $this->bot->sendMessage($chatId, $text);
            }
        }
    }

    public function sellCommand(int|string $chatId, int $userId, array $args): void
    {
        $user = $this->userRepo->findById($userId);
        if (!$user || $user['state'] === 'combat') {
            $this->bot->sendMessage($chatId, "Você não pode vender itens agora.");
            return;
        }

        if (count($args) < 3) {
            $this->bot->sendMessage($chatId, "Uso incorreto. Digite: <code>/vender [id_do_item_no_inventario] [preco]</code>\n\nDica: Digite /inventario para ver os IDs se implementados (para MVP use o ID interno do banco, ou faça melhorias no inventário para mostrar IDs).");
            return;
        }

        $inventoryId = (int)$args[1];
        $price = (int)$args[2];

        if ($price <= 0) {
            $this->bot->sendMessage($chatId, "O preço deve ser maior que zero.");
            return;
        }

        $character = $this->charRepo->findByUserId($userId);
        $success = $this->marketRepo->sellItem($character['id'], $inventoryId, $price);

        if ($success) {
            $this->bot->sendMessage($chatId, "✅ Item colocado à venda no mercado por 🪙 {$price} Ouro!");
        } else {
            $this->bot->sendMessage($chatId, "❌ Falha ao vender. Verifique se o item pertence a você, se existe, se não está equipado ou se já não está à venda.");
        }
    }

    public function buyItem(int|string $chatId, int $userId, int $messageId, string $callbackId, int $marketId): void
    {
        $character = $this->charRepo->findByUserId($userId);
        if (!$character) {
            $this->bot->answerCallbackQuery($callbackId, "Personagem não encontrado.", true);
            return;
        }

        $result = $this->marketRepo->buyItem($character['id'], $marketId);

        if ($result['success']) {
            $this->bot->answerCallbackQuery($callbackId, $result['message']);
            $this->showMarket($chatId, $userId, 0, $messageId); // Recarrega o mercado
        } else {
            $this->bot->answerCallbackQuery($callbackId, $result['message'], true);
        }
    }
}
