<?php
// backend/deconnexion.php

session_start();
$_SESSION = array();

// Si vous utilisez aussi des cookies de session, détruisez le cookie.
// Note: Cela détruira le cookie de session, mais pas les cookies spécifiques à l'utilisateur que vous pourriez avoir définis manuellement.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
// Finalement, détruire la session.
session_destroy();

// Rediriger l'utilisateur vers la page d'accueil ou la page de connexion
echo "Déconnexion en cours... Redirection."; // Temporaire pour le débug
header("Location: ../index.html"); // Rediriger vers la page d'accueil (adaptez le chemin si nécessaire)
exit(); 
?>