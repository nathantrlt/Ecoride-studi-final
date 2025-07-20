<?php
// backend/participate.php

// Démarrer ou reprendre la session PHP
session_start();

// Afficher toutes les erreurs PHP pour le débogage (en développement)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Indique que la réponse est au format JSON

// Permettre les requêtes depuis n'importe quelle origine (pour le développement)
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

        // Vérifier si l'ID du covoiturage est présent et si le décodage a réussi
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON : ' . json_last_error_msg());
        }
        if (!isset($data['ride_id'])) {
             throw new Exception('ID du covoiturage manquant.');
        }

        $ride_id = $data['ride_id'];

        // --- Vérifier si l'utilisateur est connecté ---
        if (!isset($_SESSION['user_id'])) {
            // L'utilisateur n'est pas connecté
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Vous devez être connecté pour participer à un covoiturage.']);
            exit(); // Arrêter l'exécution
        }

        $user_id = $_SESSION['user_id']; // Récupérer l'ID de l'utilisateur connecté

        // --- Connexion à la base de données ---
        // Paramètres de connexion à la base de données AWS RDS
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // Remplacez par votre Endpoint RDS
        $db_name = 'ecoride_db'; // Remplacez par le nom de votre base de données
        $db_user = 'Nathan';      // Remplacez par votre nom d'utilisateur RDS
        $db_pass = 'Af18PsKCc-';          // Remplacez par votre mot de passe RDS

        // Connexion à la base de données en utilisant PDO
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // --- Début de la transaction ---
        $pdo->beginTransaction();

        // --- Récupérer les détails du covoiturage et vérifier les places/prix ---
        $ride_sql = "SELECT covoiturage_id, nb_place, prix_personne, statut FROM Covoiturage WHERE covoiturage_id = :ride_id AND statut = 'Disponible' FOR UPDATE"; // Utiliser FOR UPDATE pour verrouiller la ligne
        $ride_stmt = $pdo->prepare($ride_sql);
        $ride_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $ride_stmt->execute();
        $ride = $ride_stmt->fetch();

        if (!$ride || $ride['nb_place'] <= 0) {
            // Covoiturage non trouvé, non disponible, ou plus de places
            $pdo->rollBack(); // Annuler la transaction
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Covoiturage non trouvé ou plus de places disponibles.']);
            exit();
        }

        $ride_price = $ride['prix_personne']; // Prix du covoiturage

        // --- Récupérer les crédits de l'utilisateur ---
        $user_sql = "SELECT utilisateur_id, credits FROM Utilisateur WHERE utilisateur_id = :user_id FOR UPDATE"; // Utiliser FOR UPDATE pour verrouiller la ligne
        $user_stmt = $pdo->prepare($user_sql);
        $user_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_stmt->execute();
        $user = $user_stmt->fetch();

        if (!$user) {
            // L'utilisateur n'a pas été trouvé dans la BDD (ne devrait pas arriver si user_id est en session, mais bonne pratique de vérifier)
             $pdo->rollBack(); // Annuler la transaction
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => 'Erreur interne: Utilisateur non trouvé dans la base de données.']);
            error_log("Erreur critique: Utilisateur ID " . $user_id . " en session mais non trouvé dans la BDD.");
            exit();
        }

        $user_credits = $user['credits']; // Crédits de l'utilisateur

        // --- Vérifier si l'utilisateur a suffisamment de crédits ---
        if ($user_credits < $ride_price) {
            $pdo->rollBack(); // Annuler la transaction
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Crédits insuffisants pour participer à ce covoiturage.']);
            exit();
        }

        // --- Vérifier si l'utilisateur participe déjà à ce covoiturage ---
        // Cela dépend de la conception de votre table Participation.
        // Supposons une table Participation(participation_id, covoiturage_id, utilisateur_id, statut)
        $check_participation_sql = "SELECT COUNT(*) FROM Participation WHERE covoiturage_id = :ride_id AND utilisateur_id = :user_id";
        $check_participation_stmt = $pdo->prepare($check_participation_sql);
        $check_participation_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $check_participation_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $check_participation_stmt->execute();
        $participation_count = $check_participation_stmt->fetchColumn();

        if ($participation_count > 0) {
            $pdo->rollBack(); // Annuler la transaction
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Vous participez déjà à ce covoiturage.']);
            exit();
        }


        // --- Toutes les vérifications sont réussies. Exécuter les mises à jour ---

        // 1. Insérer l'enregistrement de participation
        // Assurez-vous que le nom de la table et des colonnes correspondent à votre schéma
        // Supposons que la table Participation a les colonnes covoiturage_id, utilisateur_id, et statut
        $insert_participation_sql = "INSERT INTO Participation (covoiturage_id, utilisateur_id, statut) VALUES (:ride_id, :user_id, 'Confirmée')"; // Statut par défaut 'Confirmée' ou 'En attente'
        $insert_participation_stmt = $pdo->prepare($insert_participation_sql);
        $insert_participation_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $insert_participation_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $insert_participation_stmt->execute();

        // 2. Déduire les crédits de l'utilisateur
        $new_credits = $user_credits - $ride_price;
        $update_credits_sql = "UPDATE Utilisateur SET credits = :new_credits WHERE utilisateur_id = :user_id";
        $update_credits_stmt = $pdo->prepare($update_credits_sql);
        $update_credits_stmt->bindParam(':new_credits', $new_credits, PDO::PARAM_INT); // Assurez-vous du type INT
        $update_credits_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $update_credits_stmt->execute();

        // 3. Mettre à jour le nombre de places disponibles dans le covoiturage
        $new_nb_place = $ride['nb_place'] - 1;
        $update_place_sql = "UPDATE Covoiturage SET nb_place = :new_nb_place WHERE covoiturage_id = :ride_id";
        $update_place_stmt = $pdo->prepare($update_place_sql);
        $update_place_stmt->bindParam(':new_nb_place', $new_nb_place, PDO::PARAM_INT);
        $update_place_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $update_place_stmt->execute();

        // --- Fin de la transaction : Valider ---
        $pdo->commit();

        // --- Renvoyer une réponse de succès avec le nouveau nombre de places ---
        // Utiliser http_response_code(200); par défaut si le succès
        echo json_encode([
            'success' => 'Participation enregistrée avec succès !',
            'new_available_seats' => $new_nb_place, // Renvoyer le nouveau nombre de places
            'new_user_credits' => $new_credits // Renvoyer les nouveaux crédits de l'utilisateur (optionnel)
        ]);

    } else {
        // Si la méthode n'est pas POST
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    // Gérer les erreurs de base de données (et annuler la transaction si elle était active)
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur de base de données lors de l\'enregistrement de la participation.']);
    error_log("Erreur PDO lors de la participation: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request ou 500 selon le type d'erreur
    echo json_encode(['error' => $e->getMessage()]); // Renvoyer le message d'erreur spécifique
    error_log("Erreur générale lors de la participation: " . $e->getMessage());
}

?>
