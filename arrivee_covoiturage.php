<?php
// backend/arrivee_covoiturage.php

require '../vendor/autoload.php'; // Inclure l'autoloader de Composer (VERIFIEZ/ADAPTEZ CE CHEMIN)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Vous devez être connecté pour terminer un covoiturage.']);
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
        // Remplacez par vos paramètres de connexion RDS
        $db_host = 'ecoride-database.cxiwggi0wewj.eu-west-3.rds.amazonaws.com';
        $db_name = 'ecoride_db';
        $db_user = 'Nathan';
        $db_pass = 'Af18PsKCc-';

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // --- Début de la transaction ---
        $pdo->beginTransaction();

        // 1. Vérifier que l'utilisateur connecté est bien le chauffeur de ce covoiturage
        // Récupérer aussi les détails du covoiturage pour l'e-mail
        $check_driver_sql = "SELECT utilisateur_id, statut, lieu_depart, lieu_arrivee, date_depart, heure_depart FROM Covoiturage WHERE covoiturage_id = :ride_id FOR UPDATE"; // Verrouiller la ligne
        $check_driver_stmt = $pdo->prepare($check_driver_sql);
        $check_driver_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $check_driver_stmt->execute();
        $ride_info = $check_driver_stmt->fetch();

        if (!$ride_info) {
            $pdo->rollBack();
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Covoiturage introuvable.']);
            exit();
        }

        if ($ride_info['utilisateur_id'] !== $user_id) {
            $pdo->rollBack();
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Vous n\'êtes pas le chauffeur de ce covoiturage.']);
            exit();
        }

        // 2. Vérifier le statut actuel pour permettre l'arrivée
        // Adaptez 'En cours' et 'Terminé' aux statuts que vous utilisez
        if ($ride_info['statut'] === 'Terminé') {
             $pdo->rollBack();
             http_response_code(400); // Bad Request
             echo json_encode(['error' => 'Ce covoiturage est déjà terminé.']);
             exit();
        }
        if ($ride_info['statut'] !== 'En cours') {
             $pdo->rollBack();
             http_response_code(400); // Bad Request
             echo json_encode(['error' => 'Le statut de ce covoiturage ne permet pas de le terminer. Statut actuel: ' . $ride_info['statut']]);
             exit();
        }

        // 3. Mettre à jour le statut du Covoiturage à 'Terminé'
        $update_status_sql = "UPDATE Covoiturage SET statut = 'Terminé' WHERE covoiturage_id = :ride_id";
        $update_status_stmt = $pdo->prepare($update_status_sql);
        $update_status_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $update_status_stmt->execute();

        // --- NOUVEAU : Mettre à jour le statut des Participations des PASSAGERS ---
        // Mettre le statut à 'action_en_attente' pour les participants (PAS le chauffeur)
        $update_participation_status_sql = "UPDATE Participation SET statut = 'action_en_attente' WHERE covoiturage_id = :ride_id AND utilisateur_id != :driver_user_id AND statut = 'confirmée'"; // Mettre à jour seulement si le statut est 'confirmée' ou votre statut initial
        $update_participation_status_stmt = $pdo->prepare($update_participation_status_sql);
        $update_participation_status_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $update_participation_status_stmt->bindParam(':driver_user_id', $user_id, PDO::PARAM_INT);
         // Optionnel : liez le statut initial si vous voulez être plus précis sur quel statut vous mettez à jour
        // $initial_participation_status = 'confirmée'; // Adaptez si votre statut initial est différent
        // $update_participation_status_stmt->bindParam(':initial_status', $initial_participation_status, PDO::PARAM_STR);

        $update_participation_status_stmt->execute();

        // --- Fin de la transaction : Valider ---
        $pdo->commit();

        // --- Envoyer l'e-mail de notification aux participants (avec PHPMailer) ---
        // Ceci DOIT se faire APRÈS le $pdo->commit()
        // La logique d'envoi d'email est déjà présente et semble fonctionner.
        // Pas besoin de la modifier, car l'email demande déjà aux participants d'aller sur leur espace.

        // 4. Récupérer les participants (pour l'envoi d'email - code inchangé)
        $get_participants_sql = "
            SELECT u.utilisateur_id, u.email, u.pseudo
            FROM Participation p
            JOIN Utilisateur u ON p.utilisateur_id = u.utilisateur_id
            WHERE p.covoiturage_id = :ride_id
            AND p.utilisateur_id != :driver_user_id -- Exclure le chauffeur lui-même de la liste des destinataires
        ";
        $get_participants_stmt = $pdo->prepare($get_participants_sql);
        $get_participants_stmt->bindParam(':ride_id', $ride_id, PDO::PARAM_INT);
        $get_participants_stmt->bindParam(':driver_user_id', $user_id, PDO::PARAM_INT);
        $get_participants_stmt->execute();
        $participants = $get_participants_stmt->fetchAll();

        $emails_sent = 0;
        $emails_failed = 0;

        // Détails du covoiturage pour l'e-mail
        $email_ride_details = [
            'lieu_depart' => $ride_info['lieu_depart'],
            'lieu_arrivee' => $ride_info['lieu_arrivee'],
            'date_depart' => $ride_info['date_depart'],
            'heure_depart' => $ride_info['heure_depart']
        ];

        foreach ($participants as $participant_data) {
            $mail = new PHPMailer(true);

            try {
                // Paramètres du serveur (adaptez avec les vôtres) - **À CONFIGURER IMPÉRATIVEMENT**
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Remplacez
                $mail->SMTPAuth = true;
                $mail->Username = 'Ecoride95@gmail.com'; // Remplacez
                $mail->Password = 'dgph ctsp iqxd tdxe'; // Remplacez (Mot de passe d'application)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // ou PHPMailer::ENCRYPTION_STARTTLS
                $mail->Port = 465; // Port SMTP

                 // Optional: Afficher les erreurs SMTP détaillées (utile pour déboguer)
                 // $mail->SMTPDebug = 2; // 0 pour désactiver

                // Destinataires
                $recipient_email = $participant_data['email'];
                $recipient_pseudo = $participant_data['pseudo'];

                if (!empty($recipient_email)) {
                     $mail->setFrom('noreply@ecoride.com', 'EcoRide'); // Votre adresse d'envoi et nom
                     $mail->addAddress($recipient_email, $recipient_pseudo); // Ajouter le destinataire
                     $mail->addReplyTo('noreply@ecoride.com', 'EcoRide');

                    // Contenu de l'e-mail
                    $mail->isHTML(true);
                    $mail->Subject = "Covoiturage EcoRide terminé - Veuillez valider";
                    $mail->Body    = "
                        <p>Bonjour {$recipient_pseudo},</p>
                        <p>Le covoiturage suivant est arrivé à destination :</p>
                        <ul>
                            <li>Départ : {$email_ride_details['lieu_depart']}</li>
                            <li>Arrivée : {$email_ride_details['lieu_arrivee']}</li>
                            <li>Date : {$email_ride_details['date_depart']}</li>
                            <li>Heure : {$email_ride_details['heure_depart']}</li>
                        </ul>
                        <p>Veuillez vous rendre sur votre espace utilisateur EcoRide pour valider que le trajet s'est bien passé et éventuellement laisser un avis et une note.</p>
                        <p>Votre validation déclenchera le versement des crédits au chauffeur.</p>
                        <p>Merci pour votre participation !</p>
                        <p>Cordialement,<br>L'équipe EcoRide</p>
                    ";
                    $mail->AltBody = "Bonjour {$recipient_pseudo},\n\nLe covoiturage suivant est arrivé à destination :\n\nDépart : {$email_ride_details['lieu_depart']}\nArrivée : {$email_ride_details['lieu_arrivee']}\nDate : {$email_ride_details['date_depart']}\nHeure : {$email_ride_details['heure_depart']}\n\nVeuillez vous rendre sur votre espace utilisateur EcoRide pour valider que le trajet s'est bien passé et éventuellement laisser un avis et une note.\n\nVotre validation déclenchera le versement des crédits au chauffeur.\n\nMerci pour votre participation !\n\nCordialement,\nL'équipe EcoRide";


                    $mail->send();
                    $emails_sent++;
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
        $response_message = 'Covoiturage terminé avec succès !';
         if (count($participants) > 0) { // Ajouter le statut d'envoi seulement s'il y avait des participants à notifier
              $response_message .= ' Résultat de l\'envoi d\'e-mails de notification : ' . $emails_sent . ' envoyés, ' . $emails_failed . ' échecs.';
         }


        echo json_encode(['success' => $response_message, 'emails_sent_success' => $emails_sent, 'emails_sent_failed' => $emails_failed]);


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
    echo json_encode(['error' => 'Erreur de base de données lors de la fin du covoiturage.']);
    error_log("Erreur PDO dans arrivee_covoiturage.php: " . $e->getMessage());

} catch (Exception $e) {
    // Annuler la transaction en cas d'autres erreurs
     if ($pdo && $pdo->inTransaction()) { // Vérifier si $pdo est défini avant d'appeler inTransaction
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request pour les erreurs de validation/données
    echo json_encode(['error' => $e->getMessage()]); // Retourner le message d'erreur spécifique
    error_log("Erreur générale dans arrivee_covoiturage.php: " . $e->getMessage());
}
?>
