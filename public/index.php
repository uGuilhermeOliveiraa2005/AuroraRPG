<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aurora\Core\Config;
use Aurora\Core\TelegramBot;
use Aurora\Core\Router;

// Carregar variáveis de ambiente
Config::load(__DIR__ . '/../.env');

// Ler o body da requisição Webhook
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    // Resposta padrão caso seja acessado via navegador
    echo "Aurora MMORPG Webhook is active.";
    exit;
}

// Inicializar bot e roteador
$bot = new TelegramBot();
$router = new Router($bot);

try {
    $router->handleUpdate($update);
} catch (\Throwable $e) {
    error_log("Unhandled exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    // Fallback error message se possível
    if (isset($update['message']['chat']['id'])) {
        $bot->sendMessage($update['message']['chat']['id'], "⚠️ Ocorreu um erro interno no servidor.");
    }
}
