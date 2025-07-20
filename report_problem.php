<?php
// backend/report_problem.php

session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // À ajuster pour la production
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- VÉRIFICATION D'AUTORISATION (utilisateur connecté) ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Vous devez être connecté pour signaler un problème.']);
    exit();
}
$loggedInUserId = $_SESSION['user_id'];
// --- FIN DE LA VÉRIFICATION D'AUTORISATION ---

// --- CODE DE CONNEXION À LA BASE DE DONNÉES ---
// Inclure votre fichier de connexion OU copier le code de connexion ici
// require_once 'db_connect.php'; // Si vous utilisez un fichier séparé et le chemin est correct
// OU copiez le code de connexion ici si vous ne centralisez pas :
$db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com';
$db_name = 'ecoride_db';
$db_user = 'Nathan';
$db_pass = 'Af18PsKCc-';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données lors de la connexion.']);
    error_log("Erreur PDO lors de la connexion dans report_problem.php: " . $e->getMessage());
    exit();
}
// --- FIN CODE DE CONNEXION ---


// Utiliser un bloc try-catch pour le reste du script
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON.');
        }

        // --- Validation des données reçues ---
        if (!isset($data['ride_id']) || !isset($data['participation_id']) || !isset($data['description'])) {
             throw new Exception('Données manquantes dans la requête.');
        }

        $rideId = (int)$data['ride_id'];
        $participationId = (int)$data['participation_id'];
        $description = trim($data['description']);

        if (empty($description)) {
            throw new Exception('La description du problème ne peut pas être vide.');
        }

        // --- Début de la transaction ---
        $pdo->beginTransaction();

        // 1. Vérifier que la participation existe et appartient à l'utilisateur connecté pour ce trajet
        // Et que le statut de la participation est 'action_en_attente' (ou un statut qui permet le signalement)
        // On vérifie aussi que l'utilisateur n'a pas déjà signalé ce problème ou validé l'action
        $check_participation_sql = "SELECT statut FROM Participation WHERE participation_id = :participationId AND utilisateur_id = :loggedInUserId AND covoiturage_id = :rideId FOR UPDATE"; // Verrouiller la ligne
        $check_participation_stmt = $pdo->prepare($check_participation_sql);
        $check_participation_stmt->bindParam(':participationId', $participationId, PDO::PARAM_INT);
        $check_participation_stmt->bindParam(':loggedInUserId', $loggedInUserId, PDO::PARAM_INT);
        $check_participation_stmt->bindParam(':rideId', $rideId, PDO::PARAM_INT);
        $check_participation_stmt->execute();
        $participation_info = $check_participation_stmt->fetch();

        if (!$participation_info) {
            $pdo->rollBack();
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Participation introuvable ou non valide pour cette action.']);
            exit();
        }

        // Optionnel : Vérifier le statut de la participation si vous voulez limiter quand un signalement est possible
        // if ($participation_info['statut'] === 'litige' || $participation_info['statut'] === 'action_effectuée') {
        //      $pdo->rollBack();
        //      http_response_code(400);
        //      echo json_encode(['error' => 'Cette action a déjà été traitée ou fait l\'objet d\'un litige.']);
        //      exit();
        // }
        // Pour l'instant, on permet de signaler tant que ce n'est pas déjà un litige.

        // 2. Enregistrer le signalement dans la table Signalements
        $insert_signalement_sql = "INSERT INTO Signalements (covoiturage_id, participation_id, utilisateur_id_auteur, description, statut) VALUES (:rideId, :participationId, :auteurId, :description, 'litige')"; // Statut par défaut 'Nouveau'
        $insert_signalement_stmt = $pdo->prepare($insert_signalement_sql);
        $insert_signalement_stmt->bindParam(':rideId', $rideId, PDO::PARAM_INT);
        $insert_signalement_stmt->bindParam(':participationId', $participationId, PDO::PARAM_INT);
        $insert_signalement_stmt->bindParam(':auteurId', $loggedInUserId, PDO::PARAM_INT); // L'auteur du signalement est l'utilisateur connecté
        $insert_signalement_stmt->bindParam(':description', $description, PDO::PARAM_STR);
        // date_signalement sera automatique grâce au DEFAULT CURRENT_TIMESTAMP

        $insert_signalement_stmt->execute();

        // 3. (Optionnel) Mettre à jour le statut de la Participation à 'litige'
        // Ceci marque la participation comme faisant l'objet d'un litige, ce qui peut être utile pour le suivi.
        // Si vous choisissez de faire cela, assurez-vous que le statut 'litige' est une valeur valide pour la colonne statut de la table Participation (VARCHAR).
        $update_participation_status_sql = "UPDATE Participation SET statut = 'litige' WHERE participation_id = :participationId"; // Utilisez le statut 'litige'
        $update_participation_status_stmt = $pdo->prepare($update_participation_status_sql);
        $update_participation_status_stmt->bindParam(':participationId', $participationId, PDO::PARAM_INT);
        $update_participation_status_stmt->execute();


        // --- Fin de la transaction : Valider ---
        $pdo->commit();

        // --- Renvoyer une réponse de succès ---
        echo json_encode(['success' => 'Votre signalement a été enregistré. Nous allons l\'examiner.']);


    } else {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur PDO
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données lors de l\'enregistrement de votre signalement.']);
    error_log("Erreur PDO dans report_problem.php: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs (validation, etc.)
     if ($pdo && $pdo->inTransaction()) { // Vérifier si $pdo est défini avant d'appeler inTransaction
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request pour erreurs de validation/données
    echo json_encode(['error' => $e->getMessage()]); // Renvoyer le message d'erreur spécifique
    error_log("Erreur générale dans report_problem.php: " . $e->getMessage());
}
?>
