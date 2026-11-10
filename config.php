<?php
// ============================================
// CONFIGURATION - Connexion à la base de données
// Compatible WAMP, XAMPP et Docker
// ============================================
$db_host = getenv("DB_HOST") ?: "localhost";
$db_name = getenv("DB_NAME") ?: "plateforme_verification";
$db_user = getenv("DB_USER") ?: "root";
$db_pass = getenv("DB_PASS") !== false ? getenv("DB_PASS") : "";

// Ordre de test des hôtes et des ports pour éviter tout échec de connexion
$hosts = array_unique([$db_host, "db", "localhost", "127.0.0.1"]);
$ports = ["3306", "3307"];

$pdo = null;
$erreur_message = "";

foreach ($hosts as $host) {
    foreach ($ports as $port) {
        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false
                ]
            );
            break 2;
        } catch (PDOException $e) {
            $erreur_message = $e->getMessage();
        }
    }
}

if (!$pdo) {
    die("Erreur de connexion à la base de données : " . htmlspecialchars($erreur_message));
}

// Démarre la session sur toutes les pages qui incluent ce fichier
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
