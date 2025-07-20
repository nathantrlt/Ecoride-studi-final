<?php
// backend/create_ride.php

// Start or resume the PHP session
session_start();

// Display all PHP errors for debugging (in development)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Indicate that the response is in JSON format

// Allow requests from any origin (for development)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Allow POST requests
header("Access-Control-Allow-Headers: Content-Type");

// Handle OPTIONS requests (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Use a try-catch block to catch errors and return a JSON error
try {
    // Ensure the request method is POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get the JSON data from the request body
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        // Check if data was received and decoding was successful
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('JSON decoding error.');
        }

        // --- Check if the user is connected ---
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Vous devez être connecté pour proposer un covoiturage.']);
            exit();
        }
        $user_id = $_SESSION['user_id']; // ID of the connected user


        // --- Database connection ---
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // Replace with your RDS Endpoint
        $db_name = 'ecoride_db'; // Replace with your database name
        $db_user = 'Nathan';      // Replace with your RDS username
        $db_pass = 'Af18PsKCc-';          // Replace with your RDS password

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // --- Verify that the user has the 'Chauffeur' role ---
        $is_chauffeur_sql = "SELECT COUNT(*) FROM Utilisateur_Role ur JOIN Role r ON ur.role_id = r.role_id WHERE ur.utilisateur_id = :user_id AND r.libelle = 'Chauffeur'"; // Assuming role libelle is 'Chauffeur'
        $is_chauffeur_stmt = $pdo->prepare($is_chauffeur_sql);
        $is_chauffeur_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $is_chauffeur_stmt->execute();
        $is_chauffeur = $is_chauffeur_stmt->fetchColumn() > 0;

        if (!$is_chauffeur) {
             http_response_code(403); // Forbidden
             echo json_encode(['error' => 'Vous devez avoir le rôle Chauffeur pour proposer un covoiturage.']);
             exit();
        }

        // --- Get ride data to insert ---
        // Check for required fields and basic validation
        if (!isset($data['lieu_depart']) || empty(trim($data['lieu_depart'])) ||
            !isset($data['lieu_arrivee']) || empty(trim($data['lieu_arrivee'])) ||
            !isset($data['date_depart']) || empty(trim($data['date_depart'])) || // Basic check, more date validation needed
            !isset($data['heure_depart']) || empty(trim($data['heure_depart'])) || // Basic check, more time validation needed
            !isset($data['nombre_places']) || !is_numeric($data['nombre_places']) || (int)$data['nombre_places'] <= 0 ||
            !isset($data['prix']) || !is_numeric($data['prix']) || (float)$data['prix'] < 0 ||
            !isset($data['voiture_id']) || !is_numeric($data['voiture_id']) || (int)$data['voiture_id'] <= 0) {
             throw new Exception('Données du covoiturage incomplètes ou invalides.');
        }

        $lieu_depart = trim($data['lieu_depart']);
        $lieu_arrivee = trim($data['lieu_arrivee']);
        $date_depart = trim($data['date_depart']);
        $heure_depart = trim($data['heure_depart']); // Format HH:MM attendu par input type="time"
        $nombre_places = (int)$data['nombre_places'];
        $prix = (float)$data['prix'];
        $voiture_id = (int)$data['voiture_id'];

        // Calculer la date et l'heure d'arrivée (1 jour après la date et l'heure de départ)
        $departure_datetime_str_for_calculation = $date_depart . ' ' . $heure_depart;
        $arrival_datetime = new DateTime($departure_datetime_str_for_calculation);
        $arrival_datetime->modify('+1 day');

        $date_arrivee = $arrival_datetime->format('YYYY-MM-DD'); // Format YYYY-MM-DD pour la date d'arrivée
        $heure_arrivee = $arrival_datetime->format('HH:MM'); // Format HH:MM pour l'heure d'arrivée (si votre colonne accepte cela)
        // Si votre colonne heure_arrivee attend un format plus complet avec secondes, utilisez 'HH:MM:SS'
        // $heure_arrivee = $arrival_datetime->format('H:i:s'); // Exemple pour HH:MM:SS

        
        // --- More detailed Validation ---
        // Validate date format (YYYY-MM-DD)
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date_depart)) {
             throw new Exception("Format de date invalide. Utilisez YYYY-MM-DD.");
        }
        // Validate time format (HH:MM)
        if (!preg_match("/^\d{2}:\d{2}$/", $heure_depart)) {
            // If input type="time" is used, this regex might be too strict if seconds are included.
            // Adjust regex if your input type="time" includes seconds.
            // A simple regex for HH:MM
             if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $heure_depart)) {
                 throw new Exception("Format d'heure invalide. Utilisez HH:MM.");
             }
        }

        // Check if departure date/time is in the future
        $current_datetime = new DateTime();
        $departure_datetime_str = $date_depart . ' ' . $heure_depart;
        try {
            $departure_datetime = new DateTime($departure_datetime_str);
             if ($departure_datetime <= $current_datetime) {
                  throw new Exception("La date et l'heure de départ doivent être dans le futur.");
             }
        } catch (Exception $e) {
            // Handle errors during date/time object creation
            throw new Exception("Date ou heure invalide.");
        }


        // --- Verify that the selected vehicle belongs to the connected user ---
        $check_vehicle_owner_sql = "SELECT COUNT(*) FROM Voiture WHERE voiture_id = :voiture_id AND utilisateur_id = :user_id";
        $check_vehicle_owner_stmt = $pdo->prepare($check_vehicle_owner_sql);
        $check_vehicle_owner_stmt->bindParam(':voiture_id', $voiture_id, PDO::PARAM_INT);
        $check_vehicle_owner_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $check_vehicle_owner_stmt->execute();
        $is_vehicle_owner = $check_vehicle_owner_stmt->fetchColumn() > 0;

        if (!$is_vehicle_owner) {
            http_response_code(403); // Forbidden
            // Note: A user could potentially try to use the ID of another user's vehicle if not checked.
            echo json_encode(['error' => 'Le véhicule sélectionné n\'appartient pas à votre compte.']);
            exit();
        }


        // --- Calculate Platform Fee ---
        // As per consignes, 2 credits are taken by the platform.
        // This fee is usually applied to the total price or per passenger.
        // The consignes say "2 crédits sont pris par la plateforme", implying a fixed fee per ride.
        // We will store the original price set by the driver, and maybe a calculated net price.
        // Let's store the driver's price and the platform fee separately, or calculate the net.
        // Option 1: Store driver_price, platform_fee
        // Option 2: Store driver_price, calculate net_price = driver_price - platform_fee (if driver pays fee)
        // Option 3: Store price_per_passenger, platform_fee_per_passenger
        // Given "2 crédits sont pris", let's assume a fixed platform fee per ride for simplicity for now.
        // Let's store the driver's set price as price_per_passenger and the platform takes 2 credits *per passenger*
        // OR the platform takes 2 credits total from the driver's earnings.
        // The consignes are slightly ambiguous here. A common model is fee per passenger.
        // Let's assume 2 credits are taken per passenger *from the passenger's payment*. The driver receives price_per_passenger.
        // This requires a slight adjustment to the model: driver sets price_per_passenger. Passenger pays price_per_passenger + 2.
        // Let's clarify this or make an assumption.
        // ASSUMPTION: The driver sets the price *per passenger* that THEY will receive. The platform adds its fee on top for the passenger.
        // So, the field "Prix par personne (en crédits)" in the form is what the driver gets per passenger.
        $driver_price_per_passenger = $prix; // Use the price from the form
        $platform_fee_per_passenger = 2; // As per consignes

        // The total price the passenger pays would be $driver_price_per_passenger + $platform_fee_per_passenger

        // --- Insert the new ride into the Covoiturage table ---
        // Ensure the column names match your Covoiturage table
        // traveller_id (chauffeur_id), departure_location, arrival_location,
        // departure_date, departure_time, available_seats, price (driver's price per passenger),
        // vehicle_id, created_at, updated_at
        // Assuming columns: chauffeur_id, lieu_depart, lieu_arrivee, date_depart, heure_depart,
        //                     places_disponibles, prix_par_passager, voiture_id, created_at, updated_at
        // --- Insert the new ride into the Covoiturage table ---
        // Utilisez le nom de colonne correct 'utilisateur_id'
        $insert_sql = "INSERT INTO Covoiturage (utilisateur_id, lieu_depart, lieu_arrivee, date_depart, heure_depart, date_arrivee, heure_arrivee, nb_place, prix_personne, voiture_id, statut, created_at, updated_at)
        VALUES (:utilisateur_id, :lieu_depart, :lieu_arrivee, :date_depart, :heure_depart, :date_arrivee, :heure_arrivee, :nb_place, :prix_personne, :voiture_id, 'Disponible', NOW(), NOW())";

        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->bindParam(':utilisateur_id', $user_id, PDO::PARAM_INT); // Liez l'user_id au bon placeholder
        $insert_stmt->bindParam(':lieu_depart', $lieu_depart, PDO::PARAM_STR);
        $insert_stmt->bindParam(':lieu_arrivee', $lieu_arrivee, PDO::PARAM_STR);
        $insert_stmt->bindParam(':date_depart', $date_depart, PDO::PARAM_STR);
        $insert_stmt->bindParam(':heure_depart', $heure_depart, PDO::PARAM_STR);
        $insert_stmt->bindParam(':nb_place', $nombre_places, PDO::PARAM_INT);
        $insert_stmt->bindParam(':prix_personne', $driver_price_per_passenger, PDO::PARAM_STR);
        $insert_stmt->bindParam(':voiture_id', $voiture_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':date_arrivee', $date_arrivee, PDO::PARAM_STR);
        $insert_stmt->bindParam(':heure_arrivee', $heure_arrivee, PDO::PARAM_STR);


        $insert_stmt->execute();


        // Get the ID of the newly inserted ride (useful for future links)
        $new_ride_id = $pdo->lastInsertId();

        // --- Return a success response to the frontend ---
        echo json_encode(['success' => 'Covoiturage proposé avec succès !', 'ride_id' => $new_ride_id]);

    } else {
        // If the method is not POST
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur de base de données lors de la création du covoiturage.']);
    error_log("Erreur PDO dans create_ride.php: " . $e->getMessage());

} catch (Exception $e) {
    // Handle other errors (validation, etc.)
    http_response_code(400); // Bad Request for validation/data errors
    echo json_encode(['error' => $e->getMessage()]); // Return the specific error message
    error_log("Erreur générale dans create_ride.php: " . $e->getMessage());
}

?>