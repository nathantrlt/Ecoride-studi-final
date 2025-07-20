<?php
// backend/check_employe_status.php

// Démarrer la session pour accéder aux variables de session
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // À ajuster pour la production
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier simplement si la variable de session 'is_employe' existe et est vraie
$isEmploye = isset($_SESSION['is_employe']) && $_SESSION['is_employe'] === true;

// Renvoyer le statut d'employé au frontend
echo json_encode(['is_employe' => $isEmploye]);

?>
