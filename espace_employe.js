// js/espace_employe.js

document.addEventListener("DOMContentLoaded", () => {
  const pendingReviewsList = document.getElementById("pending-reviews-list");
  const problematicRidesList = document.getElementById(
    "problematic-rides-list"
  ); // Utilisé pour afficher les SIGNALEMENTS
  const espaceEmployeMessage = document.getElementById(
    "espace-employe-message"
  ); // Ajouter un élément pour les messages

  // Fonction pour afficher un message temporaire
  function displayMessage(message, type = "info") {
    // Assurez-vous d'avoir un élément avec l'ID 'espace-employe-message' dans votre HTML
    if (espaceEmployeMessage) {
      espaceEmployeMessage.innerHTML = `<p class='text-${type}'>${message}</p>`;
      // Masquer le message après quelques secondes (optionnel)
      setTimeout(() => {
        espaceEmployeMessage.innerHTML = "";
      }, 5000); // Masquer après 5 secondes
    }
  }

  // Fonction pour charger les avis en attente de validation (votre fonction existante)
  async function loadPendingReviews() {
    try {
      const response = await fetch("get_pending_reviews.php");
      const data = await response.json();

      if (response.ok) {
        if (data.length > 0) {
          pendingReviewsList.innerHTML = ""; // Vider le message de chargement
          data.forEach((review) => {
            const reviewElement = document.createElement("div");
            reviewElement.classList.add("card", "mb-2");
            // Stocker l'ID de l'avis dans l'élément pour un accès facile
            reviewElement.dataset.reviewId = review.avis_id;

            // Assurez-vous que les noms de colonnes (pseudo_auteur, pseudo_destinataire, commentaire, note)
            // correspondent à ce que backend/get_pending_reviews.php renvoie.
            reviewElement.innerHTML = `
                            <div class="card-body">
                                <h5 class="card-title">Avis sur ${
                                  review.pseudo_destinataire ?? "N/A"
                                } par ${review.pseudo_auteur ?? "N/A"}</h5>
                                <p class="card-text"><strong>Note:</strong> ${
                                  review.note !== null ? review.note : "N/A"
                                }</p>
                                <p class="card-text"><strong>Commentaire:</strong> ${
                                  review.commentaire ?? "N/A"
                                }</p>
                                <p class="card-text"><small>Avis posté le ${
                                  review.date_creation ?? "N/A"
                                }</small></p>
                                <button class="btn btn-success btn-sm validate-review-btn" data-review-id="${
                                  review.avis_id
                                }">Valider</button>
                                <button class="btn btn-danger btn-sm reject-review-btn" data-review-id="${
                                  review.avis_id
                                }">Refuser</button>
                            </div>
                        `;
            pendingReviewsList.appendChild(reviewElement);
          });
          // Ajouter les écouteurs d'événements aux boutons
          addReviewButtonListeners();
        } else {
          pendingReviewsList.innerHTML =
            "<p>Aucun avis en attente de validation.</p>";
        }
      } else {
        // Afficher l'erreur du backend
        pendingReviewsList.innerHTML = `<p class="text-danger">Erreur lors du chargement des avis : ${
          data.error || "Erreur inconnue"
        }</p>`;
      }
    } catch (error) {
      console.error("Erreur lors du chargement des avis:", error);
      pendingReviewsList.innerHTML =
        '<p class="text-danger">Une erreur est survenue lors du chargement des avis.</p>';
    }
  }

  // Fonction pour ajouter les écouteurs d'événements aux boutons de validation/refus (votre fonction existante)
  function addReviewButtonListeners() {
    // Supprimer les écouteurs existants pour éviter les doublons (important si la liste est rechargée)
    document
      .querySelectorAll(".validate-review-btn, .reject-review-btn")
      .forEach((button) => {
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
      });

    // Écouteur pour les boutons de validation
    document.querySelectorAll(".validate-review-btn").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const reviewId = event.target.dataset.reviewId;
        // Appeler la fonction de validation
        await validateReview(reviewId);
      });
    });

    // Écouteur pour les boutons de refus
    document.querySelectorAll(".reject-review-btn").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const reviewId = event.target.dataset.reviewId;
        await rejectReview(reviewId);
      });
    });
  }

  // Fonction pour valider un avis via le backend (votre fonction existante)
  async function validateReview(reviewId) {
    try {
      const response = await fetch("validate_review.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ avis_id: reviewId }), // Utiliser avis_id car c'est ce que le backend attend
      });
      const result = await response.json();

      if (response.ok) {
        displayMessage(result.success, "success");
        // Retirer l'avis de la liste après succès
        const reviewElement = document.querySelector(
          `.card[data-review-id="${reviewId}"]`
        );
        if (reviewElement) {
          // Ajouter une animation de disparition optionnelle
          reviewElement.classList.add("fade-out"); // Si vous avez cette classe CSS
          setTimeout(() => {
            reviewElement.remove();
            // Vérifier si la liste devient vide
            if (pendingReviewsList.children.length === 0) {
              pendingReviewsList.innerHTML =
                "<p>Aucun avis en attente de validation.</p>";
            }
          }, 500); // Délai correspondant à l'animation
        }
      } else {
        // Afficher l'erreur du backend
        displayMessage(
          "Erreur lors de la validation de l'avis : " +
            (result.error || "Erreur inconnue"),
          "danger"
        );
      }
    } catch (error) {
      console.error("Erreur lors de la validation de l'avis:", error);
      displayMessage(
        "Une erreur est survenue lors de la validation de l'avis.",
        "danger"
      );
    }
  }

  // Fonction pour refuser un avis via le backend (votre fonction existante)
  async function rejectReview(reviewId) {
    try {
      const response = await fetch("reject_review.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ review_id: reviewId }), // MODIFIÉ : Utilisez 'review_id' ici
      });
      const result = await response.json();

      if (response.ok) {
        displayMessage(result.success, "success");
        // Retirer l'avis de la liste après succès
        const reviewElement = document.querySelector(
          `.card[data-review-id="${reviewId}"]`
        );
        if (reviewElement) {
          // Ajouter une animation de disparition optionnelle
          reviewElement.classList.add("fade-out"); // Si vous avez cette classe CSS
          setTimeout(() => {
            reviewElement.remove();
            // Si la liste devient vide, afficher le message "Aucun avis..."
            if (pendingReviewsList.children.length === 0) {
              pendingReviewsList.innerHTML =
                "<p>Aucun avis en attente de validation.</p>";
            }
          }, 500); // Délai correspondant à l'animation
        }
      } else {
        // Afficher l'erreur du backend
        displayMessage(
          "Erreur lors du refus de l'avis : " +
            (result.error || "Erreur inconnue"),
          "danger"
        );
      }
    } catch (error) {
      console.error("Erreur lors du refus de l'avis:", error);
      displayMessage(
        "Une erreur est survenue lors du refus de l'avis.",
        "danger"
      );
    }
  }

  async function loadProblematicRides() {
    if (!problematicRidesList) {
      console.error(
        "loadProblematicRides: Élément problematicRidesList non trouvé."
      );
      return;
    }

    problematicRidesList.innerHTML = "<p>Chargement des signalements...</p>";

    try {
      const response = await fetch("list_reported_problems.php");
      const data = await response.json();

      if (response.ok) {
        if (data.length > 0) {
          problematicRidesList.innerHTML = ""; // Vider le message de chargement

          data.forEach((signalement) => {
            // Afficher chaque signalement avec les informations requises par l'US 12
            const signalementElement = document.createElement("div");
            signalementElement.classList.add("card", "mb-3"); // Exemple de style Bootstrap
            signalementElement.dataset.signalementId =
              signalement.signalement_id; // Stocker l'ID du signalement

            // Assurez-vous que les noms de colonnes renvoyés par list_reported_problems.php
            // (covoiturage_id, lieu_depart, lieu_arrivee, date_depart, heure_depart, heure_arrivee,
            // pseudo_passager, email_passager, pseudo_chauffeur, email_chauffeur, description_probleme,
            // date_signalement, statut_signalement) correspondent à ce que vous utilisez ici.
            const formattedDateDepart = signalement.date_depart ?? "N/A";
            const formattedHeureDepart = signalement.heure_depart
              ? signalement.heure_depart.substring(0, 5)
              : "N/A";
            const formattedHeureArrivee = signalement.heure_arrivee
              ? signalement.heure_arrivee.substring(0, 5)
              : "N/A";
            const formattedDateSignalement =
              signalement.date_signalement ?? "N/A";

            signalementElement.innerHTML = `
                          <div class="card-body">
                              <h5 class="card-title">Signalement ID: ${
                                signalement.signalement_id
                              }</h5>
                              <h6 class="card-subtitle mb-2 text-muted">Covoiturage ID: ${
                                signalement.covoiturage_id ?? "N/A"
                              }</h6>
                              <p class="card-text">
                                  <strong>Trajet:</strong> ${
                                    signalement.lieu_depart ?? "N/A"
                                  } &rarr; ${
              signalement.lieu_arrivee ?? "N/A"
            } <br>
                                  <strong>Date:</strong> ${formattedDateDepart} <br>
                                  <strong>Heure(s):</strong> ${formattedHeureDepart} - ${formattedHeureArrivee} <br>
                                  <strong>Signalé par (Passager):</strong> ${
                                    signalement.pseudo_passager ?? "N/A"
                                  } (${
              signalement.email_passager ?? "N/A"
            }) <br>
                                  <strong>Chauffeur concerné:</strong> ${
                                    signalement.pseudo_chauffeur ?? "N/A"
                                  } (${
              signalement.email_chauffeur ?? "N/A"
            }) <br>
                                  <strong>Date du signalement:</strong> ${formattedDateSignalement} <br>
                                  <strong>Statut du signalement:</strong> ${
                                    signalement.statut_signalement ?? "N/A"
                                  }
                              </p>
                              <div class="alert alert-warning mt-3" role="alert">
                                   <strong>Description du problème:</strong><br>${
                                     signalement.description_probleme ??
                                     "Aucune description"
                                   }
                              </div>

                              <!-- Ajouter ici des boutons d'action pour l'employé si nécessaire (ex: Marquer comme traité) -->
                              <!-- <button class="btn btn-secondary btn-sm mark-as-treated-btn" data-signalement-id="${
                                signalement.signalement_id
                              }">Marquer comme traité</button> -->

                          </div>
                      `;
            problematicRidesList.appendChild(signalementElement);
          });

          // Ajouter ici les écouteurs d'événements pour les actions de l'employé sur les signalements si vous en ajoutez
          // addSignalementButtonListeners(); // Fonction à créer si besoin
        } else {
          problematicRidesList.innerHTML =
            "<p>Aucun covoiturage à problème signalé pour le moment.</p>";
        }
      } else {
        // Afficher l'erreur du backend
        problematicRidesList.innerHTML = `<p class="text-danger">Erreur lors du chargement des signalements : ${
          data.error || "Erreur inconnue"
        }</p>`;
      }
    } catch (error) {
      console.error("Erreur lors du chargement des signalements:", error);
      problematicRidesList.innerHTML =
        '<p class="text-danger">Une erreur est survenue lors du chargement des signalements.</p>';
    }
  }

  // Charger les données au chargement de la page
  // Assurez-vous que l'utilisateur est bien un employé avant d'appeler ces fonctions dans un environnement de production réel
  // Pour le développement, vous pouvez les appeler directement
  loadPendingReviews();
  loadProblematicRides(); // Activation de l'appel pour charger les signalements

  // --- Ajouter un élément pour afficher les messages (succès/erreur) ---
  // Assurez-vous d'ajouter <div id="espace-employe-message" class="mt-3"></div> dans votre espace_employe.html
});
