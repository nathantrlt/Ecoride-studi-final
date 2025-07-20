// js/ride_details.js

document.addEventListener("DOMContentLoaded", () => {
  console.log("connexion.js chargé.");

  const detailsContentDiv = document.getElementById("details-content");
  const participateButton = document.getElementById("participate-button");
  const participationMessage = document.getElementById("participation-message");

  if (detailsContentDiv) {
    // Récupérer l'ID du covoiturage depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const rideId = urlParams.get("id");

    if (rideId) {
      console.log("ID du covoiturage trouvé dans l'URL:", rideId);
      // Appeler le backend pour obtenir les détails du covoiturage
      fetchRideDetails(rideId);
    } else {
      console.error("ID du covoiturage manquant dans l'URL.");
      detailsContentDiv.innerHTML =
        "<p class='text-danger'>Erreur: ID du covoiturage non spécifié.</p>";
    }
  } else {
    console.error("Élément 'details-content' non trouvé.");
  }

  // Fonction pour appeler le backend et récupérer les détails du covoiturage
  function fetchRideDetails(id) {
    // Assurez-vous que le chemin vers le script PHP backend est correct
    fetch(`ride_details.php?id=${id}`, {
      // Nous passerons l'ID comme paramètre d'URL (GET)
      method: "GET", // Une requête GET est appropriée pour récupérer des données
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => {
        console.log("Réponse reçue du backend (détails du trajet).");
        if (!response.ok) {
          throw new Error(`Erreur HTTP! Statut: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("Détails du covoiturage JSON reçus:", data);
        if (data.error) {
          console.error("Erreur du backend:", data.error);
          detailsContentDiv.innerHTML = `<p class='text-danger'>Erreur lors du chargement des détails : ${data.error}</p>`;
        } else {
          // Appeler une fonction pour afficher les détails sur la page
          displayRideDetails(data);
        }
      })
      .catch((error) => {
        console.error(
          "Erreur lors de l'envoi de la requête ou du traitement de la réponse:",
          error
        );
        detailsContentDiv.innerHTML =
          '<p class="text-danger">Une erreur est survenue lors de la communication avec le serveur.</p>';
      });
  }

  // Fonction pour afficher les détails du covoiturage sur la page
  function displayRideDetails(rideDetails) {
    console.log("Affichage des détails du covoiturage.", rideDetails);
    participateButton.disabled = true; // Désactiver par défaut
    participationMessage.innerHTML = ""; // Vider le message

    const formattedDepartureTime = rideDetails.heure_depart
      ? rideDetails.heure_depart.substring(0, 5)
      : "N/A";
    const formattedArrivalTime = rideDetails.heure_arrivee
      ? rideDetails.heure_arrivee.substring(0, 5)
      : "N/A";

    detailsContentDiv.innerHTML = `
          <h3>Informations du Trajet</h3>
          <p>ID du trajet: ${rideDetails.ride_id}</p>
          <p>Départ: ${rideDetails.lieu_depart} à ${formattedDepartureTime}</p>
          <p>Arrivée: ${rideDetails.lieu_arrivee} à ${formattedArrivalTime}</p>
          <p>Prix: ${rideDetails.prix_personne} €</p>
          <p>Places disponibles: <span id="available-seats-display">${
            rideDetails.availableSeats
          }</span></p>

      <h4>Informations du Chauffeur</h4>
      <p>Pseudo: ${rideDetails.driver.pseudo}</p>
      <img src="${rideDetails.driver.photo}" alt="${
      rideDetails.driver.pseudo
    }" width="100">
      <p>Note: ${
        rideDetails.driver.rating !== null &&
        rideDetails.driver.rating !== undefined
          ? parseFloat(rideDetails.driver.rating).toFixed(1)
          : "N/A"
      }</p>
      <p>Fumeur: ${rideDetails.driver.fumeur ?? "Non spécifié"}</p>
      <p>Animaux acceptés: ${rideDetails.driver.animaux ?? "Non spécifié"}</p>

       <h4>Informations de la Voiture</h4>
      <p>Modèle: ${rideDetails.vehicle.model}</p>
       <p>Énergie: ${rideDetails.vehicle.energy}</p>
      <!-- Vérifiez les noms des propriétés pour 'vehicle' aussi -->
  `;

    // --- Logique pour masquer le bouton Participer si le trajet est terminé ou annulé ---
    if (rideDetails.statut === "Terminé") {
      console.log("Le trajet est terminé. Masquage du bouton Participer.");
      participateButton.style.display = "none"; // Masquer le bouton
      participationMessage.innerHTML =
        "<p class='text-info'>Ce covoiturage est terminé.</p>"; // Afficher un message
      return; // Sortir de la fonction
    } else if (rideDetails.statut === "Annulé par le chauffeur") {
      // Nouvelle condition pour le statut Annulé
      console.log("Le trajet est annulé. Masquage du bouton Participer.");
      participateButton.style.display = "none"; // Masquer le bouton
      participationMessage.innerHTML =
        "<p class='text-danger'>Ce covoiturage a été annulé par le chauffeur.</p>"; // Afficher un message d'annulation
      return; // Sortir de la fonction
    }
    // --- Fin de la logique ---

    const availableSeats = rideDetails.availableSeats;
    if (availableSeats <= 0) {
      console.log(
        "Aucune place disponible ou données invalides pour les places."
      );
      participationMessage.innerHTML =
        "<p class='text-warning'>Aucune place disponible pour ce covoiturage.</p>";
      participateButton.disabled = true;
    } else {
      console.log("Places disponibles:", availableSeats);
      checkUserStatusAndCredits(rideDetails.ride_id, rideDetails.prix_personne);
    }
  }

  // --- Nouvelle fonction pour vérifier le statut de l'utilisateur et les crédits ---
  function checkUserStatusAndCredits(rideId, ridePrice) {
    // Nous aurons besoin d'un nouveau script PHP backend pour cela
    // Par exemple, '/ecoride/backend/check_user_status.php'
    fetch(
      `check_user_status.php?ride_id=${rideId}&ride_price=${ridePrice}`,
      {
        method: "GET", // Ou POST si vous préférez envoyer les IDs/prix dans le corps
        headers: {
          "Content-Type": "application/json",
        },
        // NOTE : Pour les sessions PHP, les cookies de session sont généralement envoyés automatiquement par le navigateur lors des requêtes fetch vers le même domaine
      }
    )
      .then((response) => {
        if (!response.ok) {
          throw new Error(`Erreur HTTP! Statut: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("Statut utilisateur et crédits reçus:", data);

        if (data.is_logged_in) {
          // Utilisateur connecté
          const userCredits = data.credits;
          const ridePrice = data.ride_price; // Récupérer le prix du backend pour plus de fiabilité

          if (userCredits >= ridePrice) {
            // Utilisateur connecté et a suffisamment de crédits
            participationMessage.innerHTML = `<p class='text-success'>Crédits disponibles : ${userCredits}. Coût du trajet : ${ridePrice}.</p>`;
            participateButton.disabled = false; // ACTIVER LE BOUTON PARTICIPER
          } else {
            // Utilisateur connecté mais pas assez de crédits
            participationMessage.innerHTML = `<p class='text-warning'>Vous avez ${userCredits} crédits. Le coût du trajet est ${ridePrice}. Crédits insuffisants.</p>`;
            participateButton.disabled = true; // Désactiver le bouton
          }
        } else {
          // Utilisateur n'est PAS connecté
          participationMessage.innerHTML = `<p class='text-info'>Vous devez être <a href="connexion.html">connecté</a> pour participer à ce covoiturage.</p>`;
          participateButton.disabled = true; // Désactiver le bouton
        }
      })
      .catch((error) => {
        console.error(
          "Erreur lors de la vérification du statut utilisateur:",
          error
        );
        participationMessage.innerHTML =
          '<p class="text-danger">Erreur lors de la vérification de votre statut.</p>';
        participateButton.disabled = true; // Désactiver le bouton en cas d'erreur
      });
  }
  // --- Ajouter l'écouteur de clic sur le bouton Participer ---
  if (participateButton) {
    // Vérifier que le bouton existe
    participateButton.addEventListener("click", () => {
      console.log("Bouton Participer cliqué.");

      // Afficher la double confirmation
      const isConfirmed = confirm(
        "Confirmez-vous vouloir participer à ce covoiturage et utiliser vos crédits ?"
      ); // Utilise la boîte de dialogue native

      if (isConfirmed) {
        console.log("Utilisateur a confirmé la participation.");
        // --- Appeler le script PHP backend pour enregistrer la participation ---
        // Nous allons créer un nouveau script backend pour cela (ex: backend/participate.php)
        // Cette logique viendra dans la prochaine étape
        initiateParticipation(); // Appeler une fonction pour gérer l'appel au backend
      } else {
        console.log("Participation annulée par l'utilisateur.");
        participationMessage.innerHTML =
          "<p class='text-info'>Participation annulée.</p>";
      }
    });
  } else {
    console.error("Bouton Participer non trouvé.");
  }

  // ... (reste de votre code, y compris fetchRideDetails, displayRideDetails, checkUserStatusAndCredits) ...

  // --- Nouvelle fonction pour initier le processus de participation (appel backend) ---
  function initiateParticipation() {
    console.log("Initiation du processus de participation...");

    // Nous aurons besoin de l'ID du covoiturage ici
    // L'ID du covoiturage a été récupéré au début du script par urlParams.get('id')
    // Il faudrait rendre rideId accessible ici ou le repasser en paramètre
    const urlParams = new URLSearchParams(window.location.search);
    const rideId = urlParams.get("id");

    if (!rideId) {
      console.error(
        "ID du covoiturage manquant pour initier la participation."
      );
      participationMessage.innerHTML =
        "<p class='text-danger'>Erreur: Impossible d'identifier le covoiturage pour la participation.</p>";
      return;
    }

    // Désactiver le bouton pour éviter les clics multiples pendant le traitement
    participateButton.disabled = true;
    participationMessage.innerHTML =
      "<p class='text-info'>Traitement de votre demande de participation...</p>";

    // --- Appeler le script PHP backend pour enregistrer la participation ---
    // Nous allons créer un nouveau script backend pour cela (ex: backend/participate.php)
    fetch("participate.php", {
      method: "POST", // Utiliser POST pour envoyer l'ID du trajet et l'ID utilisateur (implicite via session)
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ ride_id: rideId }), // Envoyer l'ID du trajet au backend
      // L'ID de l'utilisateur connecté sera récupéré en session par le backend
    })
      .then((response) => {
        console.log("Réponse reçue du backend (participation).");
        if (!response.ok) {
          // Si la réponse n'est pas OK (par exemple, 400 Bad Request, 401 Unauthorized, 500 Internal Server Error)
          // Le backend devrait renvoyer un JSON d'erreur dans ces cas
          return response
            .json()
            .then((errorData) => {
              // Afficher l'erreur du backend si elle est dans le JSON
              const errorMessage =
                errorData.error || `Erreur HTTP! Statut: ${response.status}`;
              throw new Error(`Erreur backend: ${errorMessage}`);
            })
            .catch((jsonError) => {
              // Si la réponse n'est pas OK et n'est pas un JSON, afficher un message générique
              throw new Error(
                `Erreur HTTP! Statut: ${response.status}. Réponse non JSON ou vide.`
              );
            });
        }
        return response.json(); // Si la réponse est OK (par exemple, 200 OK), parser le JSON de succès
      })
      .then((data) => {
        console.log("Réponse JSON du backend (participation):", data);
        if (data.success) {
          participationMessage.innerHTML = `<p class='text-success'>${data.success}</p>`;
          console.log("Participation réussie !");

          const availableSeatsDisplay = document.getElementById(
            "available-seats-display"
          );
          if (availableSeatsDisplay && data.new_available_seats !== undefined) {
            availableSeatsDisplay.textContent = data.new_available_seats; // Met à jour le texte avec la nouvelle valeur
          }

          participateButton.disabled = true;
          participateButton.textContent = "Participation confirmée"; // Changer le texte
        } else {
          // Gérer d'autres types de réponses (pas erreur, pas succès)
          participationMessage.innerHTML = `<p class='text-warning'>Réponse inattendue du serveur après participation.</p>`;
          participateButton.disabled = false; // Réactiver le bouton si pas une erreur fatale
        }
      })
      .catch((error) => {
        // Gérer les erreurs (réseau, HTTP non-OK, erreur backend parsée)
        console.error(
          "Erreur lors de l'enregistrement de la participation:",
          error
        );
        participationMessage.innerHTML = `<p class="text-danger">${
          error.message || "Une erreur est survenue lors de la participation."
        }</p>`;
        participateButton.disabled = false; // Réactiver le bouton en cas d'erreur pour qu'ils puissent réessayer (si l'erreur n'est pas une erreur de logique comme "pas assez de crédits")
      });
  }
});
