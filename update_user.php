<?php
// backend/update_user.php

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

        // Vérifier si des données sont reçues et si le décodage a réussi
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON : ' . json_last_error_msg());
        }
        // Vérifier si les champs attendus sont présents dans les données reçues
        // Adaptez cette vérification en fonction des champs que vous permettez de modifier
        if (!isset($data['nom']) || !isset($data['prenom']) || !isset($data['fumeur']) || !isset($data['animaux'])|| !isset($data['is_chauffeur'])) {
             throw new Exception('Données de mise à jour manquantes.');
        }


        // --- Vérifier si l'utilisateur est connecté ---
        if (!isset($_SESSION['user_id'])) {
            // L'utilisateur n'est pas connecté
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Vous devez être connecté pour modifier votre profil.']);
            exit(); // Arrêter l'exécution
        }

        $user_id = $_SESSION['user_id']; // Récupérer l'ID de l'utilisateur connecté

        // Récupérer les données à mettre à jour
        $nom = trim($data['nom']);
        $prenom = trim($data['prenom']);
        $fumeur = $data['fumeur']; // Devrait être 'oui' ou 'non'
        $animaux = $data['animaux']; // Devrait être 'oui' ou 'non'
        $is_chauffeur_requested = (bool)$data['is_chauffeur']; // Récupérer l'état de la case à cocher comme booléen

        // --- Validation des données reçues (optionnel mais recommandé) ---
        // Vous pourriez ajouter des vérifications pour le format du nom/prénom, etc.
        if ($fumeur !== 'oui' && $fumeur !== 'non') {
             throw new Exception('Valeur invalide pour fumeur.');
        }
        if ($animaux !== 'oui' && $animaux !== 'non') {
             throw new Exception('Valeur invalide pour animaux.');
        }


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


        // --- Mettre à jour les informations de l'utilisateur ---
        // Assurez-vous que les noms de colonnes correspondent exactement à votre table Utilisateur
        $update_sql = "UPDATE Utilisateur
                       SET nom = :nom,
                           prenom = :prenom,
                           fumeur = :fumeur,
                           animaux = :animaux
                       WHERE utilisateur_id = :user_id"; // Mettre à jour SEULEMENT l'utilisateur connecté

        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $update_stmt->bindParam(':prenom', $prenom, PDO::PARAM_STR);
        $update_stmt->bindParam(':fumeur', $fumeur, PDO::PARAM_STR);
        $update_stmt->bindParam(':animaux', $animaux, PDO::PARAM_STR);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT); // Utiliser l'ID de l'utilisateur connecté

        $update_stmt->execute();
 // --- Gérer la demande de rôle Chauffeur ---
        if ($is_chauffeur_requested) {
             // L'utilisateur a coché la case "Je souhaite devenir Chauffeur"

             // 1. Trouver l'ID du rôle "Chauffeur"
             $role_chauffeur_sql = "SELECT role_id FROM Role WHERE libelle = 'Chauffeur' LIMIT 1"; // Supposons que le libellé est 'Chauffeur'
             $role_chauffeur_stmt = $pdo->query($role_chauffeur_sql);
             $role_chauffeur = $role_chauffeur_stmt->fetch();

             if (!$role_chauffeur) {
                 // Rôle "Chauffeur" non trouvé dans la table Role (erreur de configuration de la BDD)
                 // Ne devrait pas arriver si la BDD est correctement configurée
                 error_log("Rôle 'Chauffeur' non trouvé dans la table Role !");
                 // On peut soit lancer une exception, soit ignorer la demande de rôle
                 // Pour l'instant, on va ignorer silencieusement la demande de rôle et juste mettre à jour le profil
                 // Vous pourriez vouloir renvoyer une erreur plus explicite si ce cas est possible en production
                 // throw new Exception("Erreur de configuration des rôles.");
             } else {
                  $role_chauffeur_id = $role_chauffeur['role_id'];

                 // 2. Vérifier si l'utilisateur a déjà ce rôle
                 $check_role_sql = "SELECT COUNT(*) FROM Utilisateur_Role WHERE utilisateur_id = :user_id AND role_id = :role_id";
                 $check_role_stmt = $pdo->prepare($check_role_sql);
                 $check_role_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                 $check_role_stmt->bindParam(':role_id', $role_chauffeur_id, PDO::PARAM_INT);
                 $check_role_stmt->execute();
                 $has_role = $check_role_stmt->fetchColumn() > 0;

                 if (!$has_role) {
                     // L'utilisateur n'a PAS encore le rôle Chauffeur, l'ajouter
                     $add_role_sql = "INSERT INTO Utilisateur_Role (utilisateur_id, role_id) VALUES (:user_id, :role_id)";
                     $add_role_stmt = $pdo->prepare($add_role_sql);
                     $add_role_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                     $add_role_stmt->bindParam(':role_id', $role_chauffeur_id, PDO::PARAM_INT);
                     $add_role_stmt->execute();
                     error_log("Rôle 'Chauffeur' ajouté pour utilisateur " . $user_id); // Log l'ajout du rôle

                     // Optionnel : Mettre à jour la session si vous y stockez les rôles
                     // if (!in_array('Chauffeur', $_SESSION['user_roles'])) {
                     //      $_SESSION['user_roles'][] = 'Chauffeur';
                     // }
                 } else {
                     // L'utilisateur a déjà le rôle Chauffeur, ne rien faire (c'est normal)
                     error_log("Utilisateur " . $user_id . " a déjà le rôle 'Chauffeur'.");
                 }
             }

        } else {
             // L'utilisateur n'a PAS coché la case "Je souhaite devenir Chauffeur"
             // Nous ne faisons rien ici (ne supprimons pas le rôle existant si l'utilisateur le décoche)
             error_log("Demande de rôle 'Chauffeur' non faite ou décochée pour utilisateur " . $user_id . ". Rôle non supprimé.");
        }
        // Vérifier si des lignes ont été affectées (si la mise à jour a réellement eu lieu)
        $rows_affected = $update_stmt->rowCount();
        if ($rows_affected === 0) {
            // Cela peut arriver si les données envoyées sont identiques aux données actuelles,
            // ou si l'utilisateur n'existe pas (ce qui ne devrait pas arriver si user_id est en session)
            // Gérer cela comme un succès ou une notification
            // throw new Exception("Aucune modification enregistrée (données identiques ou utilisateur non trouvé)."); // Ou juste un message d'info
             error_log("Aucune ligne affectée par la mise à jour pour user_id " . $user_id . ". Données peut-être identiques.");
        }


        // --- Renvoyer une réponse de succès au frontend ---
        echo json_encode(['success' => 'Profil mis à jour avec succès !']);

    } else {
        // Si la méthode n'est pas POST
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur de base de données lors de la mise à jour du profil.']);
    error_log("Erreur PDO dans update_user.php: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs (validation, etc.)
    http_response_code(400); // Bad Request ou 500
    echo json_encode(['error' => $e->getMessage()]); // Renvoyer le message d'erreur spécifique
    error_log("Erreur générale dans update_user.php: " . $e->getMessage());
}

?>
