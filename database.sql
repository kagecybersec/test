-- Base de données : plateforme_verification
CREATE DATABASE IF NOT EXISTS plateforme_verification CHARACTER SET utf8mb4;
USE plateforme_verification;

-- Table des agents / utilisateurs (avec rôles : agent ou admin)
CREATE TABLE IF NOT EXISTS agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('admin', 'agent') NOT NULL DEFAULT 'agent',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des documents administratifs enregistrés
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_unique VARCHAR(50) NOT NULL UNIQUE,
    type_document VARCHAR(100) NOT NULL,       -- ex: Acte de naissance, CNI, Diplôme...
    nom_titulaire VARCHAR(100) NOT NULL,
    prenom_titulaire VARCHAR(100) NOT NULL,
    date_naissance DATE,
    date_emission DATE NOT NULL,
    date_expiration DATE,
    statut ENUM('valide','annule','falsifie') NOT NULL DEFAULT 'valide',
    date_enregistrement TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Historique des vérifications effectuées (traçabilité)
CREATE TABLE IF NOT EXISTS verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    numero_unique_saisi VARCHAR(50) NOT NULL,
    resultat VARCHAR(50) NOT NULL,              -- AUTHENTIQUE / EXPIRÉ / FAUX DOCUMENT / INEXISTANT
    date_verification TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id)
);

-- Comptes initiaux :
-- 1. Administrateur : admin@plateforme.com / admin123 (Rôle: admin)
INSERT INTO agents (nom, prenom, email, mot_de_passe, role) VALUES
('Admin', 'Super', 'admin@plateforme.com', '$2y$10$DWbujPkrwq2w6N6P4A1XQud4zDFfrBVUlTsSsarTFxTn20T30mAJq', 'admin')
ON DUPLICATE KEY UPDATE role = 'admin', mot_de_passe = VALUES(mot_de_passe);

-- 2. Agent de contrôle : agent@plateforme.com / agent123 (Rôle: agent)
INSERT INTO agents (nom, prenom, email, mot_de_passe, role) VALUES
('Kouassi', 'Jean', 'agent@plateforme.com', '$2y$10$JcyuMvSf83Fp8.AIAleJYu/IV3.KODq7yDm0iBSMnGjyZfiMbqCPO', 'agent')
ON DUPLICATE KEY UPDATE role = 'agent', mot_de_passe = VALUES(mot_de_passe);

-- Documents de test officiels
INSERT INTO documents (numero_unique, type_document, nom_titulaire, prenom_titulaire, date_naissance, date_emission, date_expiration, statut) VALUES
('DOC-2024-000123', 'Carte Nationale d\'Identité', 'Koffi', 'Marie', '1998-05-14', '2024-01-10', '2034-01-10', 'valide'),
('DOC-2023-000045', 'Acte de Naissance', 'Traoré', 'Ali', '2000-09-02', '2023-03-22', NULL, 'valide'),
('DOC-2020-000555', 'Permis de Conduire', 'Konan', 'Yves', '1990-03-12', '2020-01-15', '2023-01-15', 'valide'),
('DOC-2022-000987', 'Diplôme BTS', 'Bamba', 'Fatou', '1999-11-30', '2022-07-15', NULL, 'falsifie')
ON DUPLICATE KEY UPDATE statut = VALUES(statut);
