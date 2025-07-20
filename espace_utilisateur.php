<?php
// backend/espace_utilisateur.php

// Démarrer ou reprendre la session PHP
session_start();

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
    // Assurez-vous que la méthode de requête est GET
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // --- Vérifier si l'utilisateur est connecté ---
        if (!isset($_SESSION['user_id'])) {
            // L'utilisateur n'est pas connecté
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Vous devez être connecté pour accéder à votre espace.']);
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

// --- Récupérer les informations de l'utilisateur ---
        $user_info_sql = "SELECT
                                utilisateur_id,
                                pseudo,
                                email,
                                credits,
                                nom,
                                prenom,
                                fumeur, -- Assurez-vous d'inclure toutes les infos utilisateur pertinentes
                                animaux   -- Assurez-vous d'inclure toutes les infos utilisateur pertinentes
                            FROM Utilisateur
                            WHERE utilisateur_id = :user_id LIMIT 1"; // Sélectionne les colonnes d'info utilisateur
        $user_info_stmt = $pdo->prepare($user_info_sql);
        $user_info_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_info_stmt->execute();
        $user_info = $user_info_stmt->fetch();

        if (!$user_info) {
            // L'utilisateur n'a pas été trouvé (ne devrait pas arriver si user_id est en session, mais bonne pratique)
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => 'Erreur interne: Profil utilisateur non trouvé.']);
             error_log("Erreur critique dans espace_utilisateur.php: Utilisateur ID " . $user_id . " en session mais non trouvé dans la BDD.");
            exit();
        }

// --- Récupérer les rôles de l'utilisateur ---
        $user_roles_sql = "SELECT
                                r.libelle AS nom_role -- Sélectionne la colonne 'libelle' de la table 'Role' (aliassée 'r')
                            FROM Utilisateur_Role ur -- Utilise la table de liaison Utilisateur_Role
                            JOIN Role r ON ur.role_id = r.role_id -- Jointure avec la table Role (aliassée 'r')
                            WHERE ur.utilisateur_id = :user_id"; // Filtre par l'utilisateur connecté
        $user_roles_stmt = $pdo->prepare($user_roles_sql);
        $user_roles_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_roles_stmt->execute();
        $user_roles = array_column($user_roles_stmt->fetchAll(PDO::FETCH_ASSOC), 'nom_role'); // Récupérer la liste des noms de rôles

        // --- Récupérer les covoiturages de l'utilisateur (en tant que participant OU chauffeur) ---
        $user_rides_sql = "
            SELECT
                c.covoiturage_id,
                c.date_depart,
                c.heure_depart,
                c.lieu_depart,
                c.date_arrivee,
                c.heure_arrivee,
                c.lieu_arrivee,
                c.prix_personne,
                c.statut AS covoiturage_statut, -- Statut du covoiturage
                c.nb_place, -- Inclure nb_place pour les chauffeurs
                -- Utiliser CASE pour déterminer le rôle de l'utilisateur connecté POUR CE TRAJET SPÉCIFIQUE
                CASE
                    WHEN c.utilisateur_id = :user_id THEN 'Chauffeur' -- Si l'utilisateur connecté est le chauffeur du trajet 'c'
                    WHEN p.utilisateur_id IS NOT NULL THEN 'Participant' -- Sinon, si l'utilisateur connecté est trouvé dans la table Participation 'p' pour ce trajet
                    ELSE 'Inconnu' -- Ne devrait pas arriver avec la clause WHERE
                END AS role,
                p.statut AS participation_statut, -- Statut spécifique de la participation (pour les participants)
                d.pseudo AS driver_pseudo -- Pseudo du chauffeur du trajet (utile pour les participants)
            FROM Covoiturage c
            LEFT JOIN Participation p ON c.covoiturage_id = p.covoiturage_id AND p.utilisateur_id = :user_id -- Jointure gauche pour les participations de l'utilisateur connecté
            LEFT JOIN Utilisateur d ON c.utilisateur_id = d.utilisateur_id -- Jointure gauche pour obtenir le pseudo du chauffeur du trajet
            WHERE c.utilisateur_id = :user_id OR p.utilisateur_id = :user_id -- Sélectionner les trajets où l'utilisateur est chauffeur OU participant
            ORDER BY c.date_depart DESC, c.heure_depart DESC
        ";
        $user_rides_stmt = $pdo->prepare($user_rides_sql);
        $user_rides_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $user_rides_stmt->execute();
        $user_rides = $user_rides_stmt->fetchAll(); // <-- Met à jour la variable $user_rides

        // --- Récupérer les véhicules de l'utilisateur (s'il est chauffeur) ---
        $user_vehicles = []; // Initialiser un tableau vide par défaut
        if (in_array('Chauffeur', $user_roles)) { // Vérifier si l'utilisateur a le rôle 'Chauffeur'
            // Supposons une table `Voiture` avec `voiture_id`, `utilisateur_id`, `marque_id`, `modele`, `immatriculation`, `energie`, `couleur`, `date_premiere_immatriculation`, `nombre_places`
            // et une table `Marque` avec `marque_id`, `libelle`
            $user_vehicles_sql = "SELECT
                                v.voiture_id,
                                m.libelle AS marque, -- Sélectionne le libelle de la table Marque (aliassée 'm') et l'alias 'marque' pour le frontend
                                v.modele,
                                v.immatriculation,
                                v.energie,
                                v.couleur,
                                v.date_premiere_immatriculation,
                                v.nombre_places
                            FROM Voiture v -- Alias 'v' pour la table Voiture
                            JOIN Marque m ON v.marque_id = m.marque_id -- Jointure avec la table Marque sur la clé étrangère marque_id
                            WHERE v.utilisateur_id = :user_id";
            $user_vehicles_stmt = $pdo->prepare($user_vehicles_sql);
            $user_vehicles_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $user_vehicles_stmt->execute();
            $user_vehicles = $user_vehicles_stmt->fetchAll(); // Récupérer toutes les voitures
        }


         // --- Vérifier si les informations du chauffeur sont incomplètes ---
         $chauffeur_info_incomplete = false;
         // S'assurer que $user_roles est bien un tableau et contient 'Chauffeur'
         $is_chauffeur = is_array($user_roles) && in_array('Chauffeur', $user_roles);

         if ($is_chauffeur) {
             // Condition d'incomplétude : pas de véhicules enregistrés
             if (empty($user_vehicles) || !is_array($user_vehicles)) { // Vérifier aussi si $user_vehicles est un tableau
                 $chauffeur_info_incomplete = true;
             }
             // Vous pourriez ajouter d'autres vérifications ici si d'autres infos sont requises pour les chauffeurs.
         }


        // --- Renvoyer toutes les données au format JSON ---
        echo json_encode([
            'user_info' => $user_info, // Informations de l'utilisateur
            'user_roles' => $user_roles, // Liste des rôles de l'utilisateur
            'user_rides' => $user_rides, // Liste de TOUS les covoiturages de l'utilisateur (chauffeur et participant)
            'user_vehicles' => $user_vehicles, // Liste des véhicules de l'utilisateur (si chauffeur)
            'chauffeur_info_incomplete' => $chauffeur_info_incomplete // Ajouter l'état d'incomplétude du profil chauffeur
        ]);


    } else {
        // Si la méthode n'est pas GET
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur de base de données lors du chargement de votre espace.']);
    error_log("Erreur PDO dans espace_utilisateur.php: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => $e->getMessage()]); // Renvoyer le message d'erreur spécifique
    error_log("Erreur générale dans espace_utilisateur.php: " . $e->getMessage());
}

?>
