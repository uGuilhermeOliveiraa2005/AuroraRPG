-- Criação das tabelas do Aurora MMORPG
-- Database: PostgreSQL

CREATE TABLE IF NOT EXISTS users (
    telegram_id BIGINT PRIMARY KEY,
    username VARCHAR(255),
    first_name VARCHAR(255),
    is_vip BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    state VARCHAR(50) DEFAULT 'idle' -- idle, combat, market, etc.
);

CREATE TABLE IF NOT EXISTS classes (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    base_hp INT NOT NULL,
    base_mana INT NOT NULL,
    base_str INT NOT NULL,
    base_agi INT NOT NULL,
    base_int INT NOT NULL,
    base_vit INT NOT NULL
);

CREATE TABLE IF NOT EXISTS characters (
    id SERIAL PRIMARY KEY,
    user_id BIGINT UNIQUE REFERENCES users(telegram_id) ON DELETE CASCADE,
    name VARCHAR(50) NOT NULL,
    class_id INT REFERENCES classes(id),
    level INT DEFAULT 1,
    xp BIGINT DEFAULT 0,
    gold BIGINT DEFAULT 0,
    hp INT NOT NULL,
    max_hp INT NOT NULL,
    mana INT NOT NULL,
    max_mana INT NOT NULL,
    stat_points INT DEFAULT 0,
    str INT NOT NULL,
    agi INT NOT NULL,
    "int" INT NOT NULL, -- quoted because int is a reserved word
    vit INT NOT NULL,
    inventory_slots INT DEFAULT 30
);

CREATE TABLE IF NOT EXISTS items (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type VARCHAR(50) NOT NULL, -- weapon, helmet, armor, boots, amulet, potion, material
    rarity VARCHAR(20) NOT NULL, -- common, uncommon, rare, epic, legendary
    buy_price INT DEFAULT 0,
    sell_price INT DEFAULT 0,
    bonus_str INT DEFAULT 0,
    bonus_agi INT DEFAULT 0,
    bonus_int INT DEFAULT 0,
    bonus_vit INT DEFAULT 0,
    bonus_hp INT DEFAULT 0,
    bonus_mana INT DEFAULT 0,
    is_stackable BOOLEAN DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS inventory (
    id SERIAL PRIMARY KEY,
    character_id INT REFERENCES characters(id) ON DELETE CASCADE,
    item_id INT REFERENCES items(id) ON DELETE CASCADE,
    quantity INT DEFAULT 1,
    is_equipped BOOLEAN DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS areas (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    min_level INT NOT NULL,
    max_level INT NOT NULL
);

CREATE TABLE IF NOT EXISTS monsters (
    id SERIAL PRIMARY KEY,
    area_id INT REFERENCES areas(id),
    name VARCHAR(100) NOT NULL,
    level INT NOT NULL,
    hp INT NOT NULL,
    damage_min INT NOT NULL,
    damage_max INT NOT NULL,
    defense INT NOT NULL,
    base_xp INT NOT NULL,
    base_gold INT NOT NULL
);

CREATE TABLE IF NOT EXISTS monster_drops (
    monster_id INT REFERENCES monsters(id),
    item_id INT REFERENCES items(id),
    drop_chance DECIMAL(5, 2) NOT NULL, -- % chance
    min_quantity INT DEFAULT 1,
    max_quantity INT DEFAULT 1,
    PRIMARY KEY (monster_id, item_id)
);

CREATE TABLE IF NOT EXISTS combat_instances (
    id SERIAL PRIMARY KEY,
    character_id INT UNIQUE REFERENCES characters(id) ON DELETE CASCADE,
    monster_id INT REFERENCES monsters(id),
    monster_current_hp INT NOT NULL,
    turn_count INT DEFAULT 1,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS guilds (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    leader_id INT UNIQUE REFERENCES characters(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    level INT DEFAULT 1,
    max_members INT DEFAULT 30
);

CREATE TABLE IF NOT EXISTS guild_members (
    guild_id INT REFERENCES guilds(id) ON DELETE CASCADE,
    character_id INT UNIQUE REFERENCES characters(id) ON DELETE CASCADE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (guild_id, character_id)
);

CREATE TABLE IF NOT EXISTS market (
    id SERIAL PRIMARY KEY,
    seller_id INT REFERENCES characters(id) ON DELETE CASCADE,
    inventory_id INT UNIQUE REFERENCES inventory(id) ON DELETE CASCADE, -- the specific item stack in inventory
    price BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed inicial do Aurora MMORPG

-- 1. Inserir Classes
INSERT INTO classes (name, base_hp, base_mana, base_str, base_agi, base_int, base_vit) VALUES
('Guerreiro', 150, 50, 10, 5, 2, 8),
('Arqueiro', 100, 70, 5, 12, 3, 5),
('Mago', 80, 150, 2, 4, 14, 4);

-- 2. Inserir Áreas
INSERT INTO areas (id, name, min_level, max_level) VALUES
(1, 'Campos Verdes', 1, 10),
(2, 'Floresta Sombria', 10, 25),
(3, 'Montanhas Rochosas', 25, 50),
(4, 'Terras Vulcânicas', 50, 100);

-- 3. Inserir Monstros (Exemplos)
-- Campos Verdes (Area 1)
INSERT INTO monsters (area_id, name, level, hp, damage_min, damage_max, defense, base_xp, base_gold) VALUES
(1, 'Rato Gigante', 1, 30, 2, 5, 1, 10, 5),
(1, 'Lobo Selvagem', 3, 50, 4, 8, 3, 25, 12),
(1, 'Javali', 5, 80, 5, 12, 5, 45, 20);

-- Floresta Sombria (Area 2)
INSERT INTO monsters (area_id, name, level, hp, damage_min, damage_max, defense, base_xp, base_gold) VALUES
(2, 'Aranha Negra', 10, 150, 10, 20, 8, 100, 50),
(2, 'Goblin', 12, 180, 15, 25, 10, 130, 60),
(2, 'Urso Selvagem', 15, 300, 20, 35, 15, 200, 90);

-- 4. Inserir Itens (Exemplos)
INSERT INTO items (name, description, type, rarity, buy_price, sell_price, bonus_str, bonus_agi, bonus_int, bonus_vit, bonus_hp, bonus_mana, is_stackable) VALUES
('Espada de Madeira', 'Uma espada frágil de treinamento.', 'weapon', 'common', 50, 10, 2, 0, 0, 0, 0, 0, false),
('Arco Curto', 'Um arco leve.', 'weapon', 'common', 50, 10, 0, 2, 0, 0, 0, 0, false),
('Cajado Velho', 'Um cajado gasto pelo tempo.', 'weapon', 'common', 50, 10, 0, 0, 2, 0, 0, 0, false),
('Poção Menor de HP', 'Recupera 50 de HP.', 'potion', 'common', 20, 5, 0, 0, 0, 0, 50, 0, true),
('Couro de Lobo', 'Material útil para criação de itens.', 'material', 'common', 0, 5, 0, 0, 0, 0, 0, 0, true);

-- 5. Configurar Drops dos Monstros
-- Lobo Selvagem pode dropar Couro de Lobo (70% de chance)
INSERT INTO monster_drops (monster_id, item_id, drop_chance, min_quantity, max_quantity) 
SELECT m.id, i.id, 70.00, 1, 2 
FROM monsters m, items i 
WHERE m.name = 'Lobo Selvagem' AND i.name = 'Couro de Lobo';

-- Lobo Selvagem pode dropar Poção Menor de HP (20% de chance)
INSERT INTO monster_drops (monster_id, item_id, drop_chance, min_quantity, max_quantity) 
SELECT m.id, i.id, 20.00, 1, 1 
FROM monsters m, items i 
WHERE m.name = 'Lobo Selvagem' AND i.name = 'Poção Menor de HP';
