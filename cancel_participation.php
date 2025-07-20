<?php
// backend/cancel_participation.php

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
            echo json_encode(['error' => 'Vous devez être connecté pour annuler une participation.']);
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
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // Replace with your RDS Endpoint
        $db_name = 'ecoride_db'; // Replace with your database name
        $db_user = 'Nathan';      // Replace with your RDS username
        $db_pass = 'Af18PsKCc-';          // Replace with your RDS password

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // --- Vérifier si le covoiturage est déjà passé ---
        // Récupérer la date et l'heure de départ du covoiturage
        $get_ride_datetime_sql = "SELECT date_depart, heure_depart FROM Covoiturage WHERE covoiturage_id = :ride_id";
        $get_ride_datetime_stmt = $pdo->prepare($get_ride_datetime_sql);
        $get_ride_datetime_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $get_ride_datetime_stmt->execute();
        $ride_datetime_info = $get_ride_datetime_stmt->fetch();

        if (!$ride_datetime_info) {
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Covoiturage introuvable.']);
            exit();
        }

        $departure_datetime_str = $ride_datetime_info['date_depart'] . ' ' . $ride_datetime_info['heure_depart'];
        $departure_datetime = new DateTime($departure_datetime_str);
        $current_datetime = new DateTime();

        // Comparer la date et l'heure de départ avec la date et l'heure actuelles
        if ($departure_datetime <= $current_datetime) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Vous ne pouvez pas annuler une participation pour un covoiturage déjà passé.']);
            exit(); // Arrêter l'exécution
        }
        // --- Fin de la vérification si le covoiturage est passé ---

        // --- Début de la transaction ---
        $pdo->beginTransaction();

        // 1. Vérifier que l'utilisateur est bien un participant pour ce covoiturage
        // Adaptez le nom de la table 'Participation' si nécessaire
        $check_participation_sql = "SELECT COUNT(*) FROM Participation WHERE covoiturage_id = :ride_id AND utilisateur_id = :user_id";
        $check_participation_stmt = $pdo->prepare($check_participation_sql);
        $check_participation_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $check_participation_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $check_participation_stmt->execute();
        $is_participant = $check_participation_stmt->fetchColumn() > 0;

        if (!$is_participant) {
            $pdo->rollBack();
            http_response_code(400); // Bad Request ou 403 Forbidden selon votre préférence
            echo json_encode(['error' => 'Vous n\'êtes pas participant à ce covoiturage.']);
            exit();
        }

        // 2. Récupérer le prix du covoiturage pour le remboursement
        $get_price_sql = "SELECT prix_personne, nb_place, statut FROM Covoiturage WHERE covoiturage_id = :ride_id";
        $get_price_stmt = $pdo->prepare($get_price_sql);
        $get_price_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $get_price_stmt->execute();
        $ride_info = $get_price_stmt->fetch();

        if (!$ride_info) {
             $pdo->rollBack();
             http_response_code(404); // Not Found
             echo json_encode(['error' => 'Covoiturage introuvable.']);
             exit();
        }

        $price_to_refund = $ride_info['prix_personne'];
        $current_nb_place = $ride_info['nb_place'];
        $current_status = $ride_info['statut'];


        // 3. Supprimer l'entrée de participation
        // Adaptez le nom de la table 'Participation' si nécessaire
        $delete_participation_sql = "DELETE FROM Participation WHERE covoiturage_id = :ride_id AND utilisateur_id = :user_id";
        $delete_participation_stmt = $pdo->prepare($delete_participation_sql);
        $delete_participation_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $delete_participation_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $delete_participation_stmt->execute();

        // Vérifier si la suppression a bien eu lieu (au cas où)
        if ($delete_participation_stmt->rowCount() === 0) {
             $pdo->rollBack();
             http_response_code(500); // Internal Server Error
             echo json_encode(['error' => 'Erreur lors de la suppression de la participation.']);
             exit();
        }


        // 4. Augmenter le nombre de places disponibles dans Covoiturage
        $new_nb_place = $current_nb_place + 1;
        $update_place_sql = "UPDATE Covoiturage SET nb_place = :new_nb_place WHERE covoiturage_id = :ride_id";
        $update_place_stmt = $pdo->prepare($update_place_sql);
        $update_place_stmt->bindParam(':new_nb_place', $new_nb_place, PDO::PARAM_INT);
        $update_place_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $update_place_stmt->execute();


        // 5. Mettre à jour le statut si nécessaire
        // Si le statut était 'Indisponible' et qu'au moins une place redevient disponible
        if ($current_status === 'Indisponible' && $new_nb_place > 0) {
             $update_status_sql = "UPDATE Covoiturage SET statut = 'Disponible' WHERE covoiturage_id = :ride_id";
             $update_status_stmt = $pdo->prepare($update_status_sql);
             $update_status_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
             $update_status_stmt->execute();
        }


        // 6. Rembourser les crédits à l'utilisateur
        // Ceci dépend de la structure de votre table Utilisateur et de votre système de crédits.
        // Exemple supposant une colonne 'credits' dans la table 'Utilisateur'
        $update_user_credits_sql = "UPDATE Utilisateur SET credits = credits + :price_to_refund WHERE utilisateur_id = :user_id";
        $update_user_credits_stmt = $pdo->prepare($update_user_credits_sql);
        $update_user_credits_stmt->bindParam(':price_to_refund', $price_to_refund, PDO::PARAM_STR); // Ou PARAM_INT/PARAM_FLOAT
        $update_user_credits_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $update_user_credits_stmt->execute();


        // --- Fin de la transaction : Valider ---
        $pdo->commit();

        // --- Renvoyer une réponse de succès ---
        echo json_encode(['success' => 'Participation annulée avec succès ! Vos crédits ont été remboursés.']);

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
    echo json_encode(['error' => 'Erreur de base de données lors de l\'annulation de la participation.']);
    error_log("Erreur PDO dans cancel_participation.php: " . $e->getMessage());

} catch (Exception $e) {
    // Annuler la transaction en cas d'autres erreurs
     if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request for validation/data errors
    echo json_encode(['error' => $e->getMessage()]); // Return the specific error message
    error_log("Erreur générale dans cancel_participation.php: " . $e->getMessage());
}
?>
