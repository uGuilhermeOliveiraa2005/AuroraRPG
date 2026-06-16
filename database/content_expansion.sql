-- Atualização 1.1: Content Expansion (Lore, Áreas e Quests)

ALTER TABLE characters ADD COLUMN IF NOT EXISTS current_area_id INT REFERENCES areas(id) DEFAULT 1;

UPDATE characters SET current_area_id = 1 WHERE current_area_id IS NULL;

CREATE TABLE IF NOT EXISTS quests (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    target_monster_id INT REFERENCES monsters(id),
    target_kill_count INT DEFAULT 0,
    target_item_id INT REFERENCES items(id),
    target_item_count INT DEFAULT 0,
    reward_xp BIGINT DEFAULT 0,
    reward_gold BIGINT DEFAULT 0,
    reward_item_id INT REFERENCES items(id),
    reward_item_qty INT DEFAULT 0,
    min_level INT DEFAULT 1
);

CREATE TABLE IF NOT EXISTS character_quests (
    character_id INT REFERENCES characters(id) ON DELETE CASCADE,
    quest_id INT REFERENCES quests(id) ON DELETE CASCADE,
    progress_kills INT DEFAULT 0,
    progress_items INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'active', -- active, completed
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    PRIMARY KEY (character_id, quest_id)
);
