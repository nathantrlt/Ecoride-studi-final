// js/create_ride.js

document.addEventListener("DOMContentLoaded", () => {
  console.log("create_ride.js chargé.");

  // --- Références aux éléments DOM ---
  const createRideForm = document.getElementById("create-ride-form");
  const createRideMessageDiv = document.getElementById("create-ride-message");

  // Champs du formulaire
  const departureAddressInput = document.getElementById("departure-address");
  const arrivalAddressInput = document.getElementById("arrival-address");
  const departureDateInput = document.getElementById("departure-date");
  const departureTimeInput = document.getElementById("departure-time");
  const availableSeatsInput = document.getElementById("available-seats");
  const priceInput = document.getElementById("price");
  const vehicleSelect = document.getElementById("vehicle-select"); // Liste déroulante des véhicules

  // --- Variables pour stocker les données (véhicules du chauffeur) ---
  let chauffeurVehicles = [];

  // --- Fonction pour afficher un message temporaire (succès/erreur) ---
  function displayMessage(message, type = "info") {
    if (createRideMessageDiv) {
      createRideMessageDiv.textContent = message;
      createRideMessageDiv.className = `alert alert-${type}`; // alert-success, alert-danger, alert-info
      createRideMessageDiv.style.display = "block";

      // Masquer le message après 5 secondes (par exemple)
      setTimeout(() => {
        if (createRideMessageDiv) {
          createRideMessageDiv.style.display = "none";
          createRideMessageDiv.textContent = ""; // Vider le contenu
        }
      }, 5000);
    }
  }

  // --- Fonction pour charger les véhicules du chauffeur ---
  function fetchChauffeurVehicles() {
    console.log(
      "fetchChauffeurVehicles: Début du chargement des véhicules du chauffeur..."
    );
    // Nous allons réutiliser le script espace_utilisateur.php pour obtenir les données,
    // car il renvoie déjà user_vehicles pour l'utilisateur connecté.
    fetch("/backend/espace_utilisateur.php", {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => {
        console.log(
          "fetchChauffeurVehicles: Réponse reçue. Statut:",
          response.status
        );
        // Toujours essayer de parser la réponse comme JSON
        return response.json().then((data) => {
          data.status = response.status;
          return data;
        });
      })
      .then((data) => {
        console.log("fetchChauffeurVehicles: Données JSON traitées:", data);

        // Vérifier si l'utilisateur est connecté et est Chauffeur
        const isChauffeur =
          Array.isArray(data.user_roles) &&
          data.user_roles.includes("Chauffeur");
        if (data.status === 401) {
          // Non connecté - Rediriger vers la connexion
          displayMessage(
            "Vous devez être connecté pour proposer un covoiturage.",
            "warning"
          );
          setTimeout(() => {
            window.location.href = "connexion.html";
          }, 2000);
          return;
        }
        if (!isChauffeur) {
          // Non Chauffeur - Afficher un message et potentiellement rediriger
          displayMessage(
            "Seuls les chauffeurs peuvent proposer un covoiturage.",
            "danger"
          );
          // Masquer le formulaire
          if (createRideForm) createRideForm.style.display = "none";
          // Rediriger après un délai vers l'espace utilisateur par exemple
          setTimeout(() => {
            window.location.href = "espace.html";
          }, 5000);
          return;
        }

        // Si connecté et Chauffeur, traiter la liste des véhicules
        if (Array.isArray(data.user_vehicles)) {
          chauffeurVehicles = data.user_vehicles; // Stocker les véhicules
          console.log(
            "fetchChauffeurVehicles: Véhicules récupérés:",
            chauffeurVehicles
          );
          populateVehicleSelect(chauffeurVehicles); // Peupler la liste déroulante
        } else {
          console.warn(
            "fetchChauffeurVehicles: La réponse ne contient pas de tableau user_vehicles."
          );
          displayMessage(
            "Erreur lors du chargement de vos véhicules.",
            "danger"
          );
          // Optionnel : Masquer le formulaire si les véhicules ne peuvent pas être chargés
          // if (createRideForm) createRideForm.style.display = 'none';
        }
      })
      .catch((error) => {
        console.error(
          "fetchChauffeurVehicles: Erreur lors de l'appel fetch:",
          error
        );
        displayMessage(
          "Erreur de communication avec le serveur lors du chargement des véhicules.",
          "danger"
        );
        // Optionnel : Masquer le formulaire en cas d'erreur
        // if (createRideForm) createRideForm.style.display = 'none';
      });
  }

  // --- Fonction pour peupler la liste déroulante des véhicules ---
  function populateVehicleSelect(vehicles) {
    console.log(
      "populateVehicleSelect: Peuplement de la liste déroulante des véhicules:",
      vehicles
    );
    // Assurez-vous que vehicleSelect existe
    if (!vehicleSelect) {
      console.error("populateVehicleSelect: Élément vehicleSelect non trouvé.");
      return;
    }

    // Vider les options actuelles (sauf l'option par défaut)
    vehicleSelect.innerHTML =
      '<option value="">-- Sélectionner un véhicule --</option>';

    if (!Array.isArray(vehicles) || vehicles.length === 0) {
      // Si aucun véhicule, ajouter une option pour indiquer
      const noVehicleOption = document.createElement("option");
      noVehicleOption.value = ""; // Option vide
      noVehicleOption.textContent =
        "Aucun véhicule disponible. Ajoutez-en un dans Mon Espace.";
      noVehicleOption.disabled = true; // Désactiver cette option
      vehicleSelect.appendChild(noVehicleOption);
      // Potentiellement désactiver le bouton de soumission si aucun véhicule
      // const submitButton = createRideForm ? createRideForm.querySelector('button[type="submit"]') : null;
      // if (submitButton) submitButton.disabled = true;

      console.warn(
        "populateVehicleSelect: Aucun véhicule trouvé pour le chauffeur."
      );
    } else {
      // Ajouter une option pour chaque véhicule
      vehicles.forEach((vehicle) => {
        const option = document.createElement("option");
        // La valeur de l'option devrait être l'ID du véhicule (voiture_id)
        option.value = vehicle.voiture_id;
        // Le texte affiché
        option.textContent = `${vehicle.marque ?? "N/A"} ${
          vehicle.modele ?? "N/A"
        } (${vehicle.immatriculation ?? "N/A"})`;
        vehicleSelect.appendChild(option);
      });
    }
  }

  // --- Gestion de la soumission du formulaire ---
  if (createRideForm) {
    console.log("Ajout de l'écouteur 'submit' au formulaire createRideForm.");
    createRideForm.addEventListener("submit", (event) => {
      event.preventDefault(); // Empêcher la soumission par défaut
      console.log("Formulaire createRideForm soumis.");

      // Récupérer les données du formulaire
      const rideData = {
        lieu_depart: departureAddressInput
          ? departureAddressInput.value.trim()
          : "",
        lieu_arrivee: arrivalAddressInput
          ? arrivalAddressInput.value.trim()
          : "",
        date_depart: departureDateInput ? departureDateInput.value : "",
        heure_depart: departureTimeInput ? departureTimeInput.value : "",
        nombre_places: availableSeatsInput
          ? parseInt(availableSeatsInput.value)
          : 0, // Convertir en nombre
        prix: priceInput ? parseFloat(priceInput.value) : 0, // Convertir en nombre (pour crédits)
        voiture_id: vehicleSelect ? parseInt(vehicleSelect.value) : null, // Récupérer l'ID du véhicule sélectionné
      };

      console.log("Données du covoiturage à envoyer:", rideData);

      // --- Validation basique frontend ---
      if (
        !rideData.lieu_depart ||
        !rideData.lieu_arrivee ||
        !rideData.date_depart ||
        !rideData.heure_depart ||
        rideData.nombre_places <= 0 ||
        rideData.prix < 0 ||
        !rideData.voiture_id
      ) {
        displayMessage(
          "Veuillez remplir tous les champs obligatoires et vérifier les valeurs (places > 0, prix >= 0, véhicule sélectionné).",
          "warning"
        );
        console.warn("Validation frontend échouée:", rideData);
        return; // Arrêter la soumission si la validation échoue
      }

      // Appeler la fonction pour envoyer les données au backend
      createRide(rideData); // Cette fonction sera définie ensuite
    });
  }

  // --- Fonction pour envoyer les données du nouveau covoiturage au backend ---
  function createRide(rideData) {
    console.log(
      "createRide: Appel backend pour créer un covoiturage:",
      rideData
    );
    const submitButton = createRideForm
      ? createRideForm.querySelector('button[type="submit"]')
      : null;
    if (submitButton) submitButton.disabled = true; // Désactiver le bouton pendant la requête
    if (createRideMessageDiv) createRideMessageDiv.style.display = "none"; // Masquer les messages précédents

    // Appel au nouveau script backend
    fetch("/backend/create_ride.php", {
      // <-- CE SCRIPT DOIT ÊTRE CRÉÉ
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(rideData),
    })
      .then((response) => {
        console.log(
          "createRide: Réponse reçue du backend. Statut:",
          response.status
        );
        return response.json().then((data) => {
          data.status = response.status;
          return data;
        });
      })
      .then((data) => {
        console.log("createRide: Réponse JSON traitée:", data);
        // Réactiver le bouton de soumission
        if (submitButton) submitButton.disabled = false;

        if (data.status >= 200 && data.status < 300) {
          // Succès HTTP (2xx)
          if (data.success) {
            console.log("createRide: Covoiturage créé avec succès.");
            displayMessage(data.success, "success");
            // Optionnel : Réinitialiser le formulaire après succès
            if (createRideForm) createRideForm.reset();
            // Optionnel : Rediriger l'utilisateur vers la page de ses trajets (à créer plus tard)
            // setTimeout(() => { window.location.href = 'mes_trajets_chauffeur.html'; }, 2000);
          } else {
            console.warn(
              "createRide: Réponse de succès HTTP sans clé 'success'.",
              data
            );
            displayMessage(
              "Opération terminée, mais réponse inattendue.",
              "warning"
            );
          }
        } else {
          // Erreur HTTP (4xx, 5xx)
          if (data.error) {
            console.error("createRide: Erreur backend:", data.error);
            displayMessage(`Erreur : ${data.error}`, "danger");
          } else {
            console.error(
              "createRide: Erreur HTTP sans message backend.",
              data
            );
            displayMessage(`Erreur : Statut HTTP ${data.status}.`, "danger");
          }
        }
      })
      .catch((error) => {
        console.error(
          "createRide: Erreur lors de l'appel fetch ou du traitement initial:",
          error
        );
        // Réactiver le bouton de soumission
        if (submitButton) submitButton.disabled = false;
        displayMessage(
          "Une erreur de communication est survenue lors de la création du covoiturage.",
          "danger"
        );
      });
  }

  // --- Appel initial pour charger les véhicules ---
  fetchChauffeurVehicles(); // Lancer le chargement des véhicules dès que le DOM est prêt
}); // Fin de l'écouteur DOMContentLoaded
