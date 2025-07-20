<?php
// backend/submit_participant_action.php

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
    echo json_encode(['error' => 'Vous devez être connecté pour effectuer cette action.']);
    exit();
}
$loggedInUserId = $_SESSION['user_id'];
// --- FIN DE LA VÉRIFICATION D'AUTORISATION ---

// --- CODE DE CONNEXION À LA BASE DE DONNÉES ---
// Inclure votre fichier de connexion OU copier le code de connexion ici
// require_once 'db_connect.php'; // Si vous utilisez un fichier séparé
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
    error_log("Erreur PDO lors de la connexion dans submit_participant_action.php: " . $e->getMessage());
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
        // On a besoin de ride_id et participation_id pour vérifier la participation,
        // driver_user_id pour le destinataire de l'avis, et note/commentaire pour l'avis.
        if (!isset($data['ride_id']) || !isset($data['participation_id']) || !isset($data['driver_user_id']) || !isset($data['note']) || !isset($data['commentaire'])) {
             throw new Exception('Données manquantes dans la requête.');
        }

        $rideId = (int)$data['ride_id'];
        $participationId = (int)$data['participation_id'];
        $driverUserId = (int)$data['driver_user_id']; // Le chauffeur est le destinataire de l'avis
        $note = (int)$data['note'];
        $commentaire = trim($data['commentaire']);

        // Validation basique de la note
        if ($note < 1 || $note > 5) {
             throw new Exception('Note invalide. Doit être entre 1 et 5.');
        }

        // --- Début de la transaction ---
        $pdo->beginTransaction();

        // 1. Vérifier que la participation existe et appartient à l'utilisateur connecté pour ce trajet
        // Et que le statut de la participation est bien 'action_en_attente'
        // On récupère aussi le prix du trajet ici pour la gestion des crédits
        $check_participation_sql = "SELECT p.statut, c.prix_personne FROM Participation p JOIN Covoiturage c ON p.covoiturage_id = c.covoiturage_id WHERE p.participation_id = :participationId AND p.utilisateur_id = :loggedInUserId AND p.covoiturage_id = :rideId FOR UPDATE"; // Verrouiller la ligne
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

        if ($participation_info['statut'] !== 'action_en_attente') {
            $pdo->rollBack();
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Cette participation n\'est pas en attente d\'action.']);
            exit();
        }

        // 2. Enregistrer l'avis et la note
        // *** MODIFIÉ : Retrait de covoiturage_id de la requête INSERT Avis ***
        $insert_avis_sql = "INSERT INTO Avis (utilisateur_id_auteur, utilisateur_id_destinataire, note, commentaire, statut) VALUES (:auteurId, :destinataireId, :note, :commentaire, 'En attente de validation')"; // 'En attente de validation' est un statut pour l'avis (par employé)
        $insert_avis_stmt = $pdo->prepare($insert_avis_sql);
        $insert_avis_stmt->bindParam(':auteurId', $loggedInUserId, PDO::PARAM_INT); // L'auteur de l'avis est le participant
        $insert_avis_stmt->bindParam(':destinataireId', $driverUserId, PDO::PARAM_INT); // Le destinataire est le chauffeur
        $insert_avis_stmt->bindParam(':note', $note, PDO::PARAM_INT);
        $insert_avis_stmt->bindParam(':commentaire', $commentaire, PDO::PARAM_STR);
        $insert_avis_stmt->execute();

        // 3. Mettre à jour le statut de la Participation à 'action_effectuée'
        $update_participation_status_sql = "UPDATE Participation SET statut = 'action_effectuée' WHERE participation_id = :participationId"; // Utilisez le statut que vous avez choisi
        $update_participation_status_stmt = $pdo->prepare($update_participation_status_sql);
        $update_participation_status_stmt->bindParam(':participationId', $participationId, PDO::PARAM_INT);
        $update_participation_status_stmt->execute();

        // 4. Gérer la mise à jour des crédits
        // Récupérer le prix du trajet par personne depuis la participation (déjà fait dans $participation_info)
        $ride_price = $participation_info['prix_personne'];

        if ($ride_price > 0) {
            // Débiter le participant
            $debit_participant_sql = "UPDATE Utilisateur SET credits = credits - :price WHERE utilisateur_id = :participantId";
            $debit_participant_stmt = $pdo->prepare($debit_participant_sql);
            $debit_participant_stmt->bindParam(':price', $ride_price, PDO::PARAM_INT); // Assurez-vous que le type de crédits est INT dans la DB
            $debit_participant_stmt->bindParam(':participantId', $loggedInUserId, PDO::PARAM_INT);
            $debit_participant_stmt->execute();

            // Créditer le chauffeur
            $credit_driver_sql = "UPDATE Utilisateur SET credits = credits + :price WHERE utilisateur_id = :driverId";
            $credit_driver_stmt = $pdo->prepare($credit_driver_sql);
            $credit_driver_stmt->bindParam(':price', $ride_price, PDO::PARAM_INT);
            $credit_driver_stmt->bindParam(':driverId', $driverUserId, PDO::PARAM_INT);
            $credit_driver_stmt->execute();

             // Vérifier si la mise à jour des crédits a affecté des lignes (optionnel mais bonne pratique)
             if ($debit_participant_stmt->rowCount() === 0 || $credit_driver_stmt->rowCount() === 0) {
                 // Ceci pourrait indiquer un problème (utilisateur ou chauffeur introuvable ?)
                 error_log("Avertissement: Mise à jour des crédits n'a pas affecté les lignes attendues pour participation " . $participationId);
             }
        } else {
             // Le prix est 0, pas de crédits à transférer
             error_log("Info: Traitement de validation pour participation " . $participationId . " avec prix 0. Pas de transfert de crédits.");
        }


        // --- Fin de la transaction : Valider ---
        $pdo->commit();

        // --- Renvoyer une réponse de succès ---
        echo json_encode(['success' => 'Votre validation et avis ont été enregistrés.']); // Le message peut mentionner les crédits si vous voulez

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
    echo json_encode(['error' => 'Erreur de base de données lors de l\'enregistrement de votre action.']);
    error_log("Erreur PDO dans submit_participant_action.php: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs (validation, etc.)
     if ($pdo && $pdo->inTransaction()) { // Vérifier si $pdo est défini avant d'appeler inTransaction
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request pour erreurs de validation/données
    echo json_encode(['error' => $e->getMessage()]); // Renvoyer le message d'erreur spécifique
    error_log("Erreur générale dans submit_participant_action.php: " . $e->getMessage());
}
?>
