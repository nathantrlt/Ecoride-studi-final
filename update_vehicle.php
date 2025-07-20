<?php
// backend/update_vehicle.php

// Démarrer ou reprendre la session PHP
session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Indique que la réponse est au format JSON

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Ou PUT
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Ou PUT
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON.');
        }

        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Vous devez être connecté pour modifier un véhicule.']);
            exit();
        }
        $user_id = $_SESSION['user_id'];

        // Vérifier si l'ID du véhicule à modifier et les données sont présentes
        if (!isset($data['voiture_id']) || !isset($data['marque']) || empty($data['marque']) ||
            !isset($data['modele']) || empty($data['modele']) ||
            !isset($data['immatriculation']) || empty($data['immatriculation']) ||
            !isset($data['nombre_places']) || !is_numeric($data['nombre_places']) || $data['nombre_places'] < 1) {
             throw new Exception('Informations véhicule incomplètes ou invalides pour la mise à jour.');
        }

        $voiture_id = (int)$data['voiture_id']; // ID du véhicule à modifier
        $marque_nom = trim($data['marque']);
        $modele = trim($data['modele']);
        $immatriculation = trim($data['immatriculation']);
        $energie = trim($data['energie'] ?? '');
        $couleur = trim($data['couleur'] ?? '');
        $date_premiere_immatriculation = $data['date_premiere_immatriculation'] ?? null;
        $nombre_places = (int)$data['nombre_places'];

        // Connexion à la base de données
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // Remplacez
        $db_name = 'ecoride_db'; // Remplacez
        $db_user = 'Nathan';      // Remplacez
        $db_pass = 'Af18PsKCc-';          // Remplacez

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // --- Vérifier que le véhicule appartient bien à l'utilisateur connecté ---
        $check_owner_sql = "SELECT COUNT(*) FROM Voiture WHERE voiture_id = :voiture_id AND utilisateur_id = :user_id";
        $check_owner_stmt = $pdo->prepare($check_owner_sql);
        $check_owner_stmt->bindParam(':voiture_id', $voiture_id, PDO::PARAM_INT);
        $check_owner_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $check_owner_stmt->execute();
        $is_owner = $check_owner_stmt->fetchColumn() > 0;

        if (!$is_owner) {
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Vous n\'êtes pas autorisé à modifier ce véhicule.']);
            exit();
        }

        // --- Trouver le marque_id basé sur le nom de la marque (et créer si nécessaire) ---
        // Reutiliser la logique de add_vehicle.php
        $marque_sql = "SELECT marque_id FROM Marque WHERE libelle = :marque_nom LIMIT 1";
        $marque_stmt = $pdo->prepare($marque_sql);
        $marque_stmt->bindParam(':marque_nom', $marque_nom, PDO::PARAM_STR);
        $marque_stmt->execute();
        $marque = $marque_stmt->fetch();

        if (!$marque) {
             // Marque non trouvée. L'insérer.
             $insert_marque_sql = "INSERT INTO Marque (libelle) VALUES (:marque_nom)";
             $insert_marque_stmt = $pdo->prepare($insert_marque_sql);
             $insert_marque_stmt->bindParam(':marque_nom', $marque_nom, PDO::PARAM_STR);
             $insert_marque_stmt->execute();
             $marque_id = $pdo->lastInsertId();
        } else {
             $marque_id = $marque['marque_id'];
        }


        // --- Mettre à jour les informations du véhicule dans la table Voiture ---
        $update_sql = "UPDATE Voiture
                       SET marque_id = :marque_id,
                           modele = :modele,
                           immatriculation = :immatriculation,
                           energie = :energie,
                           couleur = :couleur,
                           date_premiere_immatriculation = :date_premiere_immatriculation,
                           nombre_places = :nombre_places
                       WHERE voiture_id = :voiture_id
                       AND utilisateur_id = :user_id"; // Double vérification de l'appartenance

        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->bindParam(':marque_id', $marque_id, PDO::PARAM_INT);
        $update_stmt->bindParam(':modele', $modele, PDO::PARAM_STR);
        $update_stmt->bindParam(':immatriculation', $immatriculation, PDO::PARAM_STR);
        $update_stmt->bindParam(':energie', $energie, PDO::PARAM_STR);
        $update_stmt->bindParam(':couleur', $couleur, PDO::PARAM_STR);
        $update_stmt->bindParam(':date_premiere_immatriculation', $date_premiere_immatriculation, $date_premiere_immatriculation === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $update_stmt->bindParam(':nombre_places', $nombre_places, PDO::PARAM_INT);
        $update_stmt->bindParam(':voiture_id', $voiture_id, PDO::PARAM_INT);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT); // Assurez-vous de mettre à jour le véhicule du bon utilisateur

        $update_stmt->execute();

        // Vérifier si des lignes ont été affectées
        $rows_affected = $update_stmt->rowCount();


        // --- Renvoyer une réponse de succès au frontend ---
        echo json_encode(['success' => 'Véhicule mis à jour avec succès !']);

    } else {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur de base de données lors de la mise à jour du véhicule.']);
    error_log("Erreur PDO dans update_vehicle.php: " . $e->getMessage());

} catch (Exception $e) {
    http_response_code(400); // Bad Request ou 500
    echo json_encode(['error' => $e->getMessage()]);
    error_log("Erreur générale dans update_vehicle.php: " . $e->getMessage());
}
?>
