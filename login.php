<?php
require "config.php";

// Si déjà connecté, redirige vers la bonne interface selon le rôle
if (isset($_SESSION["agent_id"])) {
    if (($_SESSION["agent_role"] ?? "") === "admin") {
        header("Location: admin.php");
    } else {
        header("Location: verifier.php");
    }
    exit;
}

$erreur = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $mot_de_passe = $_POST["mot_de_passe"] ?? "";

    if ($email === "" || $mot_de_passe === "") {
        $erreur = "Veuillez renseigner votre email et mot de passe.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM agents WHERE email = ?");
        $stmt->execute([$email]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($agent && password_verify($mot_de_passe, $agent["mot_de_passe"])) {
            // Protection contre la fixation de session
            session_regenerate_id(true);

            // Enregistrement des informations de session
            $_SESSION["agent_id"] = $agent["id"];
            $_SESSION["agent_nom"] = $agent["prenom"] . " " . $agent["nom"];
            $_SESSION["agent_email"] = $agent["email"];
            $_SESSION["agent_role"] = $agent["role"] ?? "agent"; // 'admin' ou 'agent'

            if ($_SESSION["agent_role"] === "admin") {
                header("Location: admin.php");
            } else {
                header("Location: verifier.php");
            }
            exit;
        } else {
            $erreur = "Identifiants invalides. Vérifiez votre email et mot de passe.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Plateforme de Vérification</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header class="barre-titre">
        <div class="logo-groupe">
            <span class="logo-icone">🛡️</span>
            <h1>Plateforme de Vérification</h1>
            <span class="badge-ministere">DGTTC</span>
        </div>
        <div class="agent-zone">
            <span style="font-size: 13px; opacity: 0.9;">Accès Sécurisé Réservé aux Agents & Administrateurs</span>
        </div>
    </header>

    <div class="conteneur">
        <div class="en-tete">
            <h2>🔐 Authentification</h2>
        </div>
        <div class="corps">
            <?php if ($erreur): ?>
                <div class="message-erreur">
                    <span>⚠️</span>
                    <span><?= htmlspecialchars($erreur) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" autocomplete="on">
                <div class="groupe-champ">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($email) ?>"
                           placeholder="agent@plateforme.com" required autofocus>
                </div>

                <div class="groupe-champ">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe"
                           placeholder="••••••••" required>
                    <button type="button" class="btn-toggle-mdp" id="btnToggleMdp" onclick="toggleMdp()" title="Afficher/Masquer le mot de passe">
                        👁️
                    </button>
                </div>

                <button type="submit" class="bouton-principal">Se connecter</button>
            </form>

            <div class="aide-exemples" style="margin-top: 22px;">
                <span>Accès rapides de démonstration :</span>
                <button type="button" class="badge-exemple" onclick="remplir('admin@plateforme.com', 'admin123')">
                    👑 Admin (admin@plateforme.com)
                </button>
                <button type="button" class="badge-exemple" onclick="remplir('agent@plateforme.com', 'agent123')">
                    👤 Agent (agent@plateforme.com)
                </button>
            </div>
        </div>
    </div>

    <footer class="bas-de-page">
        Plateforme de Vérification des Documents Administratifs &bull; Direction Générale &bull; Système officiel sécurisé
    </footer>

    <script>
    function toggleMdp() {
        var input = document.getElementById('mot_de_passe');
        var btn = document.getElementById('btnToggleMdp');
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = '🔒';
        } else {
            input.type = 'password';
            btn.textContent = '👁️';
        }
    }

    function remplir(email, mdp) {
        document.getElementById('email').value = email;
        document.getElementById('mot_de_passe').value = mdp;
    }
    </script>

</body>
</html>
