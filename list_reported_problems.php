<?php
// backend/list_reported_problems.php

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

// --- VÉRIFICATION D'AUTORISATION (utilisateur connecté ET rôle Employé) ---
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
    error_log("Erreur PDO lors de la connexion dans list_reported_problems.php: " . $e->getMessage());
    exit();
}
// --- FIN CODE DE CONNEXION ---

// --- Vérification spécifique du rôle Employé ---
$isEmployee = false;
$check_role_sql = "SELECT COUNT(*) FROM Utilisateur_Role ur JOIN Role r ON ur.role_id = r.role_id WHERE ur.utilisateur_id = :userId AND r.libelle = 'Employe'";
$check_role_stmt = $pdo->prepare($check_role_sql);
$check_role_stmt->bindParam(':userId', $loggedInUserId, PDO::PARAM_INT);
$check_role_stmt->execute();
if ($check_role_stmt->fetchColumn() > 0) {
    $isEmployee = true;
}

if (!$isEmployee) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Accès refusé. Seuls les employés peuvent accéder à cette ressource.']);
    exit();
}
// --- FIN Vérification spécifique du rôle Employé ---


// Utiliser un bloc try-catch pour le reste du script
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // --- Requête SQL pour récupérer les signalements et les informations associées ---
        $sql = "
            SELECT
                s.signalement_id,
                s.description AS description_probleme,
                s.date_signalement,
                s.statut AS statut_signalement, -- Statut du signalement lui-même
                c.covoiturage_id,
                c.lieu_depart,
                c.lieu_arrivee,
                c.date_depart,
                c.heure_depart,
                 c.heure_arrivee, -- Inclure l'heure d'arrivée
                u_passager.pseudo AS pseudo_passager,
                u_passager.email AS email_passager,
                u_chauffeur.pseudo AS pseudo_chauffeur,
                u_chauffeur.email AS email_chauffeur
            FROM Signalements s
            JOIN Participation p ON s.participation_id = p.participation_id
            JOIN Covoiturage c ON s.covoiturage_id = c.covoiturage_id
            JOIN Utilisateur u_passager ON s.utilisateur_id_auteur = u_passager.utilisateur_id -- L'auteur du signalement est le passager
            JOIN Utilisateur u_chauffeur ON c.utilisateur_id = u_chauffeur.utilisateur_id -- Le chauffeur du covoiturage
            ORDER BY s.date_signalement DESC -- Ordonner par date pour les plus récents en premier
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $reportedProblems = $stmt->fetchAll(); // Récupérer les signalements

        // Renvoyer les données au format JSON
        echo json_encode($reportedProblems);

    } else {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données lors du chargement des signalements : ' . $e->getMessage()]);
    error_log("Erreur PDO dans list_reported_problems.php: " . $e->getMessage());
} catch (Exception $e) {
    // Gérer les autres erreurs PHP
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur lors du chargement des signalements : ' . $e->getMessage()]);
    error_log("Erreur générale dans list_reported_problems.php: " . $e->getMessage());
}
?>
