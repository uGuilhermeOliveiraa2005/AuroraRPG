<?php
$host = 'aws-0-sa-east-1.pooler.supabase.com';
$port = 6543;
$db   = 'postgres';
$user = 'postgres.dqsusabcudlswwpwbxko';
$pass = 'AuroraSecurePass2026!';

$dsn = "pgsql:host=$host;port=$port;dbname=$db";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "Connected successfully to 6543 pooler\n";
} catch (PDOException $e) {
    echo "6543 failed: " . $e->getMessage() . "\n";
}

$port = 5432;
$dsn = "pgsql:host=$host;port=$port;dbname=$db";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "Connected successfully to 5432 pooler\n";
} catch (PDOException $e) {
    echo "5432 pooler failed: " . $e->getMessage() . "\n";
}

$host = 'db.dqsusabcudlswwpwbxko.supabase.co';
$port = 5432;
$user = 'postgres';
$dsn = "pgsql:host=$host;port=$port;dbname=$db";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "Connected successfully to direct 5432\n";
} catch (PDOException $e) {
    echo "Direct 5432 failed: " . $e->getMessage() . "\n";
}
