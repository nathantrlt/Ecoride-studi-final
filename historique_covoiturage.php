<?php
// backend/historique_covoiturage.php

session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Vous devez être connecté pour voir votre historique.']);
            exit();
        }
        $user_id = $_SESSION['user_id'];

        // --- Database connection ---
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // Replace with your RDS Endpoint
        $db_name = 'ecoride_db'; // Replace with your database name
        $db_user = 'Nathan';      // Replace with your RDS username
        $db_pass = 'Af18PsKCc-';          // Replace with your RDS password

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // --- Requête SQL pour récupérer les covoiturages ---
        // Récupère les trajets où l'utilisateur est chauffeur OU participant.
        // Assurez-vous d'adapter le nom de la table de participation si elle est différente.
        $sql = "
            SELECT
                c.covoiturage_id,
                c.date_depart,
                c.heure_depart,
                c.lieu_depart,
                c.date_arrivee,
                -- c.heure_arrivee, -- Ligne supprimée
                c.lieu_arrivee,
                c.nb_place,
                c.prix_personne,
                c.statut,
                u.pseudo AS driver_pseudo,
                u.photo AS driver_photo_blob,
                v.modele AS vehicle_model,
                v.energie AS vehicle_energy,
                CASE
                    WHEN c.utilisateur_id = :user_id THEN 'Chauffeur'
                    ELSE 'Participant'
                END AS role
            FROM Covoiturage c
            JOIN Voiture v ON c.voiture_id = v.voiture_id
            JOIN Utilisateur u ON c.utilisateur_id = u.utilisateur_id
            LEFT JOIN Participation p ON c.covoiturage_id = p.covoiturage_id AND p.utilisateur_id = :user_id_participation
            WHERE c.utilisateur_id = :user_id_chauffeur OR p.utilisateur_id IS NOT NULL
            ORDER BY c.date_depart DESC, c.heure_depart DESC;
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT); // Utilisé dans CASE
        $stmt->bindParam(':user_id_participation', $user_id, PDO::PARAM_INT); // Utilisé dans LEFT JOIN et WHERE
        $stmt->bindParam(':user_id_chauffeur', $user_id, PDO::PARAM_INT); // Utilisé dans WHERE

        $stmt->execute();
        $ride_history = $stmt->fetchAll();
        // ... (exécution de la requête)

        $formatted_history = array_map(function($ride) {
             // Gérer la photo (BLOB)
            $driver_photo_data_url = 'images/default_user.png';
            if (isset($ride['driver_photo_blob']) && !empty($ride['driver_photo_blob'])) {
                 $image_info = @getimagesizefromstring($ride['driver_photo_blob']);
                 $mime_type = $image_info ? $image_info['mime'] : 'image/jpeg';
                 $driver_photo_data_url = 'data:' . $mime_type . ';base64,' . base64_encode($ride['driver_photo_blob']);
            }


            return [
                'ride_id' => $ride['covoiturage_id'],
                'role' => $ride['role'],
                'driver' => [
                    'pseudo' => $ride['driver_pseudo'] ?? 'N/A',
                    'photo' => $driver_photo_data_url,
                    'rating' => null
                ],
                'departure_location' => $ride['lieu_depart'],
                'arrival_location' => $ride['lieu_arrivee'],
                'departure_date' => $ride['date_depart'],
                'departure_time' => $ride['heure_depart'],
                 'arrival_date' => $ride['date_arrivee'],
                 // 'arrival_time' => $ride['heure_arrivee'], // Ligne supprimée
                'availableSeats' => $ride['nb_place'],
                'price' => $ride['prix_personne'],
                'vehicle_model' => $ride['vehicle_model'],
                'vehicle_energy' => $ride['vehicle_energy'],
                'isEcological' => isset($ride['vehicle_energy']) && strtolower($ride['vehicle_energy']) === 'electrique',
                'status' => $ride['statut'], // Cette ligne reste pour inclure le statut
                'detailsLink' => 'ride_details.html?id=' . $ride['covoiturage_id']
            ];
        }, $ride_history);
        // --- Debugging ---
        error_log("DEBUG: Valeur de \$ride_history après fetchAll: " . print_r($ride_history, true));
        if ($ride_history === null) {
            error_log("DEBUG: \$ride_history est NULL.");
        } elseif (is_array($ride_history)) {
            error_log("DEBUG: \$ride_history est un tableau avec " . count($ride_history) . " éléments.");
        } else {
            error_log("DEBUG: \$ride_history n'est ni tableau, ni NULL. Type: " . gettype($ride_history));
        }
        // --- Fin Debugging ---



        echo json_encode($formatted_history);

    } else {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
    error_log("Erreur PDO dans historique_covoiturage.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur : ' . $e->getMessage()]);
    error_log("Erreur générale dans historique_covoiturage.php: " . $e->getMessage());
}
?>