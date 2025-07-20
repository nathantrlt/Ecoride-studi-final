<?php
// backend/get_pending_reviews.php

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

// --- DÉBUT DE LA VÉRIFICATION D'AUTORISATION ---
// Vérifier si l'utilisateur est connecté ET s'il a le rôle d'employé
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_employe']) || !$_SESSION['is_employe']) {
    // Si l'utilisateur n'est pas connecté ou n'est pas un employé
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Accès refusé. Vous devez être connecté en tant qu\'employé pour accéder à cette ressource.']);
    exit(); // Arrêter l'exécution du script
}
// --- FIN DE LA VÉRIFICATION D'AUTORISATION ---



// Utiliser un bloc try-catch pour intercepter les erreurs
try {
    // --- Début de la logique de base de données (inchangée) ---

    // Paramètres de connexion à la base de données (adaptez à votre configuration)
    $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com';
    $db_name = 'ecoride_db';
    $db_user = 'Nathan';
    $db_pass = 'Af18PsKCc-';

    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Requête SQL pour sélectionner les avis en attente de validation
    // Assurez-vous que les noms de table et de colonnes correspondent à votre base de données
    $sql = "SELECT
                a.avis_id,
                a.commentaire,
                a.note, -- Inclure la note si pertinente
                u_emetteur.pseudo AS reviewer_pseudo,
                u_destinataire.pseudo AS driver_pseudo -- ou le pseudo de celui qui reçoit l'avis
            FROM Avis a
            JOIN Utilisateur u_emetteur ON a.utilisateur_id_auteur = u_emetteur.utilisateur_id
            JOIN Utilisateur u_destinataire ON a.utilisateur_id_destinataire = u_destinataire.utilisateur_id
            WHERE a.statut = 'En attente de validation'"; // Assurez-vous que le nom de la colonne statut et la valeur 'en_attente' sont corrects

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $pendingReviews = $stmt->fetchAll(); // Récupérer tous les avis en attente

    // Renvoyer les avis au format JSON
    echo json_encode($pendingReviews);

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données lors du chargement des avis : ' . $e->getMessage()]);
} catch (Exception $e) {
    // Gérer les autres erreurs PHP
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur lors du chargement des avis : ' . $e->getMessage()]);
}
?>
