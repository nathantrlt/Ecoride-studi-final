<?php
// backend/reject_review.php

// Démarrer la session pour accéder aux variables de session
session_start();

// Afficher toutes les erreurs PHP pour le débogage (en développement)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // À ajuster pour la production
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Autoriser les requêtes POST
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- VÉRIFICATION D'AUTORISATION DE L'EMPLOYÉ ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_employe']) || !$_SESSION['is_employe']) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Accès refusé. Vous devez être connecté en tant qu\'employé pour refuser un avis.']);
    exit();
}
// --- FIN DE LA VÉRIFICATION D'AUTORISATION ---


// Utiliser un bloc try-catch
try {
    // Assurez-vous que la méthode de requête est POST et que l'ID de l'avis est présent
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON : ' . json_last_error_msg());
        }

        if (!isset($data['review_id']) || !is_numeric($data['review_id'])) {
             throw new Exception('ID de l\'avis manquant ou invalide.');
        }

        $reviewId = $data['review_id'];

        // Connexion à la base de données
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com';
        $db_name = 'ecoride_db';
        $db_user = 'Nathan';
        $db_pass = 'Af18PsKCc-';

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Préparer et exécuter la requête de mise à jour
        // Assurez-vous que les noms de table et de colonnes correspondent
        $sql = "UPDATE Avis SET statut = 'refusé' WHERE avis_id = :review_id"; // 'refusé' doit correspondre à la valeur dans votre DB

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':review_id', $reviewId, PDO::PARAM_INT);
        $stmt->execute();

        // Vérifier si la mise à jour a affecté une ligne
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => 'Avis refusé avec succès.']);
        } else {
            // L'avis n'a pas été trouvé ou n'était pas en attente
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Avis non trouvé ou déjà traité.']);
        }

    } else {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400); // Bad Request pour les erreurs de logique/validation
    echo json_encode(['error' => $e->getMessage()]);
}
?>
