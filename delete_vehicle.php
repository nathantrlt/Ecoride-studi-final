<?php
// backend/delete_vehicle.php

// Démarrer ou reprendre la session PHP
session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Indique que la réponse est au format JSON

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Ou DELETE
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Ou DELETE
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON.');
        }

        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Vous devez être connecté pour supprimer un véhicule.']);
            exit();
        }
        $user_id = $_SESSION['user_id'];

        // Vérifier si l'ID du véhicule à supprimer est présent
        if (!isset($data['vehicle_id'])) {
             throw new Exception('ID du véhicule à supprimer manquant.');
        }

        $voiture_id = (int)$data['vehicle_id']; // ID du véhicule à supprimer

        // Connexion à la base de données
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // Remplacez
        $db_name = 'ecoride_db'; // Remplacez
        $db_user = 'Nathan';      // Remplacez
        $db_pass = 'Af18PsKCc-';          // Remplacez

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // --- Vérifier que le véhicule appartient bien à l'utilisateur connecté avant de supprimer ---
        $check_owner_sql = "SELECT COUNT(*) FROM Voiture WHERE voiture_id = :voiture_id AND utilisateur_id = :user_id";
        $check_owner_stmt = $pdo->prepare($check_owner_sql);
        $check_owner_stmt->bindParam(':voiture_id', $voiture_id, PDO::PARAM_INT);
        $check_owner_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $check_owner_stmt->execute();
        $is_owner = $check_owner_stmt->fetchColumn() > 0;

        if (!$is_owner) {
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Vous n\'êtes pas autorisé à supprimer ce véhicule.']);
            exit();
        }


        // --- Supprimer le véhicule ---
        $delete_sql = "DELETE FROM Voiture WHERE voiture_id = :voiture_id AND utilisateur_id = :user_id"; // Double vérification de l'appartenance

        $delete_stmt = $pdo->prepare($delete_sql);
        $delete_stmt->bindParam(':voiture_id', $voiture_id, PDO::PARAM_INT);
        $delete_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT); // Assurez-vous de supprimer le véhicule du bon utilisateur

        $delete_stmt->execute();

        // Vérifier si une ligne a été affectée (si la suppression a eu lieu)
        $rows_affected = $delete_stmt->rowCount();
        if ($rows_affected === 0) {
             // Cela peut arriver si le véhicule n'existe pas avec cet ID/cet utilisateur
             throw new Exception("Véhicule non trouvé ou déjà supprimé.");
        }


        // --- Renvoyer une réponse de succès au frontend ---
        echo json_encode(['success' => 'Véhicule supprimé avec succès !']);

    } else {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur de base de données lors de la suppression du véhicule.']);
    error_log("Erreur PDO dans delete_vehicle.php: " . $e->getMessage());

} catch (Exception $e) {
    http_response_code(400); // Bad Request ou 500
    echo json_encode(['error' => $e->getMessage()]);
    error_log("Erreur générale dans delete_vehicle.php: " . $e->getMessage());
}
?>
