# Aurora MMORPG - Telegram Bot

Este é o repositório do **Aurora MMORPG**, um jogo multiplayer online persistente desenvolvido para rodar via Telegram Bot. 
O projeto foi desenhado para ser altamente escalável, utilizando **PHP 8.3** e **PostgreSQL (Supabase)** com arquitetura **MVC**.

## 🚀 Arquitetura e Tecnologias
- **Linguagem:** PHP 8.3 (com `ext-pdo` e `ext-pdo_pgsql`).
- **Banco de Dados:** PostgreSQL hospedado no Supabase (utilizamos a conexão Pooler IPv4 com o driver nativo PDO com `ATTR_EMULATE_PREPARES` desativado contra SQL Injections).
- **Gerenciamento de Pacotes:** Composer (`vendor/autoload.php`).
- **Arquitetura:** MVC (Controllers, Services, Repositories e Core Router).
- **Interface:** Telegram Bot API (via long-polling local ou Webhooks em produção).

## 🛠️ Como Executar Localmente
O projeto está configurado para não exigir *Webhooks* (que precisam de HTTPS) durante o desenvolvimento local.
1. Instale as dependências executando `composer install`.
2. Renomeie o arquivo `.env.example` para `.env` e preencha o token do bot (`TELEGRAM_BOT_TOKEN`) e as credenciais do Supabase.
3. Inicie o Bot abrindo um terminal nesta pasta e rodando:
   ```bash
   php poll.php
   ```
   *O script `poll.php` utiliza a API `getUpdates` para puxar novas mensagens instantaneamente e enviar para o `Router.php`.*

## 📌 Progresso Atual (O que já foi feito)

- **Fase 1 e 2 (Arquitetura e DB):** O projeto foi criado. Modelagem SQL concluída (12 tabelas relacionais contendo `users`, `characters`, `monsters`, `items`, etc.). As `migrations` foram populadas e sincronizadas na nuvem usando Supabase CLI.
- **Fase 3 (Core API):** O motor de comunicação com o Telegram foi construído em `TelegramBot.php`.
- **Fase 4 (Personagens):** Fluxo de `/registrar` com seleção interativa (Botões Inline) de Classes (Mago, Arqueiro, Guerreiro). Geração do status global via comando `/perfil`.
- **Fase 5 (Combate):** O sistema de batalha por turnos (`/explorar`) foi integrado com sucesso! Cálculos de Ataque (Força), Crítico e Esquiva (Agilidade) salvando sessão de forma dinâmica no banco. Level up automático.

## 🔜 Próximos Passos (Para onde ir agora)

O bot está no meio do desenvolvimento de suas mecânicas principais.
- **Fase 6 - Inventário e Itens (Atual prioridade):** É necessário criar os comandos `/inventario`, gerenciar os *drops* de recompensa das vitórias em combate, além do fluxo de `/equipar` e consumo de itens (`/usar`).
- **Fase 7 - Mercado e Guildas:** Sistema transacional de troca de ouro e formação de clãs.
- **Fase 8 - Global Boss e Ranking:** Adicionar Crons globais e painéis de liderança.

> **Nota para a IA:** Ao retomar este projeto, você pode começar diretamente executando a Fase 6 listada acima, criando os repositórios/serviços de Inventário e ajustando o Motor de Combate para depositar Itens dropados pelo monstro no Inventário do jogador.
