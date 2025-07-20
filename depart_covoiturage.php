<?php
// backend/depart_covoiturage.php

session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Vous devez être connecté pour démarrer un covoiturage.']);
            exit();
        }
        $user_id = $_SESSION['user_id'];

        // Récupérer les données JSON (doit contenir l'ID du covoiturage)
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON.');
        }

        if (!isset($data['ride_id'])) {
             throw new Exception('ID du covoiturage manquant dans la requête.');
        }

        $ride_id = (int)$data['ride_id']; // S'assurer que c'est un entier

        // --- Database connection ---
        // Remplacez par vos paramètres de connexion RDS
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com';
        $db_name = 'ecoride_db';
        $db_user = 'Nathan';
        $db_pass = 'Af18PsKCc-';

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // --- Début de la transaction ---
        $pdo->beginTransaction();

        // 1. Vérifier que l'utilisateur connecté est bien le chauffeur de ce covoiturage
        $check_driver_sql = "SELECT utilisateur_id, statut FROM Covoiturage WHERE covoiturage_id = :ride_id FOR UPDATE"; // Verrouiller la ligne
        $check_driver_stmt = $pdo->prepare($check_driver_sql);
        $check_driver_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $check_driver_stmt->execute();
        $ride_info = $check_driver_stmt->fetch();

        if (!$ride_info) {
            $pdo->rollBack();
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Covoiturage introuvable.']);
            exit();
        }

        if ($ride_info['utilisateur_id'] !== $user_id) {
            $pdo->rollBack();
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Vous n\'êtes pas le chauffeur de ce covoiturage.']);
            exit();
        }

        // 2. Vérifier le statut actuel pour permettre le démarrage
        // Adaptez 'Disponible' et 'En cours' aux statuts que vous utilisez
        if ($ride_info['statut'] === 'En cours') {
             $pdo->rollBack();
             http_response_code(400); // Bad Request
             echo json_encode(['error' => 'Ce covoiturage est déjà en cours.']);
             exit();
        }
        if ($ride_info['statut'] !== 'Disponible') {
             $pdo->rollBack();
             http_response_code(400); // Bad Request
             echo json_encode(['error' => 'Le statut de ce covoiturage ne permet pas de le démarrer. Statut actuel: ' . $ride_info['statut']]);
             exit();
        }

        // --- Vérifier si le covoiturage est déjà passé (bonne pratique ici aussi) ---
        // Vous avez déjà cette logique dans cancel_ride_by_driver.php, vous pouvez la réutiliser ici
        // Assurez-vous d'avoir la date et l'heure dans $ride_info ou de les récupérer ici.
        // Pour l'instant, je suppose que $ride_info contient date_depart et heure_depart si vous les avez sélectionnés dans la requête check_driver_sql

        // $departure_datetime_str = $ride_info['date_depart'] . ' ' . $ride_info['heure_depart']; // Exemple si date/heure sont dans $ride_info
        // $departure_datetime = new DateTime($departure_datetime_str);
        // $current_datetime = new DateTime();
        // if ($departure_datetime <= $current_datetime) {
        //     $pdo->rollBack();
        //     http_response_code(400);
        //     echo json_encode(['error' => 'La date de départ de ce covoiturage est passée.']);
        //     exit();
        // }
        // --- Fin de la vérification de date ---


        // 3. Mettre à jour le statut à 'En cours'
        $update_status_sql = "UPDATE Covoiturage SET statut = 'En cours' WHERE covoiturage_id = :ride_id";
        $update_status_stmt = $pdo->prepare($update_status_sql);
        $update_status_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $update_status_stmt->execute();

        // --- Fin de la transaction : Valider ---
        $pdo->commit();

        // --- Renvoyer une réponse de succès ---
        echo json_encode(['success' => 'Covoiturage démarré avec succès !']);

    } else {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur PDO
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données lors du démarrage du covoiturage.']);
    error_log("Erreur PDO dans depart_covoiturage.php: " . $e->getMessage());

} catch (Exception $e) {
    // Annuler la transaction en cas d'autres erreurs
     if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request pour les erreurs de validation/données
    echo json_encode(['error' => $e->getMessage()]); // Retourner le message d'erreur spécifique
    error_log("Erreur générale dans depart_covoiturage.php: " . $e->getMessage());
}
?>
