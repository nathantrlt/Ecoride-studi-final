<?php
// backend/get_chart_data.php

session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // À ajuster pour la production
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- VÉRIFICATION D'AUTORISATION (utilisateur connecté ET rôle Administrateur) ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Vous devez être connecté pour accéder à cette ressource.']);
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
    error_log("Erreur PDO lors de la connexion dans get_chart_data.php: " . $e->getMessage());
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
    echo json_encode(['error' => 'Accès refusé. Seuls les administrateurs peuvent accéder aux données graphiques.']);
    exit();
}
// --- FIN Vérification spécifique du rôle Administrateur ---


// Utiliser un bloc try-catch pour le reste du script
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // --- Récupérer les données pour le graphique Covoiturages par jour ---
        // Cette requête compte le nombre de covoiturages créés par jour
        $rides_per_day_sql = "
            SELECT
                DATE(created_at) as ride_date,
                COUNT(*) as ride_count
            FROM Covoiturage
            GROUP BY DATE(created_at)
            ORDER BY ride_date ASC
        ";
        $rides_per_day_stmt = $pdo->prepare($rides_per_day_sql);
        $rides_per_day_stmt->execute();
        $rides_data_raw = $rides_per_day_stmt->fetchAll();

        $rides_labels = [];
        $rides_values = [];
        foreach ($rides_data_raw as $row) {
            $rides_labels[] = $row['ride_date'];
            $rides_values[] = $row['ride_count'];
        }
        $rides_per_day_chart_data = ['labels' => $rides_labels, 'values' => $rides_values];


        // --- Récupérer les données pour le graphique Gain de crédits par jour ---
        // Ceci suppose que les crédits sont gagnés par le chauffeur (ajoutés à son compte)
        // et correspond au prix payé par les passagers lors de la validation.
        // Si la logique des crédits est différente, cette requête doit être adaptée.
        // Cette requête somme les prix des participations qui ont été validées ('action_effectuée') par jour.
        $credits_per_day_sql = "
            SELECT
                DATE(c.updated_at) as credit_date, -- Supposons une colonne date_validation dans Covoiturage ou utiliser la date du trajet
                SUM(c.prix_personne) as daily_credits_gain
            FROM Participation p
            JOIN Covoiturage c ON p.covoiturage_id = c.covoiturage_id
            WHERE p.statut = 'action_effectuée' -- Cr��dits gagnés lorsque l'action est effectuée
             AND c.prix_personne > 0 -- Ne prendre en compte que les participations payantes
            GROUP BY DATE(c.updated_at) -- Ou DATE(c.date_depart) si les crédits sont liés à la date du trajet
            ORDER BY credit_date ASC
        ";
        // Note: Cette requête dépend de la colonne `date_validation` dans votre table `Participation`
        // et de la logique exacte de transfert des crédits. Si les crédits sont transférés à un autre moment,
        // ou si le prix est stocké ailleurs, ou si vous n'avez pas de colonne date_validation
        // dans Participation, cette requête devra être adaptée.
        // Si vous n'avez pas date_validation dans Participation, vous pourriez utiliser la date du trajet (c.date_depart)
        // mais cela ne refléterait pas exactement quand la plateforme "gagne" les crédits (qui est lié à la validation).

        $credits_per_day_stmt = $pdo->prepare($credits_per_day_sql);
        $credits_per_day_stmt->execute();
        $credits_data_raw = $credits_per_day_stmt->fetchAll();

        $credits_labels = [];
        $credits_values = [];
        foreach ($credits_data_raw as $row) {
            $credits_labels[] = $row['credit_date'];
            $credits_values[] = $row['daily_credits_gain'];
        }
        $credits_per_day_chart_data = ['labels' => $credits_labels, 'values' => $credits_values];


        // --- Renvoyer les données au format JSON ---
        echo json_encode([
            'rides_per_day' => $rides_per_day_chart_data,
            'credits_per_day' => $credits_per_day_chart_data // Utiliser la bonne variable ici
        ]);


    } else {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Méthode de requête non autorisée.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données lors du chargement des données graphiques : ' . $e->getMessage()]);
    error_log("Erreur PDO dans get_chart_data.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur lors du chargement des données graphiques : ' . $e->getMessage()]);
    error_log("Erreur générale dans get_chart_data.php: " . $e->getMessage());
}
?>
