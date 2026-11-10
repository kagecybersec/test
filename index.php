<?php
require "config.php";

// Redirection automatique selon le statut et le rôle de l'utilisateur
if (isset($_SESSION["agent_id"])) {
    if (($_SESSION["agent_role"] ?? "") === "admin") {
        header("Location: admin.php");
    } else {
        header("Location: verifier.php");
    }
} else {
    header("Location: login.php");
}
exit;
