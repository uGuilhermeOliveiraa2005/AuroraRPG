<?php

namespace Aurora\Controllers;

use Aurora\Core\TelegramBot;
use Aurora\Repositories\UserRepository;
use Aurora\Repositories\CharacterRepository;
use Aurora\Repositories\QuestRepository;

class QuestController
{
    private TelegramBot $bot;
    private UserRepository $userRepo;
    private CharacterRepository $charRepo;
    private QuestRepository $questRepo;

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
        $this->userRepo = new UserRepository();
        $this->charRepo = new CharacterRepository();
        $this->questRepo = new QuestRepository();
    }

    public function showQuests(int|string $chatId, int $userId, ?int $messageId = null): void
    {
        $character = $this->charRepo->findByUserId($userId);
        $activeQuest = $this->questRepo->getActiveQuest($character['id']);

        if ($activeQuest) {
            // Quest Ativa Encontrada
            $isComplete = $activeQuest['progress_kills'] >= $activeQuest['target_kill_count'];

            $text = "📜 <b>Missão Ativa: {$activeQuest['name']}</b>\n\n";
            $text .= "<i>\"{$activeQuest['description']}\"</i>\n\n";
            $text .= "Progresso: {$activeQuest['progress_kills']}/{$activeQuest['target_kill_count']} monstros abatidos.\n\n";
            
            $keyboard = ['inline_keyboard' => []];

            if ($isComplete) {
                $text .= "✨ <b>Objetivos concluídos!</b> Entregue a missão para receber sua recompensa.";
                $keyboard['inline_keyboard'][] = [
                    ['text' => '✅ Entregar Missão', 'callback_data' => "quest_complete:{$activeQuest['quest_id']}"]
                ];
            } else {
                $text .= "⚔️ <b>Continue caçando usando o comando /explorar.</b>";
                $keyboard['inline_keyboard'][] = [
                    ['text' => '🔙 Voltar para Cidade', 'callback_data' => 'city_menu']
                ];
            }

            if ($messageId) {
                $this->bot->editMessageText($chatId, $messageId, $text, $keyboard);
            } else {
                $this->bot->sendMessage($chatId, $text, $keyboard);
            }
            return;
        }

        // Se não tiver quest ativa, mostra disponíveis
        $availableQuests = $this->questRepo->getAvailableQuests($character['id'], $character['level']);

        if (empty($availableQuests)) {
            $text = "🧙‍♂️ <b>Ancião Kaelen:</b>\n\n'Você tem se saído muito bem, meu jovem! Mas no momento não tenho mais nenhuma tarefa para alguém do seu nível. Volte mais tarde.'";
            $keyboard = ['inline_keyboard' => [
                [['text' => '🔙 Voltar', 'callback_data' => 'city_menu']]
            ]];
        } else {
            $text = "🧙‍♂️ <b>Ancião Kaelen:</b>\n\n'Nossa vila está em perigo constante. Preciso de heróis bravos. Você tem o que é preciso?'\n\n<b>Missões Disponíveis:</b>";
            $keyboard = ['inline_keyboard' => []];

            foreach ($availableQuests as $q) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => "📜 {$q['name']}", 'callback_data' => "quest_accept:{$q['id']}"]
                ];
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => '🔙 Voltar', 'callback_data' => 'city_menu']
            ];
        }

        if ($messageId) {
            $this->bot->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->bot->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function acceptQuest(int|string $chatId, int $userId, int $messageId, string $callbackId, int $questId): void
    {
        $character = $this->charRepo->findByUserId($userId);
        
        $success = $this->questRepo->startQuest($character['id'], $questId);

        if ($success) {
            $this->bot->answerCallbackQuery($callbackId, "Missão aceita!", true);
            $this->showQuests($chatId, $userId, $messageId);
        } else {
            $this->bot->answerCallbackQuery($callbackId, "Você já tem uma missão ativa ou não pode aceitar essa.", true);
        }
    }

    public function turnInQuest(int|string $chatId, int $userId, int $messageId, string $callbackId, int $questId): void
    {
        $character = $this->charRepo->findByUserId($userId);
        $activeQuest = $this->questRepo->getActiveQuest($character['id']);

        if (!$activeQuest || $activeQuest['quest_id'] != $questId) {
            $this->bot->answerCallbackQuery($callbackId, "Missão inválida.", true);
            return;
        }

        if ($activeQuest['progress_kills'] < $activeQuest['target_kill_count']) {
            $this->bot->answerCallbackQuery($callbackId, "Missão incompleta.", true);
            return;
        }

        // Completar a quest
        $this->questRepo->completeQuest($character['id'], $questId);

        // Dar recompensas (XP e Gold)
        $db = \Aurora\Core\Database::getInstance();
        $xp = $activeQuest['reward_xp'];
        $gold = $activeQuest['reward_gold'];

        $stmt = $db->prepare("UPDATE characters SET xp = xp + :xp, gold = gold + :gold WHERE id = :id");
        $stmt->execute(['xp' => $xp, 'gold' => $gold, 'id' => $character['id']]);

        // Checar level up
        $combatSvc = new \Aurora\Services\CombatService();
        $combatSvc->checkLevelUp($character['id'], $chatId);

        $this->bot->answerCallbackQuery($callbackId, "Missão entregue com sucesso!", true);
        
        $text = "🎉 <b>Missão Concluída: {$activeQuest['name']}</b>\n\n";
        $text .= "Você recebeu:\n";
        $text .= "✨ {$xp} XP\n";
        $text .= "🪙 {$gold} Ouro\n\n";
        $text .= "<i>O Ancião Kaelen agradece seus serviços em prol da Vila de Alvorada.</i>";

        $keyboard = ['inline_keyboard' => [
            [['text' => '🔙 Voltar para Cidade', 'callback_data' => 'city_menu']]
        ]];

        $this->bot->editMessageText($chatId, $messageId, $text, $keyboard);
    }
}
