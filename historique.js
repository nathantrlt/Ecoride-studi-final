document.addEventListener("DOMContentLoaded", () => {
  const historiqueListe = document.getElementById("historique-liste");

  // Fonction pour charger et afficher l'historique
  async function loadRideHistory() {
    try {
      const response = await fetch("historique_covoiturage.php", {
        method: "GET", // S'assurer que la méthode est GET
      });
      const data = await response.json();

      if (response.ok) {
        if (data.length > 0) {
          historiqueListe.innerHTML = ""; // Vider le message de chargement

          data.forEach((ride) => {
            // Créer un élément pour chaque covoiturage
            const rideElement = document.createElement("div");
            rideElement.classList.add("card", "mb-3"); // Exemple de style Bootstrap

            let buttonsHtml = "";
            // Logique pour afficher les boutons d'annulation ou le statut terminé/annulé
            if (ride.status === "Terminé") {
              // Si le trajet est terminé, afficher seulement le statut avec un badge bleu (comme dans espace.js)
              buttonsHtml = `<span class="badge bg-primary">Terminé</span>`;
            } else if (ride.status === "Annulé par le chauffeur") {
              // Si le trajet est annulé par le chauffeur, afficher le statut Annulé avec un badge gris (comme dans espace.js)
              buttonsHtml = `<span class="badge bg-secondary">Annulé par le chauffeur</span>`;
            } else {
              // Si le trajet n'est ni terminé ni annulé
              if (ride.role === "Chauffeur") {
                // Si c'est un chauffeur et que le statut n'est pas Annulé par le chauffeur ou Terminé, afficher le bouton Annuler Covoiturage
                buttonsHtml = `<button class="btn btn-warning btn-sm cancel-ride-btn" data-ride-id="${ride.ride_id}">Annuler Covoiturage</button>`;
              } else if (ride.role === "Participant") {
                // Si c'est un participant et que le statut n'est pas Annulé par le chauffeur ou Terminé, afficher le bouton Annuler Participation
                buttonsHtml = `<button class="btn btn-danger btn-sm cancel-participation-btn" data-ride-id="${ride.ride_id}">Annuler Participation</button>`;
              }
            }

            // Logique d'affichage conditionnel des détails du trajet
            let rideDetailsHtml = "";
            if (ride.role === "Chauffeur") {
              rideDetailsHtml = `
                                    <strong>Rôle :</strong> ${ride.role} <br>
                                    <strong>Date :</strong> ${ride.departure_date} <br>
                                    <strong>Heure Départ :</strong> ${ride.departure_time} <br>
                                    <strong>Statut :</strong> ${ride.status} <br>
                                    <strong>Places disponibles :</strong> ${ride.availableSeats} <br>
                                    <strong>Prix :</strong> ${ride.price} crédits <br>
                                    <strong>Véhicule :</strong> ${ride.vehicle_model} (${ride.vehicle_energy})
                            `;
            } else if (ride.role === "Participant") {
              rideDetailsHtml = `
                                    <strong>Rôle :</strong> ${ride.role} <br>
                                    <strong>Date :</strong> ${ride.departure_date} <br>
                                    <strong>Heure Départ :</strong> ${ride.departure_time} <br>
                                    <strong>Chauffeur :</strong> ${ride.driver.pseudo} <br>
                                    <strong>Prix :</strong> ${ride.price} crédits <br>
                                    <strong>Véhicule :</strong> ${ride.vehicle_model} (${ride.vehicle_energy})
                             `;
            }

            rideElement.innerHTML = `
                            <div class="card-body">
                                <h5 class="card-title">${
                                  ride.departure_location
                                } à ${ride.arrival_location}</h5>
                                <p class="card-text">
                                    ${rideDetailsHtml}
                                </p>
                                ${buttonsHtml}
                                <a href="${
                                  ride.detailsLink
                                }" class="btn btn-primary btn-sm ml-2">Détails</a>
                                <!-- Ajout du statut ici -->
                                <span class="badge ${
                                  ride.status === "Terminé"
                                    ? "badge-success"
                                    : "badge-info"
                                }">${ride.status}</span>
                            </div>
                        `;

            historiqueListe.appendChild(rideElement);
          });

          // Ajouter les écouteurs d'événements aux boutons d'annulation (seulement s'ils sont présents)
          addCancelButtonListeners();
        } else {
          historiqueListe.innerHTML =
            "<p>Aucun covoiturage dans votre historique pour le moment.</p>";
        }
      } else {
        // Afficher l'erreur du backend
        historiqueListe.innerHTML = `<p class="text-danger">Erreur lors du chargement de l'historique : ${data.error}</p>`;
      }
    } catch (error) {
      console.error("Erreur lors de l'appel au backend:", error);
      historiqueListe.innerHTML =
        '<p class="text-danger">Une erreur est survenue lors du chargement de l\'historique.</p>';
    }
  }

  // Fonction pour ajouter les écouteurs d'événements aux boutons d'annulation
  function addCancelButtonListeners() {
    // Écouteur pour les boutons d'annulation de participation
    document.querySelectorAll(".cancel-participation-btn").forEach((button) => {
      // Supprimer les écouteurs existants pour éviter les doublons
      const old_handler = button.onclick;
      if (old_handler) button.removeEventListener("click", old_handler);

      button.addEventListener("click", async (event) => {
        const rideId = event.target.dataset.rideId;
        if (
          confirm(
            "Êtes-vous sûr de vouloir annuler votre participation à ce covoiturage ?"
          )
        ) {
          await handleCancelParticipation(rideId);
        }
      });
    });

    // Écouteur pour les boutons d'annulation de covoiturage par le chauffeur
    document.querySelectorAll(".cancel-ride-btn").forEach((button) => {
      // Supprimer les écouteurs existants pour éviter les doublons
      const old_handler = button.onclick;
      if (old_handler) button.removeEventListener("click", old_handler);

      button.addEventListener("click", async (event) => {
        const rideId = event.target.dataset.rideId;
        if (
          confirm(
            "Êtes-vous sûr de vouloir annuler ce covoiturage ? Cela remboursera tous les participants et enverra un e-mail."
          )
        ) {
          await handleCancelRideByDriver(rideId);
        }
      });
    });
  }

  // Fonctions pour gérer les appels aux scripts backend d'annulation
  async function handleCancelParticipation(rideId) {
    console.log(
      `Appel à cancel_participation.php pour le trajet ${rideId}`
    );
    try {
      const response = await fetch("cancel_participation.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ ride_id: rideId }),
      });
      const result = await response.json();

      if (response.ok) {
        alert(result.success);
        loadRideHistory(); // Recharger l'historique après annulation réussie
      } else {
        alert("Erreur lors de l'annulation: " + result.error);
      }
    } catch (error) {
      console.error("Erreur lors de l'annulation de participation:", error);
      alert("Une erreur est survenue lors de l'annulation de participation.");
    }
  }

  async function handleCancelRideByDriver(rideId) {
    console.log(
      `Appel à cancel_ride_by_driver.php pour le trajet ${rideId}`
    );
    try {
      const response = await fetch("cancel_ride_by_driver.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ ride_id: rideId }),
      });
      const result = await response.json();

      if (response.ok) {
        alert(result.success);
        loadRideHistory(); // Recharger l'historique après annulation réussie
      } else {
        alert("Erreur lors de l'annulation: " + result.error);
      }
    } catch (error) {
      console.error("Erreur lors de l'annulation par le chauffeur:", error);
      alert("Une erreur est survenue lors de l'annulation par le chauffeur.");
    }
  }

  // Charger l'historique au chargement de la page
  loadRideHistory();
});
