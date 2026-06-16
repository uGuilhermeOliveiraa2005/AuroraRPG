<?php

namespace Aurora\Core;

use PDO;
use PDOException;
use Exception;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = Config::get('DB_HOST', '127.0.0.1');
            $port = Config::get('DB_PORT', '5432');
            $dbname = Config::get('DB_NAME', 'postgres');
            $user = Config::get('DB_USER', 'postgres');
            $password = Config::get('DB_PASS', '');

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

            try {
                self::$instance = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false, // Essencial para segurança SQL Injection
                ]);
            } catch (PDOException $e) {
                throw new Exception("Erro de conexão com o banco de dados: " . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
