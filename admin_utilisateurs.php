<?php
require "config.php";

// Protection stricte : Seul un administrateur connecté a accès
if (!isset($_SESSION["agent_id"]) || ($_SESSION["agent_role"] ?? "") !== "admin") {
    header("Location: verifier.php");
    exit;
}

$message = "";
$type_message = "";

// 1. Traitement de la suppression d'un utilisateur
if (isset($_GET["action"]) && $_GET["action"] === "supprimer" && isset($_GET["id"])) {
    $id_a_supprimer = (int)$_GET["id"];

    if ($id_a_supprimer === (int)$_SESSION["agent_id"]) {
        $message = "Sécurité : Vous ne pouvez pas supprimer votre propre compte administrateur connecté.";
        $type_message = "erreur";
    } else {
        // Vérification des dépendances dans verifications
        $has_logs = $pdo->prepare("SELECT COUNT(*) FROM verifications WHERE agent_id = ?");
        $has_logs->execute([$id_a_supprimer]);
        $count = $has_logs->fetchColumn();

        if ($count > 0) {
            // Suppression des logs ou réattribution
            $del_logs = $pdo->prepare("DELETE FROM verifications WHERE agent_id = ?");
            $del_logs->execute([$id_a_supprimer]);
        }

        $stmt_del = $pdo->prepare("DELETE FROM agents WHERE id = ?");
        $stmt_del->execute([$id_a_supprimer]);
        $message = "Le compte utilisateur #$id_a_supprimer a été supprimé avec succès.";
        $type_message = "succes";
    }
}

// 2. Traitement de la création d'un nouvel utilisateur
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["creer_user"])) {
    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $mot_de_passe = $_POST["mot_de_passe"] ?? "";
    $role = ($_POST["role"] ?? "") === "admin" ? "admin" : "agent";

    if ($nom === "" || $prenom === "" || $email === "" || $mot_de_passe === "") {
        $message = "Tous les champs sont obligatoires pour créer un utilisateur.";
        $type_message = "erreur";
    } else {
        // Vérification si l'email existe déjà
        $check = $pdo->prepare("SELECT id FROM agents WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $message = "L'adresse email <strong>" . htmlspecialchars($email) . "</strong> est déjà associée à un compte.";
            $type_message = "erreur";
        } else {
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $in = $pdo->prepare("INSERT INTO agents (nom, prenom, email, mot_de_passe, role) VALUES (?, ?, ?, ?, ?)");
            $in->execute([$nom, $prenom, $email, $hash, $role]);

            $type_str = ($role === 'admin') ? "Administrateur" : "Agent de contrôle";
            $message = "Nouveau compte ($type_str) créé avec succès pour <strong>" . htmlspecialchars("$prenom $nom") . "</strong> !";
            $type_message = "succes";
        }
    }
}

// 3. Traitement de la modification du rôle ou du mot de passe
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["modifier_user"])) {
    $id_mod = (int)$_POST["id_user"];
    $nouveau_role = ($_POST["nouveau_role"] ?? "") === "admin" ? "admin" : "agent";
    $nouveau_mdp = trim($_POST["nouveau_mdp"] ?? "");

    if ($nouveau_mdp !== "") {
        $hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
        $up = $pdo->prepare("UPDATE agents SET role = ?, mot_de_passe = ? WHERE id = ?");
        $up->execute([$nouveau_role, $hash, $id_mod]);
        $message = "Rôle et mot de passe de l'utilisateur #$id_mod mis à jour avec succès.";
    } else {
        $up = $pdo->prepare("UPDATE agents SET role = ? WHERE id = ?");
        $up->execute([$nouveau_role, $id_mod]);
        $message = "Rôle de l'utilisateur #$id_mod mis à jour avec succès.";
    }
    $type_message = "succes";
}

// Récupération de tous les utilisateurs
$utilisateurs = $pdo->query("SELECT id, nom, prenom, email, role, date_creation FROM agents ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Espace Admin</title>
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
            <a href="admin.php" class="onglet-admin">👑 Tableau de bord</a>
            <a href="admin_utilisateurs.php" class="actif onglet-admin">👥 Utilisateurs</a>
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
            <h2>👥 Gestion des Utilisateurs (Administrateurs & Agents)</h2>
            <button type="button" class="bouton-secondaire" onclick="document.getElementById('zoneCreation').scrollIntoView({behavior: 'smooth'})" style="background: rgba(255,255,255,0.25); color: #FFF; border: none; font-weight: 700;">
                ➕ Créer un compte
            </button>
        </div>
        <div class="corps">

            <?php if ($message): ?>
                <div class="<?= $type_message === 'succes' ? 'message-succes' : 'message-erreur' ?>">
                    <span><?= $type_message === 'succes' ? '✅' : '⚠️' ?></span>
                    <div><?= $message ?></div>
                </div>
            <?php endif; ?>

            <!-- Tableau des utilisateurs existants -->
            <h3 style="margin-bottom: 14px; font-size: 16px;">Liste des comptes enregistrés (<?= count($utilisateurs) ?>)</h3>
            <div class="table-responsive" style="margin-bottom: 35px;">
                <table class="table-donnees">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Nom & Prénom</th>
                            <th>Email de connexion</th>
                            <th>Rôle / Privilèges</th>
                            <th>Date d'inscription</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $u): ?>
                            <tr>
                                <td><strong>#<?= (int)$u["id"] ?></strong></td>
                                <td><strong><?= htmlspecialchars($u["prenom"] . " " . $u["nom"]) ?></strong></td>
                                <td><?= htmlspecialchars($u["email"]) ?></td>
                                <td>
                                    <?php if ($u["role"] === "admin"): ?>
                                        <span class="badge-role badge-role-admin">👑 Administrateur</span>
                                    <?php else: ?>
                                        <span class="badge-role badge-role-agent">👤 Agent de contrôle</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(date("d/m/Y", strtotime($u["date_creation"]))) ?></td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <button type="button" class="bouton-modifier" onclick="ouvrirEdition(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['prenom'] . ' ' . $u['nom'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['role']) ?>')">
                                        ✏️ Modifier
                                    </button>
                                    <?php if ($u["id"] !== (int)$_SESSION["agent_id"]): ?>
                                        <a href="admin_utilisateurs.php?action=supprimer&id=<?= (int)$u['id'] ?>" class="bouton-danger" onclick="return confirm('Confirmez-vous la suppression définitive du compte de <?= htmlspecialchars($u['prenom'] . ' ' . $u['nom'], ENT_QUOTES) ?> ?')" style="text-decoration: none; margin-left: 6px;">
                                            🗑️ Supprimer
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: var(--gris-secondaire); margin-left: 6px;">(Votre compte)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal / Formulaire d'édition (caché par défaut) -->
            <div id="modalEdition" style="display: none; background: #F8FAFC; border: 2px solid var(--bleu); border-radius: 10px; padding: 22px; margin-bottom: 35px;">
                <h3 style="color: var(--bleu); margin-bottom: 14px; font-size: 16px;">
                    ✏️ Modifier l'utilisateur : <span id="nomUtilisateurEdite" style="color: var(--gris-texte);"></span>
                </h3>
                <form method="POST" action="admin_utilisateurs.php">
                    <input type="hidden" name="modifier_user" value="1">
                    <input type="hidden" name="id_user" id="editer_id_user">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="groupe-champ">
                            <label for="nouveau_role">Rôle / Droits d'accès *</label>
                            <select name="nouveau_role" id="editer_role" required>
                                <option value="agent">👤 Simple Agent de contrôle</option>
                                <option value="admin">👑 Administrateur (Tous les droits)</option>
                            </select>
                        </div>
                        <div class="groupe-champ">
                            <label for="nouveau_mdp">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                            <input type="password" name="nouveau_mdp" placeholder="Laisser vide si inchangé">
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="bouton-principal" style="width: auto; padding: 10px 20px;">Enregistrer les modifications</button>
                        <button type="button" class="bouton-secondaire" onclick="fermerEdition()">Annuler</button>
                    </div>
                </form>
            </div>

            <!-- Formulaire de création d'un utilisateur -->
            <div id="zoneCreation" style="background: #FFF; border: 1px solid var(--gris-bordure); border-radius: 10px; padding: 24px;">
                <h3 style="margin-bottom: 16px; font-size: 17px; color: var(--gris-texte); display: flex; align-items: center; gap: 8px;">
                    <span>➕</span> Créer un nouveau compte (Administrateur ou Agent)
                </h3>
                <form method="POST" action="admin_utilisateurs.php">
                    <input type="hidden" name="creer_user" value="1">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                        <div class="groupe-champ">
                            <label for="nom">Nom *</label>
                            <input type="text" id="nom" name="nom" placeholder="Ex: Kouamé" required>
                        </div>

                        <div class="groupe-champ">
                            <label for="prenom">Prénom(s) *</label>
                            <input type="text" id="prenom" name="prenom" placeholder="Ex: Paul" required>
                        </div>

                        <div class="groupe-champ">
                            <label for="email">Adresse email de connexion *</label>
                            <input type="email" id="email" name="email" placeholder="paul.kouame@plateforme.com" required>
                        </div>

                        <div class="groupe-champ">
                            <label for="mot_de_passe">Mot de passe initial *</label>
                            <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="••••••••" required>
                        </div>

                        <div class="groupe-champ" style="grid-column: span 2;">
                            <label for="role">Rôle et niveau d'accréditation *</label>
                            <select id="role" name="role" required>
                                <option value="agent">👤 Simple Agent (Vérification et consultation de l'historique)</option>
                                <option value="admin">👑 Administrateur (Gestion complète des comptes, des documents et des audits)</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="bouton-principal" style="width: auto; padding: 12px 28px; margin-top: 10px;">
                        Valider et créer le compte
                    </button>
                </form>
            </div>

        </div>
    </div>

    <footer class="bas-de-page">
        Gestion sécurisée des accréditations &bull; Système de contrôle des identités DGTTC
    </footer>

    <script>
    function ouvrirEdition(id, nom, role) {
        document.getElementById('modalEdition').style.display = 'block';
        document.getElementById('editer_id_user').value = id;
        document.getElementById('nomUtilisateurEdite').textContent = nom + ' (#' + id + ')';
        document.getElementById('editer_role').value = role;
        document.getElementById('modalEdition').scrollIntoView({behavior: 'smooth'});
    }

    function fermerEdition() {
        document.getElementById('modalEdition').style.display = 'none';
    }
    </script>

</body>
</html>

