<?php
// ==========================================================
// Initialisation des comptes de test (Admin & Agent)
// ==========================================================
require "config.php";

$hash_admin = password_hash("admin123", PASSWORD_DEFAULT);
$hash_agent = password_hash("agent123", PASSWORD_DEFAULT);

// 1. Initialisation / Réinitialisation de l'administrateur
$check_admin = $pdo->prepare("SELECT id FROM agents WHERE email = 'admin@plateforme.com'");
$check_admin->execute();
$admin_existant = $check_admin->fetch(PDO::FETCH_ASSOC);

if ($admin_existant) {
    $stmt = $pdo->prepare("UPDATE agents SET nom = 'Super', prenom = 'Admin', mot_de_passe = ?, role = 'admin' WHERE id = ?");
    $stmt->execute([$hash_admin, $admin_existant["id"]]);
} else {
    $stmt = $pdo->prepare("INSERT INTO agents (nom, prenom, email, mot_de_passe, role) VALUES ('Super', 'Admin', 'admin@plateforme.com', ?, 'admin')");
    $stmt->execute([$hash_admin]);
}

// 2. Initialisation / Réinitialisation de l'agent
$check_agent = $pdo->prepare("SELECT id FROM agents WHERE email = 'agent@plateforme.com'");
$check_agent->execute();
$agent_existant = $check_agent->fetch(PDO::FETCH_ASSOC);

if ($agent_existant) {
    $stmt = $pdo->prepare("UPDATE agents SET nom = 'Kouassi', prenom = 'Jean', mot_de_passe = ?, role = 'agent' WHERE id = ?");
    $stmt->execute([$hash_agent, $agent_existant["id"]]);
} else {
    $stmt = $pdo->prepare("INSERT INTO agents (nom, prenom, email, mot_de_passe, role) VALUES ('Kouassi', 'Jean', 'agent@plateforme.com', ?, 'agent')");
    $stmt->execute([$hash_agent]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialisation des Comptes - Plateforme de Vérification</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="conteneur">
        <div class="en-tete">
            <h2>🔑 Initialisation des Comptes</h2>
        </div>
        <div class="corps">
            <div class="message-succes">
                Les comptes de démonstration ont été configurés avec succès !
            </div>

            <div style="margin-bottom: 16px; padding: 12px; background: #F8FAFC; border-radius: 8px; border-left: 4px solid var(--violet);">
                <p><strong>👑 Compte Administrateur :</strong></p>
                <p>Email : <code>admin@plateforme.com</code></p>
                <p>Mot de passe : <code>admin123</code></p>
            </div>

            <div style="margin-bottom: 20px; padding: 12px; background: #F8FAFC; border-radius: 8px; border-left: 4px solid var(--vert);">
                <p><strong>👤 Compte Agent :</strong></p>
                <p>Email : <code>agent@plateforme.com</code></p>
                <p>Mot de passe : <code>agent123</code></p>
            </div>

            <a href="login.php" class="bouton-principal">
                &rarr; Aller à la page de connexion
            </a>
        </div>
    </div>

</body>
</html>
