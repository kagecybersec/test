<?php
// ============================================
// CONFIGURATION DE LA BASE DE DONNÉES
// Compatible Hybride :
// 1. MySQL (Docker / WAMP / XAMPP) si disponible
// 2. SQLite (Autonome / Standalone) si aucun serveur MySQL n'est actif
// ============================================

$pdo = null;
$db_driver = "none";

// Tentative 1 : Connexion à MySQL (si conteneur Docker ou serveur local démarré)
$db_host = getenv("DB_HOST") ?: "localhost";
$db_name = getenv("DB_NAME") ?: "plateforme_verification";
$db_user = getenv("DB_USER") ?: "root";
$db_pass = getenv("DB_PASS") !== false ? getenv("DB_PASS") : "";

$hosts = array_unique([$db_host, "db", "localhost", "127.0.0.1"]);
$ports = ["3306", "3307"];

foreach ($hosts as $host) {
    foreach ($ports as $port) {
        try {
            $test_pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 1 // Timeout rapide pour ne pas bloquer
                ]
            );
            $pdo = $test_pdo;
            $db_driver = "mysql";
            break 2;
        } catch (Exception $e) {
            // MySQL non disponible sur cet hôte/port, continuer
        }
    }
}

// Tentative 2 : Si MySQL n'est pas actif, bascule automatique sur SQLite (Autonome universel)
if (!$pdo) {
    $data_dir = __DIR__ . "/data";
    if (!is_dir($data_dir)) {
        @mkdir($data_dir, 0777, true);
    }
    $sqlite_file = $data_dir . "/database.sqlite";
    $first_init = !file_exists($sqlite_file) || filesize($sqlite_file) === 0;

    try {
        $pdo = new PDO("sqlite:" . $sqlite_file, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $db_driver = "sqlite";

        if ($first_init) {
            // Création automatique du schéma SQLite
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS agents (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    nom TEXT NOT NULL,
                    prenom TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    mot_de_passe TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'agent',
                    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS documents (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    numero_unique TEXT NOT NULL UNIQUE,
                    type_document TEXT NOT NULL,
                    nom_titulaire TEXT NOT NULL,
                    prenom_titulaire TEXT NOT NULL,
                    date_naissance DATE,
                    date_emission DATE NOT NULL,
                    date_expiration DATE,
                    statut TEXT NOT NULL DEFAULT 'valide',
                    date_enregistrement DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS verifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    agent_id INTEGER NOT NULL,
                    numero_unique_saisi TEXT NOT NULL,
                    resultat TEXT NOT NULL,
                    date_verification DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (agent_id) REFERENCES agents(id)
                );

                -- Comptes par défaut
                INSERT OR REPLACE INTO agents (id, nom, prenom, email, mot_de_passe, role) VALUES
                (1, 'Super', 'Admin', 'admin@plateforme.com', '$2y$10$DWbujPkrwq2w6N6P4A1XQud4zDFfrBVUlTsSsarTFxTn20T30mAJq', 'admin'),
                (2, 'Kouassi', 'Jean', 'agent@plateforme.com', '$2y$10$JcyuMvSf83Fp8.AIAleJYu/IV3.KODq7yDm0iBSMnGjyZfiMbqCPO', 'agent');

                -- Documents de test officiels
                INSERT OR REPLACE INTO documents (id, numero_unique, type_document, nom_titulaire, prenom_titulaire, date_naissance, date_emission, date_expiration, statut) VALUES
                (1, 'DOC-2024-000123', 'Carte Nationale d\'Identité', 'Koffi', 'Marie', '1998-05-14', '2024-01-10', '2034-01-10', 'valide'),
                (2, 'DOC-2023-000045', 'Acte de Naissance', 'Traoré', 'Ali', '2000-09-02', '2023-03-22', NULL, 'valide'),
                (3, 'DOC-2020-000555', 'Permis de Conduire', 'Konan', 'Yves', '1990-03-12', '2020-01-15', '2023-01-15', 'valide'),
                (4, 'DOC-2022-000987', 'Diplôme BTS', 'Bamba', 'Fatou', '1999-11-30', '2022-07-15', NULL, 'falsifie');
            ");
        }
    } catch (PDOException $e) {
        die("Erreur d'initialisation de la base de données : " . htmlspecialchars($e->getMessage()));
    }
}

// Démarre la session sur toutes les pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
