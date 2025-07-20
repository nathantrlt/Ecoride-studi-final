<?php
// backend/check_user_status.php

// Démarrer ou reprendre la session PHP
session_start();

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
    // Assurez-vous que la méthode de requête est GET et que les paramètres nécessaires sont présents
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ride_id']) && isset($_GET['ride_price'])) {

        $ride_id = $_GET['ride_id'];
        $ride_price = $_GET['ride_price']; // Récupérer le prix du frontend (à valider côté backend si sensible)

        $is_logged_in = false;
        $user_credits = 0; // Valeur par défaut si non connecté ou crédits non trouvés

        // Vérifier si l'utilisateur est connecté en regardant la variable de session
        if (isset($_SESSION['user_id'])) {
            $is_logged_in = true;
            $user_id = $_SESSION['user_id'];

            // --- Connexion à la base de données pour obtenir les crédits ---
            // Paramètres de connexion à la base de données AWS RDS
            $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // Remplacez par votre Endpoint RDS
            $db_name = 'ecoride_db'; // Remplacez par le nom de votre base de données
            $db_user = 'Nathan';      // Remplacez par votre nom d'utilisateur RDS
            $db_pass = 'Af18PsKCc-';          // Remplacez par votre mot de passe RDS

            // Connexion à la base de données en utilisant PDO
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Récupérer les crédits de l'utilisateur connecté
            $sql = "SELECT credits FROM Utilisateur WHERE utilisateur_id = :user_id LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            $user_data = $stmt->fetch();

            if ($user_data) {
                $user_credits = $user_data['credits'] ?? 0; // Utilise la valeur des crédits ou 0 par défaut
            }
            // else : cela ne devrait pas arriver si user_id est en session, mais géré par le défaut 0
        }

        // --- Renvoyer la réponse au frontend ---
        echo json_encode([
            'is_logged_in' => $is_logged_in,
            'credits' => $user_credits,
            'ride_price' => (float)$ride_price // Renvoyer le prix du trajet (s'assurer qu'il est numérique)
            // Vous pourriez renvoyer l'ID du user connecté si nécessaire, mais il est déjà en session
        ]);

    } else {
        // Si la méthode n'est pas GET ou paramètres manquants
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Requête invalide. Paramètres manquants ou méthode non autorisée.']);
    }

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur de base de données lors de la vérification du statut.']);
    error_log("Erreur PDO dans check_user_status.php: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur interne lors de la vérification du statut.']);
    error_log("Erreur générale dans check_user_status.php: " . $e->getMessage());
}

?>
