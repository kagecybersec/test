<?php
require "config.php";

// Protection : seul un agent connecté peut enregistrer un document
if (!isset($_SESSION["agent_id"])) {
    header("Location: login.php");
    exit;
}

$is_admin = ($_SESSION["agent_role"] ?? "") === "admin";

$message = "";
$type_message = "";

$numero_unique = "";
$type_document = "Carte Nationale d'Identité";
$nom_titulaire = "";
$prenom_titulaire = "";
$date_naissance = "";
$date_emission = date("Y-m-d");
$date_expiration = date("Y-m-d", strtotime("+10 years"));
$statut = "valide";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $numero_unique = strtoupper(trim($_POST["numero_unique"] ?? ""));
    $type_document = trim($_POST["type_document"] ?? "");
    $nom_titulaire = trim($_POST["nom_titulaire"] ?? "");
    $prenom_titulaire = trim($_POST["prenom_titulaire"] ?? "");
    $date_naissance = !empty($_POST["date_naissance"]) ? $_POST["date_naissance"] : null;
    $date_emission = trim($_POST["date_emission"] ?? "");
    $date_expiration = !empty($_POST["date_expiration"]) ? $_POST["date_expiration"] : null;
    $statut = trim($_POST["statut"] ?? "valide");

    if ($numero_unique === "" || $type_document === "" || $nom_titulaire === "" || $prenom_titulaire === "" || $date_emission === "") {
        $message = "Veuillez renseigner tous les champs obligatoires marqués d'une étoile (*).";
        $type_message = "erreur";
    } else {
        // Vérification d'unicité du numéro
        $check = $pdo->prepare("SELECT id FROM documents WHERE numero_unique = ?");
        $check->execute([$numero_unique]);

        if ($check->fetch()) {
            $message = "Le numéro de document <strong>" . htmlspecialchars($numero_unique) . "</strong> est déjà enregistré dans le registre officiel.";
            $type_message = "erreur";
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO documents (numero_unique, type_document, nom_titulaire, prenom_titulaire, date_naissance, date_emission, date_expiration, statut)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $numero_unique,
                $type_document,
                $nom_titulaire,
                $prenom_titulaire,
                $date_naissance,
                $date_emission,
                $date_expiration,
                $statut
            ]);

            $message = "Le document <strong>" . htmlspecialchars($numero_unique) . "</strong> a été enregistré avec succès dans le registre officiel !";
            $type_message = "succes";

            // Réinitialisation partielle
            $numero_unique = "";
            $nom_titulaire = "";
            $prenom_titulaire = "";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enregistrer un Document - Plateforme de Vérification</title>
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
            <a href="historique.php">📋 Historique</a>
            <a href="ajouter_document.php" class="actif">➕ Nouveau document</a>
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

    <div class="conteneur conteneur-large">
        <div class="en-tete">
            <h2>➕ Enregistrement d'un Document au Registre</h2>
        </div>
        <div class="corps">

            <?php if ($message): ?>
                <div class="<?= $type_message === 'succes' ? 'message-succes' : 'message-erreur' ?>">
                    <span><?= $type_message === 'succes' ? '✅' : '⚠️' ?></span>
                    <div><?= $message ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="ajouter_document.php">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="groupe-champ" style="grid-column: span 2;">
                        <label for="numero_unique">Numéro unique officiel *</label>
                        <div class="champ-avec-bouton">
                            <input type="text" id="numero_unique" name="numero_unique"
                                   placeholder="Ex : DOC-2026-004589"
                                   value="<?= htmlspecialchars($numero_unique) ?>"
                                   style="text-transform: uppercase; font-weight: bold;"
                                   oninput="this.value = this.value.toUpperCase()" required autofocus>
                            <button type="button" class="bouton-secondaire" onclick="genererNumero()" style="white-space: nowrap;">
                                🎲 Générer un ID
                            </button>
                        </div>
                    </div>

                    <div class="groupe-champ" style="grid-column: span 2;">
                        <label for="type_document">Type de document *</label>
                        <select id="type_document" name="type_document" required>
                            <option value="Carte Nationale d'Identité" <?= $type_document === "Carte Nationale d'Identité" ? 'selected' : '' ?>>Carte Nationale d'Identité</option>
                            <option value="Passeport Biométrique" <?= $type_document === "Passeport Biométrique" ? 'selected' : '' ?>>Passeport Biométrique</option>
                            <option value="Acte de Naissance" <?= $type_document === "Acte de Naissance" ? 'selected' : '' ?>>Acte de Naissance</option>
                            <option value="Permis de Conduire" <?= $type_document === "Permis de Conduire" ? 'selected' : '' ?>>Permis de Conduire</option>
                            <option value="Diplôme d'État / Universitaire" <?= $type_document === "Diplôme d'État / Universitaire" ? 'selected' : '' ?>>Diplôme d'État / Universitaire</option>
                            <option value="Certificat de Nationalité" <?= $type_document === "Certificat de Nationalité" ? 'selected' : '' ?>>Certificat de Nationalité</option>
                        </select>
                    </div>

                    <div class="groupe-champ">
                        <label for="nom_titulaire">Nom du titulaire *</label>
                        <input type="text" id="nom_titulaire" name="nom_titulaire"
                               value="<?= htmlspecialchars($nom_titulaire) ?>" placeholder="Ex: Koffi" required>
                    </div>

                    <div class="groupe-champ">
                        <label for="prenom_titulaire">Prénom(s) du titulaire *</label>
                        <input type="text" id="prenom_titulaire" name="prenom_titulaire"
                               value="<?= htmlspecialchars($prenom_titulaire) ?>" placeholder="Ex: Marie Ange" required>
                    </div>

                    <div class="groupe-champ">
                        <label for="date_naissance">Date de naissance</label>
                        <input type="date" id="date_naissance" name="date_naissance" value="<?= htmlspecialchars($date_naissance ?? '') ?>">
                    </div>

                    <div class="groupe-champ">
                        <label for="date_emission">Date d'émission légale *</label>
                        <input type="date" id="date_emission" name="date_emission" value="<?= htmlspecialchars($date_emission) ?>" required>
                    </div>

                    <div class="groupe-champ">
                        <label for="date_expiration">Date d'expiration (laisser vide si illimitée)</label>
                        <input type="date" id="date_expiration" name="date_expiration" value="<?= htmlspecialchars($date_expiration ?? '') ?>">
                    </div>

                    <div class="groupe-champ">
                        <label for="statut">Statut administratif initial *</label>
                        <select id="statut" name="statut" required>
                            <option value="valide" <?= $statut === 'valide' ? 'selected' : '' ?>>Valide (Authentique en règle)</option>
                            <option value="annule" <?= $statut === 'annule' ? 'selected' : '' ?>>Annulé / Révoqué</option>
                            <option value="falsifie" <?= $statut === 'falsifie' ? 'selected' : '' ?>>Classifié Falsifié</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <button type="submit" class="bouton-principal">Enregistrer la pièce au registre officiel</button>
                </div>
            </form>

        </div>
    </div>

    <footer class="bas-de-page">
        Système d'enregistrement sécurisé &bull; Direction Générale des Titres &bull; Décret portant registre national
    </footer>

    <script>
    function genererNumero() {
        var annee = new Date().getFullYear();
        var alea = Math.floor(100000 + Math.random() * 900000);
        document.getElementById('numero_unique').value = 'DOC-' + annee + '-' + alea;
    }
    </script>

</body>
</html>

