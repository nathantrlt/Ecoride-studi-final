<?php
// backend/rides_details.php

// Afficher toutes les erreurs PHP pour le débogage (en développement)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Indique que la réponse est au format JSON

// Permettre les requêtes depuis n'importe quelle origine (pour le développement)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Autoriser les requêtes GET
header("Access-Control-Allow-Headers: Content-Type");

// Gérer les requêtes OPTIONS (pré-vol CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Utiliser un bloc try-catch pour intercepter les erreurs et renvoyer un JSON d'erreur
try {
    // Assurez-vous que la méthode de requête est GET et que l'ID est présent dans l'URL
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        $ride_id = $_GET['id'];

        // Vérifier que l'ID est un nombre valide
        if (!is_numeric($ride_id)) {
             http_response_code(400); // Bad Request
             echo json_encode(['error' => 'ID du covoiturage invalide.']);
             exit();
        }

        // --- Début de la logique de base de données ---

        // Paramètres de connexion à la base de données AWS RDS (les mêmes que search_rides.php)
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // Remplacez par votre Endpoint RDS
        $db_name = 'ecoride_db'; // Remplacez par le nom de votre base de données
        $db_user = 'Nathan';      // Remplacez par votre nom d'utilisateur RDS
        $db_pass = 'Af18PsKCc-';          // Remplacez par votre mot de passe RDS

        // Connexion à la base de données en utilisant PDO
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Requête SQL pour récupérer TOUS les détails du covoiturage spécifique
        // Utilise les noms de tables et de colonnes de votre schéma
        $sql = "SELECT
                    c.*, -- Sélectionne toutes les colonnes de la table Covoiturage
                    u.pseudo AS driver_pseudo,
                    u.photo AS driver_photo_blob, -- Photo du chauffeur (BLOB)
                    AVG(a.note) AS driver_average_rating,
                    u.fumeur AS driver_fumeur,
                    u.animaux AS driver_animaux,
                    v.modele AS vehicle_model,
                    v.energie AS vehicle_energy,
                    v.couleur AS vehicle_color, -- Inclure d'autres détails de la voiture si nécessaire
                    v.immatriculation AS vehicle_registration
                FROM Covoiturage c
                JOIN Voiture v ON c.voiture_id = v.voiture_id
                JOIN Utilisateur u ON v.utilisateur_id = u.utilisateur_id
                LEFT JOIN Avis a ON u.utilisateur_id = a.utilisateur_id_destinataire
                WHERE c.covoiturage_id = :ride_id
                GROUP BY c.covoiturage_id, u.utilisateur_id, v.voiture_id
                LIMIT 1"; // On s'attend à un seul résultat pour un ID donné

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT); // Lier l'ID du covoiturage

        $stmt->execute();

        $ride_details = $stmt->fetch(); // Récupérer les détails (une seule ligne)

        // Vérifier si un covoiturage a été trouvé avec cet ID
        if ($ride_details) {
            // --- Début du formatage des détails pour le frontend ---

            // Gérer la photo (BLOB) : Encoder en Base64 pour utiliser comme Data URL
            $driver_photo_data_url = 'images/default_user.png'; // URL par défaut
            if (isset($ride_details['driver_photo_blob']) && !empty($ride_details['driver_photo_blob'])) {
                $image_info = @getimagesizefromstring($ride_details['driver_photo_blob']);
                $mime_type = $image_info ? $image_info['mime'] : 'image/jpeg';
                $driver_photo_data_url = 'data:' . $mime_type . ';base64,' . base64_encode($ride_details['driver_photo_blob']);
            }

            // Préparer les données dans une structure facile à utiliser pour le frontend
            $formatted_details = [
                'ride_id' => $ride_details['covoiturage_id'],
                'date_depart' => $ride_details['date_depart'],
                'heure_depart' => $ride_details['heure_depart'],
                'lieu_depart' => $ride_details['lieu_depart'],
                'date_arrivee' => $ride_details['date_arrivee'],
                'heure_arrivee' => $ride_details['heure_arrivee'],
                'lieu_arrivee' => $ride_details['lieu_arrivee'],
                // Utiliser 'nb_place_disponible' si c'est le nom de la colonne réelle
                'availableSeats' => $ride_details['nb_place_disponible'] ?? $ride_details['nb_place'] ?? 'N/A', // Utilise la colonne correcte si elle existe
                'price' => $ride_details['prix_personne'] ?? 'N/A',
                'statut' => $ride_details['statut'], // Inclure le statut si nécessaire

                'driver' => [
                    'pseudo' => $ride_details['driver_pseudo'] ?? 'N/A',
                    'photo' => $driver_photo_data_url, // URL de données de la photo
                    'rating' => isset($ride_details['driver_average_rating']) ? number_format((float)$ride_details['driver_average_rating'], 1, '.', '') : 'N/A',
                    'fumeur' => $ride_details['driver_fumeur'] ?? 'non',
                    'animaux' => $ride_details['driver_animaux'] ?? 'non',
                    // autres détails du chauffeur si nécessaire (téléphone, email - attention à la confidentialité)
                ],

                'vehicle' => [
                    'model' => $ride_details['vehicle_model'] ?? 'N/A',
                    'energy' => $ride_details['vehicle_energy'] ?? 'N/A',
                    'color' => $ride_details['vehicle_color'] ?? 'N/A',
                    'registration' => $ride_details['vehicle_registration'] ?? 'N/A',
                    // Ajouter d'autres détails de la voiture si nécessaire
                ],
                 // Ajouter ici d'autres sections si nécessaire (par exemple, liste des participants, avis)

            ];

            // --- Fin du formatage ---

            // Renvoyer les détails formatés au frontend au format JSON
            echo json_encode($formatted_details);

        } else {
            // Si aucun covoiturage n'a été trouvé avec cet ID
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Covoiturage non trouvé avec l\'ID : ' . $ride_id]);
        }

    } else {
        // Si la méthode n'est pas GET ou si l'ID est manquant
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Requête invalide. ID du covoiturage manquant ou méthode non autorisée.']);
    }

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
} catch (Exception $e) {
    // Gérer les autres erreurs PHP
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur : ' . $e->getMessage()]);
}

?>
