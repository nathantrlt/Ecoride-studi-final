<?php
// backend/update_account_status.php

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

// --- VÉRIFICATION D'AUTORISATION (utilisateur connecté ET rôle Administrateur) ---
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
    error_log("Erreur PDO lors de la connexion dans update_account_status.php: " . $e->getMessage());
    exit();
}
// --- FIN CODE DE CONNEXION ---

// --- Vérification spécifique du rôle Administrateur ---
$isAdmin = false;
$check_role_sql = "SELECT COUNT(*) FROM Utilisateur_Role ur JOIN Role r ON ur.role_id = r.role_id WHERE ur.utilisateur_id = :userId AND r.libelle = 'Administrateur'"; // 'Administrateur' est le libellé du rôle
$check_role_stmt = $pdo->prepare($check_role_sql);
$check_role_stmt->bindParam(':userId', $loggedInUserId, PDO::PARAM_INT);
$check_role_stmt->execute();
if ($check_role_stmt->fetchColumn() === 0) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Accès refusé. Seuls les administrateurs peuvent modifier le statut des comptes.']);
    exit();
}
// Si le script atteint ce point, l'utilisateur est Administrateur.
// --- FIN Vérification spécifique du rôle Administrateur ---


// Utiliser un bloc try-catch pour le reste du script
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON.');
        }

        // --- Validation des données reçues (ID du compte et nouveau statut) ---
        if (!isset($data['user_id']) || !isset($data['statut_compte'])) {
             throw new Exception('ID utilisateur ou statut manquant.');
        }

        $userIdToUpdate = (int)$data['user_id'];
        $newStatus = trim($data['statut_compte']); // Attendu: 'Actif' ou 'Suspendu'

        // Validation du nouveau statut reçu
        if (!in_array($newStatus, ['Actif', 'actif', 'Suspendu', 'suspendu'])) { // Accepter 'Actif'/'Suspendu' avec ou sans majuscule si votre DB est flexible
             throw new Exception('Statut de compte invalide.');
        }

        // Standardiser le statut pour l'insertion en DB si votre DB est sensible à la casse
        $newStatus = ($newStatus === 'Suspendu' || $newStatus === 'suspendu') ? 'Suspendu' : 'Actif'; // Enregistrer 'Actif' ou 'Suspendu' avec la casse que vous utilisez en DB

        // --- VÉRIFICATION CRUCIALE : Un administrateur ne peut pas se suspendre lui-même ---
        if ($userIdToUpdate === $loggedInUserId) {
            // Optionnel : Vérifier si l'administrateur essaie de se passer en statut 'Suspendu'
            // S'il essaie de se mettre en 'Actif', on pourrait autoriser (même si ça n'a pas de sens de se réactiver soi-même si on est déjà actif)
             if ($newStatus === 'Suspendu') {
                 throw new Exception('Un administrateur ne peut pas suspendre son propre compte.');
             }
             // Si l'administrateur essaie de se réactiver, on pourrait laisser passer, ou juste ignorer l'action.
             // Pour la sécurité, on peut aussi interdire TOUTE modification de son propre statut.
             // Choisissons d'interdire de se suspendre, mais autoriser (ou ignorer) de se réactiver si nécessaire.
        }

        // --- Début de la transaction ---
        $pdo->beginTransaction();

        // 1. Vérifier que l'utilisateur existe avant de le mettre à jour
        $check_user_sql = "SELECT utilisateur_id FROM Utilisateur WHERE utilisateur_id = :userId LIMIT 1 FOR UPDATE"; // Verrouiller la ligne
        $check_user_stmt = $pdo->prepare($check_user_sql);
        $check_user_stmt->bindParam(':userId', $userIdToUpdate, PDO::PARAM_INT);
        $check_user_stmt->execute();

        if (!$check_user_stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(404); // Not Found
            throw new Exception('Utilisateur introuvable.');
        }
        error_log("update_account_status.php: Préparation de la mise à jour. newStatus: " . $newStatus . ", userId: " . $userIdToUpdate);
         error_log("update_account_status.php: Exécution de la requête UPDATE.");


        // 2. Mettre à jour le statut du compte de l'utilisateur
        $update_status_sql = "UPDATE Utilisateur SET statut_compte = :newStatus WHERE utilisateur_id = :userId";
        $update_status_stmt = $pdo->prepare($update_status_sql);
        $update_status_stmt->bindParam(':newStatus', $newStatus, PDO::PARAM_STR);
        $update_status_stmt->bindParam(':userId', $userIdToUpdate, PDO::PARAM_INT); // <<<--- CORRIGÉ

        $update_status_stmt->execute();


        // --- Fin de la transaction : Valider ---
        $pdo->commit();

        // --- Renvoyer une réponse de succès ---
        $actionSuccessMessage = ($newStatus === 'Suspendu') ? 'Compte suspendu avec succès.' : 'Compte réactivé avec succès.';
        echo json_encode(['success' => $actionSuccessMessage]);


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
    echo json_encode(['error' => 'Erreur de base de données lors de la mise à jour du statut du compte.']);
    error_log("Erreur PDO dans update_account_status.php: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs (validation, etc.)
     if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request pour erreurs de logique/validation
    echo json_encode(['error' => $e->getMessage()]); // Renvoyer le message d'erreur spécifique
    error_log("Erreur générale dans update_account_status.php: " . $e->getMessage());
}
?>

