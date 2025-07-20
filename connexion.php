<?php
// backend/login.php

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

        error_log("Données JSON reçues dans login.php: " . $json_data); // LOG 1

        // Vérifier si les données JSON nécessaires sont présentes et si le décodage a réussi
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON : ' . json_last_error_msg());
        }

        // Vérifier si l'identifiant et le mot de passe sont présents
        if (!isset($data['identifier']) || !isset($data['password'])) {
             throw new Exception('Identifiant ou mot de passe manquant.');
        }

        $identifier = trim($data['identifier']); // Email ou pseudo
        $password = $data['password']; // Mot de passe en clair

        error_log("Identifiant reçu (trimmed): " . $identifier); // LOG 2
        error_log("Mot de passe reçu: " . $password); // LOG 3 (Attention, ne pas loguer les mots de passe en production !)

        // --- Validation des données côté backend ---
        if (empty($identifier) || empty($password)) {
             throw new Exception('Veuillez remplir tous les champs.');
        }
        // Vous pouvez ajouter ici des validations de format si nécessaire (par exemple, vérifier si l'identifiant ressemble à un email)

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

        // --- Rechercher l'utilisateur dans la base de données ---
        // Nous recherchons par email OU par pseudo (selon ce que l'utilisateur a entré dans le champ 'identifier')
        $sql = "SELECT utilisateur_id, pseudo, email, password, credits, fumeur, animaux FROM Utilisateur WHERE email = :identifier OR pseudo = :identifier LIMIT 1";

        error_log("Requête SQL de recherche dans login.php: " . $sql); // LOG 4


        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':identifier', $identifier, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(); // Récupérer la ligne utilisateur (s'il y en a une)

        error_log("Résultat de la recherche utilisateur (fetch): " . ($user ? print_r($user, true) : 'Utilisateur non trouvé')); // LOG 6


        // --- Vérifier l'utilisateur et le mot de passe ---
        if ($user && password_verify($password, $user['password'])) {

          error_log("password_verify SUCCESS pour l'utilisateur: " . $user['pseudo']); // LOG 7

            // Utilisateur trouvé et mot de passe correct

            // --- Démarrer et gérer la session ---
            // Effacer les anciennes données de session au cas où (sécurité)
            session_unset();
            // Régénérer l'ID de session (sécurité contre la fixation de session)
            session_regenerate_id(true);

            // Stocker les informations de l'utilisateur dans la session PHP
            // NE JAMAIS STOCKER LE MOT DE PASSE EN SESSION !
            $_SESSION['user_id'] = $user['utilisateur_id'];
            $_SESSION['pseudo'] = $user['pseudo'];
            $_SESSION['email'] = $user['email'];
            // Vous pouvez stocker d'autres informations utiles en session (rôle, crédits, etc.)
            // $_SESSION['credits'] = $user['credits'];
            // $_SESSION['is_logged_in'] = true; // Un indicateur si nécessaire

            // --- Vérifier si l'utilisateur est un employé ---
            $sql_check_role = "SELECT COUNT(*) AS is_employe FROM Utilisateur_Role WHERE utilisateur_id = :user_id AND role_id = 3"; // Utiliser role_id = 3 pour Employé
            $stmt_check_role = $pdo->prepare($sql_check_role);
            $stmt_check_role->bindParam(':user_id', $user['utilisateur_id'], PDO::PARAM_INT);
            $stmt_check_role->execute();
            $role_result = $stmt_check_role->fetch(PDO::FETCH_ASSOC);

            $_SESSION['is_employe'] = ($role_result['is_employe'] > 0); // Stocker le statut d'employé dans la session
    error_log("Valeur de \$_SESSION['is_employe'] après connexion : " . ($_SESSION['is_employe'] ? 'true' : 'false'));



            // --- Renvoyer une réponse de succès au frontend ---
            // Inclure le statut d'employé dans la réponse pour que le frontend puisse rediriger
            echo json_encode([
                'success' => 'Connexion réussie !',
                'user' => ['pseudo' => $user['pseudo'], 'email' => $user['email']],
                'is_employe' => $_SESSION['is_employe'] // Envoyer aussi au frontend
            ]);


        } else {
                      error_log("Échec de la connexion: Utilisateur non trouvé ou password_verify FAILED"); // LOG 8
            // Utilisateur non trouvé ou mot de passe incorrect
            // Il est préférable de donner un message générique pour ne pas indiquer si c'est l'identifiant ou le mot de passe qui est faux (sécurité)
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Identifiant ou mot de passe incorrect.']);
        }

    } else {
        // Si la méthode n'est pas POST
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur de base de données lors de la connexion.']);
    error_log("Erreur PDO lors de la connexion: " . $e->getMessage());
        error_log("Erreur PDO lors de la connexion: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs (validation, etc.)
    http_response_code(400); // Bad Request pour erreurs de validation/données
    echo json_encode(['error' => $e->getMessage()]); // Renvoyer le message d'erreur spécifique
    error_log("Erreur générale lors de la connexion: " . $e->getMessage());
}

?>
