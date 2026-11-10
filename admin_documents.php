<?php
require "config.php";

// Protection stricte : Seul un administrateur connecté a accès
if (!isset($_SESSION["agent_id"]) || ($_SESSION["agent_role"] ?? "") !== "admin") {
    header("Location: verifier.php");
    exit;
}

$message = "";
$type_message = "";

// 1. Suppression d'un document
if (isset($_GET["action"]) && $_GET["action"] === "supprimer" && isset($_GET["id"])) {
    $id_doc = (int)$_GET["id"];
    $stmt_del = $pdo->prepare("DELETE FROM documents WHERE id = ?");
    $stmt_del->execute([$id_doc]);
    $message = "Le document #$id_doc a été définitivement supprimé du registre officiel.";
    $type_message = "succes";
}

// 2. Modification d'un document
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["modifier_document"])) {
    $id_doc = (int)$_POST["id_document"];
    $type_document = trim($_POST["type_document"] ?? "");
    $nom_titulaire = trim($_POST["nom_titulaire"] ?? "");
    $prenom_titulaire = trim($_POST["prenom_titulaire"] ?? "");
    $date_naissance = !empty($_POST["date_naissance"]) ? $_POST["date_naissance"] : null;
    $date_emission = trim($_POST["date_emission"] ?? "");
    $date_expiration = !empty($_POST["date_expiration"]) ? $_POST["date_expiration"] : null;
    $statut = trim($_POST["statut"] ?? "valide");

    if ($type_document === "" || $nom_titulaire === "" || $prenom_titulaire === "" || $date_emission === "") {
        $message = "Veuillez renseigner tous les champs obligatoires.";
        $type_message = "erreur";
    } else {
        $up = $pdo->prepare(
            "UPDATE documents SET type_document = ?, nom_titulaire = ?, prenom_titulaire = ?, date_naissance = ?, date_emission = ?, date_expiration = ?, statut = ?
             WHERE id = ?"
        );
        $up->execute([$type_document, $nom_titulaire, $prenom_titulaire, $date_naissance, $date_emission, $date_expiration, $statut, $id_doc]);
        $message = "Le document #$id_doc a été mis à jour avec succès.";
        $type_message = "succes";
    }
}

// Filtres de recherche
$recherche = trim($_GET["q"] ?? "");
$filtre_statut = trim($_GET["statut"] ?? "");

$sql = "SELECT * FROM documents WHERE 1=1";
$params = [];

if ($recherche !== "") {
    $sql .= " AND (numero_unique LIKE ? OR nom_titulaire LIKE ? OR prenom_titulaire LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

if ($filtre_statut !== "") {
    $sql .= " AND statut = ?";
    $params[] = $filtre_statut;
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registre des Documents - Espace Admin</title>
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
            <a href="admin_utilisateurs.php" class="onglet-admin">👥 Utilisateurs</a>
            <a href="admin_documents.php" class="actif onglet-admin">📁 Documents</a>
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
            <h2>📁 Gestion & Administration des Documents du Registre</h2>
            <a href="ajouter_document.php" class="bouton-secondaire" style="background: rgba(255,255,255,0.25); color: #FFF; border: none; font-weight: 700;">
                ➕ Enregistrer un document
            </a>
        </div>
        <div class="corps">

            <?php if ($message): ?>
                <div class="<?= $type_message === 'succes' ? 'message-succes' : 'message-erreur' ?>">
                    <span><?= $type_message === 'succes' ? '✅' : '⚠️' ?></span>
                    <div><?= $message ?></div>
                </div>
            <?php endif; ?>

            <!-- Modal / Formulaire d'édition de document (caché par défaut) -->
            <div id="modalEditionDoc" style="display: none; background: #F8FAFC; border: 2px solid var(--bleu); border-radius: 10px; padding: 22px; margin-bottom: 30px;">
                <h3 style="color: var(--bleu); margin-bottom: 14px; font-size: 16px;">
                    ✏️ Modifier le document : <span id="titreDocEdite" style="color: var(--gris-texte);"></span>
                </h3>
                <form method="POST" action="admin_documents.php">
                    <input type="hidden" name="modifier_document" value="1">
                    <input type="hidden" name="id_document" id="editer_id_doc">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                        <div class="groupe-champ">
                            <label>Type de document *</label>
                            <input type="text" name="type_document" id="editer_type" required>
                        </div>
                        <div class="groupe-champ">
                            <label>Nom titulaire *</label>
                            <input type="text" name="nom_titulaire" id="editer_nom" required>
                        </div>
                        <div class="groupe-champ">
                            <label>Prénom titulaire *</label>
                            <input type="text" name="prenom_titulaire" id="editer_prenom" required>
                        </div>
                        <div class="groupe-champ">
                            <label>Date de naissance</label>
                            <input type="date" name="date_naissance" id="editer_naissance">
                        </div>
                        <div class="groupe-champ">
                            <label>Date d'émission *</label>
                            <input type="date" name="date_emission" id="editer_emission" required>
                        </div>
                        <div class="groupe-champ">
                            <label>Date d'expiration</label>
                            <input type="date" name="date_expiration" id="editer_expiration">
                        </div>
                        <div class="groupe-champ">
                            <label>Statut officiel *</label>
                            <select name="statut" id="editer_statut" required>
                                <option value="valide">Valide (Authentique)</option>
                                <option value="annule">Annulé / Révoqué</option>
                                <option value="falsifie">Falsifié</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <button type="submit" class="bouton-principal" style="width: auto; padding: 10px 22px;">Enregistrer les modifications</button>
                        <button type="button" class="bouton-secondaire" onclick="fermerEditionDoc()">Annuler</button>
                    </div>
                </form>
            </div>

            <!-- Filtres & Recherche -->
            <div class="barre-outils-table">
                <form method="GET" action="admin_documents.php">
                    <input type="text" name="q" placeholder="Rechercher par numéro, nom ou prénom..."
                           value="<?= htmlspecialchars($recherche) ?>"
                           style="padding: 10px 14px; border: 2px solid var(--gris-bordure); border-radius: 8px; flex: 1;">

                    <select name="statut" style="padding: 10px 12px; border: 2px solid var(--gris-bordure); border-radius: 8px; background: #FFF;">
                        <option value="">Tous les statuts</option>
                        <option value="valide" <?= $filtre_statut === 'valide' ? 'selected' : '' ?>>Valide</option>
                        <option value="annule" <?= $filtre_statut === 'annule' ? 'selected' : '' ?>>Annulé</option>
                        <option value="falsifie" <?= $filtre_statut === 'falsifie' ? 'selected' : '' ?>>Falsifié</option>
                    </select>

                    <button type="submit" class="bouton-principal" style="width: auto; padding: 10px 18px;">Filtrer</button>
                    <?php if ($recherche !== "" || $filtre_statut !== ""): ?>
                        <a href="admin_documents.php" class="bouton-secondaire" style="display: inline-flex; align-items: center;">Réinitialiser</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tableau complet des documents -->
            <?php if (empty($documents)): ?>
                <div style="text-align: center; padding: 40px; background: #F8FAFC; border-radius: 8px; border: 1px dashed var(--gris-bordure);">
                    <p style="font-size: 15px; color: var(--gris-secondaire); margin-bottom: 15px;">Aucun document trouvé dans le registre officiel.</p>
                    <a href="ajouter_document.php" class="bouton-principal" style="width: auto; padding: 10px 20px;">Enregistrer un premier document</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-donnees">
                        <thead>
                            <tr>
                                <th>Numéro Unique</th>
                                <th>Type de pièce</th>
                                <th>Titulaire légal</th>
                                <th>Émission</th>
                                <th>Expiration</th>
                                <th>Statut registre</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $d): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($d["numero_unique"]) ?></code></td>
                                    <td><?= htmlspecialchars($d["type_document"]) ?></td>
                                    <td><strong><?= htmlspecialchars($d["prenom_titulaire"] . " " . $d["nom_titulaire"]) ?></strong></td>
                                    <td><?= htmlspecialchars(date("d/m/Y", strtotime($d["date_emission"]))) ?></td>
                                    <td><?= !empty($d["date_expiration"]) ? htmlspecialchars(date("d/m/Y", strtotime($d["date_expiration"]))) : '<span style="color: #94A3B8;">Illimitée</span>' ?></td>
                                    <td>
                                        <?php if ($d["statut"] === "valide"): ?>
                                            <span class="pastille-statut pastille-valide">Valide</span>
                                        <?php elseif ($d["statut"] === "annule"): ?>
                                            <span class="pastille-statut pastille-expire">Annulé</span>
                                        <?php else: ?>
                                            <span class="pastille-statut pastille-faux">Falsifié</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <button type="button" class="bouton-modifier" onclick='ouvrirEditionDoc(<?= json_encode($d) ?>)'>
                                            ✏️ Éditer
                                        </button>
                                        <a href="admin_documents.php?action=supprimer&id=<?= (int)$d['id'] ?>" class="bouton-danger" onclick="return confirm('Confirmez-vous la suppression définitive du document <?= htmlspecialchars($d['numero_unique'], ENT_QUOTES) ?> ?')" style="text-decoration: none; margin-left: 6px;">
                                            🗑️ Supprimer
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <footer class="bas-de-page">
        Registre national centralisé des documents &bull; Direction Générale &bull; Modification certifiée
    </footer>

    <script>
    function ouvrirEditionDoc(doc) {
        document.getElementById('modalEditionDoc').style.display = 'block';
        document.getElementById('editer_id_doc').value = doc.id;
        document.getElementById('titreDocEdite').textContent = doc.numero_unique + ' (' + doc.prenom_titulaire + ' ' + doc.nom_titulaire + ')';
        document.getElementById('editer_type').value = doc.type_document;
        document.getElementById('editer_nom').value = doc.nom_titulaire;
        document.getElementById('editer_prenom').value = doc.prenom_titulaire;
        document.getElementById('editer_naissance').value = doc.date_naissance || '';
        document.getElementById('editer_emission').value = doc.date_emission;
        document.getElementById('editer_expiration').value = doc.date_expiration || '';
        document.getElementById('editer_statut').value = doc.statut;
        document.getElementById('modalEditionDoc').scrollIntoView({behavior: 'smooth'});
    }

    function fermerEditionDoc() {
        document.getElementById('modalEditionDoc').style.display = 'none';
    }
    </script>

</body>
</html>

