<?php
// backend/notation_et_commentaire.php

// Démarrer la session pour accéder aux variables de session
session_start();

// Afficher toutes les erreurs PHP pour le débogage (en développement)
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

// --- VÉRIFICATION D'AUTORISATION (utilisateur connecté) ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Vous devez être connecté pour voir vos actions en attente.']);
    exit();
}
$loggedInUserId = $_SESSION['user_id'];
// --- FIN DE LA VÉRIFICATION D'AUTORISATION ---


// Utiliser un bloc try-catch
try {
    // Connexion à la base de données
    $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com';
    $db_name = 'ecoride_db';
    $db_user = 'Nathan';
    $db_pass = 'Af18PsKCc-';

    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- Requête SQL pour récupérer les trajets terminés nécessitant une action du participant ---
    // Sélectionner les trajets où l'utilisateur est participant ET le statut du trajet est 'Terminé'
    // ET le statut de la participation est 'action_en_attente' (ou le nom que vous avez choisi).
    $sql = "
        SELECT
            c.covoiturage_id,
            c.lieu_depart,
            c.lieu_arrivee,
            c.date_depart,
            c.heure_depart,
            u_chauffeur.pseudo AS driver_pseudo,
            u_chauffeur.utilisateur_id AS driver_user_id, -- Pour pouvoir associer l'avis au bon destinataire
            p.participation_id -- Inclure l'ID de la participation, utile pour mettre à jour son statut
        FROM Participation p
        JOIN Covoiturage c ON p.covoiturage_id = c.covoiturage_id
        JOIN Utilisateur u_chauffeur ON c.utilisateur_id = u_chauffeur.utilisateur_id -- Rejoindre pour obtenir le chauffeur
        WHERE p.utilisateur_id = :loggedInUserId -- L'utilisateur connecté est participant
        AND c.statut = 'Terminé' -- Le trajet est terminé
        AND p.statut = 'action_en_attente' -- *** Condition mise à jour : Statut de la participation indiquant une action en attente ***
        ORDER BY c.date_depart DESC, c.heure_depart DESC -- Ordre d'affichage
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':loggedInUserId', $loggedInUserId, PDO::PARAM_INT); // Lier l'ID de l'utilisateur connecté
    $stmt->execute();

    $pendingActions = $stmt->fetchAll(); // Récupérer les trajets nécessitant une action

    // Renvoyer les données au format JSON
    echo json_encode($pendingActions);

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données lors du chargement des actions en attente : ' . $e->getMessage()]);
} catch (Exception $e) {
    // Gérer les autres erreurs PHP
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur lors du chargement des actions en attente : ' . $e->getMessage()]);
}
?>
