<?php
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

        // Vérifier si les données JSON nécessaires sont présentes et si le décodage a réussi
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON : ' . json_last_error_msg());
        }

        // --- Validation et récupération des données reçues pour l'inscription ---
        // S'assurer que TOUS les champs nécessaires sont présents
        if (!isset($data['pseudo']) || !isset($data['email']) || !isset($data['password']) ||
            !isset($data['nom']) || !isset($data['prenom']) || !isset($data['telephone']) ||
            !isset($data['adresse']) || !isset($data['date_naissance'])) { // Inclure tous les champs
             throw new Exception('Données d\'inscription manquantes.');
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
        // --- FIN RÉCUPÉRATION ---

        // --- Validation des données côté backend (AJOUTÉES/MODIFIÉES) ---
        if (empty($pseudo)) {
            throw new Exception('Le pseudo ne peut pas être vide.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format d\'email invalide.');
        }
        if (empty($password)) {
             throw new Exception('Le mot de passe ne peut pas être vide.');
        }
         // Ajouter des vérifications de sécurité pour le mot de passe (longueur minimale, caractères spéciaux, etc.)
        if (strlen($password) < 8) {
             throw new Exception('Le mot de passe doit contenir au moins 8 caractères.');
        }

         // Validation basique pour les nouveaux champs obligatoires (AJOUTÉ)
         if (empty($nom) || empty($prenom)) { // Adaptez si telephone, adresse, date_naissance sont aussi obligatoires
              throw new Exception('Veuillez remplir les champs Nom et Prénom.');
         }
         // Vous pouvez ajouter ici des validations spécifiques pour telephone, adresse, date_naissance si nécessaire


        // --- Hachage du mot de passe ---
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // --- Connexion à la base de données ---
        // ... (votre code de connexion existant) ...
         $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // Remplacez par votre Endpoint RDS
        $db_name = 'ecoride_db'; // Remplacez par le nom de votre base de données
        $db_user = 'Nathan';      // Remplacez par votre nom d'utilisateur RDS
        $db_pass = 'Af18PsKCc-';          // Remplacez par votre mot de passe RDS

        // Connexion à la base de données en utilisant PDO
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);


        // --- Vérifier si l'email ou le pseudo existe déjà ---
        $check_sql = "SELECT COUNT(*) FROM Utilisateur WHERE email = :email OR pseudo = :pseudo LIMIT 1";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $check_stmt->bindParam(':pseudo', $pseudo, PDO::PARAM_STR);
        $check_stmt->execute();
        $count = $check_stmt->fetchColumn();

        if ($count > 0) {
            throw new Exception('Cet email ou pseudo est déjà utilisé.');
        }

        // --- Insérer le nouvel utilisateur dans la base de données ---
        // Inclure TOUTES les colonnes nécessaires, y compris les nouvelles et celles avec des valeurs par défaut
        // Si vous avez une colonne 'statut_compte' avec DEFAULT 'actif', vous n'avez pas besoin de l'ajouter ici.
        // Si vous avez une colonne 'credits' avec DEFAULT 20, vous n'avez pas besoin de l'ajouter ici.
        // Si vous avez une colonne 'date_inscription' avec DEFAULT CURRENT_TIMESTAMP, vous n'avez pas besoin de l'ajouter ici.
        // Assurez-vous que le nom de la colonne du mot de passe est correct ('password' ou 'mot_de_passe')
        $insert_sql = "INSERT INTO Utilisateur (pseudo, email, password, nom, prenom, telephone, adresse, date_naissance, credits, statut_compte) VALUES (:pseudo, :email, :password_hashed, :nom, :prenom, :telephone, :adresse, :date_naissance, 20, 'Actif')"; // Inclure toutes les colonnes avec leurs valeurs
        // Adaptez la liste des colonnes et valeurs si certaines ont des valeurs par défaut en base de données.

        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->bindParam(':pseudo', $pseudo, PDO::PARAM_STR);
        $insert_stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $insert_stmt->bindParam(':password_hashed', $hashed_password, PDO::PARAM_STR); // Liez la variable hachée
        // --- BIND PARAM POUR LES NOUVEAUX CHAMPS (AJOUTÉ) ---
        $insert_stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $insert_stmt->bindParam(':prenom', $prenom, PDO::PARAM_STR);
        $insert_stmt->bindParam(':telephone', $telephone, PDO::PARAM_STR);
        $insert_stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $insert_stmt->bindParam(':date_naissance', $date_naissance, PDO::PARAM_STR);
        // --- FIN BIND PARAM ---
        // Pas besoin de bindParam pour credits ou statut_compte si les valeurs sont en dur dans la requête.

        $insert_stmt->execute();

        // Récupérer l'ID du nouvel utilisateur inséré (utile pour les relations futures, comme lier à un rôle si nécessaire)
        $new_user_id = $pdo->lastInsertId();

        // --- Associer le rôle 'Utilisateur' de base à ce nouvel utilisateur (AJOUTÉ) ---
        // Ceci suppose que tous les utilisateurs normaux ont un rôle 'Utilisateur'.
        // Il faut trouver l'ID du rôle 'Utilisateur'
        $get_user_role_id_sql = "SELECT role_id FROM Role WHERE libelle = 'Utilisateur'"; // 'Utilisateur' est le libellé du rôle de base
        $get_user_role_id_stmt = $pdo->prepare($get_user_role_id_sql);
        $get_user_role_id_stmt->execute();
        $userRoleId = $get_user_role_id_stmt->fetchColumn();

        if (!$userRoleId) {
             // Ceci est une erreur interne grave : le rôle 'Utilisateur' n'existe pas dans la table Role
             error_log("Erreur grave: Rôle 'Utilisateur' introuvable dans la table Role lors de l'inscription.");
             // Ne pas throw une Exception ici si l'inscription peut se faire sans rôle de base
             // ou gérez l'erreur différemment si le rôle de base est obligatoire.
        } else {
             // Insérer l'association dans Utilisateur_Role si le rôle existe
             $insert_user_role_sql = "INSERT INTO Utilisateur_Role (utilisateur_id, role_id) VALUES (:userId, :roleId)";
             $insert_user_role_stmt = $pdo->prepare($insert_user_role_sql);
             $insert_user_role_stmt->bindParam(':userId', $new_user_id, PDO::PARAM_INT);
             $insert_user_role_stmt->bindParam(':roleId', $userRoleId, PDO::PARAM_INT);
             $insert_user_role_stmt->execute();
        }
        // --- FIN AJOUT ASSOCIATION RÔLE ---


        // --- Renvoyer une réponse de succès au frontend ---
        echo json_encode(['success' => 'Compte créé avec succès ! Vous avez reçu 20 crédits.']);


    } else {
        // Si la méthode n'est pas POST
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erreur de base de données lors de l\'inscription.']);
    error_log("Erreur PDO lors de l'inscription: " . $e->getMessage());

} catch (Exception $e) {
    // Gérer les autres erreurs (validation, etc.)
    http_response_code(400); // Bad Request ou 500 selon le type d'erreur
    echo json_encode(['error' => $e->getMessage()]); // Renvoyer le message d'erreur spécifique
    error_log("Erreur générale lors de l'inscription: " . $e->getMessage());
}

?>

