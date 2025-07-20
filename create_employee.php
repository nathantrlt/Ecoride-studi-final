<?php
// backend/create_employee.php

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
    error_log("Erreur PDO lors de la connexion dans create_employee.php: " . $e->getMessage());
    exit();
}
// --- FIN CODE DE CONNEXION ---

// --- Vérification spécifique du rôle Administrateur ---
$isAdmin = false;
$check_role_sql = "SELECT COUNT(*) FROM Utilisateur_Role ur JOIN Role r ON ur.role_id = r.role_id WHERE ur.utilisateur_id = :userId AND r.libelle = 'Administrateur'"; // 'Administrateur' est le libellé du rôle
$check_role_stmt = $pdo->prepare($check_role_sql);
$check_role_stmt->bindParam(':userId', $loggedInUserId, PDO::PARAM_INT);
$check_role_stmt->execute();
if ($check_role_stmt->fetchColumn() === 0) { // Si le COUNT est 0, l'utilisateur N'EST PAS Administrateur
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Accès refusé. Seuls les administrateurs peuvent créer des comptes employés.']);
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

        // --- Validation des données reçues pour le nouvel employé ---
        if (!isset($data['pseudo']) || !isset($data['email']) || !isset($data['password']) ||
            !isset($data['nom']) || !isset($data['prenom']) || !isset($data['telephone']) ||
            !isset($data['adresse']) || !isset($data['date_naissance'])) { // Inclure tous les champs
             throw new Exception('Données manquantes pour la création de l\'employé.');
        }

        $pseudo = trim($data['pseudo']);
        $email = trim($data['email']);
        $password = $data['password']; // Le mot de passe brut reçu
        // --- RÉCUPÉRATION DES VALEURS DES NOUVEAUX CHAMPS (AJOUTÉ) ---
        $nom = trim($data['nom']);
        $prenom = trim($data['prenom']);
        $telephone = trim($data['telephone']);
        $adresse = trim($data['adresse']);
        $date_naissance = trim($data['date_naissance']); // La date est une chaîne YYYY-MM-DD

        if (empty($pseudo) || empty($email) || empty($password)) {
             throw new Exception('Le pseudo, l\'email et le mot de passe ne peuvent pas être vides.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
             throw new Exception('Format d\'email invalide.');
        }

         // Validation de la longueur minimale du mot de passe (exemple)
        if (strlen($password) < 8) {
             throw new Exception('Le mot de passe doit contenir au moins 8 caractères.');
        }


        // --- Vérification de l'unicité de l'email et du pseudo ---
        $check_unique_sql = "SELECT COUNT(*) FROM Utilisateur WHERE email = :email OR pseudo = :pseudo";
        $check_unique_stmt = $pdo->prepare($check_unique_sql);
        $check_unique_stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $check_unique_stmt->bindParam(':pseudo', $pseudo, PDO::PARAM_STR);
        $check_unique_stmt->execute();
        if ($check_unique_stmt->fetchColumn() > 0) {
             throw new Exception('Cet email ou pseudo est déjà utilisé.');
        }

        // --- Hachage du mot de passe ---
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);


        // --- Début de la transaction ---
        $pdo->beginTransaction();

        // 1. Insérer le nouvel utilisateur dans la table Utilisateur
        // Inclure les colonnes obligatoires comme date_inscription, statut (par ex: 'Actif')
        $insert_user_sql = "INSERT INTO Utilisateur (pseudo, email, password, nom, prenom, telephone, adresse, date_naissance,statut_compte) VALUES (:pseudo, :email, :password_hashed, :nom, :prenom, :telephone, :adresse, :date_naissance,'actif')"; // Utilisez mot_de_passe, et liez la variable hachée
        $insert_user_stmt = $pdo->prepare($insert_user_sql);
        $insert_user_stmt->bindParam(':pseudo', $pseudo, PDO::PARAM_STR);
        $insert_user_stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $insert_user_stmt->bindParam(':password_hashed', $hashed_password, PDO::PARAM_STR); // LIEZ LA VARIABLE HACHÉE
        $insert_user_stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $insert_user_stmt->bindParam(':prenom', $prenom, PDO::PARAM_STR);
        $insert_user_stmt->bindParam(':telephone', $telephone, PDO::PARAM_STR); // Adapter le type si besoin
        $insert_user_stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $insert_user_stmt->bindParam(':date_naissance', $date_naissance, PDO::PARAM_STR);
        $insert_user_stmt->bindParam(':statut_compte', $statutCompte, PDO::PARAM_STR);
 // La date est envoyée comme une chaîne
         // date_inscription sera automatique si définie comme DEFAULT CURRENT_TIMESTAMP dans la DB (et que vous l'avez laissée)

        $insert_user_stmt->execute();

        // Récupérer l'ID du nouvel utilisateur inséré
        $newUserId = $pdo->lastInsertId();

        // 2. Associer le rôle 'Employe' à ce nouvel utilisateur dans la table Utilisateur_Role
        // Il faut trouver l'ID du rôle 'Employe'
        $get_employee_role_id_sql = "SELECT role_id FROM Role WHERE libelle = 'Employe'"; // 'Employe' est le libellé du rôle
        $get_employee_role_id_stmt = $pdo->prepare($get_employee_role_id_sql);
        $get_employee_role_id_stmt->execute();
        $employeeRoleId = $get_employee_role_id_stmt->fetchColumn();

        if (!$employeeRoleId) {
             $pdo->rollBack();
             // Ceci est une erreur interne grave : le rôle 'Employe' n'existe pas dans la table Role
             error_log("Erreur grave: Rôle 'Employe' introuvable dans la table Role.");
             throw new Exception('Erreur interne : Le rôle Employé n\'est pas configuré.');
        }

        // Insérer l'association dans Utilisateur_Role
        $insert_user_role_sql = "INSERT INTO Utilisateur_Role (utilisateur_id, role_id) VALUES (:userId, :roleId)";
        $insert_user_role_stmt = $pdo->prepare($insert_user_role_sql);
        $insert_user_role_stmt->bindParam(':userId', $newUserId, PDO::PARAM_INT);
        $insert_user_role_stmt->bindParam(':roleId', $employeeRoleId, PDO::PARAM_INT);
        $insert_user_role_stmt->execute();


        // --- Fin de la transaction : Valider ---
        $pdo->commit();

        // --- Renvoyer une réponse de succès ---
        echo json_encode(['success' => 'Compte Employé créé avec succès.']);


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
    echo json_encode(['error' => 'Erreur de base de données lors de la création de l\'employé.']);
    error_log("Erreur PDO dans create_employee.php: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs (validation, unicité, etc.)
     if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request pour erreurs de validation/données
    echo json_encode(['error' => $e->getMessage()]); // Renvoyer le message d'erreur spécifique
    error_log("Erreur générale dans create_employee.php: " . $e->getMessage());
}
?>
