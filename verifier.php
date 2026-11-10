<?php
require "config.php";

// Protection : seul un agent connecté peut accéder à cette page
if (!isset($_SESSION["agent_id"])) {
    header("Location: login.php");
    exit;
}

$is_admin = ($_SESSION["agent_role"] ?? "") === "admin";
$resultat_html = "";
$numero_saisi = "";
$verif_id = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["numero_unique"])) {
    $numero_saisi = strtoupper(trim($_POST["numero_unique"]));

    if ($numero_saisi === "") {
        $resultat_html = "<div class='message-erreur'>⚠️ Veuillez saisir un numéro unique de document.</div>";
    } else {
        // 1. Recherche du document dans la base de données
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE numero_unique = ?");
        $stmt->execute([$numero_saisi]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Détermination rigoureuse du statut
        $aujourdhui = date("Y-m-d");
        $date_controle = date("d/m/Y à H:i:s");

        if (!$document) {
            $resultat = "INEXISTANT";
        } elseif ($document["statut"] === "valide") {
            // Prise en compte de la date d'expiration
            if (!empty($document["date_expiration"]) && $document["date_expiration"] < $aujourdhui) {
                $resultat = "EXPIRÉ";
            } else {
                $resultat = "AUTHENTIQUE";
            }
        } else {
            $resultat = "FAUX DOCUMENT";
        }

        // 3. Enregistrement dans l'historique d'audit (traçabilité juridique)
        $stmt_log = $pdo->prepare(
            "INSERT INTO verifications (agent_id, numero_unique_saisi, resultat) VALUES (?, ?, ?)"
        );
        $stmt_log->execute([$_SESSION["agent_id"], $numero_saisi, $resultat]);
        $verif_id = $pdo->lastInsertId();

        // 4. Préparation du certificat d'affichage
        if ($resultat === "AUTHENTIQUE") {
            $date_exp = !empty($document['date_expiration']) ? date('d/m/Y', strtotime($document['date_expiration'])) : 'Illimitée (Pas d\'expiration)';
            $resultat_html = "
            <div class='resultat authentique' id='zoneAttestation'>
                <div class='resultat-entete-badge'>
                    <span>✅</span> ATTESTATION : DOCUMENT AUTHENTIQUE & CONFORME
                </div>
                <div class='details-document'>
                    <p><span class='cle'>Réf. Contrôle :</span> <span class='valeur'>#VERIF-" . str_pad($verif_id, 6, "0", STR_PAD_LEFT) . "</span></p>
                    <p><span class='cle'>Date du contrôle :</span> <span class='valeur'>" . $date_controle . "</span></p>
                    <p><span class='cle'>Numéro officiel :</span> <span class='valeur'><strong>" . htmlspecialchars($document['numero_unique']) . "</strong></span></p>
                    <p><span class='cle'>Type de pièce :</span> <span class='valeur'>" . htmlspecialchars($document['type_document']) . "</span></p>
                    <p><span class='cle'>Titulaire légal :</span> <span class='valeur'>" . htmlspecialchars($document['prenom_titulaire'] . " " . $document['nom_titulaire']) . "</span></p>
                    <p><span class='cle'>Date de naissance :</span> <span class='valeur'>" . (!empty($document['date_naissance']) ? date('d/m/Y', strtotime($document['date_naissance'])) : 'Non renseignée') . "</span></p>
                    <p><span class='cle'>Date d'émission :</span> <span class='valeur'>" . date('d/m/Y', strtotime($document['date_emission'])) . "</span></p>
                    <p><span class='cle'>Date d'expiration :</span> <span class='valeur'>" . $date_exp . "</span></p>
                    <p><span class='cle'>Statut juridique :</span> <span class='valeur' style='color: var(--vert-fonce); font-weight:800;'>VALIDE ET EN VIGUEUR</span></p>
                </div>
                <div class='actions-resultat'>
                    <button type='button' class='btn-action-res btn-imprimer' onclick='window.print()'>🖨️ Imprimer l'attestation</button>
                    <a href='verifier.php' class='btn-action-res btn-nouveau'>🔄 Nouveau contrôle</a>
                </div>
            </div>";
        } elseif ($resultat === "EXPIRÉ") {
            $resultat_html = "
            <div class='resultat expire' id='zoneAttestation'>
                <div class='resultat-entete-badge'>
                    <span>⚠️</span> ALERTE : DOCUMENT ENREGISTRÉ MAIS EXPIRÉ
                </div>
                <div class='details-document'>
                    <p><span class='cle'>Réf. Contrôle :</span> <span class='valeur'>#VERIF-" . str_pad($verif_id, 6, "0", STR_PAD_LEFT) . "</span></p>
                    <p><span class='cle'>Numéro vérifié :</span> <span class='valeur'>" . htmlspecialchars($document['numero_unique']) . "</span></p>
                    <p><span class='cle'>Type de pièce :</span> <span class='valeur'>" . htmlspecialchars($document['type_document']) . "</span></p>
                    <p><span class='cle'>Titulaire :</span> <span class='valeur'>" . htmlspecialchars($document['prenom_titulaire'] . " " . $document['nom_titulaire']) . "</span></p>
                    <p><span class='cle'>Date d'expiration :</span> <span class='valeur' style='color: var(--jaune-fonce); font-weight: 800;'>" . date('d/m/Y', strtotime($document['date_expiration'])) . " (ÉCHUE)</span></p>
                    <p><span class='cle'>Conclusion :</span> <span class='valeur'>Le document existe dans le registre mais n'est plus valable juridiquement.</span></p>
                </div>
                <div class='actions-resultat'>
                    <button type='button' class='btn-action-res btn-imprimer' onclick='window.print()'>🖨️ Imprimer le rapport</button>
                    <a href='verifier.php' class='btn-action-res btn-nouveau'>🔄 Nouveau contrôle</a>
                </div>
            </div>";
        } elseif ($resultat === "FAUX DOCUMENT") {
            $motif = ($document['statut'] === 'annule') ? "Document RÉVOQUÉ / ANNULÉ par l'autorité émettrice" : "Document classifié FALSIFIÉ";
            $resultat_html = "
            <div class='resultat faux' id='zoneAttestation'>
                <div class='resultat-entete-badge'>
                    <span>🚫</span> ALERTE DE SÉCURITÉ : FAUX DOCUMENT / NON CONFORME
                </div>
                <div class='details-document'>
                    <p><span class='cle'>Réf. Contrôle :</span> <span class='valeur'>#VERIF-" . str_pad($verif_id, 6, "0", STR_PAD_LEFT) . "</span></p>
                    <p><span class='cle'>Numéro saisi :</span> <span class='valeur'><strong>" . htmlspecialchars($document['numero_unique']) . "</strong></span></p>
                    <p><span class='cle'>Motif d'alerte :</span> <span class='valeur' style='color: var(--rouge); font-weight: 800;'>" . htmlspecialchars($motif) . "</span></p>
                    <p><span class='cle'>Titulaire présumé :</span> <span class='valeur'>" . htmlspecialchars($document['prenom_titulaire'] . " " . $document['nom_titulaire']) . "</span></p>
                    <p><span class='cle'>Statut registre :</span> <span class='valeur' style='color: var(--rouge); font-weight: 800;'>" . strtoupper(htmlspecialchars($document['statut'])) . "</span></p>
                </div>
                <div class='actions-resultat'>
                    <button type='button' class='btn-action-res btn-imprimer' onclick='window.print()'>🖨️ Imprimer la notification d'infraction</button>
                    <a href='verifier.php' class='btn-action-res btn-nouveau'>🔄 Nouveau contrôle</a>
                </div>
            </div>";
        } else {
            $resultat_html = "
            <div class='resultat inexistant' id='zoneAttestation'>
                <div class='resultat-entete-badge'>
                    <span>❌</span> NUMÉRO INEXISTANT DANS LE REGISTRE
                </div>
                <div class='details-document'>
                    <p><span class='cle'>Numéro saisi :</span> <span class='valeur'><code>" . htmlspecialchars($numero_saisi) . "</code></span></p>
                    <p><span class='cle'>Constat :</span> <span class='valeur'>Aucun enregistrement officiel ne correspond à ce numéro.</span></p>
                    <p><span class='cle'>Recommandation :</span> <span class='valeur'>Refuser le document ou procéder à des investigations complémentaires.</span></p>
                </div>
                <div class='actions-resultat'>
                    <a href='verifier.php' class='btn-action-res btn-nouveau'>🔄 Nouveau contrôle</a>
                </div>
            </div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de Document - DGTTC</title>
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
            <a href="verifier.php" class="actif">🔍 Vérifier</a>
            <a href="historique.php">📋 Historique</a>
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

    <div class="conteneur">
        <div class="en-tete">
            <h2>🔍 Contrôle d'authenticité d'un document</h2>
        </div>
        <div class="corps">
            <form method="POST" action="verifier.php">
                <div class="groupe-champ">
                    <label for="numero_unique">Numéro unique d'identification du document</label>
                    <input type="text" id="numero_unique" name="numero_unique"
                           placeholder="Ex : DOC-2024-000123"
                           value="<?= htmlspecialchars($numero_saisi) ?>"
                           style="text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;"
                           oninput="this.value = this.value.toUpperCase()"
                           required autofocus autocomplete="off">
                </div>
                <button type="submit" class="bouton-principal">Lancer l'analyse du document</button>
            </form>

            <div class="aide-exemples">
                <span>Échantillons de test :</span>
                <button type="button" class="badge-exemple" onclick="remplir('DOC-2024-000123')">CNI (Valide)</button>
                <button type="button" class="badge-exemple" onclick="remplir('DOC-2023-000045')">Naissance (Valide)</button>
                <button type="button" class="badge-exemple" onclick="remplir('DOC-2020-000555')">Permis (Expiré)</button>
                <button type="button" class="badge-exemple" onclick="remplir('DOC-2022-000987')">BTS (Falsifié)</button>
            </div>

            <?= $resultat_html ?>
        </div>
    </div>

    <footer class="bas-de-page">
        Système officiel de traçabilité des pièces d'identité et actes administratifs &bull; Direction Générale
    </footer>

    <script>
    function remplir(code) {
        var input = document.getElementById('numero_unique');
        input.value = code;
        input.focus();
    }
    </script>

</body>
</html>
