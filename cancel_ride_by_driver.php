<?php
// backend/cancel_ride_by_driver.php

require '../vendor/autoload.php'; // Inclure l'autoloader de Composer (VERIFIEZ/ADAPTEZ CE CHEMIN)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Autoriser POST
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// La fonction d'envoi d'e-mail commentée (remplacée par PHPMailer)
// function sendCancellationEmail($recipient_email, $ride_details) {
//     // ... code commenté ...
// }


try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Vous devez être connecté pour annuler un covoiturage.']);
            exit();
        }
        $user_id = $_SESSION['user_id'];

        // Récupérer les données JSON (doit contenir l'ID du covoiturage)
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Erreur de décodage JSON.');
        }

        if (!isset($data['ride_id'])) {
             throw new Exception('ID du covoiturage manquant dans la requête.');
        }

        $ride_id = (int)$data['ride_id']; // S'assurer que c'est un entier

        // --- Database connection ---
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com'; // Replace with your RDS Endpoint
        $db_name = 'ecoride_db'; // Replace with your database name
        $db_user = 'Nathan';      // Replace with your RDS username
        $db_pass = 'Af18PsKCc-';          // Replace with your RDS password

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // --- Vérifier si le covoiturage est déjà passé ---
        $get_ride_datetime_sql = "SELECT date_depart, heure_depart FROM Covoiturage WHERE covoiturage_id = :ride_id";
        $get_ride_datetime_stmt = $pdo->prepare($get_ride_datetime_sql);
        $get_ride_datetime_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $get_ride_datetime_stmt->execute();
        $ride_datetime_info = $get_ride_datetime_stmt->fetch();

         if (!$ride_datetime_info) {
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Covoiturage introuvable.']);
            exit();
        }


        $departure_datetime_str = $ride_datetime_info['date_depart'] . ' ' . $ride_datetime_info['heure_depart'];
        $departure_datetime = new DateTime($departure_datetime_str);
        $current_datetime = new DateTime();

        // Comparer la date et l'heure de départ avec la date et l'heure actuelles
        if ($departure_datetime <= $current_datetime) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Vous ne pouvez pas annuler ce covoiturage car la date de départ est passée.']);
            exit(); // Arrêter l'exécution
        }
        // --- Fin de la vérification si le covoiturage est passé ---


        // --- Début de la transaction ---
        $pdo->beginTransaction();

        // 1. Vérifier que l'utilisateur connecté est bien le chauffeur de ce covoiturage
        $check_driver_sql = "SELECT utilisateur_id, prix_personne, lieu_depart, lieu_arrivee, date_depart, heure_depart FROM Covoiturage WHERE covoiturage_id = :ride_id"; // Récupérer les détails pour l'e-mail
        $check_driver_stmt = $pdo->prepare($check_driver_sql);
        $check_driver_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $check_driver_stmt->execute();
        $ride_details = $check_driver_stmt->fetch();

        if (!$ride_details) { // Double vérification, ne devrait pas arriver après la vérification de date
            $pdo->rollBack();
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Covoiturage introuvable.']);
            exit();
        }

        if ($ride_details['utilisateur_id'] !== $user_id) {
            $pdo->rollBack();
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Vous n\'êtes pas le chauffeur de ce covoiturage.']);
            exit();
        }

        $price_per_participant = $ride_details['prix_personne']; // Prix à rembourser par participant


        // 2. Mettre à jour le statut du covoiturage à 'Annulé par le chauffeur'
        $update_status_sql = "UPDATE Covoiturage SET statut = 'Annulé par le chauffeur' WHERE covoiturage_id = :ride_id";
        $update_status_stmt = $pdo->prepare($update_status_sql);
        $update_status_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $update_status_stmt->execute();


        // 3. Récupérer tous les participants pour ce covoiturage
        // Adaptez le nom de la table 'Participation' et les colonnes si nécessaire (nous aurons besoin de l'email pour l'envoi)
        $get_participants_sql = "
            SELECT u.utilisateur_id, u.email, u.pseudo
            FROM Participation p
            JOIN Utilisateur u ON p.utilisateur_id = u.utilisateur_id
            WHERE p.covoiturage_id = :ride_id
        ";
        $get_participants_stmt = $pdo->prepare($get_participants_sql);
        $get_participants_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $get_participants_stmt->execute();
        $participants = $get_participants_stmt->fetchAll();


        // 4. Rembourser les crédits et collecter les emails des participants
        $participant_emails_data = []; // Stocke les emails et pseudos pour PHPMailer
        foreach ($participants as $participant) {
            // Rembourser les crédits au participant
            $update_user_credits_sql = "UPDATE Utilisateur SET credits = credits + :price_to_refund WHERE utilisateur_id = :user_id";
            $update_user_credits_stmt = $pdo->prepare($update_user_credits_sql);
            $update_user_credits_stmt->bindParam(':price_to_refund', $price_per_participant, PDO::PARAM_STR); // Assurez-vous du bon type
            $update_user_credits_stmt->bindParam(':user_id', $participant['utilisateur_id'], PDO::PARAM_INT);
            $update_user_credits_stmt->execute();

            // Collecter l'email et le pseudo pour l'envoi de notification
            if (!empty($participant['email'])) {
                 $participant_emails_data[] = ['email' => $participant['email'], 'pseudo' => $participant['pseudo']];
            }
        }

        // Optionnel : Supprimer les entrées de participation après annulation par le chauffeur
        // ou les marquer comme annulées si vous conservez un historique des participations.
        // Par souci de simplicité, nous pourrions les supprimer ici si la table Participation ne sert qu'aux réservations actives.
        // $delete_participations_sql = "DELETE FROM Participation WHERE covoiturage_id = :ride_id";
        // $delete_participations_stmt = $pdo->prepare($delete_participations_sql);
        // $delete_participations_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        // $delete_participations_stmt->execute();


        // --- Fin de la transaction : Valider ---
        $pdo->commit();

        // --- Envoyer l'e-mail de notification aux participants (avec PHPMailer) ---
        // Ceci DOIT se faire APRÈS le $pdo->commit()

        $emails_sent = 0;
        $emails_failed = 0;

        // Détails du covoiturage pour l'e-mail (assurez-vous que $ride_details contient ces infos)
        $email_ride_details = [
            'lieu_depart' => $ride_details['lieu_depart'],
            'lieu_arrivee' => $ride_details['lieu_arrivee'],
            'date_depart' => $ride_details['date_depart'],
            'heure_depart' => $ride_details['heure_depart']
        ];


        foreach ($participant_emails_data as $participant_data) {
            // Créer une nouvelle instance de PHPMailer pour chaque e-mail
            $mail = new PHPMailer(true); // Passer true active les exceptions pour une meilleure gestion des erreurs

            try {
                // Paramètres du serveur (adaptez avec les vôtres) - **À CONFIGURER IMPÉRATIVEMENT**
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Remplacez par l'hôte de votre serveur SMTP (ex: smtp.gmail.com)
                $mail->SMTPAuth = true;
                $mail->Username = 'Ecoride95@gmail.com'; // Remplacez par votre adresse e-mail d'envoi
                $mail->Password = 'dgph ctsp iqxd tdxe'; // Remplacez par le mot de passe de votre e-mail
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // ou PHPMailer::ENCRYPTION_STARTTLS
                $mail->Port = 465; // Port SMTP (souvent 465 pour SMTPS ou 587 pour STARTTLS)

                 // Optional: Afficher les erreurs SMTP détaillées (utile pour déboguer)
                 //$mail->SMTPDebug = 2; // 0 pour désactiver, 1 pour client, 2 pour client et serveur


                // Destinataires
                $recipient_email = $participant_data['email'];
                $recipient_pseudo = $participant_data['pseudo'];

                if (!empty($recipient_email)) {
                     $mail->setFrom('noreply@ecoride.com', 'EcoRide'); // Votre adresse d'envoi et nom
                     $mail->addAddress($recipient_email, $recipient_pseudo); // Ajouter le destinataire
                     $mail->addReplyTo('noreply@ecoride.com', 'EcoRide');

                    // Contenu de l'e-mail
                    $mail->isHTML(true); // Définir le format de l'e-mail en HTML
                    $mail->Subject = "Annulation de votre covoiturage EcoRide";
                    $mail->Body    = "
                        <p>Bonjour {$recipient_pseudo},</p>
                        <p>Nous vous informons que le covoiturage suivant a été annulé par le chauffeur :</p>
                        <ul>
                            <li>Départ : {$email_ride_details['lieu_depart']}</li>
                            <li>Arrivée : {$email_ride_details['lieu_arrivee']}</li>
                            <li>Date : {$email_ride_details['date_depart']}</li>
                            <li>Heure : {$email_ride_details['heure_depart']}</li>
                        </ul>
                        <p>Vos crédits ont été remboursés sur votre compte EcoRide.</p>
                        <p>Nous nous excusons pour la gêne occasionnée.</p>
                        <p>Cordialement,<br>L'équipe EcoRide</p>
                    ";
                    $mail->AltBody = "Bonjour {$recipient_pseudo},\n\nNous vous informons que le covoiturage suivant a été annulé par le chauffeur :\n\nDépart : {$email_ride_details['lieu_depart']}\nArrivée : {$email_ride_details['lieu_arrivee']}\nDate : {$email_ride_details['date_depart']}\nHeure : {$email_ride_details['heure_depart']}\n\nVos crédits ont été remboursés sur votre compte EcoRide.\n\nNous nous excusons pour la gêne occasionnée.\n\nCordialement,\nL'équipe EcoRide";


                    $mail->send();
                    $emails_sent++;
                    // error_log("E-mail envoyé à: " . $recipient_email . " pour ride " . $ride_id); // Optionnel, déjà géré par PHPMailer logs si SMTPDebug > 0

                } else {
                     $emails_failed++;
                     error_log("Échec de l'envoi d'e-mail : Adresse e-mail vide pour un participant du covoiturage " . $ride_id);
                }

            } catch (Exception $e) {
                $emails_failed++;
                error_log("Échec de l'envoi d'e-mail à: " . $recipient_email . ". Erreur PHPMailer: {$mail->ErrorInfo}");
            }
        }


        // --- Renvoyer une réponse de succès ---
         error_log("DEBUG: Préparation de la réponse finale. emails_sent: " . $emails_sent . ", emails_failed: " . $emails_failed); // Point de débogage
        $response_message = 'Covoiturage annulé avec succès ! Tous les participants ont été remboursés.';
        if (!empty($participant_emails_data)) { // Ajouter le statut d'envoi seulement s'il y avait des participants avec email
             $response_message .= ' Résultat de l\'envoi d\'e-mails : ' . $emails_sent . ' envoyés, ' . $emails_failed . ' échecs.';
        }

        echo json_encode(['success' => $response_message, 'emails_sent' => $emails_sent, 'emails_failed' => $emails_failed]);
        error_log("DEBUG: Réponse finale envoyée."); // Point de débogage juste après (si atteint)



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
    echo json_encode(['error' => 'Erreur de base de données lors de l\'annulation du covoiturage par le chauffeur.']);
    error_log("Erreur PDO dans cancel_ride_by_driver.php: " . $e->getMessage());

} catch (Exception $e) {
    // Annuler la transaction en cas d'autres erreurs
     if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request pour les erreurs de validation/données
    echo json_encode(['error' => $e->getMessage()]); // Retourner le message d'erreur spécifique
    error_log("Erreur générale dans cancel_ride_by_driver.php: " . $e->getMessage());
}

?>
