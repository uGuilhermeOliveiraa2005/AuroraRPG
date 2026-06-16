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
