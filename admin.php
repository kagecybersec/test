<?php
require "config.php";

// Protection stricte : Seul un administrateur connecté a accès
if (!isset($_SESSION["agent_id"]) || ($_SESSION["agent_role"] ?? "") !== "admin") {
    header("Location: verifier.php");
    exit;
}

// Statistiques globales
$nb_users = $pdo->query("SELECT COUNT(*) FROM agents")->fetchColumn();
$nb_admins = $pdo->query("SELECT COUNT(*) FROM agents WHERE role = 'admin'")->fetchColumn();
$nb_agents = $pdo->query("SELECT COUNT(*) FROM agents WHERE role = 'agent'")->fetchColumn();

$nb_docs = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$nb_docs_valides = $pdo->query("SELECT COUNT(*) FROM documents WHERE statut = 'valide'")->fetchColumn();
$nb_docs_suspects = $pdo->query("SELECT COUNT(*) FROM documents WHERE statut IN ('annule', 'falsifie')")->fetchColumn();

$nb_verifs = $pdo->query("SELECT COUNT(*) FROM verifications")->fetchColumn();

// Derniers utilisateurs enregistrés
$derniers_users = $pdo->query("SELECT id, nom, prenom, email, role, date_creation FROM agents ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Dernières vérifications d'audit
$dernieres_verifs = $pdo->query("SELECT v.*, CONCAT(a.prenom, ' ', a.nom) as agent_nom FROM verifications v JOIN agents a ON v.agent_id = a.id ORDER BY v.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Administrateur - Plateforme de Vérification</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header class="barre-titre">
        <a href="admin.php" class="logo-groupe">
            <span class="logo-icone">🛡️</span>
            <h1>Plateforme de Vérification</h1>
            <span class="badge-ministere">DGTTC ADMIN</span>
        </a>

        <nav class="nav-onglets">
            <a href="admin.php" class="actif onglet-admin">👑 Tableau de bord</a>
            <a href="admin_utilisateurs.php" class="onglet-admin">👥 Utilisateurs</a>
            <a href="admin_documents.php" class="onglet-admin">📁 Documents</a>
            <a href="verifier.php">🔍 Mode Vérification</a>
            <a href="historique.php">📋 Journal d'audit</a>
        </nav>

        <div class="agent-zone">
            <div class="agent-info">
                <span class="point-vert"></span>
                <span>👑 <?= htmlspecialchars($_SESSION["agent_nom"]) ?> (Admin)</span>
            </div>
            <a href="logout.php" class="deconnexion">Déconnexion</a>
        </div>
    </header>

    <div class="conteneur conteneur-plein">
        <div class="en-tete en-tete-admin">
            <h2>⚙️ Panneau de Contrôle & Administration Globale</h2>
            <span style="font-size: 13px; opacity: 0.9;">Privilèges administrateur complets</span>
        </div>
        <div class="corps">

            <!-- Statistiques clés -->
            <div class="grille-stats">
                <div class="carte-stat">
                    <div class="icone-stat" style="color: var(--violet);">👥</div>
                    <div class="info-stat">
                        <div class="valeur-stat"><?= (int)$nb_users ?></div>
                        <div class="titre-stat"><?= (int)$nb_admins ?> admin(s) &bull; <?= (int)$nb_agents ?> agent(s)</div>
                    </div>
                </div>
                <div class="carte-stat">
                    <div class="icone-stat" style="color: var(--bleu);">📁</div>
                    <div class="info-stat">
                        <div class="valeur-stat"><?= (int)$nb_docs ?></div>
                        <div class="titre-stat">Documents enregistrés</div>
                    </div>
                </div>
                <div class="carte-stat">
                    <div class="icone-stat" style="color: var(--vert);">✅</div>
                    <div class="info-stat">
                        <div class="valeur-stat" style="color: var(--vert-fonce);"><?= (int)$nb_docs_valides ?></div>
                        <div class="titre-stat">Documents conformes</div>
                    </div>
                </div>
                <div class="carte-stat">
                    <div class="icone-stat" style="color: var(--rouge);">🚨</div>
                    <div class="info-stat">
                        <div class="valeur-stat" style="color: var(--rouge);"><?= (int)$nb_docs_suspects ?></div>
                        <div class="titre-stat">Falsifiés ou Annulés</div>
                    </div>
                </div>
            </div>

            <!-- Modules de gestion rapide -->
            <h3 style="margin-bottom: 16px; font-size: 18px; color: var(--gris-texte);">Actions Administratives</h3>
            <div class="grille-actions-admin">
                <div class="carte-action-admin">
                    <div>
                        <h3>👥 Gestion des Utilisateurs</h3>
                        <p>Créez de nouveaux administrateurs ou agents de contrôle, modifiez les rôles et supprimez des accès.</p>
                    </div>
                    <a href="admin_utilisateurs.php" class="bouton-principal" style="background-color: var(--violet); width: auto;">
                        Gérer les utilisateurs &rarr;
                    </a>
                </div>

                <div class="carte-action-admin">
                    <div>
                        <h3>📁 Registre des Documents</h3>
                        <p>Consultez la base complète des titres, modifiez leur statut (valide, annulé, falsifié) ou supprimez des fiches.</p>
                    </div>
                    <a href="admin_documents.php" class="bouton-principal" style="background-color: var(--bleu); width: auto;">
                        Gérer les documents &rarr;
                    </a>
                </div>

                <div class="carte-action-admin">
                    <div>
                        <h3>➕ Nouvel Enregistrement</h3>
                        <p>Enregistrez une nouvelle pièce officielle (CNI, Permis, Diplôme, Acte d'état civil) au registre centralisé.</p>
                    </div>
                    <a href="ajouter_document.php" class="bouton-principal" style="background-color: var(--vert); width: auto;">
                        Ajouter un document &rarr;
                    </a>
                </div>
            </div>

            <!-- Double tableau récapitulatif -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px;">
                <!-- Derniers comptes créés -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="font-size: 15px;">Derniers comptes utilisateurs</h4>
                        <a href="admin_utilisateurs.php" style="font-size: 13px; color: var(--orange); text-decoration: none; font-weight: 600;">Voir tout &rarr;</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table-donnees">
                            <thead>
                                <tr>
                                    <th>Nom & Prénom</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($derniers_users as $u): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($u["prenom"] . " " . $u["nom"]) ?></strong></td>
                                        <td><?= htmlspecialchars($u["email"]) ?></td>
                                        <td>
                                            <?php if ($u["role"] === "admin"): ?>
                                                <span class="badge-role badge-role-admin">👑 Admin</span>
                                            <?php else: ?>
                                                <span class="badge-role badge-role-agent">👤 Agent</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Dernières vérifications d'audit -->
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="font-size: 15px;">Dernières vérifications effectuées</h4>
                        <a href="historique.php" style="font-size: 13px; color: var(--orange); text-decoration: none; font-weight: 600;">Journal complet &rarr;</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table-donnees">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Agent</th>
                                    <th>Document</th>
                                    <th>Verdict</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dernieres_verifs as $v): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date("d/m H:i", strtotime($v["date_verification"]))) ?></td>
                                        <td><?= htmlspecialchars($v["agent_nom"]) ?></td>
                                        <td><code><?= htmlspecialchars($v["numero_unique_saisi"]) ?></code></td>
                                        <td>
                                            <?php if ($v["resultat"] === "AUTHENTIQUE"): ?>
                                                <span class="pastille-statut pastille-valide">Authentique</span>
                                            <?php elseif ($v["resultat"] === "EXPIRÉ"): ?>
                                                <span class="pastille-statut pastille-expire">Expiré</span>
                                            <?php elseif ($v["resultat"] === "FAUX DOCUMENT"): ?>
                                                <span class="pastille-statut pastille-faux">Faux</span>
                                            <?php else: ?>
                                                <span class="pastille-statut pastille-inexistant">Inexistant</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <footer class="bas-de-page">
        Console d'administration officielle &bull; Direction Générale &bull; Accès réservé et tracé
    </footer>

</body>
</html>

