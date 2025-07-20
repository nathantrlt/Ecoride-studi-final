<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Indique que la réponse est au format JSON

// Afficher toutes les erreurs PHP pour le débogage
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);

// Permettre les requêtes depuis n'importe quelle origine (pour le développement)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Gérer les requêtes OPTIONS (pré-vol CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Utiliser un bloc try-catch pour intercepter les erreurs et renvoyer un JSON d'erreur
try {
    // Assurez-vous que la méthode de requête est POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Récupérer les données JSON du corps de la requête
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        // Vérifier si les données nécessaires sont présentes et si le décodage JSON a réussi
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON : ' . json_last_error_msg());
        }

        if (!isset($data['departure']) || !isset($data['arrival']) || !isset($data['date'])) {
             throw new Exception('Données de recherche manquantes dans la requête.');
        }

        $departure = trim($data['departure']);
        $arrival = trim($data['arrival']);
        $date = $data['date'];

        // Récupérer les valeurs des filtres (avec vérification d'existence et conversion de type si nécessaire)
        $filterEcological = $data['filterEcological'] ?? false; // false par défaut
        $filterPriceMax = isset($data['filterPriceMax']) && is_numeric($data['filterPriceMax']) ? (float)$data['filterPriceMax'] : null; // null si vide ou non numérique
        $filterDurationMax = isset($data['filterDurationMax']) && is_numeric($data['filterDurationMax']) ? (int)$data['filterDurationMax'] : null; // null si vide ou non numérique
        $filterRatingMin = isset($data['filterRatingMin']) && is_numeric($data['filterRatingMin']) ? (float)$data['filterRatingMin'] : null; // null si vide ou non numérique

        // --- Début de la logique de base de données ---

        // Paramètres de connexion à la base de données
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // L'hôte de la base de données
        $db_name = 'ecoride_db'; // Le nom de la base de données créée
        $db_user = 'Nathan';      // nom d'utilisateur de base de données
        $db_pass = 'Af18PsKCc-';          // mot de passe de base de données

        // Connexion à la base de données en utilisant PDO
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Requête SQL pour rechercher les covoiturages 
        // Utilise les noms de tables et de colonnes de votre schéma
        $sql = "SELECT
        c.covoiturage_id,
        c.date_depart,
        c.heure_depart,
        c.lieu_depart,
        c.date_arrivee,
        c.heure_arrivee,
        c.lieu_arrivee,
        c.nb_place,
        c.prix_personne,
        u.pseudo AS driver_pseudo,
        u.photo AS driver_photo_blob,
        v.modele AS vehicle_model,
        v.energie AS vehicle_energy
    FROM Covoiturage c
    JOIN Voiture v ON c.voiture_id = v.voiture_id -- Joindre Covoiturage à Voiture
    JOIN Utilisateur u ON v.utilisateur_id = u.utilisateur_id 
    WHERE c.lieu_depart LIKE :departure
      AND c.lieu_arrivee LIKE :arrival
      AND c.date_depart = :date
      AND c.nb_place >= 1
      AND c.statut = 'Disponible'"; // Afficher uniquement les trajets avec le statut disponible

        // Construire dynamiquement les clauses de filtre
        if ($filterEcological) {
            // Ajouter la condition pour les voyages écologiques (énergie = 'electrique')
            // Assurez-vous que la colonne `energie` existe dans votre table Voiture et que la valeur est 'electrique'
            $sql .= " AND v.energie = 'Electrique'";
        }

        if ($filterPriceMax !== null) {
            // Ajouter la condition pour le prix maximum
            $sql .= " AND c.prix_personne <= :price_max";
        }
        if ($filterRatingMin !== null) {
        }

        $stmt = $pdo->prepare($sql);

        // Lier les paramètres
        $stmt->bindValue(':departure', '%' . $departure . '%', PDO::PARAM_STR);
        $stmt->bindValue(':arrival', '%' . $arrival . '%', PDO::PARAM_STR);
        $stmt->bindParam(':date', $date, PDO::PARAM_STR);

        if ($filterPriceMax !== null) {
            $stmt->bindParam(':price_max', $filterPriceMax, PDO::PARAM_STR); // Utiliser PARAM_STR ou PARAM_INT/PARAM_BOOL selon le type exact
        }

        // Exécuter la requête et récupérer tous les résultats de base
        $stmt->execute();
        $rides_data = $stmt->fetchAll();

        // --- Début de la logique de filtrage PHP après récupération ---

        $filtered_rides = []; // Nouveau tableau pour stocker les résultats filtrés

        foreach ($rides_data as $ride) {
            $include_ride = true; // Indicateur pour inclure ce covoiturage

            // 1. Calculer la durée du trajet en PHP
            // Utilisation unique des déclarations de date/heure
            $departure_datetime_str = $ride['date_depart'] . ' ' . $ride['heure_depart'];
            $arrival_datetime_str = $ride['date_arrivee'] . ' ' . $ride['heure_arrivee'];

            $duration_hours = null; // Initialiser à null en cas d'erreur de calcul ou si les données sont manquantes
            try {
                // Vérifier si les dates et heures nécessaires existent et ne sont pas vides
                if (!empty($ride['date_depart']) && !empty($ride['heure_depart']) && !empty($ride['date_arrivee']) && !empty($ride['heure_arrivee'])) {
                    $departure_datetime = new DateTime($departure_datetime_str);
                    $arrival_datetime = new DateTime($arrival_datetime_str);

                    // Vérifier que la date/heure d'arrivée n'est pas antérieure à celle de départ (évite les intervalles négatifs)
                    if ($arrival_datetime >= $departure_datetime) {
                        $interval = $departure_datetime->diff($arrival_datetime);
                        $duration_minutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
                        $duration_hours = $duration_minutes / 60;
                    } else {
                        // Gérer le cas où l'heure d'arrivée est avant l'heure de départ (par exemple, trajet nocturne)
                        // Cela nécessiterait une logique de date plus complexe ou l'utilisation du type DATETIME partout
                        // Pour l'instant, traitons comme un cas non calculable
                        $duration_hours = null;
                    }

                    // Supprimer les commentaires de style JavaScript incorrects en PHP
                    // error_log("DEBUG PHP: Calcul durée - Départ: " . $departure_datetime_str . ", Arrivée: " . $arrival_datetime_str . ", Durée (minutes): " . $duration_minutes . ", Durée (heures): " . $duration_hours);


                } else {
                     // Supprimer les commentaires de style JavaScript incorrects en PHP
                     // error_log("DEBUG PHP: Données de date/heure manquantes pour calcul durée.");
                }
            } catch (Exception $e) {
                error_log("Erreur calcul durée: " . $e->getMessage());
                $duration_hours = null;
            }

            // 2. Récupérer ou calculer la note du chauffeur
            // Utilisation de la colonne 'driver_rating' si sélectionnée, sinon 0 par défaut
            $driver_actual_rating = $ride['driver_rating'] ?? 0;


            // 3. Appliquer les filtres PHP
            if ($filterDurationMax !== null) {
                if ($duration_hours === null || $duration_hours > $filterDurationMax) {
                    $include_ride = false;
                }
            }

            if ($filterRatingMin !== null) {
                if ($driver_actual_rating < $filterRatingMin) {
                    $include_ride = false;
                }
            }

            // 4. Ajouter le covoiturage filtré au nouveau tableau
            if ($include_ride) {
                $filtered_rides[] = $ride;
            }
        } // <--- Fin de la boucle foreach

        // --- Début du formatage des résultats (appliquer à $filtered_rides) ---

        // Appliquer array_map sur le nouveau tableau de résultats filtrés
        $formatted_rides = array_map(function($ride) {
            // Déterminer si le covoiturage est écologique basé sur l'énergie de la voiture
            // Utiliser 'electrique' sans accent pour la comparaison si c'est ainsi dans la BDD
            $isEcological = isset($ride['vehicle_energy']) && strtolower($ride['vehicle_energy']) === 'electrique';

            // Gérer la photo (BLOB) : Encoder en Base64 pour utiliser comme Data URL
            $driver_photo_data_url = 'images/default_user.png';
            if (isset($ride['driver_photo_blob']) && !empty($ride['driver_photo_blob'])) {
                $image_info = @getimagesizefromstring($ride['driver_photo_blob']);
                $mime_type = $image_info ? $image_info['mime'] : 'image/jpeg';
                $driver_photo_data_url = 'data:' . $mime_type . ';base64,' . base64_encode($ride['driver_photo_blob']);
            }

            $driver_rating_for_frontend = $ride['driver_rating'] ?? 0; // Utiliser la note réelle/par défaut


            return [
                'ride_id' => $ride['covoiturage_id'],
                'driver' => [
                    'pseudo' => $ride['driver_pseudo'] ?? 'N/A',
                    'photo' => $driver_photo_data_url,
                    'rating' => $driver_rating_for_frontend
                ],
                // CORRECTION : Utiliser 'nb_place_disponible' ici
                'availableSeats' => $ride['nb_place'] ?? 'N/A',
                'price' => $ride['prix_personne'] ?? 'N/A',
                'departureTime' => $ride['heure_depart'],
                'arrivalTime' => $ride['heure_arrivee'],
                // CORRECTION : Ajouter la virgule manquante ici
                'isEcological' => $isEcological,
                'detailsLink' => 'ride_details.html?id=' . $ride['covoiturage_id'],
                'departure_location' => $ride['lieu_depart'],
                'arrival_location' => $ride['lieu_arrivee'],
                'departure_date' => $ride['date_depart']
            ];
        }, $filtered_rides); // <--- Appliquer array_map sur $filtered_rides

        // --- Fin du formatage ---

        // Renvoyer les résultats filtrés et formatés au frontend
        echo json_encode($formatted_rides);


    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur : ' . $e->getMessage()]);
}
?>