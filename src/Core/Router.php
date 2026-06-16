<?php

namespace Aurora\Core;

use Aurora\Controllers\PlayerController;
use Aurora\Controllers\CombatController;
use Aurora\Controllers\InventoryController;
use Aurora\Controllers\MarketController;
use Aurora\Controllers\GuildController;

class Router
{
    private TelegramBot $bot;

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['message']['text'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'];
        $text = trim($message['text']);
        $userId = $message['from']['id'];
        $username = $message['from']['username'] ?? 'SemUsuario';
        $firstName = $message['from']['first_name'] ?? 'Jogador';

        // Opcional: extrair comando se houver parâmetros (ex: /usar 10)
        $parts = explode(' ', $text);
        $command = strtolower($parts[0]);

        // Basic instantiations (could use a DI container in a larger project)
        $playerController = new PlayerController($this->bot);
        $combatController = new CombatController($this->bot);
        $inventoryController = new InventoryController($this->bot);
        $marketController = new MarketController($this->bot);
        $guildController = new GuildController($this->bot);

        switch ($command) {
            case '/start':
            case '/ajuda':
                $this->bot->sendMessage($chatId, "Bem-vindo ao <b>Aurora MMORPG</b>! ⚔️\n\nComandos básicos:\n/registrar - Crie seu personagem\n/perfil - Veja seus status\n/explorar - Vá caçar monstros\n/inventario - Veja seus itens");
                break;
            case '/inventario':
            case '/items':
                $inventoryController->showInventory($chatId, $userId);
                break;
            case '/registrar':
                $playerController->register($chatId, $userId, $username, $firstName);
                break;
            case '/perfil':
            case '/status':
            case '/atributos':
                $playerController->profile($chatId, $userId);
                break;
            case '/explorar':
                $combatController->explore($chatId, $userId);
                break;
            case '/mercado':
                $marketController->showMarket($chatId, $userId);
                break;
            case '/vender':
                $marketController->sellCommand($chatId, $userId, $parts);
                break;
            case '/guilda':
                $guildController->handleCommand($chatId, $userId, $parts);
                break;
            default:
                if (str_starts_with($command, '/')) {
                    $this->bot->sendMessage($chatId, "Comando desconhecido. Digite /ajuda para ver os comandos disponíveis.");
                }
                break;
        }
    }

    private function handleCallback(array $callbackQuery): void
    {
        $callbackId = $callbackQuery['id'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $userId = $callbackQuery['from']['id'];
        $data = $callbackQuery['data'];
        $messageId = $callbackQuery['message']['message_id'];

        $parts = explode(':', $data);
        $action = $parts[0];

        $playerController = new PlayerController($this->bot);
        $combatController = new CombatController($this->bot);
        $inventoryController = new InventoryController($this->bot);
        $marketController = new MarketController($this->bot);

        try {
            switch ($action) {
                case 'class_select':
                    $classId = (int)($parts[1] ?? 0);
                    $playerController->processRegistration($chatId, $userId, $classId, $messageId);
                    $this->bot->answerCallbackQuery($callbackId, "Classe selecionada!");
                    break;
                case 'combat_attack':
                    $combatController->attack($chatId, $userId, $messageId, $callbackId);
                    break;
                case 'combat_flee':
                    $combatController->flee($chatId, $userId, $messageId, $callbackId);
                    break;
                case 'inv_equip':
                    $invId = (int)($parts[1] ?? 0);
                    $inventoryController->equipItem($chatId, $userId, $messageId, $callbackId, $invId);
                    break;
                case 'inv_unequip':
                    $invId = (int)($parts[1] ?? 0);
                    $inventoryController->unequipItem($chatId, $userId, $messageId, $callbackId, $invId);
                    break;
                case 'inv_use':
                    $invId = (int)($parts[1] ?? 0);
                    $inventoryController->useItem($chatId, $userId, $messageId, $callbackId, $invId);
                    break;
                case 'market_buy':
                    $marketId = (int)($parts[1] ?? 0);
                    $marketController->buyItem($chatId, $userId, $messageId, $callbackId, $marketId);
                    break;
                case 'stat_add':
                    $stat = $parts[1] ?? '';
                    $playerController->addStat($chatId, $userId, $messageId, $callbackId, $stat);
                    break;
                default:
                    $this->bot->answerCallbackQuery($callbackId, "Ação desconhecida.", true);
                    break;
            }
        } catch (\Exception $e) {
            $this->bot->answerCallbackQuery($callbackId, "Erro ao processar ação.", true);
        }
    }
}
