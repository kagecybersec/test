<?php
require "config.php";

// Protection de session
if (!isset($_SESSION["agent_id"])) {
    header("Location: login.php");
    exit;
}

$is_admin = ($_SESSION["agent_role"] ?? "") === "admin";

// Export CSV à la demande
if (isset($_GET["export"]) && $_GET["export"] === "csv") {
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"historique_verifications_" . date("Ymd_His") . ".csv\"");
    
    $output = fopen("php://output", "w");
    // BOM UTF-8 pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ["ID", "Date et Heure", "Agent", "Numero de Document", "Resultat"], ";");

    $stmt_csv = $pdo->query("SELECT v.id, v.date_verification, CONCAT(a.prenom, ' ', a.nom) AS agent, v.numero_unique_saisi, v.resultat 
                             FROM verifications v 
                             JOIN agents a ON v.agent_id = a.id 
                             ORDER BY v.date_verification DESC");
    while ($row = $stmt_csv->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row["id"],
            date("d/m/Y H:i:s", strtotime($row["date_verification"])),
            $row["agent"],
            $row["numero_unique_saisi"],
            $row["resultat"]
        ], ";");
    }
    fclose($output);
    exit;
}

// Filtres de recherche
$recherche = strtoupper(trim($_GET["q"] ?? ""));
$filtre_statut = trim($_GET["statut"] ?? "");

// Construction de la requête filtrée
$sql = "SELECT v.*, a.nom AS agent_nom, a.prenom AS agent_prenom
        FROM verifications v
        JOIN agents a ON v.agent_id = a.id
        WHERE 1=1";
$params = [];

if ($recherche !== "") {
    $sql .= " AND v.numero_unique_saisi LIKE ?";
    $params[] = "%" . $recherche . "%";
}

if ($filtre_statut !== "") {
    $sql .= " AND v.resultat = ?";
    $params[] = $filtre_statut;
}

$sql .= " ORDER BY v.date_verification DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales
$stats = [
    "total" => 0,
    "authentique" => 0,
    "expire" => 0,
    "faux" => 0,
    "inexistant" => 0
];

$res_stats = $pdo->query("SELECT resultat, COUNT(*) as c FROM verifications GROUP BY resultat")->fetchAll(PDO::FETCH_ASSOC);
foreach ($res_stats as $s) {
    $stats["total"] += $s["c"];
    if ($s["resultat"] === "AUTHENTIQUE") $stats["authentique"] = $s["c"];
    elseif ($s["resultat"] === "EXPIRÉ") $stats["expire"] = $s["c"];
    elseif ($s["resultat"] === "FAUX DOCUMENT") $stats["faux"] = $s["c"];
    elseif ($s["resultat"] === "INEXISTANT") $stats["inexistant"] = $s["c"];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Contrôles - Plateforme de Vérification</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header class="barre-titre">
        <a href="verifier.php" class="logo-groupe">
            <span class="logo-icone">🛡️</span>
            <h1>Plateforme de Vérification</h1>
            <span class="badge-ministere">DGTTC</span>
        </a>

        <nav class="nav-onglets">
            <a href="verifier.php">🔍 Vérifier</a>
            <a href="historique.php" class="actif">📋 Historique</a>
            <a href="ajouter_document.php">➕ Nouveau document</a>
            <?php if ($is_admin): ?>
                <a href="admin.php" class="onglet-admin">👑 Administration</a>
            <?php endif; ?>
        </nav>

        <div class="agent-zone">
            <div class="agent-info">
                <span class="point-vert"></span>
                <span><?= $is_admin ? "👑 " : "" ?><?= htmlspecialchars($_SESSION["agent_nom"]) ?><?= $is_admin ? " (Admin)" : "" ?></span>
            </div>
            <a href="logout.php" class="deconnexion">Déconnexion</a>
        </div>
    </header>

    <div class="conteneur conteneur-plein">
        <div class="en-tete">
            <h2>📋 Registre & Journal d'Audit des Vérifications</h2>
            <a href="historique.php?export=csv" class="bouton-secondaire" style="background: rgba(255,255,255,0.25); color: #FFF; border: none; font-weight: 700;">
                📥 Exporter en CSV (Excel)
            </a>
        </div>
        <div class="corps">

            <!-- Cartes statistiques -->
            <div class="grille-stats">
                <div class="carte-stat">
                    <div class="icone-stat">📊</div>
                    <div class="info-stat">
                        <div class="valeur-stat"><?= (int)$stats["total"] ?></div>
                        <div class="titre-stat">Total Contrôles</div>
                    </div>
                </div>
                <div class="carte-stat">
                    <div class="icone-stat" style="color: var(--vert);">✅</div>
                    <div class="info-stat">
                        <div class="valeur-stat" style="color: var(--vert-fonce);"><?= (int)$stats["authentique"] ?></div>
                        <div class="titre-stat">Authentiques</div>
                    </div>
                </div>
                <div class="carte-stat">
                    <div class="icone-stat" style="color: var(--jaune);">⚠️</div>
                    <div class="info-stat">
                        <div class="valeur-stat" style="color: var(--jaune-fonce);"><?= (int)$stats["expire"] ?></div>
                        <div class="titre-stat">Expirés</div>
                    </div>
                </div>
                <div class="carte-stat">
                    <div class="icone-stat" style="color: var(--rouge);">🚫</div>
                    <div class="info-stat">
                        <div class="valeur-stat" style="color: var(--rouge);"><?= (int)($stats["faux"] + $stats["inexistant"]) ?></div>
                        <div class="titre-stat">Alertes Faux / Inexistants</div>
                    </div>
                </div>
            </div>

            <!-- Barre de filtrage et recherche -->
            <div class="barre-outils-table">
                <form method="GET" action="historique.php">
                    <input type="text" name="q" placeholder="Rechercher par numéro (ex: DOC-2024)..." 
                           value="<?= htmlspecialchars($recherche) ?>" 
                           style="padding: 10px 14px; border: 2px solid var(--gris-bordure); border-radius: 8px; flex: 1;">
                    
                    <select name="statut" style="padding: 10px 12px; border: 2px solid var(--gris-bordure); border-radius: 8px; background: #FFF;">
                        <option value="">Tous les résultats</option>
                        <option value="AUTHENTIQUE" <?= $filtre_statut === 'AUTHENTIQUE' ? 'selected' : '' ?>>Authentique</option>
                        <option value="EXPIRÉ" <?= $filtre_statut === 'EXPIRÉ' ? 'selected' : '' ?>>Expiré</option>
                        <option value="FAUX DOCUMENT" <?= $filtre_statut === 'FAUX DOCUMENT' ? 'selected' : '' ?>>Faux document</option>
                        <option value="INEXISTANT" <?= $filtre_statut === 'INEXISTANT' ? 'selected' : '' ?>>Inexistant</option>
                    </select>

                    <button type="submit" class="bouton-principal" style="width: auto; padding: 10px 18px;">Filtrer</button>
                    <?php if ($recherche !== "" || $filtre_statut !== ""): ?>
                        <a href="historique.php" class="bouton-secondaire" style="display: inline-flex; align-items: center;">Réinitialiser</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tableau des contrôles -->
            <?php if (empty($verifications)): ?>
                <div style="text-align: center; padding: 40px; background: #F8FAFC; border-radius: 8px; border: 1px dashed var(--gris-bordure);">
                    <p style="font-size: 16px; color: var(--gris-secondaire); margin-bottom: 15px;">Aucune vérification enregistrée correspondant aux critères.</p>
                    <a href="verifier.php" class="bouton-principal" style="width: auto; padding: 10px 20px;">Effectuer un contrôle</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-donnees">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date & Heure</th>
                                <th>Agent vérificateur</th>
                                <th>Numéro du document</th>
                                <th>Verdict officiel</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($verifications as $v): ?>
                                <tr>
                                    <td><strong>#<?= (int)$v["id"] ?></strong></td>
                                    <td><?= htmlspecialchars(date("d/m/Y H:i:s", strtotime($v["date_verification"]))) ?></td>
                                    <td>👤 <?= htmlspecialchars($v["agent_prenom"] . " " . $v["agent_nom"]) ?></td>
                                    <td><code><?= htmlspecialchars($v["numero_unique_saisi"]) ?></code></td>
                                    <td>
                                        <?php if ($v["resultat"] === "AUTHENTIQUE"): ?>
                                            <span class="pastille-statut pastille-valide">● AUTHENTIQUE</span>
                                        <?php elseif ($v["resultat"] === "EXPIRÉ"): ?>
                                            <span class="pastille-statut pastille-expire">● EXPIRÉ</span>
                                        <?php elseif ($v["resultat"] === "FAUX DOCUMENT"): ?>
                                            <span class="pastille-statut pastille-faux">● FAUX DOCUMENT</span>
                                        <?php else: ?>
                                            <span class="pastille-statut pastille-inexistant">● INEXISTANT</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <form method="POST" action="verifier.php" style="display: inline;">
                                            <input type="hidden" name="numero_unique" value="<?= htmlspecialchars($v["numero_unique_saisi"]) ?>">
                                            <button type="submit" class="bouton-table-action" title="Recharger dans l'analyseur">
                                                Re-contrôler
                                            </button>
                                        </form>
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
        Journal d'audit horodaté non altérable &bull; Direction Générale &bull; Décret portant vérification des titres
    </footer>

</body>
</html>

