<?php
// backend/add_vehicle.php

session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Indique que la réponse est au format JSON

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Autoriser les requêtes POST
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

        // Vérifier si des données sont reçues et si le décodage a réussi
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON.');
        }

        // --- Vérifier si l'utilisateur est connecté ---
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Vous devez être connecté pour ajouter un véhicule.']);
            exit();
        }
        $user_id = $_SESSION['user_id']; // ID de l'utilisateur connecté


        // --- Connexion à la base de données ---
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // Remplacez par votre Endpoint RDS
        $db_name = 'ecoride_db'; // Remplacez par le nom de votre base de données
        $db_user = 'Nathan';      // Remplacez par votre nom d'utilisateur RDS
        $db_pass = 'Af18PsKCc-';          // Remplacez par votre mot de passe RDS

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // --- Vérifier si l'utilisateur a le rôle Chauffeur (Optionnel ici, mais bonne pratique) ---
        // Vous pourriez vouloir vérifier que l'utilisateur a le rôle Chauffeur avant d'autoriser l'ajout de véhicule.
        // Ceci est fait sur la page espace_utilisateur.php, mais une revérification ici est plus sécurisée.
        // Cette partie est commentée pour l'instant pour simplifier, mais à considérer.
        /*
        $user_roles_sql = "SELECT COUNT(*) FROM Utilisateur_Role ur JOIN Role r ON ur.role_id = r.role_id WHERE ur.utilisateur_id = :user_id AND r.libelle = 'Chauffeur'";
        $user_roles_stmt = $pdo->prepare($user_roles_sql);
        $user_roles_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_roles_stmt->execute();
        $is_chauffeur = $user_roles_stmt->fetchColumn() > 0;

        if (!$is_chauffeur) {
             http_response_code(403); // Forbidden
             echo json_encode(['error' => 'Seuls les chauffeurs peuvent ajouter des véhicules.']);
             exit();
        }
        */


        // --- Récupérer les données du véhicule à insérer ---
        // Vérifier la présence des champs requis
        if (!isset($data['marque']) || empty($data['marque']) ||
            !isset($data['modele']) || empty($data['modele']) ||
            !isset($data['immatriculation']) || empty($data['immatriculation']) ||
            !isset($data['nombre_places']) || !is_numeric($data['nombre_places']) || $data['nombre_places'] < 1) {
             throw new Exception('Informations véhicule incomplètes ou invalides (Marque, Modèle, Immatriculation, Nombre de places sont requis).');
        }

        $marque_nom = trim($data['marque']);
        $modele = trim($data['modele']);
        $immatriculation = trim($data['immatriculation']);
        $energie = trim($data['energie'] ?? ''); // Utiliser '' si non défini
        $couleur = trim($data['couleur'] ?? ''); // Utiliser '' si non défini
        $date_premiere_immatriculation = $data['date_premiere_immatriculation'] ?? null; // Null si non défini
        $nombre_places = (int)$data['nombre_places'];


        // --- Trouver le marque_id basé sur le nom de la marque ---
        // Assurez-vous que le nom de la colonne pour le nom de la marque dans votre table Marque est correct (ici, supposé être 'libelle')
        $marque_sql = "SELECT marque_id FROM Marque WHERE libelle = :marque_nom LIMIT 1"; // Supposons que la colonne soit 'libelle'
        $marque_stmt = $pdo->prepare($marque_sql);
        $marque_stmt->bindParam(':marque_nom', $marque_nom, PDO::PARAM_STR);
        $marque_stmt->execute();
        $marque = $marque_stmt->fetch();

          if (!$marque) {
            // Marque non trouvée. L'insérer dans la table Marque.
             error_log("Marque '" . $marque_nom . "' non trouvée. Création..."); // Log pour le débogage
             $insert_marque_sql = "INSERT INTO Marque (libelle) VALUES (:marque_nom)"; // Assurez-vous que 'libelle' est le nom correct
             $insert_marque_stmt = $pdo->prepare($insert_marque_sql);
             $insert_marque_stmt->bindParam(':marque_nom', $marque_nom, PDO::PARAM_STR);
             $insert_marque_stmt->execute();

            // Récupérer l'ID de la nouvelle marque insérée
             $marque_id = $pdo->lastInsertId();
             error_log("Marque '" . $marque_nom . "' créée avec ID: " . $marque_id); // Log pour le débogage

        } else {
            // Marque trouvée, récupérer son ID
             $marque_id = $marque['marque_id'];
        }

        // --- Validation finale des données (optionnel mais recommandé) ---
        // Vous pourriez ajouter une validation du format d'immatriculation, de la date, etc.

        // --- Insérer le nouveau véhicule dans la table Voiture ---
        // Assurez-vous que les noms de colonnes correspondent exactement à votre table Voiture
        // et incluez l'utilisateur_id pour lier le véhicule au chauffeur.
        $insert_sql = "INSERT INTO Voiture (marque_id, utilisateur_id, modele, immatriculation, energie, couleur, date_premiere_immatriculation, nombre_places)
                       VALUES (:marque_id, :utilisateur_id, :modele, :immatriculation, :energie, :couleur, :date_premiere_immatriculation, :nombre_places)";

        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->bindParam(':marque_id', $marque_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':utilisateur_id', $user_id, PDO::PARAM_INT); // Lier à l'utilisateur connecté
        $insert_stmt->bindParam(':modele', $modele, PDO::PARAM_STR);
        $insert_stmt->bindParam(':immatriculation', $immatriculation, PDO::PARAM_STR);
        $insert_stmt->bindParam(':energie', $energie, PDO::PARAM_STR);
        $insert_stmt->bindParam(':couleur', $couleur, PDO::PARAM_STR);
        // Gérer la date : si la date est vide, l'insérer comme NULL
        $insert_stmt->bindParam(':date_premiere_immatriculation', $date_premiere_immatriculation, $date_premiere_immatriculation === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $insert_stmt->bindParam(':nombre_places', $nombre_places, PDO::PARAM_INT);

        $insert_stmt->execute();

        // Récupérer l'ID du nouveau véhicule inséré (utile pour l'édition/suppression futures)
        $new_vehicle_id = $pdo->lastInsertId();

        // --- Renvoyer une réponse de succès au frontend ---
        echo json_encode(['success' => 'Véhicule ajouté avec succès !', 'vehicle_id' => $new_vehicle_id]);

    } else {
        // Si la méthode n'est pas POST
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur de base de données lors de l\'ajout du véhicule.']);
    error_log("Erreur PDO dans add_vehicle.php: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs (validation, marque non trouvée, etc.)
    http_response_code(400); // Bad Request pour erreurs de validation/données
    echo json_encode(['error' => $e->getMessage()]); // Renvoyer le message d'erreur spécifique
    error_log("Erreur générale dans add_vehicle.php: " . $e->getMessage());
}
