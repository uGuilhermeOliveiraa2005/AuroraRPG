<?php

namespace Aurora\Controllers;

use Aurora\Core\TelegramBot;
use Aurora\Repositories\UserRepository;
use Aurora\Repositories\CharacterRepository;
use Aurora\Repositories\GuildRepository;

class GuildController
{
    private TelegramBot $bot;
    private UserRepository $userRepo;
    private CharacterRepository $charRepo;
    private GuildRepository $guildRepo;

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
        $this->userRepo = new UserRepository();
        $this->charRepo = new CharacterRepository();
        $this->guildRepo = new GuildRepository();
    }

    public function handleCommand(int|string $chatId, int $userId, array $args): void
    {
        $user = $this->userRepo->findById($userId);
        if (!$user || $user['state'] === 'combat') {
            $this->bot->sendMessage($chatId, "Você não pode acessar a guilda agora.");
            return;
        }

        $character = $this->charRepo->findByUserId($userId);
        if (!$character) {
            $this->bot->sendMessage($chatId, "Você precisa de um personagem.");
            return;
        }

        $action = strtolower($args[1] ?? 'info');

        switch ($action) {
            case 'criar':
                $this->createGuild($chatId, $character, $args);
                break;
            case 'entrar':
                $this->joinGuild($chatId, $character, $args);
                break;
            case 'sair':
                $this->leaveGuild($chatId, $character);
                break;
            case 'info':
            default:
                $this->showGuildInfo($chatId, $character);
                break;
        }
    }

    private function showGuildInfo(int|string $chatId, array $character): void
    {
        $guild = $this->guildRepo->getGuildByCharacter($character['id']);

        if (!$guild) {
            $text = "🛡️ <b>Sistema de Guildas</b>\n\n";
            $text .= "Você não pertence a nenhuma guilda no momento.\n\n";
            $text .= "Comandos:\n";
            $text .= "<code>/guilda criar [Nome]</code> - Custa 500 Ouro.\n";
            $text .= "<code>/guilda entrar [Nome]</code> - Entra em uma guilda existente.";
            $this->bot->sendMessage($chatId, $text);
            return;
        }

        $members = $this->guildRepo->getGuildMembers($guild['id']);
        
        $text = "🛡️ <b>Guilda {$guild['name']}</b> (Lvl {$guild['level']})\n\n";
        $text .= "Membros (" . count($members) . "/{$guild['max_members']}):\n";
        
        foreach ($members as $member) {
            $text .= "• {$member['name']} (Lvl {$member['level']})\n";
        }

        $text .= "\nPara sair, digite <code>/guilda sair</code>.";
        $this->bot->sendMessage($chatId, $text);
    }

    private function createGuild(int|string $chatId, array $character, array $args): void
    {
        if (count($args) < 3) {
            $this->bot->sendMessage($chatId, "Forneça o nome da guilda. Ex: <code>/guilda criar Templarios</code>");
            return;
        }

        $guildName = implode(' ', array_slice($args, 2));
        $result = $this->guildRepo->createGuild($guildName, $character['id']);

        $this->bot->sendMessage($chatId, $result['message']);
    }

    private function joinGuild(int|string $chatId, array $character, array $args): void
    {
        if (count($args) < 3) {
            $this->bot->sendMessage($chatId, "Forneça o nome da guilda. Ex: <code>/guilda entrar Templarios</code>");
            return;
        }

        $guildName = implode(' ', array_slice($args, 2));
        $guild = $this->guildRepo->getGuildByName($guildName);

        if (!$guild) {
            $this->bot->sendMessage($chatId, "A guilda <b>{$guildName}</b> não foi encontrada.");
            return;
        }

        $result = $this->guildRepo->joinGuild($guild['id'], $character['id']);
        $this->bot->sendMessage($chatId, $result['message']);
    }

    private function leaveGuild(int|string $chatId, array $character): void
    {
        $result = $this->guildRepo->leaveGuild($character['id']);
        $this->bot->sendMessage($chatId, $result['message']);
    }
}
