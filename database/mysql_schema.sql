-- Criação das tabelas do Aurora MMORPG
-- Database: MySQL (Infinity Free)
-- Database Name: if0_41884898_aurorarpg

CREATE DATABASE IF NOT EXISTS `if0_41884898_aurorarpg`;
USE `if0_41884898_aurorarpg`;

CREATE TABLE IF NOT EXISTS `users` (
    `telegram_id` BIGINT PRIMARY KEY,
    `username` VARCHAR(255),
    `first_name` VARCHAR(255),
    `is_vip` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_interaction` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `state` VARCHAR(50) DEFAULT 'idle'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `classes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `base_hp` INT NOT NULL,
    `base_mana` INT NOT NULL,
    `base_str` INT NOT NULL,
    `base_agi` INT NOT NULL,
    `base_int` INT NOT NULL,
    `base_vit` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `characters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNIQUE,
    `name` VARCHAR(50) NOT NULL,
    `class_id` INT,
    `level` INT DEFAULT 1,
    `xp` BIGINT DEFAULT 0,
    `gold` BIGINT DEFAULT 0,
    `hp` INT NOT NULL,
    `max_hp` INT NOT NULL,
    `mana` INT NOT NULL,
    `max_mana` INT NOT NULL,
    `stat_points` INT DEFAULT 0,
    `str` INT NOT NULL,
    `agi` INT NOT NULL,
    `int` INT NOT NULL,
    `vit` INT NOT NULL,
    `inventory_slots` INT DEFAULT 30,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`telegram_id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `type` VARCHAR(50) NOT NULL,
    `rarity` VARCHAR(20) NOT NULL,
    `buy_price` INT DEFAULT 0,
    `sell_price` INT DEFAULT 0,
    `bonus_str` INT DEFAULT 0,
    `bonus_agi` INT DEFAULT 0,
    `bonus_int` INT DEFAULT 0,
    `bonus_vit` INT DEFAULT 0,
    `bonus_hp` INT DEFAULT 0,
    `bonus_mana` INT DEFAULT 0,
    `is_stackable` BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `character_id` INT,
    `item_id` INT,
    `quantity` INT DEFAULT 1,
    `is_equipped` BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `areas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `min_level` INT NOT NULL,
    `max_level` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monsters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `area_id` INT,
    `name` VARCHAR(100) NOT NULL,
    `level` INT NOT NULL,
    `hp` INT NOT NULL,
    `damage_min` INT NOT NULL,
    `damage_max` INT NOT NULL,
    `defense` INT NOT NULL,
    `base_xp` INT NOT NULL,
    `base_gold` INT NOT NULL,
    FOREIGN KEY (`area_id`) REFERENCES `areas`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monster_drops` (
    `monster_id` INT,
    `item_id` INT,
    `drop_chance` DECIMAL(5, 2) NOT NULL,
    `min_quantity` INT DEFAULT 1,
    `max_quantity` INT DEFAULT 1,
    PRIMARY KEY (`monster_id`, `item_id`),
    FOREIGN KEY (`monster_id`) REFERENCES `monsters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `combat_instances` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `character_id` INT UNIQUE,
    `monster_id` INT,
    `monster_current_hp` INT NOT NULL,
    `turn_count` INT DEFAULT 1,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`monster_id`) REFERENCES `monsters`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `guilds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) UNIQUE NOT NULL,
    `leader_id` INT UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `level` INT DEFAULT 1,
    `max_members` INT DEFAULT 30,
    FOREIGN KEY (`leader_id`) REFERENCES `characters`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `guild_members` (
    `guild_id` INT,
    `character_id` INT UNIQUE,
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`guild_id`, `character_id`),
    FOREIGN KEY (`guild_id`) REFERENCES `guilds`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `market` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `seller_id` INT,
    `inventory_id` INT UNIQUE,
    `price` BIGINT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`seller_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
