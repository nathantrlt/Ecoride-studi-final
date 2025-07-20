<?php
// backend/check_admin_status.php

session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // À ajuster pour la production
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- VÉRIFICATION D'AUTORISATION (utilisateur connecté ET rôle Administrateur) ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['is_admin' => false, 'error' => 'Vous devez être connecté.']);
    exit();
}
$loggedInUserId = $_SESSION['user_id'];

// --- CODE DE CONNEXION À LA BASE DE DONNÉES ---
// Inclure votre fichier de connexion OU copier le code de connexion ici
// require_once 'db_connect.php'; // Si vous utilisez un fichier séparé
// OU copiez le code de connexion ici :
$db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com';
$db_name = 'ecoride_db';
$db_user = 'Nathan';
$db_pass = 'Af18PsKCc-';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['is_admin' => false, 'error' => 'Erreur de base de données lors de la connexion.']);
    error_log("Erreur PDO lors de la connexion dans check_admin_status.php: " . $e->getMessage());
    exit();
}
// --- FIN CODE DE CONNEXION ---

// --- Vérification spécifique du rôle Administrateur ---
$isAdmin = false;
$check_role_sql = "SELECT COUNT(*) FROM Utilisateur_Role ur JOIN Role r ON ur.role_id = r.role_id WHERE ur.utilisateur_id = :userId AND r.libelle = 'Administrateur'"; // 'Administrateur' est le libellé du rôle
$check_role_stmt = $pdo->prepare($check_role_sql);
$check_role_stmt->bindParam(':userId', $loggedInUserId, PDO::PARAM_INT);
$check_role_stmt->execute();
if ($check_role_stmt->fetchColumn() > 0) {
    $isAdmin = true;
}

// Renvoyer l'état d'administrateur
echo json_encode(['is_admin' => $isAdmin]);

?>
