<?php
// backend/validate_review.php

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

// --- VÉRIFICATION D'AUTORISATION (utilisateur connecté ET rôle Employé) ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Vous devez être connecté pour effectuer cette action.']);
    exit();
}
$loggedInUserId = $_SESSION['user_id'];

// --- CODE DE CONNEXION À LA BASE DE DONNÉES ---
// Inclure votre fichier de connexion OU copier le code de connexion ici
// require_once 'db_connect.php'; // Si vous utilisez un fichier séparé
// OU copiez le code de connexion ici :
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
    error_log("Erreur PDO lors de la connexion dans validate_review.php: " . $e->getMessage());
    exit();
}
// --- FIN CODE DE CONNEXION ---

// --- Vérification spécifique du rôle Employé ---
$isEmployee = false;
$check_role_sql = "SELECT COUNT(*) FROM Utilisateur_Role ur JOIN Role r ON ur.role_id = r.role_id WHERE ur.utilisateur_id = :userId AND r.libelle = 'Employe'";
$check_role_stmt = $pdo->prepare($check_role_sql);
$check_role_stmt->bindParam(':userId', $loggedInUserId, PDO::PARAM_INT);
$check_role_stmt->execute();
if ($check_role_stmt->fetchColumn() > 0) {
    $isEmployee = true;
}

if (!$isEmployee) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Accès refusé. Seuls les employés peuvent effectuer cette action.']);
    exit();
}
// --- FIN Vérification spécifique du rôle Employé ---


// Utiliser un bloc try-catch pour le reste du script
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON.');
        }

        // --- Validation des données reçues ---
        if (!isset($data['avis_id'])) {
             throw new Exception('ID de l\'avis manquant.');
        }

        $avisId = (int)$data['avis_id']; // Assurez-vous que le frontend envoie 'avis_id' ou adaptez ici ('review_id')

        // --- Début de la transaction ---
        $pdo->beginTransaction();

        // 1. Vérifier que l'avis existe et est en attente de validation
        // Et récupérer l'ID du chauffeur destinataire et la note de cet avis
        $check_avis_sql = "SELECT utilisateur_id_destinataire, note FROM Avis WHERE avis_id = :avisId AND statut = 'En attente de validation' FOR UPDATE"; // Verrouiller la ligne
        $check_avis_stmt = $pdo->prepare($check_avis_sql);
        $check_avis_stmt->bindParam(':avisId', $avisId, PDO::PARAM_INT);
        $check_avis_stmt->execute();
        $avis_info = $check_avis_stmt->fetch();

        if (!$avis_info) {
            $pdo->rollBack();
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Avis introuvable ou non en attente de validation.']);
            exit();
        }

        $driverUserId = $avis_info['utilisateur_id_destinataire'];
        $validatedNote = $avis_info['note']; // La note de l'avis qui vient d'être validé

        // 2. Mettre à jour le statut de l'avis à 'Validé'
        // Assurez-vous que la colonne date_validation existe si vous l'utilisez
        $update_avis_status_sql = "UPDATE Avis SET statut = 'Validé' WHERE avis_id = :avisId"; // Utilisez 'Validé'
        $update_avis_status_stmt = $pdo->prepare($update_avis_status_sql);
        $update_avis_status_stmt->bindParam(':avisId', $avisId, PDO::PARAM_INT);
        $update_avis_status_stmt->execute();

        // 3. Calculer la nouvelle note moyenne du chauffeur (en incluant l'avis validé)
        // Il faut sélectionner toutes les notes VALIDÉES pour ce chauffeur et calculer la moyenne.
        $calculate_average_note_sql = "SELECT AVG(note) FROM Avis WHERE utilisateur_id_destinataire = :driverId AND statut = 'Validé'"; // Calculer la moyenne des notes VALIDÉES
        $calculate_average_note_stmt = $pdo->prepare($calculate_average_note_sql);
        $calculate_average_note_stmt->bindParam(':driverId', $driverUserId, PDO::PARAM_INT);
        $calculate_average_note_stmt->execute();
        $averageNote = $calculate_average_note_stmt->fetchColumn(); // Récupère la moyenne

        // Mettre à jour la table Utilisateur avec la nouvelle note moyenne du chauffeur
        // Assurez-vous que votre table Utilisateur a bien une colonne 'note_moyenne' (type FLOAT ou DECIMAL)
        $update_driver_average_note_sql = "UPDATE Utilisateur SET note_moyenne = :averageNote WHERE utilisateur_id = :driverId"; // Supposons une colonne 'note_moyenne'
        $update_driver_average_note_stmt = $pdo->prepare($update_driver_average_note_sql);
        // Gérer le cas où il n'y a pas encore d'avis validé pour ce chauffeur (moyenne est NULL)
        $averageNoteToSave = ($averageNote !== null) ? round($averageNote, 2) : null; // Arrondir à 2 décimales ou mettre NULL
        $update_driver_average_note_stmt->bindParam(':averageNote', $averageNoteToSave, PDO::PARAM_STR); // Utiliser PARAM_STR pour les float/decimal ou le type approprié
        $update_driver_average_note_stmt->bindParam(':driverId', $driverUserId, PDO::PARAM_INT);
        $update_driver_average_note_stmt->execute();


        // --- Fin de la transaction : Valider ---
        $pdo->commit();

        // --- Renvoyer une réponse de succès ---
        echo json_encode(['success' => 'Avis validé avec succès. La note moyenne du chauffeur a été mise à jour.']);


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
    echo json_encode(['error' => 'Erreur de base de données lors de la validation de l\'avis.']);
    error_log("Erreur PDO dans validate_review.php: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs (validation, etc.)
     if ($pdo && $pdo->inTransaction()) { // Vérifier si $pdo est défini avant d'appeler inTransaction
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request pour erreurs de logique/validation
    echo json_encode(['error' => $e->getMessage()]); // Renvoyer le message d'erreur spécifique
    error_log("Erreur générale dans validate_review.php: " . $e->getMessage());
}
?>
