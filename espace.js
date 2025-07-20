// js/espace.js

document.addEventListener("DOMContentLoaded", () => {
  console.log("espace.js chargé.");

  // --- Références aux éléments DOM ---
  const incompleteChauffeurMessageDiv = document.getElementById(
    "incomplete-chauffeur-message"
  );
  const participationMessage = document.getElementById("participation-message"); // Pour les messages temporaires (ajout/modif/suppr véhicule, statut covoiturage)

  // Section Informations Utilisateur
  const userInfoDisplayDiv = document.getElementById("user-info-display"); // Affichage lecture seule
  const userInfoEditFormDiv = document.getElementById("user-info-edit-form"); // Formulaire d'édition
  const editUserButton = document.getElementById("edit-user-button");

  const cancelEditUserButton = document.getElementById("cancel-edit-user"); // Bouton Annuler formulaire édition
  const editUserForm = document.getElementById("edit-user-form"); // Le formulaire lui-même

  // Champs du formulaire d'édition utilisateur
  const editNomInput = document.getElementById("edit-nom");
  const editPrenomInput = document.getElementById("edit-prenom");
  const editFumeurCheckbox = document.getElementById("edit-fumeur");
  const editAnimauxCheckbox = document.getElementById("edit-animaux");
  const editIsChauffeurCheckbox = document.getElementById("edit-is-chauffeur");

  // Section Covoiturages Utilisateur (Chauffeur) - NOUVELLE DIV
  const userDriverRidesDiv = document.getElementById("user-driver-rides"); // Div pour les trajets du chauffeur

  // Section Covoiturages Utilisateur (Participant) - NOUVELLE DIV
  const userParticipantRidesDiv = document.getElementById(
    "user-participant-rides"
  ); // Div pour les trajets du participant

  // --- Nouvelle référence pour la liste des actions en attente du participant ---
  const pendingParticipantActionsList = document.getElementById(
    "pending-participant-actions-list"
  );
  // Ajouter un élément pour afficher les messages spécifiques à l'espace (par exemple, succès/erreur d'ajout de véhicule, etc.)
  const espaceMessagesDiv = document.getElementById("espace-messages"); // Supposons que vous ayez un div pour les messages généraux de l'espace

  // Section Véhicules Utilisateur (Chauffeur)
  const userVehiclesSection = document.getElementById("user-vehicles-section"); // Conteneur de la section Véhicules
  const userVehiclesListDiv = document.getElementById("user-vehicles-list"); // Liste des véhicules existants
  const addVehicleButton = document.getElementById("add-vehicle-button"); // Bouton Ajouter véhicule
  const addVehicleFormDiv = document.getElementById("add-vehicle-form"); // Formulaire d'ajout

  // Champs du formulaire d'ajout de véhicule
  const newVehicleForm = document.getElementById("new-vehicle-form");
  const newVehicleMarqueInput = document.getElementById("new-vehicle-marque");
  const newVehicleModeleInput = document.getElementById("new-vehicle-modele");
  const newVehicleImmatriculationInput = document.getElementById(
    "new-vehicle-immatriculation"
  );
  const newVehicleEnergieSelect = document.getElementById(
    "new-vehicle-energie"
  );
  const newVehicleCouleurInput = document.getElementById("new-vehicle-couleur");
  const newVehicleDateImmatriculationInput = document.getElementById(
    "new-vehicle-date-immatriculation"
  );
  const newVehicleNombrePlacesInput = document.getElementById(
    "new-vehicle-nombre-places"
  );
  const cancelAddVehicleButton = document.getElementById("cancel-add-vehicle"); // Bouton Annuler formulaire ajout

  // Section Formulaire d'édition de véhicule existant
  const editVehicleFormSection = document.getElementById(
    "edit-vehicle-form-section"
  ); // Section du formulaire d'édition
  const editVehicleForm = document.getElementById("edit-vehicle-form"); // Le formulaire lui-même
  const editVehicleIdInput = document.getElementById("edit-vehicle-id"); // Champ caché pour l'ID

  // Champs du formulaire d'édition de véhicule
  const editVehicleMarqueInput = document.getElementById("edit-vehicle-marque");
  const editVehicleModeleInput = document.getElementById("edit-vehicle-modele");
  const editVehicleImmatriculationInput = document.getElementById(
    "edit-vehicle-immatriculation"
  );
  const editVehicleEnergieSelect = document.getElementById(
    "edit-vehicle-energie"
  );
  const editVehicleCouleurInput = document.getElementById(
    "edit-vehicle-couleur"
  );
  const editVehicleDateImmatriculationInput = document.getElementById(
    "edit-vehicle-date-immatriculation"
  );
  const editVehicleNombrePlacesInput = document.getElementById(
    "edit-vehicle-nombre-places"
  );
  const cancelEditVehicleButton = document.getElementById(
    "cancel-edit-vehicle"
  ); // Bouton Annuler formulaire édition véhicule

  // --- Variables pour stocker les données utilisateur ---
  let currentUserInfo = null;
  let currentUserRoles = [];
  let currentUserVehicles = [];
  let currentUserRides = []; // Stockera tous les trajets (chauffeur et participant)

  // --- Vérification initiale et chargement des données ---
  // Vérifier l'existence des divs principales (Info, Trajets Chauffeur, Trajets Participant, Véhicules)
  // Nous ajoutons la vérification de la nouvelle section des actions en attente
  if (
    userInfoDisplayDiv &&
    userDriverRidesDiv &&
    userParticipantRidesDiv &&
    userVehiclesSection &&
    pendingParticipantActionsList // Ajout de la nouvelle section
  ) {
    console.log(
      "Éléments d'affichage essentiels trouvés. Chargement des données..."
    );
    fetchDashboardData(); // Lancer le chargement des données
  } else {
    console.error(
      "Éléments d'affichage essentiels non trouvés. Vérifiez espace.html et les IDs."
    );
    // Afficher des messages d'erreur ou masquer les sections si des éléments essentiels sont manquants
    if (userInfoDisplayDiv)
      userInfoDisplayDiv.innerHTML =
        "<p class='text-danger'>Erreur: Contenu indisponible. Éléments d'affichage manquants.</p>";
    // Vider ou masquer les sections si les divs ne sont pas trouvées
    if (userDriverRidesDiv) userDriverRidesDiv.innerHTML = "";
    else console.warn("userDriverRidesDiv non trouvé.");
    if (userParticipantRidesDiv) userParticipantRidesDiv.innerHTML = "";
    else console.warn("userParticipantRidesDiv non trouvé.");
    // Masquer la nouvelle section si son élément n'est pas trouvé
    if (
      pendingParticipantActionsList &&
      pendingParticipantActionsList.parentElement
    ) {
      pendingParticipantActionsList.parentElement.style.display = "none";
      console.warn(
        "pendingParticipantActionsList non trouvé. Section des actions en attente masquée."
      );
    }

    if (userVehiclesSection) userVehiclesSection.innerHTML = "";
    else console.warn("userVehiclesSection non trouvé.");
    if (editUserButton) editUserButton.style.display = "none";
    if (addVehicleButton) addVehicleButton.style.display = "none";
    // participationMessage est utilisé pour les messages temporaires, peut rester
    // if (participationMessage) participationMessage.innerHTML = "";
  }

  // --- Fonction pour appeler le backend et récupérer les données du tableau de bord ---
  // Cette fonction gère le chargement principal et appelle les fonctions d'affichage spécifiques
  // --- Fonction pour appeler le backend et récupérer les données du tableau de bord ---
  function fetchDashboardData() {
    console.log(
      "fetchDashboardData: Début de l'exécution. Appel du backend..."
    );
    fetch("espace_utilisateur.php", {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => {
        console.log(
          "fetchDashboardData: Réponse reçue du backend. Statut:",
          response.status
        );
        // Clone the response to read it twice (once for status, once for json)
        const clonedResponse = response.clone();
        return response.json().then((data) => {
          data.status = clonedResponse.status; // Add the status to the data object
          return data;
        });
      })
      .then((data) => {
        console.log("fetchDashboardData: Données JSON traitées:", data);

        if (data.status === 401) {
          console.warn(
            "fetchDashboardData: Utilisateur non connecté. Redirection vers la page de connexion."
          );
          if (userInfoDisplayDiv)
            userInfoDisplayDiv.innerHTML =
              "<p class='text-warning'>Vous devez être connecté pour accéder à cet espace.</p>";
          if (participationMessage) participationMessage.innerHTML = "";
          if (userDriverRidesDiv) userDriverRidesDiv.innerHTML = ""; // Vider ou masquer
          if (userParticipantRidesDiv) userParticipantRidesDiv.innerHTML = ""; // Vider ou masquer
          // Masquer la nouvelle section en cas de non-connexion
          if (
            pendingParticipantActionsList &&
            pendingParticipantActionsList.parentElement
          ) {
            pendingParticipantActionsList.parentElement.style.display = "none";
          }
          if (userVehiclesSection) userVehiclesSection.style.display = "none"; // Masquer
          if (editUserButton) editUserButton.style.display = "none";
          if (addVehicleButton) addVehicleButton.style.display = "none";

          setTimeout(() => {
            window.location.href = "connexion.html";
          }, 2000);
          return;
        }

        // Vérifier que les données essentielles sont présentes (pour les statuts non-401)
        // Assurez-vous que backend/espace_utilisateur.php renvoie bien user_info, user_rides, user_roles, user_vehicles
        if (
          data.status >= 200 &&
          data.status < 300 &&
          data.user_info &&
          Array.isArray(data.user_rides) &&
          Array.isArray(data.user_roles) &&
          Array.isArray(data.user_vehicles)
        ) {
          console.log(
            "fetchDashboardData: Données essentielles présentes. Mise à jour de l'affichage."
          );
          currentUserInfo = data.user_info;
          currentUserRoles = data.user_roles;
          currentUserRides = data.user_rides; // Tous les trajets (chauffeur et participant)
          currentUserVehicles = data.user_vehicles;

          displayUserInfo(currentUserInfo, currentUserRoles);
          displayUserRides(currentUserRides); // Afficher tous les trajets dans les sections séparées
          displayUserVehicles(currentUserVehicles); // Gérer l'affichage de la section véhicules

          // --- MODIFIÉ : Appeler loadPendingParticipantActions si l'élément existe (indépendamment du rôle global) ---
          if (pendingParticipantActionsList) {
            // Vérifier si l'élément de la liste existe dans le DOM
            console.log(
              "fetchDashboardData: Élément pendingParticipantActionsList trouvé. Tentative de chargement des actions en attente."
            );
            loadPendingParticipantActions(); // Appeler la nouvelle fonction
            // La visibilité de la section sera gérée dans loadPendingParticipantActions si la liste n'est pas vide
          } else {
            console.warn(
              "fetchDashboardData: Élément pendingParticipantActionsList non trouvé. La section des actions en attente ne sera pas chargée."
            );
            // Si l'élément n'existe pas, on s'assure que la section parente est masquée si elle a été trouvée
            if (
              pendingParticipantActionsList &&
              pendingParticipantActionsList.parentElement
            ) {
              pendingParticipantActionsList.parentElement.style.display =
                "none";
            }
          }
          // --- FIN MODIFIÉ ---

          if (data.chauffeur_info_incomplete) {
            console.log(
              "fetchDashboardData: Informations Chauffeur incomplètes. Affichage du message d'avertissement."
            );
            displayIncompleteChauffeurInfoMessage();
          } else {
            hideIncompleteChauffeurInfoMessage();
          }
        } else {
          console.error(
            "fetchDashboardData: Réponse backend inattendue ou erreur.",
            data
          );
          if (userInfoDisplayDiv)
            userInfoDisplayDiv.innerHTML = `<p class='text-danger'>Erreur : Impossible de charger les données de l'espace utilisateur. (${
              data.error || "Réponse inattendue ou données manquantes"
            })</p>`;
          if (userDriverRidesDiv) userDriverRidesDiv.innerHTML = "";
          if (userParticipantRidesDiv) userParticipantRidesDiv.innerHTML = "";
          if (
            pendingParticipantActionsList &&
            pendingParticipantActionsList.parentElement
          ) {
            pendingParticipantActionsList.parentElement.innerHTML = ""; // Vider en cas d'erreur
            pendingParticipantActionsList.parentElement.style.display = "none"; // Masquer
          }
          if (userVehiclesSection) userVehiclesSection.innerHTML = "";
          if (editUserButton) editUserButton.style.display = "none";
          if (addVehicleButton) addVehicleButton.style.display = "none";
        }
      })
      .catch((error) => {
        console.error(
          "fetchDashboardData: Erreur lors de l'appel fetch ou du traitement initial de la réponse:",
          error
        );
        if (userInfoDisplayDiv)
          userInfoDisplayDiv.innerHTML = `<p class="text-danger">Erreur de communication avec le serveur : ${
            error.message || "Impossible de charger les données."
          }</p>`;
        if (userDriverRidesDiv) userDriverRidesDiv.innerHTML = "";
        if (userParticipantRidesDiv) userParticipantRidesDiv.innerHTML = "";
        if (
          pendingParticipantActionsList &&
          pendingParticipantActionsList.parentElement
        ) {
          pendingParticipantActionsList.parentElement.innerHTML = ""; // Vider en cas d'erreur
          pendingParticipantActionsList.parentElement.style.display = "none"; // Masquer
        }
        if (userVehiclesSection) userVehiclesSection.innerHTML = "";
        if (editUserButton) editUserButton.style.display = "none";
        if (addVehicleButton) addVehicleButton.style.display = "none";
      });
  }

  // --- Fonction pour afficher les informations utilisateur ---
  function displayUserInfo(userInfo, userRoles) {
    console.log(
      "displayUserInfo: Affichage des informations utilisateur:",
      userInfo
    );
    console.log("displayUserInfo: Rôles de l'utilisateur:", userRoles);

    let rolesHtml = "";
    if (Array.isArray(userRoles) && userRoles.length > 0) {
      // Afficher les rôles en français (adapter si les libellés sont stockés différemment)
      const frenchRoles = userRoles.map((role) => {
        if (role === "Chauffeur") return "Chauffeur";
        if (role === "Participant") return "Passager"; // Utiliser 'Passager' pour le frontend
        if (role === "Employe") return "Employé"; // Ajouter le rôle Employé si nécessaire
        return role; // Renvoyer le rôle tel quel si non reconnu
      });
      rolesHtml = `<p><strong>Rôle(s) :</strong> ${frenchRoles.join(", ")}</p>`;
    } else {
      rolesHtml = `<p><strong>Rôle(s) :</strong> Non spécifié</p>`;
    }

    if (userInfoDisplayDiv) {
      userInfoDisplayDiv.innerHTML = `
                     <p><strong>Pseudo :</strong> ${
                       userInfo.pseudo ?? "N/A"
                     }</p>
                     <p><strong>Email :</strong> ${userInfo.email ?? "N/A"}</p>
                     <p><strong>Crédits :</strong> ${
                       userInfo.credits ?? "N/A"
                     }</p>
                     ${rolesHtml}
                     <p><strong>Nom :</strong> ${
                       userInfo.nom ?? "Non spécifié"
                     }</p>
                     <p><strong>Prénom :</strong> ${
                       userInfo.prenom ?? "Non spécifié"
                     }</p>
                     <p><strong>Fumeur :</strong> ${
                       userInfo.fumeur ?? "Non spécifié"
                     }</p>
                     <p><strong>Animaux :</strong> ${
                       userInfo.animaux ?? "Non spécifié"
                     }</p>
                 `;
    }

    if (userInfoDisplayDiv) userInfoDisplayDiv.style.display = "block";
    if (userInfoEditFormDiv) userInfoEditFormDiv.style.display = "none";
    if (editUserButton) editUserButton.style.display = "inline-block";
  }

  // --- Fonction pour pré-remplir le formulaire d'édition utilisateur ---
  function populateEditUserForm(userInfo) {
    console.log(
      "populateEditUserForm: Pré-remplissage du formulaire d'édition avec les données:",
      userInfo
    );
    if (editNomInput) editNomInput.value = userInfo.nom ?? "";
    if (editPrenomInput) editPrenomInput.value = userInfo.prenom ?? "";
    if (editFumeurCheckbox)
      editFumeurCheckbox.checked = userInfo.fumeur === "oui";
    if (editAnimauxCheckbox)
      editAnimauxCheckbox.checked = userInfo.animaux === "oui";
    if (editIsChauffeurCheckbox) {
      const isCurrentlyChauffeur =
        Array.isArray(currentUserRoles) &&
        currentUserRoles.includes("Chauffeur");
      editIsChauffeurCheckbox.checked = isCurrentlyChauffeur;
      console.log(
        `populateEditUserForm: Case à cocher 'Je souhaite devenir Chauffeur' définie sur ${isCurrentlyChauffeur}`
      );
    } else {
      console.warn(
        "populateEditUserForm: Référence editIsChauffeurCheckbox non trouvée."
      );
    }
  }

  // --- Fonctions pour gérer l'affichage/masquage du message d'information incomplète du Chauffeur ---
  function displayIncompleteChauffeurInfoMessage() {
    console.log(
      "displayIncompleteChauffeurInfoMessage: Affichage du message d'information incomplète."
    );
    if (incompleteChauffeurMessageDiv) {
      incompleteChauffeurMessageDiv.innerHTML = `
                    <p>Vous êtes Chauffeur mais votre profil est incomplet.</p>
                    <p>Veuillez ajouter les informations sur votre véhicule et vos préférences.</p>
                     <a href="#user-vehicles-section" class="alert-link">Aller à la section Véhicules</a>
                `;
      incompleteChauffeurMessageDiv.style.display = "block";
    }
  }

  function hideIncompleteChauffeurInfoMessage() {
    console.log(
      "hideIncompleteChauffeurInfoMessage: Masquage du message d'information incomplète."
    );
    if (incompleteChauffeurMessageDiv) {
      incompleteChauffeurMessageDiv.style.display = "none";
      incompleteChauffeurMessageDiv.innerHTML = "";
    }
  }

  // --- Fonction pour afficher la liste des covoiturages de l'utilisateur (Chauffeur ET Participant) dans des sections séparées ---
  function displayUserRides(userRides) {
    console.log(
      "displayUserRides: Réception de tous les trajets de l'utilisateur:",
      userRides
    );

    // Vérifier l'existence des divs cibles
    if (!userDriverRidesDiv) {
      console.error("displayUserRides: Élément userDriverRidesDiv non trouvé.");
      return;
    }
    if (!userParticipantRidesDiv) {
      console.error(
        "displayUserRides: Élément userParticipantRidesDiv non trouvé."
      );
      return;
    }

    // Séparer les trajets en deux tableaux : chauffeur et participant
    const driverRides = userRides.filter((ride) => ride.role === "Chauffeur");
    const participantRides = userRides.filter(
      // Assurez-vous que le backend renvoie bien "Participant" ou le rôle correct
      (ride) => ride.role === "Participant"
    );

    console.log("displayUserRides: Trajets chauffeur filtrés:", driverRides);
    console.log(
      "displayUserRides: Trajets participant filtrés:",
      participantRides
    );

    // --- Affichage des trajets du Chauffeur ---
    userDriverRidesDiv.innerHTML = `<h4>Mes Covoiturages - Chauffeur</h4>`; // Ajoute le titre dynamiquement
    userDriverRidesDiv.style.display = "none"; // Cacher par défaut si pas de trajets

    if (driverRides.length > 0) {
      let driverRidesHtml = '<ul class="list-group">';
      driverRides.forEach((ride) => {
        const formattedDepartureTime = ride.heure_depart
          ? ride.heure_depart.substring(0, 5)
          : "N/A";

        let buttonsHtml = ""; // Boutons spécifiques au chauffeur
        // Logique d'affichage des boutons pour le CHAUFFEUR basée sur le statut du Covoiturage
        if (ride.covoiturage_statut === "Disponible") {
          buttonsHtml = `<button class="btn btn-success btn-sm start-ride-btn" data-ride-id="${ride.covoiturage_id}">Démarrer</button>`;
        } else if (ride.covoiturage_statut === "En cours") {
          buttonsHtml = `<button class="btn btn-info btn-sm end-ride-btn" data-ride-id="${ride.covoiturage_id}">Arrivée à destination</button>`;
        } else if (ride.covoiturage_statut === "Terminé") {
          buttonsHtml = `<span class="badge bg-primary">Terminé</span>`;
        } else if (ride.covoiturage_statut === "Annulé par le chauffeur") {
          buttonsHtml = `<span class="badge bg-secondary">Annulé</span>`;
        } else {
          buttonsHtml = `<span class="badge bg-warning">${
            ride.covoiturage_statut ?? "Statut inconnu"
          }</span>`;
        }

        driverRidesHtml += `
                     <li class="list-group-item d-flex justify-content-between align-items-center">
                         <div>
                             <strong>${ride.lieu_depart ?? "N/A"} &rarr; ${
          ride.lieu_arrivee ?? "N/A"
        }</strong>
                             <br>
                             <small>
                                <strong>Rôle :</strong> Chauffeur <br>
                                <strong>Date :</strong> ${
                                  ride.date_depart ?? "N/A"
                                } <br>
                                <strong>Heure Départ :</strong> ${formattedDepartureTime} <br>
                                <strong>Statut :</strong> ${
                                  ride.covoiturage_statut ?? "N/A"
                                } <br>
                                <strong>Places disponibles :</strong> ${
                                  ride.nb_place ?? "N/A"
                                } <br>
                                <strong>Prix :</strong> ${
                                  ride.prix_personne ?? "N/A"
                                } crédits <br>
                             </small>
                         </div>
                         <div class="ride-actions">
                             ${buttonsHtml}
                             <a href="ride_details.html?id=${
                               ride.covoiturage_id
                             }" class="btn btn-primary btn-sm ml-2">Détails</a>
                         </div>
                     </li>
                `;
      });

      driverRidesHtml += "</ul>";
      userDriverRidesDiv.innerHTML += driverRidesHtml;
      userDriverRidesDiv.style.display = "block"; // Afficher la section Chauffeur si il y a des trajets
    } else {
      // Message si pas de trajets chauffeur
      userDriverRidesDiv.innerHTML +=
        "<p>Vous n'avez aucun covoiturage en tant que chauffeur pour le moment.</p>";
      userDriverRidesDiv.style.display = "block"; // Afficher la section avec le message
    }

    // --- Affichage des trajets du Participant ---
    userParticipantRidesDiv.innerHTML = `<h4>Mes Covoiturages - Passager</h4>`; // Ajoute le titre dynamiquement
    userParticipantRidesDiv.style.display = "none"; // Cacher par défaut si pas de trajets

    if (participantRides.length > 0) {
      let participantRidesHtml = '<ul class="list-group">';
      participantRides.forEach((ride) => {
        const formattedDepartureTime = ride.heure_depart
          ? ride.heure_depart.substring(0, 5)
          : "N/A";

        let buttonsHtml = ""; // Boutons spécifiques au participant
        // Logique d'affichage des boutons pour le PARTICIPANT basée sur le statut du Covoiturage
        // Ici, vous pouvez ajuster l'affichage en fonction du statut de la Participation si nécessaire
        if (
          ride.covoiturage_statut !== "Annulé par le chauffeur" &&
          ride.covoiturage_statut !== "Terminé"
        ) {
          buttonsHtml = `<button class="btn btn-danger btn-sm cancel-participation-btn" data-ride-id="${ride.covoiturage_id}">Annuler Participation</button>`;
        } else if (ride.covoiturage_statut === "Annulé par le chauffeur") {
          buttonsHtml = `<span class="badge bg-secondary">Annulé par le chauffeur</span>`;
        } else if (ride.covoiturage_statut === "Terminé") {
          // *** Affichage pour les trajets terminés ***
          // Si le statut de la participation est 'action_en_attente', on pourrait afficher un message ici
          // pour diriger l'utilisateur vers la section d'action en attente, ou afficher un bouton spécifique ici.
          // Pour l'instant, on affiche juste 'Terminé'. La gestion de l'action se fera dans la nouvelle section.
          buttonsHtml = `<span class="badge bg-primary">Terminé</span>`;
        } else {
          buttonsHtml = `<span class="badge bg-warning">${
            ride.covoiturage_statut ?? "Statut inconnu"
          }</span>`;
        }

        participantRidesHtml += `
                     <li class="list-group-item d-flex justify-content-between align-items-center">
                         <div>
                             <strong>${ride.lieu_depart ?? "N/A"} &rarr; ${
          ride.lieu_arrivee ?? "N/A"
        }</strong>
                             <br>
                             <small>
                                <strong>Rôle :</strong> Participant <br>
                                <strong>Date :</strong> ${
                                  ride.date_depart ?? "N/A"
                                } <br>
                                <strong>Heure Départ :</strong> ${formattedDepartureTime} <br>
                                <strong>Chauffeur :</strong> ${
                                  ride.driver_pseudo ?? "N/A"
                                } <br>
                                <strong>Statut du Covoiturage :</strong> ${
                                  ride.covoiturage_statut ?? "N/A"
                                } <br>
                                <strong>Prix Payé :</strong> ${
                                  ride.prix_personne ?? "N/A"
                                } crédits
                                 <!-- Optionnel : Afficher le statut de la Participation ici -->
                                 <!-- <br><strong>Statut Participation :</strong> ${
                                   ride.participation_statut ?? "N/A"
                                 } -->
                             </small>
                         </div>
                         <div class="ride-actions">
                             ${buttonsHtml}
                             <a href="ride_details.html?id=${
                               ride.covoiturage_id
                             }" class="btn btn-primary btn-sm ml-2">Détails</a>
                         </div>
                     </li>
                `;
      });

      participantRidesHtml += "</ul>";
      userParticipantRidesDiv.innerHTML += participantRidesHtml;
      userParticipantRidesDiv.style.display = "block"; // Afficher la section Participant si il y a des trajets
    } else {
      // Message si pas de trajets participant
      userParticipantRidesDiv.innerHTML +=
        "<p>Vous n'avez aucun covoiturage en tant que passager pour le moment.</p>";
      userParticipantRidesDiv.style.display = "block"; // Afficher la section avec le message
    }

    // Après avoir inséré le HTML dans les deux sections, ajouter les écouteurs d'événements aux boutons
    addRideButtonListeners();

    // NOTE : Les actions en attente du participant sont gérées dans la nouvelle section loadPendingParticipantActions
    // Cette fonction displayUserRides affiche l'historique des trajets participant, pas les actions requises.
  }

  // --- Fonction pour ajouter les écouteurs d'événements aux boutons des trajets ---
  function addRideButtonListeners() {
    console.log(
      "addRideButtonListeners: Ajout des écouteurs pour les boutons de trajet (Démarrer, Arrivée, Annuler Participation)."
    );

    // Écouteurs pour les boutons "Démarrer" (Chauffeur)
    document.querySelectorAll(".start-ride-btn").forEach((button) => {
      // Important: Cloner l'élément pour supprimer tous les écouteurs précédents
      const newButton = button.cloneNode(true);
      button.parentNode.replaceChild(newButton, button);

      newButton.addEventListener("click", async (event) => {
        const rideId = event.target.dataset.rideId;
        console.log("Bouton Démarrer cliqué pour le trajet:", rideId);
        if (confirm("Êtes-vous sûr de vouloir démarrer ce covoiturage ?")) {
          await handleStartRide(rideId);
        }
      });
    });

    // Écouteurs pour les boutons "Arrivée à destination" (Chauffeur)
    document.querySelectorAll(".end-ride-btn").forEach((button) => {
      // Supprimer les écouteurs existants
      const newButton = button.cloneNode(true);
      button.parentNode.replaceChild(newButton, button);

      newButton.addEventListener("click", async (event) => {
        const rideId = event.target.dataset.rideId;
        console.log(
          "Bouton Arrivée à destination cliqué pour le trajet:",
          rideId
        );
        if (
          confirm(
            "Confirmez-vous être arrivé à destination ? Cela mettra à jour le statut et notifiera les participants."
          )
        ) {
          await handleEndRide(rideId);
        }
      });
    });

    // Écouteurs pour les boutons "Annuler Participation" (Participant)
    document.querySelectorAll(".cancel-participation-btn").forEach((button) => {
      // Supprimer les écouteurs existants
      const newButton = button.cloneNode(true);
      button.parentNode.replaceChild(newButton, button);

      newButton.addEventListener("click", async (event) => {
        const rideId = event.target.dataset.rideId;
        console.log(
          "Bouton Annuler Participation cliqué pour le trajet:",
          rideId
        );
        if (
          confirm(
            "Êtes-vous sûr de vouloir annuler votre participation à ce covoiturage ?"
          )
        ) {
          await handleCancelParticipation(rideId);
        }
      });
    });

    // FUTURE : Écouteurs pour les boutons de validation participant (US 11 suite) - Gérés dans addParticipantActionListeners
  }

  // --- Fonctions pour gérer les appels backend (US 11 : Démarrer/Arrêter) ---
  // Ces fonctions appellent les scripts backend correspondants
  async function handleStartRide(rideId) {
    console.log(
      `handleStartRide: Appel à depart_covoiturage.php pour le trajet ${rideId}`
    );
    const startButton = document.querySelector(
      `.start-ride-btn[data-ride-id="${rideId}"]`
    );
    if (startButton) startButton.disabled = true; // Désactiver le bouton pendant le traitement
    // participationMessage semble être utilisé pour les messages généraux temporaires.
    // Si vous voulez des messages spécifiques à l'action de démarrage/arrivée,
    // il faudrait cibler un élément spécifique dans la liste des trajets ou utiliser espaceMessagesDiv
    if (espaceMessagesDiv) espaceMessagesDiv.innerHTML = ""; // Effacer les messages précédents de l zone générale

    try {
      const response = await fetch("depart_covoiturage.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ ride_id: rideId }),
      });
      const result = await response.json();

      if (startButton) startButton.disabled = false; // Réactiver le bouton

      if (response.ok) {
        alert(result.success); // Utiliser alert pour les messages de succès/erreur simple ou utiliser espaceMessagesDiv
        fetchDashboardData(); // Recharger toutes les données pour mettre à jour l'affichage
      } else {
        alert(
          "Erreur lors du démarrage: " + (result.error || "Erreur inconnue")
        ); // Utiliser alert ou espaceMessagesDiv
      }
    } catch (error) {
      console.error("handleStartRide: Erreur lors de l'appel fetch:", error);
      if (startButton) startButton.disabled = false; // Réactiver le bouton en cas d'erreur fetch
      alert(
        "Une erreur de communication est survenue lors du démarrage du covoiturage."
      ); // Utiliser alert ou espaceMessagesDiv
    }
  }

  async function handleEndRide(rideId) {
    console.log(
      `handleEndRide: Appel à arrivee_covoiturage.php pour le trajet ${rideId}`
    );
    const endButton = document.querySelector(
      `.end-ride-btn[data-ride-id="${rideId}"]`
    );
    if (endButton) endButton.disabled = true; // Désactiver le bouton pendant le traitement
    if (espaceMessagesDiv) espaceMessagesDiv.innerHTML = ""; // Effacer les messages précédents

    try {
      const response = await fetch("arrivee_covoiturage.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ ride_id: rideId }),
      });
      const result = await response.json();

      if (endButton) endButton.disabled = false; // Réactiver le bouton

      if (response.ok) {
        alert(result.success); // Utiliser alert ou espaceMessagesDiv
        fetchDashboardData(); // Recharger toutes les données pour mettre à jour l'affichage
      } else {
        alert(
          "Erreur lors de l'arrivée: " + (result.error || "Erreur inconnue")
        ); // Utiliser alert ou espaceMessagesDiv
      }
    } catch (error) {
      console.error("handleEndRide: Erreur lors de l'appel fetch:", error);
      if (endButton) endButton.disabled = false; // Réactiver le bouton en cas d'erreur fetch
      alert(
        "Une erreur de communication est survenue lors de l'arrivée à destination."
      ); // Utiliser alert ou espaceMessagesDiv
    }
  }

  // Fonction pour gérer l'annulation de participation (déjà présente)
  async function handleCancelParticipation(rideId) {
    console.log(
      `handleCancelParticipation: Appel à cancel_participation.php pour le trajet ${rideId}`
    );
    const cancelButton = document.querySelector(
      `.cancel-participation-btn[data-ride-id="${rideId}"]`
    );
    if (cancelButton) cancelButton.disabled = true;
    if (espaceMessagesDiv) espaceMessagesDiv.innerHTML = ""; // Effacer les messages précédents

    try {
      const response = await fetch("cancel_participation.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ ride_id: rideId }),
      });
      const result = await response.json();

      if (cancelButton) cancelButton.disabled = false; // Réactiver le bouton

      if (response.ok) {
        alert(result.success); // Utiliser alert ou espaceMessagesDiv
        fetchDashboardData(); // Recharger l'historique après annulation réussie
      } else {
        alert(
          "Erreur lors de l'annulation: " + (result.error || "Erreur inconnue")
        ); // Utiliser alert ou espaceMessagesDiv
      }
    } catch (error) {
      console.error(
        "handleCancelParticipation: Erreur lors de l'annulation de participation:",
        error
      );
      if (cancelButton) cancelButton.disabled = false; // Réactiver le bouton en cas d'erreur fetch
      alert("Une erreur est survenue lors de l'annulation de participation."); // Utiliser alert ou espaceMessagesDiv
    }
  }

  // --- Nouvelle fonction pour charger et afficher les actions en attente du participant ---
  async function loadPendingParticipantActions() {
    console.log(
      "Appel backend pour charger les actions en attente du participant..."
    );
    // Assurez-vous que l'élément existe avant de tenter de l'utiliser
    if (!pendingParticipantActionsList) {
      console.error(
        "loadPendingParticipantActions: Élément pendingParticipantActionsList non trouvé."
      );
      return; // Arrêter si l'élément n'est pas là
    }

    pendingParticipantActionsList.innerHTML =
      "<p>Chargement des actions en attente...</p>"; // Message de chargement

    try {
      // Appeler le script backend créé (notation_et_commentaire.php)
      const response = await fetch("notation_et_commentaire.php");
      const data = await response.json();

      if (response.ok) {
        if (data.length > 0) {
          pendingParticipantActionsList.innerHTML = ""; // Vider le message de chargement

          data.forEach((action) => {
            // Créer un élément pour chaque trajet nécessitant une action
            const actionElement = document.createElement("div");
            actionElement.classList.add("card", "mb-3"); // Exemple de style Bootstrap
            actionElement.dataset.rideId = action.covoiturage_id; // Stocker l'ID du covoiturage
            actionElement.dataset.participationId = action.participation_id; // Stocker l'ID de la participation
            actionElement.dataset.driverUserId = action.driver_user_id; // Stocker l'ID du chauffeur

            actionElement.innerHTML = `
                          <div class="card-body">
                              <h5 class="card-title">${
                                action.lieu_depart
                              } &rarr; ${action.lieu_arrivee}</h5>
                              <p class="card-text">
                                  <strong>Date :</strong> ${
                                    action.date_depart ?? "N/A"
                                  } <br>
                                  <strong>Heure Départ :</strong> ${
                                    action.heure_depart
                                      ? action.heure_depart.substring(0, 5)
                                      : "N/A"
                                  } <br>
                                  <strong>Chauffeur :</strong> ${
                                    action.driver_pseudo ?? "N/A"
                                  }
                              </p>

                              <!-- Interface de validation et de notation -->
                              <div class="action-interface mt-3">
                                   <h6>Valider le trajet et laisser un avis :</h6>
                                   <button class="btn btn-success btn-sm validate-ride-btn">Trajet bien passé</button>
                                   <button class="btn btn-warning btn-sm report-problem-btn">Signaler un problème</button>

                                   <div class="avis-form mt-2" style="display: none;"> <!-- Formulaire d'avis masqué par défaut -->
                                       <h6>Soumettre un avis et une note :</h6>
                                       <div class="mb-3">
                                           <label for="note-${
                                             action.participation_id
                                           }" class="form-label">Note (sur 5) :</label>
                                           <input type="number" class="form-control form-control-sm review-note" id="note-${
                                             action.participation_id
                                           }" min="1" max="5" step="1" value="5">
                                       </div>
                                       <div class="mb-3">
                                           <label for="commentaire-${
                                             action.participation_id
                                           }" class="form-label">Commentaire :</label>
                                           <textarea class="form-control form-control-sm review-comment" id="commentaire-${
                                             action.participation_id
                                           }" rows="2"></textarea>
                                       </div>
                                       <button class="btn btn-primary btn-sm submit-review-btn">Soumettre Avis</button>
                                   </div>

                                   <div class="problem-form mt-2" style="display: none;"> <!-- Formulaire de signalement masqué par défaut -->
                                        <h6>Décrivez le problème :</h6>
                                        <div class="mb-3">
                                            <label for="problem-description-${
                                              action.participation_id
                                            }" class="form-label">Description :</label>
                                            <textarea class="form-control form-control-sm problem-description" id="problem-description-${
                                              action.participation_id
                                            }" rows="3"></textarea>
                                        </div>
                                         <button class="btn btn-danger btn-sm submit-problem-btn">Signaler</button>
                                     </div>

                                   <p class="action-message mt-2"></p> <!-- Pour afficher les messages de succès/erreur pour cette action -->
                               </div>

                           </div>
                       `;
            pendingParticipantActionsList.appendChild(actionElement);
          });

          // Ajouter les écouteurs d'événements aux nouveaux boutons/formulaires
          addParticipantActionListeners();
        } else {
          pendingParticipantActionsList.innerHTML =
            "<p>Aucun trajet terminé en attente de votre validation ou avis.</p>";
          // Optionnel : masquer la section si elle est vide
          // if (pendingParticipantActionsList && pendingParticipantActionsList.parentElement) {
          //     pendingParticipantActionsList.parentElement.style.display = 'none';
          // }
        }
      } else {
        // Afficher l'erreur du backend
        pendingParticipantActionsList.innerHTML = `<p class="text-danger">Erreur lors du chargement des actions en attente : ${
          data.error || "Erreur inconnue"
        }</p>`;
      }
    } catch (error) {
      console.error(
        "Erreur lors de l'appel au backend pour les actions en attente:",
        error
      );
      pendingParticipantActionsList.innerHTML =
        '<p class="text-danger">Une erreur est survenue lors du chargement des actions en attente.</p>';
    }
  }

  // --- Fonction pour ajouter les écouteurs d'événements aux boutons d'action du participant ---
  function addParticipantActionListeners() {
    console.log(
      "addParticipantActionListeners: Ajout des écouteurs pour les actions du participant."
    );

    // Écouteur pour le bouton "Trajet bien passé" (Valider)
    document.querySelectorAll(".validate-ride-btn").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const cardBody = event.target.closest(".card-body");
        const cardElement = event.target.closest(".card"); // Référence à l'élément carte entier
        const rideId = cardElement.dataset.rideId;
        const participationId = cardElement.dataset.participationId;
        const driverUserId = cardElement.dataset.driverUserId;

        console.log(
          `Bouton 'Trajet bien passé' cliqué pour covoiturage ID: ${rideId}, Participation ID: ${participationId}`
        );

        // Afficher le formulaire d'avis et masquer le formulaire de signalement
        const avisForm = cardBody.querySelector(".avis-form");
        const problemForm = cardBody.querySelector(".problem-form");
        const validateButton = cardBody.querySelector(".validate-ride-btn");
        const reportButton = cardBody.querySelector(".report-problem-btn");

        if (avisForm) avisForm.style.display = "block";
        if (problemForm) problemForm.style.display = "none";
        // Masquer les boutons initiaux après le choix
        if (validateButton) validateButton.style.display = "none";
        if (reportButton) reportButton.style.display = "none";

        // *** NOTE : Si la validation "simple" du trajet doit déclencher les crédits sans soumettre d'avis,
        // il faudrait faire un appel backend séparé ici. Sinon, l'appel se fera lors de la soumission de l'avis.
        // Pour l'instant, l'appel pour les crédits est lié à la soumission de l'avis dans submitReviewAndValidate.
      });
    });

    // Écouteur pour le bouton "Signaler un problème"
    document.querySelectorAll(".report-problem-btn").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const cardBody = event.target.closest(".card-body");
        const cardElement = event.target.closest(".card");
        const rideId = cardElement.dataset.rideId;
        const participationId = cardElement.dataset.participationId;

        console.log(
          `Bouton 'Signaler un problème' cliqué pour covoiturage ID: ${rideId}`
        );

        // Afficher le formulaire de signalement et masquer le formulaire d'avis
        const avisForm = cardBody.querySelector(".avis-form");
        const problemForm = cardBody.querySelector(".problem-form");
        const validateButton = cardBody.querySelector(".validate-ride-btn");
        const reportButton = cardBody.querySelector(".report-problem-btn");

        if (avisForm) avisForm.style.display = "none";
        if (problemForm) problemForm.style.display = "block";
        // Masquer les boutons initiaux
        if (validateButton) validateButton.style.display = "none";
        if (reportButton) reportButton.style.display = "none";
      });
    });

    // Écouteur pour le bouton "Soumettre Avis"
    document.querySelectorAll(".submit-review-btn").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const cardBody = event.target.closest(".card-body");
        const cardElement = event.target.closest(".card");
        const actionMessageElement = cardBody.querySelector(".action-message"); // Élément pour le message
        const rideId = cardElement.dataset.rideId;
        const participationId = cardElement.dataset.participationId;
        const driverUserId = cardElement.dataset.driverUserId; // L'ID du chauffeur
        const reviewNoteInput = cardBody.querySelector(".review-note");
        const reviewCommentInput = cardBody.querySelector(".review-comment");

        const note = reviewNoteInput.value;
        const commentaire = reviewCommentInput.value.trim();

        console.log(
          `Soumettre avis pour covoiturage ID: ${rideId}, Participation ID: ${participationId}, Driver User ID: ${driverUserId}, Note: ${note}, Commentaire: "${commentaire}"`
        );

        // Validation simple des champs (optionnel mais recommandé)
        if (note === "" || commentaire === "") {
          actionMessageElement.innerHTML =
            "<p class='text-warning'>Veuillez entrer une note et un commentaire.</p>";
          return; // Arrêter si les champs sont vides
        }
        // Validation de la note si elle est numérique et dans la plage 1-5
        const parsedNote = parseInt(note);
        if (isNaN(parsedNote) || parsedNote < 1 || parsedNote > 5) {
          actionMessageElement.innerHTML =
            "<p class='text-warning'>Veuillez entrer une note valide entre 1 et 5.</p>";
          return;
        }

        // --- Appel backend pour soumettre l'avis et mettre à jour la participation/crédits ---
        // Nous allons créer un script backend pour cela (ex: backend/submit_participant_action.php)
        await submitReviewAndValidate(
          rideId,
          participationId,
          driverUserId,
          note,
          commentaire,
          actionMessageElement,
          cardElement
        ); // Passer l'élément carte entière pour suppression
      });
    });

    // Écouteur pour le bouton "Signaler" (Problème)
    document.querySelectorAll(".submit-problem-btn").forEach((button) => {
      button.addEventListener("click", async (event) => {
        const cardBody = event.target.closest(".card-body");
        const cardElement = event.target.closest(".card");
        const actionMessageElement = cardBody.querySelector(".action-message"); // Élément pour le message
        const rideId = cardElement.dataset.rideId;
        const participationId = cardElement.dataset.participationId;
        const problemDescriptionInput = cardBody.querySelector(
          ".problem-description"
        );

        const description = problemDescriptionInput.value.trim();

        console.log(
          `Signaler problème pour covoiturage ID: ${rideId}, Participation ID: ${participationId}, Description: "${description}"`
        );

        if (description === "") {
          actionMessageElement.innerHTML =
            "<p class='text-warning'>Veuillez décrire le problème.</p>";
          return;
        }

        // --- Appel backend pour signaler le problème ---
        // Nous allons créer un script backend pour cela (ex: backend/report_problem.php)
        await reportProblem(
          rideId,
          participationId,
          description,
          actionMessageElement,
          cardElement
        ); // Passer l'élément carte entière pour suppression
      });
    });
  }

  // --- Fonctions pour les appels backend (Validation, Soumission Avis, Signalement) ---
  // Ces fonctions seront appelées par les écouteurs d'événements

  // Fonction pour soumettre l'avis/note et valider le trajet (déclenchant les crédits)
  async function submitReviewAndValidate(
    rideId,
    participationId,
    driverUserId,
    note,
    commentaire,
    messageElement,
    cardElement
  ) {
    console.log("Appel backend pour soumettre avis et valider trajet.");
    messageElement.innerHTML =
      "<p class='text-info'>Soumission de l'avis et validation en cours...</p>"; // Message de traitement

    try {
      // --- Appel backend vers le script créé (backend/submit_participant_action.php) ---
      const response = await fetch("submit_participant_action.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          ride_id: rideId, // ID du covoiturage
          participation_id: participationId, // ID de la participation
          driver_user_id: driverUserId, // ID du chauffeur (destinataire de l'avis)
          note: parseInt(note), // S'assurer que la note est un entier
          commentaire: commentaire,
          action_type: "validate_and_review", // Indiquer le type d'action au backend (même si pas strictement nécessaire pour ce script, bonne pratique si le script gérait plusieurs actions)
        }),
      });
      const result = await response.json();

      if (response.ok) {
        // Succès de l'appel backend
        messageElement.innerHTML = `<p class='text-success'>${result.success}</p>`; // Afficher message de succès renvoyé par le backend

        // Retirer la carte de la liste après succès (l'action est terminée pour ce trajet)
        if (cardElement) {
          // Ajouter une animation de disparition optionnelle (si vous avez la classe CSS 'fade-out')
          cardElement.classList.add("fade-out");
          // Retirer l'élément après l'animation ou immédiatement
          setTimeout(() => {
            cardElement.remove();
            // Vérifier si la liste des actions en attente est maintenant vide
            checkIfPendingActionsListIsEmpty();
          }, 500); // Délai pour l'animation (ajuster si besoin)
        }

        // Optionnel : Recharger les données de l'utilisateur si les crédits ont été mis à jour
        // pour que le solde de crédits s'affiche correctement dans l'espace utilisateur.
        // Cela pourrait nécessiter d'appeler fetchDashboardData() ou une fonction plus ciblée.
        // fetchDashboardData(); // Attention: cela recharge TOUT

        // Alternative plus légère si vous n'avez qu'à mettre à jour les crédits affichés :
        // Appeler un script backend simple pour récupérer les crédits de l'utilisateur
        // et mettre à jour l'élément d'affichage des crédits.
      } else {
        // Erreur lors de l'appel backend
        messageElement.innerHTML = `<p class='text-danger'>Erreur : ${
          result.error || "Erreur inconnue"
        }</p>`; // Afficher l'erreur renvoyée par le backend
      }
    } catch (error) {
      // Erreur lors de l'appel fetch lui-même (problème de connexion, etc.)
      console.error(
        "Erreur lors de la soumission de l'avis et validation:",
        error
      );
      messageElement.innerHTML =
        "<p class='text-danger'>Une erreur est survenue lors de la soumission de votre action.</p>"; // Message d'erreur générique
    }
  }

  // Fonction pour signaler un problème
  async function reportProblem(
    rideId,
    participationId,
    description,
    messageElement,
    cardElement
  ) {
    console.log("Appel backend pour signaler un problème.");
    messageElement.innerHTML =
      "<p class='text-info'>Soumission du signalement en cours...</p>"; // Message de traitement

    try {
      // --- Appel backend vers le script créé (backend/report_problem.php) ---
      const response = await fetch("report_problem.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          ride_id: rideId, // ID du covoiturage
          participation_id: participationId, // ID de la participation
          description: description,
          action_type: "report_problem", // Indiquer le type d'action au backend (même si pas strictement nécessaire pour ce script)
        }),
      });
      const result = await response.json();

      if (response.ok) {
        // Succès de l'appel backend
        messageElement.innerHTML = `<p class='text-success'>${result.success}</p>`; // Afficher message de succès renvoyé par le backend

        // Retirer la carte de la liste après succès (car l'action de signalement a été effectuée pour ce trajet)
        if (cardElement) {
          // Ajouter une animation de disparition optionnelle (si vous avez la classe CSS 'fade-out')
          cardElement.classList.add("fade-out");
          // Retirer l'élément après l'animation ou immédiatement
          setTimeout(() => {
            cardElement.remove();
            // Vérifier si la liste des actions en attente est maintenant vide
            checkIfPendingActionsListIsEmpty();
          }, 500); // Délai pour l'animation (ajuster si besoin)
        }

        // Optionnel : Recharger les données de l'utilisateur si le statut de la participation a changé
        // fetchDashboardData(); // Attention: cela recharge TOUT
      } else {
        // Erreur lors de l'appel backend
        messageElement.innerHTML = `<p class='text-danger'>Erreur : ${
          result.error || "Erreur inconnue"
        }</p>`; // Afficher l'erreur renvoyée par le backend
      }
    } catch (error) {
      // Erreur lors de l'appel fetch lui-même (problème de connexion, etc.)
      console.error("Erreur lors du signalement du problème:", error);
      messageElement.innerHTML =
        "<p class='text-danger'>Une erreur est survenue lors du signalement du problème.</p>"; // Message d'erreur générique
    }
  }

  // Fonction utilitaire pour vérifier si la liste des actions en attente est vide
  function checkIfPendingActionsListIsEmpty() {
    if (
      pendingParticipantActionsList &&
      pendingParticipantActionsList.children.length === 0
    ) {
      pendingParticipantActionsList.innerHTML =
        "<p>Aucun trajet terminé en attente de votre validation ou avis.</p>";
    }
  }

  // --- Section Véhicules Utilisateur (Chauffeur) ---
  // Les fonctions displayUserVehicles, addVehicleEventListeners, addVehicle, updateVehicle, deleteVehicle,
  // populateEditUserForm, updateUserInfo, populateEditVehicleForm, etc. sont vos fonctions existantes

  // Fonction pour afficher la liste des véhicules de l'utilisateur (votre code existant)
  function displayUserVehicles(userVehicles) {
    console.log(
      "displayUserVehicles: Affichage des véhicules de l'utilisateur:",
      userVehicles
    );
    // Vérifier l'existence des éléments nécessaires pour cette section
    if (
      !userVehiclesSection ||
      !userVehiclesListDiv ||
      !addVehicleButton ||
      !addVehicleFormDiv ||
      !editVehicleFormSection
    ) {
      console.error(
        "displayUserVehicles: Éléments de la section Véhicules manquants."
      );
      // Si les éléments de la section véhicules ne sont pas présents, masquer la section parente si elle existe
      if (userVehiclesSection) userVehiclesSection.style.display = "none";
      return; // Arrêter l'exécution de la fonction si les éléments manquent
    }

    // Déterminer si l'utilisateur est Chauffeur (nécessaire pour afficher la section véhicules)
    const isChauffeur =
      Array.isArray(currentUserRoles) && currentUserRoles.includes("Chauffeur");

    // Gérer la visibilité de la section principale des véhicules
    if (isChauffeur) {
      userVehiclesSection.style.display = "block";
      console.log(
        "displayUserVehicles: Utilisateur est Chauffeur. Affichage de la section véhicules."
      );
    } else {
      userVehiclesSection.style.display = "none";
      console.log(
        "displayUserVehicles: Utilisateur n'est PAS Chauffeur. Masquage de la section véhicules."
      );
      // Vider ou masquer les éléments internes si l'utilisateur n'est pas chauffeur
      if (userVehiclesListDiv) userVehiclesListDiv.innerHTML = "";
      if (addVehicleButton) addVehicleButton.style.display = "none";
      if (addVehicleFormDiv) addVehicleFormDiv.style.display = "none";
      if (editVehicleFormSection) editVehicleFormSection.style.display = "none";
      return; // Arrêter l'exécution si l'utilisateur n'est pas Chauffeur
    }

    // Si l'utilisateur est Chauffeur, gérer l'affichage de la liste des véhicules et des formulaires
    userVehiclesListDiv.innerHTML = `<h4>Mes Vehicules</h4>`; // Réinitialiser le titre et la liste
    if (!Array.isArray(userVehicles) || userVehicles.length === 0) {
      userVehiclesListDiv.innerHTML +=
        "<p>Vous n'avez pas encore de véhicule enregistré.</p>";
      if (addVehicleButton) addVehicleButton.style.display = "inline-block"; // Afficher le bouton Ajouter
      if (addVehicleFormDiv) addVehicleFormDiv.style.display = "none"; // Masquer le formulaire Ajouter
      if (editVehicleFormSection) editVehicleFormSection.style.display = "none"; // Masquer le formulaire d'édition
    } else {
      let vehiclesHtml = '<ul class="list-group">';

      userVehicles.forEach((vehicle) => {
        const formattedFirstRegistrationDate =
          vehicle.date_premiere_immatriculation ?? "Non spécifiée";

        vehiclesHtml += `
                              <li class="list-group-item">
                                  <strong>${vehicle.marque ?? "N/A"} ${
          vehicle.modele ?? "N/A"
        }</strong> (${vehicle.couleur ?? "N/A"}, ${vehicle.energie ?? "N/A"})
                                  <br>
                                  Immatriculation: ${
                                    vehicle.immatriculation ?? "N/A"
                                  } | Places: ${vehicle.nombre_places ?? "N/A"}
                                  <br>
                                  Première immatriculation: ${formattedFirstRegistrationDate}
                                   <div class="mt-2">
                                       <button class="btn btn-sm btn-outline-primary edit-vehicle-button" data-vehicle-id="${
                                         vehicle.voiture_id
                                       }">Modifier</button>
                                       <button class="btn btn-sm btn-outline-danger delete-vehicle-button" data-vehicle-id="${
                                         vehicle.voiture_id
                                       }">Supprimer</button>
                                   </div>
                              </li>
                         `;
      });

      vehiclesHtml += "</ul>";

      userVehiclesListDiv.innerHTML += vehiclesHtml; // Ajouter le HTML des véhicules

      // S'assurer que le bouton Ajouter et la liste sont visibles, les formulaires masqués
      if (userVehiclesListDiv) userVehiclesListDiv.style.display = "block";
      if (addVehicleButton) addVehicleButton.style.display = "inline-block";
      if (addVehicleFormDiv) addVehicleFormDiv.style.display = "none";
      if (editVehicleFormSection) editVehicleFormSection.style.display = "none";

      addVehicleEventListeners(); // Appeler la fonction pour ajouter les écouteurs aux boutons véhicule
    }
  }

  // --- Fonction pour ajouter les écouteurs d'événements aux boutons Modifier/Supprimer des véhicules ---
  function addVehicleEventListeners() {
    console.log(
      "addVehicleEventListeners: Début de l'ajout des écouteurs pour les boutons véhicule."
    );
    const editButtons = document.querySelectorAll(".edit-vehicle-button");
    console.log(
      "addVehicleEventListeners: Boutons Modifier trouvés:",
      editButtons.length
    );

    editButtons.forEach((button) => {
      // Important: Cloner l'élément pour supprimer tous les écouteurs précédents
      const newButton = button.cloneNode(true);
      button.parentNode.replaceChild(newButton, button);

      newButton.addEventListener("click", (event) => {
        console.log("Bouton Modifier véhicule cliqué !");
        const vehicleId = event.target.dataset.vehicleId;
        console.log("ID du véhicule cliqué:", vehicleId);
        const vehicleToEdit = currentUserVehicles.find(
          (v) => v.voiture_id == vehicleId
        );

        if (vehicleToEdit) {
          console.log("Véhicule trouvé pour édition:", vehicleToEdit);
          populateEditVehicleForm(vehicleToEdit);
          if (userVehiclesListDiv) userVehiclesListDiv.style.display = "none";
          if (addVehicleButton) addVehicleButton.style.display = "none";
          if (addVehicleFormDiv) addVehicleFormDiv.style.display = "none";
          if (editVehicleFormSection)
            editVehicleFormSection.style.display = "block";
        } else {
          console.warn(
            "Véhicule non trouvé dans currentUserVehicles pour ID:",
            vehicleId
          );
          if (espaceMessagesDiv)
            espaceMessagesDiv.innerHTML =
              "<p class='text-danger'>Erreur : Véhicule à modifier introuvable.</p>";
        }
      });
    });

    const deleteButtons = document.querySelectorAll(".delete-vehicle-button");
    console.log(
      "addVehicleEventListeners: Boutons Supprimer trouvés:",
      deleteButtons.length
    );

    deleteButtons.forEach((button) => {
      // Important: Cloner l'élément pour supprimer tous les écouteurs précédents
      const newButton = button.cloneNode(true);
      button.parentNode.replaceChild(newButton, button);

      newButton.addEventListener("click", (event) => {
        console.log("Bouton Supprimer véhicule cliqué !");
        const vehicleId = event.target.dataset.vehicleId;
        console.log("ID du véhicule cliqué pour suppression:", vehicleId);
        const isConfirmed = confirm(
          "Confirmez-vous vouloir supprimer ce véhicule ?"
        );
        if (isConfirmed) {
          console.log("Suppression du véhicule confirmée pour ID:", vehicleId);
          // Appel direct à la fonction deleteVehicle (assurez-vous qu'elle est définie)
          deleteVehicle(vehicleId);
        }
      });
    });
    console.log(
      "addVehicleEventListeners: Écouteurs pour les boutons véhicule ajoutés."
    );
  }

  // --- Écouteurs d'événements pour les boutons et formulaires de gestion des véhicules ---

  // Bouton Ajouter un véhicule
  if (addVehicleButton) {
    addVehicleButton.addEventListener("click", () => {
      console.log("Bouton Ajouter un véhicule cliqué.");
      // Masquer les autres sections véhicules et afficher le formulaire d'ajout
      if (userVehiclesListDiv) userVehiclesListDiv.style.display = "none";
      if (editVehicleFormSection) editVehicleFormSection.style.display = "none";
      if (addVehicleButton) addVehicleButton.style.display = "none";
      if (addVehicleFormDiv) addVehicleFormDiv.style.display = "block";
      if (newVehicleForm) newVehicleForm.reset(); // Réinitialiser le formulaire d'ajout
      if (espaceMessagesDiv) espaceMessagesDiv.innerHTML = ""; // Effacer les messages précédents
    });
  }

  // Bouton Annuler (formulaire ajout véhicule)
  if (cancelAddVehicleButton) {
    cancelAddVehicleButton.addEventListener("click", () => {
      console.log("Bouton Annuler ajout véhicule cliqué.");
      // Masquer le formulaire d'ajout et réafficher les éléments précédents (liste véhicules et bouton Ajouter)
      if (addVehicleFormDiv) addVehicleFormDiv.style.display = "none";
      if (userVehiclesListDiv) userVehiclesListDiv.style.display = "block";
      if (addVehicleButton) addVehicleButton.style.display = "inline-block";
      if (newVehicleForm) newVehicleForm.reset();
      if (espaceMessagesDiv) espaceMessagesDiv.innerHTML = ""; // Effacer les messages précédents
    });
  }

  // Soumission du formulaire d'ajout de véhicule
  if (newVehicleForm) {
    newVehicleForm.addEventListener("submit", (event) => {
      event.preventDefault();
      console.log("Formulaire d'ajout de véhicule soumis.");

      // Récupérer les données du formulaire
      const newVehicleData = {
        marque: newVehicleMarqueInput ? newVehicleMarqueInput.value.trim() : "", // Vérification
        modele: newVehicleModeleInput ? newVehicleModeleInput.value.trim() : "", // Vérification
        immatriculation: newVehicleImmatriculationInput
          ? newVehicleImmatriculationInput.value.trim()
          : "", // Vérification
        energie: newVehicleEnergieSelect ? newVehicleEnergieSelect.value : "", // Vérification
        couleur: newVehicleCouleurInput
          ? newVehicleCouleurInput.value.trim()
          : "", // Vérification
        date_premiere_immatriculation: newVehicleDateImmatriculationInput
          ? newVehicleDateImmatriculationInput.value
          : "", // Vérification
        nombre_places: newVehicleNombrePlacesInput
          ? parseInt(newVehicleNombrePlacesInput.value)
          : 0, // Vérification et conversion
      };

      console.log("Nouveau véhicule à envoyer:", newVehicleData);

      addVehicle(newVehicleData); // Appeler la fonction pour gérer l'appel backend
    });
  }

  // Bouton Annuler (formulaire édition véhicule)
  if (cancelEditVehicleButton) {
    cancelEditVehicleButton.addEventListener("click", () => {
      console.log("Bouton Annuler édition véhicule cliqué.");
      // Masquer le formulaire d'édition et réafficher la liste des véhicules et le bouton Ajouter
      if (editVehicleFormSection) editVehicleFormSection.style.display = "none";
      if (userVehiclesListDiv) userVehiclesListDiv.style.display = "block";
      if (addVehicleButton) addVehicleButton.style.display = "inline-block";
      if (editVehicleForm) editVehicleForm.reset();
      if (espaceMessagesDiv) espaceMessagesDiv.innerHTML = ""; // Effacer les messages précédents
    });
  }

  // Soumission du formulaire d'édition de véhicule
  if (editVehicleForm) {
    editVehicleForm.addEventListener("submit", (event) => {
      event.preventDefault();
      console.log("Formulaire d'édition de véhicule soumis.");

      const vehicleId = editVehicleIdInput ? editVehicleIdInput.value : null; // Vérification et récupération de l'ID

      if (!vehicleId) {
        console.error("ID du véhicule manquant pour la mise à jour.");
        if (espaceMessagesDiv)
          espaceMessagesDiv.innerHTML =
            "<p class='text-danger'>Erreur: ID du véhicule manquant.</p>";
        return;
      }

      const updatedVehicleData = {
        voiture_id: vehicleId,
        marque: editVehicleMarqueInput
          ? editVehicleMarqueInput.value.trim()
          : "", // Vérification
        modele: editVehicleModeleInput
          ? editVehicleModeleInput.value.trim()
          : "", // Vérification
        immatriculation: editVehicleImmatriculationInput
          ? editVehicleImmatriculationInput.value.trim()
          : "", // Vérification
        energie: editVehicleEnergieSelect ? editVehicleEnergieSelect.value : "", // Vérification
        couleur: editVehicleCouleurInput
          ? editVehicleCouleurInput.value.trim()
          : "", // Vérification
        date_premiere_immatriculation: editVehicleDateImmatriculationInput
          ? editVehicleDateImmatriculationInput.value
          : "", // Vérification
        nombre_places: editVehicleNombrePlacesInput
          ? parseInt(editVehicleNombrePlacesInput.value)
          : 0, // Vérification et conversion
      };

      console.log(
        "Données véhicule mises à jour à envoyer:",
        updatedVehicleData
      );

      updateVehicle(updatedVehicleData); // Appeler la fonction de mise à jour
    });
  }

  // --- Fonctions pour gérer les appels backend pour les modifications (Véhicules) ---

  function addVehicle(vehicleData) {
    console.log(
      "addVehicle: Appel backend pour ajouter un véhicule:",
      vehicleData
    );
    const submitButton = newVehicleForm
      ? newVehicleForm.querySelector('button[type="submit"]')
      : null;
    if (submitButton) submitButton.disabled = true;
    if (espaceMessagesDiv) espaceMessagesDiv.innerHTML = ""; // Effacer les messages précédents

    fetch("add_vehicle.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(vehicleData),
    })
      .then((response) => {
        console.log(
          "addVehicle: Réponse reçue du backend. Statut:",
          response.status
        );
        return response.json().then((data) => {
          data.status = response.status;
          return data;
        });
      })
      .then((data) => {
        console.log("addVehicle: Réponse JSON traitée:", data);
        if (submitButton) submitButton.disabled = false;

        if (data.status >= 200 && data.status < 300) {
          if (data.success) {
            alert(data.success); // Utiliser alert pour les messages de succès/erreur simple
            fetchDashboardData(); // Recharger tout
            if (addVehicleFormDiv) addVehicleFormDiv.style.display = "none";
            if (userVehiclesListDiv)
              userVehiclesListDiv.style.display = "block"; // Réafficher la liste
            if (addVehicleButton)
              addVehicleButton.style.display = "inline-block"; // Réafficher le bouton Ajouter
            if (newVehicleForm) newVehicleForm.reset();
          } else {
            alert("Opération terminée, mais réponse inattendue."); // Afficher message d'avertissement
          }
        } else {
          alert("Erreur: " + (data.error || `Statut HTTP ${data.status}.`)); // Afficher l'erreur du backend
        }
      })
      .catch((error) => {
        console.error(
          "addVehicle: Erreur lors de l'appel fetch ou du traitement initial:",
          error
        );
        if (submitButton) submitButton.disabled = false;
        alert(
          "Une erreur de communication est survenue lors de l'ajout du véhicule."
        );
      });
  }

  function updateVehicle(vehicleData) {
    console.log(
      "updateVehicle: Appel backend pour mettre à jour un véhicule:",
      vehicleData
    );
    const submitButton = editVehicleForm
      ? editVehicleForm.querySelector('button[type="submit"]')
      : null;
    if (submitButton) submitButton.disabled = true;
    if (espaceMessagesDiv) espaceMessagesDiv.innerHTML = ""; // Effacer les messages précédents

    fetch("update_vehicle.php", {
      method: "POST", // Ou PUT
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(vehicleData),
    })
      .then((response) => {
        console.log(
          "updateVehicle: Réponse reçue du backend. Statut:",
          response.status
        );
        return response.json().then((data) => {
          data.status = response.status;
          return data;
        });
      })
      .then((data) => {
        console.log("updateVehicle: Réponse JSON traitée:", data);
        if (submitButton) submitButton.disabled = false;

        if (data.status >= 200 && data.status < 300) {
          if (data.success) {
            alert(data.success);
            fetchDashboardData(); // Recharger tout
            if (editVehicleFormSection)
              editVehicleFormSection.style.display = "none";
            if (userVehiclesListDiv)
              userVehiclesListDiv.style.display = "block";
            if (addVehicleButton)
              addVehicleButton.style.display = "inline-block";
            if (editVehicleForm) editVehicleForm.reset();
          } else {
            alert("Opération terminée, mais réponse inattendue.");
          }
        } else {
          alert("Erreur: " + (data.error || `Statut HTTP ${data.status}.`));
        }
      })
      .catch((error) => {
        console.error(
          "updateVehicle: Erreur lors de l'appel fetch ou du traitement initial:",
          error
        );
        if (submitButton) submitButton.disabled = false;
        alert(
          "Une erreur de communication est survenue lors de la mise à jour du véhicule."
        );
      });
  }
  // --- Fin de la fonction updateVehicle ---

  // --- Début de la fonction deleteVehicle ---
  function deleteVehicle(vehicleId) {
    console.log(
      "deleteVehicle: Appel backend pour supprimer un véhicule avec ID:",
      vehicleId
    );
    // Vous pourriez vouloir désactiver le bouton Supprimer spécifique qui a été cliqué
    // Pour l'instant, nous ne désactivons pas le bouton Supprimer.

    if (espaceMessagesDiv) espaceMessagesDiv.innerHTML = ""; // Effacer les messages précédents

    fetch("delete_vehicle.php", {
      method: "POST", // Ou DELETE
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ vehicle_id: vehicleId }),
    })
      .then((response) => {
        console.log(
          "deleteVehicle: Réponse reçue du backend. Statut:",
          response.status
        );
        return response.json().then((data) => {
          data.status = response.status;
          return data;
        });
      })
      .then((data) => {
        console.log("deleteVehicle: Réponse JSON traitée:", data);

        if (data.status >= 200 && data.status < 300) {
          if (data.success) {
            alert(data.success);
            fetchDashboardData(); // Recharger tout
          } else {
            alert("Opération terminée, mais réponse inattendue.");
          }
        } else {
          alert("Erreur: " + (data.error || `Statut HTTP ${data.status}.`));
        }
      })
      .catch((error) => {
        console.error(
          "deleteVehicle: Erreur lors de l'appel fetch ou du traitement initial:",
          error
        );
        alert(
          "Une erreur de communication est survenue lors de la suppression du véhicule."
        );
      });
  }
  // --- Fin de la fonction deleteVehicle ---

  // --- Écouteurs d'événements pour les boutons d'édition/annulation utilisateur ---
  // Ces écouteurs et leurs fonctions associées (populateEditUserForm, updateUserInfo)
  // semblent être déjà complétés dans votre code.
  // Je vais juste inclure la fin du listener du formulaire d'édition utilisateur
  // qui était tronquée.

  if (editUserForm) {
    editUserForm.addEventListener("submit", (event) => {
      event.preventDefault();
      console.log("Formulaire d'édition utilisateur soumis.");

      const updatedUserInfo = {
        nom: editNomInput ? editNomInput.value.trim() : "",
        prenom: editPrenomInput ? editPrenomInput.value.trim() : "",
        fumeur: editFumeurCheckbox
          ? editFumeurCheckbox.checked
            ? "oui"
            : "non"
          : "non",
        animaux: editAnimauxCheckbox
          ? editAnimauxCheckbox.checked
            ? "oui"
            : "non"
          : "non",
        is_chauffeur: editIsChauffeurCheckbox
          ? editIsChauffeurCheckbox.checked
          : false,
        // Inclure d'autres champs si nécessaires (email, etc.)
      };

      console.log(
        "Données utilisateur mises à jour à envoyer:",
        updatedUserInfo
      );

      updateUserInfo(updatedUserInfo); // Appeler la fonction pour gérer l'appel backend
    });
  }
  // --- Fin du listener du formulaire d'édition utilisateur ---

  // --- Le reste de vos fonctions existantes (updateUserInfo, populateEditVehicleForm, etc.)
  // et les nouvelles fonctions pour les actions en attente du participant
  // (loadPendingParticipantActions, addParticipantActionListeners, submitReviewAndValidate,
  // reportProblem, checkIfPendingActionsListIsEmpty) devraient suivre ici.
  // Je vais essayer de vous les donner séparément si nécessaire.
}); // Fin de l'écouteur DOMContentLoaded
