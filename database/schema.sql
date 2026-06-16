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
