<?php
// backend/get_total_credits.php

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
    echo json_encode(['error' => 'Vous devez être connecté pour accéder à cette ressource.']);
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
    echo json_encode(['error' => 'Erreur de base de données lors de la connexion.']);
    error_log("Erreur PDO lors de la connexion dans get_total_credits.php: " . $e->getMessage());
    exit();
}
// --- FIN CODE DE CONNEXION ---

// --- Vérification spécifique du rôle Administrateur ---
$isAdmin = false;
$check_role_sql = "SELECT COUNT(*) FROM Utilisateur_Role ur JOIN Role r ON ur.role_id = r.role_id WHERE ur.utilisateur_id = :userId AND r.libelle = 'Administrateur'"; // 'Administrateur' est le libellé du rôle
$check_role_stmt = $pdo->prepare($check_role_sql);
$check_role_stmt->bindParam(':userId', $loggedInUserId, PDO::PARAM_INT);
$check_role_stmt->execute();
if ($check_role_stmt->fetchColumn() === 0) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Accès refusé. Seuls les administrateurs peuvent accéder au total des crédits.']);
    exit();
}
// --- FIN Vérification spécifique du rôle Administrateur ---


// Utiliser un bloc try-catch pour le reste du script
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // --- Récupérer le total des crédits gagnés par la plateforme ---
        // Cela suppose que le gain total est la somme des prix_personne des participations validées.
        $total_credits_sql = "
            SELECT
                SUM(c.prix_personne) as total_credits_gain
            FROM Participation p
            JOIN Covoiturage c ON p.covoiturage_id = c.covoiturage_id
            WHERE p.statut = 'action_effectuée' -- Crédits gagnés lorsque l'action est effectuée
             AND c.prix_personne > 0 -- Ne prendre en compte que les participations payantes
        ";
         // Note: Adaptez cette requête si votre logique de gain total de crédits est différente.

        $total_credits_stmt = $pdo->prepare($total_credits_sql);
        $total_credits_stmt->execute();
        $total_credits = $total_credits_stmt->fetchColumn(); // Récupère le résultat agrégé

        // Renvoyer le total au format JSON
        echo json_encode([
            'total_credits' => ($total_credits !== null) ? $total_credits : 0 // Renvoyer 0 si SUM est NULL (aucune participation validée)
        ]);


    } else {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données lors du chargement du total des crédits : ' . $e->getMessage()]);
    error_log("Erreur PDO dans get_total_credits.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur lors du chargement du total des crédits : ' . $e->getMessage()]);
    error_log("Erreur générale dans get_total_credits.php: " . $e->getMessage());
}
?>
