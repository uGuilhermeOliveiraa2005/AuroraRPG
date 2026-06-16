<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aurora\Core\Config;
use Aurora\Core\TelegramBot;
use Aurora\Core\Router;

// No Vercel, as variáveis de ambiente devem ser configuradas no painel do Vercel.
// Carregar o .env localmente para testes:
if (file_exists(__DIR__ . '/../.env')) {
    Config::load(__DIR__ . '/../.env');
}

// O Telegram envia os dados via POST no corpo da requisição (payload JSON)
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    // Se não for um payload válido do Telegram, apenas finalizamos com sucesso
    http_response_code(200);
    echo "Aurora MMORPG Webhook is active!";
    exit;
}

$bot = new TelegramBot();
$router = new Router($bot);

try {
    $router->handleUpdate($update);
} catch (\Throwable $e) {
    // Em produção (Webhooks), evite cuspir o erro na tela para não travar a resposta HTTP
    error_log("Erro no bot: " . $e->getMessage());
}

// Sempre devemos retornar 200 OK rapidamente para o Telegram não reenviar a mesma mensagem
http_response_code(200);
echo "OK";
