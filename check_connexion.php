    <?php
    // backend/is_user_connected.php ou backend/get_connection_status.php

    // Démarrer ou reprendre la session PHP
    session_start();

    header('Content-Type: application/json'); // Indique que la réponse est au format JSON
    header("Access-Control-Allow-Origin: *"); // Permettre les requêtes depuis n'importe quelle origine (pour le développement)
    header("Access-Control-Allow-Methods: GET, OPTIONS"); // Autoriser les requêtes GET
    header("Access-Control-Allow-Headers: Content-Type");

    // Gérer les requêtes OPTIONS (pré-vol CORS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Vérifier si l'ID utilisateur est défini en session
    $is_connected = isset($_SESSION['user_id']);

    // Renvoyer l'état de connexion au format JSON
    echo json_encode(['is_connected' => $is_connected]);

    ?>
